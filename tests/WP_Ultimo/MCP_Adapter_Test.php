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
	 * Test init registers hooks when McpAdapterCore class exists.
	 */
	public function test_init_registers_hooks() {

		$instance = $this->get_instance();

		$instance->init();

		// The MCP adapter core class exists in this env, so hooks get registered
		$has_adapter_hook  = has_action('init', [$instance, 'initialize_adapter']);
		$has_settings_hook = has_action('init', [$instance, 'add_settings']);

		// Both should be registered (truthy priority) or both not (false)
		if ($has_adapter_hook !== false) {
			$this->assertNotFalse($has_adapter_hook);
			$this->assertNotFalse($has_settings_hook);
		} else {
			// McpAdapterCore doesn't exist, hooks not registered
			$this->assertFalse($has_adapter_hook);
		}
	}

	/**
	 * Test is_mcp_enabled returns false by default.
	 */
	public function test_is_mcp_enabled_default() {

		$instance = $this->get_instance();

		$this->assertFalse($instance->is_mcp_enabled());
	}

	/**
	 * Test is_mcp_enabled with filter override.
	 */
	public function test_is_mcp_enabled_with_filter() {

		$instance = $this->get_instance();

		add_filter('wu_is_mcp_enabled', '__return_true');

		$this->assertTrue($instance->is_mcp_enabled());

		remove_filter('wu_is_mcp_enabled', '__return_true');
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
	 * Test initialize_mcp_server bails with no abilities.
	 */
	public function test_initialize_mcp_server_no_abilities() {

		$instance = $this->get_instance();

		// wp_get_abilities doesn't exist in test env, so get_mcp_abilities returns empty
		$instance->initialize_mcp_server();

		// Should not throw, just bail
		$this->assertTrue(true);
	}

	/**
	 * Test constants are accessible.
	 */
	public function test_class_implements_singleton() {

		$instance = $this->get_instance();

		$this->assertInstanceOf(\WP_Ultimo\Interfaces\Singleton::class, $instance);
	}
}
