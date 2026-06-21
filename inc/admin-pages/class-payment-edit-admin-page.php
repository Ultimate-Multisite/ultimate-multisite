<?php
/**
 * Ultimate Multisite Payment Edit/Add New Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Payment;
use WP_Ultimo\Database\Payments\Payment_Status;

/**
 * Ultimate Multisite Payment Edit/Add New Admin Page.
 */
class Payment_Edit_Admin_Page extends Edit_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-edit-payment';

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
	public $object_id = 'payment';

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
	protected $highlight_menu_slug = 'wp-ultimo-payments';

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
		'network_admin_menu' => 'wu_edit_payments',
	];

	/**
	 * Registers the necessary scripts and styles for this admin page.
	 *
	 * @since 2.0.4
	 * @return void
	 */
	public function register_scripts(): void {

		parent::register_scripts();

		wp_enqueue_editor();
	}

	/**
	 * Register ajax forms that we use for payments.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {
		/*
		 * Edit/Add Line Item
		 */
		wu_register_form(
			'edit_line_item',
			[
				'render'     => [$this, 'render_edit_line_item_modal'],
				'handler'    => [$this, 'handle_edit_line_item_modal'],
				'capability' => 'wu_edit_payments',
			]
		);

		/*
		 * Delete Line Item - Confirmation modal
		 */
		wu_register_form(
			'delete_line_item',
			[
				'render'     => [$this, 'render_delete_line_item_modal'],
				'handler'    => [$this, 'handle_delete_line_item_modal'],
				'capability' => 'wu_delete_payments',
			]
		);

		/*
		 * Refund Line Item
		 */
		wu_register_form(
			'refund_payment',
			[
				'render'     => [$this, 'render_refund_payment_modal'],
				'handler'    => [$this, 'handle_refund_payment_modal'],
				'capability' => 'wu_refund_payments',
			]
		);

		/*
		 * Resend Invoice
		 */
		wu_register_form(
			'resend_invoice',
			[
				'render'     => [$this, 'render_resend_invoice_modal'],
				'handler'    => [$this, 'handle_resend_invoice_modal'],
				'capability' => 'wu_edit_payments',
			]
		);

		/*
		 * Delete - Confirmation modal
		 */
		add_filter(
			'wu_data_json_success_delete_payment_modal',
			fn($data_json) => [ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				'redirect_url' => wu_network_admin_url('wp-ultimo-payments', ['deleted' => 1]),
			]
		);
	}

	/**
	 * Renders the deletion confirmation form.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_delete_line_item_modal(): void {

		$payment = wu_get_payment(wu_request('id'));

		$line_item = wu_get_line_item(wu_request('line_item_id'), $payment->get_id());

		if ( ! $line_item || ! $payment) {
			return;
		}

		$fields = [
			'confirm'       => [
				'type'      => 'toggle',
				'title'     => __('Confirm Deletion', 'ultimate-multisite'),
				'desc'      => __('This action can not be undone.', 'ultimate-multisite'),
				'html_attr' => [
					'v-model' => 'confirmed',
				],
			],
			'submit_button' => [
				'type'            => 'submit',
				'title'           => __('Delete', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end',
				'html_attr'       => [
					'v-bind:disabled' => '!confirmed',
				],
			],
			'id'            => [
				'type'  => 'hidden',
				'value' => $payment->get_id(),
			],
			'line_item_id'  => [
				'type'  => 'hidden',
				'value' => $line_item->get_id(),
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'total-actions',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'true',
					'data-state'  => wu_convert_to_state(
						[
							'confirmed' => false,
						]
					),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the deletion of line items.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_delete_line_item_modal(): void {

		if ( ! wu_request('confirm')) {
			wp_send_json_error(new \WP_Error('not-confirmed', __('Please confirm the deletion.', 'ultimate-multisite')));
		}

		$payment = wu_get_payment(wu_request('id'));

		$line_item = wu_get_line_item(wu_request('line_item_id'), $payment->get_id());

		if ( ! $payment || ! $line_item) {
			wp_send_json_error(new \WP_Error('not-found', __('Payment not found.', 'ultimate-multisite')));
		}

		$line_items = $payment->get_line_items();

		unset($line_items[ $line_item->get_id() ]);

		$payment->set_line_items($line_items);

		$saved = $payment->recalculate_totals()->save();

		if (is_wp_error($saved)) {
			wp_send_json_error($saved);
		}
		$referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

		wp_send_json_success(
			[
				'redirect_url' => add_query_arg('updated', 1, $referer),
			]
		);
	}

	/**
	 * Renders the refund line item modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_refund_payment_modal(): void {

		$payment = wu_get_payment(wu_request('id'));

		if ( ! $payment) {
			return;
		}

		$fields = [
			'_amount'                   => [
				'type'              => 'text',
				'title'             => __('Refund Amount', 'ultimate-multisite'),
				'placeholder'       => __('Refund Amount', 'ultimate-multisite'),
				'money'             => true,
				'min'               => 0,
				'html_attr'         => [
					'v-model'    => 'amount',
					'step'       => '0.01',
					'v-bind:max' => 'total',
				],
				'wrapper_html_attr' => [
					'v-show' => 'step === 1',
				],
			],
			'amount'                    => [
				'type'      => 'hidden',
				'html_attr' => [
					'v-model' => 'amount',
				],
			],
			'cancel_membership'         => [
				'type'              => 'toggle',
				'title'             => __('Cancel Related Membership?', 'ultimate-multisite'),
				'desc'              => __('Checking this option will cancel the membership as well.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-show' => 'step === 1',
				],
			],
			'refund_not_immediate_note' => [
				'type'              => 'note',
				'desc'              => __('Confirming the refund might not immediately change the status of the payment, as each gateway handles refunds differently and Ultimate Multisite relies on the gateway reporting a successful refund before changing the status.', 'ultimate-multisite'),
				'classes'           => 'wu-p-2 wu-bg-yellow-200 wu-text-yellow-700 wu-rounded wu-w-full',
				'wrapper_html_attr' => [
					'v-show'  => 'step === 2',
					'v-cloak' => '1',
				],
			],
			'confirm'                   => [
				'type'              => 'toggle',
				'title'             => __('Confirm Refund', 'ultimate-multisite'),
				'desc'              => __('This action can not be undone.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-show' => 'step === 2',
				],
				'html_attr'         => [
					'v-model' => 'confirmed',
				],
			],
			'submit_button'             => [
				'type'              => 'submit',
				'title'             => __('Next Step', 'ultimate-multisite'),
				'placeholder'       => __('Next Step', 'ultimate-multisite'),
				'value'             => 'save',
				'classes'           => 'button button-primary wu-w-full',
				'wrapper_classes'   => 'wu-items-end',
				'wrapper_html_attr' => [
					'v-show' => 'step === 1',
				],
				'html_attr'         => [
					'v-bind:disabled'    => 'amount <= 0 || amount > total',
					'v-on:click.prevent' => 'step = 2',
				],
			],
			'submit_button_2'           => [
				'type'              => 'submit',
				'title'             => __('Issue Refund', 'ultimate-multisite'),
				'placeholder'       => __('Issue Refund', 'ultimate-multisite'),
				'value'             => 'save',
				'classes'           => 'button button-primary wu-w-full',
				'wrapper_classes'   => 'wu-items-end',
				'html_attr'         => [
					'v-bind:disabled' => '!confirmed',
				],
				'wrapper_html_attr' => [
					'v-show' => 'step === 2',
				],
			],
			'id'                        => [
				'type'  => 'hidden',
				'value' => $payment->get_id(),
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'total-actions',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'refund',
					'data-state'  => wu_convert_to_state(
						[
							'step'      => 1,
							'confirmed' => false,
							'total'     => round($payment->get_total(), 2),
							'amount'    => round($payment->get_total(), 2),
						]
					),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the deletion of line items.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_refund_payment_modal(): void {

		if ( ! wu_request('confirm')) {
			wp_send_json_error(new \WP_Error('not-confirmed', __('Please confirm the refund.', 'ultimate-multisite')));
		}

		$amount = wu_to_float(wu_request('amount'));

		$payment = wu_get_payment(wu_request('id'));

		if ( ! $payment) {
			wp_send_json_error(new \WP_Error('not-found', __('Payment not found.', 'ultimate-multisite')));
		}

		/*
		 * Checks for a valid amount.
		 */
		if (empty($amount) || $amount > $payment->get_total()) {
			wp_send_json_error(new \WP_Error('invalid-amount', __('The refund amount is out of bounds.', 'ultimate-multisite')));
		}

		/*
		 * Check if the payment is in a
		 * refundable status.
		 */
		$is_refundable = in_array($payment->get_status(), wu_get_refundable_payment_types(), true);

		if ( ! $is_refundable) {
			wp_send_json_error(new \WP_Error('payment-not-refunded', __('This payment is not in a refundable state.', 'ultimate-multisite')));
		}

		/*
		 * First we set the flag to cancel membership
		 * if we need to.
		 *
		 * This MUST be handled by the gateway when
		 * receiving the webhook call confirming
		 * the refund was successful.
		 */
		$should_cancel_membership_on_refund = wu_request('cancel_membership');

		$payment->set_cancel_membership_on_refund($should_cancel_membership_on_refund);

		/*
		 * Get the gateway.
		 */
		$gateway_id = $payment->get_gateway();

		if ( ! $gateway_id) {
			/*
			 * The payment does not have a
			 * gateway attached to it.
			 * Immediately refunds.
			 */
			$status = $payment->refund($amount, $should_cancel_membership_on_refund);

			if (is_wp_error($status)) {
				wp_send_json_error($status);
			}

			/*
			 * Done! Redirect back.
			 */
			wp_send_json_success(
				[
					'redirect_url' => wu_network_admin_url(
						'wp-ultimo-edit-payment',
						[
							'id'      => $payment->get_id(),
							'updated' => 1,
						]
					),
				]
			);
		}

		$gateway = wu_get_gateway($gateway_id);

		if ( ! $gateway) {
			wp_send_json_error(new \WP_Error('gateway-not-found', __('Payment gateway not found.', 'ultimate-multisite')));
		}

		/*
		 * Process the refund on the gateway.
		 */
		try {
			/*
			 * We set the cancel membership flag, so we
			 * need to save it so the gateway can use it
			 * later.
			 */
			$payment->save();

			/*
			 * After that, we create the objects we need to pass over
			 * to the gateway.
			 */
			$membership = $payment->get_membership();
			$customer   = $payment->get_customer();

			/*
			 * Passes it over to the gateway
			 */
			$status = $gateway->process_refund($amount, $payment, $membership, $customer);

			if (is_wp_error($status)) {

				// translators: %s is the exception error message.
				$error = new \WP_Error('refund-error', sprintf(__('An error occurred: %s', 'ultimate-multisite'), $status->get_error_message()));

				wp_send_json_error($error);
			}
		} catch (\Throwable $e) {

			// translators: %s is the exception error message.
			$error = new \WP_Error('refund-error', sprintf(__('An error occurred: %s', 'ultimate-multisite'), $e->getMessage()));

			wp_send_json_error($error);
		}

		/*
		 * Done! Redirect back.
		 */
		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url(
					'wp-ultimo-edit-payment',
					[
						'id'      => $payment->get_id(),
						'updated' => 1,
					]
				),
			]
		);
	}

	/**
	 * Handles the add/edit of line items.
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function handle_edit_line_item_modal(): void {

		$payment = wu_get_payment(wu_request('payment_id'));

		if ( ! $payment) {
			wp_send_json_error(new \WP_Error('not-found', __('Payment not found.', 'ultimate-multisite')));
		}

		$line_item = wu_get_line_item(wu_request('line_item_id'), $payment->get_id());

		if ( ! $line_item) {
			$line_item = new \WP_Ultimo\Checkout\Line_Item([]);
		}

		/*
		 * First, we get the type.
		 *
		 * We basically have 4 types:
		 * 1. Product
		 * 2. Fee
		 * 3. Credit
		 * 4. Refund
		 */
		$type = wu_request('type', 'product');

		if ('product' === $type) {
			$product = wu_get_product(wu_request('product_id'));

			if (empty($product)) {
				$error = new \WP_Error('missing-product', __('The product was not found.', 'ultimate-multisite'));

				wp_send_json_error($error);
			}

			/*
			 * Constructs the arguments
			 * for the product line item.
			 */
			$atts = [
				'product'     => $product,
				'quantity'    => wu_request('quantity', 1),
				'unit_price'  => wu_to_float(wu_request('unit_price')),
				'title'       => wu_request('title'),
				'description' => wu_request('description'),
				'tax_rate'    => wu_request('tax_rate', 0),
				'tax_type'    => wu_request('tax_type', 'percentage'),
				'tax_label'   => wu_request('tax_label', ''),
			];
		} else {

			/**
			 * Now, we deal with all the
			 * types.
			 *
			 * First, check the valid types.
			 */
			$allowed_types = apply_filters(
				'wu_allowed_line_item_types',
				[
					'fee',
					'refund',
					'credit',
				]
			);

			if ( ! in_array($type, $allowed_types, true)) {
				$error = new \WP_Error('invalid-type', __('The line item type is invalid.', 'ultimate-multisite'));

				wp_send_json_error($error);
			}

			/*
			 * Set the new attributes
			 */
			$atts = [
				'quantity'    => 1,
				'title'       => wu_request('title', ''),
				'description' => wu_request('description', '--'),
				'unit_price'  => wu_to_float(wu_request('unit_price')),
				'tax_rate'    => 0,
				'tax_type'    => 'percentage',
				'tax_label'   => '',
			];
		}

		$line_item->attributes($atts);

		$line_item->recalculate_totals();

		$line_items = $payment->get_line_items();

		$line_items[ $line_item->get_id() ] = $line_item;

		$payment->set_line_items($line_items);

		$saved = $payment->recalculate_totals()->save();

		if ( ! $saved) {
			wp_send_json_error(new \WP_Error('error', __('Something wrong happened.', 'ultimate-multisite')));
		}

		if (is_wp_error($saved)) {
			wp_send_json_error($saved);
		}
		$referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

		wp_send_json_success(
			[
				'redirect_url' => add_query_arg('updated', 1, $referer),
			]
		);
	}

	/**
	 * Renders the add/edit line items form.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_edit_line_item_modal(): void {
		/*
		 * Get the payment line item.
		 */
		$line_item = wu_get_line_item(wu_request('line_item_id'), wu_request('id'));

		if ( ! $line_item) {
			/*
			 * If that doesn't work,
			 * we start a new line.
			 */
			$line_item = new \WP_Ultimo\Checkout\Line_Item([]);
		}

		$fields = [
			'tab'                => [
				'type'      => 'tab-select',
				'options'   => [
					'type' => __('Type', 'ultimate-multisite'),
					'info' => __('Additional Info', 'ultimate-multisite'),
					'tax'  => __('Tax Info', 'ultimate-multisite'),
				],
				'html_attr' => [
					'v-model' => 'tab',
				],
			],
			'type'               => [
				'type'              => 'select',
				'title'             => __('Line Item Type', 'ultimate-multisite'),
				'desc'              => __('Select the line item type.', 'ultimate-multisite'),
				'options'           => [
					'product' => __('Product', 'ultimate-multisite'),
					'refund'  => __('Refund', 'ultimate-multisite'),
					'fee'     => __('Fee', 'ultimate-multisite'),
					'credit'  => __('Credit', 'ultimate-multisite'),
				],
				'wrapper_html_attr' => [
					'v-show' => 'tab === "type"',
				],
				'html_attr'         => [
					'v-model' => 'type',
				],
			],
			'product_id'         => [
				'type'              => 'model',
				'title'             => __('Product', 'ultimate-multisite'),
				'desc'              => __('Product associated with this line item.', 'ultimate-multisite'),
				'placeholder'       => __('Search Products', 'ultimate-multisite'),
				'value'             => $line_item->get_product_id(),
				'tooltip'           => '',
				'wrapper_html_attr' => [
					'v-show' => 'type === "product" && tab === "type"',
				],
				'html_attr'         => [
					'data-model'        => 'product',
					'data-value-field'  => 'id',
					'data-label-field'  => 'name',
					'data-search-field' => 'name',
					'data-max-items'    => 1,
					'data-selected'     => $line_item->get_product() ? wp_json_encode($line_item->get_product()->to_search_results()) : '',
				],
			],
			'title'              => [
				'type'              => 'text',
				'title'             => __('Line Item Title', 'ultimate-multisite'),
				'placeholder'       => __('E.g. Extra Charge', 'ultimate-multisite'),
				'desc'              => __('This is used when generating invoices.', 'ultimate-multisite'),
				'value'             => $line_item->get_title(),
				'wrapper_html_attr' => [
					'v-show' => 'tab === "info"',
				],
			],
			'description'        => [
				'type'              => 'textarea',
				'title'             => __('Line Item Description', 'ultimate-multisite'),
				'placeholder'       => __('E.g. This service was done to improve performance.', 'ultimate-multisite'),
				'desc'              => __('This is used when generating invoices.', 'ultimate-multisite'),
				'value'             => $line_item->get_description(),
				'html_attr'         => [
					'rows' => 4,
				],
				'wrapper_html_attr' => [
					'v-show' => 'tab === "info"',
				],
			],
			'quantity'           => [
				'type'              => 'number',
				'title'             => __('Quantity', 'ultimate-multisite'),
				'desc'              => __('Item quantity.', 'ultimate-multisite'),
				'value'             => $line_item->get_quantity(),
				'placeholder'       => __('E.g. 1', 'ultimate-multisite'),
				'wrapper_classes'   => 'wu-w-1/2',
				'wrapper_html_attr' => [
					'v-show' => 'type === "product" && tab === "type"',
				],
				'html_attr'         => [
					'min'      => 1,
					'required' => 'required',
				],
			],
			'unit_price'         => [
				'type'      => 'hidden',
				'html_attr' => [
					'v-model' => 'unit_price',
				],
			],
			'_unit_price'        => [
				'type'              => 'text',
				'title'             => __('Unit Price', 'ultimate-multisite'),
				'desc'              => __('Item unit price. This is multiplied by the quantity to calculate the sub-total.', 'ultimate-multisite'),
				// translators: %s is a price placeholder value.
				'placeholder'       => sprintf(__('E.g. %s', 'ultimate-multisite'), wu_format_currency(99)),
				'value'             => $line_item->get_unit_price(),
				'money'             => true,
				'wrapper_classes'   => 'wu-w-1/2',
				'wrapper_html_attr' => [
					'v-if' => 'type === "product" && tab === "type"',
				],
				'html_attr'         => [
					'required' => 'required',
					'step'     => '0.01',
					'v-model'  => 'unit_price',
				],
			],
			'_unit_price_amount' => [
				'type'              => 'text',
				'title'             => __('Amount', 'ultimate-multisite'),
				'desc'              => __('Refund, credit or fee amount.', 'ultimate-multisite'),
				// translators: %s is a price placeholder value.
				'placeholder'       => sprintf(__('E.g. %s', 'ultimate-multisite'), wu_format_currency(99)),
				'value'             => $line_item->get_unit_price(),
				'money'             => true,
				'wrapper_classes'   => 'wu-w-1/2',
				'wrapper_html_attr' => [
					'v-if' => 'type !== "product" && tab === "type"',
				],
				'html_attr'         => [
					'required' => 'required',
					'step'     => '0.01',
					'v-model'  => 'unit_price',
				],
			],
			'taxable'            => [
				'type'              => 'toggle',
				'title'             => __('Is Taxable?', 'ultimate-multisite'),
				'desc'              => __('Checking this box will toggle the tax controls.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-bind:class' => 'type !== "product" ? "wu-opacity-50" : ""',
					'v-show'       => 'tab === "tax"',
				],
				'html_attr'         => [
					'v-model'         => 'taxable',
					'v-bind:disabled' => 'type !== "product"',
				],
			],
			'tax_label'          => [
				'type'              => 'text',
				'title'             => __('Tax Label', 'ultimate-multisite'),
				'placeholder'       => __('E.g. ES VAT', 'ultimate-multisite'),
				'desc'              => __('Tax description. This is shown on invoices to end customers.', 'ultimate-multisite'),
				'value'             => $line_item->get_tax_label(),
				'wrapper_html_attr' => [
					'v-show' => 'taxable &&  tab === "tax"',
				],
			],
			'tax_rate_group'     => [
				'type'              => 'group',
				'title'             => __('Tax Rate', 'ultimate-multisite'),
				'desc'              => __('Tax rate and type to apply to this item.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-show' => 'taxable && tab === "tax"',
				],
				'fields'            => [
					'tax_rate' => [
						'type'            => 'number',
						'value'           => $line_item->get_tax_rate(),
						'placeholder'     => '',
						'wrapper_classes' => 'wu-mr-2 wu-w-1/3',
						'html_attr'       => [
							'required' => 'required',
							'step'     => '0.01',
						],
					],
					'tax_type' => [
						'type'            => 'select',
						'value'           => $line_item->get_tax_type(),
						'placeholder'     => '',
						'wrapper_classes' => 'wu-w-2/3',
						'options'         => [
							'percentage' => __('Percentage (%)', 'ultimate-multisite'),
							'absolute'   => __('Flat Rate ($)', 'ultimate-multisite'),
						],
					],
				],
			],
			'submit_button'      => [
				'type'            => 'submit',
				'title'           => __('Save', 'ultimate-multisite'),
				'placeholder'     => __('Save', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'wu-w-full button button-primary',
				'wrapper_classes' => 'wu-items-end',
			],
			'line_item_id'       => [
				'type'  => 'hidden',
				'value' => $line_item->get_id(),
			],
			'payment_id'         => [
				'type'  => 'hidden',
				'value' => wu_request('id'),
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'edit_line_item',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'edit_line_item',
					'data-state'  => wu_convert_to_state(
						[
							'tab'        => 'type',
							'type'       => $line_item->get_type(),
							'taxable'    => $line_item->get_tax_rate() > 0,
							'unit_price' => $line_item->get_unit_price(),
						]
					),
				],
			]
		);

		$form->render();
	}

	/**
	 * Display the payment actions.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function display_payment_actions(): void {

		$actions = [];

		$is_refundable = in_array($this->get_object()->get_status(), wu_get_refundable_payment_types(), true);

		if ($is_refundable) {
			$actions['refund_payment'] = [
				'label'        => __('Refund Payment', 'ultimate-multisite'),
				'icon_classes' => 'dashicons-wu-ccw wu-align-text-bottom',
				'classes'      => 'button wubox',
				'href'         => wu_get_form_url(
					'refund_payment',
					[
						'id' => $this->get_object()->get_id(),
					]
				),
			];
		}

		$actions['add_line_item'] = [
			'label'        => __('Add Line Item', 'ultimate-multisite'),
			'icon_classes' => 'dashicons-wu-circle-with-plus wu-align-text-bottom',
			'classes'      => 'button wubox',
			'href'         => wu_get_form_url(
				'edit_line_item',
				[
					'id' => $this->get_object()->get_id(),
				]
			),
		];

		wu_get_template(
			'payments/line-item-actions',
			[
				'payment' => $this->get_object(),
				'actions' => $actions,
			]
		);
	}

	/**
	 * Displays the tax tax breakthrough.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function display_tax_breakthrough(): void {

		$tax_breakthrough = $this->get_object()->get_tax_breakthrough();

		wu_get_template(
			'payments/tax-details',
			[
				'tax_breakthrough' => $tax_breakthrough,
				'payment'          => $this->get_object(),
			]
		);
	}

	/**
	 * Renders the resend invoice confirmation modal.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function render_resend_invoice_modal(): void {

		$payment = wu_get_payment(wu_request('id'));

		if ( ! $payment) {
			return;
		}

		$fields = [
			'invoice_message' => [
				'type'        => 'textarea',
				'title'       => __('Message (optional)', 'ultimate-multisite'),
				'placeholder' => __('Add a personal note to include in the email...', 'ultimate-multisite'),
				'value'       => '',
				'html_attr'   => [
					'rows' => 3,
				],
			],
			'submit_button'   => [
				'type'            => 'submit',
				'title'           => __('Send Invoice Email', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'wu-w-full button button-primary',
				'wrapper_classes' => 'wu-items-end',
			],
			'id'              => [
				'type'  => 'hidden',
				'value' => $payment->get_id(),
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'resend_invoice',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'resend_invoice',
					'data-state'  => wu_convert_to_state([]),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the resend invoice form submission.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function handle_resend_invoice_modal(): void {

		$payment = wu_get_payment(wu_request('id'));

		if ( ! $payment) {
			wp_send_json_error(new \WP_Error('not-found', __('Payment not found.', 'ultimate-multisite')));

			return;
		}

		$customer = $payment->get_customer();

		if ( ! $customer) {
			wp_send_json_error(new \WP_Error('no-customer', __('No customer found for this payment.', 'ultimate-multisite')));

			return;
		}

		$payload = array_merge(
			wu_generate_event_payload('payment', $payment),
			wu_generate_event_payload('customer', $customer),
			[
				'payment_url'     => $payment->get_payment_url() ?: '',
				'invoice_message' => sanitize_textarea_field(wu_request('invoice_message', '')),
			]
		);

		wu_do_event('invoice_sent', $payload);

		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url(
					'wp-ultimo-edit-payment',
					[
						'id'      => $payment->get_id(),
						'updated' => 1,
					]
				),
			]
		);
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		parent::register_widgets();

		$label = $this->get_object()->get_status_label();

		$class = $this->get_object()->get_status_class();

		$tag = "<span class='wu-bg-gray-200 wu-text-gray-700 wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-font-mono $class'>{$label}</span>";

		$this->add_fields_widget(
			'at_a_glance',
			[
				'title'                 => __('At a Glance', 'ultimate-multisite'),
				'position'              => 'normal',
				'classes'               => 'wu-overflow-hidden wu-widget-inset',
				'field_wrapper_classes' => 'wu-w-1/3 wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t-0 wu-border-l-0 wu-border-r wu-border-b-0 wu-border-gray-300 wu-border-solid wu-float-left wu-relative',
				'fields'                => [
					'status' => [
						'type'          => 'text-display',
						'title'         => __('Payment Status', 'ultimate-multisite'),
						'display_value' => $tag,
						'tooltip'       => '',
					],
					'hash'   => [
						'copy'          => true,
						'type'          => 'text-display',
						'title'         => __('Reference ID', 'ultimate-multisite'),
						'display_value' => $this->get_object()->get_hash(),
					],
					'total'  => [
						'type'            => 'text-display',
						'title'           => __('Total', 'ultimate-multisite'),
						'display_value'   => wu_format_currency($this->get_object()->get_total(), $this->get_object()->get_currency()),
						'wrapper_classes' => 'sm:wu-border-r-0',
					],
				],
			]
		);

		$this->add_list_table_widget(
			'line-items',
			[
				'title'        => __('Line Items', 'ultimate-multisite'),
				'table'        => new \WP_Ultimo\List_Tables\Payment_Line_Item_List_Table(),
				'position'     => 'normal',
				'query_filter' => [$this, 'payments_query_filter'],
				'after'        => [$this, 'display_payment_actions'],
			]
		);

		$this->add_widget(
			'tax-rates',
			[
				'title'    => __('Tax Rate Breakthrough', 'ultimate-multisite'),
				'position' => 'normal',
				'display'  => [$this, 'display_tax_breakthrough'],
			]
		);

		$this->add_tabs_widget(
			'options',
			[
				'title'    => __('Payment Options', 'ultimate-multisite'),
				'position' => 'normal',
				'sections' => apply_filters('wu_payments_options_sections', [], $this->get_object()),
			]
		);

		$this->add_list_table_widget(
			'events',
			[
				'title'        => __('Events', 'ultimate-multisite'),
				'table'        => new \WP_Ultimo\List_Tables\Inside_Events_List_Table(),
				'query_filter' => [$this, 'events_query_filter'],
			]
		);

		$membership = $this->get_object()->get_membership();

		$this->add_save_widget(
			'save',
			[
				'html_attr' => [
					'data-wu-app' => 'payment_save',
					'data-state'  => wu_convert_to_state(
						[
							'status'            => $this->get_object()->get_status(),
							'original_status'   => $this->get_object()->get_status(),
							'membership_id'     => $membership ? $this->get_object()->get_membership_id() : '',
							'membership_status' => $membership ? $membership->get_status() : 'active',
							'gateway'           => $this->get_object()->get_gateway(),
						]
					),
				],
				'fields'    => [
					'status'                   => [
						'type'              => 'select',
						'title'             => __('Status', 'ultimate-multisite'),
						'placeholder'       => __('Status', 'ultimate-multisite'),
						'desc'              => __('The payment current status.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_status(),
						'options'           => Payment_Status::to_array(),
						'tooltip'           => '',
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'v-model' => 'status',
						],
					],
					'confirm_membership'       => [
						'type'              => 'toggle',
						'title'             => __('Activate Membership?', 'ultimate-multisite'),
						'desc'              => __('This payment belongs to a pending membership. If you toggle this option, this change in status will also apply to the membership. If any sites are pending, they are also going to be published automatically.', 'ultimate-multisite'),
						'value'             => 0,
						'wrapper_html_attr' => [
							'v-if'    => 'status !== original_status && status === "completed" && membership_status === "pending"',
							'v-cloak' => '1',
						],
					],
					'membership_id'            => [
						'type'              => 'model',
						'title'             => __('Membership', 'ultimate-multisite'),
						'desc'              => __('The membership associated with this payment.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_membership_id(),
						'tooltip'           => '',
						'html_attr'         => [
							'v-model'          => 'membership_id',
							'data-base-link'   => wu_network_admin_url('wp-ultimo-edit-membership', ['id' => '']),
							'data-model'       => 'membership',
							'data-value-field' => 'id',
							'data-label-field' => 'reference_code',
							'data-max-items'   => 1,
							'data-selected'    => $this->get_object()->get_membership() ? wp_json_encode($this->get_object()->get_membership()->to_search_results()) : '',
						],
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
					],
					'gateway'                  => [
						'type'              => 'text',
						'title'             => __('Gateway', 'ultimate-multisite'),
						'placeholder'       => __('e.g. stripe', 'ultimate-multisite'),
						'description'       => __('e.g. stripe', 'ultimate-multisite'),
						'desc'              => __('Payment gateway used to process the payment.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_gateway(),
						'wrapper_classes'   => 'wu-w-full',
						'html_attr'         => [
							'v-on:input'   => 'gateway = $event.target.value.toLowerCase().replace(/[^a-z0-9-_]+/g, "")',
							'v-bind:value' => 'gateway',
						],
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
					],
					'gateway_payment_id_group' => [
						'type'              => 'group',
						'desc'              => function (): string {

							$gateway_id = $this->get_object()->get_gateway();

							if (empty($this->get_object()->get_gateway_payment_id())) {
								return '';
							}

							$url = apply_filters("wu_{$gateway_id}_remote_payment_url", $this->get_object()->get_gateway_payment_id());

							if ($url) {
								return sprintf('<a class="wu-text-gray-800 wu-text-center wu-w-full wu-no-underline" href="%s" target="_blank">%s</a>', esc_attr($url), __('View on Gateway &rarr;', 'ultimate-multisite'));
							}

							return '';
						},
						'wrapper_html_attr' => [
							'v-cloak' => '1',
							'v-show'  => 'gateway',
						],
						'fields'            => [
							'gateway_payment_id' => [
								'type'              => 'text',
								'title'             => __('Gateway Payment ID', 'ultimate-multisite'),
								'placeholder'       => __('e.g. EX897540987913', 'ultimate-multisite'),
								'description'       => __('e.g. EX897540987913', 'ultimate-multisite'),
								'tooltip'           => __('This will usually be set automatically by the payment gateway.', 'ultimate-multisite'),
								'value'             => $this->get_object()->get_gateway_payment_id(),
								'wrapper_classes'   => 'wu-w-full',
								'html_attr'         => [],
								'wrapper_html_attr' => [],
							],
						],
					],
					'invoice_number'           => [
						'type'              => 'number',
						'min'               => 0,
						'title'             => __('Invoice Number', 'ultimate-multisite'),
						'placeholder'       => __('e.g. 20', 'ultimate-multisite'),
						'tooltip'           => __('This number gets saved automatically when a payment transitions to a complete state. You can change it to generate invoices with a particular number. The number chosen here has no effect on other invoices in the platform.', 'ultimate-multisite'),
						'desc'              => __('The invoice number for this particular payment.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_saved_invoice_number(),
						'wrapper_classes'   => 'wu-w-full',
						'wrapper_html_attr' => [
							'v-show'  => wp_json_encode(wu_get_setting('invoice_numbering_scheme', 'reference_code') === 'sequential_number'),
							'v-cloak' => '1',
						],
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

		return $this->edit ? __('Edit Payment', 'ultimate-multisite') : __('Add new Payment', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Edit Payment', 'ultimate-multisite');
	}

	/**
	 * Returns the action links for that page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function action_links() {

		$actions = [];

		$payment = $this->get_object();

		if ($payment) {
			$actions[] = [
				'url'   => $payment->get_invoice_url(),
				'label' => __('Generate Invoice', 'ultimate-multisite'),
				'icon'  => 'wu-attachment',
			];

			if ($payment->is_payable()) {
				$actions[] = [
					'url'   => $payment->get_payment_url(),
					'label' => __('Payment URL', 'ultimate-multisite'),
					'icon'  => 'wu-credit-card',
				];

				$actions[] = [
					'url'     => wu_get_form_url(
						'resend_invoice',
						[
							'id' => $payment->get_id(),
						]
					),
					'label'   => __('Send Invoice Email', 'ultimate-multisite'),
					'icon'    => 'wu-mail',
					'classes' => 'wubox',
				];
			}
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
			'edit_label'          => __('Edit Payment', 'ultimate-multisite'),
			'add_new_label'       => __('Add new Payment', 'ultimate-multisite'),
			'updated_message'     => __('Payment updated with success!', 'ultimate-multisite'),
			'title_placeholder'   => __('Enter Payment Name', 'ultimate-multisite'),
			'title_description'   => __('This name will be used on pricing tables, invoices, and more.', 'ultimate-multisite'),
			'save_button_label'   => __('Save Payment', 'ultimate-multisite'),
			'save_description'    => '',
			'delete_button_label' => __('Delete Payment', 'ultimate-multisite'),
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
	public function events_query_filter($args) {

		$extra_args = [
			'object_type' => 'payment',
			'object_id'   => absint($this->get_object()->get_id()),
		];

		return array_merge($args, $extra_args);
	}

	/**
	 * Filters the list table to return only relevant events.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Query args passed to the list table.
	 * @return array Modified query args.
	 */
	public function payments_query_filter($args) {

		$extra_args = [
			'parent'     => absint($this->get_object()->get_id()),
			'parent__in' => false,
		];

		return array_merge($args, $extra_args);
	}

	/**
	 * Returns the object being edit at the moment.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Payment
	 */
	public function get_object() {

		static $payment;

		if (null !== $payment) {
			return $payment;
		}

		if (isset($_GET['id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query = new \WP_Ultimo\Database\Payments\Payment_Query();

			$item = $query->get_item_by('id', (int) $_GET['id']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( ! $item || $item->get_parent_id()) {
				wp_safe_redirect(wu_network_admin_url('wp-ultimo-payments'));

				exit;
			}

			$payment = $item;

			return $item;
		}

		return new Payment();
	}

	/**
	 * Payments have titles.
	 *
	 * @since 2.0.0
	 */
	public function has_title(): bool {

		return false;
	}

	/**
	 * WIP: Handles saving by recalculating totals for a payment.
	 *
	 * @todo: This can not be handled here.
	 * @since 2.0.0
	 * @return bool
	 */
	public function handle_save(): bool {

		$this->get_object()->recalculate_totals()->save();

		$should_confirm_membership = wu_request('confirm_membership');

		if ($should_confirm_membership) {
			$membership = $this->get_object()->get_membership();

			if ($membership) {
				$membership->add_to_times_billed(1);

				$membership->renew(false, 'active');
			}
		}

		return parent::handle_save();
	}
}
