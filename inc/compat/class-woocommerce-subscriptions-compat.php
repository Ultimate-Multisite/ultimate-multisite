<?php
/**
 * WooCommerce Subscriptions Compatibility Layer
 *
 * Handles WooCommerce Subscriptions staging mode detection
 *
 * @package WP_Ultimo
 * @subpackage Compat/WooCommerce_Subscriptions_Compat
 * @since 2.0.0
 */

namespace WP_Ultimo\Compat;

// Exit if accessed directly
use Psr\Log\LogLevel;

defined('ABSPATH') || exit;

/**
 * Handles WooCommerce Subscriptions staging mode detection
 *
 * When a site's URL changes (through duplication or primary domain mapping),
 * WooCommerce Subscriptions detects the URL change and enters "staging mode",
 * which disables automatic payments and subscription emails. This class resets
 * the stored site URL to match the new site's URL, preventing the staging mode
 * from being triggered.
 *
 * @since 2.0.0
 */
class WooCommerce_Subscriptions_Compat {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('wu_duplicate_site', [$this, 'reset_staging_mode_on_duplication']);

		add_action('wu_domain_became_primary', [$this, 'reset_staging_mode_on_primary_domain_change'], 10, 3);

		add_filter('woocommerce_new_customer_username', [$this, 'woocommerce_new_customer_username_no_space']);
	}

	/**
	 * Resets WooCommerce Subscriptions staging mode after site duplication.
	 *
	 * @since 2.0.0
	 *
	 * @param array $site Info about the duplicated site containing 'site_id'.
	 * @return void
	 */
	public function reset_staging_mode_on_duplication(array $site): void {

		if (! isset($site['site_id'])) {
			return;
		}

		$this->reset_staging_mode((int) $site['site_id']);
	}

	/**
	 * Resets WooCommerce Subscriptions staging mode when a primary domain is set.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Domain $domain  The domain that became primary.
	 * @param int                      $blog_id The blog ID of the affected site.
	 * @param bool                     $was_new Whether this is a newly created domain.
	 * @return void
	 */
	public function reset_staging_mode_on_primary_domain_change($domain, int $blog_id, bool $was_new): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		if (function_exists('wp_is_site_initialized') && ! wp_is_site_initialized($blog_id)) {
			/*
			 * The site was just inserted (wp_insert_site) and its tables do not exist
			 * yet. Defer until wp_initialize_site has created them.
			 */
			add_action(
				'wp_initialize_site',
				function ($new_site) use ($blog_id) {
					if ((int) $new_site->blog_id === $blog_id) {
						$this->reset_staging_mode($blog_id);
					}
				},
				PHP_INT_MAX
			);

			return;
		}

		$this->reset_staging_mode($blog_id);
	}

	/**
	 * Resets WooCommerce Subscriptions staging mode detection for a site.
	 *
	 * @since 2.0.0
	 *
	 * @param int $site_id The ID of the site.
	 * @return void
	 */
	public function reset_staging_mode(int $site_id): void {

		if (! $site_id) {
			return;
		}
		switch_to_blog($site_id);

		try {
			$option_exists = get_option('wc_subscriptions_siteurl');

			if (! $option_exists) {
				return;
			}
			$site_url = get_site_url();

			if (empty($site_url) || ! is_string($site_url)) {
				return;
			}

			$scheme = wp_parse_url($site_url, PHP_URL_SCHEME);

			if (empty($scheme) || ! is_string($scheme)) {
				return;
			}

			/*
			 * Generate the obfuscated key that WooCommerce Subscriptions uses.
			 * It inserts '_[wc_subscriptions_siteurl]_' in the middle of the URL.
			 */
			$scheme_with_separator   = $scheme . '://';
			$site_url_without_scheme = str_replace($scheme_with_separator, '', $site_url);

			if (empty($site_url_without_scheme) || ! is_string($site_url_without_scheme)) {
				return;
			}

			$obfuscated_url = $scheme_with_separator . substr_replace(
				$site_url_without_scheme,
				'_[wc_subscriptions_siteurl]_',
				intval(strlen($site_url_without_scheme) / 2),
				0
			);

			update_option('wc_subscriptions_siteurl', $obfuscated_url);

			delete_option('wcs_ignore_duplicate_siteurl_notice');
		} catch (\Error $e) {
			wu_log_add('site-duplication-errors', $e->getMessage(), LogLevel::ERROR);
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Replace spaces with dots in WooCommerce customer usernames.
	 *
	 * WooCommerce allows spaces in usernames but they prevent future logins.
	 *
	 * @since 2.5.0
	 *
	 * @param string $username The username being created.
	 * @return string The username with spaces replaced by dots.
	 */
	public function woocommerce_new_customer_username_no_space(string $username): string {
		return preg_replace('/\\s/', '.', $username);
	}
}
