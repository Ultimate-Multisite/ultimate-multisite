<?php
/**
 * BigScoots Integration.
 *
 * Provides API access to the BigScoots WPO public API.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/BigScoots
 * @since 2.15.2
 */

namespace WP_Ultimo\Integrations\Providers\BigScoots;

use WP_Ultimo\Integrations\Integration;

defined('ABSPATH') || exit;

/**
 * BigScoots WPO integration provider.
 *
 * @since 2.15.2
 */
class BigScoots_Integration extends Integration {

	/**
	 * BigScoots API base URL.
	 *
	 * @since 2.15.2
	 * @var string
	 */
	public const API_BASE_URL = 'https://api.bigscoots.com';

	/**
	 * User-Agent header sent with API requests.
	 *
	 * @since 2.15.2
	 * @var string
	 */
	private const USER_AGENT = 'UltimateMultisite-BigScoots-Integration/1.0';

	/**
	 * Constructor.
	 *
	 * @since 2.15.2
	 */
	public function __construct() {

		parent::__construct('bigscoots', 'BigScoots');

		$this->set_constants(
			[
				'WU_BIGSCOOTS_API_EMAIL',
				'WU_BIGSCOOTS_API_KEY',
				'WU_BIGSCOOTS_PRIMARY_UUID',
			]
		);
		$this->set_supports(['autossl', 'no-instructions']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description() {

		return __('BigScoots provides managed WordPress hosting through its WPO platform. This integration keeps multisite domains synchronized with WPO and requests SSL certificates after DNS propagation.', 'ultimate-multisite');
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return (bool) ($this->get_credential('WU_BIGSCOOTS_API_EMAIL') && $this->get_credential('WU_BIGSCOOTS_API_KEY') && $this->get_credential('WU_BIGSCOOTS_PRIMARY_UUID'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_BIGSCOOTS_API_EMAIL'    => [
				'title'       => __('BigScoots Account Email', 'ultimate-multisite'),
				'desc'        => __('The email address used to sign in to your BigScoots WPO account.', 'ultimate-multisite'),
				'type'        => 'email',
				'placeholder' => __('you@example.com', 'ultimate-multisite'),
			],
			'WU_BIGSCOOTS_API_KEY'      => [
				'title'       => __('BigScoots API Key', 'ultimate-multisite'),
				'desc'        => __('In the WPO Portal, open Edit Profile & Security and enable API Access to generate this key.', 'ultimate-multisite'),
				'type'        => 'password',
				'placeholder' => __('Your WPO API key', 'ultimate-multisite'),
				'html_attr'   => [
					'autocomplete' => 'new-password',
				],
			],
			'WU_BIGSCOOTS_PRIMARY_UUID' => [
				'title'       => __('BigScoots Primary Site UUID', 'ultimate-multisite'),
				'desc'        => __('The primary site UUID for the multisite installation in your WPO account.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 123e4567-e89b-12d3-a456-426614174000', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * Tests the configured API credentials.
	 *
	 * @since 2.15.2
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$primary_uuid = $this->get_credential('WU_BIGSCOOTS_PRIMARY_UUID');

		if (empty($primary_uuid)) {
			return new \WP_Error('bigscoots-missing-primary-uuid', __('BigScoots primary site UUID is required.', 'ultimate-multisite'));
		}

		$response = $this->bigscoots_api_call('/v1/multi-sites/sub-sites/' . rawurlencode($primary_uuid));

		if (is_wp_error($response)) {
			return $response;
		}

		return true;
	}

	/**
	 * Sends an authenticated request to the BigScoots public API.
	 *
	 * @since 2.15.2
	 *
	 * @param string $endpoint API endpoint path.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 * @return mixed|\WP_Error Decoded response on success, or an error.
	 */
	public function bigscoots_api_call(string $endpoint, string $method = 'GET', array $data = []) {

		$email   = $this->get_credential('WU_BIGSCOOTS_API_EMAIL');
		$api_key = $this->get_credential('WU_BIGSCOOTS_API_KEY');

		if (empty($email) || empty($api_key)) {
			return new \WP_Error('bigscoots-missing-credentials', __('BigScoots account email and API key are required.', 'ultimate-multisite'));
		}

		if ('' === $endpoint || '/' !== $endpoint[0]) {
			$endpoint = '/' . $endpoint;
		}

		$method = strtoupper($method);
		$url    = self::API_BASE_URL . $endpoint;

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Accept'       => 'application/json',
				'User-Agent'   => self::USER_AGENT,
				'x-auth-email' => $email,
				'x-auth-key'   => $api_key,
			],
		];

		if ('GET' === $method) {
			if ( ! empty($data)) {
				$url = add_query_arg($data, $url);
			}
		} elseif ( ! empty($data)) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode($data);
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
					'bigscoots-invalid-json',
					sprintf(
						/* translators: %s: JSON decoding error message. */
						__('BigScoots API returned malformed JSON: %s', 'ultimate-multisite'),
						json_last_error_msg()
					)
				);
			}

			return $decoded;
		}

		return new \WP_Error(
			'bigscoots-http-error',
			sprintf(
				/* translators: 1: HTTP status code, 2: API error message. */
				__('BigScoots API error (%1$d): %2$s', 'ultimate-multisite'),
				$status_code,
				$this->get_api_error_message($body, wp_remote_retrieve_response_message($response))
			),
			['status' => $status_code]
		);
	}

	/**
	 * Extracts a safe error message from an API response.
	 *
	 * @since 2.15.2
	 *
	 * @param string $body     Response body.
	 * @param string $fallback HTTP response message.
	 * @return string
	 */
	private function get_api_error_message(string $body, string $fallback): string {

		$decoded = json_decode($body, true);

		if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
			return $decoded['message'];
		}

		if (is_array($decoded) && isset($decoded['response']['message']) && is_string($decoded['response']['message'])) {
			return $decoded['response']['message'];
		}

		return $fallback ?: __('Unknown API error.', 'ultimate-multisite');
	}
}
