<?php
use PHPUnit\Framework\TestCase;

final class Checkout_Editor_Assets_Test extends TestCase {

	public function test_checkout_editor_mobile_styles_are_built_into_source_and_minified_css(): void {
		$source_path = __DIR__ . '/../../assets/css/checkout-editor.css';
		$min_path    = __DIR__ . '/../../assets/css/checkout-editor.min.css';

		$this->assertFileExists($source_path, 'checkout-editor.css does not exist');
		$this->assertFileExists($min_path, 'checkout-editor.min.css does not exist');

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local fixture assets in a standalone PHPUnit test.
		$source_contents = file_get_contents($source_path);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local fixture assets in a standalone PHPUnit test.
		$min_contents = file_get_contents($min_path);

		$this->assertNotFalse($source_contents);
		$this->assertNotFalse($min_contents);

		$this->assertStringContainsString('@media screen and (max-width: 782px)', $source_contents, 'Expected mobile checkout editor styles to remain in the source CSS');
		$this->assertStringContainsString('@media screen and (max-width:782px)', $min_contents, 'Expected rebuilt checkout-editor.min.css to include the mobile media query');
		$this->assertStringContainsString('flex-direction:column', $min_contents, 'Expected rebuilt checkout-editor.min.css to include the mobile header stacking rule');
		$this->assertStringContainsString('.column-order{display:none!important}', $min_contents, 'Expected rebuilt checkout-editor.min.css to hide the ORDER column on mobile');
		$this->assertStringContainsString('tr.is-expanded td.column-slug', $min_contents, 'Expected rebuilt checkout-editor.min.css to fix the expanded-row SLUG cell layout');
		$this->assertStringContainsString('#WUB_ajaxContent,#WUB_window{width:100vw', $min_contents, 'Expected rebuilt checkout-editor.min.css to cap the wubox modal at viewport width on mobile');
		$this->assertStringContainsString('add_checkout_form_field', $min_contents, 'Expected rebuilt checkout-editor.min.css to scope the field-type grid rule to the add_checkout_form_field Vue app');
	}

	/**
	 * Production users load checkout-editor.min.css. Once the release asset has
	 * been rebuilt with the mobile rules, the admin page must not also inject the
	 * same @media block inline or users receive duplicate rules.
	 */
	public function test_checkout_editor_mobile_styles_are_not_duplicated_inline_after_min_css_rebuild(): void {
		$admin_page_path = __DIR__ . '/../../inc/admin-pages/class-checkout-form-edit-admin-page.php';

		$this->assertFileExists($admin_page_path, 'class-checkout-form-edit-admin-page.php does not exist');

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local fixture assets in a standalone PHPUnit test.
		$admin_page_contents = file_get_contents($admin_page_path);
		$this->assertNotFalse($admin_page_contents);

		$this->assertStringNotContainsString('wp_add_inline_style', $admin_page_contents, 'Checkout editor mobile rules should come from checkout-editor.min.css, not a duplicate inline block');
		$this->assertStringNotContainsString('@media screen and (max-width:782px)', $admin_page_contents, 'The mobile @media query should not be duplicated inline after the minified asset was rebuilt');
	}
}
