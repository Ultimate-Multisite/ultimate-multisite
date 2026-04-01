<?php
/**
 * Transactional Email Capability Interface.
 *
 * Defines the contract for transactional email delivery providers.
 * This is distinct from Email_Selling_Capability (mailbox provisioning) —
 * transactional email is about delivery routing, not mailbox management.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Capabilities
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Capabilities;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Interface for transactional email delivery capability modules.
 *
 * Implementing this interface allows an integration to intercept wp_mail()
 * calls and route them through a configured transactional email provider,
 * using the correct from-address for the current site's domain.
 *
 * @since 2.5.0
 */
interface Transactional_Email_Capability {

	/**
	 * Initiate domain verification with the provider.
	 *
	 * Triggers the provider to begin the domain verification process and
	 * returns the DNS records that must be added to complete verification.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain The domain name to verify.
	 * @return array{
	 *   success: bool,
	 *   message?: string,
	 *   dns_records?: array
	 * }
	 */
	public function verify_domain(string $domain): array;

	/**
	 * Get the current verification status for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain The domain name to check.
	 * @return array{
	 *   success: bool,
	 *   status: string,
	 *   message?: string
	 * }
	 */
	public function get_domain_verification_status(string $domain): array;

	/**
	 * Get the DNS records required for domain verification.
	 *
	 * Returns the SPF, DKIM, and DMARC records that must be added to the
	 * domain's DNS configuration to complete verification.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain The domain name.
	 * @return array{
	 *   success: bool,
	 *   dns_records?: array<array{type: string, name: string, value: string}>,
	 *   message?: string
	 * }
	 */
	public function get_domain_dns_records(string $domain): array;

	/**
	 * Send a transactional email through the provider.
	 *
	 * @since 2.5.0
	 *
	 * @param string $from    The sender email address.
	 * @param string $to      The recipient email address.
	 * @param string $subject The email subject.
	 * @param string $body    The email body (HTML or plain text).
	 * @param array  $headers Optional additional headers.
	 * @return array{
	 *   success: bool,
	 *   message_id?: string,
	 *   message?: string
	 * }
	 */
	public function send_email(string $from, string $to, string $subject, string $body, array $headers = []): array;

	/**
	 * Get sending statistics for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain The domain name.
	 * @param string $period The time period for statistics (e.g. '24h', '7d', '30d').
	 * @return array{
	 *   success: bool,
	 *   sent?: int,
	 *   delivered?: int,
	 *   bounced?: int,
	 *   complaints?: int,
	 *   message?: string
	 * }
	 */
	public function get_sending_statistics(string $domain, string $period = '24h'): array;

	/**
	 * Set the daily sending quota for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain      The domain name.
	 * @param int    $max_per_day Maximum emails allowed per day.
	 * @return array{
	 *   success: bool,
	 *   message?: string
	 * }
	 */
	public function set_sending_quota(string $domain, int $max_per_day): array;

	/**
	 * Test the connection to the transactional email provider.
	 *
	 * @since 2.5.0
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection();
}
