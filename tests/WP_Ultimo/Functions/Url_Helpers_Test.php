<?php
/**
 * Tests for URL helper functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for URL helper functions.
 */
class Url_Helpers_Test extends WP_UnitTestCase {

	/**
	 * Test wu_replace_scheme removes http scheme.
	 */
	public function test_replace_scheme_removes_http(): void {
		$url = 'http://example.com/path';
		$this->assertEquals('example.com/path', wu_replace_scheme($url, ''));
	}

	/**
	 * Test wu_replace_scheme removes https scheme.
	 */
	public function test_replace_scheme_removes_https(): void {
		$url = 'https://example.com/path';
		$this->assertEquals('example.com/path', wu_replace_scheme($url, ''));
	}

	/**
	 * Test wu_replace_scheme converts http to https.
	 */
	public function test_replace_scheme_http_to_https(): void {
		$url = 'http://example.com/path';
		$this->assertEquals('https://example.com/path', wu_replace_scheme($url, 'https://'));
	}

	/**
	 * Test wu_replace_scheme converts https to http.
	 */
	public function test_replace_scheme_https_to_http(): void {
		$url = 'https://example.com/path';
		$this->assertEquals('http://example.com/path', wu_replace_scheme($url, 'http://'));
	}

	/**
	 * Test wu_replace_scheme with URL without scheme.
	 */
	public function test_replace_scheme_no_scheme(): void {
		$url = 'example.com/path';
		$this->assertEquals('example.com/path', wu_replace_scheme($url, ''));
	}

	/**
	 * Test wu_replace_scheme with protocol-relative URL.
	 */
	public function test_replace_scheme_protocol_relative(): void {
		$url = '//example.com/path';
		$this->assertEquals('//example.com/path', wu_replace_scheme($url, ''));
	}

	/**
	 * Test wu_network_admin_url generates correct URL.
	 */
	public function test_network_admin_url_basic(): void {
		$url = wu_network_admin_url('wp-ultimo');
		$this->assertStringContainsString('admin.php?page=wp-ultimo', $url);
	}

	/**
	 * Test wu_network_admin_url with query parameters.
	 */
	public function test_network_admin_url_with_query_params(): void {
		$url = wu_network_admin_url('wp-ultimo', [
			'tab' => 'settings',
			'id'  => 123,
		]);
		$this->assertStringContainsString('admin.php?page=wp-ultimo', $url);
		$this->assertStringContainsString('tab=settings', $url);
		$this->assertStringContainsString('id=123', $url);
	}

	/**
	 * Test wu_network_admin_url returns network admin URL.
	 */
	public function test_network_admin_url_is_network_admin(): void {
		$url = wu_network_admin_url('wp-ultimo');
		$this->assertStringContainsString(network_admin_url(), $url);
	}

	/**
	 * Test wu_ajax_url generates URL with wu-ajax parameter.
	 */
	public function test_ajax_url_has_wu_ajax_param(): void {
		$url = wu_ajax_url();
		$this->assertStringContainsString('wu-ajax=1', $url);
	}

	/**
	 * Test wu_ajax_url includes nonce.
	 */
	public function test_ajax_url_has_nonce(): void {
		$url = wu_ajax_url();
		$this->assertStringContainsString('r=', $url);
	}

	/**
	 * Test wu_ajax_url with when parameter.
	 */
	public function test_ajax_url_with_when_param(): void {
		$url = wu_ajax_url('plugins_loaded');
		$this->assertStringContainsString('wu-when=', $url);
		// The base64 value is embedded in the URL (without padding = char)
		$this->assertMatchesRegularExpression('/wu-when=[a-zA-Z0-9]+/', $url);
	}

	/**
	 * Test wu_ajax_url without when parameter.
	 */
	public function test_ajax_url_without_when_param(): void {
		$url = wu_ajax_url(null);
		$this->assertStringNotContainsString('wu-when=', $url);
	}

	/**
	 * Test wu_ajax_url with custom query args.
	 */
	public function test_ajax_url_with_query_args(): void {
		$url = wu_ajax_url(null, [
			'action' => 'test_action',
			'id'     => 42,
		]);
		$this->assertStringContainsString('action=test_action', $url);
		$this->assertStringContainsString('id=42', $url);
	}

	/**
	 * Test wu_ajax_url with specific site_id.
	 */
	public function test_ajax_url_with_site_id(): void {
		$url = wu_ajax_url(null, [], 1);
		$this->assertIsString($url);
		$this->assertStringContainsString('wu-ajax=1', $url);
	}
}
