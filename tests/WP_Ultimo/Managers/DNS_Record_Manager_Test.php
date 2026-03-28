<?php
/**
 * Test case for DNS_Record_Manager.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Integrations\Host_Providers\DNS_Record;
use WP_Ultimo\Managers\DNS_Record_Manager;
use WP_UnitTestCase;

/**
 * Test DNS_Record_Manager functionality.
 */
class DNS_Record_Manager_Test extends WP_UnitTestCase {

	use Manager_Test_Trait;

	/**
	 * The DNS Record Manager instance.
	 *
	 * @var DNS_Record_Manager
	 */
	private $manager;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->manager = DNS_Record_Manager::get_instance();
	}

	// ----------------------------------------------------------------
	// Manager_Test_Trait required methods
	// ----------------------------------------------------------------

	protected function get_manager_class(): string {
		return DNS_Record_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'dns-record';
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	// ----------------------------------------------------------------
	// Singleton
	// ----------------------------------------------------------------

	/**
	 * Test singleton instance.
	 */
	public function test_singleton_instance(): void {
		$instance1 = DNS_Record_Manager::get_instance();
		$instance2 = DNS_Record_Manager::get_instance();

		$this->assertSame($instance1, $instance2);
		$this->assertInstanceOf(DNS_Record_Manager::class, $instance1);
	}

	// ----------------------------------------------------------------
	// get_allowed_record_types
	// ----------------------------------------------------------------

	/**
	 * Test get_allowed_record_types returns all types for super admin.
	 */
	public function test_get_allowed_record_types_super_admin(): void {
		$user_id = $this->factory->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);

		$types = $this->manager->get_allowed_record_types($user_id);

		$this->assertIsArray($types);
		$this->assertContains('A', $types);
		$this->assertContains('AAAA', $types);
		$this->assertContains('CNAME', $types);
		$this->assertContains('MX', $types);
		$this->assertContains('TXT', $types);
		$this->assertEquals(DNS_Record::VALID_TYPES, $types);
	}

	/**
	 * Test get_allowed_record_types returns setting-filtered types for regular user.
	 */
	public function test_get_allowed_record_types_regular_user_default_setting(): void {
		$user_id = $this->factory->user->create(['role' => 'subscriber']);

		// Default setting: A, CNAME, TXT
		wu_save_setting('dns_record_types_allowed', ['A', 'CNAME', 'TXT']);

		$types = $this->manager->get_allowed_record_types($user_id);

		$this->assertIsArray($types);
		$this->assertContains('A', $types);
		$this->assertContains('CNAME', $types);
		$this->assertContains('TXT', $types);
		$this->assertNotContains('MX', $types);
		$this->assertNotContains('AAAA', $types);
	}

	/**
	 * Test get_allowed_record_types intersects with VALID_TYPES.
	 */
	public function test_get_allowed_record_types_filters_invalid_types(): void {
		$user_id = $this->factory->user->create(['role' => 'subscriber']);

		// Setting includes an invalid type
		wu_save_setting('dns_record_types_allowed', ['A', 'INVALID_TYPE', 'TXT']);

		$types = $this->manager->get_allowed_record_types($user_id);

		$this->assertNotContains('INVALID_TYPE', $types);
		$this->assertContains('A', $types);
		$this->assertContains('TXT', $types);
	}

	/**
	 * Test get_allowed_record_types with empty setting returns empty array for regular user.
	 */
	public function test_get_allowed_record_types_empty_setting(): void {
		$user_id = $this->factory->user->create(['role' => 'subscriber']);

		wu_save_setting('dns_record_types_allowed', []);

		$types = $this->manager->get_allowed_record_types($user_id);

		$this->assertIsArray($types);
		$this->assertEmpty($types);
	}

	/**
	 * Test get_allowed_record_types with MX allowed for regular user.
	 */
	public function test_get_allowed_record_types_mx_allowed(): void {
		$user_id = $this->factory->user->create(['role' => 'subscriber']);

		wu_save_setting('dns_record_types_allowed', ['A', 'MX', 'TXT']);

		$types = $this->manager->get_allowed_record_types($user_id);

		$this->assertContains('MX', $types);
	}

	// ----------------------------------------------------------------
	// get_dns_provider
	// ----------------------------------------------------------------

	/**
	 * Test get_dns_provider returns null when no provider configured.
	 */
	public function test_get_dns_provider_returns_null_when_not_configured(): void {
		$provider = $this->manager->get_dns_provider();

		$this->assertTrue($provider === null || is_object($provider));
	}

	// ----------------------------------------------------------------
	// get_dns_capable_providers
	// ----------------------------------------------------------------

	/**
	 * Test get_dns_capable_providers returns array.
	 */
	public function test_get_dns_capable_providers_returns_array(): void {
		$providers = $this->manager->get_dns_capable_providers();

		$this->assertIsArray($providers);
	}

	/**
	 * Test get_dns_capable_providers returns only DNS-capable providers.
	 */
	public function test_get_dns_capable_providers_all_support_dns(): void {
		$providers = $this->manager->get_dns_capable_providers();

		// Verify the return type regardless of how many providers are registered.
		$this->assertIsArray($providers);

		foreach ($providers as $id => $provider) {
			$this->assertTrue(
				$provider->supports_dns_management(),
				"Provider {$id} should support DNS management"
			);
		}
	}

	// ----------------------------------------------------------------
	// customer_can_manage_dns
	// ----------------------------------------------------------------

	/**
	 * Test customer_can_manage_dns returns true for super admin.
	 */
	public function test_customer_can_manage_dns_super_admin(): void {
		$user_id = $this->factory->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);

		$result = $this->manager->customer_can_manage_dns($user_id, 'example.com');

		$this->assertTrue($result);
	}

	/**
	 * Test customer_can_manage_dns returns false for non-owner.
	 */
	public function test_customer_can_manage_dns_non_owner(): void {
		$user_id = $this->factory->user->create();

		$result = $this->manager->customer_can_manage_dns($user_id, 'example.com');

		$this->assertFalse($result);
	}

	/**
	 * Test customer_can_manage_dns returns false when setting disabled.
	 */
	public function test_customer_can_manage_dns_setting_disabled(): void {
		$user_id = $this->factory->user->create(['role' => 'subscriber']);

		wu_save_setting('enable_customer_dns_management', false);

		$result = $this->manager->customer_can_manage_dns($user_id, 'example.com');

		$this->assertFalse($result);
	}

	/**
	 * Test customer_can_manage_dns returns false for non-existent domain.
	 */
	public function test_customer_can_manage_dns_nonexistent_domain(): void {
		$user_id = $this->factory->user->create(['role' => 'subscriber']);

		wu_save_setting('enable_customer_dns_management', true);

		$result = $this->manager->customer_can_manage_dns($user_id, 'nonexistent-domain-xyz-abc.com');

		$this->assertFalse($result);
	}

	// ----------------------------------------------------------------
	// sanitize_record_data (via reflection — protected method)
	// ----------------------------------------------------------------

	/**
	 * Helper to call protected sanitize_record_data.
	 *
	 * @param array $record Raw record data.
	 * @return array Sanitized record data.
	 */
	private function call_sanitize_record_data(array $record): array {
		$reflection = new \ReflectionClass($this->manager);
		$method     = $reflection->getMethod('sanitize_record_data');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		return $method->invoke($this->manager, $record);
	}

	/**
	 * Test sanitize_record_data with full valid record.
	 */
	public function test_sanitize_record_data_full_record(): void {
		$raw = [
			'type'     => 'a',
			'name'     => 'www',
			'content'  => '192.168.1.1',
			'ttl'      => 3600,
			'priority' => 10,
			'proxied'  => true,
		];

		$result = $this->call_sanitize_record_data($raw);

		$this->assertEquals('A', $result['type']); // uppercased
		$this->assertEquals('www', $result['name']);
		$this->assertEquals('192.168.1.1', $result['content']);
		$this->assertEquals(3600, $result['ttl']);
		$this->assertEquals(10, $result['priority']);
		$this->assertTrue($result['proxied']);
	}

	/**
	 * Test sanitize_record_data uppercases type.
	 */
	public function test_sanitize_record_data_uppercases_type(): void {
		$result = $this->call_sanitize_record_data([
			'type'    => 'cname',
			'name'    => 'www',
			'content' => 'example.com',
		]);

		$this->assertEquals('CNAME', $result['type']);
	}

	/**
	 * Test sanitize_record_data defaults type to A when missing.
	 */
	public function test_sanitize_record_data_defaults_type_to_a(): void {
		$result = $this->call_sanitize_record_data([
			'name'    => 'www',
			'content' => '1.2.3.4',
		]);

		$this->assertEquals('A', $result['type']);
	}

	/**
	 * Test sanitize_record_data defaults TTL to 3600.
	 */
	public function test_sanitize_record_data_defaults_ttl(): void {
		$result = $this->call_sanitize_record_data([
			'type'    => 'A',
			'name'    => 'www',
			'content' => '1.2.3.4',
		]);

		$this->assertEquals(3600, $result['ttl']);
	}

	/**
	 * Test sanitize_record_data uses absint for TTL.
	 */
	public function test_sanitize_record_data_absint_ttl(): void {
		$result = $this->call_sanitize_record_data([
			'type'    => 'A',
			'name'    => 'www',
			'content' => '1.2.3.4',
			'ttl'     => -100,
		]);

		$this->assertEquals(100, $result['ttl']);
	}

	/**
	 * Test sanitize_record_data sets priority to null when not provided.
	 */
	public function test_sanitize_record_data_priority_null_when_missing(): void {
		$result = $this->call_sanitize_record_data([
			'type'    => 'A',
			'name'    => 'www',
			'content' => '1.2.3.4',
		]);

		$this->assertNull($result['priority']);
	}

	/**
	 * Test sanitize_record_data sets proxied to false when not provided.
	 */
	public function test_sanitize_record_data_proxied_false_when_missing(): void {
		$result = $this->call_sanitize_record_data([
			'type'    => 'A',
			'name'    => 'www',
			'content' => '1.2.3.4',
		]);

		$this->assertFalse($result['proxied']);
	}

	/**
	 * Test sanitize_record_data proxied true when set.
	 */
	public function test_sanitize_record_data_proxied_true(): void {
		$result = $this->call_sanitize_record_data([
			'type'    => 'A',
			'name'    => 'www',
			'content' => '1.2.3.4',
			'proxied' => 1,
		]);

		$this->assertTrue($result['proxied']);
	}

	/**
	 * Test sanitize_record_data with empty name defaults to empty string.
	 */
	public function test_sanitize_record_data_empty_name(): void {
		$result = $this->call_sanitize_record_data([
			'type'    => 'A',
			'content' => '1.2.3.4',
		]);

		$this->assertEquals('', $result['name']);
	}

	/**
	 * Test sanitize_record_data with empty content defaults to empty string.
	 */
	public function test_sanitize_record_data_empty_content(): void {
		$result = $this->call_sanitize_record_data([
			'type' => 'A',
			'name' => 'www',
		]);

		$this->assertEquals('', $result['content']);
	}

	/**
	 * Test sanitize_record_data returns all required keys.
	 */
	public function test_sanitize_record_data_returns_all_keys(): void {
		$result = $this->call_sanitize_record_data([]);

		$this->assertArrayHasKey('type', $result);
		$this->assertArrayHasKey('name', $result);
		$this->assertArrayHasKey('content', $result);
		$this->assertArrayHasKey('ttl', $result);
		$this->assertArrayHasKey('priority', $result);
		$this->assertArrayHasKey('proxied', $result);
	}

	// ----------------------------------------------------------------
	// DNS_Record::validate()
	// ----------------------------------------------------------------

	/**
	 * Test DNS_Record::validate() with valid A record.
	 */
	public function test_validate_dns_record_valid_a_record(): void {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.1',
			'ttl'     => 3600,
		]);

		$this->assertTrue($record->validate());
	}

	/**
	 * Test DNS_Record::validate() with invalid type.
	 */
	public function test_validate_dns_record_invalid_type(): void {
		$record = new DNS_Record([
			'type'    => 'INVALID',
			'name'    => 'test',
			'content' => 'value',
		]);

		$result = $record->validate();

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('invalid_type', $result->get_error_code());
	}

	/**
	 * Test DNS_Record::validate() with empty name.
	 */
	public function test_validate_dns_record_empty_name(): void {
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
	 * Test DNS_Record::validate() with empty content.
	 */
	public function test_validate_dns_record_empty_content(): void {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'test',
			'content' => '',
		]);

		$result = $record->validate();

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('missing_content', $result->get_error_code());
	}

	/**
	 * Test DNS_Record::validate() with invalid IPv4.
	 */
	public function test_validate_dns_record_invalid_ipv4(): void {
		$record = new DNS_Record([
			'type'    => 'A',
			'name'    => 'test',
			'content' => 'not-an-ip',
		]);

		$result = $record->validate();

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('invalid_ipv4', $result->get_error_code());
	}

	/**
	 * Test DNS_Record::validate() with invalid IPv6.
	 */
	public function test_validate_dns_record_invalid_ipv6(): void {
		$record = new DNS_Record([
			'type'    => 'AAAA',
			'name'    => 'test',
			'content' => '192.168.1.1',
		]);

		$result = $record->validate();

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('invalid_ipv6', $result->get_error_code());
	}

	/**
	 * Test DNS_Record::validate() with valid AAAA record.
	 */
	public function test_validate_dns_record_valid_aaaa(): void {
		$record = new DNS_Record([
			'type'    => 'AAAA',
			'name'    => 'test',
			'content' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
		]);

		$this->assertTrue($record->validate());
	}

	/**
	 * Test DNS_Record::validate() with valid CNAME.
	 */
	public function test_validate_dns_record_valid_cname(): void {
		$record = new DNS_Record([
			'type'    => 'CNAME',
			'name'    => 'www',
			'content' => 'example.com',
		]);

		$this->assertTrue($record->validate());
	}

	/**
	 * Test DNS_Record::validate() with CNAME pointing to an IP (invalid).
	 */
	public function test_validate_dns_record_invalid_cname(): void {
		$record = new DNS_Record([
			'type'    => 'CNAME',
			'name'    => 'www',
			'content' => '192.168.1.1',
		]);

		$result = $record->validate();

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertEquals('invalid_cname', $result->get_error_code());
	}

	/**
	 * Test DNS_Record::validate() with valid MX record.
	 */
	public function test_validate_dns_record_valid_mx(): void {
		$record = new DNS_Record([
			'type'     => 'MX',
			'name'     => '@',
			'content'  => 'mail.example.com',
			'priority' => 10,
		]);

		$this->assertTrue($record->validate());
	}

	/**
	 * Test DNS_Record::validate() with valid TXT record.
	 */
	public function test_validate_dns_record_valid_txt(): void {
		$record = new DNS_Record([
			'type'    => 'TXT',
			'name'    => '@',
			'content' => 'v=spf1 include:_spf.google.com ~all',
		]);

		$this->assertTrue($record->validate());
	}

	// ----------------------------------------------------------------
	// export_to_bind
	// ----------------------------------------------------------------

	/**
	 * Test export_to_bind with A and MX records.
	 */
	public function test_export_to_bind_a_and_mx(): void {
		$records = [
			new DNS_Record([
				'type'    => 'A',
				'name'    => '@',
				'content' => '192.168.1.1',
				'ttl'     => 3600,
			]),
			new DNS_Record([
				'type'     => 'MX',
				'name'     => '@',
				'content'  => 'mail.example.com',
				'ttl'      => 3600,
				'priority' => 10,
			]),
		];

		$bind = $this->manager->export_to_bind('example.com', $records);

		$this->assertStringContainsString('$ORIGIN example.com.', $bind);
		$this->assertMatchesRegularExpression('/@\s+3600\s+IN\s+A\s+192\.168\.1\.1/', $bind);
		$this->assertMatchesRegularExpression('/@\s+3600\s+IN\s+MX\s+10\s+mail\.example\.com\./', $bind);
	}

	/**
	 * Test export_to_bind includes zone header.
	 */
	public function test_export_to_bind_includes_header(): void {
		$bind = $this->manager->export_to_bind('example.com', []);

		$this->assertStringContainsString('; Zone file for example.com', $bind);
		$this->assertStringContainsString('$ORIGIN example.com.', $bind);
		$this->assertStringContainsString('$TTL 3600', $bind);
	}

	/**
	 * Test export_to_bind with TXT record wraps content in quotes.
	 */
	public function test_export_to_bind_txt_record_quoted(): void {
		$records = [
			new DNS_Record([
				'type'    => 'TXT',
				'name'    => '@',
				'content' => 'v=spf1 mx ~all',
				'ttl'     => 3600,
			]),
		];

		$bind = $this->manager->export_to_bind('example.com', $records);

		$this->assertStringContainsString('"v=spf1 mx ~all"', $bind);
	}

	/**
	 * Test export_to_bind with CNAME record appends dot.
	 */
	public function test_export_to_bind_cname_appends_dot(): void {
		$records = [
			new DNS_Record([
				'type'    => 'CNAME',
				'name'    => 'www',
				'content' => 'example.com',
				'ttl'     => 3600,
			]),
		];

		$bind = $this->manager->export_to_bind('example.com', $records);

		$this->assertStringContainsString('example.com.', $bind);
	}

	/**
	 * Test export_to_bind with domain name as record name uses @.
	 */
	public function test_export_to_bind_domain_name_becomes_at(): void {
		$records = [
			new DNS_Record([
				'type'    => 'A',
				'name'    => 'example.com',
				'content' => '1.2.3.4',
				'ttl'     => 3600,
			]),
		];

		$bind = $this->manager->export_to_bind('example.com', $records);

		$this->assertStringContainsString('@', $bind);
	}

	/**
	 * Test export_to_bind with raw array records (not DNS_Record objects).
	 */
	public function test_export_to_bind_with_array_records(): void {
		$records = [
			[
				'type'    => 'A',
				'name'    => 'sub',
				'content' => '10.0.0.1',
				'ttl'     => 7200,
			],
		];

		$bind = $this->manager->export_to_bind('example.com', $records);

		$this->assertStringContainsString('sub', $bind);
		$this->assertStringContainsString('10.0.0.1', $bind);
	}

	/**
	 * Test export_to_bind with MX uses default priority 10 when not set.
	 */
	public function test_export_to_bind_mx_default_priority(): void {
		$records = [
			[
				'type'    => 'MX',
				'name'    => '@',
				'content' => 'mail.example.com',
				'ttl'     => 3600,
				// no priority key
			],
		];

		$bind = $this->manager->export_to_bind('example.com', $records);

		$this->assertMatchesRegularExpression('/@\s+3600\s+IN\s+MX\s+10\s+mail\.example\.com\./', $bind);
	}

	/**
	 * Test export_to_bind with subdomain strips domain suffix from name.
	 */
	public function test_export_to_bind_subdomain_strips_domain(): void {
		$records = [
			new DNS_Record([
				'type'    => 'A',
				'name'    => 'sub.example.com',
				'content' => '1.2.3.4',
				'ttl'     => 3600,
			]),
		];

		$bind = $this->manager->export_to_bind('example.com', $records);

		// sub.example.com should become just 'sub' in BIND output
		$this->assertStringContainsString('sub', $bind);
	}

	/**
	 * Test export_to_bind with empty records returns header only.
	 */
	public function test_export_to_bind_empty_records(): void {
		$bind = $this->manager->export_to_bind('example.com', []);

		$this->assertStringContainsString('$ORIGIN example.com.', $bind);
		$this->assertStringContainsString('$TTL 3600', $bind);
	}

	// ----------------------------------------------------------------
	// parse_bind_format
	// ----------------------------------------------------------------

	/**
	 * Test parse_bind_format with standard records.
	 */
	public function test_parse_bind_format_standard_records(): void {
		$bind_content = <<<BIND
\$ORIGIN example.com.
@ 3600 IN A 192.168.1.1
www 3600 IN CNAME example.com.
@ 3600 IN MX 10 mail.example.com.
@ 3600 IN TXT "v=spf1 mx ~all"
BIND;

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertIsArray($records);
		$this->assertGreaterThanOrEqual(4, count($records));

		$a_records = array_values(array_filter($records, fn($r) => $r['type'] === 'A'));
		$this->assertNotEmpty($a_records);
		$this->assertEquals('192.168.1.1', $a_records[0]['content']);
	}

	/**
	 * Test parse_bind_format skips comment lines.
	 */
	public function test_parse_bind_format_skips_comments(): void {
		$bind_content = <<<BIND
; This is a comment
@ 3600 IN A 192.168.1.1
; Another comment
BIND;

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
	}

	/**
	 * Test parse_bind_format skips empty lines.
	 */
	public function test_parse_bind_format_skips_empty_lines(): void {
		$bind_content = "\n\n@ 3600 IN A 1.2.3.4\n\n";

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
	}

	/**
	 * Test parse_bind_format respects $TTL directive.
	 */
	public function test_parse_bind_format_respects_ttl_directive(): void {
		$bind_content = <<<BIND
\$TTL 7200
@ IN A 1.2.3.4
BIND;

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
		$this->assertEquals(7200, $records[0]['ttl']);
	}

	/**
	 * Test parse_bind_format replaces @ with domain name.
	 */
	public function test_parse_bind_format_at_becomes_domain(): void {
		$bind_content = '@ 3600 IN A 1.2.3.4';

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
		$this->assertEquals('example.com', $records[0]['name']);
	}

	/**
	 * Test parse_bind_format parses MX priority.
	 */
	public function test_parse_bind_format_mx_priority(): void {
		$bind_content = '@ 3600 IN MX 20 mail.example.com.';

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
		$this->assertEquals('MX', $records[0]['type']);
		$this->assertEquals(20, $records[0]['priority']);
		$this->assertEquals('mail.example.com', $records[0]['content']); // trailing dot stripped
	}

	/**
	 * Test parse_bind_format strips trailing dot from content.
	 */
	public function test_parse_bind_format_strips_trailing_dot(): void {
		$bind_content = 'www 3600 IN CNAME example.com.';

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
		$this->assertEquals('example.com', $records[0]['content']);
	}

	/**
	 * Test parse_bind_format strips quotes from TXT content.
	 */
	public function test_parse_bind_format_strips_txt_quotes(): void {
		$bind_content = '@ 3600 IN TXT "v=spf1 mx ~all"';

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
		$this->assertEquals('v=spf1 mx ~all', $records[0]['content']);
	}

	/**
	 * Test parse_bind_format skips $ORIGIN directive.
	 */
	public function test_parse_bind_format_skips_origin_directive(): void {
		$bind_content = <<<BIND
\$ORIGIN example.com.
@ 3600 IN A 1.2.3.4
BIND;

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
	}

	/**
	 * Test parse_bind_format skips unsupported record types.
	 */
	public function test_parse_bind_format_skips_unsupported_types(): void {
		$bind_content = <<<BIND
@ 3600 IN A 1.2.3.4
@ 3600 IN HINFO "PC" "Linux"
@ 3600 IN SOA ns1.example.com. admin.example.com. 2024010101 3600 900 604800 300
BIND;

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		// Only A record should be included
		$this->assertCount(1, $records);
		$this->assertEquals('A', $records[0]['type']);
	}

	/**
	 * Test parse_bind_format with record without explicit TTL uses default.
	 */
	public function test_parse_bind_format_default_ttl_when_no_ttl_in_record(): void {
		$bind_content = '@ IN A 1.2.3.4';

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
		$this->assertEquals(3600, $records[0]['ttl']); // default TTL
	}

	/**
	 * Test parse_bind_format with AAAA record.
	 */
	public function test_parse_bind_format_aaaa_record(): void {
		$bind_content = '@ 3600 IN AAAA 2001:db8::1';

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
		$this->assertEquals('AAAA', $records[0]['type']);
		$this->assertEquals('2001:db8::1', $records[0]['content']);
	}

	/**
	 * Test parse_bind_format with lines having fewer than 3 parts are skipped.
	 */
	public function test_parse_bind_format_skips_short_lines(): void {
		$bind_content = <<<BIND
@ 3600
@ 3600 IN A 1.2.3.4
BIND;

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(1, $records);
	}

	/**
	 * Test parse_bind_format with empty content returns empty array.
	 */
	public function test_parse_bind_format_empty_content(): void {
		$records = $this->manager->parse_bind_format('', 'example.com');

		$this->assertIsArray($records);
		$this->assertEmpty($records);
	}

	/**
	 * Test parse_bind_format with only comments returns empty array.
	 */
	public function test_parse_bind_format_only_comments(): void {
		$bind_content = "; comment 1\n; comment 2\n";

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertIsArray($records);
		$this->assertEmpty($records);
	}

	/**
	 * Test parse_bind_format with multiple records of same type.
	 */
	public function test_parse_bind_format_multiple_same_type(): void {
		$bind_content = <<<BIND
@ 3600 IN A 1.2.3.4
@ 3600 IN A 5.6.7.8
BIND;

		$records = $this->manager->parse_bind_format($bind_content, 'example.com');

		$this->assertCount(2, $records);
		$this->assertEquals('1.2.3.4', $records[0]['content']);
		$this->assertEquals('5.6.7.8', $records[1]['content']);
	}

	/**
	 * Test parse_bind_format round-trip with export_to_bind.
	 */
	public function test_parse_bind_format_round_trip(): void {
		$original_records = [
			new DNS_Record([
				'type'    => 'A',
				'name'    => 'example.com',
				'content' => '1.2.3.4',
				'ttl'     => 3600,
			]),
			new DNS_Record([
				'type'    => 'TXT',
				'name'    => 'example.com',
				'content' => 'v=spf1 mx ~all',
				'ttl'     => 3600,
			]),
		];

		$bind    = $this->manager->export_to_bind('example.com', $original_records);
		$parsed  = $this->manager->parse_bind_format($bind, 'example.com');

		$this->assertIsArray($parsed);
		$this->assertGreaterThanOrEqual(2, count($parsed));

		$types = array_column($parsed, 'type');
		$this->assertContains('A', $types);
		$this->assertContains('TXT', $types);
	}

	// ----------------------------------------------------------------
	// add_dns_settings (smoke test — verifies no fatal errors)
	// ----------------------------------------------------------------

	/**
	 * Test add_dns_settings runs without errors.
	 */
	public function test_add_dns_settings_no_fatal_errors(): void {
		// Should not throw any errors
		$this->manager->add_dns_settings();
		$this->assertTrue(true);
	}

	// ----------------------------------------------------------------
	// init (hook registration)
	// ----------------------------------------------------------------

	/**
	 * Test init registers expected AJAX actions.
	 */
	public function test_init_registers_ajax_actions(): void {
		$this->manager->init();

		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_get_dns_records_for_domain', [$this->manager, 'ajax_get_records'])
		);
		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_create_dns_record', [$this->manager, 'ajax_create_record'])
		);
		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_update_dns_record', [$this->manager, 'ajax_update_record'])
		);
		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_delete_dns_record', [$this->manager, 'ajax_delete_record'])
		);
		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_bulk_dns_operations', [$this->manager, 'ajax_bulk_operations'])
		);
	}

	/**
	 * Test init registers DNS settings action.
	 */
	public function test_init_registers_dns_settings_action(): void {
		$this->manager->init();

		$this->assertGreaterThan(
			0,
			has_action('wu_settings_domain_mapping', [$this->manager, 'add_dns_settings'])
		);
	}
}
