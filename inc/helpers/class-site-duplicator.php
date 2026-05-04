<?php
/**
 * Exposes the public API to handle site duplication.
 *
 * @package WP_Ultimo
 * @subpackage Helper
 * @since 2.0.0
 */

namespace WP_Ultimo\Helpers;

use Psr\Log\LogLevel;

// Exit if accessed directly
defined('ABSPATH') || exit;

require_once WP_ULTIMO_PLUGIN_DIR . '/inc/duplication/duplicate.php';

if ( ! defined('MUCD_PRIMARY_SITE_ID')) {
	define('MUCD_PRIMARY_SITE_ID', get_current_network_id()); // phpcs:ignore
}
if ( ! defined('MUCD_NETWORK_PAGE_DUPLICATE_COPY_FILE_ERROR')) {
	// translators: %s the file path that failed.
	define('MUCD_NETWORK_PAGE_DUPLICATE_COPY_FILE_ERROR', __('Failed to copy files : check permissions on <strong>%s</strong>', 'ultimate-multisite')); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
}
if ( ! defined('MUCD_NETWORK_PAGE_DUPLICATE_VIEW_LOG')) {
	define('MUCD_NETWORK_PAGE_DUPLICATE_VIEW_LOG', __('View log', 'ultimate-multisite')); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
}
if ( ! defined('MUCD_MAX_NUMBER_OF_SITE')) {
	define('MUCD_MAX_NUMBER_OF_SITE', 5000); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
}

/**
 * Exposes the public API to handle site duplication.
 *
 * The decision to create a buffer interface (this file), as the API layer
 * for the duplication functions is simple: it allows us to swith the duplication
 * component used without breaking backwards-compatibility in the future.
 *
 * @since 2.0.0
 */
class Site_Duplicator {

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Duplicate an existing network site.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $from_site_id ID of the site you wish to copy.
	 * @param string $title Title of the new site.
	 * @param array  $args List of duplication parameters, check Site_Duplicator::process_duplication for reference.
	 * @return int|\WP_Error ID of the newly created site or error.
	 */
	public static function duplicate_site($from_site_id, $title, $args = []) {

		$args['from_site_id'] = $from_site_id;
		$args['title']        = $title;

		$duplicate_site = self::process_duplication($args);

		if (is_wp_error($duplicate_site)) {

			// translators: %s id the template site id and %s is the error message returned.
			$message = sprintf(__('Attempt to duplicate site %1$d failed: %2$s', 'ultimate-multisite'), $from_site_id, $duplicate_site->get_error_message());

			wu_log_add('site-duplication', $message, LogLevel::ERROR);

			return $duplicate_site;
		}

		// translators: %1$d is the ID of the site template used, and %2$d is the id of the new site.
		$message = sprintf(__('Attempt to duplicate site %1$d successful - New site id: %2$d', 'ultimate-multisite'), $from_site_id, $duplicate_site);

		wu_log_add('site-duplication', $message);

		return $duplicate_site;
	}

	/**
	 * Replace the contents of a site with the contents of another.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $from_site_id Site to get the data from.
	 * @param int   $to_site_id Site to override.
	 * @param array $args List of duplication parameters, check Site_Duplicator::process_duplication for reference.
	 * @return int|false ID of the created site.
	 */
	public static function override_site($from_site_id, $to_site_id, $args = []) {

		$to_site = wu_get_site($to_site_id);

		if (! $to_site) {
			wu_log_add('site-duplication', sprintf('Target site %d not found', $to_site_id), LogLevel::ERROR);
			return false;
		}

		// FIX (KP) — Reset blog/cache context BEFORE override.
		// MUCD_Data::copy_data() leaves $wpdb in template-blog context and
		// pollutes email_exists() / user lookup caches. On consecutive
		// override calls, this causes create_admin() to fail with
		// "Could not create admin user". Forcing context reset prevents this.
		while ( function_exists('ms_is_switched') && ms_is_switched() ) {
			restore_current_blog();
		}
		wp_cache_flush();

		// FIX (KP) — Pre-extend timeouts. Default 30s PHP-FPM timeout
		// truncates copy_files for sites >50MB of media. 300s/512M is safe.
		@set_time_limit(300);
		@ini_set('memory_limit', '512M');
		if ( ! defined('WP_IMPORTING') ) {
			define('WP_IMPORTING', true);
		}

		// FIX (KP) — Snapshot identity BEFORE process_duplication runs.
		// copy_data() will overwrite blogname / admin_email / wp_blogmeta
		// from the template. We need the originals to restore after.
		$identity_snapshot = [
			'blogname'         => get_blog_option($to_site_id, 'blogname'),
			'blogdescription'  => get_blog_option($to_site_id, 'blogdescription'),
			'home'             => get_blog_option($to_site_id, 'home'),
			'siteurl'          => get_blog_option($to_site_id, 'siteurl'),
			'admin_email'      => get_blog_option($to_site_id, 'admin_email'),
		];

		$to_site_membership_id = $to_site->get_membership_id();

		$to_site_membership = $to_site->get_membership();

		$to_site_customer = $to_site_membership ? $to_site_membership->get_customer() : false;

		// Determine email - use customer email if available, otherwise use site admin email
		$email = $to_site_customer ? $to_site_customer->get_email_address() : get_blog_option($to_site_id, 'admin_email');

		// FIX (KP) — Pre-clean user cache for the customer's user_id so
		// email_exists() returns the correct value during create_admin().
		if ( $to_site_customer && method_exists($to_site_customer, 'get_user_id') ) {
			clean_user_cache( $to_site_customer->get_user_id() );
		}

		$args = wp_parse_args(
			$args,
			[
				'email'        => $email,
				'title'        => $to_site->get_title(),
				'path'         => $to_site->get_path(),
				'from_site_id' => $from_site_id,
				'to_site_id'   => $to_site_id,
				'meta'         => $to_site->meta,
			]
		);

		$duplicate_site_id = self::process_duplication($args);

		if (is_wp_error($duplicate_site_id)) {

			// translators: %s id the template site id and %s is the error message returned.
			$message = sprintf(__('Attempt to override site %1$d with data from site %2$d failed: %3$s', 'ultimate-multisite'), $from_site_id, $to_site_id, $duplicate_site_id->get_error_message());

			wu_log_add('site-duplication', $message, LogLevel::ERROR);

			return false;
		}

		// FIX (KP) — Restore identity IMMEDIATELY after copy_data overwrote it.
		// blogname is the customer's brand — must NEVER be replaced with template's name.
		foreach ($identity_snapshot as $opt_key => $opt_val) {
			if ( ! empty($opt_val) ) {
				update_blog_option($to_site_id, $opt_key, $opt_val);
			}
		}

		// FIX (KP) — Force-copy Elementor Kit settings/data from source template.
		// MUCD copies postmeta rows but Elementor's serialize/cache layer
		// sometimes retains stale values, causing the customer site to render
		// with previous template's colors. Explicit overwrite + CSS regen
		// guarantees colors match the chosen template.
		self::force_copy_elementor_kit($from_site_id, $to_site_id);

		$new_to_site = wu_get_site($duplicate_site_id);

		$new_to_site->set_membership_id($to_site_membership_id);

		$new_to_site->set_customer_id($to_site->get_customer_id());

		$new_to_site->set_template_id($from_site_id);

		$new_to_site->set_type('customer_owned');

		$new_to_site->set_title($to_site->get_title());

		$saved = $new_to_site->save();

		if (is_wp_error($saved)) {
			// translators: %s id the template site id and %s is the error message returned.
			$message = sprintf(__('Attempt to override site %1$d with data from site %2$d failed: %3$s', 'ultimate-multisite'), $from_site_id, $to_site_id, $saved->get_error_message());

			wu_log_add('site-duplication', $message, LogLevel::ERROR);
			return false;
		}

		// FIX (KP) — Cleanup context AFTER override so next call starts clean.
		while ( function_exists('ms_is_switched') && ms_is_switched() ) {
			restore_current_blog();
		}
		wp_cache_flush();
		clean_blog_cache($to_site_id);
		if ( $to_site_customer && method_exists($to_site_customer, 'get_user_id') ) {
			clean_user_cache( $to_site_customer->get_user_id() );
		}

		// translators: %1$d is the ID of the site template used, and %2$d is the ID of the overriden site.
		$message = sprintf(__('Attempt to override site %1$d with data from site %2$d successful.', 'ultimate-multisite'), $from_site_id, $duplicate_site_id);

		wu_log_add('site-duplication', $message);

		return $saved;
	}

	/**
	 * FIX (KP) — Force-copy Elementor Kit settings + data from source template.
	 *
	 * MUCD_Data::copy_data() copies postmeta rows but the Elementor Kit's
	 * `_elementor_page_settings` and `_elementor_data` are sometimes stale
	 * because Elementor uses internal caches. Forcing an explicit copy +
	 * regen guarantees colors/typography match the chosen template.
	 *
	 * @since 2.x.x
	 *
	 * @param int $from_template_id Source template blog_id (e.g. plantilla1.example.com)
	 * @param int $to_blog_id       Target customer subsite blog_id
	 * @return bool true if Kit copied and regen'd, false if skipped
	 */
	public static function force_copy_elementor_kit($from_template_id, $to_blog_id) {
		$from_template_id = (int) $from_template_id;
		$to_blog_id = (int) $to_blog_id;

		if ( $from_template_id <= 0 || $to_blog_id <= 1 ) {
			return false;
		}
		if ( ! class_exists('\Elementor\Plugin') ) {
			return false;
		}

		// Read Kit settings from source template.
		switch_to_blog($from_template_id);
		$src_kit_id = (int) get_option('elementor_active_kit');
		$src_settings = $src_kit_id > 0 ? get_post_meta($src_kit_id, '_elementor_page_settings', true) : null;
		$src_data = $src_kit_id > 0 ? get_post_meta($src_kit_id, '_elementor_data', true) : null;
		$src_all_meta = $src_kit_id > 0 ? get_post_meta($src_kit_id) : [];
		restore_current_blog();

		if ( $src_kit_id <= 0 || empty($src_settings) ) {
			return false;
		}

		// Apply to target subsite.
		switch_to_blog($to_blog_id);
		$dst_kit_id = (int) get_option('elementor_active_kit');
		if ( $dst_kit_id <= 0 ) {
			update_option('elementor_active_kit', $src_kit_id);
			$dst_kit_id = $src_kit_id;
		}

		update_post_meta($dst_kit_id, '_elementor_page_settings', $src_settings);
		if ( ! empty($src_data) ) {
			update_post_meta($dst_kit_id, '_elementor_data', $src_data);
		}

		// Copy ALL _elementor_* and _wp_* meta from src Kit.
		if ( ! empty($src_all_meta) ) {
			foreach ($src_all_meta as $meta_key => $values) {
				if ( strpos($meta_key, '_elementor') !== 0 && strpos($meta_key, '_wp_') !== 0 ) {
					continue;
				}
				if ( in_array($meta_key, ['_elementor_page_settings', '_elementor_data'], true) ) {
					continue;
				}
				delete_post_meta($dst_kit_id, $meta_key);
				foreach ($values as $v) {
					$unserialized = maybe_unserialize($v);
					add_post_meta($dst_kit_id, $meta_key, $unserialized);
				}
			}
		}

		// Regenerate Kit CSS so frontend reflects new colors immediately.
		if ( class_exists('\Elementor\Core\Files\CSS\Post') ) {
			try {
				(new \Elementor\Core\Files\CSS\Post($dst_kit_id))->update();
			} catch (\Throwable $e) {
				// Continue — caller should not fail because of CSS regen issues.
			}
		}

		restore_current_blog();
		return true;
	}

	/**
	 * Processes a site duplication.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args List of parameters of the duplication.
	 *                    - email Email of the admin user to be created.
	 *                    - title Title of the (new or not) site.
	 *                    - path  Path of the new site.
	 *                    - from_site_id ID of the template site being used.
	 *                    - to_site_id   ID of the target site. Can be false to create new site.
	 *                    - keep_users   If we should keep users or not. Defaults to true.
	 *                    - copy_files   If we should copy the uploaded files or not. Defaults to true.
	 *                    - public       If the (new or not) site should be public. Defaults to true.
	 *                    - domain       The domain of the new site.
	 *                    - network_id   The network ID to allow for multi-network support.
	 * @return int|\WP_Error The Site ID.
	 */
	protected static function process_duplication($args) {

		global $current_site, $wpdb;

		$args = wp_parse_args(
			$args,
			[
				'email'        => '',    // Required arguments.
				'title'        => '',    // Required arguments.
				'path'         => '/',   // Required arguments.
				'from_site_id' => false, // Required arguments.
				'to_site_id'   => false,
				'keep_users'   => true,
				'public'       => true,
				'domain'       => $current_site->domain,
				'copy_files'   => '' !== wu_get_setting('copy_media', true) ? (bool) wu_get_setting('copy_media', true) : true,
				'network_id'   => get_current_network_id(),
				'meta'         => [],
				'user_id'      => 0,
			]
		);

		// Checks
		$args = (object) $args;

		$site_domain = $args->domain . $args->path;

		$wpdb->hide_errors();

		if ( ! $args->from_site_id) {
			return new \WP_Error('from_site_id_required', __('You need to provide a valid site to duplicate.', 'ultimate-multisite'));
		}

		$user_id = ! empty($args->user_id) ? $args->user_id : self::create_admin($args->email, $site_domain);

		if (is_wp_error($user_id)) {
			return $user_id;
		}

		if ( ! $args->to_site_id) {
			$meta = array_merge($args->meta, ['public' => $args->public]);

			$args->to_site_id = wpmu_create_blog($args->domain, $args->path, $args->title, $user_id, $meta, $args->network_id);

			$wpdb->show_errors();
		}

		if (is_wp_error($args->to_site_id)) {
			return $args->to_site_id;
		}

		if ( ! is_numeric($args->to_site_id)) {
			return new \WP_Error('site_creation_failed', __('An attempt to create a new site failed.', 'ultimate-multisite'));
		}

		if ( ! is_super_admin($user_id) && ! get_user_option('primary_blog', $user_id)) {
			update_user_option($user_id, 'primary_blog', $args->to_site_id, true);
		}

		\MUCD_Duplicate::bypass_server_limit();

		if ($args->copy_files) {
			\MUCD_Files::copy_files($args->from_site_id, $args->to_site_id);
		}

		/**
		 * Supress email change notification on site duplication processes.
		 */
		add_filter('send_site_admin_email_change_email', '__return_false');

		\MUCD_Data::copy_data($args->from_site_id, $args->to_site_id);

		/*
		 * Resolve the real template source from wu_template_id site meta.
		 *
		 * MUCD's hooks pass a from_site_id that may differ from the template
		 * the customer actually selected at checkout. WP Ultimo stores the
		 * customer's real choice in the wu_template_id site meta key.
		 * Prefer that over the explicit param when available.
		 *
		 * Intentionally kept in a separate variable: copy_data() and
		 * copy_files() have already run with $args->from_site_id. Mutating
		 * that property would cause copy_users() and downstream callers to
		 * reference a different source than the one whose data was copied,
		 * creating an inconsistent clone. Use $template_site_id only for the
		 * post-copy backfill, integrity check, and action payload.
		 *
		 * @since 2.3.1
		 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/820
		 */
		$template_site_id = (int) $args->from_site_id;
		$meta_template    = (int) get_site_meta($args->to_site_id, 'wu_template_id', true);
		if (0 < $meta_template && $meta_template !== (int) $args->from_site_id) {
			$template_site_id = $meta_template;
		}

		/*
		 * Backfill postmeta that MUCD_Data::copy_data() misses.
		 *
		 * MUCD copies table data with INSERT ... SELECT (full-table copy), but
		 * certain post types end up with missing postmeta rows — particularly
		 * nav_menu_item, attachment, and elementor_library posts. The Elementor
		 * Kit post (usually ID 3) also gets stub postmeta that must be
		 * overwritten with the real template values.
		 *
		 * @since 2.3.1
		 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/820
		 */
		self::backfill_postmeta($template_site_id, $args->to_site_id);

		/*
		 * Rewrite source URLs to target URLs in backfilled postmeta rows.
		 *
		 * backfill_postmeta() inserts rows after MUCD_Data::copy_data() has
		 * already run its source→target URL replacement pass, so those rows
		 * contain raw template URLs. Apply the same replacement here.
		 *
		 * @since 2.3.2
		 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/834
		 */
		self::rewrite_backfilled_postmeta_urls($template_site_id, $args->to_site_id);

		/*
		 * Verify Kit integrity after backfill.
		 *
		 * Compares the byte length of _elementor_page_settings between the
		 * template and the clone. If the clone has less than 80% of the
		 * template's byte count, the Kit fix is re-applied as a safety net.
		 *
		 * @since 2.3.1
		 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/820
		 */
		self::verify_kit_integrity($template_site_id, $args->to_site_id);

		if ($args->keep_users) {
			\MUCD_Duplicate::copy_users($args->from_site_id, $args->to_site_id);
		}

		wp_cache_flush();

		// Ensure the requested title is applied after duplication, since the
		// table copy may overwrite the blogname option set during site creation.
		// When no title was provided (e.g. WooCommerce checkout flow), fall back
		// to the subdomain portion of the site's domain so the duplicated site
		// doesn't keep the template's blogname.
		$new_title = ! empty($args->title)
			? $args->title
			: ucfirst(preg_replace('/\..*$/', '', $args->domain));

		update_blog_option($args->to_site_id, 'blogname', $new_title);

		/**
		 * Allow developers to hook after a site duplication happens.
		 *
		 * @since 1.9.4
		 * @return void
		 */
		do_action(
			'wu_duplicate_site',
			[
				'from_site_id' => $template_site_id,
				'site_id'      => $args->to_site_id,
			]
		);

		return $args->to_site_id;
	}

	/**
	 * Creates an admin user if no user exists with this email.
	 *
	 * @since 2.0.0
	 * @param  string $email The email.
	 * @param  string $domain The domain.
	 * @return int|\WP_Error Id of the user created.
	 */
	public static function create_admin($email, $domain) {

		// Create New site Admin if not exists
		$password = 'N/A';

		$user_id = email_exists($email);

		if ( ! $user_id) { // Create a new user with a random password

			$password = wp_generate_password(12, false);

			$user_id = wpmu_create_user($domain, $password, $email);

			if (false === $user_id) {
				return new \WP_Error('user_creation_error', __('We were not able to create a new admin user for the site being duplicated.', 'ultimate-multisite'));
			} else {
				wp_new_user_notification($user_id);
			}
		}

		return $user_id;
	}

	/**
	 * Backfill postmeta rows that MUCD_Data::copy_data() misses.
	 *
	 * MUCD copies table data with INSERT ... SELECT, but certain post types
	 * end up with missing or stub postmeta rows. This method fills the gaps
	 * for nav_menu_item, attachment, and Elementor post types, and force-
	 * overwrites the Elementor Kit settings which MUCD inserts as stubs.
	 *
	 * @since 2.3.1
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/820
	 *
	 * @param int $from_site_id Source (template) blog ID.
	 * @param int $to_site_id   Target (cloned) blog ID.
	 */
	protected static function backfill_postmeta($from_site_id, $to_site_id) {

		$from_site_id = (int) $from_site_id;
		$to_site_id   = (int) $to_site_id;

		if ( ! $from_site_id || ! $to_site_id || $from_site_id === $to_site_id) {
			return;
		}

		self::backfill_nav_menu_postmeta($from_site_id, $to_site_id);
		self::backfill_attachment_postmeta($from_site_id, $to_site_id);
		self::backfill_elementor_postmeta($from_site_id, $to_site_id);
		self::backfill_all_postmeta($from_site_id, $to_site_id);
		self::backfill_kit_settings($from_site_id, $to_site_id);
	}

	/**
	 * Rewrite source-site URLs to target-site URLs across all cloned tables.
	 *
	 * backfill_postmeta() inserts rows after MUCD_Data::copy_data() has already
	 * run its source→target URL replacement pass (db_update_data()), so those
	 * rows contain raw template-site URLs. This method applies the same URL
	 * substitution to the target's postmeta, posts, options, termmeta, and
	 * commentmeta tables, correcting any template references left by the
	 * backfill or missed by MUCD's pass.
	 *
	 * Safe to run after MUCD has already rewritten the copied rows: those rows
	 * no longer contain the source URL, so REPLACE() is a no-op for them.
	 *
	 * @since 2.3.2
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/834
	 *
	 * @param int $from_site_id Source (template) blog ID.
	 * @param int $to_site_id   Target (cloned) blog ID.
	 */
	protected static function rewrite_backfilled_postmeta_urls($from_site_id, $to_site_id) {

		global $wpdb;

		$from_site_id = (int) $from_site_id;
		$to_site_id   = (int) $to_site_id;

		if ( ! $from_site_id || ! $to_site_id || $from_site_id === $to_site_id) {
			return;
		}

		$from_blog_url = get_blog_option($from_site_id, 'siteurl');
		$to_blog_url   = get_blog_option($to_site_id, 'siteurl');

		$from_clean = wu_replace_scheme((string) $from_blog_url);
		$to_clean   = wu_replace_scheme((string) $to_blog_url);

		if ($from_clean === $to_clean) {
			return;
		}

		$to_prefix = $wpdb->get_blog_prefix($to_site_id);

		/*
		 * Mirror MUCD's two-pass approach: plain URL replacement and a
		 * JSON-escaped variant (forward slashes encoded as \/).
		 */
		$replacements = [
			$from_clean                          => $to_clean,
			str_replace('/', '\\/', $from_clean) => str_replace('/', '\\/', $to_clean),
		];

		/*
		 * Tables and columns to rewrite. postmeta is the primary target
		 * (backfilled rows), but posts.post_content, options.option_value,
		 * termmeta.meta_value, and commentmeta.meta_value may also contain
		 * stale template URLs — either from the backfill or from MUCD's
		 * pass missing a JSON-encoded variant.
		 */
		$tables = [
			"{$to_prefix}postmeta"    => 'meta_value',
			"{$to_prefix}posts"       => 'post_content',
			"{$to_prefix}options"     => 'option_value',
			"{$to_prefix}termmeta"    => 'meta_value',
			"{$to_prefix}commentmeta" => 'meta_value',
		];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ($tables as $table => $column) {

			// Skip tables that don't exist (e.g. termmeta on older WP versions).
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1',
					$table
				)
			);

			if ( ! $exists) {
				continue;
			}

			foreach ($replacements as $from => $to) {

				if ($from === $to) {
					continue;
				}

				$wpdb->query(
					$wpdb->prepare(
						"UPDATE `{$table}` SET `{$column}` = REPLACE(`{$column}`, %s, %s) WHERE `{$column}` LIKE %s",
						$from,
						$to,
						'%' . $wpdb->esc_like($from) . '%'
					)
				);
			}
		}
		// phpcs:enable
	}

	/**
	 * Catch-all: backfill ANY missing postmeta for ANY post type.
	 *
	 * Final safety net after the targeted backfill methods (nav_menu,
	 * attachment, Elementor). Copies every postmeta row from the source
	 * that exists for a post present in the target but is missing from
	 * the target's postmeta table.
	 *
	 * Covers: _thumbnail_id, _wp_page_template, page-builder meta,
	 * LMS course meta, WooCommerce product meta, and any custom fields
	 * not handled by the specific backfill methods above.
	 *
	 * NOT EXISTS guard makes it idempotent — safe to re-run, never duplicates.
	 *
	 * @since 2.3.3
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/820
	 *
	 * @param int $from_site_id Source blog ID.
	 * @param int $to_site_id   Target blog ID.
	 */
	protected static function backfill_all_postmeta($from_site_id, $to_site_id) {

		global $wpdb;

		$from_prefix = $wpdb->get_blog_prefix((int) $from_site_id);
		$to_prefix   = $wpdb->get_blog_prefix((int) $to_site_id);

		if ($from_prefix === $to_prefix) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"INSERT INTO {$to_prefix}postmeta (post_id, meta_key, meta_value)
			SELECT src.post_id, src.meta_key, src.meta_value
			FROM {$from_prefix}postmeta src
			INNER JOIN {$to_prefix}posts tgt
					ON tgt.ID = src.post_id
			WHERE NOT EXISTS (
				SELECT 1 FROM {$to_prefix}postmeta tpm
				WHERE tpm.post_id = src.post_id
				  AND tpm.meta_key = src.meta_key
			)"
		);
		// phpcs:enable
	}

	/**
	 * Backfill nav_menu_item postmeta from template to cloned site.
	 *
	 * MUCD copies nav_menu_item posts (preserving IDs) but not their postmeta
	 * rows. Without these rows, menus render as empty list items with no
	 * titles, URLs, or parent relationships.
	 *
	 * @since 2.3.1
	 *
	 * @param int $from_site_id Source blog ID.
	 * @param int $to_site_id   Target blog ID.
	 */
	protected static function backfill_nav_menu_postmeta($from_site_id, $to_site_id) {

		global $wpdb;

		$from_prefix = $wpdb->get_blog_prefix($from_site_id);
		$to_prefix   = $wpdb->get_blog_prefix($to_site_id);

		if ($from_prefix === $to_prefix) {
			return;
		}

		$meta_keys = [
			'_menu_item_type',
			'_menu_item_menu_item_parent',
			'_menu_item_object_id',
			'_menu_item_object',
			'_menu_item_target',
			'_menu_item_classes',
			'_menu_item_xfn',
			'_menu_item_url',
		];

		$placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$to_prefix}postmeta (post_id, meta_key, meta_value)
				SELECT src.post_id, src.meta_key, src.meta_value
				FROM {$from_prefix}postmeta src
				INNER JOIN {$to_prefix}posts tgt
						ON tgt.ID = src.post_id
						AND tgt.post_type = 'nav_menu_item'
				WHERE src.meta_key IN ({$placeholders})
				  AND NOT EXISTS (
					  SELECT 1 FROM {$to_prefix}postmeta tpm
					  WHERE tpm.post_id = src.post_id
						AND tpm.meta_key = src.meta_key
				  )",
				...$meta_keys
			)
		);
		// phpcs:enable
	}

	/**
	 * Backfill attachment postmeta from template to cloned site.
	 *
	 * MUCD copies attachment posts but not their postmeta. Without
	 * _wp_attached_file, wp_get_attachment_image_url() returns false and
	 * images disappear even though the physical files exist on disk.
	 *
	 * @since 2.3.1
	 *
	 * @param int $from_site_id Source blog ID.
	 * @param int $to_site_id   Target blog ID.
	 */
	protected static function backfill_attachment_postmeta($from_site_id, $to_site_id) {

		global $wpdb;

		$from_prefix = $wpdb->get_blog_prefix($from_site_id);
		$to_prefix   = $wpdb->get_blog_prefix($to_site_id);

		if ($from_prefix === $to_prefix) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"INSERT INTO {$to_prefix}postmeta (post_id, meta_key, meta_value)
			SELECT src.post_id, src.meta_key, src.meta_value
			FROM {$from_prefix}postmeta src
			INNER JOIN {$to_prefix}posts tgt
					ON tgt.ID = src.post_id
					AND tgt.post_type = 'attachment'
			WHERE NOT EXISTS (
				SELECT 1 FROM {$to_prefix}postmeta tpm
				WHERE tpm.post_id = src.post_id
				  AND tpm.meta_key = src.meta_key
			)"
		);
		// phpcs:enable
	}

	/**
	 * Backfill Elementor postmeta for all post types.
	 *
	 * Catch-all for any _elementor_* meta that MUCD missed. Covers
	 * elementor_library (headers, footers, popups), e-landing-page,
	 * elementor_snippet, and any custom post type with Elementor data.
	 *
	 * @since 2.3.1
	 *
	 * @param int $from_site_id Source blog ID.
	 * @param int $to_site_id   Target blog ID.
	 */
	protected static function backfill_elementor_postmeta($from_site_id, $to_site_id) {

		global $wpdb;

		$from_prefix = $wpdb->get_blog_prefix($from_site_id);
		$to_prefix   = $wpdb->get_blog_prefix($to_site_id);

		if ($from_prefix === $to_prefix) {
			return;
		}

		$like_pattern = $wpdb->esc_like('_elementor') . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$to_prefix}postmeta (post_id, meta_key, meta_value)
				SELECT src.post_id, src.meta_key, src.meta_value
				FROM {$from_prefix}postmeta src
				INNER JOIN {$to_prefix}posts tgt
						ON tgt.ID = src.post_id
				WHERE src.meta_key LIKE %s
				  AND NOT EXISTS (
					  SELECT 1 FROM {$to_prefix}postmeta tpm
					  WHERE tpm.post_id = src.post_id
						AND tpm.meta_key = src.meta_key
				  )",
				$like_pattern
			)
		);
		// phpcs:enable
	}

	/**
	 * Force-overwrite the Elementor Kit settings on the cloned site.
	 *
	 * The Kit post (holding colors, typography, logo) gets created with stub
	 * Elementor defaults BEFORE MUCD runs its INSERT ... SELECT. Because MUCD
	 * uses INSERT NOT EXISTS, the stub row is never overwritten, leaving the
	 * clone with default Elementor colors instead of the template palette.
	 *
	 * This method reads the real settings from the template and uses
	 * update_post_meta() to guarantee the overwrite.
	 *
	 * @since 2.3.1
	 *
	 * @param int $from_site_id Source blog ID.
	 * @param int $to_site_id   Target blog ID.
	 */
	protected static function backfill_kit_settings($from_site_id, $to_site_id) {

		// Read kit settings from the template site.
		switch_to_blog($from_site_id);

		$kit_id_from  = (int) get_option('elementor_active_kit', 0);
		$kit_settings = $kit_id_from ? get_post_meta($kit_id_from, '_elementor_page_settings', true) : '';
		$kit_data     = $kit_id_from ? get_post_meta($kit_id_from, '_elementor_data', true) : '';

		restore_current_blog();

		if (empty($kit_settings)) {
			return;
		}

		// Force-overwrite kit settings on the target site.
		// Uses update_post_meta() instead of INSERT NOT EXISTS because
		// the target kit may already have stub metadata from Elementor's
		// activation routine. INSERT NOT EXISTS would silently skip the
		// row, leaving the clone with default Elementor colors.
		switch_to_blog($to_site_id);

		$kit_id_to = (int) get_option('elementor_active_kit', 0);

		if ( ! $kit_id_to && $kit_id_from) {
			$kit_id_to = $kit_id_from;
			update_option('elementor_active_kit', $kit_id_to);
		}

		if ($kit_id_to) {
			update_post_meta($kit_id_to, '_elementor_page_settings', $kit_settings);

			if ( ! empty($kit_data) && '[]' !== $kit_data) {
				update_post_meta($kit_id_to, '_elementor_data', $kit_data);
			}

			// Clear compiled CSS so Elementor_Compat::regenerate_css() will
			// rebuild with the correct Kit settings on wu_duplicate_site.
			delete_post_meta($kit_id_to, '_elementor_css');
		}

		restore_current_blog();
	}

	/**
	 * Verify Kit integrity after clone and re-apply if mismatched.
	 *
	 * Compares the byte length of _elementor_page_settings between the
	 * template and the clone. If the clone has less than 80% of the
	 * template's byte count, the Kit fix is re-applied as a safety net.
	 *
	 * This catches edge cases where update_post_meta() succeeded but the
	 * stored value was truncated by a concurrent write, or where Elementor's
	 * activation routine overwrote the Kit settings after backfill.
	 *
	 * @since 2.3.1
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/820
	 *
	 * @param int $from_site_id Source blog ID.
	 * @param int $to_site_id   Target blog ID.
	 */
	protected static function verify_kit_integrity($from_site_id, $to_site_id) {

		$from_site_id = (int) $from_site_id;
		$to_site_id   = (int) $to_site_id;

		if ( ! $from_site_id || ! $to_site_id || $from_site_id === $to_site_id) {
			return;
		}

		switch_to_blog($from_site_id);
		$kit_id_from = (int) get_option('elementor_active_kit', 0);
		$from_size   = $kit_id_from ? strlen(maybe_serialize(get_post_meta($kit_id_from, '_elementor_page_settings', true))) : 0;
		restore_current_blog();

		switch_to_blog($to_site_id);
		$kit_id_to = (int) get_option('elementor_active_kit', 0);
		$to_size   = $kit_id_to ? strlen(maybe_serialize(get_post_meta($kit_id_to, '_elementor_page_settings', true))) : 0;
		restore_current_blog();

		if ( ! $from_size || ! $to_size) {
			return;
		}

		// If the clone has less than 80% of the template's byte count,
		// the Kit settings are likely incomplete — re-apply the fix.
		if ($to_size < ($from_size * 0.8)) {
			self::backfill_kit_settings($from_site_id, $to_site_id);
		}
	}
}
