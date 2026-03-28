<?php
/**
 * The Membership model.
 *
 * @package WP_Ultimo
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Models;

use WP_Ultimo\Checkout\Cart;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Models\Interfaces\Billable;
use WP_Ultimo\Models\Interfaces\Limitable;
use WP_Ultimo\Models\Interfaces\Notable;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Membership model class. Implements the Base Model.
 *
 * @since 2.0.0
 */
class Membership extends Base_Model implements Limitable, Billable, Notable {

	use Traits\Limitable;
	use Traits\Billable;
	use Traits\Notable;
	use \WP_Ultimo\Traits\WP_Ultimo_Subscription_Deprecated;

	/**
	 * Meta key for swap order.
	 */
	const META_SWAP_ORDER = 'wu_swap_order';

	/**
	 * Meta key for swap scheduled date.
	 */
	const META_SWAP_SCHEDULED_DATE = 'wu_swap_scheduled_date';

	/**
	 * Meta key for cancellation reason.
	 */
	const META_CANCELLATION_REASON = 'cancellation_reason';

	/**
	 * Meta key for discount code.
	 */
	const META_DISCOUNT_CODE = 'discount_code';

	/**
	 * Meta key for verified payment discount.
	 */
	const META_VERIFIED_PAYMENT_DISCOUNT = 'verified_payment_discount';

	/**
	 * Meta key for pending site.
	 */
	const META_PENDING_SITE = 'pending_site';

	/**
	 * ID of the customer attached to this membership.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $customer_id;

	/**
	 * User ID attached to this membership.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $user_id;

	/**
	 * Plan associated with the membership.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $plan_id;

	/**
	 * Additional products. Services and Packages.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $addon_products = [];

	/**
	 * Currency for this membership. 3-letter currency code.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $currency;

	/**
	 * Initial amount for the subscription. Includes the setup fee.
	 *
	 * @since 2.0.0
	 * @var float
	 */
	protected $initial_amount = 0;

	/**
	 * Is this product recurring?
	 *
	 * @since 2.0.0
	 * @var int|boolean
	 */
	protected $recurring = 1;

	/**
	 * Should auto-renew?
	 *
	 * @since 2.0.0
	 * @var int|boolean
	 */
	protected $auto_renew = 0;

	/**
	 * Time interval between charges.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $duration = 1;

	/**
	 * Time interval unit between charges.
	 *
	 * - day
	 * - week
	 * - month
	 * - year
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $duration_unit = 'month';

	/**
	 * Amount to charge recurrently.
	 *
	 * @since 2.0.0
	 * @var float
	 */
	protected $amount = 0;

	/**
	 * Date of creation of this membership.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_created;

	/**
	 * Date of activation of this membership.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_activated;

	/**
	 * Date of the end of the trial period.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_trial_end;

	/**
	 * Date of the next renewal.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_renewed;

	/**
	 * Date of the cancellation.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_cancellation;

	/**
	 * Date of expiration.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_expiration;

	/**
	 * Change of the payment completion for the plan value.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_payment_plan_completed;

	/**
	 * Amount of times this membership got billed.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $times_billed = 0;

	/**
	 * Maximum times we should charge this membership.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $billing_cycles;

	/**
	 * Status of the membership.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $status;

	/**
	 * ID of the customer on the payment gateway database.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $gateway_customer_id;

	/**
	 * ID of the subscription on the payment gateway database.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $gateway_subscription_id;

	/**
	 * ID of the gateway being used on this subscription.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $gateway;

	/**
	 * Signup method used to create this membership.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $signup_method;

	/**
	 * Plan that this membership upgraded from.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $upgraded_from;

	/**
	 * Date this membership was last modified.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_modified;

	/**
	 * If this membership is disabled.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $disabled;


	/**
	 * The discount code applied to this membership
	 *
	 * @since 2.0.20
	 * @var \WP_Ultimo\Models\Discount_Code
	 */
	protected $discount_code;

	/**
	 * The reason for the cancellation of this membership
	 *
	 * @since 2.1.2
	 * @var string
	 */
	protected $cancellation_reason;

	/**
	 * Network ID for multinetwork support.
	 *
	 * @since 2.3.0
	 * @var int|null
	 */
	protected $network_id;

	/**
	 * Keep original list of products.
	 *
	 * If the products are changed for some reason,
	 * we need to run additional code to handle updates
	 * in other parts of the code.
	 *
	 * For example, when products change, we
	 * might need to change the user role in every
	 * sub-site belonging to that membership.
	 *
	 * @since 2.0.10
	 * @var array
	 */
	protected $compiled_product_list = [];

	/**
	 * Keep original gateway info.
	 *
	 * If some gateway info change for some reason,
	 * we need to run additional code to handle updates
	 * in other parts of the code (eg: Cancel other gateways).
	 *
	 * @since 2.0.15
	 * @var array
	 */
	protected $gateway_info = [];

	/**
	 * Query Class to the static query methods.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Memberships\Membership_Query::class;

	/**
	 * Constructs the object via the constructor arguments
	 *
	 * @since 2.0.0
	 *
	 * @param object $object_model Std object with model parameters.
	 */
	public function __construct($object_model = null) {

		parent::__construct($object_model);

		$this->gateway_info = [
			'gateway'                 => $this->get_gateway(),
			'gateway_customer_id'     => $this->get_gateway_customer_id(),
			'gateway_subscription_id' => $this->get_gateway_subscription_id(),
		];

		if (did_action('plugins_loaded')) {
			$this->compiled_product_list = $this->get_all_products();
		}
	}

	/**
	 * Set the validation rules for this particular model.
	 *
	 * To see how to setup rules, check the documentation of the
	 * validation library we are using: https://github.com/rakit/validation
	 *
	 * @since 2.0.0
	 * @link https://github.com/rakit/validation
	 * @return array
	 */
	public function validation_rules() {

		$currency = wu_get_setting('currency_symbol', 'USD');

		$membership_status = new \WP_Ultimo\Database\Memberships\Membership_Status();

		$membership_status = $membership_status->get_allowed_list(true);

		return [
			'customer_id'         => 'required|integer|exists:\WP_Ultimo\Models\Customer,id',
			'user_id'             => 'integer',
			'plan_id'             => 'required|integer|exists:\WP_Ultimo\Models\Product,id',
			'currency'            => "default:{$currency}",
			'duration'            => 'numeric|default:1',
			'duration_unit'       => 'in:day,week,month,year',
			'initial_amount'      => 'numeric',
			'auto_renew'          => 'boolean|default:1',
			'status'              => "in:{$membership_status}|default:pending",
			'gateway_customer_id' => 'default:',
			'upgraded_from'       => 'default:',
			'amount'              => 'numeric|default:0',
			'billing_cycles'      => 'numeric|default:0',
			'times_billed'        => 'integer|default:0',
			'gateway'             => 'default:',
			'signup_method'       => 'default:',
			'disabled'            => 'default:0',
			'recurring'           => 'default:0',
			'network_id'          => 'integer|nullable',
		];
	}

	/**
	 * Gets the customer object associated with this membership.
	 *
	 * @todo Implement this.
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Customer;
	 */
	public function get_customer() {

		return wu_get_customer($this->get_customer_id());
	}

	/**
	 * Get the value of customer_id.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_customer_id() {

		return absint($this->customer_id);
	}

	/**
	 * Set the value of customer_id.
	 *
	 * @since 2.0.0
	 * @param int $customer_id The ID of the customer attached to this membership.
	 * @return void
	 */
	public function set_customer_id($customer_id): void {

		$this->customer_id = absint($customer_id);
	}

	/**
	 * Checks if a given customer should have access to this site options.
	 *
	 * @since 2.0.0
	 *
	 * @param int $customer_id The customer id to check.
	 * @return boolean
	 */
	public function is_customer_allowed($customer_id = false) {

		if (current_user_can('manage_network')) {
			return true;
		}

		if ( ! $customer_id) {
			$customer = WP_Ultimo()->currents->get_customer();

			$customer_id = $customer ? $customer->get_id() : 0;
		}

		$allowed = absint($customer_id) === absint($this->get_customer_id());

		return apply_filters('wu_membership_is_customer_allowed', $allowed, $customer_id, $this);
	}

	/**
	 * Get the value of user_id.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_user_id() {

		return $this->user_id;
	}

	/**
	 * Set the value of user_id.
	 *
	 * @since 2.0.0
	 * @param int $user_id The user ID attached to this membership.
	 * @return void
	 */
	public function set_user_id($user_id): void {

		$this->user_id = absint($user_id);
	}

	/**
	 * Get the value of plan_id.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_plan_id() {

		return (int) $this->plan_id;
	}

	/**
	 * Returns the plan that created this membership.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Product|false
	 */
	public function get_plan() {

		$plan = wu_get_product($this->get_plan_id());

		// Get the correct variation if exists
		if ($plan && ($plan->get_duration() !== $this->get_duration() || $plan->get_duration_unit() !== $this->get_duration_unit())) {
			$variation = $plan->get_as_variation($this->get_duration(), $this->get_duration_unit());

			$plan = ($variation ?: null) ?? $plan;
		}

		return $plan;
	}

	/**
	 * Set plan associated with the membership.
	 *
	 * @since 2.0.0
	 * @param int $plan_id The plan ID associated with the membership.
	 * @return void
	 */
	public function set_plan_id($plan_id): void {

		$this->plan_id = absint($plan_id);
	}

	/**
	 * Checks if this membership has a plan.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_plan() {

		return ! empty($this->get_plan());
	}

	/**
	 * Get additional product objects.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Product[] A list of the addon projects.
	 */
	public function get_addons(): array {

		$addons = array_map('wu_get_product', $this->get_addon_ids());

		return array_filter($addons);
	}

	/**
	 * Checks if the given membership has addon products.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_addons() {

		return ! empty($this->get_addons());
	}

	/**
	 * Gets a list of product ids for addons.
	 *
	 * @since 2.0.0
	 */
	public function get_addon_ids(): array {

		return array_map('absint', array_keys((array) $this->addon_products));
	}

	/**
	 * Adds an an addon product from this membership.
	 *
	 * @since 2.0.0
	 *
	 * @param int     $product_id The product id.
	 * @param integer $quantity The quantity.
	 * @return void
	 */
	public function add_product($product_id, $quantity = 1): void {

		$has_product = wu_get_isset($this->addon_products, $product_id);

		if ($has_product && $this->addon_products[ $product_id ] >= 0) {
			$this->addon_products[ $product_id ] += $quantity;
		} else {
			$this->addon_products[ $product_id ] = $quantity;
		}

		if ($this->addon_products[ $product_id ] <= 0) {
			unset($this->addon_products[ $product_id ]);
		}
	}

	/**
	 * Removes a product from the membership.
	 *
	 * @since 2.0.0
	 *
	 * @param integer $product_id The product id.
	 * @param integer $quantity The quantity to remove.
	 * @return void
	 */
	public function remove_product($product_id, $quantity = 1): void {

		$has_product = wu_get_isset($this->addon_products, $product_id);

		if ($has_product && $this->addon_products[ $product_id ] >= 0) {
			$this->addon_products[ $product_id ] -= $quantity;
		}

		if ($this->addon_products[ $product_id ] <= 0) {
			unset($this->addon_products[ $product_id ]);
		}
	}

	/**
	 * Get additional products. Services and Packages.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_addon_products() {

		$products = [];

		$this->addon_products = is_array($this->addon_products) ? $this->addon_products : [];

		foreach ($this->addon_products as $product_id => $quantity) {
			$product = wu_get_product($product_id);

			if ( ! $product) {
				continue;
			}

			$products[] = [
				'quantity' => $quantity,
				'product'  => $product,
			];
		}

		return $products;
	}

	/**
	 * Returns a list with all products, including the plan.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_all_products() {
		$product  = $this->get_plan();
		$products = [];
		if ($product) {
			$products[] = [
				'quantity' => 1,
				'product'  => $this->get_plan(),
			];
		}

		return array_merge($products, $this->get_addon_products());
	}

	/**
	 * Set additional products. Services and Packages.
	 *
	 * @since 2.0.0
	 * @param mixed $addon_products Additional products related to this membership. Services, Packages or other types of products.
	 * @return void
	 */
	public function set_addon_products($addon_products): void {

		$this->addon_products = maybe_unserialize($addon_products);
	}

	/**
	 * Changes the membership products and totals.
	 *
	 * This is used when a upgrade, downgrade or addon
	 * checkout is processed.
	 *
	 * It takes a Cart object and uses that to construct
	 * the new membership parameters.
	 *
	 * Important: this method does not SAVE the changes
	 * you need to explicitly call save() after a swap
	 * to persist the changes.
	 *
	 * @since 2.0.0
	 *
	 * @param Cart $order The cart object.
	 * @return \WP_Ultimo\Models\Membership|\WP_Error
	 */
	public function swap($order) {

		if ( ! is_a($order, Cart::class)) {
			return new \WP_Error('invalid-date', __('Swap Cart is invalid.', 'ultimate-multisite'));
		}

		/*
		 * For addon-only carts, we merge new addons with existing ones.
		 * For plan changes (upgrade/downgrade), we replace all products.
		 *
		 * @since 2.0.12
		 */
		$is_addon_cart = 'addon' === $order->get_cart_type();

		if ( ! $is_addon_cart) {
			// Clear the current addons for plan changes.
			$this->addon_products = [];
		}

		/*
		 * We'll do that based on the line items,
		 * we do it that way because it is the only
		 * place where we have quantity info, as well
		 * as product ID.
		 */
		foreach ($order->get_line_items() as $line_item) {
			$product = $line_item->get_product();

			/**
			 * We only care about products.
			 */
			if (empty($product)) {
				continue;
			}

			/*
			 * Checks if this is a plan.
			 *
			 * If that's the case, we need to replace the current
			 * plan id.
			 */
			if ($product->get_type() === 'plan') {
				$this->set_plan_id($product->get_id());

				continue;
			}

			/*
			 * For other products,
			 * we add them as addons.
			 */
			$this->add_product($product->get_id(), $line_item->get_quantity());
		}

		/*
		 * For addon carts, don't update the recurring amount/duration
		 * since we're not changing the plan, just adding products.
		 *
		 * @since 2.0.12
		 */
		if ( ! $is_addon_cart) {
			$this->set_amount($order->get_recurring_total());
			$this->set_initial_amount($order->get_total());
			$this->set_recurring($order->has_recurring());

			$this->set_duration($order->get_duration());
			$this->set_duration_unit($order->get_duration_unit());
		}

		/*
		 * Returns self for chaining.
		 */
		return $this;
	}

	/**
	 * Schedule a swap for the membership.
	 *
	 * @since 2.0.0
	 *
	 * @param Cart           $order The cart representing the change.
	 * @param string|boolean $schedule_date The date to schedule the change for.
	 * @return int|\WP_Error
	 */
	public function schedule_swap($order, $schedule_date = false) {

		if (empty($schedule_date)) {
			$schedule_date = $this->get_date_expiration();
		}

		if ( ! wu_validate_date($schedule_date)) {
			return new \WP_Error('invalid-date', __('Schedule date is invalid.', 'ultimate-multisite'));
		}

		if ( ! is_a($order, Cart::class)) {
			return new \WP_Error('invalid-date', __('Swap Cart is invalid.', 'ultimate-multisite'));
		}

		$date_instance = wu_date($schedule_date);

		/*
		 * Saves the order.
		 */
		$this->update_meta('wu_swap_order', $order);
		$this->update_meta('wu_swap_scheduled_date', $schedule_date);

		/*
		 * Remove the previous swaps.
		 */
		wu_unschedule_action(
			'wu_async_membership_swap',
			[
				'membership_id' => $this->get_id(),
			],
			'membership'
		);

		/*
		 * Schedule the swap.
		 */
		return wu_schedule_single_action(
			$date_instance->format('U'),
			'wu_async_membership_swap',
			[
				'membership_id' => $this->get_id(),
			],
			'membership'
		);
	}

	/**
	 * Returns the scheduled swap, if any.
	 *
	 * @since 2.0.0
	 * @return object|false
	 */
	public function get_scheduled_swap() {

		$order          = $this->get_meta(self::META_SWAP_ORDER);
		$scheduled_date = $this->get_meta(self::META_SWAP_SCHEDULED_DATE);

		if ( ! $scheduled_date || ! $order) {
			$this->delete_meta(self::META_SWAP_ORDER);
			$this->delete_meta(self::META_SWAP_SCHEDULED_DATE);

			return false;
		}

		return (object) [
			'order'          => $order,
			'scheduled_date' => $scheduled_date,
		];
	}

	/**
	 * Removes a schedule swap.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function delete_scheduled_swap(): void {

		$this->delete_meta(self::META_SWAP_ORDER);

		$this->delete_meta(self::META_SWAP_SCHEDULED_DATE);

		do_action('wu_membership_delete_scheduled_swap', $this);
	}

	/**
	 * Returns the amount recurring in a human-friendly way.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_recurring_description() {

		$duration      = $this->get_duration();
		$duration_unit = wu_get_translatable_string($duration <= 1 ? $this->get_duration_unit() : $this->get_duration_unit() . 's');

		if ($duration <= 1) {
			return $duration_unit;
		}

		return sprintf(
			// translators: %1$s: the duration number, %2$s: the duration unit (days, weeks, months, etc).
			__('every %1$s %2$s', 'ultimate-multisite'),
			$duration,
			$duration_unit
		);
	}

	/**
	 * Returns the times billed in a human-friendly way.
	 *
	 * @since 2.0.0
	 */
	public function get_times_billed_description(): string {

		// translators: times billed / subscription duration in cycles. e.g. 1/12 cycles
		$description = __('%1$s / %2$s cycles', 'ultimate-multisite');

		if ($this->is_forever_recurring()) {

			// translators: the place holder is the number of times the membership was billed.
			$description = __('%1$s / until cancelled', 'ultimate-multisite');
		}

		return sprintf($description, $this->get_times_billed(), $this->get_billing_cycles());
	}

	/**
	 * Returns the membership price structure in a way human can understand it.
	 *
	 * @since 2.0.0
	 */
	public function get_price_description(): string {

		$pricing = [];

		if ($this->is_recurring()) {
			$duration = $this->get_duration();

			$formatted_price = wu_format_currency($this->get_amount(), $this->get_currency());
			$duration_unit   = wu_get_translatable_string($duration <= 1 ? $this->get_duration_unit() : $this->get_duration_unit() . 's');

			if ($duration <= 1) {
				$message = sprintf(
					// translators: %1$s: the formatted price, %2$s: the duration unit (day, week, month, etc).
					__('%1$s / %2$s', 'ultimate-multisite'),
					$formatted_price,
					$duration_unit
				);
			} else {
				$message = sprintf(
					// translators: %1$s: the formatted price, %2$s: the duration number, %3$s: the duration unit (days, weeks, months, etc).
					__('%1$s every %2$s %3$s', 'ultimate-multisite'),
					$formatted_price,
					$duration,
					$duration_unit
				);
			}

			$pricing['subscription'] = $message;

			if ( ! $this->is_forever_recurring()) {
				$billing_cycles_message = sprintf(
					// translators: %s is the number of billing cycles.
					_n('for %s cycle', 'for %s cycles', $this->get_billing_cycles(), 'ultimate-multisite'),
					$this->get_billing_cycles()
				);

				$pricing['subscription'] .= ' ' . $billing_cycles_message;
			}
		} else {
			$pricing['subscription'] = sprintf(
				// translators: %1$s is the formatted price of the product
				__('%1$s one time payment', 'ultimate-multisite'),
				wu_format_currency($this->get_initial_amount(), $this->get_currency())
			);
		}

		if ($this->is_free()) {
			$pricing['subscription'] = __('Free!', 'ultimate-multisite');
		}

		return implode(' + ', $pricing);
	}

	/**
	 * Get the value of currency.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_currency() {

		if (! empty($this->currency)) {
			return $this->currency;
		}

		return wu_get_setting('currency_symbol', 'USD');
	}

	/**
	 * Set the value of currency.
	 *
	 * @since 2.0.0
	 * @param string $currency The currency that this membership. It's a 3-letter code. E.g. 'USD'.
	 * @return void
	 */
	public function set_currency($currency): void {

		$this->currency = $currency;
	}

	/**
	 * Get time interval between charges.
	 *
	 * @return int
	 */
	public function get_duration() {

		return absint($this->duration);
	}

	/**
	 * Set time interval between charges.
	 *
	 * @param int $duration The interval period between a charge. Only the interval amount, the unit will be defined in another property.
	 */
	public function set_duration($duration): void {

		$this->duration = absint($duration);
	}

	/**
	 * Get time interval unit between charges.
	 *
	 * @return string
	 */
	public function get_duration_unit() {

		return $this->duration_unit;
	}

	/**
	 * Set time interval unit between charges.
	 *
	 * @param string $duration_unit The duration amount type. Can be 'day', 'week', 'month' or 'year'.
	 */
	public function set_duration_unit($duration_unit): void {

		$this->duration_unit = $duration_unit;
	}

	/**
	 * Get the product amount.
	 *
	 * @return int
	 */
	public function get_amount() {

		return $this->amount;
	}

	/**
	 * Get normalized amount. This is used to calculate MRR>
	 *
	 * @since 2.0.0
	 * @return float
	 */
	public function get_normalized_amount() {

		$amount = $this->get_amount();

		if ($this->is_recurring()) {
			return $amount;
		}

		$duration = $this->get_duration();

		$normalized_duration_unit = wu_convert_duration_unit_to_month($this->get_duration_unit());

		return $amount / $duration * $normalized_duration_unit;
	}

	/**
	 * Set the product amount.
	 *
	 * @param float $amount The product amount.
	 */
	public function set_amount($amount): void {

		$this->amount = wu_to_float($amount);
	}

	/**
	 * Get the product setup fee.
	 *
	 * @return int
	 */
	public function get_initial_amount() {

		return $this->initial_amount;
	}

	/**
	 * Set the product setup fee.
	 *
	 * @param float $initial_amount The initial amount charged for this membership, including the setup fee.
	 */
	public function set_initial_amount($initial_amount): void {

		$this->initial_amount = wu_to_float($initial_amount);
	}

	/**
	 * Get the value of date_created.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_created() {

		return $this->date_created;
	}

	/**
	 * Set the value of date_created.
	 *
	 * @since 2.0.0
	 * @param string $date_created Date of creation of this membership.
	 * @return void
	 */
	public function set_date_created($date_created): void {

		$this->date_created = $date_created;
	}

	/**
	 * Get the value of date_activated.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_activated() {

		return $this->date_activated;
	}

	/**
	 * Set the value of date_activated.
	 *
	 * @since 2.0.0
	 * @param string $date_activated Date when this membership was activated.
	 * @return void
	 */
	public function set_date_activated($date_activated): void {

		$this->date_activated = $date_activated;
	}

	/**
	 * Get the value of date_trial_end.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_trial_end() {

		return $this->date_trial_end;
	}

	/**
	 * Set the value of date_trial_end.
	 *
	 * @since 2.0.0
	 * @param string $date_trial_end Date when the trial period ends, if this membership has or had a trial period.
	 * @return void
	 */
	public function set_date_trial_end($date_trial_end): void {

		$this->date_trial_end = $date_trial_end;
	}

	/**
	 * Get the value of date_renewed.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_renewed() {

		return $this->date_renewed;
	}

	/**
	 * Set the value of date_renewed.
	 *
	 * @since 2.0.0
	 * @param string $date_renewed Date when the membership was renewed.
	 * @return void
	 */
	public function set_date_renewed($date_renewed): void {

		$this->date_renewed = $date_renewed;
	}

	/**
	 * Get the value of date_cancellation.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_cancellation() {

		return $this->date_cancellation;
	}

	/**
	 * Set the value of date_cancellation.
	 *
	 * @since 2.0.0
	 * @param string $date_cancellation Date when the membership was cancelled.
	 * @return void
	 */
	public function set_date_cancellation($date_cancellation): void {

		$this->date_cancellation = $date_cancellation;
	}

	/**
	 * Get the cancellation reason
	 *
	 * @since 2.1.2
	 * @return string
	 */
	public function get_cancellation_reason() {

		if ($this->get_status() !== Membership_Status::CANCELLED) {
			return '';
		}

		if (null === $this->cancellation_reason) {
			$this->cancellation_reason = $this->get_meta(self::META_CANCELLATION_REASON);
		}

		return (string) $this->cancellation_reason;
	}

	/**
	 * Set the reason to cancel the membership.
	 *
	 * @since 2.1.2
	 * @param string $reason The reason to cancel the membership.
	 * @return void
	 */
	public function set_cancellation_reason($reason): void {

		$this->meta[ self::META_CANCELLATION_REASON ] = $reason;
		$this->cancellation_reason                    = $reason;
	}

	/**
	 * Get the value of date_expiration.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_expiration() {

		return $this->date_expiration;
	}

	/**
	 * Set the value of date_expiration.
	 *
	 * @since 2.0.0
	 * @param string $date_expiration Date when the membership will expiry.
	 * @return void
	 */
	public function set_date_expiration($date_expiration): void {

		$this->date_expiration = $date_expiration;
	}

	/**
	 * Calculate a new expiration date.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $from_today Whether to calculate from today (`true`), or extend the existing expiration date (`false`).
	 * @param bool $trial      Whether or not this is for a free trial.
	 * @return String Date in Y-m-d H:i:s format or null if is a lifetime membership.
	 */
	public function calculate_expiration($from_today = false, $trial = false) {

		// Get the member's current expiration date
		$expiration           = $this->get_date_expiration();
		$expiration_timestamp = 0;

		if ( ! $this->is_recurring()) {
			return null;
		}

		if (wu_validate_date($expiration)) {
			$expiration_timestamp = wu_date($expiration)->format('U');
		}

		// Determine what date to use as the start for the new expiration calculation
		if (!$from_today && $expiration_timestamp > wu_get_current_time('timestamp', true)) { // phpcs:ignore

			$base_timestamp = $expiration_timestamp;
		} else {
			$base_timestamp = wu_get_current_time('timestamp', true); // phpcs:ignore
		}

		if ($this->get_duration() > 0) {
			$expire_timestamp = strtotime('+' . $this->get_duration() . ' ' . $this->get_duration_unit() . ' 23:59:59', $base_timestamp);

			$extension_days = ['29', '30', '31'];

			if (in_array(gmdate('j', $expire_timestamp), $extension_days, true) && 'month' === $this->get_duration_unit()) {
				$month = gmdate('n', $expire_timestamp);

				if ($month < 12) {
					++$month;

					$year = gmdate('Y', $expire_timestamp);
				} else {
					$month = 1;

					$year = gmdate('Y', $expire_timestamp) + 1;
				}

				$expire_timestamp = mktime(0, 0, 0, $month, 1, $year);
			}

			$expiration = gmdate('Y-m-d 23:59:59', $expire_timestamp);
		} else {
			$expiration = null; // tag as lifetime.

		}

		/**
		 * Filters the calculated expiration date.
		 *
		 * @param string         $expiration    Calculated expiration date in MySQL format.
		 * @param int            $membership_id ID of the membership.
		 * @param \WP_Ultimo\Models\Membership $membership Membership object.
		 *
		 * @since 2.0
		 */
		$expiration = apply_filters('wu_membership_calculated_date_expiration', $expiration, $this->get_id(), $this);

		return $expiration;
	}

	/**
	 * Get the value of date_payment_plan_completed.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_payment_plan_completed() {

		return $this->date_payment_plan_completed;
	}

	/**
	 * Set the value of date_payment_plan_completed.
	 *
	 * @since 2.0.0
	 * @param string $date_payment_plan_completed Change of the payment completion for the plan value.
	 * @return void
	 */
	public function set_date_payment_plan_completed($date_payment_plan_completed): void {

		$this->date_payment_plan_completed = $date_payment_plan_completed;
	}

	/**
	 * Get the value of auto_renew.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function should_auto_renew() {

		return (bool) $this->auto_renew;
	}

	/**
	 * Deprecated: get_auto_renew
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function get_auto_renew() {

		_deprecated_function(__METHOD__, '2.0.0', 'should_auto_renew()');

		return $this->should_auto_renew();
	}

	/**
	 * Set the value of auto_renew.
	 *
	 * @since 2.0.0
	 * @param bool $auto_renew If this membership should auto-renewal.
	 * @return void
	 */
	public function set_auto_renew($auto_renew): void {

		$this->auto_renew = (bool) $auto_renew;
	}

	/**
	 * Get the discount code applied if exist.
	 *
	 * @since 2.0.20
	 * @return \WP_Ultimo\Models\Discount_Code|false
	 */
	public function get_discount_code() {

		if (null === $this->discount_code) {
			$this->discount_code = $this->get_meta(self::META_DISCOUNT_CODE);
		}

		// Get discount code from original payment for compatibility
		if (empty($this->discount_code) && ! $this->get_meta(self::META_VERIFIED_PAYMENT_DISCOUNT)) {
			$original_payment = wu_get_payments(
				[
					'number'        => 1,
					'membership_id' => $this->get_id(),
					'orderby'       => 'id',
					'order'         => 'ASC',
				]
			);

			if (isset($original_payment[0])) {
				$original_cart = $original_payment[0]->get_meta(Payment::META_ORIGINAL_CART);

				$this->discount_code = $original_cart ? $original_cart->get_discount_code() : false;

				if ($this->discount_code) {
					$this->update_meta(self::META_DISCOUNT_CODE, $this->discount_code);
				}
			}

			$this->update_meta(self::META_VERIFIED_PAYMENT_DISCOUNT, true);
		}

		return $this->discount_code;
	}

	/**
	 * Set the value of discount_code.
	 *
	 * @since 2.0.20
	 * @param string|\WP_Ultimo\Models\Discount_Code $discount_code Discount code object.
	 * @return void
	 */
	public function set_discount_code($discount_code): void {

		if (is_a($discount_code, '\WP_Ultimo\Models\Discount_Code')) {
			$this->meta[ self::META_DISCOUNT_CODE ] = $discount_code;
			$this->discount_code                    = $discount_code;

			return;
		}

		$discount_code = wu_get_discount_code_by_code($discount_code);

		if ( ! $discount_code) {
			return;
		}

		$this->meta[ self::META_DISCOUNT_CODE ] = $discount_code;
		$this->discount_code                    = $discount_code;
	}

	/**
	 * Get the value of times_billed.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_times_billed() {

		return (int) $this->times_billed;
	}

	/**
	 * Set the value of times_billed.
	 *
	 * @since 2.0.0
	 * @param int $times_billed Amount of times this membership got billed.
	 * @return void
	 */
	public function set_times_billed($times_billed): void {

		$this->times_billed = $times_billed;
	}

	/**
	 * Increments times billed.
	 *
	 * @since 2.0.0
	 *
	 * @param integer $number Amount to increment by.
	 * @return \WP_Ultimo\Models\Membership
	 */
	public function add_to_times_billed($number = 1) {

		$times_billed = absint($this->get_times_billed());

		$this->set_times_billed(($times_billed + $number));

		return $this;
	}

	/**
	 * Get the value of billing_cycles.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_billing_cycles() {

		return $this->billing_cycles;
	}

	/**
	 * Checks if this product recurs forever.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_forever_recurring() {

		return empty($this->get_billing_cycles());
	}

	/**
	 * Checks if we are on the max renewals.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function at_maximum_renewals() {

		if ( ! $this->is_forever_recurring()) {
			return false;
		}

		$times_billed = $this->get_times_billed() - 1; // Subtract 1 to exclude initial payment.
		$renew_times  = $this->get_billing_cycles();

		return $times_billed >= $renew_times;
	}

	/**
	 * Set the value of billing_cycles.
	 *
	 * @since 2.0.0
	 * @param int $billing_cycles Maximum times we should charge this membership.
	 * @return void
	 */
	public function set_billing_cycles($billing_cycles): void {

		$this->billing_cycles = $billing_cycles;
	}

	/**
	 * Returns the default billing address.
	 *
	 * Classes that implement this trait need to implement
	 * this method.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Objects\Billing_Address
	 */
	public function get_default_billing_address() {

		$billing_address = new \WP_Ultimo\Objects\Billing_Address();

		$customer = $this->get_customer();

		if ($customer) {
			return $customer->get_billing_address();
		}

		return $billing_address;
	}

	/**
	 * Checks if the current membership has a active status.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_active() {

		$active_statuses = [
			Membership_Status::ACTIVE,
			Membership_Status::ON_HOLD,
		];

		$active = in_array($this->status, $active_statuses, true);

		return apply_filters('wu_membership_is_active', $active, $this);
	}

	/**
	 * Get the value of status.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_status() {

		return $this->status;
	}

	/**
	 * Set the value of status.
	 *
	 * @since 2.0.0
	 * @param string $status The membership status. Can be 'pending', 'active', 'on-hold', 'expired', 'cancelled' or other values added by third-party add-ons.
	 * @options \WP_Ultimo\Database\Payments\Payment_Status
	 * @return void
	 */
	public function set_status($status): void {

		$this->status = $status;
	}

	/**
	 * Returns the Label for a given severity level.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_status_label() {

		$status = new Membership_Status($this->get_status());

		return $status->get_label();
	}

	/**
	 * Gets the classes for a given class.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_status_class() {

		$status = new Membership_Status($this->get_status());

		return $status->get_classes();
	}

	/**
	 * Get the value of gateway_customer_id.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_gateway_customer_id() {

		return $this->gateway_customer_id;
	}

	/**
	 * Set the value of gateway_customer_id.
	 *
	 * @since 2.0.0
	 * @param string $gateway_customer_id The ID of the customer on the payment gateway database.
	 * @return void
	 */
	public function set_gateway_customer_id($gateway_customer_id): void {

		$this->gateway_customer_id = $gateway_customer_id;
	}

	/**
	 * Get the value of gateway_subscription_id.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_gateway_subscription_id() {

		return $this->gateway_subscription_id;
	}

	/**
	 * Set the value of gateway_subscription_id.
	 *
	 * @since 2.0.0
	 * @param string $gateway_subscription_id The ID of the subscription on the payment gateway database.
	 * @return void
	 */
	public function set_gateway_subscription_id($gateway_subscription_id): void {

		$this->gateway_subscription_id = $gateway_subscription_id;
	}

	/**
	 * Get the value of gateway.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_gateway() {

		return $this->gateway;
	}

	/**
	 * Set the value of gateway.
	 *
	 * @since 2.0.0
	 * @param string $gateway ID of the gateway being used on this subscription.
	 * @return void
	 */
	public function set_gateway($gateway): void {

		$this->gateway = $gateway;
	}

	/**
	 * Get the value of signup_method.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_signup_method() {

		return $this->signup_method;
	}

	/**
	 * Set the value of signup_method.
	 *
	 * @since 2.0.0
	 * @param string $signup_method Signup method used to create this membership.
	 * @return void
	 */
	public function set_signup_method($signup_method): void {

		$this->signup_method = $signup_method;
	}

	/**
	 * Get the value of upgraded_from.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_upgraded_from() {

		return $this->upgraded_from;
	}

	/**
	 * Set the value of upgraded_from.
	 *
	 * @since 2.0.0
	 * @param int $upgraded_from Plan that this membership upgraded from.
	 * @return void
	 */
	public function set_upgraded_from($upgraded_from): void {

		$this->upgraded_from = $upgraded_from;
	}

	/**
	 * Get the value of date_modified.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_modified() {

		return $this->date_modified;
	}

	/**
	 * Set the value of date_modified.
	 *
	 * @since 2.0.0
	 * @param string $date_modified Date this membership was last modified.
	 * @return void
	 */
	public function set_date_modified($date_modified): void {

		$this->date_modified = $date_modified;
	}

	/**
	 * Get the value of disabled.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_disabled() {

		return (bool) $this->disabled;
	}

	/**
	 * Set the value of disabled.
	 *
	 * @since 2.0.0
	 * @param bool $disabled If this membership is a disabled one.
	 * @return void
	 */
	public function set_disabled($disabled): void {

		$this->disabled = (bool) $disabled;
	}

	/**
	 * Returns a list of payments associated with this membership.
	 *
	 * @since 2.0.0
	 * @param array $query Query arguments.
	 * @return array
	 */
	public function get_payments($query = []) {

		$query = array_merge(
			$query,
			[
				'membership_id' => $this->get_id(),
			]
		);

		return wu_get_payments($query);
	}

	/**
	 * Returns the last pending payment for a membership.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Payment|false
	 */
	public function get_last_pending_payment() {

		$payments = wu_get_payments(
			[
				'membership_id'      => $this->get_id(),
				'status'             => 'pending',
				'number'             => 1,
				'orderby'            => 'id',
				'order'              => 'DESC',
				'gateway_payment_id' => '',
			]
		);

		return ! empty($payments) ? array_pop($payments) : false;
	}

	/**
	 * Returns the published sites attached to this membership.
	 *
	 * @since 2.0.18
	 * @return array
	 */
	public function get_published_sites() {

		$sites = Site::query(
			[
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'customer_id' => [
						'key'   => 'wu_membership_id',
						'value' => $this->get_id(),
					],
				],
			]
		);

		return $sites;
	}

	/**
	 * Returns the sites attached to this membership.
	 *
	 * @since 2.0.0
	 * @param bool $include_pending If we should include the pending site if exist.
	 * @return array
	 */
	public function get_sites($include_pending = true) {

		$sites = Site::query(
			[
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'customer_id' => [
						'key'   => 'wu_membership_id',
						'value' => $this->get_id(),
					],
				],
			]
		);

		$pending_site = $include_pending ? $this->get_pending_site() : false;

		if ($pending_site) {
			/*
			 * Skip the pending site if a real site already exists for this
			 * membership. This avoids a race condition where the async
			 * publish job has created the real blog but hasn't yet deleted
			 * the pending_site metadata, causing duplicates on the Thank
			 * You page.
			 */
			if (empty($sites)) {
				$pending_site->set_type('pending');

				$sites[] = $pending_site;
			}
		}

		return $sites;
	}

	/**
	 * Adds a pending site to the membership meta data.
	 *
	 * @since 2.0.0
	 *
	 * @param array $site_info Site info.
	 * @return Site
	 */
	public function create_pending_site($site_info): Site {

		global $current_site;

		$site_info = wp_parse_args(
			$site_info,
			[
				'title'         => '',
				'domain'        => $current_site->domain,
				'path'          => '',
				'transient'     => [],
				'is_publishing' => false,
			]
		);

		$site = new Site($site_info);

		$this->update_meta('pending_site', $site);
		return $site;
	}

	/**
	 * Updates a pending site to the membership meta-data.
	 *
	 * @param Site $site Site info.
	 * @return bool
	 */
	public function update_pending_site($site) {

		return $this->update_meta(self::META_PENDING_SITE, $site);
	}

	/**
	 * Returns the pending site, if any.
	 *
	 * @return Site|false
	 * @since 2.0.0
	 */
	public function get_pending_site() {

		return $this->get_meta(self::META_PENDING_SITE);
	}

	/**
	 * Published a pending site, but via job queue.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function publish_pending_site_async(): void {
		/*
		 * If the force sync setting is on, fallback to the sync version.
		 */
		if ((bool) wu_get_setting('force_publish_sites_sync', false)) {
			$this->publish_pending_site();

			return;
		}

		// We first try to generate the site through request to start earlier as possible.
		$rest_path = add_query_arg(
			[
				'action'        => 'wu_publish_pending_site',
				'_ajax_nonce'   => wp_create_nonce('wu_publish_pending_site'),
				'membership_id' => $this->get_id(),
			],
			admin_url('admin-ajax.php')
		);
		$headers   = array(
			'Cache-Control' => 'no-cache',
		);

		// Include Basic auth in loopback requests.
		if ( isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode(sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_USER'])) . ':' . sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_PW']))); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		$request_args = [
			'cookies'   => wp_unslash($_COOKIE), // Cookies are needed for nonce check to work.
			'timeout'   => 10,
			/** This filter is documented in wp-includes/class-wp-http-streams.php */
			'sslverify' => apply_filters('https_local_ssl_verify', false),
			'headers'   => $headers,
		];

		if ( ! function_exists('fastcgi_finish_request')) {
			// We do not have fastcgi but can make the request continue without listening with blocking = false.
			$request_args['blocking'] = false;
		}
		$result = wp_remote_request(
			$rest_path,
			$request_args
		);

		if (is_wp_error($result)) {
			// translators: %s full error message.
			wu_log_add("membership-{$this->get_id()}", sprintf(__('Failed to trigger async site creation. The site will not be created until the next cron run which is much slower: %s', 'ultimate-multisite'), $result->get_error_message()));
		}

		wu_enqueue_async_action('wu_async_publish_pending_site', ['membership_id' => $this->get_id()], 'membership');
	}

	/**
	 * Publishes a pending site.
	 *
	 * @since 2.0.0
	 * @return true|\WP_Error
	 */
	public function publish_pending_site() {
		/*
		 * Trigger event before the publication of a site.
		 */
		do_action('wu_before_pending_site_published', $this);

		$pending_site = $this->get_pending_site();

		if ( ! $pending_site) {
			return true;
		}

		$is_publishing = $pending_site->is_publishing();

		if ($is_publishing) {
			return true;
		}

		$pending_site->set_publishing(true);

		$this->update_pending_site($pending_site);

		$pending_site->set_type('customer_owned');

		/*
		 * Ensure the template ID is set when the product uses
		 * "Assign Site Template" mode. During checkout the
		 * wu_checkout_template_id filter sets this on the pending
		 * site data, but if the checkout form has no template
		 * selection field the value may still be empty. Re-apply
		 * the filter here as a safety net so the assigned template
		 * is always honoured.
		 *
		 * @since 2.5.0
		 */
		$template_id = apply_filters('wu_checkout_template_id', (int) $pending_site->get_template_id(), $this, null);

		if ($template_id && $template_id !== (int) $pending_site->get_template_id()) {
			$pending_site->set_template_id($template_id);
		}

		try {
			$saved = $pending_site->save();
		} catch (\Throwable $e) {
			/*
			 * Reset is_publishing when Site::save() or a downstream hook throws,
			 * so the cron/manual retry path is not stuck in "Creating" state.
			 *
			 * @since 2.4.13
			 */
			$pending_site->set_publishing(false);

			$this->update_pending_site($pending_site);

			return new \WP_Error('pending_site_publish_failed', $e->getMessage());
		}

		if (is_wp_error($saved)) {
			if (in_array($saved->get_error_code(), ['site_taken', 'blog_taken'], true)) {
				/*
				 * If the site is already taken, we just delete the pending site.
				 * This is a workaround for cases where the publish is called twice
				 * even with is_publishing value as true.
				 */
				$this->delete_pending_site();

				return true;
			}

			/*
			 * Reset is_publishing on failure so the retry mechanism
			 * (cron / manual) can try again instead of staying stuck
			 * in a "Creating" state forever.
			 *
			 * @since 2.4.13-beta.12
			 */
			$pending_site->set_publishing(false);

			$this->update_pending_site($pending_site);

			return $saved;
		}

		$this->delete_pending_site();

		/*
		 * Trigger event that marks the publication of a site.
		 */
		do_action('wu_pending_site_published', $pending_site, $this);

		return true;
	}

	/**
	 * Removes a pending site of a membership.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function delete_pending_site() {

		return $this->delete_meta('pending_site');
	}

	/**
	 * Get is this product recurring?
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_recurring() {

		return (bool) $this->recurring && (float) $this->get_amount() > 0;
	}

	/**
	 * Checks if this is a lifetime membership.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_lifetime() {

		return ! $this->is_recurring() && (empty($this->get_date_expiration()) || $this->get_date_expiration() === '0000-00-00 00:00:00');
	}

	/**
	 * Checks if this plan is free or not.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_free() {

		return $this->is_recurring() === false && empty($this->get_initial_amount());
	}

	/**
	 * Set is this product recurring?
	 *
	 * @since 2.0.0
	 * @param boolean $recurring If this membership is recurring (true), which means the customer paid a defined amount each period of time, or not recurring (false).
	 * @return void
	 */
	public function set_recurring($recurring): void {

		$this->recurring = (bool) $recurring;
	}

	/**
	 * Gets the total grossed by the membership so far.
	 *
	 * @since 2.0.0
	 * @return float
	 */
	public function get_total_grossed() {

		global $wpdb;

		static $sum;

		if (null === $sum) {
			$sum = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT SUM(total) FROM {$wpdb->base_prefix}wu_payments WHERE parent_id = 0 AND membership_id = %d",
					$this->get_id()
				)
			);
		}

		return $sum;
	}

	/**
	 * Serialize model to array, ensuring meta-stored fields are loaded.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function to_array() {

		if ($this->get_id()) {
			$this->get_cancellation_reason();
			$this->get_discount_code();
		}

		return parent::to_array();
	}

	/**
	 * By default, we just use the to_array method, but you can rewrite this.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function to_search_results() {

		$search_result = $this->to_array();

		$search_result['customer'] = null;

		$search_result['display_name'] = '';

		if ($this->get_customer()) {
			$search_result['customer'] = $this->get_customer()->to_search_results();

			$search_result['display_name'] = $search_result['customer']['display_name'];
		}

		$search_result['formatted_price'] = $this->get_price_description();

		$search_result['reference_code'] = $this->get_hash();

		return $search_result;
	}

	/**
	 * Renews the membership by updating status and expiration date.
	 *
	 * Does NOT handle payment processing for the renewal. This should be called after receiving a renewal payment.
	 *
	 * @since  2.0.0
	 *
	 * @param bool   $auto_renew  Whether or not the membership is recurring.
	 * @param string $status     Membership status.
	 * @param string $expiration Membership expiration date in MySQL format.
	 * @return bool Whether or not the renewal was successful.
	 */
	public function renew($auto_renew = false, $status = 'active', $expiration = ''): bool {

		$id = $this->get_id();

		$plan_id = $this->get_plan_id();

		wu_log_add("membership-{$id}", sprintf('Starting membership renewal for membership #%d. Membership Level ID: %d; Current Expiration Date: %s', $id, $plan_id, $this->get_date_expiration()));

		if (empty($plan_id)) {
			return false;
		}

		// Bail if this has a payment plan and it's completed - prevents renewals from running after the fact.
		if ( ! $this->is_forever_recurring() && $this->at_maximum_renewals()) {
			return false;
		}

		$plan = wu_get_product($plan_id);

		if ( ! $expiration) {
			$expiration = $this->calculate_expiration(! $this->is_recurring());

			/**
			 * Filters the calculated expiration date to be set after the renewal.
			 *
			 * @param string     $expiration       Calculated expiration date.
			 * @param Product    $plan Membership level object.
			 * @param int        $membership_id    The ID of the membership.
			 * @param Membership $membership       Membership object.
			 *
			 * @since 2.0.0
			 */
			$expiration = apply_filters('wu_membership_renewal_expiration_date', $expiration, $plan, $this->get_id(), $this);
		}

		/**
		 * Triggers before the membership renewal.
		 *
		 * @param string     $expiration    New expiration date to be set.
		 * @param int        $membership_id The ID of the membership.
		 * @param Membership $membership    Membership object.
		 *
		 * @since 2.0
		 */
		do_action('wu_membership_pre_renew', $expiration, $this->get_id(), $this);

		$this->set_date_expiration($expiration);

		if ( ! empty($status)) {
			$this->set_status($status);
		}

		$this->set_auto_renew($auto_renew);

		$this->set_date_renewed(wu_get_current_time('mysql')); // Current time.

		if ($this->get_user_id()) {
			delete_user_meta($this->get_user_id(), 'wu_expired_email_sent');
		}

		$status = $this->save();

		if (is_wp_error($status)) {
			return $status;
		}

		/**
		 * Triggers after the membership renewal.
		 *
		 * @param string     $expiration    New expiration date to be set.
		 * @param int        $membership_id The ID of the membership.
		 * @param Membership $membership          Membership object.
		 *
		 * @since 2.0
		 */
		do_action('wu_membership_post_renew', $expiration, $this->get_id(), $this);

		wu_log_add("membership-{$id}", sprintf('Completed membership renewal for membership #%d. Membership Level ID: %d; New Expiration Date: %s; New Status: %s', $id, $plan_id, $expiration, $this->get_status()));

		return true;
	}

	/**
	 * Changes the membership status to "cancelled".
	 *
	 * Does NOT handle actual cancellation of subscription payments, that is done in rcp_process_member_cancellation().
	 * This should be called after a member is successfully cancelled.
	 *
	 * @since  2.0.0
	 * @param string $reason Reason for cancellation.
	 * @return void
	 */
	public function cancel($reason = ''): void {

		if ($this->get_status() === Membership_Status::CANCELLED) {
			return; // Already cancelled

		}

		/**
		 * Triggers before the membership is cancelled.
		 *
		 * @param int            $membership_id The ID of the membership.
		 * @param \WP_Ultimo\Models\Membership $membership Membership object.
		 *
		 * @since 2.0
		 */
		do_action('wu_membership_pre_cancel', $this->get_id(), $this);

		// Change status.
		$this->set_status(Membership_Status::CANCELLED);

		$this->set_date_cancellation(wu_get_current_time('mysql'));

		if ( ! empty($reason)) {
			$this->set_cancellation_reason($reason);
		}

		$this->save();

		/**
		 * Triggers after the membership is cancelled.
		 *
		 * This triggers the cancellation email.
		 *
		 * @param int            $membership_id The ID of the membership.
		 * @param \WP_Ultimo\Models\Membership $membership Membership object.
		 *
		 * @since 2.0
		 */
		do_action('wu_membership_post_cancel', $this->get_id(), $this);
	}

	/**
	 * Returns the number of days still left in the cycle.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_remaining_days_in_cycle() {
		/*
		 * If this is a lifetime membership, we have unlimited days. Large number.
		 */
		if ( ! $this->is_recurring()) {
			return 10000;
		}

		/*
		 * We need to have a valid expiration date.
		 */
		if (empty($this->get_date_expiration()) || ! wu_validate_date($this->get_date_expiration())) {
			return 0;
		}

		/*
		 * Otherwise, we need to check based on the
		 * expiration date.
		 */
		$expiration_date = wu_date($this->get_date_expiration());
		$today           = wu_date();

		/*
		 * If today is larger than the expiration date,
		 * it means that the customer used all the membership time.
		 * There's nothing to pro-rate in that case.
		 */
		if ($today > $expiration_date) {
			return 0;
		}

		return floor($today->diff($expiration_date)->days);
	}

	/**
	 * List of limitations that need to be merged.
	 *
	 * Every model that is limitable (imports this trait)
	 * needs to declare explicitly the limitations that need to be
	 * merged. This allows us to chain the merges, and gives us
	 * a final list of limitations at the end of the process.
	 *
	 * In the case of membership, we need to mash up
	 * all the limitations associated with the membership
	 * plan and additional packages.
	 *
	 * @see \WP_Ultimo\Models\Traits\Trait_Limitable
	 * @since 2.0.0
	 * @return array
	 */
	public function limitations_to_merge() {

		$limitations_to_merge = [];

		$product_ids = [$this->get_plan_id()];

		$product_ids = array_merge($this->get_addon_ids(), $product_ids);

		foreach ($product_ids as $product_id) {
			$amount = 1;

			if (is_array($this->addon_products) && isset($this->addon_products[ $product_id ])) {
				$amount = $this->addon_products[ $product_id ];
			}

			$limitation = \WP_Ultimo\Objects\Limitations::early_get_limitations('product', $product_id);

			for ($i = 0; $i < $amount; $i++) {
				$limitations_to_merge[] = $limitation;
			}
		}

		return $limitations_to_merge;
	}

	/**
	 * Checks if the membership has product changes.
	 *
	 * @since 2.0.10
	 * @return boolean
	 */
	protected function has_product_changes() {

		if (empty($this->compiled_product_list)) {
			return false;
		}

		$old_products = $this->compiled_product_list;
		$new_products = $this->get_all_products();

		if (count($old_products) !== count($new_products)) {
			return true;
		}

		$compiled_old = array_map(fn($product) => $product['product']->get_id() . '|' . $product['quantity'], $old_products);

		$compiled_new = array_map(fn($product) => $product['product']->get_id() . '|' . $product['quantity'], $new_products);

		return array_diff($compiled_old, $compiled_new) || array_diff($compiled_new, $compiled_old);
	}

	/**
	 * Checks if the membership has gateway changes.
	 *
	 * @since 2.0.15
	 * @return boolean
	 */
	protected function has_gateway_changes() {

		$has_change = false;

		// Only compare gateway and gateway_subscription_id — these identify
		// whether an old external subscription needs to be cancelled.
		// gateway_customer_id is excluded because it may be populated for the
		// first time on return from PayPal (empty → payer_id) and should NOT
		// trigger cancellation of the subscription we just confirmed.
		$keys_to_compare = ['gateway', 'gateway_subscription_id'];

		foreach ($keys_to_compare as $key) {
			$current_value  = $key === 'gateway' ? $this->get_gateway() : $this->get_gateway_subscription_id();
			$snapshot_value = $this->gateway_info[ $key ] ?? null;

			if ($current_value !== $snapshot_value) {
				$has_change = true;

				break;
			}
		}

		return $has_change;
	}

	/**
	 * Get the number of remaining sites available to this membership.
	 *
	 * This means sites that can be still added.
	 *
	 * @since 2.0.11
	 * @return int
	 */
	public function get_remaining_sites() {

		$limit = $this->get_limitations()->sites->get_limit();

		if ( ! $this->get_limitations()->sites->is_enabled()) {
			return PHP_INT_MAX;
		}

		$limit = '' === $limit ? 1 : $limit;

		return $limit - count($this->get_sites());
	}

	/**
	 * Checks if the current membership has remaining sites available.
	 *
	 * @since 2.0.11
	 * @return boolean
	 */
	public function has_remaining_sites() {

		return $this->get_remaining_sites() >= 1;
	}

	/**
	 * Checks if the current membership is current trialing.
	 *
	 * @since 2.0.18
	 * @return boolean
	 */
	public function is_trialing() {

		return $this->get_date_trial_end() > gmdate('Y-m-d 23:59:59');
	}

	/**
	 * Save (create or update) the model on the database.
	 *
	 * @since 2.0.0
	 */
	public function save() {

		// Set trial status if needed
		if ($this->get_status() === Membership_Status::PENDING && $this->is_trialing() && ! $this->get_last_pending_payment()) {
			if ($this->get_customer()->get_email_verification() !== 'pending') {
				$this->set_status(Membership_Status::TRIALING);
			}
		}

		if ($this->has_product_changes()) {
			wu_enqueue_async_action(
				'wu_async_after_membership_update_products',
				[
					'membership_id' => $this->get_id(),
				],
				'membership'
			);
		}

		// If membership is cancelled, we need to cancel the subscription on the gateway.
		if ($this->get_status() === Membership_Status::CANCELLED) {
			/**
			 * Here we just need to change the used gateway,
			 * this will trigger the cancellation process if needed.
			 */
			$this->set_gateway('');
			$this->set_gateway_customer_id('');
			$this->set_gateway_subscription_id('');
			$this->set_auto_renew(false);
		}

		if ($this->has_gateway_changes() && $this->gateway_info['gateway']) {
			/**
			 * Lets deal with gateway change processing
			 * the cancelation of the original one
			 */

			$gateway = wu_get_gateway($this->gateway_info['gateway']);

			if ($gateway) {
				$membership_old = clone $this;
				$membership_old->set_gateway($this->gateway_info['gateway']);
				$membership_old->set_gateway_customer_id($this->gateway_info['gateway_customer_id']);
				$membership_old->set_gateway_subscription_id($this->gateway_info['gateway_subscription_id']);

				$gateway->process_cancellation($membership_old, $this->get_customer());
			}
		}

		// Run it for existing memberships without gateway changes only.
		if ($this->get_id() && ! $this->has_gateway_changes()) {
			$original = $this->_get_original();

			$has_amount_change = (float) $this->get_amount() !== (float) wu_get_isset($original, 'amount');

			$has_duration_change = $this->get_duration() !== absint(wu_get_isset($original, 'duration')) || $this->get_duration_unit() !== wu_get_isset($original, 'duration_unit');

			if ($this->has_product_changes() || $has_amount_change || $has_duration_change) {
				/**
				 * If the membership has product changes, amount changes or duration changes
				 * we need to reflect those changes on the gateway.
				 */
				$gateway = wu_get_gateway($this->get_gateway());

				if ($gateway) {
					$gateway_update = $gateway->process_membership_update($this, $this->get_customer());

					if (is_wp_error($gateway_update)) {
						return $gateway_update;
					}
				}
			}
		}

		return parent::save();
	}

	/**
	 * Delete the model from the database.
	 *
	 * Here we also need to cancel the subscription on the gateway.
	 *
	 * @since 2.1.2
	 * @return \WP_Error|bool
	 */
	public function delete() {

		$gateway = wu_get_gateway($this->get_gateway());

		if ($gateway) {
			$gateway->process_cancellation($this, $this->get_customer());
		}

		return parent::delete();
	}

	/**
	 * Get the network ID for multinetwork support.
	 *
	 * @since 2.3.0
	 * @return int|null
	 */
	public function get_network_id() {

		return $this->network_id ? absint($this->network_id) : null;
	}

	/**
	 * Set the network ID for multinetwork support.
	 *
	 * @since 2.3.0
	 * @param int|null $network_id Network ID.
	 * @return void
	 */
	public function set_network_id($network_id): void {

		$this->network_id = $network_id ? absint($network_id) : null;
	}
}
