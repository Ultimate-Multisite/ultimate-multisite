<?php
/**
 * Tests for Base_Model class.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Models;

use WP_UnitTestCase;

/**
 * Test class for Base_Model in inc/models/class-base-model.php.
 *
 * Uses Customer as a concrete subclass since Base_Model is abstract.
 */
class Base_Model_Test extends WP_UnitTestCase {

	/**
	 * Customer instance used as a concrete Base_Model.
	 *
	 * @var Customer
	 */
	protected $model;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->model = new Customer();
	}

	/**
	 * Test constructor sets model name from class short name.
	 */
	public function test_constructor_sets_model_name(): void {

		$this->assertSame('customer', $this->model->model);
	}

	/**
	 * Test constructor accepts array and sets attributes.
	 */
	public function test_constructor_accepts_array(): void {

		$user_id  = self::factory()->user->create();
		$customer = new Customer(['user_id' => $user_id]);

		$this->assertSame($user_id, $customer->get_user_id());
	}

	/**
	 * Test constructor accepts stdClass and sets attributes.
	 */
	public function test_constructor_accepts_stdclass(): void {

		$user_id = self::factory()->user->create();
		$obj     = new \stdClass();
		$obj->user_id = $user_id;

		$customer = new Customer($obj);

		$this->assertSame($user_id, $customer->get_user_id());
	}

	/**
	 * Test get_id returns 0 for unsaved model.
	 */
	public function test_get_id_returns_zero_for_unsaved(): void {

		$this->assertSame(0, $this->model->get_id());
	}

	/**
	 * Test exists returns false for unsaved model.
	 */
	public function test_exists_returns_false_for_unsaved(): void {

		$this->assertFalse($this->model->exists());
	}

	/**
	 * Test exists returns true after save.
	 */
	public function test_exists_returns_true_after_save(): void {

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);
		$this->assertTrue($customer->exists());
	}

	/**
	 * Test get_slug and set_slug round-trip.
	 */
	public function test_slug_getter_setter(): void {

		$this->model->set_slug('test-slug');

		$this->assertSame('test-slug', $this->model->get_slug());
	}

	/**
	 * Test get_hash returns a non-empty string for a saved model.
	 */
	public function test_get_hash_returns_string_for_saved_model(): void {

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		$hash = $customer->get_hash('id');

		$this->assertIsString($hash);
		$this->assertNotEmpty($hash);
	}

	/**
	 * Test get_hash returns false for non-numeric field.
	 */
	public function test_get_hash_returns_false_for_non_numeric_field(): void {

		$this->setExpectedIncorrectUsage('WP_Ultimo\Models\Base_Model::get_hash');

		$user_id  = self::factory()->user->create(['user_email' => 'hash@example.com']);
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		// email_verification is a string field — should return false.
		$result = $customer->get_hash('email_verification');

		$this->assertFalse($result);
	}

	/**
	 * Test attributes() sets properties via setters.
	 */
	public function test_attributes_sets_properties_via_setters(): void {

		$user_id = self::factory()->user->create();

		$this->model->attributes(['user_id' => $user_id]);

		$this->assertSame($user_id, $this->model->get_user_id());
	}

	/**
	 * Test attributes() merges meta arrays.
	 */
	public function test_attributes_merges_meta_arrays(): void {

		$this->model->meta = ['existing_key' => 'existing_value'];

		$this->model->attributes(['meta' => ['new_key' => 'new_value']]);

		$this->assertArrayHasKey('existing_key', $this->model->meta);
		$this->assertArrayHasKey('new_key', $this->model->meta);
	}

	/**
	 * Test validation_rules returns array.
	 */
	public function test_validation_rules_returns_array(): void {

		$rules = $this->model->validation_rules();

		$this->assertIsArray($rules);
	}

	/**
	 * Test validate returns true when skip_validation is set.
	 */
	public function test_validate_returns_true_when_skip_validation(): void {

		$this->model->set_skip_validation(true);

		$result = $this->model->validate();

		$this->assertTrue($result);
	}

	/**
	 * Test to_array returns array without internal properties.
	 */
	public function test_to_array_excludes_internal_properties(): void {

		$array = $this->model->to_array();

		$this->assertIsArray($array);
		$this->assertArrayNotHasKey('query_class', $array);
		$this->assertArrayNotHasKey('skip_validation', $array);
		$this->assertArrayNotHasKey('meta', $array);
		$this->assertArrayNotHasKey('meta_fields', $array);
		$this->assertArrayNotHasKey('_original', $array);
		$this->assertArrayNotHasKey('_mappings', $array);
		$this->assertArrayNotHasKey('_mocked', $array);
	}

	/**
	 * Test to_array includes id field.
	 */
	public function test_to_array_includes_id(): void {

		$array = $this->model->to_array();

		$this->assertArrayHasKey('id', $array);
	}

	/**
	 * Test to_search_results returns array by default.
	 */
	public function test_to_search_results_returns_array(): void {

		$result = $this->model->to_search_results();

		$this->assertIsArray($result);
	}

	/**
	 * Test jsonSerialize returns array.
	 */
	public function test_json_serialize_returns_array(): void {

		$result = $this->model->jsonSerialize();

		$this->assertIsArray($result);
	}

	/**
	 * Test get_date_created returns a valid date string.
	 */
	public function test_get_date_created_returns_valid_date(): void {

		$this->model->set_date_created('2024-01-15 10:00:00');

		$this->assertSame('2024-01-15 10:00:00', $this->model->get_date_created());
	}

	/**
	 * Test get_date_created returns current time for invalid date.
	 */
	public function test_get_date_created_returns_current_time_for_invalid_date(): void {

		$this->model->set_date_created('invalid-date');

		$result = $this->model->get_date_created();

		$this->assertIsString($result);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
	}

	/**
	 * Test get_date_modified returns a valid date string.
	 */
	public function test_get_date_modified_returns_valid_date(): void {

		$this->model->set_date_modified('2024-06-01 12:00:00');

		$this->assertSame('2024-06-01 12:00:00', $this->model->get_date_modified());
	}

	/**
	 * Test set_migrated_from_id and get_migrated_from_id round-trip.
	 */
	public function test_migrated_from_id_getter_setter(): void {

		$this->model->set_migrated_from_id(99);

		$this->assertSame(99, $this->model->get_migrated_from_id());
	}

	/**
	 * Test is_migrated returns false when migrated_from_id is not set.
	 */
	public function test_is_migrated_returns_false_when_not_set(): void {

		$this->assertFalse($this->model->is_migrated());
	}

	/**
	 * Test is_migrated returns true when migrated_from_id is set.
	 */
	public function test_is_migrated_returns_true_when_set(): void {

		$this->model->set_migrated_from_id(5);

		$this->assertTrue($this->model->is_migrated());
	}

	/**
	 * Test get_by_id returns false for non-existent ID.
	 */
	public function test_get_by_id_returns_false_for_nonexistent(): void {

		$result = Customer::get_by_id(999999);

		$this->assertFalse($result);
	}

	/**
	 * Test get_by_id returns false for empty ID.
	 */
	public function test_get_by_id_returns_false_for_empty_id(): void {

		$result = Customer::get_by_id(0);

		$this->assertFalse($result);
	}

	/**
	 * Test get_by returns false for non-existent value.
	 */
	public function test_get_by_returns_false_for_nonexistent(): void {

		$result = Customer::get_by('user_id', 999999);

		$this->assertFalse($result);
	}

	/**
	 * Test get_all returns array.
	 */
	public function test_get_all_returns_array(): void {

		$result = Customer::get_all();

		$this->assertIsArray($result);
	}

	/**
	 * Test query returns array.
	 */
	public function test_query_returns_array(): void {

		$result = Customer::query([]);

		$this->assertIsArray($result);
	}

	/**
	 * Test get_items returns array.
	 */
	public function test_get_items_returns_array(): void {

		$result = Customer::get_items([]);

		$this->assertIsArray($result);
	}

	/**
	 * Test get_items_as_array returns array of arrays.
	 */
	public function test_get_items_as_array_returns_array_of_arrays(): void {

		$user_id = self::factory()->user->create();

		wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$result = Customer::get_items_as_array([]);

		$this->assertIsArray($result);

		if (! empty($result)) {
			$this->assertIsArray($result[0]);
		}
	}

	/**
	 * Test delete returns WP_Error for unsaved model.
	 */
	public function test_delete_returns_wp_error_for_unsaved_model(): void {

		$result = $this->model->delete();

		$this->assertWPError($result);
	}

	/**
	 * Test set_skip_validation sets the flag.
	 */
	public function test_set_skip_validation_sets_flag(): void {

		$this->model->set_skip_validation(true);

		// Validate should return true when skip_validation is true.
		$result = $this->model->validate();

		$this->assertTrue($result);
	}

	/**
	 * Test _get_original returns null before attributes are set.
	 */
	public function test_get_original_returns_null_before_attributes_set(): void {

		$fresh = new Customer();

		$this->assertNull($fresh->_get_original());
	}

	/**
	 * Test _get_original returns array after attributes are set.
	 */
	public function test_get_original_returns_array_after_attributes_set(): void {

		$user_id = self::factory()->user->create();

		$customer = new Customer(['user_id' => $user_id]);

		$original = $customer->_get_original();

		$this->assertIsArray($original);
	}

	/**
	 * Test duplicate creates a new model with id 0.
	 */
	public function test_duplicate_creates_model_with_zero_id(): void {

		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);
		$this->assertGreaterThan(0, $customer->get_id());

		$duplicate = $customer->duplicate();

		$this->assertSame(0, $duplicate->get_id());
	}

	/**
	 * Test get_meta returns default when meta not available.
	 */
	public function test_get_meta_returns_default_when_not_available(): void {

		// Unsaved model — meta not available.
		$result = $this->model->get_meta('nonexistent_key', 'default_val');

		$this->assertSame('default_val', $result);
	}

	/**
	 * Test update_meta returns false when meta not available.
	 */
	public function test_update_meta_returns_false_when_not_available(): void {

		$result = $this->model->update_meta('some_key', 'some_value');

		$this->assertFalse($result);
	}

	/**
	 * Test delete_meta returns false when meta not available.
	 */
	public function test_delete_meta_returns_false_when_not_available(): void {

		$result = $this->model->delete_meta('some_key');

		$this->assertFalse($result);
	}

	/**
	 * Test update_meta_batch returns false when meta not available.
	 */
	public function test_update_meta_batch_returns_false_when_not_available(): void {

		$result = $this->model->update_meta_batch(['key' => 'value']);

		$this->assertFalse($result);
	}

	/**
	 * Test get_schema returns array.
	 */
	public function test_get_schema_returns_array(): void {

		$schema = Customer::get_schema();

		$this->assertIsArray($schema);
		$this->assertNotEmpty($schema);
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
