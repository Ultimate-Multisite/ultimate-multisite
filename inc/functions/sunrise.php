<?php
/**
 * Sunrise Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * General helper functions for sunrise.
 *
 * @author      Arindo Duque
 * @category    Admin
 * @package     WP_Ultimo/Sunrise
 * @version     2.0.11
 */
function wu_should_load_sunrise() {

	return \WP_Ultimo\Sunrise::should_load_sunrise();
}

/**
 * Get a setting value, when te normal APIs are not available.
 *
 * Should only be used if we're running in sunrise.
 *
 * @since 2.0.0
 *
 * @param string $setting Setting to get.
 * @param mixed  $default_value Default value.
 * @return mixed
 */
function wu_get_setting_early($setting, $default_value = false) {

	if (did_action('wp_ultimo_load')) {
		_doing_it_wrong('wu_get_setting_early', esc_html__('Regular setting APIs are already available. You should use wu_get_setting() instead.', 'ultimate-multisite'), '2.0.0');
	}

	$settings_key = \WP_Ultimo\Settings::KEY;

	$settings = get_network_option(null, 'wp-ultimo_' . $settings_key);

	return wu_get_isset($settings, $setting, $default_value);
}

/**
 * Set a setting value, when te normal APIs are not available.
 *
 * Should only be used if we're running in sunrise.
 *
 * @since 2.0.20
 *
 * @param string $key   Setting to save.
 * @param mixed  $value Setting value.
 */
function wu_save_setting_early($key, $value) {

	if (did_action('wp_ultimo_load')) {
		_doing_it_wrong('wu_save_setting_early', esc_html__('Regular setting APIs are already available. You should use wu_save_setting() instead.', 'ultimate-multisite'), '2.0.20');
	}

	$settings_key = \WP_Ultimo\Settings::KEY;

	$settings = get_network_option(null, 'wp-ultimo_' . $settings_key);

	$settings[ $key ] = $value;

	return update_network_option(null, 'wp-ultimo_' . $settings_key, $settings);
}

/**
 * Get the security mode key used to disable security mode.
 *
 * This key is exposed in an unauthenticated query string (?wu_secure=KEY) that
 * turns the network-wide recovery "security mode" off, so it must be
 * unpredictable. It used to be substr(md5(admin_email), 0, 6) — only ~24 bits
 * and derived from a frequently public/guessable value, which an attacker could
 * compute or brute-force. We now use a high-entropy random secret generated once
 * and stored as a network option. random_bytes() is used (not
 * wp_generate_password) because this runs from sunrise, before pluggable.php is
 * loaded. The current key is shown to admins on the settings screen, so rotating
 * it is transparent for the documented copy-the-URL workflow.
 *
 * @since 2.0.20
 */
function wu_get_security_mode_key(): string {

	$key = (string) get_network_option(null, 'wu_security_mode_key', '');

	if ('' === $key) {
		$key = bin2hex(random_bytes(16));

		update_network_option(null, 'wu_security_mode_key', $key);
	}

	return $key;
}

/**
 * Early substitute for wp_kses_data before it exists.
 *
 * Sanitize content with allowed HTML KSES rules.
 *
 * This function expects unslashed data.
 *
 * @since 2.1.0
 *
 * @param string $data Content to filter, expected to not be escaped.
 * @return string Filtered content.
 */
function wu_kses_data($data) {

	return function_exists('wp_kses_data') ? wp_kses_data($data) : $data;
}
