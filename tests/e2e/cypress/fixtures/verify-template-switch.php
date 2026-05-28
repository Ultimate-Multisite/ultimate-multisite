<?php
/**
 * Fixture: read the customer subsite's current template_id so the Cypress
 * spec can assert the AJAX switch actually mutated the site state.
 *
 * Usage:
 *
 *   wp eval-file tests/e2e/cypress/fixtures/verify-template-switch.php \
 *       <customer_blog_id>
 *
 * Echoes a JSON payload Cypress can parse.
 */

defined( 'ABSPATH' ) || exit;

$blog_id = isset( $args[0] ) ? (int) $args[0] : 0;

if ( $blog_id <= 0 ) {
	echo wp_json_encode( ['error' => 'missing_blog_id'] );
	return;
}

$site = wu_get_site( $blog_id );

if ( ! $site ) {
	echo wp_json_encode( [
		'error'   => 'site_not_found',
		'blog_id' => $blog_id,
	] );
	return;
}

echo wp_json_encode(
	[
		'ok'          => true,
		'blog_id'     => (int) $site->get_id(),
		'customer_id' => (int) $site->get_customer_id(),
		'template_id' => (int) $site->get_template_id(),
		'site_type'   => $site->get_type(),
	]
);
