<?php
/**
 * Tests for the General_Compat class.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Compat;

defined('ABSPATH') || exit;

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
	 * Test Divi uploads fallback et-cache is deleted only for the cloned site.
	 */
	public function test_clear_divi_static_css_cache_deletes_uploads_fallback_cache_only(): void {

		if ( ! is_multisite()) {
			$this->markTestSkipped('Divi cache purge tests require multisite');
		}

		$blog_id       = self::factory()->blog->create();
		$other_blog_id = self::factory()->blog->create();

		switch_to_blog($blog_id);
		$upload_dir = wp_upload_dir(null, false);
		restore_current_blog();

		switch_to_blog($other_blog_id);
		$other_upload_dir = wp_upload_dir(null, false);
		restore_current_blog();

		$cache_dir = trailingslashit($upload_dir['basedir']) . 'et-cache';
		$other_dir = trailingslashit($other_upload_dir['basedir']) . 'et-cache';

		$this->cache_dirs = [$cache_dir, $other_dir];

		wp_mkdir_p($cache_dir . '/4');
		wp_mkdir_p($other_dir . '/4');

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
		file_put_contents($cache_dir . '/4/et-core-unified-deferred-4.min.css', 'stale divi css');
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
		file_put_contents($other_dir . '/4/et-core-unified-deferred-4.min.css', 'other divi css');

		General_Compat::get_instance()->clear_divi_static_css_cache(['site_id' => $blog_id]);

		$this->assertDirectoryDoesNotExist($cache_dir);
		$this->assertDirectoryExists($other_dir);
	}

	/**
	 * Test copied Divi generated CSS cache metadata is deleted only on the cloned site.
	 */
	public function test_clear_divi_static_css_cache_deletes_cloned_site_divi_cache_metadata_only(): void {

		if ( ! is_multisite()) {
			$this->markTestSkipped('Divi cache purge tests require multisite');
		}

		$blog_id       = self::factory()->blog->create();
		$other_blog_id = self::factory()->blog->create();

		switch_to_blog($blog_id);
		$post_id = self::factory()->post->create();
		update_post_meta($post_id, '_et_builder_module_features_cache', 'stale module cache');
		update_post_meta($post_id, '_et_dynamic_cached_attributes', 'stale attributes');
		update_post_meta($post_id, '_et_dynamic_cached_shortcodes', 'stale shortcodes');
		update_post_meta($post_id, 'et_enqueued_post_fonts', 'stale fonts');
		update_option('et_critical_css', 'stale critical css');
		update_option('et_builder_module_features_cache', 'stale option cache');
		restore_current_blog();

		switch_to_blog($other_blog_id);
		$other_post_id = self::factory()->post->create();
		update_post_meta($other_post_id, '_et_builder_module_features_cache', 'other module cache');
		update_option('et_critical_css', 'other critical css');
		restore_current_blog();

		$cache_flush_count = did_action('wu_flush_known_caches');

		General_Compat::get_instance()->clear_divi_static_css_cache(['site_id' => $blog_id]);

		$this->assertSame($cache_flush_count + 1, did_action('wu_flush_known_caches'));

		switch_to_blog($blog_id);
		$this->assertSame('', get_post_meta($post_id, '_et_builder_module_features_cache', true));
		$this->assertSame('', get_post_meta($post_id, '_et_dynamic_cached_attributes', true));
		$this->assertSame('', get_post_meta($post_id, '_et_dynamic_cached_shortcodes', true));
		$this->assertSame('', get_post_meta($post_id, 'et_enqueued_post_fonts', true));
		$this->assertFalse(get_option('et_critical_css'));
		$this->assertFalse(get_option('et_builder_module_features_cache'));
		restore_current_blog();

		switch_to_blog($other_blog_id);
		$this->assertSame('other module cache', get_post_meta($other_post_id, '_et_builder_module_features_cache', true));
		$this->assertSame('other critical css', get_option('et_critical_css'));
		restore_current_blog();
	}

	/**
	 * Test Divi et-cache symlinks do not delete files outside the cache tree.
	 */
	public function test_clear_divi_static_css_cache_does_not_follow_symlinks(): void {

		if ( ! is_multisite()) {
			$this->markTestSkipped('Divi cache purge tests require multisite');
		}

		if ( ! function_exists('symlink')) {
			$this->markTestSkipped('Symlink support is not available');
		}

		$blog_id       = self::factory()->blog->create();
		$other_blog_id = self::factory()->blog->create();
		$network_id    = (int) get_current_network_id();
		$cache_root    = trailingslashit(WP_CONTENT_DIR) . 'et-cache';
		$cache_dir     = trailingslashit($cache_root) . $network_id . '/' . $blog_id;
		$other_dir     = trailingslashit($cache_root) . $network_id . '/' . $other_blog_id;
		$outside_file  = $other_dir . '/external-target.css';
		$symlink       = $cache_dir . '/external-target.css';

		$this->cache_dirs = [$cache_dir, $other_dir];

		wp_mkdir_p($cache_dir);
		wp_mkdir_p($other_dir);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
		file_put_contents($outside_file, 'external divi css');

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.symlink_symlink -- Test fixture setup requires a symlink.
		if ( ! @symlink($outside_file, $symlink)) {
			$this->markTestSkipped('Symlink fixture could not be created');
		}

		General_Compat::get_instance()->clear_divi_static_css_cache(['site_id' => $blog_id]);

		$this->assertFileExists($outside_file);
		$this->assertDirectoryDoesNotExist($cache_dir);
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
			$path = $file->isLink() ? $file->getPathname() : $file->getRealPath();

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
