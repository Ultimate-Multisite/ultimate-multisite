<?php
/**
 * Plugin Name: Ultimate Multisite – WordPress Multisite SaaS & WaaS Platform
 * Plugin URI:  https://ultimatemultisite.com
 * Description: Ultimate Multisite is a WordPress Multisite plugin that turns your network into a complete Website-as-a-Service (WaaS) platform with subscriptions, site provisioning, domain mapping, and customer management. Formerly WP Ultimo.
 * Version:     2.4.10
 * Author:      Ultimate Multisite Community
 * Author URI:  https://ultimatemultisite.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ultimate-multisite
 * Domain Path: /lang
 * Network:     true
 * Requires at least: 5.3
 * Requires PHP: 7.4.30
 *
 * Ultimate Multisite is distributed under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Ultimate Multisite is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Ultimate Multisite. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author   Arindo Duque, NextPress, WPMUDEV, and the Ultimate Multisite Community
 * @category Core
 * @package  Ultimate_Multisite
 * @version 2.4.10
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

if (defined('WP_SANDBOX_SCRAPING') && WP_SANDBOX_SCRAPING) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$wu_possible_conflicts = false;
	foreach ( ['wp-ultimo/wp-ultimo.php', 'wp-multisite-waas/wp-multisite-waas.php', 'multisite-ultimate/multisite-ultimate.php'] as $plugin_file ) {
		if ( is_plugin_active($plugin_file) ) {
			// old plugin still installed and active with the old name and path
			// and the user is trying to activate this plugin. So deactivate and return.
			deactivate_plugins($plugin_file, true, true);
			$wu_possible_conflicts = true;
		}
	}
	if (file_exists(WP_CONTENT_DIR . '/sunrise.php')) {
		// We must override the old sunrise file or more name conflicts will occur.
		copy(__DIR__ . '/sunrise.php', WP_CONTENT_DIR . '/sunrise.php');
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate(WP_CONTENT_DIR . '/sunrise.php', true);
		}
		$wu_possible_conflicts = true;
	}
	if ($wu_possible_conflicts) {
		// return to avoid loading the plugin which will have name conflicts.
		// on the next page load the plugin will load normally and old plugin will be gone.
		return;
	}
}

if ( ! defined('WP_ULTIMO_PLUGIN_FILE')) {
	define('WP_ULTIMO_PLUGIN_FILE', __FILE__);
}
if ( ! defined('MULTISITE_ULTIMATE_UPDATE_URL')) {
	define('MULTISITE_ULTIMATE_UPDATE_URL', 'https://ultimatemultisite.com/');
}
/**
 * Require core file dependencies
 */
require_once __DIR__ . '/constants.php';

try {
	require_once __DIR__ . '/vendor/autoload_packages.php';
} catch ( \Error $exception ) {
	if ( defined('WP_DEBUG') && WP_DEBUG ) {
		// This message is not translated as at this point it's too early to load translations.
		error_log(  // phpcs:ignore
			esc_html('Your installation of Ultimate Multisite is incomplete. If you installed Ultimate Multisite from GitHub, please refer to this document to set up your development environment: https://github.com/superdav42/wp-multisite-waas?tab=readme-ov-file#method-2-using-git-and-composer-for-developers')
		);
	}
	add_action(
		'network_admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
					/* translators: 1: is a link to a support document. 2: closing link */
						esc_html__('Your installation of Ultimate Multisite is incomplete. If you installed from GitHub, %1$splease refer to this document%2$s to set up your development environment or download a pre-packaged ZIP release.', 'ultimate-multisite'),
						'<a href="' . esc_url('https://github.com/superdav42/wp-multisite-waas?tab=readme-ov-file#method-2-using-git-and-composer-for-developers') . '" target="_blank" rel="noopener noreferrer">',
						'</a>'
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

/**
 * Setup activation/deactivation hooks
 */
WP_Ultimo\Hooks::init();

if ( ! function_exists('WP_Ultimo')) {
	/**
	 * Initializes the WP Ultimo class
	 *
	 * This function returns the WP_Ultimo class singleton, and
	 * should be used to avoid declaring globals.
	 *
	 * @return WP_Ultimo
	 * @since 2.0.0
	 */
	function WP_Ultimo() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return WP_Ultimo::get_instance();
	}
}
// Initialize and set to global for back-compat
$GLOBALS['WP_Ultimo'] = WP_Ultimo();
// End of ultimate-multisite.php
