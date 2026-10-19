<?php
/**
 * Ultimate Multisite Broadcast Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Broadcast Admin Page.
 */
class Email_List_Admin_Page extends List_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-emails';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

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
		'network_admin_menu' => 'wu_read_emails',
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

		add_action('wu_page_list_redirect_handlers', [$this, 'handle_page_redirect'], 10);
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets() {}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('System Emails', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('System Emails', 'ultimate-multisite');
	}

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return __('System Emails', 'ultimate-multisite');
	}

	/**
	 * Register ajax form that we use for system emails.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {
		/*
		 * Send a email test
		 */
		wu_register_form(
			'send_new_test',
			[
				'render'     => [$this, 'render_send_new_test_modal'],
				'handler'    => [$this, 'handle_send_new_test_modal'],
				'capability' => 'wu_add_broadcast',
			]
		);

		/*
		 * Reset or Import modal.
		 */
		wu_register_form(
			'reset_import',
			[
				'render'     => [$this, 'render_reset_import_modal'],
				'handler'    => [$this, 'handle_reset_import_modal'],
				'capability' => 'wu_add_broadcasts',
			]
		);

		/*
		 * Reset Confirmation modal.
		 */
		wu_register_form(
			'reset_confirmation',
			[
				'render'     => [$this, 'render_reset_confirmation_modal'],
				'handler'    => [$this, 'handle_reset_confirmation_modal'],
				'capability' => 'wu_add_broadcasts',
			]
		);
	}

	/**
	 * Renders the modal to send tests with system emails.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_send_new_test_modal(): void {

		$fields = [
			'send_to'       => [
				'type'        => 'email',
				'title'       => __('Send To', 'ultimate-multisite'),
				'placeholder' => __('E.g. network@email.com', 'ultimate-multisite'),
				'desc'        => __('The test email will be sent to the above email address.', 'ultimate-multisite'),
				'value'       => get_network_option(null, 'admin_email'),
				'html_attr'   => [
					'required' => 'required',
				],
			],
			'email_id'      => [
				'type'  => 'hidden',
				'value' => wu_request('id'),
			],
			'page'          => [
				'type'  => 'hidden',
				'value' => wu_request('page'),
			],
			'submit_button' => [
				'type'            => 'submit',
				'title'           => __('Send Test Email', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end wu-text-right',
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'send_new_test',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'send_new_test',
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the modal to send tests with system emails.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_send_new_test_modal() {

		$email_id = wu_request('email_id');

		$send_to = wu_request('send_to');

		if ( ! $email_id || ! $send_to) {
			$error = new \WP_Error('error', __('Something wrong happened.', 'ultimate-multisite'));

			wp_send_json_error($error);
		}

		$from = [
			'name'  => wu_get_setting('from_name', ''),
			'email' => wu_get_setting('from_email', ''),
		];

		$to = [
			[
				'name'  => wu_get_setting('from_name', ''),
				'email' => $send_to,
			],
		];

		$email = wu_get_email($email_id);

		$event_slug = $email->get_event();

		$event_type = wu_get_event_type($event_slug);

		$payload = [];

		if ($event_type) {
			$payload = wu_maybe_lazy_load_payload($event_type['payload']);
		}

		$args = [
			'style'   => $email->get_style(),
			'content' => $email->get_content(),
			'subject' => \WP_Ultimo\Managers\Email_Manager::get_instance()->get_system_email_subject($email, $payload),
			'payload' => $payload,
		];

		/*
		 * Add the invoice attachment for test emails, matching the real email behavior.
		 */
		if (isset($payload['payment_id']) && wu_get_setting('attach_invoice_pdf', true)) {
			$invoice_payment = wu_get_payment($payload['payment_id']);

			if ( ! $invoice_payment) {
				$invoice_payment = wu_mock_payment();
			}

			$file_name = 'invoice-' . $invoice_payment->get_hash() . '.pdf';

			\WP_Ultimo\Managers\Email_Manager::get_instance()->attach_invoice_pdf($invoice_payment, $file_name, $args['subject']);
		}

		$mail_error = null;

		$capture_error = function ($wp_error) use (&$mail_error) {
			$mail_error = $wp_error;
		};

		add_action('wp_mail_failed', $capture_error);

		$send_mail = wu_send_mail($from, $to, $args);

		remove_action('wp_mail_failed', $capture_error);

		if ( ! $send_mail) {
			$message = $mail_error instanceof \WP_Error
				? $mail_error->get_error_message()
				: __('Something wrong happened with your test.', 'ultimate-multisite');

			wp_send_json_error(new \WP_Error('error', $message));
		}

		$page = wu_request('page', 'list');

		if ('edit' === $page) {
			wp_send_json_success(
				[
					'redirect_url' => wu_network_admin_url(
						'wp-ultimo-edit-email',
						[
							'id'          => $email_id,
							'test_notice' => __('Test sent successfully', 'ultimate-multisite'),
						]
					),
				]
			);

			die();
		}

		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url(
					'wp-ultimo-emails',
					[
						'notice' => __('Test sent successfully', 'ultimate-multisite'),
					]
				),
			]
		);
	}

	/**
	 * Renders the modal to reset or import system emails.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function render_reset_import_modal(): void {

		$default_system_emails = wu_get_default_system_emails();

		$created_emails = wu_get_all_system_emails();

		$fields = [
			'reset_emails' => [
				'type'      => 'toggle',
				'title'     => __('Reset System Emails ', 'ultimate-multisite'),
				'desc'      => __('Restore the system emails to their original content.', 'ultimate-multisite'),
				'tooltip'   => '',
				'value'     => 0,
				'html_attr' => [
					'v-model' => 'reset_emails',
				],
			],
		];

		$fields['reset_note'] = [
			'type'              => 'note',
			'title'             => '',
			'desc'              => __('No emails to reset.', 'ultimate-multisite'),
			'tooltip'           => '',
			'value'             => 0,
			'wrapper_html_attr' => [
				'v-show'  => 'reset_emails',
				'v-cloak' => 1,
			],
		];

		foreach ($created_emails as $system_email_key => $system_email_value) {
			$system_email_slug = $system_email_value->get_slug();

			if (isset($default_system_emails[ $system_email_slug ])) {
				$field_name = 'reset_' . $system_email_value->get_slug();

				$system_email_target = $system_email_value->get_target();

				$field_title = '<div><strong class="wu-inline-block wu-pr-1">' . $system_email_value->get_title() . '</strong></div>';

				$fields[ $field_name ] = [
					'type'              => 'toggle',
					'title'             => $field_title,
					'desc'              => $system_email_value->get_event() . ' <span class="wu-bg-gray-200 wu-text-gray-700 wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-font-mono">' . $system_email_target . '</span>',
					'tooltip'           => '',
					'value'             => 0,
					'wrapper_classes'   => 'wu-bg-gray-100',
					'wrapper_html_attr' => [
						'v-show'  => 'reset_emails',
						'v-cloak' => 1,
					],
				];

				if (isset($fields['reset_note'])) {
					unset($fields['reset_note']);
				}
			}
		}

		$fields['import_emails'] = [
			'type'      => 'toggle',
			'title'     => __('Import System Emails', 'ultimate-multisite'),
			'desc'      => __('Add new system emails based on Ultimate Multisite presets.', 'ultimate-multisite'),
			'tooltip'   => '',
			'value'     => 0,
			'html_attr' => [
				'v-model' => 'import_emails',
			],
		];

		$fields['import_note'] = [
			'type'              => 'note',
			'title'             => '',
			'desc'              => __('All emails are already present.', 'ultimate-multisite'),
			'tooltip'           => '',
			'value'             => 0,
			'wrapper_html_attr' => [
				'v-show'  => 'import_emails',
				'v-cloak' => 1,
			],
		];

		foreach ($default_system_emails as $default_email_key => $default_email_value) {
			$maybe_is_created = wu_get_email_by('slug', $default_email_key);

			if ( ! $maybe_is_created) {
				$field_name = 'import_' . $default_email_key;

				$field_title = '<div><strong class="wu-inline-block wu-pr-1">' . $default_email_value['title'] . '</strong> </div>';

				$fields[ $field_name ] = [
					'type'              => 'toggle',
					'title'             => $field_title,
					'desc'              => $default_email_value['event'] . ' <span class="wu-bg-gray-200 wu-text-gray-700 wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-font-mono">' . $default_email_value['target'] . '</span>',
					'tooltip'           => '',
					'value'             => 0,
					'wrapper_classes'   => 'wu-bg-gray-100',
					'wrapper_html_attr' => [
						'v-show'  => 'import_emails',
						'v-cloak' => 1,
					],
				];

				if (isset($fields['import_note'])) {
					unset($fields['import_note']);
				}
			}
		}

		$fields['submit_button'] = [
			'type'            => 'submit',
			'title'           => __('Reset and/or Import', 'ultimate-multisite'),
			'value'           => 'save',
			'classes'         => 'button button-primary wu-w-full',
			'wrapper_classes' => 'wu-items-end wu-text-right',
		];

		$form = new \WP_Ultimo\UI\Form(
			'reset_import',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'reset_import',
					'data-state'  => wp_json_encode(
						[
							'reset_emails'  => false,
							'import_emails' => false,
						]
					),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the modal to reset or import system emails.
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public function handle_reset_import_modal(): void {

		$reset = wu_request('reset_emails');

		$import = wu_request('import_emails');

		$default_system_emails = wu_get_default_system_emails();

		$created_emails = wu_get_all_system_emails();

		if ($reset) {
			foreach ($created_emails as $created_email) {
				$slug = $created_email->get_slug();

				$maybe_reset = wu_request('reset_' . $slug, '');

				if ($maybe_reset) {
					$created_email->delete();

					wu_create_default_system_email($slug);
				}
			}
		}

		if ($import) {
			foreach ($default_system_emails as $default_system_emails_key => $default_system_emails_value) {
				$slug = $default_system_emails_value['slug'];

				$maybe_import = wu_request('import_' . $slug, '');

				if ($maybe_import) {
					wu_create_default_system_email($slug);
				}
			}
		}

		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url('wp-ultimo-emails'),
			]
		);
	}

	/**
	 * Handles the redirect notice from sent new test modal.
	 *
	 * @param Base_Admin_Page $page The page object.
	 * @return void
	 */
	public function handle_page_redirect($page): void {

		if ($page->get_id() === 'wp-ultimo-emails') {
			if (wu_request('notice')) {
				$notice = wu_request('notice');

				?>

				<div id="message" class="updated notice notice-success is-dismissible below-h2">

					<p><?php echo esc_html($notice); ?></p>

				</div>

				<?php
			}
		}
	}

	/**
	 * Renders the reset confirmation modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_reset_confirmation_modal(): void {

		$fields = [
			'single_reset'  => [
				'type'      => 'toggle',
				'title'     => __('Confirm Reset', 'ultimate-multisite'),
				'desc'      => __('This action can not be undone.', 'ultimate-multisite'),
				'default'   => 0,
				'html_attr' => [
					'required' => 'required',
				],
			],
			'email_id'      => [
				'type'  => 'hidden',
				'value' => wu_request('id'),
			],
			'submit_button' => [
				'type'            => 'submit',
				'title'           => __('Reset Email', 'ultimate-multisite'),
				'value'           => 'reset',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end wu-text-right',
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'reset_confirmation',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'reset_confirmation',
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the reset confirmation modal.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function handle_reset_confirmation_modal() {

		$single_reset = wu_request('single_reset');

		$email_id = wu_request('email_id');

		if ( ! $single_reset || ! $email_id) {
			$error = new \WP_Error('error', __('Something wrong happened.', 'ultimate-multisite'));

			wp_send_json_error($error);
		}

		$email = wu_get_email($email_id);

		$slug = $email->get_slug();

		$default_system_emails = wu_get_default_system_emails();

		if (isset($default_system_emails[ $slug ])) {
			$email->delete();

			wu_create_default_system_email($slug);

			$new_email = wu_get_email_by('slug', $slug);

			if ( ! $new_email) {
				$error = new \WP_Error('error', __('Something wrong happened.', 'ultimate-multisite'));

				wp_send_json_error($error);
			}

			wp_send_json_success(
				[
					'redirect_url' => wu_network_admin_url(
						'wp-ultimo-edit-email',
						[
							'id' => $new_email->get_id(),
						]
					),
				]
			);
		}
	}

	/**
	 * Returns the action links for that page.
	 *
	 * @since 1.8.2
	 * @return array
	 */
	public function action_links() {

		$email_template_default = get_network_option(null, 'wu_default_email_template');

		return [
			[
				'url'   => wu_network_admin_url('wp-ultimo-edit-email'),
				'label' => __('Add System Email', 'ultimate-multisite'),
				'icon'  => 'wu-circle-with-plus',
			],
			[
				'url'   => wu_network_admin_url('wp-ultimo-customize-email-template&id=' . $email_template_default),
				'label' => __('Email Template', 'ultimate-multisite'),
				'icon'  => 'wu-mail',
			],
			[
				'url'     => wu_get_form_url('reset_import'),
				'classes' => 'wubox',
				'label'   => __('Reset or Import', 'ultimate-multisite'),
				'icon'    => 'wu-cycle',
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

		return new \WP_Ultimo\List_Tables\Email_List_Table();
	}
}
