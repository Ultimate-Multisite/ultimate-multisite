<?php
/**
 * Domain Mapping Manager
 *
 * Handles processes related to domain mappings,
 * things like adding hooks to add asynchronous checking of DNS settings and SSL certs and more.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Domain_Manager
 * @since 2.0.0
 */

namespace WP_Ultimo\Managers;

use Psr\Log\LogLevel;
use WP_Ultimo\Database\Domains\Domain_Stage;
use WP_Ultimo\Domain_Mapping\Helper;
use WP_Ultimo\Models\Domain;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles processes related to domain mappings.
 *
 * @since 2.0.0
 */
class Domain_Manager extends Base_Manager {

	use \WP_Ultimo\Apis\Rest_Api;
	use \WP_Ultimo\Apis\WP_CLI;
	use \WP_Ultimo\Apis\MCP_Abilities;
	use \WP_Ultimo\Apis\Command_Palette;
	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The manager slug.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $slug = 'domain';

	/**
	 * The model class associated to this manager.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $model_class = \WP_Ultimo\Models\Domain::class;

	/**
	 * Holds a list of the current integrations for domain mapping.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $integrations = [];

	/**
	 * Checks if this is a main domain or a subdomain.
	 *
	 * @param string $domain the domain.
	 *
	 * @return bool
	 */
	public static function is_main_domain(string $domain) {
		// Normalize: lowercase, trim spaces, drop trailing dot
		$domain = strtolower(trim(rtrim($domain, '.')));
		// Check if this is a main domain (no subdomain parts)
		// A main domain has only 2 parts when split by dots (e.g., example.com)
		// or 3 parts if it's a known TLD structure (e.g., example.co.uk)
		$parts = explode('.', $domain);

		// Simple heuristic: if domain has only 2 parts, it's definitely a main domain
		if (count($parts) <= 2) {
			return true; // e.g., example.com
		}

		// For 3+ parts, check if it's a main domain with multi-part TLD
		$known_multi_part_tlds = apply_filters('wu_multi_part_tlds', ['.co.uk', '.com.au', '.co.nz', '.com.br', '.co.in']);
		$last_two_parts        = '.' . $parts[ count($parts) - 2 ] . '.' . $parts[ count($parts) - 1 ];

		// If it has exactly 3 parts and matches a known multi-part TLD, it's a main domain
		if (count($parts) === 3 && in_array($last_two_parts, $known_multi_part_tlds, true)) {
			return true; // e.g., example.co.uk
		}
		// Must be a subdomain.
		return false;
	}

	/**
	 * Returns the list of available host integrations.
	 *
	 * This needs to be a filterable method to allow integrations to self-register.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_integrations() {

		return apply_filters('wu_domain_manager_get_integrations', $this->integrations, $this);
	}

	/**
	 * Get the instance of one of the integrations classes.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id The id of the integration. e.g. runcloud.
	 * @return mixed|false
	 */
	public function get_integration_instance($id) {

		$integrations = $this->get_integrations();

		if (isset($integrations[ $id ])) {
			$class_name = $integrations[ $id ];

			return $class_name::get_instance();
		}

		return false;
	}

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->enable_rest_api();

		$this->enable_wp_cli();

		$this->enable_mcp_abilities();

		$this->enable_command_palette();

		$this->set_cookie_domain();

		add_action('plugins_loaded', [$this, 'load_integrations']);

		// Initialize DNS Record Manager
		add_action('plugins_loaded', [$this, 'init_dns_record_manager'], 11);

		add_action('wp_ajax_wu_test_hosting_integration', [$this, 'test_integration']);

		add_action('wp_ajax_wu_get_dns_records', [$this, 'get_dns_records']);

		add_action('wu_async_remove_old_primary_domains', [$this, 'async_remove_old_primary_domains']);

		add_action('wu_async_process_domain_stage', [$this, 'async_process_domain_stage'], 10, 2);

		add_action('wu_transition_domain_domain', [$this, 'send_domain_to_host'], 10, 3);

		add_action('wu_transition_domain_stage', [$this, 'maybe_auto_promote_primary_domain'], 10, 3);

		add_action('wu_settings_domain_mapping', [$this, 'add_domain_mapping_settings']);

		add_action('wu_settings_sso', [$this, 'add_sso_settings']);

		/*
		 * Add and remove mapped domains
		 */

		add_action('wu_domain_created', [$this, 'handle_domain_created'], 10, 3);

		add_action('wu_domain_post_delete', [$this, 'handle_domain_deleted'], 10, 2);

		/*
		 * Add and remove sub-domains
		 */

		add_action('wp_insert_site', [$this, 'handle_site_created']);

		add_action('wp_delete_site', [$this, 'handle_site_deleted']);
	}

	/**
	 * Set COOKIE_DOMAIN if not defined in sites with mapped domains or subdomain subsites.
	 *
	 * Two cases require an explicit COOKIE_DOMAIN:
	 *
	 * 1. Mapped domains: the current host does not end with DOMAIN_CURRENT_SITE
	 *    (e.g. translate.example.com on a network rooted at ultimatemultisite.com).
	 *    Cookie domain must be scoped to the mapped domain to avoid leaking to the
	 *    network root.
	 *
	 * 2. Subdomain subsites that are subdomains of other subsites: when both
	 *    ultimatemultisite.com and translate.ultimatemultisite.com are separate
	 *    subsites, WordPress sets auth cookies for .ultimatemultisite.com. The
	 *    browser sends those cookies to translate.ultimatemultisite.com as well,
	 *    making it impossible to maintain independent sessions. Setting COOKIE_DOMAIN
	 *    to .translate.ultimatemultisite.com scopes the cookie to the specific
	 *    subdomain and prevents cross-subsite cookie bleeding.
	 *
	 * @since 2.0.12
	 * @since 2.4.8 Also handles subdomain subsites to prevent cross-subsite cookie bleeding.
	 *
	 * @return void
	 */
	protected function set_cookie_domain() {

		if ( ! defined('DOMAIN_CURRENT_SITE') || defined('COOKIE_DOMAIN')) {
			return;
		}

		$host           = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));
		$network_domain = DOMAIN_CURRENT_SITE;
		$cookie_domain  = $this->determine_cookie_domain($host, $network_domain);

		if (null !== $cookie_domain) {
			define('COOKIE_DOMAIN', $cookie_domain);
		}
	}

	/**
	 * Determines the appropriate COOKIE_DOMAIN value for the given host and network domain.
	 *
	 * Returns the cookie domain string (with leading dot) when an explicit override is needed,
	 * or null when the WordPress default (the network domain) is appropriate.
	 *
	 * Two cases require an explicit COOKIE_DOMAIN:
	 *
	 * 1. Mapped domains: the current host does not end with DOMAIN_CURRENT_SITE
	 *    (e.g. translate.example.com on a network rooted at ultimatemultisite.com).
	 *    Cookie domain must be scoped to the mapped domain to avoid leaking to the
	 *    network root.
	 *
	 * 2. Subdomain subsites that are subdomains of other subsites: when both
	 *    ultimatemultisite.com and translate.ultimatemultisite.com are separate
	 *    subsites, WordPress sets auth cookies for .ultimatemultisite.com. The
	 *    browser sends those cookies to translate.ultimatemultisite.com as well,
	 *    making it impossible to maintain independent sessions. Setting COOKIE_DOMAIN
	 *    to .translate.ultimatemultisite.com scopes the cookie to the specific
	 *    subdomain and prevents cross-subsite cookie bleeding.
	 *
	 * @since 2.4.8
	 *
	 * @param string $host           The current HTTP host (e.g. translate.ultimatemultisite.com).
	 * @param string $network_domain The network root domain (DOMAIN_CURRENT_SITE).
	 * @return string|null The cookie domain string (e.g. '.translate.ultimatemultisite.com'),
	 *                     or null when no override is needed.
	 */
	public function determine_cookie_domain(string $host, string $network_domain): ?string {

		// Case 1: Mapped domain — host does not belong to the network domain at all.
		if ( ! preg_match('/' . preg_quote($network_domain, '/') . '$/', '.' . $host)) {
			return '.' . $host;
		}

		// Case 2: Subdomain subsite — host is a subdomain of the network domain
		// (e.g. translate.ultimatemultisite.com on a network rooted at ultimatemultisite.com).
		// Without an explicit COOKIE_DOMAIN, WordPress uses .ultimatemultisite.com, which
		// bleeds into all subsites sharing that parent domain. Scope the cookie to the
		// most specific domain so each subdomain subsite maintains independent sessions.
		if (strcasecmp($host, $network_domain) !== 0 && str_ends_with(strtolower($host), '.' . strtolower($network_domain))) {
			return '.' . $host;
		}

		// Host is the network root domain itself — no override needed.
		return null;
	}

	/**
	 * Initialize the DNS Record Manager.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function init_dns_record_manager(): void {

		DNS_Record_Manager::get_instance();
	}

	/**
	 * Triggers subdomain mapping events on site creation.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Site $site The site being added.
	 * @return void
	 */
	public function handle_site_created($site): void {

		global $current_site;

		$has_subdomain = str_replace($current_site->domain, '', $site->domain);

		if ( ! $has_subdomain) {
			return;
		}

		$args = [
			'subdomain' => $site->domain,
			'site_id'   => $site->blog_id,
		];

		wu_enqueue_async_action('wu_add_subdomain', $args, 'domain');

		// Create a domain record for the site
		$this->create_domain_record_for_site($site);
	}

	/**
	 * Creates a domain record for a site.
	 *
	 * @since 2.4.0
	 *
	 * @param \WP_Site $site The site to create a domain record for.
	 * @return \WP_Error|\WP_Ultimo\Models\Domain
	 */
	public function create_domain_record_for_site($site) {

		// Check if a domain record already exists for this site
		$existing_domains = wu_get_domains(
			[
				'blog_id' => $site->blog_id,
				'number'  => 1,
			]
		);

		if ( ! empty($existing_domains)) {
			return $existing_domains[0];
		}

		// Create a new domain record
		$domain = wu_create_domain(
			[
				'blog_id'        => $site->blog_id,
				'domain'         => $site->domain,
				'active'         => true,
				'primary_domain' => true,
				'secure'         => false,
				'stage'          => 'checking-dns',
			]
		);

		if (is_wp_error($domain)) {
			wu_log_add('domain-creation', sprintf('Failed to create domain record for site %d: %s', $site->blog_id, $domain->get_error_message()), LogLevel::ERROR);
			return $domain;
		}

		wu_log_add('domain-creation', sprintf('Created domain record for site %d: %s', $site->blog_id, $site->domain));

		// Process the domain stage asynchronously
		wu_enqueue_async_action('wu_async_process_domain_stage', ['domain_id' => $domain->get_id()], 'domain');

		return $domain;
	}

	/**
	 * Triggers subdomain mapping events on site deletion.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Site $site The site being removed.
	 * @return void
	 */
	public function handle_site_deleted($site): void {

		global $current_site;

		$has_subdomain = str_replace($current_site->domain, '', $site->domain);

		if ( ! $has_subdomain) {
			return;
		}

		$args = [
			'subdomain' => $site->domain,
			'site_id'   => $site->blog_id,
		];

		wu_enqueue_async_action('wu_remove_subdomain', $args, 'domain');
	}

	/**
	 * Triggers the do_event of the payment successful.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Domain     $domain The domain.
	 * @param \WP_Ultimo\Models\Site       $site The site.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @return void
	 */
	public function handle_domain_created($domain, $site, $membership): void {

		$payload = array_merge(
			wu_generate_event_payload('domain', $domain),
			wu_generate_event_payload('site', $site),
			wu_generate_event_payload('membership', $membership),
			wu_generate_event_payload('customer', $membership->get_customer())
		);

		wu_do_event('domain_created', $payload);
	}

	/**
	 * Remove send domain removal event.
	 *
	 * @since 2.0.0
	 *
	 * @param boolean                  $result The result of the deletion.
	 * @param \WP_Ultimo\Models\Domain $domain The domain being deleted.
	 * @return void
	 */
	public function handle_domain_deleted($result, $domain): void {

		if ($result) {
			$domain_name = $domain->get_domain();
			$site_id     = $domain->get_site_id();

			$args = [
				'domain'  => $domain_name,
				'site_id' => $site_id,
			];

			wu_enqueue_async_action('wu_remove_domain', $args, 'domain');

			/**
			 * Fires when a custom domain is removed from the network.
			 *
			 * Use this hook to react to domain removal events, for example to
			 * clean up transactional email identities or DNS records.
			 *
			 * @since 2.5.0
			 *
			 * @param string $domain_name The domain name that was removed.
			 * @param int    $site_id     The site ID the domain was removed from.
			 */
			do_action('wu_domain_removed', $domain_name, $site_id);
		}
	}

	/**
	 * Add all domain mapping settings.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_domain_mapping_settings(): void {

		wu_register_settings_field(
			'domain-mapping',
			'domain_mapping_header',
			[
				'title' => __('Domain Mapping Settings', 'ultimate-multisite'),
				'desc'  => __('Define the domain mapping settings for your network.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'domain-mapping',
			'enable_domain_mapping',
			[
				'title'   => __('Enable Domain Mapping?', 'ultimate-multisite'),
				'desc'    => __('Do you want to enable domain mapping?', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		wu_register_settings_field(
			'domain-mapping',
			'force_admin_redirect',
			[
				'title'   => __('Force Admin Redirect', 'ultimate-multisite'),
				'desc'    => __('Select how you want your users to access the admin panel if they have mapped domains.', 'ultimate-multisite') . '<br><br>' . __('Force Redirect to Mapped Domain: your users with mapped domains will be redirected to theirdomain.com/wp-admin, even if they access using yournetworkdomain.com/wp-admin.', 'ultimate-multisite') . '<br><br>' . __('Force Redirect to Network Domain: your users with mapped domains will be redirect to yournetworkdomain.com/wp-admin, even if they access using theirdomain.com/wp-admin.', 'ultimate-multisite'),
				'tooltip' => '',
				'type'    => 'select',
				'default' => 'both',
				'require' => ['enable_domain_mapping' => 1],
				'options' => [
					'both'          => __('Allow access to the admin by both mapped domain and network domain', 'ultimate-multisite'),
					'force_map'     => __('Force Redirect to Mapped Domain', 'ultimate-multisite'),
					'force_network' => __('Force Redirect to Network Domain', 'ultimate-multisite'),
				],
			]
		);

		wu_register_settings_field(
			'domain-mapping',
			'custom_domains',
			[
				'title'   => __('Enable Custom Domains?', 'ultimate-multisite'),
				'desc'    => __('Toggle this option if you wish to allow end-customers to add their own domains. This can be controlled on a plan per plan basis.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
				'require' => [
					'enable_domain_mapping' => true,
				],
			]
		);

		wu_register_settings_field(
			'domain-mapping',
			'domain_mapping_instructions',
			[
				'title'      => __('Add New Domain Instructions', 'ultimate-multisite'),
				'tooltip'    => __('Display a customized message with instructions for the mapping and alerting the end-user of the risks of mapping a misconfigured domain.', 'ultimate-multisite'),
				'desc'       => __('You can use the placeholder <code>%NETWORK_DOMAIN%</code> and <code>%NETWORK_IP%.</code> HTML is allowed.', 'ultimate-multisite'),
				'type'       => 'textarea',
				'default'    => [$this, 'default_domain_mapping_instructions'],
				'html_attr'  => [
					'rows' => 8,
				],
				'require'    => [
					'enable_domain_mapping' => true,
					'custom_domains'        => true,
				],
				'allow_html' => true,
			]
		);

		wu_register_settings_field(
			'domain-mapping',
			'dns_check_interval',
			[
				'title'     => __('DNS Check Interval', 'ultimate-multisite'),
				'tooltip'   => __('Set the interval in seconds between DNS and SSL certificate checks for domains.', 'ultimate-multisite'),
				'desc'      => __('Minimum: 10 seconds, Maximum: 300 seconds (5 minutes). Default: 300 seconds.', 'ultimate-multisite'),
				'type'      => 'number',
				'default'   => 300,
				'min'       => 10,
				'max'       => 300,
				'html_attr' => [
					'step' => 1,
				],
				'require'   => [
					'enable_domain_mapping' => true,
				],
			]
		);

		wu_register_settings_field(
			'domain-mapping',
			'auto_create_www_subdomain',
			[
				'title'   => __('Create www Subdomain Automatically?', 'ultimate-multisite'),
				'desc'    => __('Control when www subdomains should be automatically created for mapped domains.', 'ultimate-multisite'),
				'tooltip' => __('This setting applies to all hosting integrations and determines when a www version of the domain should be automatically created.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'always',
				'options' => [
					'always'    => __('Always - Create www subdomain for all domains', 'ultimate-multisite'),
					'main_only' => __('Only for main domains (e.g., example.com but not subdomain.example.com)', 'ultimate-multisite'),
					'never'     => __('Never - Do not automatically create www subdomains', 'ultimate-multisite'),
				],
				'require' => [
					'enable_domain_mapping' => true,
				],
			]
		);
	}

	/**
	 * Check if a www subdomain should be created for the given domain.
	 *
	 * @since 2.0.0
	 * @param string $domain The domain to check.
	 * @return bool True if www subdomain should be created, false otherwise.
	 */
	public function should_create_www_subdomain($domain) {

		// Normalize incoming domain
		$domain = trim(strtolower($domain));

		// Guard against double-prefixing - return false if already starts with www.
		if (strpos($domain, 'www.') === 0) {
			return false;
		}

		$setting = wu_get_setting('auto_create_www_subdomain', 'always');

		switch ($setting) {
			case 'never':
				return false;

			case 'main_only':
				return self::is_main_domain($domain);

			case 'always':
			default:
				return true;
		}
	}

	/**
	 * Add all SSO settings.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_sso_settings(): void {

		wu_register_settings_field(
			'sso',
			'sso_header',
			[
				'title' => __('Single Sign-On Settings', 'ultimate-multisite'),
				'desc'  => __('Settings to configure the Single Sign-On functionality of Ultimate Multisite, responsible for keeping customers and admins logged in across all network domains.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'sso',
			'enable_sso',
			[
				'title'   => __('Enable Single Sign-On', 'ultimate-multisite'),
				'desc'    => __('Enables the Single Sign-on functionality.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		wu_register_settings_field(
			'sso',
			'restrict_sso_to_login_pages',
			[
				'title'   => __('Restrict SSO Checks to Login Pages', 'ultimate-multisite'),
				'desc'    => __('The Single Sign-on feature adds one extra ajax calls to every page load on sites with custom domains active to check if it should perform an auth loopback. You can restrict these extra calls to the login pages of sub-sites using this option. If enabled, SSO will only work on login pages.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
				'require' => [
					'enable_sso' => true,
				],
			]
		);

		wu_register_settings_field(
			'sso',
			'enable_sso_loading_overlay',
			[
				'title'   => __('Enable SSO Loading Overlay', 'ultimate-multisite'),
				'desc'    => __('When active, a loading overlay will be added on-top of the site currently being viewed while the SSO auth loopback is performed on the background.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
				'require' => [
					'enable_sso' => true,
				],
			]
		);

		wu_register_settings_field(
			'sso',
			'enable_magic_links',
			[
				'title'   => __('Enable Magic Links', 'ultimate-multisite'),
				'desc'    => __('Enables magic link authentication for custom domains. Magic links provide a fallback authentication method for browsers that don\'t support third-party cookies. When enabled, dashboard and site links will automatically log users in when accessing sites with custom domains. Tokens are cryptographically secure, one-time use, and expire after 10 minutes.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);
	}

	/**
	 * Returns the default instructions for domain mapping.
	 *
	 * @since 2.0.0
	 */
	public function default_domain_mapping_instructions(): string {

		$instructions = [];

		$instructions[] = __("Cool! You're about to make this site accessible using your own domain name!", 'ultimate-multisite');

		$instructions[] = __("For that to work, you'll need to create a new CNAME record pointing to <code>%NETWORK_DOMAIN%</code> on your DNS manager.", 'ultimate-multisite');

		$instructions[] = __('After you finish that step, come back to this screen and click the button below.', 'ultimate-multisite');

		return implode(PHP_EOL . PHP_EOL, $instructions);
	}

	/**
	 * Gets the instructions, filtered and without the shortcodes.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_domain_mapping_instructions() {

		global $current_site;

		$instructions = wu_get_setting('domain_mapping_instructions', '');

		if ( ! $instructions) {
			$instructions = $this->default_domain_mapping_instructions();
		}

		$domain = $current_site->domain;
		$ip     = Helper::get_network_public_ip();

		/*
		 * Replace placeholders
		 */
		$instructions = str_replace('%NETWORK_DOMAIN%', $domain, (string) $instructions);
		$instructions = str_replace('%NETWORK_IP%', $ip, $instructions);

		return apply_filters('wu_get_domain_mapping_instructions', $instructions, $domain, $ip);
	}

	/**
	 * Creates the event to save the transition.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $old_value The old value, before the transition.
	 * @param mixed $new_value The new value, after the transition.
	 * @param int   $item_id The id of the element transitioning.
	 * @return void
	 */
	public function send_domain_to_host($old_value, $new_value, $item_id): void {

		if ($old_value !== $new_value) {
			$domain  = wu_get_domain($item_id);
			$site_id = $domain->get_site_id();

			$args = [
				'domain'  => $new_value,
				'site_id' => $site_id,
			];

			wu_enqueue_async_action('wu_add_domain', $args, 'domain');

			/**
			 * Fires when a custom domain is added to the network.
			 *
			 * Use this hook to react to domain addition events, for example to
			 * initiate transactional email domain verification or DNS record creation.
			 *
			 * @since 2.5.0
			 *
			 * @param string $new_value The domain name that was added.
			 * @param int    $site_id   The site ID the domain was added to.
			 */
			do_action('wu_domain_added', $new_value, $site_id);
		}
	}

	/**
	 * Checks the DNS and SSL status of a domain.
	 *
	 * @since 2.0.0
	 *
	 * @param int $domain_id The domain mapping ID.
	 * @param int $tries Number of tries.
	 * @return void
	 */
	public function async_process_domain_stage($domain_id, $tries = 0): void {

		$domain = wu_get_domain($domain_id);

		if ( ! $domain) {
			return;
		}

		$max_tries = apply_filters('wu_async_process_domain_stage_max_tries', 5, $domain);

		// Get the DNS check interval from settings (in seconds)
		$dns_check_interval = wu_get_setting('dns_check_interval', 300);

		// Ensure the interval is within the allowed range (10-300 seconds)
		$dns_check_interval = max(10, min(300, (int) $dns_check_interval));

		// Convert seconds to minutes for the schedule
		$try_again_time = ceil($dns_check_interval / 60);

		// Ensure we have at least 1 minute
		$try_again_time = max(1, $try_again_time);

		$try_again_time = apply_filters('wu_async_process_domains_try_again_time', $try_again_time, $domain); // minutes

		++$tries;

		$stage = $domain->get_stage();

		$domain_url = $domain->get_domain();

		// translators: %s is the domain name
		wu_log_add("domain-{$domain_url}", sprintf(__('Starting Check for %s', 'ultimate-multisite'), $domain_url));

		if (Domain_Stage::CHECKING_DNS === $stage) {
			if ($domain->has_correct_dns()) {
				$domain->set_stage(Domain_Stage::CHECKING_SSL);

				$domain->save();

				wu_log_add(
					"domain-{$domain_url}",
					__('- DNS propagation finished, advancing domain to next step...', 'ultimate-multisite')
				);

				wu_enqueue_async_action(
					'wu_async_process_domain_stage',
					[
						'domain_id' => $domain_id,
						'tries'     => 0,
					],
					'domain'
				);

				do_action('wu_domain_manager_dns_propagation_finished', $domain);

				return;
			} else {
				/*
				 * Max attempts
				 */
				if ($tries > $max_tries) {
					$domain->set_stage(Domain_Stage::FAILED);

					$domain->save();

					wu_log_add(
						"domain-{$domain_url}",
						// translators: %d is the number of minutes to try again.
						sprintf(__('- DNS propagation checks tried for the max amount of times (5 times, one every %d minutes). Marking as failed.', 'ultimate-multisite'), $try_again_time)
					);

					return;
				}

				wu_log_add(
					"domain-{$domain_url}",
					// translators: %d is the number of minutes before trying again.
					sprintf(__('- DNS propagation not finished, retrying in %d minutes...', 'ultimate-multisite'), $try_again_time)
				);

				wu_schedule_single_action(
					time() + $dns_check_interval,
					'wu_async_process_domain_stage',
					[
						'domain_id' => $domain_id,
						'tries'     => $tries,
					],
					'domain'
				);

				return;
			}
		} elseif (Domain_Stage::CHECKING_SSL === $stage) {
			if ($domain->has_valid_ssl_certificate()) {
				$domain->set_stage(Domain_Stage::DONE);

				$domain->set_secure(true);

				$domain->save();

				wu_log_add(
					"domain-{$domain_url}",
					__('- Valid SSL cert found. Marking domain as done.', 'ultimate-multisite')
				);

				return;
			} else {
				/*
				 * Max attempts
				 */
				if ($tries > $max_tries) {
					// We use SSL FAILED instead of done-without-ssl since ssl is pretty much required
					// and we don't want to redirect to a domain with certificate errors.
					$domain->set_stage(Domain_Stage::SSL_FAILED);

					$domain->save();
					wu_log_add(
						"domain-{$domain_url}",
						// translators: %d is the number of minutes to try again.
						sprintf(__('- SSL checks tried for the max amount of times (5 times, one every %d minutes). Marking as ready without SSL.', 'ultimate-multisite'), $try_again_time)
					);

					return;
				}

				wu_log_add(
					"domain-{$domain_url}",
					// translators: %d is the number of minutes before trying again.
					sprintf(__('- SSL Cert not found, retrying in %d minute(s)...', 'ultimate-multisite'), $try_again_time)
				);

				wu_schedule_single_action(
					time() + $dns_check_interval,
					'wu_async_process_domain_stage',
					[
						'domain_id' => $domain_id,
						'tries'     => $tries,
					],
					'domain'
				);

				return;
			}
		}
	}

	/**
	 * Alternative implementation for PHP's native dns_get_record.
	 *
	 * @since 2.0.0
	 * @param string $domain The domain to check.
	 * @return array
	 */
	public static function dns_get_record($domain) {

		$results = [];

		wu_setup_memory_limit_trap('json');

		wu_try_unlimited_server_limits();

		$record_types = [
			'NS',
			'CNAME',
			'A',
		];

		foreach ($record_types as $record_type) {
			$chain = new \RemotelyLiving\PHPDNS\Resolvers\Chain(
				new \RemotelyLiving\PHPDNS\Resolvers\CloudFlare(),
				new \RemotelyLiving\PHPDNS\Resolvers\GoogleDNS(),
				new \RemotelyLiving\PHPDNS\Resolvers\LocalSystem(),
				new \RemotelyLiving\PHPDNS\Resolvers\Dig(),
			);

			$records = $chain->getRecords($domain, $record_type);

			foreach ($records as $record_data) {
				$record = [];

				$record['type'] = $record_type;

				$record['data'] = (string) $record_data->getData();

				if (empty($record['data'])) {
					$record['data'] = (string) $record_data->getIPAddress();
				}

				// Some DNS providers return a trailing dot.
				$record['data'] = rtrim($record['data'], '.');

				$record['ip'] = (string) $record_data->getIPAddress();

				$record['ttl'] = $record_data->getTTL();

				$record['host'] = $domain;

				$record['tag'] = ''; // Used by integrations.

				$results[] = $record;
			}
		}

		return apply_filters('wu_domain_dns_get_record', $results, $domain);
	}

	/**
	 * Get the DNS records for a given domain.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function get_dns_records(): void {

		$domain = wu_request('domain');

		if ( ! $domain) {
			wp_send_json_error(new \WP_Error('domain-missing', __('A valid domain was not passed.', 'ultimate-multisite')));
		}

		$auth_ns = [];

		$additional = [];

		try {
			$result = self::dns_get_record($domain);
		} catch (\Throwable $e) {
			wp_send_json_error(
				new \WP_Error(
					'error',
					__('Not able to fetch DNS entries.', 'ultimate-multisite'),
					[
						'exception' => $e->getMessage(),
					]
				)
			);
		}

		if (false === $result) {
			wp_send_json_error(new \WP_Error('error', __('Not able to fetch DNS entries.', 'ultimate-multisite')));
		}

		$network_ip = Helper::get_network_public_ip();
		$warnings   = [];
		$www_result = [];

		// Get A records for the bare domain.
		$a_records = array_filter($result, fn($r) => 'A' === $r['type'] && $domain === $r['host']);
		$a_ips     = array_column($a_records, 'data');

		// Warning: multiple A records.
		if (count($a_ips) > 1) {
			$warnings[] = sprintf(
				/* translators: %1$s is a comma-separated list of IPs, %2$s is the network IP */
				__('This domain has multiple A records (%1$s). Only one A record pointing to your network IP (%2$s) is expected. The extra records may cause intermittent connectivity issues.', 'ultimate-multisite'),
				implode(', ', $a_ips),
				$network_ip
			);
		}

		// Warning: no A record matches network IP.
		if ( ! empty($a_ips) && ! in_array($network_ip, $a_ips, true)) {
			$warnings[] = sprintf(
				/* translators: %s is the network IP */
				__('None of the A records point to your network IP (%s). The domain will not resolve to your network.', 'ultimate-multisite'),
				$network_ip
			);
		}

		// Fetch www subdomain records and compare.
		if (strpos($domain, 'www.') !== 0) {
			try {
				$www_result = self::dns_get_record('www.' . $domain);

				if (false === $www_result) {
					$www_result = [];
				}
			} catch (\Throwable $e) {
				$www_result = [];
			}

			$www_a_records = array_filter($www_result, fn($r) => 'A' === $r['type']);
			$www_a_ips     = array_column($www_a_records, 'data');

			if ( ! empty($www_a_ips)) {
				sort($a_ips);
				sort($www_a_ips);

				if ($a_ips !== $www_a_ips) {
					$warnings[] = sprintf(
						/* translators: %s is the network IP */
						__('The www subdomain DNS records do not match the non-www records. Both should point to %s.', 'ultimate-multisite'),
						$network_ip
					);
				}
			}
		}

		wp_send_json_success(
			[
				'entries'     => $result,
				'www_entries' => $www_result,
				'auth'        => $auth_ns,
				'additional'  => $additional,
				'network_ip'  => $network_ip,
				'warnings'    => $warnings,
			]
		);
	}

	/**
	 * Takes the list of domains and set them to non-primary when a new primary is added.
	 *
	 * This is triggered when a new domain is added as primary_domain.
	 *
	 * @since 2.0.0
	 *
	 * @param array $domains List of domain ids.
	 * @return void
	 */
	public function async_remove_old_primary_domains($domains): void {

		foreach ($domains as $domain_id) {
			$domain = wu_get_domain($domain_id);

			if ($domain) {
				$domain->set_primary_domain(false);

				$domain->save();
			}
		}
	}

	/**
	 * Auto-promote a custom domain to primary when it reaches done/done-without-ssl stage.
	 *
	 * When a non-subdomain mapping transitions to done or done-without-ssl stage
	 * for a blog that has no other primary custom domain, auto-promote it. This is
	 * the behaviour customers expect: "I verified my domain's DNS, it's now primary
	 * and the network subdomain redirects to it."
	 *
	 * Hooked into wu_transition_domain_stage so it fires only on an actual stage
	 * change — not on every save. This avoids the recursive-promotion bug where
	 * async_remove_old_primary_domains would strip the primary flag, triggering a
	 * post_save hook that immediately re-promoted the domain.
	 *
	 * @since 2.7.1
	 * @since 2.7.2 Switched from wu_domain_post_save to wu_transition_domain_stage;
	 *              replaced is_main_domain() TLD heuristic with a network-host check.
	 *
	 * @param string $old_stage The previous stage value.
	 * @param string $new_stage The new stage value.
	 * @param int    $domain_id The domain record ID.
	 * @return void
	 */
	public function maybe_auto_promote_primary_domain($old_stage, $new_stage, $domain_id): void {

		$done_stages = [
			Domain_Stage::DONE,
			Domain_Stage::DONE_WITHOUT_SSL,
		];

		/*
		 * Only act when the stage just transitioned TO done/done-without-ssl
		 * from a non-done stage. This is the "DNS verified" moment.
		 */
		if ( ! in_array($new_stage, $done_stages, true)) {
			return;
		}

		if (in_array($old_stage, $done_stages, true)) {
			return; // Already was in a done stage — not a new verification.
		}

		$domain = wu_get_domain($domain_id);

		if ( ! $domain) {
			return;
		}

		/*
		 * Already primary — nothing to do.
		 */
		if ($domain->is_primary_domain()) {
			return;
		}

		$domain_url = $domain->get_domain();

		/*
		 * Only skip auto-promotion for WP multisite native subdomains
		 * (e.g. site.kursopro.com when the network host is kursopro.com).
		 *
		 * WP multisite native subdomains live in wp_blogs and should not
		 * normally appear in the UM domains table, but we guard explicitly by
		 * comparing against the actual network host. This is more precise
		 * than the old is_main_domain() TLD-counting heuristic, which
		 * incorrectly blocked legitimate 3-part custom domains such as
		 * shop.mybrand.com or prosite.example.com.
		 *
		 * @since 2.7.2
		 */
		$network_host = strtolower( (string) wp_parse_url( network_home_url(), PHP_URL_HOST ) );
		$network_host = (string) preg_replace( '/:\d+$/', '', $network_host ); // strip port

		if ( $network_host && substr( strtolower( $domain_url ), -(strlen( $network_host ) + 1) ) === '.' . $network_host ) {
			return; // WP multisite native subdomain — skip auto-promotion.
		}

		$blog_id = $domain->get_blog_id();

		/*
		 * Check if the blog already has a primary custom domain (not a WP network
		 * subdomain). If so, don't override — let the admin manage it manually.
		 */
		$existing_primaries = wu_get_domains(
			[
				'blog_id'        => $blog_id,
				'primary_domain' => true,
				'id__not_in'     => [$domain->get_id()],
			]
		);

		foreach ($existing_primaries as $existing) {
			$existing_host = strtolower( $existing->get_domain() );

			if ( ! $network_host || substr( $existing_host, -(strlen( $network_host ) + 1) ) !== '.' . $network_host ) {
				/*
				 * Another custom (non-network-subdomain) domain is already
				 * primary for this blog. Do not auto-promote.
				 */
				return;
			}
		}

		/*
		 * Promote this domain to primary. Domain::save() schedules
		 * wu_async_remove_old_primary_domains to demote other primaries.
		 */
		$domain->set_primary_domain(true);

		$save_result = $domain->save();

		if (is_wp_error($save_result)) {
			wu_log_add(
				"domain-{$domain_url}",
				sprintf(
					// translators: %1$s is the domain name, %2$d is the blog ID, %3$s is the error message.
					__('Failed to auto-promote %1$s as primary domain for site %2$d: %3$s', 'ultimate-multisite'),
					$domain_url,
					$blog_id,
					$save_result->get_error_message()
				),
				LogLevel::ERROR
			);
			return;
		}

		wu_log_add(
			"domain-{$domain_url}",
			sprintf(
				// translators: %1$s is the domain name, %2$d is the blog ID.
				__('Auto-promoted %1$s as primary domain for site %2$d.', 'ultimate-multisite'),
				$domain_url,
				$blog_id
			)
		);
	}

	/**
	 * Tests the integration in the Wizard context.
	 *
	 * Supports both legacy host providers and new Integration objects.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function test_integration() {

		$integration_id = wu_request('integration', 'none');

		// Try the new Integration Registry first
		$registry        = \WP_Ultimo\Integrations\Integration_Registry::get_instance();
		$new_integration = $registry->get($integration_id);

		if ($new_integration) {
			if ( ! $new_integration->is_setup()) {
				wp_send_json_error(
					[
						'message' => sprintf(
							// translators: %s is the name of the missing constant
							__('The necessary constants were not found on your wp-config.php file: %s', 'ultimate-multisite'),
							implode(', ', $new_integration->get_missing_constants())
						),
					]
				);
			}

			$result = $new_integration->test_connection();

			if (is_wp_error($result)) {
				wp_send_json_error(['message' => $result->get_error_message()]);
			}

			wp_send_json_success(['message' => __('Access Authorized', 'ultimate-multisite')]);
		}

		// Fall back to legacy integration
		$integration = $this->get_integration_instance($integration_id);

		if ( ! $integration) {
			wp_send_json_error(
				[
					'message' => __('Invalid Integration ID', 'ultimate-multisite'),
				]
			);
		}

		/*
		 * Checks for the constants...
		 */
		if ( ! $integration->is_setup()) {
			wp_send_json_error(
				[
					'message' => sprintf(
						// translators: %s is the name of the missing constant
						__('The necessary constants were not found on your wp-config.php file: %s', 'ultimate-multisite'),
						implode(', ', $integration->get_missing_constants())
					),
				]
			);
		}

		$integration->test_connection();
	}

	/**
	 * Loads all the host provider integrations we have available.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function load_integrations(): void {

		/**
		 * Allow developers to add their own host provider integrations via wp plugins.
		 *
		 * @since 2.0.0
		 */
		do_action('wp_ultimo_host_providers_load');
	}

	/**
	 * Verify domain ownership using a loopback request.
	 *
	 * This method attempts to verify a domain by making an loopback request with a
	 * a specific parameter that is used by Domain_Mapper::verify_dns_mapping().
	 *
	 * @since 2.4.4
	 *
	 * @param Domain $domain The domain object to verify.
	 * @return bool True if verification succeeds, false otherwise.
	 */
	public function verify_domain_with_loopback_request(Domain $domain): bool {

		$domain_url = $domain->get_domain();
		$domain_id  = $domain->get_id();

		$endpoint_path = '/';

		// Test protocols in order of preference: HTTPS with SSL verify, HTTPS without SSL verify, HTTP
		$protocols_to_test = [
			[
				'url'       => "https://{$domain_url}{$endpoint_path}",
				/** This filter is documented in wp-includes/class-wp-http-streams.php */
				'sslverify' => apply_filters('https_local_ssl_verify', false),
				'label'     => 'HTTPS with SSL verification',
			],
			[
				'url'   => "http://{$domain_url}{$endpoint_path}",
				'label' => 'HTTP',
			],
		];

		foreach ($protocols_to_test as $protocol_config) {
			wu_log_add(
				"domain-{$domain_url}",
				sprintf(
					/* translators: %1$s: Protocol label (HTTPS with SSL verification, HTTPS without SSL verification, HTTP), %2$s: URL being tested */
					__('Testing domain verification via Loopback using %1$s: %2$s', 'ultimate-multisite'),
					$protocol_config['label'],
					$protocol_config['url']
				)
			);

			// Make API request with basic auth
			$response = wp_remote_get(
				$protocol_config['url'],
				[
					'timeout'     => 10,
					'redirection' => 0,
					'sslverify'   => $protocol_config['sslverify'] ?? false,
					'body'        => ['async_check_dns_nonce' => wp_hash($domain_url)],
				]
			);

			// Check for connection errors
			if (is_wp_error($response)) {
				wu_log_add(
					"domain-{$domain_url}",
					sprintf(
					/* translators: %1$s: Protocol label (HTTPS with SSL verification, HTTPS without SSL verification, HTTP), %2$s: Error Message */
						__('Failed to connect via %1$s: %2$s', 'ultimate-multisite'),
						$protocol_config['label'],
						$response->get_error_message()
					),
					LogLevel::WARNING
				);
				continue;
			}

			$response_code = wp_remote_retrieve_response_code($response);
			$body          = wp_remote_retrieve_body($response);

			// Check HTTP status
			if (200 !== $response_code) {
				wu_log_add(
					"domain-{$domain_url}",
					sprintf(
						/* translators: %1$s: Protocol label (HTTPS with SSL verification, HTTPS without SSL verification, HTTP), %2$s: HTTP Response Code */
						__('Loopback request via %1$s returned HTTP %2$d', 'ultimate-multisite'),
						$protocol_config['label'],
						$response_code
					),
					LogLevel::WARNING
				);
				continue;
			}

			// Try to decode JSON response
			$data = json_decode($body, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				wu_log_add(
					"domain-{$domain_url}",
					sprintf(
						/* translators: %1$s: Protocol label (HTTPS with SSL verification, HTTPS without SSL verification, HTTP), %2$s: Json error, %3$s part of the response */
						__('Loopback response via %1$s is not valid JSON: %2$s : %3$s', 'ultimate-multisite'),
						$protocol_config['label'],
						json_last_error_msg(),
						substr($body, 0, 100)
					),
					LogLevel::WARNING
				);
				continue;
			}

			// Check if we got a valid domain object back
			if (isset($data['id']) && (int) $data['id'] === $domain_id) {
				wu_log_add(
					"domain-{$domain_url}",
					sprintf(
					/* translators: %1$s: Protocol label (HTTPS with SSL verification, HTTPS without SSL verification, HTTP), %2$s: Domain ID number */
						__('Domain verification successful via Loopback using %1$s. Domain ID %2$d confirmed.', 'ultimate-multisite'),
						$protocol_config['label'],
						$domain_id
					)
				);

				return true;
			}

			wu_log_add(
				"domain-{$domain_url}",
				sprintf(
				/* translators: %1$s: Protocol label (HTTPS with SSL verification, HTTPS without SSL verification, HTTP), %2$s: Domain ID number, %3$s Domain ID number */
					__('Loopback response via %1$s did not contain expected domain ID. Expected: %2$d, Got: %3$s', 'ultimate-multisite'),
					$protocol_config['label'],
					$domain_id,
					isset($data['id']) ? $data['id'] : 'null'
				),
				LogLevel::WARNING
			);
		}

		wu_log_add(
			"domain-{$domain_url}",
			__('Domain verification failed via loopback on all protocols (HTTPS with SSL, HTTPS without SSL, HTTP).', 'ultimate-multisite'),
			LogLevel::ERROR
		);

		return false;
	}
}
