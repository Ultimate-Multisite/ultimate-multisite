<?php
// phpcs:ignoreFile -- Legacy MUCD filename/API is kept for backwards compatibility; hotfix coverage uses syntax/probe tests.

/**
 * Multisite Ultimate Clone Duplicator main class.
 *
 * @package WP_Ultimo
 * @subpackage Duplication
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy MUCD filename kept for backwards compatibility.

defined('ABSPATH') || exit;

if ( ! class_exists('MUCD_Duplicate') ) {
	require_once WP_ULTIMO_PLUGIN_DIR . '/inc/duplication/option.php';
	require_once WP_ULTIMO_PLUGIN_DIR . '/inc/duplication/files.php';
	require_once WP_ULTIMO_PLUGIN_DIR . '/inc/duplication/data.php';
	require_once WP_ULTIMO_PLUGIN_DIR . '/inc/duplication/log.php';
	require_once WP_ULTIMO_PLUGIN_DIR . '/inc/duplication/functions.php';

	/**
	 * Multisite Ultimate Clone Duplicator main class.
	 *
	 * Coordinates the site duplication process by orchestrating file copying,
	 * database operations, and configuration updates.
	 */
	class MUCD_Duplicate {

		public static $log;

		/**
		 * Init static variables
		 *
		 * @since 0.2.0
		 */
		public static function init(): void {
			self::$log = false;
		}

		/**
		 * Main function of the plugin : duplicates a site
		 *
		 * @since 0.2.0
		 * @param  array $data parameters from form.
		 * @return array $form_message result messages of the process
		 */
		public static function duplicate_site($data) {

			global $wpdb;
			$form_message = [];
			$wpdb->hide_errors();

			self::init_log($data);

			$email        = $data['email'];
			$domain       = $data['domain'];
			$newdomain    = $data['newdomain'];
			$path         = $data['path'];
			$title        = $data['title'];
			$from_site_id = $data['from_site_id'];
			$keep_users   = $data['keep_users'];
			$copy_file    = $data['copy_files'];
			$public       = $data['public'];
			$network_id   = $data['network_id'];

			self::write_log('Start site duplication : from site ' . $from_site_id);
			self::write_log('Admin email : ' . $email);
			self::write_log('Domain : ' . $newdomain);
			self::write_log('Path : ' . $path);
			self::write_log('Site title : ' . $title);

			$user_id = isset($data['user_id']) && ! empty($data['user_id']) ? $data['user_id'] : self::create_admin($email, $domain);

			if ( is_wp_error($user_id) ) {
				$form_message['error'] = $user_id->get_error_message();
				return $form_message;
			}

			// Create new site
			switch_to_blog(1);
			$to_site_id = wpmu_create_blog($newdomain, $path, $title, $user_id, ['public' => $public], $network_id);
			$wpdb->show_errors();

			if ( is_wp_error($to_site_id) ) {
				$form_message['error'] = $to_site_id->get_error_message();
				return $form_message;
			}

			// User rights adjustments
			if ( ! is_super_admin($user_id) && ! get_user_option('primary_blog', $user_id) ) {
				update_user_option($user_id, 'primary_blog', $to_site_id, true);
			}

			self::bypass_server_limit();

			// Copy Site - File
			if ('yes' === $copy_file) {
				do_action('mucd_before_copy_files', $from_site_id, $to_site_id);
				$result = MUCD_Files::copy_files($from_site_id, $to_site_id);
				do_action('mucd_after_copy_files', $from_site_id, $to_site_id);
			}

			// Copy Site - Data
			do_action('mucd_before_copy_data', $from_site_id, $to_site_id);
			$result = MUCD_Data::copy_data($from_site_id, $to_site_id);
			do_action('mucd_after_copy_data', $from_site_id, $to_site_id);

			// Copy Site - Users
			if ('yes' === $keep_users) {
				do_action('mucd_before_copy_users', $from_site_id, $to_site_id);
				$result = self::copy_users($from_site_id, $to_site_id);
				do_action('mucd_after_copy_users', $from_site_id, $to_site_id);
			}

			update_blog_option($to_site_id, 'mucd_duplicable', 'no');

			$form_message['msg']     = MUCD_NETWORK_PAGE_DUPLICATE_NOTICE_CREATED;
			$form_message['site_id'] = $to_site_id;

			self::write_log('End site duplication : new site ID = ' . $to_site_id);

			wp_cache_flush();
			return $form_message;
		}

		/**
		 * Creates an admin user if no user exists with this email
		 *
		 * @since 0.2.0
		 * @param  string $email the email.
		 * @param  string $domain the domain.
		 * @return int id of the user
		 */
		public static function create_admin($email, $domain) {
			// Create New site Admin if not exists
			$password = 'N/A';

			$user_id = email_exists($email);

			if ( ! $user_id ) { // Create a new user with a random password
				$password = wp_generate_password(12, false);
				$user_id  = wpmu_create_user($domain, $password, $email);
				if ( false === $user_id ) {
					return new \WP_Error('file_copy', MUCD_NETWORK_PAGE_DUPLICATE_ADMIN_ERROR_CREATE_USER);
				} else {
					wp_new_user_notification($user_id);
				}
			}

			return $user_id;
		}

		/**
		 * Copy users and roles from one site to another
		 *
		 * @since 0.2.0
		 * @param  int $from_site_id duplicated site id.
		 * @param  int $to_site_id   new site id.
		 */
		public static function copy_users($from_site_id, $to_site_id): void {

			global $wpdb;

			if (is_main_site($from_site_id)) {
				$is_from_main_site = true;
				$args              = ['fields' => 'ids'];
				$all_sites_ids     = MUCD_Functions::get_sites($args);
				if ( ! empty($all_sites_ids)) {
					$all_sites_ids = array_map(fn($a) => $a[0], $all_sites_ids);
				}
			} else {
				$is_from_main_site = false;
			}

			// Source Site information
			$from_site_prefix        = $wpdb->get_blog_prefix($from_site_id);                    // prefix
			$from_site_prefix_length = strlen((string) $from_site_prefix);                           // prefix length

			// Destination Site information
			$to_site_prefix        = $wpdb->get_blog_prefix($to_site_id);                        // prefix
			$to_site_prefix_length = strlen((string) $to_site_prefix);

			$users = get_users('blog_id=' . $from_site_id);

			$admin_email = get_blog_option($to_site_id, 'admin_email', 'false');

			switch_to_blog($to_site_id);

			foreach ($users as $user) {
				if ($user->user_email !== $admin_email) {
					add_user_to_blog($to_site_id, $user->ID, 'subscriber');

					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Usermeta must be read raw to avoid unserializing incomplete objects.
					$all_meta = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d",
							$user->ID
						)
					);
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					foreach ($all_meta as $meta) {
						$metakey   = $meta->meta_key;
						$metavalue = $meta->meta_value;
						$prefix    = substr($metakey, 0, $from_site_prefix_length);
						if ($prefix === $from_site_prefix) {
							$raw_meta_name = substr($metakey, $from_site_prefix_length);
							if ($is_from_main_site) {
								$parts = explode('_', $raw_meta_name, 2);
								if (count($parts) > 1 && in_array($parts[0], $all_sites_ids, true)) {
									continue;
								}
							}

							self::copy_user_meta_value($user->ID, $to_site_prefix . $raw_meta_name, $metavalue);
						}
					}
				}
			}

			restore_current_blog();
		}

		/**
		 * Copies a user meta value while preserving incomplete serialized objects.
		 *
		 * @since 2.7.1
		 *
		 * @param int    $user_id        User ID.
		 * @param string $meta_key       Destination meta key.
		 * @param mixed  $raw_meta_value Raw source meta value from the database.
		 * @return void
		 */
		private static function copy_user_meta_value($user_id, $meta_key, $raw_meta_value): void {

			$meta_value = maybe_unserialize($raw_meta_value);

			if (self::has_incomplete_class($meta_value)) {
				self::update_user_meta_raw($user_id, $meta_key, $raw_meta_value);
				return;
			}

			update_user_meta($user_id, $meta_key, $meta_value);
		}

		/**
		 * Checks whether a value contains an incomplete PHP object.
		 *
		 * @since 2.7.1
		 *
		 * @param mixed                  $value Value to inspect.
		 * @param \SplObjectStorage|null $seen  Seen objects for recursion safety.
		 * @return bool
		 */
		private static function has_incomplete_class($value, ?\SplObjectStorage $seen = null): bool {

			if ($value instanceof \__PHP_Incomplete_Class) {
				return true;
			}

			if (is_array($value)) {
				foreach ($value as $nested_value) {
					if (self::has_incomplete_class($nested_value, $seen)) {
						return true;
					}
				}

				return false;
			}

			if ( ! is_object($value)) {
				return false;
			}

			$seen = $seen ?: new \SplObjectStorage();

			if ($seen->contains($value)) {
				return false;
			}

			$seen->attach($value);

			foreach (get_object_vars($value) as $nested_value) {
				if (self::has_incomplete_class($nested_value, $seen)) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Updates a user meta row without unserializing or re-serializing its value.
		 *
		 * @since 2.7.1
		 *
		 * @param int    $user_id        User ID.
		 * @param string $meta_key       Destination meta key.
		 * @param mixed  $raw_meta_value Raw source meta value from the database.
		 * @return void
		 */
		private static function update_user_meta_raw($user_id, $meta_key, $raw_meta_value): void {

			global $wpdb;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Raw write preserves serialized incomplete-object payloads.
			$meta_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT umeta_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
					$user_id,
					$meta_key
				)
			);

			if ($meta_id) {
				$wpdb->update(
					$wpdb->usermeta,
					[
						'meta_value' => $raw_meta_value,
					],
					[
						'umeta_id' => $meta_id,
					],
					[
						'%s',
					],
					[
						'%d',
					]
				);
			} else {
				$wpdb->insert(
					$wpdb->usermeta,
					[
						'user_id'    => $user_id,
						'meta_key'   => $meta_key,
						'meta_value' => $raw_meta_value,
					],
					[
						'%d',
						'%s',
						'%s',
					]
				);
			}
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

			wp_cache_delete($user_id, 'user_meta');
		}

		/**
		 * Init log object
		 *
		 * @since 0.2.0
		 * @param  array $data data from FORM.
		 */
		public static function init_log($data): void {
			// INIT LOG AND SAVE OPTION
			if (isset($data['log']) && 'yes' === $data['log'] ) {
				if (isset($data['log-path']) && ! empty($data['log-path'])) {
					$log_name = @gmdate('Y_m_d_His') . '-' . $data['domain'] . '.log'; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					if (! str_ends_with((string) $data['log-path'], '/')) {
						$data['log-path'] .= '/';
					}

					self::$log = new MUCD_Log(true, $data['log-path'], $log_name);
				}
			} else {
				self::$log = new MUCD_Log(false);
			}
		}

		/**
		 * Check if log is active
		 *
		 * @since 0.2.0
		 * @return boolean
		 */
		public static function log() {
			return (false !== self::$log && self::$log->can_write() && false !== self::$log->mod());
		}

		/**
		 * Check if log has error
		 *
		 * @since 0.2.0
		 * @return boolean
		 */
		public static function log_error() {
			return (false !== self::$log && ! (self::$log->can_write()) && false !== self::$log->mod());
		}

		/**
		 * Writes a message in log file
		 *
		 * @since 0.2.0
		 * @param  string $msg the message.
		 */
		public static function write_log($msg): void {
			if (self::log() !== false) {
				self::$log->write_log($msg);
			}
		}

		/**
		 * Close the log file
		 *
		 * @since 0.2.0
		 */
		public static function close_log(): void {
			if (self::log() !== false) {
				self::$log->close_log();
			}
		}

		/**
		 * Get the url of the created log file
		 *
		 * @since 0.2.0
		 * @return  string the url of false if no log file was created
		 */
		public static function log_url() {
			if (self::log() !== false) {
				return self::$log->file_url();
			}

			return false;
		}

		/**
		 * Get log directory
		 *
		 * @since 0.2.0
		 * @return string the path
		 */
		public static function log_dir() {
			return self::$log->dir_path();
		}

		/**
		 * Bypass limit server if possible
		 *
		 * @since 0.2.0
		 */
		public static function bypass_server_limit(): void {
			@ini_set('memory_limit', '1024M'); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.memory_limit_Disallowed
			set_time_limit(0); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}
	}

	MUCD_Duplicate::init();
}
