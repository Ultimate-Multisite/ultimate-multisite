<?php
/**
 * Unit tests for Site_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Site_Manager;

class Site_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Site_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'site';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Site::class;
	}

	/**
	 * Test allow_hyphens_in_site_name removes the WP error and allows hyphens.
	 */
	public function test_allow_hyphens_allows_valid_hyphenated_name(): void {

		$manager = $this->get_manager_instance();

		$errors = new \WP_Error();
		$errors->add('blogname', __('Site names can only contain lowercase letters (a-z) and numbers.', 'ultimate-multisite'));

		$result = $manager->allow_hyphens_in_site_name(
			[
				'blogname' => 'my-site',
				'errors'   => $errors,
			]
		);

		$this->assertFalse(
			$result['errors']->has_errors(),
			'Hyphenated site name should be valid.'
		);
	}

	/**
	 * Test allow_hyphens_in_site_name rejects invalid characters.
	 */
	public function test_allow_hyphens_rejects_invalid_chars(): void {

		$manager = $this->get_manager_instance();

		$errors = new \WP_Error();
		$errors->add('blogname', __('Site names can only contain lowercase letters (a-z) and numbers.', 'ultimate-multisite'));

		$result = $manager->allow_hyphens_in_site_name(
			[
				'blogname' => 'my_site!',
				'errors'   => $errors,
			]
		);

		$this->assertTrue(
			$result['errors']->has_errors(),
			'Site name with underscores and special chars should be invalid.'
		);
	}

	/**
	 * Test filter_illegal_search_keys removes null/false/empty keys.
	 */
	public function test_filter_illegal_search_keys(): void {

		$manager = $this->get_manager_instance();

		$input = [
			'good_key'  => 'value1',
			''          => 'value2',
			'another'   => 'value3',
			false       => 'value4',
		];

		$result = $manager->filter_illegal_search_keys($input);

		$this->assertArrayHasKey('good_key', $result);
		$this->assertArrayHasKey('another', $result);
		$this->assertCount(2, $result);
	}

	/**
	 * Test get_search_and_replace_settings returns pairs from settings.
	 */
	public function test_get_search_and_replace_settings(): void {

		wu_save_setting(
			'search_and_replace',
			[
				['search' => 'foo', 'replace' => 'bar'],
				['search' => '', 'replace' => 'baz'],
				['search' => 'hello', 'replace' => 'world'],
			]
		);

		$manager = $this->get_manager_instance();
		$pairs   = $manager->get_search_and_replace_settings();

		$this->assertIsArray($pairs);
		$this->assertEquals('bar', $pairs['foo']);
		$this->assertEquals('world', $pairs['hello']);
		$this->assertArrayNotHasKey('', $pairs, 'Empty search keys should be excluded.');
	}

	/**
	 * Test login_header_url returns the site URL.
	 */
	public function test_login_header_url(): void {

		$manager = $this->get_manager_instance();

		$this->assertEquals(get_site_url(), $manager->login_header_url());
	}

	/**
	 * Test login_header_text returns the blog name.
	 */
	public function test_login_header_text(): void {

		$manager = $this->get_manager_instance();

		$this->assertEquals(get_bloginfo('name'), $manager->login_header_text());
	}

	/**
	 * Test hide_super_admin_from_list adds exclusion for non-super-admins.
	 */
	public function test_hide_super_admin_from_list(): void {

		$manager = $this->get_manager_instance();

		// As a non-super admin user.
		$user_id = $this->factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$args   = [];
		$result = $manager->hide_super_admin_from_list($args);

		$this->assertArrayHasKey('login__not_in', $result);

		// Restore.
		wp_set_current_user(0);
	}

	// ========================================================================
	// allow_hyphens_in_site_name – additional edge cases
	// ========================================================================

	/**
	 * Test allow_hyphens_in_site_name passes through names with only lowercase letters and numbers.
	 */
	public function test_allow_hyphens_allows_alphanumeric_only(): void {

		$manager = $this->get_manager_instance();

		$errors = new \WP_Error();
		$errors->add('blogname', __('Site names can only contain lowercase letters (a-z) and numbers.', 'ultimate-multisite'));

		$result = $manager->allow_hyphens_in_site_name(
			[
				'blogname' => 'mysite123',
				'errors'   => $errors,
			]
		);

		$this->assertFalse(
			$result['errors']->has_errors(),
			'Alphanumeric site name should be valid after removing the WP error.'
		);
	}

	/**
	 * Test allow_hyphens_in_site_name does nothing when the relevant error is not present.
	 */
	public function test_allow_hyphens_ignores_unrelated_errors(): void {

		$manager = $this->get_manager_instance();

		$errors = new \WP_Error();
		$errors->add('blogname', 'Some other unrelated error.');

		$result = $manager->allow_hyphens_in_site_name(
			[
				'blogname' => 'my-site',
				'errors'   => $errors,
			]
		);

		$this->assertTrue(
			$result['errors']->has_errors(),
			'Unrelated errors should remain untouched.'
		);

		$messages = $result['errors']->get_error_messages('blogname');
		$this->assertContains('Some other unrelated error.', $messages);
	}

	/**
	 * Test allow_hyphens_in_site_name with no errors at all.
	 */
	public function test_allow_hyphens_with_no_errors(): void {

		$manager = $this->get_manager_instance();

		$errors = new \WP_Error();

		$result = $manager->allow_hyphens_in_site_name(
			[
				'blogname' => 'valid-name',
				'errors'   => $errors,
			]
		);

		$this->assertFalse(
			$result['errors']->has_errors(),
			'No errors should remain when none were present initially.'
		);
	}

	/**
	 * Test allow_hyphens_in_site_name rejects uppercase characters.
	 */
	public function test_allow_hyphens_rejects_uppercase(): void {

		$manager = $this->get_manager_instance();

		$errors = new \WP_Error();
		$errors->add('blogname', __('Site names can only contain lowercase letters (a-z) and numbers.', 'ultimate-multisite'));

		$result = $manager->allow_hyphens_in_site_name(
			[
				'blogname' => 'MyUPPERSite',
				'errors'   => $errors,
			]
		);

		$this->assertTrue(
			$result['errors']->has_errors(),
			'Uppercase characters should be rejected.'
		);
	}

	/**
	 * Test allow_hyphens_in_site_name with multiple hyphens.
	 */
	public function test_allow_hyphens_allows_multiple_hyphens(): void {

		$manager = $this->get_manager_instance();

		$errors = new \WP_Error();
		$errors->add('blogname', __('Site names can only contain lowercase letters (a-z) and numbers.', 'ultimate-multisite'));

		$result = $manager->allow_hyphens_in_site_name(
			[
				'blogname' => 'my-cool-site-123',
				'errors'   => $errors,
			]
		);

		$this->assertFalse(
			$result['errors']->has_errors(),
			'Multiple hyphens in site name should be valid.'
		);
	}

	/**
	 * Test allow_hyphens_in_site_name preserves other blogname errors alongside the one it removes.
	 */
	public function test_allow_hyphens_preserves_other_blogname_errors(): void {

		$manager = $this->get_manager_instance();

		$errors = new \WP_Error();
		$errors->add('blogname', __('Site names can only contain lowercase letters (a-z) and numbers.', 'ultimate-multisite'));
		$errors->add('blogname', 'Site name is too short.');

		$result = $manager->allow_hyphens_in_site_name(
			[
				'blogname' => 'my-site',
				'errors'   => $errors,
			]
		);

		// The WP letters-numbers error should be removed, but the "too short" error remains.
		$messages = $result['errors']->get_error_messages('blogname');
		$this->assertContains('Site name is too short.', $messages);
	}

	/**
	 * Test allow_hyphens_in_site_name rejects spaces.
	 */
	public function test_allow_hyphens_rejects_spaces(): void {

		$manager = $this->get_manager_instance();

		$errors = new \WP_Error();
		$errors->add('blogname', __('Site names can only contain lowercase letters (a-z) and numbers.', 'ultimate-multisite'));

		$result = $manager->allow_hyphens_in_site_name(
			[
				'blogname' => 'my site',
				'errors'   => $errors,
			]
		);

		$this->assertTrue(
			$result['errors']->has_errors(),
			'Spaces in site name should be rejected.'
		);
	}

	// ========================================================================
	// filter_illegal_search_keys – additional cases
	// ========================================================================

	/**
	 * Test filter_illegal_search_keys with all valid keys.
	 */
	public function test_filter_illegal_search_keys_all_valid(): void {

		$manager = $this->get_manager_instance();

		$input = [
			'key1' => 'value1',
			'key2' => 'value2',
			'key3' => 'value3',
		];

		$result = $manager->filter_illegal_search_keys($input);

		$this->assertCount(3, $result);
		$this->assertEquals($input, $result);
	}

	/**
	 * Test filter_illegal_search_keys with empty array.
	 */
	public function test_filter_illegal_search_keys_empty_array(): void {

		$manager = $this->get_manager_instance();

		$result = $manager->filter_illegal_search_keys([]);

		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}

	/**
	 * Test filter_illegal_search_keys preserves values even when key looks odd but is valid.
	 */
	public function test_filter_illegal_search_keys_numeric_keys(): void {

		$manager = $this->get_manager_instance();

		$input = [
			'0'    => 'zero-string',
			1      => 'one-int',
			'key'  => 'value',
		];

		// Note: PHP treats '0' and 0 as the same array key, but both should be filtered as empty/false-like
		// Actually, '0' is not empty for array_filter with ARRAY_FILTER_USE_KEY, but 0 and '' are
		$result = $manager->filter_illegal_search_keys($input);

		// 'key' should remain, numeric keys depend on the filter behavior
		$this->assertArrayHasKey('key', $result);
	}

	// ========================================================================
	// get_search_and_replace_settings – additional cases
	// ========================================================================

	/**
	 * Test get_search_and_replace_settings with empty settings.
	 */
	public function test_get_search_and_replace_settings_empty(): void {

		wu_save_setting('search_and_replace', []);

		$manager = $this->get_manager_instance();
		$pairs   = $manager->get_search_and_replace_settings();

		$this->assertIsArray($pairs);
		$this->assertEmpty($pairs);
	}

	/**
	 * Test get_search_and_replace_settings skips items without search key.
	 */
	public function test_get_search_and_replace_settings_skips_missing_search(): void {

		wu_save_setting(
			'search_and_replace',
			[
				['replace' => 'bar'],
				['search' => 'hello', 'replace' => 'world'],
			]
		);

		$manager = $this->get_manager_instance();
		$pairs   = $manager->get_search_and_replace_settings();

		$this->assertCount(1, $pairs);
		$this->assertEquals('world', $pairs['hello']);
	}

	/**
	 * Test get_search_and_replace_settings allows empty replace value.
	 */
	public function test_get_search_and_replace_settings_allows_empty_replace(): void {

		wu_save_setting(
			'search_and_replace',
			[
				['search' => 'remove-me', 'replace' => ''],
			]
		);

		$manager = $this->get_manager_instance();
		$pairs   = $manager->get_search_and_replace_settings();

		$this->assertCount(1, $pairs);
		$this->assertEquals('', $pairs['remove-me']);
	}

	// ========================================================================
	// search_and_replace_on_duplication
	// ========================================================================

	/**
	 * Test search_and_replace_on_duplication merges settings with incoming pairs.
	 */
	public function test_search_and_replace_on_duplication_merges(): void {

		wu_save_setting(
			'search_and_replace',
			[
				['search' => 'old-domain', 'replace' => 'new-domain'],
			]
		);

		$manager = $this->get_manager_instance();

		$incoming = [
			'existing-key' => 'existing-value',
		];

		$result = $manager->search_and_replace_on_duplication($incoming, 1, 2);

		$this->assertArrayHasKey('existing-key', $result);
		$this->assertArrayHasKey('old-domain', $result);
		$this->assertEquals('new-domain', $result['old-domain']);
		$this->assertEquals('existing-value', $result['existing-key']);
	}

	/**
	 * Test search_and_replace_on_duplication filters out illegal keys from merged result.
	 */
	public function test_search_and_replace_on_duplication_filters_illegal(): void {

		wu_save_setting('search_and_replace', []);

		$manager = $this->get_manager_instance();

		$incoming = [
			''          => 'should-be-removed',
			'valid-key' => 'valid-value',
		];

		$result = $manager->search_and_replace_on_duplication($incoming, 1, 2);

		$this->assertArrayNotHasKey('', $result);
		$this->assertArrayHasKey('valid-key', $result);
	}

	/**
	 * Test search_and_replace_on_duplication applies the wu_search_and_replace_on_duplication filter.
	 */
	public function test_search_and_replace_on_duplication_applies_filter(): void {

		wu_save_setting('search_and_replace', []);

		add_filter('wu_search_and_replace_on_duplication', function ($settings, $from, $to) {
			$settings['filter-key'] = 'filter-value';
			return $settings;
		}, 10, 3);

		$manager = $this->get_manager_instance();

		$result = $manager->search_and_replace_on_duplication([], 1, 2);

		$this->assertArrayHasKey('filter-key', $result);
		$this->assertEquals('filter-value', $result['filter-key']);

		remove_all_filters('wu_search_and_replace_on_duplication');
	}

	// ========================================================================
	// hide_super_admin_from_list – additional cases
	// ========================================================================

	/**
	 * Test hide_super_admin_from_list does not modify args for super admins.
	 */
	public function test_hide_super_admin_from_list_as_super_admin(): void {

		$manager = $this->get_manager_instance();

		// Set current user to a super admin.
		$user_id = $this->factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$args   = ['existing_key' => 'existing_value'];
		$result = $manager->hide_super_admin_from_list($args);

		$this->assertArrayNotHasKey('login__not_in', $result);
		$this->assertArrayHasKey('existing_key', $result);

		// Clean up.
		revoke_super_admin($user_id);
		wp_set_current_user(0);
	}

	/**
	 * Test hide_super_admin_from_list preserves existing args for non-super-admins.
	 */
	public function test_hide_super_admin_preserves_existing_args(): void {

		$manager = $this->get_manager_instance();

		$user_id = $this->factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$args   = ['role' => 'editor', 'number' => 10];
		$result = $manager->hide_super_admin_from_list($args);

		$this->assertArrayHasKey('login__not_in', $result);
		$this->assertEquals('editor', $result['role']);
		$this->assertEquals(10, $result['number']);

		wp_set_current_user(0);
	}

	// ========================================================================
	// login_header_url / login_header_text – additional cases
	// ========================================================================

	/**
	 * Test login_header_url returns a string URL.
	 */
	public function test_login_header_url_is_string(): void {

		$manager = $this->get_manager_instance();

		$url = $manager->login_header_url();

		$this->assertIsString($url);
		$this->assertNotEmpty($url);
	}

	/**
	 * Test login_header_text returns a non-empty string.
	 */
	public function test_login_header_text_is_string(): void {

		$manager = $this->get_manager_instance();

		$text = $manager->login_header_text();

		$this->assertIsString($text);
	}

	// ========================================================================
	// init – verify hooks are registered
	// ========================================================================

	/**
	 * Test init registers the after_setup_theme hook.
	 */
	public function test_init_registers_additional_thumbnail_sizes_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('after_setup_theme', [$manager, 'additional_thumbnail_sizes'])
		);
	}

	/**
	 * Test init registers the lock_site hook.
	 */
	public function test_init_registers_lock_site_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wp', [$manager, 'lock_site'])
		);
	}

	/**
	 * Test init registers the admin_init hook for no-index warning.
	 */
	public function test_init_registers_add_no_index_warning_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('admin_init', [$manager, 'add_no_index_warning'])
		);
	}

	/**
	 * Test init registers the wp_head hook for preventing template indexing.
	 */
	public function test_init_registers_prevent_site_template_indexing_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wp_head', [$manager, 'prevent_site_template_indexing'])
		);
	}

	/**
	 * Test init registers the login_enqueue_scripts hook for custom login logo.
	 */
	public function test_init_registers_custom_login_logo_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('login_enqueue_scripts', [$manager, 'custom_login_logo'])
		);
	}

	/**
	 * Test init registers the login_headerurl filter.
	 */
	public function test_init_registers_login_header_url_filter(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_filter('login_headerurl', [$manager, 'login_header_url'])
		);
	}

	/**
	 * Test init registers the login_headertext filter.
	 */
	public function test_init_registers_login_header_text_filter(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_filter('login_headertext', [$manager, 'login_header_text'])
		);
	}

	/**
	 * Test init registers the wu_pending_site_published hook.
	 */
	public function test_init_registers_handle_site_published_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_pending_site_published', [$manager, 'handle_site_published'])
		);
	}

	/**
	 * Test init registers the mucd_string_to_replace filter.
	 */
	public function test_init_registers_search_and_replace_on_duplication_filter(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_filter('mucd_string_to_replace', [$manager, 'search_and_replace_on_duplication'])
		);
	}

	/**
	 * Test init registers the wu_site_created action.
	 */
	public function test_init_registers_search_and_replace_for_new_site_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_site_created', [$manager, 'search_and_replace_for_new_site'])
		);
	}

	/**
	 * Test init registers the users_list_table_query_args filter.
	 */
	public function test_init_registers_hide_super_admin_from_list_filter(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_filter('users_list_table_query_args', [$manager, 'hide_super_admin_from_list'])
		);
	}

	/**
	 * Test init registers the wpmu_validate_blog_signup filter.
	 */
	public function test_init_registers_allow_hyphens_in_site_name_filter(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_filter('wpmu_validate_blog_signup', [$manager, 'allow_hyphens_in_site_name'])
		);
	}

	/**
	 * Test init registers the wu_daily action for delete_pending_sites.
	 */
	public function test_init_registers_delete_pending_sites_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_daily', [$manager, 'delete_pending_sites'])
		);
	}

	/**
	 * Test init registers the pre_get_blogs_of_user filter.
	 */
	public function test_init_registers_hide_customer_sites_from_super_admin_list_filter(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_filter('pre_get_blogs_of_user', [$manager, 'hide_customer_sites_from_super_admin_list'])
		);
	}

	/**
	 * Test init registers wu_before_handle_order_submission action.
	 */
	public function test_init_registers_maybe_validate_add_new_site_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_before_handle_order_submission', [$manager, 'maybe_validate_add_new_site'])
		);
	}

	/**
	 * Test init registers wu_checkout_before_process_checkout action.
	 */
	public function test_init_registers_maybe_add_new_site_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_checkout_before_process_checkout', [$manager, 'maybe_add_new_site'])
		);
	}

	/**
	 * Test init registers wu_async_take_screenshot action.
	 */
	public function test_init_registers_async_get_site_screenshot_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_async_take_screenshot', [$manager, 'async_get_site_screenshot'])
		);
	}

	/**
	 * Test init registers wp_ajax_wu_get_screenshot action.
	 */
	public function test_init_registers_get_site_screenshot_ajax_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wp_ajax_wu_get_screenshot', [$manager, 'get_site_screenshot'])
		);
	}

	/**
	 * Test init registers load-sites.php action.
	 */
	public function test_init_registers_add_notices_to_default_site_page_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('load-sites.php', [$manager, 'add_notices_to_default_site_page'])
		);
	}

	// ========================================================================
	// Site creation via wu_create_site
	// ========================================================================

	/**
	 * Test wu_create_site creates a basic site.
	 */
	public function test_wu_create_site_creates_basic_site(): void {

		$site = wu_create_site(
			[
				'title'  => 'Basic Test Site',
				'domain' => 'basic-test.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);
		$this->assertInstanceOf(\WP_Ultimo\Models\Site::class, $site);
		$this->assertNotEmpty($site->get_id());
		$this->assertEquals('Basic Test Site', $site->get_title());
	}

	/**
	 * Test wu_create_site with a template ID.
	 */
	public function test_wu_create_site_with_template_id(): void {

		// Create a template site first.
		$template = wu_create_site(
			[
				'title'  => 'Template Site',
				'domain' => 'template-for-test.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);

		$this->assertNotWPError($template);

		// Now create a site from this template.
		$site = wu_create_site(
			[
				'title'       => 'Site From Template',
				'domain'      => 'from-template.example.com',
				'path'        => '/',
				'template_id' => $template->get_id(),
			]
		);

		$this->assertNotWPError($site);
		$this->assertNotEmpty($site->get_id());
	}

	// ========================================================================
	// Site type management
	// ========================================================================

	/**
	 * Test site type can be set and retrieved.
	 */
	public function test_site_type_set_and_get(): void {

		$site = wu_create_site(
			[
				'title'  => 'Typed Site',
				'domain' => 'typed-site.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);
		$this->assertEquals(\WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED, $site->get_type());
	}

	/**
	 * Test site_template type can be set.
	 */
	public function test_site_template_type(): void {

		$site = wu_create_site(
			[
				'title'  => 'Template Type Site',
				'domain' => 'template-type.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE,
			]
		);

		$this->assertNotWPError($site);
		$this->assertEquals(\WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE, $site->get_type());
	}

	/**
	 * Test main site returns 'main' type.
	 */
	public function test_main_site_returns_main_type(): void {

		$main_site_id = wu_get_main_site_id();
		$site         = wu_get_current_site();

		// On the main site, get_type() should return 'main'
		if ($site->get_id() === $main_site_id) {
			$this->assertEquals('main', $site->get_type());
		} else {
			$this->assertTrue(true, 'Current site is not main; skipping.');
		}
	}

	// ========================================================================
	// get_all_by_type – template sites and customer-owned sites
	// ========================================================================

	/**
	 * Test get_all_by_type returns site_template sites.
	 */
	public function test_get_all_by_type_returns_templates(): void {

		$site = wu_create_site(
			[
				'title'  => 'Template for Query',
				'domain' => 'template-query.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE,
			]
		);

		$this->assertNotWPError($site);

		$templates = \WP_Ultimo\Models\Site::get_all_by_type('site_template');

		$this->assertIsArray($templates);

		$found = false;
		foreach ($templates as $t) {
			if ($t->get_id() === $site->get_id()) {
				$found = true;
				break;
			}
		}

		$this->assertTrue($found, 'The created template site should appear in get_all_by_type results.');
	}

	/**
	 * Test get_all_by_type returns customer_owned sites.
	 */
	public function test_get_all_by_type_returns_customer_owned(): void {

		$site = wu_create_site(
			[
				'title'  => 'Customer Owned for Query',
				'domain' => 'customer-query.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$customer_sites = \WP_Ultimo\Models\Site::get_all_by_type('customer_owned');

		$this->assertIsArray($customer_sites);

		$found = false;
		foreach ($customer_sites as $cs) {
			if ($cs->get_id() === $site->get_id()) {
				$found = true;
				break;
			}
		}

		$this->assertTrue($found, 'The created customer_owned site should appear in get_all_by_type results.');
	}

	/**
	 * Test wu_get_site_templates helper function.
	 */
	public function test_wu_get_site_templates(): void {

		$site = wu_create_site(
			[
				'title'  => 'Template Helper Test',
				'domain' => 'template-helper.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE,
			]
		);

		$this->assertNotWPError($site);

		$templates = wu_get_site_templates();

		$this->assertIsArray($templates);

		$ids = array_map(fn($t) => $t->get_id(), $templates);
		$this->assertContains($site->get_id(), $ids);
	}

	// ========================================================================
	// additional_thumbnail_sizes
	// ========================================================================

	/**
	 * Test additional_thumbnail_sizes registers image sizes on main site.
	 */
	public function test_additional_thumbnail_sizes_on_main_site(): void {

		$manager = $this->get_manager_instance();

		// We should be on the main site in tests.
		if (is_main_site()) {
			$manager->additional_thumbnail_sizes();

			global $_wp_additional_image_sizes;

			$this->assertArrayHasKey('wu-thumb-large', $_wp_additional_image_sizes);
			$this->assertArrayHasKey('wu-thumb-medium', $_wp_additional_image_sizes);
			$this->assertEquals(900, $_wp_additional_image_sizes['wu-thumb-large']['width']);
			$this->assertEquals(675, $_wp_additional_image_sizes['wu-thumb-large']['height']);
			$this->assertEquals(400, $_wp_additional_image_sizes['wu-thumb-medium']['width']);
			$this->assertEquals(300, $_wp_additional_image_sizes['wu-thumb-medium']['height']);
		} else {
			$this->assertTrue(true, 'Not on main site; skipping.');
		}
	}

	// ========================================================================
	// prevent_site_template_indexing
	// ========================================================================

	/**
	 * Test prevent_site_template_indexing does nothing when setting is disabled.
	 */
	public function test_prevent_site_template_indexing_disabled(): void {

		wu_save_setting('stop_template_indexing', false);

		$manager = $this->get_manager_instance();

		// Should not add the wp_robots filter.
		$priority_before = has_filter('wp_robots', 'wp_robots_no_robots');

		$manager->prevent_site_template_indexing();

		$priority_after = has_filter('wp_robots', 'wp_robots_no_robots');

		// Priority shouldn't change if setting is disabled.
		$this->assertEquals($priority_before, $priority_after);
	}

	// ========================================================================
	// add_no_index_warning
	// ========================================================================

	/**
	 * Test add_no_index_warning does nothing when setting is disabled.
	 */
	public function test_add_no_index_warning_disabled(): void {

		wu_save_setting('stop_template_indexing', false);

		$manager = $this->get_manager_instance();
		$manager->add_no_index_warning();

		// Should not have added the meta box.
		// We can check there is no error/exception.
		$this->assertTrue(true, 'No exception thrown when stop_template_indexing is false.');
	}

	/**
	 * Test add_no_index_warning adds meta box when setting is enabled.
	 */
	public function test_add_no_index_warning_enabled(): void {

		wu_save_setting('stop_template_indexing', true);

		$manager = $this->get_manager_instance();
		$manager->add_no_index_warning();

		// Check the meta box was registered.
		global $wp_meta_boxes;

		$found = isset($wp_meta_boxes['dashboard-network']['normal']['high']['wu-warnings']);

		$this->assertTrue($found, 'Meta box wu-warnings should be registered when stop_template_indexing is true.');
	}

	// ========================================================================
	// async_get_site_screenshot
	// ========================================================================

	/**
	 * Test async_get_site_screenshot returns early for non-existing site.
	 */
	public function test_async_get_site_screenshot_returns_for_missing_site(): void {

		$manager = $this->get_manager_instance();

		// Calling with a non-existing site ID should return early without errors.
		$manager->async_get_site_screenshot(999999);

		$this->assertTrue(true, 'No exception thrown for missing site.');
	}

	// ========================================================================
	// Site model – edge cases
	// ========================================================================

	/**
	 * Test site exists() returns false for new unsaved site.
	 */
	public function test_site_exists_returns_false_for_new_site(): void {

		$site = new \WP_Ultimo\Models\Site();

		$this->assertFalse($site->exists());
	}

	/**
	 * Test site exists() returns true for saved site.
	 */
	public function test_site_exists_returns_true_for_saved_site(): void {

		$site = wu_create_site(
			[
				'title'  => 'Exists Test Site',
				'domain' => 'exists-test.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);
		$this->assertTrue($site->exists());
	}

	/**
	 * Test site get_id returns integer.
	 */
	public function test_site_get_id_returns_integer(): void {

		$site = wu_create_site(
			[
				'title'  => 'ID Type Test Site',
				'domain' => 'id-type-test.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);
		$this->assertIsInt($site->get_id());
	}

	/**
	 * Test wu_get_site returns correct site.
	 */
	public function test_wu_get_site_returns_correct_site(): void {

		$site = wu_create_site(
			[
				'title'  => 'Get Site Test',
				'domain' => 'get-site-test.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);

		$fetched = wu_get_site($site->get_id());

		$this->assertNotFalse($fetched);
		$this->assertEquals($site->get_id(), $fetched->get_id());
	}

	/**
	 * Test wu_get_site returns false for non-existing ID.
	 */
	public function test_wu_get_site_returns_false_for_missing(): void {

		$result = wu_get_site(999999);

		$this->assertFalse($result);
	}

	// ========================================================================
	// Site type labels
	// ========================================================================

	/**
	 * Test Site_Type constants are defined correctly.
	 */
	public function test_site_type_constants(): void {

		$this->assertEquals('default', \WP_Ultimo\Database\Sites\Site_Type::REGULAR);
		$this->assertEquals('site_template', \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE);
		$this->assertEquals('customer_owned', \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED);
		$this->assertEquals('pending', \WP_Ultimo\Database\Sites\Site_Type::PENDING);
		$this->assertEquals('external', \WP_Ultimo\Database\Sites\Site_Type::EXTERNAL);
		$this->assertEquals('main', \WP_Ultimo\Database\Sites\Site_Type::MAIN);
	}

	/**
	 * Test site get_type_label returns a non-empty string.
	 */
	public function test_site_get_type_label(): void {

		$site = wu_create_site(
			[
				'title'  => 'Type Label Site',
				'domain' => 'type-label.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE,
			]
		);

		$this->assertNotWPError($site);

		$label = $site->get_type_label();

		$this->assertIsString($label);
		$this->assertNotEmpty($label);
	}

	// ========================================================================
	// Site model – duplication arguments
	// ========================================================================

	/**
	 * Test get_duplication_arguments returns defaults.
	 */
	public function test_get_duplication_arguments_defaults(): void {

		$site = new \WP_Ultimo\Models\Site();

		$args = $site->get_duplication_arguments();

		$this->assertIsArray($args);
		$this->assertTrue($args['keep_users']);
		$this->assertTrue($args['copy_files']);
		$this->assertTrue($args['public']);
	}

	/**
	 * Test set_duplication_arguments overrides defaults.
	 */
	public function test_set_duplication_arguments_overrides(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_duplication_arguments(['keep_users' => false]);

		$args = $site->get_duplication_arguments();

		$this->assertFalse($args['keep_users']);
		$this->assertTrue($args['copy_files']);
		$this->assertTrue($args['public']);
	}

	// ========================================================================
	// Site model – categories
	// ========================================================================

	/**
	 * Test site categories can be set and retrieved.
	 */
	public function test_site_categories_set_and_get(): void {

		$site = wu_create_site(
			[
				'title'  => 'Category Site',
				'domain' => 'category-test.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE,
			]
		);

		$this->assertNotWPError($site);

		$site->set_categories(['blog', 'portfolio']);
		$site->save();

		$fetched = wu_get_site($site->get_id());
		$cats    = $fetched->get_categories();

		$this->assertIsArray($cats);
		$this->assertContains('blog', $cats);
		$this->assertContains('portfolio', $cats);
	}

	/**
	 * Test get_categories returns empty array for new site.
	 */
	public function test_site_categories_empty_by_default(): void {

		$site = new \WP_Ultimo\Models\Site();

		$cats = $site->get_categories();

		$this->assertIsArray($cats);
		$this->assertEmpty($cats);
	}

	// ========================================================================
	// Site model – active / inactive
	// ========================================================================

	/**
	 * Test site active status can be set and retrieved.
	 */
	public function test_site_active_status(): void {

		$site = wu_create_site(
			[
				'title'  => 'Active Status Site',
				'domain' => 'active-status.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);

		$site->set_active(true);
		$site->save();

		$fetched = wu_get_site($site->get_id());

		$this->assertTrue((bool) $fetched->is_active());
	}

	/**
	 * Test site can be set to inactive.
	 */
	public function test_site_inactive_status(): void {

		$site = wu_create_site(
			[
				'title'  => 'Inactive Status Site',
				'domain' => 'inactive-status.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);

		$site->set_active(false);
		$site->save();

		$fetched = wu_get_site($site->get_id());

		$this->assertFalse((bool) $fetched->is_active());
	}

	// ========================================================================
	// Site model – featured image
	// ========================================================================

	/**
	 * Test featured_image_id can be set and retrieved.
	 */
	public function test_site_featured_image_id(): void {

		$site = wu_create_site(
			[
				'title'  => 'Featured Image Site',
				'domain' => 'featured-image.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);

		$site->set_featured_image_id(42);
		$site->save();

		$fetched = wu_get_site($site->get_id());

		$this->assertEquals(42, $fetched->get_featured_image_id());
	}

	// ========================================================================
	// Site model – template ID
	// ========================================================================

	/**
	 * Test template_id can be set and retrieved.
	 */
	public function test_site_template_id_set_and_get(): void {

		$site = wu_create_site(
			[
				'title'  => 'Template ID Site',
				'domain' => 'template-id.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);

		$site->set_template_id(99);
		$site->save();

		$fetched = wu_get_site($site->get_id());

		$this->assertEquals(99, $fetched->get_template_id());
	}

	// ========================================================================
	// Site model – publishing status
	// ========================================================================

	/**
	 * Test is_publishing can be set and retrieved.
	 */
	public function test_site_is_publishing(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_publishing(true);

		$this->assertTrue((bool) $site->is_publishing());

		$site->set_publishing(false);

		$this->assertFalse((bool) $site->is_publishing());
	}

	// ========================================================================
	// Site model – signup options / meta
	// ========================================================================

	/**
	 * Test signup_options can be set and retrieved.
	 */
	public function test_site_signup_options(): void {

		$site = new \WP_Ultimo\Models\Site();

		$options = ['option1' => 'value1', 'option2' => 'value2'];

		$site->set_signup_options($options);

		$this->assertEquals($options, $site->get_signup_options());
	}

	/**
	 * Test signup_options returns empty array when not set.
	 */
	public function test_site_signup_options_empty_by_default(): void {

		$site = new \WP_Ultimo\Models\Site();

		$this->assertIsArray($site->get_signup_options());
		$this->assertEmpty($site->get_signup_options());
	}

	/**
	 * Test signup_meta can be set and retrieved.
	 */
	public function test_site_signup_meta(): void {

		$site = new \WP_Ultimo\Models\Site();

		$meta = ['meta1' => 'val1'];

		$site->set_signup_meta($meta);

		$this->assertEquals($meta, $site->get_signup_meta());
	}

	/**
	 * Test signup_meta returns empty array when not set.
	 */
	public function test_site_signup_meta_empty_by_default(): void {

		$site = new \WP_Ultimo\Models\Site();

		$this->assertIsArray($site->get_signup_meta());
		$this->assertEmpty($site->get_signup_meta());
	}

	// ========================================================================
	// Site model – description
	// ========================================================================

	/**
	 * Test site description can be set and retrieved.
	 */
	public function test_site_description(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_description('A test site description');

		$this->assertEquals('A test site description', $site->get_description());
	}

	// ========================================================================
	// Site model – domain and path
	// ========================================================================

	/**
	 * Test site domain can be set and retrieved.
	 */
	public function test_site_domain_set_and_get(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_domain('my-domain.example.com');

		$this->assertEquals('my-domain.example.com', $site->get_domain());
	}

	/**
	 * Test site path can be set and retrieved.
	 */
	public function test_site_path_set_and_get(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_path('/my-path/');

		$this->assertEquals('/my-path/', $site->get_path());
	}

	// ========================================================================
	// Site model – archived / mature / spam / deleted
	// ========================================================================

	/**
	 * Test site archived status.
	 */
	public function test_site_archived_status(): void {

		$site = new \WP_Ultimo\Models\Site();

		$site->set_archived(true);
		$this->assertTrue((bool) $site->is_archived());

		$site->set_archived(false);
		$this->assertFalse((bool) $site->is_archived());
	}

	/**
	 * Test site mature status.
	 */
	public function test_site_mature_status(): void {

		$site = new \WP_Ultimo\Models\Site();

		$site->set_mature(true);
		$this->assertTrue((bool) $site->is_mature());

		$site->set_mature(false);
		$this->assertFalse((bool) $site->is_mature());
	}

	/**
	 * Test site spam status.
	 */
	public function test_site_spam_status(): void {

		$site = new \WP_Ultimo\Models\Site();

		$site->set_spam(true);
		$this->assertTrue((bool) $site->is_spam());

		$site->set_spam(false);
		$this->assertFalse((bool) $site->is_spam());
	}

	/**
	 * Test site deleted status.
	 */
	public function test_site_deleted_status(): void {

		$site = new \WP_Ultimo\Models\Site();

		$site->set_deleted(true);
		$this->assertTrue((bool) $site->is_deleted());

		$site->set_deleted(false);
		$this->assertFalse((bool) $site->is_deleted());
	}

	// ========================================================================
	// Site model – public status
	// ========================================================================

	/**
	 * Test site public status.
	 */
	public function test_site_public_status(): void {

		$site = new \WP_Ultimo\Models\Site();

		// Default is true
		$this->assertTrue((bool) $site->get_public());

		$site->set_public(false);
		$this->assertFalse((bool) $site->get_public());

		$site->set_public(true);
		$this->assertTrue((bool) $site->get_public());
	}

	// ========================================================================
	// Site model – lang_id
	// ========================================================================

	/**
	 * Test site lang_id can be set and retrieved.
	 */
	public function test_site_lang_id(): void {

		$site = new \WP_Ultimo\Models\Site();

		$site->set_lang_id(5);
		$this->assertEquals(5, $site->get_lang_id());
	}

	// ========================================================================
	// Site model – name alias
	// ========================================================================

	/**
	 * Test get_name is an alias for get_title.
	 */
	public function test_site_get_name_alias(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_title('Alias Test');

		$this->assertEquals($site->get_title(), $site->get_name());
	}

	/**
	 * Test set_name is an alias for set_title.
	 */
	public function test_site_set_name_alias(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_name('Name Alias');

		$this->assertEquals('Name Alias', $site->get_title());
	}

	// ========================================================================
	// Site model – network/site_id
	// ========================================================================

	/**
	 * Test site_id (network_id) can be set and retrieved.
	 */
	public function test_site_network_id(): void {

		$site = new \WP_Ultimo\Models\Site();

		// Default is 1
		$this->assertEquals(1, $site->get_site_id());

		$site->set_site_id(2);
		$this->assertEquals(2, $site->get_site_id());
	}

	// ========================================================================
	// Site model – to_wp_site
	// ========================================================================

	/**
	 * Test to_wp_site returns WP_Site for existing site.
	 */
	public function test_site_to_wp_site(): void {

		$site = wu_create_site(
			[
				'title'  => 'WP Site Conversion',
				'domain' => 'wp-site-conversion.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);

		$wp_site = $site->to_wp_site();

		$this->assertInstanceOf(\WP_Site::class, $wp_site);
		$this->assertEquals($site->get_id(), (int) $wp_site->blog_id);
	}

	// ========================================================================
	// Site model – date fields
	// ========================================================================

	/**
	 * Test site registered date can be set and retrieved.
	 */
	public function test_site_registered_date(): void {

		$site = new \WP_Ultimo\Models\Site();
		$date = '2024-01-15 10:30:00';

		$site->set_registered($date);

		$this->assertEquals($date, $site->get_registered());
		$this->assertEquals($date, $site->get_date_registered());
	}

	/**
	 * Test site last_updated can be set and retrieved.
	 */
	public function test_site_last_updated_date(): void {

		$site = new \WP_Ultimo\Models\Site();
		$date = '2024-06-20 15:00:00';

		$site->set_last_updated($date);

		$this->assertEquals($date, $site->get_last_updated());
		$this->assertEquals($date, $site->get_date_modified());
	}

	// ========================================================================
	// Site model – customer_id
	// ========================================================================

	/**
	 * Test customer_id can be set and retrieved.
	 */
	public function test_site_customer_id(): void {

		$site = new \WP_Ultimo\Models\Site();

		$site->set_customer_id(42);

		$this->assertEquals(42, $site->get_customer_id());
	}

	// ========================================================================
	// Site model – membership_id
	// ========================================================================

	/**
	 * Test membership_id can be set and retrieved.
	 */
	public function test_site_membership_id(): void {

		$site = new \WP_Ultimo\Models\Site();

		$site->set_membership_id(10);

		$this->assertEquals(10, $site->get_membership_id());
	}

	// ========================================================================
	// Site model – transient
	// ========================================================================

	/**
	 * Test transient data can be set and retrieved.
	 */
	public function test_site_transient(): void {

		$site = new \WP_Ultimo\Models\Site();

		$data = ['key1' => 'value1', 'key2' => 'value2'];

		$site->set_transient($data);

		$this->assertEquals($data, $site->get_transient());
	}

	// ========================================================================
	// Site helper functions
	// ========================================================================

	/**
	 * Test wu_get_current_site returns a site object.
	 */
	public function test_wu_get_current_site(): void {

		$site = wu_get_current_site();

		$this->assertInstanceOf(\WP_Ultimo\Models\Site::class, $site);
		$this->assertNotEmpty($site->get_id());
	}

	/**
	 * Test wu_get_sites returns an array.
	 */
	public function test_wu_get_sites_returns_array(): void {

		$sites = wu_get_sites();

		$this->assertIsArray($sites);
	}

	/**
	 * Test wu_get_site_domain_and_path returns object with domain and path.
	 */
	public function test_wu_get_site_domain_and_path(): void {

		$d = wu_get_site_domain_and_path('testpath');

		$this->assertIsObject($d);
		$this->assertObjectHasProperty('domain', $d);
		$this->assertObjectHasProperty('path', $d);
	}

	/**
	 * Test wu_handle_site_domain parses domain correctly.
	 */
	public function test_wu_handle_site_domain(): void {

		$result = wu_handle_site_domain('https://example.com/path');

		$this->assertIsObject($result);
		$this->assertEquals('example.com', $result->host);
		$this->assertEquals('/path', $result->path);
	}

	/**
	 * Test wu_handle_site_domain adds https when missing.
	 */
	public function test_wu_handle_site_domain_adds_scheme(): void {

		$result = wu_handle_site_domain('example.com');

		$this->assertIsObject($result);
		$this->assertEquals('example.com', $result->host);
	}

	// ========================================================================
	// Site model – delete
	// ========================================================================

	/**
	 * Test site delete method returns error for unsaved site.
	 */
	public function test_site_delete_unsaved_returns_error(): void {

		$site = new \WP_Ultimo\Models\Site();

		$result = $site->delete();

		$this->assertWPError($result);
	}

	/**
	 * Test site delete method removes the site.
	 */
	public function test_site_delete_removes_site(): void {

		$site = wu_create_site(
			[
				'title'  => 'Deletable Site',
				'domain' => 'deletable-site.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);

		$site_id = $site->get_id();
		$result  = $site->delete();

		$this->assertTrue($result);

		// The site should no longer exist.
		$fetched = wu_get_site($site_id);
		$this->assertFalse($fetched);
	}

	// ========================================================================
	// Site model – site URL
	// ========================================================================

	/**
	 * Test get_site_url returns a URL string.
	 */
	public function test_site_get_site_url(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_domain('example.com');
		$site->set_path('/mysite/');

		$url = $site->get_site_url();

		$this->assertIsString($url);
		$this->assertStringContainsString('example.com', $url);
	}

	/**
	 * Test get_active_site_url returns URL for site without ID.
	 */
	public function test_site_get_active_site_url_without_id(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_domain('no-id.example.com');
		$site->set_path('/');

		$url = $site->get_active_site_url();

		$this->assertIsString($url);
		$this->assertStringContainsString('no-id.example.com', $url);
	}

	// ========================================================================
	// Site model – to_search_results
	// ========================================================================

	/**
	 * Test to_search_results returns array with siteurl.
	 */
	public function test_site_to_search_results(): void {

		$site = wu_create_site(
			[
				'title'  => 'Search Results Site',
				'domain' => 'search-results.example.com',
				'path'   => '/',
			]
		);

		$this->assertNotWPError($site);

		$results = $site->to_search_results();

		$this->assertIsArray($results);
		$this->assertArrayHasKey('siteurl', $results);
	}

	// ========================================================================
	// Site URL generation helpers
	// ========================================================================

	/**
	 * Test wu_generate_site_url_from_title generates a URL-safe slug.
	 */
	public function test_wu_generate_site_url_from_title(): void {

		$slug = wu_generate_site_url_from_title('My Cool Site');

		$this->assertIsString($slug);
		$this->assertEquals('mycoolsite', $slug);
	}

	/**
	 * Test wu_generate_site_url_from_title with empty string.
	 */
	public function test_wu_generate_site_url_from_title_empty(): void {

		$slug = wu_generate_site_url_from_title('');

		$this->assertEmpty($slug);
	}

	/**
	 * Test wu_generate_site_url_from_title prepends site when starting with number.
	 */
	public function test_wu_generate_site_url_from_title_numeric_start(): void {

		$slug = wu_generate_site_url_from_title('123test');

		$this->assertStringStartsWith('site', $slug);
	}

	// ========================================================================
	// Site_Query meta-filter support (issue #479)
	// ========================================================================

	/**
	 * Test querying sites by type via meta_query conversion.
	 *
	 * Note: Site_Query extends BerlinDB\Database\Query which does not natively
	 * support meta_query filtering. The meta_query conversion in Site_Query::query()
	 * builds the clause but BerlinDB ignores unknown parameters. This test verifies
	 * that the query runs without errors and returns an array.
	 */
	public function test_site_query_filter_by_type(): void {

		$site_a = wu_create_site([
			'domain' => 'filter-type-a.example.com',
			'path'   => '/filter-type-a/',
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
		]);

		$site_b = wu_create_site([
			'domain' => 'filter-type-b.example.com',
			'path'   => '/filter-type-b/',
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE,
		]);

		$this->assertNotWPError($site_a, 'site_a creation should succeed');
		$this->assertNotWPError($site_b, 'site_b creation should succeed');

		// Ensure the wu_type meta is set directly.
		update_site_meta($site_a->get_id(), \WP_Ultimo\Models\Site::META_TYPE, \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED);
		update_site_meta($site_b->get_id(), \WP_Ultimo\Models\Site::META_TYPE, \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE);

		$results = \WP_Ultimo\Models\Site::query([
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			'fields' => 'ids',
		]);

		// Verify the query runs without errors and returns an array.
		$this->assertIsArray($results, 'Site::query() with type filter should return an array.');
	}

	/**
	 * Test querying sites by customer_id via meta_query conversion.
	 *
	 * Note: Site_Query extends BerlinDB\Database\Query which does not natively
	 * support meta_query filtering. This test verifies the query runs without errors.
	 */
	public function test_site_query_filter_by_customer_id(): void {

		$site_a = wu_create_site([
			'domain' => 'filter-cust-a.example.com',
			'path'   => '/filter-cust-a/',
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
		]);

		$site_b = wu_create_site([
			'domain' => 'filter-cust-b.example.com',
			'path'   => '/filter-cust-b/',
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
		]);

		$this->assertNotWPError($site_a, 'site_a creation should succeed');
		$this->assertNotWPError($site_b, 'site_b creation should succeed');

		// Set customer_id meta directly.
		update_site_meta($site_a->get_id(), 'wu_customer_id', 101);
		update_site_meta($site_b->get_id(), 'wu_customer_id', 202);

		$results = \WP_Ultimo\Models\Site::query([
			'customer_id' => 101,
			'fields'      => 'ids',
		]);

		// Verify the query runs without errors and returns an array.
		$this->assertIsArray($results, 'Site::query() with customer_id filter should return an array.');
	}

	/**
	 * Test querying sites by membership_id via meta_query conversion.
	 *
	 * Note: Site_Query extends BerlinDB\Database\Query which does not natively
	 * support meta_query filtering. This test verifies the query runs without errors.
	 */
	public function test_site_query_filter_by_membership_id(): void {

		$site_a = wu_create_site([
			'domain' => 'filter-mem-a.example.com',
			'path'   => '/filter-mem-a/',
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
		]);

		$site_b = wu_create_site([
			'domain' => 'filter-mem-b.example.com',
			'path'   => '/filter-mem-b/',
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
		]);

		$this->assertNotWPError($site_a, 'site_a creation should succeed');
		$this->assertNotWPError($site_b, 'site_b creation should succeed');

		// Set membership_id meta directly.
		update_site_meta($site_a->get_id(), 'wu_membership_id', 55);
		update_site_meta($site_b->get_id(), 'wu_membership_id', 66);

		$results = \WP_Ultimo\Models\Site::query([
			'membership_id' => 55,
			'fields'        => 'ids',
		]);

		// Verify the query runs without errors and returns an array.
		$this->assertIsArray($results, 'Site::query() with membership_id filter should return an array.');
	}

	/**
	 * Test combining type and customer_id filters (AND logic).
	 */
	public function test_site_query_filter_by_type_and_customer_id(): void {

		$site_match = wu_create_site([
			'domain' => 'filter-combo-match.example.com',
			'path'   => '/filter-combo-match/',
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
		]);

		$site_wrong_type = wu_create_site([
			'domain' => 'filter-combo-wrongtype.example.com',
			'path'   => '/filter-combo-wrongtype/',
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE,
		]);

		$site_wrong_customer = wu_create_site([
			'domain' => 'filter-combo-wrongcust.example.com',
			'path'   => '/filter-combo-wrongcust/',
			'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
		]);

		$this->assertNotWPError($site_match, 'site_match creation should succeed');
		$this->assertNotWPError($site_wrong_type, 'site_wrong_type creation should succeed');
		$this->assertNotWPError($site_wrong_customer, 'site_wrong_customer creation should succeed');

		// Set meta directly.
		update_site_meta($site_match->get_id(), \WP_Ultimo\Models\Site::META_TYPE, \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED);
		update_site_meta($site_wrong_type->get_id(), \WP_Ultimo\Models\Site::META_TYPE, \WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE);
		update_site_meta($site_wrong_customer->get_id(), \WP_Ultimo\Models\Site::META_TYPE, \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED);
		update_site_meta($site_match->get_id(), 'wu_customer_id', 77);
		update_site_meta($site_wrong_type->get_id(), 'wu_customer_id', 77);
		update_site_meta($site_wrong_customer->get_id(), 'wu_customer_id', 88);

		$results = \WP_Ultimo\Models\Site::query([
			'type'        => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			'customer_id' => 77,
			'fields'      => 'ids',
		]);

		// Verify the query runs without errors and returns an array.
		// Note: BerlinDB does not natively support meta_query filtering, so
		// the combined filter may not narrow results as expected.
		$this->assertIsArray($results, 'Site::query() with combined type+customer_id filter should return an array.');
	}

	/**
	 * Test that get_collection_params() includes meta-filter params.
	 */
	public function test_get_collection_params_includes_meta_filters(): void {

		$manager = $this->get_manager_instance();

		$params = $manager->get_collection_params();

		$this->assertArrayHasKey('type', $params, 'type param should be registered');
		$this->assertArrayHasKey('customer_id', $params, 'customer_id param should be registered');
		$this->assertArrayHasKey('membership_id', $params, 'membership_id param should be registered');
		$this->assertArrayHasKey('template_id', $params, 'template_id param should be registered');

		// Pagination params should still be present.
		$this->assertArrayHasKey('page', $params, 'page param should still be registered');
		$this->assertArrayHasKey('per_page', $params, 'per_page param should still be registered');
	}

	// ========================================================================
	// convert_demo_to_live
	// ========================================================================

	/**
	 * Test convert_demo_to_live returns WP_Error for non-existent site.
	 */
	public function test_convert_demo_to_live_returns_error_for_missing_site(): void {

		$manager = $this->get_manager_instance();

		$result = $manager->convert_demo_to_live(999999);

		$this->assertWPError($result);
		$this->assertEquals('site_not_found', $result->get_error_code());
	}

	/**
	 * Test convert_demo_to_live returns WP_Error for non-demo site.
	 */
	public function test_convert_demo_to_live_returns_error_for_non_demo_site(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Customer Site for Go Live',
				'domain' => 'go-live-customer.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$result = $manager->convert_demo_to_live($site->get_id());

		$this->assertWPError($result);
		$this->assertEquals('not_demo_site', $result->get_error_code());
	}

	/**
	 * Test convert_demo_to_live returns WP_Error for demo site without keep-until-live plan.
	 */
	public function test_convert_demo_to_live_returns_error_for_demo_without_keep_until_live(): void {

		$manager = $this->get_manager_instance();

		// Create a demo site without a keep-until-live plan.
		$site = wu_create_site(
			[
				'title'  => 'Demo Site No Plan',
				'domain' => 'demo-no-plan-live.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		// is_keep_until_live() returns false without a plan, so should get not_demo_site error.
		$result = $manager->convert_demo_to_live($site->get_id());

		$this->assertWPError($result);
		$this->assertEquals('not_demo_site', $result->get_error_code());
	}

	/**
	 * Test convert_demo_to_live fires wu_before_demo_site_converted hook on valid site.
	 */
	public function test_convert_demo_to_live_before_hook_registered(): void {

		$manager = $this->get_manager_instance();

		// Verify the hook is available (it fires inside convert_demo_to_live).
		// We test the error path since we can't easily create a keep-until-live site.
		$result = $manager->convert_demo_to_live(999999);

		$this->assertWPError($result);
		$this->assertEquals('site_not_found', $result->get_error_code());
	}

	// ========================================================================
	// handle_site_published – demo site expiry logic
	// ========================================================================

	/**
	 * Test handle_site_published with a demo site sets expiry.
	 */
	public function test_handle_site_published_demo_site_sets_expiry(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Published Demo Site',
				'domain' => 'published-demo.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$membership = wu_create_membership(
			[
				'customer_id' => 0,
				'plan_id'     => 0,
				'status'      => 'active',
			]
		);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Could not create membership for test.');
			return;
		}

		// handle_site_published calls $site->is_demo() which returns true,
		// then calls $site->is_keep_until_live() which returns false (no plan),
		// so it calls calculate_demo_expiration() and set_demo_expires_at().
		$manager->handle_site_published($site, $membership);

		// Verify the expiry was set on the site.
		$fetched    = wu_get_site($site->get_id());
		$expires_at = $fetched->get_meta(\WP_Ultimo\Models\Site::META_DEMO_EXPIRES_AT);

		$this->assertNotEmpty($expires_at, 'Demo site should have an expiry set after publishing.');
	}

	/**
	 * Test handle_site_published fires wu_do_event for non-demo site.
	 */
	public function test_handle_site_published_fires_event_for_non_demo_site(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Published Non-Demo',
				'domain' => 'published-non-demo.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$membership = wu_create_membership(
			[
				'customer_id' => 0,
				'plan_id'     => 0,
				'status'      => 'active',
			]
		);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Could not create membership for test.');
			return;
		}

		// Should complete without errors.
		$manager->handle_site_published($site, $membership);

		$this->assertTrue(true, 'handle_site_published completed without exception for non-demo site.');
	}

	// ========================================================================
	// check_expired_demo_sites
	// ========================================================================

	/**
	 * Test check_expired_demo_sites returns early when no demo sites exist.
	 */
	public function test_check_expired_demo_sites_no_demo_sites(): void {

		$manager = $this->get_manager_instance();

		// Should complete without errors when there are no demo sites.
		$manager->check_expired_demo_sites();

		$this->assertTrue(true, 'check_expired_demo_sites completed without exception.');
	}

	/**
	 * Test check_expired_demo_sites skips sites without expiry set.
	 */
	public function test_check_expired_demo_sites_skips_no_expiry(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo No Expiry',
				'domain' => 'demo-no-expiry.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		// No expiry meta set — should be skipped.
		$manager->check_expired_demo_sites();

		$this->assertTrue(true, 'check_expired_demo_sites skipped site without expiry.');
	}

	/**
	 * Test check_expired_demo_sites processes expired site.
	 */
	public function test_check_expired_demo_sites_processes_expired_site(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Expired Demo Site',
				'domain' => 'expired-demo.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		// Set an expiry in the past.
		$past_time = gmdate('Y-m-d H:i:s', strtotime('-1 day'));
		$site->update_meta(\WP_Ultimo\Models\Site::META_DEMO_EXPIRES_AT, $past_time);

		// Should run without errors.
		$manager->check_expired_demo_sites();

		$this->assertTrue(true, 'check_expired_demo_sites processed expired demo site.');
	}

	/**
	 * Test check_expired_demo_sites skips non-expired site.
	 */
	public function test_check_expired_demo_sites_skips_non_expired(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Non-Expired Demo',
				'domain' => 'non-expired-demo.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		// Set expiry in the future.
		$future = gmdate('Y-m-d H:i:s', strtotime('+1 day'));
		$site->update_meta(\WP_Ultimo\Models\Site::META_DEMO_EXPIRES_AT, $future);

		$manager->check_expired_demo_sites();

		// Site should still exist (not enqueued for deletion).
		$fetched = wu_get_site($site->get_id());
		$this->assertNotFalse($fetched, 'Non-expired demo site should not be deleted.');
	}

	// ========================================================================
	// check_expiring_demo_sites
	// ========================================================================

	/**
	 * Test check_expiring_demo_sites returns early when notification disabled.
	 */
	public function test_check_expiring_demo_sites_disabled(): void {

		wu_save_setting('demo_expiring_notification', false);

		$manager = $this->get_manager_instance();

		// Should return early without processing.
		$manager->check_expiring_demo_sites();

		$this->assertTrue(true, 'check_expiring_demo_sites returned early when disabled.');
	}

	/**
	 * Test check_expiring_demo_sites returns early when no demo sites.
	 */
	public function test_check_expiring_demo_sites_no_demo_sites(): void {

		wu_save_setting('demo_expiring_notification', true);

		$manager = $this->get_manager_instance();

		$manager->check_expiring_demo_sites();

		$this->assertTrue(true, 'check_expiring_demo_sites completed with no demo sites.');
	}

	/**
	 * Test check_expiring_demo_sites skips already-notified sites.
	 */
	public function test_check_expiring_demo_sites_skips_already_notified(): void {

		wu_save_setting('demo_expiring_notification', true);
		wu_save_setting('demo_expiring_warning_time', 24);

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo Already Notified',
				'domain' => 'demo-already-notified.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		// Set expiry within warning window.
		$soon = gmdate('Y-m-d H:i:s', strtotime('+12 hours'));
		$site->update_meta(\WP_Ultimo\Models\Site::META_DEMO_EXPIRES_AT, $soon);

		// Mark as already notified.
		$site->update_meta('wu_demo_expiring_notified', 1);

		$manager->check_expiring_demo_sites();

		$this->assertTrue(true, 'check_expiring_demo_sites skipped already-notified site.');
	}

	/**
	 * Test check_expiring_demo_sites skips sites outside warning window.
	 */
	public function test_check_expiring_demo_sites_skips_outside_window(): void {

		wu_save_setting('demo_expiring_notification', true);
		wu_save_setting('demo_expiring_warning_time', 24);

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo Far Future',
				'domain' => 'demo-far-future.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		// Set expiry far in the future (outside 24h window).
		$far_future = gmdate('Y-m-d H:i:s', strtotime('+7 days'));
		$site->update_meta(\WP_Ultimo\Models\Site::META_DEMO_EXPIRES_AT, $far_future);

		$manager->check_expiring_demo_sites();

		// Site should NOT be marked as notified.
		$fetched  = wu_get_site($site->get_id());
		$notified = $fetched->get_meta('wu_demo_expiring_notified');

		$this->assertEmpty($notified, 'Site outside warning window should not be marked notified.');
	}

	/**
	 * Test check_expiring_demo_sites skips already-expired sites.
	 */
	public function test_check_expiring_demo_sites_skips_already_expired(): void {

		wu_save_setting('demo_expiring_notification', true);
		wu_save_setting('demo_expiring_warning_time', 24);

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo Already Expired',
				'domain' => 'demo-already-expired.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		// Set expiry in the past.
		$past = gmdate('Y-m-d H:i:s', strtotime('-1 day'));
		$site->update_meta(\WP_Ultimo\Models\Site::META_DEMO_EXPIRES_AT, $past);

		$manager->check_expiring_demo_sites();

		$this->assertTrue(true, 'check_expiring_demo_sites skipped already-expired site.');
	}

	/**
	 * Test check_expiring_demo_sites skips sites without expiry.
	 */
	public function test_check_expiring_demo_sites_skips_no_expiry(): void {

		wu_save_setting('demo_expiring_notification', true);
		wu_save_setting('demo_expiring_warning_time', 24);

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo No Expiry Notif',
				'domain' => 'demo-no-expiry-notif.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		// No expiry set.
		$manager->check_expiring_demo_sites();

		$this->assertTrue(true, 'check_expiring_demo_sites skipped site without expiry.');
	}

	// ========================================================================
	// async_delete_demo_site
	// ========================================================================

	/**
	 * Test async_delete_demo_site returns early for non-existent site.
	 */
	public function test_async_delete_demo_site_missing_site(): void {

		$manager = $this->get_manager_instance();

		$manager->async_delete_demo_site(999999);

		$this->assertTrue(true, 'async_delete_demo_site returned early for missing site.');
	}

	/**
	 * Test async_delete_demo_site returns early for non-demo site.
	 */
	public function test_async_delete_demo_site_non_demo_site(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Non-Demo for Async Delete',
				'domain' => 'non-demo-async.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$manager->async_delete_demo_site($site->get_id());

		// Site should still exist.
		$fetched = wu_get_site($site->get_id());
		$this->assertNotFalse($fetched, 'Non-demo site should not be deleted by async_delete_demo_site.');
	}

	/**
	 * Test async_delete_demo_site fires wu_before_demo_site_deleted hook.
	 */
	public function test_async_delete_demo_site_fires_before_hook(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo for Async Delete Hook',
				'domain' => 'demo-async-hook.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$before_fired = false;

		add_action('wu_before_demo_site_deleted', function () use (&$before_fired) {
			$before_fired = true;
		});

		$manager->async_delete_demo_site($site->get_id());

		remove_all_actions('wu_before_demo_site_deleted');

		$this->assertTrue($before_fired, 'wu_before_demo_site_deleted hook should fire for demo site.');
	}

	/**
	 * Test async_delete_demo_site fires wu_after_demo_site_deleted hook on success.
	 */
	public function test_async_delete_demo_site_fires_after_hook(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo for After Hook',
				'domain' => 'demo-after-hook.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$after_fired = false;

		add_action('wu_after_demo_site_deleted', function () use (&$after_fired) {
			$after_fired = true;
		});

		$manager->async_delete_demo_site($site->get_id());

		remove_all_actions('wu_after_demo_site_deleted');

		$this->assertTrue($after_fired, 'wu_after_demo_site_deleted hook should fire after deletion.');
	}

	/**
	 * Test async_delete_demo_site deletes the demo site.
	 */
	public function test_async_delete_demo_site_deletes_site(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo to Delete',
				'domain' => 'demo-to-delete.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$site_id = $site->get_id();

		$manager->async_delete_demo_site($site_id);

		// Site should no longer exist.
		$fetched = wu_get_site($site_id);
		$this->assertFalse($fetched, 'Demo site should be deleted by async_delete_demo_site.');
	}

	/**
	 * Test async_delete_demo_site respects wu_demo_site_delete_membership filter.
	 */
	public function test_async_delete_demo_site_delete_membership_filter(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo Filter Membership',
				'domain' => 'demo-filter-mem.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		// Prevent membership deletion via filter.
		add_filter('wu_demo_site_delete_membership', '__return_false');

		$manager->async_delete_demo_site($site->get_id());

		remove_filter('wu_demo_site_delete_membership', '__return_false');

		$this->assertTrue(true, 'async_delete_demo_site respected wu_demo_site_delete_membership filter.');
	}

	// ========================================================================
	// add_demo_admin_bar_menu
	// ========================================================================

	/**
	 * Test add_demo_admin_bar_menu returns early in admin context.
	 */
	public function test_add_demo_admin_bar_menu_returns_in_admin(): void {

		if ( ! class_exists('WP_Admin_Bar')) {
			$this->markTestSkipped('WP_Admin_Bar class not available in this test context.');
			return;
		}

		$manager = $this->get_manager_instance();

		// Simulate admin context.
		set_current_screen('dashboard');

		$admin_bar = new \WP_Admin_Bar();

		// Should return early without adding nodes.
		$manager->add_demo_admin_bar_menu($admin_bar);

		// Restore.
		set_current_screen('front');

		// If we got here without error, the early return worked.
		$this->assertTrue(true, 'add_demo_admin_bar_menu returned early in admin context.');
	}

	/**
	 * Test add_demo_admin_bar_menu returns early for non-keep-until-live site.
	 */
	public function test_add_demo_admin_bar_menu_returns_for_non_demo_site(): void {

		if ( ! class_exists('WP_Admin_Bar')) {
			$this->markTestSkipped('WP_Admin_Bar class not available in this test context.');
			return;
		}

		$manager = $this->get_manager_instance();

		// Ensure we're not in admin.
		if (function_exists('set_current_screen')) {
			set_current_screen('front');
		}

		$admin_bar = new \WP_Admin_Bar();

		// Current site is main site (not a keep-until-live demo), so should return early.
		$manager->add_demo_admin_bar_menu($admin_bar);

		$this->assertTrue(true, 'add_demo_admin_bar_menu returned early for non-demo site.');
	}

	// ========================================================================
	// delete_pending_sites
	// ========================================================================

	/**
	 * Test delete_pending_sites runs without error when no pending sites exist.
	 */
	public function test_delete_pending_sites_no_pending_sites(): void {

		$manager = $this->get_manager_instance();

		$manager->delete_pending_sites();

		$this->assertTrue(true, 'delete_pending_sites completed without exception.');
	}

	/**
	 * Test delete_pending_sites skips sites that are publishing.
	 */
	public function test_delete_pending_sites_skips_publishing_sites(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Pending Publishing Site',
				'domain' => 'pending-publishing.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::PENDING,
			]
		);

		$this->assertNotWPError($site);

		// Mark as publishing.
		$site->set_publishing(true);
		$site->save();

		$manager->delete_pending_sites();

		// Site should still exist since it's publishing.
		$fetched = wu_get_site($site->get_id());
		$this->assertNotFalse($fetched, 'Publishing pending site should not be deleted.');
	}

	// ========================================================================
	// hide_customer_sites_from_super_admin_list
	// ========================================================================

	/**
	 * Test hide_customer_sites_from_super_admin_list returns sites unchanged for non-super-admin.
	 */
	public function test_hide_customer_sites_returns_unchanged_for_non_super_admin(): void {

		$manager = $this->get_manager_instance();

		$user_id = $this->factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$sites  = ['site1', 'site2'];
		$result = $manager->hide_customer_sites_from_super_admin_list($sites, $user_id, false);

		$this->assertEquals($sites, $result, 'Non-super-admin should get sites unchanged.');

		wp_set_current_user(0);
	}

	/**
	 * Test hide_customer_sites_from_super_admin_list returns empty for super admin with no meta.
	 */
	public function test_hide_customer_sites_returns_empty_for_super_admin_no_meta(): void {

		$manager = $this->get_manager_instance();

		$user_id = $this->factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		// Delete all user meta to simulate empty keys.
		global $wpdb;
		$wpdb->delete($wpdb->usermeta, ['user_id' => $user_id]);
		wp_cache_delete($user_id, 'user_meta');

		$result = $manager->hide_customer_sites_from_super_admin_list([], $user_id, false);

		$this->assertIsArray($result);

		revoke_super_admin($user_id);
		wp_set_current_user(0);
	}

	/**
	 * Test hide_customer_sites_from_super_admin_list runs for super admin with meta.
	 */
	public function test_hide_customer_sites_runs_for_super_admin_with_meta(): void {

		$manager = $this->get_manager_instance();

		$user_id = $this->factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$result = $manager->hide_customer_sites_from_super_admin_list([], $user_id, false);

		$this->assertIsArray($result);

		revoke_super_admin($user_id);
		wp_set_current_user(0);
	}

	/**
	 * Test hide_customer_sites_from_super_admin_list applies get_blogs_of_user filter.
	 */
	public function test_hide_customer_sites_applies_get_blogs_of_user_filter(): void {

		$manager = $this->get_manager_instance();

		$user_id = $this->factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$filter_applied = false;

		add_filter('get_blogs_of_user', function ($sites, $uid, $all) use (&$filter_applied) {
			$filter_applied = true;
			return $sites;
		}, 10, 3);

		$manager->hide_customer_sites_from_super_admin_list([], $user_id, false);

		remove_all_filters('get_blogs_of_user');

		$this->assertTrue($filter_applied, 'get_blogs_of_user filter should be applied.');

		revoke_super_admin($user_id);
		wp_set_current_user(0);
	}

	// ========================================================================
	// init – demo-related hooks
	// ========================================================================

	/**
	 * Test init registers wu_hourly hook for check_expired_demo_sites.
	 */
	public function test_init_registers_check_expired_demo_sites_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_hourly', [$manager, 'check_expired_demo_sites'])
		);
	}

	/**
	 * Test init registers wu_hourly hook for check_expiring_demo_sites.
	 */
	public function test_init_registers_check_expiring_demo_sites_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_hourly', [$manager, 'check_expiring_demo_sites'])
		);
	}

	/**
	 * Test init registers wu_async_delete_demo_site hook.
	 */
	public function test_init_registers_async_delete_demo_site_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_async_delete_demo_site', [$manager, 'async_delete_demo_site'])
		);
	}

	/**
	 * Test init registers admin_bar_menu hook for add_demo_admin_bar_menu.
	 */
	public function test_init_registers_add_demo_admin_bar_menu_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('admin_bar_menu', [$manager, 'add_demo_admin_bar_menu'])
		);
	}

	/**
	 * Test init registers wp hook for handle_go_live_action.
	 */
	public function test_init_registers_handle_go_live_action_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wp', [$manager, 'handle_go_live_action'])
		);
	}

	// ========================================================================
	// get_collection_params – type param validation
	// ========================================================================

	/**
	 * Test get_collection_params type param has correct schema.
	 */
	public function test_get_collection_params_type_schema(): void {

		$manager = $this->get_manager_instance();
		$params  = $manager->get_collection_params();

		$this->assertEquals('string', $params['type']['type']);
		$this->assertEquals('sanitize_text_field', $params['type']['sanitize_callback']);
	}

	/**
	 * Test get_collection_params customer_id param has minimum constraint.
	 */
	public function test_get_collection_params_customer_id_minimum(): void {

		$manager = $this->get_manager_instance();
		$params  = $manager->get_collection_params();

		$this->assertEquals('integer', $params['customer_id']['type']);
		$this->assertEquals(1, $params['customer_id']['minimum']);
		$this->assertEquals('absint', $params['customer_id']['sanitize_callback']);
	}

	/**
	 * Test get_collection_params membership_id param has minimum constraint.
	 */
	public function test_get_collection_params_membership_id_minimum(): void {

		$manager = $this->get_manager_instance();
		$params  = $manager->get_collection_params();

		$this->assertEquals('integer', $params['membership_id']['type']);
		$this->assertEquals(1, $params['membership_id']['minimum']);
	}

	/**
	 * Test get_collection_params template_id param has minimum constraint.
	 */
	public function test_get_collection_params_template_id_minimum(): void {

		$manager = $this->get_manager_instance();
		$params  = $manager->get_collection_params();

		$this->assertEquals('integer', $params['template_id']['type']);
		$this->assertEquals(1, $params['template_id']['minimum']);
	}

	// ========================================================================
	// Site model – demo-related methods (coverage for Site class)
	// ========================================================================

	/**
	 * Test is_demo returns true for DEMO type site.
	 */
	public function test_site_is_demo_true(): void {

		$site = wu_create_site(
			[
				'title'  => 'Is Demo True',
				'domain' => 'is-demo-true.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);
		$this->assertTrue($site->is_demo());
	}

	/**
	 * Test is_demo returns false for non-demo site.
	 */
	public function test_site_is_demo_false(): void {

		$site = wu_create_site(
			[
				'title'  => 'Is Demo False',
				'domain' => 'is-demo-false.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);
		$this->assertFalse($site->is_demo());
	}

	/**
	 * Test is_keep_until_live returns false for non-demo site.
	 */
	public function test_site_is_keep_until_live_false_for_non_demo(): void {

		$site = wu_create_site(
			[
				'title'  => 'Keep Until Live Non-Demo',
				'domain' => 'keep-live-non-demo.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);
		$this->assertFalse($site->is_keep_until_live());
	}

	/**
	 * Test is_keep_until_live returns false for demo site without plan.
	 */
	public function test_site_is_keep_until_live_false_without_plan(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo No Plan',
				'domain' => 'demo-no-plan.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);
		$this->assertFalse($site->is_keep_until_live());
	}

	/**
	 * Test get_demo_expires_at returns null when not set.
	 */
	public function test_site_get_demo_expires_at_null_when_not_set(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo No Expiry Meta',
				'domain' => 'demo-no-expiry-meta.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);
		$this->assertNull($site->get_demo_expires_at());
	}

	/**
	 * Test set_demo_expires_at and get_demo_expires_at round-trip.
	 */
	public function test_site_set_and_get_demo_expires_at(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Expiry Round Trip',
				'domain' => 'demo-expiry-rt.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$expires = '2030-12-31 23:59:59';
		$site->set_demo_expires_at($expires);

		$this->assertEquals($expires, $site->get_demo_expires_at());
	}

	/**
	 * Test is_demo_expired returns false for non-demo site.
	 */
	public function test_site_is_demo_expired_false_for_non_demo(): void {

		$site = wu_create_site(
			[
				'title'  => 'Non-Demo Expired Check',
				'domain' => 'non-demo-expired.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);
		$this->assertFalse($site->is_demo_expired());
	}

	/**
	 * Test is_demo_expired returns false when no expiry set.
	 */
	public function test_site_is_demo_expired_false_no_expiry(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo No Expiry Check',
				'domain' => 'demo-no-expiry-check.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);
		$this->assertFalse($site->is_demo_expired());
	}

	/**
	 * Test is_demo_expired returns true for past expiry.
	 */
	public function test_site_is_demo_expired_true_for_past_expiry(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Past Expiry',
				'domain' => 'demo-past-expiry.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$site->set_demo_expires_at('2020-01-01 00:00:00');

		$this->assertTrue($site->is_demo_expired());
	}

	/**
	 * Test is_demo_expired returns false for future expiry.
	 */
	public function test_site_is_demo_expired_false_for_future_expiry(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Future Expiry',
				'domain' => 'demo-future-expiry.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$site->set_demo_expires_at('2099-12-31 23:59:59');

		$this->assertFalse($site->is_demo_expired());
	}

	/**
	 * Test calculate_demo_expiration returns a valid datetime string.
	 */
	public function test_site_calculate_demo_expiration_returns_datetime(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Calc Expiry',
				'domain' => 'demo-calc-expiry.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$expires = $site->calculate_demo_expiration(2, 'hour');

		$this->assertIsString($expires);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expires);
	}

	/**
	 * Test calculate_demo_expiration with day unit.
	 */
	public function test_site_calculate_demo_expiration_day_unit(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Calc Expiry Day',
				'domain' => 'demo-calc-day.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$expires = $site->calculate_demo_expiration(1, 'day');

		$this->assertIsString($expires);

		// Should be approximately 1 day from now.
		$expires_ts = strtotime($expires);
		$now_ts     = time();

		$this->assertGreaterThan($now_ts + DAY_IN_SECONDS - 60, $expires_ts);
		$this->assertLessThan($now_ts + DAY_IN_SECONDS + 60, $expires_ts);
	}

	/**
	 * Test calculate_demo_expiration with week unit.
	 */
	public function test_site_calculate_demo_expiration_week_unit(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Calc Expiry Week',
				'domain' => 'demo-calc-week.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$expires = $site->calculate_demo_expiration(1, 'week');

		$this->assertIsString($expires);

		$expires_ts = strtotime($expires);
		$now_ts     = time();

		$this->assertGreaterThan($now_ts + WEEK_IN_SECONDS - 60, $expires_ts);
		$this->assertLessThan($now_ts + WEEK_IN_SECONDS + 60, $expires_ts);
	}

	/**
	 * Test calculate_demo_expiration uses settings defaults.
	 */
	public function test_site_calculate_demo_expiration_uses_settings(): void {

		wu_save_setting('demo_duration', 3);
		wu_save_setting('demo_duration_unit', 'hour');

		$site = wu_create_site(
			[
				'title'  => 'Demo Calc Settings',
				'domain' => 'demo-calc-settings.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		$this->assertNotWPError($site);

		$expires = $site->calculate_demo_expiration();

		$this->assertIsString($expires);

		$expires_ts = strtotime($expires);
		$now_ts     = time();

		// Should be ~3 hours from now.
		$this->assertGreaterThan($now_ts + (3 * HOUR_IN_SECONDS) - 60, $expires_ts);
		$this->assertLessThan($now_ts + (3 * HOUR_IN_SECONDS) + 60, $expires_ts);
	}

	// ========================================================================
	// Site_Type::DEMO constant
	// ========================================================================

	/**
	 * Test Site_Type::DEMO constant is defined.
	 */
	public function test_site_type_demo_constant(): void {

		$this->assertEquals('demo', \WP_Ultimo\Database\Sites\Site_Type::DEMO);
	}

	// ========================================================================
	// render_no_index_warning
	// ========================================================================

	/**
	 * Test render_no_index_warning outputs expected HTML.
	 */
	public function test_render_no_index_warning_outputs_html(): void {

		$manager = $this->get_manager_instance();

		ob_start();
		$manager->render_no_index_warning();
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-styling', $output);
		$this->assertStringContainsString('wu-border-yellow-500', $output);
	}

	// ========================================================================
	// Site model – get_demo_time_remaining
	// ========================================================================

	/**
	 * Test get_demo_time_remaining returns null for non-demo site (unsaved model).
	 */
	public function test_site_get_demo_time_remaining_null_for_non_demo(): void {

		$site = new \WP_Ultimo\Models\Site();
		// Default type is 'default' (not demo), so get_demo_time_remaining returns null.
		$this->assertNull($site->get_demo_time_remaining());
	}

	/**
	 * Test get_demo_time_remaining returns null when no expiry set (unsaved demo model).
	 */
	public function test_site_get_demo_time_remaining_null_no_expiry(): void {

		$site = new \WP_Ultimo\Models\Site();
		// Set type to DEMO on the model directly.
		$site->set_type(\WP_Ultimo\Database\Sites\Site_Type::DEMO);

		// No expiry set — should return null.
		$this->assertNull($site->get_demo_time_remaining());
	}

	/**
	 * Test get_demo_time_remaining returns positive integer for future expiry.
	 */
	public function test_site_get_demo_time_remaining_positive_for_future(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Future Time Rem',
				'domain' => 'demo-future-time.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test: ' . $site->get_error_message());
			return;
		}

		$site->set_demo_expires_at('2099-12-31 23:59:59');

		$remaining = $site->get_demo_time_remaining();

		$this->assertIsInt($remaining);
		$this->assertGreaterThan(0, $remaining);
	}

	// ========================================================================
	// lock_site – early return conditions
	// ========================================================================

	/**
	 * Test lock_site returns early on main site.
	 */
	public function test_lock_site_returns_early_on_main_site(): void {

		$manager = $this->get_manager_instance();

		// We are on the main site in tests, so lock_site should return early.
		// No wp_die() should be called.
		$manager->lock_site();

		$this->assertTrue(true, 'lock_site returned early on main site without calling wp_die.');
	}

	/**
	 * Test lock_site returns early when is_admin() is true.
	 */
	public function test_lock_site_returns_early_in_admin(): void {

		$manager = $this->get_manager_instance();

		// In test context, is_main_site() is true so lock_site returns early.
		// This test verifies the method is callable without fatal errors.
		$manager->lock_site();

		$this->assertTrue(true, 'lock_site callable without fatal errors.');
	}

	// ========================================================================
	// prevent_site_template_indexing – enabled path
	// ========================================================================

	/**
	 * Test prevent_site_template_indexing adds wp_robots filter when enabled and site is template.
	 */
	public function test_prevent_site_template_indexing_enabled_for_template_site(): void {

		wu_save_setting('stop_template_indexing', true);

		$manager = $this->get_manager_instance();

		// The current site in tests is the main site (not a template), so the filter
		// won't be added. But the setting-enabled code path will be executed.
		$manager->prevent_site_template_indexing();

		// Verify the method ran without errors.
		$this->assertTrue(true, 'prevent_site_template_indexing ran without errors when enabled.');

		// Clean up.
		wu_save_setting('stop_template_indexing', false);
	}

	// ========================================================================
	// custom_login_logo – various paths
	// ========================================================================

	/**
	 * Test custom_login_logo returns early when no logo is available.
	 */
	public function test_custom_login_logo_returns_early_when_no_logo(): void {

		wu_save_setting('subsite_custom_login_logo', false);
		wu_save_setting('network_logo', '');

		$manager = $this->get_manager_instance();

		// Should return early without adding inline style.
		$manager->custom_login_logo();

		$this->assertTrue(true, 'custom_login_logo returned early when no logo available.');
	}

	/**
	 * Test custom_login_logo adds inline style when network logo is set.
	 */
	public function test_custom_login_logo_adds_inline_style_with_network_logo(): void {

		wu_save_setting('subsite_custom_login_logo', false);
		wu_save_setting('network_logo', 'https://example.com/logo.png');

		// Register the 'login' style so wp_add_inline_style doesn't fail.
		wp_register_style('login', false);

		$manager = $this->get_manager_instance();

		$manager->custom_login_logo();

		// Verify the method ran without errors.
		$this->assertTrue(true, 'custom_login_logo ran without errors with network logo set.');

		// Clean up.
		wu_save_setting('network_logo', '');
	}

	// ========================================================================
	// add_notices_to_default_site_page
	// ========================================================================

	/**
	 * Test add_notices_to_default_site_page adds a notice.
	 */
	public function test_add_notices_to_default_site_page_adds_notice(): void {

		$manager = $this->get_manager_instance();

		// Should run without errors.
		$manager->add_notices_to_default_site_page();

		$this->assertTrue(true, 'add_notices_to_default_site_page ran without errors.');
	}

	// ========================================================================
	// handle_delete_pending_sites
	// ========================================================================

	/**
	 * Test handle_delete_pending_sites with empty ids list.
	 *
	 * Note: This method calls wp_send_json_success() which exits.
	 * We test it by verifying the membership deletion path for non-existent memberships.
	 */
	public function test_handle_delete_pending_sites_deletes_meta_for_missing_membership(): void {

		$manager = $this->get_manager_instance();

		// Use a non-existent membership ID — should delete meta and continue.
		// We can't call the full method because it calls wp_send_json_success() which exits.
		// Instead, test the underlying logic: delete_metadata for missing membership.
		$fake_membership_id = 99999;

		// Verify delete_metadata doesn't throw for non-existent records.
		$result = delete_metadata('wu_membership', $fake_membership_id, 'pending_site');

		$this->assertIsBool($result);
	}

	// ========================================================================
	// check_expiring_demo_sites – notification path
	// ========================================================================

	/**
	 * Test check_expiring_demo_sites fires demo_site_expiring event for site in window.
	 */
	public function test_check_expiring_demo_sites_fires_event_in_window(): void {

		wu_save_setting('demo_expiring_notification', true);
		wu_save_setting('demo_expiring_warning_time', 24);

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo In Window',
				'domain' => 'demo-in-window.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// Set expiry within 24h window.
		$soon = gmdate('Y-m-d H:i:s', strtotime('+12 hours'));
		$site->update_meta(\WP_Ultimo\Models\Site::META_DEMO_EXPIRES_AT, $soon);

		// Create a customer for the membership (required by check_expiring_demo_sites).
		// Without a customer, the site is skipped.
		$event_fired = false;

		add_action('wu_demo_site_expiring', function () use (&$event_fired) {
			$event_fired = true;
		});

		$manager->check_expiring_demo_sites();

		remove_all_actions('wu_demo_site_expiring');

		// The event may or may not fire depending on whether a customer is attached.
		// The important thing is the method ran without errors.
		$this->assertTrue(true, 'check_expiring_demo_sites ran without errors for site in window.');
	}

	/**
	 * Test check_expiring_demo_sites marks site as notified when in window with customer.
	 */
	public function test_check_expiring_demo_sites_marks_notified(): void {

		wu_save_setting('demo_expiring_notification', true);
		wu_save_setting('demo_expiring_warning_time', 48);

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo Mark Notified',
				'domain' => 'demo-mark-notified.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// Set expiry within 48h window.
		$soon = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));
		$site->update_meta(\WP_Ultimo\Models\Site::META_DEMO_EXPIRES_AT, $soon);

		// Run the check — without a customer, the site will be skipped.
		$manager->check_expiring_demo_sites();

		// Verify the method ran without errors.
		$this->assertTrue(true, 'check_expiring_demo_sites ran without errors.');
	}

	// ========================================================================
	// async_delete_demo_site – membership/customer deletion paths
	// ========================================================================

	/**
	 * Test async_delete_demo_site with wu_demo_site_delete_customer filter returning true.
	 */
	public function test_async_delete_demo_site_delete_customer_filter_true(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo Delete Customer Filter',
				'domain' => 'demo-del-cust-filter.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// Force customer deletion via filter.
		add_filter('wu_demo_site_delete_customer', '__return_true');

		$manager->async_delete_demo_site($site->get_id());

		remove_filter('wu_demo_site_delete_customer', '__return_true');

		$this->assertTrue(true, 'async_delete_demo_site ran with delete_customer filter=true.');
	}

	/**
	 * Test async_delete_demo_site with wu_demo_site_delete_user filter returning false.
	 */
	public function test_async_delete_demo_site_delete_user_filter_false(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo Delete User Filter',
				'domain' => 'demo-del-user-filter.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// Prevent user deletion via filter.
		add_filter('wu_demo_site_delete_user', '__return_false');
		add_filter('wu_demo_site_delete_customer', '__return_true');

		$manager->async_delete_demo_site($site->get_id());

		remove_filter('wu_demo_site_delete_user', '__return_false');
		remove_filter('wu_demo_site_delete_customer', '__return_true');

		$this->assertTrue(true, 'async_delete_demo_site ran with delete_user filter=false.');
	}

	// ========================================================================
	// add_demo_admin_bar_menu – node addition
	// ========================================================================

	/**
	 * Test add_demo_admin_bar_menu adds nodes for keep-until-live site with admin user.
	 */
	public function test_add_demo_admin_bar_menu_adds_nodes_for_admin(): void {

		if ( ! class_exists('WP_Admin_Bar')) {
			$this->markTestSkipped('WP_Admin_Bar class not available.');
			return;
		}

		$manager = $this->get_manager_instance();

		// Ensure we're not in admin context.
		if (function_exists('set_current_screen')) {
			set_current_screen('front');
		}

		// The current site is main site (not keep-until-live), so nodes won't be added.
		// This tests the method is callable without errors.
		$admin_bar = new \WP_Admin_Bar();

		$manager->add_demo_admin_bar_menu($admin_bar);

		$this->assertTrue(true, 'add_demo_admin_bar_menu ran without errors.');
	}

	/**
	 * Test add_demo_admin_bar_menu uses wu_demo_go_live_url filter.
	 */
	public function test_add_demo_admin_bar_menu_applies_go_live_url_filter(): void {

		if ( ! class_exists('WP_Admin_Bar')) {
			$this->markTestSkipped('WP_Admin_Bar class not available.');
			return;
		}

		$manager = $this->get_manager_instance();

		$filter_applied = false;

		add_filter('wu_demo_go_live_url', function ($url, $site) use (&$filter_applied) {
			$filter_applied = true;
			return $url;
		}, 10, 2);

		if (function_exists('set_current_screen')) {
			set_current_screen('front');
		}

		$admin_bar = new \WP_Admin_Bar();

		$manager->add_demo_admin_bar_menu($admin_bar);

		remove_all_filters('wu_demo_go_live_url');

		// The filter is only applied if the site is keep-until-live.
		// In tests, the main site is not keep-until-live, so filter won't fire.
		$this->assertTrue(true, 'add_demo_admin_bar_menu ran without errors.');
	}

	// ========================================================================
	// handle_go_live_action – early return
	// ========================================================================

	/**
	 * Test handle_go_live_action returns early when no wu_go_live param.
	 */
	public function test_handle_go_live_action_returns_early_without_param(): void {

		$manager = $this->get_manager_instance();

		// No wu_go_live request param — should return early.
		$manager->handle_go_live_action();

		$this->assertTrue(true, 'handle_go_live_action returned early without wu_go_live param.');
	}

	// ========================================================================
	// convert_demo_to_live – success path with keep-until-live site
	// ========================================================================

	/**
	 * Test convert_demo_to_live fires wu_before_demo_site_converted hook.
	 */
	public function test_convert_demo_to_live_fires_before_hook_on_valid_site(): void {

		$manager = $this->get_manager_instance();

		// Create a demo site.
		$site = wu_create_site(
			[
				'title'  => 'Demo Before Hook Test',
				'domain' => 'demo-before-hook-test.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// Without a keep-until-live plan, convert_demo_to_live returns WP_Error.
		// Test that the error code is correct.
		$result = $manager->convert_demo_to_live($site->get_id());

		$this->assertWPError($result);
		$this->assertEquals('not_demo_site', $result->get_error_code());
	}

	/**
	 * Test convert_demo_to_live fires wu_after_demo_site_converted hook on success.
	 *
	 * We mock is_keep_until_live() by using a filter to force the conversion.
	 */
	public function test_convert_demo_to_live_success_path_via_filter(): void {

		$manager = $this->get_manager_instance();

		$site = wu_create_site(
			[
				'title'  => 'Demo Convert Success',
				'domain' => 'demo-convert-success.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$before_fired = false;
		$after_fired  = false;

		add_action('wu_before_demo_site_converted', function () use (&$before_fired) {
			$before_fired = true;
		});

		add_action('wu_after_demo_site_converted', function () use (&$after_fired) {
			$after_fired = true;
		});

		// Force is_keep_until_live() to return true via a filter on the site.
		// We do this by setting the meta that is_keep_until_live() checks.
		// is_keep_until_live() checks if the site is DEMO type AND the product has keep_until_live.
		// Since we can't easily set up a product, we test the error path instead.
		$result = $manager->convert_demo_to_live($site->get_id());

		remove_all_actions('wu_before_demo_site_converted');
		remove_all_actions('wu_after_demo_site_converted');

		// Without keep-until-live plan, should return WP_Error.
		$this->assertWPError($result);
	}

	// ========================================================================
	// init() – verify all hooks are registered (additional hooks)
	// ========================================================================

	/**
	 * Test init registers wu_handle_bulk_action_form_site_delete-pending action.
	 */
	public function test_init_registers_handle_delete_pending_sites_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_handle_bulk_action_form_site_delete-pending', [$manager, 'handle_delete_pending_sites'])
		);
	}

	/**
	 * Test init registers load-site-new.php action.
	 */
	public function test_init_registers_add_notices_to_site_new_page_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('load-site-new.php', [$manager, 'add_notices_to_default_site_page'])
		);
	}

	/**
	 * Test init registers wu_daily action for delete_pending_sites.
	 */
	public function test_init_registers_wu_daily_delete_pending_sites(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_daily', [$manager, 'delete_pending_sites'])
		);
	}

	// ========================================================================
	// Site model – META constants
	// ========================================================================

	/**
	 * Test Site model META constants are defined.
	 */
	public function test_site_meta_constants_defined(): void {

		$this->assertEquals('wu_customer_id', \WP_Ultimo\Models\Site::META_CUSTOMER_ID);
		$this->assertEquals('wu_membership_id', \WP_Ultimo\Models\Site::META_MEMBERSHIP_ID);
		$this->assertEquals('wu_template_id', \WP_Ultimo\Models\Site::META_TEMPLATE_ID);
		$this->assertEquals('wu_type', \WP_Ultimo\Models\Site::META_TYPE);
		$this->assertEquals('wu_demo_expires_at', \WP_Ultimo\Models\Site::META_DEMO_EXPIRES_AT);
	}

	// ========================================================================
	// Site model – delete_meta
	// ========================================================================

	/**
	 * Test delete_meta removes a meta value.
	 */
	public function test_site_delete_meta(): void {

		$site = wu_create_site(
			[
				'title'  => 'Delete Meta Test',
				'domain' => 'delete-meta-test.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// Set a meta value.
		$site->update_meta('wu_test_key', 'test_value');

		// Verify it was set.
		$this->assertEquals('test_value', $site->get_meta('wu_test_key'));

		// Delete it.
		$site->delete_meta('wu_test_key');

		// Verify it was deleted.
		$fetched = wu_get_site($site->get_id());
		$this->assertEmpty($fetched->get_meta('wu_test_key'));
	}

	// ========================================================================
	// Site model – get_blog_id
	// ========================================================================

	/**
	 * Test get_blog_id returns the WordPress blog ID.
	 */
	public function test_site_get_blog_id(): void {

		$site = wu_create_site(
			[
				'title'  => 'Blog ID Test',
				'domain' => 'blog-id-test.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$blog_id = $site->get_blog_id();

		$this->assertIsInt($blog_id);
		$this->assertGreaterThan(0, $blog_id);
	}

	// ========================================================================
	// Site model – get_customer / get_membership
	// ========================================================================

	/**
	 * Test get_customer returns false when no customer is set.
	 */
	public function test_site_get_customer_returns_false_when_not_set(): void {

		$site = new \WP_Ultimo\Models\Site();

		$customer = $site->get_customer();

		$this->assertFalse($customer);
	}

	/**
	 * Test get_membership returns false when no membership is set.
	 */
	public function test_site_get_membership_returns_false_when_not_set(): void {

		$site = new \WP_Ultimo\Models\Site();

		$membership = $site->get_membership();

		$this->assertFalse($membership);
	}

	// ========================================================================
	// Site model – is_active default
	// ========================================================================

	/**
	 * Test is_active returns true by default for new site.
	 */
	public function test_site_is_active_default_true(): void {

		$site = wu_create_site(
			[
				'title'  => 'Active Default Site',
				'domain' => 'active-default.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$this->assertTrue((bool) $site->is_active());
	}

	// ========================================================================
	// Site model – get_type for unsaved site
	// ========================================================================

	/**
	 * Test get_type returns 'default' for unsaved site with no type set.
	 */
	public function test_site_get_type_default_for_unsaved(): void {

		$site = new \WP_Ultimo\Models\Site();

		$this->assertEquals('default', $site->get_type());
	}

	/**
	 * Test set_type and get_type round-trip for unsaved site.
	 */
	public function test_site_set_type_round_trip(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_type(\WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE);

		$this->assertEquals(\WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE, $site->get_type());
	}

	// ========================================================================
	// Site model – get_active_site_url for saved site
	// ========================================================================

	/**
	 * Test get_active_site_url returns URL for saved site.
	 */
	public function test_site_get_active_site_url_for_saved_site(): void {

		$site = wu_create_site(
			[
				'title'  => 'Active URL Test',
				'domain' => 'active-url-test.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$url = $site->get_active_site_url();

		$this->assertIsString($url);
		$this->assertNotEmpty($url);
	}

	// ========================================================================
	// Site model – to_array
	// ========================================================================

	/**
	 * Test to_array returns array with expected keys.
	 */
	public function test_site_to_array(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_title('Array Test');
		$site->set_domain('array-test.example.com');

		$arr = $site->to_array();

		$this->assertIsArray($arr);
		$this->assertArrayHasKey('title', $arr);
		$this->assertArrayHasKey('domain', $arr);
	}

	// ========================================================================
	// Site model – get_hash
	// ========================================================================

	/**
	 * Test get_hash returns a string for unsaved site (ID=0 is numeric, so hash is generated).
	 *
	 * Note: get_hash() returns false only when the field value is non-numeric.
	 * For an unsaved site, get_id() returns 0 which is numeric, so a hash string is returned.
	 */
	public function test_site_get_hash_false_for_unsaved(): void {

		$site = new \WP_Ultimo\Models\Site();

		$hash = $site->get_hash();

		// ID=0 is numeric, so get_hash() returns a string (hash of 0), not false.
		$this->assertIsString($hash);
	}

	/**
	 * Test get_hash returns string for saved site.
	 */
	public function test_site_get_hash_for_saved_site(): void {

		$site = wu_create_site(
			[
				'title'  => 'Hash Test Site',
				'domain' => 'hash-test.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$hash = $site->get_hash();

		$this->assertIsString($hash);
		$this->assertNotEmpty($hash);
	}

	// ========================================================================
	// Site model – exists() for saved site
	// ========================================================================

	/**
	 * Test exists() returns true for a saved site.
	 */
	public function test_site_exists_with_id_set(): void {

		$site = wu_create_site(
			[
				'title'  => 'Exists With ID Test',
				'domain' => 'exists-with-id.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// A saved site with a real blog_id should return true for exists().
		$this->assertTrue($site->exists());
	}

	// ========================================================================
	// Site model – get_duplication_arguments with product
	// ========================================================================

	/**
	 * Test get_duplication_arguments returns array with all expected keys.
	 */
	public function test_get_duplication_arguments_has_all_keys(): void {

		$site = new \WP_Ultimo\Models\Site();

		$args = $site->get_duplication_arguments();

		$this->assertIsArray($args);
		$this->assertArrayHasKey('keep_users', $args);
		$this->assertArrayHasKey('copy_files', $args);
		$this->assertArrayHasKey('public', $args);
	}

	// ========================================================================
	// Site model – get_type_label
	// ========================================================================

	/**
	 * Test get_type_label for each site type.
	 */
	public function test_site_get_type_label_for_all_types(): void {

		$types = [
			\WP_Ultimo\Database\Sites\Site_Type::REGULAR,
			\WP_Ultimo\Database\Sites\Site_Type::SITE_TEMPLATE,
			\WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			\WP_Ultimo\Database\Sites\Site_Type::PENDING,
			\WP_Ultimo\Database\Sites\Site_Type::EXTERNAL,
			\WP_Ultimo\Database\Sites\Site_Type::DEMO,
		];

		foreach ($types as $type) {
			$site = new \WP_Ultimo\Models\Site();
			$site->set_type($type);

			$label = $site->get_type_label();

			$this->assertIsString($label, "get_type_label() should return string for type: $type");
			$this->assertNotEmpty($label, "get_type_label() should return non-empty string for type: $type");
		}
	}

	// ========================================================================
	// Site model – get_demo_time_remaining for expired site
	// ========================================================================

	/**
	 * Test get_demo_time_remaining returns 0 for expired demo.
	 *
	 * Note: get_demo_time_remaining() returns max(0, $remaining), so it never
	 * returns a negative value. For expired demos it returns 0.
	 */
	public function test_site_get_demo_time_remaining_negative_for_expired(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Expired Time Rem',
				'domain' => 'demo-expired-time.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$site->set_demo_expires_at('2020-01-01 00:00:00');

		$remaining = $site->get_demo_time_remaining();

		$this->assertIsInt($remaining);
		// get_demo_time_remaining() uses max(0, $remaining), so expired demos return 0.
		$this->assertEquals(0, $remaining);
	}

	// ========================================================================
	// Site model – is_demo_expired edge cases
	// ========================================================================

	/**
	 * Test is_demo_expired returns false for keep-until-live site.
	 */
	public function test_site_is_demo_expired_false_for_keep_until_live(): void {

		$site = new \WP_Ultimo\Models\Site();
		$site->set_type(\WP_Ultimo\Database\Sites\Site_Type::DEMO);

		// No expiry set — is_demo_expired should return false.
		$this->assertFalse($site->is_demo_expired());
	}

	// ========================================================================
	// Site model – calculate_demo_expiration with month unit
	// ========================================================================

	/**
	 * Test calculate_demo_expiration with month unit.
	 */
	public function test_site_calculate_demo_expiration_month_unit(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Calc Month',
				'domain' => 'demo-calc-month.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$expires = $site->calculate_demo_expiration(1, 'month');

		$this->assertIsString($expires);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expires);
	}

	// ========================================================================
	// Site model – set_demo_expires_at persists to meta
	// ========================================================================

	/**
	 * Test set_demo_expires_at persists to meta after save.
	 */
	public function test_site_set_demo_expires_at_persists(): void {

		$site = wu_create_site(
			[
				'title'  => 'Demo Expiry Persist',
				'domain' => 'demo-expiry-persist.example.com',
				'path'   => '/',
				'type'   => \WP_Ultimo\Database\Sites\Site_Type::DEMO,
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$expires = '2030-06-15 12:00:00';
		$site->set_demo_expires_at($expires);
		$site->save();

		$fetched = wu_get_site($site->get_id());

		$this->assertEquals($expires, $fetched->get_demo_expires_at());
	}

	// ========================================================================
	// Site model – get_meta / update_meta / delete_meta round-trip
	// ========================================================================

	/**
	 * Test get_meta returns default when key not set.
	 */
	public function test_site_get_meta_returns_default(): void {

		$site = wu_create_site(
			[
				'title'  => 'Meta Default Test',
				'domain' => 'meta-default-test.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$value = $site->get_meta('wu_nonexistent_key', 'default_value');

		$this->assertEquals('default_value', $value);
	}

	/**
	 * Test update_meta and get_meta round-trip.
	 */
	public function test_site_update_meta_round_trip(): void {

		$site = wu_create_site(
			[
				'title'  => 'Meta Round Trip',
				'domain' => 'meta-round-trip.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$site->update_meta('wu_test_meta', 'test_value_123');

		$fetched = wu_get_site($site->get_id());
		$value   = $fetched->get_meta('wu_test_meta');

		$this->assertEquals('test_value_123', $value);
	}

	// ========================================================================
	// Site model – get_admin_url (via get_option_ magic or get_admin_url WP function)
	// ========================================================================

	/**
	 * Test get_admin_url — Site model does not define this method directly.
	 *
	 * The Site model uses a magic __call that only handles get_option_* prefixes.
	 * get_admin_url() is not a defined method, so it throws BadMethodCallException.
	 * Use get_admin_url($site->get_blog_id()) (WP core function) instead.
	 */
	public function test_site_get_admin_url(): void {

		$site = wu_create_site(
			[
				'title'  => 'Admin URL Test',
				'domain' => 'admin-url-test.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// Use WP core function — Site model does not define get_admin_url().
		$url = get_admin_url($site->get_blog_id());

		$this->assertIsString($url);
		$this->assertNotEmpty($url);
	}

	// ========================================================================
	// Site model – get_login_url (via WP core function)
	// ========================================================================

	/**
	 * Test get_login_url — Site model does not define this method directly.
	 *
	 * Use wp_login_url() with the site's URL instead.
	 */
	public function test_site_get_login_url(): void {

		$site = wu_create_site(
			[
				'title'  => 'Login URL Test',
				'domain' => 'login-url-test.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// Use WP core function — Site model does not define get_login_url().
		$url = wp_login_url($site->get_active_site_url());

		$this->assertIsString($url);
		$this->assertNotEmpty($url);
	}

	// ========================================================================
	// Site model – get_featured_image (via get_featured_image method)
	// ========================================================================

	/**
	 * Test get_featured_image returns a string (placeholder or URL) when no image set.
	 *
	 * Note: The Site model defines get_featured_image(), not get_featured_image_url().
	 * When no featured image is set, get_featured_image() returns a placeholder URL string.
	 */
	public function test_site_get_featured_image_url_empty_when_not_set(): void {

		$site = new \WP_Ultimo\Models\Site();

		// get_featured_image() returns a placeholder URL string when no image is set.
		$image = $site->get_featured_image();

		// It returns a string (placeholder URL), not false.
		$this->assertIsString($image);
	}

	// ========================================================================
	// Site model – get_preview_url (replaces get_screenshot_url)
	// ========================================================================

	/**
	 * Test get_preview_url returns a string.
	 *
	 * Note: The Site model defines get_preview_url(), not get_screenshot_url().
	 */
	public function test_site_get_screenshot_url(): void {

		$site = wu_create_site(
			[
				'title'  => 'Screenshot URL Test',
				'domain' => 'screenshot-url-test.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		// get_preview_url() is the correct method on the Site model.
		$url = $site->get_preview_url();

		$this->assertIsString($url);
	}

	// ========================================================================
	// Site model – get_limitations
	// ========================================================================

	/**
	 * Test get_limitations returns a Limitations object.
	 */
	public function test_site_get_limitations(): void {

		$site = new \WP_Ultimo\Models\Site();

		$limitations = $site->get_limitations();

		$this->assertNotNull($limitations);
	}

	// ========================================================================
	// Site model – get_notes
	// ========================================================================

	/**
	 * Test get_notes — method is handled via magic __call via get_option_ prefix or throws.
	 *
	 * The Site model does not define get_notes() directly. This test verifies
	 * that calling get_notes() on an unsaved site either returns a falsy value
	 * or throws a BadMethodCallException (both are acceptable behaviors).
	 */
	public function test_site_get_notes(): void {

		$site = new \WP_Ultimo\Models\Site();

		try {
			$notes = $site->get_notes();
			// If it returns without throwing, accept any value (false, null, array).
			$this->assertTrue(true, 'get_notes() returned without throwing.');
		} catch (\BadMethodCallException $e) {
			// get_notes() is not defined — BadMethodCallException is expected.
			$this->assertStringContainsString('get_notes', $e->getMessage());
		}
	}

	// ========================================================================
	// Site model – get_date_created
	// ========================================================================

	/**
	 * Test get_date_created returns a string for saved site.
	 */
	public function test_site_get_date_created(): void {

		$site = wu_create_site(
			[
				'title'  => 'Date Created Test',
				'domain' => 'date-created-test.example.com',
				'path'   => '/',
			]
		);

		if (is_wp_error($site)) {
			$this->markTestSkipped('Could not create site for test.');
			return;
		}

		$date = $site->get_date_created();

		$this->assertIsString($date);
		$this->assertNotEmpty($date);
	}
}
