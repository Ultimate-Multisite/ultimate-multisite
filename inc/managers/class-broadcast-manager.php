<?php
/**
 * Broadcast Manager
 *
 * Handles processes related to products.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Broadcast_Manager
 * @since 2.0.0
 */

namespace WP_Ultimo\Managers;

use WP_Ultimo\Managers\Base_Manager;
use WP_Ultimo\Models\Broadcast;
use WP_Ultimo\Helpers\Sender;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles processes related to products.
 *
 * @since 2.0.0
 */
class Broadcast_Manager extends Base_Manager {

	use \WP_Ultimo\Apis\Rest_Api;
	use \WP_Ultimo\Apis\WP_CLI;
	use \WP_Ultimo\Apis\MCP_Abilities;
	use \WP_Ultimo\Apis\Command_Palette;
	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The manager slug.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $slug = 'broadcast';

	/**
	 * The model class associated to this manager.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $model_class = \WP_Ultimo\Models\Broadcast::class;

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

		add_action('wu_send_broadcast_email', [$this, 'send_broadcast_email'], 10, 2);

		/**
		 * Add unseen broadcast notices to the panel.
		 */
		if ( ! is_network_admin() && ! is_main_site()) {
			add_action('init', [$this, 'add_unseen_broadcast_notices']);
		}
	}

	/**
	 * Add unseen broadcast messages.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_unseen_broadcast_notices(): void {

		$current_customer = wu_get_current_customer();

		if ( ! $current_customer) {
			return;
		}

		$all_broadcasts = Broadcast::query(
			[
				'number'   => 10,
				'order'    => 'DESC',
				'order_by' => 'id',
				'type__in' => ['broadcast_notice'],
			]
		);

		if (isset($all_broadcasts)) {
			foreach ($all_broadcasts as $key => $broadcast) {
				if (isset($broadcast) && 'broadcast_notice' === $broadcast->get_type()) {
					$targets = $this->get_all_notice_customer_targets($broadcast->get_id());

					if ( ! is_array($targets)) {
						$targets = [$targets];
					}

					$dismissed = get_user_meta(get_current_user_id(), 'wu_dismissed_admin_notices', false);

					if (in_array($current_customer->get_id(), $targets, true) && ! in_array($broadcast->get_id(), $dismissed, true)) {
						$notice = '<span><strong>' . $broadcast->get_title() . '</strong> ' . $broadcast->get_content() . '</span>';

						WP_Ultimo()->notices->add($notice, $broadcast->get_notice_type(), 'admin', strval($broadcast->get_id()));

						WP_Ultimo()->notices->add($notice, $broadcast->get_notice_type(), 'user', strval($broadcast->get_id()));
					}
				}
			}
		}
	}

	/**
	 * Handles the broadcast message send via modal.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function handle_broadcast(): void {

		// Nonce check happens in Form_Manager::handle_form()
		$args = $_POST; // phpcs:ignore WordPress.Security.NonceVerification

		$target_customers = wu_request('target_customers', '');

		$target_products = wu_request('target_products', '');

		if ( ! $target_customers && ! $target_products) {
			wp_send_json_error(new \WP_Error('error', __('No product or customer target was selected.', 'ultimate-multisite')));
		}

		$broadcast_type = wu_request('type', 'broadcast_notice');

		$args['type'] = $broadcast_type;

		if ('broadcast_notice' === $broadcast_type) {
			$targets = [
				'customers' => $target_customers,
				'products'  => $target_products,
			];

			$args['targets'] = $targets;

			// then we save with the message status (success, fail)
			$saved = $this->save_broadcast($args);

			if (is_wp_error($saved)) {
				wp_send_json_error($saved);
			}

			$redirect = current_user_can('wu_edit_broadcasts') ? 'wp-ultimo-edit-broadcast' : 'wp-ultimo-broadcasts';

			wp_send_json_success(
				[
					'redirect_url' => add_query_arg('id', $saved->get_id(), wu_network_admin_url($redirect)),
				]
			);
		}

		if ('broadcast_email' === $args['type']) {
			$to = [];

			$targets = [];

			if ($args['target_customers']) {
				$customers = explode(',', (string) $args['target_customers']);

				$targets = array_merge($targets, $customers);
			}

			if ($args['target_products']) {
				$product_targets = explode(',', (string) $args['target_products']);

				$customers = [];

				foreach ($product_targets as $product_id) {
					$customers = array_merge($customers, wu_get_membership_customers($product_id));
				}

				$targets = array_merge($targets, $customers);
			}

			$targets = array_unique($targets);

			/**
			 * Get name and email based on user id
			 */
			foreach ($targets as $target) {
				$customer = wu_get_customer($target);

				if ($customer) {
					$to[ $customer->get_id() ] = [
						'name'  => $customer->get_display_name(),
						'email' => $customer->get_email_address(),
					];
				}
			}

			if ( ! isset($args['custom_sender'])) {
				$from = [
					'name'  => wu_get_setting('from_name', get_network_option(null, 'site_name')),
					'email' => wu_get_setting('from_email', get_network_option(null, 'admin_email')),
				];
			} else {
				$from = [
					'name'  => $args['custom_sender']['from_name'],
					'email' => $args['custom_sender']['from_email'],
				];
			}

			$template_type = wu_get_setting('email_template_type', 'html');

			$template_type = $template_type ?: 'html';

			$send_args = [
				'site_name' => get_network_option(null, 'site_name'),
				'site_url'  => get_site_url(),
				'type'      => $template_type,
				'subject'   => $args['subject'],
				'content'   => $args['content'],
			];

			$request_id = wu_request('broadcast_request_id');

			if ( ! wp_is_uuid($request_id)) {
				wp_send_json_error(new \WP_Error('request-error', __('Please reopen the broadcast form before sending.', 'ultimate-multisite')));
			}

			$args['targets'] = [
				'customers' => $target_customers,
				'products'  => $target_products,
			];

			try {
				$status = $this->queue_broadcast_emails(
					$request_id,
					$args,
					[
						'from'       => $from,
						'args'       => $send_args,
						'recipients' => $to,
					]
					);
			} catch (\Throwable $e) {
				$error = new \WP_Error($e->getCode(), $e->getMessage());

				wp_send_json_error($error);
			}

			if (is_wp_error($status)) {
				wp_send_json_error($status);
			}

			wp_send_json_success(
				[
					'redirect_url' => wu_network_admin_url('wp-ultimo-broadcasts'),
				]
			);
		}

		$error = new \WP_Error('mail-error', __('Something wrong happened.', 'ultimate-multisite'));

		wp_send_json_error($error);
	}

	/**
	 * Stores a message once and resumes queueing recipients on form retries.
	 *
	 * @param string $request_id Stable identifier generated when the form opens.
	 * @param array  $args       Broadcast attributes and targets.
	 * @param array  $message    Immutable sender, content, and recipient snapshot.
	 * @return true|\WP_Error
	 */
	private function queue_broadcast_emails($request_id, $args, $message) {

		global $wpdb;

		if (empty($message['recipients'])) {
			return new \WP_Error('recipient-error', __('No customer recipients were found.', 'ultimate-multisite'));
		}

		$args['slug'] = 'broadcast-' . md5(get_current_network_id() . ':' . get_current_user_id() . ':' . $request_id);
		$lock         = 'wu_broadcast_' . md5($wpdb->prefix . $args['slug']);

		// Use the same connection-scoped lock pattern as system email setup.
		if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- named lock, never cached.
			return new \WP_Error('queue-busy', __('This broadcast is already being queued. Please wait before retrying.', 'ultimate-multisite'));
		}

		try {
			$broadcast = wu_get_broadcast_by('slug', $args['slug']);

			if ( ! $broadcast) {
				$broadcast = $this->save_broadcast($args);

				if (is_wp_error($broadcast)) {
					return $broadcast;
				}
			}

			$stored_message = $broadcast->get_meta('broadcast_email_message');

			if ($stored_message && $stored_message !== $message) {
				return new \WP_Error('message-changed', __('This broadcast has already started. Reopen the form to send a different message or change its recipients.', 'ultimate-multisite'));
			}

			if ( ! $stored_message && ! $broadcast->update_meta('broadcast_email_message', wp_slash($message))) {
				return new \WP_Error('message-error', __('Unable to store the broadcast email. Please retry.', 'ultimate-multisite'));
			}

			// Read progress before writes invalidate the shared metadata cache.
			$queued_customers = [];

			foreach (array_keys($message['recipients']) as $customer_id) {
				if ($broadcast->get_meta('broadcast_email_action_' . $customer_id)) {
					$queued_customers[ $customer_id ] = true;
				}
			}

			foreach ($message['recipients'] as $customer_id => $recipient) {
				$progress_key = 'broadcast_email_action_' . $customer_id;

				// Keep progress even after completed scheduler actions are pruned.
				if (isset($queued_customers[ $customer_id ])) {
					continue;
				}

				$action_args = [$broadcast->get_id(), $customer_id];
				$existing    = wu_get_scheduled_actions(
					[
						'hook'     => 'wu_send_broadcast_email',
						'args'     => $action_args,
						'group'    => 'wu_broadcast_emails',
						'per_page' => 1,
					],
					'ids'
				);

				// Recover a job saved before a request stopped or a progress write failed.
				$action_id = $existing ? reset($existing) : wu_enqueue_async_action('wu_send_broadcast_email', $action_args, 'wu_broadcast_emails');

				if ( ! $action_id || ! $broadcast->update_meta($progress_key, $action_id)) {
					return new \WP_Error('queue-error', __('Unable to queue all broadcast emails. Retry this form to queue the remaining recipients without resending those already queued.', 'ultimate-multisite'));
				}

				$queued_customers[ $customer_id ] = true;
			}

			return true;
		} finally {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- named lock, never cached.
		}
	}

	/**
	 * Sends one queued broadcast email using the existing mail sender.
	 *
	 * @param int $broadcast_id Stored broadcast containing the message snapshot.
	 * @param int $customer_id  Recipient identifier within the snapshot.
	 * @return void
	 * @throws \RuntimeException When the message is missing or delivery fails.
	 */
	public function send_broadcast_email($broadcast_id, $customer_id): void {

		$broadcast = wu_get_broadcast($broadcast_id);
		$message   = $broadcast ? $broadcast->get_meta('broadcast_email_message') : false;

		if ( ! $message || ! isset($message['recipients'][ $customer_id ])) {
			throw new \RuntimeException(esc_html__('Queued broadcast message or recipient was not found.', 'ultimate-multisite'));
		}

		if ( ! Sender::send_mail($message['from'], [$message['recipients'][ $customer_id ]], $message['args'])) {
			throw new \RuntimeException(esc_html__('Broadcast email delivery failed.', 'ultimate-multisite'));
		}
	}

	/**
	 * Saves the broadcast message in the database
	 *
	 * @since 2.0.0
	 *
	 * @param array $args With the message arguments.
	 * @return Broadcast|\WP_Error
	 */
	public function save_broadcast($args) {

		$broadcast_data = [
			'type'    => $args['type'],
			'name'    => $args['subject'],
			'content' => $args['content'],
			'status'  => 'publish',
		];

		if ( ! empty($args['slug'])) {
			$broadcast_data['slug'] = $args['slug'];
		}

		$broadcast = new Broadcast($broadcast_data);

		if ('broadcast_notice' === $args['type']) {
			$broadcast->set_notice_type($args['notice_type']);
		}

		$broadcast->set_message_targets($args['targets']);

		$saved = $broadcast->save();

		return is_wp_error($saved) ? $saved : $broadcast;
	}

	/**
	 * Returns targets for a specific broadcast.
	 *
	 * @since 2.0.0
	 *
	 * @param string $object_id The broadcast object id.
	 * @param string $type The broadcast target type.
	 * @return int[]|string[] Return the broadcast targets for the specific type.
	 */
	public function get_broadcast_targets($object_id, $type) {

		$broadcast = Broadcast::get_by_id($object_id);

		$targets = $broadcast->get_message_targets();

		if (isset($targets[ $type ])) {
			if (is_string($targets[ $type ])) {
				return explode(',', $targets[ $type ]);
			}

			return (array) $targets[ $type ];
		}

		return [];
	}

	/**
	 * Returns all customer from targets.
	 *
	 * @since 2.0.0
	 *
	 * @param string $object_id The broadcast object id.
	 * @return array Return the broadcast targets for the specific type.
	 */
	public function get_all_notice_customer_targets($object_id): array {

		$customers_targets = $this->get_broadcast_targets($object_id, 'customers');

		$product_customers = [];

		foreach ($this->get_broadcast_targets($object_id, 'products') as $product) {
			$membership_customers = wu_get_membership_customers($product);

			if ($membership_customers) {
				$product_customers = array_merge((array) $membership_customers, $product_customers);
			}
		}

		return array_map('absint', array_filter(array_unique(array_merge($product_customers, $customers_targets))));
	}
}
