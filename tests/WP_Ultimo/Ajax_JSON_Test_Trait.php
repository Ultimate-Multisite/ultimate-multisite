<?php
/**
 * Shared helpers for testing AJAX handlers that emit JSON responses.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests;

defined('ABSPATH') || exit;

/**
 * Installs an AJAX context and die handler around wp_send_json_* calls.
 */
trait Ajax_JSON_Test_Trait {

	/**
	 * Install an AJAX die handler so wp_send_json_* does not stop PHPUnit.
	 *
	 * @return callable The installed handler.
	 */
	protected function install_ajax_json_die_handler(): callable {

		add_filter('wp_doing_ajax', '__return_true');

		$handler = function () {
			return function ($message) {
				throw new \WPAjaxDieContinueException(esc_html((string) $message));
			};
		};

		add_filter('wp_die_ajax_handler', $handler, 1);

		return $handler;
	}

	/**
	 * Remove an AJAX die handler installed by install_ajax_json_die_handler().
	 *
	 * @param callable $handler The handler returned by install_ajax_json_die_handler().
	 * @return void
	 */
	protected function remove_ajax_json_die_handler(callable $handler): void {

		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $handler, 1);
	}

	/**
	 * Capture a wp_send_json_* response from an AJAX handler.
	 *
	 * @param callable $ajax_handler The handler to run.
	 * @return array{output: string, decoded: array, exception: bool}
	 */
	protected function capture_ajax_json_response(callable $ajax_handler): array {

		$handler          = $this->install_ajax_json_die_handler();
		$exception_caught = false;

		ob_start();

		try {
			$ajax_handler();
		} catch (\WPAjaxDieContinueException $exception) {
			$exception_caught = true;
			unset($exception);
		} finally {
			$output = ob_get_clean();
			$this->remove_ajax_json_die_handler($handler);
		}

		$decoded = json_decode($output, true);

		return [
			'output'    => $output,
			'decoded'   => is_array($decoded) ? $decoded : [],
			'exception' => $exception_caught,
		];
	}
}
