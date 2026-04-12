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
	 * Uses the Elementor API when available, otherwise clears the CSS cache
	 * via direct database operations. This fallback is important because
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

		switch_to_blog($site['site_id']);

		// Try the Elementor API if available.
		if (class_exists('\Elementor\Plugin') && ! empty(\Elementor\Plugin::$instance->files_manager)) {
			\Elementor\Plugin::$instance->files_manager->clear_cache(); // phpcs:ignore
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

		// Clear the global CSS option so Elementor rebuilds it.
		delete_option('_elementor_global_css');

		// Reset the CSS print timestamp to force full regeneration.
		delete_option('elementor_css_print_method');

		restore_current_blog();
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
