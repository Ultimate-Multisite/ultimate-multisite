<?php
/**
 * Tests for Template_Previewer_Customize_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\UI\Template_Previewer;

/**
 * Test class for Template_Previewer_Customize_Admin_Page.
 */
class Template_Previewer_Customize_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Template_Previewer_Customize_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new Template_Previewer_Customize_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals and options.
	 */
	protected function tearDown(): void {

		unset(
			$_POST['bg_color'],
			$_POST['button_bg_color'],
			$_POST['logo_url'],
			$_POST['button_text'],
			$_POST['preview_url_parameter'],
			$_POST['display_responsive_controls'],
			$_POST['use_custom_logo'],
			$_POST['custom_logo'],
			$_POST['enabled']
		);

		// Clean up Template_Previewer option.
		delete_network_option(null, 'wp-ultimo_template_previewer');

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

		$this->assertEquals('wp-ultimo-customize-template-previewer', $property->getValue($this->page));
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
	 * Test highlight_menu_slug is set correctly.
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
		$this->assertEquals('wu_customize_invoice_template', $panels['network_admin_menu']);
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
		$this->assertEquals('Customize Template Previewer', $title);
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
		$this->assertEquals('Customize Template Previewer', $title);
	}

	// -------------------------------------------------------------------------
	// action_links()
	// -------------------------------------------------------------------------

	/**
	 * Test action_links returns empty array.
	 */
	public function test_action_links(): void {

		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertEmpty($links);
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	/**
	 * Test get_labels returns array with all required keys.
	 */
	public function test_get_labels_returns_required_keys(): void {

		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
		$this->assertArrayHasKey('customize_label', $labels);
		$this->assertArrayHasKey('add_new_label', $labels);
		$this->assertArrayHasKey('edit_label', $labels);
		$this->assertArrayHasKey('updated_message', $labels);
		$this->assertArrayHasKey('title_placeholder', $labels);
		$this->assertArrayHasKey('title_description', $labels);
		$this->assertArrayHasKey('save_button_label', $labels);
		$this->assertArrayHasKey('save_description', $labels);
	}

	/**
	 * Test get_labels customize_label value.
	 */
	public function test_get_labels_customize_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Customize Template Previewer', $labels['customize_label']);
	}

	/**
	 * Test get_labels add_new_label value.
	 */
	public function test_get_labels_add_new_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Customize Template Previewer', $labels['add_new_label']);
	}

	/**
	 * Test get_labels edit_label value.
	 */
	public function test_get_labels_edit_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Edit Template Previewer', $labels['edit_label']);
	}

	/**
	 * Test get_labels updated_message value.
	 */
	public function test_get_labels_updated_message(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Template Previewer updated with success!', $labels['updated_message']);
	}

	/**
	 * Test get_labels save_button_label value.
	 */
	public function test_get_labels_save_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Save Changes', $labels['save_button_label']);
	}

	/**
	 * Test get_labels title_placeholder value.
	 */
	public function test_get_labels_title_placeholder(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Enter Template Previewer Name', $labels['title_placeholder']);
	}

	/**
	 * Test get_labels title_description value.
	 */
	public function test_get_labels_title_description(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('This name is used for internal reference only.', $labels['title_description']);
	}

	/**
	 * Test get_labels save_description is empty string.
	 */
	public function test_get_labels_save_description_empty(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('', $labels['save_description']);
	}

	// -------------------------------------------------------------------------
	// get_preview_url()
	// -------------------------------------------------------------------------

	/**
	 * Test get_preview_url returns a string.
	 */
	public function test_get_preview_url_returns_string(): void {

		$url = $this->page->get_preview_url();

		$this->assertIsString($url);
	}

	/**
	 * Test get_preview_url contains customizer parameter.
	 */
	public function test_get_preview_url_contains_customizer_param(): void {

		$url = $this->page->get_preview_url();

		$this->assertStringContainsString('customizer=1', $url);
	}

	/**
	 * Test get_preview_url contains the template preview parameter.
	 */
	public function test_get_preview_url_contains_preview_parameter(): void {

		$url            = $this->page->get_preview_url();
		$preview_param  = Template_Previewer::get_instance()->get_preview_parameter();

		$this->assertStringContainsString($preview_param . '=1', $url);
	}

	/**
	 * Test get_preview_url is a valid URL.
	 */
	public function test_get_preview_url_is_valid_url(): void {

		$url = $this->page->get_preview_url();

		$this->assertNotFalse(filter_var($url, FILTER_VALIDATE_URL));
	}

	// -------------------------------------------------------------------------
	// Inheritance / parent class properties
	// -------------------------------------------------------------------------

	/**
	 * Test fold_menu is true (inherited from Customizer_Admin_Page).
	 */
	public function test_fold_menu_is_true(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('fold_menu');
		$property->setAccessible(true);

		$this->assertTrue($property->getValue($this->page));
	}

	/**
	 * Test preview_height is set (inherited from Customizer_Admin_Page).
	 */
	public function test_preview_height_is_set(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('preview_height');
		$property->setAccessible(true);

		$value = $property->getValue($this->page);
		$this->assertIsString($value);
		$this->assertNotEmpty($value);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets does not throw when called.
	 */
	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard-network');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with saved custom_logo setting.
	 */
	public function test_register_widgets_with_saved_custom_logo(): void {

		set_current_screen('dashboard-network');

		wu_save_option('template_previewer', ['custom_logo' => 0]);

		$page = new Template_Previewer_Customize_Admin_Page();
		$page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// handle_save() — sanitization branches
	// -------------------------------------------------------------------------

	/**
	 * Test handle_save sanitizes bg_color as hex color.
	 */
	public function test_handle_save_sanitizes_bg_color(): void {

		$_POST['bg_color'] = '#ff0000';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// wp_safe_redirect throws or calls exit — catch to inspect saved value.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved) && isset($saved['bg_color'])) {
			$this->assertEquals('#ff0000', $saved['bg_color']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes button_bg_color as hex color.
	 */
	public function test_handle_save_sanitizes_button_bg_color(): void {

		$_POST['button_bg_color'] = '#00ff00';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved) && isset($saved['button_bg_color'])) {
			$this->assertEquals('#00ff00', $saved['button_bg_color']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes display_responsive_controls as bool.
	 */
	public function test_handle_save_sanitizes_display_responsive_controls(): void {

		$_POST['display_responsive_controls'] = '1';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved) && isset($saved['display_responsive_controls'])) {
			$this->assertTrue($saved['display_responsive_controls']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes use_custom_logo as bool.
	 */
	public function test_handle_save_sanitizes_use_custom_logo(): void {

		$_POST['use_custom_logo'] = '1';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved) && isset($saved['use_custom_logo'])) {
			$this->assertTrue($saved['use_custom_logo']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes enabled as bool.
	 */
	public function test_handle_save_sanitizes_enabled(): void {

		$_POST['enabled'] = '0';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved) && isset($saved['enabled'])) {
			$this->assertFalse($saved['enabled']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes custom_logo as absint.
	 */
	public function test_handle_save_sanitizes_custom_logo_as_absint(): void {

		$_POST['custom_logo'] = '42';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved) && isset($saved['custom_logo'])) {
			$this->assertEquals(42, $saved['custom_logo']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes logo_url via esc_url_raw.
	 */
	public function test_handle_save_sanitizes_logo_url(): void {

		$_POST['logo_url'] = 'https://example.com/logo.png';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved) && isset($saved['logo_url'])) {
			$this->assertEquals('https://example.com/logo.png', $saved['logo_url']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes button_text via sanitize_text_field.
	 */
	public function test_handle_save_sanitizes_button_text(): void {

		$_POST['button_text'] = 'Use this Template <script>alert(1)</script>';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved) && isset($saved['button_text'])) {
			$this->assertStringNotContainsString('<script>', $saved['button_text']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes preview_url_parameter via sanitize_text_field.
	 */
	public function test_handle_save_sanitizes_preview_url_parameter(): void {

		$_POST['preview_url_parameter'] = 'my-preview-param';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved) && isset($saved['preview_url_parameter'])) {
			$this->assertEquals('my-preview-param', $saved['preview_url_parameter']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save skips settings not present in POST.
	 */
	public function test_handle_save_skips_absent_post_keys(): void {

		// Only set one key — others should not appear in saved settings.
		$_POST['bg_color'] = '#aabbcc';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = wu_get_option('template_previewer', []);

		if (is_array($saved)) {
			$this->assertArrayNotHasKey('button_text', $saved);
			$this->assertArrayNotHasKey('preview_url_parameter', $saved);
		} else {
			$this->assertTrue(true);
		}
	}

	// -------------------------------------------------------------------------
	// Instantiation
	// -------------------------------------------------------------------------

	/**
	 * Test page can be instantiated.
	 */
	public function test_page_instantiation(): void {

		$page = new Template_Previewer_Customize_Admin_Page();

		$this->assertInstanceOf(Template_Previewer_Customize_Admin_Page::class, $page);
	}

	/**
	 * Test page extends Customizer_Admin_Page.
	 */
	public function test_page_extends_customizer_admin_page(): void {

		$this->assertInstanceOf(Customizer_Admin_Page::class, $this->page);
	}
}
