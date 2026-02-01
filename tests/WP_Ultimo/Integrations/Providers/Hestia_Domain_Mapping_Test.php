<?php

namespace WP_Ultimo\Integrations\Providers\Hestia;

use WP_UnitTestCase;

class Hestia_Domain_Mapping_Test extends WP_UnitTestCase {

	private Hestia_Domain_Mapping $module;
	private Hestia_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Hestia_Integration::class)
			->onlyMethods(['send_hestia_request', 'get_credential'])
			->getMock();

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				$map = [
					'WU_HESTIA_ACCOUNT'    => 'admin',
					'WU_HESTIA_WEB_DOMAIN' => 'mysite.com',
					'WU_HESTIA_RESTART'    => 'yes',
				];

				return $map[ $key ] ?? '';
			});

		$this->module = new Hestia_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	public function test_get_capability_id(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
	}

	public function test_get_title(): void {

		$this->assertNotEmpty($this->module->get_title());
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

	public function test_on_add_domain_calls_add_alias_command(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_hestia_request')
			->willReturnCallback(function (string $cmd, array $args) {
				$this->assertSame('v-add-web-domain-alias', $cmd);
				$this->assertSame('admin', $args[0]);
				$this->assertSame('mysite.com', $args[1]);

				return 0;
			});

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_calls_delete_alias_command(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_hestia_request')
			->willReturnCallback(function (string $cmd, array $args) {
				$this->assertSame('v-delete-web-domain-alias', $cmd);
				$this->assertSame('admin', $args[0]);
				$this->assertSame('mysite.com', $args[1]);

				return 0;
			});

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_add_domain_skips_when_missing_credentials(): void {

		$integration = $this->getMockBuilder(Hestia_Integration::class)
			->onlyMethods(['send_hestia_request', 'get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$integration->expects($this->never())
			->method('send_hestia_request');

		$module = new Hestia_Domain_Mapping();
		$module->set_integration($integration);
		$module->on_add_domain('example.com', 1);
	}

	public function test_on_add_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_hestia_request');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_hestia_request');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('send_hestia_request')
			->willReturn(0);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}
}
