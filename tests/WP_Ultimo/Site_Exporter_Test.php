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
	 * Pending import transient hashes created during tests.
	 *
	 * @var string[]
	 */
	private array $pending_import_hashes = [];

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->exporter = Site_Exporter::get_instance();

		$this->clear_import_cron_events();
	}

	/**
	 * Tear down after each test.
	 * Unschedule any test-created cron events to avoid bleed-through.
	 */
	public function tear_down(): void {

		$this->clear_import_cron_events();

		foreach ($this->pending_import_hashes as $hash) {
			wu_exporter_delete_transient("wu_pending_site_import_{$hash}");
			wu_exporter_delete_transient("wu_pending_network_import_{$hash}");
		}

		$this->pending_import_hashes = [];

		parent::tear_down();
	}

	/**
	 * Clear importer cron events created by tests.
	 */
	private function clear_import_cron_events(): void {

		wp_clear_scheduled_hook('wu_import_site');
		wp_clear_scheduled_hook('wu_import_network');

		$this->clear_pending_import_transients('site');
		$this->clear_pending_import_transients('network');
		$this->clear_pending_export_transients();
	}

	/**
	 * Clear pending site export transients created by tests.
	 */
	private function clear_pending_export_transients(): void {

		global $wpdb;

		if (is_multisite()) {
			$table      = "{$wpdb->base_prefix}sitemeta";
			$key_column = 'meta_key';
			$like       = $wpdb->esc_like('_site_transient_wu_pending_site_export_') . '%';
		} else {
			$table      = "{$wpdb->base_prefix}options";
			$key_column = 'option_name';
			$like       = $wpdb->esc_like('_transient_wu_pending_site_export_') . '%';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column are selected from known WordPress schema names.
		$keys = $wpdb->get_col($wpdb->prepare("SELECT {$key_column} FROM {$table} WHERE {$key_column} LIKE %s", $like));

		foreach ($keys as $key) {
			$transient = preg_replace('/^_(site_)?transient_/', '', $key);

			if (is_string($transient)) {
				wu_exporter_delete_transient($transient);
			}
		}
	}

	/**
	 * Clear pending import transients for the given import type.
	 *
	 * The scheduling tests create pending imports directly so maybe_run_imports()
	 * remains responsible for scheduling the cron hook. Clean every matching
	 * transient, not only hashes created by the current test, so full-suite runs
	 * cannot inherit pending imports from a previous test failure.
	 *
	 * @param string $type Import type. Accepts site or network.
	 */
	private function clear_pending_import_transients(string $type): void {

		global $wpdb;

		if (! in_array($type, ['site', 'network'], true)) {
			return;
		}

		if (is_multisite()) {
			$table      = "{$wpdb->base_prefix}sitemeta";
			$key_column = 'meta_key';
			$like       = $wpdb->esc_like("_site_transient_wu_pending_{$type}_import_") . '%';
		} else {
			$table      = "{$wpdb->base_prefix}options";
			$key_column = 'option_name';
			$like       = $wpdb->esc_like("_transient_wu_pending_{$type}_import_") . '%';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column are selected from known WordPress schema names.
		$keys = $wpdb->get_col($wpdb->prepare("SELECT {$key_column} FROM {$table} WHERE {$key_column} LIKE %s", $like));

		foreach ($keys as $key) {
			$transient = preg_replace('/^_(site_)?transient_/', '', $key);

			if (is_string($transient)) {
				wu_exporter_delete_transient($transient);
			}
		}
	}

	/**
	 * Assert an import hook is scheduled with the expected cron event shape.
	 *
	 * @param string $hook Import cron hook.
	 * @return int Scheduled timestamp.
	 */
	private function assert_scheduled_import_event(string $hook): int {

		$scheduled = wp_next_scheduled($hook);

		$this->assertNotFalse($scheduled, "{$hook} must be scheduled after maybe_run_imports()");
		$this->assertGreaterThan(0, $scheduled, 'Scheduled timestamp must be a positive Unix timestamp');

		$cron = _get_cron_array();

		$this->assertIsArray($cron);
		$this->assertArrayHasKey($scheduled, $cron);
		$this->assertArrayHasKey($hook, $cron[ $scheduled ]);
		$this->assertArrayHasKey(md5(serialize([])), $cron[ $scheduled ][ $hook ]); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Mirrors WordPress cron event key generation.

		$event = $cron[ $scheduled ][ $hook ][ md5(serialize([])) ]; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Mirrors WordPress cron event key generation.

		$this->assertSame('wu_site_every_minute', $event['schedule']);
		$this->assertSame([], $event['args']);
		$this->assertSame(60, $event['interval']);

		return $scheduled;
	}

	/**
	 * Add a pending site import transient.
	 *
	 * @return string
	 */
	private function add_pending_site_import(): string {

		$hash = md5(uniqid('site-import-', true));

		wu_exporter_set_transient("wu_pending_site_import_{$hash}", ['/tmp/site-import.zip', [], $hash], 2 * HOUR_IN_SECONDS);

		$this->pending_import_hashes[] = $hash;

		return $hash;
	}

	/**
	 * Add a pending network import transient.
	 *
	 * @return string
	 */
	private function add_pending_network_import(): string {

		$hash = md5(uniqid('network-import-', true));

		wu_exporter_set_transient("wu_pending_network_import_{$hash}", ['/tmp/network-import.zip', [], $hash], 2 * HOUR_IN_SECONDS);

		$this->pending_import_hashes[] = $hash;

		return $hash;
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
	 * Test that main-site rows expose export without offering self-promotion.
	 */
	public function test_wp_sites_row_actions_include_main_site_export(): void {

		$actions = $this->exporter->add_wp_sites_row_actions([], get_main_site_id());

		$this->assertArrayHasKey('export', $actions, 'The WordPress main site must be exportable from Network Admin > Sites');
		$this->assertArrayNotHasKey('promote_main_site', $actions, 'The WordPress main site must not offer Promote to Main Site');
	}

	/**
	 * Test that subsite rows still expose both export and main-site promotion.
	 */
	public function test_wp_sites_row_actions_include_subsite_export_and_promotion(): void {

		$blog_id = self::factory()->blog->create(['path' => '/exportable-subsite/']);

		$actions = $this->exporter->add_wp_sites_row_actions([], $blog_id);

		$this->assertArrayHasKey('export', $actions, 'Subsites must remain exportable from Network Admin > Sites');
		$this->assertArrayHasKey('promote_main_site', $actions, 'Subsites must still offer Promote to Main Site');
	}

	/**
	 * Test that bulk exports include the main site instead of silently skipping it.
	 */
	public function test_wp_sites_bulk_export_includes_main_site(): void {

		if (! function_exists('wu_enqueue_async_action')) {
			$this->markTestSkipped('wu_enqueue_async_action is not available in this environment');
		}

		$redirect_url = $this->exporter->handle_wp_sites_bulk_action('', 'export', [get_main_site_id()]);
		$pending      = wu_exporter_get_pending();

		$this->assertStringContainsString('bulk_exported=1', $redirect_url);
		$this->assertNotEmpty($pending, 'Bulk exporting the main site must create a pending site export');
		$this->assertSame(get_main_site_id(), (int) $pending[0]->options[0]);
	}

	/**
	 * Test imported table parsing used to scope WP-CLI search-replace.
	 */
	public function test_imported_table_names_are_extracted_from_sql(): void {

		$this->exporter->load_dependencies();

		$sql_file = tempnam(sys_get_temp_dir(), 'wu_import_tables_');

		if (false === $sql_file) {
			$this->markTestSkipped('Unable to create temporary SQL file');
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture writes a local temporary SQL file.
		file_put_contents(
			$sql_file,
			implode(
				"\n",
				[
					'CREATE TABLE IF NOT EXISTS `wp_61_posts` (`ID` bigint);',
					'INSERT INTO `wp_61_options` VALUES (1, "siteurl", "http://example.test", "yes");',
					'LOCK TABLES `wp_61_postmeta` WRITE;',
					'INSERT INTO `wp_61_options` VALUES (2, "fixture", "text mentioning CREATE TABLE `wp_61_not_a_table`", "yes");',
					'INSERT INTO `wp_61_posts` VALUES (1);',
				]
			)
		);

		try {
			$command    = new \TenUp\MU_Migration\Commands\ImportCommand();
			$reflection = new \ReflectionMethod($command, 'get_imported_table_names');
			$reflection->setAccessible(true);

			$this->assertSame(
				['wp_61_posts', 'wp_61_options', 'wp_61_postmeta'],
				$reflection->invoke($command, $sql_file),
				'Importer search-replace must be scoped to tables present in the imported SQL file'
			);
		} finally {
			wp_delete_file($sql_file);
		}
	}

	/**
	 * Regression test for GH#1009 — circular dependency.
	 *
	 * The maybe_add_schedule() method must register wu_site_every_minute regardless of
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
	 * Test that maybe_run_imports does not schedule import events when no imports are pending.
	 */
	public function test_maybe_run_imports_does_not_schedule_events_without_pending_imports(): void {

		$this->assertEmpty(wu_exporter_get_pending_imports(), 'Test requires no pending site imports');
		$this->assertEmpty(wu_exporter_get_pending_network_imports(), 'Test requires no pending network imports');

		$this->exporter->maybe_run_imports();

		$this->assertFalse(wp_next_scheduled('wu_import_site'), 'wu_import_site must not be scheduled without pending imports');
		$this->assertFalse(wp_next_scheduled('wu_import_network'), 'wu_import_network must not be scheduled without pending imports');
	}

	/**
	 * Test that maybe_run_imports schedules the wu_import_site event when a site import is pending.
	 */
	public function test_maybe_run_imports_schedules_site_event_when_site_import_is_pending(): void {

		$this->add_pending_site_import();

		$this->exporter->maybe_run_imports();

		$this->assert_scheduled_import_event('wu_import_site');
		$this->assertFalse(wp_next_scheduled('wu_import_network'), 'wu_import_network must not be scheduled for a site import');
	}

	/**
	 * Test that maybe_run_imports schedules the wu_import_network event when a network import is pending.
	 */
	public function test_maybe_run_imports_schedules_network_event_when_network_import_is_pending(): void {

		$this->add_pending_network_import();

		$this->exporter->maybe_run_imports();

		$this->assert_scheduled_import_event('wu_import_network');
		$this->assertFalse(wp_next_scheduled('wu_import_site'), 'wu_import_site must not be scheduled for a network import');
	}

	/**
	 * Test that maybe_run_imports does not double-schedule the event when
	 * it is already scheduled.
	 */
	public function test_maybe_run_imports_does_not_reschedule_existing_event(): void {

		$this->add_pending_site_import();

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

		$priority = has_filter('cron_schedules', [$this->exporter, 'maybe_add_schedule']);

		$this->assertNotFalse(
			$priority,
			'maybe_add_schedule must be registered as a cron_schedules filter callback'
		);
	}

	/**
	 * Test that the wu_import_site action is hooked to handle_site_import.
	 */
	public function test_wu_import_site_action_is_registered(): void {

		$priority = has_action('wu_import_site');

		$this->assertNotFalse(
			$priority,
			'handle_site_import must be registered as a wu_import_site action callback'
		);
	}

	/**
	 * Test that the wu_export_network action is hooked to handle_network_export.
	 */
	public function test_wu_export_network_action_is_registered(): void {

		$priority = has_action('wu_export_network');

		$this->assertNotFalse(
			$priority,
			'handle_network_export must be registered as a wu_export_network action callback'
		);
	}

	/**
	 * Test that the network export form is registered.
	 */
	public function test_network_export_form_is_registered(): void {

		$forms = \WP_Ultimo\Managers\Form_Manager::get_instance()->get_registered_forms();

		$this->assertArrayHasKey('export_network', $forms, 'export_network form must be registered');
		$this->assertEquals('manage_network', $forms['export_network']['capability']);
	}

	/**
	 * Test that the bulk network export action is added.
	 */
	public function test_bulk_network_export_action_is_added(): void {

		$actions = apply_filters('wu_site_bulk_actions', []);

		$this->assertArrayHasKey('network_export', $actions, 'network_export bulk action must be added');
	}

	/**
	 * Test exporter replace preserves incomplete serialized objects unchanged.
	 */
	public function test_recursive_unserialize_replace_preserves_incomplete_objects(): void {

		$class_name = 'Missing\\Plugin\\Notification';
		$old_url    = 'https://example.com/old/page';
		$serialized = sprintf(
			'O:%d:"%s":1:{s:3:"url";s:%d:"%s";}',
			strlen($class_name),
			$class_name,
			strlen($old_url),
			$old_url
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Regression fixture must build serialized PHP payload.
		$payload  = serialize(
			[
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Intentionally creates an incomplete object fixture.
				'notice' => @unserialize($serialized),
				'url'    => $old_url,
			]
		);
		$warnings = 0;
		$replace  = (new \ReflectionClass(\WP_Ultimo\Site_Exporter\Database\Replace::class))->newInstanceWithoutConstructor();

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Regression test asserts no incomplete-object warning is emitted.
		set_error_handler(
			static function ($errno, $errstr) use (&$warnings) {
				if (false !== strpos($errstr, 'incomplete object')) {
					++$warnings;
				}

				return true;
			}
		);

		try {
			$result = $replace->recursive_unserialize_replace('example.com/old', 'example.com/new', $payload);
		} finally {
			restore_error_handler();
		}

		$this->assertSame(0, $warnings);
		$this->assertStringContainsString($old_url, $result);
		$this->assertStringContainsString('https://example.com/new/page', $result);
	}
}
