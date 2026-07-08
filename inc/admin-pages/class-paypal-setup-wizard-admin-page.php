<?php
/**
 * PayPal Setup Wizard Admin Page.
 *
 * Guided manual-credentials walkthrough that mirrors the host-integration
 * wizard pattern. Used when WU_PAYPAL_OAUTH_ENABLED is false (the shipped
 * default) so merchants can set up the PayPal REST gateway by creating a
 * REST app at developer.paypal.com and pasting the Client ID and Secret.
 *
 * The wizard auto-validates the credentials by requesting an OAuth access
 * token from PayPal, then installs the webhook automatically on success.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.6.0
 */

namespace WP_Ultimo\Admin_Pages;

use Psr\Log\LogLevel;
use WP_Ultimo\Gateways\PayPal_REST_Gateway;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * PayPal Setup Wizard Admin Page.
 *
 * @since 2.6.0
 */
class PayPal_Setup_Wizard_Admin_Page extends Wizard_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @since 2.6.0
	 * @var string
	 */
	protected $id = 'wp-ultimo-paypal-setup-wizard';

	/**
	 * Submenu type.
	 *
	 * @since 2.6.0
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * No top-level parent (rendered as a hidden settings child page).
	 *
	 * @since 2.6.0
	 * @var string
	 */
	protected $parent = 'none';

	/**
	 * Highlight the Settings menu while the wizard is active.
	 *
	 * @since 2.6.0
	 * @var string
	 */
	protected $highlight_menu_slug = 'wp-ultimo-settings';

	/**
	 * Network admin only — gateway setup is a network-level operation.
	 *
	 * @since 2.6.0
	 * @var array
	 */
	protected $supported_panels = [
		'network_admin_menu' => 'manage_network',
	];

	/**
	 * Sandbox vs live mode for the wizard session.
	 *
	 * Resolved in page_loaded() from the persisted `paypal_rest_sandbox_mode`
	 * setting. Sandbox is the safer default for a fresh setup.
	 *
	 * @since 2.6.0
	 * @var bool
	 */
	protected $sandbox_mode = true;

	/**
	 * Register early-load hooks.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function page_loaded(): void {

		$this->sandbox_mode = (bool) (int) wu_get_setting('paypal_rest_sandbox_mode', 1);

		parent::page_loaded();
	}

	/**
	 * Wizard title.
	 *
	 * @since 2.6.0
	 * @return string
	 */
	public function get_title(): string {

		return __('PayPal Setup', 'ultimate-multisite');
	}

	/**
	 * Menu label (only shown on the network sidebar when discoverable).
	 *
	 * @since 2.6.0
	 * @return string
	 */
	public function get_menu_title() {

		return __('PayPal Setup', 'ultimate-multisite');
	}

	/**
	 * Wizard sections.
	 *
	 * @since 2.6.0
	 * @return array
	 */
	public function get_sections() {

		return [
			'welcome'      => [
				'title'   => __('Welcome', 'ultimate-multisite'),
				'view'    => [$this, 'section_welcome'],
				'handler' => [$this, 'handle_welcome'],
			],
			'instructions' => [
				'title' => __('Get Credentials', 'ultimate-multisite'),
				'view'  => [$this, 'section_instructions'],
			],
			'configure'    => [
				'title'   => __('Configure', 'ultimate-multisite'),
				'view'    => [$this, 'section_configure'],
				'handler' => [$this, 'handle_configure'],
			],
			'test'         => [
				'title' => __('Test Connection', 'ultimate-multisite'),
				'view'  => [$this, 'section_test'],
			],
			'done'         => [
				'title' => __('Done', 'ultimate-multisite'),
				'view'  => [$this, 'section_done'],
			],
		];
	}

	/**
	 * Welcome section.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function section_welcome(): void {

		wu_get_template(
			'wizards/paypal-setup/welcome',
			[
				'page'         => $this,
				'sandbox_mode' => $this->sandbox_mode,
			]
		);
	}

	/**
	 * Welcome handler — persists the sandbox/live toggle and advances.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function handle_welcome(): void {

		check_admin_referer('saving_welcome', '_wpultimo_nonce');

		$sandbox = isset($_POST['paypal_sandbox_mode']) ? 1 : 0;

		wu_save_setting('paypal_rest_sandbox_mode', $sandbox);

		$this->sandbox_mode = (bool) $sandbox;

		wp_safe_redirect($this->get_next_section_link());

		exit;
	}

	/**
	 * Instructions section — guides the user through creating a REST app.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function section_instructions(): void {

		wu_get_template(
			'wizards/paypal-setup/instructions',
			[
				'page'          => $this,
				'sandbox_mode'  => $this->sandbox_mode,
				'developer_url' => $this->sandbox_mode
					? 'https://developer.paypal.com/dashboard/applications/sandbox'
					: 'https://developer.paypal.com/dashboard/applications/live',
			]
		);

		$this->render_submit_box();
	}

	/**
	 * Configure section — paste credentials into the form.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function section_configure(): void {

		$mode_prefix = $this->sandbox_mode ? 'sandbox' : 'live';

		$client_id     = (string) wu_get_setting("paypal_rest_{$mode_prefix}_client_id", '');
		$client_secret = (string) wu_get_setting("paypal_rest_{$mode_prefix}_client_secret", '');

		wu_get_template(
			'wizards/paypal-setup/configure',
			[
				'page'          => $this,
				'sandbox_mode'  => $this->sandbox_mode,
				'mode_label'    => $this->sandbox_mode
					? __('Sandbox', 'ultimate-multisite')
					: __('Live', 'ultimate-multisite'),
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
			]
		);
	}

	/**
	 * Configure handler — sanitises and saves the pasted credentials.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function handle_configure(): void {

		check_admin_referer('saving_configure', '_wpultimo_nonce');

		if ( ! current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to do this.', 'ultimate-multisite'));
		}

		$mode_prefix   = $this->sandbox_mode ? 'sandbox' : 'live';
		$client_id     = isset($_POST['paypal_client_id']) ? sanitize_text_field(wp_unslash($_POST['paypal_client_id'])) : '';
		$client_secret = isset($_POST['paypal_client_secret']) ? sanitize_text_field(wp_unslash($_POST['paypal_client_secret'])) : '';

		if (empty($client_id) || empty($client_secret)) {
			set_site_transient(
				'wu_paypal_wizard_notice',
				[
					'type'    => 'error',
					'message' => __('Please paste both the Client ID and the Client Secret before continuing.', 'ultimate-multisite'),
				],
				60
			);

			wp_safe_redirect(remove_query_arg(['_wpultimo_nonce', 'saving_configure']));

			exit;
		}

		wu_save_setting("paypal_rest_{$mode_prefix}_client_id", $client_id);
		wu_save_setting("paypal_rest_{$mode_prefix}_client_secret", $client_secret);

		// Make sure paypal-rest is in the active gateways list.
		$active_gateways = (array) wu_get_setting('active_gateways', []);

		if ( ! in_array('paypal-rest', $active_gateways, true)) {
			$active_gateways[] = 'paypal-rest';
			wu_save_setting('active_gateways', $active_gateways);
		}

		wp_safe_redirect($this->get_next_section_link());

		exit;
	}

	/**
	 * Test section — Vue widget that pings the test_credentials AJAX endpoint.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function section_test(): void {

		wp_enqueue_script('wu-vue');

		wp_enqueue_script(
			'wu-paypal-setup-wizard-test',
			wu_get_asset('paypal-setup-wizard.js', 'js'),
			[
				'jquery',
				'wu-vue',
			],
			wu_get_version(),
			true
		);

		wp_localize_script(
			'wu-paypal-setup-wizard-test',
			'wu_paypal_setup_wizard',
			[
				'ajax_url'        => admin_url('admin-ajax.php'),
				'nonce'           => wp_create_nonce('wu_paypal_setup_wizard'),
				'sandbox_mode'    => $this->sandbox_mode ? 1 : 0,
				'waiting_message' => __('Verifying your credentials with PayPal…', 'ultimate-multisite'),
				'success_message' => __('Your credentials were accepted by PayPal.', 'ultimate-multisite'),
				'webhook_message' => __('We installed the webhook automatically.', 'ultimate-multisite'),
				'error_message'   => __('PayPal rejected the credentials. Double-check the Client ID and Secret on the previous step.', 'ultimate-multisite'),
			]
		);

		wu_get_template(
			'wizards/paypal-setup/test',
			[
				'page'         => $this,
				'sandbox_mode' => $this->sandbox_mode,
			]
		);
	}

	/**
	 * Done section — confirmation and link back to settings.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function section_done(): void {

		wu_get_template(
			'wizards/paypal-setup/done',
			[
				'page'         => $this,
				'sandbox_mode' => $this->sandbox_mode,
			]
		);
	}

	/**
	 * Register the AJAX handlers used by the test step.
	 *
	 * Called from class-wp-ultimo.php during the admin-pages registration
	 * pass so that the AJAX endpoints are registered even when the wizard
	 * page itself isn't currently rendering.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function register_ajax_handlers(): void {

		add_action('wp_ajax_wu_paypal_setup_wizard_test', [$this, 'ajax_test_credentials']);
	}

	/**
	 * AJAX handler that validates the saved credentials with PayPal.
	 *
	 * Calls the PayPal REST `/v1/oauth2/token` endpoint with client_credentials
	 * grant type to verify the saved Client ID and Secret. On success, installs
	 * the webhook so the merchant doesn't have to do it manually.
	 *
	 * @since 2.6.0
	 * @return void
	 */
	public function ajax_test_credentials(): void {

		check_ajax_referer('wu_paypal_setup_wizard', 'nonce');

		if ( ! current_user_can('manage_network_options')) {
			wp_send_json_error(
				[
					'message' => __('You do not have permission to do this.', 'ultimate-multisite'),
				]
			);
		}

		$sandbox     = isset($_POST['sandbox_mode']) ? (bool) (int) $_POST['sandbox_mode'] : true;
		$mode_prefix = $sandbox ? 'sandbox' : 'live';

		$client_id     = (string) wu_get_setting("paypal_rest_{$mode_prefix}_client_id", '');
		$client_secret = (string) wu_get_setting("paypal_rest_{$mode_prefix}_client_secret", '');

		if (empty($client_id) || empty($client_secret)) {
			wp_send_json_error(
				[
					'message' => __('Credentials are missing. Please return to the previous step.', 'ultimate-multisite'),
				]
			);
		}

		$api_base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for PayPal Basic auth.
		$auth_header = 'Basic ' . base64_encode($client_id . ':' . $client_secret);

		$response = wp_remote_post(
			$api_base . '/v1/oauth2/token',
			[
				'headers' => [
					'Authorization' => $auth_header,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				],
				'body'    => 'grant_type=client_credentials',
				'timeout' => 30,
			]
		);

		if (is_wp_error($response)) {
			wu_log_add('paypal', 'Wizard credentials test failed (transport): ' . $response->get_error_message(), LogLevel::ERROR);

			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: transport-level error message */
						__('Could not reach PayPal: %s', 'ultimate-multisite'),
						$response->get_error_message()
					),
				]
			);
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (200 !== $code || empty($body['access_token'])) {
			$paypal_message = '';

			if (is_array($body)) {
				$paypal_message = (string) (
					$body['error_description']
					?? $body['error']
					?? ''
				);
			}

			wu_log_add(
				'paypal',
				sprintf('Wizard credentials test rejected (HTTP %d): %s', $code, $paypal_message),
				LogLevel::ERROR
			);

			wp_send_json_error(
				[
					'message' => $paypal_message
						? sprintf(
							/* translators: %s: PayPal error message */
							__('PayPal rejected the credentials: %s', 'ultimate-multisite'),
							$paypal_message
						)
						: __('PayPal rejected the credentials. Please double-check the Client ID and Secret.', 'ultimate-multisite'),
				]
			);
		}

		// Credentials valid — try to install the webhook automatically.
		$webhook_status  = 'not_attempted';
		$webhook_message = '';
		$gateway         = wu_get_gateway('paypal-rest');

		if ($gateway instanceof PayPal_REST_Gateway) {
			$gateway->set_test_mode($sandbox);

			$webhook_result = $gateway->install_webhook();

			if (true === $webhook_result) {
				$webhook_status  = 'installed';
				$webhook_message = __('Webhook installed.', 'ultimate-multisite');
			} elseif (is_wp_error($webhook_result)) {
				$webhook_status  = 'failed';
				$webhook_message = $webhook_result->get_error_message();

				wu_log_add(
					'paypal',
					'Wizard webhook auto-install failed: ' . $webhook_message,
					LogLevel::WARNING
				);
			}
		}

		wp_send_json_success(
			[
				'message'         => __('Credentials accepted by PayPal.', 'ultimate-multisite'),
				'webhook_status'  => $webhook_status,
				'webhook_message' => $webhook_message,
				'next_url'        => $this->get_next_section_link(),
			]
		);
	}
}
