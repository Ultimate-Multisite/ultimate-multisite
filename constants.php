<?php
/**
 * Set additional Ultimate Multisite plugin constants.
 *
 * @package WP_Ultimo
 * @since 2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Plugin Folder Path
if ( ! defined('WP_ULTIMO_PLUGIN_DIR')) {
	define('WP_ULTIMO_PLUGIN_DIR', plugin_dir_path(WP_ULTIMO_PLUGIN_FILE));
}

// Plugin Folder URL
if ( ! defined('WP_ULTIMO_PLUGIN_URL')) {
	define('WP_ULTIMO_PLUGIN_URL', plugin_dir_url(WP_ULTIMO_PLUGIN_FILE));
}

// Plugin Root File
if ( ! defined('WP_ULTIMO_PLUGIN_BASENAME')) {
	define('WP_ULTIMO_PLUGIN_BASENAME', plugin_basename(WP_ULTIMO_PLUGIN_FILE));
}

/**
 * Feature flag: Enable Template Library.
 *
 * When set to true, enables the Template Library admin page.
 * Server-side functionality is not complete, so this defaults to false.
 * Developers can enable this by defining WU_TEMPLATE_LIBRARY_ENABLED as true
 * in wp-config.php before the plugin loads.
 *
 * @since 2.5.0
 */
if ( ! defined('WU_TEMPLATE_LIBRARY_ENABLED')) {
	define('WU_TEMPLATE_LIBRARY_ENABLED', false);
}

/**
 * Feature flag: Enable External Cron Service.
 *
 * When set to true, enables the External Cron manager, admin page,
 * and service integration. Server-side functionality is not complete,
 * so this defaults to false. Developers can enable this by defining
 * WU_EXTERNAL_CRON_ENABLED as true in wp-config.php before the plugin loads.
 *
 * @since 2.5.0
 */
if ( ! defined('WU_EXTERNAL_CRON_ENABLED')) {
	define('WU_EXTERNAL_CRON_ENABLED', false);
}
