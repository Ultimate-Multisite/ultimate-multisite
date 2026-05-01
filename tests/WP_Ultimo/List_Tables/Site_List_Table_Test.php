<?php
/**
 * Tests for Site_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Site_List_Table.
 *
 * @group list-tables
 */
class Site_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Site_List_Table
	 */
	private Site_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Site_List_Table();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		unset( $_REQUEST['type'] );
		remove_all_filters( 'wu_site_list_get_bulk_actions' );

		parent::tear_down();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Site', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Sites', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'featured_image_id', $columns );
		$this->assertArrayHasKey( 'path', $columns );
		$this->assertArrayHasKey( 'type', $columns );
		$this->assertArrayHasKey( 'customer', $columns );
		$this->assertArrayHasKey( 'membership', $columns );
		$this->assertArrayHasKey( 'domains', $columns );
		$this->assertArrayHasKey( 'blog_id', $columns );
	}

	/**
	 * Test get_columns returns 8 columns.
	 */
	public function test_get_columns_returns_eight_columns(): void {

		$columns = $this->table->get_columns();

		$this->assertCount( 8, $columns );
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
	 * Test get_filters returns correct structure.
	 */
	public function test_get_filters_returns_correct_structure(): void {

		$filters = $this->table->get_filters();

		$this->assertIsArray( $filters );
		$this->assertArrayHasKey( 'filters', $filters );
		$this->assertArrayHasKey( 'date_filters', $filters );
	}

	/**
	 * Test get_filters has vip filter.
	 */
	public function test_get_filters_has_vip_filter(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'vip', $filters['filters'] );
	}

	// =========================================================================
	// get_views()
	// =========================================================================

	/**
	 * Test get_views returns expected type keys.
	 */
	public function test_get_views_returns_expected_keys(): void {

		$views = $this->table->get_views();

		$this->assertArrayHasKey( 'all', $views );
		$this->assertArrayHasKey( 'customer_owned', $views );
		$this->assertArrayHasKey( 'site_template', $views );
		$this->assertArrayHasKey( 'pending', $views );
		$this->assertArrayHasKey( 'demo', $views );
	}

	// =========================================================================
	// get_bulk_actions()
	// =========================================================================

	/**
	 * Test get_bulk_actions returns screenshot and delete actions.
	 */
	public function test_get_bulk_actions_returns_screenshot_and_delete(): void {

		$actions = $this->table->get_bulk_actions();

		$this->assertIsArray( $actions );
		$this->assertArrayHasKey( 'screenshot', $actions );
	}

	/**
	 * Test get_bulk_actions applies filter.
	 */
	public function test_get_bulk_actions_applies_filter(): void {

		add_filter(
			'wu_site_list_get_bulk_actions',
			function ( $actions ) {
				$actions['custom_action'] = 'Custom';
				return $actions;
			}
		);

		$actions = $this->table->get_bulk_actions();

		$this->assertArrayHasKey( 'custom_action', $actions );

		remove_all_filters( 'wu_site_list_get_bulk_actions' );
	}

	// =========================================================================
	// column_cb()
	// =========================================================================

	/**
	 * Test column_cb returns checkbox with blog_id for non-pending site.
	 */
	public function test_column_cb_returns_checkbox_with_blog_id(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_id', 'get_membership_id' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'customer_owned' );
		$item->method( 'get_id' )->willReturn( 3 );

		$output = $this->table->column_cb( $item );

		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( '3', $output );
	}

	/**
	 * Test column_cb returns checkbox with membership_id for pending site in the
	 * dedicated Pending view (type=pending).
	 */
	public function test_column_cb_returns_membership_id_for_pending_site_in_pending_view(): void {

		$_REQUEST['type'] = 'pending';

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_id', 'get_membership_id' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'pending' );
		$item->method( 'get_id' )->willReturn( 3 );
		$item->method( 'get_membership_id' )->willReturn( 7 );

		$output = $this->table->column_cb( $item );

		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( '7', $output );
	}

	/**
	 * Test column_cb returns no checkbox for pending site in the All Sites view,
	 * because bulk-delete uses blog IDs and pending sites only have membership IDs.
	 */
	public function test_column_cb_returns_empty_for_pending_site_in_all_view(): void {

		$_REQUEST['type'] = 'all';

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_id', 'get_membership_id' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'pending' );
		$item->method( 'get_id' )->willReturn( 3 );
		$item->method( 'get_membership_id' )->willReturn( 7 );

		$output = $this->table->column_cb( $item );

		$this->assertSame( '', $output );
	}

	/**
	 * Test column_cb returns no checkbox for pending site when no type filter is
	 * set (equivalent to the All Sites default view).
	 */
	public function test_column_cb_returns_empty_for_pending_site_with_no_type(): void {

		unset( $_REQUEST['type'] );

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_id', 'get_membership_id' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'pending' );
		$item->method( 'get_id' )->willReturn( 3 );

		$output = $this->table->column_cb( $item );

		$this->assertSame( '', $output );
	}

	// =========================================================================
	// column_type()
	// =========================================================================

	/**
	 * Test column_type returns span with label and class.
	 */
	public function test_column_type_returns_span_with_label(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type_label', 'get_type_class' ] )
			->getMock();
		$item->method( 'get_type_label' )->willReturn( 'Customer Owned' );
		$item->method( 'get_type_class' )->willReturn( 'wu-bg-blue-200' );

		$output = $this->table->column_type( $item );

		$this->assertStringContainsString( 'Customer Owned', $output );
		$this->assertStringContainsString( 'wu-bg-blue-200', $output );
		$this->assertStringContainsString( '<span', $output );
	}

	// =========================================================================
	// column_blog_id()
	// =========================================================================

	/**
	 * Test column_blog_id returns '--' for pending site.
	 */
	public function test_column_blog_id_returns_dash_for_pending(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_blog_id' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( \WP_Ultimo\Database\Sites\Site_Type::PENDING );

		$output = $this->table->column_blog_id( $item );

		$this->assertEquals( '--', $output );
	}

	/**
	 * Test column_blog_id returns blog_id for non-pending site.
	 */
	public function test_column_blog_id_returns_blog_id_for_non_pending(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_blog_id' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'customer_owned' );
		$item->method( 'get_blog_id' )->willReturn( 5 );

		$output = $this->table->column_blog_id( $item );

		$this->assertEquals( 5, $output );
	}

	// =========================================================================
	// get_items()
	// =========================================================================

	/**
	 * Test get_items returns array.
	 */
	public function test_get_items_returns_array(): void {

		$result = $this->table->get_items( 5, 1, false );

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_items with count=true returns numeric.
	 */
	public function test_get_items_count_returns_numeric(): void {

		$result = $this->table->get_items( 5, 1, true );

		$this->assertIsNumeric( $result );
	}
}
