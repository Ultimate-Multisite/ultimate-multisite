<?php
/**
 * ServerPilot Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\ServerPilot;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * ServerPilot domain mapping capability module.
 *
 * Uses the shared ServerPilot_Integration for API access.
 *
 * @since 2.5.0
 */
class ServerPilot_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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
				/* translators: %s is the hosting provider name */
				sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), 'ServerPilot'),
				/* translators: %s is the hosting provider name */
				sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added.', 'ultimate-multisite'), 'ServerPilot'),
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
	 * Gets the parent ServerPilot_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return ServerPilot_Integration
	 */
	private function get_serverpilot(): ServerPilot_Integration {

		/** @var ServerPilot_Integration */
		return $this->get_integration();
	}

	/**
	 * Handles adding a domain to ServerPilot.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$current_domain_list = $this->get_server_pilot_domains();

		if ($current_domain_list && is_array($current_domain_list)) {
			$domains_to_add = [$domain];

			if (\WP_Ultimo\Managers\Domain_Manager::get_instance()->should_create_www_subdomain($domain)) {
				$domains_to_add[] = 'www.' . $domain;
			}

			$this->get_serverpilot()->send_server_pilot_api_request(
				'',
				[
					'domains' => array_merge($current_domain_list, $domains_to_add),
				]
			);

			$this->turn_server_pilot_auto_ssl_on();
		}
	}

	/**
	 * Handles removing a domain from ServerPilot.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$current_domain_list = $this->get_server_pilot_domains();

		if ($current_domain_list && is_array($current_domain_list)) {
			$current_domain_list = array_filter(
				$current_domain_list,
				fn($remote_domain) => $remote_domain !== $domain && 'www.' . $domain !== $remote_domain
			);

			$this->get_serverpilot()->send_server_pilot_api_request(
				'',
				[
					'domains' => $current_domain_list,
				]
			);
		}
	}

	/**
	 * Handles adding a subdomain to ServerPilot.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {

		$current_domain_list = $this->get_server_pilot_domains();

		if ($current_domain_list && is_array($current_domain_list)) {
			$this->get_serverpilot()->send_server_pilot_api_request(
				'',
				[
					'domains' => array_merge($current_domain_list, [$subdomain]),
				]
			);

			$this->turn_server_pilot_auto_ssl_on();
		}
	}

	/**
	 * Handles removing a subdomain from ServerPilot.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {}

	/**
	 * Get the current list of domains added on ServerPilot.
	 *
	 * @since 2.5.0
	 * @return array|false
	 */
	public function get_server_pilot_domains() {

		$app_info = $this->get_serverpilot()->send_server_pilot_api_request('', [], 'GET');

		if (isset($app_info['data']['domains'])) {
			return $app_info['data']['domains'];
		}

		// translators: %s is the wp_json_encode of the error.
		wu_log_add('integration-serverpilot', sprintf(__('An error occurred while trying to get the current list of domains: %s', 'ultimate-multisite'), wp_json_encode($app_info)), LogLevel::ERROR);

		return false;
	}

	/**
	 * Makes sure ServerPilot autoSSL is always on, when possible.
	 *
	 * @since 2.5.0
	 * @return array|\WP_Error
	 */
	public function turn_server_pilot_auto_ssl_on() {

		return $this->get_serverpilot()->send_server_pilot_api_request(
			'/ssl',
			[
				'auto' => true,
			]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_serverpilot()->test_connection();
	}
}
