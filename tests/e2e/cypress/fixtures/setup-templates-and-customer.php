<?php
/**
 * Fixture: create site templates and a customer-owned subsite for the
 * template-switching E2E test.
 *
 * Why a fixture rather than the checkout form: the customer-reported
 * "No puedes cambiar la plantilla de este sitio" bug occurs against an
 * existing customer/site pair (the customer is already provisioned and
 * trying to change templates from their customer panel). Driving signup
 * through the checkout form for every E2E run would add 30+ seconds of
 * flaky form interaction on top of work that can be done deterministically
 * via WP-CLI in <1s.
 *
 * The fixture:
 *
 *   - Creates two site templates (the customer needs at least one
 *     template to switch *to* that is not their current one). Marks both
 *     active, non-archived, non-deleted, non-spam so the customer-panel
 *     grid lists them (mirrors the filter in
 *     Template_Switching_Element::output()).
 *   - Creates a WP user and a linked Ultimate Multisite Customer record.
 *   - Creates a customer-owned subsite, sets `wu_customer_id` blogmeta to
 *     the customer's id, sets `wu_template_id` blogmeta to the FIRST
 *     template (so the SECOND template is a legitimate switch target),
 *     and registers the WP user as the site's admin.
 *
 * Echoes a JSON payload Cypress can parse to drive the rest of the test
 * (customer credentials, subsite path, template ids).
 */

defined( 'ABSPATH' ) || exit;

$suffix = wp_generate_password( 6, false, false );

// --- Templates ---------------------------------------------------------
$template_one_id = wpmu_create_blog(
	get_current_site()->domain,
	'/template-one-' . $suffix . '/',
	'Plantilla Belleza ' . $suffix,
	1
);

$template_two_id = wpmu_create_blog(
	get_current_site()->domain,
	'/template-two-' . $suffix . '/',
	'Plantilla Marketing ' . $suffix,
	1
);

if ( is_wp_error( $template_one_id ) || is_wp_error( $template_two_id ) ) {
	echo wp_json_encode(
		[
			'error' => 'template_create_failed',
			'one'   => is_wp_error( $template_one_id ) ? $template_one_id->get_error_message() : 'ok',
			'two'   => is_wp_error( $template_two_id ) ? $template_two_id->get_error_message() : 'ok',
		]
	);
	return;
}

foreach ( [$template_one_id, $template_two_id] as $template_blog_id ) {
	$template_site = wu_get_site( $template_blog_id );

	if ( ! $template_site ) {
		echo wp_json_encode( [
			'error'   => 'template_model_missing',
			'blog_id' => $template_blog_id,
		] );
		return;
	}

	$template_site->set_type( 'site_template' );
	$template_site->set_active( true );
	$template_site->set_archived( false );
	$template_site->set_deleted( false );
	$template_site->set_spam( false );
	$template_site->save();
}

// --- Customer ----------------------------------------------------------
$user_login = 'tmplcust' . $suffix;
$user_email = $user_login . '@example.com';
$user_pass  = 'cy-pw-' . wp_generate_password( 12, true, false );

$user_id = wpmu_create_user( $user_login, $user_pass, $user_email );

if ( ! $user_id ) {
	echo wp_json_encode( ['error' => 'user_create_failed'] );
	return;
}

$customer = wu_create_customer(
	[
		'user_id'       => $user_id,
		'email_address' => $user_email,
	]
);

if ( is_wp_error( $customer ) ) {
	echo wp_json_encode( [
		'error'  => 'customer_create_failed',
		'detail' => $customer->get_error_message(),
	] );
	return;
}

// The customer-panel pages expect customer-owned sites to belong to an
// active membership. Without this link the Account page renders only the
// title because each account widget bails out when the current membership or
// customer permission check is missing.
$plan = wu_create_product(
	[
		'name'         => 'Template Switching Test Plan ' . $suffix,
		'slug'         => 'template-switching-test-plan-' . strtolower( $suffix ),
		'type'         => 'plan',
		'pricing_type' => 'free',
		'amount'       => 0,
		'recurring'    => false,
		'active'       => true,
	]
);

if ( is_wp_error( $plan ) ) {
	echo wp_json_encode( [
		'error'  => 'plan_create_failed',
		'detail' => $plan->get_error_message(),
	] );
	return;
}

$membership = wu_create_membership(
	[
		'customer_id'    => $customer->get_id(),
		'user_id'        => $user_id,
		'plan_id'        => $plan->get_id(),
		'status'         => 'active',
		'gateway'        => 'free',
		'currency'       => 'USD',
		'initial_amount' => 0,
		'amount'         => 0,
		'recurring'      => false,
		'auto_renew'     => false,
	]
);

if ( is_wp_error( $membership ) ) {
	echo wp_json_encode( [
		'error'  => 'membership_create_failed',
		'detail' => $membership->get_error_message(),
	] );
	return;
}

// --- Customer-owned subsite -------------------------------------------
$customer_blog_id = wpmu_create_blog(
	get_current_site()->domain,
	'/cust-' . $suffix . '/',
	'Customer Site ' . $suffix,
	$user_id
);

if ( is_wp_error( $customer_blog_id ) ) {
	echo wp_json_encode( [
		'error'  => 'customer_site_create_failed',
		'detail' => $customer_blog_id->get_error_message(),
	] );
	return;
}

$customer_site = wu_get_site( $customer_blog_id );

if ( ! $customer_site ) {
	echo wp_json_encode( ['error' => 'customer_site_model_missing'] );
	return;
}

$customer_site->set_type( 'customer_owned' );
$customer_site->set_customer_id( $customer->get_id() );
$customer_site->set_membership_id( $membership->get_id() );
$customer_site->set_template_id( $template_one_id );
$customer_site->set_active( true );
$site_saved = $customer_site->save();

if ( is_wp_error( $site_saved ) ) {
	echo wp_json_encode( [
		'error'  => 'customer_site_save_failed',
		'detail' => $site_saved->get_error_message(),
	] );
	return;
}

update_site_meta( $customer_blog_id, 'wu_customer_id', $customer->get_id() );
update_site_meta( $customer_blog_id, 'wu_membership_id', $membership->get_id() );

// Make sure the customer's WP user is registered as an admin of the
// subsite. Without this, /wp-admin/admin.php?page=wu-template-switching
// 403s before the element even renders.
add_user_to_blog( $customer_blog_id, $user_id, 'administrator' );

echo wp_json_encode(
	[
		'ok'                => true,
		'customer_id'       => (int) $customer->get_id(),
		'membership_id'     => (int) $membership->get_id(),
		'plan_id'           => (int) $plan->get_id(),
		'wp_user_id'        => (int) $user_id,
		'username'          => $user_login,
		'password'          => $user_pass,
		'customer_blog_id'  => (int) $customer_blog_id,
		'customer_path'     => '/cust-' . $suffix . '/',
		'template_one_id'   => (int) $template_one_id,
		'template_two_id'   => (int) $template_two_id,
		'template_two_name' => 'Plantilla Marketing ' . $suffix,
	]
);
