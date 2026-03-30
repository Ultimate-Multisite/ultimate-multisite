<?php
/**
 * Tests for Cloudflare_Domain_Mapping capability module.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Integrations\Providers\Cloudflare
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Cloudflare;

use WP_UnitTestCase;

/**
 * Test class for Cloudflare_Domain_Mapping.
 */
class Cloudflare_Domain_Mapping_Test extends WP_UnitTestCase {

	/**
	 * Cloudflare_Domain_Mapping instance.
	 *
	 * @var Cloudflare_Domain_Mapping
	 */
	private Cloudflare_Domain_Mapping $module;

	/**
	 * Mocked Cloudflare_Integration.
	 *
	 * @var Cloudflare_Integration|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $integration;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(Cloudflare_Integration::class)
			->onlyMethods(['cloudflare_api_call', 'get_credential'])
			->getMock();

		$this->module = new Cloudflare_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	/**
	 * Test get_capability_id returns 'domain-mapping'.
	 */
	public function test_get_capability_id_returns_domain_mapping(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
	}

	/**
	 * Test get_title returns non-empty string.
	 */
	public function test_get_title_returns_non_empty_string(): void {

		$this->assertNotEmpty($this->module->get_title());
		$this->assertIsString($this->module->get_title());
	}

	/**
	 * Test get_explainer_lines returns array with 'will' and 'will_not' keys.
	 */
	public function test_get_explainer_lines_has_will_and_will_not_keys(): void {

		$this->integration->method('get_credential')
			->willReturn('');

		$lines = $this->module->get_explainer_lines();

		$this->assertIsArray($lines);
		$this->assertArrayHasKey('will', $lines);
		$this->assertArrayHasKey('will_not', $lines);
	}

	/**
	 * Test register_hooks adds expected actions.
	 */
	public function test_register_hooks_adds_actions(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_add_domain', [$this->module, 'on_add_domain']));
		$this->assertIsInt(has_action('wu_remove_domain', [$this->module, 'on_remove_domain']));
		$this->assertIsInt(has_action('wu_add_subdomain', [$this->module, 'on_add_subdomain']));
		$this->assertIsInt(has_action('wu_remove_subdomain', [$this->module, 'on_remove_subdomain']));
	}

	/**
	 * Test register_hooks adds DNS filter.
	 */
	public function test_register_hooks_adds_dns_filter(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_filter('wu_domain_dns_get_record', [$this->module, 'add_cloudflare_dns_entries']));
	}

	/**
	 * Test on_add_domain is noop when no SaaS zone ID.
	 */
	public function test_on_add_domain_is_noop_when_no_saas_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_SAAS_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('cloudflare_api_call');

		$this->module->on_add_domain('example.com', 1);
	}

	/**
	 * Test on_add_domain calls API when SaaS zone ID is set.
	 */
	public function test_on_add_domain_calls_api_when_saas_zone_id_set(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_SAAS_ZONE_ID')
			->willReturn('saas-zone-123');

		$this->integration->expects($this->once())
			->method('cloudflare_api_call')
			->willReturn((object) ['id' => 'hostname-id-123']);

		$this->module->on_add_domain('example.com', 1);
	}

	/**
	 * Test on_add_domain handles API error gracefully.
	 */
	public function test_on_add_domain_handles_api_error_gracefully(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_SAAS_ZONE_ID')
			->willReturn('saas-zone-123');

		$this->integration->method('cloudflare_api_call')
			->willReturn(new \WP_Error('cloudflare-error', 'API error'));

		// Should not throw — error is logged silently.
		$this->module->on_add_domain('example.com', 1);

		$this->assertTrue(true); // Reached without exception.
	}

	/**
	 * Test on_remove_domain is noop when no SaaS zone ID.
	 */
	public function test_on_remove_domain_is_noop_when_no_saas_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_SAAS_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('cloudflare_api_call');

		$this->module->on_remove_domain('example.com', 1);
	}

	/**
	 * Test on_remove_domain handles list API error gracefully.
	 */
	public function test_on_remove_domain_handles_list_api_error(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_SAAS_ZONE_ID')
			->willReturn('saas-zone-123');

		$this->integration->method('cloudflare_api_call')
			->willReturn(new \WP_Error('cloudflare-error', 'API error'));

		// Should not throw.
		$this->module->on_remove_domain('example.com', 1);

		$this->assertTrue(true);
	}

	/**
	 * Test on_remove_domain handles empty result gracefully.
	 */
	public function test_on_remove_domain_handles_empty_result(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_SAAS_ZONE_ID')
			->willReturn('saas-zone-123');

		$empty_result         = new \stdClass();
		$empty_result->result = [];

		$this->integration->method('cloudflare_api_call')
			->willReturn($empty_result);

		// Should not throw.
		$this->module->on_remove_domain('example.com', 1);

		$this->assertTrue(true);
	}

	/**
	 * Test on_add_subdomain is noop when no zone ID.
	 */
	public function test_on_add_subdomain_is_noop_when_no_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('cloudflare_api_call');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	/**
	 * Test on_remove_subdomain is noop when no zone ID.
	 */
	public function test_on_remove_subdomain_is_noop_when_no_zone_id(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_ZONE_ID')
			->willReturn('');

		$this->integration->expects($this->never())
			->method('cloudflare_api_call');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	/**
	 * Test add_cloudflare_dns_entries returns unchanged records when no zone IDs.
	 */
	public function test_add_cloudflare_dns_entries_returns_unchanged_when_no_zone_ids(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_ZONE_ID')
			->willReturn('');

		$this->integration->method('cloudflare_api_call')
			->willReturn(new \WP_Error('fail', 'error'));

		$existing = [['type' => 'A', 'data' => '1.2.3.4']];

		$result = $this->module->add_cloudflare_dns_entries($existing, 'example.com');

		$this->assertSame($existing, $result);
	}

	/**
	 * Test add_cloudflare_dns_entries returns unchanged on API error.
	 */
	public function test_add_cloudflare_dns_entries_returns_unchanged_on_api_error(): void {

		$this->integration->method('get_credential')
			->with('WU_CLOUDFLARE_ZONE_ID')
			->willReturn('zone-123');

		$this->integration->method('cloudflare_api_call')
			->willReturn(new \WP_Error('fail', 'error'));

		$existing = [['type' => 'A', 'data' => '1.2.3.4']];

		$result = $this->module->add_cloudflare_dns_entries($existing, 'example.com');

		$this->assertSame($existing, $result);
	}

	/**
	 * Test add_cloudflare_dns_entries appends records from API.
	 */
	public function test_add_cloudflare_dns_entries_appends_records_from_api(): void {

		$this->integration->method('get_credential')
			->willReturnCallback(function (string $key) {
				if ('WU_CLOUDFLARE_ZONE_ID' === $key) {
					return 'zone-123';
				}

				return '';
			});

		$dns_entry          = new \stdClass();
		$dns_entry->ttl     = 1;
		$dns_entry->content = '1.2.3.4';
		$dns_entry->type    = 'A';
		$dns_entry->name    = 'example.com';
		$dns_entry->proxied = true;

		$zones_result         = new \stdClass();
		$zones_result->result = [];

		$dns_result         = new \stdClass();
		$dns_result->result = [$dns_entry];

		$this->integration->method('cloudflare_api_call')
			->willReturnCallback(function (string $endpoint) use ($zones_result, $dns_result) {
				if (str_contains($endpoint, 'dns_records')) {
					return $dns_result;
				}

				return $zones_result;
			});

		$existing = [];

		$result = $this->module->add_cloudflare_dns_entries($existing, 'example.com');

		$this->assertNotEmpty($result);
		$this->assertCount(1, $result);
		$this->assertSame('A', $result[0]['type']);
		$this->assertSame('1.2.3.4', $result[0]['data']);
	}

	/**
	 * Test test_connection delegates to integration.
	 */
	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('cloudflare_api_call')
			->willReturn((object) ['result' => (object) ['status' => 'active']]);

		$result = $this->module->test_connection();

		$this->assertTrue($result);
	}

	/**
	 * Test test_connection returns WP_Error on failure.
	 */
	public function test_test_connection_returns_wp_error_on_failure(): void {

		$this->integration->method('cloudflare_api_call')
			->willReturn(new \WP_Error('cloudflare-error', 'Unauthorized'));

		$result = $this->module->test_connection();

		$this->assertWPError($result);
	}
}
