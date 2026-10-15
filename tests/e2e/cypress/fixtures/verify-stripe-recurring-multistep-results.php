<?php
/**
 * Verify the recurring multi-step Stripe checkout regression results.
 *
 * Usage: wp eval-file verify-stripe-recurring-multistep-results.php <username> <site_path>
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

defined('ABSPATH') || exit;

$username           = isset($args[0]) ? sanitize_user($args[0]) : '';
$expected_site_path = isset($args[1]) ? '/' . trim(sanitize_text_field($args[1]), '/') . '/' : '';
$result             = [
	'success'                 => false,
	'user_found'              => false,
	'customer_found'          => false,
	'membership_found'        => false,
	'membership_status'       => '',
	'membership_gateway'      => '',
	'membership_recurring'    => false,
	'gateway_customer_id'     => '',
	'gateway_subscription_id' => '',
	'pending_site_exists'     => false,
	'payment_found'           => false,
	'payment_status'          => '',
	'payment_gateway'         => '',
	'gateway_payment_id'      => '',
	'site_created'            => false,
	'site_path'               => '',
	'site_type'               => '',
];

$user = $username ? get_user_by('login', $username) : false;

if ( ! $user) {
	echo wp_json_encode($result);
	return;
}

$result['user_found'] = true;
$customer             = wu_get_customer_by_user_id($user->ID);

if ( ! $customer) {
	echo wp_json_encode($result);
	return;
}

$result['customer_found'] = true;
$membership               = false;

foreach ($customer->get_memberships() as $candidate) {
	$product = wu_get_product($candidate->get_plan_id());

	if ($product && 'issue-1968-recurring-plan' === $product->get_slug()) {
		$membership = $candidate;
		break;
	}
}

if ($membership) {
	$result['membership_found']        = true;
	$result['membership_status']       = $membership->get_status();
	$result['membership_gateway']      = $membership->get_gateway();
	$result['membership_recurring']    = $membership->is_recurring();
	$result['gateway_customer_id']     = $membership->get_gateway_customer_id();
	$result['gateway_subscription_id'] = $membership->get_gateway_subscription_id();
	$result['pending_site_exists']     = (bool) $membership->get_pending_site();

	foreach ($customer->get_payments() as $candidate) {
		if ($membership->get_id() === (int) $candidate->get_membership_id()) {
			$result['payment_found']      = true;
			$result['payment_status']     = $candidate->get_status();
			$result['payment_gateway']    = $candidate->get_gateway();
			$result['gateway_payment_id'] = $candidate->get_gateway_payment_id();
			break;
		}
	}
}

foreach ($customer->get_sites(['type__in' => ['customer_owned']]) as $site) {
	if ($expected_site_path === $site->get_path()) {
		$result['site_created'] = true;
		$result['site_path']    = $site->get_path();
		$result['site_type']    = $site->get_type();
		break;
	}
}

$result['success'] = $result['membership_found']
	&& $result['payment_found']
	&& $result['site_created']
	&& ! $result['pending_site_exists'];

echo wp_json_encode($result);
