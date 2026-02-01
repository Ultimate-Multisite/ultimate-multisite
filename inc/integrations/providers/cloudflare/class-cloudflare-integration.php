<?php
/**
 * Cloudflare Integration.
 *
 * Cloudflare integration providing API access for DNS management.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Cloudflare;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Cloudflare integration provider.
 *
 * @since 2.5.0
 */
class Cloudflare_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('cloudflare', 'Cloudflare');

		$this->set_description(__('Cloudflare secures and ensures the reliability of your external-facing resources such as websites, APIs, and applications. It protects your internal resources such as behind-the-firewall applications, teams, and devices. And it is your platform for developing globally-scalable applications.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('cloudflare.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/cloudflare');
		$this->set_constants(['WU_CLOUDFLARE_API_KEY', 'WU_CLOUDFLARE_ZONE_ID']);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		/**
		 * As Cloudflare recently enabled wildcards for all customers,
		 * this integration is no longer auto-detected.
		 *
		 * @since 2.1
		 */
		return false;
	}

	/**
	 * Tests the connection with the Cloudflare API.
	 *
	 * @since 2.5.0
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$results = $this->cloudflare_api_call('client/v4/user/tokens/verify');

		if (is_wp_error($results)) {
			return $results;
		}

		return true;
	}

	/**
	 * Returns the list of installation fields.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_fields(): array {

		return [
			'WU_CLOUDFLARE_ZONE_ID' => [
				'title'       => __('Zone ID', 'ultimate-multisite'),
				'placeholder' => __('e.g. 644c7705723d62e31f700bb798219c75', 'ultimate-multisite'),
			],
			'WU_CLOUDFLARE_API_KEY' => [
				'title'       => __('API Key', 'ultimate-multisite'),
				'placeholder' => __('e.g. xKGbxxVDpdcUv9dUzRf4i4ngv0QNf1wCtbehiec_o', 'ultimate-multisite'),
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

		wu_get_template('wizards/host-integrations/cloudflare-instructions');
	}

	/**
	 * Sends an API call to Cloudflare.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint The endpoint to call.
	 * @param string $method   The HTTP verb. Defaults to GET.
	 * @param array  $data     The data to send.
	 * @return object|\WP_Error
	 */
	public function cloudflare_api_call(string $endpoint = 'client/v4/user/tokens/verify', string $method = 'GET', array $data = []) {

		$api_url = 'https://api.cloudflare.com/';

		$endpoint_url = $api_url . $endpoint;

		$response = wp_remote_request(
			$endpoint_url,
			[
				'method'      => $method,
				'body'        => 'GET' === $method ? $data : wp_json_encode($data),
				'data_format' => 'body',
				'headers'     => [
					'Authorization' => sprintf('Bearer %s', $this->get_credential('WU_CLOUDFLARE_API_KEY')),
					'Content-Type'  => 'application/json',
				],
			]
		);

		if ( ! is_wp_error($response)) {
			$body = wp_remote_retrieve_body($response);

			if (wp_remote_retrieve_response_code($response) === 200) {
				return json_decode($body);
			} else {
				$error_message = wp_remote_retrieve_response_message($response);

				return new \WP_Error('cloudflare-error', sprintf('%s: %s', $error_message, $body));
			}
		}

		return $response;
	}
}
