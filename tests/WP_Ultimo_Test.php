<?php
/**
 * Tests for the WP_Ultimo main class.
 *
 * Covers inc/class-wp-ultimo.php — targeting ≥80% statement coverage.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Test class for WP_Ultimo main class.
 */
class WP_Ultimo_Test extends WP_UnitTestCase {

	/**
	 * The WP_Ultimo singleton instance.
	 *
	 * @var \WP_Ultimo
	 */
	private \WP_Ultimo $wu;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();
		$this->wu = \WP_Ultimo();
	}

	// -------------------------------------------------------------------------
	// Legacy tests (preserved)
	// -------------------------------------------------------------------------

	/**
	 * Test if all helper functions are loaded correctly.
	 *
	 * This test case creates an instance of the WP_Ultimo class and calls the load_public_apis method.
	 * It then asserts that the helper functions wu_to_float, wu_replace_dashes, and wu_get_initials are
	 * correctly loaded. Additional assertions can be added for other helper functions.
	 *
	 * @return void
	 */
	public function testLoadAllHelperFunctionsCorrectly(): void {
		// Assert that all helper functions are loaded correctly.
		// This is all done in the bootstrap.
		$this->assertTrue(function_exists('wu_to_float'));
		$this->assertTrue(function_exists('wu_replace_dashes'));
		$this->assertTrue(function_exists('wu_get_initials'));
	}

	public function testLoaded(): void {
		$wpUltimo = \WP_Ultimo();
		$this->assertTrue($wpUltimo->version === \WP_Ultimo::VERSION);
		$this->assertTrue($wpUltimo->is_loaded());
	}

	public function testPublicProperties(): void {
		$wpUltimo = \WP_Ultimo();
		$this->assertTrue($wpUltimo->settings instanceof Settings);
		$this->assertTrue($wpUltimo->helper instanceof Helper);
		$this->assertTrue($wpUltimo->notices instanceof Admin_Notices);
		$this->assertTrue($wpUltimo->scripts instanceof Scripts);
		$this->assertTrue($wpUltimo->currents instanceof Current);
	}

	// -------------------------------------------------------------------------
	// Constants and version
	// -------------------------------------------------------------------------

	/**
	 * Test that the VERSION constant is a non-empty string.
	 */
	public function test_version_constant_is_string(): void {

		$this->assertIsString(\WP_Ultimo::VERSION);
		$this->assertNotEmpty(\WP_Ultimo::VERSION);
	}

	/**
	 * Test that the LOG_HANDLE constant is defined.
	 */
	public function test_log_handle_constant(): void {

		$this->assertIsString(\WP_Ultimo::LOG_HANDLE);
		$this->assertNotEmpty(\WP_Ultimo::LOG_HANDLE);
	}

	/**
	 * Test that the NETWORK_OPTION_SETUP_FINISHED constant is defined.
	 */
	public function test_network_option_setup_finished_constant(): void {

		$this->assertIsString(\WP_Ultimo::NETWORK_OPTION_SETUP_FINISHED);
		$this->assertNotEmpty(\WP_Ultimo::NETWORK_OPTION_SETUP_FINISHED);
	}

	/**
	 * Test that the public $version property matches the VERSION constant.
	 */
	public function test_version_property_matches_constant(): void {

		$this->assertSame(\WP_Ultimo::VERSION, $this->wu->version);
	}

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * Test that WP_Ultimo() helper returns a WP_Ultimo instance.
	 */
	public function test_singleton_returns_instance(): void {

		$this->assertInstanceOf(\WP_Ultimo::class, $this->wu);
	}

	/**
	 * Test that WP_Ultimo() always returns the same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(\WP_Ultimo(), \WP_Ultimo());
	}

	/**
	 * Test that get_instance() returns the same object as WP_Ultimo().
	 */
	public function test_get_instance_equals_helper(): void {

		$this->assertSame(\WP_Ultimo::get_instance(), \WP_Ultimo());
	}

	// -------------------------------------------------------------------------
	// is_loaded()
	// -------------------------------------------------------------------------

	/**
	 * Test that is_loaded() returns a boolean.
	 */
	public function test_is_loaded_returns_bool(): void {

		$this->assertIsBool($this->wu->is_loaded());
	}

	/**
	 * Test that is_loaded() returns true when requirements are met (test env).
	 */
	public function test_is_loaded_true_in_test_env(): void {

		$this->assertTrue($this->wu->is_loaded());
	}

	// -------------------------------------------------------------------------
	// Public properties set during init()
	// -------------------------------------------------------------------------

	/**
	 * Test that the settings property is a Settings instance.
	 */
	public function test_public_property_settings(): void {

		$this->assertInstanceOf(Settings::class, $this->wu->settings);
	}

	/**
	 * Test that the helper property is a Helper instance.
	 */
	public function test_public_property_helper(): void {

		$this->assertInstanceOf(Helper::class, $this->wu->helper);
	}

	/**
	 * Test that the notices property is an Admin_Notices instance.
	 */
	public function test_public_property_notices(): void {

		$this->assertInstanceOf(Admin_Notices::class, $this->wu->notices);
	}

	/**
	 * Test that the scripts property is a Scripts instance.
	 */
	public function test_public_property_scripts(): void {

		$this->assertInstanceOf(Scripts::class, $this->wu->scripts);
	}

	/**
	 * Test that the currents property is a Current instance.
	 */
	public function test_public_property_currents(): void {

		$this->assertInstanceOf(Current::class, $this->wu->currents);
	}

	/**
	 * Test that the tables property is set after init.
	 */
	public function test_tables_property_is_set(): void {

		$this->assertNotNull($this->wu->tables);
	}

	// -------------------------------------------------------------------------
	// register_addon_headers()
	// -------------------------------------------------------------------------

	/**
	 * Test that register_addon_headers() adds UM-specific headers.
	 */
	public function test_register_addon_headers_adds_um_headers(): void {

		$result = $this->wu->register_addon_headers([]);

		$this->assertContains('UM requires at least', $result);
		$this->assertContains('UM tested up to', $result);
	}

	/**
	 * Test that register_addon_headers() preserves existing headers.
	 */
	public function test_register_addon_headers_preserves_existing(): void {

		$existing = ['Plugin URI', 'Author URI'];
		$result   = $this->wu->register_addon_headers($existing);

		$this->assertContains('Plugin URI', $result);
		$this->assertContains('Author URI', $result);
		$this->assertContains('UM requires at least', $result);
		$this->assertContains('UM tested up to', $result);
	}

	/**
	 * Test that register_addon_headers() returns an array.
	 */
	public function test_register_addon_headers_returns_array(): void {

		$result = $this->wu->register_addon_headers(['Existing Header']);

		$this->assertIsArray($result);
	}

	/**
	 * Test that register_addon_headers() adds exactly two new headers.
	 */
	public function test_register_addon_headers_adds_two_headers(): void {

		$initial = ['Header One'];
		$result  = $this->wu->register_addon_headers($initial);

		$this->assertCount(3, $result);
	}

	// -------------------------------------------------------------------------
	// grant_customer_capabilities()
	// -------------------------------------------------------------------------

	/**
	 * Test that grant_customer_capabilities() returns allcaps unchanged when
	 * wu_manage_membership is not in the required caps.
	 */
	public function test_grant_customer_capabilities_no_op_when_cap_not_checked(): void {

		$allcaps = ['read' => true, 'edit_posts' => true];
		$caps    = ['edit_posts'];
		$args    = [];
		$user    = new \WP_User(self::factory()->user->create());

		$result = $this->wu->grant_customer_capabilities($allcaps, $caps, $args, $user);

		$this->assertSame($allcaps, $result);
	}

	/**
	 * Test that grant_customer_capabilities() does not grant capability to
	 * non-admin users even if wu_manage_membership is checked.
	 */
	public function test_grant_customer_capabilities_no_grant_for_non_admin(): void {

		$user_id = self::factory()->user->create();
		$user    = new \WP_User($user_id);

		$allcaps = ['read' => true];
		$caps    = ['wu_manage_membership'];
		$args    = [];

		$result = $this->wu->grant_customer_capabilities($allcaps, $caps, $args, $user);

		$this->assertArrayNotHasKey('wu_manage_membership', $result);
	}

	/**
	 * Test that grant_customer_capabilities() does not grant capability to
	 * an admin who is NOT a customer.
	 */
	public function test_grant_customer_capabilities_no_grant_for_admin_non_customer(): void {

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		$user    = new \WP_User($user_id);

		$allcaps = ['manage_options' => true];
		$caps    = ['wu_manage_membership'];
		$args    = [];

		$result = $this->wu->grant_customer_capabilities($allcaps, $caps, $args, $user);

		// No customer record exists, so capability should not be granted.
		$this->assertArrayNotHasKey('wu_manage_membership', $result);
	}

	/**
	 * Test that grant_customer_capabilities() grants capability to an admin
	 * who IS a customer.
	 */
	public function test_grant_customer_capabilities_grants_for_admin_customer(): void {

		$user_id = self::factory()->user->create([
			'role'       => 'administrator',
			'user_email' => 'admin-customer-test@example.com',
			'user_login' => 'admin_customer_test_' . wp_rand(1000, 9999),
		]);
		$user = new \WP_User($user_id);

		// Create a customer record for this user.
		$customer = wu_create_customer([
			'user_id'  => $user_id,
			'username' => $user->user_login,
			'email'    => 'admin-customer-test@example.com',
		]);

		$this->assertNotWPError($customer);

		$allcaps = ['manage_options' => true];
		$caps    = ['wu_manage_membership'];
		$args    = [];

		$result = $this->wu->grant_customer_capabilities($allcaps, $caps, $args, $user);

		$this->assertArrayHasKey('wu_manage_membership', $result);
		$this->assertTrue($result['wu_manage_membership']);
	}

	// -------------------------------------------------------------------------
	// maybe_add_beta_param_to_update_url()
	// -------------------------------------------------------------------------

	/**
	 * Test that maybe_add_beta_param_to_update_url() returns args unchanged
	 * when MULTISITE_ULTIMATE_UPDATE_URL is not defined.
	 */
	public function test_maybe_add_beta_param_no_constant(): void {

		// Constant is not defined in test environment.
		if (defined('MULTISITE_ULTIMATE_UPDATE_URL')) {
			$this->markTestSkipped('MULTISITE_ULTIMATE_UPDATE_URL is defined in this environment.');
		}

		$args   = ['timeout' => 10];
		$url    = 'https://example.com/update?update_action=get_metadata';
		$result = $this->wu->maybe_add_beta_param_to_update_url($args, $url);

		$this->assertSame($args, $result);
	}

	/**
	 * Test that maybe_add_beta_param_to_update_url() returns args unchanged
	 * when URL does not match the update server.
	 */
	public function test_maybe_add_beta_param_url_mismatch(): void {

		if (! defined('MULTISITE_ULTIMATE_UPDATE_URL')) {
			$this->markTestSkipped('MULTISITE_ULTIMATE_UPDATE_URL is not defined.');
		}

		$args   = ['timeout' => 10];
		$url    = 'https://other-server.com/update?update_action=get_metadata';
		$result = $this->wu->maybe_add_beta_param_to_update_url($args, $url);

		$this->assertSame($args, $result);
	}

	/**
	 * Test that maybe_add_beta_param_to_update_url() returns args unchanged
	 * when beta updates are disabled.
	 */
	public function test_maybe_add_beta_param_beta_disabled(): void {

		if (! defined('MULTISITE_ULTIMATE_UPDATE_URL')) {
			$this->markTestSkipped('MULTISITE_ULTIMATE_UPDATE_URL is not defined.');
		}

		// Ensure beta updates are disabled.
		wu_save_setting('enable_beta_updates', false);

		$args   = ['timeout' => 10];
		$url    = MULTISITE_ULTIMATE_UPDATE_URL . '?update_action=get_metadata';
		$result = $this->wu->maybe_add_beta_param_to_update_url($args, $url);

		$this->assertSame($args, $result);
	}

	// -------------------------------------------------------------------------
	// maybe_inject_beta_update()
	// -------------------------------------------------------------------------

	/**
	 * Test that maybe_inject_beta_update() returns non-object transient unchanged.
	 */
	public function test_maybe_inject_beta_update_non_object(): void {

		$result = $this->wu->maybe_inject_beta_update(null);

		$this->assertNull($result);
	}

	/**
	 * Test that maybe_inject_beta_update() returns transient unchanged when
	 * beta updates are disabled.
	 */
	public function test_maybe_inject_beta_update_beta_disabled(): void {

		wu_save_setting('enable_beta_updates', false);

		$transient = new \stdClass();
		$result    = $this->wu->maybe_inject_beta_update($transient);

		$this->assertSame($transient, $result);
	}

	/**
	 * Test that maybe_inject_beta_update() returns transient unchanged when
	 * beta updates are enabled but no release is found.
	 */
	public function test_maybe_inject_beta_update_no_release(): void {

		wu_save_setting('enable_beta_updates', true);

		// Cache an empty result so no HTTP request is made.
		set_site_transient('wu_github_release_beta', '', 60);

		$transient = new \stdClass();
		$result    = $this->wu->maybe_inject_beta_update($transient);

		$this->assertSame($transient, $result);

		// Clean up.
		delete_site_transient('wu_github_release_beta');
		wu_save_setting('enable_beta_updates', false);
	}

	/**
	 * Test that maybe_inject_beta_update() returns transient unchanged when
	 * cached release version is not newer than current.
	 */
	public function test_maybe_inject_beta_update_not_newer(): void {

		wu_save_setting('enable_beta_updates', true);

		// Cache a release with a very old version.
		$release = [
			'tag_name' => 'v0.0.1',
			'html_url' => 'https://github.com/example/releases/tag/v0.0.1',
			'assets'   => [],
		];
		set_site_transient('wu_github_release_beta', $release, 60);

		$transient = new \stdClass();
		$result    = $this->wu->maybe_inject_beta_update($transient);

		$this->assertSame($transient, $result);

		// Clean up.
		delete_site_transient('wu_github_release_beta');
		wu_save_setting('enable_beta_updates', false);
	}

	/**
	 * Test that maybe_inject_beta_update() returns transient unchanged when
	 * release has no ZIP asset.
	 */
	public function test_maybe_inject_beta_update_no_zip_asset(): void {

		wu_save_setting('enable_beta_updates', true);

		// Cache a release with a newer version but no ZIP asset.
		$release = [
			'tag_name' => 'v999.0.0',
			'html_url' => 'https://github.com/example/releases/tag/v999.0.0',
			'assets'   => [
				['browser_download_url' => 'https://github.com/example/releases/download/v999.0.0/checksums.txt'],
			],
		];
		set_site_transient('wu_github_release_beta', $release, 60);

		$transient = new \stdClass();
		$result    = $this->wu->maybe_inject_beta_update($transient);

		$this->assertSame($transient, $result);

		// Clean up.
		delete_site_transient('wu_github_release_beta');
		wu_save_setting('enable_beta_updates', false);
	}

	/**
	 * Test that maybe_inject_beta_update() rejects packages from untrusted hosts.
	 */
	public function test_maybe_inject_beta_update_untrusted_host(): void {

		wu_save_setting('enable_beta_updates', true);

		$release = [
			'tag_name' => 'v999.0.0',
			'html_url' => 'https://github.com/example/releases/tag/v999.0.0',
			'assets'   => [
				['browser_download_url' => 'https://evil.example.com/malware.zip'],
			],
		];
		set_site_transient('wu_github_release_beta', $release, 60);

		$transient = new \stdClass();
		$result    = $this->wu->maybe_inject_beta_update($transient);

		// Should return unchanged — untrusted host rejected.
		$this->assertSame($transient, $result);

		// Clean up.
		delete_site_transient('wu_github_release_beta');
		wu_save_setting('enable_beta_updates', false);
	}

	/**
	 * Test that maybe_inject_beta_update() injects update for a valid
	 * objects.githubusercontent.com release.
	 */
	public function test_maybe_inject_beta_update_injects_valid_release_githubusercontent(): void {

		wu_save_setting('enable_beta_updates', true);

		$release = [
			'tag_name' => 'v999.0.0',
			'html_url' => 'https://github.com/example/releases/tag/v999.0.0',
			'assets'   => [
				['browser_download_url' => 'https://objects.githubusercontent.com/example/plugin.zip'],
			],
		];
		set_site_transient('wu_github_release_beta', $release, 60);

		$transient           = new \stdClass();
		$transient->response = [];
		$result              = $this->wu->maybe_inject_beta_update($transient);

		$plugin_file = plugin_basename(WP_ULTIMO_PLUGIN_FILE);
		$this->assertArrayHasKey($plugin_file, $result->response);
		$this->assertEquals('999.0.0', $result->response[ $plugin_file ]->new_version);
		$this->assertEquals(
			'https://objects.githubusercontent.com/example/plugin.zip',
			$result->response[ $plugin_file ]->package
		);

		// Clean up.
		delete_site_transient('wu_github_release_beta');
		wu_save_setting('enable_beta_updates', false);
	}

	/**
	 * Test that maybe_inject_beta_update() accepts packages from github.com.
	 */
	public function test_maybe_inject_beta_update_injects_valid_release_github_com(): void {

		wu_save_setting('enable_beta_updates', true);

		$release = [
			'tag_name' => 'v999.0.0',
			'html_url' => 'https://github.com/example/releases/tag/v999.0.0',
			'assets'   => [
				['browser_download_url' => 'https://github.com/example/releases/download/v999.0.0/plugin.zip'],
			],
		];
		set_site_transient('wu_github_release_beta', $release, 60);

		$transient           = new \stdClass();
		$transient->response = [];
		$result              = $this->wu->maybe_inject_beta_update($transient);

		$plugin_file = plugin_basename(WP_ULTIMO_PLUGIN_FILE);
		$this->assertArrayHasKey($plugin_file, $result->response);

		// Clean up.
		delete_site_transient('wu_github_release_beta');
		wu_save_setting('enable_beta_updates', false);
	}

	/**
	 * Test that injected update object has expected structure.
	 */
	public function test_maybe_inject_beta_update_response_structure(): void {

		wu_save_setting('enable_beta_updates', true);

		$release = [
			'tag_name' => 'v999.0.0',
			'html_url' => 'https://github.com/example/releases/tag/v999.0.0',
			'assets'   => [
				['browser_download_url' => 'https://github.com/example/releases/download/v999.0.0/plugin.zip'],
			],
		];
		set_site_transient('wu_github_release_beta', $release, 60);

		$transient           = new \stdClass();
		$transient->response = [];
		$result              = $this->wu->maybe_inject_beta_update($transient);

		$plugin_file = plugin_basename(WP_ULTIMO_PLUGIN_FILE);
		$update      = $result->response[ $plugin_file ];

		$this->assertEquals('ultimate-multisite', $update->slug);
		$this->assertEquals($plugin_file, $update->plugin);
		$this->assertEquals('https://github.com/example/releases/tag/v999.0.0', $update->url);
		$this->assertEquals('5.3', $update->requires);

		// Clean up.
		delete_site_transient('wu_github_release_beta');
		wu_save_setting('enable_beta_updates', false);
	}

	// -------------------------------------------------------------------------
	// show_site_exporter_deactivation_notice()
	// -------------------------------------------------------------------------

	/**
	 * Test that show_site_exporter_deactivation_notice() outputs nothing when
	 * no transient is set.
	 */
	public function test_show_site_exporter_deactivation_notice_no_transient(): void {

		delete_transient('wu_site_exporter_addon_deactivated');
		delete_site_transient('wu_site_exporter_addon_deactivated');

		ob_start();
		$this->wu->show_site_exporter_deactivation_notice();
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test that show_site_exporter_deactivation_notice() outputs a notice when
	 * the regular transient is set.
	 */
	public function test_show_site_exporter_deactivation_notice_with_transient(): void {

		set_transient('wu_site_exporter_addon_deactivated', true, 60);

		ob_start();
		$this->wu->show_site_exporter_deactivation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString('notice', $output);
		$this->assertStringContainsString('Site Exporter', $output);

		// Transient should be deleted after display.
		$this->assertFalse(get_transient('wu_site_exporter_addon_deactivated'));
	}

	/**
	 * Test that show_site_exporter_deactivation_notice() outputs a notice when
	 * the network transient is set (multisite).
	 */
	public function test_show_site_exporter_deactivation_notice_with_site_transient(): void {

		if (! is_multisite()) {
			$this->markTestSkipped('Multisite-only test.');
		}

		set_site_transient('wu_site_exporter_addon_deactivated', true, 60);

		ob_start();
		$this->wu->show_site_exporter_deactivation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString('notice', $output);

		// Site transient should be deleted after display.
		$this->assertFalse(get_site_transient('wu_site_exporter_addon_deactivated'));
	}

	// -------------------------------------------------------------------------
	// get_addon_repository()
	// -------------------------------------------------------------------------

	/**
	 * Test that get_addon_repository() returns an Addon_Repository instance.
	 */
	public function test_get_addon_repository_returns_instance(): void {

		$repo = $this->wu->get_addon_repository();

		$this->assertInstanceOf(\WP_Ultimo\Addon_Repository::class, $repo);
	}

	/**
	 * Test that get_addon_repository() returns the same instance on repeated calls.
	 */
	public function test_get_addon_repository_returns_same_instance(): void {

		$repo1 = $this->wu->get_addon_repository();
		$repo2 = $this->wu->get_addon_repository();

		$this->assertSame($repo1, $repo2);
	}

	// -------------------------------------------------------------------------
	// check_addon_compatibility()
	// -------------------------------------------------------------------------

	/**
	 * Test that check_addon_compatibility() returns early when not in network admin.
	 *
	 * No notice should be registered when called outside network admin context.
	 */
	public function test_check_addon_compatibility_not_network_admin(): void {

		// Ensure we are NOT in network admin context.
		// In the test environment is_network_admin() returns false by default.
		$hooks_before = has_action('network_admin_notices');

		$this->wu->check_addon_compatibility();

		// No new network_admin_notices hook should be added.
		$this->assertSame($hooks_before, has_action('network_admin_notices'));
	}

	// -------------------------------------------------------------------------
	// Hooks registered during init()
	// -------------------------------------------------------------------------

	/**
	 * Test that init() registers the extra_plugin_headers filter.
	 */
	public function test_init_registers_extra_plugin_headers_filter(): void {

		$this->assertGreaterThan(
			0,
			has_filter('extra_plugin_headers', [$this->wu, 'register_addon_headers'])
		);
	}

	/**
	 * Test that init() registers the admin_init action for addon compatibility.
	 */
	public function test_init_registers_admin_init_action(): void {

		$this->assertGreaterThan(
			0,
			has_action('admin_init', [$this->wu, 'check_addon_compatibility'])
		);
	}

	/**
	 * Test that init() registers the user_has_cap filter.
	 */
	public function test_init_registers_user_has_cap_filter(): void {

		$this->assertGreaterThan(
			0,
			has_filter('user_has_cap', [$this->wu, 'grant_customer_capabilities'])
		);
	}

	/**
	 * Test that init() registers the http_request_args filter.
	 */
	public function test_init_registers_http_request_args_filter(): void {

		$this->assertGreaterThan(
			0,
			has_filter('http_request_args', [$this->wu, 'maybe_add_beta_param_to_update_url'])
		);
	}

	/**
	 * Test that init() registers the site_transient_update_plugins filter.
	 */
	public function test_init_registers_site_transient_update_plugins_filter(): void {

		$this->assertGreaterThan(
			0,
			has_filter('site_transient_update_plugins', [$this->wu, 'maybe_inject_beta_update'])
		);
	}

	/**
	 * Test that init() registers the show_site_exporter_deactivation_notice action.
	 */
	public function test_init_registers_site_exporter_notice_actions(): void {

		$this->assertGreaterThan(
			0,
			has_action('network_admin_notices', [$this->wu, 'show_site_exporter_deactivation_notice'])
		);
		$this->assertGreaterThan(
			0,
			has_action('admin_notices', [$this->wu, 'show_site_exporter_deactivation_notice'])
		);
	}

	// -------------------------------------------------------------------------
	// setup_tables()
	// -------------------------------------------------------------------------

	/**
	 * Test that setup_tables() sets the tables property.
	 */
	public function test_setup_tables_sets_property(): void {

		// Call setup_tables() again — it is idempotent.
		$this->wu->setup_tables();

		$this->assertNotNull($this->wu->tables);
	}

	// -------------------------------------------------------------------------
	// load_public_apis()
	// -------------------------------------------------------------------------

	/**
	 * Test that load_public_apis() loads model public API functions.
	 */
	public function test_load_public_apis_model_functions(): void {

		$this->wu->load_public_apis();

		$this->assertTrue(function_exists('wu_get_customer'));
		$this->assertTrue(function_exists('wu_get_membership'));
		$this->assertTrue(function_exists('wu_get_payment'));
	}

	/**
	 * Test that load_public_apis() loads checkout functions.
	 */
	public function test_load_public_apis_checkout_functions(): void {

		$this->wu->load_public_apis();

		$this->assertTrue(function_exists('wu_is_registration_page'));
	}

	/**
	 * Test that load_public_apis() loads URL helper functions.
	 */
	public function test_load_public_apis_url_helpers(): void {

		$this->wu->load_public_apis();

		$this->assertTrue(function_exists('wu_get_current_url'));
	}

	/**
	 * Test that load_public_apis() loads template functions.
	 */
	public function test_load_public_apis_template_functions(): void {

		$this->wu->load_public_apis();

		$this->assertTrue(function_exists('wu_get_template'));
	}

	// -------------------------------------------------------------------------
	// Reflection-based tests for private methods
	// -------------------------------------------------------------------------

	/**
	 * Test get_latest_github_release() returns cached value when transient exists.
	 */
	public function test_get_latest_github_release_returns_cached(): void {

		$reflection = new \ReflectionClass($this->wu);
		$method     = $reflection->getMethod('get_latest_github_release');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$cached_release = [
			'tag_name' => 'v2.4.0',
			'html_url' => 'https://github.com/example/releases/tag/v2.4.0',
			'assets'   => [],
		];

		set_site_transient('wu_github_release_stable', $cached_release, 60);

		$result = $method->invoke($this->wu, false);

		$this->assertIsArray($result);
		$this->assertEquals('v2.4.0', $result['tag_name']);

		delete_site_transient('wu_github_release_stable');
	}

	/**
	 * Test get_latest_github_release() returns null when cached value is empty string.
	 */
	public function test_get_latest_github_release_returns_null_for_empty_cache(): void {

		$reflection = new \ReflectionClass($this->wu);
		$method     = $reflection->getMethod('get_latest_github_release');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		// Cache an empty string (represents a failed previous request).
		set_site_transient('wu_github_release_stable', '', 60);

		$result = $method->invoke($this->wu, false);

		$this->assertNull($result);

		delete_site_transient('wu_github_release_stable');
	}

	/**
	 * Test get_latest_github_release() uses beta cache key for pre-releases.
	 */
	public function test_get_latest_github_release_beta_cache_key(): void {

		$reflection = new \ReflectionClass($this->wu);
		$method     = $reflection->getMethod('get_latest_github_release');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$beta_release = [
			'tag_name' => 'v3.0.0-beta.1',
			'html_url' => 'https://github.com/example/releases/tag/v3.0.0-beta.1',
			'assets'   => [],
		];

		set_site_transient('wu_github_release_beta', $beta_release, 60);

		$result = $method->invoke($this->wu, true);

		$this->assertIsArray($result);
		$this->assertEquals('v3.0.0-beta.1', $result['tag_name']);

		delete_site_transient('wu_github_release_beta');
	}

	/**
	 * Test maybe_deactivate_site_exporter_addon() via reflection — no-op when addon not active.
	 */
	public function test_maybe_deactivate_site_exporter_addon_no_op(): void {

		$reflection = new \ReflectionClass($this->wu);
		$method     = $reflection->getMethod('maybe_deactivate_site_exporter_addon');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		// Ensure the addon is not in active plugins.
		$active_plugins = get_option('active_plugins', []);
		$this->assertNotContains(
			'ultimate-multisite-site-exporter/ultimate-multisite-site-exporter.php',
			$active_plugins
		);

		// Should run without error.
		$method->invoke($this->wu);

		// Active plugins should be unchanged.
		$this->assertSame($active_plugins, get_option('active_plugins', []));
	}

	/**
	 * Test maybe_deactivate_site_exporter_addon() removes addon from active_plugins.
	 */
	public function test_maybe_deactivate_site_exporter_addon_removes_plugin(): void {

		$reflection = new \ReflectionClass($this->wu);
		$method     = $reflection->getMethod('maybe_deactivate_site_exporter_addon');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$addon_file = 'ultimate-multisite-site-exporter/ultimate-multisite-site-exporter.php';

		// Simulate the addon being active.
		$active_plugins   = get_option('active_plugins', []);
		$active_plugins[] = $addon_file;
		update_option('active_plugins', $active_plugins);

		$method->invoke($this->wu);

		$updated = get_option('active_plugins', []);
		$this->assertNotContains($addon_file, $updated);

		// A transient should be set to show the notice.
		$this->assertTrue((bool) get_transient('wu_site_exporter_addon_deactivated'));

		// Clean up.
		delete_transient('wu_site_exporter_addon_deactivated');
	}

	/**
	 * Test maybe_deactivate_site_exporter_addon() removes addon from network active plugins.
	 */
	public function test_maybe_deactivate_site_exporter_addon_removes_network_plugin(): void {

		if (! is_multisite()) {
			$this->markTestSkipped('Multisite-only test.');
		}

		$reflection = new \ReflectionClass($this->wu);
		$method     = $reflection->getMethod('maybe_deactivate_site_exporter_addon');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$addon_file = 'ultimate-multisite-site-exporter/ultimate-multisite-site-exporter.php';

		// Simulate the addon being network-active.
		$network_plugins                = get_site_option('active_sitewide_plugins', []);
		$network_plugins[ $addon_file ] = time();
		update_site_option('active_sitewide_plugins', $network_plugins);

		$method->invoke($this->wu);

		$updated = get_site_option('active_sitewide_plugins', []);
		$this->assertArrayNotHasKey($addon_file, $updated);

		// A site transient should be set.
		$this->assertTrue((bool) get_site_transient('wu_site_exporter_addon_deactivated'));

		// Clean up.
		delete_site_transient('wu_site_exporter_addon_deactivated');
		// Restore network plugins.
		unset($network_plugins[ $addon_file ]);
		update_site_option('active_sitewide_plugins', $network_plugins);
	}
}
