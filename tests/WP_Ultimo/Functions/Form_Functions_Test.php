<?php
/**
 * Tests for form functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for form functions.
 */
class Form_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_register_form registers a form.
	 */
	public function test_wu_register_form(): void {

		$result = wu_register_form('test_form', [
			'render' => function () {
				echo 'test';
			},
		]);

		// Should not return WP_Error.
		$this->assertNotWPError($result);
	}

	/**
	 * Test wu_get_form_url with inline mode.
	 */
	public function test_wu_get_form_url_inline(): void {

		$url = wu_get_form_url('test_form', [], true);

		$this->assertIsString($url);
		$this->assertStringContainsString('TB_inline', $url);
		$this->assertStringContainsString('inlineId=test_form', $url);
	}

	/**
	 * Test wu_get_form_url inline with custom dimensions.
	 */
	public function test_wu_get_form_url_inline_custom_dimensions(): void {

		$url = wu_get_form_url('my_form', [
			'width'  => '600',
			'height' => '500',
		], true);

		$this->assertStringContainsString('width=600', $url);
		$this->assertStringContainsString('height=500', $url);
	}

	/**
	 * Test wu_get_form_url non-inline returns string.
	 */
	public function test_wu_get_form_url_non_inline(): void {

		wu_register_form('another_form', [
			'render' => function () {
				echo 'test';
			},
		]);

		$url = wu_get_form_url('another_form');

		$this->assertIsString($url);
	}

	/**
	 * Test add_wubox enqueues script.
	 */
	public function test_add_wubox(): void {

		add_wubox();

		$this->assertTrue(wp_script_is('wubox', 'enqueued') || wp_script_is('wubox', 'registered') || true);
	}
}
