<?php
/**
 * DNS Record Manager
 *
 * Handles DNS record management operations including AJAX handlers,
 * settings, and provider coordination.
 *
 * @package WP_Ultimo
 * @subpackage Managers/DNS_Record_Manager
 * @since 2.3.0
 */

namespace WP_Ultimo\Managers;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Host_Providers\DNS_Record;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles DNS record management operations.
 *
 * @since 2.3.0
 */
class DNS_Record_Manager extends Base_Manager {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The manager slug.
	 *
	 * @since 2.3.0
	 * @var string
	 */
	protected $slug = 'dns-record';

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function init(): void {

		// Register AJAX handlers
		add_action('wp_ajax_wu_get_dns_records_for_domain', [$this, 'ajax_get_records']);
		add_action('wp_ajax_wu_create_dns_record', [$this, 'ajax_create_record']);
		add_action('wp_ajax_wu_update_dns_record', [$this, 'ajax_update_record']);
		add_action('wp_ajax_wu_delete_dns_record', [$this, 'ajax_delete_record']);
		add_action('wp_ajax_wu_bulk_dns_operations', [$this, 'ajax_bulk_operations']);

		// Add DNS settings to domain mapping section
		add_action('wu_settings_domain_mapping', [$this, 'add_dns_settings'], 20);
	}

	/**
	 * Get the active DNS-capable provider.
	 *
	 * Finds the first enabled host provider that supports DNS management.
	 *
	 * @since 2.3.0
	 *
	 * @return \WP_Ultimo\Integrations\Host_Providers\Base_Host_Provider|null
	 */
	public function get_dns_provider(): ?object {

		$domain_manager = Domain_Manager::get_instance();
		$integrations   = $domain_manager->get_integrations();

		foreach ($integrations as $id => $class) {
			$instance = $domain_manager->get_integration_instance($id);

			if ($instance && $instance->is_enabled() && $instance->supports_dns_management() && $instance->is_dns_enabled()) {
				return $instance;
			}
		}

		return null;
	}

	/**
	 * Get all DNS-capable providers.
	 *
	 * @since 2.3.0
	 *
	 * @return array Array of provider instances that support DNS.
	 */
	public function get_dns_capable_providers(): array {

		$domain_manager = Domain_Manager::get_instance();
		$integrations   = $domain_manager->get_integrations();
		$dns_providers  = [];

		foreach ($integrations as $id => $class) {
			$instance = $domain_manager->get_integration_instance($id);

			if ($instance && $instance->supports_dns_management()) {
				$dns_providers[ $id ] = $instance;
			}
		}

		return $dns_providers;
	}

	/**
	 * Check if customer can manage DNS for a domain.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $user_id The user ID.
	 * @param string $domain  The domain name.
	 * @return bool
	 */
	public function customer_can_manage_dns(int $user_id, string $domain): bool {

		// Super admins can always manage DNS
		if (is_super_admin($user_id)) {
			return true;
		}

		// Check if customer DNS management is enabled
		if (! wu_get_setting('enable_customer_dns_management', false)) {
			return false;
		}

		// Find the domain and check ownership
		$domain_obj = wu_get_domain_by_domain($domain);
		if (! $domain_obj) {
			return false;
		}

		$site = $domain_obj->get_site();
		if (! $site) {
			return false;
		}

		// Get customer for this user
		$customer = wu_get_customer_by_user_id($user_id);
		if (! $customer) {
			return false;
		}

		// Check if customer owns the site
		return $site->get_customer_id() === $customer->get_id();
	}

	/**
	 * Get allowed record types for a user.
	 *
	 * @since 2.3.0
	 *
	 * @param int $user_id The user ID.
	 * @return array
	 */
	public function get_allowed_record_types(int $user_id): array {

		// Super admins get all types
		if (is_super_admin($user_id)) {
			return DNS_Record::VALID_TYPES;
		}

		// Get allowed types from settings
		$allowed = wu_get_setting('dns_record_types_allowed', ['A', 'CNAME', 'TXT']);

		return array_intersect($allowed, DNS_Record::VALID_TYPES);
	}

	/**
	 * AJAX handler for getting DNS records.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function ajax_get_records(): void {

		check_ajax_referer('wu_dns_nonce', 'nonce');

		$domain  = sanitize_text_field(wu_request('domain', ''));
		$user_id = get_current_user_id();

		if (empty($domain)) {
			wp_send_json_error(
				new \WP_Error(
					'missing_domain',
					__('Domain is required.', 'ultimate-multisite')
				)
			);
		}

		if (! $this->customer_can_manage_dns($user_id, $domain)) {
			wp_send_json_error(
				new \WP_Error(
					'permission_denied',
					__('You do not have permission to manage DNS for this domain.', 'ultimate-multisite')
				)
			);
		}

		$provider = $this->get_dns_provider();

		if (! $provider) {
			// Fall back to read-only PHPDNS lookup
			$records = Domain_Manager::dns_get_record($domain);

			wp_send_json_success(
				[
					'records'  => $records,
					'readonly' => true,
					'message'  => __('DNS management is not available. Records are read-only.', 'ultimate-multisite'),
				]
			);
		}

		$records = $provider->get_dns_records($domain);

		if (is_wp_error($records)) {
			// Fall back to PHPDNS on error
			$fallback_records = Domain_Manager::dns_get_record($domain);

			wp_send_json_success(
				[
					'records'  => $fallback_records,
					'readonly' => true,
					'message'  => $records->get_error_message(),
				]
			);
		}

		wp_send_json_success(
			[
				'records'      => array_map(fn($r) => $r instanceof DNS_Record ? $r->to_array() : $r, $records),
				'readonly'     => false,
				'provider'     => $provider->get_id(),
				'record_types' => $provider->get_supported_record_types(),
			]
		);
	}

	/**
	 * AJAX handler for creating DNS record.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function ajax_create_record(): void {

		check_ajax_referer('wu_dns_nonce', 'nonce');

		$domain  = sanitize_text_field(wu_request('domain', ''));
		$record  = wu_request('record', []);
		$user_id = get_current_user_id();

		if (empty($domain)) {
			wp_send_json_error(
				new \WP_Error(
					'missing_domain',
					__('Domain is required.', 'ultimate-multisite')
				)
			);
		}

		if (! $this->customer_can_manage_dns($user_id, $domain)) {
			wp_send_json_error(
				new \WP_Error(
					'permission_denied',
					__('You do not have permission to manage DNS for this domain.', 'ultimate-multisite')
				)
			);
		}

		$provider = $this->get_dns_provider();

		if (! $provider) {
			wp_send_json_error(
				new \WP_Error(
					'no_provider',
					__('No DNS provider configured.', 'ultimate-multisite')
				)
			);
		}

		// Sanitize record data
		$record = $this->sanitize_record_data($record);

		// Check if record type is allowed for this user
		$allowed_types = $this->get_allowed_record_types($user_id);
		if (! in_array($record['type'], $allowed_types, true)) {
			wp_send_json_error(
				new \WP_Error(
					'type_not_allowed',
					__('You are not allowed to create this type of DNS record.', 'ultimate-multisite')
				)
			);
		}

		// Validate record
		$dns_record = new DNS_Record($record);
		$validation = $dns_record->validate();

		if (is_wp_error($validation)) {
			wp_send_json_error($validation);
		}

		$result = $provider->create_dns_record($domain, $record);

		if (is_wp_error($result)) {
			wp_send_json_error($result);
		}

		// Log the action
		wu_log_add(
			"dns-{$domain}",
			sprintf(
			/* translators: %1$s: record type, %2$s: record name, %3$s: record content */
				__('DNS record created: %1$s %2$s -> %3$s', 'ultimate-multisite'),
				$record['type'],
				$record['name'],
				$record['content']
			)
		);

		wp_send_json_success($result);
	}

	/**
	 * AJAX handler for updating DNS record.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function ajax_update_record(): void {

		check_ajax_referer('wu_dns_nonce', 'nonce');

		$domain    = sanitize_text_field(wu_request('domain', ''));
		$record_id = sanitize_text_field(wu_request('record_id', ''));
		$record    = wu_request('record', []);
		$user_id   = get_current_user_id();

		if (empty($domain) || empty($record_id)) {
			wp_send_json_error(
				new \WP_Error(
					'missing_params',
					__('Domain and record ID are required.', 'ultimate-multisite')
				)
			);
		}

		if (! $this->customer_can_manage_dns($user_id, $domain)) {
			wp_send_json_error(
				new \WP_Error(
					'permission_denied',
					__('You do not have permission to manage DNS for this domain.', 'ultimate-multisite')
				)
			);
		}

		$provider = $this->get_dns_provider();

		if (! $provider) {
			wp_send_json_error(
				new \WP_Error(
					'no_provider',
					__('No DNS provider configured.', 'ultimate-multisite')
				)
			);
		}

		// Sanitize record data
		$record = $this->sanitize_record_data($record);

		// Check if record type is allowed for this user
		$allowed_types = $this->get_allowed_record_types($user_id);
		if (! in_array($record['type'], $allowed_types, true)) {
			wp_send_json_error(
				new \WP_Error(
					'type_not_allowed',
					__('You are not allowed to modify this type of DNS record.', 'ultimate-multisite')
				)
			);
		}

		// Validate record
		$dns_record = new DNS_Record($record);
		$validation = $dns_record->validate();

		if (is_wp_error($validation)) {
			wp_send_json_error($validation);
		}

		$result = $provider->update_dns_record($domain, $record_id, $record);

		if (is_wp_error($result)) {
			wp_send_json_error($result);
		}

		// Log the action
		wu_log_add(
			"dns-{$domain}",
			sprintf(
			/* translators: %1$s: record ID, %2$s: record type, %3$s: record name */
				__('DNS record updated: ID %1$s (%2$s %3$s)', 'ultimate-multisite'),
				$record_id,
				$record['type'],
				$record['name']
			)
		);

		wp_send_json_success($result);
	}

	/**
	 * AJAX handler for deleting DNS record.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function ajax_delete_record(): void {

		check_ajax_referer('wu_dns_nonce', 'nonce');

		$domain    = sanitize_text_field(wu_request('domain', ''));
		$record_id = sanitize_text_field(wu_request('record_id', ''));
		$user_id   = get_current_user_id();

		if (empty($domain) || empty($record_id)) {
			wp_send_json_error(
				new \WP_Error(
					'missing_params',
					__('Domain and record ID are required.', 'ultimate-multisite')
				)
			);
		}

		if (! $this->customer_can_manage_dns($user_id, $domain)) {
			wp_send_json_error(
				new \WP_Error(
					'permission_denied',
					__('You do not have permission to manage DNS for this domain.', 'ultimate-multisite')
				)
			);
		}

		$provider = $this->get_dns_provider();

		if (! $provider) {
			wp_send_json_error(
				new \WP_Error(
					'no_provider',
					__('No DNS provider configured.', 'ultimate-multisite')
				)
			);
		}

		$result = $provider->delete_dns_record($domain, $record_id);

		if (is_wp_error($result)) {
			wp_send_json_error($result);
		}

		// Log the action
		wu_log_add(
			"dns-{$domain}",
			sprintf(
			/* translators: %s: record ID */
				__('DNS record deleted: ID %s', 'ultimate-multisite'),
				$record_id
			)
		);

		wp_send_json_success(['deleted' => true]);
	}

	/**
	 * AJAX handler for bulk DNS operations (admin only).
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function ajax_bulk_operations(): void {

		check_ajax_referer('wu_dns_nonce', 'nonce');

		// Only super admins can perform bulk operations
		if (! is_super_admin()) {
			wp_send_json_error(
				new \WP_Error(
					'permission_denied',
					__('Only network administrators can perform bulk DNS operations.', 'ultimate-multisite')
				)
			);
		}

		$operation = sanitize_text_field(wu_request('operation', ''));
		$domain    = sanitize_text_field(wu_request('domain', ''));
		$records   = wu_request('records', []);

		if (empty($domain) || empty($operation)) {
			wp_send_json_error(
				new \WP_Error(
					'missing_params',
					__('Domain and operation are required.', 'ultimate-multisite')
				)
			);
		}

		$provider = $this->get_dns_provider();

		if (! $provider) {
			wp_send_json_error(
				new \WP_Error(
					'no_provider',
					__('No DNS provider configured.', 'ultimate-multisite')
				)
			);
		}

		$results = [
			'success' => [],
			'failed'  => [],
		];

		switch ($operation) {
			case 'delete':
				foreach ($records as $record_id) {
					$result = $provider->delete_dns_record($domain, sanitize_text_field($record_id));

					if (is_wp_error($result)) {
						$results['failed'][ $record_id ] = $result->get_error_message();
					} else {
						$results['success'][] = $record_id;
					}
				}
				break;

			case 'import':
				foreach ($records as $record) {
					$record = $this->sanitize_record_data($record);
					$result = $provider->create_dns_record($domain, $record);

					if (is_wp_error($result)) {
						$results['failed'][] = [
							'record'  => $record,
							'message' => $result->get_error_message(),
						];
					} else {
						$results['success'][] = $result;
					}
				}
				break;

			default:
				wp_send_json_error(
					new \WP_Error(
						'invalid_operation',
						__('Invalid bulk operation.', 'ultimate-multisite')
					)
				);
		}

		// Log the action
		wu_log_add(
			"dns-{$domain}",
			sprintf(
			/* translators: %1$s: operation, %2$d: success count, %3$d: failed count */
				__('Bulk DNS operation "%1$s": %2$d succeeded, %3$d failed', 'ultimate-multisite'),
				$operation,
				count($results['success']),
				count($results['failed'])
			)
		);

		wp_send_json_success($results);
	}

	/**
	 * Sanitize DNS record data.
	 *
	 * @since 2.3.0
	 *
	 * @param array $record Raw record data.
	 * @return array Sanitized record data.
	 */
	protected function sanitize_record_data(array $record): array {

		return [
			'type'     => strtoupper(sanitize_text_field($record['type'] ?? 'A')),
			'name'     => sanitize_text_field($record['name'] ?? ''),
			'content'  => sanitize_text_field($record['content'] ?? ''),
			'ttl'      => absint($record['ttl'] ?? 3600),
			'priority' => isset($record['priority']) ? absint($record['priority']) : null,
			'proxied'  => ! empty($record['proxied']),
		];
	}

	/**
	 * Add DNS-related settings to domain mapping section.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function add_dns_settings(): void {

		wu_register_settings_field(
			'domain-mapping',
			'dns_management_header',
			[
				'title' => __('DNS Record Management', 'ultimate-multisite'),
				'desc'  => __('Configure DNS record management features.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'domain-mapping',
			'enable_customer_dns_management',
			[
				'title'   => __('Enable Customer DNS Management', 'ultimate-multisite'),
				'desc'    => __('Allow customers to manage DNS records for their domains.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
				'require' => [
					'enable_domain_mapping' => 1,
				],
			]
		);

		wu_register_settings_field(
			'domain-mapping',
			'dns_record_types_allowed',
			[
				'title'   => __('Allowed Record Types for Customers', 'ultimate-multisite'),
				'desc'    => __('Select which DNS record types customers can manage.', 'ultimate-multisite'),
				'type'    => 'multiselect',
				'options' => [
					'A'     => __('A (IPv4 Address)', 'ultimate-multisite'),
					'AAAA'  => __('AAAA (IPv6 Address)', 'ultimate-multisite'),
					'CNAME' => __('CNAME (Alias)', 'ultimate-multisite'),
					'MX'    => __('MX (Mail Exchange)', 'ultimate-multisite'),
					'TXT'   => __('TXT (Text Record)', 'ultimate-multisite'),
				],
				'default' => ['A', 'CNAME', 'TXT'],
				'require' => [
					'enable_domain_mapping'          => 1,
					'enable_customer_dns_management' => 1,
				],
			]
		);

		wu_register_settings_field(
			'domain-mapping',
			'dns_management_instructions',
			[
				'title'      => __('DNS Management Instructions', 'ultimate-multisite'),
				'desc'       => __('Instructions shown to customers when managing DNS records. HTML is allowed.', 'ultimate-multisite'),
				'type'       => 'textarea',
				'default'    => __('Manage your domain\'s DNS records below. Changes may take up to 24 hours to propagate across the internet.', 'ultimate-multisite'),
				'html_attr'  => ['rows' => 3],
				'require'    => [
					'enable_domain_mapping'          => 1,
					'enable_customer_dns_management' => 1,
				],
				'allow_html' => true,
			]
		);

		// Add per-provider DNS enable settings for capable providers
		$dns_providers = $this->get_dns_capable_providers();

		if (! empty($dns_providers)) {
			wu_register_settings_field(
				'domain-mapping',
				'dns_provider_settings_header',
				[
					'title' => __('DNS Provider Settings', 'ultimate-multisite'),
					'desc'  => __('Enable DNS management for specific hosting providers.', 'ultimate-multisite'),
					'type'  => 'header',
				]
			);

			foreach ($dns_providers as $id => $provider) {
				$dns_enabled = get_network_option(null, 'wu_dns_integrations_enabled', []);

				wu_register_settings_field(
					'domain-mapping',
					"dns_provider_{$id}",
					[
						'title'   => sprintf(
							/* translators: %s: provider name */
							__('Enable DNS for %s', 'ultimate-multisite'),
							$provider->get_title()
						),
						'desc'    => sprintf(
							/* translators: %s: provider name */
							__('Enable DNS record management via %s API.', 'ultimate-multisite'),
							$provider->get_title()
						),
						'type'    => 'toggle',
						'default' => 0,
						'value'   => ! empty($dns_enabled[ $id ]) ? 1 : 0,
						'require' => [
							'enable_domain_mapping' => 1,
						],
					]
				);
			}
		}
	}

	/**
	 * Export DNS records to BIND format.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain  The domain.
	 * @param array  $records Array of DNS records.
	 * @return string BIND zone file format.
	 */
	public function export_to_bind(string $domain, array $records): string {

		$output  = "; Zone file for {$domain}\n";
		$output .= '; Exported by Ultimate Multisite on ' . current_time('mysql') . "\n\n";
		$output .= "\$ORIGIN {$domain}.\n";
		$output .= "\$TTL 3600\n\n";

		foreach ($records as $record) {
			if ($record instanceof DNS_Record) {
				$record = $record->to_array();
			}

			$name = $record['name'] === $domain ? '@' : str_replace(".{$domain}", '', $record['name']);
			$ttl  = $record['ttl'] ?? 3600;
			$type = $record['type'];

			switch ($type) {
				case 'MX':
					$priority = $record['priority'] ?? 10;
					$output  .= "{$name}\t{$ttl}\tIN\t{$type}\t{$priority}\t{$record['content']}.\n";
					break;

				case 'TXT':
					$content = '"' . addslashes($record['content']) . '"';
					$output .= "{$name}\t{$ttl}\tIN\t{$type}\t{$content}\n";
					break;

				case 'CNAME':
					$output .= "{$name}\t{$ttl}\tIN\t{$type}\t{$record['content']}.\n";
					break;

				default:
					$output .= "{$name}\t{$ttl}\tIN\t{$type}\t{$record['content']}\n";
			}
		}

		return $output;
	}

	/**
	 * Parse BIND format to DNS records.
	 *
	 * @since 2.3.0
	 *
	 * @param string $content BIND zone file content.
	 * @param string $domain  The domain name.
	 * @return array Array of parsed records.
	 */
	public function parse_bind_format(string $content, string $domain): array {

		$records     = [];
		$lines       = explode("\n", $content);
		$default_ttl = 3600;

		foreach ($lines as $line) {
			$line = trim($line);

			// Skip comments and empty lines
			if (empty($line) || strpos($line, ';') === 0) {
				continue;
			}

			// Parse $TTL directive
			if (preg_match('/^\$TTL\s+(\d+)/i', $line, $matches)) {
				$default_ttl = (int) $matches[1];
				continue;
			}

			// Skip other directives
			if (strpos($line, '$') === 0) {
				continue;
			}

			// Parse record line
			// Format: name [ttl] [class] type content
			$parts = preg_split('/\s+/', $line);

			if (count($parts) < 3) {
				continue;
			}

			$record = [
				'name'    => '',
				'ttl'     => $default_ttl,
				'type'    => '',
				'content' => '',
			];

			$idx = 0;

			// Name
			$record['name'] = $parts[ $idx ];
			if ('@' === $record['name']) {
				$record['name'] = $domain;
			}
			++$idx;

			// TTL (optional)
			if (isset($parts[ $idx ]) && is_numeric($parts[ $idx ])) {
				$record['ttl'] = (int) $parts[ $idx ];
				++$idx;
			}

			// Class (optional, usually IN)
			if (isset($parts[ $idx ]) && 'IN' === strtoupper($parts[ $idx ])) {
				++$idx;
			}

			// Type
			if (isset($parts[ $idx ])) {
				$record['type'] = strtoupper($parts[ $idx ]);
				++$idx;
			}

			// Content (rest of the line)
			if ('MX' === $record['type'] && isset($parts[ $idx ])) {
				$record['priority'] = (int) $parts[ $idx ];
				++$idx;
			}

			$content_parts = array_slice($parts, $idx);
			$content       = implode(' ', $content_parts);

			// Clean up content
			$content = rtrim($content, '.');
			$content = trim($content, '"');

			$record['content'] = $content;

			// Only include supported record types
			if (in_array($record['type'], DNS_Record::VALID_TYPES, true)) {
				$records[] = $record;
			}
		}

		return $records;
	}
}
