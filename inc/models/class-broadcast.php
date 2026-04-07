<?php
/**
 * The Broadcast model for the Broadcasts.
 *
 * @package WP_Ultimo
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Models;

use WP_Ultimo\Models\Post_Base_Model;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Broadcast model class. Implements the Base Model.
 *
 * @since 2.0.0
 */
class Broadcast extends Post_Base_Model {

	/**
	 * Meta key for migrated from ID.
	 */
	const META_MIGRATED_FROM_ID = 'migrated_from_id';

	/**
	 * Meta key for notice type.
	 */
	const META_NOTICE_TYPE = 'notice_type';

	/**
	 * Meta key for message targets.
	 */
	const META_MESSAGE_TARGETS = 'message_targets';

	/**
	 * Post model.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $model = 'broadcast';

	/**
	 * Callback function for turning IDs into objects
	 *
	 * @since  2.0.0
	 * @access public
	 * @var mixed
	 */
	protected $query_class = \WP_Ultimo\Database\Broadcasts\Broadcast_Query::class;

	/**
	 * Post type.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $type = 'broadcast_notice';

	/**
	 * Set the allowed types to prevent saving wrong types.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $allowed_types = ['broadcast_email', 'broadcast_notice'];

	/**
	 * Set the allowed status to prevent saving wrong status.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $allowed_status = ['publish', 'draft'];

	/**
	 * Broadcast status.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $status = 'publish';

	/**
	 * Notice type
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $notice_type;

	/**
	 * Constructs the object via the constructor arguments
	 *
	 * @since 2.0.7
	 *
	 * @param mixed $object_model Std object with model parameters.
	 */
	public function __construct($object_model = null) {

		$object_model = (array) $object_model;

		if ( ! wu_get_isset($object_model, 'migrated_from_id')) {
			unset($object_model['migrated_from_id']);
		}

		parent::__construct($object_model);
	}

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
			'notice_type' => 'in:info,success,warning,error',
			'status'      => 'default:publish',
			'name'        => 'default:title',
			'title'       => 'required|min:2',
			'content'     => 'required|min:3',
			// Has a default — `required` is redundant.
			'type'        => 'in:broadcast_email,broadcast_notice|default:broadcast_notice',
		];
	}

	/**
	 * Get the id of the original 1.X model that was used to generate this item on migration.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_migrated_from_id() {

		if (null === $this->migrated_from_id) {
			$this->migrated_from_id = $this->get_meta(self::META_MIGRATED_FROM_ID, 0);
		}

		return $this->migrated_from_id;
	}

	/**
	 * Set the id of the original 1.X model that was used to generate this item on migration.
	 *
	 * @since 2.0.0
	 * @param int $migrated_from_id The ID of the original 1.X model that was used to generate this item on migration.
	 * @return void
	 */
	public function set_migrated_from_id($migrated_from_id): void {

		$this->meta[ self::META_MIGRATED_FROM_ID ] = $migrated_from_id;

		$this->migrated_from_id = $this->meta[ self::META_MIGRATED_FROM_ID ];
	}

	/**
	 * Get name of the broadcast
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_name() {

		return $this->get_title();
	}

	/**
	 * Get title of the broadcast
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return $this->title;
	}

	/**
	 * Get notice type
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_notice_type() {

		if (null === $this->notice_type) {
			$this->notice_type = $this->get_meta(self::META_NOTICE_TYPE, 'success');
		}

		return $this->notice_type;
	}

	/**
	 * Get the message targets.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_message_targets() {

		return $this->get_meta(self::META_MESSAGE_TARGETS);
	}

	/**
	 * Set the message product and/or customer targets.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message_targets The targets for this broadcast.
	 * @return void
	 */
	public function set_message_targets($message_targets): void {

		$this->meta[ self::META_MESSAGE_TARGETS ] = $message_targets;
	}

	/**
	 * Set the type of the notice.
	 *
	 * @since 2.0.0
	 *
	 * @param string $notice_type Can be info, success, warning or error.
	 * @options info,success,warning,error
	 * @return void
	 */
	public function set_notice_type($notice_type): void {

		$this->meta[ self::META_NOTICE_TYPE ] = $notice_type;

		$this->notice_type = $this->meta[ self::META_NOTICE_TYPE ];
	}

	/**
	 * Set title using the name parameter.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name This broadcast name, which is used as broadcast title as well.
	 * @return void
	 */
	public function set_name($name): void {

		$this->set_title($name);
	}

	/**
	 * Adds checks to prevent saving the model with the wrong type.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type The type being set.
	 * @return void
	 */
	public function set_type($type): void {

		if ( ! in_array($type, $this->allowed_types, true)) {
			$type = 'broadcast_notice';
		}

		$this->type = $type;
	}

	/**
	 * * Adds checks to prevent saving the model with the wrong status.
	 *
	 * @since 2.0.0
	 *
	 * @param string $status The status being set.
	 * @return void
	 */
	public function set_status($status): void {

		if ( ! in_array($status, $this->allowed_status, true)) {
			$status = 'publish';
		}

		$this->status = $status;
	}

	/**
	 * Transform the object into an assoc array.
	 *
	 * Overrides Base_Model::to_array() to ensure lazy-loaded meta properties
	 * are populated before serialization so REST API responses include all fields.
	 *
	 * @since 2.0.11
	 * @return array
	 */
	public function to_array() {

		// Only trigger lazy-loading when the model has been persisted (has an ID).
		// For unsaved models (during validate/save), meta is not available and the
		// getters return default values that can fail validation rules.
		if ($this->get_id()) {
			$this->get_migrated_from_id();
			$this->get_notice_type();
		}

		return parent::to_array();
	}
}
