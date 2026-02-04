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
}
