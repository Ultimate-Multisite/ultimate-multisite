<?php
/**
 * Tests for importer functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for importer functions.
 */
class Importer_Functions_Test extends WP_UnitTestCase {

	/**
	 * Pending import hashes created during tests.
	 *
	 * @var string[]
	 */
	private array $pending_import_hashes = [];

	/**
	 * Temporary zip files created during tests.
	 *
	 * @var string[]
	 */
	private array $temporary_import_files = [];

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {

		parent::set_up();

		wp_clear_scheduled_hook('wu_import_site');
		wp_clear_scheduled_hook('wu_import_network');
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		wp_clear_scheduled_hook('wu_import_site');
		wp_clear_scheduled_hook('wu_import_network');

		foreach ($this->pending_import_hashes as $hash) {
			wu_exporter_delete_transient("wu_pending_site_import_{$hash}");
			wu_exporter_delete_transient("wu_pending_network_import_{$hash}");
		}

		foreach ($this->temporary_import_files as $file) {
			if (file_exists($file)) {
				wp_delete_file($file);
			}
		}

		$this->pending_import_hashes  = [];
		$this->temporary_import_files = [];

		parent::tear_down();
	}

	/**
	 * Create a temporary zip file for import tests.
	 *
	 * @return string
	 */
	private function create_valid_zip_file(): string {

		$tmp = tempnam(sys_get_temp_dir(), 'wu_test_');

		if (false === $tmp) {
			$this->markTestSkipped('Unable to create a temporary file');
		}

		wp_delete_file($tmp);

		$zip_path = "{$tmp}.zip";
		$zip      = new \ZipArchive();

		if ($zip->open($zip_path, \ZipArchive::CREATE) !== true) {
			$this->markTestSkipped('ZipArchive not available');
		}

		$zip->addFromString('test.txt', 'test content');
		$zip->close();

		$this->temporary_import_files[] = $zip_path;

		return $zip_path;
	}

	/**
	 * Test wu_exporter_import async returns WP_Error for non-existent file.
	 */
	public function test_import_async_returns_wp_error_for_nonexistent_file(): void {

		$result = wu_exporter_import('/tmp/nonexistent-wu-test.zip', [], true);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('invalid-type', $result->get_error_code());
	}

	/**
	 * Test wu_exporter_add_pending_import returns WP_Error for non-existent file.
	 */
	public function test_add_pending_import_returns_wp_error_for_nonexistent(): void {

		$result = wu_exporter_add_pending_import('/tmp/nonexistent-wu-test.zip');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('invalid-type', $result->get_error_code());
	}

	/**
	 * Test wu_exporter_add_pending_import returns WP_Error for invalid mime type.
	 */
	public function test_add_pending_import_returns_wp_error_for_invalid_mime(): void {

		$tmp = tempnam(sys_get_temp_dir(), 'wu_test_');

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture writes a local temporary invalid zip file.
		file_put_contents($tmp, 'this is not a zip file');

		$result = wu_exporter_add_pending_import($tmp);

		wp_delete_file($tmp);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('invalid-type', $result->get_error_code());
	}

	/**
	 * Test wu_exporter_add_pending_import returns hash string for valid zip.
	 */
	public function test_add_pending_import_returns_hash_for_valid_zip(): void {

		$tmp    = $this->create_valid_zip_file();
		$result = wu_exporter_add_pending_import($tmp);

		if (is_string($result)) {
			$this->pending_import_hashes[] = $result;
		}

		$this->assertIsString($result);
		$this->assertSame(32, strlen($result));
	}

	/**
	 * Test wu_exporter_add_pending_import schedules the site import cron hook.
	 */
	public function test_add_pending_import_schedules_site_import_cron(): void {

		$tmp    = $this->create_valid_zip_file();
		$result = wu_exporter_add_pending_import($tmp);

		if (is_string($result)) {
			$this->pending_import_hashes[] = $result;
		}

		$this->assertIsString($result);
		$this->assertNotFalse(wp_next_scheduled('wu_import_site'), 'Adding a pending site import must schedule wu_import_site');
		$this->assertFalse(wp_next_scheduled('wu_import_network'), 'Adding a pending site import must not schedule wu_import_network');
	}

	/**
	 * Test wu_exporter_add_pending_network_import schedules the network import cron hook.
	 */
	public function test_add_pending_network_import_schedules_network_import_cron(): void {

		$tmp    = $this->create_valid_zip_file();
		$result = wu_exporter_add_pending_network_import($tmp);

		if (is_string($result)) {
			$this->pending_import_hashes[] = $result;
		}

		$this->assertIsString($result);
		$this->assertNotFalse(wp_next_scheduled('wu_import_network'), 'Adding a pending network import must schedule wu_import_network');
		$this->assertFalse(wp_next_scheduled('wu_import_site'), 'Adding a pending network import must not schedule wu_import_site');
	}

	/**
	 * Test wu_exporter_get_pending_imports returns array.
	 */
	public function test_get_pending_imports_returns_array(): void {

		$result = wu_exporter_get_pending_imports();

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_exporter_save_import_time returns bool.
	 */
	public function test_save_import_time_returns_bool(): void {

		$result = wu_exporter_save_import_time('test-import.zip', 2.5);

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_exporter_url_to_path returns string.
	 */
	public function test_url_to_path_returns_string(): void {

		$result = wu_exporter_url_to_path(site_url('/wp-content/uploads/test.zip'));

		$this->assertIsString($result);
	}

	/**
	 * Test wu_exporter_url_to_site returns false for invalid URL.
	 */
	public function test_url_to_site_returns_false_for_invalid_url(): void {

		$result = wu_exporter_url_to_site('not-a-url');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_exporter_url_to_site returns object for valid URL.
	 */
	public function test_url_to_site_returns_object_for_valid_url(): void {

		$result = wu_exporter_url_to_site(site_url('/'));

		if (is_multisite()) {
			$this->assertTrue($result instanceof \WP_Site || false === $result);
		} else {
			$this->assertIsObject($result);
			$this->assertObjectHasProperty('blog_id', $result);
		}
	}

	/**
	 * Test wu_exporter_maybe_get_site_by_path in multisite returns false for unknown domain.
	 */
	public function test_maybe_get_site_by_path_multisite(): void {

		if ( ! is_multisite()) {
			$this->markTestSkipped('Multisite only test');
		}

		$result = wu_exporter_maybe_get_site_by_path('nonexistent-domain-wu-test.com', '/');

		$this->assertFalse($result);
	}

	// --------------------------------------------------------
	// Deprecated function aliases
	// --------------------------------------------------------

	public function test_deprecated_add_pending_import_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_add_pending_import');

		$result = wu_site_exporter_add_pending_import('/tmp/nonexistent-wu-test.zip');

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function test_deprecated_get_pending_imports_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_get_pending_imports');

		$result = wu_site_exporter_get_pending_imports();

		$this->assertIsArray($result);
	}

	public function test_deprecated_save_import_time_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_save_import_time');

		$result = wu_site_exporter_save_import_time('alias-import.zip', 1.0);

		$this->assertIsBool($result);
	}

	public function test_deprecated_url_to_path_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_url_to_path');

		$result = wu_site_exporter_url_to_path(site_url('/wp-content/uploads/test.zip'));

		$this->assertIsString($result);
	}

	public function test_deprecated_url_to_site_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_url_to_site');

		$result = wu_site_exporter_url_to_site('not-a-url');

		$this->assertFalse($result);
	}

	public function test_wp_ultimo_maybe_get_site_by_path_alias(): void {

		if (is_multisite()) {
			$result = wp_ultimo_site_exporter_maybe_get_site_by_path('nonexistent-wu-test.com', '/');
			$this->assertFalse($result);
		} else {
			$result = wp_ultimo_site_exporter_maybe_get_site_by_path('example.com', '/');
			$this->assertIsObject($result);
		}
	}
}
