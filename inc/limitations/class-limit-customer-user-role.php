<?php
/**
 * Customer User Role Limit Module.
 *
 * @package WP_Ultimo
 * @subpackage Limitations
 * @since 2.0.10
 */

namespace WP_Ultimo\Limitations;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Customer User Role Limit Module.
 *
 * @since 2.0.10
 */
class Limit_Customer_User_Role extends Limit {

	/**
	 * The module id.
	 *
	 * @since 2.0.10
	 * @var string
	 */
	protected $id = 'customer_user_role';

	/**
	 * Returns a default state.
	 *
	 * @since 2.0.10
	 * @return array
	 */
	public static function default_state() {

		return [
			'enabled' => true,
			'limit'   => 'default',
		];
	}

	/**
	 * The check method is what gets called when allowed is called.
	 *
	 * Each module needs to implement a check method, that returns a boolean.
	 * This check can take any form the developer wants.
	 *
	 * @since 2.0.10
	 *
	 * @param mixed  $value_to_check Value to check.
	 * @param mixed  $limit The list of limits in this modules.
	 * @param string $type Type for sub-checking.
	 * @return bool
	 */
	public function check($value_to_check, $limit, $type = '') {

		return true;
	}

	/**
	 * Gets the limit data.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Type for sub-checking.
	 * @return mixed
	 */
	public function get_limit($type = '') {

		$default_value = wu_get_setting('default_role', 'administrator');

		return empty($this->limit) || 'default' === $this->limit ? $default_value : $this->limit;
	}

	/**
	 * Gets the role stored on this object, without resolving the global default.
	 *
	 * Used by edit forms so that "Use Ultimate Multisite default" stays selected
	 * when no explicit role was saved, instead of showing (and re-saving) the
	 * role the global default_role setting currently resolves to.
	 *
	 * @since 2.18.1
	 *
	 * @return string The stored role slug, or "default" when none is stored.
	 */
	public function get_stored_limit() {

		return empty($this->limit) || ! is_string($this->limit) ? 'default' : $this->limit;
	}
}
