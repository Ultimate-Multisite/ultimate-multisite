<?php
/**
 * Tests for the Credential_Store helper class.
 */

namespace WP_Ultimo\Helpers;

use WP_UnitTestCase;

/**
 * @group credential-store
 */
class Credential_Store_Test extends WP_UnitTestCase {

	// ------------------------------------------------------------------
	// Constants
	// ------------------------------------------------------------------

	public function test_cipher_method_constant() {
		$this->assertEquals('aes-256-cbc', Credential_Store::CIPHER_METHOD);
	}

	public function test_encrypted_prefix_constant() {
		$this->assertEquals('$wu_enc$', Credential_Store::ENCRYPTED_PREFIX);
	}

	public function test_sodium_prefix_constant() {
		$this->assertEquals('$wu_sodium$', Credential_Store::SODIUM_PREFIX);
	}

	// ------------------------------------------------------------------
	// encrypt / decrypt round-trip
	// ------------------------------------------------------------------

	public function test_encrypt_returns_string() {
		$result = Credential_Store::encrypt('test_secret');
		$this->assertIsString($result);
	}

	public function test_encrypt_returns_non_empty_for_valid_input() {
		$result = Credential_Store::encrypt('my_api_key_123');
		$this->assertNotEmpty($result);
	}

	public function test_encrypt_returns_empty_for_empty_input() {
		$result = Credential_Store::encrypt('');
		$this->assertEquals('', $result);
	}

	public function test_encrypted_value_starts_with_prefix() {
		$result = Credential_Store::encrypt('secret_value');
		$this->assertTrue(
			str_starts_with($result, Credential_Store::ENCRYPTED_PREFIX) ||
			str_starts_with($result, Credential_Store::SODIUM_PREFIX)
		);
	}

	public function test_encrypt_decrypt_round_trip() {
		$original  = 'my_super_secret_api_key_12345';
		$encrypted = Credential_Store::encrypt($original);
		$decrypted = Credential_Store::decrypt($encrypted);

		$this->assertEquals($original, $decrypted);
	}

	public function test_encrypt_decrypt_round_trip_with_special_chars() {
		$original  = 'p@$$w0rd!#%^&*()_+-={}[]|\\:";\'<>?,./~`';
		$encrypted = Credential_Store::encrypt($original);
		$decrypted = Credential_Store::decrypt($encrypted);

		$this->assertEquals($original, $decrypted);
	}

	public function test_encrypt_decrypt_round_trip_with_unicode() {
		$original  = 'Héllo Wörld 日本語 中文';
		$encrypted = Credential_Store::encrypt($original);
		$decrypted = Credential_Store::decrypt($encrypted);

		$this->assertEquals($original, $decrypted);
	}

	public function test_encrypt_produces_different_ciphertext_each_time() {
		$value = 'same_value';
		$enc1  = Credential_Store::encrypt($value);
		$enc2  = Credential_Store::encrypt($value);

		// Due to random IV, encryptions should differ
		$this->assertNotEquals($enc1, $enc2);
	}

	// ------------------------------------------------------------------
	// decrypt edge cases
	// ------------------------------------------------------------------

	public function test_decrypt_returns_empty_for_empty_input() {
		$this->assertEquals('', Credential_Store::decrypt(''));
	}

	public function test_decrypt_returns_plaintext_for_non_prefixed_value() {
		// Non-encrypted values are returned as-is (backwards compat)
		$result = Credential_Store::decrypt('plain_text_value');
		$this->assertEquals('plain_text_value', $result);
	}

	public function test_decrypt_returns_empty_for_invalid_encrypted_data() {
		$result = Credential_Store::decrypt(Credential_Store::ENCRYPTED_PREFIX . 'invalid_base64!!!');
		$this->assertEquals('', $result);
	}

	public function test_decrypt_returns_empty_for_corrupted_data() {
		// Valid base64 but wrong content
		$result = Credential_Store::decrypt(Credential_Store::ENCRYPTED_PREFIX . base64_encode(str_repeat('X', 32)));
		$this->assertIsString($result);
	}

	// ------------------------------------------------------------------
	// get_encryption_key (private)
	// ------------------------------------------------------------------

	public function test_get_encryption_key_returns_32_bytes() {
		$method = new \ReflectionMethod(Credential_Store::class, 'get_encryption_key');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$key = $method->invoke(null);
		$this->assertEquals(32, strlen($key));
	}

	// ------------------------------------------------------------------
	// get_sodium_key (private)
	// ------------------------------------------------------------------

	public function test_get_sodium_key_returns_32_bytes() {
		$method = new \ReflectionMethod(Credential_Store::class, 'get_sodium_key');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$key = $method->invoke(null);
		$this->assertEquals(32, strlen($key));
	}

	// ------------------------------------------------------------------
	// Long values
	// ------------------------------------------------------------------

	public function test_encrypt_decrypt_long_value() {
		$original  = str_repeat('A', 10000);
		$encrypted = Credential_Store::encrypt($original);
		$decrypted = Credential_Store::decrypt($encrypted);

		$this->assertEquals($original, $decrypted);
	}
}
