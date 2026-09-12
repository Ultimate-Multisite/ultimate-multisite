<?php
use PHPUnit\Framework\TestCase;

final class Wubox_Assets_Test extends TestCase {

	public function test_modal_form_errors_are_normalized_before_vue_rendering(): void {

		$source_path = __DIR__ . '/../../assets/js/wubox.js';

		$this->assertFileExists($source_path, 'wubox.js does not exist');

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local asset in a standalone PHPUnit test.
		$source_contents = file_get_contents($source_path);

		$this->assertNotFalse($source_contents);
		$this->assertStringContainsString('if (typeof normalizedErrors === "string")', $source_contents);
		$this->assertStringContainsString('normalizedErrors = [ { code: "server-error", message: normalizedErrors } ];', $source_contents);
		$this->assertStringContainsString('if (! Array.isArray(normalizedErrors) || normalizedErrors.length === 0)', $source_contents);
		$this->assertStringContainsString('errorApp.errors = normalizedErrors;', $source_contents);
	}

	public function test_unsuccessful_or_malformed_responses_stop_processing(): void {

		$source_path = __DIR__ . '/../../assets/js/wubox.js';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local asset in a standalone PHPUnit test.
		$source_contents = file_get_contents($source_path);

		$this->assertNotFalse($source_contents);
		$this->assertStringContainsString('typeof response.success !== "boolean"', $source_contents);
		$this->assertMatchesRegularExpression('/if \(! response\.success\) \{.*showFormErrors\(form, response\.data\);\s*return;/s', $source_contents);
	}
}
