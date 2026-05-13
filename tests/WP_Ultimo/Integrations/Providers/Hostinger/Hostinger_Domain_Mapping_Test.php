<?php

namespace WP_Ultimo\Integrations\Providers\Hostinger;

use WP_UnitTestCase;

class Hostinger_Domain_Mapping_Test extends WP_UnitTestCase {

	private Hostinger_Domain_Mapping $module;
	private Hostinger_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Hostinger_Integration::class)
			->onlyMethods(['hostinger_api_call', 'get_credential', 'test_connection'])
			->getMock();

		$this->module = new Hostinger_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	public function test_get_capability_id(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
	}

	public function test_get_title_is_non_empty(): void {

		$this->assertNotEmpty($this->module->get_title());
	}

	public function test_get_explainer_lines_has_will_and_will_not_keys(): void {

		$lines = $this->module->get_explainer_lines();

		$this->assertArrayHasKey('will', $lines);
		$this->assertArrayHasKey('will_not', $lines);
	}

	public function test_register_hooks_adds_all_domain_actions(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_add_domain', [$this->module, 'on_add_domain']));
		$this->assertIsInt(has_action('wu_remove_domain', [$this->module, 'on_remove_domain']));
		$this->assertIsInt(has_action('wu_add_subdomain', [$this->module, 'on_add_subdomain']));
		$this->assertIsInt(has_action('wu_remove_subdomain', [$this->module, 'on_remove_subdomain']));
		$this->assertIsInt(has_filter('wu_domain_dns_get_record', [$this->module, 'add_hostinger_dns_entries']));
	}

	public function test_relative_record_name_returns_at_for_apex(): void {

		$this->assertSame('@', $this->module->relative_record_name('example.com', 'example.com'));
	}

	public function test_relative_record_name_strips_zone_suffix(): void {

		$this->assertSame('www', $this->module->relative_record_name('www.example.com', 'example.com'));
	}

	public function test_relative_record_name_strips_deeper_zone_suffix(): void {

		$this->assertSame('client.shop', $this->module->relative_record_name('client.shop.example.com', 'example.com'));
	}

	public function test_relative_record_name_returns_hostname_when_not_in_zone(): void {

		$this->assertSame('other.tld', $this->module->relative_record_name('other.tld', 'example.com'));
	}

	public function test_find_root_domain_returns_false_when_api_errors(): void {

		$this->integration->method('hostinger_api_call')->willReturn(new \WP_Error('boom', 'fail'));

		$this->assertFalse($this->module->find_root_domain('foo.example.com'));
	}

	public function test_find_root_domain_matches_object_response(): void {

		$portfolio = [
			(object) ['domain' => 'example.com'],
			(object) ['domain' => 'another.net'],
		];

		$this->integration->method('hostinger_api_call')->willReturn($portfolio);

		$this->assertSame('example.com', $this->module->find_root_domain('sub.example.com'));
	}

	public function test_find_root_domain_prefers_deepest_delegated_zone(): void {

		$portfolio = [
			(object) ['domain' => 'example.com'],
			(object) ['domain' => 'shop.example.com'],
		];

		$this->integration->method('hostinger_api_call')->willReturn($portfolio);

		$this->assertSame('shop.example.com', $this->module->find_root_domain('client.shop.example.com'));
	}

	public function test_find_root_domain_returns_false_when_no_zone_matches(): void {

		$this->integration->method('hostinger_api_call')->willReturn([
			(object) ['domain' => 'unrelated.tld'],
		]);

		$this->assertFalse($this->module->find_root_domain('foo.example.com'));
	}

	public function test_on_add_domain_skips_when_zone_not_in_portfolio(): void {

		$this->integration->expects($this->once())
			->method('hostinger_api_call')
			->with('/api/domains/v1/portfolio')
			->willReturn([(object) ['domain' => 'unrelated.tld']]);

		$this->module->on_add_domain('not-owned.tld', 1);
	}

	public function test_on_add_domain_writes_record_when_zone_matches(): void {

		$this->integration->method('hostinger_api_call')
			->willReturnCallback(function (string $endpoint, string $method = 'GET', array $data = []) {
				if ('/api/domains/v1/portfolio' === $endpoint) {
					return [(object) ['domain' => 'example.com']];
				}

				if ('/api/dns/v1/zones/example.com' === $endpoint && 'PUT' === $method) {
					$this->assertSame(false, $data['overwrite']);
					$this->assertSame('client', $data['zone'][0]['name']);
					$this->assertSame('CNAME', $data['zone'][0]['type']);

					return (object) [];
				}

				return new \WP_Error('unexpected', 'unexpected endpoint ' . $endpoint);
			});

		$this->module->on_add_domain('client.example.com', 1);
	}

	public function test_on_remove_domain_skips_when_zone_not_in_portfolio(): void {

		$this->integration->expects($this->once())
			->method('hostinger_api_call')
			->with('/api/domains/v1/portfolio')
			->willReturn([(object) ['domain' => 'unrelated.tld']]);

		$this->module->on_remove_domain('not-owned.tld', 1);
	}

	public function test_on_remove_domain_deletes_all_record_types_when_zone_matches(): void {

		$delete_calls = 0;

		$this->integration->method('hostinger_api_call')
			->willReturnCallback(function (string $endpoint, string $method = 'GET', array $data = []) use (&$delete_calls) {
				if ('/api/domains/v1/portfolio' === $endpoint) {
					return [(object) ['domain' => 'example.com']];
				}

				if ('/api/dns/v1/zones/example.com' === $endpoint && 'DELETE' === $method) {
					$this->assertSame('client', $data['filters'][0]['name']);
					$delete_calls++;

					return (object) [];
				}

				return new \WP_Error('unexpected', 'unexpected endpoint ' . $endpoint);
			});

		$this->module->on_remove_domain('client.example.com', 1);

		$this->assertSame(3, $delete_calls, 'Expected DELETE for CNAME, A and AAAA');
	}

	public function test_add_hostinger_dns_entries_returns_unchanged_when_zone_not_matched(): void {

		$this->integration->method('hostinger_api_call')->willReturn(new \WP_Error('boom', 'fail'));

		$existing = [
			[
				'type' => 'A',
				'data' => '1.2.3.4',
			],
		];

		$this->assertSame($existing, $this->module->add_hostinger_dns_entries($existing, 'foo.example.com'));
	}

	public function test_add_hostinger_dns_entries_appends_matching_records(): void {

		$this->integration->method('hostinger_api_call')
			->willReturnCallback(function (string $endpoint) {
				if ('/api/domains/v1/portfolio' === $endpoint) {
					return [(object) ['domain' => 'example.com']];
				}

				if ('/api/dns/v1/zones/example.com' === $endpoint) {
					return [
						(object) [
							'name'    => 'client',
							'type'    => 'CNAME',
							'ttl'     => 14400,
							'records' => [(object) ['content' => 'origin.example.com.']],
						],
						(object) [
							'name'    => 'unrelated',
							'type'    => 'CNAME',
							'ttl'     => 14400,
							'records' => [(object) ['content' => 'other.example.com.']],
						],
					];
				}

				return new \WP_Error('unexpected', 'unexpected endpoint ' . $endpoint);
			});

		$result = $this->module->add_hostinger_dns_entries([], 'client.example.com');

		$this->assertCount(1, $result);
		$this->assertSame('CNAME', $result[0]['type']);
		$this->assertSame('client.example.com', $result[0]['host']);
		$this->assertSame('origin.example.com.', $result[0]['data']);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('test_connection')
			->willReturn(true);

		$this->assertTrue($this->module->test_connection());
	}
}
