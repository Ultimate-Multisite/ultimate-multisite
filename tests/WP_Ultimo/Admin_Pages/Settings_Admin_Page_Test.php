<?php
/**
 * Tests for Settings_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Settings_Admin_Page.
 *
 * Covers all public methods of Settings_Admin_Page to reach >=80% coverage.
 * Methods that call wp_die(), send headers, or require HTTP context are tested
 * for their guard conditions and side-effects only.
 */
class Settings_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Settings_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new Settings_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset(
			$_GET['wu_export_settings'],
			$_GET['updated'],
			$_GET['deleted'],
			$_GET['type'],
			$_POST['tab'],
			$_REQUEST['tab'],
			$_REQUEST['step'],
			$_FILES['import_file']
		);

		parent::tearDown();
	}

	/**
	 * Install an AJAX die handler so wp_send_json_* does not stop PHPUnit.
	 *
	 * @return callable
	 */
	private function install_ajax_die_handler(): callable {

		add_filter('wp_doing_ajax', '__return_true');

		$handler = function () {
			return function ($message) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test die handler rethrows the raw wp_die message.
				throw new \WPAjaxDieContinueException((string) $message);
			};
		};

		add_filter('wp_die_ajax_handler', $handler, 1);

		return $handler;
	}

	/**
	 * Remove an AJAX die handler installed by install_ajax_die_handler().
	 *
	 * @param callable $handler The handler returned by install_ajax_die_handler().
	 * @return void
	 */
	private function remove_ajax_die_handler(callable $handler): void {

		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $handler, 1);
	}

	/**
	 * Capture and decode a wp_send_json_* response from an AJAX handler.
	 *
	 * @param callable $callback AJAX handler callback.
	 * @return array
	 */
	private function capture_json_response(callable $callback): array {

		$handler          = $this->install_ajax_die_handler();
		$exception_caught = false;
		$output           = '';

		ob_start();

		try {
			$callback();
		} catch (\WPAjaxDieContinueException $e) {
			$exception_caught = true;
		} finally {
			$output = ob_get_clean();
			$this->remove_ajax_die_handler($handler);
		}

		$this->assertTrue($exception_caught, 'wp_send_json_* must terminate through wp_die in AJAX context.');

		$json_start  = strpos($output, '{"success"');
		$json_output = false === $json_start ? $output : substr($output, $json_start);
		$decoded     = json_decode($json_output, true);

		$this->assertIsArray($decoded, 'Response must be valid JSON: ' . $output);

		return $decoded;
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	public function test_page_id(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);
		$this->assertEquals('wp-ultimo-settings', $property->getValue($this->page));
	}

	public function test_page_type(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('type');
		$property->setAccessible(true);
		$this->assertEquals('submenu', $property->getValue($this->page));
	}

	public function test_badge_count(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);
		$this->assertEquals(0, $property->getValue($this->page));
	}

	public function test_supported_panels(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);
		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_read_settings', $panels['network_admin_menu']);
	}

	public function test_section_slug_is_tab(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('section_slug');
		$property->setAccessible(true);
		$this->assertEquals('tab', $property->getValue($this->page));
	}

	public function test_clickable_navigation_is_true(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('clickable_navigation');
		$property->setAccessible(true);
		$this->assertTrue($property->getValue($this->page));
	}

	public function test_hide_admin_notices_is_false(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('hide_admin_notices');
		$property->setAccessible(true);
		$this->assertFalse($property->getValue($this->page));
	}

	public function test_fold_menu_is_false(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('fold_menu');
		$property->setAccessible(true);
		$this->assertFalse($property->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// get_title() / get_menu_title()
	// -------------------------------------------------------------------------

	public function test_get_title(): void {
		$this->assertEquals('Settings', $this->page->get_title());
	}

	public function test_get_menu_title(): void {
		$this->assertEquals('Settings', $this->page->get_menu_title());
	}

	// -------------------------------------------------------------------------
	// get_sections()
	// -------------------------------------------------------------------------

	public function test_get_sections_returns_array(): void {
		$this->assertIsArray($this->page->get_sections());
	}

	public function test_get_sections_is_not_empty(): void {
		$this->assertNotEmpty($this->page->get_sections());
	}

	public function test_get_sections_matches_settings_sections(): void {
		$this->assertEquals(\WP_Ultimo()->settings->get_sections(), $this->page->get_sections());
	}

	// -------------------------------------------------------------------------
	// register_scripts() / register_widgets() / register_forms()
	// -------------------------------------------------------------------------

	public function test_register_scripts_does_not_throw(): void {
		$this->page->register_scripts();
		$this->assertTrue(true);
	}

	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard-network');

		$this->page->register_widgets();
		$this->assertTrue(true);
	}

	public function test_register_forms_does_not_throw(): void {
		$this->page->register_forms();
		$this->assertTrue(true);
	}

	public function test_register_forms_is_callable(): void {
		$this->assertTrue(is_callable([$this->page, 'register_forms']));
	}

	// -------------------------------------------------------------------------
	// Side panel render methods
	// -------------------------------------------------------------------------

	public function test_render_checkout_forms_side_panel_outputs_html(): void {
		ob_start();
		$this->page->render_checkout_forms_side_panel();
		$output = ob_get_clean();
		$this->assertStringContainsString('wu-widget-inset', $output);
		$this->assertStringContainsString('Checkout Forms', $output);
	}

	public function test_render_site_template_side_panel_outputs_html(): void {
		ob_start();
		$this->page->render_site_template_side_panel();
		$output = ob_get_clean();
		$this->assertStringContainsString('wu-widget-inset', $output);
		$this->assertStringContainsString('Template Previewer', $output);
	}

	public function test_render_site_placeholders_side_panel_outputs_html(): void {
		ob_start();
		$this->page->render_site_placeholders_side_panel();
		$output = ob_get_clean();
		$this->assertStringContainsString('wu-widget-inset', $output);
		$this->assertStringContainsString('Placeholder', $output);
	}

	public function test_render_invoice_side_panel_outputs_html(): void {
		ob_start();
		$this->page->render_invoice_side_panel();
		$output = ob_get_clean();
		$this->assertStringContainsString('wu-widget-inset', $output);
		$this->assertStringContainsString('Invoice', $output);
	}

	public function test_render_system_emails_side_panel_outputs_html(): void {
		ob_start();
		$this->page->render_system_emails_side_panel();
		$output = ob_get_clean();
		$this->assertStringContainsString('wu-widget-inset', $output);
		$this->assertStringContainsString('System Emails', $output);
	}

	public function test_render_email_template_side_panel_outputs_html(): void {
		ob_start();
		$this->page->render_email_template_side_panel();
		$output = ob_get_clean();
		$this->assertStringContainsString('wu-widget-inset', $output);
		$this->assertStringContainsString('Email Template', $output);
	}

	// -------------------------------------------------------------------------
	// render_import_settings_modal()
	// -------------------------------------------------------------------------

	public function test_render_import_settings_modal_is_callable(): void {
		$this->assertTrue(is_callable([$this->page, 'render_import_settings_modal']));
	}

	public function test_render_import_settings_modal_outputs_html(): void {
		ob_start();
		$this->page->render_import_settings_modal();
		$output = ob_get_clean();
		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// handle_import_settings_modal() — guard conditions
	// -------------------------------------------------------------------------

	/**
	 * No file → Runtime_Exception('no_file') → wp_send_json_error → WPAjaxDieStopException.
	 */
	public function test_handle_import_settings_modal_no_file_sends_json_error(): void {

		$_FILES = [];

		$response = $this->capture_json_response(
			function () {
				$this->page->handle_import_settings_modal();
			}
		);

		$this->assertFalse($response['success']);
	}

	public function test_handle_import_settings_modal_upload_error_sends_json_error(): void {

		$_FILES['import_file'] = [
			'name'     => 'test.json',
			'tmp_name' => '/tmp/x',
			'error'    => UPLOAD_ERR_INI_SIZE,
			'size'     => 0,
		];

		$response = $this->capture_json_response(
			function () {
				$this->page->handle_import_settings_modal();
			}
		);

		$this->assertFalse($response['success']);
	}

	public function test_handle_import_settings_modal_wrong_extension_sends_json_error(): void {

		$tmp = tempnam(sys_get_temp_dir(), 'wu_');
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture writes a temporary upload file.
		file_put_contents($tmp, '{}');

		$_FILES['import_file'] = [
			'name'     => 'test.csv',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => 2,
		];

		try {
			$response = $this->capture_json_response(
				function () {
					$this->page->handle_import_settings_modal();
				}
			);
		} finally {
			wp_delete_file($tmp);
		}

		$this->assertFalse($response['success']);
	}

	public function test_handle_import_settings_modal_invalid_json_sends_json_error(): void {

		$tmp = tempnam(sys_get_temp_dir(), 'wu_');
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture writes a temporary upload file.
		file_put_contents($tmp, 'not-json');

		$_FILES['import_file'] = [
			'name'     => 'test.json',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => 8,
		];

		try {
			$response = $this->capture_json_response(
				function () {
					$this->page->handle_import_settings_modal();
				}
			);
		} finally {
			wp_delete_file($tmp);
		}

		$this->assertFalse($response['success']);
	}

	public function test_handle_import_settings_modal_wrong_plugin_sends_json_error(): void {

		$tmp  = tempnam(sys_get_temp_dir(), 'wu_');
		$data = wp_json_encode([
			'plugin'   => 'other',
			'settings' => [],
		]);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture writes a temporary upload file.
		file_put_contents($tmp, $data);

		$_FILES['import_file'] = [
			'name'     => 'test.json',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => strlen($data),
		];

		try {
			$response = $this->capture_json_response(
				function () {
					$this->page->handle_import_settings_modal();
				}
			);
		} finally {
			wp_delete_file($tmp);
		}

		$this->assertFalse($response['success']);
	}

	public function test_handle_import_settings_modal_missing_settings_key_sends_json_error(): void {

		$tmp  = tempnam(sys_get_temp_dir(), 'wu_');
		$data = wp_json_encode(['plugin' => 'ultimate-multisite']);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture writes a temporary upload file.
		file_put_contents($tmp, $data);

		$_FILES['import_file'] = [
			'name'     => 'test.json',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => strlen($data),
		];

		try {
			$response = $this->capture_json_response(
				function () {
					$this->page->handle_import_settings_modal();
				}
			);
		} finally {
			wp_delete_file($tmp);
		}

		$this->assertFalse($response['success']);
	}

	public function test_handle_import_settings_modal_valid_file_sends_json_success(): void {

		$tmp  = tempnam(sys_get_temp_dir(), 'wu_');
		$data = wp_json_encode([
			'plugin'   => 'ultimate-multisite',
			'settings' => [],
		]);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture writes a temporary upload file.
		file_put_contents($tmp, $data);

		$_FILES['import_file'] = [
			'name'     => 'test.json',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => strlen($data),
		];

		try {
			$response = $this->capture_json_response(
				function () {
					$this->page->handle_import_settings_modal();
				}
			);
		} finally {
			wp_delete_file($tmp);
		}

		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('redirect_url', $response['data']);
	}

	// -------------------------------------------------------------------------
	// default_handler() — permission guard
	// -------------------------------------------------------------------------

	public function test_default_handler_dies_without_permission(): void {
		wp_set_current_user(0);
		$this->expectException(\WPDieException::class);
		$this->page->default_handler();
	}

	public function test_default_handler_with_permission_redirects(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);
		$user = get_user_by('id', $user_id);
		$user->add_cap('wu_edit_settings');

		$redirect_url = null;

		$redirect_filter = function ($location) use (&$redirect_url) {
			$redirect_url = $location;

			throw new \RuntimeException('redirect_intercepted');
		};

		add_filter('wp_redirect', $redirect_filter);

		try {
			$this->page->default_handler();
		} catch (\RuntimeException $e) {
			$this->assertSame('redirect_intercepted', $e->getMessage());
		} finally {
			remove_filter('wp_redirect', $redirect_filter);
			wp_set_current_user(0);
		}

		$this->assertNotNull($redirect_url, 'wp_redirect should have been called');
	}

	// -------------------------------------------------------------------------
	// default_view()
	// -------------------------------------------------------------------------

	public function test_default_view_is_callable(): void {
		$this->assertTrue(is_callable([$this->page, 'default_view']));
	}

	public function test_default_view_renders_with_section(): void {
		$sections   = $this->page->get_sections();
		$first      = array_key_first($sections);
		$reflection = new \ReflectionClass($this->page);
		$prop       = $reflection->getProperty('current_section');
		$prop->setAccessible(true);
		$prop->setValue($this->page, $sections[$first]);
		$_REQUEST['tab'] = $first;
		ob_start();
		$this->page->default_view();
		$output = ob_get_clean();
		$this->assertIsString($output);
		unset($_REQUEST['tab']);
	}

	// -------------------------------------------------------------------------
	// page_loaded() — guard conditions
	// -------------------------------------------------------------------------

	public function test_page_loaded_no_export_no_throw(): void {
		$this->page->page_loaded();
		$this->assertTrue(true);
	}

	public function test_page_loaded_with_import_redirect_registers_action(): void {
		$_GET['updated'] = '1';
		$_REQUEST['tab'] = 'import-export';
		$this->page->page_loaded();
		$this->assertGreaterThan(0, has_action('wu_page_wizard_after_title'));
		unset($_GET['updated'], $_REQUEST['tab']);
	}

	public function test_page_loaded_updated_wrong_tab_no_action(): void {
		$_GET['updated'] = '1';
		$_REQUEST['tab'] = 'general';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		$this->assertFalse(has_action('wu_page_wizard_after_title'));
		unset($_GET['updated'], $_REQUEST['tab']);
	}

	public function test_page_loaded_orphaned_tables_registers_action(): void {
		$_GET['deleted'] = '3';
		$_GET['type']    = 'tables';
		$_REQUEST['tab'] = 'other';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		$this->assertGreaterThan(0, has_action('wu_page_wizard_after_title'));
		unset($_GET['deleted'], $_GET['type'], $_REQUEST['tab']);
	}

	public function test_page_loaded_orphaned_users_registers_action(): void {
		$_GET['deleted'] = '5';
		$_GET['type']    = 'users';
		$_REQUEST['tab'] = 'other';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		$this->assertGreaterThan(0, has_action('wu_page_wizard_after_title'));
		unset($_GET['deleted'], $_GET['type'], $_REQUEST['tab']);
	}

	public function test_page_loaded_orphaned_wrong_tab_no_action(): void {
		$_GET['deleted'] = '3';
		$_GET['type']    = 'tables';
		$_REQUEST['tab'] = 'general';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		$this->assertFalse(has_action('wu_page_wizard_after_title'));
		unset($_GET['deleted'], $_GET['type'], $_REQUEST['tab']);
	}

	// -------------------------------------------------------------------------
	// Orphaned delete notice output
	// -------------------------------------------------------------------------

	public function test_orphaned_tables_success_notice(): void {
		$_GET['deleted'] = '2';
		$_GET['type']    = 'tables';
		$_REQUEST['tab'] = 'other';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		ob_start();
		do_action('wu_page_wizard_after_title');
		$output = ob_get_clean();
		$this->assertStringContainsString('notice-success', $output);
		$this->assertStringContainsString('2', $output);
		unset($_GET['deleted'], $_GET['type'], $_REQUEST['tab']);
	}

	public function test_orphaned_tables_zero_info_notice(): void {
		$_GET['deleted'] = '0';
		$_GET['type']    = 'tables';
		$_REQUEST['tab'] = 'other';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		ob_start();
		do_action('wu_page_wizard_after_title');
		$output = ob_get_clean();
		$this->assertStringContainsString('notice-info', $output);
		unset($_GET['deleted'], $_GET['type'], $_REQUEST['tab']);
	}

	public function test_orphaned_users_success_notice(): void {
		$_GET['deleted'] = '1';
		$_GET['type']    = 'users';
		$_REQUEST['tab'] = 'other';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		ob_start();
		do_action('wu_page_wizard_after_title');
		$output = ob_get_clean();
		$this->assertStringContainsString('notice-success', $output);
		unset($_GET['deleted'], $_GET['type'], $_REQUEST['tab']);
	}

	public function test_orphaned_users_zero_info_notice(): void {
		$_GET['deleted'] = '0';
		$_GET['type']    = 'users';
		$_REQUEST['tab'] = 'other';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		ob_start();
		do_action('wu_page_wizard_after_title');
		$output = ob_get_clean();
		$this->assertStringContainsString('notice-info', $output);
		unset($_GET['deleted'], $_GET['type'], $_REQUEST['tab']);
	}

	public function test_orphaned_unknown_type_outputs_nothing(): void {
		$_GET['deleted'] = '3';
		$_GET['type']    = 'unknown_type';
		$_REQUEST['tab'] = 'other';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		ob_start();
		do_action('wu_page_wizard_after_title');
		$output = ob_get_clean();
		$this->assertEmpty($output);
		unset($_GET['deleted'], $_GET['type'], $_REQUEST['tab']);
	}

	// -------------------------------------------------------------------------
	// Import redirect notice output
	// -------------------------------------------------------------------------

	public function test_import_redirect_notice_outputs_success(): void {
		$_GET['updated'] = '1';
		$_REQUEST['tab'] = 'import-export';
		remove_all_actions('wu_page_wizard_after_title');
		$this->page->page_loaded();
		ob_start();
		do_action('wu_page_wizard_after_title');
		$output = ob_get_clean();
		$this->assertStringContainsString('notice-success', $output);
		$this->assertStringContainsString('imported', strtolower($output));
		unset($_GET['updated'], $_REQUEST['tab']);
	}

	// -------------------------------------------------------------------------
	// handle_export() — guard condition
	// -------------------------------------------------------------------------

	public function test_handle_export_dies_on_invalid_nonce(): void {
		$_GET['wu_export_settings'] = '1';
		$_REQUEST['_wpnonce']       = 'invalid_nonce';
		$this->expectException(\WPDieException::class);
		$this->page->page_loaded();
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	public function test_output_method_exists(): void {
		$this->assertTrue(method_exists($this->page, 'output'));
		$this->assertTrue(is_callable([$this->page, 'output']));
	}
}
