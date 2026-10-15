<?php
/**
 * Set up the recurring multi-step Stripe checkout regression flow.
 *
 * Usage: wp eval-file setup-stripe-recurring-multistep.php <pk_key> <sk_key>
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

defined('ABSPATH') || exit;

$pk_key = isset($args[0]) ? $args[0] : wu_get_setting('stripe_test_pk_key', '');
$sk_key = isset($args[1]) ? $args[1] : wu_get_setting('stripe_test_sk_key', '');

if (empty($pk_key) || empty($sk_key)) {
	echo wp_json_encode([
		'success' => false,
		'error'   => 'Missing Stripe test keys.',
	]);
	return;
}

wu_save_setting('stripe_sandbox_mode', true);
wu_save_setting('stripe_test_pk_key', $pk_key);
wu_save_setting('stripe_test_sk_key', $sk_key);
wu_save_setting('stripe_show_direct_keys', true);
wu_save_setting('force_publish_sites_sync', false);

$active_gateways = (array) wu_get_setting('active_gateways', []);

if ( ! in_array('stripe', $active_gateways, true)) {
	$active_gateways[] = 'stripe';
}

wu_save_setting('active_gateways', $active_gateways);

$product = wu_get_product_by_slug('issue-1968-recurring-plan');

if ( ! $product) {
	$product = new WP_Ultimo\Models\Product();
}

$product->set_name('Issue 1968 Recurring Plan');
$product->set_slug('issue-1968-recurring-plan');
$product->set_amount(29.99);
$product->set_duration(1);
$product->set_duration_unit('month');
$product->set_recurring(true);
$product->set_pricing_type('paid');
$product->set_type('plan');
$product->set_active(true);
$product->set_featured_image_id(0);

$product_saved = $product->save();

if (is_wp_error($product_saved)) {
	echo wp_json_encode([
		'success' => false,
		'error'   => $product_saved->get_error_message(),
	]);
	return;
}

$form = wu_get_checkout_form_by_slug('issue-1968-multi-step');

if ( ! $form) {
	$form = wu_create_checkout_form(
		[
			'name'     => 'Issue 1968 Multi-step Checkout',
			'slug'     => 'issue-1968-multi-step',
			'settings' => [],
		]
	);
}

if (is_wp_error($form)) {
	echo wp_json_encode([
		'success' => false,
		'error'   => $form->get_error_message(),
	]);
	return;
}

$form->use_template('multi-step');
$form_saved = $form->save();

if (is_wp_error($form_saved)) {
	echo wp_json_encode([
		'success' => false,
		'error'   => $form_saved->get_error_message(),
	]);
	return;
}

$page      = get_page_by_path('issue-1968-register', OBJECT, 'page');
$page_data = [
	'post_name'    => 'issue-1968-register',
	'post_title'   => 'Issue 1968 Registration',
	'post_content' => '[wu_checkout slug="issue-1968-multi-step"]',
	'post_status'  => 'publish',
	'post_type'    => 'page',
	'post_author'  => 1,
];

if ($page) {
	$page_data['ID'] = $page->ID;
	$page_id         = wp_update_post($page_data, true);
} else {
	$page_id = wp_insert_post($page_data, true);
}

if (is_wp_error($page_id)) {
	echo wp_json_encode([
		'success' => false,
		'error'   => $page_id->get_error_message(),
	]);
	return;
}

echo wp_json_encode(
	[
		'success'          => true,
		'product_id'       => $product->get_id(),
		'checkout_form_id' => $form->get_id(),
		'page_id'          => $page_id,
		'registration_url' => get_permalink($page_id),
	]
);
