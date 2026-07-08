<?php
defined('ABSPATH') || exit;

/**
 * Set up the SSO e2e test environment.
 *
 * Creates a subsite, maps sso-test.ultimate-multisite.test:PORT to it as a
 * domain, and enables SSO.
 * Outputs JSON with the site ID and mapped domain for use by the Cypress spec.
 *
 * Note: In wp-env, DOMAIN_CURRENT_SITE includes the port (e.g. localhost:8889).
 * WordPress only strips ports 80 and 443, so non-standard ports remain part of
 * the domain throughout the multisite bootstrap. The domain mapping must therefore
 * include the port to match incoming requests.
 *
 * Domains with non-standard ports fail the Domain model's regex validation,
 * so we insert directly into the database table to preserve the exact Host
 * header wp-env receives during Cypress requests.
 */

global $wpdb, $current_site;

// Detect the network domain (includes port in wp-env, e.g. "localhost:8889").
$network_domain = $current_site->domain;

// Extract port from the network domain, if present.
$port = '';
if (preg_match('/:(\d+)$/', $network_domain, $m)) {
	$port = ':' . $m[1];
}

$mapped_domain_host = 'sso-test.ultimate-multisite.test';
$mapped_domain      = $mapped_domain_host . $port;

// 1. Create a subsite for SSO testing (or reuse if it already exists).
$existing = get_blog_id_from_url($network_domain, '/sso-test-site/');

if ($existing) {
	$site_id = $existing;
} else {
	$site_id = wpmu_create_blog(
		$network_domain,
		'/sso-test-site/',
		'SSO Test Site',
		get_current_user_id()
	);

	if (is_wp_error($site_id)) {
		echo wp_json_encode([
			'error' => $site_id->get_error_message(),
		]);
		exit(1);
	}
}

// 2. Insert domain mapping for sso-test.ultimate-multisite.test:PORT directly
// into the DB. The Domain model's validation rejects domains with ports, so
// we bypass it.
$table = $wpdb->base_prefix . 'wu_domain_mappings';
$now   = current_time('mysql');

// Check if the mapping already exists (look for both with and without port).
$existing_domain = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT id FROM {$table} WHERE domain IN (%s, %s, %s, %s) AND blog_id = %d LIMIT 1",
		$mapped_domain,
		$mapped_domain_host,
		'127.0.0.1' . $port,
		'127.0.0.1',
		$site_id
	)
);

if ($existing_domain) {
	// Update existing record to ensure domain includes port.
	$wpdb->update(
		$table,
		[
			'domain' => $mapped_domain,
			'active' => 1,
			'stage'  => 'done',
		],
		['id' => $existing_domain],
		['%s', '%d', '%s'],
		['%d']
	);
	$domain_id = (int) $existing_domain;
} else {
	$inserted = $wpdb->insert(
		$table,
		[
			'blog_id'        => $site_id,
			'domain'         => $mapped_domain,
			'active'         => 1,
			'primary_domain' => 1,
			'secure'         => 0,
			'stage'          => 'done',
			'date_created'   => $now,
			'date_modified'  => $now,
		],
		['%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s']
	);

	if (! $inserted) {
		echo wp_json_encode([
			'error'   => 'Domain insert failed: ' . $wpdb->last_error,
			'site_id' => $site_id,
		]);
		exit(1);
	}

	$domain_id = $wpdb->insert_id;
}

// Also set the wu_dmtable property so get_by_domain() can find it.
if (empty($wpdb->wu_dmtable)) {
	$wpdb->wu_dmtable = $table;
}

// Clear domain mapping cache for this domain.
wp_cache_delete('domain:' . $mapped_domain, 'domain_mappings');
wp_cache_delete('domain:' . $mapped_domain_host, 'domain_mappings');
wp_cache_delete('domain:127.0.0.1' . $port, 'domain_mappings');
wp_cache_delete('domain:127.0.0.1', 'domain_mappings');

// 3. Enable domain mapping, enable SSO, and disable the loading overlay
// (avoids flicker in tests). Domain mapping must be enabled before the
// next mapped-host request so sunrise registers the mapped site instead of
// falling back to WordPress' main-site redirect.
wu_save_setting('enable_domain_mapping', true);
wu_save_setting('enable_sso', true);
wu_save_setting('enable_sso_loading_overlay', false);

// 4. Output result for Cypress.
echo wp_json_encode([
	'site_id'   => $site_id,
	'domain'    => $mapped_domain,
	'domain_id' => $domain_id,
]);
