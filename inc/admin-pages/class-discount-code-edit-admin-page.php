<?php
/**
 * Ultimate Multisite Discount_Code Edit/Add New Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Discount_Code;
use WP_Ultimo\Managers\Discount_Code_Manager;

/**
 * Ultimate Multisite Discount_Code Edit/Add New Admin Page.
 */
class Discount_Code_Edit_Admin_Page extends Edit_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-edit-discount-code';

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
	public $object_id = 'discount_code';

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
	protected $highlight_menu_slug = 'wp-ultimo-discount-codes';

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
		'network_admin_menu' => 'wu_edit_discount_codes',
	];

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
						'title'       => __('Description', 'ultimate-multisite'),
						'placeholder' => __('Tell your customers what this product is about.', 'ultimate-multisite'),
						'value'       => $this->get_object()->get_description(),
						'html_attr'   => [
							'rows' => 3,
						],
					],
				],
			]
		);

		$tz_note = sprintf('The site timezone is <code>%s</code>. The current time is <code>%s</code>', date_i18n('e'), date_i18n('r'));

		$options = [
			'general'         => [
				'title'  => __('Limit Uses', 'ultimate-multisite'),
				'icon'   => 'dashicons-wu-lock',
				'desc'   => __('Rules and limitations to the applicability of this discount code.', 'ultimate-multisite'),
				'fields' => [
					'uses'     => [
						'title'         => __('Uses', 'ultimate-multisite'),
						'type'          => 'text-display',
						// translators: %d is the number of times the coupon was used.
						'display_value' => sprintf(__('This discount code was used %d times.', 'ultimate-multisite'), $this->get_object()->get_uses()),
						'tooltip'       => __('The number of times that this discount code was used so far.', 'ultimate-multisite'),
					],
					'max_uses' => [
						'title'       => __('Max Uses', 'ultimate-multisite'),
						'desc'        => __('Use this option to set a limit on how many times this discount code can be used. Leave blank or 0 for unlimited uses.', 'ultimate-multisite'),
						'type'        => 'number',
						'min'         => 0,
						'placeholder' => 0,
						'value'       => $this->get_object()->has_max_uses() ? $this->get_object()->get_max_uses() : __('Unlimited', 'ultimate-multisite'),
					],
				],
			],
			'time'            => [
				'title'  => __('Start & Expiration Dates', 'ultimate-multisite'),
				'desc'   => __('Define a start and end date for this discount code. Useful when running campaigns for a pre-determined period.', 'ultimate-multisite'),
				'icon'   => 'dashicons-wu-calendar',
				'state'  => [
					'enable_date_start'      => $this->get_object()->get_date_start(),
					'enable_date_expiration' => $this->get_object()->get_date_expiration(),
				],
				'fields' => [
					'enable_date_start'      => [
						'type'      => 'toggle',
						'title'     => __('Enable Start Date', 'ultimate-multisite'),
						'desc'      => __('Allows you to set a start date for this coupon code.', 'ultimate-multisite'),
						'value'     => 1,
						'html_attr' => [
							'v-model' => 'enable_date_start',
						],
					],
					'date_start'             => [
						'title'             => __('Start Date', 'ultimate-multisite'),
						'desc'              => __('The discount code will only be good to be used after this date.', 'ultimate-multisite') . ' ' . $tz_note,
						'type'              => 'text',
						'date'              => true,
						'value'             => $this->edit ? $this->get_object()->get_date_start() : __('No date', 'ultimate-multisite'),
						'placeholder'       => 'E.g. 2020-04-04 12:00:00',
						'wrapper_html_attr' => [
							'v-cloak' => 1,
							'v-show'  => 'enable_date_start',
						],
						'html_attr'         => [
							'v-bind:name'     => 'enable_date_start ? "date_start" : ""',
							'wu-datepicker'   => 'true',
							'data-format'     => 'Y-m-d H:i:S',
							'data-allow-time' => 'true',
						],
					],
					'enable_date_expiration' => [
						'type'      => 'toggle',
						'title'     => __('Enable Expiration Date', 'ultimate-multisite'),
						'desc'      => __('Allows you to set an expiration date for this coupon code.', 'ultimate-multisite'),
						'value'     => 1,
						'html_attr' => [
							'v-model' => 'enable_date_expiration',
						],
					],
					'date_expiration'        => [
						'title'             => __('Expiration Date', 'ultimate-multisite'),
						'desc'              => __('The discount code will expire after this date.', 'ultimate-multisite') . ' ' . $tz_note,
						'type'              => 'text',
						'date'              => true,
						'value'             => $this->edit ? $this->get_object()->get_date_expiration() : __('Never Expires', 'ultimate-multisite'),
						'placeholder'       => 'E.g. 2020-04-04 12:00:00',
						'wrapper_html_attr' => [
							'v-cloak' => 1,
							'v-show'  => 'enable_date_expiration',
						],
						'html_attr'         => [
							'v-bind:name'     => 'enable_date_expiration ? "date_expiration" : ""',
							'wu-datepicker'   => 'true',
							'data-format'     => 'Y-m-d H:i:S',
							'data-allow-time' => 'true',
						],
					],
				],
			],
			'products'        => [
				'title'  => __('Limit Products', 'ultimate-multisite'),
				'desc'   => __('Determine if you want this discount code to apply to all discountable products or not.', 'ultimate-multisite'),
				'icon'   => 'dashicons-wu-price-tag',
				'state'  => [
					'limit_products' => $this->get_object()->get_limit_products(),
				],
				'fields' => array_merge(
					[
						'limit_products' => [
							'type'      => 'toggle',
							'title'     => __('Select Products', 'ultimate-multisite'),
							'desc'      => __('Manually select to which products this discount code should be applicable.', 'ultimate-multisite'),
							'value'     => 1,
							'html_attr' => [
								'v-model' => 'limit_products',
							],
						],
					],
					$this->get_product_field_list()
				),
			],
			'billing_periods' => [
				'title'  => __('Limit Billing Periods', 'ultimate-multisite'),
				'desc'   => __('Restrict this discount code to specific billing periods (e.g., only monthly or only annual plans).', 'ultimate-multisite'),
				'icon'   => 'dashicons-wu-calendar',
				'state'  => [
					'limit_billing_periods' => $this->get_object()->get_limit_billing_periods(),
				],
				'fields' => array_merge(
					[
						'limit_billing_periods' => [
							'type'      => 'toggle',
							'title'     => __('Select Billing Periods', 'ultimate-multisite'),
							'desc'      => __('Manually select which billing periods this discount code should be applicable to.', 'ultimate-multisite'),
							'value'     => 1,
							'html_attr' => [
								'v-model' => 'limit_billing_periods',
							],
						],
					],
					$this->get_billing_period_field_list()
				),
			],
		];

		$this->add_tabs_widget(
			'options',
			[
				'title'    => __('Advanced Options', 'ultimate-multisite'),
				'position' => 'normal',
				'sections' => apply_filters('wu_discount_code_options_sections', $options, $this->get_object()),
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

		$this->add_save_widget(
			'save',
			[
				'html_attr' => [
					'data-wu-app' => 'save_discount_code',
					'data-state'  => wu_convert_to_state(
						[
							'apply_to_setup_fee' => $this->get_object()->get_setup_fee_value() > 0,
							'code'               => $this->get_object()->get_code(),
							'type'               => $this->get_object()->get_type(),
							'value'              => $this->get_object()->get_value(),
							'setup_fee_type'     => $this->get_object()->get_setup_fee_type(),
							'setup_fee_value'    => $this->get_object()->get_setup_fee_value(),
						]
					),
				],
				'fields'    => [
					'code'                  => [
						'title'             => __('Coupon Code', 'ultimate-multisite'),
						'type'              => 'text',
						'placeholder'       => __('E.g. XMAS10OFF', 'ultimate-multisite'),
						'desc'              => __('The actual code your customers will enter during checkout.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_code(),
						'tooltip'           => '',
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'v-on:input'   => 'code = $event.target.value.toUpperCase().replace(/[^A-Z0-9-_]+/g, "")',
							'v-bind:value' => 'code',
						],
					],
					'value_group'           => [
						'type'              => 'group',
						'title'             => __('Discount', 'ultimate-multisite'),
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
						'fields'            => [
							'type'  => [
								'type'            => 'select',
								'value'           => $this->get_object()->get_type(),
								'placeholder'     => '',
								'wrapper_classes' => 'wu-w-2/3',
								'options'         => [
									'percentage' => __('Percentage (%)', 'ultimate-multisite'),
									// translators: %s is the currency symbol. e.g. $
									'absolute'   => sprintf(__('Absolute (%s)', 'ultimate-multisite'), wu_get_currency_symbol()),
								],
								'html_attr'       => [
									'v-model' => 'type',
								],
							],
							'value' => [
								'type'            => 'number',
								'value'           => $this->get_object()->get_value(),
								'placeholder'     => '',
								'wrapper_classes' => 'wu-ml-2 wu-w-1/3',
								'html_attr'       => [
									'min'        => 0,
									'v-bind:max' => "type === 'percentage' ? 100 : 999999999",
									'step'       => 'any',
								],
							],
						],
					],
					'apply_to_renewals'     => [
						'type'              => 'toggle',
						'title'             => __('Apply to Renewals', 'ultimate-multisite'),
						'desc'              => __('By default, discounts are only applied to the first payment.', 'ultimate-multisite'),
						'value'             => $this->get_object()->should_apply_to_renewals(),
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
					],
					'apply_to_setup_fee'    => [
						'type'              => 'toggle',
						'title'             => __('Setup Fee Discount', 'ultimate-multisite'),
						'desc'              => __('Also set a discount for setup fee?', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_setup_fee_value() > 0,
						'html_attr'         => [
							'v-model' => 'apply_to_setup_fee',
						],
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
					],
					'setup_fee_value_group' => [
						'type'              => 'group',
						'title'             => __('Setup Fee Discount', 'ultimate-multisite'),
						'wrapper_html_attr' => [
							'v-show'  => 'apply_to_setup_fee',
							'v-cloak' => '1',
						],
						'fields'            => [
							'setup_fee_type'  => [
								'type'            => 'select',
								'value'           => $this->get_object()->get_setup_fee_type(),
								'placeholder'     => '',
								'wrapper_classes' => 'wu-w-2/3',
								'options'         => [
									'percentage' => __('Percentage (%)', 'ultimate-multisite'),
									// translators: %s is the currency symbol. e.g. $
									'absolute'   => sprintf(__('Absolute (%s)', 'ultimate-multisite'), wu_get_currency_symbol()),
								],
								'html_attr'       => [
									'v-model' => 'setup_fee_type',
								],
							],
							'setup_fee_value' => [
								'type'            => 'number',
								'value'           => $this->get_object()->get_setup_fee_value(),
								'placeholder'     => '',
								'wrapper_classes' => 'wu-ml-2 wu-w-1/3',
								'html_attr'       => [
									'min'        => 0,
									'v-bind:max' => "setup_fee_type === 'percentage' ? 100 : 999999999",
								],
							],
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
						'desc'  => __('Use this option to manually enable or disable this discount code for new sign-ups.', 'ultimate-multisite'),
						'value' => $this->get_object()->is_active(),
					],
				],
			]
		);
	}

	/**
	 * List of products to apply this coupon to.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_product_field_list() {

		$fields = [];

		foreach (wu_get_products() as $product) {
			$product_id = $product->get_id();

			$fields[ "allowed_products_{$product_id}" ] = [
				'type'              => 'toggle',
				'title'             => $product->get_name(),
				'desc'              => __('Make applicable to this product.', 'ultimate-multisite'),
				'tooltip'           => '',
				'wrapper_classes'   => '',
				'html_attr'         => [
					':name'    => "'allowed_products[]'",
					':checked' => wp_json_encode(!$this->get_object()->get_limit_products() || in_array($product_id, $this->get_object()->get_allowed_products())), // phpcs:ignore
					':value'   => $product_id,
				],
				'wrapper_html_attr' => [
					'v-cloak' => 1,
					'v-show'  => 'limit_products',
				],
			];

			// TODO: this is a hack-y fix. Needs to be re-implemented.
			$fields['allowed_products_none'] = [
				'type'      => 'hidden',
				'value'     => '__none',
				'html_attr' => [
					':name' => "'allowed_products[]'",
				],
			];
		}

		if (empty($fields)) {
			$fields['allowed_products_no_products'] = [
				'type'              => 'note',
				'title'             => '',
				'desc'              => __('You do not have any products at this moment.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-cloak' => 1,
					'v-show'  => 'limit_products',
				],
			];
		}

		return $fields;
	}

	/**
	 * List of billing periods to apply this coupon to.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_billing_period_field_list() {

		$fields          = [];
		$billing_periods = $this->get_available_billing_periods();
		$allowed_periods = $this->get_object()->get_allowed_billing_periods();
		$limit_periods   = $this->get_object()->get_limit_billing_periods();

		foreach ($billing_periods as $period_key => $period_label) {
			$fields[ "allowed_billing_periods_{$period_key}" ] = [
				'type'              => 'toggle',
				'title'             => $period_label,
				'desc'              => __('Make applicable to this billing period.', 'ultimate-multisite'),
				'tooltip'           => '',
				'wrapper_classes'   => '',
				'html_attr'         => [
					':name'    => "'allowed_billing_periods[]'",
					':checked' => wp_json_encode(! $limit_periods || in_array($period_key, $allowed_periods, true)),
					':value'   => wp_json_encode($period_key),
				],
				'wrapper_html_attr' => [
					'v-cloak' => 1,
					'v-show'  => 'limit_billing_periods',
				],
			];
		}

		// Hidden field to ensure at least one value is submitted
		$fields['allowed_billing_periods_none'] = [
			'type'      => 'hidden',
			'value'     => '__none',
			'html_attr' => [
				':name' => "'allowed_billing_periods[]'",
			],
		];

		if (empty($billing_periods)) {
			$fields['allowed_billing_periods_no_periods'] = [
				'type'              => 'note',
				'title'             => '',
				'desc'              => __('No billing periods found. Create products with different billing periods first.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-cloak' => 1,
					'v-show'  => 'limit_billing_periods',
				],
			];
		}

		return $fields;
	}

	/**
	 * Get all available billing periods from products.
	 *
	 * Scans all products to find unique billing period combinations.
	 *
	 * @since 2.0.0
	 * @return array Associative array of period_key => label.
	 */
	protected function get_available_billing_periods() {

		$periods = [];

		foreach (wu_get_products() as $product) {
			if ( ! $product->is_recurring()) {
				continue;
			}

			$duration      = $product->get_duration();
			$duration_unit = $product->get_duration_unit();
			$period_key    = Discount_Code::get_billing_period_key($duration, $duration_unit);

			if ( ! isset($periods[ $period_key ])) {
				$periods[ $period_key ] = $this->format_billing_period_label($duration, $duration_unit);
			}

			// Also check for price variations
			$price_variations = $product->get_price_variations();

			if ( ! empty($price_variations)) {
				foreach ($price_variations as $variation) {
					$var_duration      = isset($variation['duration']) ? (int) $variation['duration'] : 0;
					$var_duration_unit = isset($variation['duration_unit']) ? $variation['duration_unit'] : '';

					if ($var_duration > 0 && ! empty($var_duration_unit)) {
						$var_period_key = Discount_Code::get_billing_period_key($var_duration, $var_duration_unit);

						if ( ! isset($periods[ $var_period_key ])) {
							$periods[ $var_period_key ] = $this->format_billing_period_label($var_duration, $var_duration_unit);
						}
					}
				}
			}
		}

		// Sort by duration for consistent display
		uksort(
			$periods,
			function ($a, $b) {
				$a_parts = Discount_Code::parse_billing_period_key($a);
				$b_parts = Discount_Code::parse_billing_period_key($b);

				if ( ! $a_parts || ! $b_parts) {
					return 0;
				}

				// Convert to days for comparison
				$a_days = $this->get_period_in_days($a_parts['duration'], $a_parts['duration_unit']);
				$b_days = $this->get_period_in_days($b_parts['duration'], $b_parts['duration_unit']);

				return $a_days <=> $b_days;
			}
		);

		return $periods;
	}

	/**
	 * Format a billing period label for display.
	 *
	 * @since 2.0.0
	 * @param int    $duration The billing duration.
	 * @param string $duration_unit The billing duration unit.
	 * @return string Human-readable label.
	 */
	protected function format_billing_period_label(int $duration, string $duration_unit): string {

		$unit_labels = [
			'day'   => [
				'singular' => __('Day', 'ultimate-multisite'),
				'plural'   => __('Days', 'ultimate-multisite'),
			],
			'week'  => [
				'singular' => __('Week', 'ultimate-multisite'),
				'plural'   => __('Weeks', 'ultimate-multisite'),
			],
			'month' => [
				'singular' => __('Month', 'ultimate-multisite'),
				'plural'   => __('Months', 'ultimate-multisite'),
			],
			'year'  => [
				'singular' => __('Year', 'ultimate-multisite'),
				'plural'   => __('Years', 'ultimate-multisite'),
			],
		];

		$unit_label = isset($unit_labels[ $duration_unit ])
			? (1 === $duration ? $unit_labels[ $duration_unit ]['singular'] : $unit_labels[ $duration_unit ]['plural'])
			: $duration_unit;

		if (1 === $duration) {
			return $unit_label;
		}

		return sprintf('%d %s', $duration, $unit_label);
	}

	/**
	 * Convert a billing period to days for sorting purposes.
	 *
	 * @since 2.0.0
	 * @param int    $duration The billing duration.
	 * @param string $duration_unit The billing duration unit.
	 * @return int Approximate number of days.
	 */
	protected function get_period_in_days(int $duration, string $duration_unit): int {

		$multipliers = [
			'day'   => 1,
			'week'  => 7,
			'month' => 30,
			'year'  => 365,
		];

		$multiplier = isset($multipliers[ $duration_unit ]) ? $multipliers[ $duration_unit ] : 1;

		return $duration * $multiplier;
	}

	/**
	 * Handles legacy advanced options for coupons.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_legacy_options(): void {

		global $wp_filter;

		$tabs = [__('Legacy Add-ons', 'ultimate-multisite')];

		if ( ! isset($wp_filter['wp_ultimo_coupon_advanced_options'])) {
			return;
		}

		wp_enqueue_style('wu-legacy-admin-tabs', wu_get_asset('legacy-admin-tabs.css', 'css'), false, wu_get_version());

		$priorities = $wp_filter['wp_ultimo_coupon_advanced_options']->callbacks;

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

						call_user_func($callable['function'], $this->get_object());
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
	 * Register ajax forms that we use for discount code.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {
		/*
		 * Delete Discount code - Confirmation modal
		 */

		add_filter(
			'wu_data_json_success_delete_discount_code_modal',
			fn($data_json) => [ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				'redirect_url' => wu_network_admin_url('wp-ultimo-discount-codes', ['deleted' => 1]),
			]
		);
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
			'object_type' => 'discount_code',
			'object_id'   => absint($this->get_object()->get_id()),
		];

		return array_merge($args, $extra_args);
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return $this->edit ? __('Edit Discount Code', 'ultimate-multisite') : __('Add new Discount Code', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Edit Discount Code', 'ultimate-multisite');
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
			'edit_label'          => __('Edit Discount Code', 'ultimate-multisite'),
			'add_new_label'       => __('Add new Discount Code', 'ultimate-multisite'),
			'updated_message'     => __('Discount Code updated successfully!', 'ultimate-multisite'),
			'title_placeholder'   => __('Enter Discount Code', 'ultimate-multisite'),
			'title_description'   => '',
			'save_button_label'   => __('Save Discount Code', 'ultimate-multisite'),
			'save_description'    => '',
			'delete_button_label' => __('Delete Discount Code', 'ultimate-multisite'),
			'delete_description'  => __('Be careful. This action is irreversible.', 'ultimate-multisite'),
		];
	}

	/**
	 * Returns the object being edit at the moment.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Discount_Code
	 */
	public function get_object() {

		if (null !== $this->object) {
			return $this->object;
		}

		if (isset($_GET['id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$item_id = wu_request('id', 0);

			$item = wu_get_discount_code($item_id);

			if ( ! $item) {
				wp_safe_redirect(wu_network_admin_url('wp-ultimo-discount_codes'));

				exit;
			}

			$this->object = $item;

			return $this->object;
		}

		$this->object = new Discount_Code();

		return $this->object;
	}

	/**
	 * Discount_Codes have titles.
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
	 * @return void
	 */
	public function handle_save(): void {
		/*
		 * Set the recurring value to zero if the toggle is disabled.
		 */
		if ( ! wu_request('apply_to_renewals')) {
			$_POST['apply_to_renewals'] = false;
		}

		/*
		 * Set the limit products value.
		 */
		if ( ! wu_request('limit_products')) {
			$_POST['limit_products'] = false;
		}

		/*
		 * Set the limit billing periods value.
		 */
		if ( ! wu_request('limit_billing_periods')) {
			$_POST['limit_billing_periods'] = false;
		}

		/*
		 * Filter out the placeholder value from allowed_billing_periods.
		 */
		$allowed_billing_periods = wu_request('allowed_billing_periods', []);

		if (is_array($allowed_billing_periods)) {
			$_POST['allowed_billing_periods'] = array_filter($allowed_billing_periods, fn($value) => '__none' !== $value);
		}

		/*
		 * Set the setup fee value to zero if the toggle is disabled.
		 */
		if ( ! wu_request('apply_to_setup_fee')) {
			$_POST['setup_fee_value'] = 0;
		}

		/**
		 * Unset dates to prevent invalid dates
		 */
		if ( ! wu_request('enable_date_start') || ! wu_validate_date(wu_request('date_start'))) {
			$_POST['date_start'] = null;
		}

		if ( ! wu_request('enable_date_expiration') || ! wu_validate_date(wu_request('date_expiration'))) {
			$_POST['date_expiration'] = null;
		}

		$_POST['code'] = trim((string) wu_request('code'));

		parent::handle_save();
	}
}
