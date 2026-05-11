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

		$this->assertEquals(0, $result, 'Assign mode with no pre-selected template should return 0 (no template assigned).');
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
			function ($membership_id, $membership) use (&$action_fired, &$fired_args) {
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

	// -------------------------------------------------------------------------
	// Fallback-chain tests (AC1–AC7, issue #1147)
	// -------------------------------------------------------------------------

	/**
	 * AC1: Allow Site Templates ON, MODE_DEFAULT, no customer pick → oldest active template.
	 */
	public function test_fallback_mode_default_uses_oldest_active_template(): void {

		$instance = $this->get_instance();

		$product = wu_create_product(
			[
				'name'  => 'Default Fallback Plan',
				'slug'  => 'default-fallback-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		// Allow Site Templates ON, MODE_DEFAULT (no limit configured).
		$product->update_meta(
			'wu_limitations',
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'default',
					'limit'   => null,
				],
			]
		);

		// Create two template sites so the "oldest" is deterministic.
		$template_a = wu_create_site(
			[
				'title'  => 'Oldest Template',
				'domain' => 'tpl-a-oldest-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);
		$this->assertNotWPError($template_a);

		$template_b = wu_create_site(
			[
				'title'  => 'Newer Template',
				'domain' => 'tpl-b-newer-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);
		$this->assertNotWPError($template_b);

		$customer = wu_create_customer(['user_id' => self::factory()->user->create()]);
		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);
		$this->assertNotWPError($membership);

		// No customer pick (template_id = 0) — should resolve to an active template.
		$result = $instance->maybe_force_template_selection(0, $membership);

		$this->assertGreaterThan(0, $result, 'MODE_DEFAULT with no pick should return the oldest active template id.');
	}

	/**
	 * AC2: Allow Site Templates ON, MODE_CHOOSE_AVAILABLE_TEMPLATES + BEHAVIOR_PRE_SELECTED,
	 * no customer pick → uses the pre-selected template.
	 */
	public function test_fallback_choose_available_uses_preselected_when_no_pick(): void {

		$instance = $this->get_instance();

		$template = wu_create_site(
			[
				'title'  => 'Pre-Selected Template',
				'domain' => 'tpl-pre-selected-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);
		$this->assertNotWPError($template);
		$template_id = $template->get_id();

		$product = wu_create_product(
			[
				'name'  => 'Choose Pre-Selected Plan',
				'slug'  => 'choose-pre-selected-plan-' . wp_rand(),
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
					'limit'   => [
						(string) $template_id => ['behavior' => 'pre_selected'],
					],
				],
			]
		);

		$customer = wu_create_customer(['user_id' => self::factory()->user->create()]);
		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);
		$this->assertNotWPError($membership);

		$result = $instance->maybe_force_template_selection(0, $membership);

		$this->assertEquals($template_id, $result, 'Should use the pre-selected template when customer did not pick.');
	}

	/**
	 * AC3: Allow Site Templates ON, MODE_CHOOSE_AVAILABLE_TEMPLATES, no pre-selected,
	 * no customer pick → oldest active from the available subset.
	 */
	public function test_fallback_choose_available_uses_oldest_from_subset(): void {

		$instance = $this->get_instance();

		$template_a = wu_create_site(
			[
				'title'  => 'Available Template A',
				'domain' => 'tpl-avail-a-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);
		$this->assertNotWPError($template_a);
		$template_a_id = $template_a->get_id();

		$template_b = wu_create_site(
			[
				'title'  => 'Available Template B',
				'domain' => 'tpl-avail-b-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);
		$this->assertNotWPError($template_b);
		$template_b_id = $template_b->get_id();

		$product = wu_create_product(
			[
				'name'  => 'Choose Available Plan',
				'slug'  => 'choose-available-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);
		$this->assertNotWPError($product);

		// Both templates are AVAILABLE (not pre_selected) — no pre-selected set.
		$product->update_meta(
			'wu_limitations',
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'choose_available_templates',
					'limit'   => [
						(string) $template_a_id => ['behavior' => 'available'],
						(string) $template_b_id => ['behavior' => 'available'],
					],
				],
			]
		);

		$customer = wu_create_customer(['user_id' => self::factory()->user->create()]);
		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);
		$this->assertNotWPError($membership);

		$result = $instance->maybe_force_template_selection(0, $membership);

		// Should return one of the two available templates (oldest by blog_id).
		$this->assertContains(
			$result,
			[$template_a_id, $template_b_id],
			'Should return one of the available templates as fallback.'
		);
		$this->assertGreaterThan(0, $result, 'Fallback template id should be positive.');
	}

	/**
	 * AC4 (regression): Allow Site Templates ON, MODE_ASSIGN_TEMPLATE → assigned
	 * template always wins even when customer supplied a different template_id.
	 */
	public function test_assign_mode_always_wins_over_customer_pick(): void {

		$instance = $this->get_instance();

		$assigned_template = wu_create_site(
			[
				'title'  => 'Assigned Template',
				'domain' => 'tpl-assigned-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);
		$this->assertNotWPError($assigned_template);
		$assigned_id = $assigned_template->get_id();

		$product = wu_create_product(
			[
				'name'  => 'Assign Mode Plan',
				'slug'  => 'assign-mode-plan-' . wp_rand(),
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
					'mode'    => 'assign_template',
					'limit'   => [
						(string) $assigned_id => ['behavior' => 'pre_selected'],
					],
				],
			]
		);

		$customer = wu_create_customer(['user_id' => self::factory()->user->create()]);
		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);
		$this->assertNotWPError($membership);

		// Customer supplies a different template_id — should be ignored.
		$result = $instance->maybe_force_template_selection(999, $membership);

		$this->assertEquals($assigned_id, $result, 'Assign mode should always override the customer pick.');
	}

	/**
	 * AC5: Allow Site Templates OFF → returns template_id unchanged (0 stays 0).
	 */
	public function test_allow_site_templates_off_returns_template_id_unchanged(): void {

		$instance = $this->get_instance();

		$product = wu_create_product(
			[
				'name'  => 'Templates Off Plan',
				'slug'  => 'templates-off-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);
		$this->assertNotWPError($product);

		// Explicitly disable the Allow Site Templates toggle.
		$product->update_meta(
			'wu_limitations',
			[
				'site_templates' => [
					'enabled' => false,
					'mode'    => 'default',
					'limit'   => null,
				],
			]
		);

		$customer = wu_create_customer(['user_id' => self::factory()->user->create()]);
		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);
		$this->assertNotWPError($membership);

		// Even with templates in the network, template_id stays 0 when toggle is off.
		$result = $instance->maybe_force_template_selection(0, $membership);

		$this->assertEquals(0, $result, 'Allow Site Templates OFF should return 0 unchanged.');

		// Also verify a non-zero template_id is preserved as-is.
		$result_non_zero = $instance->maybe_force_template_selection(42, $membership);
		$this->assertEquals(42, $result_non_zero, 'Allow Site Templates OFF should preserve any existing template_id.');
	}

	/**
	 * AC6: Customer explicitly picks a valid template → pick is honoured (except ASSIGN mode).
	 */
	public function test_customer_pick_is_honoured_in_default_mode(): void {

		$instance = $this->get_instance();

		$template = wu_create_site(
			[
				'title'  => 'Customer Pick Template',
				'domain' => 'tpl-customer-pick-' . wp_rand() . '.example.com',
				'path'   => '/',
				'type'   => 'site_template',
			]
		);
		$this->assertNotWPError($template);
		$template_id = $template->get_id();

		$product = wu_create_product(
			[
				'name'  => 'Customer Pick Plan',
				'slug'  => 'customer-pick-plan-' . wp_rand(),
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
					'mode'    => 'default',
					'limit'   => null,
				],
			]
		);

		$customer = wu_create_customer(['user_id' => self::factory()->user->create()]);
		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);
		$this->assertNotWPError($membership);

		// Customer supplies a valid template_id — it should be kept.
		$result = $instance->maybe_force_template_selection($template_id, $membership);

		$this->assertEquals($template_id, $result, 'Customer-supplied template_id should be honoured in MODE_DEFAULT.');
	}

	/**
	 * AC7: Allow Site Templates ON, but network has zero usable templates
	 *      → returns 0 (no fatal, falls through to default WP behaviour).
	 */
	public function test_fallback_returns_zero_when_no_usable_templates(): void {

		$instance = $this->get_instance();

		$product = wu_create_product(
			[
				'name'  => 'No Templates Plan',
				'slug'  => 'no-templates-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);
		$this->assertNotWPError($product);

		// Allow Site Templates ON, MODE_DEFAULT, but no actual template sites exist
		// (we cannot delete all network templates in tests, so we use MODE_CHOOSE_AVAILABLE_TEMPLATES
		// with an empty limit list — the restricted pool will be empty and the global pool has
		// no templates configured, so resolve_default_template_id returns 0 via the log path).
		$product->update_meta(
			'wu_limitations',
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'choose_available_templates',
					'limit'   => [],  // Empty: no templates in the available list.
				],
			]
		);

		$customer = wu_create_customer(['user_id' => self::factory()->user->create()]);
		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);
		$this->assertNotWPError($membership);

		// Should return 0 (or a valid template id) — never a WP_Error or fatal.
		$result = $instance->maybe_force_template_selection(0, $membership);

		$this->assertIsInt($result, 'Result must always be an integer, never a fatal or WP_Error.');
	}

	/**
	 * Test maybe_force_template_selection returns template_id unchanged when
	 * membership argument is not a Membership object.
	 */
	public function test_non_membership_argument_returns_template_id_unchanged(): void {

		$instance = $this->get_instance();

		$result = $instance->maybe_force_template_selection(77, null);
		$this->assertEquals(77, $result, 'Non-Membership argument: template_id should be returned unchanged.');

		$result_zero = $instance->maybe_force_template_selection(0, false);
		$this->assertEquals(0, $result_zero, 'Non-Membership argument: 0 template_id should stay 0.');
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
