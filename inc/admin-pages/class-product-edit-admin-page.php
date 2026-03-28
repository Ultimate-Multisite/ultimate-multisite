<?php
/**
 * Ultimate Multisite Product Edit/Add New Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Limitations\Limit_Site_Templates;
use WP_Ultimo\Limits\Site_Template_Limits;
use WP_Ultimo\Models\Product;
use WP_Ultimo\Database\Products\Product_Type;

/**
 * Ultimate Multisite Product Edit/Add New Admin Page.
 */
class Product_Edit_Admin_Page extends Edit_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-edit-product';

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
	public $object_id = 'product';

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
	protected $highlight_menu_slug = 'wp-ultimo-products';

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
		'network_admin_menu' => 'wu_edit_products',
	];

	/**
	 * Register ajax forms.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {
		/*
		 * Adds the hooks to handle deletion.
		 */
		add_filter('wu_form_fields_delete_product_modal', [$this, 'product_extra_delete_fields'], 10, 2);

		add_action('wu_after_delete_product_modal', [$this, 'product_after_delete_actions']);

		add_action("wu_page_{$this->id}_load", [$this, 'add_new_product_warning_message']);
	}

	/**
	 * Adds the new product warning.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_new_product_warning_message(): void {

		if (wu_request('wu-new-model')) {
			if ( ! $this->get_object() || $this->get_object()->get_type() !== Product_Type::PLAN) {
				return;
			}

			\WP_Ultimo\UI\Tours::get_instance()->create_tour(
				'new_product_warning',
				[
					[
						'id'       => 'new-product-warning',
						'title'    => __('On adding a new product...', 'ultimate-multisite'),
						'text'     => [
							__("You just successfully added a new product to your Ultimate Multisite network and that's awesome!", 'ultimate-multisite'),
							__('Keep in mind that newly created products do not appear automatically in your checkout forms.', 'ultimate-multisite'),
							__('To make a product available on registration, you will need to manually add it to the pricing table field of your checkout forms.', 'ultimate-multisite'),
						],
						'buttons'  => [
							[
								'classes' => 'button wu-text-xs sm:wu-normal-case wu-float-left',
								'text'    => __('Go to Checkout Forms', 'ultimate-multisite'),
								'url'     => wu_network_admin_url('wp-ultimo-checkout-forms'),
							],
						],
						'attachTo' => [
							'element' => '#message.updated',
							'on'      => 'top',
						],
					],
				]
			);
		}
	}

	/**
	 * Adds the extra delete fields to the delete form.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $fields The original fields.
	 * @param object $product The product object.
	 * @return array
	 */
	public function product_extra_delete_fields($fields, $product) {

		$custom_fields = [
			're_assignment_product_id' => [
				'type'        => 'model',
				'title'       => __('Re-assign Memberships to', 'ultimate-multisite'),
				'placeholder' => __('Select Product...', 'ultimate-multisite'),
				'tooltip'     => __('The product you select here will be assigned to all the memberships attached to the product you are deleting.', 'ultimate-multisite'),
				'html_attr'   => [
					'data-model'        => 'product',
					'data-value-field'  => 'id',
					'data-label-field'  => 'name',
					'data-search-field' => 'name',
					'data-max-items'    => 1,
					'data-exclude'      => wp_json_encode([$product->get_id()]),
				],
			],
		];

		return array_merge($custom_fields, $fields);
	}

	/**
	 * Adds the primary domain handling to the product deletion.
	 *
	 * @since 2.0.0
	 *
	 * @param object $product The product object.
	 * @return void
	 */
	public function product_after_delete_actions($product): void {

		global $wpdb;

		$new_product_id = wu_request('re_assignment_product_id');

		$re_assignment_product = wu_get_product($new_product_id);

		if ($re_assignment_product) {
			$query = $wpdb->prepare(
				"UPDATE {$wpdb->base_prefix}wu_memberships
				 SET product_id = %d
				 WHERE product_id = %d",
				$re_assignment_product->get_id(),
				$product->get_id()
			);

			$wpdb->query($query); // phpcs:ignore
		}
	}

	/**
	 * Registers the necessary scripts and styles for this admin page.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		parent::register_scripts();

		wp_enqueue_media();
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		parent::register_widgets();

		$this->add_fields_widget(
			'description',
			[
				'title'    => __('Description', 'ultimate-multisite'),
				'position' => 'normal',
				'fields'   => [
					'description' => [
						'type'        => 'textarea',
						'title'       => __('Product Description', 'ultimate-multisite'),
						'placeholder' => __('Tell your customers what this product is about.', 'ultimate-multisite'),
						'tooltip'     => __('This description is made available for layouts and can be shown to end customers.', 'ultimate-multisite'),
						'value'       => $this->get_object()->get_description(),
						'html_attr'   => [
							'rows' => 3,
						],
					],
				],
			]
		);

		$this->add_tabs_widget(
			'product_options',
			[
				'title'    => __('Product Options', 'ultimate-multisite'),
				'position' => 'normal',
				'sections' => $this->get_product_option_sections(),
			]
		);

		/*
		 * Handle legacy options for back-compat.
		 */
		$this->handle_legacy_options();

		$this->add_list_table_widget(
			'events',
			[
				'title'        => __('Events', 'ultimate-multisite'),
				'table'        => new \WP_Ultimo\List_Tables\Inside_Events_List_Table(),
				'query_filter' => [$this, 'query_filter'],
			]
		);

		$save_widget_args = apply_filters(
			'wu_product_edit_save_widget',
			[
				'html_attr' => [
					'data-wu-app' => 'product_pricing',
					'data-state'  => wp_json_encode(
						[
							'is_recurring'        => $this->get_object()->is_recurring(),
							'pricing_type'        => $this->get_object()->get_pricing_type(),
							'has_trial'           => $this->get_object()->get_trial_duration() > 0,
							'has_setup_fee'       => $this->get_object()->has_setup_fee(),
							'setup_fee'           => $this->get_object()->get_setup_fee(),
							'amount'              => $this->get_object()->get_amount(),
							'duration'            => $this->get_object()->get_duration(),
							'duration_unit'       => $this->get_object()->get_duration_unit(),
							'pwyw_recurring_mode' => $this->get_object()->get_pwyw_recurring_mode() ?: 'customer_choice',
						]
					),
				],
				'fields'    => [
					// Fields for price
					'pricing_type'          => [
						'type'              => 'select',
						'title'             => __('Pricing Type', 'ultimate-multisite'),
						'placeholder'       => __('Select Pricing Type', 'ultimate-multisite'),
						'desc'              => __('Products can be free, paid, pay what you want, or require further contact for pricing.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_pricing_type(),
						'tooltip'           => '',
						'options'           => [
							'paid'              => __('Paid', 'ultimate-multisite'),
							'free'              => __('Free', 'ultimate-multisite'),
							'pay_what_you_want' => __('Pay What You Want', 'ultimate-multisite'),
							'contact_us'        => __('Contact Us', 'ultimate-multisite'),
						],
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'v-model' => 'pricing_type',
						],
					],
					'contact_us_label'      => [
						'type'              => 'text',
						'title'             => __('Button Label', 'ultimate-multisite'),
						'placeholder'       => __('E.g. Contact us', 'ultimate-multisite'),
						'desc'              => __('This will be used on the pricing table CTA button, as the label.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_contact_us_label(),
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'contact_us'",
							'v-cloak' => '1',
						],
					],
					'contact_us_link'       => [
						'type'              => 'url',
						'title'             => __('Button Link', 'ultimate-multisite'),
						'placeholder'       => __('E.g. https://contactus.page.com', 'ultimate-multisite'),
						'desc'              => __('This will be used on the pricing table CTA button.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_contact_us_link(),
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'contact_us'",
							'v-cloak' => '1',
						],
					],
					'pwyw_minimum_amount'   => [
						'type'              => 'text',
						'title'             => __('Minimum Price', 'ultimate-multisite'),
						'placeholder'       => wu_format_currency('0'),
						'desc'              => __('The minimum amount customers can pay. Leave at 0 for truly "pay what you want".', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_pwyw_minimum_amount(),
						'money'             => true,
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'pay_what_you_want'",
							'v-cloak' => '1',
						],
					],
					'pwyw_suggested_amount' => [
						'type'              => 'text',
						'title'             => __('Suggested Price', 'ultimate-multisite'),
						'placeholder'       => wu_format_currency('0'),
						'desc'              => __('A suggested price shown as the default value in the price input.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_pwyw_suggested_amount(),
						'money'             => true,
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'pay_what_you_want'",
							'v-cloak' => '1',
						],
					],
					'pwyw_recurring_mode'   => [
						'type'              => 'select',
						'title'             => __('Recurring Mode', 'ultimate-multisite'),
						'desc'              => __('Control whether customers can choose between one-time and recurring payments.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_pwyw_recurring_mode(),
						'options'           => [
							'customer_choice' => __('Customer Chooses (One-time or Recurring)', 'ultimate-multisite'),
							'force_recurring' => __('Force Recurring Only', 'ultimate-multisite'),
							'force_one_time'  => __('Force One-time Only', 'ultimate-multisite'),
						],
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'pay_what_you_want'",
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'v-model' => 'pwyw_recurring_mode',
						],
					],
					'pwyw_duration_group'   => [
						'type'              => 'group',
						'title'             => __('Billing Period', 'ultimate-multisite'),
						'desc'              => __('The billing period for recurring PWYW payments. Only applies when recurring is enabled.', 'ultimate-multisite'),
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'pay_what_you_want' && pwyw_recurring_mode != 'force_one_time'",
							'v-cloak' => '1',
						],
						'fields'            => [
							'duration'      => [
								'type'            => 'number',
								'value'           => $this->get_object()->get_duration(),
								'placeholder'     => 1,
								'wrapper_classes' => 'wu-w-1/2',
								'min'             => 1,
								'html_attr'       => [
									'v-model' => 'duration',
									'steps'   => 1,
								],
							],
							'duration_unit' => [
								'type'            => 'select',
								'value'           => $this->get_object()->get_duration_unit(),
								'placeholder'     => '',
								'wrapper_classes' => 'wu-w-1/2 wu-ml-2',
								'html_attr'       => [
									'v-model' => 'duration_unit',
								],
								'options'         => [
									'day'   => __('Day(s)', 'ultimate-multisite'),
									'week'  => __('Week(s)', 'ultimate-multisite'),
									'month' => __('Month(s)', 'ultimate-multisite'),
									'year'  => __('Year(s)', 'ultimate-multisite'),
								],
							],
						],
					],
					'recurring'             => [
						'type'              => 'toggle',
						'title'             => __('Is Recurring?', 'ultimate-multisite'),
						'desc'              => __('Check this if this product has a recurring charge.', 'ultimate-multisite'),
						'value'             => $this->get_object()->is_recurring(),
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'paid'",
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'v-model' => 'is_recurring',
						],
					],
					'amount'                => [
						'type'      => 'hidden',
						'html_attr' => [
							'v-model' => 'amount',
						],
					],
					'_amount'               => [
						'type'              => 'text',
						'title'             => __('Price', 'ultimate-multisite'),
						'placeholder'       => __('Price', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_formatted_amount(),
						'tooltip'           => '',
						'money'             => true,
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'paid' && !is_recurring ",
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'v-bind:name' => '""',
							'v-model'     => 'amount',
						],
					],
					'amount_group'          => [
						'type'              => 'group',
						'title'             => __('Price', 'ultimate-multisite'),
						// translators: placeholder %1$s is the amount, %2$s is the duration (such as 1, 2, 3), and %3$s is the unit (such as month, year, week)
						'desc'              => sprintf(__('The customer will be charged %1$s every %2$s %3$s(s).', 'ultimate-multisite'), '{{ wu_format_money(amount) }}', '{{ duration }}', '{{ duration_unit }}'),
						'tooltip'           => '',
						'wrapper_html_attr' => [
							'v-show'  => "is_recurring && pricing_type == 'paid'",
							'v-cloak' => '1',
						],
						'fields'            => [
							'_amount'       => [
								'type'            => 'text',
								'value'           => $this->get_object()->get_formatted_amount(),
								'placeholder'     => wu_format_currency('99'),
								'wrapper_classes' => '',
								'money'           => true,
								'html_attr'       => [
									'v-bind:name' => '""',
									'v-model'     => 'amount',
								],
							],
							'duration'      => [
								'type'            => 'number',
								'value'           => $this->get_object()->get_duration(),
								'placeholder'     => '',
								'wrapper_classes' => 'wu-mx-2 wu-w-1/3',
								'min'             => 0,
								'html_attr'       => [
									'v-model' => 'duration',
									'steps'   => 1,
								],
							],
							'duration_unit' => [
								'type'            => 'select',
								'value'           => $this->get_object()->get_duration_unit(),
								'placeholder'     => '',
								'wrapper_classes' => 'wu-w-2/3',
								'html_attr'       => [
									'v-model' => 'duration_unit',
								],
								'options'         => [
									'day'   => __('Days', 'ultimate-multisite'),
									'week'  => __('Weeks', 'ultimate-multisite'),
									'month' => __('Months', 'ultimate-multisite'),
									'year'  => __('Years', 'ultimate-multisite'),
								],
							],
						],
					],
					'billing_cycles'        => [
						'type'              => 'number',
						'title'             => __('Billing Cycles', 'ultimate-multisite'),
						'placeholder'       => __('E.g. 1', 'ultimate-multisite'),
						'desc'              => __('How many times should we bill this customer. Leave 0 to charge until cancelled.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_billing_cycles(),
						'tooltip'           => '',
						'wrapper_html_attr' => [
							'v-show'  => "is_recurring && pricing_type == 'paid'",
							'v-cloak' => '1',
						],
					],
					'has_trial'             => [
						'type'              => 'toggle',
						'title'             => __('Offer Trial', 'ultimate-multisite'),
						'desc'              => __('Check if you want to add a trial period to this product.', 'ultimate-multisite'),
						'value'             => $this->get_object()->has_trial(),
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'paid'",
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'v-model' => 'has_trial',
						],
					],
					'trial_group'           => [
						'type'              => 'group',
						'title'             => __('Trial', 'ultimate-multisite'),
						'tooltip'           => '',
						'wrapper_html_attr' => [
							'v-show'  => "has_trial && pricing_type == 'paid'",
							'v-cloak' => '1',
						],
						'fields'            => [
							'trial_duration'      => [
								'type'            => 'number',
								'value'           => $this->get_object()->get_trial_duration(),
								'placeholder'     => '',
								'wrapper_classes' => 'wu-mr-2 wu-w-1/3',
							],
							'trial_duration_unit' => [
								'type'            => 'select',
								'value'           => $this->get_object()->get_trial_duration_unit(),
								'placeholder'     => '',
								'wrapper_classes' => 'wu-w-2/3',
								'options'         => [
									'day'   => __('Days', 'ultimate-multisite'),
									'week'  => __('Weeks', 'ultimate-multisite'),
									'month' => __('Months', 'ultimate-multisite'),
									'year'  => __('Years', 'ultimate-multisite'),
								],
							],
						],
					],
					'has_setup_fee'         => [
						'type'              => 'toggle',
						'title'             => __('Add Setup Fee?', 'ultimate-multisite'),
						'desc'              => __('Check if you want to add a setup fee.', 'ultimate-multisite'),
						'value'             => $this->get_object()->has_setup_fee(),
						'wrapper_html_attr' => [
							'v-show'  => "pricing_type == 'paid'",
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'v-model' => 'has_setup_fee',
						],
					],
					'setup_fee'             => [
						'type'      => 'hidden',
						'html_attr' => [
							'v-model' => 'setup_fee',
						],
					],
					'_setup_fee'            => [
						'type'              => 'text',
						'money'             => true,
						'title'             => __('Setup Fee', 'ultimate-multisite'),
						'desc'              => __('The setup fee will be added to the first charge, in addition to the regular price of the product.', 'ultimate-multisite'),
						// translators: %s is a price placeholder value.
						'placeholder'       => sprintf(__('E.g. %s', 'ultimate-multisite'), wu_format_currency(199)),
						'value'             => $this->get_object()->get_formatted_amount('setup_fee'),
						'wrapper_html_attr' => [
							'v-show'  => "has_setup_fee && pricing_type == 'paid'",
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'v-model' => 'setup_fee',
						],
					],
				],
			],
			$this->get_object()
		);

		$this->add_save_widget('save', $save_widget_args);

		$this->add_fields_widget(
			'active',
			[
				'title'  => __('Active', 'ultimate-multisite'),
				'fields' => [
					'active' => [
						'type'  => 'toggle',
						'title' => __('Active', 'ultimate-multisite'),
						'desc'  => __('Use this option to manually enable or disable this product for new sign-ups.', 'ultimate-multisite'),
						'value' => $this->get_object()->is_active(),
					],
				],
			]
		);

		$this->add_fields_widget(
			'image',
			[
				'title'  => __('Product Image', 'ultimate-multisite'),
				'fields' => [
					'featured_image_id' => [
						'type'    => 'image',
						'stacked' => true,
						'title'   => __('Product Image', 'ultimate-multisite'),
						'desc'    => __('This image is used on product list tables and other places.', 'ultimate-multisite'),
						'value'   => $this->get_object()->get_featured_image_id(),
						'img'     => $this->get_object()->get_featured_image(),
					],
				],
			]
		);
	}

	/**
	 * Handles legacy advanced options for plans.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_legacy_options(): void {

		global $wp_filter;

		$tabs = apply_filters_deprecated(
			'wu_plans_advanced_options_tabs',
			[
				[],
			],
			'2.0.0',
			'wu_product_options_sections'
		);

		if ( ! isset($wp_filter['wu_plans_advanced_options_after_panels'])) {
			return;
		}

		wp_enqueue_style('wu-legacy-admin-tabs', wu_get_asset('legacy-admin-tabs.css', 'css'), false, wu_get_version());

		$priorities = $wp_filter['wu_plans_advanced_options_after_panels']->callbacks;

		$fields = [
			'heading' => [
				'type'  => 'header',
				'title' => __('Legacy Options', 'ultimate-multisite'),
				// translators: %s is the comma-separated list of legacy add-ons.
				'desc'  => sprintf(__('Options for %s, and others.', 'ultimate-multisite'), implode(', ', $tabs)),
			],
		];

		foreach ($priorities as $priority => $callbacks) {
			foreach ($callbacks as $id => $callable) {
				$fields[ $id ] = [
					'type'    => 'html',
					'classes' => 'wu--mt-2',
					'content' => function () use ($callable) {

						call_user_func($callable['function'], new \WU_Plan($this->get_object()));
					},
				];
			}
		}

		$this->add_fields_widget(
			'legacy-options',
			[
				'title'                 => __('Legacy Options', 'ultimate-multisite'),
				'position'              => 'normal',
				'fields'                => $fields,
				'classes'               => 'wu-legacy-options-panel',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'style' => 'margin-top: -5px;',
				],
			]
		);
	}

	/**
	 * Returns the list of sections and its fields for the product page.
	 *
	 * Can be filtered via 'wu_product_options_sections'.
	 *
	 * @see inc/managers/class-limitation-manager.php
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_product_option_sections() {

		$sections = [
			'general' => [
				'title'  => __('General', 'ultimate-multisite'),
				'desc'   => __('General product options such as product slug, type, etc.', 'ultimate-multisite'),
				'icon'   => 'dashicons-wu-globe',
				'state'  => [
					'slug'         => $this->get_object()->get_slug(),
					'product_type' => $this->get_object()->get_type(),
				],
				'fields' => [
					'slug'                               => [
						'type'        => 'text',
						'title'       => __('Product Slug', 'ultimate-multisite'),
						'placeholder' => __('e.g. premium', 'ultimate-multisite'),
						'desc'        => __('This serves as a id to the product in a number of different contexts.', 'ultimate-multisite'),
						'value'       => $this->get_object()->get_slug(),
						'tooltip'     => __('Lowercase alpha-numeric characters with dashes or underlines. No spaces allowed.', 'ultimate-multisite'),
						'html_attr'   => [
							'v-on:input'   => 'slug = $event.target.value.toLowerCase().replace(/[^a-z0-9-_]+/g, "")',
							'v-bind:value' => 'slug',
						],
					],
					// Fields for price
					'type'                               => [
						'type'        => 'select',
						'title'       => __('Product Type', 'ultimate-multisite'),
						'placeholder' => __('Product Type', 'ultimate-multisite'),
						'desc'        => __('Different product types have different options.', 'ultimate-multisite'),
						'value'       => $this->get_object()->get_type(),
						'tooltip'     => '',
						'options'     => Product_Type::to_array(),
						'html_attr'   => [
							'v-model' => 'product_type',
						],
					],
					'modules[customer_user_role][limit]' => [
						'title'             => __('Customer Role', 'ultimate-multisite'),
						'desc'              => __('Select the role Ultimate Multisite should use when adding the user to their newly created site.', 'ultimate-multisite'),
						'type'              => 'select',
						'value'             => $this->get_object()->get_customer_role(),
						'default'           => 'administrator',
						'options'           => fn() => wu_get_roles_as_options(true),
						'wrapper_html_attr' => [
							'v-show'  => 'product_type === "plan"',
							'v-cloak' => 1,
						],
					],
				],
			],
		];

		$plans_as_options = wu_get_plans_as_options();

		$sections['ups-and-downs'] = [
			'title'  => __('Up & Downgrades', 'ultimate-multisite'),
			'desc'   => __('Settings related to upgrade and downgrade flows.', 'ultimate-multisite'),
			'icon'   => 'dashicons-wu-shop',
			'v-show' => 'product_type === "plan"',
			'state'  => [],
			'fields' => [
				'group'            => [
					'title'       => __('Plan Group', 'ultimate-multisite'),
					'desc'        => __('Add related plans to the same group to have them show up as upgrade/downgrade paths.', 'ultimate-multisite'),
					'placeholder' => __('Type and press enter to search and/or add.', 'ultimate-multisite'),
					'type'        => 'select',
					'value'       => $this->get_object()->get_group(),
					'options'     => array_merge(['' => __('Select Group', 'ultimate-multisite')], wu_get_product_groups()),
					'html_attr'   => [
						'data-selectize-categories' => 999,
						'data-max-items'            => 1,
					],
				],
				'list_order'       => [
					'title'       => __('Product Order', 'ultimate-multisite'),
					'desc'        => __('Plans are shown in the order determined by this parameter, from the lowest to the highest.', 'ultimate-multisite'),
					'placeholder' => __('Type and press enter to search and/or add.', 'ultimate-multisite'),
					'type'        => 'number',
					'value'       => $this->get_object()->get_list_order(),
				],
				'available_addons' => [
					'type'        => 'model',
					'title'       => __('Offer Add-ons', 'ultimate-multisite'),
					'placeholder' => __('Search for a package or service', 'ultimate-multisite'),
					'desc'        => __('These products will be offered inside upgrade/downgrade forms as order bumps.', 'ultimate-multisite'),
					'html_attr'   => [
						'data-exclude'      => implode(',', array_keys($plans_as_options)),
						'data-model'        => 'product',
						'data-value-field'  => 'id',
						'data-label-field'  => 'name',
						'data-search-field' => 'name',
						'data-max-items'    => 99,
						'data-selected'     => wp_json_encode(
							wu_get_products(
								[
									'id__in'     => $this->get_object()->get_available_addons(),
									'id__not_in' => array_keys($plans_as_options),
								]
							)
						),
					],
				],
			],
		];

		$sections['price-variations'] = [
			'title'  => __('Price Variations', 'ultimate-multisite'),
			'desc'   => __('Discounts for longer membership commitments.', 'ultimate-multisite'),
			'icon'   => 'dashicons-wu-price-tag',
			'state'  => [
				'enable_price_variations' => ! empty($this->get_object()->get_price_variations()),
				'price_variations'        => $this->get_object()->get_price_variations(),
			],
			'fields' => [
				'enable_price_variations' => [
					'type'      => 'toggle',
					'title'     => __('Enable Price Variations', 'ultimate-multisite'),
					'desc'      => __('Price Variations are an easy way to offer discounted prices for longer subscription commitments.', 'ultimate-multisite'),
					'value'     => false,
					'html_attr' => [
						'v-model' => 'enable_price_variations',
					],
				],
				'price_variations'        => [
					'type'              => 'group',
					// translators: 1 is the price, 2 is the duration and 3 the duration unit
					'desc'              => sprintf(__('A discounted price of %1$s will be used when memberships are created with the recurrence of %2$s %3$s(s) instead of the regular period.', 'ultimate-multisite'), '{{ wu_format_money(price_variation.amount) }}', '{{ price_variation.duration }}', '{{ price_variation.duration_unit }}'),
					'tooltip'           => '',
					'wrapper_classes'   => 'wu-relative',
					'wrapper_html_attr' => [
						'v-for'   => '(price_variation, index) in price_variations',
						'v-show'  => 'enable_price_variations',
						'v-cloak' => '1',
					],
					'fields'            => [
						'price_variations_remove'        => [
							'type'            => 'note',
							'desc'            => function () {
								printf(
									'<a title="%s" class="wu-no-underline wu-inline-block wu-text-gray-600 wu-mt-2 wu-mr-2" href="#" v-on:click.prevent="() => price_variations.splice(index, 1)">
										<span class="dashicons-wu-squared-cross"></span>
									</a>',
									esc_html__('Remove', 'ultimate-multisite')
								);
							},
							'wrapper_classes' => 'wu-absolute wu-top-0 wu-right-0',
						],
						'price_variations_duration'      => [
							'type'            => 'number',
							'title'           => __('Duration', 'ultimate-multisite'),
							'placeholder'     => '',
							'wrapper_classes' => 'wu-w-1/3',
							'min'             => 1,
							'html_attr'       => [
								'v-model'     => 'price_variation.duration',
								'steps'       => 1,
								'v-bind:name' => '"price_variations[" + index + "][duration]"',
							],
						],
						'price_variations_duration_unit' => [
							'type'            => 'select',
							'title'           => __('Period', 'ultimate-multisite'),
							'placeholder'     => '',
							'wrapper_classes' => 'wu-w-1/3 wu-mx-2',
							'html_attr'       => [
								'v-model'     => 'price_variation.duration_unit',
								'v-bind:name' => '"price_variations[" + index + "][duration_unit]"',
							],
							'options'         => [
								'day'   => __('Days', 'ultimate-multisite'),
								'week'  => __('Weeks', 'ultimate-multisite'),
								'month' => __('Months', 'ultimate-multisite'),
								'year'  => __('Years', 'ultimate-multisite'),
							],
						],
						// Bind the amount of the price variation to another field so we don't send the formatted value to the server.
						'price_variations_amount'        => [
							'type'      => 'hidden',
							'html_attr' => [
								'v-bind:value' => 'price_variation.amount',
								'v-bind:name'  => '"price_variations[" + index + "][amount]"',
							],
						],
						'_price_variations_amount'       => [
							'type'            => 'text',
							'title'           => __('New Price', 'ultimate-multisite'),
							'placeholder'     => wu_format_currency('99'),
							'wrapper_classes' => 'wu-w-1/3',
							'money'           => true,
							'html_attr'       => [
								'v-model'     => 'price_variation.amount',
								'v-bind:name' => '""',
							],
						],
					],
				],
				'repeat'                  => [
					'type'              => 'submit',
					'title'             => __('Add new Price Variation', 'ultimate-multisite'),
					'classes'           => 'button wu-self-end',
					'wrapper_classes'   => 'wu-bg-whiten wu-items-end',
					'wrapper_html_attr' => [
						'v-show'  => 'enable_price_variations',
						'v-cloak' => '1',
					],
					'html_attr'         => [
						'v-on:click.prevent' => '() => price_variations.push({
							duration: 1,
							duration_unit: "month",
							amount: get_value("wu_product_pricing").amount,
						})',
					],
				],
			],
		];

		$sections['taxes'] = [
			'title'  => __('Taxes', 'ultimate-multisite'),
			'desc'   => __('Tax settings for your products.', 'ultimate-multisite'),
			'icon'   => 'dashicons-wu-credit',
			'state'  => [
				'taxable' => $this->get_object()->is_taxable(),
			],
			'fields' => [
				'taxable'      => [
					'type'      => 'toggle',
					'title'     => __('Is Taxable?', 'ultimate-multisite'),
					'desc'      => __('Enable this if you plan to collect taxes for this product.', 'ultimate-multisite'),
					'value'     => $this->get_object()->is_taxable(),
					'html_attr' => [
						'v-model' => 'taxable',
					],
				],
				'tax_category' => [
					'type'              => 'select',
					'title'             => __('Tax Category', 'ultimate-multisite'),
					'desc'              => __('Select the product tax category.', 'ultimate-multisite'),
					'value'             => $this->get_object()->get_tax_category(),
					'options'           => 'wu_get_tax_categories_as_options',
					'wrapper_html_attr' => [
						'v-cloak' => '1',
						'v-show'  => 'require("taxable", true)',
					],
				],
			],
		];

		$sections['allowed_templates'] = [
			'title'  => __('Site Templates', 'ultimate-multisite'),
			'desc'   => __('Limit which site templates are available for this particular template.', 'ultimate-multisite'),
			'icon'   => 'dashicons-wu-grid1 wu-align-text-bottom',
			'v-show' => "get_state_value('product_type', 'none') !== 'service'",
			'state'  => [
				'allow_site_templates'         => $this->get_object()->get_limitations()->site_templates->is_enabled(),
				'site_template_selection_mode' => $this->get_object()->get_limitations()->site_templates->get_mode(),
				'pre_selected_template'        => $this->get_object()->get_limitations()->site_templates->get_pre_selected_site_template(),
			],
			'fields' => [
				'modules[site_templates][enabled]' => [
					'type'              => 'toggle',
					'title'             => __('Allow Site Templates', 'ultimate-multisite'),
					'desc'              => __('Toggle this option on to allow this plan to use Site Templates. If this option is disabled, sign-ups on this plan will get a default WordPress site.', 'ultimate-multisite'),
					'wrapper_html_attr' => [
						'v-cloak' => '1',
					],
					'html_attr'         => [
						'v-model' => 'allow_site_templates',
					],
				],
				'modules[site_templates][mode]'    => [
					'type'              => 'select',
					'title'             => __('Site Template Selection Mode', 'ultimate-multisite'),
					'placeholder'       => __('Site Template Selection Mode', 'ultimate-multisite'),
					'desc'              => __('Select the type of limitation you want to apply.', 'ultimate-multisite'),
					'tooltip'           => __('"Default" will follow the settings of the checkout form: if you have a template selection field in there, all the templates selected will show up. If no field is present, then a default WordPress site will be created. <br><br>"Assign Site Template" forces new accounts with this plan to use a particular template site (this option removes the template selection field from the signup, if one exists). <br><br>Finally, "Choose Available Site Templates", overrides the templates selected on the checkout form with the templates selected here, while also giving you the chance of pre-select a template to be used as default.', 'ultimate-multisite'),
					'value'             => Limit_Site_Templates::MODE_DEFAULT,
					'options'           => [
						Limit_Site_Templates::MODE_DEFAULT => __('Default - Allow All Site Templates', 'ultimate-multisite'),
						Limit_Site_Templates::MODE_ASSIGN_TEMPLATE => __('Assign Site Template', 'ultimate-multisite'),
						Limit_Site_Templates::MODE_CHOOSE_AVAILABLE_TEMPLATES => __('Choose Available Site Templates', 'ultimate-multisite'),
					],
					'html_attr'         => [
						'v-model' => 'site_template_selection_mode',
					],
					'wrapper_html_attr' => [
						'v-cloak' => '1',
						'v-show'  => 'allow_site_templates',
					],
				],
				'templates'                        => [
					'type'              => 'html',
					'title'             => __('Site Templates', 'ultimate-multisite'),
					'desc'              => esc_attr(sprintf('{{ site_template_selection_mode === "' . Limit_Site_Templates::MODE_ASSIGN_TEMPLATE . '" ? "%s" : "%s" }}', __('Select the Site Template to assign.', 'ultimate-multisite'), __('Customize the access level of each Site Template below.', 'ultimate-multisite'))),
					'wrapper_html_attr' => [
						'v-cloak' => '1',
						'v-show'  => "allow_site_templates && site_template_selection_mode !== 'default'",
					],
					'content'           => fn() => $this->get_site_template_selection_list($this->get_object()),
				],
			],
		];

		$sections['demo-settings'] = [
			'title'  => __('Demo Settings', 'ultimate-multisite'),
			'desc'   => __('Configure how this demo product behaves. These settings only apply when the product type is "Demo".', 'ultimate-multisite'),
			'icon'   => 'dashicons-wu-clock',
			'v-show' => 'product_type === "demo"',
			'state'  => [
				'demo_behavior' => $this->get_object()->get_demo_behavior(),
			],
			'fields' => [
				'demo_behavior' => [
					'type'      => 'select',
					'title'     => __('Demo Expiry Behavior', 'ultimate-multisite'),
					'desc'      => __('Choose what happens when the customer\'s demo period ends. <strong>Delete after time</strong> automatically removes the site after the configured duration. <strong>Keep until live</strong> keeps the site indefinitely with the frontend blocked — the customer must explicitly activate it to make it visible to visitors.', 'ultimate-multisite'),
					'value'     => $this->get_object()->get_demo_behavior(),
					'options'   => [
						'delete_after_time' => __('Delete after time (auto-expire)', 'ultimate-multisite'),
						'keep_until_live'   => __('Keep until customer goes live', 'ultimate-multisite'),
					],
					'html_attr' => [
						'v-model' => 'demo_behavior',
					],
				],
			],
		];

		return apply_filters('wu_product_options_sections', $sections, $this->get_object());
	}

	/**
	 * Returns the HTML markup for the plugin selector list.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Product $product The product being edited.
	 * @return void
	 */
	public function get_site_template_selection_list($product) {

		$all_templates = wu_get_site_templates();

		wu_get_template(
			'limitations/site-template-selector',
			[
				'templates' => $all_templates,
				'product'   => $product,
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

		return $this->edit ? __('Edit Product', 'ultimate-multisite') : __('Add new Product', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Edit Product', 'ultimate-multisite');
	}

	/**
	 * Returns the action links for that page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function action_links() {

		$actions = [];

		if ($this->get_object()->get_type() === 'plan' && $this->edit) {
			$shareable_link = $this->get_object()->get_shareable_link();

			$actions[] = [
				'url'     => '#',
				'label'   => __('Click to copy Shareable Link', 'ultimate-multisite'),
				'icon'    => 'wu-attachment',
				'classes' => 'wu-copy',
				'attrs'   => [
					'data-clipboard-text' => $shareable_link,
				],
			];
		}

		return $actions;
	}

	/**
	 * Returns the labels to be used on the admin page.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_labels() {

		return [
			'edit_label'          => __('Edit Product', 'ultimate-multisite'),
			'add_new_label'       => __('Add new Product', 'ultimate-multisite'),
			'updated_message'     => __('Product updated with success!', 'ultimate-multisite'),
			'title_placeholder'   => __('Enter Product Name', 'ultimate-multisite'),
			'title_description'   => __('This name will be used on pricing tables, invoices, and more.', 'ultimate-multisite'),
			'save_button_label'   => __('Save Product', 'ultimate-multisite'),
			'save_description'    => '',
			'delete_button_label' => __('Delete Product', 'ultimate-multisite'),
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
			'object_type' => 'product',
			'object_id'   => absint($this->get_object()->get_id()),
		];

		return array_merge($args, $extra_args);
	}

	/**
	 * Returns the object being edit at the moment.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Product
	 */
	public function get_object() {

		if (null !== $this->object) {
			return $this->object;
		}

		if (isset($_GET['id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query = new \WP_Ultimo\Database\Products\Product_Query();

			$item = $query->get_item_by('id', (int) $_GET['id']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( ! $item) {
				wp_safe_redirect(wu_network_admin_url('wp-ultimo-products'));

				exit;
			}

			$this->object = $item;

			return $this->object;
		}

		$this->object = new Product();

		return $this->object;
	}

	/**
	 * Products have titles.
	 *
	 * @since 2.0.0
	 */
	public function has_title(): bool {

		return true;
	}

	/**
	 * Should implement the processes necessary to save the changes made to the object.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function handle_save(): bool {
		/*
		 * Set the recurring value to zero if the toggle is disabled.
		 */
		if ( ! wu_request('recurring')) {
			$_POST['recurring'] = false;
		}

		if ( ! wu_request('legacy_options')) {
			$_POST['legacy_options'] = false;
		}

		if ( ! wu_request('featured_plan')) {
			$_POST['featured_plan'] = false;
		}

		/*
		 * Set the setup fee value to zero if the toggle is disabled.
		 */
		if ( ! wu_request('has_setup_fee')) {
			$_POST['setup_fee'] = 0;
		}

		/*
		 * Disabled Trial
		 */
		if ( ! wu_request('has_trial')) {
			$_POST['trial_duration'] = 0;
		}

		/*
		 * Set the setup fee value to zero if the toggle is disabled.
		 */
		if ( ! wu_request('price_variations')) {
			$_POST['price_variations'] = [];
		}

		/*
		 * Set available addons to empty array if not provided.
		 */
		if ( ! wu_request('available_addons')) {
			$_POST['available_addons'] = [];
		}

		/*
		 * Set the taxable value to zero if the toggle is disabled.
		 */
		if ( ! wu_request('taxable')) {
			$_POST['taxable'] = 0;
		}

		return parent::handle_save();
	}
}
