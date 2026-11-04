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

		add_action('wu_send_broadcast_email', [$this, 'send_broadcast_email'], 10, 3);

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
					$to[] = [
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

			try {
				$status = ! empty($to);

				foreach ($to as $recipient) {
					$action_id = wu_enqueue_async_action(
						'wu_send_broadcast_email',
						[$from, $recipient, $send_args],
						'wu_broadcast_emails'
					);

					if ( ! $action_id) {
						wp_send_json_error(new \WP_Error('queue-error', __('Unable to queue all broadcast emails. Some emails may already be queued; check Scheduled Actions before retrying.', 'ultimate-multisite')));
					}
				}
			} catch (\Throwable $e) {
				$error = new \WP_Error($e->getCode(), $e->getMessage());

				wp_send_json_error($error);
			}

			if ($status) {
				$args['targets'] = [
					'customers' => $args['target_customers'],
					'products'  => $args['target_products'],
				];

				// Record the broadcast once all individual emails have been queued.
				$this->save_broadcast($args);

				wp_send_json_success(
					[
						'redirect_url' => wu_network_admin_url('wp-ultimo-broadcasts'),
					]
				);
			}
		}

		$error = new \WP_Error('mail-error', __('Something wrong happened.', 'ultimate-multisite'));

		wp_send_json_error($error);
	}

	/**
	 * Sends one queued broadcast email using the existing mail sender.
	 *
	 * @param array $from      Sender name and email address.
	 * @param array $recipient Recipient name and email address.
	 * @param array $args      Email subject, content, and template arguments.
	 * @return void
	 * @throws \RuntimeException When delivery fails, so Action Scheduler records a failed action.
	 */
	public function send_broadcast_email($from, $recipient, $args): void {

		if ( ! Sender::send_mail($from, [$recipient], $args)) {
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
