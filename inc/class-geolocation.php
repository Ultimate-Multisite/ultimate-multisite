<?php
/**
 * Optimized Geolocation Class.
 *
 * High-performance geolocation using local MaxMind database with multi-layer caching.
 * Eliminates external HTTP calls that were causing 1+ second delays.
 *
 * Performance improvements:
 * - Uses bundled MaxMind database reader via Composer (no external API calls)
 * - In-memory static cache for same-request lookups
 * - Object cache integration for cross-request caching
 * - Proper IP detection for CloudFlare, proxies, and load balancers
 * - Falls back gracefully when database is unavailable
 *
 * @package WP_Ultimo
 * @subpackage Checkout
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Geolocation Class.
 */
class Geolocation {


	/**
	 * Cache group for geolocation data.
	 */
	const CACHE_GROUP = 'wu_geolocation';

	/**
	 * In-memory cache for current request.
	 *
	 * @var array
	 */
	private static array $memory_cache = [];

	/**
	 * Cached IP address for current request.
	 *
	 * @var string|null
	 */
	private static ?string $cached_ip = null;

	/**
	 * Request values the cached IP was resolved from.
	 *
	 * The cache is reused only while these values are unchanged, so code that
	 * alters $_SERVER (long-running processes, tests) never gets a stale IP.
	 *
	 * @var string|null
	 */
	private static ?string $cached_ip_source = null;

	/**
	 * $_SERVER keys that influence client IP resolution.
	 *
	 * @since 2.18.1
	 */
	const IP_SOURCE_KEYS = [
		'REMOTE_ADDR',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'HTTP_TRUE_CLIENT_IP',
	];

	/**
	 * MaxMind database reader instance.
	 *
	 * @var \MaxMind\Db\Reader|null
	 */
	private static ?\MaxMind\Db\Reader $reader = null;

	/**
	 * GeoLite2 DB URL (deprecated - we use local MaxMind database).
	 *
	 * @deprecated 3.4.0
	 */
	const GEOLITE2_DB = 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.tar.gz';

	/**
	 * Hook in geolocation functionality.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Register shutdown handler to close MaxMind reader
		\register_shutdown_function([self::class, 'close_reader']);
	}

	/**
	 * Close the MaxMind database reader.
	 *
	 * @return void
	 */
	public static function close_reader(): void {
		if (null !== self::$reader) {
			try {
				self::$reader->close();
			} catch (\Exception $e) {
				// Ignore errors on shutdown.
				unset($e); // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
			self::$reader = null;
		}
	}

	/**
	 * Cloudflare edge network ranges.
	 *
	 * The CF-Connecting-IP header is only honoured when the connecting peer
	 * (REMOTE_ADDR) is inside one of these ranges. Source: Cloudflare's
	 * published IP list. Extend with the `wu_cloudflare_ip_ranges` filter.
	 *
	 * @since 2.18.1
	 */
	const CLOUDFLARE_IP_RANGES = [
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	];

	/**
	 * Loopback and private network ranges trusted as reverse proxies by default.
	 *
	 * A peer address in these ranges cannot be forged from the public internet,
	 * so it identifies a local/internal reverse proxy or load balancer.
	 *
	 * @since 2.18.1
	 */
	const PRIVATE_PROXY_RANGES = [
		'127.0.0.0/8',
		'10.0.0.0/8',
		'172.16.0.0/12',
		'192.168.0.0/16',
		'::1/128',
		'fc00::/7',
	];

	/**
	 * Get the current visitor's IP address.
	 *
	 * REMOTE_ADDR is the only value that cannot be forged by the client, so it
	 * is used unless the connecting peer is a trusted reverse proxy. Forwarding
	 * headers are only read in that case:
	 *
	 * 1. CF-Connecting-IP, when the peer is a Cloudflare edge address.
	 * 2. X-Forwarded-For, walked right-to-left, skipping trusted proxy hops.
	 * 3. X-Real-IP / True-Client-IP, set by the trusted proxy.
	 *
	 * Trusted proxies are loopback/private ranges, Cloudflare ranges, the
	 * WU_TRUSTED_PROXIES constant (comma-separated IPs/CIDRs or an array) and
	 * the `wu_trusted_proxies` filter.
	 *
	 * @return string The client IP address.
	 */
	public static function get_ip_address(): string {
		$source = self::get_ip_source();

		// Return cached IP if available (same request optimization)
		if (null !== self::$cached_ip && $source === self::$cached_ip_source) {
			return self::$cached_ip;
		}

		$remote_addr = self::get_server_ip('REMOTE_ADDR');
		$ip          = $remote_addr;

		if ('' !== $remote_addr) {
			$trusted_proxies = self::get_trusted_proxies();

			if (self::ip_in_ranges($remote_addr, $trusted_proxies)) {
				$ip = self::get_forwarded_client_ip($remote_addr, $trusted_proxies);
			}
		}

		self::$cached_ip        = $ip;
		self::$cached_ip_source = $source;

		return $ip;
	}

	/**
	 * Build an opaque fingerprint of the request values used for IP resolution.
	 *
	 * @since 2.18.1
	 * @return string
	 */
	private static function get_ip_source(): string {

		$parts = [];

		foreach (self::IP_SOURCE_KEYS as $key) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Compared as an opaque cache key only, never output or stored.
			$parts[] = isset($_SERVER[ $key ]) && is_string($_SERVER[ $key ]) ? $_SERVER[ $key ] : '';
		}

		return implode("\n", $parts);
	}

	/**
	 * Resolve the client IP from forwarding headers set by a trusted proxy.
	 *
	 * @since 2.18.1
	 *
	 * @param string   $remote_addr     The (trusted) connecting peer address.
	 * @param string[] $trusted_proxies Trusted proxy IPs/CIDRs.
	 * @return string The client IP address.
	 */
	private static function get_forwarded_client_ip(string $remote_addr, array $trusted_proxies): string {

		// Cloudflare overwrites this header, so it is reliable only from a Cloudflare edge.
		if (self::ip_in_ranges($remote_addr, self::get_cloudflare_ip_ranges())) {
			$cf_ip = self::get_server_ip('HTTP_CF_CONNECTING_IP');

			if ('' !== $cf_ip) {
				return $cf_ip;
			}
		}

		if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$forwarded = \sanitize_text_field(\wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
			$hops      = array_reverse(array_map('trim', explode(',', $forwarded)));
			$client_ip = '';

			/*
			 * Each proxy appends the address it received the request from, so only
			 * the right-most entries are trustworthy. Walk from the right and stop at
			 * the first address that is not a trusted proxy: anything further left
			 * may have been supplied by the client.
			 */
			foreach ($hops as $hop) {
				$hop = self::validate_ip($hop);

				if ('' === $hop) {
					break;
				}

				$client_ip = $hop;

				if (! self::ip_in_ranges($hop, $trusted_proxies)) {
					break;
				}
			}

			if ('' !== $client_ip) {
				return $client_ip;
			}
		}

		foreach (['HTTP_X_REAL_IP', 'HTTP_TRUE_CLIENT_IP'] as $header) {
			$header_ip = self::get_server_ip($header);

			if ('' !== $header_ip) {
				return $header_ip;
			}
		}

		return $remote_addr;
	}

	/**
	 * Read and validate a single IP address from $_SERVER.
	 *
	 * @since 2.18.1
	 *
	 * @param string $key The $_SERVER key.
	 * @return string The validated IP or empty string.
	 */
	private static function get_server_ip(string $key): string {

		if (empty($_SERVER[ $key ]) || ! is_string($_SERVER[ $key ])) {
			return '';
		}

		return self::validate_ip(trim(\sanitize_text_field(\wp_unslash($_SERVER[ $key ]))));
	}

	/**
	 * Get the list of trusted reverse proxy IPs/CIDR ranges.
	 *
	 * @since 2.18.1
	 * @return string[]
	 */
	public static function get_trusted_proxies(): array {

		$proxies = array_merge(self::PRIVATE_PROXY_RANGES, self::get_cloudflare_ip_ranges());

		if (defined('WU_TRUSTED_PROXIES')) {
			$configured = constant('WU_TRUSTED_PROXIES');

			if (is_string($configured)) {
				$configured = explode(',', $configured);
			}

			if (is_array($configured)) {
				$proxies = array_merge($proxies, $configured);
			}
		}

		/**
		 * Filters the reverse proxies whose forwarding headers are trusted.
		 *
		 * Return an empty array to always use REMOTE_ADDR.
		 *
		 * @since 2.18.1
		 *
		 * @param string[] $proxies IP addresses or CIDR ranges.
		 */
		$proxies = (array) \apply_filters('wu_trusted_proxies', $proxies);

		return self::normalize_ranges($proxies);
	}

	/**
	 * Get the Cloudflare edge IP ranges.
	 *
	 * @since 2.18.1
	 * @return string[]
	 */
	public static function get_cloudflare_ip_ranges(): array {

		/**
		 * Filters the Cloudflare edge ranges allowed to set CF-Connecting-IP.
		 *
		 * @since 2.18.1
		 *
		 * @param string[] $ranges CIDR ranges.
		 */
		return self::normalize_ranges((array) \apply_filters('wu_cloudflare_ip_ranges', self::CLOUDFLARE_IP_RANGES));
	}

	/**
	 * Normalize a list of IP/CIDR strings.
	 *
	 * @param array $ranges Raw list.
	 * @return string[]
	 */
	private static function normalize_ranges(array $ranges): array {

		$ranges = array_filter($ranges, 'is_string');

		return array_values(array_unique(array_filter(array_map('trim', $ranges))));
	}

	/**
	 * Check whether an IP address is inside any of the given IPs/CIDR ranges.
	 *
	 * @since 2.18.1
	 *
	 * @param string   $ip     The IP address.
	 * @param string[] $ranges IP addresses or CIDR ranges.
	 * @return bool
	 */
	public static function ip_in_ranges(string $ip, array $ranges): bool {

		foreach ($ranges as $range) {
			if (self::ip_in_range($ip, (string) $range)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether an IP address is inside an IP/CIDR range.
	 *
	 * Supports IPv4 and IPv6. IPv4-mapped IPv6 addresses match IPv4 ranges.
	 *
	 * @since 2.18.1
	 *
	 * @param string $ip    The IP address.
	 * @param string $range A single IP or a CIDR range (e.g. 10.0.0.0/8).
	 * @return bool
	 */
	public static function ip_in_range(string $ip, string $range): bool {

		$parts  = explode('/', trim($range), 2);
		$subnet = $parts[0];

		if (! filter_var($ip, FILTER_VALIDATE_IP) || ! filter_var($subnet, FILTER_VALIDATE_IP)) {
			return false;
		}

		$ip_bin     = inet_pton($ip);
		$subnet_bin = inet_pton($subnet);

		if (false === $ip_bin || false === $subnet_bin) {
			return false;
		}

		// Match IPv4-mapped IPv6 addresses (::ffff:a.b.c.d) against IPv4 ranges.
		if (16 === strlen($ip_bin) && 4 === strlen($subnet_bin) && str_repeat("\0", 10) . "\xff\xff" === substr($ip_bin, 0, 12)) {
			$ip_bin = substr($ip_bin, 12);
		}

		if (strlen($ip_bin) !== strlen($subnet_bin)) {
			return false;
		}

		$max_bits = strlen($ip_bin) * 8;

		if (! isset($parts[1])) {
			$bits = $max_bits;
		} elseif (ctype_digit($parts[1])) {
			$bits = (int) $parts[1];
		} else {
			return false;
		}

		if ($bits > $max_bits) {
			return false;
		}

		$whole_bytes = intdiv($bits, 8);
		$extra_bits  = $bits % 8;

		if (substr($ip_bin, 0, $whole_bytes) !== substr($subnet_bin, 0, $whole_bytes)) {
			return false;
		}

		if (0 === $extra_bits) {
			return true;
		}

		$mask = (0xFF << (8 - $extra_bits)) & 0xFF;

		return (ord($ip_bin[ $whole_bytes ]) & $mask) === (ord($subnet_bin[ $whole_bytes ]) & $mask);
	}

	/**
	 * Validate an IP address.
	 *
	 * @param string $ip The IP address to validate.
	 * @return string The validated IP or empty string.
	 */
	private static function validate_ip(string $ip): string {
		// Remove port from IPv4 (e.g., "1.2.3.4:8080" -> "1.2.3.4")
		if (preg_match('/^(\d+\.\d+\.\d+\.\d+):\d+$/', $ip, $matches)) {
			$ip = $matches[1];
		}
		// Remove brackets and port from IPv6 (e.g., "[::1]:8080" -> "::1")
		if (preg_match('/^\[([^]]+)](:\d+)?$/', $ip, $matches)) {
			$ip = $matches[1];
		}

		// Use WordPress's IP validation
		return (string) \rest_is_ip_address($ip);
	}

	/**
	 * Check if an IP is a private/local address.
	 *
	 * @param string $ip The IP address to check.
	 * @return bool True if private/local.
	 */
	private static function is_private_ip(string $ip): bool {
		if (empty($ip)) {
			return true;
		}

		// Check for private/reserved ranges
		return ! filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * Geolocate an IP address.
	 *
	 * Uses multi-layer caching and local database lookup for performance.
	 * Completely eliminates external HTTP calls.
	 *
	 * @param string $ip_address   IP Address (empty = current user).
	 * @param bool   $fallback     If true, fallbacks to alternative IP detection.
	 * @param bool   $api_fallback Deprecated - API fallback is disabled for performance.
	 * @return array{ip: string, country: string, state: string}
	 */
	public static function geolocate_ip(string $ip_address = '', bool $fallback = false, bool $api_fallback = true): array {
		// Get IP if not provided
		if (empty($ip_address)) {
			$ip_address = self::get_ip_address();
		}

		// Check in-memory cache first (same request)
		if (isset(self::$memory_cache[ $ip_address ])) {
			return self::$memory_cache[ $ip_address ];
		}

		// Allow plugins to short-circuit
		$country_code = \apply_filters('wu_geolocate_ip', false, $ip_address, $fallback, $api_fallback);

		if (false === $country_code) {
			// Try to get country from HTTP headers (CDN/proxy provided)
			$country_code = self::get_country_from_headers();

			// If no header, try local database lookup
			if (empty($country_code) && ! empty($ip_address)) {
				// Check object cache
				$cache_key    = 'geo_' . md5($ip_address);
				$country_code = \wp_cache_get($cache_key, self::CACHE_GROUP);

				if (false === $country_code) {
					// Perform database lookup
					$country_code = self::geolocate_via_db($ip_address);

					// Cache the result (even empty results to prevent repeated lookups)
					\wp_cache_set($cache_key, $country_code ?: '', self::CACHE_GROUP, DAY_IN_SECONDS);
				}
			}

			// Handle local/private IPs with fallback
			if (empty($country_code) && $fallback && self::is_private_ip($ip_address)) {
				// For local development, use a default country instead of external API
				$country_code = \apply_filters('wu_geolocation_default_country', 'US');
			}
		}

		$result = [
			'ip'      => $ip_address,
			'country' => is_string($country_code) ? $country_code : '',
			'state'   => '',
		];

		// Cache in memory for this request
		self::$memory_cache[ $ip_address ] = $result;

		return $result;
	}

	/**
	 * Get country code from HTTP headers set by CDN/proxy.
	 *
	 * @return string Country code or empty string.
	 */
	private static function get_country_from_headers(): string {
		$headers = [
			'HTTP_CF_IPCOUNTRY',     // Cloudflare
			'GEOIP_COUNTRY_CODE',    // Nginx GeoIP module
			'HTTP_X_COUNTRY_CODE',   // Generic proxy header
			'MM_COUNTRY_CODE',       // MaxMind via server
		];

		foreach ($headers as $header) {
			if (! empty($_SERVER[ $header ])) {
				$code = strtoupper(\sanitize_text_field(\wp_unslash($_SERVER[ $header ])));
				// Validate it looks like a country code (2 letters)
				if (preg_match('/^[A-Z]{2}$/', $code)) {
					return $code;
				}
			}
		}

		return '';
	}

	/**
	 * Geolocate using local MaxMind database.
	 *
	 * Uses the bundled MaxMind reader via Composer autoloader.
	 *
	 * @param string $ip_address The IP address to lookup.
	 * @return string Country code or empty string.
	 */
	private static function geolocate_via_db(string $ip_address): string {
		$database_path = self::get_local_database_path();

		if (! file_exists($database_path)) {
			return '';
		}

		try {
			// Reuse reader instance for performance
			if (null === self::$reader) {
				// MaxMind\Db\Reader is loaded via Composer autoloader
				self::$reader = new \MaxMind\Db\Reader($database_path);
			}

			$data = self::$reader->get($ip_address);
			if (isset($data['country']['iso_code'])) {
				return strtoupper(\sanitize_text_field($data['country']['iso_code']));
			}
		} catch (\Exception $e) {
			// Log error but don't break the site
			wu_log_add('geolocation', 'Geolocation DB error: ' . $e->getMessage());
		}

		return '';
	}

	/**
	 * Get the path to the local MaxMind database.
	 *
	 * Checks locations in order of preference:
	 * 1. WP Ultimo's own database path (in uploads directory)
	 * 2. Custom path via filter
	 *
	 * @param string $deprecated Deprecated parameter.
	 * @return string Database path.
	 */
	public static function get_local_database_path(string $deprecated = '2'): string {
		// Use WP Ultimo's own path in uploads directory
		$upload_dir = \wp_upload_dir();
		$wu_path    = $upload_dir['basedir'] . '/GeoLite2-Country.mmdb';

		return \apply_filters('wu_geolocation_local_database_path', $wu_path, $deprecated);
	}

	/**
	 * Get user IP Address using an external service.
	 *
	 * @deprecated External IP lookup is disabled for performance.
	 *             Use get_ip_address() instead.
	 * @return string
	 */
	public static function get_external_ip_address(): string {
		// For backwards compatibility, just return the detected IP
		// External API calls have been removed for performance
		$ip = self::get_ip_address();

		// If it's a private IP, return a placeholder
		if (self::is_private_ip($ip)) {
			return '0.0.0.0';
		}

		return $ip;
	}

	/**
	 * Check if server supports MaxMind GeoLite2 Reader.
	 *
	 * @return bool
	 */
	private static function supports_geolite2(): bool {
		// Check if MaxMind reader is available via Composer autoloader
		return class_exists('MaxMind\Db\Reader');
	}

	/**
	 * Check if geolocation is enabled.
	 *
	 * @param string $current_settings Current geolocation settings.
	 * @return bool
	 */
	private static function is_geolocation_enabled(string $current_settings): bool {
		return in_array($current_settings, ['geolocation', 'geolocation_ajax'], true);
	}

	/**
	 * Update geoip database.
	 *
	 * @deprecated Database management must be handled separately.
	 * @return void
	 */
	public static function update_database(): void {
		// Database updates should be handled by a separate process
		// This method is kept for backwards compatibility
	}

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

	/**
	 * Maybe trigger a DB update for the first time.
	 *
	 * @deprecated Database management must be handled separately.
	 * @param string $new_value New value.
	 * @param string $old_value Old value.
	 * @return string
	 */
	public static function maybe_update_database(
		string $new_value,
		string $old_value
	): string {

		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $new_value;
	}

	/**
	 * Disable geolocation on legacy PHP.
	 *
	 * @deprecated PHP 8.0+ is now required.
	 * @param string $default_customer_address Current value.
	 * @return string
	 */
	public static function disable_geolocation_on_legacy_php(string $default_customer_address): string {
		return $default_customer_address;
	}

	/**
	 * Clear all geolocation caches.
	 *
	 * Useful when database is updated or for debugging.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$memory_cache     = [];
		self::$cached_ip        = null;
		self::$cached_ip_source = null;

		// Note: Object cache group deletion depends on cache implementation
		// Most object caches don't support group deletion
		if (function_exists('wp_cache_flush_group')) {
			\wp_cache_flush_group(self::CACHE_GROUP);
		}
	}
}
