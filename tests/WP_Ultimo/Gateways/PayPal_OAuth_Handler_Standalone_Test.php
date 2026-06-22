<?php
/**
 * Standalone tests for PayPal OAuth Handler to improve coverage.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @since 2.x.x
 */

namespace WP_Ultimo\Gateways;

use PHPUnit\Framework\TestCase;

/**
 * PayPal OAuth Handler Standalone Test class.
 *
 * Tests methods that require mocking WordPress functions.
 */
class PayPal_OAuth_Handler_Standalone_Test extends TestCase {

	/**
	 * Test handler instance.
	 *
	 * @var PayPal_OAuth_Handler
	 */
	protected $handler;

	/**
	 * Mock functions array.
	 *
	 * @var array
	 */
	protected static $mock_functions = [];

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset mock functions
		self::$mock_functions = [];

		// Create handler instance
		$this->handler = PayPal_OAuth_Handler::get_instance();
	}

	/**
	 * Check if a namespaced WordPress function mock is registered.
	 *
	 * @param string $function_name Mocked function name.
	 * @return bool
	 */
	public static function has_mock_function(string $function_name): bool {

		return isset(self::$mock_functions[$function_name]);
	}

	/**
	 * Call a registered namespaced WordPress function mock.
	 *
	 * @param string $function_name Mocked function name.
	 * @param mixed  ...$args      Mock function arguments.
	 * @return mixed
	 */
	public static function call_mock_function(string $function_name, ...$args) {

		return self::$mock_functions[$function_name](...$args);
	}

	/**
	 * Test install_webhook_after_oauth when gateway is not found.
	 */
	public function test_install_webhook_after_oauth_gateway_not_found(): void {

		// Mock wu_get_gateway to return null
		self::$mock_functions['wu_get_gateway'] = function ($gateway_id) {
			unset($gateway_id);

			return null;
		};

		// Mock wu_log_add to capture log calls
		$log_calls                          = [];
		self::$mock_functions['wu_log_add'] = function ($type, $message, $level = null) use (&$log_calls) {
			$log_calls[] = [
				'type'    => $type,
				'message' => $message,
				'level'   => $level,
			];
		};

		// Use reflection to call protected method
		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('install_webhook_after_oauth');
		$method->setAccessible(true);

		// Call the method
		$method->invoke($this->handler, 'sandbox');

		// Assert log was called with warning
		$this->assertCount(1, $log_calls);
		$this->assertEquals('paypal', $log_calls[0]['type']);
		$this->assertStringContainsString('Could not get PayPal REST gateway instance', $log_calls[0]['message']);
		$this->assertEquals(\Psr\Log\LogLevel::WARNING, $log_calls[0]['level']);
	}

	/**
	 * Test install_webhook_after_oauth when gateway returns WP_Error.
	 */
	public function test_install_webhook_after_oauth_gateway_returns_error(): void {

		// Create a mock gateway
		$mock_gateway = $this->getMockBuilder(PayPal_REST_Gateway::class)
			->disableOriginalConstructor()
			->getMock();

		// Configure mock to return WP_Error
		$mock_gateway->expects($this->once())
			->method('set_test_mode')
			->with(true);

		$mock_gateway->expects($this->once())
			->method('install_webhook')
			->willReturn(new \WP_Error('webhook_error', 'Failed to install webhook'));

		// Mock wu_get_gateway to return our mock
		self::$mock_functions['wu_get_gateway'] = function ($gateway_id) use ($mock_gateway) {
			unset($gateway_id);

			return $mock_gateway;
		};

		// Mock wu_log_add to capture log calls
		$log_calls                          = [];
		self::$mock_functions['wu_log_add'] = function ($type, $message, $level = null) use (&$log_calls) {
			$log_calls[] = [
				'type'    => $type,
				'message' => $message,
				'level'   => $level,
			];
		};

		// Use reflection to call protected method
		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('install_webhook_after_oauth');
		$method->setAccessible(true);

		// Call the method
		$method->invoke($this->handler, 'sandbox');

		// Assert error log was called
		$this->assertCount(1, $log_calls);
		$this->assertEquals('paypal', $log_calls[0]['type']);
		$this->assertStringContainsString('Failed to install webhook after OAuth', $log_calls[0]['message']);
		$this->assertStringContainsString('Failed to install webhook', $log_calls[0]['message']);
		$this->assertEquals(\Psr\Log\LogLevel::ERROR, $log_calls[0]['level']);
	}

	/**
	 * Test install_webhook_after_oauth success case.
	 */
	public function test_install_webhook_after_oauth_success(): void {

		// Create a mock gateway
		$mock_gateway = $this->getMockBuilder(PayPal_REST_Gateway::class)
			->disableOriginalConstructor()
			->getMock();

		// Configure mock to return success
		$mock_gateway->expects($this->once())
			->method('set_test_mode')
			->with(false); // 'live' mode

		$mock_gateway->expects($this->once())
			->method('install_webhook')
			->willReturn(true);

		// Mock wu_get_gateway to return our mock
		self::$mock_functions['wu_get_gateway'] = function ($gateway_id) use ($mock_gateway) {
			unset($gateway_id);

			return $mock_gateway;
		};

		// Mock wu_log_add to capture log calls
		$log_calls                          = [];
		self::$mock_functions['wu_log_add'] = function ($type, $message, $level = null) use (&$log_calls) {
			$log_calls[] = [
				'type'    => $type,
				'message' => $message,
				'level'   => $level,
			];
		};

		// Use reflection to call protected method
		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('install_webhook_after_oauth');
		$method->setAccessible(true);

		// Call the method with 'live' mode
		$method->invoke($this->handler, 'live');

		// Assert success log was called
		$this->assertCount(1, $log_calls);
		$this->assertEquals('paypal', $log_calls[0]['type']);
		$this->assertStringContainsString('Webhook installed successfully for live mode', $log_calls[0]['message']);
		$this->assertNull($log_calls[0]['level']); // Success logs don't have a level
	}

	/**
	 * Test install_webhook_after_oauth handles exception.
	 */
	public function test_install_webhook_after_oauth_handles_exception(): void {

		// Create a mock gateway that throws exception
		$mock_gateway = $this->getMockBuilder(PayPal_REST_Gateway::class)
			->disableOriginalConstructor()
			->getMock();

		$mock_gateway->expects($this->once())
			->method('set_test_mode')
			->willThrowException(new \Exception('Gateway initialization failed'));

		// Mock wu_get_gateway to return our mock
		self::$mock_functions['wu_get_gateway'] = function ($gateway_id) use ($mock_gateway) {
			unset($gateway_id);

			return $mock_gateway;
		};

		// Mock wu_log_add to capture log calls
		$log_calls                          = [];
		self::$mock_functions['wu_log_add'] = function ($type, $message, $level = null) use (&$log_calls) {
			$log_calls[] = [
				'type'    => $type,
				'message' => $message,
				'level'   => $level,
			];
		};

		// Use reflection to call protected method
		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('install_webhook_after_oauth');
		$method->setAccessible(true);

		// Call the method
		$method->invoke($this->handler, 'sandbox');

		// Assert exception log was called
		$this->assertCount(1, $log_calls);
		$this->assertEquals('paypal', $log_calls[0]['type']);
		$this->assertStringContainsString('Exception installing webhook after OAuth', $log_calls[0]['message']);
		$this->assertStringContainsString('Gateway initialization failed', $log_calls[0]['message']);
		$this->assertEquals(\Psr\Log\LogLevel::ERROR, $log_calls[0]['level']);
	}

	/**
	 * Test delete_webhooks_on_disconnect when gateway not found.
	 */
	public function test_delete_webhooks_on_disconnect_gateway_not_found(): void {

		// Mock wu_get_gateway to return null
		self::$mock_functions['wu_get_gateway'] = function ($gateway_id) {
			unset($gateway_id);

			return null;
		};

		// Mock wu_log_add to ensure it's not called
		$log_calls                          = [];
		self::$mock_functions['wu_log_add'] = function ($type, $message, $level = null) use (&$log_calls) {
			$log_calls[] = [
				'type'    => $type,
				'message' => $message,
				'level'   => $level,
			];
		};

		// Use reflection to call protected method
		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('delete_webhooks_on_disconnect');
		$method->setAccessible(true);

		// Call the method
		$method->invoke($this->handler);

		// Assert no logs were called (method returns early)
		$this->assertCount(0, $log_calls);
	}

	/**
	 * Test delete_webhooks_on_disconnect with errors.
	 */
	public function test_delete_webhooks_on_disconnect_with_errors(): void {

		// Create a mock gateway
		$mock_gateway = $this->getMockBuilder(PayPal_REST_Gateway::class)
			->disableOriginalConstructor()
			->getMock();

		// Configure mock to return failures for both sandbox and live.
		$set_test_mode_calls = [];
		$mock_gateway->expects($this->exactly(2))
			->method('set_test_mode')
			->willReturnCallback(function ($mode) use (&$set_test_mode_calls) {
				$set_test_mode_calls[] = $mode;
			});

		$mock_gateway->expects($this->exactly(2))
			->method('delete_webhook')
			->willReturn(false);

		// Mock wu_get_gateway to return our mock
		self::$mock_functions['wu_get_gateway'] = function ($gateway_id) use ($mock_gateway) {
			unset($gateway_id);

			return $mock_gateway;
		};

		// Mock wu_log_add to capture log calls
		$log_calls                          = [];
		self::$mock_functions['wu_log_add'] = function ($type, $message, $level = null) use (&$log_calls) {
			$log_calls[] = [
				'type'    => $type,
				'message' => $message,
				'level'   => $level,
			];
		};

		// Use reflection to call protected method
		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('delete_webhooks_on_disconnect');
		$method->setAccessible(true);

		// Call the method
		$method->invoke($this->handler);

		// Assert both error logs were called
		$this->assertCount(2, $log_calls);

		// Check sandbox error
		$this->assertEquals('paypal', $log_calls[0]['type']);
		$this->assertStringContainsString('Failed to delete sandbox webhook', $log_calls[0]['message']);
		$this->assertEquals(\Psr\Log\LogLevel::WARNING, $log_calls[0]['level']);

		// Check live error
		$this->assertEquals('paypal', $log_calls[1]['type']);
		$this->assertStringContainsString('Failed to delete live webhook', $log_calls[1]['message']);
		$this->assertEquals(\Psr\Log\LogLevel::WARNING, $log_calls[1]['level']);
	}

	/**
	 * Test delete_webhooks_on_disconnect success case.
	 */
	public function test_delete_webhooks_on_disconnect_success(): void {

		// Create a mock gateway
		$mock_gateway = $this->getMockBuilder(PayPal_REST_Gateway::class)
			->disableOriginalConstructor()
			->getMock();

		// Configure mock to return success for both
		$set_test_mode_calls = [];
		$mock_gateway->expects($this->exactly(2))
			->method('set_test_mode')
			->willReturnCallback(function ($mode) use (&$set_test_mode_calls) {
				$set_test_mode_calls[] = $mode;
			});

		$mock_gateway->expects($this->exactly(2))
			->method('delete_webhook')
			->willReturn(true);

		// Mock wu_get_gateway to return our mock
		self::$mock_functions['wu_get_gateway'] = function ($gateway_id) use ($mock_gateway) {
			unset($gateway_id);

			return $mock_gateway;
		};

		// Mock wu_log_add to capture log calls
		$log_calls                          = [];
		self::$mock_functions['wu_log_add'] = function ($type, $message, $level = null) use (&$log_calls) {
			$log_calls[] = [
				'type'    => $type,
				'message' => $message,
				'level'   => $level,
			];
		};

		// Use reflection to call protected method
		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('delete_webhooks_on_disconnect');
		$method->setAccessible(true);

		// Call the method
		$method->invoke($this->handler);

		// Assert both success logs were called
		$this->assertCount(2, $log_calls);

		// Check sandbox success
		$this->assertEquals('paypal', $log_calls[0]['type']);
		$this->assertEquals('Sandbox webhook deleted during disconnect', $log_calls[0]['message']);
		$this->assertNull($log_calls[0]['level']);

		// Check live success
		$this->assertEquals('paypal', $log_calls[1]['type']);
		$this->assertEquals('Live webhook deleted during disconnect', $log_calls[1]['message']);
		$this->assertNull($log_calls[1]['level']);
	}

	/**
	 * Test delete_webhooks_on_disconnect handles exception.
	 */
	public function test_delete_webhooks_on_disconnect_handles_exception(): void {

		// Create a mock gateway that throws exception
		$mock_gateway = $this->getMockBuilder(PayPal_REST_Gateway::class)
			->disableOriginalConstructor()
			->getMock();

		$mock_gateway->expects($this->once())
			->method('set_test_mode')
			->willThrowException(new \Exception('Gateway error'));

		// Mock wu_get_gateway to return our mock
		self::$mock_functions['wu_get_gateway'] = function ($gateway_id) use ($mock_gateway) {
			unset($gateway_id);

			return $mock_gateway;
		};

		// Mock wu_log_add to capture log calls
		$log_calls                          = [];
		self::$mock_functions['wu_log_add'] = function ($type, $message, $level = null) use (&$log_calls) {
			$log_calls[] = [
				'type'    => $type,
				'message' => $message,
				'level'   => $level,
			];
		};

		// Use reflection to call protected method
		$reflection = new \ReflectionClass($this->handler);
		$method     = $reflection->getMethod('delete_webhooks_on_disconnect');
		$method->setAccessible(true);

		// Call the method
		$method->invoke($this->handler);

		// Assert exception log was called
		$this->assertCount(1, $log_calls);
		$this->assertEquals('paypal', $log_calls[0]['type']);
		$this->assertStringContainsString('Exception deleting webhooks during disconnect', $log_calls[0]['message']);
		$this->assertStringContainsString('Gateway error', $log_calls[0]['message']);
		$this->assertEquals(\Psr\Log\LogLevel::WARNING, $log_calls[0]['level']);
	}

	/**
	 * Test is_oauth_feature_enabled honours the WU_PAYPAL_OAUTH_ENABLED constant.
	 *
	 * Since v2.6.0 constants.php ships with the constant defined as false. The
	 * handler must return that bool value verbatim — no proxy probe, no
	 * transient lookup. If the constant is undefined (legacy / partner-approved
	 * configuration) this test is skipped because the standalone test cannot
	 * un-define a constant once the plugin bootstrap has set it.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_oauth_feature_enabled_with_constant(): void {

		if (! defined('WU_PAYPAL_OAUTH_ENABLED')) {
			$this->markTestSkipped(
				'WU_PAYPAL_OAUTH_ENABLED is undefined in this configuration; the '
				. 'constant short-circuit is exercised by the main test suite.'
			);
		}

		$this->assertSame(
			(bool) WU_PAYPAL_OAUTH_ENABLED,
			$this->handler->is_oauth_feature_enabled(),
			'is_oauth_feature_enabled must return the constant value verbatim.'
		);
	}

	/**
	 * Test ajax_initiate_oauth without proper permissions.
	 */
	public function test_ajax_initiate_oauth_without_permissions(): void {

		// Mock check_ajax_referer to pass
		self::$mock_functions['check_ajax_referer'] = function ($action, $query_arg) {
			unset($action, $query_arg);

			return true;
		};

		// Mock current_user_can to return false
		self::$mock_functions['current_user_can'] = function ($capability) {
			unset($capability);

			return false;
		};

		// Mock wp_send_json_error to capture the response
		$json_error                                 = null;
		self::$mock_functions['wp_send_json_error'] = function ($data) use (&$json_error) {
			$json_error = $data;
			// Verify error message inside mock before execution stops
			\PHPUnit\Framework\Assert::assertIsArray($data);
			\PHPUnit\Framework\Assert::assertArrayHasKey('message', $data);
			\PHPUnit\Framework\Assert::assertStringContainsString('do not have permission', $data['message']);
			throw new \Exception('wp_send_json_error called');
		};

		// Expect exception from our mock (wp_send_json_error normally calls exit)
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('wp_send_json_error called');

		// Call the method
		$this->handler->ajax_initiate_oauth();
	}

	/**
	 * Test ajax_disconnect without proper permissions.
	 */
	public function test_ajax_disconnect_without_permissions(): void {

		// Mock check_ajax_referer to pass
		self::$mock_functions['check_ajax_referer'] = function ($action, $query_arg) {
			unset($action, $query_arg);

			return true;
		};

		// Mock current_user_can to return false
		self::$mock_functions['current_user_can'] = function ($capability) {
			unset($capability);

			return false;
		};

		// Mock wp_send_json_error to capture the response
		$json_error                                 = null;
		self::$mock_functions['wp_send_json_error'] = function ($data) use (&$json_error) {
			$json_error = $data;
			// Verify error message inside mock before execution stops
			\PHPUnit\Framework\Assert::assertIsArray($data);
			\PHPUnit\Framework\Assert::assertArrayHasKey('message', $data);
			\PHPUnit\Framework\Assert::assertStringContainsString('do not have permission', $data['message']);
			throw new \Exception('wp_send_json_error called');
		};

		// Expect exception from our mock (wp_send_json_error normally calls exit)
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('wp_send_json_error called');

		// Call the method
		$this->handler->ajax_disconnect();
	}

	/**
	 * Test standalone JSON error encoding remains available without WordPress helpers.
	 */
	public function test_json_error_encoder_matches_php_json_encoding(): void {

		$payload = [
			'success' => false,
			'data'    => [
				'message' => 'Standalone fallback',
			],
		];

		$this->assertSame(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- The standalone fallback intentionally verifies parity with PHP's native encoder.
			json_encode($payload),
			paypal_oauth_json_encode($payload)
		);
	}
}

// Mock WordPress functions.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Namespaced function mocks are intentional test doubles for this standalone test.
function wu_get_gateway($gateway_id) {
	if (PayPal_OAuth_Handler_Standalone_Test::has_mock_function('wu_get_gateway')) {
		return PayPal_OAuth_Handler_Standalone_Test::call_mock_function('wu_get_gateway', $gateway_id);
	}
	if (function_exists('\\wu_get_gateway')) {
		return \wu_get_gateway($gateway_id);
	}
	return null;
}

function wu_log_add($type, $message, $level = null) {
	if (PayPal_OAuth_Handler_Standalone_Test::has_mock_function('wu_log_add')) {
		return PayPal_OAuth_Handler_Standalone_Test::call_mock_function('wu_log_add', $type, $message, $level);
	}
	if (function_exists('\\wu_log_add')) {
		return \wu_log_add($type, $message, $level ?? \Psr\Log\LogLevel::INFO);
	}
}

function check_ajax_referer($action, $query_arg = false) {
	if (PayPal_OAuth_Handler_Standalone_Test::has_mock_function('check_ajax_referer')) {
		return PayPal_OAuth_Handler_Standalone_Test::call_mock_function('check_ajax_referer', $action, $query_arg);
	}
	if (function_exists('\\check_ajax_referer')) {
		return \check_ajax_referer($action, $query_arg);
	}
	return true;
}

function current_user_can($capability) {
	if (PayPal_OAuth_Handler_Standalone_Test::has_mock_function('current_user_can')) {
		return PayPal_OAuth_Handler_Standalone_Test::call_mock_function('current_user_can', $capability);
	}
	if (function_exists('\\current_user_can')) {
		return \current_user_can($capability);
	}
	return true;
}

function wp_send_json_error($data = null, $status_code = null) {
	if (PayPal_OAuth_Handler_Standalone_Test::has_mock_function('wp_send_json_error')) {
		return PayPal_OAuth_Handler_Standalone_Test::call_mock_function('wp_send_json_error', $data, $status_code);
	}
	if (function_exists('\\wp_send_json_error')) {
		return \wp_send_json_error($data, $status_code);
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fallback emits a JSON response body like wp_send_json_error().
	echo paypal_oauth_json_encode([
		'success' => false,
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded as part of the JSON response body above.
		'data'    => $data,
	]);
	exit;
}

/**
 * Encode standalone JSON responses without requiring WordPress helpers.
 *
 * @param mixed $data Data to encode.
 * @return string|false
 */
function paypal_oauth_json_encode($data) {
	$encoder = function_exists('\\wp_json_encode') ? '\\wp_json_encode' : 'json_encode';

	return $encoder($data);
}

// Mock WP_Error class if not available.
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Fallback test doubles are intentionally colocated with the standalone test.
if (! class_exists('\WP_Error')) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct($code = '', $message = '', $data = '') {
			unset($data);

			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

// Mock PayPal_REST_Gateway if not available.
if (! class_exists('\WP_Ultimo\Gateways\PayPal_REST_Gateway')) {
	class PayPal_REST_Gateway {
		public function set_test_mode($test_mode) {}
		public function install_webhook() {}
		public function delete_webhook() {}
	}
}
// phpcs:enable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:enable Universal.Files.SeparateFunctionsFromOO.Mixed
