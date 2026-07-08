<?php
/**
 * Tests for the import-side directory mover helpers.
 *
 * Customer-reported regression: imports left destination plugin/theme/upload
 * folders empty even when the export ZIP contained those bytes. These tests
 * exercise the private movers that ImportCommand::all() uses, to prove that
 * a populated ZIP makes it onto the destination filesystem.
 *
 * @package WP_Ultimo\Site_Exporter
 * @subpackage Tests
 * @since 2.5.2
 */

namespace WP_Ultimo\Site_Exporter;

use ReflectionMethod;
use WP_UnitTestCase;
use TenUp\MU_Migration\Commands\ImportCommand;

/**
 * Test class for ImportCommand directory movers.
 */
class Site_Exporter_Import_Move_Test extends WP_UnitTestCase {

	/**
	 * Workspace root for fixtures.
	 *
	 * @var string
	 */
	private string $workspace = '';

	/**
	 * Source directory the ImportCommand reads from (mirrors the extracted ZIP).
	 *
	 * @var string
	 */
	private string $source = '';

	/**
	 * Destination uploads directory.
	 *
	 * @var string
	 */
	private string $dst_uploads = '';

	/**
	 * Set up: create source/destination trees per test.
	 */
	public function set_up(): void {

		parent::set_up();

		Site_Exporter::get_instance()->load_dependencies();

		$this->workspace   = sys_get_temp_dir() . '/wu_import_move_test_' . uniqid();
		$this->source      = $this->workspace . '/source';
		$this->dst_uploads = $this->workspace . '/dst-uploads';

		mkdir($this->source, 0755, true);
		mkdir($this->dst_uploads, 0755, true);

		// Pretend wp_upload_dir() points at our destination.
		add_filter(
			'upload_dir',
			function (array $dirs): array {
				$dirs['basedir'] = $this->dst_uploads;
				$dirs['path']    = $this->dst_uploads;

				return $dirs;
			}
		);
	}

	/**
	 * Tear down: remove fixtures.
	 */
	public function tear_down(): void {

		if ($this->workspace && is_dir($this->workspace)) {
			$this->rrmdir($this->workspace);
		}

		parent::tear_down();
	}

	/**
	 * Recursively remove a directory tree.
	 *
	 * @param string $dir Path to remove.
	 */
	private function rrmdir(string $dir): void {

		if (! is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) as $item) {
			if ('.' === $item || '..' === $item) {
				continue;
			}

			$path = $dir . '/' . $item;

			if (is_dir($path) && ! is_link($path)) {
				$this->rrmdir($path);
			} else {
				@unlink($path);
			}
		}

		@rmdir($dir);
	}

	/**
	 * Invoke a private ImportCommand method via reflection.
	 *
	 * @param ImportCommand $command Command instance.
	 * @param string        $method  Method name.
	 * @param array         $args    Positional arguments.
	 * @return mixed
	 */
	private function invoke_private(ImportCommand $command, string $method, array $args = []) {

		$reflection = new ReflectionMethod($command, $method);
		$reflection->setAccessible(true);

		return $reflection->invokeArgs($command, $args);
	}

	/**
	 * Customer-reported regression: the importer must copy upload files
	 * from the staging directory into the destination wp_upload_dir basedir.
	 */
	public function test_move_uploads_copies_files_to_destination(): void {

		$staging_uploads = $this->source . '/wp-content/uploads';
		mkdir($staging_uploads . '/2024/12', 0755, true);
		file_put_contents($staging_uploads . '/2024/12/customer.png', 'imported-image-bytes');

		$command = new ImportCommand();
		$this->invoke_private($command, 'move_uploads', [$staging_uploads, 1]);

		$this->assertFileExists(
			$this->dst_uploads . '/2024/12/customer.png',
			'move_uploads must place imported files under the destination upload basedir'
		);

		$this->assertSame(
			'imported-image-bytes',
			file_get_contents($this->dst_uploads . '/2024/12/customer.png'),
			'Imported upload contents must round-trip byte-for-byte'
		);
	}

	/**
	 * Customer-reported regression: when the export contained themes, the
	 * importer must move them into the WP themes directory.
	 *
	 * Theme activation goes through Helpers\runcommand('theme enable',...);
	 * the polyfill for that path is exercised by run_theme_enable_polyfill
	 * below.
	 */
	public function test_move_themes_relocates_theme_directories(): void {

		$staging_themes = $this->source . '/wp-content/themes';
		$theme_root     = get_theme_root();
		$theme_slug     = 'wu-test-imported-theme-' . uniqid();
		$dest_path      = $theme_root . '/' . $theme_slug;

		mkdir($staging_themes . '/' . $theme_slug, 0755, true);
		file_put_contents(
			$staging_themes . '/' . $theme_slug . '/style.css',
			"/* Theme Name: WU Test Imported Theme */\n"
		);

		// Make sure tear_down can clean up the moved theme.
		$this->ensure_path_cleaned_up($dest_path);

		try {
			$command = new ImportCommand();
			$this->invoke_private($command, 'move_themes', [$staging_themes]);

			$this->assertFileExists(
				$dest_path . '/style.css',
				'move_themes must move the theme directory into the WP themes folder'
			);
		} finally {
			$this->rrmdir($dest_path);
		}
	}

	/**
	 * The web-context polyfill for `theme enable` must call switch_theme()
	 * so the imported active theme actually takes effect.
	 *
	 * Before the polyfill was added, Helpers\runcommand('theme enable', [...])
	 * silently did nothing in web/AJAX context, leaving the destination on its
	 * old theme even though the new theme directory had been moved into place.
	 */
	public function test_runcommand_theme_enable_invokes_switch_theme(): void {

		// Find an installed theme other than the current one to switch to.
		$themes    = wp_get_themes();
		$current   = get_stylesheet();
		$candidate = '';

		foreach ($themes as $slug => $theme) {
			if ($slug !== $current && $theme->exists()) {
				$candidate = $slug;
				break;
			}
		}

		if ('' === $candidate) {
			$this->markTestSkipped('Need at least two installed themes to verify switch_theme behaviour');
		}

		$result = \TenUp\MU_Migration\Helpers\runcommand('theme enable', [$candidate]);

		$this->assertSame(0, $result->return_code, 'runcommand polyfill must report success');
		$this->assertSame(
			$candidate,
			get_stylesheet(),
			'theme enable polyfill must switch the active theme to the requested slug'
		);
	}

	/**
	 * Schedule cleanup of a path created during a test.
	 *
	 * @param string $path Path to remove during tear_down.
	 */
	private function ensure_path_cleaned_up(string $path): void {

		register_shutdown_function(
			function () use ($path) {
				if (is_dir($path)) {
					$it = new \RecursiveIteratorIterator(
						new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
						\RecursiveIteratorIterator::CHILD_FIRST
					);

					foreach ($it as $file_item) {
						$file_item->isDir() ? @rmdir($file_item->getPathname()) : @unlink($file_item->getPathname());
					}

					@rmdir($path);
				}
			}
		);
	}
}
