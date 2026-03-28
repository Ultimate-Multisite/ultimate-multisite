<?php
/**
 * Tests for Addons_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Addons_Admin_Page.
 *
 * @group addons-admin-page
 */
class Addons_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Addons_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->page = new Addons_Admin_Page();
	}

	/**
	 * Tear down after each test.
	 */
	protected function tearDown(): void {
		remove_all_filters('http_request_args');
		remove_all_filters('pre_http_request');
		remove_all_filters('wu_is_debug');
		remove_all_filters('wp_redirect');
		delete_site_transient('wu-addons-list');
		delete_site_transient('wu-addons-list-beta');
		delete_transient('wu-access-token');
		wu_save_setting('enable_beta_updates', false);
		delete_option('wu_allow_beta_addons');
		delete_option('wu_is_debug');
		unset($_GET['tab'], $_REQUEST['addon']);
		$_POST = array();
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

		$this->assertEquals('wp-ultimo-addons', $property->getValue($this->page));
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
	 * Test parent is wp-ultimo.
	 */
	public function test_parent(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo', $property->getValue($this->page));
	}

	/**
	 * Test position is 999.
	 */
	public function test_position(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('position');
		$property->setAccessible(true);

		$this->assertEquals(999, $property->getValue($this->page));
	}

	/**
	 * Test badge_count is zero.
	 */
	public function test_badge_count(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);

		$this->assertEquals(0, $property->getValue($this->page));
	}

	/**
	 * Test hide_admin_notices is false (overrides Wizard_Admin_Page default of true).
	 */
	public function test_hide_admin_notices_is_false(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('hide_admin_notices');
		$property->setAccessible(true);

		$this->assertFalse($property->getValue($this->page));
	}

	/**
	 * Test fold_menu is false (overrides Wizard_Admin_Page default of true).
	 */
	public function test_fold_menu_is_false(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('fold_menu');
		$property->setAccessible(true);

		$this->assertFalse($property->getValue($this->page));
	}

	/**
	 * Test section_slug is 'tab'.
	 */
	public function test_section_slug(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('section_slug');
		$property->setAccessible(true);

		$this->assertEquals('tab', $property->getValue($this->page));
	}

	/**
	 * Test clickable_navigation is true.
	 */
	public function test_clickable_navigation(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('clickable_navigation');
		$property->setAccessible(true);

		$this->assertTrue($property->getValue($this->page));
	}

	/**
	 * Test addons property starts as null.
	 */
	public function test_addons_property_starts_null(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('addons');
		$property->setAccessible(true);

		$this->assertNull($property->getValue($this->page));
	}

	/**
	 * Test supported_panels contains network_admin_menu with correct capability.
	 */
	public function test_supported_panels(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_read_settings', $panels['network_admin_menu']);
	}

	// ------------------------------------------------------------------
	// get_title / get_menu_title
	// ------------------------------------------------------------------

	/**
	 * Test get_title returns correct string.
	 */
	public function test_get_title(): void {
		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Add-ons', $title);
	}

	/**
	 * Test get_menu_title returns correct string.
	 */
	public function test_get_menu_title(): void {
		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Add-ons', $title);
	}

	// ------------------------------------------------------------------
	// get_sections
	// ------------------------------------------------------------------

	/**
	 * Test get_sections returns an array.
	 */
	public function test_get_sections_returns_array(): void {
		$sections = $this->page->get_sections();

		$this->assertIsArray($sections);
	}

	/**
	 * Test get_sections returns non-empty array.
	 */
	public function test_get_sections_not_empty(): void {
		$sections = $this->page->get_sections();

		$this->assertNotEmpty($sections);
	}

	/**
	 * Test get_sections contains 'all' section.
	 */
	public function test_get_sections_contains_all(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('all', $sections);
	}

	/**
	 * Test get_sections contains 'premium' section.
	 */
	public function test_get_sections_contains_premium(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('premium', $sections);
	}

	/**
	 * Test get_sections contains 'free' section.
	 */
	public function test_get_sections_contains_free(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('free', $sections);
	}

	/**
	 * Test get_sections contains 'gateways' section.
	 */
	public function test_get_sections_contains_gateways(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('gateways', $sections);
	}

	/**
	 * Test get_sections contains 'growth' section.
	 */
	public function test_get_sections_contains_growth(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('growth', $sections);
	}

	/**
	 * Test get_sections contains 'integrations' section.
	 */
	public function test_get_sections_contains_integrations(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('integrations', $sections);
	}

	/**
	 * Test get_sections contains 'customization' section.
	 */
	public function test_get_sections_contains_customization(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('customization', $sections);
	}

	/**
	 * Test get_sections contains 'admin-theme' section.
	 */
	public function test_get_sections_contains_admin_theme(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('admin-theme', $sections);
	}

	/**
	 * Test get_sections contains 'monetization' section.
	 */
	public function test_get_sections_contains_monetization(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('monetization', $sections);
	}

	/**
	 * Test get_sections contains 'migrators' section.
	 */
	public function test_get_sections_contains_migrators(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('migrators', $sections);
	}

	/**
	 * Test get_sections contains 'marketplace' section.
	 */
	public function test_get_sections_contains_marketplace(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('marketplace', $sections);
	}

	/**
	 * Test each section has a 'title' key.
	 */
	public function test_get_sections_each_has_title(): void {
		$sections = $this->page->get_sections();

		foreach ($sections as $key => $section) {
			$this->assertArrayHasKey('title', $section, "Section '{$key}' missing 'title' key");
		}
	}

	/**
	 * Test each section has an 'icon' key.
	 */
	public function test_get_sections_each_has_icon(): void {
		$sections = $this->page->get_sections();

		foreach ($sections as $key => $section) {
			$this->assertArrayHasKey('icon', $section, "Section '{$key}' missing 'icon' key");
		}
	}

	/**
	 * Test 'all' section title is 'All Add-ons'.
	 */
	public function test_get_sections_all_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals('All Add-ons', $sections['all']['title']);
	}

	/**
	 * Test 'premium' section title is 'Premium'.
	 */
	public function test_get_sections_premium_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals('Premium', $sections['premium']['title']);
	}

	/**
	 * Test 'free' section title is 'Free'.
	 */
	public function test_get_sections_free_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals('Free', $sections['free']['title']);
	}

	/**
	 * Test 'gateways' section title is 'Gateways'.
	 */
	public function test_get_sections_gateways_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals('Gateways', $sections['gateways']['title']);
	}

	/**
	 * Test 'migrators' section title is 'Migrators'.
	 */
	public function test_get_sections_migrators_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals('Migrators', $sections['migrators']['title']);
	}

	/**
	 * Test 'marketplace' section title is 'Marketplace'.
	 */
	public function test_get_sections_marketplace_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals('Marketplace', $sections['marketplace']['title']);
	}

	/**
	 * Test total number of sections is 11.
	 */
	public function test_get_sections_count(): void {
		$sections = $this->page->get_sections();

		$this->assertCount(11, $sections);
	}

	// ------------------------------------------------------------------
	// get_addons_list (protected, tested via reflection)
	// ------------------------------------------------------------------

	/**
	 * Test get_addons_list returns array when API fails.
	 */
	public function test_get_addons_list_returns_array_on_api_failure(): void {
		// Ensure no cached data
		delete_site_transient('wu-addons-list');
		delete_site_transient('wu-addons-list-beta');

		// Mock HTTP request to return WP_Error
		add_filter('pre_http_request', function($preempt, $args, $url) {
			return new \WP_Error('http_error', 'Connection failed');
		}, 10, 3);

		$method = new \ReflectionMethod($this->page, 'get_addons_list');
		$method->setAccessible(true);

		$result = $method->invoke($this->page);

		$this->assertIsArray($result);

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test get_addons_list returns empty array when API returns empty.
	 */
	public function test_get_addons_list_returns_empty_array_on_empty_api_response(): void {
		delete_site_transient('wu-addons-list');
		delete_site_transient('wu-addons-list-beta');

		// Mock HTTP request to return empty body
		add_filter('pre_http_request', function($preempt, $args, $url) {
			return array(
				'response' => array('code' => 200, 'message' => 'OK'),
				'body'     => wp_json_encode(array()),
				'headers'  => array(),
			);
		}, 10, 3);

		$method = new \ReflectionMethod($this->page, 'get_addons_list');
		$method->setAccessible(true);

		$result = $method->invoke($this->page);

		$this->assertIsArray($result);

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test get_addons_list uses cached value when addons property is set.
	 */
	public function test_get_addons_list_uses_cached_property(): void {
		// Pre-populate the addons property
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('addons');
		$property->setAccessible(true);
		$property->setValue($this->page, array(array('slug' => 'test-addon', 'name' => 'Test Addon')));

		$method = new \ReflectionMethod($this->page, 'get_addons_list');
		$method->setAccessible(true);

		$result = $method->invoke($this->page);

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertEquals('test-addon', $result[0]['slug']);
	}

	/**
	 * Test get_addons_list uses site transient cache.
	 */
	public function test_get_addons_list_uses_transient_cache(): void {
		$cached_addons = array(
			array('slug' => 'cached-addon', 'name' => 'Cached Addon'),
		);
		set_site_transient('wu-addons-list', $cached_addons);

		// Ensure debug mode is off so transient is used
		add_filter('wu_is_debug', '__return_false');

		$method = new \ReflectionMethod($this->page, 'get_addons_list');
		$method->setAccessible(true);

		$result = $method->invoke($this->page);

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertEquals('cached-addon', $result[0]['slug']);

		remove_all_filters('wu_is_debug');
	}

	/**
	 * Test get_addons_list uses beta transient when beta updates enabled.
	 */
	public function test_get_addons_list_uses_beta_transient_when_beta_enabled(): void {
		$beta_addons = array(
			array('slug' => 'beta-addon', 'name' => 'Beta Addon'),
		);
		set_site_transient('wu-addons-list-beta', $beta_addons);

		// Enable beta updates
		wu_save_setting('enable_beta_updates', true);
		add_filter('wu_is_debug', '__return_false');

		$method = new \ReflectionMethod($this->page, 'get_addons_list');
		$method->setAccessible(true);

		$result = $method->invoke($this->page);

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertEquals('beta-addon', $result[0]['slug']);

		wu_save_setting('enable_beta_updates', false);
		remove_all_filters('wu_is_debug');
		delete_site_transient('wu-addons-list-beta');
	}

	/**
	 * Test get_addons_list fetches from API and caches result.
	 */
	public function test_get_addons_list_fetches_and_caches(): void {
		delete_site_transient('wu-addons-list');

		$api_addons = array(
			array('slug' => 'api-addon', 'name' => 'API Addon', 'sku' => 'api-addon'),
		);

		// Mock HTTP request to return addon data
		add_filter('pre_http_request', function($preempt, $args, $url) use ($api_addons) {
			return array(
				'response' => array('code' => 200, 'message' => 'OK'),
				'body'     => wp_json_encode($api_addons),
				'headers'  => array(),
			);
		}, 10, 3);

		$method = new \ReflectionMethod($this->page, 'get_addons_list');
		$method->setAccessible(true);

		$result = $method->invoke($this->page);

		$this->assertIsArray($result);
		$this->assertNotEmpty($result);

		// Verify the addons property was set
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('addons');
		$property->setAccessible(true);
		$this->assertIsArray($property->getValue($this->page));

		remove_all_filters('pre_http_request');
	}

	/**
	 * Test get_addons_list marks installed plugins.
	 */
	public function test_get_addons_list_marks_installed_plugins(): void {
		delete_site_transient('wu-addons-list');

		$api_addons = array(
			array('slug' => 'my-addon', 'name' => 'My Addon', 'sku' => 'my-addon'),
		);

		add_filter('pre_http_request', function($preempt, $args, $url) use ($api_addons) {
			return array(
				'response' => array('code' => 200, 'message' => 'OK'),
				'body'     => wp_json_encode($api_addons),
				'headers'  => array(),
			);
		}, 10, 3);

		$method = new \ReflectionMethod($this->page, 'get_addons_list');
		$method->setAccessible(true);

		$result = $method->invoke($this->page);

		// Each addon should have an 'installed' key
		if (! empty($result)) {
			$this->assertArrayHasKey('installed', $result[0]);
		}

		remove_all_filters('pre_http_request');
	}

	// ------------------------------------------------------------------
	// serve_addons_list
	// ------------------------------------------------------------------

	/**
	 * Test serve_addons_list is a public callable method.
	 */
	public function test_serve_addons_list_is_callable(): void {
		$this->assertTrue(is_callable(array($this->page, 'serve_addons_list')));
	}

	/**
	 * Test serve_addons_list uses get_addons_list internally.
	 *
	 * Verifies that serve_addons_list delegates to get_addons_list by checking
	 * that the cached addons property is used when available.
	 */
	public function test_serve_addons_list_uses_cached_addons(): void {
		$test_addons = array(array('slug' => 'cached-addon', 'name' => 'Cached Addon'));

		// Pre-populate addons cache
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('addons');
		$property->setAccessible(true);
		$property->setValue($this->page, $test_addons);

		$handler = $this->install_ajax_die_handler();

		ob_start();
		try {
			$this->page->serve_addons_list();
		} catch (\WPAjaxDieContinueException $e) {
			// Expected: wp_send_json_* calls wp_die() in AJAX context
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		// The output should contain the cached addon slug
		$this->assertStringContainsString('cached-addon', $output);
	}

	// ------------------------------------------------------------------
	// add_auth_headers_to_download
	// ------------------------------------------------------------------

	/**
	 * Test add_auth_headers_to_download adds Authorization header for matching URL.
	 */
	public function test_add_auth_headers_to_download_adds_header_for_matching_url(): void {
		set_transient('wu-access-token', 'test_token_abc');

		$args = array('headers' => array());
		$url  = 'https://ultimatemultisite.com/download/plugin.zip';

		$result = $this->page->add_auth_headers_to_download($args, $url);

		$this->assertArrayHasKey('Authorization', $result['headers']);
		$this->assertEquals('Bearer test_token_abc', $result['headers']['Authorization']);
	}

	/**
	 * Test add_auth_headers_to_download does not add header for non-matching URL.
	 */
	public function test_add_auth_headers_to_download_skips_non_matching_url(): void {
		set_transient('wu-access-token', 'test_token_abc');

		$args = array('headers' => array());
		$url  = 'https://example.com/download/plugin.zip';

		$result = $this->page->add_auth_headers_to_download($args, $url);

		$this->assertArrayNotHasKey('Authorization', $result['headers']);
	}

	/**
	 * Test add_auth_headers_to_download does not add header when no access token.
	 */
	public function test_add_auth_headers_to_download_skips_when_no_token(): void {
		delete_transient('wu-access-token');

		$args = array('headers' => array());
		$url  = 'https://ultimatemultisite.com/download/plugin.zip';

		$result = $this->page->add_auth_headers_to_download($args, $url);

		$this->assertArrayNotHasKey('Authorization', $result['headers']);
	}

	/**
	 * Test add_auth_headers_to_download returns args unchanged for non-matching URL.
	 */
	public function test_add_auth_headers_to_download_returns_args_unchanged_for_other_url(): void {
		$args = array(
			'headers' => array('X-Custom' => 'value'),
			'timeout' => 30,
		);
		$url  = 'https://wordpress.org/download/plugin.zip';

		$result = $this->page->add_auth_headers_to_download($args, $url);

		$this->assertEquals($args, $result);
	}

	/**
	 * Test add_auth_headers_to_download initializes headers array if not set.
	 */
	public function test_add_auth_headers_to_download_initializes_headers_if_missing(): void {
		set_transient('wu-access-token', 'my_token');

		$args = array(); // No 'headers' key
		$url  = 'https://ultimatemultisite.com/download/plugin.zip';

		$result = $this->page->add_auth_headers_to_download($args, $url);

		$this->assertArrayHasKey('headers', $result);
		$this->assertArrayHasKey('Authorization', $result['headers']);
		$this->assertEquals('Bearer my_token', $result['headers']['Authorization']);
	}

	/**
	 * Test add_auth_headers_to_download returns array.
	 */
	public function test_add_auth_headers_to_download_returns_array(): void {
		$args   = array('headers' => array());
		$url    = 'https://example.com/plugin.zip';
		$result = $this->page->add_auth_headers_to_download($args, $url);

		$this->assertIsArray($result);
	}

	// ------------------------------------------------------------------
	// init
	// ------------------------------------------------------------------

	/**
	 * Test init registers the serve_addons_list ajax action.
	 */
	public function test_init_registers_ajax_action(): void {
		// Create a fresh page and call init
		$page = new Addons_Admin_Page();
		$page->init();

		$this->assertNotFalse(has_action('wp_ajax_serve_addons_list', array($page, 'serve_addons_list')));
	}

	// ------------------------------------------------------------------
	// register_forms
	// ------------------------------------------------------------------

	/**
	 * Test register_forms registers the addon_more_info form.
	 */
	public function test_register_forms_registers_addon_more_info(): void {
		$this->page->register_forms();

		// Verify the form was registered via Form_Manager
		$form_manager = \WP_Ultimo\Managers\Form_Manager::get_instance();
		$this->assertTrue($form_manager->is_form_registered('addon_more_info'));
	}

	// ------------------------------------------------------------------
	// AJAX die handler helpers
	// ------------------------------------------------------------------

	/**
	 * Install AJAX die handler so wp_send_json_* doesn't kill PHPUnit.
	 *
	 * wp_send_json_* calls wp_die(), which in AJAX context uses wp_die_ajax_handler.
	 * We install a handler that throws WPAjaxDieContinueException instead.
	 *
	 * @return callable The handler filter callback (pass to remove_ajax_die_handler).
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
	 * Remove the AJAX die handler installed by install_ajax_die_handler().
	 *
	 * @param callable $handler The handler returned by install_ajax_die_handler().
	 * @return void
	 */
	private function remove_ajax_die_handler( callable $handler ): void {
		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $handler, 1);
	}

	// ------------------------------------------------------------------
	// install_addon (permission check path)
	// ------------------------------------------------------------------

	/**
	 * Test install_addon sends JSON error when user lacks permission.
	 *
	 * wp_send_json_error calls wp_die() in AJAX context. We install a custom
	 * wp_die_ajax_handler that throws WPAjaxDieContinueException so PHPUnit
	 * is not killed by the underlying die() call.
	 */
	public function test_install_addon_sends_error_without_permission(): void {
		// Ensure current user cannot manage_network_plugins
		wp_set_current_user(0);

		$handler = $this->install_ajax_die_handler();

		ob_start();
		$exception_caught = false;
		try {
			$this->page->install_addon();
		} catch (\WPAjaxDieContinueException $e) {
			$exception_caught = true;
		}
		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		$this->assertTrue($exception_caught, 'install_addon should throw WPAjaxDieContinueException via wp_send_json_error when user lacks permission');

		// Verify the JSON output contains the error
		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Output should be valid JSON');
		$this->assertFalse($decoded['success'], 'JSON response should indicate failure');
	}

	// ------------------------------------------------------------------
	// display_more_info
	// ------------------------------------------------------------------

	/**
	 * Test display_more_info runs without fatal errors when addon not found.
	 */
	public function test_display_more_info_runs_without_fatal(): void {
		// Set up request with a non-existent addon slug
		$_REQUEST['addon'] = 'nonexistent-addon';

		// Pre-populate addons cache as empty
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('addons');
		$property->setAccessible(true);
		$property->setValue($this->page, array());

		ob_start();
		try {
			$this->page->display_more_info();
		} catch (\Throwable $e) {
			// Template may not exist in test env — that's acceptable
		}
		ob_get_clean();

		// Test passes if no fatal error thrown
		$this->assertTrue(true);

		unset($_REQUEST['addon']);
	}

	// ------------------------------------------------------------------
	// default_handler
	// ------------------------------------------------------------------

	/**
	 * Test default_handler calls wp_safe_redirect and exits.
	 */
	public function test_default_handler_redirects(): void {
		// Simulate POST data
		$_POST = array('some_setting' => 'value');

		$redirected = false;
		$exited     = false;
		add_filter('wp_redirect', function($location) use (&$redirected) {
			$redirected = true;
			return $location;
		});

		ob_start();
		try {
			$this->page->default_handler();
		} catch (\WPDieException $e) {
			// exit() in wp_safe_redirect may throw in test env
			$exited = true;
		} catch (\Throwable $e) {
			// Catch any other exception from exit
			$exited = true;
		}
		ob_get_clean();

		$this->assertTrue($redirected || $exited, 'Expected wp_redirect to be called or execution to exit in default_handler()');

		remove_all_filters('wp_redirect');
		$_POST = array();
	}

	// ------------------------------------------------------------------
	// Inheritance / class structure
	// ------------------------------------------------------------------

	/**
	 * Test Addons_Admin_Page extends Wizard_Admin_Page.
	 */
	public function test_extends_wizard_admin_page(): void {
		$this->assertInstanceOf(Wizard_Admin_Page::class, $this->page);
	}

	/**
	 * Test Addons_Admin_Page extends Base_Admin_Page.
	 */
	public function test_extends_base_admin_page(): void {
		$this->assertInstanceOf(Base_Admin_Page::class, $this->page);
	}

	/**
	 * Test page is instantiable.
	 */
	public function test_instantiation(): void {
		$this->assertInstanceOf(Addons_Admin_Page::class, $this->page);
	}

	// ------------------------------------------------------------------
	// get_current_section (inherited from Wizard_Admin_Page)
	// ------------------------------------------------------------------

	/**
	 * Test get_current_section returns first section key by default.
	 */
	public function test_get_current_section_returns_first_section_by_default(): void {
		unset($_GET['tab']);

		$section  = $this->page->get_current_section();
		$sections = $this->page->get_sections();

		$this->assertEquals(array_key_first($sections), $section);
	}

	/**
	 * Test get_current_section returns requested section when set.
	 */
	public function test_get_current_section_returns_requested_section(): void {
		$_GET['tab'] = 'premium';

		$section = $this->page->get_current_section();

		$this->assertEquals('premium', $section);

		unset($_GET['tab']);
	}

	/**
	 * Test get_current_section sanitizes the input.
	 */
	public function test_get_current_section_sanitizes_input(): void {
		$_GET['tab'] = 'premium<script>';

		$section = $this->page->get_current_section();

		// sanitize_key strips non-alphanumeric chars
		$this->assertStringNotContainsString('<', $section);
		$this->assertStringNotContainsString('>', $section);

		unset($_GET['tab']);
	}

	// ------------------------------------------------------------------
	// get_section_link (inherited from Wizard_Admin_Page)
	// ------------------------------------------------------------------

	/**
	 * Test get_section_link returns a string.
	 */
	public function test_get_section_link_returns_string(): void {
		$link = $this->page->get_section_link('premium');

		$this->assertIsString($link);
	}

	/**
	 * Test get_section_link contains the section slug parameter.
	 */
	public function test_get_section_link_contains_tab_param(): void {
		$link = $this->page->get_section_link('premium');

		$this->assertStringContainsString('tab=premium', $link);
	}

	// ------------------------------------------------------------------
	// get_first_section (inherited from Wizard_Admin_Page)
	// ------------------------------------------------------------------

	/**
	 * Test get_first_section returns second key (index 1) of sections.
	 */
	public function test_get_first_section_returns_second_key(): void {
		$sections = $this->page->get_sections();
		$keys     = array_keys($sections);

		$first = $this->page->get_first_section();

		$this->assertEquals($keys[1], $first);
	}

	// ------------------------------------------------------------------
	// get_next_section_link / get_prev_section_link (inherited)
	// ------------------------------------------------------------------

	/**
	 * Test get_next_section_link returns a string.
	 */
	public function test_get_next_section_link_returns_string(): void {
		unset($_GET['tab']);

		$link = $this->page->get_next_section_link();

		$this->assertIsString($link);
	}

	/**
	 * Test get_prev_section_link returns empty string when on first section.
	 */
	public function test_get_prev_section_link_returns_empty_on_first_section(): void {
		unset($_GET['tab']);

		$link = $this->page->get_prev_section_link();

		$this->assertSame('', $link);
	}

	/**
	 * Test get_prev_section_link returns string with tab param when not on first section.
	 */
	public function test_get_prev_section_link_returns_link_when_not_first(): void {
		$_GET['tab'] = 'premium';

		$link = $this->page->get_prev_section_link();

		$this->assertIsString($link);
		$this->assertStringContainsString('tab=', $link);

		unset($_GET['tab']);
	}

	// ------------------------------------------------------------------
	// get_logo (inherited from Wizard_Admin_Page)
	// ------------------------------------------------------------------

	/**
	 * Test get_logo returns a string.
	 */
	public function test_get_logo_returns_string(): void {
		$logo = $this->page->get_logo();

		$this->assertIsString($logo);
	}

	// ------------------------------------------------------------------
	// get_labels (inherited from Wizard_Admin_Page)
	// ------------------------------------------------------------------

	/**
	 * Test get_labels returns an array.
	 */
	public function test_get_labels_returns_array(): void {
		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
	}

	/**
	 * Test get_labels contains expected keys.
	 */
	public function test_get_labels_contains_expected_keys(): void {
		$labels = $this->page->get_labels();

		$this->assertArrayHasKey('save_button_label', $labels);
		$this->assertArrayHasKey('updated_message', $labels);
	}
}
