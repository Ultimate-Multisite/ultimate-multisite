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
	 * GH#1961: on a secondary network the BerlinDB transition hooks are
	 * network-prefixed (wu_2_transition_*). They must be re-fired under the
	 * canonical wu_transition_* name so manager listeners still run.
	 */
	public function test_network_prefixed_transition_hooks_are_bridged(): void {

		global $current_site;

		$original     = $current_site;
		$fake         = clone $current_site;
		$fake->id     = 987;
		$current_site = $fake;

		try {
			new Customer_Query();
		} finally {
			$current_site = $original;
		}

		$received = null;
		$listener = function ($old_value, $new_value, $item_id) use (&$received) {
			$received = [$old_value, $new_value, $item_id];
		};

		add_action( 'wu_transition_customer_email_verification', $listener, 10, 3 );

		do_action( 'wu_987_transition_customer_email_verification', 'new', 'pending', 42 );

		remove_action( 'wu_transition_customer_email_verification', $listener, 10 );

		$this->assertSame( ['new', 'pending', 42], $received );
	}
}
