<?php
/**
 * Unit tests for Form_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Form_Manager;

class Form_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Form_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	/**
	 * Test register_form and get_form round-trip.
	 */
	public function test_register_and_get_form(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'test_form_xyz',
			[
				'render'  => '__return_true',
				'handler' => '__return_true',
			]
		);

		$form = $manager->get_form('test_form_xyz');

		$this->assertIsArray($form);
	}

	/**
	 * Test is_form_registered returns correct values.
	 */
	public function test_is_form_registered(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'registered_form_xyz',
			[
				'render'  => '__return_true',
				'handler' => '__return_true',
			]
		);

		$this->assertTrue($manager->is_form_registered('registered_form_xyz'));
		$this->assertFalse($manager->is_form_registered('nonexistent_form_xyz'));
	}

	/**
	 * Test get_registered_forms returns an array.
	 */
	public function test_get_registered_forms_returns_array(): void {

		$manager = $this->get_manager_instance();
		$forms   = $manager->get_registered_forms();

		$this->assertIsArray($forms);
	}

	/**
	 * Test get_form_url returns a string URL.
	 */
	public function test_get_form_url_returns_url(): void {

		$manager = $this->get_manager_instance();

		$manager->register_form(
			'url_test_form',
			[
				'render'  => '__return_true',
				'handler' => '__return_true',
			]
		);

		$url = $manager->get_form_url('url_test_form');

		$this->assertIsString($url);
		$this->assertStringContainsString('url_test_form', $url);
	}

	/**
	 * Test that handle_model_delete_form aborts when confirm is not set.
	 *
	 * handle_model_delete_form() calls wp_send_json_error() which internally
	 * calls wp_die(). In AJAX context, wp_die() uses wp_die_ajax_handler
	 * (not wp_die_handler), so we must install both filters to prevent the
	 * bare die() call from killing the PHPUnit process (GitHub issue #527).
	 *
	 * wp_send_json_error() outputs JSON before calling wp_die(), so the AJAX
	 * die handler throws WPAjaxDieContinueException (output present). We
	 * capture the output with ob_start() and verify the JSON error payload.
	 *
	 * @since 2.0.0
	 */
	public function test_handle_model_delete_form_requires_confirmation(): void {

		$manager = $this->get_manager_instance();

		// Ensure 'confirm' is not set in the request.
		unset($_REQUEST['confirm']);
		$_REQUEST['model'] = 'membership';
		$_REQUEST['id']    = '1';

		/*
		 * wp_send_json() triggers a _doing_it_wrong notice when REST_REQUEST is
		 * defined (CI environment). Declare it as expected so the test framework
		 * does not treat it as a failure. Skip when REST_REQUEST is not defined
		 * (local environment) to avoid "Failed to assert that wp_send_json
		 * triggered an incorrect usage notice" errors.
		 */
		if (defined('REST_REQUEST') && REST_REQUEST) {
			$this->setExpectedIncorrectUsage('wp_send_json');
		}

		/*
		 * Simulate AJAX context so wp_send_json_error() routes through
		 * wp_die() instead of a bare `die` statement.
		 *
		 * wp_die() in AJAX context uses wp_die_ajax_handler (not wp_die_handler).
		 * We install a handler that throws WPAjaxDieContinueException so PHPUnit
		 * can catch it instead of the process terminating.
		 */
		add_filter('wp_doing_ajax', '__return_true');
		$ajax_die_handler = function() {
			return function( $message ) {
				throw new \WPAjaxDieContinueException( (string) $message );
			};
		};
		add_filter('wp_die_ajax_handler', $ajax_die_handler, 1);

		$json_output    = '';
		$exception_caught = false;

		ob_start();

		try {
			$manager->handle_model_delete_form();
		} catch (\WPAjaxDieContinueException $e) {
			$exception_caught = true;
		}

		$json_output = ob_get_clean();

		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $ajax_die_handler, 1);
		unset($_REQUEST['model'], $_REQUEST['id']);

		$this->assertTrue($exception_caught, 'handle_model_delete_form() should have terminated via wp_die()');

		$response = json_decode($json_output, true);

		$this->assertIsArray($response, 'Response should be a JSON object');
		$this->assertFalse($response['success'], 'Response should indicate failure');
		$this->assertSame('not-confirmed', $response['data'][0]['code'], 'Error code should be not-confirmed');
	}
}
