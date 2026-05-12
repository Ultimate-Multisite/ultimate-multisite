<?php
/**
 * Hostinger Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/Hostinger
 * @since 2.5.1
 */

namespace WP_Ultimo\Integrations\Providers\Hostinger;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Hostinger domain mapping capability module.
 *
 * Manages DNS records in Hostinger-hosted zones in response to network domain
 * and subdomain events. The Hostinger DNS API operates on the full zone for a
 * given root domain; this module looks up the closest root domain in the
 * Hostinger portfolio and appends or removes records as appropriate.
 *
 * Only zones that exist in the configured Hostinger account are touched; any
 * domain not present in the portfolio is logged and skipped.
 *
 * @since 2.5.1
 */
class Hostinger_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

	/**
	 * Supported features.
	 *
	 * @since 2.5.1
	 * @var array
	 */
	protected array $supported_features = ['autossl'];

	/**
	 * Cache of root domain lookups keyed by full hostname.
	 *
	 * @since 2.5.1
	 * @var array<string,string|false>
	 */
	private array $root_domain_cache = [];

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

		$lines = [
			'will'     => [],
			'will_not' => [],
		];

		if (is_subdomain_install()) {
			$lines['will']['send_sub_domains'] = __('Add a CNAME record to the matching Hostinger DNS zone whenever a new subdomain site is created', 'ultimate-multisite');
		} else {
			$lines['will']['subdirectory'] = __('Do nothing for subsites — the Hostinger DNS integration has no effect in subdirectory multisite installs', 'ultimate-multisite');
		}

		$lines['will']['custom_domain']     = __('Add an A or CNAME record to the matching Hostinger DNS zone when a customer maps a domain hosted on Hostinger', 'ultimate-multisite');
		$lines['will']['autossl']           = __('Rely on Hostinger\'s automatic SSL provisioning for records it manages', 'ultimate-multisite');
		$lines['will_not']['external_zone'] = __('Modify DNS for domains whose zone is not in the configured Hostinger account', 'ultimate-multisite');
		$lines['will_not']['register']      = __('Register new domains (use the Domain Seller addon for that)', 'ultimate-multisite');

		return $lines;
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_hooks(): void {

		add_action('wu_add_domain', [$this, 'on_add_domain'], 10, 2);
		add_action('wu_remove_domain', [$this, 'on_remove_domain'], 10, 2);
		add_action('wu_add_subdomain', [$this, 'on_add_subdomain'], 10, 2);
		add_action('wu_remove_subdomain', [$this, 'on_remove_subdomain'], 10, 2);
		add_filter('wu_domain_dns_get_record', [$this, 'add_hostinger_dns_entries'], 10, 2);
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_hostinger()->test_connection();
	}

	/**
	 * Returns the parent integration cast to the Hostinger provider.
	 *
	 * @since 2.5.1
	 * @return Hostinger_Integration
	 */
	private function get_hostinger(): Hostinger_Integration {

		/** @var Hostinger_Integration */
		return $this->get_integration();
	}

	/**
	 * Looks up the deepest root domain in the Hostinger portfolio that owns the given hostname.
	 *
	 * For `foo.bar.example.com` this returns `example.com` when only `example.com` is
	 * in the portfolio, or `bar.example.com` when that subdomain has been delegated as its
	 * own zone. Returns false when no matching zone is present.
	 *
	 * @since 2.5.1
	 *
	 * @param string $hostname The full hostname to resolve.
	 * @return string|false Root zone domain or false when no matching zone exists.
	 */
	public function find_root_domain(string $hostname) {

		$hostname = strtolower(trim($hostname, " \t\n\r\0\x0B."));

		if ('' === $hostname) {
			return false;
		}

		if (array_key_exists($hostname, $this->root_domain_cache)) {
			return $this->root_domain_cache[ $hostname ];
		}

		$portfolio = $this->get_hostinger()->hostinger_api_call('/api/domains/v1/portfolio');

		if (is_wp_error($portfolio) || ! is_array($portfolio)) {
			$this->root_domain_cache[ $hostname ] = false;

			return false;
		}

		$owned = [];

		foreach ($portfolio as $entry) {
			if (is_object($entry) && isset($entry->domain) && is_string($entry->domain)) {
				$owned[] = strtolower($entry->domain);
			} elseif (is_array($entry) && isset($entry['domain']) && is_string($entry['domain'])) {
				$owned[] = strtolower($entry['domain']);
			}
		}

		// Try progressively shorter suffixes so the deepest matching zone wins.
		$parts           = explode('.', $hostname);
		$remaining_parts = count($parts);
		while ($remaining_parts >= 2) {
			$candidate = implode('.', $parts);

			if (in_array($candidate, $owned, true)) {
				$this->root_domain_cache[ $hostname ] = $candidate;

				return $candidate;
			}

			array_shift($parts);
			--$remaining_parts;
		}

		$this->root_domain_cache[ $hostname ] = false;

		return false;
	}

	/**
	 * Computes the record name relative to the zone root.
	 *
	 * Hostinger DNS uses `@` for the zone apex; subdomains use the bare label.
	 *
	 * @since 2.5.1
	 *
	 * @param string $hostname    Full hostname.
	 * @param string $root_domain Zone root.
	 * @return string Record name (`@` for apex, otherwise the prefix without trailing dot).
	 */
	public function relative_record_name(string $hostname, string $root_domain): string {

		$hostname    = strtolower(trim($hostname, " \t\n\r\0\x0B."));
		$root_domain = strtolower(trim($root_domain, " \t\n\r\0\x0B."));

		if ($hostname === $root_domain || '' === $hostname) {
			return '@';
		}

		$suffix = '.' . $root_domain;

		if (substr($hostname, -strlen($suffix)) === $suffix) {
			$prefix = substr($hostname, 0, -strlen($suffix));

			return '' === $prefix ? '@' : $prefix;
		}

		return $hostname;
	}

	/**
	 * Determines the network's primary domain for use as CNAME content.
	 *
	 * @since 2.5.1
	 * @return string
	 */
	private function get_network_domain(): string {

		global $current_site;

		if ($current_site instanceof \WP_Network && ! empty($current_site->domain)) {
			return (string) $current_site->domain;
		}

		return (string) wp_parse_url(network_home_url(), PHP_URL_HOST);
	}

	/**
	 * Adds a single record to a Hostinger DNS zone using non-destructive append.
	 *
	 * @since 2.5.1
	 *
	 * @param string $root_domain Zone root.
	 * @param string $name        Record name relative to the zone.
	 * @param string $type        Record type (A, CNAME, TXT, ...).
	 * @param string $content     Record content.
	 * @param int    $ttl         Time-to-live in seconds.
	 * @return true|\WP_Error
	 */
	private function put_record(string $root_domain, string $name, string $type, string $content, int $ttl = 14400) {

		$payload = [
			'overwrite' => false,
			'zone'      => [
				[
					'name'    => $name,
					'type'    => $type,
					'ttl'     => $ttl,
					'records' => [
						['content' => $content],
					],
				],
			],
		];

		$result = $this->get_hostinger()->hostinger_api_call(
			'/api/dns/v1/zones/' . rawurlencode($root_domain),
			'PUT',
			$payload
		);

		if (is_wp_error($result)) {
			return $result;
		}

		return true;
	}

	/**
	 * Deletes records of a given name and type from a Hostinger DNS zone.
	 *
	 * @since 2.5.1
	 *
	 * @param string $root_domain Zone root.
	 * @param string $name        Record name relative to the zone.
	 * @param string $type        Record type.
	 * @return true|\WP_Error
	 */
	private function delete_record(string $root_domain, string $name, string $type) {

		$payload = [
			'filters' => [
				[
					'name' => $name,
					'type' => $type,
				],
			],
		];

		$result = $this->get_hostinger()->hostinger_api_call(
			'/api/dns/v1/zones/' . rawurlencode($root_domain),
			'DELETE',
			$payload
		);

		if (is_wp_error($result)) {
			return $result;
		}

		return true;
	}

	/**
	 * Reads the current DNS records of a Hostinger zone.
	 *
	 * @since 2.5.1
	 *
	 * @param string $root_domain Zone root.
	 * @return array|\WP_Error Array of record group objects or WP_Error on failure.
	 */
	private function get_zone(string $root_domain) {

		$result = $this->get_hostinger()->hostinger_api_call(
			'/api/dns/v1/zones/' . rawurlencode($root_domain)
		);

		if (is_wp_error($result)) {
			return $result;
		}

		return is_array($result) ? $result : [];
	}

	/**
	 * Adds a custom mapped domain to its matching Hostinger zone.
	 *
	 * The customer's domain `custom.tld` is only acted on when that zone (or a
	 * parent zone, e.g. `tld` when the customer delegated `custom` separately)
	 * is in the Hostinger portfolio. The record points at the network domain
	 * via CNAME, or at the zone apex via the network's resolved IPv4 address
	 * when the mapped domain itself is the zone apex.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain  The mapped domain.
	 * @param int    $site_id ID of the site receiving the mapping.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$root = $this->find_root_domain($domain);

		if (false === $root) {
			wu_log_add(
				'integration-hostinger',
				sprintf('Domain "%s" is not managed by the configured Hostinger account. Skipping.', $domain),
				LogLevel::INFO
			);

			return;
		}

		$name           = $this->relative_record_name($domain, $root);
		$network_domain = $this->get_network_domain();

		$payload = apply_filters(
			'wu_hostinger_on_add_domain_record',
			[
				'name'    => $name,
				'type'    => 'CNAME',
				'content' => $network_domain,
				'ttl'     => 14400,
			],
			$domain,
			$site_id,
			$root
		);

		$result = $this->put_record($root, $payload['name'], $payload['type'], $payload['content'], (int) $payload['ttl']);

		if (is_wp_error($result)) {
			wu_log_add(
				'integration-hostinger',
				sprintf('Failed to add %s record for "%s" to zone "%s". Reason: %s', $payload['type'], $domain, $root, $result->get_error_message()),
				LogLevel::ERROR
			);

			return;
		}

		wu_log_add('integration-hostinger', sprintf('Added %s record for "%s" to Hostinger zone "%s".', $payload['type'], $domain, $root));
	}

	/**
	 * Removes the mapped domain's record from its matching Hostinger zone.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain  The mapped domain.
	 * @param int    $site_id ID of the site. Unused.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$root = $this->find_root_domain($domain);

		if (false === $root) {
			return;
		}

		$name = $this->relative_record_name($domain, $root);

		foreach (['CNAME', 'A', 'AAAA'] as $type) {
			$result = $this->delete_record($root, $name, $type);

			if (is_wp_error($result)) {
				wu_log_add(
					'integration-hostinger',
					sprintf('Failed to delete %s record for "%s" from zone "%s". Reason: %s', $type, $domain, $root, $result->get_error_message()),
					LogLevel::ERROR
				);
			}
		}

		wu_log_add('integration-hostinger', sprintf('Removed mapping records for "%s" from Hostinger zone "%s".', $domain, $root));
	}

	/**
	 * Adds a network subdomain CNAME to the matching Hostinger zone.
	 *
	 * @since 2.5.1
	 *
	 * @param string $subdomain The full subdomain (e.g. `client.network.tld`).
	 * @param int    $site_id   The new site's ID.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {

		$network_domain = $this->get_network_domain();

		if ('' === $network_domain || ! str_ends_with($subdomain, $network_domain)) {
			return;
		}

		$root = $this->find_root_domain($network_domain);

		if (false === $root) {
			wu_log_add(
				'integration-hostinger',
				sprintf('Network domain "%s" is not managed by Hostinger. Skipping subdomain "%s".', $network_domain, $subdomain),
				LogLevel::INFO
			);

			return;
		}

		$name = $this->relative_record_name($subdomain, $root);

		if ('@' === $name) {
			return;
		}

		$payload = apply_filters(
			'wu_hostinger_on_add_subdomain_record',
			[
				'name'    => $name,
				'type'    => 'CNAME',
				'content' => $network_domain . '.',
				'ttl'     => 14400,
			],
			$subdomain,
			$site_id,
			$root
		);

		$result = $this->put_record($root, $payload['name'], $payload['type'], $payload['content'], (int) $payload['ttl']);

		if (is_wp_error($result)) {
			wu_log_add(
				'integration-hostinger',
				sprintf('Failed to add subdomain CNAME for "%s" to Hostinger zone "%s". Reason: %s', $subdomain, $root, $result->get_error_message()),
				LogLevel::ERROR
			);

			return;
		}

		wu_log_add('integration-hostinger', sprintf('Added CNAME for "%s" to Hostinger zone "%s".', $subdomain, $root));
	}

	/**
	 * Removes a previously created network subdomain CNAME from the Hostinger zone.
	 *
	 * @since 2.5.1
	 *
	 * @param string $subdomain The full subdomain.
	 * @param int    $site_id   The site ID. Unused.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {

		$network_domain = $this->get_network_domain();

		if ('' === $network_domain || ! str_ends_with($subdomain, $network_domain)) {
			return;
		}

		$root = $this->find_root_domain($network_domain);

		if (false === $root) {
			return;
		}

		$name = $this->relative_record_name($subdomain, $root);

		if ('@' === $name) {
			return;
		}

		$result = $this->delete_record($root, $name, 'CNAME');

		if (is_wp_error($result)) {
			wu_log_add(
				'integration-hostinger',
				sprintf('Failed to delete subdomain CNAME for "%s" from Hostinger zone "%s". Reason: %s', $subdomain, $root, $result->get_error_message()),
				LogLevel::ERROR
			);

			return;
		}

		wu_log_add('integration-hostinger', sprintf('Removed CNAME for "%s" from Hostinger zone "%s".', $subdomain, $root));
	}

	/**
	 * Adds DNS entries from the matching Hostinger zone to the domain-mapping comparison table.
	 *
	 * @since 2.5.1
	 *
	 * @param array  $dns_records Existing record set passed by the filter.
	 * @param string $domain      The mapped domain being inspected.
	 * @return array Updated record set.
	 */
	public function add_hostinger_dns_entries(array $dns_records, string $domain): array {

		$root = $this->find_root_domain($domain);

		if (false === $root) {
			return $dns_records;
		}

		$zone = $this->get_zone($root);

		if (is_wp_error($zone) || empty($zone)) {
			return $dns_records;
		}

		$relative = $this->relative_record_name($domain, $root);
		$tag      = sprintf(
			'<span class="wu-bg-purple-700 wu-text-white wu-p-1 wu-rounded wu-text-3xs wu-uppercase wu-ml-2 wu-font-bold">%s</span>',
			__('Hostinger', 'ultimate-multisite')
		);

		foreach ($zone as $group) {
			$group_name = is_object($group) ? ($group->name ?? null) : ($group['name'] ?? null);
			$group_type = is_object($group) ? ($group->type ?? null) : ($group['type'] ?? null);
			$group_ttl  = is_object($group) ? ($group->ttl ?? null) : ($group['ttl'] ?? null);
			$records    = is_object($group) ? ($group->records ?? []) : ($group['records'] ?? []);

			if (null === $group_name || null === $group_type) {
				continue;
			}

			if ($group_name !== $relative && $group_name !== $domain) {
				continue;
			}

			if ( ! in_array($group_type, ['A', 'AAAA', 'CNAME'], true)) {
				continue;
			}

			foreach ($records as $record) {
				$content = is_object($record) ? ($record->content ?? '') : ($record['content'] ?? '');

				if ('' === $content) {
					continue;
				}

				$dns_records[] = [
					'ttl'  => $group_ttl,
					'data' => $content,
					'type' => $group_type,
					'host' => ('@' === $group_name) ? $root : $group_name . '.' . $root,
					'tag'  => $tag,
				];
			}
		}

		return $dns_records;
	}
}
