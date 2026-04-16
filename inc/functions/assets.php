<?php
/**
 * Asset Helpers
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.11
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Returns the URL for assets inside the assets folder.
 *
 * @since 2.0.0
 *
 * @param string $asset Asset file name with the extention.
 * @param string $assets_dir Assets sub-directory. Defaults to 'img'.
 * @param string $base_dir   Base dir. Defaults to 'assets'.
 * @return string
 */
function wu_get_asset($asset, $assets_dir = 'img', $base_dir = 'assets') {

	if ( ! defined('SCRIPT_DEBUG') || ! SCRIPT_DEBUG) {
		$asset = preg_replace('/(?<!\.min)(\.js|\.css)/', '.min$1', $asset);
	}

	return wu_url("$base_dir/$assets_dir/$asset");
}

/**
 * Checks if the current admin page belongs to Ultimate Multisite.
 *
 * Used to guard asset enqueues so that Ultimate Multisite scripts and styles are
 * only loaded on Ultimate Multisite admin pages, not on every page in the network
 * admin.
 *
 * Detection relies on the hook suffix passed to `admin_enqueue_scripts`. Core
 * Ultimate Multisite admin pages register with an ID prefixed by `wp-ultimo`,
 * which WordPress embeds in the generated page hook (e.g.,
 * `toplevel_page_wp-ultimo`, `wp-ultimo_page_wp-ultimo-settings`). Addon pages
 * typically register with an ID prefixed by `wu-` (e.g., `wu-networks`,
 * `wu-sites-by-user`), and may appear either as top-level pages
 * (`toplevel_page_wu-foo`) or as submenus of other menus (`*_page_wu-foo`).
 *
 * Recognized patterns:
 * - Hook suffix contains `wp-ultimo` — core plugin pages and submenus.
 * - Hook suffix contains `_page_wu-` — any page with a slug starting `wu-`
 *   (covers `toplevel_page_wu-*` and `{parent}_page_wu-*`).
 *
 * The result is passed through the `wu_is_wu_page` filter so addons can
 * explicitly register their pages when they use a non-standard slug.
 *
 * @since 2.4.2
 * @since 2.6.3 Recognize pages with `wu-` slug prefix and add `wu_is_wu_page` filter.
 *
 * @param string $hook_suffix The hook suffix passed to `admin_enqueue_scripts`.
 * @return bool True if the current page is an Ultimate Multisite admin page.
 */
function wu_is_wu_page(string $hook_suffix = ''): bool {

	if ('' === $hook_suffix) {
		$screen      = get_current_screen();
		$hook_suffix = $screen ? (string) $screen->id : '';
	}

	$is_wu_page = str_contains($hook_suffix, 'wp-ultimo')
		|| str_contains($hook_suffix, '_page_wu-');

	/**
	 * Filters whether the current admin page is considered an Ultimate Multisite page.
	 *
	 * Addons that register admin pages with non-standard slugs (i.e., slugs that
	 * do not contain `wp-ultimo` and do not start with `wu-`) should hook into
	 * this filter to ensure the default Ultimate Multisite admin styles and
	 * scripts (including wu-form modal styling) are enqueued on their pages.
	 *
	 * Example:
	 *
	 *     add_filter( 'wu_is_wu_page', function ( $is_wu_page, $hook_suffix ) {
	 *         if ( str_contains( $hook_suffix, 'my-addon-slug' ) ) {
	 *             return true;
	 *         }
	 *         return $is_wu_page;
	 *     }, 10, 2 );
	 *
	 * @since 2.6.3
	 *
	 * @param bool   $is_wu_page  Whether the page is recognized as an Ultimate Multisite page.
	 * @param string $hook_suffix The hook suffix for the current admin page.
	 */
	return (bool) apply_filters('wu_is_wu_page', $is_wu_page, $hook_suffix);
}
