<?php
/**
 * Tests for PayPal REST Gateway.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @since 2.x.x
 */

namespace WP_Ultimo\Gateways;

use WP_UnitTestCase;

/**
 * PayPal REST Gateway Test class.
 */
class PayPal_REST_Gateway_Test extends WP_UnitTestCase {

	/**
	 * Test gateway instance.
	 *
	 * @var PayPal_REST_Gateway
	 */
	protected $gateway;

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear all PayPal REST settings before each test
		wu_save_setting('paypal_rest_sandbox_mode', 1);
		wu_save_setting('paypal_rest_sandbox_client_id', '');
		wu_save_setting('paypal_rest_sandbox_client_secret', '');
		wu_save_setting('paypal_rest_sandbox_merchant_id', '');
		wu_save_setting('paypal_rest_live_client_id', '');
		wu_save_setting('paypal_rest_live_client_secret', '');
		wu_save_setting('paypal_rest_live_merchant_id', '');
		wu_save_setting('paypal_rest_connected', false);
		wu_save_setting('paypal_rest_sandbox_webhook_id', '');
		wu_save_setting('paypal_rest_live_webhook_id', '');

		$this->gateway = new PayPal_REST_Gateway();
	}

	/**
	 * Test gateway ID.
	 */
	public function test_gateway_id(): void {

		$this->assertEquals('paypal-rest', $this->gateway->get_id());
	}

	/**
	 * Test gateway supports recurring.
	 */
	public function test_supports_recurring(): void {

		$this->assertTrue($this->gateway->supports_recurring());
	}

	/**
	 * Test gateway supports amount update.
	 */
	public function test_supports_amount_update(): void {

		$this->assertTrue($this->gateway->supports_amount_update());
	}

	/**
	 * Test not configured when no credentials.
	 */
	public function test_not_configured_without_credentials(): void {

		$this->gateway->init();
		$this->assertFalse($this->gateway->is_configured());
	}

	/**
	 * Test configured with manual client credentials.
	 */
	public function test_configured_with_manual_credentials(): void {

		wu_save_setting('paypal_rest_sandbox_client_id', 'test_client_id_123');
		wu_save_setting('paypal_rest_sandbox_client_secret', 'test_client_secret_456');
		wu_save_setting('paypal_rest_sandbox_mode', 1);

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		$this->assertTrue($gateway->is_configured());
	}

	/**
	 * Test configured with OAuth merchant ID.
	 */
	public function test_configured_with_oauth_merchant_id(): void {

		wu_save_setting('paypal_rest_sandbox_merchant_id', 'MERCHANT123');
		wu_save_setting('paypal_rest_sandbox_mode', 1);

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		$this->assertTrue($gateway->is_configured());
	}

	/**
	 * Test connection status when not connected.
	 */
	public function test_connection_status_not_connected(): void {

		$this->gateway->init();
		$status = $this->gateway->get_connection_status();

		$this->assertFalse($status['connected']);
		$this->assertArrayHasKey('message', $status);
		$this->assertArrayHasKey('details', $status);
	}

	/**
	 * Test connection status with manual credentials.
	 */
	public function test_connection_status_with_manual_credentials(): void {

		wu_save_setting('paypal_rest_sandbox_client_id', 'test_client_id_123');
		wu_save_setting('paypal_rest_sandbox_client_secret', 'test_client_secret_456');
		wu_save_setting('paypal_rest_sandbox_mode', 1);

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();
		$status = $gateway->get_connection_status();

		$this->assertTrue($status['connected']);
		$this->assertEquals('manual', $status['details']['method']);
		$this->assertEquals('sandbox', $status['details']['mode']);
	}

	/**
	 * Test connection status with OAuth merchant.
	 */
	public function test_connection_status_with_oauth(): void {

		wu_save_setting('paypal_rest_sandbox_merchant_id', 'MERCHANT123');
		wu_save_setting('paypal_rest_sandbox_merchant_email', 'merchant@example.com');
		wu_save_setting('paypal_rest_sandbox_mode', 1);
		wu_save_setting('paypal_rest_connection_date', '2026-01-01 00:00:00');

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();
		$status = $gateway->get_connection_status();

		$this->assertTrue($status['connected']);
		$this->assertEquals('oauth', $status['details']['method']);
		$this->assertEquals('MERCHANT123', $status['details']['merchant_id']);
	}

	/**
	 * Test set_test_mode switches credentials.
	 */
	public function test_set_test_mode(): void {

		wu_save_setting('paypal_rest_sandbox_client_id', 'sandbox_id');
		wu_save_setting('paypal_rest_sandbox_client_secret', 'sandbox_secret');
		wu_save_setting('paypal_rest_live_client_id', 'live_id');
		wu_save_setting('paypal_rest_live_client_secret', 'live_secret');
		wu_save_setting('paypal_rest_sandbox_mode', 1);

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		// Verify sandbox mode credentials
		$reflection = new \ReflectionClass($gateway);
		$prop       = $reflection->getProperty('client_id');

		$this->assertEquals('sandbox_id', $prop->getValue($gateway));

		// Switch to live mode
		$gateway->set_test_mode(false);

		$this->assertEquals('live_id', $prop->getValue($gateway));
	}

	/**
	 * Test payment URL generation.
	 */
	public function test_payment_url_on_gateway(): void {

		$this->gateway->init();

		$url = $this->gateway->get_payment_url_on_gateway('PAY-123');
		$this->assertStringContainsString('sandbox.paypal.com', $url);
		$this->assertStringContainsString('PAY-123', $url);
	}

	/**
	 * Test payment URL empty for empty ID.
	 */
	public function test_payment_url_empty_for_empty_id(): void {

		$this->gateway->init();

		$url = $this->gateway->get_payment_url_on_gateway('');
		$this->assertEmpty($url);
	}

	/**
	 * Test subscription URL for REST API subscription.
	 */
	public function test_subscription_url_rest_api(): void {

		$this->gateway->init();

		$url = $this->gateway->get_subscription_url_on_gateway('I-SUBSCRIPTION123');
		$this->assertStringContainsString('sandbox.paypal.com', $url);
		$this->assertStringContainsString('billing/subscriptions', $url);
		$this->assertStringContainsString('I-SUBSCRIPTION123', $url);
	}

	/**
	 * Test subscription URL for legacy NVP profile.
	 */
	public function test_subscription_url_legacy_nvp(): void {

		$this->gateway->init();

		$url = $this->gateway->get_subscription_url_on_gateway('LEGACY-PROFILE-123');
		$this->assertStringContainsString('sandbox.paypal.com', $url);
		$this->assertStringContainsString('_profile-recurring-payments', $url);
	}

	/**
	 * Test subscription URL empty for empty ID.
	 */
	public function test_subscription_url_empty_for_empty_id(): void {

		$this->gateway->init();

		$url = $this->gateway->get_subscription_url_on_gateway('');
		$this->assertEmpty($url);
	}

	/**
	 * Test live mode URLs.
	 */
	public function test_live_mode_urls(): void {

		wu_save_setting('paypal_rest_sandbox_mode', 0);
		wu_save_setting('paypal_rest_live_client_id', 'live_id');
		wu_save_setting('paypal_rest_live_client_secret', 'live_secret');

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		$url = $gateway->get_payment_url_on_gateway('PAY-123');
		$this->assertStringContainsString('www.paypal.com', $url);
		$this->assertStringNotContainsString('sandbox', $url);
	}

	/**
	 * Test other_ids includes both paypal and paypal-rest.
	 */
	public function test_other_ids(): void {

		$all_ids = $this->gateway->get_all_ids();

		$this->assertContains('paypal-rest', $all_ids);
		$this->assertContains('paypal', $all_ids);
	}

	/**
	 * Test API base URL in sandbox mode.
	 */
	public function test_api_base_url_sandbox(): void {

		$this->gateway->init();

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_api_base_url');

		$url = $method->invoke($this->gateway);
		$this->assertEquals('https://api-m.sandbox.paypal.com', $url);
	}

	/**
	 * Test API base URL in live mode.
	 */
	public function test_api_base_url_live(): void {

		$this->gateway->set_test_mode(false);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_api_base_url');

		$url = $method->invoke($this->gateway);
		$this->assertEquals('https://api-m.paypal.com', $url);
	}

	/**
	 * Test access token error without credentials.
	 */
	public function test_access_token_error_without_credentials(): void {

		$this->gateway->init();

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_access_token');

		$result = $method->invoke($this->gateway);
		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('wu_paypal_missing_credentials', $result->get_error_code());
	}

	/**
	 * Test platform fee not applied without OAuth merchant ID.
	 */
	public function test_platform_fee_not_applied_without_oauth(): void {

		wu_save_setting('paypal_rest_sandbox_client_id', 'test_client_id');
		wu_save_setting('paypal_rest_sandbox_client_secret', 'test_secret');

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		$this->assertFalse($gateway->should_apply_platform_fee());
	}

	/**
	 * Test platform fee applied with OAuth merchant ID and no addon purchase.
	 */
	public function test_platform_fee_applied_with_oauth(): void {

		wu_save_setting('paypal_rest_sandbox_merchant_id', 'MERCHANT123');

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		// Fee should apply when OAuth connected and no addon purchased
		// (has_addon_purchase returns false by default in test)
		$this->assertTrue($gateway->should_apply_platform_fee());
	}

	/**
	 * Test platform fee percentage.
	 */
	public function test_platform_fee_percent(): void {

		$this->assertEquals(3.0, $this->gateway->get_platform_fee_percent());
	}

	/**
	 * Test PayPal-Auth-Assertion JWT format.
	 */
	public function test_build_auth_assertion(): void {

		$this->gateway->init();

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('build_auth_assertion');

		$assertion = $method->invoke($this->gateway, 'PARTNER_CLIENT_ID', 'MERCHANT_PAYER_ID');

		// Should be base64(header).base64(payload).
		$parts = explode('.', $assertion);
		$this->assertCount(3, $parts);
		$this->assertEquals('', $parts[2]); // Empty signature

		// Decode header
		$header = json_decode(base64_decode($parts[0]), true); // phpcs:ignore
		$this->assertEquals('none', $header['alg']);

		// Decode payload
		$payload = json_decode(base64_decode($parts[1]), true); // phpcs:ignore
		$this->assertEquals('PARTNER_CLIENT_ID', $payload['iss']);
		$this->assertEquals('MERCHANT_PAYER_ID', $payload['payer_id']);
	}

	/**
	 * Test get_partner_data returns error when proxy unavailable.
	 */
	public function test_get_partner_data_error_on_proxy_failure(): void {

		$this->gateway->init();

		// Override proxy URL to a non-existent server
		add_filter(
			'wu_paypal_connect_proxy_url',
			function () {
				return 'https://nonexistent-proxy.test/wp-json/paypal-connect/v1';
			}
		);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_partner_data');

		$result = $method->invoke($this->gateway);
		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test settings registers fields without OAuth feature flag.
	 *
	 * When OAuth is disabled (default), manual keys are shown directly
	 * without the advanced toggle or OAuth connection field.
	 */
	public function test_settings_registers_fields_without_oauth(): void {

		$this->gateway->init();

		// Ensure OAuth feature flag is off (default state)
		add_filter('wu_paypal_oauth_enabled', '__return_false');

		$this->gateway->settings();

		$fields    = apply_filters('wu_settings_section_payment-gateways_fields', []); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		$field_ids = array_keys($fields);

		// Core fields always present
		$this->assertContains('paypal_rest_header', $field_ids);
		$this->assertContains('paypal_rest_sandbox_mode', $field_ids);
		$this->assertContains('paypal_rest_sandbox_client_id', $field_ids);
		$this->assertContains('paypal_rest_webhook_url', $field_ids);

		// OAuth fields should NOT be present
		$this->assertNotContains('paypal_rest_oauth_connection', $field_ids);
		$this->assertNotContains('paypal_rest_show_manual_keys', $field_ids);

		remove_filter('wu_paypal_oauth_enabled', '__return_false');
	}

	/**
	 * Test settings registers OAuth fields when feature flag is on.
	 */
	public function test_settings_registers_oauth_fields_when_enabled(): void {

		$this->gateway->init();

		add_filter('wu_paypal_oauth_enabled', '__return_true');

		$this->gateway->settings();

		$fields    = apply_filters('wu_settings_section_payment-gateways_fields', []); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		$field_ids = array_keys($fields);

		$this->assertContains('paypal_rest_oauth_connection', $field_ids);
		$this->assertContains('paypal_rest_show_manual_keys', $field_ids);
		$this->assertContains('paypal_rest_sandbox_client_id', $field_ids);

		remove_filter('wu_paypal_oauth_enabled', '__return_true');
	}

	/**
	 * Test settings fields require active gateway.
	 */
	public function test_settings_fields_require_active_gateway(): void {

		$this->gateway->init();
		$this->gateway->settings();

		$fields = apply_filters('wu_settings_section_payment-gateways_fields', []); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		// All PayPal REST fields should require the gateway to be active
		foreach ($fields as $field_id => $field) {
			if (strpos($field_id, 'paypal_rest_') === 0) {
				$this->assertArrayHasKey('require', $field, "Field $field_id should have require key");
				$this->assertEquals('paypal-rest', $field['require']['active_gateways'] ?? '', "Field $field_id should require paypal-rest gateway");
			}
		}
	}

	/**
	 * Test manual keys shown directly when OAuth is disabled.
	 */
	public function test_manual_fields_shown_directly_without_oauth(): void {

		$this->gateway->init();

		add_filter('wu_paypal_oauth_enabled', '__return_false');

		$this->gateway->settings();

		$fields = apply_filters('wu_settings_section_payment-gateways_fields', []); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		// Manual key fields should NOT require the show_manual_keys toggle
		$this->assertArrayHasKey('paypal_rest_sandbox_client_id', $fields);
		$this->assertArrayNotHasKey(
			'paypal_rest_show_manual_keys',
			$fields['paypal_rest_sandbox_client_id']['require'],
			'Manual keys should be shown directly when OAuth is disabled'
		);

		remove_filter('wu_paypal_oauth_enabled', '__return_false');
	}

	/**
	 * Test manual fields require toggle when OAuth is enabled.
	 */
	public function test_manual_fields_require_toggle_with_oauth(): void {

		$this->gateway->init();

		add_filter('wu_paypal_oauth_enabled', '__return_true');

		$this->gateway->settings();

		$fields = apply_filters('wu_settings_section_payment-gateways_fields', []); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$this->assertArrayHasKey('paypal_rest_sandbox_client_id', $fields);
		$this->assertEquals(
			1,
			$fields['paypal_rest_sandbox_client_id']['require']['paypal_rest_show_manual_keys'] ?? null,
			'Manual keys should require toggle when OAuth is enabled'
		);

		remove_filter('wu_paypal_oauth_enabled', '__return_true');
	}

	/**
	 * Test OAuth connection field uses html type with content callback.
	 */
	public function test_oauth_connection_field_type(): void {

		$this->gateway->init();

		add_filter('wu_paypal_oauth_enabled', '__return_true');

		$this->gateway->settings();

		$fields = apply_filters('wu_settings_section_payment-gateways_fields', []); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$this->assertArrayHasKey('paypal_rest_oauth_connection', $fields);
		$this->assertEquals('html', $fields['paypal_rest_oauth_connection']['type']);
		$this->assertIsCallable($fields['paypal_rest_oauth_connection']['content']);

		remove_filter('wu_paypal_oauth_enabled', '__return_true');
	}

	/**
	 * Test render_oauth_connection outputs disconnected state.
	 */
	public function test_render_oauth_connection_disconnected(): void {

		$this->gateway->init();

		ob_start();
		$this->gateway->render_oauth_connection();
		$output = ob_get_clean();

		// Should show the disconnected/manual keys prompt since no proxy configured in test
		$this->assertStringContainsString('wu-oauth-status', $output);
		$this->assertStringContainsString('wu-disconnected', $output);
	}

	/**
	 * Test render_oauth_connection outputs connected state with merchant ID.
	 */
	public function test_render_oauth_connection_connected(): void {

		wu_save_setting('paypal_rest_sandbox_merchant_id', 'TESTMERCHANT456');
		wu_save_setting('paypal_rest_sandbox_mode', 1);

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		ob_start();
		$gateway->render_oauth_connection();
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-connected', $output);
		$this->assertStringContainsString('TESTMERCHANT456', $output);
		$this->assertStringContainsString('wu-paypal-disconnect', $output);
	}

	/**
	 * Test render_oauth_connection renders connect button.
	 */
	public function test_render_oauth_connection_fee_notice(): void {

		$this->gateway->init();

		ob_start();
		$this->gateway->render_oauth_connection();
		$output = ob_get_clean();

		// Should render the connect button
		$this->assertStringContainsString('wu-paypal-connect', strtolower($output));
	}

	/**
	 * Test webhook listener URL is well-formed.
	 */
	public function test_webhook_listener_url(): void {

		$this->gateway->init();

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_webhook_listener_url');

		$url = $method->invoke($this->gateway);
		$this->assertNotEmpty($url);
		$this->assertStringContainsString('paypal-rest', $url);
	}

	/**
	 * Test maybe_install_webhook skips when gateway not active.
	 */
	public function test_maybe_install_webhook_skips_inactive_gateway(): void {

		$this->gateway->init();

		// Should not throw errors or install when gateway is not active
		$this->gateway->maybe_install_webhook(
			[],
			['active_gateways' => ['stripe']],
			[]
		);

		// No assertion needed — just verify it doesn't error
		$this->assertTrue(true);
	}
}
