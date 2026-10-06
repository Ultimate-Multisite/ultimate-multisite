<?php
/**
 * Tests for template functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for template functions.
 */
class Template_Functions_Test extends WP_UnitTestCase {

	use \WP_Ultimo\Tests\Ajax_JSON_Test_Trait;

	/**
	 * Template arguments must not replace the resolved include path.
	 */
	public function test_template_arguments_cannot_override_include_path(): void {

		$attributes = [
			'steps'        => [],
			'current_step' => '',
		];
		$expected   = wu_get_template_contents('checkout/templates/steps/clean', $attributes);

		foreach ([wu_path('composer.json'), 'php://filter/convert.base64-encode/resource=' . __FILE__] as $path) {
			$result = wu_get_template_contents('checkout/templates/steps/clean', $attributes + ['template_path' => $path]);

			$this->assertSame($expected, $result);
		}
	}

	/**
	 * Extracted arguments must not change the view used by override filters.
	 */
	public function test_template_arguments_preserve_view_override_context(): void {

		$view     = 'checkout/templates/steps/clean';
		$override = function ($path, $requested_view, $default_view) use ($view) {
			$this->assertSame($view, $requested_view);
			$this->assertFalse($default_view);

			return wu_path('views/checkout/templates/steps/clean.php');
		};

		add_filter('wu_view_override', $override, 10, 3);

		try {
			$result = wu_get_template_contents($view, [
				'steps'        => [],
				'current_step' => '',
				'view'         => 'checkout/attacker',
				'default_view' => 'checkout/attacker-fallback',
			]);

			$this->assertStringContainsString('wu-clean-steps', $result);
		} finally {
			remove_filter('wu_view_override', $override, 10);
		}
	}

	/**
	 * The caller's fallback must remain authoritative.
	 */
	public function test_template_arguments_cannot_override_fallback_view(): void {

		$result = wu_get_template_contents('nonexistent/view', [
			'steps'        => [],
			'current_step' => '',
			'default_view' => 'nonexistent/attacker-fallback',
		], 'checkout/templates/steps/clean');

		$this->assertStringContainsString('wu-clean-steps', $result);
	}

	/**
	 * Nested template data remains available without becoming control variables.
	 */
	public function test_nested_args_remain_available_without_overriding_include_path(): void {

		$result = wu_get_template_contents('base/responsive-table-row', [
			'args'          => [
				'id'            => 'safe-row',
				'title'         => 'Preserved nested arguments',
				'url'           => '#',
				'status'        => '',
				'image'         => '',
				'template_path' => 'php://filter/convert.base64-encode/resource=' . __FILE__,
			],
			'first_row'     => [],
			'second_row'    => [],
			'template_path' => wu_path('composer.json'),
		]);

		$this->assertStringContainsString('Preserved nested arguments', $result);
		$this->assertStringNotContainsString('PD9waHA', $result);
	}

	/**
	 * The public field-template endpoint must render HTML, not local source.
	 */
	public function test_public_field_template_does_not_disclose_local_files(): void {

		$request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve request state for a nonce-free endpoint regression test.
		$user_id = get_current_user_id();

		wp_set_current_user(0);

		$_REQUEST = [
			'template'   => 'steps/clean',
			'attributes' => [
				'steps'         => [],
				'current_step'  => '',
				'template_path' => 'php://filter/convert.base64-encode/resource=' . __FILE__,
			],
		];

		try {
			$this->assertFalse(is_user_logged_in());
			$this->assertNotFalse(has_action('wu_ajax_nopriv_wu_render_field_template'));

			$response = $this->capture_ajax_json_response(function () {
				do_action('wu_ajax_nopriv_wu_render_field_template');
			});

			$this->assertTrue($response['exception']);
			$this->assertTrue($response['decoded']['success']);
			$this->assertStringContainsString('wu-clean-steps', $response['decoded']['data']['html']);
			$this->assertStringNotContainsString('PD9waHA', $response['decoded']['data']['html']);
		} finally {
			$_REQUEST = $request;
			wp_set_current_user($user_id);
		}
	}

	/**
	 * Test wu_get_template_contents returns string.
	 */
	public function test_wu_get_template_contents_returns_string(): void {

		// Use a view that exists in the plugin.
		$result = wu_get_template_contents('base/empty-state', [
			'message'                  => 'Test message',
			'sub_message'              => 'Sub message',
			'link_label'               => 'Go Back',
			'link_url'                 => '#',
			'link_classes'             => '',
			'link_icon'                => '',
			'display_background_image' => false,
		]);

		$this->assertIsString($result);
	}

	/**
	 * Test wu_get_template_contents with nonexistent view and default.
	 */
	public function test_wu_get_template_contents_with_default_view(): void {

		$result = wu_get_template_contents('nonexistent/view', [
			'message'                  => 'Fallback',
			'sub_message'              => 'Fallback sub',
			'link_label'               => 'Back',
			'link_url'                 => '#',
			'link_classes'             => '',
			'link_icon'                => '',
			'display_background_image' => false,
		], 'base/empty-state');

		// Should fall back to the default view.
		$this->assertIsString($result);
	}

	/**
	 * Test wp_ultimo_render_vars filter is applied.
	 */
	public function test_render_vars_filter_applied(): void {

		$filter_called = false;

		add_filter(
			'wp_ultimo_render_vars',
			function ($args) use (&$filter_called) {
				$filter_called = true;
				return $args;
			}
		);

		wu_get_template_contents('base/empty-state', [
			'message'                  => 'Test',
			'sub_message'              => 'Test',
			'link_label'               => 'Back',
			'link_url'                 => '#',
			'link_classes'             => '',
			'link_icon'                => '',
			'display_background_image' => false,
		]);

		$this->assertTrue($filter_called);

		remove_all_filters('wp_ultimo_render_vars');
	}
}
