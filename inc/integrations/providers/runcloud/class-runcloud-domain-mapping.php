<?php
/**
 * RunCloud Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/RunCloud
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\RunCloud;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

defined('ABSPATH') || exit;

/**
 * RunCloud domain mapping capability module.
 *
 * @since 2.5.0
 */
class RunCloud_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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
				sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), 'RunCloud'),
				// translators: %s: hosting provider name.
				sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added.', 'ultimate-multisite'), 'RunCloud'),
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
	 * Gets the parent RunCloud_Integration.
	 *
	 * @since 2.5.0
	 * @return RunCloud_Integration
	 */
	private function get_runcloud(): RunCloud_Integration {

		/** @var RunCloud_Integration */
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

		$success = false;

		$response = $this->get_runcloud()->send_runcloud_request(
			$this->get_runcloud()->get_runcloud_base_url('domains'),
			[
				'name'        => $domain,
				'www'         => true,
				'redirection' => 'non-www',
				'type'        => 'alias',
			],
			'POST'
		);

		if (is_wp_error($response)) {
			wu_log_add('integration-runcloud', 'Add Domain Error: ' . $response->get_error_message(), LogLevel::ERROR);
		} else {
			$success = true;
			wu_log_add('integration-runcloud', 'Domain Added: ' . wp_remote_retrieve_body($response));
		}

		if ($success) {
			$ssl_id = $this->get_runcloud_ssl_id();
			if ($ssl_id) {
				$this->redeploy_runcloud_ssl($ssl_id);
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

		$domain_id = $this->get_runcloud_domain_id($domain);

		if ( ! $domain_id) {
			wu_log_add('integration-runcloud', __('Domain not found: ', 'ultimate-multisite') . $domain);

			return;
		}

		$response = $this->get_runcloud()->send_runcloud_request(
			$this->get_runcloud()->get_runcloud_base_url("domains/$domain_id"),
			[],
			'DELETE'
		);

		if (is_wp_error($response)) {
			wu_log_add('integration-runcloud', 'Remove Domain Error: ' . $response->get_error_message(), LogLevel::ERROR);
		} else {
			wu_log_add('integration-runcloud', 'Domain Removed: ' . wp_remote_retrieve_body($response));
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
	 * Finds domain ID in RunCloud.
	 *
	 * @since 2.5.0
	 * @param string $domain Domain name.
	 * @return int|false
	 */
	private function get_runcloud_domain_id(string $domain) {

		$response = $this->get_runcloud()->send_runcloud_request($this->get_runcloud()->get_runcloud_base_url('domains'), [], 'GET');
		$data     = $this->get_runcloud()->maybe_return_runcloud_body($response);

		if (is_object($data) && isset($data->data) && is_array($data->data)) {
			foreach ($data->data as $item) {
				if (isset($item->name) && $item->name === $domain) {
					return $item->id;
				}
			}
		}

		wu_log_add('integration-runcloud', "Domain $domain not found in response");

		return false;
	}

	/**
	 * Retrieves SSL certificate ID.
	 *
	 * @since 2.5.0
	 * @return int|false
	 */
	private function get_runcloud_ssl_id() {

		$response = $this->get_runcloud()->send_runcloud_request($this->get_runcloud()->get_runcloud_base_url('ssl/advanced'), [], 'GET');
		$data     = $this->get_runcloud()->maybe_return_runcloud_body($response);

		if (is_object($data) && isset($data->sslCertificate) && isset($data->sslCertificate->id)) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return $data->sslCertificate->id; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		wu_log_add('integration-runcloud', 'SSL Certificate not found');

		return false;
	}

	/**
	 * Redeploys SSL certificate.
	 *
	 * @since 2.5.0
	 * @param int $ssl_id SSL certificate ID.
	 * @return void
	 */
	private function redeploy_runcloud_ssl(int $ssl_id): void {

		$response = $this->get_runcloud()->send_runcloud_request(
			$this->get_runcloud()->get_runcloud_base_url("ssl/advanced/$ssl_id/redeploy"),
			[],
			'PATCH'
		);

		if (is_wp_error($response)) {
			wu_log_add('integration-runcloud', 'SSL Redeploy Error: ' . $response->get_error_message(), LogLevel::ERROR);
		} else {
			wu_log_add('integration-runcloud', 'SSL Redeploy Successful: ' . wp_remote_retrieve_body($response));
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_runcloud()->test_connection();
	}
}
