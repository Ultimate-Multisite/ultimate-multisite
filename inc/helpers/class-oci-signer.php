<?php
/**
 * OCI Request Signer.
 *
 * Utility class for signing Oracle Cloud Infrastructure API requests using
 * OCI's RSA-SHA256 HTTP Signature scheme.
 *
 * @package WP_Ultimo
 * @subpackage Helpers
 * @since 2.5.0
 */

namespace WP_Ultimo\Helpers;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * OCI request signer.
 *
 * @since 2.5.0
 */
class OCI_Signer {

	/**
	 * Tenancy OCID.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $tenancy_ocid;

	/**
	 * User OCID.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $user_ocid;

	/**
	 * API key fingerprint.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $fingerprint;

	/**
	 * PEM encoded private key.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $private_key;

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 *
	 * @param string $tenancy_ocid Tenancy OCID.
	 * @param string $user_ocid    User OCID.
	 * @param string $fingerprint  API key fingerprint.
	 * @param string $private_key  PEM encoded private key.
	 */
	public function __construct(string $tenancy_ocid, string $user_ocid, string $fingerprint, string $private_key) {

		$this->tenancy_ocid = $tenancy_ocid;
		$this->user_ocid    = $user_ocid;
		$this->fingerprint  = $fingerprint;
		$this->private_key  = $this->normalize_private_key($private_key);
	}

	/**
	 * Sign an OCI HTTP request.
	 *
	 * @since 2.5.0
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     Full request URL.
	 * @param string $payload Request body.
	 * @return array<string, string>
	 */
	public function sign(string $method, string $url, string $payload = ''): array {

		$parsed = wp_parse_url($url);
		$path   = $parsed['path'] ?? '/';
		$query  = isset($parsed['query']) && '' !== $parsed['query'] ? '?' . $parsed['query'] : '';
		$host   = $parsed['host'] ?? '';
		$date   = gmdate('D, d M Y H:i:s \G\M\T');
		$method = strtolower($method);

		$headers = [
			'date'             => $date,
			'host'             => $host,
			'(request-target)' => $method . ' ' . $path . $query,
		];

		$signed_header_names = ['date', '(request-target)', 'host'];

		if ('' !== $payload) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- OCI requires a base64-encoded SHA-256 digest header.
			$headers['x-content-sha256'] = base64_encode(hash('sha256', $payload, true));
			$headers['content-type']     = 'application/json';
			$headers['content-length']   = (string) strlen($payload);

			$signed_header_names = array_merge($signed_header_names, ['x-content-sha256', 'content-type', 'content-length']);
		}

		$signing_string = $this->build_signing_string($headers, $signed_header_names);
		$signature      = '';
		$private_key    = openssl_pkey_get_private($this->private_key);

		if (false === $private_key || ! openssl_sign($signing_string, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
			return [];
		}

		$key_id        = $this->tenancy_ocid . '/' . $this->user_ocid . '/' . $this->fingerprint;
		$authorization = sprintf(
			'Signature version="1",keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
			$key_id,
			implode(' ', $signed_header_names),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- OCI requires the RSA signature to be base64 encoded.
			base64_encode($signature)
		);

		$response_headers = [
			'Authorization' => $authorization,
			'Date'          => $date,
		];

		if ('' !== $payload) {
			$response_headers['x-content-sha256'] = $headers['x-content-sha256'];
			$response_headers['Content-Type']     = $headers['content-type'];
			$response_headers['Content-Length']   = $headers['content-length'];
		}

		return $response_headers;
	}

	/**
	 * Build the newline-delimited OCI signing string.
	 *
	 * @since 2.5.0
	 *
	 * @param array<string, string> $headers             Header values keyed by lowercase header name.
	 * @param array<int, string>    $signed_header_names Ordered signed header names.
	 * @return string
	 */
	private function build_signing_string(array $headers, array $signed_header_names): string {

		$lines = [];

		foreach ($signed_header_names as $name) {
			$lines[] = $name . ': ' . $headers[ $name ];
		}

		return implode("\n", $lines);
	}

	/**
	 * Normalize private keys copied through constants or option forms.
	 *
	 * @since 2.5.0
	 *
	 * @param string $private_key Private key value.
	 * @return string
	 */
	private function normalize_private_key(string $private_key): string {

		return str_replace('\\n', "\n", trim($private_key));
	}
}
