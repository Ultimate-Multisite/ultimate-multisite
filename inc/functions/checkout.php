<?php
/**
 * Checkout Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Managers\Signup_Fields_Manager;

/**
 * Needs to be removed.
 *
 * @todo Remove this and use out functions instead.
 * @since 2.0.0
 * @return \WP_Error
 */
function wu_errors() {

	global $wu_errors;

	if ( ! is_wp_error($wu_errors)) {
		$wu_errors = new \WP_Error();
	}

	return $wu_errors;
}

/**
 * Generate an idempotency key.
 *
 * @since 2.0.0
 *
 * @param array  $args Arguments used to create or update the current object.
 * @param string $context The context in which the key was generated.
 * @return string
 */
function wu_stripe_generate_idempotency_key($args, $context = 'new') {

	$idempotency_key = md5(wp_json_encode($args));

	/**
	 * Filters the idempotency_key value sent with the Stripe charge options.
	 *
	 * @since 3.5.0
	 *
	 * @param string $idempotency_key Value of the idempotency key.
	 * @param array  $args            Arguments used to help generate the key.
	 * @param string $context         Context under which the idempotency key is generated.
	*/
	$idempotency_key = apply_filters('wu_stripe_generate_idempotency_key', $idempotency_key, $args, $context);

	return $idempotency_key;
}

/**
 * Loops through the signup field types to return the checkout fields.
 *
 * @since 2.0.0
 *
 * @param array $fields List of signup field types.
 * @return array
 */
function wu_create_checkout_fields($fields = []) {

	$field_types = Signup_Fields_Manager::get_instance()->get_field_types();

	$actual_fields = [];

	// Extra check to prevent error messages from being displayed.
	if ( ! is_array($fields)) {
		$fields = [];
	}

	foreach ($fields as $field) {
		$type = $field['type'];

		if ( ! wu_get_isset($field_types, $type)) {
			continue;
		}

		try {
			$field_class = new $field_types[ $type ]();
		} catch (\Throwable $exception) {
			continue;
		}

		$field = wp_parse_args($field, $field_class->defaults());

		$field = array_merge($field, $field_class->force_attributes());

		/*
		 * Check Field Visibility
		 */
		$visibility = wu_get_isset($field, 'logged', 'always');

		if ('always' !== $visibility) {
			if ('guests_only' === $visibility && is_user_logged_in()) {
				continue;
			}

			if ('logged_only' === $visibility && ! is_user_logged_in()) {
				continue;
			}
		}

		/*
		 * Pass the attributes down to the field class.
		 */
		$field_class->set_attributes($field);

		/*
		 * Makes sure we have default indexes.
		 */
		$field = wp_parse_args(
			$field,
			[
				'element_classes' => '',
				'classes'         => '',
			]
		);

		$field_array = $field_class->to_fields_array($field);

		/**
		 * Fires before a field is added to the checkout form.
		 *
		 * @since 2.1.1
		 * @param array $field_array The field to be inserted.
		 */
		do_action("wu_checkout_add_field_{$field_class->get_type()}", $field_array);

		$actual_fields = array_merge($actual_fields, $field_array);
	}

	return $actual_fields;
}

/**
 * Returns the URL for the registration page.
 *
 * @since 2.0.0
 * @param string|false $path Path to attach to the end of the URL.
 * @return string
 */
function wu_get_registration_url($path = false) {

	$checkout_pages = \WP_Ultimo\Checkout\Checkout_Pages::get_instance();

	$url = $checkout_pages->get_page_url('register');

	if ( ! $url) {

		/**
		 * Just to be extra sure, we try to fetch the URL
		 * for a main site page that has the registration slug.
		 */
		$url = wu_switch_blog_and_run(
			function () {

				$maybe_register_page = get_page_by_path('register');

				if ($maybe_register_page && has_shortcode($maybe_register_page->post_content, 'wu_checkout')) {
					return get_the_permalink($maybe_register_page->ID);
				}
			}
		);

		return $url ?? '#no-registration-url';
	}

	return $url . $path;
}

/**
 * Returns the URL for the login page.
 *
 * @since 2.0.0
 * @param string|false $path Path to attach to the end of the URL.
 */
function wu_get_login_url($path = false): string {

	$checkout_pages = \WP_Ultimo\Checkout\Checkout_Pages::get_instance();

	$url = $checkout_pages->get_page_url('login');

	if ( ! $url) {
		return '#no-login-url';
	}

	return $url . $path;
}

/**
 * Checks if we allow for multiple memberships.
 *
 * @todo: review this.
 * @since 2.0.0
 * @return boolean
 */
function wu_multiple_memberships_enabled() {

	return (bool) wu_get_setting('enable_multiple_memberships', true);
}

/**
 * Get the number of days in a billing cycle.
 *
 * Taken from WooCommerce.
 *
 * @since 2.0.0
 * @param string $duration_unit Unit: day, month, or year.
 * @param int    $duration Cycle duration.
 *
 * @return float
 */
function wu_get_days_in_cycle($duration_unit, $duration) {

	switch ($duration_unit) {
		case 'day':
			$days_in_cycle = $duration;
			break;
		case 'week':
			$days_in_cycle = $duration * 7;
			break;
		case 'month':
			$days_in_cycle = $duration * 30.4375;
			break;
		case 'year':
			$days_in_cycle = $duration * 365.25;
			break;
		default:
			$days_in_cycle = 0;
			break;
	}

	return $days_in_cycle;
}

/**
 * Register a new field type.
 *
 * Field types are types of field (duh!) that can be
 * added to the checkout flow and other forms inside Ultimate Multisite.
 *
 * @see https://ultimatemultisite.com/docs/developer/hooks/Filters/wu_checkout_field_types
 *
 * @since 2.0.0
 *
 * @param string $field_type_id The field type id. E.g. pricing_table, template_selection.
 * @param string $field_type_class_name The field type class name. The "absolute" path to the class.
 * @return void
 */
function wu_register_field_type($field_type_id, $field_type_class_name) {

	add_filter(
		'wu_checkout_field_types',
		function ($field_types) use ($field_type_id, $field_type_class_name) {

			$field_types[ $field_type_id ] = $field_type_class_name;

			return $field_types;
		}
	);
}

/**
 * Register a new field template for a field type.
 *
 * Field templates are different layouts that can be added to
 * Ultimate Multisite to be used as the final representation of a given
 * checkout field.
 *
 * @see https://ultimatemultisite.com/docs/user-guide/configuration/customizing-your-registration-form
 *
 * @since 2.0.0
 *
 * @param string $field_type The field type. E.g. pricing_table, template_selection.
 * @param string $field_template_id The field template ID. e.g. clean, minimal.
 * @param string $field_template_class_name The field template class name. The "absolute" path to the class.
 * @return void
 */
function wu_register_field_template($field_type, $field_template_id, $field_template_class_name) {

	add_filter(
		'wu_checkout_field_templates',
		function ($field_templates) use ($field_type, $field_template_id, $field_template_class_name) {

			$field_templates_for_field_type = wu_get_isset($field_templates, $field_type, []);

			$field_templates_for_field_type[ $field_template_id ] = $field_template_class_name;

			$field_templates[ $field_type ] = $field_templates_for_field_type;

			return $field_templates;
		}
	);
}
