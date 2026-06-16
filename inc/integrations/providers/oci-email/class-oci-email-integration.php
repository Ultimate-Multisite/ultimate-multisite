<?php
/**
 * Oracle Cloud Infrastructure Email Delivery Integration.
 *
 * Provides Oracle Cloud Infrastructure (OCI) Email Delivery as a transactional
 * email domain verification provider for WordPress Multisite networks.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\OCI_Email;

use WP_Ultimo\Helpers\OCI_Signer;
use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Oracle OCI integration provider.
 *
 * @since 2.5.0
 */
class OCI_Email_Integration extends Integration {

	/**
	 * OCI Email Delivery API base URL.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private const API_BASE = 'https://ctrl.email.%s.oci.oraclecloud.com/20170907/';

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('oracle-oci', 'Oracle OCI Email Delivery');

		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('oracle-oci.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/oracle-oci-email-delivery');
		$this->set_constants(['WU_OCI_TENANCY_OCID', 'WU_OCI_USER_OCID', 'WU_OCI_KEY_FINGERPRINT', 'WU_OCI_PRIVATE_KEY', 'WU_OCI_COMPARTMENT_OCID']);
		$this->set_optional_constants(['WU_OCI_REGION', 'WU_OCI_EMAIL_SPF_INCLUDE', 'WU_OCI_EMAIL_DOMAIN_ENDPOINT']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {

		return __('Oracle Cloud Infrastructure Email Delivery sends high-volume transactional email through OCI. Use it to create Email Delivery domains, request DKIM, and publish the returned SPF/DKIM/DMARC records for each mapped domain.', 'ultimate-multisite');
	}

	/**
	 * Returns the OCI region to use for Email Delivery.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_region(): string {

		$region = $this->get_credential('WU_OCI_REGION');

		return $region ?: 'eu-zurich-1';
	}

	/**
	 * Returns the OCI API base URL for the configured region.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_api_base(): string {

		$endpoint = $this->get_credential('WU_OCI_EMAIL_DOMAIN_ENDPOINT');

		if ($endpoint) {
			return trailingslashit($endpoint);
		}

		return sprintf(self::API_BASE, $this->get_region());
	}

	/**
	 * Returns the SPF include host for OCI Email Delivery.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_spf_include(): string {

		$spf_include = $this->get_credential('WU_OCI_EMAIL_SPF_INCLUDE');

		return $spf_include ?: 'rp.oracleemaildelivery.com';
	}

	/**
	 * Returns the configured OCI compartment OCID.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_compartment_ocid(): string {

		return $this->get_credential('WU_OCI_COMPARTMENT_OCID');
	}

	/**
	 * Returns a configured OCI signer instance.
	 *
	 * @since 2.5.0
	 * @return OCI_Signer
	 */
	public function get_signer(): OCI_Signer {

		return new OCI_Signer(
			$this->get_credential('WU_OCI_TENANCY_OCID'),
			$this->get_credential('WU_OCI_USER_OCID'),
			$this->get_credential('WU_OCI_KEY_FINGERPRINT'),
			$this->get_credential('WU_OCI_PRIVATE_KEY')
		);
	}

	/**
	 * Makes an authenticated request to the OCI Email Delivery API.
	 *
	 * @since 2.5.0
	 *
	 * @param string $endpoint Relative endpoint path.
	 * @param string $method   HTTP method. Defaults to GET.
	 * @param array  $data     Request body data (JSON encoded for non-GET requests).
	 * @return array|\WP_Error Decoded response array or WP_Error on failure.
	 */
	public function oci_api_call(string $endpoint, string $method = 'GET', array $data = []) {

		$url     = $this->get_api_base() . ltrim($endpoint, '/');
		$payload = ('GET' === $method || empty($data)) ? '' : wp_json_encode($data);
		$headers = $this->get_signer()->sign($method, $url, $payload ?: '');

		if (empty($headers)) {
			return new \WP_Error('oracle-oci-signing-error', __('Unable to sign OCI API request. Check the configured private key.', 'ultimate-multisite'));
		}

		$request_args = [
			'method'  => $method,
			'headers' => $headers,
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
			'oracle-oci-error',
			sprintf(
				/* translators: 1: HTTP status code, 2: error message */
				__('OCI Email Delivery API error (HTTP %1$d): %2$s', 'ultimate-multisite'),
				$status_code,
				$error_message
			)
		);
	}

	/**
	 * Tests the connection to the OCI Email Delivery API.
	 *
	 * @since 2.5.0
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$result = $this->oci_api_call('emailDomains?compartmentId=' . rawurlencode($this->get_compartment_ocid()) . '&limit=1');

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
			'WU_OCI_TENANCY_OCID'          => [
				'title'       => __('OCI Tenancy OCID', 'ultimate-multisite'),
				'placeholder' => __('e.g. ocid1.tenancy.oc1..aaaa...', 'ultimate-multisite'),
			],
			'WU_OCI_USER_OCID'             => [
				'title'       => __('OCI User OCID', 'ultimate-multisite'),
				'placeholder' => __('e.g. ocid1.user.oc1..aaaa...', 'ultimate-multisite'),
			],
			'WU_OCI_KEY_FINGERPRINT'       => [
				'title'       => __('OCI API Key Fingerprint', 'ultimate-multisite'),
				'placeholder' => __('e.g. 12:34:56:...', 'ultimate-multisite'),
			],
			'WU_OCI_PRIVATE_KEY'           => [
				'title'     => __('OCI API Private Key', 'ultimate-multisite'),
				'type'      => 'textarea',
				'desc'      => __('Paste the PEM private key for the OCI API key. Newline escape sequences are supported.', 'ultimate-multisite'),
				'html_attr' => [
					'autocomplete' => 'new-password',
				],
			],
			'WU_OCI_COMPARTMENT_OCID'      => [
				'title'       => __('OCI Compartment OCID', 'ultimate-multisite'),
				'placeholder' => __('e.g. ocid1.compartment.oc1..aaaa...', 'ultimate-multisite'),
			],
			'WU_OCI_REGION'                => [
				'title'       => __('OCI Region', 'ultimate-multisite'),
				'placeholder' => __('e.g. eu-zurich-1', 'ultimate-multisite'),
				'desc'        => __('Optional. The OCI region for Email Delivery. Defaults to eu-zurich-1.', 'ultimate-multisite'),
			],
			'WU_OCI_EMAIL_SPF_INCLUDE'     => [
				'title'       => __('OCI SPF Include Host', 'ultimate-multisite'),
				'placeholder' => __('rp.oracleemaildelivery.com', 'ultimate-multisite'),
				'desc'        => __('Optional. Override only if Oracle changes the SPF include host for your tenancy or region.', 'ultimate-multisite'),
			],
			'WU_OCI_EMAIL_DOMAIN_ENDPOINT' => [
				'title' => __('OCI Email API Endpoint Override', 'ultimate-multisite'),
				'desc'  => __('Optional. Advanced use only; overrides the generated Email Delivery API endpoint.', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * Returns provider-specific guidance for the configuration step.
	 *
	 * @since 2.5.0
	 * @return array{title: string, description: string, steps: array<int, string>}
	 */
	public function get_configuration_instructions(): array {

		return [
			'title'       => __('Oracle OCI Email Delivery configuration checklist', 'ultimate-multisite'),
			'description' => __('Use OCI API key credentials for an IAM user that can manage Email Delivery domains and DKIM records in the selected compartment. SMTP credentials are still managed separately in OCI IAM and used by your server mail transport.', 'ultimate-multisite'),
			'steps'       => [
				__('Create or choose an OCI IAM user with a policy that allows managing Email Delivery domains and DKIM in the target compartment.', 'ultimate-multisite'),
				__('Generate an OCI API signing key for that user, then enter the Tenancy OCID, User OCID, API key fingerprint, private key, and Compartment OCID here.', 'ultimate-multisite'),
				__('Set the OCI region to the Email Delivery region you use for SMTP, for example <code>eu-zurich-1</code>. The API region and SMTP region should match.', 'ultimate-multisite'),
				__('Leave the SPF include host as <code>rp.oracleemaildelivery.com</code> unless Oracle provides a different include host for your tenancy.', 'ultimate-multisite'),
				__('After testing succeeds, publish the returned SPF, DKIM, and DMARC DNS records. For existing SPF records, merge the OCI include into the single existing SPF TXT record instead of creating a second SPF record.', 'ultimate-multisite'),
			],
		];
	}
}
