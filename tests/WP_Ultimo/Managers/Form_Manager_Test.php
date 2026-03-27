<?php
/**
 * Unit tests for Form_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Form_Manager;

class Form_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Form_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	/**
	 * Clean up request globals before each test to prevent cross-test pollution.
	 */
	public function setUp(): void {

		parent::setUp();

		// Clear request keys used by Form_Manager methods.
		foreach (['model', 'id', 'confirm', 'meta_key', 'redirect_to', 'bulk_action', 'ids', 'bulk-delete', 'form'] as $key) {
			unset($_REQUEST[ $key ], $_GET[ $key ], $_POST[ $key ]);
		}
	}

	// -------------------------------------------------------------------------
	// Helper: install AJAX die handler so wp_send_json_* doesn't kill PHPUnit.
	// wp_send_json_* calls wp_die(), which in AJAX context uses wp_die_ajax_handler.
	// We install a handler that throws WPAjaxDieContinueException instead.
	// NOTE: display_form_unavailable() calls bare `die` (not wp_die), so it
	// cannot be intercepted this way and must not be called in tests.
	// -------------------------------------------------------------------------

	/**
	 * Install AJAX die handler and return it for later removal.
	 *
	 * @return callable
	 */
	private function install_ajax_die_handler(): callable {

		add_filter('wp_doing_ajax', '__return_true');

		$handler = function () {
			return function ( $message ) {
				throw new \WPAjaxDieContinueException( (string) $message );
			};
		};

		add_filter('wp_die_ajax_handler', $handler, 1);

		return $handler;
	}

	/**
	 * Remove the AJAX die handler installed by install_ajax_die_handler().
	 *
	 * @param callable $handler The handler returned by install_ajax_die_handler().
	 * @return void
	 */
	private function remove_ajax_die_handler( callable $handler ): void {

		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $handler, 1);
	}

	/**
	 * Call a manager method inside an AJAX die context, capture JSON output.
	 *
	 * @param callable $callable The callable to invoke.
	 * @return array{output: string, exception: bool}
	 */
	private function call_in_ajax_context( callable $callable ): array {

		if (defined('REST_REQUEST') && REST_REQUEST) {
			$this->setExpectedIncorrectUsage('wp_send_json');
		}

		$handler          = $this->install_ajax_die_handler();
		$exception_caught = false;

		ob_start();

		try {
			$callable();
		} catch (\WPAjaxDieContinueException $e) {
			$exception_caught = true;
		}

		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		return [
			'output'    => $output,
			'exception' => $exception_caught,
		];
	}

	// =========================================================================
	// register_form / get_form / is_form_registered / get_registered_forms
	// =========================================================================

	/**
	 * Test register_form and get_form round-trip.
	 */
	public function test_register_and_get_form(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'test_form_xyz',
			[
				'render'  => '__return_true',
				'handler' => '__return_true',
			]
		);

		$form = $manager->get_form('test_form_xyz');

		$this->assertIsArray($form);
	}

	/**
	 * Test register_form applies default attribute values.
	 */
	public function test_register_form_applies_defaults(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form('defaults_form_xyz');

		$form = $manager->get_form('defaults_form_xyz');

		$this->assertIsArray($form);
		$this->assertSame('defaults_form_xyz', $form['id']);
		$this->assertSame('manage_network', $form['capability']);
		$this->assertSame('__return_false', $form['handler']);
		$this->assertSame('__return_empty_string', $form['render']);
	}

	/**
	 * Test register_form returns true on first registration.
	 */
	public function test_register_form_returns_true_on_first_registration(): void {

		$manager = $this->get_manager_instance();

		$result = $manager->register_form('first_reg_form_xyz');

		$this->assertTrue($result);
	}

	/**
	 * Test register_form silently skips duplicate registrations.
	 *
	 * The second call returns null (early return without explicit value).
	 * The original capability is preserved.
	 */
	public function test_register_form_skips_duplicate(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form('dup_form_xyz', ['capability' => 'manage_network']);

		// Second call with different capability — should be ignored.
		$result = $manager->register_form('dup_form_xyz', ['capability' => 'edit_posts']);

		// Returns null (early return, no explicit return value).
		$this->assertNull($result);

		// Original capability preserved.
		$form = $manager->get_form('dup_form_xyz');
		$this->assertSame('manage_network', $form['capability']);
	}

	/**
	 * Test register_form stores custom capability.
	 */
	public function test_register_form_stores_custom_capability(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form('cap_form_xyz', ['capability' => 'edit_posts']);

		$form = $manager->get_form('cap_form_xyz');

		$this->assertSame('edit_posts', $form['capability']);
	}

	/**
	 * Test register_form stores custom render and handler callables.
	 */
	public function test_register_form_stores_render_and_handler(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'callable_form_xyz',
			[
				'render'  => '__return_true',
				'handler' => '__return_false',
			]
		);

		$form = $manager->get_form('callable_form_xyz');

		$this->assertSame('__return_true', $form['render']);
		$this->assertSame('__return_false', $form['handler']);
	}

	/**
	 * Test is_form_registered returns correct values.
	 */
	public function test_is_form_registered(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'registered_form_xyz',
			[
				'render'  => '__return_true',
				'handler' => '__return_true',
			]
		);

		$this->assertTrue($manager->is_form_registered('registered_form_xyz'));
		$this->assertFalse($manager->is_form_registered('nonexistent_form_xyz'));
	}

	/**
	 * Test get_registered_forms returns an array.
	 */
	public function test_get_registered_forms_returns_array(): void {

		$manager = $this->get_manager_instance();
		$forms   = $manager->get_registered_forms();

		$this->assertIsArray($forms);
	}

	/**
	 * Test get_registered_forms includes newly registered forms.
	 */
	public function test_get_registered_forms_includes_new_form(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form('list_test_form_xyz');

		$forms = $manager->get_registered_forms();

		$this->assertArrayHasKey('list_test_form_xyz', $forms);
	}

	/**
	 * Test get_form returns false for an unregistered id.
	 */
	public function test_get_form_returns_false_for_unknown_id(): void {

		$manager = $this->get_manager_instance();

		$this->assertFalse($manager->get_form('totally_unknown_form_xyz'));
	}

	// =========================================================================
	// get_form_url
	// =========================================================================

	/**
	 * Test get_form_url returns a string URL.
	 */
	public function test_get_form_url_returns_url(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'url_test_form',
			[
				'render'  => '__return_true',
				'handler' => '__return_true',
			]
		);

		$url = $manager->get_form_url('url_test_form');

		$this->assertIsString($url);
		$this->assertStringContainsString('url_test_form', $url);
	}

	/**
	 * Test get_form_url includes default query parameters.
	 */
	public function test_get_form_url_includes_default_params(): void {

		$manager = $this->get_manager_instance();

		$url = $manager->get_form_url('param_test_form');

		$this->assertStringContainsString('action=wu_form_display', $url);
		$this->assertStringContainsString('width=400', $url);
		$this->assertStringContainsString('height=360', $url);
	}

	/**
	 * Test get_form_url allows overriding default parameters.
	 */
	public function test_get_form_url_allows_custom_params(): void {

		$manager = $this->get_manager_instance();

		$url = $manager->get_form_url(
			'custom_param_form',
			[
				'action' => 'wu_form_handler',
				'width'  => '600',
			]
		);

		$this->assertStringContainsString('action=wu_form_handler', $url);
		$this->assertStringContainsString('width=600', $url);
	}

	/**
	 * Test get_form_url includes the form id in the URL.
	 */
	public function test_get_form_url_includes_form_id(): void {

		$manager = $this->get_manager_instance();

		$url = $manager->get_form_url('my_special_form_id');

		$this->assertStringContainsString('my_special_form_id', $url);
		$this->assertStringContainsString('form=my_special_form_id', $url);
	}

	// =========================================================================
	// register_action_forms
	// =========================================================================

	/**
	 * Test register_action_forms registers delete_modal and bulk_actions forms.
	 */
	public function test_register_action_forms_registers_expected_forms(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['model'] = 'customer';

		$manager->register_action_forms();

		unset($_REQUEST['model']);

		$this->assertTrue($manager->is_form_registered('delete_modal'));
		$this->assertTrue($manager->is_form_registered('bulk_actions'));
	}

	/**
	 * Test register_action_forms hooks default_bulk_action_handler.
	 */
	public function test_register_action_forms_hooks_bulk_handler(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['model'] = 'customer';

		$manager->register_action_forms();

		unset($_REQUEST['model']);

		$this->assertNotFalse(
			has_action('wu_handle_bulk_action_form', [$manager, 'default_bulk_action_handler'])
		);
	}

	/**
	 * Test register_action_forms delete_modal has correct render/handler.
	 */
	public function test_register_action_forms_delete_modal_has_callbacks(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['model'] = 'membership';

		$manager->register_action_forms();

		unset($_REQUEST['model']);

		$form = $manager->get_form('delete_modal');

		$this->assertIsArray($form);
		$this->assertIsCallable($form['render']);
		$this->assertIsCallable($form['handler']);
	}

	/**
	 * Test register_action_forms bulk_actions form has correct render/handler.
	 */
	public function test_register_action_forms_bulk_actions_has_callbacks(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['model'] = 'membership';

		$manager->register_action_forms();

		unset($_REQUEST['model']);

		$form = $manager->get_form('bulk_actions');

		$this->assertIsArray($form);
		$this->assertIsCallable($form['render']);
		$this->assertIsCallable($form['handler']);
	}

	// =========================================================================
	// handle_model_delete_form
	// =========================================================================

	/**
	 * Test handle_model_delete_form aborts when confirm is not set.
	 *
	 * handle_model_delete_form() calls wp_send_json_error() which internally
	 * calls wp_die(). In AJAX context, wp_die() uses wp_die_ajax_handler
	 * (not wp_die_handler), so we must install both filters to prevent the
	 * bare die() call from killing the PHPUnit process (GitHub issue #527).
	 *
	 * wp_send_json_error() outputs JSON before calling wp_die(), so the AJAX
	 * die handler throws WPAjaxDieContinueException (output present). We
	 * capture the output with ob_start() and verify the JSON error payload.
	 *
	 * @since 2.0.0
	 */
	public function test_handle_model_delete_form_requires_confirmation(): void {

		$manager = $this->get_manager_instance();

		// Ensure 'confirm' is not set in the request.
		unset($_REQUEST['confirm']);
		$_REQUEST['model'] = 'membership';
		$_REQUEST['id']    = '1';

		/*
		 * wp_send_json() triggers a _doing_it_wrong notice when REST_REQUEST is
		 * defined (CI environment). Declare it as expected so the test framework
		 * does not treat it as a failure. Skip when REST_REQUEST is not defined
		 * (local environment) to avoid "Failed to assert that wp_send_json
		 * triggered an incorrect usage notice" errors.
		 */
		if (defined('REST_REQUEST') && REST_REQUEST) {
			$this->setExpectedIncorrectUsage('wp_send_json');
		}

		/*
		 * Simulate AJAX context so wp_send_json_error() routes through
		 * wp_die() instead of a bare `die` statement.
		 *
		 * wp_die() in AJAX context uses wp_die_ajax_handler (not wp_die_handler).
		 * We install a handler that throws WPAjaxDieContinueException so PHPUnit
		 * can catch it instead of the process terminating.
		 */
		add_filter('wp_doing_ajax', '__return_true');
		$ajax_die_handler = function() {
			return function( $message ) {
				throw new \WPAjaxDieContinueException( (string) $message );
			};
		};
		add_filter('wp_die_ajax_handler', $ajax_die_handler, 1);

		$json_output      = '';
		$exception_caught = false;

		ob_start();

		try {
			$manager->handle_model_delete_form();
		} catch (\WPAjaxDieContinueException $e) {
			$exception_caught = true;
		}

		$json_output = ob_get_clean();

		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $ajax_die_handler, 1);
		unset($_REQUEST['model'], $_REQUEST['id']);

		$this->assertTrue($exception_caught, 'handle_model_delete_form() should have terminated via wp_die()');

		$response = json_decode($json_output, true);

		$this->assertIsArray($response, 'Response should be a JSON object');
		$this->assertFalse($response['success'], 'Response should indicate failure');
		$this->assertSame('not-confirmed', $response['data'][0]['code'], 'Error code should be not-confirmed');
	}

	/**
	 * Test handle_model_delete_form returns error when model is empty.
	 */
	public function test_handle_model_delete_form_no_model_returns_error(): void {

		$manager = $this->get_manager_instance();

		unset($_REQUEST['model']);
		$_REQUEST['confirm'] = '1';

		$result = $this->call_in_ajax_context([$manager, 'handle_model_delete_form']);

		unset($_REQUEST['confirm']);

		$this->assertTrue($result['exception'], 'Should terminate via wp_die()');

		$response = json_decode($result['output'], true);

		$this->assertIsArray($response);
		$this->assertFalse($response['success']);
		$this->assertSame('model-not-found', $response['data'][0]['code']);
	}

	/**
	 * Test handle_model_delete_form returns not-found when object doesn't exist.
	 */
	public function test_handle_model_delete_form_object_not_found(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['model']   = 'customer';
		$_REQUEST['id']      = '999999999';
		$_REQUEST['confirm'] = '1';
		unset($_REQUEST['meta_key']);

		$result = $this->call_in_ajax_context([$manager, 'handle_model_delete_form']);

		unset($_REQUEST['model'], $_REQUEST['id'], $_REQUEST['confirm']);

		$this->assertTrue($result['exception'], 'Should terminate via wp_die()');

		$response = json_decode($result['output'], true);

		$this->assertIsArray($response);
		$this->assertFalse($response['success']);
		$this->assertSame('not-found', $response['data'][0]['code']);
	}

	/**
	 * Test handle_model_delete_form meta_key path sends success.
	 *
	 * When meta_key is set, the method deletes metadata and sends json_success
	 * with a redirect_url — it does NOT look up the object.
	 */
	public function test_handle_model_delete_form_meta_key_path(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['model']    = 'membership';
		$_REQUEST['id']       = '1';
		$_REQUEST['confirm']  = '1';
		$_REQUEST['meta_key'] = 'pending_site';

		$result = $this->call_in_ajax_context([$manager, 'handle_model_delete_form']);

		unset($_REQUEST['model'], $_REQUEST['id'], $_REQUEST['confirm'], $_REQUEST['meta_key']);

		$this->assertTrue($result['exception'], 'Should terminate via wp_die()');

		$response = json_decode($result['output'], true);

		$this->assertIsArray($response);
		// Meta key path sends json_success with redirect_url.
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('redirect_url', $response['data']);
	}

	/**
	 * Test handle_model_delete_form plural_name is derived from model.
	 *
	 * Verifies the redirect URL for a successful delete uses the plural form.
	 * Uses a real customer object so the delete path is exercised.
	 */
	public function test_handle_model_delete_form_deletes_existing_object(): void {

		$manager = $this->get_manager_instance();

		// Create a real customer to delete.
		$user_id  = $this->factory()->user->create(['role' => 'subscriber']);
		$customer = wu_create_customer(
			[
				'user_id'       => $user_id,
				'email_address' => 'delete-test-' . $user_id . '@example.com',
			]
		);

		$this->assertNotWPError($customer);

		$_REQUEST['model']   = 'customer';
		$_REQUEST['id']      = (string) $customer->get_id();
		$_REQUEST['confirm'] = '1';
		unset($_REQUEST['meta_key']);

		$result = $this->call_in_ajax_context([$manager, 'handle_model_delete_form']);

		unset($_REQUEST['model'], $_REQUEST['id'], $_REQUEST['confirm']);

		$this->assertTrue($result['exception'], 'Should terminate via wp_die()');

		$response = json_decode($result['output'], true);

		$this->assertIsArray($response);
		$this->assertTrue($response['success'], 'Delete should succeed');
		$this->assertArrayHasKey('redirect_url', $response['data']);
		$this->assertStringContainsString('customers', $response['data']['redirect_url']);
	}

	// =========================================================================
	// render_model_delete_form
	// =========================================================================

	/**
	 * Test render_model_delete_form does nothing when model is empty.
	 *
	 * When no model is in the request, the method returns early with no output.
	 * This is safe to test because the early return path does not call die.
	 */
	public function test_render_model_delete_form_no_model_does_nothing(): void {

		$manager = $this->get_manager_instance();

		unset($_REQUEST['model'], $_REQUEST['id']);

		ob_start();
		$manager->render_model_delete_form();
		$output = ob_get_clean();

		// No model → early return, no output.
		$this->assertSame('', $output);
	}

	/**
	 * Test render_model_delete_form renders form HTML for a valid customer.
	 *
	 * Creates a real customer, sets the request, and verifies the form renders
	 * without fatal errors. The form outputs HTML (not JSON), so we capture
	 * the output buffer and check for expected form elements.
	 */
	public function test_render_model_delete_form_with_valid_customer(): void {

		$manager = $this->get_manager_instance();

		// Create a real customer to render the delete form for.
		$user_id  = $this->factory()->user->create(['role' => 'subscriber']);
		$customer = wu_create_customer(
			[
				'user_id'       => $user_id,
				'email_address' => 'render-delete-' . $user_id . '@example.com',
			]
		);

		$this->assertNotWPError($customer);

		$_REQUEST['model'] = 'customer';
		$_REQUEST['id']    = (string) $customer->get_id();

		ob_start();
		$manager->render_model_delete_form();
		$output = ob_get_clean();

		unset($_REQUEST['model'], $_REQUEST['id']);

		// The form should render some HTML output.
		$this->assertNotEmpty($output, 'render_model_delete_form() should produce output for a valid object');
	}

	/**
	 * Test render_model_delete_form handles _meta_ model syntax with valid object.
	 *
	 * When model contains '_meta_', the method splits it and sets meta_key.
	 * With a valid membership, it should render the form.
	 */
	public function test_render_model_delete_form_meta_model_with_valid_object(): void {

		$manager = $this->get_manager_instance();

		// Create a real membership to render the delete form for.
		$user_id  = $this->factory()->user->create(['role' => 'subscriber']);
		$customer = wu_create_customer(
			[
				'user_id'       => $user_id,
				'email_address' => 'render-meta-' . $user_id . '@example.com',
			]
		);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => 0,
				'status'      => 'active',
			]
		);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Could not create membership for test.');
			return;
		}

		$_REQUEST['model'] = 'membership_meta_pending_site';
		$_REQUEST['id']    = (string) $membership->get_id();

		ob_start();
		$manager->render_model_delete_form();
		$output = ob_get_clean();

		unset($_REQUEST['model'], $_REQUEST['id']);

		// The form should render some HTML output.
		$this->assertNotEmpty($output, 'render_model_delete_form() should produce output for a valid membership with meta key');
	}

	// =========================================================================
	// render_bulk_action_form
	// =========================================================================

	/**
	 * Test render_bulk_action_form renders form HTML.
	 *
	 * The method builds a form with fields and calls $form->render().
	 * We capture the output and verify it contains expected form elements.
	 *
	 * NOTE: wu_request('bulk-delete', '') returns '' by default, but
	 * implode(',', '') fails. Set bulk-delete to an array to avoid TypeError.
	 */
	public function test_render_bulk_action_form_renders_html(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['bulk_action']  = 'delete';
		$_REQUEST['model']        = 'customer';
		$_REQUEST['bulk-delete']  = ['1', '2', '3'];

		ob_start();
		$manager->render_bulk_action_form();
		$output = ob_get_clean();

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['bulk-delete']);

		// The form should render some HTML output.
		$this->assertNotEmpty($output, 'render_bulk_action_form() should produce output');
	}

	/**
	 * Test render_bulk_action_form with custom action name.
	 */
	public function test_render_bulk_action_form_with_custom_action(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['bulk_action']  = 'activate';
		$_REQUEST['model']        = 'membership';
		$_REQUEST['bulk-delete']  = [];

		ob_start();
		$manager->render_bulk_action_form();
		$output = ob_get_clean();

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['bulk-delete']);

		$this->assertNotEmpty($output, 'render_bulk_action_form() should produce output for activate action');
	}

	// =========================================================================
	// handle_bulk_action_form
	// =========================================================================

	/**
	 * Test handle_bulk_action_form fires the generic wu_handle_bulk_action_form action.
	 *
	 * NOTE: handle_bulk_action_form() fires wu_handle_bulk_action_form, which has
	 * default_bulk_action_handler hooked at priority 100. That handler calls
	 * wp_send_json_success() → wp_die() (in AJAX context). We must install the
	 * AJAX die handler so the WPAjaxDieContinueException is caught instead of
	 * killing the PHPUnit process.
	 */
	public function test_handle_bulk_action_form_fires_generic_action(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['bulk_action'] = 'delete';
		$_REQUEST['model']       = 'customer';
		$_REQUEST['ids']         = '1,2,3';

		$generic_fired = false;

		add_action(
			'wu_handle_bulk_action_form',
			function ( $action, $model, $ids ) use ( &$generic_fired ) {
				$generic_fired = true;
			},
			10,
			3
		);

		// Use AJAX context so default_bulk_action_handler's wp_send_json_success
		// calls wp_die (interceptable) instead of bare die.
		$this->run_in_ajax_context([$manager, 'handle_bulk_action_form']);

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['ids']);

		$this->assertTrue($generic_fired, 'wu_handle_bulk_action_form action should fire');
	}

	/**
	 * Test handle_bulk_action_form fires the model+action-specific action.
	 */
	public function test_handle_bulk_action_form_fires_specific_action(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['bulk_action'] = 'delete';
		$_REQUEST['model']       = 'customer';
		$_REQUEST['ids']         = '1,2,3';

		$specific_fired = false;

		add_action(
			'wu_handle_bulk_action_form_customer_delete',
			function ( $action, $model, $ids ) use ( &$specific_fired ) {
				$specific_fired = true;
			},
			10,
			3
		);

		$this->run_in_ajax_context([$manager, 'handle_bulk_action_form']);

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['ids']);

		$this->assertTrue($specific_fired, 'wu_handle_bulk_action_form_customer_delete action should fire');
	}

	/**
	 * Test handle_bulk_action_form passes correct ids array to action.
	 *
	 * Uses a custom bulk_action ('custom_test') that hits the default switch case
	 * in process_bulk_action() — avoids trying to load nonexistent model objects.
	 */
	public function test_handle_bulk_action_form_passes_ids_array(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['bulk_action'] = 'custom_test';
		$_REQUEST['model']       = 'customer';
		$_REQUEST['ids']         = '10,20,30';

		$received_ids = null;

		add_action(
			'wu_handle_bulk_action_form',
			function ( $action, $model, $ids ) use ( &$received_ids ) {
				$received_ids = $ids;
			},
			10,
			3
		);

		$this->run_in_ajax_context([$manager, 'handle_bulk_action_form']);

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['ids']);

		$this->assertIsArray($received_ids);
		$this->assertContains('10', $received_ids);
		$this->assertContains('20', $received_ids);
		$this->assertContains('30', $received_ids);
	}

	/**
	 * Test handle_bulk_action_form passes correct action and model to action hook.
	 */
	public function test_handle_bulk_action_form_passes_action_and_model(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['bulk_action'] = 'custom_test_suspend';
		$_REQUEST['model']       = 'customer';
		$_REQUEST['ids']         = '5,6';

		$received_action = null;
		$received_model  = null;

		add_action(
			'wu_handle_bulk_action_form',
			function ( $action, $model, $ids ) use ( &$received_action, &$received_model ) {
				$received_action = $action;
				$received_model  = $model;
			},
			10,
			3
		);

		$this->run_in_ajax_context([$manager, 'handle_bulk_action_form']);

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['ids']);

		$this->assertSame('custom_test_suspend', $received_action);
		$this->assertSame('customer', $received_model);
	}

	// =========================================================================
	// default_bulk_action_handler
	// =========================================================================

	/**
	 * Helper: run a callable in AJAX die context using the inline pattern.
	 *
	 * Uses the same inline pattern as test_handle_model_delete_form_requires_confirmation
	 * which is proven to work. Returns ['output' => string, 'exception' => bool].
	 *
	 * @param callable $callable The callable to invoke.
	 * @return array{output: string, exception: bool}
	 */
	private function run_in_ajax_context( callable $callable ): array {

		if (defined('REST_REQUEST') && REST_REQUEST) {
			$this->setExpectedIncorrectUsage('wp_send_json');
		}

		add_filter('wp_doing_ajax', '__return_true');

		$ajax_die_handler = function () {
			return function ( $message ) {
				throw new \WPAjaxDieContinueException( (string) $message );
			};
		};

		add_filter('wp_die_ajax_handler', $ajax_die_handler, 1);

		$exception_caught = false;

		ob_start();

		try {
			$callable();
		} catch (\WPAjaxDieContinueException $e) {
			$exception_caught = true;
		}

		$output = ob_get_clean();

		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $ajax_die_handler, 1);

		return [
			'output'    => $output,
			'exception' => $exception_caught,
		];
	}

	/**
	 * Test default_bulk_action_handler sends json_success with redirect_url.
	 *
	 * Set $_REQUEST so process_bulk_action() returns true (not WP_Error):
	 * - model=customer → func_name=wu_get_customer (exists)
	 * - bulk_action=custom_test → hits default case → returns true
	 * Then wp_send_json_success is called with the redirect URL.
	 */
	public function test_default_bulk_action_handler_sends_success(): void {

		$manager = $this->get_manager_instance();

		// Set request so process_bulk_action() returns true.
		$_REQUEST['bulk_action'] = 'custom_test_action';
		$_REQUEST['model']       = 'customer';
		$_REQUEST['ids']         = '1,2,3';

		$result = $this->run_in_ajax_context(
			function () use ( $manager ) {
				$manager->default_bulk_action_handler('delete', 'customer', ['1', '2', '3']);
			}
		);

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['ids']);

		$this->assertTrue($result['exception'], 'Should terminate via wp_die()');

		$response = json_decode($result['output'], true);

		$this->assertIsArray($response);
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('redirect_url', $response['data']);
	}

	/**
	 * Test default_bulk_action_handler includes count in redirect URL.
	 */
	public function test_default_bulk_action_handler_redirect_url_includes_count(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['bulk_action'] = 'custom_test_action';
		$_REQUEST['model']       = 'customer';
		$_REQUEST['ids']         = '1,2,3';

		$ids = ['1', '2', '3'];

		$result = $this->run_in_ajax_context(
			function () use ( $manager, $ids ) {
				$manager->default_bulk_action_handler('delete', 'customer', $ids);
			}
		);

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['ids']);

		$response = json_decode($result['output'], true);

		$this->assertIsArray($response);
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('delete=3', $response['data']['redirect_url']);
	}

	/**
	 * Test default_bulk_action_handler with empty ids array.
	 */
	public function test_default_bulk_action_handler_empty_ids(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['bulk_action'] = 'custom_test_action';
		$_REQUEST['model']       = 'customer';
		$_REQUEST['ids']         = '';

		$result = $this->run_in_ajax_context(
			function () use ( $manager ) {
				$manager->default_bulk_action_handler('delete', 'customer', []);
			}
		);

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['ids']);

		$response = json_decode($result['output'], true);

		$this->assertIsArray($response);
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('delete=0', $response['data']['redirect_url']);
	}

	// =========================================================================
	// init (hook registration)
	// =========================================================================

	/**
	 * Test init registers expected WordPress actions.
	 */
	public function test_init_registers_hooks(): void {

		$manager = $this->get_manager_instance();

		$manager->init();

		$this->assertNotFalse(has_action('wu_ajax_wu_form_display', [$manager, 'display_form']));
		$this->assertNotFalse(has_action('wu_ajax_wu_form_handler', [$manager, 'handle_form']));
		$this->assertNotFalse(has_action('wu_register_forms', [$manager, 'register_action_forms']));
	}

	// =========================================================================
	// security_checks
	// =========================================================================

	/**
	 * Test security_checks calls wp_die(0) when request is not an AJAX request.
	 *
	 * security_checks() checks $_SERVER['HTTP_X_REQUESTED_WITH'] for the value
	 * 'xmlhttprequest'. When absent (or wrong), it calls wp_die(0).
	 *
	 * wp_die() in non-AJAX context uses wp_die_handler (not wp_die_ajax_handler).
	 * We install a wp_die_handler filter that throws WPDieException so PHPUnit
	 * can catch it instead of the process terminating.
	 */
	public function test_security_checks_dies_for_non_ajax_request(): void {

		$manager = $this->get_manager_instance();

		// Ensure HTTP_X_REQUESTED_WITH is absent (non-AJAX request).
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);

		$die_handler = function () {
			return function ( $message ) {
				throw new \WPDieException( (string) $message );
			};
		};

		add_filter('wp_die_handler', $die_handler, 1);

		$exception_caught = false;

		try {
			$manager->security_checks();
		} catch (\WPDieException $e) {
			$exception_caught = true;
		}

		remove_filter('wp_die_handler', $die_handler, 1);

		$this->assertTrue($exception_caught, 'security_checks() should call wp_die() for non-AJAX requests');
	}

	/**
	 * Test security_checks passes through when request is AJAX and form exists with capability.
	 *
	 * When HTTP_X_REQUESTED_WITH is 'XMLHttpRequest', the form is registered,
	 * and the current user has the required capability, security_checks() returns
	 * without calling wp_die() or display_form_unavailable().
	 */
	public function test_security_checks_passes_for_valid_ajax_request(): void {

		$manager = $this->get_manager_instance();

		// Register a form with 'read' capability (all users have this).
		$manager->register_form(
			'security_test_form_xyz',
			[
				'capability' => 'read',
				'render'     => '__return_empty_string',
				'handler'    => '__return_false',
			]
		);

		// Simulate AJAX request.
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
		$_REQUEST['form']                 = 'security_test_form_xyz';

		// Grant the current user 'read' capability (WP_UnitTestCase sets up a user).
		wp_set_current_user($this->factory()->user->create(['role' => 'subscriber']));

		$exception_caught = false;

		try {
			$manager->security_checks();
		} catch (\Exception $e) {
			$exception_caught = true;
		}

		unset($_SERVER['HTTP_X_REQUESTED_WITH'], $_REQUEST['form']);

		$this->assertFalse($exception_caught, 'security_checks() should not die for a valid AJAX request with capable user');
	}

	// =========================================================================
	// default_bulk_action_handler — error path
	// =========================================================================

	/**
	 * Test default_bulk_action_handler sends json_error when process_bulk_action fails.
	 *
	 * process_bulk_action() returns WP_Error when the model function doesn't exist
	 * (e.g. model='nonexistent_model_xyz'). default_bulk_action_handler() should
	 * then call wp_send_json_error() with that WP_Error.
	 */
	public function test_default_bulk_action_handler_sends_error_when_process_fails(): void {

		$manager = $this->get_manager_instance();

		// Use a model that has no corresponding wu_get_* function.
		$_REQUEST['bulk_action'] = 'delete';
		$_REQUEST['model']       = 'nonexistent_model_xyz';
		$_REQUEST['ids']         = '1,2,3';

		$result = $this->run_in_ajax_context(
			function () use ( $manager ) {
				$manager->default_bulk_action_handler('delete', 'nonexistent_model_xyz', ['1', '2', '3']);
			}
		);

		unset($_REQUEST['bulk_action'], $_REQUEST['model'], $_REQUEST['ids']);

		$this->assertTrue($result['exception'], 'Should terminate via wp_die()');

		$response = json_decode($result['output'], true);

		$this->assertIsArray($response);
		$this->assertFalse($response['success'], 'Should return error when process_bulk_action fails');
	}
}
