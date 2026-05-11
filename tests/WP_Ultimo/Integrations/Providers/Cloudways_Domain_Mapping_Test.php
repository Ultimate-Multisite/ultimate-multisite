<?php

namespace WP_Ultimo\Integrations\Providers\Cloudways;

use WP_UnitTestCase;

class Cloudways_Domain_Mapping_Test extends WP_UnitTestCase {

	private Cloudways_Domain_Mapping $module;
	private Cloudways_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Cloudways_Integration::class)
			->onlyMethods(['send_cloudways_request'])
			->getMock();

		$this->module = new Cloudways_Domain_Mapping();
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

	public function test_register_hooks_adds_ssl_action(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_domain_manager_dns_propagation_finished', [$this->module, 'request_ssl']));
	}

	public function test_on_add_domain_calls_aliases_api(): void {

		$this->integration->expects($this->once())
			->method('send_cloudways_request')
			->with('/app/manage/aliases', $this->anything())
			->willReturn((object) ['status' => true]);

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_calls_aliases_api(): void {

		$this->integration->expects($this->once())
			->method('send_cloudways_request')
			->with('/app/manage/aliases', $this->anything())
			->willReturn((object) ['status' => true]);

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_add_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_cloudways_request');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_cloudways_request');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('send_cloudways_request')
			->with('/app/manage/fpm_setting', [], 'GET')
			->willReturn((object) ['fpm_setting' => []]);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}

	// -------------------------------------------------------------------------
	// Wildcard / SSL split regression tests (GH#1160)
	// -------------------------------------------------------------------------

	/**
	 * Creates a fresh module+integration pair where get_credential is also
	 * stubbed so we can inject WU_CLOUDWAYS_EXTRA_DOMAINS without touching PHP
	 * constants or the database.
	 *
	 * @param string $extra_domains Comma-separated extra domains to return.
	 * @return array{0: Cloudways_Domain_Mapping, 1: Cloudways_Integration}
	 */
	private function make_module_with_extra_domains(string $extra_domains): array {

		$integration = $this->getMockBuilder(Cloudways_Integration::class)
			->onlyMethods(['send_cloudways_request', 'get_credential'])
			->getMock();

		$integration->method('get_credential')
			->willReturnCallback(
				static function ($name) use ($extra_domains) {
					if ('WU_CLOUDWAYS_EXTRA_DOMAINS' === $name) {
						return $extra_domains;
					}
					return '';
				}
			);

		$module = new Cloudways_Domain_Mapping();
		$module->set_integration($integration);

		return [$module, $integration];
	}

	/**
	 * A wildcard entry in WU_CLOUDWAYS_EXTRA_DOMAINS MUST appear in the aliases
	 * payload sent to /app/manage/aliases.
	 */
	public function test_wildcard_extra_domain_is_present_in_aliases_payload(): void {

		[$module, $integration] = $this->make_module_with_extra_domains('*.example.com,plain.example.org');

		$captured_aliases = null;

		$integration->expects($this->once())
			->method('send_cloudways_request')
			->with(
				'/app/manage/aliases',
				$this->callback(
					static function ($args) use (&$captured_aliases) {
						$captured_aliases = $args['aliases'] ?? [];
						return true;
					}
				)
			)
			->willReturn((object) ['status' => true]);

		$module->on_add_domain('plain.example.org', 1);

		$this->assertContains('*.example.com', $captured_aliases, 'Wildcard should remain in the aliases payload');
		$this->assertContains('plain.example.org', $captured_aliases, 'Plain extra domain should remain in the aliases payload');
	}

	/**
	 * A wildcard entry in WU_CLOUDWAYS_EXTRA_DOMAINS MUST NOT appear in the
	 * ssl_domains payload sent to /security/lets_encrypt_install.
	 */
	public function test_wildcard_extra_domain_is_absent_from_ssl_payload(): void {

		[$module, $integration] = $this->make_module_with_extra_domains('*.example.com');

		add_filter('wu_get_network_public_ip', static function () {
			return '1.2.3.4';
		});
		add_filter(
			'pre_http_request',
			static function ($preempt, $args, $url) {
				if (str_contains($url, 'dns.google')) {
					return [
						'headers'       => [],
						'body'          => '{"Answer":[{"data":"1.2.3.4"}]}',
						'response'      => [
							'code'    => 200,
							'message' => 'OK',
						],
						'cookies'       => [],
						'http_response' => null,
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$captured_ssl_domains = null;

		$integration->expects($this->once())
			->method('send_cloudways_request')
			->with(
				'/security/lets_encrypt_install',
				$this->callback(
					static function ($args) use (&$captured_ssl_domains) {
						$captured_ssl_domains = $args['ssl_domains'] ?? [];
						return true;
					}
				)
			)
			->willReturn((object) ['status' => true]);

		$module->request_ssl();

		$this->assertNotContains('*.example.com', $captured_ssl_domains, 'Wildcard must not appear in the SSL domains payload');

		remove_all_filters('wu_get_network_public_ip');
		remove_all_filters('pre_http_request');
	}

	/**
	 * A plain (non-wildcard) extra domain MUST appear in BOTH the aliases payload
	 * and the ssl_domains payload.
	 */
	public function test_plain_extra_domain_flows_to_both_aliases_and_ssl(): void {

		[$module, $integration] = $this->make_module_with_extra_domains('plain.example.org');

		add_filter('wu_get_network_public_ip', static function () {
			return '1.2.3.4';
		});
		add_filter(
			'pre_http_request',
			static function ($preempt, $args, $url) {
				if (str_contains($url, 'dns.google')) {
					return [
						'headers'       => [],
						'body'          => '{"Answer":[{"data":"1.2.3.4"}]}',
						'response'      => [
							'code'    => 200,
							'message' => 'OK',
						],
						'cookies'       => [],
						'http_response' => null,
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$aliases_payload     = null;
		$ssl_domains_payload = null;

		$integration->expects($this->exactly(2))
			->method('send_cloudways_request')
			->willReturnCallback(
				static function ($endpoint, $args) use (&$aliases_payload, &$ssl_domains_payload) {
					if ('/app/manage/aliases' === $endpoint) {
						$aliases_payload = $args['aliases'] ?? [];
					} elseif ('/security/lets_encrypt_install' === $endpoint) {
						$ssl_domains_payload = $args['ssl_domains'] ?? [];
					}
					return (object) ['status' => true];
				}
			);

		$module->on_add_domain('plain.example.org', 1);
		$module->request_ssl();

		$this->assertContains('plain.example.org', $aliases_payload, 'Plain extra domain should be in the aliases payload');
		$this->assertContains('plain.example.org', $ssl_domains_payload, 'Plain extra domain should be in the ssl_domains payload');

		remove_all_filters('wu_get_network_public_ip');
		remove_all_filters('pre_http_request');
	}
}
