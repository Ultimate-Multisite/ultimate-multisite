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
	 * Test unrelated user columns tolerate null output from earlier filters.
	 */
	public function test_add_column_content_returns_empty_string_for_null_unrelated_column(): void {

		$output = Multiple_Accounts_Compat::get_instance()->add_column_content(null, 'bbp_user_role', 123);

		$this->assertSame('', $output);
	}

	/**
	 * Test unrelated user columns preserve existing output from earlier filters.
	 */
	public function test_add_column_content_preserves_unrelated_column_output(): void {

		$output = Multiple_Accounts_Compat::get_instance()->add_column_content('Subscriber', 'role', 123);

		$this->assertSame('Subscriber', $output);
	}
}
