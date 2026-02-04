<?php
/**
 * The Settings API endpoint.
 *
 * @package WP_Ultimo
 * @subpackage API
 * @since 2.4.0
 */

namespace WP_Ultimo\API;

use WP_Ultimo\Settings;
use WP_Ultimo\UI\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * The Settings API endpoint.
 *
 * Provides REST API endpoints for reading and writing Ultimate Multisite settings.
 *
 * @since 2.4.0
 */
class Settings_Endpoint {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Loads the initial settings route hooks.
	 *
	 * @since 2.4.0
	 * @return void
	 */
	public function init(): void {

		add_action('wu_register_rest_routes', [$this, 'register_routes']);
	}

	/**
	 * Adds new routes to the wu namespace for the settings endpoint.
	 *
	 * @since 2.4.0
	 *
	 * @param \WP_Ultimo\API $api The API main singleton.
	 * @return void
	 */
	public function register_routes($api): void {

		$namespace = $api->get_namespace();

		// GET /settings - Retrieve all settings
		register_rest_route(
			$namespace,
			'/settings',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [$this, 'get_settings'],
				'permission_callback' => \Closure::fromCallable([$api, 'check_authorization']),
			]
		);

		// GET /settings/{setting_key} - Retrieve a specific setting
		register_rest_route(
			$namespace,
			'/settings/(?P<setting_key>[a-zA-Z0-9_-]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [$this, 'get_setting'],
				'permission_callback' => \Closure::fromCallable([$api, 'check_authorization']),
				'args'                => [
					'setting_key' => [
						'description'       => __('The setting key to retrieve.', 'ultimate-multisite'),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		// POST /settings - Update multiple settings
		register_rest_route(
			$namespace,
			'/settings',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [$this, 'update_settings'],
				'permission_callback' => \Closure::fromCallable([$api, 'check_authorization']),
				'args'                => $this->get_update_args(),
			]
		);

		// PUT/PATCH /settings/{setting_key} - Update a specific setting
		register_rest_route(
			$namespace,
			'/settings/(?P<setting_key>[a-zA-Z0-9_-]+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [$this, 'update_setting'],
				'permission_callback' => \Closure::fromCallable([$api, 'check_authorization']),
				'args'                => [
					'setting_key' => [
						'description'       => __('The setting key to update.', 'ultimate-multisite'),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'value'       => [
						'description' => __('The new value for the setting.', 'ultimate-multisite'),
						'required'    => true,
					],
				],
			]
		);
	}

	/**
	 * Get all settings.
	 *
	 * @since 2.4.0
	 *
	 * @param \WP_REST_Request $request WP Request Object.
	 * @return \WP_REST_Response
	 */
	public function get_settings($request) {

		$this->maybe_log_api_call($request);

		$settings = wu_get_all_settings();

		// Remove sensitive settings from the response
		$settings = $this->filter_sensitive_settings($settings);

		return rest_ensure_response(
			[
				'success'  => true,
				'settings' => $settings,
			]
		);
	}

	/**
	 * Get a specific setting.
	 *
	 * @since 2.4.0
	 *
	 * @param \WP_REST_Request $request WP Request Object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_setting($request) {

		$this->maybe_log_api_call($request);

		$setting_key = $request->get_param('setting_key');

		// Check if this is a sensitive setting
		if ($this->is_sensitive_setting($setting_key)) {
			return new \WP_Error(
				'setting_protected',
				__('This setting is protected and cannot be retrieved via the API.', 'ultimate-multisite'),
				['status' => 403]
			);
		}

		$value = wu_get_setting($setting_key, null);

		if (null === $value) {
			// Check if setting exists (even with null/false value) vs doesn't exist
			$all_settings = wu_get_all_settings();

			if (! array_key_exists($setting_key, $all_settings)) {
				return new \WP_Error(
					'setting_not_found',
					sprintf(
						/* translators: %s is the setting key */
						__('Setting "%s" not found.', 'ultimate-multisite'),
						$setting_key
					),
					['status' => 404]
				);
			}
		}

		return rest_ensure_response(
			[
				'success'     => true,
				'setting_key' => $setting_key,
				'value'       => $value,
			]
		);
	}

	/**
	 * Update multiple settings.
	 *
	 * @since 2.4.0
	 *
	 * @param \WP_REST_Request $request WP Request Object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings($request) {

		$this->maybe_log_api_call($request);

		$params = $request->get_json_params();

		if (empty($params) || ! is_array($params)) {
			$params = $request->get_body_params();
		}

		$settings_to_update = wu_get_isset($params, 'settings', $params);

		if (empty($settings_to_update) || ! is_array($settings_to_update)) {
			return new \WP_Error(
				'invalid_settings',
				__('No valid settings provided. Please provide a "settings" object with key-value pairs.', 'ultimate-multisite'),
				['status' => 400]
			);
		}

		// Validate, filter, and save settings
		$errors  = [];
		$updated = [];
		$failed  = [];

		foreach ($settings_to_update as $key => $value) {
			$result = $this->save_setting($key, $value);

			if (is_wp_error($result)) {
				$errors[] = $result->get_error_message();
				continue;
			}

			if ($result) {
				$updated[] = $key;
			} else {
				$failed[] = $key;
			}
		}

		if (empty($updated) && ! empty($errors)) {
			return new \WP_Error(
				'no_valid_settings',
				__('No valid settings to update after filtering.', 'ultimate-multisite'),
				[
					'status' => 400,
					'errors' => $errors,
				]
			);
		}

		$response_data = [
			'success' => ! empty($updated),
			'updated' => $updated,
		];

		if (! empty($failed)) {
			$response_data['failed'] = $failed;
		}

		if (! empty($errors)) {
			$response_data['warnings'] = $errors;
		}

		return rest_ensure_response($response_data);
	}

	/**
	 * Update a specific setting.
	 *
	 * @since 2.4.0
	 *
	 * @param \WP_REST_Request $request WP Request Object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_setting($request) {

		$this->maybe_log_api_call($request);

		$setting_key = $request->get_param('setting_key');

		$params = $request->get_json_params();

		if (empty($params)) {
			$params = $request->get_body_params();
		}

		if (! isset($params['value'])) {
			return new \WP_Error(
				'missing_value',
				__('The "value" parameter is required.', 'ultimate-multisite'),
				['status' => 400]
			);
		}

		$value  = wu_get_isset($params, 'value');
		$result = $this->save_setting($setting_key, $value);

		if (is_wp_error($result)) {
			return $result;
		}

		if (! $result) {
			return new \WP_Error(
				'update_failed',
				sprintf(
					/* translators: %s is the setting key */
					__('Failed to update setting "%s".', 'ultimate-multisite'),
					$setting_key
				),
				['status' => 500]
			);
		}

		return rest_ensure_response(
			[
				'success'     => true,
				'setting_key' => $setting_key,
				'value'       => wu_get_setting($setting_key),
			]
		);
	}

	/**
	 * Save a single setting with validation and sanitization.
	 *
	 * This method handles the common logic for saving settings:
	 * - Validates the setting key format
	 * - Checks if the setting is sensitive/protected
	 * - Sanitizes the value using the Field API if a field definition exists
	 * - Saves the setting to the database
	 *
	 * @since 2.4.0
	 *
	 * @param string $key   The setting key.
	 * @param mixed  $value The value to save.
	 * @return bool|\WP_Error True on success, false on save failure, WP_Error on validation failure.
	 */
	protected function save_setting(string $key, $value) {

		// Check if this is a sensitive setting
		if ($this->is_sensitive_setting($key)) {
			return new \WP_Error(
				'setting_protected',
				sprintf(
					/* translators: %s is the setting key */
					__('Setting "%s" is protected and cannot be modified via the API.', 'ultimate-multisite'),
					$key
				),
				['status' => 403]
			);
		}

		// Validate setting key format
		$sanitized_key = sanitize_key($key);

		if ($sanitized_key !== $key) {
			return new \WP_Error(
				'invalid_key_format',
				sprintf(
					/* translators: %s is the setting key */
					__('Invalid setting key format: "%s".', 'ultimate-multisite'),
					$key
				),
				['status' => 400]
			);
		}

		// Sanitize the value using Field API if field definition exists
		$sanitized_value = $this->sanitize_setting_value($key, $value);

		return wu_save_setting($key, $sanitized_value);
	}

	/**
	 * Sanitize a setting value using the Field API.
	 *
	 * Looks up the field definition from Settings and uses the Field class
	 * to apply appropriate sanitization based on field type.
	 *
	 * @since 2.4.0
	 *
	 * @param string $key   The setting key.
	 * @param mixed  $value The raw value to sanitize.
	 * @return mixed The sanitized value, or the original value if no field definition exists.
	 */
	protected function sanitize_setting_value(string $key, $value) {

		$field_definition = $this->get_field_definition($key);

		if (empty($field_definition)) {
			// No field definition found, apply basic sanitization based on value type
			if (is_string($value)) {
				return sanitize_text_field($value);
			}

			return $value;
		}

		// Create a Field instance and use its sanitization
		$field = new Field($key, $field_definition);
		$field->set_value($value);

		return $field->get_value();
	}

	/**
	 * Get the field definition for a setting key.
	 *
	 * Searches through all settings sections to find the field definition
	 * that matches the given setting key.
	 *
	 * @since 2.4.0
	 *
	 * @param string $key The setting key to look up.
	 * @return array|null The field definition array, or null if not found.
	 */
	protected function get_field_definition(string $key): ?array {

		$sections = Settings::get_instance()->get_sections();

		foreach ($sections as $section) {
			$fields = $section['fields'] ?? [];

			if (isset($fields[ $key ])) {
				return $fields[ $key ];
			}
		}

		return null;
	}

	/**
	 * Get the arguments schema for the update endpoint.
	 *
	 * @since 2.4.0
	 * @return array
	 */
	protected function get_update_args(): array {

		return [
			'settings' => [
				'description' => __('An object containing setting key-value pairs to update.', 'ultimate-multisite'),
				'type'        => 'object',
				'required'    => false,
			],
		];
	}

	/**
	 * Check if a setting is sensitive and should not be exposed via API.
	 *
	 * @since 2.4.0
	 *
	 * @param string $setting_key The setting key to check.
	 * @return bool
	 */
	protected function is_sensitive_setting(string $setting_key): bool {

		$sensitive_settings = [
			'api_key',
			'api_secret',
			'stripe_api_sk_live',
			'stripe_api_sk_test',
			'paypal_client_secret_live',
			'paypal_client_secret_sandbox',
		];

		/**
		 * Filter the list of sensitive settings that should not be exposed via API.
		 *
		 * @since 2.4.0
		 *
		 * @param array  $sensitive_settings List of sensitive setting keys.
		 * @param string $setting_key The setting key being checked.
		 */
		$sensitive_settings = apply_filters('wu_api_sensitive_settings', $sensitive_settings, $setting_key);

		return in_array($setting_key, $sensitive_settings, true);
	}

	/**
	 * Filter out sensitive settings from a settings array.
	 *
	 * @since 2.4.0
	 *
	 * @param array $settings The settings array to filter.
	 * @return array
	 */
	protected function filter_sensitive_settings(array $settings): array {

		foreach ($settings as $key => $_) {
			if ($this->is_sensitive_setting($key)) {
				unset($settings[ $key ]);
			}
		}

		return $settings;
	}

	/**
	 * Log API call if logging is enabled.
	 *
	 * Note: Request body is intentionally not logged to avoid
	 * accidentally storing sensitive data like passwords or API keys.
	 *
	 * @since 2.4.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return void
	 */
	protected function maybe_log_api_call($request): void {

		if (\WP_Ultimo\API::get_instance()->should_log_api_calls()) {
			$payload = [
				'route'      => $request->get_route(),
				'method'     => $request->get_method(),
				'url_params' => $request->get_url_params(),
			];

			wu_log_add('api-calls', wp_json_encode($payload, JSON_PRETTY_PRINT));
		}
	}
}
