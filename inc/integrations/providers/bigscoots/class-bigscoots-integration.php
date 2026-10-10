<?php
/**
 * BigScoots Integration.
 *
 * Provides API access to the BigScoots WPO public API.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/BigScoots
 * @since 2.15.2
 */

namespace WP_Ultimo\Integrations\Providers\BigScoots;

use WP_Ultimo\Integrations\Integration;

defined('ABSPATH') || exit;

/**
 * BigScoots WPO integration provider.
 *
 * @since 2.15.2
 */
class BigScoots_Integration extends Integration {

	/**
	 * BigScoots API base URL.
	 *
	 * @since 2.15.2
	 * @var string
	 */
	public const API_BASE_URL = 'https://api.bigscoots.com';

	/**
	 * User-Agent header sent with API requests.
	 *
	 * @since 2.15.2
	 * @var string
	 */
	private const USER_AGENT = 'UltimateMultisite-BigScoots-Integration/1.0';

	/**
	 * Constructor.
	 *
	 * @since 2.15.2
	 */
	public function __construct() {

		parent::__construct('bigscoots', __('BigScoots', 'ultimate-multisite'));

		$this->set_constants(
			[
				'WU_BIGSCOOTS_API_EMAIL',
				'WU_BIGSCOOTS_API_KEY',
				'WU_BIGSCOOTS_PRIMARY_UUID',
			]
		);
		$this->set_supports(['autossl', 'no-instructions']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description() {

		return __('BigScoots provides managed WordPress hosting through its WPO platform. This integration keeps multisite domains synchronized with WPO and requests SSL certificates after DNS propagation.', 'ultimate-multisite');
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return (bool) ($this->get_credential('WU_BIGSCOOTS_API_EMAIL') && $this->get_credential('WU_BIGSCOOTS_API_KEY') && $this->get_credential('WU_BIGSCOOTS_PRIMARY_UUID'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_ready_for_connection_test() {

		return empty($this->get_missing_connection_test_constants());
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_missing_connection_test_constants() {

		$missing = [];

		foreach (['WU_BIGSCOOTS_API_EMAIL', 'WU_BIGSCOOTS_API_KEY'] as $constant) {
			if ('' === $this->get_credential($constant)) {
				$missing[] = $constant;
			}
		}

		return $missing;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {

		return [
			'WU_BIGSCOOTS_API_EMAIL' => [
				'title'       => __('BigScoots Account Email', 'ultimate-multisite'),
				'desc'        => __('The email address used to sign in to your BigScoots WPO account.', 'ultimate-multisite'),
				'type'        => 'email',
				'placeholder' => __('you@example.com', 'ultimate-multisite'),
			],
			'WU_BIGSCOOTS_API_KEY'   => [
				'title'       => __('BigScoots API Key', 'ultimate-multisite'),
				'desc'        => __('In the WPO Portal, open Edit Profile & Security and enable API Access to generate this key.', 'ultimate-multisite'),
				'type'        => 'password',
				'placeholder' => __('Your WPO API key', 'ultimate-multisite'),
				'html_attr'   => [
					'autocomplete' => 'new-password',
				],
			],
		];
	}

	/**
	 * Returns provider-specific guidance for the configuration step.
	 *
	 * @since 2.16.2
	 * @return array{title: string, description: string, steps: array<int, string>}
	 */
	public function get_configuration_instructions(): array {

		return [
			'title'       => __('BigScoots WPO configuration checklist', 'ultimate-multisite'),
			'description' => __('Ultimate Multisite verifies your WPO credentials first, then retrieves the primary sites available to your account so you do not need to find or copy a UUID manually.', 'ultimate-multisite'),
			'steps'       => [
				__('Sign in to the BigScoots WPO Portal, open <strong>Edit Profile &amp; Security</strong>, and enable API Access to generate an API key.', 'ultimate-multisite'),
				__('Enter the email address used to sign in to that WPO account and the generated API key.', 'ultimate-multisite'),
				__('Test the credentials. On the next step, select the primary site that hosts this WordPress multisite network.', 'ultimate-multisite'),
				__('The selected WPO site must use a <strong>multisite</strong> or <strong>hybrid</strong> plan type. If BigScoots reports another plan type, contact BigScoots support and ask them to enable multisite API access for the site.', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * Returns the API-backed primary site selection field.
	 *
	 * @since 2.16.2
	 * @return array
	 */
	public function get_resource_selection_fields(): array {

		$sites = $this->get_available_sites();

		if (is_wp_error($sites)) {
			$sites = [];
		}

		return [
			'WU_BIGSCOOTS_PRIMARY_UUID' => [
				'title'       => __('BigScoots Primary Site', 'ultimate-multisite'),
				'desc'        => empty($sites)
					? __('No eligible primary sites were returned. Go back, verify the credentials, and confirm the WPO account contains a multisite or hybrid site.', 'ultimate-multisite')
					: __('Select the primary WPO site that hosts this WordPress multisite network.', 'ultimate-multisite'),
				'type'        => 'select',
				'options'     => $sites,
				'placeholder' => __('Select a primary site', 'ultimate-multisite'),
				'html_attr'   => [
					'required' => 'required',
				],
			],
		];
	}

	/**
	 * Tests the configured API credentials.
	 *
	 * @since 2.15.2
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$primary_uuid = $this->get_credential('WU_BIGSCOOTS_PRIMARY_UUID');

		if (empty($primary_uuid)) {
			$sites = $this->get_available_sites();

			return is_wp_error($sites) ? $sites : true;
		}

		$response = $this->bigscoots_api_call('/v1/multi-sites/sub-sites/' . rawurlencode($primary_uuid));

		if (is_wp_error($response)) {
			return $response;
		}

		return true;
	}

	/**
	 * Retrieves eligible primary sites for the configured WPO account.
	 *
	 * @since 2.16.2
	 * @return array<string, string>|\WP_Error Site UUID => display label pairs.
	 */
	public function get_available_sites() {

		$accounts = $this->bigscoots_api_call('/v1/accounts/');

		if (is_wp_error($accounts)) {
			return $accounts;
		}

		$account_uuids = array_unique($this->extract_property_values($accounts, 'account_uuid'));

		if (empty($account_uuids)) {
			return new \WP_Error('bigscoots-no-accounts', __('BigScoots did not return an account for these credentials.', 'ultimate-multisite'));
		}

		$options = [];

		foreach ($account_uuids as $account_uuid) {
			$response = $this->bigscoots_api_call('/v1/sites/account/' . rawurlencode($account_uuid));

			if (is_wp_error($response)) {
				return $response;
			}

			$sites = isset($response->response) ? $response->response : $response;

			foreach ((array) $sites as $site) {
				$site = (object) $site;

				if (empty($site->site_uuid) || empty($site->type) || 'primary' !== $site->type) {
					continue;
				}

				$domain = ! empty($site->domain) ? (string) $site->domain : __('Primary site', 'ultimate-multisite');
				$uuid   = (string) $site->site_uuid;

				$options[ $uuid ] = sprintf(
					/* translators: 1: Site domain, 2: BigScoots site UUID. */
					__('%1$s — %2$s', 'ultimate-multisite'),
					$domain,
					$uuid
				);
			}
		}

		if (empty($options)) {
			return new \WP_Error('bigscoots-no-sites', __('BigScoots did not return any eligible primary sites for this account.', 'ultimate-multisite'));
		}

		return $options;
	}

	/**
	 * Sends an authenticated request to the BigScoots public API.
	 *
	 * @since 2.15.2
	 *
	 * @param string $endpoint API endpoint path.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 * @return mixed|\WP_Error Decoded response on success, or an error.
	 */
	public function bigscoots_api_call(string $endpoint, string $method = 'GET', array $data = []) {

		$email   = $this->get_credential('WU_BIGSCOOTS_API_EMAIL');
		$api_key = $this->get_credential('WU_BIGSCOOTS_API_KEY');

		if (empty($email) || empty($api_key)) {
			return new \WP_Error('bigscoots-missing-credentials', __('BigScoots account email and API key are required.', 'ultimate-multisite'));
		}

		if ('' === $endpoint || '/' !== $endpoint[0]) {
			$endpoint = '/' . $endpoint;
		}

		$method = strtoupper($method);
		$url    = self::API_BASE_URL . $endpoint;

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Accept'       => 'application/json',
				'User-Agent'   => self::USER_AGENT,
				'x-auth-email' => $email,
				'x-auth-key'   => $api_key,
			],
		];

		if ('GET' === $method) {
			if ( ! empty($data)) {
				$url = add_query_arg($data, $url);
			}
		} elseif ( ! empty($data)) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode($data);
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$body        = wp_remote_retrieve_body($response);

		if ($status_code >= 200 && $status_code < 300) {
			if ('' === $body) {
				return (object) [];
			}

			$decoded = json_decode($body);

			if (JSON_ERROR_NONE !== json_last_error()) {
				return new \WP_Error(
					'bigscoots-invalid-json',
					sprintf(
						/* translators: %s: JSON decoding error message. */
						__('BigScoots API returned malformed JSON: %s', 'ultimate-multisite'),
						json_last_error_msg()
					)
				);
			}

			return $decoded;
		}

		return new \WP_Error(
			'bigscoots-http-error',
			sprintf(
				/* translators: 1: HTTP status code, 2: API error message. */
				__('BigScoots API error (%1$d): %2$s', 'ultimate-multisite'),
				$status_code,
				$this->get_api_error_message($body, wp_remote_retrieve_response_message($response))
			),
			['status' => $status_code]
		);
	}

	/**
	 * Extracts a safe error message from an API response.
	 *
	 * @since 2.15.2
	 *
	 * @param string $body     Response body.
	 * @param string $fallback HTTP response message.
	 * @return string
	 */
	private function get_api_error_message(string $body, string $fallback): string {

		$decoded = json_decode($body, true);

		if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
			return $decoded['message'];
		}

		if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
			return $decoded['error'];
		}

		if (is_array($decoded) && isset($decoded['response']['error']) && is_string($decoded['response']['error'])) {
			return $decoded['response']['error'];
		}

		if (is_array($decoded) && isset($decoded['response']['message']) && is_string($decoded['response']['message'])) {
			return $decoded['response']['message'];
		}

		return $fallback ?: __('Unknown API error.', 'ultimate-multisite');
	}

	/**
	 * Recursively extracts string values for a property from an API response.
	 *
	 * @since 2.16.2
	 *
	 * @param mixed  $value    API response value.
	 * @param string $property Property name to collect.
	 * @return array<int, string>
	 */
	private function extract_property_values($value, string $property): array {

		$values = [];

		foreach ((array) $value as $key => $item) {
			if ($property === $key && is_string($item) && '' !== $item) {
				$values[] = $item;
			}

			if (is_array($item) || is_object($item)) {
				$values = array_merge($values, $this->extract_property_values($item, $property));
			}
		}

		return $values;
	}
}
