<?php
/**
 * Adds domain mapping and auto SSL support to customer hosting networks on cPanel.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Host_Providers/CPanel_Host_Provider
 * @since 2.0.0
 */

namespace WP_Ultimo\Integrations\Host_Providers;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Host_Providers\CPanel_API\CPanel_API;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * This base class should be extended to implement new host integrations for SSL and domains.
 */
class CPanel_Host_Provider extends Base_Host_Provider {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Keeps the title of the integration.
	 *
	 * @var string
	 * @since 2.0.0
	 */
	protected $id = 'cpanel';

	/**
	 * Keeps the title of the integration.
	 *
	 * @var string
	 * @since 2.0.0
	 */
	protected $title = 'cPanel';

	/**
	 * Link to the tutorial teaching how to make this integration work.
	 *
	 * @var string
	 * @since 2.0.0
	 */
	protected $tutorial_link = 'https://github.com/superdav42/wp-multisite-waas/wiki/cPanel-Integration';

	/**
	 * Array containing the features this integration supports.
	 *
	 * @var array
	 * @since 2.0.0
	 */
	protected $supports = [
		'autossl',
		'no-instructions',
		'dns-management',
	];

	/**
	 * Constants that need to be present on wp-config.php for this integration to work.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $constants = [
		'WU_CPANEL_USERNAME',
		'WU_CPANEL_PASSWORD',
		'WU_CPANEL_HOST',
	];

	/**
	 * Constants that are optional on wp-config.php.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $optional_constants = [
		'WU_CPANEL_PORT',
		'WU_CPANEL_ROOT_DIR',
	];

	/**
	 * Holds the API object.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Integrations\Host_Providers\CPanel_API\CPanel_API
	 */
	protected $api = null;

	/**
	 * Picks up on tips that a given host provider is being used.
	 *
	 * We use this to suggest that the user should activate an integration module.
	 * Unfortunately, we don't have a good method of detecting if someone is running from cPanel.
	 *
	 * @since 2.0.0
	 */
	public function detect(): bool {

		return false;
	}

	/**
	 * Returns the list of installation fields.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_fields() {

		return [
			'WU_CPANEL_USERNAME' => [
				'title'       => __('cPanel Username', 'ultimate-multisite'),
				'placeholder' => __('e.g. username', 'ultimate-multisite'),
			],
			'WU_CPANEL_PASSWORD' => [
				'type'        => 'password',
				'title'       => __('cPanel Password', 'ultimate-multisite'),
				'placeholder' => __('password', 'ultimate-multisite'),
			],
			'WU_CPANEL_HOST'     => [
				'title'       => __('cPanel Host', 'ultimate-multisite'),
				'placeholder' => __('e.g. yourdomain.com', 'ultimate-multisite'),
			],
			'WU_CPANEL_PORT'     => [
				'title'       => __('cPanel Port', 'ultimate-multisite'),
				'placeholder' => __('Defaults to 2083', 'ultimate-multisite'),
				'value'       => 2083,
			],
			'WU_CPANEL_ROOT_DIR' => [
				'title'       => __('Root Directory', 'ultimate-multisite'),
				'placeholder' => __('Defaults to /public_html', 'ultimate-multisite'),
				'value'       => '/public_html',
			],
		];
	}

	/**
	 * This method gets called when a new domain is mapped.
	 *
	 * @since 2.0.0
	 * @param string $domain The domain name being mapped.
	 * @param int    $site_id ID of the site that is receiving that mapping.
	 * @return void
	 */
	public function on_add_domain($domain, $site_id) {

		// Root Directory
		$root_dir = defined('WU_CPANEL_ROOT_DIR') && WU_CPANEL_ROOT_DIR ? WU_CPANEL_ROOT_DIR : '/public_html';

		// Send Request
		$results = $this->load_api()->api2(
			'AddonDomain',
			'addaddondomain',
			[
				'dir'       => $root_dir,
				'newdomain' => $domain,
				'subdomain' => $this->get_subdomain($domain),
			]
		);

		$this->log_calls($results);
	}

	/**
	 * This method gets called when a mapped domain is removed.
	 *
	 * @since 2.0.0
	 * @param string $domain The domain name being removed.
	 * @param int    $site_id ID of the site that is receiving that mapping.
	 * @return void
	 */
	public function on_remove_domain($domain, $site_id) {

		// Send Request
		$results = $this->load_api()->api2(
			'AddonDomain',
			'deladdondomain',
			[
				'domain'    => $domain,
				'subdomain' => $this->get_subdomain($domain) . '_' . $this->get_site_url(),
			]
		);

		$this->log_calls($results);
	}

	/**
	 * This method gets called when a new subdomain is being added.
	 *
	 * This happens every time a new site is added to a network running on subdomain mode.
	 *
	 * @since 2.0.0
	 * @param string $subdomain The subdomain being added to the network.
	 * @param int    $site_id ID of the site that is receiving that mapping.
	 * @return void
	 */
	public function on_add_subdomain($subdomain, $site_id) {

		// Root Directory
		$root_dir = defined('WU_CPANEL_ROOT_DIR') && WU_CPANEL_ROOT_DIR ? WU_CPANEL_ROOT_DIR : '/public_html';

		$subdomain = $this->get_subdomain($subdomain, false);

		$rootdomain = str_replace($subdomain . '.', '', $this->get_site_url($site_id));

		// Send Request
		$results = $this->load_api()->api2(
			'SubDomain',
			'addsubdomain',
			[
				'dir'        => $root_dir,
				'domain'     => $subdomain,
				'rootdomain' => $rootdomain,
			]
		);

		// Check the results
		$this->log_calls($results);
	}

	/**
	 * This method gets called when a new subdomain is being removed.
	 *
	 * This happens every time a new site is removed to a network running on subdomain mode.
	 *
	 * @since 2.0.0
	 * @param string $subdomain The subdomain being removed to the network.
	 * @param int    $site_id ID of the site that is receiving that mapping.
	 * @return void
	 */
	public function on_remove_subdomain($subdomain, $site_id) {}

	/**
	 * Load the CPanel API.
	 *
	 * @since 2.0.0
	 * @return CPanel_API
	 */
	public function load_api() {

		if (null === $this->api) {
			$username = defined('WU_CPANEL_USERNAME') ? WU_CPANEL_USERNAME : '';
			$password = defined('WU_CPANEL_PASSWORD') ? WU_CPANEL_PASSWORD : '';
			$host     = defined('WU_CPANEL_HOST') ? WU_CPANEL_HOST : '';
			$port     = defined('WU_CPANEL_PORT') && WU_CPANEL_PORT ? WU_CPANEL_PORT : 2083;

			/*
			 * Set up the API.
			 */
			$this->api = new CPanel_API($username, $password, preg_replace('#^https?://#', '', (string) $host), $port);
		}

		return $this->api;
	}

	/**
	 * Returns the Site URL.
	 *
	 * @since  1.6.2
	 * @param null|int $site_id The site id.
	 */
	public function get_site_url($site_id = null): string {

		return trim(preg_replace('#^https?://#', '', get_site_url($site_id)), '/');
	}

	/**
	 * Returns the sub-domain version of the domain.
	 *
	 * @since 1.6.2
	 * @param string $domain The domain to be used.
	 * @param string $mapped_domain If this is a mapped domain.
	 * @return string
	 */
	public function get_subdomain($domain, $mapped_domain = true) {

		if (false === $mapped_domain) {
			$domain_parts = explode('.', $domain);

			return array_shift($domain_parts);
		}

		$subdomain = str_replace(['.', '/'], '', $domain);

		return $subdomain;
	}

	/**
	 * Logs the results of the calls for debugging purposes
	 *
	 * @since 1.6.2
	 * @param object $results Results of the cPanel call.
	 * @return void
	 */
	public function log_calls($results) {

		if (is_object($results->cpanelresult->data)) {
			wu_log_add('integration-cpanel', $results->cpanelresult->data->reason);
			return;
		} elseif ( ! isset($results->cpanelresult->data[0])) {
			wu_log_add('integration-cpanel', __('Unexpected error ocurred trying to sync domains with CPanel', 'ultimate-multisite'), LogLevel::ERROR);
			return;
		}

		wu_log_add('integration-cpanel', $results->cpanelresult->data[0]->reason);
	}

	/**
	 * Get DNS records for a domain.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain The domain to query.
	 * @return array|\WP_Error Array of DNS_Record objects or WP_Error.
	 */
	public function get_dns_records(string $domain) {

		// Extract the zone name (root domain)
		$zone = $this->extract_zone_name($domain);

		$result = $this->load_api()->uapi(
			'DNS',
			'parse_zone',
			['zone' => $zone]
		);

		if (! $result || ! empty($result->errors) || ! isset($result->result->data)) {
			$error_message = ! empty($result->errors) && is_array($result->errors)
				? implode(', ', $result->errors)
				: __('Failed to fetch DNS records from cPanel.', 'ultimate-multisite');

			wu_log_add('integration-cpanel', 'DNS fetch failed: ' . $error_message, LogLevel::ERROR);

			return new \WP_Error('dns-error', $error_message);
		}

		$records         = [];
		$supported_types = $this->get_supported_record_types();

		foreach ($result->result->data as $record) {
			// Only include supported record types
			if (! isset($record->type) || ! in_array($record->type, $supported_types, true)) {
				continue;
			}

			// Get content based on record type
			$content = '';
			switch ($record->type) {
				case 'A':
				case 'AAAA':
					$content = $record->address ?? '';
					break;
				case 'CNAME':
					$content = $record->cname ?? '';
					break;
				case 'MX':
					$content = $record->exchange ?? '';
					break;
				case 'TXT':
					$content = $record->txtdata ?? '';
					// Remove surrounding quotes if present
					$content = trim($content, '"');
					break;
			}

			$records[] = DNS_Record::from_provider(
				[
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- cPanel API property
					'line_index' => $record->line_index ?? $record->Line ?? '',
					'type'       => $record->type,
					'name'       => rtrim($record->name ?? '', '.'),
					'address'    => $record->address ?? null,
					'cname'      => $record->cname ?? null,
					'exchange'   => $record->exchange ?? null,
					'txtdata'    => $record->txtdata ?? null,
					'ttl'        => $record->ttl ?? 14400,
					'preference' => $record->preference ?? null,
				],
				'cpanel'
			);
		}

		return $records;
	}

	/**
	 * Create a DNS record for a domain.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain The domain.
	 * @param array  $record Record data (type, name, content, ttl, priority).
	 * @return array|\WP_Error Created record data or WP_Error.
	 */
	public function create_dns_record(string $domain, array $record) {

		$zone = $this->extract_zone_name($domain);

		$params = [
			'zone' => $zone,
			'name' => $this->format_record_name($record['name'], $zone),
			'type' => strtoupper($record['type']),
			'ttl'  => (int) ($record['ttl'] ?? 14400),
		];

		// Add type-specific parameters
		switch (strtoupper($record['type'])) {
			case 'A':
			case 'AAAA':
				$params['address'] = $record['content'];
				break;
			case 'CNAME':
				$params['cname'] = $this->ensure_trailing_dot($record['content']);
				break;
			case 'MX':
				$params['exchange']   = $this->ensure_trailing_dot($record['content']);
				$params['preference'] = (int) ($record['priority'] ?? 10);
				break;
			case 'TXT':
				$params['txtdata'] = $record['content'];
				break;
			default:
				return new \WP_Error(
					'unsupported-type',
					/* translators: %s: record type */
					sprintf(__('Unsupported record type: %s', 'ultimate-multisite'), $record['type'])
				);
		}

		$result = $this->load_api()->uapi('DNS', 'add_zone_record', $params);

		if (! $result || ! empty($result->errors)) {
			$error_message = ! empty($result->errors) && is_array($result->errors)
				? implode(', ', $result->errors)
				: __('Failed to create DNS record.', 'ultimate-multisite');

			wu_log_add('integration-cpanel', 'DNS create failed: ' . $error_message, LogLevel::ERROR);

			return new \WP_Error('dns-create-error', $error_message);
		}

		wu_log_add(
			'integration-cpanel',
			sprintf(
				'Created DNS record: %s %s -> %s',
				$record['type'],
				$record['name'],
				$record['content']
			)
		);

		// Return the record data with generated ID
		return [
			'id'       => $result->result->data->newserial ?? time(),
			'type'     => $record['type'],
			'name'     => $record['name'],
			'content'  => $record['content'],
			'ttl'      => $params['ttl'],
			'priority' => $record['priority'] ?? null,
		];
	}

	/**
	 * Update a DNS record for a domain.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain    The domain.
	 * @param string $record_id The record identifier (line index).
	 * @param array  $record    Updated record data.
	 * @return array|\WP_Error Updated record data or WP_Error.
	 */
	public function update_dns_record(string $domain, string $record_id, array $record) {

		$zone = $this->extract_zone_name($domain);

		$params = [
			'zone' => $zone,
			'line' => (int) $record_id,
			'name' => $this->format_record_name($record['name'], $zone),
			'type' => strtoupper($record['type']),
			'ttl'  => (int) ($record['ttl'] ?? 14400),
		];

		// Add type-specific parameters
		switch (strtoupper($record['type'])) {
			case 'A':
			case 'AAAA':
				$params['address'] = $record['content'];
				break;
			case 'CNAME':
				$params['cname'] = $this->ensure_trailing_dot($record['content']);
				break;
			case 'MX':
				$params['exchange']   = $this->ensure_trailing_dot($record['content']);
				$params['preference'] = (int) ($record['priority'] ?? 10);
				break;
			case 'TXT':
				$params['txtdata'] = $record['content'];
				break;
			default:
				return new \WP_Error(
					'unsupported-type',
					/* translators: %s: record type */
					sprintf(__('Unsupported record type: %s', 'ultimate-multisite'), $record['type'])
				);
		}

		$result = $this->load_api()->uapi('DNS', 'edit_zone_record', $params);

		if (! $result || ! empty($result->errors)) {
			$error_message = ! empty($result->errors) && is_array($result->errors)
				? implode(', ', $result->errors)
				: __('Failed to update DNS record.', 'ultimate-multisite');

			wu_log_add('integration-cpanel', 'DNS update failed: ' . $error_message, LogLevel::ERROR);

			return new \WP_Error('dns-update-error', $error_message);
		}

		wu_log_add(
			'integration-cpanel',
			sprintf(
				'Updated DNS record: Line %s - %s %s -> %s',
				$record_id,
				$record['type'],
				$record['name'],
				$record['content']
			)
		);

		return [
			'id'       => $record_id,
			'type'     => $record['type'],
			'name'     => $record['name'],
			'content'  => $record['content'],
			'ttl'      => $params['ttl'],
			'priority' => $record['priority'] ?? null,
		];
	}

	/**
	 * Delete a DNS record for a domain.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain    The domain.
	 * @param string $record_id The record identifier (line index).
	 * @return bool|\WP_Error True on success or WP_Error.
	 */
	public function delete_dns_record(string $domain, string $record_id) {

		$zone = $this->extract_zone_name($domain);

		$result = $this->load_api()->uapi(
			'DNS',
			'remove_zone_record',
			[
				'zone' => $zone,
				'line' => (int) $record_id,
			]
		);

		if (! $result || ! empty($result->errors)) {
			$error_message = ! empty($result->errors) && is_array($result->errors)
				? implode(', ', $result->errors)
				: __('Failed to delete DNS record.', 'ultimate-multisite');

			wu_log_add('integration-cpanel', 'DNS delete failed: ' . $error_message, LogLevel::ERROR);

			return new \WP_Error('dns-delete-error', $error_message);
		}

		wu_log_add(
			'integration-cpanel',
			sprintf(
				'Deleted DNS record: Line %s from zone %s',
				$record_id,
				$zone
			)
		);

		return true;
	}

	/**
	 * Format the record name for cPanel API.
	 *
	 * @since 2.3.0
	 *
	 * @param string $name The record name.
	 * @param string $zone The zone name.
	 * @return string Formatted name with trailing dot.
	 */
	protected function format_record_name(string $name, string $zone): string {

		// Handle @ as root domain
		if ('@' === $name || '' === $name) {
			return $zone . '.';
		}

		// If name already ends with zone, just add trailing dot
		if (str_ends_with($name, $zone)) {
			return $name . '.';
		}

		// If name ends with dot, it's already FQDN
		if (str_ends_with($name, '.')) {
			return $name;
		}

		// Append zone
		return $name . '.' . $zone . '.';
	}

	/**
	 * Ensure a hostname has a trailing dot.
	 *
	 * @since 2.3.0
	 *
	 * @param string $hostname The hostname.
	 * @return string Hostname with trailing dot.
	 */
	protected function ensure_trailing_dot(string $hostname): string {

		return str_ends_with($hostname, '.') ? $hostname : $hostname . '.';
	}

	/**
	 * Returns the description of this integration.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('cPanel is the management panel being used on a large number of shared and dedicated hosts across the globe.', 'ultimate-multisite');
	}

	/**
	 * Returns the logo for the integration.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_logo() {

		return wu_get_asset('cpanel.svg', 'img/hosts');
	}

	/**
	 * Tests the connection with the Cloudflare API.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function test_connection() {

		$results = $this->load_api()->api2('Cron', 'fetchcron', []);

		$this->log_calls($results);

		if (isset($results->cpanelresult->data) && ! isset($results->cpanelresult->error)) {
			wp_send_json_success($results);

			exit;
		}

		wp_send_json_error($results);
	}

	/**
	 * Returns the explainer lines for the integration.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_explainer_lines() {

		$explainer_lines = [
			'will'     => [
				'send_domains' => __('Add a new Addon Domain on cPanel whenever a new domain mapping gets created on your network', 'ultimate-multisite'),
			],
			'will_not' => [],
		];

		if (is_subdomain_install()) {
			$explainer_lines['will']['send_sub_domains'] = __('Add a new SubDomain on cPanel whenever a new site gets created on your network', 'ultimate-multisite');
		}

		return $explainer_lines;
	}
}
