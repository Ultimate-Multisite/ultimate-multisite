<?php
/**
 * Visits Manager
 *
 * Handles processes related to site visits control.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Visits_Manager
 * @since 2.0.0
 */

namespace WP_Ultimo\Managers;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles processes related to limitations.
 *
 * @since 2.0.0
 */
class Visits_Manager {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		if ((bool) wu_get_setting('enable_visits_limiting', true) === false || is_main_site()) {
			return; // Feature not active, bail.

		}

		/*
		 * Due to how caching plugins work, we need to count visits via ajax.
		 * This adds the ajax endpoint that performs the counting.
		 */
		add_action('wp_ajax_nopriv_wu_count_visits', [$this, 'count_visits']);

		add_action('wp_enqueue_scripts', [$this, 'enqueue_visit_counter_script']);

		add_action('template_redirect', [$this, 'maybe_lock_site']);
	}

	/**
	 * Check if the limits for visits was set. If that's the case, lock the site.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function maybe_lock_site(): void {

		$site = wu_get_current_site();

		if ( ! $site) {
			return;
		}

		/*
		 * Case unlimited visits
		 */
		if (empty($site->get_limitations()->visits->get_limit())) {
			return;
		}

		if ($site->has_limitations() && $site->get_visits_count() > $site->get_limitations()->visits->get_limit()) {
			wp_die(esc_html__('This site is not available at this time.', 'ultimate-multisite'), esc_html__('Not available', 'ultimate-multisite'), 503);
		}
	}

	/**
	 * Counts visits to network sites.
	 *
	 * This needs to be extremely light-weight.
	 * The flow happens more or less like this:
	 * 1. Gets the site current total;
	 * 2. Adds one and re-save;
	 * 3. Checks limits and see if we need to flush caches and such;
	 * 4. Delegate these heavy tasks to action_scheduler.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function count_visits(): void {

		if (is_main_site() && is_admin()) {
			return; // bail on main site.

		}

		$site = wu_get_current_site();

		if ($site->get_type() !== 'customer_owned') {
			return;
		}

		$visits_manager = new \WP_Ultimo\Objects\Visits($site->get_id());

		/*
		 * Add a new visit.
		 */
		$visits_manager->add_visit();

		die('1');
	}

	/**
	 * Enqueues the visits count script when necessary.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function enqueue_visit_counter_script(): void {

		if (is_user_logged_in()) {
			return; // bail if user is logged in.

		}

		wp_register_script('wu-visits-counter', wu_get_asset('visits-counter.js', 'js'), [], wu_get_version(), true);

		wp_localize_script(
			'wu-visits-counter',
			'wu_visits_counter',
			[
				'ajaxurl' => admin_url('admin-ajax.php'),
				'code'    => wp_create_nonce('wu-visit-counter'),
			]
		);

		wp_enqueue_script('wu-visits-counter');
	}
}
