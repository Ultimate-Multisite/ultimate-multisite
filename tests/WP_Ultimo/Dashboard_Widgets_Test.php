<?php
/**
 * Unit tests for Dashboard_Widgets.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

/**
 * Unit tests for Dashboard_Widgets.
 */
class Dashboard_Widgets_Test extends \WP_UnitTestCase {

	/**
	 * Get the singleton instance.
	 *
	 * @return Dashboard_Widgets
	 */
	protected function get_instance(): Dashboard_Widgets {

		return Dashboard_Widgets::get_instance();
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$instance = $this->get_instance();

		$this->assertInstanceOf(Dashboard_Widgets::class, $instance);
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(
			Dashboard_Widgets::get_instance(),
			Dashboard_Widgets::get_instance()
		);
	}

	/**
	 * Test default screen_id property.
	 */
	public function test_default_screen_id(): void {

		$instance = $this->get_instance();

		$this->assertSame('dashboard-network', $instance->screen_id);
	}

	/**
	 * Test core_metaboxes is an array.
	 */
	public function test_core_metaboxes_is_array(): void {

		$instance = $this->get_instance();

		$this->assertIsArray($instance->core_metaboxes);
	}

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks(): void {

		$instance = $this->get_instance();

		$instance->init();

		$this->assertIsInt(has_action('admin_enqueue_scripts', [$instance, 'enqueue_scripts']));
		$this->assertIsInt(has_action('wp_network_dashboard_setup', [$instance, 'register_network_widgets']));
		$this->assertIsInt(has_action('wp_dashboard_setup', [$instance, 'register_widgets']));
		$this->assertIsInt(has_action('wp_ajax_wu_fetch_rss', [$instance, 'process_ajax_fetch_rss']));
		$this->assertIsInt(has_action('wp_ajax_wu_fetch_activity', [$instance, 'process_ajax_fetch_events']));
		$this->assertIsInt(has_action('wp_ajax_wu_generate_csv', [$instance, 'handle_table_csv']));
	}

	/**
	 * Test enqueue_scripts does nothing when not on index.php.
	 */
	public function test_enqueue_scripts_skips_non_index_page(): void {

		global $pagenow, $wp_scripts;

		$original        = $pagenow;
		$original_queue  = isset( $wp_scripts ) ? $wp_scripts->queue : [];
		$pagenow         = 'options.php';

		// Reset the script queue so that wu-vue dependents enqueued by prior
		// tests do not cause wp_script_is() to return true via recurse_deps().
		// wp_script_is('wu-vue', 'enqueued') returns true if wu-vue itself OR
		// any script that depends on it is in the queue.
		if ( isset( $wp_scripts ) ) {
			$wp_scripts->queue = [];
			$wp_scripts->done  = [];
		}

		$instance = $this->get_instance();
		$instance->enqueue_scripts();

		// Should not enqueue scripts on non-index pages
		$this->assertFalse(wp_script_is('wu-vue', 'enqueued'));

		// Restore original queue and pagenow
		if ( isset( $wp_scripts ) ) {
			$wp_scripts->queue = $original_queue;
		}
		$pagenow = $original;
	}

	/**
	 * Test enqueue_scripts enqueues activity-stream on the network admin index.php.
	 *
	 * Since PR #785, enqueue_scripts() guards with is_network_admin() so the
	 * activity-stream assets are only loaded on the network dashboard — not
	 * the per-site dashboard. The test must simulate the network admin context.
	 */
	public function test_enqueue_scripts_enqueues_activity_stream_on_index(): void {

		global $pagenow, $wp_scripts;

		$original           = $pagenow;
		$original_queue     = isset($wp_scripts) ? $wp_scripts->queue : [];
		$original_done      = isset($wp_scripts) ? $wp_scripts->done : [];
		$original_screen    = function_exists('get_current_screen') ? get_current_screen() : null;
		$original_screen_id = $original_screen ? $original_screen->id : null;
		$pagenow            = 'index.php';

		try {
			if (isset($wp_scripts)) {
				$wp_scripts->queue = [];
				$wp_scripts->done  = [];
			}

			// Simulate the network admin so is_network_admin() returns true.
			set_current_screen('dashboard-network');

			// Ensure wu-functions is registered so the dependency chain resolves.
			\WP_Ultimo\Scripts::get_instance()->register_default_scripts();

			$instance = $this->get_instance();
			$instance->enqueue_scripts();

			$this->assertTrue(
				wp_script_is('wu-activity-stream', 'enqueued'),
				'wu-activity-stream should be enqueued on the network admin index.php'
			);

			// Verify wu-functions and moment are declared dependencies.
			$script = $wp_scripts->registered['wu-activity-stream'] ?? null;
			$this->assertNotNull($script, 'wu-activity-stream should be registered');
			$this->assertContains('wu-functions', $script->deps);
			$this->assertContains('moment', $script->deps);

		} finally {
			if (isset($wp_scripts)) {
				$wp_scripts->queue = $original_queue;
				$wp_scripts->done  = $original_done;
			}
			if (null !== $original_screen_id) {
				set_current_screen($original_screen_id);
			} else {
				unset($GLOBALS['current_screen']);
			}
			$pagenow = $original;
		}
	}

	/**
	 * Test enqueue_scripts does NOT enqueue activity-stream on per-site dashboard.
	 *
	 * PR #785 added an is_network_admin() guard to prevent the Vue
	 * "Cannot find element: #activity-stream-content" console error
	 * on the per-site /wp-admin/index.php.
	 */
	public function test_enqueue_scripts_skips_activity_stream_on_per_site_dashboard(): void {

		global $pagenow, $wp_scripts;

		$original           = $pagenow;
		$original_queue     = isset($wp_scripts) ? $wp_scripts->queue : [];
		$original_done      = isset($wp_scripts) ? $wp_scripts->done : [];
		$original_screen    = function_exists('get_current_screen') ? get_current_screen() : null;
		$original_screen_id = $original_screen ? $original_screen->id : null;
		$pagenow            = 'index.php';

		try {
			if (isset($wp_scripts)) {
				$wp_scripts->queue = [];
				$wp_scripts->done  = [];
			}

			// Simulate the per-site dashboard (not network admin).
			set_current_screen('dashboard');

			$instance = $this->get_instance();
			$instance->enqueue_scripts();

			$this->assertFalse(
				wp_script_is('wu-activity-stream', 'enqueued'),
				'wu-activity-stream should NOT be enqueued on the per-site dashboard'
			);

		} finally {
			if (isset($wp_scripts)) {
				$wp_scripts->queue = $original_queue;
				$wp_scripts->done  = $original_done;
			}
			if (null !== $original_screen_id) {
				set_current_screen($original_screen_id);
			} else {
				unset($GLOBALS['current_screen']);
			}
			$pagenow = $original;
		}
	}

	/**
	 * Test get_registered_dashboard_widgets returns array.
	 *
	 * Note: The method uses ob_start/ob_clean internally which
	 * destroys PHPUnit's output buffer. We add two extra levels
	 * so the ob_clean inside the method only destroys our sacrificial buffer.
	 */
	public function test_get_registered_dashboard_widgets_returns_array(): void {

		ob_start(); // sacrificial buffer for ob_clean inside method
		ob_start(); // extra safety
		$result = Dashboard_Widgets::get_registered_dashboard_widgets();
		// Clean up any remaining buffers we added
		while (ob_get_level() > 1) {
			ob_end_clean();
		}

		$this->assertIsArray($result);
	}

	/**
	 * Test get_registered_dashboard_widgets contains default entries.
	 */
	public function test_get_registered_dashboard_widgets_has_defaults(): void {

		ob_start();
		ob_start();
		$result = Dashboard_Widgets::get_registered_dashboard_widgets();
		while (ob_get_level() > 1) {
			ob_end_clean();
		}

		// Should contain at least the default WordPress dashboard widgets
		$this->assertArrayHasKey('normal:core:dashboard_right_now', $result);
	}

	/**
	 * Test output_widget_activity_stream renders without error.
	 */
	public function test_output_widget_activity_stream_renders(): void {

		$instance = $this->get_instance();

		ob_start();
		$instance->output_widget_activity_stream();
		$output = ob_get_clean();

		// Should produce some output (template rendering)
		$this->assertIsString($output);
	}

	/**
	 * Test output_widget_summary renders without error.
	 */
	public function test_output_widget_summary_renders(): void {

		$instance = $this->get_instance();

		ob_start();
		$instance->output_widget_summary();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	/**
	 * Test output_widget_first_steps renders without error.
	 */
	public function test_output_widget_first_steps_renders(): void {

		$instance = $this->get_instance();

		ob_start();
		$instance->output_widget_first_steps();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}
}
