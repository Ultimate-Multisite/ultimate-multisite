<?php

namespace WP_Ultimo\Models;

use WP_UnitTestCase;

/**
 * Test class for Broadcast model functionality.
 *
 * Tests broadcast creation, type validation, status handling,
 * notice types, message targets, and migration functionality.
 */
class Broadcast_Test extends WP_UnitTestCase {

	/**
	 * Test broadcast creation with valid data.
	 */
	public function test_broadcast_creation_with_valid_data(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('Test Broadcast');
		$broadcast->set_content('This is a test broadcast message');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_status('publish');
		$broadcast->set_notice_type('info');

		$this->assertEquals('Test Broadcast', $broadcast->get_title());
		$this->assertEquals("<p>This is a test broadcast message</p>\n", $broadcast->get_content());
		$this->assertEquals('broadcast_notice', $broadcast->get_type());
		$this->assertEquals('publish', $broadcast->get_status());
		$this->assertEquals('info', $broadcast->get_notice_type());
	}

	/**
	 * Test get_name returns the same as get_title.
	 */
	public function test_get_name_returns_title(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('Test Broadcast Title');

		$this->assertEquals('Test Broadcast Title', $broadcast->get_name());
		$this->assertEquals($broadcast->get_title(), $broadcast->get_name());
	}

	/**
	 * Test set_name sets the title.
	 */
	public function test_set_name_sets_title(): void {
		$broadcast = new Broadcast();
		$broadcast->set_name('Test Broadcast Name');

		$this->assertEquals('Test Broadcast Name', $broadcast->get_title());
		$this->assertEquals('Test Broadcast Name', $broadcast->get_name());
	}

	/**
	 * Test broadcast type validation.
	 */
	public function test_broadcast_type_validation(): void {
		$broadcast = new Broadcast();

		// Test valid types
		$broadcast->set_type('broadcast_email');
		$this->assertEquals('broadcast_email', $broadcast->get_type());

		$broadcast->set_type('broadcast_notice');
		$this->assertEquals('broadcast_notice', $broadcast->get_type());

		// Test invalid type defaults to broadcast_notice
		$broadcast->set_type('invalid_type');
		$this->assertEquals('broadcast_notice', $broadcast->get_type());
	}

	/**
	 * Test broadcast status validation.
	 */
	public function test_broadcast_status_validation(): void {
		$broadcast = new Broadcast();

		// Test valid statuses
		$broadcast->set_status('publish');
		$this->assertEquals('publish', $broadcast->get_status());

		$broadcast->set_status('draft');
		$this->assertEquals('draft', $broadcast->get_status());

		// Test invalid status defaults to publish
		$broadcast->set_status('invalid_status');
		$this->assertEquals('publish', $broadcast->get_status());
	}

	/**
	 * Test notice type functionality.
	 */
	public function test_notice_type_functionality(): void {
		$broadcast = new Broadcast();

		// Test setting valid notice types
		$notice_types = ['info', 'success', 'warning', 'error'];

		foreach ($notice_types as $type) {
			$broadcast->set_notice_type($type);
			$this->assertEquals($type, $broadcast->get_notice_type());
		}
	}

	/**
	 * Test default notice type when none is set.
	 */
	public function test_default_notice_type(): void {
		$broadcast = new Broadcast();

		// Default should be 'success' according to the model
		$this->assertEquals('success', $broadcast->get_notice_type());
	}

	/**
	 * Test message targets functionality.
	 */
	public function test_message_targets_functionality(): void {
		$broadcast = new Broadcast();
		$targets   = 'customers,products:1,2,3';

		$broadcast->set_message_targets($targets);
		// Message targets are stored in meta array before saving
		// Check that the meta value is set correctly
		$reflection    = new \ReflectionClass($broadcast);
		$meta_property = $reflection->getProperty('meta');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$meta_property->setAccessible(true);
		}

		$meta = $meta_property->getValue($broadcast);

		$this->assertEquals($targets, $meta['message_targets']);
	}

	/**
	 * Test migrated_from_id functionality.
	 */
	public function test_migrated_from_id_functionality(): void {
		$broadcast   = new Broadcast();
		$migrated_id = 12345;

		$broadcast->set_migrated_from_id($migrated_id);
		$this->assertEquals($migrated_id, $broadcast->get_migrated_from_id());
	}

	/**
	 * Test validation rules.
	 */
	public function test_validation_rules(): void {
		$broadcast = new Broadcast();
		$rules     = $broadcast->validation_rules();

		// Check that required validation rules exist
		$this->assertArrayHasKey('notice_type', $rules);
		$this->assertArrayHasKey('status', $rules);
		$this->assertArrayHasKey('name', $rules);
		$this->assertArrayHasKey('title', $rules);
		$this->assertArrayHasKey('content', $rules);
		$this->assertArrayHasKey('type', $rules);

		// Check specific rule patterns
		$this->assertStringContainsString('in:info,success,warning,error', $rules['notice_type']);
		$this->assertStringContainsString('default:publish', $rules['status']);
		$this->assertStringContainsString('default:title', $rules['name']);
		$this->assertStringContainsString('required', $rules['title']);
		$this->assertStringContainsString('min:2', $rules['title']);
		$this->assertStringContainsString('required', $rules['content']);
		$this->assertStringContainsString('min:3', $rules['content']);
		$this->assertStringContainsString('in:broadcast_email,broadcast_notice', $rules['type']);
		$this->assertStringContainsString('default:broadcast_notice', $rules['type']);
	}

	/**
	 * Test model property.
	 */
	public function test_model_property(): void {
		$broadcast = new Broadcast();
		$this->assertEquals('broadcast', $broadcast->model);
	}

	/**
	 * Test default type.
	 */
	public function test_default_type(): void {
		$broadcast = new Broadcast();
		// Default type should be broadcast_notice
		$this->assertEquals('broadcast_notice', $broadcast->get_type());
	}

	/**
	 * Test default status.
	 */
	public function test_default_status(): void {
		$broadcast = new Broadcast();
		// Default status should be publish
		$this->assertEquals('publish', $broadcast->get_status());
	}

	/**
	 * Test constructor with object model.
	 */
	public function test_constructor_with_object_model(): void {
		$data = [
			'title'   => 'Constructor Test',
			'content' => 'Test content from constructor',
			'type'    => 'broadcast_email',
			'status'  => 'draft',
		];

		$broadcast = new Broadcast($data);

		$this->assertEquals('Constructor Test', $broadcast->get_title());
		$this->assertEquals("<p>Test content from constructor</p>\n", $broadcast->get_content());
		$this->assertEquals('broadcast_email', $broadcast->get_type());
		$this->assertEquals('draft', $broadcast->get_status());
	}

	/**
	 * Test constructor handles migrated_from_id properly.
	 */
	public function test_constructor_handles_migrated_from_id(): void {
		// Test with migrated_from_id set
		$data_with_migration = [
			'title'            => 'Migrated Broadcast',
			'migrated_from_id' => 123,
		];

		$broadcast1 = new Broadcast($data_with_migration);
		$this->assertEquals('Migrated Broadcast', $broadcast1->get_title());

		// Test without migrated_from_id (should be unset)
		$data_without_migration = [
			'title' => 'New Broadcast',
		];

		$broadcast2 = new Broadcast($data_without_migration);
		$this->assertEquals('New Broadcast', $broadcast2->get_title());
	}

	/**
	 * Test allowed types array.
	 */
	public function test_allowed_types(): void {
		$broadcast = new Broadcast();

		// Use reflection to access protected property
		$reflection             = new \ReflectionClass($broadcast);
		$allowed_types_property = $reflection->getProperty('allowed_types');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$allowed_types_property->setAccessible(true);
		}

		$allowed_types = $allowed_types_property->getValue($broadcast);

		$this->assertEquals(['broadcast_email', 'broadcast_notice'], $allowed_types);
	}

	/**
	 * Test allowed status array.
	 */
	public function test_allowed_status(): void {
		$broadcast = new Broadcast();

		// Use reflection to access protected property
		$reflection              = new \ReflectionClass($broadcast);
		$allowed_status_property = $reflection->getProperty('allowed_status');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$allowed_status_property->setAccessible(true);
		}

		$allowed_status = $allowed_status_property->getValue($broadcast);

		$this->assertEquals(['publish', 'draft'], $allowed_status);
	}

	/**
	 * Test query class property.
	 */
	public function test_query_class(): void {
		$broadcast = new Broadcast();

		// Use reflection to access protected property
		$reflection           = new \ReflectionClass($broadcast);
		$query_class_property = $reflection->getProperty('query_class');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$query_class_property->setAccessible(true);
		}

		$query_class = $query_class_property->getValue($broadcast);

		$this->assertEquals(\WP_Ultimo\Database\Broadcasts\Broadcast_Query::class, $query_class);
	}

	/**
	 * Test that to_array() populates lazy-loaded meta properties (issue #469).
	 *
	 * migrated_from_id and notice_type are only loaded from meta when their
	 * getter is first called. Without the to_array() override they remain null.
	 */
	public function test_to_array_includes_lazy_loaded_meta_properties(): void {
		$broadcast = new \WP_Ultimo\Models\Broadcast();
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_notice_type('warning');
		$broadcast->set_migrated_from_id(99);

		$array = $broadcast->to_array();

		$this->assertIsArray($array, 'to_array() should return an array.');
		$this->assertArrayHasKey('notice_type', $array, 'to_array() must include notice_type.');
		$this->assertNotNull($array['notice_type'], 'notice_type must not be null in to_array() output.');
		$this->assertEquals('warning', $array['notice_type'], 'notice_type must match the set value.');
		$this->assertArrayHasKey('migrated_from_id', $array, 'to_array() must include migrated_from_id.');
		$this->assertNotNull($array['migrated_from_id'], 'migrated_from_id must not be null in to_array() output.');
		$this->assertEquals(99, $array['migrated_from_id'], 'migrated_from_id must match the set value.');
	}
}
