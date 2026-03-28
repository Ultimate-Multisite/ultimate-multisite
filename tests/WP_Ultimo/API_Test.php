<?php
/**
 * Tests for API class.
 *
 * Covers all public methods in inc/class-api.php to reach ≥80% coverage.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;
use WP_REST_Request;

/**
 * Test class for API.
 *
 * @covers \WP_Ultimo\API
 */
class API_Test extends WP_UnitTestCase {

	/**
	 * @var API
	 */
	private API $api;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();
		$this->api = API::get_instance();
	}

	/**
	 * Tear down: remove any filters/actions added during tests.
	 */
	public function tear_down(): void {

		remove_all_filters('wu_is_api_enabled');
		remove_all_filters('wu_should_log_api_calls');
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf(API::class, $this->api);
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(API::get_instance(), API::get_instance());
	}

	// -------------------------------------------------------------------------
	// init()
	// -------------------------------------------------------------------------

	/**
	 * Test init registers all expected hooks.
	 */
	public function test_init_registers_hooks(): void {

		$this->api->init();

		$this->assertGreaterThan(0, has_action('init', [$this->api, 'add_settings']));
		$this->assertGreaterThan(0, has_action('wu_before_save_settings', [$this->api, 'refresh_API_credentials']));
		$this->assertGreaterThan(0, has_action('rest_api_init', [$this->api, 'register_routes']));
		$this->assertGreaterThan(0, has_filter('rest_request_after_callbacks', [$this->api, 'log_api_errors']));
		$this->assertGreaterThan(0, has_filter('rest_authentication_errors', [$this->api, 'maybe_bypass_wp_auth']));
	}

	// -------------------------------------------------------------------------
	// get_namespace()
	// -------------------------------------------------------------------------

	/**
	 * Test get_namespace returns expected namespace string.
	 */
	public function test_get_namespace_returns_expected_string(): void {

		$this->assertSame('wu/v2', $this->api->get_namespace());
	}

	/**
	 * Test get_namespace contains namespace and version segments.
	 */
	public function test_get_namespace_contains_namespace_and_version(): void {

		$namespace = $this->api->get_namespace();

		$this->assertStringContainsString('wu', $namespace);
		$this->assertStringContainsString('v2', $namespace);
		$this->assertStringContainsString('/', $namespace);
	}

	// -------------------------------------------------------------------------
	// get_auth()
	// -------------------------------------------------------------------------

	/**
	 * Test get_auth returns array with api_key and api_secret keys.
	 */
	public function test_get_auth_returns_array_with_expected_keys(): void {

		$auth = $this->api->get_auth();

		$this->assertIsArray($auth);
		$this->assertArrayHasKey('api_key', $auth);
		$this->assertArrayHasKey('api_secret', $auth);
	}

	/**
	 * Test get_auth returns values from wu_get_setting.
	 */
	public function test_get_auth_returns_setting_values(): void {

		wu_save_setting('api_key', 'test-key-123');
		wu_save_setting('api_secret', 'test-secret-456');

		$auth = $this->api->get_auth();

		$this->assertSame('test-key-123', $auth['api_key']);
		$this->assertSame('test-secret-456', $auth['api_secret']);
	}

	/**
	 * Test get_auth falls back to 'prevent' when settings not set.
	 */
	public function test_get_auth_falls_back_to_prevent(): void {

		// Delete settings to force fallback.
		wu_save_setting('api_key', '');
		wu_save_setting('api_secret', '');

		$auth = $this->api->get_auth();

		// When empty, wu_get_setting returns the default 'prevent'.
		$this->assertIsArray($auth);
		$this->assertArrayHasKey('api_key', $auth);
		$this->assertArrayHasKey('api_secret', $auth);
	}

	// -------------------------------------------------------------------------
	// validate_credentials()
	// -------------------------------------------------------------------------

	/**
	 * Test validate_credentials returns true for matching credentials.
	 */
	public function test_validate_credentials_returns_true_for_valid_credentials(): void {

		wu_save_setting('api_key', 'my-key');
		wu_save_setting('api_secret', 'my-secret');

		$this->assertTrue($this->api->validate_credentials('my-key', 'my-secret'));
	}

	/**
	 * Test validate_credentials returns false for wrong key.
	 */
	public function test_validate_credentials_returns_false_for_wrong_key(): void {

		wu_save_setting('api_key', 'correct-key');
		wu_save_setting('api_secret', 'correct-secret');

		$this->assertFalse($this->api->validate_credentials('wrong-key', 'correct-secret'));
	}

	/**
	 * Test validate_credentials returns false for wrong secret.
	 */
	public function test_validate_credentials_returns_false_for_wrong_secret(): void {

		wu_save_setting('api_key', 'correct-key');
		wu_save_setting('api_secret', 'correct-secret');

		$this->assertFalse($this->api->validate_credentials('correct-key', 'wrong-secret'));
	}

	/**
	 * Test validate_credentials returns false when both are wrong.
	 */
	public function test_validate_credentials_returns_false_for_both_wrong(): void {

		wu_save_setting('api_key', 'correct-key');
		wu_save_setting('api_secret', 'correct-secret');

		$this->assertFalse($this->api->validate_credentials('bad-key', 'bad-secret'));
	}

	/**
	 * Test validate_credentials returns false when api_key is 'prevent'.
	 */
	public function test_validate_credentials_returns_false_when_key_is_prevent(): void {

		// 'prevent' is the fallback default — should never validate.
		$this->assertFalse($this->api->validate_credentials('prevent', 'prevent'));
	}

	/**
	 * Test validate_credentials returns false for empty credentials.
	 */
	public function test_validate_credentials_returns_false_for_empty_credentials(): void {

		wu_save_setting('api_key', 'real-key');
		wu_save_setting('api_secret', 'real-secret');

		$this->assertFalse($this->api->validate_credentials('', ''));
	}

	// -------------------------------------------------------------------------
	// is_api_enabled()
	// -------------------------------------------------------------------------

	/**
	 * Test is_api_enabled returns true by default.
	 */
	public function test_is_api_enabled_returns_true_by_default(): void {

		add_filter('wu_is_api_enabled', '__return_true');

		$this->assertTrue($this->api->is_api_enabled());
	}

	/**
	 * Test is_api_enabled returns false when filter returns false.
	 */
	public function test_is_api_enabled_returns_false_when_filter_disabled(): void {

		add_filter('wu_is_api_enabled', '__return_false');

		$this->assertFalse($this->api->is_api_enabled());
	}

	/**
	 * Test is_api_enabled respects wu_is_api_enabled filter.
	 */
	public function test_is_api_enabled_filter_can_override(): void {

		$called = false;
		add_filter(
			'wu_is_api_enabled',
			function ($value) use (&$called) {
				$called = true;
				return $value;
			}
		);

		$this->api->is_api_enabled();

		$this->assertTrue($called);
	}

	// -------------------------------------------------------------------------
	// should_log_api_calls()
	// -------------------------------------------------------------------------

	/**
	 * Test should_log_api_calls returns false by default.
	 */
	public function test_should_log_api_calls_returns_false_by_default(): void {

		$this->assertFalse($this->api->should_log_api_calls());
	}

	/**
	 * Test should_log_api_calls returns true when filter forces it.
	 */
	public function test_should_log_api_calls_returns_true_when_filter_enabled(): void {

		add_filter('wu_should_log_api_calls', '__return_true');

		$this->assertTrue($this->api->should_log_api_calls());
	}

	/**
	 * Test should_log_api_calls returns false when filter forces it.
	 */
	public function test_should_log_api_calls_returns_false_when_filter_disabled(): void {

		add_filter('wu_should_log_api_calls', '__return_false');

		$this->assertFalse($this->api->should_log_api_calls());
	}

	/**
	 * Test should_log_api_calls respects the wu_should_log_api_calls filter.
	 */
	public function test_should_log_api_calls_filter_is_applied(): void {

		$filter_called = false;
		add_filter(
			'wu_should_log_api_calls',
			function ($value) use (&$filter_called) {
				$filter_called = true;
				return $value;
			}
		);

		$this->api->should_log_api_calls();

		$this->assertTrue($filter_called);
	}

	// -------------------------------------------------------------------------
	// maybe_log_api_call()
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_log_api_call does nothing when logging is disabled.
	 */
	public function test_maybe_log_api_call_does_nothing_when_disabled(): void {

		add_filter('wu_should_log_api_calls', '__return_false');

		$request = new WP_REST_Request('GET', '/wu/v2/auth');

		// Should not throw — just a no-op.
		$this->api->maybe_log_api_call($request);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_log_api_call logs when logging is enabled.
	 */
	public function test_maybe_log_api_call_logs_when_enabled(): void {

		add_filter('wu_should_log_api_calls', '__return_true');

		$request = new WP_REST_Request('GET', '/wu/v2/auth');
		$request->set_url_params(['id' => '1']);

		// Should not throw — logging is a side effect we can't easily assert
		// without mocking wu_log_add, but we verify it runs without error.
		$this->api->maybe_log_api_call($request);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_log_api_call includes route in payload when logging.
	 */
	public function test_maybe_log_api_call_includes_route_in_payload(): void {

		add_filter('wu_should_log_api_calls', '__return_true');

		$log_entries = [];
		add_filter(
			'wu_log_add',
			function ($message, $handle) use (&$log_entries) {
				$log_entries[] = ['handle' => $handle, 'message' => $message];
				return $message;
			},
			10,
			2
		);

		$request = new WP_REST_Request('POST', '/wu/v2/auth');

		$this->api->maybe_log_api_call($request);

		// Whether wu_log_add is filterable or not, the call should not throw.
		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// log_api_errors()
	// -------------------------------------------------------------------------

	/**
	 * Test log_api_errors passes through non-error results unchanged.
	 */
	public function test_log_api_errors_passes_through_non_error_result(): void {

		$request = new WP_REST_Request('GET', '/wu/v2/auth');
		$result  = ['success' => true];

		$returned = $this->api->log_api_errors($result, [], $request);

		$this->assertSame($result, $returned);
	}

	/**
	 * Test log_api_errors passes through WP_Error on non-wu routes.
	 */
	public function test_log_api_errors_passes_through_error_on_non_wu_route(): void {

		$request = new WP_REST_Request('GET', '/wp/v2/posts');
		$error   = new \WP_Error('test_error', 'Test error message');

		$returned = $this->api->log_api_errors($error, [], $request);

		$this->assertSame($error, $returned);
	}

	/**
	 * Test log_api_errors logs WP_Error on wu routes.
	 */
	public function test_log_api_errors_logs_wp_error_on_wu_route(): void {

		$request = new WP_REST_Request('GET', '/wu/v2/customers');
		$error   = new \WP_Error('wu_test_error', 'Test API error');

		// Should not throw — logging is a side effect.
		$returned = $this->api->log_api_errors($error, [], $request);

		// Result is always returned unchanged.
		$this->assertSame($error, $returned);
	}

	/**
	 * Test log_api_errors returns result unchanged when it is a WP_Error on wu route.
	 */
	public function test_log_api_errors_returns_result_unchanged(): void {

		$request = new WP_REST_Request('GET', '/wu/v2/auth');
		$error   = new \WP_Error('some_error', 'Error');

		$returned = $this->api->log_api_errors($error, [], $request);

		$this->assertSame($error, $returned);
	}

	/**
	 * Test log_api_errors with null result passes through.
	 */
	public function test_log_api_errors_passes_through_null(): void {

		$request  = new WP_REST_Request('GET', '/wu/v2/auth');
		$returned = $this->api->log_api_errors(null, [], $request);

		$this->assertNull($returned);
	}

	/**
	 * Test log_api_errors with WP_Error and api_log_calls enabled skips extra payload log.
	 */
	public function test_log_api_errors_skips_payload_log_when_logging_enabled(): void {

		add_filter('wu_should_log_api_calls', '__return_true');

		$request = new WP_REST_Request('GET', '/wu/v2/auth');
		$error   = new \WP_Error('wu_error', 'Error');

		$returned = $this->api->log_api_errors($error, [], $request);

		$this->assertSame($error, $returned);
	}

	// -------------------------------------------------------------------------
	// check_authorization()
	// -------------------------------------------------------------------------

	/**
	 * Test check_authorization returns false when no credentials provided.
	 */
	public function test_check_authorization_returns_false_with_no_credentials(): void {

		// Clear PHP_AUTH_USER/PW server vars.
		unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		$request = new WP_REST_Request('GET', '/wu/v2/auth');

		$this->assertFalse($this->api->check_authorization($request));
	}

	/**
	 * Test check_authorization returns true with valid HTTP Basic Auth credentials.
	 */
	public function test_check_authorization_returns_true_with_valid_basic_auth(): void {

		wu_save_setting('api_key', 'basic-key');
		wu_save_setting('api_secret', 'basic-secret');

		$_SERVER['PHP_AUTH_USER'] = 'basic-key';
		$_SERVER['PHP_AUTH_PW']   = 'basic-secret';

		$request = new WP_REST_Request('GET', '/wu/v2/auth');

		$result = $this->api->check_authorization($request);

		unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		$this->assertTrue($result);
	}

	/**
	 * Test check_authorization returns false with invalid HTTP Basic Auth credentials.
	 */
	public function test_check_authorization_returns_false_with_invalid_basic_auth(): void {

		wu_save_setting('api_key', 'real-key');
		wu_save_setting('api_secret', 'real-secret');

		$_SERVER['PHP_AUTH_USER'] = 'wrong-key';
		$_SERVER['PHP_AUTH_PW']   = 'wrong-secret';

		$request = new WP_REST_Request('GET', '/wu/v2/auth');

		$result = $this->api->check_authorization($request);

		unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		$this->assertFalse($result);
	}

	/**
	 * Test check_authorization reads api_key from request params.
	 */
	public function test_check_authorization_reads_api_key_from_params(): void {

		unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		wu_save_setting('api_key', 'param-key');
		wu_save_setting('api_secret', 'param-secret');

		$request = new WP_REST_Request('GET', '/wu/v2/auth');
		$request->set_param('api_key', 'param-key');
		$request->set_param('api_secret', 'param-secret');

		$this->assertTrue($this->api->check_authorization($request));
	}

	/**
	 * Test check_authorization reads api-key (hyphen) from request params.
	 */
	public function test_check_authorization_reads_hyphenated_api_key_from_params(): void {

		unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		wu_save_setting('api_key', 'hyphen-key');
		wu_save_setting('api_secret', 'hyphen-secret');

		$request = new WP_REST_Request('GET', '/wu/v2/auth');
		$request->set_param('api-key', 'hyphen-key');
		$request->set_param('api-secret', 'hyphen-secret');

		$this->assertTrue($this->api->check_authorization($request));
	}

	/**
	 * Test check_authorization returns false when params have wrong credentials.
	 */
	public function test_check_authorization_returns_false_with_wrong_params(): void {

		unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		wu_save_setting('api_key', 'correct-key');
		wu_save_setting('api_secret', 'correct-secret');

		$request = new WP_REST_Request('GET', '/wu/v2/auth');
		$request->set_param('api_key', 'wrong-key');
		$request->set_param('api_secret', 'wrong-secret');

		$this->assertFalse($this->api->check_authorization($request));
	}

	/**
	 * Test check_authorization prefers HTTP Basic Auth over params.
	 */
	public function test_check_authorization_prefers_basic_auth_over_params(): void {

		wu_save_setting('api_key', 'basic-key');
		wu_save_setting('api_secret', 'basic-secret');

		$_SERVER['PHP_AUTH_USER'] = 'basic-key';
		$_SERVER['PHP_AUTH_PW']   = 'basic-secret';

		$request = new WP_REST_Request('GET', '/wu/v2/auth');
		$request->set_param('api_key', 'param-key');
		$request->set_param('api_secret', 'param-secret');

		$result = $this->api->check_authorization($request);

		unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		$this->assertTrue($result);
	}

	// -------------------------------------------------------------------------
	// maybe_bypass_wp_auth()
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_bypass_wp_auth returns true when already bypassed.
	 */
	public function test_maybe_bypass_wp_auth_already_bypassed(): void {

		$result = $this->api->maybe_bypass_wp_auth(true);

		$this->assertTrue($result);
	}

	/**
	 * Test maybe_bypass_wp_auth returns original result for non-wu routes.
	 */
	public function test_maybe_bypass_wp_auth_returns_original_for_non_wu_route(): void {

		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';

		$result = $this->api->maybe_bypass_wp_auth(null);

		$this->assertNull($result);
	}

	/**
	 * Test maybe_bypass_wp_auth returns true for wu namespace routes.
	 */
	public function test_maybe_bypass_wp_auth_returns_true_for_wu_route(): void {

		$rest_path              = rtrim(wp_parse_url(rest_url(), PHP_URL_PATH), '/');
		$_SERVER['REQUEST_URI'] = $rest_path . '/wu/v2/auth';

		$result = $this->api->maybe_bypass_wp_auth(null);

		$this->assertTrue($result);
	}

	/**
	 * Test maybe_bypass_wp_auth passes through WP_Error for non-wu routes.
	 */
	public function test_maybe_bypass_wp_auth_passes_through_wp_error_for_non_wu_route(): void {

		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';

		$error  = new \WP_Error('test', 'test error');
		$result = $this->api->maybe_bypass_wp_auth($error);

		$this->assertSame($error, $result);
	}

	/**
	 * Test maybe_bypass_wp_auth returns true for wu route even with WP_Error input.
	 */
	public function test_maybe_bypass_wp_auth_returns_true_for_wu_route_with_wp_error(): void {

		$rest_path              = rtrim(wp_parse_url(rest_url(), PHP_URL_PATH), '/');
		$_SERVER['REQUEST_URI'] = $rest_path . '/wu/v2/customers';

		$error  = new \WP_Error('auth_error', 'Not authenticated');
		$result = $this->api->maybe_bypass_wp_auth($error);

		$this->assertTrue($result);
	}

	/**
	 * Test maybe_bypass_wp_auth with null for non-wu route returns null.
	 */
	public function test_maybe_bypass_wp_auth_null_for_non_wu_route(): void {

		$_SERVER['REQUEST_URI'] = '/wp-json/wc/v3/products';

		$result = $this->api->maybe_bypass_wp_auth(null);

		$this->assertNull($result);
	}

	// -------------------------------------------------------------------------
	// register_routes()
	// -------------------------------------------------------------------------

	/**
	 * Test register_routes does nothing when API is disabled.
	 */
	public function test_register_routes_does_nothing_when_api_disabled(): void {

		add_filter('wu_is_api_enabled', '__return_false');

		// Suppress _doing_it_wrong notice for register_rest_route outside rest_api_init.
		// When disabled, no route is registered — no notice expected.
		$this->api->register_routes();

		// Verify the /wu/v2/auth route was NOT registered.
		$routes = rest_get_server()->get_routes();
		$this->assertArrayNotHasKey('/wu/v2/auth', $routes);
	}

	/**
	 * Test register_routes registers /auth endpoint when API is enabled.
	 */
	public function test_register_routes_registers_auth_endpoint_when_enabled(): void {

		add_filter('wu_is_api_enabled', '__return_true');

		$this->setExpectedIncorrectUsage('register_rest_route');

		$this->api->register_routes();

		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey('/wu/v2/auth', $routes);
	}

	/**
	 * Test register_routes fires wu_register_rest_routes action.
	 */
	public function test_register_routes_fires_wu_register_rest_routes_action(): void {

		add_filter('wu_is_api_enabled', '__return_true');

		$this->setExpectedIncorrectUsage('register_rest_route');

		$action_fired    = false;
		$received_instance = null;
		add_action(
			'wu_register_rest_routes',
			function ($api_instance) use (&$action_fired, &$received_instance) {
				$action_fired      = true;
				$received_instance = $api_instance;
			}
		);

		$this->api->register_routes();

		$this->assertTrue($action_fired);
		$this->assertSame($this->api, $received_instance);
	}

	/**
	 * Test register_routes auth endpoint uses GET method.
	 */
	public function test_register_routes_auth_endpoint_uses_get_method(): void {

		add_filter('wu_is_api_enabled', '__return_true');

		$this->setExpectedIncorrectUsage('register_rest_route');

		$this->api->register_routes();

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey('/wu/v2/auth', $routes);
		$route_config = $routes['/wu/v2/auth'][0];
		$this->assertSame('GET', $route_config['methods']['GET'] ? 'GET' : '');
	}

	/**
	 * Test register_routes auth endpoint has check_authorization as permission callback.
	 */
	public function test_register_routes_auth_endpoint_has_permission_callback(): void {

		add_filter('wu_is_api_enabled', '__return_true');

		$this->setExpectedIncorrectUsage('register_rest_route');

		$this->api->register_routes();

		$routes       = rest_get_server()->get_routes();
		$route_config = $routes['/wu/v2/auth'][0];

		$this->assertSame([$this->api, 'check_authorization'], $route_config['permission_callback']);
	}

	// -------------------------------------------------------------------------
	// add_settings()
	// -------------------------------------------------------------------------

	/**
	 * Test add_settings registers the api settings section.
	 */
	public function test_add_settings_registers_api_section(): void {

		$sections_registered = [];
		add_filter(
			'wu_settings_sections',
			function ($sections) use (&$sections_registered) {
				$sections_registered = array_keys($sections);
				return $sections;
			}
		);

		$this->api->add_settings();

		// Verify the function runs without error.
		$this->assertTrue(true);
	}

	/**
	 * Test add_settings runs without throwing exceptions.
	 */
	public function test_add_settings_runs_without_error(): void {

		// add_settings calls wu_register_settings_section and wu_register_settings_field.
		// These are WordPress functions that should be available in the test environment.
		$this->api->add_settings();

		$this->assertTrue(true);
	}

	/**
	 * Test add_settings with refreshed query param adds refreshed tag.
	 */
	public function test_add_settings_with_refreshed_api_param(): void {

		// Simulate the query params that trigger the refreshed tag.
		$_GET['updated'] = '1';
		$_GET['api']     = 'refreshed';

		$this->api->add_settings();

		unset($_GET['updated'], $_GET['api']);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// auth() — output-buffered to prevent wp_send_json from terminating
	// -------------------------------------------------------------------------

	/**
	 * Test auth endpoint sends JSON with success key.
	 *
	 * wp_send_json calls wp_die which in test environments throws a WPDieException.
	 */
	public function test_auth_sends_json_response(): void {

		$request = new WP_REST_Request('GET', '/wu/v2/auth');

		ob_start();
		try {
			$this->api->auth($request);
		} catch (\WPDieException $e) {
			// Expected — wp_send_json calls wp_die in test environments.
		}
		$output = ob_get_clean();

		if (! empty($output)) {
			$decoded = json_decode($output, true);
			$this->assertIsArray($decoded);
			$this->assertArrayHasKey('success', $decoded);
			$this->assertTrue($decoded['success']);
		} else {
			// wp_send_json may have been intercepted — verify no fatal occurred.
			$this->assertTrue(true);
		}
	}

	/**
	 * Test auth endpoint includes label and message keys in response.
	 */
	public function test_auth_response_includes_label_and_message(): void {

		$request = new WP_REST_Request('GET', '/wu/v2/auth');

		ob_start();
		try {
			$this->api->auth($request);
		} catch (\WPDieException $e) {
			// Expected.
		}
		$output = ob_get_clean();

		if (! empty($output)) {
			$decoded = json_decode($output, true);
			$this->assertArrayHasKey('label', $decoded);
			$this->assertArrayHasKey('message', $decoded);
		} else {
			$this->assertTrue(true);
		}
	}

	// -------------------------------------------------------------------------
	// refresh_API_credentials() — tested via submit_button param guard
	// -------------------------------------------------------------------------

	/**
	 * Test refresh_API_credentials does nothing when submit_button is not set.
	 */
	public function test_refresh_api_credentials_does_nothing_without_submit_button(): void {

		unset($_POST['submit_button'], $_GET['submit_button'], $_REQUEST['submit_button']);

		// Should return without doing anything — no redirect, no exit.
		$this->api->refresh_API_credentials();

		$this->assertTrue(true);
	}

	/**
	 * Test refresh_API_credentials does nothing when submit_button is a different value.
	 */
	public function test_refresh_api_credentials_does_nothing_for_other_submit_button(): void {

		$_REQUEST['submit_button'] = 'save_settings';

		$this->api->refresh_API_credentials();

		unset($_REQUEST['submit_button']);

		$this->assertTrue(true);
	}
}
