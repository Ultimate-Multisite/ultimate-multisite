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
	 * Current GridPane API base URL.
	 *
	 * @since 2.5.1
	 * @var string
	 */
	private const API_BASE_URL = 'https://my.gridpane.com/oauth/api/v1/';

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('gridpane', 'GridPane');

		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('gridpane.webp', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/gridpane');
		$this->set_constants(
			[
				['WU_GRIDPANE_API_TOKEN', 'WU_GRIDPANE_API_KEY'],
			]
		);
		$this->set_optional_constants(
			[
				'WU_GRIDPANE_SITE_ID',
				'WU_GRIDPANE_APP_ID',
				'WU_GRIDPANE_SERVER_ID',
				'WU_GRIDPANE_DNS_MANAGEMENT',
				'WU_GRIDPANE_DNS_INTEGRATION_ID',
			]
		);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {

		return __('Connect Ultimate Multisite to GridPane to synchronize mapped domains and provision SSL certificates.', 'ultimate-multisite');
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return (defined('GRIDPANE') && GRIDPANE) || '' !== $this->get_api_token();
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
	 * Missing site and server IDs are discovered from the current network host
	 * and stored in the encrypted integration credential store.
	 *
	 * @since 2.5.0
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$configuration = $this->get_site_configuration();

		if (is_wp_error($configuration)) {
			return $configuration;
		}

		$result = $this->send_gridpane_api_request('site/' . $configuration['site_id'], [], 'GET');

		if (is_wp_error($result)) {
			return $result;
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
			'WU_GRIDPANE_API_TOKEN'          => [
				'title'       => __('GridPane API Token', 'ultimate-multisite'),
				'desc'        => __('Create a token in GridPane under Settings → GridPane API. Ultimate Multisite encrypts the token in the network database.', 'ultimate-multisite'),
				'type'        => 'password',
				'placeholder' => __('Bearer token', 'ultimate-multisite'),
				'html_attr'   => [
					'autocomplete' => 'new-password',
				],
			],
			'WU_GRIDPANE_SITE_ID'            => [
				'title'       => __('GridPane Site ID', 'ultimate-multisite'),
				'desc'        => __('Leave empty to discover the site matching this network during the connection test.', 'ultimate-multisite'),
				'type'        => 'text',
				'placeholder' => __('Automatically discovered', 'ultimate-multisite'),
			],
			'WU_GRIDPANE_SERVER_ID'          => [
				'title'       => __('GridPane Server ID', 'ultimate-multisite'),
				'desc'        => __('Leave empty to discover the server from the selected GridPane site.', 'ultimate-multisite'),
				'type'        => 'text',
				'placeholder' => __('Automatically discovered', 'ultimate-multisite'),
			],
			'WU_GRIDPANE_DNS_MANAGEMENT'     => [
				'title'   => __('GridPane DNS Management', 'ultimate-multisite'),
				'desc'    => __('Choose a DNS integration only when mapped domains use an account connected to GridPane.', 'ultimate-multisite'),
				'type'    => 'select',
				'options' => [
					'none_none'            => __('Do not manage DNS', 'ultimate-multisite'),
					'cloudflare_full'      => __('Cloudflare (full)', 'ultimate-multisite'),
					'cloudflare_challenge' => __('Cloudflare (challenge)', 'ultimate-multisite'),
					'dnsme_full'           => __('DNS Made Easy (full)', 'ultimate-multisite'),
					'dnsme_challenge'      => __('DNS Made Easy (challenge)', 'ultimate-multisite'),
				],
			],
			'WU_GRIDPANE_DNS_INTEGRATION_ID' => [
				'title'       => __('GridPane DNS Integration ID', 'ultimate-multisite'),
				'desc'        => __('Required when GridPane DNS management uses any connected DNS integration.', 'ultimate-multisite'),
				'type'        => 'text',
				'placeholder' => __('Optional DNS integration ID', 'ultimate-multisite'),
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

		wu_get_template('wizards/host-integrations/gridpane-instructions');
	}

	/**
	 * Returns the configured bearer token, including the legacy key alias.
	 *
	 * @since 2.5.1
	 * @return string
	 */
	public function get_api_token() {

		return $this->get_credential('WU_GRIDPANE_API_TOKEN') ?: $this->get_credential('WU_GRIDPANE_API_KEY');
	}

	/**
	 * Returns or discovers the GridPane site and server IDs.
	 *
	 * @since 2.5.1
	 * @return array|\WP_Error
	 */
	public function get_site_configuration() {

		$site_id   = $this->get_credential('WU_GRIDPANE_SITE_ID') ?: $this->get_credential('WU_GRIDPANE_APP_ID');
		$server_id = $this->get_credential('WU_GRIDPANE_SERVER_ID');

		if (ctype_digit((string) $site_id) && ctype_digit((string) $server_id)) {
			return [
				'site_id'   => (int) $site_id,
				'server_id' => (int) $server_id,
			];
		}

		return $this->discover_site_configuration();
	}

	/**
	 * Discovers the GridPane site matching the current network hostname.
	 *
	 * @since 2.5.1
	 * @return array|\WP_Error
	 */
	public function discover_site_configuration() {

		$sites = $this->fetch_all('site');

		if (is_wp_error($sites)) {
			return $sites;
		}

		$network_host = strtolower((string) wp_parse_url(network_site_url(), PHP_URL_HOST));
		$matches      = [];

		foreach ($sites as $site) {
			$site_host = strtolower((string) wp_parse_url($site['url'] ?? '', PHP_URL_HOST));

			if ('' === $site_host) {
				$site_host = strtolower(trim((string) ($site['url'] ?? ''), '/'));
			}

			if ('' !== $network_host && $network_host === $site_host) {
				$matches[] = $site;
			}
		}

		if (1 === count($sites) && empty($matches)) {
			$matches = $sites;
		}

		if (1 !== count($matches) || empty($matches[0]['id']) || empty($matches[0]['server_id'])) {
			return new \WP_Error(
				'gridpane-site-not-found',
				__('Could not uniquely match this network to a GridPane site. Enter the GridPane site and server IDs manually.', 'ultimate-multisite')
			);
		}

		$configuration = [
			'site_id'   => (int) $matches[0]['id'],
			'server_id' => (int) $matches[0]['server_id'],
		];

		$this->save_discovered_configuration($configuration);

		return $configuration;
	}

	/**
	 * Fetches all pages from a GridPane list endpoint.
	 *
	 * @since 2.5.1
	 *
	 * @param string $endpoint   Endpoint path.
	 * @param string $collection Optional nested collection key.
	 * @return array|\WP_Error
	 */
	public function fetch_all(string $endpoint, string $collection = '') {

		$items = [];
		$next  = $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . 'per_page=200';

		for ($page = 0; $page < 100 && $next; $page++) {
			$response = $this->send_gridpane_api_request($next, [], 'GET');

			if (is_wp_error($response)) {
				return $response;
			}

			$data = $response['data'] ?? [];

			if ('' !== $collection) {
				$data = $data[ $collection ] ?? [];
			}

			if (is_array($data)) {
				$items = array_merge($items, $data);
			}

			$next = $this->normalize_next_endpoint($response['links']['next'] ?? '');
		}

		if ($next) {
			return new \WP_Error(
				'gridpane-pagination-limit',
				__('GridPane returned too many paginated results to process safely.', 'ultimate-multisite')
			);
		}

		return $items;
	}

	/**
	 * Sends a request to the current GridPane bearer-token API.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint The endpoint to hit.
	 * @param array  $data     Query parameters for GET or JSON body data for writes.
	 * @param string $method   The HTTP method.
	 * @return array|\WP_Error
	 */
	public function send_gridpane_api_request(string $endpoint, array $data = [], string $method = 'POST') {

		$token = $this->get_api_token();

		if ('' === $token) {
			return new \WP_Error('gridpane-no-token', __('GridPane API token is not configured.', 'ultimate-multisite'));
		}

		$endpoint = $this->normalize_next_endpoint($endpoint);

		if ('' === $endpoint) {
			return new \WP_Error('gridpane-invalid-endpoint', __('GridPane returned an invalid API endpoint.', 'ultimate-multisite'));
		}

		$method = strtoupper($method);
		$url    = self::API_BASE_URL . ltrim($endpoint, '/');
		$args   = [
			'method'  => $method,
			'timeout' => 45,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			],
		];

		if ('GET' === $method) {
			if ( ! empty($data)) {
				$url = add_query_arg($data, $url);
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
		$decoded     = '' === $body ? [] : json_decode($body, true);

		if ('' !== $body && JSON_ERROR_NONE !== json_last_error()) {
			return new \WP_Error('gridpane-invalid-json', __('GridPane returned a malformed API response.', 'ultimate-multisite'));
		}

		if ($status_code >= 200 && $status_code < 300) {
			return is_array($decoded) ? $decoded : [];
		}

		$message     = is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : wp_remote_retrieve_response_message($response);
		$retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');

		return new \WP_Error(
			'gridpane-http-error',
			sprintf(
				/* translators: 1: HTTP status code, 2: GridPane error message. */
				__('GridPane API error (%1$d): %2$s', 'ultimate-multisite'),
				$status_code,
				$message
			),
			[
				'status'      => $status_code,
				'retry_after' => $retry_after,
				'body'        => $decoded,
			]
		);
	}

	/**
	 * Stores discovered identifiers without writing server configuration files.
	 *
	 * @since 2.5.1
	 *
	 * @param array $configuration Discovered site and server IDs.
	 * @return void
	 */
	private function save_discovered_configuration(array $configuration): void {

		parent::save_credentials(
			[
				'WU_GRIDPANE_API_TOKEN'          => $this->get_api_token(),
				'WU_GRIDPANE_SITE_ID'            => (string) $configuration['site_id'],
				'WU_GRIDPANE_SERVER_ID'          => (string) $configuration['server_id'],
				'WU_GRIDPANE_DNS_MANAGEMENT'     => $this->get_credential('WU_GRIDPANE_DNS_MANAGEMENT'),
				'WU_GRIDPANE_DNS_INTEGRATION_ID' => $this->get_credential('WU_GRIDPANE_DNS_INTEGRATION_ID'),
			]
		);
	}

	/**
	 * Converts GridPane pagination URLs to safe API-relative endpoint paths.
	 *
	 * @since 2.5.1
	 *
	 * @param string $endpoint Endpoint path or GridPane pagination URL.
	 * @return string
	 */
	private function normalize_next_endpoint(string $endpoint): string {

		if ('' === $endpoint) {
			return '';
		}

		if (str_starts_with($endpoint, self::API_BASE_URL)) {
			return substr($endpoint, strlen(self::API_BASE_URL));
		}

		if (str_contains($endpoint, '://')) {
			return '';
		}

		return ltrim($endpoint, '/');
	}
}
