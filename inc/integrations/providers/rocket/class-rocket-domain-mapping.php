<?php
/**
 * Rocket.net Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Rocket;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Rocket.net domain mapping capability module.
 *
 * @since 2.5.0
 */
class Rocket_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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
				sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), 'Rocket.net'),
				// translators: %s: hosting provider name.
				sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added.', 'ultimate-multisite'), 'Rocket.net'),
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
	 * Gets the parent Rocket_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return Rocket_Integration
	 */
	private function get_rocket(): Rocket_Integration {

		/** @var Rocket_Integration */
		return $this->get_integration();
	}

	/**
	 * Called when a new domain is mapped.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name being mapped.
	 * @param int    $site_id ID of the site receiving the mapping.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$response = $this->get_rocket()->send_rocket_request(
			'domains',
			[
				'domain' => $domain,
			],
			'POST'
		);

		if (is_wp_error($response)) {
			wu_log_add('integration-rocket', sprintf('[Add Domain] %s: %s', $domain, $response->get_error_message()), LogLevel::ERROR);
		} else {
			$response_code = wp_remote_retrieve_response_code($response);
			$response_body = wp_remote_retrieve_body($response);

			if (200 === $response_code || 201 === $response_code) {
				wu_log_add('integration-rocket', sprintf('[Add Domain] %s: Success - %s', $domain, $response_body));
			} else {
				wu_log_add('integration-rocket', sprintf('[Add Domain] %s: Failed (HTTP %d) - %s', $domain, $response_code, $response_body), LogLevel::ERROR);
			}
		}
	}

	/**
	 * Called when a mapped domain is removed.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name being removed.
	 * @param int    $site_id ID of the site.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$domain_id = $this->get_rocket_domain_id($domain);

		if (! $domain_id) {
			wu_log_add('integration-rocket', sprintf('[Remove Domain] %s: Domain not found on Rocket.net', $domain), LogLevel::WARNING);

			return;
		}

		$response = $this->get_rocket()->send_rocket_request("domains/$domain_id", [], 'DELETE');

		if (is_wp_error($response)) {
			wu_log_add('integration-rocket', sprintf('[Remove Domain] %s: %s', $domain, $response->get_error_message()), LogLevel::ERROR);
		} else {
			$response_code = wp_remote_retrieve_response_code($response);
			$response_body = wp_remote_retrieve_body($response);

			if (200 === $response_code || 204 === $response_code) {
				wu_log_add('integration-rocket', sprintf('[Remove Domain] %s: Success - %s', $domain, $response_body));
			} else {
				wu_log_add('integration-rocket', sprintf('[Remove Domain] %s: Failed (HTTP %d) - %s', $domain, $response_code, $response_body), LogLevel::ERROR);
			}
		}
	}

	/**
	 * Called when a new subdomain is added.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain being added.
	 * @param int    $site_id   ID of the site.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {
		// Rocket.net manages subdomains automatically via wildcard SSL.
	}

	/**
	 * Called when a subdomain is removed.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain being removed.
	 * @param int    $site_id   ID of the site.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {
		// Rocket.net manages subdomains automatically via wildcard SSL.
	}

	/**
	 * Returns the Rocket.net domain ID for a given domain name.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain The domain name.
	 * @return int|false The domain ID or false if not found.
	 */
	private function get_rocket_domain_id(string $domain) {

		$response = $this->get_rocket()->send_rocket_request('domains', [], 'GET');

		if (is_wp_error($response)) {
			wu_log_add('integration-rocket', '[Get Domain ID] ' . $response->get_error_message(), LogLevel::ERROR);

			return false;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		$domains = $body['data'] ?? $body['domains'] ?? $body;

		if (is_array($domains)) {
			foreach ($domains as $remote_domain) {
				$domain_name = $remote_domain['domain'] ?? $remote_domain['name'] ?? null;

				if ($domain_name === $domain) {
					return $remote_domain['id'] ?? false;
				}
			}
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_rocket()->test_connection();
	}
}
