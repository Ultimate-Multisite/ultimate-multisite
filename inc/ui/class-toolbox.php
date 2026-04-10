<?php
/**
 * Adds the Toolbox UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Toolbox UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Toolbox {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Initialize the singleton.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init() {

		add_action('init', [$this, 'load_toolbox']);
	}

	/**
	 * Checks if we should add the toolbox or not.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	protected function is_toolbox_enabled() {

		$can_see_toolbox = current_user_can('manage_network');

		if (class_exists('\user_switching') && ! $can_see_toolbox) {
			$old_user = \user_switching::get_old_user();

			$can_see_toolbox = user_can($old_user, 'manage_network');
		}

		return apply_filters('wu_is_toolbox_enabled', wu_get_setting('enable_jumper', true) && $can_see_toolbox && ! is_network_admin());
	}

	/**
	 * Loads the necessary elements to display the Toolbox.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function load_toolbox(): void {

		if ($this->is_toolbox_enabled()) {
			add_action('wp_footer', [$this, 'output']);

			add_action('admin_footer', [$this, 'output']);

			add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
		}
	}

	/**
	 * Adds the admin styles to make sure the tooltip renders.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function enqueue_styles(): void {

		wp_enqueue_style('wu-admin');
	}

	/**
	 * Outputs the actual HTML markup of the Toolbox.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function output() {

		$current_site = wu_get_current_site();

		wu_get_template(
			'ui/toolbox',
			[
				'toolbox'      => $this,
				'current_site' => $current_site,
				'customer'     => $current_site ? $current_site->get_customer() : false,
				'membership'   => $current_site ? $current_site->get_membership() : false,
			]
		);
	}
}
