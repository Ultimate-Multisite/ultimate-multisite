<?php
/**
 * The Checkout Form model for the Checkout_Form Mappings.
 *
 * @package WP_Ultimo
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Models;

use WP_Ultimo\Models\Base_Model;
use Arrch\Arrch as Array_Search;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Checkout Form model class. Implements the Base Model.
 *
 * @since 2.0.0
 */
class Checkout_Form extends Base_Model {

	/**
	 * Meta key for thank you page ID.
	 */
	const META_THANK_YOU_PAGE_ID = 'wu_thank_you_page_id';

	/**
	 * Meta key for conversion snippets.
	 */
	const META_CONVERSION_SNIPPETS = 'wu_conversion_snippets';

	/**
	 * @var array<string, int>|array<string, string>
	 */
	public $meta;

	/**
	 * The name of the checkout form.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $name;

	/**
	 * Slug of the checkout form.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $slug = '';

	/**
	 * Is this checkout form active?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $active = true;

	/**
	 * Settings which hold the fields of the checkout form
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $settings;

	/**
	 * Custom CSS code.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $custom_css;

	/**
	 * Countries allowed on this checkout.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $allowed_countries;

	/**
	 * Thank you page id, if set.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $thank_you_page_id;

	/**
	 * Custom Snippets code.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $conversion_snippets;

	/**
	 * Set a template to use.
	 *
	 * @since 2.0.0
	 * @var string can be either 'blank', 'single-step' or 'multi-step'
	 */
	protected $template;

	/**
	 * Query Class to the static query methods.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Checkout_Forms\Checkout_Form_Query::class;

	/**
	 * The steps to show to user in checkout form.
	 *
	 * @since 2.0.19
	 * @var array
	 */
	protected $steps_to_show;

	/**
	 * Set the validation rules for this particular model.
	 *
	 * To see how to setup rules, check the documentation of the
	 * validation library we are using: https://github.com/rakit/validation
	 *
	 * @since 2.0.0
	 * @link https://github.com/rakit/validation
	 * @return array
	 */
	public function validation_rules() {

		$id = $this->get_id();

		return [
			'name'                => 'required',
			'slug'                => "required|unique:\WP_Ultimo\Models\Checkout_Form,slug,{$id}|min:3",
			'active'              => 'required|default:1',
			'custom_css'          => 'default:',
			'settings'            => 'checkout_steps',
			'allowed_countries'   => 'default:',
			'thank_you_page_id'   => 'integer',
			'conversion_snippets' => 'nullable|default:',
			'template'            => 'in:blank,single-step,multi-step,simple',
		];
	}

	/**
	 * Get the object type associated with this event.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_slug() {

		return $this->slug;
	}

	/**
	 * Set the checkout form slug
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug The checkout form slug. It needs to be unique and preferably make it clear what it is about. E.g. my_checkout_form.
	 * @return void
	 */
	public function set_slug($slug): void {

		$this->slug = $slug;
	}

	/**
	 * Get the name of the checkout form.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_name() {

		return $this->name;
	}

	/**
	 * Set the name of the checkout form.
	 *
	 * @since 2.0.0
	 * @param string $name Your checkout form name, which is used as checkout form title as well.
	 * @return void
	 */
	public function set_name($name): void {

		$this->name = $name;
	}

	/**
	 * Get is this checkout form active?
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_active() {

		return (bool) $this->active;
	}

	/**
	 * Set is this checkout form active?
	 *
	 * @since 2.0.0
	 * @param boolean $active Set this checkout form as active (true), which means available to be used, or inactive (false).
	 * @return void
	 */
	public function set_active($active): void {

		$this->active = (bool) $active;
	}

	/**
	 * Get custom CSS code.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_custom_css() {

		return $this->custom_css;
	}

	/**
	 * Set custom CSS code.
	 *
	 * @since 2.0.0
	 * @param string $custom_css Custom CSS code for the checkout form.
	 * @return void
	 */
	public function set_custom_css($custom_css): void {

		$this->custom_css = $custom_css;
	}

	/**
	 * Get settings of the event.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_settings() {

		if (empty($this->settings)) {
			return [];
		}

		if (is_string($this->settings)) {
			$this->settings = maybe_unserialize($this->settings);
		}

		return $this->settings;
	}

	/**
	 * Set settings of the checkout form.
	 *
	 * @since 2.0.0
	 * @param array|string $settings The checkout form settings and configurations.
	 * @return void
	 */
	public function set_settings($settings): void {

		if (is_string($settings)) {
			try {
				$settings = maybe_unserialize(stripslashes($settings));
			} catch (\Throwable $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Silence is golden.
			}
		}

		$this->settings = $settings;
	}

	/**
	 * Returns a specific step by the step name.
	 *
	 * @since 2.0.0
	 *
	 * @param string $step_name Name of the step. E.g. 'account'.
	 * @param bool   $to_show   If we want the steps to show in a form.
	 * @return mixed[]|false
	 */
	public function get_step($step_name, $to_show = false) {

		$settings = $to_show ? $this->get_steps_to_show() : $this->get_settings();

		$step_key = array_search($step_name, array_column($settings, 'id'), true);

		$step = false !== $step_key ? $settings[ $step_key ] : false;

		if ($step) {
			$step = wp_parse_args(
				$step,
				[
					'logged' => 'always',
					'fields' => [],
				]
			);
		}

		return $step;
	}

	/**
	 * Returns the steps to show in current form
	 *
	 * @since 2.0.19
	 * @return mixed[]|false
	 */
	public function get_steps_to_show() {

		if ($this->steps_to_show) {
			return $this->steps_to_show;
		}

		$steps = $this->get_settings();

		$user_exists = is_user_logged_in();

		$final_steps = [];

		$non_data_fields = [
			'submit_button',
			'period_selection',
			'steps',
		];

		$hidden_fields = [
			'hidden',
		];

		$final_data_fields = [];

		foreach ($steps as $key => $step) {
			$logged = wu_get_isset($step, 'logged', 'always');

			$show = 'always' === $logged;

			if ('guests_only' === $logged && ! $user_exists) {
				$show = true;
			} elseif ('logged_only' === $logged && $user_exists) {
				$show = true;
			}

			if ( ! $show) {
				continue;
			}

			$data_fields = array_filter($step['fields'], fn($field) => ! in_array($field['type'], $non_data_fields, true));

			$not_hidden_fields = array_filter($data_fields, fn($field) => ! in_array($field['type'], $hidden_fields, true) && ! wu_should_hide_form_field($field));

			if (empty($not_hidden_fields)) {
				$final_data_fields = array_merge($final_data_fields, $data_fields);
			} else {
				$final_steps[] = $step;
			}
		}

		if ( ! empty($final_steps) && ! empty($final_data_fields)) {
			$key = array_key_last($final_steps);

			$final_steps[ $key ]['fields'] = array_merge($final_data_fields, $final_steps[ $key ]['fields']);
		}

		$this->steps_to_show = $final_steps;

		return $final_steps;
	}

	/**
	 * Returns a specific field by the step name and field name.
	 *
	 * @since 2.0.0
	 *
	 * @param string $step_name Name of the step. E.g. 'account'.
	 * @param string $field_name Name of the field. E.g. 'username'.
	 * @return array|false
	 */
	public function get_field($step_name, $field_name) {

		$step = $this->get_step($step_name);

		if ( ! is_array($step)) {
			return false;
		}

		$field_key = array_search($field_name, array_column($step['fields'], 'id'), true);

		return false !== $field_key ? $step['fields'][ $field_key ] : false;
	}

	/**
	 * Returns all the fields from all steps.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_all_fields() {

		$settings = $this->get_settings();

		if ( ! is_array($settings)) {
			return [];
		}

		$fields = array_column($settings, 'fields');

		if (empty($fields)) {
			return [];
		}

		return call_user_func_array('array_merge', $fields);
	}

	/**
	 * Get all fields of a given type.
	 *
	 * @param string|array $type The field type or types to search for.
	 * @return array
	 */
	public function get_all_fields_by_type($type) {

		$all_fields = $this->get_all_fields();

		$types = (array) $type;

		return (array) Array_Search::find(
			$all_fields,
			[
				'where' => [
					['type', $types],
				],
			]
		);
	}

	/**
	 * Get fields that are meta-related.
	 *
	 * @since 2.0.0
	 *
	 * @param string $meta_type The meta type.
	 * @return array
	 */
	public function get_all_meta_fields($meta_type = 'customer_meta') {

		$all_fields = $this->get_all_fields();

		$types = apply_filters('wu_checkout_form_meta_fields_list', ['text', 'select', 'color', 'color_picker', 'textarea', 'checkbox'], $this);

		return Array_Search::find(
			$all_fields,
			[
				'where' => [
					['type', $types],
					['save_as', $meta_type],
				],
			]
		);
	}

	/**
	 * Returns the number of steps in this form.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_step_count() {

		$steps = $this->get_settings();

		return is_array($steps) ? count($steps) : 0;
	}

	/**
	 * Returns the number of fields on this form.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_field_count() {

		$fields = $this->get_all_fields();

		return is_array($fields) ? count($fields) : 0;
	}

	/**
	 * Returns the shortcode that needs to be placed to embed this form.
	 *
	 * @since 2.0.0
	 */
	public function get_shortcode(): string {

		return sprintf('[wu_checkout slug="%s"]', $this->get_slug());
	}

	/**
	 * Sets an template for blank.
	 *
	 * @since 2.0.0
	 *
	 * @param string $template The type of the template.
	 * @return void
	 */
	public function use_template($template = 'single-step'): void {

		$fields = [];

		if ('multi-step' === $template) {
			$fields = $this->get_multi_step_template();

			$this->set_settings($fields);
		} elseif ('single-step' === $template) {
			$fields = $this->get_single_step_template();
		} elseif ('simple' === $template) {
			$fields = $this->get_simple_template();
		}

		$this->set_settings($fields);
	}

	/**
	 * Get the contents of the single step template.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	private function get_single_step_template() {

		$steps = [
			[
				'id'     => 'checkout',
				'name'   => __('Checkout', 'ultimate-multisite'),
				'desc'   => '',
				'fields' => [
					[
						'step'                   => 'checkout',
						'name'                   => __('Plans', 'ultimate-multisite'),
						'type'                   => 'pricing_table',
						'id'                     => 'pricing_table',
						'required'               => true,
						'pricing_table_products' => implode(',', wu_get_plans(['fields' => 'ids'])),
						'pricing_table_template' => 'list',
					],
					[
						'step'        => 'checkout',
						'name'        => __('Email', 'ultimate-multisite'),
						'type'        => 'email',
						'id'          => 'email_address',
						'required'    => true,
						'placeholder' => '',
						'tooltip'     => '',
					],
					[
						'step'          => 'checkout',
						'name'          => __('Username', 'ultimate-multisite'),
						'type'          => 'username',
						'id'            => 'username',
						'required'      => true,
						'placeholder'   => '',
						'tooltip'       => '',
						'auto_generate' => false,
					],
					[
						'step'                    => 'checkout',
						'name'                    => __('Password', 'ultimate-multisite'),
						'type'                    => 'password',
						'id'                      => 'password',
						'required'                => true,
						'placeholder'             => '',
						'tooltip'                 => '',
						'password_strength_meter' => '1',
						'password_confirm_field'  => '1',
					],
					[
						'step'          => 'checkout',
						'name'          => __('Site Title', 'ultimate-multisite'),
						'type'          => 'site_title',
						'id'            => 'site_title',
						'required'      => true,
						'placeholder'   => '',
						'tooltip'       => '',
						'auto_generate' => false,
					],
					[
						'step'                => 'checkout',
						'name'                => __('Site URL', 'ultimate-multisite'),
						'type'                => 'site_url',
						'id'                  => 'site_url',
						'placeholder'         => '',
						'tooltip'             => '',
						'required'            => true,
						'auto_generate'       => false,
						'display_url_preview' => true,
					],
					[
						'step'                        => 'checkout',
						'name'                        => __('Template Selection', 'ultimate-multisite'),
						'type'                        => 'template_selection',
						'id'                          => 'template_selection',
						'template_selection_type'     => 'all',
						'template_selection_template' => 'clean',
					],
					[
						'step'                   => 'checkout',
						'name'                   => __('Your Order', 'ultimate-multisite'),
						'type'                   => 'order_summary',
						'id'                     => 'order_summary',
						'order_summary_template' => 'clean',
						'table_columns'          => 'simple',
					],
					[
						'step' => 'checkout',
						'name' => __('Payment Method', 'ultimate-multisite'),
						'type' => 'payment',
						'id'   => 'payment',
					],
					[
						'step'            => 'checkout',
						'name'            => __('Billing Address', 'ultimate-multisite'),
						'type'            => 'billing_address',
						'id'              => 'billing_address',
						'required'        => true,
						'zip_and_country' => '1',
					],
					[
						'step' => 'checkout',
						'name' => __('Checkout', 'ultimate-multisite'),
						'type' => 'submit_button',
						'id'   => 'checkout',
					],
				],
			],
		];

		return apply_filters('wu_checkout_form_single_step_template', $steps);
	}

	/**
	 * Get the contents of the simple template.
	 *
	 * A minimal single-step form where the customer only enters their email
	 * address. Username, password, site title, and site URL are all
	 * auto-generated. A product must be pre-selected; template selection is
	 * shown only when no template has been pre-selected via the URL.
	 *
	 * @since 2.0.20
	 * @return array
	 */
	private function get_simple_template() {

		$plan_ids = wu_get_plans(['fields' => 'ids']);

		$steps = [
			[
				'id'     => 'checkout',
				'name'   => __('Checkout', 'ultimate-multisite'),
				'desc'   => '',
				'fields' => [
					[
						'step'     => 'checkout',
						'name'     => __('Pre-selected Products', 'ultimate-multisite'),
						'type'     => 'products',
						'id'       => 'products',
						'products' => implode(',', $plan_ids),
					],
					[
						'step'        => 'checkout',
						'name'        => __('Email', 'ultimate-multisite'),
						'type'        => 'email',
						'id'          => 'email_address',
						'required'    => true,
						'placeholder' => '',
						'tooltip'     => '',
					],
					[
						'step'                   => 'checkout',
						'name'                   => __('Username', 'ultimate-multisite'),
						'type'                   => 'username',
						'id'                     => 'username',
						'required'               => true,
						'placeholder'            => '',
						'tooltip'                => '',
						'auto_generate_username' => true,
					],
					[
						'step'                   => 'checkout',
						'name'                   => __('Password', 'ultimate-multisite'),
						'type'                   => 'password',
						'id'                     => 'password',
						'required'               => true,
						'placeholder'            => '',
						'tooltip'                => '',
						'auto_generate_password' => true,
					],
					[
						'step'                     => 'checkout',
						'name'                     => __('Site Title', 'ultimate-multisite'),
						'type'                     => 'site_title',
						'id'                       => 'site_title',
						'required'                 => true,
						'placeholder'              => '',
						'tooltip'                  => '',
						'auto_generate_site_title' => true,
					],
					[
						'step'                   => 'checkout',
						'name'                   => __('Site URL', 'ultimate-multisite'),
						'type'                   => 'site_url',
						'id'                     => 'site_url',
						'required'               => true,
						'placeholder'            => '',
						'tooltip'                => '',
						'auto_generate_site_url' => true,
					],
					[
						'step'                                      => 'checkout',
						'name'                                      => __('Template Selection', 'ultimate-multisite'),
						'type'                                      => 'template_selection',
						'id'                                        => 'template_selection',
						'template_selection_type'                   => 'all',
						'template_selection_template'               => 'clean',
						'hide_template_selection_when_pre_selected' => true,
					],
					[
						'step'                   => 'checkout',
						'name'                   => __('Your Order', 'ultimate-multisite'),
						'type'                   => 'order_summary',
						'id'                     => 'order_summary',
						'order_summary_template' => 'clean',
						'table_columns'          => 'simple',
					],
					[
						'step' => 'checkout',
						'name' => __('Payment Method', 'ultimate-multisite'),
						'type' => 'payment',
						'id'   => 'payment',
					],
					[
						'step'            => 'checkout',
						'name'            => __('Billing Address', 'ultimate-multisite'),
						'type'            => 'billing_address',
						'id'              => 'billing_address',
						'required'        => true,
						'zip_and_country' => '1',
					],
					[
						'step' => 'checkout',
						'name' => __('Checkout', 'ultimate-multisite'),
						'type' => 'submit_button',
						'id'   => 'checkout',
					],
				],
			],
		];

		return apply_filters('wu_checkout_form_simple_template', $steps);
	}

	/**
	 * Get the contents of the multi step template.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	private function get_multi_step_template() {

		$steps = [
			[
				'id'     => 'checkout',
				'name'   => __('Checkout', 'ultimate-multisite'),
				'desc'   => '',
				'fields' => [
					[
						'step'                   => 'checkout',
						'name'                   => 'Plans',
						'type'                   => 'pricing_table',
						'id'                     => 'pricing_table',
						'required'               => true,
						'pricing_table_products' => implode(',', wu_get_plans(['fields' => 'ids'])),
						'pricing_table_template' => 'list',
					],
					[
						'step' => 'checkout',
						'name' => __('Next Step', 'ultimate-multisite'),
						'type' => 'submit_button',
						'id'   => 'next_step',
					],
				],
			],
			[
				'id'     => 'site',
				'name'   => __('Site Info', 'ultimate-multisite'),
				'desc'   => '',
				'fields' => [
					[
						'step'          => 'checkout',
						'name'          => __('Site Title', 'ultimate-multisite'),
						'type'          => 'site_title',
						'id'            => 'site_title',
						'required'      => true,
						'placeholder'   => '',
						'tooltip'       => '',
						'auto_generate' => false,
					],
					[
						'step'                => 'checkout',
						'name'                => __('Site URL', 'ultimate-multisite'),
						'type'                => 'site_url',
						'id'                  => 'site_url',
						'required'            => true,
						'placeholder'         => '',
						'tooltip'             => '',
						'auto_generate'       => false,
						'display_url_preview' => true,
					],
					[
						'step'                        => 'checkout',
						'name'                        => __('Template Selection', 'ultimate-multisite'),
						'type'                        => 'template_selection',
						'id'                          => 'template_selection',
						'template_selection_type'     => 'all',
						'template_selection_template' => 'clean',
					],
					[
						'step' => 'site',
						'name' => __('Next Step', 'ultimate-multisite'),
						'type' => 'submit_button',
						'id'   => 'next_step_site',
					],
				],
			],
			[
				'id'     => 'user',
				'name'   => __('User Info', 'ultimate-multisite'),
				'logged' => 'guests_only',
				'desc'   => '',
				'fields' => [
					[
						'step'        => 'checkout',
						'name'        => __('Email', 'ultimate-multisite'),
						'type'        => 'email',
						'id'          => 'email_address',
						'required'    => true,
						'placeholder' => '',
						'tooltip'     => '',
					],
					[
						'step'          => 'checkout',
						'name'          => __('Username', 'ultimate-multisite'),
						'type'          => 'username',
						'id'            => 'username',
						'required'      => true,
						'placeholder'   => '',
						'tooltip'       => '',
						'auto_generate' => false,
					],
					[
						'step'                    => 'checkout',
						'name'                    => __('Password', 'ultimate-multisite'),
						'type'                    => 'password',
						'id'                      => 'password',
						'required'                => true,
						'placeholder'             => '',
						'tooltip'                 => '',
						'password_strength_meter' => '1',
						'password_confirm_field'  => '1',
					],
					[
						'step' => 'user',
						'name' => __('Next Step', 'ultimate-multisite'),
						'type' => 'submit_button',
						'id'   => 'next_step_user',
					],
				],
			],
			[
				'id'     => 'payment',
				'name'   => __('Payment', 'ultimate-multisite'),
				'desc'   => '',
				'fields' => [
					[
						'step'                   => 'checkout',
						'name'                   => __('Your Order', 'ultimate-multisite'),
						'type'                   => 'order_summary',
						'id'                     => 'order_summary',
						'order_summary_template' => 'clean',
						'table_columns'          => 'simple',
					],
					[
						'step' => 'checkout',
						'name' => __('Payment Method', 'ultimate-multisite'),
						'type' => 'payment',
						'id'   => 'payment',
					],
					[
						'step'            => 'checkout',
						'name'            => __('Billing Address', 'ultimate-multisite'),
						'type'            => 'billing_address',
						'id'              => 'billing_address',
						'required'        => true,
						'zip_and_country' => '1',
					],
					[
						'step' => 'checkout',
						'name' => __('Checkout', 'ultimate-multisite'),
						'type' => 'submit_button',
						'id'   => 'checkout',
					],
				],
			],
		];

		return apply_filters('wu_checkout_form_multi_step_template', $steps);
	}

	/**
	 * Converts the steps from classic Ultimate Multisite 1.X to the 2.0 format.
	 *
	 * @since 2.0.0
	 *
	 * @param array $steps The steps to convert.
	 * @param array $old_settings The old settings.
	 * @return array
	 */
	public static function convert_steps_to_v2($steps, $old_settings = []) {

		$exclude_steps = [
			'begin-signup',
			'create-account',
		];

		$old_template_list = wu_get_isset($old_settings, 'templates', []);

		if (empty($old_template_list)) {
			$exclude_steps[] = 'template';
		}

		$new_format = [];

		foreach ($steps as $step_id => $step) {
			if (in_array($step_id, $exclude_steps, true)) {
				continue;
			}

			/**
			 * Deal with special cases.
			 */
			if ('plan' === $step_id) {
				$products_list = wu_get_plans(
					[
						'fields' => 'ids',
					]
				);

				/*
				 * Calculate the period selector
				 */
				$available_periods = [];

				$period_options = [
					'enable_price_1'  => [
						'duration'      => '1',
						'duration_unit' => 'month',
						'label'         => __('Monthly', 'ultimate-multisite'),
					],
					'enable_price_3'  => [
						'duration'      => '3',
						'duration_unit' => 'month',
						'label'         => __('Quarterly', 'ultimate-multisite'),
					],
					'enable_price_12' => [
						'duration'      => '1',
						'duration_unit' => 'year',
						'label'         => __('Yearly', 'ultimate-multisite'),
					],
				];

				foreach ($period_options as $period_option_key => $period_option) {
					$has_period_option = wu_get_isset($old_settings, $period_option_key, true);

					if ($has_period_option) {
						$available_periods[] = $period_option;
					}
				}

				$step['fields'] = [];

				if ($available_periods && count($available_periods) > 1) {
					$step['fields']['period_selection'] = [
						'type'                      => 'period_selection',
						'id'                        => 'period_selection',
						'period_selection_template' => 'legacy',
						'period_options_header'     => '',
						'period_options'            => $available_periods,
					];
				}

				$step['fields']['pricing_table'] = [
					'name'                   => __('Pricing Tables', 'ultimate-multisite'),
					'id'                     => 'pricing_table',
					'type'                   => 'pricing_table',
					'pricing_table_template' => 'legacy',
					'pricing_table_products' => implode(',', $products_list),
				];
			}

			/**
			 * Deal with special cases.
			 */
			if ('template' === $step_id && wu_get_isset($old_settings, 'allow_template', true)) {
				$templates = [];

				foreach (wu_get_site_templates() as $site) {
					$templates[] = $site->get_id();
				}

				$old_template_list = is_array($old_template_list) ? $old_template_list : [];

				$template_list = array_flip($old_template_list);

				$template_list = ! empty($template_list) ? $template_list : $templates;

				$step['fields'] = [
					'template_selection' => [
						'name'                        => __('Template Selection', 'ultimate-multisite'),
						'id'                          => 'template_selection',
						'type'                        => 'template_selection',
						'template_selection_template' => 'legacy',
						'template_selection_sites'    => implode(',', $template_list),
					],
				];
			}

			/**
			 * Remove unnecessary callbacks
			 */
			unset($step['handler']);
			unset($step['view']);
			unset($step['hidden']);

			$new_fields = [];

			$step['id'] = $step_id;

			$fields_to_skip = [
				'user_pass_conf',
				'url_preview',
				'site_url', // Despite the name, this is the Honeypot field.
			];

			foreach ($step['fields'] as $field_id => $field) {
				if (in_array($field_id, $fields_to_skip, true)) {
					unset($step['fields'][ $field_id ]);

					continue;
				}

				/**
				 * Format specific fields.
				 */
				switch ($field_id) {
					case 'user_name':
						$field['type'] = 'username';
						$field['id']   = 'username';
						break;

					case 'user_pass':
						$field['type']                    = 'password';
						$field['id']                      = 'password';
						$field['password_strength_meter'] = false;
						$field['password_confirm_field']  = true;
						$field['password_confirm_label']  = wu_get_isset($step['fields']['user_pass_conf'], 'name', __('Confirm Password', 'ultimate-multisite'));
						break;

					case 'user_email':
						$field['display_notices'] = false;
						$field['id']              = 'email_address';
						break;

					case 'blog_title':
						$field['type']          = 'site_title';
						$field['id']            = 'site_title';
						$field['auto_generate'] = false;
						break;

					case 'blogname':
						$field['type']                      = 'site_url';
						$field['id']                        = 'site_url';
						$field['url_preview_template']      = 'legacy/signup/steps/step-domain-url-preview';
						$field['auto_generate']             = false;
						$field['display_url_preview']       = true;
						$field['required']                  = true;
						$field['display_field_attachments'] = false;
						$field['enable_domain_selection']   = wu_get_isset($old_settings, 'enable_multiple_domains', false);
						$field['available_domains']         = wu_get_isset($old_settings, 'domain_options', []);
						break;

					case 'submit':
						$field['type'] = 'submit_button';
						$field['id']   = 'submit_button';

						if ('account' === $step_id) {
							$field['name'] = __('Continue to the Next Step', 'ultimate-multisite');
						}

						break;
				}

				$field['id'] = $field_id;

				$new_fields[] = $field;
			}

			$step['fields'] = $new_fields;

			$new_format[] = $step;
		}

		/**
		 * Add Checkout step
		 */
		$new_format[] = [
			'id'     => 'payment',
			'name'   => __('Checkout', 'ultimate-multisite'),
			'fields' => [
				[
					'name'                   => __('Order Summary', 'ultimate-multisite'),
					'type'                   => 'order_summary',
					'id'                     => 'order_summary',
					'order_summary_template' => 'clean',
					'table_columns'          => 'simple',
				],
				[
					'name'            => __('Billing Address', 'ultimate-multisite'),
					'type'            => 'billing_address',
					'id'              => 'billing_address',
					'zip_and_country' => true,
				],
				[
					'type'             => 'discount_code',
					'id'               => 'discount_code',
					'name'             => __('Coupon Code', 'ultimate-multisite'),
					'tooltip'          => __('Coupon Code', 'ultimate-multisite'),
					'display_checkbox' => true,
				],
				[
					'name' => __('Payment Methods', 'ultimate-multisite'),
					'type' => 'payment',
					'id'   => 'payment',
				],
				[
					'type' => 'submit_button',
					'id'   => 'submit_button',
					'name' => __('Pay & Create Account', 'ultimate-multisite'),
				],
			],
		];

		return $new_format;
	}

	/**
	 * Checks if this signup has limitations on countries.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_country_lock() {

		return ! empty($this->get_allowed_countries());
	}

	/**
	 * Get countries allowed on this checkout.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_allowed_countries() {

		if (is_string($this->allowed_countries)) {
			$this->allowed_countries = maybe_unserialize(stripslashes($this->allowed_countries));
		}

		return maybe_unserialize($this->allowed_countries);
	}

	/**
	 * Set countries allowed on this checkout.
	 *
	 * @since 2.0.0
	 * @param string $allowed_countries The allowed countries that can access this checkout.
	 * @return void
	 */
	public function set_allowed_countries($allowed_countries): void {

		$this->allowed_countries = $allowed_countries;
	}

	/**
	 * Checks if this checkout form has a custom thank you page.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_thank_you_page() {

		$page_id = $this->get_thank_you_page_id();

		return $page_id && get_post($page_id);
	}

	/**
	 * Get custom thank you page, if set.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_thank_you_page_id() {

		if (null === $this->thank_you_page_id) {
			$this->thank_you_page_id = $this->get_meta(self::META_THANK_YOU_PAGE_ID, '');
		}

		return $this->thank_you_page_id;
	}

	/**
	 * Set custom thank you page, if set.
	 *
	 * @since 2.0.0
	 * @param int $thank_you_page_id The thank you page ID. This page is shown after a successful purchase.
	 * @return void
	 */
	public function set_thank_you_page_id($thank_you_page_id): void {

		$this->meta[ self::META_THANK_YOU_PAGE_ID ] = $thank_you_page_id;

		$this->thank_you_page_id = $thank_you_page_id;
	}

	/**
	 * Get Snippets to run on thank you page.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_conversion_snippets() {

		if (null === $this->conversion_snippets) {
			$this->conversion_snippets = $this->get_meta(self::META_CONVERSION_SNIPPETS, '');
		}

		return $this->conversion_snippets;
	}

	/**
	 * Set snippets to run on thank you page.
	 *
	 * @since 2.0.0
	 * @param string $conversion_snippets Snippets to run on thank you page.
	 * @return void
	 */
	public function set_conversion_snippets($conversion_snippets): void {

		$this->meta[ self::META_CONVERSION_SNIPPETS ] = $conversion_snippets;

		$this->conversion_snippets = $conversion_snippets;
	}

	/**
	 * Save (create or update) the model on the database.
	 *
	 * Overrides the save method to set the template.
	 * This is used on CLI creation.
	 *
	 * @since 2.0.0
	 *
	 * @return bool|\WP_Error
	 */
	public function save() {

		$step_types = [
			'multi-step',
			'single-step',
			'simple',
		];

		if ($this->template && in_array($this->template, $step_types, true)) {
			$this->use_template($this->template);
		}

		return parent::save();
	}

	/**
	 * Get can be either 'blank', 'single-step' or 'multi-step'.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_template() {

		return $this->template;
	}

	/**
	 * Set the template mode. THis is mostly used on CLI.
	 *
	 * @since 2.0.0
	 * @param string $template Template mode. Can be either 'blank', 'single-step' or 'multi-step'.
	 * @options blank,single-step,multi-step
	 * @return void
	 */
	public function set_template($template): void {

		$this->template = $template;
	}

	/**
	 * Custom fields to allow customer to finish a payment intent.
	 *
	 * @since 2.0.18
	 * @return array
	 */
	public static function finish_checkout_form_fields() {

		$payment = wu_get_payment_by_hash(wu_request('payment'));

		if ( ! $payment && wu_request('payment_id')) {
			$payment = wu_get_payment(wu_request('payment_id'));
		}

		if ( ! $payment && current_user_can('manage_options')) {
			$payment = wu_mock_payment();
		}

		if ( ! $payment) {
			return [];
		}

		$fields = [
			[
				'step'                   => 'checkout',
				'name'                   => __('Your Order', 'ultimate-multisite'),
				'type'                   => 'order_summary',
				'id'                     => 'order_summary',
				'order_summary_template' => 'clean',
				'table_columns'          => 'simple',
			],
			[
				'step' => 'checkout',
				'name' => __('Payment Method', 'ultimate-multisite'),
				'type' => 'payment',
				'id'   => 'payment',
			],
			[
				'step'  => 'checkout',
				'name'  => __('Finish Payment', 'ultimate-multisite'),
				'type'  => 'submit_button',
				'id'    => 'checkout',
				'order' => 0,
			],
		];

		$steps = [
			[
				'id'     => 'checkout',
				'name'   => __('Checkout', 'ultimate-multisite'),
				'desc'   => '',
				'fields' => $fields,
			],
		];

		return apply_filters('wu_checkout_form_finish_checkout_form_fields', $steps);
	}

	/**
	 * Minimal form fields for paying a standalone invoice.
	 *
	 * Shows order summary, payment method selector, and submit button.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public static function pay_invoice_form_fields(): array {

		$payment = wu_get_payment_by_hash(wu_request('payment'));

		if ( ! $payment && wu_request('payment_id')) {
			$payment = wu_get_payment(wu_request('payment_id'));
		}

		if ( ! $payment && current_user_can('manage_options')) {
			$payment = wu_mock_payment();
		}

		if ( ! $payment) {
			return [];
		}

		$fields = [
			[
				'step'                   => 'checkout',
				'name'                   => __('Invoice Summary', 'ultimate-multisite'),
				'type'                   => 'order_summary',
				'id'                     => 'order_summary',
				'order_summary_template' => 'clean',
				'table_columns'          => 'simple',
			],
			[
				'step' => 'checkout',
				'name' => __('Payment Method', 'ultimate-multisite'),
				'type' => 'payment',
				'id'   => 'payment',
			],
			[
				'step'  => 'checkout',
				'name'  => __('Pay Invoice', 'ultimate-multisite'),
				'type'  => 'submit_button',
				'id'    => 'checkout',
				'order' => 0,
			],
		];

		$steps = [
			[
				'id'     => 'checkout',
				'name'   => __('Pay Invoice', 'ultimate-multisite'),
				'desc'   => '',
				'fields' => $fields,
			],
		];

		return apply_filters('wu_checkout_form_pay_invoice_form_fields', $steps);
	}

	/**
	 * Custom fields for back-end upgrade/downgrades and such.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function membership_change_form_fields() {

		$membership = WP_Ultimo()->currents->get_membership();

		if ( ! $membership && wu_request('membership_id')) {
			$membership = wu_get_membership(wu_request('membership_id'));
		}

		if ( ! $membership && current_user_can('manage_options')) {
			$membership = wu_mock_membership();
		}

		if ( ! $membership) {
			return [];
		}

		$fields = [];

		/*
		 * Adds the addons
		 * selected on the plan.
		 */
		$plan = $membership->get_plan();

		if ($plan) {
			/*
			 * Get the group
			 */
			$group = $plan->get_group();

			$search_arguments = [
				'fields' => 'ids',
			];

			if ($group) {
				$search_arguments['product_group'] = $group;
			} else {
				/*
				 * If there isn't a group available
				 * limit the return to 3.
				 */
				$search_arguments['number'] = 3;
			}

			$plans = wu_get_plans($search_arguments);

			$products = array_map('wu_get_product', $plans);

			$period_selection = [];

			$should_use_period_selector = false;

			/**
			 * Let's see if we need a period selector
			 * and which selectors we need
			 */
			foreach ($products as $product) {
				$days_in_cycle = (int) wu_get_days_in_cycle($product->get_duration_unit(), $product->get_duration());

				$product_duration = $product->get_duration();
				$product_unit     = wu_get_translatable_string($product_duration <= 1 ? $product->get_duration_unit() : $product->get_duration_unit() . 's');

				$label = $product_duration <= 1
					? $product_unit
					: $product_duration . ' ' . $product_unit;

				$period_selection[ $days_in_cycle ] = [
					'duration'      => $product->get_duration(),
					'duration_unit' => $product->get_duration_unit(),
					'label'         => $label,
				];

				$variations = $product->get_price_variations();

				if (empty($variations)) {
					continue;
				}

				/**
				 * We have different variations on same product
				 */

				$should_use_period_selector = true;

				foreach ($variations as $variation) {
					/**
					 * Get variations for selector
					 */
					$days_in_cycle = (int) wu_get_days_in_cycle($variation['duration_unit'], $variation['duration']);

					$var_duration = $variation['duration'];
					$var_unit     = wu_get_translatable_string($var_duration <= 1 ? $variation['duration_unit'] : $variation['duration_unit'] . 's');

					$label = $var_duration <= 1
						? $var_unit
						: $var_duration . ' ' . $var_unit;

					$period_selection[ $days_in_cycle ] = [
						'duration'      => $variation['duration'],
						'duration_unit' => $variation['duration_unit'],
						'label'         => $label,
					];
				}
			}

			ksort($period_selection);

			if ($should_use_period_selector) {
				$fields[] = [
					'step'                      => 'checkout',
					'name'                      => '',
					'type'                      => 'period_selection',
					'id'                        => 'period_selection',
					'period_selection_template' => 'clean',
					'period_options'            => array_values($period_selection),
				];
			}

			$fields[] = [
				'step'                      => 'checkout',
				'name'                      => __('Plans', 'ultimate-multisite'),
				'type'                      => 'pricing_table',
				'id'                        => 'pricing_table',
				'required'                  => true,
				'pricing_table_products'    => implode(',', $plans),
				'pricing_table_template'    => 'list',
				'force_different_durations' => (int) ! $should_use_period_selector,
			];

			$available_addons = (array) $plan->get_available_addons();

			foreach ($available_addons as $addon_id) {
				if ( ! $addon_id) {
					continue;
				}

				$addon = wu_get_product($addon_id);

				if ( ! $addon) {
					continue;
				}

				$fields[] = [
					'id'                    => "order_bump_{$addon_id}",
					'type'                  => 'order_bump',
					'name'                  => $addon->get_name(),
					'product'               => $addon_id,
					'display_product_image' => true,
				];
			}
		}

		$end_fields = [
			[
				'step'                   => 'checkout',
				'name'                   => __('Your Order', 'ultimate-multisite'),
				'type'                   => 'order_summary',
				'id'                     => 'order_summary',
				'order_summary_template' => 'clean',
				'table_columns'          => 'simple',
			],
			[
				'step' => 'checkout',
				'name' => __('Payment Method', 'ultimate-multisite'),
				'type' => 'payment',
				'id'   => 'payment',
			],
			[
				'step'  => 'checkout',
				'name'  => __('Complete Checkout', 'ultimate-multisite'),
				'type'  => 'submit_button',
				'id'    => 'checkout',
				'order' => 0,
			],
		];

		$fields = array_merge($fields, $end_fields);

		$steps = [
			[
				'id'     => 'checkout',
				'name'   => __('Checkout', 'ultimate-multisite'),
				'desc'   => '',
				'fields' => $fields,
			],
		];

		return apply_filters('wu_checkout_form_membership_change_form_fields', $steps);
	}

	/**
	 * Custom fields to add to the add new site screen.
	 *
	 * @since 2.0.11
	 * @return array
	 */
	public static function add_new_site_form_fields() {

		$membership = WP_Ultimo()->currents->get_membership();

		if ( ! $membership) {
			return [];
		}

		/*
		 * Adds the addons
		 * selected on the plan.
		 */
		$plan = $membership->get_plan();

		$steps = [];

		// As this limit is not membership based, we need to exclude from verification here
		if ($membership->get_limitations(true, true)->site_templates->is_enabled()) {
			$template_selection_fields = [
				[
					'step'                        => 'template',
					'name'                        => __('Template Selection', 'ultimate-multisite'),
					'type'                        => 'template_selection',
					'id'                          => 'template_selection',
					'cols'                        => 4,
					'template_selection_template' => 'clean',
					'order'                       => 0,
				],
				[
					'step'        => 'template',
					'type'        => 'hidden',
					'id'          => 'create-new-site',
					'fixed_value' => wp_create_nonce('create-new-site'),
				],
			];

			$steps[] = [
				'id'     => 'template',
				'name'   => __('Template Selection', 'ultimate-multisite'),
				'desc'   => '',
				'fields' => $template_selection_fields,
			];
		}

		$final_fields = [
			[
				'step'     => 'create',
				'type'     => 'products',
				'id'       => 'products',
				'products' => $plan->get_id(),
			],
			[
				'step'        => 'create',
				'type'        => 'hidden',
				'id'          => 'membership_id',
				'fixed_value' => $membership->get_id(),
			],
			[
				'step'        => 'create',
				'type'        => 'hidden',
				'id'          => 'create-new-site',
				'fixed_value' => wp_create_nonce('create-new-site'),
			],
			[
				'step'        => 'create',
				'type'        => 'hidden',
				'id'          => 'redirect_url',
				'fixed_value' => wu_request('redirect_url'),
			],
		];

		$final_fields[] = [
			'step'        => 'create',
			'id'          => 'site_title',
			'name'        => __('Site Title', 'ultimate-multisite'),
			'tooltip'     => '',
			'placeholder' => '',
			'type'        => 'site_title',
		];

		$domain_options = wu_get_available_domain_options();

		$final_fields[] = [
			'step'                      => 'create',
			'id'                        => 'site_url',
			'name'                      => __('Site URL', 'ultimate-multisite'),
			'tooltip'                   => '',
			'placeholder'               => '',
			'display_field_attachments' => false,
			'type'                      => 'site_url',
			'enable_domain_selection'   => ! empty($domain_options),
			'available_domains'         => implode(PHP_EOL, $domain_options),
		];

		$final_fields[] = [
			'step'  => 'create',
			'name'  => __('Create Site', 'ultimate-multisite'),
			'type'  => 'submit_button',
			'id'    => 'checkout',
			'order' => 0,
		];

		$steps[] = [
			'id'      => 'create',
			'name'    => __('Create Site', 'ultimate-multisite'),
			'desc'    => '',
			'classes' => 'wu-max-w-sm',
			'fields'  => $final_fields,
		];

		return apply_filters('wu_checkout_form_add_new_site_form_fields', $steps);
	}

	/**
	 * Transform the object into an assoc array.
	 *
	 * Overrides Base_Model::to_array() to ensure lazy-loaded meta properties
	 * are populated before serialization so REST API responses include all fields.
	 *
	 * @since 2.0.11
	 * @return array
	 */
	public function to_array() {

		// Only trigger lazy-loading when the model has been persisted (has an ID).
		// For unsaved models (during validate/save), meta is not available and the
		// getters return default values that can fail validation rules.
		if ($this->get_id()) {
			$this->get_thank_you_page_id();
			$this->get_conversion_snippets();
		}

		return parent::to_array();
	}
}
