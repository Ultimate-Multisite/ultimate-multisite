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
	private function build_checkout_variables(string $step_name = 'account'): array {

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
							[
								'id'   => 'account_submit',
								'name' => 'Next Step',
								'type' => 'submit_button',
							],
						],
					],
					// Step 2: template data only (must NOT re-demand account data).
					[
						'id'     => 'site',
						'name'   => 'Site',
						'logged' => 'always',
						'fields' => [
							[
								'id'   => 'site_title',
								'name' => 'Site Title',
								'type' => 'site_title',
							],
							[
								'id'   => 'site_url',
								'name' => 'Site URL',
								'type' => 'site_url',
							],
							[
								'id'   => 'template_selection',
								'name' => 'Templates',
								'type' => 'template_selection',
							],
							[
								'id'   => 'site_submit',
								'name' => 'Next Step',
								'type' => 'submit_button',
							],
						],
					],
					// Step 3: payment data only (must NOT leak into step 1).
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
							[
								'id'   => 'order_summary',
								'name' => 'Order Summary',
								'type' => 'order_summary',
							],
							[
								'id'   => 'submit',
								'name' => 'Submit',
								'type' => 'submit_button',
							],
						],
					],
				],
			]
		);

		$this->assertNotInstanceOf(
			\WP_Error::class,
			$form,
			'Failed to seed the checkout form fixture: ' . (is_wp_error($form) ? $form->get_error_message() : '')
		);

		$checkout = \WP_Ultimo\Checkout\Checkout::get_instance();

		// Inject the seeded form into the real checkout singleton (public prop).
		$checkout->checkout_form = $form;
		$checkout->steps         = $form->get_steps_to_show();
		$checkout->step_name     = $step_name;

		$vars = $checkout->get_checkout_variables();

		$this->assertArrayHasKey(
			'step_fields',
			$vars,
			'get_checkout_variables() must expose step_fields so the Vue validator can scope validation per step. If this key is gone, Step 1 demands every field and registration is blocked.'
		);

		return $vars;
	}

	/**
	 * Builds and returns the step_fields checkout variable.
	 *
	 * @return array
	 */
	private function build_step_fields_map(): array {

		$vars = $this->build_checkout_variables();

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
		$this->assertArrayHasKey('site', $step_fields, 'The later site/template step must be present in the step_fields map.');
		$this->assertArrayHasKey('payment', $step_fields, 'The later payment step must be present in the step_fields map.');

		$account_fields = $step_fields['account'];
		$site_fields    = $step_fields['site'];
		$payment_fields = $step_fields['payment'];

		// Step 1 owns its own fields.
		$this->assertContains('username', $account_fields, 'Step 1 should validate its own username field.');
		$this->assertContains('email_address', $account_fields, 'Step 1 should validate its own email field.');

		// The core of the guard: later-step-only fields must NOT be demanded on Step 1.
		$this->assertNotContains('password', $account_fields, 'Step 1 must NOT require the password field that lives on the payment step.');
		$this->assertNotContains('payment', $account_fields, 'Step 1 must NOT require the payment/gateway field that lives on the payment step.');
		$this->assertNotContains('site_title', $account_fields, 'Step 1 must NOT require the site title field that lives on the site step.');
		$this->assertNotContains('template_id', $account_fields, 'Step 1 must NOT require the template field that lives on the site step.');

		// The issue #1332 guard: after account fields are submitted, the template
		// step validates its own fields and does not re-demand guest credentials.
		$this->assertContains('site_title', $site_fields, 'The template step should validate its own site title field.');
		$this->assertContains('site_url', $site_fields, 'The template step should validate its own site URL field.');
		$this->assertContains('template_id', $site_fields, 'The template_selection field must expose the template_id validation rule key used by JS.');
		$this->assertNotContains('email_address', $site_fields, 'The template step must NOT revalidate the prior email field for guest users.');
		$this->assertNotContains('username', $site_fields, 'The template step must NOT revalidate the prior username field for guest users.');
		$this->assertNotContains('password', $site_fields, 'The template step must NOT revalidate the prior password field for guest users.');

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

		// All seeded validation targets are represented somewhere.
		foreach (['username', 'email_address', 'site_title', 'site_url', 'template_id', 'password', 'payment'] as $expected) {
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

	/**
	 * Guards the loading-copy decision used by the multi-step Vue form.
	 */
	public function test_checkout_variables_expose_step_specific_loading_copy(): void {

		$account_vars = $this->build_checkout_variables('account');
		$payment_vars = $this->build_checkout_variables('payment');

		$this->assertArrayHasKey('recording_responses', $account_vars['i18n']);
		$this->assertSame('Recording Your Responses...', $account_vars['i18n']['recording_responses']);
		$this->assertFalse($account_vars['is_last_step'], 'The first step must not show the provisioning copy.');
		$this->assertTrue($payment_vars['is_last_step'], 'The final step should keep the provisioning copy.');
	}
}
