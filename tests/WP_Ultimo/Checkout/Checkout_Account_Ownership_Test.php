<?php
/**
 * Checkout account ownership regressions.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Checkout;

class Checkout_Account_Ownership_Test extends \WP_UnitTestCase {

	private $checkout;
	private $reflection;
	private $saved_properties = [];
	private $saved_request;
	private $signup     = [];
	private $auth_users = [];

	public function set_up() {
		parent::set_up();
		wp_set_current_user(0);
		$this->saved_request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve test request state.
		$_REQUEST            = [];
		$this->checkout      = Checkout::get_instance();
		$this->reflection    = new \ReflectionClass($this->checkout);
		foreach (['order', 'session', 'customer', 'created_user_id'] as $name) {
			$this->saved_properties[$name] = $this->reflection->getProperty($name)->getValue($this->checkout);
		}
		$session = $this->createMock(\WP_Ultimo\Contracts\Session::class);
		$session->method('get')->willReturnCallback(function ($key) {
			return 'signup' === $key ? $this->signup : null;
		});
		$this->reflection->getProperty('session')->setValue($this->checkout, $session);
		$this->reflection->getProperty('order')->setValue($this->checkout, new Cart(['products' => []]));
		add_action('set_auth_cookie', [$this, 'capture_auth'], 10, 4);
		add_filter('pre_wp_mail', '__return_true');
	}

	public function tear_down() {
		remove_action('set_auth_cookie', [$this, 'capture_auth'], 10);
		remove_filter('pre_wp_mail', '__return_true');
		foreach ($this->saved_properties as $name => $value) {
			$this->reflection->getProperty($name)->setValue($this->checkout, $value);
		}
		$_REQUEST = $this->saved_request;
		wp_set_current_user(0);
		parent::tear_down();
	}

	public function capture_auth($cookie, $expire, $expiration, $user_id) {
		$this->auth_users[] = $user_id;
	}

	public static function existing_email_cases() {
		return [
			'raw passwordless'   => ['victim@example.org', true, false, false],
			'quoted super admin' => ['"victim"@example.org', true, false, true],
			'quoted password'    => ['"victim"@example.org', false, false, false],
			'quoted session'     => ['"victim"@example.org', true, true, false],
			'quoted customer'    => ['"victim"@example.org', true, true, true],
			'whitespace'         => [' victim@example.org ', true, false, false],
		];
	}

	/** @dataProvider existing_email_cases */
	public function test_existing_email_cannot_create_link_reset_or_login($email, $automatic, $session, $customer_exists) {
		$user_id = self::factory()->user->create([
			'user_login' => 'victim',
			'user_email' => 'victim@example.org',
		]);
		grant_super_admin($user_id);
		$hash     = get_userdata($user_id)->user_pass;
		$customer = $customer_exists ? wu_create_customer(['user_id' => $user_id]) : false;
		$data     = [
			'email_address'          => $email,
			'username'               => 'unrelated_attacker',
			'password'               => $automatic ? '' : 'attacker-password',
			'auto_generate_password' => $automatic,
		];
		if ($session) {
			$this->signup = $data;
		} else {
			$_REQUEST = $data;
		}
		$result = $this->reflection->getMethod('maybe_create_customer')->invoke($this->checkout);
		$this->assertWPError($result);
		$this->assertSame('email_exists', $result->get_error_code());
		$this->assertSame($hash, get_userdata($user_id)->user_pass);
		$this->assertSame(0, $this->reflection->getProperty('created_user_id')->getValue($this->checkout));
		$this->assertSame(0, get_current_user_id());
		$this->assertSame([], $this->auth_users);
		$this->assertFalse(get_user_by('login', 'unrelated_attacker'));
		if ($customer) {
			$this->assertSame($customer->get_id(), wu_get_customer_by_user_id($user_id)->get_id());
		} else {
			$this->assertFalse(wu_get_customer_by_user_id($user_id));
		}
		revoke_super_admin($user_id);
	}

	public function test_customer_helper_rejects_existing_user_after_normalization() {
		$user_id = self::factory()->user->create(['user_email' => 'victim@example.org']);
		$created = 999;
		$result  = wu_create_customer([
			'email'            => '"victim"@example.org',
			'require_new_user' => true,
		], $created);
		$this->assertWPError($result);
		$this->assertSame('email_exists', $result->get_error_code());
		$this->assertSame(0, $created);
		$this->assertFalse(wu_get_customer_by_user_id($user_id));
	}

	public function test_existing_user_link_is_not_reported_as_creation() {
		$user_id  = self::factory()->user->create(['user_email' => 'victim@example.org']);
		$created  = 999;
		$customer = wu_create_customer(['user_id' => $user_id], $created);
		$this->assertNotWPError($customer);
		$this->assertSame(0, $created);
		$this->reflection->getProperty('customer')->setValue($this->checkout, $customer);
		$this->reflection->getProperty('created_user_id')->setValue($this->checkout, $created);
		$result = $this->reflection->getMethod('login_customer_after_checkout')->invoke($this->checkout);
		$this->assertWPError($result);
		$this->assertSame('checkout_login_denied', $result->get_error_code());
		$this->assertSame([], $this->auth_users);
	}

	public function test_authenticated_link_does_not_reset_password() {
		$user_id = self::factory()->user->create();
		$hash    = get_userdata($user_id)->user_pass;
		wp_set_current_user($user_id);
		$_REQUEST = [
			'email_address'          => 'untrusted@example.org',
			'auto_generate_password' => true,
		];
		$customer = $this->reflection->getMethod('maybe_create_customer')->invoke($this->checkout);
		$this->assertNotWPError($customer);
		$this->assertSame($user_id, (int) $customer->get_user_id());
		$this->assertSame($hash, get_userdata($user_id)->user_pass);
		$this->assertSame(0, $this->reflection->getProperty('created_user_id')->getValue($this->checkout));
	}

	public function test_new_passwordless_customer_is_created_and_logged_in() {
		$_REQUEST = [
			'email_address'          => 'new-checkout@example.org',
			'username'               => 'new_checkout',
			'auto_generate_password' => true,
		];
		$customer = $this->reflection->getMethod('maybe_create_customer')->invoke($this->checkout);
		$this->assertNotWPError($customer);
		$user_id = (int) $customer->get_user_id();
		$this->assertSame($user_id, $this->reflection->getProperty('created_user_id')->getValue($this->checkout));
		$this->reflection->getProperty('customer')->setValue($this->checkout, $customer);
		$this->assertNull($this->reflection->getMethod('login_customer_after_checkout')->invoke($this->checkout));
		$this->assertSame([$user_id], $this->auth_users);
	}
}
