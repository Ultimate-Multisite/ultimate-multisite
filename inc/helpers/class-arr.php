<?php
/**
 * Array Helpers
 *
 * Heavily inspired on Laravel's Arr helper class and Lodash's PHP implementation.
 *
 * @see https://github.com/laravel/framework/blob/8.x/src/Illuminate/Collections/Arr.php
 * @see https://github.com/me-io/php-lodash/blob/master/src/Traits/Collections.php
 *
 * @package WP_Ultimo\Helpers
 * @since 2.0.11
 */

namespace WP_Ultimo\Helpers;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Helper Array class.
 *
 * @since 2.0.11
 */
class Arr {

	/**
	 * Returns all results.
	 */
	const RESULTS_ALL = 0;

	/**
	 * Return only the first result.
	 */
	const RESULTS_FIRST = 1;

	/**
	 * Result only the last result.
	 */
	const RESULTS_LAST = 2;

	/**
	 * Filter an array by property or key.
	 *
	 * @param array   $array_to_filter The array to filter.
	 * @param string  $property The property to filter by. Dot notation is supported.
	 * @param mixed   $expected_value The expected value to filter by.
	 * @param integer $flag The flag determining the return type.
	 *
	 * @return mixed
	 * @since 2.0.11
	 */
	public static function filter_by_property($array_to_filter, $property, $expected_value, $flag = 0) {

		$result = self::filter(
			$array_to_filter,
			function ($value) use ($property, $expected_value) {

			return Arr::get($value, $property, null) == $expected_value; // phpcs:ignore
			}
		);

		if ($flag) {
			$result = self::RESULTS_FIRST === $flag ? reset($result) : end($result);
		}

		return $result;
	}

	/**
	 * Filters an array using a callback.
	 *
	 * @param array    $array_to_search The array to search inside.
	 * @param callable $closure The closure function to call.
	 *
	 * @return array
	 * @since 2.0.11
	 */
	public static function filter($array_to_search, $closure) {

		if ($closure) {
			$result = [];

			foreach ($array_to_search as $key => $value) {
				if (call_user_func($closure, $value, $key)) {
					$result[] = $value;
				}
			}

			return $result;
		}

		return array_filter($array_to_search);
	}

	/**
	 * Get a nested value inside an array. Dot notation is supported.
	 *
	 * @param array  $array_target The array to get the value from.
	 * @param string $key The array key to get. Supports dot notation.
	 * @param mixed  $default_value The value to return ibn the case the key does not exist.
	 *
	 * @return mixed
	 * @since 2.0.11
	 */
	public static function get($array_target, $key, $default_value = null) {

		if (is_null($key)) {
			return $array_target;
		}

		if (isset($array_target[ $key ])) {
			return $array_target[ $key ];
		}

		foreach (explode('.', $key) as $segment) {
			if ( ! is_array($array_target) || ! array_key_exists($segment, $array_target)) {
				return $default_value;
			}

			$array_target = $array_target[ $segment ];
		}

		return $array_target;
	}

	/**
	 * Set a nested value inside an array. Dot notation is supported.
	 *
	 * @param array  $array_to_modify The array to modify.
	 * @param string $key The array key to set. Supports dot notation.
	 * @param mixed  $value The value to set.
	 *
	 * @return array
	 * @since 2.0.11
	 */
	public static function set(&$array_to_modify, $key, $value) {

		if (is_null($key)) {
			return $array_to_modify = $value; // phpcs:ignore
		}

		$keys = explode('.', $key);

		while (count($keys) > 1) {
			$key = array_shift($keys);

			if ( ! isset($array_to_modify[ $key ]) || ! is_array($array_to_modify[ $key ])) {
				$array_to_modify[ $key ] = [];
			}

			$array_to_modify =& $array_to_modify[ $key ];
		}

		$array_to_modify[ array_shift($keys) ] = $value;

		return $array_to_modify;
	}

	/**
	 * Static class only.
	 *
	 * @since 2.0.11
	 */
	private function __construct() {}
}
