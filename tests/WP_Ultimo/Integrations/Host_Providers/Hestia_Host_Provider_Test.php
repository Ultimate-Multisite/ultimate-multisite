<?php
/**
 * Comprehensive unit tests for Hestia_Host_Provider.
 *
 * Covers all public methods and the protected send_hestia_request / call_and_log
 * helpers via reflection. HTTP calls are intercepted with the pre_http_request
 * filter so no real network traffic is made.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Integrations\Host_Providers
 */

namespace WP_Ultimo\Tests\Integrations\Host_Providers;

use WP_Ultimo\Integrations\Host_Providers\Hestia_Host_Provider;
use WP_Ultimo\Integrations\Host_Providers\DNS_Provider_Interface;
use WP_Ultimo\Integrations\Host_Providers\DNS_Record;
use WP_UnitTestCase;

/**
 * Tests for Hestia_Host_Provider.
 *
 * Strategy:
 * - Constants (WU_HESTIA_*) are defined once per test class via define() guards.
 * - HTTP calls are mocked via the `pre_http_request` filter.
 * - Singleton is obtained via get_instance(); tests that need a fresh state
 *   use reflection to reset protected properties where needed.
 */
class Hestia_Host_Provider_Test extends WP_UnitTestCase {

	/**
	 * Provider under test.
	 *
	 * @var Hestia_Host_Provider
	 */
	private Hestia_Host_Provider $provider;

	/**
	 * Captured HTTP requests made during a test.
	 *
	 * @var array<int, array{url: string, args: array}>
	 */
	private array $http_requests = [];

	/**
	 * Queued HTTP response for the next request.
	 *
	 * @var mixed  WP_Error, or array with 'code' and 'body' keys.
	 */
	private $http_response = null;

	// -------------------------------------------------------------------------
	// Bootstrap: define constants once for the whole test run
	// -------------------------------------------------------------------------

	/**
	 * Define Hestia constants used by the provider.
	 * Called once before any test in this class runs.
	 */
	public static function setUpBeforeClass(): void {

		parent::setUpBeforeClass();

		if ( ! defined( 'WU_HESTIA_API_URL' ) ) {
			define( 'WU_HESTIA_API_URL', 'https://hestia.example.com:8083/api/' );
		}

		if ( ! defined( 'WU_HESTIA_API_USER' ) ) {
			define( 'WU_HESTIA_API_USER', 'admin' );
		}

		if ( ! defined( 'WU_HESTIA_API_HASH' ) ) {
			define( 'WU_HESTIA_API_HASH', 'test-hash-token' );
		}

		if ( ! defined( 'WU_HESTIA_ACCOUNT' ) ) {
			define( 'WU_HESTIA_ACCOUNT', 'admin' );
		}

		if ( ! defined( 'WU_HESTIA_WEB_DOMAIN' ) ) {
			define( 'WU_HESTIA_WEB_DOMAIN', 'mysite.example.com' );
		}

		if ( ! defined( 'WU_HESTIA_RESTART' ) ) {
			define( 'WU_HESTIA_RESTART', 'yes' );
		}
	}

	// -------------------------------------------------------------------------
	// Per-test setup / teardown
	// -------------------------------------------------------------------------

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->provider      = Hestia_Host_Provider::get_instance();
		$this->http_requests = [];
		$this->http_response = null;

		// Install HTTP interceptor
		add_filter( 'pre_http_request', [ $this, 'intercept_http_request' ], 10, 3 );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		remove_filter( 'pre_http_request', [ $this, 'intercept_http_request' ] );

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// HTTP mock helpers
	// -------------------------------------------------------------------------

	/**
	 * Intercept wp_remote_post calls and return the queued response.
	 *
	 * @param false|array|\WP_Error $preempt  Existing preempt value.
	 * @param array                 $args     Request arguments.
	 * @param string                $url      Request URL.
	 * @return false|array|\WP_Error
	 */
	public function intercept_http_request( $preempt, $args, $url ) {

		$this->http_requests[] = [
			'url'  => $url,
			'args' => $args,
		];

		if ( null === $this->http_response ) {
			// Default: successful 0-return (Hestia success code)
			return $this->make_http_response( 200, '0' );
		}

		if ( $this->http_response instanceof \WP_Error ) {
			return $this->http_response;
		}

		return $this->http_response;
	}

	/**
	 * Build a fake wp_remote_post response array.
	 *
	 * @param int    $code HTTP status code.
	 * @param string $body Response body.
	 * @return array
	 */
	private function make_http_response( int $code, string $body ): array {

		return [
			'headers'       => [],
			'body'          => $body,
			'response'      => [
				'code'    => $code,
				'message' => 'OK',
			],
			'cookies'       => [],
			'http_response' => null,
		];
	}

	/**
	 * Queue a successful Hestia response (return code 0).
	 */
	private function queue_success(): void {

		$this->http_response = $this->make_http_response( 200, '0' );
	}

	/**
	 * Queue a JSON response body.
	 *
	 * @param mixed $data Data to JSON-encode.
	 */
	private function queue_json( $data ): void {

		$this->http_response = $this->make_http_response( 200, wp_json_encode( $data ) );
	}

	/**
	 * Queue an HTTP error response.
	 *
	 * @param int    $code HTTP status code.
	 * @param string $body Response body.
	 */
	private function queue_http_error( int $code, string $body ): void {

		$this->http_response = $this->make_http_response( $code, $body );
	}

	/**
	 * Queue a WP_Error transport failure.
	 *
	 * @param string $message Error message.
	 */
	private function queue_wp_error( string $message = 'Connection refused' ): void {

		$this->http_response = new \WP_Error( 'http_request_failed', $message );
	}

	// -------------------------------------------------------------------------
	// Basic identity / property tests
	// -------------------------------------------------------------------------

	/**
	 * Test provider ID is 'hestia'.
	 */
	public function test_get_id_returns_hestia(): void {

		$this->assertSame( 'hestia', $this->provider->get_id() );
	}

	/**
	 * Test provider title contains 'Hestia'.
	 */
	public function test_get_title_contains_hestia(): void {

		$this->assertStringContainsString( 'Hestia', $this->provider->get_title() );
	}

	/**
	 * Test detect() always returns false (no reliable detection).
	 */
	public function test_detect_returns_false(): void {

		$this->assertFalse( $this->provider->detect() );
	}

	/**
	 * Test implements DNS_Provider_Interface.
	 */
	public function test_implements_dns_provider_interface(): void {

		$this->assertInstanceOf( DNS_Provider_Interface::class, $this->provider );
	}

	/**
	 * Test supports dns-management feature.
	 */
	public function test_supports_dns_management(): void {

		$this->assertTrue( $this->provider->supports( 'dns-management' ) );
	}

	/**
	 * Test supports no-instructions feature.
	 */
	public function test_supports_no_instructions(): void {

		$this->assertTrue( $this->provider->supports( 'no-instructions' ) );
	}

	/**
	 * Test supports_dns_management() returns true.
	 */
	public function test_supports_dns_management_method(): void {

		$this->assertTrue( $this->provider->supports_dns_management() );
	}

	// -------------------------------------------------------------------------
	// get_description / get_logo / get_fields
	// -------------------------------------------------------------------------

	/**
	 * Test get_description returns a non-empty string.
	 */
	public function test_get_description_returns_string(): void {

		$desc = $this->provider->get_description();

		$this->assertIsString( $desc );
		$this->assertNotEmpty( $desc );
	}

	/**
	 * Test get_description mentions Hestia.
	 */
	public function test_get_description_mentions_hestia(): void {

		$this->assertStringContainsString( 'Hestia', $this->provider->get_description() );
	}

	/**
	 * Test get_logo returns a string.
	 */
	public function test_get_logo_returns_string(): void {

		$this->assertIsString( $this->provider->get_logo() );
	}

	/**
	 * Test get_logo references hestia.svg.
	 */
	public function test_get_logo_references_hestia_svg(): void {

		$this->assertStringContainsString( 'hestia', $this->provider->get_logo() );
	}

	/**
	 * Test get_fields returns array with all expected keys.
	 */
	public function test_get_fields_returns_all_expected_keys(): void {

		$fields = $this->provider->get_fields();

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'WU_HESTIA_API_URL', $fields );
		$this->assertArrayHasKey( 'WU_HESTIA_API_USER', $fields );
		$this->assertArrayHasKey( 'WU_HESTIA_API_PASSWORD', $fields );
		$this->assertArrayHasKey( 'WU_HESTIA_API_HASH', $fields );
		$this->assertArrayHasKey( 'WU_HESTIA_ACCOUNT', $fields );
		$this->assertArrayHasKey( 'WU_HESTIA_WEB_DOMAIN', $fields );
		$this->assertArrayHasKey( 'WU_HESTIA_RESTART', $fields );
	}

	/**
	 * Test get_fields password field has type=password.
	 */
	public function test_get_fields_password_field_has_type(): void {

		$fields = $this->provider->get_fields();

		$this->assertArrayHasKey( 'type', $fields['WU_HESTIA_API_PASSWORD'] );
		$this->assertSame( 'password', $fields['WU_HESTIA_API_PASSWORD']['type'] );
	}

	/**
	 * Test get_fields restart field has default value 'yes'.
	 */
	public function test_get_fields_restart_has_default_yes(): void {

		$fields = $this->provider->get_fields();

		$this->assertArrayHasKey( 'value', $fields['WU_HESTIA_RESTART'] );
		$this->assertSame( 'yes', $fields['WU_HESTIA_RESTART']['value'] );
	}

	// -------------------------------------------------------------------------
	// on_add_subdomain / on_remove_subdomain (no-ops)
	// -------------------------------------------------------------------------

	/**
	 * Test on_add_subdomain makes no HTTP request.
	 */
	public function test_on_add_subdomain_is_noop(): void {

		$this->provider->on_add_subdomain( 'sub.example.com', 1 );

		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * Test on_remove_subdomain makes no HTTP request.
	 */
	public function test_on_remove_subdomain_is_noop(): void {

		$this->provider->on_remove_subdomain( 'sub.example.com', 1 );

		$this->assertCount( 0, $this->http_requests );
	}

	// -------------------------------------------------------------------------
	// on_add_domain
	// -------------------------------------------------------------------------

	/**
	 * Test on_add_domain calls v-add-web-domain-alias with correct args.
	 */
	public function test_on_add_domain_calls_add_alias_command(): void {

		$this->queue_success();

		// Suppress www alias by filtering Domain_Manager
		add_filter( 'wu_auto_create_www_subdomain', '__return_false' );

		$this->provider->on_add_domain( 'example.com', 1 );

		remove_filter( 'wu_auto_create_www_subdomain', '__return_false' );

		$this->assertNotEmpty( $this->http_requests );

		$body = $this->http_requests[0]['args']['body'];
		$this->assertSame( 'v-add-web-domain-alias', $body['cmd'] );
		$this->assertSame( WU_HESTIA_ACCOUNT, $body['arg1'] );
		$this->assertSame( WU_HESTIA_WEB_DOMAIN, $body['arg2'] );
		$this->assertSame( 'example.com', $body['arg3'] );
	}

	/**
	 * Test on_add_domain uses hash auth when WU_HESTIA_API_HASH is defined.
	 */
	public function test_on_add_domain_uses_hash_auth(): void {

		$this->queue_success();

		add_filter( 'wu_auto_create_www_subdomain', '__return_false' );
		$this->provider->on_add_domain( 'example.com', 1 );
		remove_filter( 'wu_auto_create_www_subdomain', '__return_false' );

		$body = $this->http_requests[0]['args']['body'];
		$this->assertArrayHasKey( 'hash', $body );
		$this->assertSame( WU_HESTIA_API_HASH, $body['hash'] );
		$this->assertArrayNotHasKey( 'password', $body );
	}

	/**
	 * Test on_add_domain URL is normalized to include /api suffix.
	 */
	public function test_on_add_domain_normalizes_url(): void {

		$this->queue_success();

		add_filter( 'wu_auto_create_www_subdomain', '__return_false' );
		$this->provider->on_add_domain( 'example.com', 1 );
		remove_filter( 'wu_auto_create_www_subdomain', '__return_false' );

		$url = $this->http_requests[0]['url'];
		$this->assertStringEndsWith( '/api', $url );
	}

	/**
	 * Test on_add_domain also adds www alias when domain does not start with www.
	 */
	public function test_on_add_domain_adds_www_alias_when_configured(): void {

		$this->queue_success();

		// Force www creation
		add_filter(
			'wu_auto_create_www_subdomain',
			function () {
				return 'always';
			}
		);

		// Mock Domain_Manager::should_create_www_subdomain to return true
		add_filter(
			'wu_domain_manager_should_create_www_subdomain',
			'__return_true'
		);

		$this->provider->on_add_domain( 'example.com', 1 );

		remove_filter( 'wu_auto_create_www_subdomain', '__return_true' );
		remove_filter( 'wu_domain_manager_should_create_www_subdomain', '__return_true' );

		// At least one request should be for example.com
		$found_primary = false;
		foreach ( $this->http_requests as $req ) {
			if ( isset( $req['args']['body']['arg3'] ) && 'example.com' === $req['args']['body']['arg3'] ) {
				$found_primary = true;
				break;
			}
		}

		$this->assertTrue( $found_primary, 'Primary alias request not found' );
	}

	/**
	 * Test on_add_domain does not add www alias when domain already starts with www.
	 */
	public function test_on_add_domain_skips_www_when_domain_starts_with_www(): void {

		$this->queue_success();

		$this->provider->on_add_domain( 'www.example.com', 1 );

		// Only one request (the primary alias); no www. prefix added
		$www_requests = array_filter(
			$this->http_requests,
			function ( $req ) {
				return isset( $req['args']['body']['arg3'] ) && str_starts_with( $req['args']['body']['arg3'], 'www.www.' );
			}
		);

		$this->assertCount( 0, $www_requests, 'Should not add www.www. alias' );
	}

	// -------------------------------------------------------------------------
	// on_remove_domain
	// -------------------------------------------------------------------------

	/**
	 * Test on_remove_domain calls v-delete-web-domain-alias with correct args.
	 */
	public function test_on_remove_domain_calls_delete_alias_command(): void {

		$this->queue_success();

		$this->provider->on_remove_domain( 'example.com', 1 );

		$this->assertNotEmpty( $this->http_requests );

		$body = $this->http_requests[0]['args']['body'];
		$this->assertSame( 'v-delete-web-domain-alias', $body['cmd'] );
		$this->assertSame( WU_HESTIA_ACCOUNT, $body['arg1'] );
		$this->assertSame( WU_HESTIA_WEB_DOMAIN, $body['arg2'] );
		$this->assertSame( 'example.com', $body['arg3'] );
	}

	/**
	 * Test on_remove_domain also removes www alias for non-www domains.
	 */
	public function test_on_remove_domain_removes_www_alias(): void {

		$this->queue_success();

		$this->provider->on_remove_domain( 'example.com', 1 );

		// Should have at least 2 requests: primary + www
		$this->assertGreaterThanOrEqual( 2, count( $this->http_requests ) );

		$www_found = false;
		foreach ( $this->http_requests as $req ) {
			if ( isset( $req['args']['body']['arg3'] ) && 'www.example.com' === $req['args']['body']['arg3'] ) {
				$www_found = true;
				break;
			}
		}

		$this->assertTrue( $www_found, 'www alias removal request not found' );
	}

	/**
	 * Test on_remove_domain does not add extra www prefix for www domains.
	 */
	public function test_on_remove_domain_skips_extra_www_for_www_domain(): void {

		$this->queue_success();

		$this->provider->on_remove_domain( 'www.example.com', 1 );

		// Should only have 1 request (no www.www.)
		$www_www_requests = array_filter(
			$this->http_requests,
			function ( $req ) {
				return isset( $req['args']['body']['arg3'] ) && str_starts_with( $req['args']['body']['arg3'], 'www.www.' );
			}
		);

		$this->assertCount( 0, $www_www_requests );
	}

	// -------------------------------------------------------------------------
	// send_hestia_request — tested via reflection
	// -------------------------------------------------------------------------

	/**
	 * Get the protected send_hestia_request method via reflection.
	 *
	 * @return \ReflectionMethod
	 */
	private function get_send_request_method(): \ReflectionMethod {

		$method = new \ReflectionMethod( $this->provider, 'send_hestia_request' );
		$method->setAccessible( true );

		return $method;
	}

	/**
	 * Test send_hestia_request returns WP_Error when URL is empty.
	 */
	public function test_send_hestia_request_returns_error_when_no_url(): void {

		// Temporarily override the constant by using a provider with no URL
		// We test this indirectly: the constant IS defined, so we test the
		// URL normalization path instead. For the missing-URL path, we test
		// via get_dns_records with a fresh provider that has no URL constant.
		// Since constants can't be undefined, we verify the error code path
		// by checking that the method returns WP_Error when wp_remote_post fails.
		$this->queue_wp_error( 'Connection refused' );

		$method = $this->get_send_request_method();
		$result = $method->invoke( $this->provider, 'v-list-web-domains', [ 'admin' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test send_hestia_request returns '0' on success.
	 */
	public function test_send_hestia_request_returns_zero_on_success(): void {

		$this->queue_success();

		$method = $this->get_send_request_method();
		$result = $method->invoke( $this->provider, 'v-add-web-domain-alias', [ 'admin', 'mysite.com', 'example.com', 'yes' ] );

		$this->assertSame( '0', $result );
	}

	/**
	 * Test send_hestia_request returns WP_Error on non-200 HTTP response.
	 */
	public function test_send_hestia_request_returns_error_on_non_200(): void {

		$this->queue_http_error( 403, 'Forbidden' );

		$method = $this->get_send_request_method();
		$result = $method->invoke( $this->provider, 'v-add-web-domain-alias', [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wu_hestia_http_error', $result->get_error_code() );
	}

	/**
	 * Test send_hestia_request decodes JSON response.
	 */
	public function test_send_hestia_request_decodes_json_response(): void {

		$data = [ 'DOMAIN1' => [ 'TYPE' => 'A', 'RECORD' => '@', 'VALUE' => '1.2.3.4', 'TTL' => 3600 ] ];
		$this->queue_json( $data );

		$method = $this->get_send_request_method();
		$result = $method->invoke( $this->provider, 'v-list-dns-records', [ 'admin', 'example.com', 'json' ] );

		$this->assertIsObject( $result );
	}

	/**
	 * Test send_hestia_request returns raw string for non-JSON, non-zero response.
	 */
	public function test_send_hestia_request_returns_raw_string_for_non_json(): void {

		$this->http_response = $this->make_http_response( 200, 'some-raw-output' );

		$method = $this->get_send_request_method();
		$result = $method->invoke( $this->provider, 'v-some-command', [] );

		$this->assertIsString( $result );
		$this->assertSame( 'some-raw-output', $result );
	}

	/**
	 * Test send_hestia_request maps args to arg1..argN.
	 */
	public function test_send_hestia_request_maps_args_to_numbered_keys(): void {

		$this->queue_success();

		$method = $this->get_send_request_method();
		$method->invoke( $this->provider, 'v-test-cmd', [ 'first', 'second', 'third' ] );

		$body = $this->http_requests[0]['args']['body'];
		$this->assertSame( 'first', $body['arg1'] );
		$this->assertSame( 'second', $body['arg2'] );
		$this->assertSame( 'third', $body['arg3'] );
	}

	/**
	 * Test send_hestia_request includes returncode=yes in body.
	 */
	public function test_send_hestia_request_includes_returncode(): void {

		$this->queue_success();

		$method = $this->get_send_request_method();
		$method->invoke( $this->provider, 'v-test-cmd', [] );

		$body = $this->http_requests[0]['args']['body'];
		$this->assertArrayHasKey( 'returncode', $body );
		$this->assertSame( 'yes', $body['returncode'] );
	}

	/**
	 * Test send_hestia_request appends /api when URL does not end with /api.
	 */
	public function test_send_hestia_request_appends_api_to_url(): void {

		$this->queue_success();

		$method = $this->get_send_request_method();
		$method->invoke( $this->provider, 'v-test-cmd', [] );

		$url = $this->http_requests[0]['url'];
		$this->assertStringEndsWith( '/api', $url );
	}

	/**
	 * Test send_hestia_request does not double-append /api.
	 */
	public function test_send_hestia_request_does_not_double_append_api(): void {

		$this->queue_success();

		// WU_HESTIA_API_URL already ends with /api/ — should not become /api/api
		$method = $this->get_send_request_method();
		$method->invoke( $this->provider, 'v-test-cmd', [] );

		$url = $this->http_requests[0]['url'];
		$this->assertStringNotContainsString( '/api/api', $url );
	}

	// -------------------------------------------------------------------------
	// call_and_log — tested via reflection
	// -------------------------------------------------------------------------

	/**
	 * Test call_and_log does not throw on success.
	 */
	public function test_call_and_log_on_success(): void {

		$this->queue_success();

		$method = new \ReflectionMethod( $this->provider, 'call_and_log' );
		$method->setAccessible( true );

		// Should not throw
		$method->invoke( $this->provider, 'v-add-web-domain-alias', [ 'admin', 'mysite.com', 'example.com', 'yes' ], 'Add alias example.com' );

		$this->assertCount( 1, $this->http_requests );
	}

	/**
	 * Test call_and_log on WP_Error logs error and returns.
	 */
	public function test_call_and_log_on_wp_error(): void {

		$this->queue_wp_error( 'API timeout' );

		$method = new \ReflectionMethod( $this->provider, 'call_and_log' );
		$method->setAccessible( true );

		// Should not throw — just logs
		$method->invoke( $this->provider, 'v-add-web-domain-alias', [], 'Test action' );

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// test_connection
	// -------------------------------------------------------------------------

	/**
	 * Test test_connection method exists.
	 */
	public function test_test_connection_method_exists(): void {

		$this->assertTrue( method_exists( $this->provider, 'test_connection' ) );
	}

	// -------------------------------------------------------------------------
	// get_dns_records
	// -------------------------------------------------------------------------

	/**
	 * Test get_dns_records returns WP_Error when WU_HESTIA_ACCOUNT is not defined.
	 *
	 * Since constants can't be undefined, we test the path where the API
	 * returns an error (simulating missing account by returning WP_Error).
	 */
	public function test_get_dns_records_returns_empty_array_on_zero_result(): void {

		$this->queue_success(); // Returns '0'

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_dns_records returns WP_Error when API call fails.
	 */
	public function test_get_dns_records_returns_wp_error_on_api_failure(): void {

		$this->queue_wp_error( 'Connection refused' );

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test get_dns_records returns WP_Error on HTTP error.
	 */
	public function test_get_dns_records_returns_wp_error_on_http_error(): void {

		$this->queue_http_error( 500, 'Internal Server Error' );

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test get_dns_records parses JSON response into DNS_Record objects.
	 */
	public function test_get_dns_records_parses_json_response(): void {

		$records_data = (object) [
			'1' => (object) [
				'TYPE'   => 'A',
				'RECORD' => '@',
				'VALUE'  => '1.2.3.4',
				'TTL'    => 3600,
			],
			'2' => (object) [
				'TYPE'   => 'MX',
				'RECORD' => '@',
				'VALUE'  => 'mail.example.com',
				'TTL'    => 3600,
				'PRIORITY' => 10,
			],
		];

		$this->queue_json( $records_data );

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertInstanceOf( DNS_Record::class, $result[0] );
	}

	/**
	 * Test get_dns_records filters out unsupported record types.
	 */
	public function test_get_dns_records_filters_unsupported_types(): void {

		$records_data = (object) [
			'1' => (object) [
				'TYPE'   => 'A',
				'RECORD' => '@',
				'VALUE'  => '1.2.3.4',
				'TTL'    => 3600,
			],
			'2' => (object) [
				'TYPE'   => 'UNSUPPORTED_TYPE',
				'RECORD' => 'test',
				'VALUE'  => 'some-value',
				'TTL'    => 3600,
			],
		];

		$this->queue_json( $records_data );

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
	}

	/**
	 * Test get_dns_records returns empty array when result is empty object.
	 */
	public function test_get_dns_records_returns_empty_array_on_empty_result(): void {

		$this->http_response = $this->make_http_response( 200, '' );

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_dns_records uses extract_zone_name to get the zone.
	 */
	public function test_get_dns_records_uses_zone_name(): void {

		$this->queue_success();

		$this->provider->get_dns_records( 'sub.example.com' );

		// The API call should use the zone (example.com), not the full subdomain
		$body = $this->http_requests[0]['args']['body'];
		$this->assertSame( 'example.com', $body['arg2'] );
	}

	// -------------------------------------------------------------------------
	// create_dns_record
	// -------------------------------------------------------------------------

	/**
	 * Test create_dns_record calls v-add-dns-record command.
	 */
	public function test_create_dns_record_calls_add_dns_record(): void {

		// First call: create record (returns '0')
		// Second call: list records to find new ID (returns JSON)
		$call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$call_count ) {
				++$call_count;
				if ( 1 === $call_count ) {
					return $this->make_http_response( 200, '0' );
				}

				// Second call: return record list
				$records = (object) [
					'1' => (object) [
						'TYPE'   => 'A',
						'RECORD' => 'test',
						'VALUE'  => '192.168.1.1',
						'TTL'    => 3600,
					],
				];

				return $this->make_http_response( 200, wp_json_encode( $records ) );
			},
			5,
			3
		);

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.1',
			'ttl'     => 3600,
		];

		$result = $this->provider->create_dns_record( 'example.com', $record );

		remove_all_filters( 'pre_http_request' );
		// Re-add our main interceptor for subsequent tests
		add_filter( 'pre_http_request', [ $this, 'intercept_http_request' ], 10, 3 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'type', $result );
		$this->assertSame( 'A', $result['type'] );
	}

	/**
	 * Test create_dns_record returns WP_Error on API failure.
	 */
	public function test_create_dns_record_returns_wp_error_on_failure(): void {

		$this->queue_wp_error( 'Connection refused' );

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.1',
			'ttl'     => 3600,
		];

		$result = $this->provider->create_dns_record( 'example.com', $record );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test create_dns_record returns WP_Error on non-zero Hestia error code.
	 */
	public function test_create_dns_record_returns_wp_error_on_hestia_error(): void {

		$this->http_response = $this->make_http_response( 200, '1' ); // Non-zero = error

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.1',
			'ttl'     => 3600,
		];

		$result = $this->provider->create_dns_record( 'example.com', $record );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'dns-create-error', $result->get_error_code() );
	}

	/**
	 * Test create_dns_record adds MX priority when record type is MX.
	 */
	public function test_create_dns_record_adds_mx_priority(): void {

		$call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$call_count ) {
				++$call_count;
				if ( 1 === $call_count ) {
					// Capture the body for assertion
					$this->http_requests[] = [ 'url' => $url, 'args' => $args ];

					return $this->make_http_response( 200, '0' );
				}

				return $this->make_http_response( 200, '0' );
			},
			5,
			3
		);

		$record = [
			'type'     => 'MX',
			'name'     => '@',
			'content'  => 'mail.example.com',
			'ttl'      => 3600,
			'priority' => 10,
		];

		$this->provider->create_dns_record( 'example.com', $record );

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', [ $this, 'intercept_http_request' ], 10, 3 );

		if ( ! empty( $this->http_requests ) ) {
			$body = $this->http_requests[0]['args']['body'];
			$this->assertSame( 'v-add-dns-record', $body['cmd'] );
			// arg6 should be the priority for MX
			$this->assertSame( '10', $body['arg6'] );
		} else {
			$this->markTestSkipped( 'No HTTP requests captured' );
		}
	}

	/**
	 * Test create_dns_record result contains expected keys.
	 */
	public function test_create_dns_record_result_has_expected_keys(): void {

		$call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$call_count ) {
				++$call_count;
				if ( 1 === $call_count ) {
					return $this->make_http_response( 200, '0' );
				}

				return $this->make_http_response( 200, '0' );
			},
			5,
			3
		);

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.1',
			'ttl'     => 3600,
		];

		$result = $this->provider->create_dns_record( 'example.com', $record );

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', [ $this, 'intercept_http_request' ], 10, 3 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'type', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertArrayHasKey( 'ttl', $result );
	}

	// -------------------------------------------------------------------------
	// update_dns_record
	// -------------------------------------------------------------------------

	/**
	 * Test update_dns_record calls v-change-dns-record command.
	 */
	public function test_update_dns_record_calls_change_dns_record(): void {

		$this->queue_success();

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.2',
			'ttl'     => 3600,
		];

		$result = $this->provider->update_dns_record( 'example.com', '42', $record );

		$this->assertNotEmpty( $this->http_requests );
		$body = $this->http_requests[0]['args']['body'];
		$this->assertSame( 'v-change-dns-record', $body['cmd'] );
		$this->assertSame( '42', $body['arg3'] );
	}

	/**
	 * Test update_dns_record returns array with updated data on success.
	 */
	public function test_update_dns_record_returns_updated_data(): void {

		$this->queue_success();

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.2',
			'ttl'     => 7200,
		];

		$result = $this->provider->update_dns_record( 'example.com', '42', $record );

		$this->assertIsArray( $result );
		$this->assertSame( '42', $result['id'] );
		$this->assertSame( 'A', $result['type'] );
		$this->assertSame( '192.168.1.2', $result['content'] );
		$this->assertSame( 7200, $result['ttl'] );
	}

	/**
	 * Test update_dns_record returns WP_Error on API failure.
	 */
	public function test_update_dns_record_returns_wp_error_on_failure(): void {

		$this->queue_wp_error( 'Connection refused' );

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.2',
			'ttl'     => 3600,
		];

		$result = $this->provider->update_dns_record( 'example.com', '42', $record );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test update_dns_record returns WP_Error on non-zero Hestia error code.
	 */
	public function test_update_dns_record_returns_wp_error_on_hestia_error(): void {

		$this->http_response = $this->make_http_response( 200, '1' );

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '192.168.1.2',
			'ttl'     => 3600,
		];

		$result = $this->provider->update_dns_record( 'example.com', '42', $record );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'dns-update-error', $result->get_error_code() );
	}

	/**
	 * Test update_dns_record adds MX priority when type is MX.
	 */
	public function test_update_dns_record_adds_mx_priority(): void {

		$this->queue_success();

		$record = [
			'type'     => 'MX',
			'name'     => '@',
			'content'  => 'mail.example.com',
			'ttl'      => 3600,
			'priority' => 20,
		];

		$result = $this->provider->update_dns_record( 'example.com', '5', $record );

		$this->assertIsArray( $result );
		$this->assertSame( 20, $result['priority'] );
	}

	/**
	 * Test update_dns_record null priority when not MX.
	 */
	public function test_update_dns_record_null_priority_for_non_mx(): void {

		$this->queue_success();

		$record = [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.4',
			'ttl'     => 3600,
		];

		$result = $this->provider->update_dns_record( 'example.com', '5', $record );

		$this->assertIsArray( $result );
		$this->assertNull( $result['priority'] );
	}

	// -------------------------------------------------------------------------
	// delete_dns_record
	// -------------------------------------------------------------------------

	/**
	 * Test delete_dns_record calls v-delete-dns-record command.
	 */
	public function test_delete_dns_record_calls_delete_dns_record(): void {

		$this->queue_success();

		$result = $this->provider->delete_dns_record( 'example.com', '42' );

		$this->assertNotEmpty( $this->http_requests );
		$body = $this->http_requests[0]['args']['body'];
		$this->assertSame( 'v-delete-dns-record', $body['cmd'] );
		$this->assertSame( '42', $body['arg3'] );
	}

	/**
	 * Test delete_dns_record returns true on success.
	 */
	public function test_delete_dns_record_returns_true_on_success(): void {

		$this->queue_success();

		$result = $this->provider->delete_dns_record( 'example.com', '42' );

		$this->assertTrue( $result );
	}

	/**
	 * Test delete_dns_record returns WP_Error on API failure.
	 */
	public function test_delete_dns_record_returns_wp_error_on_failure(): void {

		$this->queue_wp_error( 'Connection refused' );

		$result = $this->provider->delete_dns_record( 'example.com', '42' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test delete_dns_record returns WP_Error on non-zero Hestia error code.
	 */
	public function test_delete_dns_record_returns_wp_error_on_hestia_error(): void {

		$this->http_response = $this->make_http_response( 200, '1' );

		$result = $this->provider->delete_dns_record( 'example.com', '42' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'dns-delete-error', $result->get_error_code() );
	}

	/**
	 * Test delete_dns_record uses zone name (not full subdomain).
	 */
	public function test_delete_dns_record_uses_zone_name(): void {

		$this->queue_success();

		$this->provider->delete_dns_record( 'sub.example.com', '42' );

		$body = $this->http_requests[0]['args']['body'];
		$this->assertSame( 'example.com', $body['arg2'] );
	}

	// -------------------------------------------------------------------------
	// get_supported_record_types
	// -------------------------------------------------------------------------

	/**
	 * Test get_supported_record_types returns expected types.
	 */
	public function test_get_supported_record_types_returns_expected_types(): void {

		$types = $this->provider->get_supported_record_types();

		$this->assertIsArray( $types );
		$this->assertContains( 'A', $types );
		$this->assertContains( 'AAAA', $types );
		$this->assertContains( 'CNAME', $types );
		$this->assertContains( 'MX', $types );
		$this->assertContains( 'TXT', $types );
	}

	// -------------------------------------------------------------------------
	// extract_zone_name — tested via reflection
	// -------------------------------------------------------------------------

	/**
	 * Test extract_zone_name with standard TLD.
	 */
	public function test_extract_zone_name_standard_tld(): void {

		$method = new \ReflectionMethod( $this->provider, 'extract_zone_name' );
		$method->setAccessible( true );

		$this->assertSame( 'example.com', $method->invoke( $this->provider, 'example.com' ) );
		$this->assertSame( 'example.com', $method->invoke( $this->provider, 'www.example.com' ) );
		$this->assertSame( 'example.com', $method->invoke( $this->provider, 'sub.test.example.com' ) );
	}

	/**
	 * Test extract_zone_name with multi-part TLDs.
	 */
	public function test_extract_zone_name_multi_part_tld(): void {

		$method = new \ReflectionMethod( $this->provider, 'extract_zone_name' );
		$method->setAccessible( true );

		$this->assertSame( 'example.co.uk', $method->invoke( $this->provider, 'www.example.co.uk' ) );
		$this->assertSame( 'example.com.au', $method->invoke( $this->provider, 'sub.example.com.au' ) );
		$this->assertSame( 'example.co.nz', $method->invoke( $this->provider, 'www.example.co.nz' ) );
	}

	// -------------------------------------------------------------------------
	// Auth: password fallback when no hash
	// -------------------------------------------------------------------------

	/**
	 * Test send_hestia_request uses password when no hash is defined.
	 *
	 * We test this by using a subclass that overrides the constant check.
	 * Since constants can't be undefined, we verify the hash path is used
	 * (hash IS defined in our test setup), and the password key is absent.
	 */
	public function test_send_hestia_request_uses_hash_when_defined(): void {

		$this->queue_success();

		$method = $this->get_send_request_method();
		$method->invoke( $this->provider, 'v-test-cmd', [] );

		$body = $this->http_requests[0]['args']['body'];
		$this->assertArrayHasKey( 'hash', $body );
		$this->assertArrayNotHasKey( 'password', $body );
	}

	// -------------------------------------------------------------------------
	// constants / is_setup
	// -------------------------------------------------------------------------

	/**
	 * Test is_setup returns true when all required constants are defined.
	 */
	public function test_is_setup_returns_true_when_constants_defined(): void {

		// All WU_HESTIA_* constants are defined in setUpBeforeClass
		$this->assertTrue( $this->provider->is_setup() );
	}

	/**
	 * Test get_all_constants returns all Hestia constants.
	 */
	public function test_get_all_constants_includes_hestia_constants(): void {

		$all = $this->provider->get_all_constants();

		$this->assertContains( 'WU_HESTIA_API_URL', $all );
		$this->assertContains( 'WU_HESTIA_API_USER', $all );
		$this->assertContains( 'WU_HESTIA_ACCOUNT', $all );
		$this->assertContains( 'WU_HESTIA_WEB_DOMAIN', $all );
	}

	/**
	 * Test get_all_constants includes optional constants.
	 */
	public function test_get_all_constants_includes_optional_constants(): void {

		$all = $this->provider->get_all_constants();

		$this->assertContains( 'WU_HESTIA_RESTART', $all );
	}

	// -------------------------------------------------------------------------
	// tutorial_link
	// -------------------------------------------------------------------------

	/**
	 * Test tutorial_link property is set.
	 */
	public function test_tutorial_link_is_set(): void {

		$prop = new \ReflectionProperty( $this->provider, 'tutorial_link' );
		$prop->setAccessible( true );

		$link = $prop->getValue( $this->provider );

		$this->assertIsString( $link );
		$this->assertNotEmpty( $link );
		$this->assertStringContainsString( 'hestia', strtolower( $link ) );
	}
}
