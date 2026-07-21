<?php
/**
 * This helper class allow us to keep our external link references
 * in one place for better control; Links are also filterable;
 *
 * @package WP_Ultimo
 * @subpackage Documentation
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * This helper class allow us to keep our external link references
 * in one place for better control; Links are also filterable;
 *
 * @since 2.0.0
 */
class Documentation implements \WP_Ultimo\Interfaces\Singleton {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Holds the links so we can retrieve them later
	 *
	 * @var array|null
	 */
	protected $links;

	/**
	 * Holds the default link
	 *
	 * @var string
	 */
	protected $default_link = 'https://ultimatemultisite.com/docs/';

	/**
	 * Map of WordPress locale prefixes to Docusaurus locale codes.
	 *
	 * @var array<string, string>
	 */
	protected static array $locale_map = [
		'es'    => 'es',
		'fr'    => 'fr',
		'de'    => 'de',
		'pt_BR' => 'pt-BR',
		'ja'    => 'ja',
		'zh_CN' => 'zh-Hans',
		'ru'    => 'ru',
		'it'    => 'it',
		'ko'    => 'ko',
		'nl'    => 'nl',
	];

	/**
	 * Schedule the default links after WordPress finishes loading plugins.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('init', [$this, 'setup_links'], 0);
	}

	/**
	 * Set the default links.
	 *
	 * The links are also initialized lazily for callers that request
	 * documentation before the init hook runs.
	 *
	 * @since 2.15.0
	 * @return void
	 */
	public function setup_links(): void {

		if (is_array($this->links)) {
			return;
		}

		$base = $this->get_docs_base_url();

		$this->default_link = $base;

		$links = [];

		// Ultimate Multisite Dashboard
		$links['wp-ultimo'] = $base;

		// Settings Page
		$links['wp-ultimo-settings'] = $base;

		// Checkout Pages
		$links['wp-ultimo-checkout-forms']         = $base . 'user-guide/configuration/checkout-forms';
		$links['wp-ultimo-edit-checkout-form']     = $base . 'user-guide/configuration/checkout-forms';
		$links['wp-ultimo-populate-site-template'] = $base . 'user-guide/configuration/site-templates';

		// Products
		$links['wp-ultimo-products']     = $base . 'user-guide/configuration/creating-your-first-subscription-product';
		$links['wp-ultimo-edit-product'] = $base . 'user-guide/configuration/creating-your-first-subscription-product';

		// Memberships
		$links['wp-ultimo-memberships']     = $base . 'user-guide/administration/managing-memberships';
		$links['wp-ultimo-edit-membership'] = $base . 'user-guide/administration/managing-memberships';

		// Payments
		$links['wp-ultimo-payments']     = $base . 'user-guide/administration/managing-payments-and-invoices';
		$links['wp-ultimo-edit-payment'] = $base . 'user-guide/administration/managing-payments-and-invoices';

		// WP Config Closte Instructions
		$links['wp-ultimo-closte-config'] = $base . 'user-guide/host-integrations/closte';

		// Requirements
		$links['wp-ultimo-requirements'] = $base . 'user-guide/getting-started/requirements';

		// Installer - Migrator
		$links['installation-errors'] = $base . 'user-guide/troubleshooting/sunrise-file-error';
		$links['migration-errors']    = $base . 'user-guide/migration/migrating-from-v1';

		// Multiple Accounts
		$links['multiple-accounts'] = $base . 'user-guide/configuration/customizing-your-registration-form';

		$this->links = $links;
		$this->links = apply_filters('wu_documentation_links_list', $this->links);
	}

	/**
	 * Get the locale-aware base URL for the documentation site.
	 *
	 * @since 2.3.0
	 * @return string
	 */
	protected function get_docs_base_url(): string {

		$base = 'https://ultimatemultisite.com/docs/';

		$cookies_ready = defined('AUTH_COOKIE') && defined('SECURE_AUTH_COOKIE') && defined('LOGGED_IN_COOKIE');
		$wp_locale     = $cookies_ready ? determine_locale() : get_locale();

		// Try exact match first (e.g., pt_BR)
		if (isset(self::$locale_map[ $wp_locale ])) {
			return $base . self::$locale_map[ $wp_locale ] . '/';
		}

		// Try language-only match (e.g., es_ES -> es, fr_FR -> fr)
		$lang = substr($wp_locale, 0, 2);

		if (isset(self::$locale_map[ $lang ])) {
			return $base . self::$locale_map[ $lang ] . '/';
		}

		// Default to English (no locale prefix)
		return $base;
	}

	/**
	 * Checks if a link exists.
	 *
	 * @since 2.0.0
	 *
	 * @param  string $slug The slug of the link to be returned.
	 * @return boolean
	 */
	public function has_link($slug) {

		return (bool) $this->get_link($slug, false);
	}

	/**
	 * Retrieves a link registered
	 *
	 * @since 1.7.0
	 * @param  string $slug The slug of the link to be returned.
	 * @param  bool   $return_default If we should return a default value.
	 * @return string
	 */
	public function get_link($slug, $return_default = true) {

		$this->setup_links();

		$default = $return_default ? $this->default_link : false;

		$link = wu_get_isset($this->links, $slug, $default);

		/**
		 * Allow plugin developers to filter the links.
		 * Not sure how that could be useful, but it doesn't hurt to have it
		 *
		 * @since 1.7.0
		 * @param string $link         The link registered
		 * @param string $slug         The slug used to retrieve the link
		 * @param string $default_link The default link registered
		 */
		return apply_filters('wu_documentation_get_link', $link, $slug, $this->default_link);
	}

	/**
	 * Add a new link to the list of links available for reference
	 *
	 * @since 2.0.0
	 * @param string $slug The slug of a new link.
	 * @param string $link The documentation link.
	 * @return void
	 */
	public function register_link($slug, $link): void {

		$this->setup_links();

		$this->links[ $slug ] = $link;
	}
}
