<?php

namespace WP_Ultimo\Helpers;

/**
 * Tests for the WooCommerce_API_Client class.
 */
class WooCommerce_API_Client_Test extends \WP_UnitTestCase {

	/**
	 * Test constructor sets base URL with trailing slash.
	 */
	public function test_constructor_sets_base_url() {

		$client = new WooCommerce_API_Client('https://example.com');

		$ref = new \ReflectionProperty($client, 'base_url');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertSame('https://example.com/', $ref->getValue($client));
	}

	/**
	 * Test constructor handles URL with trailing slash.
	 */
	public function test_constructor_handles_trailing_slash() {

		$client = new WooCommerce_API_Client('https://example.com/');

		$ref = new \ReflectionProperty($client, 'base_url');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertSame('https://example.com/', $ref->getValue($client));
	}

	/**
	 * Test execute_request builds correct URL for GET.
	 */
	public function test_execute_request_get_url() {

		$client = new WooCommerce_API_Client('https://store.example.com');

		// Mock the HTTP response
		add_filter('pre_http_request', function ($preempt, $args, $url) {
			// Verify URL structure
			$this->assertStringContainsString('wp-json/wc/store/v1/products', $url);
			$this->assertSame('GET', $args['method']);

			return [
				'response' => ['code' => 200],
				'body'     => wp_json_encode(['test' => 'data']),
			];
		}, 10, 3);

		$ref = new \ReflectionMethod($client, 'execute_request');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$result = $ref->invoke($client, 'products', ['per_page' => 10]);

		$this->assertIsArray($result);
		$this->assertSame('data', $result['test']);

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test execute_request handles POST method.
	 */
	public function test_execute_request_post() {

		$client = new WooCommerce_API_Client('https://store.example.com');

		add_filter('pre_http_request', function ($preempt, $args, $url) {
			$this->assertSame('POST', $args['method']);
			$this->assertSame('application/json', $args['headers']['Content-Type']);

			return [
				'response' => ['code' => 201],
				'body'     => wp_json_encode(['created' => true]),
			];
		}, 10, 3);

		$ref = new \ReflectionMethod($client, 'execute_request');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$result = $ref->invoke($client, 'products', ['name' => 'Test'], 'POST');

		$this->assertIsArray($result);
		$this->assertTrue($result['created']);

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test execute_request handles WP_Error response.
	 */
	public function test_execute_request_wp_error() {

		$client = new WooCommerce_API_Client('https://store.example.com');

		add_filter('pre_http_request', function () {
			return new \WP_Error('http_request_failed', 'Connection refused');
		});

		$ref = new \ReflectionMethod($client, 'execute_request');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$result = $ref->invoke($client, 'products');

		$this->assertWPError($result);
		$this->assertSame('http_request_failed', $result->get_error_code());

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test execute_request handles non-200 status codes.
	 */
	public function test_execute_request_error_status() {

		$client = new WooCommerce_API_Client('https://store.example.com');

		add_filter('pre_http_request', function () {
			return [
				'response' => ['code' => 404],
				'body'     => 'Not Found',
			];
		});

		$ref = new \ReflectionMethod($client, 'execute_request');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$result = $ref->invoke($client, 'products');

		$this->assertWPError($result);
		$this->assertSame('woocommerce_api_error', $result->get_error_code());

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test execute_request handles invalid JSON.
	 */
	public function test_execute_request_invalid_json() {

		$client = new WooCommerce_API_Client('https://store.example.com');

		add_filter('pre_http_request', function () {
			return [
				'response' => ['code' => 200],
				'body'     => 'not valid json{{{',
			];
		});

		$ref = new \ReflectionMethod($client, 'execute_request');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$result = $ref->invoke($client, 'products');

		$this->assertWPError($result);
		$this->assertSame('json_decode_error', $result->get_error_code());

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test get_addons calls execute_request with correct params.
	 */
	public function test_get_addons() {

		$client = new WooCommerce_API_Client('https://store.example.com');

		add_filter('pre_http_request', function ($preempt, $args, $url) {
			$this->assertStringContainsString('products', $url);
			$this->assertStringContainsString('per_page=100', $url);
			$this->assertStringContainsString('downloadable=1', $url);
			$this->assertStringContainsString('type=subscription', $url);
			$this->assertStringContainsString('tag=addon', $url);

			return [
				'response' => ['code' => 200],
				'body'     => wp_json_encode([
					[
						'id'   => 1,
						'name' => 'Addon 1',
					],
					[
						'id'   => 2,
						'name' => 'Addon 2',
					],
				]),
			];
		}, 10, 3);

		$result = $client->get_addons();

		$this->assertIsArray($result);
		$this->assertCount(2, $result);

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test execute_request handles 500 error.
	 */
	public function test_execute_request_server_error() {

		$client = new WooCommerce_API_Client('https://store.example.com');

		add_filter('pre_http_request', function () {
			return [
				'response' => ['code' => 500],
				'body'     => 'Internal Server Error',
			];
		});

		$ref = new \ReflectionMethod($client, 'execute_request');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$result = $ref->invoke($client, 'products');

		$this->assertWPError($result);
		$this->assertStringContainsString('500', $result->get_error_message());

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test execute_request timeout setting.
	 */
	public function test_execute_request_timeout() {

		$client = new WooCommerce_API_Client('https://store.example.com');

		add_filter('pre_http_request', function ($preempt, $args) {
			$this->assertSame(30, $args['timeout']);

			return [
				'response' => ['code' => 200],
				'body'     => wp_json_encode([]),
			];
		}, 10, 2);

		$ref = new \ReflectionMethod($client, 'execute_request');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($client, 'products');

		remove_all_filters('pre_http_request');
	}
}
