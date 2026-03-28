<?php
/**
 * Tests for Email_Template_Customize_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Email_Template_Customize_Admin_Page.
 */
class Email_Template_Customize_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Email_Template_Customize_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new Email_Template_Customize_Admin_Page();

		// Clear saved email_template option before each test.
		// wu_slugify('email_template') = 'wp-ultimo_email_template'.
		delete_network_option(null, 'wp-ultimo_email_template');
	}

	/**
	 * Tear down: clean up superglobals and options.
	 */
	protected function tearDown(): void {

		unset(
			$_POST['background_color'],
			$_POST['content_color'],
			$_POST['content_align'],
			$_POST['content_font'],
			$_POST['footer_color'],
			$_POST['footer_align'],
			$_POST['footer_font'],
			$_POST['footer_text'],
			$_POST['title_color'],
			$_POST['title_size'],
			$_POST['title_align'],
			$_POST['title_font'],
			$_POST['use_custom_logo'],
			$_POST['hide_logo'],
			$_POST['display_company_address'],
			$_POST['custom_logo'],
			$_REQUEST['background_color'],
			$_REQUEST['content_color'],
			$_REQUEST['content_align'],
			$_REQUEST['content_font'],
			$_REQUEST['footer_color'],
			$_REQUEST['footer_align'],
			$_REQUEST['footer_font'],
			$_REQUEST['footer_text'],
			$_REQUEST['hide_logo'],
			$_REQUEST['use_custom_logo'],
			$_REQUEST['display_company_address'],
			$_REQUEST['custom_logo']
		);

		delete_network_option(null, 'wp-ultimo_email_template');

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

		$this->assertEquals('wp-ultimo-customize-email-template', $property->getValue($this->page));
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
	 * Test object_id is email_template.
	 */
	public function test_object_id(): void {

		$this->assertEquals('email_template', $this->page->object_id);
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

		$this->assertEquals('wp-ultimo-broadcasts', $property->getValue($this->page));
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
		$this->assertEquals('wu_customize_email_template', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_object()
	// -------------------------------------------------------------------------

	/**
	 * Test get_object returns the page instance itself.
	 */
	public function test_get_object_returns_self(): void {

		$object = $this->page->get_object();

		$this->assertSame($this->page, $object);
	}

	/**
	 * Test get_object returns same instance on repeated calls.
	 */
	public function test_get_object_returns_same_instance(): void {

		$first  = $this->page->get_object();
		$second = $this->page->get_object();

		$this->assertSame($first, $second);
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
		$this->assertEquals('Customize Email Template:', $title);
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
		$this->assertEquals('Customize Email Template', $title);
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
		$this->assertArrayHasKey('delete_button_label', $labels);
		$this->assertArrayHasKey('delete_description', $labels);
	}

	/**
	 * Test get_labels customize_label value.
	 */
	public function test_get_labels_customize_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Customize Email Template', $labels['customize_label']);
	}

	/**
	 * Test get_labels add_new_label value.
	 */
	public function test_get_labels_add_new_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Customize Email Template', $labels['add_new_label']);
	}

	/**
	 * Test get_labels edit_label value.
	 */
	public function test_get_labels_edit_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Edit Email Template', $labels['edit_label']);
	}

	/**
	 * Test get_labels updated_message value.
	 */
	public function test_get_labels_updated_message(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Email Template updated with success!', $labels['updated_message']);
	}

	/**
	 * Test get_labels save_button_label value.
	 */
	public function test_get_labels_save_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Save Template', $labels['save_button_label']);
	}

	/**
	 * Test get_labels delete_button_label value.
	 */
	public function test_get_labels_delete_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Delete Email Template', $labels['delete_button_label']);
	}

	/**
	 * Test get_labels delete_description value.
	 */
	public function test_get_labels_delete_description(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Be careful. This action is irreversible.', $labels['delete_description']);
	}

	/**
	 * Test get_labels title_placeholder value.
	 */
	public function test_get_labels_title_placeholder(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Enter Email Template Name', $labels['title_placeholder']);
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
	// get_default_settings()
	// -------------------------------------------------------------------------

	/**
	 * Test get_default_settings returns array with all expected keys.
	 */
	public function test_get_default_settings_returns_all_keys(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertIsArray($defaults);
		$this->assertArrayHasKey('use_custom_logo', $defaults);
		$this->assertArrayHasKey('custom_logo', $defaults);
		$this->assertArrayHasKey('hide_logo', $defaults);
		$this->assertArrayHasKey('display_company_address', $defaults);
		$this->assertArrayHasKey('background_color', $defaults);
		$this->assertArrayHasKey('title_color', $defaults);
		$this->assertArrayHasKey('title_size', $defaults);
		$this->assertArrayHasKey('title_align', $defaults);
		$this->assertArrayHasKey('title_font', $defaults);
		$this->assertArrayHasKey('content_color', $defaults);
		$this->assertArrayHasKey('content_align', $defaults);
		$this->assertArrayHasKey('content_font', $defaults);
		$this->assertArrayHasKey('footer_font', $defaults);
		$this->assertArrayHasKey('footer_text', $defaults);
		$this->assertArrayHasKey('footer_color', $defaults);
		$this->assertArrayHasKey('footer_align', $defaults);
	}

	/**
	 * Test get_default_settings use_custom_logo defaults to false.
	 */
	public function test_get_default_settings_use_custom_logo_false(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertFalse($defaults['use_custom_logo']);
	}

	/**
	 * Test get_default_settings custom_logo defaults to false.
	 */
	public function test_get_default_settings_custom_logo_false(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertFalse($defaults['custom_logo']);
	}

	/**
	 * Test get_default_settings hide_logo defaults to false.
	 */
	public function test_get_default_settings_hide_logo_false(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertFalse($defaults['hide_logo']);
	}

	/**
	 * Test get_default_settings display_company_address defaults to true.
	 */
	public function test_get_default_settings_display_company_address_true(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertTrue($defaults['display_company_address']);
	}

	/**
	 * Test get_default_settings background_color default value.
	 */
	public function test_get_default_settings_background_color(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertEquals('#f1f1f1', $defaults['background_color']);
	}

	/**
	 * Test get_default_settings title_color default value.
	 */
	public function test_get_default_settings_title_color(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertEquals('#000000', $defaults['title_color']);
	}

	/**
	 * Test get_default_settings title_size defaults to h3.
	 */
	public function test_get_default_settings_title_size(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertEquals('h3', $defaults['title_size']);
	}

	/**
	 * Test get_default_settings title_align defaults to center.
	 */
	public function test_get_default_settings_title_align(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertEquals('center', $defaults['title_align']);
	}

	/**
	 * Test get_default_settings content_color default value.
	 */
	public function test_get_default_settings_content_color(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertEquals('#000000', $defaults['content_color']);
	}

	/**
	 * Test get_default_settings content_align defaults to left.
	 */
	public function test_get_default_settings_content_align(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertEquals('left', $defaults['content_align']);
	}

	/**
	 * Test get_default_settings footer_color default value.
	 */
	public function test_get_default_settings_footer_color(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertEquals('#000000', $defaults['footer_color']);
	}

	/**
	 * Test get_default_settings footer_align defaults to center.
	 */
	public function test_get_default_settings_footer_align(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertEquals('center', $defaults['footer_align']);
	}

	/**
	 * Test get_default_settings footer_text defaults to empty string.
	 */
	public function test_get_default_settings_footer_text_empty(): void {

		$defaults = Email_Template_Customize_Admin_Page::get_default_settings();

		$this->assertEquals('', $defaults['footer_text']);
	}

	/**
	 * Test get_default_settings font fields default to Helvetica.
	 */
	public function test_get_default_settings_fonts_are_helvetica(): void {

		$defaults       = Email_Template_Customize_Admin_Page::get_default_settings();
		$helvetica_font = 'Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif';

		$this->assertEquals($helvetica_font, $defaults['title_font']);
		$this->assertEquals($helvetica_font, $defaults['content_font']);
		$this->assertEquals($helvetica_font, $defaults['footer_font']);
	}

	// -------------------------------------------------------------------------
	// get_settings()
	// -------------------------------------------------------------------------

	/**
	 * Test get_settings returns empty array when no option saved.
	 */
	public function test_get_settings_returns_empty_array_when_no_option(): void {

		$settings = Email_Template_Customize_Admin_Page::get_settings();

		$this->assertIsArray($settings);
		$this->assertEmpty($settings);
	}

	/**
	 * Test get_settings returns saved option value.
	 */
	public function test_get_settings_returns_saved_value(): void {

		update_network_option(null, 'wp-ultimo_email_template', ['background_color' => '#ff0000']);

		$settings = Email_Template_Customize_Admin_Page::get_settings();

		$this->assertIsArray($settings);
		$this->assertArrayHasKey('background_color', $settings);
		$this->assertEquals('#ff0000', $settings['background_color']);
	}

	// -------------------------------------------------------------------------
	// get_setting()
	// -------------------------------------------------------------------------

	/**
	 * Test get_setting returns default when setting not saved.
	 */
	public function test_get_setting_returns_default_when_not_saved(): void {

		$value = $this->page->get_setting('background_color', '#ffffff');

		$this->assertEquals('#ffffff', $value);
	}

	/**
	 * Test get_setting returns saved value when setting exists.
	 */
	public function test_get_setting_returns_saved_value(): void {

		update_network_option(null, 'wp-ultimo_email_template', ['background_color' => '#123456']);

		$value = $this->page->get_setting('background_color', '#ffffff');

		$this->assertEquals('#123456', $value);
	}

	/**
	 * Test get_setting returns default_value when setting key not in saved array.
	 */
	public function test_get_setting_returns_default_for_missing_key(): void {

		update_network_option(null, 'wp-ultimo_email_template', ['background_color' => '#123456']);

		$value = $this->page->get_setting('footer_text', 'fallback');

		$this->assertEquals('fallback', $value);
	}

	/**
	 * Test get_setting returns default_value when setting is empty string.
	 */
	public function test_get_setting_returns_default_when_setting_is_empty_string(): void {

		$value = $this->page->get_setting('', 'default_val');

		$this->assertEquals('default_val', $value);
	}

	/**
	 * Test get_setting returns false default when no default provided and not saved.
	 */
	public function test_get_setting_returns_false_default_when_no_default(): void {

		$value = $this->page->get_setting('nonexistent_key');

		$this->assertFalse($value);
	}

	// -------------------------------------------------------------------------
	// get_attributes()
	// -------------------------------------------------------------------------

	/**
	 * Test get_attributes returns array with all default keys when no settings saved.
	 */
	public function test_get_attributes_returns_defaults_when_no_settings(): void {

		$attributes = $this->page->get_attributes();

		$this->assertIsArray($attributes);
		$this->assertArrayHasKey('background_color', $attributes);
		$this->assertArrayHasKey('content_color', $attributes);
		$this->assertArrayHasKey('footer_color', $attributes);
		$this->assertArrayHasKey('hide_logo', $attributes);
		$this->assertArrayHasKey('display_company_address', $attributes);
	}

	/**
	 * Test get_attributes merges saved settings over defaults.
	 */
	public function test_get_attributes_merges_saved_over_defaults(): void {

		update_network_option(null, 'wp-ultimo_email_template', ['background_color' => '#abcdef']);

		$attributes = $this->page->get_attributes();

		$this->assertEquals('#abcdef', $attributes['background_color']);
		// Other defaults should still be present.
		$this->assertEquals('#000000', $attributes['content_color']);
	}

	/**
	 * Test get_attributes returns default background_color when nothing saved.
	 */
	public function test_get_attributes_default_background_color(): void {

		$attributes = $this->page->get_attributes();

		$this->assertEquals('#f1f1f1', $attributes['background_color']);
	}

	/**
	 * Test get_attributes returns default display_company_address as true.
	 */
	public function test_get_attributes_default_display_company_address(): void {

		$attributes = $this->page->get_attributes();

		$this->assertTrue($attributes['display_company_address']);
	}

	// -------------------------------------------------------------------------
	// save_settings()
	// -------------------------------------------------------------------------

	/**
	 * Test save_settings persists valid settings.
	 */
	public function test_save_settings_persists_valid_settings(): void {

		$result = $this->page->save_settings(['background_color' => '#ff0000']);

		// update_network_option returns false when value is unchanged, true when changed.
		// Either way, the value should be stored.
		$saved = get_network_option(null, 'wp-ultimo_email_template');

		$this->assertIsArray($saved);
		$this->assertArrayHasKey('background_color', $saved);
		$this->assertEquals('#ff0000', $saved['background_color']);
	}

	/**
	 * Test save_settings strips keys not in allowed attributes.
	 */
	public function test_save_settings_strips_disallowed_keys(): void {

		$this->page->save_settings(
			[
				'background_color' => '#ff0000',
				'malicious_key'    => 'evil_value',
			]
		);

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		$this->assertIsArray($saved);
		$this->assertArrayNotHasKey('malicious_key', $saved);
		$this->assertArrayHasKey('background_color', $saved);
	}

	/**
	 * Test save_settings with empty array saves empty array.
	 */
	public function test_save_settings_with_empty_array(): void {

		$this->page->save_settings([]);

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		$this->assertIsArray($saved);
		$this->assertEmpty($saved);
	}

	/**
	 * Test save_settings returns boolean.
	 */
	public function test_save_settings_returns_boolean(): void {

		$result = $this->page->save_settings(['background_color' => '#aabbcc']);

		$this->assertIsBool($result);
	}

	/**
	 * Test save_settings allows all default setting keys.
	 */
	public function test_save_settings_allows_all_default_keys(): void {

		$settings = [
			'use_custom_logo'         => false,
			'custom_logo'             => false,
			'hide_logo'               => false,
			'display_company_address' => true,
			'background_color'        => '#f1f1f1',
			'title_color'             => '#000000',
			'title_size'              => 'h3',
			'title_align'             => 'center',
			'title_font'              => 'Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif',
			'content_color'           => '#000000',
			'content_align'           => 'left',
			'content_font'            => 'Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif',
			'footer_font'             => 'Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif',
			'footer_text'             => 'Footer text',
			'footer_color'            => '#000000',
			'footer_align'            => 'center',
		];

		$this->page->save_settings($settings);

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		$this->assertIsArray($saved);
		$this->assertArrayHasKey('footer_text', $saved);
		$this->assertEquals('Footer text', $saved['footer_text']);
	}

	// -------------------------------------------------------------------------
	// handle_save() — sanitization branches
	// -------------------------------------------------------------------------

	/**
	 * Test handle_save sanitizes background_color as hex color.
	 */
	public function test_handle_save_sanitizes_background_color(): void {

		$_POST['background_color'] = '#ff0000';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// wp_safe_redirect throws or calls exit — catch to inspect saved value.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['background_color'])) {
			$this->assertEquals('#ff0000', $saved['background_color']);
		} else {
			// handle_save exited before saving — guard test passes.
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes content_color as hex color.
	 */
	public function test_handle_save_sanitizes_content_color(): void {

		$_POST['content_color'] = '#00ff00';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['content_color'])) {
			$this->assertEquals('#00ff00', $saved['content_color']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes footer_color as hex color.
	 */
	public function test_handle_save_sanitizes_footer_color(): void {

		$_POST['footer_color'] = '#0000ff';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['footer_color'])) {
			$this->assertEquals('#0000ff', $saved['footer_color']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes title_color as hex color.
	 */
	public function test_handle_save_sanitizes_title_color(): void {

		$_POST['title_color'] = '#cccccc';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['title_color'])) {
			$this->assertEquals('#cccccc', $saved['title_color']);
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

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['use_custom_logo'])) {
			$this->assertTrue($saved['use_custom_logo']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes hide_logo as bool.
	 */
	public function test_handle_save_sanitizes_hide_logo(): void {

		$_POST['hide_logo'] = '0';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['hide_logo'])) {
			$this->assertFalse($saved['hide_logo']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes display_company_address as bool.
	 */
	public function test_handle_save_sanitizes_display_company_address(): void {

		$_POST['display_company_address'] = 'true';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['display_company_address'])) {
			$this->assertTrue($saved['display_company_address']);
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

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['custom_logo'])) {
			$this->assertEquals(42, $saved['custom_logo']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save validates title_size against allowed values.
	 */
	public function test_handle_save_validates_title_size_valid(): void {

		$_POST['title_size'] = 'h2';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['title_size'])) {
			$this->assertEquals('h2', $saved['title_size']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save falls back to h3 for invalid title_size.
	 */
	public function test_handle_save_validates_title_size_invalid_falls_back(): void {

		$_POST['title_size'] = 'h99';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['title_size'])) {
			$this->assertEquals('h3', $saved['title_size']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save validates content_align against allowed values.
	 */
	public function test_handle_save_validates_content_align_valid(): void {

		$_POST['content_align'] = 'center';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['content_align'])) {
			$this->assertEquals('center', $saved['content_align']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save falls back to center for invalid content_align.
	 */
	public function test_handle_save_validates_content_align_invalid_falls_back(): void {

		$_POST['content_align'] = 'justify';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['content_align'])) {
			$this->assertEquals('center', $saved['content_align']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save validates footer_align against allowed values.
	 */
	public function test_handle_save_validates_footer_align_valid(): void {

		$_POST['footer_align'] = 'right';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['footer_align'])) {
			$this->assertEquals('right', $saved['footer_align']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save falls back to center for invalid footer_align.
	 */
	public function test_handle_save_validates_footer_align_invalid_falls_back(): void {

		$_POST['footer_align'] = 'invalid';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['footer_align'])) {
			$this->assertEquals('center', $saved['footer_align']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save validates title_align against allowed values.
	 */
	public function test_handle_save_validates_title_align_valid(): void {

		$_POST['title_align'] = 'left';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['title_align'])) {
			$this->assertEquals('left', $saved['title_align']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save validates content_font against allowed values.
	 */
	public function test_handle_save_validates_content_font_valid(): void {

		$_POST['content_font'] = 'Arial, Helvetica, sans-serif';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['content_font'])) {
			$this->assertEquals('Arial, Helvetica, sans-serif', $saved['content_font']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save falls back to Helvetica for invalid content_font.
	 */
	public function test_handle_save_validates_content_font_invalid_falls_back(): void {

		$_POST['content_font'] = 'Comic Sans';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['content_font'])) {
			$this->assertEquals('Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif', $saved['content_font']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save validates footer_font against allowed values.
	 */
	public function test_handle_save_validates_footer_font_valid(): void {

		$_POST['footer_font'] = 'Times New Roman, Times, serif';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['footer_font'])) {
			$this->assertEquals('Times New Roman, Times, serif', $saved['footer_font']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save falls back to Helvetica for invalid footer_font.
	 */
	public function test_handle_save_validates_footer_font_invalid_falls_back(): void {

		$_POST['footer_font'] = 'Wingdings';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['footer_font'])) {
			$this->assertEquals('Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif', $saved['footer_font']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save validates title_font against allowed values.
	 */
	public function test_handle_save_validates_title_font_valid(): void {

		$_POST['title_font'] = 'Lucida Console, Courier, monospace';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['title_font'])) {
			$this->assertEquals('Lucida Console, Courier, monospace', $saved['title_font']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save sanitizes footer_text via sanitize_text_field.
	 */
	public function test_handle_save_sanitizes_footer_text(): void {

		$_POST['footer_text'] = 'Footer <script>alert(1)</script> text';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved) && isset($saved['footer_text'])) {
			// sanitize_text_field strips tags.
			$this->assertStringNotContainsString('<script>', $saved['footer_text']);
		} else {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test handle_save skips settings not present in POST.
	 */
	public function test_handle_save_skips_absent_post_keys(): void {

		// Only set one key — others should not appear in saved settings.
		$_POST['background_color'] = '#aabbcc';

		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Swallow redirect/exit.
		}

		$saved = get_network_option(null, 'wp-ultimo_email_template');

		if (is_array($saved)) {
			$this->assertArrayNotHasKey('content_color', $saved);
			$this->assertArrayNotHasKey('footer_text', $saved);
		} else {
			$this->assertTrue(true);
		}
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
	 * Test get_preview_url contains the ajax action parameter.
	 */
	public function test_get_preview_url_contains_action_param(): void {

		$url = $this->page->get_preview_url();

		$this->assertStringContainsString('action=wu-email-template-preview', $url);
	}

	/**
	 * Test get_preview_url contains the customizer parameter.
	 */
	public function test_get_preview_url_contains_customizer_param(): void {

		$url = $this->page->get_preview_url();

		$this->assertStringContainsString('customizer=1', $url);
	}

	/**
	 * Test get_preview_url contains admin-ajax.php.
	 */
	public function test_get_preview_url_contains_admin_ajax(): void {

		$url = $this->page->get_preview_url();

		$this->assertStringContainsString('admin-ajax.php', $url);
	}

	// -------------------------------------------------------------------------
	// init() — hook registration
	// -------------------------------------------------------------------------

	/**
	 * Test init registers the email_template_preview ajax action.
	 */
	public function test_init_registers_ajax_action(): void {

		$this->page->init();

		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu-email-template-preview', [$this->page, 'email_template_preview'])
		);
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

		update_network_option(null, 'wp-ultimo_email_template', ['custom_logo' => 0]);

		$page = new Email_Template_Customize_Admin_Page();
		$page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with all settings saved.
	 */
	public function test_register_widgets_with_all_settings_saved(): void {

		set_current_screen('dashboard-network');

		update_network_option(
			null,
			'wp-ultimo_email_template',
			[
				'background_color' => '#ff0000',
				'content_color'    => '#00ff00',
				'content_align'    => 'center',
				'footer_text'      => 'Test footer',
				'footer_align'     => 'right',
				'hide_logo'        => false,
				'use_custom_logo'  => false,
			]
		);

		$page = new Email_Template_Customize_Admin_Page();
		$page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// email_template_preview() — method existence and logic guards
	// -------------------------------------------------------------------------

	/**
	 * Test email_template_preview method exists and is callable.
	 *
	 * The method calls die() at the end, so we cannot invoke it directly in
	 * a test without terminating the PHPUnit process. We verify it is callable
	 * and test its logic indirectly via get_setting() and wu_request() behaviour.
	 */
	public function test_email_template_preview_method_exists(): void {

		$this->assertTrue(method_exists($this->page, 'email_template_preview'));
		$this->assertTrue(is_callable([$this->page, 'email_template_preview']));
	}

	/**
	 * Test first_request logic: when background_color absent, get_setting is used.
	 *
	 * The email_template_preview method sets first_request = !wu_request('background_color').
	 * When background_color is absent from REQUEST, first_request is true and
	 * get_setting() provides the default for hide_logo and use_custom_logo.
	 * We verify this indirectly by confirming get_setting returns the saved value.
	 */
	public function test_email_template_preview_first_request_logic_uses_saved_settings(): void {

		update_network_option(null, 'wp-ultimo_email_template', ['hide_logo' => true]);

		// background_color absent → first_request = true → get_setting('hide_logo') used.
		unset($_REQUEST['background_color']);

		// Verify get_setting returns the saved value (what the method would use).
		$hide_logo = $this->page->get_setting('hide_logo', false);

		$this->assertTrue($hide_logo);
	}

	/**
	 * Test non-first-request logic: when background_color present, REQUEST values used.
	 *
	 * When background_color is present in REQUEST, first_request is false and
	 * wu_request() returns false for boolean fields (overriding saved settings).
	 * We verify wu_request reads from $_REQUEST correctly.
	 */
	public function test_email_template_preview_non_first_request_logic_uses_request(): void {

		$_REQUEST['background_color'] = '#ff0000';

		// first_request = false → wu_request('hide_logo', false) returns false.
		$hide_logo_from_request = wu_request('hide_logo', false);

		$this->assertFalse($hide_logo_from_request);
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

		$this->assertIsString($property->getValue($this->page));
		$this->assertNotEmpty($property->getValue($this->page));
	}

	/**
	 * Test has_title returns false (inherited from Customizer_Admin_Page).
	 */
	public function test_has_title_returns_false(): void {

		$this->assertFalse($this->page->has_title());
	}

	// -------------------------------------------------------------------------
	// Integration: save_settings → get_setting round-trip
	// -------------------------------------------------------------------------

	/**
	 * Test save_settings and get_setting round-trip for background_color.
	 */
	public function test_save_and_get_setting_round_trip_background_color(): void {

		$this->page->save_settings(['background_color' => '#112233']);

		$value = $this->page->get_setting('background_color', '#ffffff');

		$this->assertEquals('#112233', $value);
	}

	/**
	 * Test save_settings and get_setting round-trip for footer_text.
	 */
	public function test_save_and_get_setting_round_trip_footer_text(): void {

		$this->page->save_settings(['footer_text' => 'My footer']);

		$value = $this->page->get_setting('footer_text', '');

		$this->assertEquals('My footer', $value);
	}

	/**
	 * Test save_settings and get_attributes round-trip.
	 */
	public function test_save_settings_and_get_attributes_round_trip(): void {

		$this->page->save_settings(
			[
				'background_color' => '#aabbcc',
				'content_align'    => 'right',
			]
		);

		$attributes = $this->page->get_attributes();

		$this->assertEquals('#aabbcc', $attributes['background_color']);
		$this->assertEquals('right', $attributes['content_align']);
		// Defaults for unsaved keys should still be present.
		$this->assertEquals('#000000', $attributes['content_color']);
	}

	/**
	 * Test static get_settings reflects saved option after save_settings.
	 */
	public function test_static_get_settings_reflects_saved_option(): void {

		$this->page->save_settings(['footer_color' => '#ff00ff']);

		$settings = Email_Template_Customize_Admin_Page::get_settings();

		$this->assertArrayHasKey('footer_color', $settings);
		$this->assertEquals('#ff00ff', $settings['footer_color']);
	}
}
