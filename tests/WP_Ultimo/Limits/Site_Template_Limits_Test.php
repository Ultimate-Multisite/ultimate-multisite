<?php

namespace WP_Ultimo\Limits;

use WP_Ultimo\Limitations\Limit_Site_Templates;
use WP_Ultimo\Objects\Limitations;

/**
 * Tests for the Site_Template_Limits class.
 *
 * @covers \WP_Ultimo\Limits\Site_Template_Limits
 */
class Site_Template_Limits_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh Site_Template_Limits instance via reflection.
	 *
	 * @return Site_Template_Limits
	 */
	private function get_instance() {

		$ref      = new \ReflectionClass(Site_Template_Limits::class);
		$instance = $ref->newInstanceWithoutConstructor();

		return $instance;
	}

	/**
	 * Test class exists.
	 */
	public function test_class_exists(): void {

		$this->assertTrue(class_exists(Site_Template_Limits::class));
	}

	/**
	 * Test maybe_force_template_selection overrides template_id when mode is assign_template.
	 */
	public function test_maybe_force_template_selection_assign_mode(): void {

		$instance = $this->get_instance();

		$product = wu_create_product(
			[
				'name'  => 'Test Plan',
				'slug'  => 'test-plan-tpl-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		// Create a template site.
		$template = wu_create_site(
			[
				'title'  => 'Template Site',
				'domain' => 'tpl-assign-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);

		$this->assertNotWPError($template);

		$template_id = $template->get_id();

		// Save limitations on the product with assign_template mode.
		$product->update_meta(
			'wu_limitations',
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'assign_template',
					'limit'   => [
						(string) $template_id => [
							'behavior' => 'pre_selected',
						],
					],
				],
			]
		);

		// Create a membership linked to this product.
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

		// Call the filter handler with template_id = 0 (no selection).
		$result = $instance->maybe_force_template_selection(0, $membership);

		$this->assertEquals($template_id, $result, 'Assign template mode should override template_id to the pre-selected template.');
	}

	/**
	 * Test maybe_force_template_selection does NOT override when mode is default.
	 */
	public function test_maybe_force_template_selection_default_mode(): void {

		$instance = $this->get_instance();

		$product = wu_create_product(
			[
				'name'  => 'Default Plan',
				'slug'  => 'default-plan-tpl-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		// No site_templates limitations — mode defaults to 'default'.

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

		// Call the filter handler with template_id = 42.
		$result = $instance->maybe_force_template_selection(42, $membership);

		$this->assertEquals(42, $result, 'Default mode should not override the template_id.');
	}

	/**
	 * Test maybe_force_template_selection returns false when assign mode has no pre-selected template.
	 */
	public function test_maybe_force_template_selection_assign_mode_no_preselected(): void {

		$instance = $this->get_instance();

		$product = wu_create_product(
			[
				'name'  => 'Empty Assign Plan',
				'slug'  => 'empty-assign-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		// Save limitations with assign_template mode but no pre-selected template.
		$product->update_meta(
			'wu_limitations',
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'assign_template',
					'limit'   => null,
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

		// Call the filter handler with template_id = 0.
		$result = $instance->maybe_force_template_selection(0, $membership);

		$this->assertFalse($result, 'Assign mode with no pre-selected template should return false.');
	}

	/**
	 * Test handle_downgrade method exists.
	 */
	public function test_handle_downgrade_method_exists(): void {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'handle_downgrade'));
	}

	/**
	 * Test handle_downgrade returns early for invalid membership ID.
	 */
	public function test_handle_downgrade_invalid_membership(): void {

		$instance = $this->get_instance();

		// Should not throw — wu_get_membership(0) returns false.
		$instance->handle_downgrade(0);

		$this->assertTrue(true);
	}

	/**
	 * Test handle_downgrade fires wu_site_template_downgrade action.
	 */
	public function test_handle_downgrade_fires_action(): void {

		$action_fired = false;
		$fired_args   = [];

		add_action(
			'wu_site_template_downgrade',
			function($membership_id, $membership) use (&$action_fired, &$fired_args) {
				$action_fired = true;
				$fired_args   = compact('membership_id', 'membership');
			},
			10,
			2
		);

		$product = wu_create_product(
			[
				'name'  => 'Template Downgrade Plan',
				'slug'  => 'tpl-downgrade-plan-' . wp_rand(),
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

		$instance = $this->get_instance();

		$instance->handle_downgrade($membership->get_id());

		$this->assertTrue($action_fired, 'wu_site_template_downgrade action should fire.');
		$this->assertEquals($membership->get_id(), $fired_args['membership_id']);
		$this->assertInstanceOf(\WP_Ultimo\Models\Membership::class, $fired_args['membership']);
	}

	/**
	 * Test handle_downgrade does not modify existing sites (template is a one-time choice).
	 */
	public function test_handle_downgrade_does_not_modify_existing_sites(): void {

		$product = wu_create_product(
			[
				'name'  => 'No Modify Plan',
				'slug'  => 'no-modify-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		$product->update_meta(
			'wu_limitations',
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'choose_available_templates',
					'limit'   => [],
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
				'title'  => 'Template No Modify Site',
				'domain' => 'tpl-no-modify-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
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

		$instance = $this->get_instance();

		// Should complete without error and without modifying the site.
		$instance->handle_downgrade($membership->get_id());

		// Verify the site still exists and is unchanged.
		$reloaded_site = wu_get_site($site->get_id());

		$this->assertNotFalse($reloaded_site, 'Site should still exist after downgrade handler.');
	}

	/**
	 * Test maybe_force_template_selection_on_cart sets template_id in extra params.
	 */
	public function test_maybe_force_template_selection_on_cart_assign_mode(): void {

		$instance = $this->get_instance();

		$product = wu_create_product(
			[
				'name'  => 'Cart Plan',
				'slug'  => 'cart-plan-tpl-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		$template = wu_create_site(
			[
				'title'  => 'Cart Template Site',
				'domain' => 'tpl-cart-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);

		$this->assertNotWPError($template);

		$template_id = $template->get_id();

		$product->update_meta(
			'wu_limitations',
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'assign_template',
					'limit'   => [
						(string) $template_id => [
							'behavior' => 'pre_selected',
						],
					],
				],
			]
		);

		// Build a minimal cart mock with the product.
		$cart = $this->getMockBuilder(\WP_Ultimo\Checkout\Cart::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_all_products'])
			->getMock();

		$cart->method('get_all_products')->willReturn([$product]);

		$extra  = [];
		$result = $instance->maybe_force_template_selection_on_cart($extra, $cart);

		$this->assertArrayHasKey('template_id', $result, 'Cart extra params should include template_id in assign mode.');
		$this->assertEquals($template_id, $result['template_id'], 'Cart template_id should match the pre-selected template.');
	}
}
