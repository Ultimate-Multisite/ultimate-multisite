<?php
/**
 * Light Ajax Implementation
 *
 * Helper class that mimics the Admin Ajax URL.
 * Based on the code found on: https://coderwall.com/p/of7y2q/faster-ajax-for-wordpress
 *
 * @package WP_Ultimo
 * @subpackage Light_Ajax
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds a lighter ajax option to Ultimate Multisite.
 *
 * @since 1.9.14
 */
class Light_Ajax {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Sets up the listener
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		if (isset($_REQUEST['wu-ajax'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = $this->get_when_to_run();

			wu_x_header("X-Ultimo-Ajax-When: $action");

			add_action($action, [$this, 'process_light_ajax'], 20);
		}
	}

	/**
	 * Actions that can ignore the referer check.
	 *
	 * @since 2.0.4
	 * @return array
	 */
	protected function should_skip_referer_check(): bool {

		$allowed_actions = apply_filters(
			'wu_light_ajax_should_skip_referer_check',
			[

				/**
				 * Checkout Form Actions
				 *
				 * They're here because in some cases,
				 * the caching settings might prevent nonces from
				 * being properly refreshed, which could cause 403
				 * errors with the actions below.
				 */
				'wu_render_field_template',
				'wu_create_order',
				'wu_validate_form',
				'wu_check_user_exists',
				'wu_inline_login',

				/**
				 * Template Switching Action
				 *
				 * The customer-panel "switch template" JS
				 * (assets/js/template-switching.js) builds its
				 * request as `ajaxurl + '&action=' + action` and
				 * does NOT send the `r` nonce. After the referer
				 * check was added to process_light_ajax(), this
				 * action started failing the nonce verification on
				 * every request, surfacing in the customer panel as
				 * "A network error occurred. Please check your
				 * connection and try again." The handler
				 * Template_Switching_Element::switch_template()
				 * already enforces ownership via
				 * $this->site->is_customer_allowed(), so the nonce
				 * adds no extra protection here.
				 */
				'wu_switch_template',

			]
		);

		return in_array(wu_request('action', 'no-action'), $allowed_actions, true);
	}

	/**
	 * Gets the hook we should use to attach the light ajax runner to.
	 *
	 * By default, we use plugins_loaded to make sure we run as early
	 * as possible. This allows us to shave off almost 50% of the
	 * TTFB delay with these requests when compared with the regular
	 * WordPress admin-ajax.php.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_when_to_run() {

		/**
		 * For security reasons, we limit the number of actions
		 * available for hooking into. This filter allows developers
		 * to expand that list if necessary.
		 *
		 * @since 2.0.0
		 * @return array The hook list.
		 */
		$allowed_list = apply_filters(
			'wu_light_ajax_allowed_hooks',
			[
				'plugins_loaded',
				'setup_theme',
				'after_setup_theme',
				'init',
			]
		);

		// nonce checked in process_light_ajax().
		$action = isset($_REQUEST['wu-when']) ? base64_decode(sanitize_text_field(wp_unslash($_REQUEST['wu-when']))) : 'plugins_loaded'; // phpcs:ignore WordPress

		return in_array($action, $allowed_list, true) ? $action : 'plugins_loaded';
	}

	/**
	 * Adds an wu_ajax handler.
	 *
	 * @since 1.9.14
	 * @return void
	 */
	public function process_light_ajax(): void {

		// mimic the actual admin-ajax
		define('DOING_AJAX', true); // phpcs:ignore

		/**
		 * Do some light validation of the referrer,
		 * Just to make sure script kiddies are not using it
		 * as an API endpoint.
		 */
		$this->should_skip_referer_check() === false && check_ajax_referer('wu-ajax-nonce', 'r');

		/**
		 * In some cases, we'll need to load extra juice to handle actions.
		 * This action can be used to load extra dependencies when needed.
		 *
		 * @since 2.0.0
		 */
		do_action('wu_before_light_ajax');

		/** Allow for cross-domain requests (from the front end). */
		send_origin_headers();

		header('Content-Type: text/html; charset=' . get_blog_option(wu_get_main_site_id(), 'blog_charset'));
		header('X-Robots-Tag: noindex');

		if (empty($_REQUEST['action'])) {
			status_header(400);

			die('0');
		}

		send_nosniff_header();

		// Disable caching
		nocache_headers();

		header('Pragma: no-cache');

		$action = esc_attr(trim((string) $_REQUEST['action'])); // phpcs:ignore

		if (is_user_logged_in()) {
			do_action('wu_ajax_' . $action); // phpcs:ignore
		} else {
			do_action('wu_ajax_nopriv_' . $action); // phpcs:ignore
		}

		die('1');
	}
}
