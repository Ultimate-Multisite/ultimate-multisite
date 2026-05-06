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
}
