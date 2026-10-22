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
	 * Page controls must not preload all pages, including in real editors.
	 */
	public function test_page_search_fields_hydrate_only_the_saved_published_page(): void {

		$page_id = self::factory()->post->create([
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Saved target',
		]);
		$draft   = self::factory()->post->create([
			'post_type'   => 'page',
			'post_status' => 'draft',
		]);
		$cases   = [
			[My_Sites_Element::get_instance(), 'custom_manage_page'],
			[Current_Site_Element::get_instance(), 'breadcrumbs_my_sites_page'],
			[Site_Actions_Element::get_instance(), 'redirect_after_delete'],
		];

		foreach ($cases as [$element, $key]) {
			$field   = $element->fields()[ $key ];
			$options = $field['options'];

			$this->assertTrue($field['page_search']);
			$this->assertSame('page', $field['html_attr']['data-model']);
			$this->assertCount(1, $options());
			$this->assertCount(2, $options((object) ['value' => $page_id]));
			$this->assertSame('Saved target', $options((object) ['value' => $page_id])[ $page_id ]);
			$this->assertCount(1, $options((object) ['value' => $draft]));
			$this->assertCount(1, $options((object) ['value' => PHP_INT_MAX]));
		}
	}

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
