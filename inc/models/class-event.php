<?php
/**
 * The Event model for the Event Mappings.
 *
 * @package WP_Ultimo
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Models;

use WP_Ultimo\Models\Base_Model;
use WP_User;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Event model class. Implements the Base Model.
 *
 * @since 2.0.0
 */
class Event extends Base_Model {

	const SEVERITY_SUCCESS = 1;

	const SEVERITY_NEUTRAL = 2;

	const SEVERITY_INFO = 3;

	const SEVERITY_WARNING = 4;

	const SEVERITY_FATAL = 5;

	/**
	 * Severity of the problem.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $severity;

	/**
	 * Date when the event was created.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_created;

	/**
	 * Initiator of this event.
	 *
	 * Events can be run by Ultimate Multisite, saved as 'system', or
	 * by people (admins, customers), saved as 'manual'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $initiator;

	/**
	 * The author of the action, saved as the user_id.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $author_id = 0;

	/**
	 * Object type associated with this event.
	 *
	 * Can be one one the following:
	 * - network, for events concerning the entire network;
	 * - site, for events that concern a specific site;
	 * - customer, for events that concern a specific customer;
	 * - domain, for events that concern a specific domain;
	 * - membership, for events that concern a specific membership;
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $object_type;

	/**
	 * ID of the related objects.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $object_id;

	/**
	 * Slug of the event.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $slug = '';

	/**
	 * Payload of the event.
	 *
	 * @since 2.0.0
	 * @var object
	 */
	protected $payload;

	/**
	 * Query Class to the static query methods.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Events\Event_Query::class;

	/**
	 * Set the validation rules for this particular model.
	 *
	 * To see how to setup rules, check the documentation of the
	 * validation library we are using: https://github.com/rakit/validation
	 *
	 * @since 2.0.0
	 * @link https://github.com/rakit/validation
	 * @return array
	 */
	public function validation_rules() {

		return [
			'severity'    => 'required|numeric|between:1,5',
			'payload'     => 'required',
			'object_type' => 'required|alpha_dash|lowercase',
			'object_id'   => 'integer|default:0',
			'author_id'   => 'integer|default:0',
			'slug'        => 'required|alpha_dash',
			'initiator'   => 'required|in:system,manual',
		];
	}

	/**
	 * Get severity of the problem..
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_severity() {

		return (int) $this->severity;
	}

	/**
	 * Returns the Label for a given severity level.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_severity_label() {

		$labels = [
			self::SEVERITY_SUCCESS => __('Success', 'ultimate-multisite'),
			self::SEVERITY_NEUTRAL => __('Neutral', 'ultimate-multisite'),
			self::SEVERITY_INFO    => __('Info', 'ultimate-multisite'),
			self::SEVERITY_WARNING => __('Warning', 'ultimate-multisite'),
			self::SEVERITY_FATAL   => __('Fatal', 'ultimate-multisite'),
		];

		return $labels[ $this->get_severity() ] ?? __('Note', 'ultimate-multisite');
	}

	/**
	 * Gets the classes for a given severity level.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_severity_class() {

		$classes = [
			self::SEVERITY_SUCCESS => 'wu-bg-green-200 wu-text-green-700',
			self::SEVERITY_NEUTRAL => 'wu-bg-gray-200 wu-text-gray-700',
			self::SEVERITY_INFO    => 'wu-bg-blue-200 wu-text-blue-700',
			self::SEVERITY_WARNING => 'wu-bg-yellow-200 wu-text-yellow-700',
			self::SEVERITY_FATAL   => 'wu-bg-red-200 wu-text-red-700',
		];

		return $classes[ $this->get_severity() ] ?? '';
	}

	/**
	 * Set severity of the problem..
	 *
	 * @since 2.0.0
	 * @param int $severity Severity of the problem.
	 * @return void
	 */
	public function set_severity($severity): void {

		$this->severity = $severity;
	}

	/**
	 * Get date when the event was created..
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_created() {

		return $this->date_created;
	}

	/**
	 * Set date when the event was created..
	 *
	 * @since 2.0.0
	 * @param string $date_created Date when the event was created.
	 * @return void
	 */
	public function set_date_created($date_created): void {

		$this->date_created = $date_created;
	}

	/**
	 * Get payload of the event..
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function get_payload() {

		$payload = (array) maybe_unserialize(wp_unslash($this->payload));

		return $payload;
	}

	/**
	 * Set payload of the event..
	 *
	 * @since 2.0.0
	 * @param object $payload Payload of the event.
	 * @return void
	 */
	public function set_payload($payload): void {

		$this->payload = $payload;
	}

	/**
	 * Get message for the event.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_message() {

		$message = self::get_default_system_messages($this->slug);

		return $this->interpolate_message($message, $this->get_payload());
	}

	/**
	 * Interpolates the value of a message and its placeholders with the contents of the payload.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message The message with placeholders.
	 * @param array  $payload Key => value based array.
	 */
	public function interpolate_message($message, $payload): string {

		$payload = json_decode(json_encode($payload), true); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		/*
		 * The message templates (get_default_system_messages) intentionally
		 * contain HTML (e.g. <strong>{{model}}</strong>) and the rendered result
		 * is output with Vue v-html in the activity-stream widget. The
		 * interpolated payload values, however, include attacker-influenced model
		 * fields (customer display names, site titles, etc.), so every value must
		 * be HTML-escaped before substitution to prevent stored XSS — while the
		 * template's own markup is preserved.
		 */
		$interpolation = [];

		foreach ($payload as $key => $value) {
			if (is_array($value)) {
				$value = implode(' &rarr; ', array_map('esc_html', wu_array_flatten($value)));
			} else {
				$value = esc_html((string) $value);
			}

			$interpolation[ "{{{$key}}}" ] = $value;
		}

		$interpolation['{{payload}}'] = implode(' - ', array_map('esc_html', wu_array_flatten($payload, true)));

		$interpolation['{{model}}'] = esc_html(wu_slug_to_name($this->object_type));

		$interpolation['{{object_id}}'] = esc_html((string) $this->object_id);

		return strtr($message, $interpolation);
	}

	/**
	 * Returns the default system messages for events.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug The slug of the event.
	 * @return string
	 */
	public static function get_default_system_messages($slug) {

		$default_messages = [];

		$default_messages['changed'] = __('The <strong>{{model}}</strong> #{{object_id}} was changed: {{payload}}', 'ultimate-multisite');
		$default_messages['created'] = __('The <strong>{{model}}</strong> #{{object_id}} was created.', 'ultimate-multisite');

		$default_messages = apply_filters('wu_get_default_system_messages', $default_messages);

		return wu_get_isset($default_messages, $slug, __('No Message', 'ultimate-multisite'));
	}


	/**
	 * Get by people (admins, customers), saved as 'manual'.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_initiator() {

		return $this->initiator;
	}

	/**
	 * Set by people (admins, customers), saved as 'manual'.
	 *
	 * @since 2.0.0
	 *
	 * @param string $initiator The type of user responsible for initiating the event. There are two options: Manual and System. By default, the event is saved as manual.
	 * @return void
	 */
	public function set_initiator($initiator): void {

		$this->initiator = $initiator;
	}

	/**
	 * Get the author of the action, saved as the user_id.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_author_id() {

		return $this->author_id;
	}

	/**
	 * Returns the user associated with this author.
	 *
	 * @since 2.0.0
	 * @return ?WP_User
	 */
	public function get_author_user() {

		if ($this->author_id) {
			$user = get_user_by('id', $this->author_id);

			if ($user) {
				return $user;
			}
		}
	}

	/**
	 * Returns the authors' display name.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_author_display_name() {

		$user = $this->get_author_user();

		if ($user) {
			return $user->display_name;
		}
		return '';
	}

	/**
	 * Returns the authors' email address.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_author_email_address() {

		$user = $this->get_author_user();

		if ($user) {
			return $user->user_email;
		}
		return '';
	}

	/**
	 * Set the author of the action, saved as the user_id.
	 *
	 * @since 2.0.0
	 * @param int $author_id The user responsible for creating the event. By default, the event is saved with the current user_id.
	 * @return void
	 */
	public function set_author_id($author_id): void {

		$this->author_id = $author_id;
	}

	/**
	 * Get the object of this event.
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function get_object() {

		$object_type = $this->get_object_type();

		$function_name = "wu_get_{$object_type}";

		if (function_exists($function_name)) {
			return $function_name($this->get_object_id());
		}

		return false;
	}

	/**
	 * Polyfill for the get_object method.
	 *
	 * @since 2.0.0
	 * @return false|object
	 */
	public function get_membership() {

		$object_type = $this->get_object_type();

		if ('membership' !== $object_type) {
			return false;
		}

		return $this->get_object();
	}

	/**
	 * Polyfill for the get_object method.
	 *
	 * @since 2.0.0
	 * @return false|object
	 */
	public function get_product() {

		$object_type = $this->get_object_type();

		if ('product' !== $object_type) {
			return false;
		}

		return $this->get_object();
	}

	/**
	 * Polyfill for the get_object method.
	 *
	 * @since 2.0.0
	 * @return false|object
	 */
	public function get_site() {

		$object_type = $this->get_object_type();

		if ('site' !== $object_type) {
			return false;
		}

		return $this->get_object();
	}

	/**
	 * Polyfill for the get_object method.
	 *
	 * @since 2.0.0
	 * @return false|object
	 */
	public function get_customer() {

		$object_type = $this->get_object_type();

		if ('customer' !== $object_type) {
			return false;
		}

		return $this->get_object();
	}

	/**
	 * Polyfill for the get_object method.
	 *
	 * @since 2.0.0
	 * @return false|object
	 */
	public function get_payment() {

		$object_type = $this->get_object_type();

		if ('payment' !== $object_type) {
			return false;
		}

		return $this->get_object();
	}

	/**
	 * Get the object type associated with this event.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_object_type() {

		return $this->object_type;
	}

	/**
	 * Set the object type associated with this event.
	 *
	 * @since 2.0.0
	 * @param string $object_type The type of object related to this event. It's usually the model name.
	 * @return void
	 */
	public function set_object_type($object_type): void {

		$this->object_type = $object_type;
	}

	/**
	 * Get the object type associated with this event.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_slug() {

		return $this->slug;
	}

	/**
	 * Set the object type associated with this event.
	 *
	 * @since 2.0.0
	 * @param string $slug The event slug. It needs to be unique and preferably make it clear what it is about. Example: account_created is about creating an account.
	 * @return void
	 */
	public function set_slug($slug): void {

		$this->slug = $slug;
	}

	/**
	 * Get iD of the related objects..
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_object_id() {

		return $this->object_id;
	}

	/**
	 * Set iD of the related objects.
	 *
	 * @since 2.0.0
	 * @param int $object_id The ID of the related objects.
	 * @return void
	 */
	public function set_object_id($object_id): void {

		$this->object_id = $object_id;
	}

	/**
	 * Transform the object into an assoc array.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function to_array() {

		$array = parent::to_array();

		$array['payload'] = $this->get_payload();

		$array['message'] = $this->get_message();

		$array['severity_label'] = $this->get_severity_label();

		$array['severity_classes'] = $this->get_severity_class();

		$array['author'] = [];

		if ($this->get_initiator() === 'manual') {
			$user = get_user_by('ID', $this->get_author_id());

			if ($user) {
				$array['author'] = (array) $user->data;

				unset($array['author']['user_pass']);
				unset($array['author']['user_activation_key']);

				$array['author']['avatar'] = get_avatar_url(
					$this->get_author_id(),
					[
						'default' => 'identicon',
					]
				);
			}
		}

		return $array;
	}

	/**
	 * Override to clear event count.
	 *
	 * @return bool|\WP_Error
	 * @since 2.0.0
	 */
	public function save() {

		if ( ! $this->exists() && function_exists('get_current_user_id')) {
			$user_id = get_current_user_id();

			delete_site_transient("wu_{$user_id}_unseen_events_count");
		}

		return parent::save();
	}
}
