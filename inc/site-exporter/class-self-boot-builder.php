<?php
/**
 * Self-Boot Builder - generates self-booting installer files for export ZIPs.
 *
 * At export time this class writes two new files into the ZIP root:
 * - index.php  : single-file PHP wizard that installs WordPress + plugin + imports bundle.
 * - readme.txt : human-readable extraction + import guide.
 *
 * @package WP_Ultimo\Site_Exporter
 * @subpackage Self_Boot
 * @since 2.5.0
 */

namespace WP_Ultimo\Site_Exporter;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Self_Boot_Builder class.
 *
 * Pure string-assembly class. All public methods are static so they can be
 * called without instantiation from both the single-site and network export paths.
 *
 * @package WP_Ultimo\Site_Exporter
 * @since 2.5.0
 */
class Self_Boot_Builder {

	/**
	 * Template directory path (relative to plugin root).
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const TEMPLATE_DIR = 'views/site-exporter/self-boot/';

	/**
	 * Add self-boot files to an existing ZIP file.
	 *
	 * Used by the single-site export path, where mu-migration already created
	 * the ZIP. Opens the ZIP, injects index.php + readme.txt at the root, then
	 * optionally bundles the plugin source under wp-ultimate-plugin/.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zip_path    Absolute path to the .zip file.
	 * @param string $bundle_type 'single-site' or 'network'.
	 * @param array  $manifest    Optional manifest data (source URL, WP/PHP version, etc.).
	 * @return bool True on success, false on failure.
	 */
	public static function add_to_zip(string $zip_path, string $bundle_type = 'single-site', array $manifest = []): bool {

		if (! class_exists('ZipArchive')) {
			return false;
		}

		if (! file_exists($zip_path)) {
			return false;
		}

		$zip    = new \ZipArchive();
		$result = $zip->open($zip_path);

		if (true !== $result) {
			return false;
		}

		$index_content  = self::render_index_template($bundle_type, $manifest);
		$readme_content = self::render_readme($bundle_type, $manifest);

		$zip->addFromString('index.php', $index_content);
		$zip->addFromString('readme.txt', $readme_content);

		// Bundle the plugin source so the installer can activate the same version
		// that created this export, without relying on wordpress.org availability.
		self::add_plugin_source_to_zip($zip);

		$zip->close();

		return true;
	}

	/**
	 * Add self-boot files to a staging directory.
	 *
	 * Used by the network export path (Network_Exporter), which builds the bundle
	 * in a staging directory before calling create_zip(). Placing the files here
	 * means they are automatically included when the directory is zipped.
	 *
	 * @since 2.5.0
	 *
	 * @param string $staging_dir Absolute path to the staging directory.
	 * @param string $bundle_type 'single-site' or 'network'.
	 * @param array  $manifest    Manifest data (from create_network_manifest).
	 * @return bool True on success, false on failure.
	 */
	public static function add_to_staging_dir(string $staging_dir, string $bundle_type = 'network', array $manifest = []): bool {

		$staging_dir = rtrim($staging_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if (! is_dir($staging_dir)) {
			return false;
		}

		$index_content  = self::render_index_template($bundle_type, $manifest);
		$readme_content = self::render_readme($bundle_type, $manifest);

		if (false === file_put_contents($staging_dir . 'index.php', $index_content)) {
			return false;
		}

		if (false === file_put_contents($staging_dir . 'readme.txt', $readme_content)) {
			return false;
		}

		// Bundle the plugin source under wp-ultimate-plugin/ in the staging dir.
		self::add_plugin_source_to_dir($staging_dir . 'wp-ultimate-plugin' . DIRECTORY_SEPARATOR);

		return true;
	}

	/**
	 * Build a minimal manifest array for single-site exports.
	 *
	 * Network exports produce a full manifest via create_network_manifest().
	 * Single-site exports don't have one, so this method constructs the
	 * minimal fields that the installer template needs.
	 *
	 * @since 2.5.0
	 *
	 * @param int $site_id Blog ID of the exported site.
	 * @return array
	 */
	public static function build_single_site_manifest(int $site_id): array {

		global $wp_version;

		$site = get_site($site_id);

		return [
			'bundle_type' => 'single-site',
			'source'      => [
				'url'                  => $site ? 'https://' . $site->domain . $site->path : network_site_url(),
				'wp_version'           => $wp_version,
				'php_version'          => PHP_VERSION,
				'is_subdomain_install' => false,
			],
		];
	}

	/**
	 * Render the index.php installer template with token substitution.
	 *
	 * @since 2.5.0
	 *
	 * @param string $bundle_type 'single-site' or 'network'.
	 * @param array  $manifest    Manifest data for token values.
	 * @return string Rendered PHP source code for the installer.
	 */
	public static function render_index_template(string $bundle_type, array $manifest = []): string {

		$template_path = self::get_template_path('index.php.tpl');

		if (! file_exists($template_path)) {
			return '';
		}

		$template = file_get_contents($template_path);

		if (false === $template) {
			return '';
		}

		$source = wu_get_isset($manifest, 'source', []);

		$tokens = [
			'{{BUNDLE_TYPE}}'        => $bundle_type,
			'{{PLUGIN_VERSION}}'     => defined('WP_ULTIMO_VERSION') ? WP_ULTIMO_VERSION : 'unknown',
			'{{EXPORT_DATE}}'        => current_time('mysql', true),
			'{{SOURCE_WP_VERSION}}'  => wu_get_isset($source, 'wp_version', ''),
			'{{SOURCE_PHP_VERSION}}' => wu_get_isset($source, 'php_version', ''),
			'{{SUBDOMAIN_INSTALL}}'  => ! empty($source['is_subdomain_install']) ? 'true' : 'false',
			'{{SOURCE_URL}}'         => wu_get_isset($source, 'url', ''),
		];

		return str_replace(array_keys($tokens), array_values($tokens), $template);
	}

	/**
	 * Render the readme.txt template with token substitution.
	 *
	 * @since 2.5.0
	 *
	 * @param string $bundle_type 'single-site' or 'network'.
	 * @param array  $manifest    Manifest data for token values.
	 * @return string Rendered readme content.
	 */
	public static function render_readme(string $bundle_type, array $manifest = []): string {

		$template_path = self::get_template_path('readme.txt');

		if (! file_exists($template_path)) {
			return '';
		}

		$template = file_get_contents($template_path);

		if (false === $template) {
			return '';
		}

		$source = wu_get_isset($manifest, 'source', []);

		$tokens = [
			'{{BUNDLE_TYPE}}'    => $bundle_type,
			'{{PLUGIN_VERSION}}' => defined('WP_ULTIMO_VERSION') ? WP_ULTIMO_VERSION : 'unknown',
			'{{EXPORT_DATE}}'    => current_time('mysql', true),
			'{{SOURCE_URL}}'     => wu_get_isset($source, 'url', ''),
		];

		return str_replace(array_keys($tokens), array_values($tokens), $template);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve the absolute path to a template file.
	 *
	 * @since 2.5.0
	 *
	 * @param string $filename Template filename.
	 * @return string Absolute path.
	 */
	private static function get_template_path(string $filename): string {

		return WP_ULTIMO_PLUGIN_DIR . self::TEMPLATE_DIR . $filename;
	}

	/**
	 * Add the plugin source tree to an open ZipArchive.
	 *
	 * Files are added under wp-ultimate-plugin/ in the ZIP root so the installer
	 * can locate them at a known path regardless of what else is in the bundle.
	 * Skips adding if the source directory does not exist or if the plugin is
	 * already present in the ZIP (network exports that included plugins).
	 *
	 * @since 2.5.0
	 *
	 * @param \ZipArchive $zip Open ZipArchive instance.
	 * @return void
	 */
	private static function add_plugin_source_to_zip(\ZipArchive $zip): void {

		$plugin_src = self::get_plugin_source_dir();

		if (! $plugin_src || ! is_dir($plugin_src)) {
			return;
		}

		// Skip if the plugin is already bundled under wp-content/plugins/.
		if (false !== $zip->locateName('wp-content/plugins/ultimate-multisite/')) {
			return;
		}

		$prefix = 'wp-ultimate-plugin/';
		$src    = rtrim($plugin_src, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($iterator as $file) {
			if ($file->isDir()) {
				continue;
			}

			$file_path     = $file->getRealPath();
			$relative_path = substr($file_path, strlen($src));

			$zip->addFile($file_path, $prefix . $relative_path);
		}
	}

	/**
	 * Copy the plugin source tree into a directory on disk.
	 *
	 * Used by the network export staging-dir path (files are on disk, not yet
	 * in a ZIP). The destination directory is created if it does not exist.
	 *
	 * @since 2.5.0
	 *
	 * @param string $dest_dir Absolute path to the destination directory.
	 * @return void
	 */
	private static function add_plugin_source_to_dir(string $dest_dir): void {

		$plugin_src = self::get_plugin_source_dir();

		if (! $plugin_src || ! is_dir($plugin_src)) {
			return;
		}

		if (! is_dir($dest_dir)) {
			mkdir($dest_dir, 0755, true);
		}

		self::copy_directory($plugin_src, $dest_dir);
	}

	/**
	 * Return the plugin source directory path.
	 *
	 * @since 2.5.0
	 *
	 * @return string|false Absolute path to the plugin directory, or false on failure.
	 */
	private static function get_plugin_source_dir() {

		if (defined('WP_ULTIMO_PLUGIN_DIR')) {
			return rtrim(WP_ULTIMO_PLUGIN_DIR, DIRECTORY_SEPARATOR);
		}

		return false;
	}

	/**
	 * Recursively copy a directory tree from $src to $dst.
	 *
	 * @since 2.5.0
	 *
	 * @param string $src Source directory.
	 * @param string $dst Destination directory.
	 * @return void
	 */
	private static function copy_directory(string $src, string $dst): void {

		$src = rtrim($src, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$dst = rtrim($dst, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $item) {
			$dest_path = $dst . $iterator->getSubPathName();

			if ($item->isDir()) {
				if (! is_dir($dest_path)) {
					mkdir($dest_path, 0755, true);
				}
			} else {
				copy($item->getRealPath(), $dest_path);
			}
		}
	}
}
