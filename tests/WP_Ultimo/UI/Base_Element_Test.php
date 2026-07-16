<?php
/**
 * Tests for the Base_Element class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_Ultimo\Managers\Form_Manager;
use WP_UnitTestCase;

/**
 * Test class for Base_Element.
 */
class Base_Element_Test extends WP_UnitTestCase {

	/**
	 * Test forms are registered when an add-on initializes after plugins_loaded.
	 */
	public function test_late_loaded_element_registers_forms_immediately(): void {

		$this->assertGreaterThan(0, did_action('plugins_loaded'));

		$form_manager = Form_Manager::get_instance();
		$element      = new class() extends Base_Element {

			protected $id = 'late-form-registration-test';

			public function get_icon($context = 'block') { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				return '';
			}

			public function get_title() {
				return 'Late Form Registration Test';
			}

			public function get_description() {
				return '';
			}

			public function fields() {
				return [];
			}

			public function keywords() {
				return [];
			}

			public function defaults() {
				return [];
			}

			public function output($atts, $content = null) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				// No output needed for this test element.
			}
		};

		$element->init();

		$this->assertTrue($form_manager->is_form_registered('shortcode_late-form-registration-test'));
		$this->assertTrue($form_manager->is_form_registered('customize_late-form-registration-test'));
	}
}
