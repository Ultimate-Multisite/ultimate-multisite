<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Laravel Forge Integration.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/LaravelForge
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\LaravelForge;

use WP_Ultimo\Integrations\Integration;

defined('ABSPATH') || exit;

/**
 * Laravel Forge integration provider.
 *
 * Supports single-server, multi-server, and load-balanced setups.
 * Provisions Let's Encrypt SSL certificates and runs deploy commands.
 *
 * @since 2.5.0
 */
class LaravelForge_Integration extends Integration {

	/**
	 * Laravel Forge API base URL.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	public const API_BASE_URL = 'https://forge.laravel.com/api/v1';

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('laravel-forge', 'Laravel Forge');

		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('laravel-forge.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://github.com/superdav42/wp-multisite-waas/wiki/Laravel-Forge-Integration');
		$this->set_constants(
			[
				'WU_FORGE_API_TOKEN',
				'WU_FORGE_SERVER_ID',
				'WU_FORGE_SITE_ID',
			]
		);
		$this->set_optional_constants(
			[
				'WU_FORGE_LOAD_BALANCER_SERVER_ID',
				'WU_FORGE_LOAD_BALANCER_SITE_ID',
				'WU_FORGE_ADDITIONAL_SERVER_IDS',
				'WU_FORGE_DEPLOY_COMMAND',
				'WU_FORGE_SYMLINK_TARGET',
			]
		);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {

		return __('Laravel Forge is a server management tool for PHP applications. This integration automatically adds mapped domains to your Forge servers, configures load balancing, and provisions SSL certificates.', 'ultimate-multisite');
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return str_contains(ABSPATH, '/home/forge/') ||
			(defined('LARAVEL_FORGE') && LARAVEL_FORGE);
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$server_id = $this->get_primary_server_id();

		if (! $server_id) {
			return new \WP_Error('missing_server_id', __('Server ID is not configured.', 'ultimate-multisite'));
		}

		$response = $this->send_forge_request(sprintf('/servers/%s', $server_id), [], 'GET');

		if (is_wp_error($response)) {
			return $response;
		}

		$body = $this->parse_response($response);

		if (is_wp_error($body)) {
			return $body;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_FORGE_API_TOKEN'               => [
				'title'       => __('Laravel Forge API Token', 'ultimate-multisite'),
				'desc'        => __('Create an API token in your Forge account under Account Settings > API.', 'ultimate-multisite'),
				'placeholder' => __('e.g. eyJ0eXAiOiJKV1QiLCJhbGci...', 'ultimate-multisite'),
				'type'        => 'password',
				'html_attr'   => [
					'autocomplete' => 'new-password',
				],
			],
			'WU_FORGE_SERVER_ID'               => [
				'title'       => __('Primary Server ID', 'ultimate-multisite'),
				'desc'        => __('The ID of your primary Forge server. Find this in the URL when viewing your server.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 847175', 'ultimate-multisite'),
			],
			'WU_FORGE_SITE_ID'                 => [
				'title'       => __('Primary Site ID', 'ultimate-multisite'),
				'desc'        => __('The ID of your WordPress site on Forge. Find this in the URL when viewing your site.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 12345678', 'ultimate-multisite'),
			],
			'WU_FORGE_LOAD_BALANCER_SERVER_ID' => [
				'title'       => __('Load Balancer Server ID (Optional)', 'ultimate-multisite'),
				'desc'        => __('If using a load balancer, enter its server ID.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 847175', 'ultimate-multisite'),
			],
			'WU_FORGE_LOAD_BALANCER_SITE_ID'   => [
				'title'       => __('Load Balancer Site ID (Optional)', 'ultimate-multisite'),
				'desc'        => __('If using a load balancer, enter the site ID on the load balancer.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 12345678', 'ultimate-multisite'),
			],
			'WU_FORGE_ADDITIONAL_SERVER_IDS'   => [
				'title'       => __('Additional Server IDs (Optional)', 'ultimate-multisite'),
				'desc'        => __('Comma-separated list of additional server IDs for multi-server setups.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 847176,847177', 'ultimate-multisite'),
			],
			'WU_FORGE_DEPLOY_COMMAND'          => [
				'title'       => __('Deploy Command (Optional)', 'ultimate-multisite'),
				'desc'        => __('Command to run after domain is added. Use {domain} as placeholder.', 'ultimate-multisite'),
				'placeholder' => __('e.g. cd /home/forge/{domain} && git pull', 'ultimate-multisite'),
			],
			'WU_FORGE_SYMLINK_TARGET'          => [
				'title'       => __('Symlink Target (Optional)', 'ultimate-multisite'),
				'desc'        => __('Path to symlink new domain sites to. Use {domain} as placeholder for the new domain.', 'ultimate-multisite'),
				'placeholder' => __('e.g. /home/forge/main-site.com/public', 'ultimate-multisite'),
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

		wu_get_template('wizards/host-integrations/laravel-forge-instructions');
	}

	/**
	 * Gets the primary server ID.
	 *
	 * @since 2.5.0
	 * @return int
	 */
	public function get_primary_server_id(): int {

		return (int) $this->get_credential('WU_FORGE_SERVER_ID');
	}

	/**
	 * Gets the primary site ID.
	 *
	 * @since 2.5.0
	 * @return int
	 */
	public function get_primary_site_id(): int {

		return (int) $this->get_credential('WU_FORGE_SITE_ID');
	}

	/**
	 * Gets the load balancer server ID.
	 *
	 * @since 2.5.0
	 * @return int
	 */
	public function get_load_balancer_server_id(): int {

		return (int) $this->get_credential('WU_FORGE_LOAD_BALANCER_SERVER_ID');
	}

	/**
	 * Gets the load balancer site ID.
	 *
	 * @since 2.5.0
	 * @return int
	 */
	public function get_load_balancer_site_id(): int {

		return (int) $this->get_credential('WU_FORGE_LOAD_BALANCER_SITE_ID');
	}

	/**
	 * Gets the list of all servers (load balancer + primary + additional).
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_server_list(): array {

		$servers = [];

		// Add load balancer first if configured.
		$lb_server_id = $this->get_load_balancer_server_id();
		if ($lb_server_id) {
			$servers[] = $lb_server_id;
		}

		// Add primary server.
		$primary_server_id = $this->get_primary_server_id();
		if ($primary_server_id && ! in_array($primary_server_id, $servers, true)) {
			$servers[] = $primary_server_id;
		}

		// Add additional servers.
		$additional_ids = $this->get_credential('WU_FORGE_ADDITIONAL_SERVER_IDS');
		if ($additional_ids) {
			$additional = array_filter(array_map('trim', explode(',', $additional_ids)));

			foreach ($additional as $server_id) {
				$server_id = (int) $server_id;

				if ($server_id && ! in_array($server_id, $servers, true)) {
					$servers[] = $server_id;
				}
			}
		}

		return $servers;
	}

	/**
	 * Gets the deploy command with {domain} placeholder replaced.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain The domain name.
	 * @return string
	 */
	public function get_deploy_command(string $domain): string {

		$deploy_command = $this->get_credential('WU_FORGE_DEPLOY_COMMAND');
		$symlink_target = $this->get_credential('WU_FORGE_SYMLINK_TARGET');

		if ($deploy_command) {
			// Validate domain to prevent shell command injection via metacharacters,
			// mirroring the symlink branch below.
			if (! preg_match('/^[a-z0-9][a-z0-9\-\.]*[a-z0-9]$/i', $domain)) {
				wu_log_add(
					'integration-forge',
					sprintf('Invalid domain format rejected for shell command: %s', $domain),
					\Psr\Log\LogLevel::ERROR
				);

				return '';
			}

			return str_replace('{domain}', $domain, $deploy_command);
		}

		if ($symlink_target) {
			// Validate domain to prevent shell command injection via metacharacters.
			if (! preg_match('/^[a-z0-9][a-z0-9\-\.]*[a-z0-9]$/i', $domain)) {
				wu_log_add(
					'integration-forge',
					sprintf('Invalid domain format rejected for shell command: %s', $domain),
					\Psr\Log\LogLevel::ERROR
				);

				return '';
			}

			$target = str_replace('{domain}', $domain, $symlink_target);

			return sprintf(
				'rm -rf %s && ln -s %s %s',
				escapeshellarg('/home/forge/' . $domain . '/*'),
				escapeshellarg($target),
				escapeshellarg('/home/forge/' . $domain . '/public')
			);
		}

		return '';
	}

	/**
	 * Sends a request to the Laravel Forge API.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint The API endpoint (without base URL).
	 * @param array  $data     The data to send.
	 * @param string $method   The HTTP method.
	 * @return array|\WP_Error
	 */
	public function send_forge_request(string $endpoint, array $data = [], string $method = 'POST') {

		$token = $this->get_credential('WU_FORGE_API_TOKEN');

		if (empty($token)) {
			return new \WP_Error(
				'missing_token',
				__('Laravel Forge API token is not configured.', 'ultimate-multisite')
			);
		}

		$url = self::API_BASE_URL . '/' . ltrim($endpoint, '/');

		$args = [
			'method'  => $method,
			'timeout' => 60,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			],
		];

		if ('GET' === $method) {
			if (! empty($data)) {
				$url = add_query_arg($data, $url);
			}
		} else {
			$args['body'] = wp_json_encode($data);
		}

		$response = wp_remote_request($url, $args);

		// Log the request — omit response body and query string to avoid leaking sensitive values.
		$endpoint_path = preg_replace('/\?.*$/', '', wp_parse_url($url, PHP_URL_PATH));
		$status        = is_wp_error($response) ? 'ERROR' : wp_remote_retrieve_response_code($response);

		wu_log_add(
			'integration-forge',
			sprintf('Request: %s %s | Status: %s', $method, $endpoint_path, $status)
		);

		return $response;
	}

	/**
	 * Parses an API response body.
	 *
	 * @since 2.5.0
	 *
	 * @param array|\WP_Error $response The HTTP response.
	 * @return array|\WP_Error
	 */
	public function parse_response($response) {

		if (is_wp_error($response)) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body        = wp_remote_retrieve_body($response);
		$decoded     = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new \WP_Error(
				'json_error',
				// translators: %s is the JSON error message.
				sprintf(__('Invalid JSON response: %s', 'ultimate-multisite'), json_last_error_msg())
			);
		}

		if ($status_code >= 400) {
			$error_message = isset($decoded['message']) ? $decoded['message'] : __('Unknown API error', 'ultimate-multisite');

			return new \WP_Error('api_error', $error_message);
		}

		return $decoded;
	}
}
