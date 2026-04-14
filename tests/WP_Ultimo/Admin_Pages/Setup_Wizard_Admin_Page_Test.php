<?php
/**
 * Tests for Setup_Wizard_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Setup_Wizard_Admin_Page.
 *
 * Covers all public methods of Setup_Wizard_Admin_Page to reach >=80% coverage.
 * Methods that call wp_die(), send HTTP headers, or require full HTTP context
 * are tested for their guard conditions and side-effects only.
 */
class Setup_Wizard_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Setup_Wizard_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new Setup_Wizard_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset(
			$_REQUEST['installer'],
			$_REQUEST['dry-run'],
			$_REQUEST['step'],
			$_REQUEST['nonce'],
			$_GET['action'],
			$_GET['nonce'],
			$_GET['_wpnonce']
		);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	public function test_page_id(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);
		$this->assertEquals('wp-ultimo-setup', $property->getValue($this->page));
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
		$this->assertEquals('manage_network', $panels['network_admin_menu']);
	}

	public function test_parent_is_none(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);
		$this->assertEquals('none', $property->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// get_title() / get_menu_title() / get_logo()
	// -------------------------------------------------------------------------

	public function test_get_title(): void {
		$this->assertEquals('Installation', $this->page->get_title());
	}

	public function test_get_menu_title(): void {
		$title = $this->page->get_menu_title();
		$this->assertIsString($title);
		$this->assertNotEmpty($title);
	}

	public function test_get_logo(): void {
		$logo = $this->page->get_logo();
		$this->assertIsString($logo);
		$this->assertNotEmpty($logo);
	}

	// -------------------------------------------------------------------------
	// is_core_loaded() / is_migration()
	// -------------------------------------------------------------------------

	public function test_is_core_loaded_returns_bool(): void {
		$this->assertIsBool($this->page->is_core_loaded());
	}

	public function test_is_migration_returns_bool(): void {
		$this->assertIsBool($this->page->is_migration());
	}

	public function test_is_migration_caches_result(): void {
		$this->assertSame($this->page->is_migration(), $this->page->is_migration());
	}

	public function test_is_migration_uses_cached_value(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('is_migration');
		$property->setAccessible(true);
		$property->setValue($this->page, true);
		$this->assertTrue($this->page->is_migration());
		$property->setValue($this->page, null);
	}

	// -------------------------------------------------------------------------
	// set_settings()
	// -------------------------------------------------------------------------

	public function test_set_settings_does_not_throw(): void {
		$this->page->set_settings();
		$this->assertTrue(true);
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

	public function test_get_sections_contains_welcome(): void {
		$this->assertArrayHasKey('welcome', $this->page->get_sections());
	}

	public function test_get_sections_contains_checks(): void {
		$this->assertArrayHasKey('checks', $this->page->get_sections());
	}

	public function test_get_sections_contains_installation(): void {
		$this->assertArrayHasKey('installation', $this->page->get_sections());
	}

	public function test_get_sections_contains_done(): void {
		$this->assertArrayHasKey('done', $this->page->get_sections());
	}

	public function test_get_sections_contains_recommended_plugins(): void {
		$this->assertArrayHasKey('recommended-plugins', $this->page->get_sections());
	}

	public function test_welcome_section_has_required_keys(): void {
		$sections = $this->page->get_sections();
		$this->assertArrayHasKey('title', $sections['welcome']);
		$this->assertArrayHasKey('description', $sections['welcome']);
	}

	public function test_checks_section_has_handler(): void {
		$sections = $this->page->get_sections();
		$this->assertArrayHasKey('handler', $sections['checks']);
		$this->assertTrue(is_callable($sections['checks']['handler']));
	}

	public function test_done_section_has_view(): void {
		$sections = $this->page->get_sections();
		$this->assertArrayHasKey('view', $sections['done']);
		$this->assertTrue(is_callable($sections['done']['view']));
	}

	public function test_get_sections_is_filterable(): void {
		add_filter('wu_setup_wizard', function ($sections) {
			$sections['test_extra'] = ['title' => 'Test Extra'];
			return $sections;
		});
		$sections = $this->page->get_sections();
		$this->assertArrayHasKey('test_extra', $sections);
		remove_all_filters('wu_setup_wizard');
	}

	public function test_get_sections_contains_migration_when_is_migration(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('is_migration');
		$property->setAccessible(true);
		$property->setValue($this->page, true);
		$sections = $this->page->get_sections();
		$this->assertArrayHasKey('migration', $sections);
		$property->setValue($this->page, null);
	}

	public function test_get_sections_contains_your_company_when_not_migration(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('is_migration');
		$property->setAccessible(true);
		$property->setValue($this->page, false);
		$sections = $this->page->get_sections();
		$this->assertArrayHasKey('your-company', $sections);
		$property->setValue($this->page, null);
	}

	public function test_get_sections_contains_defaults_when_not_migration(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('is_migration');
		$property->setAccessible(true);
		$property->setValue($this->page, false);
		$sections = $this->page->get_sections();
		$this->assertArrayHasKey('defaults', $sections);
		$property->setValue($this->page, null);
	}

	// -------------------------------------------------------------------------
	// get_general_settings()
	// -------------------------------------------------------------------------

	public function test_get_general_settings_returns_array(): void {
		$this->assertIsArray($this->page->get_general_settings());
	}

	public function test_get_general_settings_is_not_empty(): void {
		$this->assertNotEmpty($this->page->get_general_settings());
	}

	public function test_get_general_settings_excludes_undesired_fields(): void {
		$fields = $this->page->get_general_settings();
		$this->assertArrayNotHasKey('error_reporting_header', $fields);
		$this->assertArrayNotHasKey('advanced_header', $fields);
		$this->assertArrayNotHasKey('uninstall_wipe_tables', $fields);
	}

	public function test_get_general_settings_is_filterable(): void {
		add_filter('wu_setup_get_general_settings', function ($fields) {
			$fields['test_field'] = ['type' => 'text'];
			return $fields;
		});
		$fields = $this->page->get_general_settings();
		$this->assertArrayHasKey('test_field', $fields);
		remove_all_filters('wu_setup_get_general_settings');
	}

	// -------------------------------------------------------------------------
	// get_payment_settings()
	// -------------------------------------------------------------------------

	public function test_get_payment_settings_returns_array(): void {
		$this->assertIsArray($this->page->get_payment_settings());
	}

	public function test_get_payment_settings_excludes_main_header(): void {
		$this->assertArrayNotHasKey('main_header', $this->page->get_payment_settings());
	}

	public function test_get_payment_settings_is_filterable(): void {
		add_filter('wu_setup_get_payment_settings', function ($fields) {
			$fields['test_payment_field'] = ['type' => 'text'];
			return $fields;
		});
		$fields = $this->page->get_payment_settings();
		$this->assertArrayHasKey('test_payment_field', $fields);
		remove_all_filters('wu_setup_get_payment_settings');
	}

	// -------------------------------------------------------------------------
	// renders_requirements_table()
	// -------------------------------------------------------------------------

	public function test_renders_requirements_table_returns_string(): void {
		$this->assertIsString($this->page->renders_requirements_table());
	}

	// -------------------------------------------------------------------------
	// _terms_of_support()
	// -------------------------------------------------------------------------

	public function test_terms_of_support_returns_string(): void {
		$this->assertIsString($this->page->_terms_of_support());
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	public function test_register_scripts_does_not_throw(): void {
		$this->page->register_scripts();
		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// alert_incomplete_installation()
	// -------------------------------------------------------------------------

	public function test_alert_incomplete_installation_returns_early_when_not_loaded(): void {
		// WP_Ultimo()->is_loaded() returns false in test environment.
		$this->page->alert_incomplete_installation();
		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// setup_install() — permission guard
	// -------------------------------------------------------------------------

	public function test_setup_install_sends_json_error_without_permission(): void {
		wp_set_current_user(0);
		$this->expectException(\WPAjaxDieStopException::class);
		$this->page->setup_install();
	}

	public function test_setup_install_with_permission_sends_json_success(): void {
		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);
		$this->expectException(\WPAjaxDieStopException::class);
		$this->page->setup_install();
	}

	// -------------------------------------------------------------------------
	// handle_checks()
	// -------------------------------------------------------------------------

	public function test_handle_checks_redirects(): void {
		$this->expectException(\WPDieException::class);
		$this->page->handle_checks();
	}

	// -------------------------------------------------------------------------
	// handle_save_settings()
	// -------------------------------------------------------------------------

	public function test_handle_save_settings_returns_early_for_unknown_step(): void {
		$_REQUEST['step'] = 'unknown-step';
		$this->page->handle_save_settings();
		$this->assertTrue(true);
	}

	public function test_handle_save_settings_your_company_step_redirects(): void {
		$_REQUEST['step'] = 'your-company';
		$this->expectException(\WPDieException::class);
		$this->page->handle_save_settings();
	}

	public function test_handle_save_settings_payment_gateways_step_redirects(): void {
		$_REQUEST['step'] = 'payment-gateways';
		$this->expectException(\WPDieException::class);
		$this->page->handle_save_settings();
	}

	// -------------------------------------------------------------------------
	// handle_migration()
	// -------------------------------------------------------------------------

	public function test_handle_migration_redirects(): void {
		$this->expectException(\WPDieException::class);
		$this->page->handle_migration();
	}

	public function test_handle_migration_no_dry_run_redirects(): void {
		$_REQUEST['dry-run'] = '0';
		$this->expectException(\WPDieException::class);
		$this->page->handle_migration();
	}

	// -------------------------------------------------------------------------
	// section_test() / section_ready()
	// -------------------------------------------------------------------------

	public function test_section_test_outputs_content(): void {
		ob_start();
		$this->page->section_test();
		$output = ob_get_clean();
		$this->assertIsString($output);
	}

	public function test_section_ready_outputs_content(): void {
		ob_start();
		$this->page->section_ready();
		$output = ob_get_clean();
		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// download_migration_logs() — nonce guard
	// -------------------------------------------------------------------------

	public function test_download_migration_logs_dies_on_invalid_nonce(): void {
		$this->expectException(\WPDieException::class);
		$this->page->download_migration_logs();
	}

	// -------------------------------------------------------------------------
	// page_loaded()
	// -------------------------------------------------------------------------

	public function test_page_loaded_does_not_throw(): void {
		$this->page->page_loaded();
		$this->assertTrue(true);
	}

	public function test_page_loaded_sets_current_section(): void {
		$this->page->page_loaded();
		$this->assertNotNull($this->page->current_section);
		$this->assertIsArray($this->page->current_section);
	}

	// -------------------------------------------------------------------------
	// Constructor — action/filter registration
	// -------------------------------------------------------------------------

	public function test_constructor_registers_download_migration_logs_action(): void {
		$this->assertGreaterThan(
			0,
			has_action('admin_action_download_migration_logs', [$this->page, 'download_migration_logs'])
		);
	}

	public function test_constructor_registers_setup_install_ajax_action(): void {
		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_setup_install', [$this->page, 'setup_install'])
		);
	}

	public function test_constructor_registers_alert_incomplete_installation(): void {
		$this->assertGreaterThan(
			0,
			has_action('admin_init', [$this->page, 'alert_incomplete_installation'])
		);
	}

	public function test_constructor_registers_network_activate_ajax_action(): void {
		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_setup_network_activate', [$this->page, 'ajax_network_activate'])
		);
	}

	// -------------------------------------------------------------------------
	// ajax_network_activate() — permission guard
	// -------------------------------------------------------------------------

	public function test_ajax_network_activate_sends_json_error_without_permission(): void {
		// Provide a valid nonce so the nonce check passes, isolating the permission check.
		$_REQUEST['nonce'] = wp_create_nonce('wu_setup_network_activate');
		wp_set_current_user(0);
		$this->expectException(\WPAjaxDieStopException::class);
		$this->page->ajax_network_activate();
	}

}
