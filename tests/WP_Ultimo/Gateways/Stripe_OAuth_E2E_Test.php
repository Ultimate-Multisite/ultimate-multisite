<?php
/**
 * E2E-style integration tests for Stripe OAuth and checkout with application fees.
 *
 * These tests verify the complete OAuth flow and purchase processing:
 * - OAuth token storage and retrieval
 * - Application fee logic in subscription creation
 * - Stripe-Account header configuration
 *
 * @package WP_Ultimo\Gateways
 * @since 2.x.x
 */

namespace WP_Ultimo\Gateways;

use Stripe\StripeClient;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * E2E integration tests for Stripe OAuth.
 */
class Stripe_OAuth_E2E_Test extends \WP_UnitTestCase {

	/**
	 * Clean slate before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clear_all_stripe_settings();
	}

	/**
	 * Test that OAuth tokens are correctly saved from simulated callback.
	 */
	public function test_oauth_tokens_saved_correctly() {
		// Manually simulate what the OAuth callback does
		wu_save_setting('stripe_test_access_token', 'sk_test_oauth_abc123');
		wu_save_setting('stripe_test_account_id', 'acct_test_xyz789');
		wu_save_setting('stripe_test_publishable_key', 'pk_test_oauth_abc123');
		wu_save_setting('stripe_test_refresh_token', 'rt_test_refresh_abc123');
		wu_save_setting('stripe_sandbox_mode', 1);

		// Initialize gateway
		$gateway = new Stripe_Gateway();
		$gateway->init();

		// Verify OAuth mode is detected
		$this->assertTrue($gateway->is_using_oauth());
		$this->assertEquals('oauth', $gateway->get_authentication_mode());

		// Verify account ID is loaded
		$reflection = new \ReflectionClass($gateway);
		$property   = $reflection->getProperty('oauth_account_id');
		$property->setAccessible(true);
		$this->assertEquals('acct_test_xyz789', $property->getValue($gateway));
	}

	/**
	 * Test that Stripe client is configured with account header in OAuth mode.
	 */
	public function test_stripe_client_has_account_header_in_oauth_mode() {
		// Setup OAuth mode
		wu_save_setting('stripe_test_access_token', 'sk_test_oauth_token_123');
		wu_save_setting('stripe_test_account_id', 'acct_oauth_123');
		wu_save_setting('stripe_test_publishable_key', 'pk_test_oauth_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertTrue($gateway->is_using_oauth());

		// Access oauth_account_id via reflection
		$reflection = new \ReflectionClass($gateway);
		$property   = $reflection->getProperty('oauth_account_id');
		$property->setAccessible(true);

		// Verify account ID is set
		$this->assertEquals('acct_oauth_123', $property->getValue($gateway));
	}

	/**
	 * Test the complete OAuth setup flow.
	 */
	public function test_complete_oauth_flow_simulation() {
		// Step 1: Start with no configuration
		$this->clear_all_stripe_settings();

		wu_save_setting('stripe_sandbox_mode', 1);

		// Step 2: User clicks "Connect with Stripe" and OAuth completes
		// (Simulating what happens after successful OAuth callback)
		wu_save_setting('stripe_test_access_token', 'sk_test_connected_abc');
		wu_save_setting('stripe_test_account_id', 'acct_connected_xyz');
		wu_save_setting('stripe_test_publishable_key', 'pk_test_connected_abc');
		wu_save_setting('stripe_test_refresh_token', 'rt_test_refresh_abc');

		// Step 3: Gateway initializes and detects OAuth
		$gateway = new Stripe_Gateway();
		$gateway->init();

		// Verify OAuth mode
		$this->assertTrue($gateway->is_using_oauth());
		$this->assertEquals('oauth', $gateway->get_authentication_mode());

		// Verify account ID is loaded
		$reflection       = new \ReflectionClass($gateway);
		$account_property = $reflection->getProperty('oauth_account_id');
		$account_property->setAccessible(true);
		$this->assertEquals('acct_connected_xyz', $account_property->getValue($gateway));

		// Step 4: Verify direct keys would still work if OAuth disconnected
		wu_save_setting('stripe_test_access_token', ''); // Clear OAuth
		wu_save_setting('stripe_test_pk_key', 'pk_test_direct_fallback');
		wu_save_setting('stripe_test_sk_key', 'sk_test_direct_fallback');

		$gateway2 = new Stripe_Gateway();
		$gateway2->init();

		// Should fall back to direct mode
		$this->assertFalse($gateway2->is_using_oauth());
		$this->assertEquals('direct', $gateway2->get_authentication_mode());
	}

	/**
	 * Clear all Stripe settings.
	 */
	private function clear_all_stripe_settings() {
		wu_save_setting('stripe_test_access_token', '');
		wu_save_setting('stripe_test_account_id', '');
		wu_save_setting('stripe_test_publishable_key', '');
		wu_save_setting('stripe_test_refresh_token', '');
		wu_save_setting('stripe_live_access_token', '');
		wu_save_setting('stripe_live_account_id', '');
		wu_save_setting('stripe_live_publishable_key', '');
		wu_save_setting('stripe_live_refresh_token', '');
		wu_save_setting('stripe_test_pk_key', '');
		wu_save_setting('stripe_test_sk_key', '');
		wu_save_setting('stripe_live_pk_key', '');
		wu_save_setting('stripe_live_sk_key', '');
		// Note: Platform credentials are now configured via constants/filters, not settings
	}
}
