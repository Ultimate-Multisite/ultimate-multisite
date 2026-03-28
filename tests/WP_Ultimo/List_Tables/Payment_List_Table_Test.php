<?php
/**
 * Tests for Payment_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;
use WP_Ultimo\Database\Payments\Payment_Status;

/**
 * Test class for Payment_List_Table.
 *
 * @group list-tables
 */
class Payment_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Payment_List_Table
	 */
	private Payment_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Payment_List_Table();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		unset( $_REQUEST['membership_id'], $_REQUEST['customer_id'] );
		remove_all_filters( 'wu_payments_list_table_columns' );

		parent::tear_down();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Payment', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Payments', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'membership', $columns );
		$this->assertArrayHasKey( 'total', $columns );
		$this->assertArrayHasKey( 'date_created', $columns );
		$this->assertArrayHasKey( 'id', $columns );
	}

	/**
	 * Test get_columns applies filter.
	 */
	public function test_get_columns_applies_filter(): void {

		add_filter(
			'wu_payments_list_table_columns',
			function ( $columns ) {
				$columns['extra_col'] = 'Extra';
				return $columns;
			}
		);

		$columns = $this->table->get_columns();

		$this->assertArrayHasKey( 'extra_col', $columns );

		remove_all_filters( 'wu_payments_list_table_columns' );
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
	 * Test get_filters has status and gateway filters.
	 */
	public function test_get_filters_has_status_and_gateway(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'status', $filters['filters'] );
		$this->assertArrayHasKey( 'gateway', $filters['filters'] );
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
	 * Test get_views returns expected status keys.
	 */
	public function test_get_views_returns_expected_keys(): void {

		$views = $this->table->get_views();

		$this->assertArrayHasKey( 'all', $views );
		$this->assertArrayHasKey( Payment_Status::COMPLETED, $views );
		$this->assertArrayHasKey( Payment_Status::PENDING, $views );
		$this->assertArrayHasKey( Payment_Status::REFUND, $views );
		$this->assertArrayHasKey( Payment_Status::FAILED, $views );
	}

	// =========================================================================
	// get_extra_query_fields()
	// =========================================================================

	/**
	 * Test get_extra_query_fields includes membership_id and customer_id.
	 */
	public function test_get_extra_query_fields_includes_membership_and_customer_id(): void {

		$fields = $this->table->get_extra_query_fields();

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'membership_id', $fields );
		$this->assertArrayHasKey( 'customer_id', $fields );
	}

	/**
	 * Test get_extra_query_fields includes parent_id__in filter.
	 */
	public function test_get_extra_query_fields_includes_parent_id_in(): void {

		$fields = $this->table->get_extra_query_fields();

		$this->assertArrayHasKey( 'parent_id__in', $fields );
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
		$item->method( 'get_status_label' )->willReturn( 'Completed' );
		$item->method( 'get_status_class' )->willReturn( 'wu-bg-green-500' );

		$output = $this->table->column_status( $item );

		$this->assertStringContainsString( 'Completed', $output );
		$this->assertStringContainsString( 'wu-bg-green-500', $output );
		$this->assertStringContainsString( '<span', $output );
	}

	// =========================================================================
	// column_total()
	// =========================================================================

	/**
	 * Test column_total returns formatted currency with gateway.
	 */
	public function test_column_total_returns_currency_with_gateway(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_total', 'get_gateway' ] )
			->getMock();
		$item->method( 'get_total' )->willReturn( 99.99 );
		$item->method( 'get_gateway' )->willReturn( 'stripe' );

		$output = $this->table->column_total( $item );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'Stripe', $output );
	}

	// =========================================================================
	// column_product()
	// =========================================================================

	/**
	 * Test column_product returns 'No product found' when no product.
	 */
	public function test_column_product_returns_no_product_when_null(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_product' ] )
			->getMock();
		$item->method( 'get_product' )->willReturn( null );

		$output = $this->table->column_product( $item );

		$this->assertStringContainsString( 'No product found', $output );
	}

	/**
	 * Test column_product returns product name when product exists.
	 */
	public function test_column_product_returns_product_name_when_exists(): void {

		$product = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_id', 'get_name' ] )
			->getMock();
		$product->method( 'get_id' )->willReturn( 1 );
		$product->method( 'get_name' )->willReturn( 'Pro Plan' );

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_product' ] )
			->getMock();
		$item->method( 'get_product' )->willReturn( $product );

		$output = $this->table->column_product( $item );

		$this->assertStringContainsString( 'Pro Plan', $output );
	}
}
