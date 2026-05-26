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
	 * Returns the user meta key used to record a finished tour.
	 *
	 * We persist the finished flag in user meta (not just the WordPress
	 * `wp-settings-*` cookie) because `set_user_setting()` / `wp_set_all_user_settings()`
	 * only update the user-settings cookie on regular admin page requests via
	 * `wp_user_settings()` — they do NOT set the cookie during AJAX. When the
	 * tour dismissal arrives as an AJAX `wu_mark_tour_as_finished` call, the
	 * browser is never sent a `Set-Cookie` header, so the next page request
	 * sees a stale or missing `wp-settings-{uid}` cookie and `get_user_setting()`
	 * returns `false`, re-showing the tour. Storing the flag in user meta gives
	 * us a cookie-independent source of truth that AJAX writes can persist
	 * immediately and any subsequent request can read.
	 *
	 * @since 2.5.2
	 *
	 * @param string $id The tour ID.
	 * @return string The user meta key.
	 */
	protected function get_meta_key($id) {

		return 'wu_tour_finished_' . str_replace('-', '_', $id);
	}

	/**
	 * Returns legacy user-settings keys that may hold a finished tour flag.
	 *
	 * Older tour persistence used WordPress user settings directly. Depending
	 * on the WordPress version and whether the value travelled through the
	 * wp-settings-* cookie sanitizer, hyphenated tour IDs may have been stored
	 * either with underscores or with hyphens stripped entirely. Check both
	 * shapes so users who already dismissed those tours do not see them again.
	 *
	 * @since 2.5.2
	 *
	 * @param string $id The tour ID.
	 * @return array
	 */
	protected function get_legacy_setting_keys($id) {

		return array_values(
			array_unique(
				[
					$this->get_setting_key($id),
					'wu_tour_' . preg_replace('/[^A-Za-z0-9_]/', '', $id),
				]
			)
		);
	}

	/**
	 * Whether a legacy user-settings value marks the tour as finished.
	 *
	 * @since 2.5.2
	 *
	 * @param string $id      The tour ID.
	 * @param int    $user_id User ID.
	 * @return bool
	 */
	protected function is_legacy_tour_finished($id, $user_id) {

		if (get_current_user_id() === (int) $user_id) {
			foreach ($this->get_legacy_setting_keys($id) as $setting_key) {
				if (get_user_setting($setting_key, false)) {
					return true;
				}
			}
		}

		$stored_settings = get_user_option('user-settings', $user_id);

		if ( ! is_string($stored_settings) || '' === $stored_settings) {
			return false;
		}

		$parsed_settings = [];

		parse_str($stored_settings, $parsed_settings);

		foreach ($this->get_legacy_setting_keys($id) as $setting_key) {
			if ( ! empty($parsed_settings[ $setting_key ])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the tour has been finished for the current user.
	 *
	 * Checks user meta first (the authoritative cookie-independent flag) and
	 * falls back to the legacy `get_user_setting()` lookup for users who
	 * dismissed a tour before the meta-based persistence was introduced.
	 *
	 * @since 2.5.2
	 *
	 * @param string $id The tour ID.
	 * @param int    $user_id Optional. Defaults to the current user.
	 * @return bool
	 */
	protected function is_tour_finished($id, $user_id = 0) {

		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id) {
			return false;
		}

		$meta_value = get_user_meta($user_id, $this->get_meta_key($id), true);

		if ($meta_value) {
			return true;
		}

		$legacy_finished = $this->is_legacy_tour_finished($id, $user_id);

		if ($legacy_finished) {
			update_user_meta($user_id, $this->get_meta_key($id), 1);
		}

		return $legacy_finished;
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
			$user_id = get_current_user_id();

			if ($user_id) {
				/*
				 * Persist the flag in user meta so the next request can read it
				 * regardless of the wp-settings-* cookie state. AJAX requests do
				 * not pass through wp_user_settings() (which is what normally
				 * syncs the cookie from user meta), so writing only to
				 * set_user_setting() / wp_set_all_user_settings() would leave the
				 * browser cookie stale and the tour would re-appear on the next
				 * page load.
				 */
				update_user_meta($user_id, $this->get_meta_key($id), 1);
			}

			// Keep the legacy user-settings write for backward compatibility.
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
			 * We cannot use wp_localize_script() on a module script (wu-tours).
			 *
			 * We also cannot use wp_add_inline_script() on 'underscore' here,
			 * because enqueue_scripts() is hooked to in_admin_footer — by the time
			 * that hook fires, the <head> scripts (including underscore) have
			 * already been printed, so wp_add_inline_script() is silently ignored,
			 * leaving wu_tours undefined when tours.min.js executes.
			 *
			 * Fix: call wp_print_inline_script_tag() directly in in_admin_footer.
			 * That hook fires before admin_footer, which is where WordPress prints
			 * enqueued script modules, so wu_tours is guaranteed to be defined
			 * before the wu-tours module runs. wp_print_inline_script_tag() is
			 * available since WP 5.7; this code path already requires WP 6.5+
			 * (for wp_enqueue_script_module), so no version guard is needed.
			 */
			wp_print_inline_script_tag(
				sprintf(
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
				),
				['id' => 'wu-tours-data']
			);

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

				$pre_filter_finished = $this->is_tour_finished($id);

				$finished = apply_filters('wu_tour_finished', $pre_filter_finished, $id, get_current_user_id());

				if ( ! $finished || ! $once) {
					foreach ($steps as &$step) {
						$step['text'] = is_array($step['text']) ? implode('</p><p>', $step['text']) : $step['text'];

						$step['text'] = sprintf('<p>%s</p>', $step['text']);
					}

					$this->tours[ $id ] = $steps;

					/*
					 * Persist the finished flag as soon as the tour is queued for
					 * display. Previously the flag was only written when the user
					 * clicked through to the last step (Shepherd's complete / cancel
					 * events). Users who simply navigate away — or refresh the page —
					 * before completing the walkthrough would see the same tour on
					 * every page load. Marking the tour finished here ensures the
					 * one-shot semantics hold even when the user does not explicitly
					 * close the modal. The Shepherd event handlers in tours.js still
					 * fire markTourFinished for completeness (the operation is
					 * idempotent).
					 */
					if ($once && ! $pre_filter_finished && ! $finished) {
						$user_id = get_current_user_id();

						if ($user_id) {
							update_user_meta($user_id, $this->get_meta_key($id), 1);
						}
					}
				}
			}
		);
	}
}
