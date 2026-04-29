<?php
/**
 * Ultimate Multisite Tracker
 *
 * Handles anonymous usage data collection and error reporting.
 * Follows WordPress.org guidelines for opt-in telemetry.
 *
 * @package WP_Ultimo
 * @subpackage Tracker
 * @since 2.5.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Tracker class for anonymous usage data collection.
 *
 * Background telemetry (opt-in toggle) was removed in 2.5.1 — consent is now
 * obtained per-feedback-send rather than as a global setting. The crash support
 * link feature (build_support_url / customize_fatal_error_message) remains and
 * requires no opt-in since the admin must click the link to send data.
 *
 * @since 2.5.0
 */
class Tracker implements \WP_Ultimo\Interfaces\Singleton {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * API endpoint URL for receiving tracking data.
	 *
	 * @var string
	 */
	const API_URL = 'https://ultimatemultisite.com/wp-json/wu-telemetry/v1/track';

	/**
	 * Option name for storing last send timestamp.
	 *
	 * @var string
	 */
	const LAST_SEND_OPTION = 'wu_tracker_last_send';


	/**
	 * Weekly send interval in seconds.
	 *
	 * @var int
	 */
	const SEND_INTERVAL = WEEK_IN_SECONDS;

	/**
	 * Error log levels that should be reported.
	 *
	 * @var array
	 */
	const ERROR_LOG_LEVELS = [
		\Psr\Log\LogLevel::ERROR,
		\Psr\Log\LogLevel::CRITICAL,
		\Psr\Log\LogLevel::ALERT,
		\Psr\Log\LogLevel::EMERGENCY,
	];

	/**
	 * Initialize the tracker.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function init(): void {

		// Customize fatal error message for network sites (no opt-in required —
		// the admin must click the support link to send any data).
		add_filter('wp_php_error_message', [$this, 'customize_fatal_error_message'], 10, 2);
	}

	/**
	 * Create the weekly schedule if it doesn't exist.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function create_weekly_schedule(): void {

		if (wu_next_scheduled_action('wu_weekly') === false) {
			$next_week = strtotime('next monday');

			wu_schedule_recurring_action($next_week, WEEK_IN_SECONDS, 'wu_weekly', [], 'wu_cron');
		}
	}

	/**
	 * Check if background telemetry is enabled.
	 *
	 * Background telemetry (the opt-in toggle) was removed in 2.5.1.
	 * Consent is now obtained per-feedback-send rather than as a global
	 * setting, so this method always returns false. It is kept for
	 * backwards-compatibility with add-ons that may call it directly.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function is_tracking_enabled(): bool {

		return false;
	}

	/**
	 * Send tracking data if enabled and due.
	 *
	 * Background telemetry was removed in 2.5.1 (opt-in toggle replaced by
	 * per-send consent). This method is a no-op and will always return early.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function maybe_send_tracking_data(): void {

		if ( ! $this->is_tracking_enabled()) {
			return;
		}

		$last_send = get_site_option(self::LAST_SEND_OPTION, 0);

		if (time() - $last_send < self::SEND_INTERVAL) {
			return;
		}

		$this->send_tracking_data();
	}

	/**
	 * No-op: sending initial data on settings-toggle was removed in 2.5.1.
	 *
	 * The wu_settings_update hook for this method was also removed in init().
	 * The method signature is preserved so callers are not fatally broken.
	 *
	 * @since 2.5.0
	 * @param string $setting_id The setting being updated.
	 * @param mixed  $value The new value.
	 * @return void
	 */
	public function maybe_send_initial_data(string $setting_id, $value): void {

		// No-op: background telemetry opt-in was removed in 2.5.1.
	}

	/**
	 * Gather and send tracking data.
	 *
	 * @since 2.5.0
	 * @return array|\WP_Error
	 */
	public function send_tracking_data() {

		$data = $this->get_tracking_data();

		$response = $this->send_to_api($data, 'usage');

		if ( ! is_wp_error($response)) {
			update_site_option(self::LAST_SEND_OPTION, time());
		}

		return $response;
	}

	/**
	 * Get all tracking data.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_tracking_data(): array {

		return [
			'tracker_version' => '1.0.0',
			'timestamp'       => time(),
			'site_hash'       => $this->get_site_hash(),
			'environment'     => $this->get_environment_data(),
			'plugin'          => $this->get_plugin_data(),
			'network'         => $this->get_network_data(),
			'usage'           => $this->get_usage_data(),
			'gateways'        => $this->get_gateway_data(),
		];
	}

	/**
	 * Get anonymous site hash for deduplication.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	protected function get_site_hash(): string {

		$site_url = get_site_url();
		$auth_key = defined('AUTH_KEY') ? AUTH_KEY : '';

		return hash('sha256', $site_url . $auth_key);
	}

	/**
	 * Get environment data.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	protected function get_environment_data(): array {

		global $wpdb;

		return [
			'php_version'        => PHP_VERSION,
			'wp_version'         => get_bloginfo('version'),
			'mysql_version'      => $wpdb->db_version(),
			'server_software'    => $this->get_server_software(),
			'max_execution_time' => (int) ini_get('max_execution_time'),
			'memory_limit'       => ini_get('memory_limit'),
			'is_ssl'             => is_ssl(),
			'is_multisite'       => is_multisite(),
			'locale'             => get_locale(),
			'timezone'           => wp_timezone_string(),
		];
	}

	/**
	 * Get server software (sanitized).
	 *
	 * @since 2.5.0
	 * @return string
	 */
	protected function get_server_software(): string {

		$software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown';

		// Only return server type, not version for privacy
		if (stripos($software, 'apache') !== false) {
			return 'Apache';
		} elseif (stripos($software, 'nginx') !== false) {
			return 'Nginx';
		} elseif (stripos($software, 'litespeed') !== false) {
			return 'LiteSpeed';
		} elseif (stripos($software, 'iis') !== false) {
			return 'IIS';
		}

		return 'Other';
	}

	/**
	 * Get plugin-specific data.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	protected function get_plugin_data(): array {

		$active_addons = [];

		// Get active addons
		if (function_exists('WP_Ultimo')) {
			$wu_instance = \WP_Ultimo();

			if ($wu_instance && method_exists($wu_instance, 'get_addon_repository')) {
				$addon_repository = $wu_instance->get_addon_repository();

				if ($addon_repository && method_exists($addon_repository, 'get_installed_addons')) {
					foreach ($addon_repository->get_installed_addons() as $addon) {
						$active_addons[] = $addon['slug'] ?? 'unknown';
					}
				}
			}
		}

		return [
			'version'       => wu_get_version(),
			'active_addons' => $active_addons,
		];
	}

	/**
	 * Get network configuration data.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	protected function get_network_data(): array {

		return [
			'is_subdomain'           => is_subdomain_install(),
			'is_subdirectory'        => ! is_subdomain_install(),
			'sunrise_installed'      => defined('SUNRISE') && SUNRISE,
			'domain_mapping_enabled' => (bool) wu_get_setting('enable_domain_mapping', false),
		];
	}

	/**
	 * Get aggregated usage statistics.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	protected function get_usage_data(): array {

		global $wpdb;

		$table_prefix = $wpdb->base_prefix;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Note: Direct queries without caching are intentional for telemetry counts.
		// Table prefix comes from $wpdb->base_prefix which is safe.

		$sites_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_prefix}wu_sites"
		);

		$customers_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_prefix}wu_customers"
		);

		$memberships_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_prefix}wu_memberships"
		);

		$active_memberships_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_prefix}wu_memberships WHERE status = %s",
				'active'
			)
		);

		$products_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_prefix}wu_products"
		);

		$payments_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_prefix}wu_payments"
		);

		$domains_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_prefix}wu_domain_mappings"
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'sites_count'              => $this->anonymize_count($sites_count),
			'customers_count'          => $this->anonymize_count($customers_count),
			'memberships_count'        => $this->anonymize_count($memberships_count),
			'active_memberships_count' => $this->anonymize_count($active_memberships_count),
			'products_count'           => $this->anonymize_count($products_count),
			'payments_count'           => $this->anonymize_count($payments_count),
			'domains_count'            => $this->anonymize_count($domains_count),
		];
	}

	/**
	 * Anonymize counts to ranges for privacy.
	 *
	 * @since 2.5.0
	 * @param int $count The actual count.
	 * @return string The anonymized range.
	 */
	protected function anonymize_count(int $count): string {

		if (0 === $count) {
			return '0';
		} elseif ($count <= 10) {
			return '1-10';
		} elseif ($count <= 50) {
			return '11-50';
		} elseif ($count <= 100) {
			return '51-100';
		} elseif ($count <= 500) {
			return '101-500';
		} elseif ($count <= 1000) {
			return '501-1000';
		} elseif ($count <= 5000) {
			return '1001-5000';
		}

		return '5000+';
	}

	/**
	 * Get active gateway information.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	protected function get_gateway_data(): array {

		$active_gateways = (array) wu_get_setting('active_gateways', []);

		// Only return gateway IDs, not configuration
		return [
			'active_gateways' => array_values($active_gateways),
			'gateway_count'   => count($active_gateways),
		];
	}

	/**
	 * Maybe send error data if tracking is enabled.
	 *
	 * @since 2.5.0
	 * @param string|null $handle The log handle.
	 * @param string|null $message The error message.
	 * @param string      $log_level The PSR-3 log level.
	 * @return void
	 */
	public function maybe_send_error(?string $handle, ?string $message, string $log_level = ''): void {

		if ( ! $this->is_tracking_enabled()) {
			return;
		}

		// Bail if handle or message is empty
		if (empty($handle) || empty($message)) {
			return;
		}

		// Only send error-level messages
		if ( ! in_array($log_level, self::ERROR_LOG_LEVELS, true)) {
			return;
		}

		$error_data = $this->prepare_error_data($handle, $message, $log_level);

		// Send asynchronously to avoid blocking
		$this->send_to_api($error_data, 'error', true);
	}

	/**
	 * Get human-readable error type name.
	 *
	 * @since 2.5.0
	 * @param int $type PHP error type constant.
	 * @return string
	 */
	protected function get_error_type_name(int $type): string {

		$types = [
			E_ERROR             => 'Fatal Error',
			E_PARSE             => 'Parse Error',
			E_CORE_ERROR        => 'Core Error',
			E_COMPILE_ERROR     => 'Compile Error',
			E_USER_ERROR        => 'User Error',
			E_RECOVERABLE_ERROR => 'Recoverable Error',
		];

		return $types[ $type ] ?? 'Error';
	}

	/**
	 * Customize the fatal error message for network sites.
	 *
	 * @since 2.5.0
	 * @param string $message The error message HTML.
	 * @param array  $error Error information from error_get_last().
	 * @return string
	 */
	public function customize_fatal_error_message(string $message, array $error): string {

		// Only customize for errors related to Ultimate Multisite
		$error_file = $error['file'] ?? '';

		if (strpos($error_file, 'ultimate-multisite') === false &&
			strpos($error_file, 'wp-multisite-waas') === false) {
			return $message;
		}

		$custom_message = __('There has been a critical error on this site.', 'ultimate-multisite');

		if (is_multisite()) {
			$custom_message .= ' ' . __('Please contact your network administrator for assistance.', 'ultimate-multisite');
		}

		// Get network admin email if available
		$admin_email = wu_get_setting('company_email', get_site_option('admin_email', ''));

		if ($admin_email && is_multisite()) {
			$custom_message .= ' ' . sprintf(
				/* translators: %s is the admin email address */
				__('You can reach them at %s.', 'ultimate-multisite'),
				'<a href="mailto:' . esc_attr($admin_email) . '">' . esc_html($admin_email) . '</a>'
			);
		}

		$error_details = $this->build_error_details($error);

		// Link to support for super admins, main site for regular users
		if (is_super_admin()) {
			$support_url = $this->build_support_url($error_details, $admin_email);
			$message     = $this->build_admin_error_message($custom_message, $error_details, $support_url);
		} else {
			$home_url = network_home_url('/');
			$message  = $this->build_user_error_message($custom_message, $home_url);
		}

		if ($this->is_tracking_enabled() && str_contains($error_file, 'ultimate-multisite')) {
			$error_data = $this->prepare_error_data('fatal', $error_details['full'], \Psr\Log\LogLevel::CRITICAL);

			// Send synchronously since we're about to die
			$this->send_to_api($error_data, 'error');
		}
		return $message;
	}

	/**
	 * Get normalized error context from an error array.
	 *
	 * @since 2.5.0
	 * @param array $error Error information from error_get_last().
	 * @return array Normalized error context with type, message, file, line, and environment.
	 */
	protected function get_error_context(array $error): array {

		$file = $error['file'] ?? '';

		return [
			'type'          => $this->get_error_type_name($error['type'] ?? 0),
			'message'       => $error['message'] ?? __('Unknown error', 'ultimate-multisite'),
			'file'          => $file ?: __('Unknown file', 'ultimate-multisite'),
			'line'          => $error['line'] ?? 0,
			'trace'         => $error['trace'] ?? [],
			'source_plugin' => $this->detect_plugin_from_path($file),
			'php'           => PHP_VERSION,
			'wp'            => get_bloginfo('version'),
			'plugin'        => wu_get_version(),
			'multisite'     => is_multisite() ? 'Yes' : 'No',
			'subdomain'     => is_subdomain_install() ? 'Yes' : 'No',
		];
	}

	/**
	 * Detect the plugin or theme name from a file path.
	 *
	 * @since 2.5.0
	 * @param string $file_path The file path from the error.
	 * @return string The detected plugin/theme name or 'Unknown'.
	 */
	protected function detect_plugin_from_path(string $file_path): string {

		if (empty($file_path)) {
			return __('Unknown', 'ultimate-multisite');
		}

		// Normalize path separators
		$file_path = str_replace('\\', '/', $file_path);

		// Check for plugins directory
		if (preg_match('#/plugins/([^/]+)/#', $file_path, $matches)) {
			return $this->format_plugin_name($matches[1]);
		}

		// Check for mu-plugins directory
		if (preg_match('#/mu-plugins/([^/]+)#', $file_path, $matches)) {
			$name = $matches[1];
			// Handle single file mu-plugins
			if (strpos($name, '.php') !== false) {
				$name = basename($name, '.php');
			}

			return $this->format_plugin_name($name) . ' (mu-plugin)';
		}

		// Check for themes directory
		if (preg_match('#/themes/([^/]+)/#', $file_path, $matches)) {
			return $this->format_plugin_name($matches[1]) . ' (theme)';
		}

		// Check for wp-includes or wp-admin (WordPress core)
		if (preg_match('#/wp-(includes|admin)/#', $file_path)) {
			return 'WordPress Core';
		}

		return __('Unknown', 'ultimate-multisite');
	}

	/**
	 * Format a plugin slug into a readable name.
	 *
	 * @since 2.5.0
	 * @param string $slug The plugin slug/folder name.
	 * @return string The formatted name.
	 */
	protected function format_plugin_name(string $slug): string {

		// Replace hyphens and underscores with spaces, then title case
		$name = str_replace(['-', '_'], ' ', $slug);

		return ucwords($name);
	}

	/**
	 * Build the technical error details string.
	 *
	 * @since 2.5.0
	 * @param array $error Error information from error_get_last().
	 * @return array Contains 'summary' and 'full' keys.
	 */
	protected function build_error_details(array $error): array {

		$ctx = $this->get_error_context($error);

		$summary = sprintf('%s: %s', $ctx['type'], $ctx['message']);

		$full = sprintf(
			"%s: %s\n\nSource: %s\nFile: %s\nLine: %d\n\nEnvironment:\n- PHP: %s\n- WordPress: %s\n- Ultimate Multisite: %s\n- Multisite: %s\n- Subdomain Install: %s",
			$ctx['type'],
			$ctx['message'],
			$ctx['source_plugin'],
			$ctx['file'],
			$ctx['line'],
			$ctx['php'],
			$ctx['wp'],
			$ctx['plugin'],
			$ctx['multisite'],
			$ctx['subdomain']
		);

		if ( ! empty($ctx['trace'])) {
			$full .= "\n\nBacktrace:\n" . $this->format_backtrace($ctx['trace']);
		}

		return [
			'summary'       => $summary,
			'type'          => $ctx['type'],
			'full'          => $full,
			'source_plugin' => $ctx['source_plugin'],
		];
	}

	/**
	 * Format a backtrace array into a readable string.
	 *
	 * @since 2.5.0
	 * @param array $trace The backtrace array.
	 * @return string
	 */
	protected function format_backtrace(array $trace): string {

		$lines = [];

		foreach ($trace as $index => $frame) {
			$file     = $frame['file'] ?? '[internal]';
			$line     = $frame['line'] ?? 0;
			$function = $frame['function'] ?? '[unknown]';
			$class    = $frame['class'] ?? '';
			$type     = $frame['type'] ?? '';

			$call = $class ? "{$class}{$type}{$function}()" : "{$function}()";

			$lines[] = sprintf('#%d %s:%d %s', $index, $file, $line, $call);
		}

		return implode("\n", $lines);
	}

	/**
	 * Sanitize error text for URL parameters to avoid WAF triggers.
	 *
	 * Uses Unicode lookalike characters to preserve readability while
	 * preventing Cloudflare and other WAFs from flagging the content
	 * as malicious payloads.
	 *
	 * @since 2.5.0
	 * @param string $text The error text to sanitize.
	 * @return string
	 */
	protected function sanitize_error_for_url(string $text): string {

		// Unicode lookalike replacements
		$replacements = [
			// Path separators - use division slash (U+2215) and reverse solidus operator (U+29F5)
			'/'           => "\u{2215}",  // ∕ DIVISION SLASH
			'\\'          => "\u{29F5}",  // ⧵ REVERSE SOLIDUS OPERATOR

			// File extension dots - use fullwidth full stop (U+FF0E)
			'.php'        => "\u{FF0E}php",  // ．php
			'.js'         => "\u{FF0E}js",
			'.sql'        => "\u{FF0E}sql",
			'.sh'         => "\u{FF0E}sh",
			'.exe'        => "\u{FF0E}exe",
			'.inc'        => "\u{FF0E}inc",

			// PHP tags - use fullwidth less/greater than (U+FF1C, U+FF1E)
			'<?php'       => "\u{FF1C}?php",  // ＜?php
			'<?'          => "\u{FF1C}?",
			'?>'          => "?\u{FF1E}",

			// Common dangerous function patterns - use fullwidth parentheses (U+FF08)
			'eval('       => "eval\u{FF08}",   // eval（
			'exec('       => "exec\u{FF08}",
			'system('     => "system\u{FF08}",
			'shell_exec(' => "shell_exec\u{FF08}",
			'passthru('   => "passthru\u{FF08}",
			'popen('      => "popen\u{FF08}",
			'proc_open('  => "proc_open\u{FF08}",

			// SQL injection patterns - use fullwidth semicolon (U+FF1B)
			'; DROP'      => "\u{FF1B} DROP",
			'; SELECT'    => "\u{FF1B} SELECT",
			'; INSERT'    => "\u{FF1B} INSERT",
			'; UPDATE'    => "\u{FF1B} UPDATE",
			'; DELETE'    => "\u{FF1B} DELETE",
			"' OR '"      => "\u{FF07} OR \u{FF07}",  // fullwidth apostrophe
			'" OR "'      => "\u{FF02} OR \u{FF02}",  // fullwidth quotation mark

			// XSS patterns - use fullwidth less than (U+FF1C)
			'<script'     => "\u{FF1C}script",
			'<iframe'     => "\u{FF1C}iframe",
			'<img'        => "\u{FF1C}img",

			// Path traversal already handled by / replacement, but be explicit
			'..∕'         => "\u{FF0E}\u{FF0E}\u{2215}",  // ．．∕
		];

		// Apply replacements (order matters - do multi-char patterns first)
		$text = str_replace(array_keys($replacements), array_values($replacements), $text);

		return $text;
	}

	/**
	 * Build the support URL with pre-filled error information.
	 *
	 * @since 2.5.0
	 * @param array  $error_details Error details array with 'type', 'source_plugin', and 'full' keys.
	 * @param string $admin_email The admin email address.
	 * @return string
	 */
	protected function build_support_url(array $error_details, string $admin_email): string {

		// translators: %1$s is the type of error message, %2$s is the source plugin
		$subject = sprintf(__('[%1$s] in %2$s', 'ultimate-multisite'), $error_details['type'], $error_details['source_plugin']);

		// Sanitize error details to avoid WAF triggers using Unicode lookalikes
		$safe_error = $this->sanitize_error_for_url($error_details['full']);

		return add_query_arg(
			[
				'wpf17_3' => rawurlencode($admin_email),
				'wpf17_6' => rawurlencode($subject),
				'wpf17_5' => rawurlencode(
					__('Please describe what you were doing when this error occurred:', 'ultimate-multisite') .
					"\n\n--- ---\n" .
					$safe_error
				),
			],
			'https://ultimatemultisite.com/support/'
		);
	}

	/**
	 * Build the error message HTML for super admins with technical details.
	 *
	 * @since 2.5.0
	 * @param string $custom_message The main error message.
	 * @param array  $error_details Contains 'summary', 'full', and 'source_plugin' error details.
	 * @param string $support_url The support URL with pre-filled params.
	 * @return string
	 */
	protected function build_admin_error_message(string $custom_message, array $error_details, string $support_url): string {

		$show_details_text = esc_html__('Show Technical Details', 'ultimate-multisite');
		$copy_text         = esc_html__('Copy to Clipboard', 'ultimate-multisite');
		$copied_text       = esc_html__('Copied!', 'ultimate-multisite');
		$get_support_text  = esc_html__('Get Support from Ultimate Multisite', 'ultimate-multisite');
		$source_label      = esc_html__('Source:', 'ultimate-multisite');
		$escaped_details   = esc_html($error_details['full']);
		$escaped_support   = esc_attr($support_url);
		$source_plugin     = esc_html($error_details['source_plugin'] ?? __('Unknown', 'ultimate-multisite'));

		return <<<HTML
<style>
.wu-error-container { max-width: 600px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.wu-error-source { background: #fcf0f1; border-left: 4px solid #d63638; padding: 12px 16px; margin: 1em 0; }
.wu-error-source strong { color: #d63638; }
.wu-error-actions { display: flex; gap: 10px; margin: 1.5em 0; }
.wu-error-btn { display: inline-block; padding: 10px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; cursor: pointer; }
.wu-error-btn-primary { background: #2271b1; color: #fff; border: none; }
.wu-error-btn-primary:hover { background: #135e96; color: #fff; }
.wu-error-btn-secondary { background: #f0f0f1; color: #2c3338; border: 1px solid #ccc; }
.wu-error-btn-success { background: #00a32a; color: #fff; border: 1px solid #00a32a; }
.wu-error-details { margin-top: 1em; }
.wu-error-details summary { cursor: pointer; font-weight: 500; padding: 8px 0; }
.wu-error-details pre { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 1em; font-size: 13px; white-space: pre-wrap; word-break: break-word; margin: 0.5em 0; }
</style>
<div class="wu-error-container">
	<p>{$custom_message}</p>
	<div class="wu-error-source">
		<strong>{$source_label}</strong> {$source_plugin}
	</div>
	<div class="wu-error-actions">
		<a href="{$escaped_support}" class="wu-error-btn wu-error-btn-primary" target="_blank">{$get_support_text}</a>
	</div>
	<details class="wu-error-details">
		<summary>{$show_details_text}</summary>
		<pre id="wu-error-text">{$escaped_details}</pre>
		<button type="button" id="wu-copy-btn" class="wu-error-btn wu-error-btn-secondary" data-copy="{$copy_text}" data-copied="{$copied_text}">{$copy_text}</button>
	</details>
</div>
<script>
document.getElementById('wu-copy-btn').onclick = function() {
	var btn = this;
	navigator.clipboard.writeText(document.getElementById('wu-error-text').textContent).then(function() {
		btn.textContent = btn.dataset.copied;
		btn.className = 'wu-error-btn wu-error-btn-success';
		setTimeout(function() {
			btn.textContent = btn.dataset.copy;
			btn.className = 'wu-error-btn wu-error-btn-secondary';
		}, 2000);
	});
};
</script>
HTML;
	}

	/**
	 * Build the error message HTML for regular users (non-admin).
	 *
	 * @since 2.5.0
	 * @param string $custom_message The main error message.
	 * @param string $home_url The network home URL.
	 * @return string
	 */
	protected function build_user_error_message(string $custom_message, string $home_url): string {

		$return_text = __('Return to the main site', 'ultimate-multisite');

		return sprintf(
			'<p>%s</p><p><a href="%s">%s</a></p>',
			$custom_message,
			esc_url($home_url),
			$return_text
		);
	}

	/**
	 * Prepare error data for sending.
	 *
	 * @since 2.5.0
	 * @param string $handle The log handle.
	 * @param string $message The error message.
	 * @param string $log_level The PSR-3 log level.
	 * @return array
	 */
	protected function prepare_error_data(string $handle, string $message, string $log_level = ''): array {

		return [
			'tracker_version' => '1.0.0',
			'timestamp'       => time(),
			'site_hash'       => $this->get_site_hash(),
			'type'            => 'error',
			'log_level'       => $log_level,
			'handle'          => $this->sanitize_log_handle($handle),
			'message'         => $this->sanitize_error_message($message),
			'environment'     => [
				'php_version'    => PHP_VERSION,
				'wp_version'     => get_bloginfo('version'),
				'plugin_version' => wu_get_version(),
				'is_subdomain'   => is_subdomain_install(),
			],
		];
	}

	/**
	 * Sanitize log handle for sending.
	 *
	 * @since 2.5.0
	 * @param string $handle The log handle.
	 * @return string
	 */
	protected function sanitize_log_handle(string $handle): string {

		return sanitize_key($handle);
	}

	/**
	 * Sanitize error message to remove sensitive data.
	 *
	 * @since 2.5.0
	 * @param string $message The error message.
	 * @return string
	 */
	protected function sanitize_error_message(string $message): string {

		// Remove file paths (Unix and Windows)
		$message = str_replace(ABSPATH, 'ABSPATH', $message);
		$message = str_replace(dirname(ABSPATH), '', $message);

		// Remove potential domain names
		$message = preg_replace('/https?:\/\/[^\s\'"]+/', '[url]', $message);
		$message = preg_replace('/\b[a-zA-Z0-9][a-zA-Z0-9\-]*\.(?!(?:php|js|jsx|ts|tsx|css|scss|sass|less|html|htm|json|xml|txt|md|yml|yaml|ini|log|sql|sh|py|rb|go|vue|svelte|map|lock|twig|phtml|inc|mo|po|pot)\b)[a-zA-Z]{2,}\b/', '[domain]', $message);

		// Remove potential email addresses
		$message = preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[email]', $message);

		// Remove potential IP addresses
		$message = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', '[ip]', $message);

		// Limit message length
		return substr($message, 0, 1000);
	}

	/**
	 * Send data to the API endpoint.
	 *
	 * @since 2.5.0
	 * @param array  $data The data to send.
	 * @param string $type The type of data (usage|error).
	 * @param bool   $async Whether to send asynchronously.
	 * @return array|\WP_Error
	 */
	protected function send_to_api(array $data, string $type, bool $async = false) {

		$url = add_query_arg('type', $type, self::API_URL);

		return wp_safe_remote_post(
			$url,
			[
				'method'   => 'POST',
				'blocking' => ! $async,
				'headers'  => [
					'Content-Type' => 'application/json',
					'User-Agent'   => 'UltimateMultisite/' . wu_get_version(),
				],
				'body'     => wp_json_encode($data),
			]
		);
	}
}
