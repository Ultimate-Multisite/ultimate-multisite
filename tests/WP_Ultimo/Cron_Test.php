<?php
/**
 * Tests for Cron class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Test class for Cron.
 */
class Cron_Test extends WP_UnitTestCase {

	/**
	 * @var Cron
	 */
	private Cron $cron;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();
		$this->cron = Cron::get_instance();
		wu_unschedule_all_actions('wu_monthly', [], 'wu_cron');
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {

		remove_action('wu_monthly', [$this, 'dummy_cron_callback']);
		wu_unschedule_all_actions('wu_monthly', [], 'wu_cron');

		parent::tear_down();
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf(Cron::class, $this->cron);
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(Cron::get_instance(), Cron::get_instance());
	}

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks(): void {

		$this->cron->init();

		$this->assertGreaterThan(0, has_action('init', [$this->cron, 'create_schedules']));
		$this->assertSame(2, has_action('init', [$this->cron, 'maybe_ensure_action_scheduler_tables']));
		$this->assertSame(200, has_action('wp_initialize_site', [$this->cron, 'initialize_site_action_scheduler_tables']));
		$this->assertGreaterThan(0, has_action('init', [$this->cron, 'schedule_membership_check']));
		$this->assertGreaterThan(0, has_action('wu_membership_check', [$this->cron, 'membership_renewal_check']));
		$this->assertGreaterThan(0, has_action('wu_membership_check', [$this->cron, 'membership_trial_check']));
		$this->assertGreaterThan(0, has_action('wu_membership_check', [$this->cron, 'membership_expired_check']));
	}

	/**
	 * Test missing Action Scheduler tables are recreated for a site.
	 */
	public function test_initialize_site_action_scheduler_tables_repairs_missing_tables(): void {

		global $wpdb;

		$original_site_id = get_current_blog_id();
		$site_id          = self::factory()->blog->create();
		$site             = get_site($site_id);
		$this->assertInstanceOf(\WP_Site::class, $site);

		$table_names     = [];
		$filters_removed = false;
		try {
			switch_to_blog($site_id);
			$table_names = [
				$wpdb->prefix . 'actionscheduler_actions',
				$wpdb->prefix . 'actionscheduler_claims',
				$wpdb->prefix . 'actionscheduler_groups',
				$wpdb->prefix . 'actionscheduler_logs',
			];

			// Remove the temporary schemas created by wp_initialize_site().
			foreach ($table_names as $table_name) {
				$wpdb->query("DROP TABLE IF EXISTS `{$table_name}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			// Exercise dbDelta() with normal tables because SHOW TABLES cannot see
			// the temporary tables created by the WordPress PHPUnit filters.
			remove_filter('query', [$this, '_create_temporary_tables']);
			remove_filter('query', [$this, '_drop_temporary_tables']);
			$filters_removed = true;

			// Remove any leaked regular schemas so this always exercises repair.
			foreach ($table_names as $table_name) {
				$wpdb->query("DROP TABLE IF EXISTS `{$table_name}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			$this->assertTrue($this->cron->ensure_action_scheduler_tables());

			foreach ($table_names as $table_name) {
				$this->assertSame(
					$table_name,
					$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name))),
					"Action Scheduler table was not recreated: {$table_name}"
				);
			}

			foreach ($table_names as $table_name) {
				$wpdb->query("DROP TABLE IF EXISTS `{$table_name}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			restore_current_blog();
			$this->cron->initialize_site_action_scheduler_tables($site);
			$this->assertSame($original_site_id, get_current_blog_id());

			switch_to_blog($site_id);
			foreach ($table_names as $table_name) {
				$this->assertSame(
					$table_name,
					$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name))),
					"Action Scheduler table was not recreated by the site initialization hook: {$table_name}"
				);
			}
		} finally {
			if ( ! $filters_removed) {
				remove_filter('query', [$this, '_create_temporary_tables']);
				remove_filter('query', [$this, '_drop_temporary_tables']);
			}

			if (get_current_blog_id() !== $site_id) {
				switch_to_blog($site_id);
			}

			foreach ($table_names as $table_name) {
				$wpdb->query("DROP TABLE IF EXISTS `{$table_name}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			while (ms_is_switched()) {
				restore_current_blog();
			}

			add_filter('query', [$this, '_create_temporary_tables']);
			add_filter('query', [$this, '_drop_temporary_tables']);
		}
	}

	/**
	 * Test membership_renewal_check runs without error.
	 */
	public function test_membership_renewal_check_no_memberships(): void {

		// Should not throw with no memberships.
		$this->cron->membership_renewal_check();

		$this->assertTrue(true); // No exception thrown.
	}

	/**
	 * Test membership_trial_check runs without error.
	 */
	public function test_membership_trial_check_no_memberships(): void {

		$this->cron->membership_trial_check();

		$this->assertTrue(true);
	}

	/**
	 * Test membership_expired_check runs without error.
	 */
	public function test_membership_expired_check_no_memberships(): void {

		$this->cron->membership_expired_check();

		$this->assertTrue(true);
	}

	/**
	 * Test async_create_renewal_payment with invalid membership.
	 */
	public function test_async_create_renewal_payment_invalid(): void {

		// Should return early with no error for nonexistent membership.
		$this->cron->async_create_renewal_payment(999999);

		$this->assertTrue(true);
	}

	/**
	 * Test async_mark_membership_as_expired with invalid membership.
	 */
	public function test_async_mark_membership_as_expired_invalid(): void {

		$this->cron->async_mark_membership_as_expired(999999);

		$this->assertTrue(true);
	}

	/**
	 * Test create_schedules removes stale no-callback recurring actions.
	 */
	public function test_create_schedules_unschedules_no_callback_recurring_action(): void {

		wu_schedule_recurring_action(time() + HOUR_IN_SECONDS, MONTH_IN_SECONDS, 'wu_monthly', [], 'wu_cron');

		$this->assertNotFalse(wu_next_scheduled_action('wu_monthly', [], 'wu_cron'));

		$this->cron->create_schedules();

		$this->assertFalse(wu_next_scheduled_action('wu_monthly', [], 'wu_cron'));
	}

	/**
	 * Test create_schedules keeps scheduling hooks that have callbacks.
	 */
	public function test_create_schedules_schedules_when_callback_exists(): void {

		add_action('wu_monthly', [$this, 'dummy_cron_callback']);

		$this->cron->create_schedules();

		$this->assertNotFalse(wu_next_scheduled_action('wu_monthly', [], 'wu_cron'));
	}

	/**
	 * Dummy callback for recurring cron tests.
	 */
	public function dummy_cron_callback(): void {

		$this->assertTrue(true);
	}
}
