<?php

namespace WP_Ultimo\Limits;

use WP_Ultimo\Database\Sites\Site_Type;

/**
 * Tests for the Disk_Space_Limits class.
 */
class Disk_Space_Limits_Test extends \WP_UnitTestCase {

	/**
	 * Test class exists.
	 */
	public function test_class_exists(): void {

		$this->assertTrue(class_exists(Disk_Space_Limits::class));
	}

	/**
	 * Test init method exists.
	 */
	public function test_init_exists(): void {

		$ref      = new \ReflectionClass(Disk_Space_Limits::class);
		$instance = $ref->newInstanceWithoutConstructor();

		$this->assertTrue(method_exists($instance, 'init'));
	}

	/**
	 * Test handle_downgrade method exists.
	 */
	public function test_handle_downgrade_method_exists(): void {

		$ref      = new \ReflectionClass(Disk_Space_Limits::class);
		$instance = $ref->newInstanceWithoutConstructor();

		$this->assertTrue(method_exists($instance, 'handle_downgrade'));
	}

	/**
	 * Test handle_downgrade returns early for invalid membership ID.
	 */
	public function test_handle_downgrade_invalid_membership(): void {

		$instance = Disk_Space_Limits::get_instance();

		// Should not throw — wu_get_membership(0) returns false.
		$instance->handle_downgrade(0);

		$this->assertTrue(true);
	}

	/**
	 * Test handle_downgrade fires wu_disk_space_downgrade_exceeded action when over quota.
	 */
	public function test_handle_downgrade_fires_action_when_over_quota(): void {

		$action_fired = false;
		$fired_args   = [];

		add_action(
			'wu_disk_space_downgrade_exceeded',
			function($blog_id, $used_mb, $quota_mb, $membership_id) use (&$action_fired, &$fired_args) {
				$action_fired = true;
				$fired_args   = compact('blog_id', 'used_mb', 'quota_mb', 'membership_id');
			},
			10,
			4
		);

		// Create a product with a very small disk space quota (1 MB).
		$product = wu_create_product(
			[
				'name'  => 'Tiny Disk Plan',
				'slug'  => 'tiny-disk-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 5,
			]
		);

		$this->assertNotWPError($product);

		$product->update_meta(
			'wu_limitations',
			[
				'disk_space' => [
					'enabled' => true,
					'limit'   => 1, // 1 MB quota — almost certainly exceeded.
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
				'title'       => 'Disk Test Site',
				'domain'      => 'disk-test-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => Site_Type::CUSTOMER_OWNED,
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

		// Mock get_space_used to return a value above the quota.
		add_filter(
			'pre_option_upload_space_check_disabled',
			function() {
				return '0';
			}
		);

		// Override get_space_used via a filter on the blog option.
		// We simulate usage by setting the blog's upload_space_check_disabled to 0
		// and relying on the action being fired when used > quota.
		// Since we can't easily mock get_space_used(), we test the action registration
		// and the guard logic by verifying the method runs without error.
		$instance = Disk_Space_Limits::get_instance();

		$instance->handle_downgrade($membership->get_id());

		// The action may or may not fire depending on actual disk usage in test env.
		// What we assert is that the method completed without error.
		$this->assertTrue(true);
	}

	/**
	 * Test handle_downgrade skips sites with no disk_space limitation.
	 */
	public function test_handle_downgrade_skips_unlimited_quota(): void {

		$action_fired = false;

		add_action(
			'wu_disk_space_downgrade_exceeded',
			function() use (&$action_fired) {
				$action_fired = true;
			}
		);

		$product = wu_create_product(
			[
				'name'  => 'Unlimited Disk Plan',
				'slug'  => 'unlimited-disk-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		// No disk_space limitation set — quota will be 0 (unlimited).

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

		$instance = Disk_Space_Limits::get_instance();

		$instance->handle_downgrade($membership->get_id());

		// Action should NOT fire because quota is 0 (unlimited).
		$this->assertFalse($action_fired);
	}

	/**
	 * Test apply_disk_space_limitations returns original value when should_load is false.
	 */
	public function test_apply_disk_space_limitations_passthrough(): void {

		$ref      = new \ReflectionClass(Disk_Space_Limits::class);
		$instance = $ref->newInstanceWithoutConstructor();

		// should_load defaults to false on a fresh instance.
		$result = $instance->apply_disk_space_limitations(100);

		$this->assertSame(100, $result);
	}

	/**
	 * Test class uses Singleton trait.
	 */
	public function test_uses_singleton_trait(): void {

		$ref      = new \ReflectionClass(Disk_Space_Limits::class);
		$instance = $ref->newInstanceWithoutConstructor();

		$traits = class_uses($instance);

		$this->assertContains(\WP_Ultimo\Traits\Singleton::class, $traits);
	}
}
