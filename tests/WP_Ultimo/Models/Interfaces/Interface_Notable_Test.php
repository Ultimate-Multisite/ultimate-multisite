<?php
/**
 * Tests for the Notable interface.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Models\Interfaces
 * @since 2.0.0
 */

namespace WP_Ultimo\Models\Interfaces;

use WP_UnitTestCase;
use WP_Ultimo\Models\Customer;

/**
 * Test class for the Notable interface (interface-notable.php).
 *
 * The interface is verified through Customer, which implements it via Trait_Notable.
 */
class Interface_Notable_Test extends WP_UnitTestCase {

	/**
	 * Test that Customer implements the Notable interface.
	 */
	public function test_customer_implements_notable_interface(): void {

		$customer = new Customer();

		$this->assertInstanceOf(Notable::class, $customer);
	}

	/**
	 * Test that Notable interface declares get_notes.
	 */
	public function test_interface_declares_get_notes(): void {

		$this->assertTrue(method_exists(Notable::class, 'get_notes'));
	}

	/**
	 * Test that Notable interface declares add_note.
	 */
	public function test_interface_declares_add_note(): void {

		$this->assertTrue(method_exists(Notable::class, 'add_note'));
	}

	/**
	 * Test that Notable interface declares clear_notes.
	 */
	public function test_interface_declares_clear_notes(): void {

		$this->assertTrue(method_exists(Notable::class, 'clear_notes'));
	}

	/**
	 * Test that Notable interface declares delete_note.
	 */
	public function test_interface_declares_delete_note(): void {

		$this->assertTrue(method_exists(Notable::class, 'delete_note'));
	}

	/**
	 * Test get_notes returns array or empty for unsaved model.
	 */
	public function test_get_notes_returns_array_or_empty_for_unsaved(): void {

		$customer = new Customer();

		// Unsaved model — meta not available, should return null or empty.
		$notes = $customer->get_notes();

		$this->assertTrue(is_array($notes) || is_null($notes));
	}

	/**
	 * Test get_notes returns array for saved model.
	 */
	public function test_get_notes_returns_array_for_saved_model(): void {

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		$notes = $customer->get_notes();

		$this->assertIsArray($notes);
	}

	/**
	 * Test clear_notes returns bool for saved model.
	 */
	public function test_clear_notes_returns_bool_for_saved_model(): void {

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		$result = $customer->clear_notes();

		$this->assertIsBool($result);
	}

	/**
	 * Test delete_note returns false when note not found.
	 */
	public function test_delete_note_returns_false_when_not_found(): void {

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		$result = $customer->delete_note('nonexistent-note-id');

		$this->assertFalse($result);
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {

		$customers = Customer::get_all();

		if ($customers) {
			foreach ($customers as $customer) {
				if ($customer->get_id()) {
					$customer->delete();
				}
			}
		}

		parent::tearDown();
	}
}
