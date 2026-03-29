<?php

namespace WP_Ultimo\Integrations\Providers\LaravelForge;

use WP_UnitTestCase;

class LaravelForge_Domain_Mapping_Test extends WP_UnitTestCase {

	private LaravelForge_Domain_Mapping $module;
	private LaravelForge_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(LaravelForge_Integration::class)
			->onlyMethods(['send_forge_request', 'parse_response', 'get_server_list', 'get_primary_server_id', 'get_primary_site_id', 'get_load_balancer_server_id', 'get_load_balancer_site_id', 'get_deploy_command', 'get_credential'])
			->getMock();

		$this->module = new LaravelForge_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	public function test_get_capability_id(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
	}

	public function test_get_title(): void {

		$this->assertNotEmpty($this->module->get_title());
	}

	public function test_supports_autossl(): void {

		$this->assertTrue($this->module->supports('autossl'));
	}

	public function test_does_not_support_unknown_feature(): void {

		$this->assertFalse($this->module->supports('nonexistent'));
	}

	public function test_get_explainer_lines_structure(): void {

		$this->integration->method('get_credential')->willReturn('');

		$lines = $this->module->get_explainer_lines();

		$this->assertArrayHasKey('will', $lines);
		$this->assertNotEmpty($lines['will']);
		$this->assertArrayHasKey('will_not', $lines);
	}

	public function test_register_hooks_adds_actions(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_add_domain', [$this->module, 'on_add_domain']));
		$this->assertIsInt(has_action('wu_remove_domain', [$this->module, 'on_remove_domain']));
		$this->assertIsInt(has_action('wu_add_subdomain', [$this->module, 'on_add_subdomain']));
		$this->assertIsInt(has_action('wu_remove_subdomain', [$this->module, 'on_remove_subdomain']));
	}

	public function test_on_add_domain_skips_when_no_servers(): void {

		$this->integration->method('get_server_list')->willReturn([]);

		$this->integration->expects($this->never())
			->method('send_forge_request');

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_creates_site_on_server(): void {

		$this->integration->method('get_server_list')->willReturn([12345]);
		$this->integration->method('get_load_balancer_server_id')->willReturn(0);
		$this->integration->method('get_primary_server_id')->willReturn(12345);
		$this->integration->method('get_primary_site_id')->willReturn(67890);
		$this->integration->method('get_deploy_command')->willReturn('');

		$fake_response = ['response' => ['code' => 201], 'body' => '{"site":{"id":99}}'];

		$this->integration->expects($this->atLeast(1))
			->method('send_forge_request')
			->willReturn($fake_response);

		$this->integration->method('parse_response')
			->willReturn(['site' => ['id' => 99]]);

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_skips_when_site_not_found(): void {

		$this->integration->method('get_server_list')->willReturn([12345]);

		// GET /servers/12345/sites returns empty list — no DELETE should be called.
		$this->integration->expects($this->once())
			->method('send_forge_request')
			->with($this->stringContains('/servers/12345/sites'), [], 'GET')
			->willReturn(['response' => ['code' => 200], 'body' => '{"sites":[]}']);

		$this->integration->method('parse_response')
			->willReturn(['sites' => []]);

		$this->module->on_remove_domain('notfound.com', 1);
	}

	public function test_on_add_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_forge_request');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_forge_request');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->method('get_primary_server_id')->willReturn(12345);

		$this->integration->expects($this->once())
			->method('send_forge_request')
			->with('/servers/12345', [], 'GET')
			->willReturn(['response' => ['code' => 200], 'body' => '{"server":{"id":12345}}']);

		$this->integration->method('parse_response')
			->willReturn(['server' => ['id' => 12345]]);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}
}
