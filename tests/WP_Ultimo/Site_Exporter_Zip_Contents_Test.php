<?php
/**
 * Tests that site export ZIP files actually contain plugins, themes, and uploads.
 *
 * Regression coverage for customer-reported issue: "Exporting sites with plugins,
 * themes and uploads does not seem to work. Only the site data from mysql is
 * exported and not the rest needed to make it run."
 *
 * These tests drive the real ExportCommand::all() pipeline, then inspect the
 * resulting ZIP archive to verify each requested folder is present with content.
 *
 * @package WP_Ultimo\Site_Exporter
 * @subpackage Tests
 * @since 2.5.2
 */

namespace WP_Ultimo\Site_Exporter;

use WP_UnitTestCase;
use ZipArchive;
use TenUp\MU_Migration\Commands\ExportCommand;

/**
 * Test class for site export ZIP archive contents.
 */
class Site_Exporter_Zip_Contents_Test extends WP_UnitTestCase {

	/**
	 * Temporary working directory created per test and cleaned up in tear_down().
	 *
	 * @var string
	 */
	private string $tmp_dir = '';

	/**
	 * Fake "wp-content" tree we point the exporter at via filters.
	 *
	 * @var string
	 */
	private string $fake_wp_content = '';

	/**
	 * Fake plugin directory under $fake_wp_content/plugins.
	 *
	 * @var string
	 */
	private string $fake_plugins_dir = '';

	/**
	 * Fake themes directory under $fake_wp_content/themes.
	 *
	 * @var string
	 */
	private string $fake_themes_dir = '';

	/**
	 * Fake uploads directory.
	 *
	 * @var string
	 */
	private string $fake_uploads_dir = '';

	/**
	 * Set up: build a fake wp-content/plugins, wp-content/themes, and uploads
	 * tree so we can verify that the exporter zips real files, regardless of
	 * what the WordPress test installation contains.
	 */
	public function set_up(): void {

		parent::set_up();

		// Make sure mu-migration command classes are loaded for direct invocation.
		Site_Exporter::get_instance()->load_dependencies();

		$this->tmp_dir          = sys_get_temp_dir() . '/wu_export_zip_test_' . uniqid();
		$this->fake_wp_content  = $this->tmp_dir . '/wp-content';
		$this->fake_plugins_dir = $this->fake_wp_content . '/plugins';
		$this->fake_themes_dir  = $this->fake_wp_content . '/themes';
		$this->fake_uploads_dir = $this->fake_wp_content . '/uploads';

		// Plugins fixture: two plugin dirs, one will be excluded by the filter.
		mkdir($this->fake_plugins_dir . '/sample-plugin/inc', 0755, true);
		mkdir($this->fake_plugins_dir . '/ultimate-multisite/src', 0755, true);
		file_put_contents(
			$this->fake_plugins_dir . '/sample-plugin/sample-plugin.php',
			"<?php\n/* Plugin Name: Sample Plugin */\n"
		);
		file_put_contents(
			$this->fake_plugins_dir . '/sample-plugin/inc/lib.php',
			"<?php\nclass Sample_Plugin_Lib {}\n"
		);
		file_put_contents(
			$this->fake_plugins_dir . '/ultimate-multisite/ultimate-multisite.php',
			"<?php\n/* Plugin Name: Ultimate Multisite */\n"
		);

		// Themes fixture: a parent theme and a child theme.
		mkdir($this->fake_themes_dir . '/sample-theme/templates', 0755, true);
		mkdir($this->fake_themes_dir . '/sample-child', 0755, true);
		file_put_contents(
			$this->fake_themes_dir . '/sample-theme/style.css',
			"/* Theme Name: Sample Theme */\n"
		);
		file_put_contents(
			$this->fake_themes_dir . '/sample-theme/templates/single.php',
			"<?php /* single template */\n"
		);
		file_put_contents(
			$this->fake_themes_dir . '/sample-child/style.css',
			"/* Theme Name: Sample Child\nTemplate: sample-theme */\n"
		);

		// Uploads fixture: standard year/month layout plus a file at the root.
		mkdir($this->fake_uploads_dir . '/2024/01', 0755, true);
		file_put_contents(
			$this->fake_uploads_dir . '/2024/01/photo.jpg',
			'fake-jpeg-bytes'
		);
		file_put_contents(
			$this->fake_uploads_dir . '/sitemap.xml',
			'<?xml version="1.0"?><urlset></urlset>'
		);

		// Redirect WP_PLUGIN_DIR-equivalent lookups to our fake plugins folder.
		add_filter(
			'wu_site_exporter_files_to_zip',
			[$this, 'redirect_paths_to_fixtures'],
			1
		);

		// Force wp_upload_dir() to return our fixture so multisite + single-site
		// resolve the same upload basedir during the export.
		add_filter('upload_dir', [$this, 'filter_upload_dir']);
	}

	/**
	 * Tear down: restore filters and recursively delete fixtures.
	 */
	public function tear_down(): void {

		remove_filter('wu_site_exporter_files_to_zip', [$this, 'redirect_paths_to_fixtures'], 1);
		remove_filter('upload_dir', [$this, 'filter_upload_dir']);

		if ($this->tmp_dir && is_dir($this->tmp_dir)) {
			$this->rrmdir($this->tmp_dir);
		}

		parent::tear_down();
	}

	/**
	 * Filter callback: rewrite real WP plugin/theme paths to our fixtures so
	 * we can drive ExportCommand::all() without polluting the test install.
	 *
	 * @param array $files_to_zip Original archive_path => filesystem_path map.
	 * @return array
	 */
	public function redirect_paths_to_fixtures(array $files_to_zip): array {

		$rewritten = [];

		foreach ($files_to_zip as $archive_path => $filesystem_path) {
			// Plugins: the export sets either 'wp-content/plugins' (pre-filter)
			// or 'wp-content/plugins/<slug>' (after maybe_exclude_wp_ultimo_plugins).
			if ('wp-content/plugins' === $archive_path) {
				$rewritten[ $archive_path ] = $this->fake_plugins_dir;
				continue;
			}

			if (0 === strpos($archive_path, 'wp-content/plugins/')) {
				$slug                       = substr($archive_path, strlen('wp-content/plugins/'));
				$rewritten[ $archive_path ] = $this->fake_plugins_dir . '/' . $slug;
				continue;
			}

			if (0 === strpos($archive_path, 'wp-content/themes/')) {
				$slug                       = substr($archive_path, strlen('wp-content/themes/'));
				$rewritten[ $archive_path ] = $this->fake_themes_dir . '/' . $slug;
				continue;
			}

			if ('wp-content/uploads' === $archive_path) {
				$rewritten[ $archive_path ] = $this->fake_uploads_dir;
				continue;
			}

			// Everything else (csv/sql/json) passes through unchanged.
			$rewritten[ $archive_path ] = $filesystem_path;
		}

		return $rewritten;
	}

	/**
	 * Filter callback: redirect wp_upload_dir() to the fake uploads folder.
	 *
	 * @param array $dirs Standard upload_dir array.
	 * @return array
	 */
	public function filter_upload_dir(array $dirs): array {

		$dirs['basedir'] = $this->fake_uploads_dir;
		$dirs['path']    = $this->fake_uploads_dir;

		return $dirs;
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir Path to delete.
	 */
	private function rrmdir(string $dir): void {

		if (! is_dir($dir)) {
			return;
		}

		$items = scandir($dir);

		foreach ($items as $item) {
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
	 * Open the export ZIP and return the list of entry names.
	 *
	 * @param string $zip_path Path to the .zip file.
	 * @return array<int,string>
	 */
	private function list_zip_entries(string $zip_path): array {

		$this->assertFileExists($zip_path, 'Export ZIP must exist on disk');
		$this->assertGreaterThan(0, filesize($zip_path), 'Export ZIP must not be empty');

		$entries = [];
		$zip     = new ZipArchive();

		$this->assertTrue(
			true === $zip->open($zip_path),
			'Export ZIP must be a valid archive'
		);

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entries[] = $zip->statIndex($i)['name'];
		}

		$zip->close();

		return $entries;
	}

	/**
	 * Drive the export command directly with the given options and return
	 * the path to the resulting ZIP.
	 *
	 * @param array $assoc_args Options for ExportCommand::all().
	 * @return string Absolute path to the export ZIP.
	 */
	private function run_export(array $assoc_args): string {

		$zip_path           = $this->tmp_dir . '/export-' . uniqid() . '.zip';
		$assoc_args['blog_id'] = $assoc_args['blog_id'] ?? 1;

		$command = new ExportCommand();
		$command->all([$zip_path], $assoc_args);

		return $zip_path;
	}

	/**
	 * Database-only export must still produce a valid archive containing
	 * the three required fixtures (CSV, SQL, JSON) and nothing else.
	 *
	 * This is the baseline behaviour customers report as working.
	 */
	public function test_database_only_export_contains_required_files(): void {

		$zip_path = $this->run_export([]);
		$entries  = $this->list_zip_entries($zip_path);

		$has_csv  = false;
		$has_sql  = false;
		$has_json = false;

		foreach ($entries as $entry) {
			$has_csv  = $has_csv  || (substr($entry, -4) === '.csv');
			$has_sql  = $has_sql  || (substr($entry, -4) === '.sql');
			$has_json = $has_json || (substr($entry, -5) === '.json');
		}

		$this->assertTrue($has_csv,  'Export must include the users CSV');
		$this->assertTrue($has_sql,  'Export must include the SQL dump');
		$this->assertTrue($has_json, 'Export must include the meta JSON');
	}

	/**
	 * Customer-reported regression: with --plugins, the resulting ZIP must
	 * contain wp-content/plugins/<slug>/<file> entries — not just DB data.
	 */
	public function test_export_with_plugins_includes_plugin_files(): void {

		$zip_path = $this->run_export(['plugins' => 1]);
		$entries  = $this->list_zip_entries($zip_path);

		$plugin_entries = array_filter(
			$entries,
			static fn ($e) => 0 === strpos($e, 'wp-content/plugins/')
		);

		$this->assertNotEmpty(
			$plugin_entries,
			'Export with --plugins must include wp-content/plugins/* files. '
			. 'Got entries: ' . implode(', ', $entries)
		);

		$this->assertContains(
			'wp-content/plugins/sample-plugin/sample-plugin.php',
			$entries,
			'Export must contain the sample plugin main file'
		);

		$this->assertContains(
			'wp-content/plugins/sample-plugin/inc/lib.php',
			$entries,
			'Export must recurse into plugin subfolders'
		);
	}

	/**
	 * The maybe_exclude_wp_ultimo_plugins filter must remove ultimate-multisite/*
	 * entries from the archive while preserving every other plugin folder.
	 */
	public function test_export_with_plugins_excludes_ultimate_multisite(): void {

		$zip_path = $this->run_export(['plugins' => 1]);
		$entries  = $this->list_zip_entries($zip_path);

		$has_excluded = false;
		$has_included = false;

		foreach ($entries as $entry) {
			if (0 === strpos($entry, 'wp-content/plugins/ultimate-multisite/')) {
				$has_excluded = true;
			}

			if (0 === strpos($entry, 'wp-content/plugins/sample-plugin/')) {
				$has_included = true;
			}
		}

		$this->assertFalse($has_excluded, 'ultimate-multisite/* must be excluded from the export');
		$this->assertTrue($has_included, 'Other plugins must remain in the export');
	}

	/**
	 * Customer-reported regression: with --themes, the active theme directory
	 * must be present under wp-content/themes/<slug>/.
	 */
	public function test_export_with_themes_includes_theme_files(): void {

		// Switch to the fake theme so get_template_directory() resolves to it.
		add_filter('template_directory', fn () => $this->fake_themes_dir . '/sample-theme');
		add_filter('stylesheet_directory', fn () => $this->fake_themes_dir . '/sample-theme');

		$zip_path = $this->run_export(['themes' => 1]);
		$entries  = $this->list_zip_entries($zip_path);

		$theme_entries = array_filter(
			$entries,
			static fn ($e) => 0 === strpos($e, 'wp-content/themes/')
		);

		$this->assertNotEmpty(
			$theme_entries,
			'Export with --themes must include wp-content/themes/* files. '
			. 'Got entries: ' . implode(', ', $entries)
		);
	}

	/**
	 * Customer-reported regression: with --uploads, the uploads folder must
	 * be present under wp-content/uploads/.
	 */
	public function test_export_with_uploads_includes_uploads_files(): void {

		$zip_path = $this->run_export(['uploads' => 1]);
		$entries  = $this->list_zip_entries($zip_path);

		$upload_entries = array_filter(
			$entries,
			static fn ($e) => 0 === strpos($e, 'wp-content/uploads/')
		);

		$this->assertNotEmpty(
			$upload_entries,
			'Export with --uploads must include wp-content/uploads/* files. '
			. 'Got entries: ' . implode(', ', $entries)
		);

		$this->assertContains(
			'wp-content/uploads/2024/01/photo.jpg',
			$entries,
			'Export must include nested upload files'
		);

		$this->assertContains(
			'wp-content/uploads/sitemap.xml',
			$entries,
			'Export must include root-level upload files'
		);
	}

	/**
	 * Combined export: plugins + themes + uploads must all land in the ZIP
	 * alongside the database dump. This is the "complete export" customers
	 * expect when ticking all three options in the export modal.
	 */
	public function test_export_with_all_options_includes_everything(): void {

		add_filter('template_directory', fn () => $this->fake_themes_dir . '/sample-theme');
		add_filter('stylesheet_directory', fn () => $this->fake_themes_dir . '/sample-theme');

		$zip_path = $this->run_export(
			[
				'plugins' => 1,
				'themes'  => 1,
				'uploads' => 1,
			]
		);

		$entries = $this->list_zip_entries($zip_path);

		$has_plugin  = false;
		$has_theme   = false;
		$has_upload  = false;
		$has_sql     = false;

		foreach ($entries as $entry) {
			$has_plugin = $has_plugin || (0 === strpos($entry, 'wp-content/plugins/'));
			$has_theme  = $has_theme  || (0 === strpos($entry, 'wp-content/themes/'));
			$has_upload = $has_upload || (0 === strpos($entry, 'wp-content/uploads/'));
			$has_sql    = $has_sql    || (substr($entry, -4) === '.sql');
		}

		$this->assertTrue($has_plugin, 'Combined export must contain plugins');
		$this->assertTrue($has_theme,  'Combined export must contain themes');
		$this->assertTrue($has_upload, 'Combined export must contain uploads');
		$this->assertTrue($has_sql,    'Combined export must contain the SQL dump');
	}
}
