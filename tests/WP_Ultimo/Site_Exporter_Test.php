<?php
/**
 * Tests for Site_Exporter cron scheduling.
 *
 * Regression tests for GH#1009 — Import cron schedule circular dependency.
 * The circular dependency was caused by maybe_add_schedule() returning early
 * when no pending imports existed, preventing wp_schedule_event() from
 * registering the wu_import_site event with a valid interval.
 *
 * @package WP_Ultimo\Site_Exporter
 * @subpackage Tests
 * @since 2.5.0
 */

namespace WP_Ultimo\Site_Exporter;

use WP_UnitTestCase;

/**
 * Test class for Site_Exporter cron scheduling.
 */
class Site_Exporter_Test extends WP_UnitTestCase {

	/**
	 * @var Site_Exporter
	 */
	private Site_Exporter $exporter;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->exporter = Site_Exporter::get_instance();
	}

	/**
	 * Tear down after each test.
	 * Unschedule any test-created cron events to avoid bleed-through.
	 */
	public function tear_down(): void {

		$timestamp = wp_next_scheduled('wu_import_site');

		if ($timestamp) {
			wp_unschedule_event($timestamp, 'wu_import_site');
		}

		parent::tear_down();
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf(Site_Exporter::class, $this->exporter);
	}

	/**
	 * Test singleton returns same instance on repeated calls.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(Site_Exporter::get_instance(), Site_Exporter::get_instance());
	}

	/**
	 * Regression test for GH#1009 — circular dependency.
	 *
	 * maybe_add_schedule() MUST register wu_site_every_minute regardless of
	 * whether there are pending imports. Previously it returned early when no
	 * imports were pending, causing wp_schedule_event() to fail silently because
	 * the custom interval was never registered.
	 */
	public function test_maybe_add_schedule_always_registers_interval(): void {

		// Pass empty schedules — simulates zero pending imports state.
		$schedules = $this->exporter->maybe_add_schedule([]);

		$this->assertArrayHasKey(
			'wu_site_every_minute',
			$schedules,
			'wu_site_every_minute interval must always be registered regardless of pending imports'
		);

		$this->assertArrayHasKey('interval', $schedules['wu_site_every_minute']);
		$this->assertArrayHasKey('display', $schedules['wu_site_every_minute']);
		$this->assertSame(60, $schedules['wu_site_every_minute']['interval']);
	}

	/**
	 * Test maybe_add_schedule preserves existing schedules.
	 * The filter must return the merged schedule array, not replace it.
	 */
	public function test_maybe_add_schedule_preserves_existing_schedules(): void {

		$existing = [
			'hourly' => [
				'interval' => 3600,
				'display'  => 'Once Hourly',
			],
		];

		$schedules = $this->exporter->maybe_add_schedule($existing);

		$this->assertArrayHasKey('hourly', $schedules, 'Existing schedules must be preserved');
		$this->assertArrayHasKey('wu_site_every_minute', $schedules, 'Plugin schedule must be added');
	}

	/**
	 * Test that maybe_add_schedule registers wu_site_every_minute via the
	 * cron_schedules filter hook — so wp_schedule_event() can use it.
	 */
	public function test_cron_schedules_filter_includes_wu_site_every_minute(): void {

		$schedules = wp_get_schedules();

		$this->assertArrayHasKey(
			'wu_site_every_minute',
			$schedules,
			'wu_site_every_minute must appear in wp_get_schedules() result'
		);
	}

	/**
	 * Test that maybe_run_imports schedules the wu_import_site event when
	 * it is not already scheduled.
	 */
	public function test_maybe_run_imports_schedules_event_when_not_scheduled(): void {

		// Clear any pre-existing scheduled event (set_up() hooks fire init which
		// calls maybe_run_imports() before this test body runs).
		$existing = wp_next_scheduled('wu_import_site');
		if ($existing) {
			wp_unschedule_event($existing, 'wu_import_site');
		}

		$this->assertFalse(wp_next_scheduled('wu_import_site'), 'Event must be unscheduled before test');

		$this->exporter->maybe_run_imports();

		$scheduled = wp_next_scheduled('wu_import_site');

		$this->assertNotFalse($scheduled, 'wu_import_site must be scheduled after maybe_run_imports()');
		$this->assertGreaterThan(0, $scheduled, 'Scheduled timestamp must be a positive Unix timestamp');
	}

	/**
	 * Test that maybe_run_imports does not double-schedule the event when
	 * it is already scheduled.
	 */
	public function test_maybe_run_imports_does_not_reschedule_existing_event(): void {

		// Ensure a clean single-scheduled state: clear any existing event, then
		// schedule one at a known time so we can detect whether it changed.
		$existing = wp_next_scheduled('wu_import_site');
		if ($existing) {
			wp_unschedule_event($existing, 'wu_import_site');
		}

		wp_schedule_event(time() + 60, 'wu_site_every_minute', 'wu_import_site');

		$first_timestamp = wp_next_scheduled('wu_import_site');

		$this->exporter->maybe_run_imports();

		$second_timestamp = wp_next_scheduled('wu_import_site');

		$this->assertSame(
			$first_timestamp,
			$second_timestamp,
			'maybe_run_imports() must not alter an already-scheduled event timestamp'
		);
	}

	/**
	 * Integration test: wp_schedule_event() with wu_site_every_minute interval
	 * must succeed because the cron_schedules filter always registers it.
	 *
	 * This is the core regression test for GH#1009. Before the fix, the interval
	 * was only registered when imports were pending, causing wp_schedule_event()
	 * to fail when no imports existed yet.
	 */
	public function test_wp_schedule_event_succeeds_with_no_pending_imports(): void {

		// Confirm no pending imports.
		$pending = wu_exporter_get_pending_imports();
		$this->assertEmpty($pending, 'Test requires no pending imports');

		// Attempt to schedule with the plugin interval — must succeed.
		$result = wp_schedule_event(time() + 10, 'wu_site_every_minute', 'wu_import_site');

		$this->assertNotFalse(
			$result,
			'wp_schedule_event() with wu_site_every_minute must succeed even when no imports are pending'
		);

		$this->assertNotFalse(
			wp_next_scheduled('wu_import_site'),
			'wu_import_site event must be registered in WP cron after wp_schedule_event() call'
		);
	}

	/**
	 * Test that the cron_schedules filter is hooked to maybe_add_schedule.
	 */
	public function test_cron_schedules_filter_is_registered(): void {

		$priority = has_filter('cron_schedules', [ $this->exporter, 'maybe_add_schedule' ]);

		$this->assertNotFalse(
			$priority,
			'maybe_add_schedule must be registered as a cron_schedules filter callback'
		);
	}

	/**
	 * Test that the wu_import_site action is hooked to handle_site_import.
	 */
	public function test_wu_import_site_action_is_registered(): void {

		$priority = has_action('wu_import_site', [ $this->exporter, 'handle_site_import' ]);

		$this->assertNotFalse(
			$priority,
			'handle_site_import must be registered as a wu_import_site action callback'
		);
	}
}
