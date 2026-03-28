<?php
/**
 * Base class that new host providers integrations must extend.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Host_Providers
 * @since 2.0.0
 */

namespace WP_Ultimo\Integrations\Host_Providers;

use WP_Ultimo\Helpers\WP_Config;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * This base class should be extended to implement new host integrations for SSL and domains.
 *
 * Implements DNS_Provider_Interface to provide default DNS management functionality.
 * Providers that support DNS should add 'dns-management' to their $supports array
 * and override the DNS methods.
 */
abstract class Base_Host_Provider implements DNS_Provider_Interface {

	/**
	 * Holds the id of the integration.
	 *
	 * @var string
	 * @since 2.0.0
	 */
	protected $id;

	/**
	 * Keeps the title of the integration.
	 *
	 * @var string
	 * @since 2.0.0
	 */
	protected $title;

	/**
	 * Link to the tutorial teaching how to make this integration work.
	 *
	 * @var string
	 * @since 2.0.0
	 */
	protected $tutorial_link = '';

	/**
	 * Array containing the features this integration supports.
	 *
	 * @var array
	 * @since 2.0.0
	 */
	protected $supports = [];

	/**
	 * Constants that need to be present on wp-config.php for this integration to work.
	 * The values can be strings or array with constants to check for a match.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $constants = [];

	/**
	 * Constants that are optional on wp-config.php.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $optional_constants = [];

	/**
	 * Runs on singleton instantiation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		if ($this->detect() && ! $this->is_enabled()) {
			/*
			 * Adds an admin notice telling the admin that they should probably enable this integration.
			 */
			$this->alert_provider_detected();
		}

		/*
		 * Only add hooks if the integration is enabled and correctly setup.
		 */
		if ($this->is_enabled()) {
			/*
			 * Checks if everything was correctly setup.
			 */
			if ( ! $this->is_setup()) {
				/*
				 * Adds an admin notice telling the admin that the provider is not correctly setup.
				 */
				if (did_action('init')) {
					$this->alert_provider_not_setup();
				} else {
					// avoid text domain loaded at the wrong time.
					add_action('init', [$this, 'alert_provider_not_setup']);
				}
			} else {
				/*
				 * Load the dependencies.
				 */
				$this->load_dependencies();

				/*
				 * Initialize the hooks.
				 */
				$this->register_hooks();
			}
		}

		add_filter('wu_domain_manager_get_integrations', [$this, 'self_register']);

		add_action('init', [$this, 'add_to_integration_list']);
	}

	/**
	 * Let the class register itself on the manager, allowing us to access the integrations later via the slug.
	 *
	 * @since 2.0.0
	 *
	 * @param array $integrations List of integrations added so far.
	 * @return array
	 */
	final public function self_register($integrations) {

		$integrations[ $this->get_id() ] = static::class;

		return $integrations;
	}

	/**
	 * Get the list of enabled host integrations.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_enabled_list() {

		return get_network_option(null, 'wu_host_integrations_enabled', []);
	}

	/**
	 * Check if this integration is enabled.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	final public function is_enabled() {

		$list = $this->get_enabled_list();

		return wu_get_isset($list, $this->get_id(), false);
	}

	/**
	 * Enables this integration.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function enable() {

		$list = $this->get_enabled_list();

		$list[ $this->get_id() ] = true;

		return update_network_option(null, 'wu_host_integrations_enabled', $list);
	}

	/**
	 * Disables this integration.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function disable() {

		$list = $this->get_enabled_list();

		$list[ $this->get_id() ] = false;

		return update_network_option(null, 'wu_host_integrations_enabled', $list);
	}

	/**
	 * Adds the host to the list of integrations.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_to_integration_list(): void {

		$slug = $this->get_id();

		$html = $this->is_enabled() ? sprintf('<div class="wu-self-center wu-text-green-800 wu-mr-4"><span class="dashicons-wu-check"></span> %s</div>', __('Activated', 'ultimate-multisite')) : '';

		$url = wu_network_admin_url(
			'wp-ultimo-hosting-integration-wizard',
			[
				'integration' => $slug,
			]
		);

		$html .= sprintf('<a href="%s" class="button-primary">%s</a>', $url, __('Configuration', 'ultimate-multisite'));

		// translators: %s is the name of a host provider (e.g. Cloudways, WPMUDev, Closte...).
		$title = sprintf(__('%s Integration', 'ultimate-multisite'), $this->get_title());

		$title .= sprintf(
			"<span class='wu-normal-case wu-block wu-text-xs wu-font-normal wu-mt-1'>%s</span>",
			__('Go to the setup wizard to setup this integration.', 'ultimate-multisite')
		);

		wu_register_settings_field(
			'integrations',
			'integration_' . str_replace('-', '_', $slug),
			[
				'type'  => 'note',
				'title' => $title,
				'desc'  => $html,
			]
		);
	}

	/**
	 * Adds an admin notice telling the admin that they should probably enable this integration.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function alert_provider_detected(): void {

		if (WP_Ultimo()->is_loaded() === false) {
			return;
		}

		// translators: %1$s will be replaced with the integration title. E.g. RunCloud
		$message = sprintf(__('It looks like you are using %1$s as your hosting provider, yet the %1$s integration module is not active. In order for the domain mapping integration to work with %1$s, you might want to activate that module.', 'ultimate-multisite'), $this->get_title());

		$slug = $this->get_id();

		$actions = [
			'activate' => [
				// translators: %s is the integration name.
				'title' => sprintf(__('Activate %s', 'ultimate-multisite'), $this->get_title()),
				'url'   => wu_network_admin_url(
					'wp-ultimo-hosting-integration-wizard',
					[
						'integration' => $slug,
					]
				),
			],
		];

		WP_Ultimo()->notices->add($message, 'info', 'network-admin', "should-enable-{$slug}-integration", $actions);
	}

	/**
	 * Adds an admin notice telling the admin that the provider is not correctly setup.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function alert_provider_not_setup(): void {

		if (WP_Ultimo()->is_loaded() === false) {
			return;
		}

		// translators: %1$s will be replaced with the integration title. E.g. RunCloud.
		$message = sprintf(__('It looks like you are using %1$s as your hosting provider, yet the %1$s integration module was not properly setup. In order for the domain mapping integration to work with %1$s, you need to configure that module.', 'ultimate-multisite'), $this->get_title());

		$slug = $this->get_id();

		$actions = [
			'activate' => [
				// translators: %s is the integration name
				'title' => sprintf(__('Setup %s', 'ultimate-multisite'), $this->get_title()),
				'url'   => wu_network_admin_url(
					'wp-ultimo-hosting-integration-wizard',
					[
						'integration' => $slug,
						'tab'         => 'config',
					]
				),
			],
		];

		WP_Ultimo()->notices->add($message, 'warning', 'network-admin', "should-setup-{$slug}-integration", $actions);
	}

	/**
	 * Get Fields for the integration.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_fields() {

		return [];
	}

	/**
	 * Returns the integration id.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_id() {

		return $this->id;
	}

	/**
	 * Returns the integration title.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return $this->title;
	}

	/**
	 * Checks if a feature is supported, like auto-ssl for example.
	 *
	 * @since 2.0.0
	 * @param string $feature Feature to check.
	 * @return boolean
	 */
	public function supports($feature) {

		return apply_filters('wu_hosting_support_supports', in_array($feature, $this->supports, true), $this);
	}

	/**
	 * Initializes the hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_hooks(): void {
		/*
		 * Hooks the event that is triggered when a new domain is added.
		 */
		add_action('wu_add_domain', [$this, 'on_add_domain'], 10, 2);

		/*
		 * Hooks the event that is triggered when a domain is deleted.
		 */
		add_action('wu_remove_domain', [$this, 'on_remove_domain'], 10, 2);

		/*
		 * Hooks the event that is triggered when a sub-domain is added.
		 */
		add_action('wu_add_subdomain', [$this, 'on_add_subdomain'], 10, 2);

		/*
		 * Hooks the event that is triggered when a sub-domain is added.
		 */
		add_action('wu_remove_subdomain', [$this, 'on_remove_subdomain'], 10, 2);

		/*
		 * Add additional hooks.
		 */
		$this->additional_hooks();
	}

	/**
	 * Lets integrations add additional hooks.
	 *
	 * @since 2.0.7
	 * @return void
	 */
	public function additional_hooks() {}

	/**
	 * Can be used to load dependencies.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function load_dependencies() {}

	/**
	 * Picks up on tips that a given host provider is being used.
	 *
	 * We use this to suggest that the user should activate an integration module.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	abstract public function detect();

	/**
	 * Checks if the integration is correctly setup after enabled.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_setup() {

		$all_set = true;

		foreach ($this->constants as $constant) {
			$constants = is_array($constant) ? $constant : [$constant];

			$current = false;

			foreach ($constants as $constant) {
				if (defined($constant) && constant($constant)) {
					$current = true;

					break;
				}
			}

			$all_set = $all_set && $current;

			/*
			 * If any constant fail, bail.
			 */
			if (false === $all_set) {
				return false;
			}
		}

		return $all_set;
	}

	/**
	 * Returns a list of missing constants configured on wp-config.php
	 *
	 * @since 2.0.14
	 * @return array
	 */
	public function get_missing_constants() {

		$missing_constants = [];

		foreach ($this->constants as $constant) {
			$constants = is_array($constant) ? $constant : [$constant];

			$current = false;

			foreach ($constants as $constant) {
				if (defined($constant) && constant($constant)) {
					$current = true;

					break;
				}
			}

			$missing_constants = $current ? $missing_constants : array_merge($missing_constants, $constants);
		}

		return $missing_constants;
	}

	/**
	 * Returns a list of all constants, optional or not.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_all_constants() {

		$constants = [];

		foreach ($this->constants as $constant) {
			$current = is_array($constant) ? $constant : [$constant];

			$constants = array_merge($constants, $current);
		}

		return array_merge($constants, $this->optional_constants);
	}

	/**
	 * Adds the constants with their respective values into the wp-config.php.
	 *
	 * @since 2.0.0
	 *
	 * @param array $constant_values Key => Value of the necessary constants.
	 * @return void
	 */
	public function setup_constants($constant_values): void {
		/*
		 * Important: This step is crucial, as it makes sure we clean up undesired constants.
		 * Removing this can allow insertion of arbitrary constants onto the wp-config.pp file
		 * and that should NEVER happen.
		 *
		 * Note that this is also performed on the get_constants_string.
		 */
		$values = shortcode_atts(array_flip($this->get_all_constants()), $constant_values);

		foreach ($values as $constant => $value) {
			WP_Config::get_instance()->inject_wp_config_constant($constant, $value);
		}
	}

	/**
	 * Generates a define string for manual insertion on-to wp-config.php.
	 *
	 * This is useful when the user is not willing to let Ultimate Multisite inject the code,
	 * Or when the wp-config.php is not writable.
	 *
	 * @since 2.0.0
	 *
	 * @param array $constant_values Key => Value of the necessary constants.
	 * @return string
	 */
	public function get_constants_string($constant_values) {
		/*
		 * Initializes the array with an opening comment.
		 */
		$content = [
			sprintf('// Ultimate Multisite - Domain Mapping - %s', $this->get_title()),
		];

		/*
		 * Important: This step is crucial, as it makes sure we clean up undesired constants.
		 * Removing this can allow insertion of arbitrary constants onto the wp-config.php file
		 * and that should NEVER happen.
		 */
		$constant_values = shortcode_atts(array_flip($this->get_all_constants()), $constant_values);

		/*
		 * Adds the constants, one by one.
		 */
		foreach ($constant_values as $constant => $value) {
			$content[] = sprintf("define( '%s', '%s' );", $constant, $value);
		}

		/*
		 * Adds the final line.
		 */
		$content[] = sprintf('// Ultimate Multisite - Domain Mapping - %s - End', $this->get_title());

		return implode(PHP_EOL, $content);
	}

	/**
	 * Returns the explainer lines for the integration.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_explainer_lines() {

		$explainer_lines = [
			'will'     => [
				// translators: %s is the name of the integration e.g. RunCloud
				'send_domains' => sprintf(__('Send API calls to %s servers with domain names added to this network', 'ultimate-multisite'), $this->get_title()),
			],
			'will_not' => [],
		];

		if ($this->supports('autossl')) {

			// translators: %s is the name of the integration e.g. RunCloud
			$explainer_lines['will'][] = sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added.', 'ultimate-multisite'), $this->get_title());
		} else {

			// translators: %s is the name of the integration e.g. RunCloud
			$explainer_lines['will_not'][] = sprintf(__('Fetch and install a SSL certificate on %s platform after the domain is added. This needs to be done manually.', 'ultimate-multisite'), $this->get_title());
		}

		return $explainer_lines;
	}

	/**
	 * This method gets called when a new domain is mapped.
	 *
	 * @since 2.0.0
	 * @param string $domain The domain name being mapped.
	 * @param int    $site_id ID of the site that is receiving that mapping.
	 * @return void
	 */
	abstract public function on_add_domain($domain, $site_id);

	/**
	 * This method gets called when a mapped domain is removed.
	 *
	 * @since 2.0.0
	 * @param string $domain The domain name being removed.
	 * @param int    $site_id ID of the site that is receiving that mapping.
	 * @return void
	 */
	abstract public function on_remove_domain($domain, $site_id);

	/**
	 * This method gets called when a new subdomain is being added.
	 *
	 * This happens every time a new site is added to a network running on subdomain mode.
	 *
	 * @since 2.0.0
	 * @param string $subdomain The subdomain being added to the network.
	 * @param int    $site_id ID of the site that is receiving that mapping.
	 * @return void
	 */
	abstract public function on_add_subdomain($subdomain, $site_id);

	/**
	 * This method gets called when a new subdomain is being removed.
	 *
	 * This happens every time a new site is removed to a network running on subdomain mode.
	 *
	 * @since 2.0.0
	 * @param string $subdomain The subdomain being removed to the network.
	 * @param int    $site_id ID of the site that is receiving that mapping.
	 * @return void
	 */
	abstract public function on_remove_subdomain($subdomain, $site_id);

	/**
	 * Tests the connection with the API.
	 *
	 * Needs to be implemented by integrations.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function test_connection(): void {

		wp_send_json_success([]);
	}

	/**
	 * Returns the description of this integration.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('No description provided.', 'ultimate-multisite');
	}

	/**
	 * Returns the logo for the integration.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_logo() {

		return '';
	}

	/**
	 * Check if this provider supports DNS record management.
	 *
	 * @since 2.3.0
	 * @return bool
	 */
	public function supports_dns_management(): bool {

		return $this->supports('dns-management');
	}

	/**
	 * Get DNS records for a domain.
	 *
	 * Providers that support DNS management should override this method.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain The domain to query.
	 * @return array|WP_Error Array of DNS_Record objects or WP_Error.
	 */
	public function get_dns_records(string $domain) {

		return new \WP_Error(
			'dns-not-supported',
			__('DNS record management is not supported by this hosting provider.', 'ultimate-multisite')
		);
	}

	/**
	 * Create a DNS record for a domain.
	 *
	 * Providers that support DNS management should override this method.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain The domain.
	 * @param array  $record Record data (type, name, content, ttl, priority).
	 * @return array|WP_Error Created record data or WP_Error.
	 */
	public function create_dns_record(string $domain, array $record) {

		return new \WP_Error(
			'dns-not-supported',
			__('DNS record management is not supported by this hosting provider.', 'ultimate-multisite')
		);
	}

	/**
	 * Update a DNS record for a domain.
	 *
	 * Providers that support DNS management should override this method.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain    The domain.
	 * @param string $record_id The record identifier.
	 * @param array  $record    Updated record data.
	 * @return array|WP_Error Updated record data or WP_Error.
	 */
	public function update_dns_record(string $domain, string $record_id, array $record) {

		return new \WP_Error(
			'dns-not-supported',
			__('DNS record management is not supported by this hosting provider.', 'ultimate-multisite')
		);
	}

	/**
	 * Delete a DNS record for a domain.
	 *
	 * Providers that support DNS management should override this method.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain    The domain.
	 * @param string $record_id The record identifier.
	 * @return bool|WP_Error True on success or WP_Error.
	 */
	public function delete_dns_record(string $domain, string $record_id) {

		return new \WP_Error(
			'dns-not-supported',
			__('DNS record management is not supported by this hosting provider.', 'ultimate-multisite')
		);
	}

	/**
	 * Get the DNS record types supported by this provider.
	 *
	 * @since 2.3.0
	 *
	 * @return array Array of supported record types.
	 */
	public function get_supported_record_types(): array {

		return ['A', 'AAAA', 'CNAME', 'MX', 'TXT'];
	}

	/**
	 * Get the zone/domain identifier for DNS operations.
	 *
	 * Some providers require a zone ID rather than domain name.
	 * Override this method to implement zone ID lookup.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain The domain name.
	 * @return string|null Zone identifier or null if not found.
	 */
	public function get_zone_id(string $domain): ?string {

		return null;
	}

	/**
	 * Check if DNS management is enabled for this provider.
	 *
	 * This checks both support and whether the admin has enabled it.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	public function is_dns_enabled(): bool {

		if (! $this->supports_dns_management()) {
			return false;
		}

		// Check if DNS is enabled for this specific provider
		$dns_enabled = get_network_option(null, 'wu_dns_integrations_enabled', []);

		return ! empty($dns_enabled[ $this->get_id() ]);
	}

	/**
	 * Enable DNS management for this provider.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	public function enable_dns(): bool {

		if (! $this->supports_dns_management()) {
			return false;
		}

		$dns_enabled                    = get_network_option(null, 'wu_dns_integrations_enabled', []);
		$dns_enabled[ $this->get_id() ] = true;

		return update_network_option(null, 'wu_dns_integrations_enabled', $dns_enabled);
	}

	/**
	 * Disable DNS management for this provider.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	public function disable_dns(): bool {

		$dns_enabled                    = get_network_option(null, 'wu_dns_integrations_enabled', []);
		$dns_enabled[ $this->get_id() ] = false;

		return update_network_option(null, 'wu_dns_integrations_enabled', $dns_enabled);
	}

	/**
	 * Extract the zone name (root domain) from a domain.
	 *
	 * Handles common multi-part TLDs (e.g. .co.uk, .com.au). For providers that
	 * need a more precise zone lookup (e.g. Cloudflare's iterative API search),
	 * override this method in the concrete provider class.
	 *
	 * @since 2.3.0
	 *
	 * @param string $domain The domain name.
	 * @return string The zone name (root domain).
	 */
	protected function extract_zone_name(string $domain): string {

		$parts = explode('.', $domain);

		// Known multi-part TLDs
		$multi_tlds = ['.co.uk', '.com.au', '.co.nz', '.com.br', '.co.in', '.org.uk', '.net.au'];

		foreach ($multi_tlds as $tld) {
			if (str_ends_with($domain, $tld)) {
				return implode('.', array_slice($parts, -3));
			}
		}

		// Return last 2 parts for standard TLD
		if (count($parts) >= 2) {
			return implode('.', array_slice($parts, -2));
		}

		return $domain;
	}
}
