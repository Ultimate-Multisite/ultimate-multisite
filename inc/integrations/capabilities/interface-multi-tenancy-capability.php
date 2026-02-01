<?php
/**
 * Multi-Tenancy Capability Interface.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Capabilities
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Capabilities;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Interface for multi-tenancy capability modules.
 *
 * @since 2.5.0
 */
interface Multi_Tenancy_Capability {

	/**
	 * Create a WordPress site on the remote server.
	 *
	 * @since 2.5.0
	 *
	 * @param array $site_data Site creation data.
	 * @return array|\WP_Error Creation result.
	 */
	public function create_wordpress_site(array $site_data);

	/**
	 * Delete a WordPress site from the remote server.
	 *
	 * @since 2.5.0
	 *
	 * @param string $site_identifier Site identifier.
	 * @return bool|\WP_Error
	 */
	public function delete_wordpress_site(string $site_identifier);

	/**
	 * Get the status of a remote site.
	 *
	 * @since 2.5.0
	 *
	 * @param string $site_identifier Site identifier.
	 * @return string|\WP_Error Status string or error.
	 */
	public function get_site_status(string $site_identifier);

	/**
	 * Add a domain to a remote site.
	 *
	 * @since 2.5.0
	 *
	 * @param string $site_identifier Site identifier.
	 * @param string $domain          The domain to add.
	 * @return bool|\WP_Error
	 */
	public function add_domain(string $site_identifier, string $domain);

	/**
	 * Remove a domain from a remote site.
	 *
	 * @since 2.5.0
	 *
	 * @param string $site_identifier Site identifier.
	 * @param string $domain          The domain to remove.
	 * @return bool|\WP_Error
	 */
	public function remove_domain(string $site_identifier, string $domain);

	/**
	 * Create a database on the remote server.
	 *
	 * @since 2.5.0
	 *
	 * @param array $database_data Database creation data.
	 * @return array|\WP_Error Creation result.
	 */
	public function create_database(array $database_data);

	/**
	 * Delete a database on the remote server.
	 *
	 * @since 2.5.0
	 *
	 * @param string $database_identifier Database identifier.
	 * @return bool|\WP_Error
	 */
	public function delete_database(string $database_identifier);

	/**
	 * Upload files to a remote site.
	 *
	 * @since 2.5.0
	 *
	 * @param string $site_identifier Site identifier.
	 * @param array  $files           Files to upload.
	 * @return bool|\WP_Error
	 */
	public function upload_files(string $site_identifier, array $files);

	/**
	 * Download files from a remote site.
	 *
	 * @since 2.5.0
	 *
	 * @param string $site_identifier Site identifier.
	 * @param array  $file_paths      File paths to download.
	 * @return array|\WP_Error Downloaded files data.
	 */
	public function download_files(string $site_identifier, array $file_paths);

	/**
	 * Migrate a site between servers.
	 *
	 * @since 2.5.0
	 *
	 * @param string $site_identifier       Site identifier.
	 * @param string $target_server         Target server identifier.
	 * @param array  $options               Migration options.
	 * @return array|\WP_Error Migration result.
	 */
	public function migrate_site(string $site_identifier, string $target_server, array $options = []);
}
