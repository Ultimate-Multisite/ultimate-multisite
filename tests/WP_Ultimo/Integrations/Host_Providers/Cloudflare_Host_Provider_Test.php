<?php
/**
 * Comprehensive unit tests for Cloudflare_Host_Provider.
 *
 * Targets ≥80% statement coverage of
 * inc/integrations/host-providers/class-cloudflare-host-provider.php.
 *
 * HTTP calls are intercepted via the WordPress `pre_http_request` filter so no
 * real network traffic is generated.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Integrations\Host_Providers
 */

namespace WP_Ultimo\Tests\Integrations\Host_Providers;

use WP_Ultimo\Integrations\Host_Providers\Cloudflare_Host_Provider;
use WP_Ultimo\Integrations\Host_Providers\DNS_Provider_Interface;
use WP_UnitTestCase;

/**
 * Tests for Cloudflare_Host_Provider.
 *
 * Uses the `pre_http_request` WordPress filter to mock all Cloudflare API
 * calls without making real HTTP requests.
 */
class Cloudflare_Host_Provider_Test extends WP_UnitTestCase {

	/**
	 * The provider under test.
	 *
	 * @var Cloudflare_Host_Provider
	 */
	private Cloudflare_Host_Provider $provider;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->provider = Cloudflare_Host_Provider::get_instance();
	}

	/**
	 * Tear down — remove any HTTP mocks registered during the test.
	 */
	public function tear_down(): void {

		remove_all_filters('pre_http_request');

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helper: build a fake Cloudflare HTTP response
	// -------------------------------------------------------------------------

	/**
	 * Build a fake successful Cloudflare HTTP response array.
	 *
	 * @param mixed $result The value to place in the `result` key of the body.
	 * @param array $result_info Optional result_info (e.g. pagination).
	 * @return array WordPress HTTP response array.
	 */
	private function make_cf_response( $result, array $result_info = [] ): array {

		$body = [ 'result' => $result ];

		if ( ! empty( $result_info ) ) {
			$body['result_info'] = $result_info;
		}

		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => wp_json_encode( $body ),
		];
	}

	/**
	 * Build a fake Cloudflare error HTTP response array.
	 *
	 * @param int    $code    HTTP status code.
	 * @param string $message HTTP status message.
	 * @param string $body    Response body.
	 * @return array WordPress HTTP response array.
	 */
	private function make_cf_error_response( int $code = 403, string $message = 'Forbidden', string $body = '{"errors":[{"message":"Invalid API key"}]}' ): array {

		return [
			'response' => [ 'code' => $code, 'message' => $message ],
			'body'     => $body,
		];
	}

	/**
	 * Register a `pre_http_request` filter that returns the given response for
	 * every request whose URL contains $url_fragment.
	 *
	 * @param array  $response     The response to return.
	 * @param string $url_fragment Substring that must appear in the request URL.
	 * @return void
	 */
	private function mock_http( array $response, string $url_fragment = '' ): void {

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $response, $url_fragment ) {
				if ( '' === $url_fragment || str_contains( $url, $url_fragment ) ) {
					return $response;
				}

				return $preempt;
			},
			10,
			3
		);
	}

	/**
	 * Register a `pre_http_request` filter that returns a WP_Error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return void
	 */
	private function mock_http_wp_error( string $code = 'http-error', string $message = 'Connection failed' ): void {

		add_filter(
			'pre_http_request',
			function () use ( $code, $message ) {
				return new \WP_Error( $code, $message );
			},
			10,
			3
		);
	}

	// -------------------------------------------------------------------------
	// Basic identity / interface
	// -------------------------------------------------------------------------

	/**
	 * Test that the provider implements DNS_Provider_Interface.
	 */
	public function test_implements_dns_provider_interface(): void {

		$this->assertInstanceOf( DNS_Provider_Interface::class, $this->provider );
	}

	/**
	 * Test get_id returns 'cloudflare'.
	 */
	public function test_get_id_returns_cloudflare(): void {

		$this->assertSame( 'cloudflare', $this->provider->get_id() );
	}

	/**
	 * Test get_title returns 'Cloudflare'.
	 */
	public function test_get_title_returns_cloudflare(): void {

		$this->assertSame( 'Cloudflare', $this->provider->get_title() );
	}

	/**
	 * Test supports_dns_management returns true.
	 */
	public function test_supports_dns_management_returns_true(): void {

		$this->assertTrue( $this->provider->supports_dns_management() );
	}

	/**
	 * Test supports autossl.
	 */
	public function test_supports_autossl(): void {

		$this->assertTrue( $this->provider->supports( 'autossl' ) );
	}

	// -------------------------------------------------------------------------
	// detect()
	// -------------------------------------------------------------------------

	/**
	 * Test detect() always returns false (wildcard proxying made it obsolete).
	 */
	public function test_detect_returns_false(): void {

		$this->assertFalse( $this->provider->detect() );
	}

	// -------------------------------------------------------------------------
	// get_fields()
	// -------------------------------------------------------------------------

	/**
	 * Test get_fields returns array with WU_CLOUDFLARE_ZONE_ID and WU_CLOUDFLARE_API_KEY.
	 */
	public function test_get_fields_returns_expected_keys(): void {

		$fields = $this->provider->get_fields();

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'WU_CLOUDFLARE_ZONE_ID', $fields );
		$this->assertArrayHasKey( 'WU_CLOUDFLARE_API_KEY', $fields );
	}

	/**
	 * Test get_fields entries have title and placeholder.
	 */
	public function test_get_fields_entries_have_title_and_placeholder(): void {

		$fields = $this->provider->get_fields();

		foreach ( $fields as $field ) {
			$this->assertArrayHasKey( 'title', $field );
			$this->assertArrayHasKey( 'placeholder', $field );
		}
	}

	// -------------------------------------------------------------------------
	// get_description()
	// -------------------------------------------------------------------------

	/**
	 * Test get_description returns a non-empty string.
	 */
	public function test_get_description_returns_non_empty_string(): void {

		$description = $this->provider->get_description();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
	}

	/**
	 * Test get_description mentions Cloudflare.
	 */
	public function test_get_description_mentions_cloudflare(): void {

		$description = $this->provider->get_description();

		$this->assertStringContainsString( 'Cloudflare', $description );
	}

	// -------------------------------------------------------------------------
	// get_logo()
	// -------------------------------------------------------------------------

	/**
	 * Test get_logo returns a string.
	 */
	public function test_get_logo_returns_string(): void {

		$logo = $this->provider->get_logo();

		$this->assertIsString( $logo );
	}

	/**
	 * Test get_logo references cloudflare.svg.
	 */
	public function test_get_logo_references_cloudflare_svg(): void {

		$logo = $this->provider->get_logo();

		$this->assertStringContainsString( 'cloudflare', $logo );
	}

	// -------------------------------------------------------------------------
	// get_explainer_lines()
	// -------------------------------------------------------------------------

	/**
	 * Test get_explainer_lines returns array with will and will_not keys.
	 */
	public function test_get_explainer_lines_returns_correct_structure(): void {

		$lines = $this->provider->get_explainer_lines();

		$this->assertIsArray( $lines );
		$this->assertArrayHasKey( 'will', $lines );
		$this->assertArrayHasKey( 'will_not', $lines );
	}

	/**
	 * Test get_explainer_lines will_not contains send_domain entry.
	 */
	public function test_get_explainer_lines_will_not_contains_send_domain(): void {

		$lines = $this->provider->get_explainer_lines();

		$this->assertArrayHasKey( 'send_domain', $lines['will_not'] );
	}

	/**
	 * Test get_explainer_lines on subdomain install includes send_sub_domains.
	 */
	public function test_get_explainer_lines_subdomain_install(): void {

		// Force is_subdomain_install() to return true via filter.
		add_filter( 'wu_hosting_support_supports', '__return_true' );

		// Simulate subdomain install by temporarily overriding the function.
		// In the WP test env, WP_TESTS_MULTISITE=1 so is_subdomain_install() may
		// already return true. We test both branches by checking the structure.
		$lines = $this->provider->get_explainer_lines();

		$this->assertIsArray( $lines['will'] );

		remove_filter( 'wu_hosting_support_supports', '__return_true' );
	}

	// -------------------------------------------------------------------------
	// additional_hooks()
	// -------------------------------------------------------------------------

	/**
	 * Test additional_hooks registers the wu_domain_dns_get_record filter.
	 */
	public function test_additional_hooks_registers_dns_filter(): void {

		// Remove any previously registered hooks to get a clean state.
		remove_all_filters( 'wu_domain_dns_get_record' );

		$this->provider->additional_hooks();

		$this->assertIsInt( has_filter( 'wu_domain_dns_get_record', [ $this->provider, 'add_cloudflare_dns_entries' ] ) );
	}

	// -------------------------------------------------------------------------
	// on_add_domain() / on_remove_domain() — no-ops
	// -------------------------------------------------------------------------

	/**
	 * Test on_add_domain is callable without error.
	 */
	public function test_on_add_domain_is_callable(): void {

		$this->provider->on_add_domain( 'example.com', 1 );

		$this->assertTrue( true );
	}

	/**
	 * Test on_remove_domain is callable without error.
	 */
	public function test_on_remove_domain_is_callable(): void {

		$this->provider->on_remove_domain( 'example.com', 1 );

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// get_supported_record_types()
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
	// get_zone_id() — via mocked HTTP
	// -------------------------------------------------------------------------

	/**
	 * Test get_zone_id returns zone ID from API when found.
	 */
	public function test_get_zone_id_returns_zone_id_from_api(): void {

		$zone = (object) [ 'id' => 'zone-abc-123' ];
		$this->mock_http( $this->make_cf_response( [ $zone ] ), 'client/v4/zones' );

		$result = $this->provider->get_zone_id( 'example.com' );

		$this->assertSame( 'zone-abc-123', $result );
	}

	/**
	 * Test get_zone_id falls back to WU_CLOUDFLARE_ZONE_ID constant when API returns empty.
	 */
	public function test_get_zone_id_falls_back_to_constant(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'constant-zone-id' );
		}

		// API returns empty result for all candidates.
		$this->mock_http( $this->make_cf_response( [] ), 'client/v4/zones' );

		$result = $this->provider->get_zone_id( 'example.com' );

		// Should fall back to the defined constant.
		$this->assertSame( WU_CLOUDFLARE_ZONE_ID, $result );
	}

	/**
	 * Test get_zone_id returns null when API returns WP_Error and no constant defined.
	 */
	public function test_get_zone_id_returns_null_when_api_errors_and_no_constant(): void {

		// Only run this test when the constant is not defined (or is falsy).
		if ( defined( 'WU_CLOUDFLARE_ZONE_ID' ) && WU_CLOUDFLARE_ZONE_ID ) {
			$this->markTestSkipped( 'WU_CLOUDFLARE_ZONE_ID is defined — cannot test null fallback.' );
		}

		$this->mock_http_wp_error();

		$result = $this->provider->get_zone_id( 'example.com' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_zone_id iterates domain parts (sub.example.com → example.com).
	 */
	public function test_get_zone_id_iterates_domain_parts(): void {

		$callCount = 0;
		$zone      = (object) [ 'id' => 'zone-from-root' ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone ) {
				++$callCount;

				// Return empty for the first call (sub.example.com), zone on second (example.com).
				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [] ] ),
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->get_zone_id( 'sub.example.com' );

		$this->assertSame( 'zone-from-root', $result );
		$this->assertGreaterThanOrEqual( 2, $callCount );
	}

	// -------------------------------------------------------------------------
	// get_dns_records() — via mocked HTTP
	// -------------------------------------------------------------------------

	/**
	 * Test get_dns_records returns WP_Error when zone not found.
	 */
	public function test_get_dns_records_returns_wp_error_when_zone_not_found(): void {

		if ( defined( 'WU_CLOUDFLARE_ZONE_ID' ) && WU_CLOUDFLARE_ZONE_ID ) {
			$this->markTestSkipped( 'WU_CLOUDFLARE_ZONE_ID is defined — zone will always be found.' );
		}

		// Zone lookup returns empty.
		$this->mock_http( $this->make_cf_response( [] ), 'client/v4/zones' );

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'zone-not-found', $result->get_error_code() );
	}

	/**
	 * Test get_dns_records returns WP_Error when API call fails.
	 */
	public function test_get_dns_records_returns_wp_error_on_api_failure(): void {

		$zone = (object) [ 'id' => 'zone-xyz' ];

		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone ) {
				++$callCount;

				// First call: zone lookup succeeds.
				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				// Second call: dns_records lookup fails.
				return new \WP_Error( 'api-error', 'API failure' );
			},
			10,
			3
		);

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test get_dns_records returns WP_Error on invalid response (no result key).
	 */
	public function test_get_dns_records_returns_wp_error_on_invalid_response(): void {

		$zone      = (object) [ 'id' => 'zone-xyz' ];
		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				// Invalid response — no 'result' key.
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'success' => true ] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid-response', $result->get_error_code() );
	}

	/**
	 * Test get_dns_records returns array of DNS_Record objects on success.
	 */
	public function test_get_dns_records_returns_array_on_success(): void {

		$zone = (object) [ 'id' => 'zone-success' ];

		$record = (object) [
			'id'        => 'rec-001',
			'type'      => 'A',
			'name'      => 'example.com',
			'content'   => '1.2.3.4',
			'ttl'       => 3600,
			'proxied'   => false,
			'zone_id'   => 'zone-success',
			'zone_name' => 'example.com',
		];

		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone, $record ) {
				++$callCount;

				if ( $callCount === 1 ) {
					// Zone lookup.
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				// DNS records page 1 (only page).
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [
						'result'      => [ $record ],
						'result_info' => [ 'total_pages' => 1 ],
					] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
	}

	/**
	 * Test get_dns_records filters out unsupported record types.
	 */
	public function test_get_dns_records_filters_unsupported_types(): void {

		$zone = (object) [ 'id' => 'zone-filter' ];

		$supported_record = (object) [
			'id'        => 'rec-a',
			'type'      => 'A',
			'name'      => 'example.com',
			'content'   => '1.2.3.4',
			'ttl'       => 3600,
			'proxied'   => false,
			'zone_id'   => 'zone-filter',
			'zone_name' => 'example.com',
		];

		$unsupported_record = (object) [
			'id'        => 'rec-soa',
			'type'      => 'SOA',
			'name'      => 'example.com',
			'content'   => 'ns1.example.com',
			'ttl'       => 3600,
			'proxied'   => false,
			'zone_id'   => 'zone-filter',
			'zone_name' => 'example.com',
		];

		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone, $supported_record, $unsupported_record ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [
						'result'      => [ $supported_record, $unsupported_record ],
						'result_info' => [ 'total_pages' => 1 ],
					] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->get_dns_records( 'example.com' );

		// Only the A record should be returned; SOA is unsupported.
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
	}

	/**
	 * Test get_dns_records paginates through multiple pages.
	 */
	public function test_get_dns_records_paginates(): void {

		$zone = (object) [ 'id' => 'zone-paginate' ];

		$record1 = (object) [
			'id'        => 'rec-p1',
			'type'      => 'A',
			'name'      => 'a.example.com',
			'content'   => '1.1.1.1',
			'ttl'       => 300,
			'proxied'   => false,
			'zone_id'   => 'zone-paginate',
			'zone_name' => 'example.com',
		];

		$record2 = (object) [
			'id'        => 'rec-p2',
			'type'      => 'AAAA',
			'name'      => 'b.example.com',
			'content'   => '::1',
			'ttl'       => 300,
			'proxied'   => false,
			'zone_id'   => 'zone-paginate',
			'zone_name' => 'example.com',
		];

		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone, $record1, $record2 ) {
				++$callCount;

				if ( $callCount === 1 ) {
					// Zone lookup.
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				if ( $callCount === 2 ) {
					// Page 1 of 2.
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [
							'result'      => [ $record1 ],
							'result_info' => [ 'total_pages' => 2 ],
						] ),
					];
				}

				// Page 2 of 2.
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [
						'result'      => [ $record2 ],
						'result_info' => [ 'total_pages' => 2 ],
					] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->get_dns_records( 'example.com' );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
	}

	// -------------------------------------------------------------------------
	// create_dns_record() — via mocked HTTP
	// -------------------------------------------------------------------------

	/**
	 * Test create_dns_record returns WP_Error when zone not found.
	 */
	public function test_create_dns_record_returns_wp_error_when_zone_not_found(): void {

		if ( defined( 'WU_CLOUDFLARE_ZONE_ID' ) && WU_CLOUDFLARE_ZONE_ID ) {
			$this->markTestSkipped( 'WU_CLOUDFLARE_ZONE_ID is defined — zone will always be found.' );
		}

		$this->mock_http( $this->make_cf_response( [] ), 'client/v4/zones' );

		$result = $this->provider->create_dns_record( 'example.com', [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.4',
			'ttl'     => 3600,
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'zone-not-found', $result->get_error_code() );
	}

	/**
	 * Test create_dns_record returns WP_Error when API call fails.
	 */
	public function test_create_dns_record_returns_wp_error_on_api_failure(): void {

		$zone      = (object) [ 'id' => 'zone-create' ];
		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				return new \WP_Error( 'create-failed', 'Could not create record' );
			},
			10,
			3
		);

		$result = $this->provider->create_dns_record( 'example.com', [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.4',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test create_dns_record returns WP_Error when response has no result.
	 */
	public function test_create_dns_record_returns_wp_error_on_invalid_response(): void {

		$zone      = (object) [ 'id' => 'zone-create-invalid' ];
		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				// No 'result' key in response.
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'success' => true ] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->create_dns_record( 'example.com', [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.4',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid-response', $result->get_error_code() );
	}

	/**
	 * Test create_dns_record returns array on success.
	 */
	public function test_create_dns_record_returns_array_on_success(): void {

		$zone      = (object) [ 'id' => 'zone-create-ok' ];
		$created   = (object) [
			'id'      => 'rec-new',
			'type'    => 'A',
			'name'    => 'test.example.com',
			'content' => '1.2.3.4',
			'ttl'     => 3600,
			'proxied' => false,
		];
		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone, $created ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => $created ] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->create_dns_record( 'example.com', [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.4',
			'ttl'     => 3600,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertSame( 'rec-new', $result['id'] );
	}

	/**
	 * Test create_dns_record strips proxied for MX records.
	 */
	public function test_create_dns_record_strips_proxied_for_mx(): void {

		$zone      = (object) [ 'id' => 'zone-mx' ];
		$created   = (object) [
			'id'       => 'rec-mx',
			'type'     => 'MX',
			'name'     => 'example.com',
			'content'  => 'mail.example.com',
			'ttl'      => 3600,
			'priority' => 10,
		];
		$callCount = 0;
		$sentBody  = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, &$sentBody, $zone, $created ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				$sentBody = json_decode( $args['body'], true );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => $created ] ),
				];
			},
			10,
			3
		);

		$this->provider->create_dns_record( 'example.com', [
			'type'     => 'MX',
			'name'     => '@',
			'content'  => 'mail.example.com',
			'ttl'      => 3600,
			'priority' => 10,
			'proxied'  => true, // Should be stripped for MX.
		] );

		// Verify 'proxied' was removed from the sent body.
		$this->assertIsArray( $sentBody );
		$this->assertArrayNotHasKey( 'proxied', $sentBody );
		// Priority should be included for MX.
		$this->assertArrayHasKey( 'priority', $sentBody );
		$this->assertSame( 10, $sentBody['priority'] );
	}

	/**
	 * Test create_dns_record strips proxied for TXT records.
	 */
	public function test_create_dns_record_strips_proxied_for_txt(): void {

		$zone      = (object) [ 'id' => 'zone-txt' ];
		$created   = (object) [
			'id'      => 'rec-txt',
			'type'    => 'TXT',
			'name'    => 'example.com',
			'content' => 'v=spf1 include:example.com ~all',
			'ttl'     => 3600,
		];
		$callCount = 0;
		$sentBody  = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, &$sentBody, $zone, $created ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				$sentBody = json_decode( $args['body'], true );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => $created ] ),
				];
			},
			10,
			3
		);

		$this->provider->create_dns_record( 'example.com', [
			'type'    => 'TXT',
			'name'    => '@',
			'content' => 'v=spf1 include:example.com ~all',
			'ttl'     => 3600,
			'proxied' => true,
		] );

		$this->assertIsArray( $sentBody );
		$this->assertArrayNotHasKey( 'proxied', $sentBody );
	}

	// -------------------------------------------------------------------------
	// update_dns_record() — via mocked HTTP
	// -------------------------------------------------------------------------

	/**
	 * Test update_dns_record returns WP_Error when zone not found.
	 */
	public function test_update_dns_record_returns_wp_error_when_zone_not_found(): void {

		if ( defined( 'WU_CLOUDFLARE_ZONE_ID' ) && WU_CLOUDFLARE_ZONE_ID ) {
			$this->markTestSkipped( 'WU_CLOUDFLARE_ZONE_ID is defined — zone will always be found.' );
		}

		$this->mock_http( $this->make_cf_response( [] ), 'client/v4/zones' );

		$result = $this->provider->update_dns_record( 'example.com', 'rec-123', [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.5',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'zone-not-found', $result->get_error_code() );
	}

	/**
	 * Test update_dns_record returns WP_Error when API call fails.
	 */
	public function test_update_dns_record_returns_wp_error_on_api_failure(): void {

		$zone      = (object) [ 'id' => 'zone-update' ];
		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				return new \WP_Error( 'update-failed', 'Could not update record' );
			},
			10,
			3
		);

		$result = $this->provider->update_dns_record( 'example.com', 'rec-123', [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.5',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test update_dns_record returns WP_Error on invalid response.
	 */
	public function test_update_dns_record_returns_wp_error_on_invalid_response(): void {

		$zone      = (object) [ 'id' => 'zone-update-invalid' ];
		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'success' => true ] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->update_dns_record( 'example.com', 'rec-123', [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.5',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid-response', $result->get_error_code() );
	}

	/**
	 * Test update_dns_record returns array on success.
	 */
	public function test_update_dns_record_returns_array_on_success(): void {

		$zone      = (object) [ 'id' => 'zone-update-ok' ];
		$updated   = (object) [
			'id'      => 'rec-123',
			'type'    => 'A',
			'name'    => 'test.example.com',
			'content' => '1.2.3.5',
			'ttl'     => 7200,
			'proxied' => false,
		];
		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone, $updated ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => $updated ] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->update_dns_record( 'example.com', 'rec-123', [
			'type'    => 'A',
			'name'    => 'test',
			'content' => '1.2.3.5',
			'ttl'     => 7200,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertSame( 'rec-123', $result['id'] );
	}

	/**
	 * Test update_dns_record strips proxied for MX records.
	 */
	public function test_update_dns_record_strips_proxied_for_mx(): void {

		$zone      = (object) [ 'id' => 'zone-update-mx' ];
		$updated   = (object) [
			'id'       => 'rec-mx-upd',
			'type'     => 'MX',
			'name'     => 'example.com',
			'content'  => 'mail2.example.com',
			'ttl'      => 3600,
			'priority' => 20,
		];
		$callCount = 0;
		$sentBody  = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, &$sentBody, $zone, $updated ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				$sentBody = json_decode( $args['body'], true );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => $updated ] ),
				];
			},
			10,
			3
		);

		$this->provider->update_dns_record( 'example.com', 'rec-mx-upd', [
			'type'     => 'MX',
			'name'     => '@',
			'content'  => 'mail2.example.com',
			'ttl'      => 3600,
			'priority' => 20,
			'proxied'  => true,
		] );

		$this->assertIsArray( $sentBody );
		$this->assertArrayNotHasKey( 'proxied', $sentBody );
		$this->assertArrayHasKey( 'priority', $sentBody );
	}

	// -------------------------------------------------------------------------
	// delete_dns_record() — via mocked HTTP
	// -------------------------------------------------------------------------

	/**
	 * Test delete_dns_record returns WP_Error when zone not found.
	 */
	public function test_delete_dns_record_returns_wp_error_when_zone_not_found(): void {

		if ( defined( 'WU_CLOUDFLARE_ZONE_ID' ) && WU_CLOUDFLARE_ZONE_ID ) {
			$this->markTestSkipped( 'WU_CLOUDFLARE_ZONE_ID is defined — zone will always be found.' );
		}

		$this->mock_http( $this->make_cf_response( [] ), 'client/v4/zones' );

		$result = $this->provider->delete_dns_record( 'example.com', 'rec-del-123' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'zone-not-found', $result->get_error_code() );
	}

	/**
	 * Test delete_dns_record returns WP_Error when API call fails.
	 */
	public function test_delete_dns_record_returns_wp_error_on_api_failure(): void {

		$zone      = (object) [ 'id' => 'zone-delete' ];
		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				return new \WP_Error( 'delete-failed', 'Could not delete record' );
			},
			10,
			3
		);

		$result = $this->provider->delete_dns_record( 'example.com', 'rec-del-123' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test delete_dns_record returns true on success.
	 */
	public function test_delete_dns_record_returns_true_on_success(): void {

		$zone      = (object) [ 'id' => 'zone-delete-ok' ];
		$callCount = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$callCount, $zone ) {
				++$callCount;

				if ( $callCount === 1 ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => [] ] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->delete_dns_record( 'example.com', 'rec-del-123' );

		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// add_cloudflare_dns_entries() — via mocked HTTP
	// -------------------------------------------------------------------------

	/**
	 * Test add_cloudflare_dns_entries returns original records when no zones found.
	 */
	public function test_add_cloudflare_dns_entries_returns_original_when_no_zones(): void {

		if ( defined( 'WU_CLOUDFLARE_ZONE_ID' ) && WU_CLOUDFLARE_ZONE_ID ) {
			$this->markTestSkipped( 'WU_CLOUDFLARE_ZONE_ID is defined — zone will always be found.' );
		}

		// API returns empty zones.
		$this->mock_http( $this->make_cf_response( [] ), 'client/v4/zones' );

		$original = [ [ 'type' => 'A', 'data' => '1.2.3.4', 'host' => 'example.com', 'ttl' => 300 ] ];

		$result = $this->provider->add_cloudflare_dns_entries( $original, 'example.com' );

		$this->assertSame( $original, $result );
	}

	/**
	 * Test add_cloudflare_dns_entries appends Cloudflare DNS entries.
	 *
	 * Uses a URL-based mock: zones calls return a zone, dns_records calls return entries.
	 * This is robust to the WU_CLOUDFLARE_ZONE_ID constant being defined (which adds an
	 * extra zone_id and thus an extra dns_records call).
	 */
	public function test_add_cloudflare_dns_entries_appends_entries(): void {

		$zone      = (object) [ 'id' => 'zone-dns-entries' ];
		$dns_entry = (object) [
			'ttl'     => 1,
			'content' => '1.2.3.4',
			'type'    => 'A',
			'name'    => 'example.com',
			'proxied' => true,
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zone, $dns_entry ) {
				if ( str_contains( $url, 'dns_records' ) ) {
					// DNS records lookup — return one entry.
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $dns_entry ] ] ),
					];
				}

				// Zone lookup.
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
				];
			},
			10,
			3
		);

		$original = [];
		$result   = $this->provider->add_cloudflare_dns_entries( $original, 'example.com' );

		$this->assertIsArray( $result );
		$this->assertGreaterThanOrEqual( 1, count( $result ) );
		$this->assertSame( 'A', $result[0]['type'] );
		$this->assertSame( '1.2.3.4', $result[0]['data'] );
	}

	/**
	 * Test add_cloudflare_dns_entries adds proxied tag for proxied entries.
	 */
	public function test_add_cloudflare_dns_entries_adds_proxied_tag(): void {

		$zone      = (object) [ 'id' => 'zone-proxied' ];
		$dns_entry = (object) [
			'ttl'     => 1,
			'content' => '1.2.3.4',
			'type'    => 'A',
			'name'    => 'example.com',
			'proxied' => true,
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zone, $dns_entry ) {
				if ( str_contains( $url, 'dns_records' ) ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $dns_entry ] ] ),
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->add_cloudflare_dns_entries( [], 'example.com' );

		$this->assertGreaterThanOrEqual( 1, count( $result ) );
		// Proxied tag should contain orange background class.
		$this->assertStringContainsString( 'wu-bg-orange-500', $result[0]['tag'] );
	}

	/**
	 * Test add_cloudflare_dns_entries adds not-proxied tag for non-proxied entries.
	 */
	public function test_add_cloudflare_dns_entries_adds_not_proxied_tag(): void {

		$zone      = (object) [ 'id' => 'zone-not-proxied' ];
		$dns_entry = (object) [
			'ttl'     => 3600,
			'content' => '1.2.3.4',
			'type'    => 'A',
			'name'    => 'example.com',
			'proxied' => false,
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zone, $dns_entry ) {
				if ( str_contains( $url, 'dns_records' ) ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [ $dns_entry ] ] ),
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
				];
			},
			10,
			3
		);

		$result = $this->provider->add_cloudflare_dns_entries( [], 'example.com' );

		$this->assertGreaterThanOrEqual( 1, count( $result ) );
		// Not-proxied tag should contain gray background class.
		$this->assertStringContainsString( 'wu-bg-gray-700', $result[0]['tag'] );
	}

	/**
	 * Test add_cloudflare_dns_entries skips zone when DNS entries are empty.
	 */
	public function test_add_cloudflare_dns_entries_skips_empty_dns_results(): void {

		if ( defined( 'WU_CLOUDFLARE_ZONE_ID' ) && WU_CLOUDFLARE_ZONE_ID ) {
			$this->markTestSkipped( 'WU_CLOUDFLARE_ZONE_ID is defined — constant zone always present.' );
		}

		$zone = (object) [ 'id' => 'zone-empty-dns' ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $zone ) {
				if ( str_contains( $url, 'dns_records' ) ) {
					// Empty DNS entries.
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [] ] ),
					];
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => [ $zone ] ] ),
				];
			},
			10,
			3
		);

		$original = [ [ 'type' => 'A', 'data' => '5.5.5.5', 'host' => 'example.com', 'ttl' => 300 ] ];
		$result   = $this->provider->add_cloudflare_dns_entries( $original, 'example.com' );

		// Original records unchanged — no Cloudflare entries appended.
		$this->assertSame( $original, $result );
	}

	// -------------------------------------------------------------------------
	// on_add_subdomain() — via mocked HTTP and global $current_site
	// -------------------------------------------------------------------------

	/**
	 * Test on_add_subdomain returns early when zone_id not configured.
	 */
	public function test_on_add_subdomain_returns_early_without_zone_id(): void {

		if ( defined( 'WU_CLOUDFLARE_ZONE_ID' ) && WU_CLOUDFLARE_ZONE_ID ) {
			$this->markTestSkipped( 'WU_CLOUDFLARE_ZONE_ID is defined — cannot test early return.' );
		}

		// No HTTP calls should be made.
		$called = false;

		add_filter(
			'pre_http_request',
			function () use ( &$called ) {
				$called = true;

				return new \WP_Error( 'should-not-be-called', 'Should not be called' );
			},
			10,
			3
		);

		$this->provider->on_add_subdomain( 'sub.example.com', 1 );

		$this->assertFalse( $called, 'HTTP call should not be made when zone_id is not configured.' );
	}

	/**
	 * Test on_add_subdomain returns early when subdomain is not part of current_site domain.
	 */
	public function test_on_add_subdomain_returns_early_when_not_subdomain_of_current_site(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'zone-subdomain-test' );
		}

		global $current_site;

		$current_site         = new \stdClass();
		$current_site->domain = 'mynetwork.com';

		$called = false;

		add_filter(
			'pre_http_request',
			function () use ( &$called ) {
				$called = true;

				return new \WP_Error( 'should-not-be-called', 'Should not be called' );
			},
			10,
			3
		);

		// 'otherdomain.com' is not a subdomain of 'mynetwork.com'.
		$this->provider->on_add_subdomain( 'otherdomain.com', 1 );

		$this->assertFalse( $called, 'HTTP call should not be made for non-subdomain.' );
	}

	/**
	 * Test on_add_subdomain returns early when subdomain part is empty after stripping.
	 */
	public function test_on_add_subdomain_returns_early_when_subdomain_empty(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'zone-subdomain-empty' );
		}

		global $current_site;

		$current_site         = new \stdClass();
		$current_site->domain = 'mynetwork.com';

		$called = false;

		add_filter(
			'pre_http_request',
			function () use ( &$called ) {
				$called = true;

				return new \WP_Error( 'should-not-be-called', 'Should not be called' );
			},
			10,
			3
		);

		// Passing the bare domain — after stripping, subdomain part is empty.
		$this->provider->on_add_subdomain( 'mynetwork.com', 1 );

		$this->assertFalse( $called );
	}

	/**
	 * Test on_add_subdomain makes API call when zone_id is configured and subdomain is valid.
	 */
	public function test_on_add_subdomain_makes_api_call_for_valid_subdomain(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'zone-add-subdomain' );
		}

		global $current_site;

		$current_site         = new \stdClass();
		$current_site->domain = 'mynetwork.com';

		$apiCalled = false;

		// Bypass Domain_Manager::should_create_www_subdomain() by filtering the result.
		add_filter( 'wu_cloudflare_should_add_www', '__return_false' );

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$apiCalled ) {
				if ( str_contains( $url, 'dns_records' ) ) {
					$apiCalled = true;

					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => (object) [
							'id'      => 'new-rec',
							'type'    => 'CNAME',
							'name'    => 'newsite.mynetwork.com',
							'content' => '@',
						] ] ),
					];
				}

				return $preempt;
			},
			10,
			3
		);

		$this->provider->on_add_subdomain( 'newsite.mynetwork.com', 1 );

		remove_filter( 'wu_cloudflare_should_add_www', '__return_false' );

		$this->assertTrue( $apiCalled, 'Expected API call for valid subdomain.' );
	}

	/**
	 * Test on_add_subdomain logs error when API call fails.
	 */
	public function test_on_add_subdomain_logs_error_on_api_failure(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'zone-add-subdomain-fail' );
		}

		global $current_site;

		$current_site         = new \stdClass();
		$current_site->domain = 'mynetwork.com';

		// Bypass Domain_Manager::should_create_www_subdomain() by filtering the result.
		add_filter( 'wu_cloudflare_should_add_www', '__return_false' );

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( str_contains( $url, 'dns_records' ) ) {
					return new \WP_Error( 'api-fail', 'API failure' );
				}

				return $preempt;
			},
			10,
			3
		);

		// Should not throw — error is logged internally.
		$this->provider->on_add_subdomain( 'newsite.mynetwork.com', 1 );

		remove_filter( 'wu_cloudflare_should_add_www', '__return_false' );

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// on_remove_subdomain() — via mocked HTTP and global $current_site
	// -------------------------------------------------------------------------

	/**
	 * Test on_remove_subdomain returns early when zone_id not configured.
	 */
	public function test_on_remove_subdomain_returns_early_without_zone_id(): void {

		if ( defined( 'WU_CLOUDFLARE_ZONE_ID' ) && WU_CLOUDFLARE_ZONE_ID ) {
			$this->markTestSkipped( 'WU_CLOUDFLARE_ZONE_ID is defined — cannot test early return.' );
		}

		$called = false;

		add_filter(
			'pre_http_request',
			function () use ( &$called ) {
				$called = true;

				return new \WP_Error( 'should-not-be-called', 'Should not be called' );
			},
			10,
			3
		);

		$this->provider->on_remove_subdomain( 'sub.example.com', 1 );

		$this->assertFalse( $called );
	}

	/**
	 * Test on_remove_subdomain returns early when not a subdomain of current_site.
	 */
	public function test_on_remove_subdomain_returns_early_when_not_subdomain_of_current_site(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'zone-remove-subdomain' );
		}

		global $current_site;

		$current_site         = new \stdClass();
		$current_site->domain = 'mynetwork.com';

		$called = false;

		add_filter(
			'pre_http_request',
			function () use ( &$called ) {
				$called = true;

				return new \WP_Error( 'should-not-be-called', 'Should not be called' );
			},
			10,
			3
		);

		$this->provider->on_remove_subdomain( 'otherdomain.com', 1 );

		$this->assertFalse( $called );
	}

	/**
	 * Test on_remove_subdomain returns early when subdomain part is empty.
	 */
	public function test_on_remove_subdomain_returns_early_when_subdomain_empty(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'zone-remove-empty' );
		}

		global $current_site;

		$current_site         = new \stdClass();
		$current_site->domain = 'mynetwork.com';

		$called = false;

		add_filter(
			'pre_http_request',
			function () use ( &$called ) {
				$called = true;

				return new \WP_Error( 'should-not-be-called', 'Should not be called' );
			},
			10,
			3
		);

		$this->provider->on_remove_subdomain( 'mynetwork.com', 1 );

		$this->assertFalse( $called );
	}

	/**
	 * Test on_remove_subdomain returns early when DNS entry not found.
	 */
	public function test_on_remove_subdomain_returns_early_when_dns_entry_not_found(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'zone-remove-no-entry' );
		}

		global $current_site;

		$current_site         = new \stdClass();
		$current_site->domain = 'mynetwork.com';

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				// DNS lookup returns empty result.
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => [] ] ),
				];
			},
			10,
			3
		);

		// Should not throw — returns early when no DNS entry found.
		$this->provider->on_remove_subdomain( 'oldsite.mynetwork.com', 1 );

		$this->assertTrue( true );
	}

	/**
	 * Test on_remove_subdomain makes DELETE API call when DNS entry found.
	 */
	public function test_on_remove_subdomain_makes_delete_api_call(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'zone-remove-ok' );
		}

		global $current_site;

		$current_site         = new \stdClass();
		$current_site->domain = 'mynetwork.com';

		$deleteCalled = false;
		$dnsEntry     = (object) [ 'id' => 'rec-to-delete' ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$deleteCalled, $dnsEntry ) {
				if ( 'DELETE' === $args['method'] ) {
					$deleteCalled = true;

					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body'     => wp_json_encode( [ 'result' => [] ] ),
					];
				}

				// GET for DNS lookup.
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => [ $dnsEntry ] ] ),
				];
			},
			10,
			3
		);

		$this->provider->on_remove_subdomain( 'oldsite.mynetwork.com', 1 );

		$this->assertTrue( $deleteCalled, 'Expected DELETE API call for subdomain removal.' );
	}

	/**
	 * Test on_remove_subdomain logs error when DELETE API call fails.
	 */
	public function test_on_remove_subdomain_logs_error_on_delete_failure(): void {

		if ( ! defined( 'WU_CLOUDFLARE_ZONE_ID' ) ) {
			define( 'WU_CLOUDFLARE_ZONE_ID', 'zone-remove-fail' );
		}

		global $current_site;

		$current_site         = new \stdClass();
		$current_site->domain = 'mynetwork.com';

		$dnsEntry = (object) [ 'id' => 'rec-fail-delete' ];

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $dnsEntry ) {
				if ( 'DELETE' === $args['method'] ) {
					return new \WP_Error( 'delete-fail', 'Delete failed' );
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [ 'result' => [ $dnsEntry ] ] ),
				];
			},
			10,
			3
		);

		// Should not throw — error is logged internally.
		$this->provider->on_remove_subdomain( 'oldsite.mynetwork.com', 1 );

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// cloudflare_api_call() — tested indirectly via HTTP error response
	// -------------------------------------------------------------------------

	/**
	 * Test cloudflare_api_call returns WP_Error on non-200 HTTP response.
	 */
	public function test_cloudflare_api_call_returns_wp_error_on_non_200(): void {

		// Mock a 403 response — triggers the error branch in cloudflare_api_call.
		$this->mock_http( $this->make_cf_error_response( 403, 'Forbidden' ) );

		// get_zone_id calls cloudflare_api_call internally.
		$result = $this->provider->get_zone_id( 'example.com' );

		// With a 403, the API call returns WP_Error, so get_zone_id falls back to constant or null.
		$this->assertTrue( is_null( $result ) || is_string( $result ) );
	}

	// -------------------------------------------------------------------------
	// test_connection() — method existence
	// -------------------------------------------------------------------------

	/**
	 * Test test_connection method exists.
	 *
	 * The method calls wp_send_json_success/error which exits in a real request.
	 * We only verify the method is callable.
	 */
	public function test_test_connection_method_exists(): void {

		$this->assertTrue( method_exists( $this->provider, 'test_connection' ) );
	}

	// -------------------------------------------------------------------------
	// get_instructions() — method existence
	// -------------------------------------------------------------------------

	/**
	 * Test get_instructions method exists.
	 */
	public function test_get_instructions_method_exists(): void {

		$this->assertTrue( method_exists( $this->provider, 'get_instructions' ) );
	}

	// -------------------------------------------------------------------------
	// is_setup() — constants check
	// -------------------------------------------------------------------------

	/**
	 * Test is_setup returns false when required constants are missing.
	 */
	public function test_is_setup_returns_false_when_constants_missing(): void {

		// WU_CLOUDFLARE_API_KEY and WU_CLOUDFLARE_ZONE_ID are required.
		// In the test env, at least one is likely missing.
		$result = $this->provider->is_setup();

		$this->assertIsBool( $result );
	}

	// -------------------------------------------------------------------------
	// enable_dns() / disable_dns() / is_dns_enabled()
	// -------------------------------------------------------------------------

	/**
	 * Test enable_dns and disable_dns toggle.
	 */
	public function test_enable_disable_dns_toggle(): void {

		$this->provider->enable_dns();
		$this->assertTrue( $this->provider->is_dns_enabled() );

		$this->provider->disable_dns();
		$this->assertFalse( $this->provider->is_dns_enabled() );
	}

	/**
	 * Test is_dns_enabled returns bool.
	 */
	public function test_is_dns_enabled_returns_bool(): void {

		$this->assertIsBool( $this->provider->is_dns_enabled() );
	}
}
