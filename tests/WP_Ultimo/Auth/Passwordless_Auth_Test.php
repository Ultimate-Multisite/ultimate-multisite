<?php
/**
 * Tests for passwordless authentication services.
 *
 * @package WP_Ultimo
 * @subpackage Auth
 * @since 2.13.2
 */

namespace WP_Ultimo\Auth;

use WP_Ultimo\Database\Email_OTP_Attempts\Email_OTP_Attempts_Table;
use WP_Ultimo\Database\Passkey_Credentials\Passkey_Credentials_Table;
use WP_Ultimo\Database\WebAuthn_Challenges\WebAuthn_Challenges_Table;

defined('ABSPATH') || exit;

/**
 * Passwordless authentication service tests.
 */
class Passwordless_Auth_Test extends \WP_UnitTestCase {

	/**
	 * Installs tables before each test.
	 */
	public function set_up() {

		parent::set_up();

		$this->install_auth_tables();
		$this->truncate_auth_tables();
	}

	/**
	 * Cleans up filters and rows after each test.
	 */
	public function tear_down() {

		remove_all_filters('wu_passwordless_otp_code');
		remove_all_filters('wu_passwordless_should_send_otp');

		$this->truncate_auth_tables();

		parent::tear_down();
	}

	/**
	 * Tests OTPs are stored hashed and consumed once.
	 */
	public function test_otp_codes_are_hashed_and_single_use() {

		$user = self::factory()->user->create_and_get(
			[
				'user_email' => 'passwordless@example.test',
			]
		);

		add_filter(
			'wu_passwordless_otp_code',
			function () {
				return '123456';
			}
		);

		add_filter('wu_passwordless_should_send_otp', '__return_false');

		$service = new Email_OTP_Service();
		$created = $service->create_and_send($user, $user->user_email);

		$this->assertIsArray($created);
		$this->assertNotEmpty($created['token']);

		$row = $service->get_attempt_by_token($created['token']);

		$this->assertNotNull($row);
		$this->assertStringNotContainsString('123456', $row->code_hash);
		$this->assertTrue(wp_check_password('123456', $row->code_hash));

		$verified = $service->verify($created['token'], '123456');

		$this->assertInstanceOf(\WP_User::class, $verified);
		$this->assertSame($user->ID, $verified->ID);

		$second_try = $service->verify($created['token'], '123456');

		$this->assertWPError($second_try);
	}

	/**
	 * Tests failed OTP email delivery returns an error and removes the stored attempt.
	 */
	public function test_failed_otp_email_delivery_cleans_up_attempt() {

		global $wpdb;

		$user = self::factory()->user->create_and_get(
			[
				'user_email' => 'otp-delivery-failed@example.test',
			]
		);

		$service = new class() extends Email_OTP_Service {

			/**
			 * Simulates a failed email send.
			 *
			 * @param \WP_User $user User object.
			 * @param string   $code OTP code.
			 * @return bool
			 */
			protected function send_email(\WP_User $user, $code) {

				return false;
			}
		};

		$result = $service->create_and_send($user, $user->user_email);

		$this->assertWPError($result);
		$this->assertSame('otp_email_failed', $result->get_error_code());

		$table = $service->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame('0', $wpdb->get_var("SELECT COUNT(*) FROM {$table}"));
	}

	/**
	 * Tests WebAuthn challenges are stored hashed and single-use.
	 */
	public function test_webauthn_challenges_are_hashed_and_single_use() {

		$store     = new WebAuthn_Challenge_Store();
		$challenge = $store->create('authentication', 123, 'example.test', 'https://example.test');

		$this->assertNotEmpty($challenge);

		$row = $store->get_valid($challenge, 'authentication');

		$this->assertNotNull($row);
		$this->assertSame(hash('sha256', $challenge), $row->challenge_hash);
		$this->assertStringNotContainsString($challenge, $row->challenge_hash);

		$this->assertTrue($store->mark_used((int) $row->id));

		$this->assertNull($store->get_valid($challenge, 'authentication'));
		$this->assertFalse($store->mark_used((int) $row->id));
	}

	/**
	 * Tests passkey credentials can be stored and usage counters updated.
	 */
	public function test_passkey_credentials_can_be_stored_and_updated() {

		$store = new Passkey_Credential_Store();

		$created = $store->create(
			123,
			'credential-id',
			"-----BEGIN PUBLIC KEY-----\ntest\n-----END PUBLIC KEY-----\n",
			1,
			str_repeat('a', 32),
			['internal']
		);

		$this->assertTrue($created);
		$this->assertTrue($store->user_has_credentials(123));

		$credential = $store->find_by_credential_id('credential-id');

		$this->assertNotNull($credential);
		$this->assertSame('credential-id', $credential->credential_id);
		$this->assertSame('internal', $credential->transports);

		$this->assertTrue($store->update_usage((int) $credential->id, 7));

		$updated = $store->find_by_credential_id('credential-id');

		$this->assertSame(7, (int) $updated->sign_count);
		$this->assertNotEmpty($updated->date_last_used);
	}

	/**
	 * Tests passkey authentication fails closed when usage cannot be persisted.
	 */
	public function test_passkey_authentication_fails_when_usage_update_fails() {

		$user = self::factory()->user->create_and_get(
			[
				'user_email' => 'passkey-update-failure@example.test',
			]
		);

		$credential = (object) [
			'id'         => 789,
			'user_id'    => $user->ID,
			'sign_count' => 1,
			'public_key' => 'public-key',
		];

		$challenge = (object) [
			'id'      => 456,
			'user_id' => $user->ID,
			'rp_id'   => 'example.test',
			'origin'  => 'https://example.test',
		];

		$helper = $this->getMockBuilder(WebAuthn_Helper::class)
			->onlyMethods(['parse_client_data', 'is_origin_allowed', 'parse_assertion_authenticator_data', 'verify_assertion_signature'])
			->getMock();

		$helper->method('parse_client_data')->willReturn(
			[
				'challenge' => 'challenge',
				'origin'    => 'https://example.test',
				'_raw'      => 'client-data',
			]
		);

		$helper->method('is_origin_allowed')->willReturn(true);
		$helper->method('parse_assertion_authenticator_data')->willReturn(
			[
				'rp_id_hash' => hash('sha256', 'example.test', true),
				'sign_count' => 2,
			]
		);
		$helper->method('verify_assertion_signature')->willReturn(true);

		$credentials = $this->getMockBuilder(Passkey_Credential_Store::class)
			->onlyMethods(['find_by_credential_id', 'update_usage'])
			->getMock();

		$credentials->method('find_by_credential_id')->willReturn($credential);
		$credentials->expects($this->once())
			->method('update_usage')
			->with((int) $credential->id, 2)
			->willReturn(false);

		$challenges = $this->getMockBuilder(WebAuthn_Challenge_Store::class)
			->onlyMethods(['get_valid', 'mark_used'])
			->getMock();

		$challenges->method('get_valid')->willReturn($challenge);
		$challenges->expects($this->once())
			->method('mark_used')
			->with((int) $challenge->id)
			->willReturn(true);

		$service = new Passkey_Service();

		$this->set_protected_property($service, 'helper', $helper);
		$this->set_protected_property($service, 'credentials', $credentials);
		$this->set_protected_property($service, 'challenges', $challenges);

		$result = $service->verify_authentication(
			[
				'rawId'    => 'credential-id',
				'response' => [
					'authenticatorData' => 'authenticator-data',
					'clientDataJSON'    => 'client-data',
					'signature'         => 'signature',
				],
			]
		);

		$this->assertWPError($result);
		$this->assertSame('credential_update_failed', $result->get_error_code());
	}

	/**
	 * Tests starting login always returns the generic OTP challenge first.
	 */
	public function test_ajax_start_does_not_expose_passkey_options_before_otp_verification() {

		$user = self::factory()->user->create_and_get(
			[
				'user_email' => 'passkey-holder@example.test',
				'user_login' => 'passkey_holder',
			]
		);

		$store = new Passkey_Credential_Store();

		$this->assertTrue(
			$store->create(
				$user->ID,
				'credential-id-start',
				"-----BEGIN PUBLIC KEY-----\ntest\n-----END PUBLIC KEY-----\n",
				1,
				str_repeat('b', 32),
				['internal']
			)
		);

		add_filter(
			'wu_passwordless_otp_code',
			function () {
				return '123456';
			}
		);

		add_filter('wu_passwordless_should_send_otp', '__return_false');

		$nonce      = wp_create_nonce('wu_passwordless_auth');
		$identifier = $user->user_email;

		$_POST['nonce']         = $nonce;
		$_POST['identifier']    = $identifier;
		$_REQUEST['nonce']      = $nonce;
		$_REQUEST['identifier'] = $identifier;

		$response = $this->capture_ajax_json([Passwordless_Auth_Manager::get_instance(), 'ajax_start']);

		$this->assertTrue($response['success']);
		$this->assertSame('otp', $response['data']['mode']);
		$this->assertNotEmpty($response['data']['token']);
		$this->assertArrayNotHasKey('options', $response['data']);
		$this->assertArrayNotHasKey('publicKey', $response['data']);

		unset($_POST['nonce'], $_POST['identifier'], $_REQUEST['nonce'], $_REQUEST['identifier']);
	}

	/**
	 * Sets a protected property for dependency injection in tests.
	 *
	 * @param object $target   Object to modify.
	 * @param string $property Property name.
	 * @param mixed  $value Property value.
	 */
	protected function set_protected_property($target, $property, $value) {

		$reflection = new \ReflectionProperty($target, $property);
		$reflection->setAccessible(true);
		$reflection->setValue($target, $value);
	}

	/**
	 * Installs auth tables.
	 */
	protected function install_auth_tables() {

		$tables = [
			new Passkey_Credentials_Table(),
			new WebAuthn_Challenges_Table(),
			new Email_OTP_Attempts_Table(),
		];

		foreach ($tables as $table) {
			if ( ! $this->table_exists($table->table_name)) {
				$table->install();
			}
		}
	}

	/**
	 * Checks if a test table exists.
	 *
	 * @param string $table_name Table name.
	 * @return bool
	 */
	protected function table_exists($table_name) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare('SHOW TABLES LIKE %s', sanitize_key($table_name))
		);

		return $found === $table_name;
	}

	/**
	 * Truncates auth tables.
	 */
	protected function truncate_auth_tables() {

		global $wpdb;

		$tables = [
			(new Passkey_Credential_Store())->get_table_name(),
			(new WebAuthn_Challenge_Store())->get_table_name(),
			(new Email_OTP_Service())->get_table_name(),
		];

		foreach ($tables as $table) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query("TRUNCATE TABLE {$table}");
		}
	}

	/**
	 * Captures a JSON AJAX response.
	 *
	 * @param callable $ajax_handler AJAX handler to run.
	 * @return array
	 */
	protected function capture_ajax_json(callable $ajax_handler) {

		$die_handler = function ($message) {
			throw new \WPAjaxDieContinueException(esc_html((string) $message));
		};

		add_filter('wp_doing_ajax', '__return_true');
		add_filter('wp_die_ajax_handler', $die_handler, 1);

		$did_die = false;

		ob_start();

		try {
			$ajax_handler();
		} catch (\WPAjaxDieContinueException $e) {
			$did_die = true;
		}

		$output = ob_get_clean();

		remove_filter('wp_die_ajax_handler', $die_handler, 1);
		remove_filter('wp_doing_ajax', '__return_true');

		$this->assertTrue($did_die, 'Expected wp_send_json() to terminate the AJAX request.');

		return json_decode($output, true);
	}
}
