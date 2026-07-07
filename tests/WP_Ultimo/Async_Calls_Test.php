<?php
/**
 * Tests for the Async_Calls class.
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * @group async-calls
 */
class Async_Calls_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Async_Calls::$registry = [];
	}

	public function tear_down() {
		Async_Calls::$registry = [];
		parent::tear_down();
	}

	// ------------------------------------------------------------------
	// register_listener
	// ------------------------------------------------------------------

	public function test_register_listener_adds_to_registry() {
		$callback = function () {
			return 'test';
		};

		Async_Calls::register_listener('test_id', $callback);

		$this->assertArrayHasKey('test_id', Async_Calls::$registry);
		$this->assertSame($callback, Async_Calls::$registry['test_id']['callable']);
	}

	public function test_register_listener_stores_args() {
		$callback = function ($a, $b) {
			return $a + $b;
		};

		Async_Calls::register_listener('test_args', $callback, 1, 2);

		$this->assertEquals([1, 2], Async_Calls::$registry['test_args']['args']);
	}

	public function test_register_listener_overwrites_existing() {
		$callback1 = function () {
			return 'first';
		};
		$callback2 = function () {
			return 'second';
		};

		Async_Calls::register_listener('same_id', $callback1);
		Async_Calls::register_listener('same_id', $callback2);

		$this->assertSame($callback2, Async_Calls::$registry['same_id']['callable']);
	}

	public function test_register_multiple_listeners() {
		Async_Calls::register_listener('id1', 'is_string');
		Async_Calls::register_listener('id2', 'is_array');
		Async_Calls::register_listener('id3', 'is_int');

		$this->assertCount(3, Async_Calls::$registry);
	}

	// ------------------------------------------------------------------
	// install_listeners
	// ------------------------------------------------------------------

	public function test_install_listeners_registers_ajax_actions() {
		$callback = function () {
			return 'result';
		};

		Async_Calls::register_listener('my_listener', $callback);
		Async_Calls::install_listeners();

		$this->assertTrue(has_action('wp_ajax_wu_async_call_listener_my_listener') !== false);
	}

	// ------------------------------------------------------------------
	// build_base_url
	// ------------------------------------------------------------------

	public function test_build_base_url_returns_admin_ajax_url() {
		$url = Async_Calls::build_base_url('test', ['action' => 'wu_async_call_listener_test']);

		$this->assertStringContainsString('admin-ajax.php', $url);
		$this->assertStringContainsString('action=wu_async_call_listener_test', $url);
	}

	public function test_build_base_url_includes_args() {
		$url = Async_Calls::build_base_url('test', [
			'action' => 'wu_async_call_listener_test',
			'page'   => 1,
			'custom' => 'value',
		]);

		$this->assertStringContainsString('page=1', $url);
		$this->assertStringContainsString('custom=value', $url);
	}

	// ------------------------------------------------------------------
	// build_url_list
	// ------------------------------------------------------------------

	public function test_build_url_list_returns_correct_number_of_urls() {
		$urls = Async_Calls::build_url_list('test', 100, 10);

		$this->assertCount(10, $urls);
	}

	public function test_build_url_list_handles_remainder() {
		$urls = Async_Calls::build_url_list('test', 25, 10);

		// 25 / 10 = 3 pages (ceil)
		$this->assertCount(3, $urls);
	}

	public function test_build_url_list_single_page() {
		$urls = Async_Calls::build_url_list('test', 5, 10);

		$this->assertCount(1, $urls);
	}

	public function test_build_url_list_includes_action_parameter() {
		$urls = Async_Calls::build_url_list('my_id', 10, 5);

		foreach ($urls as $url) {
			$this->assertStringContainsString('action=wu_async_call_listener_my_id', $url);
		}
	}

	public function test_build_url_list_includes_page_numbers() {
		$urls = Async_Calls::build_url_list('test', 30, 10);

		$this->assertStringContainsString('page=1', $urls[0]);
		$this->assertStringContainsString('page=2', $urls[1]);
		$this->assertStringContainsString('page=3', $urls[2]);
	}

	public function test_build_url_list_includes_per_page() {
		$urls = Async_Calls::build_url_list('test', 20, 7);

		foreach ($urls as $url) {
			$this->assertStringContainsString('per_page=7', $url);
		}
	}

	public function test_build_url_list_includes_parallel_flag() {
		$urls = Async_Calls::build_url_list('test', 10, 5);

		foreach ($urls as $url) {
			$this->assertStringContainsString('parallel=1', $url);
		}
	}

	public function test_build_url_list_includes_extra_args() {
		$urls = Async_Calls::build_url_list('test', 10, 5, ['custom_key' => 'custom_val']);

		foreach ($urls as $url) {
			$this->assertStringContainsString('custom_key=custom_val', $url);
		}
	}

	// ------------------------------------------------------------------
	// condense_results
	// ------------------------------------------------------------------

	public function test_condense_results_returns_true_when_all_success() {
		$results = [
			(object) [
				'success' => true,
				'data'    => 'ok',
			],
			(object) [
				'success' => true,
				'data'    => 'ok',
			],
		];

		$this->assertTrue(Async_Calls::condense_results($results));
	}

	public function test_condense_results_returns_failure_on_first_error() {
		$error   = (object) [
			'success' => false,
			'data'    => 'error msg',
		];
		$results = [
			(object) [
				'success' => true,
				'data'    => 'ok',
			],
			$error,
			(object) [
				'success' => true,
				'data'    => 'ok',
			],
		];

		$result = Async_Calls::condense_results($results);
		$this->assertSame($error, $result);
	}

	public function test_condense_results_returns_true_for_empty_array() {
		$this->assertTrue(Async_Calls::condense_results([]));
	}
}
