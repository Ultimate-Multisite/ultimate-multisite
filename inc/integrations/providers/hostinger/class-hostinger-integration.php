<?php
/**
 * Hostinger Integration.
 *
 * Provides API access to the Hostinger (hPanel) public API.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/Hostinger
 * @since 2.5.1
 */

namespace WP_Ultimo\Integrations\Providers\Hostinger;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Hostinger (hPanel) integration provider.
 *
 * Wraps the Hostinger public API documented at https://developers.hostinger.com.
 * Auth is HTTP Bearer using a single API token created in
 * hPanel &rarr; Account &rarr; API. Capability modules (domain mapping, domain
 * selling, multi-tenancy) call {@see Hostinger_Integration::hostinger_api_call()}
 * to reach the API.
 *
 * @since 2.5.1
 */
class Hostinger_Integration extends Integration {

	/**
	 * Hostinger API base URL.
	 *
	 * @since 2.5.1
	 * @var string
	 */
	public const API_BASE_URL = 'https://developers.hostinger.com';

	/**
	 * User-Agent header sent with API requests.
	 *
	 * @since 2.5.1
	 * @var string
	 */
	private const USER_AGENT = 'UltimateMultisite-Hostinger-Integration/1.0';

	/**
	 * Constructor.
	 *
	 * @since 2.5.1
	 */
	public function __construct() {

		parent::__construct('hostinger', 'Hostinger');

		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('hostinger.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/hostinger');
		$this->set_constants(['WU_HOSTINGER_API_TOKEN']);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {

		return __('Hostinger is a global cloud hosting provider with hPanel — a custom control panel that includes integrated CDN, DNS management, free SSL, and a public API for managing domains, DNS zones and hosted websites.', 'ultimate-multisite');
	}

	/**
	 * {@inheritdoc}
	 *
	 * Hostinger does not expose itself via a well-known constant or env var,
	 * so auto-detection is based solely on the API token being configured.
	 */
	public function detect(): bool {

		return (bool) $this->get_credential('WU_HOSTINGER_API_TOKEN');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_HOSTINGER_API_TOKEN' => [
				'title'       => __('Hostinger API Token', 'ultimate-multisite'),
				'desc'        => __('Generate an API token in hPanel under Account &rarr; API. The token is sent as a Bearer credential with every API request.', 'ultimate-multisite'),
				'type'        => 'password',
				'placeholder' => __('Your hPanel API token', 'ultimate-multisite'),
				'html_attr'   => [
					'autocomplete' => 'new-password',
				],
			],
		];
	}

	/**
	 * Renders the wizard instructions partial.
	 *
	 * @since 2.5.1
	 * @return void
	 */
	public function get_instructions(): void {

		wu_get_template('wizards/host-integrations/hostinger-instructions');
	}

	/**
	 * Tests the connection by calling a low-impact authenticated endpoint.
	 *
	 * The Hostinger API does not currently expose a dedicated "verify token"
	 * endpoint, so we issue a `GET /api/domains/v1/portfolio` request which
	 * returns the caller's domain list. Any 2xx response confirms the token
	 * is valid and the API is reachable.
	 *
	 * @since 2.5.1
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$token = $this->get_credential('WU_HOSTINGER_API_TOKEN');

		if (empty($token)) {
			return new \WP_Error('hostinger-no-token', __('Hostinger API token is not configured.', 'ultimate-multisite'));
		}

		$result = $this->hostinger_api_call('/api/domains/v1/portfolio');

		if (is_wp_error($result)) {
			return $result;
		}

		return true;
	}

	/**
	 * Sends an authenticated HTTP request to the Hostinger API.
	 *
	 * @since 2.5.1
	 *
	 * @param string $endpoint API endpoint path (must start with `/`).
	 * @param string $method   HTTP verb. Defaults to GET.
	 * @param array  $data     Request payload. For GET it is appended as the query
	 *                         string; otherwise it is JSON-encoded into the body.
	 * @return mixed Decoded JSON response (object/array) on 2xx, WP_Error otherwise.
	 */
	public function hostinger_api_call(string $endpoint, string $method = 'GET', array $data = []) {

		$token = $this->get_credential('WU_HOSTINGER_API_TOKEN');

		if (empty($token)) {
			return new \WP_Error('hostinger-no-token', __('Hostinger API token is not configured.', 'ultimate-multisite'));
		}

		// Ensure leading slash so the base URL concatenation is unambiguous.
		if ('' === $endpoint || '/' !== $endpoint[0]) {
			$endpoint = '/' . $endpoint;
		}

		$method = strtoupper($method);
		$url    = self::API_BASE_URL . $endpoint;

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
				'User-Agent'    => self::USER_AGENT,
			],
		];

		if ('GET' === $method) {
			if ( ! empty($data)) {
				$url = add_query_arg(array_map('rawurlencode', $data), $url);
			}
		} else {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = empty($data) ? '' : wp_json_encode($data);
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$body        = wp_remote_retrieve_body($response);

		if ($status_code >= 200 && $status_code < 300) {
			if ('' === $body) {
				return (object) [];
			}

			$decoded = json_decode($body);

			if (JSON_ERROR_NONE !== json_last_error()) {
				return new \WP_Error(
					'hostinger-invalid-json',
					sprintf(
						/* translators: %s: JSON decoding error message. */
						__('Hostinger API returned a malformed JSON response: %s', 'ultimate-multisite'),
						json_last_error_msg()
					)
				);
			}

			return $decoded;
		}

		$message = wp_remote_retrieve_response_message($response);
		$detail  = $body;

		// Hostinger error bodies usually contain a `message` field; surface it for clarity.
		$decoded = json_decode($body, true);
		if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
			$detail = $decoded['message'];
		}

		return new \WP_Error(
			'hostinger-http-error',
			sprintf(
				/* translators: 1: HTTP status code, 2: response status message, 3: response body or error detail. */
				__('Hostinger API error (%1$d %2$s): %3$s', 'ultimate-multisite'),
				$status_code,
				(string) $message,
				(string) $detail
			),
			[
				'status' => $status_code,
				'body'   => $body,
			]
		);
	}
}
