<?php
/**
 * Tests for the domain mapping UI element.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Test class for Domain_Mapping_Element.
 */
class Domain_Mapping_Element_Test extends WP_UnitTestCase {

	/**
	 * Test DNS management scripts are registered with front-end-safe config in the source.
	 */
	public function test_register_scripts_source_registers_dns_management_script_with_frontend_config(): void {

		$source = $this->read_source_file('inc/ui/class-domain-mapping-element.php');

		$this->assertStringContainsString("'wu-dns-management'", $source);
		$this->assertStringContainsString('wp_localize_script', $source);
		$this->assertStringContainsString('wp_add_inline_script', $source);
		$this->assertStringContainsString("'ajaxurl'", $source);
		$this->assertStringContainsString("admin_url('admin-ajax.php')", $source);
		$this->assertStringContainsString('wubox:load', $source);
		$this->assertStringContainsString('wubox-load', $source);
		$this->assertStringContainsString("wp_enqueue_script('wu-dns-management')", $source);
	}

	/**
	 * Test the script hooks into the wubox event emitted after AJAX modal content loads.
	 */
	public function test_dns_management_script_listens_for_wubox_load_event(): void {

		$source = $this->read_source_file('assets/js/dns-management.js');

		$this->assertStringContainsString("$(document.body).on('wubox:load'", $source);
		$this->assertStringContainsString('getAjaxUrl()', $source);
	}

	/**
	 * Read a local source file from the plugin under test.
	 *
	 * @param string $relative_path Relative source path.
	 * @return string
	 */
	private function read_source_file(string $relative_path): string {

		$source = file_get_contents(WP_ULTIMO_PLUGIN_DIR . '/' . $relative_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source file fixture.

		$this->assertIsString($source);

		return $source;
	}
}
