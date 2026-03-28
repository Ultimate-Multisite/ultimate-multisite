<?php
/**
 * Test case for DNS_Record value object.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Integrations\Host_Providers;

use WP_Ultimo\Integrations\Host_Providers\DNS_Record;
use WP_UnitTestCase;

/**
 * Test DNS_Record value object functionality.
 */
class DNS_Record_Test extends WP_UnitTestCase {

	/**
	 * Test creating a basic A record.
	 */
	public function test_create_a_record() {
		$record = new DNS_Record([
			'id'      => '123',
			'type'    => 'A',
			'name'    => 'example.com',
			'content' => '192.168.1.1',
			'ttl'     => 3600,
		]);

		$this->assertEquals('123', $record->id);
		$this->assertEquals('A', $record->get_type());
		$this->assertEquals('example.com', $record->get_name());
		$this->assertEquals('192.168.1.1', $record->get_content());
		$this->assertEquals(3600, $record->get_ttl());
		$this->assertNull($record->get_priority());
	}

	/**
	 * Test creating an AAAA record.
	 */
	public function test_create_aaaa_record() {
		$record = new DNS_Record([
			'id'      => '124',
			'type'    => 'AAAA',
			'name'    => 'ipv6.example.com',
			'content' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
			'ttl'     => 7200,
		]);

		$this->assertEquals('AAAA', $record->get_type());
		$this->assertEquals('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $record->get_content());
	}

	/**
	 * Test creating a CNAME record.
	 */
	public function test_create_cname_record() {
		$record = new DNS_Record([
			'id'      => '125',
			'type'    => 'CNAME',
			'name'    => 'www.example.com',
			'content' => 'example.com',
			'ttl'     => 3600,
		]);

		$this->assertEquals('CNAME', $record->get_type());
		$this->assertEquals('www.example.com', $record->get_name());
		$this->assertEquals('example.com', $record->get_content());
	}

	/**
	 * Test creating an MX record with priority.
	 */
	public function test_create_mx_record() {
		$record = new DNS_Record([
			'id'       => '126',
			'type'     => 'MX',
			'name'     => 'example.com',
			'content'  => 'mail.example.com',
			'ttl'      => 3600,
			'priority' => 10,
		]);

		$this->assertEquals('MX', $record->get_type());
		$this->assertEquals('mail.example.com', $record->get_content());
		$this->assertEquals(10, $record->get_priority());
	}

	/**
	 * Test creating a TXT record.
	 */
	public function test_create_txt_record() {
		$record = new DNS_Record([
			'id'      => '127',
			'type'    => 'TXT',
			'name'    => 'example.com',
			'content' => 'v=spf1 include:_spf.google.com ~all',
			'ttl'     => 3600,
		]);

		$this->assertEquals('TXT', $record->get_type());
		$this->assertEquals('v=spf1 include:_spf.google.com ~all', $record->get_content());
	}

	/**
	 * Test to_array method.
	 */
	public function test_to_array() {
		$data = [
			'id'       => '128',
			'type'     => 'MX',
			'name'     => 'example.com',
			'content'  => 'mail.example.com',
			'ttl'      => 3600,
			'priority' => 5,
			'proxied'  => false,
		];

		$record = new DNS_Record($data);
		$array  = $record->to_array();

		$this->assertEquals('128', $array['id']);
		$this->assertEquals('MX', $array['type']);
		$this->assertEquals('example.com', $array['name']);
		$this->assertEquals('mail.example.com', $array['content']);
		$this->assertEquals(3600, $array['ttl']);
		$this->assertEquals(5, $array['priority']);
		$this->assertFalse($array['proxied']);
	}

	/**
	 * Test VALID_TYPES constant contains expected types.
	 */
	public function test_valid_types_constant() {
		$this->assertContains('A', DNS_Record::VALID_TYPES);
		$this->assertContains('AAAA', DNS_Record::VALID_TYPES);
		$this->assertContains('CNAME', DNS_Record::VALID_TYPES);
		$this->assertContains('MX', DNS_Record::VALID_TYPES);
		$this->assertContains('TXT', DNS_Record::VALID_TYPES);
	}

	/**
	 * Test validate method with valid A record.
	 */
	public function test_validate_valid_a_record() {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'example.com',
			'content' => '192.168.1.1',
		]);

		$this->assertTrue($record->validate());
	}

	/**
	 * Test validate method with invalid type.
	 */
	public function test_validate_invalid_type() {
		$record = new DNS_Record([
			'type'    => 'INVALID',
			'name'    => 'example.com',
			'content' => 'value',
		]);

		$result = $record->validate();
		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('invalid_type', $result->get_error_code());
	}

	/**
	 * Test validate method with empty name.
	 */
	public function test_validate_empty_name() {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => '',
			'content' => '192.168.1.1',
		]);

		$result = $record->validate();
		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('missing_name', $result->get_error_code());
	}

	/**
	 * Test validate method with empty content.
	 */
	public function test_validate_empty_content() {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'example.com',
			'content' => '',
		]);

		$result = $record->validate();
		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('missing_content', $result->get_error_code());
	}

	/**
	 * Test validate_by_type with invalid IPv4 for A record.
	 */
	public function test_validate_invalid_ipv4() {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'example.com',
			'content' => 'not-an-ip',
		]);

		$result = $record->validate();
		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('invalid_ipv4', $result->get_error_code());
	}

	/**
	 * Test validate_by_type with invalid IPv6 for AAAA record.
	 */
	public function test_validate_invalid_ipv6() {
		$record = new DNS_Record([
			'type'    => 'AAAA',
			'name'    => 'example.com',
			'content' => '192.168.1.1', // IPv4 is invalid for AAAA
		]);

		$result = $record->validate();
		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('invalid_ipv6', $result->get_error_code());
	}

	/**
	 * Test validate_by_type with IP address for CNAME (should fail).
	 */
	public function test_validate_invalid_cname_with_ip() {
		$record = new DNS_Record([
			'type'    => 'CNAME',
			'name'    => 'www.example.com',
			'content' => '192.168.1.1', // IP not allowed for CNAME
		]);

		$result = $record->validate();
		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('invalid_cname', $result->get_error_code());
	}

	/**
	 * Test from_provider method with Cloudflare data.
	 */
	public function test_from_provider_cloudflare() {
		$cloudflare_data = [
			'id'      => 'cf_record_123',
			'type'    => 'A',
			'name'    => 'test.example.com',
			'content' => '192.168.1.100',
			'ttl'     => 1,
			'proxied' => true,
		];

		$record = DNS_Record::from_provider($cloudflare_data, 'cloudflare');

		$this->assertEquals('cf_record_123', $record->id);
		$this->assertEquals('A', $record->get_type());
		$this->assertEquals('test.example.com', $record->get_name());
		$this->assertEquals('192.168.1.100', $record->get_content());
		$this->assertEquals(1, $record->get_ttl());
		$this->assertTrue($record->is_proxied());
	}

	/**
	 * Test from_provider method with cPanel data.
	 */
	public function test_from_provider_cpanel() {
		$cpanel_data = [
			'line_index' => '42',
			'type'       => 'MX',
			'name'       => 'example.com.',
			'exchange'   => 'mail.example.com.',
			'ttl'        => 14400,
			'preference' => 10,
		];

		$record = DNS_Record::from_provider($cpanel_data, 'cpanel');

		$this->assertEquals('42', $record->id);
		$this->assertEquals('MX', $record->get_type());
		$this->assertEquals('example.com', $record->get_name()); // Trailing dot removed
		$this->assertEquals('mail.example.com.', $record->get_content());
		$this->assertEquals(10, $record->get_priority());
	}

	/**
	 * Test from_provider method with Hestia data.
	 */
	public function test_from_provider_hestia() {
		$hestia_data = [
			'id'    => 'record_1',
			'type'  => 'TXT',
			'name'  => '@',
			'value' => 'v=spf1 mx ~all',
			'ttl'   => 3600,
		];

		$record = DNS_Record::from_provider($hestia_data, 'hestia');

		$this->assertEquals('record_1', $record->id);
		$this->assertEquals('TXT', $record->get_type());
		$this->assertEquals('@', $record->get_name());
		$this->assertEquals('v=spf1 mx ~all', $record->get_content());
	}

	/**
	 * Test get_type_class returns correct CSS class for each type.
	 */
	public function test_get_type_class() {
		$a_record = new DNS_Record(['type' => 'A', 'name' => 'test', 'content' => '1.1.1.1']);
		$aaaa_record = new DNS_Record(['type' => 'AAAA', 'name' => 'test', 'content' => '::1']);
		$cname_record = new DNS_Record(['type' => 'CNAME', 'name' => 'test', 'content' => 'target.com']);
		$mx_record = new DNS_Record(['type' => 'MX', 'name' => 'test', 'content' => 'mail.test.com', 'priority' => 10]);
		$txt_record = new DNS_Record(['type' => 'TXT', 'name' => 'test', 'content' => 'test']);

		$this->assertStringContainsString('blue', $a_record->get_type_class());
		$this->assertStringContainsString('purple', $aaaa_record->get_type_class());
		$this->assertStringContainsString('green', $cname_record->get_type_class());
		$this->assertStringContainsString('orange', $mx_record->get_type_class());
		$this->assertStringContainsString('gray', $txt_record->get_type_class());
	}

	/**
	 * Test get_ttl_label returns correct human-readable format.
	 */
	public function test_get_ttl_label() {
		$auto_record = new DNS_Record(['type' => 'A', 'name' => 'test', 'content' => '1.1.1.1', 'ttl' => 1]);
		$this->assertEquals('Auto', $auto_record->get_ttl_label());

		$hour_record = new DNS_Record(['type' => 'A', 'name' => 'test', 'content' => '1.1.1.1', 'ttl' => 3600]);
		$this->assertEquals('1 hour', $hour_record->get_ttl_label());
	}

	/**
	 * Test default TTL value.
	 */
	public function test_default_ttl() {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'example.com',
			'content' => '192.168.1.1',
		]);

		$this->assertEquals(3600, $record->get_ttl());
	}

	/**
	 * Test is_proxied method.
	 */
	public function test_is_proxied() {
		$proxied_record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'example.com',
			'content' => '192.168.1.1',
			'proxied' => true,
		]);

		$unproxied_record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'example.com',
			'content' => '192.168.1.1',
			'proxied' => false,
		]);

		$this->assertTrue($proxied_record->is_proxied());
		$this->assertFalse($unproxied_record->is_proxied());
	}

	/**
	 * Test meta data storage and retrieval.
	 */
	public function test_meta_data() {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'example.com',
			'content' => '192.168.1.1',
			'meta'    => [
				'custom_key' => 'custom_value',
				'zone_id'    => 'zone123',
			],
		]);

		$meta = $record->get_meta();
		$this->assertIsArray($meta);
		$this->assertEquals('custom_value', $meta['custom_key']);
		$this->assertEquals('zone123', $meta['zone_id']);

		// Test specific key retrieval
		$this->assertEquals('custom_value', $record->get_meta('custom_key'));
		$this->assertNull($record->get_meta('nonexistent'));
	}

	/**
	 * Test type normalization to uppercase.
	 */
	public function test_type_normalization() {
		$record = new DNS_Record([
			'type'    => 'cname',
			'name'    => 'www.example.com',
			'content' => 'example.com',
		]);

		$this->assertEquals('CNAME', $record->get_type());
	}

	/**
	 * Test MX record without priority fails validation.
	 */
	public function test_mx_validation_requires_priority() {
		$record = new DNS_Record([
			'type'    => 'MX',
			'name'    => 'example.com',
			'content' => 'mail.example.com',
		]);

		$result = $record->validate();
		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('missing_priority', $result->get_error_code());
	}

	/**
	 * Test valid CNAME hostname validation.
	 */
	public function test_valid_cname_hostname() {
		$record = new DNS_Record([
			'type'    => 'CNAME',
			'name'    => 'www.example.com',
			'content' => 'target.example.com',
		]);

		$this->assertTrue($record->validate());
	}

	/**
	 * Test valid MX record with priority.
	 */
	public function test_valid_mx_with_priority() {
		$record = new DNS_Record([
			'type'     => 'MX',
			'name'     => 'example.com',
			'content'  => 'mail.example.com',
			'priority' => 10,
		]);

		$this->assertTrue($record->validate());
	}

	/**
	 * Test get_full_name with root domain.
	 */
	public function test_get_full_name_root() {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => '@',
			'content' => '192.168.1.1',
		]);

		$this->assertEquals('example.com', $record->get_full_name('example.com'));
	}

	/**
	 * Test get_full_name with subdomain.
	 */
	public function test_get_full_name_subdomain() {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'www',
			'content' => '192.168.1.1',
		]);

		$this->assertEquals('www.example.com', $record->get_full_name('example.com'));
	}

	/**
	 * Test TTL_OPTIONS constant.
	 */
	public function test_ttl_options_constant() {
		$this->assertIsArray(DNS_Record::TTL_OPTIONS);
		$this->assertArrayHasKey(3600, DNS_Record::TTL_OPTIONS);
		$this->assertEquals('1 hour', DNS_Record::TTL_OPTIONS[3600]);
	}
}
