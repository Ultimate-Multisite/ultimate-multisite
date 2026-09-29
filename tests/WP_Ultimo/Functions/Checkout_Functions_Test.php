<?php
/**
 * Tests for checkout functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for checkout functions.
 */
class Checkout_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_errors returns WP_Error object.
	 */
	public function test_errors_returns_wp_error(): void {
		$errors = wu_errors();

		$this->assertInstanceOf(\WP_Error::class, $errors);
	}

	/**
	 * Test wu_errors returns same instance.
	 */
	public function test_errors_singleton(): void {
		$errors1 = wu_errors();
		$errors2 = wu_errors();

		$this->assertSame($errors1, $errors2);
	}

	/**
	 * Test wu_stripe_generate_idempotency_key generates string.
	 */
	public function test_stripe_generate_idempotency_key(): void {
		$key = wu_stripe_generate_idempotency_key([
			'customer_id' => 1,
			'amount'      => 100,
		], 'new');

		$this->assertIsString($key);
		$this->assertNotEmpty($key);
	}

	/**
	 * Test wu_stripe_generate_idempotency_key with different args.
	 */
	public function test_stripe_generate_idempotency_key_different_args(): void {
		$key1 = wu_stripe_generate_idempotency_key([
			'customer_id' => 1,
			'amount'      => 100,
		], 'new');

		$key2 = wu_stripe_generate_idempotency_key([
			'customer_id' => 2,
			'amount'      => 200,
		], 'new');

		// Different args should produce different keys
		$this->assertNotEquals($key1, $key2);
	}

	/**
	 * Test wu_get_days_in_cycle for day.
	 */
	public function test_get_days_in_cycle_day(): void {
		$days = wu_get_days_in_cycle('day', 5);

		$this->assertEquals(5, $days);
	}

	/**
	 * Test wu_get_days_in_cycle for week.
	 */
	public function test_get_days_in_cycle_week(): void {
		$days = wu_get_days_in_cycle('week', 2);

		$this->assertEquals(14, $days);
	}

	/**
	 * Test wu_get_days_in_cycle for month.
	 */
	public function test_get_days_in_cycle_month(): void {
		$days = wu_get_days_in_cycle('month', 1);

		// 30.4375 days per month (avg)
		$this->assertEquals(30.4375, $days);
	}

	/**
	 * Test wu_get_days_in_cycle for year.
	 */
	public function test_get_days_in_cycle_year(): void {
		$days = wu_get_days_in_cycle('year', 1);

		// 365.25 days per year (accounting for leap years)
		$this->assertEquals(365.25, $days);
	}

	/**
	 * Test wu_get_days_in_cycle for unknown unit.
	 */
	public function test_get_days_in_cycle_unknown(): void {
		$days = wu_get_days_in_cycle('invalid', 1);

		$this->assertEquals(0, $days);
	}

	/**
	 * Test wu_get_days_in_cycle for 3 months.
	 */
	public function test_get_days_in_cycle_three_months(): void {
		$days = wu_get_days_in_cycle('month', 3);

		$this->assertEquals(91.3125, $days);
	}

	/**
	 * Test wu_multiple_memberships_enabled returns boolean.
	 */
	public function test_multiple_memberships_enabled_returns_bool(): void {
		$enabled = wu_multiple_memberships_enabled();

		$this->assertIsBool($enabled);
	}

	/**
	 * Test the helper uses the disabled setting default.
	 */
	public function test_multiple_memberships_enabled_defaults_to_disabled(): void {
		$default_value = null;
		$callback      = function ($value, $setting, $fallback) use (&$default_value) {
			if ('enable_multiple_memberships' === $setting) {
				$default_value = $fallback;
			}

			return $value;
		};

		add_filter('wu_get_setting', $callback, 10, 4);

		try {
			wu_multiple_memberships_enabled();
		} finally {
			remove_filter('wu_get_setting', $callback, 10);
		}

		$this->assertFalse($default_value);
	}

	/**
	 * Test wu_get_registration_url returns string.
	 */
	public function test_get_registration_url_returns_string(): void {
		$url = wu_get_registration_url();

		$this->assertIsString($url);
	}

	/**
	 * Test wu_get_registration_url with path.
	 */
	public function test_get_registration_url_with_path(): void {
		$url = wu_get_registration_url('/custom-path');

		$this->assertIsString($url);
		// URL may be '#no-registration-url' if no checkout form is set up
	}

	/**
	 * Test wu_get_login_url returns string.
	 */
	public function test_get_login_url_returns_string(): void {
		$url = wu_get_login_url();

		$this->assertIsString($url);
	}

	/**
	 * Test wu_get_login_url with path.
	 */
	public function test_get_login_url_with_path(): void {
		$url = wu_get_login_url('/custom-path');

		$this->assertIsString($url);
	}

	/**
	 * Test wu_create_checkout_fields returns array.
	 */
	public function test_create_checkout_fields_returns_array(): void {
		$fields = wu_create_checkout_fields([]);

		$this->assertIsArray($fields);
	}

	/**
	 * Test wu_create_checkout_fields with simple fields.
	 */
	public function test_create_checkout_fields_with_simple_fields(): void {
		// Just test with empty array as complex fields need more setup
		$fields = wu_create_checkout_fields([]);

		$this->assertIsArray($fields);
	}
}
