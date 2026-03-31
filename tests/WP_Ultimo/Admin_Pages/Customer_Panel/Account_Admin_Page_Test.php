<?php
/**
 * Tests for Account_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages\Customer_Panel;

use WP_UnitTestCase;

/**
 * Test class for Account_Admin_Page.
 */
class Account_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Account_Admin_Page
	 */
	private $page;

	/**
	 * @var \WP_Ultimo\Models\Site
	 */
	private $site;

	/**
	 * @var \WP_Ultimo\Models\Customer
	 */
	private $customer;

	/**
	 * @var \WP_Ultimo\Models\Membership
	 */
	private $membership;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		// Create a customer with user.
		$unique   = wp_rand(1000, 9999);
		$email    = 'testcustomer' . $unique . '@example.com';
		$username = 'testcustomer' . $unique;

		$this->customer = wu_create_customer(
			[
				'username' => $username,
				'email'    => $email,
				'password' => 'password123',
			]
		);

		if (is_wp_error($this->customer)) {
			$user = get_user_by('email', $email);
			if ($user) {
				$this->customer = wu_get_customer_by_user_id($user->ID);
			}

			if (!$this->customer || is_wp_error($this->customer)) {
				$this->fail('Could not create test customer');
			}
		}

		// Create a membership.
		$this->membership = wu_create_membership(
			[
				'customer_id'      => $this->customer->get_id(),
				'plan_id'          => 0,
				'amount'           => 10,
				'billing_duration' => 1,
				'billing_freq'     => 'month',
				'currency'         => 'USD',
			]
		);

		if (is_wp_error($this->membership)) {
			$this->fail('Could not create test membership');
		}

		// Create a site.
		$site_id = $this->factory->blog->create(
			[
				'user_id' => $this->customer->get_user_id(),
			]
		);

		$this->site = wu_create_site(
			[
				'blog_id'       => $site_id,
				'customer_id'   => $this->customer->get_id(),
				'membership_id' => $this->membership->get_id(),
				'type'          => 'customer_owned',
			]
		);

		if (is_wp_error($this->site)) {
			$this->fail('Could not create test site');
		}

		// Switch to the customer's site.
		switch_to_blog($site_id);

		// Mock wu_get_current_site to return our test site.
		add_filter(
			'wu_get_current_site',
			function () {
				return $this->site;
			}
		);

		$this->page = new Account_Admin_Page();
	}

	/**
	 * Tear down: restore blog and clean up.
	 */
	protected function tearDown(): void {

		restore_current_blog();

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	/**
	 * Test page id is correct.
	 */
	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('account', $property->getValue($this->page));
	}

	/**
	 * Test position is correct.
	 */
	public function test_position(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('position');
		$property->setAccessible(true);

		$this->assertEquals(101_010_101, $property->getValue($this->page));
	}

	/**
	 * Test menu_icon is correct.
	 */
	public function test_menu_icon(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('menu_icon');
		$property->setAccessible(true);

		$this->assertEquals('dashicons-wu-email', $property->getValue($this->page));
	}

	/**
	 * Test badge_count is zero.
	 */
	public function test_badge_count(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);

		$this->assertEquals(0, $property->getValue($this->page));
	}

	/**
	 * Test hide_admin_notices is true.
	 */
	public function test_hide_admin_notices(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('hide_admin_notices');
		$property->setAccessible(true);

		$this->assertTrue($property->getValue($this->page));
	}

	/**
	 * Test supported_panels contains admin_menu and user_admin_menu.
	 */
	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('admin_menu', $panels);
		$this->assertArrayHasKey('user_admin_menu', $panels);
		$this->assertEquals('wu_manage_membership', $panels['admin_menu']);
		$this->assertEquals('wu_manage_membership', $panels['user_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * get_title returns 'Account'.
	 */
	public function test_get_title(): void {

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Account', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns 'Account'.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Account', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_submenu_title returns 'Account'.
	 */
	public function test_get_submenu_title(): void {

		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Account', $title);
	}

	// -------------------------------------------------------------------------
	// page_loaded()
	// -------------------------------------------------------------------------

	/**
	 * page_loaded sets current_site and current_membership.
	 */
	public function test_page_loaded(): void {

		$this->page->page_loaded();

		$reflection = new \ReflectionClass($this->page);

		$site_property = $reflection->getProperty('current_site');
		$site_property->setAccessible(true);
		$this->assertInstanceOf(\WP_Ultimo\Models\Site::class, $site_property->getValue($this->page));

		$membership_property = $reflection->getProperty('current_membership');
		$membership_property->setAccessible(true);
		$this->assertInstanceOf(\WP_Ultimo\Models\Membership::class, $membership_property->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// hooks()
	// -------------------------------------------------------------------------

	/**
	 * hooks() does not throw.
	 */
	public function test_hooks_does_not_throw(): void {

		$this->page->hooks();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// screen_options()
	// -------------------------------------------------------------------------

	/**
	 * screen_options() does not throw.
	 */
	public function test_screen_options_does_not_throw(): void {

		$this->page->screen_options();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * register_widgets() does not throw when called with a valid screen.
	 */
	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * output() renders without throwing.
	 */
	public function test_output_renders(): void {

		set_current_screen('dashboard');

		ob_start();
		$this->page->output();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// add_notices()
	// -------------------------------------------------------------------------

	/**
	 * add_notices() with no updated query param does nothing.
	 */
	public function test_add_notices_no_update_param(): void {

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('add_notices');
		$method->setAccessible(true);

		$method->invoke($this->page);

		$this->assertTrue(true);
	}

	/**
	 * add_notices() with payment_method update type adds notice.
	 */
	public function test_add_notices_payment_method_update(): void {

		$_GET['updated'] = 'payment_method';

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('add_notices');
		$method->setAccessible(true);

		$method->invoke($this->page);

		$notices = \WP_Ultimo()->notices->get_notices('admin');
		$this->assertNotEmpty($notices);

		unset($_GET['updated']);
	}

	/**
	 * add_notices() with generic update type adds notice.
	 */
	public function test_add_notices_generic_update(): void {

		$_GET['updated'] = 'profile';

		$reflection = new \ReflectionClass($this->page);
		$method     = $reflection->getMethod('add_notices');
		$method->setAccessible(true);

		$method->invoke($this->page);

		$notices = \WP_Ultimo()->notices->get_notices('admin');
		$this->assertNotEmpty($notices);

		unset($_GET['updated']);
	}
}
