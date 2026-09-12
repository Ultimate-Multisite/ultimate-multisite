<?php
/**
 * URL Helper Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Returns the current URL.
 *
 * @since 2.0.0
 * @return string
 */
function wu_get_current_url() {
	/*
	 * When doing ajax requests, we don't want the admin-ajax URL, but
	 * the initiator URL.
	 */
	if (wp_doing_ajax() && isset($_SERVER['HTTP_REFERER'])) {
		return sanitize_url(wp_unslash($_SERVER['HTTP_REFERER']));
	}

	return (is_ssl() ? 'https://' : 'http://') . strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']?? ''))) . sanitize_url(wp_unslash($_SERVER['REQUEST_URI']?? ''));
}

/**
 * Replaces or removes the scheme from a URL.
 *
 * @since 2.0.0
 *
 * @param string $url The URL to process.
 * @param string $new_scheme An empty string, https, or http.
 * @return string
 */
function wu_replace_scheme($url, $new_scheme = '') {

	return preg_replace('(^https?://)', $new_scheme, $url);
}

/**
 * Wrapper to the network_admin_url function for Ultimate Multisite admin urls.
 *
 * @since 2.0.0
 *
 * @param string $path Ultimate Multisite page.
 * @param array  $query URL query parameters.
 * @return string
 */
function wu_network_admin_url($path, $query = []) {

	$path = sprintf('admin.php?page=%s', $path);

	$url = network_admin_url($path);

	return add_query_arg($query, $url);
}

/**
 * Get the light ajax implementation URL.
 *
 * @since 2.0.0
 *
 * @param null|string $when The wu-when parameter to be used, defaults to plugins_loaded.
 * @param array       $query_args List of additional query args to add to the final URL.
 * @param int         $site_id The site to use as a base.
 * @param null|string $scheme URL scheme. Follows the same rules as the scheme param of get_home_url.
 * @return string
 */
function wu_ajax_url($when = null, $query_args = [], $site_id = false, $scheme = null) {

	if (empty($site_id)) {
		$site_id = get_current_blog_id();
	}

	// Use the canonical root path so subdirectory installs do not redirect POST
	// requests from `/network?wu-ajax=1` to `/network/?wu-ajax=1` and lose data.
	$base_url = get_home_url($site_id, '/', $scheme);

	if ( ! is_array($query_args)) {
		$query_args = [];
	}

	$query_args['wu-ajax'] = 1;
	$query_args['r']       = wp_create_nonce('wu-ajax-nonce');

	if ($when) {
		$query_args['wu-when'] = base64_encode($when); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	$url = add_query_arg($query_args, $base_url);

	return apply_filters('wu_ajax_url', $url, $query_args, $when, $site_id);
}


/**
 * Adds a magic link if needed.
 *
 * @param ?int    $blog_id Blog id.
 * @param string  $path    Path.
 * @param ?string $scheme  Scheme.
 *
 * @return false|string
 */
function wu_get_admin_url($blog_id = null, $path = '', $scheme = 'admin') {
	if (! $blog_id) {
		return get_admin_url($blog_id, $path, $scheme);
	}

	$current_user_id = get_current_user_id();
	$admin_url       = get_admin_url($blog_id, $path, $scheme);

	if ( ! $current_user_id ) {
		return $admin_url;
	}

	$magic_link = \WP_Ultimo\SSO\Magic_Link::get_instance();

	// Check if a magic link is needed.
	if ( ! $magic_link->site_needs_magic_link($blog_id) ) {
		return $admin_url;
	}

	$magic_link_url = $magic_link->generate_magic_link($current_user_id, $blog_id, $admin_url);

	return $magic_link_url ?: get_admin_url($blog_id, $path, $scheme);
}

/**
 * Adds a magic link if needed.
 *
 * @param ?int $blog_id Blog id.
 *
 * @return false|string
 */
function wu_get_home_url($blog_id = null) {
	$site = wu_get_site($blog_id);

	if (! $site) {
		return get_home_url($blog_id);
	}

	$home_url        = $site->get_active_site_url();
	$current_user_id = get_current_user_id();

	if ( ! $current_user_id) {
		return $home_url;
	}

	$magic_link = \WP_Ultimo\SSO\Magic_Link::get_instance();

	// Check if magic link is needed.
	if ( ! $magic_link->site_needs_magic_link($blog_id) ) {
		return $home_url;
	}

	$magic_link_url = $magic_link->generate_magic_link($current_user_id, $blog_id, $home_url);

	return $magic_link_url ?: get_home_url($blog_id);
}
