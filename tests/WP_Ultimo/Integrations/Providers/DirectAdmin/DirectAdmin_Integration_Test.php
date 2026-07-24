<?php

namespace WP_Ultimo\Integrations\Providers\DirectAdmin;

use WP_UnitTestCase;

class DirectAdmin_Integration_Test extends WP_UnitTestCase {

	public function test_constructor_sets_id_and_title(): void {

		$integration = new DirectAdmin_Integration();

		$this->assertSame('directadmin', $integration->get_id());
		$this->assertSame('DirectAdmin', $integration->get_title());
	}

	public function test_constructor_declares_required_constants(): void {

		$integration = new DirectAdmin_Integration();
		$constants   = $integration->get_constants();

		$this->assertContains('WU_DIRECTADMIN_HOST', $constants);
		$this->assertContains('WU_DIRECTADMIN_USERNAME', $constants);
		$this->assertContains('WU_DIRECTADMIN_DOMAIN', $constants);
		$this->assertContains(['WU_DIRECTADMIN_API_TOKEN', 'WU_DIRECTADMIN_PASSWORD'], $constants);
	}

	public function test_constructor_declares_autossl_support(): void {

		$integration = new DirectAdmin_Integration();

		$this->assertContains('autossl', $integration->get_supports());
		$this->assertTrue($integration->supports('autossl'));
	}

	public function test_get_fields_returns_directadmin_credentials(): void {

		$integration = new DirectAdmin_Integration();
		$fields      = $integration->get_fields();

		$this->assertArrayHasKey('WU_DIRECTADMIN_HOST', $fields);
		$this->assertArrayHasKey('WU_DIRECTADMIN_PORT', $fields);
		$this->assertArrayHasKey('WU_DIRECTADMIN_USERNAME', $fields);
		$this->assertArrayHasKey('WU_DIRECTADMIN_API_TOKEN', $fields);
		$this->assertArrayHasKey('WU_DIRECTADMIN_PASSWORD', $fields);
		$this->assertArrayHasKey('WU_DIRECTADMIN_DOMAIN', $fields);
		$this->assertSame('password', $fields['WU_DIRECTADMIN_API_TOKEN']['type']);
	}

	public function test_detect_returns_true_when_host_and_username_are_set(): void {

		$integration = $this->getMockBuilder(DirectAdmin_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				$map = [
					'WU_DIRECTADMIN_HOST'     => 'server.example.com',
					'WU_DIRECTADMIN_USERNAME' => 'admin|customer',
				];

				return $map[ $key ] ?? '';
			});

		$this->assertTrue($integration->detect());
	}

	public function test_detect_returns_false_when_credentials_are_missing(): void {

		$integration = $this->getMockBuilder(DirectAdmin_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$this->assertFalse($integration->detect());
	}

	public function test_test_connection_calls_login_test_endpoint(): void {

		$integration = $this->getMockBuilder(DirectAdmin_Integration::class)
			->onlyMethods(['directadmin_api_request'])
			->getMock();

		$integration->expects($this->once())
			->method('directadmin_api_request')
			->with('/CMD_API_LOGIN_TEST')
			->willReturn([
				'error' => '0',
				'text'  => 'Login OK',
			]);

		$this->assertTrue($integration->test_connection());
	}

	public function test_test_connection_returns_error_for_login_page_response(): void {

		$integration = $this->getMockBuilder(DirectAdmin_Integration::class)
			->onlyMethods(['directadmin_api_request'])
			->getMock();

		$integration->method('directadmin_api_request')->willReturn([
			'raw' => '<html><body>DirectAdmin Login</body></html>',
		]);

		$result = $integration->test_connection();

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('directadmin-login-test-failed', $result->get_error_code());
	}

	public function test_test_connection_detects_url_encoded_login_page_response(): void {

		$integration = $this->getMockBuilder(DirectAdmin_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')
			->willReturnCallback(
				function (string $key) {
					$credentials = [
						'WU_DIRECTADMIN_HOST'      => 'server.example.com',
						'WU_DIRECTADMIN_USERNAME'  => 'admin',
						'WU_DIRECTADMIN_API_TOKEN' => 'login-key',
					];

					return $credentials[ $key ] ?? '';
				}
			);

		add_filter(
			'pre_http_request',
			function () {
				return [
					'headers'  => [],
					'body'     => '<html><body><input name="username" value="admin"></body></html>',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}
		);

		try {
			$result = $integration->test_connection();
		} finally {
			remove_all_filters('pre_http_request');
		}

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('directadmin-login-test-failed', $result->get_error_code());
	}

	public function test_directadmin_api_request_returns_error_when_host_missing(): void {

		$integration = $this->getMockBuilder(DirectAdmin_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$integration->method('get_credential')->willReturn('');

		$result = $integration->directadmin_api_request('/CMD_API_LOGIN_TEST');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('directadmin-no-host', $result->get_error_code());
	}
}
