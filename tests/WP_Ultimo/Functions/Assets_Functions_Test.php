<?php
/**
 * Tests for asset helper functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for asset helper functions.
 */
class Assets_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_asset returns URL string.
	 */
	public function test_wu_get_asset_returns_url(): void {

		$result = wu_get_asset('logo.png');

		$this->assertIsString($result);
		$this->assertStringContainsString('assets/img/', $result);
	}

	/**
	 * Test wu_get_asset with custom directory.
	 */
	public function test_wu_get_asset_custom_dir(): void {

		$result = wu_get_asset('style.css', 'css');

		$this->assertIsString($result);
		$this->assertStringContainsString('assets/css/', $result);
	}

	/**
	 * Test wu_get_asset adds .min when SCRIPT_DEBUG is off.
	 */
	public function test_wu_get_asset_adds_min_suffix(): void {

		// SCRIPT_DEBUG is not defined or false in test env.
		$result = wu_get_asset('app.js', 'js');

		$this->assertStringContainsString('.min.js', $result);
	}

	/**
	 * Test wu_get_asset does not double-add .min.
	 */
	public function test_wu_get_asset_no_double_min(): void {

		$result = wu_get_asset('app.min.js', 'js');

		// Should not contain .min.min.js.
		$this->assertStringNotContainsString('.min.min.js', $result);
	}

	/**
	 * Test wu_get_asset with custom base dir.
	 */
	public function test_wu_get_asset_custom_base_dir(): void {

		$result = wu_get_asset('logo.png', 'img', 'static');

		$this->assertStringContainsString('static/img/', $result);
	}

	/**
	 * Core top-level page hook.
	 */
	public function test_wu_is_wu_page_matches_core_toplevel(): void {

		$this->assertTrue(wu_is_wu_page('toplevel_page_wp-ultimo'));
	}

	/**
	 * Core submenu page hook.
	 */
	public function test_wu_is_wu_page_matches_core_submenu(): void {

		$this->assertTrue(wu_is_wu_page('wp-ultimo_page_wp-ultimo-settings'));
	}

	/**
	 * Addon page with wu- slug prefix registered as top-level (regression for #706).
	 *
	 * When the multinetwork addon adds its `wu-networks` page at top level,
	 * the hook suffix does not contain `wp-ultimo`. It must still be recognized
	 * so wu-admin.css / wu-admin.js are enqueued, otherwise wu-form modals
	 * on that page render un-styled.
	 */
	public function test_wu_is_wu_page_matches_addon_toplevel_wu_slug(): void {

		$this->assertTrue(wu_is_wu_page('toplevel_page_wu-networks'));
	}

	/**
	 * Addon page with wu- slug prefix registered as network admin submenu.
	 */
	public function test_wu_is_wu_page_matches_addon_network_wu_slug(): void {

		$this->assertTrue(wu_is_wu_page('toplevel_page_wu-networks-network'));
	}

	/**
	 * Addon page submenu of a non-wu parent with wu- slug.
	 */
	public function test_wu_is_wu_page_matches_addon_submenu_wu_slug(): void {

		$this->assertTrue(wu_is_wu_page('settings_page_wu-custom-addon'));
	}

	/**
	 * Unrelated WordPress pages must not match.
	 */
	public function test_wu_is_wu_page_rejects_unrelated_pages(): void {

		$this->assertFalse(wu_is_wu_page('edit-post'));
		$this->assertFalse(wu_is_wu_page('options-general'));
		$this->assertFalse(wu_is_wu_page('plugins'));
		$this->assertFalse(wu_is_wu_page('toplevel_page_other-plugin'));
	}

	/**
	 * A page slug that merely starts with `wu` (no hyphen) must not match, to
	 * avoid false positives on slugs like `wunderground`.
	 */
	public function test_wu_is_wu_page_requires_hyphen_after_wu(): void {

		$this->assertFalse(wu_is_wu_page('toplevel_page_wunderground'));
	}

	/**
	 * The wu_is_wu_page filter allows addons with non-standard slugs to opt in.
	 */
	public function test_wu_is_wu_page_filter_opt_in(): void {

		$callback = function ($is_wu_page, $hook_suffix) {
			if ('toplevel_page_my-custom-addon' === $hook_suffix) {
				return true;
			}
			return $is_wu_page;
		};

		add_filter('wu_is_wu_page', $callback, 10, 2);

		$this->assertTrue(wu_is_wu_page('toplevel_page_my-custom-addon'));

		remove_filter('wu_is_wu_page', $callback, 10);
	}

	/**
	 * The wu_is_wu_page filter allows opting out of a core match.
	 */
	public function test_wu_is_wu_page_filter_opt_out(): void {

		$callback = function ($is_wu_page, $hook_suffix) {
			if ('toplevel_page_wp-ultimo' === $hook_suffix) {
				return false;
			}
			return $is_wu_page;
		};

		add_filter('wu_is_wu_page', $callback, 10, 2);

		$this->assertFalse(wu_is_wu_page('toplevel_page_wp-ultimo'));

		remove_filter('wu_is_wu_page', $callback, 10);
	}
}
