<?php
/**
 * Tests for danger (destructive) functions.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Functions
 * @since 2.0.11
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for danger/destructive helper functions.
 */
class Danger_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_drop_tables function exists.
	 */
	public function test_wu_drop_tables_exists(): void {

		$this->assertTrue(function_exists('wu_drop_tables'));
	}

	/**
	 * Test wu_drop_tables applies the wu_drop_tables filter.
	 *
	 * We intercept via the filter to return an empty array, preventing
	 * any actual table drops during the test run.
	 */
	public function test_wu_drop_tables_applies_filter(): void {

		$filter_called = false;

		add_filter(
			'wu_drop_tables',
			function ($tables) use (&$filter_called) {
				$filter_called = true;
				// Return empty array to prevent actual table drops.
				return [];
			}
		);

		wu_drop_tables();

		remove_all_filters('wu_drop_tables');

		$this->assertTrue($filter_called, 'wu_drop_tables filter was not applied');
	}

	/**
	 * Test wu_drop_tables applies the wu_drop_tables_except filter.
	 *
	 * We intercept wu_drop_tables to return an empty list so no tables
	 * are actually dropped, while still verifying the except filter fires.
	 */
	public function test_wu_drop_tables_applies_except_filter(): void {

		$except_filter_called = false;

		// Prevent actual drops.
		add_filter('wu_drop_tables', '__return_empty_array');

		add_filter(
			'wu_drop_tables_except',
			function ($except) use (&$except_filter_called) {
				$except_filter_called = true;
				return $except;
			}
		);

		wu_drop_tables();

		remove_all_filters('wu_drop_tables');
		remove_all_filters('wu_drop_tables_except');

		$this->assertTrue($except_filter_called, 'wu_drop_tables_except filter was not applied');
	}

	/**
	 * Test wu_drop_tables_except default list contains blogs and blogmeta.
	 */
	public function test_wu_drop_tables_except_default_contains_core_tables(): void {

		$captured_except = null;

		// Prevent actual drops.
		add_filter('wu_drop_tables', '__return_empty_array');

		add_filter(
			'wu_drop_tables_except',
			function ($except) use (&$captured_except) {
				$captured_except = $except;
				return $except;
			}
		);

		wu_drop_tables();

		remove_all_filters('wu_drop_tables');
		remove_all_filters('wu_drop_tables_except');

		$this->assertIsArray($captured_except);
		$this->assertContains('blogs', $captured_except);
		$this->assertContains('blogmeta', $captured_except);
	}

	/**
	 * Test wu_drop_tables runs without exception when table list is empty.
	 */
	public function test_wu_drop_tables_no_exception_with_empty_list(): void {

		add_filter('wu_drop_tables', '__return_empty_array');

		$exception_thrown = false;

		try {
			wu_drop_tables();
		} catch (\Exception $e) {
			$exception_thrown = true;
		}

		remove_all_filters('wu_drop_tables');

		$this->assertFalse($exception_thrown, 'wu_drop_tables threw an unexpected exception');
	}

	/**
	 * Test wu_drop_tables_except filter can add custom exclusions.
	 */
	public function test_wu_drop_tables_except_filter_can_add_exclusions(): void {

		$captured_except = null;

		// Prevent actual drops.
		add_filter('wu_drop_tables', '__return_empty_array');

		add_filter(
			'wu_drop_tables_except',
			function ($except) use (&$captured_except) {
				$except[]       = 'custom_table';
				$captured_except = $except;
				return $except;
			}
		);

		wu_drop_tables();

		remove_all_filters('wu_drop_tables');
		remove_all_filters('wu_drop_tables_except');

		$this->assertContains('custom_table', $captured_except);
	}

	/**
	 * Test Sites_Table defensively blocks uninstall (DROP TABLE wp_blogs).
	 *
	 * wp_blogs is a WordPress core table. Even if a code path bypasses
	 * wu_drop_tables()'s $except guard, the table class itself must refuse.
	 */
	public function test_sites_table_uninstall_is_noop(): void {

		$table = \WP_Ultimo\Loaders\Table_Loader::get_instance()->site_table;

		// Table must exist before and after calling uninstall.
		$this->assertTrue($table->exists(), 'wp_blogs should exist before uninstall');

		$table->uninstall();

		$this->assertTrue($table->exists(), 'wp_blogs must still exist after uninstall — core table protection failed');
	}

	/**
	 * Test Sites_Table defensively blocks drop().
	 */
	public function test_sites_table_drop_returns_false(): void {

		$table = \WP_Ultimo\Loaders\Table_Loader::get_instance()->site_table;

		$result = $table->drop();

		$this->assertFalse($result, 'Sites_Table::drop() must return false');
		$this->assertTrue($table->exists(), 'wp_blogs must still exist after drop()');
	}

	/**
	 * Test Sites_Table defensively blocks truncate().
	 */
	public function test_sites_table_truncate_returns_false(): void {

		$table = \WP_Ultimo\Loaders\Table_Loader::get_instance()->site_table;

		$result = $table->truncate();

		$this->assertFalse($result, 'Sites_Table::truncate() must return false');
	}

	/**
	 * Test Sites_Table defensively blocks delete_all().
	 */
	public function test_sites_table_delete_all_returns_false(): void {

		$table = \WP_Ultimo\Loaders\Table_Loader::get_instance()->site_table;

		$result = $table->delete_all();

		$this->assertFalse($result, 'Sites_Table::delete_all() must return false');
	}

	/**
	 * Test Sites_Meta_Table defensively blocks uninstall (DROP TABLE wp_blogmeta).
	 */
	public function test_sites_meta_table_uninstall_is_noop(): void {

		$table = \WP_Ultimo\Loaders\Table_Loader::get_instance()->sitemeta_table;

		$this->assertTrue($table->exists(), 'wp_blogmeta should exist before uninstall');

		$table->uninstall();

		$this->assertTrue($table->exists(), 'wp_blogmeta must still exist after uninstall — core table protection failed');
	}

	/**
	 * Test Sites_Meta_Table defensively blocks drop().
	 */
	public function test_sites_meta_table_drop_returns_false(): void {

		$table = \WP_Ultimo\Loaders\Table_Loader::get_instance()->sitemeta_table;

		$result = $table->drop();

		$this->assertFalse($result, 'Sites_Meta_Table::drop() must return false');
		$this->assertTrue($table->exists(), 'wp_blogmeta must still exist after drop()');
	}
}
