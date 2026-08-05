<?php
/**
 * Membership Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Membership;
use WP_Ultimo\Models\Payment;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Checkout\Cart;

/**
 * Returns a membership.
 *
 * @since 2.0.0
 *
 * @param int $membership_id The ID of the membership.
 * @return \WP_Ultimo\Models\Membership|false
 */
function wu_get_membership($membership_id) {

	return Membership::get_by_id($membership_id);
}

/**
 * Returns a single membership defined by a particular column and value.
 *
 * @since 2.0.0
 *
 * @param string $column The column name.
 * @param mixed  $value The column value.
 * @return \WP_Ultimo\Models\Membership|false
 */
function wu_get_membership_by($column, $value) {

	return Membership::get_by($column, $value);
}

/**
 * Gets a membership based on the hash.
 *
 * @since 2.0.0
 *
 * @param string $hash The hash for the membership.
 * @return \WP_Ultimo\Models\Membership|false
 */
function wu_get_membership_by_hash($hash) {

	return Membership::get_by_hash($hash);
}

/**
 * Queries memberships.
 *
 * @since 2.0.0
 *
 * @param array $query Query arguments.
 * @return Membership[]
 */
function wu_get_memberships($query = []) {

	if ( ! empty($query['search'])) {
		$customer_ids = wu_get_customers(
			[
				'search' => $query['search'],
				'fields' => 'ids',
			]
		);

		if ( ! empty($customer_ids)) {
			$query['customer_id__in'] = $customer_ids;

			unset($query['search']);
		}
	}

	return Membership::query($query);
}

/**
 * Creates a new membership.
 *
 * @since 2.0.0
 *
 * @param array $membership_data Membership data.
 * @return \WP_Error|\WP_Ultimo\Models\Membership
 */
function wu_create_membership($membership_data) {
	/*
	* Why do we use shortcode atts here?
	* Shortcode atts clean the array from not-allowed keys, so we don't need to worry much.
	*/
	$membership_data = shortcode_atts(
		[
			'customer_id'             => false,
			'user_id'                 => false,
			'migrated_from_id'        => 0,
			'plan_id'                 => false,
			'addon_products'          => false,
			'currency'                => false,
			'initial_amount'          => false,
			'recurring'               => false,
			'duration'                => 1,
			'duration_unit'           => 'month',
			'amount'                  => false,
			'auto_renew'              => false,
			'times_billed'            => 0,
			'billing_cycles'          => 0,
			'gateway_customer_id'     => false,
			'gateway_subscription_id' => false,
			'gateway'                 => '',
			'signup_method'           => '',
			'upgraded_from'           => false,
			'disabled'                => false,
			'status'                  => 'pending',
			'date_created'            => wu_get_current_time('mysql', true),
			'date_activate'           => null,
			'date_trial_end'          => null,
			'date_renewed'            => null,
			'date_modified'           => wu_get_current_time('mysql', true),
			'date_expiration'         => wu_get_current_time('mysql', true),
			'skip_validation'         => false,
		],
		$membership_data
	);

	$membership_data['migrated_from_id'] = is_numeric($membership_data['migrated_from_id']) ? $membership_data['migrated_from_id'] : 0;

	$membership = new Membership($membership_data);

	$saved = $membership->save();

	return is_wp_error($saved) ? $saved : $membership;
}

/**
 * Get all customers with a specific membership using the product_id as reference.
 *
 * @since 2.0.0
 *
 * @param int|string $product_id Membership product.
 * @return int[]    With all users within the membership.
 */
function wu_get_membership_customers($product_id) {

	global $wpdb;

	is_multisite() && switch_to_blog(get_main_site_id());

	$product_id = (int) $product_id;

	$regex = "(\\\\{i:$product_id;i)|(\\\\{((i:[0-9]*;){2})+(i:$product_id;i:))";

	$table = "{$wpdb->prefix}wu_memberships";

	$query = "SELECT `customer_id` FROM $table WHERE `addon_products` REGEXP '$regex' OR `plan_id` = $product_id";

	$results = $wpdb->get_results($query); // phpcs:ignore

	$results = array_map(fn($result) => (int) $result->customer_id, $results);

	is_multisite() && restore_current_blog();

	return $results;
}

/**
 * Returns a membership based on the customer gateway ID.
 *
 * This is NOT a very reliable way of retrieving memberships
 * as the same customer can have multiple memberships using
 * the same gateway.
 *
 * As this is only used as a last ditch effort, mostly when
 * trying to process payment-related webhooks,
 * we always get pending memberships, and the last one
 * created (order by ID DESC).
 *
 * @since 2.0.0
 *
 * @param string  $customer_gateway_id The customer gateway id. E.g. cus_***.
 * @param array   $allowed_gateways List of allowed gateways.
 * @param boolean $amount The amount. Increases accuracy.
 * @return \WP_Ultimo\Models\Membership|false
 */
function wu_get_membership_by_customer_gateway_id($customer_gateway_id, $allowed_gateways = [], $amount = false) {

	$search_data = [
		'gateway__in'             => $allowed_gateways,
		'number'                  => 1,
		'gateway_customer_id__in' => [$customer_gateway_id],
		'status__in'              => ['pending'],
		'orderby'                 => 'id',
		'order'                   => 'DESC',
	];

	if ( ! empty($amount)) {
		$search_data['initial_amount'] = $amount;
	}

	$memberships = wu_get_memberships($search_data);

	return ! empty($memberships) ? current($memberships) : false;
}

/**
 * Returns the price for a product in a specific membership.
 * This allow us to calculate the values for a product change considering taxes.
 *
 * @since 2.1.3
 *
 * @param Membership $membership     The membership.
 * @param int        $product_id     The product ID.
 * @param int        $quantity       The amount of products.
 * @param bool       $only_recurring Whether to only get the recurring price.
 * @return float|\WP_Error The price or error.
 */
function wu_get_membership_product_price($membership, $product_id, $quantity, $only_recurring = true) {

	$address = $membership->get_billing_address();

	// Create a Cart with this product
	$cart = new Cart(
		[
			'duration'      => $membership->get_duration(),
			'duration_unit' => $membership->get_duration_unit(),
			'country'       => $address->billing_country,
			'state'         => $address->billing_state,
			'city'          => $address->billing_city,
		]
	);

	$discount_code = $membership->get_discount_code();

	if ($discount_code) {
		$cart->add_discount_code($discount_code);
	}

	$added = $cart->add_product($product_id, $quantity);

	if ( ! $added) {
		return $cart->errors;
	}

	$payment_data = array_merge(
		$cart->to_payment_data(),
		[
			'customer_id'   => $membership->get_customer_id(),
			'membership_id' => $membership->get_id(),
			'gateway'       => $membership->get_gateway(),
		]
	);

	// create a temporary payment to see the price.
	$temp_payment = wu_create_payment($payment_data, false);

	if (is_wp_error($temp_payment)) {
		return $temp_payment;
	}

	if ($only_recurring) {
		$temp_payment->remove_non_recurring_items();
	}

	$temp_payment->recalculate_totals();

	return $temp_payment->get_total();
}

/**
 * Creates a new payment for a membership.
 *
 * This is used by gateways to create a new payment when necessary.
 *
 * @since 2.0.0
 *
 * @param Membership $membership The membership object.
 * @param bool       $should_cancel_pending_payments If we should cancel pending payments.
 * @param bool       $remove_non_recurring If we should remove the non recurring items.
 * @param bool       $save If we should save the created payment.
 * @return \WP_Ultimo\Models\Payment|\WP_Error
 */
function wu_membership_create_new_payment($membership, $should_cancel_pending_payments = true, $remove_non_recurring = true, $save = true) {
	/*
	 * If we should cancel the previous
	 * pending payment, do that.
	 */
	if ($should_cancel_pending_payments) {
		$pending_payment = $membership->get_last_pending_payment();

		/*
		 * Change pending payment to cancelled.
		 */
		if ($pending_payment) {
			$pending_payment->set_status(Payment_Status::CANCELLED);
			$pending_payment->save();
		}
	}

	$cart = wu_get_membership_new_cart($membership);

	$payment_data = array_merge(
		$cart->to_payment_data(),
		[
			'customer_id'   => $membership->get_customer_id(),
			'membership_id' => $membership->get_id(),
			'gateway'       => $membership->get_gateway(),
		]
	);

	// We will save the payment after we recalculate the totals.
	$new_payment = wu_create_payment($payment_data, false);

	if (is_wp_error($new_payment)) {
		return $new_payment;
	}

	if ($remove_non_recurring) {
		$new_payment->remove_non_recurring_items();
	}

	/**
	 * Fires before a membership payment has its totals recalculated.
	 *
	 * Addons can add auditable line items to renewal payments without
	 * changing the membership's recurring amount or invoking a gateway.
	 *
	 * @since 2.15.1
	 *
	 * @param \WP_Ultimo\Models\Payment $new_payment         Unsaved payment object.
	 * @param Membership                $membership          Membership being billed.
	 * @param bool                      $remove_non_recurring Whether non-recurring items were removed.
	 * @param bool                      $save                 Whether the payment will be saved.
	 */
	do_action(
		'wu_membership_new_payment_pre_totals',
		$new_payment,
		$membership,
		$remove_non_recurring,
		$save
	);

	$new_payment->recalculate_totals();

	if ( ! $save) {
		return $new_payment;
	}

	$status = $new_payment->save();

	if (is_wp_error($status)) {
		return $status;
	}

	return $new_payment;
}

/**
 * Creates a full cart based on a membership.
 *
 * @since 2.1.3
 *
 * @param Membership $membership The membership object.
 * @return Cart
 */
function wu_get_membership_new_cart($membership) {

	$address = $membership->get_billing_address();

	$cart = new Cart(
		[
			'duration'      => $membership->get_duration(),
			'duration_unit' => $membership->get_duration_unit(),
			'country'       => $address->billing_country,
			'state'         => $address->billing_state,
			'city'          => $address->billing_city,
		]
	);

	$discount_code = $membership->get_discount_code();

	if ($discount_code) {
		$cart->add_discount_code($discount_code);
	}

	foreach ($membership->get_all_products() as $key => $product) {
		$cart->add_product($product['product']->get_id(), $product['quantity']);
	}

	$difference = $membership->get_amount() - $cart->get_recurring_total();

	if (round(abs($difference), wu_currency_decimal_filter()) > 0) {
		$type_translate = $difference < 0 ? __('credit', 'ultimate-multisite') : __('debit', 'ultimate-multisite');

		$line_item_params = [
			'hash'          => 'ADJUSTMENT',
			'type'          => $difference < 0 ? 'credit' : 'fee',
			// translators: %s is the type of adjustment (credit or debit).
			'title'         => sprintf(__('Adjustment %s', 'ultimate-multisite'), $type_translate),
			'description'   => __('Amount adjustment based on previous deal.', 'ultimate-multisite'),
			'unit_price'    => $difference,
			'discountable'  => false,
			'taxable'       => false,
			'recurring'     => true,
			'quantity'      => 1,
			'duration'      => $membership->get_duration(),
			'duration_unit' => $membership->get_duration_unit(),
		];

		$adjustment_line_item = new \WP_Ultimo\Checkout\Line_Item($line_item_params);

		$cart->add_line_item($adjustment_line_item);
	}

	if ($membership->get_initial_amount() !== $cart->get_total()) {
		$t = $membership->get_initial_amount();
		$y = $cart->get_total();

		$difference     = $membership->get_initial_amount() - $cart->get_total();
		$type_translate = $difference < 0 ? __('credit', 'ultimate-multisite') : __('debit', 'ultimate-multisite');

		$line_item_params = [
			'hash'         => 'INITADJUSTMENT',
			'type'         => $difference < 0 ? 'credit' : 'fee',
			// translators: %s is the type of adjustment (credit or debit).
			'title'        => sprintf(__('Adjustment %s', 'ultimate-multisite'), $type_translate),
			'description'  => __('Initial amount adjustment based on previous deal.', 'ultimate-multisite'),
			'unit_price'   => $difference,
			'discountable' => false,
			'taxable'      => false,
			'recurring'    => false,
			'quantity'     => 1,
		];

		$adjustment_line_item = new \WP_Ultimo\Checkout\Line_Item($line_item_params);

		$cart->add_line_item($adjustment_line_item);
	}

	$y = $cart->get_total();
	$t = $cart->get_recurring_total();

	return $cart;
}

/**
 * Generate the modal link to search for an upgrade path.
 *
 * @since 2.1
 *
 * @param Membership $membership The membership to get the url.
 * @return string
 */
function wu_get_membership_update_url($membership) {

	$checkout_pages = \WP_Ultimo\Checkout\Checkout_Pages::get_instance();

	$url = $checkout_pages->get_page_url('update');

	$membership_hash = $membership->get_hash();

	if ($url) {
		return add_query_arg(
			[
				'membership' => $membership_hash,
			],
			$url
		);
	}

	if ( ! is_main_site()) {
		return admin_url('admin.php?page=wu-checkout&membership=' . $membership_hash);
	}

	$sites = $membership->get_sites(false);

	if (count($sites) > 0) {
		return add_query_arg(
			[
				'page'       => 'wu-checkout',
				'membership' => $membership_hash,
			],
			get_admin_url($sites[0]->get_id())
		);
	}

	// In last case we use the default register form
	$url = $checkout_pages->get_page_url('register');

	return add_query_arg(
		[
			'membership' => $membership_hash,
			'wu_form'    => 'wu-checkout',
		],
		$url
	);
}
