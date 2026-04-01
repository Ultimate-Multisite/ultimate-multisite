<?php

namespace WP_Ultimo\Helpers;

use WP_UnitTestCase;

class AWS_Signer_Test extends WP_UnitTestCase {

	private AWS_Signer $signer;

	public function setUp(): void {

		parent::setUp();

		$this->signer = new AWS_Signer(
			'AKIAIOSFODNN7EXAMPLE',
			'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
			'us-east-1',
			'ses'
		);
	}

	public function test_sign_returns_required_headers(): void {

		$headers = $this->signer->sign('GET', 'https://email.us-east-1.amazonaws.com/v2/account');

		$this->assertArrayHasKey('Authorization', $headers);
		$this->assertArrayHasKey('x-amz-date', $headers);
		$this->assertArrayHasKey('x-amz-content-sha256', $headers);
	}

	public function test_authorization_header_format(): void {

		$headers = $this->signer->sign('GET', 'https://email.us-east-1.amazonaws.com/v2/account');

		$this->assertStringStartsWith('AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/', $headers['Authorization']);
		$this->assertStringContainsString('SignedHeaders=host;x-amz-content-sha256;x-amz-date', $headers['Authorization']);
		$this->assertStringContainsString('Signature=', $headers['Authorization']);
	}

	public function test_authorization_includes_region_and_service(): void {

		$headers = $this->signer->sign('GET', 'https://email.us-east-1.amazonaws.com/v2/account');

		$this->assertStringContainsString('/us-east-1/ses/aws4_request', $headers['Authorization']);
	}

	public function test_amz_date_format(): void {

		$headers = $this->signer->sign('GET', 'https://email.us-east-1.amazonaws.com/v2/account');

		// Should be in format YYYYMMDDTHHmmssZ
		$this->assertMatchesRegularExpression('/^\d{8}T\d{6}Z$/', $headers['x-amz-date']);
	}

	public function test_content_sha256_is_hash_of_empty_payload(): void {

		$headers = $this->signer->sign('GET', 'https://email.us-east-1.amazonaws.com/v2/account');

		$expected = hash('sha256', '');
		$this->assertSame($expected, $headers['x-amz-content-sha256']);
	}

	public function test_content_sha256_changes_with_payload(): void {

		$headers_empty   = $this->signer->sign('GET', 'https://email.us-east-1.amazonaws.com/v2/account', '');
		$headers_payload = $this->signer->sign('POST', 'https://email.us-east-1.amazonaws.com/v2/outbound-emails', '{"test":"value"}');

		$this->assertNotSame($headers_empty['x-amz-content-sha256'], $headers_payload['x-amz-content-sha256']);
	}

	public function test_different_regions_produce_different_signatures(): void {

		$signer_us = new AWS_Signer('KEY', 'SECRET', 'us-east-1', 'ses');
		$signer_eu = new AWS_Signer('KEY', 'SECRET', 'eu-west-1', 'ses');

		$headers_us = $signer_us->sign('GET', 'https://email.us-east-1.amazonaws.com/v2/account');
		$headers_eu = $signer_eu->sign('GET', 'https://email.eu-west-1.amazonaws.com/v2/account');

		$this->assertNotSame($headers_us['Authorization'], $headers_eu['Authorization']);
	}

	public function test_different_services_produce_different_signatures(): void {

		$signer_ses      = new AWS_Signer('KEY', 'SECRET', 'us-east-1', 'ses');
		$signer_workmail = new AWS_Signer('KEY', 'SECRET', 'us-east-1', 'workmail');

		$headers_ses      = $signer_ses->sign('GET', 'https://email.us-east-1.amazonaws.com/v2/account');
		$headers_workmail = $signer_workmail->sign('GET', 'https://email.us-east-1.amazonaws.com/v2/account');

		$this->assertNotSame($headers_ses['Authorization'], $headers_workmail['Authorization']);
	}

	public function test_sign_with_query_string(): void {

		$headers = $this->signer->sign(
			'GET',
			'https://email.us-east-1.amazonaws.com/v2/email-identities?PageSize=10&NextToken=abc'
		);

		$this->assertArrayHasKey('Authorization', $headers);
		$this->assertStringStartsWith('AWS4-HMAC-SHA256', $headers['Authorization']);
	}

	public function test_sign_post_with_payload(): void {

		$payload = wp_json_encode(['EmailIdentity' => 'example.com']);
		$headers = $this->signer->sign(
			'POST',
			'https://email.us-east-1.amazonaws.com/v2/email-identities',
			$payload
		);

		$this->assertSame(hash('sha256', $payload), $headers['x-amz-content-sha256']);
	}
}
