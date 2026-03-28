<?php
/**
 * Country Functions
 *
 * @package WP_Ultimo\Functions
 * @since   1.4.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Get all the currencies we use in Ultimate Multisite
 *
 * @return array Return the currencies array.
 */
function wu_get_currencies(): array {

	$currencies = apply_filters(
		'wu_currencies',
		[

			'AED' => __('United Arab Emirates Dirham', 'ultimate-multisite'),
			'AFN' => __('Afghan Afghani', 'ultimate-multisite'),
			'ALL' => __('Albanian Lek', 'ultimate-multisite'),
			'AMD' => __('Armenian Dram', 'ultimate-multisite'),
			'AOA' => __('Angolan Kwanza', 'ultimate-multisite'),
			'ARS' => __('Argentine Peso', 'ultimate-multisite'),
			'AUD' => __('Australian Dollar', 'ultimate-multisite'),
			'AWG' => __('Aruban Florin', 'ultimate-multisite'),
			'AZN' => __('Azerbaijani Manat', 'ultimate-multisite'),
			'BAM' => __('Bosnia & Herzegovina Convertible Mark', 'ultimate-multisite'),
			'BBD' => __('Barbadian Dollar', 'ultimate-multisite'),
			'BDT' => __('Bangladeshi Taka', 'ultimate-multisite'),
			'BGN' => __('Bulgarian Lev', 'ultimate-multisite'),
			'BIF' => __('Burundian Franc', 'ultimate-multisite'),
			'BMD' => __('Bermudian Dollar', 'ultimate-multisite'),
			'BND' => __('Brunei Dollar', 'ultimate-multisite'),
			'BOB' => __('Bolivian Boliviano', 'ultimate-multisite'),
			'BRL' => __('Brazilian Real', 'ultimate-multisite'),
			'BSD' => __('Bahamian Dollar', 'ultimate-multisite'),
			'BWP' => __('Botswana Pula', 'ultimate-multisite'),
			'BYN' => __('Belarusian Ruble', 'ultimate-multisite'),
			'BZD' => __('Belize Dollar', 'ultimate-multisite'),
			'CAD' => __('Canadian Dollar', 'ultimate-multisite'),
			'CDF' => __('Congolese Franc', 'ultimate-multisite'),
			'CHF' => __('Swiss Franc', 'ultimate-multisite'),
			'CLP' => __('Chilean Peso', 'ultimate-multisite'),
			'CNY' => __('Chinese Renminbi Yuan', 'ultimate-multisite'),
			'COP' => __('Colombian Peso', 'ultimate-multisite'),
			'CRC' => __('Costa Rican Colón', 'ultimate-multisite'),
			'CVE' => __('Cape Verdean Escudo', 'ultimate-multisite'),
			'CZK' => __('Czech Koruna', 'ultimate-multisite'),
			'DJF' => __('Djiboutian Franc', 'ultimate-multisite'),
			'DKK' => __('Danish Krone', 'ultimate-multisite'),
			'DOP' => __('Dominican Peso', 'ultimate-multisite'),
			'DZD' => __('Algerian Dinar', 'ultimate-multisite'),
			'EGP' => __('Egyptian Pound', 'ultimate-multisite'),
			'ETB' => __('Ethiopian Birr', 'ultimate-multisite'),
			'EUR' => __('Euro', 'ultimate-multisite'),
			'FJD' => __('Fijian Dollar', 'ultimate-multisite'),
			'FKP' => __('Falkland Islands Pound', 'ultimate-multisite'),
			'GBP' => __('British Pound', 'ultimate-multisite'),
			'GEL' => __('Georgian Lari', 'ultimate-multisite'),
			'GIP' => __('Gibraltar Pound', 'ultimate-multisite'),
			'GMD' => __('Gambian Dalasi', 'ultimate-multisite'),
			'GNF' => __('Guinean Franc', 'ultimate-multisite'),
			'GTQ' => __('Guatemalan Quetzal', 'ultimate-multisite'),
			'GYD' => __('Guyanese Dollar', 'ultimate-multisite'),
			'HKD' => __('Hong Kong Dollar', 'ultimate-multisite'),
			'HNL' => __('Honduran Lempira', 'ultimate-multisite'),
			'HRK' => __('Croatian Kuna', 'ultimate-multisite'),
			'HTG' => __('Haitian Gourde', 'ultimate-multisite'),
			'HUF' => __('Hungarian Forint', 'ultimate-multisite'),
			'IDR' => __('Indonesian Rupiah', 'ultimate-multisite'),
			'ILS' => __('Israeli New Sheqel', 'ultimate-multisite'),
			'INR' => __('Indian Rupee', 'ultimate-multisite'),
			'ISK' => __('Icelandic Króna', 'ultimate-multisite'),
			'JMD' => __('Jamaican Dollar', 'ultimate-multisite'),
			'JPY' => __('Japanese Yen', 'ultimate-multisite'),
			'KES' => __('Kenyan Shilling', 'ultimate-multisite'),
			'KGS' => __('Kyrgyzstani Som', 'ultimate-multisite'),
			'KHR' => __('Cambodian Riel', 'ultimate-multisite'),
			'KMF' => __('Comorian Franc', 'ultimate-multisite'),
			'KRW' => __('South Korean Won', 'ultimate-multisite'),
			'KYD' => __('Cayman Islands Dollar', 'ultimate-multisite'),
			'KZT' => __('Kazakhstani Tenge', 'ultimate-multisite'),
			'LAK' => __('Lao Kip', 'ultimate-multisite'),
			'LBP' => __('Lebanese Pound', 'ultimate-multisite'),
			'LKR' => __('Sri Lankan Rupee', 'ultimate-multisite'),
			'LRD' => __('Liberian Dollar', 'ultimate-multisite'),
			'LSL' => __('Lesotho Loti', 'ultimate-multisite'),
			'MAD' => __('Moroccan Dirham', 'ultimate-multisite'),
			'MDL' => __('Moldovan Leu', 'ultimate-multisite'),
			'MGA' => __('Malagasy Ariary', 'ultimate-multisite'),
			'MKD' => __('Macedonian Denar', 'ultimate-multisite'),
			'MMK' => __('Myanmar Kyat', 'ultimate-multisite'),
			'MNT' => __('Mongolian Tögrög', 'ultimate-multisite'),
			'MOP' => __('Macanese Pataca', 'ultimate-multisite'),
			'MRU' => __('Mauritanian Ouguiya', 'ultimate-multisite'), // MRO seems outdated, MRU modern replacements in ISO 4217.
			'MUR' => __('Mauritian Rupee', 'ultimate-multisite'),
			'MVR' => __('Maldivian Rufiyaa', 'ultimate-multisite'),
			'MWK' => __('Malawian Kwacha', 'ultimate-multisite'),
			'MXN' => __('Mexican Peso', 'ultimate-multisite'),
			'MYR' => __('Malaysian Ringgit', 'ultimate-multisite'),
			'MZN' => __('Mozambican Metical', 'ultimate-multisite'),
			'NAD' => __('Namibian Dollar', 'ultimate-multisite'),
			'NGN' => __('Nigerian Naira', 'ultimate-multisite'),
			'NIO' => __('Nicaraguan Córdoba', 'ultimate-multisite'),
			'NOK' => __('Norwegian Krone', 'ultimate-multisite'),
			'NPR' => __('Nepalese Rupee', 'ultimate-multisite'),
			'NZD' => __('New Zealand Dollar', 'ultimate-multisite'),
			'PAB' => __('Panamanian Balboa', 'ultimate-multisite'),
			'PEN' => __('Peruvian Nuevo Sol', 'ultimate-multisite'),
			'PGK' => __('Papua New Guinean Kina', 'ultimate-multisite'),
			'PHP' => __('Philippine Peso', 'ultimate-multisite'),
			'PKR' => __('Pakistani Rupee', 'ultimate-multisite'),
			'PLN' => __('Polish Złoty', 'ultimate-multisite'),
			'PYG' => __('Paraguayan Guaraní', 'ultimate-multisite'),
			'QAR' => __('Qatari Riyal', 'ultimate-multisite'),
			'RON' => __('Romanian Leu', 'ultimate-multisite'),
			'RSD' => __('Serbian Dinar', 'ultimate-multisite'),
			'RUB' => __('Russian Ruble', 'ultimate-multisite'),
			'RWF' => __('Rwandan Franc', 'ultimate-multisite'),
			'SAR' => __('Saudi Riyal', 'ultimate-multisite'),
			'SBD' => __('Solomon Islands Dollar', 'ultimate-multisite'),
			'SCR' => __('Seychellois Rupee', 'ultimate-multisite'),
			'SEK' => __('Swedish Krona', 'ultimate-multisite'),
			'SGD' => __('Singapore Dollar', 'ultimate-multisite'),
			'SHP' => __('Saint Helenian Pound', 'ultimate-multisite'),
			'SLE' => __('Sierra Leonean Leone', 'ultimate-multisite'), // SLL is outdated, SLE modern replacements in ISO 4217.
			'SOS' => __('Somali Shilling', 'ultimate-multisite'),
			'SRD' => __('Surinamese Dollar', 'ultimate-multisite'),
			'STD' => __('São Tomé and Príncipe Dobra', 'ultimate-multisite'),
			'SVC' => __('Salvadoran Colón', 'ultimate-multisite'),
			'SZL' => __('Swazi Lilangeni', 'ultimate-multisite'),
			'THB' => __('Thai Baht', 'ultimate-multisite'),
			'TJS' => __('Tajikistani Somoni', 'ultimate-multisite'),
			'TOP' => __('Tongan Paʻanga', 'ultimate-multisite'),
			'TRY' => __('Turkish Lira', 'ultimate-multisite'),
			'TTD' => __('Trinidad and Tobago Dollar', 'ultimate-multisite'),
			'TWD' => __('New Taiwan Dollar', 'ultimate-multisite'),
			'TZS' => __('Tanzanian Shilling', 'ultimate-multisite'),
			'UAH' => __('Ukrainian Hryvnia', 'ultimate-multisite'),
			'UGX' => __('Ugandan Shilling', 'ultimate-multisite'),
			'USD' => __('United States Dollar', 'ultimate-multisite'),
			'UYU' => __('Uruguayan Peso', 'ultimate-multisite'),
			'UZS' => __('Uzbekistani Som', 'ultimate-multisite'),
			'VND' => __('Vietnamese Đồng', 'ultimate-multisite'),
			'VUV' => __('Vanuatu Vatu', 'ultimate-multisite'),
			'WST' => __('Samoan Tala', 'ultimate-multisite'),
			'XAF' => __('Central African Cfa Franc', 'ultimate-multisite'),
			'XCD' => __('East Caribbean Dollar', 'ultimate-multisite'),
			'XOF' => __('West African Cfa Franc', 'ultimate-multisite'),
			'XPF' => __('Cfp Franc', 'ultimate-multisite'),
			'XCG' => __('Caribbean Guilder', 'ultimate-multisite'),
			'YER' => __('Yemeni Rial', 'ultimate-multisite'),
			'ZAR' => __('South African Rand', 'ultimate-multisite'),
			'ZMW' => __('Zambian Kwacha', 'ultimate-multisite'),
			'IRR' => __('Iranian Rial', 'ultimate-multisite'),
		]
	);

	return array_unique($currencies);
}

/**
 * Gets the currency symbol of a currency.
 *
 * @since 0.0.1
 *
 * @param string $currency Currency to get symbol of.
 * @return string
 */
function wu_get_currency_symbol($currency = '') {

	if ( ! $currency) {
		$currency = wu_get_setting('currency_symbol', 'USD');
	} switch ($currency) {
		case 'AED':
			$currency_symbol = 'د.إ';
			break;
		case 'AUD':
		case 'ARS':
		case 'CAD':
		case 'CLP':
		case 'COP':
		case 'HKD':
		case 'MXN':
		case 'NZD':
		case 'SGD':
		case 'USD':
		case 'BBD':
			$currency_symbol = '$';
			break;
		case 'IRR':
			$currency_symbol = '﷼';
			break;
		case 'AFN':
			$currency_symbol = '؋';
			break;
		case 'BDT':
			$currency_symbol = '৳&nbsp;';
			break;
		case 'BGN':
			$currency_symbol = 'лв.';
			break;
		case 'BRL':
			$currency_symbol = 'R$';
			break;
		case 'BYN':
			$currency_symbol = 'Br';
		case 'CHF':
			$currency_symbol = 'CHF';
			break;
		case 'CNY':
		case 'JPY':
		case 'RMB':
			$currency_symbol = '&yen;';
			break;
		case 'CZK':
			$currency_symbol = 'Kč';
			break;
		case 'DKK':
			$currency_symbol = 'DKK';
			break;
		case 'DOP':
			$currency_symbol = 'RD$';
			break;
		case 'EGP':
			$currency_symbol = 'E£';
			break;
		case 'EUR':
			$currency_symbol = '&euro;';
			break;
		case 'GBP':
		case 'LBP':
		case 'GIP':
			$currency_symbol = '&pound;';
			break;
		case 'HRK':
			$currency_symbol = 'Kn';
			break;
		case 'HUF':
			$currency_symbol = 'Ft';
			break;
		case 'IDR':
			$currency_symbol = 'Rp';
			break;
		case 'ILS':
			$currency_symbol = '₪';
			break;
		case 'INR':
		case 'NPR':
		case 'LKR':
		case 'SCR':
		case 'PKR':
			$currency_symbol = 'Rs.';
			break;
		case 'ISK':
			$currency_symbol = 'Kr.';
			break;
		case 'KES':
			$currency_symbol = 'KSh';
			break;
		case 'KIP':
			$currency_symbol = '₭';
			break;
		case 'KRW':
			$currency_symbol = '₩';
			break;
		case 'MMK':
			$currency_symbol = 'K';
			break;
		case 'MRU':
			$currency_symbol = 'UM';
			break;
		case 'MYR':
			$currency_symbol = 'RM';
			break;
		case 'NGN':
			$currency_symbol = '₦';
			break;
		case 'NOK':
		case 'SEK':
			$currency_symbol = 'kr';
			break;
		case 'SLE':
			$currency_symbol = 'Le';
			break;
		case 'PHP':
			$currency_symbol = '₱';
			break;
		case 'PLN':
			$currency_symbol = 'zł';
			break;
		case 'PYG':
			$currency_symbol = '₲';
			break;
		case 'RON':
			$currency_symbol = 'lei';
			break;
		case 'RUB':
			$currency_symbol = '₽';
			break;
		case 'THB':
			$currency_symbol = '฿';
			break;
		case 'TRY':
			$currency_symbol = '₺';
			break;
		case 'TWD':
			$currency_symbol = 'NT$';
			break;
		case 'UAH':
			$currency_symbol = '₴';
			break;
		case 'VND':
			$currency_symbol = '₫';
			break;
		case 'ZAR':
			$currency_symbol = 'R';
			break;
		case 'SAR':
			$currency_symbol = 'ر.س';
			break;
		case 'RSD':
			$currency_symbol = 'Дин';
			break;
		case 'TZS':
			$currency_symbol = 'TSh';
			break;
		case 'ALL':
			$currency_symbol = 'Lek';
			break;
		case 'ANG':
		case 'AWG':
			$currency_symbol = 'ƒ';
			break;
		case 'AZN':
			$currency_symbol = '₼';
			break;
		case 'BAM':
			$currency_symbol = 'KM';
			break;
		case 'MKD':
			$currency_symbol = 'ден';
			break;
		case 'UZS':
			$currency_symbol = 'лв';
			break;
		default:
			$currency_symbol = $currency;
			break;
	}

	return apply_filters('wu_currency_symbol', $currency_symbol, $currency);
}

/**
 * Returns the currency precision (number of decimal places) as a valid integer.
 *
 * Reads the `precision` setting and falls back to 2 when the stored value is
 * an empty string, false, or any other non-numeric value. This prevents
 * `number_format()` from receiving an invalid precision argument (which would
 * trigger "Unknown format specifier" errors) and stops `parseFloat('')`
 * returning NaN in the JavaScript counterpart.
 *
 * @since 2.4.13
 * @return int Number of decimal places (minimum 0, default 2).
 */
function wu_get_currency_precision(): int {

	$raw = wu_get_setting('precision', 2);

	/*
	 * Guard against empty string, false, or null stored in the DB.
	 * (int) '' === 0, which is a valid precision, but an empty string
	 * stored in the DB means the setting was never properly saved —
	 * fall back to the standard 2 decimal places.
	 */
	if ('' === $raw || false === $raw || null === $raw) {
		return 2;
	}

	return max(0, (int) $raw);
}

/**
 * Formats a value into our defined format
 *
 * @param  string      $value Value to be processed.
 * @param  string|null $currency Currency code.
 * @param  string|null $format Format to return the string.
 * @param  string|null $thousands_sep Thousands separator.
 * @param  string|null $decimal_sep Decimal separator.
 * @param  string|null $precision Number of decimal places.
 * @return string Formatted Value.
 */
function wu_format_currency($value, $currency = null, $format = null, $thousands_sep = null, $decimal_sep = null, $precision = null) {

	$value = wu_to_float($value);

	$args = [
		'currency'      => $currency,
		'format'        => $format,
		'thousands_sep' => $thousands_sep,
		'decimal_sep'   => $decimal_sep,
		'precision'     => $precision,
	];

	// Remove invalid args
	$args = array_filter($args);

	$atts = wp_parse_args(
		$args,
		[
			'currency'      => wu_get_setting('currency_symbol', 'USD'),
			'format'        => wu_get_setting('currency_position', '%s %v'),
			'thousands_sep' => wu_get_setting('thousand_separator', ','),
			'decimal_sep'   => wu_get_setting('decimal_separator', '.'),
			'precision'     => wu_get_currency_precision(),
		]
	);

	$currency_symbol = wu_get_currency_symbol($atts['currency']);

	// Let wu_currency_decimal_filter override precision for zero-decimal currencies
	// (and give the multi-currency addon a chance to set 3 decimals for e.g. KWD).
	$precision = wu_currency_decimal_filter($atts['precision'], $atts['currency']);

	$value = number_format($value, $precision, $atts['decimal_sep'], $atts['thousands_sep']);

	$format = str_replace('%v', $value, (string) $atts['format']);
	$format = str_replace('%s', $currency_symbol, $format);

	return apply_filters('wu_format_currency', $format, $currency_symbol, $value);
}

/**
 * Determines if Ultimate Multisite is using a zero-decimal currency.
 *
 * @param  string $currency The currency code to check.
 *
 * @since  2.0.0
 * @return bool True if currency set to a zero-decimal currency.
 */
function wu_is_zero_decimal_currency($currency = 'USD') {

	$zero_dec_currencies = [
		'BIF', // Burundian Franc
		'CLP', // Chilean Peso
		'DJF', // Djiboutian Franc
		'GNF', // Guinean Franc
		'HUF', // Hungarian Forint (PayPal treats as zero-decimal)
		'ISK', // Icelandic Króna
		'JPY', // Japanese Yen
		'KMF', // Comorian Franc
		'KRW', // South Korean Won
		'IRR', // Iranian Rial
		'MGA', // Malagasy Ariary
		'PYG', // Paraguayan Guarani
		'RWF', // Rwandan Franc
		'UGX', // Ugandan Shilling
		'VND', // Vietnamese Dong
		'VUV', // Vanuatu Vatu
		'XAF', // Central African CFA Franc
		'XOF', // West African CFA Franc
		'XPF', // CFP Franc
	];

	return apply_filters('wu_is_zero_decimal_currency', in_array(strtoupper($currency), $zero_dec_currencies, true), $currency);
}

/**
 * Returns the correct number of decimal places for the given currency.
 *
 * Zero-decimal currencies always return 0 regardless of the $decimals argument.
 * The `wu_currency_decimal_filter` filter lets the multi-currency addon (or any
 * other code) override this further — e.g. to support 3-decimal currencies like
 * KWD, BHD, OMR.
 *
 * @param int    $decimals Default number of decimal places (typically the site setting).
 * @param string $currency ISO 4217 currency code. Defaults to the site currency setting.
 *
 * @since 2.0.0
 * @return int The number of decimal places.
 */
function wu_currency_decimal_filter($decimals = 2, $currency = '') {

	if (empty($currency)) {
		$currency = wu_get_setting('currency_symbol', 'USD');
	}

	if (wu_is_zero_decimal_currency($currency)) {
		$decimals = 0;
	}

	return apply_filters('wu_currency_decimal_filter', $decimals, $currency);
}

/**
 * Returns the multiplier for the currency. Most currencies are multiplied by 100.
 * Zero decimal currencies should not be multiplied so use 1.
 *
 * @since 2.0.0
 *
 * @param string $currency The currency code, all uppercase.
 * @return int
 */
function wu_stripe_get_currency_multiplier($currency = 'USD') {

	$multiplier = (wu_is_zero_decimal_currency($currency)) ? 1 : 100;

	return apply_filters('wu_stripe_get_currency_multiplier', $multiplier, $currency);
}
