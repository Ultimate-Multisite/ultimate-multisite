<?php
/**
 * Cloudflare Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\Cloudflare;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Cloudflare domain mapping capability module.
 *
 * @since 2.5.0
 */
class Cloudflare_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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
			'will'     => [],
			'will_not' => [],
		];

		if (is_subdomain_install()) {
			$explainer_lines['will']['send_sub_domains'] = __('Add a new proxied subdomain to the configured CloudFlare zone whenever a new site gets created', 'ultimate-multisite');
		} else {
			$explainer_lines['will']['subdirectory'] = __('Do nothing! The CloudFlare integration has no effect in subdirectory multisite installs such as this one', 'ultimate-multisite');
		}

		$explainer_lines['will_not']['send_domain'] = __('Add domain mappings as new CloudFlare zones', 'ultimate-multisite');

		if ($this->get_cloudflare()->get_credential('WU_CLOUDFLARE_SAAS_ZONE_ID')) {
			$explainer_lines['will']['custom_hostnames'] = __('Register custom domains as Custom Hostnames in your Cloudflare for SaaS zone, enabling automatic SSL provisioning for mapped domains', 'ultimate-multisite');
		} else {
			$explainer_lines['will_not']['custom_hostnames'] = __('Register custom domains as Cloudflare Custom Hostnames (requires SaaS Zone ID to be configured)', 'ultimate-multisite');
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
		add_filter('wu_domain_dns_get_record', [$this, 'add_cloudflare_dns_entries'], 10, 2);
	}

	/**
	 * Gets the parent Cloudflare_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return Cloudflare_Integration
	 */
	private function get_cloudflare(): Cloudflare_Integration {

		/** @var Cloudflare_Integration */
		return $this->get_integration();
	}

	/**
	 * Handles adding a custom domain via the Cloudflare for SaaS Custom Hostnames API.
	 *
	 * When a SaaS Zone ID is configured, registers the domain as a Custom Hostname
	 * in that zone so Cloudflare can provision an SSL certificate automatically.
	 * Falls back silently when the SaaS Zone ID is not set.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name being mapped.
	 * @param int    $site_id The site ID receiving the mapping.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$saas_zone_id = $this->get_cloudflare()->get_credential('WU_CLOUDFLARE_SAAS_ZONE_ID');

		if ( ! $saas_zone_id) {
			return;
		}

		$data = apply_filters(
			'wu_cloudflare_custom_hostname_data',
			[
				'hostname' => $domain,
				'ssl'      => [
					'method' => 'http',
					'type'   => 'dv',
				],
			],
			$domain,
			$site_id
		);

		$result = $this->get_cloudflare()->cloudflare_api_call(
			"client/v4/zones/$saas_zone_id/custom_hostnames",
			'POST',
			$data
		);

		if (is_wp_error($result)) {
			wu_log_add(
				'integration-cloudflare',
				sprintf('Failed to create Custom Hostname for "%s". Reason: %s', $domain, $result->get_error_message()),
				LogLevel::ERROR
			);

			return;
		}

		wu_log_add('integration-cloudflare', sprintf('Created Custom Hostname for "%s" in Cloudflare SaaS zone.', $domain));
	}

	/**
	 * Handles removing a custom domain from the Cloudflare for SaaS Custom Hostnames API.
	 *
	 * Looks up the Custom Hostname by hostname and deletes it from the SaaS zone.
	 * Falls back silently when the SaaS Zone ID is not set.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name being removed.
	 * @param int    $site_id The site ID. Required by Domain_Mapping_Capability interface but unused here.
	 *                        Unlike on_add_domain, no filter hook is applied before deletion.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$saas_zone_id = $this->get_cloudflare()->get_credential('WU_CLOUDFLARE_SAAS_ZONE_ID');

		if ( ! $saas_zone_id) {
			return;
		}

		// Look up the Custom Hostname ID by hostname value.
		$list_result = $this->get_cloudflare()->cloudflare_api_call(
			"client/v4/zones/$saas_zone_id/custom_hostnames",
			'GET',
			['hostname' => $domain]
		);

		if (is_wp_error($list_result)) {
			wu_log_add(
				'integration-cloudflare',
				sprintf('Failed to look up Custom Hostname for "%s". Reason: %s', $domain, $list_result->get_error_message()),
				LogLevel::ERROR
			);

			return;
		}

		if (empty($list_result->result)) {
			wu_log_add(
				'integration-cloudflare',
				sprintf('Could not find Custom Hostname for "%s" to delete. Skipping.', $domain),
				LogLevel::WARNING
			);

			return;
		}

		$custom_hostname_id = $list_result->result[0]->id;

		$delete_result = $this->get_cloudflare()->cloudflare_api_call(
			"client/v4/zones/$saas_zone_id/custom_hostnames/$custom_hostname_id",
			'DELETE'
		);

		if (is_wp_error($delete_result)) {
			wu_log_add(
				'integration-cloudflare',
				sprintf('Failed to delete Custom Hostname for "%s". Reason: %s', $domain, $delete_result->get_error_message()),
				LogLevel::ERROR
			);

			return;
		}

		wu_log_add('integration-cloudflare', sprintf('Deleted Custom Hostname for "%s" from Cloudflare SaaS zone.', $domain));
	}

	/**
	 * Handles adding a subdomain to Cloudflare.
	 *
	 * Adds a proxied CNAME record to the configured Cloudflare zone.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {

		global $current_site;

		$zone_id = $this->get_cloudflare()->get_credential('WU_CLOUDFLARE_ZONE_ID');

		if ( ! $zone_id) {
			return;
		}

		if ( ! str_contains($subdomain, (string) $current_site->domain)) {
			return;
		}

		$subdomain = rtrim(str_replace($current_site->domain, '', $subdomain), '.');

		if ( ! $subdomain) {
			return;
		}

		$full_domain    = $subdomain . '.' . $current_site->domain;
		$should_add_www = apply_filters(
			'wu_cloudflare_should_add_www',
			\WP_Ultimo\Managers\Domain_Manager::get_instance()->should_create_www_subdomain($full_domain),
			$subdomain,
			$site_id
		);

		$domains_to_send = [$subdomain];

		if ( ! str_starts_with($subdomain, 'www.') && $should_add_www) {
			$domains_to_send[] = 'www.' . $subdomain;
		}

		foreach ($domains_to_send as $subdomain) {
			$should_proxy = apply_filters('wu_cloudflare_should_proxy', true, $subdomain, $site_id);

			$data = apply_filters(
				'wu_cloudflare_on_add_domain_data',
				[
					'type'    => 'CNAME',
					'name'    => $subdomain,
					'content' => '@',
					'proxied' => $should_proxy,
					'ttl'     => 1,
				],
				$subdomain,
				$site_id
			);

			$results = $this->get_cloudflare()->cloudflare_api_call("client/v4/zones/$zone_id/dns_records/", 'POST', $data);

			if (is_wp_error($results)) {
				wu_log_add('integration-cloudflare', sprintf('Failed to add subdomain "%s" to Cloudflare. Reason: %s', $subdomain, $results->get_error_message()), LogLevel::ERROR);

				return;
			}

			wu_log_add('integration-cloudflare', sprintf('Added sub-domain "%s" to Cloudflare.', $subdomain));
		}
	}

	/**
	 * Handles removing a subdomain from Cloudflare.
	 *
	 * Finds and deletes the CNAME record from the configured Cloudflare zone.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {

		global $current_site;

		$zone_id = $this->get_cloudflare()->get_credential('WU_CLOUDFLARE_ZONE_ID');

		if ( ! $zone_id) {
			return;
		}

		if ( ! str_contains($subdomain, (string) $current_site->domain)) {
			return;
		}

		$original_subdomain = $subdomain;

		$subdomain = rtrim(str_replace($current_site->domain, '', $subdomain), '.');

		if ( ! $subdomain) {
			return;
		}

		$domains_to_remove = [
			$original_subdomain,
			'www.' . $original_subdomain,
		];

		foreach ($domains_to_remove as $original_subdomain) {
			$dns_entries = $this->get_cloudflare()->cloudflare_api_call(
				"client/v4/zones/$zone_id/dns_records/",
				'GET',
				[
					'name' => $original_subdomain,
					'type' => 'CNAME',
				]
			);

			if (is_wp_error($dns_entries) || ! $dns_entries->result) {
				return;
			}

			$dns_entry_to_remove = $dns_entries->result[0];

			$results = $this->get_cloudflare()->cloudflare_api_call("client/v4/zones/$zone_id/dns_records/$dns_entry_to_remove->id", 'DELETE');

			if (is_wp_error($results)) {
				wu_log_add('integration-cloudflare', sprintf('Failed to remove subdomain "%s" from Cloudflare. Reason: %s', $subdomain, $results->get_error_message()), LogLevel::ERROR);

				return;
			}

			wu_log_add('integration-cloudflare', sprintf('Removed sub-domain "%s" from Cloudflare.', $subdomain));
		}
	}

	/**
	 * Adds Cloudflare DNS entries to the comparison table.
	 *
	 * @since 2.5.0
	 *
	 * @param array  $dns_records List of current DNS records.
	 * @param string $domain      The domain name.
	 * @return array
	 */
	public function add_cloudflare_dns_entries(array $dns_records, string $domain): array {

		$zone_ids = [];

		$default_zone_id = $this->get_cloudflare()->get_credential('WU_CLOUDFLARE_ZONE_ID') ?: false;

		if ($default_zone_id) {
			$zone_ids[] = $default_zone_id;
		}

		$cloudflare_zones = $this->get_cloudflare()->cloudflare_api_call(
			'client/v4/zones',
			'GET',
			[
				'name'   => $domain,
				'status' => 'active',
			]
		);

		if ( ! is_wp_error($cloudflare_zones)) {
			foreach ($cloudflare_zones->result as $zone) {
				$zone_ids[] = $zone->id;
			}
		}

		foreach ($zone_ids as $zone_id) {
			$dns_entries = $this->get_cloudflare()->cloudflare_api_call(
				"client/v4/zones/$zone_id/dns_records/",
				'GET',
				[
					'name'  => $domain,
					'match' => 'any',
					'type'  => 'A,AAAA,CNAME',
				]
			);

			if (is_wp_error($dns_entries) || empty($dns_entries->result)) {
				continue;
			}

			$proxied_tag = sprintf(
				'<span class="wu-bg-orange-500 wu-text-white wu-p-1 wu-rounded wu-text-3xs wu-uppercase wu-ml-2 wu-font-bold" role="tooltip" aria-label="%s">%s</span>',
				__('Proxied', 'ultimate-multisite'),
				__('Cloudflare', 'ultimate-multisite')
			);

			$not_proxied_tag = sprintf(
				'<span class="wu-bg-gray-700 wu-text-white wu-p-1 wu-rounded wu-text-3xs wu-uppercase wu-ml-2 wu-font-bold" role="tooltip" aria-label="%s">%s</span>',
				__('Not Proxied', 'ultimate-multisite'),
				__('Cloudflare', 'ultimate-multisite')
			);

			foreach ($dns_entries->result as $entry) {
				$dns_records[] = [
					'ttl'  => $entry->ttl,
					'data' => $entry->content,
					'type' => $entry->type,
					'host' => $entry->name,
					'tag'  => $entry->proxied ? $proxied_tag : $not_proxied_tag,
				];
			}
		}

		return $dns_records;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_cloudflare()->test_connection();
	}
}
