<?php
/**
 * Reproduce the subsite password reset URL bug fixed in PR #1169 / issue #1168.
 *
 * The bug:
 *   `replace_reset_password_link()` had an early return on subsites and the
 *   `retrieve_password_message` filter registration was gated by
 *   `is_main_site()`. End users who triggered "lost password" on a subsite
 *   (especially mapped custom domains) received an email pointing to
 *   `https://main-site.example/wp-login.php` — a domain they don't recognise.
 *
 * What this fixture does:
 *   1. Ensures custom login pages are enabled (the affected setting).
 *   2. Creates a test subsite at `/pwreset-subsite/` if missing.
 *   3. Creates a test user owned by that subsite.
 *   4. Switches to the subsite context and invokes UM's filter directly
 *      with a synthetic raw message containing a main-site wp-login.php URL.
 *   5. Extracts the URL from the filtered message and returns its host /
 *      path / query so the Cypress spec can assert against them.
 *   6. Also asserts the new `wu_subsite_password_reset_url` filter (added
 *      by #1169) is reachable — by hooking it transiently and confirming
 *      it ran.
 *
 * Returns JSON consumed by the Cypress spec:
 *   {
 *     "site_id":          <int>,
 *     "subsite_host":     "<host expected to appear in the reset URL>",
 *     "main_host":        "<main site host>",
 *     "filter_ran":       true,                  // wu_subsite_password_reset_url
 *     "rewritten_url":    "<final URL pulled from the message>",
 *     "rewritten_host":   "<host of the rewritten URL>",
 *     "rewritten_query":  { action, key, login, wp_lang },
 *     "raw_url":          "<the URL we asked UM to rewrite>"
 *   }
 *
 * No SMTP is sent. We invoke the filter callback directly so the test is
 * deterministic and does not depend on Mailpit, cron, or rate limiting.
 */

global $wpdb, $current_site;

$result = [
	'site_id'         => null,
	'subsite_host'    => null,
	'main_host'       => null,
	'filter_ran'      => false,
	'rewritten_url'   => null,
	'rewritten_host'  => null,
	'rewritten_query' => null,
	'raw_url'         => null,
];

// --------------------------------------------------------------------------
// 1. Custom login pages must be enabled (the affected feature).
// --------------------------------------------------------------------------
if (function_exists('wu_save_setting')) {
	wu_save_setting('enable_custom_login_page', true);
}

// --------------------------------------------------------------------------
// 2. Create or reuse the test subsite.
// --------------------------------------------------------------------------
$network_domain = $current_site->domain;
$result['main_host'] = $network_domain;

$existing = get_blog_id_from_url($network_domain, '/pwreset-subsite/');

if ($existing) {
	$site_id = (int) $existing;
} else {
	$site_id = wpmu_create_blog(
		$network_domain,
		'/pwreset-subsite/',
		'PW Reset Subsite',
		get_current_user_id()
	);

	if (is_wp_error($site_id)) {
		echo wp_json_encode([
			'error' => 'Subsite create failed: ' . $site_id->get_error_message(),
		]);
		exit(1);
	}
}

$result['site_id'] = (int) $site_id;

// --------------------------------------------------------------------------
// 3. Create or reuse a test user attached to the subsite.
// --------------------------------------------------------------------------
$login = 'pwresetuser';
$user  = get_user_by('login', $login);

if (! $user) {
	$user_id = wpmu_create_user($login, wp_generate_password(20), 'pwresetuser@example.test');
	if (! $user_id) {
		echo wp_json_encode(['error' => 'User create failed', 'site_id' => $site_id]);
		exit(1);
	}
	$user = get_user_by('id', $user_id);
}

add_user_to_blog($site_id, $user->ID, 'subscriber');

// --------------------------------------------------------------------------
// 4. Switch context to the subsite and invoke the UM filter directly.
//    `replace_reset_password_link()` is registered on `retrieve_password_message`
//    in Checkout_Pages::__construct(). We reach it via apply_filters().
// --------------------------------------------------------------------------
switch_to_blog($site_id);

// `home_url('/')` after switch_to_blog is the subsite's own URL.
// We extract just the host portion so we can compare it later.
$subsite_home = home_url('/');
$subsite_host = wp_parse_url($subsite_home, PHP_URL_HOST);
$result['subsite_host'] = $subsite_host;

// Hook the new filter introduced by PR #1169 so we can prove it ran.
$filter_marker = function ($url, $key, $user_login, $user_data) use (&$result) {
	$result['filter_ran'] = true;

	return $url;
};

add_filter('wu_subsite_password_reset_url', $filter_marker, 10, 4);

// Build a synthetic raw message that mimics what core's retrieve_password()
// would produce on a subsite BEFORE the UM filter runs: a URL pointing at
// the main network site's wp-login.php.
$key      = 'fixturetestkey1234567890';
$raw_url  = network_site_url(
	"wp-login.php?action=rp&key={$key}&login=" . rawurlencode($login),
	'login'
);

$result['raw_url'] = $raw_url;

$raw_message = "Someone has requested a password reset for the following account:\n\n"
	. "Site Name: PW Reset Subsite\n\nUsername: {$login}\n\n"
	. "If this was a mistake, ignore this email and nothing will happen.\n\n"
	. "To reset your password, visit the following address:\n\n{$raw_url}";

// Apply the UM filter chain.
$filtered = apply_filters(
	'retrieve_password_message',
	$raw_message,
	$key,
	$login,
	$user
);

remove_filter('wu_subsite_password_reset_url', $filter_marker, 10);

restore_current_blog();

// --------------------------------------------------------------------------
// 5. Extract the URL line from the filtered message.
// --------------------------------------------------------------------------
if (preg_match('#https?://\S+#', $filtered, $m)) {
	$rewritten = $m[0];
	$result['rewritten_url']  = $rewritten;
	$result['rewritten_host'] = wp_parse_url($rewritten, PHP_URL_HOST);

	$query_str = (string) wp_parse_url($rewritten, PHP_URL_QUERY);
	parse_str($query_str, $parsed_q);

	$result['rewritten_query'] = [
		'action'  => $parsed_q['action']  ?? null,
		'key'     => $parsed_q['key']     ?? null,
		'login'   => $parsed_q['login']   ?? null,
		'wp_lang' => $parsed_q['wp_lang'] ?? null,
	];
}

echo wp_json_encode($result);
