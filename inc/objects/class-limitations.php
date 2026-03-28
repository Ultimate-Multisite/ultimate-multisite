<?php
/**
 * Limitation manager.
 *
 * This class centralizes the limitation modules.
 *
 * @package WP_Ultimo
 * @subpackage Limitations
 * @since 2.0.0
 */

namespace WP_Ultimo\Objects;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Limitation manager.
 *
 * This class centralizes the limitation modules.
 *
 * @since 2.0.0
 *
 * @property-read \WP_Ultimo\Limitations\Limit_Post_Types $post_types
 * @property-read \WP_Ultimo\Limitations\Limit_Plugins $plugins
 * @property-read \WP_Ultimo\Limitations\Limit_Sites $sites
 * @property-read \WP_Ultimo\Limitations\Limit_Themes $themes
 * @property-read \WP_Ultimo\Limitations\Limit_Visits $visits
 * @property-read \WP_Ultimo\Limitations\Limit_Disk_Space $disk_space
 * @property-read \WP_Ultimo\Limitations\Limit_Users $users
 * @property-read \WP_Ultimo\Limitations\Limit_Site_Templates $site_templates
 * @property-read \WP_Ultimo\Limitations\Limit_Domain_Mapping $domain_mapping
 * @property-read \WP_Ultimo\Limitations\Limit_Customer_User_Role $customer_user_role
 * @property-read \WP_Ultimo\Limitations\Limit_Hide_Footer_Credits $hide_credits
 */
class Limitations implements \JsonSerializable {

	/**
	 * Caches early limitation queries to prevent
	 * to many database hits.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private static $limitations_cache = [];

	/**
	 * Version of the limitation schema.
	 *
	 * @since 2.0.0
	 * @var integer
	 */
	protected $version = 2;

	/**
	 * Limitation modules.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $modules = [];

	/**
	 * The current limitation being merged in merge_recursive.
	 *
	 * @since 2.1.0
	 * @var string
	 */
	protected $current_merge_id = '';

	protected array $raw_module_data;

	/**
	 * Constructs the limitation class with module data.
	 *
	 * @since 2.0.0
	 *
	 * @param array $modules_data Array of modules data.
	 */
	public function __construct($modules_data = []) {

		$this->raw_module_data = $modules_data;
	}

	/**
	 * Returns the module via magic getter.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name The module name.
	 * @return \WP_Ultimo\Limitations\Limit
	 */
	public function __get($name) {

		$module = wu_get_isset($this->modules, $name, false);

		if (false === $module) {
			$module = self::build($this->raw_module_data[ $name ] ?? [], $name);
			if ($module) {
				$this->modules[ $name ] = $module;
				return $this->modules[ $name ];
			}
		}

		return $module;
	}

	/**
	 * Prepare to serialization.
	 *
	 * @see requires php 7.3
	 * @since 2.0.0
	 * @return array
	 */
	public function __serialize() {

		return $this->to_array();
	}

	/**
	 * Handles un-serialization.
	 *
	 * @since 2.0.0
	 *
	 * @see requires php 7.3
	 * @param array $modules_data Array of modules data.
	 * @return void
	 */
	public function __unserialize($modules_data) {
		$this->raw_module_data = $modules_data;
	}

	/**
	 * Builds the module list based on the module data.
	 *
	 * @since 2.0.0
	 *
	 * @param array $modules_data Array of modules data.
	 * @return self
	 */
	public function build_modules($modules_data) {

		$this->raw_module_data = $modules_data;

		return $this;
	}

	/**
	 * Build a module, based on the data.
	 *
	 * @since 2.0.0
	 *
	 * @param string|array $data The module data.
	 * @param string       $module_name The module_name.
	 * @return false|\WP_Ultimo\Limitations\Limit
	 */
	public static function build($data, $module_name) {

		$class = wu_get_isset(self::repository(), $module_name);

		if (class_exists($class)) {
			if (is_string($data)) {
				$data = json_decode($data, true);
			}

			return new $class($data);
		}

		return false;
	}

	/**
	 * Checks if a limitation model exists in this limitations.
	 *
	 * @since 2.0.0
	 *
	 * @param string $module The module name.
	 * @return bool
	 */
	public function exists($module) {

		return (bool) wu_get_isset($this->raw_module_data, $module, false);
	}

	/**
	 * Checks if we have any limitation modules setup at all.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_limitations() {

		foreach ($this->raw_module_data as $module) {
			if ($module['enabled']) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if a particular module is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @param string $module_name Module name.
	 * @return boolean
	 */
	public function is_module_enabled($module_name) {

		return $this->raw_module_data[ $module_name ]['enabled'] ?? false;
	}

	/**
	 * Merges limitations from other entities.
	 *
	 * This is what we use to combine different limitations from
	 * different sources. For example: we override the limitations
	 * of site with restrictions coming from the membership,
	 * products, etc.
	 *
	 * @since 2.0.0
	 *
	 * @param array|bool $override    A limitation array or a boolean to override the values from first to last limitation.
	 * @param array      ...$limitations Limitation arrays.
	 * @return self
	 */
	public function merge($override = false, ...$limitations) {

		if ( ! is_bool($override)) {
			$limitations[] = $override;

			$override = false;
		}

		$results = $this->to_array();

		foreach ($limitations as $limitation) {
			if (is_a($limitation, self::class)) {
				$limitation = $limitation->to_array();
			}

			if ( ! is_array($limitation)) {
				continue;
			}

			$this->merge_recursive($results, $limitation, ! $override);
		}

		return new self($results);
	}

	/**
	 * Merges a limitation array
	 *
	 * @since 2.0.20
	 *
	 * @param array $array1 The arrays original.
	 * @param array $array2 The array to be merged in.
	 * @param bool  $should_sum If we should add up numeric values instead of replacing the original.
	 * @return void
	 */
	protected function merge_recursive(array &$array1, array &$array2, $should_sum = true) {

		$current_id = $this->current_merge_id;

		$force_enabled_list = [
			'plugins',
			'themes',
		];

		$force_enabled = in_array($current_id, $force_enabled_list, true);

		if ($force_enabled && (! wu_get_isset($array1, 'enabled', true) || ! wu_get_isset($array2, 'enabled', true))) {
			$array1['enabled'] = true;
			$array2['enabled'] = true;
		}

		if ( ! wu_get_isset($array1, 'enabled', true)) {
			$array1 = [
				'enabled' => false,
			];
		}

		if ( ! wu_get_isset($array2, 'enabled', true) && $should_sum) {
			return;
		}

		foreach ($array2 as $key => &$value) {
			/**
			 * Here we need to work with arrays and some limits
			 * as themes and plugins have stdClass values.
			 */
			$value = is_object($value) ? get_object_vars($value) : $value;

			if (isset($array1[ $key ])) {
				$array1[ $key ] = is_object($array1[ $key ]) ? get_object_vars($array1[ $key ]) : $array1[ $key ];
			}

			if (is_array($value) && isset($array1[ $key ]) && is_array($array1[ $key ])) {
				$array1_id = wu_get_isset($array1[ $key ], 'id', $current_id);

				$this->current_merge_id = wu_get_isset($value, 'id', $array1_id);

				$this->merge_recursive($array1[ $key ], $value, $should_sum);

				$this->current_merge_id = $current_id;
			} else {
				$original_value = wu_get_isset($array1, $key);

				// If the value is 0 or '' it can be an unlimited value
				$is_unlimited = (is_numeric($value) || '' === $value) && 0 === (int) $value;

				if ($should_sum && ('' === $original_value || 0 === $original_value)) {
					/**
					 *  We use values 0 or '' as unlimited in our limits
					 */
					continue;
				} elseif ($should_sum && is_null($value) && ! is_null($original_value)) {
					/*
					 * Null values should not overwrite existing values in additive mode.
					 * A null limit means "no restriction configured" and should not
					 * remove restrictions from previously merged limitations.
					 */
					continue;
				} elseif (isset($array1[ $key ]) && is_numeric($array1[ $key ]) && is_numeric($value) && $should_sum && ! $is_unlimited) {
					$array1[ $key ] = ((int) $array1[ $key ]) + $value;
				} elseif ('visibility' === $key && isset($array1[ $key ]) && $should_sum) {
					/*
					 * Hidden takes priority over visible: if any source restricts
					 * visibility to 'hidden', the item must be hidden. A product or
					 * membership that marks a plugin/theme as hidden should always
					 * override a default of 'visible'.
					 *
					 * Priority: hidden (1) > visible (0)
					 */
					$key_priority = [
						'visible' => 0,
						'hidden'  => 1,
					];

					$array1[ $key ] = $key_priority[ $value ] > $key_priority[ $array1[ $key ] ] ? $value : $array1[ $key ];
				} elseif ('behavior' === $key && isset($array1[ $key ]) && $should_sum) {
					$key_priority_list = [
						'plugins' => [
							'default'               => 10,
							'force_inactive_locked' => 20,
							'force_inactive'        => 30,
							'force_active_locked'   => 40,
							'force_active'          => 50,
						],
						'site'    => [
							'not_available' => 10,
							'available'     => 20,
							'pre_selected'  => 30,
						],
						'themes'  => [
							'not_available' => 10,
							'available'     => 20,
							'force_active'  => 30,
						],
					];

					$key_priority = apply_filters("wu_limitation_{$current_id}_priority", $key_priority_list[ $current_id ]);

					$array1[ $key ] = $key_priority[ $value ] > $key_priority[ $array1[ $key ] ] ? $value : $array1[ $key ];
				} else {

					// Avoid change true values
					$array1[ $key ] = true !== $original_value || ! $should_sum ? $value : true;
				}
			}
		}
	}

	/**
	 * Converts the limitations list to an array.
	 *
	 * @since 2.0.0
	 */
	public function to_array(): array {
		return $this->raw_module_data;
	}

	/**
	 * Returns a JSON-serializable representation of the limitations.
	 *
	 * Implements JsonSerializable so that json_encode() produces the same
	 * output as to_array() instead of serializing only public properties
	 * (which would yield {} because all properties are protected/private).
	 *
	 * @since 2.0.11
	 * @return array
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {

		return $this->to_array();
	}

	/**
	 * Static method to return limitations in very early stages of the WordPress lifecycle.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Slug of the model.
	 * @param int    $id ID of the model.
	 * @return \WP_Ultimo\Objects\Limitations
	 */
	public static function early_get_limitations($slug, $id) {

		$wu_prefix = 'wu_';

		/*
		 * Reset the slug and prefixes
		 * for the native tables of blogs.
		 */
		if ('site' === $slug) {
			$slug = 'blog';

			$wu_prefix = '';
		}

		$cache = static::$limitations_cache;

		$key = sprintf('%s-%s', $slug, $id);

		if (isset($cache[ $key ])) {
			return $cache[ $key ];
		}

		global $wpdb;

		$limitations = [];

		$table_name = "{$wpdb->base_prefix}{$wu_prefix}{$slug}meta";

		$sql = $wpdb->prepare("SELECT meta_value FROM {$table_name} WHERE meta_key = 'wu_limitations' AND  {$wu_prefix}{$slug}_id = %d LIMIT 1", $id); // phpcs:ignore

		$results = $wpdb->get_var($sql); // phpcs:ignore

		if ( ! empty($results)) {
			$limitations = maybe_unserialize($results);
		}

		/*
		 * Caches the results.
		 */
		static::$limitations_cache[ $key ] = $limitations;

		return $limitations;
	}

	/**
	 * Delete limitations.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug The slug of the model.
	 * @param int    $id The id of the meta id.
	 * @return void
	 */
	public static function remove_limitations($slug, $id): void {

		global $wpdb;

		$wu_prefix = 'wu_';

		/*
		 * Site apis are already available,
		 * so no need to use low-level sql calls.
		 */
		if ('site' === $slug) {
			$wu_prefix = '';

			$slug = 'blog';
		}

		$table_name = "{$wpdb->base_prefix}{$wu_prefix}{$slug}meta";

		$sql = $wpdb->prepare("DELETE FROM {$table_name} WHERE meta_key = 'wu_limitations' AND  {$wu_prefix}{$slug}_id = %d LIMIT 1", $id); // phpcs:ignore

		$wpdb->get_var($sql); // phpcs:ignore
	}

	/**
	 * Returns an empty permission set, with modules.
	 *
	 * @since 2.0.0
	 * @return self
	 */
	public static function get_empty() {

		$modules_data = [];

		foreach (self::repository() as $module_name => $class_name) {
			$modules_data[ $module_name ] = $class_name::default_state();
		}

		return new self($modules_data);
	}

	/**
	 * Repository of the limitation modules.
	 *
	 * @see wu_register_limit_module()
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function repository() {

		$classes = [
			'post_types'         => \WP_Ultimo\Limitations\Limit_Post_Types::class,
			'plugins'            => \WP_Ultimo\Limitations\Limit_Plugins::class,
			'sites'              => \WP_Ultimo\Limitations\Limit_Sites::class,
			'themes'             => \WP_Ultimo\Limitations\Limit_Themes::class,
			'visits'             => \WP_Ultimo\Limitations\Limit_Visits::class,
			'disk_space'         => \WP_Ultimo\Limitations\Limit_Disk_Space::class,
			'users'              => \WP_Ultimo\Limitations\Limit_Users::class,
			'site_templates'     => \WP_Ultimo\Limitations\Limit_Site_Templates::class,
			'domain_mapping'     => \WP_Ultimo\Limitations\Limit_Domain_Mapping::class,
			'customer_user_role' => \WP_Ultimo\Limitations\Limit_Customer_User_Role::class,
			'hide_credits'       => \WP_Ultimo\Limitations\Limit_Hide_Footer_Credits::class,
		];

		return apply_filters('wu_limit_classes', $classes);
	}
}
