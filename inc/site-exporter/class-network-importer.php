<?php
/**
 * Network Importer - restores network export bundles.
 *
 * @package WP_Ultimo\Site_Exporter
 * @since 2.5.0
 */

namespace WP_Ultimo\Site_Exporter;

defined('ABSPATH') || exit;

/**
 * Network Importer class.
 *
 * @package WP_Ultimo\Site_Exporter
 * @since 2.5.0
 */
class Network_Importer {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Supported bundle format version.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const FORMAT_VERSION = 1;

	/**
	 * Source user ID to target user ID map.
	 *
	 * @since 2.5.0
	 * @var array
	 */
	protected array $user_id_map = [];

	/**
	 * Detects the import ZIP bundle type.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zip_path ZIP path.
	 * @return string|\WP_Error Bundle type or WP_Error.
	 */
	public static function detect_bundle_type(string $zip_path) {

		if (! file_exists($zip_path)) {
			return new \WP_Error('file-not-found', __('ZIP file not found.', 'ultimate-multisite'));
		}

		if (! class_exists('ZipArchive')) {
			return new \WP_Error('ziparchive-missing', __('The PHP ZipArchive extension is required to inspect import bundles.', 'ultimate-multisite'));
		}

		$zip = new \ZipArchive();

		if (true !== $zip->open($zip_path)) {
			return new \WP_Error('zip-open-failed', __('Could not open the ZIP file.', 'ultimate-multisite'));
		}

		$has_network_manifest = false !== $zip->locateName('network.json', \ZipArchive::FL_NOCASE);
		$has_site_manifest    = false;

		for ($index = 0; $index < $zip->numFiles; $index++) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
			$name = $zip->getNameIndex($index);

			if (! is_string($name) || false !== strpos($name, '/')) {
				continue;
			}

			if ('network.json' === strtolower($name)) {
				continue;
			}

			if (preg_match('/\.json$/i', $name)) {
				$has_site_manifest = true;
				break;
			}
		}

		$zip->close();

		if ($has_network_manifest && $has_site_manifest) {
			return new \WP_Error('mixed-import-bundle', __('The ZIP contains both network and single-site manifests.', 'ultimate-multisite'));
		}

		if ($has_network_manifest) {
			return 'network';
		}

		if ($has_site_manifest) {
			return 'site';
		}

		return new \WP_Error('unknown-import-bundle', __('The ZIP is not a recognized Ultimate Multisite import bundle.', 'ultimate-multisite'));
	}

	/**
	 * Reads network.json from a ZIP without extracting the whole archive.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zip_path ZIP path.
	 * @return array|\WP_Error Manifest array or WP_Error.
	 */
	public static function get_manifest_from_zip(string $zip_path) {

		if (! class_exists('ZipArchive')) {
			return new \WP_Error('ziparchive-missing', __('The PHP ZipArchive extension is required to inspect import bundles.', 'ultimate-multisite'));
		}

		$zip = new \ZipArchive();

		if (true !== $zip->open($zip_path)) {
			return new \WP_Error('zip-open-failed', __('Could not open the ZIP file.', 'ultimate-multisite'));
		}

		$manifest_json = $zip->getFromName('network.json');
		$zip->close();

		if (! is_string($manifest_json) || '' === $manifest_json) {
			return new \WP_Error('network-manifest-missing', __('Network import bundles must include network.json at the ZIP root.', 'ultimate-multisite'));
		}

		$manifest = json_decode($manifest_json, true);

		if (! is_array($manifest)) {
			return new \WP_Error('network-manifest-invalid', __('The network import manifest is not valid JSON.', 'ultimate-multisite'));
		}

		return $manifest;
	}

	/**
	 * Reads selectable site rows from a network ZIP manifest.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zip_path ZIP path.
	 * @return array|\WP_Error Site rows keyed by source blog ID.
	 */
	public static function get_sites_from_zip(string $zip_path) {

		$manifest = self::get_manifest_from_zip($zip_path);

		if (is_wp_error($manifest)) {
			return $manifest;
		}

		$zip = new \ZipArchive();

		if (true !== $zip->open($zip_path)) {
			return new \WP_Error('zip-open-failed', __('Could not open the ZIP file.', 'ultimate-multisite'));
		}

		$sites = [];

		foreach ((array) ($manifest['included_blog_ids'] ?? []) as $blog_id) {
			$blog_id = (int) $blog_id;
			$row     = [
				'blog_id'    => $blog_id,
				'url'        => '',
				'post_count' => 0,
			];

			if (! empty($manifest['sites'][ $blog_id ]) && is_array($manifest['sites'][ $blog_id ])) {
				$row['url']        = (string) ($manifest['sites'][ $blog_id ]['url'] ?? '');
				$row['post_count'] = (int) ($manifest['sites'][ $blog_id ]['post_count'] ?? 0);
			} else {
				$site_manifest = self::get_site_manifest_from_zip($zip, $blog_id);

				if (is_array($site_manifest)) {
					$row['url']        = (string) ($site_manifest['url'] ?? '');
					$row['post_count'] = (int) ($site_manifest['post_count'] ?? 0);
				}
			}

			$sites[ $blog_id ] = $row;
		}

		$zip->close();

		return $sites;
	}

	/**
	 * Reads a per-site manifest from the ZIP when network.json has no site row.
	 *
	 * @since 2.5.0
	 *
	 * @param \ZipArchive $zip     Open ZIP archive.
	 * @param int         $blog_id Source blog ID.
	 * @return array|false
	 */
	protected static function get_site_manifest_from_zip(\ZipArchive $zip, int $blog_id) {

		$prefix = 'sites/' . $blog_id . '/';

		for ($index = 0; $index < $zip->numFiles; $index++) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
			$name = $zip->getNameIndex($index);

			if (! is_string($name) || 0 !== strpos($name, $prefix) || ! preg_match('/\.json$/i', $name)) {
				continue;
			}

			$contents = $zip->getFromName($name);
			$manifest = is_string($contents) ? json_decode($contents, true) : null;

			return is_array($manifest) ? $manifest : false;
		}

		return false;
	}

	/**
	 * Validates network manifest compatibility.
	 *
	 * @since 2.5.0
	 *
	 * @param array $manifest Manifest array.
	 * @return true|\WP_Error
	 */
	public function validate_manifest(array $manifest) {

		$format_version = isset($manifest['format_version']) ? (int) $manifest['format_version'] : 0;

		if (self::FORMAT_VERSION !== $format_version) {
			return new \WP_Error(
				'unsupported-network-format-version',
				sprintf(
					/* translators: 1: bundle format version, 2: importer format version. */
					__('This network import bundle uses format version %1$d, but this importer only supports version %2$d.', 'ultimate-multisite'),
					$format_version,
					self::FORMAT_VERSION
				)
			);
		}

		if (empty($manifest['included_blog_ids']) || ! is_array($manifest['included_blog_ids'])) {
			return new \WP_Error('network-manifest-empty-sites', __('The network import manifest does not list any included sites.', 'ultimate-multisite'));
		}

		$source_wp_version = $manifest['source']['wp_version'] ?? '';
		if ($source_wp_version) {
			$source_major = (int) strtok((string) $source_wp_version, '.');
			$target_major = (int) strtok((string) get_bloginfo('version'), '.');

			if ($source_major && $target_major && $source_major !== $target_major) {
				return new \WP_Error('network-import-wp-major-version-mismatch', __('The network bundle was exported from a different major WordPress version.', 'ultimate-multisite'));
			}
		}

		return true;
	}

	/**
	 * Imports a network bundle.
	 *
	 * @since 2.5.0
	 *
	 * @param string $zip_path ZIP path.
	 * @param array  $options  Import options.
	 * @return array|\WP_Error Import summary or WP_Error.
	 */
	public function import(string $zip_path, array $options = []) {

		$bundle_type = $this->detect_bundle_type($zip_path);

		if (is_wp_error($bundle_type)) {
			return $bundle_type;
		}

		if ('network' !== $bundle_type) {
			return new \WP_Error('invalid-network-bundle', __('The uploaded ZIP is not a network import bundle.', 'ultimate-multisite'));
		}

		$manifest = self::get_manifest_from_zip($zip_path);

		if (is_wp_error($manifest)) {
			return $manifest;
		}

		$validation = $this->validate_manifest($manifest);

		if (is_wp_error($validation)) {
			return $validation;
		}

		$selected_blog_ids = $this->normalize_selected_blog_ids($options['selected_blog_ids'] ?? $manifest['included_blog_ids']);

		if (empty($selected_blog_ids)) {
			return new \WP_Error('network-import-no-sites-selected', __('Select at least one site to import.', 'ultimate-multisite'));
		}

		return [
			'imported'           => [],
			'failed'             => [],
			'partial'            => false,
			'selected_blog_ids'  => $selected_blog_ids,
			'user_id_map'        => $this->user_id_map,
			'format_version'     => self::FORMAT_VERSION,
			'orchestration_mode' => 'validated-network-bundle',
		];
	}

	/**
	 * Normalizes selected blog IDs.
	 *
	 * @since 2.5.0
	 *
	 * @param array|string $selected_blog_ids Selected blog IDs.
	 * @return array
	 */
	protected function normalize_selected_blog_ids($selected_blog_ids): array {

		if (is_string($selected_blog_ids)) {
			$selected_blog_ids = explode(',', $selected_blog_ids);
		}

		return array_values(array_unique(array_filter(array_map('intval', (array) $selected_blog_ids))));
	}
}
