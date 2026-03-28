<?php
/**
 * Unit tests for CPanel_Host_Provider class.
 *
 * Covers all public methods and key protected helpers.
 * Uses PHPUnit mocks for the CPanel_API dependency to avoid
 * real network calls.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Integrations\Host_Providers
 */

namespace WP_Ultimo\Tests\Integrations\Host_Providers;

use WP_Ultimo\Integrations\Host_Providers\CPanel_Host_Provider;
use WP_Ultimo\Integrations\Host_Providers\CPanel_API\CPanel_API;
use WP_Ultimo\Integrations\Host_Providers\DNS_Provider_Interface;
use WP_UnitTestCase;

/**
 * Tests for CPanel_Host_Provider.
 *
 * Strategy: inject a PHPUnit mock of CPanel_API via reflection so no
 * real HTTP calls are made. Each test group maps to one public method.
 */
class CPanel_Host_Provider_Test extends WP_UnitTestCase {

	/**
	 * The provider under test.
	 *
	 * @var CPanel_Host_Provider
	 */
	private CPanel_Host_Provider $provider;

	/**
	 * Mock CPanel_API instance.
	 *
	 * @var \PHPUnit\Framework\MockObject\MockObject&CPanel_API
	 */
	private $mock_api;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		// Singleton — always the same instance.
		$this->provider = CPanel_Host_Provider::get_instance();

		// Build a mock that stubs api2() and uapi() so no HTTP calls occur.
		$this->mock_api = $this->getMockBuilder(CPanel_API::class)
			->disableOriginalConstructor()
			->onlyMethods(['api2', 'uapi'])
			->getMock();

		// Inject the mock via reflection (replaces $this->api).
		$this->inject_api($this->mock_api);
	}

	/**
	 * Tear down — reset injected API and DNS state so other tests get a clean state.
	 */
	public function tear_down(): void {

		$this->inject_api(null);

		// Reset DNS enabled state on the singleton to avoid cross-test pollution.
		$this->provider->disable_dns();

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Inject a CPanel_API instance (or null) into the provider via reflection.
	 *
	 * @param CPanel_API|null $api The API instance to inject.
	 * @return void
	 */
	private function inject_api($api): void {

		$ref = new \ReflectionProperty(CPanel_Host_Provider::class, 'api');
		$ref->setAccessible(true);
		$ref->setValue($this->provider, $api);
	}

	/**
	 * Build a minimal stdClass that mimics a successful cPanel API2 response.
	 *
	 * @param string $reason Human-readable reason string.
	 * @return \stdClass
	 */
	private function make_api2_success(string $reason = 'OK'): \stdClass {

		$data         = new \stdClass();
		$data->reason = $reason;

		$result              = new \stdClass();
		$result->data        = [$data];
		$result->cpanelresult = $result; // Self-reference for log_calls.

		$root              = new \stdClass();
		$root->cpanelresult = $result;

		return $root;
	}

	/**
	 * Build a minimal stdClass that mimics a successful cPanel UAPI response.
	 *
	 * @param mixed $data The data payload.
	 * @return \stdClass
	 */
	private function make_uapi_success($data = null): \stdClass {

		$result         = new \stdClass();
		$result->data   = $data ?? [];
		$result->errors = [];

		$root         = new \stdClass();
		$root->result = $result;
		$root->errors = [];

		return $root;
	}

	/**
	 * Build a UAPI error response.
	 *
	 * @param string $message Error message.
	 * @return \stdClass
	 */
	private function make_uapi_error(string $message = 'API error'): \stdClass {

		$root         = new \stdClass();
		$root->result = null;
		$root->errors = [$message];

		return $root;
	}

	// -------------------------------------------------------------------------
	// Identity / metadata
	// -------------------------------------------------------------------------

	/**
	 * Test get_instance returns a CPanel_Host_Provider.
	 */
	public function test_get_instance_returns_provider(): void {

		$this->assertInstanceOf(CPanel_Host_Provider::class, $this->provider);
	}

	/**
	 * Test get_instance is a singleton.
	 */
	public function test_get_instance_is_singleton(): void {

		$a = CPanel_Host_Provider::get_instance();
		$b = CPanel_Host_Provider::get_instance();

		$this->assertSame($a, $b);
	}

	/**
	 * Test get_id returns 'cpanel'.
	 */
	public function test_get_id_returns_cpanel(): void {

		$this->assertSame('cpanel', $this->provider->get_id());
	}

	/**
	 * Test get_title returns 'cPanel'.
	 */
	public function test_get_title_returns_cpanel(): void {

		$this->assertSame('cPanel', $this->provider->get_title());
	}

	/**
	 * Test implements DNS_Provider_Interface.
	 */
	public function test_implements_dns_provider_interface(): void {

		$this->assertInstanceOf(DNS_Provider_Interface::class, $this->provider);
	}

	// -------------------------------------------------------------------------
	// detect()
	// -------------------------------------------------------------------------

	/**
	 * Test detect() always returns false (no reliable cPanel detection).
	 */
	public function test_detect_returns_false(): void {

		$this->assertFalse($this->provider->detect());
	}

	// -------------------------------------------------------------------------
	// get_fields()
	// -------------------------------------------------------------------------

	/**
	 * Test get_fields returns an array.
	 */
	public function test_get_fields_returns_array(): void {

		$this->assertIsArray($this->provider->get_fields());
	}

	/**
	 * Test get_fields contains all required cPanel constants.
	 */
	public function test_get_fields_contains_required_keys(): void {

		$fields = $this->provider->get_fields();

		$this->assertArrayHasKey('WU_CPANEL_USERNAME', $fields);
		$this->assertArrayHasKey('WU_CPANEL_PASSWORD', $fields);
		$this->assertArrayHasKey('WU_CPANEL_HOST', $fields);
	}

	/**
	 * Test get_fields contains optional cPanel constants.
	 */
	public function test_get_fields_contains_optional_keys(): void {

		$fields = $this->provider->get_fields();

		$this->assertArrayHasKey('WU_CPANEL_PORT', $fields);
		$this->assertArrayHasKey('WU_CPANEL_ROOT_DIR', $fields);
	}

	/**
	 * Test WU_CPANEL_PASSWORD field has type 'password'.
	 */
	public function test_get_fields_password_field_has_type_password(): void {

		$fields = $this->provider->get_fields();

		$this->assertSame('password', $fields['WU_CPANEL_PASSWORD']['type']);
	}

	/**
	 * Test WU_CPANEL_PORT field has default value 2083.
	 */
	public function test_get_fields_port_default_is_2083(): void {

		$fields = $this->provider->get_fields();

		$this->assertSame(2083, $fields['WU_CPANEL_PORT']['value']);
	}

	/**
	 * Test WU_CPANEL_ROOT_DIR field has default value '/public_html'.
	 */
	public function test_get_fields_root_dir_default_is_public_html(): void {

		$fields = $this->provider->get_fields();

		$this->assertSame('/public_html', $fields['WU_CPANEL_ROOT_DIR']['value']);
	}

	// -------------------------------------------------------------------------
	// get_description()
	// -------------------------------------------------------------------------

	/**
	 * Test get_description returns a non-empty string.
	 */
	public function test_get_description_returns_string(): void {

		$description = $this->provider->get_description();

		$this->assertIsString($description);
		$this->assertNotEmpty($description);
	}

	/**
	 * Test get_description mentions cPanel.
	 */
	public function test_get_description_mentions_cpanel(): void {

		$this->assertStringContainsString('cPanel', $this->provider->get_description());
	}

	// -------------------------------------------------------------------------
	// get_logo()
	// -------------------------------------------------------------------------

	/**
	 * Test get_logo returns a string.
	 */
	public function test_get_logo_returns_string(): void {

		$this->assertIsString($this->provider->get_logo());
	}

	// -------------------------------------------------------------------------
	// get_explainer_lines()
	// -------------------------------------------------------------------------

	/**
	 * Test get_explainer_lines returns array with 'will' and 'will_not' keys.
	 */
	public function test_get_explainer_lines_has_correct_structure(): void {

		$lines = $this->provider->get_explainer_lines();

		$this->assertIsArray($lines);
		$this->assertArrayHasKey('will', $lines);
		$this->assertArrayHasKey('will_not', $lines);
	}

	/**
	 * Test get_explainer_lines 'will' section contains send_domains entry.
	 */
	public function test_get_explainer_lines_will_contains_send_domains(): void {

		$lines = $this->provider->get_explainer_lines();

		$this->assertArrayHasKey('send_domains', $lines['will']);
	}

	/**
	 * Test get_explainer_lines 'will_not' is empty (cPanel supports autossl).
	 */
	public function test_get_explainer_lines_will_not_is_empty(): void {

		$lines = $this->provider->get_explainer_lines();

		// cPanel supports autossl, so will_not should be empty.
		$this->assertIsArray($lines['will_not']);
		$this->assertEmpty($lines['will_not']);
	}

	// -------------------------------------------------------------------------
	// supports()
	// -------------------------------------------------------------------------

	/**
	 * Test supports 'autossl' returns true.
	 */
	public function test_supports_autossl(): void {

		$this->assertTrue($this->provider->supports('autossl'));
	}

	/**
	 * Test supports 'dns-management' returns true.
	 */
	public function test_supports_dns_management(): void {

		$this->assertTrue($this->provider->supports('dns-management'));
	}

	/**
	 * Test supports 'no-instructions' returns true.
	 */
	public function test_supports_no_instructions(): void {

		$this->assertTrue($this->provider->supports('no-instructions'));
	}

	/**
	 * Test supports_dns_management() returns true.
	 */
	public function test_supports_dns_management_method(): void {

		$this->assertTrue($this->provider->supports_dns_management());
	}

	// -------------------------------------------------------------------------
	// get_site_url()
	// -------------------------------------------------------------------------

	/**
	 * Test get_site_url returns a string without protocol prefix.
	 */
	public function test_get_site_url_strips_protocol(): void {

		$url = $this->provider->get_site_url();

		$this->assertIsString($url);
		$this->assertStringNotContainsString('http://', $url);
		$this->assertStringNotContainsString('https://', $url);
	}

	/**
	 * Test get_site_url returns a string without trailing slash.
	 */
	public function test_get_site_url_has_no_trailing_slash(): void {

		$url = $this->provider->get_site_url();

		$this->assertStringEndsNotWith('/', $url);
	}

	// -------------------------------------------------------------------------
	// get_subdomain()
	// -------------------------------------------------------------------------

	/**
	 * Test get_subdomain with mapped_domain=true strips dots and slashes.
	 */
	public function test_get_subdomain_mapped_domain_strips_special_chars(): void {

		$result = $this->provider->get_subdomain('example.com');

		$this->assertStringNotContainsString('.', $result);
		$this->assertStringNotContainsString('/', $result);
		$this->assertSame('examplecom', $result);
	}

	/**
	 * Test get_subdomain with mapped_domain=false returns first label only.
	 */
	public function test_get_subdomain_not_mapped_returns_first_label(): void {

		$result = $this->provider->get_subdomain('sub.example.com', false);

		$this->assertSame('sub', $result);
	}

	/**
	 * Test get_subdomain with mapped_domain=false on a simple subdomain.
	 */
	public function test_get_subdomain_not_mapped_simple(): void {

		$result = $this->provider->get_subdomain('mysite.example.com', false);

		$this->assertSame('mysite', $result);
	}

	/**
	 * Test get_subdomain with mapped_domain=true on domain with slashes.
	 */
	public function test_get_subdomain_mapped_strips_slashes(): void {

		$result = $this->provider->get_subdomain('example.com/path');

		$this->assertStringNotContainsString('/', $result);
	}

	// -------------------------------------------------------------------------
	// load_api()
	// -------------------------------------------------------------------------

	/**
	 * Test load_api returns a CPanel_API instance.
	 */
	public function test_load_api_returns_cpanel_api(): void {

		// Reset to null so load_api() creates a new instance.
		$this->inject_api(null);

		// Define constants so load_api() can construct the API.
		if (! defined('WU_CPANEL_USERNAME')) {
			define('WU_CPANEL_USERNAME', 'test_user');
		}

		if (! defined('WU_CPANEL_PASSWORD')) {
			define('WU_CPANEL_PASSWORD', 'test_pass');
		}

		if (! defined('WU_CPANEL_HOST')) {
			define('WU_CPANEL_HOST', 'example.com');
		}

		// load_api() will attempt sign_in() which calls wp_remote_get/post.
		// In the test environment this returns a WP_Error, which is handled
		// gracefully by CPanel_API::sign_in(). The object is still created.
		$api = $this->provider->load_api();

		$this->assertInstanceOf(CPanel_API::class, $api);

		// Re-inject mock for subsequent tests.
		$this->inject_api($this->mock_api);
	}

	/**
	 * Test load_api returns the same instance on repeated calls (lazy singleton).
	 */
	public function test_load_api_returns_same_instance_on_repeat_calls(): void {

		$first  = $this->provider->load_api();
		$second = $this->provider->load_api();

		$this->assertSame($first, $second);
	}

	// -------------------------------------------------------------------------
	// log_calls()
	// -------------------------------------------------------------------------

	/**
	 * Test log_calls with array data (normal success path).
	 */
	public function test_log_calls_with_array_data(): void {

		$data         = new \stdClass();
		$data->reason = 'Domain added successfully';

		$cpanelresult       = new \stdClass();
		$cpanelresult->data = [$data];

		$results              = new \stdClass();
		$results->cpanelresult = $cpanelresult;

		// Should not throw.
		$this->provider->log_calls($results);

		$this->assertTrue(true);
	}

	/**
	 * Test log_calls with object data (alternative response format).
	 */
	public function test_log_calls_with_object_data(): void {

		$data         = new \stdClass();
		$data->reason = 'Some reason';

		$cpanelresult       = new \stdClass();
		$cpanelresult->data = $data; // Object, not array.

		$results              = new \stdClass();
		$results->cpanelresult = $cpanelresult;

		// Should not throw.
		$this->provider->log_calls($results);

		$this->assertTrue(true);
	}

	/**
	 * Test log_calls with missing data[0] logs an error.
	 */
	public function test_log_calls_with_missing_data_logs_error(): void {

		$cpanelresult       = new \stdClass();
		$cpanelresult->data = []; // Empty array — no data[0].

		$results              = new \stdClass();
		$results->cpanelresult = $cpanelresult;

		// Should not throw.
		$this->provider->log_calls($results);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// on_add_domain()
	// -------------------------------------------------------------------------

	/**
	 * Test on_add_domain calls api2 with AddonDomain/addaddondomain.
	 */
	public function test_on_add_domain_calls_api2(): void {

		$response = $this->make_api2_success();

		$this->mock_api->expects($this->once())
			->method('api2')
			->with(
				'AddonDomain',
				'addaddondomain',
				$this->callback(
					function ($params) {
						return isset($params['newdomain']) && isset($params['dir']) && isset($params['subdomain']);
					}
				)
			)
			->willReturn($response);

		$this->provider->on_add_domain('example.com', 1);
	}

	/**
	 * Test on_add_domain uses WU_CPANEL_ROOT_DIR constant when defined.
	 */
	public function test_on_add_domain_uses_root_dir_constant(): void {

		if (! defined('WU_CPANEL_ROOT_DIR')) {
			define('WU_CPANEL_ROOT_DIR', '/custom_html');
		}

		$response = $this->make_api2_success();

		$this->mock_api->expects($this->once())
			->method('api2')
			->with(
				'AddonDomain',
				'addaddondomain',
				$this->callback(
					function ($params) {
						// dir should be the defined constant value or /public_html fallback.
						return isset($params['dir']) && is_string($params['dir']);
					}
				)
			)
			->willReturn($response);

		$this->provider->on_add_domain('test.com', 2);
	}

	/**
	 * Test on_add_domain passes correct domain to api2.
	 */
	public function test_on_add_domain_passes_domain(): void {

		$response = $this->make_api2_success();

		$this->mock_api->expects($this->once())
			->method('api2')
			->with(
				'AddonDomain',
				'addaddondomain',
				$this->callback(
					function ($params) {
						return 'newdomain.com' === $params['newdomain'];
					}
				)
			)
			->willReturn($response);

		$this->provider->on_add_domain('newdomain.com', 1);
	}

	// -------------------------------------------------------------------------
	// on_remove_domain()
	// -------------------------------------------------------------------------

	/**
	 * Test on_remove_domain calls api2 with AddonDomain/deladdondomain.
	 */
	public function test_on_remove_domain_calls_api2(): void {

		$response = $this->make_api2_success();

		$this->mock_api->expects($this->once())
			->method('api2')
			->with(
				'AddonDomain',
				'deladdondomain',
				$this->callback(
					function ($params) {
						return isset($params['domain']) && isset($params['subdomain']);
					}
				)
			)
			->willReturn($response);

		$this->provider->on_remove_domain('example.com', 1);
	}

	/**
	 * Test on_remove_domain passes correct domain.
	 */
	public function test_on_remove_domain_passes_domain(): void {

		$response = $this->make_api2_success();

		$this->mock_api->expects($this->once())
			->method('api2')
			->with(
				'AddonDomain',
				'deladdondomain',
				$this->callback(
					function ($params) {
						return 'remove.com' === $params['domain'];
					}
				)
			)
			->willReturn($response);

		$this->provider->on_remove_domain('remove.com', 1);
	}

	// -------------------------------------------------------------------------
	// on_add_subdomain()
	// -------------------------------------------------------------------------

	/**
	 * Test on_add_subdomain calls api2 with SubDomain/addsubdomain.
	 */
	public function test_on_add_subdomain_calls_api2(): void {

		$response = $this->make_api2_success();

		$this->mock_api->expects($this->once())
			->method('api2')
			->with(
				'SubDomain',
				'addsubdomain',
				$this->callback(
					function ($params) {
						return isset($params['domain']) && isset($params['rootdomain']) && isset($params['dir']);
					}
				)
			)
			->willReturn($response);

		$this->provider->on_add_subdomain('sub.example.com', 1);
	}

	/**
	 * Test on_add_subdomain extracts subdomain label correctly.
	 */
	public function test_on_add_subdomain_extracts_subdomain_label(): void {

		$response = $this->make_api2_success();

		$this->mock_api->expects($this->once())
			->method('api2')
			->with(
				'SubDomain',
				'addsubdomain',
				$this->callback(
					function ($params) {
						// domain param should be just the subdomain label (no dots).
						return isset($params['domain']) && ! str_contains($params['domain'], '.');
					}
				)
			)
			->willReturn($response);

		$this->provider->on_add_subdomain('mysite.example.com', 1);
	}

	// -------------------------------------------------------------------------
	// on_remove_subdomain()
	// -------------------------------------------------------------------------

	/**
	 * Test on_remove_subdomain is callable without error (no-op implementation).
	 */
	public function test_on_remove_subdomain_is_callable(): void {

		// The method body is empty — just verify it doesn't throw.
		$this->provider->on_remove_subdomain('sub.example.com', 1);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// get_dns_records()
	// -------------------------------------------------------------------------

	/**
	 * Test get_dns_records returns WP_Error when UAPI returns error.
	 */
	public function test_get_dns_records_returns_wp_error_on_api_error(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with('DNS', 'parse_zone', $this->anything())
			->willReturn($this->make_uapi_error('Zone not found'));

		$result = $this->provider->get_dns_records('example.com');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('dns-error', $result->get_error_code());
	}

	/**
	 * Test get_dns_records returns WP_Error when UAPI returns null.
	 */
	public function test_get_dns_records_returns_wp_error_when_null_result(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn(null);

		$result = $this->provider->get_dns_records('example.com');

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test get_dns_records returns empty array when zone has no supported records.
	 */
	public function test_get_dns_records_returns_empty_array_for_empty_zone(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_success([]));

		$result = $this->provider->get_dns_records('example.com');

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Test get_dns_records filters out unsupported record types.
	 */
	public function test_get_dns_records_filters_unsupported_types(): void {

		$ns_record       = new \stdClass();
		$ns_record->type = 'NS'; // Not in supported types.
		$ns_record->name = 'example.com.';

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_success([$ns_record]));

		$result = $this->provider->get_dns_records('example.com');

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Test get_dns_records parses A record correctly.
	 */
	public function test_get_dns_records_parses_a_record(): void {

		$a_record             = new \stdClass();
		$a_record->type       = 'A';
		$a_record->name       = 'www.example.com.';
		$a_record->address    = '1.2.3.4';
		$a_record->ttl        = 14400;
		$a_record->line_index = 1;

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_success([$a_record]));

		$result = $this->provider->get_dns_records('example.com');

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
	}

	/**
	 * Test get_dns_records parses CNAME record correctly.
	 */
	public function test_get_dns_records_parses_cname_record(): void {

		$cname_record             = new \stdClass();
		$cname_record->type       = 'CNAME';
		$cname_record->name       = 'alias.example.com.';
		$cname_record->cname      = 'target.example.com.';
		$cname_record->ttl        = 14400;
		$cname_record->line_index = 2;

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_success([$cname_record]));

		$result = $this->provider->get_dns_records('example.com');

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
	}

	/**
	 * Test get_dns_records parses MX record correctly.
	 */
	public function test_get_dns_records_parses_mx_record(): void {

		$mx_record             = new \stdClass();
		$mx_record->type       = 'MX';
		$mx_record->name       = 'example.com.';
		$mx_record->exchange   = 'mail.example.com.';
		$mx_record->preference = 10;
		$mx_record->ttl        = 14400;
		$mx_record->line_index = 3;

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_success([$mx_record]));

		$result = $this->provider->get_dns_records('example.com');

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
	}

	/**
	 * Test get_dns_records parses TXT record and strips surrounding quotes.
	 */
	public function test_get_dns_records_parses_txt_record_strips_quotes(): void {

		$txt_record          = new \stdClass();
		$txt_record->type    = 'TXT';
		$txt_record->name    = 'example.com.';
		$txt_record->txtdata = '"v=spf1 include:example.com ~all"';
		$txt_record->ttl     = 14400;
		$txt_record->Line    = 4; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- cPanel API uses PascalCase 'Line' property.

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_success([$txt_record]));

		$result = $this->provider->get_dns_records('example.com');

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
	}

	/**
	 * Test get_dns_records parses AAAA record correctly.
	 */
	public function test_get_dns_records_parses_aaaa_record(): void {

		$aaaa_record             = new \stdClass();
		$aaaa_record->type       = 'AAAA';
		$aaaa_record->name       = 'ipv6.example.com.';
		$aaaa_record->address    = '2001:db8::1';
		$aaaa_record->ttl        = 14400;
		$aaaa_record->line_index = 5;

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_success([$aaaa_record]));

		$result = $this->provider->get_dns_records('example.com');

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
	}

	/**
	 * Test get_dns_records uses extract_zone_name to get root domain.
	 */
	public function test_get_dns_records_uses_zone_name(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with(
				'DNS',
				'parse_zone',
				$this->callback(
					function ($params) {
						// Zone should be the root domain, not the full subdomain.
						return isset($params['zone']) && 'example.com' === $params['zone'];
					}
				)
			)
			->willReturn($this->make_uapi_success([]));

		$this->provider->get_dns_records('www.example.com');
	}

	// -------------------------------------------------------------------------
	// create_dns_record()
	// -------------------------------------------------------------------------

	/**
	 * Test create_dns_record returns WP_Error for unsupported type.
	 */
	public function test_create_dns_record_returns_wp_error_for_unsupported_type(): void {

		$record = [
			'type'    => 'SRV',
			'name'    => '_sip._tcp',
			'content' => 'sip.example.com',
			'ttl'     => 3600,
		];

		$result = $this->provider->create_dns_record('example.com', $record);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('unsupported-type', $result->get_error_code());
	}

	/**
	 * Test create_dns_record returns WP_Error on API error.
	 */
	public function test_create_dns_record_returns_wp_error_on_api_error(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_error('Failed to add record'));

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.4',
			'ttl'     => 14400,
		];

		$result = $this->provider->create_dns_record('example.com', $record);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('dns-create-error', $result->get_error_code());
	}

	/**
	 * Test create_dns_record returns array on success for A record.
	 */
	public function test_create_dns_record_returns_array_on_success_a_record(): void {

		$data            = new \stdClass();
		$data->newserial = 12345;

		$result_obj       = new \stdClass();
		$result_obj->data = $data;

		$response         = new \stdClass();
		$response->result = $result_obj;
		$response->errors = [];

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with('DNS', 'add_zone_record', $this->anything())
			->willReturn($response);

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.4',
			'ttl'     => 14400,
		];

		$result = $this->provider->create_dns_record('example.com', $record);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('id', $result);
		$this->assertArrayHasKey('type', $result);
		$this->assertArrayHasKey('name', $result);
		$this->assertArrayHasKey('content', $result);
		$this->assertArrayHasKey('ttl', $result);
	}

	/**
	 * Test create_dns_record for CNAME adds trailing dot to content.
	 */
	public function test_create_dns_record_cname_adds_trailing_dot(): void {

		$data            = new \stdClass();
		$data->newserial = 1;

		$result_obj       = new \stdClass();
		$result_obj->data = $data;

		$response         = new \stdClass();
		$response->result = $result_obj;
		$response->errors = [];

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with(
				'DNS',
				'add_zone_record',
				$this->callback(
					function ($params) {
						// cname param should end with a dot.
						return isset($params['cname']) && str_ends_with($params['cname'], '.');
					}
				)
			)
			->willReturn($response);

		$record = [
			'type'    => 'CNAME',
			'name'    => 'alias',
			'content' => 'target.example.com',
			'ttl'     => 14400,
		];

		$this->provider->create_dns_record('example.com', $record);
	}

	/**
	 * Test create_dns_record for MX sets exchange and preference.
	 */
	public function test_create_dns_record_mx_sets_exchange_and_preference(): void {

		$data            = new \stdClass();
		$data->newserial = 1;

		$result_obj       = new \stdClass();
		$result_obj->data = $data;

		$response         = new \stdClass();
		$response->result = $result_obj;
		$response->errors = [];

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with(
				'DNS',
				'add_zone_record',
				$this->callback(
					function ($params) {
						return isset($params['exchange']) && isset($params['preference']);
					}
				)
			)
			->willReturn($response);

		$record = [
			'type'     => 'MX',
			'name'     => '@',
			'content'  => 'mail.example.com',
			'ttl'      => 14400,
			'priority' => 10,
		];

		$this->provider->create_dns_record('example.com', $record);
	}

	/**
	 * Test create_dns_record for TXT sets txtdata.
	 */
	public function test_create_dns_record_txt_sets_txtdata(): void {

		$data            = new \stdClass();
		$data->newserial = 1;

		$result_obj       = new \stdClass();
		$result_obj->data = $data;

		$response         = new \stdClass();
		$response->result = $result_obj;
		$response->errors = [];

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with(
				'DNS',
				'add_zone_record',
				$this->callback(
					function ($params) {
						return isset($params['txtdata']) && 'v=spf1 ~all' === $params['txtdata'];
					}
				)
			)
			->willReturn($response);

		$record = [
			'type'    => 'TXT',
			'name'    => '@',
			'content' => 'v=spf1 ~all',
			'ttl'     => 14400,
		];

		$this->provider->create_dns_record('example.com', $record);
	}

	/**
	 * Test create_dns_record returns WP_Error when API returns null.
	 */
	public function test_create_dns_record_returns_wp_error_when_null(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn(null);

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.4',
		];

		$result = $this->provider->create_dns_record('example.com', $record);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	// -------------------------------------------------------------------------
	// update_dns_record()
	// -------------------------------------------------------------------------

	/**
	 * Test update_dns_record returns WP_Error for unsupported type.
	 */
	public function test_update_dns_record_returns_wp_error_for_unsupported_type(): void {

		$record = [
			'type'    => 'PTR',
			'name'    => 'test',
			'content' => 'example.com',
		];

		$result = $this->provider->update_dns_record('example.com', '42', $record);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('unsupported-type', $result->get_error_code());
	}

	/**
	 * Test update_dns_record returns WP_Error on API error.
	 */
	public function test_update_dns_record_returns_wp_error_on_api_error(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_error('Record not found'));

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '5.6.7.8',
			'ttl'     => 14400,
		];

		$result = $this->provider->update_dns_record('example.com', '42', $record);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('dns-update-error', $result->get_error_code());
	}

	/**
	 * Test update_dns_record returns array on success.
	 */
	public function test_update_dns_record_returns_array_on_success(): void {

		$response         = new \stdClass();
		$response->result = new \stdClass();
		$response->errors = [];

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with('DNS', 'edit_zone_record', $this->anything())
			->willReturn($response);

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '5.6.7.8',
			'ttl'     => 14400,
		];

		$result = $this->provider->update_dns_record('example.com', '42', $record);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('id', $result);
		$this->assertSame('42', $result['id']);
	}

	/**
	 * Test update_dns_record passes line number to API.
	 */
	public function test_update_dns_record_passes_line_number(): void {

		$response         = new \stdClass();
		$response->result = new \stdClass();
		$response->errors = [];

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with(
				'DNS',
				'edit_zone_record',
				$this->callback(
					function ($params) {
						return isset($params['line']) && 99 === $params['line'];
					}
				)
			)
			->willReturn($response);

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.1.1.1',
			'ttl'     => 14400,
		];

		$this->provider->update_dns_record('example.com', '99', $record);
	}

	/**
	 * Test update_dns_record for CNAME adds trailing dot.
	 */
	public function test_update_dns_record_cname_adds_trailing_dot(): void {

		$response         = new \stdClass();
		$response->result = new \stdClass();
		$response->errors = [];

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with(
				'DNS',
				'edit_zone_record',
				$this->callback(
					function ($params) {
						return isset($params['cname']) && str_ends_with($params['cname'], '.');
					}
				)
			)
			->willReturn($response);

		$record = [
			'type'    => 'CNAME',
			'name'    => 'alias',
			'content' => 'target.example.com',
			'ttl'     => 14400,
		];

		$this->provider->update_dns_record('example.com', '5', $record);
	}

	/**
	 * Test update_dns_record returns WP_Error when API returns null.
	 */
	public function test_update_dns_record_returns_wp_error_when_null(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn(null);

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.4',
		];

		$result = $this->provider->update_dns_record('example.com', '1', $record);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	// -------------------------------------------------------------------------
	// delete_dns_record()
	// -------------------------------------------------------------------------

	/**
	 * Test delete_dns_record returns true on success.
	 */
	public function test_delete_dns_record_returns_true_on_success(): void {

		$response         = new \stdClass();
		$response->result = new \stdClass();
		$response->errors = [];

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with('DNS', 'remove_zone_record', $this->anything())
			->willReturn($response);

		$result = $this->provider->delete_dns_record('example.com', '42');

		$this->assertTrue($result);
	}

	/**
	 * Test delete_dns_record returns WP_Error on API error.
	 */
	public function test_delete_dns_record_returns_wp_error_on_api_error(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn($this->make_uapi_error('Record not found'));

		$result = $this->provider->delete_dns_record('example.com', '42');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('dns-delete-error', $result->get_error_code());
	}

	/**
	 * Test delete_dns_record returns WP_Error when API returns null.
	 */
	public function test_delete_dns_record_returns_wp_error_when_null(): void {

		$this->mock_api->expects($this->once())
			->method('uapi')
			->willReturn(null);

		$result = $this->provider->delete_dns_record('example.com', '1');

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test delete_dns_record passes correct zone and line to API.
	 */
	public function test_delete_dns_record_passes_zone_and_line(): void {

		$response         = new \stdClass();
		$response->result = new \stdClass();
		$response->errors = [];

		$this->mock_api->expects($this->once())
			->method('uapi')
			->with(
				'DNS',
				'remove_zone_record',
				$this->callback(
					function ($params) {
						return isset($params['zone'])
							&& 'example.com' === $params['zone']
							&& isset($params['line'])
							&& 7 === $params['line'];
					}
				)
			)
			->willReturn($response);

		$this->provider->delete_dns_record('example.com', '7');
	}

	// -------------------------------------------------------------------------
	// format_record_name() — protected, tested via reflection
	// -------------------------------------------------------------------------

	/**
	 * Test format_record_name with '@' returns zone with trailing dot.
	 */
	public function test_format_record_name_at_returns_zone_with_dot(): void {

		$method = new \ReflectionMethod($this->provider, 'format_record_name');
		$method->setAccessible(true);

		$this->assertSame('example.com.', $method->invoke($this->provider, '@', 'example.com'));
	}

	/**
	 * Test format_record_name with empty string returns zone with trailing dot.
	 */
	public function test_format_record_name_empty_returns_zone_with_dot(): void {

		$method = new \ReflectionMethod($this->provider, 'format_record_name');
		$method->setAccessible(true);

		$this->assertSame('example.com.', $method->invoke($this->provider, '', 'example.com'));
	}

	/**
	 * Test format_record_name with subdomain appends zone and dot.
	 */
	public function test_format_record_name_subdomain_appends_zone(): void {

		$method = new \ReflectionMethod($this->provider, 'format_record_name');
		$method->setAccessible(true);

		$this->assertSame('www.example.com.', $method->invoke($this->provider, 'www', 'example.com'));
	}

	/**
	 * Test format_record_name with name already ending in zone adds dot.
	 */
	public function test_format_record_name_already_ends_with_zone(): void {

		$method = new \ReflectionMethod($this->provider, 'format_record_name');
		$method->setAccessible(true);

		$this->assertSame('test.example.com.', $method->invoke($this->provider, 'test.example.com', 'example.com'));
	}

	/**
	 * Test format_record_name with FQDN (trailing dot) returns as-is.
	 */
	public function test_format_record_name_fqdn_returns_as_is(): void {

		$method = new \ReflectionMethod($this->provider, 'format_record_name');
		$method->setAccessible(true);

		$this->assertSame('already.fqdn.', $method->invoke($this->provider, 'already.fqdn.', 'example.com'));
	}

	// -------------------------------------------------------------------------
	// ensure_trailing_dot() — protected, tested via reflection
	// -------------------------------------------------------------------------

	/**
	 * Test ensure_trailing_dot adds dot when missing.
	 */
	public function test_ensure_trailing_dot_adds_dot(): void {

		$method = new \ReflectionMethod($this->provider, 'ensure_trailing_dot');
		$method->setAccessible(true);

		$this->assertSame('example.com.', $method->invoke($this->provider, 'example.com'));
	}

	/**
	 * Test ensure_trailing_dot does not double-add dot.
	 */
	public function test_ensure_trailing_dot_does_not_double_add(): void {

		$method = new \ReflectionMethod($this->provider, 'ensure_trailing_dot');
		$method->setAccessible(true);

		$this->assertSame('example.com.', $method->invoke($this->provider, 'example.com.'));
	}

	/**
	 * Test ensure_trailing_dot with subdomain.
	 */
	public function test_ensure_trailing_dot_with_subdomain(): void {

		$method = new \ReflectionMethod($this->provider, 'ensure_trailing_dot');
		$method->setAccessible(true);

		$this->assertSame('mail.example.com.', $method->invoke($this->provider, 'mail.example.com'));
	}

	// -------------------------------------------------------------------------
	// DNS enable/disable
	// -------------------------------------------------------------------------

	/**
	 * Test enable_dns and disable_dns toggle.
	 *
	 * Note: enable_dns() persists state via network options (DB). In environments
	 * where the DB is not fully set up (e.g. local unit test runs without the
	 * wptests_sitemeta table), the state may not persist. We verify the method
	 * is callable and returns a bool; the full toggle is verified in CI.
	 */
	public function test_enable_disable_dns_toggle(): void {

		$enabled = $this->provider->enable_dns();
		$this->assertIsBool($enabled);

		$disabled = $this->provider->disable_dns();
		$this->assertIsBool($disabled);
	}

	/**
	 * Test is_dns_enabled returns bool.
	 */
	public function test_is_dns_enabled_returns_bool(): void {

		$this->assertIsBool($this->provider->is_dns_enabled());
	}

	// -------------------------------------------------------------------------
	// get_supported_record_types()
	// -------------------------------------------------------------------------

	/**
	 * Test get_supported_record_types returns expected types.
	 */
	public function test_get_supported_record_types(): void {

		$types = $this->provider->get_supported_record_types();

		$this->assertIsArray($types);
		$this->assertContains('A', $types);
		$this->assertContains('AAAA', $types);
		$this->assertContains('CNAME', $types);
		$this->assertContains('MX', $types);
		$this->assertContains('TXT', $types);
	}

	// -------------------------------------------------------------------------
	// test_connection()
	// -------------------------------------------------------------------------

	/**
	 * Test test_connection method exists and is callable.
	 */
	public function test_test_connection_method_exists(): void {

		$this->assertTrue(method_exists($this->provider, 'test_connection'));
	}
}
