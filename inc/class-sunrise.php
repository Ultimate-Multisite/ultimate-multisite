<?php
/**
 * Ultimate Multisite activation and deactivation hooks
 *
 * @package WP_Ultimo
 * @subpackage Sunrise
 * @since 2.0.0
 */

namespace WP_Ultimo;

use Psr\Log\LogLevel;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite activation and deactivation hooks
 *
 * @since 2.0.0
 */
class Sunrise {

	/**
	 * Keeps the current sunrise.php version.
	 *
	 * @var string
	 */

	public static $version = '2.0.0.10';

	/**
	 * Keeps the sunrise meta cached after the first read.
	 *
	 * @var null|array
	 */
	public static $sunrise_meta;

	/**
	 * Initializes sunrise and loads additional elements if needed.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public static function init(): void {

		require_once __DIR__ . '/functions/sunrise.php';

		/**
		 * Load the core apis we need from the start.
		 */
		require_once __DIR__ . '/functions/helper.php';

		require_once __DIR__ . '/functions/fs.php';

		require_once __DIR__ . '/functions/debug.php';

		require_once __DIR__ . '/functions/debug.php';

		/**
		 * Domain mapping needs to be loaded
		 * before anything else.
		 */
		self::load_domain_mapping();

		/**
		 * Load addon sunrise files.
		 *
		 * Some addons need to run code at sunrise time — before
		 * ms-settings.php resolves the network and blog. Instead of
		 * requiring each addon to modify the root sunrise.php (which
		 * Ultimate Multisite may overwrite on update), addons can ship
		 * a `sunrise.php` in their plugin root directory. This loader
		 * scans for and includes them after domain mapping has run.
		 *
		 * Addons are loaded in alphabetical order. Each addon's
		 * sunrise.php has access to $current_site, $current_blog,
		 * $wpdb, and all Ultimate Multisite sunrise functions.
		 *
		 * @since 2.3.0
		 */
		self::load_addon_sunrise_files();

		/**
		 * Enqueue the main hooks that deal with Sunrise
		 * loading and maintenance.
		 */
		add_action('ms_loaded', [self::class, 'load']);

		add_action('ms_loaded', [self::class, 'loaded'], 999);

		add_action('init', [self::class, 'maybe_tap_on_init']);

		add_filter('wu_system_info_data', [self::class, 'system_info']);
	}

	/**
	 * Checks if all the requirements for sunrise loading are in place.
	 *
	 * In order to be completely loaded, we need two
	 * criteria to be fulfilled:
	 *
	 * 1. The setup wizard must have been finalized;
	 * 2. Ultimo is active - which is determined by the sunrise meta file.
	 *
	 * @since 2.0.11
	 * @return boolean
	 */
	public static function should_startup() {

		$setup_finished = get_network_option(null, 'wu_setup_finished', false);

		$should_load_sunrise = wu_should_load_sunrise();

		return $setup_finished && $should_load_sunrise;
	}

	/**
	 * Load dependencies, if we need them somewhere.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public static function load_dependencies(): void {

		// We can't use JetPack autoloader because WordPress is not fully loaded yet.
		require_once __DIR__ . '/deprecated/early-deprecated.php';
		require_once __DIR__ . '/deprecated/mercator.php';
		require_once __DIR__ . '/functions/site.php';
		require_once __DIR__ . '/functions/debug.php';
		require_once __DIR__ . '/functions/url.php';
		require_once __DIR__ . '/functions/number-helpers.php';
		require_once __DIR__ . '/functions/array-helpers.php';
		require_once __DIR__ . '/traits/trait-singleton.php';
		require_once __DIR__ . '/interfaces/interface-singleton.php';
		require_once __DIR__ . '/objects/class-limitations.php';
		require_once __DIR__ . '/models/interfaces/interface-limitable.php';
		require_once __DIR__ . '/models/interfaces/interface-notable.php';
		require_once __DIR__ . '/models/interfaces/interface-billable.php';
		require_once __DIR__ . '/models/traits/trait-limitable.php';
		require_once __DIR__ . '/models/traits/trait-notable.php';
		require_once __DIR__ . '/models/traits/trait-billable.php';
		require_once __DIR__ . '/traits/trait-wp-ultimo-subscription-deprecated.php';
		require_once __DIR__ . '/traits/trait-wp-ultimo-site-deprecated.php';
		require_once __DIR__ . '/database/engine/class-enum.php';
		require_once __DIR__ . '/database/sites/class-site-type.php';
		require_once __DIR__ . '/../vendor/berlindb/core/src/Database/Base.php';
		require_once __DIR__ . '/../vendor/berlindb/core/src/Database/Query.php';
		require_once __DIR__ . '/../vendor/berlindb/core/src/Database/Row.php';
		require_once __DIR__ . '/../vendor/berlindb/core/src/Database/Schema.php';
		require_once __DIR__ . '/../vendor/berlindb/core/src/Database/Table.php';
		require_once __DIR__ . '/../vendor/berlindb/core/src/Database/Column.php';
		require_once __DIR__ . '/database/engine/traits/trait-network-prefix.php';
		require_once __DIR__ . '/database/engine/class-query.php';
		require_once __DIR__ . '/database/sites/class-site-query.php';
		require_once __DIR__ . '/models/class-base-model.php';
		require_once __DIR__ . '/models/class-domain.php';
		require_once __DIR__ . '/models/class-site.php';
		require_once __DIR__ . '/domain-mapping/class-primary-domain.php';
		require_once __DIR__ . '/class-domain-mapping.php';
		require_once __DIR__ . '/traits/trait-wp-ultimo-settings-deprecated.php';
		require_once __DIR__ . '/class-settings.php';
		require_once __DIR__ . '/limits/class-plugin-limits.php';
		require_once __DIR__ . '/limits/class-theme-limits.php';
		require_once __DIR__ . '/models/class-membership.php';
		require_once __DIR__ . '/database/engine/class-schema.php';
		require_once __DIR__ . '/database/sites/class-sites-schema.php';
		require_once __DIR__ . '/database/sites/class-site-query.php';
		require_once __DIR__ . '/limitations/class-limit.php';
		require_once __DIR__ . '/limitations/class-limit-subtype.php';
		require_once __DIR__ . '/limitations/class-limit-post-types.php';
		require_once __DIR__ . '/limitations/class-limit-plugins.php';
		require_once __DIR__ . '/limitations/class-limit-sites.php';
		require_once __DIR__ . '/limitations/class-limit-themes.php';
		require_once __DIR__ . '/limitations/class-limit-visits.php';
		require_once __DIR__ . '/limitations/class-limit-disk-space.php';
		require_once __DIR__ . '/limitations/class-limit-users.php';
		require_once __DIR__ . '/limitations/class-limit-site-templates.php';
		require_once __DIR__ . '/limitations/class-limit-domain-mapping.php';
		require_once __DIR__ . '/limitations/class-limit-customer-user-role.php';
		require_once __DIR__ . '/limitations/class-limit-hide-footer-credits.php';
		require_once __DIR__ . '/database/domains/class-domain-stage.php';
	}

	/**
	 * Loads domain mapping before anything else.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public static function load_domain_mapping(): void {

		$should_startup = self::should_startup();

		if ($should_startup) {
			self::load_dependencies();

			/*
			 * Primary Domain capabilities
			 */
			\WP_Ultimo\Domain_Mapping\Primary_Domain::get_instance();

			\WP_Ultimo\Domain_Mapping::get_instance();
		}
	}

	/**
	 * Scan for and load addon sunrise files.
	 *
	 * Ultimate Multisite addons can ship a sunrise.php in their plugin
	 * root directory. This method scans the plugins directory for
	 * matching addon sunrise files and includes them in alphabetical
	 * order. Pattern: plugins/ultimate-multisite-{name}/sunrise.php
	 *
	 * This runs AFTER domain mapping has loaded but BEFORE ms_loaded,
	 * which means addon sunrise files can:
	 *   - Override $current_site and $current_blog
	 *   - Access $wpdb (already configured by db-config.php)
	 *   - Define constants (BLOG_ID_CURRENT_SITE, etc.)
	 *   - Read Database_Router state (for multi-tenancy routing)
	 *
	 * Addon sunrise files are NOT loaded when Ultimate Multisite itself
	 * is not installed (the main sunrise.php handles that warning).
	 *
	 * @since 2.3.0
	 * @return void
	 */
	protected static function load_addon_sunrise_files(): void {

		$plugins_dir = defined( 'WP_PLUGIN_DIR' )
			? WP_PLUGIN_DIR
			: WP_CONTENT_DIR . '/plugins';

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$candidates = @glob( $plugins_dir . '/ultimate-multisite-*/sunrise.php' );

		if ( empty( $candidates ) || ! is_array( $candidates ) ) {
			return;
		}

		sort( $candidates ); // Alphabetical order for determinism.

		foreach ( $candidates as $addon_sunrise ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.include_include
			include_once $addon_sunrise;
		}
	}

	/**
	 * Loads the Sunrise components, if needed.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function load(): void {

		$should_startup = self::should_startup();

		if ($should_startup) {
			/**
			 *  Load dependencies and get autoload running
			 */
			self::load_dependencies();

			/*
			 * Plugin Limits
			 */
			\WP_Ultimo\Limits\Plugin_Limits::get_instance();

			/*
			 * Theme Limits
			 */
			\WP_Ultimo\Limits\Theme_Limits::get_instance();

			/**
			 * Define the Ultimate Multisite main debug constant.
			 */
			! defined('WP_ULTIMO_DEBUG') && define('WP_ULTIMO_DEBUG', false);

			/**
			 * Check if we are using security mode.
			 */
			$security_mode = (bool) (int) wu_get_setting_early('security_mode');

			if ($security_mode) {
				if (wu_get_isset($_GET, 'wu_secure') === wu_get_security_mode_key()) { // phpcs:ignore WordPress.Security.NonceVerification
					wu_save_setting_early('security_mode', false);
				} else {
					/**
					 *  Disable all plugins except Ultimate Multisite
					 */
					add_filter('option_active_plugins', fn() => []);

					add_filter('site_option_active_sitewide_plugins', fn() => [basename(dirname(__DIR__)) . '/ultimate-multisite.php' => 1], 10, 0);
				}
			}
		}
	}

	/**
	 * Adds an additional hook that runs after ms_loaded.
	 *
	 * This is needed since there isn't really a good hook we can use
	 * that gets triggered right after ms_loaded. The hook here
	 * only runs on a very high priority number on ms_loaded,
	 * giving other modules time to register their hooks so they
	 * can be run here.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public static function loaded(): void {

		do_action('wu_sunrise_loaded');
	}

	/**
	 * Checks if we need to upgrade the sunrise version on wp-content
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function manage_sunrise_updates(): void {
		/*
		 * Get current version of the sunrise.php file
		 */
		$old_version = defined('WP_ULTIMO_SUNRISE_VERSION') ? WP_ULTIMO_SUNRISE_VERSION : '0.0.1';

		if (version_compare($old_version, self::$version, '<')) {
			self::try_upgrade();
		}
	}

	/**
	 * Upgrades the sunrise file, if necessary.
	 *
	 * @todo: lots of logic needs to be here to deal with other plugins' code on sunrise.php
	 * @since 2.0.0
	 * @return true|\WP_Error
	 */
	public static function try_upgrade() {

		$copy_results = copy(
			dirname(WP_ULTIMO_PLUGIN_FILE) . '/sunrise.php',
			WP_CONTENT_DIR . '/sunrise.php'
		);

		if ( ! $copy_results) {
			$error = error_get_last();
			wu_log_add('sunrise', $error['message'], LogLevel::ERROR);

			/* translators: the placeholder is an error message */
			return new \WP_Error('error', sprintf(__('Sunrise copy failed: %s', 'ultimate-multisite'), $error['message']));
		}

		wu_log_add('sunrise', __('Sunrise upgrade attempt succeeded.', 'ultimate-multisite'));
		return true;
	}

	/**
	 * Reads the sunrise meta file and loads it to the static cache.
	 *
	 * It only reaches the filesystem on the first read, keeping
	 * a cache of the results on a static class property then on.
	 *
	 * @since 2.0.11
	 * @return array
	 */
	protected static function read_sunrise_meta() {

		if (is_array(self::$sunrise_meta)) {
			return self::$sunrise_meta;
		}

		self::$sunrise_meta = get_network_option(
			null,
			'wu_sunrise_meta',
			[
				'active'           => false,
				'created'          => 'unknown',
				'last_activated'   => 'unknown',
				'last_deactivated' => 'unknown',
				'last_modified'    => 'unknown',
			]
		);

		return self::$sunrise_meta;
	}

	/**
	 * Method for imputing Sunrise data at wp-ultimo-system-info table.
	 *
	 * @since 2.0.11
	 * @param array $sys_info Array containing Ultimate Multisite installation info.
	 * @return array Returns the array, modified with the sunrise data.
	 */
	public static function system_info($sys_info) {

		$data = self::read_sunrise_meta();

		$sys_info = array_merge(
			$sys_info,
			[
				'Sunrise Data' => [
					'sunrise-status'           => [
						'tooltip' => '',
						'title'   => 'Active',
						'value'   => $data['active'] ? 'Enabled' : 'Disabled',
					],
					'sunrise-data'             => [
						'tooltip' => '',
						'title'   => 'Version',
						'value'   => self::$version,
					],
					'sunrise-created'          => [
						'tooltip' => '',
						'title'   => 'Created',
						'value'   => is_int($data['created']) ? gmdate('Y-m-d @ H:i:s', $data['created']) : $data['created'],
					],
					'sunrise-last-activated'   => [
						'tooltip' => '',
						'title'   => 'Last Activated',
						'value'   => is_int($data['last_activated']) ? gmdate('Y-m-d @ H:i:s', $data['last_activated']) : $data['last_activated'],
					],
					'sunrise-last-deactivated' => [
						'tooltip' => '',
						'title'   => 'Last Deactivated',
						'value'   => is_int($data['last_deactivated']) ? gmdate('Y-m-d @ H:i:s', $data['last_deactivated']) : $data['last_deactivated'],
					],
					'sunrise-last-modified'    => [
						'tooltip' => '',
						'title'   => 'Last Modified',
						'value'   => is_int($data['last_modified']) ? gmdate('Y-m-d @ H:i:s', $data['last_modified']) : $data['last_modified'],
					],
				],
			]
		);

		return $sys_info;
	}

	/**
	 * Checks if the sunrise extra modules need to be loaded.
	 *
	 * @since 2.0.11
	 * @return boolean
	 */
	public static function should_load_sunrise() {

		$meta = self::read_sunrise_meta();

		return wu_get_isset($meta, 'active', false);
	}

	/**
	 * Makes sure the meta file accurately reflects the state of the main plugin.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public static function maybe_tap_on_init(): void {

		$state = function_exists('WP_Ultimo') && WP_Ultimo()->is_loaded();

		self::maybe_tap($state ? 'activating' : 'deactivating');
	}

	/**
	 * Updates the sunrise meta file, if an update is due.
	 *
	 * @since 2.0.11
	 *
	 * @param string $mode Either activating or deactivating.
	 * @return bool
	 */
	public static function maybe_tap($mode = 'activating') {

		$meta = self::read_sunrise_meta();

		$is_active = isset($meta['active']) && $meta['active'];

		if ($is_active && 'activating' === $mode) {
			return false;
		} elseif ( ! $is_active && 'deactivating' === $mode) {
			return false;
		}

		return (bool) self::tap($mode, $meta);
	}

	/**
	 * Updates the sunrise meta file.
	 *
	 * @since 2.0.11
	 *
	 * @param string $mode Either activating or deactivating.
	 * @param array  $existing Existing meta file values.
	 * @return bool
	 */
	protected static function tap($mode = 'activating', $existing = []) {

		$now = gmdate('U');

		$to_save = wp_parse_args(
			$existing,
			[
				'active'           => false,
				'created'          => $now,
				'last_activated'   => 'unknown',
				'last_deactivated' => 'unknown',
			]
		);

		if ('unknown' === $to_save['created']) {
			$to_save['created'] = $now;
		}

		if ('activating' === $mode) {
			$to_save['active']         = true;
			$to_save['last_activated'] = $now;
		} elseif ('deactivating' === $mode) {
			$to_save['active']           = false;
			$to_save['last_deactivated'] = $now;
		} else {
			return false;
		}

		$to_save['last_modified'] = $now;

		return update_network_option(null, 'wu_sunrise_meta', $to_save);
	}

	// phpcs:ignore
	private function __construct() {}
}
