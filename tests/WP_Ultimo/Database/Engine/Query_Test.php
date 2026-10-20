<?php
/**
 * Tests for the base engine Query class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Tests\Database\Engine;

use WP_Ultimo\Database\Customers\Customer_Query;

class Query_Test extends \WP_UnitTestCase {

	/**
	 * GH#1961 / GH#1996: on a secondary network only the table prefix gains the
	 * network ID. Hook names (e.g. wu_transition_customer_email_verification)
	 * and the meta type (wu_customer, column wu_customer_id) stay shared, so
	 * manager listeners and the shared meta schema work on every network.
	 */
	public function test_secondary_network_keeps_shared_hook_prefix_and_meta_type(): void {

		global $current_site;

		$original     = $current_site;
		$fake         = clone $current_site;
		$fake->id     = 987;
		$current_site = $fake;

		try {
			$query = new Customer_Query();
		} finally {
			$current_site = $original;
		}

		$this->assertSame( 'wu_987', $query->prefix );
		$this->assertSame( 'wu', $query->get_hook_prefix() );
		$this->assertSame( 'wu_customer', $query->get_meta_type() );
	}

	/**
	 * The main network is unchanged: hooks and meta type follow the prefix.
	 */
	public function test_main_network_uses_default_prefixes(): void {

		$query = new Customer_Query();

		$this->assertSame( 'wu', $query->prefix );
		$this->assertSame( 'wu', $query->get_hook_prefix() );
		$this->assertSame( 'wu_customer', $query->get_meta_type() );
	}
}
