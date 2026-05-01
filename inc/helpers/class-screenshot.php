<?php
/**
 * Takes screenshots from websites.
 *
 * Uses Microlink as the primary screenshot provider (free, no API key, supports
 * viewport dimensions) with thum.io as a fallback.
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
	 * PNG file signature (magic bytes).
	 *
	 * @since 2.0.0
	 */
	const PNG_MAGIC = "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a";

	/**
	 * JPEG file signature (magic bytes).
	 *
	 * @since 2.0.11
	 */
	const JPEG_MAGIC = "\xFF\xD8\xFF";

	/**
	 * Default viewport width for screenshots.
	 *
	 * @since 2.0.11
	 */
	const DEFAULT_WIDTH = 1024;

	/**
	 * Default viewport height for screenshots.
	 *
	 * @since 2.0.11
	 */
	const DEFAULT_HEIGHT = 768;

	/**
	 * Returns the primary (Microlink) API URL for a screenshot.
	 *
	 * Microlink returns a PNG image directly when the `embed=screenshot.url`
	 * parameter is used. Free tier allows 50 requests/day without an API key.
	 *
	 * @since 2.0.11
	 *
	 * @param string $domain Original site domain.
	 * @param int    $width  Viewport width.  Default 1024.
	 * @param int    $height Viewport height. Default 768.
	 */
	public static function api_url($domain, int $width = self::DEFAULT_WIDTH, int $height = self::DEFAULT_HEIGHT): string {

		$url = add_query_arg(
			[
				'url'              => 'https://' . $domain,
				'screenshot'       => 'true',
				'viewport.width'   => $width,
				'viewport.height'  => $height,
				'embed'            => 'screenshot.url',
			],
			'https://api.microlink.io/'
		);

		return apply_filters('wu_screenshot_api_url', $url, $domain);
	}

	/**
	 * Returns the fallback (thum.io) API URL for a screenshot.
	 *
	 * @since 2.0.11
	 *
	 * @param string $domain Original site domain.
	 * @param int    $width  Image width. Default 1024.
	 * @param int    $height Crop height. Default 768.
	 */
	public static function fallback_api_url($domain, int $width = self::DEFAULT_WIDTH, int $height = self::DEFAULT_HEIGHT): string {

		$url = 'https://image.thum.io/get/width/' . $width . '/crop/' . $height . '/noanimate/' . $domain;

		/**
		 * Filters the fallback screenshot API URL.
		 *
		 * @since 2.0.11
		 *
		 * @param string $url    The fallback API URL.
		 * @param string $domain The site domain.
		 */
		return apply_filters('wu_screenshot_fallback_api_url', $url, $domain);
	}

	/**
	 * Takes in a URL and creates it as an attachment.
	 *
	 * Tries the primary provider (Microlink) first, then falls back to thum.io
	 * if the primary fails.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url Image URL to download.
	 * @return int|false Attachment ID on success, false on failure.
	 */
	public static function take_screenshot($url) {

		$primary_api_url = self::api_url($url);

		$result = self::save_image_from_url($primary_api_url);

		if (false !== $result) {
			return $result;
		}

		wu_log_add('screenshot-generator', __('Primary provider (Microlink) failed, trying fallback (thum.io).', 'ultimate-multisite'));

		$fallback_api_url = self::fallback_api_url($url);

		return self::save_image_from_url($fallback_api_url);
	}

	/**
	 * Downloads the image from the URL and saves it as a WordPress attachment.
	 *
	 * Accepts both PNG and JPEG responses — the file extension and MIME type
	 * are determined from the actual response body, not assumed.
	 *
	 * @since 2.0.0
	 * @since 2.0.11 Accepts both PNG and JPEG; format auto-detected from response body.
	 *
	 * @param string $url Image URL to download.
	 * @return int|false Attachment ID on success, false on failure.
	 */
	public static function save_image_from_url($url) {

		// translators: %s is the API URL.
		$log_prefix = sprintf(__('Downloading image from "%s":', 'ultimate-multisite'), $url) . ' ';

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
		 * Detect image format from magic bytes.
		 */
		if (str_starts_with($body, self::PNG_MAGIC)) {
			$extension = 'png';
		} elseif (str_starts_with($body, self::JPEG_MAGIC)) {
			$extension = 'jpg';
		} else {
			wu_log_add('screenshot-generator', $log_prefix . __('Result is not a valid image file (expected PNG or JPEG).', 'ultimate-multisite'), LogLevel::ERROR);

			return false;
		}

		$upload = wp_upload_bits('screenshot-' . gmdate('Y-m-d-H-i-s') . '.' . $extension, null, $body);

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
