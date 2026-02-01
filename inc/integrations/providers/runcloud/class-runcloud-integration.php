<?php
/**
 * RunCloud Integration.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/RunCloud
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\RunCloud;

use WP_Ultimo\Integrations\Integration;

defined('ABSPATH') || exit;

/**
 * RunCloud integration provider.
 *
 * @since 2.5.0
 */
class RunCloud_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('runcloud', 'RunCloud');

		$this->set_description(__('With RunCloud, you don\'t need to be a Linux expert to build a website powered by DigitalOcean, AWS, or Google Cloud. Use our graphical interface and build a business on the cloud affordably.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('runcloud.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/runcloud');
		$this->set_constants(
			[
				'WU_RUNCLOUD_API_TOKEN',
				'WU_RUNCLOUD_SERVER_ID',
				'WU_RUNCLOUD_APP_ID',
			]
		);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return strpos(ABSPATH, 'runcloud') !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$response = $this->send_runcloud_request($this->get_runcloud_base_url('domains'), [], 'GET');

		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			$body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);

			return new \WP_Error('runcloud-error', $body);
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_RUNCLOUD_API_TOKEN' => [
				'title'       => __('RunCloud API Token', 'ultimate-multisite'),
				'desc'        => __('The API Token generated in RunCloud.', 'ultimate-multisite'),
				'placeholder' => __('e.g. your-api-token-here', 'ultimate-multisite'),
			],
			'WU_RUNCLOUD_SERVER_ID' => [
				'title'       => __('RunCloud Server ID', 'ultimate-multisite'),
				'desc'        => __('The Server ID retrieved in the previous step.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 11667', 'ultimate-multisite'),
			],
			'WU_RUNCLOUD_APP_ID'    => [
				'title'       => __('RunCloud App ID', 'ultimate-multisite'),
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

		wu_get_template('wizards/host-integrations/runcloud-instructions');
	}

	/**
	 * Constructs the base API URL.
	 *
	 * @since 2.5.0
	 * @param string $path Path of endpoint.
	 * @return string
	 */
	public function get_runcloud_base_url(string $path = ''): string {

		$serverid = $this->get_credential('WU_RUNCLOUD_SERVER_ID');
		$appid    = $this->get_credential('WU_RUNCLOUD_APP_ID');

		return "https://manage.runcloud.io/api/v3/servers/{$serverid}/webapps/{$appid}/{$path}";
	}

	/**
	 * Sends authenticated requests to RunCloud API.
	 *
	 * @since 2.5.0
	 * @param string $url The URL to send the request to.
	 * @param array  $data The data to send with the request.
	 * @param string $method The HTTP method to use.
	 * @return array|\WP_Error
	 */
	public function send_runcloud_request(string $url, array $data = [], string $method = 'POST') {

		$token = $this->get_credential('WU_RUNCLOUD_API_TOKEN');

		$args = [
			'timeout'     => 100,
			'redirection' => 5,
			'method'      => $method,
			'headers'     => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			],
		];

		if ('GET' === $method) {
			$url = add_query_arg($data, $url);
		} else {
			$args['body'] = wp_json_encode($data);
		}

		$response = wp_remote_request($url, $args);

		$log_message = sprintf(
			"Request: %s %s\nStatus: %s\nResponse: %s",
			$method,
			$url,
			wp_remote_retrieve_response_code($response),
			wp_remote_retrieve_body($response)
		);
		wu_log_add('integration-runcloud', $log_message);

		return $response;
	}

	/**
	 * Processes API responses.
	 *
	 * @since 2.5.0
	 * @param array|\WP_Error $response The Response.
	 * @return mixed|\WP_Error
	 */
	public function maybe_return_runcloud_body($response) {

		if (is_wp_error($response)) {
			return $response->get_error_message();
		}

		$body = json_decode(wp_remote_retrieve_body($response));

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new \WP_Error('api_error', 'Invalid JSON response: ' . json_last_error_msg());
		}

		return $body;
	}
}
