<?php
/**
 * Handle access to addons.
 */

namespace WP_Ultimo;

use Psr\Log\LogLevel;

/**
 * Addon Repository class for handling addon downloads and updates.
 *
 * This class manages the authentication and download process for
 * premium addons from the WP Ultimo repository.
 */
class Addon_Repository {

	/**
	 * Legacy key used by source packages released before the OAuth secrets were
	 * regenerated on every release build.
	 *
	 * @var string
	 */
	private const LEGACY_CREDENTIAL_KEY = '4ffb3de0414a284b500b368caedf7c40f03cac215eb83b0164e91c491ced4bdf';

	private string $authorization_header = '';
	private string $client_id;
	private string $client_secret;

	/**
	 * Add the main hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init() {
		add_filter('upgrader_pre_download', [$this,'upgrader_pre_download'], 10, 4);
	}

	/**
	 * @param string $data base64 encoded string.
	 *
	 * @return string
	 */
	private function decrypt_value(string $data): string {
		// If the site doesn't have openssl, they just won't get auto updates.
		if ( ! function_exists('openssl_decrypt') || ! function_exists('openssl_cipher_iv_length')) {
			return '';
		}

		$data = base64_decode($data, true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if (false === $data) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length('aes-256-cbc');

		if (false === $iv_length || $iv_length >= strlen($data)) {
			return '';
		}

		$iv          = substr($data, 0, $iv_length);
		$cipher_text = substr($data, $iv_length);

		foreach ($this->get_decryption_keys() as $key) {
			$decrypted = openssl_decrypt($cipher_text, 'aes-256-cbc', $key, 0, $iv);

			if (false !== $decrypted) {
				return $decrypted;
			}
		}

		return '';
	}

	/**
	 * Gets supported OAuth credential decryption keys.
	 *
	 * @return array
	 */
	private function get_decryption_keys(): array {
		$keys = [
			hash_file('sha256', __FILE__), // Hash of this file for freshly built release archives.
			self::LEGACY_CREDENTIAL_KEY,
		];

		return array_values(array_unique(array_filter($keys)));
	}

	/**
	 * @return string
	 */
	private function get_client_id(): string {
		if (isset($this->client_id)) {
			return $this->client_id;
		}
		$stuff               = include __DIR__ . '/stuff.php';
		$this->client_id     = $this->decrypt_value($stuff[0]);
		$this->client_secret = $this->decrypt_value($stuff[1]);
		return $this->client_id;
	}

	/**
	 * @return string
	 */
	private function get_client_secret(): string {
		if (isset($this->client_secret)) {
			return $this->client_secret;
		}
		$stuff               = include __DIR__ . '/stuff.php';
		$this->client_id     = $this->decrypt_value($stuff[0]);
		$this->client_secret = $this->decrypt_value($stuff[1]);
		return $this->client_secret;
	}

	/**
	 * @return string
	 */
	public function get_access_token(): string {
		$refresh_token = wu_get_option('wu-refresh-token');

		$access_token = '';

		if ($refresh_token) {
			$access_token = get_transient('wu-access-token');

			if ( ! $access_token) {
				$url     = MULTISITE_ULTIMATE_UPDATE_URL . 'oauth/token';
				$data    = [
					'grant_type'    => 'refresh_token',
					'client_id'     => $this->get_client_id(),
					'client_secret' => $this->get_client_secret(),
					'refresh_token' => $refresh_token,
				];
				$request = wp_remote_post($url, ['body' => $data]);
				$body    = wp_remote_retrieve_body($request);
				$code    = wp_remote_retrieve_response_code($request);
				$message = wp_remote_retrieve_response_message($request);

				if (200 === absint($code) && 'OK' === $message) {
					$response = json_decode($body, true);
					if ( ! empty($response['access_token'])) {
						$access_token = $response['access_token'];
						set_transient('wu-access-token', $response['access_token'], $response['expires_in']);
					}
				}
			}
		}
		return $access_token ?: '';
	}

	/**
	 * @return string
	 */
	public function get_oauth_url(): string {
		return add_query_arg(
			[
				'response_type' => 'code',
				'client_id'     => $this->get_client_id(),
				'redirect_uri'  => wu_network_admin_url('wp-ultimo-addons'),
			],
			MULTISITE_ULTIMATE_UPDATE_URL . 'oauth/authorize'
		);
	}

	/**
	 * @return array
	 * @throws \Exception If request fails.
	 */
	public function get_user_data(): array {

		$access_token = $this->get_access_token();

		if ($access_token) {
			$url     = MULTISITE_ULTIMATE_UPDATE_URL . 'oauth/me';
			$request = \wp_remote_get(
				$url,
				[
					'headers'   => [
						'Authorization' => 'Bearer ' . $access_token,
					],
					'sslverify' => defined('WP_DEBUG') && WP_DEBUG ? false : true,
				]
			);
			$body    = wp_remote_retrieve_body($request);
			$code    = wp_remote_retrieve_response_code($request);
			$message = wp_remote_retrieve_response_message($request);
			if (is_wp_error($request)) {
				wu_log_add('api-calls', $request->get_error_message(), LogLevel::ERROR);
				$this->delete_tokens();
			}
			if (200 === absint($code) && 'OK' === $message) {
				$user = json_decode($body, true);
				return $user;
			}
		}
		return [];
	}

	/**
	 * @param bool|\WP_Error|string $reply Whether to bail without returning the package. WordPress filter chain may pass WP_Error or string from other plugins.
	 * @param string                $package The package file name.
	 * @param \WP_Upgrader          $upgrader The WP_Upgrader instance.
	 * @param array                 $hook_extra Extra arguments passed to hooked filters.
	 */
	public function upgrader_pre_download($reply, $package, \WP_Upgrader $upgrader, $hook_extra) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if (! is_bool($reply)) {
			return $reply; // Pass through non-bool values (e.g. WP_Error or string) set by other filters.
		}
		if (str_starts_with($package, MULTISITE_ULTIMATE_UPDATE_URL)) {
			$access_token = $this->get_access_token();

			if (empty($access_token)) {
				// translators: %s the url for login.
				return new \WP_Error('noauth', sprintf(__('You must <a href="%s" target="_parent">Connect to UltimateMultisite.com</a> first.', 'ultimate-multisite'), $this->get_oauth_url()));
			}
			$this->authorization_header = 'Bearer ' . $access_token;

			add_filter('http_request_args', [$this, 'set_update_download_headers'], 10, 2);

			$response = wp_remote_get(
				$package,
				[
					'method' => 'HEAD',
				]
			);

			$code = wp_remote_retrieve_response_code($response);
			if (is_wp_error($response)) {
				return $response;
			}

			if (403 === absint($code)) {
				return new \WP_Error('http_request_failed', esc_html__('403 Access Denied returned from server. Ensure you have an active subscription for this addon.', 'ultimate-multisite'));
			}

			if (! in_array(absint($code), [200, 302, 301], true)) {
				return new \WP_Error('http_request_failed', esc_html__('Failed to connect to the update server. Please try again later.', 'ultimate-multisite'));
			}
		}
		return $reply;
	}

	/**
	 * Saves the OAuth access token using the authorization code.
	 *
	 * @param string $code The authorization code received from OAuth provider.
	 * @param string $redirect_url The redirect URL used in the OAuth flow.
	 *
	 * @return void
	 * @throws \Exception When the API request fails.
	 */
	public function save_access_token($code, $redirect_url) {

		$url     = MULTISITE_ULTIMATE_UPDATE_URL . 'oauth/token';
		$data    = array(
			'code'          => $code,
			'redirect_uri'  => $redirect_url,
			'grant_type'    => 'authorization_code',
			'client_id'     => $this->get_client_id(),
			'client_secret' => $this->get_client_secret(),
		);
		$request = \wp_remote_post(
			$url,
			[
				'body' => $data,
			]
		);

		$body    = wp_remote_retrieve_body($request);
		$code    = wp_remote_retrieve_response_code($request);
		$message = wp_remote_retrieve_response_message($request);

		if (is_wp_error($request)) {
			throw new \Exception(esc_html($request->get_error_message()), esc_html($request->get_error_code()));
		}

		if (200 === absint($code) && 'OK' === $message) {
			$response = json_decode($body, true);

			set_transient('wu-access-token', $response['access_token'], $response['expires_in']);
			wu_save_option('wu-refresh-token', $response['refresh_token']);
			wp_admin_notice(
				__('Successfully connected your site to UltimateMultisite.com.', 'ultimate-multisite'),
				[
					'type'        => 'success',
					'dismissible' => true,
				]
			);
			delete_site_transient('wu-addons-list');
		} else {
			wp_admin_notice(
				__('Failed to authenticate with UltimateMultisite.com.', 'ultimate-multisite'),
				[
					'type'        => 'error',
					'dismissible' => true,
				]
			);
		}
	}

	/**
	 * @param array  $parsed_args option for the request.
	 * @param string $url url requested.
	 *
	 * @return array
	 */
	public function set_update_download_headers(array $parsed_args, string $url = ''): array {
		if (str_starts_with($url, MULTISITE_ULTIMATE_UPDATE_URL) && $this->authorization_header) {
			$parsed_args['headers']['Authorization'] = $this->authorization_header;
		}
		return $parsed_args;
	}

	/**
	 * Checks if the current site has purchased any addon from ultimatemultisite.com.
	 *
	 * Used to determine if the Stripe Connect application fee should be waived.
	 * Results are cached for 24 hours.
	 *
	 * @since 2.4.11
	 * @return bool True if at least one addon has been purchased, false otherwise.
	 */
	public function has_addon_purchase(): bool {

		$cached = get_site_transient('wu_has_addon_purchase');

		if (false !== $cached) {
			return (bool) $cached;
		}

		$access_token = $this->get_access_token();

		if (empty($access_token)) {
			// Not connected to ultimatemultisite.com — cannot verify purchases.
			return false;
		}

		$api_client = new Helpers\WooCommerce_API_Client(MULTISITE_ULTIMATE_UPDATE_URL);
		$addons     = $api_client->get_addons();

		if (is_wp_error($addons) || ! is_array($addons)) {
			// API error — don't cache, let it retry next time.
			return false;
		}

		$has_purchase = false;

		foreach ($addons as $addon) {
			if (! empty($addon['extensions']['wp-update-server-plugin']['download_url'])) {
				$has_purchase = true;

				break;
			}
		}

		set_site_transient('wu_has_addon_purchase', $has_purchase ? '1' : '0', DAY_IN_SECONDS);

		return $has_purchase;
	}

	/**
	 * Clears the addon purchase status cache.
	 *
	 * Should be called when a user connects or disconnects from the store.
	 *
	 * @since 2.4.11
	 * @return void
	 */
	public static function clear_addon_purchase_cache(): void {
		delete_site_transient('wu_has_addon_purchase');
	}

	/**
	 * @return void
	 */
	public function delete_tokens(): void {
		wu_delete_option('wu-refresh-token');
		delete_transient('wu-access-token');
		delete_site_transient('wu-addons-list');
		self::clear_addon_purchase_cache();
	}
}
