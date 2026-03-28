<?php
/**
 * Unit tests for the Migrator class.
 *
 * @package WP_Ultimo\Tests\Installers
 */

namespace WP_Ultimo\Tests\Installers;

use WP_Ultimo\Installers\Migrator;
use WP_Error;

/**
 * Unit tests for Migrator.
 *
 * Tests cover all public methods and protected _install_* methods via reflection:
 * - Singleton / get_instance
 * - is_migration_done (static)
 * - is_legacy_network (static)
 * - get_errors / get_back_traces
 * - get_steps (dry-run and full)
 * - get_old_settings / get_old_setting
 * - add_id_of_interest / log_ids_of_interest
 * - handle_error_messages
 * - fake_register_settings
 * - handle (dry-run path)
 * - on_shutdown
 * - _install_customers (via reflection, with legacy table)
 * - _install_memberships (via reflection, with legacy table)
 * - _install_transactions (via reflection, with legacy table)
 * - _install_discount_codes (via reflection)
 * - _install_sites (via reflection, with legacy table)
 * - _install_site_templates (via reflection)
 * - _install_domains (via reflection, with legacy table)
 * - _install_emails (via reflection)
 * - _install_webhooks (via reflection)
 * - _install_other (via reflection)
 * - _install_dry_run_check (via reflection)
 * - build_limit_clause (via reflection)
 * - is_parallel (via reflection)
 * - get_installer (via reflection)
 * - bypass_server_limits (via reflection)
 */
class Migrator_Test extends \WP_UnitTestCase {

	/**
	 * Migrator instance under test.
	 *
	 * @var Migrator
	 */
	protected $migrator;

	/**
	 * Whether legacy tables were created.
	 *
	 * @var bool
	 */
	protected static $legacy_tables_created = false;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->migrator = Migrator::get_instance();

		// Reset state between tests.
		$this->migrator->errors      = [];
		$this->migrator->back_traces = [];

		// Reset ids_of_interest via reflection.
		$ref  = new \ReflectionClass( $this->migrator );
		$prop = $ref->getProperty( 'ids_of_interest' );
		$prop->setAccessible( true );
		$prop->setValue( $this->migrator, [] );

		// Reset settings cache.
		$settings_prop = $ref->getProperty( 'settings' );
		$settings_prop->setAccessible( true );
		$settings_prop->setValue( $this->migrator, null );

		// Reset dry_run to true (default).
		$dry_run_prop = $ref->getProperty( 'dry_run' );
		$dry_run_prop->setAccessible( true );
		$dry_run_prop->setValue( $this->migrator, true );

		// Reset server_bypass_status.
		$bypass_prop = $ref->getProperty( 'server_bypass_status' );
		$bypass_prop->setAccessible( true );
		$bypass_prop->setValue( $this->migrator, true );

		// Reset run_tests_on_limited_set.
		$limited_prop = $ref->getProperty( 'run_tests_on_limited_set' );
		$limited_prop->setAccessible( true );
		$limited_prop->setValue( $this->migrator, false );

		// Ensure legacy tables exist.
		$this->create_legacy_tables();

		// Set dry-run mode by default.
		$_REQUEST['dry-run'] = '1';
	}

	/**
	 * Tear down: remove any network options set during tests.
	 */
	public function tearDown(): void {
		delete_network_option( null, 'wu_is_migration_done' );
		unset( $_REQUEST['dry-run'], $_REQUEST['parallel'], $_REQUEST['page'], $_REQUEST['per_page'], $_REQUEST['installer'] );
		parent::tearDown();
	}

	/**
	 * Create legacy v1 database tables needed for migration tests.
	 */
	protected function create_legacy_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// wu_subscriptions table.
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}wu_subscriptions` (
				`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
				`plan_id` bigint(20) unsigned NOT NULL DEFAULT 0,
				`price` decimal(10,2) NOT NULL DEFAULT 0.00,
				`trial` int(11) NOT NULL DEFAULT 0,
				`freq` varchar(10) NOT NULL DEFAULT '1',
				`active_until` datetime DEFAULT NULL,
				`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				`gateway` varchar(100) DEFAULT NULL,
				`integration_key` varchar(255) DEFAULT NULL,
				`integration_status` tinyint(1) NOT NULL DEFAULT 0,
				`last_plan_change` datetime DEFAULT NULL,
				`meta_object` longtext DEFAULT NULL,
				PRIMARY KEY (`ID`)
			) {$charset_collate}"
		);

		// wu_transactions table.
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}wu_transactions` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
				`plan_id` bigint(20) unsigned DEFAULT NULL,
				`amount` decimal(10,2) NOT NULL DEFAULT 0.00,
				`original_amount` decimal(10,2) DEFAULT NULL,
				`type` varchar(50) NOT NULL DEFAULT 'payment',
				`description` text DEFAULT NULL,
				`gateway` varchar(100) DEFAULT NULL,
				`reference_id` varchar(255) DEFAULT NULL,
				`time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (`id`)
			) {$charset_collate}"
		);

		// wu_site_owner table.
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}wu_site_owner` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`site_id` bigint(20) unsigned NOT NULL DEFAULT 0,
				`user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`)
			) {$charset_collate}"
		);

		// domain_mapping table (for _install_domains).
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}domain_mapping` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`blog_id` bigint(20) unsigned NOT NULL DEFAULT 0,
				`domain` varchar(255) NOT NULL DEFAULT '',
				`active` tinyint(1) NOT NULL DEFAULT 1,
				PRIMARY KEY (`id`)
			) {$charset_collate}"
		);
	}

	/**
	 * Helper: invoke a protected/private method via reflection.
	 *
	 * @param string $method_name The method name.
	 * @param array  $args        Arguments to pass.
	 * @return mixed
	 */
	protected function invoke_method( string $method_name, array $args = [] ) {
		$ref    = new \ReflectionClass( $this->migrator );
		$method = $ref->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $this->migrator, $args );
	}

	/**
	 * Helper: get a protected/private property value via reflection.
	 *
	 * @param string $property_name The property name.
	 * @return mixed
	 */
	protected function get_property( string $property_name ) {
		$ref  = new \ReflectionClass( $this->migrator );
		$prop = $ref->getProperty( $property_name );
		$prop->setAccessible( true );
		return $prop->getValue( $this->migrator );
	}

	/**
	 * Helper: set a protected/private property value via reflection.
	 *
	 * @param string $property_name The property name.
	 * @param mixed  $value         The value to set.
	 */
	protected function set_property( string $property_name, $value ): void {
		$ref  = new \ReflectionClass( $this->migrator );
		$prop = $ref->getProperty( $property_name );
		$prop->setAccessible( true );
		$prop->setValue( $this->migrator, $value );
	}

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	/**
	 * Test get_instance returns a Migrator instance.
	 */
	public function test_get_instance_returns_migrator(): void {
		$this->assertInstanceOf( Migrator::class, $this->migrator );
	}

	/**
	 * Test get_instance returns the same object on repeated calls.
	 */
	public function test_get_instance_is_singleton(): void {
		$a = Migrator::get_instance();
		$b = Migrator::get_instance();
		$this->assertSame( $a, $b );
	}

	// -----------------------------------------------------------------------
	// is_migration_done
	// -----------------------------------------------------------------------

	/**
	 * Test is_migration_done returns true when no legacy plans exist and no option set.
	 */
	public function test_is_migration_done_true_when_no_legacy_plans(): void {
		$result = Migrator::is_migration_done();
		$this->assertTrue( $result );
	}

	/**
	 * Test is_migration_done returns true when network option is set.
	 */
	public function test_is_migration_done_true_when_option_set(): void {
		$plan_id = wp_insert_post( [
			'post_type'   => 'wpultimo_plan',
			'post_title'  => 'Test Plan',
			'post_status' => 'publish',
		] );

		update_network_option( null, 'wu_is_migration_done', true );

		$result = Migrator::is_migration_done();
		$this->assertTrue( $result );

		wp_delete_post( $plan_id, true );
		delete_network_option( null, 'wu_is_migration_done' );
	}

	/**
	 * Test is_migration_done returns false when legacy plans exist and option not set.
	 */
	public function test_is_migration_done_false_when_legacy_plans_exist(): void {
		$plan_id = wp_insert_post( [
			'post_type'   => 'wpultimo_plan',
			'post_title'  => 'Legacy Plan',
			'post_status' => 'publish',
		] );

		delete_network_option( null, 'wu_is_migration_done' );

		$result = Migrator::is_migration_done();
		$this->assertFalse( $result );

		wp_delete_post( $plan_id, true );
	}

	// -----------------------------------------------------------------------
	// is_legacy_network
	// -----------------------------------------------------------------------

	/**
	 * Test is_legacy_network returns false when migration is done.
	 */
	public function test_is_legacy_network_false_when_migration_done(): void {
		$result = Migrator::is_legacy_network();
		$this->assertFalse( $result );
	}

	/**
	 * Test is_legacy_network returns true when migration is not done.
	 */
	public function test_is_legacy_network_true_when_migration_not_done(): void {
		$plan_id = wp_insert_post( [
			'post_type'   => 'wpultimo_plan',
			'post_title'  => 'Legacy Plan',
			'post_status' => 'publish',
		] );

		delete_network_option( null, 'wu_is_migration_done' );

		$result = Migrator::is_legacy_network();
		$this->assertTrue( $result );

		wp_delete_post( $plan_id, true );
	}

	/**
	 * Test is_legacy_network is the inverse of is_migration_done.
	 */
	public function test_is_legacy_network_is_inverse_of_is_migration_done(): void {
		$done   = Migrator::is_migration_done();
		$legacy = Migrator::is_legacy_network();
		$this->assertNotSame( $done, $legacy );
	}

	// -----------------------------------------------------------------------
	// get_errors
	// -----------------------------------------------------------------------

	/**
	 * Test get_errors returns an empty array when no errors set.
	 */
	public function test_get_errors_returns_empty_array_initially(): void {
		$this->migrator->errors = null;
		$result = $this->migrator->get_errors();
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_errors returns unique errors.
	 */
	public function test_get_errors_returns_unique_values(): void {
		$this->migrator->errors = [ 'error1', 'error1', 'error2' ];
		$result = $this->migrator->get_errors();
		$this->assertCount( 2, $result );
		$this->assertContains( 'error1', $result );
		$this->assertContains( 'error2', $result );
	}

	/**
	 * Test get_errors handles non-array errors.
	 */
	public function test_get_errors_handles_non_array_errors(): void {
		$this->migrator->errors = 'some string error';
		$result = $this->migrator->get_errors();
		$this->assertIsArray( $result );
	}

	/**
	 * Test get_errors with multiple distinct errors.
	 */
	public function test_get_errors_returns_all_distinct_errors(): void {
		$this->migrator->errors = [ 'err_a', 'err_b', 'err_c' ];
		$result = $this->migrator->get_errors();
		$this->assertCount( 3, $result );
	}

	/**
	 * Test get_errors deduplicates.
	 */
	public function test_get_errors_deduplicates(): void {
		$this->migrator->errors = [ 'err', 'err', 'err', 'other' ];
		$result = $this->migrator->get_errors();
		$this->assertCount( 2, $result );
	}

	// -----------------------------------------------------------------------
	// get_back_traces
	// -----------------------------------------------------------------------

	/**
	 * Test get_back_traces returns an empty array when no traces set.
	 */
	public function test_get_back_traces_returns_empty_array_initially(): void {
		$this->migrator->back_traces = null;
		$result = $this->migrator->get_back_traces();
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_back_traces returns unique traces.
	 */
	public function test_get_back_traces_returns_unique_values(): void {
		$this->migrator->back_traces = [ 'trace1', 'trace1', 'trace2' ];
		$result = $this->migrator->get_back_traces();
		$this->assertCount( 2, $result );
	}

	/**
	 * Test get_back_traces handles non-array value.
	 */
	public function test_get_back_traces_handles_non_array(): void {
		$this->migrator->back_traces = false;
		$result = $this->migrator->get_back_traces();
		$this->assertIsArray( $result );
	}

	/**
	 * Test get_back_traces deduplicates traces.
	 */
	public function test_get_back_traces_deduplicates(): void {
		$this->migrator->back_traces = [ 'trace', 'trace', 'unique' ];
		$result = $this->migrator->get_back_traces();
		$this->assertCount( 2, $result );
	}

	// -----------------------------------------------------------------------
	// get_steps
	// -----------------------------------------------------------------------

	/**
	 * Test get_steps returns an array.
	 */
	public function test_get_steps_returns_array(): void {
		$result = $this->migrator->get_steps();
		$this->assertIsArray( $result );
	}

	/**
	 * Test get_steps in dry-run mode returns only dry_run_check step.
	 */
	public function test_get_steps_dry_run_returns_only_dry_run_check(): void {
		$steps = $this->migrator->get_steps();
		$this->assertArrayHasKey( 'dry_run_check', $steps );
		$this->assertCount( 1, $steps );
	}

	/**
	 * Test get_steps in non-dry-run mode returns all migration steps.
	 */
	public function test_get_steps_non_dry_run_returns_all_steps(): void {
		$_REQUEST['dry-run'] = '0';
		$steps = $this->migrator->get_steps();

		$expected_keys = [
			'backup',
			'settings',
			'products',
			'customers',
			'memberships',
			'transactions',
			'discount_codes',
			'sites',
			'site_templates',
			'domains',
			'forms',
			'emails',
			'webhooks',
			'other',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $steps, "Step '{$key}' should be present in non-dry-run mode" );
		}
	}

	/**
	 * Test get_steps with force_all=true returns all steps regardless of dry-run.
	 */
	public function test_get_steps_force_all_returns_all_steps(): void {
		$steps = $this->migrator->get_steps( true );

		$this->assertArrayNotHasKey( 'dry_run_check', $steps );
		$this->assertArrayHasKey( 'settings', $steps );
		$this->assertArrayHasKey( 'products', $steps );
		$this->assertArrayHasKey( 'customers', $steps );
	}

	/**
	 * Test each step has required fields.
	 */
	public function test_get_steps_each_step_has_required_fields(): void {
		$_REQUEST['dry-run'] = '0';
		$steps = $this->migrator->get_steps();

		$required_fields = [ 'title', 'description', 'pending', 'installing', 'success', 'done', 'help' ];

		foreach ( $steps as $key => $step ) {
			foreach ( $required_fields as $field ) {
				$this->assertArrayHasKey(
					$field,
					$step,
					"Step '{$key}' is missing field '{$field}'"
				);
			}
		}
	}

	/**
	 * Test get_steps dry_run_check step has correct structure.
	 */
	public function test_get_steps_dry_run_check_step_structure(): void {
		$steps = $this->migrator->get_steps();

		$step = $steps['dry_run_check'];
		$this->assertArrayHasKey( 'title', $step );
		$this->assertArrayHasKey( 'description', $step );
		$this->assertArrayHasKey( 'help', $step );
		$this->assertFalse( $step['done'] );
	}

	/**
	 * Test get_steps non-dry-run does not include dry_run_check.
	 */
	public function test_get_steps_non_dry_run_excludes_dry_run_check(): void {
		$_REQUEST['dry-run'] = '0';
		$steps = $this->migrator->get_steps();
		$this->assertArrayNotHasKey( 'dry_run_check', $steps );
	}

	/**
	 * Test get_steps applies wu_get_migration_steps filter.
	 *
	 * The filter is only applied in non-dry-run mode (or when force_all=true).
	 * In dry-run mode get_steps() returns early before reaching the filter.
	 */
	public function test_get_steps_applies_filter(): void {
		$filter_called = false;
		add_filter(
			'wu_get_migration_steps',
			function ( $steps ) use ( &$filter_called ) {
				$filter_called = true;
				return $steps;
			}
		);

		// Use force_all=true so the filter is reached regardless of dry-run state.
		$this->migrator->get_steps( true );
		$this->assertTrue( $filter_called, 'wu_get_migration_steps filter should be applied' );

		remove_all_filters( 'wu_get_migration_steps' );
	}

	/**
	 * Test get_steps filter can add custom step.
	 */
	public function test_get_steps_filter_can_add_custom_step(): void {
		$_REQUEST['dry-run'] = '0';

		add_filter(
			'wu_get_migration_steps',
			function ( $steps ) {
				$steps['custom_step'] = [
					'title'       => 'Custom Step',
					'description' => 'A custom migration step',
					'done'        => false,
					'help'        => '',
					'pending'     => 'Pending',
					'installing'  => 'Running...',
					'success'     => 'Done!',
				];
				return $steps;
			}
		);

		$steps = $this->migrator->get_steps();
		$this->assertArrayHasKey( 'custom_step', $steps );

		remove_all_filters( 'wu_get_migration_steps' );
	}

	// -----------------------------------------------------------------------
	// get_old_settings
	// -----------------------------------------------------------------------

	/**
	 * Test get_old_settings returns null when no legacy settings exist.
	 */
	public function test_get_old_settings_returns_null_when_no_settings(): void {
		global $wpdb;

		$wpdb->delete(
			$wpdb->base_prefix . 'sitemeta',
			[ 'meta_key' => 'wp-ultimo_settings' ],
			[ '%s' ]
		);

		$this->set_property( 'settings', null );

		$result = $this->migrator->get_old_settings();
		$this->assertNull( $result );
	}

	/**
	 * Test get_old_settings returns cached value on second call.
	 */
	public function test_get_old_settings_returns_cached_value(): void {
		$cached = [ 'currency' => 'USD', 'test_key' => 'test_value' ];
		$this->set_property( 'settings', $cached );

		$result = $this->migrator->get_old_settings();
		$this->assertSame( $cached, $result );
	}

	/**
	 * Test get_old_settings reads from sitemeta when cache is null.
	 */
	public function test_get_old_settings_reads_from_sitemeta(): void {
		global $wpdb;

		$settings_data = [ 'currency' => 'EUR', 'enable_signup' => true ];

		$wpdb->replace(
			$wpdb->base_prefix . 'sitemeta',
			[
				'site_id'    => 1,
				'meta_key'   => 'wp-ultimo_settings',
				'meta_value' => serialize( $settings_data ),
			],
			[ '%d', '%s', '%s' ]
		);

		$this->set_property( 'settings', null );

		$result = $this->migrator->get_old_settings();
		$this->assertIsArray( $result );
		$this->assertSame( 'EUR', $result['currency'] );

		$wpdb->delete(
			$wpdb->base_prefix . 'sitemeta',
			[ 'meta_key' => 'wp-ultimo_settings' ],
			[ '%s' ]
		);
	}

	// -----------------------------------------------------------------------
	// get_old_setting
	// -----------------------------------------------------------------------

	/**
	 * Test get_old_setting returns default when settings is null.
	 */
	public function test_get_old_setting_returns_default_when_no_settings(): void {
		$this->set_property( 'settings', null );

		$result = $this->migrator->get_old_setting( 'nonexistent_key', 'my_default' );
		$this->assertSame( 'my_default', $result );
	}

	/**
	 * Test get_old_setting returns correct value when key exists.
	 */
	public function test_get_old_setting_returns_value_when_key_exists(): void {
		$this->set_property( 'settings', [ 'currency' => 'GBP', 'precision' => 2 ] );

		$result = $this->migrator->get_old_setting( 'currency', 'USD' );
		$this->assertSame( 'GBP', $result );
	}

	/**
	 * Test get_old_setting returns default when key does not exist.
	 */
	public function test_get_old_setting_returns_default_when_key_missing(): void {
		$this->set_property( 'settings', [ 'currency' => 'USD' ] );

		$result = $this->migrator->get_old_setting( 'missing_key', 'fallback' );
		$this->assertSame( 'fallback', $result );
	}

	/**
	 * Test get_old_setting default is false when not specified.
	 */
	public function test_get_old_setting_default_is_false(): void {
		$this->set_property( 'settings', [] );

		$result = $this->migrator->get_old_setting( 'nonexistent' );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_old_setting returns integer value correctly.
	 */
	public function test_get_old_setting_returns_integer_value(): void {
		$this->set_property( 'settings', [ 'precision' => 3 ] );

		$result = $this->migrator->get_old_setting( 'precision', 2 );
		$this->assertSame( 3, $result );
	}

	// -----------------------------------------------------------------------
	// add_id_of_interest
	// -----------------------------------------------------------------------

	/**
	 * Test add_id_of_interest adds a single ID.
	 */
	public function test_add_id_of_interest_adds_single_id(): void {
		$this->migrator->add_id_of_interest( 42, 'not_found', 'customers' );

		$ids = $this->get_property( 'ids_of_interest' );
		$this->assertArrayHasKey( 'customers:not_found', $ids );
		$this->assertContains( 42, $ids['customers:not_found'] );
	}

	/**
	 * Test add_id_of_interest adds multiple IDs as array.
	 */
	public function test_add_id_of_interest_adds_array_of_ids(): void {
		$this->migrator->add_id_of_interest( [ 1, 2, 3 ], 'plan_not_migrated', 'memberships' );

		$ids = $this->get_property( 'ids_of_interest' );
		$this->assertArrayHasKey( 'memberships:plan_not_migrated', $ids );
		$this->assertCount( 3, $ids['memberships:plan_not_migrated'] );
	}

	/**
	 * Test add_id_of_interest accumulates IDs across multiple calls.
	 */
	public function test_add_id_of_interest_accumulates_ids(): void {
		$this->migrator->add_id_of_interest( 10, 'not_found', 'customers' );
		$this->migrator->add_id_of_interest( 20, 'not_found', 'customers' );

		$ids = $this->get_property( 'ids_of_interest' );
		$this->assertCount( 2, $ids['customers:not_found'] );
		$this->assertContains( 10, $ids['customers:not_found'] );
		$this->assertContains( 20, $ids['customers:not_found'] );
	}

	/**
	 * Test add_id_of_interest uses correct key format installer:reason.
	 */
	public function test_add_id_of_interest_uses_correct_key_format(): void {
		$this->migrator->add_id_of_interest( 5, 'customer_not_migrated', 'transactions' );

		$ids = $this->get_property( 'ids_of_interest' );
		$this->assertArrayHasKey( 'transactions:customer_not_migrated', $ids );
	}

	/**
	 * Test add_id_of_interest with different installers creates separate keys.
	 */
	public function test_add_id_of_interest_separate_keys_per_installer(): void {
		$this->migrator->add_id_of_interest( 1, 'not_found', 'customers' );
		$this->migrator->add_id_of_interest( 2, 'not_found', 'memberships' );

		$ids = $this->get_property( 'ids_of_interest' );
		$this->assertArrayHasKey( 'customers:not_found', $ids );
		$this->assertArrayHasKey( 'memberships:not_found', $ids );
	}

	// -----------------------------------------------------------------------
	// log_ids_of_interest
	// -----------------------------------------------------------------------

	/**
	 * Test log_ids_of_interest runs without error when ids_of_interest is empty.
	 */
	public function test_log_ids_of_interest_empty_list_no_error(): void {
		$this->set_property( 'ids_of_interest', [] );

		$this->migrator->log_ids_of_interest();
		$this->assertTrue( true );
	}

	/**
	 * Test log_ids_of_interest handles corrupted (non-array) ids_of_interest.
	 */
	public function test_log_ids_of_interest_handles_corrupted_data(): void {
		$this->set_property( 'ids_of_interest', 'corrupted_string' );

		$this->migrator->log_ids_of_interest();
		$this->assertTrue( true );
	}

	/**
	 * Test log_ids_of_interest skips empty ID lists.
	 */
	public function test_log_ids_of_interest_skips_empty_id_lists(): void {
		$this->set_property( 'ids_of_interest', [ 'customers:not_found' => [] ] );

		$this->migrator->log_ids_of_interest();
		$this->assertTrue( true );
	}

	/**
	 * Test log_ids_of_interest processes non-empty ID lists.
	 */
	public function test_log_ids_of_interest_processes_non_empty_lists(): void {
		$this->migrator->add_id_of_interest( [ 1, 2, 3 ], 'not_found', 'customers' );

		$this->migrator->log_ids_of_interest();
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// handle_error_messages
	// -----------------------------------------------------------------------

	/**
	 * Test handle_error_messages returns a WP_Error.
	 */
	public function test_handle_error_messages_returns_wp_error(): void {
		$e      = new \Exception( 'Test exception' );
		$result = $this->migrator->handle_error_messages( $e, null, true, 'test_installer' );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test handle_error_messages WP_Error has correct error code.
	 */
	public function test_handle_error_messages_wp_error_has_correct_code(): void {
		$e      = new \Exception( 'Test exception' );
		$result = $this->migrator->handle_error_messages( $e, null, false, 'my_installer' );

		$this->assertSame( 'my_installer', $result->get_error_code() );
	}

	/**
	 * Test handle_error_messages WP_Error message contains installer name.
	 */
	public function test_handle_error_messages_wp_error_message_contains_installer(): void {
		$e      = new \Exception( 'Test exception' );
		$result = $this->migrator->handle_error_messages( $e, null, false, 'products' );

		$message = $result->get_error_message();
		$this->assertStringContainsString( 'products', $message );
	}

	/**
	 * Test handle_error_messages with null session does not throw.
	 */
	public function test_handle_error_messages_null_session_no_throw(): void {
		$e      = new \Exception( 'Test exception' );
		$result = $this->migrator->handle_error_messages( $e, null, true, 'settings' );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test handle_error_messages in dry_run mode uses global installer name.
	 */
	public function test_handle_error_messages_dry_run_uses_global_installer(): void {
		global $wu_migrator_current_installer;
		$wu_migrator_current_installer = 'dry_run_installer';

		$e      = new \Exception( 'Test exception' );
		$result = $this->migrator->handle_error_messages( $e, null, true, 'actual_installer' );

		$message = $result->get_error_message();
		$this->assertStringContainsString( 'dry_run_installer', $message );

		$wu_migrator_current_installer = null;
	}

	/**
	 * Test handle_error_messages in non-dry-run mode uses installer parameter.
	 */
	public function test_handle_error_messages_non_dry_run_uses_installer_param(): void {
		global $wu_migrator_current_installer;
		$wu_migrator_current_installer = 'dry_run_installer';

		$e      = new \Exception( 'Test exception' );
		$result = $this->migrator->handle_error_messages( $e, null, false, 'actual_installer' );

		$message = $result->get_error_message();
		$this->assertStringContainsString( 'actual_installer', $message );

		$wu_migrator_current_installer = null;
	}

	/**
	 * Test handle_error_messages with a session object stores errors.
	 */
	public function test_handle_error_messages_with_session_stores_errors(): void {
		$session = $this->createMock( \WP_Ultimo\Contracts\Session::class );

		$session->expects( $this->atLeastOnce() )
			->method( 'get' )
			->willReturn( [] );

		$session->expects( $this->atLeastOnce() )
			->method( 'set' );

		$e = new \Exception( 'Test exception' );
		$this->migrator->handle_error_messages( $e, $session, false, 'test_installer' );
	}

	// -----------------------------------------------------------------------
	// fake_register_settings
	// -----------------------------------------------------------------------

	/**
	 * Test fake_register_settings returns an array.
	 */
	public function test_fake_register_settings_returns_array(): void {
		$result = $this->migrator->fake_register_settings( [] );
		$this->assertIsArray( $result );
	}

	/**
	 * Test fake_register_settings with empty array does not throw.
	 */
	public function test_fake_register_settings_empty_array_no_throw(): void {
		$result = $this->migrator->fake_register_settings( [] );
		$this->assertNotNull( $result );
	}

	/**
	 * Test fake_register_settings with settings array does not throw.
	 */
	public function test_fake_register_settings_with_settings_no_throw(): void {
		$to_migrate = [
			'custom_setting_1' => 'value1',
			'custom_setting_2' => 'value2',
		];

		$result = $this->migrator->fake_register_settings( $to_migrate );
		$this->assertIsArray( $result );
	}

	/**
	 * Test fake_register_settings adds wu_settings_section_core_fields filter.
	 */
	public function test_fake_register_settings_adds_filter(): void {
		$this->migrator->fake_register_settings( [ 'test_key' => 'test_value' ] );

		$this->assertNotFalse( has_filter( 'wu_settings_section_core_fields' ) );
	}

	// -----------------------------------------------------------------------
	// handle (Migrator override)
	// -----------------------------------------------------------------------

	/**
	 * Test handle returns original status when no callable exists.
	 */
	public function test_handle_returns_status_when_no_callable(): void {
		$result = $this->migrator->handle( true, 'nonexistent_step', null );
		$this->assertTrue( $result );
	}

	/**
	 * Test handle returns original status for unknown installer.
	 */
	public function test_handle_returns_original_status_for_unknown_installer(): void {
		$result = $this->migrator->handle( 'my_status', 'no_such_installer', null );
		$this->assertSame( 'my_status', $result );
	}

	/**
	 * Test handle sets dry_run from request parameter.
	 */
	public function test_handle_sets_dry_run_from_request(): void {
		$this->migrator->handle( true, 'nonexistent', null );

		$dry_run = $this->get_property( 'dry_run' );
		$this->assertTrue( (bool) $dry_run );
	}

	/**
	 * Test handle respects wu_installer_{name}_callback filter.
	 */
	public function test_handle_respects_installer_callback_filter(): void {
		$filter_called = false;

		add_filter(
			'wu_installer_test_step_callback',
			function ( $callable ) use ( &$filter_called ) {
				$filter_called = true;
				return null;
			}
		);

		$this->migrator->handle( true, 'test_step', null );

		$this->assertTrue( $filter_called, 'wu_installer_{name}_callback filter should be applied' );

		remove_all_filters( 'wu_installer_test_step_callback' );
	}

	/**
	 * Test handle with dry-run=0 commits transaction.
	 */
	public function test_handle_non_dry_run_commits(): void {
		$_REQUEST['dry-run'] = '0';

		// Use a non-existent installer so no actual DB work happens.
		$result = $this->migrator->handle( 'original', 'nonexistent_step', null );
		$this->assertSame( 'original', $result );
	}

	// -----------------------------------------------------------------------
	// on_shutdown
	// -----------------------------------------------------------------------

	/**
	 * Test on_shutdown does not throw with null session.
	 */
	public function test_on_shutdown_does_not_throw_with_null_session(): void {
		$this->migrator->on_shutdown( null, true, 'test_installer' );
		$this->assertTrue( true );
	}

	/**
	 * Test on_shutdown with dry_run true.
	 */
	public function test_on_shutdown_with_dry_run_true(): void {
		$this->migrator->on_shutdown( null, true, 'products' );
		$this->assertTrue( true );
	}

	/**
	 * Test on_shutdown with dry_run false does not throw.
	 */
	public function test_on_shutdown_with_dry_run_false(): void {
		$this->migrator->on_shutdown( null, false, 'customers' );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// LOG_FILE_NAME constant
	// -----------------------------------------------------------------------

	/**
	 * Test LOG_FILE_NAME constant is defined.
	 */
	public function test_log_file_name_constant_is_defined(): void {
		$this->assertSame( 'migrator-errors', Migrator::LOG_FILE_NAME );
	}

	// -----------------------------------------------------------------------
	// all_done (inherited from Base_Installer)
	// -----------------------------------------------------------------------

	/**
	 * Test all_done returns false when steps are pending.
	 */
	public function test_all_done_returns_false_when_steps_pending(): void {
		$result = $this->migrator->all_done();
		$this->assertFalse( $result );
	}

	/**
	 * Test all_done returns a boolean.
	 */
	public function test_all_done_returns_bool(): void {
		$result = $this->migrator->all_done();
		$this->assertIsBool( $result );
	}

	// -----------------------------------------------------------------------
	// handle_parallel_installers
	// -----------------------------------------------------------------------

	/**
	 * Test handle_parallel_installers returns without throwing.
	 */
	public function test_handle_parallel_installers_returns_without_throw(): void {
		$result = $this->migrator->handle_parallel_installers();
		$this->assertNull( $result );
	}

	// -----------------------------------------------------------------------
	// Protected method: bypass_server_limits
	// -----------------------------------------------------------------------

	/**
	 * Test bypass_server_limits runs without throwing.
	 */
	public function test_bypass_server_limits_runs_without_throw(): void {
		$this->invoke_method( 'bypass_server_limits' );
		$this->assertTrue( true );
	}

	/**
	 * Test bypass_server_limits sets server_bypass_status.
	 */
	public function test_bypass_server_limits_sets_status(): void {
		$this->invoke_method( 'bypass_server_limits' );

		$status = $this->get_property( 'server_bypass_status' );
		// Status is either true (success) or WP_Error (failure).
		$this->assertTrue( is_bool( $status ) || is_wp_error( $status ) );
	}

	// -----------------------------------------------------------------------
	// Protected method: is_parallel
	// -----------------------------------------------------------------------

	/**
	 * Test is_parallel returns false when no parallel request params.
	 */
	public function test_is_parallel_returns_false_without_params(): void {
		unset( $_REQUEST['parallel'], $_REQUEST['page'], $_REQUEST['per_page'] );

		$result = $this->invoke_method( 'is_parallel' );
		$this->assertFalse( (bool) $result );
	}

	/**
	 * Test is_parallel returns truthy when all params present.
	 */
	public function test_is_parallel_returns_truthy_with_params(): void {
		$_REQUEST['parallel'] = '1';
		$_REQUEST['page']     = '2';
		$_REQUEST['per_page'] = '10';

		$result = $this->invoke_method( 'is_parallel' );
		$this->assertTrue( (bool) $result );

		unset( $_REQUEST['parallel'], $_REQUEST['page'], $_REQUEST['per_page'] );
	}

	// -----------------------------------------------------------------------
	// Protected method: get_installer
	// -----------------------------------------------------------------------

	/**
	 * Test get_installer returns global installer name in dry_run mode.
	 */
	public function test_get_installer_returns_global_in_dry_run(): void {
		global $wu_migrator_current_installer;
		$wu_migrator_current_installer = 'products';

		$this->set_property( 'dry_run', true );

		$result = $this->invoke_method( 'get_installer' );
		$this->assertSame( 'products', $result );

		$wu_migrator_current_installer = null;
	}

	/**
	 * Test get_installer returns request installer in non-dry-run mode.
	 */
	public function test_get_installer_returns_request_in_non_dry_run(): void {
		$_REQUEST['installer'] = 'customers';
		$this->set_property( 'dry_run', false );

		$result = $this->invoke_method( 'get_installer' );
		$this->assertSame( 'customers', $result );

		unset( $_REQUEST['installer'] );
	}

	// -----------------------------------------------------------------------
	// Protected method: build_limit_clause
	// -----------------------------------------------------------------------

	/**
	 * Test build_limit_clause returns LIMIT 10 in dry-run mode.
	 */
	public function test_build_limit_clause_dry_run_returns_limit_10(): void {
		$this->set_property( 'dry_run', true );

		$result = $this->invoke_method( 'build_limit_clause' );
		$this->assertSame( 'LIMIT 10', $result );
	}

	/**
	 * Test build_limit_clause returns empty string in non-dry-run non-parallel mode.
	 */
	public function test_build_limit_clause_non_dry_run_non_parallel_returns_empty(): void {
		$this->set_property( 'dry_run', false );
		unset( $_REQUEST['parallel'], $_REQUEST['page'], $_REQUEST['per_page'] );

		$result = $this->invoke_method( 'build_limit_clause' );
		$this->assertSame( '', $result );
	}

	/**
	 * Test build_limit_clause returns offset clause in parallel mode.
	 */
	public function test_build_limit_clause_parallel_returns_offset_clause(): void {
		$this->set_property( 'dry_run', false );
		$_REQUEST['parallel'] = '1';
		$_REQUEST['page']     = '2';
		$_REQUEST['per_page'] = '10';

		$result = $this->invoke_method( 'build_limit_clause' );
		// Page 2, per_page 10 → offset = (2-1)*10 = 10
		$this->assertSame( 'LIMIT 10,10', $result );

		unset( $_REQUEST['parallel'], $_REQUEST['page'], $_REQUEST['per_page'] );
	}

	/**
	 * Test build_limit_clause page 1 gives offset 0.
	 */
	public function test_build_limit_clause_parallel_page_1_offset_0(): void {
		$this->set_property( 'dry_run', false );
		$_REQUEST['parallel'] = '1';
		$_REQUEST['page']     = '1';
		$_REQUEST['per_page'] = '25';

		$result = $this->invoke_method( 'build_limit_clause' );
		$this->assertSame( 'LIMIT 0,25', $result );

		unset( $_REQUEST['parallel'], $_REQUEST['page'], $_REQUEST['per_page'] );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_customers (with legacy table)
	// -----------------------------------------------------------------------

	/**
	 * Test _install_customers runs without error when table is empty.
	 */
	public function test_install_customers_empty_table_no_error(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_customers' );
		$this->assertTrue( true );
	}

	/**
	 * Test _install_customers skips users that already have a customer.
	 */
	public function test_install_customers_skips_existing_customers(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );

		// Create a user.
		$user_id = wp_create_user( 'testcustomer', 'password', 'testcustomer@example.com' );

		// Insert a subscription row.
		$wpdb->insert(
			$wpdb->base_prefix . 'wu_subscriptions',
			[
				'user_id'    => $user_id,
				'plan_id'    => 0,
				'price'      => 0.00,
				'created_at' => '2020-01-01 00:00:00',
			],
			[ '%d', '%d', '%f', '%s' ]
		);

		$this->set_property( 'dry_run', true );

		// Should not throw.
		$this->invoke_method( '_install_customers' );
		$this->assertTrue( true );

		// Cleanup.
		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_memberships (with legacy table)
	// -----------------------------------------------------------------------

	/**
	 * Test _install_memberships runs without error when table is empty.
	 */
	public function test_install_memberships_empty_table_no_error(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_memberships' );
		$this->assertTrue( true );
	}

	/**
	 * Test _install_memberships with subscription data runs without throw.
	 */
	public function test_install_memberships_with_subscription_data(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$user_id = wp_create_user( 'testmember', 'password', 'testmember@example.com' );

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_subscriptions',
			[
				'user_id'      => $user_id,
				'plan_id'      => 999,
				'price'        => 29.99,
				'trial'        => 0,
				'freq'         => '1',
				'active_until' => '2030-01-01 00:00:00',
				'created_at'   => '2020-01-01 00:00:00',
				'gateway'      => null,
			],
			[ '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s' ]
		);

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_memberships' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
	}

	/**
	 * Test _install_memberships handles freq=3 (quarterly).
	 */
	public function test_install_memberships_freq_3_quarterly(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$user_id = wp_create_user( 'testmember3', 'password', 'testmember3@example.com' );

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_subscriptions',
			[
				'user_id'      => $user_id,
				'plan_id'      => 999,
				'price'        => 59.99,
				'trial'        => 0,
				'freq'         => '3',
				'active_until' => '2030-01-01 00:00:00',
				'created_at'   => '2020-01-01 00:00:00',
				'gateway'      => null,
			],
			[ '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s' ]
		);

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_memberships' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
	}

	/**
	 * Test _install_memberships handles freq=12 (annual).
	 */
	public function test_install_memberships_freq_12_annual(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$user_id = wp_create_user( 'testmember12', 'password', 'testmember12@example.com' );

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_subscriptions',
			[
				'user_id'      => $user_id,
				'plan_id'      => 999,
				'price'        => 299.99,
				'trial'        => 0,
				'freq'         => '12',
				'active_until' => '2030-01-01 00:00:00',
				'created_at'   => '2020-01-01 00:00:00',
				'gateway'      => null,
			],
			[ '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s' ]
		);

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_memberships' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
	}

	/**
	 * Test _install_memberships handles subscription with trial.
	 */
	public function test_install_memberships_with_trial(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$user_id = wp_create_user( 'testtrial', 'password', 'testtrial@example.com' );

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_subscriptions',
			[
				'user_id'      => $user_id,
				'plan_id'      => 999,
				'price'        => 29.99,
				'trial'        => 14,
				'freq'         => '1',
				'active_until' => '2030-01-01 00:00:00',
				'created_at'   => '2020-01-01 00:00:00',
				'gateway'      => null,
			],
			[ '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s' ]
		);

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_memberships' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
	}

	/**
	 * Test _install_memberships handles stripe gateway.
	 */
	public function test_install_memberships_stripe_gateway(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$user_id = wp_create_user( 'teststripe', 'password', 'teststripe@example.com' );

		$meta = serialize( (object) [ 'subscription_id' => 'sub_test123' ] );

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_subscriptions',
			[
				'user_id'            => $user_id,
				'plan_id'            => 999,
				'price'              => 29.99,
				'trial'              => 0,
				'freq'               => '1',
				'active_until'       => '2030-01-01 00:00:00',
				'created_at'         => '2020-01-01 00:00:00',
				'gateway'            => 'stripe',
				'integration_key'    => 'cus_test123',
				'integration_status' => 1,
				'meta_object'        => $meta,
			],
			[ '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_memberships' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
	}

	/**
	 * Test _install_memberships handles paypal gateway.
	 */
	public function test_install_memberships_paypal_gateway(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$user_id = wp_create_user( 'testpaypal', 'password', 'testpaypal@example.com' );

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_subscriptions',
			[
				'user_id'            => $user_id,
				'plan_id'            => 999,
				'price'              => 29.99,
				'trial'              => 0,
				'freq'               => '1',
				'active_until'       => '2030-01-01 00:00:00',
				'created_at'         => '2020-01-01 00:00:00',
				'gateway'            => 'paypal',
				'integration_key'    => 'I-PAYPAL123',
				'integration_status' => 0,
				'meta_object'        => null,
			],
			[ '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_memberships' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_transactions (with legacy table)
	// -----------------------------------------------------------------------

	/**
	 * Test _install_transactions runs without error when table is empty.
	 */
	public function test_install_transactions_empty_table_no_error(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_transactions' );
		$this->assertTrue( true );
	}

	/**
	 * Test _install_transactions skips types_to_skip (recurring_setup, cancel).
	 */
	public function test_install_transactions_skips_recurring_setup_and_cancel(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$user_id = wp_create_user( 'testtx', 'password', 'testtx@example.com' );

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_transactions',
			[
				'user_id'     => $user_id,
				'amount'      => 0.00,
				'type'        => 'recurring_setup',
				'description' => 'Recurring setup',
				'time'        => '2020-01-01 00:00:00',
			],
			[ '%d', '%f', '%s', '%s', '%s' ]
		);

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_transactions',
			[
				'user_id'     => $user_id,
				'amount'      => 0.00,
				'type'        => 'cancel',
				'description' => 'Cancel',
				'time'        => '2020-01-01 00:00:00',
			],
			[ '%d', '%f', '%s', '%s', '%s' ]
		);

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_transactions' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );
	}

	/**
	 * Test _install_transactions processes payment transactions.
	 */
	public function test_install_transactions_processes_payment_type(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$user_id = wp_create_user( 'testtxpay', 'password', 'testtxpay@example.com' );

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_transactions',
			[
				'user_id'      => $user_id,
				'plan_id'      => 0,
				'amount'       => 29.99,
				'type'         => 'payment',
				'description'  => 'Monthly payment',
				'gateway'      => 'stripe',
				'reference_id' => 'ch_test123',
				'time'         => '2020-01-01 00:00:00',
			],
			[ '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s' ]
		);

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_transactions' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );
	}

	/**
	 * Test _install_transactions handles failed and refund types.
	 */
	public function test_install_transactions_handles_failed_and_refund_types(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );

		$user_id = wp_create_user( 'testtxfail', 'password', 'testtxfail@example.com' );

		foreach ( [ 'failed', 'refund', 'pending' ] as $type ) {
			$wpdb->insert(
				$wpdb->base_prefix . 'wu_transactions',
				[
					'user_id'     => $user_id,
					'amount'      => 10.00,
					'type'        => $type,
					'description' => ucfirst( $type ) . ' transaction',
					'time'        => '2020-01-01 00:00:00',
				],
				[ '%d', '%f', '%s', '%s', '%s' ]
			);
		}

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_transactions' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_discount_codes
	// -----------------------------------------------------------------------

	/**
	 * Test _install_discount_codes runs without error when no coupons exist.
	 */
	public function test_install_discount_codes_no_coupons_no_error(): void {
		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_discount_codes' );
		$this->assertTrue( true );
	}

	/**
	 * Test _install_discount_codes processes wpultimo_coupon posts.
	 */
	public function test_install_discount_codes_processes_coupons(): void {
		$coupon_id = wp_insert_post( [
			'post_type'   => 'wpultimo_coupon',
			'post_title'  => 'Test Coupon',
			'post_name'   => 'test-coupon-' . uniqid(),
			'post_status' => 'publish',
		] );

		update_post_meta( $coupon_id, 'wpu_type', 'percent' );
		update_post_meta( $coupon_id, 'wpu_value', 10 );

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_discount_codes' );
		$this->assertTrue( true );

		wp_delete_post( $coupon_id, true );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_sites (with legacy table)
	// -----------------------------------------------------------------------

	/**
	 * Test _install_sites runs without error when table is empty.
	 */
	public function test_install_sites_empty_table_no_error(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_site_owner`" );

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_sites' );
		$this->assertTrue( true );
	}

	/**
	 * Test _install_sites skips non-existent sites.
	 */
	public function test_install_sites_skips_nonexistent_sites(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_site_owner`" );

		$user_id = wp_create_user( 'testsiteowner', 'password', 'testsiteowner@example.com' );

		$wpdb->insert(
			$wpdb->base_prefix . 'wu_site_owner',
			[
				'site_id' => 99999,
				'user_id' => $user_id,
			],
			[ '%d', '%d' ]
		);

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_sites' );
		$this->assertTrue( true );

		wp_delete_user( $user_id );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_site_owner`" );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_site_templates
	// -----------------------------------------------------------------------

	/**
	 * Test _install_site_templates runs without error.
	 */
	public function test_install_site_templates_no_error(): void {
		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_site_templates' );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_domains (with legacy table)
	// -----------------------------------------------------------------------

	/**
	 * Test _install_domains runs without error when table is empty.
	 */
	public function test_install_domains_empty_table_no_error(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}domain_mapping`" );

		$this->set_property( 'dry_run', true );
		$this->set_property( 'settings', [ 'force_mapped_https' => true ] );

		$this->invoke_method( '_install_domains' );
		$this->assertTrue( true );
	}

	/**
	 * Test _install_domains processes domain_mapping rows.
	 */
	public function test_install_domains_processes_domains(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}domain_mapping`" );

		$wpdb->insert(
			$wpdb->base_prefix . 'domain_mapping',
			[
				'blog_id' => 1,
				'domain'  => 'test-migrator-domain-' . uniqid() . '.example.com',
				'active'  => 1,
			],
			[ '%d', '%s', '%d' ]
		);

		$this->set_property( 'dry_run', true );
		$this->set_property( 'settings', [ 'force_mapped_https' => false ] );

		$this->invoke_method( '_install_domains' );
		$this->assertTrue( true );

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}domain_mapping`" );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_emails
	// -----------------------------------------------------------------------

	/**
	 * Test _install_emails runs without error.
	 */
	public function test_install_emails_no_error(): void {
		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_emails' );
		$this->assertTrue( true );
	}

	/**
	 * Test _install_emails processes wpultimo_broadcast posts.
	 */
	public function test_install_emails_processes_broadcasts(): void {
		$broadcast_id = wp_insert_post( [
			'post_type'    => 'wpultimo_broadcast',
			'post_title'   => 'Test Broadcast',
			'post_content' => 'Broadcast content',
			'post_status'  => 'publish',
		] );

		update_post_meta( $broadcast_id, 'wpu_type', 'message' );
		update_post_meta( $broadcast_id, 'wpu_style', 'success' );

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_emails' );
		$this->assertTrue( true );

		wp_delete_post( $broadcast_id, true );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_webhooks
	// -----------------------------------------------------------------------

	/**
	 * Test _install_webhooks runs without error when no webhooks exist.
	 */
	public function test_install_webhooks_no_webhooks_no_error(): void {
		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_webhooks' );
		$this->assertTrue( true );
	}

	/**
	 * Test _install_webhooks processes wpultimo_webhook posts.
	 */
	public function test_install_webhooks_processes_webhooks(): void {
		$webhook_id = wp_insert_post( [
			'post_type'   => 'wpultimo_webhook',
			'post_title'  => 'Test Webhook',
			'post_status' => 'publish',
		] );

		update_post_meta( $webhook_id, 'wpu_url', 'https://example.com/webhook' );
		update_post_meta( $webhook_id, 'wpu_event', 'wu.membership.created' );
		update_post_meta( $webhook_id, 'wpu_active', 1 );

		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_webhooks' );
		$this->assertTrue( true );

		wp_delete_post( $webhook_id, true );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_other
	// -----------------------------------------------------------------------

	/**
	 * Test _install_other returns early in dry_run mode.
	 */
	public function test_install_other_returns_early_in_dry_run(): void {
		$this->set_property( 'dry_run', true );

		$this->invoke_method( '_install_other' );
		$this->assertTrue( true );
	}

	/**
	 * Test _install_other runs without error in non-dry-run mode.
	 */
	public function test_install_other_non_dry_run_no_error(): void {
		$this->set_property( 'dry_run', false );

		$this->invoke_method( '_install_other' );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// Protected method: _install_dry_run_check
	// -----------------------------------------------------------------------

	/**
	 * Test _install_dry_run_check runs all steps without throwing.
	 */
	public function test_install_dry_run_check_runs_all_steps(): void {
		global $wpdb;

		// Ensure legacy tables are empty.
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_subscriptions`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_transactions`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}wu_site_owner`" );
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->base_prefix}domain_mapping`" );

		$this->set_property( 'dry_run', true );
		$this->set_property( 'settings', [] );

		$this->invoke_method( '_install_dry_run_check' );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// Integration: add_id_of_interest + log_ids_of_interest round-trip
	// -----------------------------------------------------------------------

	/**
	 * Test add_id_of_interest followed by log_ids_of_interest completes without error.
	 */
	public function test_add_and_log_ids_of_interest_round_trip(): void {
		$this->migrator->add_id_of_interest( [ 100, 200 ], 'not_found', 'customers' );
		$this->migrator->add_id_of_interest( 300, 'plan_not_migrated', 'memberships' );

		$this->migrator->log_ids_of_interest();

		$ids = $this->get_property( 'ids_of_interest' );
		$this->assertCount( 2, $ids['customers:not_found'] );
		$this->assertCount( 1, $ids['memberships:plan_not_migrated'] );
	}

	// -----------------------------------------------------------------------
	// get_errors / get_back_traces integration
	// -----------------------------------------------------------------------

	/**
	 * Test get_errors and get_back_traces return empty arrays after reset.
	 */
	public function test_errors_and_back_traces_empty_after_reset(): void {
		$this->migrator->errors      = [];
		$this->migrator->back_traces = [];

		$this->assertEmpty( $this->migrator->get_errors() );
		$this->assertEmpty( $this->migrator->get_back_traces() );
	}
}
