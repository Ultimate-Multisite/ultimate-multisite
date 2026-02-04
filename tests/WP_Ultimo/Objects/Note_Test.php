<?php

namespace WP_Ultimo\Objects;

use WP_UnitTestCase;

class Note_Test extends WP_UnitTestCase {

	/**
	 * Test constructor with valid data.
	 */
	public function test_constructor_with_valid_data(): void {

		$note = new Note([
			'text'      => 'This is a test note',
			'author_id' => 1,
			'note_id'   => 'note_123',
		]);

		$this->assertEquals('This is a test note', $note->text);
		$this->assertEquals(1, $note->author_id);
		$this->assertEquals('note_123', $note->note_id);
	}

	/**
	 * Test constructor with empty data.
	 */
	public function test_constructor_with_empty_data(): void {

		$note = new Note();

		$this->assertEquals('', $note->text);
		$this->assertEquals('', $note->author_id);
	}

	/**
	 * Test constructor filters out invalid attributes.
	 */
	public function test_constructor_filters_invalid_attributes(): void {

		$note = new Note([
			'text'            => 'Valid text',
			'invalid_field'   => 'should be ignored',
			'another_invalid' => 'also ignored',
		]);

		$this->assertEquals('Valid text', $note->text);
		$this->assertEquals('', $note->invalid_field);
	}

	/**
	 * Test attributes method sets date_created.
	 */
	public function test_attributes_sets_date_created(): void {

		$note = new Note(['text' => 'Test']);

		$this->assertNotEmpty($note->date_created);
	}

	/**
	 * Test exists returns true when note has content.
	 */
	public function test_exists_returns_true_with_content(): void {

		$note = new Note([
			'text'      => 'Some content',
			'author_id' => 1,
		]);

		$this->assertTrue($note->exists());
	}

	/**
	 * Test exists returns true for note with only date_created.
	 */
	public function test_exists_returns_true_with_date_created_only(): void {

		$note = new Note();

		// date_created is always set, so exists() should return true
		$this->assertTrue($note->exists());
	}

	/**
	 * Test magic getter returns empty string for missing attributes.
	 */
	public function test_magic_getter_returns_empty_for_missing(): void {

		$note = new Note();

		$this->assertEquals('', $note->nonexistent_field);
	}

	/**
	 * Test magic setter sets attributes.
	 */
	public function test_magic_setter(): void {

		$note = new Note();

		$note->text = 'Updated text';

		$this->assertEquals('Updated text', $note->text);
	}

	/**
	 * Test __isset checks attribute existence.
	 */
	public function test_isset_with_existing_attribute(): void {

		$note = new Note(['text' => 'Content']);

		$this->assertNotEmpty($note->text);
	}

	/**
	 * Test validate returns true for valid note.
	 */
	public function test_validate_returns_true(): void {

		$note = new Note(['text' => 'Test note']);

		$result = $note->validate();

		$this->assertTrue($result);
	}

	/**
	 * Test to_array returns populated fields.
	 */
	public function test_to_array_returns_populated_fields(): void {

		$note = new Note([
			'text'      => 'Test note',
			'author_id' => 42,
			'note_id'   => 'abc123',
		]);

		$array = $note->to_array();

		$this->assertArrayHasKey('text', $array);
		$this->assertArrayHasKey('author_id', $array);
		$this->assertArrayHasKey('note_id', $array);
		$this->assertEquals('Test note', $array['text']);
		$this->assertEquals(42, $array['author_id']);
	}

	/**
	 * Test to_array with labels.
	 */
	public function test_to_array_with_labels(): void {

		$note = new Note([
			'text'      => 'Test note',
			'author_id' => 42,
		]);

		$array = $note->to_array(true);

		// Labels should use field title instead of key
		$this->assertArrayNotHasKey('text', $array);
		$this->assertArrayNotHasKey('author_id', $array);

		// Should have titles as keys
		$this->assertNotEmpty($array);
	}

	/**
	 * Test to_array excludes empty fields.
	 */
	public function test_to_array_excludes_empty_fields(): void {

		$note = new Note([
			'text' => 'Only text',
		]);

		$array = $note->to_array();

		$this->assertArrayHasKey('text', $array);
		$this->assertArrayNotHasKey('author_id', $array);
	}

	/**
	 * Test to_string returns joined string.
	 */
	public function test_to_string_returns_joined_string(): void {

		$note = new Note([
			'text'      => 'Note text',
			'author_id' => 1,
			'note_id'   => 'note_1',
		]);

		$string = $note->to_string();

		$this->assertStringContainsString('Note text', $string);
	}

	/**
	 * Test to_string with custom delimiter.
	 */
	public function test_to_string_with_custom_delimiter(): void {

		$note = new Note([
			'text'      => 'Note text',
			'author_id' => 1,
		]);

		$string = $note->to_string(', ');

		$this->assertStringContainsString(', ', $string);
	}

	/**
	 * Test fields returns expected field keys.
	 */
	public function test_fields_returns_expected_keys(): void {

		$fields = Note::fields();

		$this->assertArrayHasKey('text', $fields);
		$this->assertArrayHasKey('author_id', $fields);
		$this->assertArrayHasKey('note_id', $fields);
	}

	/**
	 * Test fields returns arrays with type and title.
	 */
	public function test_fields_structure(): void {

		$fields = Note::fields();

		foreach ($fields as $field) {
			$this->assertArrayHasKey('type', $field);
			$this->assertArrayHasKey('title', $field);
		}
	}
}
