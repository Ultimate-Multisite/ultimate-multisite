<?php
/**
 * Importer Functions
 *
 * Public APIs to Import the sites.
 *
 * @author      Starter Pack
 * @category    Admin
 * @package     WP_Ultimo\Functions
 * @version     2.5.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Imports a site into the network or as a main site.
 *
 * @since 2.5.0
 *
 * @param string $file_name The zip file name.
 * @param array  $options   The flags on what to import.
 * @param bool   $async     If we should generate the import file asynchronously.
 * @return \WP_Error|true
 */
function wu_exporter_import(string $file_name, array $options = [], bool $async = true) {

	if ($async) {
		$hash = wu_exporter_add_pending_import($file_name, $options, $async);

		if (is_wp_error($hash)) {
			return $hash;
		}
	} else {
		do_action_ref_array(
			'wu_import_site',
			[
				'file_name' => $file_name,
				'options'   => $options,
			],
			'site-import'
		);
	}

	return true;
}

/**
 * Adds a particular site import as pending.
 *
 * @since 2.5.0
 *
 * @param string $file_name The zip file name.
 * @param array  $options   The flags on what to import.
 * @param bool   $async     Reserved for future use.
 * @return string|\WP_Error
 */
function wu_exporter_add_pending_import(string $file_name, array $options = [], bool $async = false) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

	if (! file_exists($file_name) || ! in_array(mime_content_type($file_name), ['application/zip', 'application/x-gzip'], true)) {
		return new \WP_Error('invalid-type', __('File does not exists or it has an invalid mime-type.', 'ultimate-multisite'));
	}

	$base = [
		$file_name,
		$options,
	];

	$hash = md5(serialize($base)); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

	$base[] = $hash;

	wu_exporter_set_transient("wu_pending_site_import_{$hash}", $base, 2 * HOUR_IN_SECONDS);

	return $hash;
}

/**
 * Get pending imports.
 *
 * @since 2.5.0
 * @return array
 */
function wu_exporter_get_pending_imports(): array {

	global $wpdb;

	if (is_multisite()) {
		$table = "{$wpdb->base_prefix}sitemeta";
		$like  = $wpdb->esc_like('_site_transient_wu_pending_site_import_') . '%';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->base_prefix, not user input.
		$query = $wpdb->prepare("SELECT meta_key, meta_value as options FROM {$table} WHERE meta_key LIKE %s", $like);
	} else {
		$table = "{$wpdb->base_prefix}options";
		$like  = $wpdb->esc_like('_transient_wu_pending_site_import_') . '%';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->base_prefix, not user input.
		$query = $wpdb->prepare("SELECT option_name, option_value as options FROM {$table} WHERE option_name LIKE %s", $like);
	}

	$results = $wpdb->get_results($query); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$results = array_map(
		function ($item) {

			$item->options = maybe_unserialize($item->options);

			return $item;
		},
		$results
	);

	return $results;
}

/**
 * Saves the time it took to import the zip.
 *
 * @since 2.5.0
 *
 * @param string $file The import filename.
 * @param float  $time The time it took.
 * @return bool
 */
function wu_exporter_save_import_time(string $file, float $time): bool {

	$times = wu_get_option('exporter_import_times', []);

	$times[ $file ] = $time;

	return wu_save_option('exporter_import_times', $times);
}

/**
 * Converts a file URL to a file path
 *
 * @since 2.5.0
 *
 * @param string $url The file URL.
 * @return string
 */
function wu_exporter_url_to_path(string $url): string {

	$path = str_replace(set_url_scheme(site_url('/'), 'https'), ABSPATH, set_url_scheme($url, 'https'));

	if (file_exists($path)) {
		return $path;
	}

	return get_attached_file(attachment_url_to_postid($url));
}

/**
 * Get the site from the new url.
 *
 * @since 2.5.0
 *
 * @param string $url The file url.
 * @return \WP_Site|object|false
 */
function wu_exporter_url_to_site(string $url) {

	$parsed = wp_parse_url($url);

	if (! isset($parsed['host'])) {
		return false;
	}

	$site = wu_exporter_maybe_get_site_by_path($parsed['host'], $parsed['path'] ?? '');

	return $site;
}

/**
 * Gets a site by domain and path.
 *
 * @since 2.5.0
 *
 * @param string $domain The site domain.
 * @param string $path   The site path.
 * @return \WP_Site|object|false
 */
function wu_exporter_maybe_get_site_by_path(string $domain, string $path) {

	if (is_multisite()) {
		return get_site_by_path($domain, $path);
	} else {
		return (object) [
			'blog_id' => 1,
		];
	}
}

// --------------------------------------------------------
// Backwards compatibility aliases for deprecated functions
// --------------------------------------------------------

/**
 * Deprecated: Use wu_exporter_import() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $file_name The zip file name.
 * @param array  $options   The flags on what to import.
 * @param bool   $async     If we should generate the import file asynchronously.
 * @return \WP_Error|true
 */
function wu_site_exporter_import(string $file_name, array $options = [], bool $async = true) {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_import');

	return wu_exporter_import($file_name, $options, $async);
}

/**
 * Deprecated: Use wu_exporter_add_pending_import() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $file_name The zip file name.
 * @param array  $options   The flags on what to import.
 * @param bool   $async     If we should generate the import file asynchronously.
 * @return string|\WP_Error
 */
function wu_site_exporter_add_pending_import(string $file_name, array $options = [], bool $async = false) {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_add_pending_import');

	return wu_exporter_add_pending_import($file_name, $options, $async);
}

/**
 * Deprecated: Use wu_exporter_get_pending_imports() instead.
 *
 * @deprecated 2.5.0
 * @return array
 */
function wu_site_exporter_get_pending_imports(): array {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_get_pending_imports');

	return wu_exporter_get_pending_imports();
}

/**
 * Deprecated: Use wu_exporter_save_import_time() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $file The import filename.
 * @param float  $time The time it took.
 * @return bool
 */
function wu_site_exporter_save_import_time(string $file, float $time): bool {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_save_import_time');

	return wu_exporter_save_import_time($file, $time);
}

/**
 * Deprecated: Use wu_exporter_url_to_path() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $url The file URL.
 * @return string
 */
function wu_site_exporter_url_to_path(string $url): string {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_url_to_path');

	return wu_exporter_url_to_path($url);
}

/**
 * Deprecated: Use wu_exporter_url_to_site() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $url The file url.
 * @return \WP_Site|object|false
 */
function wu_site_exporter_url_to_site(string $url) {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_url_to_site');

	return wu_exporter_url_to_site($url);
}

/**
 * Deprecated: Use wu_exporter_maybe_get_site_by_path() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $domain The site domain.
 * @param string $path   The site path.
 * @return \WP_Site|object|false
 */
function wp_ultimo_site_exporter_maybe_get_site_by_path(string $domain, string $path) {

	return wu_exporter_maybe_get_site_by_path($domain, $path);
}
