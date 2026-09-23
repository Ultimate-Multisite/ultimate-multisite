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

		$this->assertCount(3, $fields);
		$this->assertSame('email', $fields['WU_BIGSCOOTS_API_EMAIL']['type']);
		$this->assertSame('password', $fields['WU_BIGSCOOTS_API_KEY']['type']);
		$this->assertArrayHasKey('WU_BIGSCOOTS_PRIMARY_UUID', $fields);
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

	public function test_test_connection_requires_primary_site_uuid(): void {

		$integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$result = $integration->test_connection();

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('bigscoots-missing-primary-uuid', $result->get_error_code());
	}
}
