<?php
/**
 * Test case for Requirements.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests;

use WP_Ultimo\Installers\Core_Installer;
use WP_Ultimo\Loaders\Table_Loader;
use WP_Ultimo\Requirements;

/**
 * Test Requirements functionality.
 */
class Requirements_Test extends \WP_UnitTestCase {

	/**
	 * Reset cached met value before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		Requirements::$met = null;
		remove_all_filters('wu_wp_cron_status_override');
		delete_site_transient('wp-ultimo-cron-test-ok');
		delete_transient('wp-ultimo-cron-test-ok');
	}

	/**
	 * Remove cron status test state after each test.
	 */
	public function tear_down(): void {
		remove_all_filters('wu_wp_cron_status_override');
		delete_site_transient('wp-ultimo-cron-test-ok');
		delete_transient('wp-ultimo-cron-test-ok');

		parent::tear_down();
	}

	/**
	 * Test PHP version constants are set.
	 */
	public function test_php_version_constants(): void {

		$this->assertIsString(Requirements::$php_version);
		$this->assertNotEmpty(Requirements::$php_version);
		$this->assertIsString(Requirements::$php_recommended_version);
		$this->assertNotEmpty(Requirements::$php_recommended_version);
	}

	/**
	 * Test WP version constants are set.
	 */
	public function test_wp_version_constants(): void {

		$this->assertIsString(Requirements::$wp_version);
		$this->assertNotEmpty(Requirements::$wp_version);
		$this->assertIsString(Requirements::$wp_recommended_version);
		$this->assertNotEmpty(Requirements::$wp_recommended_version);
	}

	/**
	 * Test check_php_version returns true for current PHP.
	 */
	public function test_check_php_version_passes(): void {

		// Current PHP should meet the minimum requirement
		$this->assertTrue(Requirements::check_php_version());
	}

	/**
	 * Test check_wp_version returns true for current WP.
	 */
	public function test_check_wp_version_passes(): void {

		$this->assertTrue(Requirements::check_wp_version());
	}

	/**
	 * Test a true cron status override bypasses native checks.
	 */
	public function test_check_wp_cron_true_override(): void {

		$native_check_called = false;
		$http_filter         = static function () use (&$native_check_called) {
			$native_check_called = true;

			return [
				'response' => [
					'code'    => 500,
					'message' => 'Cron unavailable',
				],
			];
		};

		add_filter('pre_http_request', $http_filter);
		add_filter('wu_wp_cron_status_override', '__return_true');

		$result = Requirements::check_wp_cron();

		remove_filter('pre_http_request', $http_filter);

		$this->assertFalse($native_check_called);
		$this->assertTrue($result);
	}

	/**
	 * Test a false cron status override bypasses native checks.
	 */
	public function test_check_wp_cron_false_override(): void {

		set_site_transient('wp-ultimo-cron-test-ok', 1, HOUR_IN_SECONDS);
		add_filter('wu_wp_cron_status_override', '__return_false');

		$this->assertFalse(Requirements::check_wp_cron());
	}

	/**
	 * Test a null cron status override preserves native checks.
	 */
	public function test_check_wp_cron_null_override(): void {

		$native_result = Requirements::check_wp_cron();

		delete_site_transient('wp-ultimo-cron-test-ok');
		delete_transient('wp-ultimo-cron-test-ok');

		$override_called = false;
		$override_input  = false;
		$override_filter = static function ($status) use (&$override_called, &$override_input) {
			$override_called = true;
			$override_input  = $status;

			return null;
		};

		add_filter('wu_wp_cron_status_override', $override_filter);

		try {
			$override_result = Requirements::check_wp_cron();
		} finally {
			remove_filter('wu_wp_cron_status_override', $override_filter);
		}

		$this->assertTrue($override_called);
		$this->assertNull($override_input);
		$this->assertSame($native_result, $override_result);
	}

	/**
	 * Test is_multisite returns true in test env.
	 */
	public function test_is_multisite(): void {

		// Test env is multisite
		$this->assertTrue(Requirements::is_multisite());
	}

	/**
	 * Test is_unit_test returns true in test env.
	 */
	public function test_is_unit_test(): void {

		$this->assertTrue(Requirements::is_unit_test());
	}

	/**
	 * Test is_network_active returns true in test env.
	 */
	public function test_is_network_active(): void {

		$this->assertTrue(Requirements::is_network_active());
	}

	/**
	 * Test met returns true when all requirements are satisfied.
	 */
	public function test_met_returns_true(): void {

		$this->assertTrue(Requirements::met());
	}

	/**
	 * Test met caches result.
	 */
	public function test_met_caches_result(): void {

		$result1 = Requirements::met();
		$result2 = Requirements::met();

		$this->assertSame($result1, $result2);
	}

	/**
	 * Test run_setup returns true in test env.
	 */
	public function test_run_setup_in_test_env(): void {

		$result = Requirements::run_setup();

		$this->assertTrue($result);
	}

	/**
	 * Test run_setup restores every missing custom table.
	 */
	public function test_run_setup_restores_incomplete_custom_tables(): void {

		global $wpdb;

		$original_table_loader = Table_Loader::get_instance();
		$original_installer    = Core_Installer::get_instance();
		$source_table_name     = $original_table_loader->domain_table->table_name;
		$table_name            = $source_table_name . '_requirements_test';
		$installer_called      = false;
		$table_loader          = $this->getMockBuilder(Table_Loader::class)
			->disableOriginalConstructor()
			->onlyMethods(['is_installed'])
			->getMock();
		$installer             = $this->getMockBuilder(Core_Installer::class)
			->disableOriginalConstructor()
			->onlyMethods(['_install_database_tables'])
			->getMock();
		$table_is_installed    = static function () use ($wpdb, $table_name): bool {
			$suppress_errors = $wpdb->suppress_errors(true);

			try {
				return null !== $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tests the disposable fixture's actual database state.
			} finally {
				$wpdb->suppress_errors($suppress_errors);
			}
		};

		$table_loader->method('is_installed')
			->willReturnCallback($table_is_installed);

		$installer->expects($this->once())
			->method('_install_database_tables')
			->willReturnCallback(
				function () use (&$installer_called, $wpdb, $table_name): void {
					$installer_called = true;
					$result           = $wpdb->query("CREATE TABLE `{$table_name}` (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (id))"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Creates the disposable custom-table fixture.

					$this->assertTrue($result, $wpdb->last_error);
				}
			);

		Table_Loader::$instance   = $table_loader;
		Core_Installer::$instance = $installer;

		try {
			$drop_result = $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removes a disposable table created from the registered custom-table schema.

			$this->assertNotFalse($drop_result, $wpdb->last_error);
			$this->assertFalse($table_is_installed());
			$this->assertTrue(Requirements::run_setup());
			$this->assertTrue($installer_called);
			$this->assertTrue($table_is_installed());

			$this->assertTrue(Requirements::run_setup());
		} finally {
			$wpdb->query("DROP TABLE IF EXISTS `{$table_name}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removes the disposable test fixture.

			Table_Loader::$instance   = $original_table_loader;
			Core_Installer::$instance = $original_installer;
		}
	}

	/**
	 * Test notice_unsupported_php_version outputs HTML.
	 */
	public function test_notice_unsupported_php_version_output(): void {

		ob_start();
		Requirements::notice_unsupported_php_version();
		$output = ob_get_clean();

		$this->assertStringContainsString('notice-error', $output);
		$this->assertStringContainsString(Requirements::$php_version, $output);
	}

	/**
	 * Test notice_unsupported_wp_version outputs HTML.
	 */
	public function test_notice_unsupported_wp_version_output(): void {

		ob_start();
		Requirements::notice_unsupported_wp_version();
		$output = ob_get_clean();

		$this->assertStringContainsString('notice-error', $output);
		$this->assertStringContainsString(Requirements::$wp_version, $output);
	}

	/**
	 * Test notice_not_multisite outputs HTML.
	 */
	public function test_notice_not_multisite_output(): void {

		ob_start();
		Requirements::notice_not_multisite();
		$output = ob_get_clean();

		$this->assertStringContainsString('notice-error', $output);
		$this->assertStringContainsString('multisite', strtolower($output));
	}

	/**
	 * Test notice_not_network_active outputs HTML.
	 */
	public function test_notice_not_network_active_output(): void {

		ob_start();
		Requirements::notice_not_network_active();
		$output = ob_get_clean();

		$this->assertStringContainsString('notice-error', $output);
		$this->assertStringContainsString('network', strtolower($output));
	}

	/**
	 * Test skip network active check filter.
	 */
	public function test_skip_network_active_check_filter(): void {

		add_filter('wp_ultimo_skip_network_active_check', '__return_true');

		$this->assertTrue(Requirements::is_network_active());

		remove_filter('wp_ultimo_skip_network_active_check', '__return_true');
	}
}
