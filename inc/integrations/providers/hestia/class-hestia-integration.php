<?php
/**
 * Hestia Integration.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/Hestia
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Hestia;

use WP_Ultimo\Integrations\Integration;

defined('ABSPATH') || exit;

/**
 * Hestia Control Panel integration provider.
 *
 * @since 2.5.0
 */
class Hestia_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('hestia', 'Hestia Control Panel');

		$this->set_description(__('Integrates with Hestia Control Panel to add and remove web domain aliases automatically when domains are mapped or removed.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('hestia.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/hestia');
		$this->set_constants(
			[
				'WU_HESTIA_API_URL',
				['WU_HESTIA_API_HASH', 'WU_HESTIA_API_PASSWORD'],
				'WU_HESTIA_API_USER',
				'WU_HESTIA_ACCOUNT',
				'WU_HESTIA_WEB_DOMAIN',
			]
		);
		$this->set_optional_constants(['WU_HESTIA_RESTART']);
		$this->set_supports(['no-instructions']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$account = $this->get_credential('WU_HESTIA_ACCOUNT');

		$response = $this->send_hestia_request('v-list-web-domains', [$account, 'json']);

		if (is_wp_error($response)) {
			return $response;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_HESTIA_API_URL'      => [
				'title'       => __('Hestia API URL', 'ultimate-multisite'),
				'desc'        => __('Base API endpoint, typically https://your-hestia:8083/api/', 'ultimate-multisite'),
				'placeholder' => __('e.g. https://server.example.com:8083/api/', 'ultimate-multisite'),
			],
			'WU_HESTIA_API_USER'     => [
				'title'       => __('Hestia API Username', 'ultimate-multisite'),
				'desc'        => __('Hestia user for API calls (often admin)', 'ultimate-multisite'),
				'placeholder' => __('e.g. admin', 'ultimate-multisite'),
			],
			'WU_HESTIA_API_PASSWORD' => [
				'type'        => 'password',
				'title'       => __('Hestia API Password', 'ultimate-multisite'),
				'desc'        => __('Optional if using API hash authentication.', 'ultimate-multisite'),
				'placeholder' => __('••••••••', 'ultimate-multisite'),
			],
			'WU_HESTIA_API_HASH'     => [
				'title'       => __('Hestia API Hash (Token)', 'ultimate-multisite'),
				'desc'        => __('Optional: API hash/token alternative to password. Provide either this OR a password.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 1a2b3c4d...', 'ultimate-multisite'),
			],
			'WU_HESTIA_ACCOUNT'      => [
				'title'       => __('Hestia Account (Owner)', 'ultimate-multisite'),
				'desc'        => __('The Hestia user that owns the web domain (first argument to v-add-web-domain-alias).', 'ultimate-multisite'),
				'placeholder' => __('e.g. admin', 'ultimate-multisite'),
			],
			'WU_HESTIA_WEB_DOMAIN'   => [
				'title'       => __('Base Web Domain', 'ultimate-multisite'),
				'desc'        => __('Existing Hestia web domain that your WordPress is served from. Aliases will be attached to this.', 'ultimate-multisite'),
				'placeholder' => __('e.g. network.example.com', 'ultimate-multisite'),
			],
			'WU_HESTIA_RESTART'      => [
				'title'       => __('Restart Web Service', 'ultimate-multisite'),
				'desc'        => __('Whether to restart/reload services after alias changes (yes/no). Defaults to yes.', 'ultimate-multisite'),
				'placeholder' => __('yes', 'ultimate-multisite'),
				'value'       => 'yes',
			],
		];
	}

	/**
	 * Send request to Hestia API.
	 *
	 * @since 2.5.0
	 *
	 * @param string $cmd  Command name (e.g., v-add-web-domain-alias).
	 * @param array  $args Positional args for the command.
	 * @return mixed|\WP_Error
	 */
	public function send_hestia_request(string $cmd, array $args = []) {

		$url = $this->get_credential('WU_HESTIA_API_URL');

		if (empty($url)) {
			return new \WP_Error('wu_hestia_no_url', __('Missing WU_HESTIA_API_URL', 'ultimate-multisite'));
		}

		// Normalize URL to point to /api endpoint
		$url = rtrim($url, '/');

		if (! preg_match('#/api$#', $url)) {
			$url .= '/api';
		}

		$body = [
			'cmd'        => $cmd,
			'returncode' => 'yes',
		];

		// Auth: prefer hash if provided, otherwise username/password
		$api_user = $this->get_credential('WU_HESTIA_API_USER');
		$api_hash = $this->get_credential('WU_HESTIA_API_HASH');
		$api_pass = $this->get_credential('WU_HESTIA_API_PASSWORD');

		$body['user'] = $api_user;

		if (! empty($api_hash)) {
			$body['hash'] = $api_hash;
		} else {
			$body['password'] = $api_pass;
		}

		// Map args to arg1..argN
		$index = 1;

		foreach ($args as $arg) {
			$body[ 'arg' . $index ] = (string) $arg;
			++$index;
		}

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 60,
				'body'    => $body,
				'method'  => 'POST',
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$raw  = wp_remote_retrieve_body($response);

		if (200 !== $code) {
			/* translators: %1$d: HTTP status code, %2$s: Response body */
			return new \WP_Error('wu_hestia_http_error', sprintf(__('HTTP %1$d from Hestia API: %2$s', 'ultimate-multisite'), $code, $raw));
		}

		$trim = trim((string) $raw);

		if ('0' === $trim) {
			return '0';
		}

		// Try to decode JSON if present, otherwise return raw string
		$json = json_decode($raw);

		return null !== $json ? $json : $raw;
	}
}
