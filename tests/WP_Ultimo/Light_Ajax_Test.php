<?php
/**
 * Tests for the Light_Ajax class.
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * @group light-ajax
 */
class Light_Ajax_Test extends WP_UnitTestCase {

	// ------------------------------------------------------------------
	// Singleton
	// ------------------------------------------------------------------

	public function test_get_instance_returns_singleton() {
		$instance = Light_Ajax::get_instance();
		$this->assertInstanceOf(Light_Ajax::class, $instance);
	}

	// ------------------------------------------------------------------
	// get_when_to_run
	// ------------------------------------------------------------------

	public function test_get_when_to_run_defaults_to_plugins_loaded() {
		$instance = Light_Ajax::get_instance();

		// No wu-when in request
		unset($_REQUEST['wu-when']);

		$result = $instance->get_when_to_run();
		$this->assertEquals('plugins_loaded', $result);
	}

	public function test_get_when_to_run_accepts_valid_hook() {
		$instance = Light_Ajax::get_instance();

		$_REQUEST['wu-when'] = base64_encode('init');

		$result = $instance->get_when_to_run();
		$this->assertEquals('init', $result);

		unset($_REQUEST['wu-when']);
	}

	public function test_get_when_to_run_accepts_setup_theme() {
		$instance = Light_Ajax::get_instance();

		$_REQUEST['wu-when'] = base64_encode('setup_theme');

		$result = $instance->get_when_to_run();
		$this->assertEquals('setup_theme', $result);

		unset($_REQUEST['wu-when']);
	}

	public function test_get_when_to_run_accepts_after_setup_theme() {
		$instance = Light_Ajax::get_instance();

		$_REQUEST['wu-when'] = base64_encode('after_setup_theme');

		$result = $instance->get_when_to_run();
		$this->assertEquals('after_setup_theme', $result);

		unset($_REQUEST['wu-when']);
	}

	public function test_get_when_to_run_rejects_invalid_hook() {
		$instance = Light_Ajax::get_instance();

		$_REQUEST['wu-when'] = base64_encode('wp_footer');

		$result = $instance->get_when_to_run();
		$this->assertEquals('plugins_loaded', $result);

		unset($_REQUEST['wu-when']);
	}

	public function test_get_when_to_run_rejects_arbitrary_string() {
		$instance = Light_Ajax::get_instance();

		$_REQUEST['wu-when'] = base64_encode('malicious_hook');

		$result = $instance->get_when_to_run();
		$this->assertEquals('plugins_loaded', $result);

		unset($_REQUEST['wu-when']);
	}

	// ------------------------------------------------------------------
	// should_skip_referer_check
	// ------------------------------------------------------------------

	public function test_should_skip_referer_check_for_allowed_actions() {
		$instance = Light_Ajax::get_instance();

		$method = new \ReflectionMethod(Light_Ajax::class, 'should_skip_referer_check');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$allowed_actions = [
			'wu_render_field_template',
			'wu_create_order',
			'wu_validate_form',
			'wu_check_user_exists',
			'wu_inline_login',
		];

		foreach ($allowed_actions as $action) {
			$_REQUEST['action'] = $action;
			$result             = $method->invoke($instance);
			$this->assertTrue($result, "Action '$action' should skip referer check");
		}

		unset($_REQUEST['action']);
	}

	public function test_should_not_skip_referer_check_for_unknown_actions() {
		$instance = Light_Ajax::get_instance();

		$method = new \ReflectionMethod(Light_Ajax::class, 'should_skip_referer_check');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$_REQUEST['action'] = 'some_random_action';
		$result             = $method->invoke($instance);
		$this->assertFalse($result);

		unset($_REQUEST['action']);
	}

	public function test_should_not_skip_referer_check_when_no_action() {
		$instance = Light_Ajax::get_instance();

		$method = new \ReflectionMethod(Light_Ajax::class, 'should_skip_referer_check');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		unset($_REQUEST['action']);
		$result = $method->invoke($instance);
		$this->assertFalse($result);
	}

	// ------------------------------------------------------------------
	// Filter: wu_light_ajax_allowed_hooks
	// ------------------------------------------------------------------

	public function test_allowed_hooks_filter_can_add_custom_hooks() {
		$instance = Light_Ajax::get_instance();

		add_filter('wu_light_ajax_allowed_hooks', function ($hooks) {
			$hooks[] = 'custom_hook';
			return $hooks;
		});

		$_REQUEST['wu-when'] = base64_encode('custom_hook');

		$result = $instance->get_when_to_run();
		$this->assertEquals('custom_hook', $result);

		unset($_REQUEST['wu-when']);
		remove_all_filters('wu_light_ajax_allowed_hooks');
	}

	// ------------------------------------------------------------------
	// Filter: wu_light_ajax_should_skip_referer_check
	// ------------------------------------------------------------------

	public function test_skip_referer_check_filter_can_add_actions() {
		$instance = Light_Ajax::get_instance();

		$method = new \ReflectionMethod(Light_Ajax::class, 'should_skip_referer_check');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		add_filter('wu_light_ajax_should_skip_referer_check', function ($actions) {
			$actions[] = 'my_custom_action';
			return $actions;
		});

		$_REQUEST['action'] = 'my_custom_action';
		$result             = $method->invoke($instance);
		$this->assertTrue($result);

		unset($_REQUEST['action']);
		remove_all_filters('wu_light_ajax_should_skip_referer_check');
	}
}
