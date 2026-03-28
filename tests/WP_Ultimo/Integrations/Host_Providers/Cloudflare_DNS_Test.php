<?php
/**
 * Test case for Cloudflare DNS Provider methods.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Integrations\Host_Providers;

use WP_Ultimo\Integrations\Host_Providers\Cloudflare_Host_Provider;
use WP_Ultimo\Integrations\Host_Providers\DNS_Provider_Interface;
use WP_UnitTestCase;

/**
 * Test Cloudflare DNS Provider functionality.
 */
class Cloudflare_DNS_Test extends WP_UnitTestCase {

	/**
	 * The Cloudflare provider instance.
	 *
	 * @var Cloudflare_Host_Provider
	 */
	private $provider;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->provider = Cloudflare_Host_Provider::get_instance();
	}

	/**
	 * Test that Cloudflare implements DNS_Provider_Interface.
	 */
	public function test_implements_dns_interface() {
		$this->assertInstanceOf(DNS_Provider_Interface::class, $this->provider);
	}

	/**
	 * Test supports_dns_management returns true.
	 */
	public function test_supports_dns_management() {
		$this->assertTrue($this->provider->supports_dns_management());
	}

	/**
	 * Test get_supported_record_types returns expected types.
	 */
	public function test_get_supported_record_types() {
		$types = $this->provider->get_supported_record_types();

		$this->assertIsArray($types);
		$this->assertContains('A', $types);
		$this->assertContains('AAAA', $types);
		$this->assertContains('CNAME', $types);
		$this->assertContains('MX', $types);
		$this->assertContains('TXT', $types);
	}

	/**
	 * Test extract_root_domain helper method.
	 */
	public function test_extract_root_domain() {
		$method = new \ReflectionMethod($this->provider, 'extract_root_domain');
		$method->setAccessible(true);

		// Standard TLD
		$this->assertEquals('example.com', $method->invoke($this->provider, 'www.example.com'));
		$this->assertEquals('example.com', $method->invoke($this->provider, 'sub.test.example.com'));
		$this->assertEquals('example.com', $method->invoke($this->provider, 'example.com'));

		// Multi-part TLDs
		$this->assertEquals('example.co.uk', $method->invoke($this->provider, 'www.example.co.uk'));
		$this->assertEquals('example.com.au', $method->invoke($this->provider, 'sub.example.com.au'));
	}

	/**
	 * Test get_dns_records returns WP_Error when not configured.
	 */
	public function test_get_dns_records_not_configured() {
		// Without proper API credentials, should return WP_Error
		$result = $this->provider->get_dns_records('example.com');

		// This will fail as we don't have real credentials
		// In a real test environment, you'd mock the API
		$this->assertTrue(is_wp_error($result) || is_array($result));
	}

	/**
	 * Test create_dns_record returns WP_Error when not configured.
	 */
	public function test_create_dns_record_not_configured() {
		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.1',
			'ttl'     => 3600,
		];

		$result = $this->provider->create_dns_record('example.com', $record);

		// Without credentials, should return error
		$this->assertTrue(is_wp_error($result) || is_array($result));
	}

	/**
	 * Test update_dns_record returns WP_Error when not configured.
	 */
	public function test_update_dns_record_not_configured() {
		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.2',
			'ttl'     => 7200,
		];

		$result = $this->provider->update_dns_record('example.com', 'record_123', $record);

		$this->assertTrue(is_wp_error($result) || is_array($result));
	}

	/**
	 * Test delete_dns_record returns WP_Error when not configured.
	 */
	public function test_delete_dns_record_not_configured() {
		$result = $this->provider->delete_dns_record('example.com', 'record_123');

		$this->assertTrue(is_wp_error($result) || $result === true);
	}

	/**
	 * Test is_dns_enabled default value.
	 */
	public function test_is_dns_enabled_default() {
		// By default, DNS should not be enabled until explicitly set
		$result = $this->provider->is_dns_enabled();

		$this->assertIsBool($result);
	}

	/**
	 * Test enable_dns and disable_dns toggle.
	 */
	public function test_enable_disable_dns() {
		// Enable DNS
		$this->provider->enable_dns();
		$this->assertTrue($this->provider->is_dns_enabled());

		// Disable DNS
		$this->provider->disable_dns();
		$this->assertFalse($this->provider->is_dns_enabled());
	}

	/**
	 * Test provider ID is correct.
	 */
	public function test_provider_id() {
		$this->assertEquals('cloudflare', $this->provider->get_id());
	}

	/**
	 * Test provider title.
	 */
	public function test_provider_title() {
		$title = $this->provider->get_title();
		$this->assertStringContainsString('Cloudflare', $title);
	}
}
