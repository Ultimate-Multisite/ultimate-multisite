<?php
/**
 * Command Palette Manager
 *
 * Manages WordPress Command Palette integration for Ultimate Multisite.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.1.0
 */

namespace WP_Ultimo\UI;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Command Palette Manager class.
 *
 * @since 2.1.0
 */
class Command_Palette_Manager {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Registered entity types for command palette.
	 *
	 * @since 2.1.0
	 * @var array
	 */
	protected $registered_entities = [];

	/**
	 * Initialize the singleton.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function init() {

		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts'], 100);

		add_action('init', [$this, 'add_settings'], 20);
	}

	/**
	 * Check if command palette is available.
	 * Uses feature detection instead of version checking.
	 *
	 * @since 2.1.0
	 * @return bool
	 */
	public function is_command_palette_available(): bool {

		global $wp_version;

		// WordPress 6.4+ has Command Palette support (Gutenberg editor only).
		// WordPress 6.9+ has admin-wide command palette.
		// WordPress 7.0+ adds categories, keywords, and admin bar shortcut.
		// We check for 6.4+ and let the JavaScript handle feature detection.
		// WP 7 features (categories, keywords) are progressive enhancements —
		// older WP silently ignores unknown properties in registerCommand().
		$is_available = version_compare($wp_version, '6.4', '>=');

		/**
		 * Filter whether command palette is available.
		 *
		 * @since 2.1.0
		 *
		 * @param bool $is_available Whether command palette is available.
		 */
		return apply_filters('wu_is_command_palette_available', $is_available);
	}

	/**
	 * Check if we should load command palette in current context.
	 *
	 * @since 2.1.0
	 * @return bool
	 */
	protected function should_load_command_palette(): bool {

		// Only in admin.
		if (! is_admin()) {
			return false;
		}

		// Check feature availability.
		if (! $this->is_command_palette_available()) {
			return false;
		}

		// Check user capability.
		if (! current_user_can('manage_network')) {
			return false;
		}

		return true;
	}

	/**
	 * Register an entity type for command palette search.
	 *
	 * @since 2.1.0
	 *
	 * @param string $slug   Entity slug (e.g., 'customer', 'site').
	 * @param array  $config Entity configuration.
	 * @return void
	 */
	public function register_entity_type(string $slug, array $config): void {

		$this->registered_entities[ $slug ] = $config;
	}

	/**
	 * Get all registered entity types.
	 *
	 * @since 2.1.0
	 * @return array
	 */
	public function get_registered_entities(): array {

		return $this->registered_entities;
	}

	/**
	 * Enqueue command palette scripts and styles.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function enqueue_scripts(): void {

		if (! $this->should_load_command_palette()) {
			return;
		}

		// Core dependencies: wp-commands, wp-data, wp-element, wp-i18n, wp-api-fetch.
		// Progressive enhancement deps (JS feature-detects these at runtime):
		//   wp-primitives: SVG/Path for icons (no global wp.icons exists in any WP version).
		//   wp-compose: useDebounce for search debouncing.
		// Both are available since WP 6.1 and safe to list — WP resolves them
		// from its registered scripts. On pages where they aren't loaded, the JS
		// gracefully degrades (no icons, setTimeout fallback for debounce).
		wp_enqueue_script(
			'wu-command-palette',
			wu_get_asset('command-palette.js', 'js'),
			['wp-commands', 'wp-data', 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-compose', 'wp-primitives'],
			wu_get_version(),
			true
		);

		// Pass configuration to JavaScript.
		wp_localize_script(
			'wu-command-palette',
			'wuCommandPalette',
			[
				'entities'        => $this->registered_entities,
				'restUrl'         => rest_url('ultimate-multisite/v1/command-palette/search'),
				'nonce'           => wp_create_nonce('wp_rest'),
				'networkAdminUrl' => network_admin_url(),
				'customLinks'     => $this->get_custom_links(),
			]
		);
	}

	/**
	 * Get custom links from settings.
	 *
	 * @since 2.1.0
	 * @return array
	 */
	protected function get_custom_links(): array {

		$saved_links = wu_get_setting('jumper_custom_links', '');

		if (empty($saved_links)) {
			return [];
		}

		$custom_links = [];
		$lines        = explode(PHP_EOL, (string) $saved_links);

		foreach ($lines as $line) {
			$line = trim($line);

			if (empty($line)) {
				continue;
			}

			// Format: Title : URL
			$parts = explode(':', $line, 2);

			if (count($parts) === 2) {
				$title = trim($parts[0]);
				$url   = trim($parts[1]);

				if (! empty($title) && ! empty($url)) {
					$custom_links[] = [
						'title' => $title,
						'url'   => $url,
					];
				}
			}
		}

		return $custom_links;
	}

	/**
	 * Add command palette settings.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function add_settings(): void {

		wu_register_settings_section(
			'tools',
			[
				'title' => __('Tools', 'ultimate-multisite'),
				'desc'  => __('Tools and utilities for managing your network.', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-tools',
			]
		);

		wu_register_settings_field(
			'tools',
			'command_palette_header',
			[
				'title' => __('Command Palette', 'ultimate-multisite'),
				'desc'  => __('Quick navigation and search using WordPress Command Palette (Ctrl/Cmd+K).', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'tools',
			'jumper_custom_links',
			[
				'title'       => __('Custom Links', 'ultimate-multisite'),
				'desc'        => __('Add custom links to the Command Palette. Add one per line, with the format "Title : URL".', 'ultimate-multisite'),
				'placeholder' => __('My Custom Link : https://example.com', 'ultimate-multisite'),
				'type'        => 'textarea',
				'html_attr'   => [
					'rows' => 4,
				],
			]
		);
	}
}
