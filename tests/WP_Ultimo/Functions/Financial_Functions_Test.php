<?php
/**
 * Tests for financial functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for financial functions.
 */
class Financial_Functions_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		// These tests explicitly exercise the empty aggregate dataset.
		$wpdb->query("DELETE FROM {$wpdb->base_prefix}wu_memberships");
		$wpdb->query("DELETE FROM {$wpdb->base_prefix}wu_payments");
	}

	/**
	 * Test wu_convert_duration_unit_to_month with day.
	 */
	public function test_convert_duration_unit_to_month_day(): void {
		$result = wu_convert_duration_unit_to_month('day');
		$this->assertEqualsWithDelta(1 / 30, $result, 0.001);
	}

	/**
	 * Test wu_convert_duration_unit_to_month with week.
	 */
	public function test_convert_duration_unit_to_month_week(): void {
		$result = wu_convert_duration_unit_to_month('week');
		$this->assertEqualsWithDelta(1 / 4, $result, 0.001);
	}

	/**
	 * Test wu_convert_duration_unit_to_month with month.
	 */
	public function test_convert_duration_unit_to_month_month(): void {
		$result = wu_convert_duration_unit_to_month('month');
		$this->assertEquals(1, $result);
	}

	/**
	 * Test wu_convert_duration_unit_to_month with year.
	 */
	public function test_convert_duration_unit_to_month_year(): void {
		$result = wu_convert_duration_unit_to_month('year');
		$this->assertEquals(12, $result);
	}

	/**
	 * Test wu_convert_duration_unit_to_month with unknown value.
	 */
	public function test_convert_duration_unit_to_month_unknown(): void {
		$result = wu_convert_duration_unit_to_month('unknown');
		$this->assertEquals(1, $result); // Defaults to 1
	}

	/**
	 * Test wu_convert_duration_unit_to_month with empty string.
	 */
	public function test_convert_duration_unit_to_month_empty(): void {
		$result = wu_convert_duration_unit_to_month('');
		$this->assertEquals(1, $result); // Defaults to 1
	}

	/**
	 * Test wu_calculate_arr is 12 times MRR.
	 */
	public function test_calculate_arr_is_12_times_mrr(): void {
		// When there are no memberships, ARR should be 0
		$arr = wu_calculate_arr();
		$mrr = wu_calculate_mrr();

		$this->assertEquals($mrr * 12, $arr);
	}

	/**
	 * Test wu_calculate_mrr returns 0 with no memberships.
	 */
	public function test_calculate_mrr_no_memberships(): void {
		$result = wu_calculate_mrr();
		$this->assertEquals(0.0, $result);
	}

	/**
	 * Test wu_calculate_revenue returns 0 with no payments.
	 */
	public function test_calculate_revenue_no_payments(): void {
		$result = wu_calculate_revenue();
		$this->assertEquals(0.0, $result);
	}

	/**
	 * Test wu_calculate_refunds returns 0 with no refunds.
	 */
	public function test_calculate_refunds_no_refunds(): void {
		$result = wu_calculate_refunds();
		$this->assertEquals(0.0, $result);
	}

	/**
	 * Test wu_calculate_taxes_by_rate returns empty array with no line items.
	 */
	public function test_calculate_taxes_by_rate_empty(): void {
		$result = wu_calculate_taxes_by_rate('2020-01-01', '2020-12-31');
		$this->assertIsArray($result);
	}

	/**
	 * Test wu_calculate_financial_data_by_product returns array.
	 */
	public function test_calculate_financial_data_by_product_empty(): void {
		$result = wu_calculate_financial_data_by_product('2020-01-01', '2020-12-31');
		$this->assertIsArray($result);
	}
}
