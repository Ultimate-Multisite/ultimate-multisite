<?php
/**
 * Tests for Placeholders_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Placeholders_Admin_Page.
 *
 * Tests all public methods of the Placeholders_Admin_Page class.
 */
class Placeholders_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Placeholders_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->page = new Placeholders_Admin_Page();
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

		$this->assertEquals('wp-ultimo-template-placeholders', $property->getValue($this->page));
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
	 * Test parent is none.
	 */
	public function test_parent_is_none(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);

		$this->assertEquals('none', $property->getValue($this->page));
	}

	/**
	 * Test highlight_menu_slug is wp-ultimo-settings.
	 */
	public function test_highlight_menu_slug(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-settings', $property->getValue($this->page));
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
		$this->assertEquals('manage_network', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * get_title returns Edit Template Placeholders.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Template Placeholders', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns Edit Template Placeholders.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Template Placeholders', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_submenu_title returns Edit Template Placeholders.
	 */
	public function test_get_submenu_title(): void {

		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Template Placeholders', $title);
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * output triggers wu_load_edit_placeholders_list_page action.
	 */
	public function test_output_triggers_load_action(): void {

		$action_fired = false;

		add_action(
			'wu_load_edit_placeholders_list_page',
			function () use (&$action_fired) {

				$action_fired = true;
			}
		);

		ob_start();
		$this->page->output();
		ob_end_clean();

		$this->assertTrue($action_fired);
	}

	/**
	 * output renders template.
	 */
	public function test_output_renders_template(): void {

		ob_start();
		$this->page->output();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	/**
	 * register_scripts does not throw.
	 */
	public function test_register_scripts_does_not_throw(): void {

		$this->page->register_scripts();

		$this->assertTrue(true);
	}

	/**
	 * register_scripts registers wu-edit-placeholders script.
	 */
	public function test_register_scripts_registers_edit_placeholders(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-edit-placeholders', 'registered'));
	}

	/**
	 * register_scripts enqueues wu-edit-placeholders script.
	 */
	public function test_register_scripts_enqueues_edit_placeholders(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-edit-placeholders', 'enqueued'));
	}
}
