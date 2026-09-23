<?php

namespace WP_Ultimo\Integrations\Providers\GridPane;

use WP_UnitTestCase;

class GridPane_Integration_Test extends WP_UnitTestCase {

	private GridPane_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = new GridPane_Integration();
		$this->integration->delete_credentials();
	}

	public function tearDown(): void {

		$this->integration->delete_credentials();

		parent::tearDown();
	}

	public function test_configuration_fields_use_encrypted_credential_store_keys(): void {

		$fields = $this->integration->get_fields();

		$this->assertArrayHasKey('WU_GRIDPANE_API_TOKEN', $fields);
		$this->assertArrayHasKey('WU_GRIDPANE_SITE_ID', $fields);
		$this->assertArrayHasKey('WU_GRIDPANE_SERVER_ID', $fields);
		$this->assertArrayHasKey('WU_GRIDPANE_DNS_MANAGEMENT', $fields);
		$this->assertArrayHasKey('WU_GRIDPANE_DNS_INTEGRATION_ID', $fields);
		$this->assertSame('password', $fields['WU_GRIDPANE_API_TOKEN']['type']);
		$this->assertSame(
			['none_none', 'cloudflare_full', 'cloudflare_challenge', 'dnsme_full', 'dnsme_challenge'],
			array_keys($fields['WU_GRIDPANE_DNS_MANAGEMENT']['options'])
		);
		$this->assertNotContains('no-config', $this->integration->get_supports());
	}

	public function test_token_only_configuration_is_ready_for_site_discovery(): void {

		$this->integration->save_credentials(['WU_GRIDPANE_API_TOKEN' => 'test-bearer-token']);

		$this->assertTrue($this->integration->is_setup());
		$this->assertSame([], $this->integration->get_missing_constants());
	}

	public function test_api_request_uses_bearer_token_and_json_body(): void {

		$this->integration->save_credentials(
			[
				'WU_GRIDPANE_API_TOKEN' => 'test-bearer-token',
				'WU_GRIDPANE_SITE_ID'   => '123456',
				'WU_GRIDPANE_SERVER_ID' => '12345',
			]
		);

		$callback = function ($preempt, $args, $url) {
			$this->assertSame('https://my.gridpane.com/oauth/api/v1/domain', $url);
			$this->assertSame('POST', $args['method']);
			$this->assertSame('Bearer test-bearer-token', $args['headers']['Authorization']);
			$this->assertSame('application/json', $args['headers']['Content-Type']);
			$this->assertSame(
				[
					'domain_url' => 'mapped.example.com',
					'type'       => 'alias',
				],
				json_decode($args['body'], true)
			);

			return [
				'headers'  => [],
				'body'     => '{"data":{"id":987}}',
				'response' => [
					'code'    => 201,
					'message' => 'Created',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		add_filter('pre_http_request', $callback, 10, 3);

		try {
			$result = $this->integration->send_gridpane_api_request(
				'domain',
				[
					'domain_url' => 'mapped.example.com',
					'type'       => 'alias',
				]
			);
		} finally {
			remove_filter('pre_http_request', $callback, 10);
		}

		$this->assertSame(987, $result['data']['id']);
	}

	public function test_http_rate_limit_error_exposes_retry_after(): void {

		$this->integration->save_credentials(['WU_GRIDPANE_API_TOKEN' => 'test-bearer-token']);

		$callback = function () {
			return [
				'headers'  => ['retry-after' => '75'],
				'body'     => '{"message":"Too many requests"}',
				'response' => [
					'code'    => 429,
					'message' => 'Too Many Requests',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		add_filter('pre_http_request', $callback);

		try {
			$result = $this->integration->send_gridpane_api_request('domain', [], 'GET');
		} finally {
			remove_filter('pre_http_request', $callback);
		}

		$this->assertWPError($result);
		$this->assertSame(429, $result->get_error_data()['status']);
		$this->assertSame(75, $result->get_error_data()['retry_after']);
	}

	public function test_discovery_matches_network_host_and_stores_ids(): void {

		$network_host = wp_parse_url(network_site_url(), PHP_URL_HOST);
		$integration  = $this->getMockBuilder(GridPane_Integration::class)
			->onlyMethods(['fetch_all'])
			->getMock();

		$integration->save_credentials(['WU_GRIDPANE_API_TOKEN' => 'test-bearer-token']);
		$integration->expects($this->once())
			->method('fetch_all')
			->with('site')
			->willReturn(
				[
					[
						'id'        => 123456,
						'server_id' => 12345,
						'url'       => 'https://' . $network_host,
					],
				]
			);

		$result = $integration->discover_site_configuration();

		$this->assertSame([
			'site_id'   => 123456,
			'server_id' => 12345,
		], $result);
		$this->assertSame('123456', $integration->get_credential('WU_GRIDPANE_SITE_ID'));
		$this->assertSame('12345', $integration->get_credential('WU_GRIDPANE_SERVER_ID'));
		$this->assertSame('test-bearer-token', $integration->get_api_token());

		$integration->delete_credentials();
	}

	public function test_fetch_all_returns_error_instead_of_partial_results_at_safety_limit(): void {

		$integration = $this->getMockBuilder(GridPane_Integration::class)
			->onlyMethods(['send_gridpane_api_request'])
			->getMock();

		$integration->expects($this->exactly(100))
			->method('send_gridpane_api_request')
			->willReturn(
				[
					'data'  => [],
					'links' => ['next' => 'site?page=2'],
				]
			);

		$result = $integration->fetch_all('site');

		$this->assertWPError($result);
		$this->assertSame('gridpane-pagination-limit', $result->get_error_code());
	}

	public function test_legacy_api_key_is_accepted_as_bearer_token(): void {

		$this->integration->save_credentials(['WU_GRIDPANE_API_KEY' => 'legacy-token']);

		$this->assertSame('legacy-token', $this->integration->get_api_token());
	}
}
