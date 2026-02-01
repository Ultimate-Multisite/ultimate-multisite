<?php

namespace WP_Ultimo\Integrations\Providers\GridPane;

use WP_UnitTestCase;

class GridPane_Domain_Mapping_Test extends WP_UnitTestCase {

	private GridPane_Domain_Mapping $module;
	private GridPane_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(GridPane_Integration::class)
			->onlyMethods(['send_gridpane_api_request', 'get_credential'])
			->getMock();

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				$map = [
					'WU_GRIDPANE_SERVER_ID' => '1.2.3.4',
					'WU_GRIDPANE_APP_ID'    => 'mysite.com',
				];

				return $map[ $key ] ?? '';
			});

		$this->module = new GridPane_Domain_Mapping();
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

	public function test_on_add_domain_calls_add_endpoint(): void {

		$this->integration->expects($this->once())
			->method('send_gridpane_api_request')
			->with(
				'application/add-domain',
				$this->callback(function (array $data) {
					return $data['domain_url'] === 'example.com'
						&& $data['server_ip'] === '1.2.3.4'
						&& $data['site_url'] === 'mysite.com';
				})
			);

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_calls_delete_endpoint(): void {

		$this->integration->expects($this->once())
			->method('send_gridpane_api_request')
			->with(
				'application/delete-domain',
				$this->callback(function (array $data) {
					return $data['domain_url'] === 'example.com';
				})
			);

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_add_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_gridpane_api_request');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_gridpane_api_request');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('send_gridpane_api_request')
			->willReturn(['success' => true]);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}
}
