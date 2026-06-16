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
			$explainer_lines['will']['custom_hostnames'] = __('Register custom domains as Cloudflare Custom Hostnames, enabling automatic SSL provisioning for mapped domains', 'ultimate-multisite');
		} else {
			$explainer_lines['will_not']['custom_hostnames'] = __('Register custom domains as Cloudflare Custom Hostnames (requires Custom Hostnames Zone ID to be configured)', 'ultimate-multisite');
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
		add_action('wu_domain_verified', [$this, 'publish_transactional_email_dns_records'], 10, 3);
		add_action('wu_settings_transactional_email', [$this, 'register_transactional_dns_settings']);
		add_filter('wu_domain_dns_get_record', [$this, 'add_cloudflare_dns_entries'], 10, 2);
	}

	/**
	 * Registers transactional DNS automation settings.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_transactional_dns_settings(): void {

		wu_register_settings_field(
			'emails',
			'cloudflare_publish_transactional_dns',
			[
				'title'   => __('Publish transactional email DNS records in Cloudflare', 'ultimate-multisite'),
				'desc'    => __('Automatically creates or updates SPF and DKIM records supplied by configured transactional email providers when a matching active Cloudflare zone is available.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => true,
			]
		);

		wu_register_settings_field(
			'emails',
			'cloudflare_preserve_transactional_dmarc',
			[
				'title'   => __('Preserve existing Cloudflare DMARC records', 'ultimate-multisite'),
				'desc'    => __('Keeps existing _dmarc TXT or CNAME records intact and only creates provider-supplied DMARC records when none exist.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => true,
			]
		);
	}

	/**
	 * Publishes provider-supplied transactional email DNS records to Cloudflare.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain      The verified domain name.
	 * @param int    $site_id     The site ID associated with the domain.
	 * @param array  $dns_records Provider-supplied records shaped as type, name, and value.
	 * @return void
	 */
	public function publish_transactional_email_dns_records(string $domain, int $site_id, array $dns_records): void {

		$enabled = apply_filters(
			'wu_cloudflare_publish_transactional_dns_records',
			(bool) wu_get_setting('cloudflare_publish_transactional_dns', true),
			$domain,
			$site_id,
			$dns_records
		);

		if ( ! $enabled) {
			wu_log_add('integration-cloudflare', sprintf('Skipped transactional email DNS publication for "%s" because it is disabled.', $domain));

			return;
		}

		if (empty($dns_records)) {
			wu_log_add('integration-cloudflare', sprintf('Skipped transactional email DNS publication for "%s" because no records were supplied.', $domain));

			return;
		}

		$zone = $this->find_best_zone_for_domain($domain);

		if ( ! $zone) {
			wu_log_add('integration-cloudflare', sprintf('Skipped transactional email DNS publication for "%s" because no active Cloudflare zone was accessible.', $domain), LogLevel::WARNING);

			return;
		}

		wu_log_add('integration-cloudflare', sprintf('Selected Cloudflare zone "%1$s" for transactional email DNS records on "%2$s".', $zone->name, $domain));

		foreach ($dns_records as $record) {
			$record = $this->normalize_transactional_dns_record($record, $domain);

			if ( ! $record) {
				wu_log_add('integration-cloudflare', sprintf('Skipped invalid transactional email DNS record for "%s".', $domain), LogLevel::WARNING);

				continue;
			}

			$skip = apply_filters('wu_cloudflare_skip_transactional_dns_record', false, $record, $domain, $site_id, $zone);

			if ($skip) {
				wu_log_add('integration-cloudflare', sprintf('Skipped transactional email DNS record "%1$s" (%2$s) by filter.', $record['name'], $record['type']));

				continue;
			}

			if ('TXT' === $record['type'] && $this->is_spf_record($record['value'])) {
				$this->upsert_spf_record($zone->id, $record, $domain);

				continue;
			}

			if ($this->is_dmarc_record($record)) {
				$this->upsert_dmarc_record($zone->id, $record, $domain);

				continue;
			}

			if (in_array($record['type'], ['CNAME', 'TXT'], true)) {
				$this->upsert_dns_record($zone->id, $record, $domain);

				continue;
			}

			wu_log_add('integration-cloudflare', sprintf('Skipped unsupported transactional email DNS record "%1$s" (%2$s).', $record['name'], $record['type']), LogLevel::WARNING);
		}
	}

	/**
	 * Finds the longest matching active Cloudflare zone for a domain.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain Domain name.
	 * @return object|null
	 */
	private function find_best_zone_for_domain(string $domain): ?object {

		$domain = $this->normalize_dns_name($domain);
		$zones  = [];

		$default_zone_id = $this->get_cloudflare()->get_credential('WU_CLOUDFLARE_ZONE_ID');

		if ($default_zone_id) {
			$default_zone = $this->get_cloudflare()->cloudflare_api_call("client/v4/zones/$default_zone_id");

			if ( ! is_wp_error($default_zone) && ! empty($default_zone->result)) {
				$zones[ $default_zone->result->id ] = $default_zone->result;
			}
		}

		$cloudflare_zones = $this->get_cloudflare()->cloudflare_api_call(
			'client/v4/zones',
			'GET',
			[
				'status' => 'active',
			]
		);

		if (is_wp_error($cloudflare_zones)) {
			wu_log_add('integration-cloudflare', sprintf('Could not list Cloudflare zones while publishing transactional DNS for "%s". Reason: %s', $domain, $cloudflare_zones->get_error_message()), LogLevel::WARNING);
		} else {
			foreach ((array) $cloudflare_zones->result as $zone) {
				$zones[ $zone->id ] = $zone;
			}
		}

		$best_zone = null;
		$best_size = 0;

		foreach ($zones as $zone) {
			$zone_name = $this->normalize_dns_name((string) ($zone->name ?? ''));

			if ( ! $zone_name) {
				continue;
			}

			if ($domain !== $zone_name && ! str_ends_with($domain, '.' . $zone_name)) {
				continue;
			}

			$zone_size = strlen($zone_name);

			if ($zone_size > $best_size) {
				$best_zone = $zone;
				$best_size = $zone_size;
			}
		}

		return $best_zone;
	}

	/**
	 * Normalizes a provider-supplied DNS record.
	 *
	 * @since 2.5.0
	 *
	 * @param array  $record Provider-supplied record.
	 * @param string $domain Domain name.
	 * @return array|null
	 */
	private function normalize_transactional_dns_record(array $record, string $domain): ?array {

		$type  = strtoupper((string) ($record['type'] ?? ''));
		$name  = $this->normalize_dns_name((string) ($record['name'] ?? $domain));
		$value = trim((string) ($record['value'] ?? $record['content'] ?? ''));

		if ( ! $type || ! $name || ! $value) {
			return null;
		}

		$normalized = [
			'type'  => $type,
			'name'  => $name,
			'value' => $value,
		];

		return apply_filters('wu_cloudflare_normalize_transactional_dns_record', $normalized, $record, $domain);
	}

	/**
	 * Normalizes a DNS name for Cloudflare lookups.
	 *
	 * @since 2.5.0
	 *
	 * @param string $name DNS name.
	 * @return string
	 */
	private function normalize_dns_name(string $name): string {

		return strtolower(rtrim(trim($name), '.'));
	}

	/**
	 * Checks if a TXT value is an SPF record.
	 *
	 * @since 2.5.0
	 *
	 * @param string $value TXT value.
	 * @return bool
	 */
	private function is_spf_record(string $value): bool {

		return str_starts_with(strtolower(trim($value)), 'v=spf1');
	}

	/**
	 * Checks if a record targets DMARC.
	 *
	 * @since 2.5.0
	 *
	 * @param array $record DNS record.
	 * @return bool
	 */
	private function is_dmarc_record(array $record): bool {

		return str_starts_with($record['name'], '_dmarc.');
	}

	/**
	 * Creates or updates a single SPF TXT record without creating duplicates.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zone_id Cloudflare zone ID.
	 * @param array  $record  Normalized SPF record.
	 * @param string $domain  Domain name.
	 * @return void
	 */
	private function upsert_spf_record(string $zone_id, array $record, string $domain): void {

		$existing_records = $this->get_cloudflare_dns_records($zone_id, $record['name'], 'TXT');

		if (is_wp_error($existing_records)) {
			wu_log_add('integration-cloudflare', sprintf('Failed to look up SPF record "%1$s" for "%2$s". Reason: %3$s', $record['name'], $domain, $existing_records->get_error_message()), LogLevel::ERROR);

			return;
		}

		$spf_record = null;

		foreach ($existing_records as $existing_record) {
			if ($this->is_spf_record((string) $existing_record->content)) {
				$spf_record = $existing_record;

				break;
			}
		}

		if ($spf_record) {
			$merged_value = $this->merge_spf_record((string) $spf_record->content, $record['value'], $domain);

			if ($merged_value === (string) $spf_record->content) {
				wu_log_add('integration-cloudflare', sprintf('Skipped SPF record "%s" because the required includes already exist.', $record['name']));

				return;
			}

			$this->update_dns_record($zone_id, (string) $spf_record->id, $record, $merged_value, $domain);

			return;
		}

		$this->create_dns_record($zone_id, $record, $record['value'], $domain);
	}

	/**
	 * Creates or updates a DMARC record while preserving existing records by default.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zone_id Cloudflare zone ID.
	 * @param array  $record  Normalized DMARC record.
	 * @param string $domain  Domain name.
	 * @return void
	 */
	private function upsert_dmarc_record(string $zone_id, array $record, string $domain): void {

		$preserve = apply_filters('wu_cloudflare_preserve_transactional_dmarc', (bool) wu_get_setting('cloudflare_preserve_transactional_dmarc', true), $record, $domain);
		$existing = [];

		foreach (['TXT', 'CNAME'] as $type) {
			$records = $this->get_cloudflare_dns_records($zone_id, $record['name'], $type);

			if (is_wp_error($records)) {
				wu_log_add('integration-cloudflare', sprintf('Failed to look up DMARC record "%1$s" for "%2$s". Reason: %3$s', $record['name'], $domain, $records->get_error_message()), LogLevel::ERROR);

				return;
			}

			$existing = array_merge($existing, $records);
		}

		if ($preserve && ! empty($existing)) {
			wu_log_add('integration-cloudflare', sprintf('Skipped DMARC record "%s" because an existing DMARC TXT or CNAME record is preserved.', $record['name']));

			return;
		}

		$this->upsert_dns_record($zone_id, $record, $domain);
	}

	/**
	 * Creates or updates a non-SPF DNS record.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zone_id Cloudflare zone ID.
	 * @param array  $record  Normalized DNS record.
	 * @param string $domain  Domain name.
	 * @return void
	 */
	private function upsert_dns_record(string $zone_id, array $record, string $domain): void {

		$existing_records = $this->get_cloudflare_dns_records($zone_id, $record['name'], $record['type']);

		if (is_wp_error($existing_records)) {
			wu_log_add('integration-cloudflare', sprintf('Failed to look up DNS record "%1$s" (%2$s) for "%3$s". Reason: %4$s', $record['name'], $record['type'], $domain, $existing_records->get_error_message()), LogLevel::ERROR);

			return;
		}

		$existing_record = $existing_records[0] ?? null;

		if ($existing_record) {
			if ($record['value'] === (string) $existing_record->content) {
				wu_log_add('integration-cloudflare', sprintf('Skipped DNS record "%1$s" (%2$s) because it already matches Cloudflare.', $record['name'], $record['type']));

				return;
			}

			$this->update_dns_record($zone_id, (string) $existing_record->id, $record, $record['value'], $domain);

			return;
		}

		$this->create_dns_record($zone_id, $record, $record['value'], $domain);
	}

	/**
	 * Retrieves Cloudflare DNS records by name and type.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zone_id Cloudflare zone ID.
	 * @param string $name    Record name.
	 * @param string $type    Record type.
	 * @return array|\WP_Error
	 */
	private function get_cloudflare_dns_records(string $zone_id, string $name, string $type) {

		$result = $this->get_cloudflare()->cloudflare_api_call(
			"client/v4/zones/$zone_id/dns_records/",
			'GET',
			[
				'name' => $name,
				'type' => $type,
			]
		);

		if (is_wp_error($result)) {
			return $result;
		}

		return (array) ($result->result ?? []);
	}

	/**
	 * Merges transactional provider SPF includes into an existing SPF record.
	 *
	 * @since 2.5.0
	 *
	 * @param string $existing Existing SPF value.
	 * @param string $incoming Provider-supplied SPF value.
	 * @param string $domain   Domain name.
	 * @return string
	 */
	private function merge_spf_record(string $existing, string $incoming, string $domain): string {

		$existing_parts = preg_split('/\s+/', trim($existing));
		$incoming_parts = preg_split('/\s+/', trim($incoming));
		$merged_parts   = $existing_parts ?: ['v=spf1'];

		foreach ((array) $incoming_parts as $part) {
			if ( ! str_starts_with($part, 'include:') || in_array($part, $merged_parts, true)) {
				continue;
			}

			$insert_at = max(1, count($merged_parts) - 1);
			array_splice($merged_parts, $insert_at, 0, [$part]);
		}

		return apply_filters('wu_cloudflare_merged_spf_record', implode(' ', $merged_parts), $existing, $incoming, $domain);
	}

	/**
	 * Creates a Cloudflare DNS record.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zone_id Cloudflare zone ID.
	 * @param array  $record  Normalized DNS record.
	 * @param string $value   Record content.
	 * @param string $domain  Domain name.
	 * @return void
	 */
	private function create_dns_record(string $zone_id, array $record, string $value, string $domain): void {

		$result = $this->get_cloudflare()->cloudflare_api_call("client/v4/zones/$zone_id/dns_records/", 'POST', $this->get_dns_record_payload($record, $value));

		if (is_wp_error($result)) {
			wu_log_add('integration-cloudflare', sprintf('Failed to create DNS record "%1$s" (%2$s) for "%3$s". Reason: %4$s', $record['name'], $record['type'], $domain, $result->get_error_message()), LogLevel::ERROR);

			return;
		}

		wu_log_add('integration-cloudflare', sprintf('Created DNS record "%1$s" (%2$s) in Cloudflare for "%3$s".', $record['name'], $record['type'], $domain));
	}

	/**
	 * Updates a Cloudflare DNS record.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zone_id   Cloudflare zone ID.
	 * @param string $record_id Cloudflare DNS record ID.
	 * @param array  $record    Normalized DNS record.
	 * @param string $value     Record content.
	 * @param string $domain    Domain name.
	 * @return void
	 */
	private function update_dns_record(string $zone_id, string $record_id, array $record, string $value, string $domain): void {

		$result = $this->get_cloudflare()->cloudflare_api_call("client/v4/zones/$zone_id/dns_records/$record_id", 'PUT', $this->get_dns_record_payload($record, $value));

		if (is_wp_error($result)) {
			wu_log_add('integration-cloudflare', sprintf('Failed to update DNS record "%1$s" (%2$s) for "%3$s". Reason: %4$s', $record['name'], $record['type'], $domain, $result->get_error_message()), LogLevel::ERROR);

			return;
		}

		wu_log_add('integration-cloudflare', sprintf('Updated DNS record "%1$s" (%2$s) in Cloudflare for "%3$s".', $record['name'], $record['type'], $domain));
	}

	/**
	 * Builds a Cloudflare DNS API payload.
	 *
	 * @since 2.5.0
	 *
	 * @param array  $record Normalized DNS record.
	 * @param string $value  Record content.
	 * @return array
	 */
	private function get_dns_record_payload(array $record, string $value): array {

		$payload = [
			'type'    => $record['type'],
			'name'    => $record['name'],
			'content' => $value,
			'ttl'     => 1,
		];

		if ('CNAME' === $record['type']) {
			$payload['proxied'] = false;
		}

		return $payload;
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
