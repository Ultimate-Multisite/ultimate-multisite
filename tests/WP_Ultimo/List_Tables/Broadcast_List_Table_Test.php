<?php
/**
 * Tests for Broadcast_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Broadcast_List_Table.
 *
 * @group list-tables
 */
class Broadcast_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Broadcast_List_Table
	 */
	private Broadcast_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Broadcast_List_Table();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		unset( $_REQUEST['type'], $_REQUEST['status'] );

		parent::tear_down();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Broadcast', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Broadcasts', $this->table->get_label( 'plural' ) );
	}

	// =========================================================================
	// get_columns()
	// =========================================================================

	/**
	 * Test get_columns returns array with expected keys.
	 */
	public function test_get_columns_returns_expected_keys(): void {

		$columns = $this->table->get_columns();

		$this->assertIsArray( $columns );
		$this->assertArrayHasKey( 'cb', $columns );
		$this->assertArrayHasKey( 'type', $columns );
		$this->assertArrayHasKey( 'the_content', $columns );
		$this->assertArrayHasKey( 'target_customers', $columns );
		$this->assertArrayHasKey( 'target_products', $columns );
		$this->assertArrayHasKey( 'date_created', $columns );
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
	// column_cb()
	// =========================================================================

	/**
	 * Test column_cb returns disabled checkbox for broadcast_email type.
	 */
	public function test_column_cb_returns_disabled_for_broadcast_email(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_id' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'broadcast_email' );
		$item->method( 'get_id' )->willReturn( 1 );

		$output = $this->table->column_cb( $item );

		$this->assertStringContainsString( 'disabled', $output );
	}

	/**
	 * Test column_cb returns normal checkbox for broadcast_notice type.
	 */
	public function test_column_cb_returns_normal_for_broadcast_notice(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_id' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'broadcast_notice' );
		$item->method( 'get_id' )->willReturn( 5 );

		$output = $this->table->column_cb( $item );

		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringNotContainsString( 'disabled', $output );
		$this->assertStringContainsString( '5', $output );
	}

	// =========================================================================
	// column_type()
	// =========================================================================

	/**
	 * Test column_type returns Email label for broadcast_email.
	 */
	public function test_column_type_returns_email_label_for_broadcast_email(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_notice_type' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'broadcast_email' );

		$output = $this->table->column_type( $item );

		$this->assertStringContainsString( 'Email', $output );
	}

	/**
	 * Test column_type returns Notice label for broadcast_notice.
	 */
	public function test_column_type_returns_notice_label_for_broadcast_notice(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_notice_type' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'broadcast_notice' );
		$item->method( 'get_notice_type' )->willReturn( 'info' );

		$output = $this->table->column_type( $item );

		$this->assertStringContainsString( 'Notice', $output );
	}

	/**
	 * Test column_type applies correct CSS class for info notice.
	 */
	public function test_column_type_applies_blue_class_for_info_notice(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_notice_type' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'broadcast_notice' );
		$item->method( 'get_notice_type' )->willReturn( 'info' );

		$output = $this->table->column_type( $item );

		$this->assertStringContainsString( 'wu-bg-blue-200', $output );
	}

	/**
	 * Test column_type applies correct CSS class for success notice.
	 */
	public function test_column_type_applies_green_class_for_success_notice(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_notice_type' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'broadcast_notice' );
		$item->method( 'get_notice_type' )->willReturn( 'success' );

		$output = $this->table->column_type( $item );

		$this->assertStringContainsString( 'wu-bg-green-200', $output );
	}

	/**
	 * Test column_type applies correct CSS class for warning notice.
	 */
	public function test_column_type_applies_orange_class_for_warning_notice(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_notice_type' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'broadcast_notice' );
		$item->method( 'get_notice_type' )->willReturn( 'warning' );

		$output = $this->table->column_type( $item );

		$this->assertStringContainsString( 'wu-bg-orange-200', $output );
	}

	/**
	 * Test column_type applies correct CSS class for error notice.
	 */
	public function test_column_type_applies_red_class_for_error_notice(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type', 'get_notice_type' ] )
			->getMock();
		$item->method( 'get_type' )->willReturn( 'broadcast_notice' );
		$item->method( 'get_notice_type' )->willReturn( 'error' );

		$output = $this->table->column_type( $item );

		$this->assertStringContainsString( 'wu-bg-red-200', $output );
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
	 * Test get_filters has type and status filters.
	 */
	public function test_get_filters_has_type_and_status(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'type', $filters['filters'] );
		$this->assertArrayHasKey( 'status', $filters['filters'] );
	}

	/**
	 * Test get_filters has date_created date filter.
	 */
	public function test_get_filters_has_date_created_date_filter(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'date_created', $filters['date_filters'] );
	}

	// =========================================================================
	// get_views()
	// =========================================================================

	/**
	 * Test get_views returns all, broadcast_email, broadcast_notice keys.
	 */
	public function test_get_views_returns_expected_keys(): void {

		$views = $this->table->get_views();

		$this->assertArrayHasKey( 'all', $views );
		$this->assertArrayHasKey( 'broadcast_email', $views );
		$this->assertArrayHasKey( 'broadcast_notice', $views );
	}

	/**
	 * Test get_views each entry has required keys.
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
	// column_the_content()
	// =========================================================================

	/**
	 * Test column_the_content returns string with title.
	 */
	public function test_column_the_content_returns_string_with_title(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_id', 'get_slug', 'get_title', 'get_content' ] )
			->getMock();
		$item->method( 'get_id' )->willReturn( 1 );
		$item->method( 'get_slug' )->willReturn( 'test-broadcast' );
		$item->method( 'get_title' )->willReturn( 'Test Broadcast Title' );
		$item->method( 'get_content' )->willReturn( 'Some broadcast content here.' );

		$output = $this->table->column_the_content( $item );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'Test Broadcast Title', $output );
	}
}
