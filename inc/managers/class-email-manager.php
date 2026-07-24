<?php
/**
 * Email Manager
 *
 * Handles processes related to Emails.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Email_Manager
 * @since 2.0.0
 */

namespace WP_Ultimo\Managers;

use Psr\Log\LogLevel;
use WP_Error;
use WP_Ultimo\Managers\Base_Manager;
use WP_Ultimo\Models\Email;
use WP_Ultimo\Helpers\Sender;
use WP_Ultimo\Models\Base_Model;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles processes related to emails.
 *
 * @since 2.0.0
 */
class Email_Manager extends Base_Manager {

	use \WP_Ultimo\Apis\Rest_Api;
	use \WP_Ultimo\Apis\WP_CLI;
	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The manager slug.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $slug = 'email';

	/**
	 * The model class associated with this manager.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $model_class = \WP_Ultimo\Models\Email::class;

	/**
	 * All default system emails and their original content.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $registered_default_system_emails = [];

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->enable_rest_api();

		$this->enable_wp_cli();

		/*
		 * Adds the Email fields
		 */
		add_action('wu_settings_emails', [$this, 'add_email_fields']);

		add_action('wu_event', [$this, 'send_system_email'], 10, 2);

		/*
		 * Registering a callback action to a schedule send.
		 */
		add_action('wu_send_schedule_system_email', [$this, 'send_schedule_system_email'], 10, 3);

		/*
		 * Create a log when a mail send fails.
		 */
		add_action('wp_mail_failed', [$this, 'log_mailer_failure']);

		add_action('wp_ajax_wu_get_event_payload_placeholders', [$this, 'get_event_placeholders']);
	}

	/**
	 * Send the email related to the current event.
	 *
	 * @param string $slug Slug of the event.
	 * @param array  $payload Payload of the event.
	 * @return void.
	 */
	public function send_system_email($slug, $payload): void {

		$all_emails = wu_get_emails(
			[
				'event' => $slug,
			]
		);

		$original_from = [
			'name'  => wu_get_setting('from_name', ''),
			'email' => wu_get_setting('from_email', ''),
		];

		if (empty($all_emails)) {
			$every_email = wu_get_emails();
			if (empty($every_email)) {
				// No system emails registered, probably they weren't created during setup.
				// Let's create them now
				$this->create_all_system_emails();
				$all_emails = wu_get_emails(
					[
						'event' => $slug,
					]
				);
			}
		}

		if (empty($all_emails)) {
			// translators: %s: event slug.
			wu_log_add('mailer', sprintf(__('No emails found for event %s.', 'ultimate-multisite'), $slug));
		}

		/*
		 * Loop through all the emails registered.
		 */
		foreach ($all_emails as $email) {
			if ($email->get_custom_sender()) {
				$from = [
					'name'  => $email->get_custom_sender_name(),
					'email' => $email->get_custom_sender_email(),
				];
			} else {
				$from = $original_from;
			}

			/*
			 * Compiles the target list.
			 */
			$to = $email->get_target_list($payload);

			if (empty($to)) {
				wu_log_add('mailer', __('No targets found.', 'ultimate-multisite'));

				return;
			}

			$args = [
				'style'   => $email->get_style(),
				'content' => $email->get_content(),
				'subject' => get_network_option(null, 'site_name') . ' - ' . $email->get_title(),
				'payload' => $payload,
			];

			/*
			 * Add the invoice attachment, if need be.
			 */
			if (wu_get_isset($payload, 'payment_id') && wu_get_setting('attach_invoice_pdf', true) && (! isset($payload['payment_is_paid']) || $payload['payment_is_paid'])) {
				$invoice_payment = wu_get_payment($payload['payment_id']);

				if ($invoice_payment) {
					$file_name = 'invoice-' . $invoice_payment->get_hash() . '.pdf';

					$this->attach_invoice_pdf($invoice_payment, $file_name, $args['subject']);
				}
			}

			$when_to_send = $email->get_when_to_send();

			if ($when_to_send) {
				$args['schedule'] = $when_to_send;
			}

			Sender::send_mail($from, $to, $args);
		}
	}

	/**
	 * Attach an invoice PDF generated directly from the payment model.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_Ultimo\Models\Payment $payment The payment to generate the invoice for.
	 * @param string                    $file_name The name to save the file with.
	 * @param string                    $email_subject The email subject, to avoid attaching a file to the wrong email.
	 * @return void
	 */
	public function attach_invoice_pdf($payment, string $file_name, string $email_subject = ''): void {

		add_action(
			'phpmailer_init',
			function ($mail) use ($payment, $file_name, $email_subject) {

				if ($email_subject && $mail->Subject !== $email_subject) { // phpcs:ignore
					return;
				}

				$invoice = new \WP_Ultimo\Invoices\Invoice($payment);

				$mail->addStringAttachment($invoice->get_content(), $file_name);
			}
		);
	}

	/**
	 * Add all email fields.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_email_fields(): void {

		wu_register_settings_field(
			'emails',
			'sender_header',
			[
				'title' => __('Sender Settings', 'ultimate-multisite'),
				'desc'  => __('Change the settings of the email headers, like from and name.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'emails',
			'from_name',
			[
				'title'       => __('"From" Name', 'ultimate-multisite'),
				'desc'        => __('How the sender name will appear in emails sent by Ultimate Multisite.', 'ultimate-multisite'),
				'type'        => 'text',
				'placeholder' => get_network_option(null, 'site_name'),
				'default'     => get_network_option(null, 'site_name'),
				'html_attr'   => [
					'v-model' => 'from_name',
				],
			]
		);

		wu_register_settings_field(
			'emails',
			'from_email',
			[
				'title'       => __('"From" E-mail', 'ultimate-multisite'),
				'desc'        => __('How the sender email will appear in emails sent by Ultimate Multisite.', 'ultimate-multisite'),
				'type'        => 'email',
				'placeholder' => get_network_option(null, 'admin_email'),
				'default'     => get_network_option(null, 'admin_email'),
				'html_attr'   => [
					'v-model' => 'from_email',
				],
			]
		);

		wu_register_settings_field(
			'emails',
			'template_header',
			[
				'title' => __('Template Settings', 'ultimate-multisite'),
				'desc'  => __('Change the settings of the email templates.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'emails',
			'email_template_type',
			[
				'title'     => __('Email Templates Style', 'ultimate-multisite'),
				'desc'      => __('Choose if email body will be sent using the HTML template or in plain text.', 'ultimate-multisite'),
				'type'      => 'select',
				'default'   => 'html',
				'options'   => [
					'html'  => __('HTML Emails', 'ultimate-multisite'),
					'plain' => __('Plain Emails', 'ultimate-multisite'),
				],
				'html_attr' => [
					'v-model' => 'emails_template',
				],
			]
		);

		wu_register_settings_field(
			'emails',
			'expiring_header',
			[
				'title' => __('Expiring Notification Settings', 'ultimate-multisite'),
				'desc'  => __('Change the settings for the expiring notification (trials and subscriptions) emails.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'emails',
			'expiring_days',
			[
				'title'       => __('Days to Expire', 'ultimate-multisite'),
				'desc'        => __('Select when we should send the notification email. If you select 3 days, for example, a notification email will be sent to every membership (or trial period) expiring in the next 3 days. Memberships are checked hourly.', 'ultimate-multisite'),
				'type'        => 'number',
				'placeholder' => __('e.g. 3', 'ultimate-multisite'),
				'html_attr'   => [
					'v-model' => 'expiring_days',
				],
			]
		);
	}

	/**
	 * Register in the global variable all the default system emails.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args System email params.
	 * @return void.
	 */
	public function register_default_system_email($args): void {

		$this->registered_default_system_emails[ $args['slug'] ] = $args;
	}

	/**
	 * Create a system email.
	 *
	 * @since 2.0.0
	 *
	 * @param array|null $args with the system email details to register.
	 * @return Email|null|WP_Error Returns Email object if created, null if already exists or invalid args, or WP_Error on failure.
	 */
	public function create_system_email($args) {

		// Validate that args is an array and has required fields
		if (! is_array($args) || empty($args['slug'])) {
			return null;
		}

		// Check if email already exists
		if ($this->is_created($args['slug'])) {
			return null; // Email already exists, no need to create
		}

		$email_args = wp_parse_args(
			$args,
			[
				'event'              => '',
				'title'              => '',
				'content'            => '',
				'slug'               => '',
				'target'             => 'admin',
				'style'              => 'use_default',
				'send_copy_to_admin' => true,
				'active'             => true,
				'legacy'             => false,
				'date_registered'    => wu_get_current_time('mysql', true),
				'date_modified'      => wu_get_current_time('mysql', true),
				'status'             => 'publish',
			]
		);

		$email = new Email($email_args);

		$saved = $email->save();

		return is_wp_error($saved) ? $saved : $email;
	}

	/**
	 * Register all default system emails.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function create_all_system_emails(): void {
		$system_emails = wu_get_default_system_emails();

		foreach ($system_emails as $email_key => $email_value) {
			$this->create_system_email($email_value);
		}
	}

	/**
	 * Register all default system emails.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register_all_default_system_emails(): void {
		/*
		 * Checkout Completed - Admin
		 */
		$this->register_default_system_email(
			[
				'event'   => 'payment_received',
				'slug'    => 'payment_received_admin',
				'target'  => 'admin',
				'title'   => '{{payment_email_admin_subject}}',
				'content' => wu_get_template_contents('emails/admin/payment-received'),
			]
		);

		/*
		 * Checkout Completed - Customer
		 */
		$this->register_default_system_email(
			[
				'event'              => 'payment_received',
				'slug'               => 'payment_received_customer',
				'target'             => 'customer',
				'title'              => '{{payment_email_customer_subject}}',
				'content'            => wu_get_template_contents('emails/customer/payment-received'),
				'send_copy_to_admin' => false,
			]
		);

		/*
		 * Site Published - Admin
		 */
		$this->register_default_system_email(
			[
				'event'   => 'site_published',
				'target'  => 'admin',
				'slug'    => 'site_published_admin',
				'title'   => __('A new site was created on your Network!', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/admin/site-published'),
			]
		);

		/*
		 * Site Published - Customer
		 */
		$this->register_default_system_email(
			[
				'event'   => 'site_published',
				'target'  => 'customer',
				'slug'    => 'site_published_customer',
				'title'   => __('Your site is ready!', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/customer/site-published'),
			]
		);

		/*
		 * Site Published - Customer
		 */
		$this->register_default_system_email(
			[
				'event'   => 'confirm_email_address',
				'target'  => 'customer',
				'slug'    => 'confirm_email_address',
				'title'   => __('Confirm your email address!', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/customer/confirm-email-address'),
			]
		);

		/*
		 * Domain Created - Admin
		 */
		$this->register_default_system_email(
			[
				'event'   => 'domain_created',
				'target'  => 'admin',
				'slug'    => 'domain_created_admin',
				'title'   => __('A new domain was added to your Network!', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/admin/domain-created'),
			]
		);

		/*
		 * Pending Renewal Payment Created - Customer
		 */
		$this->register_default_system_email(
			[
				'event'   => 'renewal_payment_created',
				'target'  => 'customer',
				'slug'    => 'renewal_payment_created',
				'title'   => __('You have a new pending payment!', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/customer/renewal-payment-created'),
			]
		);

		/*
		 * Payment Failed - Customer
		 */
		$this->register_default_system_email(
			[
				'event'   => 'payment_failed',
				'slug'    => 'payment_failed_customer',
				'target'  => 'customer',
				'title'   => __('Your payment could not be processed', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/customer/payment-failed'),
			]
		);

		/*
		 * Payment Failed - Admin
		 */
		$this->register_default_system_email(
			[
				'event'   => 'payment_failed',
				'slug'    => 'payment_failed_admin',
				'target'  => 'admin',
				'title'   => __('A recurring payment has failed!', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/admin/payment-failed'),
			]
		);

		/*
		 * Membership Expired - Customer
		 */
		$this->register_default_system_email(
			[
				'event'   => 'membership_expired',
				'slug'    => 'membership_expired_customer',
				'target'  => 'customer',
				'title'   => __('Your membership has expired', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/customer/membership-expired'),
			]
		);

		/*
		 * Membership Expired - Admin
		 */
		$this->register_default_system_email(
			[
				'event'   => 'membership_expired',
				'slug'    => 'membership_expired_admin',
				'target'  => 'admin',
				'title'   => __('A membership has expired!', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/admin/membership-expired'),
			]
		);

		/*
		 * Demo Site Expiring - Customer
		 */
		$this->register_default_system_email(
			[
				'event'   => 'demo_site_expiring',
				'slug'    => 'demo_site_expiring_customer',
				'target'  => 'customer',
				'title'   => __('Your demo site is about to expire', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/customer/demo-site-expiring'),
			]
		);

		/*
		 * Demo Site Expiring - Admin
		 */
		$this->register_default_system_email(
			[
				'event'   => 'demo_site_expiring',
				'slug'    => 'demo_site_expiring_admin',
				'target'  => 'admin',
				'title'   => __('A demo site is about to expire', 'ultimate-multisite'),
				'content' => wu_get_template_contents('emails/admin/demo-site-expiring'),
			]
		);

		do_action('wu_system_emails_after_register');
	}

	/**
	 * Get a single or all default registered system emails.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Default system email slug.
	 * @return array All default system emails.
	 */
	public function get_default_system_emails($slug = '') {
		if (empty($this->registered_default_system_emails)) {
			$this->register_all_default_system_emails();
		}

		if ($slug && isset($this->registered_default_system_emails[ $slug ])) {
			return $this->registered_default_system_emails[ $slug ];
		}

		return $this->registered_default_system_emails;
	}

	/**
	 * Check if the system email already exists.
	 *
	 * @param mixed $slug Email slug to use as reference.
	 * @return bool True if email exists, false otherwise.
	 */
	public function is_created($slug): bool {

		return wu_get_email_by('slug', $slug) !== false;
	}

	/**
	 * Get the default template email.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug With the event slug.
	 * @return void
	 */
	public function get_event_placeholders($slug = ''): void {

		$placeholders = [];

		if (wu_request('email_event')) {
			$slug = wu_request('email_event');
		}

		if ($slug) {
			$event = wu_get_event_type($slug);

			if ($event) {
				foreach (wu_maybe_lazy_load_payload($event['payload']) as $placeholder => $value) {
					$name = ucwords(str_replace('_', ' ', $placeholder));

					$placeholders[] = [
						'name'        => $name,
						'placeholder' => $placeholder,
					];
				}
			}
		}

		if (wu_request('email_event')) {
			wp_send_json($placeholders);
		}
	}

	/**
	 * Sends a schedule email.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $to Email targets.
	 * @param string $subject Email subject.
	 * @param string $template Email content.
	 * @return void
	 */
	public function send_schedule_system_email($to, $subject, $template): void {

		Sender::send_mail($to, $subject, $template);
	}

	/**
	 * Log failures on the WordPress mailer, just so we have a copy of the issues for debugging.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Error $error The error with the mailer.
	 * @return void.
	 */
	public function log_mailer_failure($error): void {

		if (is_wp_error($error)) {
			wu_log_add('mailer-errors', $error->get_error_message(), LogLevel::ERROR);
		}
	}
}
