<?php

namespace WP_Ultimo\Integrations\Providers\Enhance;

use WP_UnitTestCase;

class Enhance_Domain_Mapping_Test extends WP_UnitTestCase {

	private Enhance_Domain_Mapping $module;
	private Enhance_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Enhance_Integration::class)
			->onlyMethods(['send_enhance_api_request', 'get_credential'])
			->getMock();

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				$map = [
					'WU_ENHANCE_ORG_ID'     => 'org-uuid-1234',
					'WU_ENHANCE_WEBSITE_ID' => 'site-uuid-5678',
				];

				return $map[ $key ] ?? '';
			});

		$this->module = new Enhance_Domain_Mapping();
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

	public function test_on_add_domain_calls_api_with_correct_endpoint(): void {

		$this->integration->expects($this->once())
			->method('send_enhance_api_request')
			->with(
				'/orgs/org-uuid-1234/websites/site-uuid-5678/domains',
				'POST',
				['domain' => 'example.com']
			)
			->willReturn(['id' => 'domain-uuid-abc']);

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_skips_when_no_org_id(): void {

		$integration = $this->getMockBuilder(Enhance_Integration::class)
			->onlyMethods(['send_enhance_api_request', 'get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$integration->expects($this->never())
			->method('send_enhance_api_request');

		$module = new Enhance_Domain_Mapping();
		$module->set_integration($integration);
		$module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_skips_when_no_website_id(): void {

		$integration = $this->getMockBuilder(Enhance_Integration::class)
			->onlyMethods(['send_enhance_api_request', 'get_credential'])
			->getMock();

		$integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				if ('WU_ENHANCE_ORG_ID' === $key) {
					return 'org-uuid-1234';
				}

				return '';
			});

		$integration->expects($this->never())
			->method('send_enhance_api_request');

		$module = new Enhance_Domain_Mapping();
		$module->set_integration($integration);
		$module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_fetches_domain_list_first(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_enhance_api_request')
			->willReturnCallback(function (string $endpoint, string $method = 'GET') {
				if ('GET' === $method) {
					return [
						'items' => [
							['id' => 'domain-uuid-abc', 'domain' => 'example.com'],
						],
					];
				}

				return ['success' => true];
			});

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_remove_domain_skips_when_domain_not_found(): void {

		$this->integration->expects($this->once())
			->method('send_enhance_api_request')
			->willReturn(['items' => []]);

		$this->module->on_remove_domain('notfound.com', 1);
	}

	public function test_on_add_subdomain_delegates_to_on_add_domain(): void {

		$this->integration->expects($this->once())
			->method('send_enhance_api_request')
			->with(
				'/orgs/org-uuid-1234/websites/site-uuid-5678/domains',
				'POST',
				['domain' => 'sub.example.com']
			)
			->willReturn(['id' => 'domain-uuid-xyz']);

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_delegates_to_on_remove_domain(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_enhance_api_request')
			->willReturn(['items' => []]);

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('send_enhance_api_request')
			->willReturn(['id' => 'site-uuid-5678']);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}
}
