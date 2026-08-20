<?php
/**
 * Check if all the pre-requisites to run Ultimate Multisite are in place.
 *
 * @package WP_Ultimo
 * @subpackage Requirements
 * @since 2.0.0
 */

namespace WP_Ultimo;

use WP_Ultimo\Loaders\Table_Loader;
use WP_Ultimo\Installers\Core_Installer;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Check if all the pre-requisites to run Ultimate Multisite are in place.
 *
 * @since 2.0.0
 */
class Requirements {

	/**
	 * Caches the result of the requirement check.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	public static $met;

	/**
	 * Minimum PHP version required to run Ultimate Multisite.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public static $php_version = '8.2';

	/**
	 * Recommended PHP Version
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public static $php_recommended_version = '8.2.27';

	/**
	 * Minimum WordPress version required to run Ultimate Multisite.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public static $wp_version = '5.3';

	/**
	 * Recommended WP Version.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public static $wp_recommended_version = '6.7.2';

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Check if the minimum pre-requisites to run Ultimate Multisite are present.
	 *
	 * - Check if the PHP version requirements are met;
	 * - Check if the WordPress version requirements are met;
	 * - Check if the install is a Multisite install;
	 * - Check if Ultimate Multisite is network active.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public static function met() {

		if (null === self::$met) {
			self::$met = (
				self::check_php_version()
				&& self::check_wp_version()
				&& self::is_multisite()
				&& self::is_network_active()
			);
		}

		return self::$met;
	}

	/**
	 * Checks if we have run through the setup already.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public static function run_setup() {

		global $wpdb;

		if (self::is_unit_test()) {
			// phpcs:disable

			$tables = wu_array_flatten($wpdb->get_results(sprintf('SHOW TABLES FROM %s;', DB_NAME), ARRAY_N));

			// phpcs:enable

			if (! in_array("{$wpdb->prefix}wu_domain_mappings", $tables, true)) {
				Core_Installer::get_instance()->_install_database_tables();
			}

			return true;
		}

		return get_network_option(null, \WP_Ultimo::NETWORK_OPTION_SETUP_FINISHED, false);
	}

	/**
	 * Checks for a test environment.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public static function is_unit_test(): bool {
		return defined('WP_TESTS_MULTISITE') && WP_TESTS_MULTISITE;
	}

	/**
	 * Check if the PHP version requirements are met
	 *
	 * @since 2.0.0
	 */
	public static function check_php_version(): bool {

		if (version_compare(phpversion(), self::$php_version, '<')) {
			add_action('network_admin_notices', [self::class, 'notice_unsupported_php_version']);

			return false;
		}

		return true;
	}

	/**
	 * Check if the WordPress version requirements are met
	 *
	 * @since 2.0.0
	 */
	public static function check_wp_version(): bool {

		global $wp_version;

		if (version_compare($wp_version, self::$wp_version, '<')) {
			add_action('network_admin_notices', [self::class, 'notice_unsupported_wp_version']);

			return false;
		}

		return true;
	}

	/**
	 * Check Cron Status
	 *
	 * Gets the current cron status by performing a test spawn.
	 * Cached for one hour when all is well.
	 *
	 * Heavily inspired on Astra's test_cron check:
	 *
	 * @see astra/inc/theme-update/class-astra-theme-background-updater.php
	 *
	 * @since 2.0.0
	 * @return false if there is a problem spawning a call to WP-Cron system.
	 */
	public static function check_wp_cron(): bool {

		/**
		 * Filters the detected WP-Cron status.
		 *
		 * Return a boolean to bypass the native checks, or null to run them.
		 *
		 * @since 2.5.1
		 *
		 * @param bool|null $status_override Cron status override. Default null.
		 */
		$status_override = apply_filters('wu_wp_cron_status_override', null);

		if (is_bool($status_override)) {
			return $status_override;
		}

		global $wp_version;

		if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
			return false;
		}

		if (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) {
			return false;
		}

		$cached_status = get_site_transient('wp-ultimo-cron-test-ok');

		if ($cached_status) {
			return true;
		}

		$sslverify     = version_compare($wp_version, 4.0, '<');
		$doing_wp_cron = sprintf('%.22F', microtime(true));

		$cron_request = apply_filters(
			'cron_request', // phpcs:ignore
			[
				'url'  => site_url('wp-cron.php?doing_wp_cron=' . $doing_wp_cron),
				'args' => [
					'timeout'   => 3,
					'blocking'  => true,
					'sslverify' => apply_filters('https_local_ssl_verify', $sslverify), // phpcs:ignore
				],
			]
		);

		$result = wp_remote_post($cron_request['url'], $cron_request['args']);

		if (wp_remote_retrieve_response_code($result) >= 300) {
			return false;
		}

		set_transient('wp-ultimo-cron-test-ok', 1, HOUR_IN_SECONDS);

		return true;
	}

	/**
	 * Check if the install is a Multisite install
	 *
	 * @since 2.0.0
	 */
	public static function is_multisite(): bool {

		if (! is_multisite()) {
			add_action('admin_notices', [self::class, 'notice_not_multisite']);

			return false;
		}

		return true;
	}

	/**
	 * Check if Ultimate Multisite is network active.
	 *
	 * @since 2.0.0
	 */
	public static function is_network_active(): bool {

		/**
		 * Allow for developers to short-circuit this check.
		 *
		 * This is useful when using composer-based and other custom setups,
		 * such as Bedrock, for example, where using plugins as mu-plugins
		 * are the norm.
		 *
		 * @since 2.0.0
		 * @return bool
		 */
		$skip_network_activation_check = apply_filters('wp_ultimo_skip_network_active_check', wu_is_must_use());

		if ($skip_network_activation_check) {
			return true;
		}

		if (! function_exists('is_plugin_active_for_network')) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		if (! is_plugin_active_for_network(WP_ULTIMO_PLUGIN_BASENAME) && ! self::is_unit_test()) {
			add_action('admin_notices', [self::class, 'notice_not_network_active']);

			return false;
		}

		return true;
	}

	/**
	 * Adds a network admin notice about the PHP requirements not being met
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function notice_unsupported_php_version(): void {

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				// translators: the %1$s placeholder is the required PHP version, while the %2$s is the current PHP version, and %3$s is the recommended PHP version.
				esc_html__(
					'Ultimate Multisite requires at least PHP version %1$s to run. Your current PHP version is %2$s. Please, contact your hosting company support to upgrade your PHP version. If you want maximum performance consider upgrading your PHP to version %3$s or later.',
					'ultimate-multisite'
				),
				esc_html(self::$php_version),
				'<strong>' . esc_html(phpversion()) . '</strong>',
				esc_html(self::$php_recommended_version)
			)
		);
	}

	/**
	 * Adds a network admin notice about the WordPress requirements not being met
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function notice_unsupported_wp_version(): void {

		global $wp_version;

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				// translators: the %1$s placeholder is the required WP version, while the %2$s is the current WP version.
				esc_html__(
					'Ultimate Multisite requires at least WordPress version %1$s to run. Your current WordPress version is %2$s.',
					'ultimate-multisite'
				),
				esc_html(self::$wp_version),
				'<strong>' . esc_html($wp_version) . '</strong>'
			)
		);
	}

	/**
	 * Adds a network admin notice about the install not being a multisite install
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function notice_not_multisite(): void {

		printf('<div class="notice notice-error"><p>%s <a href="%s">%s &rarr;</a></p></div>', esc_html__('Ultimate Multisite requires a multisite install to run properly.', 'ultimate-multisite'), esc_url(admin_url('admin.php?page=wp-ultimo-multisite-setup')), esc_html__('Run the Multisite Setup Wizard', 'ultimate-multisite'));
	}

	/**
	 * Adds a network admin notice about the Ultimate Multisite not being network-active
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function notice_not_network_active(): void {

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				// translators: %s is a placeholder for the Network Admin plugins page URL with link text.
				esc_html__('Ultimate Multisite needs to be network active to run properly. You can "Network Activate" it %s', 'ultimate-multisite'),
				'<a href="' . esc_attr(network_admin_url('plugins.php')) . '">' . esc_html__('here', 'ultimate-multisite') . '</a>'
			)
		);
	}
}
