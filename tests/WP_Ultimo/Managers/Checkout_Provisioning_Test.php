<?php
/**
 * Checkout status ownership, readiness and integration regression tests.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */
namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Membership_Manager;
use WP_Ultimo\Tests\Ajax_JSON_Test_Trait;

class Checkout_Provisioning_Test extends \WP_UnitTestCase {

	use Ajax_JSON_Test_Trait;

	private $customer;
	private $membership;
	private $manager;

	public function setUp(): void {
		parent::setUp();
		$user           = self::factory()->user->create(['role' => 'subscriber']);
		$this->customer = wu_create_customer([
			'user_id'            => $user,
			'email_verification' => 'verified',
		]);
		$this->assertFalse(is_wp_error($this->customer));
		$this->membership = wu_create_membership([
			'customer_id'     => $this->customer->get_id(),
			'status'          => 'active',
			'amount'          => 0,
			'currency'        => 'USD',
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);
		$this->assertFalse(is_wp_error($this->membership));
		$this->manager = Membership_Manager::get_instance();
		wp_set_current_user($user);
		$_REQUEST['membership_hash'] = $this->membership->get_hash();
		$_REQUEST['_ajax_nonce']     = wp_create_nonce('wu_check_pending_site_created:' . $this->membership->get_hash());
	}

	public function tearDown(): void {
		$_REQUEST = [];
		wp_set_current_user(0);
		parent::tearDown();
	}

	public function test_owner_can_resolve_status(): void {
		$this->assertSame($this->membership->get_id(), $this->manager->get_authorized_status_membership()->get_id());
	}

	public function test_missing_or_expired_nonce_is_rejected(): void {
		foreach (['', 'expired'] as $nonce) {
			$_REQUEST['_ajax_nonce'] = $nonce;
			$this->assertWPError($this->manager->get_authorized_status_membership());
		}
	}

	public function test_wrong_owner_with_valid_nonce_is_rejected(): void {
		wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));
		$_REQUEST['_ajax_nonce'] = wp_create_nonce('wu_check_pending_site_created:' . $this->membership->get_hash());
		$this->assertWPError($this->manager->get_authorized_status_membership());
	}

	public function test_anonymous_and_array_input_are_rejected(): void {
		wp_set_current_user(0);
		$this->assertWPError($this->manager->get_authorized_status_membership());
		wp_set_current_user($this->customer->get_user_id());
		$_REQUEST['membership_hash'] = ['invalid'];
		$this->assertWPError($this->manager->get_authorized_status_membership());
	}

	public function test_guard_runs_before_addon_handlers(): void {
		$this->assertSame(0, has_action('wp_ajax_wu_check_pending_site_created', [$this->manager, 'authorize_pending_site_status']));
		$this->assertFalse(has_action('wp_ajax_nopriv_wu_check_pending_site_created'));
	}

	public function test_absent_sites_are_not_ready(): void {
		$data = $this->manager->get_provisioning_status($this->membership);
		$this->assertSame('pending', $data['state']);
		$this->assertSame([], $data['sites']);
		$this->assertSame('', $data['redirect_url']);
	}

	public function test_cancelled_membership_is_terminal(): void {
		$this->membership->set_status('cancelled');
		$this->assertSame('failed', $this->manager->get_provisioning_status($this->membership)['state']);
	}

	public function test_payment_and_verification_wait_do_not_activate(): void {
		$this->membership->set_status('pending');
		$this->assertSame('payment_pending', $this->manager->get_provisioning_status($this->membership)['state']);
		$this->assertSame('pending', $this->membership->get_status());
		$this->membership->set_status('active');
		$this->customer->set_email_verification('pending');
		$this->customer->save();
		$this->assertSame('verification_pending', $this->manager->get_provisioning_status($this->membership)['state']);
		$this->assertSame('pending', $this->customer->get_email_verification());
	}

	private function attach_site(): int {
		$id = self::factory()->blog->create();
		update_site_meta($id, 'wu_membership_id', $this->membership->get_id());
		update_site_meta($id, 'wu_clone_status', 'complete');
		return $id;
	}

	public function test_native_ready_links_do_not_redirect_by_default(): void {
		$id   = $this->attach_site();
		$data = $this->manager->get_provisioning_status($this->membership);
		$this->assertSame('ready', $data['state']);
		$this->assertSame('', $data['redirect_url']);
		$this->assertSame($id, $data['sites'][0]['id']);
		$this->assertNotEmpty($data['sites'][0]['admin_url']);
		$this->assertNotEmpty($data['sites'][0]['visit_url']);
	}

	public function test_copying_and_failed_clones_do_not_get_links(): void {
		$id = $this->attach_site();
		update_site_meta($id, 'wu_clone_status', 'copying');
		update_site_meta($id, 'wu_clone_status_started_at', time());
		$this->assertSame('cloning', $this->manager->get_provisioning_status($this->membership)['state']);
		update_site_meta($id, 'wu_clone_status', 'failed');
		$data = $this->manager->get_provisioning_status($this->membership);
		$this->assertSame('failed', $data['state']);
		$this->assertSame([], $data['sites']);
	}

	public function test_integration_redirect_is_ready_origin_bound(): void {
		$id       = $this->attach_site();
		$expected = get_home_url($id, '/welcome/');
		$callback = static fn() => $expected;
		add_filter('wu_checkout_ready_redirect_url', $callback);
		$this->assertSame($expected, $this->manager->get_provisioning_status($this->membership)['redirect_url']);
		remove_filter('wu_checkout_ready_redirect_url', $callback);
		foreach (['https://unrelated.example.test/', 'javascript:alert(1)', str_replace('://', '://user@', $expected)] as $url) {
			$callback = static fn() => $url;
			add_filter('wu_checkout_ready_redirect_url', $callback);
			$this->assertSame('', $this->manager->get_provisioning_status($this->membership)['redirect_url']);
			remove_filter('wu_checkout_ready_redirect_url', $callback);
		}
	}

	public function test_storage_provider_can_hold_readiness(): void {
		$this->attach_site();
		add_filter('wu_site_clone_ready', '__return_false');
		$this->assertSame('cloning', $this->manager->get_provisioning_status($this->membership)['state']);
		remove_filter('wu_site_clone_ready', '__return_false');
	}

	public function test_pending_status_does_not_change_publication_metadata(): void {
		$this->membership->create_pending_site([
			'title' => 'Pending',
			'path'  => '/pending-status/',
		]);
		$before = $this->membership->get_meta('pending_site');
		$this->manager->get_provisioning_status($this->membership);
		$this->assertEquals($before, $this->membership->get_meta('pending_site'));
	}

	public function test_pending_payment_holds_existing_ready_site_until_confirmation(): void {
		$this->attach_site();
		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_status('pending');
		$payment->set_total(10);
		$data = $this->manager->get_provisioning_status($this->membership, $payment);
		$this->assertSame('payment_pending', $data['state']);
		$this->assertSame([], $data['sites']);
		$this->assertSame('pending', $payment->get_status());
		$payment->set_status('completed');
		$this->assertSame('ready', $this->manager->get_provisioning_status($this->membership, $payment)['state']);
		$payment->set_status('failed');
		$this->assertSame('failed', $this->manager->get_provisioning_status($this->membership, $payment)['state']);
	}

	public function test_zero_total_trial_preserves_existing_entitlement_policy(): void {
		$this->attach_site();
		$this->membership->set_status('trialing');
		$payment = new \WP_Ultimo\Models\Payment();
		$payment->set_status('pending');
		$payment->set_total(0);
		$this->assertSame('ready', $this->manager->get_provisioning_status($this->membership, $payment)['state']);
		$payment->set_total(10);
		$this->assertSame('payment_pending', $this->manager->get_provisioning_status($this->membership, $payment)['state']);
	}

	public function test_supplied_payment_must_be_valid_and_bound_to_membership(): void {
		foreach (['unknown', ['invalid']] as $hash) {
			$_REQUEST['payment_hash'] = $hash;
			$this->assertWPError($this->manager->get_authorized_status_membership());
		}
		$payment = wu_create_payment([
			'customer_id'     => $this->customer->get_id(),
			'membership_id'   => $this->membership->get_id() + 999,
			'status'          => 'pending',
			'skip_validation' => true,
		]);
		$this->assertFalse(is_wp_error($payment));
		$_REQUEST['payment_hash'] = $payment->get_hash();
		$this->assertWPError($this->manager->get_authorized_status_membership());
	}

	public function test_unauthorized_request_never_reaches_addon_status_handler(): void {
		$_REQUEST['_ajax_nonce'] = 'expired';

		$called   = false;
		$callback = static function () use (&$called): void {
			$called = true;
		};
		add_action('wp_ajax_wu_check_pending_site_created', $callback, 1);
		$response = $this->capture_ajax_json_response(static function (): void {
			do_action('wp_ajax_wu_check_pending_site_created');
		});
		remove_action('wp_ajax_wu_check_pending_site_created', $callback, 1);
		$this->assertSame('forbidden', $response['decoded']['state']);
		$this->assertFalse($called);
	}

	public function test_completion_preserves_checkout_skip_output_filter(): void {
		$_REQUEST['payment'] = 'synthetic';
		$_REQUEST['status']  = 'done';

		$called   = false;
		$callback = static function () use (&$called): bool {
			$called = true;
			return true;
		};
		add_filter('wu_checkout_skip_output', $callback);
		$html = \WP_Ultimo\UI\Checkout_Element::get_instance()->get_content(['slug' => 'synthetic']);
		remove_filter('wu_checkout_skip_output', $callback);
		$this->assertTrue($called);
		$this->assertSame('', $html);
	}
}
