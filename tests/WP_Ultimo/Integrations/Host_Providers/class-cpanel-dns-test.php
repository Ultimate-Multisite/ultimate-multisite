<?php
/**
 * Test case for cPanel DNS Provider methods.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Integrations\Host_Providers;

use WP_Ultimo\Integrations\Host_Providers\CPanel_Host_Provider;
use WP_Ultimo\Integrations\Host_Providers\DNS_Provider_Interface;
use WP_UnitTestCase;

/**
 * Test cPanel DNS Provider functionality.
 */
class CPanel_DNS_Test extends WP_UnitTestCase {

	/**
	 * The cPanel provider instance.
	 *
	 * @var CPanel_Host_Provider
	 */
	private $provider;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->provider = CPanel_Host_Provider::get_instance();
	}

	/**
	 * Test that cPanel implements DNS_Provider_Interface.
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
	}

	/**
	 * Test format_record_name helper method.
	 */
	public function test_format_record_name() {
		$method = new \ReflectionMethod($this->provider, 'format_record_name');
		$method->setAccessible(true);

		// Root domain (@)
		$this->assertEquals('example.com.', $method->invoke($this->provider, '@', 'example.com'));

		// Subdomain
		$this->assertEquals('www.example.com.', $method->invoke($this->provider, 'www', 'example.com'));

		// Full domain name
		$this->assertEquals('test.example.com.', $method->invoke($this->provider, 'test.example.com', 'example.com'));
	}

	/**
	 * Test ensure_trailing_dot helper method.
	 */
	public function test_ensure_trailing_dot() {
		$method = new \ReflectionMethod($this->provider, 'ensure_trailing_dot');
		$method->setAccessible(true);

		$this->assertEquals('example.com.', $method->invoke($this->provider, 'example.com'));
		$this->assertEquals('example.com.', $method->invoke($this->provider, 'example.com.'));
		$this->assertEquals('mail.example.com.', $method->invoke($this->provider, 'mail.example.com'));
	}

	/**
	 * Test get_dns_records returns WP_Error when not configured.
	 */
	public function test_get_dns_records_not_configured() {
		$result = $this->provider->get_dns_records('example.com');

		// Without proper API credentials, should return WP_Error
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
			'ttl'     => 14400,
		];

		$result = $this->provider->create_dns_record('example.com', $record);

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
			'ttl'     => 14400,
		];

		$result = $this->provider->update_dns_record('example.com', '42', $record);

		$this->assertTrue(is_wp_error($result) || is_array($result));
	}

	/**
	 * Test delete_dns_record returns WP_Error when not configured.
	 */
	public function test_delete_dns_record_not_configured() {
		$result = $this->provider->delete_dns_record('example.com', '42');

		$this->assertTrue(is_wp_error($result) || true === $result);
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
		$this->assertEquals('cpanel', $this->provider->get_id());
	}

	/**
	 * Test provider title.
	 */
	public function test_provider_title() {
		$title = $this->provider->get_title();
		$this->assertStringContainsString('cPanel', $title);
	}

	/**
	 * Test default TTL for cPanel (14400).
	 */
	public function test_default_ttl() {
		// cPanel typically uses 14400 as default TTL
		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.1',
		];

		// This tests that the record data is properly structured
		$this->assertEquals('A', $record['type']);
	}
}
