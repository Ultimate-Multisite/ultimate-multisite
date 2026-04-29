<?php
/**
 * Elementor Compatibility Layer
 *
 * Handles Elementor Support
 *
 * @package WP_Ultimo
 * @subpackage Compat/Elementor_Compat
 * @since 2.0.0
 */

namespace WP_Ultimo\Compat;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles Elementor Support
 *
 * @since 2.0.0
 */
class Elementor_Compat {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('wu_duplicate_site', [$this, 'regenerate_css']);

		add_filter('wu_should_redirect_to_primary_domain', [$this, 'maybe_prevent_redirection']);

		add_action('elementor/widget/shortcode/skins_init', [$this, 'maybe_setup_preview']);
	}

	/**
	 * Makes sure we force Elementor to regenerate styles after site duplication.
	 *
	 * Deletes stale compiled CSS files from disk (copied verbatim from the
	 * template site's uploads), clears CSS metadata, and uses the Elementor
	 * API to regenerate when available. This fallback is important because
	 * Elementor classes are typically not loaded in the network admin
	 * context where site duplication runs.
	 *
	 * @since 1.10.10
	 * @param array $site Info about the duplicated site.
	 * @return void
	 */
	public function regenerate_css($site): void {

		if ( ! isset($site['site_id'])) {
			return;
		}

		$blog_id = (int) $site['site_id'];

		switch_to_blog($blog_id);

		// Step 1: Delete stale compiled CSS files from disk.
		// MUCD_Files copies the template's upload directory including
		// pre-compiled post-*.css and global.css files. These contain
		// hardcoded template URLs and must be deleted so Elementor
		// rebuilds them with the correct cloned-site URLs.
		self::delete_stale_css_files();

		// Step 2: Try the Elementor API if available.
		if (class_exists('\Elementor\Plugin') && ! empty(\Elementor\Plugin::$instance->files_manager)) {
			\Elementor\Plugin::$instance->files_manager->clear_cache(); // phpcs:ignore

			// Ensure external CSS mode for subsequent requests.
			update_option('elementor_css_print_method', 'external');

			restore_current_blog();

			return;
		}

		// Fallback: clear Elementor CSS cache via direct DB operations.
		// Duplication typically runs in the network admin context where
		// Elementor classes are not loaded — this ensures the compiled
		// CSS is regenerated on the first visit to the cloned site.
		global $wpdb;

		// Delete compiled CSS metadata — Elementor will regenerate on next load.
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->postmeta,
			['meta_key' => '_elementor_css'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			['%s']
		);

		// Also delete inline SVG cache metadata.
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->postmeta,
			['meta_key' => '_elementor_inline_svg'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			['%s']
		);

		// Clear the global CSS option so Elementor rebuilds it.
		delete_option('_elementor_global_css');

		// Ensure external CSS mode so Elementor generates CSS files on
		// the first frontend visit rather than falling back to inline.
		update_option('elementor_css_print_method', 'external');

		restore_current_blog();
	}

	/**
	 * Delete stale compiled Elementor CSS files from the uploads directory.
	 *
	 * When MUCD_Files copies the template's uploads, it includes pre-compiled
	 * post-*.css and global.css files in elementor/css/. These files contain
	 * hardcoded template-site URLs (in background-image, font-face, etc.)
	 * that won't match the cloned site. Deleting them forces Elementor to
	 * rebuild from the corrected postmeta on the first frontend visit.
	 *
	 * @since 2.3.3
	 * @return void
	 */
	private static function delete_stale_css_files(): void {

		$upload  = wp_upload_dir();
		$css_dir = rtrim($upload['basedir'], '/\\') . '/elementor/css';

		if ( ! is_dir($css_dir)) {
			return;
		}

		$files = glob($css_dir . '/post-*.css');

		if (is_array($files)) {
			foreach ($files as $file) {
				wp_delete_file($file);
			}
		}

		// Also remove global.css.
		$global_css = $css_dir . '/global.css';

		if (file_exists($global_css)) {
			wp_delete_file($global_css);
		}
	}

	/**
	 * Prevents redirection to primary domain when in Elementor preview mode.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $should_redirect If we should redirect or not.
	 * @return bool
	 */
	public function maybe_prevent_redirection($should_redirect) {

		return wu_request('elementor-preview', false) === false ? $should_redirect : false;
	}

	/**
	 * Maybe adds the setup preview for elements inside elementor.
	 *
	 * @since 2.0.5
	 * @return void
	 */
	public function maybe_setup_preview(): void {

		$elementor_actions = [
			'elementor',
			'elementor_ajax',
		];

		if (in_array(wu_request('action'), $elementor_actions, true)) {
			wu_element_setup_preview();
		}
	}
}
