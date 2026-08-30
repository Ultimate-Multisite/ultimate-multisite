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
	 * WebP RIFF header.
	 *
	 * @since 2.15.1
	 */
	const WEBP_RIFF_MAGIC = 'RIFF';

	/**
	 * WebP format header offset.
	 *
	 * @since 2.15.1
	 */
	const WEBP_FORMAT_OFFSET = 8;

	/**
	 * WebP format header.
	 *
	 * @since 2.15.1
	 */
	const WEBP_FORMAT_MAGIC = 'WEBP';

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
	 * Requests WebP when the active WordPress image editor supports it, and PNG
	 * otherwise. Free tier allows 50 requests/day without an API key.
	 *
	 * @since 2.0.11
	 *
	 * @param string $domain Original site domain.
	 * @param int    $width  Viewport width.  Default 1024.
	 * @param int    $height Viewport height. Default 768.
	 */
	public static function api_url($domain, int $width = self::DEFAULT_WIDTH, int $height = self::DEFAULT_HEIGHT): string {

		// Strip any pre-existing scheme to avoid `https://https://example.com`
		// when callers pass a full URL (e.g. `Site::get_active_site_url()` which
		// returns the URL with scheme for mapped domains).
		$clean_domain = preg_replace( '#^https?://#i', '', (string) $domain );

		$url = add_query_arg(
			[
				'url'             => 'https://' . $clean_domain,
				'screenshot'      => 'true',
				'screenshot.type' => self::get_screenshot_format(),
				'viewport.width'  => $width,
				'viewport.height' => $height,
				'embed'           => 'screenshot.url',
			],
			'https://api.microlink.io/'
		);

		return apply_filters('wu_screenshot_api_url', $url, $domain);
	}

	/**
	 * Returns the best screenshot format supported by the active image editor.
	 *
	 * @since 2.15.1
	 *
	 * @return string WebP when supported, otherwise PNG.
	 */
	public static function get_screenshot_format(): string {

		require_once ABSPATH . 'wp-admin/includes/image.php';

		return wp_image_editor_supports(['mime_type' => 'image/webp']) ? 'webp' : 'png';
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

		// Ensure the domain is passed with a scheme to thum.io so it does not
		// receive a malformed URL when the caller already passes a full URL.
		$clean_domain = preg_replace( '#^https?://#i', '', (string) $domain );
		$url          = 'https://image.thum.io/get/width/' . $width . '/crop/' . $height . '/noanimate/https://' . $clean_domain;

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
	 * Accepts PNG, JPEG, and WebP responses — the file extension and MIME type
	 * are determined from the actual response body, not assumed.
	 *
	 * @since 2.0.0
	 * @since 2.0.11 Accepts PNG, JPEG, and WebP; format auto-detected from response body.
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
				'timeout'    => 120,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			]
		);

		if (is_wp_error($response)) {
			wu_log_add('screenshot-generator', $log_prefix . $response->get_error_message(), LogLevel::WARNING);

			return false;
		}

		if (wp_remote_retrieve_response_code($response) !== 200) {
			wu_log_add('screenshot-generator', $log_prefix . wp_remote_retrieve_response_message($response), LogLevel::ERROR);

			return false;
		}

		$body = (string) wp_remote_retrieve_body($response);

		if ('' === $body) {
			wu_log_add('screenshot-generator', $log_prefix . __('Result is an empty screenshot; not saving.', 'ultimate-multisite'), LogLevel::ERROR);

			return false;
		}

		/*
		 * Detect image format from magic bytes.
		 */
		if (str_starts_with($body, self::PNG_MAGIC)) {
			$extension = 'png';
		} elseif (str_starts_with($body, self::JPEG_MAGIC)) {
			$extension = 'jpg';
		} elseif (self::WEBP_RIFF_MAGIC === substr($body, 0, strlen(self::WEBP_RIFF_MAGIC)) && self::WEBP_FORMAT_MAGIC === substr($body, self::WEBP_FORMAT_OFFSET, strlen(self::WEBP_FORMAT_MAGIC))) {
			$extension = 'webp';
		} else {
			wu_log_add('screenshot-generator', $log_prefix . __('Result is not a valid image file (expected PNG, JPEG, or WebP).', 'ultimate-multisite'), LogLevel::ERROR);

			return false;
		}

		if (self::is_blank_image($body, $extension)) {
			wu_log_add('screenshot-generator', $log_prefix . __('Result is a blank or single-colour screenshot; not saving.', 'ultimate-multisite'), LogLevel::ERROR);

			return false;
		}

		$upload = wp_upload_bits('screenshot-' . gmdate('Y-m-d-H-i-s') . '.' . $extension, null, $body);

		if ( ! empty($upload['error'])) {
			wu_log_add('screenshot-generator', $log_prefix . wp_json_encode($upload['error']), LogLevel::ERROR);

			return false;
		}

		$file_path = $upload['file'];

		if ( ! is_file($file_path) || 0 === filesize($file_path)) {
			if (is_file($file_path)) {
				wp_delete_file($file_path);
			}

			wu_log_add('screenshot-generator', $log_prefix . __('Uploaded screenshot is empty; not saving.', 'ultimate-multisite'), LogLevel::ERROR);

			return false;
		}

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
		$attach_id = wp_insert_attachment($post_info, $file_path, 0, true);

		if (is_wp_error($attach_id)) {
			wp_delete_file($file_path);
			wu_log_add('screenshot-generator', $log_prefix . $attach_id->get_error_message(), LogLevel::ERROR);

			return false;
		}

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
	 * Checks whether an image body is blank or contains a single solid colour.
	 *
	 * The scan stops as soon as both visible and varied pixels are found, which
	 * avoids rejecting mostly blank screenshots that contain real content.
	 * Images that cannot be decoded are rejected, except for WebP images that
	 * WordPress can process with a non-GD image editor.
	 *
	 * @since 2.14.2
	 *
	 * @param string $body      Raw image body.
	 * @param string $extension Detected image file extension.
	 * @return bool True when the image is unusable.
	 */
	private static function is_blank_image($body, $extension) {

		$image_info = @getimagesizefromstring($body); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid provider data is expected.

		if (false === $image_info) {
			return true;
		}

		if ( ! function_exists('imagecreatefromstring')) {
			return false;
		}

		$image = @imagecreatefromstring($body); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid provider data is expected.

		if (false === $image) {
			if ('webp' === $extension && 'webp' === self::get_screenshot_format()) {
				return false;
			}

			return true;
		}

		$width       = imagesx($image);
		$height      = imagesy($image);
		$first_color = null;
		$is_solid    = true;
		$transparent = true;

		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				$color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
				$rgba  = [$color['red'], $color['green'], $color['blue'], $color['alpha']];

				if (null === $first_color) {
					$first_color = $rgba;
				} elseif ($first_color !== $rgba) {
					$is_solid = false;
				}

				if ($color['alpha'] < 127) {
					$transparent = false;
				}

				if ( ! $is_solid && ! $transparent) {
					imagedestroy($image);

					return false;
				}
			}
		}

		imagedestroy($image);

		return $is_solid || $transparent;
	}
}
