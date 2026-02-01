<?php
/**
 * Enhance Integration.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/Enhance
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Enhance;

use WP_Ultimo\Integrations\Integration;

defined('ABSPATH') || exit;

/**
 * Enhance Control Panel integration provider.
 *
 * @since 2.5.0
 */
class Enhance_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('enhance', 'Enhance Control Panel');

		$this->set_description(__('Enhance is a modern control panel that provides powerful hosting automation and management capabilities.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('enhance.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/enhance');
		$this->set_constants(
			[
				'WU_ENHANCE_API_TOKEN',
				'WU_ENHANCE_API_URL',
				'WU_ENHANCE_ORG_ID',
				'WU_ENHANCE_WEBSITE_ID',
			]
		);
		$this->set_supports(['autossl', 'no-instructions']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return (bool) $this->get_credential('WU_ENHANCE_API_TOKEN');
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$org_id     = $this->get_credential('WU_ENHANCE_ORG_ID');
		$website_id = $this->get_credential('WU_ENHANCE_WEBSITE_ID');

		if (empty($org_id)) {
			return new \WP_Error('no-org-id', __('Organization ID is not configured', 'ultimate-multisite'));
		}

		if (empty($website_id)) {
			return new \WP_Error('no-website-id', __('Website ID is not configured', 'ultimate-multisite'));
		}

		$response = $this->send_enhance_api_request(
			'/orgs/' . $org_id . '/websites/' . $website_id
		);

		if (isset($response['id'])) {
			return true;
		}

		return new \WP_Error(
			'connection-failed',
			sprintf(
				/* translators: %s is the error message from the API */
				__('Failed to connect to Enhance API: %s', 'ultimate-multisite'),
				$response['error'] ?? __('Unknown error', 'ultimate-multisite')
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_ENHANCE_API_TOKEN'  => [
				'type'        => 'password',
				'html_attr'   => ['autocomplete' => 'new-password'],
				'title'       => __('Enhance API Token', 'ultimate-multisite'),
				'desc'        => sprintf(
					/* translators: %s is the link to the API token documentation */
					__('Generate an API token in your Enhance Control Panel under Settings &rarr; API Tokens. <a href="%s" target="_blank">Learn more</a>', 'ultimate-multisite'),
					'https://apidocs.enhance.com/#section/Authentication'
				),
				'placeholder' => __('Your bearer token', 'ultimate-multisite'),
			],
			'WU_ENHANCE_API_URL'    => [
				'title'       => __('Enhance API URL', 'ultimate-multisite'),
				'desc'        => __('The API URL of your Enhance Control Panel (e.g., https://your-enhance-server.com/api).', 'ultimate-multisite'),
				'placeholder' => __('e.g. https://your-enhance-server.com/api', 'ultimate-multisite'),
				'html_attr'   => [
					'id' => 'wu_enhance_api_url',
				],
			],
			'WU_ENHANCE_ORG_ID'     => [
				'title'       => __('Organization ID', 'ultimate-multisite'),
				'desc'        => __('The UUID of your organization. You can find this in your Enhance Control Panel URL when viewing the organization (e.g., /org/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx).', 'ultimate-multisite'),
				'placeholder' => __('e.g. xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'ultimate-multisite'),
				'html_attr'   => [
					'id' => 'wu_enhance_org_id',
				],
			],
			'WU_ENHANCE_WEBSITE_ID' => [
				'title'       => __('Website ID', 'ultimate-multisite'),
				'placeholder' => __('e.g. xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'ultimate-multisite'),
				'desc'        => __('The UUID of the website where domains should be added. You can find this in your Enhance Control Panel URL when viewing a website (e.g., /websites/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx).', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * Sends a request to the Enhance API.
	 *
	 * @since 2.5.0
	 *
	 * @param string       $endpoint API endpoint (relative to base URL).
	 * @param string       $method   HTTP method (GET, POST, DELETE, etc.).
	 * @param array|string $data     Request body data (for POST/PUT/PATCH).
	 * @return array
	 */
	public function send_enhance_api_request(string $endpoint, string $method = 'GET', $data = []): array {

		$api_token = $this->get_credential('WU_ENHANCE_API_TOKEN');

		if (empty($api_token)) {
			wu_log_add('integration-enhance', 'WU_ENHANCE_API_TOKEN constant not defined or empty');

			return [
				'success' => false,
				'error'   => 'Enhance API Token not found.',
			];
		}

		$api_base_url = $this->get_credential('WU_ENHANCE_API_URL');

		if (empty($api_base_url)) {
			wu_log_add('integration-enhance', 'WU_ENHANCE_API_URL constant not defined or empty');

			return [
				'success' => false,
				'error'   => 'Enhance API URL not found.',
			];
		}

		$api_url = rtrim($api_base_url, '/') . $endpoint;

		$args = [
			'method'  => $method,
			'timeout' => 45,
			'headers' => [
				'Authorization' => 'Bearer ' . $api_token,
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'WP-Ultimo-Enhance-Integration/2.0',
			],
		];

		// Add body for POST/PUT/PATCH methods
		if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && ! empty($data)) {
			$args['body'] = wp_json_encode($data);
		}

		wu_log_add('integration-enhance', sprintf('Making %s request to: %s', $method, $api_url));

		if (! empty($data)) {
			wu_log_add('integration-enhance', sprintf('Request data: %s', wp_json_encode($data)));
		}

		$response = wp_remote_request($api_url, $args);

		if (is_wp_error($response)) {
			wu_log_add('integration-enhance', sprintf('API request failed: %s', $response->get_error_message()));

			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		wu_log_add('integration-enhance', sprintf('API response code: %d, body: %s', $response_code, $response_body));

		// Handle successful responses
		if ($response_code >= 200 && $response_code < 300) {
			if (empty($response_body)) {
				return [
					'success' => true,
				];
			}

			$body = json_decode($response_body, true);

			if (json_last_error() === JSON_ERROR_NONE) {
				return $body;
			}

			wu_log_add('integration-enhance', sprintf('JSON decode error: %s', json_last_error_msg()));

			return [
				'success'    => false,
				'error'      => 'Invalid JSON response',
				'json_error' => json_last_error_msg(),
			];
		}

		// Handle error responses
		wu_log_add('integration-enhance', sprintf('HTTP error %d for endpoint %s', $response_code, $endpoint));

		$error_data = [
			'success'       => false,
			'error'         => sprintf('HTTP %d error', $response_code),
			'response_code' => $response_code,
			'response_body' => $response_body,
		];

		if (! empty($response_body)) {
			$error_body = json_decode($response_body, true);

			if (json_last_error() === JSON_ERROR_NONE && isset($error_body['message'])) {
				$error_data['error'] = $error_body['message'];
			}
		}

		return $error_data;
	}
}
