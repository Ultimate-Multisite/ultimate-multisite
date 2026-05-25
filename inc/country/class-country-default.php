<?php
/**
 * Default Country class for when country data is not available.
 *
 * @package WP_Ultimo
 * @subpackage Country
 * @since 2.0.11
 */

namespace WP_Ultimo\Country;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Default Country class for when country data is not available.
 *
 * @since 2.0.11
 *
 * @property-read string $code
 * @property-read string $currency
 * @property-read int $phone_code
 */
class Country_Default extends Country {

	/**
	 * Holds the country name.
	 *
	 * @since 2.0.11
	 * @var string
	 */
	protected $name = '';

	/**
	 * Two-letter ISO codes for countries that do not use postal codes.
	 *
	 * Based on the Universal Postal Union "No postcode in use" list. The list
	 * is intentionally conservative — when in doubt we prefer keeping the ZIP
	 * field visible. Site owners can adjust the result for any individual
	 * country via the `wu_country_uses_postal_code` filter exposed by the base
	 * Country class.
	 *
	 * @since 2.5.3
	 * @var string[]
	 */
	protected static $countries_without_postal_codes = [
		'AO', // Angola.
		'AG', // Antigua and Barbuda.
		'AW', // Aruba.
		'BS', // Bahamas.
		'BZ', // Belize.
		'BJ', // Benin.
		'BO', // Bolivia.
		'BW', // Botswana.
		'BF', // Burkina Faso.
		'BI', // Burundi.
		'CM', // Cameroon.
		'CF', // Central African Republic.
		'KM', // Comoros.
		'CG', // Congo (Brazzaville).
		'CD', // Congo (Kinshasa).
		'CK', // Cook Islands.
		'CI', // Ivory Coast.
		'DJ', // Djibouti.
		'DM', // Dominica.
		'GQ', // Equatorial Guinea.
		'ER', // Eritrea.
		'FJ', // Fiji.
		'TF', // French Southern Territories.
		'GM', // Gambia.
		'GH', // Ghana.
		'GD', // Grenada.
		'GY', // Guyana.
		'HK', // Hong Kong.
		'JM', // Jamaica.
		'KI', // Kiribati.
		'MO', // Macao.
		'MW', // Malawi.
		'ML', // Mali.
		'MR', // Mauritania.
		'MS', // Montserrat.
		'NR', // Nauru.
		'NU', // Niue.
		'QA', // Qatar.
		'RW', // Rwanda.
		'KN', // Saint Kitts and Nevis.
		'LC', // Saint Lucia.
		'ST', // Sao Tome and Principe.
		'SC', // Seychelles.
		'SL', // Sierra Leone.
		'SB', // Solomon Islands.
		'SO', // Somalia.
		'SR', // Suriname.
		'SY', // Syria.
		'TZ', // Tanzania.
		'TL', // Timor-Leste.
		'TG', // Togo.
		'TK', // Tokelau.
		'TO', // Tonga.
		'TV', // Tuvalu.
		'UG', // Uganda.
		'AE', // United Arab Emirates.
		'VU', // Vanuatu.
		'YE', // Yemen.
		'ZW', // Zimbabwe.
	];

	/**
	 * Return the country name.
	 *
	 * @since 2.0.11
	 * @return string
	 */
	public function get_name() {

		return $this->name;
	}

	/**
	 * Returns the list of ISO codes treated as not using postal codes.
	 *
	 * Exposed (and filterable) so other code or tests can introspect the list
	 * without instantiating a Country object.
	 *
	 * @since 2.5.3
	 * @return string[]
	 */
	public static function get_countries_without_postal_codes() {

		/**
		 * Filters the list of country ISO codes that do not use postal codes.
		 *
		 * @since 2.5.3
		 *
		 * @param string[] $codes The default list of two-letter ISO codes.
		 * @return string[]
		 */
		return (array) apply_filters(
			'wu_countries_without_postal_codes',
			self::$countries_without_postal_codes
		);
	}

	/**
	 * Creates a country instance with a code and name.
	 *
	 * @since 2.0.11
	 *
	 * @param string $code The country two-letter code.
	 * @param string $name The country name.
	 * @param array  $attributes The country attributes.
	 * @return \WP_Ultimo\Country\Country
	 */
	public static function build($code, $name = null, $attributes = []) {

		$instance = new self();

		$instance->name = $name ?: wu_get_country_name($code);

		$upper_code = strtoupper($code);

		$instance->attributes = wp_parse_args(
			$attributes,
			[
				'country_code' => $upper_code,
				'currency'     => $upper_code,
				'phone_code'   => 00,
			]
		);

		if (in_array($upper_code, self::get_countries_without_postal_codes(), true)) {
			$instance->uses_postal_code = false;
		}

		return $instance;
	}
}
