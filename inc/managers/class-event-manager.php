<?php
/**
 * Events Manager
 *
 * Handles processes related to events.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Event_Manager
 * @since 2.0.0
 */

namespace WP_Ultimo\Managers;

use Psr\Log\LogLevel;
use WP_Ultimo\Models\Base_Model;
use WP_Ultimo\Models\Event;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles processes related to events.
 *
 * @since 2.0.0
 */
class Event_Manager extends Base_Manager {

	use \WP_Ultimo\Apis\Rest_Api;
	use \WP_Ultimo\Apis\WP_CLI;
	use \WP_Ultimo\Apis\MCP_Abilities;
	use \WP_Ultimo\Traits\Singleton;

	const LOG_FILE_NAME = 'events';
	/**
	 * The manager slug.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $slug = 'event';

	/**
	 * The model class associated to this manager.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $model_class = \WP_Ultimo\Models\Event::class;

	/**
	 * Holds the list of available events for webhooks.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $events = [];

	/**
	 * The list of registered models events.
	 *
	 * @since 2.1.4
	 * @var array
	 */
	protected $models_events = [];

	/**
	 * Whether register_all_events() has already registered the model event types.
	 *
	 * @since 2.17.3
	 * @var bool
	 */
	protected $model_event_types_registered = false;

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

		add_action('init', [$this, 'register_all_events']);

		add_action('wp_ajax_wu_get_event_payload_preview', [$this, 'event_payload_preview']);

		add_action('rest_api_init', [$this, 'hooks_endpoint']);

		add_action('wu_model_post_save', [$this, 'log_transitions'], 10, 4);

		add_action('wu_daily', [$this, 'clean_old_events']);
	}

	/**
	 * Returns the payload to be displayed in the payload preview field.
	 * Log model transitions.
	 *
	 * @since 2.0.0
	 *
	 * @param string     $model The model name.
	 * @param array      $data The data being saved, serialized.
	 * @param array      $data_unserialized The data being saved, un-serialized.
	 * @param Base_Model $obj The object being saved.
	 *
	 * @return void
	 */
	public function log_transitions($model, $data, $data_unserialized, $obj) {

		if ('event' === $model) {
			return;
		}

		/*
		 * Editing Model
		 */
		if (wu_get_isset($data_unserialized, 'id')) {
			$original = $obj->_get_original();

			$diff = wu_array_recursive_diff($data_unserialized, $original);

			$keys_to_remove = apply_filters(
				'wu_exclude_transitions_keys',
				[
					'meta',
					'last_login',
					'ips',
					'query_class',
					'settings',
					'_compiled_product_list',
					'_gateway_info',
					'_limitations',
				]
			);

			foreach ($keys_to_remove as $key_to_remove) {
				unset($diff[ $key_to_remove ]);
			}

			/**
			 * If empty, go home.
			 */
			if (empty($diff)) {
				return;
			}

			$changed = [];

			/**
			 * Loop changed data.
			 */
			foreach ($diff as $key => $new_value) {
				$old_value = wu_get_isset($original, $key, '');

				if ('id' === $key && intval($old_value) === 0) {
					return;
				}

				if (empty(wp_json_encode($old_value)) && empty(wp_json_encode($new_value))) {
					return;
				}

				$changed[ $key ] = [
					'old_value' => $old_value,
					'new_value' => $new_value,
				];
			}

			$event_data = [
				'severity'    => Event::SEVERITY_INFO,
				'slug'        => 'changed',
				'object_type' => $model,
				'object_id'   => $obj->get_id(),
				'payload'     => $changed,
			];
		} else {
			$event_data = [
				'severity'    => Event::SEVERITY_INFO,
				'slug'        => 'created',
				'object_type' => $model,
				'object_id'   => $obj->get_id(),
				'payload'     => [],
			];
		}

		if ( ! empty($_POST) && is_user_logged_in()) { // phpcs:ignore WordPress.Security.NonceVerification
			$event_data['initiator'] = 'manual';
			$event_data['author_id'] = get_current_user_id();
		}

		wu_create_event($event_data);
	}

	/**
	 * Returns the payload to be displayed in the payload preview field.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function event_payload_preview(): void {

		if ( ! wu_request('event')) {
			wp_send_json_error(new \WP_Error('error', __('No event was selected.', 'ultimate-multisite')));
		}

		$slug = wu_request('event');

		if ( ! $slug) {
			wp_send_json_error(new \WP_Error('not-found', __('Event was not found.', 'ultimate-multisite')));
		}

		$event = wu_get_event_type($slug);

		if ( ! $event) {
			wp_send_json_error(new \WP_Error('not-found', __('Data not found.', 'ultimate-multisite')));
		} else {
			$payload = isset($event['payload']) ? wu_maybe_lazy_load_payload($event['payload']) : '{}';

			$payload = array_map('htmlentities2', $payload);

			wp_send_json_success($payload);
		}
	}

	/**
	 * Returns the list of event types to register.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_event_type_as_options() {
		/*
		 * We use this to order the options.
		*/
		$event_type_settings = wu_get_setting('saving_type', []);

		$types = [
			'id'         => '$id',
			'title'      => '$title',
			'desc'       => '$desc',
			'class_name' => '$class_name',
			'active'     => 'in_array($id, $active_gateways, true)',
			'gateway'    => '$class_name', // Deprecated.
			'hidden'     => false,
		];

		$types = array_filter($types, fn($item) => false === $item['hidden']);

		return $types;
	}

	/**
	 * Add a new event.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug The slug of the event. Something like payment_received.
	 * @param array  $payload with the events information.
	 *
	 * @return bool
	 */
	public function do_event($slug, $payload) {

		$registered_event = $this->get_event($slug);

		if ( ! $registered_event) {
			wu_log_add(self::LOG_FILE_NAME, 'Event not found', LogLevel::ERROR);
			return false;
		}

		$payload_diff = array_diff_key(wu_maybe_lazy_load_payload($registered_event['payload']), $payload);

		if (! empty($payload_diff[0])) {
			foreach ($payload_diff[0] as $diff_key => $diff_value) {
				wu_log_add(self::LOG_FILE_NAME, 'Param required:' . $diff_key, LogLevel::ERROR);
			}
			return false;
		}

		$payload['wu_version'] = wu_get_version();

		do_action('wu_event', $slug, $payload);

		do_action("wu_event_{$slug}", $payload);

		/**
		 * Saves in the database
		 */
		return $this->save_event($slug, $payload);
	}

	/**
	 * Register a new event to be used as param.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug The slug of the event. Something like payment_received.
	 * @param array  $args with the events information.
	 *
	 * @return true
	 */
	public function register_event($slug, $args): bool {

		$this->events[ $slug ] = $args;

		return true;
	}

	/**
	 * Returns the list of available webhook events.
	 *
	 * @since 2.0.0
	 * @return array $events with all events.
	 */
	public function get_events() {

		return $this->events;
	}

	/**
	 * Returns the list of available webhook events.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug of the event.
	 * @return array|false $event with event params.
	 */
	public function get_event($slug) {

		$events = $this->get_events();

		if ($events) {
			foreach ($events as $key => $event) {
				if ($key === $slug) {
					return $event;
				}
			}
		}

		return false;
	}

	/**
	 * Saves event in the database.
	 *
	 * @param string $slug of the event.
	 * @param array  $payload with event params.
	 * @return bool.
	 */
	public function save_event($slug, $payload): bool {

		$event = new Event(
			[
				'object_id'    => wu_get_isset($payload, 'object_id', ''),
				'object_type'  => wu_get_isset($payload, 'object_type', ''),
				'severity'     => wu_get_isset($payload, 'type', Event::SEVERITY_INFO),
				'date_created' => wu_get_current_time('mysql', true),
				'initiator'    => 'system',
				'slug'         => strtolower($slug),
				'payload'      => $payload,
			]
		);

		$return = $event->save();
		if ( is_wp_error($return)) {
			return false;
		}
		return (bool) $return;
	}

	/**
	 * Registers the list of default events.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_all_events(): void {

		/**
		 * Payment Received.
		 */
		wu_register_event_type(
			'payment_received',
			[
				'name'            => __('Payment Received', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new payment is received, regardless of the payment status.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('payment'),
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [
					'user_id' => 'customer_user_id',
					'amount'  => 'payment_total',
					'gateway' => 'payment_gateway',
					'status'  => 'payment_status',
					'date'    => 'payment_date_created',
				],
			]
		);

		/**
		 * Site Published.
		 */
		wu_register_event_type(
			'site_published',
			[
				'name'            => __('Site Published', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new site is created tied to a membership, or transitions from a pending state to a published state.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('site'),
					wu_generate_event_payload('customer'),
					wu_generate_event_payload('membership')
				),
				'deprecated_args' => [],
			]
		);

		/**
		 * Confirm Email Address
		 */
		wu_register_event_type(
			'confirm_email_address',
			[
				'name'            => __('Email Verification Needed', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new customer is added with an email verification status of pending.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					[
						'verification_link' => 'https://linktoverifyemail.com',
					],
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [],
			]
		);

		/**
		 * Domain Mapping Added
		 */
		wu_register_event_type(
			'domain_created',
			[
				'name'            => __('New Domain Mapping Added', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new domain mapping is added by a customer.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('domain'),
					wu_generate_event_payload('site'),
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [
					'user_id'       => 1,
					'user_site_id'  => 1,
					'mapped_domain' => 'mydomain.com',
					'user_site_url' => 'http://test.mynetwork.com/',
					'network_ip'    => '125.399.3.23',
				],
			]
		);

		/**
		 * Renewal payment created
		 */
		wu_register_event_type(
			'renewal_payment_created',
			[
				'name'            => __('New Renewal Payment Created', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time a new renewal payment is created by Ultimate Multisite.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					[
						'default_payment_url' => 'https://linktopayment.com',
					],
					wu_generate_event_payload('payment'),
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [],
			]
		);

		/**
		 * Invoice Sent
		 */
		wu_register_event_type(
			'invoice_sent',
			[
				'name'            => __('Invoice Sent', 'ultimate-multisite'),
				'desc'            => __('This event is fired every time an invoice is sent to a customer by a network admin.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					[
						'payment_url'     => 'https://linktopayment.com',
						'invoice_message' => 'Example message to the customer.',
					],
					wu_generate_event_payload('payment'),
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [],
			]
		);

		/**
		 * Payment Failed.
		 */
		wu_register_event_type(
			'payment_failed',
			[
				'name'            => __('Recurring Payment Failed', 'ultimate-multisite'),
				'desc'            => __('Fired when an auto-renewing payment fails (Stripe/PayPal).', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer'),
					[
						'payment_gateway' => 'stripe',
					]
				),
				'deprecated_args' => [],
			]
		);

		/**
		 * Membership Expired.
		 */
		wu_register_event_type(
			'membership_expired',
			[
				'name'            => __('Membership Expired', 'ultimate-multisite'),
				'desc'            => __('Fired when a membership transitions to expired status.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer')
				),
				'deprecated_args' => [],
			]
		);

		/**
		 * Trial or membership expiring.
		 */
		wu_register_event_type(
			'membership_expiring',
			[
				'name'            => __('Trial or Membership Expiring', 'ultimate-multisite'),
				'desc'            => __('Fired once when a free trial, or a membership that does not renew automatically, reaches the "Days to Expire" window set in Settings > Emails. With 0 days it fires when the trial or membership ends.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer'),
					[
						'expiring_is_trial'      => true,
						'expiring_is_membership' => false,
						'expiring_will_renew'    => false,
						'expiring_needs_action'  => true,
						'expiring_end_date'      => gmdate('Y-m-d 23:59:59', strtotime('+3 days')),
						'expiring_date'          => wp_date(get_option('date_format'), strtotime('+3 days')),
						'expiring_days_left'     => 3,
					]
				),
				'deprecated_args' => [],
			]
		);

		/**
		 * Demo Site Expiring.
		 */
		wu_register_event_type(
			'demo_site_expiring',
			[
				'name'            => __('Demo Site Expiring', 'ultimate-multisite'),
				'desc'            => __('Fired when a demo site is about to expire, based on the warning time setting.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					wu_generate_event_payload('site'),
					wu_generate_event_payload('membership'),
					wu_generate_event_payload('customer'),
					[
						'demo_expires_at'     => '2025-12-31 23:59:59',
						'demo_time_remaining' => '1 hour',
						'site_admin_url'      => 'https://example.com/wp-admin/',
						'site_url'            => 'https://example.com/',
					]
				),
				'deprecated_args' => [],
			]
		);

		foreach ($this->models_events as $model => $params) {
			$this->register_model_event_types($model, $params);
		}

		$this->model_event_types_registered = true;

		do_action('wu_register_all_events');
	}

	/**
	 * Registers the event types (name, description, payload) for a model.
	 *
	 * @since 2.17.3
	 *
	 * @param string $model  The model slug.
	 * @param array  $params The model event params (label and types).
	 * @return void
	 */
	protected function register_model_event_types(string $model, array $params): void {

		foreach ($params['types'] as $type) {
			wu_register_event_type(
				$model . '_' . $type,
				[
					// translators: %1$s is the model name, %2$s is the event type.
					'name'            => sprintf(__('%1$s %2$s', 'ultimate-multisite'), $params['label'], ucfirst($type)),
					// translators: %1$s is the model name, %2$s is the event type.
					'desc'            => sprintf(__('This event is fired every time a %1$s is %2$s by Ultimate Multisite.', 'ultimate-multisite'), $params['label'], $type),
					'deprecated_args' => [],
					'payload'         => fn() => $this->get_model_payload($model),
				]
			);
		}
	}

	/**
	 * Register models events
	 *
	 * The dispatcher is attached to the model's post_save hook immediately,
	 * so model events fire regardless of whether this runs before or after
	 * register_all_events() on the same `init` priority.
	 *
	 * @param string $slug slug of event.
	 * @param string $label label of event.
	 * @param array  $event_types event types allowed.
	 * @since 2.1.4
	 */
	public static function register_model_events(string $slug, string $label, array $event_types): void {

		$instance = self::get_instance();

		$instance->models_events[ $slug ] = [
			'label' => $label,
			'types' => $event_types,
		];

		if ( ! has_action("wu_{$slug}_post_save", [$instance, 'dispatch_base_model_event'])) {
			add_action("wu_{$slug}_post_save", [$instance, 'dispatch_base_model_event'], 10, 3);
		}

		/*
		 * If the event types were already registered, register this
		 * model's event types now so they show up in the events list.
		 */
		if ($instance->model_event_types_registered) {
			$instance->register_model_event_types($slug, $instance->models_events[ $slug ]);
		}
	}

	/**
	 * Dispatch registered model events
	 *
	 * @param array $data Data.
	 * @param mixed $obj Object.
	 * @param bool  $new_model New.
	 *
	 * @since 2.1.4
	 */
	public function dispatch_base_model_event(array $data, $obj, bool $new_model): void {

		$model = $obj->model;

		$type = $new_model ? 'created' : 'updated';

		$registered_model = wu_get_isset($this->models_events, $model);

		if ( ! $registered_model || ! in_array($type, $registered_model['types'], true)) {
			return;
		}

		$payload = $this->get_model_payload($model, $obj);

		wu_do_event($model . '_' . $type, $payload);
	}

	/**
	 * Returns the full payload for a given model.
	 *
	 * @param string      $model The model name.
	 * @param object|null $model_object The model object.
	 * @return array
	 *
	 * @since 2.3.0
	 */
	public function get_model_payload(string $model, ?object $model_object = null) {

		$obj     = $model_object ?? call_user_func("wu_mock_{$model}");
		$payload = wu_generate_event_payload($model, $obj);

		if (method_exists($obj, 'get_membership')) {
			$membership = $model_object ? $obj->get_membership() : false;

			$payload = array_merge(
				$payload,
				wu_generate_event_payload('membership', $membership)
			);
		}

		if (method_exists($obj, 'get_customer')) {
			$customer = $model_object ? $obj->get_customer() : false;

			$payload = array_merge(
				$payload,
				wu_generate_event_payload('customer', $customer)
			);
		}

		if (method_exists($obj, 'get_billing_address') || method_exists($obj, 'get_membership')) {
			/*
			 * Objects without a billing address of their own (e.g. payments)
			 * borrow it from their membership, which may not exist.
			 */
			$billing_source = null;

			if (null !== $model_object) {
				$billing_source = method_exists($obj, 'get_billing_address') ? $obj : $obj->get_membership();
			}

			if ($billing_source) {
				$payload = array_merge(
					$payload,
					$billing_source->get_billing_address()->to_array()
				);
			} else {
				$payload = array_merge(
					$payload,
					array_map(
						fn() => '',
						\WP_Ultimo\Objects\Billing_Address::fields()
					)
				);
			}
		}

		return $payload;
	}

	/**
	 * Every day, deletes old events that we don't want to keep.
	 *
	 * @since 2.0.0
	 */
	public function clean_old_events(): void {
		/*
		 * Add a filter setting this to 0 or false
		 * to prevent old events from being ever deleted.
		 */
		$threshold_days = apply_filters('wu_events_threshold_days', 1);

		if (empty($threshold_days)) {
			return;
		}

		$events_to_remove = wu_get_events(
			[
				'number'     => 100,
				'date_query' => [
					'column'    => 'date_created',
					'before'    => "-{$threshold_days} days",
					'inclusive' => true,
				],
			]
		);

		$success_count = 0;

		foreach ($events_to_remove as $event) {
			$status = $event->delete();

			if ( ! is_wp_error($status) && $status) {
				++$success_count;
			}
		}

		// Translators:  1: Number of successfully removed events.  2: Number of failed events to remove.
		wu_log_add('wu-cron', sprintf(__('Removed %1$d events successfully. Failed to remove %2$d events.', 'ultimate-multisite'), $success_count, count($events_to_remove) - $success_count));
	}

	/**
	 * Create a endpoint to retrieve all available event hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public function hooks_endpoint(): void {

		if ( ! wu_get_setting('enable_api', true)) {
			return;
		}

		$api = \WP_Ultimo\API::get_instance();

		register_rest_route(
			$api->get_namespace(),
			'/hooks',
			[
				'methods'             => 'GET',
				'callback'            => [$this, 'get_hooks_rest'],
				'permission_callback' => [$api, 'check_authorization'],
			]
		);
	}

	/**
	 * Return all event types for the REST API request.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request The request sent.
	 * @return mixed
	 */
	public function get_hooks_rest($request) {

		$response = wu_get_event_types();

		foreach ($response as $key => $value) {
			$payload = wu_get_isset($value, 'payload');

			if (is_callable($payload)) {
				$response[ $key ]['payload'] = $payload();
			}
		}

		return rest_ensure_response($response);
	}
}
