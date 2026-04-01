<?php
/**
 * Amazon SES Integration.
 *
 * Provides Amazon Simple Email Service (SES) as a transactional email
 * delivery provider for WordPress Multisite networks.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Amazon_SES;

use WP_Ultimo\Helpers\AWS_Signer;
use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Amazon SES integration provider.
 *
 * @since 2.5.0
 */
class Amazon_SES_Integration extends Integration {

	/**
	 * Amazon SES API endpoint base URL.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private const API_BASE = 'https://email.%s.amazonaws.com/v2/';

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('amazon-ses', 'Amazon SES');

		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('amazon-ses.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/amazon-ses');
		$this->set_constants(['WU_AWS_ACCESS_KEY_ID', 'WU_AWS_SECRET_ACCESS_KEY']);
		$this->set_optional_constants(['WU_AWS_SES_REGION']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {

		return __('Amazon Simple Email Service (SES) is a cost-effective, flexible, and scalable email service that enables developers to send mail from within any application. Route transactional email for each subsite through SES with automatic domain verification.', 'ultimate-multisite');
	}

	/**
	 * Returns the AWS region to use for SES.
	 *
	 * Defaults to us-east-1 if not configured.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_region(): string {

		$region = $this->get_credential('WU_AWS_SES_REGION');

		return $region ?: 'us-east-1';
	}

	/**
	 * Returns the SES API base URL for the configured region.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_api_base(): string {

		return sprintf(self::API_BASE, $this->get_region());
	}

	/**
	 * Returns a configured AWS_Signer instance for SES.
	 *
	 * @since 2.5.0
	 * @return AWS_Signer
	 */
	public function get_signer(): AWS_Signer {

		return new AWS_Signer(
			$this->get_credential('WU_AWS_ACCESS_KEY_ID'),
			$this->get_credential('WU_AWS_SECRET_ACCESS_KEY'),
			$this->get_region(),
			'ses'
		);
	}

	/**
	 * Makes an authenticated request to the Amazon SES v2 API.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint Relative endpoint path (e.g. 'email-identities').
	 * @param string $method   HTTP method. Defaults to GET.
	 * @param array  $data     Request body data (will be JSON-encoded for non-GET requests).
	 * @return array|\WP_Error Decoded response array or WP_Error on failure.
	 */
	public function ses_api_call(string $endpoint, string $method = 'GET', array $data = []) {

		$url     = $this->get_api_base() . ltrim($endpoint, '/');
		$payload = ('GET' === $method || empty($data)) ? '' : wp_json_encode($data);

		$auth_headers = $this->get_signer()->sign($method, $url, $payload ?: '');

		$request_args = [
			'method'  => $method,
			'headers' => array_merge(
				$auth_headers,
				[
					'Content-Type' => 'application/json',
				]
			),
		];

		if ($payload) {
			$request_args['body'] = $payload;
		}

		$response = wp_remote_request($url, $request_args);

		if (is_wp_error($response)) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body        = wp_remote_retrieve_body($response);
		$decoded     = json_decode($body, true);

		if ($status_code >= 200 && $status_code < 300) {
			return $decoded ?: [];
		}

		$error_message = isset($decoded['message']) ? $decoded['message'] : wp_remote_retrieve_response_message($response);

		return new \WP_Error(
			'amazon-ses-error',
			sprintf(
				/* translators: 1: HTTP status code, 2: error message */
				__('Amazon SES API error (HTTP %1$d): %2$s', 'ultimate-multisite'),
				$status_code,
				$error_message
			)
		);
	}

	/**
	 * Tests the connection to the Amazon SES API.
	 *
	 * Verifies credentials by fetching the account sending quota.
	 *
	 * @since 2.5.0
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$result = $this->ses_api_call('account');

		if (is_wp_error($result)) {
			return $result;
		}

		return true;
	}

	/**
	 * Returns the credential form fields for the setup wizard.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_fields(): array {

		return [
			'WU_AWS_ACCESS_KEY_ID'     => [
				'title'       => __('AWS Access Key ID', 'ultimate-multisite'),
				'placeholder' => __('e.g. AKIAIOSFODNN7EXAMPLE', 'ultimate-multisite'),
			],
			'WU_AWS_SECRET_ACCESS_KEY' => [
				'title'       => __('AWS Secret Access Key', 'ultimate-multisite'),
				'placeholder' => __('e.g. wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'ultimate-multisite'),
				'type'        => 'password',
				'html_attr'   => [
					'autocomplete' => 'new-password',
				],
			],
			'WU_AWS_SES_REGION'        => [
				'title'       => __('AWS Region', 'ultimate-multisite'),
				'placeholder' => __('e.g. us-east-1', 'ultimate-multisite'),
				'desc'        => __('Optional. The AWS region for SES. Defaults to us-east-1.', 'ultimate-multisite'),
			],
		];
	}
}
