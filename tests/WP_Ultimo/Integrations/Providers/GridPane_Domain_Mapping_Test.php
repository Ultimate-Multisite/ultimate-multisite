<?php

namespace WP_Ultimo\Integrations\Providers\GridPane;

use WP_UnitTestCase;

class GridPane_Domain_Mapping_Test extends WP_UnitTestCase {

	private GridPane_Domain_Mapping $module;
	private GridPane_Integration $integration;
	private array $credentials = [];

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(GridPane_Integration::class)
			->onlyMethods(['send_gridpane_api_request', 'get_credential', 'get_site_configuration', 'fetch_all', 'test_connection'])
			->getMock();

		$this->integration->method('get_credential')
			->willReturnCallback(fn(string $key) => $this->credentials[ $key ] ?? '');

		$this->integration->method('get_site_configuration')
			->willReturn(
				[
					'site_id'   => 123456,
					'server_id' => 12345,
				]
			);

		$this->module = new GridPane_Domain_Mapping();
		$this->module->set_integration($this->integration);

		delete_network_option(null, 'wu_gridpane_next_write_slot');
		wu_unschedule_all_actions('wu_gridpane_retry_add_domain');
		wu_unschedule_all_actions('wu_gridpane_retry_remove_domain');
	}

	public function tearDown(): void {

		delete_network_option(null, 'wu_gridpane_next_write_slot');
		wu_unschedule_all_actions('wu_gridpane_retry_add_domain');
		wu_unschedule_all_actions('wu_gridpane_retry_remove_domain');

		parent::tearDown();
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
		$this->assertIsInt(has_action('wu_gridpane_retry_add_domain', [$this->module, 'retry_add_domain']));
		$this->assertIsInt(has_action('wu_gridpane_retry_remove_domain', [$this->module, 'retry_remove_domain']));
	}

	public function test_on_add_domain_calls_add_endpoint(): void {

		$this->integration->expects($this->once())
			->method('send_gridpane_api_request')
			->with(
				'domain',
				$this->callback(function (array $data) {
					return $data['domain_url'] === 'example.com'
						&& $data['site_id'] === 123456
						&& $data['server_id'] === 12345
						&& $data['type'] === 'alias'
						&& $data['dns_management'] === 'none_none';
				}),
				'POST'
			);

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_requires_dns_integration_id_for_managed_dns(): void {

		$this->credentials['WU_GRIDPANE_DNS_MANAGEMENT'] = 'cloudflare_full';

		$this->integration->expects($this->never())
			->method('send_gridpane_api_request');

		$this->module->on_add_domain('example.com', 1);

		$this->assertSame(0, (int) get_network_option(null, 'wu_gridpane_next_write_slot', 0));
	}

	public function test_on_remove_domain_calls_delete_endpoint(): void {

		$this->integration->expects($this->once())
			->method('fetch_all')
			->with('domain', 'domains')
			->willReturn(
				[
					[
						'id'      => 987,
						'url'     => 'example.com',
						'site_id' => 123456,
						'type'    => 'alias',
					],
				]
			);

		$this->integration->expects($this->once())
			->method('send_gridpane_api_request')
			->with(
				'domain/987',
				[],
				'DELETE'
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

	public function test_rate_limited_write_slot_schedules_domain_add(): void {

		$reserved_slot = time() + 30;

		update_network_option(null, 'wu_gridpane_next_write_slot', $reserved_slot);

		$this->integration->expects($this->never())
			->method('send_gridpane_api_request');

		$this->module->on_add_domain('queued.example.com', 1);

		$actions = wu_get_scheduled_actions(
			[
				'hook'     => 'wu_gridpane_retry_add_domain',
				'group'    => 'gridpane',
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'args'     => ['queued.example.com', 1],
				'per_page' => 5,
			],
			'ids'
		);

		$this->assertNotEmpty($actions);
		$this->assertSame($reserved_slot, (int) get_network_option(null, 'wu_gridpane_next_write_slot', 0));
	}

	public function test_queued_domain_add_runs_when_its_write_slot_is_available(): void {

		$domain  = 'queued.example.com';
		$mapping = wu_create_domain(
			[
				'blog_id'        => 1,
				'domain'         => $domain,
				'active'         => true,
				'primary_domain' => false,
				'secure'         => false,
				'stage'          => \WP_Ultimo\Database\Domains\Domain_Stage::DONE,
			]
		);

		$this->assertNotWPError($mapping);

		update_network_option(null, 'wu_gridpane_next_write_slot', time() - 1);

		$this->integration->expects($this->once())
			->method('send_gridpane_api_request')
			->with('domain', $this->isType('array'), 'POST');

		$this->module->retry_add_domain($domain, 1);
	}

	public function test_api_rate_limit_extends_shared_write_cooldown(): void {

		$started_at = time();
		$error      = new \WP_Error(
			'gridpane-http-error',
			'Too many requests',
			[
				'status'      => 429,
				'retry_after' => 600,
			]
		);

		$this->integration->expects($this->once())
			->method('send_gridpane_api_request')
			->willReturn($error);

		$this->module->on_add_domain('limited.example.com', 1);

		$this->assertGreaterThanOrEqual(
			$started_at + 600,
			(int) get_network_option(null, 'wu_gridpane_next_write_slot', 0)
		);

		$actions = wu_get_scheduled_actions(
			[
				'hook'     => 'wu_gridpane_retry_add_domain',
				'group'    => 'gridpane',
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'args'     => ['limited.example.com', 2],
				'per_page' => 5,
			],
			'ids'
		);

		$this->assertNotEmpty($actions);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('test_connection')
			->willReturn(true);

		$result = $this->module->test_connection();

		$this->assertTrue($result);
	}
}
