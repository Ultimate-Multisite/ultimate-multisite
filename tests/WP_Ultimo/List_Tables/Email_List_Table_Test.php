<?php
/**
 * Tests for Email_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Email_List_Table.
 *
 * @group list-tables
 */
class Email_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Email_List_Table
	 */
	private Email_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Email_List_Table();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		unset( $_REQUEST['s'], $_REQUEST['target'] );

		parent::tear_down();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Email', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Emails', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'title', $columns );
		$this->assertArrayHasKey( 'slug', $columns );
		$this->assertArrayHasKey( 'event', $columns );
		$this->assertArrayHasKey( 'schedule', $columns );
		$this->assertArrayHasKey( 'id', $columns );
	}

	/**
	 * Test get_columns returns 6 columns.
	 */
	public function test_get_columns_returns_six_columns(): void {

		$columns = $this->table->get_columns();

		$this->assertCount( 6, $columns );
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
	 * Test get_filters has type filter.
	 */
	public function test_get_filters_has_type_filter(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'type', $filters['filters'] );
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
	 * Test get_views returns all, admin, customer keys.
	 */
	public function test_get_views_returns_expected_keys(): void {

		$views = $this->table->get_views();

		$this->assertArrayHasKey( 'all', $views );
		$this->assertArrayHasKey( 'admin', $views );
		$this->assertArrayHasKey( 'customer', $views );
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
	// column_event()
	// =========================================================================

	/**
	 * Test column_event returns event slug in span.
	 */
	public function test_column_event_returns_event_in_span(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_event' ] )
			->getMock();
		$item->method( 'get_event' )->willReturn( 'membership_created' );

		$output = $this->table->column_event( $item );

		$this->assertStringContainsString( 'membership_created', $output );
		$this->assertStringContainsString( '<span', $output );
		$this->assertStringContainsString( 'wu-font-mono', $output );
	}

	// =========================================================================
	// column_slug()
	// =========================================================================

	/**
	 * Test column_slug returns slug in span.
	 */
	public function test_column_slug_returns_slug_in_span(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_slug' ] )
			->getMock();
		$item->method( 'get_slug' )->willReturn( 'welcome-email' );

		$output = $this->table->column_slug( $item );

		$this->assertStringContainsString( 'welcome-email', $output );
		$this->assertStringContainsString( '<span', $output );
	}

	// =========================================================================
	// column_schedule()
	// =========================================================================

	/**
	 * Test column_schedule returns immediate text when no schedule.
	 */
	public function test_column_schedule_returns_immediate_when_no_schedule(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'has_schedule', 'get_schedule_type', 'get_send_hours', 'get_send_days' ] )
			->getMock();
		$item->method( 'has_schedule' )->willReturn( false );

		$output = $this->table->column_schedule( $item );

		$this->assertStringContainsString( 'immediately', $output );
	}

	/**
	 * Test column_schedule returns hours text for hours schedule type.
	 */
	public function test_column_schedule_returns_hours_text_for_hours_type(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'has_schedule', 'get_schedule_type', 'get_send_hours', 'get_send_days' ] )
			->getMock();
		$item->method( 'has_schedule' )->willReturn( true );
		$item->method( 'get_schedule_type' )->willReturn( 'hours' );
		$item->method( 'get_send_hours' )->willReturn( '2:30' );

		$output = $this->table->column_schedule( $item );

		$this->assertStringContainsString( 'hour', $output );
	}

	/**
	 * Test column_schedule returns days text for days schedule type.
	 */
	public function test_column_schedule_returns_days_text_for_days_type(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'has_schedule', 'get_schedule_type', 'get_send_hours', 'get_send_days' ] )
			->getMock();
		$item->method( 'has_schedule' )->willReturn( true );
		$item->method( 'get_schedule_type' )->willReturn( 'days' );
		$item->method( 'get_send_days' )->willReturn( 3 );

		$output = $this->table->column_schedule( $item );

		$this->assertStringContainsString( 'day', $output );
		$this->assertStringContainsString( '3', $output );
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
