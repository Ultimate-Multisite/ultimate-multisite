<?php
/**
 * Tests for the Limitable trait via the Product model.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Models\Traits;

use WP_UnitTestCase;
use WP_Ultimo\Models\Product;
use WP_Ultimo\Objects\Limitations;

/**
 * Test class for the Limitable trait.
 *
 * Uses Product because it implements Limitable and is simpler to instantiate
 * than Site or Membership (no foreign key requirements).
 */
class Limitable_Trait_Test extends WP_UnitTestCase {

	/**
	 * Create a saved product for tests that need persistence.
	 */
	protected function create_saved_product(): Product {

		$product = wu_create_product([
			'name'            => 'Test Plan',
			'slug'            => 'test-plan-' . uniqid(),
			'amount'          => 9.99,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'type'            => 'plan',
			'skip_validation' => true,
		]);

		return $product;
	}

	public function test_get_limitations_returns_limitations_object(): void {

		$product = $this->create_saved_product();

		$limitations = $product->get_limitations(false);

		$this->assertInstanceOf(Limitations::class, $limitations);
	}

	public function test_has_limitations_returns_bool(): void {

		$product = $this->create_saved_product();

		$result = $product->has_limitations();

		$this->assertIsBool($result);
	}

	public function test_has_module_limitation_returns_bool(): void {

		$product = $this->create_saved_product();

		$result = $product->has_module_limitation('plugins');

		$this->assertIsBool($result);
	}

	public function test_limitations_to_merge_returns_array(): void {

		$product = $this->create_saved_product();

		$result = $product->limitations_to_merge();

		$this->assertIsArray($result);
	}

	public function test_get_applicable_product_slugs_returns_array(): void {

		$product = $this->create_saved_product();

		$result = $product->get_applicable_product_slugs();

		$this->assertIsArray($result);
	}

	public function tearDown(): void {

		$products = Product::get_all();

		if ($products) {
			foreach ($products as $product) {
				if ($product->get_id()) {
					$product->delete();
				}
			}
		}

		parent::tearDown();
	}
}
