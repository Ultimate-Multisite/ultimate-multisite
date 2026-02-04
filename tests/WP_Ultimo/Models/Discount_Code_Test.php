<?php

namespace WP_Ultimo\Models;

use WP_Error;
use WP_UnitTestCase;

/**
 * Test class for Discount Code model functionality.
 *
 * Tests discount code validation, expiration dates, usage limits,
 * product restrictions, and error handling.
 */
class Discount_Code_Test extends WP_UnitTestCase {

	/**
	 * Tests that a valid discount code returns true.
	 */
	public function test_is_valid_active_discount_code(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	/**
	 * Tests that an inactive discount code returns an error.
	 */
	public function test_is_valid_inactive_discount_code(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(false);

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals('This coupon code is not valid.', $result->get_error_message());
	}

	/**
	 * Tests that a discount code with max uses returns an error after being used maximum times.
	 */
	public function test_is_valid_max_uses_exceeded(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_max_uses(5);
		$discount_code->set_uses(5);

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals(
			'This discount code was already redeemed the maximum amount of times allowed.',
			$result->get_error_message()
		);
	}

	/**
	 * Tests that a discount code before the start date is invalid.
	 */
	public function test_is_valid_before_start_date(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_date_start(gmdate('Y-m-d H:i:s', strtotime('+1 day')));

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals('This coupon code is not valid.', $result->get_error_message());
	}

	/**
	 * Tests that a discount code after the expiration date is invalid.
	 */
	public function test_is_valid_after_expiration_date(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_date_expiration(gmdate('Y-m-d H:i:s', strtotime('-1 day')));

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals('This coupon code is not valid.', $result->get_error_message());
	}

	/**
	 * Tests that a discount code limited to specific products returns true for allowed products.
	 */
	public function test_is_valid_for_allowed_product(): void {
		$product_id    = 123;
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_limit_products(true);
		$discount_code->set_allowed_products([$product_id]);

		$result = $discount_code->is_valid($product_id);

		$this->assertTrue($result);
	}

	/**
	 * Tests that a discount code limited to specific products returns an error for disallowed products.
	 */
	public function test_is_valid_for_disallowed_product(): void {
		$allowed_product_id    = 123;
		$disallowed_product_id = 456;
		$discount_code         = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_limit_products(true);
		$discount_code->set_allowed_products([$allowed_product_id]);

		$result = $discount_code->is_valid($disallowed_product_id);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('discount_code', $result->get_error_code());
		$this->assertEquals('This coupon code is not valid.', $result->get_error_message());
	}

	/**
	 * Tests that a discount code with no product limits returns true.
	 */
	public function test_is_valid_no_product_limits(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_limit_products(false);

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	// ----------------------------------------------------------------
	// Getter/Setter Tests
	// ----------------------------------------------------------------

	/**
	 * Tests get_name and set_name.
	 */
	public function test_get_set_name(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_name('Summer Sale');

		$this->assertSame('Summer Sale', $discount_code->get_name());
	}

	/**
	 * Tests get_code and set_code.
	 */
	public function test_get_set_code(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_code('PROMO10');

		$this->assertSame('PROMO10', $discount_code->get_code());
	}

	/**
	 * Tests get_description and set_description.
	 */
	public function test_get_set_description(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_description('A great discount for summer.');

		$this->assertSame('A great discount for summer.', $discount_code->get_description());
	}

	/**
	 * Tests get_uses and set_uses return integers.
	 */
	public function test_get_set_uses(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_uses(10);

		$this->assertSame(10, $discount_code->get_uses());
	}

	/**
	 * Tests that set_uses casts to integer.
	 */
	public function test_set_uses_casts_to_int(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_uses('7');

		$this->assertSame(7, $discount_code->get_uses());
	}

	/**
	 * Tests default value of uses is 0.
	 */
	public function test_uses_defaults_to_zero(): void {
		$discount_code = new Discount_Code();

		$this->assertSame(0, $discount_code->get_uses());
	}

	/**
	 * Tests get_max_uses and set_max_uses.
	 */
	public function test_get_set_max_uses(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_max_uses(100);

		$this->assertSame(100, $discount_code->get_max_uses());
	}

	/**
	 * Tests that set_max_uses casts to integer.
	 */
	public function test_set_max_uses_casts_to_int(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_max_uses('50');

		$this->assertSame(50, $discount_code->get_max_uses());
	}

	/**
	 * Tests has_max_uses returns true when max_uses is greater than zero.
	 */
	public function test_has_max_uses_true(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_max_uses(5);

		$this->assertTrue($discount_code->has_max_uses());
	}

	/**
	 * Tests has_max_uses returns false when max_uses is zero.
	 */
	public function test_has_max_uses_false_when_zero(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_max_uses(0);

		$this->assertFalse($discount_code->has_max_uses());
	}

	/**
	 * Tests has_max_uses returns false by default (null cast to int is 0).
	 */
	public function test_has_max_uses_false_by_default(): void {
		$discount_code = new Discount_Code();

		$this->assertFalse($discount_code->has_max_uses());
	}

	/**
	 * Tests should_apply_to_renewals and set_apply_to_renewals.
	 */
	public function test_get_set_apply_to_renewals(): void {
		$discount_code = new Discount_Code();

		$discount_code->set_apply_to_renewals(true);
		$this->assertTrue($discount_code->should_apply_to_renewals());

		$discount_code->set_apply_to_renewals(false);
		$this->assertFalse($discount_code->should_apply_to_renewals());
	}

	/**
	 * Tests default value of apply_to_renewals is false.
	 */
	public function test_apply_to_renewals_defaults_to_false(): void {
		$discount_code = new Discount_Code();

		$this->assertFalse($discount_code->should_apply_to_renewals());
	}

	/**
	 * Tests get_type and set_type.
	 */
	public function test_get_set_type(): void {
		$discount_code = new Discount_Code();

		$discount_code->set_type('percentage');
		$this->assertSame('percentage', $discount_code->get_type());

		$discount_code->set_type('absolute');
		$this->assertSame('absolute', $discount_code->get_type());
	}

	/**
	 * Tests default type is percentage.
	 */
	public function test_type_defaults_to_percentage(): void {
		$discount_code = new Discount_Code();

		$this->assertSame('percentage', $discount_code->get_type());
	}

	/**
	 * Tests get_value and set_value.
	 */
	public function test_get_set_value(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_value(25);

		$this->assertEquals(25.0, $discount_code->get_value());
	}

	/**
	 * Tests get_value returns float.
	 */
	public function test_get_value_returns_float(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_value('15.5');

		$this->assertIsFloat($discount_code->get_value());
		$this->assertEquals(15.5, $discount_code->get_value());
	}

	/**
	 * Tests default value is 0.
	 */
	public function test_value_defaults_to_zero(): void {
		$discount_code = new Discount_Code();

		$this->assertEquals(0.0, $discount_code->get_value());
	}

	/**
	 * Tests get_setup_fee_type and set_setup_fee_type.
	 */
	public function test_get_set_setup_fee_type(): void {
		$discount_code = new Discount_Code();

		$discount_code->set_setup_fee_type('absolute');
		$this->assertSame('absolute', $discount_code->get_setup_fee_type());

		$discount_code->set_setup_fee_type('percentage');
		$this->assertSame('percentage', $discount_code->get_setup_fee_type());
	}

	/**
	 * Tests default setup_fee_type is percentage.
	 */
	public function test_setup_fee_type_defaults_to_percentage(): void {
		$discount_code = new Discount_Code();

		$this->assertSame('percentage', $discount_code->get_setup_fee_type());
	}

	/**
	 * Tests get_setup_fee_value and set_setup_fee_value.
	 */
	public function test_get_set_setup_fee_value(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_setup_fee_value(10);

		$this->assertEquals(10.0, $discount_code->get_setup_fee_value());
	}

	/**
	 * Tests get_setup_fee_value returns float.
	 */
	public function test_get_setup_fee_value_returns_float(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_setup_fee_value('20.99');

		$this->assertIsFloat($discount_code->get_setup_fee_value());
		$this->assertEquals(20.99, $discount_code->get_setup_fee_value());
	}

	/**
	 * Tests default setup_fee_value is 0.
	 */
	public function test_setup_fee_value_defaults_to_zero(): void {
		$discount_code = new Discount_Code();

		$this->assertEquals(0.0, $discount_code->get_setup_fee_value());
	}

	/**
	 * Tests is_active returns true when active is set.
	 */
	public function test_is_active_true(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);

		$this->assertTrue($discount_code->is_active());
	}

	/**
	 * Tests is_active returns false when inactive.
	 */
	public function test_is_active_false(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(false);

		$this->assertFalse($discount_code->is_active());
	}

	/**
	 * Tests is_active defaults to true (active = 1).
	 */
	public function test_is_active_defaults_to_true(): void {
		$discount_code = new Discount_Code();

		$this->assertTrue($discount_code->is_active());
	}

	/**
	 * Tests get_date_start and set_date_start with a valid date.
	 */
	public function test_get_set_date_start(): void {
		$discount_code = new Discount_Code();
		$date          = '2025-06-01 00:00:00';
		$discount_code->set_date_start($date);

		$this->assertSame($date, $discount_code->get_date_start());
	}

	/**
	 * Tests get_date_start returns empty string for invalid date.
	 */
	public function test_get_date_start_returns_empty_for_invalid_date(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_date_start('not-a-date');

		$this->assertSame('', $discount_code->get_date_start());
	}

	/**
	 * Tests get_date_start returns null when no date is set.
	 *
	 * When date_start is null, wu_validate_date(null) returns true,
	 * so the raw null property value is returned directly.
	 */
	public function test_get_date_start_returns_null_when_not_set(): void {
		$discount_code = new Discount_Code();

		$this->assertNull($discount_code->get_date_start());
	}

	/**
	 * Tests get_date_expiration and set_date_expiration with a valid date.
	 */
	public function test_get_set_date_expiration(): void {
		$discount_code = new Discount_Code();
		$date          = '2025-12-31 23:59:59';
		$discount_code->set_date_expiration($date);

		$this->assertSame($date, $discount_code->get_date_expiration());
	}

	/**
	 * Tests get_date_expiration returns empty string for invalid date.
	 */
	public function test_get_date_expiration_returns_empty_for_invalid_date(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_date_expiration('invalid');

		$this->assertSame('', $discount_code->get_date_expiration());
	}

	/**
	 * Tests get_date_expiration returns null when no date is set.
	 *
	 * When date_expiration is null, wu_validate_date(null) returns true,
	 * so the raw null property value is returned directly.
	 */
	public function test_get_date_expiration_returns_null_when_not_set(): void {
		$discount_code = new Discount_Code();

		$this->assertNull($discount_code->get_date_expiration());
	}

	/**
	 * Tests get_date_created and set_date_created.
	 */
	public function test_get_set_date_created(): void {
		$discount_code = new Discount_Code();
		$date          = '2025-01-15 10:30:00';
		$discount_code->set_date_created($date);

		$this->assertSame($date, $discount_code->get_date_created());
	}

	/**
	 * Tests get_date_created returns null when not set.
	 */
	public function test_get_date_created_returns_null_when_not_set(): void {
		$discount_code = new Discount_Code();

		$this->assertNull($discount_code->get_date_created());
	}

	// ----------------------------------------------------------------
	// add_use() Tests
	// ----------------------------------------------------------------

	/**
	 * Tests add_use increments uses by 1 by default.
	 */
	public function test_add_use_increments_by_one(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_uses(3);
		$discount_code->add_use();

		$this->assertSame(4, $discount_code->get_uses());
	}

	/**
	 * Tests add_use increments uses by a custom amount.
	 */
	public function test_add_use_increments_by_custom_amount(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_uses(2);
		$discount_code->add_use(5);

		$this->assertSame(7, $discount_code->get_uses());
	}

	/**
	 * Tests add_use from zero.
	 */
	public function test_add_use_from_zero(): void {
		$discount_code = new Discount_Code();
		$discount_code->add_use();

		$this->assertSame(1, $discount_code->get_uses());
	}

	/**
	 * Tests multiple consecutive add_use calls.
	 */
	public function test_add_use_multiple_calls(): void {
		$discount_code = new Discount_Code();
		$discount_code->add_use();
		$discount_code->add_use();
		$discount_code->add_use();

		$this->assertSame(3, $discount_code->get_uses());
	}

	// ----------------------------------------------------------------
	// is_one_time() Tests
	// ----------------------------------------------------------------

	/**
	 * Tests is_one_time returns true when apply_to_renewals is true.
	 */
	public function test_is_one_time_true(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_apply_to_renewals(true);

		$this->assertTrue($discount_code->is_one_time());
	}

	/**
	 * Tests is_one_time returns false when apply_to_renewals is false.
	 */
	public function test_is_one_time_false(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_apply_to_renewals(false);

		$this->assertFalse($discount_code->is_one_time());
	}

	// ----------------------------------------------------------------
	// allowed_products and limit_products Tests
	// ----------------------------------------------------------------

	/**
	 * Tests get_allowed_products and set_allowed_products.
	 */
	public function test_get_set_allowed_products(): void {
		$discount_code = new Discount_Code();
		$products      = [1, 2, 3];
		$discount_code->set_allowed_products($products);

		$this->assertSame($products, $discount_code->get_allowed_products());
	}

	/**
	 * Tests set_allowed_products with an empty array.
	 */
	public function test_set_allowed_products_empty_array(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_allowed_products([]);

		$this->assertSame([], $discount_code->get_allowed_products());
	}

	/**
	 * Tests get_limit_products and set_limit_products.
	 */
	public function test_get_set_limit_products(): void {
		$discount_code = new Discount_Code();

		$discount_code->set_limit_products(true);
		$this->assertTrue($discount_code->get_limit_products());

		$discount_code->set_limit_products(false);
		$this->assertFalse($discount_code->get_limit_products());
	}

	/**
	 * Tests set_limit_products casts to boolean.
	 */
	public function test_set_limit_products_casts_to_bool(): void {
		$discount_code = new Discount_Code();

		$discount_code->set_limit_products(1);
		$this->assertTrue($discount_code->get_limit_products());

		$discount_code->set_limit_products(0);
		$this->assertFalse($discount_code->get_limit_products());
	}

	// ----------------------------------------------------------------
	// is_valid() Additional Edge Cases
	// ----------------------------------------------------------------

	/**
	 * Tests is_valid returns true when uses are below max_uses.
	 */
	public function test_is_valid_uses_below_max(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_max_uses(10);
		$discount_code->set_uses(5);

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	/**
	 * Tests is_valid returns error when uses exceed max_uses.
	 */
	public function test_is_valid_uses_exceed_max(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_max_uses(5);
		$discount_code->set_uses(10);

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
	}

	/**
	 * Tests is_valid when max_uses is zero (unlimited uses).
	 */
	public function test_is_valid_unlimited_uses(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_max_uses(0);
		$discount_code->set_uses(999);

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	/**
	 * Tests is_valid with a valid start date in the past.
	 */
	public function test_is_valid_start_date_in_past(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_date_start(gmdate('Y-m-d H:i:s', strtotime('-1 day')));

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	/**
	 * Tests is_valid with a valid expiration date in the future.
	 */
	public function test_is_valid_expiration_date_in_future(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_date_expiration(gmdate('Y-m-d H:i:s', strtotime('+1 day')));

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	/**
	 * Tests is_valid with both start and expiration dates set to valid range.
	 */
	public function test_is_valid_within_date_range(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_date_start(gmdate('Y-m-d H:i:s', strtotime('-1 day')));
		$discount_code->set_date_expiration(gmdate('Y-m-d H:i:s', strtotime('+1 day')));

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	/**
	 * Tests is_valid with invalid start date (not parseable) is treated as empty.
	 */
	public function test_is_valid_with_invalid_start_date_string(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_date_start('not-a-date');

		$result = $discount_code->is_valid();

		// An invalid date returns empty string from get_date_start(), so it is skipped.
		$this->assertTrue($result);
	}

	/**
	 * Tests is_valid with invalid expiration date is treated as empty.
	 */
	public function test_is_valid_with_invalid_expiration_date_string(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_date_expiration('not-a-date');

		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	/**
	 * Tests is_valid with limit_products true but no product passed.
	 */
	public function test_is_valid_limit_products_true_no_product_passed(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_limit_products(true);
		$discount_code->set_allowed_products([1, 2, 3]);

		// No product argument passed, so the product check block is skipped.
		$result = $discount_code->is_valid();

		$this->assertTrue($result);
	}

	/**
	 * Tests is_valid with multiple allowed products checking each one.
	 */
	public function test_is_valid_multiple_allowed_products(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_limit_products(true);
		$discount_code->set_allowed_products([10, 20, 30]);

		$this->assertTrue($discount_code->is_valid(10));
		$this->assertTrue($discount_code->is_valid(20));
		$this->assertTrue($discount_code->is_valid(30));

		$result = $discount_code->is_valid(40);
		$this->assertInstanceOf(WP_Error::class, $result);
	}

	/**
	 * Tests is_valid passes all checks: active, within dates, under max uses, allowed product.
	 */
	public function test_is_valid_all_conditions_pass(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_max_uses(10);
		$discount_code->set_uses(3);
		$discount_code->set_date_start(gmdate('Y-m-d H:i:s', strtotime('-7 days')));
		$discount_code->set_date_expiration(gmdate('Y-m-d H:i:s', strtotime('+7 days')));
		$discount_code->set_limit_products(true);
		$discount_code->set_allowed_products([42]);

		$result = $discount_code->is_valid(42);

		$this->assertTrue($result);
	}

	/**
	 * Tests is_valid fails on first check (inactive) even if everything else is valid.
	 */
	public function test_is_valid_inactive_short_circuits(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(false);
		$discount_code->set_max_uses(10);
		$discount_code->set_uses(0);
		$discount_code->set_date_start(gmdate('Y-m-d H:i:s', strtotime('-7 days')));
		$discount_code->set_date_expiration(gmdate('Y-m-d H:i:s', strtotime('+7 days')));

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertEquals('This coupon code is not valid.', $result->get_error_message());
	}

	// ----------------------------------------------------------------
	// get_discount_description() Tests
	// ----------------------------------------------------------------

	/**
	 * Tests discount description for percentage type.
	 */
	public function test_get_discount_description_percentage(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_type('percentage');
		$discount_code->set_value(10);

		$description = $discount_code->get_discount_description();

		$this->assertStringContainsString('10%', $description);
		$this->assertStringContainsString('OFF on Subscriptions', $description);
	}

	/**
	 * Tests discount description for absolute type.
	 */
	public function test_get_discount_description_absolute(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_type('absolute');
		$discount_code->set_value(25);

		$description = $discount_code->get_discount_description();

		$this->assertStringContainsString('OFF on Subscriptions', $description);
		// Should not contain % since it is absolute.
		$this->assertStringNotContainsString('%', $description);
	}

	/**
	 * Tests discount description when value is zero returns empty string.
	 */
	public function test_get_discount_description_zero_value(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_type('percentage');
		$discount_code->set_value(0);
		$discount_code->set_setup_fee_value(0);

		$description = $discount_code->get_discount_description();

		$this->assertSame('', $description);
	}

	/**
	 * Tests discount description with setup fee percentage.
	 */
	public function test_get_discount_description_setup_fee_percentage(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_value(0);
		$discount_code->set_setup_fee_type('percentage');
		$discount_code->set_setup_fee_value(15);

		$description = $discount_code->get_discount_description();

		$this->assertStringContainsString('15%', $description);
		$this->assertStringContainsString('OFF on Setup Fees', $description);
	}

	/**
	 * Tests discount description with setup fee absolute.
	 */
	public function test_get_discount_description_setup_fee_absolute(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_value(0);
		$discount_code->set_setup_fee_type('absolute');
		$discount_code->set_setup_fee_value(5);

		$description = $discount_code->get_discount_description();

		$this->assertStringContainsString('OFF on Setup Fees', $description);
		$this->assertStringNotContainsString('%', $description);
	}

	/**
	 * Tests discount description with both subscription and setup fee discounts.
	 */
	public function test_get_discount_description_both_subscription_and_setup_fee(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_type('percentage');
		$discount_code->set_value(10);
		$discount_code->set_setup_fee_type('percentage');
		$discount_code->set_setup_fee_value(20);

		$description = $discount_code->get_discount_description();

		$this->assertStringContainsString('10%', $description);
		$this->assertStringContainsString('OFF on Subscriptions', $description);
		$this->assertStringContainsString('20%', $description);
		$this->assertStringContainsString('OFF on Setup Fees', $description);
		$this->assertStringContainsString('and', $description);
	}

	// ----------------------------------------------------------------
	// to_array() Tests
	// ----------------------------------------------------------------

	/**
	 * Tests to_array includes discount_description key.
	 */
	public function test_to_array_includes_discount_description(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_type('percentage');
		$discount_code->set_value(10);

		$array = $discount_code->to_array();

		$this->assertArrayHasKey('discount_description', $array);
		$this->assertStringContainsString('10%', $array['discount_description']);
	}

	/**
	 * Tests to_array includes standard fields.
	 */
	public function test_to_array_includes_standard_fields(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_name('Test Discount');
		$discount_code->set_code('TESTCODE');
		$discount_code->set_value(10);
		$discount_code->set_type('absolute');

		$array = $discount_code->to_array();

		$this->assertArrayHasKey('discount_description', $array);
	}

	// ----------------------------------------------------------------
	// validation_rules() Tests
	// ----------------------------------------------------------------

	/**
	 * Tests validation_rules returns expected keys.
	 */
	public function test_validation_rules_structure(): void {
		$discount_code = new Discount_Code();
		$rules         = $discount_code->validation_rules();

		$this->assertArrayHasKey('name', $rules);
		$this->assertArrayHasKey('code', $rules);
		$this->assertArrayHasKey('uses', $rules);
		$this->assertArrayHasKey('max_uses', $rules);
		$this->assertArrayHasKey('active', $rules);
		$this->assertArrayHasKey('apply_to_renewals', $rules);
		$this->assertArrayHasKey('type', $rules);
		$this->assertArrayHasKey('value', $rules);
		$this->assertArrayHasKey('setup_fee_type', $rules);
		$this->assertArrayHasKey('setup_fee_value', $rules);
		$this->assertArrayHasKey('allowed_products', $rules);
		$this->assertArrayHasKey('limit_products', $rules);
	}

	/**
	 * Tests validation_rules name is required with min length.
	 */
	public function test_validation_rules_name_required(): void {
		$discount_code = new Discount_Code();
		$rules         = $discount_code->validation_rules();

		$this->assertStringContainsString('required', $rules['name']);
		$this->assertStringContainsString('min:2', $rules['name']);
	}

	/**
	 * Tests validation_rules code has proper constraints.
	 */
	public function test_validation_rules_code_constraints(): void {
		$discount_code = new Discount_Code();
		$rules         = $discount_code->validation_rules();

		$this->assertStringContainsString('required', $rules['code']);
		$this->assertStringContainsString('min:2', $rules['code']);
		$this->assertStringContainsString('max:20', $rules['code']);
		$this->assertStringContainsString('alpha_dash', $rules['code']);
	}

	/**
	 * Tests validation_rules type must be percentage or absolute.
	 */
	public function test_validation_rules_type_options(): void {
		$discount_code = new Discount_Code();
		$rules         = $discount_code->validation_rules();

		$this->assertStringContainsString('in:percentage,absolute', $rules['type']);
	}

	/**
	 * Tests validation_rules value is required and numeric.
	 */
	public function test_validation_rules_value_required_numeric(): void {
		$discount_code = new Discount_Code();
		$rules         = $discount_code->validation_rules();

		$this->assertStringContainsString('required', $rules['value']);
		$this->assertStringContainsString('numeric', $rules['value']);
	}

	// ----------------------------------------------------------------
	// Meta Constants Tests
	// ----------------------------------------------------------------

	/**
	 * Tests the META_ALLOWED_PRODUCTS constant value.
	 */
	public function test_meta_allowed_products_constant(): void {
		$this->assertSame('wu_allowed_products', Discount_Code::META_ALLOWED_PRODUCTS);
	}

	/**
	 * Tests the META_LIMIT_PRODUCTS constant value.
	 */
	public function test_meta_limit_products_constant(): void {
		$this->assertSame('wu_limit_products', Discount_Code::META_LIMIT_PRODUCTS);
	}

	// ----------------------------------------------------------------
	// Combination / Integration-style Tests
	// ----------------------------------------------------------------

	/**
	 * Tests that using add_use makes code invalid once max_uses is reached.
	 */
	public function test_add_use_triggers_max_uses_invalidation(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_max_uses(3);
		$discount_code->set_uses(0);

		// Use it 3 times.
		$discount_code->add_use();
		$discount_code->add_use();
		$discount_code->add_use();

		$result = $discount_code->is_valid();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertStringContainsString('maximum amount of times', $result->get_error_message());
	}

	/**
	 * Tests that a code is still valid one use before reaching max.
	 */
	public function test_code_valid_one_before_max(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_active(true);
		$discount_code->set_max_uses(3);
		$discount_code->set_uses(0);

		$discount_code->add_use();
		$discount_code->add_use();

		$result = $discount_code->is_valid();
		$this->assertTrue($result);

		// One more use should make it invalid.
		$discount_code->add_use();

		$result = $discount_code->is_valid();
		$this->assertInstanceOf(WP_Error::class, $result);
	}

	/**
	 * Tests creating a fully configured discount code and verifying all properties.
	 */
	public function test_fully_configured_discount_code(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_name('Black Friday');
		$discount_code->set_code('BF2025');
		$discount_code->set_description('Black Friday Sale');
		$discount_code->set_active(true);
		$discount_code->set_type('percentage');
		$discount_code->set_value(50);
		$discount_code->set_setup_fee_type('absolute');
		$discount_code->set_setup_fee_value(10);
		$discount_code->set_max_uses(100);
		$discount_code->set_uses(25);
		$discount_code->set_apply_to_renewals(true);
		$discount_code->set_limit_products(true);
		$discount_code->set_allowed_products([1, 2, 3]);
		$discount_code->set_date_start('2025-11-28 00:00:00');
		$discount_code->set_date_expiration('2025-11-30 23:59:59');
		$discount_code->set_date_created('2025-11-01 00:00:00');

		$this->assertSame('Black Friday', $discount_code->get_name());
		$this->assertSame('BF2025', $discount_code->get_code());
		$this->assertSame('Black Friday Sale', $discount_code->get_description());
		$this->assertTrue($discount_code->is_active());
		$this->assertSame('percentage', $discount_code->get_type());
		$this->assertEquals(50.0, $discount_code->get_value());
		$this->assertSame('absolute', $discount_code->get_setup_fee_type());
		$this->assertEquals(10.0, $discount_code->get_setup_fee_value());
		$this->assertSame(100, $discount_code->get_max_uses());
		$this->assertSame(25, $discount_code->get_uses());
		$this->assertTrue($discount_code->should_apply_to_renewals());
		$this->assertTrue($discount_code->get_limit_products());
		$this->assertSame([1, 2, 3], $discount_code->get_allowed_products());
		$this->assertSame('2025-11-28 00:00:00', $discount_code->get_date_start());
		$this->assertSame('2025-11-30 23:59:59', $discount_code->get_date_expiration());
		$this->assertSame('2025-11-01 00:00:00', $discount_code->get_date_created());
		$this->assertTrue($discount_code->has_max_uses());
		$this->assertTrue($discount_code->is_one_time());
	}
}
