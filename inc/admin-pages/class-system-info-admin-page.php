<?php
/**
 * Ultimate Multisite System Info Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

use WP_Ultimo\Logger;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite System Info Admin Page.
 */
class System_Info_Admin_Page extends Base_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-system-info';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * If this is a submenu, we need a parent menu to attach this to
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $parent = 'none';

	/**
	 * Allows us to highlight another menu page, if this page has no parent page at all.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $highlight_menu_slug = 'wp-ultimo-settings';

	/**
	 * If this number is greater than 0, a badge with the number will be displayed alongside the menu title
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $badge_count = 0;

	/**
	 * Holds the admin panels where this page should be displayed, as well as which capability to require.
	 *
	 * To add a page to the regular admin (wp-admin/), use: 'admin_menu' => 'capability_here'
	 * To add a page to the network admin (wp-admin/network), use: 'network_admin_menu' => 'capability_here'
	 * To add a page to the user (wp-admin/user) admin, use: 'user_admin_menu' => 'capability_here'
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $supported_panels = [
		'network_admin_menu' => 'manage_network',
	];

	/**
	 * Allow child classes to add further initializations.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function init(): void {

		add_action('wp_ajax_wu_generate_text_file_system_info', [$this, 'generate_text_file_system_info']);
	}

	/**
	 * Allow child classes to register scripts and styles that can be loaded on the output function, for example.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_scripts(): void {

		wp_enqueue_script('dashboard');
		wp_enqueue_script('clipboard');
		wp_enqueue_style('wu-system-info', wu_get_asset('system-info.css', 'css'), [], wu_get_version());

		wp_add_inline_script('clipboard', 'var clipboard = new ClipboardJS(".btn");');
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		$screen = get_current_screen();

		foreach ($this->get_data() as $name_type => $data) {
			add_meta_box(
				'wp-table-system-info-' . sanitize_title($name_type),
				$name_type,
				function () use ($data) {

					$this->output_table_system_info($data);
				},
				$screen->id,
				'normal',
				null
			);
		}
	}

	/**
	 * Display system info Table
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Data.
	 * @return void
	 */
	public function output_table_system_info($data): void {

		$screen = get_current_screen();

		wu_get_template(
			'system-info/system-info-table',
			[
				'data'   => $data,
				'screen' => $screen,
			]
		);
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('System Info', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('System Info', 'ultimate-multisite');
	}

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return __('Dashboard', 'ultimate-multisite');
	}

	/**
	 * Every child class should implement the output method to display the contents of the page.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function output(): void {

		$screen = get_current_screen();

		wu_get_template(
			'system-info/system-info',
			[
				'data'   => $this->get_data(),
				'screen' => $screen,
			]
		);
	}

	/**
	 * Get data for system info
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_data() {

		global $wp_filesystem, $wpdb;

		$pad_spaces = 45;

		$theme   = wp_get_theme();
		$browser = $this->get_browser();

		$plugins                  = $this->get_all_plugins();
		$active_plugins           = $this->get_active_plugins();
		$active_plugins_main_site = $this->get_active_plugins_on_main_site();

		$memory_limit = (int) str_replace('M', '', ini_get('memory_limit'));
		$memory_usage = $this->get_memory_usage();

		// translators: %s is the number of seconds.
		$max_execution_time = sprintf(__('%s seconds', 'ultimate-multisite'), ini_get('max_execution_time'));

		$all_options            = $this->get_all_options();
		$all_options_serialized = serialize($all_options); // phpcs:ignore
		$all_options_bytes      = round(mb_strlen($all_options_serialized, '8bit') / 1024, 2);
		$all_options_transients = $this->get_transients_in_options($all_options);

		$array_active_plugins = [];

		$array_constants_options = [
			'SAVEQUERIES',
			'WP_DEBUG',
			'WP_DEBUG_DISPLAY',
			'WP_DEBUG_LOG',
			'WP_DISABLE_FATAL_ERROR_HANDLER',
			'SCRIPT_DEBUG',
			'WP_ENV',
			'NOBLOGREDIRECT',
		];

		$array_constants = [];

		foreach ($array_constants_options as $constant) {
			$array_constants[] = [
				'tooltip' => '',
				'title'   => $constant,
				'value'   => defined($constant) ? (is_bool(constant($constant)) ? __('Enabled', 'ultimate-multisite') : constant($constant)) : __('Disabled', 'ultimate-multisite'),
			];
		}

		foreach ($plugins as $plugin_path => $plugin) {
			if (in_array($plugin_path, array_keys($active_plugins), true)) {
				$plugin_uri = '';

				if (isset($plugin['PluginURI'])) {
					$plugin_uri = ' (' . $plugin['PluginURI'] . ')';
				}

				$array_active_plugins[] = [
					'tooltip' => '',
					'title'   => $plugin['Name'],
					'value'   => $plugin['Version'] . $plugin_uri,
				];
			}
		}

		$array_active_plugins_main_site = [];

		foreach ($plugins as $plugin_path => $plugin) {
			if (in_array($plugin_path, $active_plugins_main_site, true)) {
				$plugin_uri = '';

				if (isset($plugin['PluginURI'])) {
					$plugin_uri = ' (' . $plugin['PluginURI'] . ')';
				}

				$array_active_plugins_main_site[] = [
					'tooltip' => '',
					'title'   => $plugin['Name'],
					'value'   => $plugin['Version'] . $plugin_uri,
				];
			}
		}

		$wpultimo_settings = [];

		foreach ($this->get_all_wp_ultimo_settings() as $setting => $value) {
			if (is_array($value)) {
				continue;
			}

			$wpultimo_settings[ $setting ] = [
				'tooltip' => '',
				'title'   => $setting,
				'value'   => $value,
			];
		}

		$array_wu_tables = [];

		foreach ($wpdb->ms_global_tables as $key => $value) {
			if (str_starts_with((string) $value, 'wu_') && ! array_key_exists($value, $array_wu_tables)) {
				$array_wu_tables[ $value ] = [
					'tooltip' => '',
					'title'   => $value,
					'value'   => get_network_option(null, "wpdb_{$value}_version"),
				];
			}
		}

		return apply_filters(
			'wu_system_info_data',
			[
				'WordPress and System Settings'      => [
					'wp-ultimo-version'      => [
						'tooltip' => 'Ultimate Multisite current version installed locally',
						'title'   => 'Ultimate Multisite Version',
						'value'   => wu_get_version(),
					],
					'wordpress-version'      => [
						'tooltip' => '',
						'title'   => 'WordPress Version',
						'value'   => get_bloginfo('version'),
					],
					'php-version'            => [
						'tooltip' => '',
						'title'   => 'PHP Version',
						'value'   => PHP_VERSION,
					],
					'mysql-version'          => [
						'tooltip' => '',
						'title'   => 'MySQL Version ',
						'value'   => $wpdb->db_version(),
					],
					'web-server'             => [
						'tooltip' => '',
						'title'   => 'Web Server',
						'value'   => sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'] ?? '')),
					],
					'wordpress-url'          => [
						'tooltip' => '',
						'title'   => 'WordPress URL',
						'value'   => get_bloginfo('wpurl'),
					],
					'home-url'               => [
						'tooltip' => '',
						'title'   => 'Home URL',
						'value'   => get_bloginfo('url'),
					],
					'content-directory'      => [
						'tooltip' => '',
						'title'   => 'Content Directory',
						'value'   => WP_CONTENT_DIR, // This is acceptable for system info display
					],
					'content-url'            => [
						'tooltip' => '',
						'title'   => 'Content URL',
						'value'   => content_url(),
					],
					'plugins-directory'      => [
						'tooltip' => '',
						'title'   => 'Plugins Directory',
						'value'   => WP_PLUGIN_DIR, // This is acceptable for system info display
					],
					'pluguins-url'           => [
						'tooltip' => '',
						'title'   => 'Plugins URL',
						'value'   => plugins_url(),
					],
					'uploads-directory'      => [
						'tooltip' => '',
						'title'   => 'Uploads Directory',
						'value'   => wp_upload_dir()['basedir'],
					],
					'cookie-domain'          => [
						'tooltip' => '',
						'title'   => 'Cookie Domain',
						'value'   => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN ?: __('Disabled', 'ultimate-multisite') : __('Not set', 'ultimate-multisite'),
					],
					'multisite-active'       => [
						'tooltip' => '',
						'title'   => 'Multi-Site Active',
						'value'   => is_multisite() ? __('Yes', 'ultimate-multisite') : __('No', 'ultimate-multisite'),
					],
					'php-current-time-gmt'   => [
						'tooltip' => '',
						'title'   => 'PHP Current Time - GMT',
						'value'   => wu_get_current_time('mysql', true),
					],
					'timezone'               => [
						'tooltip' => '',
						'title'   => 'Timezone',
						'value'   => wp_timezone_string(),
					],
					'php-current-time'       => [
						'tooltip' => '',
						'title'   => 'PHP Current Time - with Timezone',
						'value'   => wu_get_current_time(),
					],
					'database-current-time'  => [
						'tooltip' => '',
						'title'   => 'Database Current Time',
						'value'   => gmdate('Y-m-d H:i:s', strtotime((string) $wpdb->get_row('SELECT NOW() as time;')->time)), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					],
					'php-curl-support'       => [
						'tooltip' => '',
						'title'   => 'PHP cURL Support',
						'value'   => function_exists('curl_init') ? __('Yes', 'ultimate-multisite') : __('No', 'ultimate-multisite'),
					],
					'php-gd-time'            => [
						'tooltip' => '',
						'title'   => 'PHP GD Support',
						'value'   => function_exists('gd_info') ? __('Yes', 'ultimate-multisite') : __('No', 'ultimate-multisite'),
					],
					'php-memory-limit'       => [
						'tooltip' => '',
						'title'   => 'PHP Memory Limit',
						'value'   => $memory_limit . 'M',
					],
					'php-memory-usage'       => [
						'tooltip' => '',
						'title'   => 'PHP Memory Usage',
						'value'   => $memory_usage . 'M (' . round($memory_usage / $memory_limit * $pad_spaces, 0) . '%)',
					],
					'php-post-max-size'      => [
						'tooltip' => '',
						'title'   => 'PHP Post Max Size',
						'value'   => ini_get('post_max_size'),
					],
					'php-upload-max-size'    => [
						'tooltip' => '',
						'title'   => 'PHP Upload Max Size',
						'value'   => ini_get('upload_max_filesize'),
					],
					'php-max-execution-time' => [
						'tooltip' => '',
						'title'   => 'PHP Max Execution Time',
						'value'   => $max_execution_time,
					],
					'php-allow-url-fopen'    => [
						'tooltip' => '',
						'title'   => 'PHP Allow URL Fopen',
						'value'   => ini_get('allow_url_fopen'),
					],
					'php-max-file-uploads'   => [
						'tooltip' => '',
						'title'   => 'PHP Max File Uploads',
						'value'   => ini_get('max_file_uploads'),
					],
					'wp-options-count'       => [
						'tooltip' => '',
						'title'   => 'WP Options Count',
						'value'   => count($all_options),
					],
					'wp-options-size'        => [
						'tooltip' => '',
						'title'   => 'WP Options Size',
						'value'   => $all_options_bytes . 'kb',
					],
					'wp-options-transients'  => [
						'tooltip' => '',
						'title'   => 'WP Options Transients',
						'value'   => count($all_options_transients),
					],
					'wp-debug'               => [
						'tooltip' => '',
						'title'   => 'WP Options Transients',
						'value'   => defined('WP_DEBUG') ? WP_DEBUG ? __('Enabled', 'ultimate-multisite') : __('Disabled', 'ultimate-multisite') : __('Not set', 'ultimate-multisite'),
					],
					'script-debug'           => [
						'tooltip' => '',
						'title'   => 'WP Options Transients',
						'value'   => defined('SCRIPT_DEBUG') ? SCRIPT_DEBUG ? __('Enabled', 'ultimate-multisite') : __('Disabled', 'ultimate-multisite') : __('Not set', 'ultimate-multisite'),
					],
					'save-queries'           => [
						'tooltip' => '',
						'title'   => 'WP Options Transients',
						'value'   => defined('SAVEQUERIES') ? SAVEQUERIES ? __('Enabled', 'ultimate-multisite') : __('Disabled', 'ultimate-multisite') : __('Not set', 'ultimate-multisite'),
					],
					'autosave-interval'      => [
						'tooltip' => '',
						'title'   => 'WP Options Transients',
						'value'   => defined('AUTOSAVE_INTERVAL') ? AUTOSAVE_INTERVAL ?: __('Disabled', 'ultimate-multisite') : __('Not set', 'ultimate-multisite'),
					],
					'wp_post_revisions'      => [
						'tooltip' => '',
						'title'   => 'WP Options Transients',
						'value'   => defined('WP_POST_REVISIONS') ? WP_POST_REVISIONS ?: __('Disabled', 'ultimate-multisite') : __('Not set', 'ultimate-multisite'),
					],
					'disable_wp_cron'        => [
						'tooltip' => '',
						'title'   => 'DISABLE_WP_CRON',
						'value'   => defined('DISABLE_WP_CRON') ? DISABLE_WP_CRON ?: __('Yes', 'ultimate-multisite') : __('No', 'ultimate-multisite'),
					],
					'wp_lang'                => [
						'tooltip' => '',
						'title'   => 'WPLANG',
						'value'   => defined('WPLANG') ? WPLANG ?: __('Yes', 'ultimate-multisite') : __('No', 'ultimate-multisite'),
					],
					'wp_memory_limit'        => [
						'tooltip' => '',
						'title'   => 'WP_MEMORY_LIMIT',
						'value'   => (defined('WP_MEMORY_LIMIT') && WP_MEMORY_LIMIT) ? WP_MEMORY_LIMIT : __('Not set', 'ultimate-multisite'),
					],
					'wp_max_memory_limit'    => [
						'tooltip' => '',
						'title'   => 'WP_MAX_MEMORY_LIMIT',
						'value'   => (defined('WP_MAX_MEMORY_LIMIT') && WP_MAX_MEMORY_LIMIT) ? WP_MAX_MEMORY_LIMIT : __('Not set', 'ultimate-multisite'),
					],
					'operating-system'       => [
						'tooltip' => '',
						'title'   => 'Operating System',
						'value'   => $browser['platform'],
					],
					'browser'                => [
						'tooltip' => '',
						'title'   => 'Browser',
						'value'   => $browser['name'] . ' ' . $browser['version'],
					],
					'user-agent'             => [
						'tooltip' => '',
						'title'   => 'User Agent',
						'value'   => $browser['user_agent'],
					],
				],

				'Active Theme'                       => [
					'active-theme' => [
						'tooltip' => '',
						'title'   => 'Active Theme',
						'value'   => $theme->get('Name') . ' - ' . $theme->get('Version') . '(' . $theme->get('ThemeURI') . ')',
					],
				],

				'Active Plugins'                     => $array_active_plugins,
				'Active Plugins on Main Site'        => $array_active_plugins_main_site,

				'Ultimate Multisite Database Status' => $array_wu_tables,

				'Ultimate Multisite Core Settings'   => array_merge(
					[
						'logs-directory' => [
							'tooltip' => '',
							'title'   => 'Logs Directory',
							'value'   => wp_is_writable(Logger::get_logs_folder()) ? __('Writable', 'ultimate-multisite') : __('Not Writable', 'ultimate-multisite'),
						],
					],
					$wpultimo_settings
				),
				'Defined Constants'                  => $array_constants,
			]
		);
	}

	/**
	 * Generate text file of system info data
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function generate_text_file_system_info(): void {

		if ( ! current_user_can('manage_network')) {
			wp_die(esc_html__('You do not have permission to access this resource.', 'ultimate-multisite'), 403);
		}

		$file_name = sprintf("$this->id-%s.txt", gmdate('Y-m-d'));

		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename=' . basename($file_name));
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Type: text/plain');

		foreach ($this->get_data() as $type) {
			foreach ($type as $item) {
				echo esc_html($item['title'] . ': ' . $item['value'] . PHP_EOL);
			}
		}
		die;
	}

	/**
	 * Get browser data
	 *
	 * @since 1.1.5
	 * @return array
	 */
	public function get_browser() {

		// http://www.php.net/manual/en/function.get-browser.php#101125.
		// Cleaned up a bit, but overall it's the same.

		$user_agent   = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
		$browser_name = 'Unknown';
		$platform     = 'Unknown';
		$version      = '';

		// First get the platform
		if (preg_match('/linux/i', (string) $user_agent)) {
			$platform = 'Linux';
		} elseif (preg_match('/macintosh|mac os x/i', (string) $user_agent)) {
			$platform = 'Mac';
		} elseif (preg_match('/windows|win32/i', (string) $user_agent)) {
			$platform = 'Windows';
		}

		// Next get the name of the user agent yes seperately and for good reason
		if (preg_match('/MSIE/i', (string) $user_agent) && ! preg_match('/Opera/i', (string) $user_agent)) {
			$browser_name       = 'Internet Explorer';
			$browser_name_short = 'MSIE';
		} elseif (preg_match('/Firefox/i', (string) $user_agent)) {
			$browser_name       = 'Mozilla Firefox';
			$browser_name_short = 'Firefox';
		} elseif (preg_match('/Chrome/i', (string) $user_agent)) {
			$browser_name       = 'Google Chrome';
			$browser_name_short = 'Chrome';
		} elseif (preg_match('/Safari/i', (string) $user_agent)) {
			$browser_name       = 'Apple Safari';
			$browser_name_short = 'Safari';
		} elseif (preg_match('/Opera/i', (string) $user_agent)) {
			$browser_name       = 'Opera';
			$browser_name_short = 'Opera';
		} elseif (preg_match('/Netscape/i', (string) $user_agent)) {
			$browser_name       = 'Netscape';
			$browser_name_short = 'Netscape';
		}

		// Finally get the correct version number
		$known   = ['Version', $browser_name_short, 'other'];
		$pattern = '#(?<browser>' . implode('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';

		if ( preg_match_all($pattern, (string) $user_agent, $matches)) {

			// See how many we have
			$i = count($matches['browser']);

			if (1 !== $i) {

				// We will have two since we are not using 'other' argument yet
				// See if version is before or after the name
				if (strripos((string) $user_agent, 'Version') < strripos((string) $user_agent, (string) $browser_name_short)) {
					$version = $matches['version'][0];
				} else {
					$version = $matches['version'][1];
				}
			} else {
				$version = $matches['version'][0];
			}

			// Check if we have a version number
			if (empty($version)) {
				$version = '?';
			}
		}

		return [
			'user_agent' => $user_agent,
			'name'       => $browser_name,
			'version'    => $version,
			'platform'   => $platform,
			'pattern'    => $pattern,
		];
	}

	/**
	 * Get list of all the plugins
	 *
	 * @return array
	 */
	public function get_all_plugins() {

		return get_plugins();
	}

	/**
	 * Get only the active plugins
	 *
	 * @return array
	 */
	public function get_active_plugins() {

		return (array) get_site_option('active_sitewide_plugins', []);
	}

	/**
	 * Get only the active plugins on main site
	 *
	 * @return array
	 */
	public function get_active_plugins_on_main_site() {

		return (array) get_option('active_plugins', []);
	}

	/**
	 * Get memory usage
	 */
	public function get_memory_usage(): float {

		return round(memory_get_usage() / 1024 / 1024, 2);
	}

	/**
	 * Get all the ioptions
	 *
	 * @return array
	 */
	public function get_all_options() {

		// Not to be confused with the core deprecated get_alloptions
		return wp_load_alloptions();
	}

	/**
	 * Return all the desired Ultimate Multisite Settings
	 *
	 * @since 1.1.5
	 * @return array
	 */
	public function get_all_wp_ultimo_settings() {

		$exclude = [
			'email',
			'logo',
			'color',
			'from_name',
			'paypal',
			'stripe',
			'terms_content',
			'wu-',
			'license_key',
			'api-',
			'manual_payment_instructions',
		];

		$include = ['enable'];

		$return_settings = [];

		$settings = \WP_Ultimo\Settings::get_instance();

		foreach ($settings->get_all() as $setting => $value) {
			$add = true;

			foreach ($exclude as $ex) {
				if (stristr($setting, $ex) !== false) {
					$add = false;

					break;
				}
			}

			if ($add) {
				$return_settings[ $setting ] = $value;
			}
		}

		return $return_settings;
	}

	/**
	 * Get the transients om the options
	 *
	 * @param array $options Options.
	 * @return array
	 */
	public function get_transients_in_options($options) {

		$transients = [];

		foreach ($options as $name => $value) {
			if (stristr($name, 'transient')) {
				$transients[ $name ] = $value;
			}
		}

		return $transients;
	}
}
