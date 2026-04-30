<?php
/**
 * Adds the Tours UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Tours UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Tours {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Registered tours.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $tours = [];

	/**
	 * Initialize the singleton.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init() {

		add_action('wp_ajax_wu_mark_tour_as_finished', [$this, 'mark_as_finished']);

		add_action('admin_enqueue_scripts', [$this, 'register_scripts']);

		add_action('in_admin_footer', [$this, 'enqueue_scripts']);
	}

	/**
	 * Normalize a tour ID to a safe user-settings key.
	 *
	 * WordPress's user-settings cookie is sanitized with
	 * preg_replace('/[^A-Za-z0-9=&_]/', '', ...) before being stored, and
	 * PHP's parse_str() converts hyphens in key names to underscores. Either
	 * path mangles keys like "wp-ultimo-dashboard" so that get_user_setting()
	 * can never find the value that was saved, making every tour re-show on
	 * every session. Replacing hyphens with underscores before building the
	 * setting key keeps write and read in sync regardless of which code path
	 * (cookie or database) WordPress uses for retrieval.
	 *
	 * @since 2.1.1
	 *
	 * @param string $id The tour ID.
	 * @return string The normalized user-settings key.
	 */
	protected function get_setting_key($id) {

		return 'wu_tour_' . str_replace('-', '_', $id);
	}

	/**
	 * Mark the tour as finished for a particular user.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function mark_as_finished(): void {

		check_ajax_referer('wu_tour_finished', 'nonce');

		$id = wu_request('tour_id');

		if ($id) {
			set_user_setting($this->get_setting_key($id), true);
			if (\function_exists('save_user_settings')) {
				\save_user_settings();
			}

			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * Register the necessary scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts() {

		WP_Ultimo()->scripts->register_script_module('shepherd.js', wu_get_asset('lib/shepherd.js', 'js'));
		WP_Ultimo()->scripts->register_style('shepherd', wu_get_asset('lib/shepherd.css', 'css'));

		WP_Ultimo()->scripts->register_script_module('wu-tours', wu_get_asset('tours.js', 'js'), ['shepherd.js']);
	}

	/**
	 * Enqueues the scripts, if we need to.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {

		if ($this->has_tours()) {
			/*
			 * We cannot use wp_localize_script() on a module script (wu-tours), and
			 * we cannot rely on wu-admin being enqueued on every admin page — since
			 * PR #433 it is only enqueued on WP Ultimo pages. The network dashboard
			 * (index.php, hook suffix dashboard-network) is not a WP Ultimo page, so
			 * wu-admin is absent there and localizing onto it silently does nothing,
			 * leaving wu_tours undefined when tours.js executes.
			 *
			 * Fix: use wp_add_inline_script() on 'underscore', which is a WordPress
			 * core script always present in the admin. This injects wu_tours and
			 * wu_tours_vars as globals immediately after underscore loads, making them
			 * available to the wu-tours module regardless of whether wu-admin is
			 * enqueued. See https://core.trac.wordpress.org/ticket/60234.
			 */
			wp_enqueue_script('underscore');

			$inline_data = sprintf(
				'var wu_tours = %s; var wu_tours_vars = %s;',
				wp_json_encode($this->tours),
				wp_json_encode(
					[
						'ajaxurl' => wu_ajax_url(),
						'nonce'   => wp_create_nonce('wu_tour_finished'),
						'i18n'    => [
							'next'   => __('Next', 'ultimate-multisite'),
							'finish' => __('Close', 'ultimate-multisite'),
						],
					]
				)
			);

			wp_add_inline_script('underscore', $inline_data, 'after');

			wp_enqueue_script_module('wu-tours');
			wp_enqueue_style('shepherd');
		}
	}

	/**
	 * Checks if we have registered tours.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_tours() {

		return ! empty($this->tours);
	}

	/**
	 * Register a new tour.
	 *
	 * @see https://shepherdjs.dev/docs/
	 *
	 * @since 2.0.0
	 *
	 * @param string  $id The id of the tour.
	 * @param array   $steps The tour definition. Check shepherd.js docs.
	 * @param boolean $once Whether or not we will show this more than once.
	 * @return void
	 */
	public function create_tour($id, $steps = [], $once = true): void {

		if (did_action('in_admin_header')) {
			return;
		}

		add_action(
			'in_admin_header',
			function () use ($id, $steps, $once) {

				$force_hide = wu_get_setting('hide_tours', false);

				if ($force_hide) {
					return;
				}

				$finished = (bool) get_user_setting($this->get_setting_key($id), false);

				$finished = apply_filters('wu_tour_finished', $finished, $id, get_current_user_id());

				if ( ! $finished || ! $once) {
					foreach ($steps as &$step) {
						$step['text'] = is_array($step['text']) ? implode('</p><p>', $step['text']) : $step['text'];

						$step['text'] = sprintf('<p>%s</p>', $step['text']);
					}

					$this->tours[ $id ] = $steps;
				}
			}
		);
	}
}
