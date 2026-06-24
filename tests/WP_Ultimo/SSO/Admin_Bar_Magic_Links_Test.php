<?php
/**
 * Tests for Admin_Bar_Magic_Links class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\SSO;

use WP_UnitTestCase;

/**
 * Test class for Admin_Bar_Magic_Links.
 */
class Admin_Bar_Magic_Links_Test extends WP_UnitTestCase {

	/**
	 * @var Admin_Bar_Magic_Links
	 */
	private $magic_links;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->magic_links = Admin_Bar_Magic_Links::get_instance();
	}

	/**
	 * Test get_instance returns an Admin_Bar_Magic_Links instance.
	 */
	public function test_get_instance_returns_instance(): void {
		$this->assertInstanceOf(Admin_Bar_Magic_Links::class, $this->magic_links);
	}

	/**
	 * Test get_instance returns the same instance (singleton).
	 */
	public function test_get_instance_is_singleton(): void {
		$instance1 = Admin_Bar_Magic_Links::get_instance();
		$instance2 = Admin_Bar_Magic_Links::get_instance();

		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test modify_my_sites_menu does not throw when user is not logged in.
	 */
	public function test_modify_my_sites_menu_no_user(): void {
		// Ensure no user is logged in.
		wp_set_current_user(0);

		if ( ! class_exists('\WP_Admin_Bar') ) {
			require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		}

		$admin_bar = new \WP_Admin_Bar();
		$this->magic_links->modify_my_sites_menu($admin_bar);

		$this->assertTrue(true); // No exception thrown.
	}
}
