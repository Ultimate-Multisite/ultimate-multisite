<?php
/**
 * Tests for Network_Exporter.
 *
 * @package WP_Ultimo\Tests\Site_Exporter
 */

namespace WP_Ultimo\Tests\Site_Exporter;

use WP_UnitTestCase;
use WP_Ultimo\Site_Exporter\Network_Exporter;

/**
 * Test class for Network_Exporter.
 *
 * @package WP_Ultimo\Tests\Site_Exporter
 * @since 2.5.0
 */
class Network_Exporter_Test extends WP_UnitTestCase {

	/**
	 * @var Network_Exporter
	 */
	private Network_Exporter $exporter;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->exporter = Network_Exporter::get_instance();
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf(Network_Exporter::class, $this->exporter);
	}

	/**
	 * Test singleton returns same instance on repeated calls.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(Network_Exporter::get_instance(), Network_Exporter::get_instance());
	}

	/**
	 * Test that export fails when no sites are selected.
	 */
	public function test_network_export_refuses_empty_included_blog_ids(): void {

		$result = $this->exporter->export([], 1, []);

		$this->assertWPError($result);
		$this->assertEquals('no_sites_selected', $result->get_error_code());
	}

	/**
	 * Test that export fails when main site is not in included list.
	 */
	public function test_network_export_refuses_main_site_not_in_included(): void {

		// Create a site to have at least one site in the network
		$site_id = $this->factory->blog->create();

		// Try to export with main_site_blog_id not in included_blog_ids
		$result = $this->exporter->export([$site_id], 1, []);

		$this->assertWPError($result);
		$this->assertEquals('main_site_not_included', $result->get_error_code());
	}

	/**
	 * Test sitemeta whitelist contains expected keys.
	 */
	public function test_sitemeta_whitelist_contains_expected_keys(): void {

		$reflection = new \ReflectionClass($this->exporter);
		$method     = $reflection->getMethod('get_sitemeta_whitelist');
		$method->setAccessible(true);

		$keys = $method->invoke($this->exporter);

		$this->assertContains('site_admins', $keys, 'site_admins must be in whitelist');
		$this->assertContains('registration', $keys, 'registration must be in whitelist');
		$this->assertContains('active_plugins', $keys, 'active_plugins must be in whitelist');
	}

	/**
	 * Test that network.json manifest has required fields.
	 */
	public function test_network_json_schema_has_required_fields(): void {

		// Create a site
		$site_id = $this->factory->blog->create();

		// Use reflection to test the manifest creation
		$reflection = new \ReflectionClass($this->exporter);
		$method     = $reflection->getMethod('create_network_manifest');
		$method->setAccessible(true);

		// Set up the exporter state
		$reflection_property = $reflection->getProperty('included_blog_ids');
		$reflection_property->setAccessible(true);
		$reflection_property->setValue($this->exporter, [$site_id]);

		$reflection_property = $reflection->getProperty('designated_main_site_blog_id');
		$reflection_property->setAccessible(true);
		$reflection_property->setValue($this->exporter, $site_id);

		$reflection_property = $reflection->getProperty('options');
		$reflection_property->setAccessible(true);
		$reflection_property->setValue($this->exporter, [
			'include_plugins'    => true,
			'include_themes'     => true,
			'include_uploads'    => true,
			'include_mu_plugins' => true,
		]);

		// Create a temp staging dir
		$tmp_dir     = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$rand        = wp_rand(10000, 99999);
		$staging_dir = $tmp_dir . 'wu-network-export-test-' . $rand . DIRECTORY_SEPARATOR;
		mkdir($staging_dir, 0755, true);

		$reflection_property = $reflection->getProperty('staging_dir');
		$reflection_property->setAccessible(true);
		$reflection_property->setValue($this->exporter, $staging_dir);

		// Call the method
		$method->invoke($this->exporter);

		// Check the manifest file
		$manifest_file = $staging_dir . 'network.json';
		$this->assertFileExists($manifest_file);

		$manifest = json_decode(file_get_contents($manifest_file), true);

		// Verify required fields
		$this->assertArrayHasKey('format_version', $manifest, 'format_version must be in manifest');
		$this->assertArrayHasKey('exported_at', $manifest, 'exported_at must be in manifest');
		$this->assertArrayHasKey('exported_by_plugin_version', $manifest, 'exported_by_plugin_version must be in manifest');
		$this->assertArrayHasKey('source', $manifest, 'source must be in manifest');
		$this->assertArrayHasKey('designated_main_site_blog_id', $manifest, 'designated_main_site_blog_id must be in manifest');
		$this->assertArrayHasKey('included_blog_ids', $manifest, 'included_blog_ids must be in manifest');
		$this->assertArrayHasKey('options', $manifest, 'options must be in manifest');

		// Verify source sub-fields
		$this->assertArrayHasKey('url', $manifest['source'], 'source.url must be in manifest');
		$this->assertArrayHasKey('is_subdomain_install', $manifest['source'], 'source.is_subdomain_install must be in manifest');
		$this->assertArrayHasKey('main_site_blog_id', $manifest['source'], 'source.main_site_blog_id must be in manifest');
		$this->assertArrayHasKey('db_prefix', $manifest['source'], 'source.db_prefix must be in manifest');
		$this->assertArrayHasKey('wp_version', $manifest['source'], 'source.wp_version must be in manifest');

		// Cleanup - delete staging directory directly (protected method can't be called from test)
		if (is_dir($staging_dir)) {
			$this->delete_directory($staging_dir);
		}
	}

	/**
	 * Delete directory recursively (for test cleanup).
	 *
	 * @param string $dir Directory to delete.
	 */
	private function delete_directory($dir): void {

		if (! is_dir($dir)) {
			return;
		}

		$files = array_diff(scandir($dir), ['.', '..']);
		foreach ($files as $file) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if (is_dir($path)) {
				$this->delete_directory($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}
}
