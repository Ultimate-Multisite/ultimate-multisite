<?php
/**
 * Discount Code Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Discount_Code;

/**
 * Returns a discount code object searching by the code.
 *
 * @since 2.0.0
 *
 * @param string $coupon_code Coupon code to search.
 * @return \WP_Ultimo\Models\Discount_Code|false
 */
function wu_get_discount_code_by_code($coupon_code) {

	return \WP_Ultimo\Models\Discount_Code::get_by('code', $coupon_code);
}

/**
 * Gets a discount code based on the ID.
 *
 * @since 2.0.0
 *
 * @param integer $discount_code_id ID of the discount code to retrieve.
 * @return \WP_Ultimo\Models\Discount_Code|false
 */
function wu_get_discount_code($discount_code_id) {

	return \WP_Ultimo\Models\Discount_Code::get_by_id($discount_code_id);
}

/**
 * Queries discount codes.
 *
 * @since 2.0.0
 *
 * @param array $query Query arguments.
 * @return \WP_Ultimo\Models\Discount_Code[]
 */
function wu_get_discount_codes($query = []) {

	return \WP_Ultimo\Models\Discount_Code::query($query);
}

/**
 * Calculates the discounted price after running it through the discount code.
 *
 * @since 2.0.0
 *
 * @param float   $base_price Original price of the product.
 * @param float   $amount Discount amount.
 * @param string  $type Type of the discount, can be percentage or absolute.
 * @param boolean $format If we should format the results or not.
 * @return float|string
 */
function wu_get_discounted_price($base_price, $amount, $type, $format = true) {

	// Default to the base price so an unknown discount type fails safe to
	// "no discount applied" instead of an undefined variable.
	$discounted_price = $base_price;

	if ('percentage' === $type) {
		$discounted_price = $base_price - ($base_price * ($amount / 100));
	} elseif ('absolute' === $type) {
		$discounted_price = $base_price - $amount;
	}

	if ( ! $format) {
		return $discounted_price;
	}

	return number_format((float) $discounted_price, 2);
}

/**
 * Creates a new discount code.
 *
 * Check the wp_parse_args below to see what parameters are necessary.
 *
 * @since 2.0.0
 *
 * @param array $discount_code_data Discount code attributes.
 * @return \WP_Error|\WP_Ultimo\Models\Discount_Code
 */
function wu_create_discount_code($discount_code_data) {

	$discount_code_data = wp_parse_args(
		$discount_code_data,
		[
			'max_uses'        => true,
			'name'            => false,
			'code'            => false,
			'value'           => false,
			'setup_fee_value' => 0,
			'start_date'      => false,
			'active'          => true,
			'expiration_date' => false,
			'date_created'    => wu_get_current_time('mysql', true),
			'date_modified'   => wu_get_current_time('mysql', true),
			'skip_validation' => false,
		]
	);

	$discount_code = new Discount_Code($discount_code_data);

	$saved = $discount_code->save();

	return is_wp_error($saved) ? $saved : $discount_code;
}
