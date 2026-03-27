<?php
/**
 * Ultimate Multisite Domain Edit/Add New Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Database\Domains\Domain_Stage;

/**
 * Ultimate Multisite Domain Edit/Add New Admin Page.
 */
class Domain_Edit_Admin_Page extends Edit_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-edit-domain';

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
	public $object_id = 'domain';

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
	protected $highlight_menu_slug = 'wp-ultimo-domains';

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
		'network_admin_menu' => 'wu_edit_domains',
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
		add_filter('wu_form_fields_delete_domain_modal', [$this, 'domain_extra_delete_fields'], 10, 2);

		add_action('wu_after_delete_domain_modal', [$this, 'domain_after_delete_actions']);

		/*
		 * Register admin DNS management forms.
		 */
		wu_register_form(
			'admin_add_dns_record',
			[
				'render'  => [$this, 'render_admin_add_dns_record_modal'],
				'handler' => [$this, 'handle_admin_add_dns_record_modal'],
			]
		);

		wu_register_form(
			'admin_edit_dns_record',
			[
				'render'  => [$this, 'render_admin_edit_dns_record_modal'],
				'handler' => [$this, 'handle_admin_edit_dns_record_modal'],
			]
		);

		wu_register_form(
			'admin_delete_dns_record',
			[
				'render'  => [$this, 'render_admin_delete_dns_record_modal'],
				'handler' => [$this, 'handle_admin_delete_dns_record_modal'],
			]
		);
	}
	/**
	 * Registers the necessary scripts and styles for this admin page.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {
		parent::register_scripts();

		$domain_id = $this->get_object()->get_id();

		// Enqueue read-only DNS table for PHP DNS lookup fallback
		wp_enqueue_script(
			'wu-dns-table',
			wu_get_asset('dns-table.js', 'js'),
			['jquery', 'wu-vue'],
			\WP_Ultimo::VERSION,
			[
				'in_footer' => true,
			]
		);

		// Enqueue DNS management script for provider-based management
		wp_enqueue_script(
			'wu-dns-management',
			wu_get_asset('dns-management.js', 'js'),
			['jquery', 'wu-vue'],
			\WP_Ultimo::VERSION,
			[
				'in_footer' => true,
			]
		);

		wp_enqueue_script(
			'wu-domain-logs',
			wu_get_asset('domain-logs.js', 'js'),
			['jquery'],
			\WP_Ultimo::VERSION,
			[
				'in_footer' => true,
			]
		);

		// Config for read-only DNS lookup
		wp_localize_script(
			'wu-dns-table',
			'wu_dns_table_config',
			[
				'domain' => $this->get_object()->get_domain(),
			]
		);

		// Config for DNS management (provider-based)
		wp_localize_script(
			'wu-dns-management',
			'wu_dns_config',
			[
				'nonce'      => wp_create_nonce('wu_dns_nonce'),
				'add_url'    => wu_get_form_url('admin_add_dns_record', ['domain_id' => $domain_id]),
				'edit_url'   => wu_get_form_url('admin_edit_dns_record', ['domain_id' => $domain_id]),
				'delete_url' => wu_get_form_url('admin_delete_dns_record', ['domain_id' => $domain_id]),
			]
		);

		wp_localize_script(
			'wu-domain-logs',
			'wu_domain_logs',
			[
				'log_file' => \WP_Ultimo\Logger::get_logs_folder() . 'domain-' . $this->get_object()->get_domain() . '.log',
			]
		);
	}

	/**
	 * Adds the extra delete fields to the delete form.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $fields The original fields.
	 * @param object $domain The domain object.
	 * @return array
	 */
	public function domain_extra_delete_fields($fields, $domain) {

		$is_primary_domain = $domain->is_primary_domain();

		$has_other_domains = false;

		if ($is_primary_domain) {
			$other_domains = \WP_Ultimo\Models\Domain::get_by_site($domain->get_blog_id());

			$has_other_domains = is_countable($other_domains) ? count($other_domains) - 1 : false;
		}

		$custom_fields = [
			'set_domain_as_primary' => [
				'type'              => 'model',
				'title'             => __('Set another domain as primary', 'ultimate-multisite'),
				'html_attr'         => [
					'data-model'        => 'domain',
					'data-value-field'  => 'id',
					'data-label-field'  => 'domain',
					'data-search-field' => 'domain',
					'data-max-items'    => 1,
					'data-exclude'      => wp_json_encode([$domain->get_id()]),
					'data-include'      => wp_json_encode($domain->get_blog_id()),
				],
				'wrapper_html_attr' => [
					'v-if' => $is_primary_domain && $has_other_domains ? 'true' : 'false',
				],
			],
			'confirm'               => [
				'type'      => 'toggle',
				'title'     => __('Confirm Deletion', 'ultimate-multisite'),
				'desc'      => __('This action can not be undone.', 'ultimate-multisite'),
				'html_attr' => [
					'v-model' => 'confirmed',
				],
			],
			'submit_button'         => [
				'type'            => 'submit',
				'title'           => __('Delete', 'ultimate-multisite'),
				'placeholder'     => __('Delete', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end',
				'html_attr'       => [
					'v-bind:disabled' => '!confirmed',
				],
			],
			'id'                    => [
				'type'  => 'hidden',
				'value' => $domain->get_id(),
			],
		];

		return array_merge($custom_fields, $fields);
	}

	/**
	 * Adds the primary domain handling to the domain deletion.
	 *
	 * @since 2.0.0
	 *
	 * @param object $domain The domain object.
	 * @return void
	 */
	public function domain_after_delete_actions($domain): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens in the form handler
		$new_primary_domain_name = wu_request('set_domain_as_primary');

		$new_primary_domain = wu_get_domain($new_primary_domain_name);

		if ($new_primary_domain) {
			$new_primary_domain->set_primary_domain(true);

			$new_primary_domain->save();
		}
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
			'domain-url',
			[
				'title'    => __('Domain URL', 'ultimate-multisite'),
				'position' => 'normal',
				'after'    => [$this, 'render_dns_widget'],
				'fields'   => [
					'domain' => [
						'type'          => 'text-display',
						'title'         => __('Domain', 'ultimate-multisite'),
						'tooltip'       => __('Editing an existing domain is not possible. If you want to make changes to this domain, first delete it, and then re-add the right domain.', 'ultimate-multisite'),
						'display_value' => '<span class="wu-text-sm wu-uppercase wu-font-mono">' . $this->get_object()->get_domain() . '</span> <a target="_blank" class="wu-no-underline" href="' . esc_url($this->get_object()->get_url()) . '"><span class="dashicons-wu-link1	"></span></a>',
					],
				],
			]
		);

		$this->add_tabs_widget(
			'options',
			[
				'title'    => __('Domain Options', 'ultimate-multisite'),
				'position' => 'normal',
				'sections' => [
					'general' => [
						'title'  => __('General', 'ultimate-multisite'),
						'desc'   => __('General options for the domain.', 'ultimate-multisite'),
						'icon'   => 'dashicons-wu-globe',
						'state'  => [
							'primary_domain' => $this->get_object()->is_primary_domain(),
						],
						'fields' => [
							'primary_domain' => [
								'type'      => 'toggle',
								'title'     => __('Is Primary Domain?', 'ultimate-multisite'),
								'desc'      => __('Set as the primary domain.', 'ultimate-multisite'),
								'tooltip'   => __('Setting this as the primary domain will remove any other domain mapping marked as the primary domain for this site.', 'ultimate-multisite'),
								'value'     => $this->get_object()->is_primary_domain(),
								'html_attr' => [
									'v-model' => 'primary_domain',
								],
							],
							'primary_note'   => [
								'type'              => 'note',
								'desc'              => __('By making this the primary domain, we will convert the previous primary domain for this site, if one exists, into an alias domain.', 'ultimate-multisite'),
								'wrapper_html_attr' => [
									'v-if' => "require('primary_domain', true)",
								],
							],
							'secure'         => [
								'type'  => 'toggle',
								'title' => __('Is Secure?', 'ultimate-multisite'),
								'desc'  => __('Force the load using HTTPS.', 'ultimate-multisite'),
								'value' => $this->get_object()->is_secure(),
							],
						],
					],
				],
			]
		);

		$this->add_list_table_widget(
			'sites',
			[
				'title'        => __('Linked Site', 'ultimate-multisite'),
				'table'        => new \WP_Ultimo\List_Tables\Memberships_Site_List_Table(),
				'query_filter' => [$this, 'sites_query_filter'],
			]
		);

		add_meta_box('wp-ultimo-domain-log', __('Domain Test Log', 'ultimate-multisite'), [$this, 'render_log_widget'], get_current_screen()->id, 'normal', null);

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
					'data-wu-app' => 'save',
					'data-state'  => wu_convert_to_state(
						[
							'stage' => $this->get_object()->get_stage(),
						]
					),
				],
				'fields'    => [
					'stage'   => [
						'type'              => 'select',
						'title'             => __('Stage', 'ultimate-multisite'),
						'placeholder'       => __('Select Stage', 'ultimate-multisite'),
						'desc'              => __('The stage in the checking lifecycle of this domain.', 'ultimate-multisite'),
						'options'           => Domain_Stage::to_array(),
						'value'             => $this->get_object()->get_stage(),
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
						'html_attr'         => [
							'@change' => 'window.wu_basic.stage = $event.target.value',
							'v-model' => 'stage',
						],
					],
					'blog_id' => [
						'type'              => 'model',
						'title'             => __('Site', 'ultimate-multisite'),
						'placeholder'       => __('Search Site...', 'ultimate-multisite'),
						'desc'              => __('The target site of this domain.', 'ultimate-multisite'),
						'value'             => $this->get_object()->get_blog_id(),
						'tooltip'           => '',
						'html_attr'         => [
							'data-model'        => 'site',
							'data-value-field'  => 'blog_id',
							'data-label-field'  => 'title',
							'data-search-field' => 'title',
							'data-max-items'    => 1,
							'data-selected'     => $this->get_object()->get_site() ? wp_json_encode($this->get_object()->get_site()->to_search_results()) : '',
						],
						'wrapper_html_attr' => [
							'v-cloak' => '1',
						],
					],
				],
			]
		);

		$check_for_active_string = sprintf('%s.includes(stage)', wp_json_encode(\WP_Ultimo\Models\Domain::INACTIVE_STAGES));

		$this->add_fields_widget(
			'basic',
			[
				'title'     => __('Active', 'ultimate-multisite'),
				'html_attr' => [
					'data-wu-app' => 'basic',
					'data-state'  => wu_convert_to_state(
						[
							'stage' => $this->get_object()->get_stage(),
						]
					),
				],
				'fields'    => [
					'active' => [
						'type'              => 'toggle',
						'title'             => __('Active', 'ultimate-multisite'),
						'desc'              => __('Use this option to manually enable or disable this domain.', 'ultimate-multisite'),
						'value'             => $this->get_object()->is_active(),
						'html_attr'         => [
							'v-cloak'         => '1',
							'v-bind:disabled' => $check_for_active_string,
						],
						'wrapper_html_attr' => [
							'v-bind:class' => "$check_for_active_string ? 'wu-cursor-not-allowed wu-opacity-75' : ''",
						],

					],
					'note'   => [
						'type'              => 'note',
						'desc'              => __('This domain has a domain stage that forces it to be inactive. Change the status to Ready or Ready (without SSL) to be able to control the active status directly.', 'ultimate-multisite'),
						'classes'           => 'wu-p-2 wu-bg-red-100 wu-text-red-600 wu-rounded wu-w-full',
						'wrapper_html_attr' => [
							'v-show'  => $check_for_active_string,
							'v-cloak' => '1',
						],
					],
				],
			]
		);
	}

	/**
	 * Renders the DNS widget
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_dns_widget(): void {

		$dns_manager  = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
		$dns_provider = $dns_manager->get_dns_provider();
		$domain       = $this->get_object();
		$domain_id    = $domain->get_id();

		wu_get_template(
			'domain/admin-dns-management',
			[
				'domain'        => $domain,
				'domain_id'     => $domain_id,
				'can_manage'    => true, // Admins can always manage DNS
				'has_provider'  => (bool) $dns_provider,
				'provider_name' => $dns_provider ? $dns_provider->get_title() : '',
				'add_url'       => wu_get_form_url('admin_add_dns_record', ['domain_id' => $domain_id]),
			]
		);
	}

	/**
	 * Renders the DNS widget
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_log_widget(): void {

		wu_get_template(
			'domain/log',
			[
				'domain'   => $this->get_object(),
				'log_path' => \WP_Ultimo\Logger::get_logs_folder(),
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

		return $this->edit ? __('Edit Domain', 'ultimate-multisite') : __('Add new Domain', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Edit Domain', 'ultimate-multisite');
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
			'edit_label'          => __('Edit Domain', 'ultimate-multisite'),
			'add_new_label'       => __('Add new Domain', 'ultimate-multisite'),
			'updated_message'     => __('Domain updated with success!', 'ultimate-multisite'),
			'title_placeholder'   => __('Enter Domain', 'ultimate-multisite'),
			'title_description'   => '',
			'save_button_label'   => __('Save Domain', 'ultimate-multisite'),
			'save_description'    => '',
			'delete_button_label' => __('Delete Domain', 'ultimate-multisite'),
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
			'object_type' => 'domain',
			'object_id'   => absint($this->get_object()->get_id()),
		];

		return array_merge($args, $extra_args);
	}

	/**
	 * Filters the list table to return only relevant sites.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Query args passed to the list table.
	 * @return array Modified query args.
	 */
	public function sites_query_filter($args) {

		$args['blog_id'] = $this->get_object()->get_site_id();

		return $args;
	}

	/**
	 * Returns the object being edit at the moment.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Domain
	 */
	public function get_object() {

		if (null !== $this->object) {
			return $this->object;
		}

		$item_id = wu_request('id', 0);

		$item = wu_get_domain($item_id);

		if ( ! $item) {
			wp_safe_redirect(wu_network_admin_url('wp-ultimo-domains'));

			exit;
		}

		$this->object = $item;

		return $this->object;
	}

	/**
	 * Domains have titles.
	 *
	 * @since 2.0.0
	 */
	public function has_title(): bool {

		return false;
	}

	/**
	 * Should implement the processes necessary to save the changes made to the object.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function handle_save(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens in parent::handle_save()
		if ( ! wu_request('primary_domain')) {
			$_POST['primary_domain'] = false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens in parent::handle_save()
		if ( ! wu_request('active')) {
			$_POST['active'] = false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens in parent::handle_save()
		if ( ! wu_request('secure')) {
			$_POST['secure'] = false;
		}

		wu_enqueue_async_action('wu_async_process_domain_stage', ['domain_id' => $this->get_object()->get_id()], 'domain');

		return parent::handle_save();
	}

	/**
	 * Renders the admin add DNS record modal.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function render_admin_add_dns_record_modal(): void {

		$domain_id = wu_request('domain_id');
		$domain    = wu_get_domain($domain_id);

		if ( ! $domain) {
			wp_die(esc_html__('Domain not found.', 'ultimate-multisite'));
		}

		$dns_manager  = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
		$dns_provider = $dns_manager->get_dns_provider();

		wu_get_template(
			'domain/dns-record-form',
			[
				'domain_id'     => $domain_id,
				'domain_name'   => $domain->get_domain(),
				'mode'          => 'add',
				'record'        => [],
				'allowed_types' => $dns_provider ? $dns_provider->get_supported_record_types() : ['A', 'AAAA', 'CNAME', 'MX', 'TXT'],
				'show_proxied'  => $dns_provider && method_exists($dns_provider, 'get_id') && $dns_provider->get_id() === 'cloudflare',
			]
		);
	}

	/**
	 * Handles the admin add DNS record modal submission.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function handle_admin_add_dns_record_modal(): void {

		check_ajax_referer('wu_dns_nonce', 'nonce');

		$domain_id = wu_request('domain_id');
		$domain    = wu_get_domain($domain_id);

		if ( ! $domain) {
			wp_send_json_error(['message' => __('Domain not found.', 'ultimate-multisite')]);
		}

		$dns_manager  = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
		$dns_provider = $dns_manager->get_dns_provider();

		if ( ! $dns_provider) {
			wp_send_json_error(['message' => __('No DNS provider configured.', 'ultimate-multisite')]);
		}

		$record_data = wu_request('record', []);

		$result = $dns_provider->create_dns_record($domain->get_domain(), $record_data);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success(
			[
				'message' => __('DNS record created successfully.', 'ultimate-multisite'),
				'record'  => $result,
			]
		);
	}

	/**
	 * Renders the admin edit DNS record modal.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function render_admin_edit_dns_record_modal(): void {

		$domain_id = wu_request('domain_id');
		$record_id = wu_request('record_id');
		$domain    = wu_get_domain($domain_id);

		if ( ! $domain) {
			wp_die(esc_html__('Domain not found.', 'ultimate-multisite'));
		}

		$dns_manager  = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
		$dns_provider = $dns_manager->get_dns_provider();

		// Get the record data from the provider
		$records = $dns_provider ? $dns_provider->get_dns_records($domain->get_domain()) : [];
		$record  = [];

		if ( ! is_wp_error($records)) {
			foreach ($records as $r) {
				if ((string) $r->get_id() === (string) $record_id) {
					$record = $r->to_array();
					break;
				}
			}
		}

		wu_get_template(
			'domain/dns-record-form',
			[
				'domain_id'     => $domain_id,
				'domain_name'   => $domain->get_domain(),
				'mode'          => 'edit',
				'record'        => $record,
				'allowed_types' => $dns_provider ? $dns_provider->get_supported_record_types() : ['A', 'AAAA', 'CNAME', 'MX', 'TXT'],
				'show_proxied'  => $dns_provider && method_exists($dns_provider, 'get_id') && $dns_provider->get_id() === 'cloudflare',
			]
		);
	}

	/**
	 * Handles the admin edit DNS record modal submission.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function handle_admin_edit_dns_record_modal(): void {

		check_ajax_referer('wu_dns_nonce', 'nonce');

		$domain_id = wu_request('domain_id');
		$record_id = wu_request('record_id');
		$domain    = wu_get_domain($domain_id);

		if ( ! $domain) {
			wp_send_json_error(['message' => __('Domain not found.', 'ultimate-multisite')]);
		}

		$dns_manager  = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
		$dns_provider = $dns_manager->get_dns_provider();

		if ( ! $dns_provider) {
			wp_send_json_error(['message' => __('No DNS provider configured.', 'ultimate-multisite')]);
		}

		$record_data = wu_request('record', []);

		$result = $dns_provider->update_dns_record($domain->get_domain(), $record_id, $record_data);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success(
			[
				'message' => __('DNS record updated successfully.', 'ultimate-multisite'),
				'record'  => $result,
			]
		);
	}

	/**
	 * Renders the admin delete DNS record modal.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function render_admin_delete_dns_record_modal(): void {

		$domain_id = wu_request('domain_id');
		$record_id = wu_request('record_id');
		$domain    = wu_get_domain($domain_id);

		if ( ! $domain) {
			wp_die(esc_html__('Domain not found.', 'ultimate-multisite'));
		}

		$dns_manager  = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
		$dns_provider = $dns_manager->get_dns_provider();

		// Get the record data from the provider
		$records     = $dns_provider ? $dns_provider->get_dns_records($domain->get_domain()) : [];
		$record_name = $record_id;

		if ( ! is_wp_error($records)) {
			foreach ($records as $r) {
				if ((string) $r->get_id() === (string) $record_id) {
					$record_name = $r->get_type() . ' - ' . $r->get_name();
					break;
				}
			}
		}

		$fields = [
			'confirm_message' => [
				'type' => 'note',
				'desc' => sprintf(
					/* translators: %s: Record name/identifier */
					__('Are you sure you want to delete the DNS record <strong>%s</strong>? This action cannot be undone.', 'ultimate-multisite'),
					esc_html($record_name)
				),
			],
			'domain_id'       => [
				'type'  => 'hidden',
				'value' => $domain_id,
			],
			'record_id'       => [
				'type'  => 'hidden',
				'value' => $record_id,
			],
			'submit_button'   => [
				'type'            => 'submit',
				'title'           => __('Delete Record', 'ultimate-multisite'),
				'value'           => 'delete',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end',
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'admin_delete_dns_record',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'delete_dns_record',
					'data-state'  => wu_convert_to_state([]),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the admin delete DNS record modal submission.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function handle_admin_delete_dns_record_modal(): void {

		check_ajax_referer('wu_dns_nonce', 'nonce');

		$domain_id = wu_request('domain_id');
		$record_id = wu_request('record_id');
		$domain    = wu_get_domain($domain_id);

		if ( ! $domain) {
			wp_send_json_error(['message' => __('Domain not found.', 'ultimate-multisite')]);
		}

		$dns_manager  = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
		$dns_provider = $dns_manager->get_dns_provider();

		if ( ! $dns_provider) {
			wp_send_json_error(['message' => __('No DNS provider configured.', 'ultimate-multisite')]);
		}

		$result = $dns_provider->delete_dns_record($domain->get_domain(), $record_id);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success(
			[
				'message' => __('DNS record deleted successfully.', 'ultimate-multisite'),
			]
		);
	}
}
