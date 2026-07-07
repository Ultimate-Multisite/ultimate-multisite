<?php
/**
 * Tests for model helper functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for model helper functions.
 */
class Model_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_cast_model_to_array with a model object.
	 */
	public function test_cast_model_to_array_with_model(): void {

		$product = wu_create_product([
			'name'            => 'Cast Test',
			'slug'            => 'cast-test-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 10.00,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		$result = wu_cast_model_to_array($product);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('name', $result);
	}

	/**
	 * Test wu_cast_model_to_array with a plain array.
	 */
	public function test_cast_model_to_array_with_array(): void {

		$input = [
			'name'  => 'Test',
			'value' => 123,
		];

		$result = wu_cast_model_to_array($input);

		$this->assertIsArray($result);
		$this->assertEquals('Test', $result['name']);
	}

	/**
	 * Test wu_models_to_options converts models to options.
	 */
	public function test_models_to_options(): void {

		$product1 = wu_create_product([
			'name'            => 'Option Plan A',
			'slug'            => 'option-plan-a-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 10.00,
			'skip_validation' => true,
		]);

		$product2 = wu_create_product([
			'name'            => 'Option Plan B',
			'slug'            => 'option-plan-b-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 20.00,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product1);
		$this->assertNotWPError($product2);

		$options = wu_models_to_options([$product1, $product2]);

		$this->assertIsArray($options);
		$this->assertCount(2, $options);
		$this->assertEquals('Option Plan A', $options[$product1->get_id()]);
		$this->assertEquals('Option Plan B', $options[$product2->get_id()]);
	}

	/**
	 * Test wu_model_get_schema returns array.
	 */
	public function test_model_get_schema(): void {

		$schema = wu_model_get_schema(\WP_Ultimo\Models\Product::class);

		$this->assertIsArray($schema);
	}

	/**
	 * Test wu_model_get_schema with nonexistent class.
	 */
	public function test_model_get_schema_nonexistent(): void {

		$schema = wu_model_get_schema('NonExistentClass');

		$this->assertIsArray($schema);
		$this->assertEmpty($schema);
	}

	/**
	 * Test wu_model_get_required_fields returns array.
	 */
	public function test_model_get_required_fields(): void {

		$fields = wu_model_get_required_fields(\WP_Ultimo\Models\Product::class);

		$this->assertIsArray($fields);
	}

	/**
	 * Test wu_model_get_required_fields with nonexistent class.
	 */
	public function test_model_get_required_fields_nonexistent(): void {

		$fields = wu_model_get_required_fields('NonExistentClass');

		$this->assertIsArray($fields);
		$this->assertEmpty($fields);
	}
}
