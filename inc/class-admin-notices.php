<?php
/**
 * Handles the Admin Notices added by Ultimate Multisite.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Notices
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles Admin Notices.
 *
 * @since 2.0.0
 */
class Admin_Notices implements \WP_Ultimo\Interfaces\Singleton {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Holds the notices added by Ultimate Multisite.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $notices = [
		'admin'         => [],
		'network-admin' => [],
		'user'          => [],
	];

	/**
	 * Loads the hooks we need for dismissing notices
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

		add_action('in_admin_header', [$this, 'display_notices']);

		add_action('wp_ajax_wu_dismiss_admin_notice', [$this, 'ajax_dismiss_admin_notices']);
	}

	/**
	 * Get the notices the current user has dismissed
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_dismissed_notices() {

		$dismissed = get_user_meta(get_current_user_id(), 'wu_dismissed_admin_notices', true);

		return $dismissed ?: [];
	}

	/**
	 * Adds a new admin notice
	 *
	 * An Admin Notice is a massage displayed at the top of the admin panel, alerting
	 * users of something wrong, successful or informational.
	 *
	 * @since 2.0.0
	 *
	 * @param string  $notice The message to display on the notice block. Supports HTML.
	 * @param string  $type Type of notice. Can be info, warning, error or success.
	 * @param string  $panel Panel on which to show this notice. Can be network-admin, admin or user.
	 * @param boolean $dismissible_key Key to keep track if this notice was already displayed and dismissed.
	 * @param array   $actions List of buttons to add to the notification block.
	 * @return void
	 */
	public function add($notice, $type = 'success', $panel = 'admin', $dismissible_key = false, $actions = []): void {

		$id = $dismissible_key ?: md5($notice);

		$this->notices[ $panel ][ $id ] = [
			'type'            => $type,
			'message'         => $notice,
			'dismissible_key' => is_string($dismissible_key) ? $dismissible_key : false,
			'actions'         => $actions,
		];
	}

	/**
	 * Returns the list of notices added for a particular panel.
	 *
	 * @since 2.0.0
	 *
	 * @param string  $panel Panel to retrieve the notices. Defaults to admin.
	 * @param boolean $filter Wether or not to removed notices already dismissed or not. Defaults to true.
	 * @return array
	 */
	public function get_notices($panel = 'admin', $filter = true) {

		$notices = $this->notices[ $panel ] ?? [];

		$dismissed_messages = $this->get_dismissed_notices();

		if ($filter && $notices) {
			$notices = array_filter($notices, fn($notice) => ! $notice['dismissible_key'] || ! in_array($notice['dismissible_key'], $dismissed_messages, true));
		}

		/**
		 * Allow developers to filter admin notices added by Ultimate Multisite.
		 *
		 * @since 2.0.0
		 *
		 * @param array  $notices List of notices for that particular panel.
		 * @param array  $all_notices List of notices added, segregated by panel.
		 * @param string $panel Panel to retrieve the notices.
		 * @param bool   $filter If the dismissable notices have been filtered out.
		 * @param array  $dismissed_messages List of dismissed notice keys.
		* @return array
		 */
		return apply_filters('wu_admin_notices', $notices, $this->notices, $panel, $filter, $dismissed_messages);
	}

	/**
	 * Enqueues the JavaScript code that sends the dismiss call to the ajax endpoint.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {

		wp_enqueue_script('wu-admin-notices', wu_get_asset('admin-notices.js', 'js'), ['jquery'], wu_get_version(), true);
	}

	/**
	 * Gets the current panel the user is viewing
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_current_panel() {

		$panel = 'admin';

		if (is_user_admin()) {
			$panel = 'user';
		}

		if (is_network_admin()) {
			$panel = 'network-admin';
		}

		return $panel;
	}

	/**
	 * Retrieves the admin notices for the current panel and displays them.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function display_notices(): void {

		$panel = $this->get_current_panel();

		$this->maybe_add_recent_error_notice($panel);

		$notices = $this->get_notices($panel);

		wu_get_template(
			'admin-notices',
			[
				'notices' => $notices,
				'nonce'   => wp_create_nonce('wu-dismiss-admin-notice'),
			]
		);
	}

	/**
	 * Adds a dismissible notice for the latest recent error log entry.
	 *
	 * @since 2.14.3
	 *
	 * @param string $panel Current admin panel.
	 * @return void
	 */
	private function maybe_add_recent_error_notice(string $panel): void {

		if ('network-admin' !== $panel || ! current_user_can('manage_network')) {
			return;
		}

		$page = sanitize_key(wu_request('page'));

		if ('ultimate-multisite' !== $page && ! str_starts_with($page, 'wp-ultimo')) {
			return;
		}

		$error = Logger::get_recent_error();

		if ( ! $error) {
			return;
		}

		$handle    = $error['handle'] ?? __('unknown', 'ultimate-multisite');
		$message   = $error['message'] ?? __('No details available.', 'ultimate-multisite');
		$timestamp = (int) ($error['timestamp'] ?? 0);

		$notice = sprintf(
			/* translators: 1: Log handle, 2: error message. */
			__('A recent Ultimate Multisite error was logged in %1$s: %2$s', 'ultimate-multisite'),
			esc_html($handle . '.log'),
			esc_html($message)
		);

		$this->add(
			$notice,
			'error',
			'network-admin',
			'wu-recent-error-log-' . $timestamp,
			[
				'view-logs' => [
					'title' => __('View Logs', 'ultimate-multisite'),
					'url'   => wu_network_admin_url('wp-ultimo-view-logs'),
				],
			]
		);
	}

	/**
	 * Adds an ajax endpoint to dismiss admin notices
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_dismiss_admin_notices(): void {

		if ( ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'wu-dismiss-admin-notice')) {
			die('-1');
		}

		$dismissed = $this->get_dismissed_notices();

		if ( isset($_POST['notice_id']) && ! in_array($_POST['notice_id'], $dismissed, true)) {
			$dismissed[] = sanitize_text_field(wp_unslash($_POST['notice_id']));

			update_user_meta(get_current_user_id(), 'wu_dismissed_admin_notices', $dismissed);

			die('1');
		}

		die('0');
	}
}
