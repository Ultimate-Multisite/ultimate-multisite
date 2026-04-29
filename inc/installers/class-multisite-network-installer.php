<?php
/**
 * Multisite Network Installer.
 *
 * Handles the step-by-step installation of a WordPress Multisite network
 * via AJAX, used by the Multisite Setup wizard page.
 *
 * @package WP_Ultimo
 * @subpackage Installers
 * @since 2.0.0
 */

namespace WP_Ultimo\Installers;

use Psr\Log\LogLevel;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Multisite Network Installer.
 *
 * @since 2.0.0
 */
class Multisite_Network_Installer extends Base_Installer {
	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Transient key for storing the network configuration.
	 *
	 * @var string
	 */
	const CONFIG_TRANSIENT = 'wu_multisite_network_config';

	/**
	 * Returns the list of installation steps.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_steps() {

		$has_multisite_constant = defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE;
		$has_network_tables     = $this->check_network_tables_exist();
		$has_wp_config_updated  = defined('MULTISITE') && MULTISITE; // @phpstan-ignore phpstanWP.wpConstant.fetch

		return [
			'enable_multisite' => [
				'done'        => $has_multisite_constant,
				'title'       => __('Enable Multisite', 'ultimate-multisite'),
				'description' => __('Adds WP_ALLOW_MULTISITE constant to wp-config.php.', 'ultimate-multisite'),
				'pending'     => __('Pending', 'ultimate-multisite'),
				'installing'  => __('Enabling multisite...', 'ultimate-multisite'),
				'success'     => __('Success!', 'ultimate-multisite'),
				'help'        => '',
			],
			'create_network'   => [
				'done'        => $has_network_tables,
				'title'       => __('Create Network', 'ultimate-multisite'),
				'description' => __('Creates network database tables and populates network data.', 'ultimate-multisite'),
				'pending'     => __('Pending', 'ultimate-multisite'),
				'installing'  => __('Creating network tables...', 'ultimate-multisite'),
				'success'     => __('Success!', 'ultimate-multisite'),
				'help'        => '',
			],
			'update_wp_config' => [
				'done'        => $has_wp_config_updated,
				'title'       => __('Update Configuration', 'ultimate-multisite'),
				'description' => __('Adds final multisite constants to wp-config.php.', 'ultimate-multisite'),
				'pending'     => __('Pending', 'ultimate-multisite'),
				'installing'  => __('Updating configuration...', 'ultimate-multisite'),
				'success'     => __('Success!', 'ultimate-multisite'),
				'help'        => '',
			],
			'network_activate' => [
				'done'        => is_plugin_active_for_network(WP_ULTIMO_PLUGIN_BASENAME),
				'title'       => __('Network Activate Plugin', 'ultimate-multisite'),
				'description' => __('Network-activates Ultimate Multisite so it runs across the entire network.', 'ultimate-multisite'),
				'pending'     => __('Pending', 'ultimate-multisite'),
				'installing'  => __('Activating plugin...', 'ultimate-multisite'),
				'success'     => __('Success!', 'ultimate-multisite'),
				'help'        => '',
			],
		];
	}

	/**
	 * Checks whether the multisite network tables exist.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	protected function check_network_tables_exist(): bool {

		global $wpdb;

		$table_name = $wpdb->base_prefix . 'site';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
		);

		return $result === $table_name;
	}

	/**
	 * Returns the stored network configuration from the transient.
	 *
	 * @since 2.0.0
	 * @throws \Exception When no configuration is found.
	 * @return array
	 */
	protected function get_config(): array {

		$config = get_transient(self::CONFIG_TRANSIENT);

		if (empty($config) || ! is_array($config)) {
			throw new \Exception(esc_html__('Network configuration not found. Please go back and submit the configuration form again.', 'ultimate-multisite'));
		}

		return $config;
	}

	/**
	 * Step 1: Enable multisite by adding WP_ALLOW_MULTISITE to wp-config.php.
	 *
	 * @since 2.0.0
	 * @throws \Exception When the constant cannot be injected.
	 * @return void
	 */
	public function _install_enable_multisite(): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		if (defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE) {
			return;
		}

		$wp_config = \WP_Ultimo\Helpers\WP_Config::get_instance();

		$result = $wp_config->inject_wp_config_constant('WP_ALLOW_MULTISITE', true);

		if (is_wp_error($result)) {
			throw new \Exception(esc_html($result->get_error_message()));
		}
	}

	/**
	 * Step 2: Create network tables and populate network data.
	 *
	 * @since 2.0.0
	 * @throws \Exception When network creation fails.
	 * @return void
	 */
	public function _install_create_network(): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		global $wpdb;

		if ($this->check_network_tables_exist()) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$has_data = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}site");

			if ($has_data) {
				return;
			}
		}

		$config = $this->get_config();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if (! function_exists('install_network')) {
			require_once ABSPATH . 'wp-admin/includes/network.php';
		}

		// On a single-site install, $wpdb doesn't have multisite table names set.
		// Explicitly set the core WordPress multisite tables that install_network()
		// and wp_get_db_schema('global') reference via $wpdb->tablename interpolation.
		// We cannot rely solely on $wpdb->ms_global_tables because plugins may have
		// appended custom entries while the core defaults could be missing.
		$wp_ms_tables = ['blogs', 'blogmeta', 'signups', 'site', 'sitemeta', 'registration_log'];

		foreach ($wp_ms_tables as $table) {
			$wpdb->$table = $wpdb->base_prefix . $table;
		}

		// Also set any additional tables registered in ms_global_tables (e.g. by addons).
		foreach ($wpdb->ms_global_tables as $table) {
			if (! isset($wpdb->$table)) {
				$wpdb->$table = $wpdb->base_prefix . $table;
			}
		}

		install_network();

		$result = populate_network(
			1,
			$config['domain'],
			$config['email'],
			$config['sitename'],
			$config['base'],
			$config['subdomain_install']
		);

		// populate_network() returns WP_Error('no_wildcard_dns') for subdomain
		// installs when wildcard DNS isn't configured. This is a warning, not
		// a fatal error — the network tables are still created successfully.
		if (is_wp_error($result) && ! in_array($result->get_error_code(), ['no_wildcard_dns', 'siteid_exists'], true)) {
			throw new \Exception(esc_html($result->get_error_message()));
		}

		// Fix siteurl trailing slash to prevent cookie hash change.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->sitemeta,
			['meta_value' => untrailingslashit(get_option('siteurl'))], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			[
				'site_id'  => 1,
				'meta_key' => 'siteurl', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			]
		);
	}

	/**
	 * Step 3: Add final multisite constants to wp-config.php.
	 *
	 * @since 2.0.0
	 * @throws \Exception When constants cannot be injected.
	 * @return void
	 */
	public function _install_update_wp_config(): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		if (defined('MULTISITE') && MULTISITE) { // @phpstan-ignore phpstanWP.wpConstant.fetch
			return;
		}

		$config = $this->get_config();

		$wp_config = \WP_Ultimo\Helpers\WP_Config::get_instance();

		$constants = [
			'MULTISITE'            => true,
			'SUBDOMAIN_INSTALL'    => $config['subdomain_install'],
			'DOMAIN_CURRENT_SITE'  => $config['domain'],
			'PATH_CURRENT_SITE'    => '/',
			'SITE_ID_CURRENT_SITE' => 1,
			'BLOG_ID_CURRENT_SITE' => 1,
		];

		foreach ($constants as $constant => $value) {
			$result = $wp_config->inject_wp_config_constant($constant, $value);

			if (is_wp_error($result)) {
				throw new \Exception(esc_html($result->get_error_message()));
			}
		}
		wp_cache_flush();
	}

	/**
	 * Step 4: Network-activate Ultimate Multisite.
	 *
	 * Writes directly to the sitemeta table because the MULTISITE constant
	 * was written to wp-config.php only in the previous step
	 * (_install_update_wp_config) and may not yet be reflected in the current
	 * PHP process when OPcache or another bytecode cache is active. Using
	 * activate_plugin() would silently fall back to single-site activation
	 * when is_multisite() returns false. Bypassing it and writing directly to
	 * sitemeta guarantees network-wide activation regardless of whether
	 * multisite constants are loaded in this process.
	 *
	 * After the write, the WordPress object-cache entry for
	 * active_sitewide_plugins is explicitly deleted so that
	 * is_plugin_active_for_network() and wp_get_active_and_valid_plugins()
	 * return fresh data from the database on the next request — even when a
	 * persistent object cache (Redis, Memcached, APC) is in use.
	 *
	 * @since 2.0.0
	 * @throws \Exception When the sitemeta write fails.
	 * @return void
	 */
	public function _install_network_activate(): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		global $wpdb;

		$sitemeta_table = $wpdb->base_prefix . 'sitemeta';
		$network_id     = get_current_network_id();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta_id, meta_value FROM {$sitemeta_table} WHERE meta_key = %s AND site_id = %d",
				'active_sitewide_plugins',
				$network_id
			)
		);
		// phpcs:enable

		$active_plugins = ($existing && $existing->meta_value) ? maybe_unserialize($existing->meta_value) : [];

		if ( ! is_array($active_plugins)) {
			$active_plugins = [];
		}

		// Already network-activated — but still flush the cache so that
		// is_plugin_active_for_network() returns true even when a persistent
		// object cache is holding a stale (empty or outdated) plugins list.
		if (isset($active_plugins[ WP_ULTIMO_PLUGIN_BASENAME ])) {
			wp_cache_delete( "{$network_id}:active_sitewide_plugins", 'site-options' );

			return;
		}

		$active_plugins[ WP_ULTIMO_PLUGIN_BASENAME ] = time();

		$serialized = serialize($active_plugins);

		if ($existing) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->update(
				$sitemeta_table,
				['meta_value' => $serialized], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				['meta_id' => $existing->meta_id]
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert(
				$sitemeta_table,
				[
					'site_id'    => $network_id,
					'meta_key'   => 'active_sitewide_plugins', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $serialized, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				]
			);
		}

		if (false === $result) {
			throw new \Exception(esc_html__('Failed to network-activate Ultimate Multisite: could not write to the sitemeta table.', 'ultimate-multisite'));
		}

		/*
		 * Invalidate the WordPress object-cache entry so the next call to
		 * get_site_option( 'active_sitewide_plugins' ) reads the updated
		 * value from the database rather than returning the stale cached
		 * value.  This matters on any site using a persistent object cache
		 * (Redis, Memcached, APC) — without this, is_plugin_active_for_network()
		 * returns false on the very next page load even though the DB row is
		 * correct, causing the "Network Activate" button to appear to do
		 * nothing (sends success JSON, page reloads, still shows NOT activated).
		 */
		wp_cache_delete( "{$network_id}:active_sitewide_plugins", 'site-options' );
	}
}
