<?php
/**
 * Amazon SES Transactional Email Capability.
 *
 * Implements the Transactional_Email_Capability interface using Amazon SES v2 API.
 * Handles domain verification, wp_mail() interception, and per-site from-address routing.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Amazon_SES;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Transactional_Email_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Amazon SES transactional email capability module.
 *
 * @since 2.5.0
 */
class Amazon_SES_Transactional_Email extends Base_Capability_Module implements Transactional_Email_Capability {

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
				'intercept_wp_mail'  => __('Intercept wp_mail() calls and route them through Amazon SES using the current site\'s domain as the from-address', 'ultimate-multisite'),
				'domain_verify'      => __('Automatically initiate domain verification in SES when a new domain is added to the network', 'ultimate-multisite'),
				'domain_cleanup'     => __('Optionally remove the SES email identity when a domain is removed from the network', 'ultimate-multisite'),
			],
			'will_not' => [
				'mailbox_provision'  => __('Provision mailboxes or IMAP/POP3 accounts (use the Email Selling capability for that)', 'ultimate-multisite'),
				'dns_auto_create'    => __('Automatically create DNS records (you must add the provided SPF/DKIM records to your DNS manually, unless a supported DNS provider is configured)', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_hooks(): void {

		// Intercept wp_mail() to route through SES.
		add_filter('pre_wp_mail', [$this, 'intercept_wp_mail'], 10, 2);

		// React to domain lifecycle events.
		add_action('wu_domain_added', [$this, 'on_domain_added'], 10, 2);
		add_action('wu_domain_removed', [$this, 'on_domain_removed'], 10, 2);

		// Register provider status field in the Emails settings section.
		add_action('wu_settings_transactional_email', [$this, 'register_transactional_email_settings']);
	}

	/**
	 * Registers a status field in the Transactional Email Delivery settings section.
	 *
	 * Hooked to `wu_settings_transactional_email`. Displays the active SES region
	 * so administrators can confirm which provider is handling outbound email.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_transactional_email_settings(): void {

		$region = $this->get_ses()->get_region();

		wu_register_settings_field(
			'emails',
			'amazon_ses_active_region',
			[
				'title' => __('Amazon SES Active Region', 'ultimate-multisite'),
				'desc'  => sprintf(
					/* translators: %s is the AWS region identifier, e.g. us-east-1. */
					__('Outbound email is currently routed through Amazon SES in the <strong>%s</strong> region.', 'ultimate-multisite'),
					esc_html($region)
				),
				'type'  => 'note',
			]
		);
	}

	/**
	 * Gets the parent Amazon_SES_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return Amazon_SES_Integration
	 */
	private function get_ses(): Amazon_SES_Integration {

		/** @var Amazon_SES_Integration */
		return $this->get_integration();
	}

	/**
	 * Intercepts wp_mail() and routes the message through Amazon SES.
	 *
	 * Hooked to `pre_wp_mail`. Returning a non-null value short-circuits
	 * the default wp_mail() sending.
	 *
	 * @since 2.5.0
	 *
	 * @param null|bool $return Short-circuit return value (null to proceed normally).
	 * @param array     $atts   wp_mail() arguments: to, subject, message, headers, attachments.
	 * @return bool|null True on success, false on failure, null to fall through to default.
	 */
	public function intercept_wp_mail($return, array $atts) {

		if (null !== $return) {
			return $return;
		}

		$to      = $atts['to'];
		$subject = $atts['subject'];
		$message = $atts['message'];
		$headers = $atts['headers'];

		// Determine the from address for the current site.
		$from = $this->get_site_from_address();

		// Parse additional headers to extract CC, BCC, Reply-To, etc.
		$parsed_headers = $this->parse_mail_headers($headers);

		// Use the from address from headers if explicitly set.
		if ( ! empty($parsed_headers['from'])) {
			$from = $parsed_headers['from'];
		}

		// Normalize recipients to array.
		$recipients = is_array($to) ? $to : explode(',', $to);
		$recipients = array_map('trim', $recipients);

		// Build the SES v2 SendEmail request body.
		$body = [
			'FromEmailAddress' => $from,
			'Destination'      => [
				'ToAddresses' => $recipients,
			],
			'Content'          => [
				'Simple' => [
					'Subject' => [
						'Data'    => $subject,
						'Charset' => 'UTF-8',
					],
					'Body'    => $this->build_ses_body($message),
				],
			],
		];

		if ( ! empty($parsed_headers['cc'])) {
			$body['Destination']['CcAddresses'] = $parsed_headers['cc'];
		}

		if ( ! empty($parsed_headers['bcc'])) {
			$body['Destination']['BccAddresses'] = $parsed_headers['bcc'];
		}

		if ( ! empty($parsed_headers['reply-to'])) {
			$body['ReplyToAddresses'] = $parsed_headers['reply-to'];
		}

		/**
		 * Filters the Amazon SES SendEmail request body before sending.
		 *
		 * @since 2.5.0
		 *
		 * @param array $body The SES v2 SendEmail request body.
		 * @param array $atts The original wp_mail() arguments.
		 */
		$body = apply_filters('wu_ses_send_email_body', $body, $atts);

		$result = $this->get_ses()->ses_api_call('outbound-emails', 'POST', $body);

		if (is_wp_error($result)) {
			wu_log_add(
				'integration-amazon-ses',
				sprintf('Failed to send email via SES. Reason: %s', $result->get_error_message()),
				LogLevel::ERROR
			);

			return false;
		}

		wu_log_add(
			'integration-amazon-ses',
			sprintf('Email sent via SES. MessageId: %s', $result['MessageId'] ?? 'unknown')
		);

		return true;
	}

	/**
	 * Handles the wu_domain_added action.
	 *
	 * Initiates domain verification in SES when a new domain is added to the network.
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
				'integration-amazon-ses',
				sprintf(
					'Failed to initiate SES domain verification for "%s". Reason: %s',
					$domain,
					$result['message'] ?? __('Unknown error', 'ultimate-multisite')
				),
				LogLevel::ERROR
			);

			return;
		}

		wu_log_add(
			'integration-amazon-ses',
			sprintf('Initiated SES domain verification for "%s".', $domain)
		);

		/**
		 * Fires after SES domain verification has been initiated.
		 *
		 * @since 2.5.0
		 *
		 * @param string $domain     The domain name.
		 * @param int    $site_id    The site ID.
		 * @param array  $dns_records The DNS records that must be added to complete verification.
		 */
		do_action('wu_domain_verified', $domain, $site_id, $result['dns_records'] ?? []);
	}

	/**
	 * Handles the wu_domain_removed action.
	 *
	 * Optionally removes the SES email identity when a domain is removed.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name that was removed.
	 * @param int    $site_id The site ID the domain was removed from.
	 * @return void
	 */
	public function on_domain_removed(string $domain, int $site_id): void {

		/**
		 * Filters whether to delete the SES email identity when a domain is removed.
		 *
		 * @since 2.5.0
		 *
		 * @param bool   $should_delete Whether to delete the identity. Default false.
		 * @param string $domain        The domain name.
		 * @param int    $site_id       The site ID.
		 */
		$should_delete = apply_filters('wu_ses_delete_identity_on_domain_removed', false, $domain, $site_id);

		if ( ! $should_delete) {
			return;
		}

		$result = $this->get_ses()->ses_api_call(
			'identities/' . rawurlencode($domain),
			'DELETE'
		);

		if (is_wp_error($result)) {
			wu_log_add(
				'integration-amazon-ses',
				sprintf('Failed to delete SES email identity for "%s". Reason: %s', $domain, $result->get_error_message()),
				LogLevel::ERROR
			);

			return;
		}

		wu_log_add('integration-amazon-ses', sprintf('Deleted SES email identity for "%s".', $domain));
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify_domain(string $domain): array {

		$result = $this->get_ses()->ses_api_call(
			'identities',
			'POST',
			[
				'EmailIdentity' => $domain,
				'DkimSigningAttributes' => [
					'NextSigningKeyLength' => 'RSA_2048_BIT',
				],
			]
		);

		if (is_wp_error($result)) {
			return [
				'success' => false,
				'message' => $result->get_error_message(),
			];
		}

		$dns_records = $this->extract_dns_records($result, $domain);

		return [
			'success'     => true,
			'dns_records' => $dns_records,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_domain_verification_status(string $domain): array {

		$result = $this->get_ses()->ses_api_call(
			'identities/' . rawurlencode($domain)
		);

		if (is_wp_error($result)) {
			return [
				'success' => false,
				'status'  => 'unknown',
				'message' => $result->get_error_message(),
			];
		}

		$status = $result['VerifiedForSendingStatus'] ?? false
			? 'verified'
			: ($result['DkimAttributes']['Status'] ?? 'pending');

		return [
			'success' => true,
			'status'  => $status,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_domain_dns_records(string $domain): array {

		$result = $this->get_ses()->ses_api_call(
			'identities/' . rawurlencode($domain)
		);

		if (is_wp_error($result)) {
			return [
				'success' => false,
				'message' => $result->get_error_message(),
			];
		}

		$records = $this->extract_dns_records($result, $domain);

		return [
			'success'     => true,
			'dns_records' => $records,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function send_email(string $from, string $to, string $subject, string $body, array $headers = []): array {

		$result = $this->get_ses()->ses_api_call(
			'outbound-emails',
			'POST',
			[
				'FromEmailAddress' => $from,
				'Destination'      => [
					'ToAddresses' => [$to],
				],
				'Content'          => [
					'Simple' => [
						'Subject' => [
							'Data'    => $subject,
							'Charset' => 'UTF-8',
						],
						'Body'    => $this->build_ses_body($body),
					],
				],
			]
		);

		if (is_wp_error($result)) {
			return [
				'success' => false,
				'message' => $result->get_error_message(),
			];
		}

		return [
			'success'    => true,
			'message_id' => $result['MessageId'] ?? '',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_sending_statistics(string $domain, string $period = '24h'): array {

		// SES v2 exposes account-level quota via GET /v2/email/account.
		// The SendQuota object returns the total sent in the last 24 hours.
		// Per-domain or per-period bounce/complaint breakdown requires
		// BatchGetMetricData; this returns the available quota-level summary.
		$result = $this->get_ses()->ses_api_call('account');

		if (is_wp_error($result)) {
			return [
				'success' => false,
				'message' => $result->get_error_message(),
			];
		}

		$quota = $result['SendQuota'] ?? [];

		return [
			'success'    => true,
			'sent'       => (int) ($quota['SentLast24Hours'] ?? 0),
			'delivered'  => (int) ($quota['SentLast24Hours'] ?? 0),
			'bounced'    => 0,
			'complaints' => 0,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_sending_quota(string $domain, int $max_per_day): array {

		// SES v2 manages sending quotas at the account level, not per-domain.
		// This is a no-op placeholder; quota management is done via the AWS console.
		return [
			'success' => true,
			'message' => __('Sending quota management is handled at the AWS account level via the AWS console.', 'ultimate-multisite'),
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_ses()->test_connection();
	}

	/**
	 * Determines the from-address for the current site.
	 *
	 * Uses the site's domain as the from-address domain, with the
	 * WordPress admin email as the local part.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	private function get_site_from_address(): string {

		$site_domain = get_bloginfo('url');
		$parsed      = wp_parse_url($site_domain);
		$domain      = $parsed['host'] ?? get_network()->domain;

		$from_name  = get_bloginfo('name');
		$from_email = 'noreply@' . $domain;

		/**
		 * Filters the from-address used when sending via SES.
		 *
		 * @since 2.5.0
		 *
		 * @param string $from_email The from email address.
		 * @param string $from_name  The from name.
		 * @param string $domain     The current site domain.
		 */
		$from_email = apply_filters('wu_ses_from_email', $from_email, $from_name, $domain);
		$from_name  = apply_filters('wu_ses_from_name', $from_name, $from_email, $domain);

		if ($from_name) {
			return sprintf('%s <%s>', $from_name, $from_email);
		}

		return $from_email;
	}

	/**
	 * Parses wp_mail() headers into a structured array.
	 *
	 * @since 2.5.0
	 *
	 * @param string|array $headers Raw headers from wp_mail().
	 * @return array{from?: string, cc?: array, bcc?: array, reply-to?: array}
	 */
	private function parse_mail_headers($headers): array {

		$parsed = [
			'from'     => '',
			'cc'       => [],
			'bcc'      => [],
			'reply-to' => [],
		];

		if (empty($headers)) {
			return $parsed;
		}

		if ( ! is_array($headers)) {
			$headers = explode("\n", str_replace("\r\n", "\n", $headers));
		}

		foreach ($headers as $header) {
			if ( ! str_contains($header, ':')) {
				continue;
			}

			[$name, $value] = explode(':', $header, 2);
			$name  = strtolower(trim($name));
			$value = trim($value);

			switch ($name) {
				case 'from':
					$parsed['from'] = $value;
					break;
				case 'cc':
					$parsed['cc'] = array_map('trim', explode(',', $value));
					break;
				case 'bcc':
					$parsed['bcc'] = array_map('trim', explode(',', $value));
					break;
				case 'reply-to':
					$parsed['reply-to'] = array_map('trim', explode(',', $value));
					break;
			}
		}

		return $parsed;
	}

	/**
	 * Builds the SES v2 Body object from a message string.
	 *
	 * Detects whether the message is HTML or plain text.
	 *
	 * @since 2.5.0
	 *
	 * @param string $message The email body.
	 * @return array SES v2 Body structure.
	 */
	private function build_ses_body(string $message): array {

		$is_html = wp_strip_all_tags($message) !== $message;

		if ($is_html) {
			return [
				'Html' => [
					'Data'    => $message,
					'Charset' => 'UTF-8',
				],
				'Text' => [
					'Data'    => wp_strip_all_tags($message),
					'Charset' => 'UTF-8',
				],
			];
		}

		return [
			'Text' => [
				'Data'    => $message,
				'Charset' => 'UTF-8',
			],
		];
	}

	/**
	 * Extracts DNS records from an SES email identity API response.
	 *
	 * @since 2.5.0
	 *
	 * @param array  $response The SES API response.
	 * @param string $domain   The domain name.
	 * @return array<array{type: string, name: string, value: string}>
	 */
	private function extract_dns_records(array $response, string $domain): array {

		$records = [];

		// DKIM CNAME records.
		$dkim_tokens = $response['DkimAttributes']['Tokens'] ?? [];

		foreach ($dkim_tokens as $token) {
			$records[] = [
				'type'  => 'CNAME',
				'name'  => $token . '._domainkey.' . $domain,
				'value' => $token . '.dkim.amazonses.com',
			];
		}

		// DKIM EasyDKIM records (newer format).
		if ( ! empty($response['DkimAttributes']['DomainSigningSelector'])) {
			$selector  = $response['DkimAttributes']['DomainSigningSelector'];
			$records[] = [
				'type'  => 'CNAME',
				'name'  => $selector . '._domainkey.' . $domain,
				'value' => $selector . '.dkim.amazonses.com',
			];
		}

		return $records;
	}
}
