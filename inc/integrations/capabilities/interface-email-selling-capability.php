<?php
/**
 * Email Selling Capability Interface.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Capabilities
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Capabilities;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Interface for email selling capability modules.
 *
 * @since 2.5.0
 */
interface Email_Selling_Capability {

	/**
	 * Create an email account.
	 *
	 * @since 2.5.0
	 *
	 * @param string $email    Local part of the email address.
	 * @param string $password Account password.
	 * @param string $domain   Domain name.
	 * @param int    $quota_mb Storage quota in megabytes.
	 * @return array Result with 'success' boolean and optional 'message'.
	 */
	public function create_email_account(string $email, string $password, string $domain, int $quota_mb = 1024): array;

	/**
	 * Delete an email account.
	 *
	 * @since 2.5.0
	 *
	 * @param string $email  Local part of the email address.
	 * @param string $domain Domain name.
	 * @return array Result with 'success' boolean and optional 'message'.
	 */
	public function delete_email_account(string $email, string $domain): array;

	/**
	 * Update an email account.
	 *
	 * @since 2.5.0
	 *
	 * @param string $email  Local part of the email address.
	 * @param string $domain Domain name.
	 * @param array  $params Parameters to update (e.g. password, quota).
	 * @return array Result with 'success' boolean and optional 'message'.
	 */
	public function update_email_account(string $email, string $domain, array $params): array;

	/**
	 * Set the storage quota for an email account.
	 *
	 * @since 2.5.0
	 *
	 * @param string $email    Local part of the email address.
	 * @param string $domain   Domain name.
	 * @param int    $quota_mb Storage quota in megabytes.
	 * @return array Result with 'success' boolean and optional 'message'.
	 */
	public function set_quota(string $email, string $domain, int $quota_mb): array;

	/**
	 * List all email accounts for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @return array Result with 'success' boolean and 'data' array of accounts.
	 */
	public function list_email_accounts(string $domain): array;

	/**
	 * Create an email forwarder.
	 *
	 * @since 2.5.0
	 *
	 * @param string $email      Local part of the email address.
	 * @param string $domain     Domain name.
	 * @param string $forward_to Destination email address.
	 * @return array Result with 'success' boolean and optional 'message'.
	 */
	public function create_forwarder(string $email, string $domain, string $forward_to): array;

	/**
	 * Delete an email forwarder.
	 *
	 * @since 2.5.0
	 *
	 * @param string $email      Local part of the email address.
	 * @param string $domain     Domain name.
	 * @param string $forward_to Destination email address to remove.
	 * @return array Result with 'success' boolean and optional 'message'.
	 */
	public function delete_forwarder(string $email, string $domain, string $forward_to): array;

	/**
	 * List all forwarders for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @return array Result with 'success' boolean and 'data' array of forwarders.
	 */
	public function list_forwarders(string $domain): array;

	/**
	 * Create an autoresponder for an email account.
	 *
	 * @since 2.5.0
	 *
	 * @param string $email  Local part of the email address.
	 * @param string $domain Domain name.
	 * @param array  $config Autoresponder configuration (subject, body, interval, start, stop).
	 * @return array Result with 'success' boolean and optional 'message'.
	 */
	public function create_autoresponder(string $email, string $domain, array $config): array;

	/**
	 * Delete an autoresponder for an email account.
	 *
	 * @since 2.5.0
	 *
	 * @param string $email  Local part of the email address.
	 * @param string $domain Domain name.
	 * @return array Result with 'success' boolean and optional 'message'.
	 */
	public function delete_autoresponder(string $email, string $domain): array;

	/**
	 * List all autoresponders for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @return array Result with 'success' boolean and 'data' array of autoresponders.
	 */
	public function list_autoresponders(string $domain): array;

	/**
	 * Test the connection to the email provider.
	 *
	 * @since 2.5.0
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection();
}
