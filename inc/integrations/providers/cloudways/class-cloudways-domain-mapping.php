<?php
/**
 * Cloudways Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/Cloudways
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Cloudways;

use Psr\Log\LogLevel;
use WP_Ultimo\Domain_Mapping\Helper;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

defined('ABSPATH') || exit;

/**
 * Cloudways domain mapping capability module.
 *
 * @since 2.5.0
 */
class Cloudways_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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
				sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), 'Cloudways'),
				// translators: %s: hosting provider name.
				sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added.', 'ultimate-multisite'), 'Cloudways'),
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
		add_action('wu_domain_manager_dns_propagation_finished', [$this, 'request_ssl'], 10, 0);
	}

	/**
	 * Gets the parent Cloudways_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return Cloudways_Integration
	 */
	private function get_cloudways(): Cloudways_Integration {

		/** @var Cloudways_Integration */
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

		$this->sync_domains();
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

		$this->sync_domains();
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
	}

	/**
	 * Syncs the domains with the Cloudways API.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private function sync_domains(): void {

		$all_domains = $this->get_domains();

		$alias_response = $this->get_cloudways()->send_cloudways_request(
			'/app/manage/aliases',
			[
				'aliases' => $all_domains,
			]
		);

		if (is_wp_error($alias_response)) {
			wu_log_add('integration-cloudways', '[Alias]' . $alias_response->get_error_message(), LogLevel::ERROR);
		} else {
			wu_log_add('integration-cloudways', '[Alias]' . print_r($alias_response, true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}

	/**
	 * Runs a request to Cloudways API to install SSL.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function request_ssl(): void {

		$all_domains = $this->get_domains();

		$ssl_response = $this->get_cloudways()->send_cloudways_request(
			'/security/lets_encrypt_install',
			[
				'ssl_domains' => $this->get_valid_ssl_domains($all_domains),
			]
		);

		if (is_wp_error($ssl_response)) {
			wu_log_add('integration-cloudways', '[SSL]' . $ssl_response->get_error_message(), LogLevel::ERROR);
		} else {
			wu_log_add('integration-cloudways', '[SSL]' . print_r($ssl_response, true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}

	/**
	 * Returns an array of valid SSL domains.
	 *
	 * @since 2.5.0
	 * @param array $domains List of domains.
	 * @return array
	 */
	private function get_valid_ssl_domains(array $domains): array {

		$ssl_domains = array_unique(
			array_map(
				function ($domain) {

					if (str_starts_with($domain, '*.')) {
						$domain = str_replace('*.', '', $domain);
					}

					return $domain;
				},
				$domains
			)
		);

		$ssl_valid_domains = $this->check_domain_dns($ssl_domains, Helper::get_network_public_ip());

		$main_domain = get_current_site()->domain;

		$ssl_valid_domains[] = $main_domain;

		return array_values(array_unique(array_filter($ssl_valid_domains)));
	}

	/**
	 * Returns an array of all domains that should be added to Cloudways.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	private function get_domains(): array {

		$domain_list = $this->get_domain_list();

		foreach ($domain_list as $naked_domain) {
			if (! str_starts_with((string) $naked_domain, 'www.') && ! str_starts_with((string) $naked_domain, '*.') && \WP_Ultimo\Managers\Domain_Manager::get_instance()->should_create_www_subdomain($naked_domain)) {
				$domain_list[] = 'www.' . $naked_domain;
			}
		}

		sort($domain_list);

		return array_values(array_unique(array_filter($domain_list)));
	}

	/**
	 * Validates DNS records for domains.
	 *
	 * @since 2.5.0
	 * @param array  $domain_names Array of domain names.
	 * @param string $network_ip The IP address of the server.
	 * @return array
	 */
	private function check_domain_dns(array $domain_names, string $network_ip): array {

		$valid_domains = [];

		foreach ($domain_names as $domain_name) {
			$response = wp_remote_get('https://dns.google/resolve?name=' . $domain_name);

			if (is_wp_error($response)) {
				wu_log_add('integration-cloudways', $response->get_error_message(), LogLevel::ERROR);

				continue;
			}

			$data = json_decode(wp_remote_retrieve_body($response), true);

			if (isset($data['Answer'])) {
				foreach ($data['Answer'] as $answer) {
					if ($answer['data'] === $network_ip) {
						$valid_domains[] = $domain_name;
						break;
					}
				}
			}
		}

		return $valid_domains;
	}

	/**
	 * Returns all mapped domains on the network.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	private function get_all_mapped_domains(): array {

		global $wpdb;

		$final_domain_list = [];

		$query = "SELECT domain FROM {$wpdb->base_prefix}wu_domain_mappings";

		$suppress = $wpdb->suppress_errors();

		$mappings = $wpdb->get_col($query, 0); // phpcs:ignore

		foreach ($mappings as $domain) {
			$final_domain_list[] = $domain;

			if (! str_starts_with((string) $domain, 'www.') && \WP_Ultimo\Managers\Domain_Manager::get_instance()->should_create_www_subdomain($domain)) {
				$final_domain_list[] = "www.$domain";
			}
		}

		$wpdb->suppress_errors($suppress);

		return $final_domain_list;
	}

	/**
	 * Gets the full domain list including extra domains.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	private function get_domain_list(): array {

		$domain_list = $this->get_all_mapped_domains();

		$extra_domains = $this->get_cloudways()->get_credential('WU_CLOUDWAYS_EXTRA_DOMAINS');

		if ($extra_domains) {
			$extra_domains_list = array_filter(array_map('trim', explode(',', $extra_domains)));

			$domain_list = array_merge($domain_list, $extra_domains_list);
		}

		return $domain_list;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_cloudways()->test_connection();
	}
}
