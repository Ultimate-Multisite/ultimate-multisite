<?php

namespace WP_Ultimo\Integrations\Providers\Hostinger;

use WP_UnitTestCase;

class Hostinger_Integration_Test extends WP_UnitTestCase {

	public function test_constructor_sets_id_and_title(): void {

		$integration = new Hostinger_Integration();

		$this->assertSame('hostinger', $integration->get_id());
		$this->assertSame('Hostinger', $integration->get_title());
	}

	public function test_constructor_declares_required_token_constant(): void {

		$integration = new Hostinger_Integration();

		$this->assertContains('WU_HOSTINGER_API_TOKEN', $integration->get_constants());
	}

	public function test_constructor_declares_autossl_support(): void {

		$integration = new Hostinger_Integration();

		$this->assertContains('autossl', $integration->get_supports());
		$this->assertTrue($integration->supports('autossl'));
	}

	public function test_get_fields_returns_only_api_token(): void {

		$integration = new Hostinger_Integration();
		$fields      = $integration->get_fields();

		$this->assertArrayHasKey('WU_HOSTINGER_API_TOKEN', $fields);
		$this->assertCount(1, $fields);
		$this->assertSame('password', $fields['WU_HOSTINGER_API_TOKEN']['type']);
	}

	public function test_detect_returns_true_when_token_is_set(): void {

		$integration = $this->getMockBuilder(Hostinger_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')
			->with('WU_HOSTINGER_API_TOKEN')
			->willReturn('abc123');

		$this->assertTrue($integration->detect());
	}

	public function test_detect_returns_false_when_token_missing(): void {

		$integration = $this->getMockBuilder(Hostinger_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$this->assertFalse($integration->detect());
	}

	public function test_test_connection_returns_wp_error_when_token_missing(): void {

		$integration = $this->getMockBuilder(Hostinger_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$result = $integration->test_connection();

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('hostinger-no-token', $result->get_error_code());
	}

	public function test_test_connection_calls_portfolio_endpoint(): void {

		$integration = $this->getMockBuilder(Hostinger_Integration::class)
			->onlyMethods(['get_credential', 'hostinger_api_call'])
			->getMock();

		$integration->method('get_credential')->willReturn('valid-token');

		$integration->expects($this->once())
			->method('hostinger_api_call')
			->with('/api/domains/v1/portfolio')
			->willReturn([]);

		$this->assertTrue($integration->test_connection());
	}

	public function test_test_connection_propagates_api_wp_error(): void {

		$integration = $this->getMockBuilder(Hostinger_Integration::class)
			->onlyMethods(['get_credential', 'hostinger_api_call'])
			->getMock();

		$integration->method('get_credential')->willReturn('valid-token');

		$error = new \WP_Error('hostinger-http-error', 'Unauthorized');

		$integration->method('hostinger_api_call')->willReturn($error);

		$this->assertSame($error, $integration->test_connection());
	}

	public function test_hostinger_api_call_returns_wp_error_when_token_missing(): void {

		$integration = $this->getMockBuilder(Hostinger_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$result = $integration->hostinger_api_call('/api/dns/v1/zones/example.com');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('hostinger-no-token', $result->get_error_code());
	}
}
