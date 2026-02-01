<?php

namespace WP_Ultimo\Integrations\Providers\Closte;

use WP_UnitTestCase;

class Closte_Domain_Mapping_Test extends WP_UnitTestCase {

	private Closte_Domain_Mapping $module;
	private Closte_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Closte_Integration::class)
			->onlyMethods(['api_call'])
			->getMock();

		$this->module = new Closte_Domain_Mapping();
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

	public function test_get_explainer_lines_has_will_key(): void {

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
		$this->assertIsInt(has_filter('wu_async_process_domain_stage_max_tries', [$this->module, 'ssl_tries']));
	}

	public function test_on_add_domain_calls_api(): void {

		$this->integration->expects($this->atLeast(1))
			->method('api_call')
			->willReturnCallback(function (string $endpoint, array $data) {
				if ($endpoint === '/adddomainalias') {
					$this->assertSame('example.com', $data['domain']);
					$this->assertFalse($data['wildcard']);

					return ['success' => true];
				}

				// SSL endpoint calls
				return ['success' => true];
			});

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_wildcard(): void {

		$this->integration->expects($this->atLeast(1))
			->method('api_call')
			->willReturnCallback(function (string $endpoint, array $data) {
				if ($endpoint === '/adddomainalias') {
					$this->assertTrue($data['wildcard']);

					return ['success' => true];
				}

				return ['success' => true];
			});

		$this->module->on_add_domain('*.example.com', 1);
	}

	public function test_on_add_domain_no_ssl_on_api_failure(): void {

		// When adddomainalias fails, SSL should not be requested
		$this->integration->expects($this->once())
			->method('api_call')
			->with('/adddomainalias', $this->anything())
			->willReturn(['success' => false]);

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_no_ssl_on_wp_error(): void {

		$this->integration->expects($this->once())
			->method('api_call')
			->with('/adddomainalias', $this->anything())
			->willReturn(new \WP_Error('fail', 'error'));

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_calls_api(): void {

		$this->integration->expects($this->once())
			->method('api_call')
			->with(
				'/deletedomainalias',
				$this->callback(function (array $data) {
					return $data['domain'] === 'example.com' && $data['wildcard'] === false;
				})
			);

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_add_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('api_call');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('api_call');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_ssl_tries_increases_for_checking_ssl_cert(): void {

		$domain = $this->createMock(\WP_Ultimo\Models\Domain::class);
		$domain->method('get_stage')->willReturn('checking-ssl-cert');

		$this->assertSame(20, $this->module->ssl_tries(5, $domain));
	}

	public function test_ssl_tries_unchanged_for_other_stages(): void {

		$domain = $this->createMock(\WP_Ultimo\Models\Domain::class);
		$domain->method('get_stage')->willReturn('checking-dns');

		$this->assertSame(5, $this->module->ssl_tries(5, $domain));
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('api_call')
			->willReturn(['error' => 'Invalid or empty domain: ']);

		// test_connection on Closte_Integration returns true for that response,
		// but since we mocked api_call, we call it on the module directly
		$result = $this->module->test_connection();

		// The module delegates to integration's test_connection, which calls api_call
		// Since we mocked the integration, test_connection will call the real method
		// which calls our mocked api_call
		$this->assertNotNull($result);
	}
}
