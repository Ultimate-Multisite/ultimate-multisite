<?php
/**
 * Unit tests for Form_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Form_Manager;

class Form_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Form_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	/**
	 * Test register_form and get_form round-trip.
	 */
	public function test_register_and_get_form(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'test_form_xyz',
			[
				'render'  => '__return_true',
				'handler' => '__return_true',
			]
		);

		$form = $manager->get_form('test_form_xyz');

		$this->assertIsArray($form);
	}

	/**
	 * Test is_form_registered returns correct values.
	 */
	public function test_is_form_registered(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'registered_form_xyz',
			[
				'render'  => '__return_true',
				'handler' => '__return_true',
			]
		);

		$this->assertTrue($manager->is_form_registered('registered_form_xyz'));
		$this->assertFalse($manager->is_form_registered('nonexistent_form_xyz'));
	}

	/**
	 * Test get_registered_forms returns an array.
	 */
	public function test_get_registered_forms_returns_array(): void {

		$manager = $this->get_manager_instance();
		$forms   = $manager->get_registered_forms();

		$this->assertIsArray($forms);
	}

	/**
	 * Test get_form_url returns a string URL.
	 */
	public function test_get_form_url_returns_url(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'url_test_form',
			[
				'render'  => '__return_true',
				'handler' => '__return_true',
			]
		);

		$url = $manager->get_form_url('url_test_form');

		$this->assertIsString($url);
		$this->assertStringContainsString('url_test_form', $url);
	}
}
