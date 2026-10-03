<?php

namespace WP_Ultimo\Integrations\Providers\BigScoots;

use WP_UnitTestCase;

class BigScoots_Domain_Mapping_Test extends WP_UnitTestCase {

	private BigScoots_Domain_Mapping $module;
	private BigScoots_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(BigScoots_Integration::class)
			->onlyMethods(['bigscoots_api_call', 'get_credential', 'test_connection'])
			->getMock();

		$this->integration->method('get_credential')
			->with('WU_BIGSCOOTS_PRIMARY_UUID')
			->willReturn('primary-uuid');

		$this->module = new BigScoots_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	public function test_capability_supports_domain_mapping_and_ssl(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
		$this->assertTrue($this->module->supports('autossl'));
	}

	public function test_register_hooks_adds_domain_and_ssl_actions(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_add_domain', [$this->module, 'on_add_domain']));
		$this->assertIsInt(has_action('wu_remove_domain', [$this->module, 'on_remove_domain']));
		$this->assertIsInt(has_action('wu_add_subdomain', [$this->module, 'on_add_subdomain']));
		$this->assertIsInt(has_action('wu_remove_subdomain', [$this->module, 'on_remove_subdomain']));
		$this->assertIsInt(has_action('wu_domain_manager_dns_propagation_finished', [$this->module, 'request_ssl']));
		$this->assertIsInt(has_action('wp_insert_site', [$this->module, 'on_wordpress_site_created']));
		$this->assertIsInt(has_action('wp_delete_site', [$this->module, 'on_wordpress_site_deleted']));
		$this->assertIsInt(has_action('wu_bigscoots_add_subdirectory_site', [$this->module, 'on_add_subdomain']));
		$this->assertIsInt(has_action('wu_bigscoots_remove_subdirectory_site', [$this->module, 'on_remove_subdomain']));
	}

	public function test_on_add_subdomain_registers_site(): void {

		$this->integration->expects($this->once())
			->method('bigscoots_api_call')
			->with(
				'/v1/multi-sites/sub-sites/',
				'POST',
				[
					'primary_uuid' => 'primary-uuid',
					'blog_url'     => 'customer.example.com/shop',
				]
			)
			->willReturn((object) ['success' => true]);

		$this->module->on_add_subdomain('https://customer.example.com/shop/', 27);
	}

	public function test_on_remove_subdomain_deletes_site_by_wordpress_blog_id(): void {

		$this->integration->expects($this->once())
			->method('bigscoots_api_call')
			->with('/v1/multi-sites/sub-sites/primary-uuid/27', 'DELETE')
			->willReturn((object) ['success' => true]);

		$this->module->on_remove_subdomain('customer.example.com', 27);
	}

	public function test_on_add_domain_synchronizes_current_primary_mapping(): void {

		$site_id = self::factory()->blog->create();
		$primary = wu_create_domain(
			[
				'blog_id'        => $site_id,
				'domain'         => 'primary-' . wp_rand() . '.example.net',
				'active'         => true,
				'primary_domain' => true,
				'stage'          => 'done',
			]
		);

		$this->assertNotWPError($primary);

		$this->integration->expects($this->once())
			->method('bigscoots_api_call')
			->with(
				'/v1/multi-sites/sub-sites/primary-uuid/' . $site_id,
				'PATCH',
				['domain' => $primary->get_domain()]
			)
			->willReturn((object) ['success' => true]);

		$this->module->on_add_domain('secondary.example.net', $site_id);
	}

	public function test_on_remove_domain_keeps_remaining_primary_mapping(): void {

		$site_id = self::factory()->blog->create();
		$primary = wu_create_domain(
			[
				'blog_id'        => $site_id,
				'domain'         => 'remaining-' . wp_rand() . '.example.net',
				'active'         => true,
				'primary_domain' => true,
				'stage'          => 'done',
			]
		);

		$this->assertNotWPError($primary);

		$this->integration->expects($this->once())
			->method('bigscoots_api_call')
			->with(
				'/v1/multi-sites/sub-sites/primary-uuid/' . $site_id,
				'PATCH',
				['domain' => $primary->get_domain()]
			)
			->willReturn((object) ['success' => true]);

		$this->module->on_remove_domain('secondary.example.net', $site_id);
	}

	public function test_on_remove_last_domain_restores_wordpress_site_domain(): void {

		$site_id         = self::factory()->blog->create();
		$original_domain = get_site($site_id)->domain;

		$this->integration->expects($this->once())
			->method('bigscoots_api_call')
			->with(
				'/v1/multi-sites/sub-sites/primary-uuid/' . $site_id,
				'PATCH',
				['domain' => $original_domain]
			)
			->willReturn((object) ['success' => true]);

		$this->module->on_remove_domain('mapped.example.net', $site_id);
	}

	public function test_request_ssl_uses_primary_uuid_and_blog_id(): void {

		$this->integration->expects($this->once())
			->method('bigscoots_api_call')
			->with('/v1/multi-sites/sub-sites/ssl/primary-uuid/27', 'POST')
			->willReturn((object) ['success' => true]);

		$domain = new class() {

			public function get_blog_id(): int {

				return 27;
			}
		};

		$this->module->request_ssl($domain);
	}

	public function test_request_ssl_prepares_default_candidate_before_issuance(): void {

		$site_id = self::factory()->blog->create();
		$domain  = wu_create_domain(
			[
				'blog_id'        => $site_id,
				'domain'         => 'pending-' . wp_rand() . '.example.net',
				'active'         => true,
				'primary_domain' => false,
				'stage'          => 'checking-ssl-cert',
			]
		);

		$this->assertNotWPError($domain);

		$calls = 0;
		$this->integration->expects($this->exactly(2))
			->method('bigscoots_api_call')
			->willReturnCallback(function (string $endpoint, string $method, array $data = []) use ($site_id, $domain, &$calls) {

				++$calls;

				if (1 === $calls) {
					$this->assertSame('/v1/multi-sites/sub-sites/primary-uuid/' . $site_id, $endpoint);
					$this->assertSame('PATCH', $method);
					$this->assertSame(['domain' => $domain->get_domain()], $data);
				} else {
					$this->assertSame('/v1/multi-sites/sub-sites/ssl/primary-uuid/' . $site_id, $endpoint);
					$this->assertSame('POST', $method);
				}

				return (object) ['success' => true];
			});

		$this->module->request_ssl($domain);
	}

	public function test_request_ssl_skips_secondary_mapping(): void {

		$site_id = self::factory()->blog->create();
		$primary = wu_create_domain(
			[
				'blog_id'        => $site_id,
				'domain'         => 'primary-' . wp_rand() . '.external.test',
				'active'         => true,
				'primary_domain' => true,
				'stage'          => 'done',
			]
		);
		$domain  = wu_create_domain(
			[
				'blog_id'        => $site_id,
				'domain'         => 'secondary-' . wp_rand() . '.example.net',
				'active'         => true,
				'primary_domain' => false,
				'stage'          => 'checking-ssl-cert',
			]
		);

		$this->assertNotWPError($primary);
		$this->assertNotWPError($domain);
		$this->assertTrue($primary->is_primary_domain());
		$this->assertFalse($domain->is_primary_domain());
		$this->assertNotEmpty(
			wu_get_domains(
				[
					'blog_id'        => $site_id,
					'primary_domain' => true,
				]
			)
		);
		$this->integration->expects($this->never())->method('bigscoots_api_call');

		$this->module->request_ssl($domain);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('test_connection')
			->willReturn(true);

		$this->assertTrue($this->module->test_connection());
	}
}
