<?php
/**
 * Ultimate Multisite main class.
 *
 * @package WP_Ultimo
 * @since 2.0.0
 */

// Exit if accessed directly
use WP_Ultimo\Addon_Repository;

defined('ABSPATH') || exit;

/**
 * Ultimate Multisite main class
 *
 * This class instantiates our dependencies and loads the things
 * our plugin needs to run.
 *
 * @package WP_Ultimo
 * @since 2.0.0
 */
final class WP_Ultimo {

	use \WP_Ultimo\Traits\Singleton;
	use \WP_Ultimo\Traits\WP_Ultimo_Deprecated;

	/**
	 * Version of the Plugin.
	 *
	 * @since 2.1.0
	 * @var string
	 */
	const VERSION = '2.4.10';

	/**
	 * Core log handle for Ultimate Multisite.
	 *
	 * @since 2.4.4
	 * @var string
	 */
	const LOG_HANDLE = 'ultimate-multisite-core';

	const NETWORK_OPTION_SETUP_FINISHED = 'wu_setup_finished';

	/**
	 * Version of the Plugin.
	 *
	 * @deprecated use the const version instead.
	 * @var string
	 */
	public $version = self::VERSION;

	/**
	 * Tables registered by Ultimate Multisite.
	 *
	 * @var array
	 */
	public $tables = [];

	/**
	 * Checks if Ultimate Multisite was loaded or not.
	 *
	 * This is set to true when all the Ultimate Multisite requirements are met.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $loaded = false;

	/**
	 * Holds an instance of the helper functions layer.
	 *
	 * @since 2.0.0
	 * @var WP_Ultimo\Helper
	 */
	public $helper;

	/**
	 * Holds an instance of the notices functions layer.
	 *
	 * @since 2.0.0
	 * @var WP_Ultimo\Admin_Notices
	 */
	public $notices;

	/**
	 * Holds an instance of the settings layer.
	 *
	 * @since 2.0.0
	 * @var WP_Ultimo\Settings
	 */
	public $settings;

	/**
	 * Holds an instance to the scripts layer.
	 *
	 * @var \WP_Ultimo\Scripts
	 */
	public $scripts;

	/**
	 * Holds an instance to the currents layer.
	 *
	 * @var \WP_Ultimo\Current
	 */
	public $currents;

	private Addon_Repository $addon_repository;

	/**
	 * Loads the necessary components into the main class
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {
		/*
		 * Core Helper Functions
		 */
		require_once __DIR__ . '/functions/helper.php';

		/*
		 * Loads the WP_Ultimo\Helper class.
		 * @deprecated
		 */
		$this->helper = WP_Ultimo\Helper::get_instance();

		/*
		 * Deprecated Classes, functions and more.
		 */
		require_once wu_path('inc/deprecated/deprecated.php');

		/*
		 * The only core components we need to load
		 * before every other public api are the options
		 * and settings.
		 */
		require_once wu_path('inc/functions/fs.php');
		require_once wu_path('inc/functions/sort.php');
		require_once wu_path('inc/functions/settings.php');

		/*
		 * Loads files containing public functions.
		 */
		$this->load_public_apis();

		/*
		 * Setup Wizard
		 */
		new WP_Ultimo\Admin_Pages\Setup_Wizard_Admin_Page();

		/*
		 * Loads the Ultimate Multisite settings helper class.
		 */
		$this->settings = WP_Ultimo\Settings::get_instance();

		// These must be loaded here so the settings are in the setup wizard.
		WP_Ultimo\Newsletter::get_instance();
		\WP_Ultimo\Credits::get_instance();

		/*
		 * Check if the Ultimate Multisite requirements are present.
		 *
		 * Everything we need to run our setup install needs top be loaded before this
		 * and have no dependencies outside of the classes loaded so far.
		 */
		if (WP_Ultimo\Requirements::met() === false || WP_Ultimo\Requirements::run_setup() === false) {
			return;
		}

		$this->loaded = true;

		/*
		 * Loads the current site.
		 */
		$this->currents = WP_Ultimo\Current::get_instance();

		/*
		 * Loads the Ultimate Multisite admin notices helper class.
		 */
		$this->notices = WP_Ultimo\Admin_Notices::get_instance();

		/*
		 * Loads the Ultimate Multisite scripts handler
		 */
		$this->scripts = WP_Ultimo\Scripts::get_instance();

		/*
		 * Loads tables
		 */
		$this->setup_tables();

		/*
		 * Loads extra components
		 */
		$this->load_extra_components();

		/*
		 * Loads managers
		 */
		$this->load_managers();

		/**
		 * Triggers when all the dependencies were loaded
		 *
		 * Allows plugin developers to add new functionality. For example, support to new
		 * Hosting providers, etc.
		 *
		 * @since 2.0.0
		 */
		do_action('wp_ultimo_load');

		add_action('init', [$this, 'after_init']);

		add_filter('user_has_cap', [$this, 'grant_customer_capabilities'], 10, 4);
	}

	/**
	 * Loads admin pages
	 *
	 * @return void
	 */
	public function after_init() {
		/**
		 * Loads admin pages
		 *
		 * @todo: move this to a manager in the future?
		 */
		$this->load_admin_pages();

		$this->get_addon_repository()->init();

		/*
		 * Checks Sunrise versions
		 */
		WP_Ultimo\Sunrise::manage_sunrise_updates();
	}

	/**
	 * Returns true if all the requirements are met.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_loaded() {

		return $this->loaded;
	}

	/**
	 * Loads the table objects for our custom tables.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_tables(): void {

		$this->tables = \WP_Ultimo\Loaders\Table_Loader::get_instance();
	}

	/**
	 * Loads public apis that should be on the global scope
	 *
	 * This method is responsible for loading and exposing public apis that
	 * plugin developers will use when creating extensions for Ultimate Multisite.
	 * Things like render functions, helper methods, etc.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function load_public_apis(): void {

		/**
		 * Primitive Helpers
		 *
		 * Loads helper functions to deal with
		 * PHP and WordPress primitives, such as arrays,
		 * string, and numbers.
		 *
		 * Markup helpers - functions that help
		 * in generating HTML markup that we can
		 * print on screen is loaded laster.
		 *
		 * @see wu_to_float()
		 * @see wu_replace_dashes()
		 * @see wu_get_initials()
		 */
		require_once wu_path('inc/functions/array-helpers.php');
		require_once wu_path('inc/functions/string-helpers.php');
		require_once wu_path('inc/functions/number-helpers.php');

		/**
		 * General Helpers
		 *
		 * Loads general helpers that take care of a number
		 * of different tasks, from interacting with the license,
		 * to enabling context switching in sub-sites.
		 *
		 * @see wu_switch_blog_and_run()
		 */
		require_once wu_path('inc/functions/sunrise.php');
		require_once wu_path('inc/functions/legacy.php');
		require_once wu_path('inc/functions/site-context.php');
		require_once wu_path('inc/functions/sort.php');
		require_once wu_path('inc/functions/debug.php');
		require_once wu_path('inc/functions/reflection.php');
		require_once wu_path('inc/functions/scheduler.php');
		require_once wu_path('inc/functions/session.php');
		require_once wu_path('inc/functions/documentation.php');

		/**
		 * I/O and HTTP Helpers
		 *
		 * Loads helper functions that allows for interaction
		 * with PHP input, request and response headers, etc.
		 *
		 * @see wu_get_input()
		 * @see wu_no_cache()
		 * @see wu_x_header()
		 */
		require_once wu_path('inc/functions/http.php');
		require_once wu_path('inc/functions/rest.php');

		/**
		 * Localization APIs.
		 *
		 * Loads functions that help us localize content,
		 * prices, dates, and language.
		 *
		 * @see wu_validate_date()
		 * @see wu_get_countries()
		 */
		require_once wu_path('inc/functions/date.php');
		require_once wu_path('inc/functions/currency.php');
		require_once wu_path('inc/functions/countries.php');
		require_once wu_path('inc/functions/geolocation.php');
		require_once wu_path('inc/functions/translation.php');

		/**
		 * Model public APIs.
		 */
		require_once wu_path('inc/functions/mock.php');
		require_once wu_path('inc/functions/model.php');
		require_once wu_path('inc/functions/broadcast.php');
		require_once wu_path('inc/functions/email.php');
		require_once wu_path('inc/functions/checkout-form.php');
		require_once wu_path('inc/functions/customer.php');
		require_once wu_path('inc/functions/discount-code.php');
		require_once wu_path('inc/functions/domain.php');
		require_once wu_path('inc/functions/event.php');
		require_once wu_path('inc/functions/membership.php');
		require_once wu_path('inc/functions/payment.php');
		require_once wu_path('inc/functions/product.php');
		require_once wu_path('inc/functions/site.php');
		require_once wu_path('inc/functions/user.php');
		require_once wu_path('inc/functions/webhook.php');

		/**
		 * URL and Asset Helpers
		 *
		 * Functions to easily return the url to plugin assets
		 * and generate urls for the plugin UI in general.
		 *
		 * @see wu_get_current_url()
		 * @see wu_get_asset()
		 */
		require_once wu_path('inc/functions/url.php');
		require_once wu_path('inc/functions/assets.php');

		/**
		 * Checkout and Registration.
		 *
		 * Loads functions that interact with the checkout
		 * and the registration elements of Ultimate Multisite.
		 *
		 * @see wu_is_registration_page()
		 */
		require_once wu_path('inc/functions/pages.php');
		require_once wu_path('inc/functions/checkout.php');
		require_once wu_path('inc/functions/gateway.php');
		require_once wu_path('inc/functions/financial.php');
		require_once wu_path('inc/functions/invoice.php');
		require_once wu_path('inc/functions/tax.php');

		/**
		 * Access Control.
		 *
		 * Functions related to limitation checking,
		 * membership validation, and more. Here are the
		 * functions that you might want to use if you are
		 * planning to lock portions of your app based on
		 * membership status and products.
		 *
		 * @see wu_is_membership_active()
		 */
		require_once wu_path('inc/functions/limitations.php');

		/**
		 * Content Helpers.
		 *
		 * Functions that deal with content output, view/template
		 * loading and more.
		 *
		 * @see wu_get_template()
		 */
		require_once wu_path('inc/functions/template.php');
		require_once wu_path('inc/functions/env.php');
		require_once wu_path('inc/functions/form.php');
		require_once wu_path('inc/functions/markup-helpers.php');
		require_once wu_path('inc/functions/element.php');

		/**
		 * Other Tools.
		 *
		 * Other tools that are used less-often, but are still important.
		 *
		 * @todo maybe only load when necessary?
		 */
		require_once wu_path('inc/functions/generator.php');
		require_once wu_path('inc/functions/color.php');
		require_once wu_path('inc/functions/danger.php');

		/*
		 * Admin helper functions
		 */
		if (is_admin()) {
			require_once wu_path('inc/functions/admin.php');

			/*
			 * Configuration Checker for multisite setup issues
			 */
			\WP_Ultimo\Admin\Configuration_Checker::get_instance();
		}
	}

	/**
	 * Load extra the Ultimate Multisite elements
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function load_extra_components(): void {
		/*
		 * SSO Functionality
		 */
		WP_Ultimo\SSO\SSO::get_instance();

		WP_Ultimo\SSO\Magic_Link::get_instance();

		/*
		 * Loads the debugger tools
		 */
		WP_Ultimo\Debug\Debug::get_instance();

		/*
		 * Loads the Jumper UI
		 */
		WP_Ultimo\UI\Jumper::get_instance();

		/*
		 * Loads the Template Previewer
		 */
		WP_Ultimo\UI\Template_Previewer::get_instance();

		/*
		 * Loads the Toolbox UI
		 */
		WP_Ultimo\UI\Toolbox::get_instance();

		/*
		 * Loads the Tours
		 */
		WP_Ultimo\UI\Tours::get_instance();

		/*
		 * Loads the Maintenance Mode
		 */
		WP_Ultimo\Maintenance_Mode::get_instance();

		/*
		 * Support for Page Builder
		 * @todo: move to add-on
		 */
		\WP_Ultimo\Builders\Block_Editor\Block_Editor_Widget_Manager::get_instance();

		/*
		 * Loads the Checkout Block
		 * @todo remove those
		 */
		WP_Ultimo\UI\Thank_You_Element::get_instance();
		WP_Ultimo\UI\Checkout_Element::get_instance();
		WP_Ultimo\UI\Login_Form_Element::get_instance();
		WP_Ultimo\UI\Simple_Text_Element::get_instance();

		/*
		 * Customer Blocks
		 */
		\WP_Ultimo\UI\My_Sites_Element::get_instance();
		\WP_Ultimo\UI\Current_Site_Element::get_instance();
		\WP_Ultimo\UI\Current_Membership_Element::get_instance();
		\WP_Ultimo\UI\Billing_Info_Element::get_instance();
		\WP_Ultimo\UI\Invoices_Element::get_instance();
		\WP_Ultimo\UI\Site_Actions_Element::get_instance();

		\WP_Ultimo\UI\Account_Summary_Element::get_instance();
		\WP_Ultimo\UI\Limits_Element::get_instance();
		\WP_Ultimo\UI\Domain_Mapping_Element::get_instance();
		\WP_Ultimo\UI\Site_Maintenance_Element::get_instance();
		\WP_Ultimo\UI\Template_Switching_Element::get_instance();
		\WP_Ultimo\UI\Magic_Link_Url_Element::get_instance();

		/*
		 * Loads our Light Ajax implementation
		 */
		\WP_Ultimo\Light_Ajax::get_instance();

		/*
		 * Loads the Tax functionality
		 */
		\WP_Ultimo\Tax\Tax::get_instance();

		/*
		 * Loads the template placeholders
		 */
		\WP_Ultimo\Site_Templates\Template_Placeholders::get_instance();

		/*
		 * Loads our general Ajax endpoints.
		 */
		\WP_Ultimo\Ajax::get_instance();

		/*
		 * Loads API auth code.
		 */
		\WP_Ultimo\API::get_instance();

		/*
		 * Loads API registration endpoint.
		 */
		\WP_Ultimo\API\Register_Endpoint::get_instance();

		/*
		 * Loads API settings endpoint.
		 */
		\WP_Ultimo\API\Settings_Endpoint::get_instance();

		/*
		 * Loads Documentation
		 */
		\WP_Ultimo\Documentation::get_instance();

		/*
		 * Loads our Limitations implementation
		 */
		\WP_Ultimo\Limits\Post_Type_Limits::get_instance();

		/*
		 * Loads our user role limitations.
		 */
		\WP_Ultimo\Limits\Customer_User_Role_Limits::get_instance();

		/*
		 * Loads the disk space limitations
		 */
		\WP_Ultimo\Limits\Disk_Space_Limits::get_instance();

		/*
		 * Loads the site templates limitation modules
		 */
		\WP_Ultimo\Limits\Site_Template_Limits::get_instance();

		/*
		 * Loads Checkout
		 */
		\WP_Ultimo\Checkout\Checkout::get_instance();

		\WP_Ultimo\Checkout\Checkout_Pages::get_instance();

		add_action(
			'init',
			function () {
				\WP_Ultimo\Checkout\Legacy_Checkout::get_instance();
			}
		);

		/*
		 * Network Plugins/Themes usage columns
		 */
		\WP_Ultimo\Admin\Network_Usage_Columns::get_instance();

		/*
		 * Loads User Switching
		 */
		\WP_Ultimo\User_Switching::get_instance();

		/*
		 * Loads Legacy Shortcodes
		 */
		\WP_Ultimo\Compat\Legacy_Shortcodes::get_instance();

		/*
		 * Gutenberg Compatibility
		 */
		\WP_Ultimo\Compat\Gutenberg_Support::get_instance();

		/*
		 * Elementor compatibility Layer
		 */
		\WP_Ultimo\Compat\Elementor_Compat::get_instance();

		/*
		 * General compatibility fixes.
		 */
		\WP_Ultimo\Compat\General_Compat::get_instance();

		\WP_Ultimo\Compat\Login_WP_Compat::get_instance();

		\WP_Ultimo\Compat\Honeypot_Compat::get_instance();

		/*
		 * WooCommerce Subscriptions compatibility
		 */
		\WP_Ultimo\Compat\WooCommerce_Subscriptions_Compat::get_instance();

		/*
		 * Loads Basic White-labeling
		 */
		\WP_Ultimo\Whitelabel::get_instance();

		/*
		 * Adds support to multiple accounts.
		 *
		 * This used to be an add-on on Ultimate Multisite 1.X
		 * Now it is native, but needs to be activated on Ultimate Multisite settings.
		 */
		\WP_Ultimo\Compat\Multiple_Accounts_Compat::get_instance();
		\WP_Ultimo\Compat\Edit_Users_Compat::get_instance();
		\WP_Ultimo\Compat\Auto_Delete_Users_Compat::get_instance();

		/*
		 * Network Admin Widgets
		 */
		\WP_Ultimo\Dashboard_Widgets::get_instance();

		/*
		 *  Admin Themes Compatibility for Ultimate Multisite
		 */
		\WP_Ultimo\Admin_Themes_Compatibility::get_instance();

		add_filter(
			'action_scheduler_lock_class',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			fn ($class_name) => \WP_Ultimo\Compat\ActionScheduler_OptionLock_UM::class
		);

		/*
		 * Cron Schedules
		 */
		\WP_Ultimo\Cron::get_instance();

		/*
		 * Usage Tracker (opt-in telemetry)
		 */
		\WP_Ultimo\Tracker::get_instance();

		\WP_Ultimo\MCP_Adapter::get_instance();
	}

	/**
	 * Load the Ultimate Multisite Admin Pages.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function load_admin_pages(): void {
		/*
		 * These classes register hooks that fire on both frontend and admin
		 * (admin bar menus, nav menu filters), so they must always be loaded.
		 */
		new WP_Ultimo\Admin_Pages\Top_Admin_Nav_Menu();

		\WP_Ultimo\SSO\Admin_Bar_Magic_Links::get_instance();

		\WP_Ultimo\SSO\Nav_Menu_Subsite_Links::get_instance();

		/*
		 * My_Sites registers an admin_bar_menu hook for customer-owned sites,
		 * which fires on frontend for logged-in users.
		 */
		new WP_Ultimo\Admin_Pages\Customer_Panel\My_Sites_Admin_Page();

		/*
		 * The remaining admin pages only register admin menu items,
		 * admin-only forms, and wp_ajax_ handlers. They are not needed
		 * on frontend requests.
		 */
		if (is_admin() || wp_doing_ajax()) {
			$this->load_admin_only_pages();
		}

		do_action('wp_ultimo_admin_pages');
	}

	/**
	 * Loads admin pages that are only needed on admin or AJAX requests.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	protected function load_admin_only_pages(): void {

		new WP_Ultimo\Admin_Pages\Migration_Alert_Admin_Page();

		new WP_Ultimo\Admin_Pages\Dashboard_Admin_Page();

		new WP_Ultimo\Admin_Pages\Checkout_Form_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Checkout_Form_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Product_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Product_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Membership_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Membership_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Payment_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Payment_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Customer_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Customer_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Site_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Site_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Domain_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Domain_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Discount_Code_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Discount_Code_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Broadcast_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Broadcast_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Email_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Email_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Email_Template_Customize_Admin_Page();

		new WP_Ultimo\Admin_Pages\Settings_Admin_Page();

		new WP_Ultimo\Admin_Pages\Invoice_Template_Customize_Admin_Page();

		new WP_Ultimo\Admin_Pages\Template_Previewer_Customize_Admin_Page();

		new WP_Ultimo\Admin_Pages\Hosting_Integration_Wizard_Admin_Page();

		new WP_Ultimo\Admin_Pages\Event_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Event_View_Admin_Page();

		new WP_Ultimo\Admin_Pages\Webhook_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\Webhook_Edit_Admin_Page();

		new WP_Ultimo\Admin_Pages\Jobs_List_Admin_Page();

		new WP_Ultimo\Admin_Pages\System_Info_Admin_Page();

		new WP_Ultimo\Admin_Pages\Shortcodes_Admin_Page();

		new WP_Ultimo\Admin_Pages\View_Logs_Admin_Page();

		new WP_Ultimo\Admin_Pages\Customer_Panel\Account_Admin_Page();
		new WP_Ultimo\Admin_Pages\Customer_Panel\Add_New_Site_Admin_Page();
		new WP_Ultimo\Admin_Pages\Customer_Panel\Checkout_Admin_Page();
		new WP_Ultimo\Admin_Pages\Customer_Panel\Template_Switching_Admin_Page();

		new WP_Ultimo\Tax\Dashboard_Taxes_Tab();

		new WP_Ultimo\Admin_Pages\Addons_Admin_Page();
	}

	/**
	 * Load extra the Ultimate Multisite managers.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function load_managers(): void {
		/*
		 * Loads the Integration Registry.
		 *
		 * This must be loaded before the Domain Manager so that
		 * its plugins_loaded hooks fire at the correct priority.
		 *
		 * @since 2.5.0
		 */
		WP_Ultimo\Integrations\Integration_Registry::get_instance()->init();

		/*
		 * Loads the Event manager.
		 */
		WP_Ultimo\Managers\Event_Manager::get_instance();

		/*
		 * Loads the Domain Mapping manager.
		 */
		WP_Ultimo\Managers\Domain_Manager::get_instance();

		/*
		 * Loads the Product manager.
		 */
		WP_Ultimo\Managers\Product_Manager::get_instance();

		/*
		 * Loads the Discount Code manager.
		 */
		WP_Ultimo\Managers\Discount_Code_Manager::get_instance();

		/*
		 * Loads the Membership manager.
		 */
		WP_Ultimo\Managers\Membership_Manager::get_instance();

		/*
		 * Loads the Payment manager.
		 */
		WP_Ultimo\Managers\Payment_Manager::get_instance();

		/*
		 * Loads the Gateway manager.
		 */
		WP_Ultimo\Managers\Gateway_Manager::get_instance();

		/*
		 * Loads the Customer manager.
		 */
		WP_Ultimo\Managers\Customer_Manager::get_instance();

		/*
		 * Loads the Site manager.
		 */
		WP_Ultimo\Managers\Site_Manager::get_instance();

		/*
		 * Loads the Checkout Form manager.
		 */
		WP_Ultimo\Managers\Checkout_Form_Manager::get_instance();

		/*
		 * Loads the field templates manager.
		 */
		WP_Ultimo\Managers\Field_Templates_Manager::get_instance();

		/*
		 * Loads the Webhook manager.
		 */
		WP_Ultimo\Managers\Webhook_Manager::get_instance();

		/*
		 * Loads the Broadcasts manager.
		 */
		WP_Ultimo\Managers\Email_Manager::get_instance();

		/*
		 * Loads the Broadcasts manager.
		 */
		WP_Ultimo\Managers\Broadcast_Manager::get_instance();

		/*
		 * Loads the Limitation manager.
		 */
		WP_Ultimo\Managers\Limitation_Manager::get_instance();

		/*
		 * Loads the Visits Manager.
		 */
		WP_Ultimo\Managers\Visits_Manager::get_instance();

		/*
		 * Loads the Job Queue manager.
		 */
		WP_Ultimo\Managers\Job_Manager::get_instance();

		/*
		 * Loads the Block manager.
		 */
		WP_Ultimo\Managers\Block_Manager::get_instance();

		/*
		 * Loads the Notification manager.
		 */
		WP_Ultimo\Managers\Notification_Manager::get_instance();

		/*
		 * Loads the Notes manager.
		 */
		WP_Ultimo\Managers\Notes_Manager::get_instance();

		/*
		 * Loads the Cache manager.
		 */
		WP_Ultimo\Managers\Cache_Manager::get_instance();
		WP_Ultimo\Orphaned_Tables_Manager::get_instance();
		WP_Ultimo\Orphaned_Users_Manager::get_instance();

		/*
		 * Loads the Rating Notice manager.
		 */
		WP_Ultimo\Managers\Rating_Notice_Manager::get_instance();

		/**
		 * Loads views overrides
		 */
		WP_Ultimo\Views::get_instance();
	}

	/**
	 * Gets the addon repository instance.
	 *
	 * Returns a singleton instance of the Addon_Repository class that manages
	 * addon installations and updates for WP Ultimo.
	 *
	 * @since 2.0.0
	 * @return Addon_Repository The addon repository instance.
	 */
	public function get_addon_repository(): Addon_Repository {
		if (! isset($this->addon_repository)) {
			$this->addon_repository = new Addon_Repository();
		}
		return $this->addon_repository;
	}

	/**
	 * Grants wu_manage_membership capability to administrators who are customers.
	 *
	 * This filter dynamically adds the wu_manage_membership capability to users who:
	 * - Have the administrator role (or manage_options capability)
	 * - Are also Ultimate Multisite customers
	 *
	 * @since 2.4.8
	 *
	 * @param array   $allcaps All capabilities of the user.
	 * @param array   $caps    Required capabilities.
	 * @param array   $args    Argument array.
	 * @param WP_User $user    The user object.
	 * @return array Modified capabilities.
	 */
	public function grant_customer_capabilities($allcaps, $caps, $args, $user) {

		// Only check when wu_manage_membership capability is being checked
		if (! in_array('wu_manage_membership', $caps, true)) {
			return $allcaps;
		}

		// Check if user is an administrator and a customer
		if (isset($allcaps['manage_options']) && $allcaps['manage_options']) {
			$customer = wu_get_customer_by_user_id($user->ID);

			if ($customer) {
				$allcaps['wu_manage_membership'] = true;
			}
		}

		return $allcaps;
	}
}
