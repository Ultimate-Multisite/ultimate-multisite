<?php
/**
 * Exporter Functions
 *
 * Public APIs to Export the sites.
 *
 * @author      Starter Pack
 * @category    Admin
 * @package     WP_Ultimo\Functions
 * @version     2.5.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Exports a sub-site.
 *
 * @since 2.5.0
 *
 * @param int   $site_id The site ID.
 * @param array $options The flags on what to export.
 * @param bool  $async   If we should generate the export file asynchronously.
 * @return \WP_Error|true
 */
function wu_exporter_export(int $site_id, array $options = [], bool $async = false) {

	if ($async) {
		if (! function_exists('wu_enqueue_async_action')) {
			return new \WP_Error('not-enabled', __('The site exporter requires async action support.', 'ultimate-multisite'));
		}

		$hash = wu_exporter_add_pending($site_id, $options, $async);

		wu_enqueue_async_action(
			'wu_export_site',
			[
				'site_id' => $site_id,
				'options' => $options,
				'hash'    => $hash,
			],
			'site-exporter'
		);
	} else {
		/*
		 * For the synchronous path, call the exporter directly so errors can
		 * be captured and returned to the caller. Using do_action_ref_array()
		 * previously discarded the return value, making silent failures invisible.
		 */
		$result = \WP_Ultimo\Site_Exporter\Site_Exporter::get_instance()->handle_site_export($site_id, $options);

		if (is_wp_error($result)) {
			return $result;
		}
	}

	return true;
}

/**
 * Gets a list of all the exports generated to date.
 *
 * @since 2.5.0
 * @return array
 */
function wu_exporter_get_all_exports(): array {

	$path = wu_maybe_create_folder('wu-site-exports');

	$zip_files = glob(trailingslashit($path) . '*.zip');

	if (! $zip_files) {
		return [];
	}

	// Sort by modified time, newest first
	usort(
		$zip_files,
		function ($a, $b) {
			return filemtime($b) - filemtime($a);
		}
	);

	$results  = [];
	$base_url = wu_exporter_get_folder();

	foreach ($zip_files as $filepath) {
		$filename  = basename($filepath);
		$results[] = [
			'file' => $filename,
			'path' => $filepath,
			'date' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), filemtime($filepath)),
			'size' => size_format(filesize($filepath)),
			'url'  => trailingslashit($base_url) . $filename,
		];
	}

	return $results;
}

/**
 * Gets the exporter URL for the folder.
 *
 * @since 2.5.0
 * @return string
 */
function wu_exporter_get_folder(): string {

	return WP_Ultimo()->helper->get_folder_url('wu-site-exports');
}

/**
 * Gets the site object based on the export name.
 *
 * @since 2.5.0
 *
 * @param string $export_name The file name.
 * @return \WP_Ultimo\Models\Site|false
 */
function wu_exporter_get_site_from_export_name(string $export_name) {

	$matches = [];

	preg_match('/wu-site-export-([0-9]+)/', $export_name, $matches);

	$site_id = absint($matches[1] ?? 0);

	return wu_get_site($site_id);
}

/**
 * Saves the time it took to generate the zip.
 *
 * @since 2.5.0
 *
 * @param string $file The export filename.
 * @param float  $time The time it took.
 * @return bool
 */
function wu_exporter_save_generation_time(string $file, float $time): bool {

	$times = wu_get_option('exporter_generation_times', []);

	$times[ $file ] = $time;

	return wu_save_option('exporter_generation_times', $times);
}

/**
 * Get the generated time for a given export.
 *
 * @since 2.5.0
 *
 * @param string $file The file name.
 * @return string
 */
function wu_exporter_get_generation_time(string $file): string {

	$times = wu_get_option('exporter_generation_times', []);

	$time = wu_get_isset($times, $file, false);

	if (false === $time) {
		return __('Time to generate not saved', 'ultimate-multisite');
	}

	$now = time();

	return human_time_diff($now, $now + $time);
}

/**
 * Adds a particular site as pending.
 *
 * @since 2.5.0
 *
 * @param int   $site_id The site ID.
 * @param array $options The flags on what to export.
 * @param bool  $async   If we should generate the export file asynchronously.
 * @return string
 */
function wu_exporter_add_pending(int $site_id, array $options = [], bool $async = false): string {

	$base = [$site_id, $options, $async];

	$hash = md5(serialize($base)); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

	wu_exporter_set_transient("wu_pending_site_export_{$hash}", $site_id, 2 * HOUR_IN_SECONDS);

	return $hash;
}

/**
 * Get pending exports.
 *
 * @since 2.5.0
 * @return array
 */
function wu_exporter_get_pending(): array {

	global $wpdb;

	$table = is_multisite() ? "{$wpdb->base_prefix}sitemeta" : "{$wpdb->base_prefix}options";

	$like = is_multisite() ? '\\_site\\_transient\\_wu\\_pending\\_site\\_export\\_%' : '\\_transient\\_wu\\_pending\\_site\\_export\\_%';

	$query = "SELECT meta_key, meta_value as site_id FROM {$table} WHERE meta_key LIKE '{$like}'";

	return $wpdb->get_results($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
}

/**
 * Decide how to create transients.
 *
 * @since 2.5.0
 *
 * @param string $transient  The transient key.
 * @param mixed  $value      The transient value.
 * @param int    $expiration The expiration period.
 * @return bool
 */
function wu_exporter_set_transient(string $transient, $value, int $expiration = 0): bool {

	global $_wp_using_ext_object_cache;

	$default_wp_using_ext_object_cache = $_wp_using_ext_object_cache;

	$_wp_using_ext_object_cache = false;

	if (is_multisite()) {
		$results = set_site_transient($transient, $value, $expiration);
	} else {
		$results = set_transient($transient, $value, $expiration);
	}

	$_wp_using_ext_object_cache = $default_wp_using_ext_object_cache;

	return $results;
}

/**
 * Decides how to delete transients.
 *
 * @since 2.5.0
 *
 * @param string $transient The transient key.
 * @return bool
 */
function wu_exporter_delete_transient(string $transient): bool {

	global $_wp_using_ext_object_cache;

	$default_wp_using_ext_object_cache = $_wp_using_ext_object_cache;

	$_wp_using_ext_object_cache = false;

	if (is_multisite()) {
		$results = delete_site_transient($transient);
	} else {
		$results = delete_transient($transient);
	}

	$_wp_using_ext_object_cache = $default_wp_using_ext_object_cache;

	return $results;
}

/**
 * Add a plugin or pattern to the exclusion list on the export zips.
 *
 * @since 2.5.0
 *
 * @param string $plugin_or_pattern The plugin name of pattern. E.g.: wp-ultimo or wp-ultimo-*.
 * @return bool
 */
function wu_exporter_exclude_plugin_from_export(string $plugin_or_pattern): bool {

	add_filter(
		'wu_site_exporter_plugin_exclusion_list',
		function ($plugins_or_patterns) use ($plugin_or_pattern) {

			$plugins_or_patterns[] = $plugin_or_pattern;

			return $plugins_or_patterns;
		}
	);

	return true;
}

// --------------------------------------------------------
// Backwards compatibility aliases for deprecated functions
// --------------------------------------------------------

/**
 * Deprecated: Use wu_exporter_export() instead.
 *
 * @deprecated 2.5.0
 *
 * @param int   $site_id The site ID.
 * @param array $options The flags on what to export.
 * @param bool  $async   If we should generate the export file asynchronously.
 * @return \WP_Error|true
 */
function wu_site_exporter_export(int $site_id, array $options = [], bool $async = false) {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_export');

	return wu_exporter_export($site_id, $options, $async);
}

/**
 * Deprecated: Use wu_exporter_get_all_exports() instead.
 *
 * @deprecated 2.5.0
 * @return array
 */
function wu_site_exporter_get_all_exports(): array {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_get_all_exports');

	return wu_exporter_get_all_exports();
}

/**
 * Deprecated: Use wu_exporter_get_folder() instead.
 *
 * @deprecated 2.5.0
 * @return string
 */
function wu_site_exporter_get_folder(): string {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_get_folder');

	return wu_exporter_get_folder();
}

/**
 * Deprecated: Use wu_exporter_get_site_from_export_name() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $export_name The file name.
 * @return \WP_Ultimo\Models\Site|false
 */
function wu_site_exporter_get_site_from_export_name(string $export_name) {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_get_site_from_export_name');

	return wu_exporter_get_site_from_export_name($export_name);
}

/**
 * Deprecated: Use wu_exporter_save_generation_time() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $file The export filename.
 * @param float  $time The time it took.
 * @return bool
 */
function wu_site_exporter_save_generation_time(string $file, float $time): bool {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_save_generation_time');

	return wu_exporter_save_generation_time($file, $time);
}

/**
 * Deprecated: Use wu_exporter_get_generation_time() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $file The file name.
 * @return string
 */
function wu_site_exporter_get_generation_time(string $file): string {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_get_generation_time');

	return wu_exporter_get_generation_time($file);
}

/**
 * Deprecated: Use wu_exporter_add_pending() instead.
 *
 * @deprecated 2.5.0
 *
 * @param int   $site_id The site ID.
 * @param array $options The flags on what to export.
 * @param bool  $async   If we should generate the export file asynchronously.
 * @return string
 */
function wu_site_exporter_add_pending(int $site_id, array $options = [], bool $async = false): string {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_add_pending');

	return wu_exporter_add_pending($site_id, $options, $async);
}

/**
 * Deprecated: Use wu_exporter_get_pending() instead.
 *
 * @deprecated 2.5.0
 * @return array
 */
function wu_site_exporter_get_pending(): array {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_get_pending');

	return wu_exporter_get_pending();
}

/**
 * Deprecated transient function - Use wu_exporter_set_transient() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $transient  The transient key.
 * @param mixed  $value      The transient value.
 * @param int    $expiration The expiration period.
 * @return bool
 */
function wp_ultimo_site_exporter_set_transient(string $transient, $value, int $expiration = 0): bool {

	return wu_exporter_set_transient($transient, $value, $expiration);
}

/**
 * Deprecated transient function - Use wu_exporter_delete_transient() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $transient The transient key.
 * @return bool
 */
function wp_ultimo_site_exporter_delete_transient(string $transient): bool {

	return wu_exporter_delete_transient($transient);
}

/**
 * Deprecated: Use wu_exporter_exclude_plugin_from_export() instead.
 *
 * @deprecated 2.5.0
 *
 * @param string $plugin_or_pattern The plugin name of pattern.
 * @return bool
 */
function wu_site_exporter_exclude_plugin_from_export(string $plugin_or_pattern): bool {

	_deprecated_function(__FUNCTION__, '2.5.0', 'wu_exporter_exclude_plugin_from_export');

	return wu_exporter_exclude_plugin_from_export($plugin_or_pattern);
}
