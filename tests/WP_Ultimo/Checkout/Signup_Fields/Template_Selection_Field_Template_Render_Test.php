<?php
/**
 * Tests for template selection field template rendering.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;
use WP_Ultimo\Checkout\Signup_Fields\Field_Templates\Template_Selection\Clean_Template_Selection_Field_Template;
use WP_Ultimo\Checkout\Signup_Fields\Field_Templates\Template_Selection\Legacy_Template_Selection_Field_Template;
use WP_Ultimo\Checkout\Signup_Fields\Field_Templates\Template_Selection\Minimal_Template_Selection_Field_Template;

/**
 * Test class for template selection field template rendering.
 */
class Template_Selection_Field_Template_Render_Test extends WP_UnitTestCase {

	/**
	 * Test template selection views skip missing site IDs.
	 */
	public function test_template_selection_views_skip_missing_site_ids(): void {
		$attributes = [
			'sites'          => [
				999999,
			],
			'name'           => 'template_selection',
			'cols'           => 3,
			'categories'     => [],
			'customer_sites' => [],
			'should_display' => true,
		];

		$templates = [
			new Clean_Template_Selection_Field_Template(),
			new Minimal_Template_Selection_Field_Template(),
			new Legacy_Template_Selection_Field_Template(),
		];

		foreach ($templates as $template) {
			$html = $template->render($attributes);

			$this->assertIsString($html);
			$this->assertStringNotContainsString('999999', $html);
		}
	}
}
