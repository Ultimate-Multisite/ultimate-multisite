<?php

namespace WP_Ultimo\Integrations\Providers\BigScoots;

use WP_UnitTestCase;

class BigScoots_Integration_Test extends WP_UnitTestCase {

	public function test_constructor_configures_provider(): void {

		$integration = new BigScoots_Integration();

		$this->assertSame('bigscoots', $integration->get_id());
		$this->assertSame('BigScoots', $integration->get_title());
		$this->assertContains('WU_BIGSCOOTS_API_EMAIL', $integration->get_constants());
		$this->assertContains('WU_BIGSCOOTS_API_KEY', $integration->get_constants());
		$this->assertContains('WU_BIGSCOOTS_PRIMARY_UUID', $integration->get_constants());
		$this->assertTrue($integration->supports('autossl'));
	}

	public function test_get_fields_returns_required_credentials(): void {

		$fields = (new BigScoots_Integration())->get_fields();

		$this->assertCount(2, $fields);
		$this->assertSame('email', $fields['WU_BIGSCOOTS_API_EMAIL']['type']);
		$this->assertSame('password', $fields['WU_BIGSCOOTS_API_KEY']['type']);
		$this->assertArrayNotHasKey('WU_BIGSCOOTS_PRIMARY_UUID', $fields);
	}

	public function test_connection_test_is_ready_before_site_selection(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturnMap(
			[
				['WU_BIGSCOOTS_API_EMAIL', 'owner@example.com'],
				['WU_BIGSCOOTS_API_KEY', 'secret-key'],
				['WU_BIGSCOOTS_PRIMARY_UUID', ''],
			]
		);

		$this->assertTrue($integration->is_ready_for_connection_test());
		$this->assertSame([], $integration->get_missing_connection_test_constants());
	}

	public function test_get_configuration_instructions_explains_site_selection(): void {

		$instructions = (new BigScoots_Integration())->get_configuration_instructions();

		$this->assertNotEmpty($instructions['title']);
		$this->assertStringContainsString('UUID', $instructions['description']);
		$this->assertCount(5, $instructions['steps']);
		$this->assertStringContainsString('href="https://wpo.bigscoots.com"', $instructions['steps'][0]);
		$this->assertStringContainsString('href="https://wpo.bigscoots.com/user/profile/edit"', $instructions['steps'][0]);
		$this->assertStringContainsString('migrated to the newer WPO Portal', $instructions['steps'][1]);
		$this->assertStringContainsString('multisite', $instructions['steps'][4]);
	}

	public function test_get_resource_selection_fields_returns_api_sites(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['get_available_sites'])
			->getMock();

		$integration->method('get_available_sites')->willReturn(
			['site-uuid' => 'example.com — site-uuid']
		);

		$fields = $integration->get_resource_selection_fields();

		$this->assertSame('select', $fields['WU_BIGSCOOTS_PRIMARY_UUID']['type']);
		$this->assertSame(['site-uuid' => 'example.com — site-uuid'], $fields['WU_BIGSCOOTS_PRIMARY_UUID']['options']);
	}

	public function test_existing_site_reconciliation_schedules_only_one_coordinator_action(): void {

		$queries     = 0;
		$query_spy   = function ($sites) use (&$queries) {

			++$queries;

			return $sites;
		};
		$integration = new BigScoots_Integration();

		add_filter('sites_pre_query', $query_spy);

		try {
			$this->assertGreaterThan(0, $integration->schedule_existing_site_reconciliation());
			$this->assertSame(0, $integration->schedule_existing_site_reconciliation());
		} finally {
			remove_filter('sites_pre_query', $query_spy);
		}

		$args = ['last_site_id' => 0];

		$this->assertSame(0, $queries);
		$this->assertNotFalse(wu_next_scheduled_action(BigScoots_Integration::RECONCILE_BATCH_HOOK, $args, 'domain'));

		wu_unschedule_all_actions(BigScoots_Integration::RECONCILE_BATCH_HOOK, $args, 'domain');
	}

	public function test_existing_site_reconciliation_batch_is_queued_once_per_subsite(): void {

		$site_ids    = [
			self::factory()->blog->create(),
			self::factory()->blog->create(),
		];
		$integration = new BigScoots_Integration();

		$this->assertSame(2, $integration->reconcile_existing_sites_batch());

		foreach ($site_ids as $site_id) {
			$args = ['site_id' => $site_id];

			$this->assertNotFalse(wu_next_scheduled_action(BigScoots_Integration::RECONCILE_SITE_HOOK, $args, 'domain'));
		}

		$this->assertFalse(
			wu_next_scheduled_action(
				BigScoots_Integration::RECONCILE_SITE_HOOK,
				['site_id' => get_main_site_id(get_current_network_id())],
				'domain'
			)
		);
		$this->assertSame(0, $integration->reconcile_existing_sites_batch());

		foreach ($site_ids as $site_id) {
			wu_unschedule_all_actions(BigScoots_Integration::RECONCILE_SITE_HOOK, ['site_id' => $site_id], 'domain');
		}
	}

	public function test_full_reconciliation_batch_schedules_the_next_batch(): void {

		$site_ids  = range(100, 149);
		$query_spy = function ($sites, \WP_Site_Query $query) use ($site_ids) {

			$this->assertSame(50, $query->query_vars['number']);

			return $site_ids;
		};

		add_filter('sites_pre_query', $query_spy, 10, 2);

		try {
			$this->assertSame(50, (new BigScoots_Integration())->reconcile_existing_sites_batch());
		} finally {
			remove_filter('sites_pre_query', $query_spy, 10);
		}

		$next_args = ['last_site_id' => 149];

		$this->assertNotFalse(wu_next_scheduled_action(BigScoots_Integration::RECONCILE_BATCH_HOOK, $next_args, 'domain'));

		foreach ($site_ids as $site_id) {
			wu_unschedule_all_actions(BigScoots_Integration::RECONCILE_SITE_HOOK, ['site_id' => $site_id], 'domain');
		}

		wu_unschedule_all_actions(BigScoots_Integration::RECONCILE_BATCH_HOOK, $next_args, 'domain');
	}

	public function test_reconciliation_cursor_continues_after_the_last_processed_site_id(): void {

		$site_ids = [
			self::factory()->blog->create(),
			self::factory()->blog->create(),
			self::factory()->blog->create(),
		];

		$this->assertSame(2, (new BigScoots_Integration())->reconcile_existing_sites_batch($site_ids[0]));
		$this->assertFalse(
			wu_next_scheduled_action(
				BigScoots_Integration::RECONCILE_SITE_HOOK,
				['site_id' => $site_ids[0]],
				'domain'
			)
		);

		foreach (array_slice($site_ids, 1) as $site_id) {
			$args = ['site_id' => $site_id];

			$this->assertNotFalse(wu_next_scheduled_action(BigScoots_Integration::RECONCILE_SITE_HOOK, $args, 'domain'));
			wu_unschedule_all_actions(BigScoots_Integration::RECONCILE_SITE_HOOK, $args, 'domain');
		}
	}

	public function test_selecting_primary_site_starts_existing_site_reconciliation(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['is_setup', 'schedule_existing_site_reconciliation'])
			->getMock();

		$integration->expects($this->once())->method('is_setup')->willReturn(true);
		$integration->expects($this->once())->method('schedule_existing_site_reconciliation')->willReturn(0);

		$integration->update_credentials(['WU_BIGSCOOTS_PRIMARY_UUID' => 'primary-uuid']);

		delete_network_option(null, 'wu_hosting_credential_WU_BIGSCOOTS_PRIMARY_UUID');
	}

	public function test_detect_requires_all_credentials(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturnMap(
			[
				['WU_BIGSCOOTS_API_EMAIL', 'owner@example.com'],
				['WU_BIGSCOOTS_API_KEY', 'secret-key'],
				['WU_BIGSCOOTS_PRIMARY_UUID', 'primary-uuid'],
			]
		);

		$this->assertTrue($integration->detect());
	}

	public function test_api_call_requires_authentication_credentials(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$result = $integration->bigscoots_api_call('/v1/accounts/');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('bigscoots-missing-credentials', $result->get_error_code());
	}

	public function test_api_call_sends_required_headers(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturnMap(
			[
				['WU_BIGSCOOTS_API_EMAIL', 'owner@example.com'],
				['WU_BIGSCOOTS_API_KEY', 'secret-key'],
			]
		);

		$intercept = function ($response, array $args, string $url) {

			$this->assertSame('https://api.bigscoots.com/v1/accounts/', $url);
			$this->assertSame('owner@example.com', $args['headers']['x-auth-email']);
			$this->assertSame('secret-key', $args['headers']['x-auth-key']);

			return [
				'headers'  => [],
				'body'     => '{"primaryOwner":{"account_uuid":"account-uuid"}}',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			];
		};

		add_filter('pre_http_request', $intercept, 10, 3);

		try {
			$result = $integration->bigscoots_api_call('/v1/accounts/');
		} finally {
			remove_filter('pre_http_request', $intercept, 10);
		}

		$this->assertSame('account-uuid', $result->primaryOwner->account_uuid); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	public function test_api_call_returns_top_level_api_error_message(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('configured');

		$intercept = function () {

			return [
				'headers'  => [],
				'body'     => '{"success":false,"error":"Your plan type should be multisite or hybrid."}',
				'response' => [
					'code'    => 400,
					'message' => 'Bad Request',
				],
				'cookies'  => [],
			];
		};

		add_filter('pre_http_request', $intercept);

		try {
			$result = $integration->bigscoots_api_call('/v1/multi-sites/sub-sites/primary-uuid');
		} finally {
			remove_filter('pre_http_request', $intercept);
		}

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('bigscoots-http-error', $result->get_error_code());
		$this->assertStringContainsString('Your plan type should be multisite or hybrid.', $result->get_error_message());
	}

	public function test_api_call_returns_nested_api_error_message(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('configured');

		$intercept = function () {

			return [
				'headers'  => [],
				'body'     => '{"success":false,"response":{"error":"Your plan type should be multisite or hybrid."}}',
				'response' => [
					'code'    => 400,
					'message' => 'Bad Request',
				],
				'cookies'  => [],
			];
		};

		add_filter('pre_http_request', $intercept);

		try {
			$result = $integration->bigscoots_api_call('/v1/multi-sites/sub-sites/primary-uuid');
		} finally {
			remove_filter('pre_http_request', $intercept);
		}

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertStringContainsString('Your plan type should be multisite or hybrid.', $result->get_error_message());
	}

	public function test_test_connection_uses_primary_site_endpoint(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['bigscoots_api_call', 'get_credential'])
			->getMock();

		$integration->method('get_credential')
			->with('WU_BIGSCOOTS_PRIMARY_UUID')
			->willReturn('primary-uuid');

		$integration->expects($this->once())
			->method('bigscoots_api_call')
			->with('/v1/multi-sites/sub-sites/primary-uuid')
			->willReturn((object) []);

		$this->assertTrue($integration->test_connection());
	}

	public function test_test_connection_discovers_sites_before_uuid_selection(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['get_available_sites', 'get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');
		$integration->expects($this->once())
			->method('get_available_sites')
			->willReturn(['site-uuid' => 'example.com — site-uuid']);

		$this->assertTrue($integration->test_connection());
	}

	public function test_get_available_sites_discovers_primary_sites(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['bigscoots_api_call'])
			->getMock();

		$integration->method('bigscoots_api_call')->willReturnCallback(
			function (string $endpoint) {

				if ('/v1/accounts/' === $endpoint) {
					return (object) [
						'response' => (object) [
							'primaryOwner' => (object) ['account_uuid' => 'account-uuid'],
						],
					];
				}

				$this->assertSame('/v1/sites/account/account-uuid', $endpoint);

				return (object) [
					'response' => [
						(object) [
							'type'      => 'primary',
							'domain'    => 'example.com',
							'site_uuid' => 'site-uuid',
						],
						(object) [
							'type'      => 'staging',
							'domain'    => 'staging.example.com',
							'site_uuid' => 'staging-uuid',
						],
						(object) [
							'domain'    => 'unknown.example.com',
							'site_uuid' => 'unknown-uuid',
						],
					],
				];
			}
		);

		$sites = $integration->get_available_sites();

		$this->assertSame(['site-uuid' => 'example.com — site-uuid'], $sites);
	}
}
