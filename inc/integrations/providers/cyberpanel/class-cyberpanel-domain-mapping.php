<?php
/**
 * CyberPanel Domain Mapping Capability.
 *
 * Handles adding/removing custom domains on CyberPanel and issuing SSL
 * certificates. Uses the standard CyberPanel API at /api/{endpoint}.
 *
 * Flow for custom domains:
 * 1. User maps a domain in Ultimate Multisite -> wu_add_domain fires
 * 2. This module calls CyberPanel createWebsite to add the domain
 * 3. Then issues SSL via CyberPanel's acme.sh/Let's Encrypt integration
 * 4. User points DNS A record to server IP -> domain works
 *
 * Flow for subdomains:
 * 1. Ultimate Multisite creates a site -> wu_add_subdomain fires
 * 2. Wildcard DNS + wildcard SSL handle it automatically -> NOOP
 *
 * @see https://documenter.getpostman.com/view/2s8Yt1s9Pf
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/CyberPanel
 * @since 2.6.0
 */

namespace WP_Ultimo\Integrations\Providers\CyberPanel;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * CyberPanel domain mapping capability module.
 *
 * Uses the shared CyberPanel_Integration for API access.
 *
 * @since 2.6.0
 */
class CyberPanel_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

	/**
	 * Supported features.
	 *
	 * @since 2.6.0
	 * @var array
	 */
	protected array $supported_features = ['autossl'];

	/**
	 * {@inheritdoc}
	 */
	public function get_capability_id(): string {

		return 'domain-mapping';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title(): string {

		return __('Domain Mapping', 'ultimate-multisite');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_explainer_lines(): array {

		return [
			'will'     => [
				__('Create a website on CyberPanel when a custom domain is mapped.', 'ultimate-multisite'),
				__('Automatically issue a Let\'s Encrypt SSL certificate for the mapped domain.', 'ultimate-multisite'),
			],
			'will_not' => [
				__('Subdomains are handled automatically via wildcard DNS and SSL — no action needed.', 'ultimate-multisite'),
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_hooks(): void {

		add_action('wu_add_domain', [$this, 'on_add_domain'], 10, 2);
		add_action('wu_remove_domain', [$this, 'on_remove_domain'], 10, 2);
		add_action('wu_add_subdomain', [$this, 'on_add_subdomain'], 10, 2);
		add_action('wu_remove_subdomain', [$this, 'on_remove_subdomain'], 10, 2);

		// Give SSL more time to propagate — Let's Encrypt can take a minute to verify DNS
		add_filter('wu_async_process_domain_stage_max_tries', [$this, 'ssl_tries'], 10, 2);
	}

	/**
	 * Gets the parent CyberPanel_Integration for API calls.
	 *
	 * @since 2.6.0
	 * @return CyberPanel_Integration
	 */
	private function get_cyberpanel(): CyberPanel_Integration {

		/** @var CyberPanel_Integration */
		return $this->get_integration();
	}

	/**
	 * Called when a new domain is mapped.
	 *
	 * Creates a website on CyberPanel for the mapped domain, then
	 * explicitly issues an SSL certificate as a safety net.
	 *
	 * @since 2.6.0
	 *
	 * @param string $domain  The domain name being mapped (e.g. 'example.com').
	 * @param int    $site_id ID of the site receiving the mapping.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$master_domain = $this->get_cyberpanel()->get_master_domain();

		wu_log_add('integration-cyberpanel', sprintf(
			'Adding domain: %s for site ID: %d (master: %s)',
			$domain,
			$site_id,
			$master_domain
		));

		// Step 1: Create website on CyberPanel for this domain
		$result = $this->create_website($domain, $master_domain);

		if (is_wp_error($result)) {
			wu_log_add('integration-cyberpanel', 'Failed to create website: ' . $result->get_error_message(), LogLevel::ERROR);

			return;
		}

		wu_log_add('integration-cyberpanel', sprintf('Website %s created, requesting SSL...', $domain));

		// Step 2: Issue SSL certificate explicitly as a safety net
		$this->issue_ssl($domain);
	}

	/**
	 * Called when a mapped domain is removed.
	 *
	 * Deletes the website from CyberPanel.
	 *
	 * @since 2.6.0
	 *
	 * @param string $domain  The domain name being removed.
	 * @param int    $site_id ID of the site.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		wu_log_add('integration-cyberpanel', sprintf('Removing domain: %s for site ID: %d', $domain, $site_id));

		$this->delete_website($domain);
	}

	/**
	 * Called when a new subdomain is added.
	 *
	 * NOOP — wildcard DNS + wildcard SSL cover all subdomains automatically.
	 *
	 * @since 2.6.0
	 *
	 * @param string $subdomain The subdomain being added.
	 * @param int    $site_id   ID of the site.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {

		// Wildcard handles this automatically
		wu_log_add('integration-cyberpanel', sprintf('Subdomain %s — handled by wildcard, no action needed.', $subdomain));
	}

	/**
	 * Called when a subdomain is removed.
	 *
	 * NOOP — wildcard DNS + SSL cover it.
	 *
	 * @since 2.6.0
	 *
	 * @param string $subdomain The subdomain being removed.
	 * @param int    $site_id   ID of the site.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {

		// Wildcard handles this
	}

	/**
	 * Create a website on CyberPanel for the mapped domain.
	 *
	 * CyberPanel's standard API does not have a dedicated child-domain
	 * endpoint, so we use createWebsite to add the domain as a website
	 * under the same server. The ssl=1 flag triggers automatic Let's
	 * Encrypt issuance during creation.
	 *
	 * @since 2.6.0
	 *
	 * @param string $domain        The domain to add.
	 * @param string $master_domain The main CyberPanel website domain.
	 * @return array|\WP_Error
	 */
	private function create_website(string $domain, string $master_domain) {

		$username = $this->get_cyberpanel()->get_credential('WU_CYBERPANEL_USERNAME');

		return $this->get_cyberpanel()->api_call('createWebsite', [
			'domainName'    => $domain,
			'ownerEmail'    => 'ssl@' . $master_domain,
			'packageName'   => 'Default',
			'websiteOwner'  => $username,
			'ownerPassword' => wp_generate_password(24, false), // Random, not used for login
			'phpSelection'  => 'PHP 8.3',
			'ssl'           => 1,
		]);
	}

	/**
	 * Delete a website from CyberPanel.
	 *
	 * @since 2.6.0
	 *
	 * @param string $domain The domain to remove.
	 * @return array|\WP_Error
	 */
	private function delete_website(string $domain) {

		return $this->get_cyberpanel()->api_call('deleteWebsite', [
			'domainName' => $domain,
		]);
	}

	/**
	 * Issue an SSL certificate for a domain via CyberPanel.
	 *
	 * CyberPanel uses acme.sh internally for Let's Encrypt. While
	 * createWebsite with ssl=1 auto-issues SSL, we also trigger it
	 * explicitly as a safety net.
	 *
	 * @since 2.6.0
	 *
	 * @param string $domain The domain to issue SSL for.
	 * @return void
	 */
	private function issue_ssl(string $domain): void {

		$result = $this->get_cyberpanel()->api_call('submitWebsiteStatus', [
			'websiteName' => $domain,
			'state'       => 'issueSSL',
		]);

		if (is_wp_error($result)) {
			wu_log_add('integration-cyberpanel', 'SSL issuance failed for ' . $domain . ': ' . $result->get_error_message(), LogLevel::ERROR);
		} else {
			wu_log_add('integration-cyberpanel', 'SSL issued for ' . $domain);
		}
	}

	/**
	 * Increase SSL check retries for CyberPanel.
	 *
	 * Let's Encrypt can take a minute to verify DNS, so we allow more
	 * retries than the default when checking SSL cert status.
	 *
	 * @since 2.6.0
	 *
	 * @param int    $max_tries Current max tries.
	 * @param object $domain    The domain object.
	 * @return int
	 */
	public function ssl_tries($max_tries, $domain) {

		if (method_exists($domain, 'get_stage') && 'checking-ssl-cert' === $domain->get_stage()) {
			return 30; // More retries since we control the server
		}

		return $max_tries;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_cyberpanel()->test_connection();
	}
}
