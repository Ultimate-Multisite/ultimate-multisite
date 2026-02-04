<?php
/**
 * Tests for Whitelabel class.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @since 2.0.0
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Test class for Whitelabel functionality.
 *
 * Tests whitelabel settings registration, text replacement,
 * WordPress logo hiding, dashboard widgets removal, footer
 * text clearing, and sites admin menu removal.
 */
class Whitelabel_Test extends WP_UnitTestCase {

	/**
	 * Store the Whitelabel instance.
	 *
	 * @var Whitelabel
	 */
	private Whitelabel $whitelabel;

	/**
	 * Set up the test fixture.
	 *
	 * @return void
	 */
	public function set_up(): void {

		parent::set_up();

		$this->whitelabel = Whitelabel::get_instance();

		// Reset internal state using reflection so each test starts clean.
		$reflection = new \ReflectionClass($this->whitelabel);

		$search_prop = $reflection->getProperty('search');
		$search_prop->setValue($this->whitelabel, []);

		$replace_prop = $reflection->getProperty('replace');
		$replace_prop->setValue($this->whitelabel, []);

		$allowed_prop = $reflection->getProperty('allowed_domains');
		$allowed_prop->setValue($this->whitelabel, null);

		// Remove hooks that may have been registered by previous tests.
		remove_filter('gettext', [$this->whitelabel, 'replace_text'], 10);
		remove_action('wp_before_admin_bar_render', [$this->whitelabel, 'wp_logo_admin_bar_remove'], 0);
		remove_action('wp_user_dashboard_setup', [$this->whitelabel, 'remove_dashboard_widgets'], 11);
		remove_action('wp_dashboard_setup', [$this->whitelabel, 'remove_dashboard_widgets'], 11);
		remove_action('network_admin_menu', [$this->whitelabel, 'remove_sites_admin_menu']);
	}

	/**
	 * Tear down the test fixture.
	 *
	 * @return void
	 */
	public function tear_down(): void {

		// Remove any filters/actions we added.
		remove_filter('gettext', [$this->whitelabel, 'replace_text'], 10);
		remove_filter('admin_footer_text', '__return_empty_string', 11);
		remove_filter('update_footer', '__return_empty_string', 11);
		remove_action('wp_before_admin_bar_render', [$this->whitelabel, 'wp_logo_admin_bar_remove'], 0);
		remove_action('wp_user_dashboard_setup', [$this->whitelabel, 'remove_dashboard_widgets'], 11);
		remove_action('wp_dashboard_setup', [$this->whitelabel, 'remove_dashboard_widgets'], 11);
		remove_action('network_admin_menu', [$this->whitelabel, 'remove_sites_admin_menu']);

		parent::tear_down();
	}

	/**
	 * Test that get_instance returns a Whitelabel instance.
	 */
	public function test_get_instance_returns_whitelabel(): void {

		$instance = Whitelabel::get_instance();

		$this->assertInstanceOf(Whitelabel::class, $instance);
	}

	/**
	 * Test that get_instance returns the same instance (singleton).
	 */
	public function test_singleton_returns_same_instance(): void {

		$instance1 = Whitelabel::get_instance();
		$instance2 = Whitelabel::get_instance();

		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test that init registers proper hooks.
	 */
	public function test_init_registers_hooks(): void {

		$this->assertGreaterThan(
			0,
			has_action('init', [$this->whitelabel, 'add_settings']),
			'add_settings should be registered on init'
		);

		$this->assertGreaterThan(
			0,
			has_action('admin_init', [$this->whitelabel, 'clear_footer_texts']),
			'clear_footer_texts should be registered on admin_init'
		);

		$this->assertGreaterThan(
			0,
			has_action('init', [$this->whitelabel, 'hooks']),
			'hooks should be registered on init'
		);
	}

	/**
	 * Test hooks registers wp_logo_admin_bar_remove when hide_wordpress_logo is enabled.
	 */
	public function test_hooks_registers_logo_removal_when_enabled(): void {

		wu_save_setting('hide_wordpress_logo', true);

		$this->whitelabel->hooks();

		$this->assertSame(
			0,
			has_action('wp_before_admin_bar_render', [$this->whitelabel, 'wp_logo_admin_bar_remove']),
			'wp_logo_admin_bar_remove should be registered at priority 0'
		);

		$this->assertGreaterThan(
			0,
			has_action('wp_user_dashboard_setup', [$this->whitelabel, 'remove_dashboard_widgets']),
			'remove_dashboard_widgets should be registered on wp_user_dashboard_setup'
		);

		$this->assertGreaterThan(
			0,
			has_action('wp_dashboard_setup', [$this->whitelabel, 'remove_dashboard_widgets']),
			'remove_dashboard_widgets should be registered on wp_dashboard_setup'
		);
	}

	/**
	 * Test hooks does not register logo removal when hide_wordpress_logo is disabled.
	 */
	public function test_hooks_does_not_register_logo_removal_when_disabled(): void {

		wu_save_setting('hide_wordpress_logo', false);

		$this->whitelabel->hooks();

		$this->assertFalse(
			has_action('wp_before_admin_bar_render', [$this->whitelabel, 'wp_logo_admin_bar_remove']),
			'wp_logo_admin_bar_remove should not be registered when disabled'
		);
	}

	/**
	 * Test hooks registers sites menu removal when enabled.
	 */
	public function test_hooks_registers_sites_menu_removal_when_enabled(): void {

		wu_save_setting('hide_sites_menu', true);

		$this->whitelabel->hooks();

		$this->assertGreaterThan(
			0,
			has_action('network_admin_menu', [$this->whitelabel, 'remove_sites_admin_menu']),
			'remove_sites_admin_menu should be registered when hide_sites_menu is enabled'
		);
	}

	/**
	 * Test hooks does not register sites menu removal when disabled.
	 */
	public function test_hooks_does_not_register_sites_menu_removal_when_disabled(): void {

		wu_save_setting('hide_sites_menu', false);

		$this->whitelabel->hooks();

		$this->assertFalse(
			has_action('network_admin_menu', [$this->whitelabel, 'remove_sites_admin_menu']),
			'remove_sites_admin_menu should not be registered when hide_sites_menu is disabled'
		);
	}

	/**
	 * Test hooks registers gettext filter when rename_wordpress is set.
	 */
	public function test_hooks_registers_gettext_filter_when_rename_wordpress_set(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$this->assertSame(
			10,
			has_filter('gettext', [$this->whitelabel, 'replace_text']),
			'replace_text should be registered as gettext filter'
		);
	}

	/**
	 * Test hooks registers gettext filter when rename_site_singular is set.
	 */
	public function test_hooks_registers_gettext_filter_when_rename_singular_set(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', 'App');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$this->assertSame(
			10,
			has_filter('gettext', [$this->whitelabel, 'replace_text']),
			'replace_text should be registered when rename_site_singular is set'
		);
	}

	/**
	 * Test hooks registers gettext filter when rename_site_plural is set.
	 */
	public function test_hooks_registers_gettext_filter_when_rename_plural_set(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', 'Apps');

		$this->whitelabel->hooks();

		$this->assertSame(
			10,
			has_filter('gettext', [$this->whitelabel, 'replace_text']),
			'replace_text should be registered when rename_site_plural is set'
		);
	}

	/**
	 * Test hooks does not register gettext filter when no rename settings are set.
	 */
	public function test_hooks_does_not_register_gettext_when_no_rename(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$this->assertFalse(
			has_filter('gettext', [$this->whitelabel, 'replace_text']),
			'replace_text should not be registered when no rename settings are set'
		);
	}

	/**
	 * Test replace_text replaces WordPress with custom name.
	 */
	public function test_replace_text_replaces_wordpress(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('Welcome to WordPress', 'Welcome to WordPress', 'default');

		$this->assertEquals('Welcome to MyPlatform', $result);
	}

	/**
	 * Test replace_text replaces lowercase wordpress variant.
	 */
	public function test_replace_text_replaces_lowercase_wordpress(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('powered by wordpress', 'powered by wordpress', 'default');

		$this->assertEquals('powered by myplatform', $result);
	}

	/**
	 * Test replace_text replaces capitalized WordPress variant.
	 */
	public function test_replace_text_replaces_capitalized_wordpress(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('WordPress is great', 'WordPress is great', 'default');

		$this->assertEquals('MyPlatform is great', $result);
	}

	/**
	 * Test replace_text replaces "Wordpress" variant (common misspelling).
	 */
	public function test_replace_text_replaces_wordpress_misspelling(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('Wordpress dashboard', 'Wordpress dashboard', 'default');

		$this->assertEquals('MyPlatform dashboard', $result);
	}

	/**
	 * Test replace_text replaces "wordPress" variant.
	 */
	public function test_replace_text_replaces_wordpress_camelcase(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('wordPress setup', 'wordPress setup', 'default');

		$this->assertEquals('MyPlatform setup', $result);
	}

	/**
	 * Test replace_text replaces site singular.
	 */
	public function test_replace_text_replaces_site_singular(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', 'App');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('Edit Site', 'Edit Site', 'default');
		$this->assertEquals('Edit App', $result);
	}

	/**
	 * Test replace_text replaces lowercase site singular.
	 */
	public function test_replace_text_replaces_lowercase_site_singular(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', 'App');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('your site is ready', 'your site is ready', 'default');
		$this->assertEquals('your app is ready', $result);
	}

	/**
	 * Test replace_text replaces site plural.
	 */
	public function test_replace_text_replaces_site_plural(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', 'Apps');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('All Sites', 'All Sites', 'default');
		$this->assertEquals('All Apps', $result);
	}

	/**
	 * Test replace_text replaces lowercase sites plural.
	 */
	public function test_replace_text_replaces_lowercase_site_plural(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', 'Apps');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('manage sites', 'manage sites', 'default');
		$this->assertEquals('manage apps', $result);
	}

	/**
	 * Test replace_text skips non-allowed domains.
	 */
	public function test_replace_text_skips_non_allowed_domains(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('WordPress is great', 'WordPress is great', 'some-other-plugin');

		$this->assertEquals('WordPress is great', $result);
	}

	/**
	 * Test replace_text allows default domain.
	 */
	public function test_replace_text_allows_default_domain(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('WordPress', 'WordPress', 'default');

		$this->assertEquals('MyPlatform', $result);
	}

	/**
	 * Test replace_text allows ultimate-multisite domain.
	 */
	public function test_replace_text_allows_ultimate_multisite_domain(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('WordPress', 'WordPress', 'ultimate-multisite');

		$this->assertEquals('MyPlatform', $result);
	}

	/**
	 * Test replace_text allows wp-ultimo domain.
	 */
	public function test_replace_text_allows_wp_ultimo_domain(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('WordPress', 'WordPress', 'wp-ultimo');

		$this->assertEquals('MyPlatform', $result);
	}

	/**
	 * Test replace_text does not replace URLs starting with https.
	 */
	public function test_replace_text_does_not_replace_https_urls(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$url    = 'https://wordpress.org/support/';
		$result = $this->whitelabel->replace_text($url, $url, 'default');

		$this->assertEquals($url, $result);
	}

	/**
	 * Test replace_text does not replace URLs starting with http.
	 */
	public function test_replace_text_does_not_replace_http_urls(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$url    = 'http://wordpress.org/';
		$result = $this->whitelabel->replace_text($url, $url, 'default');

		$this->assertEquals($url, $result);
	}

	/**
	 * Test replace_text returns translation unchanged when search array is empty.
	 */
	public function test_replace_text_returns_unchanged_when_search_empty(): void {

		wu_save_setting('rename_wordpress', 'Test');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		// Manually clear the search array using reflection.
		$reflection  = new \ReflectionClass($this->whitelabel);
		$search_prop = $reflection->getProperty('search');
		$search_prop->setValue($this->whitelabel, []);

		$result = $this->whitelabel->replace_text('Hello World', 'Hello World', 'default');

		$this->assertEquals('Hello World', $result);
	}

	/**
	 * Test replace_text with all rename settings combined.
	 */
	public function test_replace_text_with_all_replacements(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', 'App');
		wu_save_setting('rename_site_plural', 'Apps');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('WordPress Sites', 'WordPress Sites', 'default');

		$this->assertEquals('MyPlatform Apps', $result);
	}

	/**
	 * Test remove_dashboard_widgets unsets expected meta boxes.
	 */
	public function test_remove_dashboard_widgets(): void {

		global $wp_meta_boxes;

		// Set up the global with dashboard-user boxes.
		$wp_meta_boxes = [
			'dashboard-user' => [
				'side'   => [
					'core' => [
						'dashboard_quick_press' => ['id' => 'dashboard_quick_press'],
						'dashboard_primary'     => ['id' => 'dashboard_primary'],
						'dashboard_secondary'   => ['id' => 'dashboard_secondary'],
					],
				],
				'normal' => [
					'core' => [
						'dashboard_incoming_links'  => ['id' => 'dashboard_incoming_links'],
						'dashboard_right_now'       => ['id' => 'dashboard_right_now'],
						'dashboard_plugins'         => ['id' => 'dashboard_plugins'],
						'dashboard_recent_drafts'   => ['id' => 'dashboard_recent_drafts'],
						'dashboard_recent_comments' => ['id' => 'dashboard_recent_comments'],
					],
				],
			],
		];

		$this->whitelabel->remove_dashboard_widgets();

		$this->assertArrayNotHasKey('dashboard_quick_press', $wp_meta_boxes['dashboard-user']['side']['core']);
		$this->assertArrayNotHasKey('dashboard_primary', $wp_meta_boxes['dashboard-user']['side']['core']);
		$this->assertArrayNotHasKey('dashboard_secondary', $wp_meta_boxes['dashboard-user']['side']['core']);
		$this->assertArrayNotHasKey('dashboard_incoming_links', $wp_meta_boxes['dashboard-user']['normal']['core']);
		$this->assertArrayNotHasKey('dashboard_right_now', $wp_meta_boxes['dashboard-user']['normal']['core']);
		$this->assertArrayNotHasKey('dashboard_plugins', $wp_meta_boxes['dashboard-user']['normal']['core']);
		$this->assertArrayNotHasKey('dashboard_recent_drafts', $wp_meta_boxes['dashboard-user']['normal']['core']);
		$this->assertArrayNotHasKey('dashboard_recent_comments', $wp_meta_boxes['dashboard-user']['normal']['core']);
	}

	/**
	 * Test clear_footer_texts does nothing for super admins.
	 */
	public function test_clear_footer_texts_does_nothing_for_super_admin(): void {

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		// Remove any pre-existing filter to start clean.
		remove_filter('admin_footer_text', '__return_empty_string', 11);

		$this->whitelabel->clear_footer_texts();

		$this->assertFalse(
			has_filter('admin_footer_text', '__return_empty_string'),
			'admin_footer_text filter should not be added for super admins'
		);
	}

	/**
	 * Test clear_footer_texts adds filters for non-super-admin users.
	 */
	public function test_clear_footer_texts_adds_filters_for_regular_users(): void {

		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$this->whitelabel->clear_footer_texts();

		$this->assertSame(
			11,
			has_filter('admin_footer_text', '__return_empty_string'),
			'admin_footer_text should be filtered for regular users'
		);

		$this->assertSame(
			11,
			has_filter('update_footer', '__return_empty_string'),
			'update_footer should be filtered for regular users'
		);
	}

	/**
	 * Test remove_sites_admin_menu removes sites.php from the menu.
	 */
	public function test_remove_sites_admin_menu_removes_sites_item(): void {

		global $menu;

		$menu = [
			5  => ['Dashboard', 'manage_options', 'index.php', '', 'menu-top'],
			10 => ['Sites', 'manage_sites', 'sites.php', '', 'menu-top'],
			15 => ['Users', 'manage_network_users', 'users.php', '', 'menu-top'],
		];

		$this->whitelabel->remove_sites_admin_menu();

		$this->assertArrayNotHasKey(10, $menu, 'sites.php menu item should be removed');
		$this->assertArrayHasKey(5, $menu, 'Dashboard should remain');
		$this->assertArrayHasKey(15, $menu, 'Users should remain');
	}

	/**
	 * Test remove_sites_admin_menu does nothing when sites.php is not in the menu.
	 */
	public function test_remove_sites_admin_menu_does_nothing_without_sites_item(): void {

		global $menu;

		$menu = [
			5  => ['Dashboard', 'manage_options', 'index.php', '', 'menu-top'],
			15 => ['Users', 'manage_network_users', 'users.php', '', 'menu-top'],
		];

		$this->whitelabel->remove_sites_admin_menu();

		$this->assertCount(2, $menu, 'Menu should remain unchanged');
		$this->assertArrayHasKey(5, $menu);
		$this->assertArrayHasKey(15, $menu);
	}

	/**
	 * Test wp_logo_admin_bar_remove removes the wp-logo node.
	 */
	public function test_wp_logo_admin_bar_remove(): void {

		global $wp_admin_bar;

		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		$wp_admin_bar = new \WP_Admin_Bar();
		$wp_admin_bar->initialize();
		$wp_admin_bar->add_node([
			'id'    => 'wp-logo',
			'title' => 'WordPress',
		]);

		$this->assertNotNull($wp_admin_bar->get_node('wp-logo'), 'wp-logo node should exist before removal');

		$this->whitelabel->wp_logo_admin_bar_remove();

		$this->assertNull($wp_admin_bar->get_node('wp-logo'), 'wp-logo node should be removed');
	}

	/**
	 * Test add_settings registers the whitelabel settings section.
	 */
	public function test_add_settings_registers_section(): void {

		$this->whitelabel->add_settings();

		$settings = \WP_Ultimo\Settings::get_instance();
		$sections = $settings->get_sections();

		$this->assertArrayHasKey('whitelabel', $sections, 'Whitelabel section should be registered');
	}

	/**
	 * Test add_settings registers all expected fields.
	 */
	public function test_add_settings_registers_all_fields(): void {

		$this->whitelabel->add_settings();

		$settings = \WP_Ultimo\Settings::get_instance();
		$sections = $settings->get_sections();
		$fields   = $sections['whitelabel']['fields'];

		$this->assertArrayHasKey('whitelabel_header', $fields, 'whitelabel_header field should exist');
		$this->assertArrayHasKey('hide_wordpress_logo', $fields, 'hide_wordpress_logo field should exist');
		$this->assertArrayHasKey('hide_sites_menu', $fields, 'hide_sites_menu field should exist');
		$this->assertArrayHasKey('rename_wordpress', $fields, 'rename_wordpress field should exist');
		$this->assertArrayHasKey('rename_site_singular', $fields, 'rename_site_singular field should exist');
		$this->assertArrayHasKey('rename_site_plural', $fields, 'rename_site_plural field should exist');
	}

	/**
	 * Test add_settings field types are correct.
	 */
	public function test_add_settings_field_types(): void {

		$this->whitelabel->add_settings();

		$settings = \WP_Ultimo\Settings::get_instance();
		$sections = $settings->get_sections();
		$fields   = $sections['whitelabel']['fields'];

		$this->assertEquals('header', $fields['whitelabel_header']['type']);
		$this->assertEquals('toggle', $fields['hide_wordpress_logo']['type']);
		$this->assertEquals('toggle', $fields['hide_sites_menu']['type']);
		$this->assertEquals('text', $fields['rename_wordpress']['type']);
		$this->assertEquals('text', $fields['rename_site_singular']['type']);
		$this->assertEquals('text', $fields['rename_site_plural']['type']);
	}

	/**
	 * Test add_settings default values are correct.
	 */
	public function test_add_settings_default_values(): void {

		$this->whitelabel->add_settings();

		$settings = \WP_Ultimo\Settings::get_instance();
		$sections = $settings->get_sections();
		$fields   = $sections['whitelabel']['fields'];

		$this->assertEquals(1, $fields['hide_wordpress_logo']['default']);
		$this->assertEquals(0, $fields['hide_sites_menu']['default']);
		$this->assertEquals('', $fields['rename_wordpress']['default']);
		$this->assertEquals('', $fields['rename_site_singular']['default']);
		$this->assertEquals('', $fields['rename_site_plural']['default']);
	}

	/**
	 * Test remove_dashboard_widgets with partially populated meta boxes.
	 */
	public function test_remove_dashboard_widgets_with_partial_meta_boxes(): void {

		global $wp_meta_boxes;

		$wp_meta_boxes = [
			'dashboard-user' => [
				'side'   => [
					'core' => [
						'dashboard_quick_press' => ['id' => 'dashboard_quick_press'],
					],
				],
				'normal' => [
					'core' => [
						'dashboard_right_now' => ['id' => 'dashboard_right_now'],
					],
				],
			],
		];

		$this->whitelabel->remove_dashboard_widgets();

		$this->assertArrayNotHasKey('dashboard_quick_press', $wp_meta_boxes['dashboard-user']['side']['core']);
		$this->assertArrayNotHasKey('dashboard_right_now', $wp_meta_boxes['dashboard-user']['normal']['core']);
	}

	/**
	 * Test the wu_replace_text_allowed_domains filter.
	 */
	public function test_allowed_domains_filter(): void {

		wu_save_setting('rename_wordpress', 'CustomName');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		add_filter('wu_replace_text_allowed_domains', function ($domains) {
			$domains[] = 'my-custom-domain';
			return $domains;
		});

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('WordPress', 'WordPress', 'my-custom-domain');

		$this->assertEquals('CustomName', $result);
	}

	/**
	 * Test hooks with only rename_site_plural set does not affect singular.
	 */
	public function test_hooks_with_only_site_plural_does_not_affect_singular(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', 'Stores');

		$this->whitelabel->hooks();

		// Singular should not be affected.
		$result = $this->whitelabel->replace_text('Edit Site', 'Edit Site', 'default');

		$this->assertEquals('Edit Site', $result);
	}

	/**
	 * Test hooks with only rename_site_singular set.
	 */
	public function test_hooks_with_only_site_singular(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', 'Store');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('Edit Site', 'Edit Site', 'default');

		$this->assertEquals('Edit Store', $result);
	}

	/**
	 * Test replace_text case sensitivity for site plural.
	 */
	public function test_replace_text_case_sensitivity_plural(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', 'projects');

		$this->whitelabel->hooks();

		$lower = $this->whitelabel->replace_text('manage sites easily', 'manage sites easily', 'default');
		$this->assertEquals('manage projects easily', $lower);

		$upper = $this->whitelabel->replace_text('Manage Sites Easily', 'Manage Sites Easily', 'default');
		$this->assertEquals('Manage Projects Easily', $upper);
	}

	/**
	 * Test replace_text case sensitivity for site singular.
	 */
	public function test_replace_text_case_sensitivity_singular(): void {

		wu_save_setting('rename_wordpress', '');
		wu_save_setting('rename_site_singular', 'project');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$lower = $this->whitelabel->replace_text('edit site', 'edit site', 'default');
		$this->assertEquals('edit project', $lower);

		$upper = $this->whitelabel->replace_text('Edit Site', 'Edit Site', 'default');
		$this->assertEquals('Edit Project', $upper);
	}

	/**
	 * Test that remove_sites_admin_menu only removes the first sites.php match.
	 */
	public function test_remove_sites_admin_menu_removes_only_first_match(): void {

		global $menu;

		$menu = [
			5  => ['Dashboard', 'manage_options', 'index.php', '', 'menu-top'],
			10 => ['Sites', 'manage_sites', 'sites.php', '', 'menu-top'],
			20 => ['Sites Duplicate', 'manage_sites', 'sites.php', '', 'menu-top'],
		];

		$this->whitelabel->remove_sites_admin_menu();

		// The first match at key 10 should be removed.
		$this->assertArrayNotHasKey(10, $menu);

		// The second match at key 20 should still exist due to the break statement.
		$this->assertArrayHasKey(20, $menu);
	}

	/**
	 * Test replace_text with empty translation string.
	 */
	public function test_replace_text_with_empty_translation(): void {

		wu_save_setting('rename_wordpress', 'MyPlatform');
		wu_save_setting('rename_site_singular', '');
		wu_save_setting('rename_site_plural', '');

		$this->whitelabel->hooks();

		$result = $this->whitelabel->replace_text('', '', 'default');

		$this->assertEquals('', $result);
	}

	/**
	 * Test clear_footer_texts admin_footer_text filter returns empty string.
	 */
	public function test_clear_footer_texts_filter_returns_empty_string(): void {

		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$this->whitelabel->clear_footer_texts();

		$result = apply_filters('admin_footer_text', 'Thank you for creating with WordPress.');

		$this->assertEquals('', $result);
	}

	/**
	 * Test clear_footer_texts update_footer filter returns empty string.
	 */
	public function test_clear_footer_texts_update_footer_returns_empty_string(): void {

		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$this->whitelabel->clear_footer_texts();

		$result = apply_filters('update_footer', 'WordPress 6.4');

		$this->assertEquals('', $result);
	}
}
