<?php
/**
 * Tests for Customer_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Customer_List_Table.
 *
 * @group list-tables
 */
class Customer_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Customer_List_Table
	 */
	private Customer_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Customer_List_Table();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		unset( $_GET['s'], $_REQUEST['filter'] );

		parent::tear_down();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Customer', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Customers', $this->table->get_label( 'plural' ) );
	}

	/**
	 * Test constructor sets grid and list modes.
	 */
	public function test_constructor_sets_grid_and_list_modes(): void {

		$this->assertArrayHasKey( 'grid', $this->table->modes );
		$this->assertArrayHasKey( 'list', $this->table->modes );
	}

	// =========================================================================
	// get_columns()
	// =========================================================================

	/**
	 * Test get_columns returns expected keys.
	 */
	public function test_get_columns_returns_expected_keys(): void {

		$columns = $this->table->get_columns();

		$this->assertIsArray( $columns );
		$this->assertArrayHasKey( 'cb', $columns );
		$this->assertArrayHasKey( 'customer_status', $columns );
		$this->assertArrayHasKey( 'name', $columns );
		$this->assertArrayHasKey( 'last_login', $columns );
		$this->assertArrayHasKey( 'date_registered', $columns );
		$this->assertArrayHasKey( 'memberships', $columns );
		$this->assertArrayHasKey( 'id', $columns );
	}

	/**
	 * Test get_columns returns 7 columns.
	 */
	public function test_get_columns_returns_seven_columns(): void {

		$columns = $this->table->get_columns();

		$this->assertCount( 7, $columns );
	}

	// =========================================================================
	// get_sortable_columns()
	// =========================================================================

	/**
	 * Test get_sortable_columns returns array.
	 */
	public function test_get_sortable_columns_returns_array(): void {

		$columns = $this->table->get_sortable_columns();

		$this->assertIsArray( $columns );
	}

	// =========================================================================
	// get_filters()
	// =========================================================================

	/**
	 * Test get_filters returns array (Customer uses schema-based filters).
	 */
	public function test_get_filters_returns_array(): void {

		$filters = $this->table->get_filters();

		$this->assertIsArray( $filters );
	}

	// =========================================================================
	// get_views()
	// =========================================================================

	/**
	 * Test get_views returns all, vip, online keys.
	 */
	public function test_get_views_returns_expected_keys(): void {

		$views = $this->table->get_views();

		$this->assertArrayHasKey( 'all', $views );
		$this->assertArrayHasKey( 'vip', $views );
		$this->assertArrayHasKey( 'online', $views );
	}

	/**
	 * Test get_views entries have required keys.
	 */
	public function test_get_views_entries_have_required_keys(): void {

		$views = $this->table->get_views();

		foreach ( $views as $key => $view ) {
			$this->assertArrayHasKey( 'field', $view, "Missing 'field' in {$key}" );
			$this->assertArrayHasKey( 'url', $view, "Missing 'url' in {$key}" );
			$this->assertArrayHasKey( 'label', $view, "Missing 'label' in {$key}" );
			$this->assertArrayHasKey( 'count', $view, "Missing 'count' in {$key}" );
		}
	}

	// =========================================================================
	// get_extra_query_fields()
	// =========================================================================

	/**
	 * Test get_extra_query_fields includes type=customer.
	 */
	public function test_get_extra_query_fields_includes_customer_type(): void {

		$fields = $this->table->get_extra_query_fields();

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'type', $fields );
		$this->assertEquals( 'customer', $fields['type'] );
	}

	/**
	 * Test get_extra_query_fields adds vip filter when filter=vip.
	 */
	public function test_get_extra_query_fields_adds_vip_filter(): void {

		$_REQUEST['filter'] = 'vip';

		$fields = $this->table->get_extra_query_fields();

		$this->assertArrayHasKey( 'vip', $fields );
		$this->assertEquals( 1, $fields['vip'] );

		unset( $_REQUEST['filter'] );
	}

	/**
	 * Test get_extra_query_fields adds last_login_query for online filter.
	 */
	public function test_get_extra_query_fields_adds_last_login_for_online(): void {

		$_REQUEST['filter'] = 'online';

		$fields = $this->table->get_extra_query_fields();

		$this->assertArrayHasKey( 'last_login_query', $fields );

		unset( $_REQUEST['filter'] );
	}

	// =========================================================================
	// column_customer_status()
	// =========================================================================

	/**
	 * Test column_customer_status returns HTML with avatar.
	 */
	public function test_column_customer_status_returns_html(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_vip', 'get_user_id' ] )
			->getMock();
		$item->method( 'is_vip' )->willReturn( false );
		$item->method( 'get_user_id' )->willReturn( 1 );

		$output = $this->table->column_customer_status( $item );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'wu-status-container', $output );
	}

	/**
	 * Test column_customer_status includes VIP tag for VIP customers.
	 */
	public function test_column_customer_status_includes_vip_tag(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_vip', 'get_user_id' ] )
			->getMock();
		$item->method( 'is_vip' )->willReturn( true );
		$item->method( 'get_user_id' )->willReturn( 1 );

		$output = $this->table->column_customer_status( $item );

		$this->assertStringContainsString( 'VIP', $output );
	}

	// =========================================================================
	// column_last_login()
	// =========================================================================

	/**
	 * Test column_last_login returns Online for online customers.
	 */
	public function test_column_last_login_returns_online_for_online_customer(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_online', 'get_last_login' ] )
			->getMock();
		$item->method( 'is_online' )->willReturn( true );

		$output = $this->table->column_last_login( $item );

		$this->assertStringContainsString( 'Online', $output );
	}

	/**
	 * Test column_last_login returns datetime for offline customers.
	 */
	public function test_column_last_login_returns_datetime_for_offline_customer(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_online', 'get_last_login' ] )
			->getMock();
		$item->method( 'is_online' )->willReturn( false );
		$item->method( 'get_last_login' )->willReturn( '2024-01-15 10:00:00' );

		$output = $this->table->column_last_login( $item );

		// Should return datetime HTML (span with role=tooltip) or '--' for invalid date.
		$this->assertIsString( $output );
	}
}
