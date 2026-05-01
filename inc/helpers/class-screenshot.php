<?php
/**
 * Takes screenshots from websites.
 *
 * @package WP_Ultimo
 * @subpackage Helper
 * @since 2.0.0
 */

namespace WP_Ultimo\Helpers;

use Psr\Log\LogLevel;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Takes screenshots from websites.
 *
 * @since 2.0.0
 */
class Screenshot {

	/**
	 * JPEG file signature (magic bytes). Every valid JPEG starts with these three bytes.
	 *
	 * @since 2.0.11
	 */
	const JPEG_MAGIC = "\xFF\xD8\xFF";

	/**
	 * GIF file signature (magic bytes). mShots returns an 8.7 KB GIF "loading"
	 * placeholder while the real screenshot is being generated.
	 *
	 * @since 2.0.11
	 */
	const GIF_MAGIC = "\x47\x49\x46";

	/**
	 * Returns the api link for the screenshot.
	 *
	 * @since 2.0.0
	 *
	 * @param string $domain Original site domain.
	 */
	public static function api_url($domain): string {

		$url = 'https://s.wordpress.com/mshots/v1/' . rawurlencode('https://' . $domain) . '?w=1280';

		return apply_filters('wu_screenshot_api_url', $url, $domain);
	}

	/**
	 * Takes in a URL and creates it as an attachment.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url Image URL to download.
	 * @return string|false
	 */
	public static function take_screenshot($url) {

		$url = self::api_url($url);

		return self::save_image_from_url($url);
	}

	/**
	 * Downloads the image from the URL and saves it as a WordPress attachment.
	 *
	 * Delegates the HTTP fetch (with mShots GIF-placeholder retry) to
	 * {@see Screenshot::fetch_image_body()}.
	 *
	 * @since 2.0.0
	 * @since 2.0.11 HTTP fetch extracted into fetch_image_body() with GIF retry logic.
	 *
	 * @param string $url Image URL to download.
	 * @return int|false Attachment ID on success, false on failure.
	 */
	public static function save_image_from_url($url) {

		// translators: %s is the API URL.
		$log_prefix = sprintf(__('Downloading image from "%s":', 'ultimate-multisite'), $url) . ' ';

		$body = self::fetch_image_body($url, $log_prefix);

		if (false === $body) {
			return false;
		}

		$upload = wp_upload_bits('screenshot-' . gmdate('Y-m-d-H-i-s') . '.jpg', null, $body);

		if ( ! empty($upload['error'])) {
			wu_log_add('screenshot-generator', $log_prefix . wp_json_encode($upload['error']), LogLevel::ERROR);

			return false;
		}

		$file_path        = $upload['file'];
		$file_name        = basename($file_path);
		$file_type        = wp_check_filetype($file_name, null);
		$attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
		$wp_upload_dir    = wp_upload_dir();

		$post_info = [
			'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
			'post_mime_type' => $file_type['type'],
			'post_title'     => $attachment_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		// Create the attachment
		$attach_id = wp_insert_attachment($post_info, $file_path);

		// Include image.php
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Define attachment metadata
		$attach_data = wp_generate_attachment_metadata($attach_id, $file_path);

		// Assign metadata to attachment
		wp_update_attachment_metadata($attach_id, $attach_data);

		wu_log_add('screenshot-generator', $log_prefix . __('Success!', 'ultimate-multisite'));

		return $attach_id;
	}

	/**
	 * Fetches the raw image body from a URL, retrying when mShots returns a GIF placeholder.
	 *
	 * mShots (s.wordpress.com/mshots/v1/) returns an ~8.7 KB GIF "loading" placeholder on the
	 * first request to a URL it has not recently cached; the real JPEG arrives 2–30 seconds
	 * later on subsequent requests. This method detects the GIF signature and retries up to
	 * five times with increasing wait intervals before giving up.
	 *
	 * HTTP errors (non-200 status or WP_Error) abort immediately without further retries.
	 *
	 * The retry schedule can be customised via the {@see 'wu_mshots_retry_delays'} filter.
	 *
	 * @since 2.0.11
	 *
	 * @param string $url        Image URL to fetch.
	 * @param string $log_prefix Log message prefix for contextual logging.
	 * @return string|false Raw JPEG image bytes on success, false on failure.
	 */
	protected static function fetch_image_body(string $url, string $log_prefix) {

		/**
		 * Filters the retry delay schedule (in seconds) used when mShots returns a GIF placeholder.
		 *
		 * The array defines wait times before each successive retry attempt.  The first HTTP
		 * request is always immediate; each element adds one retry attempt after sleeping the
		 * given number of seconds.  Pass an empty array to disable retries entirely.
		 *
		 * Example — shorten delays for unit tests:
		 *   add_filter( 'wu_mshots_retry_delays', fn() => [ 0, 0 ] );
		 *
		 * @since 2.0.11
		 *
		 * @param int[] $delays Seconds to wait before each successive retry. Default [3, 5, 8, 12, 15].
		 */
		$retry_delays = (array) apply_filters('wu_mshots_retry_delays', [3, 5, 8, 12, 15]);

		/*
		 * Build the full attempt schedule: one immediate first attempt (delay 0) followed
		 * by one attempt per entry in $retry_delays.
		 *   e.g. [0, 3, 5, 8, 12, 15] → 6 total attempts.
		 */
		$all_delays  = array_merge([0], $retry_delays);
		$total       = count($all_delays);

		foreach ($all_delays as $attempt_index => $delay) {

			if ($delay > 0) {
				sleep($delay); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.sleep_sleep
			}

			$response = wp_remote_get(
				$url,
				[
					'timeout'    => 50,
					'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				]
			);

			if (is_wp_error($response)) {
				wu_log_add('screenshot-generator', $log_prefix . $response->get_error_message(), LogLevel::ERROR);

				return false;
			}

			if (wp_remote_retrieve_response_code($response) !== 200) {
				wu_log_add('screenshot-generator', $log_prefix . wp_remote_retrieve_response_message($response), LogLevel::ERROR);

				return false;
			}

			$body = $response['body'];

			/*
			 * Success: the response is a JPEG — return the body for saving.
			 */
			if (str_starts_with($body, self::JPEG_MAGIC)) {
				return $body;
			}

			/*
			 * mShots GIF placeholder detected. Log and retry if attempts remain,
			 * otherwise log the final failure and fall through to return false.
			 */
			if (str_starts_with($body, self::GIF_MAGIC)) {

				$is_last_attempt = ($attempt_index + 1 >= $total);

				if ($is_last_attempt) {
					wu_log_add(
						'screenshot-generator',
						$log_prefix . __('mShots still returning loading placeholder after all retries.', 'ultimate-multisite'),
						LogLevel::ERROR
					);
				} else {
					wu_log_add(
						'screenshot-generator',
						$log_prefix . sprintf(
							/* translators: 1: current attempt number, 2: total attempts, 3: seconds until next retry */
							__('mShots loading placeholder on attempt %1$d of %2$d, retrying in %3$ds.', 'ultimate-multisite'),
							$attempt_index + 1,
							$total,
							$retry_delays[$attempt_index]
						)
					);
				}

				continue;
			}

			/*
			 * Unexpected body format — neither JPEG nor GIF. Do not retry.
			 */
			wu_log_add('screenshot-generator', $log_prefix . __('Result is not a JPEG file.', 'ultimate-multisite'), LogLevel::ERROR);

			return false;
		}

		return false;
	}
}
