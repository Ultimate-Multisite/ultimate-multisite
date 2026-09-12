<?php
/**
 * Ultimate Multisite Customize/Add New Template Previewer Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

use WP_Ultimo\UI\Template_Previewer;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Template Previewer Customize/Add New Admin Page.
 */
class Template_Previewer_Customize_Admin_Page extends Customizer_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-customize-template-previewer';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $parent = 'none';

	/**
	 * This page has no parent, so we need to highlight another sub-menu.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $highlight_menu_slug = 'wp-ultimo-settings';

	/**
	 * Holds the admin panels where this page should be displayed, as well as which capability to require.
	 *
	 * To add a page to the regular admin (wp-admin/), use: 'admin_menu' => 'capability_here'
	 * To add a page to the network admin (wp-admin/network), use: 'network_admin_menu' => 'capability_here'
	 * To add a page to the user (wp-admin/user) admin, use: 'user_admin_menu' => 'capability_here'
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $supported_panels = [
		'network_admin_menu' => 'wu_customize_invoice_template',
	];

	/**
	 * Returns the preview URL. This is then added to the iframe.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_preview_url() {

		$url = get_site_url(null, '/');

		return add_query_arg(
			[
				'customizer' => 1,
				Template_Previewer::get_instance()->get_preview_parameter() => 1,
			],
			$url
		);
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		$this->add_save_widget(
			'save',
			[
				'fields' => [
					'preview_url_parameter' => [
						'type'  => 'text',
						'title' => __('URL Parameter', 'ultimate-multisite'),
						'desc'  => __('This is the URL parameter Ultimate Multisite will use to generate the template preview URLs.', 'ultimate-multisite'),
						'value' => Template_Previewer::get_instance()->get_setting('preview_url_parameter', 'template-preview'),
					],
					'enabled'               => [
						'type'      => 'toggle',
						'title'     => __('Active', 'ultimate-multisite'),
						'desc'      => __('If your site templates are not loading, you can disable the top-bar using this setting.', 'ultimate-multisite'),
						'value'     => Template_Previewer::get_instance()->get_setting('enabled', true),
						'html_attr' => [],
					],
				],
			]
		);

		$custom_logo = Template_Previewer::get_instance()->get_setting('custom_logo');

		$custom_logo_args = wp_get_attachment_image_src($custom_logo, 'full');

		$custom_logo_url = $custom_logo_args ? $custom_logo_args[0] : false;

		$fields = [
			'tab'                         => [
				'type'              => 'tab-select',
				'wrapper_classes'   => '',
				'wrapper_html_attr' => [
					'v-cloak' => 1,
				],
				'html_attr'         => [
					'v-model' => 'tab',
				],
				'options'           => [
					'general' => __('General', 'ultimate-multisite'),
					'colors'  => __('Colors', 'ultimate-multisite'),
					'images'  => __('Images', 'ultimate-multisite'),
				],
			],

			'display_responsive_controls' => [
				'type'              => 'toggle',
				'title'             => __('Show Responsive Controls', 'ultimate-multisite'),
				'desc'              => __('Toggle to show or hide the responsive controls.', 'ultimate-multisite'),
				'value'             => true,
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "general")',
					'v-cloak' => 1,
				],
				'html_attr'         => [
					'v-model' => 'display_responsive_controls',
				],
			],
			'button_text'                 => [
				'type'              => 'text',
				'title'             => __('Button Text', 'ultimate-multisite'),
				'value'             => __('Use this Template', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "general")',
					'v-cloak' => 1,
				],
				'html_attr'         => [
					'v-model.lazy' => 'button_text',
				],
			],

			'bg_color'                    => [
				'type'              => 'color-picker',
				'title'             => __('Background Color', 'ultimate-multisite'),
				'desc'              => __('Choose the background color for the top-bar.', 'ultimate-multisite'),
				'value'             => '#f9f9f9',
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "colors")',
					'v-cloak' => 1,
				],
				'html_attr'         => [
					'v-model' => 'bg_color',
				],
			],
			'button_bg_color'             => [
				'type'              => 'color-picker',
				'title'             => __('Button BG Color', 'ultimate-multisite'),
				'desc'              => __('Pick the background color for the button.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "colors")',
					'v-cloak' => 1,
				],
				'html_attr'         => [
					'v-model' => 'button_bg_color',
				],
			],

			'use_custom_logo'             => [
				'type'              => 'toggle',
				'title'             => __('Use Custom Logo', 'ultimate-multisite'),
				'desc'              => __('You can set a different logo to be used on the top-bar.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "images")',
					'v-cloak' => 1,
				],
				'html_attr'         => [
					'v-model' => 'use_custom_logo',
				],
			],
			'custom_logo'                 => [
				'type'              => 'image',
				'stacked'           => true,
				'title'             => __('Custom Logo', 'ultimate-multisite'),
				'desc'              => __('The logo is displayed on the preview page top-bar.', 'ultimate-multisite'),
				'value'             => $custom_logo,
				'img'               => $custom_logo_url,
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "images") && require("use_custom_logo", true)',
					'v-cloak' => 1,
				],
			],
		];

		$settings = Template_Previewer::get_instance()->get_settings();

		$state = array_merge(
			$settings,
			[
				'tab'     => 'general',
				'refresh' => true,
			]
		);

		$this->add_fields_widget(
			'customizer',
			[
				'title'     => __('Customizer', 'ultimate-multisite'),
				'position'  => 'side',
				'fields'    => $fields,
				'html_attr' => [
					'style'                    => 'margin-top: -6px;',
					'data-wu-app'              => 'site_template_customizer',
					'data-wu-customizer-panel' => true,
					'data-state'               => wp_json_encode($state),
				],
			]
		);
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Customize Template Previewer', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Customize Template Previewer', 'ultimate-multisite');
	}

	/**
	 * Returns the action links for that page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function action_links() {

		return [];
	}

	/**
	 * Returns the labels to be used on the admin page.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_labels() {

		return [
			'customize_label'   => __('Customize Template Previewer', 'ultimate-multisite'),
			'add_new_label'     => __('Customize Template Previewer', 'ultimate-multisite'),
			'edit_label'        => __('Edit Template Previewer', 'ultimate-multisite'),
			'updated_message'   => __('Template Previewer updated with success!', 'ultimate-multisite'),
			'title_placeholder' => __('Enter Template Previewer Name', 'ultimate-multisite'),
			'title_description' => __('This name is used for internal reference only.', 'ultimate-multisite'),
			'save_button_label' => __('Save Changes', 'ultimate-multisite'),
			'save_description'  => '',
		];
	}

	/**
	 * Should implement the processes necessary to save the changes made to the object.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_save(): void {

		// Nonce checked in calling method.
		$allowed_settings = [
			'bg_color',
			'button_bg_color',
			'logo_url',
			'button_text',
			'preview_url_parameter',
			'display_responsive_controls',
			'use_custom_logo',
			'custom_logo',
			'enabled',
		];

		$settings_to_save = [];

		foreach ($allowed_settings as $setting) {
			if (isset($_POST[ $setting ])) { // phpcs:ignore WordPress.Security.NonceVerification
				$value = wp_unslash($_POST[ $setting ]); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

				switch ($setting) {
					case 'bg_color':
					case 'button_bg_color':
						$settings_to_save[ $setting ] = sanitize_hex_color($value);
						break;
					case 'display_responsive_controls':
					case 'use_custom_logo':
					case 'enabled':
						$settings_to_save[ $setting ] = wu_string_to_bool($value);
						break;
					case 'custom_logo':
						$settings_to_save[ $setting ] = absint($value);
						break;
					case 'logo_url':
						$settings_to_save[ $setting ] = esc_url_raw($value);
						break;
					case 'button_text':
					case 'preview_url_parameter':
					default:
						$settings_to_save[ $setting ] = sanitize_text_field($value);
						break;
				}
			}
		}

		Template_Previewer::get_instance()->save_settings($settings_to_save);

		$array_params = [
			'updated' => 1,
		];

		$url = add_query_arg($array_params);

		wp_safe_redirect($url);

		exit;
	}
}
