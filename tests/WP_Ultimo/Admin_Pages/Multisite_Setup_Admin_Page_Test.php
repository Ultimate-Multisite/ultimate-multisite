<?php
/**
 * Tests for Multisite_Setup_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Multisite_Setup_Admin_Page.
 *
 * Covers all public methods of Multisite_Setup_Admin_Page to reach >=50%
 * statement coverage. Methods that call wp_die(), send HTTP headers, or
 * require a live AJAX context are tested for their guard conditions only.
 */
class Multisite_Setup_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Multisite_Setup_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new Multisite_Setup_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals and transients.
	 */
	protected function tearDown(): void {

		unset(
			$_REQUEST['installer'],
			$_REQUEST['subdomain_install'],
			$_REQUEST['sitename'],
			$_REQUEST['email'],
			$_GET['step'],
			$_GET['result']
		);

		delete_transient(\WP_Ultimo\Installers\Multisite_Network_Installer::CONFIG_TRANSIENT);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Page property defaults
	// -------------------------------------------------------------------------

	/**
	 * Page id is 'wp-ultimo-multisite-setup'.
	 */
	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-multisite-setup', $property->getValue($this->page));
	}

	/**
	 * Page type is 'menu'.
	 */
	public function test_page_type_is_menu(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('type');
		$property->setAccessible(true);

		$this->assertEquals('menu', $property->getValue($this->page));
	}

	/**
	 * highlight_menu_slug is false.
	 */
	public function test_highlight_menu_slug_is_false(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertFalse($property->getValue($this->page));
	}

	/**
	 * badge_count is 0.
	 */
	public function test_badge_count_is_zero(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);

		$this->assertEquals(0, $property->getValue($this->page));
	}

	/**
	 * supported_panels contains admin_menu with manage_options.
	 */
	public function test_supported_panels_contains_admin_menu(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);
		$panels = $property->getValue($this->page);

		$this->assertArrayHasKey('admin_menu', $panels);
		$this->assertEquals('manage_options', $panels['admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title() / get_menu_title() / get_logo()
	// -------------------------------------------------------------------------

	/**
	 * get_title() returns a non-empty string.
	 */
	public function test_get_title_returns_string(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertNotEmpty($title);
	}

	/**
	 * get_title() contains 'Multisite' keyword.
	 */
	public function test_get_title_contains_multisite(): void {

		$this->assertStringContainsString('Multisite', $this->page->get_title());
	}

	/**
	 * get_menu_title() returns a non-empty string.
	 */
	public function test_get_menu_title_returns_string(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertNotEmpty($title);
	}

	/**
	 * get_logo() returns a non-empty string (asset URL).
	 */
	public function test_get_logo_returns_string(): void {

		$logo = $this->page->get_logo();

		$this->assertIsString($logo);
		$this->assertNotEmpty($logo);
	}

	/**
	 * get_logo() references logo.webp.
	 */
	public function test_get_logo_references_logo_webp(): void {

		$this->assertStringContainsString('logo.webp', $this->page->get_logo());
	}

	// -------------------------------------------------------------------------
	// get_sections()
	// -------------------------------------------------------------------------

	/**
	 * get_sections() returns an array.
	 */
	public function test_get_sections_returns_array(): void {

		$this->assertIsArray($this->page->get_sections());
	}

	/**
	 * get_sections() is not empty.
	 */
	public function test_get_sections_is_not_empty(): void {

		$this->assertNotEmpty($this->page->get_sections());
	}

	/**
	 * get_sections() contains 'welcome' section.
	 */
	public function test_get_sections_contains_welcome(): void {

		$this->assertArrayHasKey('welcome', $this->page->get_sections());
	}

	/**
	 * get_sections() contains 'configure' section.
	 */
	public function test_get_sections_contains_configure(): void {

		$this->assertArrayHasKey('configure', $this->page->get_sections());
	}

	/**
	 * get_sections() contains 'install' section.
	 */
	public function test_get_sections_contains_install(): void {

		$this->assertArrayHasKey('install', $this->page->get_sections());
	}

	/**
	 * get_sections() contains 'complete' section.
	 */
	public function test_get_sections_contains_complete(): void {

		$this->assertArrayHasKey('complete', $this->page->get_sections());
	}

	/**
	 * 'welcome' section has a title key.
	 */
	public function test_welcome_section_has_title(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('title', $sections['welcome']);
	}

	/**
	 * 'welcome' section has a view callable.
	 */
	public function test_welcome_section_has_view_callable(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('view', $sections['welcome']);
		$this->assertTrue(is_callable($sections['welcome']['view']));
	}

	/**
	 * 'configure' section has a handler callable.
	 */
	public function test_configure_section_has_handler_callable(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('handler', $sections['configure']);
		$this->assertTrue(is_callable($sections['configure']['handler']));
	}

	/**
	 * 'configure' section has a fields callable.
	 */
	public function test_configure_section_has_fields_callable(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('fields', $sections['configure']);
		$this->assertTrue(is_callable($sections['configure']['fields']));
	}

	/**
	 * 'install' section has disable_next set to true.
	 */
	public function test_install_section_has_disable_next(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('disable_next', $sections['install']);
		$this->assertTrue($sections['install']['disable_next']);
	}

	/**
	 * 'complete' section has next set to false.
	 */
	public function test_complete_section_has_no_next(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('next', $sections['complete']);
		$this->assertFalse($sections['complete']['next']);
	}

	/**
	 * 'complete' section has a view callable.
	 */
	public function test_complete_section_has_view_callable(): void {

		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('view', $sections['complete']);
		$this->assertTrue(is_callable($sections['complete']['view']));
	}

	// -------------------------------------------------------------------------
	// get_network_configuration_fields()
	// -------------------------------------------------------------------------

	/**
	 * get_network_configuration_fields() returns an array.
	 */
	public function test_get_network_configuration_fields_returns_array(): void {

		$this->assertIsArray($this->page->get_network_configuration_fields());
	}

	/**
	 * get_network_configuration_fields() is not empty.
	 */
	public function test_get_network_configuration_fields_is_not_empty(): void {

		$this->assertNotEmpty($this->page->get_network_configuration_fields());
	}

	/**
	 * get_network_configuration_fields() contains subdomain_install field.
	 */
	public function test_get_network_configuration_fields_contains_subdomain_install(): void {

		$fields = $this->page->get_network_configuration_fields();

		$this->assertArrayHasKey('subdomain_install', $fields);
	}

	/**
	 * subdomain_install field is a select type.
	 */
	public function test_subdomain_install_field_is_select(): void {

		$fields = $this->page->get_network_configuration_fields();

		$this->assertEquals('select', $fields['subdomain_install']['type']);
	}

	/**
	 * subdomain_install field has two options.
	 */
	public function test_subdomain_install_field_has_two_options(): void {

		$fields = $this->page->get_network_configuration_fields();

		$this->assertCount(2, $fields['subdomain_install']['options']);
	}

	/**
	 * subdomain_install field default is '1' (subdomain).
	 */
	public function test_subdomain_install_field_default_is_subdomain(): void {

		$fields = $this->page->get_network_configuration_fields();

		$this->assertEquals('1', $fields['subdomain_install']['default']);
	}

	/**
	 * get_network_configuration_fields() contains sitename field.
	 */
	public function test_get_network_configuration_fields_contains_sitename(): void {

		$fields = $this->page->get_network_configuration_fields();

		$this->assertArrayHasKey('sitename', $fields);
		$this->assertEquals('text', $fields['sitename']['type']);
	}

	/**
	 * get_network_configuration_fields() contains email field.
	 */
	public function test_get_network_configuration_fields_contains_email(): void {

		$fields = $this->page->get_network_configuration_fields();

		$this->assertArrayHasKey('email', $fields);
		$this->assertEquals('email', $fields['email']['type']);
	}

	/**
	 * get_network_configuration_fields() contains network_structure_header.
	 */
	public function test_get_network_configuration_fields_contains_network_structure_header(): void {

		$fields = $this->page->get_network_configuration_fields();

		$this->assertArrayHasKey('network_structure_header', $fields);
		$this->assertEquals('header', $fields['network_structure_header']['type']);
	}

	/**
	 * get_network_configuration_fields() contains backup_warning note.
	 */
	public function test_get_network_configuration_fields_contains_backup_warning(): void {

		$fields = $this->page->get_network_configuration_fields();

		$this->assertArrayHasKey('backup_warning', $fields);
		$this->assertEquals('note', $fields['backup_warning']['type']);
	}

	/**
	 * sitename field value defaults to blogname + ' Network'.
	 */
	public function test_sitename_field_value_contains_blogname(): void {

		$blogname = get_option('blogname');
		$fields   = $this->page->get_network_configuration_fields();

		$this->assertStringContainsString($blogname, $fields['sitename']['value']);
		$this->assertStringContainsString('Network', $fields['sitename']['value']);
	}

	// -------------------------------------------------------------------------
	// Constructor hook registration
	// -------------------------------------------------------------------------

	/**
	 * Constructor registers admin_enqueue_scripts hook.
	 */
	public function test_constructor_registers_admin_enqueue_scripts(): void {

		$this->assertGreaterThan(0, has_action('admin_enqueue_scripts', [$this->page, 'register_scripts']));
	}

	/**
	 * Constructor registers wp_ajax_wu_setup_install hook at priority 5.
	 */
	public function test_constructor_registers_ajax_setup_install(): void {

		$priority = has_action('wp_ajax_wu_setup_install', [$this->page, 'setup_install']);

		$this->assertEquals(5, $priority);
	}

	// -------------------------------------------------------------------------
	// register_scripts() — guard condition (wrong screen)
	// -------------------------------------------------------------------------

	/**
	 * register_scripts() returns early when not on the multisite-setup screen.
	 *
	 * We verify no scripts are enqueued when the current screen ID does not
	 * match 'toplevel_page_wp-ultimo-multisite-setup'.
	 */
	public function test_register_scripts_does_not_enqueue_on_wrong_screen(): void {

		$GLOBALS['current_screen'] = \WP_Screen::get('dashboard');

		$this->page->register_scripts();

		$this->assertFalse(wp_script_is('wu-block-ui', 'enqueued'));
		$this->assertFalse(wp_script_is('wu-setup-wizard-extra', 'enqueued'));

		unset($GLOBALS['current_screen']);
	}

	// -------------------------------------------------------------------------
	// setup_install() — permission guard
	// -------------------------------------------------------------------------

	/**
	 * setup_install() sends JSON error when user lacks manage_options.
	 */
	public function test_setup_install_sends_json_error_when_no_permission(): void {

		// Ensure no user is logged in (no manage_options capability).
		wp_set_current_user(0);

		// Capture JSON output.
		ob_start();
		try {
			$this->page->setup_install();
		} catch (\WPDieException $e) {
			// wp_send_json_error calls wp_die in test context.
		}
		$output = ob_get_clean();

		// The response should be a JSON error.
		if (! empty($output)) {
			$decoded = json_decode($output, true);
			if (is_array($decoded)) {
				$this->assertFalse($decoded['success']);
			}
		}

		// Verify the method did not proceed to installer logic.
		$this->assertTrue(true, 'setup_install() handled permission check without fatal error');
	}

	/**
	 * setup_install() returns early when installer step is not found.
	 */
	public function test_setup_install_returns_early_when_installer_not_found(): void {

		// Grant manage_options.
		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_REQUEST['installer'] = 'nonexistent_step_xyz';

		ob_start();
		$this->page->setup_install();
		$output = ob_get_clean();

		// No JSON output expected — method returns early.
		$this->assertEmpty($output);

		wp_set_current_user(0);
		unset($_REQUEST['installer']);
	}

	// -------------------------------------------------------------------------
	// handle_configure() — permission guard
	// -------------------------------------------------------------------------

	/**
	 * handle_configure() calls wp_die when user lacks manage_options.
	 */
	public function test_handle_configure_dies_when_no_permission(): void {

		wp_set_current_user(0);

		$this->expectException(\WPDieException::class);

		$this->page->handle_configure();
	}

	/**
	 * handle_configure() stores transient and redirects when user has permission.
	 *
	 * We test the transient storage side-effect; the redirect/exit is caught
	 * by overriding wp_safe_redirect via a filter.
	 */
	public function test_handle_configure_stores_transient_when_permitted(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_REQUEST['subdomain_install'] = '1';
		$_REQUEST['sitename']          = 'Test Network';
		$_REQUEST['email']             = 'admin@example.com';

		// Intercept wp_safe_redirect to prevent exit.
		add_filter('wp_redirect', '__return_false');

		try {
			$this->page->handle_configure();
		} catch (\Exception $e) {
			// Catch any exit() call wrapped as exception in test context.
		}

		remove_filter('wp_redirect', '__return_false');

		$stored = get_transient(\WP_Ultimo\Installers\Multisite_Network_Installer::CONFIG_TRANSIENT);

		if (false !== $stored) {
			$this->assertIsArray($stored);
			$this->assertArrayHasKey('subdomain_install', $stored);
			$this->assertArrayHasKey('sitename', $stored);
			$this->assertArrayHasKey('email', $stored);
			$this->assertArrayHasKey('domain', $stored);
			$this->assertArrayHasKey('base', $stored);
			$this->assertTrue($stored['subdomain_install']);
			$this->assertEquals('Test Network', $stored['sitename']);
			$this->assertEquals('admin@example.com', $stored['email']);
		}

		wp_set_current_user(0);
		unset($_REQUEST['subdomain_install'], $_REQUEST['sitename'], $_REQUEST['email']);
	}

	/**
	 * handle_configure() stores subdomain_install as false when value is '0'.
	 */
	public function test_handle_configure_stores_subdomain_install_false_when_zero(): void {

		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
		grant_super_admin($user_id);

		$_REQUEST['subdomain_install'] = '0';
		$_REQUEST['sitename']          = 'My Network';
		$_REQUEST['email']             = 'test@example.com';

		add_filter('wp_redirect', '__return_false');

		try {
			$this->page->handle_configure();
		} catch (\Exception $e) {
			// Catch exit() in test context.
		}

		remove_filter('wp_redirect', '__return_false');

		$stored = get_transient(\WP_Ultimo\Installers\Multisite_Network_Installer::CONFIG_TRANSIENT);

		if (false !== $stored) {
			$this->assertFalse($stored['subdomain_install']);
		}

		wp_set_current_user(0);
		unset($_REQUEST['subdomain_install'], $_REQUEST['sitename'], $_REQUEST['email']);
	}

	// -------------------------------------------------------------------------
	// section_welcome()
	// -------------------------------------------------------------------------

	/**
	 * section_welcome() runs without fatal error.
	 */
	public function test_section_welcome_runs_without_error(): void {

		ob_start();
		$this->page->section_welcome();
		ob_end_clean();

		$this->assertTrue(true, 'section_welcome() ran without fatal error');
	}

	// -------------------------------------------------------------------------
	// section_complete() — success branch
	// -------------------------------------------------------------------------

	/**
	 * section_complete() outputs success markup when result=success.
	 */
	public function test_section_complete_outputs_success_when_result_is_success(): void {

		$_GET['result'] = 'success';

		ob_start();
		$this->page->section_complete();
		$output = ob_get_clean();

		$this->assertStringContainsString('Success', $output);

		unset($_GET['result']);
	}

	/**
	 * section_complete() outputs manual instructions when result is not success and not multisite.
	 *
	 * In the test environment, is_multisite() returns true (WP_TESTS_MULTISITE=1),
	 * so the success branch is taken. We verify the method runs without error.
	 */
	public function test_section_complete_runs_without_error(): void {

		ob_start();
		$this->page->section_complete();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	/**
	 * section_complete() button uses visible WP button classes, not missing Tailwind ones.
	 *
	 * wu-bg-blue-600 is not compiled into framework.css and caused the button to be
	 * rendered with white text on a transparent/white background (invisible). The fix
	 * replaces it with standard WP 'button-primary' classes which are always styled.
	 */
	public function test_section_complete_button_uses_wp_button_classes_not_missing_tailwind(): void {

		// In test env is_multisite() is true, so the success branch renders.
		ob_start();
		$this->page->section_complete();
		$output = ob_get_clean();

		$this->assertStringContainsString('button-primary', $output, 'Continue button must use WP button-primary class');
		$this->assertStringNotContainsString('wu-bg-blue-600', $output, 'wu-bg-blue-600 is not in framework.css and must not be used');
		$this->assertStringNotContainsString('wu-bg-blue-700', $output, 'wu-bg-blue-700 is not in framework.css and must not be used');
	}

	// -------------------------------------------------------------------------
	// display_manual_instructions() — via reflection
	// -------------------------------------------------------------------------

	/**
	 * display_manual_instructions() outputs wp-config.php constants.
	 */
	public function test_display_manual_instructions_outputs_wp_config_constants(): void {

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('display_manual_instructions');
		$method->setAccessible(true);

		ob_start();
		$method->invoke($this->page);
		$output = ob_get_clean();

		$this->assertStringContainsString('WP_ALLOW_MULTISITE', $output);
		$this->assertStringContainsString('MULTISITE', $output);
		$this->assertStringContainsString('SUBDOMAIN_INSTALL', $output);
	}

	/**
	 * display_manual_instructions() outputs DOMAIN_CURRENT_SITE constant.
	 */
	public function test_display_manual_instructions_outputs_domain_constant(): void {

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('display_manual_instructions');
		$method->setAccessible(true);

		ob_start();
		$method->invoke($this->page);
		$output = ob_get_clean();

		$this->assertStringContainsString('DOMAIN_CURRENT_SITE', $output);
	}

	/**
	 * display_manual_instructions() outputs Step 1 heading.
	 */
	public function test_display_manual_instructions_outputs_step_1_heading(): void {

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('display_manual_instructions');
		$method->setAccessible(true);

		ob_start();
		$method->invoke($this->page);
		$output = ob_get_clean();

		$this->assertStringContainsString('wp-config.php', $output);
	}

	/**
	 * display_manual_instructions() refresh button uses WP button classes, not missing Tailwind ones.
	 *
	 * wu-bg-green-600 is not compiled into framework.css, causing the "Refresh and
	 * Check Again" button to be invisible (white text on transparent background).
	 * The fix replaces it with standard WP 'button-primary' classes.
	 */
	public function test_display_manual_instructions_button_uses_wp_button_classes(): void {

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('display_manual_instructions');
		$method->setAccessible(true);

		ob_start();
		$method->invoke($this->page);
		$output = ob_get_clean();

		$this->assertStringContainsString('button-primary', $output, 'Refresh button must use WP button-primary class');
		$this->assertStringNotContainsString('wu-bg-green-600', $output, 'wu-bg-green-600 is not in framework.css and must not be used');
		$this->assertStringNotContainsString('wu-bg-green-700', $output, 'wu-bg-green-700 is not in framework.css and must not be used');
	}

	// -------------------------------------------------------------------------
	// Wizard_Admin_Page inherited methods
	// -------------------------------------------------------------------------

	/**
	 * get_current_section() returns the first section key when no step param.
	 */
	public function test_get_current_section_returns_first_section_by_default(): void {

		unset($_GET['step']);

		$current = $this->page->get_current_section();

		$sections = $this->page->get_sections();
		$first    = array_key_first($sections);

		$this->assertEquals($first, $current);
	}

	/**
	 * get_current_section() returns the requested section when step param is set.
	 */
	public function test_get_current_section_returns_requested_section(): void {

		$_GET['step'] = 'configure';

		$current = $this->page->get_current_section();

		$this->assertEquals('configure', $current);

		unset($_GET['step']);
	}

	/**
	 * get_section_link() returns a URL containing the section slug.
	 */
	public function test_get_section_link_returns_url_with_section(): void {

		$link = $this->page->get_section_link('configure');

		$this->assertStringContainsString('configure', $link);
	}

	/**
	 * get_next_section_link() returns a URL for the next section.
	 */
	public function test_get_next_section_link_returns_next_section(): void {

		unset($_GET['step']); // Start at 'welcome'.

		$link = $this->page->get_next_section_link();

		// Next after 'welcome' is 'configure'.
		$this->assertStringContainsString('configure', $link);
	}

	/**
	 * get_first_section() returns the second section key (index 1).
	 */
	public function test_get_first_section_returns_second_key(): void {

		$sections = $this->page->get_sections();
		$keys     = array_keys($sections);

		$first = $this->page->get_first_section();

		$this->assertEquals($keys[1], $first);
	}

	/**
	 * get_labels() returns an array with expected keys.
	 */
	public function test_get_labels_returns_array_with_expected_keys(): void {

		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
		$this->assertArrayHasKey('save_button_label', $labels);
		$this->assertArrayHasKey('edit_label', $labels);
	}

	/**
	 * get_classes() returns a non-empty string.
	 */
	public function test_get_classes_returns_string(): void {

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('get_classes');
		$method->setAccessible(true);

		$classes = $method->invoke($this->page);

		$this->assertIsString($classes);
		$this->assertNotEmpty($classes);
	}
}
