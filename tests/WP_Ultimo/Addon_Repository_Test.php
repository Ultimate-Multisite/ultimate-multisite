<?php
/**
 * Tests for the Addon_Repository class.
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * @group addon-repository
 */
class Addon_Repository_Test extends WP_UnitTestCase {

	/**
	 * @var Addon_Repository
	 */
	private $repo;

	public function set_up() {
		parent::set_up();
		$this->repo = new Addon_Repository();
	}

	public function tear_down() {
		remove_all_filters('upgrader_pre_download');
		remove_all_filters('http_request_args');
		parent::tear_down();
	}

	private function get_oauth_query_args(): array {
		$query = (string) wp_parse_url($this->repo->get_oauth_url(), PHP_URL_QUERY);
		$args  = [];

		parse_str($query, $args);

		return $args;
	}

	private function encrypt_for_addon_repository(string $plain_text, string $key): string {
		$iv_length = openssl_cipher_iv_length('aes-256-cbc');

		$this->assertNotFalse($iv_length);

		$iv        = str_repeat('1', $iv_length);
		$encrypted = openssl_encrypt($plain_text, 'aes-256-cbc', $key, 0, $iv);

		$this->assertNotFalse($encrypted);

		return base64_encode($iv . $encrypted); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	// ------------------------------------------------------------------
	// Constructor
	// ------------------------------------------------------------------

	public function test_constructor_creates_instance() {
		$this->assertInstanceOf(Addon_Repository::class, $this->repo);
	}

	// ------------------------------------------------------------------
	// init
	// ------------------------------------------------------------------

	public function test_init_registers_upgrader_pre_download_filter() {
		$this->repo->init();

		$this->assertNotFalse(has_filter('upgrader_pre_download', [$this->repo, 'upgrader_pre_download']));
	}

	// ------------------------------------------------------------------
	// get_access_token
	// ------------------------------------------------------------------

	public function test_get_access_token_returns_empty_string_without_refresh_token() {
		// No refresh token saved
		wu_save_option('wu-refresh-token', '');

		$token = $this->repo->get_access_token();
		$this->assertIsString($token);
		$this->assertEmpty($token);
	}

	public function test_get_access_token_returns_string() {
		$token = $this->repo->get_access_token();
		$this->assertIsString($token);
	}

	// ------------------------------------------------------------------
	// set_update_download_headers
	// ------------------------------------------------------------------

	public function test_set_update_download_headers_adds_auth_for_matching_url() {
		// Set the authorization header via reflection
		$ref = new \ReflectionProperty(Addon_Repository::class, 'authorization_header');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->setValue($this->repo, 'Bearer test_token_123');

		$parsed_args = ['headers' => []];
		$url         = MULTISITE_ULTIMATE_UPDATE_URL . 'some/path';

		$result = $this->repo->set_update_download_headers($parsed_args, $url);

		$this->assertEquals('Bearer test_token_123', $result['headers']['Authorization']);
	}

	public function test_set_update_download_headers_ignores_non_matching_url() {
		$ref = new \ReflectionProperty(Addon_Repository::class, 'authorization_header');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->setValue($this->repo, 'Bearer test_token_123');

		$parsed_args = ['headers' => []];
		$url         = 'https://example.com/other/path';

		$result = $this->repo->set_update_download_headers($parsed_args, $url);

		$this->assertArrayNotHasKey('Authorization', $result['headers']);
	}

	public function test_set_update_download_headers_ignores_empty_auth_header() {
		$parsed_args = ['headers' => []];
		$url         = MULTISITE_ULTIMATE_UPDATE_URL . 'some/path';

		$result = $this->repo->set_update_download_headers($parsed_args, $url);

		$this->assertArrayNotHasKey('Authorization', $result['headers']);
	}

	// ------------------------------------------------------------------
	// delete_tokens
	// ------------------------------------------------------------------

	public function test_delete_tokens_clears_all_token_data() {
		// Set some token data first
		wu_save_option('wu-refresh-token', 'test_refresh');
		set_transient('wu-access-token', 'test_access', 3600);
		set_site_transient('wu-addons-list', ['addon1'], 3600);
		set_site_transient('wu_has_addon_purchase', '1', 3600);

		$this->repo->delete_tokens();

		$this->assertEmpty(wu_get_option('wu-refresh-token'));
		$this->assertFalse(get_transient('wu-access-token'));
		$this->assertFalse(get_site_transient('wu-addons-list'));
		$this->assertFalse(get_site_transient('wu_has_addon_purchase'));
	}

	// ------------------------------------------------------------------
	// clear_addon_purchase_cache
	// ------------------------------------------------------------------

	public function test_clear_addon_purchase_cache_deletes_transient() {
		set_site_transient('wu_has_addon_purchase', '1', 3600);

		Addon_Repository::clear_addon_purchase_cache();

		$this->assertFalse(get_site_transient('wu_has_addon_purchase'));
	}

	// ------------------------------------------------------------------
	// has_addon_purchase
	// ------------------------------------------------------------------

	public function test_has_addon_purchase_returns_false_without_access_token() {
		// No refresh token = no access token
		wu_save_option('wu-refresh-token', '');
		delete_site_transient('wu_has_addon_purchase');

		$result = $this->repo->has_addon_purchase();
		$this->assertFalse($result);
	}

	public function test_has_addon_purchase_returns_cached_true() {
		set_site_transient('wu_has_addon_purchase', '1', 3600);

		$result = $this->repo->has_addon_purchase();
		$this->assertTrue($result);
	}

	public function test_has_addon_purchase_returns_cached_false() {
		set_site_transient('wu_has_addon_purchase', '0', 3600);

		$result = $this->repo->has_addon_purchase();
		$this->assertFalse($result);
	}

	public function test_has_addon_purchase_returns_bool() {
		$result = $this->repo->has_addon_purchase();
		$this->assertIsBool($result);
	}

	// ------------------------------------------------------------------
	// get_oauth_url
	// ------------------------------------------------------------------

	public function test_get_oauth_url_returns_string() {
		$url = $this->repo->get_oauth_url();
		$this->assertIsString($url);
	}

	public function test_get_oauth_url_contains_base_url() {
		$url = $this->repo->get_oauth_url();
		$this->assertStringContainsString(MULTISITE_ULTIMATE_UPDATE_URL, $url);
	}

	public function test_get_oauth_url_contains_oauth_authorize() {
		$url = $this->repo->get_oauth_url();
		$this->assertStringContainsString('oauth/authorize', $url);
	}

	public function test_get_oauth_url_contains_response_type() {
		$url = $this->repo->get_oauth_url();
		$this->assertStringContainsString('response_type=code', $url);
	}

	public function test_get_oauth_url_contains_client_id() {
		$args = $this->get_oauth_query_args();

		$this->assertArrayHasKey('client_id', $args);
		$this->assertNotSame('', $args['client_id'], 'Packaged addon-store OAuth credentials must decrypt to a non-empty client_id.');
	}

	public function test_get_oauth_url_contains_redirect_uri() {
		$url = $this->repo->get_oauth_url();
		$this->assertStringContainsString('redirect_uri=', $url);
	}

	// ------------------------------------------------------------------
	// get_user_data
	// ------------------------------------------------------------------

	public function test_get_user_data_returns_empty_array_without_token() {
		wu_save_option('wu-refresh-token', '');
		delete_transient('wu-access-token');

		$data = $this->repo->get_user_data();
		$this->assertIsArray($data);
		$this->assertEmpty($data);
	}

	// ------------------------------------------------------------------
	// decrypt_value (private, tested via reflection)
	// ------------------------------------------------------------------

	public function test_decrypt_value_returns_string() {
		$method = new \ReflectionMethod(Addon_Repository::class, 'decrypt_value');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		// Provide data with enough bytes for IV (16 bytes) + some cipher text
		$fake_data = str_repeat('A', 32); // 16 bytes IV + 16 bytes cipher text
		$result    = $method->invoke($this->repo, base64_encode($fake_data)); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		// Malformed payloads should fail closed.
		$this->assertSame('', $result);
	}

	public function test_decrypt_value_supports_current_release_archive_key() {
		if ( ! function_exists('openssl_encrypt') || ! function_exists('openssl_cipher_iv_length')) {
			$this->markTestSkipped('OpenSSL is required to test addon credential encryption.');
		}

		$method = new \ReflectionMethod(Addon_Repository::class, 'decrypt_value');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$key = hash_file('sha256', dirname(__DIR__, 2) . '/inc/class-addon-repository.php');

		$this->assertNotFalse($key);

		$payload = $this->encrypt_for_addon_repository('current-client-id', $key);

		$this->assertSame('current-client-id', $method->invoke($this->repo, $payload));
	}

	public function test_decrypt_value_supports_legacy_credential_key() {
		if ( ! function_exists('openssl_encrypt') || ! function_exists('openssl_cipher_iv_length')) {
			$this->markTestSkipped('OpenSSL is required to test addon credential encryption.');
		}

		$method = new \ReflectionMethod(Addon_Repository::class, 'decrypt_value');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$payload = $this->encrypt_for_addon_repository(
			'legacy-client-id',
			'4ffb3de0414a284b500b368caedf7c40f03cac215eb83b0164e91c491ced4bdf'
		);

		$this->assertSame('legacy-client-id', $method->invoke($this->repo, $payload));
	}
}
