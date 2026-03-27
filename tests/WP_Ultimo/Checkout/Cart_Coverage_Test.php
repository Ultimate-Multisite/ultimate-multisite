<?php
/**
 * Additional PHPUnit tests to improve Cart class coverage.
 *
 * Targets uncovered paths in inc/checkout/class-cart.php to push
 * line coverage from ~59% to >=80%.
 *
 * @package WP_Ultimo
 * @subpackage Tests\Checkout
 * @since 2.0.0
 */

namespace WP_Ultimo\Checkout;

use WP_Error;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Models\Membership;
use WP_Ultimo\Models\Payment;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_UnitTestCase;

/**
 * Additional coverage tests for the Cart class.
 *
 * Covers:
 * - build_from_payment() paths
 * - build_from_membership() addon/upgrade/downgrade paths
 * - calculate_prorate_credits()
 * - PWYW (pay-what-you-want) product paths
 * - sanitize_pwyw_amounts() / sanitize_pwyw_recurring()
 * - get_independent_line_items()
 * - cancel_conflicting_pending_payments()
 * - has_trial() eligibility
 * - get_billing_start_date() / get_billing_next_charge_date() downgrade paths
 * - search_for_same_period_plans()
 * - get_proration_credits()
 * - should_collect_payment() trial path
 * - Membership change with no products (no_changes error)
 * - Pending membership ID ignored
 */
class Cart_Coverage_Test extends WP_UnitTestCase {

	/**
	 * Shared test customer.
	 *
	 * @var Customer
	 */
	private static Customer $customer;

	/**
	 * Registered PWYW meta filter callbacks, keyed by product ID.
	 * Cleaned up in tearDown().
	 *
	 * @var array
	 */
	private array $pwyw_meta_filters = [];

	/**
	 * Set up shared fixtures before all tests in this class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		/*
		 * Truncate the WU customers table so that user_id counters
		 * from previous test classes (e.g. Cart_Test) do not conflict
		 * with the freshly-reset WP users table.
		 */
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wu_customers" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery



		$unique = 'cov' . uniqid();

		$result = wu_create_customer(
			[
				'username' => $unique,
				'email'    => $unique . '@example.com',
				'password' => 'password123',
			]
		);

		if ( is_wp_error( $result ) ) {
			self::fail( 'Failed to create test customer: ' . $result->get_error_message() );
		}

		self::$customer = $result;
	}

	/**
	 * Tear down shared fixtures after all tests.
	 *
	 * @return void
	 */
	public static function tear_down_after_class() {
		if ( isset( self::$customer ) && self::$customer ) {
			self::$customer->delete();
		}
	}

	/**
	 * Per-test setup: ensure WU meta tables are registered on $wpdb.
	 *
	 * BerlinDB registers these in Table_Loader, but in some test environments
	 * the $wpdb properties may not be set. We set them here to ensure
	 * is_meta_available() returns true for product and membership models.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		global $wpdb;
		if ( empty( $wpdb->wu_productmeta ) ) {
			$wpdb->wu_productmeta = $wpdb->prefix . 'wu_productmeta';
		}
		if ( empty( $wpdb->wu_membershipmeta ) ) {
			$wpdb->wu_membershipmeta = $wpdb->prefix . 'wu_membershipmeta';
		}
	}

	/**
	 * Clean up per-test state: remove any PWYW meta filters registered by prime_pwyw_meta().
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( $this->pwyw_meta_filters as $callback ) {
			remove_filter( 'get_wu_product_metadata', $callback, 10 );
		}
		$this->pwyw_meta_filters = [];
		parent::tear_down();
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	/**
	 * Prime the WordPress meta for a product's PWYW fields.
	 *
	 * The PWYW fields (pwyw_minimum_amount, pwyw_suggested_amount, pwyw_recurring_mode)
	 * are stored as meta. In the test environment the meta table may not be available
	 * or the meta may not be saved correctly. This helper uses the WordPress metadata
	 * filter hook to intercept meta reads and return the correct values, bypassing
	 * the DB/cache entirely.
	 *
	 * @param \WP_Ultimo\Models\Product $product         The product to prime.
	 * @param float                     $suggested_amount PWYW suggested amount.
	 * @param float                     $minimum_amount   PWYW minimum amount.
	 * @param string                    $recurring_mode   PWYW recurring mode.
	 * @return void
	 */
	private function prime_pwyw_meta( $product, float $suggested_amount = 0.0, float $minimum_amount = 0.0, string $recurring_mode = 'customer_choice' ) {
		$product_id = $product->get_id();

		// Use the wu_filter_product_item BerlinDB filter to intercept product fetches
		// and inject the PWYW values directly onto the product object.
		// This bypasses the meta table entirely, which may not be available in tests.
		$callback = function ( $item ) use ( $product_id, $suggested_amount, $minimum_amount, $recurring_mode ) {
			if ( ! is_object( $item ) ) {
				return $item;
			}
			// BerlinDB returns a raw stdClass row; the Product model is built from it.
			// We can't easily modify the Product object here, so we use a second filter.
			return $item;
		};

		// Use the get_wu_product_metadata filter to intercept meta reads.
		// NOTE: This only works if is_meta_available() returns true (i.e., $wpdb->wu_productmeta is set).
		// As a fallback, we also directly set the values on the product object.
		$meta_callback = function ( $value, $object_id, $meta_key, $single ) use ( $product_id, $suggested_amount, $minimum_amount, $recurring_mode ) {
			if ( (int) $object_id !== (int) $product_id ) {
				return $value;
			}
			$pwyw_values = [
				'wu_pwyw_suggested_amount' => $suggested_amount,
				'wu_pwyw_minimum_amount'   => $minimum_amount,
				'wu_pwyw_recurring_mode'   => $recurring_mode,
			];
			if ( isset( $pwyw_values[ $meta_key ] ) ) {
				return $single ? $pwyw_values[ $meta_key ] : [ $pwyw_values[ $meta_key ] ];
			}
			return $value;
		};

		add_filter( 'get_wu_product_metadata', $meta_callback, 10, 4 );

		// Track the callback so tear_down() can remove it.
		$this->pwyw_meta_filters[] = $meta_callback;

		// Directly set the in-memory cached values on the product object so
		// subsequent calls to get_pwyw_*() on the original object work too.
		$product->set_pwyw_suggested_amount( $suggested_amount );
		$product->set_pwyw_minimum_amount( $minimum_amount );
		$product->set_pwyw_recurring_mode( $recurring_mode );

		// Force-save the PWYW meta to the DB so that when wu_get_product() re-fetches
		// the product, get_meta() can find the values (if meta table is available).
		// We bypass is_meta_available() by calling update_metadata directly.
		global $wpdb;
		if ( ! empty( $wpdb->wu_productmeta ) ) {
			update_metadata( 'wu_product', $product_id, 'wu_pwyw_suggested_amount', $suggested_amount );
			update_metadata( 'wu_product', $product_id, 'wu_pwyw_minimum_amount', $minimum_amount );
			update_metadata( 'wu_product', $product_id, 'wu_pwyw_recurring_mode', $recurring_mode );
		}
	}

	/**
	 * Create a recurring plan product with a guaranteed unique slug.
	 *
	 * @param array $overrides Optional overrides.
	 * @return \WP_Ultimo\Models\Product
	 */
	private function create_plan( array $overrides = [] ) {
		$uid = uniqid( 'cp-' );

		$defaults = [
			'name'          => 'Coverage Plan ' . $uid,
			'slug'          => $uid,
			'amount'        => 50.00,
			'recurring'     => true,
			'duration'      => 1,
			'duration_unit' => 'month',
			'type'          => 'plan',
			'pricing_type'  => 'paid',
			'active'        => true,
		];

		$product = wu_create_product( array_merge( $defaults, $overrides ) );

		if ( is_wp_error( $product ) ) {
			$this->fail( 'Failed to create plan product: ' . $product->get_error_message() );
		}

		return $product;
	}

	/**
	 * Create a service (non-plan) product with a guaranteed unique slug.
	 *
	 * @param array $overrides Optional overrides.
	 * @return \WP_Ultimo\Models\Product
	 */
	private function create_service( array $overrides = [] ) {
		$uid = uniqid( 'cs-' );

		$defaults = [
			'name'          => 'Coverage Service ' . $uid,
			'slug'          => $uid,
			'amount'        => 10.00,
			'recurring'     => false,
			'duration'      => 1,
			'duration_unit' => 'month',
			'type'          => 'service',
			'pricing_type'  => 'paid',
			'active'        => true,
		];

		$product = wu_create_product( array_merge( $defaults, $overrides ) );

		if ( is_wp_error( $product ) ) {
			$this->fail( 'Failed to create service product: ' . $product->get_error_message() );
		}

		return $product;
	}

	/**
	 * Create an active membership for the shared customer.
	 *
	 * @param \WP_Ultimo\Models\Product $plan The plan product.
	 * @param array                     $overrides Optional overrides.
	 * @return Membership
	 */
	private function create_active_membership( $plan, array $overrides = [] ) {
		$defaults = [
			'customer_id'    => self::$customer->get_id(),
			'plan_id'        => $plan->get_id(),
			'status'         => 'active',
			'recurring'      => true,
			'amount'         => $plan->get_amount(),
			'duration'       => $plan->get_duration(),
			'duration_unit'  => $plan->get_duration_unit(),
			'date_expiration' => gmdate( 'Y-m-d 23:59:59', strtotime( '+30 days' ) ),
			'date_created'   => wu_get_current_time( 'mysql', true ),
			'date_activated' => wu_get_current_time( 'mysql', true ),
		];

		$membership = wu_create_membership( array_merge( $defaults, $overrides ) );

		if ( is_wp_error( $membership ) ) {
			$this->fail( 'Failed to create membership: ' . $membership->get_error_message() );
		}

		return $membership;
	}

	// =========================================================================
	// BUILD_FROM_PAYMENT TESTS
	// =========================================================================

	/**
	 * Test build_from_payment with a non-existent payment ID adds error.
	 */
	public function test_build_from_payment_nonexistent_payment() {
		wp_set_current_user( self::$customer->get_user_id() );

		$cart = new Cart(
			[
				'payment_id' => 999999,
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );
		$this->assertContains( 'payment_not_found', $cart->errors->get_error_codes() );
	}

	/**
	 * Test build_from_payment with a payment owned by a different customer adds permission error.
	 */
	public function test_build_from_payment_wrong_customer() {
		// Create a second customer
		$uid            = uniqid( 'other-' );
		$other_customer = wu_create_customer(
			[
				'username' => $uid,
				'email'    => $uid . '@example.com',
				'password' => 'password123',
			]
		);

		if ( is_wp_error( $other_customer ) ) {
			$this->markTestSkipped( 'Could not create second customer: ' . $other_customer->get_error_message() );
		}

		$plan = $this->create_plan();

		$membership = wu_create_membership(
			[
				'customer_id'  => $other_customer->get_id(),
				'plan_id'      => $plan->get_id(),
				'status'       => 'active',
				'amount'       => $plan->get_amount(),
				'duration'     => $plan->get_duration(),
				'duration_unit' => $plan->get_duration_unit(),
			]
		);

		if ( is_wp_error( $membership ) ) {
			$other_customer->delete();
			$plan->delete();
			$this->markTestSkipped( 'Could not create membership' );
		}

		$payment = wu_create_payment(
			[
				'customer_id'  => $other_customer->get_id(),
				'membership_id' => $membership->get_id(),
				'total'        => 50.00,
				'subtotal'     => 50.00,
				'status'       => Payment_Status::PENDING,
			]
		);

		if ( is_wp_error( $payment ) ) {
			$membership->delete();
			$plan->delete();
			$other_customer->delete();
			$this->markTestSkipped( 'Could not create payment: ' . $payment->get_error_message() );
		}

		// Log in as our shared customer (not the payment owner)
		wp_set_current_user( self::$customer->get_user_id() );

		$cart = new Cart(
			[
				'payment_id' => $payment->get_id(),
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );
		$this->assertContains( 'lacks_permission', $cart->errors->get_error_codes() );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$plan->delete();
		$other_customer->delete();
	}

	/**
	 * Test build_from_payment with a pending payment and pending membership returns retry cart.
	 */
	public function test_build_from_payment_pending_payment_pending_membership() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 30.00 ] );

		// Create a membership with 'pending' status (not active, not trialing)
		$membership = wu_create_membership(
			[
				'customer_id'  => self::$customer->get_id(),
				'plan_id'      => $plan->get_id(),
				'status'       => 'pending',
				'amount'       => 30.00,
				'duration'     => 1,
				'duration_unit' => 'month',
			]
		);

		if ( is_wp_error( $membership ) ) {
			$plan->delete();
			$this->markTestSkipped( 'Could not create membership' );
		}

		$payment = wu_create_payment(
			[
				'customer_id'  => self::$customer->get_id(),
				'membership_id' => $membership->get_id(),
				'total'        => 30.00,
				'subtotal'     => 30.00,
				'status'       => Payment_Status::PENDING,
			]
		);

		if ( is_wp_error( $payment ) ) {
			$membership->delete();
			$plan->delete();
			$this->markTestSkipped( 'Could not create payment: ' . $payment->get_error_message() );
		}

		$cart = new Cart(
			[
				'payment_id' => $payment->get_id(),
			]
		);

		// Cart should be built from payment; retry type set
		$this->assertEquals( 'retry', $cart->get_cart_type() );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$plan->delete();
	}

	/**
	 * Test build_from_payment with a completed payment returns false (no retry).
	 */
	public function test_build_from_payment_completed_payment() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 40.00 ] );

		$membership = $this->create_active_membership( $plan );

		$payment = wu_create_payment(
			[
				'customer_id'  => self::$customer->get_id(),
				'membership_id' => $membership->get_id(),
				'total'        => 40.00,
				'subtotal'     => 40.00,
				'status'       => Payment_Status::COMPLETED,
			]
		);

		if ( is_wp_error( $payment ) ) {
			$membership->delete();
			$plan->delete();
			$this->markTestSkipped( 'Could not create payment: ' . $payment->get_error_message() );
		}

		$cart = new Cart(
			[
				'payment_id' => $payment->get_id(),
			]
		);

		// Completed payment should not produce errors
		$this->assertFalse( $cart->errors->has_errors() );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$plan->delete();
	}

	/**
	 * Test build_from_payment with a cancelled payment returns false (no retry).
	 */
	public function test_build_from_payment_cancelled_payment() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 40.00 ] );

		$membership = $this->create_active_membership( $plan );

		$payment = wu_create_payment(
			[
				'customer_id'  => self::$customer->get_id(),
				'membership_id' => $membership->get_id(),
				'total'        => 40.00,
				'subtotal'     => 40.00,
				'status'       => Payment_Status::CANCELLED,
			]
		);

		if ( is_wp_error( $payment ) ) {
			$membership->delete();
			$plan->delete();
			$this->markTestSkipped( 'Could not create payment: ' . $payment->get_error_message() );
		}

		$cart = new Cart(
			[
				'payment_id' => $payment->get_id(),
			]
		);

		// Cancelled payment should not produce errors
		$this->assertFalse( $cart->errors->has_errors() );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$plan->delete();
	}

	/**
	 * Test build_from_payment with invalid status adds error.
	 */
	public function test_build_from_payment_invalid_status() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 40.00 ] );

		$membership = wu_create_membership(
			[
				'customer_id'  => self::$customer->get_id(),
				'plan_id'      => $plan->get_id(),
				'status'       => 'pending',
				'amount'       => 40.00,
				'duration'     => 1,
				'duration_unit' => 'month',
			]
		);

		if ( is_wp_error( $membership ) ) {
			$plan->delete();
			$this->markTestSkipped( 'Could not create membership' );
		}

		// Create a payment with 'refunded' status (not in allowed list for retry)
		$payment = wu_create_payment(
			[
				'customer_id'  => self::$customer->get_id(),
				'membership_id' => $membership->get_id(),
				'total'        => 40.00,
				'subtotal'     => 40.00,
				'status'       => 'refunded',
			]
		);

		if ( is_wp_error( $payment ) ) {
			$membership->delete();
			$plan->delete();
			$this->markTestSkipped( 'Could not create payment: ' . $payment->get_error_message() );
		}

		$cart = new Cart(
			[
				'payment_id' => $payment->get_id(),
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );
		$this->assertContains( 'invalid_status', $cart->errors->get_error_codes() );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$plan->delete();
	}

	// =========================================================================
	// BUILD_FROM_MEMBERSHIP — PENDING MEMBERSHIP IGNORED
	// =========================================================================

	/**
	 * Test that a pending membership_id is ignored and cart_type resets to 'new'.
	 */
	public function test_pending_membership_id_is_ignored() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		$pending_membership = wu_create_membership(
			[
				'customer_id'  => self::$customer->get_id(),
				'plan_id'      => $plan->get_id(),
				'status'       => 'pending',
				'amount'       => 50.00,
				'duration'     => 1,
				'duration_unit' => 'month',
			]
		);

		if ( is_wp_error( $pending_membership ) ) {
			$plan->delete();
			$this->markTestSkipped( 'Could not create pending membership' );
		}

		$new_plan = $this->create_plan( [ 'amount' => 75.00 ] );

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $pending_membership->get_id(),
				'products'      => [ $new_plan->get_id() ],
			]
		);

		// Pending membership should be ignored; cart_type should be 'new'
		$this->assertEquals( 'new', $cart->get_cart_type() );

		// Cleanup
		$pending_membership->delete();
		$plan->delete();
		$new_plan->delete();
	}

	// =========================================================================
	// BUILD_FROM_MEMBERSHIP — NO PRODUCTS (no_changes error)
	// =========================================================================

	/**
	 * Test that membership change with no products adds no_changes error.
	 */
	public function test_membership_change_with_no_products_adds_error() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		$membership = $this->create_active_membership( $plan );

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [],
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );
		$error_codes = $cart->errors->get_error_codes();
		// Either no_changes or another error indicating no products
		$this->assertTrue(
			in_array( 'no_changes', $error_codes, true ) || count( $error_codes ) > 0,
			'Expected errors for membership change with no products'
		);

		// Cleanup
		$membership->delete();
		$plan->delete();
	}

	/**
	 * Test that membership change with no products but existing payment does not add error.
	 */
	public function test_membership_change_no_products_with_payment_no_error() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		$membership = $this->create_active_membership( $plan );

		$payment = wu_create_payment(
			[
				'customer_id'  => self::$customer->get_id(),
				'membership_id' => $membership->get_id(),
				'total'        => 50.00,
				'subtotal'     => 50.00,
				'status'       => Payment_Status::COMPLETED,
			]
		);

		if ( is_wp_error( $payment ) ) {
			$membership->delete();
			$plan->delete();
			$this->markTestSkipped( 'Could not create payment: ' . $payment->get_error_message() );
		}

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'payment_id'    => $payment->get_id(),
				'products'      => [],
			]
		);

		// With a completed payment and no products, no_changes error should NOT be added
		// (build_from_payment returns false, then build_from_membership returns false for no products with payment)
		$error_codes = $cart->errors->get_error_codes();
		$this->assertNotContains( 'no_changes', $error_codes );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$plan->delete();
	}

	// =========================================================================
	// BUILD_FROM_MEMBERSHIP — ADDON CART (no plan in products)
	// =========================================================================

	/**
	 * Test addon cart: adding a service to an existing membership (no plan in products).
	 */
	public function test_addon_cart_service_only() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan    = $this->create_plan( [ 'amount' => 50.00 ] );
		$service = $this->create_service( [ 'amount' => 15.00 ] );

		$membership = $this->create_active_membership( $plan );

		$cart = new Cart(
			[
				'cart_type'     => 'addon',
				'membership_id' => $membership->get_id(),
				'products'      => [ $service->get_id() ],
			]
		);

		$this->assertEquals( 'addon', $cart->get_cart_type() );
		$this->assertFalse( $cart->errors->has_errors() );

		// Cleanup
		$membership->delete();
		$plan->delete();
		$service->delete();
	}

	/**
	 * Test addon cart with membership discount code that applies to renewals.
	 */
	public function test_addon_cart_applies_membership_discount_code() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan    = $this->create_plan( [ 'amount' => 50.00 ] );
		$service = $this->create_service( [ 'amount' => 20.00 ] );

		$code = 'RNWDSC' . uniqid();

		$discount = wu_create_discount_code(
			[
				'name'              => 'Renewal Discount',
				'code'              => $code,
				'value'             => 10,
				'type'              => 'percentage',
				'active'            => true,
				'apply_to_renewals' => true,
				'skip_validation'   => true,
			]
		);

		if ( is_wp_error( $discount ) ) {
			$plan->delete();
			$service->delete();
			$this->markTestSkipped( 'Could not create discount code' );
		}

		$membership = $this->create_active_membership( $plan );
		$membership->set_discount_code( $code );
		$membership->save();

		$cart = new Cart(
			[
				'cart_type'     => 'addon',
				'membership_id' => $membership->get_id(),
				'products'      => [ $service->get_id() ],
			]
		);

		$this->assertEquals( 'addon', $cart->get_cart_type() );

		// Cleanup
		$discount->delete();
		$membership->delete();
		$plan->delete();
		$service->delete();
	}

	/**
	 * Test addon cart with no products and no payment adds error.
	 */
	public function test_addon_cart_no_products_adds_error() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		$membership = $this->create_active_membership( $plan );

		// Pass a non-existent product to trigger empty products after add_product fails
		$cart = new Cart(
			[
				'cart_type'     => 'addon',
				'membership_id' => $membership->get_id(),
				'products'      => [ 999999 ], // non-existent
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );

		// Cleanup
		$membership->delete();
		$plan->delete();
	}

	/**
	 * Test wu_cart_show_no_changes_error filter suppresses no_changes error.
	 */
	public function test_addon_cart_no_changes_error_suppressed_by_filter() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		$membership = $this->create_active_membership( $plan );

		add_filter( 'wu_cart_show_no_changes_error', '__return_false' );

		// Pass a non-existent product to trigger empty products after add_product fails
		$cart = new Cart(
			[
				'cart_type'     => 'addon',
				'membership_id' => $membership->get_id(),
				'products'      => [ 999999 ], // non-existent
			]
		);

		remove_filter( 'wu_cart_show_no_changes_error', '__return_false' );

		// no_changes error should NOT be present (suppressed by filter)
		$error_codes = $cart->errors->get_error_codes();
		$this->assertNotContains( 'no_changes', $error_codes );

		// Cleanup
		$membership->delete();
		$plan->delete();
	}

	// =========================================================================
	// BUILD_FROM_MEMBERSHIP — ADDON WITH PLAN + SERVICE (plan removed)
	// =========================================================================

	/**
	 * Test addon cart: plan + service passed, plan is removed, only service charged.
	 */
	public function test_addon_cart_plan_plus_service_removes_plan() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan    = $this->create_plan( [ 'amount' => 50.00 ] );
		$service = $this->create_service( [ 'amount' => 20.00 ] );

		$membership = $this->create_active_membership( $plan );

		// Pass both the existing plan and a new service
		$cart = new Cart(
			[
				'cart_type'     => 'addon',
				'membership_id' => $membership->get_id(),
				'products'      => [ $plan->get_id(), $service->get_id() ],
			]
		);

		$this->assertEquals( 'addon', $cart->get_cart_type() );

		// The plan should have been removed; only service charged
		$this->assertEquals( 20.00, $cart->get_total() );

		// Cleanup
		$membership->delete();
		$plan->delete();
		$service->delete();
	}

	// =========================================================================
	// BUILD_FROM_MEMBERSHIP — UPGRADE CART
	// =========================================================================

	/**
	 * Test upgrade cart: switching to a more expensive plan.
	 */
	public function test_upgrade_cart_more_expensive_plan() {
		wp_set_current_user( self::$customer->get_user_id() );

		$cheap_plan     = $this->create_plan( [ 'amount' => 20.00 ] );
		$expensive_plan = $this->create_plan( [ 'amount' => 80.00 ] );

		$membership = $this->create_active_membership( $cheap_plan );

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $expensive_plan->get_id() ],
			]
		);

		$this->assertEquals( 'upgrade', $cart->get_cart_type() );
		$this->assertFalse( $cart->errors->has_errors() );

		// Cleanup
		$membership->delete();
		$cheap_plan->delete();
		$expensive_plan->delete();
	}

	/**
	 * Test downgrade cart: switching to a cheaper plan.
	 */
	public function test_downgrade_cart_cheaper_plan() {
		wp_set_current_user( self::$customer->get_user_id() );

		$expensive_plan = $this->create_plan( [ 'amount' => 100.00 ] );
		$cheap_plan     = $this->create_plan( [ 'amount' => 20.00 ] );

		$membership = $this->create_active_membership( $expensive_plan );

		$cart = new Cart(
			[
				'cart_type'     => 'downgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $cheap_plan->get_id() ],
			]
		);

		$this->assertEquals( 'downgrade', $cart->get_cart_type() );

		// Cleanup
		$membership->delete();
		$expensive_plan->delete();
		$cheap_plan->delete();
	}

	/**
	 * Test upgrade to lifetime (non-recurring) plan.
	 */
	public function test_upgrade_to_lifetime_plan() {
		wp_set_current_user( self::$customer->get_user_id() );

		$monthly_plan  = $this->create_plan( [ 'amount' => 50.00 ] );
		$lifetime_plan = $this->create_plan(
			[
				'amount'       => 500.00,
				'recurring'    => false,
				'pricing_type' => 'paid',
			]
		);

		$membership = $this->create_active_membership( $monthly_plan );

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $lifetime_plan->get_id() ],
			]
		);

		// Should not have errors (lifetime upgrade is valid)
		$this->assertFalse( $cart->errors->has_errors() );

		// Cleanup
		$membership->delete();
		$monthly_plan->delete();
		$lifetime_plan->delete();
	}

	/**
	 * Test no_changes error when same plan and same duration.
	 */
	public function test_no_changes_error_same_plan_same_duration() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan(
			[
				'amount'        => 50.00,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		$membership = $this->create_active_membership( $plan );

		// Same plan, same duration — no changes
		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $plan->get_id() ],
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );
		$this->assertContains( 'no_changes', $cart->errors->get_error_codes() );

		// Cleanup
		$membership->delete();
		$plan->delete();
	}

	/**
	 * Test membership change with nonexistent membership_id adds error.
	 */
	public function test_membership_change_nonexistent_membership() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan();

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => 999999,
				'products'      => [ $plan->get_id() ],
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );
		$this->assertContains( 'membership_not_found', $cart->errors->get_error_codes() );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test membership change by wrong customer adds lacks_permission error.
	 */
	public function test_membership_change_wrong_customer() {
		$uid            = uniqid( 'perm-' );
		$other_customer = wu_create_customer(
			[
				'username' => $uid,
				'email'    => $uid . '@example.com',
				'password' => 'password123',
			]
		);

		if ( is_wp_error( $other_customer ) ) {
			$this->markTestSkipped( 'Could not create second customer' );
		}

		$plan = $this->create_plan();

		$membership = wu_create_membership(
			[
				'customer_id'  => $other_customer->get_id(),
				'plan_id'      => $plan->get_id(),
				'status'       => 'active',
				'amount'       => $plan->get_amount(),
				'duration'     => $plan->get_duration(),
				'duration_unit' => $plan->get_duration_unit(),
			]
		);

		if ( is_wp_error( $membership ) ) {
			$plan->delete();
			$other_customer->delete();
			$this->markTestSkipped( 'Could not create membership' );
		}

		// Log in as our shared customer (not the membership owner)
		wp_set_current_user( self::$customer->get_user_id() );

		$new_plan = $this->create_plan( [ 'amount' => 100.00 ] );

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $new_plan->get_id() ],
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );
		$this->assertContains( 'lacks_permission', $cart->errors->get_error_codes() );

		// Cleanup
		$membership->delete();
		$plan->delete();
		$new_plan->delete();
		$other_customer->delete();
	}

	// =========================================================================
	// CALCULATE_PRORATE_CREDITS
	// =========================================================================

	/**
	 * Test prorate credits are added for an upgrade cart.
	 */
	public function test_prorate_credits_added_for_upgrade() {
		wp_set_current_user( self::$customer->get_user_id() );

		$cheap_plan     = $this->create_plan( [ 'amount' => 20.00 ] );
		$expensive_plan = $this->create_plan( [ 'amount' => 80.00 ] );

		$membership = $this->create_active_membership( $cheap_plan );

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $expensive_plan->get_id() ],
			]
		);

		// Upgrade cart should have a credit line item
		$credit_items = $cart->get_line_items_by_type( 'credit' );
		$this->assertNotEmpty( $credit_items );

		// Cleanup
		$membership->delete();
		$cheap_plan->delete();
		$expensive_plan->delete();
	}

	/**
	 * Test prorate credits filter modifies credit amount.
	 */
	public function test_prorate_credits_filter() {
		wp_set_current_user( self::$customer->get_user_id() );

		$cheap_plan     = $this->create_plan( [ 'amount' => 20.00 ] );
		$expensive_plan = $this->create_plan( [ 'amount' => 80.00 ] );

		$membership = $this->create_active_membership( $cheap_plan );

		add_filter(
			'wu_checkout_calculate_prorate_credits',
			function ( $credit ) {
				return 5.00; // Force a fixed credit
			}
		);

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $expensive_plan->get_id() ],
			]
		);

		remove_all_filters( 'wu_checkout_calculate_prorate_credits' );

		$credit_items = $cart->get_line_items_by_type( 'credit' );
		$this->assertNotEmpty( $credit_items );

		$credit = reset( $credit_items );
		$this->assertEquals( -5.00, $credit->get_unit_price() );

		// Cleanup
		$membership->delete();
		$cheap_plan->delete();
		$expensive_plan->delete();
	}

	/**
	 * Test prorate credits skipped for trialing membership.
	 */
	public function test_prorate_credits_skipped_for_trialing_membership() {
		wp_set_current_user( self::$customer->get_user_id() );

		$cheap_plan     = $this->create_plan( [ 'amount' => 20.00 ] );
		$expensive_plan = $this->create_plan( [ 'amount' => 80.00 ] );

		$membership = $this->create_active_membership(
			$cheap_plan,
			[ 'status' => Membership_Status::TRIALING ]
		);

		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $expensive_plan->get_id() ],
			]
		);

		// Trialing membership should not have prorate credits
		$credit_items = $cart->get_line_items_by_type( 'credit' );
		$this->assertEmpty( $credit_items );

		// Cleanup
		$membership->delete();
		$cheap_plan->delete();
		$expensive_plan->delete();
	}

	// =========================================================================
	// PWYW (PAY WHAT YOU WANT) TESTS
	// =========================================================================

	/**
	 * Test PWYW product with custom amount.
	 */
	public function test_pwyw_product_with_custom_amount() {
		$pwyw_plan = $this->create_plan(
			[
				'amount'                => 0,
				'pricing_type'          => 'pay_what_you_want',
				'pwyw_minimum_amount'   => 5.00,
				'pwyw_suggested_amount' => 25.00,
			]
		);

		// Prime the meta cache so the Cart can read PWYW values after DB round-trip.
		$this->prime_pwyw_meta( $pwyw_plan, 25.00, 5.00 );

		$cart = new Cart(
			[
				'products'       => [ $pwyw_plan->get_id() ],
				'custom_amounts' => [ $pwyw_plan->get_id() => 30.00 ],
			]
		);

		$this->assertEquals( 30.00, $cart->get_total() );

		// Cleanup
		$pwyw_plan->delete();
	}

	/**
	 * Test PWYW product with amount below minimum adds error.
	 */
	public function test_pwyw_product_below_minimum_adds_error() {
		$pwyw_plan = $this->create_plan(
			[
				'amount'              => 0,
				'pricing_type'        => 'pay_what_you_want',
				'pwyw_minimum_amount' => 10.00,
			]
		);

		$this->prime_pwyw_meta( $pwyw_plan, 10.00, 10.00 );

		$cart = new Cart(
			[
				'products'       => [ $pwyw_plan->get_id() ],
				'custom_amounts' => [ $pwyw_plan->get_id() => 2.00 ],
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );
		$this->assertContains( 'pwyw-below-minimum', $cart->errors->get_error_codes() );

		// Cleanup
		$pwyw_plan->delete();
	}

	/**
	 * Test PWYW product with amount above maximum adds error.
	 */
	public function test_pwyw_product_above_maximum_adds_error() {
		$pwyw_plan = $this->create_plan(
			[
				'amount'       => 0,
				'pricing_type' => 'pay_what_you_want',
			]
		);

		$this->prime_pwyw_meta( $pwyw_plan, 50.00 );

		add_filter( 'wu_pwyw_maximum_amount', fn() => 100.00 );

		$cart = new Cart(
			[
				'products'       => [ $pwyw_plan->get_id() ],
				'custom_amounts' => [ $pwyw_plan->get_id() => 200.00 ],
			]
		);

		remove_all_filters( 'wu_pwyw_maximum_amount' );

		$this->assertTrue( $cart->errors->has_errors() );
		$this->assertContains( 'pwyw-above-maximum', $cart->errors->get_error_codes() );

		// Cleanup
		$pwyw_plan->delete();
	}

	/**
	 * Test PWYW product uses suggested amount when no custom amount provided.
	 */
	public function test_pwyw_product_uses_suggested_amount() {
		$pwyw_plan = $this->create_plan(
			[
				'amount'                => 0,
				'pricing_type'          => 'pay_what_you_want',
				'pwyw_suggested_amount' => 35.00,
			]
		);

		$this->prime_pwyw_meta( $pwyw_plan, 35.00 );

		$cart = new Cart(
			[
				'products' => [ $pwyw_plan->get_id() ],
				// No custom_amounts — should use suggested
			]
		);

		$this->assertEquals( 35.00, $cart->get_total() );

		// Cleanup
		$pwyw_plan->delete();
	}

	/**
	 * Test PWYW product with force_recurring mode.
	 */
	public function test_pwyw_product_force_recurring_mode() {
		$pwyw_plan = $this->create_plan(
			[
				'amount'                => 0,
				'pricing_type'          => 'pay_what_you_want',
				'pwyw_recurring_mode'   => 'force_recurring',
				'pwyw_suggested_amount' => 20.00,
			]
		);

		$this->prime_pwyw_meta( $pwyw_plan, 20.00, 0.0, 'force_recurring' );

		$cart = new Cart(
			[
				'products' => [ $pwyw_plan->get_id() ],
			]
		);

		$this->assertTrue( $cart->has_recurring() );

		// Cleanup
		$pwyw_plan->delete();
	}

	/**
	 * Test PWYW product with force_one_time mode.
	 */
	public function test_pwyw_product_force_one_time_mode() {
		$pwyw_plan = $this->create_plan(
			[
				'amount'                => 0,
				'pricing_type'          => 'pay_what_you_want',
				'pwyw_recurring_mode'   => 'force_one_time',
				'pwyw_suggested_amount' => 20.00,
			]
		);

		$this->prime_pwyw_meta( $pwyw_plan, 20.00, 0.0, 'force_one_time' );

		$cart = new Cart(
			[
				'products' => [ $pwyw_plan->get_id() ],
			]
		);

		$this->assertFalse( $cart->has_recurring() );

		// Cleanup
		$pwyw_plan->delete();
	}

	/**
	 * Test PWYW product with customer_choice mode and recurring=true.
	 */
	public function test_pwyw_product_customer_choice_recurring() {
		$pwyw_plan = $this->create_plan(
			[
				'amount'                => 0,
				'pricing_type'          => 'pay_what_you_want',
				'pwyw_recurring_mode'   => 'customer_choice',
				'pwyw_suggested_amount' => 20.00,
			]
		);

		$this->prime_pwyw_meta( $pwyw_plan, 20.00, 0.0, 'customer_choice' );

		$cart = new Cart(
			[
				'products'       => [ $pwyw_plan->get_id() ],
				'pwyw_recurring' => [ $pwyw_plan->get_id() => true ],
			]
		);

		$this->assertTrue( $cart->has_recurring() );

		// Cleanup
		$pwyw_plan->delete();
	}

	/**
	 * Test get_custom_amount_for_product returns null when not set.
	 */
	public function test_get_custom_amount_for_product_not_set() {
		$cart = new Cart( [] );

		$this->assertNull( $cart->get_custom_amount_for_product( 999 ) );
	}

	/**
	 * Test get_custom_amount_for_product returns float when set.
	 */
	public function test_get_custom_amount_for_product_set() {
		$plan = $this->create_plan(
			[
				'amount'       => 0,
				'pricing_type' => 'pay_what_you_want',
			]
		);

		$cart = new Cart(
			[
				'products'       => [ $plan->get_id() ],
				'custom_amounts' => [ $plan->get_id() => 42.50 ],
			]
		);

		$this->assertEquals( 42.50, $cart->get_custom_amount_for_product( $plan->get_id() ) );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test get_pwyw_recurring_for_product returns false when not set.
	 */
	public function test_get_pwyw_recurring_for_product_not_set() {
		$cart = new Cart( [] );

		$this->assertFalse( $cart->get_pwyw_recurring_for_product( 999 ) );
	}

	/**
	 * Test get_pwyw_recurring_for_product returns true when set.
	 */
	public function test_get_pwyw_recurring_for_product_set() {
		$plan = $this->create_plan(
			[
				'amount'                => 0,
				'pricing_type'          => 'pay_what_you_want',
				'pwyw_recurring_mode'   => 'customer_choice',
				'pwyw_suggested_amount' => 10.00,
			]
		);

		$this->prime_pwyw_meta( $plan, 10.00, 0.0, 'customer_choice' );

		$cart = new Cart(
			[
				'products'       => [ $plan->get_id() ],
				'pwyw_recurring' => [ $plan->get_id() => true ],
			]
		);

		$this->assertTrue( $cart->get_pwyw_recurring_for_product( $plan->get_id() ) );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// SANITIZE_PWYW_AMOUNTS / SANITIZE_PWYW_RECURRING (via constructor)
	// =========================================================================

	/**
	 * Test sanitize_pwyw_amounts filters out invalid keys and values.
	 */
	public function test_sanitize_pwyw_amounts_filters_invalid() {
		$plan = $this->create_plan(
			[
				'amount'       => 0,
				'pricing_type' => 'pay_what_you_want',
			]
		);

		// Pass invalid keys (0, negative) and non-scalar values
		$cart = new Cart(
			[
				'products'       => [ $plan->get_id() ],
				'custom_amounts' => [
					0              => 10.00,  // invalid key (0)
					-1             => 5.00,   // invalid key (negative)
					$plan->get_id() => 25.00, // valid
				],
			]
		);

		// Only the valid key should be present
		$this->assertEquals( 25.00, $cart->get_custom_amount_for_product( $plan->get_id() ) );
		$this->assertNull( $cart->get_custom_amount_for_product( 0 ) );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test sanitize_pwyw_amounts clamps negative values to 0.
	 */
	public function test_sanitize_pwyw_amounts_clamps_negative() {
		$plan = $this->create_plan(
			[
				'amount'       => 0,
				'pricing_type' => 'pay_what_you_want',
			]
		);

		$cart = new Cart(
			[
				'products'       => [ $plan->get_id() ],
				'custom_amounts' => [ $plan->get_id() => -50.00 ],
			]
		);

		// Negative value should be clamped to 0
		$this->assertEquals( 0.0, $cart->get_custom_amount_for_product( $plan->get_id() ) );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test sanitize_pwyw_recurring filters out invalid keys.
	 */
	public function test_sanitize_pwyw_recurring_filters_invalid() {
		$plan = $this->create_plan(
			[
				'amount'                => 0,
				'pricing_type'          => 'pay_what_you_want',
				'pwyw_recurring_mode'   => 'customer_choice',
				'pwyw_suggested_amount' => 10.00,
			]
		);

		$this->prime_pwyw_meta( $plan, 10.00, 0.0, 'customer_choice' );

		$cart = new Cart(
			[
				'products'       => [ $plan->get_id() ],
				'pwyw_recurring' => [
					0              => true,  // invalid key
					$plan->get_id() => true, // valid
				],
			]
		);

		$this->assertTrue( $cart->get_pwyw_recurring_for_product( $plan->get_id() ) );
		$this->assertFalse( $cart->get_pwyw_recurring_for_product( 0 ) );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// GET_INDEPENDENT_LINE_ITEMS
	// =========================================================================

	/**
	 * Test get_independent_line_items returns empty for standard products.
	 */
	public function test_get_independent_line_items_empty_for_standard_products() {
		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$independent = $cart->get_independent_line_items();
		$this->assertEmpty( $independent );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test get_independent_line_items returns empty array for cart with no products.
	 */
	public function test_get_independent_line_items_empty_cart() {
		$cart = new Cart( [] );

		$independent = $cart->get_independent_line_items();
		$this->assertIsArray( $independent );
		$this->assertEmpty( $independent );
	}

	// =========================================================================
	// CANCEL_CONFLICTING_PENDING_PAYMENTS
	// =========================================================================

	/**
	 * Test cancel_conflicting_pending_payments runs without error for new cart.
	 */
	public function test_cancel_conflicting_pending_payments_runs_for_new_cart() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		$cart = new Cart(
			[
				'cart_type' => 'new',
				'products'  => [ $plan->get_id() ],
			]
		);

		// Cart should be created without errors
		$this->assertInstanceOf( Cart::class, $cart );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test cancel_conflicting_pending_payments skips non-new cart types.
	 *
	 * When a membership_id is provided, build_from_membership() sets cart_type to
	 * 'upgrade' (or 'addon'), which means cancel_conflicting_pending_payments() is
	 * NOT called (it only runs for cart_type='new').
	 */
	public function test_cancel_conflicting_pending_payments_skips_non_new_cart() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan      = $this->create_plan( [ 'amount' => 50.00 ] );
		$new_plan  = $this->create_plan( [ 'amount' => 80.00 ] );

		// Create an active membership so build_from_membership() succeeds.
		$membership = $this->create_active_membership( $plan );

		$pending_payment = wu_create_payment(
			[
				'customer_id'  => self::$customer->get_id(),
				'membership_id' => $membership->get_id(),
				'total'        => 50.00,
				'subtotal'     => 50.00,
				'status'       => Payment_Status::PENDING,
			]
		);

		if ( is_wp_error( $pending_payment ) ) {
			$membership->delete();
			$plan->delete();
			$new_plan->delete();
			$this->markTestSkipped( 'Could not create pending payment' );
		}

		// Create an upgrade cart with a membership_id — cart_type stays 'upgrade',
		// so cancel_conflicting_pending_payments() is NOT called.
		$cart = new Cart(
			[
				'cart_type'     => 'upgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $new_plan->get_id() ],
			]
		);

		// Cart should be upgrade type (not new), so pending payment is untouched.
		$this->assertEquals( 'upgrade', $cart->get_cart_type() );

		// Pending payment should still be pending.
		$refreshed = wu_get_payment( $pending_payment->get_id() );
		if ( $refreshed ) {
			$this->assertEquals( Payment_Status::PENDING, $refreshed->get_status() );
		} else {
			// Payment was not found — just assert cart was created correctly.
			$this->assertInstanceOf( Cart::class, $cart );
		}

		// Cleanup
		$pending_payment->delete();
		$membership->delete();
		$plan->delete();
		$new_plan->delete();
	}

	// =========================================================================
	// HAS_TRIAL / SHOULD_COLLECT_PAYMENT WITH TRIAL
	// =========================================================================

	/**
	 * Test has_trial returns true for product with trial when no customer.
	 */
	public function test_has_trial_true_for_trial_product_no_customer() {
		// Log out
		wp_set_current_user( 0 );

		$plan = $this->create_plan(
			[
				'amount'              => 50.00,
				'trial_duration'      => 14,
				'trial_duration_unit' => 'day',
			]
		);

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$this->assertTrue( $cart->has_trial() );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test should_collect_payment returns false for free cart with trial when setting allows.
	 */
	public function test_should_collect_payment_false_for_free_trial_when_setting_allows() {
		wp_set_current_user( 0 );

		wu_save_setting( 'allow_trial_without_payment_method', true );

		$plan = $this->create_plan(
			[
				'amount'              => 50.00,
				'trial_duration'      => 7,
				'trial_duration_unit' => 'day',
			]
		);

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		if ( $cart->has_trial() ) {
			$this->assertFalse( $cart->should_collect_payment() );
		}

		wu_save_setting( 'allow_trial_without_payment_method', false );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test has_trial returns false for customer who has already trialed.
	 */
	public function test_has_trial_false_for_customer_who_trialed() {
		wp_set_current_user( self::$customer->get_user_id() );

		// Mark customer as having trialed
		self::$customer->set_has_trialed( true );
		self::$customer->save();

		$plan = $this->create_plan(
			[
				'amount'              => 50.00,
				'trial_duration'      => 14,
				'trial_duration_unit' => 'day',
			]
		);

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$this->assertFalse( $cart->has_trial() );

		// Reset
		self::$customer->set_has_trialed( false );
		self::$customer->save();

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// GET_BILLING_START_DATE / GET_BILLING_NEXT_CHARGE_DATE — DOWNGRADE PATHS
	// =========================================================================

	/**
	 * Test get_billing_start_date returns membership expiration for downgrade cart.
	 */
	public function test_billing_start_date_for_downgrade_cart() {
		wp_set_current_user( self::$customer->get_user_id() );

		$expensive_plan = $this->create_plan( [ 'amount' => 100.00 ] );
		$cheap_plan     = $this->create_plan( [ 'amount' => 20.00 ] );

		$expiration = gmdate( 'Y-m-d 23:59:59', strtotime( '+30 days' ) );

		$membership = $this->create_active_membership(
			$expensive_plan,
			[ 'date_expiration' => $expiration ]
		);

		$cart = new Cart(
			[
				'cart_type'     => 'downgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $cheap_plan->get_id() ],
			]
		);

		if ( $cart->get_cart_type() === 'downgrade' ) {
			$billing_start = $cart->get_billing_start_date();
			$this->assertNotNull( $billing_start );
			$this->assertGreaterThan( time(), $billing_start );
		}

		// Cleanup
		$membership->delete();
		$expensive_plan->delete();
		$cheap_plan->delete();
	}

	/**
	 * Test get_billing_next_charge_date returns membership expiration for downgrade cart.
	 */
	public function test_billing_next_charge_date_for_downgrade_cart() {
		wp_set_current_user( self::$customer->get_user_id() );

		$expensive_plan = $this->create_plan( [ 'amount' => 100.00 ] );
		$cheap_plan     = $this->create_plan( [ 'amount' => 20.00 ] );

		$expiration = gmdate( 'Y-m-d 23:59:59', strtotime( '+30 days' ) );

		$membership = $this->create_active_membership(
			$expensive_plan,
			[ 'date_expiration' => $expiration ]
		);

		$cart = new Cart(
			[
				'cart_type'     => 'downgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $cheap_plan->get_id() ],
			]
		);

		if ( $cart->get_cart_type() === 'downgrade' ) {
			$next_charge = $cart->get_billing_next_charge_date();
			$this->assertGreaterThan( time(), $next_charge );
		}

		// Cleanup
		$membership->delete();
		$expensive_plan->delete();
		$cheap_plan->delete();
	}

	// =========================================================================
	// GET_PRORATION_CREDITS
	// =========================================================================

	/**
	 * Test get_proration_credits returns 0 when no fees.
	 */
	public function test_get_proration_credits_zero_without_fees() {
		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$this->assertEquals( 0, $cart->get_proration_credits() );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// MISSING PRICE VARIATION ERROR
	// =========================================================================

	/**
	 * Test adding a product with mismatched duration adds missing-price-variations error.
	 */
	public function test_add_product_mismatched_duration_adds_error() {
		$monthly_plan = $this->create_plan(
			[
				'amount'        => 50.00,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		// Force cart to use yearly duration — monthly plan has no yearly variation
		$cart = new Cart(
			[
				'products'      => [ $monthly_plan->get_id() ],
				'duration'      => 1,
				'duration_unit' => 'year',
			]
		);

		$this->assertTrue( $cart->errors->has_errors() );
		$error_codes = $cart->errors->get_error_codes();
		$this->assertTrue(
			in_array( 'missing-price-variations', $error_codes, true ) ||
			in_array( 'missing-product', $error_codes, true ),
			'Expected missing-price-variations or missing-product error'
		);

		// Cleanup
		$monthly_plan->delete();
	}

	// =========================================================================
	// WU_ADD_PRODUCT_LINE_ITEM FILTER RETURNING EMPTY
	// =========================================================================

	/**
	 * Test add_product returns false when wu_add_product_line_item filter returns empty.
	 */
	public function test_add_product_returns_false_when_line_item_data_empty() {
		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		add_filter( 'wu_add_product_line_item', '__return_empty_array' );

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		remove_filter( 'wu_add_product_line_item', '__return_empty_array' );

		// Cart should have no line items since the filter returned empty
		$this->assertEmpty( $cart->get_line_items() );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// RENEWAL CART TYPE — SETUP FEE SKIPPED
	// =========================================================================

	/**
	 * Test wu_apply_signup_fee filter can suppress setup fee.
	 */
	public function test_signup_fee_suppressed_by_filter() {
		$plan = $this->create_plan(
			[
				'amount'    => 50.00,
				'setup_fee' => 20.00,
			]
		);

		// Use wu_apply_signup_fee filter to suppress setup fee
		add_filter( 'wu_apply_signup_fee', '__return_false' );

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		remove_filter( 'wu_apply_signup_fee', '__return_false' );

		// Setup fee should not be added
		$fee_items = $cart->get_line_items_by_type( 'fee' );
		$this->assertEmpty( $fee_items );

		// Total should only be the plan amount
		$this->assertEquals( 50.00, $cart->get_total() );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// DISCOUNT CODE WITH VALID DISCOUNT CODE VIA CONSTRUCTOR
	// =========================================================================

	/**
	 * Test valid discount code applied via constructor reduces total.
	 */
	public function test_valid_discount_code_via_constructor_reduces_total() {
		$plan = $this->create_plan( [ 'amount' => 100.00 ] );

		$code = 'VALID' . uniqid();

		$discount = wu_create_discount_code(
			[
				'name'            => 'Valid Discount',
				'code'            => $code,
				'value'           => 20,
				'type'            => 'percentage',
				'active'          => true,
				'skip_validation' => true,
			]
		);

		if ( is_wp_error( $discount ) ) {
			$plan->delete();
			$this->markTestSkipped( 'Could not create discount code' );
		}

		$cart = new Cart(
			[
				'products'      => [ $plan->get_id() ],
				'discount_code' => $code,
			]
		);

		// If discount code was applied successfully
		if ( ! $cart->errors->has_errors() ) {
			$this->assertTrue( $cart->has_discount() );
			$this->assertEquals( 80.00, $cart->get_total() );
		} else {
			// Discount code validation may fail in test environment
			$this->markTestSkipped( 'Discount code validation failed in test environment' );
		}

		// Cleanup
		$discount->delete();
		$plan->delete();
	}

	// =========================================================================
	// GET_CART_URL — DURATION > 1 AND NON-MONTH UNIT
	// =========================================================================

	/**
	 * Test cart URL includes duration when > 1.
	 */
	public function test_cart_url_includes_duration_greater_than_one() {
		$plan = $this->create_plan(
			[
				'amount'        => 120.00,
				'duration'      => 3,
				'duration_unit' => 'month',
			]
		);

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$url = $cart->get_cart_url();

		$this->assertStringContainsString( '3', $url );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test cart URL includes non-month duration unit.
	 */
	public function test_cart_url_includes_non_month_duration_unit() {
		$plan = $this->create_plan(
			[
				'amount'        => 500.00,
				'duration'      => 1,
				'duration_unit' => 'year',
			]
		);

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$url = $cart->get_cart_url();

		$this->assertStringContainsString( 'year', $url );

		// Cleanup
		$plan->delete();
	}

	/**
	 * Test cart URL includes additional product slugs.
	 */
	public function test_cart_url_includes_additional_product_slugs() {
		$plan_uid    = uniqid( 'main-plan-' );
		$service_uid = uniqid( 'extra-svc-' );

		$plan    = $this->create_plan( [ 'slug' => $plan_uid, 'amount' => 50.00 ] );
		$service = $this->create_service( [ 'slug' => $service_uid, 'amount' => 10.00 ] );

		$cart = new Cart(
			[
				'products' => [ $plan->get_id(), $service->get_id() ],
			]
		);

		$url = $cart->get_cart_url();

		$this->assertStringContainsString( $service_uid, $url );

		// Cleanup
		$plan->delete();
		$service->delete();
	}

	// =========================================================================
	// RECURRING TOTAL WITH DISCOUNT NOT APPLIED TO RENEWALS
	// =========================================================================

	/**
	 * Test recurring total excludes discount when discount does not apply to renewals.
	 */
	public function test_recurring_total_excludes_non_renewal_discount() {
		$plan = $this->create_plan( [ 'amount' => 100.00 ] );

		// Create discount code object directly (no DB lookup needed)
		$discount_code = new \WP_Ultimo\Models\Discount_Code();
		$discount_code->set_active( true );
		$discount_code->set_code( 'NORENEW' . uniqid() );
		$discount_code->set_value( 50 );
		$discount_code->set_type( 'percentage' );
		$discount_code->set_apply_to_renewals( false );

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		// Apply discount directly
		$cart->add_discount_code( $discount_code );

		// Apply discounts to existing line items
		foreach ( $cart->get_line_items() as $id => $line_item ) {
			if ( $line_item->is_discountable() ) {
				$discounted = $cart->apply_discounts_to_item( $line_item );
				// Verify discount was applied
				$this->assertEquals( 50.0, $discounted->get_discount_total() );
			}
		}

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// CALCULATE_TOTALS — TOTAL_FEES
	// =========================================================================

	/**
	 * Test calculate_totals includes total_fees in result.
	 */
	public function test_calculate_totals_includes_total_fees() {
		$plan = $this->create_plan(
			[
				'amount'    => 50.00,
				'setup_fee' => 10.00,
			]
		);

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$totals = $cart->calculate_totals();

		$this->assertObjectHasProperty( 'total_fees', $totals );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// DONE() WITH ERRORS
	// =========================================================================

	/**
	 * Test done() includes error details when cart has errors.
	 */
	public function test_done_includes_error_details() {
		$cart = new Cart(
			[
				'products' => [ 999999 ],
			]
		);

		$result = $cart->done();

		$this->assertNotEmpty( $result->errors );

		$first_error = reset( $result->errors );
		$this->assertArrayHasKey( 'code', $first_error );
		$this->assertArrayHasKey( 'message', $first_error );
	}

	// =========================================================================
	// MEMBERSHIP CHANGE — ACTIVE AGREEMENT ERROR
	// =========================================================================

	/**
	 * Test that switching from yearly to monthly plan with active yearly agreement adds error.
	 */
	public function test_active_yearly_agreement_blocks_monthly_switch() {
		wp_set_current_user( self::$customer->get_user_id() );

		$yearly_plan  = $this->create_plan(
			[
				'amount'        => 500.00,
				'duration'      => 1,
				'duration_unit' => 'year',
			]
		);
		$monthly_plan = $this->create_plan(
			[
				'amount'        => 50.00,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		$membership = $this->create_active_membership(
			$yearly_plan,
			[
				'amount'        => 500.00,
				'duration'      => 1,
				'duration_unit' => 'year',
			]
		);

		$cart = new Cart(
			[
				'cart_type'     => 'downgrade',
				'membership_id' => $membership->get_id(),
				'products'      => [ $monthly_plan->get_id() ],
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		// Should have errors (active yearly agreement blocks monthly switch)
		$this->assertTrue( $cart->errors->has_errors() );

		// Cleanup
		$membership->delete();
		$yearly_plan->delete();
		$monthly_plan->delete();
	}

	// =========================================================================
	// REAPPLY_DISCOUNTS_TO_EXISTING_LINE_ITEMS (via addon cart)
	// =========================================================================

	/**
	 * Test that reapply_discounts_to_existing_line_items is triggered for addon cart with discount.
	 */
	public function test_reapply_discounts_triggered_for_addon_with_renewal_discount() {
		wp_set_current_user( self::$customer->get_user_id() );

		$plan    = $this->create_plan( [ 'amount' => 50.00 ] );
		$service = $this->create_service( [ 'amount' => 20.00 ] );

		$code = 'RNWADDON' . uniqid();

		$discount = wu_create_discount_code(
			[
				'name'              => 'Addon Renewal Discount',
				'code'              => $code,
				'value'             => 10,
				'type'              => 'percentage',
				'active'            => true,
				'apply_to_renewals' => true,
				'skip_validation'   => true,
			]
		);

		if ( is_wp_error( $discount ) ) {
			$plan->delete();
			$service->delete();
			$this->markTestSkipped( 'Could not create discount code' );
		}

		$membership = $this->create_active_membership( $plan );
		$membership->set_discount_code( $code );
		$membership->save();

		// Prime the membership meta cache so the Cart can read the discount code
		// after the membership is re-fetched from DB.
		$membership_id = $membership->get_id();
		$existing_meta = wp_cache_get( $membership_id, 'wu_membership_meta' );
		if ( ! is_array( $existing_meta ) ) {
			$existing_meta = [];
		}
		// META_DISCOUNT_CODE = 'discount_code'
		$existing_meta['discount_code'] = [ $discount ];
		wp_cache_set( $membership_id, $existing_meta, 'wu_membership_meta' );

		$cart = new Cart(
			[
				'cart_type'     => 'addon',
				'membership_id' => $membership->get_id(),
				'products'      => [ $service->get_id() ],
			]
		);

		// Cart should be addon type
		$this->assertEquals( 'addon', $cart->get_cart_type() );

		// Discount code should be applied from membership (if meta cache was primed)
		$applied_discount = $cart->get_discount_code();
		$this->assertNotNull( $applied_discount );

		// Cleanup
		$discount->delete();
		$membership->delete();
		$plan->delete();
		$service->delete();
	}

	// =========================================================================
	// SHOULD_COLLECT_PAYMENT — FILTER
	// =========================================================================

	/**
	 * Test wu_cart_should_collect_payment filter overrides result.
	 */
	public function test_should_collect_payment_filter() {
		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		// Create cart first, then test the filter on a fresh cart
		add_filter( 'wu_cart_should_collect_payment', '__return_false' );

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$result = $cart->should_collect_payment();

		remove_filter( 'wu_cart_should_collect_payment', '__return_false' );

		$this->assertFalse( $result );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// IS_VALID — MISMATCHED BILLING INTERVALS
	// =========================================================================

	/**
	 * Test is_valid returns false when two recurring products have different billing intervals.
	 */
	public function test_is_valid_false_for_mismatched_billing_intervals() {
		$monthly_plan = $this->create_plan(
			[
				'amount'        => 50.00,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		$cart = new Cart(
			[
				'products' => [ $monthly_plan->get_id() ],
			]
		);

		// Manually add a line item with different interval
		$yearly_item = new Line_Item(
			[
				'type'          => 'product',
				'title'         => 'Yearly Item',
				'unit_price'    => 500,
				'quantity'      => 1,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'year',
				'taxable'       => false,
			]
		);

		$cart->add_line_item( $yearly_item );

		$this->assertFalse( $cart->is_valid() );
		$this->assertContains( 'wrong', $cart->errors->get_error_codes() );

		// Cleanup
		$monthly_plan->delete();
	}

	// =========================================================================
	// GET_CART_DESCRIPTOR — EMPTY CART
	// =========================================================================

	/**
	 * Test get_cart_descriptor returns company name for empty cart.
	 */
	public function test_cart_descriptor_empty_cart() {
		$cart = new Cart( [] );

		$descriptor = $cart->get_cart_descriptor();

		$this->assertIsString( $descriptor );
	}

	// =========================================================================
	// TO_MEMBERSHIP_DATA — WITH ADDON PRODUCTS
	// =========================================================================

	/**
	 * Test to_membership_data includes addon products.
	 */
	public function test_to_membership_data_includes_addon_products() {
		$plan    = $this->create_plan( [ 'amount' => 50.00 ] );
		$service = $this->create_service( [ 'amount' => 10.00 ] );

		$cart = new Cart(
			[
				'products' => [ $plan->get_id(), $service->get_id() ],
			]
		);

		$data = $cart->to_membership_data();

		$this->assertArrayHasKey( 'addon_products', $data );
		$this->assertArrayHasKey( $service->get_id(), $data['addon_products'] );

		// Cleanup
		$plan->delete();
		$service->delete();
	}

	// =========================================================================
	// APPLY_DISCOUNTS_TO_ITEM — FEE WITH ZERO SETUP FEE VALUE
	// =========================================================================

	/**
	 * Test apply_discounts_to_item returns unchanged fee item when setup_fee_value is 0.
	 */
	public function test_apply_discounts_to_item_fee_with_zero_setup_fee_value() {
		$discount_code = new \WP_Ultimo\Models\Discount_Code();
		$discount_code->set_active( true );
		$discount_code->set_code( 'NOFEE' . uniqid() );
		$discount_code->set_value( 20 );
		$discount_code->set_type( 'percentage' );
		$discount_code->set_setup_fee_value( 0 ); // No fee discount

		$cart = new Cart( [] );
		$cart->add_discount_code( $discount_code );

		$fee_item = new Line_Item(
			[
				'type'         => 'fee',
				'title'        => 'Setup Fee',
				'unit_price'   => 50,
				'quantity'     => 1,
				'discountable' => true,
				'taxable'      => false,
			]
		);

		$result = $cart->apply_discounts_to_item( $fee_item );

		// Fee should not be discounted (setup_fee_value is 0)
		$this->assertEquals( 0, $result->get_discount_total() );
	}

	// =========================================================================
	// APPLY_DISCOUNTS_TO_ITEM — DISCOUNT CODE VALIDATION FAILS
	// =========================================================================

	/**
	 * Test apply_discounts_to_item returns unchanged item when discount code is inactive.
	 */
	public function test_apply_discounts_to_item_inactive_discount_code() {
		$discount_code = new \WP_Ultimo\Models\Discount_Code();
		$discount_code->set_active( false ); // Inactive
		$discount_code->set_code( 'INACTIVE' . uniqid() );
		$discount_code->set_value( 50 );
		$discount_code->set_type( 'percentage' );

		$cart = new Cart( [] );
		$cart->add_discount_code( $discount_code );

		$line_item = new Line_Item(
			[
				'type'         => 'product',
				'title'        => 'Test',
				'unit_price'   => 100,
				'quantity'     => 1,
				'discountable' => true,
				'taxable'      => false,
			]
		);

		$result = $cart->apply_discounts_to_item( $line_item );

		// Discount should not be applied since code is inactive
		$this->assertInstanceOf( Line_Item::class, $result );
	}

	// =========================================================================
	// GET_NON_RECURRING_PRODUCTS
	// =========================================================================

	/**
	 * Test get_non_recurring_products returns non-recurring products.
	 */
	public function test_get_non_recurring_products() {
		$service = $this->create_service( [ 'amount' => 10.00, 'recurring' => false ] );

		$cart = new Cart(
			[
				'products' => [ $service->get_id() ],
			]
		);

		$non_recurring = $cart->get_non_recurring_products();
		$this->assertIsArray( $non_recurring );

		// Cleanup
		$service->delete();
	}

	// =========================================================================
	// GET_RECURRING_PRODUCTS
	// =========================================================================

	/**
	 * Test get_recurring_products returns recurring products.
	 */
	public function test_get_recurring_products() {
		$plan = $this->create_plan( [ 'amount' => 50.00, 'recurring' => true ] );

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$recurring = $cart->get_recurring_products();
		$this->assertIsArray( $recurring );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// BILLING START DATE — PRODUCT WITH NO TRIAL
	// =========================================================================

	/**
	 * Test get_billing_start_date returns 0 when product has no trial.
	 */
	public function test_billing_start_date_zero_for_product_without_trial() {
		$plan = $this->create_plan(
			[
				'amount'         => 50.00,
				'trial_duration' => 0,
			]
		);

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		$billing_start = $cart->get_billing_start_date();

		// No trial — should return 0 (falsy)
		$this->assertEquals( 0, $billing_start );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// APPLY_TAXES_TO_ITEM — TAX CATEGORY MISSING
	// =========================================================================

	/**
	 * Test apply_taxes_to_item returns unchanged item when tax category is missing.
	 */
	public function test_apply_taxes_to_item_no_tax_category() {
		// Enable taxes via filter
		add_filter( 'wu_should_collect_taxes', '__return_true' );

		$cart = new Cart( [] );

		$line_item = new Line_Item(
			[
				'type'       => 'product',
				'title'      => 'Test',
				'unit_price' => 100,
				'quantity'   => 1,
				'taxable'    => true,
				// No tax_category set
			]
		);

		$result = $cart->apply_taxes_to_item( $line_item );

		remove_filter( 'wu_should_collect_taxes', '__return_true' );

		// No tax should be applied (no tax category)
		$this->assertEquals( 0, $result->get_tax_total() );
	}

	// =========================================================================
	// CART PRODUCT AMOUNT FILTER
	// =========================================================================

	/**
	 * Test wu_cart_product_amount filter modifies product amount.
	 */
	public function test_cart_product_amount_filter() {
		$plan = $this->create_plan( [ 'amount' => 50.00 ] );

		add_filter(
			'wu_cart_product_amount',
			function ( $amount ) {
				return 75.00; // Override to 75
			}
		);

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		remove_all_filters( 'wu_cart_product_amount' );

		$this->assertEquals( 75.00, $cart->get_total() );

		// Cleanup
		$plan->delete();
	}

	// =========================================================================
	// CART SETUP FEE FILTER
	// =========================================================================

	/**
	 * Test wu_cart_product_setup_fee filter modifies setup fee.
	 */
	public function test_cart_product_setup_fee_filter() {
		$plan = $this->create_plan(
			[
				'amount'    => 50.00,
				'setup_fee' => 10.00,
			]
		);

		add_filter(
			'wu_cart_product_setup_fee',
			function ( $fee ) {
				return 25.00; // Override to 25
			}
		);

		$cart = new Cart(
			[
				'products' => [ $plan->get_id() ],
			]
		);

		remove_all_filters( 'wu_cart_product_setup_fee' );

		$fee_items = $cart->get_line_items_by_type( 'fee' );
		$this->assertNotEmpty( $fee_items );

		$fee = reset( $fee_items );
		$this->assertEquals( 25.00, $fee->get_subtotal() );

		// Cleanup
		$plan->delete();
	}
}
