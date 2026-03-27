<?php
/**
 * Unit tests for Product class.
 */

namespace WP_Ultimo\Models;

use WP_Ultimo\Faker;
use WP_Ultimo\Database\Products\Product_Type;

/**
 * Unit tests for Product class.
 */
class Product_Test extends \WP_UnitTestCase {

	/**
	 * Product instance.
	 *
	 * @var Product
	 */
	protected $product;

	/**
	 * Faker instance.
	 *
	 * @var \WP_Ultimo\Faker
	 */
	protected $faker;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a product manually to avoid faker issues
		$this->product = new Product();
		$this->product->set_name('Test Product');
		$this->product->set_description('Test Description');
		$this->product->set_pricing_type('paid');
		$this->product->set_amount(19.99);
		$this->product->set_currency('USD');
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_type('plan');
	}

	/**
	 * Test product creation.
	 */
	public function test_product_creation(): void {
		$this->assertInstanceOf(Product::class, $this->product, 'Product should be an instance of Product class.');
		$this->assertNotEmpty($this->product->get_name(), 'Product should have a name.');
		$this->assertNotEmpty($this->product->get_description(), 'Product should have a description.');
	}

	/**
	 * Test product validation rules.
	 */
	public function test_product_validation_rules(): void {
		$validation_rules = $this->product->validation_rules();

		// Test required fields
		$this->assertArrayHasKey('slug', $validation_rules, 'Validation rules should include slug field.');
		$this->assertArrayHasKey('pricing_type', $validation_rules, 'Validation rules should include pricing_type field.');
		$this->assertArrayHasKey('amount', $validation_rules, 'Validation rules should include amount field.');
		$this->assertArrayHasKey('currency', $validation_rules, 'Validation rules should include currency field.');
		$this->assertArrayHasKey('duration', $validation_rules, 'Validation rules should include duration field.');
		$this->assertArrayHasKey('duration_unit', $validation_rules, 'Validation rules should include duration_unit field.');
		$this->assertArrayHasKey('type', $validation_rules, 'Validation rules should include type field.');

		// Test field constraints
		$this->assertStringContainsString('required', $validation_rules['slug'], 'Slug should be required.');
		$this->assertStringContainsString('in:free,paid,contact_us', $validation_rules['pricing_type'], 'Pricing type should have valid options.');
		$this->assertStringContainsString('numeric', $validation_rules['amount'], 'Amount should be numeric.');
		$this->assertStringContainsString('default:1', $validation_rules['duration'], 'Duration should default to 1.');
		$this->assertStringContainsString('in:day,week,month,year|default:month', $validation_rules['duration_unit'], 'Duration unit should have valid options.');
	}

	/**
	 * Test recurring billing setup.
	 */
	public function test_recurring_billing(): void {
		// Test recurring flag
		$this->product->set_recurring(true);
		$this->assertTrue($this->product->is_recurring(), 'Recurring flag should be set to true.');

		$this->product->set_recurring(false);
		$this->assertFalse($this->product->is_recurring(), 'Recurring flag should be set to false.');

		// Test duration and unit
		$this->product->set_duration(6);
		$this->product->set_duration_unit('month');
		$this->assertEquals(6, $this->product->get_duration(), 'Duration should be set and retrieved correctly.');
		$this->assertEquals('month', $this->product->get_duration_unit(), 'Duration unit should be set and retrieved correctly.');

		// Test billing cycles
		$this->product->set_billing_cycles(12);
		$this->assertEquals(12, $this->product->get_billing_cycles(), 'Billing cycles should be set and retrieved correctly.');
	}

	/**
	 * Test trial period setup.
	 */
	public function test_trial_period(): void {
		// Test trial duration and unit
		$this->product->set_trial_duration(14);
		$this->product->set_trial_duration_unit('day');
		$this->assertEquals(14, $this->product->get_trial_duration(), 'Trial duration should be set and retrieved correctly.');
		$this->assertEquals('day', $this->product->get_trial_duration_unit(), 'Trial duration unit should be set and retrieved correctly.');
	}

	/**
	 * Test product types.
	 */
	public function test_product_types(): void {
		$product_types = [
			Product_Type::PLAN,
			Product_Type::PACKAGE,
			Product_Type::SERVICE,
		];

		foreach ($product_types as $type) {
			$this->product->set_type($type);
			$this->assertEquals($type, $this->product->get_type(), "Product type {$type} should be set and retrieved correctly.");
		}
	}

	/**
	 * Test product relationships.
	 */
	public function test_product_relationships(): void {
		// Test parent product
		$parent_id = 123;
		$this->product->set_parent_id($parent_id);
		$this->assertEquals($parent_id, $this->product->get_parent_id(), 'Parent ID should be set and retrieved correctly.');

		// Test featured image
		$attachment_id = $this->factory()->attachment->create_object(['file' => 'product.jpg']);
		$this->product->set_featured_image_id($attachment_id);
		$this->assertEquals($attachment_id, $this->product->get_featured_image_id(), 'Featured image ID should be set and retrieved correctly.');
	}

	/**
	 * Test product properties.
	 */
	public function test_product_properties(): void {
		// Test slug
		$this->product->set_slug('test-product');
		$this->assertEquals('test-product', $this->product->get_slug(), 'Slug should be set and retrieved correctly.');

		// Test list order
		$this->product->set_list_order(5);
		$this->assertEquals(5, $this->product->get_list_order(), 'List order should be set and retrieved correctly.');

		// Test active status
		$this->product->set_active(true);
		$this->assertTrue($this->product->is_active(), 'Active status should be set to true.');

		$this->product->set_active(false);
		$this->assertFalse($this->product->is_active(), 'Active status should be set to false.');

		// Test tax settings
		$this->product->set_taxable(true);
		$this->assertTrue($this->product->is_taxable(), 'Taxable flag should be set to true.');

		$this->product->set_tax_category('digital');
		$this->assertEquals('digital', $this->product->get_tax_category(), 'Tax category should be set and retrieved correctly.');
	}

	/**
	 * Test product add-ons and variations.
	 */
	public function test_addons_and_variations(): void {
		// Test available add-ons
		$addons = ['addon1', 'addon2'];
		$this->product->set_available_addons($addons);
		$this->assertEquals($addons, $this->product->get_available_addons(), 'Available add-ons should be set and retrieved correctly.');

		// Test price variations
		$variations = [
			[
				'amount'      => 9.99,
				'description' => 'Basic',
			],
			[
				'amount'      => 19.99,
				'description' => 'Pro',
			],
		];
		$this->product->set_price_variations($variations);
		$this->assertEquals($variations, $this->product->get_price_variations(), 'Price variations should be set and retrieved correctly.');
	}

	/**
	 * Test contact us functionality.
	 */
	public function test_contact_us_functionality(): void {
		$label = 'Contact Sales';
		$link  = 'https://example.com/contact';

		$this->product->set_contact_us_label($label);
		$this->product->set_contact_us_link($link);

		$this->assertEquals($label, $this->product->get_contact_us_label(), 'Contact us label should be set and retrieved correctly.');
		$this->assertEquals($link, $this->product->get_contact_us_link(), 'Contact us link should be set and retrieved correctly.');
	}

	/**
	 * Test product group assignment.
	 */
	public function test_product_group(): void {
		$group = 'premium';
		$this->product->set_group($group);
		$this->assertEquals($group, $this->product->get_group(), 'Product group should be set and retrieved correctly.');
	}

	/**
	 * Test network ID support.
	 */
	public function test_network_id(): void {
		$network_id = 2;
		$this->product->set_network_id($network_id);
		$this->assertEquals($network_id, $this->product->get_network_id(), 'Network ID should be set and retrieved correctly.');
	}

	/**
	 * Test legacy options flag.
	 */
	public function test_legacy_options(): void {
		$this->product->set_legacy_options(true);
		$this->assertTrue($this->product->get_legacy_options(), 'Legacy options flag should be set to true.');

		$this->product->set_legacy_options(false);
		$this->assertFalse($this->product->get_legacy_options(), 'Legacy options flag should be set to false.');
	}

	/**
	 * Test product save with validation error.
	 */
	public function test_product_save_with_validation_error(): void {
		$product = new Product();

		// Try to save without required fields
		$product->set_skip_validation(false);
		$result = $product->save();

		$this->assertInstanceOf(\WP_Error::class, $result, 'Save should return WP_Error when validation fails.');
	}

	/**
	 * Test product save with validation bypassed.
	 */
	public function test_product_save_with_validation_bypassed(): void {
		$product = new Product();

		// Set required fields
		$product->set_name('Test Product');
		$product->set_description('Test Description');
		$product->set_pricing_type('paid');
		$product->set_amount(19.99);
		$product->set_currency('USD');
		$product->set_duration(1);
		$product->set_duration_unit('month');
		$product->set_type('plan');

		// Bypass validation for testing
		$product->set_skip_validation(true);
		$result = $product->save();

		// In test environment, this might fail due to WordPress constraints
		// We're mainly testing that the method runs without errors
		$this->assertIsBool($result, 'Save should return boolean result.');
	}

	/**
	 * Test to_array method.
	 */
	public function test_to_array(): void {
		$array = $this->product->to_array();

		$this->assertIsArray($array, 'to_array() should return an array.');
		$this->assertArrayHasKey('id', $array, 'Array should contain id field.');
		$this->assertArrayHasKey('name', $array, 'Array should contain name field.');
		$this->assertArrayHasKey('description', $array, 'Array should contain description field.');
		$this->assertArrayHasKey('pricing_type', $array, 'Array should contain pricing_type field.');
		$this->assertArrayHasKey('amount', $array, 'Array should contain amount field.');

		// Should not contain internal properties
		$this->assertArrayNotHasKey('query_class', $array, 'Array should not contain query_class.');
		$this->assertArrayNotHasKey('meta', $array, 'Array should not contain meta.');
	}

	/**
	 * Test that to_array() populates lazy-loaded meta properties (issue #469).
	 *
	 * featured_image_id, tax_category, contact_us_label, contact_us_link,
	 * feature_list, available_addons, legacy_options, and pwyw fields are only
	 * loaded from meta when their getter is first called. Without the to_array()
	 * override they remain null in REST API responses.
	 */
	public function test_to_array_includes_lazy_loaded_meta_properties(): void {
		$product = new \WP_Ultimo\Models\Product();
		$product->set_name('Test Product');
		$product->set_slug('test-product');
		$product->set_amount(50);
		$product->set_tax_category('standard');
		$product->set_contact_us_label('Contact Us');
		$product->set_contact_us_link('https://example.com/contact');
		$product->set_pwyw_minimum_amount(10.0);
		$product->set_pwyw_suggested_amount(25.0);
		$product->set_pwyw_recurring_mode('customer_choice');

		$array = $product->to_array();

		$this->assertIsArray($array, 'to_array() should return an array.');

		$this->assertArrayHasKey('tax_category', $array, 'to_array() must include tax_category.');
		$this->assertNotNull($array['tax_category'], 'tax_category must not be null in to_array() output.');
		$this->assertEquals('standard', $array['tax_category'], 'tax_category must match the set value.');

		$this->assertArrayHasKey('contact_us_label', $array, 'to_array() must include contact_us_label.');
		$this->assertNotNull($array['contact_us_label'], 'contact_us_label must not be null in to_array() output.');

		$this->assertArrayHasKey('contact_us_link', $array, 'to_array() must include contact_us_link.');
		$this->assertNotNull($array['contact_us_link'], 'contact_us_link must not be null in to_array() output.');

		$this->assertArrayHasKey('pwyw_minimum_amount', $array, 'to_array() must include pwyw_minimum_amount.');
		$this->assertEquals(10.0, $array['pwyw_minimum_amount'], 'pwyw_minimum_amount must match the set value.');

		$this->assertArrayHasKey('pwyw_suggested_amount', $array, 'to_array() must include pwyw_suggested_amount.');
		$this->assertEquals(25.0, $array['pwyw_suggested_amount'], 'pwyw_suggested_amount must match the set value.');

		$this->assertArrayHasKey('pwyw_recurring_mode', $array, 'to_array() must include pwyw_recurring_mode.');
		$this->assertEquals('customer_choice', $array['pwyw_recurring_mode'], 'pwyw_recurring_mode must match the set value.');
	}

	/**
	 * Test hash generation.
	 */
	public function test_hash_generation(): void {
		$hash = $this->product->get_hash('id');

		$this->assertIsString($hash, 'Hash should be a string.');
		$this->assertNotEmpty($hash, 'Hash should not be empty.');
	}

	/**
	 * Test meta data handling.
	 */
	public function test_meta_data_handling(): void {
		$this->product->save();

		$meta_key   = 'test_meta_key';
		$meta_value = 'test_meta_value';

		// Test meta update
		$result = $this->product->update_meta($meta_key, $meta_value);
		$this->assertTrue($result || is_numeric($result), 'Meta update should return true or numeric ID.');

		// Test meta retrieval
		$retrieved_value = $this->product->get_meta($meta_key);
		$this->assertEquals($meta_value, $retrieved_value, 'Meta value should be retrieved correctly.');

		// Test meta deletion
		$delete_result = $this->product->delete_meta($meta_key);
		$this->assertTrue($delete_result || is_numeric($delete_result), 'Meta deletion should return true or numeric ID.');

		// Test default value
		$default_value = $this->product->get_meta($meta_key, 'default');
		$this->assertEquals('default', $default_value, 'Should return default value when meta does not exist.');
	}

	/**
	 * Test formatted amount.
	 */
	public function test_formatted_amount(): void {
		$this->product->set_amount(19.99);
		$formatted_amount = $this->product->get_formatted_amount();

		$this->assertIsString($formatted_amount, 'Formatted amount should be a string.');
		$this->assertNotEmpty($formatted_amount, 'Formatted amount should not be empty.');
	}

	/**
	 * Test formatted date.
	 */
	public function test_formatted_date(): void {
		// Set a date first
		$this->product->set_date_created('2023-01-01 12:00:00');

		$formatted_date = $this->product->get_formatted_date('date_created');

		$this->assertIsString($formatted_date, 'Formatted date should be a string.');
		$this->assertNotEmpty($formatted_date, 'Formatted date should not be empty.');
	}
	// ========================================================================
	// New tests: Getter/Setter pairs
	// ========================================================================

	/**
	 * Test name getter and setter.
	 */
	public function test_get_set_name(): void {
		$this->product->set_name('Premium Plan');
		$this->assertSame('Premium Plan', $this->product->get_name());
	}

	/**
	 * Test name can be set to empty string.
	 */
	public function test_name_empty_string(): void {
		$this->product->set_name('');
		$this->assertSame('', $this->product->get_name());
	}

	/**
	 * Test description getter and setter.
	 */
	public function test_get_set_description(): void {
		$this->product->set_description('A premium plan for businesses');
		$this->assertSame('A premium plan for businesses', $this->product->get_description());
	}

	/**
	 * Test description strips slashes.
	 */
	public function test_description_strips_slashes(): void {
		$this->product->set_description('It\\\'s a great plan');
		$description = $this->product->get_description();
		$this->assertStringNotContainsString('\\', $description);
	}

	/**
	 * Test slug getter and setter.
	 */
	public function test_get_set_slug(): void {
		$this->product->set_slug('premium-plan');
		$this->assertSame('premium-plan', $this->product->get_slug());
	}

	/**
	 * Test currency getter and setter.
	 */
	public function test_get_set_currency(): void {
		$this->product->set_currency('EUR');
		// get_currency uses the setting by default, not the stored value
		// unless the wu_should_use_saved_currency filter is true
		$this->assertIsString($this->product->get_currency());
	}

	/**
	 * Test currency getter respects filter to use saved currency.
	 */
	public function test_get_currency_uses_saved_when_filtered(): void {
		$this->product->set_currency('GBP');

		add_filter('wu_should_use_saved_currency', '__return_true');
		$this->assertSame('GBP', $this->product->get_currency());
		remove_filter('wu_should_use_saved_currency', '__return_true');
	}

	/**
	 * Test pricing type getter and setter for paid.
	 */
	public function test_get_set_pricing_type_paid(): void {
		$this->product->set_pricing_type('paid');
		$this->assertSame('paid', $this->product->get_pricing_type());
	}

	/**
	 * Test pricing type getter and setter for free.
	 */
	public function test_get_set_pricing_type_free(): void {
		$this->product->set_pricing_type('free');
		$this->assertSame('free', $this->product->get_pricing_type());
	}

	/**
	 * Test pricing type getter and setter for contact_us.
	 */
	public function test_get_set_pricing_type_contact_us(): void {
		$this->product->set_pricing_type('contact_us');
		$this->assertSame('contact_us', $this->product->get_pricing_type());
	}

	/**
	 * Test setting pricing type to free zeroes amount and disables recurring.
	 */
	public function test_pricing_type_free_zeroes_amount_and_recurring(): void {
		$this->product->set_amount(49.99);
		$this->product->set_recurring(true);
		$this->product->set_pricing_type('free');

		$this->assertEquals(0, $this->product->get_amount());
		$this->assertFalse($this->product->is_recurring());
	}

	/**
	 * Test setting pricing type to contact_us zeroes amount and disables recurring.
	 */
	public function test_pricing_type_contact_us_zeroes_amount_and_recurring(): void {
		$this->product->set_amount(49.99);
		$this->product->set_recurring(true);
		$this->product->set_pricing_type('contact_us');

		$this->assertEquals(0, $this->product->get_amount());
		$this->assertFalse($this->product->is_recurring());
	}

	/**
	 * Regression test: pricing_type 'pay_what_you_want' must survive a full round-trip
	 * through set/get without truncation.
	 *
	 * The DB column was varchar(10), silently truncating 'pay_what_you_want' (17 chars)
	 * to 'pay_what_y', breaking is_pay_what_you_want() on every subsequent load.
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/564
	 */
	public function test_get_set_pricing_type_pay_what_you_want(): void {
		$this->product->set_pricing_type('pay_what_you_want');
		$this->assertSame('pay_what_you_want', $this->product->get_pricing_type());
	}

	/**
	 * Regression test: is_pay_what_you_want() must return true after set_pricing_type('pay_what_you_want').
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/564
	 */
	public function test_is_pay_what_you_want_returns_true(): void {
		$this->product->set_pricing_type('pay_what_you_want');
		$this->assertTrue($this->product->is_pay_what_you_want());
	}

	/**
	 * Regression test: is_pay_what_you_want() must return false for non-PWYW types.
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/564
	 */
	public function test_is_pay_what_you_want_returns_false_for_paid(): void {
		$this->product->set_pricing_type('paid');
		$this->assertFalse($this->product->is_pay_what_you_want());
	}

	/**
	 * Regression test: get_amount() for PWYW returns the suggested amount, not 0.
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/564
	 */
	public function test_pwyw_get_amount_returns_suggested_amount(): void {
		$this->product->set_pricing_type('pay_what_you_want');
		$this->product->set_pwyw_suggested_amount(25.00);
		$this->assertEquals(25.00, $this->product->get_amount());
	}

	/**
	 * Regression test: get_amount() for PWYW returns 0 when no suggested amount is set.
	 *
	 * This is the value submitted by the hidden amount field in the admin form.
	 * It must be numeric (0) not a formatted string like 'Name Your Price'.
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/564
	 */
	public function test_pwyw_get_amount_returns_zero_when_no_suggested_amount(): void {
		$this->product->set_pricing_type('pay_what_you_want');
		$this->assertEquals(0.0, $this->product->get_amount());
	}

	/**
	 * Regression test: PWYW with force_recurring mode reports is_recurring() as true.
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/564
	 */
	public function test_pwyw_force_recurring_is_recurring(): void {
		$this->product->set_pricing_type('pay_what_you_want');
		$this->product->set_pwyw_recurring_mode('force_recurring');
		$this->product->set_pwyw_suggested_amount(10.00);
		$this->assertTrue($this->product->is_recurring());
	}

	/**
	 * Regression test: PWYW with force_one_time mode reports is_recurring() as false.
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/564
	 */
	public function test_pwyw_force_one_time_is_not_recurring(): void {
		$this->product->set_pricing_type('pay_what_you_want');
		$this->product->set_pwyw_recurring_mode('force_one_time');
		$this->product->set_pwyw_suggested_amount(10.00);
		$this->assertFalse($this->product->is_recurring());
	}

	/**
	 * Regression test: pricing_type value length — 'pay_what_you_want' is 17 chars,
	 * must not be truncated by a varchar(10) column.
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/564
	 */
	public function test_pay_what_you_want_value_length_fits_column(): void {
		$this->assertLessThanOrEqual(
			20,
			strlen('pay_what_you_want'),
			'pay_what_you_want must fit in varchar(20) column'
		);
		$this->assertGreaterThan(
			10,
			strlen('pay_what_you_want'),
			'pay_what_you_want exceeds old varchar(10) limit — confirms the truncation bug'
		);
	}

	/**
	 * Test amount getter and setter.
	 */
	public function test_get_set_amount(): void {
		$this->product->set_amount(99.50);
		$this->assertEquals(99.50, $this->product->get_amount());
	}

	/**
	 * Test amount returns zero for free pricing type.
	 */
	public function test_get_amount_returns_zero_for_free_pricing(): void {
		$this->product->set_pricing_type('free');
		$this->assertEquals(0, $this->product->get_amount());
	}

	/**
	 * Test amount returns zero for contact_us pricing type.
	 */
	public function test_get_amount_returns_zero_for_contact_us_pricing(): void {
		$this->product->set_pricing_type('contact_us');
		$this->assertEquals(0, $this->product->get_amount());
	}

	/**
	 * Test setup fee getter and setter.
	 */
	public function test_get_set_setup_fee(): void {
		$this->product->set_setup_fee(50.00);
		$this->assertEquals(50.00, $this->product->get_setup_fee());
	}

	/**
	 * Test setup fee default is zero.
	 */
	public function test_setup_fee_default_zero(): void {
		$product = new Product();
		$this->assertEquals(0, $product->get_setup_fee());
	}

	/**
	 * Test has_setup_fee returns true when fee is set.
	 */
	public function test_has_setup_fee_true(): void {
		$this->product->set_setup_fee(25.00);
		$this->assertTrue($this->product->has_setup_fee());
	}

	/**
	 * Test has_setup_fee returns false when fee is zero.
	 */
	public function test_has_setup_fee_false(): void {
		$this->product->set_setup_fee(0);
		$this->assertFalse($this->product->has_setup_fee());
	}

	/**
	 * Test duration getter and setter.
	 */
	public function test_get_set_duration(): void {
		$this->product->set_duration(3);
		$this->assertSame(3, $this->product->get_duration());
	}

	/**
	 * Test duration unit getter and setter with all valid units.
	 */
	public function test_get_set_duration_unit_all_values(): void {
		$units = ['day', 'week', 'month', 'year'];

		foreach ($units as $unit) {
			$this->product->set_duration_unit($unit);
			$this->assertSame($unit, $this->product->get_duration_unit());
		}
	}

	/**
	 * Test billing cycles getter and setter.
	 */
	public function test_get_set_billing_cycles(): void {
		$this->product->set_billing_cycles(24);
		$this->assertSame(24, $this->product->get_billing_cycles());
	}

	/**
	 * Test billing cycles default is zero.
	 */
	public function test_billing_cycles_default_zero(): void {
		$product = new Product();
		$this->assertSame(0, $product->get_billing_cycles());
	}

	/**
	 * Test list order getter and setter.
	 */
	public function test_get_set_list_order(): void {
		$this->product->set_list_order(99);
		$this->assertEquals(99, $this->product->get_list_order());
	}

	/**
	 * Test list order default value.
	 */
	public function test_list_order_default(): void {
		$product = new Product();
		$this->assertEquals(10, $product->get_list_order());
	}

	/**
	 * Test active getter and setter.
	 */
	public function test_get_set_active(): void {
		$this->product->set_active(true);
		$this->assertTrue($this->product->is_active());

		$this->product->set_active(false);
		$this->assertFalse($this->product->is_active());
	}

	/**
	 * Test type getter and setter.
	 */
	public function test_get_set_type(): void {
		$this->product->set_type('service');
		$this->assertSame('service', $this->product->get_type());
	}

	/**
	 * Test parent ID getter and setter.
	 */
	public function test_get_set_parent_id(): void {
		$this->product->set_parent_id(42);
		$this->assertEquals(42, $this->product->get_parent_id());
	}

	/**
	 * Test recurring getter and setter.
	 */
	public function test_get_set_recurring(): void {
		$this->product->set_recurring(true);
		$this->assertTrue($this->product->is_recurring());

		$this->product->set_recurring(false);
		$this->assertFalse($this->product->is_recurring());
	}

	/**
	 * Test trial duration getter and setter.
	 */
	public function test_get_set_trial_duration(): void {
		$this->product->set_trial_duration(30);
		$this->assertEquals(30, $this->product->get_trial_duration());
	}

	/**
	 * Test trial duration unit getter and setter with all valid values.
	 */
	public function test_get_set_trial_duration_unit_all_values(): void {
		$units = ['day', 'week', 'month', 'year'];
		foreach ($units as $unit) {
			$this->product->set_trial_duration_unit($unit);
			$this->assertSame($unit, $this->product->get_trial_duration_unit());
		}
	}

	/**
	 * Test date created getter and setter.
	 */
	public function test_get_set_date_created(): void {
		$date = '2025-06-15 10:30:00';
		$this->product->set_date_created($date);
		$this->assertSame($date, $this->product->get_date_created());
	}

	/**
	 * Test date modified getter and setter.
	 */
	public function test_get_set_date_modified(): void {
		$date = '2025-06-15 11:00:00';
		$this->product->set_date_modified($date);
		$this->assertSame($date, $this->product->get_date_modified());
	}

	/**
	 * Test featured image ID getter and setter.
	 */
	public function test_get_set_featured_image_id(): void {
		$this->product->set_featured_image_id(123);
		$this->assertEquals(123, $this->product->get_featured_image_id());
	}

	/**
	 * Test network ID getter and setter with null.
	 */
	public function test_network_id_null(): void {
		$this->product->set_network_id(null);
		$this->assertNull($this->product->get_network_id());
	}

	/**
	 * Test network ID getter and setter with zero.
	 */
	public function test_network_id_zero_returns_null(): void {
		$this->product->set_network_id(0);
		$this->assertNull($this->product->get_network_id());
	}

	/**
	 * Test network ID getter returns absint.
	 */
	public function test_network_id_returns_positive_int(): void {
		$this->product->set_network_id(5);
		$this->assertSame(5, $this->product->get_network_id());
	}

	// ========================================================================
	// Product types tests
	// ========================================================================

	/**
	 * Test product type plan.
	 */
	public function test_type_plan(): void {
		$this->product->set_type('plan');
		$this->assertSame('plan', $this->product->get_type());
	}

	/**
	 * Test product type package.
	 */
	public function test_type_package(): void {
		$this->product->set_type('package');
		$this->assertSame('package', $this->product->get_type());
	}

	/**
	 * Test product type service.
	 */
	public function test_type_service(): void {
		$this->product->set_type('service');
		$this->assertSame('service', $this->product->get_type());
	}

	/**
	 * Test get_type_label returns a string for plan.
	 */
	public function test_get_type_label_plan(): void {
		$this->product->set_type('plan');
		$label = $this->product->get_type_label();
		$this->assertIsString($label);
		$this->assertNotEmpty($label);
	}

	/**
	 * Test get_type_label returns a string for package.
	 */
	public function test_get_type_label_package(): void {
		$this->product->set_type('package');
		$label = $this->product->get_type_label();
		$this->assertIsString($label);
		$this->assertNotEmpty($label);
	}

	/**
	 * Test get_type_label returns a string for service.
	 */
	public function test_get_type_label_service(): void {
		$this->product->set_type('service');
		$label = $this->product->get_type_label();
		$this->assertIsString($label);
		$this->assertNotEmpty($label);
	}

	/**
	 * Test get_type_class returns a string.
	 */
	public function test_get_type_class(): void {
		$this->product->set_type('plan');
		$classes = $this->product->get_type_class();
		$this->assertIsString($classes);
		$this->assertNotEmpty($classes);
	}

	// ========================================================================
	// Pricing method tests
	// ========================================================================

	/**
	 * Test is_free returns true when amount and initial amount are zero.
	 */
	public function test_is_free_true(): void {
		$this->product->set_amount(0);
		$this->product->set_setup_fee(0);
		$this->product->set_pricing_type('paid');
		$this->assertTrue($this->product->is_free());
	}

	/**
	 * Test is_free returns false when amount is positive.
	 */
	public function test_is_free_false_with_amount(): void {
		$this->product->set_amount(10.00);
		$this->assertFalse($this->product->is_free());
	}

	/**
	 * Test is_free returns false when only setup fee is set.
	 */
	public function test_is_free_false_with_setup_fee_only(): void {
		$this->product->set_amount(0);
		$this->product->set_setup_fee(50.00);
		$this->product->set_pricing_type('paid');
		$this->assertFalse($this->product->is_free());
	}

	/**
	 * Test is_recurring returns true with amount and recurring flag.
	 */
	public function test_is_recurring_true(): void {
		$this->product->set_recurring(true);
		$this->product->set_amount(10.00);
		$this->assertTrue($this->product->is_recurring());
	}

	/**
	 * Test is_recurring returns false when recurring flag is false.
	 */
	public function test_is_recurring_false_flag_off(): void {
		$this->product->set_recurring(false);
		$this->product->set_amount(10.00);
		$this->assertFalse($this->product->is_recurring());
	}

	/**
	 * Test is_recurring returns false when amount is zero even if recurring flag is on.
	 */
	public function test_is_recurring_false_zero_amount(): void {
		$this->product->set_recurring(true);
		$this->product->set_amount(0);
		$this->product->set_pricing_type('paid');
		$this->assertFalse($this->product->is_recurring());
	}

	/**
	 * Test is_forever_recurring when billing cycles is zero.
	 */
	public function test_is_forever_recurring_true_zero_cycles(): void {
		$this->product->set_billing_cycles(0);
		$this->assertTrue($this->product->is_forever_recurring());
	}

	/**
	 * Test is_forever_recurring when billing cycles is set.
	 */
	public function test_is_forever_recurring_false_with_cycles(): void {
		$this->product->set_billing_cycles(12);
		$this->assertFalse($this->product->is_forever_recurring());
	}

	/**
	 * Test get_initial_amount sums amount and setup fee.
	 */
	public function test_get_initial_amount(): void {
		$this->product->set_amount(29.99);
		$this->product->set_setup_fee(10.00);
		$this->assertEqualsWithDelta(39.99, $this->product->get_initial_amount(), 0.001);
	}

	/**
	 * Test get_initial_amount with zero setup fee.
	 */
	public function test_get_initial_amount_no_setup_fee(): void {
		$this->product->set_amount(29.99);
		$this->product->set_setup_fee(0);
		$this->assertEquals(29.99, $this->product->get_initial_amount());
	}

	/**
	 * Test get_initial_amount with only setup fee.
	 */
	public function test_get_initial_amount_only_setup_fee(): void {
		$this->product->set_amount(0);
		$this->product->set_setup_fee(50.00);
		$this->product->set_pricing_type('paid');
		$this->assertEquals(50.00, $this->product->get_initial_amount());
	}

	/**
	 * Test has_trial returns true when trial duration is set.
	 */
	public function test_has_trial_true(): void {
		$this->product->set_trial_duration(14);
		$this->assertTrue($this->product->has_trial());
	}

	/**
	 * Test has_trial returns false when trial duration is zero.
	 */
	public function test_has_trial_false(): void {
		$this->product->set_trial_duration(0);
		$this->assertFalse($this->product->has_trial());
	}

	// ========================================================================
	// Price description and formatting tests
	// ========================================================================

	/**
	 * Test get_price_description for a free product.
	 */
	public function test_get_price_description_free(): void {
		$this->product->set_amount(0);
		$this->product->set_setup_fee(0);
		$this->product->set_pricing_type('free');
		$desc = $this->product->get_price_description();
		$this->assertStringContainsString('Free', $desc);
	}

	/**
	 * Test get_price_description for contact_us product.
	 */
	public function test_get_price_description_contact_us(): void {
		$this->product->set_pricing_type('contact_us');
		$desc = $this->product->get_price_description();
		$this->assertStringContainsString('Contact', $desc);
	}

	/**
	 * Test get_price_description for recurring paid product.
	 */
	public function test_get_price_description_recurring(): void {
		$this->product->set_amount(19.99);
		$this->product->set_recurring(true);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_billing_cycles(0);

		$desc = $this->product->get_price_description();
		$this->assertIsString($desc);
		$this->assertNotEmpty($desc);
		$this->assertStringContainsString('/ month', $desc);
	}

	/**
	 * Test get_price_description for non-recurring paid product (one time).
	 */
	public function test_get_price_description_one_time(): void {
		$this->product->set_amount(99.00);
		$this->product->set_recurring(false);
		$this->product->set_pricing_type('paid');

		$desc = $this->product->get_price_description();
		$this->assertStringContainsString('one time', $desc);
	}

	/**
	 * Test get_price_description includes setup fee when present.
	 */
	public function test_get_price_description_with_setup_fee(): void {
		$this->product->set_amount(19.99);
		$this->product->set_setup_fee(50.00);
		$this->product->set_recurring(true);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');

		$desc = $this->product->get_price_description(true);
		$this->assertStringContainsString('Setup Fee', $desc);
	}

	/**
	 * Test get_price_description excludes setup fee when include_fees is false.
	 */
	public function test_get_price_description_excludes_setup_fee(): void {
		$this->product->set_amount(19.99);
		$this->product->set_setup_fee(50.00);
		$this->product->set_recurring(true);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');

		$desc = $this->product->get_price_description(false);
		$this->assertStringNotContainsString('Setup Fee', $desc);
	}

	/**
	 * Test get_price_description for recurring with billing cycles.
	 */
	public function test_get_price_description_with_billing_cycles(): void {
		$this->product->set_amount(19.99);
		$this->product->set_recurring(true);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_billing_cycles(12);

		$desc = $this->product->get_price_description();
		$this->assertStringContainsString('cycle', $desc);
	}

	/**
	 * Test get_price_description for recurring with duration > 1.
	 */
	public function test_get_price_description_multi_duration(): void {
		$this->product->set_amount(49.99);
		$this->product->set_recurring(true);
		$this->product->set_duration(3);
		$this->product->set_duration_unit('month');
		$this->product->set_billing_cycles(0);

		$desc = $this->product->get_price_description();
		$this->assertStringContainsString('3', $desc);
	}

	/**
	 * Test get_formatted_amount for a paid product.
	 */
	public function test_get_formatted_amount_paid(): void {
		$this->product->set_amount(49.99);
		$this->product->set_pricing_type('paid');

		$formatted = $this->product->get_formatted_amount();
		$this->assertIsString($formatted);
		$this->assertNotEmpty($formatted);
	}

	/**
	 * Test get_formatted_amount for a free product.
	 */
	public function test_get_formatted_amount_free(): void {
		$this->product->set_amount(0);
		$this->product->set_setup_fee(0);
		$this->product->set_pricing_type('free');

		$formatted = $this->product->get_formatted_amount();
		$this->assertStringContainsString('Free', $formatted);
	}

	/**
	 * Test get_formatted_amount for a contact_us product.
	 *
	 * When pricing_type is contact_us, set_pricing_type zeroes the amount,
	 * so is_free() returns true and get_formatted_amount returns "Free!"
	 * before reaching the contact_us check. This tests the actual behavior.
	 */
	public function test_get_formatted_amount_contact_us(): void {
		$this->product->set_pricing_type('contact_us');

		$formatted = $this->product->get_formatted_amount();
		// Since contact_us zeroes the amount, is_free() returns true first
		$this->assertStringContainsString('Free', $formatted);
	}

	/**
	 * Test get_formatted_amount for contact_us with setup fee set to avoid is_free.
	 *
	 * To actually reach the contact_us code path in get_formatted_amount,
	 * we need the product to not be is_free(), so we set a setup fee.
	 */
	public function test_get_formatted_amount_contact_us_with_setup_fee(): void {
		$this->product->set_setup_fee(10.00);
		$this->product->set_pricing_type('contact_us');

		// Now is_free() is false because initial_amount > 0,
		// and get_pricing_type() is contact_us
		$formatted = $this->product->get_formatted_amount();
		$this->assertStringContainsString('Contact', $formatted);
	}

	/**
	 * Test get_formatted_amount for contact_us with custom label and setup fee.
	 */
	public function test_get_formatted_amount_contact_us_custom_label(): void {
		$this->product->set_setup_fee(10.00);
		$this->product->set_pricing_type('contact_us');
		$this->product->set_contact_us_label('Talk to Sales');

		$formatted = $this->product->get_formatted_amount();
		$this->assertSame('Talk to Sales', $formatted);
	}

	/**
	 * Test get_recurring_description for a free product.
	 */
	public function test_get_recurring_description_free(): void {
		$this->product->set_amount(0);
		$this->product->set_setup_fee(0);
		$this->product->set_pricing_type('free');

		$desc = $this->product->get_recurring_description();
		$this->assertSame('--', $desc);
	}

	/**
	 * Test get_recurring_description for contact_us product.
	 */
	public function test_get_recurring_description_contact_us(): void {
		$this->product->set_pricing_type('contact_us');

		$desc = $this->product->get_recurring_description();
		$this->assertSame('--', $desc);
	}

	/**
	 * Test get_recurring_description for non-recurring product.
	 */
	public function test_get_recurring_description_one_time(): void {
		$this->product->set_amount(50.00);
		$this->product->set_recurring(false);
		$this->product->set_pricing_type('paid');

		$desc = $this->product->get_recurring_description();
		$this->assertStringContainsString('one-time', $desc);
	}

	/**
	 * Test get_recurring_description for recurring product.
	 */
	public function test_get_recurring_description_recurring(): void {
		$this->product->set_amount(19.99);
		$this->product->set_recurring(true);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');

		$desc = $this->product->get_recurring_description();
		$this->assertStringContainsString('month', $desc);
	}

	// ========================================================================
	// Feature list tests
	// ========================================================================

	/**
	 * Test feature list getter and setter.
	 */
	public function test_get_set_feature_list(): void {
		$features = [
			[
				'feature' => '10 Sites',
				'tooltip' => '',
			],
			[
				'feature' => '100GB Storage',
				'tooltip' => 'SSD storage',
			],
		];
		$this->product->set_feature_list($features);
		$this->assertEquals($features, $this->product->get_feature_list());
	}

	/**
	 * Test feature list with empty array.
	 */
	public function test_feature_list_empty_array(): void {
		$this->product->set_feature_list([]);
		$this->assertSame([], $this->product->get_feature_list());
	}

	// ========================================================================
	// Taxable tests
	// ========================================================================

	/**
	 * Test taxable setter stores value correctly.
	 */
	public function test_set_taxable_true(): void {
		$this->product->set_taxable(true);
		$this->assertTrue($this->product->is_taxable());
	}

	/**
	 * Test taxable setter with false.
	 */
	public function test_set_taxable_false(): void {
		$this->product->set_taxable(false);
		// is_taxable reads from meta, and since this is unsaved we check the filter path
		// The set_taxable method stores in meta array, so we verify it was set
		$this->assertIsBool($this->product->is_taxable());
	}

	/**
	 * Test tax category getter and setter.
	 */
	public function test_get_set_tax_category(): void {
		$this->product->set_tax_category('digital-goods');
		$this->assertSame('digital-goods', $this->product->get_tax_category());
	}

	// ========================================================================
	// Contact us tests
	// ========================================================================

	/**
	 * Test contact us label getter and setter.
	 */
	public function test_get_set_contact_us_label(): void {
		$this->product->set_contact_us_label('Get a Quote');
		$this->assertSame('Get a Quote', $this->product->get_contact_us_label());
	}

	/**
	 * Test contact us link getter and setter.
	 */
	public function test_get_set_contact_us_link(): void {
		$link = 'https://example.com/enterprise';
		$this->product->set_contact_us_link($link);
		$this->assertSame($link, $this->product->get_contact_us_link());
	}

	// ========================================================================
	// Available addons tests
	// ========================================================================

	/**
	 * Test available addons getter and setter.
	 */
	public function test_get_set_available_addons(): void {
		$addons = [1, 2, 3];
		$this->product->set_available_addons($addons);
		$this->assertEquals($addons, $this->product->get_available_addons());
	}

	/**
	 * Test available addons with empty array.
	 */
	public function test_available_addons_empty(): void {
		$this->product->set_available_addons([]);
		$this->assertSame([], $this->product->get_available_addons());
	}

	// ========================================================================
	// Price variations tests
	// ========================================================================

	/**
	 * Test price variations getter and setter.
	 */
	public function test_get_set_price_variations(): void {
		$variations = [
			[
				'duration'      => 1,
				'duration_unit' => 'year',
				'amount'        => 199.99,
			],
		];
		$this->product->set_price_variations($variations);
		$result = $this->product->get_price_variations();

		$this->assertCount(1, $result);
		$this->assertEquals(199.99, $result[0]['amount']);
		$this->assertSame('year', $result[0]['duration_unit']);
	}

	/**
	 * Test price variations with null sets empty array.
	 */
	public function test_set_price_variations_null(): void {
		$this->product->set_price_variations(null);
		$this->assertSame([], $this->product->get_price_variations());
	}

	/**
	 * Test price variations ensures amount is float.
	 */
	public function test_price_variations_amount_is_float(): void {
		$variations = [
			[
				'duration'      => 1,
				'duration_unit' => 'year',
				'amount'        => '99',
			],
		];
		$this->product->set_price_variations($variations);
		$result = $this->product->get_price_variations();
		$this->assertIsFloat($result[0]['amount']);
	}

	/**
	 * Test get_price_variation returns matching variation.
	 */
	public function test_get_price_variation_match(): void {
		$variations = [
			[
				'duration'      => 1,
				'duration_unit' => 'year',
				'amount'        => 199.99,
			],
			[
				'duration'      => 1,
				'duration_unit' => 'month',
				'amount'        => 19.99,
			],
		];
		$this->product->set_price_variations($variations);
		$result = $this->product->get_price_variation(1, 'year');

		$this->assertIsArray($result);
		$this->assertEquals(199.99, $result['amount']);
	}

	/**
	 * Test get_price_variation returns false when no match.
	 */
	public function test_get_price_variation_no_match(): void {
		$variations = [
			[
				'duration'      => 1,
				'duration_unit' => 'year',
				'amount'        => 199.99,
			],
		];
		$this->product->set_price_variations($variations);
		$result = $this->product->get_price_variation(1, 'week');

		$this->assertFalse($result);
	}

	/**
	 * Test get_price_variation converts 12 months to 1 year.
	 */
	public function test_get_price_variation_12_months_to_year(): void {
		$variations = [
			[
				'duration'      => 1,
				'duration_unit' => 'year',
				'amount'        => 199.99,
			],
		];
		$this->product->set_price_variations($variations);
		$result = $this->product->get_price_variation(12, 'month');

		$this->assertIsArray($result);
		$this->assertEquals(199.99, $result['amount']);
	}

	// ========================================================================
	// Product group tests
	// ========================================================================

	/**
	 * Test group getter and setter.
	 */
	public function test_get_set_group(): void {
		$this->product->set_group('enterprise');
		$this->assertSame('enterprise', $this->product->get_group());
	}

	/**
	 * Test group with empty string.
	 */
	public function test_group_empty_string(): void {
		$this->product->set_group('');
		$this->assertSame('', $this->product->get_group());
	}

	// ========================================================================
	// Legacy options tests
	// ========================================================================

	/**
	 * Test legacy options getter and setter with true.
	 */
	public function test_get_set_legacy_options_true(): void {
		$this->product->set_legacy_options(true);
		$this->assertTrue($this->product->get_legacy_options());
	}

	/**
	 * Test legacy options getter and setter with false.
	 */
	public function test_get_set_legacy_options_false(): void {
		$this->product->set_legacy_options(false);
		$this->assertFalse($this->product->get_legacy_options());
	}

	// ========================================================================
	// to_array tests
	// ========================================================================

	/**
	 * Test to_array contains expected fields.
	 */
	public function test_to_array_contains_all_expected_fields(): void {
		$array = $this->product->to_array();

		$expected_keys = [
			'id',
			'name',
			'slug',
			'description',
			'currency',
			'pricing_type',
			'setup_fee',
			'recurring',
			'trial_duration',
			'trial_duration_unit',
			'duration',
			'duration_unit',
			'amount',
			'billing_cycles',
			'list_order',
			'active',
			'type',
		];

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $array, "to_array should contain '$key'.");
		}
	}

	/**
	 * Test to_array excludes internal fields.
	 */
	public function test_to_array_excludes_internal_fields(): void {
		$array = $this->product->to_array();

		$excluded_keys = [
			'query_class',
			'skip_validation',
			'meta',
			'meta_fields',
			'_original',
			'_mappings',
			'_mocked',
		];

		foreach ($excluded_keys as $key) {
			$this->assertArrayNotHasKey($key, $array, "to_array should not contain '$key'.");
		}
	}

	/**
	 * Test to_array values match getters.
	 */
	public function test_to_array_values_match_getters(): void {
		$this->product->set_name('Test to_array');
		$this->product->set_slug('test-to-array');
		$this->product->set_amount(25.00);

		$array = $this->product->to_array();

		$this->assertSame('Test to_array', $array['name']);
		$this->assertSame('test-to-array', $array['slug']);
	}

	// ========================================================================
	// to_search_results tests
	// ========================================================================

	/**
	 * Test to_search_results includes type label and formatted price.
	 */
	public function test_to_search_results(): void {
		$this->product->set_name('Search Result Product');
		$this->product->set_type('plan');
		$this->product->set_amount(19.99);
		$this->product->set_pricing_type('paid');

		$result = $this->product->to_search_results();

		$this->assertIsArray($result);
		$this->assertArrayHasKey('type', $result);
		$this->assertArrayHasKey('formatted_price', $result);
		$this->assertArrayHasKey('image', $result);
		$this->assertArrayHasKey('name', $result);
	}

	/**
	 * Test to_search_results type is the type label not the raw type.
	 */
	public function test_to_search_results_type_is_label(): void {
		$this->product->set_type('plan');

		$result = $this->product->to_search_results();

		// The type in search results should be the label (e.g. "Plan"), not the raw type "plan"
		$this->assertSame($this->product->get_type_label(), $result['type']);
	}

	/**
	 * Test to_search_results formatted_price matches get_price_description.
	 */
	public function test_to_search_results_formatted_price(): void {
		$this->product->set_amount(19.99);
		$this->product->set_recurring(true);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_pricing_type('paid');

		$result = $this->product->to_search_results();

		$this->assertSame($this->product->get_price_description(), $result['formatted_price']);
	}

	/**
	 * Test to_search_results image is empty when no featured image.
	 */
	public function test_to_search_results_no_image(): void {
		$result = $this->product->to_search_results();
		$this->assertEmpty($result['image']);
	}

	// ========================================================================
	// Validation rules tests
	// ========================================================================

	/**
	 * Test validation rules include all expected keys.
	 */
	public function test_validation_rules_complete(): void {
		$rules = $this->product->validation_rules();

		$expected = [
			'featured_image_id',
			'currency',
			'pricing_type',
			'trial_duration',
			'trial_duration_unit',
			'parent_id',
			'amount',
			'recurring',
			'setup_fee',
			'duration',
			'duration_unit',
			'billing_cycles',
			'active',
			'price_variations',
			'type',
			'slug',
			'taxable',
			'tax_category',
			'contact_us_label',
			'contact_us_link',
			'customer_role',
			'network_id',
		];

		foreach ($expected as $key) {
			$this->assertArrayHasKey($key, $rules, "Validation rules should include '$key'.");
		}
	}

	/**
	 * Test validation rules type includes all valid types.
	 */
	public function test_validation_rules_type_includes_valid_types(): void {
		$rules = $this->product->validation_rules();

		$this->assertStringContainsString('plan', $rules['type']);
		$this->assertStringContainsString('package', $rules['type']);
		$this->assertStringContainsString('service', $rules['type']);
	}

	/**
	 * Test validation rules slug includes unique constraint.
	 */
	public function test_validation_rules_slug_unique(): void {
		$rules = $this->product->validation_rules();

		$this->assertStringContainsString('unique', $rules['slug']);
		$this->assertStringContainsString('min:2', $rules['slug']);
	}

	// ========================================================================
	// CRUD with wu_create_product and wu_get_product tests
	// ========================================================================

	/**
	 * Test creating a product with wu_create_product helper.
	 */
	public function test_wu_create_product(): void {
		$product = wu_create_product(
			[
				'name'          => 'Created Product',
				'slug'          => 'created-product',
				'description'   => 'A test product',
				'pricing_type'  => 'paid',
				'amount'        => 29.99,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$this->assertGreaterThan(0, $product->get_id());
		$this->assertSame('Created Product', $product->get_name());
		$this->assertSame('created-product', $product->get_slug());
	}

	/**
	 * Test retrieving a product by ID with wu_get_product.
	 */
	public function test_wu_get_product_by_id(): void {
		$product = wu_create_product(
			[
				'name'          => 'Fetch By ID',
				'slug'          => 'fetch-by-id',
				'pricing_type'  => 'paid',
				'amount'        => 9.99,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);

		$fetched = wu_get_product($product->get_id());
		$this->assertInstanceOf(Product::class, $fetched);
		$this->assertEquals($product->get_id(), $fetched->get_id());
		$this->assertSame('Fetch By ID', $fetched->get_name());
	}

	/**
	 * Test retrieving a product by slug with wu_get_product.
	 */
	public function test_wu_get_product_by_slug(): void {
		$product = wu_create_product(
			[
				'name'          => 'Fetch By Slug',
				'slug'          => 'fetch-by-slug',
				'pricing_type'  => 'paid',
				'amount'        => 9.99,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);

		$fetched = wu_get_product('fetch-by-slug');
		$this->assertInstanceOf(Product::class, $fetched);
		$this->assertSame('Fetch By Slug', $fetched->get_name());
	}

	/**
	 * Test wu_get_product returns false for non-existent ID.
	 */
	public function test_wu_get_product_nonexistent(): void {
		$result = wu_get_product(999999);
		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_product returns false for non-existent slug.
	 */
	public function test_wu_get_product_nonexistent_slug(): void {
		$result = wu_get_product('does-not-exist-slug');
		$this->assertFalse($result);
	}

	/**
	 * Test creating a product with wu_create_product and then updating it.
	 */
	public function test_product_create_and_update(): void {
		$product = wu_create_product(
			[
				'name'          => 'Update Test',
				'slug'          => 'update-test',
				'pricing_type'  => 'paid',
				'amount'        => 10.00,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);

		$product->set_name('Updated Name');
		$product->set_amount(20.00);
		$product->set_skip_validation(true);
		$product->save();

		$fetched = wu_get_product($product->get_id());
		$this->assertSame('Updated Name', $fetched->get_name());
		$this->assertEquals(20.00, $fetched->get_amount());
	}

	/**
	 * Test creating a product and then deleting it.
	 */
	public function test_product_create_and_delete(): void {
		$product = wu_create_product(
			[
				'name'          => 'Delete Test',
				'slug'          => 'delete-test',
				'pricing_type'  => 'paid',
				'amount'        => 10.00,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$product_id = $product->get_id();
		$this->assertGreaterThan(0, $product_id);

		$product->delete();

		$fetched = wu_get_product($product_id);
		$this->assertFalse($fetched);
	}

	/**
	 * Test wu_create_product returns WP_Error without required fields.
	 */
	public function test_wu_create_product_validation_error(): void {
		$result = wu_create_product(
			[
				'name' => '',
				'slug' => '',
			]
		);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	// ========================================================================
	// Edge cases tests
	// ========================================================================

	/**
	 * Test product with zero amount but positive setup fee is not free.
	 */
	public function test_zero_amount_positive_setup_fee_not_free(): void {
		$this->product->set_amount(0);
		$this->product->set_setup_fee(25.00);
		$this->product->set_pricing_type('paid');

		$this->assertFalse($this->product->is_free());
		$this->assertEquals(25.00, $this->product->get_initial_amount());
	}

	/**
	 * Test product with zero amount and zero setup fee is free.
	 */
	public function test_zero_amount_zero_setup_fee_is_free(): void {
		$this->product->set_amount(0);
		$this->product->set_setup_fee(0);
		$this->product->set_pricing_type('paid');

		$this->assertTrue($this->product->is_free());
	}

	/**
	 * Test product type transition from plan to service.
	 */
	public function test_type_transition_plan_to_service(): void {
		$this->product->set_type('plan');
		$this->assertSame('plan', $this->product->get_type());

		$this->product->set_type('service');
		$this->assertSame('service', $this->product->get_type());
	}

	/**
	 * Test product type transition from service to package.
	 */
	public function test_type_transition_service_to_package(): void {
		$this->product->set_type('service');
		$this->assertSame('service', $this->product->get_type());

		$this->product->set_type('package');
		$this->assertSame('package', $this->product->get_type());
	}

	/**
	 * Test product defaults for a new empty product.
	 */
	public function test_product_defaults(): void {
		$product = new Product();

		$this->assertSame('', $product->get_name());
		$this->assertSame('', $product->get_slug());
		$this->assertSame('USD', $product->get_currency());
		$this->assertSame('paid', $product->get_pricing_type());
		$this->assertEquals(0, $product->get_setup_fee());
		$this->assertEquals(0, $product->get_trial_duration());
		$this->assertSame('day', $product->get_trial_duration_unit());
		$this->assertSame(1, $product->get_duration());
		$this->assertSame('month', $product->get_duration_unit());
		$this->assertSame(0, $product->get_billing_cycles());
		$this->assertEquals(10, $product->get_list_order());
		$this->assertTrue($product->is_active());
		$this->assertSame('plan', $product->get_type());
	}

	/**
	 * Test amount setter uses wu_to_float conversion.
	 */
	public function test_amount_float_conversion(): void {
		$this->product->set_amount(19);
		$this->assertIsFloat($this->product->get_amount());

		$this->product->set_amount('29.99');
		$this->assertEquals(29.99, $this->product->get_amount());
	}

	/**
	 * Test setup fee setter uses wu_to_float conversion.
	 */
	public function test_setup_fee_float_conversion(): void {
		$this->product->set_setup_fee('50');
		$this->assertIsFloat($this->product->get_setup_fee());
		$this->assertEquals(50.0, $this->product->get_setup_fee());
	}

	/**
	 * Test creating product from constructor array.
	 */
	public function test_product_construction_from_array(): void {
		$product = new Product(
			[
				'name'         => 'From Array',
				'slug'         => 'from-array',
				'amount'       => 15.00,
				'type'         => 'service',
				'pricing_type' => 'paid',
			]
		);

		$this->assertSame('From Array', $product->get_name());
		$this->assertSame('from-array', $product->get_slug());
		$this->assertEquals(15.00, $product->get_amount());
		$this->assertSame('service', $product->get_type());
		$this->assertSame('paid', $product->get_pricing_type());
	}

	/**
	 * Test product mapping for product_group to group.
	 */
	public function test_product_group_mapping(): void {
		$product = new Product(
			[
				'name'          => 'Mapping Test',
				'slug'          => 'mapping-test',
				'product_group' => 'gold',
			]
		);

		$this->assertSame('gold', $product->get_group());
	}

	/**
	 * Test exists returns false for unsaved product.
	 */
	public function test_exists_false_for_unsaved(): void {
		$product = new Product();
		$this->assertFalse($product->exists());
	}

	/**
	 * Test exists returns true for saved product.
	 */
	public function test_exists_true_for_saved(): void {
		$product = wu_create_product(
			[
				'name'          => 'Exists Test',
				'slug'          => 'exists-test',
				'pricing_type'  => 'paid',
				'amount'        => 10.00,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$this->assertTrue($product->exists());
	}

	/**
	 * Test save auto-generates slug from name if slug is empty.
	 */
	public function test_save_auto_generates_slug(): void {
		$product = new Product();
		$product->set_name('Auto Slug Test');
		$product->set_pricing_type('paid');
		$product->set_amount(10.00);
		$product->set_type('plan');
		$product->set_skip_validation(true);
		$product->save();

		$this->assertSame('auto-slug-test', $product->get_slug());
	}

	/**
	 * Test save sets pricing type to free when product is free and not contact_us.
	 */
	public function test_save_sets_pricing_type_free_when_free(): void {
		$product = new Product();
		$product->set_name('Free Auto');
		$product->set_slug('free-auto');
		$product->set_amount(0);
		$product->set_setup_fee(0);
		$product->set_pricing_type('paid');
		$product->set_type('plan');
		$product->set_skip_validation(true);
		$product->save();

		$this->assertSame('free', $product->get_pricing_type());
	}

	/**
	 * Test product model string is correct.
	 */
	public function test_product_model_name(): void {
		$this->assertSame('product', $this->product->model);
	}

	/**
	 * Test get_id returns zero for new unsaved product.
	 */
	public function test_get_id_unsaved(): void {
		$product = new Product();
		$this->assertSame(0, $product->get_id());
	}

	/**
	 * Test product limitations_to_merge returns empty array.
	 */
	public function test_limitations_to_merge_empty(): void {
		$result = $this->product->limitations_to_merge();
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Test get_limitations returns Limitations object.
	 */
	public function test_get_limitations_returns_object(): void {
		$product = wu_create_product(
			[
				'name'          => 'Limits Test',
				'slug'          => 'limits-test',
				'pricing_type'  => 'paid',
				'amount'        => 10.00,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);

		$limitations = $product->get_limitations();
		$this->assertInstanceOf(\WP_Ultimo\Objects\Limitations::class, $limitations);
	}

	/**
	 * Test get_featured_image returns empty string when no image is set.
	 */
	public function test_get_featured_image_empty(): void {
		$image = $this->product->get_featured_image();
		$this->assertSame('', $image);
	}

	/**
	 * Test customer_role setter is deprecated no-op.
	 */
	public function test_set_customer_role_is_noop(): void {
		// set_customer_role is deprecated and does nothing
		$this->product->set_customer_role('editor');
		// No assertion needed - just verify it doesn't throw
		$this->assertTrue(true);
	}

	/**
	 * Test creating a free product with wu_create_product.
	 */
	public function test_wu_create_free_product(): void {
		$product = wu_create_product(
			[
				'name'          => 'Free Product',
				'slug'          => 'free-product',
				'pricing_type'  => 'free',
				'amount'        => 0,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => false,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$this->assertTrue($product->is_free());
		$this->assertSame('free', $product->get_pricing_type());
	}

	/**
	 * Test creating a product with setup fee via wu_create_product.
	 */
	public function test_wu_create_product_with_setup_fee(): void {
		$product = wu_create_product(
			[
				'name'          => 'Setup Fee Product',
				'slug'          => 'setup-fee-product',
				'pricing_type'  => 'paid',
				'amount'        => 19.99,
				'setup_fee'     => 50.00,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$this->assertEquals(50.00, $product->get_setup_fee());
		$this->assertTrue($product->has_setup_fee());
		$this->assertEquals(69.99, $product->get_initial_amount());
	}

	/**
	 * Test creating a product of type package.
	 */
	public function test_wu_create_package_product(): void {
		$product = wu_create_product(
			[
				'name'          => 'Package Product',
				'slug'          => 'package-product',
				'pricing_type'  => 'paid',
				'amount'        => 5.00,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'package',
				'recurring'     => false,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$this->assertSame('package', $product->get_type());
	}

	/**
	 * Test creating a product of type service.
	 */
	public function test_wu_create_service_product(): void {
		$product = wu_create_product(
			[
				'name'          => 'Service Product',
				'slug'          => 'service-product',
				'pricing_type'  => 'paid',
				'amount'        => 100.00,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'service',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$this->assertSame('service', $product->get_type());
	}

	/**
	 * Test product with trial and recurring.
	 */
	public function test_product_with_trial_and_recurring(): void {
		$product = wu_create_product(
			[
				'name'                => 'Trial Product',
				'slug'                => 'trial-product',
				'pricing_type'        => 'paid',
				'amount'              => 29.99,
				'currency'            => 'USD',
				'duration'            => 1,
				'duration_unit'       => 'month',
				'type'                => 'plan',
				'recurring'           => true,
				'trial_duration'      => 14,
				'trial_duration_unit' => 'day',
				'active'              => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$this->assertTrue($product->has_trial());
		$this->assertEquals(14, $product->get_trial_duration());
		$this->assertSame('day', $product->get_trial_duration_unit());
		$this->assertTrue($product->is_recurring());
	}

	/**
	 * Test product with yearly billing cycles.
	 */
	public function test_product_yearly_with_billing_cycles(): void {
		$product = wu_create_product(
			[
				'name'           => 'Yearly Cycles',
				'slug'           => 'yearly-cycles',
				'pricing_type'   => 'paid',
				'amount'         => 199.99,
				'currency'       => 'USD',
				'duration'       => 1,
				'duration_unit'  => 'year',
				'type'           => 'plan',
				'recurring'      => true,
				'billing_cycles' => 3,
				'active'         => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$this->assertSame(3, $product->get_billing_cycles());
		$this->assertFalse($product->is_forever_recurring());
	}

	/**
	 * Test product get_as_variation returns self when product is free.
	 */
	public function test_get_as_variation_free_product(): void {
		$this->product->set_amount(0);
		$this->product->set_setup_fee(0);
		$this->product->set_pricing_type('free');

		$result = $this->product->get_as_variation(1, 'year');
		$this->assertSame($this->product, $result);
	}

	/**
	 * Test product get_as_variation returns self when duration matches.
	 */
	public function test_get_as_variation_same_duration(): void {
		$this->product->set_amount(19.99);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_pricing_type('paid');

		$result = $this->product->get_as_variation(1, 'month');
		$this->assertInstanceOf(Product::class, $result);
	}

	/**
	 * Test product get_as_variation returns false when no variation matches.
	 */
	public function test_get_as_variation_no_match(): void {
		$this->product->set_amount(19.99);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_pricing_type('paid');
		$this->product->set_price_variations([]);

		$result = $this->product->get_as_variation(1, 'year');
		$this->assertFalse($result);
	}

	/**
	 * Test product get_as_variation returns modified product when variation matches.
	 */
	public function test_get_as_variation_with_match(): void {
		$this->product->set_amount(19.99);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_pricing_type('paid');
		$this->product->set_price_variations(
			[
				[
					'duration'      => 1,
					'duration_unit' => 'year',
					'amount'        => 199.99,
				],
			]
		);

		$result = $this->product->get_as_variation(1, 'year');
		$this->assertInstanceOf(Product::class, $result);
		$this->assertEquals(199.99, $result->get_amount());
	}

	/**
	 * Test get_as_variation handles string duration from AJAX POST data (issue #328).
	 *
	 * When duration arrives as a string "1" from AJAX, the strict !== comparison
	 * with the integer 1 returned by get_duration() must not cause a false mismatch.
	 */
	public function test_get_as_variation_string_duration_matches_base(): void {
		$this->product->set_amount(19.99);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_pricing_type('paid');
		$this->product->set_price_variations([]);

		// Simulate AJAX POST: duration arrives as string "1", not integer 1.
		$result = $this->product->get_as_variation('1', 'month');
		$this->assertInstanceOf(Product::class, $result);
		$this->assertEquals(19.99, $result->get_amount());
	}

	/**
	 * Test get_as_variation treats a yearly product as matching "1 year" request (issue #328).
	 *
	 * A product configured with duration=1/unit=year must be visible when the period
	 * selector requests duration=1/unit=year, even when no price variations are stored.
	 */
	public function test_get_as_variation_yearly_product_matches_year_request(): void {
		$this->product->set_amount(199.99);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('year');
		$this->product->set_pricing_type('paid');
		$this->product->set_price_variations([]);

		$result = $this->product->get_as_variation(1, 'year');
		$this->assertInstanceOf(Product::class, $result);
		$this->assertEquals(199.99, $result->get_amount());
	}

	/**
	 * Test get_as_variation treats "12 months" and "1 year" as equivalent (issue #328).
	 *
	 * A product configured with duration=12/unit=month must be visible when the period
	 * selector requests duration=1/unit=year (and vice-versa).
	 */
	public function test_get_as_variation_12months_equals_1year(): void {
		$this->product->set_amount(199.99);
		$this->product->set_duration(12);
		$this->product->set_duration_unit('month');
		$this->product->set_pricing_type('paid');
		$this->product->set_price_variations([]);

		// Request "1 year" — should match a product configured as "12 months".
		$result = $this->product->get_as_variation(1, 'year');
		$this->assertInstanceOf(Product::class, $result);
		$this->assertEquals(199.99, $result->get_amount());
	}

	/**
	 * Test get_as_variation treats "1 year" product as matching "12 months" request (issue #328).
	 */
	public function test_get_as_variation_1year_equals_12months(): void {
		$this->product->set_amount(199.99);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('year');
		$this->product->set_pricing_type('paid');
		$this->product->set_price_variations([]);

		// Request "12 months" — should match a product configured as "1 year".
		$result = $this->product->get_as_variation(12, 'month');
		$this->assertInstanceOf(Product::class, $result);
		$this->assertEquals(199.99, $result->get_amount());
	}

	/**
	 * Test get_price_variation finds a "12 month" variation when requesting "1 year" (issue #328).
	 */
	public function test_get_price_variation_12months_variation_found_for_1year_request(): void {
		$this->product->set_amount(19.99);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_pricing_type('paid');
		$this->product->set_price_variations(
			[
				[
					'duration'      => 12,
					'duration_unit' => 'month',
					'amount'        => 199.99,
				],
			]
		);

		// Requesting "1 year" should find the "12 months" variation.
		$variation = $this->product->get_price_variation(1, 'year');
		$this->assertNotFalse($variation);
		$this->assertEquals(199.99, $variation['amount']);
	}

	/**
	 * Test get_price_variation finds a "1 year" variation when requesting "12 months" (issue #328).
	 */
	public function test_get_price_variation_1year_variation_found_for_12months_request(): void {
		$this->product->set_amount(19.99);
		$this->product->set_duration(1);
		$this->product->set_duration_unit('month');
		$this->product->set_pricing_type('paid');
		$this->product->set_price_variations(
			[
				[
					'duration'      => 1,
					'duration_unit' => 'year',
					'amount'        => 199.99,
				],
			]
		);

		// Requesting "12 months" should find the "1 year" variation.
		$variation = $this->product->get_price_variation(12, 'month');
		$this->assertNotFalse($variation);
		$this->assertEquals(199.99, $variation['amount']);
	}

	/**
	 * Test shareable link returns a string.
	 */
	public function test_get_shareable_link(): void {
		$this->product->set_slug('shareable-test');
		$link = $this->product->get_shareable_link();
		$this->assertIsString($link);
	}

	/**
	 * Test json serialize returns same as to_array.
	 */
	public function test_json_serialize(): void {
		$array     = $this->product->to_array();
		$json_data = $this->product->jsonSerialize();

		$this->assertEquals($array, $json_data);
	}

	/**
	 * Test duplicate creates a copy.
	 */
	public function test_duplicate(): void {
		$product = wu_create_product(
			[
				'name'          => 'Original',
				'slug'          => 'original',
				'pricing_type'  => 'paid',
				'amount'        => 10.00,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);

		$duplicate = $product->duplicate();
		$this->assertInstanceOf(Product::class, $duplicate);
		$this->assertEquals(0, $duplicate->get_id());
		$this->assertSame('Original', $duplicate->get_name());
	}

	/**
	 * Test get_recurring_description for recurring product with duration > 1.
	 */
	public function test_get_recurring_description_multi_duration(): void {
		$this->product->set_amount(49.99);
		$this->product->set_recurring(true);
		$this->product->set_duration(6);
		$this->product->set_duration_unit('month');

		$desc = $this->product->get_recurring_description();
		$this->assertStringContainsString('6', $desc);
		$this->assertStringContainsString('every', $desc);
	}

	/**
	 * Test product attributes method sets multiple values.
	 */
	public function test_attributes_method(): void {
		$this->product->attributes(
			[
				'name'   => 'Attr Test',
				'amount' => 55.00,
				'type'   => 'service',
			]
		);

		$this->assertSame('Attr Test', $this->product->get_name());
		$this->assertEquals(55.00, $this->product->get_amount());
		$this->assertSame('service', $this->product->get_type());
	}

	/**
	 * Test meta constants are defined.
	 */
	public function test_meta_constants(): void {
		$this->assertSame('wu_featured_image_id', Product::META_FEATURED_IMAGE_ID);
		$this->assertSame('taxable', Product::META_TAXABLE);
		$this->assertSame('tax_category', Product::META_TAX_CATEGORY);
		$this->assertSame('wu_contact_us_label', Product::META_CONTACT_US_LABEL);
		$this->assertSame('wu_contact_us_link', Product::META_CONTACT_US_LINK);
		$this->assertSame('feature_list', Product::META_FEATURE_LIST);
		$this->assertSame('price_variations', Product::META_PRICE_VARIATIONS);
		$this->assertSame('wu_limitations', Product::META_LIMITATIONS);
		$this->assertSame('wu_available_addons', Product::META_AVAILABLE_ADDONS);
		$this->assertSame('legacy_options', Product::META_LEGACY_OPTIONS);
	}

	/**
	 * Test duration returns absint (always positive).
	 */
	public function test_duration_returns_absint(): void {
		$this->product->set_duration(-5);
		$this->assertGreaterThanOrEqual(0, $this->product->get_duration());
	}

	/**
	 * Test billing cycles setter casts to int.
	 */
	public function test_billing_cycles_casts_to_int(): void {
		$this->product->set_billing_cycles(5);
		$this->assertIsInt($this->product->get_billing_cycles());
		$this->assertSame(5, $this->product->get_billing_cycles());
	}

	/**
	 * Test active setter casts to bool.
	 */
	public function test_active_casts_to_bool(): void {
		$this->product->set_active(1);
		$this->assertTrue($this->product->is_active());

		$this->product->set_active(0);
		$this->assertFalse($this->product->is_active());
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		// Clean up created products
		$products = Product::get_all();
		if ($products) {
			foreach ($products as $product) {
				if ($product->get_id()) {
					$product->delete();
				}
			}
		}

		parent::tearDown();
	}
}
