<?php
/**
 * ServerPilot Integration.
 *
 * Shared ServerPilot integration providing API access for domain mapping
 * and other capabilities.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\ServerPilot;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * ServerPilot integration provider.
 *
 * @since 2.5.0
 */
class ServerPilot_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('serverpilot', 'ServerPilot');

		$this->set_description(__('ServerPilot is a cloud service for hosting WordPress and other PHP websites on servers at DigitalOcean, Amazon, Google, or any other server provider. You can think of ServerPilot as a modern, centralized hosting control panel.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('serverpilot.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/serverpilot');
		$this->set_constants(['WU_SERVER_PILOT_CLIENT_ID', 'WU_SERVER_PILOT_API_KEY', 'WU_SERVER_PILOT_APP_ID']);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return (bool) preg_match('/\/srv\/users\/(.+)\/apps\/(.+)/', WP_ULTIMO_PLUGIN_DIR);
	}

	/**
	 * Tests the connection with the ServerPilot API.
	 *
	 * @since 2.5.0
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$response = $this->send_server_pilot_api_request('', [], 'GET');

		if (is_wp_error($response) || wu_get_isset($response, 'error')) {
			return new \WP_Error('serverpilot-error', __('Could not connect to ServerPilot.', 'ultimate-multisite'));
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
			'WU_SERVER_PILOT_CLIENT_ID' => [
				'title'       => __('ServerPilot Client ID', 'ultimate-multisite'),
				'desc'        => __('Your ServerPilot Client ID.', 'ultimate-multisite'),
				'placeholder' => __('e.g. cid_lSmjevkdoSOpasYVqm', 'ultimate-multisite'),
			],
			'WU_SERVER_PILOT_API_KEY'   => [
				'title'       => __('ServerPilot API Key', 'ultimate-multisite'),
				'desc'        => __('The API Key retrieved in the previous step.', 'ultimate-multisite'),
				'placeholder' => __('e.g. eYP0Jo3Fzzm5SOZCi5nLR0Mki2lbYZ', 'ultimate-multisite'),
			],
			'WU_SERVER_PILOT_APP_ID'    => [
				'title'       => __('ServerPilot App ID', 'ultimate-multisite'),
				'desc'        => __('The App ID retrieved in the previous step.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 940288', 'ultimate-multisite'),
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

		wu_get_template('wizards/host-integrations/serverpilot-instructions');
	}

	/**
	 * Sends a request to ServerPilot, with the right API key.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint Endpoint to send the call to.
	 * @param array  $data     Array containing the params to the call.
	 * @param string $method   HTTP Method: POST, GET, PUT, etc.
	 * @return array|\WP_Error
	 */
	public function send_server_pilot_api_request(string $endpoint, array $data = [], string $method = 'POST') {

		$post_fields = [
			'timeout'  => 45,
			'blocking' => true,
			'method'   => $method,
			'body'     => $data ? wp_json_encode($data) : [],
			'headers'  => [
				'Authorization' => 'Basic ' . base64_encode($this->get_credential('WU_SERVER_PILOT_CLIENT_ID') . ':' . $this->get_credential('WU_SERVER_PILOT_API_KEY')), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Content-Type'  => 'application/json',
			],
		];

		$response = wp_remote_request('https://api.serverpilot.io/v1/apps/' . $this->get_credential('WU_SERVER_PILOT_APP_ID') . $endpoint, $post_fields);

		if ( ! is_wp_error($response)) {
			$body = json_decode(wp_remote_retrieve_body($response), true);

			if (json_last_error() === JSON_ERROR_NONE) {
				return $body;
			}
		}

		return $response;
	}
}
