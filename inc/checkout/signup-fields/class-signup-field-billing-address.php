<?php
/**
 * Creates a cart with the parameters of the purchase being placed.
 *
 * @package WP_Ultimo
 * @subpackage Order
 * @since 2.0.0
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_Ultimo\Checkout\Signup_Fields\Base_Signup_Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Creates an cart with the parameters of the purchase being placed.
 *
 * @package WP_Ultimo
 * @subpackage Checkout
 * @since 2.0.0
 */
class Signup_Field_Billing_Address extends Base_Signup_Field {

	/**
	 * Returns the type of the field.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_type() {

		return 'billing_address';
	}

	/**
	 * Returns if this field should be present on the checkout flow or not.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_required() {

		return false;
	}

	/**
	 * Is this a user-related field?
	 *
	 * If this is set to true, this field will be hidden
	 * when the user is already logged in.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_user_field() {

		return true;
	}

	/**
	 * Requires the title of the field/element type.
	 *
	 * This is used on the Field/Element selection screen.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('Billing Address', 'ultimate-multisite');
	}

	/**
	 * Returns the description of the field/element.
	 *
	 * This is used as the title attribute of the selector.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('Adds billing address fields such as country, zip code.', 'ultimate-multisite');
	}

	/**
	 * Returns the tooltip of the field/element.
	 *
	 * This is used as the tooltip attribute of the selector.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_tooltip() {

		return __('Adds billing address fields such as country, zip code.', 'ultimate-multisite');
	}

	/**
	 * Returns the icon to be used on the selector.
	 *
	 * Can be either a dashicon class or a wu-dashicon class.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_icon() {

		return 'dashicons-wu-map1';
	}

	/**
	 * Returns the default values for the field-elements.
	 *
	 * This is passed through a wp_parse_args before we send the values
	 * to the method that returns the actual fields for the checkout form.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function defaults() {

		return [
			'zip_and_country' => true,
			'required'        => true,
		];
	}

	/**
	 * List of keys of the default fields we want to display on the builder.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function default_fields() {

		return [
			'name',
		];
	}

	/**
	 * If you want to force a particular attribute to a value, declare it here.
	 *
	 * The `required` attribute is intentionally not forced here so site owners
	 * can mark the billing address as optional via the "Address fields are
	 * required?" toggle in the element editor.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function force_attributes() {

		return [
			'id' => 'billing_address',
		];
	}

	/**
	 * Returns the list of additional fields specific to this type.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_fields() {

		return [
			'zip_and_country' => [
				'type'  => 'toggle',
				'title' => __('Display only ZIP and Country?', 'ultimate-multisite'),
				'desc'  => __('Checking this option will only add the ZIP and country fields, instead of all the normal billing address fields.', 'ultimate-multisite'),
				'value' => true,
			],
			'required'        => [
				'type'  => 'toggle',
				'title' => __('Address fields are required?', 'ultimate-multisite'),
				'desc'  => __('When enabled, the visible billing address fields must be filled in to complete checkout. Turn this off to make the billing address optional — useful for free plans, donations, or stores that only need a country for tax purposes. Stripe and PayPal still collect whatever billing data their own checkout surface requires (Stripe Payment Element: name, country, postal code; Stripe Checkout & PayPal: full address from their hosted page or the payer\'s PayPal account).', 'ultimate-multisite'),
				'value' => true,
			],
		];
	}

	/**
	 * Build a filed alternative.
	 *
	 * @since 2.0.11
	 *
	 * @param array  $base_field The base field.
	 * @param string $data_key_name The data key name.
	 * @param string $label_key_field The field label name.
	 * @param bool   $required Whether the alternative select must be marked required.
	 * @return array
	 */
	public function build_select_alternative(&$base_field, $data_key_name, $label_key_field, $required = true) {

		$base_field['wrapper_html_attr']['v-if'] = "!{$data_key_name}.length";

		$field = $base_field;

		$option_template = sprintf(
			'<option v-for="item in %s" :value="item.code">
			{{ item.name }}
		</option>',
			$data_key_name
		);

		$field['type']                      = 'select';
		$field['options_template']          = $option_template;
		$field['options']                   = [];
		$field['required']                  = (bool) $required;
		$field['wrapper_html_attr']['v-if'] = "{$data_key_name}.length";
		$field['html_attr']['v-bind:name']  = "'billing_" . str_replace('_list', '', $data_key_name) . "'";
		$field['title']                     = sprintf('<span v-html="%s">%s</span>', "labels.$label_key_field", $field['title']);

		if ($required) {
			$field['html_attr']['required'] = 'required';
		} else {
			unset($field['html_attr']['required']);
		}

		return $field;
	}

	/**
	 * Returns the field/element actual field array to be used on the checkout form.
	 *
	 * @since 2.0.0
	 *
	 * @param array $attributes Attributes saved on the editor form.
	 * @return array An array of fields, not the field itself.
	 */
	public function to_fields_array($attributes) {

		$zip_only = wu_string_to_bool($attributes['zip_and_country']);

		/*
		 * Per-form "address is required" toggle. Defaults to true so existing
		 * checkout forms keep their current behaviour after upgrade. When set
		 * to false on the editor, the per-field `required` markers are removed
		 * so customers can complete the form without filling in the address.
		 */
		$address_required = ! array_key_exists('required', $attributes)
			|| wu_string_to_bool($attributes['required']);

		$customer = wu_get_current_customer();

		/*
		 * Checks for an existing customer
		 */
		if ($customer) {
			$fields = $customer->get_billing_address()->get_fields($zip_only);
		} else {
			$checkout_form = \WP_Ultimo\Checkout\Checkout::get_instance()->checkout_form;

			$fields = \WP_Ultimo\Objects\Billing_Address::fields($zip_only, $checkout_form);
		}

		if ( ! $address_required) {
			foreach ($fields as $field_key => &$field_def) {
				unset($field_def['required']);

				if (isset($field_def['html_attr']['required'])) {
					unset($field_def['html_attr']['required']);
				}
			}

			unset($field_def);
		}

		if (isset($fields['billing_country'])) {
			$fields['billing_country']['html_attr'] = [
				'v-model' => 'country',
			];
		}

		if ( ! $zip_only) {
			if (isset($fields['billing_state'])) {
				$fields['billing_state']['html_attr'] = [
					'v-model.lazy' => 'state',
				];

				/**
				 * Format the state field accordingly.
				 *
				 * @since 2.0.11
				 */
				$fields['billing_state_select'] = $this->build_select_alternative($fields['billing_state'], 'state_list', 'state_field', $address_required);
			}

			if (isset($fields['billing_city'])) {
				$fields['billing_city']['html_attr'] = [
					'v-model.lazy' => 'city',
				];

				/**
				 * Format the city field accordingly.
				 *
				 * @since 2.0.11
				 */
				$fields['billing_city_select'] = $this->build_select_alternative($fields['billing_city'], 'city_list', 'city_field', $address_required);
			}
		}

		/*
		 * Gateways that collect billing address on their own checkout surface,
		 * so the plugin can hide its own billing fields when those gateways
		 * are selected.
		 *
		 * Caveat (worth knowing before relying on this): each of these
		 * gateways collects a different subset, and the plugin currently does
		 * not read the resulting address back into the customer record.
		 *
		 * - stripe / stripe-checkout (hosted) – Stripe collects the full
		 *   address on its hosted page (`billing_address_collection: required`).
		 * - stripe (embedded Payment Element) – Stripe collects only name,
		 *   country and postal code by default; street, city and state are
		 *   NOT collected unless the Payment Element is configured with a
		 *   custom `fields.billingDetails.address` config.
		 * - paypal / paypal-rest – PayPal uses the payer's account address;
		 *   the country is still needed up-front for tax calculation.
		 *
		 * Site owners that want full address data for embedded Stripe should
		 * filter `wu_billing_address_self_billing_gateways` to drop 'stripe'
		 * from the JS expression (e.g. return a string that only matches
		 * 'stripe-checkout', 'paypal', 'paypal-rest').
		 */
		$self_billing_gateways = (string) apply_filters(
			'wu_billing_address_self_billing_gateways',
			"gateway && (gateway.startsWith('stripe') || gateway === 'paypal' || gateway === 'paypal-rest')",
			$attributes
		);

		foreach ($fields as $field_key => &$field) {
			$field['wrapper_classes']              = trim(wu_get_isset($field, 'wrapper_classes', '') . ' ' . $attributes['element_classes']);
			$field['wrapper_html_attr']['v-cloak'] = 1;

			/*
			 * billing_country uses v-if (not v-show) so the input is removed
			 * from the DOM when payment is not required. This prevents the
			 * server-side required_with:billing_country rule from firing on a
			 * field the user cannot see or edit.
			 *
			 * For gateways that handle their own address collection (Stripe,
			 * PayPal) we also remove the field from the DOM so that it is never
			 * submitted and server-side validation cannot fire.
			 *
			 * billing_zip_code adds an extra `uses_postal_code` gate so the
			 * ZIP field is hidden for countries that don't use postal codes
			 * (Hong Kong, UAE, Angola, etc. — see Country_Default's list).
			 * Guard the symbol with `typeof` so older/minified checkout bundles
			 * that have not registered the Vue data key yet still render the form
			 * instead of throwing a ReferenceError.
			 */
			if ('billing_country' === $field_key) {
				$field['wrapper_html_attr']['v-if'] = "(order === false || order.should_collect_payment) && !($self_billing_gateways)";
			} elseif ('billing_zip_code' === $field_key) {
				$field['wrapper_html_attr']['v-if'] = "(order === false || order.should_collect_payment) && !($self_billing_gateways) && (typeof uses_postal_code === 'undefined' || uses_postal_code)";
			} elseif ($zip_only) {
				// Other fields in zip_only mode share the same gateway exclusion.
				$field['wrapper_html_attr']['v-if'] = "!($self_billing_gateways)";
			} else {
				$field['wrapper_html_attr']['v-show'] = 'order === false || order.should_collect_payment';
			}
		}

		uasort($fields, 'wu_sort_by_order');

		return $fields;
	}
}
