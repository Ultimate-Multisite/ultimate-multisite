<?php
/**
 * BigScoots Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers/BigScoots
 * @since 2.15.2
 */

namespace WP_Ultimo\Integrations\Providers\BigScoots;

use Psr\Log\LogLevel;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

defined('ABSPATH') || exit;

/**
 * Synchronizes multisite domains with BigScoots WPO.
 *
 * @since 2.15.2
 */
class BigScoots_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

	/**
	 * Supported features.
	 *
	 * @since 2.15.2
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
				__('Register new network sites with BigScoots WPO', 'ultimate-multisite'),
				__('Keep mapped domains synchronized with their BigScoots multisite records', 'ultimate-multisite'),
				__('Request SSL certificates after domain DNS propagation is complete', 'ultimate-multisite'),
			],
			'will_not' => [
				__('Change DNS records at BigScoots or another DNS provider', 'ultimate-multisite'),
			],
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
		add_action('wu_domain_manager_dns_propagation_finished', [$this, 'request_ssl'], 10, 1);
		add_action('wp_insert_site', [$this, 'on_wordpress_site_created'], 20, 1);
		add_action('wp_delete_site', [$this, 'on_wordpress_site_deleted'], 20, 1);
		add_action('wu_bigscoots_add_subdirectory_site', [$this, 'on_add_subdomain'], 10, 2);
		add_action('wu_bigscoots_remove_subdirectory_site', [$this, 'on_remove_subdomain'], 10, 2);
	}

	/**
	 * Changes a BigScoots subsite to use a mapped domain.
	 *
	 * @since 2.15.2
	 *
	 * @param string $domain  Domain being mapped.
	 * @param int    $site_id Site receiving the mapping.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$this->sync_active_domain($site_id, 'synchronize mapped domain');
	}

	/**
	 * Restores the original network domain after a mapping is removed.
	 *
	 * @since 2.15.2
	 *
	 * @param string $domain  Domain being removed.
	 * @param int    $site_id Site whose mapping is being removed.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {

		$this->sync_active_domain($site_id, 'synchronize domain after removing a mapping');
	}

	/**
	 * Registers a newly created network subsite with BigScoots.
	 *
	 * @since 2.15.2
	 *
	 * @param string $subdomain Full subdomain being added.
	 * @param int    $site_id   New site ID.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {

		$response = $this->get_bigscoots()->bigscoots_api_call(
			'/v1/multi-sites/sub-sites/',
			'POST',
			[
				'primary_uuid' => $this->get_primary_uuid(),
				'blog_url'     => $this->normalize_site_address($subdomain),
			]
		);

		$this->log_result($response, sprintf('register subsite %d', $site_id));
	}

	/**
	 * Removes a network subsite from BigScoots.
	 *
	 * @since 2.15.2
	 *
	 * @param string $subdomain Full subdomain being removed.
	 * @param int    $site_id   Removed site ID.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {

		$response = $this->get_bigscoots()->bigscoots_api_call(
			$this->get_subsite_endpoint($site_id),
			'DELETE'
		);

		if (is_wp_error($response) && 404 === (int) $response->get_error_data('status')) {
			wu_log_add('integration-bigscoots', sprintf('BigScoots subsite %d was already absent.', $site_id));

			return;
		}

		$this->log_result($response, sprintf('remove subsite %d', $site_id));
	}

	/**
	 * Registers subdirectory sites that do not emit the subdomain lifecycle hook.
	 *
	 * @since 2.15.2
	 *
	 * @param \WP_Site $site Created WordPress site.
	 * @return void
	 */
	public function on_wordpress_site_created($site) {

		if (is_subdomain_install() || ! $site instanceof \WP_Site) {
			return;
		}

		wu_enqueue_async_action(
			'wu_bigscoots_add_subdirectory_site',
			[
				'subdomain' => $site->domain . $site->path,
				'site_id'   => (int) $site->blog_id,
			],
			'domain'
		);
	}

	/**
	 * Removes subdirectory sites that do not emit the subdomain lifecycle hook.
	 *
	 * @since 2.15.2
	 *
	 * @param \WP_Site $site Deleted WordPress site.
	 * @return void
	 */
	public function on_wordpress_site_deleted($site) {

		if (is_subdomain_install() || ! $site instanceof \WP_Site) {
			return;
		}

		wu_enqueue_async_action(
			'wu_bigscoots_remove_subdirectory_site',
			[
				'subdomain' => $site->domain . $site->path,
				'site_id'   => (int) $site->blog_id,
			],
			'domain'
		);
	}

	/**
	 * Requests SSL after Ultimate Multisite confirms DNS propagation.
	 *
	 * @since 2.15.2
	 *
	 * @param object $domain Domain model passed by the domain manager.
	 * @return void
	 */
	public function request_ssl($domain): void {

		if ( ! is_object($domain) || ! method_exists($domain, 'get_blog_id')) {
			$this->log_error('Could not request SSL because the mapped domain did not include a site ID.');

			return;
		}

		$site_id = (int) $domain->get_blog_id();

		if ($site_id < 1) {
			$this->log_error('Could not request SSL because the mapped domain site ID was invalid.');

			return;
		}

		if ( ! $this->is_ssl_candidate($domain, $site_id)) {
			return;
		}

		if (method_exists($domain, 'get_domain') && ! $this->update_subsite_domain($site_id, (string) $domain->get_domain(), 'prepare mapped domain for SSL')) {
			return;
		}

		$response = $this->get_bigscoots()->bigscoots_api_call(
			sprintf(
				'/v1/multi-sites/sub-sites/ssl/%s/%d',
				rawurlencode($this->get_primary_uuid()),
				$site_id
			),
			'POST'
		);

		$this->log_result($response, sprintf('request SSL for subsite %d', $site_id));
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_bigscoots()->test_connection();
	}

	/**
	 * Gets the parent BigScoots integration.
	 *
	 * @since 2.15.2
	 * @return BigScoots_Integration
	 */
	private function get_bigscoots(): BigScoots_Integration {

		/** @var BigScoots_Integration */
		return $this->get_integration();
	}

	/**
	 * Gets the configured primary site UUID.
	 *
	 * @since 2.15.2
	 * @return string
	 */
	private function get_primary_uuid(): string {

		return $this->get_bigscoots()->get_credential('WU_BIGSCOOTS_PRIMARY_UUID');
	}

	/**
	 * Builds the endpoint for a specific BigScoots subsite.
	 *
	 * @since 2.15.2
	 *
	 * @param int $site_id WordPress site ID.
	 * @return string
	 */
	private function get_subsite_endpoint(int $site_id): string {

		return sprintf(
			'/v1/multi-sites/sub-sites/%s/%d',
			rawurlencode($this->get_primary_uuid()),
			$site_id
		);
	}

	/**
	 * Synchronizes a BigScoots subsite with its currently active primary domain.
	 *
	 * @since 2.15.2
	 *
	 * @param int    $site_id Site ID.
	 * @param string $action  Log context.
	 * @return void
	 */
	private function sync_active_domain(int $site_id, string $action): void {

		$domain = $this->get_active_domain($site_id);

		if ('' === $domain) {
			$this->log_error(sprintf('Could not %s because site %d was not found.', $action, $site_id));

			return;
		}

		$this->update_subsite_domain($site_id, $domain, $action);
	}

	/**
	 * Updates the remote domain assigned to a BigScoots subsite.
	 *
	 * @since 2.15.2
	 *
	 * @param int    $site_id Site ID.
	 * @param string $domain  Domain to assign.
	 * @param string $action  Log context.
	 * @return bool Whether BigScoots accepted the request.
	 */
	private function update_subsite_domain(int $site_id, string $domain, string $action): bool {

		$response = $this->get_bigscoots()->bigscoots_api_call(
			$this->get_subsite_endpoint($site_id),
			'PATCH',
			['domain' => $this->normalize_domain($domain)]
		);

		$this->log_result($response, sprintf('%s for subsite %d', $action, $site_id));

		return ! is_wp_error($response);
	}

	/**
	 * Gets the effective primary hostname for a site.
	 *
	 * Resolving this when the asynchronous action runs prevents stale secondary
	 * domain events from replacing the site's current primary mapping.
	 *
	 * @since 2.15.2
	 *
	 * @param int $site_id Site ID.
	 * @return string
	 */
	private function get_active_domain(int $site_id): string {

		$site = function_exists('wu_get_site') ? wu_get_site($site_id) : false;

		if ($site && method_exists($site, 'get_active_site_url')) {
			return $this->normalize_domain((string) $site->get_active_site_url());
		}

		$wp_site = get_site($site_id);

		return $wp_site && ! empty($wp_site->domain) ? $this->normalize_domain((string) $wp_site->domain) : '';
	}

	/**
	 * Determines whether a domain should become the remote SSL/domain candidate.
	 *
	 * New customer mappings are not marked primary until SSL succeeds. They are
	 * still eligible when the site has no other custom primary mapping. Native
	 * network subdomains do not block that automatic promotion.
	 *
	 * @since 2.15.2
	 *
	 * @param object $domain  Domain model.
	 * @param int    $site_id Site ID.
	 * @return bool
	 */
	private function is_ssl_candidate($domain, int $site_id): bool {

		if ( ! method_exists($domain, 'is_primary_domain')) {
			return true;
		}

		if ($domain->is_primary_domain()) {
			return true;
		}

		$domain_id    = method_exists($domain, 'get_id') ? (int) $domain->get_id() : 0;
		$network_host = strtolower((string) wp_parse_url(network_home_url(), PHP_URL_HOST));
		$network_host = (string) preg_replace('/:\d+$/', '', $network_host);
		$query        = [
			'blog_id'        => $site_id,
			'primary_domain' => true,
		];

		if ($domain_id > 0) {
			$query['id__not_in'] = [$domain_id];
		}

		foreach (wu_get_domains($query) as $existing) {
			$existing_host = strtolower($existing->get_domain());

			if ( ! $network_host || ! str_ends_with($existing_host, '.' . $network_host)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalizes a hostname before sending it to BigScoots.
	 *
	 * @since 2.15.2
	 *
	 * @param string $domain Domain or URL.
	 * @return string
	 */
	private function normalize_domain(string $domain): string {

		$host = wp_parse_url($domain, PHP_URL_HOST);

		if (is_string($host) && '' !== $host) {
			$domain = $host;
		}

		return strtolower(trim($domain, " \t\n\r\0\x0B./"));
	}

	/**
	 * Normalizes a site address while preserving a subdirectory path.
	 *
	 * @since 2.15.2
	 *
	 * @param string $address Site hostname or URL.
	 * @return string
	 */
	private function normalize_site_address(string $address): string {

		$url  = str_contains($address, '://') ? $address : 'https://' . ltrim($address, '/');
		$host = wp_parse_url($url, PHP_URL_HOST);
		$path = wp_parse_url($url, PHP_URL_PATH);

		if ( ! is_string($host) || '' === $host) {
			return $this->normalize_domain($address);
		}

		$path = is_string($path) ? trim($path, '/') : '';

		return strtolower($host . ('' === $path ? '' : '/' . $path));
	}

	/**
	 * Logs an API operation result without exposing response credentials.
	 *
	 * @since 2.15.2
	 *
	 * @param mixed  $response API response.
	 * @param string $action   Operation description.
	 * @return void
	 */
	private function log_result($response, string $action): void {

		if (is_wp_error($response)) {
			$this->log_error(sprintf('Could not %1$s: %2$s', $action, $response->get_error_message()));

			return;
		}

		wu_log_add('integration-bigscoots', sprintf('BigScoots successfully accepted the request to %s.', $action));
	}

	/**
	 * Logs a BigScoots integration error.
	 *
	 * @since 2.15.2
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function log_error(string $message): void {

		wu_log_add('integration-bigscoots', $message, LogLevel::ERROR);
	}
}
