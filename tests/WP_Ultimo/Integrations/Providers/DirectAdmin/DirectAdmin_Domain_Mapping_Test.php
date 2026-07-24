<?php

namespace WP_Ultimo\Integrations\Providers\DirectAdmin;

use WP_UnitTestCase;

class DirectAdmin_Domain_Mapping_Test extends WP_UnitTestCase {

	private DirectAdmin_Domain_Mapping $module;
	private DirectAdmin_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(DirectAdmin_Integration::class)
			->onlyMethods(['directadmin_api_request', 'get_credential', 'test_connection'])
			->getMock();

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				$map = [
					'WU_DIRECTADMIN_DOMAIN' => 'mysite.com',
				];

				return $map[ $key ] ?? '';
			});

		$this->module = new DirectAdmin_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	public function test_get_capability_id(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
	}

	public function test_get_title_is_non_empty(): void {

		$this->assertNotEmpty($this->module->get_title());
	}

	public function test_supports_autossl(): void {

		$this->assertTrue($this->module->supports('autossl'));
	}

	public function test_get_explainer_lines_has_will_and_will_not_keys(): void {

		$lines = $this->module->get_explainer_lines();

		$this->assertArrayHasKey('will', $lines);
		$this->assertNotEmpty($lines['will']);
		$this->assertArrayHasKey('will_not', $lines);
	}

	public function test_register_hooks_adds_all_domain_actions(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_add_domain', [$this->module, 'on_add_domain']));
		$this->assertIsInt(has_action('wu_remove_domain', [$this->module, 'on_remove_domain']));
		$this->assertIsInt(has_action('wu_add_subdomain', [$this->module, 'on_add_subdomain']));
		$this->assertIsInt(has_action('wu_remove_subdomain', [$this->module, 'on_remove_subdomain']));
	}

	public function test_on_add_domain_calls_domain_pointer_add(): void {

		$this->integration->expects($this->atLeast(1))
			->method('directadmin_api_request')
			->willReturnCallback(function (string $endpoint, string $method, array $data) {
				$this->assertSame('/CMD_API_DOMAIN_POINTER', $endpoint);
				$this->assertSame('POST', $method);
				$this->assertSame('mysite.com', $data['domain']);
				$this->assertSame('add', $data['action']);
				$this->assertContains($data['from'], ['example.com', 'www.example.com']);
				$this->assertSame('yes', $data['alias']);

				return ['error' => '0'];
			});

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_skips_when_base_domain_missing(): void {

		$integration = $this->getMockBuilder(DirectAdmin_Integration::class)
			->onlyMethods(['directadmin_api_request', 'get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$integration->expects($this->never())
			->method('directadmin_api_request');

		$module = new DirectAdmin_Domain_Mapping();
		$module->set_integration($integration);
		$module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_calls_domain_pointer_delete(): void {

		$this->integration->expects($this->atLeast(1))
			->method('directadmin_api_request')
			->willReturnCallback(function (string $endpoint, string $method, array $data) {
				$this->assertSame('/CMD_API_DOMAIN_POINTER', $endpoint);
				$this->assertSame('POST', $method);
				$this->assertSame('mysite.com', $data['domain']);
				$this->assertSame('delete', $data['action']);
				$this->assertContains($data['select0'], ['example.com', 'www.example.com']);

				return ['error' => '0'];
			});

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_add_subdomain_calls_domain_pointer_add(): void {

		$this->integration->expects($this->once())
			->method('directadmin_api_request')
			->with(
				'/CMD_API_DOMAIN_POINTER',
				'POST',
				$this->callback(function (array $data) {
					return 'mysite.com' === $data['domain']
						&& 'add' === $data['action']
						&& 'sub.mysite.com' === $data['from']
						&& 'yes' === $data['alias'];
				})
			)
			->willReturn(['error' => '0']);

		$this->module->on_add_subdomain('sub.mysite.com', 1);
	}

	public function test_on_remove_subdomain_calls_domain_pointer_delete(): void {

		$this->integration->expects($this->once())
			->method('directadmin_api_request')
			->with(
				'/CMD_API_DOMAIN_POINTER',
				'POST',
				$this->callback(function (array $data) {
					return 'mysite.com' === $data['domain']
						&& 'delete' === $data['action']
						&& 'sub.mysite.com' === $data['select0'];
				})
			)
			->willReturn(['error' => '0']);

		$this->module->on_remove_subdomain('sub.mysite.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('test_connection')
			->willReturn(true);

		$this->assertTrue($this->module->test_connection());
	}
}
