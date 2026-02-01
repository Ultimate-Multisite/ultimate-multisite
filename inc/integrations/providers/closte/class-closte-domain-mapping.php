<?php
/**
 * Closte Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Closte;

use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Closte domain mapping capability module.
 *
 * Uses the shared Closte_Integration for API access.
 *
 * @since 2.5.0
 */
class Closte_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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
				/* translators: %s is the hosting provider name (e.g. Closte) */
				sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), 'Closte'),
				/* translators: %s is the hosting provider name (e.g. Closte) */
				sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added.', 'ultimate-multisite'), 'Closte'),
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

		// Closte needs more SSL check retries
		add_filter('wu_async_process_domain_stage_max_tries', [$this, 'ssl_tries'], 10, 2);
	}

	/**
	 * Gets the parent Closte_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return Closte_Integration
	 */
	private function get_closte(): Closte_Integration {

		/** @var Closte_Integration */
		return $this->get_integration();
	}

	/**
	 * Handles adding a domain to Closte.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		wu_log_add('integration-closte', sprintf('Adding domain: %s for site ID: %d', $domain, $site_id));

		$response = $this->get_closte()->api_call(
			'/adddomainalias',
			[
				'domain'   => $domain,
				'wildcard' => str_starts_with($domain, '*.'),
			]
		);

		if ( ! is_wp_error($response) && ! empty($response['success'])) {
			wu_log_add('integration-closte', sprintf('Domain %s added, requesting SSL', $domain));
			$this->request_ssl_certificate($domain);
		}
	}

	/**
	 * Handles removing a domain from Closte.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$this->get_closte()->api_call(
			'/deletedomainalias',
			[
				'domain'   => $domain,
				'wildcard' => str_starts_with($domain, '*.'),
			]
		);
	}

	/**
	 * Handles adding a subdomain to Closte.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {
		// Closte handles subdomains automatically.
	}

	/**
	 * Handles removing a subdomain from Closte.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {
		// Closte handles subdomains automatically.
	}

	/**
	 * Requests an SSL certificate for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain The domain.
	 * @return void
	 */
	private function request_ssl_certificate(string $domain): void {

		$ssl_endpoints = ['/ssl/install', '/installssl', '/ssl', '/certificate/install'];

		foreach ($ssl_endpoints as $endpoint) {
			$response = $this->get_closte()->api_call(
				$endpoint,
				[
					'domain' => $domain,
					'type'   => 'letsencrypt',
				]
			);

			if ( ! is_wp_error($response) && (! isset($response['error']) || ! preg_match('/HTTP [45]\d\d/', $response['error']))) {
				break;
			}
		}
	}

	/**
	 * Increases SSL check retries for Closte.
	 *
	 * @since 2.5.0
	 *
	 * @param int                      $max_tries Current max tries.
	 * @param \WP_Ultimo\Models\Domain $domain    The domain object.
	 * @return int
	 */
	public function ssl_tries($max_tries, $domain) {

		if ('checking-ssl-cert' === $domain->get_stage()) {
			return 20;
		}

		return $max_tries;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_closte()->test_connection();
	}
}
