<?php
/**
 * Default API hooks.
 *
 * @package WP_Ultimo
 * @subpackage API
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds a lighter ajax option to Ultimate Multisite.
 *
 * @since 1.9.14
 */
class API implements \WP_Ultimo\Interfaces\Singleton {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Namespace of our API endpoints
	 *
	 * @since 1.7.4
	 * @var string
	 */
	private string $namespace = 'wu';

	/**
	 * Version fo the API, this is used to build the API URL
	 *
	 * @since 1.7.4
	 * @var string
	 */
	private string $api_version = 'v2';

	/**
	 * Initiates the API hooks
	 *
	 * @since 1.7.4
	 * @return void
	 */
	public function init(): void {

		/**
		 * Add the admin settings for the API
		 *
		 * @since 1.7.4
		 */
		add_action('init', [$this, 'add_settings']);

		/**
		 * Refreshing API credentials
		 *
		 * @since 1.7.4
		 */
		add_action('wu_before_save_settings', [$this, 'refresh_API_credentials'], 10);

		/**
		 * Register the routes
	 *
		 * @since 1.7.4
		 */
		add_action('rest_api_init', [$this, 'register_routes']);

		/**
		 * Log API errors
		 *
		 * @since 2.0.0
		 */
		add_filter('rest_request_after_callbacks', [$this, 'log_api_errors'], 10, 3);

		/**
		 * We need to bypass the WP Core auth errors in our routes so we can use our api keys
		 *
		 * @since 2.1.2
		 */
		add_filter('rest_authentication_errors', [$this, 'maybe_bypass_wp_auth'], 1);
	}

	/**
	 * Maybe bypass the WP Core auth errors in our routes so we can use our api keys.
	 *
	 * @since 2.1.2
	 *
	 * @param \WP_Error|null|bool $result Error from another authentication handler, null if we should handle it, or another value if not.
	 * @return \WP_Error|null|bool The current filter value or true if we should handle it.
	 */
	public function maybe_bypass_wp_auth($result) {

		// Another plugin already bypass this request
		if (true === $result) {
			return $result;
		}

		$current_route = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));

		$rest_url  = rest_url();
		$rest_path = rtrim(wp_parse_url($rest_url, PHP_URL_PATH), '/');

		if (! str_starts_with($current_route, $rest_path . '/' . $this->get_namespace())) {
			return $result;
		}

		return true;
	}

	/**
	 * Allow admins to refresh their API credentials.
	 *
	 * @since 1.7.4
	 * @return void
	 */
	public function refresh_API_credentials(): void { // phpcs:ignore

		if (wu_request('submit_button') === 'refresh_api_credentials') {
			wu_save_setting('api_url', network_site_url());

			wu_save_setting('api_key', wp_generate_password(24, false));

			wu_save_setting('api_secret', wp_generate_password(24, false));

			wp_safe_redirect(network_admin_url('admin.php?page=wp-ultimo-settings&tab=api&api=refreshed&updated=1'));

			exit;
		}
	}

	/**
	 * Add the admin interface to create new webhooks
	 *
	 * @since 1.7.4
	 */
	public function add_settings(): void {
		/*
		 * API & Webhooks
		 * This section holds the API settings of the Ultimate Multisite Plugin.
		 */
		wu_register_settings_section(
			'api',
			[
				'title' => __('API & Webhooks', 'ultimate-multisite'),
				'desc'  => __('API & Webhooks', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-paper-plane',
				'order' => 95,
			]
		);

		wu_register_settings_field(
			'api',
			'api_header',
			[
				'title' => __('API Settings', 'ultimate-multisite'),
				'desc'  => __('Options related to Ultimate Multisite API endpoints.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'api',
			'enable_api',
			[
				'title'   => __('Enable API', 'ultimate-multisite'),
				'desc'    => __('Tick this box if you want Ultimate Multisite to add its own endpoints to the WordPress REST API. This is required for some integrations to work, most notabily, Zapier.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$refreshed_tag = '';

		if (wu_request('updated') && wu_request('api') === 'refreshed') {
			$refreshed_tag = sprintf('<span class="wu-ml-2 wu-text-green-600">%s</span>', __('Credentials Refreshed', 'ultimate-multisite'));
		}

		wu_register_settings_field(
			'api',
			'api_url',
			[
				'title'         => __('API URL', 'ultimate-multisite'),
				'desc'          => '',
				'tooltip'       => '',
				'copy'          => true,
				'type'          => 'text-display',
				'display_value' => network_site_url('wp-json'),
				'require'       => [
					'enable_api' => true,
				],
			]
		);

		wu_register_settings_field(
			'api',
			'api_key',
			[
				'title'           => __('API Key', 'ultimate-multisite') . $refreshed_tag,
				'desc'            => '',
				'tooltip'         => '',
				'type'            => 'text-display',
				'copy'            => true,
				'display_value'   => wp_generate_password(24, false),
				'wrapper_classes' => 'sm:wu-w-1/2 wu-float-left',
				'require'         => [
					'enable_api' => true,
				],
			]
		);

		wu_register_settings_field(
			'api',
			'api_secret',
			[
				'title'           => __('API Secret', 'ultimate-multisite') . $refreshed_tag,
				'tooltip'         => '',
				'type'            => 'text-display',
				'copy'            => true,
				'display_value'   => wp_generate_password(24, false),
				'wrapper_classes' => 'sm:wu-border-l-0 sm:wu-w-1/2 wu-float-left',
				'require'         => [
					'enable_api' => 1,
				],
			]
		);

		wu_register_settings_field(
			'api',
			'api_note',
			[
				'desc'            => fn() => esc_html__('This is your API Key. You cannot change it directly. To reset the API key and secret, use the button "Refresh API credentials" below.', 'ultimate-multisite'),
				'type'            => 'note',
				'classes'         => 'wu-text-gray-700 wu-text-xs',
				'wrapper_classes' => 'wu-bg-white sm:wu-border-t-0 sm:wu-mt-0 sm:wu-pt-0',
				'require'         => [
					'enable_api' => 1,
				],
			]
		);

		wu_register_settings_field(
			'api',
			'refresh_api_credentials',
			[
				'title'           => __('Refresh API Credentials', 'ultimate-multisite'),
				'type'            => 'submit',
				'classes'         => 'button wu-ml-auto',
				'wrapper_classes' => 'wu-bg-white sm:wu-border-t-0 sm:wu-mt-0 sm:wu-pt-0',
				'require'         => [
					'enable_api' => 1,
				],
			]
		);

		wu_register_settings_field(
			'api',
			'api_log_calls',
			[
				'title'   => __('Log API calls (Advanced)', 'ultimate-multisite'),
				'desc'    => __('Tick this box if you want to log all calls received via Ultimate Multisite API endpoints. You can access the logs on Ultimate Multisite &rarr; System Info &rarr; Logs.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
				'require' => [
					'enable_api' => 1,
				],
			]
		);

		wu_register_settings_field(
			'api',
			'webhook_header',
			[
				'title' => __('Webhook Settings', 'ultimate-multisite'),
				'desc'  => __('Options related to Ultimate Multisite API webhooks.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'api',
			'webhook_calls_blocking',
			[
				'title'   => __('Wait for Response (Advanced)', 'ultimate-multisite'),
				'desc'    => __('Tick this box if you want the Ultimate Multisite\'s webhook calls to wait for the remote server to respond. Keeping this option enabled can have huge effects on your network\'s performance, only enable it if you know what you are doing and need to debug webhook calls.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);
	}

	/**
	 * Returns the namespace of our API endpoints.
	 *
	 * @since 1.7.4
	 * @return string
	 */
	public function get_namespace() {

		return "$this->namespace/$this->api_version";
	}

	/**
	 * Returns the credentials.
	 *
	 * @since 1.7.4
	 * @return array
	 */
	public function get_auth() {

		return [
			'api_key'    => wu_get_setting('api_key', 'prevent'),
			'api_secret' => wu_get_setting('api_secret', 'prevent'),
		];
	}

	/**
	 * Validate a pair of API credentials
	 *
	 * @since 1.7.4
	 * @param string $api_key The API key.
	 * @param string $api_secret The API secret.
	 * @return boolean
	 */
	public function validate_credentials($api_key, $api_secret) {
		$auth = $this->get_auth();

		/*
		 * 'prevent' is the sentinel stored when API credentials have not been
		 * generated yet. Reject it explicitly so a request that supplies
		 * 'prevent' (or empty/unset credentials) can never authenticate.
		 */
		if ('prevent' === $auth['api_key'] || 'prevent' === $auth['api_secret'] || '' === (string) $api_key || '' === (string) $api_secret) {
			return false;
		}

		/*
		 * Compare with hash_equals() so the check runs in constant time and does
		 * not leak the stored key/secret through response-timing differences.
		 */
		return hash_equals((string) $auth['api_key'], (string) $api_key) && hash_equals((string) $auth['api_secret'], (string) $api_secret);
	}

	/**
	 * Check if we can log api calls.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function should_log_api_calls() {

		return apply_filters('wu_should_log_api_calls', wu_get_setting('api_log_calls', false));
	}

	/**
	 * Checks if we should log api calls or not, and if we should, log them.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request The request sent.
	 */
	public function maybe_log_api_call($request): void {

		if ($this->should_log_api_calls()) {
			$payload = [
				'route'       => $request->get_route(),
				'method'      => $request->get_method(),
				'url_params'  => $request->get_url_params(),
				'body_params' => $request->get_body(),
			];

			wu_log_add('api-calls', wp_json_encode($payload, JSON_PRETTY_PRINT));
		}
	}

	/**
	 * Log api errors.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed            $result The result of the REST API call.
	 * @param string|array     $handler The callback.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return mixed
	 */
	public function log_api_errors($result, $handler, $request) {

		if (is_wp_error($result) && str_starts_with($request->get_route(), '/wu')) {
			/*
			 * Log API call here if we didn't log it before.
			 */
			if ( ! $this->should_log_api_calls()) {
				$payload = [
					'route'       => $request->get_route(),
					'method'      => $request->get_method(),
					'url_params'  => $request->get_url_params(),
					'body_params' => $request->get_body(),
				];

				wu_log_add('api-errors', wp_json_encode($payload, JSON_PRETTY_PRINT));
			}

			wu_log_add('api-errors', $result);
		}
		return $result;
	}

	/**
	 * Tries to validate the API key and secret from the request
	 *
	 * @since 1.7.4
	 * @param \WP_REST_Request $request WP Request Object.
	 * @return boolean
	 */
	public function check_authorization($request) {

		if (! empty($_SERVER['PHP_AUTH_USER']) && ! empty($_SERVER['PHP_AUTH_PW'])) {
			$api_key    = sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_USER']));
			$api_secret = sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_PW']));
		} else {
			$params = $request->get_params();

			$api_key    = wu_get_isset($params, 'api_key', wu_get_isset($params, 'api-key'));
			$api_secret = wu_get_isset($params, 'api_secret', wu_get_isset($params, 'api-secret'));
		}

		if (false === $api_key) {
			return false;
		}

		return $this->validate_credentials($api_key, $api_secret);
	}

	/**
	 * Checks if the API routes are available or not, via the settings.
	 *
	 * @since 1.7.4
	 * @return boolean
	 */
	public function is_api_enabled() {

		/**
		 * Allow plugin developers to force a given state for the API.
		 *
		 * @since 1.7.4
		 * @return boolean
		 */
		return apply_filters('wu_is_api_enabled', wu_get_setting('enable_api', true));
	}

	/**
	 * Register the API routes.
	 *
	 * @since 1.7.4
	 * @return void
	 */
	public function register_routes(): void {

		if ( ! $this->is_api_enabled()) {
			return;
		}

		$namespace = $this->get_namespace();

		register_rest_route(
			$namespace,
			'/auth',
			[
				'methods'             => 'GET',
				'callback'            => [$this, 'auth'],
				'permission_callback' => [$this, 'check_authorization'],
			]
		);

		/**
		 * Allow additional routes to be registered.
		 *
		 * This is used by our /register endpoint.
		 *
		 * @since 2.0.0
		 * @param self $api_instance The current API instance.
		 */
		do_action('wu_register_rest_routes', $this);
	}

	/**
	 * Dummy endpoint to low services to test the authentication method being used.
	 *
	 * @since 1.7.4
	 *
	 * @param \WP_REST_Request $request WP Request Object.
	 * @return void
	 */
	public function auth($request): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		$current_site = get_current_site();

		wp_send_json(
			[
				'success' => true,
				'label'   => $current_site->site_name,
				'message' => __('Welcome to our API', 'ultimate-multisite'),
			]
		);
	}
}
