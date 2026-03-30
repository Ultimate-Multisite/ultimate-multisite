<?php
/**
 * Unit tests for Tours.
 *
 * @package WP_Ultimo\Tests
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Unit tests for Tours.
 */
class Tours_Test extends WP_UnitTestCase {

	/**
	 * Get the singleton instance.
	 *
	 * @return Tours
	 */
	protected function get_instance(): Tours {

		return Tours::get_instance();
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$instance = $this->get_instance();

		$this->assertInstanceOf(Tours::class, $instance);
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(
			Tours::get_instance(),
			Tours::get_instance()
		);
	}

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks(): void {

		$instance = $this->get_instance();

		$instance->init();

		$this->assertIsInt(has_action('wp_ajax_wu_mark_tour_as_finished', [$instance, 'mark_as_finished']));
		$this->assertIsInt(has_action('admin_enqueue_scripts', [$instance, 'register_scripts']));
		$this->assertIsInt(has_action('in_admin_footer', [$instance, 'enqueue_scripts']));
	}

	/**
	 * Test has_tours returns false when no tours registered.
	 */
	public function test_has_tours_returns_false_when_empty(): void {

		$instance = $this->get_instance();

		// Access protected property via reflection to reset tours.
		$reflection = new \ReflectionClass($instance);
		$prop       = $reflection->getProperty('tours');
		$prop->setAccessible(true);
		$prop->setValue($instance, []);

		$this->assertFalse($instance->has_tours());
	}

	/**
	 * Test has_tours returns true when tours are registered.
	 */
	public function test_has_tours_returns_true_when_tours_exist(): void {

		$instance = $this->get_instance();

		$reflection = new \ReflectionClass($instance);
		$prop       = $reflection->getProperty('tours');
		$prop->setAccessible(true);
		$prop->setValue($instance, ['test-tour' => [['id' => 'step1', 'text' => 'Hello']]]);

		$this->assertTrue($instance->has_tours());

		// Reset.
		$prop->setValue($instance, []);
	}

	/**
	 * Test enqueue_scripts does nothing when no tours registered.
	 */
	public function test_enqueue_scripts_skips_when_no_tours(): void {

		global $wp_scripts;

		$instance = $this->get_instance();

		// Ensure no tours.
		$reflection = new \ReflectionClass($instance);
		$prop       = $reflection->getProperty('tours');
		$prop->setAccessible(true);
		$prop->setValue($instance, []);

		$queue_before = isset($wp_scripts) ? $wp_scripts->queue : [];

		$instance->enqueue_scripts();

		$queue_after = isset($wp_scripts) ? $wp_scripts->queue : [];

		// Queue should not have grown.
		$this->assertSame($queue_before, $queue_after);
	}

	/**
	 * Test enqueue_scripts uses wp_add_inline_script on 'underscore', not wu-admin.
	 *
	 * Regression test for GH#707: wu_tours was localized onto wu-admin which is
	 * not enqueued on the network dashboard, causing a ReferenceError. The fix
	 * uses wp_add_inline_script on 'underscore' (always present in WP admin).
	 */
	public function test_enqueue_scripts_inlines_data_on_underscore_not_wu_admin(): void {

		global $wp_scripts;

		$instance = $this->get_instance();

		// Register 'underscore' if not already registered (test environment may not have it).
		if ( ! wp_script_is('underscore', 'registered')) {
			wp_register_script('underscore', false, [], false, false);
		}

		// Inject a tour so enqueue_scripts() proceeds.
		$reflection = new \ReflectionClass($instance);
		$prop       = $reflection->getProperty('tours');
		$prop->setAccessible(true);
		$prop->setValue($instance, ['test-tour' => [['id' => 'step1', 'text' => 'Hello']]]);

		$instance->enqueue_scripts();

		// 'underscore' must be enqueued.
		$this->assertTrue(wp_script_is('underscore', 'enqueued'), 'underscore should be enqueued');

		// Inline data must be attached to 'underscore', not 'wu-admin'.
		$inline_data = $wp_scripts->get_data('underscore', 'after');
		$this->assertNotEmpty($inline_data, 'Inline script data should be attached to underscore');

		$inline_str = is_array($inline_data) ? implode('', $inline_data) : (string) $inline_data;
		$this->assertStringContainsString('wu_tours', $inline_str, 'wu_tours should be defined in inline script');
		$this->assertStringContainsString('wu_tours_vars', $inline_str, 'wu_tours_vars should be defined in inline script');

		// wu-admin must NOT have wu_tours localized onto it.
		$wu_admin_data = $wp_scripts->get_data('wu-admin', 'data');
		$this->assertStringNotContainsString('wu_tours', (string) $wu_admin_data, 'wu_tours must not be localized onto wu-admin');

		// Reset.
		$prop->setValue($instance, []);
	}
}
