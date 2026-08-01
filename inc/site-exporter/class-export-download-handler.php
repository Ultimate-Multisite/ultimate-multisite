<?php
/**
 * Export Download Handler
 *
 * Serves export ZIP files through an authenticated WordPress endpoint,
 * preventing direct public URL access to sensitive site export archives.
 *
 * @package WP_Ultimo\Site_Exporter
 * @subpackage Site_Exporter
 * @author      WP Ultimo
 * @category    Security
 * @since       2.5.1
 */

namespace WP_Ultimo\Site_Exporter;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Export Download Handler.
 *
 * Registers a secure download endpoint for site export ZIP files that
 * streams files through PHP after verifying user capability and nonce.
 * This prevents direct web-server-level access to exported archives,
 * which may contain database dumps, user credentials, and media files.
 *
 * @package WP_Ultimo\Site_Exporter
 * @since   2.5.1
 */
class Export_Download_Handler {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Sets up the download handler hooks.
	 *
	 * @since 2.5.1
	 * @return void
	 */
	public function init(): void {

		add_action('admin_init', [$this, 'maybe_handle_download']);
	}

	/**
	 * Returns the nonce action string for a given export filename.
	 *
	 * @since 2.5.1
	 *
	 * @param string $filename The export filename.
	 * @return string
	 */
	public static function nonce_action(string $filename): string {

		return 'wu_download_export_' . $filename;
	}

	/**
	 * Generates an authenticated, nonce-protected download URL for an export file.
	 *
	 * Returns an HTML-escaped URL suitable for use in `href` attributes.
	 * `wp_nonce_url()` internally calls `esc_html()`, which converts `&` to
	 * `&amp;`. Always pass the return value through `esc_url()` when echoing
	 * it into HTML.
	 *
	 * For use in JavaScript/JSON contexts where the URL must contain literal
	 * `&` separators, call `raw_download_url()` instead.
	 *
	 * The URL routes through the WordPress admin and requires `manage_network`
	 * capability. It cannot be guessed without a valid nonce.
	 *
	 * @since 2.5.1
	 *
	 * @param string $filename The export filename.
	 * @return string HTML-escaped URL (ampersands as &amp;).
	 */
	public static function download_url(string $filename): string {

		return wp_nonce_url(
			add_query_arg(
				[
					'page'   => 'wu-site-export',
					'action' => 'download',
					'file'   => rawurlencode($filename),
				],
				network_admin_url('sites.php')
			),
			self::nonce_action($filename)
		);
	}

	/**
	 * Generates a raw (non-HTML-escaped) download URL for use in JSON or JS.
	 *
	 * Unlike `download_url()`, this method builds the URL with `add_query_arg()`
	 * and `wp_create_nonce()` directly, bypassing `wp_nonce_url()`'s internal
	 * `esc_html()` call. The result contains literal `&` separators, which is
	 * required for URLs embedded in `wp_send_json_success()` responses.
	 *
	 * @since 2.5.1
	 *
	 * @param string $filename The export filename.
	 * @return string Raw URL (ampersands as &, not &amp;).
	 */
	public static function raw_download_url(string $filename): string {

		return add_query_arg(
			[
				'page'     => 'wu-site-export',
				'action'   => 'download',
				'file'     => rawurlencode($filename),
				'_wpnonce' => wp_create_nonce(self::nonce_action($filename)),
			],
			network_admin_url('sites.php')
		);
	}

	/**
	 * Checks if the current request is an authenticated export download.
	 *
	 * Verifies `manage_network` capability, nonce, filename format, and file
	 * existence before streaming the file. Calls `wp_die()` on any failure.
	 *
	 * @since 2.5.1
	 * @return void
	 */
	public function maybe_handle_download(): void {

		$page   = wu_request('page', '');
		$action = wu_request('action', '');

		if ('wu-site-export' !== $page || 'download' !== $action) {
			return;
		}

		if (! current_user_can('manage_network')) {
			wp_die(
				esc_html__('You do not have permission to download exports.', 'ultimate-multisite'),
				esc_html__('Forbidden', 'ultimate-multisite'),
				['response' => 403]
			);
		}

		$file = sanitize_file_name(rawurldecode(wu_request('file', '')));

		if (! wp_verify_nonce(wu_request('_wpnonce'), self::nonce_action($file))) {
			wp_die(
				esc_html__('Security check failed.', 'ultimate-multisite'),
				esc_html__('Forbidden', 'ultimate-multisite'),
				['response' => 403]
			);
		}

		/*
		 * Validate filename format.
		 * Only allow files matching: wu-site-export-{ID}-{YYYY-MM-DD}-{timestamp}.zip
		 * This prevents path traversal and access to arbitrary files.
		 */
		if (! preg_match('/^wu-site-export-[0-9]+-[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]+\.zip$/', $file)) {
			wp_die(
				esc_html__('Invalid export file name.', 'ultimate-multisite'),
				esc_html__('Bad Request', 'ultimate-multisite'),
				['response' => 400]
			);
		}

		$file_path = trailingslashit(wu_maybe_create_folder('wu-site-exports')) . $file;

		if (! file_exists($file_path)) {
			wp_die(
				esc_html__('Export file not found.', 'ultimate-multisite'),
				esc_html__('Not Found', 'ultimate-multisite'),
				['response' => 404]
			);
		}

		$this->stream_file($file_path, $file);
	}

	/**
	 * Streams a file to the browser as a forced download.
	 *
	 * Sends appropriate HTTP headers and streams the file in 8 KB chunks,
	 * then exits. Does not allow the web server to serve the file directly.
	 *
	 * @since 2.5.1
	 *
	 * @param string $file_path The absolute server path to the file.
	 * @param string $filename  The filename to present in the Content-Disposition header.
	 * @return void
	 */
	private function stream_file(string $file_path, string $filename): void {

		if (headers_sent()) {
			wp_die(esc_html__('Cannot stream file: headers already sent.', 'ultimate-multisite'));
		}

		$file_size = (int) filesize($file_path);

		$this->prepare_streaming_environment();

		/*
		 * A HEAD request only needs validated metadata. Streaming a multi-GB
		 * archive on HEAD wastes server resources and can trip PHP/LiteSpeed
		 * buffering safeguards before the browser starts the real download.
		 */
		$request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
		$handle         = null;

		if ('head' !== $request_method) {
			$handle = @fopen($file_path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- A warning here would corrupt the download response.

			if (false === $handle) {
				error_log('WP Ultimo export download open error: ' . $filename); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Preserve the failed archive name for server administrators.

				wp_die(
					esc_html__('Cannot open the export file for download.', 'ultimate-multisite'),
					esc_html__('Download Failed', 'ultimate-multisite'),
					['response' => 500]
				);
			}
		}

		nocache_headers();
		status_header(200);

		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
		header('Content-Length: ' . $file_size);
		header('Content-Transfer-Encoding: binary');
		header('X-Content-Type-Options: nosniff');

		if ('head' === $request_method) {
			exit;
		}

		while (! feof($handle)) {
			$chunk = fread($handle, 8192); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread

			if (false === $chunk) {
				error_log('WP Ultimo export download read error: ' . $filename); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Preserve the failed archive name for server administrators.
				break;
			}

			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary ZIP stream.
			flush();

			if (connection_aborted()) {
				break;
			}
		}

		fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		exit;
	}

	/**
	 * Prepares PHP to stream large export archives without buffering them in memory.
	 *
	 * Some hosts leave multiple output buffers active in wp-admin requests. Cleaning
	 * only one buffer causes large ZIP downloads to accumulate in the remaining
	 * buffer until PHP exhausts memory. Close every removable buffer before sending
	 * binary file chunks.
	 *
	 * @since 2.5.1
	 *
	 * @param int $minimum_level Lowest output buffer level to preserve. Used by tests.
	 * @return void
	 */
	private function prepare_streaming_environment(int $minimum_level = 0): void {

		if (function_exists('ignore_user_abort')) {
			ignore_user_abort(true);
		}

		if (function_exists('set_time_limit')) {
			@set_time_limit(0); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort for large authenticated downloads.
		}

		if (function_exists('ini_set')) {
			@ini_set('zlib.output_compression', 'Off'); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky -- Best-effort for binary streams.
			@ini_set('output_buffering', 'Off'); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky -- Best-effort for binary streams.
		}

		while (ob_get_level() > $minimum_level) {
			$status = ob_get_status();

			if (isset($status['del']) && ! $status['del']) {
				break;
			}

			if (! @ob_end_clean()) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Avoid corrupting download output if a host buffer refuses cleanup.
				break;
			}
		}
	}
}
