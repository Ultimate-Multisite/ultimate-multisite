<?php
/**
 * Create a checkout form and registration page for e2e testing.
 * Idempotent: skips creation if the form already exists.
 */
$existing = WP_Ultimo\Models\Checkout_Form::query(
	[
		'search' => 'main-form',
		'number' => 1,
	]
);

if ( $existing ) {
	$form    = $existing[0];
	$page_id = wu_get_setting('default_registration_page', 0);
	echo 'form:' . esc_html($form->get_id()) . ',page:' . esc_html($page_id);
	return;
}

$form_data = [
	'name'     => 'Registration Form',
	'slug'     => 'main-form',
	'settings' => [],
];

$form = wu_create_checkout_form($form_data);

if ( is_wp_error($form) ) {
	echo 'error:' . esc_html($form->get_error_message());
	return;
}

$form->use_template('single-step');
$form->save();

$page_id = wp_insert_post(
	[
		'post_name'    => 'register',
		'post_title'   => 'Register',
		'post_content' => '[wu_checkout slug="main-form"]',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_author'  => 1,
	]
);

wu_save_setting('default_registration_page', $page_id);

echo 'form:' . esc_html($form->get_id()) . ',page:' . esc_html($page_id);
