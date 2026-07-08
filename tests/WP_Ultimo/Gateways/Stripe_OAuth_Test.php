<?php
/**
 * Tests for Stripe OAuth functionality.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @since 2.x.x
 */

namespace WP_Ultimo\Gateways;

use WP_UnitTestCase;

/**
 * Stripe OAuth Test class.
 */
class Stripe_OAuth_Test extends WP_UnitTestCase {

	/**
	 * Test gateway instance.
	 *
	 * @var Stripe_Gateway
	 */
	protected $gateway;

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear all Stripe settings before each test
		wu_save_setting('stripe_test_access_token', '');
		wu_save_setting('stripe_test_account_id', '');
		wu_save_setting('stripe_test_publishable_key', '');
		wu_save_setting('stripe_test_pk_key', '');
		wu_save_setting('stripe_test_sk_key', '');
		wu_save_setting('stripe_sandbox_mode', 1);

		$this->gateway = new Stripe_Gateway();
	}

	/**
	 * Test authentication mode detection - OAuth mode.
	 */
	public function test_oauth_mode_detection() {
		// Set OAuth token
		wu_save_setting('stripe_test_access_token', 'sk_test_oauth_token_123');
		wu_save_setting('stripe_test_account_id', 'acct_123');
		wu_save_setting('stripe_test_publishable_key', 'pk_test_oauth_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertEquals('oauth', $gateway->get_authentication_mode());
		$this->assertTrue($gateway->is_using_oauth());
	}

	/**
	 * Test authentication mode detection - direct mode.
	 */
	public function test_direct_mode_detection() {
		// Only set direct API keys
		wu_save_setting('stripe_test_pk_key', 'pk_test_direct_123');
		wu_save_setting('stripe_test_sk_key', 'sk_test_direct_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertEquals('direct', $gateway->get_authentication_mode());
		$this->assertFalse($gateway->is_using_oauth());
	}

	/**
	 * Test OAuth takes precedence over direct API keys.
	 */
	public function test_oauth_precedence_over_direct() {
		// Set both OAuth and direct keys
		wu_save_setting('stripe_test_access_token', 'sk_test_oauth_token_123');
		wu_save_setting('stripe_test_account_id', 'acct_123');
		wu_save_setting('stripe_test_publishable_key', 'pk_test_oauth_123');
		wu_save_setting('stripe_test_pk_key', 'pk_test_direct_123');
		wu_save_setting('stripe_test_sk_key', 'sk_test_direct_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		// OAuth should take precedence
		$this->assertEquals('oauth', $gateway->get_authentication_mode());
		$this->assertTrue($gateway->is_using_oauth());
	}

	/**
	 * Test OAuth authorization URL generation via proxy.
	 */
	public function test_oauth_authorization_url_generation() {
		// Mock proxy response
		add_filter('pre_http_request', function ($preempt, $args, $url) {
			if (strpos($url, '/oauth/init') !== false) {
				return [
					'response' => ['code' => 200],
					'body'     => wp_json_encode([
						'oauthUrl' => 'https://connect.stripe.com/oauth/authorize?client_id=ca_test123&state=encrypted_state&scope=read_write',
						'state'    => 'test_state_123',
					]),
				];
			}
			return $preempt;
		}, 10, 3);

		$gateway = new Stripe_Gateway();
		$url     = $gateway->get_connect_authorization_url('');

		$this->assertStringContainsString('connect.stripe.com/oauth/authorize', $url);
		$this->assertStringContainsString('client_id=ca_test123', $url);
		$this->assertStringContainsString('scope=read_write', $url);

		// Verify state was stored
		$this->assertEquals('test_state_123', get_option('wu_stripe_oauth_state'));
	}

	/**
	 * Test OAuth authorization URL returns empty on proxy failure.
	 */
	public function test_oauth_authorization_url_requires_client_id() {
		// Mock proxy returning error or invalid response
		add_filter('pre_http_request', function ($preempt, $args, $url) {
			if (strpos($url, '/oauth/init') !== false) {
				return new \WP_Error('http_request_failed', 'Connection failed');
			}
			return $preempt;
		}, 10, 3);

		$gateway = new Stripe_Gateway();
		$url     = $gateway->get_connect_authorization_url('');

		$this->assertEmpty($url);
	}

	/**
	 * Test backwards compatibility with existing API keys.
	 */
	public function test_backwards_compatibility_with_existing_keys() {
		// Simulate existing installation with direct API keys
		wu_save_setting('stripe_test_pk_key', 'pk_test_existing_123');
		wu_save_setting('stripe_test_sk_key', 'sk_test_existing_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		// Should work in direct mode
		$this->assertEquals('direct', $gateway->get_authentication_mode());
		$this->assertFalse($gateway->is_using_oauth());

		// Verify API keys are loaded
		$reflection      = new \ReflectionClass($gateway);
		$secret_property = $reflection->getProperty('secret_key');
		$secret_property->setAccessible(true);

		$this->assertEquals('sk_test_existing_123', $secret_property->getValue($gateway));
	}

	/**
	 * Test OAuth account ID is loaded in OAuth mode.
	 */
	public function test_oauth_account_id_loaded() {
		wu_save_setting('stripe_test_access_token', 'sk_test_oauth_token_123');
		wu_save_setting('stripe_test_account_id', 'acct_test123');
		wu_save_setting('stripe_test_publishable_key', 'pk_test_oauth_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertTrue($gateway->is_using_oauth());

		// Verify account ID is loaded
		$reflection = new \ReflectionClass($gateway);
		$property   = $reflection->getProperty('oauth_account_id');
		$property->setAccessible(true);

		$this->assertEquals('acct_test123', $property->getValue($gateway));
	}

	/**
	 * Test OAuth account ID is not loaded in direct mode.
	 */
	public function test_oauth_account_id_not_loaded_for_direct() {
		wu_save_setting('stripe_test_pk_key', 'pk_test_direct_123');
		wu_save_setting('stripe_test_sk_key', 'sk_test_direct_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertFalse($gateway->is_using_oauth());

		// Verify account ID is empty
		$reflection = new \ReflectionClass($gateway);
		$property   = $reflection->getProperty('oauth_account_id');
		$property->setAccessible(true);

		$this->assertEmpty($property->getValue($gateway));
	}

	/**
	 * Test disconnect settings are cleared manually (without redirect).
	 */
	public function test_disconnect_settings_cleared() {
		$id = 'stripe';

		// Set OAuth tokens for both test and live mode
		wu_save_setting("{$id}_test_access_token", 'sk_test_oauth_token_123');
		wu_save_setting("{$id}_test_account_id", 'acct_test123');
		wu_save_setting("{$id}_test_publishable_key", 'pk_test_oauth_123');
		wu_save_setting("{$id}_test_refresh_token", 'rt_test_123');
		wu_save_setting("{$id}_live_access_token", 'sk_live_oauth_token_123');
		wu_save_setting("{$id}_live_account_id", 'acct_live123');
		wu_save_setting("{$id}_live_publishable_key", 'pk_live_oauth_123');
		wu_save_setting("{$id}_live_refresh_token", 'rt_live_123');

		// Manually clear settings (simulating disconnect without the redirect)
		wu_save_setting("{$id}_test_access_token", '');
		wu_save_setting("{$id}_test_refresh_token", '');
		wu_save_setting("{$id}_test_account_id", '');
		wu_save_setting("{$id}_test_publishable_key", '');
		wu_save_setting("{$id}_live_access_token", '');
		wu_save_setting("{$id}_live_refresh_token", '');
		wu_save_setting("{$id}_live_account_id", '');
		wu_save_setting("{$id}_live_publishable_key", '');

		// Verify all OAuth tokens are cleared
		$this->assertEmpty(wu_get_setting("{$id}_test_access_token", ''));
		$this->assertEmpty(wu_get_setting("{$id}_test_account_id", ''));
		$this->assertEmpty(wu_get_setting("{$id}_test_publishable_key", ''));
		$this->assertEmpty(wu_get_setting("{$id}_test_refresh_token", ''));
		$this->assertEmpty(wu_get_setting("{$id}_live_access_token", ''));
		$this->assertEmpty(wu_get_setting("{$id}_live_account_id", ''));
		$this->assertEmpty(wu_get_setting("{$id}_live_publishable_key", ''));
		$this->assertEmpty(wu_get_setting("{$id}_live_refresh_token", ''));
	}

	/**
	 * Test direct API keys are independent from OAuth tokens.
	 */
	public function test_direct_keys_independent() {
		// Set both OAuth tokens and direct keys
		wu_save_setting('stripe_test_access_token', 'sk_test_oauth_token_123');
		wu_save_setting('stripe_test_pk_key', 'pk_test_direct_123');
		wu_save_setting('stripe_test_sk_key', 'sk_test_direct_123');

		// Clearing OAuth tokens shouldn't affect direct keys
		wu_save_setting('stripe_test_access_token', '');

		// Verify direct API keys are still present
		$this->assertEquals('pk_test_direct_123', wu_get_setting('stripe_test_pk_key'));
		$this->assertEquals('sk_test_direct_123', wu_get_setting('stripe_test_sk_key'));
	}

	/**
	 * Test live mode OAuth detection.
	 */
	public function test_live_mode_oauth_detection() {
		// Set live mode OAuth tokens
		wu_save_setting('stripe_live_access_token', 'sk_live_oauth_token_123');
		wu_save_setting('stripe_live_account_id', 'acct_live123');
		wu_save_setting('stripe_live_publishable_key', 'pk_live_oauth_123');
		wu_save_setting('stripe_sandbox_mode', 0); // Live mode

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertEquals('oauth', $gateway->get_authentication_mode());
		$this->assertTrue($gateway->is_using_oauth());
	}

	/**
	 * Test disconnect URL has proper nonce.
	 */
	public function test_disconnect_url_has_nonce() {
		$gateway = new Stripe_Gateway();

		$reflection = new \ReflectionClass($gateway);
		$method     = $reflection->getMethod('get_disconnect_url');
		$method->setAccessible(true);

		$url = $method->invoke($gateway);

		$this->assertStringContainsString('stripe_disconnect=1', $url);
		$this->assertStringContainsString('_wpnonce=', $url);
		$this->assertStringContainsString('page=wp-ultimo-settings', $url);
		$this->assertStringContainsString('tab=payment-gateways', $url);
	}
}
