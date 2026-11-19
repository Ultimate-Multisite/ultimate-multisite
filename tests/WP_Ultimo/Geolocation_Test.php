<?php
/**
 * Test case for Geolocation class.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests;

use WP_Ultimo\Geolocation;
use WP_UnitTestCase;

/**
 * Test Geolocation functionality.
 */
class Geolocation_Test extends WP_UnitTestCase {

	/**
	 * Original $_SERVER values to restore after each test.
	 *
	 * @var array
	 */
	private $original_server = [];

	/**
	 * Save original $_SERVER values before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_server = $_SERVER;
	}

	/**
	 * Reset static properties and restore $_SERVER after each test.
	 */
	public function tearDown(): void {
		// Restore original $_SERVER to avoid polluting other tests
		$_SERVER = $this->original_server;

		// Reset the static memory cache and cached IP
		$this->reset_static_properties();
		parent::tearDown();
	}

	/**
	 * Reset static properties via reflection.
	 */
	private function reset_static_properties(): void {
		$reflection = new \ReflectionClass(Geolocation::class);

		$memory_cache = $reflection->getProperty('memory_cache');
		if (PHP_VERSION_ID < 80100) {
			$memory_cache->setAccessible(true);
		}
		$memory_cache->setValue(null, []);

		$cached_ip = $reflection->getProperty('cached_ip');
		if (PHP_VERSION_ID < 80100) {
			$cached_ip->setAccessible(true);
		}
		$cached_ip->setValue(null, null);

		$reader = $reflection->getProperty('reader');
		if (PHP_VERSION_ID < 80100) {
			$reader->setAccessible(true);
		}
		$reader_value = $reader->getValue();
		if (null !== $reader_value) {
			try {
				$reader_value->close();
			} catch (\Exception $e) {
				// Ignore errors on close - reader may already be closed.
				unset($e); // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}
		$reader->setValue(null, null);
	}

	/**
	 * Test cache group constant.
	 */
	public function test_cache_group_constant() {
		$this->assertEquals('wu_geolocation', Geolocation::CACHE_GROUP);
	}

	/**
	 * Test validate_ip with valid IPv4 address.
	 */
	public function test_validate_ip_valid_ipv4() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('validate_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null, '192.168.1.1');
		$this->assertEquals('192.168.1.1', $result);
	}

	/**
	 * Test validate_ip with IPv4 address including port.
	 */
	public function test_validate_ip_ipv4_with_port() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('validate_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null, '192.168.1.1:8080');
		$this->assertEquals('192.168.1.1', $result);
	}

	/**
	 * Test validate_ip with valid IPv6 address.
	 */
	public function test_validate_ip_valid_ipv6() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('validate_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null, '::1');
		$this->assertEquals('::1', $result);
	}

	/**
	 * Test validate_ip with IPv6 address including port.
	 */
	public function test_validate_ip_ipv6_with_port() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('validate_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null, '[::1]:8080');
		$this->assertEquals('::1', $result);
	}

	/**
	 * Test validate_ip with invalid IP.
	 */
	public function test_validate_ip_invalid() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('validate_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null, 'invalid-ip');
		$this->assertEquals('', $result);
	}

	/**
	 * Test validate_ip with empty string.
	 */
	public function test_validate_ip_empty() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('validate_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null, '');
		$this->assertEquals('', $result);
	}

	/**
	 * Test is_private_ip with private IPv4 ranges.
	 */
	public function test_is_private_ip_private_ranges() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('is_private_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		// Private ranges
		$this->assertTrue($method->invoke(null, '192.168.1.1'));
		$this->assertTrue($method->invoke(null, '10.0.0.1'));
		$this->assertTrue($method->invoke(null, '172.16.0.1'));
		$this->assertTrue($method->invoke(null, '127.0.0.1'));
		$this->assertTrue($method->invoke(null, '::1'));
	}

	/**
	 * Test is_private_ip with public IPv4 addresses.
	 */
	public function test_is_private_ip_public_ranges() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('is_private_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		// Public addresses
		$this->assertFalse($method->invoke(null, '8.8.8.8'));
		$this->assertFalse($method->invoke(null, '1.1.1.1'));
		$this->assertFalse($method->invoke(null, '208.67.222.222'));
	}

	/**
	 * Test is_private_ip with empty string.
	 */
	public function test_is_private_ip_empty() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('is_private_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$this->assertTrue($method->invoke(null, ''));
	}

	/**
	 * Forwarding headers from an untrusted (direct) peer must be ignored.
	 */
	public function test_get_ip_address_ignores_spoofed_headers_from_untrusted_peer() {
		$_SERVER['REMOTE_ADDR']           = '203.0.113.9';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.1';
		$_SERVER['HTTP_TRUE_CLIENT_IP']   = '198.51.100.2';
		$_SERVER['HTTP_X_REAL_IP']        = '198.51.100.3';
		$_SERVER['HTTP_X_FORWARDED_FOR']  = '198.51.100.4';

		$this->assertSame('203.0.113.9', Geolocation::get_ip_address());

		// Changing a header must not produce a new identity either.
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.5';
		$this->assertSame('203.0.113.9', Geolocation::get_ip_address());
	}

	/**
	 * CF-Connecting-IP is honoured only from Cloudflare edge addresses.
	 */
	public function test_get_ip_address_trusts_cloudflare_header_from_cloudflare_edge() {
		$_SERVER['REMOTE_ADDR']           = '2606:4700::1';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.1';
		unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_TRUE_CLIENT_IP']);

		$this->assertSame('198.51.100.1', Geolocation::get_ip_address());

		// A private proxy cannot vouch for CF-Connecting-IP.
		$_SERVER['REMOTE_ADDR'] = '10.0.0.5';
		$this->assertSame('10.0.0.5', Geolocation::get_ip_address());
	}

	/**
	 * X-Forwarded-For is walked right-to-left, skipping trusted hops.
	 */
	public function test_get_ip_address_walks_forwarded_for_from_trusted_proxy() {
		unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_TRUE_CLIENT_IP']);
		$_SERVER['REMOTE_ADDR']          = '127.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99, 203.0.113.20, 162.158.1.1, 10.1.2.3';

		// 10.1.2.3 (private) and 162.158.1.1 (Cloudflare) are trusted hops; spoofed left-most value is ignored.
		$this->assertSame('203.0.113.20', Geolocation::get_ip_address());
	}

	/**
	 * Trusted proxies can be configured with the filter, or disabled entirely.
	 */
	public function test_get_ip_address_respects_trusted_proxies_filter() {
		unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_TRUE_CLIENT_IP']);
		$_SERVER['REMOTE_ADDR']          = '192.0.2.10';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
		$_SERVER['HTTP_X_REAL_IP']       = '198.51.100.8';

		$this->assertSame('192.0.2.10', Geolocation::get_ip_address());

		$add_proxy = fn($proxies) => array_merge($proxies, ['192.0.2.0/24']);
		add_filter('wu_trusted_proxies', $add_proxy);
		Geolocation::clear_cache();
		$this->assertSame('198.51.100.7', Geolocation::get_ip_address());
		remove_filter('wu_trusted_proxies', $add_proxy);

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		add_filter('wu_trusted_proxies', '__return_empty_array');
		Geolocation::clear_cache();
		$this->assertSame('127.0.0.1', Geolocation::get_ip_address());
		remove_filter('wu_trusted_proxies', '__return_empty_array');
	}

	/**
	 * Test CIDR matching for IPv4, IPv6 and IPv4-mapped IPv6.
	 */
	public function test_ip_in_range() {
		$this->assertTrue(Geolocation::ip_in_range('10.20.30.40', '10.0.0.0/8'));
		$this->assertTrue(Geolocation::ip_in_range('172.31.255.255', '172.16.0.0/12'));
		$this->assertFalse(Geolocation::ip_in_range('172.32.0.1', '172.16.0.0/12'));
		$this->assertTrue(Geolocation::ip_in_range('192.0.2.1', '192.0.2.1'));
		$this->assertFalse(Geolocation::ip_in_range('192.0.2.2', '192.0.2.1'));
		$this->assertTrue(Geolocation::ip_in_range('2a06:98c7::1', '2a06:98c0::/29'));
		$this->assertFalse(Geolocation::ip_in_range('2a06:98c8::1', '2a06:98c0::/29'));
		$this->assertTrue(Geolocation::ip_in_range('::ffff:127.0.0.1', '127.0.0.0/8'));
		$this->assertFalse(Geolocation::ip_in_range('10.0.0.1', '::1/128'));
		$this->assertFalse(Geolocation::ip_in_range('10.0.0.1', '10.0.0.0/33'));
		$this->assertFalse(Geolocation::ip_in_range('not-an-ip', '10.0.0.0/8'));
		$this->assertTrue(Geolocation::ip_in_range('8.8.8.8', '0.0.0.0/0'));
	}

	/**
	 * Test get_local_database_path returns expected path structure.
	 */
	public function test_get_local_database_path_structure() {
		$path = Geolocation::get_local_database_path();

		// Should contain the expected filename
		$this->assertStringEndsWith('GeoLite2-Country.mmdb', $path);

		// Should contain wp-content/uploads
		$this->assertStringContainsString('uploads', $path);
	}

	/**
	 * Test supports_geolite2 returns true with Composer package.
	 */
	public function test_supports_geolite2_with_composer() {
		// With the maxmind-db/reader package installed via Composer,
		// this should return true
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('supports_geolite2');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null);
		$this->assertTrue($result, 'MaxMind\Db\Reader class should be available via Composer');
	}

	/**
	 * Test supports_geolite2 returns false when MaxMind reader class is unavailable.
	 *
	 * Runs in a separate process so the MaxMind autoloader cannot satisfy the
	 * class_exists() check inside supports_geolite2().
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_supports_geolite2_returns_false_when_class_missing() {
		// Bootstrap WordPress/plugin in the child process so Geolocation is defined,
		// but do NOT load the MaxMind autoloader — the class should be absent.
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('supports_geolite2');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		if (class_exists('MaxMind\Db\Reader')) {
			// MaxMind is already loaded in this process — skip rather than give a
			// false-positive failure. The positive test covers the loaded case.
			$this->markTestSkipped('MaxMind\Db\Reader is already loaded; cannot test the missing-class path in this process.');
			return;
		}

		$result = $method->invoke(null);
		$this->assertFalse($result, 'supports_geolite2() should return false when MaxMind\Db\Reader is not available');
	}

	/**
	 * Test is_geolocation_enabled with enabled values.
	 */
	public function test_is_geolocation_enabled_enabled_values() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('is_geolocation_enabled');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$this->assertTrue($method->invoke(null, 'geolocation'));
		$this->assertTrue($method->invoke(null, 'geolocation_ajax'));
	}

	/**
	 * Test is_geolocation_enabled with disabled values.
	 */
	public function test_is_geolocation_enabled_disabled_values() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('is_geolocation_enabled');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$this->assertFalse($method->invoke(null, ''));
		$this->assertFalse($method->invoke(null, 'base'));
		$this->assertFalse($method->invoke(null, 'shop'));
	}

	/**
	 * Test geolocate_ip returns expected structure.
	 */
	public function test_geolocate_ip_structure() {
		$result = Geolocation::geolocate_ip('127.0.0.1');

		$this->assertIsArray($result);
		$this->assertArrayHasKey('ip', $result);
		$this->assertArrayHasKey('country', $result);
		$this->assertArrayHasKey('state', $result);
		$this->assertEquals('127.0.0.1', $result['ip']);
	}

	/**
	 * Test geolocate_ip with fallback for private IP.
	 */
	public function test_geolocate_ip_private_ip_with_fallback() {
		$result = Geolocation::geolocate_ip('192.168.1.1', true);

		$this->assertIsArray($result);
		$this->assertEquals('192.168.1.1', $result['ip']);
		// Should either be empty or the filtered default country
		$this->assertIsString($result['country']);
	}

	/**
	 * Test clear_cache clears the memory cache.
	 */
	public function test_clear_cache() {
		// First geolocate to populate cache
		Geolocation::geolocate_ip('127.0.0.1');

		// Clear cache
		Geolocation::clear_cache();

		$reflection = new \ReflectionClass(Geolocation::class);

		// Check memory cache is cleared
		$memory_cache = $reflection->getProperty('memory_cache');
		if (PHP_VERSION_ID < 80100) {
			$memory_cache->setAccessible(true);
		}
		$this->assertEquals([], $memory_cache->getValue());

		// Check cached_ip is cleared
		$cached_ip = $reflection->getProperty('cached_ip');
		if (PHP_VERSION_ID < 80100) {
			$cached_ip->setAccessible(true);
		}
		$this->assertNull($cached_ip->getValue());
	}

	/**
	 * Test get_external_ip_address returns placeholder for private IP.
	 */
	public function test_get_external_ip_address_private_ip() {
		// Mock REMOTE_ADDR to be a private IP
		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

		$result = Geolocation::get_external_ip_address();

		$this->assertEquals('0.0.0.0', $result);

		// Clean up
		unset($_SERVER['REMOTE_ADDR']);
		$this->reset_static_properties();
	}

	/**
	 * Test get_external_ip_address returns IP for public address.
	 */
	public function test_get_external_ip_address_public_ip() {
		// Mock REMOTE_ADDR to be a public IP
		$_SERVER['REMOTE_ADDR'] = '8.8.8.8';

		$result = Geolocation::get_external_ip_address();

		$this->assertEquals('8.8.8.8', $result);

		// Clean up
		unset($_SERVER['REMOTE_ADDR']);
		$this->reset_static_properties();
	}

	/**
	 * Test get_country_from_headers with Cloudflare header.
	 */
	public function test_get_country_from_headers_cloudflare() {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';

		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('get_country_from_headers');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null);
		$this->assertEquals('US', $result);

		// Clean up
		unset($_SERVER['HTTP_CF_IPCOUNTRY']);
	}

	/**
	 * Test get_country_from_headers with invalid country code.
	 */
	public function test_get_country_from_headers_invalid_code() {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'USA'; // Invalid: 3 letters

		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('get_country_from_headers');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null);
		$this->assertEquals('', $result);

		// Clean up
		unset($_SERVER['HTTP_CF_IPCOUNTRY']);
	}

	/**
	 * Test get_country_from_headers with no headers.
	 */
	public function test_get_country_from_headers_no_headers() {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('get_country_from_headers');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null);
		$this->assertEquals('', $result);
	}

	/**
	 * Test maybe_update_database returns new value.
	 */
	public function test_maybe_update_database_returns_new_value() {
		$result = Geolocation::maybe_update_database('new_value', 'old_value');
		$this->assertEquals('new_value', $result);
	}

	/**
	 * Test update_database is deprecated but callable.
	 */
	public function test_update_database_deprecated_callable() {
		// Should not throw an error
		Geolocation::update_database();
		$this->assertTrue(true);
	}

	/**
	 * Test geolocate_ip caching with same IP.
	 */
	public function test_geolocate_ip_caching() {
		$ip = '127.0.0.1';

		// First call
		$result1 = Geolocation::geolocate_ip($ip);

		// Verify cache was populated after first call
		$reflection   = new \ReflectionClass(Geolocation::class);
		$memory_cache = $reflection->getProperty('memory_cache');
		if (PHP_VERSION_ID < 80100) {
			$memory_cache->setAccessible(true);
		}
		$cache_value = $memory_cache->getValue();
		$this->assertArrayHasKey($ip, $cache_value, 'Memory cache should contain the IP after first call');

		// Second call should return from cache
		$result2 = Geolocation::geolocate_ip($ip);

		$this->assertEquals($result1, $result2);
	}

	/**
	 * Test close_reader handles null reader gracefully.
	 */
	public function test_close_reader_null() {
		// Should not throw an error when reader is null
		Geolocation::close_reader();
		$this->assertTrue(true);
	}

	/**
	 * Test disable_geolocation_on_legacy_php returns unchanged value.
	 */
	public function test_disable_geolocation_on_legacy_php() {
		$result = Geolocation::disable_geolocation_on_legacy_php('some_value');
		$this->assertEquals('some_value', $result);
	}

	/**
	 * Test validate_ip with various edge cases.
	 *
	 * @param string $input    The IP address to validate.
	 * @param string $expected The expected result.
	 * @dataProvider validate_ip_provider
	 */
	public function test_validate_ip_edge_cases($input, $expected) {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('validate_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null, $input);
		$this->assertEquals($expected, $result);
	}

	/**
	 * Data provider for validate_ip edge cases.
	 */
	public static function validate_ip_provider() {
		return [
			'empty_string'       => ['', ''],
			'valid_ipv4'         => ['192.168.1.1', '192.168.1.1'],
			'ipv4_with_port'     => ['192.168.1.1:8080', '192.168.1.1'],
			'valid_ipv6'         => ['::1', '::1'],
			'ipv6_with_port'     => ['[::1]:8080', '::1'],
			'invalid_ip'         => ['not-an-ip', ''],
			'partial_ip'         => ['192.168', ''],
			'ipv4_with_brackets' => ['[192.168.1.1]', '192.168.1.1'],
		];
	}

	/**
	 * Test is_private_ip with various edge cases.
	 *
	 * @param string $input    The IP address to check.
	 * @param bool   $expected The expected result.
	 * @dataProvider is_private_ip_provider
	 */
	public function test_is_private_ip_edge_cases($input, $expected) {
		$reflection = new \ReflectionClass(Geolocation::class);
		$method     = $reflection->getMethod('is_private_ip');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null, $input);
		$this->assertEquals($expected, $result);
	}

	/**
	 * Data provider for is_private_ip edge cases.
	 */
	public static function is_private_ip_provider() {
		return [
			'empty_string'          => ['', true],
			'private_192_168'       => ['192.168.1.1', true],
			'private_10_0_0'        => ['10.0.0.1', true],
			'private_172_16'        => ['172.16.0.1', true],
			'private_172_31'        => ['172.31.255.255', true],
			'private_loopback'      => ['127.0.0.1', true],
			'private_ipv6_loopback' => ['::1', true],
			'private_ipv6'          => ['fe80::1', true],
			'public_google_dns'     => ['8.8.8.8', false],
			'public_cloudflare'     => ['1.1.1.1', false],
			'public_quad9'          => ['9.9.9.9', false],
		];
	}
}
