<?php
/**
 * Handles Domain Mapping in Ultimate Multisite.
 *
 * @package WP_Ultimo
 * @subpackage Domain_Mapping
 * @since 2.0.0
 */

namespace WP_Ultimo;

use WP_Ultimo\Models\Domain;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles Domain Mapping in Ultimate Multisite.
 *
 * @since 2.0.0
 */
class Domain_Mapping {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Keeps a copy of the current mapping.
	 *
	 * @since 2.0.0
	 * @var Domain
	 */
	public $current_mapping = null;

	/**
	 * Keeps a copy of the original URL.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $original_url = null;

	/**
	 * Runs on singleton instantiation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		if (static::should_skip_checks()) {
			$this->startup();
		} else {
			$this->maybe_startup();
		}

		/*
		 * Allow redirects to any host that belongs to this network
		 * (either a mapped domain or a site's original domain).
		 * Always run this to allow wp_safe_redirect in any context.
		 */
		add_filter('allowed_redirect_hosts', [$this, 'allow_network_redirect_hosts'], 20, 2);
	}

	/**
	 * Check if we should skip checks before running mapping functions.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public static function should_skip_checks() {

		return defined('WP_ULTIMO_DOMAIN_MAPPING_SKIP_CHECKS') && WP_ULTIMO_DOMAIN_MAPPING_SKIP_CHECKS;
	}

	/**
	 * Run the checks to make sure the requirements for Domain mapping are in place and execute it.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function maybe_startup(): void {
		/*
		 * Don't run during installation...
		 */
		if (defined('WP_INSTALLING') && '/wp-activate.php' !== $_SERVER['SCRIPT_NAME']) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return;
		}

		/*
		 * Make sure we got loaded in the sunrise stage.
		 */
		if (did_action('muplugins_loaded')) {
			return;
		}

		$is_enabled = (bool) wu_get_setting_early('enable_domain_mapping');

		if (false === $is_enabled) {
			return;
		}

		/*
		 * Start the engines!
		 */
		$this->startup();
	}

	/**
	 * Actual handles domain mapping functionality.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function startup(): void {
		/*
		 * Adds the necessary tables to the $wpdb global.
		 */
		if (empty($GLOBALS['wpdb']->wu_dmtable)) {
			$GLOBALS['wpdb']->wu_dmtable = $GLOBALS['wpdb']->base_prefix . 'wu_domain_mappings';

			$GLOBALS['wpdb']->ms_global_tables[] = 'wu_domain_mappings';
		}

		// Ensure cache is shared
		wp_cache_add_global_groups(['domain_mappings', 'network_mappings']);

		/*
		 * Check if the URL being accessed right now is a mapped domain
		 */
		add_filter('pre_get_site_by_path', [$this, 'check_domain_mapping'], 10, 2);

		add_action('ms_site_not_found', [$this, 'verify_dns_mapping'], 5, 3);

		/*
		 * When a site gets delete, clean up the mapped domains
		 */
		add_action('wp_delete_site', [$this, 'clear_mappings_on_delete']);

		/*
		 * Adds the filters that will change the URLs when a mapped domains is in use
		 */
		add_action('ms_loaded', [$this, 'register_mapped_filters'], 11);

		/**
		 * On WP Ultimo 1.X builds we used Mercator. The Mercator actions and filters are now deprecated.
		 */
		if (has_action('mercator_load')) {
			do_action_deprecated('mercator_load', [], '2.0.0', 'wu_domain_mapping_load');
		}

		add_filter(
			'wu_sso_site_allowed_domains',
			function ($domain_list, $site_id): array {

				$domains = wu_get_domains(
					[
						'active'        => true,
						'blog_id'       => $site_id,
						'stage__not_in' => Domain::INACTIVE_STAGES,
						'fields'        => 'domain',
					]
				);

				return array_merge($domain_list, $domains);
			},
			10,
			2
		);

		/**
		 * Fired after our core Domain Mapping has been loaded
		 *
		 * Hook into this to handle any add-on functionality.
		 */
		do_action('wu_domain_mapping_load');
	}

	/**
	 * Checks if an origin is a mapped domain.
	 *
	 * If that's the case, we should always allow that origin.
	 *
	 * @since 2.0.0
	 *
	 * @param string $origin The origin passed.
	 * @return string
	 */
	public function add_mapped_domains_as_allowed_origins($origin) {

		if ( ! function_exists('wu_get_domain_by_domain')) {
			return '';
		}

		if (empty($origin) && wp_doing_ajax()) {
			$origin = wu_get_current_url();
		}

		$the_domain = wp_parse_url($origin, PHP_URL_HOST);

		$domain = wu_get_domain_by_domain($the_domain);

		if ($domain) {
			return $domain->get_domain();
		}

		return $origin;
	}

	/**
	 * Extend allowed redirect hosts with any host present in the network.
	 *
	 * - If `$host` is already allowed, return as-is.
	 * - Otherwise, allow when `$host` matches a mapped domain (including www/no-www variants),
	 *   or when there is a site registered on the network using `$host` as its domain.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $allowed_hosts Currently allowed hosts.
	 * @param string   $host          Host being validated.
	 * @return string[] Updated list of allowed hosts.
	 */
	public function allow_network_redirect_hosts($allowed_hosts, $host) {

		// Basic sanity checks
		if (empty($host)) {
			return $allowed_hosts;
		}

		$allowed_hosts = (array) $allowed_hosts;
		$host          = strtolower($host);
		$host_no_port  = preg_replace('/:\d+$/', '', $host);

		foreach ($allowed_hosts as $allowed_host) {
			$allowed_host_no_port = preg_replace('/:\d+$/', '', strtolower($allowed_host));

			if (
				$allowed_host_no_port !== $allowed_host
				&& ! in_array($allowed_host_no_port, $allowed_hosts, true)
			) {
				$allowed_hosts[] = $allowed_host_no_port;
			}
		}

		// If already allowed, bail early
		if (
			in_array($host, $allowed_hosts, true)
			|| in_array($host_no_port, $allowed_hosts, true)
		) {
			return array_values(array_unique($allowed_hosts));
		}

		$hosts_to_allow = array_values(array_unique([$host, $host_no_port]));

		// 1) Check mapped domains (including www/no-www variants)
		$domains_to_check = [];

		foreach ($hosts_to_allow as $host_to_check) {
			$domains_to_check = array_merge(
				$domains_to_check,
				$this->get_www_and_nowww_versions($host_to_check)
			);
		}

		$domains_to_check = array_values(array_unique($domains_to_check));
		$mapping          = Domain::get_by_domain($domains_to_check);

		if ($mapping && ! is_wp_error($mapping)) {
			$allowed_hosts = array_merge($allowed_hosts, $hosts_to_allow);
			return array_values(array_unique($allowed_hosts));
		}

		// 2) Check if any site is registered with this domain on the network
		$site = null;

		if (function_exists('get_site_by_path')) {
			foreach ($hosts_to_allow as $host_to_check) {
				$site = get_site_by_path($host_to_check, '/');

				if ($site instanceof \WP_Site) {
					break;
				}
			}
		}

		if ($site instanceof \WP_Site) {
			$allowed_hosts = array_merge($allowed_hosts, $hosts_to_allow);
			return array_values(array_unique($allowed_hosts));
		}

		// Fallback: try a lightweight site query by domain (if available in this context)
		if (function_exists('get_sites')) {
			foreach ($hosts_to_allow as $host_to_check) {
				$maybe = get_sites(
					[
						'number' => 1,
						'domain' => $host_to_check,
						'fields' => 'ids',
					]
				);

				if (! empty($maybe)) {
					$allowed_hosts = array_merge($allowed_hosts, $hosts_to_allow);
					return array_values(array_unique($allowed_hosts));
				}
			}
		}

		return $allowed_hosts;
	}

	/**
	 * Fixes the SSO target site in cases of domain mapping.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Site $target_site The current target site.
	 * @param string   $domain The domain being searched.
	 * @return \WP_Site
	 */
	public function fix_sso_target_site($target_site, $domain) {

		if ( ! $target_site || ! $target_site->blog_id) {
			$mapping = Domain::get_by_domain($domain);

			if ($mapping) {
				$target_site = get_site($mapping->get_site_id());
			}
		}

		return $target_site;
	}

	/**
	 * Returns both the naked and www. version of the given domain
	 *
	 * @since 2.0.0
	 *
	 * @param string $domain Domain to get the naked and www. versions to.
	 * @return array
	 */
	public function get_www_and_nowww_versions($domain) {

		if (str_starts_with($domain, 'www.')) {
			$www   = $domain;
			$nowww = substr($domain, 4);
		} else {
			$nowww = $domain;
			$www   = 'www.' . $domain;
		}

		return [$nowww, $www];
	}

	/**
	 * Check if this is a special loopback request.
	 *
	 * @param null|false|\WP_Site $current_site Current Site.
	 * @param string              $domain Current domain.
	 * @param string              $path   Current Path.
	 *
	 * @return void
	 */
	public function verify_dns_mapping($current_site, $domain, $path) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		// Nonce functions are unavailable and the wp_hash is basically the same.
		if (isset($_REQUEST['async_check_dns_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// This is very early in the request we need these to use wp_hash.
			require_once ABSPATH . WPINC . '/l10n.php';
			require_once ABSPATH . WPINC . '/pluggable.php';
			if (hash_equals(wp_hash($domain), $_REQUEST['async_check_dns_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				$domains = $this->get_www_and_nowww_versions($domain);

				$mapping = Domain::get_by_domain($domains);
				if ($mapping) {
					wp_send_json($mapping->to_array());
				}
			}
		}
	}

	/**
	 * Checks if we have a site associated with the domain being accessed
	 *
	 * This method tries to find a site on the network that has a mapping related to the current
	 * domain being accessed. This uses the default WordPress mapping functionality, added on 4.5.
	 *
	 * @since 2.0.0
	 *
	 * @param null|false|\WP_Site $site Site object being searched by path.
	 * @param string              $domain Domain to search for.
	 * @return null|false|\WP_Site
	 */
	public function check_domain_mapping($site, $domain) {

		$this->verify_dns_mapping($site, $domain, '/');
		// Have we already matched? (Allows other plugins to match first)
		if ( ! empty($site)) {
			return $site;
		}

		$domains = $this->get_www_and_nowww_versions($domain);

		$mapping = Domain::get_by_domain($domains);

		if (empty($mapping) || is_wp_error($mapping)) {
			return $site;
		}

		if (has_filter('mercator.use_mapping')) {
			$deprecated_args = [
				$mapping->is_active(),
				$mapping,
				$domain,
			];

			$is_active = apply_filters_deprecated('mercator.use_mapping', $deprecated_args, '2.0.0', 'wu_use_domain_mapping');
		}

		/**
		 * Determine whether a mapping should be used
		 *
		 * Typically, you'll want to only allow active mappings to be used. However,
		 * if you want to use more advanced logic, or allow non-active domains to
		 * be mapped too, simply filter here.
		 *
		 * @param boolean $is_active Should the mapping be treated as active?
		 * @param Domain $mapping Mapping that we're inspecting
		 * @param string $domain
		 */
		$is_active = apply_filters('wu_use_domain_mapping', $mapping->is_active(), $mapping, $domain);

		// Ignore non-active mappings
		if ( ! $is_active) {
			return $site;
		}

		// Store the mapping for later use
		$this->current_mapping = $mapping;

		// Fetch the actual data for the site
		$mapped_site = $mapping->get_site();

		if (empty($mapped_site)) {
			return $site;
		}

		/*
		 * Note: This is only for backwards compatibility with WPMU Domain Mapping,
		 * do not rely on this constant in new code.
		 */
		defined('DOMAIN_MAPPING') || define('DOMAIN_MAPPING', 1); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

		/*
		 * Decide if we use SSL
		 */
		if ($mapping->is_secure()) {
			force_ssl_admin(true);
		}

		$_site = $site;

		if (is_a($mapped_site, '\WP_Site')) {
			$this->original_url = $mapped_site->domain . $mapped_site->path;

			$_site = $mapped_site;
		} elseif (is_a($mapped_site, '\WP_Ultimo\Models\Site')) {
			$this->original_url = $mapped_site->get_domain() . $mapped_site->get_path();

			$_site = $mapped_site->to_wp_site();
		}

		/*
		 * We found a site based on the mapped domain =)
		 */
		return $_site;
	}

	/**
	 * Clear mappings for a site when it's deleted
	 *
	 * @param \WP_Site $site Site being deleted.
	 */
	public function clear_mappings_on_delete($site): void {

		$mappings = Domain::get_by_site($site->blog_id);

		if (empty($mappings)) {
			return;
		}

		foreach ($mappings as $mapping) {
			$error = $mapping->delete();

			if (is_wp_error($error)) {

				// translators: First placeholder is the mapping ID, second is the site ID.
				$message = sprintf(__('Unable to delete mapping %1$d for site %2$d', 'ultimate-multisite'), $mapping->get_id(), $site->blog_id);

				trigger_error(esc_html($message), E_USER_WARNING); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			}
		}
	}

	/**
	 * Register filters for URLs, if we've mapped
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_mapped_filters(): void {

		$current_site = $GLOBALS['current_blog'];

		if ( ! $current_site) {
			return;
		}

		$domain = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']?? ''));

		$domains = $this->get_www_and_nowww_versions($domain);

		$mapping = Domain::get_by_domain($domains);

		if (empty($mapping) || is_wp_error($mapping) || ! $mapping->get_path()) {
			return;
		}

		$this->current_mapping = $mapping;

		add_filter('site_url', [$this, 'mangle_url'], -10, 4);
		add_filter('home_url', [$this, 'mangle_url'], -10, 4);
		add_filter('option_siteurl', [$this, 'mangle_url'], 20);
		add_filter('option_home', [$this, 'mangle_url'], 20);

		add_filter('theme_file_uri', [$this, 'mangle_url']);
		add_filter('stylesheet_directory_uri', [$this, 'mangle_url']);
		add_filter('template_directory_uri', [$this, 'mangle_url']);
		add_filter('plugins_url', [$this, 'mangle_url'], -10, 3);

		add_filter('autoptimize_filter_base_replace_cdn', [$this, 'mangle_url'], 8); // @since 1.8.2 - Fix for Autoptimizer

		// Fix srcset
		add_filter('wp_calculate_image_srcset', [$this, 'fix_srcset']); // @since 1.5.5

		// If on network site, also filter network urls
		if (is_main_site()) {
			add_filter('network_site_url', [$this, 'mangle_url'], -10, 3);
			add_filter('network_home_url', [$this, 'mangle_url'], -10, 3);
		}

		add_filter('jetpack_sync_home_url', [$this, 'mangle_url']);
		add_filter('jetpack_sync_site_url', [$this, 'mangle_url']);

		/**
		 * Some plugins will save URL before the mapping was active
		 * or will build URLs in a different manner that is not included on
		 * the above filters.
		 *
		 * In cases like that, we want to add additional filters.
		 * The second parameter passed is the mangle_url callback.
		 *
		 * We recommend against using this filter directly.
		 * Instead, use the Domain_Mapping::apply_mapping_to_url method.
		 *
		 * @since 2.0.0
		 * @param callable $mangle_url The mangle callable.
		 * @param self $domain_mapper This object.
		 * @return void
		 */
		do_action('wu_domain_mapping_register_filters', [$this, 'mangle_url'], $this);
	}

	/**
	 * Apply the replace URL to URL filters provided by other plugins.
	 *
	 * @since 2.0.0
	 *
	 * @param string|array $hooks List of hooks to apply the callback to.
	 * @return void
	 */
	public static function apply_mapping_to_url($hooks): void {

		add_action(
			'wu_domain_mapping_register_filters',
			function ($callback) use ($hooks) {

				$hooks = (array) $hooks;

				foreach ($hooks as $hook) {
					add_filter($hook, $callback);
				}
			}
		);
	}

	/**
	 * Replaces the URL.
	 *
	 * @param string      $url URL to replace.
	 * @param null|Domain $current_mapping The current mapping.
	 *
	 * @return string
	 * @since 2.0.0
	 */
	public function replace_url($url, $current_mapping = null) {

		if (null === $current_mapping) {
			$current_mapping = $this->current_mapping;
		}

		// If we don't have a valid mapping, return the original URL
		if (! $current_mapping) {
			return $url;
		}

		// Bail if the URL is not a usable string. WordPress filters such as
		// `home_url` and `site_url` can occasionally pass null/false/empty
		// values, and on PHP 8.1+ passing those into preg_quote()/parse_url()
		// emits deprecation notices.
		if (! is_string($url) || '' === $url) {
			return $url;
		}

		// Get the site associated with the mapping
		$path = $current_mapping->get_path();

		// If we don't have a valid site, return the original URL
		if (! $path) {
			return $url;
		}

		// Replace the domain.
		// wp_parse_url not available because this happens very early in the WP loading process.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$domain_base = parse_url($url, PHP_URL_HOST);

		// Relative URLs (no host) cannot be re-mapped — return as-is rather
		// than feeding null into preg_quote() (deprecated on PHP 8.1+).
		if (! is_string($domain_base) || '' === $domain_base) {
			return $url;
		}

		$domain = preg_quote($domain_base, '#') . '(?::\d+)?';

		if ('/' !== $path) {
			$domain = rtrim($domain . '/' . preg_quote(ltrim($path, '/'), '#'), '/');
		}

		$regex       = '#^(\w+://)' . $domain . '#i';
		$mangled     = preg_replace($regex, '${1}' . $current_mapping->get_domain(), $url);

		/*
		 * Another try if we don't need to deal with subdirectory.
		 */
		if ($mangled === $url && $this->current_mapping !== $current_mapping) {
			$domain  = preg_quote($domain_base, '#') . '(?::\d+)?';
			$regex   = '#^(\w+://)' . $domain . '#i';
			$mangled = preg_replace($regex, '${1}' . $current_mapping->get_domain(), $url);
		}

		$mangled = wu_replace_scheme($mangled, $current_mapping->is_secure() || is_ssl() ? 'https://' : 'http://');

		return $mangled;
	}

	/**
	 * Mangle the home URL to give our primary domain
	 *
	 * @param string      $url The complete home URL including scheme and path.
	 * @param string      $path Path relative to the home URL. Blank string if no path is specified.
	 * @param string|null $orig_scheme Scheme to give the home URL context. Accepts 'http', 'https', 'relative' or null.
	 * @param int|null    $site_id Blog ID, or null for the current blog.
	 * @return string Mangled URL
	 */
	public function mangle_url($url, $path = '/', $orig_scheme = '', $site_id = 0) {

		if (empty($site_id)) {
			$site_id = get_current_blog_id();
		}

		$current_mapping = $this->current_mapping;

		// Check if we have a valid mapping for this site
		if (empty($current_mapping) || $current_mapping->get_blog_id() !== $site_id) {
			return $url;
		}

		return $this->replace_url($url);
	}

	/**
	 * Adds a fix to the srcset URLs when we need that domain mapped
	 *
	 * @since 1.5.5
	 * @param array $sources Image source URLs.
	 * @return array
	 */
	public function fix_srcset($sources) {

		// Check if we have a valid mapping
		if (empty($this->current_mapping) || ! $this->current_mapping->get_site()) {
			return $sources;
		}

		foreach ($sources as &$source) {
			$sources[ $source['value'] ]['url'] = $this->replace_url($sources[ $source['value'] ]['url']);
		}

		return $sources;
	}
}
