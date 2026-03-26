<?php
/**
 * Billing Address class
 *
 * @package WP_Ultimo
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Objects;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Billing Address class
 *
 * @since 2.0.0
 *
 * @property string $company_name Company name (optional).
 * @property string $billing_email Billing email address.
 * @property string $billing_address_line_1 Address line 1.
 * @property string $billing_address_line_2 Address line 2 (optional).
 * @property string $billing_country Country code.
 * @property string $billing_state State or province.
 * @property string $billing_city City or town.
 * @property string $billing_zip_code ZIP or postal code.
 */
class Billing_Address implements \JsonSerializable {

	/**
	 * The Billing Address content.
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
	 * @param array $data Array of key => values billing address fields.
	 */
	public function __construct($data = []) {

		$this->attributes($data);
	}

	/**
	 * Loops through allowed fields and loads them.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Array of key => values billing address fields.
	 * @return void
	 */
	public function attributes($data): void {

		$allowed_attributes = array_keys(self::fields());

		foreach ($data as $key => $value) {
			if (in_array($key, $allowed_attributes, true)) {
				$this->attributes[ $key ] = $value;
			}
		}
	}

	/**
	 * Loads only the allowed keys from $_POST array and sanitizes them.
	 *
	 * @param array $session optional data to be loaded if not in $_POST.
	 * @return void
	 */
	public function load_attributes_from_post(array $session = []) {
		$allowed_attributes = array_keys(self::fields());

		foreach ($allowed_attributes as $attribute) {
			// Nonce checked in class-form-manager:151
			if (isset($_POST[ $attribute ])) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->attributes[ $attribute ] = sanitize_text_field(wp_unslash($_POST[ $attribute ])); // phpcs:ignore WordPress.Security.NonceVerification
			} elseif (isset($session[ $attribute ])) {
				$this->attributes[ $attribute ] = $session[ $attribute ];
			}
		}
	}

	/**
	 * Checks if this billing address has any content at all.
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
	 * Gets a billing address field.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name The parameter to return.
	 * @return string
	 */
	public function __get($name) {

		$value = wu_get_isset($this->attributes, $name, '');

		return apply_filters("wu_billing_address_get_{$name}", $value, $this);
	}

	/**
	 * Sets a billing address field.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Field name.
	 * @param string $value The field value.
	 */
	public function __set($name, $value) {

		$value = apply_filters("wu_billing_address_set_{$name}", $value, $this);

		$this->attributes[ $name ] = $value;
	}

	/**
	 * Returns the validation rules for the billing address fields.
	 *
	 * @since 2.0.0
	 */
	protected function validation_rules(): array {

		$fields = self::fields();

		$keys = array_keys(array_filter($fields, fn($item) => wu_get_isset($item, 'validation_rules')));

		return array_combine($keys, array_column($fields, 'validation_rules'));
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
	 * Returns a key => value representation of the billing address.
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
	 * Serialize billing address for JSON encoding.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {

		return $this->to_array();
	}

	/**
	 * Returns a string representation of the billing address.
	 *
	 * Example:
	 *
	 * Company Name
	 * Tax ID
	 * Address 1
	 * ...
	 *
	 * @since 2.0.0
	 *
	 * @param string $delimiter Delimiter to glue address pieces together.
	 */
	public function to_string($delimiter = PHP_EOL): string {

		return implode($delimiter, $this->to_array());
	}

	/**
	 * Returns the field array with values added.
	 *
	 * @since 2.0.0
	 * @param bool $zip_only If we only need zip and country.
	 * @return array
	 */
	public function get_fields($zip_only = false) {

		$fields = self::fields($zip_only);

		foreach ($fields as $field_key => &$field) {
			$field['value'] = $this->{$field_key};
		}

		return $fields;
	}

	/**
	 * Billing Address field definitions.
	 *
	 * This is used to determine fields allowed on the billing address.
	 *
	 * @since 2.0.0
	 * @param bool                            $zip_only If we only need zip and country.
	 * @param \WP_Ultimo\Models\Checkout_Form $checkout_form The current checkout form.
	 * @return array
	 */
	public static function fields($zip_only = false, $checkout_form = false) {

		$fields = [];

		$countries = wu_get_countries_as_options();

		// Get allowed countries in form
		if ($checkout_form && $checkout_form->has_country_lock()) {
			$allowed_countries = $checkout_form->get_allowed_countries();

			// Allow the Select Country field
			$allowed_countries[] = '';

			foreach ($countries as $country_code => $country) {
				if ( ! in_array($country_code, $allowed_countries, true)) {
					unset($countries[ $country_code ]);
				}
			}
		}

		$fields['company_name'] = [
			'type'                => 'text',
			'title'               => __('Company Name', 'ultimate-multisite'),
			'default_placeholder' => __('E.g. Google (optional)', 'ultimate-multisite'),
			'wrapper_classes'     => 'sm:wu-col-span-1',
		];

		$fields['billing_email'] = [
			'type'                => 'text',
			'title'               => __('Billing Email', 'ultimate-multisite'),
			'default_placeholder' => __('E.g. john@company.com', 'ultimate-multisite'),
			'wrapper_classes'     => 'sm:wu-col-span-1',
			'required'            => true,
		];

		$fields['billing_address_line_1'] = [
			'type'                => 'text',
			'title'               => __('Address Line 1', 'ultimate-multisite'),
			'default_placeholder' => __('E.g. 555 1st Avenue', 'ultimate-multisite'),
			'wrapper_classes'     => 'wu-col-span-2',
			'required'            => true,
		];

		$fields['billing_address_line_2'] = [
			'type'                => 'text',
			'title'               => __('Address Line 2', 'ultimate-multisite'),
			'default_placeholder' => __('E.g. Apartment 10a', 'ultimate-multisite'),
			'wrapper_classes'     => 'wu-col-span-2',
		];

		$fields['billing_country'] = [
			'type'                => 'select',
			'title'               => __('Country', 'ultimate-multisite'),
			'default_placeholder' => __('E.g. US', 'ultimate-multisite'),
			'wrapper_classes'     => 'sm:wu-col-span-1',
			'value'               => ' ',
			'options'             => $countries,
			'required'            => true,
		];

		$fields['billing_state'] = [
			'type'                => 'text',
			'title'               => __('State / Province', 'ultimate-multisite'),
			'default_placeholder' => __('E.g. NY', 'ultimate-multisite'),
			'wrapper_classes'     => 'sm:wu-col-span-1',
		];

		$fields['billing_city'] = [
			'type'                => 'text',
			'title'               => __('City / Town', 'ultimate-multisite'),
			'default_placeholder' => __('E.g. New York City', 'ultimate-multisite'),
			'wrapper_classes'     => 'sm:wu-col-span-1',
		];

		$fields['billing_zip_code'] = [
			'type'                => 'text',
			'title'               => __('ZIP / Postal Code', 'ultimate-multisite'),
			'default_placeholder' => __('E.g. 10009', 'ultimate-multisite'),
			'wrapper_classes'     => 'sm:wu-col-span-1',
			'required'            => true,
		];

		$fields = wu_set_order_from_index($fields); // Adds missing order attributes

		if ($zip_only) {
			$fields = [
				'billing_zip_code' => $fields['billing_zip_code'],
				'billing_country'  => $fields['billing_country'],
			];
		}

		/**
		 * Allow plugin developers to filter the billing address fields.
		 *
		 * @since 2.0.0
		 *
		 * @param array $fields Billing Address array.
		* @param bool  $zip_only If we only need zip and country.
		 * @return array
		 */
		$fields = apply_filters('wu_billing_address_fields', $fields, $zip_only);

		uasort($fields, 'wu_sort_by_order');

		return $fields;
	}

	/**
	 * Billing Address fields array for REST API.
	 *
	 * @since 2.0.0
	 * @param bool $zip_only If we only need zip and country.
	 * @return array
	 */
	public static function fields_for_rest($zip_only = false) {

		$fields_for_rest = [];

		foreach (self::fields($zip_only) as $field_key => $field) {
			$options = wu_get_isset($field, 'options', false);

			$enum = is_callable($options) ? call_user_func($options) : false;

			$fields_for_rest[ $field_key ] = [
				'description' => wu_get_isset($field, 'title', false) . '. ' . wu_get_isset($field, 'default_placeholder', false),
				'type'        => 'string',
				'required'    => wu_get_isset($field, 'required', false),
			];

			if ($enum) {
				$fields_for_rest[ $field_key ]['enum'] = array_keys($enum);
			}
		}

		return $fields_for_rest;
	}
}
