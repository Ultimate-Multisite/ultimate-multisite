<?php
/**
 * Tests for the Notable trait.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Models\Traits
 * @since 2.0.0
 */

namespace WP_Ultimo\Models\Traits;

use WP_UnitTestCase;
use WP_Ultimo\Models\Customer;

/**
 * Test class for the Notable trait (trait-notable.php).
 *
 * Uses Customer as the concrete class that uses this trait.
 */
class Trait_Notable_Test extends WP_UnitTestCase {

	/**
	 * Test Customer uses the Notable trait.
	 */
	public function test_customer_uses_notable_trait(): void {

		$traits = class_uses_recursive(Customer::class);

		$this->assertContains(Notable::class, $traits);
	}

	/**
	 * Test get_notes returns null for unsaved model (meta not available).
	 */
	public function test_get_notes_returns_null_for_unsaved_model(): void {

		$customer = new Customer();

		$notes = $customer->get_notes();

		// Unsaved model has no meta access; notes property starts as null.
		$this->assertNull($notes);
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
	 * Test add_note returns WP_Error for invalid note data.
	 */
	public function test_add_note_returns_wp_error_for_invalid_note(): void {

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		// Empty note should fail validation.
		$result = $customer->add_note([]);

		// Should return WP_Error or false (validation failure).
		$this->assertTrue(is_wp_error($result) || false === $result || 0 === $result);
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
	 * Test delete_note returns false when note does not exist.
	 */
	public function test_delete_note_returns_false_for_nonexistent_note(): void {

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		$result = $customer->delete_note('nonexistent-note-id-xyz');

		$this->assertFalse($result);
	}

	/**
	 * Test get_notes caches result after first call.
	 */
	public function test_get_notes_caches_result(): void {

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		$first  = $customer->get_notes();
		$second = $customer->get_notes();

		$this->assertSame($first, $second);
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
