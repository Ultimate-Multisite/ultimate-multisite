<?php

namespace WP_Ultimo\Objects;

use WP_UnitTestCase;

class Billing_Address_Test extends WP_UnitTestCase {

	/**
	 * Test constructor with valid billing data.
	 */
	public function test_constructor_with_valid_data(): void {

		$address = new Billing_Address([
			'company_name'           => 'Acme Corp',
			'billing_email'          => 'john@acme.com',
			'billing_address_line_1' => '123 Main St',
			'billing_address_line_2' => 'Suite 100',
			'billing_country'        => 'US',
			'billing_state'          => 'NY',
			'billing_city'           => 'New York',
			'billing_zip_code'       => '10001',
		]);

		$this->assertEquals('Acme Corp', $address->company_name);
		$this->assertEquals('john@acme.com', $address->billing_email);
		$this->assertEquals('123 Main St', $address->billing_address_line_1);
		$this->assertEquals('Suite 100', $address->billing_address_line_2);
		$this->assertEquals('US', $address->billing_country);
		$this->assertEquals('NY', $address->billing_state);
		$this->assertEquals('New York', $address->billing_city);
		$this->assertEquals('10001', $address->billing_zip_code);
	}

	/**
	 * Test constructor with empty data.
	 */
	public function test_constructor_with_empty_data(): void {

		$address = new Billing_Address();

		$this->assertEquals('', $address->company_name);
		$this->assertEquals('', $address->billing_email);
		$this->assertEquals('', $address->billing_country);
	}

	/**
	 * Test constructor filters out invalid attributes.
	 */
	public function test_constructor_filters_invalid_attributes(): void {

		$address = new Billing_Address([
			'billing_email' => 'valid@test.com',
			'invalid_field' => 'should be ignored',
		]);

		$this->assertEquals('valid@test.com', $address->billing_email);
		$this->assertEquals('', $address->invalid_field);
	}

	/**
	 * Test exists returns true when address has content.
	 */
	public function test_exists_returns_true_with_content(): void {

		$address = new Billing_Address([
			'billing_email' => 'test@test.com',
		]);

		$this->assertTrue($address->exists());
	}

	/**
	 * Test exists returns false when address is empty.
	 */
	public function test_exists_returns_false_when_empty(): void {

		$address = new Billing_Address();

		$this->assertFalse($address->exists());
	}

	/**
	 * Test magic getter returns empty string for missing attributes.
	 */
	public function test_magic_getter_returns_empty_for_missing(): void {

		$address = new Billing_Address();

		$this->assertEquals('', $address->nonexistent);
	}

	/**
	 * Test magic setter sets attribute.
	 */
	public function test_magic_setter(): void {

		$address = new Billing_Address();

		$address->billing_email = 'new@example.com';

		$this->assertEquals('new@example.com', $address->billing_email);
	}

	/**
	 * Test __isset checks attribute existence.
	 */
	public function test_isset_with_existing_attribute(): void {

		$address = new Billing_Address(['billing_email' => 'test@test.com']);

		$this->assertNotEmpty($address->billing_email);
	}

	/**
	 * Test validate returns true for valid address.
	 */
	public function test_validate_returns_true_for_valid(): void {

		$address = new Billing_Address([
			'billing_email'          => 'test@test.com',
			'billing_address_line_1' => '123 Main St',
			'billing_country'        => 'US',
			'billing_zip_code'       => '10001',
		]);

		$result = $address->validate();

		$this->assertTrue($result);
	}

	/**
	 * Test to_array returns populated fields.
	 */
	public function test_to_array_returns_populated_fields(): void {

		$address = new Billing_Address([
			'company_name'     => 'Acme',
			'billing_email'    => 'test@test.com',
			'billing_zip_code' => '10001',
		]);

		$array = $address->to_array();

		$this->assertArrayHasKey('company_name', $array);
		$this->assertArrayHasKey('billing_email', $array);
		$this->assertArrayHasKey('billing_zip_code', $array);
		$this->assertEquals('Acme', $array['company_name']);
	}

	/**
	 * Test to_array with labels.
	 */
	public function test_to_array_with_labels(): void {

		$address = new Billing_Address([
			'company_name'  => 'Acme',
			'billing_email' => 'test@test.com',
		]);

		$array = $address->to_array(true);

		// Should not have original keys
		$this->assertArrayNotHasKey('company_name', $array);
		$this->assertArrayNotHasKey('billing_email', $array);

		// Should have label-based keys
		$this->assertNotEmpty($array);
	}

	/**
	 * Test to_array excludes empty fields.
	 */
	public function test_to_array_excludes_empty_fields(): void {

		$address = new Billing_Address([
			'billing_email' => 'only@this.com',
		]);

		$array = $address->to_array();

		$this->assertArrayHasKey('billing_email', $array);
		$this->assertArrayNotHasKey('company_name', $array);
		$this->assertArrayNotHasKey('billing_state', $array);
	}

	/**
	 * Test to_string returns joined string.
	 */
	public function test_to_string(): void {

		$address = new Billing_Address([
			'company_name'  => 'Acme Corp',
			'billing_email' => 'john@acme.com',
			'billing_city'  => 'New York',
		]);

		$string = $address->to_string();

		$this->assertStringContainsString('Acme Corp', $string);
		$this->assertStringContainsString('john@acme.com', $string);
		$this->assertStringContainsString('New York', $string);
	}

	/**
	 * Test to_string with custom delimiter.
	 */
	public function test_to_string_with_custom_delimiter(): void {

		$address = new Billing_Address([
			'company_name'  => 'Acme',
			'billing_email' => 'test@test.com',
		]);

		$string = $address->to_string(', ');

		$this->assertStringContainsString(', ', $string);
	}

	/**
	 * Test fields returns expected keys.
	 */
	public function test_fields_returns_expected_keys(): void {

		$fields = Billing_Address::fields();

		$expected_keys = [
			'company_name',
			'billing_email',
			'billing_address_line_1',
			'billing_address_line_2',
			'billing_country',
			'billing_state',
			'billing_city',
			'billing_zip_code',
		];

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $fields);
		}
	}

	/**
	 * Test fields with zip_only flag.
	 */
	public function test_fields_zip_only(): void {

		$fields = Billing_Address::fields(true);

		$this->assertArrayHasKey('billing_zip_code', $fields);
		$this->assertArrayHasKey('billing_country', $fields);
		$this->assertCount(2, $fields);
	}

	/**
	 * Test fields structure has type and title.
	 */
	public function test_fields_structure(): void {

		$fields = Billing_Address::fields();

		foreach ($fields as $field) {
			$this->assertArrayHasKey('type', $field);
			$this->assertArrayHasKey('title', $field);
		}
	}

	/**
	 * Test get_fields returns fields with values.
	 */
	public function test_get_fields_returns_fields_with_values(): void {

		$address = new Billing_Address([
			'billing_email' => 'test@test.com',
		]);

		$fields = $address->get_fields();

		$this->assertArrayHasKey('billing_email', $fields);
		$this->assertEquals('test@test.com', $fields['billing_email']['value']);
	}

	/**
	 * Test get_fields with zip_only.
	 */
	public function test_get_fields_zip_only(): void {

		$address = new Billing_Address([
			'billing_zip_code' => '10001',
			'billing_country'  => 'US',
		]);

		$fields = $address->get_fields(true);

		$this->assertCount(2, $fields);
		$this->assertArrayHasKey('billing_zip_code', $fields);
		$this->assertArrayHasKey('billing_country', $fields);
	}

	/**
	 * Test fields_for_rest returns correct format.
	 */
	public function test_fields_for_rest(): void {

		$fields = Billing_Address::fields_for_rest();

		foreach ($fields as $field) {
			$this->assertArrayHasKey('description', $field);
			$this->assertArrayHasKey('type', $field);
			$this->assertEquals('string', $field['type']);
		}
	}

	/**
	 * Test fields_for_rest with zip_only.
	 */
	public function test_fields_for_rest_zip_only(): void {

		$fields = Billing_Address::fields_for_rest(true);

		$this->assertCount(2, $fields);
		$this->assertArrayHasKey('billing_zip_code', $fields);
		$this->assertArrayHasKey('billing_country', $fields);
	}

	/**
	 * Test load_attributes_from_post with session data.
	 */
	public function test_load_attributes_from_post_with_session(): void {

		$address = new Billing_Address();

		$session = [
			'billing_email'    => 'session@test.com',
			'billing_zip_code' => '90210',
		];

		$address->load_attributes_from_post($session);

		$this->assertEquals('session@test.com', $address->billing_email);
		$this->assertEquals('90210', $address->billing_zip_code);
	}
}
