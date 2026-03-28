<?php
/**
 * Installs default content during the setup install.
 *
 * @package WP_Ultimo
 * @subpackage Installers/Default_Content_Installer
 * @since 2.0.0
 */

namespace WP_Ultimo\Installers;

use WP_Ultimo\Logger;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Installs default content during the setup install.
 *
 * @since 2.0.0
 */
class Default_Content_Installer extends Base_Installer {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Loads dependencies for when Ultimate Multisite is not yet loaded.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		require_once wu_path('inc/functions/email.php');

		require_once wu_path('inc/functions/tax.php');

		require_once wu_path('inc/functions/site.php');

		require_once wu_path('inc/functions/customer.php');

		require_once wu_path('inc/functions/product.php');

		require_once wu_path('inc/functions/checkout-form.php');
	}

	/**
	 * Checks if we already created a template site.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	protected function done_creating_template_site() {

		if (! is_multisite()) {
			return false;
		}
		$d = wu_get_site_domain_and_path('template');

		return domain_exists($d->domain, $d->path, get_current_network_id());
	}

	/**
	 * Checks if we already created the base products.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	protected function done_creating_products() {
		/*
		 * Check for tables before
		 */
		$has_tables_installed = \WP_Ultimo\Loaders\Table_Loader::get_instance()->is_installed();

		if ( ! $has_tables_installed) {
			return false;
		}

		return ! empty(wu_get_plans());
	}

	/**
	 * Checks if we already created the base checkout form.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	protected function done_creating_checkout_forms() {
		/*
		 * Check for tables before
		 */
		$has_tables_installed = \WP_Ultimo\Loaders\Table_Loader::get_instance()->is_installed();

		if ( ! $has_tables_installed) {
			return false;
		}

		return ! empty(wu_get_checkout_forms());
	}

	/**
	 * Checks if we already created the system emails and the template email.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	protected function done_creating_emails() {
		/*
		 * Check for tables before
		 */
		$has_tables_installed = \WP_Ultimo\Loaders\Table_Loader::get_instance()->is_installed();

		if ( ! $has_tables_installed) {
			return false;
		}

		return ! empty(wu_get_all_system_emails());
	}

	/**
	 * Checks if we already created the custom login page.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	protected function done_creating_login_page() {

		$page_id = wu_get_setting('default_login_page', 0);

		if ( ! $page_id) {
			return false;
		}

		$page = get_post($page_id);

		return ! empty($page);
	}

	/**
	 * Returns the list of migration steps.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_steps() {

		$steps = [];

		$steps['create_template_site'] = [
			'done'        => $this->done_creating_template_site(),
			'title'       => __('Create Example Template Site', 'ultimate-multisite'),
			'description' => __('This will create a template site on your network that you can use as a starting point.', 'ultimate-multisite'),
			'pending'     => __('Pending', 'ultimate-multisite'),
			'installing'  => __('Creating Template Site...', 'ultimate-multisite'),
			'success'     => __('Success!', 'ultimate-multisite'),
			'help'        => wu_get_documentation_url('installation-errors'),
			'checked'     => true,
		];

		$steps['create_products'] = [
			'done'        => $this->done_creating_products(),
			'title'       => __('Create Example Products', 'ultimate-multisite'),
			'description' => __('This action will create example products (plans, packages, and services), so you have an starting point.', 'ultimate-multisite'),
			'pending'     => __('Pending', 'ultimate-multisite'),
			'installing'  => __('Creating Products...', 'ultimate-multisite'),
			'success'     => __('Success!', 'ultimate-multisite'),
			'help'        => wu_get_documentation_url('installation-errors'),
			'checked'     => true,
		];

		$steps['create_checkout'] = [
			'done'        => $this->done_creating_checkout_forms(),
			'title'       => __('Create a Checkout Form', 'ultimate-multisite'),
			'description' => __('This action will create a single-step checkout form that your customers will use to place purchases, as well as the page that goes with it.', 'ultimate-multisite'),
			'pending'     => __('Pending', 'ultimate-multisite'),
			'installing'  => __('Creating Checkout Form and Registration Page...', 'ultimate-multisite'),
			'success'     => __('Success!', 'ultimate-multisite'),
			'help'        => wu_get_documentation_url('installation-errors'),
			'checked'     => true,
		];

		$steps['create_emails'] = [
			'done'        => $this->done_creating_emails(),
			'title'       => __('Create the System Emails', 'ultimate-multisite'),
			'description' => __('This action will create all emails sent by Ultimate Multisite.', 'ultimate-multisite'),
			'pending'     => __('Pending', 'ultimate-multisite'),
			'installing'  => __('Creating System Emails...', 'ultimate-multisite'),
			'success'     => __('Success!', 'ultimate-multisite'),
			'help'        => wu_get_documentation_url('installation-errors'),
			'checked'     => true,
		];

		$steps['create_login_page'] = [
			'done'        => $this->done_creating_login_page(),
			'title'       => __('Create Custom Login Page', 'ultimate-multisite'),
			'description' => __('This action will create a custom login page and replace the default one.', 'ultimate-multisite'),
			'pending'     => __('Pending', 'ultimate-multisite'),
			'installing'  => __('Creating Custom Login Page...', 'ultimate-multisite'),
			'success'     => __('Success!', 'ultimate-multisite'),
			'help'        => wu_get_documentation_url('installation-errors'),
			'checked'     => true,
		];

		return $steps;
	}

	// Default_Content_Installers start below

	/**
	 * Creates a new template site as an example.
	 *
	 * @since 2.0.0
	 * @throws \Exception When a site with the /template path already exists.
	 * @return void
	 */
	public function _install_create_template_site(): void {

		$d = wu_get_site_domain_and_path('template');

		$template_site = [
			'domain' => $d->domain,
			'path'   => $d->path,
			'title'  => __('Template Site', 'ultimate-multisite'),
			'type'   => 'site_template',
		];

		$status = wu_create_site($template_site);

		if (is_wp_error($status)) {
			throw new \Exception(esc_html($status->get_error_message()));
		}

		if ( ! $status) {
			$error_message = __('Template Site was not created. Maybe a site with the /template path already exists?', 'ultimate-multisite');

			throw new \Exception(esc_html($error_message));
		}
	}

	/**
	 * Ensures currency settings are initialized with proper defaults.
	 *
	 * Called before creating products or checkout forms so that
	 * `wu_get_setting('precision')` always returns a usable integer
	 * even when the wizard's "Your Company" step was skipped or the
	 * precision field was submitted as an empty string.
	 *
	 * @since 2.4.13
	 * @return void
	 */
	protected function ensure_currency_defaults(): void {

		$defaults = [
			'currency_symbol'    => 'USD',
			'currency_position'  => '%s %v',
			'decimal_separator'  => '.',
			'thousand_separator' => ',',
			'precision'          => 2,
		];

		foreach ($defaults as $key => $default) {
			$current = wu_get_setting($key, false);

			/*
			 * Only write the default when the stored value is missing,
			 * false, or an empty string. A stored 0 for precision is
			 * intentional (zero-decimal currencies) and must be kept.
			 */
			if (false === $current || '' === $current) {
				wu_save_setting($key, $default);
			}
		}
	}

	/**
	 * Creates a example products.
	 *
	 * @since 2.0.0
	 * @throws \Exception When the network already has products.
	 * @return void
	 */
	public function _install_create_products(): void {

		/*
		 * Guarantee that currency settings (especially `precision`) are
		 * stored with valid defaults before product validation runs.
		 * Without this, `wu_get_setting('precision')` can return '' which
		 * causes the Product model's validation rule to fail with
		 * "The Currency field is required" and later causes NaN prices.
		 */
		$this->ensure_currency_defaults();

		/*
		 * Saves Images
		 */
		$images = [
			'free'    => wu_get_asset('free.webp', 'img/wizards'),
			'premium' => wu_get_asset('premium.webp', 'img/wizards'),
			'seo'     => wu_get_asset('seo.webp', 'img/wizards'),
		];

		$images = array_map([\WP_Ultimo\Helpers\Screenshot::class, 'save_image_from_url'], $images);

		$products = [];

		/*
		 * Free Plan
		 */
		$products[] = [
			'name'           => __('Free', 'ultimate-multisite'),
			'description'    => __('This is an example of a free plan.', 'ultimate-multisite'),
			'currency'       => wu_get_setting('currency_symbol', 'USD'),
			'pricing_type'   => 'free',
			'duration'       => 1,
			'duration_unit'  => 'month',
			'slug'           => 'free',
			'type'           => 'plan',
			'setup_fee'      => 0,
			'recurring'      => false,
			'amount'         => 0,
			'billing_cycles' => false,
			'list_order'     => false,
			'active'         => 1,
		];

		/*
		 * Premium Plan
		 */
		$products[] = [
			'name'           => __('Premium', 'ultimate-multisite'),
			'description'    => __('This is an example of a paid plan.', 'ultimate-multisite'),
			'currency'       => wu_get_setting('currency_symbol', 'USD'),
			'pricing_type'   => 'paid',
			'type'           => 'plan',
			'slug'           => 'premium',
			'setup_fee'      => 99,
			'recurring'      => true,
			'duration'       => 1,
			'duration_unit'  => 'month',
			'amount'         => 29.99,
			'billing_cycles' => false,
			'list_order'     => false,
			'active'         => 1,
		];

		/*
		 * Service
		 */
		$products[] = [
			'name'           => __('SEO Consulting', 'ultimate-multisite'),
			'description'    => __('This is an example of a service that you can create and charge customers for.', 'ultimate-multisite'),
			'currency'       => wu_get_setting('currency_symbol', 'USD'),
			'pricing_type'   => 'paid',
			'type'           => 'service',
			'slug'           => 'seo',
			'setup_fee'      => 0,
			'recurring'      => true,
			'duration'       => 1,
			'duration_unit'  => 'month',
			'amount'         => 9.99,
			'billing_cycles' => false,
			'list_order'     => false,
			'active'         => 1,
		];

		foreach ($products as $product_data) {
			$status = wu_create_product($product_data);

			if (is_wp_error($status)) {
				throw new \Exception(esc_html($status->get_error_message()));
			}

			$status->set_featured_image_id($images[ $product_data['slug'] ]);

			$status->save();
		}
	}

	/**
	 * Creates a new checkout form as an example.
	 *
	 * @since 2.0.0
	 * @throws \Exception When a checkout form is already present.
	 * @return void
	 */
	public function _install_create_checkout(): void {

		/*
		 * Ensure currency defaults are set before the checkout form template
		 * renders any price formatting (e.g. the format specifier for precision).
		 */
		$this->ensure_currency_defaults();

		$checkout_form = [
			'name'     => __('Registration Form', 'ultimate-multisite'),
			'slug'     => 'main-form',
			'settings' => [],
		];

		$status = wu_create_checkout_form($checkout_form);

		if (is_wp_error($status)) {
			throw new \Exception(esc_html($status->get_error_message()));
		} else {
			$status->use_template('single-step');

			$status->save();
		}

		/*
		 * Build a Gutenberg block layout: three columns (25/50/25) with the
		 * Ultimate Multisite checkout block centred in the middle column.
		 * This gives site owners a clean starting point that is easy to
		 * customise in the block editor without touching shortcode syntax.
		 */
		$post_content = '<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"25%%"} -->
<div class="wp-block-column" style="flex-basis:25%%"></div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%%"} -->
<div class="wp-block-column" style="flex-basis:50%%"><!-- wp:wp-ultimo/checkout {"slug":"%s"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"25%%"} -->
<div class="wp-block-column" style="flex-basis:25%%"></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->';

		/*
		 * Create the page on the main site.
		 */
		$post_details = [
			'post_name'    => 'register',
			'post_title'   => __('Register', 'ultimate-multisite'),
			'post_content' => sprintf($post_content, $status->get_slug()),
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
		];

		$page_id = wp_insert_post($post_details);

		if (is_wp_error($page_id)) {
			throw new \Exception(esc_html($page_id->get_error_message()));
		}

		/*
		 * Set page as the default registration page.
		 */
		wu_save_setting('default_registration_page', $page_id);

		/*
		 * Flush rewrite rules so checkout URL patterns
		 * (e.g. /register/plan-slug) work immediately.
		 */
		flush_rewrite_rules(true);
	}

	/**
	 * Creates the template email, invoice template and system emails.
	 *
	 * @since 2.0.0
	 * @throws \Exception When the content is already present.
	 * @return void
	 */
	public function _install_create_emails(): void {

		\WP_Ultimo\Managers\Email_Manager::get_instance()->create_all_system_emails();
	}

	/**
	 * Creates custom login page.
	 *
	 * @since 2.0.0
	 * @throws \Exception When the content is already present.
	 * @return void
	 */
	public function _install_create_login_page(): void {

		$page_content = '
		<!-- wp:shortcode -->
			[wu_login_form]
		<!-- /wp:shortcode -->
	  ';

		$page_args = [
			'post_title'   => __('Login', 'ultimate-multisite'),
			'post_content' => $page_content,
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
			'post_type'    => 'page',
		];

		$page_id = wp_insert_post($page_args);

		if (is_wp_error($page_id)) {
			throw new \Exception(esc_html($page_id->get_error_message()));
		}

		/*
		 * Enable a custom login page.
		 */
		wu_save_setting('enable_custom_login_page', 1);

		/*
		 * Set page as the default login page.
		 */
		wu_save_setting('default_login_page', $page_id);
	}
}
