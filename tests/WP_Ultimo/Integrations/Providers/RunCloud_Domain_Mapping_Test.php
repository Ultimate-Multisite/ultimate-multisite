<?php

namespace WP_Ultimo\Integrations\Providers\RunCloud;

use WP_UnitTestCase;

class RunCloud_Domain_Mapping_Test extends WP_UnitTestCase {

	private RunCloud_Domain_Mapping $module;
	private RunCloud_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(RunCloud_Integration::class)
			->onlyMethods(['send_runcloud_request', 'get_runcloud_base_url', 'maybe_return_runcloud_body'])
			->getMock();

		$this->integration->method('get_runcloud_base_url')
			->willReturnCallback(fn(string $path) => "https://manage.runcloud.io/api/v3/$path");

		$this->module = new RunCloud_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	public function test_get_capability_id(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
	}

	public function test_supports_autossl(): void {

		$this->assertTrue($this->module->supports('autossl'));
	}

	public function test_get_explainer_lines_structure(): void {

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

	public function test_on_add_domain_calls_api(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_runcloud_request')
			->willReturnCallback(function (string $url, array $data, string $method) {
				if ($method === 'POST') {
					$this->assertSame('example.com', $data['name']);
					$this->assertSame('alias', $data['type']);

					return ['response' => ['code' => 200], 'body' => '{"id":1}'];
				}

				// GET requests for SSL
				return ['response' => ['code' => 200], 'body' => '{}'];
			});

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_does_not_redeploy_ssl_on_failure(): void {

		$this->integration->expects($this->once())
			->method('send_runcloud_request')
			->willReturn(new \WP_Error('fail', 'API error'));

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_runcloud_request');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_runcloud_request');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_runcloud_request')
			->willReturn(['response' => ['code' => 200], 'body' => '{"data":{}}']);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}
}
