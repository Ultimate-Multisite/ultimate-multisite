<?php
/**
 * Hestia Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/Hestia
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Hestia;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

defined('ABSPATH') || exit;

/**
 * Hestia domain mapping capability module.
 *
 * @since 2.5.0
 */
class Hestia_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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
				sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), 'Hestia'),
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
	 * Gets the parent Hestia_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return Hestia_Integration
	 */
	private function get_hestia(): Hestia_Integration {

		/** @var Hestia_Integration */
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

		$account     = $this->get_hestia()->get_credential('WU_HESTIA_ACCOUNT');
		$base_domain = $this->get_hestia()->get_credential('WU_HESTIA_WEB_DOMAIN');
		$restart     = $this->get_hestia()->get_credential('WU_HESTIA_RESTART') ?: 'yes';

		if (empty($account) || empty($base_domain)) {
			wu_log_add('integration-hestia', __('Missing WU_HESTIA_ACCOUNT or WU_HESTIA_WEB_DOMAIN; cannot add alias.', 'ultimate-multisite'), LogLevel::ERROR);

			return;
		}

		// Add primary alias
		$this->call_and_log('v-add-web-domain-alias', [$account, $base_domain, $domain, $restart], sprintf('Add alias %s', $domain));

		// Optionally add www alias if configured
		if (! str_starts_with($domain, 'www.') && \WP_Ultimo\Managers\Domain_Manager::get_instance()->should_create_www_subdomain($domain)) {
			$www = 'www.' . $domain;
			$this->call_and_log('v-add-web-domain-alias', [$account, $base_domain, $www, $restart], sprintf('Add alias %s', $www));
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

		$account     = $this->get_hestia()->get_credential('WU_HESTIA_ACCOUNT');
		$base_domain = $this->get_hestia()->get_credential('WU_HESTIA_WEB_DOMAIN');
		$restart     = $this->get_hestia()->get_credential('WU_HESTIA_RESTART') ?: 'yes';

		if (empty($account) || empty($base_domain)) {
			wu_log_add('integration-hestia', __('Missing WU_HESTIA_ACCOUNT or WU_HESTIA_WEB_DOMAIN; cannot remove alias.', 'ultimate-multisite'), LogLevel::ERROR);

			return;
		}

		// Remove primary alias
		$this->call_and_log('v-delete-web-domain-alias', [$account, $base_domain, $domain, $restart], sprintf('Delete alias %s', $domain));

		// Also try to remove www alias
		if (! str_starts_with($domain, 'www.')) {
			$www = 'www.' . $domain;
			$this->call_and_log('v-delete-web-domain-alias', [$account, $base_domain, $www, $restart], sprintf('Delete alias %s', $www));
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
	public function on_add_subdomain(string $subdomain, int $site_id): void {}

	/**
	 * Called when a subdomain is removed.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain being removed.
	 * @param int    $site_id   ID of the site.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {}

	/**
	 * Perform a Hestia API call and log result.
	 *
	 * @since 2.5.0
	 *
	 * @param string $cmd          Hestia command (e.g., v-add-web-domain-alias).
	 * @param array  $args         Command args.
	 * @param string $action_label Log label.
	 * @return void
	 */
	protected function call_and_log(string $cmd, array $args, string $action_label): void {

		$result = $this->get_hestia()->send_hestia_request($cmd, $args);

		if (is_wp_error($result)) {
			wu_log_add('integration-hestia', sprintf('[%s] %s', $action_label, $result->get_error_message()), LogLevel::ERROR);

			return;
		}

		wu_log_add('integration-hestia', sprintf('[%s] %s', $action_label, is_scalar($result) ? (string) $result : wp_json_encode($result)));
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_hestia()->test_connection();
	}
}
