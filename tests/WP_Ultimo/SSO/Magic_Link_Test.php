<?php

namespace WP_Ultimo\SSO;

/**
 * Tests for the Magic_Link class.
 */
class Magic_Link_Test extends \WP_UnitTestCase {

	private $original_home;
	private $original_registration_page;

	/**
	 * Get a fresh Magic_Link instance.
	 *
	 * @return Magic_Link
	 */
	private function get_instance() {

		return Magic_Link::get_instance();
	}

	/**
	 * Invokes a protected checkout handoff method.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Method arguments.
	 * @return mixed
	 */
	private function invoke_checkout_handoff_method($method, ...$args) {

		$reflection = new \ReflectionMethod($this->get_instance(), $method);

		if (PHP_VERSION_ID < 80100) {
			$reflection->setAccessible(true);
		}

		return $reflection->invoke($this->get_instance(), ...$args);
	}

	/**
	 * Ensures the cache-independent nonce table exists before handoff tests.
	 *
	 * @return void
	 */
	private function ensure_checkout_handoff_table(): void {

		$table = \WP_Ultimo\Loaders\Table_Loader::get_instance()->sso_nonce_table;

		if ( ! $table->exists()) {
			$table->install();
		}
	}

	/**
	 * Creates signed claims valid for the current blog and user.
	 *
	 * @param int $user_id User who can access the current site.
	 * @param array $overrides Claim overrides.
	 * @return array
	 */
	private function get_checkout_handoff_claims($user_id, array $overrides = []) {

		$instance = $this->get_instance();

		return array_merge(
			[
				'v'          => 1,
				'sub'        => $user_id,
				'blog_id'    => get_current_blog_id(),
				'iat'        => time(),
				'exp'        => time() + Magic_Link::CHECKOUT_HANDOFF_EXPIRATION,
				'jti'        => $this->invoke_checkout_handoff_method('generate_token'),
				'path'       => '/wp-admin/',
				'user_agent' => $this->invoke_checkout_handoff_method('get_checkout_handoff_context_hash', $this->invoke_checkout_handoff_method('get_user_agent')),
				'ip_address' => $this->invoke_checkout_handoff_method('get_checkout_handoff_context_hash', $this->invoke_checkout_handoff_method('get_client_ip')),
			],
			$overrides
		);
	}

	/**
	 * Create a payable payment owned by a new customer.
	 *
	 * @return array{customer: \WP_Ultimo\Models\Customer, payment: \WP_Ultimo\Models\Payment, user_id: int}
	 */
	private function create_payable_payment() {

		$page_id = self::factory()->post->create([
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => 'register',
			'post_content' => '[wu_checkout]',
		]);
		wu_save_setting('default_registration_page', $page_id);
		update_option('home', set_url_scheme($this->original_home, 'https'));

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer(['user_id' => $user_id]);

		$this->assertNotWPError($customer);

		$payment = wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => 0,
				'currency'      => 'USD',
				'subtotal'      => 25,
				'total'         => 25,
				'status'        => 'pending',
				'gateway'       => 'manual',
			]
		);

		$this->assertNotWPError($payment);

		return compact('customer', 'payment', 'user_id');
	}

	public function set_up() {

		parent::set_up();

		$this->original_home              = get_option('home');
		$this->original_registration_page = wu_get_setting('default_registration_page', 0);

		// Enable magic links by default for tests
		add_filter('wu_magic_links_enabled', '__return_true');
	}

	public function tear_down() {

		remove_filter('wu_magic_links_enabled', '__return_true');
		update_option('home', $this->original_home);
		wu_save_setting('default_registration_page', $this->original_registration_page);
		wp_set_current_user(0);

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
		$this->assertSame('wu_checkout_handoff', Magic_Link::CHECKOUT_HANDOFF_QUERY_ARG);
		$this->assertSame(120, Magic_Link::CHECKOUT_HANDOFF_EXPIRATION);
	}

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks() {

		$instance = $this->get_instance();

		$instance->init();

		$this->assertNotFalse(has_action('init', [$instance, 'handle_magic_link']));
		$this->assertNotFalse(has_action('init', [$instance, 'handle_checkout_handoff']));
		$this->assertNotFalse(has_action('wu_daily', [$instance, 'clean_expired_checkout_handoff_nonces']));
		$this->assertNotFalse(has_filter('removable_query_args', [$instance, 'add_removable_query_args']));
	}

	/**
	 * Test add_removable_query_args adds the token arg.
	 */
	public function test_add_removable_query_args() {

		$instance = $this->get_instance();

		$args = $instance->add_removable_query_args([]);

		$this->assertContains(Magic_Link::TOKEN_QUERY_ARG, $args);
		$this->assertContains(Magic_Link::CHECKOUT_HANDOFF_QUERY_ARG, $args);
	}

	/**
	 * Test checkout handoffs are signed, database-backed and single-use.
	 */
	public function test_checkout_handoff_token_verifies_and_consumes_once() {

		$this->ensure_checkout_handoff_table();

		$user_id = self::factory()->user->create();
		add_user_to_blog(get_current_blog_id(), $user_id, 'subscriber');

		$_SERVER['HTTP_USER_AGENT'] = 'Ultimate-Multisite-Handoff-Test';
		$_SERVER['REMOTE_ADDR']     = '192.0.2.10';

		try {
			$claims = $this->get_checkout_handoff_claims($user_id, ['path' => '/welcome/']);
			$token  = $this->invoke_checkout_handoff_method('encode_checkout_handoff_token', $claims);

			$this->assertIsString($token);
			$this->assertSame($claims['jti'], $this->invoke_checkout_handoff_method('verify_checkout_handoff_token', $token)['jti']);
			$this->assertTrue($this->invoke_checkout_handoff_method('store_checkout_handoff_nonce', $claims['jti'], get_current_blog_id(), $claims['exp']));
			$this->assertTrue($this->invoke_checkout_handoff_method('consume_checkout_handoff_nonce', $claims['jti'], get_current_blog_id()));
			$this->assertFalse($this->invoke_checkout_handoff_method('consume_checkout_handoff_nonce', $claims['jti'], get_current_blog_id()));
		} finally {
			unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
		}
	}

	/**
	 * Test checkout handoff URLs respect the magic-link setting.
	 */
	public function test_generate_checkout_handoff_url_returns_false_when_magic_links_are_disabled() {

		$user_id = self::factory()->user->create();
		add_user_to_blog(get_current_blog_id(), $user_id, 'subscriber');

		remove_filter('wu_magic_links_enabled', '__return_true');
		add_filter('wu_magic_links_enabled', '__return_false');

		try {
			$this->assertFalse($this->get_instance()->generate_checkout_handoff_url($user_id, get_current_blog_id()));
		} finally {
			remove_filter('wu_magic_links_enabled', '__return_false');
			add_filter('wu_magic_links_enabled', '__return_true');
		}
	}

	/**
	 * Test checkout handoffs reject expired and retargeted signed tokens.
	 */
	public function test_checkout_handoff_token_rejects_expired_or_retargeted_claims() {

		$user_id = self::factory()->user->create();
		add_user_to_blog(get_current_blog_id(), $user_id, 'subscriber');

		$expired_claims = $this->get_checkout_handoff_claims(
			$user_id,
			[
				'iat' => time() - 121,
				'exp' => time() - 1,
			]
		);
		$expired_token  = $this->invoke_checkout_handoff_method('encode_checkout_handoff_token', $expired_claims);

		$this->assertFalse($this->invoke_checkout_handoff_method('verify_checkout_handoff_token', $expired_token));

		$retargeted_claims = $this->get_checkout_handoff_claims(
			$user_id,
			[
				'blog_id' => get_current_blog_id() + 999,
			]
		);
		$retargeted_token  = $this->invoke_checkout_handoff_method('encode_checkout_handoff_token', $retargeted_claims);

		$this->assertFalse($this->invoke_checkout_handoff_method('verify_checkout_handoff_token', $retargeted_token));
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
	 * Test verify_user_site_access allows super administrators on every site.
	 */
	public function test_verify_user_site_access_super_admin() {

		$instance = $this->get_instance();
		$user_id  = self::factory()->user->create();
		$site_id  = self::factory()->blog->create();
		$ref      = new \ReflectionMethod($instance, 'verify_user_site_access');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		grant_super_admin($user_id);

		try {
			$this->assertTrue($ref->invoke($instance, $user_id, $site_id));
		} finally {
			revoke_super_admin($user_id);
		}
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
	 * Test payment magic links bind a customer to a single payment checkout URL.
	 */
	public function test_generate_payment_magic_link_for_owner() {

		$objects = $this->create_payable_payment();
		$payment = $objects['payment'];

		wp_set_current_user($objects['user_id']);

		$site_id = self::factory()->blog->create();
		switch_to_blog($site_id);
		update_option('home', 'https://customer.example.test');

		try {
			$redirect_to = add_query_arg(
				[
					'payment'       => $payment->get_hash(),
					'checkout_form' => 'wu-pay-invoice',
				],
				wu_get_registration_url()
			);
			$magic_link  = $payment->get_payment_url();
		} finally {
			restore_current_blog();
		}

		$this->assertIsString($magic_link);
		$this->assertStringContainsString($payment->get_hash(), $magic_link);
		$this->assertSame($redirect_to, remove_query_arg(Magic_Link::TOKEN_QUERY_ARG, $magic_link));

		parse_str((string) wp_parse_url($magic_link, PHP_URL_QUERY), $query_args);
		$this->assertArrayHasKey(Magic_Link::TOKEN_QUERY_ARG, $query_args);

		$token_data = wu_switch_blog_and_run(
			fn() => get_transient(Magic_Link::TRANSIENT_PREFIX . $query_args[ Magic_Link::TOKEN_QUERY_ARG ])
		);

		$this->assertSame('payment', $token_data['purpose']);
		$this->assertSame($objects['user_id'], $token_data['user_id']);
		$this->assertSame($payment->get_id(), $token_data['payment_id']);
		$this->assertSame($redirect_to, $token_data['redirect_to']);

		$consume = new \ReflectionMethod($this->get_instance(), 'verify_and_consume_token');

		if (PHP_VERSION_ID < 80100) {
			$consume->setAccessible(true);
		}

		$this->assertIsArray($consume->invoke($this->get_instance(), $query_args[ Magic_Link::TOKEN_QUERY_ARG ]));
		$this->assertFalse($consume->invoke($this->get_instance(), $query_args[ Magic_Link::TOKEN_QUERY_ARG ]));
	}

	/**
	 * Test payment magic links reject users who do not own the payment.
	 */
	public function test_generate_payment_magic_link_rejects_non_owner() {

		$objects = $this->create_payable_payment();
		$payment = $objects['payment'];

		wp_set_current_user(self::factory()->user->create());

		$this->assertFalse(
			$this->get_instance()->generate_payment_magic_link($payment, $payment->get_payment_url())
		);
	}

	/**
	 * Test payment access rejects a modified checkout URL.
	 */
	public function test_verify_payment_access_rejects_modified_redirect() {

		$objects     = $this->create_payable_payment();
		$payment     = $objects['payment'];
		$redirect_to = add_query_arg('payment', $payment->get_hash(), wu_get_registration_url());
		$redirect_to = add_query_arg('redirect_to', 'https://attacker.example.test', $redirect_to);
		$ref         = new \ReflectionMethod($this->get_instance(), 'verify_payment_access');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertFalse($ref->invoke($this->get_instance(), $payment, $objects['user_id'], $redirect_to));
	}

	/**
	 * Test payment magic links reject payments that can no longer be paid.
	 */
	public function test_generate_payment_magic_link_rejects_non_payable_payment() {

		$objects = $this->create_payable_payment();
		$payment = $objects['payment'];
		$payment->set_status('completed');

		wp_set_current_user($objects['user_id']);

		$this->assertFalse(
			$this->get_instance()->generate_payment_magic_link($payment, add_query_arg('payment', $payment->get_hash(), wu_get_registration_url()))
		);
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
	 * Test site_needs_magic_link detects a custom domain stored directly on the site.
	 */
	public function test_site_needs_magic_link_direct_custom_domain() {

		$site = wu_create_site(
			[
				'domain' => 'direct-domain.example.test',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);
		$this->assertTrue($this->get_instance()->site_needs_magic_link($site->get_id()));
	}

	/**
	 * Test site_needs_magic_link excludes subdomains of the main site.
	 */
	public function test_site_needs_magic_link_main_site_subdomain() {

		$main_site_domain = wp_parse_url(get_site_url(wu_get_main_site_id()), PHP_URL_HOST);
		$site             = wu_create_site(
			[
				'domain' => 'customer.' . $main_site_domain,
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);
		$this->assertFalse($this->get_instance()->site_needs_magic_link($site->get_id()));
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
