<?php
/**
 * Ultimate Multisite Checkout_Form Edit/Add New Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Checkout\Signup_Fields\Base_Signup_Field;
use WP_Ultimo\Managers\Signup_Fields_Manager;

/**
 * Ultimate Multisite Checkout_Form Edit/Add New Admin Page.
 * Here we use the default WP Session to avoid errors with cookie size.
 */
class Checkout_Form_Edit_Admin_Page extends Edit_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-edit-checkout-form';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * Object ID being edited.
	 *
	 * @since 1.8.2
	 * @var string
	 */
	public $object_id = 'checkout-form';

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
	 * @var bool|string
	 */
	protected $highlight_menu_slug = 'wp-ultimo-checkout-forms';

	/**
	 * If this number is greater than 0, a badge with the number will be displayed alongside the menu title
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $badge_count = 0;

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
		'network_admin_menu' => 'wu_edit_checkout_forms',
	];

	/**
	 * Overrides the init method to add additional hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		parent::init();

		$this->generate_checkout_form_preview();

		add_action('wp_ajax_wu_save_editor_session', [$this, 'save_editor_session']);

		add_action('load-admin_page_wp-ultimo-edit-checkout-form', [$this, 'add_width_control_script']);
	}

	/**
	 * Adds the script that controls
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function add_width_control_script(): void {

		wp_enqueue_script('wu-checkout-form-edit-modal', wu_get_asset('checkout-form-editor-modal.js', 'js'), [], wu_get_version(), true);
	}

	/**
	 * Returns the action links for that page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function action_links() {

		$actions = [];

		if ($this->get_object()->exists()) {
			$url_atts = [
				'id'    => $this->get_object()->get_id(),
				'slug'  => $this->get_object()->get_slug(),
				'model' => 'checkout_form',
			];

			$actions[] = [
				'label'   => __('Generate Shortcode', 'ultimate-multisite'),
				'icon'    => 'wu-copy',
				'classes' => 'wubox',
				'url'     => wu_get_form_url('shortcode_checkout', $url_atts),
			];
		}

		return $actions;
	}

	/**
	 * Renders the preview of a given form being edited.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function generate_checkout_form_preview(): void {

		if (wu_request('action') === 'wu_generate_checkout_form_preview') {

			// disable unnecessary filters
			add_filter('show_admin_bar', '__return_false');
			add_filter('wu_is_jumper_enabled', '__return_false');
			add_filter('wu_is_toolbox_enabled', '__return_false');

			add_action('wp', [$this, 'content_checkout_form_by_settings']);
		}
	}

	/**
	 * Filter the content to render checkout form by settings.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function content_checkout_form_by_settings(): void {

		$checkout_form = wu_get_checkout_form(wu_request('form_id'));

		if ( ! $checkout_form) {
			return;
		}

		$key = wp_get_session_token();

		$session = \WP_Session_Tokens::get_instance(get_current_user_id());

		$settings_session = wu_get_isset($session->get($key), 'wu_checkout_form_editor', []);

		if ( ! empty($settings_session)) {
			$checkout_form->set_settings($settings_session);
		}

		$settings = $checkout_form->get_settings();

		$preview_type = wu_request('type', 'user');

		if ('visitor' === $preview_type) {
			global $current_user;

			$current_user = wp_set_current_user(0);
		}

		wp_enqueue_scripts();

		wp_print_head_scripts();

		printf('<body %s>', 'class="' . esc_attr(implode(' ', get_body_class('wu-styling'))) . '"');

		echo '<div class="wu-p-6">';

		$count = count($settings);

		foreach ($settings as $index => $step) {
			$final_fields = wu_create_checkout_fields($step['fields']);

			wu_get_template(
				'checkout/form',
				[
					'step'               => $step,
					'step_name'          => $step['id'],
					'final_fields'       => $final_fields,
					'checkout_form_name' => '',
					'password_strength'  => false,
					'apply_styles'       => true,
					'display_title'      => true,
				]
			);

			if ($index < $count - 1) {
				printf('<hr class="sm:wu-bg-transparent wu-hr-text wu-font-semibold wu-my-4 wu-mt-6 wu-text-gray-600 wu-text-sm" data-content="%s">', esc_attr__('Step Separator', 'ultimate-multisite'));
			}
		}

		wp_print_footer_scripts();

		echo '</div></body>';

		exit;
	}

	/**
	 * Save the editor session.
	 *
	 * This is used to edit steps and fields that were not saved.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function save_editor_session(): void {

		$settings = wu_request('settings', []);

		$form = wu_get_checkout_form(wu_request('form_id'));

		if ($form && $settings) {
			$session = \WP_Session_Tokens::get_instance(get_current_user_id());

			$key = wp_get_session_token();

			$session_data = $session->get($key);

			$session_data['wu_checkout_form_editor'] = $settings;

			$session->update($key, $session_data);

			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * Adds hooks when the page loads.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function page_loaded(): void {

		parent::page_loaded();

		$object = $this->get_object();

		if ( ! is_wp_error($object->validate())) {
			$key = wp_get_session_token();

			$session = \WP_Session_Tokens::get_instance(get_current_user_id());

			$session_data = $session->get($key);

			unset($session_data['wu_checkout_form_editor']);

			$session->update($key, $session_data);
		}

		$screen = get_current_screen();

		add_action("wu_edit_{$screen->id}_after_normal", [$this, 'render_steps']);

		add_action('admin_footer', [$this, 'render_js_templates']);
	}

	// Forms

	/**
	 * Register ajax forms to handle adding new memberships.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {
		/*
		 * Add new Section
		 */
		wu_register_form(
			'add_new_form_step',
			[
				'render'     => [$this, 'render_add_new_form_step_modal'],
				'handler'    => [$this, 'handle_add_new_form_step_modal'],
				'capability' => 'wu_edit_checkout_forms',
			]
		);

		/*
		 * Add new Field
		 */
		wu_register_form(
			'add_new_form_field',
			[
				'render'     => [$this, 'render_add_new_form_field_modal'],
				'handler'    => [$this, 'handle_add_new_form_field_modal'],
				'capability' => 'wu_edit_checkout_forms',
			]
		);
	}

	/**
	 * Returns the list of available field types.
	 *
	 * @since 2.0.0
	 */
	public function field_types(): array {

		$field_type_objects = Signup_Fields_Manager::get_instance()->get_field_types();

		$fields = array_map(
			function ($class_name) {

				/** @var Base_Signup_Field $field */
				$field = new $class_name();

				/*
				* Remove the hidden fields.
				*/
				if ($field->is_hidden()) {
					return null;
				}

				return $field->get_field_as_type_option();
			},
			$field_type_objects
		);

		return array_filter($fields);
	}

	/**
	 * Returns the list of fields for the add/edit new field screen.
	 *
	 * @since 2.0.0
	 * @param array $attributes The field attributes.
	 * @return array
	 */
	public function get_create_field_fields($attributes = []) {

		$field_types = $this->field_types();

		$fields = [

			// Tab
			'tab'                     => [
				'type'              => 'tab-select',
				'value'             => 'field',
				'order'             => 0,
				'html_attr'         => [
					'v-model' => 'tab',
				],
				'options'           => [
					'content'  => __('Field', 'ultimate-multisite'),
					'advanced' => __('Additional Settings', 'ultimate-multisite'),
					'style'    => __('Style', 'ultimate-multisite'),
				],
				'wrapper_html_attr' => [
					'v-show' => 'type',
				],
			],

			// Content Tab
			'type'                    => [
				'type'              => 'select-icon',
				'title'             => __('Field Type', 'ultimate-multisite'),
				'desc'              => __('Select the type of field you want to add to the checkout form.', 'ultimate-multisite'),
				'placeholder'       => '',
				'tooltip'           => '',
				'value'             => '',
				'classes'           => 'wu-w-1/4',
				'options'           => $field_types,
				'html_attr'         => [
					'v-model' => 'type',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'type == ""',
					'v-cloak' => 1,
				],
			],
			'type_note'               => [
				'type'              => 'note',
				'order'             => 0,
				'desc'              => function () {
					printf('<a href="#" class="wu-no-underline wu-mt-1 wu-uppercase wu-text-2xs wu-font-semibold wu-text-gray-600" v-on:click.prevent="type = \'\'">%s</a>', esc_html__('&larr; Back to Field Type Selection', 'ultimate-multisite'));
				},
				'wrapper_html_attr' => [
					'v-show'  => 'type && (!saved && !name)',
					'v-cloak' => '1',
				],
			],
			'step'                    => [
				'type'  => 'hidden',
				'value' => wu_request('step'),
			],
			'checkout_form'           => [
				'type'  => 'hidden',
				'value' => wu_request('checkout_form'),
			],

			// Advanced Tab
			'from_request'            => [
				'type'              => 'toggle',
				'title'             => __('Pre-fill from Request', 'ultimate-multisite'),
				'tooltip'           => __('The key is the field slug. If your field has the slug "my-color" for example, adding ?my-color=blue will pre-fill this field with the value "blue".', 'ultimate-multisite'),
				'desc'              => __('Enable this to allow this field to be pre-filled based on the request parameters.', 'ultimate-multisite'),
				'value'             => 1,
				'order'             => 100,
				'html_attr'         => [
					'v-model'                  => 'from_request',
					'v-initempty:from_request' => 'true',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'type && require("tab", "advanced")',
					'v-cloak' => 1,
				],
			],

			'logged'                  => [
				'type'              => 'select',
				'value'             => 'always',
				'title'             => __('Field Visibility', 'ultimate-multisite'),
				'desc'              => __('Select the visibility of this field.', 'ultimate-multisite'),
				'options'           => [
					'always'      => __('Always show', 'ultimate-multisite'),
					'logged_only' => __('Only show for logged in users', 'ultimate-multisite'),
					'guests_only' => __('Only show for guests', 'ultimate-multisite'),
				],
				'html_attr'         => [
					'v-model' => 'logged',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'type && require("tab", "advanced")',
					'v-cloak' => 1,
				],
			],

			'original_id'             => [
				'type'      => 'hidden',
				'value'     => wu_request('id', ''),
				'html_attr' => [
					'v-bind:value' => 'original_id',
				],
			],

			// Style Tab
			'width'                   => [
				'type'              => 'number',
				'title'             => __('Wrapper Width', 'ultimate-multisite'),
				'placeholder'       => __('100', 'ultimate-multisite'),
				'desc'              => __('Set the width of this field wrapper (in %).', 'ultimate-multisite'),
				'min'               => 0,
				'max'               => 100,
				'value'             => 100,
				'order'             => 52,
				'html_attr'         => [
					'v-model' => 'width',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'type && require("tab", "style")',
					'v-cloak' => 1,
				],
			],
			'wrapper_element_classes' => [
				'type'              => 'text',
				'title'             => __('Wrapper CSS Classes', 'ultimate-multisite'),
				'placeholder'       => __('e.g. custom-field example-class', 'ultimate-multisite'),
				'desc'              => __('You can enter multiple CSS classes separated by spaces. These will be applied to the field wrapper element.', 'ultimate-multisite'),
				'value'             => '',
				'order'             => 54,
				'html_attr'         => [
					'v-model' => 'wrapper_element_classes',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'type && require("tab", "style")',
					'v-cloak' => 1,
				],
			],
			'element_classes'         => [
				'type'              => 'text',
				'title'             => __('Field CSS Classes', 'ultimate-multisite'),
				'placeholder'       => __('e.g. custom-field example-class', 'ultimate-multisite'),
				'desc'              => __('You can enter multiple CSS classes separated by spaces. These will be applied to the field element itself, when possible.', 'ultimate-multisite'),
				'value'             => '',
				'order'             => 56,
				'html_attr'         => [
					'v-model' => 'element_classes',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'type && require("tab", "style")',
					'v-cloak' => 1,
				],
			],
		];

		$additional_fields = [];

		foreach ($field_types as $field_type) {
			$_fields = call_user_func($field_type['fields'], $attributes);

			foreach ($_fields as $field_slug => $field) {
				if (isset($additional_fields[ $field_slug ])) {
					$additional_fields[ $field_slug ] = $this->merge_duplicate_field_type_field($additional_fields[ $field_slug ], $field);

					continue;
				}

				$additional_fields[ $field_slug ] = $field;
			}
		}

		$default_fields = \WP_Ultimo\Checkout\Signup_Fields\Base_Signup_Field::fields_list();

		$index = 0;

		foreach ($default_fields as $default_field_slug => &$default_field) {
			$default_field['order'] = $index + 10;

			++$index;

			$reqs = $this->get_required_list($default_field_slug, $field_types);

			$tab = wu_get_isset($default_field, 'tab', 'content');

			$default_field['wrapper_html_attr'] = array_merge(
				wu_get_isset($default_field, 'wrapper_html_attr', []),
				[
					'v-if'    => sprintf('type && require("type", %s) && require("tab", "%s")', wp_json_encode($reqs), $tab),
					'v-cloak' => '1',
				]
			);

			if ('name' === $default_field_slug || 'id' === $default_field_slug || 'default_value' === $default_field_slug) {
				unset($default_field['wrapper_html_attr']['v-if']);

				$default_field['wrapper_html_attr']['v-show'] = sprintf('type && require("type", %s) && require("tab", "%s")', wp_json_encode($reqs), $tab);
			}

			if ('id' === $default_field_slug) {
				$default_field['html_attr']['v-bind:required'] = sprintf('type && require("type", %s) && require("tab", "content")', wp_json_encode($reqs));
			}
		}

		$fields = array_merge(
			$fields,
			$default_fields,
			$additional_fields,
			[
				'submit_button' => [
					'type'              => 'submit',
					'title'             => empty($attributes) ? __('Add Field', 'ultimate-multisite') : __('Save Field', 'ultimate-multisite'),
					'value'             => 'save',
					'order'             => 100,
					'classes'           => 'button button-primary wu-w-full',
					'wrapper_classes'   => 'wu-items-end',
					'wrapper_html_attr' => [
						'v-show'  => 'type',
						'v-cloak' => '1',
					],
				],
			]
		);

		return $fields;
	}

	/**
	 * Merges editor fields that use the same attribute key across field types.
	 *
	 * Add-ons can register field types with editor attributes that collide with
	 * core field attributes, for example an add-on field that also uses a
	 * `product` selector. Keeping only the last field hides the earlier selector
	 * because its Vue visibility condition is replaced. Preserve the first field
	 * configuration and make it visible for both field types.
	 *
	 * @since 2.4.13
	 *
	 * @param array $existing_field Previously registered editor field.
	 * @param array $new_field      New editor field using the same key.
	 * @return array
	 */
	protected function merge_duplicate_field_type_field($existing_field, $new_field) {

		$existing_show = wu_get_isset($existing_field, 'wrapper_html_attr', [])['v-show'] ?? '';
		$new_show      = wu_get_isset($new_field, 'wrapper_html_attr', [])['v-show'] ?? '';

		if ($existing_show && $new_show && $existing_show !== $new_show) {
			$existing_field['wrapper_html_attr']['v-show'] = sprintf('(%s) || (%s)', $existing_show, $new_show);
		}

		return $existing_field;
	}

	/**
	 * Gets the field from the checkout step OR from the session.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Checkout_Form $checkout_form The checkout form.
	 * @param string                          $step_name The step name.
	 * @param string                          $field_name The field name.
	 * @return array
	 */
	protected function get_field($checkout_form, $step_name, $field_name) {

		$field = $checkout_form->get_field($step_name, $field_name);

		if ( ! $field) {
			$field = [
				'saved' => false,
			];
		} else {
			$field['saved'] = true;
		}

		$key = wp_get_session_token();

		$session = \WP_Session_Tokens::get_instance(get_current_user_id());

		$settings = wu_get_isset($session->get($key), 'wu_checkout_form_editor', []);

		if ( ! empty($settings)) {
			$checkout_form->set_settings($settings);

			$new_field = $checkout_form->get_field($step_name, $field_name);

			if (is_array($new_field)) {
				$field = array_merge($field, $new_field);
			}
		}

		return $field;
	}

	/**
	 * Gets the step from the checkout OR from the session.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Checkout_Form $checkout_form The checkout form.
	 * @param string                          $step_name The step name.
	 * @return array
	 */
	protected function get_step($checkout_form, $step_name) {

		$step = $checkout_form->get_step($step_name);

		if ( ! $step) {
			$step = [
				'saved' => false,
			];
		} else {
			$step['saved'] = true;
		}

		$key = wp_get_session_token();

		$session = \WP_Session_Tokens::get_instance(get_current_user_id());

		$settings = wu_get_isset($session->get($key), 'wu_checkout_form_editor');

		if ($settings) {
			$checkout_form->set_settings($settings);

			$step = $checkout_form->get_step($step_name);
		}

		return $step;
	}

	/**
	 * Adds the modal for adding new fields.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_add_new_form_field_modal(): void {

		$checkout_form = wu_get_checkout_form_by_slug(wu_request('checkout_form'));

		if ( ! $checkout_form) {
			return;
		}

		$steps = $checkout_form->get_settings();

		$step_name = wu_request('step', current(array_keys($steps)));

		$field_name = wu_request('field');

		$_field = $this->get_field($checkout_form, $step_name, $field_name);

		$edit_fields = $this->get_create_field_fields($_field);

		$state = array_map(
			function ($field) {

				$value = wu_get_isset($field, 'value', wu_get_isset($field, 'default', ''));

				return $value;
			},
			$edit_fields
		);

		if ($_field) {
			$state = array_merge($state, $_field);
		}

		$state = array_map(
			function ($value) {

				if ('false' === $value || 'true' === $value) {
					$value = (int) wu_string_to_bool($value);
				}

				return $value;
			},
			$state
		);

		$state['tab'] = 'content';

		if ( ! wu_get_isset($state, 'logged', false)) {
			$state['logged'] = 'always';
		}

		if ( ! wu_get_isset($state, 'period_options', false)) {
			$state['period_options'] = [
				[
					'duration'      => 1,
					'duration_unit' => 'month',
					'label'         => __('Monthly', 'ultimate-multisite'),
				],
			];
		}

		if ( ! wu_get_isset($state, 'options', false)) {
			$state['options'] = [];
		}

		if ( ! wu_get_isset($state, 'save_as', false)) {
			$state['save_as'] = 'customer_meta';
		}

		if ( ! wu_get_isset($state, 'auto_generate', false)) {
			$state['auto_generate'] = 0;
		}

		if ( ! wu_get_isset($state, 'original_id', false)) {
			$state['original_id'] = wu_get_isset($state, 'id', '');
		}

		$state['from_request'] = wu_string_to_bool(wu_get_isset($state, 'from_request', true));

		uasort($edit_fields, 'wu_sort_by_order');

		$form = new \WP_Ultimo\UI\Form(
			'add_edit_fields_modal',
			$edit_fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'add_checkout_form_field',
					'data-state'  => wu_convert_to_state($state),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the submission of a new form field modal submission.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_add_new_form_field_modal(): void {

		$checkout_form = wu_get_checkout_form_by_slug(wu_request('checkout_form'));

		if ( ! $checkout_form) {
			wp_send_json_error(
				new \WP_Error(
					'checkout-form-not-found',
					__('The checkout form could not be found.', 'ultimate-multisite')
				)
			);
		}

		$data = [
			'id'           => wu_request('id', ''),
			'original_id'  => wu_request('original_id', ''),
			'step'         => wu_request('step'),
			'name'         => wu_request('label', ''),
			'type'         => wu_request('type', 'text'),
			'from_request' => wu_request('from_request', false),
		];

		$type = wu_request('type', 'text');

		$field_types = $this->field_types();

		$all_attributes_list = array_combine($field_types[ $type ]['all_attributes'], $field_types[ $type ]['all_attributes']);

		$data = array_merge(
			$data,
			$field_types[ $type ]['force_attributes'],
			array_map(fn($item) => wu_request($item, wu_get_isset($data, $item, '')), $all_attributes_list)
		);

		/**
		 * Auto-assign ID if none is set
		 */
		if (wu_get_isset($data, 'id', '') === '') {
			$data['id'] = wu_get_isset($data, 'type', 'field') . '-' . uniqid();
		}

		/*
		 * Allow developers to change the id of the fields.
		 */
		$data['id'] = apply_filters("wu_checkout_form_field_{$type}_id", $data['id'], $data, $checkout_form, $type);

		wp_send_json_success(
			[
				'send' => [
					'scope'         => 'wu_checkout_forms_editor_app',
					'function_name' => 'add_field',
					'data'          => $data,
				],
			]
		);
	}

	/**
	 * Renders the content of the edit-add section modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_add_new_form_step_modal(): void {

		$checkout_form = wu_get_checkout_form_by_slug(wu_request('checkout_form'));

		if ( ! $checkout_form) {
			return;
		}

		$steps = $checkout_form->get_settings();

		$step_name = wu_request('step', current(array_keys($steps)));

		$_step = $this->get_step($checkout_form, $step_name);

		$fields = [
			'tab'           => [
				'type'      => 'tab-select',
				'value'     => 'content',
				'order'     => 0,
				'html_attr' => [
					'v-model' => 'tab',
				],
				'options'   => [
					'content'    => __('Content', 'ultimate-multisite'),
					'visibility' => __('Visibility', 'ultimate-multisite'),
					'style'      => __('Style', 'ultimate-multisite'),
				],
			],

			// Content Tab
			'id'            => [
				'type'              => 'text',
				'title'             => __('Step ID', 'ultimate-multisite'),
				'placeholder'       => __('e.g. step-name', 'ultimate-multisite'),
				'desc'              => __('This will be used on the URL. Only alpha-numeric and hyphens allowed.', 'ultimate-multisite'),
				'value'             => '',
				'html_attr'         => [
					'v-on:input'   => 'id = $event.target.value.toLowerCase().replace(/[^a-z0-9-_]+/g, "")',
					'v-bind:value' => 'id',
					'required'     => 'require("tab", "content")',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "content")',
					'v-cloak' => 1,
				],
			],
			'original_id'   => [
				'type'      => 'hidden',
				'value'     => wu_request('id', ''),
				'html_attr' => [
					'v-bind:value' => 'original_id',
				],
			],
			'name'          => [
				'type'              => 'text',
				'title'             => __('Step Title', 'ultimate-multisite'),
				'placeholder'       => __('e.g. My Extra Step', 'ultimate-multisite'),
				'desc'              => __('Mostly used internally, but made available for templates.', 'ultimate-multisite'),
				'tooltip'           => '',
				'value'             => '',
				'html_attr'         => [
					'v-model'  => 'name',
					'required' => 'require("tab", "content")',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "content")',
					'v-cloak' => 1,
				],
			],
			'desc'          => [
				'type'              => 'textarea',
				'title'             => __('Step Description', 'ultimate-multisite'),
				'placeholder'       => __('e.g. This is the last step!', 'ultimate-multisite'),
				'desc'              => __('Mostly used internally, but made available for templates.', 'ultimate-multisite'),
				'tooltip'           => '',
				'value'             => '',
				'html_attr'         => [
					'v-model' => 'desc',
					'rows'    => 3,
				],
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "content")',
					'v-cloak' => 1,
				],
			],

			// Visibility Tab
			'logged'        => [
				'type'              => 'select',
				'value'             => 'always',
				'title'             => __('Logged Status', 'ultimate-multisite'),
				'desc'              => __('Select the visibility of this step.', 'ultimate-multisite'),
				'options'           => [
					'always'      => __('Always show', 'ultimate-multisite'),
					'logged_only' => __('Only show for logged in users', 'ultimate-multisite'),
					'guests_only' => __('Only show for guests', 'ultimate-multisite'),
				],
				'html_attr'         => [
					'v-model' => 'logged',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "visibility")',
					'v-cloak' => 1,
				],
			],

			// Style Tab
			'element_id'    => [
				'type'              => 'text',
				'title'             => __('Element ID', 'ultimate-multisite'),
				'placeholder'       => __('myfield', 'ultimate-multisite'),
				'desc'              => __('A custom ID to be added to the form element. Do not add the # symbol.', 'ultimate-multisite'),
				'value'             => '',
				'html_attr'         => [
					'v-model' => 'element_id',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "style")',
					'v-cloak' => 1,
				],
			],

			'classes'       => [
				'type'              => 'text',
				'title'             => __('Extra CSS Classes', 'ultimate-multisite'),
				'placeholder'       => __('custom-field example-class', 'ultimate-multisite'),
				'desc'              => __('You can enter multiple CSS classes separated by spaces.', 'ultimate-multisite'),
				'value'             => '',
				'html_attr'         => [
					'v-model' => 'classes',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'require("tab", "style")',
					'v-cloak' => 1,
				],
			],

			// Submit Button
			'submit_button' => [
				'type'              => 'submit',
				'title'             => empty($_step) ? __('Add Step', 'ultimate-multisite') : __('Save Step', 'ultimate-multisite'),
				'value'             => 'save',
				'classes'           => 'button button-primary wu-w-full',
				'wrapper_classes'   => 'wu-items-end',
				'wrapper_html_attr' => [],
			],
			'step'          => [
				'type'  => 'hidden',
				'value' => wu_request('step'),
			],
			'checkout_form' => [
				'type'  => 'hidden',
				'value' => wu_request('checkout_form'),
			],
		];

		$state = array_map('__return_empty_string', $fields);

		if ($_step) {
			$state = array_merge($state, $_step);
		}

		$state['tab'] = 'content';

		$state['logged'] = wu_get_isset($state, 'logged', 'always');

		if ( ! wu_get_isset($state, 'original_id', false)) {
			$state['original_id'] = wu_get_isset($state, 'id', '');
		}

		$form = new \WP_Ultimo\UI\Form(
			'add_new_form_step',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'add_checkout_form_field',
					'data-state'  => wu_convert_to_state($state),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the form used to add a new step to the signup.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_add_new_form_step_modal(): void {

		$checkout_form = wu_get_checkout_form_by_slug(wu_request('checkout_form'));

		if ( ! $checkout_form) {
			wp_send_json_error(
				new \WP_Error(
					'checkout-form-not-found',
					__('The checkout form could not be found.', 'ultimate-multisite')
				)
			);
		}

		$data = [
			'id'          => wu_request('id', ''),
			'original_id' => wu_request('original_id', ''),
			'name'        => wu_request('name', ''),
			'desc'        => wu_request('desc', ''),
			'element_id'  => wu_request('element_id', ''),
			'classes'     => wu_request('classes', ''),
			'logged'      => wu_request('logged', 'always'),
			'fields'      => [],
		];

		wp_send_json_success(
			[
				'send' => [
					'scope'         => 'wu_checkout_forms_editor_app',
					'function_name' => 'add_step',
					'data'          => $data,
				],
			]
		);
	}

	/**
	 * Get the required fields for a given field-type.
	 *
	 * @since 2.0.0
	 *
	 * @param string $field_slug Field slug to check.
	 * @param array  $field_types List of available field types.
	 */
	public function get_required_list($field_slug, $field_types): array {

		$fields = \Arrch\Arrch::find(
			$field_types,
			[
				'sort_key' => 'order',
				'where'    => [
					['default_fields', '~', $field_slug],
				],
			]
		);

		return array_keys($fields);
	}

	// Render JS Templates

	/**
	 * Render the steps to be used by Vue.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_steps(): void {

		wu_get_template(
			'base/checkout-forms/steps',
			[
				'checkout_form'                      => $this->get_object()->get_slug(),
				'price_variation_notice_products'    => $this->get_price_variation_notice_products(),
				'template_selection_notice_products' => $this->get_template_selection_notice_products(),
			]
		);
	}

	/**
	 * Returns the names of products that do NOT force a specific site template
	 * when the checkout form has no template selection field.
	 *
	 * Used to render an admin notice in the editor warning that the template
	 * selection field is required for customers to pick a site template.
	 *
	 * @since 2.4.13
	 * @return array
	 */
	protected function get_template_selection_notice_products(): array {

		$form = $this->get_object();

		if ( ! is_a($form, \WP_Ultimo\Models\Checkout_Form::class)) {
			return [];
		}

		$template_selection_fields = $form->get_all_fields_by_type('template_selection');

		if ( ! empty($template_selection_fields)) {
			return [];
		}

		$product_id_fields = $form->get_all_fields_by_type(['pricing_table', 'products']);

		if (empty($product_id_fields)) {
			return [];
		}

		$product_ids = [];

		foreach ($product_id_fields as $field) {
			$ids_string = '';

			if ('pricing_table' === ($field['type'] ?? '')) {
				$ids_string = (string) ($field['pricing_table_products'] ?? '');
			} elseif ('products' === ($field['type'] ?? '')) {
				$ids_string = (string) ($field['products'] ?? '');
			}

			if ('' === $ids_string) {
				continue;
			}

			foreach (explode(',', $ids_string) as $id) {
				$id = absint(trim($id));

				if ($id) {
					$product_ids[ $id ] = $id;
				}
			}
		}

		if (empty($product_ids)) {
			return [];
		}

		$names = [];

		foreach ($product_ids as $product_id) {
			$product = wu_get_product($product_id);

			if ( ! $product) {
				continue;
			}

			$limitations = $product->get_limitations();

			if ( ! $limitations || ! isset($limitations->site_templates)) {
				$names[] = $product->get_name();
				continue;
			}

			$mode = $limitations->site_templates->get_mode();

			if (\WP_Ultimo\Limitations\Limit_Site_Templates::MODE_ASSIGN_TEMPLATE !== $mode) {
				$names[] = $product->get_name();
			}
		}

		return $names;
	}

	/**
	 * Returns the names of products with price variations that lack a period
	 * selection field on this checkout form.
	 *
	 * Used to render an admin notice in the editor warning that the period
	 * selection field is required for price variations to be selectable.
	 *
	 * @since 2.4.13
	 * @return array
	 */
	protected function get_price_variation_notice_products(): array {

		$form = $this->get_object();

		if ( ! is_a($form, \WP_Ultimo\Models\Checkout_Form::class)) {
			return [];
		}

		$period_selection_fields = $form->get_all_fields_by_type('period_selection');

		if ( ! empty($period_selection_fields)) {
			return [];
		}

		$product_id_fields = $form->get_all_fields_by_type(['pricing_table', 'products']);

		if (empty($product_id_fields)) {
			return [];
		}

		$product_ids = [];

		foreach ($product_id_fields as $field) {
			$ids_string = '';

			if ('pricing_table' === ($field['type'] ?? '')) {
				$ids_string = (string) ($field['pricing_table_products'] ?? '');
			} elseif ('products' === ($field['type'] ?? '')) {
				$ids_string = (string) ($field['products'] ?? '');
			}

			if ('' === $ids_string) {
				continue;
			}

			foreach (explode(',', $ids_string) as $id) {
				$id = absint(trim($id));

				if ($id) {
					$product_ids[ $id ] = $id;
				}
			}
		}

		if (empty($product_ids)) {
			return [];
		}

		$names = [];

		foreach ($product_ids as $product_id) {
			$product = wu_get_product($product_id);

			if ( ! $product) {
				continue;
			}

			$variations = $product->get_price_variations();

			if ( ! empty($variations)) {
				$names[] = $product->get_name();
			}
		}

		return $names;
	}

	/**
	 * Renders the Vue JS Templates.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_js_templates(): void {

		wu_get_template(
			'base/checkout-forms/js-templates',
			[
				'checkout_form' => $this->get_object()->get_slug(),
			]
		);
	}

	// Boilerplate

	/**
	 * Registers the necessary scripts and styles for this admin page.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		parent::register_scripts();

		wp_enqueue_code_editor(['type' => 'text/html']);

		wp_enqueue_script('csslint');

		wp_enqueue_script('htmlhint');

		WP_Ultimo()->scripts->register_script('wu-checkout-form-editor', wu_get_asset('checkout-forms-editor.js', 'js'), ['jquery', 'wu-vue', 'underscore', 'wu-vue-sortable', 'wu-vue-draggable']);

		$steps = $this->get_object()->get_settings();

		wp_localize_script(
			'wu-checkout-form-editor',
			'wu_checkout_form',
			[
				'form_id'       => $this->get_object()->get_id(),
				'checkout_form' => $this->get_object()->get_slug(),
				'register_page' => wu_get_registration_url(),
				'steps'         => $steps,
				'headers'       => [
					'order' => __('Order', 'ultimate-multisite'),
					'name'  => __('Label', 'ultimate-multisite'),
					'type'  => __('Type', 'ultimate-multisite'),
					'slug'  => __('Slug', 'ultimate-multisite'),
					'move'  => '',
				],
			]
		);

		wp_enqueue_script('wu-checkout-form-editor');

		wp_enqueue_style('wu-checkout-form-editor', wu_get_asset('checkout-editor.css', 'css'), [], wu_get_version());
	}

	/**
	 * Returns the array of thank you page fields, based on the element.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_thank_you_page_fields() {

		$thank_you_settings = $this->get_thank_you_settings();

		$fields = \WP_Ultimo\UI\Thank_You_Element::get_instance()->fields();

		$new_fields = [];

		foreach ($fields as $index => $field) {
			if ('header' === $field['type']) {
				continue;
			}

			if (wu_get_isset($thank_you_settings, $index)) {
				$field['value'] = $thank_you_settings[ $index ];
			}

			$new_fields[ "meta[wu_thank_you_settings][$index]" ] = $field;
		}

		$placeholders = [
			'CUSTOMER_ID',
			'CUSTOMER_EMAIL',
			'MEMBERSHIP_DURATION',
			'MEMBERSHIP_PLAN',
			'MEMBERSHIP_AMOUNT',
			'ORDER_ID',
			'ORDER_CURRENCY',
			'ORDER_PRODUCTS',
			'ORDER_AMOUNT',
		];

		$fields_placeholder = '<code>%%' . implode('%% | %%', $placeholders) . '%%</code>';

		$new_fields['conversion_snippets'] = [
			'type'  => 'code-editor',
			'title' => __('Conversion Snippets', 'ultimate-multisite'),
			// translators: %s is a list of placeholders.
			'desc'  => sprintf(__('Add custom snippets in HTML (with javascript support) to add conversion tracking pixels and such. This code is only run on the successful Thank You step.<br> Available placeholders are: %s', 'ultimate-multisite'), $fields_placeholder),
			'value' => $this->get_object()->get_conversion_snippets(),
			'lang'  => 'htmlmixed',
		];

		return $new_fields;
	}

	/**
	 * Returns the values of the thank you page settings.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_thank_you_settings() {

		$defaults = \WP_Ultimo\UI\Thank_You_Element::get_instance()->defaults();

		$settings = wp_parse_args($this->get_object()->get_meta('wu_thank_you_settings'), $defaults);

		return $settings;
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		parent::register_widgets();

		$this->add_tabs_widget(
			'advanced',
			[
				'title'     => __('Advanced Options', 'ultimate-multisite'),
				'position'  => 'advanced',
				'html_attr' => [
					'data-on-load' => 'wu_initialize_code_editors',
				],
				'sections'  => [
					'thank-you'    => [
						'title'  => __('Thank You', 'ultimate-multisite'),
						'desc'   => __('Configure the Thank You page for this Checkout Form.', 'ultimate-multisite'),
						'icon'   => 'dashicons-wu-emoji-happy',
						'state'  => [
							'enable_thank_you_page' => $this->get_object()->has_thank_you_page(),
							'thank_you_page'        => $this->get_object()->get_thank_you_page_id(),
						],
						'fields' => $this->get_thank_you_page_fields(),
					],
					'scripts'      => [
						'title'  => __('Scripts', 'ultimate-multisite'),
						'desc'   => __('Configure the Thank You page for this Checkout Form.', 'ultimate-multisite'),
						'icon'   => 'dashicons-wu-code',
						'state'  => [
							'enable_thank_you_page' => $this->get_object()->has_thank_you_page(),
							'thank_you_page'        => $this->get_object()->get_thank_you_page_id(),
						],
						'fields' => [
							'custom_css' => [
								'type'  => 'code-editor',
								'title' => __('Custom CSS', 'ultimate-multisite'),
								'desc'  => __('Add custom CSS code to your checkout form. SCSS syntax is supported.', 'ultimate-multisite'),
								'value' => $this->get_object()->get_custom_css(),
								'lang'  => 'css',
							],
						],
					],
					'restrictions' => [
						'title'  => __('Restrictions', 'ultimate-multisite'),
						'desc'   => __('Control the access to this checkout form.', 'ultimate-multisite'),
						'icon'   => 'dashicons-wu-block',
						'state'  => [
							'restrict_by_country' => $this->get_object()->has_country_lock(),
						],
						'fields' => [
							'restrict_by_country' => [
								'type'      => 'toggle',
								'title'     => __('Restrict by Country', 'ultimate-multisite'),
								'desc'      => __('Restrict this checkout form to specific countries.', 'ultimate-multisite'),
								'html_attr' => [
									'v-model' => 'restrict_by_country',
								],
							],
							'allowed_countries'   => [
								'type'              => 'select',
								'title'             => __('Allowed Countries', 'ultimate-multisite'),
								'desc'              => __('Select the allowed countries.', 'ultimate-multisite'),
								'placeholder'       => __('Type to search countries...', 'ultimate-multisite'),
								'options'           => 'wu_get_countries',
								'value'             => $this->get_object()->get_allowed_countries(),
								'wrapper_html_attr' => [
									'v-show' => 'require("restrict_by_country", true)',
								],
								'html_attr'         => [
									'v-cloak'        => 1,
									'data-selectize' => 1,
									'multiple'       => true,
								],
							],
						],
					],
				],
			]
		);

		$this->add_list_table_widget(
			'events',
			[
				'title'        => __('Events', 'ultimate-multisite'),
				'table'        => new \WP_Ultimo\List_Tables\Inside_Events_List_Table(),
				'query_filter' => [$this, 'query_filter'],
				'position'     => 'advanced',
			]
		);

		$this->add_save_widget(
			'save',
			[
				'html_attr' => [
					'data-wu-app' => 'checkout-form',
					'data-state'  => wu_convert_to_state(
						[
							'original_slug' => $this->get_object()->get_slug(),
							'slug'          => $this->get_object()->get_slug(),
						]
					),
				],
				'fields'    => [
					'slug'             => [
						'type'              => 'text',
						'title'             => __('Checkout Form Slug', 'ultimate-multisite'),
						'desc'              => __('This is used to create shortcodes and more.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_slug(),
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'required'     => 'required',
							'v-on:input'   => 'slug = $event.target.value.toLowerCase().replace(/[^a-z0-9-_]+/g, "")',
							'v-bind:value' => 'slug',
						],
					],
					'slug_change_note' => [
						'type'              => 'note',
						'desc'              => __('You are changing the form slug. If you save this change, all the shortcodes and blocks referencing this slug will stop working until you update them with the new slug.', 'ultimate-multisite'),
						'classes'           => 'wu-p-2 wu-bg-yellow-200 wu-text-yellow-700 wu-rounded wu-w-full',
						'wrapper_html_attr' => [
							'v-show'  => '(original_slug != slug) && slug',
							'v-cloak' => '1',
						],
					],
				],
			]
		);

		$this->add_fields_widget(
			'active',
			[
				'title'  => __('Active', 'ultimate-multisite'),
				'fields' => [
					'active' => [
						'type'  => 'toggle',
						'title' => __('Active', 'ultimate-multisite'),
						'desc'  => __('Use this option to manually enable or disable this checkout form.', 'ultimate-multisite'),
						'value' => $this->get_object()->is_active(),
					],
				],
			]
		);

		\WP_Ultimo\UI\Tours::get_instance()->create_tour(
			'checkout-form-editor',
			[
				[
					'id'    => 'checkout-form-editor',
					'title' => __('Welcome to the Checkout Form builder!', 'ultimate-multisite'),
					'text'  => [
						__('You should be able to create registration forms in any way, shape, and form you desire. This editor allows you to do just that 😃', 'ultimate-multisite'),
						__('Want a registration form with multiple steps? Check! A single step? Check! Control the visibility of certain steps and fields based on the context of the customer? Check!', 'ultimate-multisite'),
					],
				],
				[
					'id'       => 'add-new-step',
					'title'    => __('Adding new Steps', 'ultimate-multisite'),
					'text'     => [
						__('To add a new step to the registration form, use this button here.', 'ultimate-multisite'),
					],
					'attachTo' => [
						'element' => '#wp-ultimo-list-table-add-new-1 > div > div.wu-w-1\/2.wu-text-right > ul > li:nth-child(2) > a',
						'on'      => 'left',
					],
				],
				[
					'id'       => 'add-new-field',
					'title'    => __('Adding new Fields', 'ultimate-multisite'),
					'text'     => [
						__('To add a new field to a step, use this button here. You can add fields to capture additional data from your customers and use that data to populate site templates.', 'ultimate-multisite'),
						sprintf('<a class="wu-no-underline" href="%s" target="_blank">%s</a>', wu_get_documentation_url('wp-ultimo-populate-site-template'), __('You can learn more about that here &rarr;', 'ultimate-multisite')),
					],
					'attachTo' => [
						'element' => '#wp-ultimo-list-table-checkout > div.inside > div.wu-bg-gray-100.wu-px-4.wu-py-3.wu--m-3.wu-mt-3.wu-border-t.wu-border-l-0.wu-border-r-0.wu-border-b-0.wu-border-gray-400.wu-border-solid.wu-text-right > ul > li:nth-child(3) > a',
						'on'      => 'left',
					],
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

		return $this->edit ? __('Edit Checkout Form', 'ultimate-multisite') : __('Add new Checkout Form', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Edit Checkout_Form', 'ultimate-multisite');
	}

	/**
	 * Returns the labels to be used on the admin page.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_labels() {

		return [
			'edit_label'          => __('Edit Checkout Form', 'ultimate-multisite'),
			'add_new_label'       => __('Add new Checkout Form', 'ultimate-multisite'),
			'updated_message'     => __('Checkout Form updated with success!', 'ultimate-multisite'),
			'title_placeholder'   => __('Enter Checkout Form Name', 'ultimate-multisite'),
			'title_description'   => __('This name is used for internal reference only.', 'ultimate-multisite'),
			'save_button_label'   => __('Save Checkout Form', 'ultimate-multisite'),
			'save_description'    => '',
			'delete_button_label' => __('Delete Checkout Form', 'ultimate-multisite'),
			'delete_description'  => __('Be careful. This action is irreversible.', 'ultimate-multisite'),
		];
	}

	/**
	 * Filters the list table to return only relevant events.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Query args passed to the list table.
	 * @return array Modified query args.
	 */
	public function query_filter($args) {

		$extra_args = [
			'object_type' => 'checkout_form',
			'object_id'   => absint($this->get_object()->get_id()),
		];

		return array_merge($args, $extra_args);
	}

	/**
	 * Returns the object being edit at the moment.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Checkout_Form
	 */
	public function get_object() {

		if (null !== $this->object) {
			return $this->object;
		}

		$item_id = wu_request('id', 0);

		$item = wu_get_checkout_form($item_id);

		if ( ! $item) {
			wp_safe_redirect(wu_network_admin_url('wp-ultimo-checkout-forms'));

			exit;
		}

		$this->object = $item;

		return $this->object;
	}

	/**
	 * Should implement the processes necessary to save the changes made to the object.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function handle_save() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens in parent::handle_save()
		if ( ! wu_request('restrict_by_country') || (isset($_POST['allowed_countries']) && empty($_POST['allowed_countries']))) {
			$_POST['allowed_countries'] = [];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens in parent::handle_save()
		if (isset($_POST['_settings'])) {
			// We're using json_decode which handles sanitization
			$this->get_object()->set_settings(json_decode(wp_unslash((string) $_POST['_settings']), true)); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			unset($_POST['_settings']);
		}

		/**
		 * Prevent parents redirect to perform additional checks to destroy session.
		 */
		ob_start();

		$result = parent::handle_save();

		$object = $this->get_object();

		$key = wp_get_session_token();

		if ( ! is_wp_error($object->validate())) {
			$session = \WP_Session_Tokens::get_instance(get_current_user_id());

			$session_data = $session->get($key);

			unset($session_data['wu_checkout_form_editor']);

			$session->update($key, $session_data);
		}

		wp_ob_end_flush_all();
		return $result;
	}

	/**
	 * Checkout_Forms have titles.
	 *
	 * @since 2.0.0
	 */
	public function has_title(): bool {

		return true;
	}
}
