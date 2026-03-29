<?php

namespace WP_Ultimo\Integrations\Providers\Plesk;

use WP_UnitTestCase;

class Plesk_Domain_Mapping_Test extends WP_UnitTestCase {

	private Plesk_Domain_Mapping $module;
	private Plesk_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Plesk_Integration::class)
			->onlyMethods(['send_plesk_api_request', 'get_credential'])
			->getMock();

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				$map = [
					'WU_PLESK_DOMAIN' => 'mysite.com',
				];

				return $map[ $key ] ?? '';
			});

		$this->module = new Plesk_Domain_Mapping();
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

	public function test_on_add_domain_calls_site_alias_create(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_plesk_api_request')
			->with(
				'/api/v2/cli/site_alias/call',
				'POST',
				$this->callback(function (array $data) {
					return isset($data['params']) && in_array('--create', $data['params'], true);
				})
			)
			->willReturn(['success' => true]);

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_skips_when_no_base_domain(): void {

		$integration = $this->getMockBuilder(Plesk_Integration::class)
			->onlyMethods(['send_plesk_api_request', 'get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$integration->expects($this->never())
			->method('send_plesk_api_request');

		$module = new Plesk_Domain_Mapping();
		$module->set_integration($integration);
		$module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_calls_site_alias_delete(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_plesk_api_request')
			->with(
				'/api/v2/cli/site_alias/call',
				'POST',
				$this->callback(function (array $data) {
					return isset($data['params']) && in_array('--delete', $data['params'], true);
				})
			)
			->willReturn(['success' => true]);

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_add_subdomain_calls_subdomain_create(): void {

		$this->integration->expects($this->once())
			->method('send_plesk_api_request')
			->with(
				'/api/v2/cli/subdomain/call',
				'POST',
				$this->callback(function (array $data) {
					return isset($data['params']) && in_array('--create', $data['params'], true);
				})
			)
			->willReturn(['success' => true]);

		$this->module->on_add_subdomain('sub.mysite.com', 1);
	}

	public function test_on_add_subdomain_skips_when_no_base_domain(): void {

		$integration = $this->getMockBuilder(Plesk_Integration::class)
			->onlyMethods(['send_plesk_api_request', 'get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$integration->expects($this->never())
			->method('send_plesk_api_request');

		$module = new Plesk_Domain_Mapping();
		$module->set_integration($integration);
		$module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_calls_subdomain_delete(): void {

		$this->integration->expects($this->once())
			->method('send_plesk_api_request')
			->with(
				'/api/v2/cli/subdomain/call',
				'POST',
				$this->callback(function (array $data) {
					return isset($data['params']) && in_array('--delete', $data['params'], true);
				})
			)
			->willReturn(['success' => true]);

		$this->module->on_remove_subdomain('sub.mysite.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('send_plesk_api_request')
			->with('/api/v2/server', 'GET')
			->willReturn(['platform' => 'Linux']);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}
}
