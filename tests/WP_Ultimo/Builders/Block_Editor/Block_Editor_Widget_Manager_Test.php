<?php
/**
 * Unit tests for Block_Editor_Widget_Manager class.
 */

namespace WP_Ultimo\Builders\Block_Editor;

/**
 * Unit tests for Block_Editor_Widget_Manager class.
 */
class Block_Editor_Widget_Manager_Test extends \WP_UnitTestCase {

	/**
	 * Manager instance.
	 *
	 * @var Block_Editor_Widget_Manager
	 */
	protected $manager;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->manager = Block_Editor_Widget_Manager::get_instance();
	}

	/**
	 * Test that register_scripts hook is added only in admin context.
	 */
	public function test_init_registers_scripts_hook_only_in_admin(): void {

		// Remove any existing hooks first.
		remove_all_actions('init');

		// Simulate frontend context — set_current_screen('front') sets is_admin() to false.
		set_current_screen('front');

		$this->manager->init();

		$this->assertFalse(
			has_action('init', [$this->manager, 'register_scripts']),
			'register_scripts should NOT be hooked on init when on the frontend.'
		);
	}

	/**
	 * Test that register_scripts hook is added in admin context.
	 */
	public function test_init_registers_scripts_hook_in_admin(): void {

		// Remove any existing hooks first.
		remove_all_actions('init');

		// Simulate admin context.
		set_current_screen('dashboard');

		$this->manager->init();

		$priority = has_action('init', [$this->manager, 'register_scripts']);

		// has_action returns the priority (int) or false.
		$this->assertNotFalse(
			$priority,
			'register_scripts should be hooked on init when in admin.'
		);
	}

	/**
	 * Test that element_loaded and is_preview filter are always registered.
	 */
	public function test_init_always_registers_element_loaded_and_preview_filter(): void {

		remove_all_actions('wu_element_loaded');
		remove_all_filters('wu_element_is_preview');

		// Even on frontend these should be registered.
		set_current_screen('front');

		$this->manager->init();

		$this->assertNotFalse(
			has_action('wu_element_loaded', [$this->manager, 'handle_element']),
			'handle_element should always be hooked on wu_element_loaded.'
		);

		$this->assertNotFalse(
			has_filter('wu_element_is_preview', [$this->manager, 'is_block_preview']),
			'is_block_preview should always be hooked on wu_element_is_preview.'
		);
	}

	/**
	 * Test is_block_preview returns true in REST edit context.
	 */
	public function test_is_block_preview_returns_true_in_rest_edit_context(): void {

		define('REST_REQUEST', true) || true;

		$_GET['context'] = 'edit';

		$result = $this->manager->is_block_preview(false);

		$this->assertTrue($result, 'Should return true when in REST edit context.');

		unset($_GET['context']);
	}

	/**
	 * Test is_block_preview passes through when not in REST context.
	 */
	public function test_is_block_preview_passes_through_outside_rest(): void {

		$result = $this->manager->is_block_preview(false);

		$this->assertFalse($result, 'Should return false when not in REST edit context.');
	}
}
