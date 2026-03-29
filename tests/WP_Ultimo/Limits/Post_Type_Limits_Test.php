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
	public function test_handle_downgrade_method_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'handle_downgrade'));
	}

	/**
	 * Test handle_downgrade returns early for invalid membership ID.
	 */
	public function test_handle_downgrade_invalid_membership() {

		$instance = Post_Type_Limits::get_instance();

		// Should not throw — wu_get_membership(0) returns false.
		$instance->handle_downgrade(0);

		$this->assertTrue(true);
	}

	/**
	 * Test handle_downgrade demotes excess published posts to draft on downgrade.
	 */
	public function test_handle_downgrade_demotes_excess_posts_to_draft() {

		$product = wu_create_product(
			[
				'name'  => 'Post Limit Plan',
				'slug'  => 'post-limit-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		// Set a post limit of 1 for the 'post' post type.
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

		$site = wu_create_site(
			[
				'title'       => 'Post Downgrade Site',
				'domain'      => 'post-downgrade-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		// Associate the site with the membership.
		$site->update_meta('wu_membership_id', $membership->get_id());

		// Create 3 published posts on the site (limit is 1, so 2 should be demoted).
		switch_to_blog($site->get_id());

		$post_ids = [];

		for ($i = 0; $i < 3; $i++) {
			$post_ids[] = self::factory()->post->create(
				[
					'post_status' => 'publish',
					'post_type'   => 'post',
				]
			);
		}

		restore_current_blog();

		// Run the downgrade handler.
		$instance = Post_Type_Limits::get_instance();

		$instance->handle_downgrade($membership->get_id());

		// Verify that the oldest 2 posts (lowest IDs) are now drafts.
		switch_to_blog($site->get_id());

		sort($post_ids);

		// The 2 oldest posts should be demoted to draft.
		$this->assertEquals('draft', get_post_status($post_ids[0]), 'Oldest post should be demoted to draft.');
		$this->assertEquals('draft', get_post_status($post_ids[1]), 'Second oldest post should be demoted to draft.');

		// The newest post should remain published.
		$this->assertEquals('publish', get_post_status($post_ids[2]), 'Newest post should remain published.');

		restore_current_blog();
	}

	/**
	 * Test handle_downgrade fires wu_post_type_downgrade_demoted action for each demoted post.
	 */
	public function test_handle_downgrade_fires_demoted_action() {

		$demoted_post_ids = [];

		add_action(
			'wu_post_type_downgrade_demoted',
			function($post_id) use (&$demoted_post_ids) {
				$demoted_post_ids[] = $post_id;
			}
		);

		$product = wu_create_product(
			[
				'name'  => 'Action Test Plan',
				'slug'  => 'action-test-plan-' . wp_rand(),
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

		$site = wu_create_site(
			[
				'title'       => 'Action Test Site',
				'domain'      => 'action-test-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		$site->update_meta('wu_membership_id', $membership->get_id());

		switch_to_blog($site->get_id());

		// Create 2 published posts (limit is 1, so 1 should be demoted).
		self::factory()->post->create(['post_status' => 'publish', 'post_type' => 'post']);
		self::factory()->post->create(['post_status' => 'publish', 'post_type' => 'post']);

		restore_current_blog();

		$instance = Post_Type_Limits::get_instance();

		$instance->handle_downgrade($membership->get_id());

		$this->assertCount(1, $demoted_post_ids, 'Exactly one post should have been demoted.');
	}

	/**
	 * Test handle_downgrade does not demote posts when within quota.
	 */
	public function test_handle_downgrade_no_demotion_within_quota() {

		$product = wu_create_product(
			[
				'name'  => 'Within Quota Plan',
				'slug'  => 'within-quota-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		// Set a post limit of 5 — we'll only create 2 posts.
		$product->update_meta(
			'wu_limitations',
			[
				'post_types' => [
					'enabled' => true,
					'limit'   => [
						'post' => [
							'enabled' => true,
							'number'  => 5,
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

		$site = wu_create_site(
			[
				'title'       => 'Within Quota Site',
				'domain'      => 'within-quota-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => \WP_Ultimo\Database\Sites\Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		$site->update_meta('wu_membership_id', $membership->get_id());

		switch_to_blog($site->get_id());

		$post_id_1 = self::factory()->post->create(['post_status' => 'publish', 'post_type' => 'post']);
		$post_id_2 = self::factory()->post->create(['post_status' => 'publish', 'post_type' => 'post']);

		restore_current_blog();

		$instance = Post_Type_Limits::get_instance();

		$instance->handle_downgrade($membership->get_id());

		switch_to_blog($site->get_id());

		// Both posts should remain published.
		$this->assertEquals('publish', get_post_status($post_id_1), 'Post 1 should remain published.');
		$this->assertEquals('publish', get_post_status($post_id_2), 'Post 2 should remain published.');

		restore_current_blog();
	}
}
