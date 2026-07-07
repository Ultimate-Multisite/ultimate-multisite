<?php

namespace WP_Ultimo\Domain_Mapping;

/**
 * Tests for the Helper class.
 */
class Helper_Test extends \WP_UnitTestCase {

	/**
	 * Test class exists.
	 */
	public function test_class_exists() {

		$this->assertTrue(class_exists(Helper::class));
	}

	/**
	 * Test providers array is defined.
	 */
	public function test_providers_defined() {

		$ref = new \ReflectionProperty(Helper::class, 'providers');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$providers = $ref->getValue();

		$this->assertIsArray($providers);
		$this->assertNotEmpty($providers);
	}

	/**
	 * Test is_development_mode returns truthy/falsy value.
	 */
	public function test_is_development_mode_returns_value() {

		$result = Helper::is_development_mode();

		// Returns int (0 or 1) from preg_match, but should be treated as bool
		$this->assertTrue($result === 0 || $result === 1 || is_bool($result));
	}

	/**
	 * Test is_development_mode applies filter.
	 */
	public function test_is_development_mode_applies_filter() {

		$fired = false;

		add_filter('wu_is_development_mode', function ($is_dev, $site_url) use (&$fired) {
			$fired = true;
			return $is_dev;
		}, 10, 2);

		Helper::is_development_mode();

		$this->assertTrue($fired);
	}

	/**
	 * Test get_local_network_ip returns string or false.
	 */
	public function test_get_local_network_ip() {

		$result = Helper::get_local_network_ip();

		// Can be string or false depending on SERVER_ADDR
		$this->assertTrue($result === false || is_string($result));
	}

	/**
	 * Test get_network_public_ip in development mode.
	 */
	public function test_get_network_public_ip_development() {

		add_filter('site_url', function () {
			return 'http://localhost/test';
		});

		$result = Helper::get_network_public_ip();

		// In dev mode, should return local IP
		$this->assertTrue($result === false || is_string($result));

		remove_filter('site_url', function () {
			return 'http://localhost/test';
		});
	}

	/**
	 * Test has_valid_ssl_certificate with empty domain.
	 */
	public function test_has_valid_ssl_certificate_empty() {

		$result = Helper::has_valid_ssl_certificate('');

		$this->assertFalse($result);
	}

	/**
	 * Test has_valid_ssl_certificate with invalid domain.
	 */
	public function test_has_valid_ssl_certificate_invalid() {

		// Use a domain that definitely doesn't have SSL
		$result = Helper::has_valid_ssl_certificate('invalid-domain-for-testing-12345.test');

		$this->assertFalse($result);
	}

	/**
	 * Test constructor is private.
	 */
	public function test_constructor_is_private() {

		$ref         = new \ReflectionClass(Helper::class);
		$constructor = $ref->getConstructor();

		$this->assertTrue($constructor->isPrivate());
	}

	/**
	 * Test class cannot be instantiated.
	 */
	public function test_cannot_instantiate() {

		$this->expectException(\Error::class);

		new Helper();
	}
}
