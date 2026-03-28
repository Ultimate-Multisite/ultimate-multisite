<?php
/**
 * Unit tests for Notes_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Notes_Manager;

/**
 * Unit tests for Notes_Manager.
 */
class Notes_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	/**
	 * Test customer used across tests.
	 *
	 * @var \WP_Ultimo\Models\Customer|null
	 */
	private $customer = null;

	/**
	 * Get the manager class name.
	 *
	 * @return string
	 */
	protected function get_manager_class(): string {
		return Notes_Manager::class;
	}

	/**
	 * Get the expected slug.
	 *
	 * @return string|null
	 */
	protected function get_expected_slug(): ?string {
		return 'notes';
	}

	/**
	 * Get the expected model class.
	 *
	 * @return string|null
	 */
	protected function get_expected_model_class(): ?string {
		return '\\WP_Ultimo\\Models\\Notes';
	}

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Load admin functions (wu_render_empty_state) used by the notes template.
		require_once wu_path('inc/functions/admin.php');

		$this->customer = wu_create_customer(
			[
				'username' => 'notes_test_' . wp_rand(),
				'email'    => 'notes_test_' . wp_rand() . '@example.com',
				'password' => 'password123',
			]
		);

		if (is_wp_error($this->customer)) {
			$this->fail('Could not create test customer: ' . $this->customer->get_error_message());
		}
	}

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		if ($this->customer && ! is_wp_error($this->customer)) {
			$this->customer->delete();
		}

		// Clean up $_REQUEST entries set during tests.
		unset(
			$_REQUEST['model'],
			$_REQUEST['object_id'],
			$_REQUEST['content'],
			$_REQUEST['confirm_clear_notes'],
			$_REQUEST['confirm_delete_note'],
			$_REQUEST['note_id']
		);

		parent::tearDown();
	}

	// ========================================================================
	// Helpers
	// ========================================================================

	/**
	 * Install AJAX die handler and return the filter callback for later removal.
	 *
	 * @return callable
	 */
	private function install_ajax_die_handler(): callable {
		add_filter('wp_doing_ajax', '__return_true');

		$handler = function () {
			return function ( $message ) {
				throw new \WPAjaxDieContinueException((string) $message);
			};
		};

		add_filter('wp_die_ajax_handler', $handler, 1);

		return $handler;
	}

	/**
	 * Remove AJAX die handler installed by install_ajax_die_handler().
	 *
	 * @param callable $handler The handler returned by install_ajax_die_handler().
	 */
	private function remove_ajax_die_handler( callable $handler ): void {
		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $handler, 1);
	}

	/**
	 * Capture JSON output from a callable that calls wp_send_json_*.
	 *
	 * @param callable $callable The callable to invoke.
	 * @return array
	 */
	private function capture_json_response( callable $callable ): array {
		$handler   = $this->install_ajax_die_handler();
		$exception = null;

		if (defined('REST_REQUEST') && REST_REQUEST) {
			$this->setExpectedIncorrectUsage('wp_send_json');
		}

		ob_start();

		try {
			$callable();
		} catch (\WPAjaxDieContinueException $e) {
			$exception = $e;
		}

		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		return [
			'output'    => $output,
			'exception' => $exception,
		];
	}

	// ========================================================================
	// init() — hook registration
	// ========================================================================

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks(): void {

		$manager = $this->get_manager_instance();

		$manager->init();

		$this->assertIsInt(has_action('plugins_loaded', [$manager, 'register_forms']));
		$this->assertIsInt(has_filter('wu_membership_options_sections', [$manager, 'add_notes_options_section']));
		$this->assertIsInt(has_filter('wu_payments_options_sections', [$manager, 'add_notes_options_section']));
		$this->assertIsInt(has_filter('wu_customer_options_sections', [$manager, 'add_notes_options_section']));
		$this->assertIsInt(has_filter('wu_site_options_sections', [$manager, 'add_notes_options_section']));
	}

	// ========================================================================
	// register_forms()
	// ========================================================================

	/**
	 * Test register_forms registers expected forms.
	 */
	public function test_register_forms(): void {

		$manager = $this->get_manager_instance();

		$manager->register_forms();

		$form_manager = \WP_Ultimo\Managers\Form_Manager::get_instance();

		$this->assertTrue($form_manager->is_form_registered('add_note'));
		$this->assertTrue($form_manager->is_form_registered('clear_notes'));
		$this->assertTrue($form_manager->is_form_registered('delete_note'));
	}

	/**
	 * Test add_note form has correct capability.
	 */
	public function test_register_forms_add_note_capability(): void {

		$manager = $this->get_manager_instance();
		$manager->register_forms();

		$form_manager = \WP_Ultimo\Managers\Form_Manager::get_instance();
		$form         = $form_manager->get_form('add_note');

		$this->assertIsArray($form);
		$this->assertEquals('edit_notes', $form['capability']);
	}

	/**
	 * Test clear_notes form has correct capability.
	 */
	public function test_register_forms_clear_notes_capability(): void {

		$manager = $this->get_manager_instance();
		$manager->register_forms();

		$form_manager = \WP_Ultimo\Managers\Form_Manager::get_instance();
		$form         = $form_manager->get_form('clear_notes');

		$this->assertIsArray($form);
		$this->assertEquals('delete_notes', $form['capability']);
	}

	/**
	 * Test delete_note form has correct capability.
	 */
	public function test_register_forms_delete_note_capability(): void {

		$manager = $this->get_manager_instance();
		$manager->register_forms();

		$form_manager = \WP_Ultimo\Managers\Form_Manager::get_instance();
		$form         = $form_manager->get_form('delete_note');

		$this->assertIsArray($form);
		$this->assertEquals('delete_notes', $form['capability']);
	}

	// ========================================================================
	// add_notes_options_section()
	// ========================================================================

	/**
	 * Test add_notes_options_section returns sections unchanged without capability.
	 */
	public function test_add_notes_options_section_without_capability(): void {

		$manager = $this->get_manager_instance();

		// Create a subscriber user (no note capabilities)
		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$sections = ['existing' => ['title' => 'Existing']];

		$result = $manager->add_notes_options_section($sections, new \stdClass());

		// Should return sections unchanged
		$this->assertSame($sections, $result);
	}

	/**
	 * Test add_notes_options_section adds notes section when user has read_notes capability.
	 */
	public function test_add_notes_options_section_with_read_notes_capability(): void {

		$manager = $this->get_manager_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$user = wp_get_current_user();
		$user->add_cap('read_notes');

		$sections = [];

		$result = $manager->add_notes_options_section($sections, $this->customer);

		$this->assertArrayHasKey('notes', $result);
		$this->assertEquals('Notes', $result['notes']['title']);

		$user->remove_cap('read_notes');
	}

	/**
	 * Test add_notes_options_section adds notes section when user has edit_notes capability.
	 */
	public function test_add_notes_options_section_with_edit_notes_capability(): void {

		$manager = $this->get_manager_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$user = wp_get_current_user();
		$user->add_cap('edit_notes');

		$sections = ['existing' => ['title' => 'Existing']];

		$result = $manager->add_notes_options_section($sections, $this->customer);

		$this->assertArrayHasKey('notes', $result);
		$this->assertArrayHasKey('existing', $result);

		$user->remove_cap('edit_notes');
	}

	/**
	 * Test add_notes_options_section notes section has correct order.
	 */
	public function test_add_notes_options_section_order(): void {

		$manager = $this->get_manager_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$user = wp_get_current_user();
		$user->add_cap('read_notes');

		$result = $manager->add_notes_options_section([], $this->customer);

		$this->assertEquals(1001, $result['notes']['order']);

		$user->remove_cap('read_notes');
	}

	/**
	 * Test add_notes_options_section includes clear button when user has delete_notes.
	 */
	public function test_add_notes_options_section_includes_clear_button_with_delete_cap(): void {

		$manager = $this->get_manager_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$user = wp_get_current_user();
		$user->add_cap('read_notes');
		$user->add_cap('delete_notes');

		$result = $manager->add_notes_options_section([], $this->customer);

		$this->assertArrayHasKey('notes', $result);
		$buttons_fields = $result['notes']['fields']['buttons']['fields'];
		$this->assertArrayHasKey('button_clear_notes', $buttons_fields);

		$user->remove_cap('read_notes');
		$user->remove_cap('delete_notes');
	}

	/**
	 * Test add_notes_options_section includes add button when user has edit_notes.
	 */
	public function test_add_notes_options_section_includes_add_button_with_edit_cap(): void {

		$manager = $this->get_manager_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$user = wp_get_current_user();
		$user->add_cap('edit_notes');

		$result = $manager->add_notes_options_section([], $this->customer);

		$this->assertArrayHasKey('notes', $result);
		$buttons_fields = $result['notes']['fields']['buttons']['fields'];
		$this->assertArrayHasKey('button_add_note', $buttons_fields);

		$user->remove_cap('edit_notes');
	}

	/**
	 * Test add_notes_options_section does NOT include clear button without delete_notes.
	 */
	public function test_add_notes_options_section_no_clear_button_without_delete_cap(): void {

		$manager = $this->get_manager_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$user = wp_get_current_user();
		$user->add_cap('read_notes');
		// Do NOT grant delete_notes.

		$result = $manager->add_notes_options_section([], $this->customer);

		$this->assertArrayHasKey('notes', $result);
		$buttons_fields = $result['notes']['fields']['buttons']['fields'];
		$this->assertArrayNotHasKey('button_clear_notes', $buttons_fields);

		$user->remove_cap('read_notes');
	}

	/**
	 * Test add_notes_options_section does NOT include add button without edit_notes.
	 */
	public function test_add_notes_options_section_no_add_button_without_edit_cap(): void {

		$manager = $this->get_manager_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$user = wp_get_current_user();
		$user->add_cap('read_notes');
		// Do NOT grant edit_notes.

		$result = $manager->add_notes_options_section([], $this->customer);

		$this->assertArrayHasKey('notes', $result);
		$buttons_fields = $result['notes']['fields']['buttons']['fields'];
		$this->assertArrayNotHasKey('button_add_note', $buttons_fields);

		$user->remove_cap('read_notes');
	}

	// ========================================================================
	// render_add_note_modal()
	// ========================================================================

	/**
	 * Test render_add_note_modal produces output.
	 */
	public function test_render_add_note_modal_produces_output(): void {

		$manager = $this->get_manager_instance();

		ob_start();
		$manager->render_add_note_modal();
		$output = ob_get_clean();

		// The form renders HTML output.
		$this->assertIsString($output);
	}

	/**
	 * Test render_add_note_modal applies wu_notes_options_section_fields filter.
	 */
	public function test_render_add_note_modal_applies_filter(): void {

		$manager      = $this->get_manager_instance();
		$filter_fired = false;

		add_filter(
			'wu_notes_options_section_fields',
			function ( $fields ) use ( &$filter_fired ) {
				$filter_fired = true;
				return $fields;
			}
		);

		ob_start();
		$manager->render_add_note_modal();
		ob_get_clean();

		$this->assertTrue($filter_fired, 'wu_notes_options_section_fields filter should have fired.');
	}

	// ========================================================================
	// render_clear_notes_modal()
	// ========================================================================

	/**
	 * Test render_clear_notes_modal produces output.
	 */
	public function test_render_clear_notes_modal_produces_output(): void {

		$manager = $this->get_manager_instance();

		ob_start();
		$manager->render_clear_notes_modal();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// ========================================================================
	// render_delete_note_modal()
	// ========================================================================

	/**
	 * Test render_delete_note_modal produces output.
	 */
	public function test_render_delete_note_modal_produces_output(): void {

		$manager = $this->get_manager_instance();

		ob_start();
		$manager->render_delete_note_modal();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// ========================================================================
	// handle_add_note_modal()
	// ========================================================================

	/**
	 * Test handle_add_note_modal succeeds with valid customer and content.
	 */
	public function test_handle_add_note_modal_success(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['model']     = 'customer';
		$_REQUEST['object_id'] = $this->customer->get_id();
		$_REQUEST['content']   = 'Test note content';

		$result = $this->capture_json_response([$manager, 'handle_add_note_modal']);

		$this->assertNotNull($result['exception'], 'handle_add_note_modal should call wp_die via wp_send_json_success.');

		$response = json_decode($result['output'], true);
		$this->assertIsArray($response);
		$this->assertTrue($response['success'], 'Response should be success.');
		$this->assertArrayHasKey('redirect_url', $response['data']);
	}

	/**
	 * Test handle_add_note_modal redirect URL contains model and object id.
	 */
	public function test_handle_add_note_modal_redirect_url_contains_model(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['model']     = 'customer';
		$_REQUEST['object_id'] = $this->customer->get_id();
		$_REQUEST['content']   = 'Another test note';

		$result = $this->capture_json_response([$manager, 'handle_add_note_modal']);

		$response = json_decode($result['output'], true);

		$this->assertStringContainsString('customer', $response['data']['redirect_url']);
	}

	// ========================================================================
	// handle_clear_notes_modal()
	// ========================================================================

	/**
	 * Test handle_clear_notes_modal returns error when not confirmed.
	 */
	public function test_handle_clear_notes_modal_requires_confirmation(): void {

		$manager = $this->get_manager_instance();

		unset($_REQUEST['confirm_clear_notes']);

		$result = $this->capture_json_response([$manager, 'handle_clear_notes_modal']);

		$this->assertNotNull($result['exception'], 'handle_clear_notes_modal should call wp_die.');

		$response = json_decode($result['output'], true);
		$this->assertIsArray($response);
		$this->assertFalse($response['success']);
		$this->assertSame('not-confirmed', $response['data'][0]['code']);
	}

	/**
	 * Test handle_clear_notes_modal returns early when object not found.
	 */
	public function test_handle_clear_notes_modal_returns_early_when_object_not_found(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['confirm_clear_notes'] = '1';
		$_REQUEST['model']               = 'customer';
		$_REQUEST['object_id']           = 999999;

		// Should return early (no wp_die) because object is not found.
		ob_start();
		$returned = $manager->handle_clear_notes_modal();
		ob_get_clean();

		$this->assertNull($returned);
	}

	/**
	 * Test handle_clear_notes_modal succeeds with valid customer.
	 */
	public function test_handle_clear_notes_modal_success(): void {

		$manager = $this->get_manager_instance();

		// Add a note first.
		$this->customer->add_note(
			[
				'text'      => 'Note to clear',
				'author_id' => 1,
				'note_id'   => uniqid(),
			]
		);

		$_REQUEST['confirm_clear_notes'] = '1';
		$_REQUEST['model']               = 'customer';
		$_REQUEST['object_id']           = $this->customer->get_id();

		$result = $this->capture_json_response([$manager, 'handle_clear_notes_modal']);

		$this->assertNotNull($result['exception'], 'handle_clear_notes_modal should call wp_die via wp_send_json_success.');

		$response = json_decode($result['output'], true);
		$this->assertIsArray($response);
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('redirect_url', $response['data']);
	}

	/**
	 * Test handle_clear_notes_modal redirect URL contains model.
	 */
	public function test_handle_clear_notes_modal_redirect_url_contains_model(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['confirm_clear_notes'] = '1';
		$_REQUEST['model']               = 'customer';
		$_REQUEST['object_id']           = $this->customer->get_id();

		$result = $this->capture_json_response([$manager, 'handle_clear_notes_modal']);

		$response = json_decode($result['output'], true);

		$this->assertStringContainsString('customer', $response['data']['redirect_url']);
	}

	// ========================================================================
	// handle_delete_note_modal()
	// ========================================================================

	/**
	 * Test handle_delete_note_modal returns error when not confirmed.
	 */
	public function test_handle_delete_note_modal_requires_confirmation(): void {

		$manager = $this->get_manager_instance();

		unset($_REQUEST['confirm_delete_note']);

		$result = $this->capture_json_response([$manager, 'handle_delete_note_modal']);

		$this->assertNotNull($result['exception'], 'handle_delete_note_modal should call wp_die.');

		$response = json_decode($result['output'], true);
		$this->assertIsArray($response);
		$this->assertFalse($response['success']);
		$this->assertSame('not-confirmed', $response['data'][0]['code']);
	}

	/**
	 * Test handle_delete_note_modal returns early when object not found.
	 */
	public function test_handle_delete_note_modal_returns_early_when_object_not_found(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['confirm_delete_note'] = '1';
		$_REQUEST['model']               = 'customer';
		$_REQUEST['object_id']           = 999999;
		$_REQUEST['note_id']             = 'nonexistent';

		ob_start();
		$returned = $manager->handle_delete_note_modal();
		ob_get_clean();

		$this->assertNull($returned);
	}

	/**
	 * Test handle_delete_note_modal returns error when note not found.
	 */
	public function test_handle_delete_note_modal_note_not_found(): void {

		$manager = $this->get_manager_instance();

		$_REQUEST['confirm_delete_note'] = '1';
		$_REQUEST['model']               = 'customer';
		$_REQUEST['object_id']           = $this->customer->get_id();
		$_REQUEST['note_id']             = 'nonexistent_note_id_xyz';

		$result = $this->capture_json_response([$manager, 'handle_delete_note_modal']);

		$this->assertNotNull($result['exception'], 'handle_delete_note_modal should call wp_die.');

		$response = json_decode($result['output'], true);
		$this->assertIsArray($response);
		$this->assertFalse($response['success']);
		$this->assertSame('not-found', $response['data'][0]['code']);
	}

	/**
	 * Test handle_delete_note_modal succeeds when note exists.
	 */
	public function test_handle_delete_note_modal_success(): void {

		$manager = $this->get_manager_instance();

		$note_id = uniqid('note_', true);

		$this->customer->add_note(
			[
				'text'      => 'Note to delete',
				'author_id' => 1,
				'note_id'   => $note_id,
			]
		);

		// Reload customer to get fresh notes cache.
		$fresh_customer = wu_get_customer($this->customer->get_id());

		$_REQUEST['confirm_delete_note'] = '1';
		$_REQUEST['model']               = 'customer';
		$_REQUEST['object_id']           = $fresh_customer->get_id();
		$_REQUEST['note_id']             = $note_id;

		$result = $this->capture_json_response([$manager, 'handle_delete_note_modal']);

		$this->assertNotNull($result['exception'], 'handle_delete_note_modal should call wp_die.');

		$response = json_decode($result['output'], true);
		$this->assertIsArray($response);
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('redirect_url', $response['data']);
	}

	/**
	 * Test handle_delete_note_modal redirect URL contains model.
	 */
	public function test_handle_delete_note_modal_redirect_url_contains_model(): void {

		$manager = $this->get_manager_instance();

		$note_id = uniqid('note_', true);

		$this->customer->add_note(
			[
				'text'      => 'Note for redirect test',
				'author_id' => 1,
				'note_id'   => $note_id,
			]
		);

		$fresh_customer = wu_get_customer($this->customer->get_id());

		$_REQUEST['confirm_delete_note'] = '1';
		$_REQUEST['model']               = 'customer';
		$_REQUEST['object_id']           = $fresh_customer->get_id();
		$_REQUEST['note_id']             = $note_id;

		$result = $this->capture_json_response([$manager, 'handle_delete_note_modal']);

		$response = json_decode($result['output'], true);

		$this->assertStringContainsString('customer', $response['data']['redirect_url']);
	}
}
