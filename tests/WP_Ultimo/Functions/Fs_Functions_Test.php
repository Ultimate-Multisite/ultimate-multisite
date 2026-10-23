<?php
/**
 * Tests for file system functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for file system functions.
 */
class Fs_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test opcode cache invalidation degrades safely when a file is not cached.
	 */
	public function test_invalidate_opcode_cache_returns_boolean(): void {

		$file_path = tempnam(sys_get_temp_dir(), 'wu-opcache-');

		try {
			$this->assertIsBool(wu_invalidate_opcode_cache($file_path));
		} finally {
			wp_delete_file($file_path);
		}
	}

	/**
	 * Test wu_get_main_site_upload_dir returns array.
	 */
	public function test_get_main_site_upload_dir(): void {

		$result = wu_get_main_site_upload_dir();

		$this->assertIsArray($result);
		$this->assertArrayHasKey('basedir', $result);
		$this->assertArrayHasKey('baseurl', $result);
	}

	/**
	 * Test wu_maybe_create_folder returns path string.
	 */
	public function test_maybe_create_folder(): void {

		$result = wu_maybe_create_folder('wu-test-folder');

		$this->assertIsString($result);
		$this->assertStringContainsString('wu-test-folder', $result);
	}

	/**
	 * Test wu_maybe_create_folder with subpath.
	 */
	public function test_maybe_create_folder_with_subpath(): void {

		$result = wu_maybe_create_folder('wu-test-folder', 'sub', 'path');

		$this->assertIsString($result);
		$this->assertStringContainsString('wu-test-folder', $result);
		$this->assertStringContainsString('sub/path', $result);
	}

	/**
	 * Test wu_get_folder_url returns URL string.
	 */
	public function test_get_folder_url(): void {

		$result = wu_get_folder_url('wu-test-folder');

		$this->assertIsString($result);
		$this->assertStringContainsString('wu-test-folder', $result);
		$this->assertStringContainsString('://', $result);
	}
}
