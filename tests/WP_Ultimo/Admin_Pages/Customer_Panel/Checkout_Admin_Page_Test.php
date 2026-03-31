<?php
/**
 * Tests for Checkout_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages\Customer_Panel;

use WP_UnitTestCase;

/**
 * Test class for Checkout_Admin_Page.
 */
class Checkout_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Checkout_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->page = new Checkout_Admin_Page();
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

		$this->assertEquals('wu-checkout', $property->getValue($this->page));
	}

	/**
	 * Test type is submenu.
	 */
	public function test_type(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('type');
		$property->setAccessible(true);

		$this->assertEquals('submenu', $property->getValue($this->page));
	}

	/**
	 * Test highlight_menu_slug is account.
	 */
	public function test_highlight_menu_slug(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('account', $property->getValue($this->page));
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
	 * Test supported_panels contains user_admin_menu and admin_menu.
	 */
	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('user_admin_menu', $panels);
		$this->assertArrayHasKey('admin_menu', $panels);
		$this->assertEquals('wu_manage_membership', $panels['user_admin_menu']);
		$this->assertEquals('wu_manage_membership', $panels['admin_menu']);
	}

	/**
	 * Test hide_admin_notices is true.
	 */
	public function test_hide_admin_notices(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('hide_admin_notices');
		$property->setAccessible(true);

		$this->assertTrue($property->getValue($this->page));
	}

	/**
	 * Test fold_menu is true.
	 */
	public function test_fold_menu(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('fold_menu');
		$property->setAccessible(true);

		$this->assertTrue($property->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * get_title returns 'Checkout'.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Checkout', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns 'Checkout'.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Checkout', $title);
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	/**
	 * register_scripts fires wu_checkout_scripts action.
	 */
	public function test_register_scripts_fires_action(): void {

		$action_fired = false;

		add_action(
			'wu_checkout_scripts',
			function () use (&$action_fired) {
				$action_fired = true;
			}
		);

		$this->page->register_scripts();

		$this->assertTrue($action_fired);
	}

	// -------------------------------------------------------------------------
	// page_loaded()
	// -------------------------------------------------------------------------

	/**
	 * page_loaded fires wu_setup_checkout action.
	 */
	public function test_page_loaded_fires_action(): void {

		$action_fired = false;

		add_action(
			'wu_setup_checkout',
			function () use (&$action_fired) {
				$action_fired = true;
			}
		);

		$this->page->page_loaded();

		$this->assertTrue($action_fired);
	}

	// -------------------------------------------------------------------------
	// get_sections()
	// -------------------------------------------------------------------------

	/**
	 * get_sections returns an array.
	 */
	public function test_get_sections_returns_array(): void {

		$sections = $this->page->get_sections();

		$this->assertIsArray($sections);
	}

	/**
	 * get_sections contains plan section.
	 */
	public function test_get_sections_contains_plan(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('plan', $sections);
		$this->assertArrayHasKey('title', $sections['plan']);
		$this->assertArrayHasKey('view', $sections['plan']);
	}

	/**
	 * get_sections plan title is correct.
	 */
	public function test_get_sections_plan_title(): void {

		$sections = $this->page->get_sections();

		$this->assertEquals('Change Membership', $sections['plan']['title']);
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * output() renders without throwing.
	 */
	public function test_output_renders(): void {

		set_current_screen('dashboard');

		ob_start();
		$this->page->output();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * register_widgets() does not throw when called with a valid screen.
	 */
	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}
}
