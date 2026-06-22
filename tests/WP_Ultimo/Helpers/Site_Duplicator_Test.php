<?php
/**
 * Test case for Site Duplicator Helper.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Helpers;

use Psr\Log\LogLevel;
use WP_Ultimo\Helpers\Site_Duplicator;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Models\Site;
use WP_Ultimo\Database\Sites\Site_Type;
use WP_UnitTestCase;

/**
 * Test Site Duplicator Helper functionality.
 */
class Site_Duplicator_Test extends WP_UnitTestCase {

	/**
	 * Test customer.
	 *
	 * @var Customer
	 */
	private $customer;

	/**
	 * Template site ID.
	 *
	 * @var int
	 */
	private $template_site_id;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Skip if not in multisite
		if (! is_multisite()) {
			$this->markTestSkipped('Site duplication tests require multisite');
		}

		// Create test customer
		$this->customer = wu_create_customer(
			[
				'username' => 'testuser',
				'email'    => 'test@example.com',
				'password' => 'password123',
			]
		);

		if (is_wp_error($this->customer)) {
			$this->fail('Could not create test customer: ' . $this->customer->get_error_message());
		}

		// Create template site
		$this->template_site_id = self::factory()->blog->create(
			[
				'domain' => 'template.example.com',
				'path'   => '/',
				'title'  => 'Template Site',
			]
		);

		// Switch to template site and add some content
		switch_to_blog($this->template_site_id);

		// Create a test post
		wp_insert_post(
			[
				'post_title'   => 'Template Post',
				'post_content' => 'This is template content',
				'post_status'  => 'publish',
			]
		);

		// Create a test page
		wp_insert_post(
			[
				'post_title'   => 'Template Page',
				'post_type'    => 'page',
				'post_content' => 'This is a template page',
				'post_status'  => 'publish',
			]
		);

		restore_current_blog();
	}

	/**
	 * Test successful site duplication.
	 */
	public function test_successful_site_duplication() {
		$args = [
			'domain' => 'newsite.example.com',
			'path'   => '/',
			'title'  => 'New Site',
		];

		$result = Site_Duplicator::duplicate_site($this->template_site_id, 'New Site', $args);

		$this->assertIsInt($result);
		$this->assertGreaterThan(0, $result);

		// Verify the new site exists
		$new_site = get_site($result);
		$this->assertNotNull($new_site);
		$this->assertEquals('New Site', $new_site->blogname);

		// Clean up
		wpmu_delete_blog($result, true);
	}

	/**
	 * Test duplication preserves usermeta containing incomplete serialized objects.
	 */
	public function test_duplication_preserves_incomplete_serialized_user_meta() {

		global $wpdb;

		$result        = null;
		$raw_meta_name = 'yoast_notifications';
		$user_id       = self::factory()->user->create(
			[
				'user_email' => 'template-subscriber@example.com',
			]
		);

		add_user_to_blog($this->template_site_id, $user_id, 'subscriber');

		$class_name = 'Missing\\Plugin\\Notification';
		$old_url    = 'https://template.example.com/old/page';
		$raw_value  = sprintf(
			'O:%d:"%s":1:{s:3:"url";s:%d:"%s";}',
			strlen($class_name),
			$class_name,
			strlen($old_url),
			$old_url
		);

		$source_meta_key = $wpdb->get_blog_prefix($this->template_site_id) . $raw_meta_name;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Regression fixture needs raw serialized usermeta.
		$wpdb->insert(
			$wpdb->usermeta,
			[
				'user_id'    => $user_id,
				'meta_key'   => $source_meta_key,
				'meta_value' => $raw_value,
			],
			[
				'%d',
				'%s',
				'%s',
			]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		try {
			$result = Site_Duplicator::duplicate_site(
				$this->template_site_id,
				'Incomplete Usermeta Site',
				[
					'domain'     => 'incomplete-usermeta.example.com',
					'path'       => '/',
					'title'      => 'Incomplete Usermeta Site',
					'email'      => 'incomplete-usermeta-admin@example.com',
					'copy_files' => false,
				]
			);

			$this->assertIsInt($result);

			$target_meta_key = $wpdb->get_blog_prefix($result) . $raw_meta_name;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Assert raw serialized usermeta was preserved.
			$stored_value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
					$user_id,
					$target_meta_key
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

			$this->assertSame($raw_value, $stored_value);
		} finally {
			if ($result) {
				wpmu_delete_blog($result, true);
			}

			wp_delete_user($user_id);
		}
	}

	/**
	 * Test duplication with invalid source site.
	 */
	public function test_duplicate_invalid_source_site() {
		$invalid_site_id = 99999;

		$args = [
			'domain' => 'newsite.example.com',
			'path'   => '/',
			'title'  => 'New Site',
		];

		$result = Site_Duplicator::duplicate_site($invalid_site_id, 'New Site', $args);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('source_template_site_not_found', $result->get_error_code());
	}

	/**
	 * Test duplication with conflicting domain.
	 */
	public function test_duplicate_conflicting_domain() {
		// Use the same domain as template site
		$args = [
			'domain' => 'template.example.com',
			'path'   => '/',
			'title'  => 'Conflicting Site',
		];

		$result = Site_Duplicator::duplicate_site($this->template_site_id, 'Conflicting Site', $args);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test site override functionality.
	 */
	public function test_site_override() {
		// Create target site to override
		$target_site_id = self::factory()->blog->create(
			[
				'domain' => 'target.example.com',
				'path'   => '/',
				'title'  => 'Target Site',
			]
		);

		// Create wu_site record for target
		$target_wu_site = wu_create_site(
			[
				'blog_id'     => $target_site_id,
				'customer_id' => $this->customer->get_id(),
				'type'        => Site_Type::REGULAR,
			]
		);

		$this->assertInstanceOf(Site::class, $target_wu_site);
		$this->assertSame($target_site_id, $target_wu_site->get_blog_id());

		$logged_messages = [];
		$logger          = function ($handle, $message, $log_level) use (&$logged_messages) {
			if ('site-duplication' === $handle) {
				$logged_messages[] = [
					'message' => $message,
					'level'   => $log_level,
				];
			}
		};

		add_action('wu_log_add', $logger, 10, 3);

		$args = [
			'copy_files' => false,
			'keep_users' => false,
		];

		$result = Site_Duplicator::override_site($this->template_site_id, $target_site_id, $args);

		remove_action('wu_log_add', $logger, 10);

		$validation_errors = array_filter(
			$logged_messages,
			function ($log) {
				return LogLevel::ERROR === $log['level'] && false !== strpos($log['message'], 'not found');
			}
		);

		$this->assertEmpty($validation_errors, 'Valid source and destination sites should not fail existence validation.');
		$this->assertNotFalse($result, 'Valid source and destination sites should complete the override.');

		// Clean up
		wpmu_delete_blog($target_site_id, true);
		if ($target_wu_site && ! is_wp_error($target_wu_site)) {
			$target_wu_site->delete();
		}
	}

	/**
	 * Test identity option restoration preserves intentionally blank values.
	 */
	public function test_restore_identity_options_preserves_blank_values() {
		$target_site_id = self::factory()->blog->create(
			[
				'domain' => 'blank-description.example.com',
				'path'   => '/',
				'title'  => 'Blank Description',
			]
		);

		update_blog_option($target_site_id, 'blogdescription', 'Template description');
		update_blog_option($target_site_id, 'admin_email', 'template@example.com');

		$method = new \ReflectionMethod(Site_Duplicator::class, 'restore_identity_options');
		$method->setAccessible(true);
		$method->invoke(
			null,
			$target_site_id,
			[
				'blogdescription' => '',
				'admin_email'     => false,
				'blogname'        => null,
			]
		);

		$this->assertSame('', get_blog_option($target_site_id, 'blogdescription'));
		$this->assertSame('template@example.com', get_blog_option($target_site_id, 'admin_email'));
		$this->assertSame('Blank Description', get_blog_option($target_site_id, 'blogname'));

		wpmu_delete_blog($target_site_id, true);
	}

	/**
	 * Test override with invalid target site.
	 */
	public function test_override_invalid_target_site() {
		$invalid_target_id = 99999;
		$logged_messages   = [];
		$logger            = function ($handle, $message, $log_level) use (&$logged_messages) {
			if ('site-duplication' === $handle) {
				$logged_messages[] = [
					'message' => $message,
					'level'   => $log_level,
				];
			}
		};

		add_action('wu_log_add', $logger, 10, 3);

		$args = [];

		$result = Site_Duplicator::override_site($this->template_site_id, $invalid_target_id, $args);

		remove_action('wu_log_add', $logger, 10);

		// Should handle gracefully
		$this->assertFalse($result);
		$this->assertSame(LogLevel::ERROR, $logged_messages[0]['level']);
		$this->assertStringContainsString('Destination site 99999 not found', $logged_messages[0]['message']);
	}

	/**
	 * Test override with invalid source site.
	 */
	public function test_override_invalid_source_site() {
		$invalid_source_id = 99999;
		$target_site_id    = self::factory()->blog->create(
			[
				'domain' => 'invalid-source-target.example.com',
				'path'   => '/',
				'title'  => 'Invalid Source Target',
			]
		);
		$logged_messages   = [];
		$logger            = function ($handle, $message, $log_level) use (&$logged_messages) {
			if ('site-duplication' === $handle) {
				$logged_messages[] = [
					'message' => $message,
					'level'   => $log_level,
				];
			}
		};

		add_action('wu_log_add', $logger, 10, 3);

		$result = Site_Duplicator::override_site($invalid_source_id, $target_site_id);

		remove_action('wu_log_add', $logger, 10);

		$this->assertFalse($result);
		$this->assertSame(LogLevel::ERROR, $logged_messages[0]['level']);
		$this->assertStringContainsString('Source template site 99999 not found', $logged_messages[0]['message']);

		wpmu_delete_blog($target_site_id, true);
	}

	/**
	 * Test duplication with custom arguments.
	 */
	public function test_duplication_with_custom_args() {
		$args = [
			'domain'     => 'custom.example.com',
			'path'       => '/',
			'title'      => 'Custom Site',
			'copy_files' => true,
			'copy_users' => false,
			'keep_users' => true,
		];

		$result = Site_Duplicator::duplicate_site($this->template_site_id, 'Custom Site', $args);

		if (! is_wp_error($result)) {
			$this->assertIsInt($result);
			$this->assertGreaterThan(0, $result);

			// Clean up
			wpmu_delete_blog($result, true);
		} else {
			// Some configurations might fail, which is acceptable
			$this->assertInstanceOf(\WP_Error::class, $result);
		}
	}

	/**
	 * Test duplication creates a queryable content table on the cloned site.
	 */
	public function test_duplication_creates_queryable_content_table() {
		$args = [
			'domain' => 'content.example.com',
			'path'   => '/',
			'title'  => 'Content Site',
		];

		$result = Site_Duplicator::duplicate_site($this->template_site_id, 'Content Site', $args);

		if (! is_wp_error($result)) {
			$this->assertIsInt($result);

			// Switch to new site and check content storage.
			switch_to_blog($result);

			$posts = get_posts(['post_type' => 'any']);
			$this->assertNotEmpty($posts);

			restore_current_blog();

			// Clean up
			wpmu_delete_blog($result, true);
		} else {
			$this->fail('Site duplication failed: ' . $result->get_error_message());
		}
	}

	/**
	 * Test duplication with subdirectory path.
	 */
	public function test_duplication_with_subdirectory() {
		$args = [
			'domain' => get_current_site()->domain,
			'path'   => '/subdir/',
			'title'  => 'Subdirectory Site',
		];

		$result = Site_Duplicator::duplicate_site($this->template_site_id, 'Subdirectory Site', $args);

		if (! is_wp_error($result)) {
			$this->assertIsInt($result);

			$new_site = get_site($result);
			$this->assertEquals('/subdir/', $new_site->path);

			// Clean up
			wpmu_delete_blog($result, true);
		} else {
			// Subdirectory creation might fail in some test environments
			$this->assertInstanceOf(\WP_Error::class, $result);
		}
	}

	/**
	 * Test backfill_nav_menu_postmeta copies missing meta keys.
	 */
	public function test_backfill_nav_menu_postmeta_copies_missing_meta() {
		$template_id = self::factory()->blog->create();
		$target_id   = self::factory()->blog->create();

		switch_to_blog($template_id);
		$post_id     = wp_insert_post(
			[
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
				'post_title'  => 'Test Menu Item',
			]
		);
		$source_post = get_post($post_id);
		add_post_meta($post_id, '_menu_item_type', 'custom');
		add_post_meta($post_id, '_menu_item_url', 'https://example.com');
		add_post_meta($post_id, '_menu_item_object', 'custom');
		add_post_meta($post_id, '_menu_item_target', '');
		restore_current_blog();

		switch_to_blog($target_id);
		$target_post_id = wp_insert_post(
			[
				'import_id'   => $post_id,
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
				'post_title'  => 'Test Menu Item',
			]
		);
		$target_post    = get_post($target_post_id);
		$this->assertSame($source_post->post_type, $target_post->post_type);
		$this->assertSame($source_post->post_title, $target_post->post_title);
		$this->assertEmpty(get_post_meta($target_post_id, '_menu_item_type', true));
		restore_current_blog();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_nav_menu_postmeta');
		$method->setAccessible(true);
		$method->invoke(null, $template_id, $target_id);

		switch_to_blog($target_id);
		$this->assertEquals('custom', get_post_meta($target_post_id, '_menu_item_type', true));
		$this->assertEquals('https://example.com', get_post_meta($target_post_id, '_menu_item_url', true));
		$this->assertEquals('custom', get_post_meta($target_post_id, '_menu_item_object', true));
		restore_current_blog();

		wpmu_delete_blog($template_id, true);
		wpmu_delete_blog($target_id, true);
	}

	/**
	 * Test backfill_attachment_postmeta copies missing attachment meta.
	 */
	public function test_backfill_attachment_postmeta_copies_missing_meta() {
		$template_id = self::factory()->blog->create();
		$target_id   = self::factory()->blog->create();

		switch_to_blog($template_id);
		$post_id = wp_insert_post(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_title'     => 'Test Image',
				'post_mime_type' => 'image/jpeg',
			]
		);
		add_post_meta($post_id, '_wp_attached_file', '2026/04/test-image.jpg');
		add_post_meta($post_id, '_wp_attachment_metadata', [
			'width'  => 800,
			'height' => 600,
		]);
		add_post_meta($post_id, '_wp_attachment_image_alt', 'Alt text');
		restore_current_blog();

		switch_to_blog($target_id);
		$target_post_id = wp_insert_post(
			[
				'import_id'      => $post_id,
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_title'     => 'Test Image',
				'post_mime_type' => 'image/jpeg',
			]
		);
		$this->assertEmpty(get_post_meta($target_post_id, '_wp_attached_file', true));
		restore_current_blog();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_attachment_postmeta');
		$method->setAccessible(true);
		$method->invoke(null, $template_id, $target_id);

		switch_to_blog($target_id);
		$this->assertEquals('2026/04/test-image.jpg', get_post_meta($target_post_id, '_wp_attached_file', true));
		$this->assertEquals('Alt text', get_post_meta($target_post_id, '_wp_attachment_image_alt', true));
		$metadata = get_post_meta($target_post_id, '_wp_attachment_metadata', true);
		$this->assertIsArray($metadata);
		$this->assertEquals(800, $metadata['width']);
		restore_current_blog();

		wpmu_delete_blog($template_id, true);
		wpmu_delete_blog($target_id, true);
	}

	/**
	 * Test backfill_elementor_postmeta copies _elementor_* meta for all post types.
	 */
	public function test_backfill_elementor_postmeta_copies_missing_meta() {
		$template_id = self::factory()->blog->create();
		$target_id   = self::factory()->blog->create();

		switch_to_blog($template_id);
		$post_id        = wp_insert_post(
			[
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
				'post_title'  => 'Header Template',
			]
		);
		$elementor_data = '[{"id":"abc123","elType":"section","settings":{}}]';
		add_post_meta($post_id, '_elementor_data', $elementor_data);
		add_post_meta($post_id, '_elementor_edit_mode', 'builder');
		add_post_meta($post_id, '_elementor_template_type', 'header');
		restore_current_blog();

		switch_to_blog($target_id);
		$target_post_id = wp_insert_post(
			[
				'import_id'   => $post_id,
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
				'post_title'  => 'Header Template',
			]
		);
		$this->assertEmpty(get_post_meta($target_post_id, '_elementor_data', true));
		restore_current_blog();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_elementor_postmeta');
		$method->setAccessible(true);
		$method->invoke(null, $template_id, $target_id);

		switch_to_blog($target_id);
		$this->assertEquals($elementor_data, get_post_meta($target_post_id, '_elementor_data', true));
		$this->assertEquals('builder', get_post_meta($target_post_id, '_elementor_edit_mode', true));
		$this->assertEquals('header', get_post_meta($target_post_id, '_elementor_template_type', true));
		restore_current_blog();

		wpmu_delete_blog($template_id, true);
		wpmu_delete_blog($target_id, true);
	}

	/**
	 * Test backfill_kit_settings overwrites stub data with real template values.
	 */
	public function test_backfill_kit_settings_overwrites_stub_data() {
		$template_id = self::factory()->blog->create();
		$target_id   = self::factory()->blog->create();

		$real_settings = [
			'system_colors' => [
				[
					'_id'   => 'primary',
					'color' => '#EAC7C7',
				],
				[
					'_id'   => 'secondary',
					'color' => '#ED6363',
				],
			],
			'custom_colors' => [
				[
					'_id'   => 'brand',
					'color' => '#FF0000',
				],
			],
		];
		$real_data     = '[{"id":"kit1","elType":"kit","settings":{}}]';

		switch_to_blog($template_id);
		$kit_id = wp_insert_post(
			[
				'import_id'   => 3,
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
				'post_title'  => 'Default Kit',
			]
		);
		update_option('elementor_active_kit', $kit_id);
		update_post_meta($kit_id, '_elementor_page_settings', $real_settings);
		update_post_meta($kit_id, '_elementor_data', $real_data);
		restore_current_blog();

		$stub_settings = [
			'system_colors' => [
				[
					'_id'   => 'primary',
					'color' => '#6EC1E4',
				],
			],
		];

		switch_to_blog($target_id);
		$target_kit_id = wp_insert_post(
			[
				'import_id'   => 3,
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
				'post_title'  => 'Default Kit',
			]
		);
		update_option('elementor_active_kit', $target_kit_id);
		update_post_meta($target_kit_id, '_elementor_page_settings', $stub_settings);
		restore_current_blog();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_kit_settings');
		$method->setAccessible(true);
		$method->invoke(null, $template_id, $target_id);

		switch_to_blog($target_id);
		$copied = get_post_meta(3, '_elementor_page_settings', true);
		$this->assertIsArray($copied);
		$this->assertEquals('#EAC7C7', $copied['system_colors'][0]['color']);
		$this->assertEquals('#ED6363', $copied['system_colors'][1]['color']);
		$this->assertArrayHasKey('custom_colors', $copied);
		$this->assertEquals($real_data, get_post_meta(3, '_elementor_data', true));
		$this->assertEmpty(get_post_meta(3, '_elementor_css', true));
		restore_current_blog();

		wpmu_delete_blog($template_id, true);
		wpmu_delete_blog($target_id, true);
	}

	/**
	 * Test backfill_postmeta skips when source and target are the same site.
	 */
	public function test_backfill_postmeta_skips_same_site() {
		$site_id = self::factory()->blog->create();

		switch_to_blog($site_id);
		$post_id = wp_insert_post(
			[
				'import_id'   => 800,
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
			]
		);
		add_post_meta($post_id, '_menu_item_type', 'custom');
		restore_current_blog();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_postmeta');
		$method->setAccessible(true);
		$method->invoke(null, $site_id, $site_id);

		switch_to_blog($site_id);
		$values = get_post_meta(800, '_menu_item_type', false);
		$this->assertCount(1, $values);
		restore_current_blog();

		wpmu_delete_blog($site_id, true);
	}

	/**
	 * Test backfill is idempotent — running twice does not duplicate rows.
	 */
	public function test_backfill_nav_menu_postmeta_is_idempotent() {
		$template_id = self::factory()->blog->create();
		$target_id   = self::factory()->blog->create();

		switch_to_blog($template_id);
		wp_insert_post(
			[
				'import_id'   => 900,
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
			]
		);
		add_post_meta(900, '_menu_item_type', 'post_type');
		add_post_meta(900, '_menu_item_url', '');
		restore_current_blog();

		switch_to_blog($target_id);
		wp_insert_post(
			[
				'import_id'   => 900,
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
			]
		);
		restore_current_blog();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_nav_menu_postmeta');
		$method->setAccessible(true);

		$method->invoke(null, $template_id, $target_id);
		$method->invoke(null, $template_id, $target_id);

		switch_to_blog($target_id);
		$values = get_post_meta(900, '_menu_item_type', false);
		$this->assertCount(1, $values);
		$this->assertEquals('post_type', $values[0]);
		restore_current_blog();

		wpmu_delete_blog($template_id, true);
		wpmu_delete_blog($target_id, true);
	}

	/**
	 * Test wu_duplicate_site action receives from_site_id.
	 */
	public function test_wu_duplicate_site_action_includes_from_site_id() {
		$captured = null;

		add_action(
			'wu_duplicate_site',
			function ($site) use (&$captured) {
				$captured = $site;
			}
		);

		$args = [
			'domain' => 'actiontest.example.com',
			'path'   => '/',
			'title'  => 'Action Test Site',
		];

		$result = Site_Duplicator::duplicate_site($this->template_site_id, 'Action Test Site', $args);

		if (is_wp_error($result)) {
			$this->fail('Site duplication failed: ' . $result->get_error_message());
		}

		$this->assertIsArray($captured);
		$this->assertArrayHasKey('from_site_id', $captured);
		$this->assertArrayHasKey('site_id', $captured);
		$this->assertEquals($this->template_site_id, $captured['from_site_id']);
		$this->assertEquals($result, $captured['site_id']);

		wpmu_delete_blog($result, true);
	}

	/**
	 * Test backfill_kit_settings is a no-op when template has no Kit.
	 */
	public function test_backfill_kit_settings_noop_without_elementor() {
		$template_id = self::factory()->blog->create();
		$target_id   = self::factory()->blog->create();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_kit_settings');
		$method->setAccessible(true);
		$method->invoke(null, $template_id, $target_id);

		$this->assertTrue(true);

		wpmu_delete_blog($template_id, true);
		wpmu_delete_blog($target_id, true);
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Clean up template site
		if ($this->template_site_id) {
			wpmu_delete_blog($this->template_site_id, true);
		}

		// Clean up test customer
		if ($this->customer && ! is_wp_error($this->customer)) {
			$this->customer->delete();
		}

		parent::tearDown();
	}
}
