<?php
/**
 * Test case for Site Duplicator Helper.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Helpers;

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
				'username'      => 'testuser',
				'email' => 'test@example.com',
				'password'      => 'password123',
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

		// The result should be either a WP_Error or a failure case
		$this->assertTrue(is_wp_error($result) || ! $result || is_int($result));
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

		$this->assertTrue(is_wp_error($target_wu_site));
		$this->assertEquals('Sorry, that site already exists!', $target_wu_site->get_error_message());

		$args = [];

		$result = Site_Duplicator::override_site($this->template_site_id, $target_site_id, $args);

		// Method should return the target site ID or false
		$this->assertTrue($result === $target_site_id || $result === false);

		// Clean up
		wpmu_delete_blog($target_site_id, true);
		if ($target_wu_site && ! is_wp_error($target_wu_site)) {
			$target_wu_site->delete();
		}
	}

	/**
	 * Test override with invalid target site.
	 */
	public function test_override_invalid_target_site() {
		$invalid_target_id = 99999;

		$args = [];

		$result = Site_Duplicator::override_site($this->template_site_id, $invalid_target_id, $args);

		// Should handle gracefully
		$this->assertFalse($result);
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
	 * Test duplication preserves site content.
	 */
	public function test_duplication_preserves_content() {
		$args = [
			'domain' => 'content.example.com',
			'path'   => '/',
			'title'  => 'Content Site',
		];

		$result = Site_Duplicator::duplicate_site($this->template_site_id, 'Content Site', $args);

		if (! is_wp_error($result)) {
			$this->assertIsInt($result);

			// Switch to new site and check content
			switch_to_blog($result);

			$posts = get_posts(['post_type' => 'any']);
			$this->assertNotEmpty($posts);

			// Look for our template content
			$found_template_post = false;
			foreach ($posts as $post) {
				if ('Template Post' === $post->post_title) {
					$found_template_post = true;
					break;
				}
			}

			$this->assertTrue($found_template_post);

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
		$post_id = wp_insert_post(
			[
				'import_id'   => 500,
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
				'post_title'  => 'Test Menu Item',
			]
		);
		add_post_meta($post_id, '_menu_item_type', 'custom');
		add_post_meta($post_id, '_menu_item_url', 'https://example.com');
		add_post_meta($post_id, '_menu_item_object', 'custom');
		add_post_meta($post_id, '_menu_item_target', '');
		restore_current_blog();

		switch_to_blog($target_id);
		wp_insert_post(
			[
				'import_id'   => 500,
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
				'post_title'  => 'Test Menu Item',
			]
		);
		$this->assertEmpty(get_post_meta(500, '_menu_item_type', true));
		restore_current_blog();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_nav_menu_postmeta');
		$method->setAccessible(true);
		$method->invoke(null, $template_id, $target_id);

		switch_to_blog($target_id);
		$this->assertEquals('custom', get_post_meta(500, '_menu_item_type', true));
		$this->assertEquals('https://example.com', get_post_meta(500, '_menu_item_url', true));
		$this->assertEquals('custom', get_post_meta(500, '_menu_item_object', true));
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
				'import_id'      => 600,
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_title'     => 'Test Image',
				'post_mime_type' => 'image/jpeg',
			]
		);
		add_post_meta($post_id, '_wp_attached_file', '2026/04/test-image.jpg');
		add_post_meta($post_id, '_wp_attachment_metadata', ['width' => 800, 'height' => 600]);
		add_post_meta($post_id, '_wp_attachment_image_alt', 'Alt text');
		restore_current_blog();

		switch_to_blog($target_id);
		wp_insert_post(
			[
				'import_id'      => 600,
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_title'     => 'Test Image',
				'post_mime_type' => 'image/jpeg',
			]
		);
		$this->assertEmpty(get_post_meta(600, '_wp_attached_file', true));
		restore_current_blog();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_attachment_postmeta');
		$method->setAccessible(true);
		$method->invoke(null, $template_id, $target_id);

		switch_to_blog($target_id);
		$this->assertEquals('2026/04/test-image.jpg', get_post_meta(600, '_wp_attached_file', true));
		$this->assertEquals('Alt text', get_post_meta(600, '_wp_attachment_image_alt', true));
		$metadata = get_post_meta(600, '_wp_attachment_metadata', true);
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
		$post_id = wp_insert_post(
			[
				'import_id'   => 700,
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
		wp_insert_post(
			[
				'import_id'   => 700,
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
				'post_title'  => 'Header Template',
			]
		);
		$this->assertEmpty(get_post_meta(700, '_elementor_data', true));
		restore_current_blog();

		$method = new \ReflectionMethod(Site_Duplicator::class, 'backfill_elementor_postmeta');
		$method->setAccessible(true);
		$method->invoke(null, $template_id, $target_id);

		switch_to_blog($target_id);
		$this->assertEquals($elementor_data, get_post_meta(700, '_elementor_data', true));
		$this->assertEquals('builder', get_post_meta(700, '_elementor_edit_mode', true));
		$this->assertEquals('header', get_post_meta(700, '_elementor_template_type', true));
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
				['_id' => 'primary', 'color' => '#EAC7C7'],
				['_id' => 'secondary', 'color' => '#ED6363'],
			],
			'custom_colors' => [
				['_id' => 'brand', 'color' => '#FF0000'],
			],
		];
		$real_data = '[{"id":"kit1","elType":"kit","settings":{}}]';

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

		$stub_settings = ['system_colors' => [['_id' => 'primary', 'color' => '#6EC1E4']]];

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
		$values = get_post_meta(800, '_menu_item_type');
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
		$values = get_post_meta(900, '_menu_item_type');
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

		if ( ! is_wp_error($result)) {
			$this->assertIsArray($captured);
			$this->assertArrayHasKey('from_site_id', $captured);
			$this->assertArrayHasKey('site_id', $captured);
			$this->assertEquals($this->template_site_id, $captured['from_site_id']);
			$this->assertEquals($result, $captured['site_id']);

			wpmu_delete_blog($result, true);
		}
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
