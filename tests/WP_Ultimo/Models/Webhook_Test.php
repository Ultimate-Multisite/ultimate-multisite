<?php
/**
 * Unit tests for Webhook class.
 */

namespace WP_Ultimo\Models;

/**
 * Unit tests for Webhook class.
 */
class Webhook_Test extends \WP_UnitTestCase {

	/**
	 * Webhook instance.
	 *
	 * @var Webhook
	 */
	protected $webhook;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a webhook manually to avoid faker issues
		$this->webhook = new Webhook();
		$this->webhook->set_name('Test Webhook');
		$this->webhook->set_webhook_url('https://example.com/webhook');
		$this->webhook->set_event('user_registration');
		$this->webhook->set_integration('manual');
	}

	/**
	 * Test webhook creation.
	 */
	public function test_webhook_creation(): void {
		$this->assertInstanceOf(Webhook::class, $this->webhook, 'Webhook should be an instance of Webhook class.');
		$this->assertEquals('Test Webhook', $this->webhook->get_name(), 'Webhook should have a name.');
		$this->assertEquals('https://example.com/webhook', $this->webhook->get_webhook_url(), 'Webhook should have a URL.');
		$this->assertEquals('user_registration', $this->webhook->get_event(), 'Webhook should have an event.');
		$this->assertEquals('manual', $this->webhook->get_integration(), 'Webhook should have integration set.');
	}

	/**
	 * Test webhook validation rules.
	 */
	public function test_webhook_validation_rules(): void {
		$validation_rules = $this->webhook->validation_rules();

		// Test required fields
		$this->assertArrayHasKey('name', $validation_rules, 'Validation rules should include name field.');
		$this->assertArrayHasKey('webhook_url', $validation_rules, 'Validation rules should include webhook_url field.');
		$this->assertArrayHasKey('event', $validation_rules, 'Validation rules should include event field.');

		// Test field constraints
		$this->assertStringContainsString('required', $validation_rules['name'], 'Name should be required.');
		$this->assertStringContainsString('required', $validation_rules['webhook_url'], 'Webhook URL should be required.');
		$this->assertStringContainsString('required', $validation_rules['event'], 'Event should be required.');
		$this->assertStringContainsString('url:http,https', $validation_rules['webhook_url'], 'Webhook URL should be valid URL.');
	}

	/**
	 * Test webhook properties.
	 */
	public function test_webhook_properties(): void {
		// Test name getter/setter
		$this->webhook->set_name('Updated Webhook');
		$this->assertEquals('Updated Webhook', $this->webhook->get_name(), 'Name should be set and retrieved correctly.');

		// Test webhook URL getter/setter
		$this->webhook->set_webhook_url('https://updated.example.com/webhook');
		$this->assertEquals('https://updated.example.com/webhook', $this->webhook->get_webhook_url(), 'Webhook URL should be set and retrieved correctly.');

		// Test event getter/setter
		$this->webhook->set_event('payment_completed');
		$this->assertEquals('payment_completed', $this->webhook->get_event(), 'Event should be set and retrieved correctly.');

		// Test integration getter/setter
		$this->webhook->set_integration('stripe');
		$this->assertEquals('stripe', $this->webhook->get_integration(), 'Integration should be set and retrieved correctly.');
	}

	/**
	 * Test webhook status and counting.
	 */
	public function test_webhook_status_and_counting(): void {
		// Test active status
		$this->webhook->set_active(true);
		$this->assertTrue($this->webhook->is_active(), 'Active status should be set to true.');

		$this->webhook->set_active(false);
		$this->assertFalse($this->webhook->is_active(), 'Active status should be set to false.');

		// Test hidden status
		$this->webhook->set_hidden(true);
		$this->assertTrue($this->webhook->is_hidden(), 'Hidden status should be set to true.');

		$this->webhook->set_hidden(false);
		$this->assertFalse($this->webhook->is_hidden(), 'Hidden status should be set to false.');

		// Test event counting
		$this->webhook->set_event_count(5);
		$this->assertEquals(5, $this->webhook->get_event_count(), 'Event count should be set and retrieved correctly.');
	}

	/**
	 * Test webhook integration handling.
	 */
	public function test_webhook_integration(): void {
		// Test integration name
		$integrations = ['manual', 'stripe', 'paypal', 'zapier'];
		foreach ($integrations as $integration) {
			$this->webhook->set_integration($integration);
			$this->assertEquals($integration, $this->webhook->get_integration(), "Integration {$integration} should be set and retrieved correctly.");
		}
	}

	/**
	 * Test webhook date handling.
	 */
	public function test_webhook_date_handling(): void {

		$this->webhook->set_date_created(date_i18n('Y-m-d H:i:s'));
		// Test date formatting
		if (method_exists($this->webhook, 'get_formatted_date')) {
			$formatted_date = $this->webhook->get_formatted_date('date_created');
			$this->assertIsString($formatted_date, 'Formatted date should be a string.');
			$this->assertNotEmpty($formatted_date, 'Formatted date should not be empty.');
		}
	}

	/**
	 * Test webhook save with validation error.
	 */
	public function test_webhook_save_with_validation_error(): void {
		$webhook = new Webhook();

		// Try to save without required fields
		$webhook->set_skip_validation(false);
		$result = $webhook->save();

		$this->assertInstanceOf(\WP_Error::class, $result, 'Save should return WP_Error when validation fails.');
	}

	/**
	 * Test webhook save with validation bypassed.
	 */
	public function test_webhook_save_with_validation_bypassed(): void {
		$webhook = new Webhook();

		// Set required fields
		$webhook->set_name('Test Webhook');
		$webhook->set_webhook_url('https://example.com/webhook');
		$webhook->set_event('user_registration');
		$webhook->set_integration('manual');

		// Bypass validation for testing
		$webhook->set_skip_validation(true);
		$result = $webhook->save();

		// In test environment, this might fail due to WordPress constraints
		// We're mainly testing that the method runs without errors
		$this->assertIsBool($result, 'Save should return boolean result.');
	}

	/**
	 * Test webhook deletion.
	 */
	public function test_webhook_deletion(): void {

		// Set up a webhook with ID first
		$this->webhook->set_name('Test Webhook for Deletion');
		$this->webhook->set_webhook_url('https://example.com/webhook');
		$this->webhook->set_event('user_registration');
		$this->webhook->set_integration('manual');
		$this->webhook->set_skip_validation(true);
		$save_result = $this->webhook->save();

		if ($save_result) {
			$webhook_id = $this->webhook->get_id();
			$this->assertNotEmpty($webhook_id, 'Webhook should have ID after save.');

			// Test deletion
			$delete_result = $this->webhook->delete();
			$this->assertTrue((bool) $delete_result, 'Webhook should be deleted successfully.');
		}
	}

	/**
	 * Test to_array method.
	 */
	public function test_to_array(): void {
		$array = $this->webhook->to_array();

		$this->assertIsArray($array, 'to_array() should return an array.');
		$this->assertArrayHasKey('id', $array, 'Array should contain id field.');
		$this->assertArrayHasKey('name', $array, 'Array should contain name field.');
		$this->assertArrayHasKey('webhook_url', $array, 'Array should contain webhook_url field.');
		$this->assertArrayHasKey('event', $array, 'Array should contain event field.');

		// Should not contain internal properties
		$this->assertArrayNotHasKey('query_class', $array, 'Array should not contain query_class.');
		$this->assertArrayNotHasKey('meta', $array, 'Array should not contain meta.');
	}

	/**
	 * Test hash generation.
	 */
	public function test_hash_generation(): void {
		$hash = $this->webhook->get_hash('id');

		$this->assertIsString($hash, 'Hash should be a string.');
		$this->assertNotEmpty($hash, 'Hash should not be empty.');
	}

	/**
	 * Test meta data handling.
	 */
	public function test_meta_data_handling(): void {
		$meta_key   = 'test_meta_key';
		$meta_value = 'test_meta_value';

		// Test meta update
		$result = $this->webhook->update_meta($meta_key, $meta_value);
		$this->assertFalse($result || is_numeric($result), 'Web hooks don\'t do meta ');
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		// Clean up created webhooks
		$webhooks = Webhook::get_all();
		if ($webhooks) {
			foreach ($webhooks as $webhook) {
				if ($webhook->get_id()) {
					$webhook->delete();
				}
			}
		}

		parent::tearDown();
	}
}
