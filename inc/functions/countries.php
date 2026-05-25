<?php
/**
 * Country Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Returns the list of countries.
 *
 * @since 2.0.0
 * @return array
 */
function wu_get_countries() {

	$countries = apply_filters(
		'wu_get_countries',
		[
			'AF' => __('Afghanistan', 'ultimate-multisite'),
			'AX' => __('&#197;land Islands', 'ultimate-multisite'),
			'AL' => __('Albania', 'ultimate-multisite'),
			'DZ' => __('Algeria', 'ultimate-multisite'),
			'AS' => __('American Samoa', 'ultimate-multisite'),
			'AD' => __('Andorra', 'ultimate-multisite'),
			'AO' => __('Angola', 'ultimate-multisite'),
			'AI' => __('Anguilla', 'ultimate-multisite'),
			'AQ' => __('Antarctica', 'ultimate-multisite'),
			'AG' => __('Antigua and Barbuda', 'ultimate-multisite'),
			'AR' => __('Argentina', 'ultimate-multisite'),
			'AM' => __('Armenia', 'ultimate-multisite'),
			'AW' => __('Aruba', 'ultimate-multisite'),
			'AU' => __('Australia', 'ultimate-multisite'),
			'AT' => __('Austria', 'ultimate-multisite'),
			'AZ' => __('Azerbaijan', 'ultimate-multisite'),
			'BS' => __('Bahamas', 'ultimate-multisite'),
			'BH' => __('Bahrain', 'ultimate-multisite'),
			'BD' => __('Bangladesh', 'ultimate-multisite'),
			'BB' => __('Barbados', 'ultimate-multisite'),
			'BY' => __('Belarus', 'ultimate-multisite'),
			'BE' => __('Belgium', 'ultimate-multisite'),
			'PW' => __('Belau', 'ultimate-multisite'),
			'BZ' => __('Belize', 'ultimate-multisite'),
			'BJ' => __('Benin', 'ultimate-multisite'),
			'BM' => __('Bermuda', 'ultimate-multisite'),
			'BT' => __('Bhutan', 'ultimate-multisite'),
			'BO' => __('Bolivia', 'ultimate-multisite'),
			'BQ' => __('Bonaire, Saint Eustatius and Saba', 'ultimate-multisite'),
			'BA' => __('Bosnia and Herzegovina', 'ultimate-multisite'),
			'BW' => __('Botswana', 'ultimate-multisite'),
			'BV' => __('Bouvet Island', 'ultimate-multisite'),
			'BR' => __('Brazil', 'ultimate-multisite'),
			'IO' => __('British Indian Ocean Territory', 'ultimate-multisite'),
			'VG' => __('British Virgin Islands', 'ultimate-multisite'),
			'BN' => __('Brunei', 'ultimate-multisite'),
			'BG' => __('Bulgaria', 'ultimate-multisite'),
			'BF' => __('Burkina Faso', 'ultimate-multisite'),
			'BI' => __('Burundi', 'ultimate-multisite'),
			'KH' => __('Cambodia', 'ultimate-multisite'),
			'CM' => __('Cameroon', 'ultimate-multisite'),
			'CA' => __('Canada', 'ultimate-multisite'),
			'CV' => __('Cape Verde', 'ultimate-multisite'),
			'KY' => __('Cayman Islands', 'ultimate-multisite'),
			'CF' => __('Central African Republic', 'ultimate-multisite'),
			'TD' => __('Chad', 'ultimate-multisite'),
			'CL' => __('Chile', 'ultimate-multisite'),
			'CN' => __('China', 'ultimate-multisite'),
			'CX' => __('Christmas Island', 'ultimate-multisite'),
			'CC' => __('Cocos (Keeling) Islands', 'ultimate-multisite'),
			'CO' => __('Colombia', 'ultimate-multisite'),
			'KM' => __('Comoros', 'ultimate-multisite'),
			'CG' => __('Congo (Brazzaville)', 'ultimate-multisite'),
			'CD' => __('Congo (Kinshasa)', 'ultimate-multisite'),
			'CK' => __('Cook Islands', 'ultimate-multisite'),
			'CR' => __('Costa Rica', 'ultimate-multisite'),
			'HR' => __('Croatia', 'ultimate-multisite'),
			'CU' => __('Cuba', 'ultimate-multisite'),
			'CW' => __('Cura&ccedil;ao', 'ultimate-multisite'),
			'CY' => __('Cyprus', 'ultimate-multisite'),
			'CZ' => __('Czech Republic', 'ultimate-multisite'),
			'DK' => __('Denmark', 'ultimate-multisite'),
			'DJ' => __('Djibouti', 'ultimate-multisite'),
			'DM' => __('Dominica', 'ultimate-multisite'),
			'DO' => __('Dominican Republic', 'ultimate-multisite'),
			'EC' => __('Ecuador', 'ultimate-multisite'),
			'EG' => __('Egypt', 'ultimate-multisite'),
			'SV' => __('El Salvador', 'ultimate-multisite'),
			'GQ' => __('Equatorial Guinea', 'ultimate-multisite'),
			'ER' => __('Eritrea', 'ultimate-multisite'),
			'EE' => __('Estonia', 'ultimate-multisite'),
			'ET' => __('Ethiopia', 'ultimate-multisite'),
			'FK' => __('Falkland Islands', 'ultimate-multisite'),
			'FO' => __('Faroe Islands', 'ultimate-multisite'),
			'FJ' => __('Fiji', 'ultimate-multisite'),
			'FI' => __('Finland', 'ultimate-multisite'),
			'FR' => __('France', 'ultimate-multisite'),
			'GF' => __('French Guiana', 'ultimate-multisite'),
			'PF' => __('French Polynesia', 'ultimate-multisite'),
			'TF' => __('French Southern Territories', 'ultimate-multisite'),
			'GA' => __('Gabon', 'ultimate-multisite'),
			'GM' => __('Gambia', 'ultimate-multisite'),
			'GE' => __('Georgia', 'ultimate-multisite'),
			'DE' => __('Germany', 'ultimate-multisite'),
			'GH' => __('Ghana', 'ultimate-multisite'),
			'GI' => __('Gibraltar', 'ultimate-multisite'),
			'GR' => __('Greece', 'ultimate-multisite'),
			'GL' => __('Greenland', 'ultimate-multisite'),
			'GD' => __('Grenada', 'ultimate-multisite'),
			'GP' => __('Guadeloupe', 'ultimate-multisite'),
			'GU' => __('Guam', 'ultimate-multisite'),
			'GT' => __('Guatemala', 'ultimate-multisite'),
			'GG' => __('Guernsey', 'ultimate-multisite'),
			'GN' => __('Guinea', 'ultimate-multisite'),
			'GW' => __('Guinea-Bissau', 'ultimate-multisite'),
			'GY' => __('Guyana', 'ultimate-multisite'),
			'HT' => __('Haiti', 'ultimate-multisite'),
			'HM' => __('Heard Island and McDonald Islands', 'ultimate-multisite'),
			'HN' => __('Honduras', 'ultimate-multisite'),
			'HK' => __('Hong Kong', 'ultimate-multisite'),
			'HU' => __('Hungary', 'ultimate-multisite'),
			'IS' => __('Iceland', 'ultimate-multisite'),
			'IN' => __('India', 'ultimate-multisite'),
			'ID' => __('Indonesia', 'ultimate-multisite'),
			'IR' => __('Iran', 'ultimate-multisite'),
			'IQ' => __('Iraq', 'ultimate-multisite'),
			'IE' => __('Ireland', 'ultimate-multisite'),
			'IM' => __('Isle of Man', 'ultimate-multisite'),
			'IL' => __('Israel', 'ultimate-multisite'),
			'IT' => __('Italy', 'ultimate-multisite'),
			'CI' => __('Ivory Coast', 'ultimate-multisite'),
			'JM' => __('Jamaica', 'ultimate-multisite'),
			'JP' => __('Japan', 'ultimate-multisite'),
			'JE' => __('Jersey', 'ultimate-multisite'),
			'JO' => __('Jordan', 'ultimate-multisite'),
			'KZ' => __('Kazakhstan', 'ultimate-multisite'),
			'KE' => __('Kenya', 'ultimate-multisite'),
			'KI' => __('Kiribati', 'ultimate-multisite'),
			'KW' => __('Kuwait', 'ultimate-multisite'),
			'KG' => __('Kyrgyzstan', 'ultimate-multisite'),
			'LA' => __('Laos', 'ultimate-multisite'),
			'LV' => __('Latvia', 'ultimate-multisite'),
			'LB' => __('Lebanon', 'ultimate-multisite'),
			'LS' => __('Lesotho', 'ultimate-multisite'),
			'LR' => __('Liberia', 'ultimate-multisite'),
			'LY' => __('Libya', 'ultimate-multisite'),
			'LI' => __('Liechtenstein', 'ultimate-multisite'),
			'LT' => __('Lithuania', 'ultimate-multisite'),
			'LU' => __('Luxembourg', 'ultimate-multisite'),
			'MO' => __('Macao S.A.R., China', 'ultimate-multisite'),
			'MK' => __('Macedonia', 'ultimate-multisite'),
			'MG' => __('Madagascar', 'ultimate-multisite'),
			'MW' => __('Malawi', 'ultimate-multisite'),
			'MY' => __('Malaysia', 'ultimate-multisite'),
			'MV' => __('Maldives', 'ultimate-multisite'),
			'ML' => __('Mali', 'ultimate-multisite'),
			'MT' => __('Malta', 'ultimate-multisite'),
			'MH' => __('Marshall Islands', 'ultimate-multisite'),
			'MQ' => __('Martinique', 'ultimate-multisite'),
			'MR' => __('Mauritania', 'ultimate-multisite'),
			'MU' => __('Mauritius', 'ultimate-multisite'),
			'YT' => __('Mayotte', 'ultimate-multisite'),
			'MX' => __('Mexico', 'ultimate-multisite'),
			'FM' => __('Micronesia', 'ultimate-multisite'),
			'MD' => __('Moldova', 'ultimate-multisite'),
			'MC' => __('Monaco', 'ultimate-multisite'),
			'MN' => __('Mongolia', 'ultimate-multisite'),
			'ME' => __('Montenegro', 'ultimate-multisite'),
			'MS' => __('Montserrat', 'ultimate-multisite'),
			'MA' => __('Morocco', 'ultimate-multisite'),
			'MZ' => __('Mozambique', 'ultimate-multisite'),
			'MM' => __('Myanmar', 'ultimate-multisite'),
			'NA' => __('Namibia', 'ultimate-multisite'),
			'NR' => __('Nauru', 'ultimate-multisite'),
			'NP' => __('Nepal', 'ultimate-multisite'),
			'NL' => __('Netherlands', 'ultimate-multisite'),
			'NC' => __('New Caledonia', 'ultimate-multisite'),
			'NZ' => __('New Zealand', 'ultimate-multisite'),
			'NI' => __('Nicaragua', 'ultimate-multisite'),
			'NE' => __('Niger', 'ultimate-multisite'),
			'NG' => __('Nigeria', 'ultimate-multisite'),
			'NU' => __('Niue', 'ultimate-multisite'),
			'NF' => __('Norfolk Island', 'ultimate-multisite'),
			'MP' => __('Northern Mariana Islands', 'ultimate-multisite'),
			'KP' => __('North Korea', 'ultimate-multisite'),
			'NO' => __('Norway', 'ultimate-multisite'),
			'OM' => __('Oman', 'ultimate-multisite'),
			'PK' => __('Pakistan', 'ultimate-multisite'),
			'PS' => __('Palestinian Territory', 'ultimate-multisite'),
			'PA' => __('Panama', 'ultimate-multisite'),
			'PG' => __('Papua New Guinea', 'ultimate-multisite'),
			'PY' => __('Paraguay', 'ultimate-multisite'),
			'PE' => __('Peru', 'ultimate-multisite'),
			'PH' => __('Philippines', 'ultimate-multisite'),
			'PN' => __('Pitcairn', 'ultimate-multisite'),
			'PL' => __('Poland', 'ultimate-multisite'),
			'PT' => __('Portugal', 'ultimate-multisite'),
			'PR' => __('Puerto Rico', 'ultimate-multisite'),
			'QA' => __('Qatar', 'ultimate-multisite'),
			'RE' => __('Reunion', 'ultimate-multisite'),
			'RO' => __('Romania', 'ultimate-multisite'),
			'RU' => __('Russia', 'ultimate-multisite'),
			'RW' => __('Rwanda', 'ultimate-multisite'),
			'BL' => __('Saint Barth&eacute;lemy', 'ultimate-multisite'),
			'SH' => __('Saint Helena', 'ultimate-multisite'),
			'KN' => __('Saint Kitts and Nevis', 'ultimate-multisite'),
			'LC' => __('Saint Lucia', 'ultimate-multisite'),
			'MF' => __('Saint Martin (French part)', 'ultimate-multisite'),
			'SX' => __('Saint Martin (Dutch part)', 'ultimate-multisite'),
			'PM' => __('Saint Pierre and Miquelon', 'ultimate-multisite'),
			'VC' => __('Saint Vincent and the Grenadines', 'ultimate-multisite'),
			'SM' => __('San Marino', 'ultimate-multisite'),
			'ST' => __('S&atilde;o Tom&eacute; and Pr&iacute;ncipe', 'ultimate-multisite'),
			'SA' => __('Saudi Arabia', 'ultimate-multisite'),
			'SN' => __('Senegal', 'ultimate-multisite'),
			'RS' => __('Serbia', 'ultimate-multisite'),
			'SC' => __('Seychelles', 'ultimate-multisite'),
			'SL' => __('Sierra Leone', 'ultimate-multisite'),
			'SG' => __('Singapore', 'ultimate-multisite'),
			'SK' => __('Slovakia', 'ultimate-multisite'),
			'SI' => __('Slovenia', 'ultimate-multisite'),
			'SB' => __('Solomon Islands', 'ultimate-multisite'),
			'SO' => __('Somalia', 'ultimate-multisite'),
			'ZA' => __('South Africa', 'ultimate-multisite'),
			'GS' => __('South Georgia/Sandwich Islands', 'ultimate-multisite'),
			'KR' => __('South Korea', 'ultimate-multisite'),
			'SS' => __('South Sudan', 'ultimate-multisite'),
			'ES' => __('Spain', 'ultimate-multisite'),
			'LK' => __('Sri Lanka', 'ultimate-multisite'),
			'SD' => __('Sudan', 'ultimate-multisite'),
			'SR' => __('Suriname', 'ultimate-multisite'),
			'SJ' => __('Svalbard and Jan Mayen', 'ultimate-multisite'),
			'SZ' => __('Swaziland', 'ultimate-multisite'),
			'SE' => __('Sweden', 'ultimate-multisite'),
			'CH' => __('Switzerland', 'ultimate-multisite'),
			'SY' => __('Syria', 'ultimate-multisite'),
			'TW' => __('Taiwan', 'ultimate-multisite'),
			'TJ' => __('Tajikistan', 'ultimate-multisite'),
			'TZ' => __('Tanzania', 'ultimate-multisite'),
			'TH' => __('Thailand', 'ultimate-multisite'),
			'TL' => __('Timor-Leste', 'ultimate-multisite'),
			'TG' => __('Togo', 'ultimate-multisite'),
			'TK' => __('Tokelau', 'ultimate-multisite'),
			'TO' => __('Tonga', 'ultimate-multisite'),
			'TT' => __('Trinidad and Tobago', 'ultimate-multisite'),
			'TN' => __('Tunisia', 'ultimate-multisite'),
			'TR' => __('Turkey', 'ultimate-multisite'),
			'TM' => __('Turkmenistan', 'ultimate-multisite'),
			'TC' => __('Turks and Caicos Islands', 'ultimate-multisite'),
			'TV' => __('Tuvalu', 'ultimate-multisite'),
			'UG' => __('Uganda', 'ultimate-multisite'),
			'UA' => __('Ukraine', 'ultimate-multisite'),
			'AE' => __('United Arab Emirates', 'ultimate-multisite'),
			'GB' => __('United Kingdom (UK)', 'ultimate-multisite'),
			'US' => __('United States (US)', 'ultimate-multisite'),
			'UM' => __('United States (US) Minor Outlying Islands', 'ultimate-multisite'),
			'VI' => __('United States (US) Virgin Islands', 'ultimate-multisite'),
			'UY' => __('Uruguay', 'ultimate-multisite'),
			'UZ' => __('Uzbekistan', 'ultimate-multisite'),
			'VU' => __('Vanuatu', 'ultimate-multisite'),
			'VA' => __('Vatican', 'ultimate-multisite'),
			'VE' => __('Venezuela', 'ultimate-multisite'),
			'VN' => __('Vietnam', 'ultimate-multisite'),
			'WF' => __('Wallis and Futuna', 'ultimate-multisite'),
			'EH' => __('Western Sahara', 'ultimate-multisite'),
			'WS' => __('Samoa', 'ultimate-multisite'),
			'YE' => __('Yemen', 'ultimate-multisite'),
			'ZM' => __('Zambia', 'ultimate-multisite'),
			'ZW' => __('Zimbabwe', 'ultimate-multisite'),
		]
	);

	wu_sort_countries_by_translated_name($countries);

	return $countries;
}

/**
 * Sorts a country code => name map by the translated/localized name in place.
 *
 * Uses the PHP intl Collator when available so non-Latin scripts (Persian,
 * Arabic, Chinese, Japanese, Cyrillic, etc.) sort correctly for the active
 * locale. Falls back to `strnatcasecmp` so the result is still meaningful on
 * installations without the `intl` extension.
 *
 * The function intentionally accepts the array by reference and returns void
 * so it can be applied to results of other country filters by callers without
 * an intermediate assignment.
 *
 * @since 2.5.3
 *
 * @param array<string, string> $countries Country code => translated name.
 * @return void
 */
function wu_sort_countries_by_translated_name(array &$countries) {

	if (count($countries) < 2) {
		return;
	}

	$locale = function_exists('determine_locale') ? determine_locale() : get_locale();

	if (class_exists('Collator')) {
		$collator = new \Collator($locale);

		if ($collator) {
			// Decode HTML entities so accented names (e.g. "&Aring;land Islands")
			// sort by their natural letter rather than by the ampersand.
			$decoded = array_map(
				static fn($name) => html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				$countries
			);

			uksort(
				$countries,
				static function ($key_a, $key_b) use ($decoded, $collator) {
					return $collator->compare($decoded[ $key_a ], $decoded[ $key_b ]);
				}
			);

			return;
		}
	}

	uasort(
		$countries,
		static function ($name_a, $name_b) {
			$a = html_entity_decode((string) $name_a, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$b = html_entity_decode((string) $name_b, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			return strnatcasecmp($a, $b);
		}
	);
}

/**
 * Returns the list of countries with an additional empty state option.
 *
 * @since 2.0.0
 * @return array
 */
function wu_get_countries_as_options() {

	return array_merge(
		[
			'' => __('Select Country', 'ultimate-multisite'),
		],
		wu_get_countries()
	);
}

/**
 * Returns the country object.
 *
 * @since 2.0.11
 *
 * @param string      $country_code Two-letter country ISO code.
 * @param string|null $name The country name.
 * @param array       $fallback_attributes Fallback attributes if the country class is not present.
 * @return \WP_Ultimo\Country\Country
 */
function wu_get_country($country_code, $name = null, $fallback_attributes = []) {

	$country_code = strtoupper($country_code);

	$country_class = "\\WP_Ultimo\\Country\\Country_{$country_code}";

	if (class_exists($country_class)) {
		return $country_class::get_instance();
	}

	return \WP_Ultimo\Country\Country_Default::build($country_code, $name, $fallback_attributes);
}

/**
 * Get the state list for a country.
 *
 * @since 2.0.12
 *
 * @param string $country_code The country code.
 * @param string $key_name The name to use for the key entry.
 * @param string $value_name The name to use for the value entry.
 * @return array
 */
function wu_get_country_states($country_code, $key_name = 'id', $value_name = 'value') {

	static $state_options = [];

	$options = [];

	$cache = wu_get_isset($state_options, $country_code, false);

	if ($cache) {
		$options = $cache;
	} else {
		$country = wu_get_country($country_code);

		$state_options[ $country_code ] = $country->get_states_as_options(false);

		$options = $state_options[ $country_code ];
	}

	if (empty($key_name)) {
		return $options;
	}

	return wu_key_map_to_array($options, $key_name, $value_name);
}

/**
 * Get cities for a collection of states of a country.
 *
 * @since 2.0.11
 *
 * @param string $country_code The country code.
 * @param array  $states The list of state codes to retrieve.
 * @param string $key_name The name to use for the key entry.
 * @param string $value_name The name to use for the value entry.
 * @return array
 */
function wu_get_country_cities($country_code, $states, $key_name = 'id', $value_name = 'value') {

	static $city_options = [];

	$states = (array) $states;

	$options = [];

	foreach ($states as $state_code) {
		$cache = wu_get_isset($city_options, $state_code, false);

		if ($cache) {
			$options = array_merge($options, $cache);
		} else {
			$country = wu_get_country($country_code);

			$city_options[ $state_code ] = $country->get_cities_as_options($state_code, false);

			$options = array_merge($options, $city_options[ $state_code ]);
		}
	}

	if (empty($key_name)) {
		return $options;
	}

	return wu_key_map_to_array($options, $key_name, $value_name);
}

/**
 * Returns the country name for a given country code.
 *
 * @since 2.0.0
 *
 * @param string $country_code Country code.
 * @return string
 */
function wu_get_country_name($country_code) {

	$country_name = wu_get_isset(wu_get_countries(), $country_code, __('Not found', 'ultimate-multisite'));

	return apply_filters('wu_get_country_name', $country_name, $country_code);
}

/**
 * Get the list of countries and counts based on customers.
 *
 * @since 2.0.0
 * @param integer        $count The number of results to return.
 * @param boolean|string $start_date The start date.
 * @param boolean|string $end_date The end date.
 * @return array
 */
function wu_get_countries_of_customers($count = 10, $start_date = false, $end_date = false) {

	global $wpdb;

	$table_name          = "{$wpdb->base_prefix}wu_customermeta";
	$customer_table_name = "{$wpdb->base_prefix}wu_customers";

	$date_query = '';

	if ($start_date || $end_date) {
		$date_query = 'AND c.date_registered >= %s AND c.date_registered <= %s';

		$date_query = $wpdb->prepare($date_query, $start_date . ' 00:00:00', $end_date . " 23:59:59"); // phpcs:ignore
	}

	$query = "
		SELECT m.meta_value as country, COUNT(distinct c.id) as count
		FROM `{$table_name}` as m
		INNER JOIN `{$customer_table_name}` as c ON m.wu_customer_id = c.id
		WHERE m.meta_key = 'ip_country' AND m.meta_value != ''
		$date_query
		GROUP BY m.meta_value
		ORDER BY count DESC
		LIMIT %d
	";

	$query = $wpdb->prepare($query, $count); // phpcs:ignore

	$results = $wpdb->get_results($query); // phpcs:ignore

	$countries = [];

	foreach ($results as $result) {
		$countries[ $result->country ] = $result->count;
	}

	return $countries;
}

/**
 * Get the list of countries and counts based on customers.
 *
 * @since 2.0.0
 * @param string         $country_code The country code.
 * @param integer        $count The number of results to return.
 * @param boolean|string $start_date The start date.
 * @param boolean|string $end_date The end date.
 * @return array
 */
function wu_get_states_of_customers($country_code, $count = 100, $start_date = false, $end_date = false) {

	global $wpdb;

	static $states = [];

	$table_name          = "{$wpdb->base_prefix}wu_customermeta";
	$customer_table_name = "{$wpdb->base_prefix}wu_customers";

	$date_query = '';

	if ($start_date || $end_date) {
		$date_query = 'AND c.date_registered >= %s AND c.date_registered <= %s';

		$date_query = $wpdb->prepare($date_query, $start_date . ' 00:00:00', $end_date . " 23:59:59"); // phpcs:ignore
	}

	$states = wu_get_country_states('BR', false);

	if (empty($states)) {
		return [];
	}

	$states_in = implode("','", array_keys($states));

	$query = "
		SELECT m.meta_value as state, COUNT(distinct c.id) as count
		FROM `{$table_name}` as m
		INNER JOIN `{$customer_table_name}` as c ON m.wu_customer_id = c.id
		WHERE m.meta_key = 'ip_state' AND m.meta_value IN ('$states_in')
		$date_query
		GROUP BY m.meta_value
		ORDER BY count DESC
		LIMIT %d
	";

	$query = $wpdb->prepare($query, $count); // phpcs:ignore

	$results = $wpdb->get_results($query); // phpcs:ignore

	if (empty($results)) {
		return [];
	}

	$_states = [];

	foreach ($results as $result) {
		$final_label = sprintf('%s (%s)', $states[ $result->state ], $result->state);

		$_states[ $final_label ] = absint($result->count);
	}

	return $_states;
}
