<?php
/**
 * Ultimate Multisite Customer Checkout Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages\Customer_Panel;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Dashboard Admin Page.
 */
class Checkout_Admin_Page extends \WP_Ultimo\Admin_Pages\Base_Customer_Facing_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wu-checkout';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * This page has no parent, so we need to highlight another sub-menu.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $highlight_menu_slug = 'account';

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
	protected $supported_panels = [
		'user_admin_menu' => 'wu_manage_membership',
		'admin_menu'      => 'wu_manage_membership',
	];

	/**
	 * Should we hide admin notices on this page?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $hide_admin_notices = true;

	/**
	 * Should we force the admin menu into a folded state?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $fold_menu = true;

	/**
	 * The return_to URL for sovereign-tenant context.
	 *
	 * @since 2.0.0
	 * @var string|null
	 */
	protected $return_to_url;

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Checkout', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Checkout', 'ultimate-multisite');
	}

	/**
	 * Registers the necessary scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		do_action('wu_checkout_scripts', null, null);
	}

	/**
	 * Overrides the page loaded method.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function page_loaded(): void {

		do_action('wu_setup_checkout', null);

		$this->return_to_url = $this->get_validated_return_to_url();

		parent::page_loaded();
	}

	/**
	 * Returns the sections for this Wizard.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_sections() {

		$sections = [
			'plan' => [
				'title' => __('Change Membership', 'ultimate-multisite'),
				'view'  => [$this, 'display_checkout_form'],
			],
		];

		return $sections;
	}

	/**
	 * Displays the content of the activation section.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function output(): void {
		/*
		 * Renders the base edit page layout, with the columns and everything else =)
		 */
		wu_get_template(
			'base/centered',
			[
				'screen'  => get_current_screen(),
				'page'    => $this,
				'content' => '[wu_checkout slug="wu-checkout"]',
			]
		);
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		\WP_Ultimo\UI\Current_Membership_Element::get_instance()->as_metabox(get_current_screen()->id);

		\WP_Ultimo\UI\Simple_Text_Element::get_instance()->as_inline_content(get_current_screen()->id, 'wu_centered_right');
	}

	/**
	 * Gets and validates the return_to URL from query parameters.
	 *
	 * @since 2.0.0
	 * @return string|null The validated return_to URL or null if invalid.
	 */
	protected function get_validated_return_to_url() {

		$return_to = wu_request('return_to');

		if (empty($return_to)) {
			return null;
		}

		// Decode the URL
		$return_to = urldecode($return_to);

		// Validate that it's a valid URL
		if ( ! filter_var($return_to, FILTER_VALIDATE_URL)) {
			return null;
		}

		// Get the host from the return_to URL
		$return_host = wp_parse_url($return_to, PHP_URL_HOST);

		if (empty($return_host)) {
			return null;
		}

		// Get the current customer
		$customer = wu_get_current_customer();

		if ( ! $customer) {
			return null;
		}

		// Get all sites for the current customer
		$customer_sites = wu_get_sites(
			[
				'customer_id' => $customer->get_id(),
			]
			);

		// Check if the return_to host matches any of the customer's sites
		foreach ($customer_sites as $site) {
			$site_domain = $site->get_domain();

			if ($site_domain === $return_host) {
				return $return_to;
			}
		}

		// Host not found in customer's sites - invalid
		return null;
	}

	/**
	 * Gets the return_to URL for display in the page header.
	 *
	 * @since 2.0.0
	 * @return string|null The return_to URL or null.
	 */
	public function get_return_to_url() {

		return $this->return_to_url;
	}

	/**
	 * Gets the site name for the return_to link.
	 *
	 * @since 2.0.0
	 * @return string|null The site name or null.
	 */
	public function get_return_to_site_name() {

		if (empty($this->return_to_url)) {
			return null;
		}

		$return_host = wp_parse_url($this->return_to_url, PHP_URL_HOST);

		if (empty($return_host)) {
			return null;
		}

		$customer = wu_get_current_customer();

		if ( ! $customer) {
			return null;
		}

		$customer_sites = wu_get_sites(
			[
				'customer_id' => $customer->get_id(),
			]
			);

		foreach ($customer_sites as $site) {
			if ($site->get_domain() === $return_host) {
				return $site->get_title();
			}
		}

		return null;
	}
}
