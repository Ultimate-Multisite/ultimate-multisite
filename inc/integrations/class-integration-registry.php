<?php
/**
 * Integration Registry.
 *
 * Central registry that holds a single provider instance per service.
 * Addons register Capability Modules that attach behavior to a provider.
 *
 * @package WP_Ultimo
 * @subpackage Integrations
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Singleton Integration Registry.
 *
 * @since 2.5.0
 */
class Integration_Registry {

	/**
	 * Singleton instance.
	 *
	 * @since 2.5.0
	 * @var Integration_Registry|null
	 */
	private static ?Integration_Registry $instance = null;

	/**
	 * Registered integrations keyed by ID.
	 *
	 * @since 2.5.0
	 * @var array<string, Integration>
	 */
	private array $integrations = [];

	/**
	 * Registered capability modules keyed by integration_id then capability_id.
	 *
	 * @since 2.5.0
	 * @var array<string, array<string, Capability_Module>>
	 */
	private array $capabilities = [];

	/**
	 * Whether the registry has been resolved.
	 *
	 * @since 2.5.0
	 * @var bool
	 */
	private bool $resolved = false;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.5.0
	 * @return Integration_Registry
	 */
	public static function get_instance(): Integration_Registry {

		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 2.5.0
	 */
	private function __construct() {}

	/**
	 * Initialize the registry and fire registration hooks.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function init(): void {

		add_action('plugins_loaded', [$this, 'fire_registration_hooks'], 9);
		add_action('plugins_loaded', [$this, 'fire_capability_hooks'], 12);
	}

	/**
	 * Fires the integration registration hook.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function fire_registration_hooks(): void {

		// Register core native integrations
		$this->register_core_integrations();

		/**
		 * Fires to allow integrations to register themselves.
		 *
		 * Core registers its integrations here.
		 *
		 * @since 2.5.0
		 *
		 * @param Integration_Registry $registry The registry instance.
		 */
		do_action('wu_register_integrations', $this);
	}

	/**
	 * Registers core native integrations.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private function register_core_integrations(): void {

		$this->register(new Providers\Closte\Closte_Integration());
		$this->register(new Providers\Cloudways\Cloudways_Integration());
		$this->register(new Providers\RunCloud\RunCloud_Integration());
		$this->register(new Providers\CPanel\CPanel_Integration());
		$this->register(new Providers\ServerPilot\ServerPilot_Integration());
		$this->register(new Providers\GridPane\GridPane_Integration());
		$this->register(new Providers\Cloudflare\Cloudflare_Integration());
		$this->register(new Providers\Hestia\Hestia_Integration());
		$this->register(new Providers\Enhance\Enhance_Integration());
		$this->register(new Providers\Plesk\Plesk_Integration());
		$this->register(new Providers\Rocket\Rocket_Integration());
		$this->register(new Providers\WPEngine\WPEngine_Integration());
		$this->register(new Providers\WPMUDEV\WPMUDEV_Integration());
		$this->register(new Providers\BunnyNet\BunnyNet_Integration());
		$this->register(new Providers\LaravelForge\LaravelForge_Integration());
		$this->register(new Providers\Amazon_SES\Amazon_SES_Integration());
		$this->register(new Providers\CyberPanel\CyberPanel_Integration());
	}

	/**
	 * Fires the capability registration hook and resolves.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function fire_capability_hooks(): void {

		// Register core native capabilities
		$this->register_core_capabilities();

		/**
		 * Fires to allow addons to attach capability modules to integrations.
		 *
		 * @since 2.5.0
		 *
		 * @param Integration_Registry $registry The registry instance.
		 */
		do_action('wu_register_capabilities', $this);

		$this->resolve();
	}

	/**
	 * Registers core native capability modules.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private function register_core_capabilities(): void {

		$this->add_capability('closte', new Providers\Closte\Closte_Domain_Mapping());
		$this->add_capability('cloudways', new Providers\Cloudways\Cloudways_Domain_Mapping());
		$this->add_capability('runcloud', new Providers\RunCloud\RunCloud_Domain_Mapping());
		$this->add_capability('cpanel', new Providers\CPanel\CPanel_Domain_Mapping());
		$this->add_capability('serverpilot', new Providers\ServerPilot\ServerPilot_Domain_Mapping());
		$this->add_capability('gridpane', new Providers\GridPane\GridPane_Domain_Mapping());
		$this->add_capability('cloudflare', new Providers\Cloudflare\Cloudflare_Domain_Mapping());
		$this->add_capability('hestia', new Providers\Hestia\Hestia_Domain_Mapping());
		$this->add_capability('enhance', new Providers\Enhance\Enhance_Domain_Mapping());
		$this->add_capability('plesk', new Providers\Plesk\Plesk_Domain_Mapping());
		$this->add_capability('rocket', new Providers\Rocket\Rocket_Domain_Mapping());
		$this->add_capability('wpengine', new Providers\WPEngine\WPEngine_Domain_Mapping());
		$this->add_capability('wpmudev', new Providers\WPMUDEV\WPMUDEV_Domain_Mapping());
		$this->add_capability('bunnynet', new Providers\BunnyNet\BunnyNet_Domain_Mapping());
		$this->add_capability('laravel-forge', new Providers\LaravelForge\LaravelForge_Domain_Mapping());
		$this->add_capability('amazon-ses', new Providers\Amazon_SES\Amazon_SES_Transactional_Email());
		$this->add_capability('cyberpanel', new Providers\CyberPanel\CyberPanel_Domain_Mapping());
	}

	/**
	 * Register an integration.
	 *
	 * @since 2.5.0
	 *
	 * @param Integration $integration The integration to register.
	 * @return void
	 */
	public function register(Integration $integration): void {

		$this->integrations[ $integration->get_id() ] = $integration;
	}

	/**
	 * Get an integration by ID.
	 *
	 * @since 2.5.0
	 *
	 * @param string $id Integration identifier.
	 * @return Integration|null
	 */
	public function get(string $id): ?Integration {

		return $this->integrations[ $id ] ?? null;
	}

	/**
	 * Get all registered integrations.
	 *
	 * @since 2.5.0
	 * @return array<string, Integration>
	 */
	public function get_all(): array {

		return $this->integrations;
	}

	/**
	 * Add a capability module to an integration.
	 *
	 * @since 2.5.0
	 *
	 * @param string            $integration_id The integration to attach to.
	 * @param Capability_Module $module         The capability module.
	 * @return void
	 */
	public function add_capability(string $integration_id, Capability_Module $module): void {

		if ( ! isset($this->capabilities[ $integration_id ])) {
			$this->capabilities[ $integration_id ] = [];
		}

		$this->capabilities[ $integration_id ][ $module->get_capability_id() ] = $module;
	}

	/**
	 * Get all capability modules for an integration.
	 *
	 * @since 2.5.0
	 *
	 * @param string $integration_id Integration identifier.
	 * @return array<string, Capability_Module>
	 */
	public function get_capabilities(string $integration_id): array {

		return $this->capabilities[ $integration_id ] ?? [];
	}

	/**
	 * Check if an integration has a specific capability.
	 *
	 * @since 2.5.0
	 *
	 * @param string $integration_id Integration identifier.
	 * @param string $capability_id  Capability identifier.
	 * @return bool
	 */
	public function has_capability(string $integration_id, string $capability_id): bool {

		return isset($this->capabilities[ $integration_id ][ $capability_id ]);
	}

	/**
	 * Find all integrations that have a given capability.
	 *
	 * @since 2.5.0
	 *
	 * @param string $capability_id Capability identifier.
	 * @return array<string, Integration>
	 */
	public function get_integrations_with_capability(string $capability_id): array {

		$result = [];

		foreach ($this->capabilities as $integration_id => $modules) {
			if (isset($modules[ $capability_id ]) && isset($this->integrations[ $integration_id ])) {
				$result[ $integration_id ] = $this->integrations[ $integration_id ];
			}
		}

		return $result;
	}

	/**
	 * Get a specific capability module from an integration.
	 *
	 * @since 2.5.0
	 *
	 * @param string $integration_id Integration identifier.
	 * @param string $capability_id  Capability identifier.
	 * @return Capability_Module|null
	 */
	public function get_capability(string $integration_id, string $capability_id): ?Capability_Module {

		return $this->capabilities[ $integration_id ][ $capability_id ] ?? null;
	}

	/**
	 * Resolve the registry: set parent integration refs and register hooks.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private function resolve(): void {

		if ($this->resolved) {
			return;
		}

		foreach ($this->capabilities as $integration_id => $modules) {
			$integration = $this->get($integration_id);

			if ( ! $integration) {
				continue;
			}

			foreach ($modules as $module) {
				$module->set_integration($integration);

				if ($integration->is_enabled() && $integration->is_setup()) {
					$module->register_hooks();
				}
			}
		}

		$this->resolved = true;

		add_action('wu_settings_integrations', [$this, 'register_settings_fields']);
		add_action('init', [$this, 'register_admin_notices']);
	}

	/**
	 * Registers settings fields for all integrations on the integrations tab.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_settings_fields(): void {

		foreach ($this->integrations as $integration) {
			$slug       = $integration->get_id();
			$field_slug = str_replace('-', '_', $slug);

			$html = $integration->is_enabled()
				? sprintf('<div class="wu-self-center wu-text-green-800 wu-mr-4"><span class="dashicons-wu-check"></span> %s</div>', __('Activated', 'ultimate-multisite'))
				: '';

			$url = wu_network_admin_url(
				'wp-ultimo-hosting-integration-wizard',
				[
					'integration' => $slug,
				]
			);

			$html .= sprintf('<a href="%s" class="button-primary">%s</a>', $url, __('Configuration', 'ultimate-multisite'));

			// translators: %s is the name of a host provider (e.g. Cloudways, WPMUDev, Closte...).
			$title = sprintf(__('%s Integration', 'ultimate-multisite'), $integration->get_title());

			$title .= sprintf(
				"<span class='wu-normal-case wu-block wu-text-xs wu-font-normal wu-mt-1'>%s</span>",
				__('Go to the setup wizard to setup this integration.', 'ultimate-multisite')
			);

			wu_register_settings_field(
				'integrations',
				"integration_{$field_slug}",
				[
					'type'  => 'note',
					'title' => $title,
					'desc'  => $html,
				]
			);
		}
	}

	/**
	 * Registers admin notices for detected-but-not-enabled and enabled-but-not-setup integrations.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_admin_notices(): void {

		if (\WP_Ultimo()->is_loaded() === false) {
			return;
		}

		foreach ($this->integrations as $integration) {
			$slug = $integration->get_id();

			if ($integration->detect() && ! $integration->is_enabled()) {
				// translators: %1$s will be replaced with the integration title. E.g. RunCloud
				$message = sprintf(__('It looks like you are using %1$s as your hosting provider, yet the %1$s integration module is not active. In order for the domain mapping integration to work with %1$s, you might want to activate that module.', 'ultimate-multisite'), $integration->get_title());

				$actions = [
					'activate' => [
						// translators: %s is the integration name.
						'title' => sprintf(__('Activate %s', 'ultimate-multisite'), $integration->get_title()),
						'url'   => wu_network_admin_url(
							'wp-ultimo-hosting-integration-wizard',
							[
								'integration' => $slug,
							]
						),
					],
				];

				\WP_Ultimo()->notices->add($message, 'info', 'network-admin', "should-enable-{$slug}-integration", $actions);
			} elseif ($integration->is_enabled() && ! $integration->is_setup()) {
				// translators: %s is the integration name.
				$message = sprintf(__('The %s integration module is active but not properly configured. Please complete the setup.', 'ultimate-multisite'), $integration->get_title());

				$actions = [
					'setup' => [
						// translators: %s is the integration name.
						'title' => sprintf(__('Setup %s', 'ultimate-multisite'), $integration->get_title()),
						'url'   => wu_network_admin_url(
							'wp-ultimo-hosting-integration-wizard',
							[
								'integration' => $slug,
							]
						),
					],
				];

				\WP_Ultimo()->notices->add($message, 'warning', 'network-admin', "should-setup-{$slug}-integration", $actions);
			}
		}
	}
}
