<?php
/**
 * Disable the custom login page redirect for e2e testing.
 *
 * If a previous wizard run enabled the custom login page, /wp-login.php
 * redirects to /login/ and loginByForm breaks. This runs with
 * failOnNonZeroExit: false because the plugin may not yet be
 * network-activated when the before() hook executes.
 *
 * Using a fixture file instead of inline wp eval to avoid shell-quoting
 * issues through the npx -> wp-env -> docker exec -> wp eval chain.
 */
if ( function_exists( 'wu_save_setting' ) ) {
	wu_save_setting( 'enable_custom_login_page', 0 );
	echo 'disabled';
} else {
	echo 'skipped:wu_save_setting not available';
}
