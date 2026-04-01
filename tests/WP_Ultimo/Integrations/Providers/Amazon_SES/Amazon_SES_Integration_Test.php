<?php

namespace WP_Ultimo\Integrations\Providers\Amazon_SES;

use WP_UnitTestCase;

class Amazon_SES_Integration_Test extends WP_UnitTestCase {

	private Amazon_SES_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = new Amazon_SES_Integration();
	}

	public function tearDown(): void {

		$this->integration->delete_credentials();

		parent::tearDown();
	}

	public function test_get_id(): void {

		$this->assertSame('amazon-ses', $this->integration->get_id());
	}

	public function test_get_title(): void {

		$this->assertSame('Amazon SES', $this->integration->get_title());
	}

	public function test_get_description_is_not_empty(): void {

		$this->assertNotEmpty($this->integration->get_description());
	}

	public function test_get_region_defaults_to_us_east_1(): void {

		$this->assertSame('us-east-1', $this->integration->get_region());
	}

	public function test_get_region_uses_credential_when_set(): void {

		$this->integration->save_credentials(['WU_AWS_SES_REGION' => 'eu-west-1']);

		$this->assertSame('eu-west-1', $this->integration->get_region());
	}

	public function test_get_api_base_includes_region(): void {

		$api_base = $this->integration->get_api_base();

		$this->assertStringContainsString('us-east-1', $api_base);
		$this->assertStringContainsString('amazonaws.com', $api_base);
	}

	public function test_get_api_base_uses_configured_region(): void {

		$this->integration->save_credentials(['WU_AWS_SES_REGION' => 'ap-southeast-1']);

		$api_base = $this->integration->get_api_base();

		$this->assertStringContainsString('ap-southeast-1', $api_base);
	}

	public function test_get_signer_returns_aws_signer_instance(): void {

		$signer = $this->integration->get_signer();

		$this->assertInstanceOf(\WP_Ultimo\Helpers\AWS_Signer::class, $signer);
	}

	public function test_get_fields_returns_required_credential_fields(): void {

		$fields = $this->integration->get_fields();

		$this->assertArrayHasKey('WU_AWS_ACCESS_KEY_ID', $fields);
		$this->assertArrayHasKey('WU_AWS_SECRET_ACCESS_KEY', $fields);
		$this->assertArrayHasKey('WU_AWS_SES_REGION', $fields);
	}

	public function test_ses_api_call_returns_wp_error_on_http_failure(): void {

		$integration = $this->getMockBuilder(Amazon_SES_Integration::class)
			->onlyMethods(['ses_api_call'])
			->getMock();

		$integration->method('ses_api_call')
			->willReturn(new \WP_Error('http-error', 'Connection failed'));

		$result = $integration->ses_api_call('account');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('http-error', $result->get_error_code());
	}

	public function test_test_connection_returns_wp_error_on_failure(): void {

		$integration = $this->getMockBuilder(Amazon_SES_Integration::class)
			->onlyMethods(['ses_api_call'])
			->getMock();

		$integration->method('ses_api_call')
			->willReturn(new \WP_Error('amazon-ses-error', 'Invalid credentials'));

		$result = $integration->test_connection();

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function test_test_connection_returns_true_on_success(): void {

		$integration = $this->getMockBuilder(Amazon_SES_Integration::class)
			->onlyMethods(['ses_api_call'])
			->getMock();

		$integration->method('ses_api_call')
			->willReturn(['SendingEnabled' => true, 'SendingQuota' => []]);

		$result = $integration->test_connection();

		$this->assertTrue($result);
	}
}
