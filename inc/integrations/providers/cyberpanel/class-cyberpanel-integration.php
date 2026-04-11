<?php
/**
 * CyberPanel Integration.
 *
 * Shared CyberPanel integration providing API access for domain mapping
 * and other capabilities.
 *
 * CyberPanel exposes a standard REST API at /api/{endpoint} where each
 * endpoint name maps to an action (e.g. verifyConn, createWebsite).
 * Authentication is via adminUser/adminPass in the JSON request body.
 *
 * CyberPanel servers typically use self-signed SSL certificates, so
 * sslverify is disabled for API calls.
 *
 * @see https://documenter.getpostman.com/view/2s8Yt1s9Pf
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/CyberPanel
 * @since 2.6.0
 */

namespace WP_Ultimo\Integrations\Providers\CyberPanel;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * CyberPanel integration provider.
 *
 * @since 2.6.0
 */
class CyberPanel_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.6.0
	 */
	public function __construct() {

		parent::__construct('cyberpanel', 'CyberPanel');

		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('cyberpanel.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/cyberpanel');
		$this->set_constants(
			[
				'WU_CYBERPANEL_HOST',
				'WU_CYBERPANEL_USERNAME',
				'WU_CYBERPANEL_PASSWORD',
			]
		);
		$this->set_optional_constants(['WU_CYBERPANEL_PORT', 'WU_CYBERPANEL_MASTER_DOMAIN']);
		$this->set_supports(['autossl']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {

		return __('Integrates with CyberPanel to automatically create websites for mapped domains and issue SSL certificates via Let\'s Encrypt.', 'ultimate-multisite');
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return false;
	}

	/**
	 * Tests the connection with the CyberPanel API.
	 *
	 * Uses the verifyConn endpoint to confirm credentials are valid.
	 *
	 * @since 2.6.0
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$response = $this->api_call('verifyConn');

		if (is_wp_error($response)) {
			return $response;
		}

		if ( ! empty($response['verifyConn'])) {
			return true;
		}

		return new \WP_Error(
			'cyberpanel-connection-failed',
			__('Could not connect to CyberPanel API.', 'ultimate-multisite')
		);
	}

	/**
	 * Returns the list of installation fields for the setup wizard.
	 *
	 * @since 2.6.0
	 * @return array
	 */
	public function get_fields(): array {

		return [
			'WU_CYBERPANEL_HOST'          => [
				'title'       => __('CyberPanel Host', 'ultimate-multisite'),
				'desc'        => __('The hostname or IP address of your CyberPanel server (e.g., server.example.com or 5.78.200.4). Do not include the port or protocol.', 'ultimate-multisite'),
				'placeholder' => __('e.g. server.example.com', 'ultimate-multisite'),
			],
			'WU_CYBERPANEL_PORT'          => [
				'title'       => __('CyberPanel Port', 'ultimate-multisite'),
				'desc'        => __('The port CyberPanel listens on. Defaults to 8090 if not set.', 'ultimate-multisite'),
				'placeholder' => __('8090', 'ultimate-multisite'),
				'value'       => '8090',
			],
			'WU_CYBERPANEL_USERNAME'      => [
				'title'       => __('CyberPanel Username', 'ultimate-multisite'),
				'desc'        => __('Your CyberPanel admin username (typically "admin").', 'ultimate-multisite'),
				'placeholder' => __('e.g. admin', 'ultimate-multisite'),
			],
			'WU_CYBERPANEL_PASSWORD'      => [
				'type'        => 'password',
				'html_attr'   => ['autocomplete' => 'new-password'],
				'title'       => __('CyberPanel Password', 'ultimate-multisite'),
				'desc'        => __('Your CyberPanel admin password.', 'ultimate-multisite'),
				'placeholder' => __('Your password', 'ultimate-multisite'),
			],
			'WU_CYBERPANEL_MASTER_DOMAIN' => [
				'title'       => __('Master Domain', 'ultimate-multisite'),
				'desc'        => __('The primary domain in CyberPanel that your WordPress multisite is served from. If not set, the network\'s current domain will be used.', 'ultimate-multisite'),
				'placeholder' => __('e.g. network.example.com', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * Returns the master domain configured for the integration.
	 *
	 * Falls back to the WordPress multisite DOMAIN_CURRENT_SITE constant
	 * if no explicit master domain is configured.
	 *
	 * @since 2.6.0
	 * @return string
	 */
	public function get_master_domain(): string {

		$domain = $this->get_credential('WU_CYBERPANEL_MASTER_DOMAIN');

		if (empty($domain)) {
			$domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : '';
		}

		return $domain;
	}

	/**
	 * Sends a request to the CyberPanel standard API.
	 *
	 * CyberPanel uses per-endpoint URLs at /api/{endpoint}. Authentication
	 * is passed as adminUser/adminPass fields in the JSON request body.
	 *
	 * @since 2.6.0
	 *
	 * @param string $endpoint The API endpoint name (e.g. 'verifyConn', 'createWebsite').
	 * @param array  $data     Additional parameters to merge into the request body.
	 * @return array|\WP_Error Decoded JSON response or WP_Error on failure.
	 */
	public function api_call(string $endpoint, array $data = []) {

		$host = $this->get_credential('WU_CYBERPANEL_HOST');

		if (empty($host)) {
			return new \WP_Error('wu_cyberpanel_no_host', __('Missing WU_CYBERPANEL_HOST', 'ultimate-multisite'));
		}

		$username = $this->get_credential('WU_CYBERPANEL_USERNAME');
		$password = $this->get_credential('WU_CYBERPANEL_PASSWORD');

		if (empty($username) || empty($password)) {
			return new \WP_Error('wu_cyberpanel_no_auth', __('Missing CyberPanel username or password', 'ultimate-multisite'));
		}

		$port = $this->get_credential('WU_CYBERPANEL_PORT') ?: '8090';

		// Sanitize host: strip protocol, trailing slashes, port if included
		$clean_host = preg_replace('#^https?://#', '', (string) $host);
		$clean_host = rtrim($clean_host, "; \t\n\r\0\x0B/");
		$clean_host = preg_replace('#:\d+$#', '', $clean_host);

		$api_url = sprintf('https://%s:%s/api/%s', $clean_host, $port, $endpoint);

		// CyberPanel standard API: credentials go in the JSON body
		$body = array_merge(
			[
				'adminUser' => $username,
				'adminPass' => $password,
			],
			$data
		);

		$response = wp_remote_post(
			$api_url,
			[
				'timeout'   => 60,
				'sslverify' => false, // CyberPanel commonly uses self-signed certs
				'headers'   => [
					'Content-Type' => 'application/json',
					'User-Agent'   => 'Ultimate-Multisite-CyberPanel-Integration/1.0',
				],
				'body'      => wp_json_encode($body),
			]
		);

		if (is_wp_error($response)) {
			wu_log_add('integration-cyberpanel', sprintf('API error (%s): %s', $endpoint, $response->get_error_message()));

			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$raw  = wp_remote_retrieve_body($response);

		if ($code >= 400) {
			wu_log_add('integration-cyberpanel', sprintf('API HTTP %d (%s): %s', $code, $endpoint, $raw));

			return new \WP_Error(
				'wu_cyberpanel_http_error',
				sprintf(
					/* translators: %1$d: HTTP status code, %2$s: Response body */
					__('CyberPanel API returned HTTP %1$d: %2$s', 'ultimate-multisite'),
					$code,
					$raw
				)
			);
		}

		$decoded = json_decode($raw, true);

		if (JSON_ERROR_NONE !== json_last_error()) {
			wu_log_add('integration-cyberpanel', sprintf('API invalid JSON (%s): %s', $endpoint, substr($raw, 0, 500)));

			return new \WP_Error(
				'wu_cyberpanel_json_error',
				sprintf(
					/* translators: %s: JSON error message */
					__('Failed to decode CyberPanel API response: %s', 'ultimate-multisite'),
					json_last_error_msg()
				)
			);
		}

		return $decoded;
	}
}
