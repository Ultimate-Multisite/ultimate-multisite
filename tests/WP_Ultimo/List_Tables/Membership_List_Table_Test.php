<?php
/**
 * Tests for Membership_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Membership_List_Table.
 *
 * @group list-tables
 */
class Membership_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Membership_List_Table
	 */
	private Membership_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Membership_List_Table();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		unset( $_REQUEST['customer_id'] );
		remove_all_filters( 'wu_memberships_list_table_columns' );

		parent::tear_down();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Membership', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Memberships', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'hash', $columns );
		$this->assertArrayHasKey( 'status', $columns );
		$this->assertArrayHasKey( 'customer', $columns );
		$this->assertArrayHasKey( 'product', $columns );
		$this->assertArrayHasKey( 'amount', $columns );
		$this->assertArrayHasKey( 'date_created', $columns );
		$this->assertArrayHasKey( 'date_expiration', $columns );
		$this->assertArrayHasKey( 'id', $columns );
	}

	/**
	 * Test get_columns applies filter.
	 */
	public function test_get_columns_applies_filter(): void {

		add_filter(
			'wu_memberships_list_table_columns',
			function ( $columns ) {
				$columns['extra_col'] = 'Extra';
				return $columns;
			}
		);

		$columns = $this->table->get_columns();

		$this->assertArrayHasKey( 'extra_col', $columns );

		remove_all_filters( 'wu_memberships_list_table_columns' );
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
	 * Test get_filters has status filter.
	 */
	public function test_get_filters_has_status_filter(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'status', $filters['filters'] );
	}

	/**
	 * Test get_filters has date_created, date_expiration, date_renewed date filters.
	 */
	public function test_get_filters_has_date_filters(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'date_created', $filters['date_filters'] );
		$this->assertArrayHasKey( 'date_expiration', $filters['date_filters'] );
		$this->assertArrayHasKey( 'date_renewed', $filters['date_filters'] );
	}

	// =========================================================================
	// get_views()
	// =========================================================================

	/**
	 * Test get_views returns expected status keys.
	 */
	public function test_get_views_returns_expected_keys(): void {

		$views = $this->table->get_views();

		$this->assertArrayHasKey( 'all', $views );
		$this->assertArrayHasKey( 'active', $views );
		$this->assertArrayHasKey( 'trialing', $views );
		$this->assertArrayHasKey( 'pending', $views );
		$this->assertArrayHasKey( 'on-hold', $views );
		$this->assertArrayHasKey( 'expired', $views );
		$this->assertArrayHasKey( 'cancelled', $views );
	}

	// =========================================================================
	// get_extra_query_fields()
	// =========================================================================

	/**
	 * Test get_extra_query_fields includes customer_id.
	 */
	public function test_get_extra_query_fields_includes_customer_id(): void {

		$_REQUEST['customer_id'] = '3';

		$fields = $this->table->get_extra_query_fields();

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'customer_id', $fields );

		unset( $_REQUEST['customer_id'] );
	}

	// =========================================================================
	// column_status()
	// =========================================================================

	/**
	 * Test column_status returns span with label and class.
	 */
	public function test_column_status_returns_span_with_label(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_status_label', 'get_status_class' ] )
			->getMock();
		$item->method( 'get_status_label' )->willReturn( 'Active' );
		$item->method( 'get_status_class' )->willReturn( 'wu-bg-green-500' );

		$output = $this->table->column_status( $item );

		$this->assertStringContainsString( 'Active', $output );
		$this->assertStringContainsString( 'wu-bg-green-500', $output );
		$this->assertStringContainsString( '<span', $output );
	}

	// =========================================================================
	// column_amount()
	// =========================================================================

	/**
	 * Test column_amount returns 'Free' for zero amount.
	 */
	public function test_column_amount_returns_free_for_zero_amount(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_amount', 'get_initial_amount', 'is_recurring' ] )
			->getMock();
		$item->method( 'get_amount' )->willReturn( 0 );
		$item->method( 'get_initial_amount' )->willReturn( 0 );

		$output = $this->table->column_amount( $item );

		$this->assertStringContainsString( 'Free', $output );
	}

	/**
	 * Test column_amount returns 'one time payment' for non-recurring.
	 */
	public function test_column_amount_returns_one_time_for_non_recurring(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [
				'get_amount',
				'get_initial_amount',
				'is_recurring',
				'get_currency',
			] )
			->getMock();
		$item->method( 'get_amount' )->willReturn( 0 );
		$item->method( 'get_initial_amount' )->willReturn( 50 );
		$item->method( 'is_recurring' )->willReturn( false );
		$item->method( 'get_currency' )->willReturn( 'USD' );

		$output = $this->table->column_amount( $item );

		$this->assertStringContainsString( 'one time payment', $output );
	}

	// =========================================================================
	// column_date_expiration()
	// =========================================================================

	/**
	 * Test column_date_expiration returns 'Lifetime' for empty date.
	 */
	public function test_column_date_expiration_returns_lifetime_for_empty_date(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_date_expiration' ] )
			->getMock();
		$item->method( 'get_date_expiration' )->willReturn( '' );

		$output = $this->table->column_date_expiration( $item );

		$this->assertStringContainsString( 'Lifetime', $output );
	}

	/**
	 * Test column_date_expiration returns 'Lifetime' for zero date.
	 */
	public function test_column_date_expiration_returns_lifetime_for_zero_date(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_date_expiration' ] )
			->getMock();
		$item->method( 'get_date_expiration' )->willReturn( '0000-00-00 00:00:00' );

		$output = $this->table->column_date_expiration( $item );

		$this->assertStringContainsString( 'Lifetime', $output );
	}

	/**
	 * Test column_date_expiration returns datetime HTML for valid date.
	 */
	public function test_column_date_expiration_returns_datetime_for_valid_date(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_date_expiration' ] )
			->getMock();
		$item->method( 'get_date_expiration' )->willReturn( '2099-12-31 00:00:00' );

		$output = $this->table->column_date_expiration( $item );

		$this->assertStringContainsString( '<span', $output );
	}
}
