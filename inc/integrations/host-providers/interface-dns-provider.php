<?php
/**
 * DNS Provider Interface
 *
 * Defines the contract for host providers that support DNS management.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Host_Providers
 * @since 2.3.0
 */

namespace WP_Ultimo\Integrations\Host_Providers;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Interface for providers that support DNS record management.
 *
 * Host providers that can manage DNS records should implement this interface.
 * This provides a consistent API for DNS operations across different providers.
 *
 * @since 2.3.0
 */
interface DNS_Provider_Interface {

	/**
	 * Check if DNS management is supported by this provider.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if DNS management is supported.
	 */
	public function supports_dns_management(): bool;

	/**
	 * Check if DNS management is enabled for this provider.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if DNS management is enabled.
	 */
	public function is_dns_enabled(): bool;

	/**
	 * Enable DNS management for this provider.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True on success.
	 */
	public function enable_dns(): bool;

	/**
	 * Disable DNS management for this provider.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True on success.
	 */
	public function disable_dns(): bool;

	/**
	 * Get DNS records for a domain.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain The domain to query.
	 * @return array|\WP_Error Array of DNS_Record objects or WP_Error on failure.
	 */
	public function get_dns_records(string $domain);

	/**
	 * Create a DNS record for a domain.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain The domain.
	 * @param array  $record Record data containing type, name, content, ttl, and priority.
	 * @return array|\WP_Error Created record data or WP_Error on failure.
	 */
	public function create_dns_record(string $domain, array $record);

	/**
	 * Update a DNS record for a domain.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain    The domain.
	 * @param string $record_id The record identifier.
	 * @param array  $record    Updated record data.
	 * @return array|\WP_Error Updated record data or WP_Error on failure.
	 */
	public function update_dns_record(string $domain, string $record_id, array $record);

	/**
	 * Delete a DNS record for a domain.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain    The domain.
	 * @param string $record_id The record identifier.
	 * @return bool|\WP_Error True on success or WP_Error on failure.
	 */
	public function delete_dns_record(string $domain, string $record_id);

	/**
	 * Get the list of supported DNS record types.
	 *
	 * @since 2.3.0
	 *
	 * @return array Array of supported record types (e.g., ['A', 'AAAA', 'CNAME', 'MX', 'TXT']).
	 */
	public function get_supported_record_types(): array;
}
