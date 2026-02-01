<?php
/**
 * Domain Selling Capability Interface.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Capabilities
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Capabilities;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Interface for domain selling capability modules.
 *
 * @since 2.5.0
 */
interface Domain_Selling_Capability {

	/**
	 * Search for available domains.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name Domain name without TLD.
	 * @param array  $tlds        Array of TLDs to check.
	 * @return array Search results.
	 */
	public function search_domains(string $domain_name, array $tlds = []): array;

	/**
	 * Register a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name    Full domain name.
	 * @param array  $registrant_info Registrant information.
	 * @param int    $years          Registration period.
	 * @return array Registration result.
	 */
	public function register_domain(string $domain_name, array $registrant_info, int $years = 1): array;

	/**
	 * Renew a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name Full domain name.
	 * @param int    $years       Renewal period.
	 * @return array Renewal result.
	 */
	public function renew_domain(string $domain_name, int $years = 1): array;

	/**
	 * Get domain information.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name Full domain name.
	 * @return array Domain information.
	 */
	public function get_domain_info(string $domain_name): array;

	/**
	 * Update domain nameservers.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name  Full domain name.
	 * @param array  $nameservers Array of nameservers.
	 * @return array Update result.
	 */
	public function update_nameservers(string $domain_name, array $nameservers): array;

	/**
	 * Enable domain lock.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name Full domain name.
	 * @return array Result.
	 */
	public function enable_domain_lock(string $domain_name): array;

	/**
	 * Disable domain lock.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name Full domain name.
	 * @return array Result.
	 */
	public function disable_domain_lock(string $domain_name): array;

	/**
	 * Get EPP authorization code.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name Full domain name.
	 * @return array Result with auth code.
	 */
	public function get_epp_code(string $domain_name): array;

	/**
	 * Transfer a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name    Full domain name.
	 * @param string $auth_code      Authorization code.
	 * @param array  $registrant_info Registrant information.
	 * @param array  $options        Transfer options.
	 * @return array Transfer result.
	 */
	public function transfer_domain(string $domain_name, string $auth_code, array $registrant_info, array $options = []): array;

	/**
	 * Get DNS records for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name Full domain name.
	 * @return array DNS records.
	 */
	public function get_dns_records(string $domain_name): array;

	/**
	 * Add a DNS record.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name Full domain name.
	 * @param array  $record_data Record data.
	 * @return array Result.
	 */
	public function add_dns_record(string $domain_name, array $record_data): array;

	/**
	 * Enable WHOIS privacy.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain_name Full domain name.
	 * @return array Result.
	 */
	public function enable_whois_privacy(string $domain_name): array;
}
