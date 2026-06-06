<?php
/**
 * Tests for the General_Compat class.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests;

use WP_Ultimo\Compat\General_Compat;
use WP_UnitTestCase;

/**
 * Test general compatibility fixes.
 */
class General_Compat_Test extends WP_UnitTestCase {

	/**
	 * Cache directories created by a test.
	 *
	 * @var array
	 */
	private $cache_dirs = [];

	/**
	 * Clean up generated cache fixtures.
	 */
	public function tearDown(): void {

		foreach ($this->cache_dirs as $cache_dir) {
			$this->remove_directory($cache_dir);
		}

		parent::tearDown();
	}

	/**
	 * Test init registers the Divi cache purge duplication hook.
	 */
	public function test_init_registers_divi_cache_purge_hook(): void {

		$instance = General_Compat::get_instance();
		$instance->init();

		$this->assertNotFalse(has_action('wu_duplicate_site', [$instance, 'clear_divi_static_css_cache']));
	}

	/**
	 * Test Divi et-cache files are deleted only for the cloned site.
	 */
	public function test_clear_divi_static_css_cache_deletes_cloned_site_cache_only(): void {

		if ( ! is_multisite()) {
			$this->markTestSkipped('Divi cache purge tests require multisite');
		}

		$blog_id       = self::factory()->blog->create();
		$other_blog_id = self::factory()->blog->create();
		$network_id    = (int) get_current_network_id();
		$cache_root    = trailingslashit(WP_CONTENT_DIR) . 'et-cache';
		$cache_dir     = trailingslashit($cache_root) . $network_id . '/' . $blog_id;
		$other_dir     = trailingslashit($cache_root) . $network_id . '/' . $other_blog_id;

		$this->cache_dirs = [$cache_dir, $other_dir];

		wp_mkdir_p($cache_dir . '/9');
		wp_mkdir_p($other_dir . '/9');

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
		file_put_contents($cache_dir . '/9/et-core-unified-deferred-9.min.css', 'stale divi css');
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
		file_put_contents($other_dir . '/9/et-core-unified-deferred-9.min.css', 'other divi css');

		General_Compat::get_instance()->clear_divi_static_css_cache(['site_id' => $blog_id]);

		$this->assertDirectoryDoesNotExist($cache_dir);
		$this->assertDirectoryExists($other_dir);
	}

	/**
	 * Recursively remove a test directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function remove_directory(string $dir): void {

		if ('' === $dir || ! is_dir($dir)) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $file) {
			$path = $file->getRealPath();

			if (false === $path) {
				continue;
			}

			if ($file->isDir()) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- Test fixture cleanup.
				@rmdir($path);
			} else {
				wp_delete_file($path);
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- Test fixture cleanup.
		@rmdir($dir);
	}
}
