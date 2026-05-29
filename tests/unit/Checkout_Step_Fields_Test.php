<?php
/**
 * Guards the per-step field partitioning consumed by the multi-step Vue
 * registration form.
 *
 * @package Wp_Multisite_Waas
 */

use PHPUnit\Framework\TestCase;

final class Checkout_Step_Fields_Test extends TestCase {

	/**
	 * Builds a real Checkout_Form with two steps (an account step and a later
	 * payment step), runs the REAL checkout variable builder, and returns the
	 * resulting step_fields map (step_id => [field_ids]) that the JS validator
	 * consumes.
	 *
	 * @return array
	 */
	private function build_step_fields_map(): array {

		$form = wu_create_checkout_form(
			[
				'name'     => 'Step Fields Guard Form',
				'slug'     => 'step-fields-guard-form-' . uniqid(),
				'active'   => true,
				'settings' => [
					// Step 1: account data only (NO payment).
					[
						'id'     => 'account',
						'name'   => 'Account',
						'logged' => 'always',
						'fields' => [
							[
								'id'   => 'username',
								'name' => 'Username',
								'type' => 'username',
							],
							[
								'id'   => 'email_address',
								'name' => 'E-mail Address',
								'type' => 'email',
							],
						],
					],
					// Step 2: payment data only (must NOT leak into step 1).
					[
						'id'     => 'payment',
						'name'   => 'Payment',
						'logged' => 'always',
						'fields' => [
							[
								'id'   => 'password',
								'name' => 'Password',
								'type' => 'password',
							],
							[
								'id'   => 'payment',
								'name' => 'Payment',
								'type' => 'payment',
							],
						],
					],
				],
			]
		);

		$this->assertNotInstanceOf(
			\WP_Error::class,
			$form,
			'Failed to seed the checkout form fixture: ' . ( is_wp_error($form) ? $form->get_error_message() : '' )
		);

		$checkout = new \WP_Ultimo\Checkout\Checkout();

		// Inject the seeded form into the real checkout instance (public prop).
		$checkout->checkout_form = $form;

		$vars = $checkout->get_checkout_variables();

		$this->assertArrayHasKey(
			'step_fields',
			$vars,
			'get_checkout_variables() must expose step_fields so the Vue validator can scope validation per step. If this key is gone, Step 1 demands every field and registration is blocked.'
		);

		return $vars['step_fields'];
	}

	/**
	 * Guards multi-step Vue registration — Step 1 must validate only its own
	 * fields, else registration is blocked on Step 1 (real cases: Lis/Eva).
	 * Replaces a grep-only check.
	 *
	 * Executes the real Checkout::get_checkout_variables() against a seeded
	 * 2-step form and asserts the fields are genuinely partitioned by step:
	 * Step 1 (account) carries its own fields and does NOT contain the
	 * later-step-only payment/password fields.
	 */
	public function test_step_one_does_not_demand_later_step_fields(): void {

		$step_fields = $this->build_step_fields_map();

		$this->assertArrayHasKey('account', $step_fields, 'Step 1 (account) must be present in the step_fields map.');
		$this->assertArrayHasKey('payment', $step_fields, 'The later payment step must be present in the step_fields map.');

		$account_fields = $step_fields['account'];
		$payment_fields = $step_fields['payment'];

		// Step 1 owns its own fields.
		$this->assertContains('username', $account_fields, 'Step 1 should validate its own username field.');
		$this->assertContains('email_address', $account_fields, 'Step 1 should validate its own email field.');

		// The core of the guard: later-step-only fields must NOT be demanded on Step 1.
		$this->assertNotContains('password', $account_fields, 'Step 1 must NOT require the password field that lives on the payment step.');
		$this->assertNotContains('payment', $account_fields, 'Step 1 must NOT require the payment/gateway field that lives on the payment step.');

		// And the later step really does carry the rest (proves a real partition,
		// not everything collapsed into step 1).
		$this->assertContains('password', $payment_fields, 'The payment step should carry the password field.');
		$this->assertContains('payment', $payment_fields, 'The payment step should carry the payment field.');
	}

	/**
	 * Guards multi-step Vue registration — Step 1 must validate only its own
	 * fields, else registration is blocked on Step 1 (real cases: Lis/Eva).
	 * Replaces a grep-only check.
	 *
	 * Belt-and-suspenders: the full set of fields must be distributed across
	 * the steps (no step holds every field), proving per-step grouping is real
	 * rather than every field being duplicated into a single step.
	 */
	public function test_fields_are_partitioned_across_steps_not_collapsed(): void {

		$step_fields = $this->build_step_fields_map();

		$all_field_ids = [];

		foreach ($step_fields as $field_ids) {
			$all_field_ids = array_merge($all_field_ids, $field_ids);
		}

		$all_field_ids = array_values(array_unique($all_field_ids));

		// All four seeded fields are represented somewhere.
		foreach (['username', 'email_address', 'password', 'payment'] as $expected) {
			$this->assertContains($expected, $all_field_ids, "Field '{$expected}' must appear in some step.");
		}

		// No single step contains the full field set (would mean no partition).
		foreach ($step_fields as $step_id => $field_ids) {
			$this->assertLessThan(
				count($all_field_ids),
				count($field_ids),
				"Step '{$step_id}' contains every field — fields are not partitioned per step, so Step 1 would demand all of them."
			);
		}
	}
}
