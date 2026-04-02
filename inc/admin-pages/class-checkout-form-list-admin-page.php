<?php
/**
 * Ultimate Multisite Checkout Form Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Checkout_Form;

/**
 * Ultimate Multisite Checkout Form Admin Page.
 */
class Checkout_Form_List_Admin_Page extends List_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-checkout-forms';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

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
		'network_admin_menu' => 'wu_read_checkout_forms',
	];

	/**
	 * Register the list page tour.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_widgets(): void {

		\WP_Ultimo\UI\Tours::get_instance()->create_tour(
			'checkout-form-list',
			[
				[
					'id'    => 'checkout-form-list',
					'title' => __('Checkout Forms', 'ultimate-multisite'),
					'text'  => [
						__('Checkout Forms are an easy and flexible way to experiment with different approaches when trying to convert new customers.', 'ultimate-multisite'),
					],
				],
				[
					'id'       => 'default-form',
					'title'    => __('Experiment!', 'ultimate-multisite'),
					'text'     => [
						__('You can create as many checkout forms as you want, with different fields, products on offer, etc.', 'ultimate-multisite'),
						__('Planning on running some sort of promotion? Why not create a custom landing page with a tailor-maid checkout form to go with? The possibilities are endless.', 'ultimate-multisite'),
					],
					'attachTo' => [
						'element' => '#wp-ultimo-wrap > h1 > a:first-child',
						'on'      => 'right',
					],
				],
			]
		);
	}

	/**
	 * Register ajax forms to handle adding new checkout forms.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {
		/*
		 * Add new Checkout Form
		 */
		wu_register_form(
			'add_new_checkout_form',
			[
				'render'     => [$this, 'render_add_new_checkout_form_modal'],
				'handler'    => [$this, 'handle_add_new_checkout_form_modal'],
				'capability' => 'wu_edit_checkout_forms',
			]
		);
	}

	/**
	 * Renders the add new customer modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_add_new_checkout_form_modal(): void {

		$fields = [
			'template'      => [
				'type'        => 'select-icon',
				'title'       => __('Checkout Form Template', 'ultimate-multisite'),
				'desc'        => __('Select a starting point for a new Checkout Form.', 'ultimate-multisite'),
				'placeholder' => '',
				'tooltip'     => '',
				'value'       => '',
				'classes'     => 'wu-w-1/3',
				'html_attr'   => [
					'v-model' => 'template',
				],
				'options'     => [
					'single-step' => [
						'title' => __('Single Step', 'ultimate-multisite'),
						'icon'  => 'dashicons-before dashicons-list-view',
					],
					'multi-step'  => [
						'title' => __('Multi-Step', 'ultimate-multisite'),
						'icon'  => 'dashicons-before dashicons-excerpt-view',
					],
					'simple'      => [
						'title' => __('Simple (Email Only)', 'ultimate-multisite'),
						'icon'  => 'dashicons-before dashicons-email-alt',
					],
					'blank'       => [
						'title' => __('Blank', 'ultimate-multisite'),
						'icon'  => 'dashicons-before dashicons-admin-page',
					],
				],
			],
			'submit_button' => [
				'type'            => 'submit',
				'title'           => __('Go to the Editor &rarr;', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end',
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'add_new_checkout_form',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'add_checkout_form_field',
					'data-state'  => wp_json_encode(
						[
							'template' => 'single-step',
						]
					),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles creation of a new memberships.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_add_new_checkout_form_modal(): void {

		$template = wu_request('template');

		$checkout_form = new \WP_Ultimo\Models\Checkout_Form();

		$checkout_form->use_template($template);

		$checkout_form->set_name(__('Draft Checkout Form', 'ultimate-multisite'));

		$checkout_form->set_slug(uniqid());

		$checkout_form->set_skip_validation(true);

		$status = $checkout_form->save();

		if (is_wp_error($status)) {
			wp_send_json_error($status);
		} else {
			wp_send_json_success(
				[
					'redirect_url' => wu_network_admin_url(
						'wp-ultimo-edit-checkout-form',
						[
							'id' => $checkout_form->get_id(),
						]
					),
				]
			);
		}
	}

	/**
	 * Returns an array with the labels for the edit page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function get_labels() {

		return [
			'deleted_message' => __('Checkout Form removed successfully.', 'ultimate-multisite'),
			'search_label'    => __('Search Checkout Form', 'ultimate-multisite'),
		];
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Checkout Forms', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Checkout Forms', 'ultimate-multisite');
	}

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return __('Checkout Forms', 'ultimate-multisite');
	}

	/**
	 * Returns the action links for that page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function action_links() {

		return [
			[
				'label'   => __('Add Checkout Form', 'ultimate-multisite'),
				'icon'    => 'wu-circle-with-plus',
				'classes' => 'wubox',
				'url'     => wu_get_form_url('add_new_checkout_form'),
			],
		];
	}

	/**
	 * Loads the list table for this particular page.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\List_Tables\Base_List_Table
	 */
	public function table() {

		return new \WP_Ultimo\List_Tables\Checkout_Form_List_Table();
	}
}
