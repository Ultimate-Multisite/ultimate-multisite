<?php
/**
 * Verify manual gateway checkout results: UM payment, membership, and site.
 * Outputs a JSON object with the status of each entity.
 */

// UM payment
$payments           = WP_Ultimo\Models\Payment::query(
	[
		'number'  => 1,
		'orderby' => 'id',
		'order'   => 'DESC',
	]
);
$um_payment_status  = $payments ? $payments[0]->get_status() : 'no-payments';
$um_payment_gateway = $payments ? $payments[0]->get_gateway() : 'none';
$um_payment_total   = $payments ? (float) $payments[0]->get_total() : 0;

// UM membership
$memberships          = WP_Ultimo\Models\Membership::query(
	[
		'number'  => 1,
		'orderby' => 'id',
		'order'   => 'DESC',
	]
);
$um_membership_status = $memberships ? $memberships[0]->get_status() : 'no-memberships';

// UM sites
$sites         = WP_Ultimo\Models\Site::query(['type__in' => ['customer_owned']]);
$um_site_count = count($sites);
$um_site_type  = $sites ? $sites[0]->get_type() : 'no-sites';

echo wp_json_encode(
	[
		'um_payment_status'    => $um_payment_status,
		'um_payment_gateway'   => $um_payment_gateway,
		'um_payment_total'     => $um_payment_total,
		'um_membership_status' => $um_membership_status,
		'um_site_count'        => $um_site_count,
		'um_site_type'         => $um_site_type,
	]
);
