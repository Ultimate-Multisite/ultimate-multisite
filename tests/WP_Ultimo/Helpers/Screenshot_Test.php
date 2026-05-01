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
		remove_all_filters('wu_mshots_retry_delays');
		remove_all_filters('pre_http_request');
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
	// take_screenshot / save_image_from_url — non-retry paths
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
	}

	// ------------------------------------------------------------------
	// mShots GIF placeholder retry logic
	// ------------------------------------------------------------------

	/**
	 * Helper — build a GIF89a stub body (passes GIF magic-byte check).
	 *
	 * @return string
	 */
	private function gif_body() {
		return "GIF89a\x01\x00\x01\x00\x00\x00\x00;";
	}

	/**
	 * Helper — build a minimal JPEG stub body (passes JPEG magic-byte check).
	 *
	 * @return string
	 */
	private function jpeg_body() {
		return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00";
	}

	/**
	 * Helper — register a pre_http_request filter that returns $gif_count GIF responses
	 * and then switches to JPEG for all subsequent requests.
	 *
	 * @param int &$call_count Reference to call counter (incremented on each request).
	 * @param int  $gif_count  Number of initial GIF responses before switching to JPEG.
	 */
	private function mock_gif_then_jpeg( &$call_count, $gif_count ) {
		$gif_body  = $this->gif_body();
		$jpeg_body = $this->jpeg_body();

		add_filter(
			'pre_http_request',
			function () use ( &$call_count, $gif_body, $jpeg_body, $gif_count ) {
				$call_count++;
				$body = ( $call_count <= $gif_count ) ? $gif_body : $jpeg_body;

				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $body,
				];
			}
		);
	}

	/**
	 * Helper — register a pre_http_request filter that always returns a GIF.
	 *
	 * @param int &$call_count Reference to call counter.
	 */
	private function mock_always_gif( &$call_count ) {
		$gif_body = $this->gif_body();

		add_filter(
			'pre_http_request',
			function () use ( &$call_count, $gif_body ) {
				$call_count++;

				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $gif_body,
				];
			}
		);
	}

	/**
	 * When the first response is a GIF placeholder and the second is a JPEG,
	 * save_image_from_url must make exactly 2 HTTP requests.
	 */
	public function test_gif_placeholder_triggers_one_retry() {
		// Zero-second delays so the test does not actually sleep.
		add_filter('wu_mshots_retry_delays', function() { return [0]; });

		$call_count = 0;
		$this->mock_gif_then_jpeg($call_count, 1);

		Screenshot::save_image_from_url('https://example.com/test');

		$this->assertSame(2, $call_count, 'Expected exactly 2 HTTP calls: 1 GIF + 1 JPEG retry.');
	}

	/**
	 * When all responses are GIF placeholders, save_image_from_url returns false and
	 * makes exactly (1 + count(delays)) HTTP requests before giving up.
	 */
	public function test_all_gif_responses_return_false_after_retries() {
		// Use 3 retries (delays: 0, 0, 0) → 4 total attempts.
		add_filter('wu_mshots_retry_delays', function() { return [0, 0, 0]; });

		$call_count = 0;
		$this->mock_always_gif($call_count);

		$result = Screenshot::save_image_from_url('https://example.com/test');

		$this->assertFalse($result, 'Expected false when all retries return GIF placeholder.');
		$this->assertSame(4, $call_count, 'Expected 4 HTTP calls: 1 initial + 3 retries.');
	}

	/**
	 * When wu_mshots_retry_delays returns an empty array, no retries are performed:
	 * a GIF on the first (and only) attempt must immediately return false.
	 */
	public function test_empty_retry_delays_disables_retries() {
		add_filter('wu_mshots_retry_delays', function() { return []; });

		$call_count = 0;
		$this->mock_always_gif($call_count);

		$result = Screenshot::save_image_from_url('https://example.com/test');

		$this->assertFalse($result);
		$this->assertSame(1, $call_count, 'Expected exactly 1 HTTP call when retries are disabled.');
	}

	/**
	 * A non-200 response after a GIF placeholder must abort immediately without
	 * further retries.
	 */
	public function test_non_200_response_after_gif_aborts_immediately() {
		add_filter('wu_mshots_retry_delays', function() { return [0, 0, 0]; });

		$call_count = 0;
		$gif_body   = $this->gif_body();

		add_filter(
			'pre_http_request',
			function () use ( &$call_count, $gif_body ) {
				$call_count++;
				// First request returns GIF, second returns 503.
				if ( $call_count === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => $gif_body,
					];
				}

				return [
					'response' => [ 'code' => 503, 'message' => 'Service Unavailable' ],
					'body'     => '',
				];
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');

		$this->assertFalse($result);
		$this->assertSame(2, $call_count, 'Expected 2 HTTP calls: 1 GIF + 1 non-200, then stop.');
	}

	/**
	 * A WP_Error response on a retry attempt must abort immediately without
	 * further retries.
	 */
	public function test_wp_error_on_retry_aborts_immediately() {
		add_filter('wu_mshots_retry_delays', function() { return [0, 0, 0]; });

		$call_count = 0;
		$gif_body   = $this->gif_body();

		add_filter(
			'pre_http_request',
			function () use ( &$call_count, $gif_body ) {
				$call_count++;
				if ( $call_count === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => $gif_body,
					];
				}

				return new \WP_Error('http_request_failed', 'Connection timed out.');
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');

		$this->assertFalse($result);
		$this->assertSame(2, $call_count, 'Expected 2 HTTP calls: 1 GIF + 1 WP_Error, then stop.');
	}

	/**
	 * A non-GIF, non-JPEG body (e.g. plain text) must return false immediately,
	 * without triggering any retries.
	 */
	public function test_non_gif_non_jpeg_body_does_not_retry() {
		add_filter('wu_mshots_retry_delays', function() { return [0, 0, 0]; });

		$call_count = 0;

		add_filter(
			'pre_http_request',
			function () use ( &$call_count ) {
				$call_count++;

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => 'unexpected plain-text body',
				];
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');

		$this->assertFalse($result);
		$this->assertSame(1, $call_count, 'Expected exactly 1 HTTP call for non-GIF unexpected body.');
	}

	/**
	 * The default delay schedule contains exactly 5 entries (one per retry).
	 * Verify the filter default value shape without relying on timing.
	 */
	public function test_default_retry_delays_contains_five_entries() {
		$delays = apply_filters('wu_mshots_retry_delays', [3, 5, 8, 12, 15]);
		$this->assertCount(5, $delays, 'Default retry schedule should have 5 delay entries.');
	}

	/**
	 * The default delay schedule uses ascending values (increasing backoff).
	 */
	public function test_default_retry_delays_are_ascending() {
		$delays = apply_filters('wu_mshots_retry_delays', [3, 5, 8, 12, 15]);
		$sorted = $delays;
		sort($sorted);
		$this->assertSame($sorted, $delays, 'Default retry delays should be in ascending order.');
	}
}
