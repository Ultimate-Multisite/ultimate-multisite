<?php
/**
 * Ultimate Multisite class to hold current objects
 *
 * @package WP_Ultimo
 * @subpackage Current
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite class to hold current objects
 *
 * @since 2.0.0
 */
class Current implements \WP_Ultimo\Interfaces\Singleton {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The current site instance.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Models\Site
	 */
	protected $site;

	/**
	 * The current customer instance.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Models\Customer
	 */
	protected $customer;

	/**
	 * The current membership instance.
	 *
	 * @since 2.0.18
	 * @var \WP_Ultimo\Models\Membership
	 */
	protected $membership;

	/**
	 * Wether or not the site was set via request.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $site_set_via_request = false;

	/**
	 * Wether or not the customer was set via request.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $customer_set_via_request = false;

	/**
	 * Wether or not the membership was set via request.
	 *
	 * @since 2.0.18
	 * @var boolean
	 */
	protected $membership_set_via_request = false;

	/**
	 * Called when the singleton is first initialized.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {
		/*
		 * Add rewrite rules
		 */
		add_action('init', [$this, 'add_rewrite_rules']);
		add_filter('query_vars', [$this, 'add_query_vars']);

		add_action('wu_after_save_settings', [$this, 'flush_rewrite_rules_on_update']);
		add_action('wu_core_update', [$this, 'flush_rewrite_rules_on_update']);

		/*
		 * Instantiate the currents.
		 */
		add_action('init', [$this, 'load_currents']);
		add_action('wp', [$this, 'load_currents']);
	}

	/**
	 * Flush rewrite rules to make sure any newly added ones get installed on update.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function flush_rewrite_rules_on_update(): void {

		flush_rewrite_rules();
	}

	/**
	 * Adds a new rewrite rule to allow for pretty links.
	 *
	 * Managing a site would be done via /account/site/{$id}, for example.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_rewrite_rules(): void {

		$site_url_param = self::param_key('site');

		add_rewrite_rule(
			"(.?.+?)/{$site_url_param}/([0-9a-zA-Z]+)/?$",
			'index.php?pagename=$matches[1]&site_hash=$matches[2]',
			'top'
		);

		if (is_subdomain_install() === false) {
			add_rewrite_rule(
				"blog/(.?.+?)/{$site_url_param}/([0-9a-zA-Z]+)/?$",
				'index.php?name=$matches[1]&site_hash=$matches[2]',
				'top'
			);
		}
	}

	/**
	 * Adds the necessary query vars to support pretty links.
	 *
	 * @since 2.0.0
	 *
	 * @param array $query_vars The WP_Query object.
	 * @return \WP_Query
	 */
	public function add_query_vars($query_vars) {

		$query_vars[] = 'site_hash';
		$query_vars[] = 'products';
		$query_vars[] = 'duration';
		$query_vars[] = 'duration_unit';
		$query_vars[] = 'template_name';
		$query_vars[] = 'wu_preselected';

		return $query_vars;
	}

	/**
	 * List of URL keys to set the current objects.
	 *
	 * @since 2.0.0
	 * @param string $type The type of object to get.
	 * @return string
	 */
	public static function param_key($type = 'site') {

		$params = [
			'site'       => apply_filters('wu_current_get_site_param', 'site'),
			'customer'   => apply_filters('wu_current_get_customer_param', 'customer'),
			'membership' => apply_filters('wu_current_get_membership_param', 'membership'),
		];

		return wu_get_isset($params, $type, $type);
	}

	/**
	 * Returns the URL to manage a site/customer on the front-end or back end.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $id The site ID.
	 * @param string $type The type. Can be either site or customer.
	 * @return string
	 */
	public static function get_manage_url($id, $type = 'site') {

		// Uses hash instead of the ID.
		$site_hash = \WP_Ultimo\Helpers\Hash::encode($id, $type);

		if ( ! is_admin()) {
			$current_url = rtrim((string) wu_get_current_url(), '/');

			$url_param = self::param_key($type);

			/*
			 * Check if the current URL already has a site parameter and remove it.
			 */
			if (str_contains($current_url, '/' . $url_param . '/')) {
				$current_url = preg_replace('/\/' . $url_param . '\/(.+)/', '/', $current_url);
			}

			$pretty_url = $current_url . '/' . $url_param . '/' . $site_hash;

			$manage_site_url = get_option('permalink_structure') ? $pretty_url : add_query_arg($url_param, $site_hash);
		} else {
			$manage_site_url = get_admin_url($id);
		}

		/**
		 * Allow developers to modify the manage site URL parameters.
		 *
		 * @since 2.0.9
		 *
		 * @param string $manage_site_url The manage site URL.
		 * @param int $id The site ID.
		 * @param string $site_hash The site hash.
		 * @return string The modified manage URL.
		 */
		return apply_filters("wu_current_{$type}_get_manage_url", $manage_site_url, $id, $site_hash);
	}

	/**
	 * Loads the current site and makes it available.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function load_currents(): void {

		// On the wp hook, only re-run if we're on the frontend
		// (query var overrides from pretty URLs only work there).
		// On admin or AJAX, the init run already set everything.
		if (did_action('init') && $this->site && (is_admin() || wp_doing_ajax())) {
			return;
		}

		$site = false;

		/**
		 * On the front-end, we need to check for url overrides.
		 */
		if ( ! is_admin()) {
			/*
			 * By default, we'll use the `site` parameter.
			 */
			$site_url_param = self::param_key('site');

			$site_hash = wu_request($site_url_param, get_query_var('site_hash'));

			$site_from_url = wu_get_site_by_hash($site_hash);

			if ($site_from_url) {
				$this->site_set_via_request = true;

				$site = $site_from_url;
			}
		} else {
			$site = wu_get_current_site();
		}

		if ($site) {
			$this->set_site($site);
		}

		$customer = wu_get_current_customer();

		/**
		 * On the front-end, we need to check for url overrides.
		 */
		if ( ! is_admin()) {
			/*
			 * By default, we'll use the `site` parameter.
			 */
			$customer_url_param = self::param_key('customer');

			$customer_from_url = wu_get_customer(wu_request($customer_url_param, 0));

			if ($customer_from_url) {
				$this->customer_set_via_request = true;

				$customer = $customer_from_url;
			}
		}

		$this->set_customer($customer);

		$membership = false;

		/*
		 * By default, we'll use the `membership` parameter.
		 */
		$membership_url_param = self::param_key('membership');

		$membership_hash = wu_request($membership_url_param, get_query_var('membership_hash'));

		if ($membership_hash) {
			$this->membership_set_via_request = true;

			$membership = wu_get_membership_by_hash($membership_hash);
		} elseif ($site) {
			$membership = $site->get_membership();
		}

		if ($customer && ! $membership) {
			$memberships = (array) $customer->get_memberships();

			$membership = wu_get_isset($memberships, 0, false);
		}

		$this->set_membership($membership);
	}

	/**
	 * Get the current site instance.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Site
	 */
	public function get_site() {

		return $this->site;
	}

	/**
	 * Set the current site instance.
	 *
	 * @since 2.0.0
	 * @param \WP_Ultimo\Models\Site $site The current site instance.
	 * @return void
	 */
	public function set_site($site): void {

		/**
		 * Allow developers to modify the default behavior and set
		 * the current site differently.
		 *
		 * @since 2.0.9
		 *
		 * @param \WP_Ultimo\Models\Site $site The current site to set.
		 * @param self The Current class instance.
		 * @return \WP_Ultimo\Models\Site
		 */
		$site = apply_filters('wu_current_set_site', $site, $this);

		$this->site = $site;
	}

	/**
	 * Get wether or not the site was set via request.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_site_set_via_request() {

		return $this->site_set_via_request;
	}

	/**
	 * Get the current customer instance.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Customer
	 */
	public function get_customer() {

		return $this->customer;
	}

	/**
	 * Set the current customer instance.
	 *
	 * @since 2.0.0
	 * @param \WP_Ultimo\Models\Customer $customer The current customer instance.
	 * @return void
	 */
	public function set_customer($customer): void {

		/**
		 * Allow developers to modify the default behavior and set
		 * the current customer differently.
		 *
		 * @since 2.0.9
		 *
		 * @param \WP_Ultimo\Models\Customer $customer The current customer to set.
		 * @param self The Current class instance.
		 * @return \WP_Ultimo\Models\Customer
		 */
		$customer = apply_filters('wu_current_set_customer', $customer, $this);

		$this->customer = $customer;
	}

	/**
	 * Get the current membership instance.
	 *
	 * @since 2.0.18
	 * @return \WP_Ultimo\Models\Membership
	 */
	public function get_membership() {

		return $this->membership;
	}

	/**
	 * Set the current membership instance.
	 *
	 * @since 2.0.18
	 * @param \WP_Ultimo\Models\Membership $membership The current membership instance.
	 * @return void
	 */
	public function set_membership($membership): void {

		/**
		 * Allow developers to modify the default behavior and set
		 * the current membership differently.
		 *
		 * @since 2.0.18
		 *
		 * @param \WP_Ultimo\Models\Membership $membership The current membership to set.
		 * @param self The Current class instance.
		 * @return \WP_Ultimo\Models\Membership
		 */
		$membership = apply_filters('wu_current_set_membership', $membership, $this);

		$this->membership = $membership;
	}

	/**
	 * Get wether or not the membership was set via request.
	 *
	 * @since 2.0.18
	 * @return boolean
	 */
	public function is_membership_set_via_request() {

		return $this->membership_set_via_request;
	}
}
