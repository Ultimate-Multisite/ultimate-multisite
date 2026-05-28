<?php
/**
 * Set up the SSO e2e test environment.
 *
 * Creates a subsite, maps 127.0.0.1:PORT to it as a domain, and enables SSO.
 * Outputs JSON with the site ID and mapped domain for use by the Cypress spec.
 *
 * Note: In wp-env, DOMAIN_CURRENT_SITE includes the port (e.g. localhost:8889).
 * WordPress only strips ports 80 and 443, so non-standard ports remain part of
 * the domain throughout the multisite bootstrap. The domain mapping must therefore
 * include the port to match incoming requests.
 *
 * IP addresses with ports fail the Domain model's regex validation,
 * so we insert directly into the database table to bypass validation.
 */

global $wpdb, $current_site;

// Detect the network domain (includes port in wp-env, e.g. "localhost:8889").
$network_domain = $current_site->domain;

// Extract port from the network domain, if present.
$port = '';
if (preg_match('/:(\d+)$/', $network_domain, $m)) {
	$port = ':' . $m[1];
}

$mapped_host   = '127.0.0.1';
$mapped_domain = $mapped_host . $port;

// 1. Create a subsite for SSO testing (or reuse if it already exists).
$existing_sites = get_sites(
	[
		'domain' => $mapped_host,
		'path'   => '/',
		'number' => 1,
		'fields' => 'ids',
	]
);

$existing = $existing_sites ? (int) $existing_sites[0] : get_blog_id_from_url($network_domain, '/sso-test-site/');

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

/*
 * wp-env receives browser requests for 127.0.0.1:PORT, but WordPress core's
 * multisite bootstrap strips the non-standard port before `pre_get_site_by_path`
 * runs. Store the test subsite as a native 127.0.0.1 root-domain site as well
 * as a mapped domain so the request resolves to the subsite before core's
 * unknown-domain redirect sends it back to the main localhost site.
 */
$wpdb->update(
	$wpdb->blogs,
	[
		'domain' => $mapped_host,
		'path'   => '/',
	],
	['blog_id' => $site_id],
	['%s', '%s'],
	['%d']
);

clean_blog_cache($site_id);

update_blog_option($site_id, 'home', 'http://' . $mapped_domain);
update_blog_option($site_id, 'siteurl', 'http://' . $mapped_domain);

/*
 * 2. Insert domain mapping for 127.0.0.1:PORT directly into the DB.
 * The Domain model's validation rejects IP addresses, so we bypass it.
 */
$table = $wpdb->base_prefix . 'wu_domain_mappings';
$now   = current_time('mysql');

// Check if the mapping already exists (look for both with and without port).
$existing_domain = $wpdb->get_var(
	$wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the trusted WP database prefix.
		"SELECT id FROM {$table} WHERE domain IN (%s, %s) AND blog_id = %d LIMIT 1",
		$mapped_domain,
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

if ($port) {
	$bare_domain_id = $wpdb->get_var(
	$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the trusted WP database prefix.
			"SELECT id FROM {$table} WHERE domain = %s AND blog_id = %d LIMIT 1",
			'127.0.0.1',
			$site_id
		)
	);

	if ($bare_domain_id) {
		$wpdb->update(
			$table,
			[
				'active' => 1,
				'stage'  => 'done',
			],
			['id' => $bare_domain_id],
			['%d', '%s'],
			['%d']
		);
	} else {
		$wpdb->insert(
			$table,
			[
				'blog_id'        => $site_id,
				'domain'         => '127.0.0.1',
				'active'         => 1,
				'primary_domain' => 0,
				'secure'         => 0,
				'stage'          => 'done',
				'date_created'   => $now,
				'date_modified'  => $now,
			],
			['%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s']
		);
	}
}

// Also set the wu_dmtable property so get_by_domain() can find it.
if (empty($wpdb->wu_dmtable)) {
	$wpdb->wu_dmtable = $table;
}

// Clear domain mapping cache for this domain.
wp_cache_delete('domain:' . $mapped_domain, 'domain_mappings');
wp_cache_delete('domain:127.0.0.1', 'domain_mappings');

// 3. Enable SSO and disable the loading overlay (avoids flicker in tests).
wu_save_setting('enable_sso', true);
wu_save_setting('enable_sso_loading_overlay', false);

// 4. Output result for Cypress.
echo wp_json_encode([
	'site_id'   => $site_id,
	'domain'    => $mapped_domain,
	'domain_id' => $domain_id,
]);
