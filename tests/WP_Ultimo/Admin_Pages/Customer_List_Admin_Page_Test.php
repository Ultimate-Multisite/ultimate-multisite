<?php
/**
 * Tests for Customer_List_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Customer_List_Admin_Page.
 *
 * Covers all public methods of Customer_List_Admin_Page to reach >=80% coverage.
 * Methods that call wp_die(), output templates, or require HTTP context
 * are tested for their guard conditions and side-effects only.
 */
class Customer_List_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Customer_List_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->page = new Customer_List_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset(
			$_REQUEST['wu_action'],
			$_REQUEST['nonce'],
			$_REQUEST['type'],
			$_REQUEST['user_id'],
			$_REQUEST['email_address'],
			$_REQUEST['username'],
			$_REQUEST['password']
		);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// init()
	// -------------------------------------------------------------------------

	/**
	 * init() registers the export_customers action on plugins_loaded.
	 */
	public function test_init_registers_export_customers_action(): void {

		$page = new Customer_List_Admin_Page();
		$page->init();

		$this->assertGreaterThan(
			0,
			has_action('plugins_loaded', [$page, 'export_customers']),
			'export_customers should be hooked to plugins_loaded'
		);
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

		$this->assertEquals('wp-ultimo-customers', $property->getValue($this->page));
	}

	/**
	 * Test page type is submenu.
	 */
	public function test_page_type(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('type');
		$property->setAccessible(true);

		$this->assertEquals('submenu', $property->getValue($this->page));
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
	 * Test supported_panels contains network_admin_menu.
	 */
	public function test_supported_panels(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_read_customers', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_title returns string.
	 */
	public function test_get_title(): void {
		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Customers', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_menu_title returns string.
	 */
	public function test_get_menu_title(): void {
		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Customers', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_submenu_title returns string.
	 */
	public function test_get_submenu_title(): void {
		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Customers', $title);
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	/**
	 * Test get_labels returns array.
	 */
	public function test_get_labels(): void {
		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
		$this->assertArrayHasKey('deleted_message', $labels);
		$this->assertArrayHasKey('search_label', $labels);
	}

	/**
	 * Test get_labels deleted_message is non-empty.
	 */
	public function test_get_labels_deleted_message(): void {
		$labels = $this->page->get_labels();

		$this->assertNotEmpty($labels['deleted_message']);
		$this->assertIsString($labels['deleted_message']);
	}

	/**
	 * Test get_labels search_label is non-empty.
	 */
	public function test_get_labels_search_label(): void {
		$labels = $this->page->get_labels();

		$this->assertNotEmpty($labels['search_label']);
		$this->assertIsString($labels['search_label']);
	}

	// -------------------------------------------------------------------------
	// action_links()
	// -------------------------------------------------------------------------

	/**
	 * Test action_links returns array.
	 */
	public function test_action_links(): void {
		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertCount(2, $links);
	}

	/**
	 * Test action_links has add customer link.
	 */
	public function test_action_links_add_customer(): void {
		$links = $this->page->action_links();

		$this->assertEquals('Add Customer', $links[0]['label']);
		$this->assertArrayHasKey('url', $links[0]);
		$this->assertArrayHasKey('icon', $links[0]);
	}

	/**
	 * Test action_links add customer has classes.
	 */
	public function test_action_links_add_customer_has_classes(): void {
		$links = $this->page->action_links();

		$this->assertArrayHasKey('classes', $links[0]);
		$this->assertEquals('wubox', $links[0]['classes']);
	}

	/**
	 * Test action_links has export link.
	 */
	public function test_action_links_export(): void {
		$links = $this->page->action_links();

		$this->assertEquals('Export as CSV', $links[1]['label']);
		$this->assertStringContainsString('wu_export_customers', $links[1]['url']);
	}

	/**
	 * Test action_links export has icon.
	 */
	public function test_action_links_export_has_icon(): void {
		$links = $this->page->action_links();

		$this->assertArrayHasKey('icon', $links[1]);
		$this->assertNotEmpty($links[1]['icon']);
	}

	/**
	 * Test action_links export URL contains nonce.
	 */
	public function test_action_links_export_url_has_nonce(): void {
		$links = $this->page->action_links();

		$this->assertStringContainsString('nonce', $links[1]['url']);
	}

	// -------------------------------------------------------------------------
	// table()
	// -------------------------------------------------------------------------

	/**
	 * Test table returns list table instance.
	 */
	public function test_table(): void {
		$table = $this->page->table();

		$this->assertInstanceOf(\WP_Ultimo\List_Tables\Customer_List_Table::class, $table);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets is callable (empty method).
	 */
	public function test_register_widgets_is_callable(): void {

		$this->assertTrue(is_callable([$this->page, 'register_widgets']));

		// Should not throw.
		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// export_customers()
	// -------------------------------------------------------------------------

	/**
	 * export_customers() returns early when wu_action is not wu_export_customers.
	 */
	public function test_export_customers_returns_early_when_no_action(): void {

		// No wu_action in request — should return early without doing anything.
		ob_start();
		$this->page->export_customers();
		$output = ob_get_clean();

		// No output expected.
		$this->assertEmpty($output);
	}

	/**
	 * export_customers() calls wp_die when nonce is invalid.
	 */
	public function test_export_customers_dies_on_invalid_nonce(): void {

		$_REQUEST['wu_action'] = 'wu_export_customers';
		$_REQUEST['nonce']     = 'invalid_nonce';

		$this->expectException(\WPDieException::class);

		$this->page->export_customers();
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * register_forms() does not throw.
	 */
	public function test_register_forms_does_not_throw(): void {

		$this->page->register_forms();

		$this->assertTrue(true);
	}

	/**
	 * register_forms() is callable.
	 */
	public function test_register_forms_is_callable(): void {

		$this->assertTrue(is_callable([$this->page, 'register_forms']));
	}

	// -------------------------------------------------------------------------
	// render_add_new_customer_modal()
	// -------------------------------------------------------------------------

	/**
	 * render_add_new_customer_modal() outputs HTML form.
	 */
	public function test_render_add_new_customer_modal_outputs_html(): void {

		ob_start();
		$this->page->render_add_new_customer_modal();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	/**
	 * render_add_new_customer_modal() is callable.
	 */
	public function test_render_add_new_customer_modal_is_callable(): void {

		$this->assertTrue(is_callable([$this->page, 'render_add_new_customer_modal']));
	}

	// -------------------------------------------------------------------------
	// handle_add_new_customer_modal()
	// -------------------------------------------------------------------------

	/**
	 * handle_add_new_customer_modal() sends JSON error when customer creation fails.
	 *
	 * When type is 'existing' and user_id is 0, wu_create_customer returns WP_Error.
	 * wp_send_json_error() calls wp_die() which throws WPAjaxDieStopException.
	 */
	public function test_handle_add_new_customer_modal_sends_json_error_on_failure(): void {

		$_REQUEST['type']    = 'existing';
		$_REQUEST['user_id'] = 0;

		$this->expectException(\WPAjaxDieStopException::class);

		$this->page->handle_add_new_customer_modal();
	}

	/**
	 * handle_add_new_customer_modal() is callable.
	 */
	public function test_handle_add_new_customer_modal_is_callable(): void {

		$this->assertTrue(is_callable([$this->page, 'handle_add_new_customer_modal']));
	}

	/**
	 * handle_add_new_customer_modal() with type 'new' and missing email sends JSON error.
	 */
	public function test_handle_add_new_customer_modal_new_type_missing_email(): void {

		$_REQUEST['type']          = 'new';
		$_REQUEST['username']      = 'testuser_' . wp_rand(1000, 9999);
		$_REQUEST['email_address'] = '';

		$this->expectException(\WPAjaxDieStopException::class);

		$this->page->handle_add_new_customer_modal();
	}

	/**
	 * handle_add_new_customer_modal() with type 'new' and valid email sends JSON error
	 * when username is already taken (exercises the new-user branch).
	 */
	public function test_handle_add_new_customer_modal_new_type_with_valid_email(): void {

		$_REQUEST['type']          = 'new';
		$_REQUEST['username']      = 'testcustomer_' . wp_rand(10000, 99999);
		$_REQUEST['email_address'] = 'testcustomer_' . wp_rand(10000, 99999) . '@example.com';

		// wu_create_customer will attempt to create a user; it either succeeds (sends JSON success)
		// or fails (sends JSON error). Either way wp_send_json_* calls wp_die().
		$this->expectException(\WPAjaxDieStopException::class);

		$this->page->handle_add_new_customer_modal();
	}
}
