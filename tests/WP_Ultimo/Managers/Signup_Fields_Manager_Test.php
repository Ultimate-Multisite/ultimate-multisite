<?php
/**
 * Unit tests for Signup_Fields_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Signup_Fields_Manager;

class Signup_Fields_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Signup_Fields_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	/**
	 * Test get_field_types returns an array.
	 */
	public function test_get_field_types_returns_array(): void {

		$manager = $this->get_manager_instance();
		$types   = $manager->get_field_types();

		$this->assertIsArray($types);
		$this->assertNotEmpty($types);
	}

	/**
	 * Test get_required_fields returns an array.
	 */
	public function test_get_required_fields_returns_array(): void {

		$manager = $this->get_manager_instance();
		$fields  = $manager->get_required_fields();

		$this->assertIsArray($fields);
	}

	/**
	 * Test get_user_fields returns an array.
	 */
	public function test_get_user_fields_returns_array(): void {

		$manager = $this->get_manager_instance();
		$fields  = $manager->get_user_fields();

		$this->assertIsArray($fields);
	}

	/**
	 * Test get_site_fields returns an array.
	 */
	public function test_get_site_fields_returns_array(): void {

		$manager = $this->get_manager_instance();
		$fields  = $manager->get_site_fields();

		$this->assertIsArray($fields);
	}
}
