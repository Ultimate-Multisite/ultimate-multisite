<?php
/**
 * Tests for Jobs_List_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Jobs_List_Admin_Page.
 *
 * Tests all public methods of the Jobs_List_Admin_Page class.
 */
class Jobs_List_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Jobs_List_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->page = new Jobs_List_Admin_Page();
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

		$this->assertEquals('wp-ultimo-jobs', $property->getValue($this->page));
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
		$this->assertEquals('wu_read_jobs', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// init()
	// -------------------------------------------------------------------------

	/**
	 * init registers hide_as_admin_page filter.
	 */
	public function test_init_registers_hide_as_admin_page_filter(): void {

		$this->page->init();

		$this->assertGreaterThan(
			0,
			has_filter('action_scheduler_admin_view_class', [$this->page, 'hide_as_admin_page'])
		);
	}

	// -------------------------------------------------------------------------
	// hide_as_admin_page()
	// -------------------------------------------------------------------------

	/**
	 * hide_as_admin_page returns original class in network admin.
	 */
	public function test_hide_as_admin_page_returns_original_in_network_admin(): void {

		// Simulate network admin context.
		set_current_screen('dashboard-network');

		$original = 'ActionScheduler_AdminView';
		$result   = $this->page->hide_as_admin_page($original);

		$this->assertEquals($original, $result);
	}

	/**
	 * hide_as_admin_page returns custom class when not network admin.
	 */
	public function test_hide_as_admin_page_returns_custom_when_not_network_admin(): void {

		// Simulate regular admin context.
		set_current_screen('dashboard');

		$original = 'ActionScheduler_AdminView';
		$result   = $this->page->hide_as_admin_page($original);

		$this->assertEquals(\WP_Ultimo\Compat\AS_Admin_View::class, $result);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * get_title returns Jobs.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Jobs', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns Jobs.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Jobs', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_submenu_title returns Jobs.
	 */
	public function test_get_submenu_title(): void {

		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Jobs', $title);
	}

	// -------------------------------------------------------------------------
	// page_loaded()
	// -------------------------------------------------------------------------

	/**
	 * page_loaded does not throw.
	 */
	public function test_page_loaded_does_not_throw(): void {

		// Mock ActionScheduler_AdminView if not available.
		if ( ! class_exists('ActionScheduler_AdminView')) {
			$this->markTestSkipped('ActionScheduler_AdminView not available');
		}

		$this->page->page_loaded();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * output does not throw.
	 */
	public function test_output_does_not_throw(): void {

		// Mock ActionScheduler_AdminView if not available.
		if ( ! class_exists('ActionScheduler_AdminView')) {
			$this->markTestSkipped('ActionScheduler_AdminView not available');
		}

		ob_start();
		$this->page->output();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}
}
