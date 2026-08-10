<?php
/**
 * Tests for the Multiple_Accounts_Compat class.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Compat;

defined('ABSPATH') || exit;

use WP_UnitTestCase;

/**
 * Test multiple accounts compatibility fixes.
 */
class Multiple_Accounts_Compat_Test extends WP_UnitTestCase {

	/**
	 * Test user query filter tolerates missing wpdb function call context.
	 */
	public function test_fix_user_query_tolerates_null_func_call(): void {

		global $wpdb;

		$func_call = $wpdb->func_call ?? null;

		$wpdb->func_call = null;

		$query = 'SELECT * FROM wp_users';

		try {
			$this->assertSame($query, \WP_Ultimo\Compat\Multiple_Accounts_Compat::get_instance()->fix_user_query($query));
		} finally {
			$wpdb->func_call = $func_call;
		}
	}

	/**
	 * Test unrelated user columns tolerate null output from earlier filters.
	 */
	public function test_add_column_content_returns_empty_string_for_null_unrelated_column(): void {

		$output = \WP_Ultimo\Compat\Multiple_Accounts_Compat::get_instance()->add_column_content(null, 'bbp_user_role', 123);

		$this->assertSame('', $output);
	}

	/**
	 * Test unrelated user columns preserve existing output from earlier filters.
	 */
	public function test_add_column_content_preserves_unrelated_column_output(): void {

		$output = \WP_Ultimo\Compat\Multiple_Accounts_Compat::get_instance()->add_column_content('Subscriber', 'role', 123);

		$this->assertSame('Subscriber', $output);
	}
}
