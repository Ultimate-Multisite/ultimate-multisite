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

		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('cloudways.webp', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/cloudways');
		$this->set_constants(
			[
				'WU_CLOUDWAYS_EMAIL',
				'WU_CLOUDWAYS_ACCESS_TOKEN',
				'WU_CLOUDWAYS_SERVER_ID',
				'WU_CLOUDWAYS_APP_ID',
			]
		);
		$this->set_optional_constants(['WU_CLOUDWAYS_EXTRA_DOMAINS']);
		$this->set_supports(['autossl', 'no-instructions']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {

		return __('Focus on your business and avoid all the web hosting hassles. Our managed hosting guarantees unmatched performance, reliability and choice with 24/7 support that acts as your extended team, making Cloudways an ultimate choice for growing agencies and e-commerce businesses.', 'ultimate-multisite');
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
				'title'       => __('SSL Contact Email', 'ultimate-multisite'),
				'desc'        => __('Email address used for Let\'s Encrypt certificates, not API authentication.', 'ultimate-multisite'),
				'type'        => 'email',
				'placeholder' => __('e.g. me@gmail.com', 'ultimate-multisite'),
			],
			'WU_CLOUDWAYS_ACCESS_TOKEN'  => [
				'title'       => __('Cloudways Access Token', 'ultimate-multisite'),
				'desc'        => __('Create an access token in Cloudways API Integration. Legacy API keys are no longer supported.', 'ultimate-multisite'),
				'placeholder' => __('Your Cloudways access token', 'ultimate-multisite'),
				'type'        => 'password',
				'html_attr'   => [
					'autocomplete' => 'new-password',
				],
			],
			'WU_CLOUDWAYS_SERVER_ID'     => [
				'title'       => __('Cloudways Server ID', 'ultimate-multisite'),
				'desc'        => __('The numeric ID in the URL of your Cloudways Server Management page.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 11667', 'ultimate-multisite'),
			],
			'WU_CLOUDWAYS_APP_ID'        => [
				'title'       => __('Cloudways App ID', 'ultimate-multisite'),
				'desc'        => __('The numeric ID in the URL of your Cloudways Application Management page.', 'ultimate-multisite'),
				'placeholder' => __('e.g. 940288', 'ultimate-multisite'),
			],
			'WU_CLOUDWAYS_EXTRA_DOMAINS' => [
				'title'       => __('Cloudways Extra Domains', 'ultimate-multisite'),
				'tooltip'     => __('The Cloudways API is a bit strange in that it doesn\'t offer a way to add or remove just one domain, only a way to update the whole domain list. That means that Ultimate Multisite will replace all domains you might have there with the list of mapped domains of the network every time a new domain is added.', 'ultimate-multisite'),
				'desc'        => __('Comma-separated list of additional domains to add to Cloudways.', 'ultimate-multisite'),
				'placeholder' => __('e.g. external-domain.com, another-domain.com', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * Returns setup guidance alongside the credential fields.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_configuration_instructions() {

		return [
			'title'       => __('Cloudways API v2 configuration checklist', 'ultimate-multisite'),
			'description' => __('Cloudways retired legacy API keys. Create a dedicated access token for Ultimate Multisite; an existing API key cannot be converted automatically.', 'ultimate-multisite'),
			'steps'       => [
				sprintf(
					/* translators: %s: Cloudways access token documentation URL. */
					__('Sign in as the primary Cloudways account owner, open API Integration from the profile menu, and create an access token. <a href="%s" target="_blank" rel="noopener noreferrer">Read the token setup guide</a>.', 'ultimate-multisite'),
					esc_url('https://support.cloudways.com/en/articles/5136065-how-to-create-and-manage-cloudways-api-access-tokens')
				),
				__('Choose Limited Access and allow GET /app/manage/fpm_setting, POST /app/manage/aliases, and POST /security/lets_encrypt_install. Read-Only access cannot synchronize domains or install certificates.', 'ultimate-multisite'),
				__('Copy the token when it is displayed and enter it below. Choose an expiration period and replace the token before it expires. For wp-config.php configuration, use WU_CLOUDWAYS_ACCESS_TOKEN instead of WU_CLOUDWAYS_API_KEY.', 'ultimate-multisite'),
				__('Enter an SSL contact email and the Server ID and App ID from the URLs of the Cloudways management pages for this WordPress network. Test the connection before enabling the integration.', 'ultimate-multisite'),
				__('Domain synchronization replaces the application aliases with the network\'s mapped domains. Add any external domains you need to preserve to Extra Domains, separated by commas.', 'ultimate-multisite'),
				__('Use a standard Let\'s Encrypt certificate for the main network domain, not a wildcard certificate. Keep your network\'s subdomain wildcard in DNS only, not in Extra Domains. Wildcard entries are excluded from SSL requests.', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * Returns the configured Cloudways access token without a legacy OAuth exchange.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_cloudways_access_token(): string {

		return trim($this->get_credential('WU_CLOUDWAYS_ACCESS_TOKEN'));
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

		if ('' === $token) {
			return new \WP_Error('cloudways-missing-token', __('Configure a Cloudways access token. Legacy API keys are no longer supported.', 'ultimate-multisite'));
		}

		$endpoint = '/' . ltrim($endpoint, '/');

		$endpoint_url = "https://api.cloudways.com/api/v2$endpoint";

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

		$status_code = wp_remote_retrieve_response_code($response);

		if (401 === $status_code) {
			return new \WP_Error('cloudways-authentication', __('Cloudways rejected the access token. Replace it if it has expired or been revoked.', 'ultimate-multisite'));
		}

		if (403 === $status_code) {
			return new \WP_Error('cloudways-permissions', __('The Cloudways access token does not have permission for this operation. Review its Limited Access endpoints.', 'ultimate-multisite'));
		}

		if (422 === $status_code) {
			$body     = json_decode(wp_remote_retrieve_body($response), true);
			$errors   = $body['errors'] ?? $body;
			$messages = [];

			foreach (is_array($errors) ? $errors : [] as $field => $field_errors) {
				if ( ! is_array($field_errors)) {
					continue;
				}

				foreach ($field_errors as $error) {
					$message = is_array($error) ? ($error['message'] ?? '') : $error;

					if (is_string($message) && '' !== trim($message)) {
						$messages[] = sanitize_text_field($field . ': ' . $message);
					}
				}
			}

			if ($messages) {
				/* translators: %s: Field-level validation messages returned by Cloudways. */
				return new \WP_Error('cloudways-error', sprintf(__('Cloudways API validation failed (HTTP 422): %s', 'ultimate-multisite'), implode('; ', $messages)));
			}
		}

		if (200 > $status_code || 300 <= $status_code) {
			/* translators: %d: HTTP response status code. */
			return new \WP_Error('cloudways-error', sprintf(__('Cloudways API request failed (HTTP %d). Check the server and application IDs and try again.', 'ultimate-multisite'), $status_code));
		}

		$response_data = json_decode(wp_remote_retrieve_body($response));

		if ( ! is_object($response_data)) {
			return new \WP_Error('cloudways-invalid-response', __('Cloudways returned an invalid API response.', 'ultimate-multisite'));
		}

		return $response_data;
	}
}
