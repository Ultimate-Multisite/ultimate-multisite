<?php

namespace WP_Ultimo\Traits;

use WP_UnitTestCase;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Objects\Note;

class Notable_Test extends WP_UnitTestCase {

	/**
	 * Create a customer for testing the Notable trait.
	 *
	 * @return Customer
	 */
	protected function create_test_customer(): Customer {

		$customer = wu_create_customer([
			'user_id' => self::factory()->user->create(),
		]);

		return $customer;
	}

	/**
	 * Test get_notes returns array.
	 */
	public function test_get_notes_returns_array(): void {

		$customer = $this->create_test_customer();

		$notes = $customer->get_notes();

		$this->assertIsArray($notes);
	}

	/**
	 * Test get_notes returns empty array for new customer.
	 */
	public function test_get_notes_empty_for_new_customer(): void {

		$customer = $this->create_test_customer();

		$notes = $customer->get_notes();

		$this->assertEmpty($notes);
	}

	/**
	 * Test add_note with array data.
	 */
	public function test_add_note_with_array(): void {

		$customer = $this->create_test_customer();

		$result = $customer->add_note([
			'text'      => 'Test note content',
			'author_id' => 1,
			'note_id'   => 'note_' . uniqid(),
		]);

		$this->assertNotFalse($result);
		$this->assertNotInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test add_note with Note object.
	 */
	public function test_add_note_with_note_object(): void {

		$customer = $this->create_test_customer();

		$note = new Note([
			'text'      => 'Note from object',
			'author_id' => 1,
			'note_id'   => 'note_' . uniqid(),
		]);

		$result = $customer->add_note($note);

		$this->assertNotFalse($result);
	}

	/**
	 * Test notes can be retrieved after adding.
	 */
	public function test_notes_retrieved_after_adding(): void {

		$customer = $this->create_test_customer();

		$note_id = 'note_' . uniqid();

		$customer->add_note([
			'text'      => 'Retrievable note',
			'author_id' => 1,
			'note_id'   => $note_id,
		]);

		// Reset the cached notes
		$reflection = new \ReflectionClass($customer);
		$property   = $reflection->getProperty('notes');
		$property->setValue($customer, null);

		$notes = $customer->get_notes();

		$this->assertNotEmpty($notes);
	}

	/**
	 * Test clear_notes removes all notes.
	 */
	public function test_clear_notes(): void {

		$customer = $this->create_test_customer();

		$customer->add_note([
			'text'      => 'Note 1',
			'author_id' => 1,
			'note_id'   => 'note_1',
		]);

		$customer->add_note([
			'text'      => 'Note 2',
			'author_id' => 1,
			'note_id'   => 'note_2',
		]);

		$result = $customer->clear_notes();

		// Reset cached notes
		$reflection = new \ReflectionClass($customer);
		$property   = $reflection->getProperty('notes');
		$property->setValue($customer, null);

		$notes = $customer->get_notes();

		$this->assertEmpty($notes);
	}

	/**
	 * Test delete_note removes a specific note.
	 */
	public function test_delete_note(): void {

		$customer = $this->create_test_customer();

		$note_id = 'note_' . uniqid();

		$customer->add_note([
			'text'      => 'Note to delete',
			'author_id' => 1,
			'note_id'   => $note_id,
		]);

		// Reset cached notes
		$reflection = new \ReflectionClass($customer);
		$property   = $reflection->getProperty('notes');
		$property->setValue($customer, null);

		$result = $customer->delete_note($note_id);

		// Reset cached notes again
		$property->setValue($customer, null);

		$notes = $customer->get_notes();

		// After deletion, the specific note should be gone
		$found = false;
		foreach ($notes as $note) {
			if ($note->note_id === $note_id) {
				$found = true;
				break;
			}
		}

		$this->assertFalse($found, 'The deleted note should not be found');
	}

	/**
	 * Test delete_note returns false for non-existent note.
	 */
	public function test_delete_note_returns_false_for_nonexistent(): void {

		$customer = $this->create_test_customer();

		$result = $customer->delete_note('nonexistent_note_id');

		$this->assertFalse($result);
	}

	/**
	 * Test multiple notes can be added.
	 */
	public function test_multiple_notes(): void {

		$customer = $this->create_test_customer();

		for ($i = 0; $i < 3; $i++) {
			$customer->add_note([
				'text'      => "Note $i",
				'author_id' => 1,
				'note_id'   => 'note_' . $i . '_' . uniqid(),
			]);
		}

		// Reset cached notes
		$reflection = new \ReflectionClass($customer);
		$property   = $reflection->getProperty('notes');
		$property->setValue($customer, null);

		$notes = $customer->get_notes();

		$this->assertCount(3, $notes);
	}
}
