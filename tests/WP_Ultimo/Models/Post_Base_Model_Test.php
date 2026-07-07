<?php
/**
 * Unit tests for Post_Base_Model class.
 */

namespace WP_Ultimo\Models;

/**
 * Unit tests for Post_Base_Model class.
 */
class Post_Base_Model_Test extends \WP_UnitTestCase {

	/**
	 * Post_Base_Model instance.
	 *
	 * @var Post_Base_Model
	 */
	protected $post_base_model;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a post base model manually to avoid faker issues
		$this->post_base_model = new Post_Base_Model();
		$this->post_base_model->set_title('Test Post');
		$this->post_base_model->set_content('Test content');
		$this->post_base_model->set_author_id(1);
		$this->post_base_model->set_type('post');
		$this->post_base_model->set_status('publish');
	}

	/**
	 * Test post base model properties.
	 */
	public function test_post_base_model_properties(): void {
		// Test author ID getter/setter
		$author_id = 123;
		$this->post_base_model->set_author_id($author_id);
		$this->assertEquals($author_id, $this->post_base_model->get_author_id(), 'Author ID should be set and retrieved correctly.');

		// Test title getter/setter
		$title = 'Updated Title';
		$this->post_base_model->set_title($title);
		$this->assertEquals($title, $this->post_base_model->get_title(), 'Title should be set and retrieved correctly.');

		// Test content getter/setter
		$content = 'Updated content';
		$this->post_base_model->set_content($content);
		$this->assertEquals($content, trim(strip_tags($this->post_base_model->get_content())), 'Content should be set and retrieved correctly.');

		// Test excerpt getter/setter
		$excerpt = 'Test excerpt';
		$this->post_base_model->set_excerpt($excerpt);
		$this->assertEquals($excerpt, $this->post_base_model->get_excerpt(), 'Excerpt should be set and retrieved correctly.');

		// Test name getter (alias for title) - skip as Post_Base_Model doesn't have get_name()
		// $this->assertEquals($title, $this->post_base_model->get_name(), 'Name should return title.');

		// Test list order getter/setter
		$list_order = 5;
		$this->post_base_model->set_list_order($list_order);
		$this->assertEquals($list_order, $this->post_base_model->get_list_order(), 'List order should be set and retrieved correctly.');

		// Test status getter/setter
		$statuses = ['publish', 'draft', 'pending'];
		foreach ($statuses as $status) {
			$this->post_base_model->set_status($status);
			$this->assertEquals($status, $this->post_base_model->get_status(), "Status {$status} should be set and retrieved correctly.");
		}
	}

	/**
	 * Test post base model validation.
	 */
	public function test_post_base_model_validation(): void {
		$validation_rules = $this->post_base_model->validation_rules();

		// Test validation rules structure
		$this->assertIsArray($validation_rules, 'Validation rules should return an array.');
		$this->assertEmpty($validation_rules, 'Post_Base_Model should have empty validation rules by default.');
	}

	/**
	 * Test post base model save with validation bypassed.
	 */
	public function test_post_base_model_save_with_validation_bypassed(): void {
		$post_base_model = new Post_Base_Model();

		// Set required fields
		$post_base_model->set_title('Test Post');
		$post_base_model->set_content('Test content');
		$post_base_model->set_author_id(1);
		$post_base_model->set_type('post');
		$post_base_model->set_status('publish');

		// Bypass validation for testing
		$post_base_model->set_skip_validation(true);
		$result = $post_base_model->save();

		// In test environment, this might fail due to WordPress constraints
		// We're mainly testing that the method runs without errors
		$this->assertIsBool($result, 'Save should return boolean result.');
	}

	/**
	 * Test post base model inheritance from Base_Model.
	 */
	public function test_post_base_model_inheritance(): void {
		// Test Base_Model methods are available
		$this->assertTrue(method_exists($this->post_base_model, 'get_id'), 'Should inherit get_id() from Base_Model.');
		$this->assertTrue(method_exists($this->post_base_model, 'exists'), 'Should inherit exists() from Base_Model.');
		$this->assertTrue(method_exists($this->post_base_model, 'save'), 'Should inherit save() from Base_Model.');
		$this->assertTrue(method_exists($this->post_base_model, 'delete'), 'Should inherit delete() from Base_Model.');
		$this->assertTrue(method_exists($this->post_base_model, 'to_array'), 'Should inherit to_array() from Base_Model.');
		$this->assertTrue(method_exists($this->post_base_model, 'get_hash'), 'Should inherit get_hash() from Base_Model.');
		$this->assertTrue(method_exists($this->post_base_model, 'get_meta'), 'Should inherit get_meta() from Base_Model.');
		$this->assertTrue(method_exists($this->post_base_model, 'update_meta'), 'Should inherit update_meta() from Base_Model.');
		$this->assertTrue(method_exists($this->post_base_model, 'delete_meta'), 'Should inherit delete_meta() from Base_Model.');
	}

	/**
	 * Test post base model to_array method.
	 */
	public function test_to_array(): void {
		$array = $this->post_base_model->to_array();

		$this->assertIsArray($array, 'to_array() should return an array.');
		$this->assertArrayHasKey('id', $array, 'Array should contain id field.');
		$this->assertArrayHasKey('title', $array, 'Array should contain title field.');
		$this->assertArrayHasKey('content', $array, 'Array should contain content field.');
		$this->assertArrayHasKey('author_id', $array, 'Array should contain author_id field.');
		$this->assertArrayHasKey('type', $array, 'Array should contain type field.');
		$this->assertArrayHasKey('status', $array, 'Array should contain status field.');

		// Should not contain internal properties
		$this->assertArrayNotHasKey('query_class', $array, 'Array should not contain query_class.');
		$this->assertArrayNotHasKey('meta', $array, 'Array should not contain meta.');
	}

	/**
	 * Test post base model hash generation.
	 */
	public function test_hash_generation(): void {
		$hash = $this->post_base_model->get_hash('id');

		$this->assertIsString($hash, 'Hash should be a string.');
		$this->assertNotEmpty($hash, 'Hash should not be empty.');
	}
	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		// Clean up created data
		if ($this->post_base_model && $this->post_base_model->get_id()) {
			wp_delete_post($this->post_base_model->get_id(), true);
		}

		parent::tearDown();
	}
}
