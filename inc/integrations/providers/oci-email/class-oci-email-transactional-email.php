<?php
/**
 * Oracle OCI Transactional Email Capability.
 *
 * Implements the Transactional_Email_Capability interface using Oracle Cloud
 * Infrastructure Email Delivery management APIs.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\OCI_Email;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Transactional_Email_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Oracle OCI transactional email capability module.
 *
 * @since 2.5.0
 */
class OCI_Email_Transactional_Email extends Base_Capability_Module implements Transactional_Email_Capability {

	/**
	 * {@inheritdoc}
	 */
	public function get_capability_id(): string {

		return 'transactional-email';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title(): string {

		return __('Transactional Email', 'ultimate-multisite');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_explainer_lines(): array {

		return [
			'will'     => [
				'domain_verify'      => __('Create or locate OCI Email Delivery domains when a new domain is added to the network', 'ultimate-multisite'),
				'dkim_records'       => __('Request DKIM for verified domains and return the DNS records required by OCI', 'ultimate-multisite'),
				'authentication_dns' => __('Return SPF and DMARC records alongside OCI DKIM records so DNS automation can publish a complete authentication set', 'ultimate-multisite'),
			],
			'will_not' => [
				'mailbox_provision' => __('Provision mailboxes or IMAP/POP3 accounts (use the Email Selling capability for that)', 'ultimate-multisite'),
				'dns_auto_create'   => __('Automatically create DNS records unless a supported DNS provider capability is configured to consume the returned records', 'ultimate-multisite'),
				'smtp_credentials'  => __('Create OCI SMTP credentials; those remain managed in OCI IAM for the sending user', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_hooks(): void {

		add_action('wu_domain_added', [$this, 'on_domain_added'], 10, 2);
		add_action('wu_domain_removed', [$this, 'on_domain_removed'], 10, 2);
		add_action('wu_settings_transactional_email', [$this, 'register_transactional_email_settings']);
	}

	/**
	 * Registers a status field in the Transactional Email Delivery settings section.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_transactional_email_settings(): void {

		$region = $this->get_oci()->get_region();

		wu_register_settings_field(
			'emails',
			'oracle_oci_active_region',
			[
				'title' => __('Oracle OCI Email Delivery Active Region', 'ultimate-multisite'),
				'desc'  => sprintf(
					/* translators: %s is the OCI region identifier, e.g. eu-zurich-1. */
					__('Outbound email domain verification is currently managed through Oracle OCI Email Delivery in the <strong>%s</strong> region.', 'ultimate-multisite'),
					esc_html($region)
				),
				'type'  => 'note',
			]
		);
	}

	/**
	 * Gets the parent OCI_Email_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return OCI_Email_Integration
	 */
	private function get_oci(): OCI_Email_Integration {

		/** @var OCI_Email_Integration */
		return $this->get_integration();
	}

	/**
	 * Handles the wu_domain_added action.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name that was added.
	 * @param int    $site_id The site ID the domain was added to.
	 * @return void
	 */
	public function on_domain_added(string $domain, int $site_id): void {

		$result = $this->verify_domain($domain);

		if ( ! $result['success']) {
			wu_log_add(
				'integration-oracle-oci',
				sprintf(
					'Failed to initiate OCI Email Delivery domain verification for "%s". Reason: %s',
					$domain,
					$result['message'] ?? __('Unknown error', 'ultimate-multisite')
				),
				LogLevel::ERROR
			);

			return;
		}

		wu_log_add('integration-oracle-oci', sprintf('Initiated OCI Email Delivery domain verification for "%s".', $domain));

		/**
		 * Fires after OCI domain verification has been initiated.
		 *
		 * @since 2.5.0
		 *
		 * @param string $domain      The domain name.
		 * @param int    $site_id     The site ID.
		 * @param array  $dns_records The DNS records that must be added to complete verification.
		 */
		do_action('wu_domain_verified', $domain, $site_id, $result['dns_records'] ?? []);
	}

	/**
	 * Handles the wu_domain_removed action.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name that was removed.
	 * @param int    $site_id The site ID the domain was removed from.
	 * @return void
	 */
	public function on_domain_removed(string $domain, int $site_id): void {

		$should_delete = apply_filters('wu_oci_delete_email_domain_on_domain_removed', false, $domain, $site_id);

		if ( ! $should_delete) {
			return;
		}

		$domain_record = $this->get_email_domain($domain);

		if (is_wp_error($domain_record)) {
			wu_log_add('integration-oracle-oci', sprintf('Failed to locate OCI email domain for "%s". Reason: %s', $domain, $domain_record->get_error_message()), LogLevel::ERROR);

			return;
		}

		$domain_id = $domain_record['id'] ?? '';

		if ( ! $domain_id) {
			return;
		}

		$result = $this->get_oci()->oci_api_call('emailDomains/' . rawurlencode($domain_id), 'DELETE');

		if (is_wp_error($result)) {
			wu_log_add('integration-oracle-oci', sprintf('Failed to delete OCI email domain for "%s". Reason: %s', $domain, $result->get_error_message()), LogLevel::ERROR);

			return;
		}

		wu_log_add('integration-oracle-oci', sprintf('Deleted OCI email domain for "%s".', $domain));
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $domain The domain name.
	 */
	public function verify_domain(string $domain): array {

		$email_domain = $this->get_or_create_email_domain($domain);

		if (is_wp_error($email_domain)) {
			return [
				'success' => false,
				'message' => $email_domain->get_error_message(),
			];
		}

		$dkim = $this->get_or_create_dkim($email_domain, $domain);

		if (is_wp_error($dkim)) {
			return [
				'success' => false,
				'message' => $dkim->get_error_message(),
			];
		}

		$approved_sender = $this->get_or_create_approved_sender($domain);

		if (is_wp_error($approved_sender)) {
			return [
				'success' => false,
				'message' => $approved_sender->get_error_message(),
			];
		}

		return [
			'success'         => true,
			'approved_sender' => $approved_sender['emailAddress'] ?? $approved_sender['email_address'] ?? $this->get_default_approved_sender_address(
				$domain
			),
			'dns_records'     => $this->build_dns_records($domain, $email_domain, $dkim),
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $domain The domain name.
	 */
	public function get_domain_verification_status(string $domain): array {

		$result = $this->get_email_domain($domain);

		if (is_wp_error($result)) {
			return [
				'success' => false,
				'status'  => 'unknown',
				'message' => $result->get_error_message(),
			];
		}

		$status = $result['lifecycleState'] ?? $result['lifecycle_state'] ?? $result['status'] ?? 'unknown';

		return [
			'success' => true,
			'status'  => strtolower((string) $status),
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $domain The domain name.
	 */
	public function get_domain_dns_records(string $domain): array {

		$email_domain = $this->get_email_domain($domain);

		if (is_wp_error($email_domain)) {
			return [
				'success' => false,
				'message' => $email_domain->get_error_message(),
			];
		}

		$dkim = $this->get_or_create_dkim($email_domain, $domain);

		if (is_wp_error($dkim)) {
			return [
				'success' => false,
				'message' => $dkim->get_error_message(),
			];
		}

		return [
			'success'     => true,
			'dns_records' => $this->build_dns_records($domain, $email_domain, $dkim),
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $from    The sender email address.
	 * @param string $to      The recipient email address.
	 * @param string $subject The email subject.
	 * @param string $body    The email body.
	 * @param array  $headers Optional headers.
	 */
	public function send_email(string $from, string $to, string $subject, string $body, array $headers = []): array {

		return [
			'success' => false,
			'message' => __('OCI Email Delivery sending is handled by the configured SMTP transport. This integration manages OCI domain, SPF, DKIM, and DMARC setup.', 'ultimate-multisite'),
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $domain The domain name.
	 * @param string $period The metrics period.
	 */
	public function get_sending_statistics(string $domain, string $period = '24h'): array {

		return [
			'success'    => true,
			'sent'       => 0,
			'delivered'  => 0,
			'bounced'    => 0,
			'complaints' => 0,
			'message'    => __('OCI Email Delivery delivery metrics are available in OCI Monitoring, not through this transactional email capability.', 'ultimate-multisite'),
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $domain      The domain name.
	 * @param int    $max_per_day Maximum emails allowed per day.
	 */
	public function set_sending_quota(string $domain, int $max_per_day): array {

		return [
			'success' => true,
			'message' => __('Sending quota management is handled at the OCI tenancy/account level via Oracle Cloud Console.', 'ultimate-multisite'),
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_oci()->test_connection();
	}

	/**
	 * Create or return an existing OCI email domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @return array|\WP_Error
	 */
	private function get_or_create_email_domain(string $domain) {

		$existing = $this->get_email_domain($domain);

		if ( ! is_wp_error($existing) && ! empty($existing)) {
			return $existing;
		}

		$body = [
			'compartmentId' => $this->get_oci()->get_compartment_ocid(),
			'name'          => $domain,
		];

		$body = apply_filters('wu_oci_create_email_domain_body', $body, $domain);

		$result = $this->get_oci()->oci_api_call('emailDomains', 'POST', $body);

		if ( ! is_wp_error($result)) {
			wu_log_add('integration-oracle-oci', sprintf('Created OCI Email Delivery domain for "%s".', $domain));
		}

		return $result;
	}

	/**
	 * Locate an OCI email domain by name.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @return array|\WP_Error
	 */
	private function get_email_domain(string $domain) {

		$result = $this->get_oci()->oci_api_call(
			'emailDomains?compartmentId=' . rawurlencode($this->get_oci()->get_compartment_ocid()) . '&name=' . rawurlencode($domain)
		);

		if (is_wp_error($result)) {
			return $result;
		}

		$items = $result['items'] ?? $result;

		if (isset($items['name'])) {
			return $items;
		}

		if (is_array($items)) {
			foreach ($items as $item) {
				if (isset($item['name']) && strtolower($domain) === strtolower($item['name'])) {
					return $item;
				}
			}
		}

		return new \WP_Error(
			'oracle-oci-domain-not-found',
			sprintf(
				/* translators: %s is a domain name. */
				__('OCI email domain "%s" was not found.', 'ultimate-multisite'),
				$domain
			)
		);
	}

	/**
	 * Create or return an existing DKIM record for an OCI email domain.
	 *
	 * @since 2.5.0
	 *
	 * @param array  $email_domain OCI email domain object.
	 * @param string $domain       Domain name.
	 * @return array|\WP_Error
	 */
	private function get_or_create_dkim(array $email_domain, string $domain) {

		$domain_id = $email_domain['id'] ?? '';

		if ( ! $domain_id) {
			return new \WP_Error(
				'oracle-oci-domain-id-missing',
				__('OCI email domain response did not include an ID.', 'ultimate-multisite')
			);
		}

		$existing = $this->get_oci()->oci_api_call('emailDomains/' . rawurlencode($domain_id) . '/dkims');

		if ( ! is_wp_error($existing)) {
			$items = $existing['items'] ?? $existing;

			if (isset($items['id'])) {
				return $this->get_dkim_details($domain_id, $items);
			}

			if (is_array($items) && ! empty($items)) {
				return $this->get_dkim_details($domain_id, reset($items));
			}
		}

		$selector = sanitize_key(
			apply_filters('wu_oci_dkim_selector', 'ultimate-multisite', $domain, $email_domain)
		);
		$body     = apply_filters(
			'wu_oci_create_dkim_body',
			[
				'name' => $selector,
			],
			$domain,
			$email_domain
		);

		$created = $this->get_oci()->oci_api_call('emailDomains/' . rawurlencode($domain_id) . '/dkims', 'POST', $body);

		if (is_wp_error($created)) {
			return $created;
		}

		wu_log_add('integration-oracle-oci', sprintf('Created OCI DKIM selector "%s" for "%s".', $selector, $domain));

		return $this->wait_for_dkim_dns_fields($domain_id, $created);
	}

	/**
	 * Fetch a DKIM resource detail response when a list item omits DNS values.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_id OCI email domain OCID.
	 * @param array  $dkim      OCI DKIM summary or detail response.
	 * @return array|\WP_Error
	 */
	private function get_dkim_details(string $domain_id, array $dkim) {

		if ($this->dkim_has_dns_fields($dkim)) {
			return $dkim;
		}

		$dkim_id = $dkim['id'] ?? '';

		if ( ! $dkim_id) {
			return $dkim;
		}

		$result = $this->get_oci()->oci_api_call(
			'emailDomains/' . rawurlencode($domain_id) . '/dkims/' . rawurlencode($dkim_id)
		);

		if (is_wp_error($result)) {
			return $result;
		}

		return array_merge($dkim, $result);
	}

	/**
	 * Wait briefly for newly-created DKIM DNS fields to become available.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_id OCI email domain OCID.
	 * @param array  $dkim      Newly-created OCI DKIM response.
	 * @return array|\WP_Error
	 */
	private function wait_for_dkim_dns_fields(string $domain_id, array $dkim) {

		$attempts = (int) apply_filters('wu_oci_dkim_dns_field_poll_attempts', 3, $dkim, $domain_id);
		$attempts = max(1, min(5, $attempts));

		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			$details = $this->get_dkim_details($domain_id, $dkim);

			if (is_wp_error($details)) {
				return $details;
			}

			if ($this->dkim_has_dns_fields($details)) {
				return $details;
			}

			if ($attempt < $attempts) {
				sleep(1);
			}
		}

		return $dkim;
	}

	/**
	 * Check whether a DKIM response includes enough DNS data to publish.
	 *
	 * @since 2.5.0
	 *
	 * @param array $dkim OCI DKIM response.
	 * @return bool
	 */
	private function dkim_has_dns_fields(array $dkim): bool {

		return ! empty($this->get_dkim_value($dkim));
	}

	/**
	 * Create or return an existing OCI approved sender for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @return array|\WP_Error
	 */
	private function get_or_create_approved_sender(string $domain) {

		$email_address = $this->get_default_approved_sender_address($domain);
		$existing      = $this->get_approved_sender($email_address);

		if ( ! is_wp_error($existing) && ! empty($existing)) {
			return $existing;
		}

		$body = apply_filters(
			'wu_oci_create_approved_sender_body',
			[
				'compartmentId' => $this->get_oci()->get_compartment_ocid(),
				'emailAddress'  => $email_address,
			],
			$domain
		);

		$result = $this->get_oci()->oci_api_call('approvedSenders', 'POST', $body);

		if (is_wp_error($result)) {
			return new \WP_Error(
				'oracle-oci-approved-sender-create-failed',
				sprintf(
					/* translators: 1: email address, 2: OCI API error message. */
					__('Failed to create OCI approved sender "%1$s": %2$s', 'ultimate-multisite'),
					$email_address,
					$result->get_error_message()
				)
			);
		}

		wu_log_add('integration-oracle-oci', sprintf('Created OCI approved sender "%s".', $email_address));

		return $result;
	}

	/**
	 * Locate an OCI approved sender by email address.
	 *
	 * @since 2.5.0
	 *
	 * @param string $email_address Sender email address.
	 * @return array|\WP_Error
	 */
	private function get_approved_sender(string $email_address) {

		$query  = 'approvedSenders?compartmentId=' . rawurlencode($this->get_oci()->get_compartment_ocid());
		$query .= '&emailAddress=' . rawurlencode($email_address);
		$result = $this->get_oci()->oci_api_call($query);

		if (is_wp_error($result)) {
			return $result;
		}

		$items = $result['items'] ?? $result;

		if (isset($items['emailAddress']) || isset($items['email_address'])) {
			return $items;
		}

		if (is_array($items)) {
			foreach ($items as $item) {
				$item_address = $item['emailAddress'] ?? $item['email_address'] ?? '';

				if (strtolower($email_address) === strtolower($item_address)) {
					return $item;
				}
			}
		}

		return new \WP_Error(
			'oracle-oci-approved-sender-not-found',
			__('OCI approved sender was not found.', 'ultimate-multisite')
		);
	}

	/**
	 * Build the default approved sender address for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @return string
	 */
	private function get_default_approved_sender_address(string $domain): string {

		$local_part = apply_filters(
			'wu_oci_approved_sender_local_part',
			$this->get_oci()->get_approved_sender_local_part(),
			$domain
		);
		$local_part = sanitize_key($local_part) ?: 'support';

		return $local_part . '@' . $domain;
	}

	/**
	 * Build DNS records from OCI Email Delivery responses.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain       Domain name.
	 * @param array  $email_domain OCI email domain response.
	 * @param array  $dkim         OCI DKIM response.
	 * @return array<array{type: string, name: string, value: string}>
	 */
	private function build_dns_records(string $domain, array $email_domain, array $dkim): array {

		$records = [
			[
				'type'  => 'TXT',
				'name'  => $domain,
				'value' => 'v=spf1 include:' . $this->get_oci()->get_spf_include() . ' ~all',
			],
			[
				'type'  => 'TXT',
				'name'  => '_dmarc.' . $domain,
				'value' => 'v=DMARC1; p=none',
			],
		];

		$dkim_record = $this->extract_dkim_record($domain, $dkim);

		if ($dkim_record) {
			$records[] = $dkim_record;
		}

		return apply_filters('wu_oci_email_dns_records', $records, $domain, $email_domain, $dkim);
	}

	/**
	 * Extract a DKIM DNS record from flexible OCI response shapes.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @param array  $dkim   OCI DKIM response.
	 * @return array{type: string, name: string, value: string}|null
	 */
	private function extract_dkim_record(string $domain, array $dkim): ?array {

		$name  = $this->get_dkim_name($domain, $dkim);
		$value = $this->get_dkim_value($dkim);
		$type  = $this->is_cname_dkim($dkim) ? 'CNAME' : 'TXT';

		if ( ! $name) {
			$selector = $dkim['name'] ?? $dkim['selector'] ?? 'ultimate-multisite';
			$name     = $selector . '._domainkey.' . $domain;
		}

		if ( ! $value) {
			return null;
		}

		return [
			'type'  => $type,
			'name'  => $name,
			'value' => $value,
		];
	}

	/**
	 * Extract the DKIM DNS record name from flexible OCI response shapes.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @param array  $dkim   OCI DKIM response.
	 * @return string
	 */
	private function get_dkim_name(string $domain, array $dkim): string {

		$name = $dkim['dnsSubdomainName'] ?? $dkim['dns_subdomain_name'] ?? $dkim['dnsName'] ?? '';
		$name = $name ?: ($dkim['dnsCnameRecordName'] ?? $dkim['cnameRecordName'] ?? '');

		if ($name) {
			return $name;
		}

		$selector = $dkim['name'] ?? $dkim['selector'] ?? 'ultimate-multisite';

		return $selector . '._domainkey.' . $domain;
	}

	/**
	 * Extract the DKIM DNS record value from flexible OCI response shapes.
	 *
	 * @since 2.5.0
	 *
	 * @param array $dkim OCI DKIM response.
	 * @return string
	 */
	private function get_dkim_value(array $dkim): string {

		$value = $dkim['dnsTxtRecordValue'] ?? $dkim['dns_txt_record_value'] ?? $dkim['txtRecordValue'] ?? '';

		return (string) ($value ?: ($dkim['dnsCnameRecordValue'] ?? $dkim['cnameRecordValue'] ?? ''));
	}

	/**
	 * Determine whether the DKIM response represents a CNAME record.
	 *
	 * @since 2.5.0
	 *
	 * @param array $dkim OCI DKIM response.
	 * @return bool
	 */
	private function is_cname_dkim(array $dkim): bool {

		return ! empty($dkim['dnsCnameRecordValue'])
			|| ! empty($dkim['cnameRecordValue'])
			|| ! empty($dkim['dnsCnameRecordName'])
			|| ! empty($dkim['cnameRecordName']);
	}
}
