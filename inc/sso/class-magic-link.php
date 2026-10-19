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
	 * Claim expiration time in seconds.
	 *
	 * @since 2.16.2
	 * @var int
	 */
	const CLAIM_EXPIRATION = 30;

	/**
	 * Query var used for signed checkout handoff tokens.
	 *
	 * @since 2.17.4
	 * @var string
	 */
	const CHECKOUT_HANDOFF_QUERY_ARG = 'wu_checkout_handoff';

	/**
	 * Maximum lifetime for checkout handoff tokens, in seconds.
	 *
	 * @since 2.17.4
	 * @var int
	 */
	const CHECKOUT_HANDOFF_EXPIRATION = 120;

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('init', array($this, 'handle_checkout_handoff'), 1);
		add_action('init', array($this, 'handle_magic_link'), 5);
		add_action('wu_daily', array($this, 'clean_expired_checkout_handoff_nonces'));

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
		$args[] = self::CHECKOUT_HANDOFF_QUERY_ARG;

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

		$site = wu_get_site($site_id);

		if ( ! $site ) {
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
	 * Generate a magic link for a customer to pay an outstanding payment.
	 *
	 * Payment links target the network main site, where customers are not
	 * necessarily site members. Access is therefore bound to payment ownership
	 * rather than normal site membership.
	 *
	 * @since 2.16.2
	 *
	 * @param \WP_Ultimo\Models\Payment $payment    Payment to be paid.
	 * @param string                    $redirect_to Exact payment checkout URL.
	 * @return string|false The magic link URL or false on failure.
	 */
	public function generate_payment_magic_link($payment, $redirect_to) {

		if ( ! $this->is_enabled() || ! $payment instanceof \WP_Ultimo\Models\Payment || ! $payment->is_payable() ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id || ! $this->verify_payment_access($payment, $user_id, $redirect_to) ) {
			return false;
		}

		$target_host   = wp_parse_url($redirect_to, PHP_URL_HOST);
		$target_scheme = wp_parse_url($redirect_to, PHP_URL_SCHEME);
		$current_host  = wp_parse_url(home_url('/'), PHP_URL_HOST);

		if ( ! $target_host || 'https' !== strtolower((string) $target_scheme) || $target_host === $current_host ) {
			return false;
		}

		$site_id = wu_get_main_site_id();
		$token   = $this->generate_token();

		$token_data = [
			'user_id'     => $user_id,
			'site_id'     => $site_id,
			'redirect_to' => $redirect_to,
			'created_at'  => time(),
			'user_agent'  => $this->get_user_agent(),
			'ip_address'  => $this->get_client_ip(),
			'purpose'     => 'payment',
			'payment_id'  => $payment->get_id(),
		];

		$transient_key = self::TRANSIENT_PREFIX . $token;

		wu_switch_blog_and_run(
			fn() => set_transient($transient_key, $token_data, self::TOKEN_EXPIRATION)
		);

		$magic_link = add_query_arg(self::TOKEN_QUERY_ARG, $token, $redirect_to);

		return apply_filters('wu_magic_link_url', $magic_link, $user_id, $site_id, $redirect_to);
	}

	/**
	 * Generates a cache-independent signed handoff URL for a newly provisioned site.
	 *
	 * The signed token is intentionally self-contained so it can be read on a mapped
	 * domain with a host-scoped object cache. Its random ID is separately persisted in
	 * the database and atomically consumed by the target site.
	 *
	 * @since 2.17.4
	 *
	 * @param int    $user_id      User to authenticate on the target site.
	 * @param int    $site_id      Target site ID.
	 * @param string $landing_path Local path to visit after authentication.
	 * @return string|false The handoff URL or false when it cannot be created.
	 */
	public function generate_checkout_handoff_url($user_id, $site_id, $landing_path = '/wp-admin/') {

		if ( ! $this->is_enabled() ) {
			return false;
		}

		$user_id      = absint($user_id);
		$site_id      = absint($site_id);
		$landing_path = $this->sanitize_checkout_handoff_path($landing_path);

		if ( ! $user_id || ! $site_id || ! $landing_path || ! $this->verify_user_site_access($user_id, $site_id) ) {
			return false;
		}

		$site = wu_get_site($site_id);

		if ( ! $site || ! $site->get_active_site_url() ) {
			return false;
		}

		$issued_at = time();
		$expires   = $issued_at + $this->get_checkout_handoff_lifetime();
		$jti       = $this->generate_token();

		$token = $this->encode_checkout_handoff_token(
			[
				'v'          => 1,
				'sub'        => $user_id,
				'blog_id'    => $site_id,
				'iat'        => $issued_at,
				'exp'        => $expires,
				'jti'        => $jti,
				'path'       => $landing_path,
				'user_agent' => $this->get_checkout_handoff_context_hash($this->get_user_agent()),
				'ip_address' => $this->get_checkout_handoff_context_hash($this->get_client_ip()),
			]
		);

		if ( ! $token || ! $this->store_checkout_handoff_nonce($jti, $site_id, $expires) ) {
			return false;
		}

		return add_query_arg(self::CHECKOUT_HANDOFF_QUERY_ARG, $token, $site->get_active_site_url());
	}

	/**
	 * Authenticates and redirects a customer arriving through a checkout handoff.
	 *
	 * @since 2.17.4
	 * @return void
	 */
	public function handle_checkout_handoff(): void {

		$token = wu_request(self::CHECKOUT_HANDOFF_QUERY_ARG);

		if ( ! is_string($token) || '' === $token) {
			return;
		}

		$claims = $this->verify_checkout_handoff_token($token);

		if ( ! $claims || ! $this->consume_checkout_handoff_nonce($claims['jti'], $claims['blog_id']) ) {
			$this->handle_invalid_checkout_handoff();
		}

		$user_id = (int) $claims['sub'];

		if (is_user_logged_in() && get_current_user_id() !== $user_id) {
			wp_logout();
		}

		wp_set_auth_cookie($user_id, true);

		/**
		 * Fires after a user is logged in through a completed-checkout handoff.
		 *
		 * @since 2.17.4
		 *
		 * @param int $user_id The authenticated user ID.
		 * @param int $site_id The target site ID.
		 */
		do_action('wu_checkout_handoff_login', $user_id, (int) $claims['blog_id']);

		nocache_headers();
		wp_safe_redirect($claims['path']);

		exit;
	}

	/**
	 * Removes expired single-use checkout handoff records.
	 *
	 * @since 2.17.4
	 * @return void
	 */
	public function clean_expired_checkout_handoff_nonces() {

		global $wpdb;

		$table = $this->get_checkout_handoff_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table} WHERE expires_at < %s",
				current_time('mysql', true)
			)
		);
	}

	/**
	 * Returns the checkout handoff token lifetime.
	 *
	 * @since 2.17.4
	 * @return int
	 */
	protected function get_checkout_handoff_lifetime() {

		/**
		 * Filters the checkout handoff token lifetime.
		 *
		 * Values are capped at two minutes to keep a handoff link short-lived.
		 *
		 * @since 2.17.4
		 *
		 * @param int $lifetime Token lifetime in seconds.
		 */
		$lifetime = (int) apply_filters('wu_checkout_handoff_token_lifetime', self::CHECKOUT_HANDOFF_EXPIRATION);

		return min(self::CHECKOUT_HANDOFF_EXPIRATION, max(1, $lifetime));
	}

	/**
	 * Creates the signed representation of a checkout handoff payload.
	 *
	 * @since 2.17.4
	 *
	 * @param array $claims Signed token claims.
	 * @return string|false
	 */
	protected function encode_checkout_handoff_token($claims) {

		$encoded_claims = $this->base64url_encode(wp_json_encode($claims));

		if ( ! $encoded_claims) {
			return false;
		}

		$signature = hash_hmac('sha256', $encoded_claims, wp_salt('auth'));

		return $encoded_claims . '.' . $signature;
	}

	/**
	 * Verifies a signed checkout handoff token without consuming its nonce.
	 *
	 * @since 2.17.4
	 *
	 * @param string $token Signed token from the request.
	 * @return array|false Validated claims or false.
	 */
	protected function verify_checkout_handoff_token($token) {

		$parts = explode('.', $token);

		if (2 !== count($parts) || ! preg_match('/^[a-f0-9]{64}$/D', $parts[1])) {
			return false;
		}

		$expected_signature = hash_hmac('sha256', $parts[0], wp_salt('auth'));

		if ( ! hash_equals($expected_signature, $parts[1])) {
			return false;
		}

		$decoded_claims = $this->base64url_decode($parts[0]);
		$claims         = $decoded_claims ? json_decode($decoded_claims, true) : null;

		if ( ! is_array($claims) || 1 !== (int) ($claims['v'] ?? 0)) {
			return false;
		}

		$user_id      = absint($claims['sub'] ?? 0);
		$site_id      = absint($claims['blog_id'] ?? 0);
		$issued_at    = absint($claims['iat'] ?? 0);
		$expires      = absint($claims['exp'] ?? 0);
		$jti          = $claims['jti'] ?? '';
		$landing_path = $this->sanitize_checkout_handoff_path($claims['path'] ?? '');
		$now          = time();

		if ( ! $user_id || ! $site_id || ! is_string($jti) || ! preg_match('/^[a-f0-9]{64}$/D', $jti)
			|| ! $landing_path || $issued_at > $now || $expires <= $now
			|| $expires - $issued_at > self::CHECKOUT_HANDOFF_EXPIRATION
			|| get_current_blog_id() !== $site_id || ! $this->verify_user_site_access($user_id, $site_id) ) {
			return false;
		}

		if ( ! $this->verify_checkout_handoff_security_context($claims) ) {
			return false;
		}

		$claims['sub']     = $user_id;
		$claims['blog_id'] = $site_id;
		$claims['jti']     = $jti;
		$claims['path']    = $landing_path;

		return $claims;
	}

	/**
	 * Checks optional client context bound to a checkout handoff token.
	 *
	 * User-agent binding follows the existing magic-link filter. IP checks are
	 * intentionally disabled by default because a mapped domain can route through
	 * a different address family or mobile network.
	 *
	 * @since 2.17.4
	 *
	 * @param array $claims Token claims.
	 * @return bool
	 */
	protected function verify_checkout_handoff_security_context($claims) {

		$user_agent_hash = $claims['user_agent'] ?? '';
		$ip_address_hash = $claims['ip_address'] ?? '';

		if (apply_filters('wu_magic_link_enforce_user_agent', true)
			&& (! is_string($user_agent_hash) || ! hash_equals($this->get_checkout_handoff_context_hash($this->get_user_agent()), $user_agent_hash))) {
			return false;
		}

		/**
		 * Filters whether checkout handoff tokens enforce the originating IP address.
		 *
		 * @since 2.17.4
		 *
		 * @param bool $enforce Whether to enforce the IP address match.
		 */
		$enforce_ip = (bool) apply_filters('wu_checkout_handoff_enforce_ip', false);

		if ($enforce_ip && (! is_string($ip_address_hash) || ! hash_equals($this->get_checkout_handoff_context_hash($this->get_client_ip()), $ip_address_hash))) {
			return false;
		}

		return true;
	}

	/**
	 * Stores a handoff nonce without persisting the signed token itself.
	 *
	 * @since 2.17.4
	 *
	 * @param string $jti     Random token identifier.
	 * @param int    $site_id Target site ID.
	 * @param int    $expires Unix timestamp at which the token expires.
	 * @return bool
	 */
	protected function store_checkout_handoff_nonce($jti, $site_id, $expires) {

		global $wpdb;

		if ( ! $this->ensure_checkout_handoff_table()) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->get_checkout_handoff_table_name(),
			[
				'jti_hash'     => $this->get_checkout_handoff_context_hash($jti),
				'blog_id'      => absint($site_id),
				'expires_at'   => gmdate('Y-m-d H:i:s', $expires),
				'consumed_at'  => null,
				'date_created' => current_time('mysql', true),
			],
			['%s', '%d', '%s', '%s', '%s']
		);

		return 1 === $inserted;
	}

	/**
	 * Atomically marks a valid checkout handoff nonce as consumed.
	 *
	 * @since 2.17.4
	 *
	 * @param string $jti     Random token identifier.
	 * @param int    $site_id Target site ID.
	 * @return bool
	 */
	protected function consume_checkout_handoff_nonce($jti, $site_id) {

		global $wpdb;

		$table = $this->get_checkout_handoff_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table} SET consumed_at = %s WHERE jti_hash = %s AND blog_id = %d AND consumed_at IS NULL AND expires_at >= %s",
				current_time('mysql', true),
				$this->get_checkout_handoff_context_hash($jti),
				absint($site_id),
				current_time('mysql', true)
			)
		);

		return 1 === $result;
	}

	/**
	 * Returns the fully qualified SSO nonce table name.
	 *
	 * @since 2.17.4
	 * @return string
	 */
	protected function get_checkout_handoff_table_name() {

		$table = $this->get_checkout_handoff_table();

		if ($table && ! empty($table->table_name)) {
			return $table->table_name;
		}

		global $wpdb;

		return $wpdb->base_prefix . 'wu_sso_nonces';
	}

	/**
	 * Returns the registered SSO nonce table object.
	 *
	 * @since 2.17.4
	 * @return \WP_Ultimo\Database\SSO_Nonces\SSO_Nonces_Table|null
	 */
	protected function get_checkout_handoff_table() {

		$tables = function_exists('WP_Ultimo') ? WP_Ultimo()->tables : null;

		return $tables && isset($tables->sso_nonce_table) ? $tables->sso_nonce_table : null;
	}

	/**
	 * Creates the SSO nonce table on installs upgraded from an earlier version.
	 *
	 * BerlinDB only creates missing tables on `admin_init` of the main site, so
	 * a checkout on another site can need the table before any admin visit.
	 *
	 * @since 2.17.4
	 * @return bool Whether the table is available.
	 */
	protected function ensure_checkout_handoff_table() {

		$table = $this->get_checkout_handoff_table();

		// Without the table object, fall back to the conventional name; the insert reports failure.
		if ( ! $table || $table->exists()) {
			return true;
		}

		$table->install();

		global $wpdb;

		$name = $this->get_checkout_handoff_table_name();

		return $name === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($name))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Validates and normalizes a local handoff path.
	 *
	 * @since 2.17.4
	 *
	 * @param mixed $path Path requested after authentication.
	 * @return string|false
	 */
	protected function sanitize_checkout_handoff_path($path) {

		if ( ! is_string($path)) {
			return false;
		}

		$path = trim($path);

		if ('' === $path || '/' !== $path[0] || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
			return false;
		}

		$parts = wp_parse_url($path);

		if (false === $parts || array_intersect_key($parts, array_flip(['scheme', 'host', 'user', 'pass', 'port']))) {
			return false;
		}

		$path = wp_sanitize_redirect($path);

		if ('' === $path || '/' !== $path[0] || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
			return false;
		}

		return $path;
	}

	/**
	 * Creates a keyed hash for data included in a signed handoff token.
	 *
	 * @since 2.17.4
	 *
	 * @param string $value Value to hash.
	 * @return string
	 */
	protected function get_checkout_handoff_context_hash($value) {

		return hash_hmac('sha256', (string) $value, wp_salt('auth'));
	}

	/**
	 * Encodes data for use in a URL-safe token segment.
	 *
	 * @since 2.17.4
	 *
	 * @param string $value Value to encode.
	 * @return string
	 */
	protected function base64url_encode($value) {

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe encoding for a signed token segment.
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}

	/**
	 * Decodes a URL-safe token segment.
	 *
	 * @since 2.17.4
	 *
	 * @param string $value Value to decode.
	 * @return string|false
	 */
	protected function base64url_decode($value) {

		$padding = strlen($value) % 4;

		if ($padding) {
			$value .= str_repeat('=', 4 - $padding);
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- URL-safe decoding for a signed token segment.
		return base64_decode(strtr($value, '-_', '+/'), true);
	}

	/**
	 * Stops a request carrying an invalid checkout handoff token.
	 *
	 * @since 2.17.4
	 * @return void
	 */
	protected function handle_invalid_checkout_handoff() {

		wp_die(
			esc_html__('This sign-in link is invalid or has expired.', 'ultimate-multisite'),
			esc_html__('Sign-in link unavailable', 'ultimate-multisite'),
			['response' => 403]
		);
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

		if (is_super_admin($user_id) || is_user_member_of_blog($user_id, $site_id)) {
			return true;
		}
		// Check if the site is the dashboard site in WP Frontend Admin which the user would not be a member of.
		if (function_exists('WPFA_Global_Dashboard_Obj') && (int) \WPFA_Global_Dashboard_Obj()->get_dashboard_site_id() === (int) $site_id) {
			return true;
		}
		return false;
	}

	/**
	 * Verify that a user owns a payable payment and its fixed checkout URL.
	 *
	 * @since 2.16.2
	 *
	 * @param \WP_Ultimo\Models\Payment $payment    Payment being accessed.
	 * @param int                       $user_id     User ID to authenticate.
	 * @param string                    $redirect_to Payment checkout URL.
	 * @return bool True when access is allowed, false otherwise.
	 */
	protected function verify_payment_access($payment, $user_id, $redirect_to) {

		if ( ! $payment instanceof \WP_Ultimo\Models\Payment || ! $payment->is_payable() ) {
			return false;
		}

		$customer = $payment->get_customer();

		if ( ! $customer || (int) $customer->get_user_id() !== (int) $user_id ) {
			return false;
		}

		$args = [
			'payment' => $payment->get_hash(),
		];

		if ( ! $payment->get_membership_id() ) {
			$args['checkout_form'] = 'wu-pay-invoice';
		}

		$expected_url = add_query_arg($args, wu_get_registration_url());

		return $expected_url === $redirect_to;
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

		if ( 'payment' === ($token_data['purpose'] ?? '') ) {
			$payment = wu_get_payment((int) ($token_data['payment_id'] ?? 0));

			if ( ! $payment || ! $this->verify_payment_access($payment, $user_id, $redirect_to) ) {
				$this->handle_invalid_token('User does not have access to this payment.');
				return;
			}
		} elseif ( ! $this->verify_user_site_access($user_id, $site_id) ) {
			// Verify user still has access to the site.
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
		$claim_key     = $transient_key . '_claim';

		$token_data = wu_switch_blog_and_run(
			function () use ($claim_key, $transient_key) {

				$token_data = get_transient($transient_key);

				if ( false === $token_data ) {
					return false;
				}

				if ( ! add_option($claim_key, time(), '', false) ) {
					$claim_created_at = (int) get_option($claim_key, 0);

					if ( time() - $claim_created_at <= self::CLAIM_EXPIRATION ) {
						return false;
					}

					delete_option($claim_key);

					if ( ! add_option($claim_key, time(), '', false) ) {
						return false;
					}
				}

				$token_data = get_transient($transient_key);

				if ( false === $token_data ) {
					delete_option($claim_key);
				}

				return $token_data;
			}
		);

		if ( false === $token_data ) {
			wu_log_add('magic-link', sprintf('Token not found or expired: %s', $token));
			return false;
		}

		// Verify security context (user agent and IP).
		if ( ! $this->verify_security_context($token_data) ) {
			wu_log_add('magic-link', sprintf('Security context mismatch for token: %s', $token));
			wu_switch_blog_and_run(
				function () use ($claim_key, $transient_key) {

					delete_transient($transient_key);
					delete_option($claim_key);
				}
			);
			return false;
		}

		// Delete the token before releasing its atomic consumption claim.
		wu_switch_blog_and_run(
			function () use ($claim_key, $transient_key) {

				delete_transient($transient_key);
				delete_option($claim_key);
			}
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

		// Prefer the primary mapped domain, but fall back to the site's active URL.
		// Some multisite configurations store custom domains directly in wp_blogs.
		$primary_domain = $site->get_primary_mapped_domain();

		// Get the main site domain.
		$main_site_domain = wp_parse_url(get_site_url(wu_get_main_site_id()), PHP_URL_HOST);

		// Get the target domain.
		$site_domain = $primary_domain
			? $primary_domain->get_domain()
			: wp_parse_url($site->get_active_site_url(), PHP_URL_HOST);

		if ( ! $main_site_domain || ! $site_domain ) {
			return false;
		}

		$main_site_domain = strtolower(rtrim($main_site_domain, '.'));
		$site_domain      = strtolower(rtrim($site_domain, '.'));

		// Sites on the main domain or one of its subdomains share authentication cookies.
		return $site_domain !== $main_site_domain && ! str_ends_with($site_domain, '.' . $main_site_domain);
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
