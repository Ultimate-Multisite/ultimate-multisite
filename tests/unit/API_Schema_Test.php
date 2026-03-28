<?php
/**
 * Unit tests for API schema validation files in inc/apis/schemas/.
 *
 * These tests verify the structural integrity of all 24 schema files:
 * - Each field has required keys: description, type, required
 * - Required fields are correctly marked
 * - Enum values are valid arrays
 * - Type values are from the known allowed set
 * - Schema-specific required fields are present
 *
 * @package WP_Ultimo\Tests\Unit
 * @since 2.0.11
 */

use PHPUnit\Framework\TestCase;

/**
 * API Schema Validation Test Suite.
 *
 * Tests all 24 schema files in inc/apis/schemas/ for structural correctness.
 */
final class API_Schema_Test extends TestCase {

	/**
	 * Known valid field types across all schemas.
	 *
	 * @var string[]
	 */
	private const VALID_TYPES = [
		'string',
		'integer',
		'number',
		'boolean',
		'array',
		'object',
		'mixed',
	];

	/**
	 * Base path to schema files.
	 *
	 * @var string
	 */
	private string $schemas_dir;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->schemas_dir = dirname(__DIR__, 2) . '/inc/apis/schemas';
	}

	/**
	 * Load a schema file and return its array.
	 *
	 * @param string $filename Schema filename (without path).
	 * @return array<string, array<string, mixed>>
	 */
	private function load_schema(string $filename): array {
		$path = $this->schemas_dir . '/' . $filename;
		$this->assertFileExists($path, "Schema file {$filename} must exist");
		$schema = require $path;
		$this->assertIsArray($schema, "Schema {$filename} must return an array");
		return $schema;
	}

	/**
	 * Assert that a schema field has all required structural keys.
	 *
	 * @param string                 $field_name  Field name for error messages.
	 * @param array<string, mixed>   $field_def   Field definition array.
	 * @param string                 $schema_file Schema filename for error messages.
	 * @return void
	 */
	private function assert_field_structure(string $field_name, array $field_def, string $schema_file): void {
		$this->assertArrayHasKey(
			'description',
			$field_def,
			"Field '{$field_name}' in {$schema_file} must have a 'description' key"
		);
		$this->assertArrayHasKey(
			'type',
			$field_def,
			"Field '{$field_name}' in {$schema_file} must have a 'type' key"
		);
		$this->assertArrayHasKey(
			'required',
			$field_def,
			"Field '{$field_name}' in {$schema_file} must have a 'required' key"
		);
		$this->assertIsString(
			$field_def['description'],
			"Field '{$field_name}' description in {$schema_file} must be a string"
		);
		$this->assertNotEmpty(
			$field_def['description'],
			"Field '{$field_name}' description in {$schema_file} must not be empty"
		);
		$this->assertContains(
			$field_def['type'],
			self::VALID_TYPES,
			"Field '{$field_name}' type '{$field_def['type']}' in {$schema_file} must be a valid type"
		);
		$this->assertIsBool(
			$field_def['required'],
			"Field '{$field_name}' 'required' in {$schema_file} must be a boolean"
		);
	}

	/**
	 * Assert that a schema field with enum has a valid non-empty array of values.
	 *
	 * @param string                 $field_name  Field name for error messages.
	 * @param array<string, mixed>   $field_def   Field definition array.
	 * @param string                 $schema_file Schema filename for error messages.
	 * @return void
	 */
	private function assert_enum_structure(string $field_name, array $field_def, string $schema_file): void {
		if ( ! isset($field_def['enum']) ) {
			return;
		}
		$this->assertIsArray(
			$field_def['enum'],
			"Field '{$field_name}' enum in {$schema_file} must be an array"
		);
		$this->assertNotEmpty(
			$field_def['enum'],
			"Field '{$field_name}' enum in {$schema_file} must not be empty"
		);
		foreach ( $field_def['enum'] as $enum_value ) {
			$this->assertIsString(
				$enum_value,
				"Field '{$field_name}' enum values in {$schema_file} must be strings"
			);
		}
	}

	/**
	 * Assert that a schema has all the expected required fields.
	 *
	 * @param array<string, array<string, mixed>> $schema         Schema array.
	 * @param string[]                            $required_keys  Expected required field names.
	 * @param string                              $schema_file    Schema filename for error messages.
	 * @return void
	 */
	private function assert_required_fields(array $schema, array $required_keys, string $schema_file): void {
		foreach ( $required_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$schema,
				"Schema {$schema_file} must contain field '{$key}'"
			);
			$this->assertTrue(
				$schema[$key]['required'],
				"Field '{$key}' in {$schema_file} must be marked as required"
			);
		}
	}

	/**
	 * Assert that a schema has all the expected optional fields.
	 *
	 * @param array<string, array<string, mixed>> $schema         Schema array.
	 * @param string[]                            $optional_keys  Expected optional field names.
	 * @param string                              $schema_file    Schema filename for error messages.
	 * @return void
	 */
	private function assert_optional_fields(array $schema, array $optional_keys, string $schema_file): void {
		foreach ( $optional_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$schema,
				"Schema {$schema_file} must contain field '{$key}'"
			);
			$this->assertFalse(
				$schema[$key]['required'],
				"Field '{$key}' in {$schema_file} must be marked as optional (required=false)"
			);
		}
	}

	/**
	 * Run full structural validation on every field in a schema.
	 *
	 * @param array<string, array<string, mixed>> $schema      Schema array.
	 * @param string                              $schema_file Schema filename for error messages.
	 * @return void
	 */
	private function assert_schema_structure(array $schema, string $schema_file): void {
		$this->assertNotEmpty($schema, "Schema {$schema_file} must not be empty");
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertIsArray(
				$field_def,
				"Field '{$field_name}' in {$schema_file} must be an array"
			);
			$this->assert_field_structure($field_name, $field_def, $schema_file);
			$this->assert_enum_structure($field_name, $field_def, $schema_file);
		}
	}

	// =========================================================================
	// Checkout Form Schemas
	// =========================================================================

	/**
	 * Test checkout-form-create schema structure.
	 *
	 * @return void
	 */
	public function test_checkout_form_create_schema_structure(): void {
		$schema = $this->load_schema('checkout-form-create.php');
		$this->assert_schema_structure($schema, 'checkout-form-create.php');
	}

	/**
	 * Test checkout-form-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_checkout_form_create_required_fields(): void {
		$schema = $this->load_schema('checkout-form-create.php');
		$this->assert_required_fields($schema, ['slug', 'name', 'active'], 'checkout-form-create.php');
	}

	/**
	 * Test checkout-form-create has correct optional fields.
	 *
	 * @return void
	 */
	public function test_checkout_form_create_optional_fields(): void {
		$schema = $this->load_schema('checkout-form-create.php');
		$this->assert_optional_fields(
			$schema,
			['custom_css', 'settings', 'allowed_countries', 'thank_you_page_id', 'conversion_snippets', 'template', 'date_created', 'date_modified', 'migrated_from_id', 'skip_validation'],
			'checkout-form-create.php'
		);
	}

	/**
	 * Test checkout-form-create template field has valid enum values.
	 *
	 * @return void
	 */
	public function test_checkout_form_create_template_enum(): void {
		$schema = $this->load_schema('checkout-form-create.php');
		$this->assertArrayHasKey('template', $schema);
		$this->assertSame(['blank', 'single-step', 'multi-step'], $schema['template']['enum']);
	}

	/**
	 * Test checkout-form-create settings field type is object (for create).
	 *
	 * @return void
	 */
	public function test_checkout_form_create_settings_type(): void {
		$schema = $this->load_schema('checkout-form-create.php');
		$this->assertSame('object', $schema['settings']['type']);
	}

	/**
	 * Test checkout-form-update schema structure.
	 *
	 * @return void
	 */
	public function test_checkout_form_update_schema_structure(): void {
		$schema = $this->load_schema('checkout-form-update.php');
		$this->assert_schema_structure($schema, 'checkout-form-update.php');
	}

	/**
	 * Test checkout-form-update has no required fields (all optional for updates).
	 *
	 * @return void
	 */
	public function test_checkout_form_update_all_fields_optional(): void {
		$schema = $this->load_schema('checkout-form-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in checkout-form-update.php must be optional"
			);
		}
	}

	/**
	 * Test checkout-form-update settings field type is array (differs from create).
	 *
	 * @return void
	 */
	public function test_checkout_form_update_settings_type_is_array(): void {
		$schema = $this->load_schema('checkout-form-update.php');
		$this->assertArrayHasKey('settings', $schema);
		$this->assertSame('array', $schema['settings']['type']);
	}

	/**
	 * Test checkout-form-update template field has same enum as create.
	 *
	 * @return void
	 */
	public function test_checkout_form_update_template_enum(): void {
		$schema = $this->load_schema('checkout-form-update.php');
		$this->assertArrayHasKey('template', $schema);
		$this->assertSame(['blank', 'single-step', 'multi-step'], $schema['template']['enum']);
	}

	// =========================================================================
	// Payment Schemas
	// =========================================================================

	/**
	 * Test payment-create schema structure.
	 *
	 * @return void
	 */
	public function test_payment_create_schema_structure(): void {
		$schema = $this->load_schema('payment-create.php');
		$this->assert_schema_structure($schema, 'payment-create.php');
	}

	/**
	 * Test payment-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_payment_create_required_fields(): void {
		$schema = $this->load_schema('payment-create.php');
		$this->assert_required_fields(
			$schema,
			['customer_id', 'membership_id', 'subtotal', 'total', 'status'],
			'payment-create.php'
		);
	}

	/**
	 * Test payment-create has correct optional fields.
	 *
	 * @return void
	 */
	public function test_payment_create_optional_fields(): void {
		$schema = $this->load_schema('payment-create.php');
		$this->assert_optional_fields(
			$schema,
			['parent_id', 'currency', 'refund_total', 'tax_total', 'discount_code', 'gateway', 'product_id', 'gateway_payment_id', 'discount_total', 'invoice_number', 'cancel_membership_on_refund', 'date_created', 'date_modified', 'migrated_from_id', 'skip_validation'],
			'payment-create.php'
		);
	}

	/**
	 * Test payment-create status field has a valid enum.
	 *
	 * @return void
	 */
	public function test_payment_create_status_enum_is_array(): void {
		$schema = $this->load_schema('payment-create.php');
		$this->assertArrayHasKey('status', $schema);
		$this->assertArrayHasKey('enum', $schema['status']);
		$this->assertIsArray($schema['status']['enum']);
		$this->assertNotEmpty($schema['status']['enum']);
	}

	/**
	 * Test payment-create status enum contains expected payment statuses.
	 *
	 * @return void
	 */
	public function test_payment_create_status_enum_contains_core_statuses(): void {
		$schema = $this->load_schema('payment-create.php');
		$enum   = $schema['status']['enum'];
		// Core payment statuses that must always be present
		$this->assertContains('pending', $enum, 'Payment status enum must include pending');
		$this->assertContains('completed', $enum, 'Payment status enum must include completed');
		$this->assertContains('refunded', $enum, 'Payment status enum must include refunded');
		$this->assertContains('failed', $enum, 'Payment status enum must include failed');
		$this->assertContains('cancelled', $enum, 'Payment status enum must include cancelled');
	}

	/**
	 * Test payment-create numeric fields have correct types.
	 *
	 * @return void
	 */
	public function test_payment_create_numeric_field_types(): void {
		$schema = $this->load_schema('payment-create.php');
		// These are monetary values — should be 'number' (float-compatible)
		$this->assertSame('number', $schema['subtotal']['type']);
		$this->assertSame('number', $schema['total']['type']);
		// These are integer IDs
		$this->assertSame('integer', $schema['customer_id']['type']);
		$this->assertSame('integer', $schema['membership_id']['type']);
	}

	/**
	 * Test payment-update schema structure.
	 *
	 * @return void
	 */
	public function test_payment_update_schema_structure(): void {
		$schema = $this->load_schema('payment-update.php');
		$this->assert_schema_structure($schema, 'payment-update.php');
	}

	/**
	 * Test payment-update has no required fields.
	 *
	 * @return void
	 */
	public function test_payment_update_all_fields_optional(): void {
		$schema = $this->load_schema('payment-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in payment-update.php must be optional"
			);
		}
	}

	/**
	 * Test payment-update status enum matches payment-create status enum.
	 *
	 * @return void
	 */
	public function test_payment_update_status_enum_matches_create(): void {
		$create = $this->load_schema('payment-create.php');
		$update = $this->load_schema('payment-update.php');
		$this->assertSame(
			$create['status']['enum'],
			$update['status']['enum'],
			'payment-update status enum must match payment-create status enum'
		);
	}

	// =========================================================================
	// Membership Schemas
	// =========================================================================

	/**
	 * Test membership-create schema structure.
	 *
	 * @return void
	 */
	public function test_membership_create_schema_structure(): void {
		$schema = $this->load_schema('membership-create.php');
		$this->assert_schema_structure($schema, 'membership-create.php');
	}

	/**
	 * Test membership-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_membership_create_required_fields(): void {
		$schema = $this->load_schema('membership-create.php');
		$this->assert_required_fields(
			$schema,
			['customer_id', 'plan_id'],
			'membership-create.php'
		);
	}

	/**
	 * Test membership-create has correct optional fields.
	 *
	 * @return void
	 */
	public function test_membership_create_optional_fields(): void {
		$schema = $this->load_schema('membership-create.php');
		$this->assert_optional_fields(
			$schema,
			['user_id', 'currency', 'duration', 'duration_unit', 'amount', 'initial_amount', 'status', 'auto_renew', 'times_billed', 'billing_cycles', 'gateway', 'recurring', 'disabled', 'migrated_from_id', 'skip_validation'],
			'membership-create.php'
		);
	}

	/**
	 * Test membership-create duration_unit enum has valid values.
	 *
	 * @return void
	 */
	public function test_membership_create_duration_unit_enum(): void {
		$schema = $this->load_schema('membership-create.php');
		$this->assertArrayHasKey('duration_unit', $schema);
		$enum = $schema['duration_unit']['enum'];
		$this->assertContains('day', $enum);
		$this->assertContains('week', $enum);
		$this->assertContains('month', $enum);
		$this->assertContains('year', $enum);
		$this->assertCount(4, $enum, 'duration_unit enum must have exactly 4 values');
	}

	/**
	 * Test membership-create status enum is a valid non-empty array.
	 *
	 * @return void
	 */
	public function test_membership_create_status_enum_is_array(): void {
		$schema = $this->load_schema('membership-create.php');
		$this->assertArrayHasKey('status', $schema);
		$this->assertArrayHasKey('enum', $schema['status']);
		$this->assertIsArray($schema['status']['enum']);
		$this->assertNotEmpty($schema['status']['enum']);
	}

	/**
	 * Test membership-create status enum contains core membership statuses.
	 *
	 * @return void
	 */
	public function test_membership_create_status_enum_contains_core_statuses(): void {
		$schema = $this->load_schema('membership-create.php');
		$enum   = $schema['status']['enum'];
		$this->assertContains('pending', $enum, 'Membership status enum must include pending');
		$this->assertContains('active', $enum, 'Membership status enum must include active');
		$this->assertContains('expired', $enum, 'Membership status enum must include expired');
		$this->assertContains('cancelled', $enum, 'Membership status enum must include cancelled');
	}

	/**
	 * Test membership-update schema structure.
	 *
	 * @return void
	 */
	public function test_membership_update_schema_structure(): void {
		$schema = $this->load_schema('membership-update.php');
		$this->assert_schema_structure($schema, 'membership-update.php');
	}

	/**
	 * Test membership-update has no required fields.
	 *
	 * @return void
	 */
	public function test_membership_update_all_fields_optional(): void {
		$schema = $this->load_schema('membership-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in membership-update.php must be optional"
			);
		}
	}

	/**
	 * Test membership-update status enum matches membership-create status enum.
	 *
	 * @return void
	 */
	public function test_membership_update_status_enum_matches_create(): void {
		$create = $this->load_schema('membership-create.php');
		$update = $this->load_schema('membership-update.php');
		$this->assertSame(
			$create['status']['enum'],
			$update['status']['enum'],
			'membership-update status enum must match membership-create status enum'
		);
	}

	/**
	 * Test membership-update duration_unit enum matches membership-create.
	 *
	 * @return void
	 */
	public function test_membership_update_duration_unit_enum_matches_create(): void {
		$create = $this->load_schema('membership-create.php');
		$update = $this->load_schema('membership-update.php');
		$this->assertSame(
			$create['duration_unit']['enum'],
			$update['duration_unit']['enum'],
			'membership-update duration_unit enum must match membership-create'
		);
	}

	// =========================================================================
	// Customer Schemas
	// =========================================================================

	/**
	 * Test customer-create schema structure.
	 *
	 * @return void
	 */
	public function test_customer_create_schema_structure(): void {
		$schema = $this->load_schema('customer-create.php');
		$this->assert_schema_structure($schema, 'customer-create.php');
	}

	/**
	 * Test customer-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_customer_create_required_fields(): void {
		$schema = $this->load_schema('customer-create.php');
		$this->assert_required_fields(
			$schema,
			['user_id', 'email_verification', 'type'],
			'customer-create.php'
		);
	}

	/**
	 * Test customer-create email_verification enum has valid values.
	 *
	 * @return void
	 */
	public function test_customer_create_email_verification_enum(): void {
		$schema = $this->load_schema('customer-create.php');
		$this->assertArrayHasKey('email_verification', $schema);
		$enum = $schema['email_verification']['enum'];
		$this->assertContains('verified', $enum);
		$this->assertContains('pending', $enum);
		$this->assertContains('none', $enum);
		$this->assertCount(3, $enum, 'email_verification enum must have exactly 3 values');
	}

	/**
	 * Test customer-create type enum only allows 'customer'.
	 *
	 * @return void
	 */
	public function test_customer_create_type_enum(): void {
		$schema = $this->load_schema('customer-create.php');
		$this->assertArrayHasKey('type', $schema);
		$this->assertSame(['customer'], $schema['type']['enum']);
	}

	/**
	 * Test customer-update schema structure.
	 *
	 * @return void
	 */
	public function test_customer_update_schema_structure(): void {
		$schema = $this->load_schema('customer-update.php');
		$this->assert_schema_structure($schema, 'customer-update.php');
	}

	/**
	 * Test customer-update has no required fields.
	 *
	 * @return void
	 */
	public function test_customer_update_all_fields_optional(): void {
		$schema = $this->load_schema('customer-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in customer-update.php must be optional"
			);
		}
	}

	// =========================================================================
	// Product Schemas
	// =========================================================================

	/**
	 * Test product-create schema structure.
	 *
	 * @return void
	 */
	public function test_product_create_schema_structure(): void {
		$schema = $this->load_schema('product-create.php');
		$this->assert_schema_structure($schema, 'product-create.php');
	}

	/**
	 * Test product-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_product_create_required_fields(): void {
		$schema = $this->load_schema('product-create.php');
		$this->assert_required_fields(
			$schema,
			['slug', 'currency', 'pricing_type', 'type'],
			'product-create.php'
		);
	}

	/**
	 * Test product-create pricing_type enum has valid values.
	 *
	 * @return void
	 */
	public function test_product_create_pricing_type_enum(): void {
		$schema = $this->load_schema('product-create.php');
		$this->assertArrayHasKey('pricing_type', $schema);
		$enum = $schema['pricing_type']['enum'];
		$this->assertContains('free', $enum);
		$this->assertContains('paid', $enum);
		$this->assertContains('contact_us', $enum);
		$this->assertCount(3, $enum, 'pricing_type enum must have exactly 3 values');
	}

	/**
	 * Test product-create type enum has valid values.
	 *
	 * @return void
	 */
	public function test_product_create_type_enum(): void {
		$schema = $this->load_schema('product-create.php');
		$this->assertArrayHasKey('type', $schema);
		$enum = $schema['type']['enum'];
		$this->assertContains('plan', $enum);
		$this->assertContains('service', $enum);
		$this->assertContains('package', $enum);
	}

	/**
	 * Test product-create duration_unit enum has valid values.
	 *
	 * @return void
	 */
	public function test_product_create_duration_unit_enum(): void {
		$schema = $this->load_schema('product-create.php');
		$this->assertArrayHasKey('duration_unit', $schema);
		$enum = $schema['duration_unit']['enum'];
		$this->assertContains('day', $enum);
		$this->assertContains('week', $enum);
		$this->assertContains('month', $enum);
		$this->assertContains('year', $enum);
	}

	/**
	 * Test product-create trial_duration_unit enum has valid values.
	 *
	 * @return void
	 */
	public function test_product_create_trial_duration_unit_enum(): void {
		$schema = $this->load_schema('product-create.php');
		$this->assertArrayHasKey('trial_duration_unit', $schema);
		$enum = $schema['trial_duration_unit']['enum'];
		$this->assertContains('day', $enum);
		$this->assertContains('week', $enum);
		$this->assertContains('month', $enum);
		$this->assertContains('year', $enum);
	}

	/**
	 * Test product-update schema structure.
	 *
	 * @return void
	 */
	public function test_product_update_schema_structure(): void {
		$schema = $this->load_schema('product-update.php');
		$this->assert_schema_structure($schema, 'product-update.php');
	}

	/**
	 * Test product-update has no required fields.
	 *
	 * @return void
	 */
	public function test_product_update_all_fields_optional(): void {
		$schema = $this->load_schema('product-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in product-update.php must be optional"
			);
		}
	}

	// =========================================================================
	// Discount Code Schemas
	// =========================================================================

	/**
	 * Test discount-code-create schema structure.
	 *
	 * @return void
	 */
	public function test_discount_code_create_schema_structure(): void {
		$schema = $this->load_schema('discount-code-create.php');
		$this->assert_schema_structure($schema, 'discount-code-create.php');
	}

	/**
	 * Test discount-code-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_discount_code_create_required_fields(): void {
		$schema = $this->load_schema('discount-code-create.php');
		$this->assert_required_fields(
			$schema,
			['name', 'code', 'value'],
			'discount-code-create.php'
		);
	}

	/**
	 * Test discount-code-create type enum has valid values.
	 *
	 * @return void
	 */
	public function test_discount_code_create_type_enum(): void {
		$schema = $this->load_schema('discount-code-create.php');
		$this->assertArrayHasKey('type', $schema);
		$enum = $schema['type']['enum'];
		$this->assertContains('percentage', $enum);
		$this->assertContains('absolute', $enum);
		$this->assertCount(2, $enum, 'discount type enum must have exactly 2 values');
	}

	/**
	 * Test discount-code-create setup_fee_type enum matches type enum.
	 *
	 * @return void
	 */
	public function test_discount_code_create_setup_fee_type_enum(): void {
		$schema = $this->load_schema('discount-code-create.php');
		$this->assertArrayHasKey('setup_fee_type', $schema);
		$this->assertSame(
			$schema['type']['enum'],
			$schema['setup_fee_type']['enum'],
			'setup_fee_type enum must match type enum'
		);
	}

	/**
	 * Test discount-code-update schema structure.
	 *
	 * @return void
	 */
	public function test_discount_code_update_schema_structure(): void {
		$schema = $this->load_schema('discount-code-update.php');
		$this->assert_schema_structure($schema, 'discount-code-update.php');
	}

	/**
	 * Test discount-code-update has no required fields.
	 *
	 * @return void
	 */
	public function test_discount_code_update_all_fields_optional(): void {
		$schema = $this->load_schema('discount-code-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in discount-code-update.php must be optional"
			);
		}
	}

	// =========================================================================
	// Domain Schemas
	// =========================================================================

	/**
	 * Test domain-create schema structure.
	 *
	 * @return void
	 */
	public function test_domain_create_schema_structure(): void {
		$schema = $this->load_schema('domain-create.php');
		$this->assert_schema_structure($schema, 'domain-create.php');
	}

	/**
	 * Test domain-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_domain_create_required_fields(): void {
		$schema = $this->load_schema('domain-create.php');
		$this->assert_required_fields(
			$schema,
			['domain', 'blog_id', 'stage'],
			'domain-create.php'
		);
	}

	/**
	 * Test domain-create stage enum has valid values.
	 *
	 * @return void
	 */
	public function test_domain_create_stage_enum_is_valid(): void {
		$schema = $this->load_schema('domain-create.php');
		$this->assertArrayHasKey('stage', $schema);
		$this->assertArrayHasKey('enum', $schema['stage']);
		$enum = $schema['stage']['enum'];
		$this->assertIsArray($enum);
		$this->assertNotEmpty($enum);
		// All enum values must be non-empty strings
		foreach ( $enum as $value ) {
			$this->assertIsString($value);
			$this->assertNotEmpty($value);
		}
	}

	/**
	 * Test domain-update schema structure.
	 *
	 * @return void
	 */
	public function test_domain_update_schema_structure(): void {
		$schema = $this->load_schema('domain-update.php');
		$this->assert_schema_structure($schema, 'domain-update.php');
	}

	/**
	 * Test domain-update has no required fields.
	 *
	 * @return void
	 */
	public function test_domain_update_all_fields_optional(): void {
		$schema = $this->load_schema('domain-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in domain-update.php must be optional"
			);
		}
	}

	// =========================================================================
	// Site Schemas
	// =========================================================================

	/**
	 * Test site-create schema structure.
	 *
	 * @return void
	 */
	public function test_site_create_schema_structure(): void {
		$schema = $this->load_schema('site-create.php');
		$this->assert_schema_structure($schema, 'site-create.php');
	}

	/**
	 * Test site-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_site_create_required_fields(): void {
		$schema = $this->load_schema('site-create.php');
		$this->assert_required_fields(
			$schema,
			['site_id', 'title', 'name', 'description', 'path', 'customer_id', 'membership_id', 'type'],
			'site-create.php'
		);
	}

	/**
	 * Test site-create type enum has valid values.
	 *
	 * @return void
	 */
	public function test_site_create_type_enum(): void {
		$schema = $this->load_schema('site-create.php');
		$this->assertArrayHasKey('type', $schema);
		$enum = $schema['type']['enum'];
		$this->assertContains('default', $enum);
		$this->assertContains('site_template', $enum);
		$this->assertContains('customer_owned', $enum);
		$this->assertContains('pending', $enum);
		$this->assertContains('external', $enum);
		$this->assertContains('main', $enum);
	}

	/**
	 * Test site-update schema structure.
	 *
	 * @return void
	 */
	public function test_site_update_schema_structure(): void {
		$schema = $this->load_schema('site-update.php');
		$this->assert_schema_structure($schema, 'site-update.php');
	}

	/**
	 * Test site-update has no required fields.
	 *
	 * @return void
	 */
	public function test_site_update_all_fields_optional(): void {
		$schema = $this->load_schema('site-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in site-update.php must be optional"
			);
		}
	}

	// =========================================================================
	// Broadcast Schemas
	// =========================================================================

	/**
	 * Test broadcast-create schema structure.
	 *
	 * @return void
	 */
	public function test_broadcast_create_schema_structure(): void {
		$schema = $this->load_schema('broadcast-create.php');
		$this->assert_schema_structure($schema, 'broadcast-create.php');
	}

	/**
	 * Test broadcast-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_broadcast_create_required_fields(): void {
		$schema = $this->load_schema('broadcast-create.php');
		$this->assert_required_fields(
			$schema,
			['type', 'title', 'content'],
			'broadcast-create.php'
		);
	}

	/**
	 * Test broadcast-create notice_type enum has valid values.
	 *
	 * @return void
	 */
	public function test_broadcast_create_notice_type_enum(): void {
		$schema = $this->load_schema('broadcast-create.php');
		$this->assertArrayHasKey('notice_type', $schema);
		$enum = $schema['notice_type']['enum'];
		$this->assertContains('info', $enum);
		$this->assertContains('success', $enum);
		$this->assertContains('warning', $enum);
		$this->assertContains('error', $enum);
		$this->assertCount(4, $enum, 'notice_type enum must have exactly 4 values');
	}

	/**
	 * Test broadcast-update schema structure.
	 *
	 * @return void
	 */
	public function test_broadcast_update_schema_structure(): void {
		$schema = $this->load_schema('broadcast-update.php');
		$this->assert_schema_structure($schema, 'broadcast-update.php');
	}

	/**
	 * Test broadcast-update has no required fields.
	 *
	 * @return void
	 */
	public function test_broadcast_update_all_fields_optional(): void {
		$schema = $this->load_schema('broadcast-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in broadcast-update.php must be optional"
			);
		}
	}

	// =========================================================================
	// Email Schemas
	// =========================================================================

	/**
	 * Test email-create schema structure.
	 *
	 * @return void
	 */
	public function test_email_create_schema_structure(): void {
		$schema = $this->load_schema('email-create.php');
		$this->assert_schema_structure($schema, 'email-create.php');
	}

	/**
	 * Test email-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_email_create_required_fields(): void {
		$schema = $this->load_schema('email-create.php');
		$this->assert_required_fields(
			$schema,
			['event', 'target', 'title'],
			'email-create.php'
		);
	}

	/**
	 * Test email-create style enum has valid values.
	 *
	 * @return void
	 */
	public function test_email_create_style_enum(): void {
		$schema = $this->load_schema('email-create.php');
		$this->assertArrayHasKey('style', $schema);
		$enum = $schema['style']['enum'];
		$this->assertContains('html', $enum);
		$this->assertContains('plain-text', $enum);
		$this->assertCount(2, $enum, 'email style enum must have exactly 2 values');
	}

	/**
	 * Test email-create target enum has valid values.
	 *
	 * @return void
	 */
	public function test_email_create_target_enum(): void {
		$schema = $this->load_schema('email-create.php');
		$this->assertArrayHasKey('target', $schema);
		$enum = $schema['target']['enum'];
		$this->assertContains('customer', $enum);
		$this->assertContains('admin', $enum);
		$this->assertCount(2, $enum, 'email target enum must have exactly 2 values');
	}

	/**
	 * Test email-create schedule_type enum has valid values.
	 *
	 * @return void
	 */
	public function test_email_create_schedule_type_enum(): void {
		$schema = $this->load_schema('email-create.php');
		$this->assertArrayHasKey('schedule_type', $schema);
		$enum = $schema['schedule_type']['enum'];
		$this->assertContains('days', $enum);
		$this->assertContains('hours', $enum);
		$this->assertCount(2, $enum, 'email schedule_type enum must have exactly 2 values');
	}

	/**
	 * Test email-update schema structure.
	 *
	 * @return void
	 */
	public function test_email_update_schema_structure(): void {
		$schema = $this->load_schema('email-update.php');
		$this->assert_schema_structure($schema, 'email-update.php');
	}

	/**
	 * Test email-update has no required fields.
	 *
	 * @return void
	 */
	public function test_email_update_all_fields_optional(): void {
		$schema = $this->load_schema('email-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in email-update.php must be optional"
			);
		}
	}

	// =========================================================================
	// Event Schemas
	// =========================================================================

	/**
	 * Test event-create schema structure.
	 *
	 * @return void
	 */
	public function test_event_create_schema_structure(): void {
		$schema = $this->load_schema('event-create.php');
		$this->assert_schema_structure($schema, 'event-create.php');
	}

	/**
	 * Test event-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_event_create_required_fields(): void {
		$schema = $this->load_schema('event-create.php');
		$this->assert_required_fields(
			$schema,
			['severity', 'payload', 'initiator', 'object_type', 'slug'],
			'event-create.php'
		);
	}

	/**
	 * Test event-create initiator enum has valid values.
	 *
	 * @return void
	 */
	public function test_event_create_initiator_enum(): void {
		$schema = $this->load_schema('event-create.php');
		$this->assertArrayHasKey('initiator', $schema);
		$enum = $schema['initiator']['enum'];
		$this->assertContains('system', $enum);
		$this->assertContains('manual', $enum);
		$this->assertCount(2, $enum, 'initiator enum must have exactly 2 values');
	}

	/**
	 * Test event-create payload field type is object.
	 *
	 * @return void
	 */
	public function test_event_create_payload_type_is_object(): void {
		$schema = $this->load_schema('event-create.php');
		$this->assertArrayHasKey('payload', $schema);
		$this->assertSame('object', $schema['payload']['type']);
	}

	/**
	 * Test event-update schema structure.
	 *
	 * @return void
	 */
	public function test_event_update_schema_structure(): void {
		$schema = $this->load_schema('event-update.php');
		$this->assert_schema_structure($schema, 'event-update.php');
	}

	/**
	 * Test event-update has no required fields.
	 *
	 * @return void
	 */
	public function test_event_update_all_fields_optional(): void {
		$schema = $this->load_schema('event-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in event-update.php must be optional"
			);
		}
	}

	// =========================================================================
	// Webhook Schemas
	// =========================================================================

	/**
	 * Test webhook-create schema structure.
	 *
	 * @return void
	 */
	public function test_webhook_create_schema_structure(): void {
		$schema = $this->load_schema('webhook-create.php');
		$this->assert_schema_structure($schema, 'webhook-create.php');
	}

	/**
	 * Test webhook-create has correct required fields.
	 *
	 * @return void
	 */
	public function test_webhook_create_required_fields(): void {
		$schema = $this->load_schema('webhook-create.php');
		$this->assert_required_fields(
			$schema,
			['name', 'webhook_url', 'event', 'integration'],
			'webhook-create.php'
		);
	}

	/**
	 * Test webhook-create optional fields.
	 *
	 * @return void
	 */
	public function test_webhook_create_optional_fields(): void {
		$schema = $this->load_schema('webhook-create.php');
		$this->assert_optional_fields(
			$schema,
			['event_count', 'active', 'hidden', 'date_created', 'date_last_failed', 'date_modified', 'migrated_from_id', 'skip_validation'],
			'webhook-create.php'
		);
	}

	/**
	 * Test webhook-update schema structure.
	 *
	 * @return void
	 */
	public function test_webhook_update_schema_structure(): void {
		$schema = $this->load_schema('webhook-update.php');
		$this->assert_schema_structure($schema, 'webhook-update.php');
	}

	/**
	 * Test webhook-update has no required fields.
	 *
	 * @return void
	 */
	public function test_webhook_update_all_fields_optional(): void {
		$schema = $this->load_schema('webhook-update.php');
		foreach ( $schema as $field_name => $field_def ) {
			$this->assertFalse(
				$field_def['required'],
				"Field '{$field_name}' in webhook-update.php must be optional"
			);
		}
	}

	// =========================================================================
	// Cross-schema consistency tests
	// =========================================================================

	/**
	 * Test that all create schemas have a skip_validation field.
	 *
	 * @return void
	 */
	public function test_all_create_schemas_have_skip_validation(): void {
		$create_schemas = [
			'broadcast-create.php',
			'checkout-form-create.php',
			'customer-create.php',
			'discount-code-create.php',
			'domain-create.php',
			'email-create.php',
			'event-create.php',
			'membership-create.php',
			'payment-create.php',
			'product-create.php',
			'site-create.php',
			'webhook-create.php',
		];
		foreach ( $create_schemas as $filename ) {
			$schema = $this->load_schema($filename);
			$this->assertArrayHasKey(
				'skip_validation',
				$schema,
				"Schema {$filename} must have a skip_validation field"
			);
			$this->assertFalse(
				$schema['skip_validation']['required'],
				"skip_validation in {$filename} must be optional"
			);
			$this->assertSame(
				'boolean',
				$schema['skip_validation']['type'],
				"skip_validation in {$filename} must be of type boolean"
			);
		}
	}

	/**
	 * Test that all create schemas have a migrated_from_id field.
	 *
	 * @return void
	 */
	public function test_all_create_schemas_have_migrated_from_id(): void {
		$create_schemas = [
			'broadcast-create.php',
			'checkout-form-create.php',
			'customer-create.php',
			'discount-code-create.php',
			'domain-create.php',
			'email-create.php',
			'event-create.php',
			'membership-create.php',
			'payment-create.php',
			'product-create.php',
			'site-create.php',
			'webhook-create.php',
		];
		foreach ( $create_schemas as $filename ) {
			$schema = $this->load_schema($filename);
			$this->assertArrayHasKey(
				'migrated_from_id',
				$schema,
				"Schema {$filename} must have a migrated_from_id field"
			);
			$this->assertFalse(
				$schema['migrated_from_id']['required'],
				"migrated_from_id in {$filename} must be optional"
			);
			$this->assertSame(
				'integer',
				$schema['migrated_from_id']['type'],
				"migrated_from_id in {$filename} must be of type integer"
			);
		}
	}

	/**
	 * Test that all update schemas have no required fields.
	 *
	 * This is a fundamental API design constraint: update (PATCH) operations
	 * must not require any fields — only the fields being updated need to be sent.
	 *
	 * @return void
	 */
	public function test_all_update_schemas_have_no_required_fields(): void {
		$update_schemas = [
			'broadcast-update.php',
			'checkout-form-update.php',
			'customer-update.php',
			'discount-code-update.php',
			'domain-update.php',
			'email-update.php',
			'event-update.php',
			'membership-update.php',
			'payment-update.php',
			'product-update.php',
			'site-update.php',
			'webhook-update.php',
		];
		foreach ( $update_schemas as $filename ) {
			$schema = $this->load_schema($filename);
			foreach ( $schema as $field_name => $field_def ) {
				$this->assertFalse(
					$field_def['required'],
					"Field '{$field_name}' in {$filename} must be optional (update schemas must have no required fields)"
				);
			}
		}
	}

	/**
	 * Test that all 24 schema files exist and are loadable.
	 *
	 * @return void
	 */
	public function test_all_24_schema_files_exist(): void {
		$expected_files = [
			'broadcast-create.php',
			'broadcast-update.php',
			'checkout-form-create.php',
			'checkout-form-update.php',
			'customer-create.php',
			'customer-update.php',
			'discount-code-create.php',
			'discount-code-update.php',
			'domain-create.php',
			'domain-update.php',
			'email-create.php',
			'email-update.php',
			'event-create.php',
			'event-update.php',
			'membership-create.php',
			'membership-update.php',
			'payment-create.php',
			'payment-update.php',
			'product-create.php',
			'product-update.php',
			'site-create.php',
			'site-update.php',
			'webhook-create.php',
			'webhook-update.php',
		];
		$this->assertCount(24, $expected_files, 'Must test exactly 24 schema files');
		foreach ( $expected_files as $filename ) {
			$path = $this->schemas_dir . '/' . $filename;
			$this->assertFileExists($path, "Schema file {$filename} must exist");
			$schema = require $path;
			$this->assertIsArray($schema, "Schema {$filename} must return an array");
			$this->assertNotEmpty($schema, "Schema {$filename} must not be empty");
		}
	}

	/**
	 * Test that create/update schema pairs share the same field names.
	 *
	 * Update schemas should be a subset of (or equal to) create schemas.
	 *
	 * @return void
	 */
	public function test_update_schemas_fields_are_subset_of_create_schemas(): void {
		$pairs = [
			['checkout-form-create.php', 'checkout-form-update.php'],
			['payment-create.php', 'payment-update.php'],
			['membership-create.php', 'membership-update.php'],
			['customer-create.php', 'customer-update.php'],
			['product-create.php', 'product-update.php'],
			['discount-code-create.php', 'discount-code-update.php'],
			['domain-create.php', 'domain-update.php'],
			['site-create.php', 'site-update.php'],
			['broadcast-create.php', 'broadcast-update.php'],
			['email-create.php', 'email-update.php'],
			['event-create.php', 'event-update.php'],
			['webhook-create.php', 'webhook-update.php'],
		];
		foreach ( $pairs as [$create_file, $update_file] ) {
			$create_schema = $this->load_schema($create_file);
			$update_schema = $this->load_schema($update_file);
			$create_keys   = array_keys($create_schema);
			$update_keys   = array_keys($update_schema);
			$extra_in_update = array_diff($update_keys, $create_keys);
			$this->assertEmpty(
				$extra_in_update,
				"Update schema {$update_file} has fields not in create schema {$create_file}: " . implode(', ', $extra_in_update)
			);
		}
	}
}
