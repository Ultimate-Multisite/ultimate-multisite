<?php
/**
 * Rocket.net Integration.
 *
 * Shared Rocket.net integration providing API access for domain mapping.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Rocket;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Rocket.net integration provider.
 *
 * @since 2.5.0
 */
class Rocket_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('rocket', 'Rocket.net');

		$this->set_description(__('Rocket.net is a fully API-driven managed WordPress hosting platform built for speed, security, and scalability. With edge-first private cloud infrastructure and automatic SSL management, Rocket.net makes it easy to deploy and manage WordPress sites at scale.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('rocket.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/rocket');
		$this->set_constants(['WU_ROCKET_EMAIL', 'WU_ROCKET_PASSWORD', 'WU_ROCKET_SITE_ID']);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return str_contains(ABSPATH, 'rocket.net') || str_contains(ABSPATH, 'rocketdotnet');
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$response = $this->send_rocket_request('domains', [], 'GET');

		if (is_wp_error($response)) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);

		if (200 === $response_code) {
			return true;
		}

		return new \WP_Error(
			'rocket-connection-failed',
			sprintf(
				/* translators: %1$d: HTTP response code, %2$s: response body. */
				__('Connection failed with HTTP code %1$d: %2$s', 'ultimate-multisite'),
				$response_code,
				wp_remote_retrieve_body($response)
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_ROCKET_EMAIL'    => [
				'title'       => __('Rocket.net Account Email', 'ultimate-multisite'),
				'desc'        => __('Your Rocket.net account email address.', 'ultimate-multisite'),
				'placeholder' => __('e.g. me@example.com', 'ultimate-multisite'),
				'type'        => 'email',
			],
			'WU_ROCKET_PASSWORD' => [
				'title'       => __('Rocket.net Password', 'ultimate-multisite'),
				'desc'        => __('Your Rocket.net account password.', 'ultimate-multisite'),
				'placeholder' => __('Enter your password', 'ultimate-multisite'),
				'type'        => 'password',
			],
			'WU_ROCKET_SITE_ID'  => [
				'title'       => __('Rocket.net Site ID', 'ultimate-multisite'),
				'desc'        => __('The Site ID from your Rocket.net control panel.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 12345', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * Renders the instructions content.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function get_instructions(): void {

		wu_get_template('wizards/host-integrations/rocket-instructions');
	}

	/**
	 * Returns the base domain API URL for Rocket.net calls.
	 *
	 * @since 2.5.0
	 *
	 * @param string $path Path relative to the main endpoint.
	 * @return string
	 */
	public function get_rocket_base_url(string $path = ''): string {

		$site_id = $this->get_credential('WU_ROCKET_SITE_ID');

		$base_url = "https://api.rocket.net/v1/sites/{$site_id}";

		if ($path) {
			$base_url .= '/' . ltrim($path, '/');
		}

		return $base_url;
	}

	/**
	 * Fetches and caches a Rocket.net JWT access token.
	 *
	 * @since 2.5.0
	 * @return string|false
	 */
	public function get_rocket_access_token() {

		$token = get_site_transient('wu_rocket_token');

		if (! $token) {
			$response = wp_remote_post(
				'https://api.rocket.net/v1/auth/login',
				[
					'blocking' => true,
					'method'   => 'POST',
					'timeout'  => 30,
					'headers'  => [
						'Content-Type' => 'application/json',
						'Accept'       => 'application/json',
					],
					'body'     => wp_json_encode(
						[
							'email'    => $this->get_credential('WU_ROCKET_EMAIL'),
							'password' => $this->get_credential('WU_ROCKET_PASSWORD'),
						]
					),
				]
			);

			if (! is_wp_error($response)) {
				$body = json_decode(wp_remote_retrieve_body($response), true);

				if (isset($body['token']) || isset($body['access_token'])) {
					$token = $body['token'] ?? $body['access_token'];

					set_site_transient('wu_rocket_token', $token, 50 * MINUTE_IN_SECONDS);
				} else {
					wu_log_add('integration-rocket', '[Auth] Failed to retrieve token: ' . wp_remote_retrieve_body($response), \Psr\Log\LogLevel::ERROR);

					return false;
				}
			} else {
				wu_log_add('integration-rocket', '[Auth] ' . $response->get_error_message(), \Psr\Log\LogLevel::ERROR);

				return false;
			}
		}

		return $token;
	}

	/**
	 * Sends a request to the Rocket.net API.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint The API endpoint (relative to /sites/{id}/).
	 * @param array  $data     The data to send.
	 * @param string $method   The HTTP verb.
	 * @return array|\WP_Error
	 */
	public function send_rocket_request(string $endpoint, array $data = [], string $method = 'POST') {

		$token = $this->get_rocket_access_token();

		if (! $token) {
			return new \WP_Error('no_token', __('Failed to authenticate with Rocket.net API', 'ultimate-multisite'));
		}

		$url = $this->get_rocket_base_url($endpoint);

		$args = [
			'blocking' => true,
			'method'   => $method,
			'timeout'  => 60,
			'headers'  => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
		];

		if (! empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
			$args['body'] = wp_json_encode($data);
		}

		return wp_remote_request($url, $args);
	}
}
