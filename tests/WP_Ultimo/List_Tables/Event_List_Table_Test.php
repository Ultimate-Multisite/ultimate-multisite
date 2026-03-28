<?php
/**
 * Tests for Event_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Event_List_Table.
 *
 * @group list-tables
 */
class Event_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Event_List_Table
	 */
	private Event_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Event_List_Table();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Event', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Events', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'initiator', $columns );
		$this->assertArrayHasKey( 'message', $columns );
		$this->assertArrayHasKey( 'slug', $columns );
		$this->assertArrayHasKey( 'object_type', $columns );
		$this->assertArrayHasKey( 'date_created', $columns );
		$this->assertArrayHasKey( 'id', $columns );
	}

	/**
	 * Test get_columns applies filter.
	 */
	public function test_get_columns_applies_filter(): void {

		add_filter(
			'wu_events_list_table_get_columns',
			function ( $columns ) {
				$columns['custom_col'] = 'Custom';
				return $columns;
			}
		);

		$columns = $this->table->get_columns();

		$this->assertArrayHasKey( 'custom_col', $columns );

		remove_all_filters( 'wu_events_list_table_get_columns' );
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
	 * Test get_filters has severity filter.
	 */
	public function test_get_filters_has_severity_filter(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'severity', $filters['filters'] );
	}

	/**
	 * Test get_filters has date_created date filter.
	 */
	public function test_get_filters_has_date_created_date_filter(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'date_created', $filters['date_filters'] );
	}

	// =========================================================================
	// column_object_type()
	// =========================================================================

	/**
	 * Test column_object_type returns object type in span.
	 */
	public function test_column_object_type_returns_type_in_span(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_object_type' ] )
			->getMock();
		$item->method( 'get_object_type' )->willReturn( 'membership' );

		$output = $this->table->column_object_type( $item );

		$this->assertStringContainsString( 'membership', $output );
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
		$item->method( 'get_slug' )->willReturn( 'membership.created' );

		$output = $this->table->column_slug( $item );

		$this->assertStringContainsString( 'membership.created', $output );
		$this->assertStringContainsString( '<span', $output );
	}

	// =========================================================================
	// column_initiator() — system initiator
	// =========================================================================

	/**
	 * Test column_initiator returns HTML for system initiator.
	 */
	public function test_column_initiator_returns_html_for_system_initiator(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [
				'get_initiator',
				'get_severity_label',
				'get_severity_class',
				'get_author_id',
				'get_author_display_name',
				'get_author_email_address',
			] )
			->getMock();
		$item->method( 'get_initiator' )->willReturn( 'system' );
		$item->method( 'get_severity_label' )->willReturn( 'Info' );
		$item->method( 'get_severity_class' )->willReturn( 'wu-bg-blue-200' );

		$output = $this->table->column_initiator( $item );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'System', $output );
	}

	/**
	 * Test column_initiator returns HTML for unknown initiator.
	 */
	public function test_column_initiator_returns_not_found_for_unknown_initiator(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [
				'get_initiator',
				'get_severity_label',
				'get_severity_class',
				'get_author_id',
				'get_author_display_name',
				'get_author_email_address',
			] )
			->getMock();
		$item->method( 'get_initiator' )->willReturn( 'unknown_type' );
		$item->method( 'get_severity_label' )->willReturn( 'Info' );
		$item->method( 'get_severity_class' )->willReturn( '' );

		$output = $this->table->column_initiator( $item );

		$this->assertStringContainsString( 'No initiator found', $output );
	}

	// =========================================================================
	// column_message()
	// =========================================================================

	/**
	 * Test column_message returns trimmed message.
	 */
	public function test_column_message_returns_trimmed_message(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_id', 'get_message' ] )
			->getMock();
		$item->method( 'get_id' )->willReturn( 1 );
		$item->method( 'get_message' )->willReturn( 'A membership was created for the customer.' );

		$output = $this->table->column_message( $item );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'membership', $output );
	}
}
