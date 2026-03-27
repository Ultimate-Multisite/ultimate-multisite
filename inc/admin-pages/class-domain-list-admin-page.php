<?php
/**
 * Ultimate Multisite Dashboard Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Domain;
use WP_Ultimo\Database\Domains\Domain_Stage;

/**
 * Ultimate Multisite Dashboard Admin Page.
 */
class Domain_List_Admin_Page extends List_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-domains';

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
		'network_admin_menu' => 'wu_read_domains',
	];

	/**
	 * Register ajax forms that we use for payments.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {
		/*
		 * Add new Domain
		 */
		wu_register_form(
			'add_new_domain',
			[
				'render'     => [$this, 'render_add_new_domain_modal'],
				'handler'    => [$this, 'handle_add_new_domain_modal'],
				'capability' => 'wu_edit_domains',
			]
		);
	}

	/**
	 * Renders the add new customer modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_add_new_domain_modal(): void {

		$addon_url = wu_network_admin_url(
			'wp-ultimo-addons',
			[
				's' => 'Domain Seller',
			]
		);

		// translators: %s is the URL to the add-on.
		$note_desc = sprintf(__('To activate this feature you need to install the <a href="%s" target="_blank" class="wu-no-underline">Ultimate Multisite: Domain Seller</a> add-on.', 'ultimate-multisite'), $addon_url);

		$fields = [
			'type'                   => [
				'type'      => 'tab-select',
				'options'   => [
					'add'      => __('Add Existing Domain', 'ultimate-multisite'),
					'register' => __('Register New', 'ultimate-multisite'),
				],
				'html_attr' => [
					'v-model' => 'type',
				],
			],
			'domain'                 => [
				'type'              => 'text',
				'title'             => __('Domain', 'ultimate-multisite'),
				'placeholder'       => __('E.g. mydomain.com', 'ultimate-multisite'),
				'desc'              => __('Be sure the domain has the right DNS setup in place before adding it.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-show' => "require('type', 'add')",
				],
			],
			'blog_id'                => [
				'type'              => 'model',
				'title'             => __('Apply to Site', 'ultimate-multisite'),
				'placeholder'       => __('Search Sites...', 'ultimate-multisite'),
				'desc'              => __('The target site of the domain being added.', 'ultimate-multisite'),
				'html_attr'         => [
					'data-model'        => 'site',
					'data-value-field'  => 'blog_id',
					'data-label-field'  => 'title',
					'data-search-field' => 'title',
					'data-max-items'    => 1,
				],
				'wrapper_html_attr' => [
					'v-show' => "require('type', 'add')",
				],
			],
			'stage'                  => [
				'type'        => 'select',
				'title'       => __('Stage', 'ultimate-multisite'),
				'placeholder' => __('Select Stage', 'ultimate-multisite'),
				'desc'        => __('The stage in the domain check lifecycle. Leave "Checking DNS" to have the domain go through Ultimate Multisite\'s automated tests.', 'ultimate-multisite'),
				'options'     => Domain_Stage::to_array(),
				'value'       => Domain_Stage::CHECKING_DNS,
			],
			'primary_domain'         => [
				'type'      => 'toggle',
				'title'     => __('Primary Domain', 'ultimate-multisite'),
				'desc'      => __('Check to set this domain as the primary', 'ultimate-multisite'),
				'html_attr' => [
					'v-model' => 'primary_domain',
				],
			],
			'primary_note'           => [
				'type'              => 'note',
				'desc'              => __('By making this the primary domain, we will convert the previous primary domain for this site, if one exists, into an alias domain.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-show' => "require('primary_domain', true)",
				],
			],
			'submit_button_new'      => [
				'type'              => 'submit',
				'title'             => __('Add Existing Domain', 'ultimate-multisite'),
				'value'             => 'save',
				'classes'           => 'button button-primary wu-w-full',
				'wrapper_classes'   => 'wu-items-end',
				'wrapper_html_attr' => [
					'v-show' => "require('type', 'add')",
				],
			],
			'addon_note'             => [
				'type'              => 'note',
				'desc'              => $note_desc,
				'classes'           => 'wu-p-2 wu-bg-blue-100 wu-text-gray-600 wu-rounded wu-w-full',
				'wrapper_html_attr' => [
					'v-show' => "require('type', 'register')",
				],
			],
			'submit_button_register' => [
				'type'              => 'submit',
				'title'             => __('Register and Add Domain (soon)', 'ultimate-multisite'),
				'value'             => 'save',
				'classes'           => 'button button-primary wu-w-full',
				'wrapper_classes'   => 'wu-items-end',
				'wrapper_html_attr' => [
					'v-show' => "require('type', 'register')",
				],
				'html_attr'         => [
					'disabled' => 'disabled',
				],
			],
		];

		/**
		 * Filters the fields for the add new domain modal.
		 *
		 * Allows addons (e.g. Domain Seller) to modify or replace
		 * the domain registration fields.
		 *
		 * @since 2.1.0
		 * @param array $fields The form fields.
		 */
		$fields = apply_filters('wu_add_new_domain_modal_fields', $fields);

		$form = new \WP_Ultimo\UI\Form(
			'add_new_domain',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'add_new_domain',
					'data-state'  => wp_json_encode(
						[
							'type'           => 'add',
							'primary_domain' => false,
						]
					),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles creation of a new customer.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_add_new_domain_modal(): void {

		/**
		 * Fires before handle the add new domain modal request.
		 *
		 * @since 2.0.0
		 */
		do_action('wu_handle_add_new_domain_modal');

		if (wu_request('type', 'add') === 'add') {
			/*
			 * Tries to create the domain
			 */
			$domain = wu_create_domain(
				[
					'domain'         => wu_request('domain'),
					'stage'          => wu_request('stage'),
					'blog_id'        => (int) wu_request('blog_id'),
					'primary_domain' => (bool) wu_request('primary_domain'),
				]
			);

			if (is_wp_error($domain)) {
				wp_send_json_error($domain);
			}

			wu_enqueue_async_action('wu_async_process_domain_stage', ['domain_id' => $domain->get_id()], 'domain');

			wp_send_json_success(
				[
					'redirect_url' => wu_network_admin_url(
						'wp-ultimo-edit-domain',
						[
							'id' => $domain->get_id(),
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
			'deleted_message' => __('Domains removed successfully.', 'ultimate-multisite'),
			'search_label'    => __('Search Domains', 'ultimate-multisite'),
		];
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Domains', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Domains', 'ultimate-multisite');
	}

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return __('Domains', 'ultimate-multisite');
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
				'label'   => __('Add Domain', 'ultimate-multisite'),
				'icon'    => 'wu-circle-with-plus',
				'classes' => 'wubox',
				'url'     => wu_get_form_url('add_new_domain'),
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

		return new \WP_Ultimo\List_Tables\Domain_List_Table();
	}
}
