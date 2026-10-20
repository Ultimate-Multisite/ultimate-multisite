<?php

namespace WP_Ultimo\Integrations\Providers\OCI_Email;

use WP_UnitTestCase;

class OCI_Email_Transactional_Email_Test extends WP_UnitTestCase {

	private OCI_Email_Transactional_Email $module;
	private OCI_Email_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(OCI_Email_Integration::class)
			->onlyMethods(['oci_api_call'])
			->getMock();

		$this->module = new OCI_Email_Transactional_Email();
		$this->module->set_integration($this->integration);
	}

	public function test_verify_domain_reuses_existing_sender_from_senders_endpoint(): void {

		$this->integration->expects($this->exactly(4))
			->method('oci_api_call')
			->withConsecutive(
				['emailDomains?compartmentId=&name=example.com'],
				['dkims?emailDomainId=email-domain-id'],
				['dkims/dkim-id'],
				['senders?compartmentId=&emailAddress=support%40example.com']
			)
			->willReturnOnConsecutiveCalls(
				[
					'items' => [
						[
							'id'   => 'email-domain-id',
							'name' => 'example.com',
						],
					],
				],
				[
					'items' => [
						[
							'id' => 'dkim-id',
						],
					],
				],
				[
					'dnsTxtRecordValue' => 'dkim-value',
				],
				[
					'items' => [
						[
							'emailAddress'   => 'support@example.com',
							'lifecycleState' => 'ACTIVE',
						],
					],
				]
			);

		$result = $this->module->verify_domain('example.com');

		$this->assertTrue($result['success']);
		$this->assertSame('support@example.com', $result['approved_sender']);
	}

	public function test_verify_domain_creates_sender_at_senders_endpoint(): void {

		$this->integration->expects($this->exactly(5))
			->method('oci_api_call')
			->withConsecutive(
				['emailDomains?compartmentId=&name=example.com'],
				['dkims?emailDomainId=email-domain-id'],
				['dkims/dkim-id'],
				['senders?compartmentId=&emailAddress=support%40example.com'],
				[
					'senders',
					'POST',
					[
						'compartmentId' => '',
						'emailAddress'  => 'support@example.com',
					],
				]
			)
			->willReturnOnConsecutiveCalls(
				[
					'items' => [
						[
							'id'   => 'email-domain-id',
							'name' => 'example.com',
						],
					],
				],
				[
					'items' => [
						[
							'id' => 'dkim-id',
						],
					],
				],
				[
					'dnsTxtRecordValue' => 'dkim-value',
				],
				new \WP_Error('oracle-oci-approved-sender-not-found'),
				[
					'emailAddress'   => 'support@example.com',
					'lifecycleState' => 'ACTIVE',
				]
			);

		$result = $this->module->verify_domain('example.com');

		$this->assertTrue($result['success']);
		$this->assertSame('support@example.com', $result['approved_sender']);
	}

	public function test_get_domain_dns_records_reuses_existing_dkim_from_dkims_endpoint(): void {

		$this->integration->expects($this->exactly(3))
			->method('oci_api_call')
			->withConsecutive(
				['emailDomains?compartmentId=&name=example.com'],
				['dkims?emailDomainId=email-domain-id'],
				['dkims/dkim-id']
			)
			->willReturnOnConsecutiveCalls(
				[
					'items' => [
						[
							'id'   => 'email-domain-id',
							'name' => 'example.com',
						],
					],
				],
				[
					'items' => [
						[
							'id' => 'dkim-id',
						],
					],
				],
				[
					'dnsTxtRecordValue' => 'dkim-value',
				]
			);

		$result = $this->module->get_domain_dns_records('example.com');

		$this->assertTrue($result['success']);
		$this->assertContains('dkim-value', array_column($result['dns_records'], 'value'));
	}

	public function test_get_domain_dns_records_creates_dkim_at_dkims_endpoint(): void {

		$this->integration->expects($this->exactly(3))
			->method('oci_api_call')
			->withConsecutive(
				['emailDomains?compartmentId=&name=example.com'],
				['dkims?emailDomainId=email-domain-id'],
				[
					'dkims',
					'POST',
					[
						'name'          => 'ultimate-multisite',
						'emailDomainId' => 'email-domain-id',
					],
				]
			)
			->willReturnOnConsecutiveCalls(
				[
					'items' => [
						[
							'id'   => 'email-domain-id',
							'name' => 'example.com',
						],
					],
				],
				new \WP_Error('oracle-oci-dkim-not-found'),
				[
					'id'                => 'dkim-id',
					'dnsTxtRecordValue' => 'dkim-value',
				]
			);

		$result = $this->module->get_domain_dns_records('example.com');

		$this->assertTrue($result['success']);
	}
}
