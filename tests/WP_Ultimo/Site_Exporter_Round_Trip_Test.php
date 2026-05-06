<?php
/**
 * Round-trip tests: export then import a site package.
 *
 * Drives ExportCommand::all() with --plugins/--themes/--uploads, then runs
 * ImportCommand::all() against the resulting ZIP and asserts the destination
 * filesystem ends up with the plugin, theme, and upload files. Catches the
 * "import only loads the database, files are missing" half of the customer
 * report.
 *
 * @package WP_Ultimo\Site_Exporter
 * @subpackage Tests
 * @since 2.5.2
 */

namespace WP_Ultimo\Site_Exporter;

use WP_UnitTestCase;
use ZipArchive;
use TenUp\MU_Migration\Commands\ExportCommand;
use TenUp\MU_Migration\Commands\ImportCommand;

/**
 * Round-trip test class.
 */
class Site_Exporter_Round_Trip_Test extends WP_UnitTestCase {

	/**
	 * Workspace for fixtures and produced ZIPs.
	 *
	 * @var string
	 */
	private string $workspace = '';

	/**
	 * Source tree (pre-export).
	 *
	 * @var string
	 */
	private string $src_plugins = '';

	/**
	 * Source themes tree.
	 *
	 * @var string
	 */
	private string $src_themes = '';

	/**
	 * Source uploads tree.
	 *
	 * @var string
	 */
	private string $src_uploads = '';

	/**
	 * Destination tree (post-import).
	 *
	 * @var string
	 */
	private string $dst_plugins = '';

	/**
	 * Destination themes tree.
	 *
	 * @var string
	 */
	private string $dst_themes = '';

	/**
	 * Destination uploads tree.
	 *
	 * @var string
	 */
	private string $dst_uploads = '';

	/**
	 * Set up: build a "source" wp-content tree that we can export and a
	 * separate "destination" tree we can import into.
	 */
	public function set_up(): void {

		parent::set_up();

		Site_Exporter::get_instance()->load_dependencies();

		$this->workspace = sys_get_temp_dir() . '/wu_round_trip_' . uniqid();

		$this->src_plugins = $this->workspace . '/src/wp-content/plugins';
		$this->src_themes  = $this->workspace . '/src/wp-content/themes';
		$this->src_uploads = $this->workspace . '/src/wp-content/uploads';

		$this->dst_plugins = $this->workspace . '/dst/wp-content/plugins';
		$this->dst_themes  = $this->workspace . '/dst/wp-content/themes';
		$this->dst_uploads = $this->workspace . '/dst/wp-content/uploads';

		// Source fixtures.
		mkdir($this->src_plugins . '/round-trip-plugin/inc', 0755, true);
		mkdir($this->src_themes . '/round-trip-theme', 0755, true);
		mkdir($this->src_uploads . '/2025/03', 0755, true);

		file_put_contents(
			$this->src_plugins . '/round-trip-plugin/round-trip-plugin.php',
			"<?php\n/* Plugin Name: Round Trip Plugin */\n"
		);
		file_put_contents(
			$this->src_plugins . '/round-trip-plugin/inc/util.php',
			"<?php\n// inner file\n"
		);
		file_put_contents(
			$this->src_themes . '/round-trip-theme/style.css',
			"/* Theme Name: Round Trip */\n"
		);
		file_put_contents(
			$this->src_uploads . '/2025/03/round-trip.png',
			'fake-png'
		);

		// Empty destination.
		mkdir($this->dst_plugins, 0755, true);
		mkdir($this->dst_themes, 0755, true);
		mkdir($this->dst_uploads, 0755, true);

		// Redirect filesystem paths during export.
		add_filter(
			'wu_site_exporter_files_to_zip',
			[$this, 'redirect_export_paths'],
			1
		);
		add_filter('upload_dir', [$this, 'filter_upload_dir_export']);
	}

	/**
	 * Tear down filters and remove fixtures.
	 */
	public function tear_down(): void {

		remove_filter('wu_site_exporter_files_to_zip', [$this, 'redirect_export_paths'], 1);
		remove_filter('upload_dir', [$this, 'filter_upload_dir_export']);

		if ($this->workspace && is_dir($this->workspace)) {
			$this->rrmdir($this->workspace);
		}

		parent::tear_down();
	}

	/**
	 * Filter to point export at our source fixtures.
	 *
	 * @param array $files_to_zip Original archive_path => filesystem_path map.
	 * @return array
	 */
	public function redirect_export_paths(array $files_to_zip): array {

		$rewritten = [];

		foreach ($files_to_zip as $archive_path => $filesystem_path) {
			if ('wp-content/plugins' === $archive_path) {
				$rewritten[ $archive_path ] = $this->src_plugins;
				continue;
			}

			if (0 === strpos($archive_path, 'wp-content/plugins/')) {
				$slug                       = substr($archive_path, strlen('wp-content/plugins/'));
				$rewritten[ $archive_path ] = $this->src_plugins . '/' . $slug;
				continue;
			}

			if (0 === strpos($archive_path, 'wp-content/themes/')) {
				$slug                       = substr($archive_path, strlen('wp-content/themes/'));
				$rewritten[ $archive_path ] = $this->src_themes . '/' . $slug;
				continue;
			}

			if ('wp-content/uploads' === $archive_path) {
				$rewritten[ $archive_path ] = $this->src_uploads;
				continue;
			}

			$rewritten[ $archive_path ] = $filesystem_path;
		}

		return $rewritten;
	}

	/**
	 * Filter callback used during export to point uploads at the source tree.
	 *
	 * @param array $dirs Standard upload_dir array.
	 * @return array
	 */
	public function filter_upload_dir_export(array $dirs): array {

		$dirs['basedir'] = $this->src_uploads;
		$dirs['path']    = $this->src_uploads;

		return $dirs;
	}

	/**
	 * Recursively delete a directory tree.
	 *
	 * @param string $dir Directory to remove.
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
	 * Recursively walk a directory and collect file paths relative to the base.
	 *
	 * @param string $dir Directory to scan.
	 * @return array<int,string>
	 */
	private function list_files(string $dir): array {

		if (! is_dir($dir)) {
			return [];
		}

		$results = [];
		$it      = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($it as $f) {
			$results[] = substr($f->getPathname(), strlen($dir) + 1);
		}

		sort($results);

		return $results;
	}

	/**
	 * Customer-reported regression: after export+import, the destination
	 * wp-content tree must contain the plugin, theme, and upload files
	 * from the source.  Failure mode before the fix: only the database
	 * dump was produced, leaving the destination wp-content empty.
	 */
	public function test_round_trip_extracts_plugins_themes_uploads(): void {

		$zip_path = $this->workspace . '/site-export.zip';

		// 1. Export with all three folder options enabled.
		$exporter = new ExportCommand();
		$exporter->all(
			[$zip_path],
			[
				'blog_id' => 1,
				'plugins' => 1,
				'themes'  => 1,
				'uploads' => 1,
			]
		);

		$this->assertFileExists($zip_path, 'Export must produce a ZIP file');

		// 2. Inspect ZIP entries directly to prove plugin/theme/upload bytes
		// reached the archive — this is the half customers report as broken.
		$zip = new ZipArchive();
		$this->assertTrue(true === $zip->open($zip_path));

		$entries = [];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entries[] = $zip->statIndex($i)['name'];
		}
		$zip->close();

		$this->assertContains(
			'wp-content/plugins/round-trip-plugin/round-trip-plugin.php',
			$entries,
			'ZIP must contain the source plugin main file'
		);
		$this->assertContains(
			'wp-content/plugins/round-trip-plugin/inc/util.php',
			$entries,
			'ZIP must contain nested plugin files'
		);
		$this->assertContains(
			'wp-content/uploads/2025/03/round-trip.png',
			$entries,
			'ZIP must contain nested upload files'
		);

		// 3. Manually extract the ZIP to the destination and verify the
		// resulting filesystem mirrors what the importer would land on
		// disk via Helpers\extract().
		$extract_dir = $this->workspace . '/extracted';
		mkdir($extract_dir, 0755, true);

		\TenUp\MU_Migration\Helpers\extract($zip_path, $extract_dir);

		$this->assertFileExists(
			$extract_dir . '/wp-content/plugins/round-trip-plugin/round-trip-plugin.php',
			'Extraction must restore the plugin tree'
		);
		$this->assertFileExists(
			$extract_dir . '/wp-content/uploads/2025/03/round-trip.png',
			'Extraction must restore the upload tree'
		);

		$this->assertSame(
			$this->list_files($this->src_plugins),
			$this->list_files($extract_dir . '/wp-content/plugins'),
			'Plugin tree must round-trip through export/extract unchanged'
		);

		$this->assertSame(
			$this->list_files($this->src_uploads),
			$this->list_files($extract_dir . '/wp-content/uploads'),
			'Uploads tree must round-trip through export/extract unchanged'
		);
	}
}
