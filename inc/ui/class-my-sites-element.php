<?php
/**
 * Adds the My_Sites_Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use WP_Ultimo\Models\Customer;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Checkout Element UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class My_Sites_Element extends Base_Element {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The id of the element.
	 *
	 * Something simple, without prefixes, like 'checkout', or 'pricing-tables'.
	 *
	 * This is used to construct shortcodes by prefixing the id with 'wu_'
	 * e.g. an id checkout becomes the shortcode 'wu_checkout' and
	 * to generate the Gutenberg block by prefixing it with 'wp-ultimo/'
	 * e.g. checkout would become the block 'wp-ultimo/checkout'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $id = 'my-sites';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var boolean
	 */
	protected $public = true;

	/**
	 * The current customer.
	 *
	 * @since 2.2.0
	 * @var Customer
	 */
	protected $customer;

	/**
	 * The sites of the current customer.
	 *
	 * @since 2.2.0
	 * @var array
	 */
	protected $sites;

	/**
	 * The icon of the UI element.
	 * e.g. return fa fa-search
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 * @return string
	 */
	public function get_icon($context = 'block') {

		if ('elementor' === $context) {
			return 'eicon-info-circle-o';
		}

		return 'fa fa-search';
	}

	/**
	 * The title of the UI element.
	 *
	 * This is used on the Blocks list of Gutenberg.
	 * You should return a string with the localized title.
	 * e.g. return __('My Element', 'ultimate-multisite').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('My Sites', 'ultimate-multisite');
	}

	/**
	 * The description of the UI element.
	 *
	 * This is also used on the Gutenberg block list
	 * to explain what this block is about.
	 * You should return a string with the localized title.
	 * e.g. return __('Adds a checkout form to the page', 'ultimate-multisite').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('Displays a list of all sites owned by the current customer with quick access links.', 'ultimate-multisite');
	}

	/**
	 * The list of fields to be added to Gutenberg.
	 *
	 * If you plan to add Gutenberg controls to this block,
	 * you'll need to return an array of fields, following
	 * our fields interface (@see inc/ui/class-field.php).
	 *
	 * You can create new Gutenberg panels by adding fields
	 * with the type 'header'. See the Checkout Elements for reference.
	 *
	 * @see inc/ui/class-checkout-element.php
	 *
	 * Return an empty array if you don't have controls to add.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function fields() {

		$fields = [];

		$fields['header'] = [
			'title' => __('General', 'ultimate-multisite'),
			'desc'  => __('General', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['site_manage_type'] = [
			'type'    => 'select',
			'title'   => __('Site Manage Type', 'ultimate-multisite'),
			'desc'    => __('The page to manage a site.', 'ultimate-multisite'),
			'tooltip' => '',
			'default' => 'default',
			'options' => [
				'default'     => __('Same Page', 'ultimate-multisite'),
				'wp_admin'    => __('WP Admin', 'ultimate-multisite'),
				'custom_page' => __('Custom Page', 'ultimate-multisite'),
			],
		];

		$fields['site_show'] = [
			'type'    => 'select',
			'title'   => __('Which sites to show?', 'ultimate-multisite'),
			'desc'    => __('Select which sites should be listed for user.', 'ultimate-multisite'),
			'tooltip' => '',
			'default' => 'all',
			'options' => [
				'all'   => __('All', 'ultimate-multisite'),
				'owned' => __('Owned', 'ultimate-multisite'),
			],
		];

		$pages = get_pages(
			[
				'exclude' => [get_the_ID()], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			]
		);

		$pages = $pages ?: [];

		$pages_list = [0 => __('Current Page', 'ultimate-multisite')];

		foreach ($pages as $page) {
			$pages_list[ $page->ID ] = $page->post_title;
		}

		$fields['custom_manage_page'] = [
			'type'     => 'select',
			'title'    => __('Manage Redirect Page', 'ultimate-multisite'),
			'value'    => 0,
			'desc'     => __('The page to redirect user after select a site.', 'ultimate-multisite'),
			'tooltip'  => '',
			'required' => [
				'site_manage_type' => 'custom_page',
			],
			'options'  => $pages_list,
		];

		$fields['columns'] = [
			'type'    => 'number',
			'title'   => __('Columns', 'ultimate-multisite'),
			'desc'    => __('How many columns to use.', 'ultimate-multisite'),
			'tooltip' => '',
			'value'   => 4,
			'min'     => 1,
			'max'     => 5,
		];

		$fields['display_images'] = [
			'type'    => 'toggle',
			'title'   => __('Display Site Screenshot?', 'ultimate-multisite'),
			'desc'    => __('Toggle to show/hide the site screenshots on the element.', 'ultimate-multisite'),
			'tooltip' => '',
			'value'   => 1,
		];

		return $fields;
	}

	/**
	 * The list of keywords for this element.
	 *
	 * Return an array of strings with keywords describing this
	 * element. Gutenberg uses this to help customers find blocks.
	 *
	 * e.g.:
	 * return array(
	 *  'Ultimate Multisite',
	 *  'Site',
	 *  'Form',
	 *  'Cart',
	 * );
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function keywords() {

		return [
			'WP Ultimo',
			'Ultimate Multisite',
			'Site',
			'Form',
			'Cart',
		];
	}

	/**
	 * List of default parameters for the element.
	 *
	 * If you are planning to add controls using the fields,
	 * it might be a good idea to use this method to set defaults
	 * for the parameters you are expecting.
	 *
	 * These defaults will be used inside a 'wp_parse_args' call
	 * before passing the parameters down to the block render
	 * function and the shortcode render function.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function defaults() {

		return [
			'columns'            => 4,
			'display_images'     => 1,
			'site_manage_type'   => 'default',
			'custom_manage_page' => 0,
			'site_show'          => 'owned',
		];
	}

	/**
	 * Loads the necessary scripts and styles for this element.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		wp_enqueue_style('wu-admin');
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup(): void {

		global $wpdb;

		if ( ! is_user_logged_in() || WP_Ultimo()->currents->is_site_set_via_request()) {
			$this->set_display(false);

			return;
		}

		$this->customer = WP_Ultimo()->currents->get_customer();
	}

	/**
	 * Allows the setup in the context of previews.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_preview(): void {

		$this->customer = wu_mock_customer();

		$this->sites = [
			wu_mock_site(1),
			wu_mock_site(2),
		];
	}

	/**
	 * The content to be output on the screen.
	 *
	 * Should return HTML markup to be used to display the block.
	 * This method is shared between the block render method and
	 * the shortcode implementation.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output($atts, $content = null): void {

		$atts['customer'] = $this->customer;

		$atts['sites'] = $this->get_sites(wu_get_isset($atts, 'site_show'));

		wu_get_template('dashboard-widgets/my-sites', $atts);
	}

	/**
	 * Get sites to display on widget
	 *
	 * @param string $show   The kind of output expected, i.e: all, owned.
	 * @return array $sites  The list of sites do display.
	 */
	protected function get_sites(?string $show = null): array {

		if ( ! empty($this->sites)) {
			return $this->sites;
		}

		$this->sites = apply_filters('wp_ultimo_pre_my_sites_sites', [], $show);

		if ( ! empty($this->sites)) {
			return $this->sites;
		}

		if ( ! empty($this->customer)) {
			$pending_sites = \WP_Ultimo\Models\Site::get_all_by_type('pending', ['customer_id' => $this->customer->get_id()]);

			$customer_sites = array_reduce(
				$this->customer->get_sites(),
				function ($customer_sites, $site) {
					$customer_sites[ $site->get_id() ] = $site;
					return $customer_sites;
				}
			);
		}

		if ('all' === $show) {
			$wp_user_sites = get_blogs_of_user(get_current_user_id());

			$user_sites = array_reduce(
				$wp_user_sites,
				function ($user_sites, $wp_site) use ($customer_sites) {
					if ( ! array_key_exists($wp_site->userblog_id, $customer_sites ?? []) && get_main_site_id() !== $wp_site->userblog_id) {
						$wu_site = wu_get_site($wp_site->userblog_id);
						$wu_site->set_membership_id(0);
						$user_sites[ $wp_site->userblog_id ] = $wu_site;
					}

					return $user_sites;
				}
			);
		}

		$sites = array_merge(
			$pending_sites ?? [],
			$customer_sites ?? [],
			$user_sites ?? [],
		);

		$this->sites = apply_filters('wp_ultimo_after_my_sites_sites', $sites, $show);

		return $this->sites;
	}


	/**
	 * Returns the manage URL for sites, depending on the environment.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $site_id     A Site ID.
	 * @param string $type        De redirection type (can be: default, wp_admin or custom_page).
	 * @param string $custom_page_id The path to redirect ir using custom_page type.
	 * @return string
	 */
	public function get_manage_url($site_id, $type = 'default', $custom_page_id = 0) {

		if ('wp_admin' === $type) {
			// Use wu_get_admin_url() to get admin URL with magic link support if needed
			// This function automatically adds magic links for custom domains
			$admin_url = wu_get_admin_url($site_id);

			// Check if magic link is already used (contains wu_magic_token)
			// If magic link exists, add admin path directly to the URL
			$has_magic_link = (strpos($admin_url, 'wu_magic_token') !== false);

			if ($has_magic_link) {
				// Magic link is generated on home URL, we need to add admin path
				// Parse the URL to get the base URL and query string
				$parsed_url = wp_parse_url($admin_url);
				$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				if (isset($parsed_url['port'])) {
					$base_url .= ':' . $parsed_url['port'];
				}

				// Get admin path (respects WP Hide and other plugins)
				switch_to_blog($site_id);
				$admin_path = wp_parse_url(get_admin_url(), PHP_URL_PATH);
				restore_current_blog();

				// Ensure admin path exists, default to /wp-admin if not found
				if (empty($admin_path)) {
					$admin_path = '/wp-admin';
				}

				// Build new URL with admin path
				$query_string = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
				$admin_url    = $base_url . $admin_path . $query_string;
			} elseif (class_exists('\WP_Ultimo\SSO\SSO')) {
				// Add SSO support if enabled and magic link is not used
				// SSO needs to go through login page first, then redirect to admin
				$sso = \WP_Ultimo\SSO\SSO::get_instance();
				if ($sso && $sso->is_enabled()) {
					// Switch to target site to get correct login URL
					switch_to_blog($site_id);
					$sso_path         = $sso->get_url_path();
					$actual_admin_url = get_admin_url($site_id);
					$login_url        = wp_login_url($actual_admin_url);
					restore_current_blog();

					// Add SSO parameter to login URL
					$admin_url = add_query_arg($sso_path, 'login', $login_url);
				}
			}

			// Apply wp_ultimo_manage_url filter for backward compatibility
			$site = wu_get_site($site_id);
			if ($site) {
				$admin_url = apply_filters('wp_ultimo_manage_url', $admin_url, $site);
			}

			return $admin_url;
		}

		if ('custom_page' === $type) {
			$custom_page = get_page_link($custom_page_id);

			$url_param = \WP_Ultimo\Current::param_key('site');

			$site_hash = \WP_Ultimo\Helpers\Hash::encode($site_id, 'site');

			return add_query_arg(
				[
					$url_param => $site_hash,
				],
				$custom_page
			);
		}

		// For default type, use admin URL directly (like the old version)
		// This ensures the "Manage" button goes directly to admin, not to front-end URL
		if ( ! is_admin()) {
			// Use wu_get_admin_url() to get admin URL with magic link support if needed
			// This function automatically adds magic links for custom domains
			$admin_url = wu_get_admin_url($site_id);

			// Check if magic link is already used (contains wu_magic_token)
			// If magic link exists, add admin path directly to the URL
			$has_magic_link = (strpos($admin_url, 'wu_magic_token') !== false);

			if ($has_magic_link) {
				// Magic link is generated on home URL, we need to add admin path
				// Parse the URL to get the base URL and query string
				$parsed_url = wp_parse_url($admin_url);
				$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				if (isset($parsed_url['port'])) {
					$base_url .= ':' . $parsed_url['port'];
				}

				// Get admin path (respects WP Hide and other plugins)
				switch_to_blog($site_id);
				$admin_path = wp_parse_url(get_admin_url(), PHP_URL_PATH);
				restore_current_blog();

				// Ensure admin path exists, default to /wp-admin if not found
				if (empty($admin_path)) {
					$admin_path = '/wp-admin';
				}

				// Build new URL with admin path
				$query_string = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
				$admin_url    = $base_url . $admin_path . $query_string;
			} elseif (class_exists('\WP_Ultimo\SSO\SSO')) {
				// Add SSO support if enabled and magic link is not used
				// SSO needs to go through login page first, then redirect to admin
				$sso = \WP_Ultimo\SSO\SSO::get_instance();
				if ($sso && $sso->is_enabled()) {
					// Switch to target site to get correct login URL
					switch_to_blog($site_id);
					$sso_path         = $sso->get_url_path();
					$actual_admin_url = get_admin_url($site_id);
					$login_url        = wp_login_url($actual_admin_url);
					restore_current_blog();

					// Add SSO parameter to login URL
					$admin_url = add_query_arg($sso_path, 'login', $login_url);
				}
			}

			// Apply wp_ultimo_manage_url filter for backward compatibility
			$site = wu_get_site($site_id);
			if ($site) {
				$admin_url = apply_filters('wp_ultimo_manage_url', $admin_url, $site);
			}

			return $admin_url;
		}

		return \WP_Ultimo\Current::get_manage_url($site_id, 'site');
	}

	/**
	 * Returns the new site URL for site creation.
	 *
	 * @since 2.0.21
	 *
	 * @return string
	 */
	public function get_new_site_url() {

		$membership = WP_Ultimo()->currents->get_membership();

		$checkout_pages = \WP_Ultimo\Checkout\Checkout_Pages::get_instance();

		$url = $checkout_pages->get_page_url('new_site');

		if ($membership) {
			if ($url) {
				return add_query_arg(
					[
						'membership' => $membership->get_hash(),
					],
					$url
				);
			}

			if (is_main_site()) {
				$sites = $membership->get_sites(false);

				if ( ! empty($sites)) {
					return add_query_arg(
						[
							'page' => 'add-new-site',
						],
						get_admin_url($sites[0]->get_id())
					);
				}

				return '';
			}
		}

		return admin_url('admin.php?page=add-new-site');
	}
}
