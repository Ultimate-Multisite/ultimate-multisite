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

		try {
			return Recommended_Plugins_Installer::get_instance()->get_steps();
		} finally {
			remove_filter('locale', $locale_filter);
		}
	}

	public function test_language_packs_are_selected_for_non_english_locales(): void {
		$steps = $this->get_steps_for_locale('pt_BR');

		$this->assertTrue($steps['install_plugin_superdav-ai-language-packs']['checked']);
		$this->assertTrue($steps['activate_plugin_superdav-ai-language-packs']['checked']);
		$this->assertFalse($steps['install_plugin_superdav-ai-agent']['checked']);
		$this->assertFalse($steps['activate_plugin_superdav-ai-agent']['checked']);
	}

	public function test_language_packs_are_not_selected_for_english_locales(): void {
		$steps = $this->get_steps_for_locale('en_US');

		$this->assertFalse($steps['install_plugin_superdav-ai-language-packs']['checked']);
		$this->assertFalse($steps['activate_plugin_superdav-ai-language-packs']['checked']);
	}

	public function test_ai_plugin_descriptions_explain_their_purpose(): void {
		$steps = $this->get_steps_for_locale('en_US');

		$this->assertSame('Add AI-powered language packs to translate your network.', $steps['install_plugin_superdav-ai-language-packs']['description']);
		$this->assertSame('Use an AI assistant to help manage and support your network.', $steps['install_plugin_superdav-ai-agent']['description']);
	}
}
