<?php
/**
 * Cloudways Integration.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/Cloudways
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Cloudways;

use WP_Ultimo\Integrations\Integration;

defined('ABSPATH') || exit;

/**
 * Cloudways integration provider.
 *
 * @since 2.5.0
 */
class Cloudways_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('cloudways', 'Cloudways');

		$this->set_description(__('Focus on your business and avoid all the web hosting hassles. Our managed hosting guarantees unmatched performance, reliability and choice with 24/7 support that acts as your extended team, making Cloudways an ultimate choice for growing agencies and e-commerce businesses.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('cloudways.webp', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/cloudways');
		$this->set_constants(
			[
				'WU_CLOUDWAYS_EMAIL',
				'WU_CLOUDWAYS_API_KEY',
				'WU_CLOUDWAYS_SERVER_ID',
				'WU_CLOUDWAYS_APP_ID',
			]
		);
		$this->set_optional_constants(['WU_CLOUDWAYS_EXTRA_DOMAINS']);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return str_contains(ABSPATH, 'cloudways');
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$response = $this->send_cloudways_request('/app/manage/fpm_setting', [], 'GET');

		if (is_wp_error($response)) {
			return $response;
		}

		if (isset($response->error)) {
			return new \WP_Error('cloudways-error', $response->error);
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_CLOUDWAYS_EMAIL'         => [
				'title'       => __('Cloudways Account Email', 'ultimate-multisite'),
				'desc'        => __('Your Cloudways account email address.', 'ultimate-multisite'),
				'placeholder' => __('e.g. me@gmail.com', 'ultimate-multisite'),
			],
			'WU_CLOUDWAYS_API_KEY'       => [
				'title'       => __('Cloudways API Key', 'ultimate-multisite'),
				'desc'        => __('The API Key retrieved in the previous step.', 'ultimate-multisite'),
				'placeholder' => __('e.g. eYP0Jo3Fzzm5SOZCi5nLR0Mki2lbYZ', 'ultimate-multisite'),
			],
			'WU_CLOUDWAYS_SERVER_ID'     => [
				'title'       => __('Cloudways Server ID', 'ultimate-multisite'),
				'desc'        => __('The Server ID retrieved in the previous step.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 11667', 'ultimate-multisite'),
			],
			'WU_CLOUDWAYS_APP_ID'        => [
				'title'       => __('Cloudways App ID', 'ultimate-multisite'),
				'desc'        => __('The App ID retrieved in the previous step.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 940288', 'ultimate-multisite'),
			],
			'WU_CLOUDWAYS_EXTRA_DOMAINS' => [
				'title'       => __('Cloudways Extra Domains', 'ultimate-multisite'),
				'tooltip'     => __('The Cloudways API is a bit strange in that it doesn\'t offer a way to add or remove just one domain, only a way to update the whole domain list. That means that Ultimate Multisite will replace all domains you might have there with the list of mapped domains of the network every time a new domain is added.', 'ultimate-multisite'),
				'desc'        => __('Comma-separated list of additional domains to add to Cloudways.', 'ultimate-multisite'),
				'placeholder' => __('e.g. *.test.com, test.com', 'ultimate-multisite'),
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

		wu_get_template('wizards/host-integrations/cloudways-instructions');
	}

	/**
	 * Fetches and saves a Cloudways access token.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_cloudways_access_token(): string {

		$token = get_site_transient('wu_cloudways_token');

		if ( ! $token) {
			$response = wp_remote_post(
				'https://api.cloudways.com/api/v1/oauth/access_token',
				[
					'blocking' => true,
					'method'   => 'POST',
					'headers'  => [
						'cache-control' => 'no-cache',
						'content-type'  => 'application/x-www-form-urlencoded',
					],
					'body'     => [
						'email'   => $this->get_credential('WU_CLOUDWAYS_EMAIL'),
						'api_key' => $this->get_credential('WU_CLOUDWAYS_API_KEY'),
					],
				]
			);

			if ( ! is_wp_error($response)) {
				$body = json_decode(wp_remote_retrieve_body($response), true);

				if (isset($body['access_token'])) {
					$expires_in = $body['expires_in'] ?? 50 * MINUTE_IN_SECONDS;

					set_site_transient('wu_cloudways_token', $body['access_token'], $expires_in);

					$token = $body['access_token'];
				}
			}
		}

		return $token ?: '';
	}

	/**
	 * Sends a request to the Cloudways API.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data The data to send.
	 * @param string $method The HTTP verb.
	 * @return object|\WP_Error
	 */
	public function send_cloudways_request(string $endpoint, array $data = [], string $method = 'POST') {

		$token = $this->get_cloudways_access_token();

		$endpoint = '/' . ltrim($endpoint, '/');

		$endpoint_url = "https://api.cloudways.com/api/v1$endpoint";

		if ('GET' === $method) {
			$endpoint_url = add_query_arg(
				[
					'server_id' => $this->get_credential('WU_CLOUDWAYS_SERVER_ID'),
					'app_id'    => $this->get_credential('WU_CLOUDWAYS_APP_ID'),
				],
				$endpoint_url
			);
		} else {
			$data['server_id'] = $this->get_credential('WU_CLOUDWAYS_SERVER_ID');
			$data['app_id']    = $this->get_credential('WU_CLOUDWAYS_APP_ID');
			$data['ssl_email'] = $this->get_credential('WU_CLOUDWAYS_EMAIL');
			$data['wild_card'] = false;
		}

		$response = wp_remote_post(
			$endpoint_url,
			[
				'blocking' => true,
				'method'   => $method,
				'timeout'  => 45,
				'body'     => $data,
				'headers'  => [
					'cache-control' => 'no-cache',
					'content-type'  => 'application/x-www-form-urlencoded',
					'authorization' => "Bearer $token",
				],
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$response_data = wp_remote_retrieve_body($response);

		return json_decode($response_data);
	}
}
