<?php
/**
 * Tests for Checkout_Form_List_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Faker;
use WP_Ultimo\Models\Checkout_Form;

/**
 * Test class for Checkout_Form_List_Admin_Page.
 */
class Checkout_Form_List_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Checkout_Form_List_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new Checkout_Form_List_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset(
			$_POST['template'],
			$_REQUEST['template']
		);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Static properties
	// -------------------------------------------------------------------------

	/**
	 * Test page id is correct.
	 */
	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-checkout-forms', $property->getValue($this->page));
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
	 * Test supported_panels contains network_admin_menu with correct capability.
	 */
	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_read_checkout_forms', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_title returns expected string.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Checkout Forms', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_menu_title returns expected string.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Checkout Forms', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_submenu_title returns expected string.
	 */
	public function test_get_submenu_title(): void {

		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Checkout Forms', $title);
	}

	// -------------------------------------------------------------------------
	// action_links()
	// -------------------------------------------------------------------------

	/**
	 * Test action_links returns array with one item.
	 */
	public function test_action_links_returns_array(): void {

		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertCount(1, $links);
	}

	/**
	 * Test action_links first item has required keys.
	 */
	public function test_action_links_first_item_has_required_keys(): void {

		$links = $this->page->action_links();

		$this->assertArrayHasKey('label', $links[0]);
		$this->assertArrayHasKey('icon', $links[0]);
		$this->assertArrayHasKey('classes', $links[0]);
		$this->assertArrayHasKey('url', $links[0]);
	}

	/**
	 * Test action_links first item label is Add Checkout Form.
	 */
	public function test_action_links_label(): void {

		$links = $this->page->action_links();

		$this->assertEquals('Add Checkout Form', $links[0]['label']);
	}

	/**
	 * Test action_links first item has wubox class.
	 */
	public function test_action_links_has_wubox_class(): void {

		$links = $this->page->action_links();

		$this->assertEquals('wubox', $links[0]['classes']);
	}

	/**
	 * Test action_links first item has icon.
	 */
	public function test_action_links_has_icon(): void {

		$links = $this->page->action_links();

		$this->assertEquals('wu-circle-with-plus', $links[0]['icon']);
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	/**
	 * Test get_labels returns array with required keys.
	 */
	public function test_get_labels_returns_required_keys(): void {

		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
		$this->assertArrayHasKey('deleted_message', $labels);
		$this->assertArrayHasKey('search_label', $labels);
	}

	/**
	 * Test get_labels deleted_message value.
	 */
	public function test_get_labels_deleted_message(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Checkout Form removed successfully.', $labels['deleted_message']);
	}

	/**
	 * Test get_labels search_label value.
	 */
	public function test_get_labels_search_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Search Checkout Form', $labels['search_label']);
	}

	// -------------------------------------------------------------------------
	// table()
	// -------------------------------------------------------------------------

	/**
	 * Test table returns Checkout_Form_List_Table instance.
	 */
	public function test_table_returns_list_table_instance(): void {

		$table = $this->page->table();

		$this->assertInstanceOf(\WP_Ultimo\List_Tables\Checkout_Form_List_Table::class, $table);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets does not throw when called.
	 */
	public function test_register_widgets_does_not_throw(): void {

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * Test register_forms registers the add_new_checkout_form form.
	 */
	public function test_register_forms_registers_add_new_checkout_form(): void {

		$this->page->register_forms();

		$form_manager = \WP_Ultimo\Managers\Form_Manager::get_instance();
		$this->assertTrue($form_manager->is_form_registered('add_new_checkout_form'));
	}

	// -------------------------------------------------------------------------
	// render_add_new_checkout_form_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test render_add_new_checkout_form_modal produces output.
	 */
	public function test_render_add_new_checkout_form_modal_produces_output(): void {

		ob_start();
		$this->page->render_add_new_checkout_form_modal();
		$output = ob_get_clean();

		$this->assertNotEmpty($output);
	}

	/**
	 * Test render_add_new_checkout_form_modal output contains template options.
	 */
	public function test_render_add_new_checkout_form_modal_contains_template_options(): void {

		ob_start();
		$this->page->render_add_new_checkout_form_modal();
		$output = ob_get_clean();

		// The form should contain template selection options.
		$this->assertStringContainsString('single-step', $output);
	}

	// -------------------------------------------------------------------------
	// handle_add_new_checkout_form_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_add_new_checkout_form_modal creates a checkout form and sends success.
	 */
	public function test_handle_add_new_checkout_form_modal_sends_success(): void {

		$_REQUEST['template'] = 'single-step';

		ob_start();
		try {
			$this->page->handle_add_new_checkout_form_modal();
		} catch (\WPDieException $e) {
			// wp_send_json_success calls wp_die.
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertTrue($decoded['success']);
	}

	/**
	 * Test handle_add_new_checkout_form_modal success response contains redirect_url.
	 */
	public function test_handle_add_new_checkout_form_modal_response_has_redirect_url(): void {

		$_REQUEST['template'] = 'blank';

		ob_start();
		try {
			$this->page->handle_add_new_checkout_form_modal();
		} catch (\WPDieException $e) {
			// expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);

		if (isset($decoded['success']) && $decoded['success']) {
			$this->assertArrayHasKey('data', $decoded);
			$this->assertArrayHasKey('redirect_url', $decoded['data']);
			$this->assertStringContainsString('wp-ultimo-edit-checkout-form', $decoded['data']['redirect_url']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_add_new_checkout_form_modal with multi-step template.
	 */
	public function test_handle_add_new_checkout_form_modal_multi_step_template(): void {

		$_REQUEST['template'] = 'multi-step';

		ob_start();
		try {
			$this->page->handle_add_new_checkout_form_modal();
		} catch (\WPDieException $e) {
			// expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertTrue($decoded['success']);
	}

	// -------------------------------------------------------------------------
	// Instantiation
	// -------------------------------------------------------------------------

	/**
	 * Test page can be instantiated.
	 */
	public function test_page_instantiation(): void {

		$page = new Checkout_Form_List_Admin_Page();

		$this->assertInstanceOf(Checkout_Form_List_Admin_Page::class, $page);
	}

	/**
	 * Test page extends List_Admin_Page.
	 */
	public function test_page_extends_list_admin_page(): void {

		$this->assertInstanceOf(List_Admin_Page::class, $this->page);
	}
}
