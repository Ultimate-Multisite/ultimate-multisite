<?php
/**
 * Pages Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.11
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Guess the id of the registration page.
 *
 * @since 2.1.0
 * @return int|false
 */
function wu_guess_registration_page() {

	return wu_switch_blog_and_run(
		function () {

			$saved_register_page_id = wu_get_setting('default_registration_page', 0);

			$page = get_post($saved_register_page_id);

			if ($page) {
				return $page->ID;
			}

			$maybe_register_page = get_page_by_path('register');

			if ($maybe_register_page && has_shortcode($maybe_register_page->post_content, 'wu_checkout') && 'publish' === $maybe_register_page->post_status) {
				wu_save_setting('default_registration_page', $maybe_register_page->ID);

				function_exists('flush_rewrite_rules') && flush_rewrite_rules(true);

				return $maybe_register_page->ID;
			}

			return false;
		}
	);
}

/**
 * Checks if the current post is a registration page.
 *
 * @since 2.0.0
 * @return boolean
 */
function wu_is_registration_page() {

	/** @var \WP_Post|null $post */
	global $post;

	if ( ! is_main_site()) {
		return false;
	}

	if ( ! is_a($post, '\WP_Post')) {
		return false;
	}

	return absint(wu_guess_registration_page()) === $post->ID;
}

/**
 * Checks if the current post is a update page.
 *
 * @since 2.0.21
 * @return boolean
 */
function wu_is_update_page() {

	global $post;

	if ( ! is_main_site()) {
		return false;
	}

	if ( ! is_a($post, '\WP_Post')) {
		return false;
	}

	return absint(wu_get_setting('default_update_page', 0)) === $post->ID;
}

/**
 * Checks if the current post is a new site page.
 *
 * @since 2.0.21
 * @return boolean
 */
function wu_is_new_site_page() {

	global $post;

	if ( ! is_main_site()) {
		return false;
	}

	if ( ! is_a($post, '\WP_Post')) {
		return false;
	}

	return absint(wu_get_setting('default_new_site_page', 0)) === $post->ID;
}

/**
 * Returns a request-memoized list of pages for select field options.
 *
 * @since 2.0.0
 * @param string $default_label The label for the current page option.
 * @return array
 */
function wu_get_pages_as_options($default_label) {

	static $pages_by_context = [];

	$current_page_id = get_the_ID();
	$context_key     = get_current_blog_id() . ':' . $current_page_id;

	if ( ! array_key_exists($context_key, $pages_by_context)) {
		$pages_by_context[ $context_key ] = get_pages(
			[
				'exclude' => [$current_page_id], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			]
		) ?: [];
	}

	$pages_list = [0 => $default_label];

	foreach ($pages_by_context[ $context_key ] as $page) {
		$pages_list[ $page->ID ] = $page->post_title;
	}

	return $pages_list;
}

/**
 * Checks if the current page is a login page.
 *
 * @since 2.0.11
 * @return bool
 */
function wu_is_login_page() {

	global $pagenow;

	$is_login_element_present = \WP_Ultimo\UI\Login_Form_Element::get_instance()->is_actually_loaded();

	$is_default_wp_login = 'wp-login.php' === $pagenow;

	return $is_login_element_present || $is_default_wp_login;
}
