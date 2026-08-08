<?php
/**
 * Tests for the Screenshot helper class.
 *
 * @package WP_Ultimo\Tests\Helpers
 */

namespace WP_Ultimo\Helpers;

use WP_UnitTestCase;

require_once __DIR__ . '/screenshot-test-imagecreatefromstring.php';
require_once __DIR__ . '/class-imagick-webp-test-editor.php';

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
		remove_all_filters('wu_screenshot_test_imagecreatefromstring');
		remove_all_filters('wp_image_editors');
		remove_all_filters('pre_http_request');
		parent::tear_down();
	}

	// ------------------------------------------------------------------
	// Helper stubs
	// ------------------------------------------------------------------

	private function png_body() {
		$image = imagecreatetruecolor(10, 10);
		imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 20, 40, 60));
		imagesetpixel($image, 5, 5, imagecolorallocate($image, 200, 180, 160));
		ob_start();
		imagepng($image);
		$body = ob_get_clean();
		imagedestroy($image);

		return $body;
	}

	private function jpeg_body() {
		$image = imagecreatetruecolor(10, 10);
		imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 20, 40, 60));
		imagesetpixel($image, 5, 5, imagecolorallocate($image, 200, 180, 160));
		ob_start();
		imagejpeg($image);
		$body = ob_get_clean();
		imagedestroy($image);

		return $body;
	}

	private function webp_body() {
		$image = imagecreatetruecolor(10, 10);
		imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 20, 40, 60));
		imagesetpixel($image, 5, 5, imagecolorallocate($image, 200, 180, 160));
		ob_start();
		imagewebp($image);
		$body = ob_get_clean();
		imagedestroy($image);

		return $body;
	}

	private function solid_png_body($transparent = false) {
		$image = imagecreatetruecolor(10, 10);

		if ($transparent) {
			imagesavealpha($image, true);
			$color = imagecolorallocatealpha($image, 255, 255, 255, 127);
		} else {
			$color = imagecolorallocate($image, 255, 255, 255);
		}

		imagefilledrectangle($image, 0, 0, 9, 9, $color);
		ob_start();
		imagepng($image);
		$body = ob_get_clean();
		imagedestroy($image);

		return $body;
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

	public function test_api_url_requests_webp_screenshot_output_when_supported() {
		if ( ! wp_image_editor_supports(['mime_type' => 'image/webp'])) {
			$this->markTestSkipped('The active image editor does not support WebP.');
		}

		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('screenshot.type=webp', $url);
	}

	public function test_api_url_requests_png_screenshot_output_without_webp_support() {
		add_filter('wp_image_editors', '__return_empty_array');

		$url = Screenshot::api_url('example.com');
		$this->assertStringContainsString('screenshot.type=png', $url);
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
			function ($url, $domain) {
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
			function ($url, $domain) {
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
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => 'not an image',
				];
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');
		$this->assertFalse($result);
	}

	public function test_save_image_returns_false_for_empty_body() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => '',
				];
			}
		);

		$this->assertFalse(Screenshot::save_image_from_url('https://example.com/test'));
	}

	public function test_save_image_returns_false_for_all_white_png() {
		$body = $this->solid_png_body();
		add_filter(
			'pre_http_request',
			function () use ($body) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $body,
				];
			}
		);

		$this->assertFalse(Screenshot::save_image_from_url('https://example.com/test'));
	}

	public function test_save_image_returns_false_for_all_transparent_png() {
		$body = $this->solid_png_body(true);
		add_filter(
			'pre_http_request',
			function () use ($body) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $body,
				];
			}
		);

		$this->assertFalse(Screenshot::save_image_from_url('https://example.com/test'));
	}

	public function test_save_image_returns_false_on_http_error() {
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
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
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
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $this->jpeg_body(),
				];
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');

		$this->assertIsInt($result);
		$this->assertGreaterThan(0, $result);
	}

	public function test_save_image_accepts_webp_body() {
		if ( ! function_exists('imagewebp')) {
			$this->markTestSkipped('GD does not support creating WebP images.');
		}

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $this->webp_body(),
				];
			}
		);

		$result = Screenshot::save_image_from_url('https://example.com/test');

		$this->assertIsInt($result);
		$this->assertGreaterThan(0, $result);
	}

	public function test_blank_image_accepts_webp_when_imagick_supports_it_without_gd() {
		add_filter(
			'wp_image_editors',
			function () {
				return [Imagick_WebP_Test_Editor::class];
			}
		);
		add_filter('wu_screenshot_test_imagecreatefromstring', '__return_false');

		$method = new \ReflectionMethod(Screenshot::class, 'is_blank_image');
		$result = $method->invoke(null, $this->webp_body(), 'webp');

		$this->assertFalse($result);
	}

	// ------------------------------------------------------------------
	// take_screenshot — fallback behaviour
	// ------------------------------------------------------------------

	public function test_take_screenshot_returns_false_when_both_providers_fail() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
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
			function () use ($png_body) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
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
			function ($preempt, $args, $url) use (&$call_count, $png_body) {
				$call_count++;

				// First call (Microlink) fails, second call (thum.io) succeeds.
				if (strpos($url, 'microlink') !== false) {
					return [
						'response' => [
							'code'    => 429,
							'message' => 'Too Many Requests',
						],
						'body'     => '',
					];
				}

				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
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

	public function test_take_screenshot_falls_back_when_primary_is_blank() {
		$call_count = 0;
		$blank_body = $this->solid_png_body();
		$valid_body = $this->png_body();

		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) use (&$call_count, $blank_body, $valid_body) {
				$call_count++;

				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => false !== strpos($url, 'microlink') ? $blank_body : $valid_body,
				];
			},
			10,
			3
		);

		$result = Screenshot::take_screenshot('example.com');

		$this->assertIsInt($result);
		$this->assertSame(2, $call_count);
	}

	public function test_take_screenshot_does_not_call_fallback_when_primary_succeeds() {
		$call_count = 0;
		$png_body   = $this->png_body();

		add_filter(
			'pre_http_request',
			function () use (&$call_count, $png_body) {
				$call_count++;

				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $png_body,
				];
			}
		);

		Screenshot::take_screenshot('example.com');

		$this->assertSame(1, $call_count, 'Expected only 1 HTTP call when primary succeeds.');
	}
}
