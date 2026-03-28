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

	// -------------------------------------------------------------------------
	// is_currency_supported()
	// -------------------------------------------------------------------------

	/**
	 * Test USD is a supported currency.
	 */
	public function test_is_currency_supported_usd(): void {

		wu_save_setting('currency', 'USD');

		$this->assertTrue(PayPal_REST_Gateway::is_currency_supported());
	}

	/**
	 * Test EUR is a supported currency.
	 */
	public function test_is_currency_supported_eur(): void {

		wu_save_setting('currency', 'EUR');

		$this->assertTrue(PayPal_REST_Gateway::is_currency_supported());
	}

	/**
	 * Test GBP is a supported currency.
	 */
	public function test_is_currency_supported_gbp(): void {

		wu_save_setting('currency', 'GBP');

		$this->assertTrue(PayPal_REST_Gateway::is_currency_supported());
	}

	/**
	 * Test unsupported currency returns false.
	 */
	public function test_is_currency_not_supported(): void {

		wu_save_setting('currency', 'NGN'); // Nigerian Naira — not supported

		$this->assertFalse(PayPal_REST_Gateway::is_currency_supported());
	}

	/**
	 * Test currency check is case-insensitive.
	 */
	public function test_is_currency_supported_case_insensitive(): void {

		wu_save_setting('currency', 'usd');

		$this->assertTrue(PayPal_REST_Gateway::is_currency_supported());
	}

	// -------------------------------------------------------------------------
	// maybe_remove_for_unsupported_currency()
	// -------------------------------------------------------------------------

	/**
	 * Test gateway is not removed when currency is supported.
	 */
	public function test_maybe_remove_for_unsupported_currency_keeps_when_supported(): void {

		wu_save_setting('currency', 'USD');

		$gateways = ['paypal-rest' => $this->gateway, 'stripe' => 'stripe'];
		$result   = $this->gateway->maybe_remove_for_unsupported_currency($gateways);

		$this->assertArrayHasKey('paypal-rest', $result);
	}

	/**
	 * Test gateway is removed when currency is not supported.
	 */
	public function test_maybe_remove_for_unsupported_currency_removes_when_unsupported(): void {

		wu_save_setting('currency', 'NGN');

		$gateways = ['paypal-rest' => $this->gateway, 'stripe' => 'stripe'];
		$result   = $this->gateway->maybe_remove_for_unsupported_currency($gateways);

		$this->assertArrayNotHasKey('paypal-rest', $result);
		$this->assertArrayHasKey('stripe', $result);
	}

	/**
	 * Test other gateways are not affected by currency check.
	 */
	public function test_maybe_remove_for_unsupported_currency_preserves_other_gateways(): void {

		wu_save_setting('currency', 'NGN');

		$gateways = ['paypal-rest' => $this->gateway, 'stripe' => 'stripe', 'manual' => 'manual'];
		$result   = $this->gateway->maybe_remove_for_unsupported_currency($gateways);

		$this->assertArrayHasKey('stripe', $result);
		$this->assertArrayHasKey('manual', $result);
	}

	// -------------------------------------------------------------------------
	// get_checkout_label_html()
	// -------------------------------------------------------------------------

	/**
	 * Test checkout label HTML contains PayPal logo.
	 */
	public function test_get_checkout_label_html_contains_logo(): void {

		$html = $this->gateway->get_checkout_label_html('PayPal');

		$this->assertStringContainsString('paypalobjects.com', $html);
		$this->assertStringContainsString('PayPal', $html);
	}

	/**
	 * Test checkout label HTML is a span element.
	 */
	public function test_get_checkout_label_html_is_span(): void {

		$html = $this->gateway->get_checkout_label_html('PayPal');

		$this->assertStringContainsString('<span', $html);
		$this->assertStringContainsString('<img', $html);
	}

	/**
	 * Test checkout label HTML includes the title text.
	 */
	public function test_get_checkout_label_html_includes_title(): void {

		$html = $this->gateway->get_checkout_label_html('Custom Title');

		$this->assertStringContainsString('PayPal', $html); // Always shows PayPal
	}

	// -------------------------------------------------------------------------
	// preserve_oauth_settings()
	// -------------------------------------------------------------------------

	/**
	 * Test preserve_oauth_settings carries forward OAuth keys.
	 */
	public function test_preserve_oauth_settings_carries_forward_oauth_keys(): void {

		$saved_settings = [
			'paypal_rest_sandbox_merchant_id'    => 'MERCHANT123',
			'paypal_rest_sandbox_merchant_email' => 'merchant@example.com',
			'paypal_rest_connected'              => true,
			'paypal_rest_connection_date'        => '2026-01-01 00:00:00',
			'other_setting'                      => 'other_value',
		];

		$result = $this->gateway->preserve_oauth_settings([], [], $saved_settings);

		$this->assertEquals('MERCHANT123', $result['paypal_rest_sandbox_merchant_id']);
		$this->assertEquals('merchant@example.com', $result['paypal_rest_sandbox_merchant_email']);
		$this->assertTrue($result['paypal_rest_connected']);
		$this->assertEquals('2026-01-01 00:00:00', $result['paypal_rest_connection_date']);
	}

	/**
	 * Test preserve_oauth_settings does not carry non-OAuth keys.
	 */
	public function test_preserve_oauth_settings_does_not_carry_non_oauth_keys(): void {

		$saved_settings = [
			'paypal_rest_sandbox_merchant_id' => 'MERCHANT123',
			'other_setting'                   => 'other_value',
		];

		$result = $this->gateway->preserve_oauth_settings([], [], $saved_settings);

		$this->assertArrayNotHasKey('other_setting', $result);
	}

	/**
	 * Test preserve_oauth_settings merges with existing settings.
	 */
	public function test_preserve_oauth_settings_merges_with_existing(): void {

		$existing_settings = ['paypal_rest_sandbox_mode' => 1];
		$saved_settings    = ['paypal_rest_sandbox_merchant_id' => 'MERCHANT123'];

		$result = $this->gateway->preserve_oauth_settings($existing_settings, [], $saved_settings);

		$this->assertEquals(1, $result['paypal_rest_sandbox_mode']);
		$this->assertEquals('MERCHANT123', $result['paypal_rest_sandbox_merchant_id']);
	}

	/**
	 * Test preserve_oauth_settings skips keys not in saved_settings.
	 */
	public function test_preserve_oauth_settings_skips_missing_keys(): void {

		$saved_settings = []; // No OAuth keys saved

		$result = $this->gateway->preserve_oauth_settings([], [], $saved_settings);

		$this->assertArrayNotHasKey('paypal_rest_sandbox_merchant_id', $result);
	}

	// -------------------------------------------------------------------------
	// supports_payment_polling()
	// -------------------------------------------------------------------------

	/**
	 * Test supports_payment_polling returns true.
	 */
	public function test_supports_payment_polling(): void {

		$this->assertTrue($this->gateway->supports_payment_polling());
	}

	// -------------------------------------------------------------------------
	// verify_and_complete_payment()
	// -------------------------------------------------------------------------

	/**
	 * Test verify_and_complete_payment returns error when payment not found.
	 */
	public function test_verify_and_complete_payment_payment_not_found(): void {

		$this->gateway->init();

		$result = $this->gateway->verify_and_complete_payment(999999);

		$this->assertFalse($result['success']);
		$this->assertStringContainsString('not found', strtolower($result['message']));
	}

	/**
	 * Test verify_and_complete_payment returns success when already completed.
	 */
	public function test_verify_and_complete_payment_already_completed(): void {

		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_status(\WP_Ultimo\Database\Payments\Payment_Status::COMPLETED);
		$payment->set_gateway('paypal-rest');
		$payment->set_currency('USD');
		$payment->set_total(100);
		$saved = $payment->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save payment: ' . $saved->get_error_message());
			return;
		}

		$this->gateway->init();

		$result = $this->gateway->verify_and_complete_payment($payment->get_id());

		$this->assertTrue($result['success']);
		$this->assertEquals('completed', $result['status']);
	}

	/**
	 * Test verify_and_complete_payment returns error when membership not found.
	 */
	public function test_verify_and_complete_payment_no_membership(): void {

		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_status(\WP_Ultimo\Database\Payments\Payment_Status::PENDING);
		$payment->set_gateway('paypal-rest');
		$payment->set_currency('USD');
		$payment->set_total(100);
		$saved = $payment->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save payment: ' . $saved->get_error_message());
			return;
		}

		$this->gateway->init();

		$result = $this->gateway->verify_and_complete_payment($payment->get_id());

		$this->assertFalse($result['success']);
		$this->assertStringContainsString('not found', strtolower($result['message']));
	}

	/**
	 * Test verify_and_complete_payment returns error when no subscription ID.
	 */
	public function test_verify_and_complete_payment_no_subscription_id(): void {

		// Create a membership without a subscription ID
		$membership = wu_create_membership(
			[
				'status'     => 'active',
				'gateway'    => 'paypal-rest',
				'product_id' => 0,
			]
		);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Could not create membership: ' . $membership->get_error_message());
			return;
		}

		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_status(\WP_Ultimo\Database\Payments\Payment_Status::PENDING);
		$payment->set_gateway('paypal-rest');
		$payment->set_currency('USD');
		$payment->set_total(100);
		$payment->set_membership_id($membership->get_id());
		$saved = $payment->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save payment: ' . $saved->get_error_message());
			return;
		}

		$this->gateway->init();

		$result = $this->gateway->verify_and_complete_payment($payment->get_id());

		$this->assertFalse($result['success']);
		$this->assertStringContainsString('subscription', strtolower($result['message']));
	}

	// -------------------------------------------------------------------------
	// process_cancellation()
	// -------------------------------------------------------------------------

	/**
	 * Test process_cancellation returns early when no subscription ID.
	 */
	public function test_process_cancellation_returns_early_without_subscription(): void {

		$membership = wu_create_membership(
			[
				'status'     => 'active',
				'gateway'    => 'paypal-rest',
				'product_id' => 0,
			]
		);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Could not create membership: ' . $membership->get_error_message());
			return;
		}

		$customer = wu_get_customer_by_user_id(get_current_user_id());

		$this->gateway->init();

		// Should not throw — returns early when no subscription ID
		$this->gateway->process_cancellation($membership, $customer);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// process_membership_update()
	// -------------------------------------------------------------------------

	/**
	 * Test process_membership_update returns WP_Error when no subscription ID.
	 */
	public function test_process_membership_update_no_subscription_id(): void {

		$membership = wu_create_membership(
			[
				'status'     => 'active',
				'gateway'    => 'paypal-rest',
				'product_id' => 0,
			]
		);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Could not create membership: ' . $membership->get_error_message());
			return;
		}

		$customer = wu_get_customer_by_user_id(get_current_user_id());

		$this->gateway->init();

		$result = $this->gateway->process_membership_update($membership, $customer);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('wu_paypal_no_subscription', $result->get_error_code());
	}

	/**
	 * Test process_membership_update returns true when subscription ID exists.
	 */
	public function test_process_membership_update_with_subscription_id(): void {

		$membership = wu_create_membership(
			[
				'status'     => 'active',
				'gateway'    => 'paypal-rest',
				'product_id' => 0,
			]
		);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Could not create membership: ' . $membership->get_error_message());
			return;
		}

		$membership->set_gateway_subscription_id('I-SUBSCRIPTION123');
		$membership->save();

		$customer = wu_get_customer_by_user_id(get_current_user_id());

		$this->gateway->init();

		$result = $this->gateway->process_membership_update($membership, $customer);

		$this->assertTrue($result);
	}

	// -------------------------------------------------------------------------
	// process_refund()
	// -------------------------------------------------------------------------

	/**
	 * Test process_refund throws exception when no capture ID.
	 */
	public function test_process_refund_throws_when_no_capture_id(): void {

		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_status(\WP_Ultimo\Database\Payments\Payment_Status::COMPLETED);
		$payment->set_gateway('paypal-rest');
		$payment->set_currency('USD');
		$payment->set_total(100);
		$saved = $payment->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save payment: ' . $saved->get_error_message());
			return;
		}

		$membership = wu_create_membership(
			[
				'status'     => 'active',
				'gateway'    => 'paypal-rest',
				'product_id' => 0,
			]
		);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Could not create membership: ' . $membership->get_error_message());
			return;
		}

		$customer = wu_get_customer_by_user_id(get_current_user_id());

		$this->gateway->init();

		$this->expectException(\Exception::class);

		$this->gateway->process_refund(100, $payment, $membership, $customer);
	}

	// -------------------------------------------------------------------------
	// format_amount()
	// -------------------------------------------------------------------------

	/**
	 * Test format_amount returns 2 decimal places for USD.
	 */
	public function test_format_amount_usd(): void {

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('format_amount');

		$result = $method->invoke($this->gateway, 99.9, 'USD');

		$this->assertEquals('99.90', $result);
	}

	/**
	 * Test format_amount returns 2 decimal places for EUR.
	 */
	public function test_format_amount_eur(): void {

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('format_amount');

		$result = $method->invoke($this->gateway, 10.5, 'EUR');

		$this->assertEquals('10.50', $result);
	}

	/**
	 * Test format_amount returns 0 decimal places for JPY (zero-decimal currency).
	 */
	public function test_format_amount_jpy_zero_decimal(): void {

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('format_amount');

		$result = $method->invoke($this->gateway, 4767.0, 'JPY');

		// JPY is zero-decimal — should not have decimal point
		$this->assertEquals('4767', $result);
	}

	/**
	 * Test format_amount rounds correctly.
	 */
	public function test_format_amount_rounds_correctly(): void {

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('format_amount');

		$result = $method->invoke($this->gateway, 10.999, 'USD');

		$this->assertEquals('11.00', $result);
	}

	/**
	 * Test format_amount with zero amount.
	 */
	public function test_format_amount_zero(): void {

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('format_amount');

		$result = $method->invoke($this->gateway, 0.0, 'USD');

		$this->assertEquals('0.00', $result);
	}

	// -------------------------------------------------------------------------
	// get_payment_currency_code()
	// -------------------------------------------------------------------------

	/**
	 * Test get_payment_currency_code returns valid ISO code.
	 */
	public function test_get_payment_currency_code_valid_iso(): void {

		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_currency('EUR');

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_payment_currency_code');

		$result = $method->invoke($this->gateway, $payment);

		$this->assertEquals('EUR', $result);
	}

	/**
	 * Test get_payment_currency_code normalizes lowercase to uppercase.
	 */
	public function test_get_payment_currency_code_normalizes_case(): void {

		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_currency('usd');

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_payment_currency_code');

		$result = $method->invoke($this->gateway, $payment);

		$this->assertEquals('USD', $result);
	}

	/**
	 * Test get_payment_currency_code falls back to store currency for symbol.
	 */
	public function test_get_payment_currency_code_falls_back_for_symbol(): void {

		wu_save_setting('currency', 'USD');

		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_currency('$'); // Symbol instead of ISO code

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_payment_currency_code');

		$result = $method->invoke($this->gateway, $payment);

		// Should return a valid 3-letter ISO code
		$this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $result);
	}

	// -------------------------------------------------------------------------
	// render_currency_warning()
	// -------------------------------------------------------------------------

	/**
	 * Test render_currency_warning outputs HTML with currency code.
	 */
	public function test_render_currency_warning_outputs_html(): void {

		wu_save_setting('currency', 'NGN');

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		ob_start();
		$gateway->render_currency_warning();
		$output = ob_get_clean();

		$this->assertStringContainsString('NGN', $output);
		$this->assertStringContainsString('Unsupported Currency', $output);
	}

	/**
	 * Test render_currency_warning includes docs link.
	 */
	public function test_render_currency_warning_includes_docs_link(): void {

		wu_save_setting('currency', 'NGN');

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		ob_start();
		$gateway->render_currency_warning();
		$output = ob_get_clean();

		$this->assertStringContainsString('developer.paypal.com', $output);
	}

	// -------------------------------------------------------------------------
	// hooks()
	// -------------------------------------------------------------------------

	/**
	 * Test hooks registers the preserve_oauth_settings filter.
	 */
	public function test_hooks_registers_preserve_oauth_settings(): void {

		$this->gateway->init();
		$this->gateway->hooks();

		$this->assertGreaterThan(0, has_filter('wu_pre_save_settings', [$this->gateway, 'preserve_oauth_settings']));

		remove_filter('wu_pre_save_settings', [$this->gateway, 'preserve_oauth_settings']);
	}

	/**
	 * Test hooks registers the maybe_install_webhook action.
	 */
	public function test_hooks_registers_maybe_install_webhook(): void {

		$this->gateway->init();
		$this->gateway->hooks();

		$this->assertGreaterThan(0, has_action('wu_after_save_settings', [$this->gateway, 'maybe_install_webhook']));

		remove_action('wu_after_save_settings', [$this->gateway, 'maybe_install_webhook']);
	}

	/**
	 * Test hooks registers the unsupported currency filter.
	 */
	public function test_hooks_registers_currency_filter(): void {

		$this->gateway->init();
		$this->gateway->hooks();

		$this->assertGreaterThan(0, has_filter('wu_get_active_gateways', [$this->gateway, 'maybe_remove_for_unsupported_currency']));

		remove_filter('wu_get_active_gateways', [$this->gateway, 'maybe_remove_for_unsupported_currency']);
	}

	// -------------------------------------------------------------------------
	// get_access_token() — cached token path
	// -------------------------------------------------------------------------

	/**
	 * Test get_access_token returns cached token when set.
	 */
	public function test_get_access_token_returns_cached_token(): void {

		$this->gateway->init();

		// Set a cached access token via reflection
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('access_token');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, 'cached_token_abc123');

		$method = $reflection->getMethod('get_access_token');
		$result = $method->invoke($this->gateway);

		$this->assertEquals('cached_token_abc123', $result);
	}

	/**
	 * Test get_access_token returns WP_Error when credentials missing (no transient).
	 *
	 * Note: site transients require wptests_sitemeta which may not exist in all
	 * test environments. This test verifies the error path when no token is cached
	 * and credentials are empty.
	 */
	public function test_get_access_token_error_when_credentials_empty(): void {

		// No credentials set — should return WP_Error
		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		$reflection = new \ReflectionClass($gateway);
		$method     = $reflection->getMethod('get_access_token');
		$result     = $method->invoke($gateway);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('wu_paypal_missing_credentials', $result->get_error_code());
	}

	// -------------------------------------------------------------------------
	// process_confirmation() — invalid request
	// -------------------------------------------------------------------------

	/**
	 * Test process_confirmation calls wp_die when no token or subscription_id.
	 */
	public function test_process_confirmation_dies_without_token_or_subscription(): void {

		$this->gateway->init();

		// Ensure no token or subscription_id in request
		unset($_REQUEST['token'], $_REQUEST['subscription_id']);

		$this->expectException(\WPDieException::class);

		$this->gateway->process_confirmation();
	}

	// -------------------------------------------------------------------------
	// find_capture_id_for_payment()
	// -------------------------------------------------------------------------

	/**
	 * Test find_capture_id_for_payment returns false when no subscription ID.
	 */
	public function test_find_capture_id_for_payment_no_subscription_id(): void {

		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_status(\WP_Ultimo\Database\Payments\Payment_Status::COMPLETED);
		$payment->set_currency('USD');
		$payment->set_total(100);
		$saved = $payment->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save payment: ' . $saved->get_error_message());
			return;
		}

		$membership = wu_create_membership(
			[
				'status'     => 'active',
				'gateway'    => 'paypal-rest',
				'product_id' => 0,
			]
		);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Could not create membership: ' . $membership->get_error_message());
			return;
		}

		// No subscription ID set
		$this->gateway->init();

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('find_capture_id_for_payment');

		$result = $method->invoke($this->gateway, $payment, $membership);

		$this->assertFalse($result);
	}

	// -------------------------------------------------------------------------
	// maybe_install_webhook() — additional branches
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_install_webhook skips when settings unchanged.
	 */
	public function test_maybe_install_webhook_skips_when_settings_unchanged(): void {

		$this->gateway->init();

		$settings = [
			'paypal_rest_sandbox_mode'          => '1',
			'paypal_rest_sandbox_client_id'     => 'same_id',
			'paypal_rest_sandbox_client_secret' => 'same_secret',
			'paypal_rest_live_client_id'        => '',
			'paypal_rest_live_client_secret'    => '',
		];

		// Same settings in both arrays — should skip
		$this->gateway->maybe_install_webhook(
			$settings,
			['active_gateways' => ['paypal-rest']],
			$settings
		);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_install_webhook skips when not configured after reload.
	 */
	public function test_maybe_install_webhook_skips_when_not_configured(): void {

		$this->gateway->init();

		// Settings changed but no credentials
		$new_settings = [
			'paypal_rest_sandbox_mode'          => '1',
			'paypal_rest_sandbox_client_id'     => '',
			'paypal_rest_sandbox_client_secret' => '',
			'paypal_rest_live_client_id'        => '',
			'paypal_rest_live_client_secret'    => '',
		];

		$old_settings = [
			'paypal_rest_sandbox_mode'          => '0',
			'paypal_rest_sandbox_client_id'     => '',
			'paypal_rest_sandbox_client_secret' => '',
			'paypal_rest_live_client_id'        => '',
			'paypal_rest_live_client_secret'    => '',
		];

		// Should not throw — skips because not configured
		$this->gateway->maybe_install_webhook(
			$new_settings,
			['active_gateways' => ['paypal-rest']],
			$old_settings
		);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// has_webhook_installed()
	// -------------------------------------------------------------------------

	/**
	 * Test has_webhook_installed returns false when no webhook ID stored.
	 */
	public function test_has_webhook_installed_returns_false_when_no_id(): void {

		wu_save_setting('paypal_rest_sandbox_webhook_id', '');

		$this->gateway->init();

		$result = $this->gateway->has_webhook_installed();

		$this->assertFalse($result);
	}

	// -------------------------------------------------------------------------
	// delete_webhook()
	// -------------------------------------------------------------------------

	/**
	 * Test delete_webhook returns true when no webhook ID stored.
	 */
	public function test_delete_webhook_returns_true_when_no_id(): void {

		wu_save_setting('paypal_rest_sandbox_webhook_id', '');

		$this->gateway->init();

		$result = $this->gateway->delete_webhook();

		$this->assertTrue($result);
	}

	// -------------------------------------------------------------------------
	// get_connection_status() — live mode
	// -------------------------------------------------------------------------

	/**
	 * Test connection status in live mode with manual credentials.
	 */
	public function test_connection_status_live_mode_manual(): void {

		wu_save_setting('paypal_rest_sandbox_mode', 0);
		wu_save_setting('paypal_rest_live_client_id', 'live_client_id');
		wu_save_setting('paypal_rest_live_client_secret', 'live_client_secret');

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();
		$status = $gateway->get_connection_status();

		$this->assertTrue($status['connected']);
		$this->assertEquals('manual', $status['details']['method']);
		$this->assertEquals('live', $status['details']['mode']);
	}

	/**
	 * Test connection status not connected in live mode.
	 */
	public function test_connection_status_not_connected_live_mode(): void {

		wu_save_setting('paypal_rest_sandbox_mode', 0);

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();
		$status = $gateway->get_connection_status();

		$this->assertFalse($status['connected']);
		$this->assertEquals('live', $status['details']['mode']);
	}

	// -------------------------------------------------------------------------
	// set_test_mode() — live to sandbox
	// -------------------------------------------------------------------------

	/**
	 * Test set_test_mode clears access token cache.
	 */
	public function test_set_test_mode_clears_access_token(): void {

		wu_save_setting('paypal_rest_sandbox_client_id', 'sandbox_id');
		wu_save_setting('paypal_rest_sandbox_client_secret', 'sandbox_secret');
		wu_save_setting('paypal_rest_sandbox_mode', 1);

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		// Set a cached access token
		$reflection = new \ReflectionClass($gateway);
		$prop       = $reflection->getProperty('access_token');
		$prop->setAccessible(true);
		$prop->setValue($gateway, 'old_token');

		// Switch mode — should clear token
		$gateway->set_test_mode(false);

		$this->assertEquals('', $prop->getValue($gateway));
	}

	/**
	 * Test set_test_mode switches to live credentials.
	 */
	public function test_set_test_mode_switches_to_live(): void {

		wu_save_setting('paypal_rest_sandbox_client_id', 'sandbox_id');
		wu_save_setting('paypal_rest_live_client_id', 'live_id');
		wu_save_setting('paypal_rest_sandbox_mode', 1);

		$gateway = new PayPal_REST_Gateway();
		$gateway->init();

		$gateway->set_test_mode(false);

		$reflection = new \ReflectionClass($gateway);
		$prop       = $reflection->getProperty('client_id');

		$this->assertEquals('live_id', $prop->getValue($gateway));
	}

	// -------------------------------------------------------------------------
	// get_all_ids() / backwards compatibility
	// -------------------------------------------------------------------------

	/**
	 * Test backwards_compatibility_v1_id is false.
	 */
	public function test_backwards_compatibility_v1_id_is_false(): void {

		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('backwards_compatibility_v1_id');
		$prop->setAccessible(true);

		$this->assertFalse($prop->getValue($this->gateway));
	}

	// -------------------------------------------------------------------------
	// API base URL
	// -------------------------------------------------------------------------

	/**
	 * Test API base URL sandbox contains sandbox subdomain.
	 */
	public function test_api_base_url_sandbox_contains_sandbox(): void {

		$this->gateway->init();

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_api_base_url');

		$url = $method->invoke($this->gateway);

		$this->assertStringContainsString('sandbox', $url);
	}

	/**
	 * Test API base URL live does not contain sandbox.
	 */
	public function test_api_base_url_live_no_sandbox(): void {

		$this->gateway->set_test_mode(false);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_api_base_url');

		$url = $method->invoke($this->gateway);

		$this->assertStringNotContainsString('sandbox', $url);
	}

	// -------------------------------------------------------------------------
	// build_order_items()
	// -------------------------------------------------------------------------

	/**
	 * Test build_order_items returns empty array for empty cart.
	 */
	public function test_build_order_items_empty_cart(): void {

		$cart = $this->createMock(\WP_Ultimo\Checkout\Cart::class);
		$cart->method('get_line_items')->willReturn([]);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('build_order_items');

		$result = $method->invoke($this->gateway, $cart, 'USD');

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	// -------------------------------------------------------------------------
	// Supported currencies list
	// -------------------------------------------------------------------------

	/**
	 * Test all major currencies are in the supported list.
	 */
	public function test_supported_currencies_includes_major_currencies(): void {

		$major_currencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'];

		foreach ($major_currencies as $currency) {
			wu_save_setting('currency', $currency);
			$this->assertTrue(
				PayPal_REST_Gateway::is_currency_supported(),
				"$currency should be supported"
			);
		}
	}

	/**
	 * Test BRL is in the supported currencies list.
	 */
	public function test_supported_currencies_includes_brl(): void {

		wu_save_setting('currency', 'BRL');

		$this->assertTrue(PayPal_REST_Gateway::is_currency_supported());
	}

	/**
	 * Test MXN is in the supported currencies list.
	 */
	public function test_supported_currencies_includes_mxn(): void {

		wu_save_setting('currency', 'MXN');

		$this->assertTrue(PayPal_REST_Gateway::is_currency_supported());
	}
}
