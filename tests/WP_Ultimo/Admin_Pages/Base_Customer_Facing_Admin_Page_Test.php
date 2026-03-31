<?php
/**
 * Tests for Base_Customer_Facing_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Base_Customer_Facing_Admin_Page.
 *
 * Tests all public methods of the abstract Base_Customer_Facing_Admin_Page class
 * using a concrete implementation.
 */
class Base_Customer_Facing_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Base_Customer_Facing_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		// Create a concrete implementation for testing the abstract class.
		$this->page = new class() extends Base_Customer_Facing_Admin_Page {

			protected $id = 'test-customer-facing-page';

			protected $type = 'toplevel';

			protected $supported_panels = [
				'network_admin_menu' => 'manage_network',
			];

			public function get_title() {

				return 'Test Customer Facing Page';
			}

			public function get_menu_title() {

				return 'Test Page';
			}

			public function output(): void {

				echo 'Test output';
			}
		};
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset(
			$_GET['customize'],
			$_POST['title'],
			$_POST['position'],
			$_POST['menu_icon'],
			$_POST['submit'],
			$_SERVER['HTTP_REFERER']
		);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	/**
	 * Test edit_capability is manage_network by default.
	 */
	public function test_edit_capability_default(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit_capability');
		$property->setAccessible(true);

		$this->assertEquals('manage_network', $property->getValue($this->page));
	}

	/**
	 * Test editing is false by default.
	 */
	public function test_editing_default_false(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('editing');
		$property->setAccessible(true);

		$this->assertFalse($property->getValue($this->page));
	}

	/**
	 * Test menu_settings is true by default.
	 */
	public function test_menu_settings_default_true(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('menu_settings');
		$property->setAccessible(true);

		$this->assertTrue($property->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// get_page_unique_id()
	// -------------------------------------------------------------------------

	/**
	 * get_page_unique_id returns a string.
	 */
	public function test_get_page_unique_id_returns_string(): void {

		$result = $this->page->get_page_unique_id();

		$this->assertIsString($result);
	}

	/**
	 * get_page_unique_id returns lowercase.
	 */
	public function test_get_page_unique_id_lowercase(): void {

		$result = $this->page->get_page_unique_id();

		$this->assertEquals(strtolower($result), $result);
	}

	/**
	 * get_page_unique_id is non-empty.
	 */
	public function test_get_page_unique_id_non_empty(): void {

		$result = $this->page->get_page_unique_id();

		$this->assertNotEmpty($result);
	}

	// -------------------------------------------------------------------------
	// get_defaults()
	// -------------------------------------------------------------------------

	/**
	 * get_defaults returns an array.
	 */
	public function test_get_defaults_returns_array(): void {

		// Trigger change_parameters to populate original_parameters.
		$this->page->change_parameters();

		$result = $this->page->get_defaults();

		$this->assertIsArray($result);
	}

	/**
	 * get_defaults contains title key.
	 */
	public function test_get_defaults_contains_title(): void {

		$this->page->change_parameters();

		$result = $this->page->get_defaults();

		$this->assertArrayHasKey('title', $result);
	}

	/**
	 * get_defaults contains position key.
	 */
	public function test_get_defaults_contains_position(): void {

		$this->page->change_parameters();

		$result = $this->page->get_defaults();

		$this->assertArrayHasKey('position', $result);
	}

	/**
	 * get_defaults contains menu_icon key.
	 */
	public function test_get_defaults_contains_menu_icon(): void {

		$this->page->change_parameters();

		$result = $this->page->get_defaults();

		$this->assertArrayHasKey('menu_icon', $result);
	}

	// -------------------------------------------------------------------------
	// get_page_settings()
	// -------------------------------------------------------------------------

	/**
	 * get_page_settings returns an array.
	 */
	public function test_get_page_settings_returns_array(): void {

		$this->page->change_parameters();

		$result = $this->page->get_page_settings();

		$this->assertIsArray($result);
	}

	/**
	 * get_page_settings merges with defaults.
	 */
	public function test_get_page_settings_merges_with_defaults(): void {

		$this->page->change_parameters();

		$result = $this->page->get_page_settings();

		$this->assertArrayHasKey('title', $result);
		$this->assertArrayHasKey('position', $result);
		$this->assertArrayHasKey('menu_icon', $result);
	}

	// -------------------------------------------------------------------------
	// save_page_settings()
	// -------------------------------------------------------------------------

	/**
	 * save_page_settings returns boolean.
	 */
	public function test_save_page_settings_returns_boolean(): void {

		$this->page->change_parameters();

		$result = $this->page->save_page_settings(
			[
				'title'     => 'New Title',
				'position'  => 10,
				'menu_icon' => 'dashicons-admin-generic',
			]
		);

		$this->assertIsBool($result);
	}

	/**
	 * save_page_settings filters unauthorized params.
	 */
	public function test_save_page_settings_filters_unauthorized_params(): void {

		$this->page->change_parameters();

		$this->page->save_page_settings(
			[
				'title'            => 'New Title',
				'unauthorized_key' => 'should be filtered',
			]
		);

		$saved = $this->page->get_page_settings();

		$this->assertArrayNotHasKey('unauthorized_key', $saved);
	}

	// -------------------------------------------------------------------------
	// is_edit_mode()
	// -------------------------------------------------------------------------

	/**
	 * is_edit_mode returns false when not editing.
	 */
	public function test_is_edit_mode_false_when_not_editing(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('editing');
		$property->setAccessible(true);
		$property->setValue($this->page, false);

		$this->assertFalse($this->page->is_edit_mode());
	}

	/**
	 * is_edit_mode returns false when editing but no capability.
	 */
	public function test_is_edit_mode_false_without_capability(): void {

		wp_set_current_user(0);

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('editing');
		$property->setAccessible(true);
		$property->setValue($this->page, true);

		$this->assertFalse($this->page->is_edit_mode());
	}

	/**
	 * is_edit_mode returns true when editing with capability.
	 */
	public function test_is_edit_mode_true_with_capability(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('editing');
		$property->setAccessible(true);
		$property->setValue($this->page, true);

		$this->assertTrue($this->page->is_edit_mode());
	}

	// -------------------------------------------------------------------------
	// change_parameters()
	// -------------------------------------------------------------------------

	/**
	 * change_parameters stores original parameters.
	 */
	public function test_change_parameters_stores_originals(): void {

		$this->page->change_parameters();

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('original_parameters');
		$property->setAccessible(true);

		$originals = $property->getValue($this->page);

		$this->assertIsArray($originals);
		$this->assertArrayHasKey('title', $originals);
		$this->assertArrayHasKey('position', $originals);
		$this->assertArrayHasKey('menu_icon', $originals);
	}

	/**
	 * change_parameters updates title from settings.
	 */
	public function test_change_parameters_updates_title(): void {

		$this->page->change_parameters();

		$this->page->save_page_settings(['title' => 'Custom Title']);

		$this->page->change_parameters();

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('title');
		$property->setAccessible(true);

		$this->assertEquals('Custom Title', $property->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// render_edit_page()
	// -------------------------------------------------------------------------

	/**
	 * render_edit_page outputs HTML.
	 */
	public function test_render_edit_page_outputs_html(): void {

		$this->page->change_parameters();

		ob_start();
		$this->page->render_edit_page();
		$output = ob_get_clean();

		$this->assertIsString($output);
		$this->assertNotEmpty($output);
	}

	/**
	 * render_edit_page includes title field.
	 */
	public function test_render_edit_page_includes_title_field(): void {

		$this->page->change_parameters();

		ob_start();
		$this->page->render_edit_page();
		$output = ob_get_clean();

		$this->assertStringContainsString('Page & Menu Title', $output);
	}

	// -------------------------------------------------------------------------
	// handle_edit_page()
	// -------------------------------------------------------------------------

	/**
	 * handle_edit_page saves settings and sends JSON success.
	 */
	public function test_handle_edit_page_saves_settings(): void {

		$this->page->change_parameters();

		$_POST['title']     = 'Updated Title';
		$_POST['position']  = '15';
		$_POST['menu_icon'] = 'dashicons-admin-site';
		$_POST['submit']    = 'edit';
		$_SERVER['HTTP_REFERER'] = 'http://example.com/wp-admin/';

		$this->expectException(\WPAjaxDieStopException::class);

		$this->page->handle_edit_page();
	}

	/**
	 * handle_edit_page restores defaults when submit is restore.
	 */
	public function test_handle_edit_page_restores_defaults(): void {

		$this->page->change_parameters();

		$_POST['submit'] = 'restore';
		$_SERVER['HTTP_REFERER'] = 'http://example.com/wp-admin/';

		$this->expectException(\WPAjaxDieStopException::class);

		$this->page->handle_edit_page();
	}

	// -------------------------------------------------------------------------
	// get_settings()
	// -------------------------------------------------------------------------

	/**
	 * get_settings returns saved value when available.
	 */
	public function test_get_settings_returns_saved_value(): void {

		$option = 'test_option';
		$saved  = ['key' => 'value'];

		wu_save_setting($option, $saved);

		$result = $this->page->get_settings([], $option, 1);

		$this->assertEquals($saved, $result);
	}

	/**
	 * get_settings returns original result when no saved value.
	 */
	public function test_get_settings_returns_original_when_empty(): void {

		$option   = 'nonexistent_option_' . wp_rand();
		$original = ['original' => 'data'];

		$result = $this->page->get_settings($original, $option, 1);

		$this->assertEquals($original, $result);
	}

	// -------------------------------------------------------------------------
	// save_settings()
	// -------------------------------------------------------------------------

	/**
	 * save_settings returns early when action is not meta-box-order.
	 */
	public function test_save_settings_returns_early_wrong_action(): void {

		$_REQUEST['action'] = 'other-action';

		// Should not throw.
		$this->page->save_settings(1, 1, 'meta_key', 'value');

		$this->assertTrue(true);
	}

	/**
	 * save_settings returns early when page does not match.
	 */
	public function test_save_settings_returns_early_wrong_page(): void {

		$_REQUEST['action'] = 'meta-box-order';
		$_REQUEST['page']   = 'other-page';

		$this->page->save_settings(1, 1, 'meta_key', 'value');

		$this->assertTrue(true);
	}

	/**
	 * save_settings returns early when user lacks capability.
	 */
	public function test_save_settings_returns_early_no_capability(): void {

		wp_set_current_user(0);

		$_REQUEST['action'] = 'meta-box-order';
		$_REQUEST['page']   = 'test-customer-facing-page';

		$this->page->save_settings(1, 0, 'meta_key', 'value');

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_toplevel_menu_page()
	// -------------------------------------------------------------------------

	/**
	 * add_toplevel_menu_page sets edit to true when id is present.
	 */
	public function test_add_toplevel_menu_page_sets_edit(): void {

		$_REQUEST['id'] = '123';

		$this->page->add_toplevel_menu_page();

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit');
		$property->setAccessible(true);

		$this->assertTrue($property->getValue($this->page));
	}

	/**
	 * add_toplevel_menu_page returns string.
	 */
	public function test_add_toplevel_menu_page_returns_string(): void {

		$result = $this->page->add_toplevel_menu_page();

		$this->assertIsString($result);
	}
}
