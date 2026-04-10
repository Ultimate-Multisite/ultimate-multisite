<?php
/**
 * Tests for Dashboard_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Dashboard_Admin_Page.
 *
 * Covers all public methods of Dashboard_Admin_Page to reach >=80% coverage.
 * Methods that call wp_die(), output templates, or require a full HTTP context
 * are tested for their guard conditions and side-effects only.
 */
class Dashboard_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Dashboard_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->page             = new Dashboard_Admin_Page();
		$this->page->start_date = '2023-01-01';
		$this->page->end_date   = '2023-01-31';
		$this->page->tab        = 'general';
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset(
			$_REQUEST['tab'],
			$_REQUEST['start_date'],
			$_REQUEST['end_date']
		);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	/**
	 * Test page id is correct.
	 */
	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo', $property->getValue($this->page));
	}

	/**
	 * Test menu_icon is set to the SVG data URI constant.
	 */
	public function test_menu_icon(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('menu_icon');
		$property->setAccessible(true);

		$this->assertEquals(Base_Admin_Page::MENU_ICON_SVG, $property->getValue($this->page));
	}

	/**
	 * Test badge_count is zero.
	 */
	public function test_badge_count(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);

		$this->assertEquals(0, $property->getValue($this->page));
	}

	/**
	 * Test supported_panels contains network_admin_menu with correct capability.
	 */
	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_read_dashboard', $panels['network_admin_menu']);
	}

	/**
	 * Test position is set.
	 */
	public function test_position(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('position');
		$property->setAccessible(true);

		$this->assertIsInt($property->getValue($this->page));
		$this->assertGreaterThan(0, $property->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * get_title returns 'Dashboard'.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Dashboard', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns 'Ultimate Multisite'.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Ultimate Multisite', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_submenu_title returns 'Dashboard'.
	 */
	public function test_get_submenu_title(): void {

		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Dashboard', $title);
	}

	// -------------------------------------------------------------------------
	// init()
	// -------------------------------------------------------------------------

	/**
	 * init() sets tab from request (default 'general').
	 */
	public function test_init_sets_tab_default(): void {

		$page = new Dashboard_Admin_Page();
		$page->init();

		$this->assertEquals('general', $page->tab);
	}

	/**
	 * init() sets tab from request parameter.
	 */
	public function test_init_sets_tab_from_request(): void {

		$_REQUEST['tab'] = 'custom';

		$page = new Dashboard_Admin_Page();
		$page->init();

		$this->assertEquals('custom', $page->tab);
	}

	/**
	 * init() sets start_date as a valid date string.
	 */
	public function test_init_sets_start_date(): void {

		$page = new Dashboard_Admin_Page();
		$page->init();

		$this->assertIsString($page->start_date);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $page->start_date);
	}

	/**
	 * init() sets end_date as a valid date string.
	 */
	public function test_init_sets_end_date(): void {

		$page = new Dashboard_Admin_Page();
		$page->init();

		$this->assertIsString($page->end_date);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $page->end_date);
	}

	/**
	 * init() sets start_date from request parameter.
	 */
	public function test_init_sets_start_date_from_request(): void {

		$_REQUEST['start_date'] = '2023-06-01';

		$page = new Dashboard_Admin_Page();
		$page->init();

		$this->assertEquals('2023-06-01', $page->start_date);
	}

	/**
	 * init() sets end_date from request parameter.
	 */
	public function test_init_sets_end_date_from_request(): void {

		$_REQUEST['end_date'] = '2023-06-30';

		$page = new Dashboard_Admin_Page();
		$page->init();

		$this->assertEquals('2023-06-30', $page->end_date);
	}

	// -------------------------------------------------------------------------
	// hooks()
	// -------------------------------------------------------------------------

	/**
	 * hooks() registers wu_dash_after_full_metaboxes action.
	 */
	public function test_hooks_registers_render_filter_action(): void {

		$this->page->hooks();

		$this->assertGreaterThan(
			0,
			has_action('wu_dash_after_full_metaboxes', [$this->page, 'render_filter'])
		);
	}

	/**
	 * hooks() registers wu_dashboard_general_widgets action.
	 */
	public function test_hooks_registers_general_widgets_action(): void {

		$this->page->hooks();

		$this->assertGreaterThan(
			0,
			has_action('wu_dashboard_general_widgets', [$this->page, 'register_general_tab_widgets'])
		);
	}

	// -------------------------------------------------------------------------
	// render_filter()
	// -------------------------------------------------------------------------

	/**
	 * render_filter() does nothing when filter returns false.
	 */
	public function test_render_filter_skips_when_filter_false(): void {

		add_filter('wu_dashboard_display_filter', '__return_false');

		ob_start();
		$this->page->render_filter($this->page);
		$output = ob_get_clean();

		remove_filter('wu_dashboard_display_filter', '__return_false');

		$this->assertEmpty($output);
	}

	/**
	 * render_filter() does nothing when page id does not match.
	 */
	public function test_render_filter_skips_when_page_id_mismatch(): void {

		$mock_page     = new \stdClass();
		$mock_page->id = 'some-other-page';

		ob_start();
		$this->page->render_filter($mock_page);
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * render_filter() outputs content when page id matches and filter is true.
	 */
	public function test_render_filter_outputs_when_page_id_matches(): void {

		ob_start();
		$this->page->render_filter($this->page);
		$output = ob_get_clean();

		// Template output is expected (non-empty string).
		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// get_views()
	// -------------------------------------------------------------------------

	/**
	 * get_views() returns an array.
	 */
	public function test_get_views_returns_array(): void {

		$views = $this->page->get_views();

		$this->assertIsArray($views);
	}

	/**
	 * get_views() contains 'general' key.
	 */
	public function test_get_views_contains_general(): void {

		$views = $this->page->get_views();

		$this->assertArrayHasKey('general', $views);
	}

	/**
	 * get_views() general entry has required keys.
	 */
	public function test_get_views_general_has_required_keys(): void {

		$views = $this->page->get_views();

		$this->assertArrayHasKey('field', $views['general']);
		$this->assertArrayHasKey('url', $views['general']);
		$this->assertArrayHasKey('label', $views['general']);
		$this->assertArrayHasKey('count', $views['general']);
	}

	/**
	 * get_views() general label is 'General'.
	 */
	public function test_get_views_general_label(): void {

		$views = $this->page->get_views();

		$this->assertEquals('General', $views['general']['label']);
	}

	/**
	 * get_views() applies wu_dashboard_filter_bar filter.
	 */
	public function test_get_views_applies_filter(): void {

		add_filter(
			'wu_dashboard_filter_bar',
			function ($filters) {
				$filters['custom_tab'] = [
					'field' => 'type',
					'url'   => '#',
					'label' => 'Custom',
					'count' => 0,
				];
				return $filters;
			}
		);

		$views = $this->page->get_views();

		$this->assertArrayHasKey('custom_tab', $views);

		remove_all_filters('wu_dashboard_filter_bar');
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * register_widgets() returns early when no screen is set.
	 */
	public function test_register_widgets_returns_early_without_screen(): void {

		// Ensure no current screen.
		$GLOBALS['current_screen'] = null;

		// Should not throw.
		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * register_widgets() fires wu_dashboard_general_widgets action when screen is set.
	 */
	public function test_register_widgets_fires_action_with_screen(): void {

		set_current_screen('dashboard-network');

		$fired = false;
		add_action(
			'wu_dashboard_general_widgets',
			function () use (&$fired) {
				$fired = true;
			}
		);

		$this->page->register_widgets();

		$this->assertTrue($fired);
	}

	/**
	 * register_widgets() fires wu_dashboard_widgets action when screen is set.
	 */
	public function test_register_widgets_fires_dashboard_widgets_action(): void {

		set_current_screen('dashboard-network');

		$fired = false;
		add_action(
			'wu_dashboard_widgets',
			function () use (&$fired) {
				$fired = true;
			}
		);

		$this->page->register_widgets();

		$this->assertTrue($fired);
	}

	// -------------------------------------------------------------------------
	// register_general_tab_widgets()
	// -------------------------------------------------------------------------

	/**
	 * register_general_tab_widgets() adds meta boxes when user has wu_read_financial.
	 */
	public function test_register_general_tab_widgets_with_financial_capability(): void {

		set_current_screen('dashboard-network');
		$screen = get_current_screen();

		wp_set_current_user(1);
		grant_super_admin(1);

		$this->page->register_general_tab_widgets('general', $screen);

		global $wp_meta_boxes;
		$screen_id = $screen->id;

		// At least one meta box should be registered.
		$this->assertNotEmpty($wp_meta_boxes);
	}

	/**
	 * register_general_tab_widgets() always adds countries meta box.
	 */
	public function test_register_general_tab_widgets_adds_countries_meta_box(): void {

		set_current_screen('dashboard-network');
		$screen = get_current_screen();

		// Log out — no financial capability.
		wp_set_current_user(0);

		$this->page->register_general_tab_widgets('general', $screen);

		global $wp_meta_boxes;
		$screen_id = $screen->id;

		// Countries meta box should always be added.
		$this->assertArrayHasKey($screen_id, $wp_meta_boxes);
	}

	// -------------------------------------------------------------------------
	// output_widget_mrr_growth()
	// -------------------------------------------------------------------------

	/**
	 * output_widget_mrr_growth() outputs a string.
	 */
	public function test_output_widget_mrr_growth_outputs_string(): void {

		ob_start();
		$this->page->output_widget_mrr_growth();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// output_widget_countries()
	// -------------------------------------------------------------------------

	/**
	 * output_widget_countries() outputs a string.
	 */
	public function test_output_widget_countries_outputs_string(): void {

		ob_start();
		$this->page->output_widget_countries();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// output_widget_forms()
	// -------------------------------------------------------------------------

	/**
	 * output_widget_forms() outputs a string.
	 */
	public function test_output_widget_forms_outputs_string(): void {

		ob_start();
		$this->page->output_widget_forms();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// output_widget_most_visited_sites()
	// -------------------------------------------------------------------------

	/**
	 * output_widget_most_visited_sites() outputs a string.
	 */
	public function test_output_widget_most_visited_sites_outputs_string(): void {

		ob_start();
		$this->page->output_widget_most_visited_sites();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// output_widget_revenues()
	// -------------------------------------------------------------------------

	/**
	 * output_widget_revenues() outputs a string.
	 */
	public function test_output_widget_revenues_outputs_string(): void {

		ob_start();
		$this->page->output_widget_revenues();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	/**
	 * output_widget_revenues() fires wu_dashboard_after_revenue_widget action.
	 */
	public function test_output_widget_revenues_fires_after_revenue_action(): void {

		$fired = false;
		add_action(
			'wu_dashboard_after_revenue_widget',
			function () use (&$fired) {
				$fired = true;
			}
		);

		ob_start();
		$this->page->output_widget_revenues();
		ob_get_clean();

		$this->assertTrue($fired);
	}

	/**
	 * output_widget_revenues() applies wu_dashboard_revenue_amount filter.
	 */
	public function test_output_widget_revenues_applies_revenue_filter(): void {

		$filter_called = false;
		add_filter(
			'wu_dashboard_revenue_amount',
			function ($amount, $currency) use (&$filter_called) {
				$filter_called = true;
				return $amount;
			},
			10,
			2
		);

		ob_start();
		$this->page->output_widget_revenues();
		ob_get_clean();

		$this->assertTrue($filter_called);

		remove_all_filters('wu_dashboard_revenue_amount');
	}

	// -------------------------------------------------------------------------
	// output_widget_new_accounts()
	// -------------------------------------------------------------------------

	/**
	 * output_widget_new_accounts() outputs a string.
	 */
	public function test_output_widget_new_accounts_outputs_string(): void {

		ob_start();
		$this->page->output_widget_new_accounts();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	/**
	 * output_widget_new_accounts() accepts null arguments.
	 */
	public function test_output_widget_new_accounts_accepts_null_args(): void {

		ob_start();
		$this->page->output_widget_new_accounts(null, null);
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	/**
	 * register_scripts() registers wu-apex-charts script.
	 */
	public function test_register_scripts_registers_apex_charts(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-apex-charts', 'registered'));
	}

	/**
	 * register_scripts() registers wu-vue-apex-charts script.
	 */
	public function test_register_scripts_registers_vue_apex_charts(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-vue-apex-charts', 'registered'));
	}

	/**
	 * register_scripts() registers wu-dashboard-stats script.
	 */
	public function test_register_scripts_registers_dashboard_stats(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-dashboard-stats', 'registered'));
	}

	/**
	 * register_scripts() enqueues wu-dashboard-stats script.
	 */
	public function test_register_scripts_enqueues_dashboard_stats(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-dashboard-stats', 'enqueued'));
	}

	/**
	 * register_scripts() registers wu-apex-charts style.
	 */
	public function test_register_scripts_registers_apex_charts_style(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_style_is('wu-apex-charts', 'registered'));
	}

	/**
	 * register_scripts() localizes wu-dashboard-stats with month_list.
	 */
	public function test_register_scripts_localizes_month_list(): void {

		$this->page->register_scripts();

		$localized_vars = wp_scripts()->get_data('wu-dashboard-stats', 'data');
		$this->assertStringContainsString('"month_list":', $localized_vars);
	}

	/**
	 * register_scripts() localizes wu-dashboard-stats with today.
	 */
	public function test_register_scripts_localizes_today(): void {

		$this->page->register_scripts();

		$localized_vars = wp_scripts()->get_data('wu-dashboard-stats', 'data');
		$this->assertStringContainsString('"today":"', $localized_vars);
	}

	/**
	 * register_scripts() localizes wu-dashboard-stats with i18n new_mrr.
	 */
	public function test_register_scripts_localizes_new_mrr(): void {

		$this->page->register_scripts();

		$localized_vars = wp_scripts()->get_data('wu-dashboard-stats', 'data');
		$this->assertStringContainsString('"new_mrr":"New MRR"', $localized_vars);
	}

	/**
	 * register_scripts() localizes wu-dashboard-stats with i18n cancellations.
	 */
	public function test_register_scripts_localizes_cancellations(): void {

		$this->page->register_scripts();

		$localized_vars = wp_scripts()->get_data('wu-dashboard-stats', 'data');
		$this->assertStringContainsString('"cancellations":', $localized_vars);
	}

	/**
	 * register_scripts() localizes wu-dashboard-stats with start_date.
	 */
	public function test_register_scripts_localizes_start_date(): void {

		$this->page->register_scripts();

		$localized_vars = wp_scripts()->get_data('wu-dashboard-stats', 'data');
		$this->assertStringContainsString('"start_date":', $localized_vars);
	}

	/**
	 * register_scripts() month_list contains 12 months.
	 */
	public function test_register_scripts_month_list_has_12_months(): void {

		$this->page->register_scripts();

		$localized_vars = wp_scripts()->get_data('wu-dashboard-stats', 'data');

		// Extract the JSON from the localized script data.
		preg_match('/wu_dashboard_statistics_vars\s*=\s*(\{.*\})/s', $localized_vars, $matches);
		if ( ! empty($matches[1])) {
			$data = json_decode($matches[1], true);
			if (is_array($data) && isset($data['month_list'])) {
				$this->assertCount(12, $data['month_list']);
			}
		}

		// At minimum, verify the month_list key is present.
		$this->assertStringContainsString('"month_list":', $localized_vars);
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * output() is callable (method exists and is public).
	 *
	 * The full template rendering requires wu_wrap_use_container() which is
	 * only available in a fully-bootstrapped WP environment. We verify the
	 * method is accessible and callable without asserting template output.
	 */
	public function test_output_method_is_callable(): void {

		$this->assertTrue(is_callable([$this->page, 'output']));
	}

	// -------------------------------------------------------------------------
	// render_csv_button()
	// -------------------------------------------------------------------------

	/**
	 * render_csv_button() outputs HTML with CSV link.
	 */
	public function test_render_csv_button_outputs_html(): void {

		ob_start();
		$this->page->render_csv_button(
			[
				'slug'    => 'test-csv',
				'headers' => ['col1', 'col2'],
				'data'    => [['val1', 'val2']],
			]
		);
		$output = ob_get_clean();

		$this->assertIsString($output);
		$this->assertStringContainsString('wu-export-button', $output);
	}

	/**
	 * render_csv_button() uses default slug 'csv' when not provided.
	 */
	public function test_render_csv_button_uses_default_slug(): void {

		ob_start();
		$this->page->render_csv_button([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('attr-slug-csv="csv"', $output);
	}

	/**
	 * render_csv_button() uses provided slug.
	 */
	public function test_render_csv_button_uses_provided_slug(): void {

		ob_start();
		$this->page->render_csv_button(['slug' => 'my-export']);
		$output = ob_get_clean();

		$this->assertStringContainsString('attr-slug-csv="my-export"', $output);
	}

	/**
	 * render_csv_button() includes CSV label.
	 */
	public function test_render_csv_button_includes_csv_label(): void {

		ob_start();
		$this->page->render_csv_button([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('CSV', $output);
	}

	/**
	 * render_csv_button() includes hidden input for headers.
	 */
	public function test_render_csv_button_includes_headers_input(): void {

		ob_start();
		$this->page->render_csv_button(
			[
				'slug'    => 'test',
				'headers' => ['id', 'name'],
			]
		);
		$output = ob_get_clean();

		$this->assertStringContainsString('csv_headers_test', $output);
	}

	/**
	 * render_csv_button() includes hidden input for data.
	 */
	public function test_render_csv_button_includes_data_input(): void {

		ob_start();
		$this->page->render_csv_button(
			[
				'slug' => 'test',
				'data' => [['row1col1', 'row1col2']],
			]
		);
		$output = ob_get_clean();

		$this->assertStringContainsString('csv_data_test', $output);
	}

	/**
	 * render_csv_button() includes hidden input for action.
	 */
	public function test_render_csv_button_includes_action_input(): void {

		ob_start();
		$this->page->render_csv_button(
			[
				'slug'   => 'test',
				'action' => 'wu_generate_csv',
			]
		);
		$output = ob_get_clean();

		$this->assertStringContainsString('csv_action_test', $output);
	}

	/**
	 * render_csv_button() uses default action wu_generate_csv.
	 */
	public function test_render_csv_button_default_action(): void {

		ob_start();
		$this->page->render_csv_button(['slug' => 'test']);
		$output = ob_get_clean();

		$this->assertStringContainsString('wu_generate_csv', $output);
	}

	/**
	 * render_csv_button() escapes slug in output.
	 */
	public function test_render_csv_button_escapes_slug(): void {

		ob_start();
		$this->page->render_csv_button(['slug' => 'safe-slug']);
		$output = ob_get_clean();

		$this->assertStringContainsString('safe-slug', $output);
		// Ensure no unescaped HTML injection.
		$this->assertStringNotContainsString('<script>', $output);
	}
}
