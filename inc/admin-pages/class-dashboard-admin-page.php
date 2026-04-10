<?php
/**
 * Ultimate Multisite Dashboard Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Dashboard_Statistics;

/**
 * Ultimate Multisite Dashboard Admin Page.
 */
class Dashboard_Admin_Page extends Base_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo';

	/**
	 * Menu position. This is only used for top-level menus
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $position = 10_101_010;

	/**
	 * Dashicon to be used on the menu item. This is only used on top-level menus
	 *
	 * Set to the SVG data URI constant in init() so the icon renders on all
	 * admin pages without requiring the full admin.css stylesheet.
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $menu_icon = '';

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
		'network_admin_menu' => 'wu_read_dashboard',
	];

	/**
	 * The tab being displayed.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $tab;

	/**
	 * The start date for the statistics.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $start_date;

	/**
	 * The end date for the statistics.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $end_date;

	/**
	 * Sets up the global parameters.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->menu_icon = self::MENU_ICON_SVG;

		parent::init();

		/*
		 * Get the content of the tab.
		 */
		$this->tab        = wu_request('tab', 'general');
		$this->start_date = date_i18n('Y-m-d', strtotime((string) wu_request('start_date', '-1 month')));
		$this->end_date   = date_i18n('Y-m-d', strtotime((string) wu_request('end_date', 'tomorrow')));
	}

	/**
	 * Allow child classes to add hooks to be run once the page is loaded.
	 *
	 * @see https://codex.wordpress.org/Plugin_API/Action_Reference/load-(page)
	 * @since 1.8.2
	 * @return void
	 */
	public function hooks(): void {

		add_action('wu_dash_after_full_metaboxes', [$this, 'render_filter']);

		add_action('wu_dashboard_general_widgets', [$this, 'register_general_tab_widgets'], 10, 2);
	}

	/**
	 * Renders the filter.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Admin_Pages\Base_Admin_Page $page The page object.
	 * @return void
	 */
	public function render_filter($page): void {

		if (apply_filters('wu_dashboard_display_filter', true) === false) {
			return;
		}

		if ('wp-ultimo' === $page->id) {
			$preset_options = [
				'last_7_days'  => [
					'label'      => __('Last 7 days', 'ultimate-multisite'),
					'start_date' => date_i18n('Y-m-d', strtotime('-7 days')),
					'end_date'   => date_i18n('Y-m-d'),
				],
				'last_30_days' => [
					'label'      => __('Last 30 days', 'ultimate-multisite'),
					'start_date' => date_i18n('Y-m-d', strtotime('-30 days')),
					'end_date'   => date_i18n('Y-m-d'),
				],
				'year_to_date' => [
					'label'      => __('Year to date', 'ultimate-multisite'),
					'start_date' => date_i18n('Y-m-d', strtotime('first day of january this year')),
					'end_date'   => date_i18n('Y-m-d'),
				],
			];

			$args = [
				'preset_options'  => $preset_options,
				'filters_el_id'   => 'dashboard-filters',
				'search_label'    => '',
				'has_search'      => false,
				'has_view_switch' => false,
				'table'           => $this,
				'active_tab'      => $this->tab,
				'views'           => $this->get_views(),
			];

			wu_get_template('dashboard-statistics/filter', $args);
		}
	}

	/**
	 * Returns the views for the filter menu bar.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_views() {

		$dashboard_filters = [
			'general' => [
				'field' => 'type',
				'url'   => add_query_arg('tab', 'general'),
				'label' => __('General', 'ultimate-multisite'),
				'count' => 0,
			],
		];

		return apply_filters('wu_dashboard_filter_bar', $dashboard_filters);
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		$screen = get_current_screen();

		if ( ! $screen) {
			return;
		}

		/**
		 * Allow plugin developers to add widgets to Network Dashboard Panel.
		 *
		 * @since 2.0.0
		 *
		 * @param string $tab The current tab.
		 * @param \WP_Screen $screen The screen object.
		 * @param \WP_Ultimo\Admin_Pages\Dashboard_Admin_Page $page Ultimate Multisite admin page instance.
		 */
		do_action("wu_dashboard_{$this->tab}_widgets", $this->tab, $screen, $this);

		/**
		 * Allow plugin developers to add widgets to Network Dashboard Panel.
		 *
		 * @since 2.0.0
		 *
		 * @param string $tab The current tab.
		 * @param \WP_Screen $screen The screen object.
		 * @param \WP_Ultimo\Admin_Pages\Dashboard_Admin_Page $page Ultimate Multisite admin page instance.
		 */
		do_action('wu_dashboard_widgets', $this->tab, $screen, $this);

		if (wu_request('tab', 'general') === 'general') {
			\WP_Ultimo\UI\Tours::get_instance()->create_tour(
				'wp-ultimo-dashboard',
				[
					[
						'id'    => 'your-dashboard',
						'title' => __('Our dashboard', 'ultimate-multisite'),
						'text'  => [
							__('This is the <strong>Ultimate Multisite Dashboard</strong>, where you will find most of the important information you will need regarding your business\' performance.', 'ultimate-multisite'),
						],
					],
					[
						'id'       => 'documentation',
						'title'    => __('Learning more', 'ultimate-multisite'),
						'text'     => [
							__('Most of the Ultimate Multisite admin pages will contain a link like this one at the top. These will link directly to the relevant knowledge base page on the Ultimate Multisite site.', 'ultimate-multisite'),
						],
						'attachTo' => [
							'element' => '#wp-ultimo-wrap > h1 > a:last-child',
							'on'      => 'left',
						],
					],
					[
						'id'       => 'mrr-growth',
						'title'    => __('It\'s all about growth!', 'ultimate-multisite'),
						'text'     => [
							__('This graph allows you to follow how your monthly recurring revenue is growing this year.', 'ultimate-multisite'),
						],
						'attachTo' => [
							'element' => '#wp-ultimo-mrr-growth',
							'on'      => 'bottom',
						],
					],
					[
						'id'       => 'tailor-made',
						'title'    => __('Date-range support', 'ultimate-multisite'),
						'text'     => [
							__('Checking statistics and comparing data for different periods is key in maintaining a good grasp on your business.', 'ultimate-multisite'),
							__('You can use the date-range selectors to have access to just the data you need and nothing more.', 'ultimate-multisite'),
						],
						'attachTo' => [
							'element' => '#dashboard-filters',
							'on'      => 'bottom',
						],
					],
				]
			);
		}
	}

	/**
	 * Register the widgets of the default general tab.
	 *
	 * @since 2.0.0
	 *
	 * @param string     $tab Tab slug.
	 * @param \WP_Screen $screen The screen object.
	 * @return void
	 */
	public function register_general_tab_widgets($tab, $screen): void {

		if (current_user_can('wu_read_financial')) {
			add_meta_box('wp-ultimo-mrr-growth', __('Monthly Recurring Revenue Growth', 'ultimate-multisite'), [$this, 'output_widget_mrr_growth'], $screen->id, 'full', 'high');

			add_meta_box('wp-ultimo-revenue', __('Revenue', 'ultimate-multisite'), [$this, 'output_widget_revenues'], $screen->id, 'normal', 'high');
		}

		add_meta_box('wp-ultimo-countries', __('Signups by Countries', 'ultimate-multisite'), [$this, 'output_widget_countries'], $screen->id, 'side', 'high');

		add_meta_box('wp-ultimo-signups', __('Signups by Form', 'ultimate-multisite'), [$this, 'output_widget_forms'], $screen->id, 'side', 'high');

		add_meta_box('wp-ultimo-most-visited-sites', __('Most Visited Sites', 'ultimate-multisite'), [$this, 'output_widget_most_visited_sites'], $screen->id, 'side', 'low');

		add_meta_box('wp-ultimo-new-accounts', __('New Memberships', 'ultimate-multisite'), [$this, 'output_widget_new_accounts'], $screen->id, 'normal', 'low');
	}

	/**
	 * Output the statistics filter widget
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function output_widget_mrr_growth(): void {

		wu_get_template('dashboard-statistics/widget-mrr-growth');
	}

	/**
	 * Output the statistics filter widget
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function output_widget_countries(): void {

		wu_get_template(
			'dashboard-statistics/widget-countries',
			[
				'countries' => wu_get_countries_of_customers(10, $this->start_date, $this->end_date),
				'page'      => $this,
			]
		);
	}

	/**
	 * Output the statistics filter widget
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function output_widget_forms(): void {

		wu_get_template(
			'dashboard-statistics/widget-forms',
			[
				'forms' => wu_calculate_signups_by_form($this->start_date, $this->end_date),
				'page'  => $this,
			]
		);
	}

	/**
	 * Output the statistics filter widget
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function output_widget_most_visited_sites(): void {

		$sites = [];

		$site_results = \WP_Ultimo\Objects\Visits::get_sites_by_visit_count($this->start_date, $this->end_date, 10);

		foreach ($site_results as $site_result) {
			$site = wu_get_site($site_result->site_id);

			if ( ! $site) {
				continue;
			}

			$sites[] = (object) [
				'site'  => $site,
				'count' => $site_result->count,
			];
		}

		wu_get_template(
			'dashboard-statistics/widget-most-visited-sites',
			[
				'sites' => $sites,
				'page'  => $this,
			]
		);
	}

	/**
	 * Outputs the total refunds widget content.
	 *
	 * @since 2.0.0
	 *
	 * @param string $unknown Unknown.
	 * @param array  $metabox With the metabox arguments passed when registered.
	 * @return void.
	 */
	public function output_widget_revenues($unknown = null, $metabox = null): void {

		$mrr           = wu_calculate_mrr();
		$gross_revenue = wu_calculate_revenue($this->start_date, $this->end_date);
		$refunds       = wu_calculate_refunds($this->start_date, $this->end_date);

		$base_currency = wu_get_setting('currency_symbol', 'USD');

		/**
		 * Filters a revenue amount displayed on the dashboard.
		 *
		 * Allows addons (e.g. multi-currency) to convert amounts
		 * to the base currency for consistent reporting.
		 *
		 * @since 2.3.0
		 *
		 * @param float  $amount   The revenue amount.
		 * @param string $currency The currency of the amount.
		 * @return float
		 */
		$mrr           = apply_filters('wu_dashboard_revenue_amount', $mrr, $base_currency);
		$gross_revenue = apply_filters('wu_dashboard_revenue_amount', $gross_revenue, $base_currency);
		$refunds       = apply_filters('wu_dashboard_revenue_amount', $refunds, $base_currency);

		wu_get_template(
			'dashboard-statistics/widget-revenue',
			[
				'mrr'           => $mrr,
				'gross_revenue' => $gross_revenue,
				'refunds'       => $refunds,
				'product_stats' => wu_calculate_financial_data_by_product($this->start_date, $this->end_date),
			]
		);

		/**
		 * Fires after the revenue widget is rendered on the dashboard.
		 *
		 * Allows addons to append additional information (e.g. exchange rate status).
		 *
		 * @since 2.3.0
		 */
		do_action('wu_dashboard_after_revenue_widget');
	}

	/**
	 * Outputs the total refunds widget content.
	 *
	 * @since 2.0.0
	 *
	 * @param string $unknown Unknown.
	 * @param array  $metabox With the metabox arguments passed when registered.
	 * @return void.
	 */
	public function output_widget_new_accounts($unknown = null, $metabox = []): void {

		$new_accounts = wu_get_memberships(
			[
				'fields'     => ['plan_id'],
				'date_query' => [
					'column'    => 'date_created',
					'after'     => $this->start_date . ' 00:00:00',
					'before'    => $this->end_date . ' 23:59:59',
					'inclusive' => true,
				],
			]
		);

		$products = wu_get_products(
			[
				'type'   => 'plan',
				'fields' => ['id', 'name', 'count'],
			]
		);

		$products_ids = array_column($products, 'id');

		$products = array_combine($products_ids, $products);

		$products = array_map(
			function ($item) {

				$item->count = 0;

				return $item;
			},
			$products
		);

		/**
		 * Add edge case for no plan.
		 */
		$products['none'] = (object) [
			'name'  => __('No Product', 'ultimate-multisite'),
			'count' => 0,
		];

		foreach ($new_accounts as $new_account) {
			if (isset($products[ $new_account->plan_id ])) {
				$products[ $new_account->plan_id ]->count += 1;
			} else {
				$products['none']->count += 1;
			}
		}

		wu_get_template(
			'dashboard-statistics/widget-new-accounts',
			[
				'new_accounts' => count($new_accounts),
				'products'     => $products,
			]
		);
	}

	/**
	 * Enqueue the necessary scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		$month_list = [];

		$current_year = date_i18n('Y');

		for ($i = 1; $i <= 12; $i++) {
			$month_list[] = date_i18n('M y', mktime(0, 0, 0, $i, 1, $current_year));
		}

		$statistics = new Dashboard_Statistics(
			[
				'start_date' => $this->start_date,
				'end_date'   => $this->end_date,
				'types'      => [
					'mrr_growth' => 'mrr_growth',
				],
			]
		);

		$data = $statistics->statistics_data();

		wp_register_script('wu-apex-charts', wu_get_asset('apexcharts.js', 'js/lib'), [], wu_get_version(), true);

		wp_register_script('wu-vue-apex-charts', wu_get_asset('vue-apexcharts.js', 'js/lib'), [], wu_get_version(), true);

		wp_register_script('wu-dashboard-stats', wu_get_asset('dashboard-statistics.js', 'js'), ['jquery', 'wu-functions', 'wu-ajax-list-table', 'moment', 'wu-block-ui', 'dashboard', 'wu-apex-charts', 'wu-vue-apex-charts'], wu_get_version(), true);

		wp_localize_script(
			'wu-dashboard-stats',
			'wu_dashboard_statistics_vars',
			[
				'mrr_array'  => $data['mrr_growth'],
				'start_date' => date_i18n('Y-m-d', strtotime((string) wu_request('start_date', '-1 month'))),
				'end_date'   => date_i18n('Y-m-d', strtotime((string) wu_request('end_date', 'tomorrow'))),
				'today'      => date_i18n('Y-m-d', strtotime('tomorrow')),
				'month_list' => $month_list,
				'i18n'       => [
					'new_mrr'       => __('New MRR', 'ultimate-multisite'),
					'cancellations' => __('Cancellations', 'ultimate-multisite'),
				],
			]
		);

		wp_enqueue_script('wu-dashboard-stats');

		wp_enqueue_style('wu-apex-charts', wu_get_asset('apexcharts.css', 'css'), [], wu_get_version());

		wp_enqueue_style('wu-flags');

		wp_enqueue_script_module('wu-flags-polyfill');
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Dashboard', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Ultimate Multisite', 'ultimate-multisite');
	}

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return __('Dashboard', 'ultimate-multisite');
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
				'has_full_position' => true,
			]
		);
	}

	/**
	 * Render an export CSV button.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Data array to convert to CSV.
	 * @return void
	 */
	public function render_csv_button($args): void {

		$args = wp_parse_args(
			$args,
			[
				'slug'    => 'csv',
				'headers' => [],
				'data'    => [],
				'action'  => apply_filters('wu_export_data_table_action', 'wu_generate_csv'),
			]
		);

		$slug = $args['slug'];

		$header_strings = wp_json_encode($args['headers']);

		$data_strings = wp_json_encode($args['data']);

		printf(
			'<div class="wu-bg-gray-100 wu-p-2 wu-text-right wu-border-0 wu-border-b wu-border-solid wu-border-gray-400">
			<a href="#" attr-slug-csv="%2$s" class="wu-export-button wu-no-underline wu-text-gray-800 wu-text-xs">
				<span class="dashicons-wu-download wu-mr-1"></span> %1$s
			</a>
			<input type="hidden" id="csv_headers_%2$s" value="%3$s" />
			<input type="hidden" id="csv_data_%2$s" value="%4$s" />
			<input type="hidden" id="csv_action_%2$s" value="%5$s" />
		</div>',
			esc_html__('CSV', 'ultimate-multisite'),
			esc_attr($slug),
			esc_attr($header_strings),
			esc_attr($data_strings),
			esc_attr($args['action'])
		);
	}
}
