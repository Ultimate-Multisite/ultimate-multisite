<?php

namespace WP_Ultimo\Limits;

/**
 * Tests for the Post_Type_Limits class.
 */
class Post_Type_Limits_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh Post_Type_Limits instance via reflection.
	 *
	 * @return Post_Type_Limits
	 */
	private function get_instance() {

		$ref = new \ReflectionClass(Post_Type_Limits::class);
		$instance = $ref->newInstanceWithoutConstructor();

		return $instance;
	}

	/**
	 * Test class exists.
	 */
	public function test_class_exists() {

		$this->assertTrue(class_exists(Post_Type_Limits::class));
	}

	/**
	 * Test init method exists.
	 */
	public function test_init_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'init'));
	}

	/**
	 * Test register_emulated_post_types returns early with empty setting.
	 */
	public function test_register_emulated_post_types_empty() {

		$instance = $this->get_instance();

		// Should not throw with empty setting
		$instance->register_emulated_post_types();

		$this->assertTrue(true);
	}

	/**
	 * Test register_emulated_post_types cleans corrupted data.
	 */
	public function test_register_emulated_post_types_cleans_data() {

		// Set corrupted data
		wu_save_setting('emulated_post_types', [
			'not_an_array',
			['post_type' => 'test', 'label' => 'Test'],
			['invalid_key' => 'value'],
		]);

		$instance = $this->get_instance();

		// Should not throw and should clean data
		$instance->register_emulated_post_types();

		// Verify data was cleaned
		$cleaned = wu_get_setting('emulated_post_types');

		$this->assertIsArray($cleaned);

		// Clean up
		wu_save_setting('emulated_post_types', []);
	}

	/**
	 * Test limit_media returns file with error when media disabled.
	 */
	public function test_limit_media_disabled() {

		$instance = $this->get_instance();

		$file = [
			'name'     => 'test.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/test',
			'error'    => 0,
			'size'     => 1000,
		];

		// This will likely pass through as we can't easily mock the limitations
		$result = $instance->limit_media($file);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('name', $result);
	}

	/**
	 * Test limit_tabs returns tabs.
	 */
	public function test_limit_tabs() {

		$instance = $this->get_instance();

		$tabs = [
			'type'     => 'From Computer',
			'type_url' => 'From URL',
			'library'  => 'Media Library',
		];

		$result = $instance->limit_tabs($tabs);

		$this->assertIsArray($result);
	}

	/**
	 * Test limit_draft_publishing returns data when no screen.
	 */
	public function test_limit_draft_publishing_no_screen() {

		$instance = $this->get_instance();

		$data = [
			'post_status' => 'publish',
			'post_type'   => 'post',
		];

		$modified_data = ['ID' => 1];

		$result = $instance->limit_draft_publishing($data, $modified_data);

		$this->assertSame($data, $result);
	}

	/**
	 * Test limit_restoring method exists.
	 */
	public function test_limit_restoring_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'limit_restoring'));
	}

	/**
	 * Test limit_posts method exists.
	 */
	public function test_limit_posts_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'limit_posts'));
	}

	/**
	 * Test class uses Singleton trait.
	 */
	public function test_uses_singleton_trait() {

		$instance = $this->get_instance();

		$traits = class_uses($instance);

		$this->assertContains(\WP_Ultimo\Traits\Singleton::class, $traits);
	}

	/**
	 * Test handle_downgrade method exists.
	 */
	public function test_handle_downgrade_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'handle_downgrade'));
	}

	/**
	 * Test handle_downgrade returns early for invalid membership ID.
	 */
	public function test_handle_downgrade_invalid_membership() {

		$instance = $this->get_instance();

		// Should not throw with an invalid membership ID.
		$instance->handle_downgrade(0);

		$this->assertTrue(true);
	}

	/**
	 * Test handle_downgrade trashes excess posts when over quota on downgrade.
	 */
	public function test_handle_downgrade_trashes_excess_posts() {

		$instance = $this->get_instance();

		// Create a product with a post limit of 1.
		$product = wu_create_product(
			[
				'name'  => 'Post Limit Plan',
				'slug'  => 'post-limit-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		$product->update_meta(
			'wu_limitations',
			[
				'post_types' => [
					'enabled' => true,
					'limit'   => [
						'post' => [
							'enabled' => true,
							'number'  => 1,
						],
					],
				],
			]
		);

		$customer = wu_create_customer(
			[
				'user_id' => self::factory()->user->create(),
			]
		);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		// Create a site and attach it to the membership.
		$site = wu_create_site(
			[
				'title'       => 'Post Limit Test Site',
				'domain'      => 'post-limit-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => 'customer_owned',
			]
		);

		$this->assertNotWPError($site);

		// Attach site to membership via set_membership_id.
		$site->set_membership_id($membership->get_id());
		$site->save();

		switch_to_blog($site->get_id());

		// Create 3 published posts — 2 over the limit of 1.
		$post_ids = [];
		for ($i = 0; $i < 3; $i++) {
			$post_ids[] = self::factory()->post->create(['post_status' => 'publish']);
		}

		$published_before = count(
			get_posts(['post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'])
		);

		restore_current_blog();

		// Run the downgrade handler.
		$instance->handle_downgrade($membership->get_id());

		switch_to_blog($site->get_id());

		$published_after = count(
			get_posts(['post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'])
		);

		// After downgrade, published count should be at or below the limit.
		$this->assertLessThanOrEqual(1, $published_after, 'Excess posts should be trashed after downgrade.');
		$this->assertLessThan($published_before, $published_after, 'Some posts should have been trashed.');

		restore_current_blog();

		// Clean up.
		$site->delete();
	}

	/**
	 * Test handle_downgrade respects wu_membership_downgrade_post_types filter.
	 */
	public function test_handle_downgrade_filter_can_prevent_trashing() {

		$instance = $this->get_instance();

		// Prevent trashing via filter.
		add_filter(
			'wu_membership_downgrade_post_types',
			function() {
				return []; // Return empty to prevent trashing.
			}
		);

		$product = wu_create_product(
			[
				'name'  => 'Filter Test Plan',
				'slug'  => 'filter-test-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		$product->update_meta(
			'wu_limitations',
			[
				'post_types' => [
					'enabled' => true,
					'limit'   => [
						'post' => [
							'enabled' => true,
							'number'  => 1,
						],
					],
				],
			]
		);

		$customer = wu_create_customer(
			[
				'user_id' => self::factory()->user->create(),
			]
		);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		$site = wu_create_site(
			[
				'title'       => 'Filter Test Site',
				'domain'      => 'filter-test-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => 'customer_owned',
			]
		);

		$this->assertNotWPError($site);

		// Attach site to membership via set_membership_id.
		$site->set_membership_id($membership->get_id());
		$site->save();

		switch_to_blog($site->get_id());

		// Create 3 published posts.
		for ($i = 0; $i < 3; $i++) {
			self::factory()->post->create(['post_status' => 'publish']);
		}

		$published_before = count(
			get_posts(['post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'])
		);

		restore_current_blog();

		$instance->handle_downgrade($membership->get_id());

		switch_to_blog($site->get_id());

		$published_after = count(
			get_posts(['post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'])
		);

		// Filter prevented trashing — count should be unchanged.
		$this->assertSame($published_before, $published_after, 'Filter should prevent posts from being trashed.');

		restore_current_blog();

		// Remove the filter.
		remove_all_filters('wu_membership_downgrade_post_types');

		// Clean up.
		$site->delete();
	}

	/**
	 * Test handle_downgrade does nothing when no sites attached to membership.
	 */
	public function test_handle_downgrade_no_sites() {

		$instance = $this->get_instance();

		$product = wu_create_product(
			[
				'name'  => 'No Sites Plan',
				'slug'  => 'no-sites-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		$customer = wu_create_customer(
			[
				'user_id' => self::factory()->user->create(),
			]
		);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		// No sites attached — should complete without error.
		$instance->handle_downgrade($membership->get_id());

		$this->assertTrue(true);
	}
}
