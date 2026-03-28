<?php
/**
 * Ultimate Multisite Dashboard Widgets
 *
 * Log string messages to a file with a timestamp. Useful for debugging.
 *
 * @package WP_Ultimo
 * @subpackage Logger
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Dashboard Widgets
 *
 * @since 2.0.0
 */
class Dashboard_Widgets implements \WP_Ultimo\Interfaces\Singleton {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Network Dashboard Screen Id
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $screen_id = 'dashboard-network';

	/**
	 * Undocumented variable
	 *
	 * @since 2.0.0
	 * @var array
	 */
	public $core_metaboxes = [];

	/**
	 * Runs on singleton instantiation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

		add_action('wp_network_dashboard_setup', [$this, 'register_network_widgets']);

		add_action('wp_dashboard_setup', [$this, 'register_widgets']);

		add_action('wp_ajax_wu_fetch_rss', [$this, 'process_ajax_fetch_rss']);

		add_action('wp_ajax_wu_fetch_activity', [$this, 'process_ajax_fetch_events']);

		add_action('wp_ajax_wu_generate_csv', [$this, 'handle_table_csv']);
	}

	/**
	 * Enqueues the JavaScript code that sends the dismiss call to the ajax endpoint.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {

		global $pagenow;

		if ( ! $pagenow || 'index.php' !== $pagenow) {
			return;
		}

		wp_enqueue_script('wu-vue');

		wp_enqueue_script('moment');

		/*
		 * wu-functions defines window.wu_moment, which is required by the
		 * activity-stream widget. The network admin dashboard is not a
		 * wp-ultimo page, so enqueue_default_admin_scripts() skips it —
		 * we must enqueue wu-functions explicitly here.
		 */
		wp_enqueue_script('wu-functions');
	}

	/**
	 * Register the widgets
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_network_widgets(): void {

		add_meta_box('wp-ultimo-setup', __('Ultimate Multisite - First Steps', 'ultimate-multisite'), [$this, 'output_widget_first_steps'], $this->screen_id, 'normal', 'high');

		add_meta_box('wp-ultimo-summary', __('Ultimate Multisite - Summary', 'ultimate-multisite'), [$this, 'output_widget_summary'], $this->screen_id, 'normal', 'high');

		add_meta_box('wp-ultimo-activity-stream', __('Ultimate Multisite - Activity Stream', 'ultimate-multisite'), [$this, 'output_widget_activity_stream'], $this->screen_id, 'normal', 'high');

		\WP_Ultimo\UI\Tours::get_instance()->create_tour(
			'dashboard',
			[
				[
					'id'    => 'welcome',
					'title' => __('Welcome!', 'ultimate-multisite'),
					'text'  => [
						__('Welcome to your new network dashboard!', 'ultimate-multisite'),
						__('You will notice that <strong>Ultimate Multisite</strong> adds a couple of useful widgets here so you can keep an eye on how your network is doing.', 'ultimate-multisite'),
					],
				],
				[
					'id'       => 'finish-your-setup',
					'title'    => __('Finish your setup', 'ultimate-multisite'),
					'text'     => [
						__('You still have a couple of things to do configuration-wise. Check the steps on this list and make sure you complete them all.', 'ultimate-multisite'),
					],
					'attachTo' => [
						'element' => '#wp-ultimo-setup',
						'on'      => 'left',
					],
				],
				[
					'id'       => 'wp-ultimo-menu',
					'title'    => __('Our home', 'ultimate-multisite'),
					'text'     => [
						__('You can always find Ultimate Multisite settings and other pages under our menu item, here on the Network-level dashboard. 😃', 'ultimate-multisite'),
					],
					'attachTo' => [
						'element' => '.toplevel_page_wp-ultimo',
						'on'      => 'left',
					],
				],
			]
		);
	}

	/**
	 * Adds the customer's site's dashboard widgets.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_widgets(): void {

		$screen = get_current_screen();

		if (wu_get_current_site()->get_type() !== 'customer_owned') {
			return;
		}

		/*
		 * Account Summary
		 */
		\WP_Ultimo\UI\Account_Summary_Element::get_instance()->as_metabox($screen->id, 'normal');

		/*
		 * Limits & Quotas
		 */
		\WP_Ultimo\UI\Limits_Element::get_instance()->as_metabox($screen->id, 'side');

		/*
		 * Maintenance Mode Widget
		 */
		if (wu_get_setting('maintenance_mode', false)) {
			\WP_Ultimo\UI\Site_Maintenance_Element::get_instance()->as_metabox($screen->id, 'side');
		}

		/*
		 * Domain Mapping Widget
		 */
		if (wu_get_setting('enable_domain_mapping', false) && wu_get_setting('custom_domains', false)) {
			\WP_Ultimo\UI\Domain_Mapping_Element::get_instance()->as_metabox($screen->id, 'side');
		}
	}

	/**
	 * Widget First Steps Output.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function output_widget_first_steps(): void {

		$initial_setup_done = get_network_option(null, \WP_Ultimo::NETWORK_OPTION_SETUP_FINISHED, false);

		$steps = [
			'inital-setup'        => [
				'title'             => __('Initial Setup', 'ultimate-multisite'),
				'desc'              => __('Go through the initial Setup Wizard to configure the basic settings of your network.', 'ultimate-multisite'),
				'action_label'      => __('Finish the Setup Wizard', 'ultimate-multisite'),
				'done_action_label' => __('Run Setup Wizard Again', 'ultimate-multisite'),
				'action_link'       => wu_network_admin_url('wp-ultimo-setup'),
				'done'              => ! empty($initial_setup_done),
			],
			'payment-method'      => [
				'title'             => __('Payment Method', 'ultimate-multisite'),
				'desc'              => __('You will need to configure at least one payment gateway to be able to receive money from your customers.', 'ultimate-multisite'),
				'action_label'      => __('Add a Payment Method', 'ultimate-multisite'),
				'done_action_label' => __('Edit Payment Methods', 'ultimate-multisite'),
				'action_link'       => wu_network_admin_url(
					'wp-ultimo-settings',
					[
						'tab' => 'payment-gateways',
					]
				),
				'done'              => ! empty(wu_get_active_gateways()),
			],
			'your-first-customer' => [
				'done'              => ! empty(wu_get_customers()),
				'title'             => __('Your First Customer', 'ultimate-multisite'),
				'desc'              => __('Open the link below in an incognito tab and go through your newly created signup form.', 'ultimate-multisite'),
				'action_link'       => wp_registration_url(),
				'action_label'      => __('Create a test Account', 'ultimate-multisite'),
				'done_action_label' => __('Create another Account', 'ultimate-multisite'),
			],
		];

		$done = \Arrch\Arrch::find(
			$steps,
			[
				'where' => [
					['done', true],
				],
			]
		);

		wu_get_template(
			'dashboard-widgets/first-steps',
			[
				'steps'      => $steps,
				'percentage' => round(count($done) / count($steps) * 100),
				'all_done'   => count($done) === count($steps),
			]
		);
	}

	/**
	 * Widget Activity Stream Output.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function output_widget_activity_stream(): void {

		wu_get_template('dashboard-widgets/activity-stream');
	}

	/**
	 * Widget Summary Output
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function output_widget_summary(): void {
		/*
		 * Get today's signups.
		 */
		$signups = wu_get_customers(
			[
				'count'      => true,
				'date_query' => [
					'column'    => 'date_registered',
					'after'     => 'today',
					'inclusive' => true,
				],
			]
		);

		wu_get_template(
			'dashboard-widgets/summary',
			[
				'signups'       => $signups,
				'mrr'           => wu_calculate_mrr(),
				'gross_revenue' => wu_calculate_revenue('today'),
			]
		);
	}

	/**
	 * Process Ajax Filters for rss.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function process_ajax_fetch_rss(): void {

		$atts = wp_parse_args(
			$_GET, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			[
				'url'          => 'https://community.wpultimo.com/topics/feed',
				'title'        => __('Forum Discussions', 'ultimate-multisite'),
				'items'        => 3,
				'show_summary' => 1,
				'show_author'  => 0,
				'show_date'    => 1,
			]
		);

		wp_widget_rss_output($atts);

		exit;
	}

	/**
	 * Process Ajax Filters for rss.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function process_ajax_fetch_events(): void {

		check_ajax_referer('wu_activity_stream');

		$count = wu_get_events(
			[
				'count'  => true,
				'number' => -1,
			]
		);

		$data = wu_get_events(
			[
				'offset' => (wu_request('page', 1) - 1) * 5,
				'number' => 5,
			]
		);

		wp_send_json_success(
			[
				'events' => $data,
				'count'  => $count,
			]
		);
	}

	/**
	 * Handle ajax endpoint to generate table CSV.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_table_csv(): void {

		$date_range = wu_request('date_range');
		$headers    = json_decode(stripslashes((string) wu_request('headers')));
		$data       = json_decode(stripslashes((string) wu_request('data')));

		$file_name = sprintf('wp-ultimo-%s_%s_(%s)', wu_request('slug'), $date_range, gmdate('Y-m-d', wu_get_current_time('timestamp')));

		$data = array_merge([$headers], $data);

		wu_generate_csv($file_name, $data);

		die;
	}

	/**
	 * Get the registered widgets.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function get_registered_dashboard_widgets() {

		global $wp_meta_boxes, $wp_registered_widgets;

		ob_start();

		if ( ! function_exists('wp_add_dashboard_widget')) {
			require_once ABSPATH . '/wp-admin/includes/dashboard.php';
		}

		do_action('wp_network_dashboard_setup'); // phpcs:ignore

		ob_clean(); // Prevent eventual echos.

		$dashboard_widgets = wu_get_isset($wp_meta_boxes, 'dashboard-network', []);

		$options = [
			'normal:core:dashboard_right_now'         => __('At a Glance'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'normal:core:network_dashboard_right_now' => __('Right Now'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'normal:core:dashboard_activity'          => __('Activity'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'normal:core:dashboard_primary'           => __('WordPress Events and News'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		];

		foreach ($dashboard_widgets as $position => $priorities) {
			foreach ($priorities as $priority => $widgets) {
				foreach ($widgets as $widget_key => $widget) {
					if (empty($widget) || wu_get_isset($widget, 'title') === false) {
						continue;
					}

					$key = implode(
						':',
						[
							$position,
							$priority,
							$widget_key,
						]
					);

					/**
					 * For some odd reason, in some cases, $options
					 * becomes a bool and the assignment below throws a fatal error.
					 * This checks prevents that error from happening.
					 * I don't know why $options would ever be a boolean here, though.
					 */
					if ( ! is_array($options)) {
						$options = [];
					}

					$options[ $key ] = $widget['title'];
				}
			}
		}

		return $options;
	}
}
