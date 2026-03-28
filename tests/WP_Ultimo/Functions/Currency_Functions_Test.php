<?php
/**
 * Tests for currency functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for currency functions.
 */
class Currency_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_currencies returns array.
	 */
	public function test_get_currencies_returns_array(): void {
		$currencies = wu_get_currencies();

		$this->assertIsArray($currencies);
		$this->assertNotEmpty($currencies);
	}

	/**
	 * Test wu_get_currencies contains common currencies.
	 */
	public function test_get_currencies_contains_common(): void {
		$currencies = wu_get_currencies();

		$this->assertArrayHasKey('USD', $currencies);
		$this->assertArrayHasKey('EUR', $currencies);
		$this->assertArrayHasKey('GBP', $currencies);
		$this->assertArrayHasKey('BRL', $currencies);
		$this->assertArrayHasKey('JPY', $currencies);
	}

	/**
	 * Test wu_get_currency_symbol returns correct symbols.
	 */
	public function test_get_currency_symbol_usd(): void {
		$symbol = wu_get_currency_symbol('USD');
		$this->assertEquals('$', $symbol);
	}

	/**
	 * Test wu_get_currency_symbol returns correct symbols for EUR.
	 */
	public function test_get_currency_symbol_eur(): void {
		$symbol = wu_get_currency_symbol('EUR');
		$this->assertEquals('&euro;', $symbol);
	}

	/**
	 * Test wu_get_currency_symbol returns correct symbols for GBP.
	 */
	public function test_get_currency_symbol_gbp(): void {
		$symbol = wu_get_currency_symbol('GBP');
		$this->assertEquals('&pound;', $symbol);
	}

	/**
	 * Test wu_get_currency_symbol returns correct symbols for JPY.
	 */
	public function test_get_currency_symbol_jpy(): void {
		$symbol = wu_get_currency_symbol('JPY');
		$this->assertEquals('&yen;', $symbol);
	}

	/**
	 * Test wu_get_currency_symbol returns correct symbols for BRL.
	 */
	public function test_get_currency_symbol_brl(): void {
		$symbol = wu_get_currency_symbol('BRL');
		$this->assertEquals('R$', $symbol);
	}

	/**
	 * Test wu_get_currency_symbol returns code for unknown currency.
	 */
	public function test_get_currency_symbol_unknown(): void {
		$symbol = wu_get_currency_symbol('XYZ');
		$this->assertEquals('XYZ', $symbol);
	}

	/**
	 * Test wu_format_currency formats correctly.
	 */
	public function test_format_currency_basic(): void {
		$formatted = wu_format_currency(99.99, 'USD', '%s %v', ',', '.', 2);

		$this->assertStringContainsString('$', $formatted);
		$this->assertStringContainsString('99.99', $formatted);
	}

	/**
	 * Test wu_format_currency with different format.
	 */
	public function test_format_currency_format_after_value(): void {
		$formatted = wu_format_currency(100, 'EUR', '%v %s', '.', ',', 2);

		$this->assertStringContainsString('&euro;', $formatted);
		$this->assertStringContainsString('100', $formatted);
	}

	/**
	 * Test wu_format_currency with thousands separator.
	 */
	public function test_format_currency_thousands(): void {
		$formatted = wu_format_currency(1000.50, 'USD', '%s %v', ',', '.', 2);

		$this->assertStringContainsString('1,000.50', $formatted);
	}

	/**
	 * Test wu_format_currency with zero value.
	 */
	public function test_format_currency_zero(): void {
		$formatted = wu_format_currency(0, 'USD', '%s %v', ',', '.', 2);

		$this->assertStringContainsString('$', $formatted);
		$this->assertStringContainsString('0.00', $formatted);
	}

	/**
	 * Test wu_is_zero_decimal_currency returns true for JPY.
	 */
	public function test_is_zero_decimal_currency_jpy(): void {
		$this->assertTrue(wu_is_zero_decimal_currency('JPY'));
	}

	/**
	 * Test wu_is_zero_decimal_currency returns true for KRW.
	 */
	public function test_is_zero_decimal_currency_krw(): void {
		$this->assertTrue(wu_is_zero_decimal_currency('KRW'));
	}

	/**
	 * Test wu_is_zero_decimal_currency returns false for USD.
	 */
	public function test_is_zero_decimal_currency_usd(): void {
		$this->assertFalse(wu_is_zero_decimal_currency('USD'));
	}

	/**
	 * Test wu_is_zero_decimal_currency returns false for EUR.
	 */
	public function test_is_zero_decimal_currency_eur(): void {
		$this->assertFalse(wu_is_zero_decimal_currency('EUR'));
	}

	/**
	 * Test wu_stripe_get_currency_multiplier returns 100 for USD.
	 */
	public function test_stripe_currency_multiplier_usd(): void {
		$multiplier = wu_stripe_get_currency_multiplier('USD');
		$this->assertEquals(100, $multiplier);
	}

	/**
	 * Test wu_stripe_get_currency_multiplier returns 1 for JPY.
	 */
	public function test_stripe_currency_multiplier_jpy(): void {
		$multiplier = wu_stripe_get_currency_multiplier('JPY');
		$this->assertEquals(1, $multiplier);
	}

	/**
	 * Test wu_stripe_get_currency_multiplier returns 1 for KRW.
	 */
	public function test_stripe_currency_multiplier_krw(): void {
		$multiplier = wu_stripe_get_currency_multiplier('KRW');
		$this->assertEquals(1, $multiplier);
	}

	/**
	 * Test wu_currency_decimal_filter returns int.
	 */
	public function test_currency_decimal_filter_returns_int(): void {
		$decimals = wu_currency_decimal_filter();
		$this->assertIsInt($decimals);
	}

	/**
	 * Test wu_currency_decimal_filter with custom value.
	 */
	public function test_currency_decimal_filter_custom(): void {
		$decimals = wu_currency_decimal_filter(3);
		$this->assertIsInt($decimals);
	}

	/**
	 * Test wu_get_currency_precision returns 2 when precision setting is empty string.
	 *
	 * Regression test for issue #496: wizard saves precision as '' causing
	 * number_format() to fail with "Unknown format specifier" and JS to show NaN.
	 */
	public function test_get_currency_precision_empty_string_returns_2(): void {
		add_filter(
			'wu_get_setting',
			function ($value, $setting) {
				if ('precision' === $setting) {
					return '';
				}
				return $value;
			},
			10,
			2
		);

		$precision = wu_get_currency_precision();

		remove_all_filters('wu_get_setting');

		$this->assertSame(2, $precision);
	}

	/**
	 * Test wu_get_currency_precision returns 2 when precision setting is false.
	 */
	public function test_get_currency_precision_false_returns_2(): void {
		add_filter(
			'wu_get_setting',
			function ($value, $setting) {
				if ('precision' === $setting) {
					return false;
				}
				return $value;
			},
			10,
			2
		);

		$precision = wu_get_currency_precision();

		remove_all_filters('wu_get_setting');

		$this->assertSame(2, $precision);
	}

	/**
	 * Test wu_get_currency_precision returns the stored integer value when valid.
	 */
	public function test_get_currency_precision_valid_value(): void {
		add_filter(
			'wu_get_setting',
			function ($value, $setting) {
				if ('precision' === $setting) {
					return '3';
				}
				return $value;
			},
			10,
			2
		);

		$precision = wu_get_currency_precision();

		remove_all_filters('wu_get_setting');

		$this->assertSame(3, $precision);
	}

	/**
	 * Test wu_get_currency_precision returns 0 for zero-decimal currencies.
	 */
	public function test_get_currency_precision_zero_is_valid(): void {
		add_filter(
			'wu_get_setting',
			function ($value, $setting) {
				if ('precision' === $setting) {
					return '0';
				}
				return $value;
			},
			10,
			2
		);

		$precision = wu_get_currency_precision();

		remove_all_filters('wu_get_setting');

		$this->assertSame(0, $precision);
	}
}
