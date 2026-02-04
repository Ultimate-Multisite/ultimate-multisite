<?php
/**
 * Verify free trial checkout results: UM payment, membership, and site.
 * Outputs a JSON object with the status of each entity.
 */

// UM payment
$payments          = WP_Ultimo\Models\Payment::query(
	[
		'number'  => 1,
		'orderby' => 'id',
		'order'   => 'DESC',
	]
);
$um_payment_status = $payments ? $payments[0]->get_status() : 'no-payments';

// UM membership
$memberships             = WP_Ultimo\Models\Membership::query(
	[
		'number'  => 1,
		'orderby' => 'id',
		'order'   => 'DESC',
	]
);
$um_membership_status    = $memberships ? $memberships[0]->get_status() : 'no-memberships';
$um_membership_trial_end = $memberships ? (string) $memberships[0]->get_date_trial_end() : '';

// UM sites
$sites        = WP_Ultimo\Models\Site::query(['type__in' => ['customer_owned']]);
$um_site_type = $sites ? $sites[0]->get_type() : 'no-sites';

echo wp_json_encode(
	[
		'um_payment_status'       => $um_payment_status,
		'um_membership_status'    => $um_membership_status,
		'um_membership_trial_end' => $um_membership_trial_end,
		'um_site_type'            => $um_site_type,
	]
);
