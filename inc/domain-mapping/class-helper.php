<?php
/**
 * Helper class for domain mapping functionality.
 *
 * @package WP_Ultimo
 * @subpackage Domain_Mapping
 * @since 2.0.0
 */

namespace WP_Ultimo\Domain_Mapping;

use Psr\Log\LogLevel;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Helper class for domain mapping functionality.
 *
 * @since 2.0.0
 */
class Helper {

	/**
	 * List of API endpoints we can use to check the remote IP address.
	 *
	 * @var array
	 */
	public static $providers = [
		'https://ipv4.canihazip.com/s',
		'https://ipv4.icanhazip.com/',
		'https://api.ipify.org/',
	];

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Checks if we are in development mode.
	 *
	 * @todo this needs to be migrate somewhere else, where it can be accessed by everyone.
	 * @since 2.0.0
	 * @return boolean
	 */
	public static function is_development_mode() {

		$site_url = site_url();

		$is_development_mode = preg_match('#(localhost|staging.*\.|\.local|\.test)#', $site_url);

		/**
		 * Allow plugin developers to add additional tests
		 * for development mode.
		 *
		 * @since 2.0.0
		 *
		 * @param bool   $is_development_mode The current development status.
		 * @param string $site_url The site URL.
		 * @return bool
		 */
		return apply_filters('wu_is_development_mode', $is_development_mode, $site_url);
	}

	/**
	 * Gets the local IP address of the network.
	 *
	 * Sometimes, this will be the same address as the public one, but we need different methods.
	 *
	 * @since 2.0.0
	 * @return string|bool
	 */
	public static function get_local_network_ip() {

		return sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'] ?? false));
	}

	/**
	 * Gets the public IP address of the network using an external HTTP call.
	 *
	 * The reason why this IP can't be determined locally is because proxies like
	 * Cloudflare and others will mask the real domain address.
	 * By default, we cache the values in a transient for 10 days.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public static function get_network_public_ip() {

		if ( self::is_development_mode() ) {
			$local_ip = self::get_local_network_ip();

			/**
			 * See more about this filter below, on this same method.
			 */
			return apply_filters('wu_get_network_public_ip', $local_ip, true);
		}

		$_ip_address = get_site_transient('wu_public_network_ip');

		if ( ! $_ip_address ) {
			$ip_address = false;

			foreach ( self::$providers as $provider_url ) {
				$response = wp_remote_get(
					$provider_url,
					[
						'timeout' => 5,
					]
				);

				if ( ! is_wp_error($response) ) {
					$ip_address = trim(wp_remote_retrieve_body($response));

					continue;
				}
			}

			set_site_transient('wu_public_network_ip', $ip_address, 10 * DAY_IN_SECONDS);

			$_ip_address = $ip_address;
		}

		/**
		 * Allow developers to change the public IP address of the network.
		 *
		 * This is displayed to the customer/users when new domains are mapped
		 * and need DNS records configured.
		 *
		 * This is useful in cases where a load balancer might be present and IP might vary.
		 *
		 * @see https://wpultimo.feedbear.com/boards/bug-reports/posts/network-ip-filter-required
		 *
		 * @param string $_ip_address The public IP address.
		 * @param bool $local True if this is a local network (localhost, .dev, etc.), false otherwise.
		 * @return string The new IP address.
		 */
		return apply_filters('wu_get_network_public_ip', $_ip_address, false);
	}

	/**
	 * Checks if a given domain name has a valid associated SSL certificate.
	 *
	 * @since 2.0.0
	 *
	 * @param string $domain Domain name, e.g. google.com.
	 * @return boolean
	 * @throws \Exception Caught internally to log TLS connection failures.
	 */
	public static function has_valid_ssl_certificate($domain = '') {
		$is_valid = false;

		// Ensure the domain is not empty.
		if (empty($domain)) {
			return $is_valid;
		}

		// Add 'https://' if not already present to use SSL context properly.
		$domain = str_starts_with($domain, 'https://') ? $domain : 'https://' . $domain;
		$host   = wp_parse_url($domain, PHP_URL_HOST);
		$stream = false;
		$reason = __('The server did not provide a readable SSL certificate.', 'ultimate-multisite');

		try {
			// Create SSL context to fetch the certificate.
			$context = stream_context_create(
				[
					'ssl' => [
						'capture_peer_cert' => true,
					],
				]
			);

			// TLS failures often have an empty $errstr; capture the warning as well.
			$warnings = [];
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Capture TLS diagnostics, restoring the previous handler in finally.
			set_error_handler(
				static function ($severity, $message) use (&$warnings) {
					$warnings[] = $message;
					return true;
				},
				E_WARNING
			);

			try {
				$stream = stream_socket_client(
					'ssl://' . $host . ':443',
					$errno,
					$errstr,
					10,
					STREAM_CLIENT_CONNECT,
					$context
				);
			} finally {
				restore_error_handler();
			}

			// If stream could not be established, SSL is invalid.
			if ( ! $stream) {
				$details = array_filter(array_merge([$errstr], $warnings));
				throw new \Exception($details ? implode('; ', array_unique($details)) : __('The TLS connection or certificate verification failed without further details.', 'ultimate-multisite'));
			}

			// Retrieve the certificate and parse its details.
			$options = stream_context_get_options($context);

			if (isset($options['ssl']['peer_certificate'])) {
				$cert = openssl_x509_parse($options['ssl']['peer_certificate']);

				if ($cert) {
					// Verify the certificate's validity period.
					$current_time = time();
					$valid_from   = $cert['validFrom_time_t'] ?? 0;
					$valid_to     = $cert['validTo_time_t'] ?? 0;

					// Check if the certificate is currently valid.
					if ($current_time >= $valid_from && $current_time <= $valid_to) {
						$reason = __('The certificate does not match the requested domain.', 'ultimate-multisite');

						// Check that the domain matches the certificate.
						$common_name = $cert['subject']['CN'] ?? ''; // Common Name (CN)
						$alt_names   = $cert['extensions']['subjectAltName'] ?? ''; // Subject Alternative Names (SAN)

						// Parse SAN into an array if present.
						$alt_names_array   = array_filter(array_map('trim', explode(',', str_replace('DNS:', '', $alt_names))));
						$alt_names_array[] = $common_name;
						// Check if the host matches either the CN, any SAN entry, or supports a wildcard match.
						if (
							$host === $common_name ||
							in_array($host, $alt_names_array, true)
						) {
							$is_valid = true;
						} else {
							foreach ($alt_names_array as $alt_name) {
								if ( str_starts_with($alt_name, '*.') && str_ends_with($host, substr($alt_name, 1))) {
									$is_valid = true;
									break;
								}
							}
						}
					} else {
						$reason = $current_time < $valid_from ? __('The certificate is not yet valid.', 'ultimate-multisite') : __('The certificate has expired.', 'ultimate-multisite');
					}
				}
			}
		} catch (\Exception $e) {
			$reason = $e->getMessage() ?: __('The TLS connection or certificate verification failed without further details.', 'ultimate-multisite');
		} finally {
			if (is_resource($stream)) {
				fclose($stream); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}

		if ( ! $is_valid) {
			wu_log_add(
				'domain-ssl-checks',
				/* translators: 1: Domain being checked, 2: failure reason. */
				sprintf(__('Certificate Invalid for %1$s: %2$s', 'ultimate-multisite'), $host ?: $domain, $reason),
				LogLevel::ERROR
			);
		}

		return $is_valid;
	}
}
