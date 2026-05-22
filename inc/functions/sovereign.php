<?php
/**
 * Sovereign Mode Helper Functions
 *
 * Functions for handling sovereign-mode tenant redirects.
 *
 * @package WP_Ultimo
 * @subpackage Functions/Sovereign
 * @since 2.2.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Get the main site account URL for sovereign-mode redirects.
 *
 * Returns the account page URL on the main site, with a return_to parameter
 * pointing back to the current site's admin.
 *
 * @since 2.2.0
 * @return string The main site account URL.
 */
function wu_mt_main_site_account_url() {
	$main_site_blog_id = get_main_site_id();

	return get_admin_url(
		$main_site_blog_id,
		'admin.php?page=account&return_to=' . rawurlencode(home_url('/wp-admin/'))
	);
}
