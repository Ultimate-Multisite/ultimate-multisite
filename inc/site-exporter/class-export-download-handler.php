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
	 * The URL routes through the WordPress admin and requires `manage_network`
	 * capability. It cannot be guessed without a valid nonce.
	 *
	 * @since 2.5.1
	 *
	 * @param string $filename The export filename.
	 * @return string
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

		nocache_headers();

		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
		header('Content-Length: ' . $file_size);
		header('Content-Transfer-Encoding: binary');

		/*
		 * Flush any buffered output before streaming to avoid memory issues
		 * with large export files.
		 */
		if (ob_get_level()) {
			ob_end_clean();
		}

		$handle = fopen($file_path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ($handle) {
			while (! feof($handle)) {
				echo fread($handle, 8192); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread
				flush();
			}

			fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		exit;
	}
}
