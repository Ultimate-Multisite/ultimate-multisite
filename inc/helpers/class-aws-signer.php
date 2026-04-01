<?php
/**
 * AWS SigV4 Request Signer.
 *
 * Utility class for signing AWS API requests using Signature Version 4.
 * Extracted from the WorkMail integration for reuse across AWS services
 * (SES, WorkMail, etc.).
 *
 * @package WP_Ultimo
 * @subpackage Helpers
 * @since 2.5.0
 */

namespace WP_Ultimo\Helpers;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * AWS Signature Version 4 request signer.
 *
 * @since 2.5.0
 */
class AWS_Signer {

	/**
	 * AWS access key ID.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $access_key;

	/**
	 * AWS secret access key.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $secret_key;

	/**
	 * AWS region.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $region;

	/**
	 * AWS service name (e.g. 'ses', 'email').
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $service;

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 *
	 * @param string $access_key AWS access key ID.
	 * @param string $secret_key AWS secret access key.
	 * @param string $region     AWS region (e.g. 'us-east-1').
	 * @param string $service    AWS service name (e.g. 'ses').
	 */
	public function __construct(string $access_key, string $secret_key, string $region, string $service) {

		$this->access_key = $access_key;
		$this->secret_key = $secret_key;
		$this->region     = $region;
		$this->service    = $service;
	}

	/**
	 * Sign an HTTP request using AWS Signature Version 4.
	 *
	 * Returns the Authorization header and x-amz-date header values needed
	 * to authenticate the request with AWS.
	 *
	 * @since 2.5.0
	 *
	 * @param string $method  HTTP method (GET, POST, etc.).
	 * @param string $url     Full request URL.
	 * @param string $payload Request body (empty string for GET requests).
	 * @return array{
	 *   Authorization: string,
	 *   x-amz-date: string,
	 *   x-amz-content-sha256: string
	 * }
	 */
	public function sign(string $method, string $url, string $payload = ''): array {

		$parsed      = wp_parse_url($url);
		$uri         = $parsed['path'] ?? '/';
		$query       = $parsed['query'] ?? '';
		$host        = $parsed['host'] ?? '';
		$amz_date    = gmdate('Ymd\THis\Z');
		$date_stamp  = gmdate('Ymd');
		$payload_hash = hash('sha256', $payload);

		$canonical_headers = "host:{$host}\n" .
			"x-amz-content-sha256:{$payload_hash}\n" .
			"x-amz-date:{$amz_date}\n";

		$signed_headers = 'host;x-amz-content-sha256;x-amz-date';

		$canonical_request = implode(
			"\n",
			[
				strtoupper($method),
				$this->uri_encode_path($uri),
				$this->canonical_query_string($query),
				$canonical_headers,
				$signed_headers,
				$payload_hash,
			]
		);

		$credential_scope = implode(
			'/',
			[
				$date_stamp,
				$this->region,
				$this->service,
				'aws4_request',
			]
		);

		$string_to_sign = implode(
			"\n",
			[
				'AWS4-HMAC-SHA256',
				$amz_date,
				$credential_scope,
				hash('sha256', $canonical_request),
			]
		);

		$signing_key   = $this->get_signing_key($date_stamp);
		$signature     = hash_hmac('sha256', $string_to_sign, $signing_key);
		$authorization = sprintf(
			'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
			$this->access_key,
			$credential_scope,
			$signed_headers,
			$signature
		);

		return [
			'Authorization'        => $authorization,
			'x-amz-date'           => $amz_date,
			'x-amz-content-sha256' => $payload_hash,
		];
	}

	/**
	 * Derive the signing key for the given date.
	 *
	 * @since 2.5.0
	 *
	 * @param string $date_stamp Date in Ymd format.
	 * @return string Binary signing key.
	 */
	private function get_signing_key(string $date_stamp): string {

		$k_date    = hash_hmac('sha256', $date_stamp, 'AWS4' . $this->secret_key, true);
		$k_region  = hash_hmac('sha256', $this->region, $k_date, true);
		$k_service = hash_hmac('sha256', $this->service, $k_region, true);

		return hash_hmac('sha256', 'aws4_request', $k_service, true);
	}

	/**
	 * URI-encode a path component per AWS requirements.
	 *
	 * @since 2.5.0
	 *
	 * @param string $path The URI path.
	 * @return string Encoded path.
	 */
	private function uri_encode_path(string $path): string {

		$segments = explode('/', $path);
		$encoded  = array_map(
			static function (string $segment): string {
				return rawurlencode(rawurldecode($segment));
			},
			$segments
		);

		return implode('/', $encoded);
	}

	/**
	 * Build a canonical query string sorted by key.
	 *
	 * @since 2.5.0
	 *
	 * @param string $query Raw query string.
	 * @return string Canonical query string.
	 */
	private function canonical_query_string(string $query): string {

		if ('' === $query) {
			return '';
		}

		parse_str($query, $params);
		ksort($params);

		$parts = [];

		foreach ($params as $key => $value) {
			$parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
		}

		return implode('&', $parts);
	}
}
