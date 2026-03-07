<?php
/**
 * Integration tests for WordPress_Simple_Cache class.
 *
 * These tests verify basic PSR-16 compliance and can be run
 * in a simpler environment without full WordPress test suite.
 *
 * @package WP_Ultimo
 * @subpackage Tests\SSO
 */

namespace WP_Ultimo\SSO;

/**
 * Simple test runner for WordPress_Simple_Cache.
 *
 * Run with: php tests/WP_Ultimo/SSO/WordPress_Simple_Cache_Integration_Test.php
 */
class WordPress_Simple_Cache_Integration_Test {

	/**
	 * Test counter.
	 *
	 * @var int
	 */
	private static $tests_run = 0;

	/**
	 * Passed test counter.
	 *
	 * @var int
	 */
	private static $tests_passed = 0;

	/**
	 * Run all tests.
	 */
	public static function run_all_tests() {
		echo "Starting WordPress_Simple_Cache Integration Tests\n";
		echo "==================================================\n\n";

		// Mock WordPress functions if they don't exist.
		self::mock_wordpress_functions();

		// Run tests.
		self::test_implements_psr16_interface();
		self::test_basic_set_and_get();
		self::test_get_with_default();
		self::test_delete();
		self::test_has();
		self::test_clear();
		self::test_multiple_operations();
		self::test_data_types();
		self::test_prefix_isolation();
		self::test_ttl_handling();

		// Print summary.
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n==================================================\n";
		echo 'Tests run: ' . self::$tests_run . "\n";
		echo 'Tests passed: ' . self::$tests_passed . "\n";
		echo 'Tests failed: ' . (self::$tests_run - self::$tests_passed) . "\n";

		if (self::$tests_run === self::$tests_passed) {
			echo "\n✓ All tests passed!\n";
			exit(0);
		} else {
			echo "\n✗ Some tests failed!\n";
			exit(1);
		}
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Mock WordPress functions for standalone testing.
	 */
	private static function mock_wordpress_functions() {
		if (! function_exists('get_site_transient')) {
			/**
			 * Mock get_site_transient for standalone testing.
			 *
			 * @param string $key Transient key.
			 * @return mixed Value or false if not set.
			 */
			function get_site_transient($key) {
				global $_site_transients;
				if (! isset($_site_transients)) {
					$_site_transients = array();
				}
				return isset($_site_transients[ $key ]) ? $_site_transients[ $key ] : false;
			}
		}

		if (! function_exists('set_site_transient')) {
			/**
			 * Mock set_site_transient for standalone testing.
			 *
			 * @param string $key        Transient key.
			 * @param mixed  $value      Value to store.
			 * @param int    $expiration Expiration in seconds (unused in mock).
			 * @return bool Always true.
			 */
			function set_site_transient($key, $value, $expiration = 0) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				global $_site_transients;
				if (! isset($_site_transients)) {
					$_site_transients = array();
				}
				$_site_transients[ $key ] = $value;
				return true;
			}
		}

		if (! function_exists('delete_site_transient')) {
			/**
			 * Mock delete_site_transient for standalone testing.
			 *
			 * @param string $key Transient key.
			 * @return bool True if deleted, false if not found.
			 */
			function delete_site_transient($key) {
				global $_site_transients;
				if (! isset($_site_transients)) {
					$_site_transients = array();
				}
				if (isset($_site_transients[ $key ])) {
					unset($_site_transients[ $key ]);
					return true;
				}
				return false;
			}
		}
	}

	/**
	 * Assert helper.
	 *
	 * @param bool   $condition Condition to test.
	 * @param string $message   Test message.
	 */
	private static function assert($condition, $message) {
		++self::$tests_run;
		if ($condition) {
			++self::$tests_passed;
			echo "\xE2\x9C\x93 {$message}\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo "\xE2\x9C\x97 {$message}\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Test PSR-16 interface implementation.
	 */
	private static function test_implements_psr16_interface() {
		$cache = new WordPress_Simple_Cache('test_');
		self::assert(
			$cache instanceof \Psr\SimpleCache\CacheInterface,
			'Cache implements PSR-16 CacheInterface'
		);
	}

	/**
	 * Test basic set and get operations.
	 */
	private static function test_basic_set_and_get() {
		$cache = new WordPress_Simple_Cache('test_');
		$cache->clear();

		$result = $cache->set('key1', 'value1');
		self::assert(true === $result, 'set() returns true');

		$value = $cache->get('key1');
		self::assert('value1' === $value, 'get() returns correct value');

		$cache->clear();
	}

	/**
	 * Test get with default value.
	 */
	private static function test_get_with_default() {
		$cache = new WordPress_Simple_Cache('test_');
		$cache->clear();

		$value = $cache->get('nonexistent', 'default');
		self::assert('default' === $value, 'get() returns default for nonexistent key');

		$cache->clear();
	}

	/**
	 * Test delete operation.
	 */
	private static function test_delete() {
		$cache = new WordPress_Simple_Cache('test_');
		$cache->clear();

		$cache->set('delete_key', 'delete_value');
		$result = $cache->delete('delete_key');
		self::assert(true === $result, 'delete() returns true');

		$value = $cache->get('delete_key');
		self::assert(null === $value, 'Deleted key returns null');

		$cache->clear();
	}

	/**
	 * Test has operation.
	 */
	private static function test_has() {
		$cache = new WordPress_Simple_Cache('test_');
		$cache->clear();

		$has = $cache->has('has_key');
		self::assert(false === $has, 'has() returns false for nonexistent key');

		$cache->set('has_key', 'has_value');
		$has = $cache->has('has_key');
		self::assert(true === $has, 'has() returns true for existing key');

		$cache->clear();
	}

	/**
	 * Test clear operation.
	 */
	private static function test_clear() {
		$cache = new WordPress_Simple_Cache('test_');
		$cache->clear();

		$cache->set('clear1', 'value1');
		$cache->set('clear2', 'value2');

		$result = $cache->clear();
		self::assert(true === $result, 'clear() returns true');

		$has1 = $cache->has('clear1');
		$has2 = $cache->has('clear2');
		self::assert(false === $has1 && false === $has2, 'clear() removes all keys');

		$cache->clear();
	}

	/**
	 * Test multiple operations.
	 */
	private static function test_multiple_operations() {
		$cache = new WordPress_Simple_Cache('test_');
		$cache->clear();

		// Set multiple.
		$values = array(
			'm1' => 'v1',
			'm2' => 'v2',
		);
		$result = $cache->setMultiple($values);
		self::assert(true === $result, 'setMultiple() returns true');

		// Get multiple.
		$retrieved = $cache->getMultiple(array('m1', 'm2', 'm3'), 'default');
		self::assert(
			'v1' === $retrieved['m1'] && 'v2' === $retrieved['m2'] && 'default' === $retrieved['m3'],
			'getMultiple() returns correct values'
		);

		// Delete multiple.
		$result = $cache->deleteMultiple(array('m1', 'm2'));
		self::assert(true === $result, 'deleteMultiple() returns true');

		$cache->clear();
	}

	/**
	 * Test different data types.
	 */
	private static function test_data_types() {
		$cache = new WordPress_Simple_Cache('test_');
		$cache->clear();

		// String.
		$cache->set('string', 'test');
		self::assert('test' === $cache->get('string'), 'Stores and retrieves string');

		// Integer.
		$cache->set('int', 42);
		self::assert(42 === $cache->get('int'), 'Stores and retrieves integer');

		// Float.
		$cache->set('float', 3.14);
		self::assert(3.14 === $cache->get('float'), 'Stores and retrieves float');

		// Boolean.
		$cache->set('bool', true);
		self::assert(true === $cache->get('bool'), 'Stores and retrieves boolean');

		// Array.
		$array = array('foo' => 'bar');
		$cache->set('array', $array);
		self::assert($array === $cache->get('array'), 'Stores and retrieves array');

		$cache->clear();
	}

	/**
	 * Test prefix isolation.
	 */
	private static function test_prefix_isolation() {
		$cache1 = new WordPress_Simple_Cache('prefix1_');
		$cache2 = new WordPress_Simple_Cache('prefix2_');

		$cache1->clear();
		$cache2->clear();

		$cache1->set('key', 'value1');
		$cache2->set('key', 'value2');

		self::assert(
			'value1' === $cache1->get('key') && 'value2' === $cache2->get('key'),
			'Different prefixes isolate cache data'
		);

		$cache1->clear();
		$cache2->clear();
	}

	/**
	 * Test TTL handling.
	 */
	private static function test_ttl_handling() {
		$cache = new WordPress_Simple_Cache('test_');
		$cache->clear();

		// Integer TTL.
		$result = $cache->set('ttl_int', 'value', 60);
		self::assert(true === $result, 'set() with integer TTL returns true');

		// Null TTL.
		$result = $cache->set('ttl_null', 'value', null);
		self::assert(true === $result, 'set() with null TTL returns true');

		// DateInterval TTL.
		$interval = new \DateInterval('PT1H');
		$result   = $cache->set('ttl_interval', 'value', $interval);
		self::assert(true === $result, 'set() with DateInterval TTL returns true');

		$cache->clear();
	}
}

// Auto-run if this file is executed directly (not included by PHPUnit).
if (php_sapi_name() === 'cli' && ! defined('PHPUNIT_COMPOSER_INSTALL')) {
	// Load autoloader.
	require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
	require_once dirname(dirname(dirname(__DIR__))) . '/inc/sso/class-wordpress-simple-cache.php';

	WordPress_Simple_Cache_Integration_Test::run_all_tests();
}
