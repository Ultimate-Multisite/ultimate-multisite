<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Files class from MUCD.
 */

defined('ABSPATH') || exit;

/*
 * MUCD_Files::copy_files() references MUCD_PRIMARY_SITE_ID at runtime (see
 * copy_files() below). That constant is otherwise only defined in
 * inc/helpers/class-site-duplicator.php, which is not guaranteed to have loaded
 * when copy_files() is invoked through every code path (e.g. async actions or a
 * direct programmatic call). Under PHP 8.x an undefined constant is a fatal
 * Error, so copy_files() dies mid-loop and the destination site ends up with an
 * EMPTY uploads/ directory — every template image on the cloned site 404s.
 *
 * Define it here, idempotently, so the constant is always available wherever
 * this class is loaded. Mirrors the definition in class-site-duplicator.php.
 */
if ( ! defined('MUCD_PRIMARY_SITE_ID') ) {
	define('MUCD_PRIMARY_SITE_ID', function_exists('get_current_network_id') ? (int) get_current_network_id() : 1); // phpcs:ignore
}

if ( ! class_exists('MUCD_Files') ) {

	/**
	 * Multisite Ultimate Clone Duplicator Files class.
	 *
	 * Handles file operations for site duplication, including copying
	 * uploads and other media files between sites.
	 */
	class MUCD_Files {

		/**
		 * Copy files from one site to another
		 *
		 * @since 0.2.0
		 * @param  int $from_site_id duplicated site id.
		 * @param  int $to_site_id   new site id.
		 */
		public static function copy_files($from_site_id, $to_site_id) {
			/*
			 * Two switch_to_blog() calls are pushed onto the WordPress blog
			 * stack to read uploads info from the source and destination sites,
			 * so two restore_current_blog() calls must follow to fully unwind
			 * the stack. Previously only one restore was issued, leaving the
			 * caller's blog context pointing at the template/source site after
			 * copy_files() returned.
			 *
			 * The leak broke downstream code that relies on the network/admin
			 * context — most visibly the site_published email pipeline. When
			 * a customer signed up with a template the publish flow ran on the
			 * template's blog context, so wp_mail() picked up the template
			 * site's options (and per-site SMTP plugins) instead of the
			 * network's, causing welcome emails to silently fail. Sending a
			 * test email from the network admin worked because the admin
			 * request never went through duplication.
			 *
			 * @since 2.7.2
			 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/1163
			 */

			// Switch to Source site and get uploads info
			switch_to_blog($from_site_id);
			$wp_upload_info   = wp_upload_dir();
			$from_dir['path'] = $wp_upload_info['basedir'];
			MUCD_PRIMARY_SITE_ID === (int) $from_site_id ? $from_dir['exclude'] = MUCD_Option::get_primary_dir_exclude() : $from_dir['exclude'] = []; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude

			// Switch to Destination site and get uploads info
			switch_to_blog($to_site_id);
			$wp_upload_info = wp_upload_dir();
			$to_dir         = $wp_upload_info['basedir'];

			// Pop both switches: the destination, then the source.
			restore_current_blog();
			restore_current_blog();

			$dirs   = [];
			$dirs[] = [
				'from_dir_path' => $from_dir['path'],
				'to_dir_path'   => $to_dir,
				'exclude_dirs'  => $from_dir['exclude'],
			];

			$dirs = apply_filters('mucd_copy_dirs', $dirs, $from_site_id, $to_site_id);

			foreach ($dirs as $dir) {
				if (isset($dir['to_dir_path']) && ! self::init_dir($dir['to_dir_path'])) {
					self::mkdir_error($dir['to_dir_path'], $to_site_id);
				}

				MUCD_Duplicate::write_log('Copy files from ' . $dir['from_dir_path'] . ' to ' . $dir['to_dir_path']);
				self::recurse_copy($dir['from_dir_path'], $dir['to_dir_path'], $dir['exclude_dirs']);
			}

			return true;
		}

		/**
		 * Copy files from one directory to another
		 *
		 * @since 0.2.0
		 * @param  string $src source directory path.
		 * @param  string $dst destination directory path.
		 * @param  array  $exclude_dirs directories to ignore.
		 */
		public static function recurse_copy($src, $dst, $exclude_dirs = []): void {
			global $wp_filesystem;

			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$src = rtrim($src, '/');
			$dst = rtrim($dst, '/');

			if ( ! $wp_filesystem->is_dir($src) ) {
				return;
			}

			if ( ! $wp_filesystem->is_dir($dst) ) {
				$wp_filesystem->mkdir($dst);
			}

			$files = $wp_filesystem->dirlist($src);

			if ( ! $files ) {
				return;
			}

			foreach ( $files as $file ) {
				$src_path = $src . '/' . $file['name'];
				$dst_path = $dst . '/' . $file['name'];

				if ( 'd' === $file['type'] ) {
					if ( ! in_array($file['name'], $exclude_dirs, true) ) {
						self::recurse_copy($src_path, $dst_path, $exclude_dirs);
					}
				} else {
					$wp_filesystem->copy($src_path, $dst_path);
				}
			}
		}

		/**
		 * Set a directory writable, creates it if not exists, or return false
		 *
		 * @since 0.2.0
		 * @param  string $path the path.
		 * @return boolean True on success, False on failure
		 */
		public static function init_dir($path) {
			if ( ! function_exists('WP_Filesystem')) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			/** @var $wp_filesystem WP_Filesystem_Base */
			global $wp_filesystem;
			WP_Filesystem();
			if ( ! $wp_filesystem->exists($path)) {
				return $wp_filesystem->mkdir($path, 0777);
			} elseif (! $wp_filesystem->is_writable($path)) {
				return $wp_filesystem->chmod($path, 0777, true);
			}
			return true;
		}

		/**
		 * Removes a directory and all its content
		 *
		 * @since 0.2.0
		 * @param string $dir the path.
		 */
		public static function rrmdir($dir): void {
			if ( ! function_exists('WP_Filesystem')) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			/** @var $wp_filesystem WP_Filesystem_Base */
			global $wp_filesystem;
			WP_Filesystem();
			$wp_filesystem->rmdir($dir, true);
		}

		/**
		 * Stop process on Creating dir Error, print and log error, removes the new blog
		 *
		 * @since 0.2.0
		 * @param string $dir_path the path.
		 * @param int    $to_site_id the site id.
		 */
		public static function mkdir_error($dir_path, $to_site_id): void {
			$error_1 = 'ERROR DURING FILE COPY : CANNOT CREATE ' . $dir_path;
			MUCD_Duplicate::write_log($error_1);
			$error_2 = sprintf(MUCD_NETWORK_PAGE_DUPLICATE_COPY_FILE_ERROR, MUCD_Functions::get_primary_upload_dir());
			MUCD_Duplicate::write_log($error_2);
			MUCD_Duplicate::write_log('Duplication interrupted on FILE COPY ERROR');
			echo '<br />Duplication failed :<br /><br />' . esc_html($error_1) . '<br /><br />' . esc_html($error_2) . '<br /><br />';
			$log_url = MUCD_Duplicate::log_url();
			if ( $log_url ) {
				echo '<a href="' . esc_attr($log_url) . '">' . esc_html(MUCD_NETWORK_PAGE_DUPLICATE_VIEW_LOG) . '</a>';
			}

			MUCD_Functions::remove_blog($to_site_id);
			wp_die();
		}
	}
}
