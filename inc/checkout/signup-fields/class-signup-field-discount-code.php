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
class Signup_Field_Discount_Code extends Base_Signup_Field {

	/**
	 * Returns the type of the field.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_type() {

		return 'discount_code';
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
	 * Requires the title of the field/element type.
	 *
	 * This is used on the Field/Element selection screen.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('Coupon Code', 'ultimate-multisite');
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

		return __('Adds an additional field to apply a discount code.', 'ultimate-multisite');
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

		return __('Adds an additional field to apply a discount code.', 'ultimate-multisite');
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

		return 'dashicons-wu-tag1';
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
			'placeholder' => '',
			'default'     => '',
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
			'placeholder',
			'tooltip',
		];
	}

	/**
	 * If you want to force a particular attribute to a value, declare it here.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function force_attributes() {

		return [
			'id' => 'discount_code',
		];
	}

	/**
	 * Returns the list of additional fields specific to this type.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_fields() {

		return [];
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

		$checkout_fields = [];

		$checkout_fields['discount_code_checkbox'] = [
			'id'        => 'discount_code_checkbox',
			'type'      => 'toggle',
			'name'      => __('Have a coupon code?', 'ultimate-multisite'),
			'class'     => 'wu-w-auto',
			'html_attr' => [
				'v-model' => 'toggle_discount_code',
			],
		];

		$checkout_fields['discount_code'] = [
			'type'              => 'text',
			'id'                => 'discount_code',
			'name'              => $attributes['name'],
			'placeholder'       => $attributes['placeholder'],
			'tooltip'           => $attributes['tooltip'],
			'default'           => $attributes['default'],
			'wrapper_classes'   => wu_get_isset($attributes, 'wrapper_element_classes', ''),
			'classes'           => wu_get_isset($attributes, 'element_classes', ''),
			'wrapper_html_attr' => [
				'v-show' => 'toggle_discount_code',
				'style'  => $this->calculate_style_attr(),
			],
			'html_attr'         => [
				'v-model.lazy'                => 'discount_code',
				'v-init:discount_code'        => "'{$this->get_value()}'",
				/*
				 * Use explicit 'true'/'false' strings so Vue evaluates them as
				 * boolean literals. An empty PHP false value becomes an empty
				 * attribute (""), which Vue treats as undefined — causing the
				 * toggle_discount_code watcher to fire and clear discount_code
				 * on page load even when no coupon is pre-applied.
				 */
				'v-init:toggle_discount_code' => ! empty($this->get_value()) ? 'true' : 'false',
			],
		];

		return $checkout_fields;
	}
}
