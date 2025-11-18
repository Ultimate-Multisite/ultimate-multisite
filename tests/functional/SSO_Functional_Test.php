<?php

use WP_Ultimo\SSO\SSO;
use WP_Ultimo\SSO\SSO_Broker;
use WP_Ultimo\SSO\Exception\SSO_Session_Exception;

/**
 * Functional-style tests for SSO behaviors without full redirect flows.
 */
class SSO_Functional_Test extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Flush caches to ensure clean state
		wp_cache_flush();
		// Ensure SSO is available.
		SSO::get_instance();
		// Default enable SSO during these tests.
		add_filter('wu_sso_enabled', '__return_true');
		// Stabilize salt across full suite.
		add_filter(
			'wu_sso_salt',
			function () {
				return 'testsalt';
			}
		);
	}

	protected function tearDown(): void {
		remove_all_filters('wu_sso_enabled');
		remove_all_filters('wu_sso_get_url_path');
		remove_all_filters('wu_sso_salt');
		remove_all_filters('network_home_url');
		parent::tearDown();
	}

	public function test_custom_url_path_filter_applies(): void {
		add_filter(
			'wu_sso_get_url_path',
			function ($default, $action) {
				// Always return a custom base; SSO appends the action suffix.
				return 'custom';
			},
			10,
			2
		);

		$sso = SSO::get_instance();

		$this->assertSame('custom', $sso->get_url_path());
		$this->assertSame('custom-sso', $sso->get_url_path('sso'));

		$url  = 'https://example.com/app';
		$with = SSO::with_sso($url);
		$this->assertStringContainsString('custom=login', $with);
	}

	public function test_calculate_secret_from_date_valid_and_invalid(): void {
		$sso    = SSO::get_instance();
		$secret = $sso->calculate_secret_from_date('2024-01-01 00:00:00');
		$this->assertIsString($secret);
		$this->assertNotEmpty($secret);

		$this->expectException(\WP_Ultimo\SSO\Exception\SSO_Exception::class);
		$sso->calculate_secret_from_date('not-a-date');
	}

	public function test_get_broker_by_id_roundtrip_domains_and_secret(): void {
		$blog_id = 1;
		if (! get_site($blog_id)) {
			$this->markTestSkipped('Main site not available in this environment.');
		}
		switch_to_blog($blog_id);
		try {
			$sso   = SSO::get_instance();
			$salt  = $sso->salt();
			$coded = $sso->encode($blog_id, $salt);

			$info = $sso->get_broker_by_id($coded);
		} finally {
			restore_current_blog();
		}
		$this->assertIsArray($info);
		$this->assertArrayHasKey('secret', $info);
		$this->assertArrayHasKey('domains', $info);
		$this->assertNotEmpty($info['domains']);

		// Invalid should return null
		$this->assertNull($sso->get_broker_by_id('invalid'));
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_final_return_url_builds_login_url_with_done_and_redirect(): void {
		// Mock network_home_url to avoid database calls
		add_filter(
			'network_home_url',
			function ($url, $path) {
				return 'https://example.com' . $path;
			},
			10,
			2
		);

		$sso  = SSO::get_instance();
		$base = network_home_url('/some/path');
		$url  = add_query_arg(
			[
				$sso->get_url_path() => 'login',
				'redirect_to'        => rawurlencode('https://example.com/after'),
			],
			$base
		);

		$final = $sso->get_final_return_url($url);
		$this->assertStringContainsString($sso->get_url_path() . '=done', $final);
		$this->assertStringContainsString('redirect_to=', $final);
		$this->assertStringContainsString('wp-login.php', $final);
	}

	public function test_broker_attach_url_contains_token_broker_checksum_and_params(): void {
		// Work on a dedicated site to avoid pollution from other tests.
		$blog_id = 1;
		switch_to_blog($blog_id);
		try {
			$sso = SSO::get_instance();
			// Build a broker like SSO::get_broker but with absolute URL and in-memory state.
			$blog      = get_blog_details($blog_id);
			$date      = $blog ? $blog->registered : '2024-01-01 00:00:00';
			$secret    = $sso->calculate_secret_from_date($date);
			$url       = trailingslashit(network_home_url()) . $sso->get_url_path('grant');
			$broker_id = $sso->encode($blog_id, $sso->salt());
			$broker    = new SSO_Broker($url, $broker_id, $secret);
		} finally {
			restore_current_blog();
		}
		// Avoid headers by storing token/verify in memory rather than cookies.
		$broker = $broker->withTokenIn(new \ArrayObject());

		$attach = $broker->getAttachUrl(['return_url' => 'https://example.com/here']);
		$parts  = wp_parse_url($attach);
		parse_str($parts['query'] ?? '', $q);

		$this->assertArrayHasKey('broker', $q);
		$this->assertArrayHasKey('token', $q);
		$this->assertArrayHasKey('checksum', $q);
		$this->assertSame('https://example.com/here', $q['return_url']);

		// JSONP variant
		$attach2 = $broker->getAttachUrl(['_jsonp' => '1']);
		$parts2  = wp_parse_url($attach2);
		parse_str($parts2['query'] ?? '', $q2);
		$this->assertSame('1', $q2['_jsonp']);
	}

	public function test_session_handler_start_throws_when_not_logged_in(): void {
		$this->expectException(SSO_Session_Exception::class);
		$this->expectExceptionCode(401);

		$sso = SSO::get_instance();
		unset($_REQUEST['broker']);
		$handler = new \WP_Ultimo\SSO\SSO_Session_Handler($sso);
		$handler->start();
	}

	public function test_get_return_type_defaults_and_values(): void {
		$sso = SSO::get_instance();

		unset($_REQUEST['return_type']);
		$this->assertSame('redirect', $sso->get_return_type());

		$_REQUEST['return_type'] = 'jsonp';
		$this->assertSame('jsonp', $sso->get_return_type());

		$_REQUEST['return_type'] = 'json';
		$this->assertSame('json', $sso->get_return_type());

		$_REQUEST['return_type'] = 'invalid';
		$this->assertSame('redirect', $sso->get_return_type());
	}
}
