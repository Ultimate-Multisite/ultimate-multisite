<?php
/**
 * GridPane Integration.
 *
 * GridPane integration providing API access for domain mapping.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\GridPane;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * GridPane integration provider.
 *
 * @since 2.5.0
 */
class GridPane_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('gridpane', 'Gridpane');

		$this->set_description(__("GridPane is the world's first hosting control panel built exclusively for serious WordPress professionals.", 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('gridpane.webp', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/gridpane');
		$this->set_constants(['WU_GRIDPANE', 'WU_GRIDPANE_API_KEY', 'WU_GRIDPANE_APP_ID', 'WU_GRIDPANE_SERVER_ID']);
		$this->set_supports(['autossl', 'no-config']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return defined('GRIDPANE') && GRIDPANE;
	}

	/**
	 * Enables this integration.
	 *
	 * Reverts SUNRISE constant before enabling to prevent issues with GridPane.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function enable(): bool {

		\WP_Ultimo\Helpers\WP_Config::get_instance()->revert('SUNRISE');

		return parent::enable();
	}

	/**
	 * Tests the connection with the GridPane API.
	 *
	 * @since 2.5.0
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$results = $this->send_gridpane_api_request(
			'application/delete-domain',
			[
				'server_ip'  => $this->get_credential('WU_GRIDPANE_SERVER_ID'),
				'site_url'   => $this->get_credential('WU_GRIDPANE_APP_ID'),
				'domain_url' => 'test.com',
			]
		);

		if (is_wp_error($results)) {
			return new \WP_Error('gridpane-error', __('We were not able to successfully establish a connection.', 'ultimate-multisite'));
		}

		if (wu_get_isset($results, 'message') === 'This action is unauthorized.') {
			return new \WP_Error('gridpane-unauthorized', __('We were not able to successfully establish a connection.', 'ultimate-multisite'));
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

		return [];
	}

	/**
	 * Renders the instructions content.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function get_instructions(): void {

		wu_get_template('wizards/host-integrations/gridpane-instructions');
	}

	/**
	 * Sends a request to the GridPane API.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint The endpoint to hit.
	 * @param array  $data     The post body to send to the API.
	 * @param string $method   The HTTP method.
	 * @return array|\WP_Error
	 */
	public function send_gridpane_api_request(string $endpoint, array $data = [], string $method = 'POST') {

		$post_fields = [
			'timeout'  => 45,
			'blocking' => true,
			'method'   => $method,
			'body'     => array_merge(
				[
					'api_token' => $this->get_credential('WU_GRIDPANE_API_KEY'),
				],
				$data
			),
		];

		$response = wp_remote_request("https://my.gridpane.com/api/{$endpoint}", $post_fields);

		if ( ! is_wp_error($response)) {
			$body = json_decode(wp_remote_retrieve_body($response), true);

			if (json_last_error() === JSON_ERROR_NONE) {
				return $body;
			}
		}

		return $response;
	}
}
