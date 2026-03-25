<?php
/**
 * External Cron Manager
 *
 * Handles the client-side integration with the Ultimate Multisite External Cron Service.
 *
 * @package WP_Ultimo
 * @subpackage External_Cron
 * @since 2.3.0
 */

namespace WP_Ultimo\External_Cron;

use WP_Ultimo\Traits\Singleton;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * External Cron Manager class.
 *
 * @since 2.3.0
 */
class External_Cron_Manager {

	use Singleton;

	/**
	 * Service client instance.
	 *
	 * @var External_Cron_Service_Client|null
	 */
	private ?External_Cron_Service_Client $client = null;

	/**
	 * Schedule reporter instance.
	 *
	 * @var External_Cron_Schedule_Reporter|null
	 */
	private ?External_Cron_Schedule_Reporter $reporter = null;

	/**
	 * Registration handler instance.
	 *
	 * @var External_Cron_Registration|null
	 */
	private ?External_Cron_Registration $registration = null;

	/**
	 * Constructor.
	 *
	 * @since 2.3.0
	 */
	public function __construct() {

		$this->client       = new External_Cron_Service_Client();
		$this->reporter     = new External_Cron_Schedule_Reporter($this->client);
		$this->registration = new External_Cron_Registration($this->client);

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 2.3.0
	 */
	private function init_hooks(): void {

		// Register the admin page.
		add_action('wu_admin_pages_init', [$this, 'register_admin_page']);

		// Maybe disable WordPress cron.
		add_action('init', [$this, 'maybe_disable_wp_cron'], 1);

		// Schedule sync action.
		add_action('wu_external_cron_sync_schedules', [$this, 'sync_schedules']);
		$this->schedule_sync();

		// Heartbeat.
		add_action('wu_external_cron_heartbeat', [$this, 'send_heartbeat']);
		$this->schedule_heartbeat();

		// WP-CLI commands.
		if (defined('WP_CLI') && WP_CLI) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Register the admin page.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function register_admin_page(): void {

		new \WP_Ultimo\Admin_Pages\External_Cron_Admin_Page();
	}

	/**
	 * Maybe disable WordPress cron if service is active and site is registered.
	 *
	 * @since 2.3.0
	 */
	public function maybe_disable_wp_cron(): void {

		if ( ! $this->is_service_ready()) {
			return;
		}

		if ( ! defined('DISABLE_WP_CRON')) {
			define('DISABLE_WP_CRON', true);
		}
	}

	/**
	 * Check if external cron service is active.
	 *
	 * @since 2.3.0
	 * @return bool
	 */
	public function is_service_active(): bool {

		return (bool) wu_get_setting('external_cron_enabled', false);
	}

	/**
	 * Check if site is registered with the service.
	 *
	 * @since 2.3.0
	 * @return bool
	 */
	public function is_registered(): bool {

		$site_id = wu_get_setting('external_cron_site_id');

		return ! empty($site_id);
	}

	/**
	 * Check if service is enabled and the site is registered.
	 *
	 * Guards disable/sync/heartbeat paths so WP-Cron is not disabled and
	 * scheduled actions are not sent until the site has a valid site ID.
	 *
	 * @since 2.3.0
	 * @return bool
	 */
	private function is_service_ready(): bool {

		return $this->is_service_active() && $this->is_registered();
	}

	/**
	 * Schedule the sync action.
	 *
	 * @since 2.3.0
	 */
	private function schedule_sync(): void {

		if ( ! $this->is_service_ready()) {
			return;
		}

		if ( ! wu_next_scheduled_action('wu_external_cron_sync_schedules')) {
			wu_schedule_recurring_action(time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, 'wu_external_cron_sync_schedules');
		}
	}

	/**
	 * Schedule the heartbeat action.
	 *
	 * @since 2.3.0
	 */
	private function schedule_heartbeat(): void {

		if ( ! $this->is_service_ready()) {
			return;
		}

		if ( ! wu_next_scheduled_action('wu_external_cron_heartbeat')) {
			wu_schedule_recurring_action(time() + 300, 300, 'wu_external_cron_heartbeat'); // Every 5 minutes.
		}
	}

	/**
	 * Sync schedules with the service.
	 *
	 * @since 2.3.0
	 */
	public function sync_schedules(): void {

		if ( ! $this->is_service_ready()) {
			return;
		}

		$this->reporter->report_all_schedules();

		update_site_option('wu_external_cron_last_sync', time());
	}

	/**
	 * Send heartbeat to the service.
	 *
	 * @since 2.3.0
	 */
	public function send_heartbeat(): void {

		if ( ! $this->is_service_ready()) {
			return;
		}

		$this->client->heartbeat();
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @since 2.3.0
	 */
	private function register_cli_commands(): void {

		\WP_CLI::add_command(
			'wu external-cron',
			function ($args, $_assoc_args) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				$subcommand = $args[0] ?? 'status';

				switch ($subcommand) {
					case 'register':
						$result = $this->registration->register_network();
						if (is_wp_error($result)) {
							\WP_CLI::error($result->get_error_message());
						}
						\WP_CLI::success('Network registered with site ID: ' . $result['site_id']);
						break;

					case 'unregister':
						$result = $this->registration->unregister();
						if (is_wp_error($result)) {
							\WP_CLI::error($result->get_error_message());
						}
						\WP_CLI::success('Network unregistered from external cron service.');
						break;

					case 'sync':
						$this->sync_schedules();
						\WP_CLI::success('Schedules synced successfully.');
						break;

					case 'status':
					default:
						$enabled     = $this->is_service_active();
						$registered  = $this->is_registered();
						$site_id     = wu_get_setting('external_cron_site_id', 'N/A');
						$granularity = wu_get_setting('external_cron_granularity', 'network');

						\WP_CLI::log('External Cron Service Status:');
						\WP_CLI::log('  Enabled: ' . ($enabled ? 'Yes' : 'No'));
						\WP_CLI::log('  Registered: ' . ($registered ? 'Yes' : 'No'));
						\WP_CLI::log('  Site ID: ' . $site_id);
						\WP_CLI::log('  Granularity: ' . $granularity);
						break;
				}
			}
		);
	}

	/**
	 * Get the service client.
	 *
	 * @since 2.3.0
	 * @return External_Cron_Service_Client
	 */
	public function get_client(): External_Cron_Service_Client {

		return $this->client;
	}

	/**
	 * Get the registration handler.
	 *
	 * @since 2.3.0
	 * @return External_Cron_Registration
	 */
	public function get_registration(): External_Cron_Registration {

		return $this->registration;
	}

	/**
	 * Get the schedule reporter.
	 *
	 * @since 2.3.0
	 * @return External_Cron_Schedule_Reporter
	 */
	public function get_reporter(): External_Cron_Schedule_Reporter {

		return $this->reporter;
	}
}
