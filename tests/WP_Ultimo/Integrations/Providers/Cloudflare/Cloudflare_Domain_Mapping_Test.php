<?php

namespace WP_Ultimo\Integrations\Providers\Cloudflare;

use WP_UnitTestCase;

class Cloudflare_Domain_Mapping_Test extends WP_UnitTestCase {

	private Cloudflare_Domain_Mapping $module;
	private Cloudflare_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Cloudflare_Integration::class)
			->onlyMethods(['cloudflare_api_call', 'get_credential'])
			->getMock();

		$this->module = new Cloudflare_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	public function test_get_capability_id(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
	}

	public function test_get_title(): void {

		$this->assertNotEmpty($this->module->get_title());
	}

	public function test_get_explainer_lines_has_will_and_will_not_keys(): void {

		$this->integration->method('get_credential')
			->willReturn('');

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

		$this->assertIsInt(has_filter('wu_domain_dns_get_record', [$this->module, 'add_cloudflare_dns_entries']));
	}

	public function test_on_add_domain_skips_when_no_saas_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_SAAS_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('cloudflare_api_call');

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_calls_api_when_saas_zone_id_set(): void {

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				if ('WU_CLOUDFLARE_SAAS_ZONE_ID' === $key) {
					return 'zone123';
				}

				return '';
			});

		$this->integration->expects($this->once())
			->method('cloudflare_api_call')
			->with(
				$this->stringContains('custom_hostnames'),
				'POST',
				$this->callback(function ($data) {
					return $data['hostname'] === 'custom.example.com';
				})
			)
			->willReturn((object) ['result' => (object) ['id' => 'ch_123']]);

		$this->module->on_add_domain('custom.example.com', 1);
	}

	public function test_on_add_domain_handles_api_error(): void {

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				if ('WU_CLOUDFLARE_SAAS_ZONE_ID' === $key) {
					return 'zone123';
				}

				return '';
			});

		$this->integration->method('cloudflare_api_call')
			->willReturn(new \WP_Error('cloudflare-error', 'API failed'));

		// Should not throw — just logs the error.
		$this->module->on_add_domain('fail.example.com', 1);

		$this->assertTrue(true);
	}

	public function test_on_remove_domain_skips_when_no_saas_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_SAAS_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('cloudflare_api_call');

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_remove_domain_handles_empty_result(): void {

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				if ('WU_CLOUDFLARE_SAAS_ZONE_ID' === $key) {
					return 'zone123';
				}

				return '';
			});

		$this->integration->method('cloudflare_api_call')
			->willReturn((object) ['result' => []]);

		$this->module->on_remove_domain('missing.example.com', 1);

		$this->assertTrue(true);
	}

	public function test_on_add_subdomain_skips_when_no_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('cloudflare_api_call');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_skips_when_no_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('cloudflare_api_call');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_add_cloudflare_dns_entries_returns_unchanged_when_no_zone_id(): void {

		$this->integration->method('get_credential')
			->willReturn('');

		$this->integration->method('cloudflare_api_call')
			->willReturn(new \WP_Error('fail', 'error'));

		$existing = [['type' => 'A', 'data' => '1.2.3.4']];

		$result = $this->module->add_cloudflare_dns_entries($existing, 'example.com');

		$this->assertSame($existing, $result);
	}

	public function test_add_cloudflare_dns_entries_returns_unchanged_on_api_error(): void {

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				if ('WU_CLOUDFLARE_ZONE_ID' === $key) {
					return '12345';
				}

				return '';
			});

		$this->integration->method('cloudflare_api_call')
			->willReturn(new \WP_Error('fail', 'error'));

		$existing = [['type' => 'A', 'data' => '1.2.3.4']];

		$result = $this->module->add_cloudflare_dns_entries($existing, 'example.com');

		$this->assertSame($existing, $result);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('cloudflare_api_call')
			->with('client/v4/user/tokens/verify')
			->willReturn((object) ['result' => (object) ['status' => 'active']]);

		$result = $this->module->test_connection();

		$this->assertTrue($result);
	}

	public function test_test_connection_returns_wp_error_on_failure(): void {

		$error = new \WP_Error('cloudflare-error', 'Auth failed');

		$this->integration->expects($this->once())
			->method('cloudflare_api_call')
			->willReturn($error);

		$result = $this->module->test_connection();

		$this->assertInstanceOf(\WP_Error::class, $result);
	}
}
