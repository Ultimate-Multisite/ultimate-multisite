<?php
/**
 * SSO unit tests.
 *
 * @package WP_Ultimo\SSO
 */

namespace WP_Ultimo\SSO;

/**
 * Unit tests for SSO class.
 *
 * Covers utility methods, filter integrations, early return guards,
 * and the JSONP Content-Type header fix.
 */
class SSO_Test extends \WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		add_filter('wu_sso_enabled', '__return_true');

		add_filter(
			'wu_sso_salt',
			function () {
				return 'test-salt';
			}
		);
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		remove_all_filters('wu_sso_enabled');
		remove_all_filters('wu_sso_salt');
		remove_all_filters('wu_sso_get_strategy');
		remove_all_filters('wu_sso_get_url_path');
		remove_all_filters('mercator.sso.enabled');

		unset($_REQUEST['return_type']);
		unset($_REQUEST['broker']);
		unset($_REQUEST['sso_verify']);
		unset($_REQUEST['wu_sso_token']);
		unset($_REQUEST['return_url']);
		unset($_REQUEST['redirect_to']);
		unset($_COOKIE['wu_sso_denied']);
		unset($_COOKIE['wu_sso_redirect_attempts']);

		remove_all_filters('wu_sso_redirect_loop_threshold');
		remove_all_filters('wu_sso_redirect_loop_window');

		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// is_enabled
	// ------------------------------------------------------------------

	/**
	 * Test is_enabled returns true when the filter returns true.
	 */
	public function test_is_enabled_returns_true_when_filter_returns_true(): void {
		$sso = SSO::get_instance();

		$this->assertTrue($sso->is_enabled());
	}

	/**
	 * Test is_enabled returns false when the filter returns false.
	 */
	public function test_is_enabled_returns_false_when_filter_returns_false(): void {
		remove_all_filters('wu_sso_enabled');
		add_filter('wu_sso_enabled', '__return_false');

		$sso = SSO::get_instance();

		$this->assertFalse($sso->is_enabled());
	}

	// ------------------------------------------------------------------
	// force_secure_login_cookie
	// ------------------------------------------------------------------

	/**
	 * Test force_secure_login_cookie returns a boolean value.
	 */
	public function test_force_secure_login_cookie_returns_boolean(): void {
		$sso    = SSO::get_instance();
		$result = $sso->force_secure_login_cookie();

		$this->assertIsBool($result);
	}

	// ------------------------------------------------------------------
	// get_url_path
	// ------------------------------------------------------------------

	/**
	 * Test get_url_path defaults to 'sso'.
	 */
	public function test_get_url_path_defaults_to_sso(): void {
		$sso = SSO::get_instance();

		$this->assertSame('sso', $sso->get_url_path());
	}

	/**
	 * Test get_url_path appends the action suffix.
	 */
	public function test_get_url_path_appends_action(): void {
		$sso = SSO::get_instance();

		$this->assertSame('sso-grant', $sso->get_url_path('grant'));
		$this->assertSame('sso-login', $sso->get_url_path('login'));
	}

	/**
	 * Test get_url_path respects the wu_sso_get_url_path filter.
	 */
	public function test_get_url_path_respects_filter(): void {
		add_filter(
			'wu_sso_get_url_path',
			function () {
				return 'mysso';
			}
		);

		$sso = SSO::get_instance();

		$this->assertSame('mysso', $sso->get_url_path());
		$this->assertSame('mysso-grant', $sso->get_url_path('grant'));
	}

	/**
	 * Test nested return_url values are extracted from redirect_to.
	 */
	public function test_get_sso_return_url_extracts_nested_return_url_from_redirect_to(): void {
		$sso        = SSO::get_instance();
		$return_url = 'https://customer.example.com/wp/wp-admin/';

		$_REQUEST['redirect_to'] = add_query_arg(
			'return_url',
			$return_url,
			home_url('/wp-login.php')
		);

		$method = new \ReflectionMethod($sso, 'get_sso_return_url');
		$method->setAccessible(true);

		$this->assertSame($return_url, $method->invoke($sso));
	}

	/**
	 * Test nested return_url values are sanitized when extracted from redirect_to.
	 */
	public function test_get_sso_return_url_sanitizes_nested_return_url_from_redirect_to(): void {
		$sso = SSO::get_instance();

		$_REQUEST['redirect_to'] = add_query_arg(
			'return_url',
			'javascript:alert(1)',
			home_url('/wp-login.php')
		);

		$method = new \ReflectionMethod($sso, 'get_sso_return_url');
		$method->setAccessible(true);

		$this->assertSame('', $method->invoke($sso));
	}

	/**
	 * Test cookie-less SSO tokens are one-time tokens for the target audience host.
	 */
	public function test_cookie_less_sso_token_validates_once_for_target_audience(): void {
		$user_id = self::factory()->user->create();
		$sso     = SSO::get_instance();

		$generate = new \ReflectionMethod($sso, 'generate_sso_token');
		$generate->setAccessible(true);

		$validate = new \ReflectionMethod($sso, 'validate_sso_token');
		$validate->setAccessible(true);

		$token = $generate->invoke($sso, $user_id, home_url('/wp-admin/'));

		$this->assertSame(['user_id' => $user_id], $validate->invoke($sso, $token));

		$second_result = $validate->invoke($sso, $token);

		$this->assertWPError($second_result);
		$this->assertSame('invalid_token', $second_result->get_error_code());
	}

	/**
	 * Test cookie-less SSO tokens are rejected when replayed on the wrong host.
	 */
	public function test_cookie_less_sso_token_rejects_wrong_audience(): void {
		$user_id = self::factory()->user->create();
		$sso     = SSO::get_instance();

		$generate = new \ReflectionMethod($sso, 'generate_sso_token');
		$generate->setAccessible(true);

		$validate = new \ReflectionMethod($sso, 'validate_sso_token');
		$validate->setAccessible(true);

		$token  = $generate->invoke($sso, $user_id, 'https://customer.example.com/wp-admin/');
		$result = $validate->invoke($sso, $token);

		$this->assertWPError($result);
		$this->assertSame('invalid_audience', $result->get_error_code());
	}

	/**
	 * Test cross-domain redirect targets survive the token handoff.
	 */
	public function test_get_sso_redirect_to_preserves_cross_domain_redirect_to(): void {
		$sso         = SSO::get_instance();
		$return_url  = 'https://customer.example.com/';
		$redirect_to = 'https://customer.example.com/wp-admin/edit.php';

		$_REQUEST['redirect_to'] = $redirect_to;

		$method = new \ReflectionMethod($sso, 'get_sso_redirect_to');
		$method->setAccessible(true);

		$this->assertSame($redirect_to, $method->invoke($sso, $return_url));
	}

	/**
	 * Test cross-domain return URLs default to the customer admin target.
	 */
	public function test_get_sso_redirect_to_defaults_cross_domain_return_url_to_admin(): void {
		$sso        = SSO::get_instance();
		$return_url = 'https://customer.example.com/';

		$method = new \ReflectionMethod($sso, 'get_sso_redirect_to');
		$method->setAccessible(true);

		$this->assertSame(
			'https://customer.example.com/wp/wp-admin/',
			$method->invoke($sso, $return_url)
		);
	}

	/**
	 * Test repeated mapped-domain SSO redirects are capped to avoid loops.
	 */
	public function test_sso_redirect_loop_counter_skips_at_threshold(): void {
		$sso = SSO::get_instance();

		add_filter(
			'wu_sso_redirect_loop_threshold',
			function () {
				return 2;
			}
		);

		$method = new \ReflectionMethod($sso, 'should_skip_redirect_due_to_loop');
		$method->setAccessible(true);

		$this->assertFalse($method->invoke($sso));
		$this->assertSame(1, $this->get_sso_redirect_attempt_count());

		$this->assertTrue($method->invoke($sso));
		$this->assertSame(2, $this->get_sso_redirect_attempt_count());
	}

	/**
	 * Test the redirect-loop counter restarts after its window expires.
	 */
	public function test_sso_redirect_loop_counter_resets_after_window(): void {
		$sso = SSO::get_instance();

		add_filter(
			'wu_sso_redirect_loop_threshold',
			function () {
				return 2;
			}
		);

		add_filter(
			'wu_sso_redirect_loop_window',
			function () {
				return 60;
			}
		);

		$_COOKIE['wu_sso_redirect_attempts'] = '1:' . (time() - 120);

		$method = new \ReflectionMethod($sso, 'should_skip_redirect_due_to_loop');
		$method->setAccessible(true);

		$this->assertFalse($method->invoke($sso));
		$this->assertSame(1, $this->get_sso_redirect_attempt_count());
	}

	/**
	 * Get the redirect-attempt count from the test cookie.
	 *
	 * @return int
	 */
	private function get_sso_redirect_attempt_count(): int {

		$cookie = isset($_COOKIE['wu_sso_redirect_attempts']) ? sanitize_text_field(wp_unslash($_COOKIE['wu_sso_redirect_attempts'])) : '';

		return absint(strtok($cookie, ':'));
	}

	// ------------------------------------------------------------------
	// encode / decode
	// ------------------------------------------------------------------

	/**
	 * Test encode returns a non-numeric string.
	 */
	public function test_encode_returns_non_numeric_string(): void {
		$sso     = SSO::get_instance();
		$encoded = $sso->encode(42, $sso->salt());

		$this->assertIsString($encoded);
		$this->assertNotSame('42', $encoded);
	}

	/**
	 * Test decode returns the original value after encoding.
	 */
	public function test_decode_returns_original_value(): void {
		$sso     = SSO::get_instance();
		$salt    = $sso->salt();
		$encoded = $sso->encode(999, $salt);
		$decoded = $sso->decode($encoded, $salt);

		$this->assertSame(999, $decoded);
	}

	/**
	 * Test different salts produce different encoded values.
	 */
	public function test_different_salts_produce_different_encodings(): void {
		$sso = SSO::get_instance();

		$enc_a = $sso->encode(1, 'salt-a');
		$enc_b = $sso->encode(1, 'salt-b');

		$this->assertNotSame($enc_a, $enc_b);
	}

	// ------------------------------------------------------------------
	// get_strategy
	// ------------------------------------------------------------------

	/**
	 * Test get_strategy returns a valid strategy string.
	 */
	public function test_get_strategy_returns_string(): void {
		$sso = SSO::get_instance();

		$strategy = $sso->get_strategy();

		$this->assertContains($strategy, ['ajax', 'redirect']);
	}

	/**
	 * Test get_strategy respects the wu_sso_get_strategy filter.
	 */
	public function test_get_strategy_respects_filter(): void {
		add_filter(
			'wu_sso_get_strategy',
			function () {
				return 'ajax';
			}
		);

		$sso = SSO::get_instance();

		$this->assertSame('ajax', $sso->get_strategy());
	}

	// ------------------------------------------------------------------
	// get_return_type
	// ------------------------------------------------------------------

	/**
	 * Test get_return_type defaults to 'redirect' when no request param is set.
	 */
	public function test_get_return_type_defaults_to_redirect(): void {
		unset($_REQUEST['return_type']);

		$sso = SSO::get_instance();

		$this->assertSame('redirect', $sso->get_return_type());
	}

	/**
	 * Test get_return_type accepts 'jsonp' as a valid return type.
	 */
	public function test_get_return_type_accepts_jsonp(): void {
		$_REQUEST['return_type'] = 'jsonp';

		$sso = SSO::get_instance();

		$this->assertSame('jsonp', $sso->get_return_type());
	}

	/**
	 * Test get_return_type accepts 'json' as a valid return type.
	 */
	public function test_get_return_type_accepts_json(): void {
		$_REQUEST['return_type'] = 'json';

		$sso = SSO::get_instance();

		$this->assertSame('json', $sso->get_return_type());
	}

	/**
	 * Test get_return_type rejects invalid values and defaults to 'redirect'.
	 */
	public function test_get_return_type_rejects_invalid_and_defaults_to_redirect(): void {
		$_REQUEST['return_type'] = 'xml';

		$sso = SSO::get_instance();

		$this->assertSame('redirect', $sso->get_return_type());
	}

	// ------------------------------------------------------------------
	// add_sso_removable_query_args
	// ------------------------------------------------------------------

	/**
	 * Test add_sso_removable_query_args adds the SSO path to the list.
	 */
	public function test_add_sso_removable_query_args_adds_sso_path(): void {
		$sso  = SSO::get_instance();
		$args = $sso->add_sso_removable_query_args([]);

		$this->assertContains('sso', $args);
	}

	/**
	 * Test add_sso_removable_query_args preserves existing arguments.
	 */
	public function test_add_sso_removable_query_args_preserves_existing(): void {
		$sso  = SSO::get_instance();
		$args = $sso->add_sso_removable_query_args(['existing_arg']);

		$this->assertContains('existing_arg', $args);
		$this->assertContains('sso', $args);
	}

	// ------------------------------------------------------------------
	// target_user_id
	// ------------------------------------------------------------------

	/**
	 * Test set_target_user_id and get_target_user_id round-trip.
	 */
	public function test_set_and_get_target_user_id(): void {
		$sso = SSO::get_instance();

		$this->assertNull($sso->get_target_user_id());

		$sso->set_target_user_id(42);
		$this->assertSame(42, $sso->get_target_user_id());

		// Reset for other tests.
		$sso->set_target_user_id(null);
	}

	// ------------------------------------------------------------------
	// calculate_secret_from_date
	// ------------------------------------------------------------------

	/**
	 * Test calculate_secret_from_date produces consistent hashes.
	 */
	public function test_calculate_secret_produces_consistent_hash(): void {
		$sso = SSO::get_instance();

		$secret_a = $sso->calculate_secret_from_date('2024-06-15 12:30:00');
		$secret_b = $sso->calculate_secret_from_date('2024-06-15 12:30:00');

		$this->assertSame($secret_a, $secret_b);
	}

	/**
	 * Test calculate_secret_from_date differs for different dates.
	 */
	public function test_calculate_secret_differs_for_different_dates(): void {
		$sso = SSO::get_instance();

		$secret_a = $sso->calculate_secret_from_date('2024-01-01 00:00:00');
		$secret_b = $sso->calculate_secret_from_date('2024-12-31 23:59:59');

		$this->assertNotSame($secret_a, $secret_b);
	}

	/**
	 * Test calculate_secret_from_date throws on invalid date input.
	 */
	public function test_calculate_secret_throws_on_invalid_date(): void {
		$sso = SSO::get_instance();

		$this->expectException(\WP_Ultimo\SSO\Exception\SSO_Exception::class);

		$sso->calculate_secret_from_date('garbage');
	}

	// ------------------------------------------------------------------
	// with_sso (static)
	// ------------------------------------------------------------------

	/**
	 * Test with_sso adds the sso=login query parameter.
	 */
	public function test_with_sso_adds_sso_login_param(): void {
		$url    = 'https://example.com/checkout';
		$result = SSO::with_sso($url);

		$this->assertStringContainsString('sso=login', $result);
	}

	/**
	 * Test with_sso preserves existing query parameters.
	 */
	public function test_with_sso_preserves_existing_query_params(): void {
		$url    = 'https://example.com/checkout?plan=pro';
		$result = SSO::with_sso($url);

		$this->assertStringContainsString('plan=pro', $result);
		$this->assertStringContainsString('sso=login', $result);
	}

	/**
	 * Test with_sso returns the original URL when SSO is disabled.
	 */
	public function test_with_sso_returns_original_url_when_disabled(): void {
		remove_all_filters('wu_sso_enabled');
		add_filter('wu_sso_enabled', '__return_false');

		$url    = 'https://example.com/checkout';
		$result = SSO::with_sso($url);

		$this->assertSame($url, $result);
	}

	// ------------------------------------------------------------------
	// get_final_return_url
	// ------------------------------------------------------------------

	/**
	 * Test get_final_return_url includes sso=done and wp-login.php.
	 */
	public function test_get_final_return_url_includes_done_and_wp_login(): void {
		$sso = SSO::get_instance();
		$url = 'https://example.com/sso?redirect_to=https%3A%2F%2Fexample.com%2Fdashboard';

		$final = $sso->get_final_return_url($url);

		$this->assertStringContainsString('sso=done', $final);
		$this->assertStringContainsString('wp-login.php', $final);
		$this->assertStringContainsString('redirect_to=', $final);
	}

	/**
	 * Test get_final_return_url strips the SSO path from the URL.
	 */
	public function test_get_final_return_url_strips_sso_path_from_url_path(): void {
		$sso   = SSO::get_instance();
		$url   = 'https://sub.example.com/sso';
		$final = $sso->get_final_return_url($url);

		// The path portion should not end with /sso/.
		$parsed_path = wp_parse_url($final, PHP_URL_PATH);
		$this->assertStringNotContainsString('/sso/', $parsed_path);
	}

	// ------------------------------------------------------------------
	// get_broker_by_id
	// ------------------------------------------------------------------

	/**
	 * Test get_broker_by_id returns null for an invalid ID.
	 */
	public function test_get_broker_by_id_returns_null_for_invalid_id(): void {
		$sso = SSO::get_instance();

		$this->assertNull($sso->get_broker_by_id('completely-invalid'));
	}

	/**
	 * Test get_broker_by_id returns an array for a valid site.
	 */
	public function test_get_broker_by_id_returns_array_for_valid_site(): void {
		$blog_id = get_current_blog_id();

		$sso    = SSO::get_instance();
		$coded  = $sso->encode($blog_id, $sso->salt());
		$result = $sso->get_broker_by_id($coded);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('secret', $result);
		$this->assertArrayHasKey('domains', $result);
		$this->assertIsArray($result['domains']);
		$this->assertNotEmpty($result['domains']);
	}

	// ------------------------------------------------------------------
	// handle_broker early returns
	// ------------------------------------------------------------------

	/**
	 * Test handle_broker returns early on the main site.
	 */
	public function test_handle_broker_returns_early_on_main_site(): void {
		// On the main site, handle_broker should return without doing anything.
		$this->assertTrue(is_main_site(), 'Test expects to run on main site');

		$sso = SSO::get_instance();

		// Should return without exit — no exception, no redirect.
		$sso->handle_broker('redirect');

		// If we reach here, the early return worked.
		$this->assertTrue(true);
	}

	// ------------------------------------------------------------------
	// handle_broker sets wu_sso_denied cookie on failure
	// ------------------------------------------------------------------

	/**
	 * Verify that the source code sets the wu_sso_denied cookie
	 * when sso_verify is 'invalid', preventing redirect loops.
	 */
	public function test_handle_broker_source_sets_denied_cookie_on_invalid_verify(): void {
		$source = file_get_contents(
			dirname(__DIR__, 3) . '/inc/sso/class-sso.php'
		);

		// The 'invalid' === $verify_code branch must setcookie('wu_sso_denied', ...)
		$pattern = "/'invalid'\s*===\s*\\\$verify_code.*?setcookie\(\s*'wu_sso_denied'/s";
		$this->assertMatchesRegularExpression(
			$pattern,
			$source,
			'handle_broker must set wu_sso_denied cookie when sso_verify is invalid'
		);
	}

	/**
	 * Verify that $_COOKIE['wu_sso_denied'] is also set for the current request,
	 * so that later code (handle_auth_redirect, enqueue_script) sees it immediately.
	 */
	public function test_handle_broker_source_sets_cookie_superglobal_on_invalid_verify(): void {
		$source = file_get_contents(
			dirname(__DIR__, 3) . '/inc/sso/class-sso.php'
		);

		$pattern = "/'invalid'\s*===\s*\\\$verify_code.*?\\\$_COOKIE\s*\[\s*'wu_sso_denied'\s*\]/s";
		$this->assertMatchesRegularExpression(
			$pattern,
			$source,
			'handle_broker must set $_COOKIE[wu_sso_denied] for the current request'
		);
	}

	/**
	 * Verify that the invalid verify branch redirects to return_url
	 * instead of leaving the user on the /sso 404 page.
	 */
	public function test_handle_broker_source_redirects_to_return_url_on_invalid_verify(): void {
		$source = file_get_contents(
			dirname(__DIR__, 3) . '/inc/sso/class-sso.php'
		);

		$pattern = "/'invalid'\s*===\s*\\\$verify_code.*?wp_safe_redirect\s*\(\s*\\\$return_url/s";
		$this->assertMatchesRegularExpression(
			$pattern,
			$source,
			'handle_broker must redirect to return_url when sso_verify is invalid'
		);
	}

	// ------------------------------------------------------------------
	// handle_broker JSONP returns error instead of redirect for unattached broker
	// ------------------------------------------------------------------

	/**
	 * Verify that when the broker is not attached and the response type is JSONP,
	 * handle_broker returns a JSONP error response instead of redirecting.
	 * Redirecting a script tag request breaks the wu.sso() callback and
	 * causes an infinite redirect loop.
	 */
	public function test_handle_broker_source_returns_jsonp_error_for_unattached_broker(): void {
		$source = file_get_contents(
			dirname(__DIR__, 3) . '/inc/sso/class-sso.php'
		);

		// The unattached broker JSONP branch must call wu.sso() with an error, not wp_safe_redirect.
		$pattern = "/isAttached\(\).*?'jsonp'\s*===\s*\\\$response_type.*?wu\.sso\(/s";
		$this->assertMatchesRegularExpression(
			$pattern,
			$source,
			'handle_broker must return JSONP error (not redirect) when broker is unattached and response type is jsonp'
		);
	}

	/**
	 * Verify that the SSO JS does not redirect in incognito mode.
	 * The incognito redirect caused an infinite loop:
	 * redirect -> sso_verify=invalid -> redirect -> repeat.
	 */
	public function test_sso_js_does_not_contain_incognito_redirect(): void {
		$source = file_get_contents(
			dirname(__DIR__, 3) . '/assets/js/sso.js'
		);

		$this->assertStringNotContainsString(
			'is_incognito',
			$source,
			'SSO JS should not contain incognito detection — the incognito redirect path caused infinite loops'
		);
	}

	// ------------------------------------------------------------------
	// handle_requests early return
	// ------------------------------------------------------------------

	/**
	 * Test handle_requests returns early without SSO action params.
	 */
	public function test_handle_requests_returns_early_without_sso_action(): void {
		// With no SSO-related request params, handle_requests should return immediately.
		$sso = SSO::get_instance();

		$sso->handle_requests();

		// If we reach here without exit, the early return worked.
		$this->assertTrue(true);
	}

	/**
	 * Test handle_requests normalizes sso-grant URLs before dispatching.
	 */
	public function test_handle_requests_source_normalizes_sso_grant_to_server_action(): void {
		$source = file_get_contents(
			dirname(__DIR__, 3) . '/inc/sso/class-sso.php'
		);

		$this->assertStringContainsString(
			"preg_replace('/-grant\\/?$/', '', \$action)",
			$source,
			'handle_requests() must normalize sso-grant requests to the sso server action'
		);
	}

	// ------------------------------------------------------------------
	// Session handler
	// ------------------------------------------------------------------

	/**
	 * Test session handler isActive returns false by default.
	 */
	public function test_session_handler_is_active_returns_false(): void {
		$sso     = SSO::get_instance();
		$handler = new SSO_Session_Handler($sso);

		$this->assertFalse($handler->isActive());
	}

	/**
	 * Test session handler getId reads the broker request parameter.
	 */
	public function test_session_handler_get_id_reads_broker_param(): void {
		$_REQUEST['broker'] = 'test-broker-id';

		$sso     = SSO::get_instance();
		$handler = new SSO_Session_Handler($sso);

		$this->assertSame('test-broker-id', $handler->getId());
	}

	/**
	 * Test session handler start throws when user is not logged in.
	 */
	public function test_session_handler_start_throws_when_not_logged_in(): void {
		wp_set_current_user(0);

		$this->expectException(\WP_Ultimo\SSO\Exception\SSO_Session_Exception::class);
		$this->expectExceptionCode(401);

		$sso     = SSO::get_instance();
		$handler = new SSO_Session_Handler($sso);

		$handler->start();
	}

	/**
	 * Test session handler resume sets target user ID from transient.
	 */
	public function test_session_handler_resume_sets_target_user_id_from_transient(): void {
		$user_id = self::factory()->user->create();
		$site_id = get_current_blog_id();

		$sso    = SSO::get_instance();
		$salt   = $sso->salt();
		$broker = $sso->encode($site_id, $salt);

		set_site_transient("sso-{$broker}-{$site_id}", $user_id, 180);

		$handler = new SSO_Session_Handler($sso);
		$handler->resume($broker);

		$this->assertSame($user_id, $sso->get_target_user_id());

		// Clean up.
		$sso->set_target_user_id(null);
		delete_site_transient("sso-{$broker}-{$site_id}");
	}

	/**
	 * Test session handler resume does not set user when transient is missing.
	 */
	public function test_session_handler_resume_does_not_set_user_when_transient_missing(): void {
		$sso  = SSO::get_instance();
		$salt = $sso->salt();

		// Reset target.
		$sso->set_target_user_id(null);

		$broker = $sso->encode(9999, $salt);

		$handler = new SSO_Session_Handler($sso);
		$handler->resume($broker);

		$this->assertNull($sso->get_target_user_id());
	}

	// ------------------------------------------------------------------
	// SSO Broker
	// ------------------------------------------------------------------

	/**
	 * Test broker getAttachUrl includes required parameters.
	 */
	public function test_broker_get_attach_url_includes_required_params(): void {
		$sso = SSO::get_instance();

		$blog_id   = get_current_blog_id();
		$blog      = get_blog_details($blog_id);
		$date      = $blog ? $blog->registered : '2024-01-01 00:00:00';
		$secret    = $sso->calculate_secret_from_date($date);
		$url       = trailingslashit(network_home_url()) . $sso->get_url_path('grant');
		$broker_id = $sso->encode($blog_id, $sso->salt());
		$broker    = new SSO_Broker($url, $broker_id, $secret);
		$broker    = $broker->withTokenIn(new \ArrayObject());

		$attach_url = $broker->getAttachUrl(['return_url' => 'https://example.com/page']);

		$parts = wp_parse_url($attach_url);
		parse_str($parts['query'] ?? '', $query);

		$this->assertArrayHasKey('broker', $query);
		$this->assertArrayHasKey('token', $query);
		$this->assertArrayHasKey('checksum', $query);
		$this->assertSame('https://example.com/page', $query['return_url']);
	}

	/**
	 * Test broker is_must_redirect_call returns false initially.
	 */
	public function test_broker_is_must_redirect_call_returns_false_initially(): void {
		$sso = SSO::get_instance();

		$blog_id   = get_current_blog_id();
		$blog      = get_blog_details($blog_id);
		$date      = $blog ? $blog->registered : '2024-01-01 00:00:00';
		$secret    = $sso->calculate_secret_from_date($date);
		$url       = trailingslashit(network_home_url()) . $sso->get_url_path('grant');
		$broker_id = $sso->encode($blog_id, $sso->salt());
		$broker    = new SSO_Broker($url, $broker_id, $secret);
		$broker    = $broker->withTokenIn(new \ArrayObject());

		$this->assertFalse($broker->is_must_redirect_call());
	}

	// ------------------------------------------------------------------
	// JSONP Content-Type header (the bug fix we are validating)
	// ------------------------------------------------------------------

	/**
	 * Verify that the JSONP branch in handle_server sets the
	 * Content-Type: application/javascript header in the source code.
	 *
	 * Since handle_server calls exit(), we cannot run it directly in
	 * a unit test. Instead we verify the source contains the header
	 * call before the printf to guard against regressions.
	 */
	public function test_handle_server_source_sets_javascript_content_type_for_jsonp(): void {
		$source = file_get_contents(
			dirname(__DIR__, 3) . '/inc/sso/class-sso.php'
		);

		// Find the handle_server JSONP branch.
		$pattern = "/if\s*\(\s*'jsonp'\s*===\s*\\\$response_type\s*\)\s*\{.*?header\(\s*'Content-Type:\s*application\/javascript/s";
		$this->assertMatchesRegularExpression(
			$pattern,
			$source,
			'handle_server JSONP branch must set Content-Type: application/javascript header'
		);
	}

	/**
	 * Verify that all JSONP branches set the
	 * Content-Type: application/javascript header.
	 *
	 * There are four JSONP response paths:
	 * 1. handle_server JSONP success/error response
	 * 2. handle_broker JSONP error for unattached broker
	 * 3. handle_broker JSONP "nothing to see here" for attached broker
	 * 4. handle_main_site_logged_in_user JSONP success response
	 */
	public function test_handle_broker_source_sets_javascript_content_type_for_jsonp(): void {
		$source = file_get_contents(
			dirname(__DIR__, 3) . '/inc/sso/class-sso.php'
		);

		// There should be four JSONP blocks with the header.
		$count = preg_match_all(
			"/header\(\s*'Content-Type:\s*application\/javascript;\s*charset=utf-8'\s*\)/",
			$source
		);

		$this->assertSame(
			4,
			$count,
			'All four JSONP response paths must set the Content-Type header'
		);
	}

	// ------------------------------------------------------------------
	// build_server_request
	// ------------------------------------------------------------------

	/**
	 * Test build_server_request returns a PSR-7 instance.
	 */
	public function test_build_server_request_returns_psr7_instance(): void {
		$sso     = SSO::get_instance();
		$request = $sso->build_server_request('GET');

		$this->assertInstanceOf(\Psr\Http\Message\ServerRequestInterface::class, $request);
	}

	// ------------------------------------------------------------------
	// get_server
	// ------------------------------------------------------------------

	/**
	 * Test get_server returns a vendored SSO Server instance.
	 */
	public function test_get_server_returns_server_instance(): void {
		$sso    = SSO::get_instance();
		$server = $sso->get_server();

		$this->assertInstanceOf(\WP_Ultimo\SSO\Jasny\Server\Server::class, $server);
	}

	// ------------------------------------------------------------------
	// add_additional_origins
	// ------------------------------------------------------------------

	/**
	 * Test add_additional_origins includes the main site domain.
	 */
	public function test_add_additional_origins_includes_main_site_domain(): void {
		global $current_site;

		$sso     = SSO::get_instance();
		$origins = $sso->add_additional_origins([]);

		$this->assertContains("http://{$current_site->domain}", $origins);
		$this->assertContains("https://{$current_site->domain}", $origins);
	}

	/**
	 * Test add_additional_origins preserves existing origins.
	 */
	public function test_add_additional_origins_preserves_existing_origins(): void {
		$sso     = SSO::get_instance();
		$origins = $sso->add_additional_origins(['https://existing.com']);

		$this->assertContains('https://existing.com', $origins);
	}
}
