<?php
/**
 * Reproduce the SSO bootstrap race fixed in PR #1185.
 *
 * The bug:
 *   `convert_bearer_into_auth_cookies()` runs on `init`, but on some multisite
 *   bootstraps the `$GLOBALS['current_blog']` object exists with an empty
 *   `registered` property. `calculate_secret_from_date('')` then threw
 *   `SSO_Exception` and the response became a 500 / blank page (most visible
 *   when admin UI is loaded inside an iframe via SSO).
 *
 * The fix (PR #1185, merged in 2.11.1) added two guards:
 *   1. `convert_bearer_into_auth_cookies()`: early return when `$current_blog`
 *      is missing or its `registered` property is empty.
 *   2. `calculate_secret_from_date($date)`: when `$date` is empty, fall back
 *      to the main site's registered date (or '2024-01-01 00:00:00') instead
 *      of throwing.
 *
 * This fixture verifies both guards still work, in a way that survives
 * future refactors of the SSO bootstrap chain.
 *
 * Outputs JSON consumed by the Cypress spec:
 *   {
 *     "main_secret":    "<hash from a populated main-site registered date>",
 *     "empty_secret_1": "<hash from calculate_secret_from_date('')>",
 *     "empty_secret_2": "<same call again — must equal empty_secret_1>",
 *     "bearer_threw":   false,                  // guard 1 must not throw
 *     "secret_threw":   false                   // guard 2 must not throw
 *   }
 */

if (! class_exists('\\WP_Ultimo\\SSO\\SSO')) {
	echo wp_json_encode(['error' => 'WP_Ultimo SSO class not loaded']);
	exit(1);
}

$result = [
	'main_secret'    => null,
	'empty_secret_1' => null,
	'empty_secret_2' => null,
	'bearer_threw'   => false,
	'secret_threw'   => false,
];

// Grab the SSO singleton (uses the Singleton trait, see inc/sso/class-sso.php:40).
try {
	$sso = \WP_Ultimo\SSO\SSO::get_instance();
} catch (\Throwable $e) {
	echo wp_json_encode(['error' => 'Cannot get SSO instance: ' . $e->getMessage()]);
	exit(1);
}

// -----------------------------------------------------------------------
// Guard 2: calculate_secret_from_date() must never throw on empty input.
// It must return a deterministic hash (same call -> same hash).
// -----------------------------------------------------------------------
try {
	$main = function_exists('get_main_site_id') ? get_site(get_main_site_id()) : get_site(1);
	$result['main_secret'] = $sso->calculate_secret_from_date($main->registered);
} catch (\Throwable $e) {
	$result['main_secret'] = 'THREW:' . $e->getMessage();
}

try {
	$result['empty_secret_1'] = $sso->calculate_secret_from_date('');
} catch (\Throwable $e) {
	$result['secret_threw']  = true;
	$result['empty_secret_1'] = 'THREW:' . $e->getMessage();
}

try {
	$result['empty_secret_2'] = $sso->calculate_secret_from_date('');
} catch (\Throwable $e) {
	$result['secret_threw']   = true;
	$result['empty_secret_2'] = 'THREW:' . $e->getMessage();
}

// -----------------------------------------------------------------------
// Guard 1: convert_bearer_into_auth_cookies() must early-return (no throw)
// when $current_blog->registered is empty.
// -----------------------------------------------------------------------
global $current_blog;
$saved_registered = $current_blog->registered ?? null;

try {
	if (is_object($current_blog)) {
		$current_blog->registered = '';
	}
	$sso->convert_bearer_into_auth_cookies();
} catch (\Throwable $e) {
	$result['bearer_threw'] = true;
	$result['bearer_error'] = $e->getMessage();
} finally {
	if (is_object($current_blog) && $saved_registered !== null) {
		$current_blog->registered = $saved_registered;
	}
}

echo wp_json_encode($result);
