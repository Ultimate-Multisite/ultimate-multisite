<?php

namespace WP_Ultimo\SSO;

/**
 * Tests for the Magic_Link class.
 */
class Magic_Link_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh Magic_Link instance.
	 *
	 * @return Magic_Link
	 */
	private function get_instance() {

		return Magic_Link::get_instance();
	}

	public function set_up() {

		parent::set_up();

		// Enable magic links by default for tests
		add_filter('wu_magic_links_enabled', '__return_true');
	}

	public function tear_down() {

		remove_filter('wu_magic_links_enabled', '__return_true');

		parent::tear_down();
	}

	/**
	 * Test singleton instance.
	 */
	public function test_get_instance() {

		$instance = $this->get_instance();

		$this->assertInstanceOf(Magic_Link::class, $instance);
		$this->assertSame($instance, Magic_Link::get_instance());
	}

	/**
	 * Test constants are defined.
	 */
	public function test_constants() {

		$this->assertSame('wu_magic_token', Magic_Link::TOKEN_QUERY_ARG);
		$this->assertSame('wu_magic_link_', Magic_Link::TRANSIENT_PREFIX);
		$this->assertSame(600, Magic_Link::TOKEN_EXPIRATION);
	}

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks() {

		$instance = $this->get_instance();

		$instance->init();

		$this->assertNotFalse(has_action('init', [$instance, 'handle_magic_link']));
		$this->assertNotFalse(has_filter('removable_query_args', [$instance, 'add_removable_query_args']));
	}

	/**
	 * Test add_removable_query_args adds the token arg.
	 */
	public function test_add_removable_query_args() {

		$instance = $this->get_instance();

		$args = $instance->add_removable_query_args([]);

		$this->assertContains(Magic_Link::TOKEN_QUERY_ARG, $args);
	}

	/**
	 * Test add_removable_query_args preserves existing args.
	 */
	public function test_add_removable_query_args_preserves_existing() {

		$instance = $this->get_instance();

		$args = $instance->add_removable_query_args(['existing_arg']);

		$this->assertContains('existing_arg', $args);
		$this->assertContains(Magic_Link::TOKEN_QUERY_ARG, $args);
	}

	/**
	 * Test generate_token returns a 64-char hex string.
	 */
	public function test_generate_token() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'generate_token');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$token = $ref->invoke($instance);

		$this->assertIsString($token);
		$this->assertSame(64, strlen($token));
		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
	}

	/**
	 * Test generate_token returns unique values.
	 */
	public function test_generate_token_uniqueness() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'generate_token');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$token1 = $ref->invoke($instance);
		$token2 = $ref->invoke($instance);

		$this->assertNotSame($token1, $token2);
	}

	/**
	 * Test verify_user_site_access with valid user.
	 */
	public function test_verify_user_site_access_valid() {

		$instance = $this->get_instance();

		$user_id = self::factory()->user->create();
		$site_id = get_current_blog_id();

		// Add user to the current site
		add_user_to_blog($site_id, $user_id, 'subscriber');

		$ref = new \ReflectionMethod($instance, 'verify_user_site_access');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertTrue($ref->invoke($instance, $user_id, $site_id));
	}

	/**
	 * Test verify_user_site_access with invalid user.
	 */
	public function test_verify_user_site_access_invalid_user() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'verify_user_site_access');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertFalse($ref->invoke($instance, 999999, get_current_blog_id()));
	}

	/**
	 * Test generate_magic_link returns false when disabled.
	 */
	public function test_generate_magic_link_disabled() {

		$instance = $this->get_instance();

		remove_filter('wu_magic_links_enabled', '__return_true');
		add_filter('wu_magic_links_enabled', '__return_false');

		$result = $instance->generate_magic_link(1, 1);

		$this->assertFalse($result);

		remove_filter('wu_magic_links_enabled', '__return_false');
		add_filter('wu_magic_links_enabled', '__return_true');
	}

	/**
	 * Test generate_magic_link returns false for invalid user.
	 */
	public function test_generate_magic_link_invalid_user() {

		$instance = $this->get_instance();

		$result = $instance->generate_magic_link(999999, get_current_blog_id());

		$this->assertFalse($result);
	}

	/**
	 * Test handle_magic_link bails with no token.
	 */
	public function test_handle_magic_link_no_token() {

		$instance = $this->get_instance();

		// No token in request, should just return
		$instance->handle_magic_link();

		$this->assertTrue(true); // No exception thrown
	}

	/**
	 * Test handle_invalid_token fires action.
	 */
	public function test_handle_invalid_token_fires_action() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'handle_invalid_token');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$fired = false;

		add_action('wu_magic_link_invalid_token', function () use (&$fired) {
			$fired = true;
		}, 10, 0);

		$ref->invoke($instance, 'test reason');

		$this->assertTrue($fired);
	}

	/**
	 * Test verify_and_consume_token returns false for non-existent token.
	 */
	public function test_verify_and_consume_token_nonexistent() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'verify_and_consume_token');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$result = $ref->invoke($instance, 'nonexistent_token_abc123');

		$this->assertFalse($result);
	}

	/**
	 * Test get_client_ip with REMOTE_ADDR.
	 */
	public function test_get_client_ip_remote_addr() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'get_client_ip');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

		$ip = $ref->invoke($instance);

		$this->assertSame('192.168.1.1', $ip);

		unset($_SERVER['REMOTE_ADDR']);
	}

	/**
	 * Test get_client_ip with Cloudflare header.
	 */
	public function test_get_client_ip_cloudflare() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'get_client_ip');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.0.0.1';

		$ip = $ref->invoke($instance);

		$this->assertSame('10.0.0.1', $ip);

		unset($_SERVER['HTTP_CF_CONNECTING_IP']);
	}

	/**
	 * Test get_client_ip with X-Forwarded-For multiple IPs.
	 */
	public function test_get_client_ip_forwarded_for_multiple() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'get_client_ip');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Clear other headers
		unset($_SERVER['HTTP_CF_CONNECTING_IP']);
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 70.41.3.18, 150.172.238.178';

		$ip = $ref->invoke($instance);

		$this->assertSame('203.0.113.50', $ip);

		unset($_SERVER['HTTP_X_FORWARDED_FOR']);
	}

	/**
	 * Test get_user_agent.
	 */
	public function test_get_user_agent() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'get_user_agent');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';

		$ua = $ref->invoke($instance);

		$this->assertSame('TestBrowser/1.0', $ua);

		unset($_SERVER['HTTP_USER_AGENT']);
	}

	/**
	 * Test get_user_agent returns empty when not set.
	 */
	public function test_get_user_agent_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'get_user_agent');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		unset($_SERVER['HTTP_USER_AGENT']);

		$ua = $ref->invoke($instance);

		$this->assertSame('', $ua);
	}

	/**
	 * Test verify_security_context with matching context.
	 */
	public function test_verify_security_context_matching() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'verify_security_context');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';
		$_SERVER['REMOTE_ADDR']     = '192.168.1.1';

		$token_data = [
			'user_agent' => 'TestBrowser/1.0',
			'ip_address' => '192.168.1.1',
		];

		$result = $ref->invoke($instance, $token_data);

		$this->assertTrue($result);

		unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
	}

	/**
	 * Test verify_security_context with mismatched user agent.
	 */
	public function test_verify_security_context_ua_mismatch() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'verify_security_context');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Enforce user agent check
		add_filter('wu_magic_link_enforce_user_agent', '__return_true');

		$_SERVER['HTTP_USER_AGENT'] = 'DifferentBrowser/2.0';

		$token_data = [
			'user_agent' => 'TestBrowser/1.0',
			'ip_address' => '192.168.1.1',
		];

		$result = $ref->invoke($instance, $token_data);

		$this->assertFalse($result);

		remove_filter('wu_magic_link_enforce_user_agent', '__return_true');
		unset($_SERVER['HTTP_USER_AGENT']);
	}

	/**
	 * Test verify_security_context with IP enforcement.
	 */
	public function test_verify_security_context_ip_mismatch() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'verify_security_context');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Disable UA check, enable IP check
		add_filter('wu_magic_link_enforce_user_agent', '__return_false');
		add_filter('wu_magic_link_enforce_ip', '__return_true');

		$_SERVER['REMOTE_ADDR'] = '10.0.0.99';

		$token_data = [
			'user_agent' => 'anything',
			'ip_address' => '192.168.1.1',
		];

		$result = $ref->invoke($instance, $token_data);

		$this->assertFalse($result);

		remove_filter('wu_magic_link_enforce_user_agent', '__return_false');
		remove_filter('wu_magic_link_enforce_ip', '__return_true');
		unset($_SERVER['REMOTE_ADDR']);
	}

	/**
	 * Test site_needs_magic_link returns false for non-existent site.
	 */
	public function test_site_needs_magic_link_nonexistent() {

		$instance = $this->get_instance();

		$result = $instance->site_needs_magic_link(999999);

		$this->assertFalse($result);
	}

	/**
	 * Test maybe_convert_to_magic_link returns original URL when disabled.
	 */
	public function test_maybe_convert_to_magic_link_disabled() {

		$instance = $this->get_instance();

		remove_filter('wu_magic_links_enabled', '__return_true');
		add_filter('wu_magic_links_enabled', '__return_false');

		$url    = 'https://example.com/test';
		$result = $instance->maybe_convert_to_magic_link($url);

		$this->assertSame($url, $result);

		remove_filter('wu_magic_links_enabled', '__return_false');
		add_filter('wu_magic_links_enabled', '__return_true');
	}

	/**
	 * Test maybe_convert_to_magic_link returns original URL when not logged in.
	 */
	public function test_maybe_convert_to_magic_link_not_logged_in() {

		$instance = $this->get_instance();

		wp_set_current_user(0);

		$url    = 'https://example.com/test';
		$result = $instance->maybe_convert_to_magic_link($url);

		$this->assertSame($url, $result);
	}

	/**
	 * Test is_enabled returns bool.
	 */
	public function test_is_enabled() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'is_enabled');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$result = $ref->invoke($instance);

		$this->assertIsBool($result);
	}

	/**
	 * Test extract_site_id_from_url with invalid URL.
	 */
	public function test_extract_site_id_from_url_invalid() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'extract_site_id_from_url');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$result = $ref->invoke($instance, 'not-a-url');

		$this->assertNull($result);
	}

	/**
	 * Test generate_cross_network_magic_link returns false when disabled.
	 */
	public function test_generate_cross_network_magic_link_disabled() {

		$instance = $this->get_instance();

		remove_filter('wu_magic_links_enabled', '__return_true');
		add_filter('wu_magic_links_enabled', '__return_false');

		$result = $instance->generate_cross_network_magic_link(1, 1, 'https://example.com');

		$this->assertFalse($result);

		remove_filter('wu_magic_links_enabled', '__return_false');
		add_filter('wu_magic_links_enabled', '__return_true');
	}

	/**
	 * Test generate_cross_network_magic_link returns false for invalid user.
	 */
	public function test_generate_cross_network_magic_link_invalid_user() {

		$instance = $this->get_instance();

		$result = $instance->generate_cross_network_magic_link(999999, 1, 'https://example.com');

		$this->assertFalse($result);
	}

	/**
	 * Test generate_cross_network_magic_link with valid user.
	 */
	public function test_generate_cross_network_magic_link_valid() {

		$instance = $this->get_instance();

		$user_id = self::factory()->user->create();
		$site_id = get_current_blog_id();

		$result = $instance->generate_cross_network_magic_link($user_id, $site_id, 'https://example.com');

		$this->assertIsString($result);
		$this->assertStringContainsString(Magic_Link::TOKEN_QUERY_ARG, $result);
		$this->assertStringContainsString('example.com', $result);
	}
}
