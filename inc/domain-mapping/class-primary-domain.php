<?php
/**
 * Handles redirects to the primary domain of a site with mappings
 *
 * @package WP_Ultimo
 * @subpackage Domain_Mapping
 * @since 2.0.0
 */

namespace WP_Ultimo\Domain_Mapping;

use WP_Ultimo\Domain_Mapping;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles redirects to the primary domain of a site with mappings
 *
 * @since 2.0.0
 */
class Primary_Domain {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Adds the hooks
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('wu_domain_mapping_load', [$this, 'add_hooks'], -20);

		add_filter('allowed_redirect_hosts', [$this, 'allow_mapped_domain_redirect_hosts']);
	}

	/**
	 * Adds mapped domain hosts to the list of allowed redirect hosts.
	 *
	 * wp_safe_redirect() validates the redirect target host against this list.
	 * Without this filter, redirects to mapped domains (which differ from the
	 * network's own host) would be blocked and fall back to wp_admin_url().
	 *
	 * @since 2.1.0
	 * @param string[] $hosts Allowed redirect hosts.
	 * @return string[]
	 */
	public function allow_mapped_domain_redirect_hosts(array $hosts): array {

		if ( ! function_exists('wu_get_domains')) {
			return $hosts;
		}

		$domains = wu_get_domains(
			[
				'blog_id' => get_current_blog_id(),
				'active'  => 1,
			]
		);

		foreach ($domains as $domain) {
			$hosts[] = $domain->get_domain();
		}

		return $hosts;
	}

	/**
	 * Adds the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_hooks(): void {

		add_action('template_redirect', [$this, 'redirect_to_primary_domain']);

		add_action('admin_init', [$this, 'maybe_redirect_to_mapped_or_network_domain']);

		add_action('login_init', [$this, 'maybe_redirect_to_mapped_or_network_domain']);
	}

	/**
	 * Redirects the site to its primary mapped domain, if any.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function redirect_to_primary_domain(): void {

		$should_redirect = true;

		if (is_preview()) {
			$should_redirect = false;
		}

		if (is_customize_preview()) {
			$should_redirect = false;
		}

		/**
		 * Allow developers to short-circuit the redirection, preventing it
		 * from happening.
		 *
		 * @since 2.0.0
		 * @param bool $should_redirect If we should redirect or not.
		 *
		 * @return bool
		 */
		if (apply_filters('wu_should_redirect_to_primary_domain', $should_redirect) === false) {
			return;
		}

		if ( ! function_exists('wu_get_domains')) {
			return;
		}
		$current_host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));

		$domains = wu_get_domains(
			[
				'blog_id'        => get_current_blog_id(),
				'primary_domain' => 1,
				'active'         => 1,
				'domain__not_in' => [$current_host],
			]
		);

		if (empty($domains)) {
			return;
		}

		$primary_domain = $domains[0];

		if ($primary_domain->get_domain() !== $current_host && $primary_domain->is_active()) {
			$url = wu_get_current_url();

			$new_url = Domain_Mapping::get_instance()->replace_url($url, $primary_domain);

			if ($url !== $new_url ) {
				wp_safe_redirect(set_url_scheme($new_url));

				exit;
			}
		}
	}

	/**
	 * Handles redirects to mapped ot network domain for the admin panel.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function maybe_redirect_to_mapped_or_network_domain(): void {

		if ('GET' !== (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? ''))) || wp_doing_ajax()) {
			return;
		}

		/*
		 * The visitor is actively trying to logout. Let them do it!
		 */
		if (wu_request('action', 'nothing') === 'logout' || wu_request('loggedout')) {
			return;
		}

		$site = wu_get_current_site();

		$mapped_domain = $site->get_primary_mapped_domain();

		if ( ! $mapped_domain) {
			return;
		}

		$redirect_settings = wu_get_setting('force_admin_redirect', 'both');

		if ('both' === $redirect_settings) {
			return;
		}

		$current_url = wp_parse_url(wu_get_current_url());

		$mapped_url = wp_parse_url($mapped_domain->get_url());

		$current_url_to_compare = $current_url['host'];

		$mapped_url_to_compare = $mapped_url['host'];

		$redirect_url = false;

		if ('force_map' === $redirect_settings && $current_url_to_compare !== $mapped_url_to_compare) {
			$redirect_url = Domain_Mapping::get_instance()->replace_url(wu_get_current_url(), $mapped_domain);
		} elseif ('force_network' === $redirect_settings && $current_url_to_compare === $mapped_url_to_compare) {
			$redirect_url = wu_restore_original_url(wu_get_current_url(), $site->get_id());
		}

		if ($redirect_url) {
			/*
			 * Use the redirect URL directly instead of parsing and rebuilding
			 * query args with wp_parse_str() + add_query_arg(). That approach
			 * URL-decodes percent-encoded values like %2F without re-encoding
			 * them, breaking URLs that use encoded slashes in query parameters
			 * (e.g., WooCommerce analytics path=%2Fanalytics%2Foverview).
			 *
			 * replace_url() and wu_restore_original_url() already handle the
			 * full URL including the query string, so separate query arg
			 * processing is not needed.
			 */
			wp_safe_redirect($redirect_url);

			exit;
		}
	}
}
