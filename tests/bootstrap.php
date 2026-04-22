<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Wp_Multisite_Waas
 */

$_tests_dir = getenv('WP_TESTS_DIR');
require 'vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// WordPress ms-functions.php accesses $_SERVER['REMOTE_ADDR'] without isset() check.
// PHP 8.5 treats undefined array key access as an error in strict mode.
// Set a default value to prevent "Undefined array key" errors during blog creation.
if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if ( false !== $_phpunit_polyfills_path ) {
	define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

if ( ! file_exists("{$_tests_dir}/includes/functions.php") ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit(1);
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname(__DIR__) . '/ultimate-multisite.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Load test traits (not autoloaded since they don't end in Test.php).
require_once __DIR__ . '/WP_Ultimo/Managers/Manager_Test_Trait.php';

/**
 * Minimal wc_get_order() stub for unit tests.
 *
 * Tests that exercise reclaim_pending_site_on_wc_order_completion() set
 * $GLOBALS['_wu_test_wc_order_email'] to control what billing email the
 * stub returns. Tests that do NOT set the global receive `false`.
 *
 * This stub is only defined when WooCommerce is not loaded; if WC is
 * present the real wc_get_order() takes precedence.
 */
if ( ! function_exists('wc_get_order')) {
	/**
	 * Test stub for wc_get_order().
	 *
	 * @param int $order_id Unused in the stub.
	 * @return object|false
	 */
	function wc_get_order($order_id) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		if ( ! isset($GLOBALS['_wu_test_wc_order_email'])) {
			return false;
		}

		$email = $GLOBALS['_wu_test_wc_order_email'];

		return new class($email) {
			/** @var string */
			private $billing_email;
			public function __construct(string $email) {
				$this->billing_email = $email;
			}
			public function get_billing_email(): string {
				return $this->billing_email;
			}
		};
	}
}
