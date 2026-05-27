<?php
/**
 * Footer Credits handler.
 *
 * @package WP_Ultimo
 * @subpackage Credits
 * @since 2.4.5
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Database\Sites\Site_Type;

/**
 * Handles optional display of "Powered by" credits.
 *
 * - Opt-in via settings and setup wizard (default OFF).
 * - Optional custom HTML for the credit text.
 * - Optional allowance for per-site removal.
 */
class Credits {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Boot hooks.
	 */
	public function init(): void {
		// Register settings into the General section so they show in wizard + settings page.
		add_action('init', [$this, 'register_settings'], 20);

		// Hook admin footer replacement.
		add_filter('admin_footer_text', [$this, 'filter_admin_footer_text'], 100);
		add_filter('update_footer', [$this, 'filter_update_footer_text'], 100);

		// Hook front-end/footer rendering.
		add_action('wp_footer', [$this, 'render_frontend_footer'], 100);
		add_action('login_footer', [$this, 'render_frontend_footer'], 100);
	}

	/**
	 * Register settings controls.
	 */
	public function register_settings(): void {
		// Header
		wu_register_settings_field(
			'general',
			'footer_credits_header',
			[
				'title' => __('Footer Credits', 'ultimate-multisite'),
				'desc'  => __('Optional footer credit for public site and admin.', 'ultimate-multisite'),
				'type'  => 'header',
			],
			2000
		);

		// Enable/disable powered by (global)
		wu_register_settings_field(
			'general',
			'credits_enable',
			[
				'title'   => __('Show Footer Credits', 'ultimate-multisite'),
				'desc'    => __('Adds a small "Powered By..." message in the footer of customer and template sites.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			],
			2010
		);

		// Footer credit type selection
		wu_register_settings_field(
			'general',
			'credits_type',
			[
				'title'   => __('Footer Credit Type', 'ultimate-multisite'),
				'desc'    => __('Choose the type of footer credit to display.', 'ultimate-multisite'),
				'type'    => 'select',
				'options' => [
					'default' => __('Default "Powered by Ultimate Multisite" with logo', 'ultimate-multisite'),
					'custom'  => __('Custom "Powered by [Network Name]" with company logo', 'ultimate-multisite'),
					'html'    => __('Custom HTML (enter below)', 'ultimate-multisite'),
				],
				'default' => 'default',
				'require' => [
					'credits_enable' => 1,
				],
			],
			2020
		);

		// Custom HTML text (only for html option)
		wu_register_settings_field(
			'general',
			'credits_custom_html',
			[
				'title'         => __('Custom Footer HTML', 'ultimate-multisite'),
				'desc'          => __('HTML allowed. Use any text or link you prefer.', 'ultimate-multisite'),
				'type'          => 'textarea',
				'allow_html'    => true,
				'default'       => [$this, 'get_default_custom_credit_html'],
				'value'         => function () {
					return $this->normalize_custom_credit_html(
						wu_get_setting('credits_custom_html', $this->get_default_custom_credit_html())
					);
				},
				'display_value' => function () {
					return $this->normalize_custom_credit_html(
						wu_get_setting('credits_custom_html', $this->get_default_custom_credit_html())
					);
				},
				'placeholder'   => __('Powered by <a href="https://example.com">Your Company</a>', 'ultimate-multisite'),
				'require'       => [
					'credits_enable' => 1,
					'credits_type'   => 'html',
				],
			],
			2030
		);
	}

	/**
	 * Normalizes a stored custom credit HTML value.
	 *
	 * Returns the default credit HTML when the stored value is the literal
	 * string '[object Object]', which can appear when a Closure leaked into
	 * the Vue/settings JSON state before this bug was fixed.
	 *
	 * @param mixed $html The raw stored value.
	 * @return string
	 */
	protected function normalize_custom_credit_html($html): string {
		$html = is_string($html) ? $html : (string) $html;

		return '[object Object]' === trim($html) ? $this->get_default_custom_credit_html() : $html;
	}

	/**
	 * Returns the default custom credit HTML.
	 *
	 * Public so the array callable `[$this, 'get_default_custom_credit_html']`
	 * registered as a field default (see register_settings() above) resolves
	 * via `is_callable()` when checked from outside this class — for example
	 * in `WP_Ultimo\Settings::save_settings()`, which lives in a different
	 * class scope.
	 *
	 * `is_callable([$instance, 'protected_method'])` returns `false` outside
	 * the declaring class scope. When that happens, `Settings::save_settings()`
	 * never invokes the callable, the literal array `[$instance, 'method-name']`
	 * survives as the field default, and it is later persisted/used as the
	 * field value. For a textarea field this leaks into
	 * `Field::validate_textarea_field()` → `addslashes()`, fatally:
	 *   "Uncaught TypeError: addslashes(): Argument #1 ($string) must be of
	 *   type string, array given".
	 *
	 * The Setup Wizard symptom is twofold: the data-state JSON the Vue form
	 * is initialised from contains the unresolved callable array, so the form
	 * renders with no settings fields; clicking Continue still POSTs and
	 * triggers the fatal on save.
	 */
	public function get_default_custom_credit_html(): string {
		$name = (string) get_network_option(null, 'site_name');
		$name = $name ?: __('this network', 'ultimate-multisite');
		$url  = is_multisite() ? get_site_url(get_main_site_id()) : network_home_url('/');

		return sprintf(
			/* translators: 1: Opening anchor tag, 2: Network name, 3: Closing anchor tag. */
			__('Powered by %1$s%2$s%3$s', 'ultimate-multisite'),
			'<a href="' . esc_url($url) . '" target="_blank">',
			esc_html($name),
			'</a>'
		);
	}

	/**
	 * Build the credit text (HTML) based on settings.
	 */
	protected function build_credit_html(): string {
		$enabled = (bool) wu_get_setting('credits_enable', 0);
		if (! $enabled) {
			return '';
		}

		$type = wu_get_setting('credits_type', 'default');

		switch ($type) {
			case 'custom':
				return $this->build_custom_credit();

			case 'html':
				$html = $this->normalize_custom_credit_html(
					wu_get_setting('credits_custom_html', $this->get_default_custom_credit_html())
				);
				return wp_kses_post($html);

			default:
				return $this->build_default_credit();
		}
	}

	/**
	 * Build the default "Powered by Ultimate Multisite" credit with logo.
	 */
	protected function build_default_credit(): string {
		$logo_html = $this->get_plugin_logo_html();
		$text      = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url('https://ultimatemultisite.com'),
			esc_html__('Ultimate Multisite', 'ultimate-multisite')
		);

		if ($logo_html) {
			return $logo_html . esc_html__('Powered by', 'ultimate-multisite') . ' ' . $text;
		}

		return esc_html__('Powered by', 'ultimate-multisite') . ' ' . $text;
	}

	/**
	 * Build the custom "Powered by [Network Name]" credit with company logo.
	 */
	protected function build_custom_credit(): string {
		$logo_html    = $this->get_company_logo_html();
		$network_name = (string) get_network_option(null, 'site_name');
		$network_name = $network_name ?: __('this network', 'ultimate-multisite');
		$network_url  = is_multisite() ? get_site_url(get_main_site_id()) : network_home_url('/');

		$text = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url($network_url),
			esc_html($network_name)
		);

		if ($logo_html) {
			return $logo_html . esc_html__('Powered by', 'ultimate-multisite') . ' ' . $text;
		}

		return esc_html__('Powered by', 'ultimate-multisite') . ' ' . $text;
	}

	/**
	 * Get the Ultimate Multisite plugin logo HTML.
	 */
	protected function get_plugin_logo_html(): string {
		$logo_url = wu_get_asset('badge.webp', 'img');
		if (! $logo_url) {
			return '';
		}

		return sprintf(
			'<img src="%s" alt="%s" style="height: 30px; width: 30px; vertical-align: middle; margin-right: 6px;" />',
			esc_url($logo_url),
			esc_attr__('Ultimate Multisite', 'ultimate-multisite')
		);
	}

	/**
	 * Get the company logo HTML from settings.
	 */
	protected function get_company_logo_html(): string {
		$logo_url = wu_get_network_logo('thumbnail');

		$company_name = wu_get_setting('company_name', get_network_option(null, 'site_name'));

		return sprintf(
			'<img src="%s" alt="%s" style="height: 30px; width: auto; vertical-align: middle; margin-right: 6px;" />',
			esc_url($logo_url),
			esc_attr($company_name ?: __('Company Logo', 'ultimate-multisite'))
		);
	}

	/**
	 * Check if current site is allowed to show footer credit.
	 *
	 * Sites can hide credits if their membership/product has the 'hide_credits' limit enabled.
	 */
	protected function site_allows_credit(): bool {
		// Check if the site has the limitation to hide footer credits
		$site = function_exists('wu_get_current_site') ? \wu_get_current_site() : null;
		if (! $site || ! in_array($site->get_type(), [Site_Type::CUSTOMER_OWNED, Site_Type::SITE_TEMPLATE], true)) {
			return false;
		}
		if ($site->has_limitations()) {
			$limitations = $site->get_limitations();

			// If the hide_footer_credits limit is enabled and set to true, the site can hide credits
			if ($limitations->hide_credits && $limitations->hide_credits->allowed(true)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Admin footer replacement.
	 *
	 * Only show on customer-owned site admins (not network admin or main site admin).
	 *
	 * @param string $text Default footer text.
	 * @return string
	 */
	public function filter_admin_footer_text($text): string {
		$text = is_string($text) ? $text : '';
		if (is_network_admin()) {
			return $text;
		}

		$site = function_exists('wu_get_current_site') ? \wu_get_current_site() : null;
		if (! $site || ($site->get_type() !== Site_Type::CUSTOMER_OWNED)) {
			return $text;
		}

		$credit = $this->build_credit_html();
		if ($credit && $this->site_allows_credit()) {
			return $credit;
		}
		return $text;
	}

	/**
	 * Remove default update footer text when our credit is enabled.
	 *
	 * @param string $text Default Text.
	 */
	public function filter_update_footer_text($text): string {
		$text = is_string($text) ? $text : '';
		if (is_network_admin()) {
			return $text;
		}

		$site = function_exists('wu_get_current_site') ? \wu_get_current_site() : null;
		if (! $site || ($site->get_type() !== Site_Type::CUSTOMER_OWNED)) {
			return $text;
		}

		$enabled = (bool) wu_get_setting('credits_enable', 0);
		if ($enabled && $this->site_allows_credit()) {
			return '';
		}
		return $text;
	}

	/**
	 * Front-end footer output (appended near wp_footer).
	 */
	public function render_frontend_footer(): void {
		if (is_admin()) {
			return;
		}

		if (! $this->site_allows_credit()) {
			return;
		}

		$credit = $this->build_credit_html();
		if (! $credit) {
			return;
		}
		echo '<div class="wu-powered-by" style="text-align:center;font-size:14px;margin:14px 0;">' . $credit . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
