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
		remove_all_filters('wu_screenshot_fallback_api_url');
		remove_all_filters('pre_http_request');
		parent::tear_down();
	}

	// ------------------------------------------------------------------
	// Helper stubs
	// ------------------------------------------------------------------

	private function png_body() {
		return "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a" . str_repeat("\x00", 100);
	}

	private function jpeg_body() {
		return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00";
	}

	// ------------------------------------------------------------------
	// api_url (Microlink — primary)
	// ------------------------------------------------------------------

	public function test_api_url_returns_string() {
		$url = Screenshot::api_url('example.com');
		$this->assertIsString($url);
	}

	public function test_api_url_contains_domain() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('example.com', $url);
	}

	public function test_api_url_uses_microlink() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('api.microlink.io', $url);
	}

	public function test_api_url_includes_screenshot_param() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('screenshot=true', $url);
	}

	public function test_api_url_includes_embed_param() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('embed=screenshot.url', $url);
	}

	public function test_api_url_includes_default_viewport_width() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('viewport.width=1024', $url);
	}

	public function test_api_url_includes_default_viewport_height() {
		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('viewport.height=768', $url);
	}

	public function test_api_url_accepts_custom_dimensions() {
		$url = Screenshot::api_url('example.com', 1920, 1080);
		$this->assertStringContainsString('viewport.width=1920', $url);
		$this->assertStringContainsString('viewport.height=1080', $url);
	}

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
	// fallback_api_url (thum.io)
	// ------------------------------------------------------------------

	public function test_fallback_api_url_uses_thum_io() {
		$url = Screenshot::fallback_api_url('example.com');
		$this->assertStringContainsString('image.thum.io', $url);
	}

	public function test_fallback_api_url_includes_default_width() {
		$url = Screenshot::fallback_api_url('example.com');
		$this->assertStringContainsString('width/1024', $url);
	}

	public function test_fallback_api_url_includes_default_crop() {
		$url = Screenshot::fallback_api_url('example.com');
		$this->assertStringContainsString('crop/768', $url);
	}

	public function test_fallback_api_url_includes_noanimate() {
		$url = Screenshot::fallback_api_url('example.com');
		$this->assertStringContainsString('noanimate', $url);
	}

	public function test_fallback_api_url_accepts_custom_dimensions() {
		$url = Screenshot::fallback_api_url('example.com', 1920, 1080);
		$this->assertStringContainsString('width/1920', $url);
		$this->assertStringContainsString('crop/1080', $url);
	}

	public function test_fallback_api_url_filter_can_override() {
		add_filter(
			'wu_screenshot_fallback_api_url',
			function ( $url, $domain ) {
				return 'https://other-fallback.com/' . $domain;
			},
			10,
			2
		);

		$url = Screenshot::fallback_api_url('example.com');
		$this->assertEquals('https://other-fallback.com/example.com', $url);
	}

	// ------------------------------------------------------------------
	// save_image_from_url — image format detection
	// ------------------------------------------------------------------

	public function test_save_image_returns_false_for_non_image_body() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => 'not an image',
				];
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');
		$this->assertFalse($result);
	}

	public function test_save_image_returns_false_on_http_error() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 500, 'message' => 'Server Error' ],
					'body'     => '',
				];
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');
		$this->assertFalse($result);
	}

	public function test_save_image_returns_false_on_wp_error() {
		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error('http_request_failed', 'Connection timed out.');
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');
		$this->assertFalse($result);
	}

	public function test_save_image_accepts_png_body() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => $this->png_body(),
				];
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');

		// Returns an attachment ID (integer) on success.
		$this->assertIsInt($result);
		$this->assertGreaterThan(0, $result);
	}

	public function test_save_image_accepts_jpeg_body() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => $this->jpeg_body(),
				];
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');

		$this->assertIsInt($result);
		$this->assertGreaterThan(0, $result);
	}

	// ------------------------------------------------------------------
	// take_screenshot — fallback behaviour
	// ------------------------------------------------------------------

	public function test_take_screenshot_returns_false_when_both_providers_fail() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => 'not an image',
				];
			}
		);

		$result = Screenshot::take_screenshot('example.com');
		$this->assertFalse($result);
	}

	public function test_take_screenshot_succeeds_on_primary_provider() {
		$png_body = $this->png_body();

		add_filter(
			'pre_http_request',
			function () use ( $png_body ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => $png_body,
				];
			}
		);

		$result = Screenshot::take_screenshot('example.com');
		$this->assertIsInt($result);
		$this->assertGreaterThan(0, $result);
	}

	public function test_take_screenshot_falls_back_to_thum_io_on_primary_failure() {
		$call_count = 0;
		$png_body   = $this->png_body();

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$call_count, $png_body ) {
				$call_count++;

				// First call (Microlink) fails, second call (thum.io) succeeds.
				if (strpos($url, 'microlink') !== false) {
					return [
						'response' => [ 'code' => 429, 'message' => 'Too Many Requests' ],
						'body'     => '',
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => $png_body,
				];
			},
			10,
			3
		);

		$result = Screenshot::take_screenshot('example.com');

		$this->assertIsInt($result, 'Expected fallback (thum.io) to succeed after Microlink failure.');
		$this->assertSame(2, $call_count, 'Expected 2 HTTP calls: 1 Microlink (failed) + 1 thum.io.');
	}

	public function test_take_screenshot_does_not_call_fallback_when_primary_succeeds() {
		$call_count = 0;
		$png_body   = $this->png_body();

		add_filter(
			'pre_http_request',
			function () use ( &$call_count, $png_body ) {
				$call_count++;

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => $png_body,
				];
			}
		);

		Screenshot::take_screenshot('example.com');

		$this->assertSame(1, $call_count, 'Expected only 1 HTTP call when primary succeeds.');
	}
}
