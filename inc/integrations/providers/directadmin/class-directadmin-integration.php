<?php
/**
 * DirectAdmin Integration.
 *
 * Provides API access to DirectAdmin's legacy CMD_API endpoints for domain
 * pointer and subdomain management.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/DirectAdmin
 * @since 2.5.1
 */

namespace WP_Ultimo\Integrations\Providers\DirectAdmin;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * DirectAdmin integration provider.
 *
 * Uses DirectAdmin's documented legacy CMD_API interface. Authentication is
 * HTTP Basic Auth where the password value should preferably be a DirectAdmin
 * Login Key.
 *
 * @since 2.5.1
 */
class DirectAdmin_Integration extends Integration {

	/**
	 * Default DirectAdmin HTTPS port.
	 *
	 * @since 2.5.1
	 * @var string
	 */
	private const DEFAULT_PORT = '2222';

	/**
	 * User-Agent header sent with API requests.
	 *
	 * @since 2.5.1
	 * @var string
	 */
	private const USER_AGENT = 'UltimateMultisite-DirectAdmin-Integration/1.0';

	/**
	 * Constructor.
	 *
	 * @since 2.5.1
	 */
	public function __construct() {

		parent::__construct('directadmin', 'DirectAdmin');

		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('directadmin.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://docs.directadmin.com/developer/api/');
		$this->set_constants(
			[
				'WU_DIRECTADMIN_HOST',
				'WU_DIRECTADMIN_USERNAME',
				['WU_DIRECTADMIN_API_TOKEN', 'WU_DIRECTADMIN_PASSWORD'],
				'WU_DIRECTADMIN_DOMAIN',
			]
		);
		$this->set_optional_constants(['WU_DIRECTADMIN_PORT']);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {

		return __('Integrates with DirectAdmin to add and remove domain pointer aliases automatically when domains are mapped or removed.', 'ultimate-multisite');
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return (bool) ($this->get_credential('WU_DIRECTADMIN_HOST') && $this->get_credential('WU_DIRECTADMIN_USERNAME'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$response = $this->directadmin_api_request('/CMD_API_LOGIN_TEST');

		if (is_wp_error($response)) {
			return $response;
		}

		if (isset($response['raw']) && false !== stripos((string) $response['raw'], '<html')) {
			return new \WP_Error('directadmin-login-test-failed', __('DirectAdmin returned the login page instead of an API response. Check the username and Login Key or password.', 'ultimate-multisite'));
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_DIRECTADMIN_HOST'      => [
				'title'       => __('DirectAdmin Host', 'ultimate-multisite'),
				'desc'        => __('The hostname or IP address of your DirectAdmin server. Do not include the protocol or port.', 'ultimate-multisite'),
				'placeholder' => __('e.g. server.example.com', 'ultimate-multisite'),
			],
			'WU_DIRECTADMIN_PORT'      => [
				'title'       => __('DirectAdmin Port', 'ultimate-multisite'),
				'desc'        => __('The HTTPS port DirectAdmin listens on. Defaults to 2222.', 'ultimate-multisite'),
				'placeholder' => __('2222', 'ultimate-multisite'),
				'value'       => self::DEFAULT_PORT,
			],
			'WU_DIRECTADMIN_USERNAME'  => [
				'title'       => __('DirectAdmin Username', 'ultimate-multisite'),
				'desc'        => __('The DirectAdmin account username. Admins and resellers can use the DirectAdmin impersonation format, such as admin|customer.', 'ultimate-multisite'),
				'placeholder' => __('e.g. admin|customer or customer', 'ultimate-multisite'),
			],
			'WU_DIRECTADMIN_API_TOKEN' => [
				'type'        => 'password',
				'html_attr'   => [
					'autocomplete' => 'new-password',
				],
				'title'       => __('DirectAdmin Login Key (Recommended)', 'ultimate-multisite'),
				'desc'        => __('Create a DirectAdmin Login Key and use it here instead of the account password. It is sent as the password part of HTTP Basic authentication.', 'ultimate-multisite'),
				'placeholder' => __('Your DirectAdmin Login Key', 'ultimate-multisite'),
			],
			'WU_DIRECTADMIN_PASSWORD'  => [
				'type'        => 'password',
				'html_attr'   => [
					'autocomplete' => 'new-password',
				],
				'title'       => __('DirectAdmin Password (Alternative)', 'ultimate-multisite'),
				'desc'        => __('Only required when you are not using a Login Key.', 'ultimate-multisite'),
				'placeholder' => __('Your DirectAdmin password', 'ultimate-multisite'),
			],
			'WU_DIRECTADMIN_DOMAIN'    => [
				'title'       => __('Base Domain', 'ultimate-multisite'),
				'desc'        => __('The DirectAdmin domain that serves this WordPress multisite. Mapped domains will be added as alias pointers to this domain.', 'ultimate-multisite'),
				'placeholder' => __('e.g. network.example.com', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * Renders the wizard instructions partial.
	 *
	 * @since 2.5.1
	 * @return void
	 */
	public function get_instructions(): void {

		wu_get_template('wizards/host-integrations/directadmin-instructions');
	}

	/**
	 * Sends an authenticated request to a DirectAdmin CMD_API endpoint.
	 *
	 * @since 2.5.1
	 *
	 * @param string $endpoint DirectAdmin endpoint path, e.g. /CMD_API_DOMAIN_POINTER.
	 * @param string $method   HTTP method.
	 * @param array  $data     Form/query parameters.
	 * @return array|\WP_Error
	 */
	public function directadmin_api_request(string $endpoint, string $method = 'GET', array $data = []) {

		$host = $this->normalize_host($this->get_credential('WU_DIRECTADMIN_HOST'));

		if (empty($host)) {
			return new \WP_Error('directadmin-no-host', __('DirectAdmin host is not configured.', 'ultimate-multisite'));
		}

		$username   = $this->get_credential('WU_DIRECTADMIN_USERNAME');
		$api_token  = $this->get_credential('WU_DIRECTADMIN_API_TOKEN');
		$password   = $this->get_credential('WU_DIRECTADMIN_PASSWORD');
		$credential = $api_token ?: $password;

		if (empty($username) || empty($credential)) {
			return new \WP_Error('directadmin-no-auth', __('DirectAdmin username and Login Key or password are required.', 'ultimate-multisite'));
		}

		if ('' === $endpoint || '/' !== $endpoint[0]) {
			$endpoint = '/' . $endpoint;
		}

		$method = strtoupper($method);
		$port   = $this->get_credential('WU_DIRECTADMIN_PORT') ?: self::DEFAULT_PORT;
		$url    = sprintf('https://%1$s:%2$s%3$s', $host, $port, $endpoint);

		$args = [
			'method'  => $method,
			'timeout' => 45,
			'headers' => [
				'Accept'        => 'application/json, text/plain, */*',
				'Content-Type'  => 'application/x-www-form-urlencoded',
				'User-Agent'    => self::USER_AGENT,
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Authorization' => 'Basic ' . base64_encode($username . ':' . $credential),
			],
		];

		if ('GET' === $method) {
			if ( ! empty($data)) {
				$url = add_query_arg($data, $url);
			}
		} else {
			$args['body'] = $data;
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$body        = wp_remote_retrieve_body($response);

		if (200 > $status_code || 300 <= $status_code) {
			return new \WP_Error(
				'directadmin-http-error',
				sprintf(
					/* translators: 1: HTTP status code, 2: response body. */
					__('DirectAdmin API error (%1$d): %2$s', 'ultimate-multisite'),
					$status_code,
					(string) $body
				),
				[
					'status' => $status_code,
					'body'   => $body,
				]
			);
		}

		$decoded = $this->decode_directadmin_response((string) $body);

		if ($this->is_directadmin_error($decoded)) {
			return new \WP_Error(
				'directadmin-api-error',
				$this->get_directadmin_error_message($decoded),
				$decoded
			);
		}

		return $decoded;
	}

	/**
	 * Normalizes a host field submitted by users.
	 *
	 * @since 2.5.1
	 *
	 * @param string $host Hostname submitted in settings.
	 * @return string
	 */
	private function normalize_host(string $host): string {

		$host = preg_replace('#^https?://#i', '', $host);
		$host = rtrim((string) $host, "; \t\n\r\0\x0B/");
		$host = preg_replace('#:\d+$#', '', $host);

		return (string) $host;
	}

	/**
	 * Decodes DirectAdmin response bodies.
	 *
	 * @since 2.5.1
	 *
	 * @param string $body Raw response body.
	 * @return array
	 */
	private function decode_directadmin_response(string $body): array {

		$body = trim($body);

		if ('' === $body) {
			return ['success' => true];
		}

		$decoded = json_decode($body, true);

		if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
			return $decoded;
		}

		if (str_contains($body, '=') || str_contains($body, '&')) {
			$parsed = [];
			parse_str($body, $parsed);

			if ( ! empty($parsed)) {
				$parsed['raw'] = $body;

				return $parsed;
			}
		}

		$lines = array_values(array_filter(preg_split('/\R/', $body) ?: []));

		if ( ! empty($lines)) {
			return [
				'list' => $lines,
				'raw'  => $body,
			];
		}

		return ['raw' => $body];
	}

	/**
	 * Checks if a decoded DirectAdmin payload signals an error.
	 *
	 * @since 2.5.1
	 *
	 * @param array $decoded Decoded DirectAdmin response.
	 * @return bool
	 */
	private function is_directadmin_error(array $decoded): bool {

		if ( ! array_key_exists('error', $decoded)) {
			return false;
		}

		return ! in_array((string) $decoded['error'], ['', '0', 'false'], true);
	}

	/**
	 * Extracts a human-readable DirectAdmin error message.
	 *
	 * @since 2.5.1
	 *
	 * @param array $decoded Decoded DirectAdmin response.
	 * @return string
	 */
	private function get_directadmin_error_message(array $decoded): string {

		foreach (['details', 'text', 'message', 'error'] as $key) {
			if (isset($decoded[ $key ]) && is_scalar($decoded[ $key ])) {
				return (string) $decoded[ $key ];
			}
		}

		return __('Unknown DirectAdmin API error.', 'ultimate-multisite');
	}
}
