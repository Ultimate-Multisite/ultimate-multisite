<?php
/**
 * @package TenUp\MU_Migration
 */
namespace TenUp\MU_Migration\Helpers;

/**
 * Checks if WooCommerce is active.
 *
 * @return bool
 */
function is_woocommerce_active() {
	return in_array(
		'woocommerce/woocommerce.php',
		apply_filters('active_plugins', get_option('active_plugins'))
	);
}

/**
 * Checks if $filename is a zip file by checking it's first few bytes sequence.
 *
 * @param string $filename
 * @return bool
 */
function is_zip_file($filename) {
	$fh = fopen($filename, 'r');

	if ( ! $fh ) {
		return false;
	}

	$blob = fgets($fh, 5);

	fclose($fh);

	if ( strpos($blob, 'PK') !== false ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Parses a url for use in search-replace by removing its protocol.
 *
 * @param string $url
 * @return string
 */
function parse_url_for_search_replace($url) {
	$parsed_url = parse_url(esc_url($url));

	$path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

	return $parsed_url['host'] . $path;
}

/**
 * Recursively removes a directory and its files.
 *
 * @param string $dirPath
 * @param bool   $deleteParent
 */
function delete_folder($dirPath, $deleteParent = true) {
	$limit = 0;

	/*
	 * We may hit the recursion depth,
	 * so let's keep trying until everything has been deleted.
	 *
	 * The limit check avoids infinite loops.
	 */
	while ( file_exists($dirPath) && $limit++ < 10 ) {
		foreach (
			new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			) as $path
		) {
			$path->isFile() ? @unlink($path->getPathname()) : @rmdir($path->getPathname());
		}

		if ( $deleteParent ) {
			rmdir($dirPath);
		}
	}
}

/**
 * Recursively copies a directory and its files.
 *
 * @param string $source
 * @param string $dest
 */
function move_folder($source, $dest) {
	if ( ! file_exists($dest) ) {
		mkdir($dest);
	}

	foreach (
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		) as $item
	) {
		if ( $item->isDir() ) {
			$dir = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
			if ( ! file_exists($dir) ) {
				mkdir($dir);
			}
		} else {
			$dest_file = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
			if ( ! file_exists($dest_file) ) {
				rename($item, $dest_file);
			}
		}
	}
}

/**
 * Retrieves the db prefix based on the $blog_id.
 *
 * @uses wpdb
 *
 * @param int $blog_id
 * @return string
 */
function get_db_prefix($blog_id) {
	global $wpdb;

	if ( $blog_id > 1 ) {
		$new_db_prefix = $wpdb->base_prefix . $blog_id . '_';
	} else {
		$new_db_prefix = $wpdb->prefix;
	}

	return $new_db_prefix;
}

/**
 * Does the same thing that add_user_to_blog does, but without calling switch_to_blog().
 *
 * @param int    $blog_id
 * @param int    $user_id
 * @param string $role
 * @return \WP_Error
 */
function light_add_user_to_blog($blog_id, $user_id, $role) {
	$user = get_userdata($user_id);

	if ( ! $user ) {
		restore_current_blog();
		return new \WP_Error('user_does_not_exist', __('The requested user does not exist.'));
	}

	if ( ! get_user_meta($user_id, 'primary_blog', true) ) {
		update_user_meta($user_id, 'primary_blog', $blog_id);
		$details = get_blog_details($blog_id);
		update_user_meta($user_id, 'source_domain', $details->domain);
	}

	$user->set_role($role);

	/**
	 * Fires immediately after a user is added to a site.
	 *
	 * @since MU
	 *
	 * @param int    $user_id User ID.
	 * @param string $role    User role.
	 * @param int    $blog_id Blog ID.
	 */
	do_action('add_user_to_blog', $user_id, $role, $blog_id);
	wp_cache_delete($user_id, 'users');
	wp_cache_delete($blog_id . '_user_count', 'blog-details');
}

/**
 * Frees up memory for long running processes.
 */
function stop_the_insanity() {
	global $wpdb, $wp_actions, $wp_filter, $wp_object_cache;

	// reset queries
	$wpdb->queries = [];
	// Prevent wp_actions from growing out of control
	$wp_actions = [];

	if ( is_object($wp_object_cache) ) {
		$wp_object_cache->group_ops      = [];
		$wp_object_cache->stats          = [];
		$wp_object_cache->memcache_debug = [];
		$wp_object_cache->cache          = [];

		if ( method_exists($wp_object_cache, '__remoteset') ) {
			$wp_object_cache->__remoteset();
		}
	}

	/*
	 * The WP_Query class hooks a reference to one of its own methods
	 * onto filters if update_post_term_cache or
	 * update_post_meta_cache are true, which prevents PHP's garbage
	 * collector from cleaning up the WP_Query instance on long-
	 * running processes.
	 *
	 * By manually removing these callbacks (often created by things
	 * like get_posts()), we're able to properly unallocate memory
	 * once occupied by a WP_Query object.
	 *
	 */
	if ( isset($wp_filter['get_term_metadata']) ) {
		/*
		 * WordPress 4.7 has a new Hook infrastructure, so we need to make sure
		 * we're accessing the global array properly.
		 */
		if ( class_exists('WP_Hook') && $wp_filter['get_term_metadata'] instanceof \WP_Hook ) {
			$filter_callbacks = &$wp_filter['get_term_metadata']->callbacks;
		} else {
			$filter_callbacks = &$wp_filter['get_term_metadata'];
		}

		if ( isset($filter_callbacks[10]) ) {
			foreach ( $filter_callbacks[10] as $hook => $content ) {
				if ( preg_match('#^[0-9a-f]{32}lazyload_term_meta$#', $hook) ) {
					unset($filter_callbacks[10][ $hook ]);
				}
			}
		}
	}
}

/**
 * Add START TRANSACTION and COMMIT to the sql export.
 * shamelessly stolen from http://stackoverflow.com/questions/1760525/need-to-write-at-beginning-of-file-with-php
 *
 * @param string $orig_filename SQL dump file name.
 */
function addTransaction($orig_filename) {
	$context   = stream_context_create();
	$orig_file = fopen($orig_filename, 'r', 1, $context);

	$temp_filename = tempnam(sys_get_temp_dir(), 'php_prepend_');
	file_put_contents($temp_filename, 'START TRANSACTION;' . PHP_EOL);
	file_put_contents($temp_filename, $orig_file, FILE_APPEND);
	file_put_contents($temp_filename, 'COMMIT;', FILE_APPEND);

	fclose($orig_file);
	unlink($orig_filename);
	rename($temp_filename, $orig_filename);
}

/**
 * Switches to another blog if on Multisite
 *
 * @param $blog_id
 */
function maybe_switch_to_blog($blog_id) {
	if ( is_multisite() ) {
		switch_to_blog($blog_id);
	}
}

/**
 * Restore the current blog if on multisite
 */
function maybe_restore_current_blog() {
	if ( is_multisite() ) {
		restore_current_blog();
	}
}


/**
 * Extracts a zip file to the $dest_dir.
 *
 * Uses PHP's built-in ZipArchive (available since PHP 5.2, required by WP 5.3+).
 *
 * @param string $filename
 * @param string $dest_dir
 */
function extract($filename, $dest_dir) {
	$zip = new \ZipArchive();

	if ( $zip->open($filename) !== true ) {
		return;
	}

	if ( ! file_exists($dest_dir) ) {
		mkdir($dest_dir, 0755, true);
	}

	$zip->extractTo($dest_dir);
	$zip->close();
}

/**
 * Creates a zip file with the provided files/folders.
 *
 * Uses PHP's built-in ZipArchive (available since PHP 5.2, required by WP 5.3+).
 * The $files_to_zip array maps archive entry names (keys) to filesystem paths (values).
 * When key equals value (both are the same filesystem path), the basename is used as
 * the archive entry name (e.g. individual export files like .sql, .csv, .json).
 * When the key is a relative path (e.g. 'wp-content/plugins'), all contents of the
 * filesystem directory (value) are added recursively under that prefix.
 *
 * @param string $zip_file    The path of the zip file to create.
 * @param array  $files_to_zip Archive-entry => filesystem-path pairs.
 *
 * @return bool True on success, false on failure.
 */
function zip($zip_file, $files_to_zip) {
	$files_to_zip = apply_filters('wu_site_exporter_files_to_zip', $files_to_zip);

	$zip = new \ZipArchive();

	if ( $zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true ) {
		return false;
	}

	foreach ( $files_to_zip as $archive_path => $filesystem_path ) {
		if ( is_file($filesystem_path) ) {
			// When key === value both are the full filesystem path; store by basename.
			$entry_name = ( $archive_path === $filesystem_path ) ? basename($filesystem_path) : $archive_path;
			$zip->addFile($filesystem_path, $entry_name);
		} elseif ( is_dir($filesystem_path) ) {
			$filesystem_path  = rtrim($filesystem_path, DIRECTORY_SEPARATOR);
			$base_path_length = strlen($filesystem_path) + 1;

			$dir_iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($filesystem_path, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $dir_iterator as $file_item ) {
				$relative_path = substr($file_item->getPathname(), $base_path_length);
				$zip->addFile($file_item->getPathname(), $archive_path . DIRECTORY_SEPARATOR . $relative_path);
			}
		}
	}

	return $zip->close();
}

/**
 * Run a command within WP_CLI
 *
 * @param string $command     The command to run
 * @param array  $args        The command arguments
 * @param array  $assoc_args  The associative arguments
 * @param array  $global_args The global arguments
 *
 * @return object Result object with stdout, stderr, and return_code properties
 */
function runcommand($command, $args = [], $assoc_args = [], $global_args = []) {
	global $wpdb, $wu_site_exporter_site_id;

	$assoc_args = array_merge($assoc_args, $global_args);

	$transformed_assoc_args = [];

	foreach ( $assoc_args as $key => $arg ) {
		$transformed_assoc_args[] = '--' . $key . '=' . $arg;
	}
	$params = sprintf('%s %s', implode(' ', $args), implode(' ', $transformed_assoc_args));

	$full_command = sprintf('%s %s', $command, $params);

	/**
	 * If we're in CLI context with real WP-CLI available, use it.
	 * Otherwise, use pure PHP implementation for web/AJAX context.
	 */
	if (PHP_SAPI === 'cli' && class_exists('\WP_CLI') && method_exists('\WP_CLI', 'runcommand')) {
		$options = [
			'return'     => 'all',
			'launch'     => false,
			'exit_error' => false,
		];
		return \WP_CLI::runcommand($full_command, $options);
	}

	/**
	 * Web/AJAX context - implement commands using pure PHP
	 * This is the polyfill implementation that works without WP-CLI
	 */
	$stdout = '';

	if (strpos($full_command, 'db tables') === 0) {
		// Get list of tables for the current site
		$sql     = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix) . '%');
		$results = $wpdb->get_col($sql); // phpcs:ignore
		$stdout  = implode(',', $results);
	} elseif (strpos($full_command, 'db export') === 0) {
		// Export database using mysqldump-php
		$table_list = [];
		preg_match('/--tables=(.+)/', $full_command, $table_list);

		$dump = new \Ifsnop\Mysqldump\Mysqldump(
			sprintf('mysql:dbname=%s;host=%s', DB_NAME, DB_HOST),
			DB_USER,
			DB_PASSWORD,
			[
				'compress'       => \Ifsnop\Mysqldump\Mysqldump::NONE,
				'include-tables' => explode(',', $table_list[1]),
			]
		);

		$file_name = str_replace('db export', '', $full_command);
		$file_name = preg_replace('/--tables=(.+)/', '', $file_name);
		$file_name = trim($file_name);

		$dump->start($file_name);
	} elseif (strpos($full_command, 'db import') === 0) {
		// Import database using custom importer
		$file_name = str_replace('db import', '', $full_command);
		$file_name = trim($file_name);

		$import = new \WP_Ultimo\Site_Exporter\Database\Import(
			$file_name,
			DB_USER,
			DB_PASSWORD,
			DB_NAME,
			DB_HOST,
			true, // drop_tables
			true, // force_drop_tables
			$wu_site_exporter_site_id
		);
	}

	return (object) [
		'stdout'      => $stdout,
		'stderr'      => '',
		'return_code' => 0,
	];
}

/**
 * Run a WP-CLI command in the same process (launch_self wrapper)
 *
 * @param string $command        WP-CLI command to call
 * @param array  $args          Positional arguments
 * @param array  $assoc_args    Associative arguments
 * @param bool   $exit_on_error Whether to exit if command returns elevated return code
 * @param bool   $return_detailed Whether to return exit status or detailed results
 * @param array  $runtime_args  Override global args (path, url, user, allow-root)
 *
 * @return int Return code (0 for success)
 */
function launch_self($command, $args = [], $assoc_args = [], $exit_on_error = true, $return_detailed = false, $runtime_args = []) {
	global $wpdb, $wu_site_exporter_site_id;

	/**
	 * If we're in CLI context with real WP-CLI available, use it
	 */
	if (PHP_SAPI === 'cli' && class_exists('\WP_CLI') && method_exists('\WP_CLI', 'launch_self')) {
		return \WP_CLI::launch_self($command, $args, $assoc_args, $exit_on_error, $return_detailed, $runtime_args);
	}

	/**
	 * Web/AJAX context - implement commands using pure PHP
	 */
	if ($command === 'search-replace') {
		$search  = $args[0];
		$replace = $args[1];

		$site = is_multisite() ? get_site($wu_site_exporter_site_id) : (object) [
			'blog_id' => 1,
		];

		if (! $site) {
			return 1;
		}

		$max_execution = new \WP_Ultimo\Site_Exporter\Database\Max_Execution_Time();
		$dbm           = new \WP_Ultimo\Site_Exporter\Database\Manager($wpdb);
		$replacer      = new \WP_Ultimo\Site_Exporter\Database\Replace($dbm, $max_execution);
		$tables        = $dbm->get_tables($site->blog_id);

		$replacer->set_dry_run(false);

		$report = $replacer->run_search_replace($search, $replace, $tables);

		if (is_wp_error($report)) {
			error_log('Site Exporter Error: ' . $report->get_error_message());
			return 1;
		} elseif (count($report['changes']) === 0) {
				error_log('Site Exporter Warning: Search pattern not found');
		}

		return 0;
	} elseif ($command === 'theme enable') {
		if (isset($args[0])) {
			switch_theme($args[0]);
		}
		return 0;
	}

	// Unknown command
	return 1;
}
