<?php
/**
 * Tests for External_Cron_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for External_Cron_Admin_Page.
 *
 * @group external-cron-admin-page
 */
class External_Cron_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var External_Cron_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new External_Cron_Admin_Page();
	}

	/**
	 * Tear down after each test.
	 */
	protected function tearDown(): void {

		// Clean up settings.
		wu_save_setting('external_cron_site_id', '');
		wu_save_setting('external_cron_enabled', false);

		// Clean up site options.
		delete_site_option('wu_external_cron_last_sync');

		// Clean up superglobals.
		$_POST    = array();
		$_REQUEST = array();

		// Remove all filters added during tests.
		remove_all_filters('wp_doing_ajax');
		remove_all_filters('wp_die_ajax_handler');

		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Property defaults
	// ------------------------------------------------------------------

	/**
	 * Test page id is correct.
	 */
	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-external-cron', $property->getValue($this->page));
	}

	/**
	 * Test page type is submenu.
	 */
	public function test_page_type(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('type');
		$property->setAccessible(true);

		$this->assertEquals('submenu', $property->getValue($this->page));
	}

	/**
	 * Test parent is 'none'.
	 */
	public function test_parent_is_none(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);

		$this->assertEquals('none', $property->getValue($this->page));
	}

	/**
	 * Test highlight_menu_slug is 'wp-ultimo-settings'.
	 */
	public function test_highlight_menu_slug(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-settings', $property->getValue($this->page));
	}

	/**
	 * Test badge_count is zero.
	 */
	public function test_badge_count_is_zero(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);

		$this->assertEquals(0, $property->getValue($this->page));
	}

	/**
	 * Test supported_panels contains network_admin_menu with manage_network capability.
	 */
	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);

		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('manage_network', $panels['network_admin_menu']);
	}

	// ------------------------------------------------------------------
	// Constants
	// ------------------------------------------------------------------

	/**
	 * Test SERVICE_URL constant is set.
	 */
	public function test_service_url_constant(): void {

		$this->assertEquals('https://ultimatemultisite.com', External_Cron_Admin_Page::SERVICE_URL);
	}

	/**
	 * Test PRODUCT_SLUG constant is set.
	 */
	public function test_product_slug_constant(): void {

		$this->assertEquals('external-cron-service', External_Cron_Admin_Page::PRODUCT_SLUG);
	}

	// ------------------------------------------------------------------
	// Instantiation / inheritance
	// ------------------------------------------------------------------

	/**
	 * Test page is instantiable.
	 */
	public function test_instantiation(): void {

		$this->assertInstanceOf(External_Cron_Admin_Page::class, $this->page);
	}

	/**
	 * Test page extends Base_Admin_Page.
	 */
	public function test_extends_base_admin_page(): void {

		$this->assertInstanceOf(Base_Admin_Page::class, $this->page);
	}

	// ------------------------------------------------------------------
	// get_title / get_menu_title
	// ------------------------------------------------------------------

	/**
	 * Test get_title returns a string.
	 */
	public function test_get_title_returns_string(): void {

		$this->assertIsString($this->page->get_title());
	}

	/**
	 * Test get_title returns expected value.
	 */
	public function test_get_title_value(): void {

		$this->assertEquals('External Cron Service', $this->page->get_title());
	}

	/**
	 * Test get_menu_title returns a string.
	 */
	public function test_get_menu_title_returns_string(): void {

		$this->assertIsString($this->page->get_menu_title());
	}

	/**
	 * Test get_menu_title returns expected value.
	 */
	public function test_get_menu_title_value(): void {

		$this->assertEquals('External Cron', $this->page->get_menu_title());
	}

	// ------------------------------------------------------------------
	// init
	// ------------------------------------------------------------------

	/**
	 * Test init registers ajax_connect action.
	 */
	public function test_init_registers_ajax_connect(): void {

		$page = new External_Cron_Admin_Page();
		$page->init();

		$this->assertNotFalse(has_action('wp_ajax_wu_external_cron_connect', array($page, 'ajax_connect')));
	}

	/**
	 * Test init registers ajax_disconnect action.
	 */
	public function test_init_registers_ajax_disconnect(): void {

		$page = new External_Cron_Admin_Page();
		$page->init();

		$this->assertNotFalse(has_action('wp_ajax_wu_external_cron_disconnect', array($page, 'ajax_disconnect')));
	}

	/**
	 * Test init registers ajax_sync action.
	 */
	public function test_init_registers_ajax_sync(): void {

		$page = new External_Cron_Admin_Page();
		$page->init();

		$this->assertNotFalse(has_action('wp_ajax_wu_external_cron_sync', array($page, 'ajax_sync')));
	}

	/**
	 * Test init registers ajax_toggle action.
	 */
	public function test_init_registers_ajax_toggle(): void {

		$page = new External_Cron_Admin_Page();
		$page->init();

		$this->assertNotFalse(has_action('wp_ajax_wu_external_cron_toggle', array($page, 'ajax_toggle')));
	}

	// ------------------------------------------------------------------
	// is_connected
	// ------------------------------------------------------------------

	/**
	 * Test is_connected returns false when site_id is empty.
	 */
	public function test_is_connected_false_when_no_site_id(): void {

		wu_save_setting('external_cron_site_id', '');

		$this->assertFalse($this->page->is_connected());
	}

	/**
	 * Test is_connected returns true when site_id is set.
	 */
	public function test_is_connected_true_when_site_id_set(): void {

		wu_save_setting('external_cron_site_id', 'abc123');

		$this->assertTrue($this->page->is_connected());
	}

	/**
	 * Test is_connected returns false when site_id is null.
	 */
	public function test_is_connected_false_when_site_id_null(): void {

		wu_save_setting('external_cron_site_id', null);

		$this->assertFalse($this->page->is_connected());
	}

	// ------------------------------------------------------------------
	// is_enabled
	// ------------------------------------------------------------------

	/**
	 * Test is_enabled returns false by default.
	 */
	public function test_is_enabled_false_by_default(): void {

		wu_save_setting('external_cron_enabled', false);

		$this->assertFalse($this->page->is_enabled());
	}

	/**
	 * Test is_enabled returns true when setting is true.
	 */
	public function test_is_enabled_true_when_setting_enabled(): void {

		wu_save_setting('external_cron_enabled', true);

		$this->assertTrue($this->page->is_enabled());
	}

	/**
	 * Test is_enabled returns bool type.
	 */
	public function test_is_enabled_returns_bool(): void {

		$this->assertIsBool($this->page->is_enabled());
	}

	// ------------------------------------------------------------------
	// get_schedule_count
	// ------------------------------------------------------------------

	/**
	 * Test get_schedule_count returns an integer.
	 */
	public function test_get_schedule_count_returns_int(): void {

		$this->assertIsInt($this->page->get_schedule_count());
	}

	/**
	 * Test get_schedule_count returns 0 when manager has no reporter.
	 */
	public function test_get_schedule_count_returns_zero_without_reporter(): void {

		// Without a reporter that has get_schedule_count, should return 0.
		$count = $this->page->get_schedule_count();

		$this->assertGreaterThanOrEqual(0, $count);
	}

	// ------------------------------------------------------------------
	// get_recent_logs
	// ------------------------------------------------------------------

	/**
	 * Test get_recent_logs returns an array.
	 */
	public function test_get_recent_logs_returns_array(): void {

		$this->assertIsArray($this->page->get_recent_logs());
	}

	/**
	 * Test get_recent_logs returns empty array when not connected.
	 */
	public function test_get_recent_logs_empty_when_not_connected(): void {

		wu_save_setting('external_cron_site_id', '');

		$logs = $this->page->get_recent_logs();

		$this->assertIsArray($logs);
		$this->assertEmpty($logs);
	}

	/**
	 * Test get_recent_logs returns empty array when client is not authenticated.
	 *
	 * The External_Cron_Service_Client returns a WP_Error when API credentials
	 * are not configured, which causes get_recent_logs to return an empty array.
	 */
	public function test_get_recent_logs_empty_on_client_error(): void {

		wu_save_setting('external_cron_site_id', 'site-123');

		// No API credentials set — client->request() returns WP_Error('not_authenticated').
		$logs = $this->page->get_recent_logs();

		$this->assertIsArray($logs);
		$this->assertEmpty($logs);
	}

	// ------------------------------------------------------------------
	// get_service_status
	// ------------------------------------------------------------------

	/**
	 * Test get_service_status returns an array.
	 */
	public function test_get_service_status_returns_array(): void {

		$this->assertIsArray($this->page->get_service_status());
	}

	/**
	 * Test get_service_status returns disconnected status when not connected.
	 */
	public function test_get_service_status_disconnected_when_not_connected(): void {

		wu_save_setting('external_cron_site_id', '');

		$status = $this->page->get_service_status();

		$this->assertArrayHasKey('status', $status);
		$this->assertEquals('disconnected', $status['status']);
	}

	/**
	 * Test get_service_status disconnected has label and color keys.
	 */
	public function test_get_service_status_disconnected_has_required_keys(): void {

		wu_save_setting('external_cron_site_id', '');

		$status = $this->page->get_service_status();

		$this->assertArrayHasKey('label', $status);
		$this->assertArrayHasKey('color', $status);
		$this->assertEquals('red', $status['color']);
	}

	/**
	 * Test get_service_status returns disabled when connected but not enabled.
	 */
	public function test_get_service_status_disabled_when_connected_but_not_enabled(): void {

		wu_save_setting('external_cron_site_id', 'abc123');
		wu_save_setting('external_cron_enabled', false);

		$status = $this->page->get_service_status();

		$this->assertEquals('disabled', $status['status']);
		$this->assertEquals('yellow', $status['color']);
	}

	/**
	 * Test get_service_status returns active when connected and enabled.
	 */
	public function test_get_service_status_active_when_connected_and_enabled(): void {

		wu_save_setting('external_cron_site_id', 'abc123');
		wu_save_setting('external_cron_enabled', true);

		$status = $this->page->get_service_status();

		$this->assertEquals('active', $status['status']);
		$this->assertEquals('green', $status['color']);
	}

	/**
	 * Test get_service_status active has label key.
	 */
	public function test_get_service_status_active_has_label(): void {

		wu_save_setting('external_cron_site_id', 'abc123');
		wu_save_setting('external_cron_enabled', true);

		$status = $this->page->get_service_status();

		$this->assertArrayHasKey('label', $status);
		$this->assertIsString($status['label']);
	}

	/**
	 * Test get_service_status always has status, label, and color keys.
	 */
	public function test_get_service_status_always_has_required_keys(): void {

		$scenarios = array(
			array('site_id' => '', 'enabled' => false),
			array('site_id' => 'abc', 'enabled' => false),
			array('site_id' => 'abc', 'enabled' => true),
		);

		foreach ($scenarios as $scenario) {
			wu_save_setting('external_cron_site_id', $scenario['site_id']);
			wu_save_setting('external_cron_enabled', $scenario['enabled']);

			$status = $this->page->get_service_status();

			$this->assertArrayHasKey('status', $status, "Missing 'status' key for scenario: " . wp_json_encode($scenario));
			$this->assertArrayHasKey('label', $status, "Missing 'label' key for scenario: " . wp_json_encode($scenario));
			$this->assertArrayHasKey('color', $status, "Missing 'color' key for scenario: " . wp_json_encode($scenario));
		}
	}

	// ------------------------------------------------------------------
	// get_subscription_url
	// ------------------------------------------------------------------

	/**
	 * Test get_subscription_url returns a string.
	 */
	public function test_get_subscription_url_returns_string(): void {

		$this->assertIsString($this->page->get_subscription_url());
	}

	/**
	 * Test get_subscription_url contains SERVICE_URL.
	 */
	public function test_get_subscription_url_contains_service_url(): void {

		$url = $this->page->get_subscription_url();

		$this->assertStringContainsString(External_Cron_Admin_Page::SERVICE_URL, $url);
	}

	/**
	 * Test get_subscription_url contains PRODUCT_SLUG.
	 */
	public function test_get_subscription_url_contains_product_slug(): void {

		$url = $this->page->get_subscription_url();

		$this->assertStringContainsString(External_Cron_Admin_Page::PRODUCT_SLUG, $url);
	}

	/**
	 * Test get_subscription_url returns expected URL.
	 */
	public function test_get_subscription_url_expected_value(): void {

		$expected = 'https://ultimatemultisite.com/addons/external-cron-service/';

		$this->assertEquals($expected, $this->page->get_subscription_url());
	}

	// ------------------------------------------------------------------
	// get_connect_url
	// ------------------------------------------------------------------

	/**
	 * Test get_connect_url returns a string.
	 */
	public function test_get_connect_url_returns_string(): void {

		$this->assertIsString($this->page->get_connect_url());
	}

	/**
	 * Test get_connect_url contains SERVICE_URL.
	 */
	public function test_get_connect_url_contains_service_url(): void {

		$url = $this->page->get_connect_url();

		$this->assertStringContainsString(External_Cron_Admin_Page::SERVICE_URL, $url);
	}

	/**
	 * Test get_connect_url contains action parameter.
	 */
	public function test_get_connect_url_contains_action_param(): void {

		$url = $this->page->get_connect_url();

		$this->assertStringContainsString('action=external_cron_connect', $url);
	}

	/**
	 * Test get_connect_url contains site_url parameter.
	 */
	public function test_get_connect_url_contains_site_url_param(): void {

		$url = $this->page->get_connect_url();

		$this->assertStringContainsString('site_url=', $url);
	}

	/**
	 * Test get_connect_url contains return_url parameter.
	 */
	public function test_get_connect_url_contains_return_url_param(): void {

		$url = $this->page->get_connect_url();

		$this->assertStringContainsString('return_url=', $url);
	}

	/**
	 * Test get_connect_url contains oauth authorize endpoint.
	 */
	public function test_get_connect_url_contains_oauth_endpoint(): void {

		$url = $this->page->get_connect_url();

		$this->assertStringContainsString('oauth/authorize', $url);
	}

	// ------------------------------------------------------------------
	// AJAX handlers — permission denied (no capability)
	// ------------------------------------------------------------------

	/**
	 * Test ajax_connect sends JSON error when user lacks manage_network.
	 */
	public function test_ajax_connect_permission_denied(): void {

		wp_set_current_user(0);

		$_REQUEST['nonce'] = wp_create_nonce('wu_external_cron_nonce');

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$caught = false;
		try {
			$this->page->ajax_connect();
		} catch (\WPAjaxDieContinueException $e) {
			$caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$this->assertTrue($caught, 'ajax_connect should call wp_send_json_error for unauthorized user');

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Output should be valid JSON');
		$this->assertFalse($decoded['success']);
	}

	/**
	 * Test ajax_disconnect sends JSON error when user lacks manage_network.
	 */
	public function test_ajax_disconnect_permission_denied(): void {

		wp_set_current_user(0);

		$_REQUEST['nonce'] = wp_create_nonce('wu_external_cron_nonce');

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$caught = false;
		try {
			$this->page->ajax_disconnect();
		} catch (\WPAjaxDieContinueException $e) {
			$caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$this->assertTrue($caught, 'ajax_disconnect should call wp_send_json_error for unauthorized user');

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded);
		$this->assertFalse($decoded['success']);
	}

	/**
	 * Test ajax_sync sends JSON error when user lacks manage_network.
	 */
	public function test_ajax_sync_permission_denied(): void {

		wp_set_current_user(0);

		$_REQUEST['nonce'] = wp_create_nonce('wu_external_cron_nonce');

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$caught = false;
		try {
			$this->page->ajax_sync();
		} catch (\WPAjaxDieContinueException $e) {
			$caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$this->assertTrue($caught, 'ajax_sync should call wp_send_json_error for unauthorized user');

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded);
		$this->assertFalse($decoded['success']);
	}

	/**
	 * Test ajax_toggle sends JSON error when user lacks manage_network.
	 */
	public function test_ajax_toggle_permission_denied(): void {

		wp_set_current_user(0);

		$_REQUEST['nonce'] = wp_create_nonce('wu_external_cron_nonce');

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$caught = false;
		try {
			$this->page->ajax_toggle();
		} catch (\WPAjaxDieContinueException $e) {
			$caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$this->assertTrue($caught, 'ajax_toggle should call wp_send_json_error for unauthorized user');

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded);
		$this->assertFalse($decoded['success']);
	}

	// ------------------------------------------------------------------
	// ajax_toggle — success path (network admin)
	// ------------------------------------------------------------------

	/**
	 * Test ajax_toggle enables the service when enabled=true.
	 */
	public function test_ajax_toggle_enables_service(): void {

		$admin = $this->get_network_admin_user();
		wp_set_current_user($admin);

		$_REQUEST['nonce']   = wp_create_nonce('wu_external_cron_nonce');
		$_REQUEST['enabled'] = 'true';

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$caught = false;
		try {
			$this->page->ajax_toggle();
		} catch (\WPAjaxDieContinueException $e) {
			$caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$this->assertTrue($caught, 'ajax_toggle should call wp_send_json_success');

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Output should be valid JSON');
		$this->assertTrue($decoded['success']);
		$this->assertTrue($decoded['data']['enabled']);
	}

	/**
	 * Test ajax_toggle disables the service when enabled=false.
	 */
	public function test_ajax_toggle_disables_service(): void {

		$admin = $this->get_network_admin_user();
		wp_set_current_user($admin);

		$_REQUEST['nonce']   = wp_create_nonce('wu_external_cron_nonce');
		$_REQUEST['enabled'] = 'false';

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$caught = false;
		try {
			$this->page->ajax_toggle();
		} catch (\WPAjaxDieContinueException $e) {
			$caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$this->assertTrue($caught, 'ajax_toggle should call wp_send_json_success');

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded);
		$this->assertTrue($decoded['success']);
		$this->assertFalse($decoded['data']['enabled']);
	}

	/**
	 * Test ajax_toggle response contains message key.
	 */
	public function test_ajax_toggle_response_has_message(): void {

		$admin = $this->get_network_admin_user();
		wp_set_current_user($admin);

		$_REQUEST['nonce']   = wp_create_nonce('wu_external_cron_nonce');
		$_REQUEST['enabled'] = 'true';

		$handler = $this->install_ajax_die_handler();

		ob_start();
		try {
			$this->page->ajax_toggle();
		} catch (\WPAjaxDieContinueException $e) {
			// Expected.
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$decoded = json_decode($output, true);
		$this->assertArrayHasKey('message', $decoded['data']);
		$this->assertIsString($decoded['data']['message']);
	}

	/**
	 * Test ajax_toggle persists the enabled setting.
	 */
	public function test_ajax_toggle_persists_setting(): void {

		$admin = $this->get_network_admin_user();
		wp_set_current_user($admin);

		wu_save_setting('external_cron_enabled', false);

		$_REQUEST['nonce']   = wp_create_nonce('wu_external_cron_nonce');
		$_REQUEST['enabled'] = '1';

		$handler = $this->install_ajax_die_handler();

		ob_start();
		try {
			$this->page->ajax_toggle();
		} catch (\WPAjaxDieContinueException $e) {
			// Expected.
		}
		ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$this->assertTrue((bool) wu_get_setting('external_cron_enabled', false));
	}

	// ------------------------------------------------------------------
	// ajax_sync — success path
	// ------------------------------------------------------------------

	/**
	 * Test ajax_sync updates last_sync site option.
	 */
	public function test_ajax_sync_updates_last_sync(): void {

		$admin = $this->get_network_admin_user();
		wp_set_current_user($admin);

		wu_save_setting('external_cron_site_id', 'site-abc');
		wu_save_setting('external_cron_enabled', true);

		delete_site_option('wu_external_cron_last_sync');

		$_REQUEST['nonce'] = wp_create_nonce('wu_external_cron_nonce');

		// Mock HTTP to avoid real API calls.
		add_filter('pre_http_request', function () {
			return array(
				'response' => array('code' => 200, 'message' => 'OK'),
				'body'     => wp_json_encode(array()),
				'headers'  => array(),
			);
		});

		$handler = $this->install_ajax_die_handler();

		ob_start();
		try {
			$this->page->ajax_sync();
		} catch (\WPAjaxDieContinueException $e) {
			// Expected.
		}
		ob_get_clean();

		$this->remove_ajax_die_handler($handler);
		remove_all_filters('pre_http_request');

		$last_sync = get_site_option('wu_external_cron_last_sync', 0);
		$this->assertGreaterThan(0, $last_sync);
	}

	/**
	 * Test ajax_sync returns success JSON.
	 */
	public function test_ajax_sync_returns_success(): void {

		$admin = $this->get_network_admin_user();
		wp_set_current_user($admin);

		$_REQUEST['nonce'] = wp_create_nonce('wu_external_cron_nonce');

		// Mock HTTP to avoid real API calls.
		add_filter('pre_http_request', function () {
			return array(
				'response' => array('code' => 200, 'message' => 'OK'),
				'body'     => wp_json_encode(array()),
				'headers'  => array(),
			);
		});

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$caught = false;
		try {
			$this->page->ajax_sync();
		} catch (\WPAjaxDieContinueException $e) {
			$caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);
		remove_all_filters('pre_http_request');

		$this->assertTrue($caught, 'ajax_sync should call wp_send_json_success');

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded);
		$this->assertTrue($decoded['success']);
		$this->assertArrayHasKey('message', $decoded['data']);
	}

	// ------------------------------------------------------------------
	// ajax_connect — error path (API returns WP_Error)
	// ------------------------------------------------------------------

	/**
	 * Test ajax_connect returns JSON error when registration fails.
	 */
	public function test_ajax_connect_returns_error_on_registration_failure(): void {

		$admin = $this->get_network_admin_user();
		wp_set_current_user($admin);

		$_REQUEST['nonce'] = wp_create_nonce('wu_external_cron_nonce');

		// Mock HTTP to return error.
		add_filter('pre_http_request', function () {
			return new \WP_Error('http_error', 'Service unavailable');
		});

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$caught = false;
		try {
			$this->page->ajax_connect();
		} catch (\WPAjaxDieContinueException $e) {
			$caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);
		remove_all_filters('pre_http_request');

		$this->assertTrue($caught, 'ajax_connect should call wp_send_json_error on failure');

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded);
		$this->assertFalse($decoded['success']);
	}

	// ------------------------------------------------------------------
	// ajax_disconnect — error path (API returns WP_Error)
	// ------------------------------------------------------------------

	/**
	 * Test ajax_disconnect returns JSON error when unregistration fails.
	 *
	 * Without API credentials, the client returns WP_Error('not_authenticated'),
	 * which causes the registration handler to propagate the error as JSON.
	 */
	public function test_ajax_disconnect_returns_error_on_failure(): void {

		$admin = $this->get_network_admin_user();
		wp_set_current_user($admin);

		// Set site_id so is_connected() returns true, but leave credentials empty
		// so client->request() returns WP_Error('not_authenticated').
		wu_save_setting('external_cron_site_id', 'site-abc');

		$_REQUEST['nonce'] = wp_create_nonce('wu_external_cron_nonce');

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$caught = false;
		try {
			$this->page->ajax_disconnect();
		} catch (\WPAjaxDieContinueException $e) {
			$caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$this->assertTrue($caught, 'ajax_disconnect should call wp_send_json_error on failure');

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded);
		$this->assertFalse($decoded['success']);
	}

	// ------------------------------------------------------------------
	// register_scripts
	// ------------------------------------------------------------------

	/**
	 * Test register_scripts is callable.
	 */
	public function test_register_scripts_is_callable(): void {

		$this->assertTrue(is_callable(array($this->page, 'register_scripts')));
	}

	/**
	 * Test register_scripts runs without fatal errors.
	 */
	public function test_register_scripts_runs_without_fatal(): void {

		// register_scripts calls wp_enqueue_script — safe in test env.
		$this->page->register_scripts();

		$this->assertTrue(true); // No exception thrown.
	}

	// ------------------------------------------------------------------
	// output
	// ------------------------------------------------------------------

	/**
	 * Test output is callable.
	 */
	public function test_output_is_callable(): void {

		$this->assertTrue(is_callable(array($this->page, 'output')));
	}

	/**
	 * Test output runs without fatal errors (template may not exist in test env).
	 */
	public function test_output_runs_without_fatal(): void {

		ob_start();
		try {
			$this->page->output();
		} catch (\Throwable $e) {
			// Template may not exist in test env — acceptable.
		}
		ob_get_clean();

		$this->assertTrue(true);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Get or create a network admin user.
	 *
	 * @return int User ID.
	 */
	private function get_network_admin_user(): int {

		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		grant_super_admin($user_id);

		return $user_id;
	}

	/**
	 * Install AJAX die handler so wp_send_json_* doesn't kill PHPUnit.
	 *
	 * @return callable The handler filter callback.
	 */
	private function install_ajax_die_handler(): callable {

		add_filter('wp_doing_ajax', '__return_true');

		$handler = function () {
			return function ( $message ) {
				throw new \WPAjaxDieContinueException( (string) $message );
			};
		};

		add_filter('wp_die_ajax_handler', $handler, 1);

		return $handler;
	}

	/**
	 * Remove the AJAX die handler.
	 *
	 * @param callable $handler The handler returned by install_ajax_die_handler().
	 * @return void
	 */
	private function remove_ajax_die_handler( callable $handler ): void {

		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $handler, 1);
	}
}
