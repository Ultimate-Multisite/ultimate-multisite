<?php
/**
 * Unit tests for Product_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Product_Manager;

class Product_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Product_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'product';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Product::class;
	}
}
