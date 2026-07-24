<?php
/**
 * DirectAdmin Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/DirectAdmin
 * @since 2.5.1
 */

namespace WP_Ultimo\Integrations\Providers\DirectAdmin;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * DirectAdmin domain mapping capability module.
 *
 * Adds mapped domains as DirectAdmin domain pointer aliases to the configured
 * base domain so they resolve to the same WordPress multisite document root.
 *
 * @since 2.5.1
 */
class DirectAdmin_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

	/**
	 * Supported features.
	 *
	 * @since 2.5.1
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
				'send_domains' => __('Add domain pointer aliases in DirectAdmin whenever a new domain mapping gets created on your network', 'ultimate-multisite'),
				'autossl'      => __('Rely on DirectAdmin SSL automation to issue certificates for aliases when Let\'s Encrypt or Auto SSL is enabled on the DirectAdmin account', 'ultimate-multisite'),
			],
			'will_not' => [
				'dns'      => __('Modify registrar or external DNS records for customer domains', 'ultimate-multisite'),
				'register' => __('Register new domains', 'ultimate-multisite'),
				'email'    => __('Create DirectAdmin email accounts or mailboxes', 'ultimate-multisite'),
				'docroot'  => __('Create separate document roots for mapped domains', 'ultimate-multisite'),
			],
		];

		if (is_subdomain_install()) {
			$explainer_lines['will']['send_sub_domains'] = __('Add DirectAdmin domain pointer aliases for new subdomain sites so they use the same WordPress document root', 'ultimate-multisite');
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
	 * Called when a new domain is mapped.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain  The domain name being mapped.
	 * @param int    $site_id ID of the site receiving the mapping.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$this->add_pointer($domain);

		if (! str_starts_with($domain, 'www.') && \WP_Ultimo\Managers\Domain_Manager::get_instance()->should_create_www_subdomain($domain)) {
			$this->add_pointer('www.' . $domain);
		}
	}

	/**
	 * Called when a mapped domain is removed.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain  The domain name being removed.
	 * @param int    $site_id ID of the site.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$this->delete_pointer($domain);

		if (! str_starts_with($domain, 'www.')) {
			$this->delete_pointer('www.' . $domain);
		}
	}

	/**
	 * Called when a new subdomain site is added.
	 *
	 * @since 2.5.1
	 *
	 * @param string $subdomain The subdomain being added.
	 * @param int    $site_id   ID of the site.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {

		$this->add_pointer($subdomain);
	}

	/**
	 * Called when a subdomain site is removed.
	 *
	 * @since 2.5.1
	 *
	 * @param string $subdomain The subdomain being removed.
	 * @param int    $site_id   ID of the site.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {

		$this->delete_pointer($subdomain);
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_directadmin()->test_connection();
	}

	/**
	 * Gets the parent DirectAdmin integration for API calls.
	 *
	 * @since 2.5.1
	 * @return DirectAdmin_Integration
	 */
	private function get_directadmin(): DirectAdmin_Integration {

		/** @var DirectAdmin_Integration */
		return $this->get_integration();
	}

	/**
	 * Adds a DirectAdmin domain pointer alias.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain Domain pointer hostname.
	 * @return void
	 */
	private function add_pointer(string $domain): void {

		$base_domain = $this->get_base_domain();
		$domain      = $this->normalize_hostname($domain);

		if (empty($base_domain) || empty($domain)) {
			wu_log_add('integration-directadmin', __('Missing DirectAdmin base domain or mapped domain; cannot add domain pointer.', 'ultimate-multisite'), LogLevel::ERROR);

			return;
		}

		$this->log_response(
			sprintf(
				/* translators: %s: domain pointer hostname. */
				__('Add domain pointer %s', 'ultimate-multisite'),
				$domain
			),
			$this->get_directadmin()->directadmin_api_request(
				'/CMD_API_DOMAIN_POINTER',
				'POST',
				[
					'domain' => $base_domain,
					'action' => 'add',
					'from'   => $domain,
					'alias'  => 'yes',
				]
			)
		);
	}

	/**
	 * Deletes a DirectAdmin domain pointer alias.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain Domain pointer hostname.
	 * @return void
	 */
	private function delete_pointer(string $domain): void {

		$base_domain = $this->get_base_domain();
		$domain      = $this->normalize_hostname($domain);

		if (empty($base_domain) || empty($domain)) {
			wu_log_add('integration-directadmin', __('Missing DirectAdmin base domain or mapped domain; cannot delete domain pointer.', 'ultimate-multisite'), LogLevel::ERROR);

			return;
		}

		$this->log_response(
			sprintf(
				/* translators: %s: domain pointer hostname. */
				__('Delete domain pointer %s', 'ultimate-multisite'),
				$domain
			),
			$this->get_directadmin()->directadmin_api_request(
				'/CMD_API_DOMAIN_POINTER',
				'POST',
				[
					'domain'  => $base_domain,
					'action'  => 'delete',
					'select0' => $domain,
				]
			)
		);
	}

	/**
	 * Returns the configured DirectAdmin base domain.
	 *
	 * @since 2.5.1
	 * @return string
	 */
	private function get_base_domain(): string {

		return $this->normalize_hostname($this->get_directadmin()->get_credential('WU_DIRECTADMIN_DOMAIN'));
	}

	/**
	 * Normalizes hostnames received from domain mapping hooks.
	 *
	 * @since 2.5.1
	 *
	 * @param string $hostname Hostname.
	 * @return string
	 */
	private function normalize_hostname(string $hostname): string {

		$hostname = preg_replace('#^https?://#i', '', $hostname);
		$hostname = preg_replace('#/.*$#', '', (string) $hostname);
		$hostname = strtolower(trim((string) $hostname, " \t\n\r\0\x0B."));

		return $hostname;
	}

	/**
	 * Log an API response with a contextual label.
	 *
	 * @since 2.5.1
	 *
	 * @param string          $action_label Descriptive label for the action.
	 * @param array|\WP_Error $response     The API response.
	 * @return void
	 */
	protected function log_response(string $action_label, $response): void {

		if (is_wp_error($response)) {
			wu_log_add('integration-directadmin', sprintf('[%s] %s', $action_label, $response->get_error_message()), LogLevel::ERROR);

			return;
		}

		wu_log_add('integration-directadmin', sprintf('[%s] %s', $action_label, wp_json_encode($response)));
	}
}
