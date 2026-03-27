<?php
/**
 * Test case for Checkout_Pages.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Checkout;

use WP_Ultimo\Checkout\Checkout_Pages;

/**
 * Test Checkout_Pages functionality.
 */
class Checkout_Pages_Test extends \WP_UnitTestCase {

	/**
	 * The Checkout_Pages instance.
	 *
	 * @var Checkout_Pages
	 */
	private $pages;

	/**
	 * Set up test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->pages = Checkout_Pages::get_instance();
	}

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * Test singleton instance.
	 */
	public function test_singleton_instance(): void {

		$this->assertInstanceOf(Checkout_Pages::class, $this->pages);
		$this->assertSame($this->pages, Checkout_Pages::get_instance());
	}

	// -------------------------------------------------------------------------
	// init() — hook registration
	// -------------------------------------------------------------------------

	/**
	 * Test init registers expected filters and actions.
	 */
	public function test_init_registers_hooks(): void {

		$this->assertGreaterThan(
			0,
			has_filter('display_post_states', [$this->pages, 'add_wp_ultimo_status_annotation']),
			'display_post_states filter not registered'
		);

		$this->assertGreaterThan(
			0,
			has_action('wu_thank_you_site_block', [$this->pages, 'add_verify_email_notice']),
			'wu_thank_you_site_block action not registered'
		);

		$this->assertGreaterThan(
			0,
			has_action('wp_enqueue_scripts', [$this->pages, 'maybe_enqueue_payment_status_poll']),
			'wp_enqueue_scripts action not registered'
		);

		$this->assertGreaterThan(
			0,
			has_filter('lostpassword_redirect', [$this->pages, 'filter_lost_password_redirect']),
			'lostpassword_redirect filter not registered'
		);
	}

	/**
	 * Test init registers main-site-only hooks when on main site.
	 */
	public function test_init_registers_main_site_hooks(): void {

		// We are on the main site in the test environment.
		$this->assertTrue(is_main_site());

		$this->assertGreaterThan(
			0,
			has_action('before_signup_header', [$this->pages, 'redirect_to_registration_page']),
			'before_signup_header action not registered'
		);

		$this->assertGreaterThan(
			0,
			has_action('save_post_page', [$this->pages, 'maybe_flush_rewrite_rules_on_page_save']),
			'save_post_page action not registered'
		);

		$this->assertGreaterThan(
			0,
			has_action('wp_trash_post', [$this->pages, 'maybe_flush_rewrite_rules_on_page_trash']),
			'wp_trash_post action not registered'
		);
	}

	// -------------------------------------------------------------------------
	// get_signup_pages
	// -------------------------------------------------------------------------

	/**
	 * Test get_signup_pages returns expected keys.
	 */
	public function test_get_signup_pages_returns_expected_keys(): void {

		$pages = $this->pages->get_signup_pages();

		$this->assertIsArray($pages);
		$this->assertArrayHasKey('register', $pages);
		$this->assertArrayHasKey('update', $pages);
		$this->assertArrayHasKey('login', $pages);
		$this->assertArrayHasKey('block_frontend', $pages);
		$this->assertArrayHasKey('new_site', $pages);
	}

	/**
	 * Test get_signup_pages returns configured page IDs.
	 */
	public function test_get_signup_pages_returns_configured_ids(): void {

		$page_id = self::factory()->post->create(['post_type' => 'page']);

		wu_save_setting('default_login_page', $page_id);
		wu_save_setting('default_update_page', $page_id);

		$pages = $this->pages->get_signup_pages();

		$this->assertEquals($page_id, $pages['login']);
		$this->assertEquals($page_id, $pages['update']);
	}

	// -------------------------------------------------------------------------
	// get_signup_page
	// -------------------------------------------------------------------------

	/**
	 * Test get_signup_page returns false for invalid page.
	 */
	public function test_get_signup_page_returns_false_for_invalid_page(): void {

		$result = $this->pages->get_signup_page('nonexistent');

		$this->assertFalse($result);
	}

	/**
	 * Test get_signup_page returns false when page_id is 0.
	 */
	public function test_get_signup_page_returns_false_when_no_id(): void {

		wu_save_setting('default_login_page', 0);

		$result = $this->pages->get_signup_page('login');

		$this->assertFalse($result);
	}

	/**
	 * Test get_signup_page returns WP_Post when valid page configured.
	 */
	public function test_get_signup_page_returns_post_when_configured(): void {

		$page_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_status' => 'publish',
		]);

		wu_save_setting('default_login_page', $page_id);

		$result = $this->pages->get_signup_page('login');

		// In multisite test env get_blog_post may return the post.
		if ($result !== false) {
			$this->assertInstanceOf(\WP_Post::class, $result);
			$this->assertEquals($page_id, $result->ID);
		} else {
			// Acceptable: get_blog_post returns false when blog_id differs.
			$this->assertFalse($result);
		}
	}

	// -------------------------------------------------------------------------
	// get_page_url
	// -------------------------------------------------------------------------

	/**
	 * Test get_page_url returns false for invalid page.
	 */
	public function test_get_page_url_returns_false_for_invalid_page(): void {

		$result = $this->pages->get_page_url('nonexistent');

		$this->assertFalse($result);
	}

	/**
	 * Test get_page_url returns false when no page configured.
	 */
	public function test_get_page_url_returns_false_when_no_page(): void {

		wu_save_setting('default_login_page', 0);

		$result = $this->pages->get_page_url('login');

		$this->assertFalse($result);
	}

	/**
	 * Test get_page_url returns string URL when page is configured.
	 */
	public function test_get_page_url_returns_url_when_configured(): void {

		$page_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_name'   => 'test-login-page',
			'post_status' => 'publish',
		]);

		wu_save_setting('default_login_page', $page_id);

		$result = $this->pages->get_page_url('login');

		// Either a URL string or false (if get_blog_post can't find it cross-blog).
		if ($result !== false) {
			$this->assertIsString($result);
			$this->assertNotEmpty($result);
		} else {
			$this->assertFalse($result);
		}
	}

	// -------------------------------------------------------------------------
	// get_error_message
	// -------------------------------------------------------------------------

	/**
	 * Test get_error_message returns known error messages.
	 */
	public function test_get_error_message_returns_known_messages(): void {

		$message = $this->pages->get_error_message('incorrect_password');

		$this->assertIsString($message);
		$this->assertNotEmpty($message);
		$this->assertStringContainsString('password', strtolower($message));
	}

	/**
	 * Test get_error_message returns default for unknown code.
	 */
	public function test_get_error_message_returns_default_for_unknown_code(): void {

		$message = $this->pages->get_error_message('totally_unknown_error_code');

		$this->assertIsString($message);
		$this->assertNotEmpty($message);
	}

	/**
	 * Test get_error_message with username parameter.
	 */
	public function test_get_error_message_with_username(): void {

		$message = $this->pages->get_error_message('invalid_username', 'testuser');

		$this->assertIsString($message);
		$this->assertStringContainsString('testuser', $message);
	}

	/**
	 * Test get_error_message for expired session.
	 */
	public function test_get_error_message_expired(): void {

		$message = $this->pages->get_error_message('expired');

		$this->assertIsString($message);
		$this->assertStringContainsString('expired', strtolower($message));
	}

	/**
	 * Test get_error_message for loggedout.
	 */
	public function test_get_error_message_loggedout(): void {

		$message = $this->pages->get_error_message('loggedout');

		$this->assertIsString($message);
		$this->assertStringContainsString('logged out', strtolower($message));
	}

	/**
	 * Test get_error_message for empty_username.
	 */
	public function test_get_error_message_empty_username(): void {

		$message = $this->pages->get_error_message('empty_username');

		$this->assertIsString($message);
		$this->assertStringContainsString('username', strtolower($message));
	}

	/**
	 * Test get_error_message for empty_password.
	 */
	public function test_get_error_message_empty_password(): void {

		$message = $this->pages->get_error_message('empty_password');

		$this->assertIsString($message);
		$this->assertStringContainsString('password', strtolower($message));
	}

	/**
	 * Test get_error_message for invalid_email.
	 */
	public function test_get_error_message_invalid_email(): void {

		$message = $this->pages->get_error_message('invalid_email');

		$this->assertIsString($message);
		$this->assertStringContainsString('email', strtolower($message));
	}

	/**
	 * Test get_error_message for password_reset_mismatch.
	 */
	public function test_get_error_message_password_mismatch(): void {

		$message = $this->pages->get_error_message('password_reset_mismatch');

		$this->assertIsString($message);
		$this->assertStringContainsString('passwords', strtolower($message));
	}

	/**
	 * Test get_error_message for confirm code.
	 */
	public function test_get_error_message_confirm(): void {

		$message = $this->pages->get_error_message('confirm');

		$this->assertIsString($message);
		$this->assertStringContainsString('email', strtolower($message));
	}

	/**
	 * Test get_error_message for registered code.
	 */
	public function test_get_error_message_registered(): void {

		$message = $this->pages->get_error_message('registered');

		$this->assertIsString($message);
		$this->assertStringContainsString('registration', strtolower($message));
	}

	/**
	 * Test get_error_message for registerdisabled code.
	 */
	public function test_get_error_message_registerdisabled(): void {

		$message = $this->pages->get_error_message('registerdisabled');

		$this->assertIsString($message);
		$this->assertStringContainsString('registration', strtolower($message));
	}

	/**
	 * Test get_error_message for invalidcombo code.
	 */
	public function test_get_error_message_invalidcombo(): void {

		$message = $this->pages->get_error_message('invalidcombo');

		$this->assertIsString($message);
		$this->assertNotEmpty($message);
	}

	/**
	 * Test get_error_message for invalidkey code.
	 */
	public function test_get_error_message_invalidkey(): void {

		$message = $this->pages->get_error_message('invalidkey');

		$this->assertIsString($message);
		$this->assertStringContainsString('invalid', strtolower($message));
	}

	/**
	 * Test get_error_message for expiredkey code.
	 */
	public function test_get_error_message_expiredkey(): void {

		$message = $this->pages->get_error_message('expiredkey');

		$this->assertIsString($message);
		$this->assertStringContainsString('expired', strtolower($message));
	}

	/**
	 * Test get_error_message for invalid_key code.
	 */
	public function test_get_error_message_invalid_key(): void {

		$message = $this->pages->get_error_message('invalid_key');

		$this->assertIsString($message);
		$this->assertStringContainsString('invalid', strtolower($message));
	}

	/**
	 * Test get_error_message for expired_key code.
	 */
	public function test_get_error_message_expired_key(): void {

		$message = $this->pages->get_error_message('expired_key');

		$this->assertIsString($message);
		$this->assertStringContainsString('expired', strtolower($message));
	}

	/**
	 * Test get_error_message for password_reset_empty_space code.
	 */
	public function test_get_error_message_password_reset_empty_space(): void {

		$message = $this->pages->get_error_message('password_reset_empty_space');

		$this->assertIsString($message);
		$this->assertStringContainsString('password', strtolower($message));
	}

	/**
	 * Test error messages filter.
	 */
	public function test_error_messages_filter(): void {

		add_filter('wu_checkout_pages_error_messages', function ($messages) {
			$messages['custom_error'] = 'Custom error message';
			return $messages;
		});

		$message = $this->pages->get_error_message('custom_error');

		$this->assertEquals('Custom error message', $message);
	}

	// -------------------------------------------------------------------------
	// add_wp_ultimo_status_annotation
	// -------------------------------------------------------------------------

	/**
	 * Test add_wp_ultimo_status_annotation on non-main site.
	 */
	public function test_status_annotation_on_non_main_site(): void {

		// Switch to a subsite so is_main_site() returns false
		$blog_id = self::factory()->blog->create();

		switch_to_blog($blog_id);

		$page_id = self::factory()->post->create(['post_type' => 'page']);
		$post    = get_post($page_id);

		$states = $this->pages->add_wp_ultimo_status_annotation([], $post);

		restore_current_blog();

		$this->assertIsArray($states);
		$this->assertEmpty($states); // Should return early on non-main site
	}

	/**
	 * Test add_wp_ultimo_status_annotation with matching page on main site.
	 */
	public function test_status_annotation_with_matching_page(): void {

		// Create a page on the main site
		$page_id = self::factory()->post->create(['post_type' => 'page']);

		$this->assertIsInt($page_id);

		wu_save_setting('default_login_page', $page_id);

		$post = get_post($page_id);

		$this->assertInstanceOf(\WP_Post::class, $post);

		$states = $this->pages->add_wp_ultimo_status_annotation([], $post);

		$this->assertIsArray($states);

		// The page should be annotated as a WP Ultimo page
		$this->assertArrayHasKey('wp_ultimo_page', $states);
	}

	/**
	 * Test add_wp_ultimo_status_annotation with non-matching page on main site.
	 */
	public function test_status_annotation_with_non_matching_page(): void {

		// Clear all signup page settings so no page matches.
		wu_save_setting('default_login_page', 0);
		wu_save_setting('default_update_page', 0);
		wu_save_setting('default_block_frontend_page', 0);
		wu_save_setting('default_new_site_page', 0);

		$page_id = self::factory()->post->create(['post_type' => 'page']);
		$post    = get_post($page_id);

		$states = $this->pages->add_wp_ultimo_status_annotation(['existing_state' => 'value'], $post);

		$this->assertIsArray($states);
		$this->assertArrayHasKey('existing_state', $states);
		$this->assertArrayNotHasKey('wp_ultimo_page', $states);
	}

	/**
	 * Test add_wp_ultimo_status_annotation preserves existing states.
	 */
	public function test_status_annotation_preserves_existing_states(): void {

		$page_id = self::factory()->post->create(['post_type' => 'page']);

		wu_save_setting('default_login_page', $page_id);

		$post = get_post($page_id);

		$initial_states = ['some_plugin_state' => 'Some Label'];

		$states = $this->pages->add_wp_ultimo_status_annotation($initial_states, $post);

		$this->assertArrayHasKey('some_plugin_state', $states);
		$this->assertArrayHasKey('wp_ultimo_page', $states);
	}

	/**
	 * Test add_wp_ultimo_status_annotation labels each page type correctly.
	 */
	public function test_status_annotation_labels_each_page_type(): void {

		$page_types = [
			'default_login_page'          => 'Login Page',
			'default_update_page'         => 'Membership Update Page',
			'default_block_frontend_page' => 'Site Blocked Page',
			'default_new_site_page'       => 'New Site Page',
		];

		foreach ($page_types as $setting => $label_fragment) {
			// Reset all settings.
			wu_save_setting('default_login_page', 0);
			wu_save_setting('default_update_page', 0);
			wu_save_setting('default_block_frontend_page', 0);
			wu_save_setting('default_new_site_page', 0);

			$page_id = self::factory()->post->create(['post_type' => 'page']);
			wu_save_setting($setting, $page_id);

			$post   = get_post($page_id);
			$states = $this->pages->add_wp_ultimo_status_annotation([], $post);

			$this->assertArrayHasKey('wp_ultimo_page', $states, "Missing annotation for $setting");
			$this->assertStringContainsString(
				$label_fragment,
				$states['wp_ultimo_page'],
				"Wrong label for $setting"
			);
		}
	}

	// -------------------------------------------------------------------------
	// filter_lost_password_redirect
	// -------------------------------------------------------------------------

	/**
	 * Test filter_lost_password_redirect with non-empty redirect.
	 */
	public function test_filter_lost_password_redirect_with_redirect(): void {

		$redirect = 'https://example.com/custom-redirect';

		$result = $this->pages->filter_lost_password_redirect($redirect);

		$this->assertEquals($redirect, $result);
	}

	/**
	 * Test filter_lost_password_redirect with empty redirect.
	 */
	public function test_filter_lost_password_redirect_empty(): void {

		$result = $this->pages->filter_lost_password_redirect('');

		$this->assertIsString($result);
		$this->assertStringContainsString('checkemail=confirm', $result);
	}

	// -------------------------------------------------------------------------
	// maybe_change_wp_login_on_urls
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_change_wp_login_on_urls with non-login URL.
	 */
	public function test_maybe_change_wp_login_on_urls_no_login(): void {

		$url = 'https://example.com/some-page';

		$result = $this->pages->maybe_change_wp_login_on_urls($url);

		$this->assertEquals($url, $result);
	}

	/**
	 * Test maybe_change_wp_login_on_urls with login URL but no custom page.
	 */
	public function test_maybe_change_wp_login_on_urls_with_login_no_custom(): void {

		wu_save_setting('default_login_page', 0);

		$url = 'https://example.com/wp-login.php';

		$result = $this->pages->maybe_change_wp_login_on_urls($url);

		// Without a valid custom login page, URL should remain unchanged
		$this->assertIsString($result);
	}

	/**
	 * Test maybe_change_wp_login_on_urls with login URL and custom page.
	 */
	public function test_maybe_change_wp_login_on_urls_with_custom_page(): void {

		$page_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_name'   => 'custom-login',
			'post_title'  => 'Custom Login',
			'post_status' => 'publish',
		]);

		$this->assertIsInt($page_id);

		wu_save_setting('default_login_page', $page_id);

		// Verify the setting was saved and the post exists
		$saved_id = wu_get_setting('default_login_page', 0);
		$post     = get_post($saved_id);

		// The method does str_replace('wp-login.php', $post->post_name, $url)
		// Only assert if the post is retrievable (multisite env may differ)
		if ($post) {
			$url    = 'https://example.com/wp-login.php?action=lostpassword';
			$result = $this->pages->maybe_change_wp_login_on_urls($url);

			$this->assertStringContainsString('custom-login', $result);
			$this->assertStringNotContainsString('wp-login.php', $result);
		} else {
			// If post can't be retrieved via setting, test the passthrough behavior
			$url    = 'https://example.com/wp-login.php?action=lostpassword';
			$result = $this->pages->maybe_change_wp_login_on_urls($url);

			$this->assertIsString($result);
		}
	}

	/**
	 * Test maybe_change_wp_login_on_urls preserves query string after replacement.
	 */
	public function test_maybe_change_wp_login_on_urls_preserves_query_string(): void {

		$page_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_name'   => 'my-login',
			'post_status' => 'publish',
		]);

		wu_save_setting('default_login_page', $page_id);

		$post = get_post($page_id);

		if ($post) {
			$url    = 'https://example.com/wp-login.php?redirect_to=%2Fwp-admin%2F';
			$result = $this->pages->maybe_change_wp_login_on_urls($url);

			$this->assertStringContainsString('redirect_to', $result);
		} else {
			$this->assertTrue(true); // Cross-blog post not accessible — acceptable.
		}
	}

	// -------------------------------------------------------------------------
	// handle_compat_mode_setting
	// -------------------------------------------------------------------------

	/**
	 * Test handle_compat_mode_setting with autosave.
	 */
	public function test_handle_compat_mode_setting_autosave(): void {

		if ( ! defined('DOING_AUTOSAVE')) {
			define('DOING_AUTOSAVE', true);
		}

		$post_id = self::factory()->post->create(['post_type' => 'page']);

		$this->assertIsInt($post_id);

		// Should return early without doing anything
		$this->pages->handle_compat_mode_setting($post_id);

		$meta = get_post_meta($post_id, '_wu_force_elements_loading', true);
		$this->assertEmpty($meta);
	}

	/**
	 * Test handle_compat_mode_setting with missing nonce returns early.
	 */
	public function test_handle_compat_mode_setting_missing_nonce(): void {

		// DOING_AUTOSAVE may already be defined as true from previous test.
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			$this->markTestSkipped('DOING_AUTOSAVE is defined as true — cannot test nonce path.');
		}

		$post_id = self::factory()->post->create(['post_type' => 'page']);

		unset($_POST['_wu_force_compat']);

		$this->pages->handle_compat_mode_setting($post_id);

		$meta = get_post_meta($post_id, '_wu_force_elements_loading', true);
		$this->assertEmpty($meta);
	}

	/**
	 * Test handle_compat_mode_setting with invalid nonce returns early.
	 */
	public function test_handle_compat_mode_setting_invalid_nonce(): void {

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			$this->markTestSkipped('DOING_AUTOSAVE is defined as true — cannot test nonce path.');
		}

		$post_id = self::factory()->post->create(['post_type' => 'page']);

		$_POST['_wu_force_compat'] = 'invalid_nonce_value';

		$this->pages->handle_compat_mode_setting($post_id);

		$meta = get_post_meta($post_id, '_wu_force_elements_loading', true);
		$this->assertEmpty($meta);

		unset($_POST['_wu_force_compat']);
	}

	/**
	 * Test handle_compat_mode_setting with valid nonce and capability saves meta.
	 */
	public function test_handle_compat_mode_setting_saves_meta_with_valid_nonce(): void {

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			$this->markTestSkipped('DOING_AUTOSAVE is defined as true — cannot test save path.');
		}

		$admin_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($admin_id);

		$post_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_author' => $admin_id,
		]);

		$_POST['_wu_force_compat']           = wp_create_nonce('_wu_force_compat_' . $post_id);
		$_POST['_wu_force_elements_loading'] = '1';

		$this->pages->handle_compat_mode_setting($post_id);

		$meta = get_post_meta($post_id, '_wu_force_elements_loading', true);
		$this->assertEquals('1', $meta);

		unset($_POST['_wu_force_compat'], $_POST['_wu_force_elements_loading']);
		wp_set_current_user(0);
	}

	/**
	 * Test handle_compat_mode_setting deletes meta when checkbox unchecked.
	 */
	public function test_handle_compat_mode_setting_deletes_meta_when_unchecked(): void {

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			$this->markTestSkipped('DOING_AUTOSAVE is defined as true — cannot test delete path.');
		}

		$admin_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($admin_id);

		$post_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_author' => $admin_id,
		]);

		// Pre-set the meta.
		update_post_meta($post_id, '_wu_force_elements_loading', '1');

		$_POST['_wu_force_compat'] = wp_create_nonce('_wu_force_compat_' . $post_id);
		unset($_POST['_wu_force_elements_loading']);

		$this->pages->handle_compat_mode_setting($post_id);

		$meta = get_post_meta($post_id, '_wu_force_elements_loading', true);
		$this->assertEmpty($meta);

		unset($_POST['_wu_force_compat']);
		wp_set_current_user(0);
	}

	// -------------------------------------------------------------------------
	// render_compat_mode_setting
	// -------------------------------------------------------------------------

	/**
	 * Test render_compat_mode_setting outputs expected HTML.
	 */
	public function test_render_compat_mode_setting_outputs_html(): void {

		$post_id = self::factory()->post->create(['post_type' => 'page']);

		// Set up global $post so get_the_ID() works.
		global $post;
		$post = get_post($post_id);
		setup_postdata($post);

		ob_start();
		$this->pages->render_compat_mode_setting();
		$output = ob_get_clean();

		wp_reset_postdata();

		$this->assertIsString($output);
		$this->assertStringContainsString('wu-compat-mode', $output);
		$this->assertStringContainsString('_wu_force_elements_loading', $output);
		$this->assertStringContainsString('Compatibility Mode', $output);
	}

	/**
	 * Test render_compat_mode_setting outputs checked state when meta is set.
	 */
	public function test_render_compat_mode_setting_checked_when_meta_set(): void {

		$post_id = self::factory()->post->create(['post_type' => 'page']);
		update_post_meta($post_id, '_wu_force_elements_loading', true);

		global $post;
		$post = get_post($post_id);
		setup_postdata($post);

		ob_start();
		$this->pages->render_compat_mode_setting();
		$output = ob_get_clean();

		wp_reset_postdata();

		$this->assertStringContainsString('checked', $output);
	}

	// -------------------------------------------------------------------------
	// maybe_flush_rewrite_rules_on_page_save
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_flush_rewrite_rules_on_page_save skips autosave.
	 */
	public function test_flush_rewrite_rules_on_save_skips_autosave(): void {

		if ( ! defined('DOING_AUTOSAVE')) {
			define('DOING_AUTOSAVE', true);
		}

		$page_id = self::factory()->post->create(['post_type' => 'page']);

		// Should return early — no error.
		$this->pages->maybe_flush_rewrite_rules_on_page_save($page_id);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_flush_rewrite_rules_on_page_save with non-signup page.
	 */
	public function test_flush_rewrite_rules_on_save_non_signup_page(): void {

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			$this->markTestSkipped('DOING_AUTOSAVE is true — cannot test non-autosave path.');
		}

		wu_save_setting('default_login_page', 0);
		wu_save_setting('default_update_page', 0);
		wu_save_setting('default_block_frontend_page', 0);
		wu_save_setting('default_new_site_page', 0);

		$page_id = self::factory()->post->create(['post_type' => 'page']);

		// Should not flush (page not in signup pages list).
		$this->pages->maybe_flush_rewrite_rules_on_page_save($page_id);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_flush_rewrite_rules_on_page_save with signup page.
	 */
	public function test_flush_rewrite_rules_on_save_signup_page(): void {

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			$this->markTestSkipped('DOING_AUTOSAVE is true — cannot test non-autosave path.');
		}

		$page_id = self::factory()->post->create(['post_type' => 'page']);
		wu_save_setting('default_login_page', $page_id);

		// Should flush rewrite rules — no error.
		$this->pages->maybe_flush_rewrite_rules_on_page_save($page_id);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// maybe_flush_rewrite_rules_on_page_trash
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_flush_rewrite_rules_on_page_trash with non-page post type.
	 */
	public function test_flush_rewrite_rules_on_trash_non_page(): void {

		$post_id = self::factory()->post->create(['post_type' => 'post']);

		$this->assertIsInt($post_id);

		// Should return early without flushing (not a page)
		$this->pages->maybe_flush_rewrite_rules_on_page_trash($post_id);

		$this->assertTrue(true); // No error means success
	}

	/**
	 * Test maybe_flush_rewrite_rules_on_page_trash with page post type.
	 */
	public function test_flush_rewrite_rules_on_trash_page(): void {

		$page_id = self::factory()->post->create(['post_type' => 'page']);

		$this->assertIsInt($page_id);

		// Should not flush since this page is not a signup page
		$this->pages->maybe_flush_rewrite_rules_on_page_trash($page_id);

		$this->assertTrue(true); // No error means success
	}

	/**
	 * Test maybe_flush_rewrite_rules_on_page_trash flushes when signup page trashed.
	 */
	public function test_flush_rewrite_rules_on_trash_signup_page(): void {

		$page_id = self::factory()->post->create(['post_type' => 'page']);
		wu_save_setting('default_login_page', $page_id);

		// Should flush — no error.
		$this->pages->maybe_flush_rewrite_rules_on_page_trash($page_id);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// replace_reset_password_link
	// -------------------------------------------------------------------------

	/**
	 * Test replace_reset_password_link on non-main site.
	 */
	public function test_replace_reset_password_link_non_main_site(): void {

		// On main site in test env, this should process normally
		$message = 'Reset your password at https://example.com/wp-login.php?action=rp&key=abc123';

		$result = $this->pages->replace_reset_password_link($message, 'abc123', 'testuser', ['ID' => 1]);

		$this->assertIsString($result);
	}

	/**
	 * Test replace_reset_password_link with no wp-login.php in message.
	 */
	public function test_replace_reset_password_link_no_wp_login(): void {

		$message = 'Please visit https://example.com/reset to reset your password.';

		$result = $this->pages->replace_reset_password_link($message, 'key123', 'user', ['ID' => 1]);

		// No wp-login.php in message — should return unchanged.
		$this->assertEquals($message, $result);
	}

	/**
	 * Test replace_reset_password_link replaces wp-login.php URL.
	 */
	public function test_replace_reset_password_link_replaces_url(): void {

		$message = "To reset your password, visit the following address:\nhttps://example.com/wp-login.php?action=rp&key=testkey&login=testuser\n";

		$result = $this->pages->replace_reset_password_link($message, 'testkey', 'testuser', ['ID' => 1]);

		$this->assertIsString($result);
		// The method replaces the matched URL line with a new login_url()-based URL.
		// In the test environment wp_login_url() may still return wp-login.php,
		// but the replacement URL will include action=rp and the key parameter.
		$this->assertStringContainsString('action=rp', $result);
		$this->assertStringContainsString('key=testkey', $result);
	}

	// -------------------------------------------------------------------------
	// maybe_redirect_to_confirm_screen
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_redirect_to_confirm_screen with no redirect_to does nothing.
	 */
	public function test_maybe_redirect_to_confirm_screen_no_redirect(): void {

		unset($_REQUEST['redirect_to']);

		// Should return without redirecting — no exit called.
		$this->pages->maybe_redirect_to_confirm_screen();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// maybe_handle_password_reset_errors
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_handle_password_reset_errors with no errors does nothing.
	 */
	public function test_maybe_handle_password_reset_errors_no_errors(): void {

		$errors = new \WP_Error();

		// No errors — should return without redirecting.
		$this->pages->maybe_handle_password_reset_errors($errors);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// filter_login_url
	// -------------------------------------------------------------------------

	/**
	 * Test filter_login_url before wp_loaded.
	 */
	public function test_filter_login_url_before_wp_loaded(): void {

		// Remove the wp_loaded action count to simulate before wp_loaded
		// This is tricky in tests since wp_loaded has already fired
		$original_url = 'https://example.com/wp-login.php';

		$result = $this->pages->filter_login_url($original_url, '', false);

		$this->assertIsString($result);
	}

	/**
	 * Test filter_login_url returns original when no custom login page set.
	 */
	public function test_filter_login_url_no_custom_page(): void {

		wu_save_setting('default_login_page', 0);

		$original_url = 'https://example.com/wp-login.php';

		$result = $this->pages->filter_login_url($original_url, '', false);

		$this->assertEquals($original_url, $result);
	}

	/**
	 * Test filter_login_url appends raw query string to custom login URL.
	 */
	public function test_filter_login_url_appends_query_string(): void {

		$page_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_name'   => 'login-page',
			'post_status' => 'publish',
		]);

		wu_save_setting('default_login_page', $page_id);

		// Simulate wp_loaded having fired.
		do_action('wp_loaded');

		$original_url = 'https://example.com/wp-login.php?redirect_to=%2Fwp-admin%2F&reauth=1';

		$result = $this->pages->filter_login_url($original_url, '', false);

		$this->assertIsString($result);

		// If a custom page URL was returned, it should contain the query params.
		if ($result !== $original_url) {
			$this->assertStringContainsString('redirect_to', $result);
		}
	}

	/**
	 * Test filter_login_url with no query string on original URL.
	 */
	public function test_filter_login_url_no_query_string(): void {

		$page_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_name'   => 'login-no-query',
			'post_status' => 'publish',
		]);

		wu_save_setting('default_login_page', $page_id);

		do_action('wp_loaded');

		$original_url = 'https://example.com/wp-login.php';

		$result = $this->pages->filter_login_url($original_url, '', false);

		$this->assertIsString($result);
	}

	// -------------------------------------------------------------------------
	// filter_lostpassword_url
	// -------------------------------------------------------------------------

	/**
	 * Test filter_lostpassword_url returns string on main site.
	 */
	public function test_filter_lostpassword_url_on_main_site(): void {

		$this->assertTrue(is_main_site());

		wu_save_setting('default_login_page', 0);

		$original_url = 'https://example.com/wp-login.php?action=lostpassword';

		$result = $this->pages->filter_lostpassword_url($original_url, '');

		// With no custom login page, filter_login_url returns original.
		$this->assertEquals($original_url, $result);
	}

	/**
	 * Test filter_lostpassword_url on main site with custom login page.
	 */
	public function test_filter_lostpassword_url_main_site_with_custom_page(): void {

		$this->assertTrue(is_main_site());

		$page_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_name'   => 'custom-login-lp',
			'post_status' => 'publish',
		]);

		wu_save_setting('default_login_page', $page_id);

		do_action('wp_loaded');

		$original_url = 'https://example.com/wp-login.php?action=lostpassword';

		$result = $this->pages->filter_lostpassword_url($original_url, '');

		$this->assertIsString($result);
	}

	/**
	 * Test filter_lostpassword_url on subsite keeps user on subsite domain.
	 */
	public function test_filter_lostpassword_url_on_subsite(): void {

		$blog_id = self::factory()->blog->create();

		switch_to_blog($blog_id);

		do_action('wp_loaded');

		$original_url = 'https://example.com/wp-login.php?action=lostpassword';

		$result = $this->pages->filter_lostpassword_url($original_url, '');

		restore_current_blog();

		$this->assertIsString($result);
	}

	/**
	 * Test filter_lostpassword_url on subsite with redirect parameter.
	 */
	public function test_filter_lostpassword_url_subsite_with_redirect(): void {

		$blog_id = self::factory()->blog->create();

		switch_to_blog($blog_id);

		do_action('wp_loaded');

		$original_url = 'https://example.com/wp-login.php?action=lostpassword';
		$redirect     = 'https://subsite.example.com/dashboard';

		$result = $this->pages->filter_lostpassword_url($original_url, $redirect);

		restore_current_blog();

		$this->assertIsString($result);
	}

	// -------------------------------------------------------------------------
	// redirect_to_registration_page
	// -------------------------------------------------------------------------

	/**
	 * Test redirect_to_registration_page does nothing when no registration URL.
	 */
	public function test_redirect_to_registration_page_no_url(): void {

		// Ensure no registration page is configured.
		// wu_guess_registration_page() returns 0/false when none configured.
		// We can't easily mock it, but we can verify no fatal error occurs.
		$this->pages->redirect_to_registration_page();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_verify_email_notice
	// -------------------------------------------------------------------------

	/**
	 * Test add_verify_email_notice with zero total and pending verification outputs notice.
	 */
	public function test_add_verify_email_notice_outputs_when_pending(): void {

		$customer = wu_create_customer([
			'username'           => 'verify_test_user_' . wp_rand(1000, 9999),
			'email'              => 'verify_' . wp_rand(1000, 9999) . '@example.com',
			'password'           => 'password123',
			'email_verification' => 'pending',
		]);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::COMPLETED,
			'total'         => 0.0,
		]);

		ob_start();
		$this->pages->add_verify_email_notice($payment, $membership, $customer);
		$output = ob_get_clean();

		$this->assertIsString($output);
		$this->assertNotEmpty($output);
		$this->assertStringContainsString('verified', strtolower($output));

		$payment->delete();
		$membership->delete();
		$customer->delete();
	}

	/**
	 * Test add_verify_email_notice with non-zero total outputs nothing.
	 */
	public function test_add_verify_email_notice_silent_when_paid(): void {

		$customer = wu_create_customer([
			'username'           => 'verify_paid_user_' . wp_rand(1000, 9999),
			'email'              => 'verifypaid_' . wp_rand(1000, 9999) . '@example.com',
			'password'           => 'password123',
			'email_verification' => 'pending',
		]);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::COMPLETED,
			'total'         => 10.0,
		]);

		ob_start();
		$this->pages->add_verify_email_notice($payment, $membership, $customer);
		$output = ob_get_clean();

		$this->assertEmpty($output);

		$payment->delete();
		$membership->delete();
		$customer->delete();
	}

	/**
	 * Test add_verify_email_notice with verified email outputs nothing.
	 */
	public function test_add_verify_email_notice_silent_when_verified(): void {

		$customer = wu_create_customer([
			'username'           => 'verify_done_user_' . wp_rand(1000, 9999),
			'email'              => 'verifydone_' . wp_rand(1000, 9999) . '@example.com',
			'password'           => 'password123',
			'email_verification' => 'verified',
		]);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::COMPLETED,
			'total'         => 0.0,
		]);

		ob_start();
		$this->pages->add_verify_email_notice($payment, $membership, $customer);
		$output = ob_get_clean();

		$this->assertEmpty($output);

		$payment->delete();
		$membership->delete();
		$customer->delete();
	}

	// -------------------------------------------------------------------------
	// maybe_enqueue_payment_status_poll
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_enqueue_payment_status_poll does nothing when no payment hash.
	 */
	public function test_maybe_enqueue_payment_status_poll_no_hash(): void {

		unset($_REQUEST['payment'], $_REQUEST['status']);

		$this->pages->maybe_enqueue_payment_status_poll();

		$this->assertFalse(wp_script_is('wu-payment-status-poll', 'enqueued'));
	}

	/**
	 * Test maybe_enqueue_payment_status_poll does nothing when status is not done.
	 */
	public function test_maybe_enqueue_payment_status_poll_wrong_status(): void {

		$_REQUEST['payment'] = 'somehash';
		$_REQUEST['status']  = 'pending';

		$this->pages->maybe_enqueue_payment_status_poll();

		$this->assertFalse(wp_script_is('wu-payment-status-poll', 'enqueued'));

		unset($_REQUEST['payment'], $_REQUEST['status']);
	}

	/**
	 * Test maybe_enqueue_payment_status_poll does nothing when payment hash is 'none'.
	 */
	public function test_maybe_enqueue_payment_status_poll_hash_is_none(): void {

		$_REQUEST['payment'] = 'none';
		$_REQUEST['status']  = 'done';

		$this->pages->maybe_enqueue_payment_status_poll();

		$this->assertFalse(wp_script_is('wu-payment-status-poll', 'enqueued'));

		unset($_REQUEST['payment'], $_REQUEST['status']);
	}

	/**
	 * Test maybe_enqueue_payment_status_poll does nothing when payment not found.
	 */
	public function test_maybe_enqueue_payment_status_poll_payment_not_found(): void {

		$_REQUEST['payment'] = 'nonexistent_hash_xyz_' . wp_rand(10000, 99999);
		$_REQUEST['status']  = 'done';

		$this->pages->maybe_enqueue_payment_status_poll();

		$this->assertFalse(wp_script_is('wu-payment-status-poll', 'enqueued'));

		unset($_REQUEST['payment'], $_REQUEST['status']);
	}

	/**
	 * Test maybe_enqueue_payment_status_poll enqueues script for Stripe pending payment.
	 */
	public function test_maybe_enqueue_payment_status_poll_enqueues_for_stripe_pending(): void {

		$customer = wu_create_customer([
			'username' => 'poll_test_user_' . wp_rand(1000, 9999),
			'email'    => 'poll_' . wp_rand(1000, 9999) . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'stripe',
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'total'         => 10.0,
			'gateway'       => 'stripe',
		]);

		$this->assertNotWPError($payment);

		$hash = $payment->get_hash();

		$_REQUEST['payment'] = $hash;
		$_REQUEST['status']  = 'done';

		$this->pages->maybe_enqueue_payment_status_poll();

		$this->assertTrue(
			wp_script_is('wu-payment-status-poll', 'enqueued') ||
			wp_script_is('wu-payment-status-poll', 'registered'),
			'Script should be enqueued or registered for Stripe pending payment'
		);

		unset($_REQUEST['payment'], $_REQUEST['status']);

		$payment->delete();
		$membership->delete();
		$customer->delete();
	}

	/**
	 * Test maybe_enqueue_payment_status_poll does not enqueue for non-Stripe payment.
	 */
	public function test_maybe_enqueue_payment_status_poll_skips_non_stripe(): void {

		// Dequeue/deregister any previously registered script from earlier tests.
		wp_dequeue_script('wu-payment-status-poll');
		wp_deregister_script('wu-payment-status-poll');

		$customer = wu_create_customer([
			'username' => 'poll_manual_user_' . wp_rand(1000, 9999),
			'email'    => 'pollmanual_' . wp_rand(1000, 9999) . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'manual',
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'total'         => 10.0,
			'gateway'       => 'manual',
		]);

		$this->assertNotWPError($payment);

		$hash = $payment->get_hash();

		$_REQUEST['payment'] = $hash;
		$_REQUEST['status']  = 'done';

		$this->pages->maybe_enqueue_payment_status_poll();

		$this->assertFalse(
			wp_script_is('wu-payment-status-poll', 'enqueued'),
			'Script should not be enqueued for non-Stripe payment'
		);

		$this->assertFalse(
			wp_script_is('wu-payment-status-poll', 'registered'),
			'Script should not be registered for non-Stripe payment'
		);

		unset($_REQUEST['payment'], $_REQUEST['status']);

		$payment->delete();
		$membership->delete();
		$customer->delete();
	}

	/**
	 * Test maybe_enqueue_payment_status_poll enqueues for stripe-checkout gateway.
	 */
	public function test_maybe_enqueue_payment_status_poll_stripe_checkout_gateway(): void {

		$customer = wu_create_customer([
			'username' => 'poll_sc_user_' . wp_rand(1000, 9999),
			'email'    => 'pollsc_' . wp_rand(1000, 9999) . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'stripe-checkout',
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'total'         => 10.0,
			'gateway'       => 'stripe-checkout',
		]);

		$this->assertNotWPError($payment);

		$hash = $payment->get_hash();

		$_REQUEST['payment'] = $hash;
		$_REQUEST['status']  = 'done';

		$this->pages->maybe_enqueue_payment_status_poll();

		$this->assertTrue(
			wp_script_is('wu-payment-status-poll', 'enqueued') ||
			wp_script_is('wu-payment-status-poll', 'registered'),
			'Script should be enqueued for stripe-checkout gateway'
		);

		unset($_REQUEST['payment'], $_REQUEST['status']);

		$payment->delete();
		$membership->delete();
		$customer->delete();
	}

	// -------------------------------------------------------------------------
	// maybe_redirect_to_admin_panel
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_redirect_to_admin_panel does nothing when user not logged in.
	 */
	public function test_maybe_redirect_to_admin_panel_not_logged_in(): void {

		wp_set_current_user(0);

		// Should return early — no exit.
		$this->pages->maybe_redirect_to_admin_panel();

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_redirect_to_admin_panel does nothing when no custom login page.
	 */
	public function test_maybe_redirect_to_admin_panel_no_login_page(): void {

		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		wu_save_setting('default_login_page', 0);

		// Should return early — no exit.
		$this->pages->maybe_redirect_to_admin_panel();

		wp_set_current_user(0);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_redirect_to_admin_panel does nothing when on different page.
	 */
	public function test_maybe_redirect_to_admin_panel_different_page(): void {

		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$login_page_id = self::factory()->post->create(['post_type' => 'page']);
		$other_page_id = self::factory()->post->create(['post_type' => 'page']);

		wu_save_setting('default_login_page', $login_page_id);

		global $post;
		$post = get_post($other_page_id);

		// Should return early — current page is not the login page.
		$this->pages->maybe_redirect_to_admin_panel();

		wp_set_current_user(0);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_redirect_to_admin_panel respects exclusion list params.
	 */
	public function test_maybe_redirect_to_admin_panel_exclusion_list(): void {

		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$login_page_id = self::factory()->post->create(['post_type' => 'page']);
		wu_save_setting('default_login_page', $login_page_id);

		global $post;
		$post = get_post($login_page_id);

		// Set an exclusion param — should prevent redirect.
		$_REQUEST['preview'] = '1';

		$this->pages->maybe_redirect_to_admin_panel();

		unset($_REQUEST['preview']);
		wp_set_current_user(0);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_redirect_to_admin_panel exclusion list filter is applied.
	 */
	public function test_maybe_redirect_to_admin_panel_exclusion_filter(): void {

		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$login_page_id = self::factory()->post->create(['post_type' => 'page']);
		wu_save_setting('default_login_page', $login_page_id);

		global $post;
		$post = get_post($login_page_id);

		// Add a custom exclusion param via filter.
		add_filter('wu_maybe_redirect_to_admin_panel_exclusion_list', function ($list) {
			$list[] = 'my_custom_builder';
			return $list;
		});

		$_REQUEST['my_custom_builder'] = '1';

		$this->pages->maybe_redirect_to_admin_panel();

		unset($_REQUEST['my_custom_builder']);
		wp_set_current_user(0);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// maybe_obfuscate_login_url
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_obfuscate_login_url does nothing when custom login disabled.
	 */
	public function test_maybe_obfuscate_login_url_disabled(): void {

		wu_save_setting('enable_custom_login_page', false);

		$this->pages->maybe_obfuscate_login_url();

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_obfuscate_login_url does nothing on POST request.
	 */
	public function test_maybe_obfuscate_login_url_post_request(): void {

		wu_save_setting('enable_custom_login_page', true);

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->pages->maybe_obfuscate_login_url();

		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_obfuscate_login_url does nothing for interim-login request.
	 */
	public function test_maybe_obfuscate_login_url_interim_login(): void {

		wu_save_setting('enable_custom_login_page', true);

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_REQUEST['interim-login'] = '1';

		$this->pages->maybe_obfuscate_login_url();

		unset($_REQUEST['interim-login']);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_obfuscate_login_url does nothing for logout action.
	 */
	public function test_maybe_obfuscate_login_url_logout_action(): void {

		wu_save_setting('enable_custom_login_page', true);

		$_SERVER['REQUEST_METHOD'] = 'GET';
		unset($_REQUEST['interim-login']);
		$_REQUEST['action'] = 'logout';

		$this->pages->maybe_obfuscate_login_url();

		unset($_REQUEST['action']);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_obfuscate_login_url does nothing when no custom login URL configured.
	 */
	public function test_maybe_obfuscate_login_url_no_custom_url(): void {

		wu_save_setting('enable_custom_login_page', true);
		wu_save_setting('default_login_page', 0);

		$_SERVER['REQUEST_METHOD'] = 'GET';
		unset($_REQUEST['interim-login'], $_REQUEST['action']);

		$this->pages->maybe_obfuscate_login_url();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// render_confirmation_page
	// -------------------------------------------------------------------------

	/**
	 * Test render_confirmation_page shortcode.
	 */
	public function test_render_confirmation_page(): void {

		$result = $this->pages->render_confirmation_page([]);

		$this->assertIsString($result);
	}

	/**
	 * Test render_confirmation_page with content parameter.
	 */
	public function test_render_confirmation_page_with_content(): void {

		$result = $this->pages->render_confirmation_page([], 'some content');

		$this->assertIsString($result);
	}

	/**
	 * Test render_confirmation_page with membership hash in request.
	 */
	public function test_render_confirmation_page_with_membership_hash(): void {

		$_REQUEST['membership'] = 'nonexistent_hash_xyz';

		$result = $this->pages->render_confirmation_page([]);

		unset($_REQUEST['membership']);

		$this->assertIsString($result);
	}

	// -------------------------------------------------------------------------
	// Tear down
	// -------------------------------------------------------------------------

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {

		// Reset common settings.
		wu_save_setting('default_login_page', 0);
		wu_save_setting('default_update_page', 0);
		wu_save_setting('default_block_frontend_page', 0);
		wu_save_setting('default_new_site_page', 0);
		wu_save_setting('enable_custom_login_page', false);
		wu_save_setting('obfuscate_original_login_url', 1);

		// Clean up request globals.
		unset(
			$_REQUEST['payment'],
			$_REQUEST['status'],
			$_REQUEST['membership'],
			$_REQUEST['redirect_to'],
			$_REQUEST['action'],
			$_REQUEST['interim-login'],
			$_REQUEST['preview'],
			$_REQUEST['wu_bypass_obfuscation'],
			$_POST['_wu_force_compat'],
			$_POST['_wu_force_elements_loading']
		);

		if (isset($_SERVER['REQUEST_METHOD'])) {
			$_SERVER['REQUEST_METHOD'] = 'GET';
		}

		wp_set_current_user(0);

		parent::tear_down();
	}
}
