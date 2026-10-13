<?php
/**
 * WP Ultimo Dashboard Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * WP Ultimo Dashboard Admin Page.
 */
class Addons_Admin_Page extends Wizard_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-addons';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * Menu position. This is only used for top-level menus
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $position = 999;

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $parent = 'wp-ultimo';

	/**
	 * If this number is greater than 0, a badge with the number will be displayed alongside the menu title
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $badge_count = 0;

	/**
	 * Holds the admin panels where this page should be displayed, as well as which capability to require.
	 *
	 * To add a page to the regular admin (wp-admin/), use: 'admin_menu' => 'capability_here'
	 * To add a page to the network admin (wp-admin/network), use: 'network_admin_menu' => 'capability_here'
	 * To add a page to the user (wp-admin/user) admin, use: 'user_admin_menu' => 'capability_here'
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $supported_panels = array(
		'network_admin_menu' => 'wu_read_settings',
	);

	/**
	 * Should we hide admin notices on this page?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $hide_admin_notices = false;

	/**
	 * Should we force the admin menu into a folded state?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $fold_menu = false;

	/**
	 * Holds the section slug for the URLs.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $section_slug = 'tab';

	/**
	 * Defines if the step links on the side are clickable or not.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $clickable_navigation = true;

	/**
	 * Caches the list of add-ons.
	 *
	 * @since 2.0.0
	 * @var null|array
	 */
	protected $addons;

	/**
	 * Allow child classes to add hooks to be run once the page is loaded.
	 *
	 * @see https://codex.wordpress.org/Plugin_API/Action_Reference/load-(page)
	 * @since 1.8.2
	 * @return void
	 */
	public function init() {

		parent::init();

		add_action('wp_ajax_serve_addons_list', array($this, 'serve_addons_list'));
	}

	/**
	 * Register forms
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms() {

		wu_register_form(
			'addon_more_info',
			array(
				'render'  => array($this, 'display_more_info'),
				'handler' => array($this, 'install_addon'),
			)
		);
	}

	/**
	 * Get an addon given a slug.
	 *
	 * @param string $addon_slug The addon Slug.
	 *
	 * @return array
	 */
	private function get_addon(string $addon_slug): array {
		return array_find($this->get_addons_list(), fn($addon) => $addon['slug'] === $addon_slug);
	}

	/**
	 * Displays the more info tab.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function display_more_info() {

		$addon_slug = wu_request('addon');

		$addon = $this->get_addon($addon_slug);

		wu_get_template(
			'base/addons/details',
			array(
				'addon'      => (object) $addon,
				'addon_slug' => $addon_slug,
			)
		);

		do_action('wu_form_scripts', false);
	}

	/**
	 * Installs a given add-on.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function install_addon() {

		if (! current_user_can('manage_network_plugins')) {
			$error = new \WP_Error('error', __('You do not have enough permissions to perform this task.', 'ultimate-multisite'));

			wp_send_json_error($error);
		}

		$addon_slug = wu_request('addon');

		$addon = $this->get_addon($addon_slug);

		$download_url = $addon['extensions']['wp-update-server-plugin']['download_url'] ?? '';

		if (! $download_url) {
			// translators: %s slug of the addon.
			wp_send_json_error(sprintf(__('Unable to download update addon. User does not have permission to install %s', 'ultimate-multisite'), $addon_slug), 400);
		}

		/**
		 * Security check: Ensure URL is from our domain
		 */
		$allowed = strncmp($download_url, MULTISITE_ULTIMATE_UPDATE_URL, strlen(MULTISITE_ULTIMATE_UPDATE_URL)) === 0;

		if (! $allowed) {
			$error = new \WP_Error('insecure-url', __('You are trying to download an add-on from an insecure URL', 'ultimate-multisite'));
			wp_send_json_error($error);
		}

		// Install the plugin
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/misc.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$skin     = new \Automatic_Upgrader_Skin(array());
		$upgrader = new \Plugin_Upgrader($skin);

		// Add authentication headers for download
		add_filter('http_request_args', array($this, 'add_auth_headers_to_download'), 10, 2);

		$results = $upgrader->install($download_url);

		remove_filter('http_request_args', array($this, 'add_auth_headers_to_download'), 10);

		if (is_wp_error($results)) {
			wp_send_json_error($results);
		}

		$messages = $upgrader->skin->get_upgrade_messages();

		if (! in_array($upgrader->strings['process_success'], $messages, true)) {
			$error = new \WP_Error('installation-failed', array_pop($messages));
			wp_send_json_error($error);
		}

		wp_send_json_success(
			array(
				'redirect_url' => add_query_arg(
					array(
						's' => rawurlencode((string) $addon['name']),
					),
					network_admin_url('plugins.php')
				),
			)
		);
	}

	/**
	 * Enqueue the necessary scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts() {

		wp_enqueue_style('theme');

		wp_register_script('wu-addons', wu_get_asset('addons.js', 'js'), array('jquery', 'wu-vue', 'underscore'), wu_get_version(), true);

		wp_localize_script(
			'wu-addons',
			'wu_addons',
			array(
				'search'   => wu_request('s', ''),
				'category' => wu_request('tab', 'all'),
				'i18n'     => array(
					'all' => __('All Add-ons', 'ultimate-multisite'),
				),
			)
		);

		wp_enqueue_script('wu-addons');
	}

	/**
	 * Fetches the list of add-ons available.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_addons_list() {
		/*
		 * Checks if we have a cached version.
		 */
		if (is_array($this->addons)) {
			return $this->addons;
		}

		/*
		 * Check for local cache.
		 */
		$transient_key = wu_get_setting('enable_beta_updates', false) ? 'wu-addons-list-beta' : 'wu-addons-list';

		if (! wu_is_debug()) {
			$addons_list = get_site_transient($transient_key);

			if (is_array($addons_list) && ! empty($addons_list)) {
				/**
				 * Filters the add-ons available in the catalog.
				 *
				 * Providers can use this to decorate catalog entries with local
				 * availability or entitlement information.
				 *
				 * @since 2.18.0
				 * @param array             $addons Add-on catalog entries.
				 * @param Addons_Admin_Page $page   Current add-ons page instance.
				 */
				$this->addons = apply_filters('wu_addons_list', $addons_list, $this);

				return $this->addons;
			}
		}

		// Use WooCommerce API to fetch addons (credentials are optional for public data)
		$api_client = new \WP_Ultimo\Helpers\WooCommerce_API_Client(
			MULTISITE_ULTIMATE_UPDATE_URL,
		);

		$data = $api_client->get_addons();

		if (is_wp_error($data) || empty($data)) {
			// translators: %s error message.
			wu_log_add('api-calls', sprintf(__('Failed to fetch addons from API: %s', 'ultimate-multisite'), $data ? $data->get_error_message() : __('no addons returned', 'ultimate-multisite')));
			return array();
		}

		$installed_plugins = get_plugins();

		foreach ($data as &$item) {
			$item['installed'] = isset($installed_plugins[ "{$item['sku']}/{$item['sku']}.php" ]);
		}

		/** This filter is documented above for cached catalog entries. */
		$this->addons = apply_filters('wu_addons_list', $data, $this);

		set_transient($transient_key, $this->addons, 2 * DAY_IN_SECONDS);

		return $this->addons;
	}

	/**
	 * Gets the list of addons from the remote server.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function serve_addons_list() {

		$addons_list = $this->get_addons_list();

		wp_send_json_success($addons_list);
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Add-ons', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Add-ons', 'ultimate-multisite');
	}

	/**
	 * Adds authentication headers to download requests.
	 *
	 * @param array  $args HTTP request arguments.
	 * @param string $url  The request URL.
	 * @return array Modified arguments.
	 */
	public function add_auth_headers_to_download($args, $url) {
		if (strpos($url, 'ultimatemultisite.com') !== false) {
			$access_token = get_transient('wu-access-token');
			if ($access_token) {
				if (! isset($args['headers'])) {
					$args['headers'] = array();
				}
				$args['headers']['Authorization'] = 'Bearer ' . $access_token;
			}
		}
		return $args;
	}
	/**
	 * Every child class should implement the output method to display the contents of the page.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function output() {

		$redirect_url = wu_network_admin_url('wp-ultimo-addons');
		$code         = wu_request('code');

		$addon_repo = \WP_Ultimo::get_instance()->get_addon_repository();

		if ( wu_request('logout') && wp_verify_nonce(wu_request('_wpnonce'), 'logout')) {
			$addon_repo->delete_tokens();
		}

		$more_info_url = wu_get_form_url(
			'addon_more_info',
			array(
				'width' => 768,
				'addon' => 'ADDON_SLUG',
			)
		);

		$user = $addon_repo->get_user_data();

		if (! $user && $code) {
			$addon_repo->save_access_token($code, $redirect_url);
			$user = $addon_repo->get_user_data();
		}

		wu_get_template(
			'base/addons',
			array(
				'screen'               => get_current_screen(),
				'page'                 => $this,
				'classes'              => '',
				'sections'             => $this->get_sections(),
				'current_section'      => $this->get_current_section(),
				'clickable_navigation' => $this->clickable_navigation,
				'more_info_url'        => $more_info_url,
				'oauth_url'            => $addon_repo->get_oauth_url(),
				'logout_url'           => wu_network_admin_url(
					'wp-ultimo-addons',
					[
						'logout'   => 'logout',
						'_wpnonce' => wp_create_nonce('logout'),
					]
				),
				'user'                 => $user ?? false,
			)
		);
	}

	/**
	 * Returns the list of settings sections.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_sections() {

		return array(
			'all'           => array(
				'title' => __('All Add-ons', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-grid',
			),
			'premium'       => array(
				'title' => __('Premium', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-rocket',
			),
			'free'          => array(
				'title' => __('Free', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-pin',
			),
			'gateways'      => array(
				'title' => __('Gateways', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-credit-card',
			),
			'growth'        => array(
				'title' => __('Growth & Scaling', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-line-graph',
			),
			'integrations'  => array(
				'title' => __('Integrations', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-power-plug',
			),
			'customization' => array(
				'title' => __('Customization', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-edit',
			),
			'admin-theme'   => array(
				'title' => __('Admin Themes', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-palette',
			),
			'monetization'  => array(
				'title' => __('Monetization', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-credit',
			),
			'migrators'     => array(
				'title' => __('Migrators', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-publish',
			),
			'marketplace'   => array(
				'title' => __('Marketplace', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-shop',
			),
		);
	}

	/**
	 * Default handler for step submission. Simply redirects to the next step.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function default_handler() {

		// Nonce handled in calling function
		WP_Ultimo()->settings->save_settings($_POST); // phpcs:ignore WordPress.Security.NonceVerification

		wp_safe_redirect(add_query_arg('updated', 1, wu_get_current_url()));

		exit;
	}
}
