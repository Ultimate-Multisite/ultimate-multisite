<?php
/**
 * Handles limitations to post types, uploads and more.
 *
 * @package WP_Ultimo
 * @subpackage Limits
 * @since 2.0.0
 */

namespace WP_Ultimo\Limits;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles limitations to post types, uploads and more.
 *
 * @since 2.0.0
 */
class Plugin_Limits {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Site-level plugins cache, keyed by blog ID.
	 *
	 * The filters this class registers stay attached while WordPress is
	 * switched to another site, so this cache must never be shared between
	 * sites.
	 *
	 * @since 2.0.0
	 * @var array<int, array>
	 */
	protected $plugins = [];

	/**
	 * Network plugins cache, keyed by blog ID.
	 *
	 * @since 2.0.0
	 * @var array<int, array>
	 */
	protected $network_plugins = [];

	/**
	 * Runs on the first and only instantiation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('wu_sunrise_loaded', [$this, 'load_limitations']);
	}

	/**
	 * Apply limitations if they are available.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function load_limitations(): void {

		if (wu_get_current_site()->has_limitations()) {
			add_filter('site_option_active_sitewide_plugins', [$this, 'deactivate_network_plugins']);

			add_filter('option_active_plugins', [$this, 'deactivate_plugins']);

			add_filter('all_plugins', [$this, 'clear_plugin_list']);

			add_filter('the_content', [$this, 'clean_unused_shortcodes'], 9999);

			add_filter('plugin_action_links', [$this, 'clear_actions'], -2000, 2);

			add_filter('show_network_active_plugins', '__return_true');

			add_action('load-plugins.php', [$this, 'admin_page_hooks']);

			/*
			 * The option filters above are not removed when WordPress switches
			 * to another site, so the lists they cache must not outlive the
			 * site they were built for.
			 */
			add_action('switch_blog', [$this, 'flush_plugin_caches']);
		}

		add_action('wu_site_post_save', [$this, 'activate_and_inactive_plugins'], 10, 3);

		add_action('wu_checkout_done', [$this, 'maybe_activate_and_inactive_plugins'], 10, 5);
	}

	/**
	 * Registers scripts onto the plugins page.
	 *
	 * @since 2.0.5
	 * @return void
	 */
	public function admin_page_hooks(): void {
		add_action('admin_enqueue_scripts', 'add_wubox');
	}

	/**
	 * Automatically activate and deactivate plugins when the site is created or a upgrade happens.
	 *
	 * @since 2.0.0
	 *
	 * @param array                  $data Saved data.
	 * @param \WP_Ultimo\Models\Site $site_object The site created.
	 * @param bool                   $new_site If this site is a new one.
	 * @return void
	 */
	public function activate_and_inactive_plugins($data, $site_object, $new_site): void {
		if ($site_object && $new_site) {
			$site_object->sync_plugins();
		}
	}

	/**
	 * Activate and Deactivate plugins on upgrades and downgrades.
	 *
	 * @since 2.0.5
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment The payment object.
	 * @param \WP_Ultimo\Models\Membership $membership The membership object.
	 * @param \WP_Ultimo\Models\Customer   $customer The customer object.
	 * @param \WP_Ultimo\Checkout\Cart     $cart The cart object.
	 * @param string                       $type The cart type.
	 * @return void
	 */
	public function maybe_activate_and_inactive_plugins($payment, $membership, $customer, $cart, $type): void {
		if ('new' !== $type && $membership) {
			$membership->sync_plugins();
		}
	}

	/**
	 * Clear the actions of the plugins list table.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $actions The list of plugin actions.
	 * @param string $plugin_file The plugin path/slug.
	 * @return array
	 */
	public function clear_actions($actions, $plugin_file) {

		if ( ! function_exists('wu_generate_upgrade_to_unlock_url')) {
			return $actions;
		}

		$plugin_limits = wu_get_current_site()->get_limitations()->plugins;

		if (isset($actions['network_active'])) {
			$actions['network_active'] = sprintf(
				'<span class="wu-styling">
				<span class="wu-text-green-600">
					<span class="dashicons-wu-flash wu-align-text-bottom"></span>%s
				</span>
			</span>',
				__('Always Loaded', 'ultimate-multisite')
			);
		}

		if ($plugin_limits->allowed($plugin_file, 'force_active_locked')) {
			unset($actions['deactivate']);
		} elseif ($plugin_limits->allowed($plugin_file, 'force_inactive_locked')) {
			$upgrade = sprintf(
				'<a href="%s" class="wu-styling" title="%s"><span class="dashicons-wu-lock1 wu-mr-1"></span>%s</a>',
				wu_generate_upgrade_to_unlock_url(
					[
						'module' => 'plugins',
						'type'   => $plugin_file,
					]
				),
				__('Upgrade to unlock', 'ultimate-multisite'),
				__('Upgrade to unlock', 'ultimate-multisite')
			);

			$actions['upgrade'] = $upgrade;

			unset($actions['activate']);
		}

		return $actions;
	}

	/**
	 * Clears the plugin list.
	 *
	 * This method is responsible for controlling
	 * plugin visibility on the plugins list table.
	 *
	 * @since 2.0.0
	 *
	 * @param array $plugins The original list of plugins.
	 * @return array
	 */
	public function clear_plugin_list($plugins) {

		if (is_main_site()) {
			return $plugins;
		}

		$plugin_limits = wu_get_current_site()->get_limitations()->plugins;

		foreach ($plugins as $plugin_slug => $plugin_data) {
			if ($plugin_data['Network']) {
				unset($plugins[ $plugin_slug ]);
			}

			if (str_starts_with($plugin_slug, 'ultimate-multisite')) {
				unset($plugins[ $plugin_slug ]);
			}

			if ($plugin_limits->allowed($plugin_slug, 'hidden')) {
				unset($plugins[ $plugin_slug ]);
			}
		}

		return $plugins;
	}

	/**
	 * Deactivates the network plugins that people are not allowed to use.
	 *
	 * We need different methods because keys are different network wide and on the sub-site level.
	 *
	 * @since 2.0.0
	 *
	 * @param array $plugins Array with the plugins activated.
	 * @return array
	 */
	public function deactivate_network_plugins($plugins) {
		/*
		 * Bail on network admin =)
		 */
		if (is_network_admin() || is_main_site()) {
			return $plugins;
		}

		$blog_id = get_current_blog_id();

		/*
		 * Get the network plugins cache for the current site, if it's set.
		 */
		if (isset($this->network_plugins[ $blog_id ])) {
			return $this->network_plugins[ $blog_id ];
		}

		$plugin_limits = wu_get_current_site()->get_limitations()->plugins;

		foreach ($plugins as $plugin_slug => $timestamp) {
			if (str_contains($plugin_slug, 'ultimate-multisite')) {
				continue;
			}

			if ($plugin_limits->allowed($plugin_slug, 'force_inactive_locked')) {
				unset($plugins[ $plugin_slug ]);
			}
		}

		// Ensure get_plugins function is loaded.
		if ( ! function_exists('get_plugins')) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$other_plugins = get_plugins();

		foreach ($other_plugins as $plugin_path => $other_plugin) {
			if ( ! wu_get_isset($plugins, $plugin_path) && $plugin_limits->allowed($plugin_path, 'force_active_locked')) {
				$plugins[ $plugin_path ] = true;
			}
		}

		$this->network_plugins[ $blog_id ] = $plugins;

		return $plugins;
	}

	/**
	 * Deactivates the plugins that people are not allowed to use.
	 *
	 * @since 2.0.0
	 *
	 * @param array $plugins Array with the plugins activated.
	 * @return array
	 */
	public function deactivate_plugins($plugins) {
		/*
		 * Bail on network admin =)
		 */
		if (is_network_admin() || is_main_site()) {
			return $plugins;
		}

		$blog_id = get_current_blog_id();

		/*
		 * Get the site-level plugins cache for the current site, if it's set.
		 */
		if (isset($this->plugins[ $blog_id ])) {
			return $this->plugins[ $blog_id ];
		}

		$plugin_limits = wu_get_current_site()->get_limitations()->plugins;

		foreach ($plugins as $plugin_slug) {
			if (str_contains((string) $plugin_slug, 'ultimate-multisite')) {
				continue;
			}

			if ($plugin_limits->allowed($plugin_slug, 'force_inactive_locked')) {
				$index = array_search($plugin_slug, $plugins, true);
				unset($plugins[ $index ]);
			}
		}

		// Ensure get_plugins function is loaded.
		if ( ! function_exists('get_plugins')) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$other_plugins = get_plugins();

		foreach ($other_plugins as $plugin_path => $other_plugin) {
			if (in_array($plugin_path, $plugins, true) && $plugin_limits->allowed($plugin_path, 'force_active_locked')) {
				$plugins[] = $plugin_path;
			}
		}

		$this->plugins[ $blog_id ] = $plugins;

		return $plugins;
	}

	/**
	 * Flushes the plugin caches when WordPress switches to another site.
	 *
	 * The `option_active_plugins` and `site_option_active_sitewide_plugins`
	 * filters stay attached across `switch_to_blog()`, so a list built for one
	 * site must not be served on the next one. The per-site keys already
	 * guarantee that; dropping the caches on every switch also keeps them from
	 * growing in long-running processes and makes sure a site whose active
	 * plugins changed while switched is read again instead of served stale.
	 *
	 * @since 2.16.1
	 * @return void
	 */
	public function flush_plugin_caches(): void {

		$this->plugins = [];

		$this->network_plugins = [];
	}

	/**
	 * Remove the unused shortcodes after we disable plugins.
	 *
	 * @since 2.0.0
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function clean_unused_shortcodes($content) {
		return preg_replace('/\[.+\].+\[\/.+\]/', '', $content);
	}
}
