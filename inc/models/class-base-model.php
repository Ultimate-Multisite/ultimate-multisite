<?php
/**
 * Abstract base model for our data types.
 *
 * @package WP_Ultimo
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Models;

use stdClass;
use WP_Ultimo\Database\Engine\Schema;
use WP_Ultimo\Helpers\Hash;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Abstract base model for our data types
 *
 * This class is the base class that is extended by all of our data types
 * such as plans, coupons, broadcasts, domains, etc.
 *
 * @since 2.0.0
 */
abstract class Base_Model implements \JsonSerializable {

	/**
	 * ID of the object
	 *
	 * @since 2.0.0
	 * @var integer
	 */
	protected $id = 0;

	/**
	 * Model name.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $model = '';

	/**
	 * Holds the Query Class for this particular object type.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = '';

	/**
	 * Holds the slug for this particular object type.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	protected $slug = '';

	/**
	 * Holds meta fields we want to always save.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $meta_fields = [];

	/**
	 * Model creation date.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_created = '';

	/**
	 * Model last modification date.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_modified = '';

	/**
	 * Meta data holder.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	public $meta = [];

	/**
	 * The ID of the original 1.X model that was used to generate this item on migration.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	public $migrated_from_id;

	/**
	 * Set this to true to skip validations when saving.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $skip_validation = false;

	/**
	 * Keeps a copy in memory of the object being edited.
	 *
	 * @since 2.0.0
	 * @var Base_Model
	 */
	protected $_original;

	/**
	 * Map setters to other parameters.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $_mappings = [];

	/**
	 * Mocked status. Used to suppress errors.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	public $_mocked = false;

	/**
	 * Constructs the object via the constructor arguments
	 *
	 * @since 2.0.0
	 * @param mixed $object_model Std object with model parameters.
	 */
	public function __construct($object_model = null) {

		$this->model = sanitize_key((new \ReflectionClass($this))->getShortName());

		if (is_array($object_model)) {
			$object_model = (object) $object_model;
		}

		if ( ! is_object($object_model)) {
			return;
		}

		$this->setup_model($object_model);
	}

	/**
	 * Get the value of slug
	 *
	 * @return mixed
	 */
	public function get_slug() {

		return $this->slug;
	}

	/**
	 * Set the value of slug
	 *
	 * @param mixed $slug The slug.
	 */
	public function set_slug($slug) {

		$this->slug = $slug;
	}

	/**
	 * Returns a hashed version of the id. Useful for displaying data publicly.
	 *
	 * @param string $field Field to use to generate the hash.
	 * @since 2.0.0
	 * @return string|false
	 */
	public function get_hash($field = 'id') {

		$value = call_user_func([$this, "get_{$field}"]);

		if ( ! is_numeric($value)) {
			_doing_it_wrong(__METHOD__, esc_html__('You can only use numeric fields to generate hashes.', 'ultimate-multisite'), '2.0.0');

			return false;
		}

		return Hash::encode($value, $this->model);
	}

	/**
	 * Setup properties.
	 *
	 * @param object $object_model Row from the database.
	 *
	 * @since  2.0.0
	 * @return bool
	 */
	private function setup_model($object_model) {

		if ( ! is_object($object_model)) {
			return false;
		}

		$vars = get_object_vars($object_model);

		$this->attributes($vars);
		return ! empty($this->id);
	}

	/**
	 * Sets the attributes of the model using the setters available.
	 *
	 * @since 2.0.0
	 *
	 * @param array $atts Key-value pairs of model attributes.
	 * @return \WP_Ultimo\Models\Base_Model
	 */
	public function attributes($atts) {

		foreach ($atts as $key => $value) {
			if ('meta' === $key && is_array($value)) {
				$this->meta = is_array($this->meta) ? array_merge($this->meta, $value) : $value;
			}

			if (method_exists($this, "set_$key")) {
				call_user_func([$this, "set_$key"], $value);
			}

			$mapping = wu_get_isset($this->_mappings, $key);

			if ($mapping && method_exists($this, "set_$mapping")) {
				call_user_func([$this, "set_$mapping"], $value);
			}
		}

		/*
		 * Keeps the original.
		 */
		if (null === $this->_original) {
			$original = get_object_vars($this);

			unset($original['_original']);

			$this->_original = $original;
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function load_attributes_from_post() {
		// Nonce check handled in calling method.
		foreach ($_POST as $key => $value) { // phpcs:ignore WordPress.Security.NonceVerification
			if ('meta' === $key && is_array($value)) {
				$value      = wu_clean(wp_unslash($value));
				$this->meta = is_array($this->meta) ? array_merge($this->meta, $value) : $value;
			}

			if (method_exists($this, "set_$key")) {
				call_user_func([$this, "set_$key"], wu_clean(wp_unslash($value)));
			}

			$mapping = wu_get_isset($this->_mappings, $key);

			if ($mapping && method_exists($this, "set_$mapping")) {
				call_user_func([$this, "set_$mapping"], wu_clean(wp_unslash($value)));
			}
		}

		/*
		 * Keeps the original.
		 */
		if (null === $this->_original) {
			$original = get_object_vars($this);

			unset($original['_original']);

			$this->_original = $original;
		}

		return $this;
	}

	/**
	 * Return the model schema. useful to list all models fields.
	 *
	 * @since 2.0.0
	 * @return Schema
	 */
	public static function get_schema() {

		$instance = new static();

		$query_class = new $instance->query_class();

		$columns = $query_class->get_columns();

		return array_map(
			fn($column) => $column->to_array(),
			$columns
		);
	}

	/**
	 * Checks if this model was already saved to the database.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function exists() {

		return ! empty($this->id);
	}

	/**
	 * Gets a single database row by the primary column ID, possibly from cache.
	 *
	 * @since 2.0.0
	 *
	 * @param int $item_id The item id.
	 *
	 * @return static|false Base_Model
	 */
	public static function get_by_id($item_id) {

		if (empty($item_id)) {
			return false;
		}

		$instance = new static();

		$query_class = new $instance->query_class();

		return $query_class->get_item($item_id);
	}

	/**
	 * Gets a single database row by the hash, possibly from cache.
	 *
	 * @since 2.0.0
	 *
	 * @param string $item_hash The item hash.
	 *
	 * @return Base_Model|false
	 */
	public static function get_by_hash($item_hash) {

		$instance = new static();

		$item_id = Hash::decode($item_hash, sanitize_key((new \ReflectionClass(static::class))->getShortName()));

		$query_class = new $instance->query_class();

		return $query_class->get_item($item_id);
	}

	/**
	 * Gets a model instance by a column value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $column The name of the column to query for.
	 * @param string $value Value to search for.
	 * @return static|false
	 */
	public static function get_by($column, $value) {

		$instance = new static();

		$query_class = new $instance->query_class();

		return $query_class->get_item_by($column, $value);
	}

	/**
	 * Wrapper for a Query call.
	 *
	 * @since 2.0.0
	 *
	 * @param array $query Arguments for the query.
	 * @return array|int List of items, or number of items when 'count' is passed as a query var.
	 */
	public static function get_items($query) {

		$instance = new static();

		return (new $instance->query_class($query))->query();
	}

	/**
	 * Wrapper for a Query call, but returns the list as arrays.
	 *
	 * @since 2.0.0
	 *
	 * @param array $query Arguments for the query.
	 * @return array|int List of items, or number of items when 'count' is passed as a query var.
	 */
	public static function get_items_as_array($query = []) {

		$instance = new static();

		$list = (new $instance->query_class($query))->query();

		return array_map(fn($item) => $item->to_array(), $list);
	}

	/**
	 * Get the ID of the model.
	 *
	 * @access public
	 * @since  2.0.0
	 * @return int
	 */
	public function get_id() {

		return absint($this->id);
	}

	/**
	 * Check if this model has a job running.
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function has_running_jobs() {

		$jobs = wu_get_scheduled_actions(
			[
				'status' => \ActionScheduler_Store::STATUS_RUNNING,
				'args'   => [
					"{$this->model}_id" => $this->get_id(),
				],
			]
		);

		return $jobs;
	}

	/**
	 * Set iD of the object.
	 *
	 * @since 2.0.0
	 * @param integer $id ID of the object.
	 * @return void
	 */
	private function set_id($id): void {

		$this->id = $id;
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

		return [];
	}

	/**
	 * Validates the rules and make sure we only save models when necessary.
	 *
	 * @since 2.0.0
	 * @return true|\WP_Error
	 */
	public function validate() {

		if ($this->skip_validation) {
			return true;
		}

		$validator = new \WP_Ultimo\Helpers\Validator();

		$validator->validate($this->to_array(), $this->validation_rules());

		if ($validator->fails()) {
			return $validator->get_errors();
		}

		foreach ($validator->get_validation()->getValidData() as $key => $value) {
			// Skip null values to avoid overwriting existing property values with null.
			// The rakit/validation library may return null for fields on PHP 8.4+ due to
			// implicit nullable parameter deprecations in Attribute::getValue(), which would
			// incorrectly reset boolean/integer fields (e.g. apply_to_renewals) to null.
			if (null !== $value) {
				$this->{$key} = $value;
			}
		}

		return true;
	}

	/**
	 * Save (create or update) the model on the database.
	 *
	 * @since 2.0.0
	 *
	 * @return bool|\WP_Error
	 */
	public function save() {

		/** @var \WP_Ultimo\Database\Engine\Query $query_class */
		$query_class = new $this->query_class();

		$data = get_object_vars($this);

		if (isset($data['id']) && empty($data['id'])) {
			unset($data['id']);
		}

		unset($data['_original']);

		$data_unserialized = $data;

		$meta = wu_get_isset($data, 'meta', []);

		$new = ! $this->exists();

		/**
		 * Filters the data meta before it is serialized to be stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param array      $meta The meta data that will be stored, unserializedserialized.
		 * @param array      $data_unserialized The object data that will be stored.
		 * @param Base_Model $this The object instance.
		 */
		$meta = apply_filters("wu_{$this->model}_meta_pre_save", $meta, $data_unserialized, $this);

		$blocked_attributes = [
			'query_class',
			'meta',
		];

		foreach ($blocked_attributes as $attribute) {
			unset($data[ $attribute ]);
		}

		$this->validate();

		$data = array_map('maybe_serialize', $data);

		$data = array_map(
			function ($_data) {

				if (is_serialized($_data)) {
					$_data = addslashes($_data);
				}

				return $_data;
			},
			$data
		);

		/**
		 * Filters the object data before it is stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param array      $data The object data that will be stored, serialized.
		 * @param array      $data_unserialized The object data that will be stored.
		 * @param Base_Model $this The object instance.
		 */
		$data = apply_filters("wu_{$this->model}_pre_save", $data, $data_unserialized, $this);

		$is_valid_data = $this->validate();

		if (is_wp_error($is_valid_data) && ! $this->skip_validation) {
			return $is_valid_data;
		}

		$saved = false;

		if ( ! $this->get_id()) {
			$new_id = $query_class->add_item($data);

			if ($new_id) {
				$this->id = $new_id;

				$saved = true;
			}
		} else {
			$saved = (bool) $query_class->update_item($this->get_id(), $data);
		}

		if ( ! empty($meta)) {
			$saved = $this->update_meta_batch($meta);
		}

		/**
		 * Delete object cache to prevent errors.
		 * As BerlinDB groups are protected, we try
		 * guess the group name with model name.
		 */
		wp_cache_delete($this->get_id(), "wu-{$this->model}s");

		/**
		 * Fires after an object is stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param string     $model The model slug.
		 * @param array      $data The object data that will be stored, serialized.
		 * @param array      $data_unserialized The object data that will be stored.
		 * @param Base_Model $this The object instance.
		 */
		do_action('wu_model_post_save', $this->model, $data, $data_unserialized, $this);

		/**
		 * Fires after an object is stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param array      $data The object data that will be stored.
		 * @param Base_Model $this The object instance.
		 * @param bool       $new  True if the object is new.
		 */
		do_action("wu_{$this->model}_post_save", $data, $this, $new);

		return $saved;
	}

	/**
	 * Delete the model from the database.
	 *
	 * @since 2.0.0
	 *
	 * @return \WP_Error|bool
	 */
	public function delete() {

		if ( ! $this->get_id()) {
			return new \WP_Error("wu_{$this->model}_delete_unsaved_item", __('Item not found.', 'ultimate-multisite'));
		}

		/**
		 * Fires after an object is stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param Base_Model $this The object instance.
		 */
		do_action("wu_{$this->model}_pre_delete", $this);

		$query_class = new $this->query_class();

		$result = $query_class->delete_item($this->get_id());

		/**
		 * Fires after an object is stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param bool       $result True if the object was successfully deleted.
		 * @param Base_Model $this   The object instance.
		 */
		do_action("wu_{$this->model}_post_delete", $result, $this);

		return $result;
	}

	/**
	 * Returns the meta type name.
	 *
	 * @since 2.0.0
	 *
	 * @return mixed Meta type name
	 */
	private function get_meta_type_name() {

		$query_class = new $this->query_class();

		// Maybe apply table prefix
		$table = ! empty($query_class->prefix)
		? "{$query_class->prefix}_{$query_class->item_name}"
		: $query_class->item_name;

		// Return table if exists, or false if not
		return $table;
	}

	/**
	 * Returns the meta table name.
	 *
	 * @since 2.0.0
	 *
	 * @return string|false Table name if exists, False if not
	 */
	private function get_meta_table_name() {

		$table = $this->get_meta_type_name();

		return _get_meta_table($table);
	}

	/**
	 * Checks if metadata handling is available, i.e., if there is a meta table
	 * for this model and if the object already has an ID set.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	protected function is_meta_available() {

		if ( ! $this->get_meta_table_name()) {

			// _doing_it_wrong(__METHOD__, __('This model does not support metadata.', 'ultimate-multisite'), '2.0.0');

			return false;
		}

		// _doing_it_wrong(__METHOD__, __('Model metadata only works for already saved models.', 'ultimate-multisite'), '2.0.0');
		return ! (! $this->get_id() && ! $this->_mocked);
	}

	/**
	 * Returns the meta data, if set. Otherwise, returns the default.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key     The meta key.
	 * @param mixed  $default_value The default value to be passed.
	 * @param bool   $single  To return single values or not.
	 * @return mixed
	 */
	public function get_meta($key, $default_value = false, $single = true) {

		if ( ! $this->is_meta_available()) {
			return $default_value;
		}

		$meta_type = $this->get_meta_type_name();
		$value     = get_metadata_raw($meta_type, $this->get_id(), $key, $single);

		return ! is_null($value) ? $value : $default_value;
	}

	/**
	 * Adds or updates meta data in batch.
	 *
	 * @since 2.0.0
	 *
	 * @param array $meta  An array of meta data in `'key' => 'value'` format.
	 * @return bool True on successful update, false on failure.
	 */
	public function update_meta_batch($meta) {

		if ( ! $this->is_meta_available()) {
			return false;
		}

		if ( ! is_array($meta)) {
			_doing_it_wrong(__METHOD__, esc_html__('This method expects an array as argument.', 'ultimate-multisite'), '2.0.0');

			return false;
		}

		$meta_type = $this->get_meta_type_name();

		$success = true;

		foreach ($meta as $key => $value) {
			update_metadata($meta_type, $this->get_id(), $key, $value);
		}

		return $success;
	}

	/**
	 * Adds or updates the meta data.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key  The meta key.
	 * @param mixed  $value The new meta value.
	 * @return int|bool The new meta field ID if a field with the given key didn't exist and was
	 *                  therefore added, true on successful update, false on failure.
	 */
	public function update_meta($key, $value) {

		if ( ! $this->is_meta_available()) {
			return false;
		}

		$meta_type = $this->get_meta_type_name();

		return update_metadata($meta_type, $this->get_id(), $key, $value);
	}

	/**
	 * Deletes the meta data.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key The meta key.
	 * @return bool True on successful delete, false on failure.
	 */
	public function delete_meta($key) {

		if ( ! $this->is_meta_available()) {
			return false;
		}

		$meta_type = $this->get_meta_type_name();

		return delete_metadata($meta_type, $this->get_id(), $key);
	}

	/**
	 * Queries object in the database.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array|int List of items, or number of items when 'count' is passed as a query var.
	 */
	public static function query($args = []) {

		$instance = new static();

		$query_class = new $instance->query_class();

		$items = $query_class->query($args);

		return $items;
	}

	/**
	 * Transform the object into an assoc array.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function to_array() {

		$array = get_object_vars($this);

		unset($array['query_class']);
		unset($array['skip_validation']);
		unset($array['meta']);
		unset($array['meta_fields']);
		unset($array['_original']);
		unset($array['_mappings']);
		unset($array['_mocked']);

		foreach ($array as $key => $value) {
			if (str_starts_with('_', $key)) {
				unset($array[ $key ]);
			}
		}

		return $array;
	}

	/**
	 * Convert data to Mapping instance
	 *
	 * Allows use as a callback, such as in `array_map`
	 *
	 * @param stdClass $data Raw mapping data.
	 * @return Base_model
	 */
	protected static function to_instance($data) {

		return new static($data);
	}

	/**
	 * Convert list of data to Mapping instances
	 *
	 * @param stdClass[] $data Raw mapping rows.
	 * @return Domain[]
	 */
	protected static function to_instances($data) {

		return array_map([static::class, 'to_instance'], $data);
	}

	/**
	 * By default, we just use the to_array method, but you can rewrite this.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function to_search_results() {

		return $this->to_array();
	}

	/**
	 * Defines how we should encode this.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {

		if (wp_doing_ajax() && wu_request('action') === 'wu_search') {
			return $this->to_search_results();
		}

		return $this->to_array();
	}

	/**
	 * Get the date when this model was created.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_created() {

		if ( ! wu_validate_date($this->date_created)) {
			return wu_get_current_time('mysql');
		}

		return $this->date_created;
	}

	/**
	 * Get the date when this model was last modified.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_modified() {

		if ( ! wu_validate_date($this->date_modified)) {
			return wu_get_current_time('mysql');
		}

		return $this->date_modified;
	}

	/**
	 * Set model creation date.
	 *
	 * @since 2.0.0
	 * @param string $date_created Model creation date.
	 * @return void
	 */
	public function set_date_created($date_created) {

		$this->date_created = $date_created;
	}

	/**
	 * Set model last modification date.
	 *
	 * @since 2.0.0
	 * @param string $date_modified Model last modification date.
	 * @return void
	 */
	public function set_date_modified($date_modified) {

		$this->date_modified = $date_modified;
	}

	/**
	 * Get the id of the original 1.X model that was used to generate this item on migration.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_migrated_from_id() {

		return $this->migrated_from_id;
	}

	/**
	 * Set the id of the original 1.X model that was used to generate this item on migration.
	 *
	 * @since 2.0.0
	 * @param int $migrated_from_id The ID of the original 1.X model that was used to generate this item on migration.
	 * @return void
	 */
	public function set_migrated_from_id($migrated_from_id) {

		$this->migrated_from_id = absint($migrated_from_id);
	}

	/**
	 * Checks if this model is a migration from 1.X.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_migrated() {

		return ! empty($this->get_migrated_from_id());
	}

	/**
	 * Helper method to return formatted values.
	 *
	 * Deals with:
	 * - currency values;
	 *
	 * @since 2.0.0
	 *
	 * @param string $key The key to return.
	 * @return mixed
	 */
	public function get_formatted_amount($key = 'amount') {

		$value = (float) $this->{"get_{$key}"}();

		if (is_numeric($value)) {
			return wu_format_currency($value);
		}

		return $value;
	}

	/**
	 * Helper method to return formatted dates.
	 *
	 * Deals with:
	 * - dates
	 *
	 * @since 2.0.0
	 *
	 * @param string $key The key to return.
	 * @return mixed
	 */
	public function get_formatted_date($key = 'date_created') {

		$value = $this->{"get_{$key}"}();

		if (wu_validate_date($value)) {
			return date_i18n(get_option('date_format'), wu_date($value)->format('U'));
		}

		return $value;
	}

	/**
	 * Get all items.
	 *
	 * @since 2.0.0
	 *
	 * @param array $query_args If you need to select a type to get all.
	 * @return array With all items requested.
	 */
	public static function get_all($query_args = []) {

		$instance = new static();

		$query_class = new $instance->query_class();

		$items = $query_class->query($query_args);

		return $items;
	}

	/**
	 * Creates a copy of the given model adn resets it's id to a 'new' state.
	 *
	 * @since 2.0.0
	 * @return Base_Model
	 */
	public function duplicate() {

		$this->hydrate();

		$clone = clone $this;

		$clone->set_id(0);

		if (method_exists($clone, 'set_date_created')) {
			$clone->set_date_created(wu_get_current_time('mysql'));
		}

		return $clone;
	}

	/**
	 * Populate the data the resides on meta tables.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function hydrate() {

		$attributes = get_object_vars($this);
		$attributes = array_filter($attributes, fn ($value) => null === $value);

		unset($attributes['meta']);

		foreach ($attributes as $attribute => $maybe_null) {
			$possible_setters = [
				"get_{$attribute}",
				"is_{$attribute}",
			];

			foreach ($possible_setters as $setter) {
				$setter = method_exists($this, $setter) ? $setter : '';

				if ( ! $setter || ! method_exists($this, "set_{$attribute}")) {
					continue;
				}

				$value = $this->{$setter}();

				$this->{"set_{$attribute}"}($value);
			}
		}
	}

	/**
	 * Set set this to true to skip validations when saving..
	 *
	 * @since 2.0.0
	 * @param boolean $skip_validation Set true to have field information validation bypassed when saving this event.
	 * @return void
	 */
	public function set_skip_validation($skip_validation = false) {

		$this->skip_validation = $skip_validation;
	}

	/**
	 * Returns the original parameters of the object.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function _get_original() {

		return $this->_original;
	}

	/**
	 * Locks this model.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function lock() {

		return $this->update_meta('wu_lock', true);
	}

	/**
	 * Check ths lock status of the model.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_locked() {

		return $this->get_meta('wu_lock', false);
	}

	/**
	 * Unlocks the model.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function unlock() {

		return $this->delete_meta('wu_lock');
	}
}
