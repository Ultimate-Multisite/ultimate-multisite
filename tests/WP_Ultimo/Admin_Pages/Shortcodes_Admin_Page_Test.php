<?php
/**
 * Tests for Shortcodes_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Shortcodes_Admin_Page.
 *
 * Tests all public methods of the Shortcodes_Admin_Page class.
 */
class Shortcodes_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Shortcodes_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->page = new Shortcodes_Admin_Page();
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

		$this->assertEquals('wp-ultimo-shortcodes', $property->getValue($this->page));
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
		$this->assertEquals('manage_network', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * get_title returns Available Shortcodes.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Available Shortcodes', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns Available Shortcodes.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Available Shortcodes', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_submenu_title returns Dashboard.
	 */
	public function test_get_submenu_title(): void {

		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Dashboard', $title);
	}

	// -------------------------------------------------------------------------
	// get_data()
	// -------------------------------------------------------------------------

	/**
	 * get_data returns an array.
	 */
	public function test_get_data_returns_array(): void {

		$data = $this->page->get_data();

		$this->assertIsArray($data);
	}

	/**
	 * get_data array items have required keys.
	 */
	public function test_get_data_items_have_required_keys(): void {

		$data = $this->page->get_data();

		if (empty($data)) {
			$this->markTestSkipped('No shortcode elements registered');
		}

		$first_item = reset($data);

		$this->assertArrayHasKey('generator_form_url', $first_item);
		$this->assertArrayHasKey('title', $first_item);
		$this->assertArrayHasKey('shortcode', $first_item);
		$this->assertArrayHasKey('description', $first_item);
		$this->assertArrayHasKey('params', $first_item);
	}

	/**
	 * get_data params is an array.
	 */
	public function test_get_data_params_is_array(): void {

		$data = $this->page->get_data();

		if (empty($data)) {
			$this->markTestSkipped('No shortcode elements registered');
		}

		$first_item = reset($data);

		$this->assertIsArray($first_item['params']);
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * output renders template.
	 */
	public function test_output_renders_template(): void {

		set_current_screen('dashboard-network');

		ob_start();
		$this->page->output();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}
}
