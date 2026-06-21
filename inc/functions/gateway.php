<?php
/**
 * Gateway Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Managers\Gateway_Manager;

/**
 * Adds a new Gateway to the System. Used by gateways to make themselves visible.
 *
 * @since 2.0.0
 *
 * @param string $id ID of the gateway. This is how we will identify the gateway in the system.
 * @param string $title Name of the gateway.
 * @param string $desc A description of the gateway to help super admins understand what services they integrate with.
 * @param string $class_name Gateway class name.
 * @param bool   $hidden If we need to hide this gateway publicly.
 * @return bool
 */
function wu_register_gateway($id, $title, $desc, $class_name, $hidden = false) {

	if ( ! did_action('wu_register_gateways')) {
		_doing_it_wrong(__FUNCTION__, esc_html__('You should not register new payment gateways before the wu_register_gateways hook.', 'ultimate-multisite'), '2.0.0');
	}

	return Gateway_Manager::get_instance()->register_gateway($id, $title, $desc, $class_name, $hidden);
}

/**
 * Returns the currently registered gateways.
 *
 * @since 2.0.0
 *
 * @return array
 */
function wu_get_gateways() {

	return Gateway_Manager::get_instance()->get_registered_gateways();
}

/**
 * Returns the currently registered and active gateways.
 *
 * @since 2.0.0
 * @return array
 */
function wu_get_active_gateways() {

	$gateways = [];

	$active_gateways = (array) wu_get_setting('active_gateways', []);

	foreach ($active_gateways as $active_gateway) {
		if (Gateway_Manager::get_instance()->is_gateway_registered($active_gateway)) {
			$gateways[ $active_gateway ] = Gateway_Manager::get_instance()->get_gateway($active_gateway);
		}
	}

	return apply_filters('wu_get_active_gateways', $gateways);
}

/**
 * Returns a gateway class if it exists.
 *
 * @since 2.0.0
 *
 * @param string $id Gateway ID.
 * @param string $subscription Subscription object to load into the gateway.
 * @return \WP_Ultimo\Gateways\Base_Gateway|false Gateway class.
 */
function wu_get_gateway($id, $subscription = null) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

	$gateway = Gateway_Manager::get_instance()->get_gateway($id);

	if ( ! $gateway) {
		return apply_filters('wu_get_gateway', false, $id, $subscription);
	}

	$gateway_class = new $gateway['class_name']();

	return apply_filters('wu_get_gateway', $gateway_class, $id, $subscription);
}

/**
 * Returns the list of available gateways as key => name.
 *
 * @since 2.0.0
 * @return array
 */
function wu_get_gateway_as_options() {

	$options = [];

	foreach (wu_get_gateways() as $gateway_slug => $gateway) {
		$instance = class_exists($gateway['class_name']) ? new $gateway['class_name']() : false;

		if (false === $instance || $gateway['hidden']) {
			continue;
		}

		$options[ $gateway_slug ] = $gateway['title'];
	}

	return $options;
}

/**
 * Get the active gateways.
 *
 * @since 2.0.0
 * @return array
 */
function wu_get_active_gateway_as_options() {

	$options = [];

	foreach (wu_get_active_gateways() as $gateway_slug => $gateway) {
		$instance = class_exists($gateway['class_name']) ? new $gateway['class_name']() : false;

		if (false === $instance || $gateway['hidden']) {
			continue;
		}

		$title = $instance->get_public_title();

		$options[ $gateway_slug ] = apply_filters("wu_gateway_{$gateway_slug}_as_option_title", $title, $gateway);
	}

	return $options;
}
