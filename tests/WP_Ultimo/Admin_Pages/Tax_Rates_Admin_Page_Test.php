<?php
/**
 * Tests for Tax_Rates_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Tax\Tax;

/**
 * Test class for Tax_Rates_Admin_Page.
 *
 * Covers all public methods of Tax_Rates_Admin_Page to reach >=50% coverage.
 * Methods that call wp_die(), send headers, or require HTTP context are tested
 * for their guard conditions and side-effects only.
 */
class Tax_Rates_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Tax_Rates_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new Tax_Rates_Admin_Page();
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-tax-rates', $property->getValue($this->page));
	}

	public function test_page_type(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('type');
		$property->setAccessible(true);

		$this->assertEquals('submenu', $property->getValue($this->page));
	}

	public function test_parent_is_none(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);

		$this->assertEquals('none', $property->getValue($this->page));
	}

	public function test_highlight_menu_slug(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-settings', $property->getValue($this->page));
	}

	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('manage_network', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title() / get_menu_title() / get_submenu_title()
	// -------------------------------------------------------------------------

	public function test_get_title(): void {

		$this->assertEquals('Tax Rates', $this->page->get_title());
	}

	public function test_get_menu_title(): void {

		$this->assertEquals('Tax Rates', $this->page->get_menu_title());
	}

	public function test_get_submenu_title(): void {

		$this->assertEquals('Tax Rates', $this->page->get_submenu_title());
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	public function test_output_method_exists(): void {

		$this->assertTrue(method_exists($this->page, 'output'));
		$this->assertTrue(is_callable([$this->page, 'output']));
	}

	/**
	 * output() fires the wu_load_tax_rates_list_page action.
	 */
	public function test_output_fires_action(): void {

		$action_fired = false;
		$callback     = function () use (&$action_fired) {
			$action_fired = true;
		};

		add_action('wu_load_tax_rates_list_page', $callback);

		set_current_screen('dashboard-network');

		try {
			ob_start();
			$this->page->output();
			ob_get_clean();
		} finally {
			remove_action('wu_load_tax_rates_list_page', $callback);
		}

		$this->assertTrue($action_fired);
	}

	/**
	 * output() applies the wu_tax_rates_columns filter.
	 */
	public function test_output_applies_columns_filter(): void {

		$filter_called = false;
		$callback      = function ($columns) use (&$filter_called) {
			$filter_called = true;
			return $columns;
		};

		add_filter('wu_tax_rates_columns', $callback);

		set_current_screen('dashboard-network');

		try {
			ob_start();
			$this->page->output();
			ob_get_clean();
		} finally {
			remove_filter('wu_tax_rates_columns', $callback);
		}

		$this->assertTrue($filter_called);
	}

	/**
	 * output() columns filter receives an array with the expected keys.
	 */
	public function test_output_columns_filter_receives_expected_keys(): void {

		$received_columns = null;
		$callback         = function ($columns) use (&$received_columns) {
			$received_columns = $columns;
			return $columns;
		};

		add_filter('wu_tax_rates_columns', $callback);

		set_current_screen('dashboard-network');

		try {
			ob_start();
			$this->page->output();
			ob_get_clean();
		} finally {
			remove_filter('wu_tax_rates_columns', $callback);
		}

		$this->assertIsArray($received_columns);
		$this->assertArrayHasKey('title', $received_columns);
		$this->assertArrayHasKey('country', $received_columns);
		$this->assertArrayHasKey('state', $received_columns);
		$this->assertArrayHasKey('city', $received_columns);
		$this->assertArrayHasKey('tax_rate', $received_columns);
		$this->assertArrayHasKey('move', $received_columns);
	}

	/**
	 * output() columns filter can modify the columns array.
	 */
	public function test_output_columns_filter_can_modify_columns(): void {

		$received_columns = null;
		$callback         = function ($columns) use (&$received_columns) {
			$columns['custom_col'] = 'Custom Column';
			$received_columns      = $columns;
			return $columns;
		};

		add_filter('wu_tax_rates_columns', $callback);

		set_current_screen('dashboard-network');

		try {
			ob_start();
			$this->page->output();
			ob_get_clean();
		} finally {
			remove_filter('wu_tax_rates_columns', $callback);
		}

		$this->assertIsArray($received_columns);
		$this->assertArrayHasKey('custom_col', $received_columns);
		$this->assertEquals('Custom Column', $received_columns['custom_col']);
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	public function test_register_scripts_does_not_throw(): void {

		$this->page->register_scripts();

		$this->assertTrue(true);
	}

	public function test_register_scripts_registers_wu_tax_rates_script(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-tax-rates', 'registered'));
	}

	public function test_register_scripts_enqueues_wu_tax_rates_script(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-tax-rates', 'enqueued'));
	}

	// -------------------------------------------------------------------------
	// add_fields_widget() — protected method via reflection
	// -------------------------------------------------------------------------

	public function test_add_fields_widget_is_callable_via_reflection(): void {

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('add_fields_widget');
		$method->setAccessible(true);

		$this->assertTrue($method->isProtected());
	}

	/**
	 * add_fields_widget() registers a meta box with the given ID.
	 */
	public function test_add_fields_widget_registers_meta_box(): void {

		set_current_screen('dashboard-network');

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('add_fields_widget');
		$method->setAccessible(true);

		$method->invoke(
			$this->page,
			'test-widget',
			[
				'title'  => 'Test Widget',
				'fields' => [],
				'screen' => get_current_screen(),
			]
		);

		global $wp_meta_boxes;
		$screen_id = get_current_screen()->id;

		// Meta box should be registered under the screen ID.
		$this->assertArrayHasKey($screen_id, $wp_meta_boxes);
	}

	/**
	 * add_fields_widget() uses 'side' as the default position.
	 */
	public function test_add_fields_widget_default_position_is_side(): void {

		set_current_screen('dashboard-network');

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('add_fields_widget');
		$method->setAccessible(true);

		$method->invoke(
			$this->page,
			'test-side-widget',
			[
				'title'  => 'Side Widget',
				'fields' => [],
				'screen' => get_current_screen(),
			]
		);

		global $wp_meta_boxes;
		$screen_id = get_current_screen()->id;

		// Default position is 'side'.
		$this->assertArrayHasKey('side', $wp_meta_boxes[$screen_id]);
	}

	// -------------------------------------------------------------------------
	// Tax integration — Tax::get_instance()->get_tax_rate_types()
	// -------------------------------------------------------------------------

	public function test_tax_get_instance_returns_tax_object(): void {

		$tax = Tax::get_instance();

		$this->assertInstanceOf(Tax::class, $tax);
	}

	public function test_tax_get_tax_rate_types_returns_array(): void {

		$types = Tax::get_instance()->get_tax_rate_types();

		$this->assertIsArray($types);
	}

	public function test_tax_get_tax_rate_types_has_regular_key(): void {

		$types = Tax::get_instance()->get_tax_rate_types();

		$this->assertArrayHasKey('regular', $types);
	}

	public function test_tax_get_tax_rate_types_regular_value_is_string(): void {

		$types = Tax::get_instance()->get_tax_rate_types();

		$this->assertIsString($types['regular']);
	}

	/**
	 * The wu_get_tax_rate_types filter can add new types.
	 */
	public function test_tax_get_tax_rate_types_filter_can_add_types(): void {

		$callback = function ($types) {
			$types['custom'] = 'Custom Type';
			return $types;
		};

		add_filter('wu_get_tax_rate_types', $callback);

		$types = Tax::get_instance()->get_tax_rate_types();

		remove_filter('wu_get_tax_rate_types', $callback);

		$this->assertArrayHasKey('custom', $types);
		$this->assertEquals('Custom Type', $types['custom']);
	}

	// -------------------------------------------------------------------------
	// Instantiation
	// -------------------------------------------------------------------------

	public function test_instantiation_creates_object(): void {

		$page = new Tax_Rates_Admin_Page();

		$this->assertInstanceOf(Tax_Rates_Admin_Page::class, $page);
	}

	public function test_page_extends_base_admin_page(): void {

		$this->assertInstanceOf(Base_Admin_Page::class, $this->page);
	}
}
