<?php
/**
 * Tests for the Screenshot helper class.
 *
 * @package WP_Ultimo\Tests\Helpers
 */

namespace WP_Ultimo\Helpers;

use WP_UnitTestCase;

/**
 * Tests for the Screenshot helper class.
 *
 * @group screenshot
 */
class Screenshot_Test extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		remove_all_filters('wu_screenshot_api_url');
		parent::tear_down();
	}

	// ------------------------------------------------------------------
	// api_url
	// ------------------------------------------------------------------

	/**
	 * Test api_url returns a string.
	 */
	public function test_api_url_returns_string() {
		$url = Screenshot::api_url('example.com');
		$this->assertIsString($url);
	}

	/**
	 * Test api_url contains the domain.
	 */
	public function test_api_url_contains_domain() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('example.com', $url);
	}

	/**
	 * Test api_url uses WordPress.com mShots service.
	 */
	public function test_api_url_uses_mshots() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('s.wordpress.com/mshots/v1/', $url);
	}

	/**
	 * Test api_url includes width query parameter.
	 */
	public function test_api_url_includes_width_parameter() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('w=1280', $url);
	}

	/**
	 * Test api_url prepends https:// to the domain before encoding.
	 */
	public function test_api_url_prepends_https_to_domain() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString(rawurlencode('https://example.com'), $url);
	}

	/**
	 * Test api_url filter can override the URL.
	 */
	public function test_api_url_filter_can_override() {
		add_filter(
			'wu_screenshot_api_url',
			function ( $url, $domain ) {
				return 'https://custom-screenshot.com/' . $domain;
			},
			10,
			2
		);

		$url = Screenshot::api_url('example.com');
		$this->assertEquals('https://custom-screenshot.com/example.com', $url);
	}

	// ------------------------------------------------------------------
	// take_screenshot
	// ------------------------------------------------------------------

	/**
	 * Test take_screenshot returns false when response body is not a JPEG.
	 */
	public function test_take_screenshot_returns_false_for_invalid_url() {
		// Will fail because the mock body is not a valid JPEG.
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => 'not a jpeg',
				];
			}
		);

		$result = Screenshot::take_screenshot('http://nonexistent.invalid');
		$this->assertFalse($result);

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test take_screenshot returns false on HTTP error.
	 */
	public function test_take_screenshot_returns_false_on_http_error() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 500,
						'message' => 'Server Error',
					],
					'body'     => '',
				];
			}
		);

		$result = Screenshot::take_screenshot('http://example.com');
		$this->assertFalse($result);

		remove_all_filters('pre_http_request');
	}
}
