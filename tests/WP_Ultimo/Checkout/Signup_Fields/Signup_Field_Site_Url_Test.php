<?php
/**
 * Tests for Signup_Field_Site_Url class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Site_Url.
 */
class Signup_Field_Site_Url_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Site_Url
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Site_Url();
	}

	/**
	 * Test get_type returns site_url.
	 */
	public function test_get_type(): void {
		$this->assertEquals('site_url', $this->field->get_type());
	}

	/**
	 * Test is_required returns false.
	 */
	public function test_is_required(): void {
		$this->assertFalse($this->field->is_required());
	}

	/**
	 * Test is_site_field returns true.
	 */
	public function test_is_site_field(): void {
		$this->assertTrue($this->field->is_site_field());
	}

	/**
	 * Test get_title returns string.
	 */
	public function test_get_title(): void {
		$title = $this->field->get_title();
		$this->assertIsString($title);
		$this->assertEquals('Site URL', $title);
	}

	/**
	 * Test get_description returns string.
	 */
	public function test_get_description(): void {
		$description = $this->field->get_description();
		$this->assertIsString($description);
		$this->assertNotEmpty($description);
	}

	/**
	 * Test get_tooltip returns string.
	 */
	public function test_get_tooltip(): void {
		$tooltip = $this->field->get_tooltip();
		$this->assertIsString($tooltip);
		$this->assertNotEmpty($tooltip);
	}

	/**
	 * Test get_icon returns dashicon class.
	 */
	public function test_get_icon(): void {
		$icon = $this->field->get_icon();
		$this->assertIsString($icon);
		$this->assertStringContainsString('dashicons', $icon);
	}

	/**
	 * Test defaults returns array.
	 */
	public function test_defaults(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray($defaults);
		$this->assertArrayHasKey('auto_generate_site_url', $defaults);
		$this->assertArrayHasKey('display_url_preview', $defaults);
		$this->assertArrayHasKey('enable_domain_selection', $defaults);
		$this->assertArrayHasKey('display_field_attachments', $defaults);
		$this->assertArrayHasKey('available_domains', $defaults);
	}

	/**
	 * Test default_fields returns expected fields.
	 */
	public function test_default_fields(): void {
		$fields = $this->field->default_fields();
		$this->assertIsArray($fields);
		$this->assertContains('name', $fields);
		$this->assertContains('placeholder', $fields);
		$this->assertContains('tooltip', $fields);
	}

	/**
	 * Test force_attributes returns expected values.
	 */
	public function test_force_attributes(): void {
		$forced = $this->field->force_attributes();
		$this->assertIsArray($forced);
		$this->assertArrayHasKey('id', $forced);
		$this->assertEquals('site_url', $forced['id']);
		$this->assertArrayHasKey('required', $forced);
		$this->assertTrue($forced['required']);
	}

	/**
	 * Test get_fields returns array.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray($fields);
		$this->assertArrayHasKey('auto_generate_site_url', $fields);
		$this->assertArrayHasKey('display_url_preview', $fields);
		$this->assertArrayHasKey('enable_domain_selection', $fields);
		$this->assertArrayHasKey('available_domains', $fields);
	}

	/**
	 * Test get_url_preview_templates returns array.
	 */
	public function test_get_url_preview_templates(): void {
		$templates = $this->field->get_url_preview_templates();
		$this->assertIsArray($templates);
		$this->assertNotEmpty($templates);
	}

	/**
	 * Test to_fields_array with auto generate enabled.
	 */
	public function test_to_fields_array_auto_generate(): void {
		$attributes = [
			'id'                            => 'site_url',
			'name'                          => 'Site URL',
			'placeholder'                   => 'Enter site URL',
			'tooltip'                       => 'Your site URL',
			'auto_generate_site_url'        => true,
			'display_url_preview_with_auto' => false,
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('auto_generate_site_url', $fields);
		$this->assertArrayHasKey('site_url', $fields);
		$this->assertEquals('hidden', $fields['site_url']['type']);
	}

	/**
	 * Test to_fields_array with regular configuration.
	 */
	public function test_to_fields_array_regular(): void {
		$attributes = [
			'id'                        => 'site_url',
			'name'                      => 'Site URL',
			'placeholder'               => 'Enter site URL',
			'tooltip'                   => 'Your site URL',
			'auto_generate_site_url'    => false,
			'display_url_preview'       => false,
			'display_field_attachments' => false,
			'enable_domain_selection'   => false,
			'available_domains'         => '',
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('site_url', $fields);
		$this->assertEquals('text', $fields['site_url']['type']);
		$this->assertEquals('Site URL', $fields['site_url']['name']);
	}

	/**
	 * Test to_fields_array with domain selection enabled.
	 */
	public function test_to_fields_array_with_domain_selection(): void {
		$attributes = [
			'id'                        => 'site_url',
			'name'                      => 'Site URL',
			'placeholder'               => 'Enter site URL',
			'tooltip'                   => 'Your site URL',
			'auto_generate_site_url'    => false,
			'display_url_preview'       => false,
			'display_field_attachments' => false,
			'enable_domain_selection'   => true,
			'available_domains'         => "example.com\nexample.org",
			'available_domains_multi'   => "example.com\nexample.org",
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('site_domain', $fields);
		$this->assertEquals('select', $fields['site_domain']['type']);
	}

	/**
	 * Test to_fields_array with field attachments.
	 */
	public function test_to_fields_array_with_attachments(): void {
		$attributes = [
			'id'                        => 'site_url',
			'name'                      => 'Site URL',
			'placeholder'               => 'Enter site URL',
			'tooltip'                   => 'Your site URL',
			'auto_generate_site_url'    => false,
			'display_url_preview'       => false,
			'display_field_attachments' => true,
			'enable_domain_selection'   => false,
			'available_domains'         => '',
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('site_url', $fields);
		$this->assertArrayHasKey('prefix', $fields['site_url']);
		$this->assertArrayHasKey('suffix', $fields['site_url']);
	}
}
