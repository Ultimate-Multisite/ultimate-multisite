<?php
/**
 * Tests for the Stripe webhook processing functionality.
 *
 * Covers:
 * - livemode operator-precedence bug fix (line 2577)
 * - customer.subscription.updated → membership status sync
 * - customer.subscription.deleted → membership cancellation
 * - invoice.payment_failed → payment_failed event
 * - install_webhook detects OAuth token changes
 *
 * @package WP_Ultimo\Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Models\Customer;
use Stripe\StripeClient;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for Stripe webhook processing.
 */
class Stripe_Webhook_Process_Test extends \WP_UnitTestCase {

	/**
	 * @var \WP_Ultimo\Gateways\Stripe_Gateway
	 */
	private $gateway;

	/**
	 * @var MockObject|StripeClient
	 */
	private $stripe_client_mock;

	/**
	 * @var MockObject|\Stripe\Service\EventService
	 */
	private $events_mock;

	/**
	 * @var MockObject|\Stripe\Service\SubscriptionService
	 */
	private $subscriptions_mock;

	/**
	 * @var \WP_Ultimo\Models\Membership
	 */
	private $membership;

	/**
	 * @var Customer
	 */
	private static Customer $customer;

	/**
	 * Set up test customer once for the class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$customer = wu_create_customer(
			[
				'username' => 'webhook_testuser',
				'email'    => 'webhook_test@example.com',
				'password' => 'password123',
			]
		);
	}

	/**
	 * Set up test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a membership for the customer.
		$product = wu_create_product(
			[
				'name'                => 'Webhook Test Plan',
				'slug'                => 'webhook-test-plan-' . uniqid(),
				'amount'              => 29.00,
				'duration'            => 1,
				'duration_unit'       => 'month',
				'trial_duration'      => 0,
				'trial_duration_unit' => 'day',
				'type'                => 'plan',
				'pricing_type'        => 'paid',
				'active'              => true,
			]
		);

		$this->membership = wu_create_membership(
			[
				'customer_id'             => self::$customer->get_id(),
				'plan_id'                 => $product->get_id(),
				'status'                  => Membership_Status::ACTIVE,
				'gateway'                 => 'stripe',
				'gateway_subscription_id' => 'sub_test123',
				'gateway_customer_id'     => 'cus_test123',
				'recurring'               => true,
				'amount'                  => 29.00,
				'currency'                => 'USD',
			]
		);

		// Build Stripe service mocks.
		$this->stripe_client_mock = $this->getMockBuilder(StripeClient::class)
			->disableOriginalConstructor()
			->getMock();

		$this->events_mock = $this->getMockBuilder(\Stripe\Service\EventService::class)
			->disableOriginalConstructor()
			->getMock();

		$this->subscriptions_mock = $this->getMockBuilder(\Stripe\Service\SubscriptionService::class)
			->disableOriginalConstructor()
			->getMock();

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) {
					switch ($property) {
						case 'events':
							return $this->events_mock;
						case 'subscriptions':
							return $this->subscriptions_mock;
						default:
							return null;
					}
				}
			);

		// Create gateway and inject mock client.
		$this->gateway = new \WP_Ultimo\Gateways\Stripe_Gateway();
		$this->gateway->set_stripe_client($this->stripe_client_mock);
	}

	/**
	 * Tear down after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		if ($this->membership) {
			$this->membership->delete();
		}

		parent::tearDown();
	}

	public static function invoice_subscription_shapes(): array {
		$expanded = [
			'id'     => 'sub_test123',
			'object' => 'subscription',
		];
		return [
			'legacy ID'       => [['subscription' => 'sub_test123'], true],
			'legacy expanded' => [['subscription' => $expanded], true],
			'Basil ID'        => [['parent' => ['subscription_details' => ['subscription' => 'sub_test123']]], true],
			'Basil expanded'  => [['parent' => ['subscription_details' => ['subscription' => $expanded]]], true],
			'no reference'    => [[], false],
		];
	}

	/**
	 * @dataProvider invoice_subscription_shapes
	 */
	public function test_invoice_payment_completes_active_membership(array $shape, bool $linked): void {
		$payment = wu_create_payment([
			'customer_id'   => self::$customer->get_id(),
			'membership_id' => $this->membership->get_id(),
			'gateway'       => 'stripe',
			'status'        => 'pending',
			'subtotal'      => 29,
			'total'         => 29,
		]);
		$this->assertNotWPError($payment);
		$this->assertGreaterThan(0, $payment->get_id());

		$this->subscriptions_mock->expects($linked ? $this->once() : $this->never())
			->method('retrieve')
			->with('sub_test123')
			->willReturn($this->make_stripe_subscription());

		$event = $this->make_stripe_event('invoice.payment_succeeded', array_merge([
			'id'          => 'in_active_membership',
			'object'      => 'invoice',
			'customer'    => 'cus_test123',
			'currency'    => 'usd',
			'amount_paid' => 2900,
			'total'       => 2900,
		], $shape));

		try {
			$this->dispatch_webhook($event);
			$this->assertSame($linked ? 'completed' : 'pending', wu_get_payment($payment->get_id())->get_status());
			$this->assertSame(Membership_Status::ACTIVE, wu_get_membership($this->membership->get_id())->get_status());
		} finally {
			$payment->delete();
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a minimal Stripe Event object for testing.
	 *
	 * @param string $type           Stripe event type.
	 * @param array  $data_object    The event data.object attributes.
	 * @param array  $previous_attrs Optional previous_attributes for .updated events.
	 * @param bool   $livemode       Whether this is a live-mode event.
	 * @return \Stripe\Event
	 */
	private function make_stripe_event(string $type, array $data_object, array $previous_attrs = [], bool $livemode = false): \Stripe\Event {
		$event_data = [
			'id'       => 'evt_' . uniqid(),
			'type'     => $type,
			'livemode' => $livemode,
			'data'     => [
				'object' => $data_object,
			],
		];

		if ( ! empty($previous_attrs)) {
			$event_data['data']['previous_attributes'] = $previous_attrs;
		}

		return \Stripe\Event::constructFrom($event_data);
	}

	/**
	 * Build a minimal Stripe Subscription object for testing.
	 *
	 * @param string $status Subscription status.
	 * @param int    $period_end Unix timestamp for current_period_end.
	 * @return \Stripe\Subscription
	 */
	private function make_stripe_subscription(string $status = 'active', int $period_end = 0): \Stripe\Subscription {
		if (0 === $period_end) {
			$period_end = strtotime('+30 days');
		}

		return \Stripe\Subscription::constructFrom(
			[
				'id'     => 'sub_test123',
				'status' => $status,
				'items'  => [
					'object' => 'list',
					'data'   => [
						[
							'id'                 => 'si_test123',
							'current_period_end' => $period_end,
						],
					],
				],
			]
		);
	}

	/**
	 * Simulate process_webhooks() by injecting a raw event payload into php://input
	 * and calling the method directly.
	 *
	 * Because process_webhooks() reads php://input via wu_get_input(), we mock the
	 * events->retrieve() call to return our pre-built event object instead.
	 *
	 * @param \Stripe\Event $event The event to process.
	 * @return void
	 */
	private function dispatch_webhook(\Stripe\Event $event): void {
		// Mock events->retrieve() to return our event.
		$this->events_mock->expects($this->any())
			->method('retrieve')
			->willReturn($event);

		// Stub wu_get_input() to return a minimal object with just the event ID.
		// We use a filter to override the function since it reads php://input.
		$event_id = $event->id;

		add_filter(
			'wu_get_input_override',
			function () use ($event_id) {
				return (object) [
					'id'       => $event_id,
					'livemode' => false,
				];
			}
		);

		try {
			$this->gateway->process_webhooks();
		} finally {
			remove_all_filters('wu_get_input_override');
		}
	}

	// -------------------------------------------------------------------------
	// Bug fix: livemode operator-precedence
	// -------------------------------------------------------------------------

	/**
	 * Test that a live-mode event received while gateway is in test mode
	 * correctly switches test_mode to false before loading API keys.
	 *
	 * Before the fix: `! $received_event->livemode !== $this->test_mode`
	 * was evaluated as `(!livemode) !== test_mode` = `false !== true` = `true`,
	 * so test_mode was always set to `! livemode = false` for live events,
	 * which is correct by accident. But for test events (livemode=false) when
	 * test_mode=false, the condition was `true !== false` = `true`, incorrectly
	 * switching test_mode to true.
	 *
	 * After the fix: `(bool) $received_event->livemode === $this->test_mode`
	 * correctly detects the mismatch and updates test_mode to match the event.
	 *
	 * @return void
	 */
	public function test_livemode_flag_sets_test_mode_correctly_for_live_event(): void {
		// Gateway starts in test mode (default).
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('test_mode');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, true); // test mode ON

		// Build a live-mode event.
		$subscription = $this->make_stripe_subscription('active');
		$event        = $this->make_stripe_event(
			'customer.subscription.updated',
			[
				'id'       => 'sub_test123',
				'object'   => 'subscription',
				'status'   => 'active',
				'customer' => 'cus_test123',
				'items'    => [
					'object' => 'list',
					'data'   => [
						[
							'id'                 => 'si_test123',
							'current_period_end' => strtotime('+30 days'),
						],
					],
				],
			],
			[],
			true // livemode = true
		);

		$this->events_mock->expects($this->any())
			->method('retrieve')
			->willReturn($event);

		$this->subscriptions_mock->expects($this->any())
			->method('retrieve')
			->willReturn($subscription);

		// Simulate receiving the event with livemode=true in the raw payload.
		$event_id = $event->id;

		add_filter(
			'wu_get_input_override',
			function () use ($event_id) {
				return (object) [
					'id'       => $event_id,
					'livemode' => true, // live event
				];
			}
		);

		try {
			// Should not throw — the gateway should switch to live mode.
			$this->gateway->process_webhooks();
		} catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Ignorable exceptions (e.g. no membership found) are acceptable.
		} finally {
			remove_all_filters('wu_get_input_override');
		}

		// After processing, test_mode should be false (live mode).
		$this->assertFalse($prop->getValue($this->gateway), 'test_mode should be false after receiving a live-mode event');
	}

	// -------------------------------------------------------------------------
	// customer.subscription.updated — status changes
	// -------------------------------------------------------------------------

	/**
	 * Test that a subscription transitioning to past_due expires the membership.
	 *
	 * @return void
	 */
	public function test_subscription_updated_past_due_expires_membership(): void {
		$this->assertSame(Membership_Status::ACTIVE, $this->membership->get_status(), 'Membership should start active');

		$subscription = $this->make_stripe_subscription('past_due');

		$event = $this->make_stripe_event(
			'customer.subscription.updated',
			[
				'id'       => 'sub_test123',
				'object'   => 'subscription',
				'status'   => 'past_due',
				'customer' => 'cus_test123',
				'items'    => [
					'object' => 'list',
					'data'   => [
						[
							'id'                 => 'si_test123',
							'current_period_end' => strtotime('+30 days'),
						],
					],
				],
			],
			['status' => 'active'] // previous_attributes
		);

		$this->events_mock->expects($this->any())
			->method('retrieve')
			->willReturn($event);

		$this->subscriptions_mock->expects($this->any())
			->method('retrieve')
			->willReturn($subscription);

		$event_id = $event->id;

		add_filter(
			'wu_get_input_override',
			function () use ($event_id) {
				return (object) [
					'id'       => $event_id,
					'livemode' => false,
				];
			}
		);

		try {
			$this->gateway->process_webhooks();
		} finally {
			remove_all_filters('wu_get_input_override');
		}

		// Reload membership from DB.
		$updated = wu_get_membership($this->membership->get_id());

		$this->assertSame(
			Membership_Status::EXPIRED,
			$updated->get_status(),
			'Membership should be expired after past_due webhook'
		);
	}

	/**
	 * Test that a subscription transitioning to unpaid expires the membership.
	 *
	 * @return void
	 */
	public function test_subscription_updated_unpaid_expires_membership(): void {
		$subscription = $this->make_stripe_subscription('unpaid');

		$event = $this->make_stripe_event(
			'customer.subscription.updated',
			[
				'id'       => 'sub_test123',
				'object'   => 'subscription',
				'status'   => 'unpaid',
				'customer' => 'cus_test123',
				'items'    => [
					'object' => 'list',
					'data'   => [
						[
							'id'                 => 'si_test123',
							'current_period_end' => strtotime('+30 days'),
						],
					],
				],
			],
			['status' => 'past_due']
		);

		$this->events_mock->expects($this->any())
			->method('retrieve')
			->willReturn($event);

		$this->subscriptions_mock->expects($this->any())
			->method('retrieve')
			->willReturn($subscription);

		$event_id = $event->id;

		add_filter(
			'wu_get_input_override',
			function () use ($event_id) {
				return (object) [
					'id'       => $event_id,
					'livemode' => false,
				];
			}
		);

		try {
			$this->gateway->process_webhooks();
		} finally {
			remove_all_filters('wu_get_input_override');
		}

		$updated = wu_get_membership($this->membership->get_id());

		$this->assertSame(
			Membership_Status::EXPIRED,
			$updated->get_status(),
			'Membership should be expired after unpaid webhook'
		);
	}

	/**
	 * Test that a subscription recovering from past_due to active renews the membership.
	 *
	 * @return void
	 */
	public function test_subscription_updated_recovery_to_active_renews_membership(): void {
		// Set membership to expired first (simulating a prior past_due event).
		$this->membership->set_status(Membership_Status::EXPIRED);
		$this->membership->save();

		$period_end   = strtotime('+30 days');
		$subscription = $this->make_stripe_subscription('active', $period_end);

		$event = $this->make_stripe_event(
			'customer.subscription.updated',
			[
				'id'       => 'sub_test123',
				'object'   => 'subscription',
				'status'   => 'active',
				'customer' => 'cus_test123',
				'items'    => [
					'object' => 'list',
					'data'   => [
						[
							'id'                 => 'si_test123',
							'current_period_end' => $period_end,
						],
					],
				],
			],
			['status' => 'past_due']
		);

		$this->events_mock->expects($this->any())
			->method('retrieve')
			->willReturn($event);

		$this->subscriptions_mock->expects($this->any())
			->method('retrieve')
			->willReturn($subscription);

		$event_id = $event->id;

		add_filter(
			'wu_get_input_override',
			function () use ($event_id) {
				return (object) [
					'id'       => $event_id,
					'livemode' => false,
				];
			}
		);

		try {
			$this->gateway->process_webhooks();
		} finally {
			remove_all_filters('wu_get_input_override');
		}

		$updated = wu_get_membership($this->membership->get_id());

		$this->assertSame(
			Membership_Status::ACTIVE,
			$updated->get_status(),
			'Membership should be active after subscription recovery webhook'
		);
	}

	/**
	 * Test that subscription.updated without a status change does not modify membership.
	 *
	 * Routine billing-cycle updates (e.g. current_period_end changes) should not
	 * trigger membership status transitions.
	 *
	 * @return void
	 */
	public function test_subscription_updated_no_status_change_leaves_membership_unchanged(): void {
		$this->assertSame(Membership_Status::ACTIVE, $this->membership->get_status());

		$subscription = $this->make_stripe_subscription('active');

		// previous_attributes does NOT include 'status' — only current_period_end changed.
		$event = $this->make_stripe_event(
			'customer.subscription.updated',
			[
				'id'       => 'sub_test123',
				'object'   => 'subscription',
				'status'   => 'active',
				'customer' => 'cus_test123',
				'items'    => [
					'object' => 'list',
					'data'   => [
						[
							'id'                 => 'si_test123',
							'current_period_end' => strtotime('+30 days'),
						],
					],
				],
			],
			['current_period_end' => strtotime('-1 day')] // only period changed, not status
		);

		$this->events_mock->expects($this->any())
			->method('retrieve')
			->willReturn($event);

		$this->subscriptions_mock->expects($this->any())
			->method('retrieve')
			->willReturn($subscription);

		$event_id = $event->id;

		add_filter(
			'wu_get_input_override',
			function () use ($event_id) {
				return (object) [
					'id'       => $event_id,
					'livemode' => false,
				];
			}
		);

		try {
			$this->gateway->process_webhooks();
		} finally {
			remove_all_filters('wu_get_input_override');
		}

		$updated = wu_get_membership($this->membership->get_id());

		$this->assertSame(
			Membership_Status::ACTIVE,
			$updated->get_status(),
			'Membership status should not change when only non-status fields changed'
		);
	}

	// -------------------------------------------------------------------------
	// customer.subscription.deleted
	// -------------------------------------------------------------------------

	/**
	 * Test that subscription.deleted cancels an active membership.
	 *
	 * @return void
	 */
	public function test_subscription_deleted_cancels_active_membership(): void {
		$this->assertSame(Membership_Status::ACTIVE, $this->membership->get_status());

		$subscription = $this->make_stripe_subscription('canceled');

		$event = $this->make_stripe_event(
			'customer.subscription.deleted',
			[
				'id'       => 'sub_test123',
				'object'   => 'subscription',
				'status'   => 'canceled',
				'customer' => 'cus_test123',
				'items'    => [
					'object' => 'list',
					'data'   => [
						[
							'id'                 => 'si_test123',
							'current_period_end' => strtotime('+30 days'),
						],
					],
				],
			]
		);

		$this->events_mock->expects($this->any())
			->method('retrieve')
			->willReturn($event);

		$this->subscriptions_mock->expects($this->any())
			->method('retrieve')
			->willReturn($subscription);

		$event_id = $event->id;

		add_filter(
			'wu_get_input_override',
			function () use ($event_id) {
				return (object) [
					'id'       => $event_id,
					'livemode' => false,
				];
			}
		);

		try {
			$this->gateway->process_webhooks();
		} finally {
			remove_all_filters('wu_get_input_override');
		}

		$updated = wu_get_membership($this->membership->get_id());

		$this->assertSame(
			Membership_Status::CANCELLED,
			$updated->get_status(),
			'Membership should be cancelled after subscription.deleted webhook'
		);
	}

	// -------------------------------------------------------------------------
	// install_webhook — OAuth token change detection
	// -------------------------------------------------------------------------

	/**
	 * Test that install_webhook fires when an OAuth access token changes.
	 *
	 * Previously, the change-detection array only included direct API keys
	 * (pk_key, sk_key), so OAuth-connected sites never triggered webhook
	 * installation when they connected via Stripe Connect.
	 *
	 * @return void
	 */
	public function test_install_webhook_detects_oauth_token_change(): void {
		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		// Expect all() to be called (checking for existing webhook).
		$webhook_endpoints_mock->expects($this->once())
			->method('all')
			->willReturn(
				\Stripe\Collection::constructFrom(
					[
						'object' => 'list',
						'data'   => [],
					]
				)
			);

		// Expect create() to be called (no existing webhook found).
		$webhook_endpoints_mock->expects($this->once())
			->method('create')
			->with(
				$this->arrayHasKey('url')
			);

		$this->stripe_client_mock = $this->getMockBuilder(StripeClient::class)
			->disableOriginalConstructor()
			->getMock();

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}

					return null;
				}
			);

		$this->gateway = new \WP_Ultimo\Gateways\Stripe_Gateway();
		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$id = 'stripe';

		// Simulate settings where the OAuth access token just changed.
		$settings = [
			"{$id}_sandbox_mode"      => '1',
			"{$id}_test_pk_key"       => '',
			"{$id}_test_sk_key"       => '',
			"{$id}_live_pk_key"       => '',
			"{$id}_live_sk_key"       => '',
			"{$id}_test_access_token" => 'sk_test_NEW_TOKEN',
			"{$id}_live_access_token" => '',
			'active_gateways'         => ['stripe'],
		];

		$saved_settings = [
			"{$id}_sandbox_mode"      => '1',
			"{$id}_test_pk_key"       => '',
			"{$id}_test_sk_key"       => '',
			"{$id}_live_pk_key"       => '',
			"{$id}_live_sk_key"       => '',
			"{$id}_test_access_token" => '', // was empty before
			"{$id}_live_access_token" => '',
		];

		$settings_to_save = $settings;

		// Should NOT return early — the OAuth token changed.
		$this->gateway->install_webhook($settings, $settings_to_save, $saved_settings);

		// PHPUnit will verify that create() was called exactly once (expectation above).
	}

	/**
	 * Test that install_webhook does NOT fire when nothing changed.
	 *
	 * @return void
	 */
	public function test_install_webhook_skips_when_nothing_changed(): void {
		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		// Expect all() to NOT be called (early return before checking).
		$webhook_endpoints_mock->expects($this->never())
			->method('all');

		$this->stripe_client_mock = $this->getMockBuilder(StripeClient::class)
			->disableOriginalConstructor()
			->getMock();

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}

					return null;
				}
			);

		$this->gateway = new \WP_Ultimo\Gateways\Stripe_Gateway();
		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$id = 'stripe';

		// Identical settings — nothing changed.
		$settings = [
			"{$id}_sandbox_mode"      => '1',
			"{$id}_test_pk_key"       => 'pk_test_existing',
			"{$id}_test_sk_key"       => 'sk_test_existing',
			"{$id}_live_pk_key"       => '',
			"{$id}_live_sk_key"       => '',
			"{$id}_test_access_token" => '',
			"{$id}_live_access_token" => '',
			'active_gateways'         => ['stripe'],
		];

		$saved_settings = $settings;

		$this->gateway->install_webhook($settings, $settings, $saved_settings);

		// PHPUnit will verify that all() was never called (expectation above).
	}
}
