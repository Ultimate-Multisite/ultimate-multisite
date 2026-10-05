<?php
/**
 * Regression coverage for pending sites in customer and membership panels.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;
use WP_Ultimo\Models\Site;
use WP_Ultimo\Admin_Pages\Customer_Edit_Admin_Page;

defined('ABSPATH') || exit;

class Pending_Site_Isolation_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		unset($_REQUEST['page'], $_REQUEST['id'], $_REQUEST['type']);
		remove_all_filters('wu_customers_site_list_table_get_items');
		parent::tear_down();
	}

	private function customer() {
		$name     = uniqid('pending_');
		$customer = wu_create_customer([
			'username' => $name,
			'email'    => $name . '@example.invalid',
			'password' => wp_generate_password(),
		]);
		$this->assertNotWPError($customer);
		return $customer;
	}

	private function membership($customer, $title = null) {
		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'status'      => 'pending',
		]);
		$this->assertNotWPError($membership);
		if ($title) {
			$membership->create_pending_site([
				'title'  => $title,
				'domain' => uniqid('pending-') . '.example.invalid',
				'path'   => '/',
			]);
		}
		return $membership;
	}

	private function customer_table($customer) {
		$_REQUEST['page'] = 'wp-ultimo-edit-customer';
		$_REQUEST['id']   = $customer->get_id();
		$page             = new Customer_Edit_Admin_Page();
		$page->object     = $customer;
		$table            = new Customers_Site_List_Table();
		add_filter('wu_' . $table->get_table_id() . '_get_items', [$page, 'sites_query_filter']);
		return $table;
	}

	public function test_customer_panel_excludes_unrelated_pending_sites_and_counts_once(): void {
		$owner = $this->customer();
		$other = $this->customer();
		$this->membership($owner, 'Owned pending');
		$this->membership($other, 'Unrelated pending');
		$table = $this->customer_table($owner);

		$items = $table->get_items(5);
		$this->assertCount(1, $items);
		$this->assertSame('Owned pending', $items[0]->get_title());
		$this->assertSame(1, $table->get_items(5, 1, true));
		$this->assertSame([], $table->get_items(1, 2));
		ob_start();
		$table->column_responsive($items[0]);
		$html = ob_get_clean();
		$this->assertStringContainsString('Owned pending', $html);
		$this->assertStringNotContainsString('Unrelated pending', $html);
	}

	public function test_customer_without_memberships_has_no_pending_sites(): void {
		$this->membership($this->customer(), 'Unrelated pending');
		$customer = $this->customer();
		$table    = $this->customer_table($customer);

		$this->assertSame([], Site::get_all_by_type('pending', ['customer_id' => $customer->get_id()]));
		$this->assertSame([], $table->get_items());
		$this->assertSame(0, $table->get_items(5, 1, true));
	}

	public function test_customer_with_membership_but_no_pending_site_has_empty_panel(): void {
		$this->membership($this->customer(), 'Unrelated pending');
		$customer = $this->customer();
		$this->membership($customer);
		$table = $this->customer_table($customer);

		$this->assertSame([], $table->get_items());
		$this->assertSame(0, $table->get_items(5, 1, true));
	}

	public function test_pending_customer_lookup_fails_closed_for_invalid_customer(): void {
		$this->membership($this->customer(), 'Unrelated pending');
		$this->assertSame([], Site::get_all_by_type('pending', ['customer_id' => 0]));
		$this->assertSame([], Site::get_all_by_type('pending', ['customer_id' => -1]));
	}

	public function test_membership_panel_shows_only_its_pending_site(): void {
		$customer   = $this->customer();
		$membership = $this->membership($customer, 'Selected membership');
		$this->membership($customer, 'Other membership');
		$_REQUEST['page'] = 'wp-ultimo-edit-membership';
		$_REQUEST['id']   = $membership->get_id();
		$table            = new Customers_Site_List_Table();
		add_filter('wu_' . $table->get_table_id() . '_get_items', function ($query) use ($membership) {
			$query['membership_id'] = $membership->get_id();
			return $query;
		});
		$items = $table->get_items();
		$this->assertCount(1, $items);
		$this->assertSame('Selected membership', $items[0]->get_title());
		$this->assertSame(1, $table->get_items(5, 1, true));
	}

	public function test_pending_sites_are_paginated_and_network_view_stays_unrestricted(): void {
		$customer = $this->customer();
		$this->membership($customer, 'First pending');
		$this->membership($customer, 'Second pending');
		$this->membership($this->customer(), 'Other pending');
		$table            = $this->customer_table($customer);
		$_REQUEST['type'] = 'pending';
		$this->assertSame(2, $table->get_items(1, 1, true));
		$this->assertCount(1, $table->get_items(1, 1));
		$this->assertCount(1, $table->get_items(1, 2));
		$this->assertNotSame($table->get_items(1, 1)[0]->get_title(), $table->get_items(1, 2)[0]->get_title());
		$this->assertSame([], $table->get_items(1, 3));
		$network = new Site_List_Table();
		$this->assertSame(3, $network->get_items(5, 1, true));
	}

	public function test_mixed_pending_and_published_sites_share_pagination(): void {
		$customer = $this->customer();
		$this->membership($customer, 'Owned pending');
		$this->membership($this->customer(), 'Unrelated pending');
		$blog_id = self::factory()->blog->create();
		update_site_meta($blog_id, Site::META_CUSTOMER_ID, $customer->get_id());
		$table = $this->customer_table($customer);

		$this->assertSame(2, $table->get_items(1, 1, true));
		$this->assertSame('Owned pending', $table->get_items(1, 1)[0]->get_title());
		$this->assertSame($blog_id, $table->get_items(1, 2)[0]->get_id());
		$this->assertSame([], $table->get_items(1, 3));
		$this->assertCount(2, $table->get_items(5));
	}

	public function test_pending_lookup_includes_memberships_beyond_default_query_limit(): void {
		$customer = $this->customer();
		$this->membership($customer, 'Oldest pending');
		for ($i = 0; $i < 25; $i++) {
			$this->membership($customer);
		}
		$this->membership($this->customer(), 'Unrelated pending');
		$sites = Site::get_all_by_type('pending', ['customer_id' => $customer->get_id()]);
		$this->assertCount(1, $sites);
		$this->assertSame('Oldest pending', $sites[0]->get_title());
	}

	public function test_customer_panel_without_context_does_not_show_network_pending_sites(): void {
		$this->membership($this->customer(), 'Unrelated pending');
		$_REQUEST['type'] = 'pending';
		$table            = new Customers_Site_List_Table();
		$this->assertSame([], $table->get_items());
		$this->assertSame(0, $table->get_items(5, 1, true));
	}
}
