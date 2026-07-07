<?php
/**
 * Process a Stripe subscription renewal by replicating the webhook handler logic.
 *
 * Finds the renewal invoice, creates a renewal payment, and renews the membership
 * using the same expiration date formula as class-base-stripe-gateway.php.
 *
 * Usage: wp eval-file process-stripe-renewal.php <sk_key> <subscription_id> <membership_id>
 */

$sk_key          = isset($args[0]) ? $args[0] : '';
$subscription_id = isset($args[1]) ? $args[1] : '';
$membership_id   = isset($args[2]) ? (int) $args[2] : 0;

if (empty($sk_key) || empty($subscription_id) || empty($membership_id)) {
	echo wp_json_encode(['error' => 'Missing arguments. Expected: sk_key, subscription_id, membership_id']);
	return;
}

try {
	$stripe = new \Stripe\StripeClient($sk_key);

	// 1. Retrieve subscription to get current period info
	$subscription = $stripe->subscriptions->retrieve($subscription_id);

	// 2. List invoices for this subscription and find the renewal invoice
	$invoices = $stripe->invoices->all([
		'subscription' => $subscription_id,
		'limit'        => 10,
	]);

	$renewal_invoice = null;

	foreach ($invoices->data as $invoice) {
		if ($invoice->billing_reason === 'subscription_cycle') {
			$renewal_invoice = $invoice;
			break;
		}
	}

	if (! $renewal_invoice) {
		echo wp_json_encode([
			'error'           => 'No renewal invoice found.',
			'invoice_count'   => count($invoices->data),
			'billing_reasons' => array_map(function ($inv) {
				return $inv->billing_reason;
			}, $invoices->data),
		]);
		return;
	}

	// 3. If the renewal invoice is still open (test clock timing), pay it explicitly
	if ($renewal_invoice->status === 'open' || $renewal_invoice->status === 'draft') {
		if ($renewal_invoice->status === 'draft') {
			$renewal_invoice = $stripe->invoices->finalizeInvoice($renewal_invoice->id);
		}

		$renewal_invoice = $stripe->invoices->pay($renewal_invoice->id);
	}

	// 4. Get charge/payment identifier from the paid renewal invoice
	// Test clock invoices don't link charge/payment_intent, so fall back to invoice ID
	$charge_id = $renewal_invoice->charge ?? $renewal_invoice->payment_intent ?? $renewal_invoice->id;

	// 5. Calculate expiration using the same formula as the webhook handler
	// (class-base-stripe-gateway.php lines 2546-2567)
	$end_timestamp = null;

	foreach ($subscription->items->data as $item) {
		$end_timestamp = $item->current_period_end;
		break;
	}

	if (! $end_timestamp) {
		$end_timestamp = $subscription->current_period_end;
	}

	$renewal_date = new \DateTime();
	$renewal_date->setTimestamp($end_timestamp);
	$renewal_date->setTime(23, 59, 59);

	$stripe_estimated_charge_timestamp = $end_timestamp + (2 * HOUR_IN_SECONDS);

	if ($stripe_estimated_charge_timestamp > $renewal_date->getTimestamp()) {
		$renewal_date->setTimestamp($stripe_estimated_charge_timestamp);
	}

	$expiration = $renewal_date->format('Y-m-d H:i:s');

	// 6. Get membership and customer
	$membership = wu_get_membership($membership_id);

	if (! $membership) {
		echo wp_json_encode(['error' => 'Membership not found: ' . $membership_id]);
		return;
	}

	$customer_id = $membership->get_customer_id();

	// 7. Get invoice total (convert from Stripe cents)
	$currency_multiplier = function_exists('wu_stripe_get_currency_multiplier')
		? wu_stripe_get_currency_multiplier(strtoupper($renewal_invoice->currency))
		: 100;

	$total = $renewal_invoice->amount_paid / $currency_multiplier;

	// 8. Create renewal payment
	$payment = wu_create_payment([
		'customer_id'        => $customer_id,
		'membership_id'      => $membership_id,
		'status'             => 'completed',
		'gateway'            => 'stripe',
		'gateway_payment_id' => (string) $charge_id,
		'subtotal'           => $total,
		'total'              => $total,
		'currency'           => strtoupper($renewal_invoice->currency),
	]);

	if (is_wp_error($payment)) {
		echo wp_json_encode(['error' => 'Failed to create payment: ' . $payment->get_error_message()]);
		return;
	}

	// 9. Renew membership (replicate webhook handler logic)
	$membership->add_to_times_billed(1);
	$membership->renew($membership->is_recurring(), 'active', $expiration);

	echo wp_json_encode([
		'success'            => true,
		'renewal_invoice_id' => $renewal_invoice->id,
		'charge_id'          => $charge_id,
		'renewal_payment_id' => $payment->get_id(),
		'renewal_total'      => $total,
		'new_expiration'     => $expiration,
		'new_times_billed'   => $membership->get_times_billed(),
		'membership_status'  => $membership->get_status(),
		'current_period_end' => $end_timestamp,
	]);
} catch (\Exception $e) {
	echo wp_json_encode([
		'error' => $e->getMessage(),
		'code'  => $e->getCode(),
	]);
}
