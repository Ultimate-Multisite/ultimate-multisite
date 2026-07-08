<?php
/**
 * Enable the Stripe gateway with test API keys for e2e testing.
 * Usage: wp eval-file setup-stripe-gateway.php <pk_key> <sk_key>
 */

$pk_key = isset($args[0]) ? $args[0] : '';
$sk_key = isset($args[1]) ? $args[1] : '';

if (empty($pk_key) || empty($sk_key)) {
	echo wp_json_encode(['error' => 'Missing Stripe test keys. Pass pk_key and sk_key as arguments.']);
	return;
}

// Enable sandbox mode
wu_save_setting('stripe_sandbox_mode', true);

// Set test keys
wu_save_setting('stripe_test_pk_key', $pk_key);
wu_save_setting('stripe_test_sk_key', $sk_key);

// Show direct keys (not OAuth)
wu_save_setting('stripe_show_direct_keys', true);

// Add stripe to active gateways while keeping existing ones
$active_gateways = (array) wu_get_setting('active_gateways', []);

if (! in_array('stripe', $active_gateways, true)) {
	$active_gateways[] = 'stripe';
}

wu_save_setting('active_gateways', $active_gateways);

echo wp_json_encode(
	[
		'success'         => true,
		'active_gateways' => wu_get_setting('active_gateways', []),
		'sandbox_mode'    => wu_get_setting('stripe_sandbox_mode', false),
		'pk_key_set'      => ! empty(wu_get_setting('stripe_test_pk_key')),
		'sk_key_set'      => ! empty(wu_get_setting('stripe_test_sk_key')),
	]
);
