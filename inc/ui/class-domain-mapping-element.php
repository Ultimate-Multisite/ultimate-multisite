<?php
/**
 * Adds the Domain Mapping Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use WP_Ultimo\Models\Domain;
use WP_Ultimo\Database\Domains\Domain_Stage;
use WP_Ultimo\Models\Site;
use WP_Ultimo\Models\Membership;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Checkout Element UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Domain_Mapping_Element extends Base_Element {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The id of the element.
	 *
	 * Something simple, without prefixes, like 'checkout', or 'pricing-tables'.
	 *
	 * This is used to construct shortcodes by prefixing the id with 'wu_'
	 * e.g. an id checkout becomes the shortcode 'wu_checkout' and
	 * to generate the Gutenberg block by prefixing it with 'wp-ultimo/'
	 * e.g. checkout would become the block 'wp-ultimo/checkout'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $id = 'domain-mapping';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var boolean
	 */
	protected $public = true;

	/**
	 * The current site.
	 *
	 * @since 2.2.0
	 * @var Site
	 */
	protected $site;

	/**
	 * The current membership.
	 *
	 * @since 2.2.0
	 * @var Membership
	 */
	protected $membership;

	/**
	 * The icon of the UI element.
	 * e.g. return fa fa-search
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 */
	public function get_icon($context = 'block'): string {

		if ('elementor' === $context) {
			return 'eicon-url';
		}

		return 'fa fa-search';
	}

	/**
	 * The title of the UI element.
	 *
	 * This is used on the Blocks list of Gutenberg.
	 * You should return a string with the localized title.
	 * e.g. return __('My Element', 'ultimate-multisite').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('Domains', 'ultimate-multisite');
	}

	/**
	 * The description of the UI element.
	 *
	 * This is also used on the Gutenberg block list
	 * to explain what this block is about.
	 * You should return a string with the localized title.
	 * e.g. return __('Adds a checkout form to the page', 'ultimate-multisite').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('Allows customers to manage custom domains mapped to their site.', 'ultimate-multisite');
	}

	/**
	 * The list of fields to be added to Gutenberg.
	 *
	 * If you plan to add Gutenberg controls to this block,
	 * you'll need to return an array of fields, following
	 * our fields interface (@see inc/ui/class-field.php).
	 *
	 * You can create new Gutenberg panels by adding fields
	 * with the type 'header'. See the Checkout Elements for reference.
	 *
	 * @see inc/ui/class-checkout-element.php
	 *
	 * Return an empty array if you don't have controls to add.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function fields() {

		$fields = [];

		$fields['header'] = [
			'title' => __('General', 'ultimate-multisite'),
			'desc'  => __('General', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['title'] = [
			'type'    => 'text',
			'title'   => __('Title', 'ultimate-multisite'),
			'value'   => __('Domains', 'ultimate-multisite'),
			'desc'    => __('Leave blank to hide the title completely.', 'ultimate-multisite'),
			'tooltip' => '',
		];

		return $fields;
	}

	/**
	 * The list of keywords for this element.
	 *
	 * Return an array of strings with keywords describing this
	 * element. Gutenberg uses this to help customers find blocks.
	 *
	 * e.g.:
	 * return array(
	 *  'Ultimate Multisite',
	 *  'Checkout',
	 *  'Form',
	 *  'Cart',
	 * );
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function keywords() {

		return [
			'WP Ultimo',
			'Ultimate Multisite',
			'Domain',
		];
	}

	/**
	 * List of default parameters for the element.
	 *
	 * If you are planning to add controls using the fields,
	 * it might be a good idea to use this method to set defaults
	 * for the parameters you are expecting.
	 *
	 * These defaults will be used inside a 'wp_parse_args' call
	 * before passing the parameters down to the block render
	 * function and the shortcode render function.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function defaults() {

		return [
			'title' => __('Domains', 'ultimate-multisite'),
		];
	}

	/**
	 * Initializes the singleton.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init() {

		parent::init();

		if ($this->is_preview()) {
			$this->site = wu_mock_site();

			return;
		}

		$this->site = wu_get_current_site();

		$maybe_limit_domain_mapping = true;

		if ($this->site->has_limitations()) {
			$maybe_limit_domain_mapping = $this->site->get_limitations()->domain_mapping->is_enabled();
		}

		if ( ! $this->site || ! wu_get_setting('enable_domain_mapping', false) || ! wu_get_setting('custom_domains', false) || ! $maybe_limit_domain_mapping) {
			$this->set_display(false);
		}

		add_action('plugins_loaded', [$this, 'register_forms']);
	}

	/**
	 * Loads the required scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts() {

		add_wubox();
	}

	/**
	 * Register ajax forms used to add a new domain.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {
		/*
		 * Add new Domain
		 */
		wu_register_form(
			'user_add_new_domain',
			[
				'render'     => [$this, 'render_user_add_new_domain_modal'],
				'handler'    => [$this, 'handle_user_add_new_domain_modal'],
				'capability' => 'exist',
			]
		);

		wu_register_form(
			'user_make_domain_primary',
			[
				'render'     => [$this, 'render_user_make_domain_primary_modal'],
				'handler'    => [$this, 'handle_user_make_domain_primary_modal'],
				'capability' => 'exist',
			]
		);

		wu_register_form(
			'user_delete_domain_modal',
			[
				'render'     => [$this, 'render_user_delete_domain_modal'],
				'handler'    => [$this, 'handle_user_delete_domain_modal'],
				'capability' => 'exist',
			]
		);

		/*
		 * DNS Management Forms
		 */
		wu_register_form(
			'user_manage_dns_records',
			[
				'render'     => [$this, 'render_dns_management_modal'],
				'handler'    => false,
				'capability' => 'exist',
			]
		);

		wu_register_form(
			'user_add_dns_record',
			[
				'render'     => [$this, 'render_add_dns_record_modal'],
				'handler'    => [$this, 'handle_add_dns_record'],
				'capability' => 'exist',
			]
		);

		wu_register_form(
			'user_edit_dns_record',
			[
				'render'     => [$this, 'render_edit_dns_record_modal'],
				'handler'    => [$this, 'handle_edit_dns_record'],
				'capability' => 'exist',
			]
		);

		wu_register_form(
			'user_delete_dns_record',
			[
				'render'     => [$this, 'render_delete_dns_record_modal'],
				'handler'    => [$this, 'handle_delete_dns_record'],
				'capability' => 'exist',
			]
		);
	}

	/**
	 * Renders the add new customer modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_user_add_new_domain_modal(): void {

		$instructions = \WP_Ultimo\Managers\Domain_Manager::get_instance()->get_domain_mapping_instructions();

		$fields = [
			'instructions_note' => [
				'type'              => 'note',
				'desc'              => function () {
					printf('<a href="#" class="wu-no-underline" v-on:click.prevent="ready = false">%s</a>', esc_html__('&larr; Back to the Instructions', 'ultimate-multisite'));
				},
				'wrapper_html_attr' => [
					'v-if'    => 'ready',
					'v-cloak' => '1',
				],
			],
			'instructions'      => [
				'type'              => 'text-display',
				'copy'              => false,
				'title'             => __('Instructions', 'ultimate-multisite'),
				'tooltip'           => '',
				'display_value'     => sprintf('<div class="wu--mt-2 wu--mb-2">%s</div>', wpautop($instructions)),
				'wrapper_html_attr' => [
					'v-show'  => '!ready',
					'v-cloak' => 1,
				],
			],
			'ready'             => [
				'type'              => 'submit',
				'title'             => __('Next Step &rarr;', 'ultimate-multisite'),
				'value'             => 'save',
				'classes'           => 'button button-primary wu-w-full',
				'wrapper_classes'   => 'wu-items-end',
				'html_attr'         => [
					'v-on:click.prevent' => 'ready = true',
				],
				'wrapper_html_attr' => [
					'v-show'  => '!ready',
					'v-cloak' => 1,
				],
			],
			'current_site'      => [
				'type'  => 'hidden',
				'value' => wu_request('current_site'),
			],
			'domain'            => [
				'type'              => 'text',
				'title'             => __('Domain', 'ultimate-multisite'),
				'placeholder'       => __('mydomain.com', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-show'  => 'ready',
					'v-cloak' => 1,
				],
			],
			'primary_domain'    => [
				'type'              => 'toggle',
				'title'             => __('Primary Domain', 'ultimate-multisite'),
				'desc'              => __('Check to set this domain as the primary', 'ultimate-multisite'),
				'html_attr'         => [
					'v-model' => 'primary_domain',
				],
				'wrapper_html_attr' => [
					'v-show'  => 'ready',
					'v-cloak' => 1,
				],
			],
			'primary_note'      => [
				'type'              => 'note',
				'desc'              => __('By making this the primary domain, we will convert the previous primary domain for this site, if one exists, into an alias domain.', 'ultimate-multisite'),
				'wrapper_html_attr' => [
					'v-if'    => "require('primary_domain', true) && ready",
					'v-cloak' => 1,
				],
			],
			'submit_button_new' => [
				'type'              => 'submit',
				'title'             => __('Add Domain', 'ultimate-multisite'),
				'value'             => 'save',
				'classes'           => 'button button-primary wu-w-full',
				'wrapper_classes'   => 'wu-items-end',
				'wrapper_html_attr' => [
					'v-show'  => 'ready',
					'v-cloak' => 1,
				],
			],
		];

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
							'ready'          => 0,
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
	public function handle_user_add_new_domain_modal(): void {

		$current_user_id = get_current_user_id();

		$current_site_id = wu_request('current_site');

		$current_site = wu_get_site($current_site_id);

		if ( ! is_super_admin() && (! $current_site || $current_site->get_customer()->get_user_id() !== $current_user_id)) {
			wp_send_json_error(
				new \WP_Error('no-permissions', __('You do not have permissions to perform this action.', 'ultimate-multisite'))
			);

			exit;
		}

		/*
		* Tries to create the domain
		*/
		$domain = wu_create_domain(
			[
				'domain'         => wu_request('domain'),
				'blog_id'        => absint($current_site_id),
				'primary_domain' => (bool) wu_request('primary_domain'),
			]
		);

		if (is_wp_error($domain)) {
			wp_send_json_error($domain);
		}

		wu_enqueue_async_action('wu_async_process_domain_stage', ['domain_id' => $domain->get_id()], 'domain');

		/**
		 * Triggers when a new domain mapping is added.
		 */
		do_action('wu_domain_created', $domain, $domain->get_site(), $domain->get_site()->get_membership());

		wp_send_json_success(
			[
				'redirect_url' => wu_get_current_url(),
			]
		);

		exit;
	}

	/**
	 * Renders the domain delete action.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_user_delete_domain_modal(): void {

		$fields = [
			'confirm'       => [
				'type'      => 'toggle',
				'title'     => __('Confirm Deletion', 'ultimate-multisite'),
				'desc'      => __('This action can not be undone.', 'ultimate-multisite'),
				'html_attr' => [
					'v-model' => 'confirmed',
				],
			],
			'domain_id'     => [
				'type'  => 'hidden',
				'value' => wu_request('domain_id'),
			],
			'submit_button' => [
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
		];

		$form = new \WP_Ultimo\UI\Form(
			'user_delete_domain_modal',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'user_delete_domain_modal',
					'data-state'  => wp_json_encode(
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
	 * Handles deletion of the selected domain
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_user_delete_domain_modal(): void {

		if ( ! wu_request('confirm')) {
			wp_send_json_error(new \WP_Error('not-confirmed', __('Please confirm the deletion.', 'ultimate-multisite')));
		}

		if (wu_request('user_id')) {
			$customer = wu_get_customer_by_user_id(wu_request('user_id'));
		}

		$current_site = wu_request('current_site');

		$get_domain = Domain::get_by_id(wu_request('domain_id'));

		$domain = new Domain($get_domain);

		if ($domain) {
			$domain->delete();
		}

		wp_send_json_success(
			[
				'redirect_url' => wu_get_current_url(),
			]
		);
	}

	/**
	 * Renders the domain delete action.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_user_make_domain_primary_modal(): void {

		$fields = [
			'confirm'       => [
				'type'      => 'toggle',
				'title'     => __('Confirm Action', 'ultimate-multisite'),
				'desc'      => __('This action will also convert the previous primary domain (if any) to an alias to prevent unexpected behavior.', 'ultimate-multisite'),
				'html_attr' => [
					'v-model' => 'confirmed',
				],
			],
			'domain_id'     => [
				'type'  => 'hidden',
				'value' => wu_request('domain_id'),
			],
			'submit_button' => [
				'type'            => 'submit',
				'title'           => __('Make it Primary', 'ultimate-multisite'),
				'placeholder'     => __('Make it Primary', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end',
				'html_attr'       => [
					'v-bind:disabled' => '!confirmed',
				],
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'user_delete_domain_modal',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'user_delete_domain_modal',
					'data-state'  => wp_json_encode(
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
	 * Handles conversion to primary domain.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_user_make_domain_primary_modal(): void {

		if ( ! wu_request('confirm')) {
			wp_send_json_error(new \WP_Error('not-confirmed', __('Please confirm the action.', 'ultimate-multisite')));
		}

		$current_site = wu_request('current_site');

		$domain_id = wu_request('domain_id');

		$domain = wu_get_domain($domain_id);

		if ($domain) {
			$domain->set_primary_domain(true);

			$status = $domain->save();

			if (is_wp_error($status)) {
				wp_send_json_error($status);
			}

			$old_primary_domains = wu_get_domains(
				[
					'primary_domain' => true,
					'blog_id'        => $domain->get_blog_id(),
					'id__not_in'     => [$domain->get_id()],
					'fields'         => 'ids',
				]
			);

			// Updating the old primaries now happens in the save method.

			wp_send_json_success(
				[
					/**
					 * Filters the redirect URL after making a domain primary.
					 *
					 * Allows developers to customize where users are redirected after successfully
					 * setting a domain as primary. By default, redirects to the current URL on the
					 * main site, or to the admin URL of the site being modified.
					 *
					 * @since 2.0.0
					 *
					 * @param string $redirect_url        The default redirect URL. Either the current URL (if main site)
					 *                                    or the admin URL of the current site.
					 * @param int    $current_site        The ID of the site whose domain is being made primary.
					 * @param Domain $domain              The domain object that was made primary.
					 * @param array  $old_primary_domains Array of IDs of domains that were previously primary.
					 *
					 * @return string The filtered redirect URL.
					 */
					'redirect_url' => apply_filters('wu_make_primary_domain_redirect_url', is_main_site() ? wu_get_current_url() : get_admin_url($current_site), $current_site, $domain, $old_primary_domains),
				]
			);
		}

		wp_send_json_error(new \WP_Error('error', __('Something wrong happenned.', 'ultimate-multisite')));
	}

	/**
	 * Renders the DNS management modal.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function render_dns_management_modal(): void {

		$domain_id = wu_request('domain_id');
		$domain    = wu_get_domain($domain_id);

		if (! $domain) {
			wp_send_json_error(new \WP_Error('invalid-domain', __('Invalid domain.', 'ultimate-multisite')));
			return;
		}

		$dns_manager = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
		$provider    = $dns_manager->get_dns_provider();

		wu_get_template(
			'domain/dns-management-modal',
			[
				'domain'        => $domain,
				'domain_id'     => $domain_id,
				'site_id'       => $domain->get_blog_id(),
				'can_manage'    => $dns_manager->customer_can_manage_dns(get_current_user_id(), $domain->get_domain()),
				'has_provider'  => null !== $provider,
				'provider_name' => $provider ? $provider->get_title() : '',
			]
		);
	}

	/**
	 * Renders the add DNS record modal.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function render_add_dns_record_modal(): void {

		$domain_id = wu_request('domain_id');
		$domain    = wu_get_domain($domain_id);

		if (! $domain) {
			wp_send_json_error(new \WP_Error('invalid-domain', __('Invalid domain.', 'ultimate-multisite')));
			return;
		}

		$dns_manager = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
		$provider    = $dns_manager->get_dns_provider();

		wu_get_template(
			'domain/dns-record-form',
			[
				'domain_id'     => $domain_id,
				'domain_name'   => $domain->get_domain(),
				'mode'          => 'add',
				'record'        => [],
				'allowed_types' => $dns_manager->get_allowed_record_types(get_current_user_id()),
				'show_proxied'  => $provider && $provider->get_id() === 'cloudflare',
			]
		);
	}

	/**
	 * Handles adding a DNS record.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function handle_add_dns_record(): void {

		$domain_id = wu_request('domain_id');
		$domain    = wu_get_domain($domain_id);
		$record    = wu_request('record', []);

		if (! $domain) {
			wp_send_json_error(new \WP_Error('invalid-domain', __('Invalid domain.', 'ultimate-multisite')));
			return;
		}

		$dns_manager = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();

		if (! $dns_manager->customer_can_manage_dns(get_current_user_id(), $domain->get_domain())) {
			wp_send_json_error(new \WP_Error('permission-denied', __('You do not have permission to manage DNS for this domain.', 'ultimate-multisite')));
			return;
		}

		$provider = $dns_manager->get_dns_provider();

		if (! $provider) {
			wp_send_json_error(new \WP_Error('no-provider', __('No DNS provider configured.', 'ultimate-multisite')));
			return;
		}

		// Sanitize record data before passing to provider (mirrors DNS_Record_Manager::sanitize_record_data()).
		$record = [
			'type'     => strtoupper(sanitize_text_field($record['type'] ?? 'A')),
			'name'     => sanitize_text_field($record['name'] ?? ''),
			'content'  => sanitize_text_field($record['content'] ?? ''),
			'ttl'      => absint($record['ttl'] ?? 3600),
			'priority' => isset($record['priority']) ? absint($record['priority']) : null,
			'proxied'  => ! empty($record['proxied']),
		];

		// Enforce allowed record types server-side.
		$allowed_types = $dns_manager->get_allowed_record_types(get_current_user_id());
		if (! in_array($record['type'], $allowed_types, true)) {
			wp_send_json_error(new \WP_Error('type-not-allowed', __('You are not allowed to create this type of DNS record.', 'ultimate-multisite')));
			return;
		}

		$result = $provider->create_dns_record($domain->get_domain(), $record);

		if (is_wp_error($result)) {
			wp_send_json_error($result);
			return;
		}

		wp_send_json_success(
			[
				'redirect_url' => wu_get_form_url('user_manage_dns_records', ['domain_id' => $domain_id]),
			]
		);
	}

	/**
	 * Renders the edit DNS record modal.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function render_edit_dns_record_modal(): void {

		$domain_id = wu_request('domain_id');
		$record_id = wu_request('record_id');
		$domain    = wu_get_domain($domain_id);

		if (! $domain) {
			wp_send_json_error(new \WP_Error('invalid-domain', __('Invalid domain.', 'ultimate-multisite')));
			return;
		}

		$dns_manager = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();

		if (! $dns_manager->customer_can_manage_dns(get_current_user_id(), $domain->get_domain())) {
			wp_send_json_error(new \WP_Error('permission-denied', __('You do not have permission to manage DNS for this domain.', 'ultimate-multisite')));
			return;
		}

		$provider = $dns_manager->get_dns_provider();

		if (! $provider) {
			wp_send_json_error(new \WP_Error('no-provider', __('No DNS provider configured.', 'ultimate-multisite')));
			return;
		}

		// Get current record data
		$records = $provider->get_dns_records($domain->get_domain());
		$record  = [];

		if (! is_wp_error($records)) {
			foreach ($records as $r) {
				$record_data = $r instanceof \WP_Ultimo\Integrations\Host_Providers\DNS_Record ? $r->to_array() : $r;
				if (($record_data['id'] ?? '') === $record_id) {
					$record = $record_data;
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
				'allowed_types' => $dns_manager->get_allowed_record_types(get_current_user_id()),
				'show_proxied'  => $provider->get_id() === 'cloudflare',
			]
		);
	}

	/**
	 * Handles editing a DNS record.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function handle_edit_dns_record(): void {

		$domain_id = wu_request('domain_id');
		$record_id = wu_request('record_id');
		$domain    = wu_get_domain($domain_id);
		$record    = wu_request('record', []);

		if (! $domain) {
			wp_send_json_error(new \WP_Error('invalid-domain', __('Invalid domain.', 'ultimate-multisite')));
			return;
		}

		$dns_manager = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();

		if (! $dns_manager->customer_can_manage_dns(get_current_user_id(), $domain->get_domain())) {
			wp_send_json_error(new \WP_Error('permission-denied', __('You do not have permission to manage DNS for this domain.', 'ultimate-multisite')));
			return;
		}

		$provider = $dns_manager->get_dns_provider();

		if (! $provider) {
			wp_send_json_error(new \WP_Error('no-provider', __('No DNS provider configured.', 'ultimate-multisite')));
			return;
		}

		// Sanitize record data before passing to provider.
		$record = [
			'type'     => strtoupper(sanitize_text_field($record['type'] ?? 'A')),
			'name'     => sanitize_text_field($record['name'] ?? ''),
			'content'  => sanitize_text_field($record['content'] ?? ''),
			'ttl'      => absint($record['ttl'] ?? 3600),
			'priority' => isset($record['priority']) ? absint($record['priority']) : null,
			'proxied'  => ! empty($record['proxied']),
		];

		// Enforce allowed record types server-side.
		$allowed_types = $dns_manager->get_allowed_record_types(get_current_user_id());
		if (! in_array($record['type'], $allowed_types, true)) {
			wp_send_json_error(new \WP_Error('type-not-allowed', __('You are not allowed to modify this type of DNS record.', 'ultimate-multisite')));
			return;
		}

		$result = $provider->update_dns_record($domain->get_domain(), $record_id, $record);

		if (is_wp_error($result)) {
			wp_send_json_error($result);
			return;
		}

		wp_send_json_success(
			[
				'redirect_url' => wu_get_form_url('user_manage_dns_records', ['domain_id' => $domain_id]),
			]
		);
	}

	/**
	 * Renders the delete DNS record confirmation modal.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function render_delete_dns_record_modal(): void {

		$domain_id = wu_request('domain_id');
		$record_id = wu_request('record_id');
		$domain    = wu_get_domain($domain_id);

		if (! $domain) {
			wp_send_json_error(new \WP_Error('invalid-domain', __('Invalid domain.', 'ultimate-multisite')));
			return;
		}

		$fields = [
			'confirm'       => [
				'type'      => 'toggle',
				'title'     => __('Confirm Deletion', 'ultimate-multisite'),
				'desc'      => __('I understand this action cannot be undone.', 'ultimate-multisite'),
				'html_attr' => [
					'v-model' => 'confirmed',
				],
			],
			'domain_id'     => [
				'type'  => 'hidden',
				'value' => $domain_id,
			],
			'record_id'     => [
				'type'  => 'hidden',
				'value' => $record_id,
			],
			'submit_button' => [
				'type'            => 'submit',
				'title'           => __('Delete Record', 'ultimate-multisite'),
				'placeholder'     => __('Delete Record', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end',
				'html_attr'       => [
					'v-bind:disabled' => '!confirmed',
				],
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'delete_dns_record',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'delete_dns_record',
					'data-state'  => wp_json_encode(['confirmed' => false]),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles deleting a DNS record.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function handle_delete_dns_record(): void {

		$domain_id = wu_request('domain_id');
		$record_id = wu_request('record_id');
		$domain    = wu_get_domain($domain_id);

		if (! $domain) {
			wp_send_json_error(new \WP_Error('invalid-domain', __('Invalid domain.', 'ultimate-multisite')));
			return;
		}

		$dns_manager = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();

		if (! $dns_manager->customer_can_manage_dns(get_current_user_id(), $domain->get_domain())) {
			wp_send_json_error(new \WP_Error('permission-denied', __('You do not have permission to manage DNS for this domain.', 'ultimate-multisite')));
			return;
		}

		$provider = $dns_manager->get_dns_provider();

		if (! $provider) {
			wp_send_json_error(new \WP_Error('no-provider', __('No DNS provider configured.', 'ultimate-multisite')));
			return;
		}

		$result = $provider->delete_dns_record($domain->get_domain(), $record_id);

		if (is_wp_error($result)) {
			wp_send_json_error($result);
			return;
		}

		wp_send_json_success(
			[
				'redirect_url' => wu_get_form_url('user_manage_dns_records', ['domain_id' => $domain_id]),
			]
		);
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup() {

		$this->site = WP_Ultimo()->currents->get_site();

		if ( ! $this->site || ! $this->site->is_customer_allowed()) {
			$this->set_display(false);

			return;
		}

		// Ensure admin.php is loaded as we need wu_responsive_table_row function
		require_once wu_path('inc/functions/admin.php');

		$this->membership = $this->site->get_membership();
	}

	/**
	 * Allows the setup in the context of previews.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_preview() {

		$this->site = wu_mock_site();

		$this->membership = wu_mock_membership();
	}

	/**
	 * The content to be output on the screen.
	 *
	 * Should return HTML markup to be used to display the block.
	 * This method is shared between the block render method and
	 * the shortcode implementation.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output($atts, $content = null) {

		$current_site = $this->site;

		$all_domains = wu_get_domains(
			[
				'blog_id' => $current_site->get_id(),
				'orderby' => 'primary_domain',
				'order'   => 'DESC',
			]
		);

		$domains = [];

		foreach ($all_domains as $key => $domain) {
			$stage = new Domain_Stage($domain->get_stage());

			$secure = 'dashicons-wu-lock-open';

			$secure_message = __('Domain not secured with HTTPS', 'ultimate-multisite');

			if ($domain->is_secure()) {
				$secure = 'dashicons-wu-lock wu-text-green-500';

				$secure_message = __('Domain secured with HTTPS', 'ultimate-multisite');
			}

			$url_atts = [
				'current_site' => $current_site->get_id(),
				'domain_id'    => $domain->get_id(),
			];

			$delete_url  = wu_get_form_url('user_delete_domain_modal', $url_atts);
			$primary_url = wu_get_form_url('user_make_domain_primary', $url_atts);

			$domains[ $key ] = [
				'id'             => $domain->get_id(),
				'domain_object'  => $domain,
				'domain'         => $domain->get_domain(),
				'stage'          => $stage->get_label(),
				'primary'        => $domain->is_primary_domain(),
				'stage_class'    => $stage->get_classes(),
				'secure_class'   => $secure,
				'secure_message' => $secure_message,
				'delete_link'    => $delete_url,
				'primary_link'   => $primary_url,
			];
		}

		$url_atts = [
			'current_site' => $current_site->get_ID(),
		];

		$other_atts = [
			'domains' => $domains,
			'modal'   => [
				'label'   => __('Add Domain', 'ultimate-multisite'),
				'icon'    => 'wu-circle-with-plus',
				'classes' => 'wubox',
				'url'     => wu_get_form_url('user_add_new_domain', $url_atts),
			],
		];

		$atts = array_merge($other_atts, $atts);

		wu_get_template('dashboard-widgets/domain-mapping', $atts);
	}
}
