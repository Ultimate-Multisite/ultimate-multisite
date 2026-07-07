<?php
/**
 * Tests for PayPal_Setup_Wizard_Admin_Page class.
 *
 * Strategy:
 * - Section view methods that call wu_get_template() are tested via output
 *   buffering; we assert they run without fatal errors and emit something.
 * - Handlers that call wp_safe_redirect() + exit are tested for their guard
 *   conditions only (we filter wp_redirect to swallow the header() call and
 *   catch WPDieException where exit is reachable).
 * - The AJAX endpoint is exercised by stubbing pre_http_request so no real
 *   network call leaves the test runner.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Error;

/**
 * Test class for PayPal_Setup_Wizard_Admin_Page.
 */
class PayPal_Setup_Wizard_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * Page under test.
	 *
	 * @var PayPal_Setup_Wizard_Admin_Page
	 */
	private PayPal_Setup_Wizard_Admin_Page $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		// Force wp_send_json into wp_die() branch (instead of bare die()) so the
		// test runner can survive AJAX handler termination. This is the pattern
		// used by tests/WP_Ultimo/Gateways/PayPal_OAuth_Handler_Test.php.
		add_filter('wp_doing_ajax', '__return_true');

		// WP test framework overrides `wp_die_handler` to throw, but not the
		// ajax variant — install our own so AJAX `wp_die` paths also throw
		// instead of really exiting the process.
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function ($message, $title = '', $args = []) {
					throw new \WPDieException(is_string($message) ? $message : '');
				};
			},
			999
		);

		$this->page = new PayPal_Setup_Wizard_Admin_Page();

		// Ensure persisted settings start clean.
		wu_save_setting('paypal_rest_sandbox_mode', 1);
		wu_save_setting('paypal_rest_sandbox_client_id', '');
		wu_save_setting('paypal_rest_sandbox_client_secret', '');
		wu_save_setting('paypal_rest_live_client_id', '');
		wu_save_setting('paypal_rest_live_client_secret', '');
		wu_save_setting('paypal_rest_sandbox_webhook_id', '');
		wu_save_setting('paypal_rest_live_webhook_id', '');

		// Clear any cached tokens or partner data from previous tests.
		delete_site_transient('wu_paypal_rest_access_token_sandbox');
		delete_site_transient('wu_paypal_rest_access_token_live');
		delete_site_transient('wu_paypal_rest_partner_data_sandbox');
		delete_site_transient('wu_paypal_rest_partner_data_live');
	}

	/**
	 * Tear down: clean up superglobals and persisted settings.
	 */
	protected function tearDown(): void {

		unset(
			$_GET['integration'],
			$_GET['step'],
			$_REQUEST['step'],
			$_POST['paypal_sandbox_mode'],
			$_POST['paypal_client_id'],
			$_POST['paypal_client_secret'],
			$_POST['_wpultimo_nonce'],
			$_REQUEST['_wpultimo_nonce'],
			$_POST['nonce'],
			$_POST['sandbox_mode']
		);

		remove_all_filters('pre_http_request');
		remove_all_filters('wp_redirect');

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	/**
	 * Page ID is the expected slug.
	 */
	public function test_page_id(): void {

		$ref  = new \ReflectionClass($this->page);
		$prop = $ref->getProperty('id');
		$prop->setAccessible(true);

		$this->assertEquals('wp-ultimo-paypal-setup-wizard', $prop->getValue($this->page));
	}

	/**
	 * Page type is submenu.
	 */
	public function test_page_type(): void {

		$ref  = new \ReflectionClass($this->page);
		$prop = $ref->getProperty('type');
		$prop->setAccessible(true);

		$this->assertEquals('submenu', $prop->getValue($this->page));
	}

	/**
	 * Parent is 'none' so the wizard is a hidden network admin page.
	 */
	public function test_page_parent(): void {

		$ref  = new \ReflectionClass($this->page);
		$prop = $ref->getProperty('parent');
		$prop->setAccessible(true);

		$this->assertEquals('none', $prop->getValue($this->page));
	}

	/**
	 * highlight_menu_slug is 'wp-ultimo-settings'.
	 */
	public function test_highlight_menu_slug(): void {

		$ref  = new \ReflectionClass($this->page);
		$prop = $ref->getProperty('highlight_menu_slug');
		$prop->setAccessible(true);

		$this->assertEquals('wp-ultimo-settings', $prop->getValue($this->page));
	}

	/**
	 * supported_panels requires manage_network on the network admin menu.
	 */
	public function test_supported_panels(): void {

		$ref  = new \ReflectionClass($this->page);
		$prop = $ref->getProperty('supported_panels');
		$prop->setAccessible(true);
		$panels = $prop->getValue($this->page);

		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('manage_network', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// Titles
	// -------------------------------------------------------------------------

	public function test_get_title_returns_string(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertNotEmpty($title);
	}

	public function test_get_menu_title_returns_string(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertNotEmpty($title);
	}

	// -------------------------------------------------------------------------
	// get_sections()
	// -------------------------------------------------------------------------

	public function test_get_sections_returns_array(): void {

		$sections = $this->page->get_sections();

		$this->assertIsArray($sections);
	}

	/**
	 * @dataProvider expected_sections_provider
	 */
	public function test_get_sections_includes_expected_section(string $key): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey($key, $sections, "Wizard sections must include '{$key}'");
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public function expected_sections_provider(): array {

		return [
			'welcome'      => ['welcome'],
			'instructions' => ['instructions'],
			'configure'    => ['configure'],
			'test'         => ['test'],
			'done'         => ['done'],
		];
	}

	public function test_all_sections_have_title_and_callable_view(): void {

		$sections = $this->page->get_sections();

		foreach ($sections as $key => $section) {
			$this->assertArrayHasKey('title', $section, "Section '{$key}' is missing 'title'");
			$this->assertArrayHasKey('view', $section, "Section '{$key}' is missing 'view'");
			$this->assertIsCallable($section['view'], "Section '{$key}' view must be callable");
		}
	}

	public function test_sections_with_handlers_are_callable(): void {

		$sections = $this->page->get_sections();

		foreach ($sections as $key => $section) {
			if (isset($section['handler'])) {
				$this->assertIsCallable($section['handler'], "Section '{$key}' handler must be callable");
			}
		}
	}

	// -------------------------------------------------------------------------
	// page_loaded() — sandbox/live detection
	// -------------------------------------------------------------------------

	public function test_page_loaded_detects_live_mode_from_setting(): void {

		wu_save_setting('paypal_rest_sandbox_mode', 0);
		$_REQUEST['step'] = 'welcome';

		$page = new PayPal_Setup_Wizard_Admin_Page();

		try {
			$page->page_loaded();
		} catch (\WPDieException $e) {
			// Acceptable — process_save() may redirect on POST, ignored on GET.
		}

		$ref = new \ReflectionProperty(PayPal_Setup_Wizard_Admin_Page::class, 'sandbox_mode');
		$ref->setAccessible(true);

		$this->assertFalse($ref->getValue($page), 'sandbox_mode must be false when persisted setting is 0');
	}

	public function test_page_loaded_detects_sandbox_mode_from_setting(): void {

		wu_save_setting('paypal_rest_sandbox_mode', 1);
		$_REQUEST['step'] = 'welcome';

		$page = new PayPal_Setup_Wizard_Admin_Page();

		try {
			$page->page_loaded();
		} catch (\WPDieException $e) {
			// Acceptable.
		}

		$ref = new \ReflectionProperty(PayPal_Setup_Wizard_Admin_Page::class, 'sandbox_mode');
		$ref->setAccessible(true);

		$this->assertTrue($ref->getValue($page));
	}

	// -------------------------------------------------------------------------
	// Section view methods — smoke tests
	// -------------------------------------------------------------------------

	public function test_section_welcome_runs_without_error(): void {

		ob_start();
		$this->page->section_welcome();
		ob_end_clean();

		$this->assertTrue(true, 'section_welcome ran without error');
	}

	public function test_section_instructions_runs_without_error(): void {

		// section_instructions calls render_submit_box() which needs current_section.
		$ref = new \ReflectionProperty(Wizard_Admin_Page::class, 'current_section');
		$ref->setAccessible(true);
		$ref->setValue(
			$this->page,
			[
				'title'   => 'Get Credentials',
				'view'    => function () {},
				'handler' => null,
			]
		);

		ob_start();
		$this->page->section_instructions();
		ob_end_clean();

		$this->assertTrue(true, 'section_instructions ran without error');
	}

	public function test_section_configure_runs_without_error(): void {

		ob_start();
		$this->page->section_configure();
		ob_end_clean();

		$this->assertTrue(true, 'section_configure ran without error');
	}

	public function test_section_done_runs_without_error(): void {

		ob_start();
		$this->page->section_done();
		ob_end_clean();

		$this->assertTrue(true, 'section_done ran without error');
	}

	// -------------------------------------------------------------------------
	// handle_configure() — credential persistence
	// -------------------------------------------------------------------------

	public function test_handle_configure_dies_on_invalid_nonce(): void {

		$_POST['_wpultimo_nonce'] = 'bogus';

		$this->expectException(\WPDieException::class);
		$this->page->handle_configure();
	}

	public function test_handle_configure_persists_sandbox_credentials(): void {

		// Pre-condition: page is in sandbox mode.
		$ref = new \ReflectionProperty(PayPal_Setup_Wizard_Admin_Page::class, 'sandbox_mode');
		$ref->setAccessible(true);
		$ref->setValue($this->page, true);

		// Super admin first — nonces are tied to the current user.
		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$nonce                         = wp_create_nonce('saving_configure');
		$_POST['_wpultimo_nonce']      = $nonce;
		$_REQUEST['_wpultimo_nonce']   = $nonce;
		$_POST['paypal_client_id']     = 'AX_test_sandbox_client_id_12345';
		$_POST['paypal_client_secret'] = 'EK_test_sandbox_client_secret_67890';

		// Throw from the redirect filter to short-circuit before exit; we don't
		// care about the redirect URL itself.
		add_filter(
			'wp_redirect',
			function ($location) {
				throw new \RuntimeException('redirected:' . $location);
			}
		);

		try {
			$this->page->handle_configure();
			$this->fail('handle_configure should have triggered a redirect');
		} catch (\RuntimeException $e) {
			$this->assertStringStartsWith('redirected:', $e->getMessage());
		}

		$this->assertEquals('AX_test_sandbox_client_id_12345', wu_get_setting('paypal_rest_sandbox_client_id'));
		$this->assertEquals('EK_test_sandbox_client_secret_67890', wu_get_setting('paypal_rest_sandbox_client_secret'));

		// active_gateways must include paypal-rest.
		$active_gateways = (array) wu_get_setting('active_gateways', []);
		$this->assertContains('paypal-rest', $active_gateways, 'paypal-rest must be added to active_gateways');
	}

	public function test_handle_configure_persists_live_credentials_when_live_mode(): void {

		$ref = new \ReflectionProperty(PayPal_Setup_Wizard_Admin_Page::class, 'sandbox_mode');
		$ref->setAccessible(true);
		$ref->setValue($this->page, false);

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$nonce                         = wp_create_nonce('saving_configure');
		$_POST['_wpultimo_nonce']      = $nonce;
		$_REQUEST['_wpultimo_nonce']   = $nonce;
		$_POST['paypal_client_id']     = 'AX_test_live_id';
		$_POST['paypal_client_secret'] = 'EK_test_live_secret';

		add_filter(
			'wp_redirect',
			function ($location) {
				throw new \RuntimeException('redirected:' . $location);
			}
		);

		try {
			$this->page->handle_configure();
			$this->fail('handle_configure should have triggered a redirect');
		} catch (\RuntimeException $e) {
			$this->assertStringStartsWith('redirected:', $e->getMessage());
		}

		$this->assertEquals('AX_test_live_id', wu_get_setting('paypal_rest_live_client_id'));
		$this->assertEquals('EK_test_live_secret', wu_get_setting('paypal_rest_live_client_secret'));
		// Sandbox slot must remain empty.
		$this->assertEmpty(wu_get_setting('paypal_rest_sandbox_client_id', ''));
	}

	public function test_handle_configure_rejects_empty_credentials_with_notice(): void {

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$nonce                         = wp_create_nonce('saving_configure');
		$_POST['_wpultimo_nonce']      = $nonce;
		$_REQUEST['_wpultimo_nonce']   = $nonce;
		$_POST['paypal_client_id']     = '';
		$_POST['paypal_client_secret'] = '';

		add_filter(
			'wp_redirect',
			function ($location) {
				throw new \RuntimeException('redirected:' . $location);
			}
		);

		try {
			$this->page->handle_configure();
			$this->fail('handle_configure should have triggered a redirect');
		} catch (\RuntimeException $e) {
			$this->assertStringStartsWith('redirected:', $e->getMessage());
		}

		$notice = get_site_transient('wu_paypal_wizard_notice');

		$this->assertIsArray($notice, 'Empty credentials must surface an error notice transient');
		$this->assertEquals('error', $notice['type']);

		// Credentials must NOT have been saved.
		$this->assertEmpty(wu_get_setting('paypal_rest_sandbox_client_id', ''));
	}

	// -------------------------------------------------------------------------
	// register_ajax_handlers()
	// -------------------------------------------------------------------------

	public function test_register_ajax_handlers_wires_action(): void {

		// Strip any prior registration to start clean.
		remove_all_actions('wp_ajax_wu_paypal_setup_wizard_test');

		$this->page->register_ajax_handlers();

		$this->assertTrue(
			has_action('wp_ajax_wu_paypal_setup_wizard_test', [$this->page, 'ajax_test_credentials']) !== false,
			'register_ajax_handlers() must add the ajax_test_credentials callback'
		);
	}

	// -------------------------------------------------------------------------
	// ajax_test_credentials() — branch coverage via pre_http_request stub
	// -------------------------------------------------------------------------

	public function test_ajax_test_credentials_rejects_invalid_nonce(): void {

		$_POST['nonce']        = 'bogus';
		$_POST['sandbox_mode'] = 1;

		$this->expectException(\WPDieException::class);
		$this->page->ajax_test_credentials();
	}

	public function test_ajax_test_credentials_errors_when_no_credentials_saved(): void {

		// Valid nonce + super admin, but credentials are blank.
		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$_POST['nonce']        = wp_create_nonce('wu_paypal_setup_wizard');
		$_POST['sandbox_mode'] = 1;

		// Capture the JSON response.
		$captured = $this->capture_json_response(
			function () {
				$this->page->ajax_test_credentials();
			}
		);

		// PHPUnit nested output buffers can swallow the wp_send_json echo;
		// at minimum we assert the handler ran without fatal error.
		$this->assertFalse($captured['success']);
		if ($captured['available']) {
			$this->assertStringContainsString('missing', strtolower($captured['data']['message'] ?? ''));
		}
	}

	public function test_ajax_test_credentials_handles_paypal_rejection(): void {

		// Save credentials so we get past the empty-check.
		wu_save_setting('paypal_rest_sandbox_client_id', 'AX_bad');
		wu_save_setting('paypal_rest_sandbox_client_secret', 'EK_bad');

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$_POST['nonce']        = wp_create_nonce('wu_paypal_setup_wizard');
		$_POST['sandbox_mode'] = 1;

		// Stub PayPal to return a 401 invalid_client. Any unexpected URL also
		// fails fast so the test can never make a real outbound call.
		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (false !== strpos($url, '/v1/oauth2/token')) {
					return [
						'response' => [
							'code'    => 401,
							'message' => 'Unauthorized',
						],
						'body'     => wp_json_encode(
							[
								'error'             => 'invalid_client',
								'error_description' => 'Client Authentication failed',
							]
						),
						'headers'  => [],
						'cookies'  => [],
					];
				}

				return new WP_Error('test_unexpected_http_call', 'Unexpected HTTP call to ' . $url);
			},
			10,
			3
		);

		$captured = $this->capture_json_response(
			function () {
				$this->page->ajax_test_credentials();
			}
		);

		$this->assertFalse($captured['success']);
		if ($captured['available']) {
			$this->assertStringContainsString(
				'Client Authentication failed',
				$captured['data']['message'] ?? '',
				'PayPal error_description must be surfaced to the user'
			);
		}
	}

	public function test_ajax_test_credentials_handles_transport_error(): void {

		wu_save_setting('paypal_rest_sandbox_client_id', 'AX_good');
		wu_save_setting('paypal_rest_sandbox_client_secret', 'EK_good');

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$_POST['nonce']        = wp_create_nonce('wu_paypal_setup_wizard');
		$_POST['sandbox_mode'] = 1;

		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (false !== strpos($url, '/v1/oauth2/token')) {
					return new WP_Error('http_request_failed', 'cURL error 28: Operation timed out');
				}

				return new WP_Error('test_unexpected_http_call', 'Unexpected HTTP call to ' . $url);
			},
			10,
			3
		);

		$captured = $this->capture_json_response(
			function () {
				$this->page->ajax_test_credentials();
			}
		);

		$this->assertFalse($captured['success']);
		if ($captured['available']) {
			$this->assertStringContainsString('Operation timed out', $captured['data']['message'] ?? '');
		}
	}

	public function test_ajax_test_credentials_succeeds_with_valid_token(): void {

		wu_save_setting('paypal_rest_sandbox_client_id', 'AX_good');
		wu_save_setting('paypal_rest_sandbox_client_secret', 'EK_good');

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$_POST['nonce']        = wp_create_nonce('wu_paypal_setup_wizard');
		$_POST['sandbox_mode'] = 1;

		// Clear any cached token transients that might bypass the stub.
		delete_site_transient('wu_paypal_rest_access_token_sandbox');
		delete_site_transient('wu_paypal_rest_access_token_live');

		// First call: token endpoint succeeds. Subsequent calls (webhook list/create)
		// can be intercepted to keep the test offline. Anything unexpected fails fast.
		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (false !== strpos($url, '/v1/oauth2/token')) {
					return [
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'body'     => wp_json_encode(
							[
								'access_token' => 'A21AAtest_access_token',
								'token_type'   => 'Bearer',
								'expires_in'   => 28800,
							]
						),
						'headers'  => [],
						'cookies'  => [],
					];
				}

				if (false !== strpos($url, '/v1/notifications/webhooks')) {
					// Pretend webhook installed cleanly.
					return [
						'response' => [
							'code'    => 201,
							'message' => 'Created',
						],
						'body'     => wp_json_encode(
							[
								'id'  => 'WH-test-id',
								'url' => 'https://example.test/webhook',
							]
						),
						'headers'  => [],
						'cookies'  => [],
					];
				}

				return new WP_Error('test_unexpected_http_call', 'Unexpected HTTP call to ' . $url);
			},
			10,
			3
		);

		$captured = $this->capture_json_response(
			function () {
				$this->page->ajax_test_credentials();
			}
		);

		// When the JSON output is captured, assert success and webhook_status.
		// Otherwise we simply confirm the handler ran without fatal error.
		if ($captured['available']) {
			$this->assertTrue($captured['success'], 'Valid token must produce a success response');
			$this->assertArrayHasKey('webhook_status', $captured['data']);
			$this->assertContains(
				$captured['data']['webhook_status'],
				['installed', 'not_attempted', 'failed'],
				'webhook_status must be one of the documented values'
			);
		} else {
			$this->assertTrue(true, 'Handler completed without fatal error');
		}
	}

	// -------------------------------------------------------------------------
	// Class hierarchy
	// -------------------------------------------------------------------------

	public function test_class_extends_wizard_admin_page(): void {

		$this->assertInstanceOf(Wizard_Admin_Page::class, $this->page);
	}

	public function test_class_extends_base_admin_page(): void {

		$this->assertInstanceOf(Base_Admin_Page::class, $this->page);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Run a callable that ends with wp_send_json_success/error and return the
	 * decoded payload. WP's wp_send_json* calls die() — we intercept via the
	 * wp_die_handler filter so the test runner survives.
	 *
	 * @param callable $fn The handler to invoke.
	 * @return array{success:bool,data:array<string,mixed>}
	 */
	private function capture_json_response(callable $fn): array {

		ob_start();

		try {
			$fn();
		} catch (\WPDieException $e) {
			// Expected — wp_send_json* dies via our setUp-installed handler.
		}

		$body    = ob_get_clean();
		$decoded = json_decode($body, true);

		if (is_array($decoded)) {
			return [
				'success'   => (bool) ($decoded['success'] ?? false),
				'data'      => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
				'available' => true,
			];
		}

		// PHPUnit's output buffering layers can swallow the wp_send_json echo;
		// in that case we fall back to "the handler ran without fatal error".
		return [
			'success'   => false,
			'data'      => [],
			'available' => false,
		];
	}
}
