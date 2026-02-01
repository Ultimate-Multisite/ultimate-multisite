<?php
/**
 * Enhance Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/Enhance
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Enhance;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

defined('ABSPATH') || exit;

/**
 * Enhance domain mapping capability module.
 *
 * @since 2.5.0
 */
class Enhance_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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
				'send_domains' => __('Add domains to Enhance Control Panel whenever a new domain mapping gets created on your network', 'ultimate-multisite'),
				'autossl'      => __('SSL certificates will be automatically provisioned via LetsEncrypt when DNS resolves', 'ultimate-multisite'),
			],
			'will_not' => [],
		];

		if (is_subdomain_install()) {
			$explainer_lines['will']['send_sub_domains'] = __('Add subdomains to Enhance Control Panel whenever a new site gets created on your network', 'ultimate-multisite');
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
	 * Gets the parent Enhance_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return Enhance_Integration
	 */
	private function get_enhance(): Enhance_Integration {

		/** @var Enhance_Integration */
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

		wu_log_add('integration-enhance', sprintf('Adding domain: %s for site ID: %d', $domain, $site_id));

		$org_id     = $this->get_enhance()->get_credential('WU_ENHANCE_ORG_ID');
		$website_id = $this->get_enhance()->get_credential('WU_ENHANCE_WEBSITE_ID');

		if (empty($org_id)) {
			wu_log_add('integration-enhance', 'Organization ID not configured');

			return;
		}

		if (empty($website_id)) {
			wu_log_add('integration-enhance', 'Website ID not configured');

			return;
		}

		$domain_data = [
			'domain' => $domain,
		];

		$domain_response = $this->get_enhance()->send_enhance_api_request(
			'/orgs/' . $org_id . '/websites/' . $website_id . '/domains',
			'POST',
			$domain_data
		);

		if (wu_get_isset($domain_response, 'id') || (isset($domain_response['success']) && $domain_response['success'])) {
			wu_log_add('integration-enhance', sprintf('Domain %s added successfully. SSL will be automatically provisioned via LetsEncrypt when DNS resolves.', $domain));
		} elseif (isset($domain_response['error'])) {
			wu_log_add('integration-enhance', sprintf('Failed to add domain %s. Error: %s', $domain, wp_json_encode($domain_response)));
		} else {
			wu_log_add('integration-enhance', sprintf('Domain %s may have been added, but response was unclear: %s', $domain, wp_json_encode($domain_response)));
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

		wu_log_add('integration-enhance', sprintf('Removing domain: %s for site ID: %d', $domain, $site_id));

		$org_id     = $this->get_enhance()->get_credential('WU_ENHANCE_ORG_ID');
		$website_id = $this->get_enhance()->get_credential('WU_ENHANCE_WEBSITE_ID');

		if (empty($org_id)) {
			wu_log_add('integration-enhance', 'Organization ID not configured');

			return;
		}

		if (empty($website_id)) {
			wu_log_add('integration-enhance', 'Website ID not configured');

			return;
		}

		// First, get the domain ID by listing domains and finding a match
		$domains_list = $this->get_enhance()->send_enhance_api_request(
			'/orgs/' . $org_id . '/websites/' . $website_id . '/domains'
		);

		$domain_id = null;

		if (isset($domains_list['items']) && is_array($domains_list['items'])) {
			foreach ($domains_list['items'] as $item) {
				if (isset($item['domain']) && $item['domain'] === $domain) {
					$domain_id = $item['id'];

					break;
				}
			}
		}

		if (empty($domain_id)) {
			wu_log_add('integration-enhance', sprintf('Could not find domain ID for %s, it may have already been removed', $domain));

			return;
		}

		$this->get_enhance()->send_enhance_api_request(
			'/orgs/' . $org_id . '/websites/' . $website_id . '/domains/' . $domain_id,
			'DELETE'
		);

		wu_log_add('integration-enhance', sprintf('Domain %s removal request sent', $domain));
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

		$this->on_add_domain($subdomain, $site_id);
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

		$this->on_remove_domain($subdomain, $site_id);
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_enhance()->test_connection();
	}
}
