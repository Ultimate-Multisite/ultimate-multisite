<?php

namespace WP_Ultimo\Integrations\Providers\Rocket;

use WP_UnitTestCase;

class Rocket_Domain_Mapping_Test extends WP_UnitTestCase {

	private Rocket_Domain_Mapping $module;
	private Rocket_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Rocket_Integration::class)
			->onlyMethods(['send_rocket_request'])
			->getMock();

		$this->module = new Rocket_Domain_Mapping();
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

	public function test_on_add_domain_posts_to_domains_endpoint(): void {

		$this->integration->expects($this->once())
			->method('send_rocket_request')
			->with(
				'domains',
				['domain' => 'example.com'],
				'POST'
			)
			->willReturn([
				'response' => ['code' => 201],
				'body'     => '{"id":42}',
			]);

		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_add_domain_handles_wp_error(): void {

		$this->integration->expects($this->once())
			->method('send_rocket_request')
			->willReturn(new \WP_Error('fail', 'API error'));

		// Should not throw — just logs.
		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_fetches_domain_list_first(): void {

		$this->integration->expects($this->atLeast(1))
			->method('send_rocket_request')
			->willReturnCallback(function (string $endpoint, array $data, string $method) {
				if ('GET' === $method) {
					return [
						'response' => ['code' => 200],
						'body'     => '{"data":[{"id":42,"domain":"example.com"}]}',
					];
				}

				return [
					'response' => ['code' => 204],
					'body'     => '',
				];
			});

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_remove_domain_skips_delete_when_domain_not_found(): void {

		// First call (GET domains) returns empty list.
		$this->integration->expects($this->once())
			->method('send_rocket_request')
			->with('domains', [], 'GET')
			->willReturn([
				'response' => ['code' => 200],
				'body'     => '{"data":[]}',
			]);

		$this->module->on_remove_domain('notfound.com', 1);
	}

	public function test_on_add_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_rocket_request');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_is_noop(): void {

		$this->integration->expects($this->never())
			->method('send_rocket_request');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('send_rocket_request')
			->with('domains', [], 'GET')
			->willReturn([
				'response' => ['code' => 200],
				'body'     => '{"data":[]}',
			]);

		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}

	/**
	 * @dataProvider rocket_access_token_response_provider
	 */
	public function test_get_rocket_access_token_uses_login_endpoint_and_supported_token_response(array $response_body, string $expected_token): void {

		delete_site_transient('wu_rocket_token');

		$integration = $this->getMockBuilder(Rocket_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')
			->willReturnMap(
				[
					['WU_ROCKET_EMAIL', 'rocket-user@example.com'],
					['WU_ROCKET_PASSWORD', 'rocket-password'],
				]
			);

		$request_seen = false;
		$http_filter  = function ($preempt, array $args, string $url) use (&$request_seen, $response_body) {
			$request_seen = true;

			$this->assertSame('https://api.rocket.net/v1/login', $url);
			$this->assertSame('POST', $args['method']);
			$this->assertSame('application/json', $args['headers']['Content-Type']);
			$this->assertSame('application/json', $args['headers']['Accept']);
			$this->assertSame(
				[
					'username' => 'rocket-user@example.com',
					'password' => 'rocket-password',
				],
				json_decode($args['body'], true)
			);

			return [
				'headers'  => [],
				'body'     => wp_json_encode($response_body),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			];
		};

		add_filter('pre_http_request', $http_filter, 10, 3);

		try {
			$token = $integration->get_rocket_access_token();
		} finally {
			remove_filter('pre_http_request', $http_filter, 10);
			delete_site_transient('wu_rocket_token');
		}

		$this->assertTrue($request_seen);
		$this->assertSame($expected_token, $token);
	}

	public function rocket_access_token_response_provider(): array {

		return [
			'direct token'          => [
				['token' => 'direct-token'],
				'direct-token',
			],
			'direct access token'   => [
				['access_token' => 'direct-access-token'],
				'direct-access-token',
			],
			'nested token'          => [
				['result' => ['token' => 'nested-token']],
				'nested-token',
			],
			'nested access token'   => [
				['result' => ['access_token' => 'nested-access-token']],
				'nested-access-token',
			],
		];
	}
}
