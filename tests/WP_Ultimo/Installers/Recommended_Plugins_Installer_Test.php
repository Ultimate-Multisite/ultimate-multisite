<?php
/**
 * Tests for Recommended_Plugins_Installer class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Installers;

use WP_UnitTestCase;

/**
 * Test class for Recommended_Plugins_Installer.
 */
class Recommended_Plugins_Installer_Test extends WP_UnitTestCase {

	/**
	 * Gets the recommended plugin steps with a specific locale.
	 *
	 * @param string $locale The locale to use.
	 * @return array
	 */
	private function get_steps_for_locale($locale): array {

		$locale_filter = static function () use ($locale) {
			return $locale;
		};

		add_filter('locale', $locale_filter);
		add_filter('determine_locale', $locale_filter);

		try {
			return Recommended_Plugins_Installer::get_instance()->get_steps();
		} finally {
			remove_filter('locale', $locale_filter);
			remove_filter('determine_locale', $locale_filter);
		}
	}

	public function test_language_packs_are_selected_for_non_english_locales(): void {
		$steps = $this->get_steps_for_locale('pt_BR');

		$this->assertTrue($steps['install_plugin_superdav-ai-language-packs']['checked']);
		$this->assertFalse($steps['install_plugin_superdav-ai-agent']['checked']);
	}

	public function test_language_packs_are_not_selected_for_english_locales(): void {
		$steps = $this->get_steps_for_locale('en_US');

		$this->assertFalse($steps['install_plugin_superdav-ai-language-packs']['checked']);
	}

	public function test_plugins_have_one_installation_row_with_an_activation_action(): void {
		$steps = $this->get_steps_for_locale('en_US');

		$this->assertSame(
			[
				'install_plugin_user-switching',
				'install_plugin_superdav-ai-language-packs',
				'install_plugin_superdav-ai-agent',
			],
			array_keys($steps)
		);

		foreach ($steps as $slug => $step) {
			$plugin_slug = substr($slug, strlen('install_plugin_'));

			$this->assertSame('activate_plugin_' . $plugin_slug, $step['activation']);
			$this->assertNotEmpty($step['activating']);
			$this->assertSame('Installed and activated!', $step['success']);
		}
	}

	public function test_activation_action_network_activates_a_locally_active_plugin(): void {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$plugin_slug = 'recommended-plugin-network-activation-test';
		$plugin_dir  = WP_PLUGIN_DIR . '/' . $plugin_slug;
		$plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

		$this->assertTrue(WP_Filesystem());
		$this->assertTrue(wp_mkdir_p($plugin_dir));
		$this->assertTrue($wp_filesystem->put_contents($plugin_path, "<?php\n/* Plugin Name: Recommended Plugin Network Activation Test */\n"));

		wp_clean_plugins_cache();

		try {
			$local_activation = activate_plugin($plugin_file, '', false, true);

			$this->assertNotWPError($local_activation);
			$this->assertTrue(is_plugin_active($plugin_file));
			$this->assertFalse(is_plugin_active_for_network($plugin_file));

			$result = Recommended_Plugins_Installer::get_instance()->handle(true, 'activate_plugin_' . $plugin_slug, $this);

			$this->assertTrue($result);
			$this->assertTrue(is_plugin_active_for_network($plugin_file));
		} finally {
			deactivate_plugins($plugin_file, true, true);
			deactivate_plugins($plugin_file, true, false);
			wp_clean_plugins_cache();
			$wp_filesystem->delete($plugin_dir, true);
		}
	}

	public function test_ai_plugin_descriptions_explain_their_purpose(): void {
		$steps = $this->get_steps_for_locale('en_US');

		$this->assertSame('Add AI-powered language packs for plugin and theme translates, fully localize Ultimate Multisite and all community plugins.', $steps['install_plugin_superdav-ai-language-packs']['description']);
		$this->assertSame('Use an AI Agent fully control all of WordPress. Create content, products, custom plugins and more. Like Claude code or Cursor running inside of wp-admin.', $steps['install_plugin_superdav-ai-agent']['description']);
	}
}
