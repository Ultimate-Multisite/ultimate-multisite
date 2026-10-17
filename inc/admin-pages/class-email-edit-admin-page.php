<?php
/**
 * Ultimate Multisite Email Edit/Add New Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Email;
use WP_Ultimo\Managers\Email_Manager;

/**
 * Ultimate Multisite Email Edit/Add New Admin Page.
 */
class Email_Edit_Admin_Page extends Edit_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-edit-email';

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
	public $object_id = 'system_email';


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
	protected $highlight_menu_slug = 'wp-ultimo-broadcasts';

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
		'network_admin_menu' => 'wu_edit_emails',
	];

	/**
	 * Initializes the class
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function init(): void {

		/**
		 * Runs the parent init functions
		 */
		parent::init();

		add_action('wu_page_edit_redirect_handlers', [$this, 'handle_page_redirect'], 10);
	}

	/**
	 * Registers the necessary scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		parent::register_scripts();

		wp_enqueue_script('wu-email-edit-page', wu_get_asset('email-edit-page.js', 'js'), ['jquery', 'clipboard'], \WP_Ultimo::VERSION, true);
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		parent::register_widgets();

		$object = $this->get_object();

		// translators: %s is replaced with the number of days.
		$days_text = sprintf(__('Send %s day(s) after the event.', 'ultimate-multisite'), '{{ days }}');

		// translators: %1$s is replaced with the number of hours, %2$s is replaced with the number of minutes.
		$hour_text = sprintf(__('Send %1$s hour(s) and %2$s minute(s) after the event.', 'ultimate-multisite'), '{{ hours.split(":").shift() }}', '{{ hours.split(":").pop() }}');

		$desc = sprintf(
			'<span v-show="schedule && schedule_type == \'days\'">%s</span>
			<span v-show="schedule && schedule_type == \'hours\'">%s</span>',
			$days_text,
			$hour_text
		);

		$this->add_save_widget(
			'save',
			[
				'html_attr' => [
					'data-wu-app' => 'email_edit_save',
					'data-state'  => wu_convert_to_state(
						[
							'slug'           => $this->edit ? $object->get_slug() : '',
							'target'         => $this->edit ? $object->get_target() : 'admin',
							'schedule'       => $this->edit ? $object->has_schedule() : false,
							'schedule_type'  => $this->edit ? $object->get_schedule_type() : 'days',
							'days'           => $this->edit ? $object->get_send_days() : 1,
							'hours'          => $this->edit ? $object->get_send_hours() : '12:00',
							'product_filter' => $this->edit ? $object->get_product_filter() : 'all',
						]
					),
				],
				'fields'    => [
					'slug'               => [
						'type'      => 'text',
						'title'     => __('Slug', 'ultimate-multisite'),
						'desc'      => __('An unique identifier for this system email.', 'ultimate-multisite'),
						'value'     => $this->edit ? $object->get_slug() : '',
						'html_attr' => [
							'required'     => 'required',
							'v-on:input'   => 'slug = $event.target.value.toLowerCase().replace(/[^a-z0-9-_]+/g, "")',
							'v-bind:value' => 'slug',
						],
					],
					'event'              => [
						'type'        => 'select',
						'title'       => __('Event', 'ultimate-multisite'),
						'desc'        => __('The event that will trigger the sending of this email.', 'ultimate-multisite'),
						'placeholder' => __('Event', 'ultimate-multisite'),
						'options'     => 'wu_get_event_types_as_options',
						'value'       => $this->edit ? $object->get_event() : 0,
						'html_attr'   => [
							'name' => '',
						],
					],
					'target'             => [
						'type'        => 'select',
						'title'       => __('Target', 'ultimate-multisite'),
						'desc'        => __('To whom this email should be sent.', 'ultimate-multisite'),
						'placeholder' => __('Network Administrators', 'ultimate-multisite'),
						'value'       => $this->edit ? $object->get_target() : 'admin',
						'options'     => [
							'admin'    => __('Network Administrators', 'ultimate-multisite'),
							'customer' => __('Customer', 'ultimate-multisite'),
						],
						'html_attr'   => [
							'v-model' => 'target',
						],
					],
					'send_copy_to_admin' => [
						'type'              => 'toggle',
						'title'             => __('Send Copy to Admins?', 'ultimate-multisite'),
						'desc'              => __('Checking this options will add the network admins as bcc every time this email is sent to a customer.', 'ultimate-multisite'),
						'value'             => $this->edit ? $object->get_send_copy_to_admin() : false,
						'wrapper_html_attr' => [
							'v-show'  => 'target == "customer"',
							'v-cloak' => 1,
						],
					],
					'schedule'           => [
						'type'      => 'toggle',
						'title'     => __('Schedule?', 'ultimate-multisite'),
						'desc'      => __('You can define when the email is sent after the event triggers.', 'ultimate-multisite'),
						'value'     => $this->edit ? $this->get_object()->has_schedule() : 0,
						'html_attr' => [
							'v-model' => 'schedule',
						],
					],
					'send_date'          => [
						'type'              => 'group',
						'title'             => __('Scheduling Options', 'ultimate-multisite'),
						'tooltip'           => __('When this email will be sent after the event?', 'ultimate-multisite'),
						'desc'              => $desc,
						'desc_id'           => 'send_date_desc',
						'wrapper_html_attr' => [
							'v-show'  => 'schedule',
							'v-cloak' => 1,
						],
						'fields'            => [
							'schedule_type' => [
								'type'            => 'select',
								'default'         => 'days',
								'wrapper_classes' => 'wu-w-2/3',
								'value'           => $this->edit ? $object->get_schedule_type() : 'days',
								'options'         => [
									'hours' => __('Delay for hours', 'ultimate-multisite'),
									'days'  => __('Delay for days', 'ultimate-multisite'),
								],
								'html_attr'       => [
									'v-model' => 'schedule_type',
								],
							],
							'send_days'     => [
								'type'              => 'number',
								'value'             => $this->edit && $object->get_send_days() ? $object->get_send_days() : 1,
								'placeholder'       => 1,
								'min'               => 0,
								'wrapper_classes'   => 'wu-ml-2 wu-w-1/3',
								'wrapper_html_attr' => [
									'v-show'  => "schedule_type == 'days'",
									'v-cloak' => '1',
								],
								'html_attr'         => [
									'v-model' => 'days',
								],
							],
							'send_hours'    => [
								'type'              => 'text',
								'date'              => true,
								'placeholder'       => $this->edit ? $object->get_send_hours() : '12:00',
								'value'             => $this->edit ? $object->get_send_hours() : '',
								'wrapper_classes'   => 'wu-ml-2 wu-w-1/3',
								'html_attr'         => [
									'data-no-calendar' => 'true',
									'wu-datepicker'    => 'true',
									'data-format'      => 'H:i',
									'data-allow-time'  => 'true',
									'v-model'          => 'hours',
								],
								'wrapper_html_attr' => [
									'v-show'  => "schedule_type == 'hours'",
									'v-cloak' => '1',
								],
							],
						],
					],
					'product_filter'     => [
						'type'      => 'select',
						'title'     => __('Products', 'ultimate-multisite'),
						'desc'      => __('Limit this email to customers with certain products, for example to send a welcome series only to free trials. Delayed emails are checked again when they are due, so customers who changed plans in the meantime are skipped.', 'ultimate-multisite'),
						'value'     => $this->edit ? $object->get_product_filter() : 'all',
						'options'   => [
							'all'     => __('Send for all products', 'ultimate-multisite'),
							'include' => __('Only for the selected products', 'ultimate-multisite'),
							'exclude' => __('For all products except the selected ones', 'ultimate-multisite'),
						],
						'html_attr' => [
							'v-model' => 'product_filter',
						],
					],
					'products'           => [
						'type'              => 'model',
						'title'             => __('Selected Products', 'ultimate-multisite'),
						'placeholder'       => __('Search Products...', 'ultimate-multisite'),
						'value'             => implode(',', $this->edit ? $object->get_products() : []),
						'html_attr'         => [
							'data-model'        => 'product',
							'data-value-field'  => 'id',
							'data-label-field'  => 'name',
							'data-search-field' => 'name',
							'data-max-items'    => 99,
							'data-selected'     => wp_json_encode($this->get_selected_products()),
						],
						'wrapper_html_attr' => [
							'v-show'  => "product_filter != 'all'",
							'v-cloak' => 1,
						],
					],
				],
			]
		);

		add_meta_box('wp-ultimo-placeholders', __('Placeholders', 'ultimate-multisite'), [$this, 'output_default_widget_placeholders'], get_current_screen()->id, 'normal', null, []);

		$this->add_fields_widget(
			'active',
			[
				'title'  => __('Active', 'ultimate-multisite'),
				'fields' => [
					'active' => [
						'type'  => 'toggle',
						'title' => __('Active', 'ultimate-multisite'),
						'desc'  => __('Use this option to manually enable or disable this email.', 'ultimate-multisite'),
						'value' => $this->get_object()->is_active(),
					],
				],
			]
		);

		$this->add_tabs_widget(
			'email_edit_options',
			[
				'title'    => __('Advanced Options', 'ultimate-multisite'),
				'position' => 'normal',
				'sections' => [
					'general' => [
						'title'  => __('General', 'ultimate-multisite'),
						'icon'   => 'dashicons-wu-lock',
						'desc'   => __('Rules and limitations to the applicability of this discount code.', 'ultimate-multisite'),
						'state'  => [
							'sender' => $this->edit ? $object->get_custom_sender() : 0,
						],
						'fields' => [
							'style' => [
								'type'        => 'select',
								'title'       => __('Email Style', 'ultimate-multisite'),
								'desc'        => __('Choose if email body will be sent using the HTML template or in plain text.', 'ultimate-multisite'),
								'placeholder' => __('Style', 'ultimate-multisite'),
								'options'     => [
									'default' => __('Use Default', 'ultimate-multisite'),
									'html'    => __('HTML Emails', 'ultimate-multisite'),
									'plain'   => __('Plain Emails', 'ultimate-multisite'),
								],
								'value'       => $this->edit ? $object->get_style() : 'html',
							],
						],
					],
					'sender'  => [
						'title'  => __('Custom Sender', 'ultimate-multisite'),
						'icon'   => 'dashicons-wu-mail',
						'desc'   => __('You can define an email and a name that will only be used when this email is sent.', 'ultimate-multisite'),
						'fields' => [
							'custom_sender'       => [
								'type'      => 'toggle',
								'title'     => __('Use a custom sender?', 'ultimate-multisite'),
								'desc'      => __('You can define an email and a name that will only be used when this email is sent.', 'ultimate-multisite'),
								'value'     => $this->edit ? $object->get_custom_sender() : 0,
								'html_attr' => [
									'v-model' => 'sender',
								],
							],
							'custom_sender_name'  => [
								'type'              => 'text',
								'title'             => __('From "Name"', 'ultimate-multisite'),
								'desc'              => __('Override the global from name for this particular email.', 'ultimate-multisite'),
								'wrapper_classes'   => 'wu-full',
								'value'             => $this->edit ? $object->get_custom_sender_name() : '',
								'wrapper_html_attr' => [
									'v-show'  => 'sender',
									'v-cloak' => 1,
								],
							],
							'custom_sender_email' => [
								'type'              => 'email',
								'title'             => __('From "Email"', 'ultimate-multisite'),
								'desc'              => __('Override the global from email for this particular email.', 'ultimate-multisite'),
								'wrapper_classes'   => 'wu-full',
								'value'             => $this->edit ? $object->get_custom_sender_email() : '',
								'wrapper_html_attr' => [
									'v-show'  => 'sender',
									'v-cloak' => 1,
								],
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Returns the saved filter products in the format the product selector expects.
	 *
	 * @since 2.18.0
	 * @return array
	 */
	protected function get_selected_products(): array {

		if ( ! $this->edit) {
			return [];
		}

		$selected = [];

		foreach ($this->get_object()->get_products() as $product_id) {
			$product = wu_get_product($product_id);

			if ($product) {
				$selected[] = $product->to_search_results();
			}
		}

		return $selected;
	}

	/**
	 * Outputs the block that shows the event payload placeholders.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $unused Not sure.
	 * @param array $data Arguments passed by add_meta_box.
	 * @return void
	 */
	public function output_default_widget_placeholders($unused, $data): void {

		wu_get_template(
			'email/widget-placeholders',
			[
				'title'        => __('Event Payload', 'ultimate-multisite'),
				'loading_text' => __('Loading Payload', 'ultimate-multisite'),
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

		return $this->edit ? __('Edit Email', 'ultimate-multisite') : __('Add new Email', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Edit Email', 'ultimate-multisite');
	}

	/**
	 * Returns the action links for that page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function action_links() {

		$url_atts = [
			'id'    => $this->get_object()->get_id(),
			'model' => 'email',
			'page'  => 'edit',
		];

		$send_test_link = wu_get_form_url('send_new_test', $url_atts);

		return [
			[
				'url'   => wu_network_admin_url('wp-ultimo-emails'),
				'label' => __('Go Back', 'ultimate-multisite'),
				'icon'  => 'wu-reply',
			],
			[
				'url'     => $send_test_link,
				'label'   => __('Send Test Email', 'ultimate-multisite'),
				'icon'    => 'wu-mail',
				'classes' => 'wubox',
			],
		];
	}

	/**
	 * Returns the labels to be used on the admin page.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_labels() {

		return [
			'edit_label'          => __('Edit Email', 'ultimate-multisite'),
			'add_new_label'       => __('Add new Email', 'ultimate-multisite'),
			'updated_message'     => __('Email updated with success!', 'ultimate-multisite'),
			'title_placeholder'   => __('Enter Email Subject', 'ultimate-multisite'),
			'title_description'   => __('This will be used as the email subject line.', 'ultimate-multisite'),
			'save_button_label'   => __('Save Email', 'ultimate-multisite'),
			'save_description'    => '',
			'delete_button_label' => __('Delete Email', 'ultimate-multisite'),
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
			'object_type' => 'system_email',
			'object_id'   => absint($this->get_object()->get_id()),
		];

		return array_merge($args, $extra_args);
	}

	/**
	 * Handles the toggles.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function handle_save(): bool {

		$_POST['schedule'] = wu_request('schedule');

		$_POST['send_copy_to_admin'] = wu_request('send_copy_to_admin');

		$_POST['custom_sender'] = wu_request('custom_sender');

		$_POST['products'] = wu_request('products', '');

		return parent::handle_save();
	}

	/**
	 * Handles the redirect notice from sent new test modal.
	 *
	 * @param Base_Admin_Page $page The page object.
	 * @return void
	 */
	public function handle_page_redirect($page): void {

		if ('wp-ultimo-edit-email' === $page->get_id()) {
			if (wu_request('test_notice')) {
				$test_notice = wu_request('test_notice');

				?>

				<div id="message" class="updated notice notice-success is-dismissible below-h2">

					<p><?php echo esc_html($test_notice); ?></p>

				</div>

				<?php
			}
		}
	}

	/**
	 * Returns the object being edit at the moment.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Email
	 */
	public function get_object() {

		if (isset($_GET['id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query = new \WP_Ultimo\Database\Emails\Email_Query();

			$item = $query->get_item_by('id', (int) $_GET['id']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if (! $item) {
				wp_safe_redirect(wu_network_admin_url('wp-ultimo-emails'));

				exit;
			}

			return $item;
		}

		return new Email();
	}
	/**
	 * Emails have titles.
	 *
	 * @since 2.0.0
	 */
	public function has_title(): bool {

		return true;
	}
	/**
	 * Wether or not this pages should have an editor field.
	 *
	 * @since 2.0.0
	 */
	public function has_editor(): bool {

		return true;
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
			'object_type' => 'email',
			'object_id'   => absint($this->get_object()->get_id()),
		];

		return array_merge($args, $extra_args);
	}
}
