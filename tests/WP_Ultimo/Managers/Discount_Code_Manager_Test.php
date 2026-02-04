<?php
/**
 * Unit tests for Discount_Code_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Discount_Code_Manager;

class Discount_Code_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Discount_Code_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'discount_code';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Discount_Code::class;
	}
}
