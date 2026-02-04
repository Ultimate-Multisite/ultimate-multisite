<?php
/**
 * Credential Store helper for encrypting/decrypting hosting integration credentials.
 *
 * @package WP_Ultimo
 * @subpackage Helpers
 * @since 2.3.0
 */

namespace WP_Ultimo\Helpers;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles encryption and decryption of hosting credentials stored in network options.
 *
 * @since 2.3.0
 */
class Credential_Store {

	/**
	 * The cipher method used for encryption.
	 *
	 * @var string
	 */
	const CIPHER_METHOD = 'aes-256-cbc';

	/**
	 * Prefix for encrypted values to identify them.
	 *
	 * @var string
	 */
	const ENCRYPTED_PREFIX = '$wu_enc$';

	/**
	 * Encrypt a value for storage.
	 *
	 * @since 2.3.0
	 *
	 * @param string $value The plaintext value to encrypt.
	 * @return string The encrypted value.
	 */
	public static function encrypt(string $value): string {

		if (empty($value)) {
			return '';
		}

		if ( ! function_exists('openssl_encrypt') || ! in_array(self::CIPHER_METHOD, openssl_get_cipher_methods(), true)) {
			return self::ENCRYPTED_PREFIX . base64_encode($value); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$key = self::get_encryption_key();
		$iv  = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER_METHOD));

		$encrypted = openssl_encrypt($value, self::CIPHER_METHOD, $key, 0, $iv);

		if (false === $encrypted) {
			return self::ENCRYPTED_PREFIX . base64_encode($value); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		return self::ENCRYPTED_PREFIX . base64_encode($iv . $encrypted); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored value.
	 *
	 * @since 2.3.0
	 *
	 * @param string $value The encrypted value to decrypt.
	 * @return string The decrypted plaintext value.
	 */
	public static function decrypt(string $value): string {

		if (empty($value)) {
			return '';
		}

		if (strpos($value, self::ENCRYPTED_PREFIX) !== 0) {
			return $value;
		}

		$encoded = substr($value, strlen(self::ENCRYPTED_PREFIX));
		$decoded = base64_decode($encoded); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if (false === $decoded) {
			return '';
		}

		if ( ! function_exists('openssl_decrypt') || ! in_array(self::CIPHER_METHOD, openssl_get_cipher_methods(), true)) {
			return $decoded;
		}

		$iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
		$iv        = substr($decoded, 0, $iv_length);
		$encrypted = substr($decoded, $iv_length);

		if (empty($encrypted)) {
			return $decoded;
		}

		$key       = self::get_encryption_key();
		$decrypted = openssl_decrypt($encrypted, self::CIPHER_METHOD, $key, 0, $iv);

		return false === $decrypted ? '' : $decrypted;
	}

	/**
	 * Get the encryption key derived from WordPress salts.
	 *
	 * @since 2.3.0
	 * @return string
	 */
	private static function get_encryption_key(): string {

		return hash('sha256', wp_salt('auth'), true);
	}
}
