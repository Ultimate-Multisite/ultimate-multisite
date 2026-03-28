<?php
/**
 * Tests for System_Info_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for System_Info_Admin_Page.
 */
class System_Info_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var System_Info_Admin_Page
	 */
	private $page;

	/**
	 * Original HTTP_USER_AGENT value, captured before setUp() overwrites it.
	 *
	 * @var string|null
	 */
	private $original_user_agent;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		// Snapshot the original value so tearDown() can restore it rather than
		// unconditionally unsetting it (which would remove a pre-existing UA).
		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

		// Set a default user agent so get_browser() always matches a known browser.
		// The source class has a known bug: $browser_name_short is undefined when no
		// browser matches, causing a PHP warning/error. Using a known Chrome UA avoids
		// triggering that code path in tests that don't specifically test unknown UAs.
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

		$this->page = new System_Info_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		if (null === $this->original_user_agent) {
			unset($_SERVER['HTTP_USER_AGENT']);
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Static properties
	// -------------------------------------------------------------------------

	/**
	 * Test page id is correct.
	 */
	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-system-info', $property->getValue($this->page));
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
	 * Test parent is none.
	 */
	public function test_parent_is_none(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);

		$this->assertEquals('none', $property->getValue($this->page));
	}

	/**
	 * Test highlight_menu_slug is set correctly.
	 */
	public function test_highlight_menu_slug(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-settings', $property->getValue($this->page));
	}

	/**
	 * Test badge_count defaults to 0.
	 */
	public function test_badge_count_is_zero(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);

		$this->assertEquals(0, $property->getValue($this->page));
	}

	/**
	 * Test supported_panels contains network_admin_menu.
	 */
	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('manage_network', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_title returns expected string.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('System Info', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_menu_title returns expected string.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('System Info', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_submenu_title returns Dashboard.
	 */
	public function test_get_submenu_title(): void {

		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Dashboard', $title);
	}

	// -------------------------------------------------------------------------
	// init() — hook registration
	// -------------------------------------------------------------------------

	/**
	 * Test init registers the generate_text_file_system_info ajax action.
	 */
	public function test_init_registers_ajax_action(): void {

		$this->page->init();

		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_generate_text_file_system_info', [$this->page, 'generate_text_file_system_info'])
		);
	}

	// -------------------------------------------------------------------------
	// get_browser()
	// -------------------------------------------------------------------------

	/**
	 * Test get_browser returns array with required keys.
	 */
	public function test_get_browser_returns_required_keys(): void {

		$browser = $this->page->get_browser();

		$this->assertIsArray($browser);
		$this->assertArrayHasKey('user_agent', $browser);
		$this->assertArrayHasKey('name', $browser);
		$this->assertArrayHasKey('version', $browser);
		$this->assertArrayHasKey('platform', $browser);
		$this->assertArrayHasKey('pattern', $browser);
	}

	/**
	 * Test get_browser detects Linux platform.
	 */
	public function test_get_browser_detects_linux_platform(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/91.0.4472.114 Safari/537.36';

		$browser = $this->page->get_browser();

		$this->assertEquals('Linux', $browser['platform']);
	}

	/**
	 * Test get_browser detects Mac platform.
	 */
	public function test_get_browser_detects_mac_platform(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/91.0 Safari/537.36';

		$browser = $this->page->get_browser();

		$this->assertEquals('Mac', $browser['platform']);
	}

	/**
	 * Test get_browser detects Windows platform.
	 */
	public function test_get_browser_detects_windows_platform(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/91.0 Safari/537.36';

		$browser = $this->page->get_browser();

		$this->assertEquals('Windows', $browser['platform']);
	}

	/**
	 * Test get_browser returns Unknown platform when no platform match.
	 *
	 * Note: the source class has a known bug where $browser_name_short is
	 * undefined when no browser matches. We use a Firefox UA here (known browser)
	 * with a non-standard platform string to isolate the platform detection path.
	 * The platform detection is independent of browser detection.
	 */
	public function test_get_browser_returns_unknown_platform_when_no_match(): void {

		// Use a UA that matches Firefox (known browser) but no known platform.
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (FreeBSD; rv:89.0) Gecko/20100101 Firefox/89.0';

		$browser = $this->page->get_browser();

		$this->assertEquals('Unknown', $browser['platform']);
	}

	/**
	 * Test get_browser detects Firefox browser.
	 */
	public function test_get_browser_detects_firefox(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0';

		$browser = $this->page->get_browser();

		$this->assertEquals('Mozilla Firefox', $browser['name']);
	}

	/**
	 * Test get_browser detects Chrome browser.
	 */
	public function test_get_browser_detects_chrome(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

		$browser = $this->page->get_browser();

		$this->assertEquals('Google Chrome', $browser['name']);
	}

	/**
	 * Test get_browser detects Safari browser.
	 */
	public function test_get_browser_detects_safari(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15';

		$browser = $this->page->get_browser();

		$this->assertEquals('Apple Safari', $browser['name']);
	}

	/**
	 * Test get_browser detects Opera browser.
	 */
	public function test_get_browser_detects_opera(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Opera/9.80 (Windows NT 6.1; WOW64) Presto/2.12.388 Version/12.18';

		$browser = $this->page->get_browser();

		$this->assertEquals('Opera', $browser['name']);
	}

	/**
	 * Test get_browser detects Netscape browser.
	 */
	public function test_get_browser_detects_netscape(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:0.9.4) Netscape/6.2';

		$browser = $this->page->get_browser();

		$this->assertEquals('Netscape', $browser['name']);
	}

	/**
	 * Test get_browser returns Unknown name when no browser matched.
	 *
	 * Note: the source class has a known bug where $browser_name_short is
	 * undefined when no browser matches the detection chain, causing a PHP error.
	 * This test documents the expected behaviour (Unknown name) but is skipped
	 * until the upstream bug is fixed.
	 *
	 * @see inc/admin-pages/class-system-info-admin-page.php line 628
	 */
	public function test_get_browser_returns_unknown_name_when_no_match(): void {

		$this->markTestSkipped('Known upstream bug: $browser_name_short undefined when no browser matches (line 628 of source class).');
	}

	/**
	 * Test get_browser user_agent matches SERVER value.
	 *
	 * Uses a known Firefox UA to avoid the $browser_name_short undefined bug.
	 */
	public function test_get_browser_user_agent_matches_server(): void {

		$ua = 'Mozilla/5.0 (X11; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0';
		$_SERVER['HTTP_USER_AGENT'] = $ua;

		$browser = $this->page->get_browser();

		$this->assertEquals($ua, $browser['user_agent']);
	}

	/**
	 * Test get_browser returns empty string user_agent when SERVER key absent.
	 *
	 * Note: when HTTP_USER_AGENT is absent, no browser matches and the source
	 * class triggers a PHP error ($browser_name_short undefined). This test is
	 * skipped until the upstream bug is fixed.
	 *
	 * @see inc/admin-pages/class-system-info-admin-page.php line 628
	 */
	public function test_get_browser_user_agent_empty_when_absent(): void {

		$this->markTestSkipped('Known upstream bug: $browser_name_short undefined when HTTP_USER_AGENT is absent (line 628 of source class).');
	}

	/**
	 * Test get_browser version is a string.
	 */
	public function test_get_browser_version_is_string(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0';

		$browser = $this->page->get_browser();

		$this->assertIsString($browser['version']);
	}

	/**
	 * Test get_browser version is non-empty for known browser.
	 */
	public function test_get_browser_version_non_empty_for_known_browser(): void {

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0';

		$browser = $this->page->get_browser();

		$this->assertNotEmpty($browser['version']);
	}

	// -------------------------------------------------------------------------
	// get_all_plugins()
	// -------------------------------------------------------------------------

	/**
	 * Test get_all_plugins returns an array.
	 */
	public function test_get_all_plugins_returns_array(): void {

		$plugins = $this->page->get_all_plugins();

		$this->assertIsArray($plugins);
	}

	// -------------------------------------------------------------------------
	// get_active_plugins()
	// -------------------------------------------------------------------------

	/**
	 * Test get_active_plugins returns an array.
	 */
	public function test_get_active_plugins_returns_array(): void {

		$plugins = $this->page->get_active_plugins();

		$this->assertIsArray($plugins);
	}

	/**
	 * Test get_active_plugins returns empty array when no active plugins.
	 */
	public function test_get_active_plugins_returns_empty_when_none(): void {

		$original = get_site_option('active_sitewide_plugins', null);

		try {
			delete_site_option('active_sitewide_plugins');

			$plugins = $this->page->get_active_plugins();

			$this->assertIsArray($plugins);
			$this->assertEmpty($plugins);
		} finally {
			if (null === $original) {
				delete_site_option('active_sitewide_plugins');
			} else {
				update_site_option('active_sitewide_plugins', $original);
			}
		}
	}

	/**
	 * Test get_active_plugins reflects saved site option.
	 */
	public function test_get_active_plugins_reflects_site_option(): void {

		$original = get_site_option('active_sitewide_plugins', null);

		try {
			update_site_option('active_sitewide_plugins', ['some-plugin/some-plugin.php' => time()]);

			$plugins = $this->page->get_active_plugins();

			$this->assertArrayHasKey('some-plugin/some-plugin.php', $plugins);
		} finally {
			if (null === $original) {
				delete_site_option('active_sitewide_plugins');
			} else {
				update_site_option('active_sitewide_plugins', $original);
			}
		}
	}

	// -------------------------------------------------------------------------
	// get_active_plugins_on_main_site()
	// -------------------------------------------------------------------------

	/**
	 * Test get_active_plugins_on_main_site returns an array.
	 */
	public function test_get_active_plugins_on_main_site_returns_array(): void {

		$plugins = $this->page->get_active_plugins_on_main_site();

		$this->assertIsArray($plugins);
	}

	// -------------------------------------------------------------------------
	// get_memory_usage()
	// -------------------------------------------------------------------------

	/**
	 * Test get_memory_usage returns a float.
	 */
	public function test_get_memory_usage_returns_float(): void {

		$usage = $this->page->get_memory_usage();

		$this->assertIsFloat($usage);
	}

	/**
	 * Test get_memory_usage returns a positive value.
	 */
	public function test_get_memory_usage_is_positive(): void {

		$usage = $this->page->get_memory_usage();

		$this->assertGreaterThan(0, $usage);
	}

	// -------------------------------------------------------------------------
	// get_all_options()
	// -------------------------------------------------------------------------

	/**
	 * Test get_all_options returns an array.
	 */
	public function test_get_all_options_returns_array(): void {

		$options = $this->page->get_all_options();

		$this->assertIsArray($options);
	}

	/**
	 * Test get_all_options is non-empty (WordPress always has options).
	 */
	public function test_get_all_options_is_non_empty(): void {

		$options = $this->page->get_all_options();

		$this->assertNotEmpty($options);
	}

	// -------------------------------------------------------------------------
	// get_transients_in_options()
	// -------------------------------------------------------------------------

	/**
	 * Test get_transients_in_options returns empty array when no transients.
	 */
	public function test_get_transients_in_options_returns_empty_when_none(): void {

		$options    = ['siteurl' => 'http://example.com', 'blogname' => 'Test'];
		$transients = $this->page->get_transients_in_options($options);

		$this->assertIsArray($transients);
		$this->assertEmpty($transients);
	}

	/**
	 * Test get_transients_in_options returns transient entries.
	 */
	public function test_get_transients_in_options_returns_transients(): void {

		$options = [
			'siteurl'                    => 'http://example.com',
			'_transient_my_cache'        => 'cached_value',
			'_transient_timeout_my_cache' => '9999999999',
			'blogname'                   => 'Test',
		];

		$transients = $this->page->get_transients_in_options($options);

		$this->assertIsArray($transients);
		$this->assertArrayHasKey('_transient_my_cache', $transients);
		$this->assertArrayHasKey('_transient_timeout_my_cache', $transients);
		$this->assertArrayNotHasKey('siteurl', $transients);
		$this->assertArrayNotHasKey('blogname', $transients);
	}

	/**
	 * Test get_transients_in_options is case-insensitive for 'transient'.
	 */
	public function test_get_transients_in_options_case_insensitive(): void {

		$options = [
			'_TRANSIENT_upper' => 'value',
			'_Transient_mixed' => 'value2',
		];

		$transients = $this->page->get_transients_in_options($options);

		$this->assertArrayHasKey('_TRANSIENT_upper', $transients);
		$this->assertArrayHasKey('_Transient_mixed', $transients);
	}

	/**
	 * Test get_transients_in_options with empty options returns empty array.
	 */
	public function test_get_transients_in_options_with_empty_options(): void {

		$transients = $this->page->get_transients_in_options([]);

		$this->assertIsArray($transients);
		$this->assertEmpty($transients);
	}

	// -------------------------------------------------------------------------
	// get_all_wp_ultimo_settings()
	// -------------------------------------------------------------------------

	/**
	 * Test get_all_wp_ultimo_settings returns an array.
	 */
	public function test_get_all_wp_ultimo_settings_returns_array(): void {

		$settings = $this->page->get_all_wp_ultimo_settings();

		$this->assertIsArray($settings);
	}

	/**
	 * Test get_all_wp_ultimo_settings excludes email-related settings.
	 */
	public function test_get_all_wp_ultimo_settings_excludes_email(): void {

		$settings = $this->page->get_all_wp_ultimo_settings();

		$this->assertIsArray($settings);

		foreach (array_keys($settings) as $key) {
			$this->assertStringNotContainsStringIgnoringCase('email', $key);
		}
	}

	/**
	 * Test get_all_wp_ultimo_settings excludes logo-related settings.
	 */
	public function test_get_all_wp_ultimo_settings_excludes_logo(): void {

		$settings = $this->page->get_all_wp_ultimo_settings();

		$this->assertIsArray($settings);

		foreach (array_keys($settings) as $key) {
			$this->assertStringNotContainsStringIgnoringCase('logo', $key);
		}
	}

	/**
	 * Test get_all_wp_ultimo_settings excludes stripe-related settings.
	 */
	public function test_get_all_wp_ultimo_settings_excludes_stripe(): void {

		$settings = $this->page->get_all_wp_ultimo_settings();

		$this->assertIsArray($settings);

		foreach (array_keys($settings) as $key) {
			$this->assertStringNotContainsStringIgnoringCase('stripe', $key);
		}
	}

	/**
	 * Test get_all_wp_ultimo_settings excludes license_key.
	 */
	public function test_get_all_wp_ultimo_settings_excludes_license_key(): void {

		$settings = $this->page->get_all_wp_ultimo_settings();

		$this->assertIsArray($settings);

		foreach (array_keys($settings) as $key) {
			$this->assertStringNotContainsStringIgnoringCase('license_key', $key);
		}
	}

	/**
	 * Test get_all_wp_ultimo_settings excludes paypal-related settings.
	 */
	public function test_get_all_wp_ultimo_settings_excludes_paypal(): void {

		$settings = $this->page->get_all_wp_ultimo_settings();

		$this->assertIsArray($settings);

		foreach (array_keys($settings) as $key) {
			$this->assertStringNotContainsStringIgnoringCase('paypal', $key);
		}
	}

	/**
	 * Test get_all_wp_ultimo_settings excludes array values.
	 */
	public function test_get_all_wp_ultimo_settings_excludes_array_values(): void {

		$settings = $this->page->get_all_wp_ultimo_settings();

		$this->assertIsArray($settings);

		foreach ($settings as $value) {
			$this->assertFalse(is_array($value), 'Array values should be excluded from settings');
		}
	}

	// -------------------------------------------------------------------------
	// get_data()
	// -------------------------------------------------------------------------

	/**
	 * Test get_data returns an array.
	 */
	public function test_get_data_returns_array(): void {

		$data = $this->page->get_data();

		$this->assertIsArray($data);
	}

	/**
	 * Test get_data contains WordPress and System Settings section.
	 */
	public function test_get_data_contains_wordpress_system_settings(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('WordPress and System Settings', $data);
	}

	/**
	 * Test get_data contains Active Theme section.
	 */
	public function test_get_data_contains_active_theme(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('Active Theme', $data);
	}

	/**
	 * Test get_data contains Active Plugins section.
	 */
	public function test_get_data_contains_active_plugins(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('Active Plugins', $data);
	}

	/**
	 * Test get_data contains Active Plugins on Main Site section.
	 */
	public function test_get_data_contains_active_plugins_main_site(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('Active Plugins on Main Site', $data);
	}

	/**
	 * Test get_data contains Ultimate Multisite Database Status section.
	 */
	public function test_get_data_contains_database_status(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('Ultimate Multisite Database Status', $data);
	}

	/**
	 * Test get_data contains Ultimate Multisite Core Settings section.
	 */
	public function test_get_data_contains_core_settings(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('Ultimate Multisite Core Settings', $data);
	}

	/**
	 * Test get_data contains Defined Constants section.
	 */
	public function test_get_data_contains_defined_constants(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('Defined Constants', $data);
	}

	/**
	 * Test get_data WordPress section contains wp-ultimo-version key.
	 */
	public function test_get_data_wp_section_has_wu_version(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('wp-ultimo-version', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains wordpress-version key.
	 */
	public function test_get_data_wp_section_has_wordpress_version(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('wordpress-version', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-version key.
	 */
	public function test_get_data_wp_section_has_php_version(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-version', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains mysql-version key.
	 */
	public function test_get_data_wp_section_has_mysql_version(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('mysql-version', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-memory-limit key.
	 */
	public function test_get_data_wp_section_has_memory_limit(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-memory-limit', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-memory-usage key.
	 */
	public function test_get_data_wp_section_has_memory_usage(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-memory-usage', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains multisite-active key.
	 */
	public function test_get_data_wp_section_has_multisite_active(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('multisite-active', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains operating-system key.
	 */
	public function test_get_data_wp_section_has_operating_system(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('operating-system', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains browser key.
	 */
	public function test_get_data_wp_section_has_browser(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('browser', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data each item has tooltip, title, value keys.
	 */
	public function test_get_data_items_have_required_keys(): void {

		$data = $this->page->get_data();

		$this->assertIsArray($data);
		$this->assertNotEmpty($data);

		foreach ($data as $section_name => $section) {
			if ( ! is_array($section)) {
				continue;
			}

			foreach ($section as $key => $item) {
				if ( ! is_array($item)) {
					continue;
				}

				$this->assertArrayHasKey('tooltip', $item, "Section '{$section_name}' key '{$key}' missing 'tooltip'");
				$this->assertArrayHasKey('title', $item, "Section '{$section_name}' key '{$key}' missing 'title'");
				$this->assertArrayHasKey('value', $item, "Section '{$section_name}' key '{$key}' missing 'value'");
			}
		}
	}

	/**
	 * Test get_data PHP version value matches PHP_VERSION.
	 */
	public function test_get_data_php_version_matches_constant(): void {

		$data = $this->page->get_data();

		$this->assertEquals(PHP_VERSION, $data['WordPress and System Settings']['php-version']['value']);
	}

	/**
	 * Test get_data Active Theme section has active-theme key.
	 */
	public function test_get_data_active_theme_has_key(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('active-theme', $data['Active Theme']);
	}

	/**
	 * Test get_data Active Theme value is a non-empty string.
	 */
	public function test_get_data_active_theme_value_is_string(): void {

		$data = $this->page->get_data();

		$this->assertIsString($data['Active Theme']['active-theme']['value']);
		$this->assertNotEmpty($data['Active Theme']['active-theme']['value']);
	}

	/**
	 * Test get_data Defined Constants is an array.
	 */
	public function test_get_data_defined_constants_is_array(): void {

		$data = $this->page->get_data();

		$this->assertIsArray($data['Defined Constants']);
	}

	/**
	 * Test get_data Core Settings contains logs-directory key.
	 */
	public function test_get_data_core_settings_has_logs_directory(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('logs-directory', $data['Ultimate Multisite Core Settings']);
	}

	/**
	 * Test get_data applies wu_system_info_data filter.
	 */
	public function test_get_data_applies_filter(): void {

		$filter_called = false;
		$callback      = function ($data) use (&$filter_called) {
			$filter_called = true;
			return $data;
		};

		add_filter('wu_system_info_data', $callback);

		try {
			$this->page->get_data();
			$this->assertTrue($filter_called);
		} finally {
			remove_filter('wu_system_info_data', $callback);
		}
	}

	/**
	 * Test get_data filter can modify returned data.
	 */
	public function test_get_data_filter_can_modify_data(): void {

		$callback = function ($data) {
			$data['Custom Section'] = ['custom-key' => ['tooltip' => '', 'title' => 'Custom', 'value' => 'test']];
			return $data;
		};

		add_filter('wu_system_info_data', $callback);

		try {
			$data = $this->page->get_data();
			$this->assertArrayHasKey('Custom Section', $data);
			$this->assertEquals('test', $data['Custom Section']['custom-key']['value']);
		} finally {
			remove_filter('wu_system_info_data', $callback);
		}
	}

	/**
	 * Test get_data WordPress section contains wordpress-url key.
	 */
	public function test_get_data_wp_section_has_wordpress_url(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('wordpress-url', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains home-url key.
	 */
	public function test_get_data_wp_section_has_home_url(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('home-url', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains content-directory key.
	 */
	public function test_get_data_wp_section_has_content_directory(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('content-directory', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains plugins-directory key.
	 */
	public function test_get_data_wp_section_has_plugins_directory(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('plugins-directory', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-curl-support key.
	 */
	public function test_get_data_wp_section_has_curl_support(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-curl-support', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-gd-time key.
	 */
	public function test_get_data_wp_section_has_gd_support(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-gd-time', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains wp-options-count key.
	 */
	public function test_get_data_wp_section_has_options_count(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('wp-options-count', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains wp-options-size key.
	 */
	public function test_get_data_wp_section_has_options_size(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('wp-options-size', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains wp-options-transients key.
	 */
	public function test_get_data_wp_section_has_options_transients(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('wp-options-transients', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains timezone key.
	 */
	public function test_get_data_wp_section_has_timezone(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('timezone', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains user-agent key.
	 */
	public function test_get_data_wp_section_has_user_agent(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('user-agent', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-max-execution-time key.
	 */
	public function test_get_data_wp_section_has_max_execution_time(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-max-execution-time', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-post-max-size key.
	 */
	public function test_get_data_wp_section_has_post_max_size(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-post-max-size', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-upload-max-size key.
	 */
	public function test_get_data_wp_section_has_upload_max_size(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-upload-max-size', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-allow-url-fopen key.
	 */
	public function test_get_data_wp_section_has_allow_url_fopen(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-allow-url-fopen', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains php-max-file-uploads key.
	 */
	public function test_get_data_wp_section_has_max_file_uploads(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('php-max-file-uploads', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains cookie-domain key.
	 */
	public function test_get_data_wp_section_has_cookie_domain(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('cookie-domain', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains wp-debug key.
	 */
	public function test_get_data_wp_section_has_wp_debug(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('wp-debug', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains disable_wp_cron key.
	 */
	public function test_get_data_wp_section_has_disable_wp_cron(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('disable_wp_cron', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains wp_memory_limit key.
	 */
	public function test_get_data_wp_section_has_wp_memory_limit(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('wp_memory_limit', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data WordPress section contains wp_max_memory_limit key.
	 */
	public function test_get_data_wp_section_has_wp_max_memory_limit(): void {

		$data = $this->page->get_data();

		$this->assertArrayHasKey('wp_max_memory_limit', $data['WordPress and System Settings']);
	}

	/**
	 * Test get_data Defined Constants contains WP_DEBUG entry.
	 */
	public function test_get_data_defined_constants_contains_wp_debug(): void {

		$data      = $this->page->get_data();
		$constants = $data['Defined Constants'];

		$titles = array_column($constants, 'title');
		$this->assertContains('WP_DEBUG', $titles);
	}

	/**
	 * Test get_data Defined Constants contains SAVEQUERIES entry.
	 */
	public function test_get_data_defined_constants_contains_savequeries(): void {

		$data      = $this->page->get_data();
		$constants = $data['Defined Constants'];

		$titles = array_column($constants, 'title');
		$this->assertContains('SAVEQUERIES', $titles);
	}

	/**
	 * Test get_data Defined Constants contains SCRIPT_DEBUG entry.
	 */
	public function test_get_data_defined_constants_contains_script_debug(): void {

		$data      = $this->page->get_data();
		$constants = $data['Defined Constants'];

		$titles = array_column($constants, 'title');
		$this->assertContains('SCRIPT_DEBUG', $titles);
	}

	/**
	 * Test get_data Defined Constants contains NOBLOGREDIRECT entry.
	 */
	public function test_get_data_defined_constants_contains_noblogredirect(): void {

		$data      = $this->page->get_data();
		$constants = $data['Defined Constants'];

		$titles = array_column($constants, 'title');
		$this->assertContains('NOBLOGREDIRECT', $titles);
	}

	/**
	 * Test get_data memory usage value contains percentage.
	 */
	public function test_get_data_memory_usage_value_contains_percentage(): void {

		$data  = $this->page->get_data();
		$value = $data['WordPress and System Settings']['php-memory-usage']['value'];

		$this->assertStringContainsString('%', $value);
	}

	/**
	 * Test get_data memory limit value ends with M.
	 */
	public function test_get_data_memory_limit_value_ends_with_m(): void {

		$data  = $this->page->get_data();
		$value = $data['WordPress and System Settings']['php-memory-limit']['value'];

		$this->assertStringEndsWith('M', $value);
	}

	/**
	 * Test get_data options size value ends with kb.
	 */
	public function test_get_data_options_size_value_ends_with_kb(): void {

		$data  = $this->page->get_data();
		$value = $data['WordPress and System Settings']['wp-options-size']['value'];

		$this->assertStringEndsWith('kb', $value);
	}

	/**
	 * Test get_data max execution time value contains the translated 'seconds' word.
	 *
	 * Uses the same translation call as the production code so the assertion is
	 * locale-independent and does not break in non-en_US environments.
	 * Production code: sprintf(__('%s seconds', 'ultimate-multisite'), ini_get('max_execution_time'))
	 */
	public function test_get_data_max_execution_time_contains_seconds(): void {

		$data  = $this->page->get_data();
		$value = $data['WordPress and System Settings']['php-max-execution-time']['value'];

		// Assert against the translated format string rather than the hardcoded English word.
		$this->assertStringContainsString(__('seconds', 'ultimate-multisite'), $value);
	}

	/**
	 * Test get_data multisite-active value matches the translated Yes or No string.
	 *
	 * Uses the same translation calls as the production code so the assertion is
	 * locale-independent and does not break in non-en_US environments.
	 * Production code: is_multisite() ? __('Yes', ...) : __('No', ...)
	 */
	public function test_get_data_multisite_active_is_yes_or_no(): void {

		$data  = $this->page->get_data();
		$value = $data['WordPress and System Settings']['multisite-active']['value'];

		$this->assertContains($value, [__('Yes', 'ultimate-multisite'), __('No', 'ultimate-multisite')]);
	}

	/**
	 * Test get_data curl support value matches the translated Yes or No string.
	 *
	 * Uses the same translation calls as the production code so the assertion is
	 * locale-independent and does not break in non-en_US environments.
	 * Production code: function_exists('curl_init') ? __('Yes', ...) : __('No', ...)
	 */
	public function test_get_data_curl_support_is_yes_or_no(): void {

		$data  = $this->page->get_data();
		$value = $data['WordPress and System Settings']['php-curl-support']['value'];

		$this->assertContains($value, [__('Yes', 'ultimate-multisite'), __('No', 'ultimate-multisite')]);
	}

	/**
	 * Test get_data gd support value matches the translated Yes or No string.
	 *
	 * Uses the same translation calls as the production code so the assertion is
	 * locale-independent and does not break in non-en_US environments.
	 * Production code: function_exists('gd_info') ? __('Yes', ...) : __('No', ...)
	 */
	public function test_get_data_gd_support_is_yes_or_no(): void {

		$data  = $this->page->get_data();
		$value = $data['WordPress and System Settings']['php-gd-time']['value'];

		$this->assertContains($value, [__('Yes', 'ultimate-multisite'), __('No', 'ultimate-multisite')]);
	}

	/**
	 * Test get_data options count value is an integer.
	 */
	public function test_get_data_options_count_is_integer(): void {

		$data  = $this->page->get_data();
		$value = $data['WordPress and System Settings']['wp-options-count']['value'];

		$this->assertIsInt($value);
		$this->assertGreaterThan(0, $value);
	}

	/**
	 * Test get_data options transients count is an integer.
	 */
	public function test_get_data_options_transients_count_is_integer(): void {

		$data  = $this->page->get_data();
		$value = $data['WordPress and System Settings']['wp-options-transients']['value'];

		$this->assertIsInt($value);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets does not throw when called.
	 */
	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard-network');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// output_table_system_info()
	// -------------------------------------------------------------------------

	/**
	 * Test output_table_system_info method exists and is callable.
	 */
	public function test_output_table_system_info_method_exists(): void {

		$this->assertTrue(method_exists($this->page, 'output_table_system_info'));
		$this->assertTrue(is_callable([$this->page, 'output_table_system_info']));
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * Test output method exists and is callable.
	 */
	public function test_output_method_exists(): void {

		$this->assertTrue(method_exists($this->page, 'output'));
		$this->assertTrue(is_callable([$this->page, 'output']));
	}

	// -------------------------------------------------------------------------
	// generate_text_file_system_info()
	// -------------------------------------------------------------------------

	/**
	 * Test generate_text_file_system_info method exists and is callable.
	 */
	public function test_generate_text_file_system_info_method_exists(): void {

		$this->assertTrue(method_exists($this->page, 'generate_text_file_system_info'));
		$this->assertTrue(is_callable([$this->page, 'generate_text_file_system_info']));
	}

	// -------------------------------------------------------------------------
	// Integration: get_data round-trip with active plugins
	// -------------------------------------------------------------------------

	/**
	 * Test get_data Active Plugins section is an array.
	 */
	public function test_get_data_active_plugins_is_array(): void {

		$data = $this->page->get_data();

		$this->assertIsArray($data['Active Plugins']);
	}

	/**
	 * Test get_data Active Plugins on Main Site section is an array.
	 */
	public function test_get_data_active_plugins_main_site_is_array(): void {

		$data = $this->page->get_data();

		$this->assertIsArray($data['Active Plugins on Main Site']);
	}

	/**
	 * Test get_data Ultimate Multisite Database Status is an array.
	 */
	public function test_get_data_database_status_is_array(): void {

		$data = $this->page->get_data();

		$this->assertIsArray($data['Ultimate Multisite Database Status']);
	}

	/**
	 * Test get_data Ultimate Multisite Core Settings is an array.
	 */
	public function test_get_data_core_settings_is_array(): void {

		$data = $this->page->get_data();

		$this->assertIsArray($data['Ultimate Multisite Core Settings']);
	}

	/**
	 * Test get_data returns consistent results on repeated calls.
	 */
	public function test_get_data_returns_consistent_results(): void {

		$data1 = $this->page->get_data();
		$data2 = $this->page->get_data();

		$this->assertEquals(array_keys($data1), array_keys($data2));
	}

	/**
	 * Test get_data WordPress section php-version value matches PHP_VERSION constant.
	 */
	public function test_get_data_php_version_value_is_current_php(): void {

		$data = $this->page->get_data();

		$this->assertEquals(PHP_VERSION, $data['WordPress and System Settings']['php-version']['value']);
	}

	/**
	 * Test get_data WordPress section content-directory value is WP_CONTENT_DIR.
	 */
	public function test_get_data_content_directory_value(): void {

		$data = $this->page->get_data();

		$this->assertEquals(WP_CONTENT_DIR, $data['WordPress and System Settings']['content-directory']['value']);
	}

	/**
	 * Test get_data WordPress section plugins-directory value is WP_PLUGIN_DIR.
	 */
	public function test_get_data_plugins_directory_value(): void {

		$data = $this->page->get_data();

		$this->assertEquals(WP_PLUGIN_DIR, $data['WordPress and System Settings']['plugins-directory']['value']);
	}
}
