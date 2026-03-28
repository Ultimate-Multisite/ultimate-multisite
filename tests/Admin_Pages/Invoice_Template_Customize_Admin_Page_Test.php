<?php
/**
 * Unit tests for Invoice_Template_Customize_Admin_Page.
 *
 * Covers: class properties, init, get_title, get_menu_title, get_labels,
 * action_links, get_preview_url, register_widgets, handle_save.
 *
 * @package WP_Ultimo\Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Invoices\Invoice;

/**
 * Test suite for Invoice_Template_Customize_Admin_Page.
 */
class Invoice_Template_Customize_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * Instance under test.
	 *
	 * @var Invoice_Template_Customize_Admin_Page
	 */
	protected Invoice_Template_Customize_Admin_Page $page;

	/**
	 * Set up a fresh page instance before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->page = new Invoice_Template_Customize_Admin_Page();
	}

	/**
	 * Tear down: reset superglobals and saved invoice settings.
	 */
	public function tearDown(): void {
		$_REQUEST = [];
		$_POST    = [];
		$_GET     = [];

		// Clean up any saved invoice settings written during tests.
		wu_save_option(Invoice::KEY, []);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Class property defaults
	// -------------------------------------------------------------------------

	/**
	 * The page ID is set correctly.
	 */
	public function test_page_id_is_correct(): void {
		$ref = new \ReflectionProperty($this->page, 'id');
		$ref->setAccessible(true);

		$this->assertEquals('wp-ultimo-customize-invoice-template', $ref->getValue($this->page));
	}

	/**
	 * The page type is submenu.
	 */
	public function test_page_type_is_submenu(): void {
		$ref = new \ReflectionProperty($this->page, 'type');
		$ref->setAccessible(true);

		$this->assertEquals('submenu', $ref->getValue($this->page));
	}

	/**
	 * The object_id is invoice_template.
	 */
	public function test_object_id_is_invoice_template(): void {
		$this->assertEquals('invoice_template', $this->page->object_id);
	}

	/**
	 * The parent is 'none'.
	 */
	public function test_parent_is_none(): void {
		$ref = new \ReflectionProperty($this->page, 'parent');
		$ref->setAccessible(true);

		$this->assertEquals('none', $ref->getValue($this->page));
	}

	/**
	 * The highlight_menu_slug is wp-ultimo-settings.
	 */
	public function test_highlight_menu_slug_is_correct(): void {
		$ref = new \ReflectionProperty($this->page, 'highlight_menu_slug');
		$ref->setAccessible(true);

		$this->assertEquals('wp-ultimo-settings', $ref->getValue($this->page));
	}

	/**
	 * The supported_panels requires wu_customize_invoice_template capability.
	 */
	public function test_supported_panels_requires_correct_capability(): void {
		$ref = new \ReflectionProperty($this->page, 'supported_panels');
		$ref->setAccessible(true);

		$panels = $ref->getValue($this->page);

		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_customize_invoice_template', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title
	// -------------------------------------------------------------------------

	/**
	 * get_title returns the expected string.
	 */
	public function test_get_title_returns_expected_string(): void {
		$this->assertEquals(
			__('Customize Invoice Template', 'ultimate-multisite'),
			$this->page->get_title()
		);
	}

	// -------------------------------------------------------------------------
	// get_menu_title
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns the expected string.
	 */
	public function test_get_menu_title_returns_expected_string(): void {
		$this->assertEquals(
			__('Customize Invoice Template', 'ultimate-multisite'),
			$this->page->get_menu_title()
		);
	}

	// -------------------------------------------------------------------------
	// get_labels
	// -------------------------------------------------------------------------

	/**
	 * get_labels returns an array.
	 */
	public function test_get_labels_returns_array(): void {
		$this->assertIsArray($this->page->get_labels());
	}

	/**
	 * get_labels contains all required keys.
	 */
	public function test_get_labels_contains_required_keys(): void {
		$labels = $this->page->get_labels();

		$required_keys = [
			'customize_label',
			'add_new_label',
			'edit_label',
			'updated_message',
			'title_placeholder',
			'title_description',
			'save_button_label',
			'save_description',
		];

		foreach ($required_keys as $key) {
			$this->assertArrayHasKey($key, $labels, "Missing key: $key");
		}
	}

	/**
	 * get_labels returns correct string values.
	 */
	public function test_get_labels_returns_correct_values(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals(
			__('Customize Invoice Template', 'ultimate-multisite'),
			$labels['customize_label']
		);
		$this->assertEquals(
			__('Customize Invoice Template', 'ultimate-multisite'),
			$labels['add_new_label']
		);
		$this->assertEquals(
			__('Edit Invoice Template', 'ultimate-multisite'),
			$labels['edit_label']
		);
		$this->assertEquals(
			__('Invoice Template updated with success!', 'ultimate-multisite'),
			$labels['updated_message']
		);
		$this->assertEquals(
			__('Save Invoice Template', 'ultimate-multisite'),
			$labels['save_button_label']
		);
	}

	// -------------------------------------------------------------------------
	// action_links
	// -------------------------------------------------------------------------

	/**
	 * action_links returns an empty array.
	 */
	public function test_action_links_returns_empty_array(): void {
		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertEmpty($links);
	}

	// -------------------------------------------------------------------------
	// init
	// -------------------------------------------------------------------------

	/**
	 * init registers the wu-preview-invoice AJAX action.
	 */
	public function test_init_registers_ajax_action(): void {
		$this->page->init();

		$this->assertNotFalse(
			has_action('wp_ajax_wu-preview-invoice', [$this->page, 'generate_invoice_preview'])
		);
	}

	// -------------------------------------------------------------------------
	// get_preview_url
	// -------------------------------------------------------------------------

	/**
	 * get_preview_url returns a non-empty string.
	 */
	public function test_get_preview_url_returns_string(): void {
		$url = $this->page->get_preview_url();

		$this->assertIsString($url);
		$this->assertNotEmpty($url);
	}

	/**
	 * get_preview_url contains the admin-ajax.php endpoint.
	 */
	public function test_get_preview_url_contains_admin_ajax(): void {
		$url = $this->page->get_preview_url();

		$this->assertStringContainsString('admin-ajax.php', $url);
	}

	/**
	 * get_preview_url contains the wu-preview-invoice action.
	 */
	public function test_get_preview_url_contains_preview_action(): void {
		$url = $this->page->get_preview_url();

		$this->assertStringContainsString('action=wu-preview-invoice', $url);
	}

	/**
	 * get_preview_url contains the customizer query arg.
	 */
	public function test_get_preview_url_contains_customizer_arg(): void {
		$url = $this->page->get_preview_url();

		$this->assertStringContainsString('customizer=1', $url);
	}

	/**
	 * get_preview_url contains the invoice-customize query arg.
	 */
	public function test_get_preview_url_contains_invoice_customize_arg(): void {
		$url = $this->page->get_preview_url();

		$this->assertStringContainsString('invoice-customize=1', $url);
	}

	/**
	 * get_preview_url contains a nonce parameter.
	 */
	public function test_get_preview_url_contains_nonce(): void {
		$url = $this->page->get_preview_url();

		$this->assertStringContainsString('wu-preview-nonce=', $url);
	}

	// -------------------------------------------------------------------------
	// handle_save — sanitization logic
	// -------------------------------------------------------------------------

	/**
	 * handle_save sanitizes primary_color as a hex color.
	 */
	public function test_handle_save_sanitizes_primary_color(): void {
		$_POST['primary_color'] = '#ff0000';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// wp_safe_redirect + exit expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayHasKey('primary_color', $saved);
		$this->assertEquals('#ff0000', $saved['primary_color']);
	}

	/**
	 * handle_save rejects an invalid hex color and stores empty string.
	 */
	public function test_handle_save_rejects_invalid_hex_color(): void {
		$_POST['primary_color'] = 'not-a-color';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		// sanitize_hex_color returns null/empty for invalid values.
		$this->assertArrayHasKey('primary_color', $saved);
		$this->assertEmpty($saved['primary_color']);
	}

	/**
	 * handle_save sanitizes use_custom_logo as boolean.
	 */
	public function test_handle_save_sanitizes_use_custom_logo_as_bool(): void {
		$_POST['use_custom_logo'] = '1';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayHasKey('use_custom_logo', $saved);
		$this->assertTrue((bool) $saved['use_custom_logo']);
	}

	/**
	 * handle_save sanitizes custom_logo as absint.
	 */
	public function test_handle_save_sanitizes_custom_logo_as_absint(): void {
		$_POST['custom_logo'] = '42';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayHasKey('custom_logo', $saved);
		$this->assertEquals(42, $saved['custom_logo']);
	}

	/**
	 * handle_save sanitizes logo_url as a URL.
	 */
	public function test_handle_save_sanitizes_logo_url(): void {
		$_POST['logo_url'] = 'https://example.com/logo.png';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayHasKey('logo_url', $saved);
		$this->assertEquals('https://example.com/logo.png', $saved['logo_url']);
	}

	/**
	 * handle_save accepts a valid font value.
	 */
	public function test_handle_save_accepts_valid_font(): void {
		$_POST['font'] = 'DejaVuSerifCondensed';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayHasKey('font', $saved);
		$this->assertEquals('DejaVuSerifCondensed', $saved['font']);
	}

	/**
	 * handle_save falls back to DejaVuSansCondensed for an invalid font.
	 */
	public function test_handle_save_falls_back_to_default_font_for_invalid_value(): void {
		$_POST['font'] = 'InvalidFontName';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayHasKey('font', $saved);
		$this->assertEquals('DejaVuSansCondensed', $saved['font']);
	}

	/**
	 * handle_save accepts all three valid font values.
	 *
	 * @dataProvider valid_font_provider
	 */
	public function test_handle_save_accepts_all_valid_fonts( string $font ): void {
		$_POST['font'] = $font;

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertEquals($font, $saved['font']);
	}

	/**
	 * Data provider for valid font values.
	 *
	 * @return array<string, array<string>>
	 */
	public function valid_font_provider(): array {
		return [
			'sans-serif' => ['DejaVuSansCondensed'],
			'serif'      => ['DejaVuSerifCondensed'],
			'mono'       => ['FreeMono'],
		];
	}

	/**
	 * handle_save sanitizes company_name as text.
	 */
	public function test_handle_save_sanitizes_company_name(): void {
		$_POST['company_name'] = 'Acme Corp <script>alert(1)</script>';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayHasKey('company_name', $saved);
		$this->assertStringNotContainsString('<script>', $saved['company_name']);
	}

	/**
	 * handle_save sanitizes footer_message as text.
	 */
	public function test_handle_save_sanitizes_footer_message(): void {
		$_POST['footer_message'] = 'Thank you for your business!';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayHasKey('footer_message', $saved);
		$this->assertEquals('Thank you for your business!', $saved['footer_message']);
	}

	/**
	 * handle_save sanitizes paid_tag_text as text.
	 */
	public function test_handle_save_sanitizes_paid_tag_text(): void {
		$_POST['paid_tag_text'] = 'PAID';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayHasKey('paid_tag_text', $saved);
		$this->assertEquals('PAID', $saved['paid_tag_text']);
	}

	/**
	 * handle_save only saves allowed settings keys.
	 */
	public function test_handle_save_only_saves_allowed_keys(): void {
		$_POST['company_name']    = 'Test Corp';
		$_POST['disallowed_key']  = 'should_not_be_saved';
		$_POST['another_bad_key'] = 'also_not_saved';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertArrayNotHasKey('disallowed_key', $saved);
		$this->assertArrayNotHasKey('another_bad_key', $saved);
		$this->assertArrayHasKey('company_name', $saved);
	}

	/**
	 * handle_save saves multiple settings in one call.
	 */
	public function test_handle_save_saves_multiple_settings(): void {
		$_POST['company_name']    = 'My Company';
		$_POST['company_address'] = '123 Main St';
		$_POST['paid_tag_text']   = 'Settled';
		$_POST['font']            = 'FreeMono';

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		$saved = Invoice::get_settings();

		$this->assertEquals('My Company', $saved['company_name']);
		$this->assertEquals('123 Main St', $saved['company_address']);
		$this->assertEquals('Settled', $saved['paid_tag_text']);
		$this->assertEquals('FreeMono', $saved['font']);
	}

	/**
	 * handle_save does not save settings when POST is empty.
	 */
	public function test_handle_save_does_not_save_when_post_is_empty(): void {
		// Pre-populate settings.
		wu_save_option(Invoice::KEY, ['company_name' => 'Pre-existing']);

		$_POST = [];

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected.
		}
		ob_get_clean();

		// handle_save calls Invoice::save_settings([]) which overwrites with empty.
		$saved = Invoice::get_settings();

		// The saved settings should be empty (no POST data to save).
		$this->assertEmpty($saved);
	}

	// -------------------------------------------------------------------------
	// register_widgets
	// -------------------------------------------------------------------------

	/**
	 * register_widgets runs without errors when a screen context is set.
	 */
	public function test_register_widgets_runs_without_errors(): void {
		// add_meta_box (called internally) requires a valid screen context.
		set_current_screen('dashboard-network');

		// Should not throw any exceptions.
		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * register_widgets does not throw when a screen context is set.
	 */
	public function test_register_widgets_does_not_throw(): void {
		set_current_screen('dashboard-network');

		$threw = false;

		try {
			$this->page->register_widgets();
		} catch (\Exception $e) {
			$threw = true;
		}

		$this->assertFalse($threw, 'register_widgets() should not throw an exception');
	}

	// -------------------------------------------------------------------------
	// generate_invoice_preview — permission check
	// -------------------------------------------------------------------------

	/**
	 * generate_invoice_preview returns early when user lacks wu_manage_invoice capability.
	 */
	public function test_generate_invoice_preview_returns_early_without_capability(): void {
		// Ensure no user is logged in (no capabilities).
		wp_set_current_user(0);

		ob_start();
		$this->page->generate_invoice_preview();
		$output = ob_get_clean();

		// Should produce no output — returned early.
		$this->assertEmpty($output);
	}
}
