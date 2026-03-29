<?php

namespace WP_Ultimo\Integrations\Providers\BunnyNet;

use WP_UnitTestCase;

class BunnyNet_Domain_Mapping_Test extends WP_UnitTestCase {

	private BunnyNet_Domain_Mapping $module;
	private BunnyNet_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(BunnyNet_Integration::class)
			->onlyMethods(['send_bunnynet_request', 'get_credential'])
			->getMock();

		$this->module = new BunnyNet_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	public function test_get_capability_id(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
	}

	public function test_get_title(): void {

		$this->assertNotEmpty($this->module->get_title());
	}

	public function test_get_explainer_lines_has_will_and_will_not_keys(): void {

		$lines = $this->module->get_explainer_lines();

		$this->assertArrayHasKey('will', $lines);
		$this->assertArrayHasKey('will_not', $lines);
	}

	public function test_register_hooks_adds_actions(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_add_domain', [$this->module, 'on_add_domain']));
		$this->assertIsInt(has_action('wu_remove_domain', [$this->module, 'on_remove_domain']));
		$this->assertIsInt(has_action('wu_add_subdomain', [$this->module, 'on_add_subdomain']));
		$this->assertIsInt(has_action('wu_remove_subdomain', [$this->module, 'on_remove_subdomain']));
	}

	public function test_register_hooks_adds_dns_filter(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_filter('wu_domain_dns_get_record', [$this->module, 'add_bunnynet_dns_entries']));
	}

	public function test_on_add_domain_is_noop(): void {

		// BunnyNet does not support automated custom domain mapping.
		$this->integration->expects($this->never())
			->method('send_bunnynet_request');

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_is_noop(): void {

		// BunnyNet does not support automated custom domain mapping.
		$this->integration->expects($this->never())
			->method('send_bunnynet_request');

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_add_subdomain_skips_when_no_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_BUNNYNET_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('send_bunnynet_request');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_skips_when_no_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_BUNNYNET_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('send_bunnynet_request');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_add_bunnynet_dns_entries_returns_unchanged_when_no_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_BUNNYNET_ZONE_ID')
			->willReturn('');

		$existing = [['type' => 'A', 'data' => '1.2.3.4']];

		$result = $this->module->add_bunnynet_dns_entries($existing, 'example.com');

		$this->assertSame($existing, $result);
	}

	public function test_add_bunnynet_dns_entries_returns_unchanged_on_api_error(): void {

		$this->integration->method('get_credential')
			->with('WU_BUNNYNET_ZONE_ID')
			->willReturn('12345');

		$this->integration->method('send_bunnynet_request')
			->willReturn(new \WP_Error('fail', 'error'));

		$existing = [['type' => 'A', 'data' => '1.2.3.4']];

		$result = $this->module->add_bunnynet_dns_entries($existing, 'example.com');

		$this->assertSame($existing, $result);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				if ('WU_BUNNYNET_ZONE_ID' === $key) {
					return '12345';
				}

				return '';
			});

		$this->integration->expects($this->once())
			->method('send_bunnynet_request')
			->willReturn((object) ['Id' => 12345]);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}
}
