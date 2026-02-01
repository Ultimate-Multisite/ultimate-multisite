<?php
/**
 * GridPane Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\GridPane;

use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * GridPane domain mapping capability module.
 *
 * @since 2.5.0
 */
class GridPane_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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

		return [
			'will'     => [
				// translators: %s: hosting provider name.
				sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), 'GridPane'),
				// translators: %s: hosting provider name.
				sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added.', 'ultimate-multisite'), 'GridPane'),
			],
			'will_not' => [],
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
	}

	/**
	 * Gets the parent GridPane_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return GridPane_Integration
	 */
	private function get_gridpane(): GridPane_Integration {

		/** @var GridPane_Integration */
		return $this->get_integration();
	}

	/**
	 * Handles adding a domain to GridPane.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$this->get_gridpane()->send_gridpane_api_request(
			'application/add-domain',
			[
				'server_ip'  => $this->get_gridpane()->get_credential('WU_GRIDPANE_SERVER_ID'),
				'site_url'   => $this->get_gridpane()->get_credential('WU_GRIDPANE_APP_ID'),
				'domain_url' => $domain,
			]
		);
	}

	/**
	 * Handles removing a domain from GridPane.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$this->get_gridpane()->send_gridpane_api_request(
			'application/delete-domain',
			[
				'server_ip'  => $this->get_gridpane()->get_credential('WU_GRIDPANE_SERVER_ID'),
				'site_url'   => $this->get_gridpane()->get_credential('WU_GRIDPANE_APP_ID'),
				'domain_url' => $domain,
			]
		);
	}

	/**
	 * Handles adding a subdomain to GridPane.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {
		// GridPane handles subdomains automatically.
	}

	/**
	 * Handles removing a subdomain from GridPane.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {
		// GridPane handles subdomains automatically.
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_gridpane()->test_connection();
	}
}
