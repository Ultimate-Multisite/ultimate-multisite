<?php
/**
 * Ultimate Multisite My_Account Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages\Customer_Panel;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Admin_Pages\Base_Customer_Facing_Admin_Page;

/**
 * Ultimate Multisite My_Account Admin Page.
 */
class Account_Admin_Page extends Base_Customer_Facing_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'account';

	/**
	 * Menu position. This is only used for top-level menus
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $position = 101_010_101;

	/**
	 * Dashicon to be used on the menu item. This is only used on top-level menus
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $menu_icon = 'dashicons-wu-email';

	/**
	 * If this number is greater than 0, a badge with the number will be displayed alongside the menu title
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $badge_count = 0;

	/**
	 * Should we hide admin notices on this page?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $hide_admin_notices = true;

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
		'admin_menu'      => 'wu_manage_membership',
		'user_admin_menu' => 'wu_manage_membership',
	];

	/**
	 * The current site instance.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Models\Site
	 */
	protected $current_site;

	/**
	 * The current membership instance.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Models\Membership
	 */
	protected $current_membership;

	/**
	 * The current customer instance.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Models\Customer
	 */
	protected $current_customer;

	/**
	 * The return_to URL for external redirects.
	 *
	 * @since 2.0.0
	 * @var string|null
	 */
	protected $return_to_url;

	/**
	 * Checks if we need to add this page.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->current_site = wu_get_current_site();

		$this->current_membership = $this->current_site->get_membership();

		$this->current_customer = wu_get_current_customer();

		$this->register_page_settings();

		if ($this->current_site->get_type() === 'customer_owned') {
			parent::__construct();
		}
	}

	/**
	 * Loads the current site and membership.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function page_loaded(): void {

		$this->current_site = wu_get_current_site();

		$this->current_membership = $this->current_site->get_membership();

		$this->current_customer = wp_get_current_user();

		$this->return_to_url = $this->get_validated_return_to_url();

		$this->add_notices();
	}

	/**
	 * Adds notices after a membership is changed.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function add_notices() {

		$nonce = wu_request('nonce');

		$update_type = wu_request('updated');

		if (empty($update_type)) {
			return;
		}

		if ('payment_method' === $update_type) {
			$update_message = __('Your payment method was successfully updated.', 'ultimate-multisite');
		} else {
			$update_message = __('Your account was successfully updated.', 'ultimate-multisite');
		}

		$update_message = apply_filters('wu_account_update_message', $update_message, $update_type);

		WP_Ultimo()->notices->add($update_message);
	}

	/**
	 * Allow child classes to add hooks to be run once the page is loaded.
	 *
	 * @see https://codex.wordpress.org/Plugin_API/Action_Reference/load-(page)
	 * @since 1.8.2
	 * @return void
	 */
	public function hooks() {}

	/**
	 * Allow child classes to add screen options; Useful for pages that have list tables.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function screen_options() {}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		\WP_Ultimo\UI\Current_Membership_Element::get_instance()->as_metabox(get_current_screen()->id);

		\WP_Ultimo\UI\Billing_Info_Element::get_instance()->as_metabox(get_current_screen()->id, 'side');

		\WP_Ultimo\UI\Invoices_Element::get_instance()->as_metabox(get_current_screen()->id, 'side');

		\WP_Ultimo\UI\Site_Actions_Element::get_instance()->as_metabox(get_current_screen()->id, 'side');

		\WP_Ultimo\UI\Account_Summary_Element::get_instance()->as_metabox(get_current_screen()->id);

		\WP_Ultimo\UI\Limits_Element::get_instance()->as_metabox(get_current_screen()->id);

		\WP_Ultimo\UI\Domain_Mapping_Element::get_instance()->as_metabox(get_current_screen()->id, 'side');

		\WP_Ultimo\UI\Login_Form_Element::get_instance()->as_inline_content(get_current_screen()->id, 'wu_dash_before_metaboxes');

		\WP_Ultimo\UI\Simple_Text_Element::get_instance()->as_inline_content(get_current_screen()->id, 'wu_dash_before_metaboxes');

		\WP_Ultimo\UI\Current_Site_Element::get_instance()->as_inline_content(get_current_screen()->id, 'wu_dash_before_metaboxes', ['show_admin_link' => false]);
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Account', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Account', 'ultimate-multisite');
	}

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return __('Account', 'ultimate-multisite');
	}

	/**
	 * Every child class should implement the output method to display the contents of the page.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function output(): void {
		/*
		 * Renders the base edit page layout, with the columns and everything else =)
		 */
		wu_get_template(
			'base/dash',
			[
				'screen'            => get_current_screen(),
				'page'              => $this,
				'has_full_position' => false,
			]
		);
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
