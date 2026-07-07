<?php
/**
 * Extended tests for checkout functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for checkout functions - extended coverage.
 */
class Checkout_Functions_Extended_Test extends WP_UnitTestCase {

	/**
	 * Test wu_errors returns WP_Error.
	 */
	public function test_errors_returns_wp_error(): void {

		$result = wu_errors();

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test wu_errors returns same instance.
	 */
	public function test_errors_returns_same_instance(): void {

		$this->assertSame(wu_errors(), wu_errors());
	}

	/**
	 * Test wu_stripe_generate_idempotency_key returns string.
	 */
	public function test_stripe_idempotency_key_returns_string(): void {

		$result = wu_stripe_generate_idempotency_key(['test' => 'data']);

		$this->assertIsString($result);
		$this->assertEquals(32, strlen($result)); // MD5 hash length
	}

	/**
	 * Test wu_stripe_generate_idempotency_key is deterministic.
	 */
	public function test_stripe_idempotency_key_deterministic(): void {

		$args = [
			'amount'   => 100,
			'currency' => 'USD',
		];

		$key1 = wu_stripe_generate_idempotency_key($args);
		$key2 = wu_stripe_generate_idempotency_key($args);

		$this->assertEquals($key1, $key2);
	}

	/**
	 * Test wu_stripe_generate_idempotency_key differs for different args.
	 */
	public function test_stripe_idempotency_key_differs(): void {

		$key1 = wu_stripe_generate_idempotency_key(['amount' => 100]);
		$key2 = wu_stripe_generate_idempotency_key(['amount' => 200]);

		$this->assertNotEquals($key1, $key2);
	}

	/**
	 * Test wu_stripe_generate_idempotency_key with context.
	 */
	public function test_stripe_idempotency_key_with_context(): void {

		$result = wu_stripe_generate_idempotency_key(['test' => 1], 'update');

		$this->assertIsString($result);
	}

	/**
	 * Test wu_create_checkout_fields returns array.
	 */
	public function test_create_checkout_fields_returns_array(): void {

		$result = wu_create_checkout_fields([]);

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Test wu_create_checkout_fields with non-array input.
	 */
	public function test_create_checkout_fields_non_array(): void {

		$result = wu_create_checkout_fields(null);

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Test wu_get_registration_url returns string.
	 */
	public function test_get_registration_url_returns_string(): void {

		$result = wu_get_registration_url();

		$this->assertIsString($result);
	}

	/**
	 * Test wu_get_login_url returns string.
	 */
	public function test_get_login_url_returns_string(): void {

		$result = wu_get_login_url();

		$this->assertIsString($result);
	}

	/**
	 * Test wu_multiple_memberships_enabled returns bool.
	 */
	public function test_multiple_memberships_enabled(): void {

		$result = wu_multiple_memberships_enabled();

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_get_days_in_cycle with day.
	 */
	public function test_get_days_in_cycle_day(): void {

		$this->assertEquals(1, wu_get_days_in_cycle('day', 1));
		$this->assertEquals(30, wu_get_days_in_cycle('day', 30));
	}

	/**
	 * Test wu_get_days_in_cycle with week.
	 */
	public function test_get_days_in_cycle_week(): void {

		$this->assertEquals(7, wu_get_days_in_cycle('week', 1));
		$this->assertEquals(14, wu_get_days_in_cycle('week', 2));
	}

	/**
	 * Test wu_get_days_in_cycle with month.
	 */
	public function test_get_days_in_cycle_month(): void {

		$result = wu_get_days_in_cycle('month', 1);

		$this->assertEqualsWithDelta(30.4375, $result, 0.001);
	}

	/**
	 * Test wu_get_days_in_cycle with year.
	 */
	public function test_get_days_in_cycle_year(): void {

		$result = wu_get_days_in_cycle('year', 1);

		$this->assertEqualsWithDelta(365.25, $result, 0.001);
	}

	/**
	 * Test wu_get_days_in_cycle with unknown unit.
	 */
	public function test_get_days_in_cycle_unknown(): void {

		$this->assertEquals(0, wu_get_days_in_cycle('unknown', 1));
	}

	/**
	 * Test wu_register_field_type adds filter.
	 */
	public function test_register_field_type(): void {

		wu_register_field_type('test_field_type', 'TestFieldClass');

		$field_types = apply_filters('wu_checkout_field_types', []);

		$this->assertArrayHasKey('test_field_type', $field_types);
		$this->assertEquals('TestFieldClass', $field_types['test_field_type']);
	}

	/**
	 * Test wu_register_field_template adds filter.
	 */
	public function test_register_field_template(): void {

		wu_register_field_template('pricing_table', 'test_template', 'TestTemplateClass');

		$templates = apply_filters('wu_checkout_field_templates', []);

		$this->assertArrayHasKey('pricing_table', $templates);
		$this->assertArrayHasKey('test_template', $templates['pricing_table']);
	}
}
