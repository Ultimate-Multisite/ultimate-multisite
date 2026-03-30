<?php
/**
 * Tests for the Notable trait via the Customer model.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Models\Traits;

use WP_UnitTestCase;
use WP_Ultimo\Models\Customer;

/**
 * Test class for the Notable trait.
 */
class Notable_Trait_Test extends WP_UnitTestCase {

	/**
	 * Create a saved customer for tests that need persistence.
	 */
	protected function create_saved_customer(): Customer {

		return wu_create_customer([
			'user_id' => self::factory()->user->create(),
		]);
	}

	public function test_get_notes_returns_array(): void {

		$customer = $this->create_saved_customer();

		$notes = $customer->get_notes();

		$this->assertIsArray($notes);
	}

	public function test_add_note_with_array(): void {

		$customer = $this->create_saved_customer();

		$result = $customer->add_note([
			'text'      => 'Test note content',
			'author_id' => 1,
		]);

		// add_note returns int (meta_id) on success or WP_Error on failure.
		$this->assertNotFalse($result);
		$this->assertNotInstanceOf(\WP_Error::class, $result);
	}

	public function test_clear_notes_returns_bool(): void {

		$customer = $this->create_saved_customer();

		$customer->add_note([
			'text'      => 'Note to clear',
			'author_id' => 1,
		]);

		$result = $customer->clear_notes();

		$this->assertIsBool($result);
	}

	public function test_get_notes_empty_after_clear(): void {

		$customer = $this->create_saved_customer();

		$customer->add_note([
			'text'      => 'Note to clear',
			'author_id' => 1,
		]);

		$customer->clear_notes();

		// Reset the internal cache so get_notes re-fetches.
		$reflection = new \ReflectionProperty($customer, 'notes');
		$reflection->setAccessible(true);
		$reflection->setValue($customer, null);

		$notes = $customer->get_notes();

		$this->assertEmpty($notes);
	}

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
