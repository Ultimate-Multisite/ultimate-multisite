<?php
/**
 * Base limit module class.
 *
 * @package WP_Ultimo
 * @subpackage Limitations
 * @since 2.0.0
 */

namespace WP_Ultimo\Limitations;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Base limit module class.
 *
 * @since 2.0.0
 */
abstract class Limit implements \JsonSerializable {

	/**
	 * The module id.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $id;

	/**
	 * The limit data.
	 *
	 * Here, each module will have different schemas.
	 * From simple int values for disk_space, for example,
	 * to arrays with fields, like plugins and post_types.
	 *
	 * @since 2.0.0
	 * @var mixed
	 */
	protected $limit;

	/**
	 * The on/off status of the module.
	 *
	 * Limitations are only applied if the module
	 * is enabled.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $enabled;

	/**
	 * Controls if this limit has its own limit.
	 *
	 * When the limit is inherited from other models,
	 * such as memberships or products.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	private bool $has_own_limit = true;

	/**
	 * Controls if this limit has its own enabled status.
	 *
	 * When the enabled is inherited from other models,
	 * such as memberships or products.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	private bool $has_own_enabled = true;

	/**
	 * Allows sub-type limits to set their own default value for enabled.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected bool $enabled_default_value = true;

	/**
	 * Constructs the limit module.
	 *
	 * @since 2.0.0
	 * @param array $data The module data.
	 */
	public function __construct($data) {

		$this->setup($data);
	}

	/**
	 * Prepare for serialization.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function __serialize() {

		return serialize($this->to_array()); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Handles un-serialization.
	 *
	 * @since 2.0.0
	 *
	 * @param string $data The un-serialized data.
	 * @return void
	 */
	public function __unserialize($data) {

		$this->setup(unserialize($data));  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	}

	/**
	 * Sets up the module based on the module data.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data The module data.
	 * @return void
	 */
	public function setup($data): void {

		if ( ! is_array($data)) {
			$data = (array) $data;
		}

		$current_limit = wu_get_isset($data, 'limit', 'not-set');

		/*
		 * Sets the own limit flag, if necessary.
		 */
		if ('not-set' === $current_limit || '' === $current_limit) {
			$this->has_own_limit = false;
		}

		/*
		 * Sets the own enabled flag, if necessary.
		 *
		 * Treat empty string the same as "not set" — when limitations are
		 * serialized through the WooCommerce addon or other external flows,
		 * the enabled flag can arrive as '' instead of being absent entirely.
		 * Casting '' to bool yields false, which incorrectly disables the
		 * capability instead of inheriting from the parent (plan/membership).
		 *
		 * @since 2.7.1
		 */
		$current_enabled = wu_get_isset($data, 'enabled', 'not-set');

		if ('not-set' === $current_enabled || '' === $current_enabled) {
			$this->has_own_enabled = false;

			unset($data['enabled']);
		}

		$data = wp_parse_args(
			$data,
			[
				'limit'   => null,
				'enabled' => $this->enabled_default_value,
			]
		);

		$this->limit   = is_array($data['limit']) ? (object) $data['limit'] : $data['limit'];
		$this->enabled = (bool) $data['enabled'];

		do_action("wu_{$this->id}_limit_setup", $data, $this);
	}

	/**
	 * Returns the id of the module.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_id() {

		return $this->id;
	}

	/**
	 * Checks if a value is allowed under this limit.
	 *
	 * This function is what you should be calling when validating
	 * limits. This method is final, so it can't be redefined.
	 *
	 * Limits should implement a check method that gets
	 * called in here.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $value_to_check Value to check.
	 * @param string $type The type parameter.
	 * @return bool
	 */
	final public function allowed($value_to_check, $type = '') {

		$allowed = $this->is_enabled();

		if ($allowed) {
			$allowed = $this->check($value_to_check, $this->limit, $type);
		}

		return apply_filters("wu_limit_{$this->id}_{$type}_allowed", $allowed, $type, $this);
	}

	/**
	 * The check method is what gets called when allowed is called.
	 *
	 * Each module needs to implement a check method, that returns a boolean.
	 * This check can take any form the developer wants.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $value_to_check Value to check.
	 * @param mixed  $limit The list of limits in this modules.
	 * @param string $type Type for sub-checking.
	 * @return bool
	 */
	abstract public function check($value_to_check, $limit, $type = '');

	/**
	 * Gets the limit data.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Type for sub-checking.
	 * @return mixed
	 */
	public function get_limit($type = '') {

		return $this->limit;
	}

	/**
	 * Checks if the module is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Type for sub-checking.
	 * @return boolean
	 */
	public function is_enabled($type = '') {

		return $this->enabled;
	}

	/**
	 * Converts the limitations list to an array.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function to_array() {

		$array = get_object_vars($this);

		/*
		 * Removes the unnecessary data.
		 */
		unset($array['has_own_limit']);
		unset($array['has_own_enabled']);
		unset($array['enabled_default_value']);

		return $array;
	}

	/**
	 * Prepares for serialization.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {

		return wp_json_encode($this->to_array());
	}

	/**
	 * Checks if this module has its own limit.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_own_limit() {

		return $this->has_own_limit;
	}

	/**
	 * Checks if this module has its own enabled.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_own_enabled() {

		return $this->has_own_enabled;
	}

	/**
	 * Handles enabled status on post submission.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function handle_enabled() {

		$module = wu_get_isset(wu_clean(wp_unslash($_POST['modules'] ?? [])), $this->id, []); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return (bool) wu_get_isset($module, 'enabled', false);
	}

	/**
	 * Handles other elements when saving. Used for custom attributes.
	 *
	 * @since 2.0.0
	 *
	 * @param array $module The current module, extracted from the request.
	 * @return array
	 */
	public function handle_others($module) {

		return $module;
	}

	/**
	 * Handles limits on post submission.
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function handle_limit() {

		$module = wu_get_isset(wu_clean(wp_unslash($_POST['modules'] ?? [])), $this->id, []); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return wu_get_isset($module, 'limit', null);
	}

	/**
	 * Returns a default state.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function default_state() {

		return [
			'enabled' => false,
			'limit'   => null,
		];
	}
}
