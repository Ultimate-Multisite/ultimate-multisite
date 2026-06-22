<?php
/**
 * Regression tests for two production incidents in the checkout flow:
 *
 *  BUG 4 — Pending site stuck / infinite provisioning overlay.
 *          A site whose `is_publishing` flag was left `true` by a killed
 *          PHP process (OOM / timeout / server restart) blocked every retry,
 *          so the front-end overlay polled forever. The fix treats a
 *          publishing flag older than the timeout as *stale* so the AJAX
 *          poller (`Membership_Manager::check_pending_site_created()`)
 *          resets it and answers `stopped`, unblocking re-publishing.
 *
 *          @see https://github.com/Ultimate-Multisite/ultimate-multisite/pull/1267
 *
 *  BUG 5 — Subdomain callback under wildcard DNS → half-built site.
 *          With no host-provider integration hooked to `wu_add_subdomain`,
 *          enqueuing the async job ran it with zero callbacks ("no calls
 *          registered") and aborted site creation, leaving a half-built
 *          subsite (real case: Eva, blog 347). The fix guards the enqueue
 *          with `has_action('wu_add_subdomain')`.
 *
 *          @since 2.12.1
 *
 * These tests EXECUTE the real manager methods and assert on the resulting
 * behaviour (JSON status, reset flag, scheduled actions) — not on source
 * text — so a refactor that breaks the behaviour turns CI red.
 *
 * @package WP_Ultimo
 */

namespace WP_Ultimo\Managers;

defined('ABSPATH') || exit;

use WP_UnitTestCase;
use WP_Ultimo\Models\Site;
use WP_Ultimo\Tests\Ajax_JSON_Test_Trait;

/**
 * Regression coverage for the pending-site stale-flag reset (BUG 4) and the
 * subdomain enqueue guard (BUG 5).
 */
class Regression_Pending_Site_And_Subdomain_Test extends WP_UnitTestCase {

	use Ajax_JSON_Test_Trait;

	/**
	 * Membership manager under test (BUG 4).
	 *
	 * @var Membership_Manager
	 */
	private Membership_Manager $membership_manager;

	/**
	 * Domain manager under test (BUG 5).
	 *
	 * @var Domain_Manager
	 */
	private Domain_Manager $domain_manager;

	/**
	 * Customer that owns the test membership.
	 *
	 * @var \WP_Ultimo\Models\Customer
	 */
	private $customer;

	/**
	 * Set up managers and a customer before each test.
	 */
	public function setUp(): void {

		parent::setUp();

		if ( ! defined('UPLOADBLOGSDIR')) {
			define('UPLOADBLOGSDIR', 'wp-content/blogs.dir');
		}

		$this->membership_manager = Membership_Manager::get_instance();
		$this->domain_manager     = Domain_Manager::get_instance();

		$uid = uniqid('reg_');

		$this->customer = wu_create_customer(
			[
				'username' => $uid,
				'email'    => $uid . '@example.com',
				'password' => 'password123',
			]
		);
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Create a membership with a pending site whose is_publishing flag was
	 * recorded at $started_at (a Unix timestamp), simulating a publish that
	 * began that long ago.
	 *
	 * @param int $started_at Unix timestamp to record as publishing_started_at.
	 * @return \WP_Ultimo\Models\Membership
	 */
	private function create_membership_with_publishing_pending_site(int $started_at) {

		$product = wu_create_product(
			[
				'name'          => 'Plan',
				'slug'          => 'reg-plan-' . uniqid(),
				'amount'        => 50.00,
				'type'          => 'plan',
				'active'        => true,
				'pricing_type'  => 'paid',
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		$membership = wu_create_membership(
			[
				'customer_id' => $this->customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'pending',
				'recurring'   => true,
			]
		);

		$pending_site = $membership->create_pending_site(
			[
				'title'  => 'Pending Reg Site',
				'domain' => 'pending-reg-' . uniqid() . '.example.com',
				'path'   => '/',
			]
		);

		// Mark it publishing, then overwrite the timestamp via reflection to
		// simulate a publish that started $started_at seconds ago.
		$pending_site->set_publishing(true);

		$reflection = new \ReflectionProperty(Site::class, 'publishing_started_at');
		$reflection->setAccessible(true);
		$reflection->setValue($pending_site, $started_at);

		$membership->update_pending_site($pending_site);

		return $membership;
	}

	// =========================================================================
	// BUG 4 — stale publishing flag unblocks the overlay
	// Exercises: Membership_Manager::check_pending_site_created()
	// which wires Site::is_publishing_stale()
	// =========================================================================

	/**
	 * A publishing flag older than the 300s timeout is STALE: the poller must
	 * reset is_publishing and answer 'stopped' so the overlay stops looping.
	 *
	 * Real case: provisioning overlay spinning forever after the PHP worker
	 * that was creating the subsite was killed mid-flight.
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/pull/1267
	 */
	public function test_stale_publishing_flag_is_reset_and_returns_stopped(): void {

		$membership = $this->create_membership_with_publishing_pending_site(time() - 600);

		// Precondition: the pending site really is stale at the model level.
		$this->assertTrue(
			$membership->get_pending_site()->is_publishing_stale(),
			'Precondition: a 10-minute-old publishing flag must be stale.'
		);

		$_REQUEST['membership_hash'] = $membership->get_hash();
		$_GET['membership_hash']     = $membership->get_hash();

		$response = $this->capture_ajax_json_response(
			function () {
				$this->membership_manager->check_pending_site_created();
			}
		);
		$payload  = $response['decoded'];

		unset($_REQUEST['membership_hash'], $_GET['membership_hash']);

		$this->assertSame(
			'stopped',
			$payload['publish_status'] ?? null,
			'A stale publishing flag must yield publish_status=stopped.'
		);

		// The flag must have been actually reset in storage so a retry can run.
		$refreshed = wu_get_membership($membership->get_id());
		$this->assertFalse(
			(bool) $refreshed->get_pending_site()->is_publishing(),
			'check_pending_site_created() must reset the stale is_publishing flag.'
		);
	}

	/**
	 * A FRESH publishing flag (just started) is NOT stale: the poller must
	 * leave it alone and answer 'running' so the overlay keeps waiting.
	 *
	 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/pull/1267
	 */
	public function test_fresh_publishing_flag_returns_running_and_is_preserved(): void {

		$membership = $this->create_membership_with_publishing_pending_site(time());

		$this->assertFalse(
			$membership->get_pending_site()->is_publishing_stale(),
			'Precondition: a just-started publishing flag must NOT be stale.'
		);

		$_REQUEST['membership_hash'] = $membership->get_hash();
		$_GET['membership_hash']     = $membership->get_hash();

		$response = $this->capture_ajax_json_response(
			function () {
				$this->membership_manager->check_pending_site_created();
			}
		);
		$payload  = $response['decoded'];

		unset($_REQUEST['membership_hash'], $_GET['membership_hash']);

		$this->assertSame(
			'running',
			$payload['publish_status'] ?? null,
			'A fresh publishing flag must yield publish_status=running.'
		);

		$refreshed = wu_get_membership($membership->get_id());
		$this->assertTrue(
			(bool) $refreshed->get_pending_site()->is_publishing(),
			'A fresh publishing flag must be preserved (not reset).'
		);
	}

	// =========================================================================
	// BUG 5 — subdomain enqueue guarded by has_action('wu_add_subdomain')
	// Exercises: Domain_Manager::handle_site_created()
	// =========================================================================

	/**
	 * With NO listener hooked to wu_add_subdomain, handle_site_created() must
	 * NOT enqueue the async job (which would otherwise run with zero callbacks,
	 * fail with "no calls registered" and abort site creation).
	 *
	 * Real case: Eva, blog 347 — half-built subsite under wildcard DNS.
	 *
	 * @since 2.12.1
	 */
	public function test_site_created_does_not_enqueue_subdomain_without_listener(): void {
		global $current_site;

		$unique_sub = 'reg-no-listener-' . uniqid('', false) . '.' . $current_site->domain;

		$prior_callbacks = $GLOBALS['wp_filter']['wu_add_subdomain'] ?? null;
		unset($GLOBALS['wp_filter']['wu_add_subdomain']);

		try {
			$blog_id = self::factory()->blog->create(['domain' => $unique_sub]);

			if (is_wp_error($blog_id)) {
				$this->markTestSkipped('Could not create test blog: ' . $blog_id->get_error_message());
			}

			$site = get_blog_details($blog_id);

			$this->assertFalse(
				has_action('wu_add_subdomain'),
				'Precondition: wu_add_subdomain must have no listeners.'
			);

			$this->domain_manager->handle_site_created($site);

			$matching = wu_get_scheduled_actions(
				[
					'hook'     => 'wu_add_subdomain',
					'status'   => \ActionScheduler_Store::STATUS_PENDING,
					'args'     => [
						'subdomain' => $site->domain,
						'site_id'   => $site->blog_id,
					],
					'per_page' => 5,
				],
				'ids'
			);

			$this->assertEmpty(
				$matching,
				'wu_add_subdomain must NOT be enqueued when no host provider is listening.'
			);
		} finally {
			if (null !== $prior_callbacks) {
				$GLOBALS['wp_filter']['wu_add_subdomain'] = $prior_callbacks;
			}
		}
	}

	/**
	 * With a listener hooked to wu_add_subdomain, handle_site_created() MUST
	 * enqueue the async job (the host-provider integration is present).
	 *
	 * @since 2.12.1
	 */
	public function test_site_created_enqueues_subdomain_with_listener(): void {
		global $current_site;

		$callback = function () {
			// no-op listener — its presence is what we are testing.
		};

		$prior_callbacks = $GLOBALS['wp_filter']['wu_add_subdomain'] ?? null;
		unset($GLOBALS['wp_filter']['wu_add_subdomain']);

		try {
			$unique_sub = 'reg-with-listener-' . uniqid('', false) . '.' . $current_site->domain;
			$blog_id    = self::factory()->blog->create(['domain' => $unique_sub]);

			if (is_wp_error($blog_id)) {
				$this->markTestSkipped('Could not create test blog: ' . $blog_id->get_error_message());
			}

			$site = get_blog_details($blog_id);

			wu_unschedule_all_actions('wu_add_subdomain');
			add_action('wu_add_subdomain', $callback);

			$this->domain_manager->handle_site_created($site);

			$matching = wu_get_scheduled_actions(
				[
					'hook'     => 'wu_add_subdomain',
					'status'   => \ActionScheduler_Store::STATUS_PENDING,
					'args'     => [
						'subdomain' => $site->domain,
						'site_id'   => $site->blog_id,
					],
					'per_page' => 5,
				],
				'ids'
			);

			$this->assertNotEmpty(
				$matching,
				'wu_add_subdomain must be enqueued when a host-provider integration is listening.'
			);
		} finally {
			remove_action('wu_add_subdomain', $callback);
			if (null !== $prior_callbacks) {
				$GLOBALS['wp_filter']['wu_add_subdomain'] = $prior_callbacks;
			}
			wu_unschedule_all_actions('wu_add_subdomain');
		}
	}

	/**
	 * Clean up test data after each test.
	 */
	public function tearDown(): void {

		global $wpdb;

		if ($this->customer) {
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}wu_memberships WHERE customer_id = %d", $this->customer->get_id()));
			$this->customer->delete();
		}

		parent::tearDown();
	}
}
