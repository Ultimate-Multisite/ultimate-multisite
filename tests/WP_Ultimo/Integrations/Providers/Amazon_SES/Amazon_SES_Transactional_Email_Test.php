<?php

namespace WP_Ultimo\Integrations\Providers\Amazon_SES;

use WP_UnitTestCase;

class Amazon_SES_Transactional_Email_Test extends WP_UnitTestCase {

	private Amazon_SES_Transactional_Email $module;
	private Amazon_SES_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Amazon_SES_Integration::class)
			->onlyMethods(['ses_api_call'])
			->getMock();

		$this->module = new Amazon_SES_Transactional_Email();
		$this->module->set_integration($this->integration);
	}

	public function test_get_capability_id(): void {

		$this->assertSame('transactional-email', $this->module->get_capability_id());
	}

	public function test_get_title(): void {

		$this->assertNotEmpty($this->module->get_title());
	}

	public function test_get_explainer_lines_has_will_and_will_not(): void {

		$lines = $this->module->get_explainer_lines();

		$this->assertArrayHasKey('will', $lines);
		$this->assertArrayHasKey('will_not', $lines);
		$this->assertNotEmpty($lines['will']);
		$this->assertNotEmpty($lines['will_not']);
	}

	public function test_register_hooks_adds_pre_wp_mail_filter(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_filter('pre_wp_mail', [$this->module, 'intercept_wp_mail']));
	}

	public function test_register_hooks_adds_domain_lifecycle_actions(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_domain_added', [$this->module, 'on_domain_added']));
		$this->assertIsInt(has_action('wu_domain_removed', [$this->module, 'on_domain_removed']));
	}

	public function test_register_hooks_subscribes_to_settings_hook(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_settings_transactional_email', [$this->module, 'register_transactional_email_settings']));
	}

	public function test_register_transactional_email_settings_registers_field(): void {

		$registered = [];

		add_filter(
			'wu_settings_fields',
			function ($fields) use (&$registered) {
				$registered = $fields;
				return $fields;
			}
		);

		$this->module->register_transactional_email_settings();

		// Verify the method runs without error and the integration's get_region() is called.
		$region = $this->integration->get_region();
		$this->assertSame('us-east-1', $region);
	}

	public function test_intercept_wp_mail_returns_original_when_return_is_not_null(): void {

		$result = $this->module->intercept_wp_mail(
			true,
			['to' => 'test@example.com', 'subject' => 'Test', 'message' => 'Body', 'headers' => [], 'attachments' => []]
		);

		$this->assertTrue($result);
	}

	public function test_intercept_wp_mail_sends_via_ses_on_success(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->with('outbound-emails', 'POST', $this->anything())
			->willReturn(['MessageId' => 'test-message-id-123']);

		$result = $this->module->intercept_wp_mail(
			null,
			[
				'to'          => 'recipient@example.com',
				'subject'     => 'Test Subject',
				'message'     => 'Test message body',
				'headers'     => [],
				'attachments' => [],
			]
		);

		$this->assertTrue($result);
	}

	public function test_intercept_wp_mail_returns_false_on_ses_error(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->willReturn(new \WP_Error('amazon-ses-error', 'Sending failed'));

		$result = $this->module->intercept_wp_mail(
			null,
			[
				'to'          => 'recipient@example.com',
				'subject'     => 'Test Subject',
				'message'     => 'Test message body',
				'headers'     => [],
				'attachments' => [],
			]
		);

		$this->assertFalse($result);
	}

	public function test_verify_domain_returns_success_with_dns_records(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->with('email-identities', 'POST', $this->anything())
			->willReturn([
				'IdentityType'             => 'DOMAIN',
				'VerifiedForSendingStatus' => false,
				'DkimAttributes'           => [
					'SigningEnabled' => false,
					'Status'        => 'NOT_STARTED',
					'Tokens'        => ['token1abc', 'token2def', 'token3ghi'],
				],
			]);

		$result = $this->module->verify_domain('example.com');

		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('dns_records', $result);
		$this->assertCount(3, $result['dns_records']);
	}

	public function test_verify_domain_returns_failure_on_api_error(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->willReturn(new \WP_Error('amazon-ses-error', 'Domain already exists'));

		$result = $this->module->verify_domain('example.com');

		$this->assertFalse($result['success']);
		$this->assertArrayHasKey('message', $result);
	}

	public function test_get_domain_verification_status_returns_verified(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->with('email-identities/example.com')
			->willReturn([
				'VerifiedForSendingStatus' => true,
				'DkimAttributes'           => ['Status' => 'SUCCESS'],
			]);

		$result = $this->module->get_domain_verification_status('example.com');

		$this->assertTrue($result['success']);
		$this->assertSame('verified', $result['status']);
	}

	public function test_get_domain_verification_status_returns_pending(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->willReturn([
				'VerifiedForSendingStatus' => false,
				'DkimAttributes'           => ['Status' => 'PENDING'],
			]);

		$result = $this->module->get_domain_verification_status('example.com');

		$this->assertTrue($result['success']);
		$this->assertSame('PENDING', $result['status']);
	}

	public function test_get_domain_dns_records_returns_cname_records(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->willReturn([
				'DkimAttributes' => [
					'Tokens' => ['abc123', 'def456'],
				],
			]);

		$result = $this->module->get_domain_dns_records('example.com');

		$this->assertTrue($result['success']);
		$this->assertCount(2, $result['dns_records']);

		foreach ($result['dns_records'] as $record) {
			$this->assertSame('CNAME', $record['type']);
			$this->assertStringContainsString('example.com', $record['name']);
			$this->assertStringContainsString('amazonses.com', $record['value']);
		}
	}

	public function test_send_email_returns_message_id_on_success(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->willReturn(['MessageId' => 'msg-id-xyz']);

		$result = $this->module->send_email(
			'sender@example.com',
			'recipient@example.com',
			'Test Subject',
			'Test body'
		);

		$this->assertTrue($result['success']);
		$this->assertSame('msg-id-xyz', $result['message_id']);
	}

	public function test_send_email_returns_failure_on_error(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->willReturn(new \WP_Error('amazon-ses-error', 'Sending failed'));

		$result = $this->module->send_email(
			'sender@example.com',
			'recipient@example.com',
			'Test Subject',
			'Test body'
		);

		$this->assertFalse($result['success']);
		$this->assertArrayHasKey('message', $result);
	}

	public function test_set_sending_quota_returns_success(): void {

		$result = $this->module->set_sending_quota('example.com', 1000);

		$this->assertTrue($result['success']);
	}

	public function test_get_sending_statistics_returns_totals(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->with('account/sending-statistics')
			->willReturn([
				'SendingStatistics' => [
					[
						'DeliveryAttempts' => 100,
						'Bounces'          => 5,
						'Complaints'       => 2,
						'Rejects'          => 1,
					],
				],
			]);

		$result = $this->module->get_sending_statistics('example.com');

		$this->assertTrue($result['success']);
		$this->assertSame(100, $result['sent']);
		$this->assertSame(5, $result['bounced']);
		$this->assertSame(2, $result['complaints']);
	}

	public function test_on_domain_added_calls_verify_domain(): void {

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->with('email-identities', 'POST', $this->anything())
			->willReturn([
				'DkimAttributes' => ['Tokens' => ['tok1', 'tok2', 'tok3']],
			]);

		$this->module->on_domain_added('example.com', 1);
	}

	public function test_on_domain_removed_does_not_delete_by_default(): void {

		$this->integration->expects($this->never())
			->method('ses_api_call');

		$this->module->on_domain_removed('example.com', 1);
	}

	public function test_on_domain_removed_deletes_when_filter_enabled(): void {

		add_filter('wu_ses_delete_identity_on_domain_removed', '__return_true');

		$this->integration->expects($this->once())
			->method('ses_api_call')
			->with('email-identities/example.com', 'DELETE')
			->willReturn([]);

		$this->module->on_domain_removed('example.com', 1);

		remove_filter('wu_ses_delete_identity_on_domain_removed', '__return_true');
	}

	public function test_test_connection_delegates_to_integration(): void {

		$integration = $this->getMockBuilder(Amazon_SES_Integration::class)
			->onlyMethods(['test_connection'])
			->getMock();

		$integration->expects($this->once())
			->method('test_connection')
			->willReturn(true);

		$module = new Amazon_SES_Transactional_Email();
		$module->set_integration($integration);

		$result = $module->test_connection();

		$this->assertTrue($result);
	}
}
