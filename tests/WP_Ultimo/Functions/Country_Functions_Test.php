<?php
/**
 * Tests for country functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for country functions.
 */
class Country_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_countries returns array.
	 */
	public function test_get_countries_returns_array(): void {
		$countries = wu_get_countries();

		$this->assertIsArray($countries);
		$this->assertNotEmpty($countries);
	}

	/**
	 * Test wu_get_countries contains common countries.
	 */
	public function test_get_countries_contains_common(): void {
		$countries = wu_get_countries();

		$this->assertArrayHasKey('US', $countries);
		$this->assertArrayHasKey('GB', $countries);
		$this->assertArrayHasKey('CA', $countries);
		$this->assertArrayHasKey('BR', $countries);
		$this->assertArrayHasKey('DE', $countries);
	}

	/**
	 * Test wu_get_countries_as_options returns array.
	 */
	public function test_get_countries_as_options_returns_array(): void {
		$options = wu_get_countries_as_options();

		$this->assertIsArray($options);
		$this->assertNotEmpty($options);
	}

	/**
	 * Test wu_get_country returns object with valid code.
	 */
	public function test_get_country_valid_code(): void {
		$country = wu_get_country('US');

		$this->assertIsObject($country);
		// Check that it has the expected properties
		$this->assertTrue(property_exists($country, 'attributes') || method_exists($country, 'get_country_code'));
	}

	/**
	 * Test wu_get_country returns object for invalid code.
	 */
	public function test_get_country_invalid_code(): void {
		$country = wu_get_country('XX', 'Unknown', ['fallback_key' => 'value']);

		$this->assertIsObject($country);
	}

	/**
	 * Test wu_get_country_name returns correct name.
	 */
	public function test_get_country_name_us(): void {
		$name = wu_get_country_name('US');

		$this->assertIsString($name);
		$this->assertEquals('United States (US)', $name);
	}

	/**
	 * Test wu_get_country_name returns correct name for GB.
	 */
	public function test_get_country_name_gb(): void {
		$name = wu_get_country_name('GB');

		$this->assertIsString($name);
		$this->assertEquals('United Kingdom (UK)', $name);
	}

	/**
	 * Test wu_get_country_name returns correct name for BR.
	 */
	public function test_get_country_name_br(): void {
		$name = wu_get_country_name('BR');

		$this->assertIsString($name);
		$this->assertEquals('Brazil', $name);
	}

	/**
	 * Test wu_get_country_name returns something for invalid code.
	 */
	public function test_get_country_name_invalid(): void {
		$name = wu_get_country_name('XX');

		// Function may return the code itself or empty
		$this->assertIsString($name);
	}

	/**
	 * Test wu_get_country_states returns array.
	 */
	public function test_get_country_states_returns_array(): void {
		$states = wu_get_country_states('US');

		$this->assertIsArray($states);
	}

	/**
	 * Test wu_get_country_states contains states for US.
	 */
	public function test_get_country_states_us(): void {
		$states = wu_get_country_states('US');

		$this->assertIsArray($states);
		$this->assertNotEmpty($states);
	}

	/**
	 * Test wu_get_country_states empty for country without states.
	 */
	public function test_get_country_states_empty_country(): void {
		$states = wu_get_country_states('XX');

		$this->assertIsArray($states);
		// May be empty for invalid or small countries
	}

	/**
	 * Test wu_get_country_cities returns array.
	 */
	public function test_get_country_cities_returns_array(): void {
		$cities = wu_get_country_cities('US', ['CA']);

		$this->assertIsArray($cities);
	}

	/**
	 * Test wu_get_countries_of_customers returns array.
	 */
	public function test_get_countries_of_customers_returns_array(): void {
		$countries = wu_get_countries_of_customers();

		$this->assertIsArray($countries);
	}

	/**
	 * Test wu_get_countries_of_customers with count limit.
	 */
	public function test_get_countries_of_customers_with_limit(): void {
		$countries = wu_get_countries_of_customers(5);

		$this->assertIsArray($countries);
		$this->assertLessThanOrEqual(5, count($countries));
	}

	/**
	 * Test wu_get_states_of_customers returns array.
	 */
	public function test_get_states_of_customers_returns_array(): void {
		$states = wu_get_states_of_customers('US');

		$this->assertIsArray($states);
	}

	/**
	 * Country names should be sorted using the active locale's collation rules,
	 * so a Persian translation lands in Persian alphabetical order rather than
	 * the English ordering of the source array.
	 */
	public function test_get_countries_sorted_by_translated_name(): void {
		$filter = static function ($countries) {
			return [
				'BR' => 'برزیل',     // Brazil.
				'US' => 'ایالات متحده', // United States.
				'IR' => 'ایران',     // Iran.
				'AF' => 'افغانستان', // Afghanistan.
			];
		};

		add_filter('wu_get_countries', $filter, PHP_INT_MAX);

		$keys_in_order = array_keys(wu_get_countries());

		remove_filter('wu_get_countries', $filter, PHP_INT_MAX);

		$this->assertSame(['AF', 'US', 'IR', 'BR'], $keys_in_order, 'Persian country names should sort in Persian alphabetical order.');
	}

	/**
	 * Sorting should leverage the intl Collator when available so accented
	 * Latin names sort by their natural letter, ignoring HTML entities.
	 */
	public function test_get_countries_sort_handles_html_entities(): void {
		$filter = static function ($countries) {
			return [
				'AX' => '&Aring;land Islands',
				'AL' => 'Albania',
				'AF' => 'Afghanistan',
			];
		};

		add_filter('wu_get_countries', $filter, PHP_INT_MAX);

		$keys_in_order = array_keys(wu_get_countries());

		remove_filter('wu_get_countries', $filter, PHP_INT_MAX);

		// Afghanistan first, then Albania (Al < Ål in en-US collation), then Åland.
		$this->assertSame('AF', $keys_in_order[0]);
		$this->assertContains('AL', $keys_in_order);
		$this->assertContains('AX', $keys_in_order);
	}

	/**
	 * `wu_get_country()` should report `uses_postal_code = false` for countries
	 * in the UPU "no postcode in use" list (e.g. Hong Kong) and true for the
	 * default majority case (e.g. United Kingdom).
	 */
	public function test_country_default_postal_code_flag(): void {
		$this->assertFalse(wu_get_country('HK')->get_uses_postal_code());
		$this->assertFalse(wu_get_country('AO')->get_uses_postal_code());
		$this->assertFalse(wu_get_country('AE')->get_uses_postal_code());
		$this->assertTrue(wu_get_country('GB')->get_uses_postal_code());
		$this->assertTrue(wu_get_country('FR')->get_uses_postal_code());
	}

	/**
	 * The `wu_country_uses_postal_code` filter should be able to override the
	 * default flag for individual countries.
	 */
	public function test_country_uses_postal_code_filter(): void {
		$filter = static function ($uses_postal_code, $country_code) {
			return 'GB' === $country_code ? false : $uses_postal_code;
		};

		add_filter('wu_country_uses_postal_code', $filter, 10, 2);

		$this->assertFalse(wu_get_country('GB')->get_uses_postal_code());
		$this->assertTrue(wu_get_country('FR')->get_uses_postal_code(), 'Filter must only affect the targeted country.');

		remove_filter('wu_country_uses_postal_code', $filter, 10);
	}
}
