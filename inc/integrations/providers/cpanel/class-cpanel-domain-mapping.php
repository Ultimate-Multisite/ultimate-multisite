<?php
/**
 * CPanel Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\CPanel;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * CPanel domain mapping capability module.
 *
 * Uses the shared CPanel_Integration for API access.
 *
 * @since 2.5.0
 */
class CPanel_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

	/**
	 * Supported features.
	 *
	 * @since 2.5.0
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

		$explainer_lines = [
			'will'     => [
				'send_domains' => __('Add a new Addon Domain on cPanel whenever a new domain mapping gets created on your network', 'ultimate-multisite'),
			],
			'will_not' => [],
		];

		if (is_subdomain_install()) {
			$explainer_lines['will']['send_sub_domains'] = __('Add a new SubDomain on cPanel whenever a new site gets created on your network', 'ultimate-multisite');
		}

		return $explainer_lines;
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_hooks(): void {

		add_action('wu_add_domain', [$this, 'on_add_domain'], 10, 2);
		add_action('wu_remove_domain', [$this, 'on_remove_domain'], 10, 2);
		add_action('wu_add_subdomain', [$this, 'on_add_subdomain'], 10, 2);
		add_action('wu_remove_subdomain', [$this, 'on_remove_subdomain'], 10, 2);
	}

	/**
	 * Gets the parent CPanel_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return CPanel_Integration
	 */
	private function get_cpanel(): CPanel_Integration {

		/** @var CPanel_Integration */
		return $this->get_integration();
	}

	/**
	 * Handles adding a domain to cPanel.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$root_dir = $this->get_cpanel()->get_credential('WU_CPANEL_ROOT_DIR') ?: '/public_html';

		$results = $this->get_cpanel()->load_api()->api2(
			'AddonDomain',
			'addaddondomain',
			[
				'dir'       => $root_dir,
				'newdomain' => $domain,
				'subdomain' => $this->get_subdomain($domain),
			]
		);

		$this->log_calls($results);
	}

	/**
	 * Handles removing a domain from cPanel.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$results = $this->get_cpanel()->load_api()->api2(
			'AddonDomain',
			'deladdondomain',
			[
				'domain'    => $domain,
				'subdomain' => $this->get_subdomain($domain) . '_' . $this->get_site_url(),
			]
		);

		$this->log_calls($results);
	}

	/**
	 * Handles adding a subdomain to cPanel.
	 *
	 * This happens every time a new site is added to a network running on subdomain mode.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {

		if ( ! is_subdomain_install()) {
			return;
		}

		$root_dir = $this->get_cpanel()->get_credential('WU_CPANEL_ROOT_DIR') ?: '/public_html';

		$subdomain_part = $this->get_subdomain($subdomain, false);

		$rootdomain = str_replace($subdomain_part . '.', '', $this->get_site_url($site_id));

		$results = $this->get_cpanel()->load_api()->api2(
			'SubDomain',
			'addsubdomain',
			[
				'dir'        => $root_dir,
				'domain'     => $subdomain_part,
				'rootdomain' => $rootdomain,
			]
		);

		$this->log_calls($results);
	}

	/**
	 * Handles removing a subdomain from cPanel.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {}

	/**
	 * Returns the Site URL.
	 *
	 * @since 2.5.0
	 *
	 * @param int|null $site_id The site id.
	 * @return string
	 */
	public function get_site_url(?int $site_id = null): string {

		return trim(preg_replace('#^https?://#', '', get_site_url($site_id)), '/');
	}

	/**
	 * Returns the sub-domain version of the domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain        The domain to be used.
	 * @param bool   $mapped_domain If this is a mapped domain.
	 * @return string
	 */
	public function get_subdomain(string $domain, bool $mapped_domain = true): string {

		if (false === $mapped_domain) {
			$domain_parts = explode('.', $domain);

			return array_shift($domain_parts);
		}

		return str_replace(['.', '/'], '', $domain);
	}

	/**
	 * Logs the results of the calls for debugging purposes.
	 *
	 * @since 2.5.0
	 *
	 * @param object $results Results of the cPanel call.
	 * @return void
	 */
	public function log_calls($results): void {

		if (is_object($results->cpanelresult->data)) {
			wu_log_add('integration-cpanel', $results->cpanelresult->data->reason);

			return;
		} elseif ( ! isset($results->cpanelresult->data[0])) {
			wu_log_add('integration-cpanel', __('Unexpected error ocurred trying to sync domains with CPanel', 'ultimate-multisite'), LogLevel::ERROR);

			return;
		}

		wu_log_add('integration-cpanel', $results->cpanelresult->data[0]->reason);
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_cpanel()->test_connection();
	}
}
