<?php
/**
 * Network Exporter - bundles entire multisite network into single ZIP.
 *
 * @package WP_Ultimo\Site_Exporter
 * @since 2.5.0
 */

namespace WP_Ultimo\Site_Exporter;

use TenUp\MU_Migration\Commands\ExportCommand;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Network Exporter class.
 *
 * Orchestrates the export of an entire multisite network into a single ZIP file.
 *
 * @package WP_Ultimo\Site_Exporter
 * @since 2.5.0
 */
class Network_Exporter {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Staging directory for building the bundle.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected string $staging_dir;

	/**
	 * Included blog IDs.
	 *
	 * @since 2.5.0
	 * @var array
	 */
	protected array $included_blog_ids;

	/**
	 * Designated main site blog ID (for reassignment).
	 *
	 * @since 2.5.0
	 * @var int
	 */
	protected int $designated_main_site_blog_id;

	/**
	 * Export options.
	 *
	 * @since 2.5.0
	 * @var array
	 */
	protected array $options;

	/**
	 * Export result filename.
	 *
	 * @since 2.5.0
	 * @var string|false
	 */
	protected $result_filename = false;

	/**
	 * Export the entire network.
	 *
	 * @since 2.5.0
	 *
	 * @param array $included_blog_ids    Array of blog IDs to include.
	 * @param int   $main_site_blog_id    The designated main site blog ID (for reassignment).
	 * @param array $options              Export options (include_plugins, include_themes, include_uploads, include_mu_plugins).
	 * @return string|\WP_Error Filename on success, WP_Error on failure.
	 */
	public function export(array $included_blog_ids, int $main_site_blog_id, array $options = []) {

		$this->included_blog_ids            = $included_blog_ids;
		$this->designated_main_site_blog_id = $main_site_blog_id;
		$this->options                      = wp_parse_args(
			$options,
			[
				'include_plugins'    => true,
				'include_themes'     => true,
				'include_uploads'    => true,
				'include_mu_plugins' => true,
			]
		);

		// Validate inputs
		$validation_error = $this->validate_inputs();
		if (is_wp_error($validation_error)) {
			return $validation_error;
		}

		// Create staging directory
		$this->staging_dir = $this->create_staging_directory();
		if (is_wp_error($this->staging_dir)) {
			return $this->staging_dir;
		}

		try {
			// Step 1: Export network-level SQL (wp_blogs, wp_site, wp_sitemeta, etc.)
			$this->export_network_sql();

			// Step 2: Export global users and usermeta
			$this->export_users();

			// Step 3: Export each included site using mu-migration
			$this->export_sites();

			// Step 4: Export network-shared wp-content (plugins, themes, mu-plugins, uploads)
			$this->export_network_content();

			// Step 5: Create network.json manifest
			$this->create_network_manifest();

			// Step 6: Create the final ZIP
			$zip_result = $this->create_zip();
			if (is_wp_error($zip_result)) {
				return $zip_result;
			}

			// Cleanup staging directory
			$this->cleanup_staging_directory();

			return $this->result_filename;
		} catch (\Exception $e) {
			$this->cleanup_staging_directory();
			return new \WP_Error('export_failed', $e->getMessage());
		}
	}

	/**
	 * Validate inputs before export.
	 *
	 * @since 2.5.0
	 * @return true|\WP_Error
	 */
	protected function validate_inputs() {

		if (empty($this->included_blog_ids)) {
			return new \WP_Error('no_sites_selected', __('No sites selected for export.', 'ultimate-multisite'));
		}

		if (! in_array($this->designated_main_site_blog_id, $this->included_blog_ids, true)) {
			return new \WP_Error(
				'main_site_not_included',
				__('The designated main site must be in the list of included sites.', 'ultimate-multisite')
			);
		}

		return true;
	}

	/**
	 * Create staging directory under sys_get_temp_dir().
	 *
	 * @since 2.5.0
	 * @return string|\WP_Error
	 */
	protected function create_staging_directory() {

		$tmp_dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$rand    = wp_rand(10000, 99999);

		$staging_dir = $tmp_dir . 'wu-network-export-' . $rand . DIRECTORY_SEPARATOR;

		if (! mkdir($staging_dir, 0755, true)) {
			return new \WP_Error('staging_dir_failed', __('Failed to create staging directory.', 'ultimate-multisite'));
		}

		return $staging_dir;
	}

	/**
	 * Export network-level SQL (wp_blogs, wp_site, wp_sitemeta, etc.).
	 *
	 * @since 2.5.0
	 * @return void
	 */
	protected function export_network_sql() {

		global $wpdb;

		$sql_file = $this->staging_dir . 'network.sql';

		$sql_content  = "-- Network Export SQL\n";
		$sql_content .= '-- Exported at: ' . current_time('mysql') . "\n\n";

		// Export wp_blogs - only included blog IDs
		$blog_ids          = array_map('intval', $this->included_blog_ids);
		$blog_placeholders = implode(',', array_fill(0, count($blog_ids), '%d'));
		$blogs             = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $blog_placeholders is built from count($blog_ids), not user input.
				"SELECT * FROM {$wpdb->blogs} WHERE blog_id IN ($blog_placeholders)",
				$blog_ids
			),
			ARRAY_A
		);

		if (! empty($blogs)) {
			$sql_content .= "-- wp_blogs\n";
			$sql_content .= $this->array_to_insert_sql($wpdb->blogs, $blogs) . "\n\n";
		}

		// Export wp_site
		$site = $wpdb->get_row("SELECT * FROM {$wpdb->site}", ARRAY_A);
		if ($site) {
			$sql_content .= "-- wp_site\n";
			$sql_content .= $this->array_to_insert_sql($wpdb->site, [$site]) . "\n\n";
		}

		// Export wp_sitemeta - whitelist keys
		$sitemeta_keys = $this->get_sitemeta_whitelist();
		if (! empty($sitemeta_keys)) {
			$placeholders = implode(',', array_fill(0, count($sitemeta_keys), '%s'));
			$sitemeta     = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is built from count($sitemeta_keys), not user input.
					"SELECT * FROM {$wpdb->sitemeta} WHERE meta_key IN ($placeholders)",
					$sitemeta_keys
				),
				ARRAY_A
			);

			if (! empty($sitemeta)) {
				$sql_content .= "-- wp_sitemeta\n";
				$sql_content .= $this->array_to_insert_sql($wpdb->sitemeta, $sitemeta) . "\n\n";
			}
		}

		// Export wp_signups if it exists
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}signups'") === $wpdb->prefix . 'signups') {
			$signups = $wpdb->get_results("SELECT * FROM {$wpdb->signups}", ARRAY_A);
			if (! empty($signups)) {
				$sql_content .= "-- wp_signups\n";
				$sql_content .= $this->array_to_insert_sql($wpdb->prefix . 'signups', $signups) . "\n\n";
			}
		}

		// Export wp_registration_log if it exists
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}registration_log'") === $wpdb->prefix . 'registration_log') {
			$reg_log = $wpdb->get_results("SELECT * FROM {$wpdb->registration_log}", ARRAY_A);
			if (! empty($reg_log)) {
				$sql_content .= "-- wp_registration_log\n";
				$sql_content .= $this->array_to_insert_sql($wpdb->prefix . 'registration_log', $reg_log) . "\n\n";
			}
		}

		// Export wp_blogmeta if it exists (BerlinDB)
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}blogmeta'") === $wpdb->prefix . 'blogmeta') {
			$blog_ids_list = implode(',', array_map('intval', $this->included_blog_ids));
			$blogmeta      = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $blog_ids_list is built from intval blog IDs, not user input.
				$wpdb->prepare("SELECT * FROM {$wpdb->blogmeta} WHERE blog_id IN ({$blog_ids_list})"),
				ARRAY_A
			);

			if (! empty($blogmeta)) {
				$sql_content .= "-- wp_blogmeta\n";
				$sql_content .= $this->array_to_insert_sql($wpdb->prefix . 'blogmeta', $blogmeta) . "\n\n";
			}
		}

		// Export BerlinDB tables (WP_Ultimo tables) - these are global
		$this->export_berlinbd_tables($sql_content);

		file_put_contents($sql_file, $sql_content);
	}

	/**
	 * Export BerlinDB tables (WP_Ultimo global tables).
	 *
	 * @since 2.5.0
	 * @param string &$sql_content SQL content to append to.
	 * @return void
	 */
	protected function export_berlinbd_tables(&$sql_content) {

		global $wpdb;

		// Try to detect WP_Ultimo tables
		$wu_tables = [
			'wu_customers',
			'wu_memberships',
			'wu_products',
			'wu_subscriptions',
			'wu_payment_plans',
			'wu_api_keys',
			'wu_webhooks',
		];

		foreach ($wu_tables as $table) {
			$full_table = $wpdb->prefix . $table;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $full_table is built from $wpdb->prefix, not user input.
			if ($wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table) {
				$rows = $wpdb->get_results(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $full_table is built from $wpdb->prefix, not user input.
					"SELECT * FROM {$full_table}",
					ARRAY_A
				);
				if (! empty($rows)) {
					$sql_content .= "-- {$full_table}\n";
					$sql_content .= $this->array_to_insert_sql($full_table, $rows) . "\n\n";
				}
			}
		}
	}

	/**
	 * Get whitelist of sitemeta keys to include.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	protected function get_sitemeta_whitelist() {

		$default_keys = [
			'site_admins',
			'registration',
			'add_new_users',
			'fileupload_maxk',
			'siteurl',
			'home',
			'blogname',
			'blogdescription',
			'admin_email',
			'active_plugins',
			'themes',
			'template',
			'stylesheet',
			'comment_registration',
			'users_can_register',
			'admin_language',
			'language',
			'timezone',
			'date_format',
			'time_format',
			'start_of_week',
			'mu_plugins',
			'activated_plugins',
			'blog_public',
			'permalink',
			'category_base',
			'tag_base',
			'show_avatars',
			'avatar_rating',
			'avatar_default',
			'medium_size_w',
			'medium_size_h',
			'avatar_default',
			'large_size_w',
			'large_size_h',
			'thumbnail_size_w',
			'thumbnail_size_h',
			'thumbnail_crop',
			'medium_large_size_w',
			'medium_large_size_h',
			'global_terms_enabled',
			'ms_files_rewriting',
			'upload_filetypes',
			'blog_upload_space',
			'max_upload_size',
			'post_count',
			'default_comment_status',
			'default_ping_status',
			'default_pingback_flag',
			'comment_moderation',
			'comment_whitelist',
			'comment_max_links',
			'moderation_keys',
			'active_sitewide_plugins',
			'network_type',
			'wpmu_signup',
			'new_blog_template',
			'new_blogmeta',
		];

		/**
		 * Filter the whitelist of sitemeta keys to include in network export.
		 *
		 * @since 2.5.0
		 * @param array $keys Default whitelist keys.
		 * @return array
		 */
		return apply_filters('wu_network_export_sitemeta_keys', $default_keys);
	}

	/**
	 * Export global users and usermeta.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	protected function export_users() {

		global $wpdb;

		// Export wp_users - all users (global in multisite)
		$users = $wpdb->get_results("SELECT * FROM {$wpdb->users}", ARRAY_A);

		if (! empty($users)) {
			$csv_file = $this->staging_dir . 'users.csv';
			$this->array_to_csv($csv_file, $users);
		}

		// Export wp_usermeta - filter out blog-prefixed capability rows for excluded blogs
		$excluded_blog_ids = array_diff(
			get_sites(
				[
					'fields' => 'ids',
					'number' => PHP_INT_MAX,
				]
				),
			$this->included_blog_ids
		);

		$meta_key_where = '';
		if (! empty($excluded_blog_ids)) {
			$excluded_prefixes = array_map(
				function ($blog_id) use ($wpdb) {
					return $wpdb->prepare('meta_key NOT LIKE %s', $wpdb->base_prefix . $blog_id . '_%');
				},
				$excluded_blog_ids
			);
			$meta_key_where    = 'WHERE ' . implode(' AND ', $excluded_prefixes);
		}

		$usermeta = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $meta_key_where is built from excluded blog IDs, not user input.
			"SELECT * FROM {$wpdb->usermeta} {$meta_key_where}",
			ARRAY_A
		);

		if (! empty($usermeta)) {
			$csv_file = $this->staging_dir . 'usermeta.csv';
			$this->array_to_csv($csv_file, $usermeta);
		}
	}

	/**
	 * Export each included site using mu-migration.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	protected function export_sites() {

		$sites_dir = $this->staging_dir . 'sites' . DIRECTORY_SEPARATOR;
		mkdir($sites_dir, 0755, true);

		$export_command = new ExportCommand();

		foreach ($this->included_blog_ids as $blog_id) {
			$site_dir = $sites_dir . $blog_id . DIRECTORY_SEPARATOR;
			mkdir($site_dir, 0755, true);

			// Create a temp zip file for this site
			$tmp_dir  = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			$rand     = wp_rand(10000, 99999);
			$site_zip = $tmp_dir . 'mu-migration-site-' . $blog_id . '-' . $rand . '.zip';

			// Build assoc_args for mu-migration
			$assoc_args = [
				'blog_id' => $blog_id,
			];

			if ($this->options['include_plugins']) {
				$assoc_args['plugins'] = true;
			}

			if ($this->options['include_themes']) {
				$assoc_args['themes'] = true;
			}

			if ($this->options['include_uploads']) {
				$assoc_args['uploads'] = true;
			}

			// Call mu-migration export all
			// We need to temporarily change the output file
			$export_command->all([$site_zip], $assoc_args, false);

			// If the zip was created, extract it to the sites directory
			if (file_exists($site_zip)) {
				$this->extract_zip_to_directory($site_zip, $site_dir);
				unlink($site_zip);
			}
		}
	}

	/**
	 * Extract ZIP to a directory.
	 *
	 * @since 2.5.0
	 * @param string $zip_file Path to ZIP file.
	 * @param string $dest_dir  Destination directory.
	 * @return void
	 */
	protected function extract_zip_to_directory($zip_file, $dest_dir) {

		if (! class_exists('ZipArchive')) {
			return;
		}

		$zip    = new \ZipArchive();
		$result = $zip->open($zip_file);

		if (true === $result) {
			$zip->extractTo($dest_dir);
			$zip->close();
		}
	}

	/**
	 * Export network-shared wp-content (plugins, themes, mu-plugins, uploads).
	 *
	 * @since 2.5.0
	 * @return void
	 */
	protected function export_network_content() {

		$wp_content_dir = $this->staging_dir . 'wp-content' . DIRECTORY_SEPARATOR;
		mkdir($wp_content_dir, 0755, true);

		// Always include mu-plugins if present
		if ($this->options['include_mu_plugins']) {
			$mu_plugins_dir = WPMU_PLUGIN_DIR;
			if (is_dir($mu_plugins_dir)) {
				$dest = $wp_content_dir . 'mu-plugins';
				$this->copy_directory($mu_plugins_dir, $dest);
			}
		}

		// Include network-active plugins
		if ($this->options['include_plugins']) {
			$plugins_dir = WP_PLUGIN_DIR;
			if (is_dir($plugins_dir)) {
				$network_plugins = get_site_option('active_sitewide_plugins', []);
				if (! empty($network_plugins)) {
					$dest = $wp_content_dir . 'plugins';
					foreach ($network_plugins as $plugin => $timestamp) {
						$plugin_dir = dirname($plugins_dir . '/' . $plugin);
						if (is_dir($plugin_dir)) {
							$this->copy_directory($plugin_dir, $dest . '/' . basename($plugin_dir));
						}
					}
				}
			}
		}

		// Include network-installed themes
		if ($this->options['include_themes']) {
			$themes_dir = get_theme_root();
			if (is_dir($themes_dir)) {
				$dest = $wp_content_dir . 'themes';
				$this->copy_directory($themes_dir, $dest);
			}
		}

		// Include network-root uploads (not per-site)
		if ($this->options['include_uploads']) {
			$upload_dir = wp_upload_dir();
			$base_dir   = $upload_dir['basedir'];

			// Only include the base uploads dir, not per-site dirs
			// Per-site uploads are included in each site's bundle
			if (is_dir($base_dir)) {
				$dest = $wp_content_dir . 'uploads';
				$this->copy_directory($base_dir, $dest);
			}
		}
	}

	/**
	 * Copy directory recursively.
	 *
	 * @since 2.5.0
	 * @param string $src Source directory.
	 * @param string $dest Destination directory.
	 * @return void
	 */
	protected function copy_directory($src, $dest) {

		if (! is_dir($src)) {
			return;
		}

		if (! is_dir($dest)) {
			mkdir($dest, 0755, true);
		}

		$dir = opendir($src);
		while (($file = readdir($dir)) !== false) {
			if ('.' === $file || '..' === $file) {
				continue;
			}

			$src_path  = $src . DIRECTORY_SEPARATOR . $file;
			$dest_path = $dest . DIRECTORY_SEPARATOR . $file;

			if (is_dir($src_path)) {
				$this->copy_directory($src_path, $dest_path);
			} else {
				copy($src_path, $dest_path);
			}
		}
		closedir($dir);
	}

	/**
	 * Create network.json manifest.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	protected function create_network_manifest() {

		global $wpdb, $wp_version;

		$manifest = [
			'format_version'               => 1,
			'exported_at'                  => current_time('mysql', true),
			'exported_by_plugin_version'   => defined('WP_ULTIMO_VERSION') ? WP_ULTIMO_VERSION : 'unknown',
			'source'                       => [
				'url'                  => network_site_url(),
				'is_subdomain_install' => defined('SUBDOMAIN_INSTALL') ? SUBDOMAIN_INSTALL : false,
				'main_site_blog_id'    => defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : 1,
				'db_prefix'            => $wpdb->prefix,
				'wp_version'           => $wp_version,
				'php_version'          => PHP_VERSION,
			],
			'designated_main_site_blog_id' => $this->designated_main_site_blog_id,
			'included_blog_ids'            => $this->included_blog_ids,
			'excluded_blog_ids'            => array_diff(
				get_sites(
					[
						'fields' => 'ids',
						'number' => PHP_INT_MAX,
					]
					),
				$this->included_blog_ids
			),
			'network_active_plugins'       => array_keys(get_site_option('active_sitewide_plugins', [])),
			'sitemeta_keys_restored'       => $this->get_sitemeta_whitelist(),
			'options'                      => $this->options,
		];

		$json_file = $this->staging_dir . 'network.json';
		file_put_contents($json_file, wp_json_encode($manifest, JSON_PRETTY_PRINT));
	}

	/**
	 * Create the final ZIP file.
	 *
	 * @since 2.5.0
	 * @return string|\WP_Error
	 */
	protected function create_zip() {

		$upload_dir = wp_upload_dir();
		$export_dir = wu_maybe_create_folder('wu-site-exports');

		$date_str = gmdate('Y-m-d');
		$time_str = time();
		$rand     = wp_rand(1000, 9999);

		$filename = "wu-network-export-{$date_str}-{$time_str}-{$rand}.zip";
		$zip_path = trailingslashit($export_dir) . $filename;

		// Use ZipArchive to create the final ZIP
		if (! class_exists('ZipArchive')) {
			return new \WP_Error('zip_not_available', __('ZIP extension not available.', 'ultimate-multisite'));
		}

		$zip    = new \ZipArchive();
		$result = $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

		if (true !== $result) {
			return new \WP_Error('zip_create_failed', __('Failed to create ZIP file.', 'ultimate-multisite'));
		}

		// Add all files from staging directory
		$this->add_directory_to_zip($zip, $this->staging_dir, '');

		$zip->close();

		$this->result_filename = $filename;

		return $filename;
	}

	/**
	 * Add directory contents to ZIP recursively.
	 *
	 * @since 2.5.0
	 * @param ZipArchive $zip         ZipArchive instance.
	 * @param string     $source_dir  Source directory.
	 * @param string     $zip_path    Current path in ZIP.
	 * @return void
	 */
	protected function add_directory_to_zip($zip, $source_dir, $zip_path) {

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source_dir),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($files as $file) {
			if ($file->isDir()) {
				continue;
			}

			$file_path     = $file->getRealPath();
			$relative_path = substr($file_path, strlen($this->staging_dir));

			$zip->addFile($file_path, ltrim($relative_path, '/'));
		}
	}

	/**
	 * Cleanup staging directory.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	protected function cleanup_staging_directory() {

		if (! empty($this->staging_dir) && is_dir($this->staging_dir)) {
			$this->delete_directory($this->staging_dir);
		}
	}

	/**
	 * Delete directory recursively.
	 *
	 * @since 2.5.0
	 * @param string $dir Directory to delete.
	 * @return void
	 */
	protected function delete_directory($dir) {

		if (! is_dir($dir)) {
			return;
		}

		$files = array_diff(scandir($dir), ['.', '..']);
		foreach ($files as $file) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if (is_dir($path)) {
				$this->delete_directory($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}

	/**
	 * Convert array to INSERT SQL statements.
	 *
	 * @since 2.5.0
	 * @param string $table Table name.
	 * @param array  $rows  Array of row data.
	 * @return string
	 */
	protected function array_to_insert_sql($table, $rows) {

		if (empty($rows)) {
			return '';
		}

		global $wpdb;

		$sql         = '';
		$columns     = array_keys($rows[0]);
		$column_list = implode(
			', ',
			array_map(
			function ($col) use ($wpdb) {
				return '`' . $wpdb->escape($col) . '`';
			},
			$columns
			)
			);

		foreach ($rows as $row) {
			$values = array_map(
				function ($value) use ($wpdb) {
					if (is_null($value)) {
						return 'NULL';
					}
					return "'" . $wpdb->escape($value) . "'";
				},
				$row
				);

			$sql .= "INSERT INTO `{$table}` ({$column_list}) VALUES (" . implode(', ', $values) . ");\n";
		}

		return $sql;
	}

	/**
	 * Convert array to CSV file.
	 *
	 * @since 2.5.0
	 * @param string $csv_file Path to CSV file.
	 * @param array  $rows      Array of row data.
	 * @return void
	 */
	protected function array_to_csv($csv_file, $rows) {

		if (empty($rows)) {
			return;
		}

		$fp = fopen($csv_file, 'w');

		// Write header
		fputcsv($fp, array_keys($rows[0]));

		// Write data
		foreach ($rows as $row) {
			fputcsv($fp, $row);
		}

		fclose($fp);
	}
}
