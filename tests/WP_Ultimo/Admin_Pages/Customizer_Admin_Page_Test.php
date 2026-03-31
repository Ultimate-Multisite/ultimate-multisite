<?php
/**
 * Tests for Customizer_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Customizer_Admin_Page.
 *
 * Tests all public methods of the abstract Customizer_Admin_Page class
 * using a concrete implementation.
 */
class Customizer_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Customizer_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		// Create a concrete implementation for testing the abstract class.
		$this->page = new class() extends Customizer_Admin_Page {

			protected $id = 'test-customizer-page';

			protected $type = 'submenu';

			protected $parent = 'none';

			protected $supported_panels = [
				'network_admin_menu' => 'manage_network',
			];

			public function get_title() {

				return 'Test Customizer Page';
			}

			public function get_menu_title() {

				return 'Test Customizer';
			}

			public function get_labels() {

				return [
					'edit_label'       => 'Edit Test',
					'add_new_label'    => 'Add Test',
					'updated_message'  => 'Test updated',
					'title_placeholder' => 'Enter title',
					'title_description' => 'Title desc',
					'save_button_label' => 'Save Test',
					'save_description'  => 'Save desc',
					'delete_button_label' => 'Delete Test',
					'delete_description' => 'Delete desc',
				];
			}

			public function get_object() {}
		};

		// Set object property to avoid redirects.
		$this->page->object = new \stdClass();
		$this->page->edit   = true;
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset($_GET['customize']);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	/**
	 * Test fold_menu is true by default.
	 */
	public function test_fold_menu_default_true(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('fold_menu');
		$property->setAccessible(true);

		$this->assertTrue($property->getValue($this->page));
	}

	/**
	 * Test preview_height is 120vh by default.
	 */
	public function test_preview_height_default(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('preview_height');
		$property->setAccessible(true);

		$this->assertEquals('120vh', $property->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// get_preview_url()
	// -------------------------------------------------------------------------

	/**
	 * get_preview_url returns a string.
	 */
	public function test_get_preview_url_returns_string(): void {

		$result = $this->page->get_preview_url();

		$this->assertIsString($result);
	}

	/**
	 * get_preview_url returns non-empty string.
	 */
	public function test_get_preview_url_non_empty(): void {

		$result = $this->page->get_preview_url();

		$this->assertNotEmpty($result);
	}

	/**
	 * get_preview_url returns site URL.
	 */
	public function test_get_preview_url_returns_site_url(): void {

		$result = $this->page->get_preview_url();

		$this->assertEquals(get_site_url(null), $result);
	}

	// -------------------------------------------------------------------------
	// page_loaded()
	// -------------------------------------------------------------------------

	/**
	 * page_loaded registers display_preview_window action.
	 */
	public function test_page_loaded_registers_preview_action(): void {

		set_current_screen('dashboard-network');

		$this->page->page_loaded();

		$screen = get_current_screen();

		$this->assertGreaterThan(
			0,
			has_action("wu_edit_{$screen->id}_after_normal", [$this->page, 'display_preview_window'])
		);
	}

	/**
	 * page_loaded does not throw.
	 */
	public function test_page_loaded_does_not_throw(): void {

		set_current_screen('dashboard-network');

		$this->page->page_loaded();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// display_preview_window()
	// -------------------------------------------------------------------------

	/**
	 * display_preview_window outputs HTML.
	 */
	public function test_display_preview_window_outputs_html(): void {

		ob_start();
		$this->page->display_preview_window();
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
	 * register_scripts enqueues wu-customizer script.
	 */
	public function test_register_scripts_enqueues_customizer(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-customizer', 'enqueued'));
	}

	/**
	 * register_scripts enqueues wp-color-picker.
	 */
	public function test_register_scripts_enqueues_color_picker(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_style_is('wp-color-picker', 'enqueued'));
		$this->assertTrue(wp_script_is('wp-color-picker', 'enqueued'));
	}

	// -------------------------------------------------------------------------
	// has_title()
	// -------------------------------------------------------------------------

	/**
	 * has_title returns false.
	 */
	public function test_has_title_returns_false(): void {

		$this->assertFalse($this->page->has_title());
	}
}
