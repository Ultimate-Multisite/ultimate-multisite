<?php
/**
 * WPMU DEV Domain Mapping Capability.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\WPMUDEV;

use Psr\Log\LogLevel;
use WP_Ultimo\Database\Domains\Domain_Stage;
use WP_Ultimo\Integrations\Base_Capability_Module;
use WP_Ultimo\Integrations\Capabilities\Domain_Mapping_Capability;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * WPMU DEV domain mapping capability module.
 *
 * @since 2.5.0
 */
class WPMUDEV_Domain_Mapping extends Base_Capability_Module implements Domain_Mapping_Capability {

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
				sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), 'WPMU DEV'),
				// translators: %s: hosting provider name.
				sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added.', 'ultimate-multisite'), 'WPMU DEV'),
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

		add_filter('wu_async_process_domain_stage_max_tries', [$this, 'ssl_tries'], 10, 2);
	}

	/**
	 * Gets the parent WPMUDEV_Integration.
	 *
	 * @since 2.5.0
	 * @return WPMUDEV_Integration
	 */
	private function get_wpmudev(): WPMUDEV_Integration {

		/** @var WPMUDEV_Integration */
		return $this->get_integration();
	}

	/**
	 * Called when a new domain is mapped.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name being mapped.
	 * @param int    $site_id ID of the site receiving the mapping.
	 * @return void
	 */
	public function on_add_domain(string $domain, int $site_id): void {

		$hosting_site_id = $this->get_wpmudev()->get_credential('WPMUDEV_HOSTING_SITE_ID');

		$api_key = get_site_option('wpmudev_apikey');

		$domains = [$domain];

		if (! str_starts_with($domain, 'www.') && \WP_Ultimo\Managers\Domain_Manager::get_instance()->should_create_www_subdomain($domain)) {
			$domains[] = "www.$domain";
		}

		foreach ($domains as $_domain) {
			$response = wp_remote_post(
				"https://premium.wpmudev.org/api/hosting/v1/$hosting_site_id/domains",
				[
					'timeout' => 50,
					'body'    => [
						'domain'  => $_domain,
						'site_id' => $hosting_site_id,
					],
					'headers' => [
						'Authorization' => $api_key,
					],
				]
			);

			if (is_wp_error($response)) {
				wu_log_add(
					'integration-wpmudev',
					/* translators: %s: domain name. */
					sprintf(__('An error occurred while trying to add the custom domain %s to WPMU Dev hosting.', 'ultimate-multisite'), $_domain),
					LogLevel::ERROR
				);

				continue;
			}

			$body = json_decode(wp_remote_retrieve_body($response));

			if (isset($body->message)) {
				wu_log_add(
					'integration-wpmudev',
					/* translators: %1$s: domain name, %2$s: error message. */
					sprintf(__('An error occurred while trying to add the custom domain %1$s to WPMU Dev hosting: %2$s', 'ultimate-multisite'), $_domain, $body->message->message ?? $body->message),
					LogLevel::ERROR
				);
			} else {
				wu_log_add(
					'integration-wpmudev',
					/* translators: %s: domain name. */
					sprintf(__('Domain %s added to WPMU Dev hosting successfully.', 'ultimate-multisite'), $_domain)
				);
			}
		}
	}

	/**
	 * Called when a mapped domain is removed.
	 *
	 * @since 2.5.0
	 *
	 * @param string $domain  The domain name being removed.
	 * @param int    $site_id ID of the site.
	 * @return void
	 */
	public function on_remove_domain(string $domain, int $site_id): void {
		// The WPMU DEV Hosting REST API does not offer an endpoint to remove domains yet.
	}

	/**
	 * Called when a new subdomain is added.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain being added.
	 * @param int    $site_id   ID of the site.
	 * @return void
	 */
	public function on_add_subdomain(string $subdomain, int $site_id): void {
		// WPMU DEV handles subdomains automatically.
	}

	/**
	 * Called when a subdomain is removed.
	 *
	 * @since 2.5.0
	 *
	 * @param string $subdomain The subdomain being removed.
	 * @param int    $site_id   ID of the site.
	 * @return void
	 */
	public function on_remove_subdomain(string $subdomain, int $site_id): void {
		// WPMU DEV handles subdomains automatically.
	}

	/**
	 * Increases the number of tries to get the SSL certificate.
	 *
	 * WPMU DEV hosting takes a while to provision SSL certificates.
	 *
	 * @since 2.5.0
	 *
	 * @param int                      $max_tries The current max tries.
	 * @param \WP_Ultimo\Models\Domain $domain    The domain object.
	 * @return int
	 */
	public function ssl_tries(int $max_tries, $domain): int {

		if (Domain_Stage::CHECKING_SSL === $domain->get_stage()) {
			$max_tries = 10;
		}

		return $max_tries;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return $this->get_wpmudev()->test_connection();
	}
}
