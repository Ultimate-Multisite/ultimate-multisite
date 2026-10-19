<?php
/**
 * Tests for delayed, product-targeted system emails and the expiring event.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Managers;

use WP_Ultimo\Cron;
use WP_Ultimo\Models\Email;
use WP_UnitTestCase;

/**
 * Email sequence tests.
 */
class Email_Sequence_Test extends WP_UnitTestCase {

	const EVENT = 'payment_received';

	/**
	 * Captured wp_mail calls.
	 *
	 * @var array
	 */
	private array $mails = [];

	/**
	 * Set up mail capture and remove any existing emails for the event.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->mails = [];

		add_filter('pre_wp_mail', [$this, 'capture_mail'], 10, 2);

		foreach (wu_get_emails(['event' => self::EVENT]) as $email) {
			$email->delete();
		}

		$this->unschedule();
	}

	/**
	 * Remove mail capture and queued actions.
	 */
	public function tear_down(): void {

		remove_filter('pre_wp_mail', [$this, 'capture_mail'], 10);

		$this->unschedule();

		parent::tear_down();
	}

	/**
	 * Captures a mail instead of sending it.
	 *
	 * @param mixed $pre  Short-circuit value.
	 * @param array $atts wp_mail arguments.
	 * @return bool
	 */
	public function capture_mail($pre, $atts) {

		$this->mails[] = $atts;

		return true;
	}

	/**
	 * Removes queued scheduled system emails.
	 */
	private function unschedule(): void {

		wu_switch_blog_and_run(fn() => as_unschedule_all_actions('wu_send_schedule_system_email', null, 'wu_system_emails'));
	}

	/**
	 * Returns pending scheduled system email actions.
	 *
	 * @return array
	 */
	private function pending(): array {

		return wu_switch_blog_and_run(
			fn() => as_get_scheduled_actions(
				[
					'hook'     => 'wu_send_schedule_system_email',
					'group'    => 'wu_system_emails',
					'status'   => \ActionScheduler_Store::STATUS_PENDING,
					'per_page' => 20,
				]
			)
		);
	}

	/**
	 * Creates a customer email for the test event.
	 *
	 * @param array $args Overrides.
	 * @return Email
	 */
	private function create_email(array $args = []): Email {

		$email = new Email(
			array_merge(
				[
					'event'   => self::EVENT,
					'slug'    => 'sequence-' . wp_generate_uuid4(),
					'title'   => 'Hello {{customer_name}}',
					'content' => '<p>Status: {{membership_status}}</p>',
					'target'  => 'customer',
					'style'   => 'plain',
					'active'  => true,
				],
				$args
			)
		);

		foreach ($args as $key => $value) {
			if (method_exists($email, "set_{$key}")) {
				$email->{"set_{$key}"}($value);
			}
		}

		$this->assertTrue($email->save());

		return wu_get_email($email->get_id());
	}

	/**
	 * Creates a membership on a new plan and returns its event payload.
	 *
	 * @param array $membership_args Membership overrides.
	 * @return array{0: \WP_Ultimo\Models\Membership, 1: array}
	 */
	private function create_membership(array $membership_args = []): array {

		$customer = wu_create_customer(
			[
				'username' => 'seq-' . wp_generate_uuid4(),
				'email'    => 'seq-' . wp_generate_uuid4() . '@example.com',
				'password' => 'password123',
			]
		);

		$this->assertNotWPError($customer, is_wp_error($customer) ? $customer->get_error_message() : '');

		$plan = $this->create_plan();

		$membership = wu_create_membership(
			array_merge(
				[
					'customer_id' => $customer->get_id(),
					'plan_id'     => $plan->get_id(),
				],
				$membership_args
			)
		);

		$this->assertNotWPError($membership);

		$payload = array_merge(
			wu_generate_event_payload('membership', $membership),
			wu_generate_event_payload('customer', $customer)
		);

		return [$membership, $payload];
	}

	/**
	 * Creates a plan.
	 *
	 * @return \WP_Ultimo\Models\Product
	 */
	private function create_plan() {

		$plan = wu_create_product(
			[
				'name'         => 'Plan ' . wp_generate_uuid4(),
				'slug'         => 'plan-' . wp_generate_uuid4(),
				'pricing_type' => 'free',
				'amount'       => 0,
				'currency'     => 'USD',
				'recurring'    => false,
				'type'         => 'plan',
			]
		);

		$this->assertNotWPError($plan);

		return $plan;
	}

	/**
	 * Immediate emails are still sent right away.
	 */
	public function test_immediate_email_is_sent(): void {

		$this->create_email();
		[, $payload] = $this->create_membership();

		Email_Manager::get_instance()->send_system_email(self::EVENT, $payload);

		$this->assertCount(1, $this->mails);
		$this->assertEmpty($this->pending());
	}

	/**
	 * A delayed email is queued instead of sent, then sent when its action runs.
	 */
	public function test_delayed_email_is_queued_and_sent_when_due(): void {

		$email                  = $this->create_email(
			[
				'schedule'      => true,
				'schedule_type' => 'days',
				'send_days'     => 7,
			]
		);
		[$membership, $payload] = $this->create_membership();

		Email_Manager::get_instance()->send_system_email(self::EVENT, $payload);

		$this->assertCount(0, $this->mails, 'Delayed emails must not be sent immediately.');

		$pending = $this->pending();
		$this->assertCount(1, $pending);

		$action = reset($pending);
		$due    = $action->get_schedule()->get_date()->getTimestamp();
		$this->assertEqualsWithDelta(time() + 7 * DAY_IN_SECONDS, $due, 120);

		$args = array_values($action->get_args());
		$this->assertSame($email->get_id(), $args[0]);
		$this->assertSame($membership->get_id(), (int) $args[1]['membership_id']);
		$this->assertArrayNotHasKey('membership_status', $args[1], 'Model fields are stored by ID and rebuilt when due.');
		$this->assertArrayNotHasKey('customer_user_email', $args[1]);

		do_action_ref_array('wu_send_schedule_system_email', $args);

		$this->assertCount(1, $this->mails);
		$this->assertStringContainsString('Status: ' . $membership->get_status(), $this->mails[0]['message']);
	}

	/**
	 * Oversized event data is reported instead of silently dropped by Action Scheduler.
	 */
	public function test_oversized_payload_is_not_scheduled(): void {

		$email       = $this->create_email([
			'schedule'  => true,
			'send_days' => 1,
		]);
		[, $payload] = $this->create_membership();

		$payload['huge'] = str_repeat('x', 9000);

		$this->assertSame(0, Email_Manager::get_instance()->schedule_system_email($email, $payload, time() + DAY_IN_SECONDS));
		$this->assertEmpty($this->pending());
	}

	/**
	 * Jobs queued with the pre-2.18 (to, subject, template) arguments never send.
	 */
	public function test_legacy_scheduled_arguments_are_ignored(): void {

		$this->create_email(['target' => 'admin']);

		Email_Manager::get_instance()->send_schedule_system_email([['email' => 'old@example.com']], 'Old subject');

		$this->assertCount(0, $this->mails);
	}

	/**
	 * Delayed emails use current membership values and skip deactivated emails.
	 */
	public function test_delayed_email_uses_current_state(): void {

		$email                  = $this->create_email([
			'schedule'  => true,
			'send_days' => 1,
		]);
		[$membership, $payload] = $this->create_membership(['status' => 'active']);

		$membership->set_status('on-hold');
		$membership->save();

		Email_Manager::get_instance()->send_schedule_system_email($email->get_id(), $payload);

		$this->assertCount(1, $this->mails);
		$this->assertStringContainsString('on-hold', $this->mails[0]['message']);

		// The admin toggle posts '0'.
		$email->set_active('0');
		$saved = $email->save();
		$this->assertTrue($saved, is_wp_error($saved) ? $saved->get_error_message() : '');

		// The scheduled action runs in a later request with fresh caches.
		wp_cache_flush();

		$this->assertFalse((bool) wu_get_email($email->get_id())->is_active());

		Email_Manager::get_instance()->send_schedule_system_email($email->get_id(), $payload);

		$this->assertCount(1, $this->mails, 'Deactivated emails must not be sent.');
	}

	/**
	 * Include and exclude filters match the membership's products.
	 */
	public function test_product_filter(): void {

		[$membership, $payload] = $this->create_membership();
		$other_plan             = $this->create_plan();

		$this->create_email([
			'product_filter' => 'include',
			'products'       => [$membership->get_plan_id()],
		]);
		$this->create_email([
			'product_filter' => 'include',
			'products'       => [$other_plan->get_id()],
		]);
		$this->create_email([
			'product_filter' => 'exclude',
			'products'       => [$membership->get_plan_id()],
		]);
		$this->create_email([
			'product_filter' => 'exclude',
			'products'       => (string) $other_plan->get_id(),
		]);

		Email_Manager::get_instance()->send_system_email(self::EVENT, $payload);

		$this->assertCount(2, $this->mails);
	}

	/**
	 * A delayed email limited to a product is skipped after the customer changes plan.
	 */
	public function test_delayed_product_email_skipped_after_plan_change(): void {

		[$membership, $payload] = $this->create_membership();
		$trial_plan             = $membership->get_plan_id();

		$email = $this->create_email(
			[
				'schedule'       => true,
				'send_days'      => 14,
				'product_filter' => 'include',
				'products'       => [$trial_plan],
			]
		);

		Email_Manager::get_instance()->send_system_email(self::EVENT, $payload);
		$this->assertCount(1, $this->pending());

		$membership->set_plan_id($this->create_plan()->get_id());
		$membership->save();

		Email_Manager::get_instance()->send_schedule_system_email($email->get_id(), $payload);

		$this->assertCount(0, $this->mails);
	}

	/**
	 * The expiring event fires once for a trial ending inside the window.
	 */
	public function test_membership_expiring_fires_once_for_trial(): void {

		[$membership] = $this->create_membership(
			[
				'status'         => 'trialing',
				'date_trial_end' => gmdate('Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS),
			]
		);

		$fired   = [];
		$capture = function ($payload) use (&$fired) {
			$fired[] = $payload;
		};
		add_action('wu_event_membership_expiring', $capture);

		$cron = Cron::get_instance();
		$cron->async_membership_expiring($membership->get_id());
		$cron->async_membership_expiring($membership->get_id());

		remove_action('wu_event_membership_expiring', $capture);

		$this->assertCount(1, $fired);
		$this->assertTrue($fired[0]['expiring_is_trial']);
		$this->assertTrue($fired[0]['expiring_needs_action']);
		$this->assertSame(2, $fired[0]['expiring_days_left']);
	}

	/**
	 * Trials ending after the window do not fire yet.
	 */
	public function test_membership_expiring_ignores_trials_outside_window(): void {

		[$membership] = $this->create_membership(
			[
				'status'         => 'trialing',
				'date_trial_end' => gmdate('Y-m-d H:i:s', time() + 10 * DAY_IN_SECONDS),
			]
		);

		$fired   = 0;
		$capture = function () use (&$fired) {
			++$fired;
		};
		add_action('wu_event_membership_expiring', $capture);

		Cron::get_instance()->async_membership_expiring($membership->get_id());

		remove_action('wu_event_membership_expiring', $capture);

		$this->assertSame(0, $fired);
	}

	/**
	 * With 0 days, a trial ending today is still reported as a trial.
	 */
	public function test_membership_expiring_zero_days_reports_trial_ending_today(): void {

		[$membership] = $this->create_membership(
			[
				'status'         => 'trialing',
				'date_trial_end' => gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS),
			]
		);

		$zero    = fn() => 0;
		$fired   = [];
		$capture = function ($payload) use (&$fired) {
			$fired[] = $payload;
		};
		add_filter('wu_membership_expiring_days', $zero);
		add_action('wu_event_membership_expiring', $capture);

		Cron::get_instance()->async_membership_expiring($membership->get_id());

		remove_action('wu_event_membership_expiring', $capture);
		remove_filter('wu_membership_expiring_days', $zero);

		$this->assertCount(1, $fired);
		$this->assertTrue($fired[0]['expiring_is_trial']);
		$this->assertSame(0, $fired[0]['expiring_days_left']);
	}

	/**
	 * A delayed expiring email is skipped when the trial was extended or renewed meanwhile.
	 */
	public function test_delayed_expiring_email_skipped_after_term_change(): void {

		$trial_end    = gmdate('Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS);
		[$membership] = $this->create_membership(
			[
				'status'         => 'trialing',
				'date_trial_end' => $trial_end,
			]
		);

		$email = $this->create_email(
			[
				'event'     => 'membership_expiring',
				'schedule'  => true,
				'send_days' => 1,
			]
		);

		$payload = array_merge(
			wu_generate_event_payload('membership', $membership),
			wu_generate_event_payload('customer', $membership->get_customer()),
			Cron::get_instance()->get_expiring_fields($membership)
		);

		Email_Manager::get_instance()->send_schedule_system_email($email->get_id(), $payload);
		$this->assertCount(1, $this->mails, 'Same term: the email is sent.');

		$membership->set_date_trial_end(gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS));
		$membership->save();

		Email_Manager::get_instance()->send_schedule_system_email($email->get_id(), $payload);
		$this->assertCount(1, $this->mails, 'Changed term: the stale email is skipped.');
	}

	/**
	 * Checkout Completed is dispatched as a full event, so emails and webhooks can use it.
	 */
	public function test_checkout_completed_is_dispatched_with_model_fields(): void {

		[$membership] = $this->create_membership();

		$payment = wu_create_payment(
			[
				'customer_id'   => $membership->get_customer_id(),
				'membership_id' => $membership->get_id(),
				'currency'      => 'USD',
				'subtotal'      => 0,
				'total'         => 0,
				'gateway'       => 'free',
			]
		);
		$this->assertNotWPError($payment);

		$fired   = [];
		$capture = function ($payload) use (&$fired) {
			$fired[] = $payload;
		};
		add_action('wu_event_checkout_completed', $capture);

		\WP_Ultimo\Signup_Metrics::get_instance()->track_checkout_completed($payment, $membership, $membership->get_customer(), null, 'new', null);

		remove_action('wu_event_checkout_completed', $capture);

		$this->assertCount(1, $fired);
		$this->assertSame($membership->get_id(), $fired[0]['membership_id']);
		$this->assertSame($membership->get_customer()->get_email_address(), $fired[0]['customer_user_email']);
		$this->assertSame('new', $fired[0]['cart_type']);

		// Dispatched events must still be stored, e.g. for the signup funnel statistics.
		$stored = wu_get_events(
			[
				'slug'      => 'checkout_completed',
				'object_id' => $membership->get_id(),
			]
		);
		$this->assertCount(1, $stored);
		$this->assertSame('system', $stored[0]->get_initiator());
	}

	/**
	 * The default expiring email is registered for the new event.
	 */
	public function test_default_expiring_email_is_registered(): void {

		$default = wu_get_default_system_emails('membership_expiring_customer');

		$this->assertSame('membership_expiring', $default['event']);
		$this->assertSame('customer', $default['target']);
		$this->assertStringContainsString('{{#expiring_is_trial}}', $default['content']);
	}
}
