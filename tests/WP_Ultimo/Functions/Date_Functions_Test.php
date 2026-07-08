<?php
/**
 * Tests for date functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for date functions.
 */
class Date_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_validate_date returns true for valid date.
	 */
	public function test_validate_date_valid(): void {
		$this->assertTrue(wu_validate_date('2024-01-15 10:30:00'));
	}

	/**
	 * Test wu_validate_date returns false for invalid date.
	 */
	public function test_validate_date_invalid(): void {
		$this->assertFalse(wu_validate_date('not-a-date'));
	}

	/**
	 * Test wu_validate_date returns true for null.
	 */
	public function test_validate_date_null(): void {
		$this->assertTrue(wu_validate_date(null));
	}

	/**
	 * Test wu_validate_date returns false for false.
	 */
	public function test_validate_date_false(): void {
		$this->assertFalse(wu_validate_date(false));
	}

	/**
	 * Test wu_validate_date returns false for empty string.
	 */
	public function test_validate_date_empty_string(): void {
		$this->assertFalse(wu_validate_date(''));
	}

	/**
	 * Test wu_validate_date with custom format.
	 */
	public function test_validate_date_custom_format(): void {
		$this->assertTrue(wu_validate_date('15/01/2024', 'd/m/Y'));
		$this->assertFalse(wu_validate_date('2024-01-15', 'd/m/Y'));
	}

	/**
	 * Test wu_date returns DateTime object.
	 */
	public function test_wu_date_returns_datetime(): void {
		$date = wu_date('2024-01-15 10:30:00');

		$this->assertInstanceOf(\DateTime::class, $date);
	}

	/**
	 * Test wu_date returns current time for invalid input.
	 */
	public function test_wu_date_invalid_uses_now(): void {
		$date = wu_date('invalid');

		$this->assertInstanceOf(\DateTime::class, $date);
		// Should be close to current time
		$now  = new \DateTime();
		$diff = $now->getTimestamp() - $date->getTimestamp();
		$this->assertLessThan(5, abs($diff)); // Within 5 seconds
	}

	/**
	 * Test wu_date returns current time for false.
	 */
	public function test_wu_date_false_uses_now(): void {
		$date = wu_date(false);

		$this->assertInstanceOf(\DateTime::class, $date);
	}

	/**
	 * Regression: wu_date(null) must not crash and must return a usable DateTime.
	 *
	 * Before the fix, wu_validate_date(null) returned true, so wu_date()
	 * skipped its "now" fallback and \DateTime::createFromFormat(null) returned
	 * false, causing a fatal "Call to a member function format() on false" in
	 * Base_Model::get_formatted_date() when a membership had no expiration date.
	 *
	 * @see \WP_Ultimo\Models\Base_Model::get_formatted_date()
	 */
	public function test_wu_date_null_returns_datetime(): void {
		$date = wu_date(null);

		$this->assertInstanceOf(\DateTime::class, $date);
		$this->assertIsString($date->format('U'));
	}

	/**
	 * Regression: wu_date('') must not crash.
	 */
	public function test_wu_date_empty_string_returns_datetime(): void {
		$date = wu_date('');

		$this->assertInstanceOf(\DateTime::class, $date);
		$this->assertIsString($date->format('U'));
	}

	/**
	 * Regression: wu_date('0000-00-00 00:00:00') (MySQL zero-date) must not
	 * crash and must fall back to "now" rather than yielding a year-0 date
	 * that downstream formatters reject.
	 */
	public function test_wu_date_zero_date_uses_now(): void {
		$date = wu_date('0000-00-00 00:00:00');

		$this->assertInstanceOf(\DateTime::class, $date);

		// Year 0 must not leak through; the fallback should land us at "now".
		$year = (int) $date->format('Y');
		$this->assertGreaterThan(2000, $year);
	}

	/**
	 * Test wu_get_days_ago returns correct value.
	 */
	public function test_get_days_ago(): void {
		$today     = date('Y-m-d H:i:s');
		$yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));

		$days = wu_get_days_ago($yesterday, $today);

		$this->assertEquals(-1, $days);
	}

	/**
	 * Test wu_get_days_ago with same date returns 0.
	 */
	public function test_get_days_ago_same_date(): void {
		$date = '2024-01-15 10:00:00';
		$days = wu_get_days_ago($date, $date);

		$this->assertEquals(0, $days);
	}

	/**
	 * Test wu_get_days_ago with future date.
	 */
	public function test_get_days_ago_future_date(): void {
		$today    = date('Y-m-d H:i:s');
		$tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));

		$days = wu_get_days_ago($tomorrow, $today);

		// Function always returns negative of days diff
		$this->assertEquals(-1, $days);
	}

	/**
	 * Test wu_get_current_time returns string.
	 */
	public function test_get_current_time_returns_string(): void {
		$time = wu_get_current_time('mysql');

		$this->assertIsString($time);
		$this->assertNotEmpty($time);
	}

	/**
	 * Test wu_filter_duration_unit for day singular.
	 */
	public function test_filter_duration_unit_day_singular(): void {
		$unit = wu_filter_duration_unit('day', 1);
		$this->assertEquals('Day', $unit);
	}

	/**
	 * Test wu_filter_duration_unit for days plural.
	 */
	public function test_filter_duration_unit_day_plural(): void {
		$unit = wu_filter_duration_unit('day', 5);
		$this->assertEquals('Days', $unit);
	}

	/**
	 * Test wu_filter_duration_unit for month singular.
	 */
	public function test_filter_duration_unit_month_singular(): void {
		$unit = wu_filter_duration_unit('month', 1);
		$this->assertEquals('Month', $unit);
	}

	/**
	 * Test wu_filter_duration_unit for months plural.
	 */
	public function test_filter_duration_unit_month_plural(): void {
		$unit = wu_filter_duration_unit('month', 3);
		$this->assertEquals('Months', $unit);
	}

	/**
	 * Test wu_filter_duration_unit for year singular.
	 */
	public function test_filter_duration_unit_year_singular(): void {
		$unit = wu_filter_duration_unit('year', 1);
		$this->assertEquals('Year', $unit);
	}

	/**
	 * Test wu_filter_duration_unit for years plural.
	 */
	public function test_filter_duration_unit_year_plural(): void {
		$unit = wu_filter_duration_unit('year', 2);
		$this->assertEquals('Years', $unit);
	}

	/**
	 * Test wu_filter_duration_unit for unknown unit.
	 */
	public function test_filter_duration_unit_unknown(): void {
		$unit = wu_filter_duration_unit('week', 1);
		$this->assertEquals('', $unit);
	}

	/**
	 * Test wu_human_time_diff returns string.
	 */
	public function test_human_time_diff_returns_string(): void {
		$result = wu_human_time_diff(date('Y-m-d H:i:s', strtotime('-1 hour')));

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
	}

	/**
	 * Test wu_convert_php_date_format_to_moment_js_format basic conversion.
	 */
	public function test_convert_date_format_year(): void {
		$format = wu_convert_php_date_format_to_moment_js_format('Y');
		$this->assertEquals('YYYY', $format);
	}

	/**
	 * Test wu_convert_php_date_format_to_moment_js_format for month.
	 */
	public function test_convert_date_format_month(): void {
		$format = wu_convert_php_date_format_to_moment_js_format('m');
		$this->assertEquals('MM', $format);
	}

	/**
	 * Test wu_convert_php_date_format_to_moment_js_format for day.
	 */
	public function test_convert_date_format_day(): void {
		$format = wu_convert_php_date_format_to_moment_js_format('d');
		$this->assertEquals('DD', $format);
	}

	/**
	 * Test wu_convert_php_date_format_to_moment_js_format for full date.
	 */
	public function test_convert_date_format_full(): void {
		$format = wu_convert_php_date_format_to_moment_js_format('Y-m-d');
		$this->assertEquals('YYYY-MM-DD', $format);
	}

	/**
	 * Test wu_convert_php_date_format_to_moment_js_format for time.
	 */
	public function test_convert_date_format_time(): void {
		$format = wu_convert_php_date_format_to_moment_js_format('H:i:s');
		$this->assertEquals('HH:mm:ss', $format);
	}
}
