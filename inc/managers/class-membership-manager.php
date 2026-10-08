<?php
/**
 * Membership Manager
 *
 * Handles processes related to memberships.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Membership_Manager
 * @since 2.0.0
 */

namespace WP_Ultimo\Managers;

use Psr\Log\LogLevel;
use WP_Ultimo\Database\Memberships\Membership_Status;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles processes related to memberships.
 *
 * @since 2.0.0
 */
class Membership_Manager extends Base_Manager {

	use \WP_Ultimo\Apis\Rest_Api;
	use \WP_Ultimo\Apis\WP_CLI;
	use \WP_Ultimo\Apis\MCP_Abilities;
	use \WP_Ultimo\Apis\Command_Palette;
	use \WP_Ultimo\Traits\Singleton;

	const LOG_FILE_NAME = 'memberships';
	/**
	 * The manager slug.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $slug = 'membership';

	/**
	 * The model class associated to this manager.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $model_class = \WP_Ultimo\Models\Membership::class;

	/**
	 * Whether a checkout database transaction is currently open.
	 *
	 * @since 2.15.2
	 * @var bool
	 */
	private $checkout_transaction_in_progress = false;

	/**
	 * Membership IDs whose pending sites should publish after checkout commits.
	 *
	 * @since 2.15.2
	 * @var int[]
	 */
	private $deferred_pending_site_publications = [];

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->enable_rest_api();

		$this->enable_wp_cli();

		$this->enable_mcp_abilities();

		$this->enable_command_palette();

		add_action(
			'init',
			function () {
				Event_Manager::register_model_events('membership', __('Membership', 'ultimate-multisite'), ['created', 'updated']);
			}
		);

		add_action('wu_async_transfer_membership', [$this, 'async_transfer_membership'], 10, 2);

		add_action('wu_async_delete_membership', [$this, 'async_delete_membership'], 10);

		/*
		 * Transitions
		 */
		add_action('wu_transition_membership_status', [$this, 'mark_cancelled_date'], 10, 3);

		add_action('wu_transition_membership_status', [$this, 'transition_membership_status'], 10, 3);

		add_action('wu_transition_membership_status', [$this, 'handle_pending_site_on_cancellation'], 10, 3);

		add_action('wu_checkout_transaction_started', [$this, 'begin_checkout_transaction'], 0, 0);
		add_action('wu_checkout_transaction_committed', [$this, 'commit_checkout_transaction'], 0, 0);
		add_action('wu_checkout_transaction_rolled_back', [$this, 'rollback_checkout_transaction'], 0, 0);
		add_action('wu_membership_post_save', [$this, 'maybe_defer_pending_site_publication'], 20, 2);

		/*
		 * Deal with delayed/schedule swaps
		 */
		add_action('wu_async_membership_swap', [$this, 'async_membership_swap'], 10);

		/*
		 * Deal with pending sites creation.
		 *
		 * `publish_pending_site` is also registered on the nopriv hook
		 * because the loopback fast-path called by
		 * Membership::publish_pending_site_async() uses
		 * wp_remote_request() — which does not forward auth cookies, so
		 * admin-ajax.php dispatches `wp_ajax_nopriv_wu_publish_pending_site`.
		 * Without a nopriv listener the dispatch falls through to
		 * `wp_die('0')` and admin-ajax returns HTTP 400 on every loopback,
		 * forcing the slower Action Scheduler fallback for every site
		 * creation. Security on the nopriv path is enforced by the HMAC
		 * token verified in publish_pending_site(); the admin-modal
		 * (logged-in) path keeps the nonce check.
		 */
		add_action('wp_ajax_wu_publish_pending_site', [$this, 'publish_pending_site']);
		add_action('wp_ajax_nopriv_wu_publish_pending_site', [$this, 'publish_pending_site']);

		// Run before add-on status handlers so hashes cannot bypass ownership checks.
		add_action('wp_ajax_wu_check_pending_site_created', [$this, 'authorize_pending_site_status'], 0);
		add_action('wp_ajax_wu_check_pending_site_created', [$this, 'check_pending_site_created']);

		add_action('wu_async_publish_pending_site', [$this, 'async_publish_pending_site'], 10);

		/*
		 * Reclaim orphaned pending_site when a WooCommerce order completes.
		 */
		add_action('woocommerce_order_status_completed', [$this, 'reclaim_pending_site_on_wc_order_completion']);
	}

	/**
	 * Processes a delayed site publish action.
	 *
	 * @since 2.0.11
	 */
	public function publish_pending_site(): void {

		// Check for HMAC token (loopback fast-path) or nonce (admin modal).
		$wu_token   = wu_request('wu_token');
		$wu_expires = wu_request('wu_expires');

		if ($wu_token && $wu_expires) {
			// Verify HMAC token for loopback requests.
			$membership_id = wu_request('membership_id');
			$expires       = (int) $wu_expires;

			// Token must not be expired.
			if ($expires < time()) {
				wp_die('0', 400);
			}

			// Verify HMAC.
			$expected_token = hash_hmac('sha256', $membership_id . '|' . $expires, wp_salt('auth'));
			if (! hash_equals($expected_token, $wu_token)) {
				wp_die('0', 400);
			}
		} else {
			// Fall back to nonce check for admin modal / manual flow.
			check_ajax_referer('wu_publish_pending_site');
			$membership_id = wu_request('membership_id');
		}

		ignore_user_abort(true);

		$this->send_pending_site_publish_started_response();

		$this->async_publish_pending_site($membership_id);

		exit; // Just exit the request
	}

	/**
	 * Sends the loopback response before the long-running publish starts.
	 *
	 * The checkout fast-path calls this endpoint with a normal blocking
	 * wp_remote_request() so it can confirm that the HMAC-protected action was
	 * reached. The endpoint must therefore complete the HTTP response before it
	 * starts copying/provisioning the pending site, otherwise FrankenPHP and other
	 * non-FastCGI SAPIs keep the checkout waiting for the full publish.
	 *
	 * @since 2.5.x
	 * @return void
	 */
	protected function send_pending_site_publish_started_response() {

		$response = wp_json_encode(['status' => 'creating-site']);

		if ( ! headers_sent()) {
			header('Content-Type: application/json; charset=' . get_option('blog_charset'));
			header('Cache-Control: no-cache, must-revalidate, max-age=0');
			header('Content-Length: ' . strlen($response));

			/*
			 * The manual fallback below relies on the client being able to treat
			 * the response body as complete after Content-Length bytes even though
			 * PHP continues executing the publish in the same request.
			 */
			header('Connection: close');
		}

		echo $response; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe JSON from wp_json_encode().

		$this->finish_pending_site_publish_response();
	}

	/**
	 * Finishes or flushes the HTTP response while allowing PHP to keep running.
	 *
	 * @since 2.5.x
	 * @return void
	 */
	protected function finish_pending_site_publish_response() {

		if (function_exists('litespeed_finish_request')) {
			litespeed_finish_request();
			return;
		}

		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
			return;
		}

		/*
		 * FrankenPHP does not expose a PHP-level finish_request() function in all
		 * contexts. Flush every active output buffer and the SAPI buffer so the
		 * loopback client can receive the Content-Length-delimited response before
		 * the pending-site publish work starts.
		 */
		while (ob_get_level() > 0) {
			ob_end_flush();
		}

		flush();
	}

	/**
	 * Processes a delayed site publish action.
	 *
	 * @since 2.0.0
	 *
	 * @param int $membership_id The membership id.
	 * @return void
	 */
	public function async_publish_pending_site($membership_id) {
		/*
		 * Allow unlimited execution time for site initialization.
		 *
		 * Complex site templates (Elementor kits, Freemius SDK, Rank Math,
		 * large database tables) can exceed PHP's default time limit,
		 * killing the process and leaving the Action Scheduler action
		 * stuck in-progress status.
		 *
		 * @since 2.4.13
		 */
		if (function_exists('set_time_limit')) {
			set_time_limit(0);
		}

		$membership = wu_get_membership($membership_id);

		if ( ! $membership) {
			wu_log_add(self::LOG_FILE_NAME, __('An unexpected error happened.', 'ultimate-multisite'), LogLevel::ERROR);
			return;
		}

		// Recovery belongs to the background publisher, never the status reader.
		$pending_site = $membership->get_pending_site();
		if ($pending_site && $pending_site->is_publishing_stale()) {
			$pending_site->set_publishing(false);
			$membership->update_pending_site($pending_site);
		}

		$status = $membership->publish_pending_site();

		if (is_wp_error($status)) {
			wu_log_add('site-errors', $status, LogLevel::ERROR);
		}
	}

	/**
	 * Authorizes status requests before any add-on can return private links.
	 *
	 * @since 2.17.3
	 * @return void
	 */
	public function authorize_pending_site_status() {
		nocache_headers();
		if ( ! headers_sent()) {
			header('Cache-Control: private, no-store, max-age=0');
		}
		if (is_wp_error($this->get_authorized_status_membership())) {
			wp_send_json(
				[
					'state'          => 'forbidden',
					'publish_status' => 'stopped',
				],
				403
			);
		}
	}

	/**
	 * Resolves an owner- and nonce-bound status request.
	 *
	 * @since 2.17.3
	 * @return \WP_Ultimo\Models\Membership|\WP_Error
	 */
	public function get_authorized_status_membership() {
		$hash  = wu_request('membership_hash');
		$nonce = wu_request('_ajax_nonce');
		if ( ! is_user_logged_in() || ! is_string($hash) || ! is_string($nonce)
			|| ! wp_verify_nonce($nonce, 'wu_check_pending_site_created:' . $hash)) {
			return new \WP_Error('checkout_access_denied');
		}
		$membership = wu_get_membership_by_hash($hash);
		$customer   = $membership ? $membership->get_customer() : false;
		if ( ! $customer || ((int) $customer->get_user_id() !== get_current_user_id() && ! current_user_can('manage_network'))) {
			return new \WP_Error('checkout_access_denied');
		}
		$payment_hash = wu_request('payment_hash');
		if ($payment_hash) {
			$payment = is_string($payment_hash) ? wu_get_payment_by_hash($payment_hash) : false;
			if ( ! $payment || (int) $payment->get_membership_id() !== (int) $membership->get_id()
				|| (int) $payment->get_customer_id() !== (int) $membership->get_customer_id()) {
				return new \WP_Error('checkout_access_denied');
			}
		}
		return $membership;
	}

	/**
	 * Returns current status without activating, publishing or retrying an order.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public function check_pending_site_created() {
		$this->authorize_pending_site_status();
		$membership = $this->get_authorized_status_membership();
		$hash       = wu_request('payment_hash');
		$payment    = $hash && is_string($hash) ? wu_get_payment_by_hash($hash) : false;
		wp_cache_delete($membership->get_id(), 'wu_membership_meta');
		wp_send_json($this->get_provisioning_status($membership, $payment));
	}

	/**
	 * Builds the generic readiness contract shared by the UI and integrations.
	 *
	 * @since 2.17.3
	 * @param \WP_Ultimo\Models\Membership    $membership Owned membership.
	 * @param \WP_Ultimo\Models\Payment|false $payment Optional checkout payment.
	 * @return array
	 */
	public function get_provisioning_status($membership, $payment = false) {
		$data = [
			'state'          => 'pending',
			'publish_status' => 'stopped',
			'sites'          => [],
			'redirect_url'   => '',
		];
		if (in_array($membership->get_status(), ['cancelled', 'expired'], true)
			|| ($payment && in_array($payment->get_status(), ['failed', 'cancelled', 'refunded'], true))) {
			$data['state']          = 'failed';
			$data['publish_status'] = 'failed';
			return $data;
		}
		if ( ! in_array($membership->get_status(), ['active', 'trialing'], true)
			|| ($payment && 'completed' !== $payment->get_status()
				&& ! ('trialing' === $membership->get_status() && (float) $payment->get_total() <= 0))) {
			return array_merge($data, ['state' => 'payment_pending']);
		}
		$customer = $membership->get_customer();
		if ($customer && 'pending' === $customer->get_email_verification()) {
			return array_merge($data, ['state' => 'verification_pending']);
		}
		$pending_site = $membership->get_pending_site();
		if ($pending_site) {
			return array_merge($data, ['publish_status' => $pending_site->is_publishing() ? 'running' : 'stopped']);
		}
		/**
		 * Supplies membership-bound sites for integrations with other site topologies.
		 *
		 * @since 2.17.3
		 * @param array $sites Published sites; integrations must preserve ownership.
		 * @param object $membership Owned membership.
		 * @param object|false $payment Checkout payment.
		 */
		$sites = apply_filters('wu_checkout_provisioning_sites', $membership->get_sites(false), $membership, $payment);
		if ( ! is_array($sites) || ! $sites) {
			return $data; // Missing pending metadata is not proof that a site exists.
		}
		foreach ($sites as $site) {
			if ( ! $site instanceof \WP_Ultimo\Models\Site) {
				return $data;
			}
			if (\WP_Ultimo\Helpers\Site_Duplicator::is_clone_failed($site->get_id())) {
				$data['state']          = 'failed';
				$data['publish_status'] = 'failed';
				return $data;
			}
			if ( ! \WP_Ultimo\Helpers\Site_Duplicator::is_site_ready($site->get_id())) {
				$data['state']          = 'cloning';
				$data['publish_status'] = 'running';
				return $data;
			}
		}
		foreach ($sites as $site) {
			$data['sites'][] = [
				'id'        => (int) $site->get_id(),
				'title'     => $site->get_title(),
				'url'       => esc_url_raw($site->get_active_site_url()),
				'admin_url' => esc_url_raw(get_admin_url($site->get_id())),
				'visit_url' => esc_url_raw(wu_with_sso($site->get_active_site_url())),
			];
		}
		$data['state']          = 'ready';
		$data['publish_status'] = 'completed';
		/**
		 * Opts an integration into a final handoff; default core remains on thank-you.
		 *
		 * @since 2.17.3
		 * @param string $url Empty by default; must use a ready site's HTTP(S) origin.
		 * @param object $membership Owned membership.
		 * @param object|false $payment Checkout payment.
		 * @param array $sites Ready site models.
		 */
		$url                  = apply_filters('wu_checkout_ready_redirect_url', '', $membership, $payment, $sites);
		$data['redirect_url'] = $this->validate_ready_redirect($url, $data['sites']);
		return $data;
	}

	/**
	 * Rejects destinations outside the purchased site's origins.
	 *
	 * @since 2.17.3
	 * @param mixed $url Integration destination.
	 * @param array $sites Serialized ready sites.
	 * @return string
	 */
	private function validate_ready_redirect($url, array $sites): string {
		$parts = is_string($url) ? wp_parse_url($url) : false;
		if ( ! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
			return '';
		}
		foreach ($sites as $site) {
			$origin = wp_parse_url($site['url']);
			if ($origin && ($parts['scheme'] ?? '') === ($origin['scheme'] ?? '')
				&& strtolower($parts['host'] ?? '') === strtolower($origin['host'] ?? '')
				&& ($parts['port'] ?? null) === ($origin['port'] ?? null)) {
				return esc_url_raw($url);
			}
		}
		return '';
	}

	/**
	 * Processes a membership swap.
	 *
	 * @since 2.0.0
	 *
	 * @param int $membership_id The membership id.
	 * @return void
	 */
	public function async_membership_swap($membership_id) {

		global $wpdb;

		$membership = wu_get_membership($membership_id);

		if ( ! $membership) {
			wu_log_add(self::LOG_FILE_NAME, __('An unexpected error happened.', 'ultimate-multisite'), LogLevel::ERROR);
			return;
		}

		$scheduled_swap = $membership->get_scheduled_swap();

		if (empty($scheduled_swap)) {
			wu_log_add(self::LOG_FILE_NAME, __('An unexpected error happened.', 'ultimate-multisite'), LogLevel::ERROR);
			return;
		}

		$order = $scheduled_swap->order;

		$wpdb->query('START TRANSACTION'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		try {
			$membership->swap($order);

			$status = $membership->save();

			if (is_wp_error($status)) {
				$wpdb->query('ROLLBACK');  // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				wu_log_add(self::LOG_FILE_NAME, $status->get_error_message(), LogLevel::ERROR);
				return;
			}
		} catch (\Throwable $exception) {
			$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			wu_log_add(self::LOG_FILE_NAME, $exception->getMessage(), LogLevel::ERROR);
			return;
		}

		/*
		 * Clean up the membership swap order.
		 */
		$membership->delete_scheduled_swap();

		$wpdb->query('COMMIT'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Watches the change in payment status to take action when needed.
	 *
	 * @todo Publishing sites should be done in async.
	 *
	 * @since 2.0.0
	 *
	 * @param string  $old_status The old status of the membership.
	 * @param string  $new_status The new status of the membership.
	 * @param integer $membership_id Payment ID.
	 * @return void
	 */
	public function transition_membership_status($old_status, $new_status, $membership_id): void {

		$allowed_previous_status = [
			Membership_Status::PENDING,
			Membership_Status::ON_HOLD,
		];

		if ( ! in_array($old_status, $allowed_previous_status, true)) {
			return;
		}

		$allowed_status = [
			Membership_Status::ACTIVE,
			Membership_Status::TRIALING,
		];

		if ( ! in_array($new_status, $allowed_status, true)) {
			return;
		}

		/*
		 * Create pending sites.
		 */
		$membership = wu_get_membership($membership_id);

		if ( ! $this->can_publish_pending_site($membership)) {
			return;
		}

		/*
		 * A checkout activates free memberships before its database transaction
		 * commits. Starting the loopback here lets the second request race the
		 * commit and fail to load the newly created membership. Publish as soon as
		 * the checkout signals that its transaction committed instead.
		 */
		if ($this->checkout_transaction_in_progress) {
			$this->deferred_pending_site_publications[ $membership_id ] = (int) $membership_id;
			return;
		}

		$membership->publish_pending_site_async();
	}

	/**
	 * Queues an eligible pending site when checkout saves without a status change.
	 *
	 * Trial retries can save an already-trialing membership, which does not emit a
	 * status transition. Watching saves only while checkout owns a transaction
	 * ensures those pending sites are still published after commit without changing
	 * normal webhook or administrative save behavior.
	 *
	 * @since 2.15.2
	 *
	 * @param array                        $_data      The saved membership data.
	 * @param \WP_Ultimo\Models\Membership $membership The saved membership.
	 * @return void
	 */
	public function maybe_defer_pending_site_publication($_data, $membership): void {

		if (
			! $this->checkout_transaction_in_progress
			|| ! $this->can_publish_pending_site($membership)
			|| ! $membership->get_pending_site()
		) {
			return;
		}

		$membership_id = $membership->get_id();

		$this->deferred_pending_site_publications[ $membership_id ] = (int) $membership_id;
	}

	/**
	 * Checks whether a membership is still eligible for pending-site publication.
	 *
	 * The state is checked from storage immediately before dispatch so gateway or
	 * add-on changes made later in the same checkout are respected.
	 *
	 * @since 2.15.2
	 *
	 * @param \WP_Ultimo\Models\Membership|false $membership Membership to check.
	 * @return bool
	 */
	private function can_publish_pending_site($membership): bool {

		if ( ! $membership) {
			return false;
		}

		$allowed_statuses = [
			Membership_Status::ACTIVE,
			Membership_Status::TRIALING,
		];

		if ( ! in_array($membership->get_status(), $allowed_statuses, true)) {
			return false;
		}

		/*
		 * If the customer has not yet verified their email, hold off on
		 * publishing the pending site. The site will be published later
		 * when the customer completes email verification (handled in
		 * Customer_Manager::handle_email_verification()).
		 */
		$customer = $membership->get_customer();

		return ! $customer || 'pending' !== $customer->get_email_verification();
	}

	/**
	 * Marks the beginning of a checkout database transaction.
	 *
	 * @since 2.15.2
	 * @return void
	 */
	public function begin_checkout_transaction() {

		$this->checkout_transaction_in_progress   = true;
		$this->deferred_pending_site_publications = [];
	}

	/**
	 * Publishes pending sites after the checkout transaction commits.
	 *
	 * @since 2.15.2
	 * @return void
	 */
	public function commit_checkout_transaction() {

		$membership_ids = $this->deferred_pending_site_publications;

		$this->rollback_checkout_transaction();

		foreach ($membership_ids as $membership_id) {
			$membership = wu_get_membership($membership_id);

			if ( ! $this->can_publish_pending_site($membership)) {
				continue;
			}

			try {
				$membership->publish_pending_site_async();
			} catch (\Throwable $e) {
				/*
				 * The checkout transaction is already committed. Log a failed
				 * dispatch and continue so other memberships and listeners run.
				 */
				wu_maybe_log_error($e);
			}
		}
	}

	/**
	 * Clears pending-site publications when checkout rolls back.
	 *
	 * @since 2.15.2
	 * @return void
	 */
	public function rollback_checkout_transaction() {

		$this->checkout_transaction_in_progress   = false;
		$this->deferred_pending_site_publications = [];
	}

	/**
	 * Mark the membership date of cancellation.
	 *
	 * @since 2.0.0
	 *
	 * @param string $old_value Old status value.
	 * @param string $new_value New status value.
	 * @param int    $item_id The membership id.
	 * @return void
	 */
	public function mark_cancelled_date($old_value, $new_value, $item_id): void {

		if ('cancelled' === $new_value && $new_value !== $old_value) {
			$membership = wu_get_membership($item_id);

			$membership->set_date_cancellation(wu_get_current_time('mysql', true));

			$membership->save();
		}
	}

	/**
	 * Preserve pending_site data in a transient when a membership is cancelled.
	 *
	 * When a membership transitions to `cancelled`, any orphaned pending_site
	 * stored in membership meta is moved to a 24-hour transient keyed by the
	 * customer's email hash. This allows a retry checkout flow to reclaim the
	 * pending site rather than losing it permanently.
	 *
	 * Transient key format: `wu_transferable_pending_` . md5( $email )
	 *
	 * @since 2.3.2
	 *
	 * @param string $old_status    The previous membership status.
	 * @param string $new_status    The new membership status.
	 * @param int    $membership_id The ID of the membership.
	 * @return void
	 */
	public function handle_pending_site_on_cancellation($old_status, $new_status, $membership_id): void {

		if ('cancelled' !== $new_status) {
			return;
		}

		$membership = wu_get_membership($membership_id);

		if ( ! $membership) {
			return;
		}

		$pending_site = $membership->get_pending_site();

		if ( ! $pending_site) {
			return;
		}

		$customer = $membership->get_customer();

		if ( ! $customer) {
			$membership->delete_pending_site();
			return;
		}

		$email         = $customer->get_email_address();
		$transient_key = 'wu_transferable_pending_' . md5($email);

		set_transient($transient_key, $pending_site, DAY_IN_SECONDS);

		$membership->delete_pending_site();
	}

	/**
	 * Reclaim an orphaned pending_site when a WooCommerce order completes.
	 *
	 * When a membership is cancelled, `handle_pending_site_on_cancellation`
	 * stores the pending_site in a 24-hour transient keyed by the customer's
	 * billing email hash. If the customer then completes a new WooCommerce
	 * order, this method reclaims the pending_site, reactivates their
	 * cancelled membership, triggers async site provisioning, and links the
	 * WC order to the membership via `_wu_membership_id` post meta.
	 *
	 * Transient key format: `wu_transferable_pending_` . md5( $email )
	 *
	 * @since 2.3.2
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return void
	 */
	public function reclaim_pending_site_on_wc_order_completion($order_id): void {

		if ( ! function_exists('wc_get_order')) {
			return;
		}

		$order = wc_get_order($order_id);

		if ( ! $order) {
			return;
		}

		$billing_email = $order->get_billing_email();

		if ( ! $billing_email) {
			return;
		}

		$transient_key = 'wu_transferable_pending_' . md5($billing_email);
		$pending_site  = get_transient($transient_key);

		if ( ! $pending_site) {
			return;
		}

		$wp_user = get_user_by('email', $billing_email);

		if ( ! $wp_user) {
			return;
		}

		$customer = wu_get_customer_by_user_id($wp_user->ID);

		if ( ! $customer) {
			return;
		}

		$memberships = wu_get_memberships(
			[
				'customer_id' => $customer->get_id(),
				'status'      => Membership_Status::CANCELLED,
				'number'      => 1,
				'orderby'     => 'date_created',
				'order'       => 'DESC',
			]
		);

		if (empty($memberships)) {
			return;
		}

		$membership = $memberships[0];

		$result = $membership->reactivate();

		if (is_wp_error($result)) {
			wu_log_add(self::LOG_FILE_NAME, $result->get_error_message(), LogLevel::ERROR);
			return;
		}

		$membership->update_pending_site($pending_site);

		update_post_meta(absint($order_id), '_wu_membership_id', $membership->get_id());

		delete_transient($transient_key);

		$membership->publish_pending_site_async();
	}

	/**
	 * Transfer a membership from a user to another.
	 *
	 * @since 2.0.0
	 *
	 * @param int $membership_id The ID of the membership being transferred.
	 * @param int $target_customer_id The new owner.
	 * @return void
	 */
	public function async_transfer_membership($membership_id, $target_customer_id) {

		global $wpdb;

		$membership = wu_get_membership($membership_id);

		$target_customer = wu_get_customer($target_customer_id);

		if ( ! $membership || ! $target_customer || absint($membership->get_customer_id()) === absint($target_customer->get_id())) {
			wu_log_add(self::LOG_FILE_NAME, __('An unexpected error happened.', 'ultimate-multisite'), LogLevel::ERROR);
			return;
		}

		$wpdb->query('START TRANSACTION'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		try {
			/*
			 * Get Sites and move them over.
			 */
			$sites = wu_get_sites(
				[
					'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'membership_id' => [
							'key'   => 'wu_membership_id',
							'value' => $membership->get_id(),
						],
					],
				]
			);

			foreach ($sites as $site) {
				$site->set_customer_id($target_customer_id);

				$saved = $site->save();

				if (is_wp_error($saved)) {
					$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					wu_log_add(self::LOG_FILE_NAME, $saved->get_error_message(), LogLevel::ERROR);
					return;
				}
			}

			/*
			 * Change the membership
			 */
			$membership->set_customer_id($target_customer_id);

			$saved = $membership->save();

			if (is_wp_error($saved)) {
				$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

				wu_log_add(self::LOG_FILE_NAME, $saved->get_error_message(), LogLevel::ERROR);
				return;
			}
			$wpdb->query('COMMIT'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} catch (\Throwable $e) {
			$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			wu_log_add(self::LOG_FILE_NAME, $e->getMessage(), LogLevel::ERROR);
		} finally {
			$membership->unlock();
		}
	}

	/**
	 * Delete a membership.
	 *
	 * @since 2.0.0
	 *
	 * @param int $membership_id The ID of the membership being deleted.
	 * @return void
	 */
	public function async_delete_membership($membership_id) {

		global $wpdb;

		$membership = wu_get_membership($membership_id);

		if ( ! $membership) {
			wu_log_add(self::LOG_FILE_NAME, __('An unexpected error happened.', 'ultimate-multisite'), LogLevel::ERROR);
			return;
		}

		$wpdb->query('START TRANSACTION'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		try {
			/*
			 * Get Sites and delete them.
			 */
			$sites = wu_get_sites(
				[
					'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'membership_id' => [
							'key'   => 'wu_membership_id',
							'value' => $membership->get_id(),
						],
					],
				]
			);

			foreach ($sites as $site) {
				$saved = $site->delete();

				if (is_wp_error($saved)) {
					$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					wu_log_add(self::LOG_FILE_NAME, $saved->get_error_message(), LogLevel::ERROR);
					return;
				}
			}

			/*
			 * Delete the membership
			 */
			$saved = $membership->delete();

			if (is_wp_error($saved)) {
				$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				wu_log_add(self::LOG_FILE_NAME, $saved->get_error_message(), LogLevel::ERROR);
				return;
			}
		} catch (\Throwable $e) {
			$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wu_log_add(self::LOG_FILE_NAME, $e->getMessage(), LogLevel::ERROR);
			return;
		}

		$wpdb->query('COMMIT'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
