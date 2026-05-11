<?php
/**
 * Ultimate Multisite — Self-Boot Installer
 *
 * Bundle: {{BUNDLE_TYPE}}
 * Plugin: {{PLUGIN_VERSION}}
 * Exported: {{EXPORT_DATE}}
 * Source: {{SOURCE_URL}}
 *
 * INSTRUCTIONS: Extract the ZIP onto an empty web root and open this file in
 * a browser. The 5-step wizard will install WordPress, activate the plugin,
 * and import the bundle automatically.
 *
 * For manual WP-CLI import, see readme.txt.
 *
 * This file deletes itself on successful completion. Do not modify it.
 *
 * Requirements: PHP 7.4+, MySQL / MariaDB, outbound HTTPS (or bundled core).
 */

// phpcs:disable -- self-contained installer, no WP coding standards apply here.

/**
 * =========================================================================
 * CONSTANTS
 * =========================================================================
 */

define('WU_BOOT_VERSION', '1.0');
define('WU_BOOT_BUNDLE_TYPE', '{{BUNDLE_TYPE}}');
define('WU_BOOT_PLUGIN_VERSION', '{{PLUGIN_VERSION}}');
define('WU_BOOT_EXPORT_DATE', '{{EXPORT_DATE}}');
define('WU_BOOT_SOURCE_URL', '{{SOURCE_URL}}');
define('WU_BOOT_SOURCE_WP_VERSION', '{{SOURCE_WP_VERSION}}');
define('WU_BOOT_SOURCE_PHP_VERSION', '{{SOURCE_PHP_VERSION}}');
define('WU_BOOT_IS_NETWORK', '{{BUNDLE_TYPE}}' === 'network');
define('WU_BOOT_SUBDOMAIN_INSTALL', {{SUBDOMAIN_INSTALL}});

define('WU_BOOT_DIR', __DIR__ . DIRECTORY_SEPARATOR);
define('WU_BOOT_STATE_FILE', WU_BOOT_DIR . '.wu-bootstrap-state.json');
define('WU_BOOT_TOKEN_FILE', WU_BOOT_DIR . '.wu-bootstrap-token');
define('WU_BOOT_LOG_FILE', WU_BOOT_DIR . 'wu-bootstrap-complete.log');
define('WU_BOOT_WP_DIR', WU_BOOT_DIR . 'wordpress' . DIRECTORY_SEPARATOR);
define('WU_BOOT_WPCLI_FILE', WU_BOOT_DIR . 'wp-cli.phar');
define('WU_BOOT_PLUGIN_BUNDLE_DIR', WU_BOOT_DIR . 'wp-ultimate-plugin' . DIRECTORY_SEPARATOR);
define('WU_BOOT_PLUGIN_SLUG', 'ultimate-multisite');

/** Steps in execution order. */
define('WU_BOOT_STEPS', ['db_credentials', 'site_info', 'core_acquired', 'core_installed', 'plugin_installed', 'bundle_imported', 'complete']);

/**
 * =========================================================================
 * BOOTSTRAP CHECKS
 * =========================================================================
 */

// PHP version guard
if (version_compare(PHP_VERSION, '7.4', '<')) {
	wu_boot_fatal(
		'PHP Version Too Old',
		'This installer requires PHP 7.4 or later. Your server is running PHP ' . PHP_VERSION . '.'
	);
}

// Forward-compatibility: refuse if target PHP is lower than source PHP.
if (WU_BOOT_SOURCE_PHP_VERSION && version_compare(PHP_VERSION, WU_BOOT_SOURCE_PHP_VERSION, '<')) {
	wu_boot_fatal(
		'PHP Version Incompatible',
		'This bundle was exported from PHP ' . WU_BOOT_SOURCE_PHP_VERSION . '. '
		. 'Your server is running PHP ' . PHP_VERSION . ' which is older. '
		. 'Upgrade PHP to at least ' . WU_BOOT_SOURCE_PHP_VERSION . ' before continuing.'
	);
}

/**
 * =========================================================================
 * MAIN DISPATCH
 * =========================================================================
 */

$state  = wu_boot_load_state();
$step   = wu_boot_current_step($state);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = preg_replace('/[^a-z_]/', '', $_GET['wu_action'] ?? '');

// Detect existing WP install — refuse to overwrite.
if ('complete' !== $step && wu_boot_is_wp_installed()) {
	wu_boot_render_page(
		'WordPress Already Installed',
		'<div class="wu-error"><h2>WordPress Already Installed</h2>'
		. '<p>A WordPress installation was detected in this directory. '
		. 'The self-boot installer cannot run over an existing installation.</p>'
		. '<p>To import this bundle into an existing Ultimate Multisite network, '
		. 'go to <strong>Ultimate Multisite &rarr; Tools &rarr; Import Site</strong>.</p></div>'
	);
	exit;
}

// If already complete, just show the done screen.
if ('complete' === $step) {
	wu_boot_render_complete($state);
	exit;
}

// Route.
switch ($step) {
	case 'db_credentials':
	case 'site_info':
		if ('POST' === $method) {
			wu_boot_handle_form_post($step, $state);
		} else {
			wu_boot_render_form($step, $state);
		}
		break;

	case 'core_acquired':
	case 'core_installed':
	case 'plugin_installed':
	case 'bundle_imported':
		wu_boot_render_progress_or_run($step, $state, $action);
		break;

	default:
		wu_boot_fatal('Unknown Step', 'Installer reached an unknown state. Delete .wu-bootstrap-state.json and try again.');
}

exit;

/**
 * =========================================================================
 * STATE MANAGEMENT
 * =========================================================================
 */

/**
 * Load installer state from disk.
 */
function wu_boot_load_state(): array {
	if (file_exists(WU_BOOT_STATE_FILE)) {
		$raw = file_get_contents(WU_BOOT_STATE_FILE);
		if ($raw) {
			$data = json_decode($raw, true);
			if (is_array($data)) {
				return $data;
			}
		}
	}
	return [
		'completed_steps' => [],
		'data'            => [],
		'errors'          => [],
	];
}

/**
 * Save installer state to disk.
 */
function wu_boot_save_state(array $state): void {
	file_put_contents(WU_BOOT_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
}

/**
 * Return the first incomplete step name.
 */
function wu_boot_current_step(array $state): string {
	$completed = $state['completed_steps'] ?? [];
	foreach (WU_BOOT_STEPS as $step) {
		if (! in_array($step, $completed, true)) {
			return $step;
		}
	}
	return 'complete';
}

/**
 * Mark a step as completed and persist state.
 */
function wu_boot_mark_step_complete(array &$state, string $step, array $extra_data = []): void {
	if (! in_array($step, $state['completed_steps'], true)) {
		$state['completed_steps'][] = $step;
	}
	foreach ($extra_data as $key => $value) {
		$state['data'][ $key ] = $value;
	}
	wu_boot_save_state($state);
}

/**
 * =========================================================================
 * CSRF PROTECTION
 * =========================================================================
 */

/**
 * Generate and persist a CSRF token.
 */
function wu_boot_generate_csrf_token(): string {
	$token = bin2hex(random_bytes(32));
	file_put_contents(WU_BOOT_TOKEN_FILE, $token);
	if (function_exists('chmod')) {
		chmod(WU_BOOT_TOKEN_FILE, 0600);
	}
	return $token;
}

/**
 * Get the current CSRF token (or generate one if missing).
 */
function wu_boot_get_csrf_token(): string {
	if (file_exists(WU_BOOT_TOKEN_FILE)) {
		$token = trim(file_get_contents(WU_BOOT_TOKEN_FILE));
		if ($token) {
			return $token;
		}
	}
	return wu_boot_generate_csrf_token();
}

/**
 * Verify CSRF token from POST data.
 */
function wu_boot_verify_csrf(): bool {
	if (! file_exists(WU_BOOT_TOKEN_FILE)) {
		return false;
	}
	$stored = trim(file_get_contents(WU_BOOT_TOKEN_FILE) ?: '');
	$posted = trim($_POST['_wu_token'] ?? '');
	if (! $stored || ! $posted) {
		return false;
	}
	return hash_equals($stored, $posted);
}

/**
 * =========================================================================
 * WORDPRESS DETECTION
 * =========================================================================
 */

/**
 * Detect whether WordPress is already installed in this directory.
 * Uses wp-config.php as the primary signal.
 */
function wu_boot_is_wp_installed(): bool {
	// wp-config.php in the same directory as index.php means WP is installed.
	return file_exists(WU_BOOT_DIR . 'wp-config.php');
}

/**
 * =========================================================================
 * FORM HANDLING
 * =========================================================================
 */

/**
 * Handle POST for wizard form steps (db_credentials and site_info).
 */
function wu_boot_handle_form_post(string $step, array $state): void {
	if (! wu_boot_verify_csrf()) {
		wu_boot_render_form($step, $state, 'Security token mismatch. Please refresh the page and try again.');
		return;
	}

	$errors = [];

	if ('db_credentials' === $step) {
		$db_host   = trim($_POST['db_host'] ?? '');
		$db_name   = trim($_POST['db_name'] ?? '');
		$db_user   = trim($_POST['db_user'] ?? '');
		$db_pass   = $_POST['db_pass'] ?? '';
		$db_prefix = trim($_POST['db_prefix'] ?? 'wp_');

		if (! $db_host) {
			$errors[] = 'Database host is required.';
		}
		if (! $db_name) {
			$errors[] = 'Database name is required.';
		}
		if (! $db_user) {
			$errors[] = 'Database username is required.';
		}
		if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $db_prefix)) {
			$errors[] = 'Database prefix must start with a letter or underscore and contain only letters, numbers, and underscores.';
		}

		if (! $errors) {
			// Test the connection.
			$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);
			if (! $conn) {
				$errors[] = 'Could not connect to the database: ' . mysqli_connect_error() . '. Please check your credentials.';
			} else {
				mysqli_close($conn);
			}
		}

		if ($errors) {
			wu_boot_render_form($step, $state, implode(' ', $errors));
			return;
		}

		$state['data']['db_host']   = $db_host;
		$state['data']['db_name']   = $db_name;
		$state['data']['db_user']   = $db_user;
		$state['data']['db_pass']   = $db_pass;
		$state['data']['db_prefix'] = $db_prefix;
		wu_boot_mark_step_complete($state, 'db_credentials');
		header('Location: ' . wu_boot_self_url());
		exit;
	}

	if ('site_info' === $step) {
		$site_url   = trim($_POST['site_url'] ?? '');
		$admin_user = trim($_POST['admin_user'] ?? '');
		$admin_pass = $_POST['admin_pass'] ?? '';
		$admin_email = trim($_POST['admin_email'] ?? '');
		$site_title = trim($_POST['site_title'] ?? 'My Site');
		$network_mode = trim($_POST['network_mode'] ?? (WU_BOOT_SUBDOMAIN_INSTALL ? 'subdomain' : 'subdirectory'));

		if (! $site_url) {
			$errors[] = 'Site URL is required.';
		} elseif (! filter_var($site_url, FILTER_VALIDATE_URL)) {
			$errors[] = 'Site URL does not appear to be a valid URL.';
		}
		if (! $admin_user) {
			$errors[] = 'Admin username is required.';
		}
		if (strlen($admin_pass) < 6) {
			$errors[] = 'Admin password must be at least 6 characters.';
		}
		if (! filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
			$errors[] = 'A valid admin email address is required.';
		}

		if ($errors) {
			wu_boot_render_form($step, $state, implode(' ', $errors));
			return;
		}

		$state['data']['site_url']     = rtrim($site_url, '/');
		$state['data']['admin_user']   = $admin_user;
		$state['data']['admin_pass']   = $admin_pass;
		$state['data']['admin_email']  = $admin_email;
		$state['data']['site_title']   = $site_title;
		$state['data']['network_mode'] = $network_mode;
		wu_boot_mark_step_complete($state, 'site_info');
		header('Location: ' . wu_boot_self_url());
		exit;
	}
}

/**
 * =========================================================================
 * PROGRESS STEPS (core acquisition, WP install, plugin install, import)
 * =========================================================================
 */

/**
 * Render progress page or execute the step, depending on whether the user
 * clicked the action button.
 */
function wu_boot_render_progress_or_run(string $step, array &$state, string $action): void {
	$run = ('run' === $action && wu_boot_verify_csrf());

	if (! $run) {
		// Show "start this step" confirmation page.
		wu_boot_render_step_prompt($step, $state);
		return;
	}

	// Execute the step.
	$error = null;

	switch ($step) {
		case 'core_acquired':
			$error = wu_boot_acquire_core($state);
			break;
		case 'core_installed':
			$error = wu_boot_install_core($state);
			break;
		case 'plugin_installed':
			$error = wu_boot_install_plugin($state);
			break;
		case 'bundle_imported':
			$error = wu_boot_run_import($state);
			break;
	}

	if ($error) {
		$state['errors'][ $step ] = $error;
		wu_boot_save_state($state);
		wu_boot_render_step_error($step, $error, $state);
		return;
	}

	wu_boot_mark_step_complete($state, $step);

	// If this was the last step, finalize.
	if ('bundle_imported' === $step) {
		wu_boot_finalize($state);
		return;
	}

	// Advance to next step.
	header('Location: ' . wu_boot_self_url());
	exit;
}

/**
 * Download (or confirm bundled) WordPress core.
 * Returns null on success, error string on failure.
 */
function wu_boot_acquire_core(array &$state): ?string {
	// If already present, skip download.
	if (file_exists(WU_BOOT_WP_DIR . 'wp-includes' . DIRECTORY_SEPARATOR . 'version.php')) {
		$state['data']['core_source'] = 'bundled';
		return null;
	}

	// Probe connectivity to wordpress.org.
	$wp_version = WU_BOOT_SOURCE_WP_VERSION ?: 'latest';
	$download_url = 'https://wordpress.org/wordpress-' . $wp_version . '.zip';
	if ('latest' === $wp_version) {
		$download_url = 'https://wordpress.org/latest.zip';
	}

	$tmp_zip = WU_BOOT_DIR . 'wordpress-download-' . time() . '.zip';
	$downloaded = wu_boot_download_file($download_url, $tmp_zip);

	if (! $downloaded) {
		// Try with "latest" as fallback.
		if ('latest' !== $wp_version) {
			$downloaded = wu_boot_download_file('https://wordpress.org/latest.zip', $tmp_zip);
		}
		if (! $downloaded) {
			return 'Could not download WordPress from wordpress.org. Check outbound internet access, or bundle core by exporting with "Include WordPress core for offline self-boot" enabled.';
		}
	}

	// Extract zip into wordpress/ subdirectory.
	if (! class_exists('ZipArchive')) {
		@unlink($tmp_zip);
		return 'PHP ZipArchive extension is not available. Cannot extract WordPress core.';
	}

	$zip = new ZipArchive();
	if (true !== $zip->open($tmp_zip)) {
		@unlink($tmp_zip);
		return 'Failed to open downloaded WordPress ZIP.';
	}

	if (! is_dir(WU_BOOT_WP_DIR)) {
		mkdir(WU_BOOT_WP_DIR, 0755, true);
	}

	// The WordPress zip contains a single "wordpress/" folder at root.
	// Extract to a temp dir, then move contents.
	$extract_tmp = WU_BOOT_DIR . 'wp-extract-tmp-' . time() . DIRECTORY_SEPARATOR;
	mkdir($extract_tmp, 0755, true);
	$zip->extractTo($extract_tmp);
	$zip->close();
	@unlink($tmp_zip);

	$wp_extracted = $extract_tmp . 'wordpress' . DIRECTORY_SEPARATOR;
	if (! is_dir($wp_extracted)) {
		wu_boot_rrmdir($extract_tmp);
		return 'Unexpected WordPress ZIP structure. Expected a "wordpress/" folder inside.';
	}

	// Move contents from wp-extract-tmp/wordpress/ to wordpress/.
	wu_boot_rmove($wp_extracted, WU_BOOT_WP_DIR);
	wu_boot_rrmdir($extract_tmp);

	$state['data']['core_source'] = 'downloaded';

	return null;
}

/**
 * Run WordPress core installation.
 * Returns null on success, error string on failure.
 */
function wu_boot_install_core(array &$state): ?string {
	$data = $state['data'];

	$db_host   = $data['db_host'] ?? '';
	$db_name   = $data['db_name'] ?? '';
	$db_user   = $data['db_user'] ?? '';
	$db_pass   = $data['db_pass'] ?? '';
	$db_prefix = $data['db_prefix'] ?? 'wp_';
	$site_url  = $data['site_url'] ?? '';
	$admin_user  = $data['admin_user'] ?? '';
	$admin_pass  = $data['admin_pass'] ?? '';
	$admin_email = $data['admin_email'] ?? '';
	$site_title  = $data['site_title'] ?? 'My Site';

	if (! $site_url || ! $db_host) {
		return 'Missing required installation parameters. Please restart the wizard.';
	}

	// Prefer WP-CLI if available.
	if (file_exists(WU_BOOT_WPCLI_FILE)) {
		return wu_boot_install_core_wpcli($data);
	}

	// PHP-native fallback: generate wp-config.php, load WP, call wp_install().
	return wu_boot_install_core_native($data);
}

/**
 * Install WP core using WP-CLI phar.
 */
function wu_boot_install_core_wpcli(array $data): ?string {
	if (! wu_boot_can_exec()) {
		// Fall through to native.
		return wu_boot_install_core_native($data);
	}

	$db_host   = escapeshellarg($data['db_host']);
	$db_name   = escapeshellarg($data['db_name']);
	$db_user   = escapeshellarg($data['db_user']);
	$db_pass   = escapeshellarg($data['db_pass']);
	$db_prefix = escapeshellarg($data['db_prefix']);
	$site_url  = escapeshellarg($data['site_url']);
	$admin_user  = escapeshellarg($data['admin_user']);
	$admin_pass  = escapeshellarg($data['admin_pass']);
	$admin_email = escapeshellarg($data['admin_email']);
	$site_title  = escapeshellarg($data['site_title']);

	$php      = escapeshellarg(PHP_BINARY);
	$wpcli    = escapeshellarg(WU_BOOT_WPCLI_FILE);
	$wp_path  = escapeshellarg(WU_BOOT_WP_DIR);
	$boot_dir = escapeshellarg(WU_BOOT_DIR);

	// Create wp-config.php.
	$cmd = "$php $wpcli config create"
		. " --dbhost=$db_host --dbname=$db_name --dbuser=$db_user --dbpass=$db_pass --dbprefix=$db_prefix"
		. " --path=$wp_path --extra-php='define(\"WP_DEBUG\", false);'"
		. ' 2>&1';

	$out = wu_boot_exec($cmd);
	if (stripos($out, 'error') !== false) {
		return 'wp-config.php creation failed: ' . $out;
	}

	// Copy wp-config.php to the web root.
	$src_config = WU_BOOT_WP_DIR . 'wp-config.php';
	if (file_exists($src_config)) {
		copy($src_config, WU_BOOT_DIR . 'wp-config.php');
	}

	// Run wp core install.
	$cmd = "$php $wpcli core install"
		. " --url=$site_url --title=$site_title --admin_user=$admin_user --admin_password=$admin_pass --admin_email=$admin_email"
		. " --path=$wp_path --skip-email"
		. ' 2>&1';

	$out = wu_boot_exec($cmd);
	if (stripos($out, 'success') === false && stripos($out, 'already installed') === false) {
		return 'WordPress installation failed: ' . $out;
	}

	if (WU_BOOT_IS_NETWORK) {
		$multisite_type = (WU_BOOT_SUBDOMAIN_INSTALL ? 'subdomains' : 'subdirectories');
		$cmd = "$php $wpcli core multisite-convert --title=$site_title --$multisite_type --path=$wp_path 2>&1";
		$out = wu_boot_exec($cmd);
		if (stripos($out, 'error') !== false) {
			return 'Multisite conversion failed: ' . $out;
		}
	}

	return null;
}

/**
 * PHP-native WP install (no exec/proc_open).
 * Generates wp-config.php and calls wp_install() after bootstrapping WP.
 */
function wu_boot_install_core_native(array $data): ?string {
	$db_host   = $data['db_host'];
	$db_name   = $data['db_name'];
	$db_user   = $data['db_user'];
	$db_pass   = $data['db_pass'];
	$db_prefix = $data['db_prefix'];
	$site_url  = $data['site_url'];
	$admin_user  = $data['admin_user'];
	$admin_pass  = $data['admin_pass'];
	$admin_email = $data['admin_email'];
	$site_title  = $data['site_title'];

	// Generate wp-config.php from the WP sample.
	$sample_config = WU_BOOT_WP_DIR . 'wp-config-sample.php';

	if (! file_exists($sample_config)) {
		return 'wp-config-sample.php not found in wordpress/. Cannot generate wp-config.php.';
	}

	$config = file_get_contents($sample_config);
	$config = str_replace('database_name_here', $db_name, $config);
	$config = str_replace('username_here', $db_user, $config);
	$config = str_replace('password_here', $db_pass, $config);
	$config = str_replace('localhost', $db_host, $config);
	$config = str_replace("'wp_'", "'" . $db_prefix . "'", $config);

	// Generate salts.
	$salts_url = 'https://api.wordpress.org/secret-key/1.1/salt/';
	$salts     = wu_boot_download_string($salts_url);
	if ($salts) {
		// Replace the placeholder block with real salts.
		$config = preg_replace(
			'/define\( \'AUTH_KEY\'.*?define\( \'NONCE_SALT\'.*?\);/s',
			$salts,
			$config
		);
	}

	// Add ABSPATH and table prefix in case they're not in sample.
	if (strpos($config, "define( 'ABSPATH'") === false) {
		$config .= "\nif ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }\n";
	}

	file_put_contents(WU_BOOT_DIR . 'wp-config.php', $config);
	file_put_contents(WU_BOOT_WP_DIR . 'wp-config.php', $config);

	// Bootstrap WordPress.
	$_SERVER['HTTP_HOST']  = parse_url($site_url, PHP_URL_HOST);
	$_SERVER['REQUEST_URI'] = '/';

	$wp_load = WU_BOOT_WP_DIR . 'wp-load.php';
	if (! file_exists($wp_load)) {
		return 'wp-load.php not found in wordpress/. Cannot bootstrap WordPress.';
	}

	require_once $wp_load;

	if (! function_exists('wp_install')) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	if (! function_exists('wp_install')) {
		return 'wp_install() function is unavailable. Cannot complete native installation.';
	}

	$result = wp_install($site_title, $admin_user, $admin_email, false, '', $admin_pass);

	if (is_wp_error($result)) {
		return 'WordPress installation error: ' . $result->get_error_message();
	}

	return null;
}

/**
 * Install the Ultimate Multisite plugin.
 * Returns null on success, error string on failure.
 */
function wu_boot_install_plugin(array &$state): ?string {
	// Determine plugin source.
	$plugin_src = WU_BOOT_PLUGIN_BUNDLE_DIR;
	$use_bundled = is_dir($plugin_src . 'ultimate-multisite.php') || file_exists($plugin_src . 'ultimate-multisite.php');

	// Also accept without trailing slug.
	if (! $use_bundled && file_exists(WU_BOOT_PLUGIN_BUNDLE_DIR . 'ultimate-multisite.php')) {
		$plugin_src  = WU_BOOT_PLUGIN_BUNDLE_DIR;
		$use_bundled = true;
	}

	$wp_plugins_dir = WU_BOOT_WP_DIR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;

	if ($use_bundled) {
		// Copy bundled plugin to wp-content/plugins/.
		$dest = $wp_plugins_dir . WU_BOOT_PLUGIN_SLUG . DIRECTORY_SEPARATOR;
		if (! is_dir($dest)) {
			mkdir($dest, 0755, true);
		}

		wu_boot_rcopy($plugin_src, $dest);
		$state['data']['plugin_source'] = 'bundled';
	} else {
		// Fallback: attempt download from wordpress.org.
		if (! wu_boot_can_exec() || ! file_exists(WU_BOOT_WPCLI_FILE)) {
			return 'Plugin bundle (wp-ultimate-plugin/) not found in ZIP. Cannot install plugin.';
		}

		$php   = escapeshellarg(PHP_BINARY);
		$wpcli = escapeshellarg(WU_BOOT_WPCLI_FILE);
		$slug  = escapeshellarg(WU_BOOT_PLUGIN_SLUG);
		$path  = escapeshellarg(WU_BOOT_WP_DIR);

		$cmd = "$php $wpcli plugin install $slug --activate --path=$path 2>&1";
		$out = wu_boot_exec($cmd);

		if (stripos($out, 'installed successfully') === false && stripos($out, 'already installed') === false) {
			return 'Plugin installation failed: ' . $out;
		}

		$state['data']['plugin_source'] = 'downloaded';
	}

	// Network-activate via WP-CLI if exec is available.
	if (WU_BOOT_IS_NETWORK && wu_boot_can_exec() && file_exists(WU_BOOT_WPCLI_FILE)) {
		$php   = escapeshellarg(PHP_BINARY);
		$wpcli = escapeshellarg(WU_BOOT_WPCLI_FILE);
		$slug  = escapeshellarg(WU_BOOT_PLUGIN_SLUG);
		$path  = escapeshellarg(WU_BOOT_WP_DIR);

		wu_boot_exec("$php $wpcli plugin activate $slug --network --path=$path 2>&1");
	}

	return null;
}

/**
 * Run the bundle import.
 * Returns null on success, error string on failure.
 */
function wu_boot_run_import(array &$state): ?string {
	if (! wu_boot_can_exec() || ! file_exists(WU_BOOT_WPCLI_FILE)) {
		return 'Import requires WP-CLI (wp-cli.phar) and exec()/proc_open(). '
			. 'Neither is available on this server. '
			. 'Please run the import manually using WP-CLI (see readme.txt).';
	}

	$php   = escapeshellarg(PHP_BINARY);
	$wpcli = escapeshellarg(WU_BOOT_WPCLI_FILE);
	$path  = escapeshellarg(WU_BOOT_WP_DIR);

	if (WU_BOOT_IS_NETWORK) {
		// Network import: load network.sql, then per-site mu-migration imports.
		$network_sql = WU_BOOT_DIR . 'network.sql';
		if (file_exists($network_sql)) {
			$sql_file = escapeshellarg($network_sql);
			$cmd      = "$php $wpcli db import $sql_file --path=$path 2>&1";
			$out      = wu_boot_exec($cmd);
			if (stripos($out, 'error') !== false && stripos($out, 'success') === false) {
				return 'Network SQL import failed: ' . $out;
			}
		}

		// Import per-site bundles under sites/.
		$sites_dir = WU_BOOT_DIR . 'sites' . DIRECTORY_SEPARATOR;
		if (is_dir($sites_dir)) {
			foreach (glob($sites_dir . '*.zip') ?: [] as $site_zip) {
				$site_zip_arg = escapeshellarg($site_zip);
				$cmd = "$php $wpcli mu-migration import all $site_zip_arg --path=$path 2>&1";
				$out = wu_boot_exec($cmd);
				if (stripos($out, 'error') !== false) {
					// Non-fatal; log and continue.
					$state['errors'][ 'import_' . basename($site_zip) ] = $out;
				}
			}
		}
	} else {
		// Single-site import: find the site zip (not this file).
		$zips = glob(WU_BOOT_DIR . 'wu-site-export-*.zip') ?: [];
		if (empty($zips)) {
			return 'No site export ZIP found in the bundle directory.';
		}

		$site_zip = escapeshellarg($zips[0]);
		$cmd      = "$php $wpcli mu-migration import all $site_zip --path=$path 2>&1";
		$out      = wu_boot_exec($cmd);

		if (stripos($out, 'error') !== false && stripos($out, 'success') === false) {
			return 'Site import failed: ' . $out;
		}
	}

	return null;
}

/**
 * =========================================================================
 * FINALIZATION
 * =========================================================================
 */

/**
 * Remove installer files and redirect to wp-admin on success.
 */
function wu_boot_finalize(array &$state): void {
	// Write audit log first.
	$log  = 'Ultimate Multisite Self-Boot Install Log' . "\n";
	$log .= str_repeat('=', 50) . "\n";
	$log .= 'Completed: ' . date('Y-m-d H:i:s') . "\n";
	$log .= 'Bundle type: ' . WU_BOOT_BUNDLE_TYPE . "\n";
	$log .= 'Plugin version: ' . WU_BOOT_PLUGIN_VERSION . "\n";
	$log .= 'Source URL: ' . WU_BOOT_SOURCE_URL . "\n";
	$log .= 'Steps completed: ' . implode(', ', $state['completed_steps']) . "\n";
	if (! empty($state['errors'])) {
		$log .= 'Non-fatal errors: ' . json_encode($state['errors']) . "\n";
	}
	file_put_contents(WU_BOOT_LOG_FILE, $log);

	// Mark as complete in state.
	$state['completed_steps'][] = 'complete';
	wu_boot_save_state($state);

	// Remove installer files (keep log + source ZIP for operator).
	@unlink(WU_BOOT_DIR . 'index.php');
	@unlink(WU_BOOT_DIR . 'readme.txt');
	@unlink(WU_BOOT_TOKEN_FILE);

	$admin_url = ($state['data']['site_url'] ?? '') . '/wp-admin/';
	wu_boot_render_complete($state, $admin_url);
}

/**
 * =========================================================================
 * RENDERING
 * =========================================================================
 */

/**
 * Render the appropriate wizard form step.
 */
function wu_boot_render_form(string $step, array $state, string $error = ''): void {
	$token = wu_boot_get_csrf_token();
	$data  = $state['data'] ?? [];

	switch ($step) {
		case 'db_credentials':
			wu_boot_render_page(
				'Step 1: Database Credentials',
				wu_boot_form_db($token, $data, $error)
			);
			break;
		case 'site_info':
			wu_boot_render_page(
				'Step 2: Site Information',
				wu_boot_form_site($token, $data, $error)
			);
			break;
	}
}

/**
 * Render the DB credentials form.
 */
function wu_boot_form_db(string $token, array $data, string $error): string {
	$h = '';
	if ($error) {
		$h .= '<p class="wu-error-msg">' . htmlspecialchars($error) . '</p>';
	}
	$h .= '<form method="post" action="">';
	$h .= wu_boot_hidden('_wu_token', $token);
	$h .= wu_boot_field('Database Host', 'db_host', $data['db_host'] ?? 'localhost', 'localhost');
	$h .= wu_boot_field('Database Name', 'db_name', $data['db_name'] ?? '', 'wordpress');
	$h .= wu_boot_field('Database Username', 'db_user', $data['db_user'] ?? '', 'root');
	$h .= wu_boot_field('Database Password', 'db_pass', $data['db_pass'] ?? '', '', 'password');
	$h .= wu_boot_field('Table Prefix', 'db_prefix', $data['db_prefix'] ?? 'wp_', 'wp_');
	$h .= '<p><button type="submit" class="wu-btn">Next: Site Information &rarr;</button></p>';
	$h .= '</form>';
	return $h;
}

/**
 * Render the site information form.
 */
function wu_boot_form_site(string $token, array $data, string $error): string {
	$h = '';
	if ($error) {
		$h .= '<p class="wu-error-msg">' . htmlspecialchars($error) . '</p>';
	}

	$source_url = WU_BOOT_SOURCE_URL ?: 'https://example.com';
	$h .= '<form method="post" action="">';
	$h .= wu_boot_hidden('_wu_token', $token);
	$h .= wu_boot_field('Site URL', 'site_url', $data['site_url'] ?? '', $source_url);
	$h .= wu_boot_field('Site Title', 'site_title', $data['site_title'] ?? 'My Site', 'My Site');
	$h .= wu_boot_field('Admin Username', 'admin_user', $data['admin_user'] ?? 'admin', 'admin');
	$h .= wu_boot_field('Admin Password', 'admin_pass', $data['admin_pass'] ?? '', '', 'password');
	$h .= wu_boot_field('Admin Email', 'admin_email', $data['admin_email'] ?? '', 'admin@example.com', 'email');

	if (WU_BOOT_IS_NETWORK) {
		$default_mode = WU_BOOT_SUBDOMAIN_INSTALL ? 'subdomain' : 'subdirectory';
		$selected_sub = ($data['network_mode'] ?? $default_mode) === 'subdomain' ? ' selected' : '';
		$selected_dir = ($data['network_mode'] ?? $default_mode) === 'subdirectory' ? ' selected' : '';
		$h .= '<div class="wu-field"><label for="network_mode">Network Mode</label>'
			. '<select id="network_mode" name="network_mode">'
			. '<option value="subdomain"' . $selected_sub . '>Subdomain (site1.example.com)</option>'
			. '<option value="subdirectory"' . $selected_dir . '>Subdirectory (example.com/site1)</option>'
			. '</select></div>';
	}

	$h .= '<p><button type="submit" class="wu-btn">Next: Acquire WordPress Core &rarr;</button></p>';
	$h .= '</form>';
	return $h;
}

/**
 * Render a step-confirmation page (before running an action).
 */
function wu_boot_render_step_prompt(string $step, array $state): void {
	$token = wu_boot_get_csrf_token();
	$labels = [
		'core_acquired'  => ['Step 3: Acquire WordPress Core', 'Start: Download / Detect Core'],
		'core_installed' => ['Step 4: Install WordPress', 'Start: Install WordPress'],
		'plugin_installed' => ['Step 5: Install Plugin', 'Start: Install Ultimate Multisite'],
		'bundle_imported'  => ['Step 6: Import Bundle', 'Start: Import Bundle'],
	];

	[$title, $btn] = $labels[ $step ] ?? ['Processing', 'Run Step'];

	$url = wu_boot_self_url() . '?wu_action=run';

	$body = '<p>Click the button below to begin this step. It may take a few minutes.</p>';
	$body .= '<form method="post" action="' . htmlspecialchars($url) . '">';
	$body .= wu_boot_hidden('_wu_token', $token);
	$body .= '<p><button type="submit" class="wu-btn">' . htmlspecialchars($btn) . '</button></p>';
	$body .= '</form>';

	wu_boot_render_page($title, $body);
}

/**
 * Render a step-failure page.
 */
function wu_boot_render_step_error(string $step, string $error, array $state): void {
	$token = wu_boot_get_csrf_token();
	$url   = wu_boot_self_url() . '?wu_action=run';

	$body  = '<div class="wu-error"><h3>Step failed</h3><p>' . htmlspecialchars($error) . '</p></div>';
	$body .= '<form method="post" action="' . htmlspecialchars($url) . '">';
	$body .= wu_boot_hidden('_wu_token', $token);
	$body .= '<p><button type="submit" class="wu-btn">Try Again</button></p>';
	$body .= '</form>';

	wu_boot_render_page('Error in ' . ucwords(str_replace('_', ' ', $step)), $body);
}

/**
 * Render the completion page.
 */
function wu_boot_render_complete(array $state, string $admin_url = ''): void {
	$body  = '<div class="wu-success"><h2>&#10003; Installation Complete!</h2>';
	$body .= '<p>WordPress, the Ultimate Multisite plugin, and your bundle have been successfully installed.</p>';
	if ($admin_url) {
		$body .= '<p><a class="wu-btn" href="' . htmlspecialchars($admin_url) . '">Go to WordPress Admin &rarr;</a></p>';
	}
	$body .= '<p><small>An audit log has been saved as <code>wu-bootstrap-complete.log</code>.</small></p>';
	$body .= '</div>';

	wu_boot_render_page('Installation Complete', $body);
}

/**
 * Render a full HTML page.
 */
function wu_boot_render_page(string $title, string $body): void {
	$bundle_label = 'single-site' === WU_BOOT_BUNDLE_TYPE ? 'Single-Site' : 'Network';

	echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
	echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
	echo '<title>' . htmlspecialchars($title) . ' — Ultimate Multisite Installer</title>';
	echo '<style>';
	echo 'body{font-family:sans-serif;margin:0;background:#f0f0f1;color:#1d2327}';
	echo '.wu-wrap{max-width:640px;margin:40px auto;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:24px 32px}';
	echo 'h1{font-size:1.2em;margin:0 0 4px;color:#1d2327}.wu-sub{font-size:.85em;color:#646970;margin:0 0 20px}';
	echo 'h2{font-size:1.1em;margin-top:0}.wu-field{margin-bottom:16px}';
	echo '.wu-field label{display:block;font-weight:600;margin-bottom:4px;font-size:.9em}';
	echo '.wu-field input,.wu-field select{width:100%;padding:8px;border:1px solid #8c8f94;border-radius:3px;font-size:.9em;box-sizing:border-box}';
	echo '.wu-btn{background:#0071a1;color:#fff;border:none;padding:10px 18px;border-radius:3px;cursor:pointer;font-size:.9em;text-decoration:none;display:inline-block}';
	echo '.wu-btn:hover{background:#005a86}.wu-error-msg,.wu-error{background:#fcf0f1;border-left:4px solid #d63638;padding:8px 12px;margin-bottom:16px;border-radius:0 3px 3px 0}';
	echo '.wu-success{background:#edfaef;border-left:4px solid #00a32a;padding:8px 12px;border-radius:0 3px 3px 0}';
	echo 'code{background:#f0f0f1;padding:2px 5px;border-radius:3px;font-size:.85em}';
	echo '</style></head><body>';
	echo '<div class="wu-wrap">';
	echo '<h1>Ultimate Multisite — Self-Boot Installer</h1>';
	echo '<p class="wu-sub">Bundle: ' . htmlspecialchars($bundle_label) . ' &bull; Plugin ' . htmlspecialchars(WU_BOOT_PLUGIN_VERSION) . ' &bull; Exported: ' . htmlspecialchars(WU_BOOT_EXPORT_DATE) . '</p>';
	echo $body;
	echo '</div></body></html>';
}

/**
 * Render a fatal-error page and exit.
 */
function wu_boot_fatal(string $title, string $message): void {
	wu_boot_render_page($title, '<div class="wu-error"><h2>' . htmlspecialchars($title) . '</h2><p>' . htmlspecialchars($message) . '</p></div>');
	exit(1);
}

/**
 * =========================================================================
 * UTILITY HELPERS
 * =========================================================================
 */

/** Current installer URL (without query string). */
function wu_boot_self_url(): string {
	$scheme = (! empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS']) ? 'https' : 'http';
	$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$path   = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
	return $scheme . '://' . $host . $path;
}

/** Render a hidden input. */
function wu_boot_hidden(string $name, string $value): string {
	return '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '">';
}

/** Render a labelled form field. */
function wu_boot_field(string $label, string $name, string $value, string $placeholder = '', string $type = 'text'): string {
	return '<div class="wu-field"><label for="' . htmlspecialchars($name) . '">' . htmlspecialchars($label) . '</label>'
		. '<input type="' . htmlspecialchars($type) . '" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '"'
		. ' value="' . htmlspecialchars($value) . '"'
		. ' placeholder="' . htmlspecialchars($placeholder) . '"'
		. '></div>';
}

/** Download a file to disk. Returns true on success. */
function wu_boot_download_file(string $url, string $dest): bool {
	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		$fp = fopen($dest, 'w');
		if (! $fp) {
			return false;
		}
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_exec($ch);
		$ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
		curl_close($ch);
		fclose($fp);
		if (! $ok) {
			@unlink($dest);
		}
		return $ok;
	}

	// Fallback to file_get_contents with context.
	$context = stream_context_create([
		'http' => [
			'timeout'         => 300,
			'follow_location' => 1,
		],
		'ssl' => [
			'verify_peer'      => true,
			'verify_peer_name' => true,
		],
	]);

	$content = @file_get_contents($url, false, $context);
	if (false === $content) {
		return false;
	}
	return false !== file_put_contents($dest, $content);
}

/** Download a URL as a string. Returns the body or empty string. */
function wu_boot_download_string(string $url): string {
	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		$body = curl_exec($ch);
		curl_close($ch);
		return is_string($body) ? $body : '';
	}
	$result = @file_get_contents($url);
	return is_string($result) ? $result : '';
}

/** Whether exec() or proc_open() are usable. */
function wu_boot_can_exec(): bool {
	$disabled = (string) ini_get('disable_functions');
	$disabled = array_map('trim', explode(',', $disabled));
	if (in_array('exec', $disabled, true) && in_array('proc_open', $disabled, true)) {
		return false;
	}
	return (function_exists('exec') || function_exists('proc_open'));
}

/** Execute a shell command; returns combined stdout+stderr. */
function wu_boot_exec(string $cmd): string {
	if (function_exists('exec')) {
		$output = [];
		exec($cmd, $output);
		return implode("\n", $output);
	}

	if (function_exists('proc_open')) {
		$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		if (! is_resource($proc)) {
			return '';
		}
		$out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($proc);
		return (string) $out;
	}

	return '';
}

/** Recursively delete a directory. */
function wu_boot_rrmdir(string $dir): void {
	if (! is_dir($dir)) {
		return;
	}
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($files as $file) {
		if ($file->isDir()) {
			rmdir($file->getRealPath());
		} else {
			unlink($file->getRealPath());
		}
	}
	rmdir($dir);
}

/** Move directory contents (not the directory itself) to another directory. */
function wu_boot_rmove(string $src, string $dst): void {
	$src = rtrim($src, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	$dst = rtrim($dst, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

	if (! is_dir($dst)) {
		mkdir($dst, 0755, true);
	}

	$items = scandir($src);
	foreach ($items as $item) {
		if ('.' === $item || '..' === $item) {
			continue;
		}
		rename($src . $item, $dst . $item);
	}
}

/** Recursively copy a directory. */
function wu_boot_rcopy(string $src, string $dst): void {
	$src = rtrim($src, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	$dst = rtrim($dst, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

	if (! is_dir($dst)) {
		mkdir($dst, 0755, true);
	}

	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($it as $item) {
		$dest = $dst . $it->getSubPathName();
		if ($item->isDir()) {
			if (! is_dir($dest)) {
				mkdir($dest, 0755, true);
			}
		} else {
			copy($item->getRealPath(), $dest);
		}
	}
}
