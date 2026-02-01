<?php
/**
 * Closte Integration.
 *
 * Shared Closte integration providing API access for domain mapping
 * and multi-tenancy capabilities.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Closte;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Closte integration provider.
 *
 * @since 2.5.0
 */
class Closte_Integration extends Integration {

	/**
	 * Closte API base URL.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $api_base_url = 'https://app.closte.com/api/client';

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('closte', 'Closte');

		$this->set_description(__('Closte serverless hosting integration with automatic domain mapping and SSL.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('closte.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/closte');
		$this->set_constants(['CLOSTE_CLIENT_API_KEY']);
		$this->set_supports(['autossl', 'no-instructions', 'no-config']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return (bool) $this->get_credential('CLOSTE_CLIENT_API_KEY');
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$response = $this->api_call('/adddomainalias', []);

		if (is_wp_error($response)) {
			return $response;
		}

		if (($response['error'] ?? '') === 'Invalid or empty domain: ') {
			return true;
		}

		return new \WP_Error('not-auth', __('Something went wrong', 'ultimate-multisite'));
	}

	/**
	 * Sends a request to the Closte API.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint Endpoint to send the call to.
	 * @param array  $data     Array containing the params.
	 * @return array|\WP_Error
	 */
	public function api_call(string $endpoint, array $data) {

		$api_key = $this->get_credential('CLOSTE_CLIENT_API_KEY');

		if (empty($api_key)) {
			return new \WP_Error('missing-key', __('Closte API Key not found.', 'ultimate-multisite'));
		}

		$response = wp_remote_post(
			$this->api_base_url . $endpoint,
			[
				'blocking' => true,
				'timeout'  => 45,
				'headers'  => [
					'Content-Type' => 'application/x-www-form-urlencoded',
					'User-Agent'   => 'WP-Ultimo-Closte-Integration/2.5',
				],
				'body'     => array_merge(['apikey' => $api_key], $data),
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if ($code >= 400) {
			return [
				'success' => false,
				'error'   => sprintf('HTTP %d error', $code),
			];
		}

		if ($body) {
			$decoded = json_decode($body, true);

			if (json_last_error() === JSON_ERROR_NONE) {
				return $decoded;
			}
		}

		return [
			'success' => false,
			'error'   => 'Empty or invalid response',
		];
	}
}
