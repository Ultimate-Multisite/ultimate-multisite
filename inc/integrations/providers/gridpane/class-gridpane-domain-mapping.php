<?php
/**
 * GridPane Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\GridPane;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * GridPane domain mapping capability module.
 *
 * @since 2.5.0
 */
class GridPane_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

	/**
	 * Action Scheduler hook used for deferred domain additions.
	 *
	 * @since 2.5.1
	 * @var string
	 */
	private const RETRY_ADD_HOOK = 'wu_gridpane_retry_add_domain';

	/**
	 * Action Scheduler hook used for deferred domain removals.
	 *
	 * @since 2.5.1
	 * @var string
	 */
	private const RETRY_REMOVE_HOOK = 'wu_gridpane_retry_remove_domain';

	/**
	 * Action Scheduler group used by GridPane jobs.
	 *
	 * @since 2.5.1
	 * @var string
	 */
	private const ACTION_GROUP = 'gridpane';

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

		return [
			'will'     => [
				// translators: %s: hosting provider name.
				sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), 'GridPane'),
				// translators: %s: hosting provider name.
				sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added.', 'ultimate-multisite'), 'GridPane'),
			],
			'will_not' => [],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_hooks(): void {

		add_action('wu_add_domain', [$this, 'on_add_domain'], 10, 2);
		add_action('wu_remove_domain', [$this, 'on_remove_domain'], 10, 2);
		add_action('wu_add_subdomain', [$this, 'on_add_subdomain'], 10, 2);
		add_action('wu_remove_subdomain', [$this, 'on_remove_subdomain'], 10, 2);
		add_action(self::RETRY_ADD_HOOK, [$this, 'retry_add_domain'], 10, 2);
		add_action(self::RETRY_REMOVE_HOOK, [$this, 'retry_remove_domain'], 10, 2);
	}

	/**
	 * Gets the parent GridPane_Integration for API calls.
	 *
	 * @since 2.5.0
	 * @return GridPane_Integration
	 */
	private function get_gridpane(): GridPane_Integration {

		/** @var GridPane_Integration */
		return $this->get_integration();
	}

	/**
	 * Handles adding a domain to GridPane.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The WordPress site ID. Unused.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$this->push_domain($domain, 1);
	}

	/**
	 * Handles a scheduled domain-add retry.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain  The domain name.
	 * @param int    $attempt Current attempt number.
	 * @return void
	 */
	public function retry_add_domain(string $domain, int $attempt) {

		if (function_exists('wu_get_domain_by_domain') && ! wu_get_domain_by_domain($domain)) {
			$this->log_domain($domain, sprintf('Skipped stale add of "%s" because the mapping no longer exists.', $domain));

			return;
		}

		$this->push_domain($domain, $attempt);
	}

	/**
	 * Adds a mapped domain as a GridPane alias.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain  The domain name.
	 * @param int    $attempt Current attempt number.
	 * @return void
	 */
	private function push_domain(string $domain, int $attempt): void {

		$network_host = strtolower((string) wp_parse_url(network_site_url(), PHP_URL_HOST));

		if ('' !== $network_host && str_ends_with(strtolower($domain), '.' . $network_host)) {
			$this->log_domain($domain, sprintf('Skipped "%s" because GridPane already covers network subdomains with its wildcard configuration.', $domain));

			return;
		}

		$dns_management     = $this->get_gridpane()->get_credential('WU_GRIDPANE_DNS_MANAGEMENT') ?: 'none_none';
		$dns_integration_id = $this->get_gridpane()->get_credential('WU_GRIDPANE_DNS_INTEGRATION_ID');
		$dns_options        = ['none_none', 'cloudflare_full', 'cloudflare_challenge', 'dnsme_full', 'dnsme_challenge'];

		if ( ! in_array($dns_management, $dns_options, true)) {
			$this->log_domain($domain, sprintf('GridPane DNS management value "%s" is not supported.', $dns_management), LogLevel::ERROR);

			return;
		}

		if ('none_none' !== $dns_management && ! ctype_digit($dns_integration_id)) {
			$this->log_domain($domain, 'GridPane requires a DNS integration ID for the selected DNS management mode.', LogLevel::ERROR);

			return;
		}

		$wait = $this->reserve_write_slot();

		if ($wait > 0 && $this->schedule_retry(self::RETRY_ADD_HOOK, $domain, $attempt, $wait)) {
			$this->log_domain($domain, sprintf('Queued adding "%1$s" to GridPane in %2$d seconds to respect its write limit.', $domain, $wait));

			return;
		}

		$configuration = $this->get_gridpane()->get_site_configuration();

		if (is_wp_error($configuration)) {
			$this->handle_failure(self::RETRY_ADD_HOOK, $domain, $attempt, $configuration, 'add');

			return;
		}

		$payload = [
			'domain_url'     => $domain,
			'site_id'        => $configuration['site_id'],
			'server_id'      => $configuration['server_id'],
			'type'           => 'alias',
			'dns_management' => $dns_management,
		];

		if (ctype_digit($dns_integration_id)) {
			$payload['dns_integration_id'] = (int) $dns_integration_id;
		}

		/**
		 * Filters the payload used to add a mapped domain to GridPane.
		 *
		 * @since 2.5.1
		 *
		 * @param array  $payload GridPane domain payload.
		 * @param string $domain  Mapped domain.
		 */
		$payload = apply_filters('wu_gridpane_add_domain_payload', $payload, $domain);
		$result  = $this->get_gridpane()->send_gridpane_api_request('domain', $payload, 'POST');

		if (is_wp_error($result)) {
			$this->handle_failure(self::RETRY_ADD_HOOK, $domain, $attempt, $result, 'add');

			return;
		}

		$this->log_domain($domain, sprintf('Added alias domain "%s" to GridPane site %d.', $domain, $configuration['site_id']));
	}

	/**
	 * Handles removing a domain from GridPane.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name.
	 * @param int    $site_id The WordPress site ID. Unused.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$this->pull_domain($domain, 1);
	}

	/**
	 * Handles a scheduled domain-removal retry.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain  The domain name.
	 * @param int    $attempt Current attempt number.
	 * @return void
	 */
	public function retry_remove_domain(string $domain, int $attempt) {

		if (function_exists('wu_get_domain_by_domain') && wu_get_domain_by_domain($domain)) {
			$this->log_domain($domain, sprintf('Skipped stale removal of "%s" because the domain has been mapped again.', $domain));

			return;
		}

		$this->pull_domain($domain, $attempt);
	}

	/**
	 * Removes a mapped alias from GridPane.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain  The domain name.
	 * @param int    $attempt Current attempt number.
	 * @return void
	 */
	private function pull_domain(string $domain, int $attempt): void {

		$wait = $this->reserve_write_slot();

		if ($wait > 0 && $this->schedule_retry(self::RETRY_REMOVE_HOOK, $domain, $attempt, $wait)) {
			$this->log_domain($domain, sprintf('Queued removing "%1$s" from GridPane in %2$d seconds to respect its write limit.', $domain, $wait));

			return;
		}

		$configuration = $this->get_gridpane()->get_site_configuration();

		if (is_wp_error($configuration)) {
			$this->handle_failure(self::RETRY_REMOVE_HOOK, $domain, $attempt, $configuration, 'remove');

			return;
		}

		$domains = $this->get_gridpane()->fetch_all('domain', 'domains');

		if (is_wp_error($domains)) {
			$this->handle_failure(self::RETRY_REMOVE_HOOK, $domain, $attempt, $domains, 'remove');

			return;
		}

		foreach ($domains as $gridpane_domain) {
			$matches_domain = isset($gridpane_domain['url']) && 0 === strcasecmp((string) $gridpane_domain['url'], $domain);
			$matches_site   = isset($gridpane_domain['site_id']) && (int) $gridpane_domain['site_id'] === $configuration['site_id'];
			$is_alias       = 'alias' === ($gridpane_domain['type'] ?? '');

			if ( ! $matches_domain || ! $matches_site || ! $is_alias || empty($gridpane_domain['id'])) {
				continue;
			}

			$result = $this->get_gridpane()->send_gridpane_api_request('domain/' . (int) $gridpane_domain['id'], [], 'DELETE');

			if (is_wp_error($result)) {
				$this->handle_failure(self::RETRY_REMOVE_HOOK, $domain, $attempt, $result, 'remove');

				return;
			}

			$this->log_domain($domain, sprintf('Removed alias domain "%s" from GridPane.', $domain));

			return;
		}

		$this->log_domain($domain, sprintf('No GridPane alias matched "%s"; no removal was needed.', $domain));
	}

	/**
	 * Reserves the next GridPane domain-write slot.
	 *
	 * @since 2.5.1
	 * @return int Seconds until the reserved slot, or zero when it is available now.
	 */
	private function reserve_write_slot(): int {

		$spacing = max(1, (int) apply_filters('wu_gridpane_write_spacing', 65));
		$now     = time();
		$next    = (int) get_network_option(null, 'wu_gridpane_next_write_slot', 0);

		if ($next <= $now) {
			update_network_option(null, 'wu_gridpane_next_write_slot', $now + $spacing);

			return 0;
		}

		return $next - $now;
	}

	/**
	 * Schedules a deduplicated GridPane retry.
	 *
	 * @since 2.5.1
	 *
	 * @param string $hook    Retry hook.
	 * @param string $domain  Domain name.
	 * @param int    $attempt Attempt number.
	 * @param int    $delay   Delay in seconds.
	 * @return bool Whether the action was scheduled or already queued.
	 */
	private function schedule_retry(string $hook, string $domain, int $attempt, int $delay): bool {

		if ( ! function_exists('wu_schedule_single_action')) {
			return false;
		}

		$args = [$domain, $attempt];

		if (function_exists('wu_next_scheduled_action') && wu_next_scheduled_action($hook, $args, self::ACTION_GROUP)) {
			return true;
		}

		wu_schedule_single_action(time() + max(1, $delay), $hook, $args, self::ACTION_GROUP);

		return true;
	}

	/**
	 * Logs a failed operation and schedules retryable failures.
	 *
	 * @since 2.5.1
	 *
	 * @param string    $hook      Retry hook.
	 * @param string    $domain    Domain name.
	 * @param int       $attempt   Attempt number.
	 * @param \WP_Error $error     Operation error.
	 * @param string    $operation Human-readable operation name.
	 * @return void
	 */
	private function handle_failure(string $hook, string $domain, int $attempt, \WP_Error $error, string $operation): void {

		$delays     = [90, 300, 900, 1800];
		$error_data = $error->get_error_data();
		$status     = is_array($error_data) ? (int) ($error_data['status'] ?? 0) : 0;
		$retryable  = 0 === $status || 429 === $status || $status >= 500;

		if ($retryable && $attempt <= count($delays)) {
			$retry_after = is_array($error_data) ? min(3600, max(0, (int) ($error_data['retry_after'] ?? 0))) : 0;
			$delay       = max($delays[ $attempt - 1 ], $retry_after);

			if (429 === $status) {
				$next_slot = (int) get_network_option(null, 'wu_gridpane_next_write_slot', 0);
				$cooldown  = time() + $delay;

				update_network_option(null, 'wu_gridpane_next_write_slot', max($next_slot, $cooldown));
			}

			if ($this->schedule_retry($hook, $domain, $attempt + 1, $delay)) {
				$this->log_domain(
					$domain,
					sprintf('GridPane could not %1$s "%2$s" (attempt %3$d): %4$s Retrying in %5$d seconds.', $operation, $domain, $attempt, $error->get_error_message(), $delay),
					LogLevel::WARNING
				);

				return;
			}
		}

		$this->log_domain(
			$domain,
			sprintf('GridPane failed to %1$s "%2$s": %3$s', $operation, $domain, $error->get_error_message()),
			LogLevel::ERROR
		);
	}

	/**
	 * Writes integration and per-domain diagnostic log entries.
	 *
	 * @since 2.5.1
	 *
	 * @param string $domain Domain name.
	 * @param string $message Log message.
	 * @param string $level   PSR log level.
	 * @return void
	 */
	private function log_domain(string $domain, string $message, string $level = LogLevel::INFO): void {

		wu_log_add('integration-gridpane', $message, $level);
		wu_log_add('domain-' . $domain, '[GridPane] ' . $message, $level);
	}

	/**
	 * Handles adding a subdomain to GridPane.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {
		// GridPane handles subdomains automatically.
	}

	/**
	 * Handles removing a subdomain from GridPane.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {
		// GridPane handles subdomains automatically.
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_gridpane()->test_connection();
	}
}
