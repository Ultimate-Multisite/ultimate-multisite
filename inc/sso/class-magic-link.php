<?php
/**
 * Magic Link Authentication for Custom Domains.
 *
 * Provides a fallback authentication method for browsers that don't
 * support third-party cookies. Generates secure, one-time use tokens
 * that automatically log users in when accessing custom domains.
 *
 * @package WP_Ultimo
 * @subpackage SSO
 * @since 2.0.0
 */

namespace WP_Ultimo\SSO;

defined('ABSPATH') || exit;

/**
 * Handles Magic Link authentication for custom domains.
 *
 * @since 2.0.0
 */
class Magic_Link {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Query var used for magic link tokens.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const TOKEN_QUERY_ARG = 'wu_magic_token';

	/**
	 * Transient prefix for storing tokens.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'wu_magic_link_';

	/**
	 * Token expiration time in seconds (default: 10 minutes).
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const TOKEN_EXPIRATION = 600;

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('init', array($this, 'handle_magic_link'), 5);

		add_filter('removable_query_args', array($this, 'add_removable_query_args'));

		// Hook into frontend admin my site URLs to add magic links.
		add_filter('wp_frontend_admin/my_site_url', array($this, 'maybe_convert_to_magic_link'), 15);
	}

	/**
	 * Add magic link query args to removable list.
	 *
	 * @since 2.0.0
	 * @param array $args Removable query args.
	 * @return array Modified removable query args.
	 */
	public function add_removable_query_args($args) {

		$args[] = self::TOKEN_QUERY_ARG;

		return $args;
	}

	/**
	 * Generate a magic link for a user to access a specific site.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id The user ID to authenticate.
	 * @param int    $site_id The site ID to access.
	 * @param string $redirect_to Optional. URL to redirect to after login.
	 * @return string|false The magic link URL or false on failure.
	 */
	public function generate_magic_link($user_id, $site_id, $redirect_to = '') {

		// Check if magic links are enabled.
		if ( ! $this->is_enabled() ) {
			return false;
		}

		// Verify user exists and has access to the site.
		if ( ! $this->verify_user_site_access($user_id, $site_id) ) {
			return false;
		}

		// Generate secure token.
		$token = $this->generate_token();

		// Get user agent and IP for additional security.
		$user_agent = $this->get_user_agent();
		$ip_address = $this->get_client_ip();

		// Store token data with security context.
		$token_data = array(
			'user_id'     => $user_id,
			'site_id'     => $site_id,
			'redirect_to' => $redirect_to,
			'created_at'  => time(),
			'user_agent'  => $user_agent,
			'ip_address'  => $ip_address,
		);

		$transient_key = self::TRANSIENT_PREFIX . $token;

		wu_switch_blog_and_run(
			fn() => set_transient($transient_key, $token_data, self::TOKEN_EXPIRATION)
		);

		$site = wu_get_site($site_id);

		// Build the magic link URL.
		$site_url = $site->get_active_site_url();

		$magic_link = add_query_arg(
			array(
				self::TOKEN_QUERY_ARG => $token,
			),
			$site_url
		);

		/**
		 * Filter the generated magic link URL.
		 *
		 * @since 2.0.0
		 *
		 * @param string $magic_link The magic link URL.
		 * @param int    $user_id    The user ID.
		 * @param int    $site_id    The site ID.
		 * @param string $redirect_to The redirect URL.
		 */
		return apply_filters('wu_magic_link_url', $magic_link, $user_id, $site_id, $redirect_to);
	}

	/**
	 * Generate a magic link for cross-network authentication.
	 *
	 * Unlike generate_magic_link(), this method stores the transient on the
	 * target site directly (for cross-network scenarios where the target site
	 * is on a different network) and accepts the site URL as a parameter
	 * instead of looking it up via wu_get_site().
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id     The user ID to authenticate.
	 * @param int    $site_id     The target site ID (on the other network).
	 * @param string $site_url    The target site's URL.
	 * @param string $redirect_to Optional. URL to redirect to after login.
	 * @return string|false The magic link URL or false on failure.
	 */
	public function generate_cross_network_magic_link(int $user_id, int $site_id, string $site_url, string $redirect_to = '') {

		if ( ! $this->is_enabled()) {
			return false;
		}

		$user = get_userdata($user_id);

		if ( ! $user) {
			return false;
		}

		$token      = $this->generate_token();
		$user_agent = $this->get_user_agent();
		$ip_address = $this->get_client_ip();

		$token_data = [
			'user_id'     => $user_id,
			'site_id'     => $site_id,
			'redirect_to' => $redirect_to,
			'created_at'  => time(),
			'user_agent'  => $user_agent,
			'ip_address'  => $ip_address,
		];

		$transient_key = self::TRANSIENT_PREFIX . $token;

		// Store transient on the target site's network main site
		// so the handler on that network can find it.
		wu_switch_blog_and_run(
			fn() => set_transient($transient_key, $token_data, self::TOKEN_EXPIRATION),
			$site_id
		);

		return add_query_arg(
			[self::TOKEN_QUERY_ARG => $token],
			$site_url
		);
	}

	/**
	 * Generate a cryptographically secure token.
	 *
	 * @since 2.0.0
	 * @return string The generated token.
	 */
	protected function generate_token() {

		return bin2hex(random_bytes(32));
	}

	/**
	 * Verify that a user has access to a specific site.
	 *
	 * @since 2.0.0
	 *
	 * @param int $user_id The user ID.
	 * @param int $site_id The site ID.
	 * @return bool True if user has access, false otherwise.
	 */
	protected function verify_user_site_access($user_id, $site_id) {

		$user = get_userdata($user_id);

		if ( ! $user ) {
			return false;
		}

		if (is_user_member_of_blog($user_id, $site_id)) {
			return true;
		}
		// Check if the site is the dashboard site in WP Frontend Admin which the user would not be a member of.
		if (function_exists('WPFA_Global_Dashboard_Obj') && (int) \WPFA_Global_Dashboard_Obj()->get_dashboard_site_id() === (int) $site_id) {
			return true;
		}
		return false;
	}

	/**
	 * Handle magic link token verification and login.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_magic_link(): void {

		$token = wu_request(self::TOKEN_QUERY_ARG);

		if ( empty($token) ) {
			return;
		}

		// Verify and consume the token.
		$token_data = $this->verify_and_consume_token($token);

		if ( false === $token_data ) {
			// Token is invalid, expired, or already used.
			$this->handle_invalid_token();
			return;
		}

		// Extract token data.
		$user_id     = $token_data['user_id'];
		$site_id     = $token_data['site_id'];
		$redirect_to = $token_data['redirect_to'];

		// Verify we're on the correct site.
		if ( get_current_blog_id() !== $site_id ) {
			$this->handle_invalid_token('Wrong site for this token.');
			return;
		}

		// Verify user still has access to the site.
		if ( ! $this->verify_user_site_access($user_id, $site_id) ) {
			$this->handle_invalid_token('User does not have access to this site.');
			return;
		}

		// Log the user in.
		wp_set_auth_cookie($user_id, true);

		/**
		 * Fires after a user is logged in via magic link.
		 *
		 * @since 2.0.0
		 *
		 * @param int $user_id The user ID.
		 * @param int $site_id The site ID.
		 */
		do_action('wu_magic_link_login', $user_id, $site_id);

		if ( empty($redirect_to) ) {
			return;
		}

		// Remove the token from the URL and redirect.
		$redirect_to = remove_query_arg(self::TOKEN_QUERY_ARG, $redirect_to);

		nocache_headers();

		wp_safe_redirect($redirect_to);

		exit;
	}

	/**
	 * Verify a token and mark it as used.
	 *
	 * @since 2.0.0
	 *
	 * @param string $token The token to verify.
	 * @return array|false Token data on success, false on failure.
	 */
	protected function verify_and_consume_token($token) {

		$transient_key = self::TRANSIENT_PREFIX . $token;

		$token_data = wu_switch_blog_and_run(
			fn() => get_transient($transient_key)
		);

		if ( false === $token_data ) {
			wu_log_add('magic-link', sprintf('Token not found or expired: %s', $token));
			return false;
		}

		// Verify security context (user agent and IP).
		if ( ! $this->verify_security_context($token_data) ) {
			wu_log_add('magic-link', sprintf('Security context mismatch for token: %s', $token));
			wu_switch_blog_and_run(
				fn() => delete_transient($transient_key)
			);
			return false;
		}

		// Delete the transient to ensure one-time use.
		wu_switch_blog_and_run(
			fn() => delete_transient($transient_key)
		);

		// Log successful authentication for audit trail.
		wu_log_add(
			'magic-link',
			sprintf(
				'Successful magic link login for user %d to site %d from IP %s',
				$token_data['user_id'],
				$token_data['site_id'],
				$this->get_client_ip()
			)
		);

		return $token_data;
	}

	/**
	 * Handle invalid token scenario.
	 *
	 * @since 2.0.0
	 *
	 * @param string $reason Optional. Reason for invalid token.
	 * @return void
	 */
	protected function handle_invalid_token($reason = '') {

		if ( $reason ) {
			wu_log_add('magic-link', sprintf('Invalid token: %s', $reason));
		}

		/**
		 * Fires when an invalid magic link token is encountered.
		 *
		 * @since 2.0.0
		 *
		 * @param string $reason The reason for the invalid token.
		 */
		do_action('wu_magic_link_invalid_token', $reason);
	}

	/**
	 * Check if a site has a custom domain different from the main site.
	 *
	 * @since 2.0.0
	 *
	 * @param int $site_id The site ID to check.
	 * @return bool True if site has a custom domain, false otherwise.
	 */
	public function site_needs_magic_link($site_id) {

		$site = wu_get_site($site_id);

		if ( ! $site ) {
			return false;
		}

		// Check if site has a primary mapped domain.
		$primary_domain = $site->get_primary_mapped_domain();

		if ( ! $primary_domain ) {
			return false;
		}

		// Get the main site domain.
		$main_site_domain = wp_parse_url(get_site_url(wu_get_main_site_id()), PHP_URL_HOST);

		// Get the custom domain.
		$custom_domain = $primary_domain->get_domain();

		// If not a subdomain we need a magic link
		return ! str_ends_with($custom_domain, $main_site_domain);
	}

	/**
	 * Check if magic links are enabled.
	 *
	 * @since 2.0.0
	 * @return bool True if enabled, false otherwise.
	 */
	protected function is_enabled() {

		/**
		 * Filter whether magic links are enabled.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $enabled Whether magic links are enabled.
		 */
		$enabled = apply_filters('wu_magic_links_enabled', wu_get_setting('enable_magic_links', true));

		return (bool) $enabled;
	}

	/**
	 * Verify security context (user agent and IP address).
	 *
	 * @since 2.0.0
	 *
	 * @param array $token_data Token data containing security context.
	 * @return bool True if security context matches, false otherwise.
	 */
	protected function verify_security_context($token_data) {

		// Get current user agent and IP.
		$current_user_agent = $this->get_user_agent();
		$current_ip         = $this->get_client_ip();

		// Get stored values.
		$stored_user_agent = $token_data['user_agent'] ?? '';
		$stored_ip         = $token_data['ip_address'] ?? '';

		/**
		 * Filter whether to enforce user agent verification.
		 *
		 * Set to false to allow tokens to work across different browsers/devices.
		 * This reduces security but increases usability.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $enforce Whether to enforce user agent matching.
		 */
		$enforce_user_agent = apply_filters('wu_magic_link_enforce_user_agent', true);

		/**
		 * Filter whether to enforce IP address verification.
		 *
		 * Set to false to allow tokens to work from different networks.
		 * This reduces security but increases usability (e.g., for mobile users switching networks).
		 *
		 * @since 2.0.0
		 *
		 * @param bool $enforce Whether to enforce IP address matching.
		 */
		$enforce_ip = apply_filters('wu_magic_link_enforce_ip', false);

		// Verify user agent if enforced.
		if ( $enforce_user_agent && $stored_user_agent !== $current_user_agent ) {
			wu_log_add(
				'magic-link',
				sprintf(
					'User agent mismatch. Expected: %s, Got: %s',
					$stored_user_agent,
					$current_user_agent
				)
			);
			return false;
		}

		// Verify IP address if enforced.
		if ( $enforce_ip && $stored_ip !== $current_ip ) {
			wu_log_add(
				'magic-link',
				sprintf(
					'IP address mismatch. Expected: %s, Got: %s',
					$stored_ip,
					$current_ip
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Get the current user's user agent.
	 *
	 * @since 2.0.0
	 * @return string The user agent string.
	 */
	protected function get_user_agent() {

		return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
	}

	/**
	 * Get the client's IP address.
	 *
	 * Checks for proxies and load balancers.
	 *
	 * @since 2.0.0
	 * @return string The client IP address.
	 */
	protected function get_client_ip() {

		$ip = '';

		// Check for proxied IP addresses.
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',  // Common proxy header.
			'HTTP_X_REAL_IP',        // Nginx proxy.
			'REMOTE_ADDR',           // Direct connection.
		);

		foreach ( $headers as $header ) {
			if ( ! empty($_SERVER[ $header ]) ) {
				$ip = sanitize_text_field(wp_unslash($_SERVER[ $header ]));

				// X-Forwarded-For may contain multiple IPs, take the first one.
				if ( strpos($ip, ',') !== false ) {
					$ip_list = explode(',', $ip);
					$ip      = trim($ip_list[0]);
				}

				// Validate IP address.
				if ( filter_var($ip, FILTER_VALIDATE_IP) ) {
					break;
				}
			}
		}

		return $ip;
	}

	/**
	 * Maybe convert a URL to a magic link.
	 *
	 * This method is hooked into the wp_frontend_admin/my_site_url filter
	 * to convert URLs to magic links when accessing sites with custom domains.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url     The URL to potentially convert.
	 * @return string The magic link URL or original URL.
	 */
	public function maybe_convert_to_magic_link($url) {

		// If not enabled, return original URL.
		if ( ! $this->is_enabled() ) {
			return $url;
		}

		// Get current user ID.
		$current_user_id = get_current_user_id();

		if ( ! $current_user_id ) {
			return $url;
		}

		// Try to extract site ID from URL if not provided.
		$site_id = $this->extract_site_id_from_url($url);

		if ( ! $site_id ) {
			return $url;
		}

		// Check if this site needs a magic link.
		if ( ! $this->site_needs_magic_link($site_id) ) {
			return $url;
		}

		// Generate magic link with the original URL as redirect target.
		$magic_link = $this->generate_magic_link($current_user_id, $site_id, $url);

		return $magic_link ?: $url;
	}

	/**
	 * Extract site ID from a URL.
	 *
	 * Attempts to determine which site a URL belongs to by parsing the domain.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url The URL to parse.
	 * @return int|null The site ID or null if not found.
	 */
	protected function extract_site_id_from_url($url) {

		$parsed_url = wp_parse_url($url);

		if ( ! isset($parsed_url['host']) ) {
			return null;
		}

		$host = $parsed_url['host'];

		// Try to find a domain mapping for this host.
		$domain = wu_get_domain_by_domain($host);

		if ( $domain ) {
			return $domain->get_blog_id();
		}

		// Try to get site by domain (for subdomain/subdirectory installs).
		$site = get_site_by_path($host, isset($parsed_url['path']) ? $parsed_url['path'] : '/');

		if ( $site ) {
			return $site->blog_id;
		}

		return null;
	}
}
