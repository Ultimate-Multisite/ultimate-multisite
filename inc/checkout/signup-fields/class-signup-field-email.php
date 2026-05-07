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
class Signup_Field_Email extends Base_Signup_Field {

	/**
	 * Returns the type of the field.
	 *
	 * @since 2.0.0
	 */
	public function get_type(): string {

		return 'email';
	}
	/**
	 * Returns if this field should be present on the checkout flow or not.
	 *
	 * @since 2.0.0
	 */
	public function is_required(): bool {

		return true;
	}
	/**
	 * Is this a user-related field?
	 *
	 * If this is set to true, this field will be hidden
	 * when the user is already logged in.
	 *
	 * @since 2.0.0
	 */
	public function is_user_field(): bool {

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

		return __('Email', 'ultimate-multisite');
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

		return __('Adds a email address field. This email address will be used to create the WordPress user.', 'ultimate-multisite');
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

		return __('Adds a email address field. This email address will be used to create the WordPress user.', 'ultimate-multisite');
	}
	/**
	 * Returns the icon to be used on the selector.
	 *
	 * Can be either a dashicon class or a wu-dashicon class.
	 *
	 * @since 2.0.0
	 */
	public function get_icon(): string {

		return 'dashicons-wu-at-sign';
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
			'display_notices'     => true,
			'email_confirm_field' => false,
			'email_confirm_label' => __('Confirm Email', 'ultimate-multisite'),
			'enable_inline_login' => true,
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
			'id'       => 'email_address',
			'required' => true,
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
			'display_notices'     => [
				'type'      => 'toggle',
				'title'     => __('Display Notices', 'ultimate-multisite'),
				'desc'      => __('When the customer is already logged in, a box with the customer\'s username and a link to logout is displayed instead of the email field. Disable this option if you do not want that box to show up.', 'ultimate-multisite'),
				'tooltip'   => '',
				'value'     => 1,
				'html_attr' => [
					'v-model' => 'display_notices',
				],
			],
			'email_confirm_field' => [
				'type'  => 'toggle',
				'title' => __('Display Email Confirm Field', 'ultimate-multisite'),
				'desc'  => __('Adds a "Confirm Email" field below email field to reduce the chance of making a mistake.', 'ultimate-multisite'),
				'value' => 1,
			],
			'enable_inline_login' => [
				'type'  => 'toggle',
				'title' => __('Enable Inline Login', 'ultimate-multisite'),
				'desc'  => __('When enabled, users entering an existing email address will see an inline login prompt to authenticate with their password without leaving the page.', 'ultimate-multisite'),
				'value' => 1,
			],
		];
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

		if (is_user_logged_in()) {
			if ($attributes['display_notices']) {
				$checkout_fields['login_note'] = [
					'type'              => 'note',
					'title'             => __('Not you?', 'ultimate-multisite'),
					'desc'              => [$this, 'render_not_you_customer_message'],
					'wrapper_classes'   => wu_get_isset($attributes, 'wrapper_element_classes', ''),
					'wrapper_html_attr' => [
						'style' => $this->calculate_style_attr(),
					],
				];
			}
		} else {
			$checkout_fields['email_address'] = [
				'type'              => 'text',
				'id'                => 'email_address',
				'name'              => $attributes['name'],
				'placeholder'       => $attributes['placeholder'],
				'tooltip'           => $attributes['tooltip'],
				'value'             => $this->get_value(),
				'required'          => true,
				'wrapper_classes'   => wu_get_isset($attributes, 'wrapper_element_classes', ''),
				'classes'           => wu_get_isset($attributes, 'element_classes', ''),
				'html_attr'         => [
					'v-model' => 'email_address',
				],
				'wrapper_html_attr' => [
					'style' => $this->calculate_style_attr(),
				],
			];
			if ($attributes['email_confirm_field']) {
				$checkout_fields['email_address_conf'] = [
					'type'              => 'text',
					'id'                => 'email_address_conf',
					'name'              => $attributes['email_confirm_label'],
					'placeholder'       => '',
					'tooltip'           => '',
					'meter'             => false,
					'required'          => true,
					'wrapper_classes'   => wu_get_isset($attributes, 'wrapper_element_classes', ''),
					'classes'           => wu_get_isset($attributes, 'element_classes', ''),
					'wrapper_html_attr' => [
						'style' => $this->calculate_style_attr(),
					],
				];
			}

			if (wu_get_isset($attributes, 'enable_inline_login', true)) {
				$checkout_fields['email_inline_login_prompt'] = [
					'type'              => 'html',
					'id'                => 'email_inline_login_prompt',
					'content'           => [$this, 'render_inline_login_prompt'],
					'wrapper_classes'   => '',
					'wrapper_html_attr' => [
						'v-if'    => "show_login_prompt && login_prompt_field === 'email'",
						'v-cloak' => true,
					],
				];
			}
		}

		return $checkout_fields;
	}

	/**
	 * Renders the login message for users that are not logged in.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function render_not_you_customer_message() {

		$login_url = wp_login_url(add_query_arg('logged', '1'), true);

		ob_start();

		?>

		<p class="wu-p-4 wu-bg-yellow-200">
		<?php

		// translators: 1$s is the display name of the user currently logged in.
		printf(wp_kses_post(__('Not %1$s? <a href="%2$s">Log in</a> using your account.', 'ultimate-multisite')), esc_html(wp_get_current_user()->display_name), esc_attr($login_url));

		?>
		</p>

		<?php

		return ob_get_clean();
	}

	/**
	 * Renders the inline login prompt HTML.
	 *
	 * @since 2.0.20
	 * @return string
	 */
	public function render_inline_login_prompt(): string {
		return wu_get_template_contents('checkout/partials/inline-login-prompt', ['field_type' => 'email']);
	}
}
