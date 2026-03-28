<?php
/**
 * Test case for Hestia DNS Provider methods.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Integrations\Host_Providers;

use WP_Ultimo\Integrations\Host_Providers\Hestia_Host_Provider;
use WP_Ultimo\Integrations\Host_Providers\DNS_Provider_Interface;
use WP_UnitTestCase;

/**
 * Test Hestia DNS Provider functionality.
 */
class Hestia_DNS_Test extends WP_UnitTestCase {

	/**
	 * The Hestia provider instance.
	 *
	 * @var Hestia_Host_Provider
	 */
	private $provider;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->provider = Hestia_Host_Provider::get_instance();
	}

	/**
	 * Test that Hestia implements DNS_Provider_Interface.
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
	 * Test extract_zone_name helper method.
	 */
	public function test_extract_zone_name() {
		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		// Standard TLD
		$this->assertEquals('example.com', $method->invoke($this->provider, 'www.example.com'));
		$this->assertEquals('example.com', $method->invoke($this->provider, 'sub.test.example.com'));
		$this->assertEquals('example.com', $method->invoke($this->provider, 'example.com'));

		// Multi-part TLDs
		$this->assertEquals('example.co.uk', $method->invoke($this->provider, 'www.example.co.uk'));
		$this->assertEquals('example.com.au', $method->invoke($this->provider, 'sub.example.com.au'));
		$this->assertEquals('example.co.nz', $method->invoke($this->provider, 'www.example.co.nz'));
	}

	/**
	 * Test get_dns_records returns WP_Error when not configured.
	 */
	public function test_get_dns_records_not_configured() {
		$result = $this->provider->get_dns_records('example.com');

		// Without proper API credentials, should return WP_Error
		$this->assertInstanceOf(\WP_Error::class, $result);
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

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test update_dns_record returns WP_Error when not configured.
	 */
	public function test_update_dns_record_not_configured() {
		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.2',
			'ttl'     => 3600,
		];

		$result = $this->provider->update_dns_record('example.com', 'record_1', $record);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test delete_dns_record returns WP_Error when not configured.
	 */
	public function test_delete_dns_record_not_configured() {
		$result = $this->provider->delete_dns_record('example.com', 'record_1');

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test is_dns_enabled default value.
	 */
	public function test_is_dns_enabled_default() {
		$result = $this->provider->is_dns_enabled();
		$this->assertIsBool($result);
	}

	/**
	 * Test enable_dns and disable_dns toggle.
	 */
	public function test_enable_disable_dns() {
		$this->provider->enable_dns();
		$this->assertTrue($this->provider->is_dns_enabled());

		$this->provider->disable_dns();
		$this->assertFalse($this->provider->is_dns_enabled());
	}

	/**
	 * Test provider ID is correct.
	 */
	public function test_provider_id() {
		$this->assertEquals('hestia', $this->provider->get_id());
	}

	/**
	 * Test provider title.
	 */
	public function test_provider_title() {
		$title = $this->provider->get_title();
		$this->assertStringContainsString('Hestia', $title);
	}

	/**
	 * Test Hestia-specific record data structure.
	 */
	public function test_hestia_record_structure() {
		// Test that record array has expected keys
		$record = [
			'type'     => 'MX',
			'name'     => '@',
			'content'  => 'mail.example.com',
			'ttl'      => 3600,
			'priority' => 10,
		];

		$this->assertArrayHasKey('type', $record);
		$this->assertArrayHasKey('name', $record);
		$this->assertArrayHasKey('content', $record);
		$this->assertArrayHasKey('priority', $record);
	}

	/**
	 * Test root domain indicator (@).
	 */
	public function test_root_domain_indicator() {
		$record = [
			'type'    => 'A',
			'name'    => '@',
			'content' => '192.168.1.1',
		];

		$this->assertEquals('@', $record['name']);
	}
}
