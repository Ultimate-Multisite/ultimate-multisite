<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Site duplication data helpers.
 *
 * @package WP_Ultimo
 * @subpackage Duplication
 * @since 0.2.0
 */

use Psr\Log\LogLevel;

defined('ABSPATH') || exit;

if ( ! class_exists('MUCD_Data') ) {

	/**
	 * Multisite Ultimate Clone Duplicator Data class.
	 *
	 * Handles database operations for site duplication, including copying
	 * and updating table data between sites.
	 */
	class MUCD_Data {

		private static $to_site_id;

		/**
		 * Copy and Update tables from a site to another
		 *
		 * @since 0.2.0
		 * @param  int $from_site_id Duplicated site id.
		 * @param  int $to_site_id   New site id.
		 */
		public static function copy_data($from_site_id, $to_site_id): void {
			self::$to_site_id = $to_site_id;

			// Copy
			$saved_options = self::db_copy_tables($from_site_id, $to_site_id);
			self::db_copy_blog_meta($from_site_id, $to_site_id);
			// Update
			self::db_update_data($from_site_id, $to_site_id, $saved_options);
		}

		/**
		 * Copy blog meta data from source site to target site.
		 *
		 * Deletes existing meta data for the target site and copies all
		 * meta data from the source site.
		 *
		 * @since 0.2.0
		 * @param int $from_site_id Source site ID.
		 * @param int $to_site_id   Target site ID.
		 */
		public static function db_copy_blog_meta($from_site_id, $to_site_id): void {

			global $wpdb;

			// Delete everything
			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				_get_meta_table('blog'),
				[
					'blog_id' => $to_site_id,
				]
			);

			$meta = get_site_meta($from_site_id);

			foreach ($meta as $meta_key => $list) {
				foreach ($list as $value) {
					add_site_meta($to_site_id, $meta_key, $value);
				}
			}
		}

		/**
		 * Copy tables from a site to another
		 *
		 * @since 0.2.0
		 * @param  int $from_site_id Duplicated site id.
		 * @param  int $to_site_id   New site id.
		 */
		public static function db_copy_tables($from_site_id, $to_site_id) {
			global $wpdb;

			// Source Site information
			$from_site_prefix        = $wpdb->get_blog_prefix($from_site_id);                    // prefix
			$from_site_prefix_length = strlen((string) $from_site_prefix);                           // prefix length

			// Destination Site information
			$to_site_prefix        = $wpdb->get_blog_prefix($to_site_id);                        // prefix
			$to_site_prefix_length = strlen((string) $to_site_prefix);                               // prefix length

			// Options that should be preserved in the new blog.
			$saved_options = MUCD_Option::get_saved_option();

			foreach ($saved_options as $option_name => $option_value) {
				$saved_options[ $option_name ] = get_blog_option($to_site_id, $option_name);
			}

			// Bugfix : escape '_' , '%' and '/' character for mysql 'like' queries
			$from_site_prefix_like = $wpdb->esc_like($from_site_prefix);

			// SCHEMA - TO FIX for HyperDB
			$schema = DB_NAME;

			// Get sources Tables
			if ((int) MUCD_PRIMARY_SITE_ID === (int) $from_site_id) {
				$from_site_table = self::get_primary_tables($from_site_prefix);
			} else {
				$sql_query       = $wpdb->prepare('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s', $schema, $from_site_prefix_like . '%');
				$from_site_table = self::do_sql_query($sql_query, 'col');
			}

			if (empty($from_site_table)) {
				$from_site_table = self::get_existing_blog_tables($from_site_id);
			}

			foreach ($from_site_table as $table) {
				$table_base_name = substr((string) $table, $from_site_prefix_length);

				if ( ! self::should_copy_table($table, $table_base_name, $from_site_id, $to_site_id)) {
					continue;
				}

				$table_name = $to_site_prefix . $table_base_name;

				$wpdb->get_results('SET foreign_key_checks = 0'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				// Drop table if exists
				self::do_sql_query('DROP TABLE IF EXISTS `' . $table_name . '`');

				// Create new table from source table
				// self::do_sql_query('CREATE TABLE IF NOT EXISTS `' . $table_name . '` LIKE `' . $schema . '`.`' . $table . '`');

				$create_statement = self::do_sql_query('SHOW CREATE TABLE `' . $table . '`', 'row_array');

				$create_statement_sql = str_replace($from_site_prefix, $to_site_prefix, (string) $create_statement[1]);

				self::do_sql_query($create_statement_sql);

				// Populate database with data from source table
				self::do_sql_query('INSERT `' . $table_name . '` SELECT * FROM `' . $table . '`');

				$wpdb->get_results('SET foreign_key_checks = 1'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}

			// apply key options from new blog.
			self::db_restore_data($to_site_id, $saved_options);

			return $saved_options;
		}

		/**
		 * Determine whether a source table should be copied to the duplicated site.
		 *
		 * Runtime-only tables are skipped by default because queues, logs, caches,
		 * analytics, and sessions are regenerated on the destination site. Empty
		 * optional/custom tables are also skipped to avoid spending most of the
		 * duplication time cloning schemas with no template data. WordPress core
		 * content/config tables are always copied because wpmu_create_blog() creates
		 * destination defaults that must be replaced with template values.
		 *
		 * @since 2.5.1
		 *
		 * @param string $table           Full source table name.
		 * @param string $table_base_name Source table name without blog prefix.
		 * @param int    $from_site_id    Source site ID.
		 * @param int    $to_site_id      Target site ID.
		 * @return bool Whether the table should be copied.
		 */
		public static function should_copy_table($table, $table_base_name, $from_site_id, $to_site_id) {

			$copy = true;

			if (self::is_runtime_table($table_base_name)) {
				$copy = false;
			} elseif (self::should_skip_empty_table($table, $table_base_name, $from_site_id, $to_site_id)) {
				$copy = false;
			}

			/**
			 * Filter whether a source table should be copied during site duplication.
			 *
			 * Returning true forces a table to be copied; returning false skips it.
			 * This preserves extension control over custom table duplication while
			 * keeping the default path optimized for empty/runtime template tables.
			 *
			 * @since 2.5.1
			 *
			 * @param bool   $copy            Whether the table should be copied.
			 * @param string $table           Full source table name.
			 * @param string $table_base_name Source table name without blog prefix.
			 * @param int    $from_site_id    Source site ID.
			 * @param int    $to_site_id      Target site ID.
			 */
			return (bool) apply_filters('wu_mucd_should_copy_table', $copy, $table, $table_base_name, $from_site_id, $to_site_id);
		}

		/**
		 * Check whether a source table is runtime-only data that should not be cloned.
		 *
		 * @since 2.5.1
		 *
		 * @param string $table_base_name Source table name without blog prefix.
		 * @return bool Whether the table is runtime-only.
		 */
		public static function is_runtime_table($table_base_name) {

			$runtime_tables = [
				'actionscheduler_actions',
				'actionscheduler_claims',
				'actionscheduler_groups',
				'actionscheduler_logs',
				'blc_filters',
				'blc_instances',
				'blc_links',
				'blc_synch',
				'ewwwio_images',
				'icl_background_task',
				'icl_translate_job',
				'icl_translation_batches',
				'litespeed_avatar',
				'litespeed_img_optm',
				'litespeed_img_optming',
				'litespeed_url',
				'litespeed_url_file',
				'quform_entries',
				'quform_entry_data',
				'quform_entry_entry_labels',
				'quform_entry_labels',
				'redirection_404',
				'redirection_logs',
				'woocommerce_api_keys',
				'woocommerce_downloadable_product_permissions',
				'woocommerce_log',
				'woocommerce_sessions',
				'yoast_indexable',
				'yoast_indexable_hierarchy',
				'yoast_migrations',
				'yoast_primary_term',
				'yoast_seo_links',
				'yoast_seo_meta',
			];

			/**
			 * Filter runtime-only table base names skipped during site duplication.
			 *
			 * @since 2.5.1
			 *
			 * @param array $runtime_tables Runtime-only table base names.
			 */
			$runtime_tables = (array) apply_filters('wu_mucd_runtime_tables_to_ignore', $runtime_tables);

			return in_array($table_base_name, $runtime_tables, true);
		}

		/**
		 * Determine whether an empty source table can be skipped.
		 *
		 * @since 2.5.1
		 *
		 * @param string $table           Full source table name.
		 * @param string $table_base_name Source table name without blog prefix.
		 * @param int    $from_site_id    Source site ID.
		 * @param int    $to_site_id      Target site ID.
		 * @return bool Whether the table should be skipped because it is empty.
		 */
		private static function should_skip_empty_table($table, $table_base_name, $from_site_id, $to_site_id) {

			if ( ! self::skip_empty_tables($from_site_id, $to_site_id)) {
				return false;
			}

			if (self::is_required_table($table_base_name)) {
				return false;
			}

			return ! self::table_has_rows($table);
		}

		/**
		 * Check whether empty optional/custom tables should be skipped.
		 *
		 * @since 2.5.1
		 *
		 * @param int $from_site_id Source site ID.
		 * @param int $to_site_id   Target site ID.
		 * @return bool Whether empty optional/custom tables should be skipped.
		 */
		private static function skip_empty_tables($from_site_id, $to_site_id) {

			/**
			 * Filter whether empty optional/custom source tables are skipped.
			 *
			 * @since 2.5.1
			 *
			 * @param bool $skip_empty_tables Whether to skip empty optional tables.
			 * @param int  $from_site_id      Source site ID.
			 * @param int  $to_site_id        Target site ID.
			 */
			return (bool) apply_filters('wu_mucd_skip_empty_tables', true, $from_site_id, $to_site_id);
		}

		/**
		 * Check whether a table is required for a faithful template clone.
		 *
		 * @since 2.5.1
		 *
		 * @param string $table_base_name Source table name without blog prefix.
		 * @return bool Whether the table should be copied even when empty.
		 */
		private static function is_required_table($table_base_name) {

			$required_tables = [
				'commentmeta',
				'comments',
				'links',
				'options',
				'postmeta',
				'posts',
				'term_relationships',
				'term_taxonomy',
				'termmeta',
				'terms',
			];

			/**
			 * Filter required table base names copied even when empty.
			 *
			 * @since 2.5.1
			 *
			 * @param array $required_tables Required table base names.
			 */
			$required_tables = (array) apply_filters('wu_mucd_required_tables_to_copy', $required_tables);

			return in_array($table_base_name, $required_tables, true);
		}

		/**
		 * Check whether a source table has rows.
		 *
		 * @since 2.5.1
		 *
		 * @param string $table Full source table name.
		 * @return bool Whether the source table has rows.
		 */
		private static function table_has_rows($table) {
			global $wpdb;

			$wpdb->last_error = '';
			$has_rows         = self::do_sql_query('SELECT 1 FROM `' . $table . '` LIMIT 1', 'var', false);

			if ('' !== $wpdb->last_error) {
				return true;
			}

			return null !== $has_rows;
		}

		/**
		 * Get tables to copy if duplicated site is primary site
		 *
		 * @since 0.2.0
		 * @param  string $from_site_prefix DB prefix of duplicated site.
		 * @return array  Array of table names to copy.
		 */
		public static function get_primary_tables($from_site_prefix) {

			$default_tables = MUCD_Option::get_primary_tables_to_copy();

			foreach ($default_tables as $k => $default_table) {
				$default_tables[ $k ] = $from_site_prefix . $default_table;
			}

			return $default_tables;
		}

		/**
		 * Get source blog tables that exist on the current database connection.
		 *
		 * WordPress PHPUnit uses temporary per-blog tables for multisite fixtures.
		 * Those tables can be described and copied in the current connection, but
		 * they do not appear in INFORMATION_SCHEMA or SHOW TABLES. Falling back to
		 * the canonical WordPress blog table list keeps duplication working in tests
		 * while preserving the INFORMATION_SCHEMA path for production custom tables.
		 *
		 * @since 2.5.1
		 *
		 * @param int $site_id Source site ID.
		 * @return array Existing source blog table names.
		 */
		private static function get_existing_blog_tables($site_id) {

			global $wpdb;

			$tables   = [];
			$site_id  = (int) $site_id;
			$blog_set = $wpdb->tables('blog', true, $site_id);

			foreach ($blog_set as $table_name) {
				$suppress_errors = $wpdb->suppress_errors();
				$exists          = (bool) $wpdb->get_results('DESCRIBE `' . $table_name . '`'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table names cannot be bound and temporary PHPUnit tables are intentionally probed.
				$wpdb->suppress_errors($suppress_errors);

				if ($exists) {
					$tables[] = $table_name;
				}
			}

			return $tables;
		}


		/**
		 * Updated tables from a site to another
		 *
		 * @since 0.2.0
		 * @param  int   $from_site_id  Duplicated site id.
		 * @param  int   $to_site_id    New site id.
		 * @param  array $saved_options Saved options from source site.
		 */
		public static function db_update_data($from_site_id, $to_site_id, $saved_options): void {

			global $wpdb;

			$to_blog_prefix = $wpdb->get_blog_prefix($to_site_id);

			// Looking for uploads dirs
			switch_to_blog($from_site_id);

			$dir                       = wp_upload_dir();
			$from_upload_url_w_network = $dir['baseurl'];
			$from_upload_url           = str_replace(network_site_url(), get_bloginfo('url') . '/', $dir['baseurl']);
			$from_blog_url             = get_blog_option($from_site_id, 'siteurl');

			restore_current_blog();

			switch_to_blog($to_site_id);

			$dir                     = wp_upload_dir();
			$to_upload_url_w_network = $dir['baseurl'];
			$to_upload_url           = str_replace(network_site_url(), get_bloginfo('url') . '/', $dir['baseurl']);
			$to_blog_url             = get_blog_option($to_site_id, 'siteurl');

			restore_current_blog();

			$tables = [];

			// Bugfix : escape '_' , '%' and '/' character for mysql 'like' queries
			$to_blog_prefix_like = $wpdb->esc_like($to_blog_prefix);

			$results = self::do_sql_query('SHOW TABLES LIKE \'' . $to_blog_prefix_like . '%\'', 'col', false);

			foreach ( $results as $k => $v ) {
				$tables[ str_replace($to_blog_prefix, '', (string) $v) ] = [];
			}

			foreach ( $tables as $table => $col) {
				$results = self::do_sql_query('SHOW COLUMNS FROM `' . $to_blog_prefix . $table . '`', 'col', false);

				$columns = [];

				foreach ( $results as $k => $v ) {
					$columns[] = $v;
				}

				$tables[ $table ] = $columns;
			}

			$default_tables = MUCD_Option::get_fields_to_update();

			foreach ( $default_tables as $table => $field) {
				$tables[ $table ] = $field;
			}

			$from_site_prefix = $wpdb->get_blog_prefix($from_site_id);
			$to_site_prefix   = $wpdb->get_blog_prefix($to_site_id);

			$from_upload_url_clean           = wu_replace_scheme($from_upload_url);
			$to_upload_url_clean             = wu_replace_scheme($to_upload_url);
			$from_upload_url_w_network_clean = wu_replace_scheme($from_upload_url_w_network);
			$to_upload_url_w_network_clean   = wu_replace_scheme($to_upload_url_w_network);
			$from_blog_url_clean             = wu_replace_scheme($from_blog_url);
			$to_blog_url_clean               = wu_replace_scheme($to_blog_url);
			$from_upload_path_segment        = 'wp-content/uploads/sites/' . (int) $from_site_id;
			$to_upload_path_segment          = 'wp-content/uploads/sites/' . (int) $to_site_id;

			$string_to_replace = [
				$from_upload_url_clean           => $to_upload_url_clean,
				$from_upload_url_w_network_clean => $to_upload_url_w_network_clean,
				$from_upload_path_segment        => $to_upload_path_segment,
				$from_blog_url_clean             => $to_blog_url_clean,
				$from_site_prefix                => $to_site_prefix,
			];

			// Add JSON-escaped versions of URLs (forward slashes escaped as \/).
			// Page builders like Elementor store URLs in JSON where / becomes \/.
			$json_replacements = [
				str_replace('/', '\\/', $from_upload_url_clean)           => str_replace('/', '\\/', $to_upload_url_clean),
				str_replace('/', '\\/', $from_upload_url_w_network_clean) => str_replace('/', '\\/', $to_upload_url_w_network_clean),
				str_replace('/', '\\/', $from_upload_path_segment)        => str_replace('/', '\\/', $to_upload_path_segment),
				str_replace('/', '\\/', $from_blog_url_clean)             => str_replace('/', '\\/', $to_blog_url_clean),
			];

			$string_to_replace = array_merge($string_to_replace, $json_replacements);

			$string_to_replace = apply_filters('mucd_string_to_replace', $string_to_replace, $from_site_id, $to_site_id);

			foreach ( $tables as $table => $field) {
				foreach ( $string_to_replace as $from_string => $to_string) {
					self::update($to_blog_prefix . $table, $field, $from_string, $to_string);
				}
			}

			self::db_restore_data($to_site_id, $saved_options);
		}

		/**
		 * Restore options that should be preserved in the new blog
		 *
		 * @since 0.2.0
		 * @param  int   $to_site_id    Target site id.
		 * @param  array $saved_options Saved options to restore.
		 */
		public static function db_restore_data($to_site_id, $saved_options): void {

			switch_to_blog($to_site_id);

			foreach ( $saved_options as $option_name => $option_value ) {
				try {
						update_option($option_name, $option_value);
				} catch (\Throwable $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
					// ...nothing
				}
			}

			restore_current_blog();
		}

		/**
		 * Get the primary key column name for a table.
		 *
		 * Uses a static cache to avoid repeated SHOW KEYS queries for the
		 * same table across multiple replacement passes.
		 *
		 * @since 2.3.1
		 * @param  string $table Full table name.
		 * @return string|null   Primary key column name, or null if not found.
		 */
		public static function get_primary_key($table) {
			static $cache = [];

			if (array_key_exists($table, $cache)) {
				return $cache[ $table ];
			}

			global $wpdb;

			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be bound as placeholders.
			);

			$cache[ $table ] = $row ? $row->Column_name : null; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Column_name is returned by SHOW KEYS.

			return $cache[ $table ];
		}

		/**
		 * Updates a table
		 *
		 * Identifies rows by primary key when available so that large
		 * serialized values (like Elementor Kit settings) are not used
		 * in the WHERE clause—preventing mismatches and accidental
		 * multi-row updates.
		 *
		 * @since 0.2.0
		 * @param  string $table       Table to update.
		 * @param  array  $fields      Fields to update.
		 * @param  string $from_string Original string to replace.
		 * @param  string $to_string   New string.
		 */
		public static function update($table, $fields, $from_string, $to_string): void {
			if (is_array($fields) || ! empty($fields)) {
				global $wpdb;

				$pk_column = self::get_primary_key($table);

				foreach ($fields as $field) {

					// Bugfix : escape '_' , '%' and '/' character for mysql 'like' queries
					$from_string_like = $wpdb->esc_like($from_string);

					$wpdb->query("SET SQL_MODE='ALLOW_INVALID_DATES';"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

					// Include primary key in SELECT when available for reliable row identification.
					if ($pk_column) {
						$sql_query = $wpdb->prepare(
							'SELECT `' . $pk_column . '`, `' . $field . '` FROM `' . $table . '` WHERE `' . $field . '` LIKE %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.QuotedSimplePlaceholder
							'%' . $from_string_like . '%'
						);
					} else {
						$sql_query = $wpdb->prepare(
							'SELECT `' . $field . '` FROM `' . $table . '` WHERE `' . $field . '` LIKE %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.QuotedSimplePlaceholder
							'%' . $from_string_like . '%'
						);
					}

					$results = self::do_sql_query($sql_query, 'results', false);

					if ($results) {
						foreach ($results as $row) {
							$old_value = $row[ $field ];
							$new_value = self::try_replace($row, $field, $from_string, $to_string);

							// Skip UPDATE when the replacement produced no change.
							if ($new_value === $old_value) {
								continue;
							}

							if ($pk_column && isset($row[ $pk_column ])) {
								$update_sql = $wpdb->prepare(
									'UPDATE `' . $table . '` SET `' . $field . '` = %s WHERE `' . $pk_column . '` = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
									$new_value,
									$row[ $pk_column ]
								);
							} else {
								$update_sql = $wpdb->prepare(
									'UPDATE `' . $table . '` SET `' . $field . '` = %s WHERE `' . $field . '` = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
									$new_value,
									$old_value
								);
							}

							self::do_sql_query($update_sql);
						}
					}
				}
			}
		}

		/**
		 * Replace $from_string with $to_string in $val
		 *
		 * @since 0.2.0
		 * @param  string $val         Original value to modify.
		 * @param  string $from_string String to replace.
		 * @param  string $to_string   Replacement string.
		 * @return string The new string.
		 */
		public static function replace($val, $from_string, $to_string) {
			if (is_string($val)) {
				// Guard: if the target string is already present and the source
				// is not, skip replacement to prevent double-substitution.
				if ($from_string !== $to_string && strpos($val, $to_string) !== false && strpos($val, $from_string) === false) {
					return $val;
				}

				return str_replace($from_string, $to_string, $val);
			}

			return $val;
		}

		/**
		 * Replace recursively $from_string with $to_string in $val
		 *
		 * @since 0.2.0
		 * @param  mixed  $val         Original value (string or array) to modify.
		 * @param  string $from_string String to replace.
		 * @param  string $to_string   Replacement string.
		 * @return mixed  The modified value.
		 */
		public static function replace_recursive($val, $from_string, $to_string) {
			$unset = [];
			if (is_array($val)) {
				foreach ($val as $k => $v) {
					$val[ $k ] = self::try_replace($val, $k, $from_string, $to_string);
				}
			} else {
				$val = self::replace($val, $from_string, $to_string);
			}

			foreach ($unset as $k) {
				unset($val[ $k ]);
			}

			return $val;
		}

		/**
		 * Try to replace $from_string with $to_string in a row
		 *
		 * @since 0.2.0
		 * @param  array  $row         The row data.
		 * @param  string $field       The field name.
		 * @param  string $from_string String to replace.
		 * @param  string $to_string   Replacement string.
		 * @return mixed  The modified data.
		 */
		public static function try_replace($row, $field, $from_string, $to_string) {
			if (is_serialized($row[ $field ])) {
				$double_serialize = false;
				$original_value   = $row[ $field ];
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Required for legacy serialized option/meta replacement during site duplication.
				$row[ $field ] = @unserialize($row[ $field ], ['allowed_classes' => false]);

				// Safety: if unserialize failed, return the original value
				// instead of re-serializing false — which would destroy the data.
				if (false === $row[ $field ] && 'b:0;' !== $original_value) {
					return $original_value;
				}

				// FOR SERIALISED OPTIONS, like in wp_carousel plugin
				if (is_serialized($row[ $field ])) {
					// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Required for legacy double-serialized option/meta replacement during site duplication.
					$inner_unserialized = @unserialize($row[ $field ], ['allowed_classes' => false]);

					if (false === $inner_unserialized && 'b:0;' !== $row[ $field ]) {
						// Inner unserialize failed — fall back to single-serialized handling.
						$double_serialize = false;
					} else {
						$row[ $field ]    = $inner_unserialized;
						$double_serialize = true;
					}
				}

				if ($row[ $field ] instanceof __PHP_Incomplete_Class) {
					return $original_value;
				}

				if (is_array($row[ $field ])) {
					$row[ $field ] = self::replace_recursive($row[ $field ], $from_string, $to_string);
				} elseif (is_object($row[ $field ])) {
					$array_object = (array) $row[ $field ];
					$array_object = self::replace_recursive($array_object, $from_string, $to_string);
					foreach ($array_object as $key => $value) {
						try {
							$row[ $field ]->$key = $value;
						} catch (\Throwable $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Best-effort object property replacement.
							// ...nothing
						}
					}
				} else {
					$row[ $field ] = self::replace($row[ $field ], $from_string, $to_string);
				}

				$row[ $field ] = serialize($row[ $field ]); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Required for legacy serialized option/meta replacement during site duplication.

				// Pour des options comme wp_carousel...
				if ($double_serialize) {
					$row[ $field ] = serialize($row[ $field ]); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Required for legacy double-serialized option/meta replacement during site duplication.
				}
			} elseif (is_array($row[ $field ])) {
				$row[ $field ] = self::replace_recursive($row[ $field ], $from_string, $to_string);
			} elseif ($row[ $field ] instanceof __PHP_Incomplete_Class) {
				return $row[ $field ];
			} elseif (is_object($row[ $field ])) {
				$array_object = (array) $row[ $field ];
				$array_object = self::replace_recursive($array_object, $from_string, $to_string);
				foreach ($array_object as $key => $value) {
					try {
						$row[ $field ]->$key = $value;
					} catch (\Throwable $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Best-effort object property replacement.
						// ...nothing
					}
				}
			} else {
				$row[ $field ] = self::replace($row[ $field ], $from_string, $to_string);
			}

			return $row[ $field ];
		}

		/**
		 * Runs a WPDB query
		 *
		 * @since 0.2.0
		 * @param  string $sql_query The SQL query to execute.
		 * @param  string $type      Type of result to return.
		 * @param  bool   $log       Whether to log the query.
		 * @return mixed  Results of the query.
		 */
		public static function do_sql_query($sql_query, $type = '', $log = true) {
			global $wpdb;

			$wpdb->suppress_errors();

			switch ($type) {
				case 'col':
					$results = $wpdb->get_col($sql_query);  // phpcs:ignore WordPress.DB
					break;
				case 'row':
					$results = $wpdb->get_row($sql_query);  // phpcs:ignore WordPress.DB
					break;
				case 'row_array':
					$results = $wpdb->get_row($sql_query, ARRAY_N); // phpcs:ignore WordPress.DB
					break;
				case 'var':
					$results = $wpdb->get_var($sql_query); // phpcs:ignore WordPress.DB
					break;
				case 'results':
					$results = $wpdb->get_results($sql_query, ARRAY_A); // phpcs:ignore WordPress.DB
					break;
				default:
					$results = $wpdb->query($sql_query); // phpcs:ignore WordPress.DB
					break;
			}

			if ($log) {
				MUCD_Duplicate::write_log('SQL :' . $sql_query);
				MUCD_Duplicate::write_log('Result :' . var_export($results, true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			}

			if ('' !== $wpdb->last_error) {
				self::sql_error($sql_query, $wpdb->last_error);
			}

			$wpdb->suppress_errors(false);

			return $results;
		}

		/**
		 * Stop process on SQL Error, print and log error, removes the new blog
		 *
		 * @since 0.2.0
		 * @param  string $sql_query The SQL query that failed.
		 * @param  string $sql_error The error message.
		 */
		public static function sql_error($sql_query, $sql_error): void {
			wu_log_add('site-duplication-errors', sprintf('Got error "%s" while running: %s', $sql_error, $sql_query), LogLevel::ERROR);
		}
	}
}
