<?php
/**
 * Tests for Migration_Alert_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Migration_Alert_Admin_Page.
 *
 * Tests all public methods of the Migration_Alert_Admin_Page class.
 */
class Migration_Alert_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Migration_Alert_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->page = new Migration_Alert_Admin_Page();
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

		$this->assertEquals('wp-ultimo-migration-alert', $property->getValue($this->page));
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
	// get_logo()
	// -------------------------------------------------------------------------

	/**
	 * get_logo returns a string.
	 */
	public function test_get_logo_returns_string(): void {

		$logo = $this->page->get_logo();

		$this->assertIsString($logo);
	}

	/**
	 * get_logo returns non-empty string.
	 */
	public function test_get_logo_non_empty(): void {

		$logo = $this->page->get_logo();

		$this->assertNotEmpty($logo);
	}

	/**
	 * get_logo contains logo.webp.
	 */
	public function test_get_logo_contains_logo_webp(): void {

		$logo = $this->page->get_logo();

		$this->assertStringContainsString('logo.webp', $logo);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * get_title returns Migration.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Migration', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns string.
	 */
	public function test_get_menu_title_returns_string(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
	}

	/**
	 * get_menu_title contains Ultimate Multisite.
	 */
	public function test_get_menu_title_contains_ultimate_multisite(): void {

		$title = $this->page->get_menu_title();

		$this->assertStringContainsString('Ultimate Multisite', $title);
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
	 * get_sections contains alert section.
	 */
	public function test_get_sections_contains_alert(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('alert', $sections);
	}

	/**
	 * get_sections alert has required keys.
	 */
	public function test_get_sections_alert_has_required_keys(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('title', $sections['alert']);
		$this->assertArrayHasKey('view', $sections['alert']);
		$this->assertArrayHasKey('handler', $sections['alert']);
	}

	/**
	 * get_sections alert title is Alert!.
	 */
	public function test_get_sections_alert_title(): void {

		$sections = $this->page->get_sections();

		$this->assertEquals('Alert!', $sections['alert']['title']);
	}

	// -------------------------------------------------------------------------
	// section_alert()
	// -------------------------------------------------------------------------

	/**
	 * section_alert outputs HTML.
	 */
	public function test_section_alert_outputs_html(): void {

		set_current_screen('dashboard-network');

		ob_start();
		$this->page->section_alert();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// handle_proceed()
	// -------------------------------------------------------------------------

	/**
	 * handle_proceed deletes network options and redirects.
	 */
	public function test_handle_proceed_deletes_options_and_redirects(): void {

		// Set up network options.
		update_network_option(null, \WP_Ultimo::NETWORK_OPTION_SETUP_FINISHED, true);
		update_network_option(null, 'wu_is_migration_done', true);

		// Expect redirect exception.
		$this->expectException(\WPDieException::class);

		$this->page->handle_proceed();
	}
}
