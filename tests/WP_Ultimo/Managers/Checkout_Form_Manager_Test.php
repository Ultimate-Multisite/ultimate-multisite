<?php
/**
 * Unit tests for Checkout_Form_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Checkout_Form_Manager;

class Checkout_Form_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Checkout_Form_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'checkout_form';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Checkout_Form::class;
	}
}
