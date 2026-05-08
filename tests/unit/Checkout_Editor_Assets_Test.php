<?php
use PHPUnit\Framework\TestCase;

final class Checkout_Editor_Assets_Test extends TestCase {

	public function test_checkout_editor_mobile_styles_stay_in_source_css_only(): void {
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
		$this->assertStringNotContainsString('@media screen and (max-width:782px)', $min_contents, 'Generated checkout-editor.min.css should not contain the manually inlined mobile styles from PR #1073');
		$this->assertStringNotContainsString('flex-direction:column', $min_contents, 'Generated checkout-editor.min.css should remain at its pre-PR #1073 state on feature branches');
	}

	/**
	 * Production users load checkout-editor.min.css, which is rebuilt only at
	 * release time. To ship mobile fixes immediately we inject the @media
	 * block via wp_add_inline_style() from the admin page class. Verify that
	 * registration is wired up so the rules actually reach end users.
	 */
	public function test_checkout_editor_mobile_styles_are_injected_inline_for_min_css_users(): void {
		$admin_page_path = __DIR__ . '/../../inc/admin-pages/class-checkout-form-edit-admin-page.php';

		$this->assertFileExists($admin_page_path, 'class-checkout-form-edit-admin-page.php does not exist');

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local fixture assets in a standalone PHPUnit test.
		$admin_page_contents = file_get_contents($admin_page_path);
		$this->assertNotFalse($admin_page_contents);

		$this->assertStringContainsString(
			"wp_add_inline_style(\n\t\t\t'wu-checkout-form-editor'",
			$admin_page_contents,
			'Expected register_scripts() to call wp_add_inline_style on wu-checkout-form-editor so mobile rules ship without rebuilding the .min.css'
		);
		$this->assertStringContainsString(
			'@media screen and (max-width:782px)',
			$admin_page_contents,
			'Expected the inline style block to contain the mobile @media query so production users receive the mobile checkout editor fixes'
		);
		$this->assertStringContainsString(
			'.column-order{display:none!important}',
			$admin_page_contents,
			'Expected the inline style block to hide the ORDER column on mobile'
		);
		$this->assertStringContainsString(
			'tr.is-expanded td.column-slug',
			$admin_page_contents,
			'Expected the inline style block to fix the broken expanded-row layout (vertical 1-character text wrap) for the SLUG cell'
		);
		$this->assertStringContainsString(
			'#WUB_window,#WUB_ajaxContent{width:100vw',
			$admin_page_contents,
			'Expected the inline style block to cap the wubox modal at viewport width on mobile so the "Add new Field" dialogue fits a phone'
		);
		$this->assertStringContainsString(
			'add_checkout_form_field',
			$admin_page_contents,
			'Expected the inline style block to scope the field-type grid rule to the add_checkout_form_field Vue app'
		);
		$this->assertStringContainsString(
			'.wu-w-1\/4{width:50%!important}',
			$admin_page_contents,
			'Expected the inline style block to drop the field-type tile grid from 4 columns to 2 on mobile so labels (PASSWORD, TEMPLATES, DOMAIN SELECTION) stay readable'
		);
	}
}
