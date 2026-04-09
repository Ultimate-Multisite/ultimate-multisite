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
		add_action('wu_duplicate_site', [$this, 'flush_rewrite_rules_for_new_site'], 20); // @since 2.4.1 - Flush stale rewrite rules copied from template site

		add_filter('wu_should_redirect_to_primary_domain', [$this, 'maybe_prevent_redirection']);

		add_action('elementor/widget/shortcode/skins_init', [$this, 'maybe_setup_preview']);
	}

	/**
	 * Makes sure we force elementor to regenerate the styles when necessary.
	 *
	 * @since 1.10.10
	 * @param array $site Info about the duplicated site.
	 * @return void
	 */
	public function regenerate_css($site): void {

		if ( ! class_exists('\Elementor\Plugin')) {
			return;
		}

		if ( ! isset($site['site_id'])) {
			return;
		}

		switch_to_blog($site['site_id']);

		$file_manager = \Elementor\Plugin::$instance->files_manager; // phpcs:ignore

		if ( ! empty($file_manager)) {
			$file_manager->clear_cache();
		}

		restore_current_blog();
	}

	/**
	 * Flushes rewrite rules on the newly duplicated site.
	 *
	 * When UM duplicates a template site, the rewrite_rules option is copied
	 * verbatim. If the template's rules were generated with a different CPT slug,
	 * all cloned subsites keep stale rules, causing 404s on CPT URLs.
	 *
	 * @since 2.4.1
	 * @param array $site Info about the duplicated site.
	 * @return void
	 */
	public function flush_rewrite_rules_for_new_site($site): void {

		if ( ! isset($site['site_id'])) {
			return;
		}

		switch_to_blog((int) $site['site_id']);

		flush_rewrite_rules(true);

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
