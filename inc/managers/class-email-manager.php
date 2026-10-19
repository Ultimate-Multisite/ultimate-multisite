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
	 * Payload models that delayed emails store by ID and rebuild when due.
	 *
	 * @since 2.18.0
	 * @var array<string, string> Model name => getter function.
	 */
	const SCHEDULED_PAYLOAD_MODELS = [
		'membership' => 'wu_get_membership',
		'customer'   => 'wu_get_customer',
		'payment'    => 'wu_get_payment',
		'site'       => 'wu_get_site',
		'domain'     => 'wu_get_domain',
		'product'    => 'wu_get_product',
	];

	/**
	 * Largest JSON length Action Scheduler accepts for action arguments.
	 *
	 * @since 2.18.0
	 * @var int
	 */
	const SCHEDULED_ARGS_MAX_LENGTH = 8000;

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
		add_action('wu_send_schedule_system_email', [$this, 'send_schedule_system_email'], 10, 2);

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
			if ( ! $email->is_active() || ! $email->applies_to_payload($payload)) {
				continue;
			}

			$when_to_send = $email->get_when_to_send();

			/*
			 * Delayed emails are queued and re-evaluated when they are due,
			 * so content edits, deactivation and plan changes made in the
			 * meantime are respected.
			 */
			if ($when_to_send) {
				$this->schedule_system_email($email, $payload, $when_to_send);

				continue;
			}

			$this->deliver_system_email($email, $payload);
		}
	}

	/**
	 * Queues a delayed system email.
	 *
	 * @since 2.18.0
	 *
	 * @param Email $email        The system email.
	 * @param array $payload      The event payload.
	 * @param int   $when_to_send Timestamp when the email is due.
	 * @return int The scheduled action ID, or 0 on failure.
	 */
	public function schedule_system_email($email, $payload, $when_to_send) {

		$args = [
			'email_id' => (int) $email->get_id(),
			'payload'  => $this->compact_scheduled_payload((array) $payload),
		];

		/*
		 * Action Scheduler rejects arguments longer than 8000 JSON characters
		 * and only logs the failure, so report it against this email.
		 */
		$size = strlen((string) wp_json_encode($args));

		if ($size > self::SCHEDULED_ARGS_MAX_LENGTH) {
			// translators: 1: email slug, 2: size in characters.
			wu_log_add('mailer', sprintf(__('Could not schedule the system email %1$s: its event data is too large (%2$d characters).', 'ultimate-multisite'), $email->get_slug(), $size), LogLevel::ERROR);

			return 0;
		}

		$action_id = (int) wu_schedule_single_action((int) $when_to_send, 'wu_send_schedule_system_email', $args, 'wu_system_emails');

		if ( ! $action_id) {
			// translators: %s: email slug.
			wu_log_add('mailer', sprintf(__('Could not schedule the system email %s.', 'ultimate-multisite'), $email->get_slug()), LogLevel::ERROR);
		}

		return $action_id;
	}

	/**
	 * Replaces model fields in a payload with their IDs for storage.
	 *
	 * Fields of models that still exist are rebuilt by refresh_scheduled_payload()
	 * when the email is due; event-specific fields are kept as captured.
	 *
	 * @since 2.18.0
	 *
	 * @param array $payload The event payload.
	 * @return array
	 */
	protected function compact_scheduled_payload(array $payload): array {

		foreach (self::SCHEDULED_PAYLOAD_MODELS as $model => $getter) {
			$id_key = "{$model}_id";

			if ( ! wu_get_isset($payload, $id_key)) {
				continue;
			}

			$object = call_user_func($getter, $payload[ $id_key ]);

			if ( ! $object) {
				continue;
			}

			$model_keys = array_diff(array_keys(wu_generate_event_payload($model, $object)), [$id_key]);

			$payload = array_diff_key($payload, array_flip($model_keys));
		}

		return $payload;
	}

	/**
	 * Builds a system email subject, optionally prefixed with the network name.
	 *
	 * @since 2.18.0
	 *
	 * @param Email $email   The system email.
	 * @param array $payload The event payload.
	 * @return string The subject before placeholders are replaced.
	 */
	public function get_system_email_subject($email, $payload = []): string {

		$subject = $email->has_subject_prefix()
			? get_network_option(null, 'site_name') . ' - ' . $email->get_title()
			: $email->get_title();

		/**
		 * Filters the subject of a system email before placeholders are replaced.
		 *
		 * @since 2.18.0
		 *
		 * @param string $subject The subject, with the network name prefix if enabled.
		 * @param Email  $email   The system email.
		 * @param array  $payload The event payload.
		 */
		return (string) apply_filters('wu_system_email_subject', $subject, $email, (array) $payload);
	}

	/**
	 * Sends a single system email now.
	 *
	 * @since 2.18.0
	 *
	 * @param Email $email   The system email.
	 * @param array $payload The event payload.
	 * @return bool Whether the mailer accepted the email.
	 */
	public function deliver_system_email($email, $payload): bool {

		if ($email->get_custom_sender()) {
			$from = [
				'name'  => $email->get_custom_sender_name(),
				'email' => $email->get_custom_sender_email(),
			];
		} else {
			$from = [
				'name'  => wu_get_setting('from_name', ''),
				'email' => wu_get_setting('from_email', ''),
			];
		}

		/*
		 * Compiles the target list.
		 */
		$to = $email->get_target_list($payload);

		if (empty($to)) {
			// translators: %s: email slug.
			wu_log_add('mailer', sprintf(__('No targets found for the system email %s.', 'ultimate-multisite'), $email->get_slug()));

			return false;
		}

		$args = [
			'style'   => $email->get_style(),
			'content' => $email->get_content(),
			'subject' => $this->get_system_email_subject($email, $payload),
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

		return (bool) Sender::send_mail($from, $to, $args);
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
				'desc'        => __('Select when the "Trial or Membership Expiring" event fires. If you select 3 days, for example, it fires once for every free trial, and every membership that does not renew automatically, ending in the next 3 days. Use 0 to fire when it ends. Memberships are checked hourly.', 'ultimate-multisite'),
				'type'        => 'number',
				'default'     => 3,
				'placeholder' => __('e.g. 3', 'ultimate-multisite'),
				'html_attr'   => [
					'v-model' => 'expiring_days',
					'min'     => 0,
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
		 * Trial or Membership Expiring - Customer
		 */
		$this->register_default_system_email(
			[
				'event'              => 'membership_expiring',
				'slug'               => 'membership_expiring_customer',
				'target'             => 'customer',
				'title'              => __('Your trial or membership is ending soon', 'ultimate-multisite'),
				'content'            => wu_get_template_contents('emails/customer/membership-expiring'),
				'send_copy_to_admin' => false,
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
	 * Sends a delayed system email when it is due.
	 *
	 * The email is reloaded, so it uses its current content and is skipped if
	 * it was deleted, deactivated, or no longer applies to the membership
	 * (for example after the customer changed plans). Membership and customer
	 * placeholders are refreshed with their current values.
	 *
	 * @since 2.0.0
	 * @since 2.18.0 Receives the email ID and event payload.
	 *
	 * @param int   $email_id The system email ID.
	 * @param array $payload  The payload captured when the event fired.
	 * @return void
	 */
	public function send_schedule_system_email($email_id, $payload = []): void {
		/*
		 * Before 2.18.0 this hook was registered with (to, subject, template)
		 * arguments. Never treat such a job's recipients as an email ID.
		 */
		if ( ! is_numeric($email_id) || ! is_array($payload)) {
			wu_log_add('mailer', __('Skipped a scheduled system email queued in an unsupported format.', 'ultimate-multisite'), LogLevel::WARNING);

			return;
		}

		$email = wu_get_email((int) $email_id);

		if ( ! $email || ! $email->is_active()) {
			return;
		}

		$payload = $this->refresh_scheduled_payload($payload, $email);

		if (false === $payload || ! $email->applies_to_payload($payload)) {
			return;
		}

		/**
		 * Filters whether a delayed system email should still be sent.
		 *
		 * @since 2.18.0
		 *
		 * @param bool  $send    Whether to send the email.
		 * @param Email $email   The system email.
		 * @param array $payload The refreshed event payload.
		 */
		if ( ! apply_filters('wu_send_scheduled_system_email', true, $email, $payload)) {
			return;
		}

		$this->deliver_system_email($email, $payload);
	}

	/**
	 * Rebuilds the model placeholders of a delayed email payload with current values.
	 *
	 * @since 2.18.0
	 *
	 * @param array $payload The stored payload.
	 * @param Email $email   The system email being sent.
	 * @return array|false The refreshed payload, or false if a referenced record no
	 *                     longer exists or a trial/membership term has changed.
	 */
	protected function refresh_scheduled_payload(array $payload, $email) {

		foreach (self::SCHEDULED_PAYLOAD_MODELS as $model => $getter) {
			$id_key = "{$model}_id";

			if ( ! wu_get_isset($payload, $id_key)) {
				continue;
			}

			$object = call_user_func($getter, $payload[ $id_key ]);

			if ( ! $object) {
				return false;
			}

			$payload = array_merge($payload, wu_generate_event_payload($model, $object));
		}

		/*
		 * A delayed expiring email must describe the term it was triggered
		 * for. Skip it after a renewal or upgrade; otherwise refresh the
		 * remaining days.
		 */
		if ('membership_expiring' === $email->get_event() && wu_get_isset($payload, 'expiring_end_date')) {
			$membership = wu_get_isset($payload, 'membership_id') ? wu_get_membership((int) $payload['membership_id']) : false;
			$fields     = $membership ? \WP_Ultimo\Cron::get_instance()->get_expiring_fields($membership) : null;

			if (null === $fields || $fields['expiring_end_date'] !== $payload['expiring_end_date']) {
				return false;
			}

			$payload = array_merge($payload, $fields);
		}

		return $payload;
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
