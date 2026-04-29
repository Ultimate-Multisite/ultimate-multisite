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
	 * Downloads the image from the URL.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url Image URL to download.
	 * @return int|false
	 */
	public static function save_image_from_url($url) {

		// translators: %s is the API URL.
		$log_prefix = sprintf(__('Downloading image from "%s":', 'ultimate-multisite'), $url) . ' ';

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 50,
			]
		);

		if (wp_remote_retrieve_response_code($response) !== 200) {
			wu_log_add('screenshot-generator', $log_prefix . wp_remote_retrieve_response_message($response), LogLevel::ERROR);

			return false;
		}

		if (is_wp_error($response)) {
			wu_log_add('screenshot-generator', $log_prefix . $response->get_error_message(), LogLevel::ERROR);

			return false;
		}

		/*
		 * Check if the results contain a JPEG header.
		 */
		if (! str_starts_with($response['body'], "\xFF\xD8\xFF")) {
			wu_log_add('screenshot-generator', $log_prefix . __('Result is not a JPEG file.', 'ultimate-multisite'), LogLevel::ERROR);

			return false;
		}

		$upload = wp_upload_bits('screenshot-' . gmdate('Y-m-d-H-i-s') . '.jpg', null, $response['body']);

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
}
