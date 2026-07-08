<?php
/**
 * Set up Stripe renewal test using Test Clocks.
 *
 * Creates a Stripe Test Clock, Customer, Product, Price, Subscription,
 * plus local UM records (customer, membership, payment).
 *
 * Usage: wp eval-file setup-stripe-renewal-test.php <sk_key>
 */

$sk_key = isset($args[0]) ? $args[0] : '';

if (empty($sk_key)) {
	echo wp_json_encode(['error' => 'Missing Stripe secret key.']);
	return;
}

try {
	$stripe = new \Stripe\StripeClient($sk_key);

	// 1. Create Test Clock frozen at now
	$now        = time();
	$test_clock = $stripe->testHelpers->testClocks->create(['frozen_time' => $now]);

	// 2. Create Stripe Customer attached to test clock
	$s_customer = $stripe->customers->create([
		'name'       => 'UM Renewal Test ' . $now,
		'email'      => 'renewal-test-' . $now . '@test.com',
		'test_clock' => $test_clock->id,
	]);

	// 3. Create Stripe Product + Price ($29.99/month)
	$s_product = $stripe->products->create([
		'name' => 'UM Renewal Test Plan ' . $now,
	]);

	$s_price = $stripe->prices->create([
		'product'     => $s_product->id,
		'unit_amount' => 2999,
		'currency'    => 'usd',
		'recurring'   => ['interval' => 'month'],
	]);

	// 4. Create PaymentMethod and attach to customer
	$pm = $stripe->paymentMethods->create([
		'type' => 'card',
		'card' => ['token' => 'tok_visa'],
	]);

	$stripe->paymentMethods->attach($pm->id, ['customer' => $s_customer->id]);

	$stripe->customers->update($s_customer->id, [
		'invoice_settings' => ['default_payment_method' => $pm->id],
	]);

	// 5. Create Subscription
	$subscription = $stripe->subscriptions->create([
		'customer'               => $s_customer->id,
		'items'                  => [['price' => $s_price->id]],
		'default_payment_method' => $pm->id,
	]);

	$current_period_end = $subscription->items->data[0]->current_period_end ?? $subscription->current_period_end;

	// 6. Create local UM records

	// Create a WP user for the customer
	$username = 'renewaltest' . $now;
	$email    = 'renewal-test-' . $now . '@test.com';
	$user_id  = wpmu_create_user($username, 'TestPassword123!', $email);

	if (! $user_id) {
		echo wp_json_encode(['error' => 'Failed to create WP user.']);
		return;
	}

	// Get or create the test product (reuse existing "Test Plan" or create one)
	$products = WP_Ultimo\Models\Product::query([
		'number' => 1,
		'search' => 'Test Plan',
	]);

	if (! empty($products)) {
		$product = $products[0];
	} else {
		$product = new WP_Ultimo\Models\Product();
		$product->set_name('Test Plan');
		$product->set_slug('test-plan-renewal');
		$product->set_amount(29.99);
		$product->set_duration(1);
		$product->set_duration_unit('month');
		$product->set_type('plan');
		$product->set_active(true);
		$product->save();
	}

	// Create UM Customer
	$customer = wu_create_customer([
		'user_id'  => $user_id,
		'email'    => $email,
		'username' => $username,
	]);

	if (is_wp_error($customer)) {
		echo wp_json_encode(['error' => 'Failed to create customer: ' . $customer->get_error_message()]);
		return;
	}

	// Calculate expiration from Stripe's period end (same formula as webhook handler)
	$renewal_date = new \DateTime();
	$renewal_date->setTimestamp($current_period_end);
	$renewal_date->setTime(23, 59, 59);

	$stripe_estimated_charge = $current_period_end + (2 * HOUR_IN_SECONDS);

	if ($stripe_estimated_charge > $renewal_date->getTimestamp()) {
		$renewal_date->setTimestamp($stripe_estimated_charge);
	}

	$expiration = $renewal_date->format('Y-m-d H:i:s');

	// Create UM Membership
	$membership = wu_create_membership([
		'customer_id'             => $customer->get_id(),
		'plan_id'                 => $product->get_id(),
		'status'                  => 'active',
		'gateway'                 => 'stripe',
		'gateway_customer_id'     => $s_customer->id,
		'gateway_subscription_id' => $subscription->id,
		'amount'                  => 29.99,
		'recurring'               => true,
		'auto_renew'              => true,
		'duration'                => 1,
		'duration_unit'           => 'month',
		'times_billed'            => 1,
		'date_expiration'         => $expiration,
		'currency'                => 'USD',
	]);

	if (is_wp_error($membership)) {
		echo wp_json_encode(['error' => 'Failed to create membership: ' . $membership->get_error_message()]);
		return;
	}

	// Create initial Payment (completed)
	$initial_invoice = $subscription->latest_invoice;

	if (is_string($initial_invoice)) {
		$invoice_obj    = $stripe->invoices->retrieve($initial_invoice);
		$gateway_pay_id = $invoice_obj->charge ?? $invoice_obj->payment_intent ?? $initial_invoice;
	} else {
		$gateway_pay_id = $initial_invoice->charge ?? $initial_invoice->payment_intent ?? '';
	}

	$payment = wu_create_payment([
		'customer_id'        => $customer->get_id(),
		'membership_id'      => $membership->get_id(),
		'status'             => 'completed',
		'gateway'            => 'stripe',
		'gateway_payment_id' => is_object($gateway_pay_id) ? $gateway_pay_id->id : (string) $gateway_pay_id,
		'subtotal'           => 29.99,
		'total'              => 29.99,
		'currency'           => 'USD',
	]);

	if (is_wp_error($payment)) {
		echo wp_json_encode(['error' => 'Failed to create payment: ' . $payment->get_error_message()]);
		return;
	}

	echo wp_json_encode([
		'success'              => true,
		'test_clock_id'        => $test_clock->id,
		'stripe_customer_id'   => $s_customer->id,
		'subscription_id'      => $subscription->id,
		'stripe_product_id'    => $s_product->id,
		'stripe_price_id'      => $s_price->id,
		'current_period_end'   => $current_period_end,
		'um_customer_id'       => $customer->get_id(),
		'um_membership_id'     => $membership->get_id(),
		'um_payment_id'        => $payment->get_id(),
		'initial_times_billed' => $membership->get_times_billed(),
		'expiration'           => $expiration,
	]);
} catch (\Exception $e) {
	echo wp_json_encode([
		'error' => $e->getMessage(),
		'code'  => $e->getCode(),
	]);
}
