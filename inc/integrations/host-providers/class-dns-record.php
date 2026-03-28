<?php
/**
 * DNS Record value object for DNS management.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Host_Providers
 * @since 2.3.0
 */

namespace WP_Ultimo\Integrations\Host_Providers;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Represents a DNS record with common properties across different providers.
 *
 * @since 2.3.0
 */
class DNS_Record {

	/**
	 * The unique identifier for this record (provider-specific).
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * The DNS record type (A, AAAA, CNAME, MX, TXT).
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * The record name/host (subdomain or @ for root).
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * The record content/value (IP address, hostname, or text).
	 *
	 * @var string
	 */
	public string $content;

	/**
	 * Time to live in seconds.
	 *
	 * @var int
	 */
	public int $ttl;

	/**
	 * Priority value for MX records.
	 *
	 * @var int|null
	 */
	public ?int $priority;

	/**
	 * Whether the record is proxied (Cloudflare-specific).
	 *
	 * @var bool
	 */
	public bool $proxied;

	/**
	 * Provider-specific metadata.
	 *
	 * @var array
	 */
	public array $meta;

	/**
	 * Valid DNS record types.
	 *
	 * @var array
	 */
	public const VALID_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT'];

	/**
	 * Common TTL values in seconds.
	 *
	 * @var array
	 */
	public const TTL_OPTIONS = [
		60     => '1 minute',
		300    => '5 minutes',
		600    => '10 minutes',
		1800   => '30 minutes',
		3600   => '1 hour',
		7200   => '2 hours',
		14400  => '4 hours',
		43200  => '12 hours',
		86400  => '1 day',
		172800 => '2 days',
		604800 => '1 week',
	];

	/**
	 * Constructor.
	 *
	 * @since 2.3.0
	 *
	 * @param array $data Record data.
	 */
	public function __construct(array $data) {

		$this->id       = (string) ($data['id'] ?? '');
		$this->type     = strtoupper($data['type'] ?? 'A');
		$this->name     = (string) ($data['name'] ?? '');
		$this->content  = (string) ($data['content'] ?? '');
		$this->ttl      = (int) ($data['ttl'] ?? 3600);
		$this->priority = isset($data['priority']) ? (int) $data['priority'] : null;
		$this->proxied  = (bool) ($data['proxied'] ?? false);
		$this->meta     = (array) ($data['meta'] ?? []);
	}

	/**
	 * Convert the record to an array.
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	public function to_array(): array {

		return [
			'id'       => $this->id,
			'type'     => $this->type,
			'name'     => $this->name,
			'content'  => $this->content,
			'ttl'      => $this->ttl,
			'priority' => $this->priority,
			'proxied'  => $this->proxied,
			'meta'     => $this->meta,
		];
	}

	/**
	 * Get the record ID.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return $this->id;
	}

	/**
	 * Get the record type.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_type(): string {

		return $this->type;
	}

	/**
	 * Get the record name/host.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_name(): string {

		return $this->name;
	}

	/**
	 * Get the full hostname including the domain.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain The base domain.
	 * @return string
	 */
	public function get_full_name(string $domain): string {

		if ('@' === $this->name || '' === $this->name || $domain === $this->name) {
			return $domain;
		}

		// If name already ends with domain, return as-is
		if (str_ends_with($this->name, $domain)) {
			return $this->name;
		}

		return $this->name . '.' . $domain;
	}

	/**
	 * Get the record content/value.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_content(): string {

		return $this->content;
	}

	/**
	 * Get the TTL value.
	 *
	 * @since 2.3.0
	 *
	 * @return int
	 */
	public function get_ttl(): int {

		return $this->ttl;
	}

	/**
	 * Get a human-readable TTL string.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_ttl_label(): string {

		if (1 === $this->ttl) {
			return __('Auto', 'ultimate-multisite');
		}

		if (isset(self::TTL_OPTIONS[ $this->ttl ])) {
			return self::TTL_OPTIONS[ $this->ttl ];
		}

		// Format custom TTL values
		if ($this->ttl < 60) {
			/* translators: %d: number of seconds */
			return sprintf(_n('%d second', '%d seconds', $this->ttl, 'ultimate-multisite'), $this->ttl);
		}

		if ($this->ttl < 3600) {
			$minutes = floor($this->ttl / 60);
			/* translators: %d: number of minutes */
			return sprintf(_n('%d minute', '%d minutes', $minutes, 'ultimate-multisite'), $minutes);
		}

		if ($this->ttl < 86400) {
			$hours = floor($this->ttl / 3600);
			/* translators: %d: number of hours */
			return sprintf(_n('%d hour', '%d hours', $hours, 'ultimate-multisite'), $hours);
		}

		$days = floor($this->ttl / 86400);
		/* translators: %d: number of days */
		return sprintf(_n('%d day', '%d days', $days, 'ultimate-multisite'), $days);
	}

	/**
	 * Get the priority (for MX records).
	 *
	 * @since 2.3.0
	 *
	 * @return int|null
	 */
	public function get_priority(): ?int {

		return $this->priority;
	}

	/**
	 * Check if the record is proxied (Cloudflare).
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	public function is_proxied(): bool {

		return $this->proxied;
	}

	/**
	 * Get provider-specific metadata.
	 *
	 * @since 2.3.0
	 *
	 * @param string|null $key Optional key to retrieve specific value.
	 * @return mixed
	 */
	public function get_meta(?string $key = null) {

		if (null === $key) {
			return $this->meta;
		}

		return $this->meta[ $key ] ?? null;
	}

	/**
	 * Validate the record data.
	 *
	 * @since 2.3.0
	 *
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate() {

		// Check required fields
		if (empty($this->type)) {
			return new \WP_Error(
				'missing_type',
				__('DNS record type is required.', 'ultimate-multisite')
			);
		}

		if (empty($this->name)) {
			return new \WP_Error(
				'missing_name',
				__('DNS record name is required.', 'ultimate-multisite')
			);
		}

		if (empty($this->content)) {
			return new \WP_Error(
				'missing_content',
				__('DNS record content is required.', 'ultimate-multisite')
			);
		}

		// Validate type
		if (! in_array($this->type, self::VALID_TYPES, true)) {
			return new \WP_Error(
				'invalid_type',
				sprintf(
					/* translators: %s: list of valid types */
					__('Invalid DNS record type. Valid types are: %s', 'ultimate-multisite'),
					implode(', ', self::VALID_TYPES)
				)
			);
		}

		// Type-specific validation
		$type_validation = $this->validate_by_type();
		if (is_wp_error($type_validation)) {
			return $type_validation;
		}

		// Validate TTL
		if ($this->ttl < 1) {
			return new \WP_Error(
				'invalid_ttl',
				__('TTL must be a positive integer.', 'ultimate-multisite')
			);
		}

		return true;
	}

	/**
	 * Validate record based on its type.
	 *
	 * @since 2.3.0
	 *
	 * @return true|\WP_Error
	 */
	protected function validate_by_type() {

		switch ($this->type) {
			case 'A':
				if (! filter_var($this->content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
					return new \WP_Error(
						'invalid_ipv4',
						__('A record requires a valid IPv4 address.', 'ultimate-multisite')
					);
				}
				break;

			case 'AAAA':
				if (! filter_var($this->content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
					return new \WP_Error(
						'invalid_ipv6',
						__('AAAA record requires a valid IPv6 address.', 'ultimate-multisite')
					);
				}
				break;

			case 'CNAME':
				// CNAME should be a hostname, not an IP
				if (filter_var($this->content, FILTER_VALIDATE_IP)) {
					return new \WP_Error(
						'invalid_cname',
						__('CNAME record requires a hostname, not an IP address.', 'ultimate-multisite')
					);
				}
				break;

			case 'MX':
				// MX requires priority
				if (null === $this->priority || $this->priority < 0) {
					return new \WP_Error(
						'missing_priority',
						__('MX record requires a valid priority value.', 'ultimate-multisite')
					);
				}
				// MX should be a hostname
				if (filter_var($this->content, FILTER_VALIDATE_IP)) {
					return new \WP_Error(
						'invalid_mx',
						__('MX record requires a mail server hostname, not an IP address.', 'ultimate-multisite')
					);
				}
				break;

			case 'TXT':
				// TXT records can contain almost anything, but limit length
				if (strlen($this->content) > 2048) {
					return new \WP_Error(
						'txt_too_long',
						__('TXT record content is too long (max 2048 characters).', 'ultimate-multisite')
					);
				}
				break;
		}

		return true;
	}

	/**
	 * Get CSS class for record type badge.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_type_class(): string {

		$classes = [
			'A'     => 'wu-bg-blue-100 wu-text-blue-800',
			'AAAA'  => 'wu-bg-purple-100 wu-text-purple-800',
			'CNAME' => 'wu-bg-green-100 wu-text-green-800',
			'MX'    => 'wu-bg-orange-100 wu-text-orange-800',
			'TXT'   => 'wu-bg-gray-100 wu-text-gray-800',
		];

		return $classes[ $this->type ] ?? 'wu-bg-gray-100 wu-text-gray-800';
	}

	/**
	 * Create a DNS_Record from provider-specific data.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $data     Provider data.
	 * @param string $provider Provider ID.
	 * @return self
	 */
	public static function from_provider(array $data, string $provider): self {

		// Normalize data based on provider format
		switch ($provider) {
			case 'cloudflare':
				return new self(
					[
						'id'       => $data['id'] ?? '',
						'type'     => $data['type'] ?? 'A',
						'name'     => $data['name'] ?? '',
						'content'  => $data['content'] ?? '',
						'ttl'      => $data['ttl'] ?? 1,
						'priority' => $data['priority'] ?? null,
						'proxied'  => $data['proxied'] ?? false,
						'meta'     => [
							'zone_id'   => $data['zone_id'] ?? '',
							'zone_name' => $data['zone_name'] ?? '',
						],
					]
				);

			case 'cpanel':
				return new self(
					[
						'id'       => $data['line_index'] ?? $data['line'] ?? '',
						'type'     => $data['type'] ?? 'A',
						'name'     => rtrim($data['name'] ?? '', '.'),
						'content'  => $data['address'] ?? $data['cname'] ?? $data['exchange'] ?? $data['txtdata'] ?? '',
						'ttl'      => (int) ($data['ttl'] ?? 14400),
						'priority' => isset($data['preference']) ? (int) $data['preference'] : null,
						'proxied'  => false,
						'meta'     => [
							'line_index' => $data['line_index'] ?? '',
						],
					]
				);

			case 'hestia':
				return new self(
					[
						'id'       => $data['id'] ?? ($data['type'] . '-' . ($data['name'] ?? '@')),
						'type'     => $data['type'] ?? 'A',
						'name'     => $data['name'] ?? '@',
						'content'  => $data['value'] ?? '',
						'ttl'      => (int) ($data['ttl'] ?? 3600),
						'priority' => isset($data['priority']) ? (int) $data['priority'] : null,
						'proxied'  => false,
						'meta'     => [],
					]
				);

			default:
				return new self($data);
		}
	}
}
