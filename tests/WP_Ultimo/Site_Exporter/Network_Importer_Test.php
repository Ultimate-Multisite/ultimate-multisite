<?php
/**
 * Tests for Network_Importer.
 *
 * @package WP_Ultimo\Tests\Site_Exporter
 */

namespace WP_Ultimo\Tests\Site_Exporter;

use WP_UnitTestCase;
use WP_Ultimo\Site_Exporter\Network_Importer;

/**
 * Test class for Network_Importer.
 *
 * @package WP_Ultimo\Tests\Site_Exporter
 * @since 2.5.0
 */
class Network_Importer_Test extends WP_UnitTestCase {

	/**
	 * Temporary files created by tests.
	 *
	 * @var array
	 */
	private array $tmp_files = [];

	/**
	 * Tear down temporary files.
	 */
	public function tear_down(): void {

		foreach ($this->tmp_files as $file) {
			if (file_exists($file)) {
				wp_delete_file($file);
			}
		}

		parent::tear_down();
	}

	/**
	 * Test network bundle detection by root manifest.
	 */
	public function test_network_import_detects_bundle_type_by_manifest(): void {

		$zip_path = $this->create_zip(
			[
				'network.json' => wp_json_encode($this->manifest()),
			]
		);

		$this->assertSame('network', Network_Importer::detect_bundle_type($zip_path));
	}

	/**
	 * Test single-site bundle detection still works.
	 */
	public function test_network_import_detects_single_site_bundle_type(): void {

		$zip_path = $this->create_zip(
			[
				'site.json' => wp_json_encode(['url' => 'https://source.example.com']),
			]
		);

		$this->assertSame('site', Network_Importer::detect_bundle_type($zip_path));
	}

	/**
	 * Test mixed bundles are rejected clearly.
	 */
	public function test_network_import_rejects_mixed_bundle_type(): void {

		$zip_path = $this->create_zip(
			[
				'network.json' => wp_json_encode($this->manifest()),
				'site.json'    => wp_json_encode(['url' => 'https://source.example.com']),
			]
		);

		$result = Network_Importer::detect_bundle_type($zip_path);

		$this->assertWPError($result);
		$this->assertSame('mixed-import-bundle', $result->get_error_code());
	}

	/**
	 * Test unsupported format versions are refused.
	 */
	public function test_network_import_refuses_unknown_format_version(): void {

		$manifest                   = $this->manifest();
		$manifest['format_version'] = Network_Importer::FORMAT_VERSION + 1;

		$result = Network_Importer::get_instance()->validate_manifest($manifest);

		$this->assertWPError($result);
		$this->assertSame('unsupported-network-format-version', $result->get_error_code());
	}

	/**
	 * Test selectable sites can fall back to per-site manifests.
	 */
	public function test_network_import_reads_sites_from_manifest_and_site_json(): void {

		$zip_path = $this->create_zip(
			[
				'network.json'      => wp_json_encode($this->manifest()),
				'sites/2/site.json' => wp_json_encode([
					'url'        => 'https://two.example.com',
					'post_count' => 12,
				]),
				'sites/3/site.json' => wp_json_encode([
					'url'        => 'https://three.example.com',
					'post_count' => 4,
				]),
			]
		);

		$sites = Network_Importer::get_sites_from_zip($zip_path);

		$this->assertIsArray($sites);
		$this->assertSame('https://two.example.com', $sites[2]['url']);
		$this->assertSame(12, $sites[2]['post_count']);
	}

	/**
	 * Create a zip file with entries.
	 *
	 * @param array $entries ZIP entries.
	 * @return string
	 */
	private function create_zip(array $entries): string {

		if (! class_exists('ZipArchive')) {
			$this->markTestSkipped('ZipArchive is required for network importer tests.');
		}

		$temp_file = tempnam(sys_get_temp_dir(), 'wu-network-import-test-');
		$zip_path  = $temp_file . '.zip';
		$zip       = new \ZipArchive();

		wp_delete_file($temp_file);

		$zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

		foreach ($entries as $name => $contents) {
			$zip->addFromString($name, $contents);
		}

		$zip->close();

		$this->tmp_files[] = $zip_path;

		return $zip_path;
	}

	/**
	 * Build a valid v1 manifest.
	 *
	 * @return array
	 */
	private function manifest(): array {

		return [
			'format_version'         => Network_Importer::FORMAT_VERSION,
			'included_blog_ids'      => [2, 3],
			'source'                 => [
				'wp_version' => get_bloginfo('version'),
			],
			'sitemeta_keys_restored' => [],
		];
	}
}
