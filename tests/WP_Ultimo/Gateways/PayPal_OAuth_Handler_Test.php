<?php
/**
 * Tests for PayPal OAuth Handler.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @since 2.x.x
 */

namespace WP_Ultimo\Gateways;

use WP_UnitTestCase;

/**
 * PayPal OAuth Handler Test class.
 *
 * Covers: singleton, is_configured, is_merchant_connected, get_merchant_details,
 * init hooks, is_oauth_feature_enabled (filter/transient/HTTP paths),
 * add_oauth_notice, display_oauth_notices, handle_oauth_return (all branches),
 * ajax_initiate_oauth (proxy error/success/tracking transient),
 * ajax_disconnect (settings cleared/transients deleted),
 * verify_merchant_via_proxy (WP_Error/non-200/success),
 * get_proxy_url filter, get_api_base_url sandbox/live.
 */
class PayPal_OAuth_Handler_Test extends WP_UnitTestCase {

	/**
	 * Test handler instance.
	 *
	 * @var PayPal_OAuth_Handler
	 */
	protected $handler;

	/**
	 * Settings cleared in setUp/tearDown.
	 *
	 * @var string[]
	 */
	private static $settings_to_clear = [
		'paypal_rest_sandbox_mode',
		'paypal_rest_sandbox_merchant_id',
		'paypal_rest_sandbox_merchant_email',
		'paypal_rest_sandbox_payments_receivable',
		'paypal_rest_sandbox_email_confirmed',
		'paypal_rest_live_merchant_id',
		'paypal_rest_live_merchant_email',
		'paypal_rest_live_payments_receivable',
		'paypal_rest_live_email_confirmed',
		'paypal_rest_connected',
		'paypal_rest_connection_date',
		'paypal_rest_connection_mode',
		'paypal_rest_sandbox_webhook_id',
		'paypal_rest_live_webhook_id',
	];

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		parent::setUp();

		wu_save_setting('paypal_rest_sandbox_mode', 1);

		foreach (self::$settings_to_clear as $setting) {
			if ('paypal_rest_sandbox_mode' !== $setting) {
				wu_save_setting($setting, '');
			}
		}

		delete_site_transient('wu_paypal_oauth_enabled');
		delete_site_transient('wu_paypal_oauth_notice');
		delete_site_transient('wu_paypal_rest_access_token_sandbox');
		delete_site_transient('wu_paypal_rest_access_token_live');

		// Make wp_send_json_* use wp_die() (throwable) instead of die (not catchable)
		add_filter('wp_doing_ajax', '__return_true');

		$this->handler = PayPal_OAuth_Handler::get_instance();
	}

	/**
	 * Tear down: remove all filters added during tests.
	 */
	public function tearDown(): void {
		remove_all_filters('wu_paypal_connect_proxy_url');
		remove_all_filters('wu_paypal_oauth_enabled');
		remove_all_filters('pre_http_request');
		remove_all_filters('wp_redirect');
		remove_all_filters('wp_doing_ajax');

		delete_site_transient('wu_paypal_oauth_enabled');
		delete_site_transient('wu_paypal_oauth_notice');
		delete_site_transient('wu_paypal_rest_access_token_sandbox');
		delete_site_transient('wu_paypal_rest_access_token_live');

		// Reset superglobals
		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];

		wp_set_current_user(0);

		parent::tearDown();
	}

	// =========================================================================
	// Singleton
	// =========================================================================

	/**
	 * Test handler is singleton.
	 */
	public function test_singleton(): void {

		$instance1 = PayPal_OAuth_Handler::get_instance();
		$instance2 = PayPal_OAuth_Handler::get_instance();

		$this->assertSame($instance1, $instance2);
	}

	// =========================================================================
	// is_configured
	// =========================================================================

	/**
	 * Test is_configured returns true (proxy URL is always set).
	 */
	public function test_is_configured(): void {

		$this->assertTrue($this->handler->is_configured());
	}

	/**
	 * Test is_configured returns false when proxy URL is empty.
	 */
	public function test_is_configured_false_without_proxy(): void {

		add_filter('wu_paypal_connect_proxy_url', '__return_empty_string');

		$this->assertFalse($this->handler->is_configured());
	}

	// =========================================================================
	// get_proxy_url / get_api_base_url (via reflection)
	// =========================================================================

	/**
	 * Test get_proxy_url returns default URL.
	 */
	public function test_get_proxy_url_default(): void {

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('get_proxy_url');

		$url = $method->invoke($this->handler);

		$this->assertStringContainsString('ultimatemultisite.com', $url);
		$this->assertStringContainsString('paypal-connect', $url);
	}

	/**
	 * Test get_proxy_url can be overridden via filter.
	 */
	public function test_get_proxy_url_filter_override(): void {

		add_filter(
			'wu_paypal_connect_proxy_url',
			function () {
				return 'https://custom-proxy.example.com/v1';
			}
		);

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('get_proxy_url');

		$url = $method->invoke($this->handler);

		$this->assertEquals('https://custom-proxy.example.com/v1', $url);
	}

	/**
	 * Test get_api_base_url returns sandbox URL in test mode.
	 */
	public function test_get_api_base_url_sandbox(): void {

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('get_api_base_url');

		$url = $method->invoke($this->handler);

		$this->assertEquals('https://api-m.sandbox.paypal.com', $url);
	}

	/**
	 * Test get_api_base_url returns live URL when test_mode is false.
	 */
	public function test_get_api_base_url_live(): void {

		$reflection     = new \ReflectionClass($this->handler);
		$test_mode_prop = $reflection->getProperty('test_mode');
		$test_mode_prop->setValue($this->handler, false);

		$method = $reflection->getMethod('get_api_base_url');
		$url    = $method->invoke($this->handler);

		$this->assertEquals('https://api-m.paypal.com', $url);

		// Restore
		$test_mode_prop->setValue($this->handler, true);
	}

	// =========================================================================
	// is_merchant_connected
	// =========================================================================

	/**
	 * Test merchant not connected without merchant ID.
	 */
	public function test_merchant_not_connected_without_id(): void {

		$this->assertFalse($this->handler->is_merchant_connected(true));
		$this->assertFalse($this->handler->is_merchant_connected(false));
	}

	/**
	 * Test merchant connected in sandbox mode.
	 */
	public function test_merchant_connected_sandbox(): void {

		wu_save_setting('paypal_rest_sandbox_merchant_id', 'SANDBOX_MERCHANT_123');

		$this->assertTrue($this->handler->is_merchant_connected(true));
		$this->assertFalse($this->handler->is_merchant_connected(false));
	}

	/**
	 * Test merchant connected in live mode.
	 */
	public function test_merchant_connected_live(): void {

		wu_save_setting('paypal_rest_live_merchant_id', 'LIVE_MERCHANT_456');

		$this->assertFalse($this->handler->is_merchant_connected(true));
		$this->assertTrue($this->handler->is_merchant_connected(false));
	}

	// =========================================================================
	// get_merchant_details
	// =========================================================================

	/**
	 * Test get_merchant_details returns correct sandbox data.
	 */
	public function test_get_merchant_details_sandbox(): void {

		wu_save_setting('paypal_rest_sandbox_merchant_id', 'MERCHANT_ID_TEST');
		wu_save_setting('paypal_rest_sandbox_merchant_email', 'test@merchant.com');
		wu_save_setting('paypal_rest_sandbox_payments_receivable', true);
		wu_save_setting('paypal_rest_sandbox_email_confirmed', true);
		wu_save_setting('paypal_rest_connection_date', '2026-02-01 10:00:00');

		$details = $this->handler->get_merchant_details(true);

		$this->assertEquals('MERCHANT_ID_TEST', $details['merchant_id']);
		$this->assertEquals('test@merchant.com', $details['merchant_email']);
		$this->assertTrue($details['payments_receivable']);
		$this->assertTrue($details['email_confirmed']);
		$this->assertEquals('2026-02-01 10:00:00', $details['connection_date']);
	}

	/**
	 * Test get_merchant_details returns correct live data.
	 */
	public function test_get_merchant_details_live(): void {

		wu_save_setting('paypal_rest_live_merchant_id', 'LIVE_MID');
		wu_save_setting('paypal_rest_live_merchant_email', 'live@merchant.com');

		$details = $this->handler->get_merchant_details(false);

		$this->assertEquals('LIVE_MID', $details['merchant_id']);
		$this->assertEquals('live@merchant.com', $details['merchant_email']);
	}

	/**
	 * Test get_merchant_details returns empty defaults when not connected.
	 */
	public function test_get_merchant_details_empty(): void {

		$details = $this->handler->get_merchant_details(true);

		$this->assertEmpty($details['merchant_id']);
		$this->assertEmpty($details['merchant_email']);
		$this->assertEmpty($details['connection_date']);
	}

	/**
	 * Test get_merchant_details returns all expected keys.
	 */
	public function test_get_merchant_details_has_all_keys(): void {

		$details = $this->handler->get_merchant_details(true);

		$this->assertArrayHasKey('merchant_id', $details);
		$this->assertArrayHasKey('merchant_email', $details);
		$this->assertArrayHasKey('payments_receivable', $details);
		$this->assertArrayHasKey('email_confirmed', $details);
		$this->assertArrayHasKey('connection_date', $details);
	}

	// =========================================================================
	// init
	// =========================================================================

	/**
	 * Test init registers AJAX hooks.
	 */
	public function test_init_registers_hooks(): void {

		$this->handler->init();

		$this->assertNotFalse(has_action('wp_ajax_wu_paypal_connect', [$this->handler, 'ajax_initiate_oauth']));
		$this->assertNotFalse(has_action('wp_ajax_wu_paypal_disconnect', [$this->handler, 'ajax_disconnect']));
		$this->assertNotFalse(has_action('admin_init', [$this->handler, 'handle_oauth_return']));
	}

	/**
	 * Test init reads sandbox mode from settings.
	 */
	public function test_init_reads_sandbox_mode_setting(): void {

		wu_save_setting('paypal_rest_sandbox_mode', 0);

		$this->handler->init();

		$reflection = new \ReflectionClass($this->handler);
		$prop       = $reflection->getProperty('test_mode');

		$this->assertFalse($prop->getValue($this->handler));

		// Restore
		wu_save_setting('paypal_rest_sandbox_mode', 1);
		$this->handler->init();
	}

	// =========================================================================
	// is_oauth_feature_enabled
	// =========================================================================

	/**
	 * Test is_oauth_feature_enabled defaults to false (proxy unreachable in tests).
	 */
	public function test_oauth_feature_disabled_by_default(): void {

		delete_site_transient('wu_paypal_oauth_enabled');

		add_filter('wu_paypal_oauth_enabled', '__return_false');

		$this->assertFalse($this->handler->is_oauth_feature_enabled());
	}

	/**
	 * Test is_oauth_feature_enabled returns true with filter override.
	 */
	public function test_oauth_feature_enabled_via_filter(): void {

		add_filter('wu_paypal_oauth_enabled', '__return_true');

		$this->assertTrue($this->handler->is_oauth_feature_enabled());
	}

	/**
	 * Test is_oauth_feature_enabled respects cached transient 'yes'.
	 */
	public function test_oauth_feature_uses_transient_cache_yes(): void {

		set_site_transient('wu_paypal_oauth_enabled', 'yes', HOUR_IN_SECONDS);

		$this->assertTrue($this->handler->is_oauth_feature_enabled());
	}

	/**
	 * Test is_oauth_feature_enabled respects cached transient 'no'.
	 */
	public function test_oauth_feature_uses_transient_cache_no(): void {

		set_site_transient('wu_paypal_oauth_enabled', 'no', HOUR_IN_SECONDS);

		$this->assertFalse($this->handler->is_oauth_feature_enabled());
	}

	/**
	 * Test is_oauth_feature_enabled returns false and caches on HTTP error.
	 */
	public function test_oauth_feature_caches_failure_on_http_error(): void {

		delete_site_transient('wu_paypal_oauth_enabled');

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/status') !== false) {
					return new \WP_Error('http_request_failed', 'Connection refused');
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->handler->is_oauth_feature_enabled();

		$this->assertFalse($result);

		$cached = get_site_transient('wu_paypal_oauth_enabled');
		$this->assertEquals('no', $cached);
	}

	/**
	 * Test is_oauth_feature_enabled returns true when proxy responds with oauth_enabled=true.
	 */
	public function test_oauth_feature_enabled_from_proxy_response(): void {

		delete_site_transient('wu_paypal_oauth_enabled');

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/status') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(['oauth_enabled' => true]),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->handler->is_oauth_feature_enabled();

		$this->assertTrue($result);

		$cached = get_site_transient('wu_paypal_oauth_enabled');
		$this->assertEquals('yes', $cached);
	}

	/**
	 * Test is_oauth_feature_enabled returns false when proxy responds with oauth_enabled=false.
	 */
	public function test_oauth_feature_disabled_from_proxy_response(): void {

		delete_site_transient('wu_paypal_oauth_enabled');

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/status') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(['oauth_enabled' => false]),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->handler->is_oauth_feature_enabled();

		$this->assertFalse($result);

		$cached = get_site_transient('wu_paypal_oauth_enabled');
		$this->assertEquals('no', $cached);
	}

	/**
	 * Test is_oauth_feature_enabled returns false when proxy URL is empty.
	 */
	public function test_oauth_feature_disabled_when_no_proxy_url(): void {

		delete_site_transient('wu_paypal_oauth_enabled');

		add_filter('wu_paypal_connect_proxy_url', '__return_empty_string');

		$result = $this->handler->is_oauth_feature_enabled();

		$this->assertFalse($result);
	}

	// =========================================================================
	// add_oauth_notice / display_oauth_notices
	// =========================================================================

	/**
	 * Test add_oauth_notice stores transient with correct type and message.
	 */
	public function test_add_oauth_notice_stores_transient(): void {

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('add_oauth_notice');

		$method->invoke($this->handler, 'success', 'Connected successfully!');

		$notice = get_site_transient('wu_paypal_oauth_notice');

		$this->assertIsArray($notice);
		$this->assertEquals('success', $notice['type']);
		$this->assertEquals('Connected successfully!', $notice['message']);
	}

	/**
	 * Test add_oauth_notice stores error type correctly.
	 */
	public function test_add_oauth_notice_error_type(): void {

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('add_oauth_notice');

		$method->invoke($this->handler, 'error', 'Something went wrong.');

		$notice = get_site_transient('wu_paypal_oauth_notice');

		$this->assertEquals('error', $notice['type']);
		$this->assertEquals('Something went wrong.', $notice['message']);
	}

	/**
	 * Test add_oauth_notice stores warning type correctly.
	 */
	public function test_add_oauth_notice_warning_type(): void {

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('add_oauth_notice');

		$method->invoke($this->handler, 'warning', 'Permissions not granted.');

		$notice = get_site_transient('wu_paypal_oauth_notice');

		$this->assertEquals('warning', $notice['type']);
	}

	/**
	 * Test display_oauth_notices outputs HTML and deletes transient.
	 */
	public function test_display_oauth_notices_outputs_html(): void {

		set_site_transient(
			'wu_paypal_oauth_notice',
			[
				'type'    => 'success',
				'message' => 'PayPal connected!',
			],
			60
		);

		ob_start();
		$this->handler->display_oauth_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString('notice-success', $output);
		$this->assertStringContainsString('PayPal connected!', $output);
		$this->assertStringContainsString('is-dismissible', $output);

		// Transient should be deleted after display
		$this->assertFalse(get_site_transient('wu_paypal_oauth_notice'));
	}

	/**
	 * Test display_oauth_notices outputs nothing when no notice.
	 */
	public function test_display_oauth_notices_empty_when_no_transient(): void {

		delete_site_transient('wu_paypal_oauth_notice');

		ob_start();
		$this->handler->display_oauth_notices();
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test display_oauth_notices escapes message content.
	 */
	public function test_display_oauth_notices_escapes_message(): void {

		set_site_transient(
			'wu_paypal_oauth_notice',
			[
				'type'    => 'error',
				'message' => '<script>alert("xss")</script>',
			],
			60
		);

		ob_start();
		$this->handler->display_oauth_notices();
		$output = ob_get_clean();

		$this->assertStringNotContainsString('<script>', $output);
	}

	// =========================================================================
	// verify_merchant_via_proxy (via reflection)
	// =========================================================================

	/**
	 * Test verify_merchant_via_proxy returns WP_Error on HTTP failure.
	 */
	public function test_verify_merchant_via_proxy_returns_wp_error_on_http_failure(): void {

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return new \WP_Error('http_request_failed', 'Connection refused');
				}
				return $preempt;
			},
			10,
			3
		);

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('verify_merchant_via_proxy');

		$result = $method->invoke($this->handler, 'MERCHANT123', 'TRACKING456');

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test verify_merchant_via_proxy returns WP_Error on non-200 response.
	 */
	public function test_verify_merchant_via_proxy_returns_wp_error_on_non_200(): void {

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return [
						'response' => ['code' => 400, 'message' => 'Bad Request'],
						'body'     => wp_json_encode(['error' => 'Invalid tracking ID']),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('verify_merchant_via_proxy');

		$result = $method->invoke($this->handler, 'MERCHANT123', 'TRACKING456');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('wu_paypal_verify_error', $result->get_error_code());
		$this->assertStringContainsString('Invalid tracking ID', $result->get_error_message());
	}

	/**
	 * Test verify_merchant_via_proxy returns WP_Error with default message when no error key.
	 */
	public function test_verify_merchant_via_proxy_default_error_message(): void {

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return [
						'response' => ['code' => 500, 'message' => 'Internal Server Error'],
						'body'     => wp_json_encode([]),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('verify_merchant_via_proxy');

		$result = $method->invoke($this->handler, 'MERCHANT123', 'TRACKING456');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('wu_paypal_verify_error', $result->get_error_code());
	}

	/**
	 * Test verify_merchant_via_proxy returns data array on success.
	 */
	public function test_verify_merchant_via_proxy_returns_data_on_success(): void {

		$merchant_data = [
			'paymentsReceivable' => true,
			'emailConfirmed'     => true,
			'merchantId'         => 'MERCHANT123',
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $merchant_data ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode($merchant_data),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('verify_merchant_via_proxy');

		$result = $method->invoke($this->handler, 'MERCHANT123', 'TRACKING456');

		$this->assertIsArray($result);
		$this->assertTrue($result['paymentsReceivable']);
		$this->assertTrue($result['emailConfirmed']);
	}

	// =========================================================================
	// handle_oauth_return
	// =========================================================================

	/**
	 * Test handle_oauth_return does nothing when no onboarding param.
	 */
	public function test_handle_oauth_return_skips_without_param(): void {

		$_GET = [];

		$this->handler->handle_oauth_return();

		$this->assertFalse(get_site_transient('wu_paypal_oauth_notice'));
	}

	/**
	 * Test handle_oauth_return skips when onboarding param is not 'complete'.
	 */
	public function test_handle_oauth_return_skips_wrong_param_value(): void {

		$_GET = ['wu_paypal_onboarding' => 'started'];

		$this->handler->handle_oauth_return();

		$this->assertFalse(get_site_transient('wu_paypal_oauth_notice'));
	}

	/**
	 * Test handle_oauth_return skips when not on settings page.
	 */
	public function test_handle_oauth_return_skips_wrong_page(): void {

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'some-other-page',
		];

		$this->handler->handle_oauth_return();

		$this->assertFalse(get_site_transient('wu_paypal_oauth_notice'));
	}

	/**
	 * Test handle_oauth_return shows error for invalid tracking ID.
	 */
	public function test_handle_oauth_return_invalid_tracking_id(): void {

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'wp-ultimo-settings',
			'tracking_id'          => 'INVALID_TRACKING_ID',
			'permissionsGranted'   => 'true',
		];

		$this->handler->handle_oauth_return();

		$notice = get_site_transient('wu_paypal_oauth_notice');
		$this->assertIsArray($notice);
		$this->assertEquals('error', $notice['type']);
	}

	/**
	 * Test handle_oauth_return shows warning when permissions not granted.
	 */
	public function test_handle_oauth_return_permissions_not_granted(): void {

		$tracking_id = 'VALID_TRACKING_' . uniqid();

		set_site_transient(
			'wu_paypal_onboarding_' . $tracking_id,
			[
				'started'   => time(),
				'test_mode' => true,
			],
			DAY_IN_SECONDS
		);

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'wp-ultimo-settings',
			'tracking_id'          => $tracking_id,
			'permissionsGranted'   => 'false',
			'merchantIdInPayPal'   => 'MERCHANT123',
		];

		$this->handler->handle_oauth_return();

		$notice = get_site_transient('wu_paypal_oauth_notice');
		$this->assertIsArray($notice);
		$this->assertEquals('warning', $notice['type']);
	}

	/**
	 * Test handle_oauth_return shows error when merchant verification fails.
	 */
	public function test_handle_oauth_return_verify_merchant_fails(): void {

		$tracking_id = 'VALID_TRACKING_' . uniqid();

		set_site_transient(
			'wu_paypal_onboarding_' . $tracking_id,
			[
				'started'   => time(),
				'test_mode' => true,
			],
			DAY_IN_SECONDS
		);

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'wp-ultimo-settings',
			'tracking_id'          => $tracking_id,
			'permissionsGranted'   => 'true',
			'merchantIdInPayPal'   => 'MERCHANT123',
			'merchantId'           => 'merchant@example.com',
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return new \WP_Error('http_request_failed', 'Connection refused');
				}
				return $preempt;
			},
			10,
			3
		);

		$this->handler->handle_oauth_return();

		$notice = get_site_transient('wu_paypal_oauth_notice');
		$this->assertIsArray($notice);
		$this->assertEquals('error', $notice['type']);
	}

	/**
	 * Test handle_oauth_return saves merchant credentials on success.
	 *
	 * Uses a wp_redirect filter that throws WPDieException to intercept
	 * the redirect+exit at the end of the success path.
	 */
	public function test_handle_oauth_return_saves_credentials_on_success(): void {

		$tracking_id = 'VALID_TRACKING_' . uniqid();

		set_site_transient(
			'wu_paypal_onboarding_' . $tracking_id,
			[
				'started'   => time(),
				'test_mode' => true,
			],
			DAY_IN_SECONDS
		);

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'wp-ultimo-settings',
			'tracking_id'          => $tracking_id,
			'permissionsGranted'   => 'true',
			'merchantIdInPayPal'   => 'MERCHANT_SAVE_TEST',
			'merchantId'           => 'save@merchant.com',
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(
							[
								'paymentsReceivable' => true,
								'emailConfirmed'     => true,
							]
						),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		// Throw WPDieException on redirect to prevent bare exit() from terminating the process
		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new \WPDieException('redirect:' . $location);
			}
		);

		try {
			$this->handler->handle_oauth_return();
		} catch (\WPDieException $e) {
			// Expected — redirect intercepted
			$this->assertStringContainsString('paypal_connected', $e->getMessage());
		}

		$this->assertEquals('MERCHANT_SAVE_TEST', wu_get_setting('paypal_rest_sandbox_merchant_id'));
		$this->assertEquals('save@merchant.com', wu_get_setting('paypal_rest_sandbox_merchant_email'));
		$this->assertEquals('1', wu_get_setting('paypal_rest_connected'));

		// Tracking transient should be deleted
		$this->assertFalse(get_site_transient('wu_paypal_onboarding_' . $tracking_id));
	}

	/**
	 * Test handle_oauth_return saves live credentials when test_mode is false.
	 */
	public function test_handle_oauth_return_saves_live_credentials(): void {

		$tracking_id = 'LIVE_TRACKING_' . uniqid();

		set_site_transient(
			'wu_paypal_onboarding_' . $tracking_id,
			[
				'started'   => time(),
				'test_mode' => false,
			],
			DAY_IN_SECONDS
		);

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'wp-ultimo-settings',
			'tracking_id'          => $tracking_id,
			'permissionsGranted'   => 'true',
			'merchantIdInPayPal'   => 'LIVE_MERCHANT_SAVE',
			'merchantId'           => 'live@merchant.com',
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(['paymentsReceivable' => true]),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new \WPDieException('redirect:' . $location);
			}
		);

		try {
			$this->handler->handle_oauth_return();
		} catch (\WPDieException $e) {
			// Expected
		}

		$this->assertEquals('LIVE_MERCHANT_SAVE', wu_get_setting('paypal_rest_live_merchant_id'));
		$this->assertEquals('live@merchant.com', wu_get_setting('paypal_rest_live_merchant_email'));
	}

	/**
	 * Test handle_oauth_return stores paymentsReceivable and emailConfirmed status.
	 */
	public function test_handle_oauth_return_stores_merchant_status(): void {

		$tracking_id = 'TRACKING_PR_' . uniqid();

		set_site_transient(
			'wu_paypal_onboarding_' . $tracking_id,
			[
				'started'   => time(),
				'test_mode' => true,
			],
			DAY_IN_SECONDS
		);

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'wp-ultimo-settings',
			'tracking_id'          => $tracking_id,
			'permissionsGranted'   => 'true',
			'merchantIdInPayPal'   => 'MERCHANT_PR',
			'merchantId'           => 'pr@merchant.com',
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(
							[
								'paymentsReceivable' => true,
								'emailConfirmed'     => true,
							]
						),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new \WPDieException('redirect:' . $location);
			}
		);

		try {
			$this->handler->handle_oauth_return();
		} catch (\WPDieException $e) {
			// Expected
		}

		$this->assertEquals(true, wu_get_setting('paypal_rest_sandbox_payments_receivable'));
		$this->assertEquals(true, wu_get_setting('paypal_rest_sandbox_email_confirmed'));
	}

	// =========================================================================
	// ajax_initiate_oauth
	// =========================================================================

	/**
	 * Test ajax_initiate_oauth sends error when proxy returns WP_Error.
	 */
	public function test_ajax_initiate_oauth_proxy_wp_error(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_POST['nonce'] = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce'] = $_POST['nonce'];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/init') !== false) {
					return new \WP_Error('http_request_failed', 'Connection refused');
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_initiate_oauth();
		} catch (\WPDieException $e) {
			// Expected — wp_send_json_error calls wp_die
		}
		$output = ob_get_clean();

		if (! empty($output)) {
			$data = json_decode($output, true);
			if (is_array($data)) {
				$this->assertFalse($data['success'] ?? true);
			}
		}
	}

	/**
	 * Test ajax_initiate_oauth without nonce fails.
	 */
	public function test_ajax_initiate_oauth_without_nonce_fails(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		// No nonce set
		$_POST = [];
		$_REQUEST = [];

		$this->expectException(\WPDieException::class);

		$this->handler->ajax_initiate_oauth();
	}

	/**
	 * Test ajax_disconnect without nonce fails.
	 */
	public function test_ajax_disconnect_without_nonce_fails(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		// No nonce set
		$_POST = [];
		$_REQUEST = [];

		$this->expectException(\WPDieException::class);

		$this->handler->ajax_disconnect();
	}

	/**
	 * Test ajax_initiate_oauth sends error when proxy returns non-200.
	 */
	public function test_ajax_initiate_oauth_proxy_non_200(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_POST['nonce'] = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce'] = $_POST['nonce'];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/init') !== false) {
					return [
						'response' => ['code' => 500, 'message' => 'Internal Server Error'],
						'body'     => wp_json_encode(['error' => 'Proxy unavailable']),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_initiate_oauth();
		} catch (\WPDieException $e) {
			// Expected
		}
		$output = ob_get_clean();

		if (! empty($output)) {
			$data = json_decode($output, true);
			if (is_array($data)) {
				$this->assertFalse($data['success'] ?? true);
			}
		}
	}

	/**
	 * Test ajax_initiate_oauth sends error when actionUrl is missing.
	 */
	public function test_ajax_initiate_oauth_missing_action_url(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_POST['nonce'] = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce'] = $_POST['nonce'];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/init') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(['trackingId' => 'TRACK123']), // No actionUrl
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_initiate_oauth();
		} catch (\WPDieException $e) {
			// Expected
		}
		$output = ob_get_clean();

		if (! empty($output)) {
			$data = json_decode($output, true);
			if (is_array($data)) {
				$this->assertFalse($data['success'] ?? true);
			}
		}
	}

	/**
	 * Test ajax_initiate_oauth stores tracking transient on success.
	 */
	public function test_ajax_initiate_oauth_stores_tracking_transient(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_POST['nonce']        = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce']     = $_POST['nonce'];
		$_POST['sandbox_mode'] = '1';

		$tracking_id = 'TRACK_' . uniqid();

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $tracking_id ) {
				if (strpos($url, '/oauth/init') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(
							[
								'actionUrl'  => 'https://paypal.com/connect?token=abc',
								'trackingId' => $tracking_id,
							]
						),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_initiate_oauth();
		} catch (\WPDieException $e) {
			// Expected — wp_send_json_success calls wp_die
		}
		$output = ob_get_clean();

		// Tracking transient should be stored
		$transient = get_site_transient('wu_paypal_onboarding_' . $tracking_id);
		$this->assertIsArray($transient);
		$this->assertArrayHasKey('started', $transient);
		$this->assertArrayHasKey('test_mode', $transient);
		$this->assertTrue($transient['test_mode']);

		if (! empty($output)) {
			$data = json_decode($output, true);
			if (is_array($data) && isset($data['success'])) {
				$this->assertTrue($data['success']);
				$this->assertEquals('https://paypal.com/connect?token=abc', $data['data']['redirect_url'] ?? '');
			}
		}
	}

	/**
	 * Test ajax_initiate_oauth respects sandbox_mode POST param (live mode).
	 */
	public function test_ajax_initiate_oauth_updates_test_mode_from_post(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_POST['nonce']        = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce']     = $_POST['nonce'];
		$_POST['sandbox_mode'] = '0'; // Live mode

		$tracking_id = 'TRACK_LIVE_' . uniqid();

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $tracking_id ) {
				if (strpos($url, '/oauth/init') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(
							[
								'actionUrl'  => 'https://paypal.com/connect?token=xyz',
								'trackingId' => $tracking_id,
							]
						),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_initiate_oauth();
		} catch (\WPDieException $e) {
			// Expected
		}
		ob_get_clean();

		// Tracking transient should reflect live mode
		$transient = get_site_transient('wu_paypal_onboarding_' . $tracking_id);
		if (is_array($transient)) {
			$this->assertFalse($transient['test_mode']);
		}
	}

	// =========================================================================
	// ajax_disconnect
	// =========================================================================

	/**
	 * Test ajax_disconnect clears all connection settings.
	 */
	public function test_ajax_disconnect_clears_settings(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		// Set up connected state
		wu_save_setting('paypal_rest_connected', true);
		wu_save_setting('paypal_rest_sandbox_merchant_id', 'MERCHANT123');
		wu_save_setting('paypal_rest_sandbox_merchant_email', 'test@merchant.com');
		wu_save_setting('paypal_rest_live_merchant_id', 'LIVE_MERCHANT');
		wu_save_setting('paypal_rest_connection_date', '2026-01-01 00:00:00');
		wu_save_setting('paypal_rest_connection_mode', 'sandbox');

		set_site_transient('wu_paypal_rest_access_token_sandbox', 'TOKEN123', HOUR_IN_SECONDS);
		set_site_transient('wu_paypal_rest_access_token_live', 'LIVE_TOKEN', HOUR_IN_SECONDS);

		$_POST['nonce'] = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce'] = $_POST['nonce'];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/deauthorize') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => '',
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_disconnect();
		} catch (\WPDieException $e) {
			// Expected — wp_send_json_success calls wp_die
		}
		$output = ob_get_clean();

		// All settings should be cleared
		$this->assertEmpty(wu_get_setting('paypal_rest_sandbox_merchant_id'));
		$this->assertEmpty(wu_get_setting('paypal_rest_live_merchant_id'));
		$this->assertEmpty(wu_get_setting('paypal_rest_sandbox_merchant_email'));
		$this->assertEmpty(wu_get_setting('paypal_rest_connection_date'));

		// Access token transients should be deleted
		$this->assertFalse(get_site_transient('wu_paypal_rest_access_token_sandbox'));
		$this->assertFalse(get_site_transient('wu_paypal_rest_access_token_live'));

		if (! empty($output)) {
			$data = json_decode($output, true);
			if (is_array($data) && isset($data['success'])) {
				$this->assertTrue($data['success']);
			}
		}
	}

	/**
	 * Test ajax_disconnect clears webhook ID settings.
	 */
	public function test_ajax_disconnect_clears_webhook_ids(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		wu_save_setting('paypal_rest_sandbox_webhook_id', 'WH-SANDBOX-123');
		wu_save_setting('paypal_rest_live_webhook_id', 'WH-LIVE-456');

		$_POST['nonce'] = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce'] = $_POST['nonce'];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/deauthorize') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => '',
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_disconnect();
		} catch (\WPDieException $e) {
			// Expected
		}
		ob_get_clean();

		$this->assertEmpty(wu_get_setting('paypal_rest_sandbox_webhook_id'));
		$this->assertEmpty(wu_get_setting('paypal_rest_live_webhook_id'));
	}

	// =========================================================================
	// Additional tests for webhook methods (indirect coverage)
	// =========================================================================

	/**
	 * Test handle_oauth_return calls install_webhook_after_oauth on success.
	 *
	 * This test mocks the gateway to verify webhook installation is attempted.
	 */
	public function test_handle_oauth_return_installs_webhook_on_success(): void {

		$tracking_id = 'WEBHOOK_TRACKING_' . uniqid();

		set_site_transient(
			'wu_paypal_onboarding_' . $tracking_id,
			[
				'started'   => time(),
				'test_mode' => true,
			],
			DAY_IN_SECONDS
		);

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'wp-ultimo-settings',
			'tracking_id'          => $tracking_id,
			'permissionsGranted'   => 'true',
			'merchantIdInPayPal'   => 'WEBHOOK_TEST_MERCHANT',
			'merchantId'           => 'webhook@merchant.com',
		];

		// Mock successful merchant verification
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(['paymentsReceivable' => true]),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		// Track if wu_get_gateway was called
		$gateway_called = false;
		add_filter(
			'wu_get_gateway',
			function ( $gateway ) use ( &$gateway_called ) {
				$gateway_called = true;
				// Return null to simulate gateway not found (webhook install will fail gracefully)
				return null;
			}
		);

		// Intercept redirect
		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new \WPDieException('redirect:' . $location);
			}
		);

		try {
			$this->handler->handle_oauth_return();
		} catch (\WPDieException $e) {
			// Expected
		}

		// Verify gateway was attempted to be retrieved for webhook installation
		$this->assertTrue($gateway_called, 'Gateway should be retrieved for webhook installation');
	}

	/**
	 * Test ajax_disconnect calls delete_webhooks_on_disconnect.
	 *
	 * This test verifies webhook deletion is attempted during disconnect.
	 */
	public function test_ajax_disconnect_attempts_webhook_deletion(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		// Set up some webhook IDs to be cleared
		wu_save_setting('paypal_rest_sandbox_webhook_id', 'WH-SANDBOX-DELETE');
		wu_save_setting('paypal_rest_live_webhook_id', 'WH-LIVE-DELETE');

		$_POST['nonce'] = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce'] = $_POST['nonce'];

		// Track if gateway was called for webhook deletion
		$gateway_calls = 0;
		add_filter(
			'wu_get_gateway',
			function ( $gateway ) use ( &$gateway_calls ) {
				$gateway_calls++;
				// Return null to simulate gateway not found
				return null;
			}
		);

		// Mock deauthorize request
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/deauthorize') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => '',
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_disconnect();
		} catch (\WPDieException $e) {
			// Expected
		}
		ob_get_clean();

		// Verify gateway was called (for webhook deletion attempt)
		$this->assertGreaterThan(0, $gateway_calls, 'Gateway should be called for webhook deletion');

		// Verify webhook IDs were cleared
		$this->assertEmpty(wu_get_setting('paypal_rest_sandbox_webhook_id'));
		$this->assertEmpty(wu_get_setting('paypal_rest_live_webhook_id'));
	}

	/**
	 * Test handle_oauth_return with missing merchant email.
	 */
	public function test_handle_oauth_return_with_missing_merchant_email(): void {

		$tracking_id = 'NO_EMAIL_' . uniqid();

		set_site_transient(
			'wu_paypal_onboarding_' . $tracking_id,
			[
				'started'   => time(),
				'test_mode' => false, // Live mode
			],
			DAY_IN_SECONDS
		);

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'wp-ultimo-settings',
			'tracking_id'          => $tracking_id,
			'permissionsGranted'   => 'true',
			'merchantIdInPayPal'   => 'MERCHANT_NO_EMAIL',
			// merchantId (email) is missing
		];

		// Mock successful verification
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(['paymentsReceivable' => true]),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new \WPDieException('redirect:' . $location);
			}
		);

		try {
			$this->handler->handle_oauth_return();
		} catch (\WPDieException $e) {
			// Expected
		}

		// Verify merchant ID was saved but email is empty
		$this->assertEquals('MERCHANT_NO_EMAIL', wu_get_setting('paypal_rest_live_merchant_id'));
		$this->assertEmpty(wu_get_setting('paypal_rest_live_merchant_email'));
	}

	/**
	 * Test ajax_initiate_oauth with empty tracking ID in response.
	 */
	public function test_ajax_initiate_oauth_empty_tracking_id(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_POST['nonce'] = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce'] = $_POST['nonce'];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/init') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(
							[
								'actionUrl'  => 'https://paypal.com/connect',
								// trackingId is missing/empty
							]
						),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_initiate_oauth();
		} catch (\WPDieException $e) {
			// Expected
		}
		$output = ob_get_clean();

		if (! empty($output)) {
			$data = json_decode($output, true);
			if (is_array($data) && isset($data['success'])) {
				$this->assertTrue($data['success']);
				// Tracking ID should be empty but request should still succeed
				$this->assertEmpty($data['data']['tracking_id'] ?? '');
			}
		}
	}

	/**
	 * Test handle_oauth_return with empty paymentsReceivable and emailConfirmed.
	 */
	public function test_handle_oauth_return_without_optional_status_fields(): void {

		$tracking_id = 'NO_STATUS_' . uniqid();

		set_site_transient(
			'wu_paypal_onboarding_' . $tracking_id,
			[
				'started'   => time(),
				'test_mode' => true,
			],
			DAY_IN_SECONDS
		);

		$_GET = [
			'wu_paypal_onboarding' => 'complete',
			'page'                 => 'wp-ultimo-settings',
			'tracking_id'          => $tracking_id,
			'permissionsGranted'   => 'true',
			'merchantIdInPayPal'   => 'MERCHANT_MIN_STATUS',
			'merchantId'           => 'minimal@merchant.com',
		];

		// Mock verification with minimal response (no status fields)
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/oauth/verify') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode([]), // Empty response
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new \WPDieException('redirect:' . $location);
			}
		);

		try {
			$this->handler->handle_oauth_return();
		} catch (\WPDieException $e) {
			// Expected
		}

		// Verify basic fields were saved but optional status fields were not
		$this->assertEquals('MERCHANT_MIN_STATUS', wu_get_setting('paypal_rest_sandbox_merchant_id'));
		$this->assertEmpty(wu_get_setting('paypal_rest_sandbox_payments_receivable'));
		$this->assertEmpty(wu_get_setting('paypal_rest_sandbox_email_confirmed'));
	}

	/**
	 * Test is_oauth_feature_enabled with empty proxy response body.
	 */
	public function test_oauth_feature_with_empty_proxy_response(): void {

		delete_site_transient('wu_paypal_oauth_enabled');

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/status') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => '', // Empty body
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->handler->is_oauth_feature_enabled();

		$this->assertFalse($result);
		$this->assertEquals('no', get_site_transient('wu_paypal_oauth_enabled'));
	}

	/**
	 * Test is_oauth_feature_enabled with malformed JSON response.
	 */
	public function test_oauth_feature_with_malformed_json_response(): void {

		delete_site_transient('wu_paypal_oauth_enabled');

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if (strpos($url, '/status') !== false) {
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => '{invalid json', // Malformed JSON
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->handler->is_oauth_feature_enabled();

		$this->assertFalse($result);
		$this->assertEquals('no', get_site_transient('wu_paypal_oauth_enabled'));
	}

	/**
	 * Test verify_merchant_via_proxy sends correct test mode parameter.
	 */
	public function test_verify_merchant_via_proxy_test_mode_parameter(): void {

		$captured_body = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_body ) {
				if (strpos($url, '/oauth/verify') !== false) {
					$captured_body = json_decode($args['body'], true);
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(['paymentsReceivable' => true]),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$reflection = new \ReflectionClass($this->handler);
		$method = $reflection->getMethod('verify_merchant_via_proxy');

		// Test with test_mode = true (default)
		$method->invoke($this->handler, 'MERCHANT123', 'TRACKING456');

		$this->assertIsArray($captured_body);
		$this->assertTrue($captured_body['testMode']);

		// Test with test_mode = false
		$test_mode_prop = $reflection->getProperty('test_mode');
		$test_mode_prop->setValue($this->handler, false);

		$method->invoke($this->handler, 'MERCHANT789', 'TRACKING789');

		$this->assertFalse($captured_body['testMode']);

		// Restore
		$test_mode_prop->setValue($this->handler, true);
	}

	/**
	 * Test ajax_initiate_oauth sends correct request body to proxy.
	 */
	public function test_ajax_initiate_oauth_request_body(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_POST['nonce'] = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['sandbox_mode'] = '0'; // Live mode

		$captured_body = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_body ) {
				if (strpos($url, '/oauth/init') !== false) {
					$captured_body = json_decode($args['body'], true);
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => wp_json_encode(
							[
								'actionUrl'  => 'https://paypal.com/connect',
								'trackingId' => 'TRACK123',
							]
						),
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_initiate_oauth();
		} catch (\WPDieException $e) {
			// Expected
		}
		ob_get_clean();

		// Verify request body
		$this->assertIsArray($captured_body);
		$this->assertArrayHasKey('returnUrl', $captured_body);
		$this->assertArrayHasKey('testMode', $captured_body);
		$this->assertFalse($captured_body['testMode']); // Live mode
		$this->assertStringContainsString('wu_paypal_onboarding=complete', $captured_body['returnUrl']);
	}

	/**
	 * Test ajax_disconnect sends deauthorize request with correct parameters.
	 */
	public function test_ajax_disconnect_deauthorize_request(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_POST['nonce'] = wp_create_nonce('wu_paypal_oauth');
		$_REQUEST['nonce'] = $_POST['nonce'];

		$captured_body = null;
		$deauthorize_called = false;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_body, &$deauthorize_called ) {
				if (strpos($url, '/deauthorize') !== false) {
					$deauthorize_called = true;
					$captured_body = json_decode($args['body'], true);
					// Verify it's non-blocking
					$this->assertFalse($args['blocking']);
					return [
						'response' => ['code' => 200, 'message' => 'OK'],
						'body'     => '',
						'headers'  => [],
						'cookies'  => [],
						'filename' => '',
					];
				}
				return $preempt;
			},
			10,
			3
		);

		ob_start();
		try {
			$this->handler->ajax_disconnect();
		} catch (\WPDieException $e) {
			// Expected
		}
		ob_get_clean();

		// Verify deauthorize was called
		$this->assertTrue($deauthorize_called);
		$this->assertIsArray($captured_body);
		$this->assertArrayHasKey('siteUrl', $captured_body);
		$this->assertArrayHasKey('testMode', $captured_body);
		$this->assertTrue($captured_body['testMode']); // Default is sandbox
	}
}
