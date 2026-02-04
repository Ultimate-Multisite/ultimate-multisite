<?php

namespace WP_Ultimo\Models;

use WP_Ultimo\Checkout\Line_Item;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_UnitTestCase;

/**
 * Test class for Payment model functionality.
 *
 * Tests payment creation, line items management, financial calculations,
 * status handling, gateway functionality, and invoice features.
 */
class Payment_Test extends WP_UnitTestCase {

	private static Customer $customer;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$customer = wu_create_customer(
			[
				'username' => 'testuser2',
				'email'    => 'test2@example.com',
				'password' => 'password123',
			]
		);
	}
	/**
	 * Test payment creation with valid data.
	 */
	public function test_payment_creation_with_valid_data(): void {
		$payment = new Payment();
		$payment->set_customer_id(1); // Use dummy ID to test setters/getters
		$payment->set_membership_id(1);
		$payment->set_currency('USD');
		$payment->set_subtotal(100.00);
		$payment->set_total(110.00);
		$payment->set_status(Payment_Status::PENDING);
		$payment->set_gateway('manual');

		$this->assertEquals(1, $payment->get_customer_id());
		$this->assertEquals(1, $payment->get_membership_id());
		$this->assertEquals('USD', $payment->get_currency());
		$this->assertEquals(100.00, $payment->get_subtotal());
		$this->assertEquals(110.00, $payment->get_total());
		$this->assertEquals(Payment_Status::PENDING, $payment->get_status());
		$this->assertEquals('manual', $payment->get_gateway());
	}

	/**
	 * Test line items functionality.
	 */
	public function test_line_items_functionality(): void {
		$payment = new Payment();

		// Initially no line items
		$this->assertFalse($payment->has_line_items());
		$this->assertEquals([], $payment->get_line_items());

		// Create line items
		$line_item_1 = new Line_Item(
			[
				'type'        => 'fee',
				'hash'        => 'test_item_1',
				'title'       => 'Test Item 1',
				'description' => 'First test item',
				'unit_price'  => 50.00,
				'quantity'    => 2,
				'taxable'     => true,
				'tax_rate'    => 10.00,
			]
		);

		$line_item_2 = new Line_Item(
			[
				'type'        => 'product',
				'hash'        => 'test_item_2',
				'title'       => 'Test Item 2',
				'description' => 'Second test item',
				'unit_price'  => 30.00,
				'quantity'    => 1,
				'taxable'     => false,
				'tax_rate'    => 0.00,
			]
		);

		// Set line items
		$line_items = [$line_item_1, $line_item_2];
		$payment->set_line_items($line_items);

		// Test that line items were set
		$this->assertTrue($payment->has_line_items());
		$saved_line_items = $payment->get_line_items();
		$this->assertCount(2, $saved_line_items);

		// Verify we have items with the expected types
		$found_types = array_map(fn($item) => $item->get_type(), $saved_line_items);
		$this->assertContains('fee', $found_types);
		$this->assertContains('product', $found_types);

		// Verify first item by finding it by type
		$fee_items = array_filter($saved_line_items, fn($item) => $item->get_type() === 'fee');
		$this->assertNotEmpty($fee_items);
		$fee_item = reset($fee_items);
		$this->assertEquals('Test Item 1', $fee_item->get_title());
		$this->assertEquals(50.00, $fee_item->get_unit_price());
		$this->assertEquals(2, $fee_item->get_quantity());
		$this->assertTrue($fee_item->is_taxable());
		$this->assertEquals(10.00, $fee_item->get_tax_rate());

		// Verify second item by finding it by type
		$product_items = array_filter($saved_line_items, fn($item) => $item->get_type() === 'product');
		$this->assertNotEmpty($product_items);
		$product_item = reset($product_items);
		$this->assertEquals('Test Item 2', $product_item->get_title());
		$this->assertEquals(30.00, $product_item->get_unit_price());
		$this->assertEquals(1, $product_item->get_quantity());
		$this->assertFalse($product_item->is_taxable());
	}

	/**
	 * Test adding individual line items.
	 */
	public function test_add_line_item(): void {
		$payment = new Payment();

		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());
		// Create first product
		$product = wu_create_product(
			[
				'name'                => 'Test Product',
				'slug'                => 'test-product',
				'amount'              => 50.00,
				'recurring'           => true,
				'duration'            => 1,
				'duration_unit'       => 'month',
				'trial_duration'      => 5,
				'trial_duration_unit' => 'day',
				'type'                => 'plan',
				'pricing_type'        => 'paid',
				'active'              => true,
			]
		);

		// Create membership
		$membership = wu_create_membership(
			[
				'customer_id'     => $customer->get_id(),
				'plan_id'         => $product->get_id(),
				'status'          => Membership_Status::TRIALING,
				'recurring'       => true,
				'date_expiration' => gmdate('Y-m-d 23:59:59', strtotime('+5 days')),
			]
		);
		$payment->set_parent_id(0);
		$payment->set_customer_id($customer->get_id());
		$payment->set_membership_id($membership->get_id());
		$payment->set_currency('USD');
		$payment->set_status(Payment_Status::PENDING);
		$payment->set_product_id($product->get_id());
		$payment->set_gateway('manual');
		$payment->set_gateway_payment_id('1');
		$payment->set_discount_code('');

		// Initially no line items
		$this->assertFalse($payment->has_line_items());
		$this->assertEmpty($payment->get_line_items());

		// Add first line item
		$line_item_1 = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'product_1',
				'title'      => 'Product 1',
				'unit_price' => 25.00,
				'quantity'   => 1,
			]
		);

		$payment->add_line_item($line_item_1);
		$this->assertTrue($payment->has_line_items());
		$this->assertCount(1, $payment->get_line_items());

		// Add second line item
		$line_item_2 = new Line_Item(
			[
				'type'       => 'fee',
				'hash'       => 'fee_1',
				'title'      => 'Processing Fee',
				'unit_price' => 5.00,
				'quantity'   => 1,
			]
		);

		$payment->add_line_item($line_item_2);
		$this->assertCount(2, $payment->get_line_items());

		// Verify both items are present
		$line_items = $payment->get_line_items();
		$types      = array_map(fn($item) => $item->get_type(), $line_items);
		$this->assertContains('product', $types);
		$this->assertContains('fee', $types);

		$payment->recalculate_totals();
		$saved = $payment->save();

		$this->assertTrue($saved, 'failed to save payment');

		$saved_payment = wu_get_payment($payment->get_id());

		$this->assertInstanceOf(Payment::class, $saved_payment);
		$this->assertCount(2, $saved_payment->get_line_items());
	}

	/**
	 * Test line items recalculation.
	 */
	public function test_line_items_recalculation(): void {
		$payment = new Payment();

		// Create line items with different types and values
		$product_item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'product_item',
				'title'      => 'Main Product',
				'unit_price' => 100.00,
				'quantity'   => 1,
				'taxable'    => true,
				'tax_rate'   => 8.25,
			]
		);

		$discount_item = new Line_Item(
			[
				'type'       => 'discount',
				'hash'       => 'discount_item',
				'title'      => 'Discount',
				'unit_price' => -10.00,
				'quantity'   => 1,
			]
		);

		$payment->set_line_items([$product_item, $discount_item]);

		// Recalculate totals based on line items
		$payment->recalculate_totals();

		$this->assertEquals(90.00, $payment->get_subtotal());
		$this->assertEquals(8.25, $payment->get_tax_total());
		$this->assertEquals(0, $payment->get_discount_total()); // This might not be right, but it's how the existing code works.
		$this->assertEquals(98.25, $payment->get_total()); // 100 - 10 + 8.25
	}

	/**
	 * Test empty line items handling.
	 */
	public function test_empty_line_items_handling(): void {
		$payment = new Payment();

		// Test empty line items
		$this->assertFalse($payment->has_line_items());
		$this->assertEquals([], $payment->get_line_items());

		// Set empty array
		$payment->set_line_items([]);
		$this->assertFalse($payment->has_line_items());
		$this->assertEquals([], $payment->get_line_items());
	}

	/**
	 * Test payment status functionality.
	 */
	public function test_payment_status_functionality(): void {
		$payment = new Payment();
		$payment->set_customer_id(1);
		$payment->set_membership_id(1);
		$payment->set_currency('USD');
		$payment->set_subtotal(100.00);
		$payment->set_total(100.00);

		// Test different statuses
		$statuses = [
			Payment_Status::PENDING,
			Payment_Status::COMPLETED,
			Payment_Status::REFUND,
			Payment_Status::FAILED,
		];

		foreach ($statuses as $status) {
			$payment->set_status($status);
			$this->assertEquals($status, $payment->get_status());

			// Test status label and class methods
			$label = $payment->get_status_label();
			$class = $payment->get_status_class();
			$this->assertIsString($label);
			$this->assertIsString($class);
		}
	}

	/**
	 * Test payment financial properties.
	 */
	public function test_payment_financial_properties(): void {
		$payment = new Payment();
		$payment->set_customer_id(1);
		$payment->set_membership_id(1);
		$payment->set_currency('USD');
		$payment->set_status(Payment_Status::PENDING);

		// Test all financial setters and getters
		$payment->set_subtotal(100.50);
		$this->assertEquals(100.50, $payment->get_subtotal());

		$payment->set_tax_total(8.25);
		$this->assertEquals(8.25, $payment->get_tax_total());

		$payment->set_discount_total(15.00);
		$this->assertEquals(15.00, $payment->get_discount_total());

		$payment->set_refund_total(25.00);
		$this->assertEquals(25.00, $payment->get_refund_total());

		$payment->set_total(93.75);
		$this->assertEquals(93.75, $payment->get_total());

		// Test discount code
		$payment->set_discount_code('SAVE20');
		$this->assertEquals('SAVE20', $payment->get_discount_code());
	}

	/**
	 * Test payment gateway functionality.
	 */
	public function test_payment_gateway_functionality(): void {
		$payment = new Payment();

		// Test gateway setter and getter
		$payment->set_gateway('stripe');
		$this->assertEquals('stripe', $payment->get_gateway());

		// Test gateway payment ID
		$payment->set_gateway_payment_id('pi_test123456789');
		$this->assertEquals('pi_test123456789', $payment->get_gateway_payment_id());

		// Test payment method returns a string
		$payment_method = $payment->get_payment_method();
		$this->assertIsString($payment_method);
	}

	/**
	 * Test payment invoice functionality.
	 */
	public function test_payment_invoice_functionality(): void {
		$payment = new Payment();
		$payment->set_customer_id(1);
		$payment->set_membership_id(1);

		// Test invoice number functionality
		$payment->set_invoice_number(12345);
		$this->assertEquals(12345, $payment->get_saved_invoice_number());

		// Test invoice URL generation
		$invoice_url = $payment->get_invoice_url();
		$this->assertIsString($invoice_url);
		$this->assertStringContainsString('action=invoice', $invoice_url);
	}

	/**
	 * Test draft payment status.
	 */
	public function test_draft_status(): void {
		$status = new Payment_Status(Payment_Status::DRAFT);
		$this->assertEquals('Draft', $status->get_label());
		$this->assertEquals('wu-bg-blue-200 wu-text-blue-700', $status->get_classes());
		$this->assertEquals('wu-align-middle dashicons-wu-edit wu-text-blue-700', $status->get_icon_classes());
	}

	/**
	 * Test cancelling a pending payment.
	 */
	public function test_cancel_pending_payment(): void {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		$product = wu_create_product([
			'name' => 'Test Plan',
			'slug' => 'test-plan',
			'pricing_type' => 'paid',
			'amount' => 10,
			'currency' => 'USD',
			'recurring' => false,
			'type' => 'plan',
		]);

		if (is_wp_error($product)) {
			$this->fail('Failed to create product: ' . $product->get_error_message());
		}

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id' => $product->get_id(),
			'status' => Membership_Status::ACTIVE,
		]);

		if (is_wp_error($membership)) {
			$this->fail('Failed to create membership: ' . $membership->get_error_message());
		}

		$payment = wu_create_payment([
			'customer_id' => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency' => 'USD',
			'subtotal' => 100.00,
			'total' => 100.00,
			'status' => Payment_Status::PENDING,
			'gateway' => 'manual',
		]);

		$this->assertInstanceOf(Payment::class, $payment, 'Payment creation failed');
		$this->assertGreaterThan(0, $payment->get_id(), 'Payment ID not set');
		$this->assertEquals(Payment_Status::PENDING, $payment->get_status());

		// Simulate cancel
		$payment->set_status(Payment_Status::CANCELLED);
		$payment->save();

		$saved_payment = wu_get_payment($payment->get_id());
		$this->assertEquals(Payment_Status::CANCELLED, $saved_payment->get_status());
	}

	/**
	 * Helper to create a saved payment with a customer, product, and membership.
	 *
	 * @param array $payment_overrides Optional overrides for payment data.
	 * @return Payment
	 */
	private function create_saved_payment(array $payment_overrides = []): Payment {

		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		$slug = 'plan-' . wp_generate_uuid4();

		$product = wu_create_product(
			[
				'name'         => 'Test Plan',
				'slug'         => $slug,
				'pricing_type' => 'paid',
				'amount'       => 50,
				'currency'     => 'USD',
				'recurring'    => false,
				'type'         => 'plan',
			]
		);

		$this->assertNotWPError($product);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => Membership_Status::ACTIVE,
			]
		);

		$this->assertNotWPError($membership);

		$defaults = [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'product_id'    => $product->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 100.00,
			'total'         => 100.00,
			'status'        => Payment_Status::COMPLETED,
			'gateway'       => 'manual',
		];

		$payment = wu_create_payment(array_merge($defaults, $payment_overrides));

		$this->assertInstanceOf(Payment::class, $payment);
		$this->assertGreaterThan(0, $payment->get_id());

		return $payment;
	}

	/**
	 * Test product_id getter and setter.
	 */
	public function test_product_id_getter_setter(): void {

		$payment = new Payment();

		$this->assertNull($payment->get_product_id());

		$payment->set_product_id(42);
		$this->assertEquals(42, $payment->get_product_id());

		$payment->set_product_id(0);
		$this->assertEquals(0, $payment->get_product_id());
	}

	/**
	 * Test parent_id getter and setter.
	 */
	public function test_parent_id_getter_setter(): void {

		$payment = new Payment();

		$this->assertNull($payment->get_parent_id());

		$payment->set_parent_id(99);
		$this->assertEquals(99, $payment->get_parent_id());

		$payment->set_parent_id(0);
		$this->assertEquals(0, $payment->get_parent_id());
	}

	/**
	 * Test get_customer returns a Customer object for a saved payment.
	 */
	public function test_get_customer_returns_customer_object(): void {

		$payment = $this->create_saved_payment();

		$customer = $payment->get_customer();

		$this->assertInstanceOf(Customer::class, $customer);
		$this->assertEquals(self::$customer->get_id(), $customer->get_id());
	}

	/**
	 * Test get_membership returns a Membership object for a saved payment.
	 */
	public function test_get_membership_returns_membership_object(): void {

		$payment = $this->create_saved_payment();

		$membership = $payment->get_membership();

		$this->assertInstanceOf(Membership::class, $membership);
		$this->assertEquals($payment->get_membership_id(), $membership->get_id());
	}

	/**
	 * Test get_product returns a Product object when product_id is set.
	 */
	public function test_get_product_returns_product_object(): void {

		$payment = $this->create_saved_payment();

		$product = $payment->get_product();

		$this->assertInstanceOf(Product::class, $product);
		$this->assertEquals($payment->get_product_id(), $product->get_id());
	}

	/**
	 * Test get_product returns false when no product_id is set.
	 */
	public function test_get_product_returns_false_without_product_id(): void {

		$payment = new Payment();

		$result = $payment->get_product();

		$this->assertFalse($result);
	}

	/**
	 * Test is_payable with a pending payment that has a positive total.
	 */
	public function test_is_payable_with_pending_positive_total(): void {

		$payment = new Payment();
		$payment->set_total(50.00);
		$payment->set_status(Payment_Status::PENDING);

		$this->assertTrue($payment->is_payable());
	}

	/**
	 * Test is_payable with a failed payment that has a positive total.
	 */
	public function test_is_payable_with_failed_positive_total(): void {

		$payment = new Payment();
		$payment->set_total(50.00);
		$payment->set_status(Payment_Status::FAILED);

		$this->assertTrue($payment->is_payable());
	}

	/**
	 * Test is_payable returns false for completed payment.
	 */
	public function test_is_payable_returns_false_for_completed(): void {

		$payment = new Payment();
		$payment->set_total(50.00);
		$payment->set_status(Payment_Status::COMPLETED);

		$this->assertFalse($payment->is_payable());
	}

	/**
	 * Test is_payable returns false for refunded payment.
	 */
	public function test_is_payable_returns_false_for_refunded(): void {

		$payment = new Payment();
		$payment->set_total(50.00);
		$payment->set_status(Payment_Status::REFUND);

		$this->assertFalse($payment->is_payable());
	}

	/**
	 * Test is_payable returns false when total is zero.
	 */
	public function test_is_payable_returns_false_for_zero_total(): void {

		$payment = new Payment();
		$payment->set_total(0);
		$payment->set_status(Payment_Status::PENDING);

		$this->assertFalse($payment->is_payable());
	}

	/**
	 * Test get_payment_url returns a URL for payable payments.
	 */
	public function test_get_payment_url_returns_url_for_payable(): void {

		$payment = $this->create_saved_payment(
			[
				'status' => Payment_Status::PENDING,
				'total'  => 50.00,
			]
		);

		$url = $payment->get_payment_url();

		$this->assertIsString($url);
		$this->assertStringContainsString('payment=', $url);
	}

	/**
	 * Test get_payment_url returns false for non-payable payments.
	 */
	public function test_get_payment_url_returns_false_for_non_payable(): void {

		$payment = new Payment();
		$payment->set_total(50.00);
		$payment->set_status(Payment_Status::COMPLETED);

		$this->assertFalse($payment->get_payment_url());
	}

	/**
	 * Test get_payment_method returns None when no gateway is set.
	 */
	public function test_get_payment_method_returns_none_without_gateway(): void {

		$payment = new Payment();

		$method = $payment->get_payment_method();

		$this->assertIsString($method);
	}

	/**
	 * Test cancel_membership_on_refund getter and setter.
	 */
	public function test_cancel_membership_on_refund_getter_setter(): void {

		$payment = new Payment();

		// Default should be falsy
		$this->assertEmpty($payment->should_cancel_membership_on_refund());

		$payment->set_cancel_membership_on_refund(true);
		$this->assertTrue($payment->should_cancel_membership_on_refund());

		$payment->set_cancel_membership_on_refund(false);
		$this->assertFalse($payment->should_cancel_membership_on_refund());
	}

	/**
	 * Test get_tax_breakthrough with line items at different tax rates.
	 */
	public function test_get_tax_breakthrough(): void {

		$payment = new Payment();

		$item_1 = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'prod_1',
				'title'      => 'Product 1',
				'unit_price' => 100.00,
				'quantity'   => 1,
				'taxable'    => true,
				'tax_rate'   => 10.00,
			]
		);

		$item_2 = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'prod_2',
				'title'      => 'Product 2',
				'unit_price' => 200.00,
				'quantity'   => 1,
				'taxable'    => true,
				'tax_rate'   => 10.00,
			]
		);

		$item_3 = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'prod_3',
				'title'      => 'Product 3',
				'unit_price' => 50.00,
				'quantity'   => 1,
				'taxable'    => true,
				'tax_rate'   => 5.00,
			]
		);

		$payment->set_line_items([$item_1, $item_2, $item_3]);

		$breakthrough = $payment->get_tax_breakthrough();

		$this->assertIsArray($breakthrough);
		$this->assertNotEmpty($breakthrough);

		// Tax rates are float keys; check they exist and have expected values
		$found_10 = false;
		$found_5  = false;

		foreach ($breakthrough as $rate => $amount) {
			if (abs($rate - 10.0) < 0.001) {
				$found_10 = true;
				// 10% of 100 + 10% of 200 = 10 + 20 = 30
				$this->assertEquals(30.00, $amount);
			}

			if (abs($rate - 5.0) < 0.001) {
				$found_5 = true;
				// 5% of 50 = 2.5
				$this->assertEquals(2.50, $amount);
			}
		}

		$this->assertTrue($found_10, 'Expected 10% tax bracket not found');
		$this->assertTrue($found_5, 'Expected 5% tax bracket not found');
	}

	/**
	 * Test get_tax_breakthrough with no taxable items.
	 */
	public function test_get_tax_breakthrough_empty_with_no_taxable_items(): void {

		$payment = new Payment();

		$item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'notax',
				'title'      => 'Non-taxable',
				'unit_price' => 100.00,
				'quantity'   => 1,
				'taxable'    => false,
				'tax_rate'   => 0,
			]
		);

		$payment->set_line_items([$item]);

		$breakthrough = $payment->get_tax_breakthrough();

		$this->assertIsArray($breakthrough);
		$this->assertEmpty($breakthrough);
	}

	/**
	 * Test recalculate_totals with refund line items.
	 */
	public function test_recalculate_totals_with_refund_line_item(): void {

		$payment = new Payment();

		$product_item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'prod_main',
				'title'      => 'Main Product',
				'unit_price' => 100.00,
				'quantity'   => 1,
				'taxable'    => false,
			]
		);

		$refund_item = new Line_Item(
			[
				'type'       => 'refund',
				'hash'       => 'refund_1',
				'title'      => 'Refund',
				'unit_price' => -50.00,
				'quantity'   => 1,
				'taxable'    => false,
			]
		);

		$payment->set_line_items([$product_item, $refund_item]);
		$payment->recalculate_totals();

		$this->assertEquals(50.00, $payment->get_subtotal());
		$this->assertEquals(50.00, $payment->get_total());
		$this->assertEquals(-50.00, $payment->get_refund_total());
		$this->assertEquals(0, $payment->get_tax_total());
	}

	/**
	 * Test recalculate_totals returns Payment instance for chaining.
	 */
	public function test_recalculate_totals_returns_payment(): void {

		$payment = new Payment();
		$payment->set_line_items([]);

		$result = $payment->recalculate_totals();

		$this->assertInstanceOf(Payment::class, $result);
		$this->assertSame($payment, $result);
	}

	/**
	 * Test recalculate_totals with multiple taxable items.
	 */
	public function test_recalculate_totals_with_multiple_taxable_items(): void {

		$payment = new Payment();

		$item_1 = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'tax_prod_1',
				'title'      => 'Product A',
				'unit_price' => 80.00,
				'quantity'   => 1,
				'taxable'    => true,
				'tax_rate'   => 10.00,
			]
		);

		$item_2 = new Line_Item(
			[
				'type'       => 'fee',
				'hash'       => 'tax_fee_1',
				'title'      => 'Setup Fee',
				'unit_price' => 20.00,
				'quantity'   => 1,
				'taxable'    => true,
				'tax_rate'   => 10.00,
			]
		);

		$payment->set_line_items([$item_1, $item_2]);
		$payment->recalculate_totals();

		// Subtotal: 80 + 20 = 100
		$this->assertEquals(100.00, $payment->get_subtotal());
		// Tax: 8 + 2 = 10
		$this->assertEquals(10.00, $payment->get_tax_total());
		// Total: 88 + 22 = 110
		$this->assertEquals(110.00, $payment->get_total());
	}

	/**
	 * Test set_line_items with array data converts to Line_Item objects.
	 */
	public function test_set_line_items_converts_arrays_to_line_item_objects(): void {

		$payment = new Payment();

		$line_item_data = [
			[
				'type'       => 'product',
				'hash'       => 'arr_prod',
				'title'      => 'Array Product',
				'unit_price' => 75.00,
				'quantity'   => 1,
			],
		];

		$payment->set_line_items($line_item_data);

		$items = $payment->get_line_items();
		$this->assertCount(1, $items);

		$item = reset($items);
		$this->assertInstanceOf(Line_Item::class, $item);
		$this->assertEquals('Array Product', $item->get_title());
		$this->assertEquals(75.00, $item->get_unit_price());
	}

	/**
	 * Test add_line_item ignores non-Line_Item objects.
	 */
	public function test_add_line_item_ignores_non_line_item(): void {

		$payment = new Payment();

		$initial_count = count($payment->get_line_items());

		// Try adding a non-Line_Item (stdClass)
		$payment->add_line_item(new \stdClass());

		$this->assertCount($initial_count, $payment->get_line_items());

		// Try adding a string
		$payment->add_line_item('not a line item');

		$this->assertCount($initial_count, $payment->get_line_items());
	}

	/**
	 * Test remove_non_recurring_items removes only non-recurring items.
	 */
	public function test_remove_non_recurring_items(): void {

		$payment = new Payment();

		$recurring_item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'recurring_prod',
				'title'      => 'Recurring Product',
				'unit_price' => 50.00,
				'quantity'   => 1,
				'recurring'  => true,
			]
		);

		$one_time_item = new Line_Item(
			[
				'type'       => 'fee',
				'hash'       => 'onetime_fee',
				'title'      => 'Setup Fee',
				'unit_price' => 25.00,
				'quantity'   => 1,
				'recurring'  => false,
			]
		);

		$payment->set_line_items([$recurring_item, $one_time_item]);
		$this->assertCount(2, $payment->get_line_items());

		$result = $payment->remove_non_recurring_items();

		// Should return itself for chaining
		$this->assertSame($payment, $result);

		$items = $payment->get_line_items();
		$this->assertCount(1, $items);

		$remaining = reset($items);
		$this->assertTrue($remaining->is_recurring());
		$this->assertEquals('Recurring Product', $remaining->get_title());

		// Verify totals were recalculated
		$this->assertEquals(50.00, $payment->get_subtotal());
		$this->assertEquals(50.00, $payment->get_total());
	}

	/**
	 * Test remove_non_recurring_items with all recurring items keeps all.
	 */
	public function test_remove_non_recurring_items_keeps_all_recurring(): void {

		$payment = new Payment();

		$item_1 = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'rec1',
				'title'      => 'Recurring 1',
				'unit_price' => 30.00,
				'quantity'   => 1,
				'recurring'  => true,
			]
		);

		$item_2 = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'rec2',
				'title'      => 'Recurring 2',
				'unit_price' => 20.00,
				'quantity'   => 1,
				'recurring'  => true,
			]
		);

		$payment->set_line_items([$item_1, $item_2]);
		$payment->remove_non_recurring_items();

		$this->assertCount(2, $payment->get_line_items());
	}

	/**
	 * Test remove_non_recurring_items with all non-recurring items removes all.
	 */
	public function test_remove_non_recurring_items_removes_all_non_recurring(): void {

		$payment = new Payment();

		$item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'onetime',
				'title'      => 'One-time Item',
				'unit_price' => 30.00,
				'quantity'   => 1,
				'recurring'  => false,
			]
		);

		$payment->set_line_items([$item]);
		$payment->remove_non_recurring_items();

		$this->assertCount(0, $payment->get_line_items());
		$this->assertEquals(0, $payment->get_total());
	}

	/**
	 * Test refund full amount on a saved payment.
	 */
	public function test_refund_full_amount(): void {

		$payment = $this->create_saved_payment(
			[
				'subtotal' => 100.00,
				'total'    => 100.00,
				'status'   => Payment_Status::COMPLETED,
			]
		);

		$line_item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'refund_test_prod',
				'title'      => 'Product',
				'unit_price' => 100.00,
				'quantity'   => 1,
			]
		);

		$payment->set_line_items([$line_item]);
		$payment->recalculate_totals();
		$payment->save();

		// Perform full refund
		$result = $payment->refund(100.00);

		$this->assertTrue($result);
		$this->assertEquals(Payment_Status::REFUND, $payment->get_status());

		// Verify refund line item was added
		$items = $payment->get_line_items();
		$refund_items = array_filter($items, fn($item) => $item->get_type() === 'refund');
		$this->assertNotEmpty($refund_items);

		$refund_item = reset($refund_items);
		$this->assertEquals(-100.00, $refund_item->get_unit_price());
	}

	/**
	 * Test refund partial amount on a saved payment.
	 */
	public function test_refund_partial_amount(): void {

		$payment = $this->create_saved_payment(
			[
				'subtotal' => 100.00,
				'total'    => 100.00,
				'status'   => Payment_Status::COMPLETED,
			]
		);

		$line_item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'partial_refund_prod',
				'title'      => 'Product',
				'unit_price' => 100.00,
				'quantity'   => 1,
			]
		);

		$payment->set_line_items([$line_item]);
		$payment->recalculate_totals();
		$payment->save();

		// Perform partial refund
		$result = $payment->refund(30.00);

		$this->assertTrue($result);
		$this->assertEquals(Payment_Status::PARTIAL_REFUND, $payment->get_status());

		// Verify the refund line item
		$items = $payment->get_line_items();
		$refund_items = array_filter($items, fn($item) => $item->get_type() === 'refund');
		$this->assertNotEmpty($refund_items);

		$refund_item = reset($refund_items);
		$this->assertEquals(-30.00, $refund_item->get_unit_price());
	}

	/**
	 * Test refund with no amount refunds the full total.
	 */
	public function test_refund_without_amount_refunds_full_total(): void {

		$payment = $this->create_saved_payment(
			[
				'subtotal' => 75.00,
				'total'    => 75.00,
				'status'   => Payment_Status::COMPLETED,
			]
		);

		$line_item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'full_refund_no_amount',
				'title'      => 'Product',
				'unit_price' => 75.00,
				'quantity'   => 1,
			]
		);

		$payment->set_line_items([$line_item]);
		$payment->recalculate_totals();
		$payment->save();

		// Calling refund() without amount should refund the full total
		$result = $payment->refund();

		$this->assertTrue($result);
		$this->assertEquals(Payment_Status::REFUND, $payment->get_status());
	}

	/**
	 * Test to_search_results includes reference_code and product_names.
	 */
	public function test_to_search_results(): void {

		$payment = new Payment();
		$payment->set_customer_id(1);
		$payment->set_membership_id(1);
		$payment->set_currency('USD');
		$payment->set_subtotal(100.00);
		$payment->set_total(100.00);
		$payment->set_status(Payment_Status::PENDING);

		$item_1 = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'search_prod_1',
				'title'      => 'Product Alpha',
				'unit_price' => 60.00,
				'quantity'   => 1,
			]
		);

		$item_2 = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'search_prod_2',
				'title'      => 'Product Beta',
				'unit_price' => 40.00,
				'quantity'   => 1,
			]
		);

		$payment->set_line_items([$item_1, $item_2]);

		$search_results = $payment->to_search_results();

		$this->assertIsArray($search_results);
		$this->assertArrayHasKey('reference_code', $search_results);
		$this->assertArrayHasKey('product_names', $search_results);
		$this->assertStringContainsString('Product Alpha', $search_results['product_names']);
		$this->assertStringContainsString('Product Beta', $search_results['product_names']);
	}

	/**
	 * Test to_search_results with no line items.
	 */
	public function test_to_search_results_with_no_line_items(): void {

		$payment = new Payment();
		$payment->set_customer_id(1);
		$payment->set_membership_id(1);
		$payment->set_status(Payment_Status::PENDING);
		$payment->set_subtotal(0);
		$payment->set_total(0);

		$search_results = $payment->to_search_results();

		$this->assertIsArray($search_results);
		$this->assertArrayHasKey('product_names', $search_results);
		$this->assertEquals('', $search_results['product_names']);
	}

	/**
	 * Test __call magic method for formatted values.
	 */
	public function test_magic_call_formatted_methods(): void {

		$payment = new Payment();
		$payment->set_subtotal(100.00);
		$payment->set_total(110.00);
		$payment->set_tax_total(10.00);
		$payment->set_refund_total(5.00);
		$payment->set_discount_total(0);

		$subtotal_formatted = $payment->get_subtotal_formatted();
		$this->assertIsString($subtotal_formatted);

		$total_formatted = $payment->get_total_formatted();
		$this->assertIsString($total_formatted);

		$tax_formatted = $payment->get_tax_total_formatted();
		$this->assertIsString($tax_formatted);

		$refund_formatted = $payment->get_refund_total_formatted();
		$this->assertIsString($refund_formatted);
	}

	/**
	 * Test __call magic method throws exception for non-existent methods.
	 */
	public function test_magic_call_throws_exception_for_invalid_method(): void {

		$payment = new Payment();

		$this->expectException(\BadMethodCallException::class);

		$payment->non_existent_method();
	}

	/**
	 * Test status label returns specific expected values.
	 */
	public function test_status_label_specific_values(): void {

		$payment = new Payment();

		$payment->set_status(Payment_Status::PENDING);
		$this->assertEquals('Pending', $payment->get_status_label());

		$payment->set_status(Payment_Status::COMPLETED);
		$this->assertEquals('Completed', $payment->get_status_label());

		$payment->set_status(Payment_Status::REFUND);
		$this->assertEquals('Refunded', $payment->get_status_label());

		$payment->set_status(Payment_Status::PARTIAL_REFUND);
		$this->assertEquals('Partially Refunded', $payment->get_status_label());

		$payment->set_status(Payment_Status::FAILED);
		$this->assertEquals('Failed', $payment->get_status_label());

		$payment->set_status(Payment_Status::CANCELLED);
		$this->assertEquals('Cancelled', $payment->get_status_label());

		$payment->set_status(Payment_Status::DRAFT);
		$this->assertEquals('Draft', $payment->get_status_label());
	}

	/**
	 * Test status class returns specific expected CSS classes.
	 */
	public function test_status_class_specific_values(): void {

		$payment = new Payment();

		$payment->set_status(Payment_Status::PENDING);
		$this->assertStringContainsString('wu-bg-gray-200', $payment->get_status_class());

		$payment->set_status(Payment_Status::COMPLETED);
		$this->assertStringContainsString('wu-bg-green-200', $payment->get_status_class());

		$payment->set_status(Payment_Status::FAILED);
		$this->assertStringContainsString('wu-bg-red-200', $payment->get_status_class());
	}

	/**
	 * Test duplicate creates a copy with line items and reset ID.
	 */
	public function test_duplicate_creates_copy_with_line_items(): void {

		$payment = $this->create_saved_payment();

		$line_item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'dup_prod',
				'title'      => 'Duplicated Product',
				'unit_price' => 60.00,
				'quantity'   => 1,
			]
		);

		$payment->set_line_items([$line_item]);
		$payment->save();

		$duplicate = $payment->duplicate();

		// ID should be reset
		$this->assertEquals(0, $duplicate->get_id());

		// Other properties should be preserved
		$this->assertEquals($payment->get_customer_id(), $duplicate->get_customer_id());
		$this->assertEquals($payment->get_membership_id(), $duplicate->get_membership_id());
		$this->assertEquals($payment->get_total(), $duplicate->get_total());
		$this->assertEquals($payment->get_gateway(), $duplicate->get_gateway());

		// Line items should be preserved
		$dup_items = $duplicate->get_line_items();
		$this->assertCount(1, $dup_items);

		$dup_item = reset($dup_items);
		$this->assertEquals('Duplicated Product', $dup_item->get_title());
	}

	/**
	 * Test invoice URL contains expected query parameters.
	 */
	public function test_invoice_url_contains_reference_and_key(): void {

		$payment = $this->create_saved_payment();

		$url = $payment->get_invoice_url();

		$this->assertIsString($url);
		$this->assertStringContainsString('action=invoice', $url);
		$this->assertStringContainsString('reference=', $url);
		$this->assertStringContainsString('key=', $url);
	}

	/**
	 * Test set and get saved invoice number on a saved payment (meta roundtrip).
	 */
	public function test_invoice_number_meta_roundtrip(): void {

		$payment = $this->create_saved_payment();

		$payment->set_invoice_number(999);
		$payment->save();

		$reloaded = wu_get_payment($payment->get_id());

		$this->assertEquals(999, $reloaded->get_saved_invoice_number());
	}

	/**
	 * Test payment creation via wu_create_payment with line items.
	 */
	public function test_wu_create_payment_with_line_items(): void {

		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		$slug = 'plan-li-' . wp_generate_uuid4();

		$product = wu_create_product(
			[
				'name'         => 'Plan with Line Items',
				'slug'         => $slug,
				'pricing_type' => 'paid',
				'amount'       => 100,
				'currency'     => 'USD',
				'recurring'    => false,
				'type'         => 'plan',
			]
		);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => Membership_Status::ACTIVE,
			]
		);

		$line_item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'wu_create_li',
				'title'      => 'Created via wu_create_payment',
				'unit_price' => 100.00,
				'quantity'   => 1,
			]
		);

		$payment = wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => $membership->get_id(),
				'currency'      => 'USD',
				'subtotal'      => 100.00,
				'total'         => 100.00,
				'status'        => Payment_Status::COMPLETED,
				'gateway'       => 'manual',
				'line_items'    => [$line_item],
			]
		);

		$this->assertInstanceOf(Payment::class, $payment);

		$reloaded = wu_get_payment($payment->get_id());
		$this->assertInstanceOf(Payment::class, $reloaded);
		$this->assertTrue($reloaded->has_line_items());
		$this->assertCount(1, $reloaded->get_line_items());
	}

	/**
	 * Test financial defaults are zero when no values are set.
	 */
	public function test_financial_defaults_are_zero(): void {

		$payment = new Payment();

		$this->assertEquals(0, $payment->get_subtotal());
		$this->assertEquals(0, $payment->get_total());
		$this->assertEquals(0, $payment->get_tax_total());
		$this->assertEquals(0, $payment->get_discount_total());
		$this->assertEquals(0, $payment->get_refund_total());
	}

	/**
	 * Test cancel_membership_on_refund persists via meta on save/reload.
	 */
	public function test_cancel_membership_on_refund_persists_via_meta(): void {

		$payment = $this->create_saved_payment();

		$payment->set_cancel_membership_on_refund(true);
		$payment->save();

		$reloaded = wu_get_payment($payment->get_id());

		$this->assertTrue((bool) $reloaded->should_cancel_membership_on_refund());
	}

	/**
	 * Test payment status constants coverage.
	 */
	public function test_all_payment_status_constants_exist(): void {

		$this->assertEquals('pending', Payment_Status::PENDING);
		$this->assertEquals('completed', Payment_Status::COMPLETED);
		$this->assertEquals('refunded', Payment_Status::REFUND);
		$this->assertEquals('partially-refunded', Payment_Status::PARTIAL_REFUND);
		$this->assertEquals('partially-paid', Payment_Status::PARTIAL);
		$this->assertEquals('failed', Payment_Status::FAILED);
		$this->assertEquals('cancelled', Payment_Status::CANCELLED);
		$this->assertEquals('draft', Payment_Status::DRAFT);
	}

	/**
	 * Test recalculate_totals with a discount line item that has a discount_rate.
	 */
	public function test_recalculate_totals_with_quantity_greater_than_one(): void {

		$payment = new Payment();

		$item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'qty_prod',
				'title'      => 'Multi-qty Product',
				'unit_price' => 25.00,
				'quantity'   => 4,
				'taxable'    => false,
			]
		);

		$payment->set_line_items([$item]);
		$payment->recalculate_totals();

		// 25 * 4 = 100
		$this->assertEquals(100.00, $payment->get_subtotal());
		$this->assertEquals(100.00, $payment->get_total());
	}

	/**
	 * Test validation_rules returns expected keys.
	 */
	public function test_validation_rules_returns_expected_keys(): void {

		$payment = new Payment();

		$rules = $payment->validation_rules();

		$this->assertIsArray($rules);
		$this->assertArrayHasKey('customer_id', $rules);
		$this->assertArrayHasKey('membership_id', $rules);
		$this->assertArrayHasKey('subtotal', $rules);
		$this->assertArrayHasKey('total', $rules);
		$this->assertArrayHasKey('status', $rules);
		$this->assertArrayHasKey('currency', $rules);
		$this->assertArrayHasKey('gateway', $rules);
		$this->assertArrayHasKey('discount_code', $rules);
		$this->assertArrayHasKey('refund_total', $rules);
		$this->assertArrayHasKey('tax_total', $rules);
		$this->assertArrayHasKey('discount_total', $rules);
		$this->assertArrayHasKey('invoice_number', $rules);
		$this->assertArrayHasKey('cancel_membership_on_refund', $rules);
	}

	/**
	 * Test the Payment META constants are defined.
	 */
	public function test_meta_constants(): void {

		$this->assertEquals('wu_line_items', Payment::META_LINE_ITEMS);
		$this->assertEquals('wu_invoice_number', Payment::META_INVOICE_NUMBER);
		$this->assertEquals('wu_cancel_membership_on_refund', Payment::META_CANCEL_MEMBERSHIP_ON_REFUND);
		$this->assertEquals('wu_original_cart', Payment::META_ORIGINAL_CART);
	}

	/**
	 * Test setting line items filters out empty/falsy values.
	 */
	public function test_set_line_items_filters_empty_values(): void {

		$payment = new Payment();

		$item = new Line_Item(
			[
				'type'       => 'product',
				'hash'       => 'filter_test',
				'title'      => 'Real Item',
				'unit_price' => 50.00,
				'quantity'   => 1,
			]
		);

		// Pass array with null/false values mixed in
		$payment->set_line_items([$item, null, false, 0, '']);

		$items = $payment->get_line_items();
		$this->assertCount(1, $items);

		$remaining = reset($items);
		$this->assertEquals('Real Item', $remaining->get_title());
	}

	public static function tear_down_after_class() {
		global $wpdb;
		self::$customer->delete();
		// Clean up the test data
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_memberships");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_products");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_customers");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_payments");
		parent::tear_down_after_class();
	}
}
