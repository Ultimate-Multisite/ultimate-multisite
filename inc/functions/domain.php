<?php
/**
 * Domain Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Domain;

/**
 * Returns a domain.
 *
 * @since 2.0.0
 *
 * @param int $domain_id The id of the domain. This is not the user ID.
 * @return \WP_Ultimo\Models\Domain|false
 */
function wu_get_domain($domain_id) {

	return \WP_Ultimo\Models\Domain::get_by_id($domain_id);
}

/**
 * Queries domains.
 *
 * @since 2.0.0
 *
 * @param array $query Query arguments.
 * @return \WP_Ultimo\Models\Domain[]|string[]|int
 */
function wu_get_domains($query = []) {

	return \WP_Ultimo\Models\Domain::query($query);
}

/**
 * Returns a domain based on domain.
 *
 * @since 2.0.0
 *
 * @param string $domain The domain url.
 * @return \WP_Ultimo\Models\Domain|false
 */
function wu_get_domain_by_domain($domain) {

	return \WP_Ultimo\Models\Domain::get_by('domain', $domain);
}

/**
 * Creates a new domain.
 *
 * Check the wp_parse_args below to see what parameters are necessary.
 *
 * @since 2.0.0
 *
 * @param array $domain_data Domain attributes.
 * @return \WP_Error|\WP_Ultimo\Models\Domain
 */
function wu_create_domain($domain_data) {

	$domain_data = wp_parse_args(
		$domain_data,
		[
			'blog_id'        => false,
			'domain'         => false,
			'active'         => true,
			'primary_domain' => false,
			'secure'         => false,
			'stage'          => 'checking-dns',
			'date_created'   => wu_get_current_time('mysql', true),
			'date_modified'  => wu_get_current_time('mysql', true),
		]
	);

	$domain = new Domain($domain_data);

	$saved = $domain->save();

	if (is_wp_error($saved)) {
		return $saved;
	}

	/*
	 * Add the processing.
	 */
	wu_enqueue_async_action('wu_async_process_domain_stage', ['domain_id' => $domain->get_id()], 'domain');

	return $domain;
}

/**
 * Restores the original URL for a mapped URL.
 *
 * @since 2.0.0
 *
 * @param string $url URL with mapped domain.
 * @param int    $blog_id The blog ID.
 * @return string
 */
function wu_restore_original_url($url, $blog_id) {

	$site = wu_get_site($blog_id);

	if ($site) {
		$wp_site = get_site($blog_id);

		$mapped_domain_url = $site->get_active_site_url();

		$original_domain = $wp_site ? trim($wp_site->domain . $wp_site->path, '/') : trim(preg_replace('#^https?://#', '', $site->get_site_url()), '/');

		$mapped_domain = wp_parse_url($mapped_domain_url, PHP_URL_HOST);

		if ($original_domain !== $mapped_domain) {
			$url = str_replace($mapped_domain, $original_domain, $url);
		}
	}

	return $url;
}

/**
 * Adds the sso tags to a given URL.
 *
 * @since 2.0.11
 *
 * @param string $url The base url to sso-fy.
 * @return string
 */
function wu_with_sso($url) {

	$sso_url = \WP_Ultimo\SSO\SSO::with_sso($url);

	$user = wp_get_current_user();

	if ( ! $user->exists()) {
		$user = null;
	}

	$site_id = 0;
	$host    = wp_parse_url($url, PHP_URL_HOST);

	if ($host) {
		$site_id = (int) get_blog_id_from_url($host);
	}

	/**
	 * Filter the generated SSO URL.
	 *
	 * @since 2.0.0
	 *
	 * @param string        $sso_url     The SSO URL.
	 * @param \WP_User|null $user        The current user, or null when unavailable.
	 * @param int           $site_id     The target site ID.
	 * @param string        $redirect_to The redirect URL.
	 */
	return apply_filters('wu_sso_url', $sso_url, $user, $site_id, '');
}

/**
 * Compares the current domain to the main network domain.
 *
 * @since 2.0.11
 * @return bool
 */
function wu_is_same_domain() {

	global $current_blog, $current_site;

	return wp_parse_url(wu_get_current_url(), PHP_URL_HOST) === $current_blog->domain && $current_blog->domain === $current_site->domain;
}
