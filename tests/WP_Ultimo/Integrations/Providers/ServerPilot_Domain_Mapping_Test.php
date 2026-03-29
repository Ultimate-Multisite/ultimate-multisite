<?php

namespace WP_Ultimo\Integrations\Providers\ServerPilot;

use WP_UnitTestCase;

class ServerPilot_Domain_Mapping_Test extends WP_UnitTestCase {

	private ServerPilot_Domain_Mapping $module;
	private ServerPilot_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(ServerPilot_Integration::class)
			->onlyMethods(['send_server_pilot_api_request'])
			->getMock();

		$this->module = new ServerPilot_Domain_Mapping();
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

	public function test_on_add_domain_fetches_current_domains_first(): void {

		$domains_updated = false;

		$this->integration->expects($this->atLeast(2))
			->method('send_server_pilot_api_request')
			->willReturnCallback(function (string $endpoint, array $data, string $method) use (&$domains_updated) {
				if ('GET' === $method) {
					return ['data' => ['domains' => ['existing.com']]];
				}

				// POST to update domains (endpoint '' with domains key) or SSL endpoint.
				if (isset($data['domains'])) {
					$this->assertContains('example.com', $data['domains']);
					$domains_updated = true;
				}

				return ['data' => []];
			});

		$this->module->on_add_domain('example.com', 1);

		$this->assertTrue($domains_updated, 'Domain list was never updated via API');
	}

	public function test_on_add_domain_skips_when_no_current_domains(): void {

		$this->integration->expects($this->once())
			->method('send_server_pilot_api_request')
			->with('', [], 'GET')
			->willReturn(['data' => []]);

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_removes_domain_from_list(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_server_pilot_api_request')
			->willReturnCallback(function (string $endpoint, array $data, string $method) {
				if ('GET' === $method) {
					return ['data' => ['domains' => ['example.com', 'other.com']]];
				}

				// POST to update domains — example.com should be removed.
				$this->assertNotContains('example.com', $data['domains']);
				$this->assertContains('other.com', $data['domains']);

				return ['data' => ['domains' => ['other.com']]];
			});

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_add_subdomain_adds_subdomain_to_list(): void {

		$subdomain_added = false;

		$this->integration->expects($this->atLeast(2))
			->method('send_server_pilot_api_request')
			->willReturnCallback(function (string $endpoint, array $data, string $method) use (&$subdomain_added) {
				if ('GET' === $method) {
					return ['data' => ['domains' => ['existing.com']]];
				}

				if (isset($data['domains'])) {
					$this->assertContains('sub.example.com', $data['domains']);
					$subdomain_added = true;
				}

				return ['data' => []];
			});

		$this->module->on_add_subdomain('sub.example.com', 1);

		$this->assertTrue($subdomain_added, 'Subdomain was never added to domain list');
	}

	public function test_on_remove_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_server_pilot_api_request');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_get_server_pilot_domains_returns_domain_list(): void {

		$this->integration->expects($this->once())
			->method('send_server_pilot_api_request')
			->with('', [], 'GET')
			->willReturn(['data' => ['domains' => ['example.com', 'other.com']]]);

		$domains = $this->module->get_server_pilot_domains();

		$this->assertIsArray($domains);
		$this->assertContains('example.com', $domains);
	}

	public function test_get_server_pilot_domains_returns_false_on_error(): void {

		$this->integration->expects($this->once())
			->method('send_server_pilot_api_request')
			->willReturn(['error' => 'API error']);

		$result = $this->module->get_server_pilot_domains();

		$this->assertFalse($result);
	}

	public function test_turn_server_pilot_auto_ssl_on_calls_ssl_endpoint(): void {

		$this->integration->expects($this->once())
			->method('send_server_pilot_api_request')
			->with('/ssl', ['auto' => true])
			->willReturn(['data' => ['ssl' => ['auto' => true]]]);

		$this->module->turn_server_pilot_auto_ssl_on();
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('send_server_pilot_api_request')
			->with('', [], 'GET')
			->willReturn(['data' => ['id' => 'app123']]);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}
}
