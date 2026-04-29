<?php
/**
 * Ultimate Multisite settings helper class.
 *
 * @package WP_Ultimo
 * @subpackage Settings
 * @since 2.0.0
 */

namespace WP_Ultimo;

use WP_Ultimo\Checkout\Checkout_Pages;
use WP_Ultimo\UI\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite settings helper class.
 *
 * @since 2.0.0
 */
class Settings implements \WP_Ultimo\Interfaces\Singleton {

	use \WP_Ultimo\Traits\Singleton;
	use \WP_Ultimo\Traits\WP_Ultimo_Settings_Deprecated;

	/**
	 * Keeps the key used to access settings.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const KEY = 'v2_settings';

	/**
	 * Holds the array containing all the saved settings.
	 *
	 * @since 2.0.0
	 * @var array|null
	 */
	private ?array $settings = null;

	/**
	 * Holds the sections of the settings page.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private ?array $sections = null;

	/**
	 * @var bool
	 */
	private bool $saving = false;

	/**
	 * Runs on singleton instantiation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('init', [$this, 'handle_legacy_filters'], 2);

		add_action('wu_render_settings', [$this, 'handle_legacy_scripts']);

		add_filter('pre_site_option_registration', [$this, 'force_registration_status'], 10, 3);

		add_filter('pre_site_option_add_new_users', [$this, 'force_add_new_users'], 10, 3);

		add_filter('site_option_menu_items', [$this, 'force_plugins_menu'], 10, 3);
		add_filter('default_site_option_menu_items', [$this, 'force_plugins_menu'], 10, 3);

		// Persist the registration state into the WP core site option so the
		// Network Admin Settings page reflects reality and registration survives
		// plugin deactivation in the correct state.
		add_action('wu_activation', [$this, 'sync_wp_registration_option']);
		add_action('wu_after_save_settings', [$this, 'sync_wp_registration_option']);
	}

	/**
	 * Change the current status of the registration on WordPress MS.
	 *
	 * @since 2.0.0
	 *
	 * @param string $status The registration status.
	 * @param string $option Option name, in this case, 'registration'.
	 * @param int    $network_id The id of the network being accessed.
	 * @return string
	 */
	public function force_registration_status($status, $option, $network_id) {

		global $current_site;

		if ($current_site->id !== $network_id) {
			return $status;
		}

		$status = wu_get_setting('enable_registration', true) ? 'all' : $status;

		return $status;
	}

	/**
	 * Persist the registration state into the WordPress core site option.
	 *
	 * The pre_site_option_registration filter already overrides the value at
	 * read-time, but the stored WP core option stays 'none' (the WP default).
	 * This causes the Network Admin Settings page to show "No registrations
	 * allowed" and means registration goes dark if the plugin is deactivated.
	 *
	 * Hooked to wu_activation (one-time on plugin activation) and to
	 * wu_after_save_settings (whenever the settings form is saved) so the
	 * stored value always matches the plugin setting.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public function sync_wp_registration_option() {

		$enabled = wu_get_setting('enable_registration', true);

		update_site_option('registration', $enabled ? 'all' : 'none');
	}

	/**
	 * Change the current status of the add_new network option.
	 *
	 * @since 2.0.0
	 *
	 * @param string $status The add_new_users status.
	 * @param string $option Option name, in this case, 'add_new_user'.
	 * @param int    $network_id The id of the network being accessed.
	 * @return string
	 */
	public function force_add_new_users($status, $option, $network_id) {

		global $current_site;

		if ($current_site->id !== $network_id) {
			return $status;
		}

		return wu_get_setting('add_new_users', true);
	}

	/**
	 * Change the current status of the add_new network option.
	 *
	 * @since 2.0.0
	 *
	 * @param array|bool $status The add_new_users status.
	 * @param string     $option Option name, in this case, 'add_new_user'.
	 * @param int        $network_id The id of the network being accessed.
	 * @return string
	 */
	public function force_plugins_menu($status, $option, $network_id) {

		global $current_site;

		if ($current_site->id !== $network_id || is_bool($status)) {
			return $status;
		}

		$status['plugins'] = wu_get_setting('menu_items_plugin', true);

		return $status;
	}

	/**
	 * Get all the settings from Ultimate Multisite
	 *
	 * @param bool $check_caps If we should remove the settings the user does not have rights to see.
	 * @return array Array containing all the settings
	 */
	public function get_all($check_caps = false) {

		// Get all the settings
		if (null === $this->settings) {
			$this->settings = wu_get_option(self::KEY);
		}

		if (empty($this->settings)) {
			return [];
		}

		if ($check_caps) {} // phpcs:ignore;

		return $this->settings;
	}

	/**
	 * Get all.
	 *
	 * @param bool $check_caps Capability to check.
	 *
	 * @return array
	 */
	public function get_all_with_defaults($check_caps = false) {
		$all_settings = $this->get_all($check_caps);
		foreach ($this->get_sections() as $section_slug => $section) {
			foreach ($section['fields'] ?? [] as $field_slug => $field_atts) {
				if (is_callable($field_atts['value'])) {
					$value = $field_atts['value']();
					if ( ! isset($all_settings[ $field_slug ]) || $value !== $all_settings[ $field_slug ]) {
						$all_settings[ $field_slug ] = $value;
					}
				}
			}
		}
		return $all_settings;
	}

	/**
	 * Get a specific settings from the plugin
	 *
	 * @since  1.1.5 Let's we pass default values in case nothing is found.
	 * @since  1.4.0 Now we can filter settings we get.
	 *
	 * @param  string $setting Settings name to return.
	 * @param  mixed  $default_value Default value for the setting if it doesn't exist.
	 *
	 * @return mixed The value of that setting
	 */
	public function get_setting($setting, $default_value = false) {

		$settings = $this->get_all();

		if (str_contains($setting, '-')) {
			_doing_it_wrong(esc_html($setting), esc_html__('Dashes are no longer supported when registering a setting. You should change it to underscores in later versions.', 'ultimate-multisite'), '2.0.0');
		}

		if (isset($settings[ $setting ])) {
			$setting_value = $settings[ $setting ];
		} elseif (false !== $default_value) {
			$setting_value = $default_value;
		} else {
			$defaults      = static::get_setting_defaults();
			$setting_value = $defaults[ $setting ] ?? false;
		}

		return apply_filters('wu_get_setting', $setting_value, $setting, $default_value, $settings);
	}

	/**
	 * Saves a specific setting into the database
	 *
	 * @param string $setting Option key to save.
	 * @param mixed  $value   New value of the option.
	 * @return boolean
	 */
	public function save_setting($setting, $value) {

		$settings = $this->get_all();

		$value = apply_filters('wu_save_setting', $value, $setting, $settings);

		if (is_callable($value)) {
			$value = call_user_func($value);
		}

		$settings[ $setting ] = $value;

		$status = wu_save_option(self::KEY, $settings);

		$this->settings = $settings;

		return $status;
	}

	/**
	 * Save Ultimate Multisite Settings
	 *
	 * This function loops through the settings sections and saves the settings
	 * after validating them.
	 *
	 * @since 2.0.0
	 *
	 * @param array   $settings_to_save Array containing the settings to save.
	 * @param boolean $reset If true, Ultimate Multisite will override the saved settings with the default values.
	 * @return array
	 */
	public function save_settings($settings_to_save = [], $reset = false) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		$settings = [];

		$sections = $this->get_sections();

		$saved_settings = $this->get_all();

		do_action('wu_before_save_settings', $settings_to_save);

		foreach ($sections as $section_slug => $section) {
			foreach ($section['fields'] ?? [] as $field_slug => $field_atts) {
				$existing_value = $saved_settings[ $field_slug ] ?? false;

				$field = new Field($field_slug, $field_atts);

				$new_value = $settings_to_save[ $field_slug ] ?? $existing_value;

				/**
				 * For the current tab, we need to assume toggle fields.
				 */
				if (wu_request('tab', 'general') === $section_slug && 'toggle' === $field->type && ! isset($settings_to_save[ $field_slug ])) {
					$new_value = false;
				}

				$value = $new_value;

				$field->set_value($value);

				if ($field->get_value() !== null) {
					$settings[ $field_slug ] = $field->get_value();
				}

				do_action('wu_saving_setting', $field_slug, $field, $settings_to_save);
			}
		}

		/**
		 * Allow developers to filter settings before save by Ultimate Multisite.
		 *
		 * @since 2.0.18
		 *
		 * @param array  $settings         The settings to be saved.
		 * @param array  $settings_to_save The new settings to add.
		 * @param array $saved_settings   The current settings saved.
		 */
		$settings = apply_filters('wu_pre_save_settings', $settings, $settings_to_save, $saved_settings);

		wu_save_option(self::KEY, $settings);

		$this->settings = $settings;

		do_action('wu_after_save_settings', $settings, $settings_to_save, $saved_settings);

		return $settings;
	}

	/**
	 * Returns the list of sections and their respective fields.
	 *
	 * @since 1.1.0
	 * @todo Order sections by the order parameter.
	 * @todo Order fields by the order parameter.
	 * @return array
	 */
	public function get_sections() {

		if ( $this->sections ) {
			return $this->sections;
		}

		$this->default_sections();
		$this->sections = apply_filters(
			'wu_settings_get_sections',
			[

				/*
				 * Add a default invisible section that we can use
				 * to register settings that will not have a control.
				 */
				'core' => [
					'invisible' => true,
					'order'     => 1_000_000,
					'fields'    => apply_filters('wu_settings_section_core_fields', []),
				],
			]
		);

		uasort($this->sections, 'wu_sort_by_order');

		return $this->sections;
	}

	/**
	 * Returns a particular settings section.
	 *
	 * @since 2.0.0
	 *
	 * @param string $section_name The slug of the section to return.
	 * @return array
	 */
	public function get_section($section_name = 'general') {

		$sections = $this->get_sections();

		return wu_get_isset(
			$sections,
			$section_name,
			[
				'fields' => [],
			]
		);
	}

	/**
	 * Adds a new settings section.
	 *
	 * Sections are a way to organize correlated settings into one cohesive unit.
	 * Developers should be able to add their own sections, if they need to.
	 * This is the purpose of this APIs.
	 *
	 * @since 2.0.0
	 *
	 * @param string $section_slug ID of the Section. This is used to register fields to this section later.
	 * @param array  $atts Section attributes such as title, description and so on.
	 * @return void
	 */
	public function add_section($section_slug, $atts): void {

		add_filter(
			'wu_settings_get_sections',
			function ($sections) use ($section_slug, $atts) {

				$default_order = (count($sections) + 1) * 10;

				$atts = wp_parse_args(
					$atts,
					[
						'icon'       => 'dashicons-wu-cog',
						'order'      => $default_order,
						'capability' => 'manage_network',
					]
				);

				$atts['fields'] = apply_filters("wu_settings_section_{$section_slug}_fields", []);

				$sections[ $section_slug ] = $atts;

				return $sections;
			}
		);
	}

	/**
	 * Adds a new field to a settings section.
	 *
	 * Fields are settings that admins can actually change.
	 * This API allows developers to add new fields to a given settings section.
	 *
	 * @since 2.0.0
	 *
	 * @param string $section_slug Section to which this field will be added to.
	 * @param string $field_slug ID of the field. This is used to later retrieve the value saved on this setting.
	 * @param array  $atts Field attributes such as title, description, tooltip, default value, etc.
	 * @param int    $priority Priority of the field. This is used to order the fields.
	 * @return void
	 */
	public function add_field($section_slug, $field_slug, $atts, $priority = 10): void {
		/*
		 * Adds the field to the desired fields array.
		 */
		add_filter(
			"wu_settings_section_{$section_slug}_fields",
			function ($fields) use ($field_slug, $atts) {
				/*
				* We no longer support settings with hyphens.
				*/
				if (str_contains($field_slug, '-')) {
					_doing_it_wrong(esc_html($field_slug), esc_html__('Dashes are no longer supported when registering a setting. You should change it to underscores in later versions.', 'ultimate-multisite'), '2.0.0');
				}

				$default_order = (count($fields) + 1) * 10;

				$atts = wp_parse_args(
					$atts,
					[
						'setting_id'        => $field_slug,
						'title'             => '',
						'desc'              => '',
						'order'             => $default_order,
						'default'           => null,
						'capability'        => 'manage_network',
						'wrapper_html_attr' => [],
						'require'           => [],
						'html_attr'         => [],
						'value'             => fn() => wu_get_setting($field_slug),
						'display_value'     => fn() => wu_get_setting($field_slug),
						'img'               => function () use ($field_slug) {

							$img_id = wu_get_setting($field_slug);

							if ( ! $img_id) {
								return '';
							}

							$custom_logo_args = wp_get_attachment_image_src($img_id, 'full');

							return $custom_logo_args ? $custom_logo_args[0] : '';
						},
					]
				);

				/**
				 * Adds v-model
				 */
				if (wu_get_isset($atts, 'type') !== 'submit') {
					$atts['html_attr']['v-model']     = wu_replace_dashes($field_slug);
					$atts['html_attr']['true-value']  = '1';
					$atts['html_attr']['false-value'] = '0';
				}

				$atts['html_attr']['id'] = $field_slug;

				/**
				 * Handle selectize.
				 */
				$model_name = wu_get_isset($atts['html_attr'], 'data-model');

				if ($model_name) {
					if (function_exists("wu_get_{$model_name}") || 'page' === $model_name) {
						$original_html_attr = $atts['html_attr'];

						$atts['html_attr'] = function () use ($field_slug, $model_name, $atts, $original_html_attr) {

							$value = wu_get_setting($field_slug);

							if ('page' === $model_name) {
								$new_attrs['data-selected'] = get_post($value);
							} else {
								$data_selected              = call_user_func("wu_get_{$model_name}", $value);
								$new_attrs['data-selected'] = $data_selected->to_search_results();
							}

							$new_attrs['data-selected'] = wp_json_encode($new_attrs['data-selected']);

							return array_merge($original_html_attr, $new_attrs);
						};
					}
				}

				if ( ! empty($atts['require'])) {
					$require_rules = [];

					foreach ($atts['require'] as $attr => $value) {
						$attr = str_replace('-', '_', $attr);

						$value = wp_json_encode($value);

						$require_rules[] = "require('{$attr}', {$value})";
					}

					$atts['wrapper_html_attr']['v-show']  = implode(' && ', $require_rules);
					$atts['wrapper_html_attr']['v-cloak'] = 'v-cloak';
				}

				$fields[ $field_slug ] = $atts;

				return $fields;
			},
			$priority
		);
	}

	/**
	 * Register the Ultimate Multisite default sections and fields.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function default_sections(): void {
		/*
		 * General Settings
		 * This section holds the General settings of the Ultimate Multisite Plugin.
		 */

		// Comma separated string of page ids that are already being used as default option
		$filter_default_signup_pages = implode(',', array_filter(Checkout_Pages::get_instance()->get_signup_pages()));

		$this->add_section(
			'general',
			[
				'title' => __('General', 'ultimate-multisite'),
				'desc'  => __('General', 'ultimate-multisite'),
			]
		);

		$this->add_field(
			'general',
			'company_header',
			[
				'title' => __('Your Business', 'ultimate-multisite'),
				'desc'  => __('General information about your business..', 'ultimate-multisite'),
				'type'  => 'header',
			],
			10
		);

		$this->add_field(
			'general',
			'company_name',
			[
				'title'   => __('Company Name', 'ultimate-multisite'),
				'desc'    => __('This name is used when generating invoices, for example.', 'ultimate-multisite'),
				'type'    => 'text',
				'default' => get_network_option(null, 'site_name'),
			],
			20
		);

		$this->add_field(
			'general',
			'company_logo',
			[
				'title'   => __('Upload Company Logo', 'ultimate-multisite'),
				'desc'    => __('Add your company logo to be used on the login page and other places.', 'ultimate-multisite'),
				'type'    => 'image',
				'default' => '',
			],
			30
		);

		$this->add_field(
			'general',
			'company_email',
			[
				'title'   => __('Company Email Address', 'ultimate-multisite'),
				'desc'    => __('This email is used when generating invoices, for example.', 'ultimate-multisite'),
				'type'    => 'text',
				'default' => get_network_option(null, 'admin_email'),
			],
			40
		);

		$this->add_field(
			'general',
			'company_address',
			[
				'title'       => __('Company Address', 'ultimate-multisite'),
				'desc'        => __('This address is used when generating invoices.', 'ultimate-multisite'),
				'type'        => 'textarea',
				'placeholder' => "350 Fifth Avenue\nManhattan, \nNew York City, NY \n10118",
				'default'     => '',
				'html_attr'   => [
					'rows' => 5,
				],
			],
			50
		);

		$this->add_field(
			'general',
			'company_country',
			[
				'title'   => __('Company Country', 'ultimate-multisite'),
				'desc'    => __('This info is used when generating invoices, as well as for calculating when taxes apply in some contexts.', 'ultimate-multisite'),
				'type'    => 'select',
				'options' => 'wu_get_countries',
				'default' => [$this, 'get_default_company_country'],
			],
			60
		);

		$this->add_field(
			'general',
			'currency_header',
			[
				'title' => __('Currency Options', 'ultimate-multisite'),
				'desc'  => __('The following options affect how prices are displayed on the frontend, the backend and in reports.', 'ultimate-multisite'),
				'type'  => 'header',
			],
			70
		);

		$this->add_field(
			'general',
			'currency_symbol',
			[
				'title'   => __('Currency', 'ultimate-multisite'),
				'desc'    => __('Select the currency to be used in Ultimate Multisite.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'USD',
				'options' => 'wu_get_currencies',
			],
			80
		);

		$this->add_field(
			'general',
			'currency_position',
			[
				'title'   => __('Currency Position', 'ultimate-multisite'),
				'desc'    => __('This setting affects all prices displayed across the plugin elements.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => '%s %v',
				'options' => [
					'%s%v'  => __('Left ($99.99)', 'ultimate-multisite'),
					'%v%s'  => __('Right (99.99$)', 'ultimate-multisite'),
					'%s %v' => __('Left with space ($ 99.99)', 'ultimate-multisite'),
					'%v %s' => __('Right with space (99.99 $)', 'ultimate-multisite'),
				],
			],
			90
		);

		$this->add_field(
			'general',
			'decimal_separator',
			[
				'title'   => __('Decimal Separator', 'ultimate-multisite'),
				'desc'    => __('This setting affects all prices displayed across the plugin elements.', 'ultimate-multisite'),
				'type'    => 'text',
				'default' => '.',
			],
			100
		);

		$this->add_field(
			'general',
			'thousand_separator',
			[
				'title'   => __('Thousand Separator', 'ultimate-multisite'),
				'desc'    => __('This setting affects all prices displayed across the plugin elements.', 'ultimate-multisite'),
				'type'    => 'text',
				'default' => ',',
				'raw'     => true,
			],
			110
		);

		$this->add_field(
			'general',
			'precision',
			[
				'title'   => __('Number of Decimals', 'ultimate-multisite'),
				'desc'    => __('This setting affects all prices displayed across the plugin elements.', 'ultimate-multisite'),
				'type'    => 'number',
				'default' => '2',
				'min'     => 0,
			],
			120
		);

		$this->add_field(
			'general',
			'enable_error_reporting',
			[
				'title'   => __('Help Improve Ultimate Multisite', 'ultimate-multisite'),
				'desc'    => sprintf(
				/* translators: %s is a link to the privacy policy */
					__('Allow Ultimate Multisite to collect anonymous usage data and error reports to help us improve the plugin. We collect: PHP version, WordPress version, plugin version, network type (subdomain/subdirectory), aggregate counts (sites, memberships), active gateways, and error logs. We never collect personal data, customer information, or domain names. <a href="%s" target="_blank" rel="noopener noreferrer">Learn more</a>.', 'ultimate-multisite'),
					esc_url('https://ultimatemultisite.com/privacy-policy/')
				),
				'type'    => 'toggle',
				'default' => 0,
			],
			130
		);

		$this->add_field(
			'general',
			'enable_beta_updates',
			[
				'title'   => __('Beta Updates', 'ultimate-multisite'),
				'desc'    => __('Opt in to receive pre-release versions of Ultimate Multisite and its add-ons. Beta versions may contain bugs or incomplete features.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			],
			135
		);

		/*
		 * Login & Registration
		 * This section holds the Login & Registration settings of the Ultimate Multisite Plugin.
		 */

		$this->add_section(
			'login-and-registration',
			[
				'title' => __('Login & Registration', 'ultimate-multisite'),
				'desc'  => __('Login & Registration', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-key',
			]
		);

		$this->add_field(
			'login-and-registration',
			'registration_header',
			[
				'title' => __('Login and Registration Options', 'ultimate-multisite'),
				'desc'  => __('Options related to registration and login behavior.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$this->add_field(
			'login-and-registration',
			'enable_registration',
			[
				'title'   => __('Enable Registration', 'ultimate-multisite'),
				'desc'    => __('Turning this toggle off will disable registration in all checkout forms across the network.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'login-and-registration',
			'enable_email_verification',
			[
				'title'   => __('Email verification', 'ultimate-multisite'),
				'desc'    => __('Controls if email verification is required during registration. If set, sites will not be created until the customer email verification status is changed to verified.', 'ultimate-multisite'),
				'type'    => 'select',
				'options' => [
					'never'     => __('Never require email verification', 'ultimate-multisite'),
					'free_only' => __('Only for free plans', 'ultimate-multisite'),
					'always'    => __('Always require email verification', 'ultimate-multisite'),
				],
				'default' => 'free_only',
				'value'   => function () {
					$raw = wu_get_setting('enable_email_verification', 'free_only');
					if (1 === $raw || '1' === $raw || true === $raw) {
							return 'free_only'; // legacy "enabled"
					}
					if (0 === $raw || '0' === $raw || false === $raw) {
							return 'never'; // legacy "disabled"
					}
					return in_array($raw, ['never', 'free_only', 'always'], true) ? $raw : 'free_only';
				},
			]
		);

		$this->add_field(
			'login-and-registration',
			'default_registration_page',
			[
				'type'        => 'model',
				'title'       => __('Default Registration Page', 'ultimate-multisite'),
				'placeholder' => __('Search pages on the main site...', 'ultimate-multisite'),
				'desc'        => __('Only published pages on the main site are available for selection, and you need to make sure they contain a [wu_checkout] shortcode.', 'ultimate-multisite'),
				'tooltip'     => '',
				'html_attr'   => [
					'data-base-link'    => get_admin_url(wu_get_main_site_id(), 'post.php?action=edit&post'),
					'data-model'        => 'page',
					'data-value-field'  => 'ID',
					'data-label-field'  => 'post_title',
					'data-search-field' => 'post_title',
					'data-max-items'    => 1,
					'data-exclude'      => $filter_default_signup_pages,
				],
			]
		);

		$this->add_field(
			'login-and-registration',
			'enable_custom_login_page',
			[
				'title'   => __('Use Custom Login Page', 'ultimate-multisite'),
				'desc'    => __('Turn this toggle on to select a custom page to be used as the login page.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$this->add_field(
			'login-and-registration',
			'default_login_page',
			[
				'type'        => 'model',
				'title'       => __('Default Login Page', 'ultimate-multisite'),
				'placeholder' => __('Search pages on the main site...', 'ultimate-multisite'),
				'desc'        => __('Only published pages on the main site are available for selection, and you need to make sure they contain a [wu_login_form] shortcode.', 'ultimate-multisite'),
				'tooltip'     => '',
				'html_attr'   => [
					'data-base-link'    => get_admin_url(wu_get_main_site_id(), 'post.php?action=edit&post'),
					'data-model'        => 'page',
					'data-value-field'  => 'ID',
					'data-label-field'  => 'post_title',
					'data-search-field' => 'post_title',
					'data-max-items'    => 1,
				],
				'require'     => [
					'enable_custom_login_page' => true,
				],
			]
		);

		$this->add_field(
			'login-and-registration',
			'obfuscate_original_login_url',
			[
				'title'   => __('Obfuscate the Original Login URL (wp-login.php)', 'ultimate-multisite'),
				'desc'    => __('If this option is enabled, we will display a 404 error when a user tries to access the original wp-login.php link. This is useful to prevent brute-force attacks.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
				'require' => [
					'enable_custom_login_page' => 1,
				],
			]
		);

		$this->add_field(
			'login-and-registration',
			'subsite_custom_login_logo',
			[
				'title'   => __('Use Sub-site logo on Login Page', 'ultimate-multisite'),
				'desc'    => __('Toggle this option to replace the WordPress logo on the sub-site login page with the logo set for that sub-site. If unchecked, the network logo will be used instead.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
				'require' => [
					'enable_custom_login_page' => 0,
				],
			]
		);

		$this->add_field(
			'login-and-registration',
			'force_publish_sites_sync',
			[
				'title'   => __('Force Synchronous Site Publication', 'ultimate-multisite'),
				'desc'    => __('By default, when a new pending site needs to be converted into a real network site, the publishing process happens via Job Queue, asynchronously. Enable this option to force the publication to happen in the same request as the signup. Be careful, as this can cause timeouts depending on the size of the site templates being copied.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$this->add_field(
			'login-and-registration',
			'password_strength_header',
			[
				'title' => __('Password Strength', 'ultimate-multisite'),
				'desc'  => __('Configure password strength requirements for user registration.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$this->add_field(
			'login-and-registration',
			'minimum_password_strength',
			[
				'title'   => __('Minimum Password Strength', 'ultimate-multisite'),
				'desc'    => __('Set the minimum password strength required during registration and password reset. "Weak" allows most passwords; "Medium" rejects common patterns (e.g. P@ssw0rd); "Super Strong" requires 12+ characters with mixed case, numbers, and symbols.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'medium',
				'options' => [
					'weak'         => __('Weak', 'ultimate-multisite'),
					'medium'       => __('Medium', 'ultimate-multisite'),
					'strong'       => __('Strong', 'ultimate-multisite'),
					'super_strong' => __('Super Strong (12+ chars, mixed case, numbers, symbols)', 'ultimate-multisite'),
				],
			]
		);

		$this->add_field(
			'login-and-registration',
			'other_header',
			[
				'title' => __('Other Options', 'ultimate-multisite'),
				'desc'  => __('Other registration-related options.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$this->add_field(
			'login-and-registration',
			'default_role',
			[
				'title'   => __('Default Role', 'ultimate-multisite'),
				'desc'    => __('Set the role to be applied to the user during the signup process.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'administrator',
				'options' => fn() => wu_get_roles_as_options(),
			]
		);

		$this->add_field(
			'login-and-registration',
			'add_users_to_main_site',
			[
				'title'   => __('Add Users to the Main Site as well?', 'ultimate-multisite'),
				'desc'    => __('Enabling this option will also add the user to the main site of your network.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$this->add_field(
			'login-and-registration',
			'main_site_default_role',
			[
				'title'   => __('Add to Main Site with Role...', 'ultimate-multisite'),
				'desc'    => __('Select the role Ultimate Multisite should use when adding the user to the main site of your network. Be careful.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'subscriber',
				'options' => fn() => wu_get_roles_as_options(),
				'require' => [
					'add_users_to_main_site' => 1,
				],
			]
		);

		do_action('wu_settings_login');

		/*
		 * Memberships
		 * This section holds the Membership  settings of the Ultimate Multisite Plugin.
		 */

		$this->add_section(
			'memberships',
			[
				'title' => __('Memberships', 'ultimate-multisite'),
				'desc'  => __('Memberships', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-infinity',
			]
		);

		$this->add_field(
			'memberships',
			'default_update_page',
			[
				'type'        => 'model',
				'title'       => __('Default Membership Update Page', 'ultimate-multisite'),
				'placeholder' => __('Search pages on the main site...', 'ultimate-multisite'),
				'desc'        => __('Only published pages on the main site are available for selection, and you need to make sure they contain a [wu_checkout] shortcode.', 'ultimate-multisite'),
				'tooltip'     => '',
				'html_attr'   => [
					'data-base-link'    => get_admin_url(wu_get_main_site_id(), 'post.php?action=edit&post'),
					'data-model'        => 'page',
					'data-value-field'  => 'ID',
					'data-label-field'  => 'post_title',
					'data-search-field' => 'post_title',
					'data-max-items'    => 1,
					'data-exclude'      => $filter_default_signup_pages,
				],
			]
		);

		$this->add_field(
			'memberships',
			'block_frontend',
			[
				'title'   => __('Block Frontend Access', 'ultimate-multisite'),
				'desc'    => __('Block the frontend access of network sites after a membership is no longer active.', 'ultimate-multisite'),
				'tooltip' => __('By default, if a user does not pay and the account goes inactive, only the admin panel will be blocked, but the user\'s site will still be accessible on the frontend. If enabled, this option will also block frontend access in those cases.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$this->add_field(
			'memberships',
			'block_frontend_grace_period',
			[
				'title'   => __('Frontend Block Grace Period', 'ultimate-multisite'),
				'desc'    => __('Select the number of days Ultimate Multisite should wait after the membership goes inactive before blocking the frontend access. Leave 0 to block immediately after the membership becomes inactive.', 'ultimate-multisite'),
				'type'    => 'number',
				'default' => 0,
				'min'     => 0,
				'require' => [
					'block_frontend' => 1,
				],
			]
		);

		$this->add_field(
			'memberships',
			'default_block_frontend_page',
			[
				'title'     => __('Frontend Block Page', 'ultimate-multisite'),
				'desc'      => __('Select a page on the main site to redirect user if access is blocked', 'ultimate-multisite'),
				'tooltip'   => '',
				'html_attr' => [
					'data-base-link'    => get_admin_url(wu_get_main_site_id(), 'post.php?action=edit&post'),
					'data-model'        => 'page',
					'data-value-field'  => 'ID',
					'data-label-field'  => 'post_title',
					'data-search-field' => 'post_title',
					'data-max-items'    => 1,
				],
				'require'   => [
					'block_frontend' => 1,
				],
			]
		);

		$this->add_field(
			'memberships',
			'enable_multiple_memberships',
			[
				'title'   => __('Enable Multiple Memberships per Customer', 'ultimate-multisite'),
				'desc'    => __('Enabling this option will allow your users to create more than one membership.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$this->add_field(
			'memberships',
			'enable_multiple_sites',
			[
				'title'   => __('Enable Multiple Sites per Membership', 'ultimate-multisite'),
				'desc'    => __('Enabling this option will allow your customers to create more than one site. You can limit how many sites your users can create in a per plan basis.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$this->add_field(
			'memberships',
			'block_sites_on_downgrade',
			[
				'title'   => __('Block Sites on Downgrade', 'ultimate-multisite'),
				'desc'    => __('Choose how Ultimate Multisite should handle client sites above their plan quota on downgrade.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'none',
				'options' => [
					'none'           => __('Keep sites as is (do nothing)', 'ultimate-multisite'),
					'block-frontend' => __('Block only frontend access', 'ultimate-multisite'),
					'block-backend'  => __('Block only backend access', 'ultimate-multisite'),
					'block-both'     => __('Block both frontend and backend access', 'ultimate-multisite'),
				],
				'require' => [
					'enable_multiple_sites' => true,
				],
			]
		);

		$this->add_field(
			'memberships',
			'move_posts_on_downgrade',
			[
				'title'   => __('Move Posts on Downgrade', 'ultimate-multisite'),
				'desc'    => __('Select how you want to handle the posts above the quota on downgrade. This will apply to all post types with quotas set.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'none',
				'options' => [
					'none'  => __('Keep posts as is (do nothing)', 'ultimate-multisite'),
					'trash' => __('Move posts above the new quota to the Trash', 'ultimate-multisite'),
					'draft' => __('Mark posts above the new quota as Drafts', 'ultimate-multisite'),
				],
			]
		);

		$this->add_field(
			'memberships',
			'emulated_post_types_header',
			[
				'type'  => 'header',
				'title' => __('Emulated Post Types', 'ultimate-multisite'),
				'desc'  => __('Emulates the registering of a custom post type to be able to create limits for it without having to activate plugins on the main site.', 'ultimate-multisite'),
			]
		);

		$this->add_field(
			'memberships',
			'emulated_post_types_explanation',
			[
				'type'            => 'note',
				'desc'            => __('By default, Ultimate Multisite only allows super admins to limit post types that are registered on the main site. This makes sense from a technical stand-point but it also forces you to have plugins network-activated in order to be able to set limitations for their custom post types. Using this option, you can emulate the registering of a post type. This will register them on the main site and allow you to create limits for them on your products.', 'ultimate-multisite'),
				'classes'         => '',
				'wrapper_classes' => '',
			]
		);

		$this->add_field(
			'memberships',
			'emulated_post_types_empty',
			[
				'type'              => 'note',
				'desc'              => __('Add the first post type using the button below.', 'ultimate-multisite'),
				'classes'           => 'wu-text-gray-600 wu-text-xs wu-text-center wu-w-full',
				'wrapper_classes'   => 'wu-bg-gray-100 wu-items-end',
				'wrapper_html_attr' => [
					'v-if'    => 'emulated_post_types.length === 0',
					'v-cloak' => '1',
				],
			]
		);

		$this->add_field(
			'memberships',
			'emulated_post_types',
			[
				'type'              => 'group',
				'tooltip'           => '',
				'raw'               => true,
				'default'           => [],
				'wrapper_classes'   => 'wu-relative wu-bg-gray-100 wu-pb-2',
				'wrapper_html_attr' => [
					'v-if'    => 'emulated_post_types.length',
					'v-for'   => '(emulated_post_type, index) in emulated_post_types',
					'v-cloak' => '1',
				],
				'fields'            => [
					'emulated_post_types_remove' => [
						'type'            => 'note',
						'desc'            => function () {
							printf('<a title="%s" class="wu-no-underline wu-inline-block wu-text-gray-600 wu-mt-2 wu-mr-2" href="#" @click.prevent="() => emulated_post_types.splice(index, 1)"><span class="dashicons-wu-squared-cross"></span></a>', esc_html__('Remove', 'ultimate-multisite'));
						},
						'wrapper_classes' => 'wu-absolute wu-top-0 wu-right-0',
					],
					'emulated_post_types_slug'   => [
						'type'            => 'text',
						'title'           => __('Post Type Slug', 'ultimate-multisite'),
						'placeholder'     => __('e.g. product', 'ultimate-multisite'),
						'wrapper_classes' => 'wu-w-5/12',
						'html_attr'       => [
							'v-model'     => 'emulated_post_type.post_type',
							'v-bind:name' => '"emulated_post_types[" + index + "][post_type]"',
						],
					],
					'emulated_post_types_label'  => [
						'type'            => 'text',
						'title'           => __('Post Type Label', 'ultimate-multisite'),
						'placeholder'     => __('e.g. Products', 'ultimate-multisite'),
						'wrapper_classes' => 'wu-w-7/12 wu-ml-2',
						'html_attr'       => [
							'v-model'     => 'emulated_post_type.label',
							'v-bind:name' => '"emulated_post_types[" + index + "][label]"',
						],
					],
				],
			]
		);

		$this->add_field(
			'memberships',
			'emulated_post_types_repeat',
			[
				'type'              => 'submit',
				'title'             => __('+ Add Post Type', 'ultimate-multisite'),
				'classes'           => 'wu-uppercase wu-text-2xs wu-text-blue-700 wu-border-none wu-bg-transparent wu-font-bold wu-text-right wu-w-full wu-cursor-pointer',
				'wrapper_classes'   => 'wu-bg-gray-100 wu-items-end',
				'wrapper_html_attr' => [
					'v-cloak' => '1',
				],
				'html_attr'         => [
					'v-on:click.prevent' => '() => {
					emulated_post_types = Array.isArray(emulated_post_types) ? emulated_post_types : [];  emulated_post_types.push({
						post_type: "",
						label: "",
					})
				}',
				],
			]
		);

		do_action('wu_settings_memberships');

		/*
		 * Site Templates
		 * This section holds the Site Templates settings of the Ultimate Multisite Plugin.
		 */

		$this->add_section(
			'sites',
			[
				'title' => __('Sites', 'ultimate-multisite'),
				'desc'  => __('Sites', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-browser',
			]
		);

		$this->add_field(
			'sites',
			'sites_features_heading',
			[
				'title' => __('Site Options', 'ultimate-multisite'),
				'desc'  => __('Configure certain aspects of how network Sites behave.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$this->add_field(
			'sites',
			'default_new_site_page',
			[
				'type'        => 'model',
				'title'       => __('Default New Site Page', 'ultimate-multisite'),
				'placeholder' => __('Search pages on the main site...', 'ultimate-multisite'),
				'desc'        => __('Only published pages on the main site are available for selection, and you need to make sure they contain a [wu_checkout] shortcode.', 'ultimate-multisite'),
				'tooltip'     => '',
				'html_attr'   => [
					'data-base-link'    => get_admin_url(wu_get_main_site_id(), 'post.php?action=edit&post'),
					'data-model'        => 'page',
					'data-value-field'  => 'ID',
					'data-label-field'  => 'post_title',
					'data-search-field' => 'post_title',
					'data-max-items'    => 1,
					'data-exclude'      => $filter_default_signup_pages,
				],
			]
		);

		$this->add_field(
			'sites',
			'enable_visits_limiting',
			[
				'title'   => __('Enable Visits Limitation & Counting', 'ultimate-multisite'),
				'desc'    => __('Enabling this option will add visits limitation settings to the plans and add the functionality necessary to count site visits on the front-end.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'sites',
			'enable_screenshot_generator',
			[
				'title'   => __('Enable Screenshot Generator', 'ultimate-multisite'),
				'desc'    => __('With this option is enabled, Ultimate Multisite will take a screenshot for every newly created site on your network and set the resulting image as that site\'s featured image. This features requires a valid license key to work and it is not supported for local sites.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'sites',
			'wordpress_features_heading',
			[
				'title' => __('WordPress Features', 'ultimate-multisite'),
				'desc'  => __('Override default WordPress settings for network Sites.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$this->add_field(
			'sites',
			'menu_items_plugin',
			[
				'title'   => __('Enable Plugins Menu', 'ultimate-multisite'),
				'desc'    => __('Do you want to let users on the network to have access to the Plugins page, activating plugins for their sites? If this option is disabled, the customer will not be able to manage the site plugins.', 'ultimate-multisite'),
				'tooltip' => __('You can select which plugins the user will be able to use for each plan.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'sites',
			'add_new_users',
			[
				'title'   => __('Add New Users', 'ultimate-multisite'),
				'desc'    => __('Allow site administrators to add new users to their site via the "Users → Add New" page.', 'ultimate-multisite'),
				'tooltip' => __('You can limit the number of users allowed for each plan.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'sites',
			'site_template_features_heading',
			[
				'title' => __('Site Template Options', 'ultimate-multisite'),
				'desc'  => __('Configure certain aspects of how Site Templates behave.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$this->add_field(
			'sites',
			'allow_template_switching',
			[
				'title'   => __('Allow Template Switching', 'ultimate-multisite'),
				'desc'    => __("Enabling this option will add an option on your client's dashboard to switch their site template to another one available on the catalog of available templates. The data is lost after a switch as the data from the new template is copied over.", 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'sites',
			'allow_own_site_as_template',
			[
				'title'   => __('Allow Users to use their own Sites as Templates', 'ultimate-multisite'),
				'desc'    => __('Enabling this option will add the user own sites to the template screen, allowing them to create a new site based on the content and customizations they made previously.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
				'require' => [
					'allow_template_switching' => true,
				],
			]
		);

		$this->add_field(
			'sites',
			'copy_media',
			[
				'title'   => __('Copy Media on Template Duplication?', 'ultimate-multisite'),
				'desc'    => __('Checking this option will copy the media uploaded on the template site to the newly created site. This can be overridden on each of the plans.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'sites',
			'stop_template_indexing',
			[
				'title'   => __('Prevent Search Engines from indexing Site Templates', 'ultimate-multisite'),
				'desc'    => __('Checking this option will discourage search engines from indexing all the Site Templates on your network.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		do_action('wu_settings_site_templates');

		/*
		 * Demo Sites
		 * Settings for demo/sandbox site functionality.
		 */
		$this->add_field(
			'sites',
			'demo_sites_heading',
			[
				'title' => __('Demo Sites', 'ultimate-multisite'),
				'desc'  => __('Configure demo/sandbox site behavior. Demo sites are temporary sites that automatically expire and get deleted after a set period.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$this->add_field(
			'sites',
			'demo_duration',
			[
				'title'     => __('Demo Duration', 'ultimate-multisite'),
				'desc'      => __('How long demo sites should remain active before being automatically deleted. Set to 0 to disable automatic deletion.', 'ultimate-multisite'),
				'type'      => 'number',
				'default'   => 2,
				'min'       => 0,
				'html_attr' => [
					'style' => 'width: 80px;',
				],
			]
		);

		$this->add_field(
			'sites',
			'demo_duration_unit',
			[
				'title'   => __('Demo Duration Unit', 'ultimate-multisite'),
				'desc'    => __('The time unit for demo duration.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'hour',
				'options' => [
					'hour' => __('Hours', 'ultimate-multisite'),
					'day'  => __('Days', 'ultimate-multisite'),
					'week' => __('Weeks', 'ultimate-multisite'),
				],
			]
		);

		$this->add_field(
			'sites',
			'demo_delete_customer',
			[
				'title'   => __('Delete Customer After Demo Expires', 'ultimate-multisite'),
				'desc'    => __('When enabled, the customer account will also be deleted when their demo site expires (only if they have no other memberships).', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$this->add_field(
			'sites',
			'demo_expiring_notification',
			[
				'title'   => __('Send Expiration Warning Email', 'ultimate-multisite'),
				'desc'    => __('When enabled, customers will receive an email notification before their demo site expires.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'sites',
			'demo_expiring_warning_time',
			[
				'title'     => __('Warning Time Before Expiration', 'ultimate-multisite'),
				'desc'      => __('How long before the demo expires should the warning email be sent. Uses the same time unit as demo duration.', 'ultimate-multisite'),
				'type'      => 'number',
				'default'   => 1,
				'min'       => 1,
				'html_attr' => [
					'style' => 'width: 80px;',
				],
			]
		);

		$this->add_field(
			'sites',
			'demo_go_live_url',
			[
				'title'   => __('Go Live URL', 'ultimate-multisite'),
				'desc'    => __('The URL customers are sent to when they click "Go Live" on a keep-until-live demo site. Typically this is your checkout form page URL. Leave empty to use the built-in instant activation (no payment collected).', 'ultimate-multisite'),
				'type'    => 'url',
				'default' => '',
			]
		);

		do_action('wu_settings_demo_sites');

		/*
		 * Payment Gateways
		 * This section holds the Payment Gateways settings of the Ultimate Multisite Plugin.
		 */

		$this->add_section(
			'payment-gateways',
			[
				'title' => __('Payments', 'ultimate-multisite'),
				'desc'  => __('Payments', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-credit-card',
			]
		);

		$this->add_field(
			'payment-gateways',
			'main_header',
			[
				'title'           => __('Payment Settings', 'ultimate-multisite'),
				'desc'            => __('The following options affect how prices are displayed on the frontend, the backend and in reports.', 'ultimate-multisite'),
				'type'            => 'header',
				'show_as_submenu' => true,
			]
		);

		$this->add_field(
			'payment-gateways',
			'force_auto_renew',
			[
				'title'   => __('Force Auto-Renew', 'ultimate-multisite'),
				'desc'    => __('Enable this option if you want to make sure memberships are created with auto-renew activated whenever the selected gateway supports it. Disabling this option will show an auto-renew option during checkout.', 'ultimate-multisite'),
				'tooltip' => '',
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'payment-gateways',
			'allow_trial_without_payment_method',
			[
				'title'   => __('Allow Trials without Payment Method', 'ultimate-multisite'),
				'desc'    => __('By default, Ultimate Multisite asks customers to add a payment method on sign-up even if a trial period is present. Enable this option to only ask for a payment method when the trial period is over.', 'ultimate-multisite'),
				'tooltip' => '',
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$this->add_field(
			'payment-gateways',
			'attach_invoice_pdf',
			[
				'title'   => __('Send Invoice on Payment Confirmation', 'ultimate-multisite'),
				'desc'    => __('Enabling this option will attach a PDF invoice (marked paid) with the payment confirmation email. This option does not apply to the Manual Gateway, which sends invoices regardless of this option.', 'ultimate-multisite'),
				'tooltip' => __('The invoice files will be saved on the wp-content/uploads/wu-invoices folder.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 1,
			]
		);

		$this->add_field(
			'payment-gateways',
			'invoice_numbering_scheme',
			[
				'title'   => __('Invoice Numbering Scheme', 'ultimate-multisite'),
				'desc'    => __('What should Ultimate Multisite use as the invoice number?', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'reference_code',
				'tooltip' => '',
				'options' => [
					'reference_code'    => __('Payment Reference Code', 'ultimate-multisite'),
					'sequential_number' => __('Sequential Number', 'ultimate-multisite'),
				],
			]
		);

		$this->add_field(
			'payment-gateways',
			'next_invoice_number',
			[
				'title'   => __('Next Invoice Number', 'ultimate-multisite'),
				'desc'    => __('This number will be used as the invoice number for the next invoice generated on the system. It is incremented by one every time a new invoice is created. You can change it and save it to reset the invoice sequential number to a specific value.', 'ultimate-multisite'),
				'type'    => 'number',
				'default' => '1',
				'min'     => 0,
				'require' => [
					'invoice_numbering_scheme' => 'sequential_number',
				],
			]
		);

		$this->add_field(
			'payment-gateways',
			'invoice_prefix',
			[
				'title'       => __('Invoice Number Prefix', 'ultimate-multisite'),
				'placeholder' => __('INV00', 'ultimate-multisite'),
				// translators: %%YEAR%%, %%MONTH%%, and %%DAY%% are placeholders but are replaced before shown to the user but are used as examples.
				'desc'        => sprintf(__('Use %%YEAR%%, %%MONTH%%, and %%DAY%% to create a dynamic placeholder. E.g. %%YEAR%%-%%MONTH%%-INV will become %s.', 'ultimate-multisite'), gmdate('Y') . '-' . gmdate('m') . '-INV'),
				'default'     => '',
				'type'        => 'text',
				'raw'         => true, // Necessary to prevent the removal of the %% tags.
				'require'     => [
					'invoice_numbering_scheme' => 'sequential_number',
				],
			]
		);

		$this->add_field(
			'payment-gateways',
			'gateways_header',
			[
				'title'           => __('Payment Gateways', 'ultimate-multisite'),
				'desc'            => __('Activate and configure the installed payment gateways in this section.', 'ultimate-multisite'),
				'type'            => 'header',
				'show_as_submenu' => true,
			]
		);

		do_action('wu_settings_payment_gateways');

		/*
		 * Emails
		 * This section holds the Email settings of the Ultimate Multisite Plugin.
		 */
		$this->add_section(
			'emails',
			[
				'title' => __('Emails', 'ultimate-multisite'),
				'desc'  => __('Emails', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-email',
			]
		);

		do_action('wu_settings_emails');

		$this->add_field(
			'emails',
			'transactional_email_header',
			[
				'title'           => __('Transactional Email Delivery', 'ultimate-multisite'),
				'desc'            => __('Configure how WordPress sends transactional email (password resets, notifications, admin alerts) on a per-site basis. When a provider is configured, outbound email is routed through the provider using each site\'s own domain as the from-address.', 'ultimate-multisite'),
				'type'            => 'header',
				'show_as_submenu' => true,
			]
		);

		$this->add_field(
			'emails',
			'transactional_email_provider_note',
			[
				'type' => 'note',
				'desc' => sprintf(
					/* translators: %s is a link to the Integrations settings page. */
					__('To configure a transactional email provider (e.g. Amazon SES), go to the <a href="%s">Integrations settings</a> and set up the provider there.', 'ultimate-multisite'),
					esc_url(wu_network_admin_url('wp-ultimo-settings', ['tab' => 'integrations']))
				),
			]
		);

		/**
		 * Fires after the Transactional Email Delivery settings fields are registered.
		 *
		 * Providers implementing the Transactional_Email_Capability interface should hook
		 * here to register provider-specific settings fields (e.g. status indicators,
		 * per-provider configuration options) in the Emails settings section.
		 *
		 * @since 2.5.0
		 */
		do_action('wu_settings_transactional_email');

		/*
		 * Domain Mapping
		 * This section holds the Domain Mapping settings of the Ultimate Multisite Plugin.
		 */

		$this->add_section(
			'domain-mapping',
			[
				'title' => __('Domain Mapping', 'ultimate-multisite'),
				'desc'  => __('Domain Mapping', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-link',
			]
		);

		do_action('wu_settings_domain_mapping');

		/*
		 * Single Sign-on
		 * This section includes settings related to the single sign-on functionality
		 */

		$this->add_section(
			'sso',
			[
				'title' => __('Single Sign-On', 'ultimate-multisite'),
				'desc'  => __('Single Sign-On', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-add-user',
			]
		);

		do_action('wu_settings_sso');

		/*
		 * Integrations
		 * This section holds the Integrations settings of the Ultimate Multisite Plugin.
		 */

		$this->add_section(
			'integrations',
			[
				'title' => __('Integrations', 'ultimate-multisite'),
				'desc'  => __('Integrations', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-power-plug',
			]
		);

		$this->add_field(
			'integrations',
			'hosting_providers_header',
			[
				'title'           => __('Hosting or Panel Providers', 'ultimate-multisite'),
				'desc'            => __('Configure and manage the integration with your Hosting or Panel Provider.', 'ultimate-multisite'),
				'type'            => 'header',
				'show_as_submenu' => true,
			]
		);

		do_action('wu_settings_integrations');

		/*
		 * Import/Export
		 * This section holds the Import/Export settings of the Ultimate Multisite Plugin.
		 */

		$this->add_section(
			'import-export',
			[
				'title' => __('Import/Export', 'ultimate-multisite'),
				'desc'  => __('Export your settings to a JSON file or import settings from a previously exported file.', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-download',
				'order' => 995,
			]
		);

		// Export Settings Header
		$this->add_field(
			'import-export',
			'export_header',
			[
				'title' => __('Export Settings', 'ultimate-multisite'),
				'desc'  => __('Download all your Ultimate Multisite settings as a JSON file for backup or migration purposes.', 'ultimate-multisite'),
				'type'  => 'header',
			],
			10
		);

		// Export Description
		$this->add_field(
			'import-export',
			'export_description',
			[
				'type'    => 'note',
				'desc'    => __('The exported file will contain all ultimate multisite settings defined on this page. This includes general settings, payment gateway configurations, email settings, domain mapping settings, and all other plugin configurations. It does not include products, sites, domains, customers and other entities.', 'ultimate-multisite'),
				'classes' => 'wu-text-gray-600 wu-text-sm',
			],
			20
		);

		// Export Button
		$this->add_field(
			'import-export',
			'export_settings_button',
			[
				'type'            => 'submit',
				'title'           => __('Export Settings', 'ultimate-multisite'),
				'classes'         => 'button button-primary',
				'wrapper_classes' => 'wu-items-start',
				'html_attr'       => [
					'onclick' => 'window.location.href="' . wp_nonce_url(
						add_query_arg(['wu_export_settings' => '1'], wu_get_current_url()),
						'wu_export_settings'
					) . '"; return false;',
				],
			],
			30
		);

		// Import Settings Header
		$this->add_field(
			'import-export',
			'import_header',
			[
				'title' => __('Import Settings', 'ultimate-multisite'),
				'desc'  => __('Upload a previously exported JSON file to restore settings.', 'ultimate-multisite'),
				'type'  => 'header',
			],
			40
		);

		// Import Button
		$this->add_field(
			'import-export',
			'import_settings_button',
			[
				'type'            => 'link',
				'display_value'   => __('Import Settings', 'ultimate-multisite'),
				'title'           => __('Import and Replace All Settings', 'ultimate-multisite'),
				'classes'         => 'button button-secondary wu-ml-0 wubox',
				'wrapper_classes' => 'wu-items-start',
				'html_attr'       => [
					'href' => wu_get_form_url(
						'import_settings',
						[
							'width' => 600,
						]
					),
				],
			],
			55
		);

		// Import Warning
		$this->add_field(
			'import-export',
			'import_warning',
			[
				'type'    => 'note',
				'desc'    => sprintf(
					'<strong class="wu-text-red-600">%s</strong> %s',
					__('Warning:', 'ultimate-multisite'),
					__('Importing settings will replace ALL current settings with the values from the uploaded file. This action cannot be undone. We recommend exporting your current settings as a backup before importing.', 'ultimate-multisite')
				),
				'classes' => 'wu-bg-red-50 wu-border-l-4 wu-border-red-500 wu-p-4',
			],
			60
		);

		do_action('wu_settings_import_export');

		/*
		 * Other Options
		 * This section holds the Other Options settings of the Ultimate Multisite Plugin.
		 */

		$this->add_section(
			'other',
			[
				'title' => __('Other Options', 'ultimate-multisite'),
				'desc'  => __('Other Options', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-switch',
				'order' => 1000,
			]
		);

		$this->add_field(
			'other',
			'Other_header',
			[
				'title' => __('Miscellaneous', 'ultimate-multisite'),
				'desc'  => __('Other options that do not fit anywhere else.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$preview_image = wu_preview_image(wu_get_asset('settings/settings-hide-ui-tours.webp'));

		$this->add_field(
			'other',
			'hide_tours',
			[
				'title'   => __('Hide UI Tours', 'ultimate-multisite') . $preview_image,
				'desc'    => __('The UI tours showed by Ultimate Multisite should permanently hide themselves after being seen but if they persist for whatever reason, toggle this option to force them into their viewed state - which will prevent them from showing up again.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$preview_image_2 = wu_preview_image(wu_get_asset('settings/settings-disable-hover-to-zoom.webp'));

		$this->add_field(
			'other',
			'disable_image_zoom',
			[
				'title'   => __('Disable "Hover to Zoom"', 'ultimate-multisite') . $preview_image_2,
				'desc'    => __('By default, Ultimate Multisite adds a "hover to zoom" feature, allowing network admins to see larger version of site screenshots and other images across the UI in full-size when hovering over them. You can disable that feature here. Preview tags like the above are not affected.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		$this->add_field(
			'other',
			'error_reporting_header',
			[
				'title' => __('Logging', 'ultimate-multisite'),
				'desc'  => __('Log Ultimate Multisite data. This is useful for debugging purposes.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$this->add_field(
			'other',
			'error_logging_level',
			[
				'title'   => __('Logging Level', 'ultimate-multisite'),
				'desc'    => __('Select the level of logging you want to use.', 'ultimate-multisite'),
				'type'    => 'select',
				'default' => 'default',
				'options' => [
					'default'  => __('PHP Default', 'ultimate-multisite'),
					'disabled' => __('Disabled', 'ultimate-multisite'),
					'errors'   => __('Errors Only', 'ultimate-multisite'),
					'all'      => __('Everything', 'ultimate-multisite'),
				],
			]
		);

		$this->add_field(
			'other',
			'advanced_header',
			[
				'title' => __('Advanced Options', 'ultimate-multisite'),
				'desc'  => __('Change the plugin and wordpress behavior.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		$plans = get_posts(
			[
				'post_type'   => 'wpultimo_plan',
				'numberposts' => 1,
			]
		);

		if ( ! empty($plans)) {
			$url = wu_network_admin_url('wp-ultimo-migration-alert');

			$title = __('Run Migration Again', 'ultimate-multisite') . sprintf(
				"<span class='wu-normal-case wu-block wu-text-xs wu-font-normal wu-mt-1'>%s</span>",
				__('Rerun the Migration Wizard if you experience data-loss after migrate.', 'ultimate-multisite')
			) . sprintf(
				"<span class='wu-normal-case wu-block wu-text-xs wu-font-normal wu-mt-2'>%s</span>",
				__('<b>Important:</b> This process can have unexpected behavior with your current Ultimo models.<br>We recommend that you create a backup before continue.', 'ultimate-multisite')
			);

			$html = sprintf('<a href="%s" class="button-primary">%s</a>', $url, __('Migrate', 'ultimate-multisite'));

			$this->add_field(
				'other',
				'run_migration',
				[
					'title' => $title,
					'type'  => 'note',
					'desc'  => $html,
				]
			);
		}

		if (function_exists('wu_get_security_mode_key')) {
			/**
			 *  Only allow security mode if we added sunrise.php functions
			 */
			$security_mode_key = '?wu_secure=' . wu_get_security_mode_key();

			$this->add_field(
				'other',
				'security_mode',
				[
					'title'   => __('Security Mode', 'ultimate-multisite'),
					// Translators: Placeholder adds the security mode key and current site url with query string
					'desc'    => sprintf(__('Only Ultimate Multisite and other must-use plugins will run on your WordPress install while this option is enabled.<div class="wu-mt-2"><b>Important:</b> Copy the following URL to disable security mode if something goes wrong and this page becomes unavailable:<code>%2$s</code></div>', 'ultimate-multisite'), $security_mode_key, get_site_url() . $security_mode_key),
					'type'    => 'toggle',
					'default' => 0,
				]
			);
		}

		$this->add_field(
			'other',
			'uninstall_wipe_tables',
			[
				'title'   => __('Remove Data on Uninstall', 'ultimate-multisite'),
				'desc'    => __('Remove all saved data for Ultimate Multisite when the plugin is uninstalled.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		do_action('wu_settings_other');
	}

	/**
	 * Returns a flat map of setting keys to their default values.
	 *
	 * This is used as a lightweight fallback in get_setting() so that
	 * default_sections() (which is expensive) does not need to run on
	 * every page load.
	 *
	 * @since 2.5.0
	 * @return array<string, mixed>
	 */
	public static function get_setting_defaults(): array {

		return [
			// General
			'company_name'                       => '',
			'company_logo'                       => '',
			'company_email'                      => '',
			'company_address'                    => '',
			'company_country'                    => 'US',
			'currency_symbol'                    => 'USD',
			'currency_position'                  => '%s %v',
			'decimal_separator'                  => '.',
			'thousand_separator'                 => ',',
			'precision'                          => '2',
			'enable_error_reporting'             => 0,
			'enable_beta_updates'                => 0,

			// Login & Registration
			'enable_registration'                => 1,
			'enable_email_verification'          => 'free_only',
			'enable_custom_login_page'           => 0,
			'default_login_page'                 => 0,
			'obfuscate_original_login_url'       => 0,
			'subsite_custom_login_logo'          => 0,
			'force_publish_sites_sync'           => 0,
			'minimum_password_strength'          => 'medium',
			'default_role'                       => 'administrator',
			'add_users_to_main_site'             => 0,
			'main_site_default_role'             => 'subscriber',

			// Memberships
			'block_frontend'                     => 0,
			'block_frontend_grace_period'        => 0,
			'enable_multiple_memberships'        => 0,
			'enable_multiple_sites'              => 0,
			'block_sites_on_downgrade'           => 'none',
			'move_posts_on_downgrade'            => 'none',
			'emulated_post_types'                => [],

			// Sites
			'enable_visits_limiting'             => 1,
			'enable_screenshot_generator'        => 1,
			'menu_items_plugin'                  => 1,
			'add_new_users'                      => 1,
			'allow_template_switching'           => 1,
			'allow_own_site_as_template'         => 0,
			'copy_media'                         => 1,
			'stop_template_indexing'             => 0,

			// Payment Gateways
			'force_auto_renew'                   => 1,
			'allow_trial_without_payment_method' => 0,
			'attach_invoice_pdf'                 => 1,
			'invoice_numbering_scheme'           => 'reference_code',
			'next_invoice_number'                => '1',
			'invoice_prefix'                     => '',

			// Emails (registered via hooks but commonly queried)
			'from_name'                          => '',
			'from_email'                         => '',

			// Domain Mapping (registered via hooks but commonly queried)
			'enable_domain_mapping'              => false,
			'custom_domains'                     => false,
			'domain_mapping_instructions'        => '',

			// SSO (registered via hooks)
			'enable_sso'                         => 1,

			// Other
			'hide_tours'                         => 0,
			'disable_image_zoom'                 => 0,
			'error_logging_level'                => 'default',
			'security_mode'                      => 0,
			'uninstall_wipe_tables'              => 0,

			// Whitelabel (registered via hooks)
			'rename_site_plural'                 => '',
			'rename_site_singular'               => '',
			'rename_wordpress'                   => '',

			// Maintenance mode (registered via hooks)
			'maintenance_mode'                   => false,

			// Notifications
			'hide_notifications_subsites'        => false,

			// Taxes
			'enable_taxes'                       => false,

			// Checkout-related
			'default_pricing_option'             => 1,
			'allowed_countries'                  => [],
			'trial'                              => 0,

			// Manual gateway
			'manual_payment_instructions'        => '',

			// Event manager
			'saving_type'                        => [],

			// Jumper
			'jumper_custom_links'                => '',

			// Limits
			'limits_and_quotas'                  => [],

			// Legacy pricing toggles
			'enable_price_3'                     => true,
			'enable_price_12'                    => true,
		];
	}

	/**
	 * Returns a lightweight list of section slugs and titles for use
	 * in the admin bar and other places that don't need full field definitions.
	 *
	 * This avoids triggering default_sections() and all the expensive
	 * field registration that comes with it.
	 *
	 * @since 2.5.0
	 * @return array<string, array{title: string, icon: string}>
	 */
	public function get_section_names(): array {

		$core_sections = [
			'general'                => [
				'title' => __('General', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-cog',
			],
			'login-and-registration' => [
				'title' => __('Login & Registration', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-key',
			],
			'memberships'            => [
				'title' => __('Memberships', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-infinity',
			],
			'sites'                  => [
				'title' => __('Sites', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-browser',
			],
			'payment-gateways'       => [
				'title' => __('Payments', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-credit-card',
			],
			'emails'                 => [
				'title' => __('Emails', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-email',
			],
			'domain-mapping'         => [
				'title' => __('Domain Mapping', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-link',
			],
			'sso'                    => [
				'title' => __('Single Sign-On', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-add-user',
			],
			'integrations'           => [
				'title' => __('Integrations', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-power-plug',
			],
			'import-export'          => [
				'title' => __('Import/Export', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-download',
				'order' => 995,
			],
			'other'                  => [
				'title' => __('Other Options', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-switch',
				'order' => 1000,
			],
		];

		/**
		 * Allows addons to register their section names without triggering
		 * the full field registration in default_sections().
		 *
		 * @since 2.5.0
		 * @param array $sections Section slug => array with 'title', 'icon', and optionally 'addon' => true.
		 */
		return apply_filters('wu_settings_section_names', $core_sections);
	}

	/**
	 * Tries to determine the location of the company based on the admin IP.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_default_company_country() {

		$geolocation = \WP_Ultimo\Geolocation::geolocate_ip('', true);

		return $geolocation['country'];
	}
}
