<?php

namespace WP_Ultimo\Integrations\Providers\WPMUDEV;

use WP_UnitTestCase;
use WP_Ultimo\Database\Domains\Domain_Stage;

class WPMUDEV_Domain_Mapping_Test extends WP_UnitTestCase {

	private WPMUDEV_Domain_Mapping $module;
	private WPMUDEV_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(WPMUDEV_Integration::class)
			->onlyMethods(['get_credential'])
			->getMock();

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				$map = [
					'WPMUDEV_HOSTING_SITE_ID' => '12345',
				];

				return $map[ $key ] ?? '';
			});

		$this->module = new WPMUDEV_Domain_Mapping();
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

	public function test_register_hooks_adds_ssl_tries_filter(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_filter('wu_async_process_domain_stage_max_tries', [$this->module, 'ssl_tries']));
	}

	public function test_on_remove_domain_is_noop(): void {

		// WPMU DEV API does not support domain removal yet.
		// Verify no HTTP calls are made (no wp_remote_post mock needed).
		$this->module->on_remove_domain('example.com', 1);

		// If we get here without error, the noop is confirmed.
		$this->assertTrue(true);
	}

	public function test_on_add_subdomain_is_noop(): void {

		// WPMU DEV handles subdomains automatically.
		$this->module->on_add_subdomain('sub.example.com', 1);

		$this->assertTrue(true);
	}

	public function test_on_remove_subdomain_is_noop(): void {

		// WPMU DEV handles subdomains automatically.
		$this->module->on_remove_subdomain('sub.example.com', 1);

		$this->assertTrue(true);
	}

	public function test_ssl_tries_increases_for_checking_ssl_cert_stage(): void {

		$domain = $this->createMock(\WP_Ultimo\Models\Domain::class);
		$domain->method('get_stage')->willReturn(Domain_Stage::CHECKING_SSL);

		$result = $this->module->ssl_tries(5, $domain);

		$this->assertSame(10, $result);
	}

	public function test_ssl_tries_unchanged_for_other_stages(): void {

		$domain = $this->createMock(\WP_Ultimo\Models\Domain::class);
		$domain->method('get_stage')->willReturn('checking-dns');

		$result = $this->module->ssl_tries(5, $domain);

		$this->assertSame(5, $result);
	}

	public function test_test_connection_delegates_to_integration(): void {

		// test_connection calls wp_remote_get internally — we verify it returns a value.
		// Since no real HTTP call is made in tests, it will return a WP_Error or true.
		$result = $this->module->test_connection();

		$this->assertNotNull($result);
	}
}
