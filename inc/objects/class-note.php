<?php
/**
 * Note class
 *
 * @package WP_Ultimo
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Objects;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Note class
 *
 * @since 2.0.0
 */
class Note implements \JsonSerializable {

	/**
	 * The Note content.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $attributes = [];

	/**
	 * Initializes the object.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Array of key => values note fields.
	 */
	public function __construct($data = []) {

		$this->attributes($data);
	}

	/**
	 * Loops through allowed fields and loads them.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Array of key => values note fields.
	 * @return void
	 */
	public function attributes($data): void {

		$allowed_attributes = array_keys(self::fields());

		foreach ($data as $key => $value) {
			if (in_array($key, $allowed_attributes, true)) {
				$this->attributes[ $key ] = $value;
			}
		}

		$this->attributes['date_created'] = wu_get_current_time('mysql', true);
	}

	/**
	 * Checks if this note has any content at all.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function exists() {

		return ! empty(array_filter($this->attributes));
	}

	/**
	 * Checks if a parameter exists.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name The parameter to check.
	 * @return boolean
	 */
	public function __isset($name) {

		return wu_get_isset($this->attributes, $name, '');
	}

	/**
	 * Gets a note field.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name The parameter to return.
	 * @return string
	 */
	public function __get($name) {

		$value = wu_get_isset($this->attributes, $name, '');

		return apply_filters("wu_note_get_{$name}", $value, $this);
	}

	/**
	 * Sets a note field.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Field name.
	 * @param string $value The field value.
	 */
	public function __set($name, $value) {

		$value = apply_filters("wu_note_set_{$name}", $value, $this);

		$this->attributes[ $name ] = $value;
	}

	/**
	 * Returns the validation rules for new notes.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function validation_rules() {

		return [];
	}

	/**
	 * Validates the fields following the validation rules.
	 *
	 * @since 2.0.0
	 * @return true|\WP_Error
	 */
	public function validate() {

		$validator = new \WP_Ultimo\Helpers\Validator();

		$validator->validate($this->to_array(), $this->validation_rules());

		if ($validator->fails()) {
			return $validator->get_errors();
		}

		return true;
	}

	/**
	 * Returns a key => value representation of the notes fields.
	 *
	 * @since 2.0.0
	 *
	 * @param boolean $labels Wether or not to return labels as keys or the actual keys.
	 * @return array
	 */
	public function to_array($labels = false) {

		$address_array = [];

		$fields = self::fields();

		foreach ($fields as $field_key => $field) {
			if ( ! empty($this->{$field_key})) {
				$key = $labels ? $field['title'] : $field_key;

				$address_array[ $key ] = $this->{$field_key};
			}
		}

		return $address_array;
	}

	/**
	 * Returns a JSON-serializable representation of the note.
	 *
	 * Implements JsonSerializable so that json_encode() produces the same
	 * output as to_array() instead of serializing only public properties
	 * (which would yield {} because $attributes is protected).
	 *
	 * @since 2.0.11
	 * @return array
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {

		return $this->to_array();
	}

	/**
	 * Returns the contents of the note.
	 *
	 * @since 2.0.0
	 *
	 * @param string $delimiter Delimiter to glue address pieces together.
	 */
	public function to_string($delimiter = PHP_EOL): string {

		return implode($delimiter, $this->to_array());
	}

	/**
	 * Note field definitions.
	 *
	 * This is used to determine fields allowed on the note.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function fields() {

		$fields = [];

		$fields['text'] = [
			'type'  => 'text',
			'title' => __('Text', 'ultimate-multisite'),
		];

		$fields['author_id'] = [
			'type'  => 'number',
			'title' => __('Author ID', 'ultimate-multisite'),
		];

		$fields['note_id'] = [
			'type'  => 'text',
			'title' => __('Note ID', 'ultimate-multisite'),
		];

		uasort($fields, 'wu_sort_by_order');

		return $fields;
	}
}
