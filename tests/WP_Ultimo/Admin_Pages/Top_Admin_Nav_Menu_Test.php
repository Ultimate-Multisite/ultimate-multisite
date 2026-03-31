<?php
/**
 * Tests for Top_Admin_Nav_Menu class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test class for Top_Admin_Nav_Menu.
 *
 * Tests all public methods of the Top_Admin_Nav_Menu class.
 */
class Top_Admin_Nav_Menu_Test extends WP_UnitTestCase {

	/**
	 * @var Top_Admin_Nav_Menu
	 */
	private $menu;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->menu = new Top_Admin_Nav_Menu();
	}

	// -------------------------------------------------------------------------
	// __construct()
	// -------------------------------------------------------------------------

	/**
	 * Constructor registers admin_bar_menu action.
	 */
	public function test_constructor_registers_admin_bar_menu_action(): void {

		$menu = new Top_Admin_Nav_Menu();

		$this->assertGreaterThan(
			0,
			has_action('admin_bar_menu', [$menu, 'add_top_bar_menus'])
		);
	}

	/**
	 * Constructor registers action with priority 50.
	 */
	public function test_constructor_registers_action_with_priority_50(): void {

		$menu = new Top_Admin_Nav_Menu();

		$this->assertEquals(
			50,
			has_action('admin_bar_menu', [$menu, 'add_top_bar_menus'])
		);
	}

	// -------------------------------------------------------------------------
	// add_top_bar_menus()
	// -------------------------------------------------------------------------

	/**
	 * add_top_bar_menus returns early when user lacks manage_network capability.
	 */
	public function test_add_top_bar_menus_returns_early_without_capability(): void {

		wp_set_current_user(0);

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$wp_admin_bar->expects($this->never())
			->method('add_node');

		$this->menu->add_top_bar_menus($wp_admin_bar);
	}

	/**
	 * add_top_bar_menus adds parent node when user has capability.
	 */
	public function test_add_top_bar_menus_adds_parent_node(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$wp_admin_bar->expects($this->atLeastOnce())
			->method('add_node')
			->with(
				$this->callback(
					function ($node) {

						return isset($node['id']) && 'wp-ultimo' === $node['id'];
					}
				)
			);

		$this->menu->add_top_bar_menus($wp_admin_bar);
	}

	/**
	 * add_top_bar_menus adds sites node when user has wu_read_sites capability.
	 */
	public function test_add_top_bar_menus_adds_sites_node_with_capability(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		// Grant wu_read_sites capability.
		$user = wp_get_current_user();
		$user->add_cap('wu_read_sites');

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$added_nodes = [];

		$wp_admin_bar->expects($this->atLeastOnce())
			->method('add_node')
			->willReturnCallback(
				function ($node) use (&$added_nodes) {

					$added_nodes[] = $node['id'];
				}
			);

		$this->menu->add_top_bar_menus($wp_admin_bar);

		$this->assertContains('wp-ultimo-sites', $added_nodes);
	}

	/**
	 * add_top_bar_menus adds memberships node when user has wu_read_memberships capability.
	 */
	public function test_add_top_bar_menus_adds_memberships_node_with_capability(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$user = wp_get_current_user();
		$user->add_cap('wu_read_memberships');

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$added_nodes = [];

		$wp_admin_bar->expects($this->atLeastOnce())
			->method('add_node')
			->willReturnCallback(
				function ($node) use (&$added_nodes) {

					$added_nodes[] = $node['id'];
				}
			);

		$this->menu->add_top_bar_menus($wp_admin_bar);

		$this->assertContains('wp-ultimo-memberships', $added_nodes);
	}

	/**
	 * add_top_bar_menus adds customers node when user has wu_read_customers capability.
	 */
	public function test_add_top_bar_menus_adds_customers_node_with_capability(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$user = wp_get_current_user();
		$user->add_cap('wu_read_customers');

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$added_nodes = [];

		$wp_admin_bar->expects($this->atLeastOnce())
			->method('add_node')
			->willReturnCallback(
				function ($node) use (&$added_nodes) {

					$added_nodes[] = $node['id'];
				}
			);

		$this->menu->add_top_bar_menus($wp_admin_bar);

		$this->assertContains('wp-ultimo-customers', $added_nodes);
	}

	/**
	 * add_top_bar_menus adds products node when user has wu_read_products capability.
	 */
	public function test_add_top_bar_menus_adds_products_node_with_capability(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$user = wp_get_current_user();
		$user->add_cap('wu_read_products');

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$added_nodes = [];

		$wp_admin_bar->expects($this->atLeastOnce())
			->method('add_node')
			->willReturnCallback(
				function ($node) use (&$added_nodes) {

					$added_nodes[] = $node['id'];
				}
			);

		$this->menu->add_top_bar_menus($wp_admin_bar);

		$this->assertContains('wp-ultimo-products', $added_nodes);
	}

	/**
	 * add_top_bar_menus adds payments node when user has wu_read_payments capability.
	 */
	public function test_add_top_bar_menus_adds_payments_node_with_capability(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$user = wp_get_current_user();
		$user->add_cap('wu_read_payments');

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$added_nodes = [];

		$wp_admin_bar->expects($this->atLeastOnce())
			->method('add_node')
			->willReturnCallback(
				function ($node) use (&$added_nodes) {

					$added_nodes[] = $node['id'];
				}
			);

		$this->menu->add_top_bar_menus($wp_admin_bar);

		$this->assertContains('wp-ultimo-payments', $added_nodes);
	}

	/**
	 * add_top_bar_menus adds discount codes node when user has wu_read_discount_codes capability.
	 */
	public function test_add_top_bar_menus_adds_discount_codes_node_with_capability(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$user = wp_get_current_user();
		$user->add_cap('wu_read_discount_codes');

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$added_nodes = [];

		$wp_admin_bar->expects($this->atLeastOnce())
			->method('add_node')
			->willReturnCallback(
				function ($node) use (&$added_nodes) {

					$added_nodes[] = $node['id'];
				}
			);

		$this->menu->add_top_bar_menus($wp_admin_bar);

		$this->assertContains('wp-ultimo-discount-codes', $added_nodes);
	}

	/**
	 * add_top_bar_menus adds settings node when user has wu_read_settings capability.
	 */
	public function test_add_top_bar_menus_adds_settings_node_with_capability(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$user = wp_get_current_user();
		$user->add_cap('wu_read_settings');

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$added_nodes = [];

		$wp_admin_bar->expects($this->atLeastOnce())
			->method('add_node')
			->willReturnCallback(
				function ($node) use (&$added_nodes) {

					$added_nodes[] = $node['id'];
				}
			);

		$this->menu->add_top_bar_menus($wp_admin_bar);

		$this->assertContains('wp-ultimo-settings', $added_nodes);
	}

	/**
	 * add_top_bar_menus does not add sites node without capability.
	 */
	public function test_add_top_bar_menus_does_not_add_sites_without_capability(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$wp_admin_bar = $this->getMockBuilder(\WP_Admin_Bar::class)
			->disableOriginalConstructor()
			->getMock();

		$added_nodes = [];

		$wp_admin_bar->expects($this->atLeastOnce())
			->method('add_node')
			->willReturnCallback(
				function ($node) use (&$added_nodes) {

					$added_nodes[] = $node['id'];
				}
			);

		$this->menu->add_top_bar_menus($wp_admin_bar);

		$this->assertNotContains('wp-ultimo-sites', $added_nodes);
	}
}
