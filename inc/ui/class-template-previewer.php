<?php
/**
 * Adds the Template Previewer code.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use WP_Ultimo\Database\Sites\Site_Type;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Template_Previewer UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Template_Previewer {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Keeps a list of the available templates for the products selected.
	 *
	 * @since 2.0.11
	 * @var null|array
	 */
	protected $available_templates = null;

	/**
	 * Keeps the settings key for the top-bar.
	 */
	const KEY = 'top_bar_settings';

	/**
	 * Initializes the class.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('plugins_loaded', [$this, 'hooks']);
	}

	/**
	 * Hooks into WordPress to add the template preview.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function hooks(): void {

		add_action('template_redirect', [$this, 'send_cross_origin_headers'], 1000);

		if ($this->is_preview()) {
			/*
			 * Remove admin bar from logged users.
			 */
			add_filter('show_admin_bar', '__return_false');

			add_filter('wu_is_jumper_enabled', '__return_false');

			add_filter('wu_is_toolbox_enabled', '__return_false');

			return;
		}

		if ($this->is_template_previewer()) {
			add_action('init', [$this, 'template_previewer']);

			add_action('wp_enqueue_scripts', [$this, 'register_scripts']);

			add_action('wp_print_styles', [$this, 'remove_unnecessary_styles'], 0);

			/**
			 * Runs when inside the template previewer context.
			 *
			 * @since 2.0.4
			 * @param self $template_previewer Instance of the current class.
			 */
			do_action('wu_template_previewer', $this);
		}
	}

	/**
	 * Send the cross origin headers to allow iframes to be loaded.
	 *
	 * @since 2.0.9
	 * @return void
	 */
	public function send_cross_origin_headers(): void {

		$site = wu_get_current_site();
		if (Site_Type::SITE_TEMPLATE !== $site->get_type()) {
			return;
		}

		global $current_site;

		$_SERVER['HTTP_ORIGIN'] = set_url_scheme("http://{$current_site->domain}");

		send_origin_headers();

		// Get the main site domain for frame-ancestors (where the template previewer iframe is embedded)
		$main_site_url    = get_site_url(get_main_site_id());
		$main_site_domain = wp_parse_url($main_site_url, PHP_URL_HOST);

		if (strpos($main_site_domain, 'www.') === 0) {
			$main_site_domain = str_replace('www.', '', $main_site_domain);
		}

		$current_site_info = wp_parse_url(get_site_url());
		$current_site_host = $current_site_info['host'];
		$allowed_domains   = set_url_scheme("*.$main_site_domain $main_site_domain $current_site_host");

		// Check if CSP header already exists
		$existing_csp = '';
		foreach (headers_list() as $header) {
			if (stripos($header, 'Content-Security-Policy:') === 0) {
				$existing_csp = trim(substr($header, strlen('Content-Security-Policy:')));
				break;
			}
		}

		if ($existing_csp) {
			// Parse existing CSP and add/modify frame-ancestors
			$directives            = array_map('trim', explode(';', $existing_csp));
			$frame_ancestors_found = false;

			foreach ($directives as $key => $directive) {
				if (stripos($directive, 'frame-ancestors') === 0) {
					// Modify existing frame-ancestors directive
					$directives[ $key ]    = "frame-ancestors 'self' {$allowed_domains}";
					$frame_ancestors_found = true;
					break;
				}
			}
			if ( ! $frame_ancestors_found) {
				// Add frame-ancestors directive
				$directives[] = "frame-ancestors 'self' {$allowed_domains}";
			}
			$new_csp = implode('; ', array_filter($directives));
		} else {
			// Create new CSP with frame-ancestors only
			$new_csp = "frame-ancestors 'self' $allowed_domains";
		}

		header("Content-Security-Policy: {$new_csp}");
	}

	/**
	 * Register the necessary scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		global $current_site;

		$settings = $this->get_settings();

		$bg_color         = wu_color($settings['bg_color']);
		$button_bg_color  = wu_color($settings['button_bg_color']);
		$button_bg_darker = wu_color($button_bg_color->darken(4));

		wp_register_script('wu-template-previewer', wu_get_asset('template-previewer.js', 'js'), [], wu_get_version(), true);

		wp_localize_script(
			'wu-template-previewer',
			'wu_template_previewer',
			[
				'domain'           => str_replace('www.', '', (string) $current_site->domain),
				'current_template' => wu_request($this->get_preview_parameter(), false),
				'current_url'      => wu_get_current_url(),
				'query_parameter'  => $this->get_preview_parameter(),
			]
		);

		wp_enqueue_script('wu-template-previewer');

		wp_enqueue_style('wu-template-previewer', wu_get_asset('template-previewer.css', 'css'), [], wu_get_version());

		wp_add_inline_style(
			'wu-template-previewer',
			wu_get_template_contents(
				'dynamic-styles/template-previewer',
				[
					'bg_color'        => $bg_color,
					'button_bg_color' => $button_bg_color,
				]
			)
		);

		wp_enqueue_style('dashicons');
	}

	/**
	 * Remove the unnecessary styles added by themes and other plugins.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function remove_unnecessary_styles(): void {

		global $wp_styles;

		$wp_styles->queue = [
			'wu-admin',
			'wu-template-previewer',
			'dashicons',
		];
	}

	/**
	 * Returns the preview URL for the template previewer.
	 *
	 * @since 2.0.0
	 *
	 * @param int $site_id The ID of the template site.
	 * @return string
	 */
	public function get_preview_url($site_id) {

		$args = [
			$this->get_preview_parameter() => $site_id,
		];

		if (wu_request('open')) {
			$args['open'] = 1;
		}

		return add_query_arg($args, home_url());
	}

	/**
	 * Template Previewer code
	 *
	 * @since 1.5.5
	 * @return void
	 */
	public function template_previewer(): void {

		global $current_site;

		$template_value = wu_request($this->get_preview_parameter(), false);

		$selected_template = wu_get_site($template_value);

		/**
		 * Check if this is a site template
		 */
		if ( ! $selected_template || ($selected_template->get_type() !== Site_Type::SITE_TEMPLATE && ! wu_request('customizer'))) {
			wp_die(esc_html__('This template is not available', 'ultimate-multisite'));
		}

		$categories = [];

		$settings = $this->get_settings();

		$render_parameters = [
			'current_site'      => $current_site,
			'categories'        => $categories,
			'selected_template' => $selected_template,
			'tp'                => $this,
		];

		$products_ids = isset($_COOKIE['wu_selected_products']) ? explode(',', sanitize_text_field(wp_unslash($_COOKIE['wu_selected_products']))) : [];

		$products = array_map('wu_get_product', $products_ids);

		// clear array
		$products = array_filter($products);

		if ( ! empty($products)) {
			$limits = new \WP_Ultimo\Objects\Limitations();

			[$plan, $additional_products] = wu_segregate_products($products);

			$products = array_filter(array_merge([$plan], $additional_products));

			foreach ($products as $product) {
				$limits = $limits->merge($product->get_limitations());
			}

			if ($limits->site_templates->get_mode() !== 'default') {
				$site_ids = $limits->site_templates->get_available_site_templates();

				$render_parameters['templates'] = array_map('wu_get_site', $site_ids);

				/**
				 * Check if the current site is a member of
				 * the list of available templates
				 */
				if ( ! in_array($selected_template->get_id(), $site_ids, true)) {
					$redirect_to = wu_get_current_url();

					$redirect_to = add_query_arg($this->get_preview_parameter(), current($site_ids), $redirect_to);

					wp_redirect($redirect_to);

					exit;
				}
			}
		}

		if ( ! isset($render_parameters['templates'])) {
			$render_parameters['templates'] = wu_get_site_templates();
		}

		$render_parameters['templates'] = array_filter((array) $render_parameters['templates'], fn($site) => $site->is_active());

		$render_parameters = array_merge($render_parameters, $settings);

		wu_get_template('ui/template-previewer', $render_parameters);

		exit;
	}

	/**
	 * Returns the preview parameter, so admins can change it.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_preview_parameter() {

		$slug = $this->get_setting('preview_url_parameter', 'template-preview');

		return apply_filters('wu_get_template_preview_slug', $slug);
	}

	/**
	 * Checks if this is a template previewer window.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_template_previewer() {

		$slug = $this->get_preview_parameter();

		return wu_request($slug);
	}

	/**
	 * Check if the frame is a preview.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_preview() {

		return isset($_SERVER['HTTP_SEC_FETCH_DEST']) && 'iframe' === $_SERVER['HTTP_SEC_FETCH_DEST'];
	}

	/**
	 * Returns the settings.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_settings() {

		// Fix to issue on wp_get_attachment_url() inside core.
		// @todo report it.
		$initial_pagenow    = $GLOBALS['pagenow'] ?? '';
		$GLOBALS['pagenow'] = '';

		$default_settings = [
			'bg_color'                    => '#f9f9f9',
			'button_bg_color'             => '#00a1ff',
			'logo_url'                    => wu_get_network_logo(),
			'button_text'                 => __('Use this Template', 'ultimate-multisite'),
			'preview_url_parameter'       => 'template-preview',
			'display_responsive_controls' => true,
			'use_custom_logo'             => false,
			'custom_logo'                 => false,
			'enabled'                     => true,
		];

		$saved_settings = wu_get_option(self::KEY, []);

		$default_settings = array_merge($default_settings, $saved_settings);

		$server_request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification

		// Ensure that templates key does not change with request
		if (isset($server_request['templates'])) {
			unset($server_request['templates']);
		}

		$parsed_args = wp_parse_args($server_request, $default_settings);

		$parsed_args['display_responsive_controls'] = wu_string_to_bool($parsed_args['display_responsive_controls']);
		$parsed_args['use_custom_logo']             = wu_string_to_bool($parsed_args['use_custom_logo']);

		$GLOBALS['pagenow'] = $initial_pagenow;
		return $parsed_args;
	}

	/**
	 * Gets a particular setting.
	 *
	 * @since 2.0.0
	 *
	 * @param string $setting The setting key.
	 * @param mixed  $default_value Default value, if it is not found.
	 *
	 * @return mixed
	 */
	public function get_setting($setting, $default_value = false) {
		$settings = wu_get_option(self::KEY, []);
		return wu_get_isset($settings, $setting, $default_value);
	}

	/**
	 * Save settings.
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings_to_save List of settings to save.
	 * @return boolean
	 */
	public function save_settings($settings_to_save) {

		$settings = $this->get_settings();

		foreach ($settings as $setting => $value) {
			if ('logo_url' === $setting) {
				$settings['logo_url'] = wu_get_network_logo();

				continue;
			}

			$settings[ $setting ] = wu_get_isset($settings_to_save, $setting, false);
		}

		return wu_save_option(self::KEY, $settings);
	}
}
