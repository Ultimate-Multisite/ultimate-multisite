<?php
/**
 * Tests for Debug_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages\Debug;

use WP_UnitTestCase;

/**
 * Test class for Debug_Admin_Page.
 *
 * Tests all public methods of the Debug_Admin_Page class.
 */
class Debug_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Debug_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->page = new Debug_Admin_Page();
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

		$this->assertEquals('wp-ultimo-debug-pages', $property->getValue($this->page));
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
	 * Test supported_panels contains network_admin_menu.
	 */
	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * get_title returns Registered Pages.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Registered Pages', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns Registered Pages.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Registered Pages', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_submenu_title returns Registered Pages.
	 */
	public function test_get_submenu_title(): void {

		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Registered Pages', $title);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * register_widgets adds meta box.
	 */
	public function test_register_widgets_adds_meta_box(): void {

		global $wp_meta_boxes;

		set_current_screen('dashboard-network');

		$this->page->register_widgets();

		$screen_id = get_current_screen()->id;

		$this->assertArrayHasKey($screen_id, $wp_meta_boxes);
		$this->assertArrayHasKey('normal', $wp_meta_boxes[ $screen_id ]);
		$this->assertArrayHasKey('default', $wp_meta_boxes[ $screen_id ]['normal']);
		$this->assertArrayHasKey('wp-ultimo-debug-pages', $wp_meta_boxes[ $screen_id ]['normal']['default']);
	}

	/**
	 * register_widgets does not throw.
	 */
	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard-network');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// render_debug_pages()
	// -------------------------------------------------------------------------

	/**
	 * render_debug_pages outputs HTML.
	 */
	public function test_render_debug_pages_outputs_html(): void {

		ob_start();
		$this->page->render_debug_pages();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	/**
	 * render_debug_pages outputs list.
	 */
	public function test_render_debug_pages_outputs_list(): void {

		ob_start();
		$this->page->render_debug_pages();
		$output = ob_get_clean();

		$this->assertStringContainsString('<ul', $output);
		$this->assertStringContainsString('</ul>', $output);
	}

	/**
	 * render_debug_pages outputs links.
	 */
	public function test_render_debug_pages_outputs_links(): void {

		ob_start();
		$this->page->render_debug_pages();
		$output = ob_get_clean();

		$this->assertStringContainsString('<a', $output);
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
