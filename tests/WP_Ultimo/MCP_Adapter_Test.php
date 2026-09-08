<?php

namespace WP_Ultimo;

/**
 * Tests for the MCP_Adapter class.
 */
class MCP_Adapter_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh MCP_Adapter instance via reflection.
	 *
	 * @return MCP_Adapter
	 */
	private function get_instance() {

		return MCP_Adapter::get_instance();
	}

	/**
	 * Test singleton instance.
	 */
	public function test_get_instance() {

		$instance = $this->get_instance();

		$this->assertInstanceOf(MCP_Adapter::class, $instance);
		$this->assertSame($instance, MCP_Adapter::get_instance());
	}

	/**
	 * Test init always registers settings and optional integration hooks.
	 */
	public function test_init_registers_hooks() {

		$instance = $this->get_instance();

		$instance->init();

		$has_adapter_hook  = has_action('init', [$instance, 'initialize_adapter']);
		$has_server_hook   = has_action('mcp_adapter_init', [$instance, 'initialize_mcp_server']);
		$has_settings_hook = has_action('init', [$instance, 'add_settings']);
		$has_notice_hook   = has_action('network_admin_notices', [$instance, 'display_dependency_notice']);

		$this->assertNotFalse($has_adapter_hook);
		$this->assertNotFalse($has_server_hook);
		$this->assertNotFalse($has_settings_hook);
		$this->assertNotFalse($has_notice_hook);
	}

	/**
	 * Test is_mcp_enabled returns false by default.
	 */
	public function test_is_mcp_enabled_default() {

		$instance = $this->get_instance();

		$this->assertFalse($instance->is_mcp_enabled());
	}

	/**
	 * Test MCP remains disabled when its canonical plugin is unavailable.
	 */
	public function test_is_mcp_enabled_requires_available_plugin() {

		$instance = $this->get_instance();

		add_filter('wu_is_mcp_enabled', '__return_true');

		$this->assertFalse($instance->is_mcp_enabled());

		remove_filter('wu_is_mcp_enabled', '__return_true');
	}

	/**
	 * Test availability and enabled-state filters together.
	 */
	public function test_is_mcp_enabled_with_available_plugin() {

		$instance = $this->get_instance();

		add_filter('wu_mcp_adapter_available', '__return_true');
		add_filter('wu_is_mcp_enabled', '__return_true');

		$this->assertTrue($instance->is_mcp_available());
		$this->assertTrue($instance->is_mcp_enabled());

		remove_filter('wu_is_mcp_enabled', '__return_true');
		remove_filter('wu_mcp_adapter_available', '__return_true');
	}

	/**
	 * Test get_adapter returns null by default.
	 */
	public function test_get_adapter_returns_null() {

		$instance = $this->get_instance();

		$this->assertNull($instance->get_adapter());
	}

	/**
	 * Test initialize_adapter bails when MCP is disabled.
	 */
	public function test_initialize_adapter_bails_when_disabled() {

		$instance = $this->get_instance();

		// MCP is disabled by default
		$instance->initialize_adapter();

		// Adapter should still be null
		$this->assertNull($instance->get_adapter());
	}

	/**
	 * Test add_settings registers settings fields.
	 */
	public function test_add_settings() {

		$instance = $this->get_instance();

		// Should not throw
		$instance->add_settings();

		$this->assertTrue(true);
	}

	/**
	 * Test permission_callback returns WP_Error when no request.
	 */
	public function test_permission_callback_no_request() {

		$instance = $this->get_instance();

		// Reset current_request via reflection
		$ref = new \ReflectionProperty($instance, 'current_request');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->setValue($instance, null);

		$result = $instance->permission_callback();

		$this->assertWPError($result);
		$this->assertSame('no_request_object', $result->get_error_code());
	}

	/**
	 * Test rest_pre_dispatch_save_request stores the request.
	 */
	public function test_rest_pre_dispatch_save_request() {

		$instance = $this->get_instance();

		$request = new \WP_REST_Request('GET', '/test');

		$result = $instance->rest_pre_dispatch_save_request(null, null, $request);

		// Should return the original result (null)
		$this->assertNull($result);

		// Verify request was stored
		$ref = new \ReflectionProperty($instance, 'current_request');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertSame($request, $ref->getValue($instance));
	}

	/**
	 * Test MCP server registration bails when the integration is disabled.
	 */
	public function test_initialize_mcp_server_bails_when_disabled() {

		$instance = $this->get_instance();

		$instance->initialize_mcp_server();

		$this->assertNull($instance->get_adapter());
	}

	/**
	 * Test constants are accessible.
	 */
	public function test_class_implements_singleton() {

		$instance = $this->get_instance();

		$this->assertInstanceOf(\WP_Ultimo\Interfaces\Singleton::class, $instance);
	}
}
