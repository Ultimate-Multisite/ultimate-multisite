<?php
/**
 * Tests for Shortcodes_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\UI\Base_Element;
use WP_Ultimo\UI\Field;

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
	 * Get_title returns Available Shortcodes.
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
	 * Get_menu_title returns Available Shortcodes.
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
	 * Get_submenu_title returns Dashboard.
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
	 * Get_data returns an array.
	 */
	public function test_get_data_returns_array(): void {

		$data = $this->page->get_data();

		$this->assertIsArray($data);
	}

	/**
	 * Get_data array items have required keys.
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
	 * Get_data params is an array.
	 */
	public function test_get_data_params_is_array(): void {

		$data = $this->page->get_data();

		if (empty($data)) {
			$this->markTestSkipped('No shortcode elements registered');
		}

		$first_item = reset($data);

		$this->assertIsArray($first_item['params']);
	}

	/**
	 * Callable select options receive the field instance used by normal form rendering.
	 */
	public function test_get_data_passes_field_to_callable_select_options(): void {

		$resolved_field = null;
		$options        = function (Field $field) use (&$resolved_field) {
			$resolved_field = $field;

			return [
				'first'  => 'First',
				'second' => 'Second',
			];
		};

		$element = new class($options) extends Base_Element {

			private $options;

			public function __construct($options) {
				$this->id      = 'callable-options-test';
				$this->options = $options;
			}

			public function get_icon($context = 'block') {
				return '';
			}

			public function get_title() {
				return 'Callable Options Test';
			}

			public function get_description() {
				return '';
			}

			public function fields() {
				return [
					'destination' => [
						'type'    => 'select',
						'options' => $this->options,
					],
				];
			}

			public function keywords() {
				return [];
			}

			public function defaults() {
				return [];
			}

			public function output($atts, $content = null) {
			}
		};

		$property = new \ReflectionProperty(Base_Element::class, 'public_elements');
		$property->setAccessible(true);
		$original_elements = $property->getValue();

		try {
			Base_Element::register_public_element($element);

			$data      = $this->page->get_data();
			$test_data = end($data);

			$this->assertInstanceOf(Field::class, $resolved_field);
			$this->assertSame('first | second', $test_data['params']['destination']['options']);
		} finally {
			$property->setValue(null, $original_elements);
		}
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * Output renders template.
	 */
	public function test_output_renders_template(): void {

		set_current_screen('dashboard-network');

		ob_start();
		$this->page->output();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}
}
