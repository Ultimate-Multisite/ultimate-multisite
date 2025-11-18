<?php

use WP_Ultimo\SSO\SSO;
use WP_Ultimo\SSO\SSO_Session_Handler;

/**
 * SSO unit tests covering helpers and session handler.
 */
class SSO_Test extends \WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Flush caches to ensure clean state
		wp_cache_flush();
		// Ensure SSO singleton is initialized fresh per test when needed.
		// SSO hooks only run if enabled; our tests use direct methods.
	}

	public function tearDown(): void {
		// Remove any filters set in tests.
		remove_all_filters('wu_sso_enabled');
		remove_all_filters('wu_sso_get_url_path');
		remove_all_filters('determine_current_user');
		parent::tearDown();
	}

	public function test_with_sso_appends_query_param_when_enabled(): void {
		add_filter('wu_sso_enabled', '__return_true');
		$url     = 'https://example.com/path?foo=bar';
		$withSso = SSO::with_sso($url);

		$this->assertStringContainsString('sso=login', $withSso, 'SSO query arg should be added');
		$this->assertStringContainsString('foo=bar', $withSso, 'Original query args should be preserved');
	}

	public function test_with_sso_returns_same_url_when_disabled(): void {
		add_filter('wu_sso_enabled', '__return_false');
		$url     = 'https://example.com/path?foo=bar';
		$withSso = SSO::with_sso($url);

		$this->assertSame($url, $withSso, 'URL should be unchanged when SSO disabled');
	}

	public function test_encode_decode_roundtrip_uses_hashids(): void {
		$sso   = SSO::get_instance();
		$salt  = $sso->salt();
		$value = 12345;

		$encoded = $sso->encode($value, $salt);
		$this->assertIsString($encoded);
		$this->assertNotSame((string) $value, $encoded, 'Encoded value should be obfuscated');

		$decoded = $sso->decode($encoded, $salt);
		$this->assertSame($value, $decoded, 'Decoded value should match original');
	}

	public function test_session_handler_start_and_resume_sets_target_user_id(): void {
		// Use a fixed user ID to avoid database issues in test environment
		$user_id = 1;

		// Ensure we have a site id to encode as broker id.
		$site_id = get_current_blog_id();
		$sso     = SSO::get_instance();
		$salt    = $sso->salt();
		$broker  = $sso->encode($site_id, $salt);

		// Simulate request param that SSO_Session_Handler reads.
		$_REQUEST['broker'] = $broker;

		$handler = new SSO_Session_Handler($sso);

		// Mock the transient get to avoid database calls
		add_filter(
			'pre_site_transient_sso-' . $broker . '-' . $site_id,
			function () use ($user_id) {
				return $user_id;
			}
		);

		// resume() should read the transient and set target user id inside SSO.
		$handler->resume($broker);

		$this->assertSame($user_id, $sso->get_target_user_id(), 'Target user id should be set from session');
	}
}
