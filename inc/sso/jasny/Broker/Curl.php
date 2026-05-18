<?php
/**
 * Thin wrapper around the PHP cURL extension used by the broker.
 *
 * In-tree vendored from jasny/sso.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Broker
 * @since 2.0.11
 *
 * @codeCoverageIgnore
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Broker;

defined('ABSPATH') || exit;

/**
 * cURL transport for broker -> SSO server requests.
 */
class Curl {

	/**
	 * Constructor.
	 *
	 * @throws \Exception If the cURL extension isn't loaded.
	 */
	public function __construct() {
		if ( ! extension_loaded('curl')) {
			throw new \Exception('cURL extension not loaded');
		}
	}

	/**
	 * Send an HTTP request to the SSO server.
	 *
	 * @param string                     $method  HTTP method (`GET`, `POST`, `DELETE`).
	 * @param string                     $url     Full URL to call.
	 * @param string[]                   $headers HTTP headers to send.
	 * @param array<string,mixed>|string $data    Query or post payload.
	 * @return array{httpCode:int,contentType:string,body:string}
	 * @throws RequestException If cURL signals a transport failure.
	 */
	public function request(string $method, string $url, array $headers, $data = '') {
		$ch = curl_init($url);

		if (false === $ch) {
			throw new \RuntimeException('Failed to initialize a cURL session');
		}

		if ([] !== $data && '' !== $data) {
			$post = is_string($data) ? $data : http_build_query($data);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$responseBody = (string) curl_exec($ch);

		if (0 !== curl_errno($ch)) {
			throw new RequestException('Server request failed: ' . curl_error($ch));
		}

		$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? 'text/html';

		return [
			'httpCode'    => $httpCode,
			'contentType' => $contentType,
			'body'        => $responseBody,
		];
	}
}
