<?php
/**
 * Tests for Product_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Product_List_Table.
 *
 * @group list-tables
 */
class Product_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Product_List_Table
	 */
	private Product_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Product_List_Table();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Product', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Products', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'name', $columns );
		$this->assertArrayHasKey( 'type', $columns );
		$this->assertArrayHasKey( 'slug', $columns );
		$this->assertArrayHasKey( 'amount', $columns );
		$this->assertArrayHasKey( 'setup_fee', $columns );
		$this->assertArrayHasKey( 'id', $columns );
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
	}

	// =========================================================================
	// get_views()
	// =========================================================================

	/**
	 * Test get_views returns all, plan, package, service keys.
	 */
	public function test_get_views_returns_expected_keys(): void {

		$views = $this->table->get_views();

		$this->assertArrayHasKey( 'all', $views );
		$this->assertArrayHasKey( 'plan', $views );
		$this->assertArrayHasKey( 'package', $views );
		$this->assertArrayHasKey( 'service', $views );
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
	// column_type()
	// =========================================================================

	/**
	 * Test column_type returns span with label and class.
	 */
	public function test_column_type_returns_span_with_label(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_type_label', 'get_type_class' ] )
			->getMock();
		$item->method( 'get_type_label' )->willReturn( 'Plan' );
		$item->method( 'get_type_class' )->willReturn( 'wu-bg-blue-200' );

		$output = $this->table->column_type( $item );

		$this->assertStringContainsString( 'Plan', $output );
		$this->assertStringContainsString( 'wu-bg-blue-200', $output );
		$this->assertStringContainsString( '<span', $output );
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
		$item->method( 'get_slug' )->willReturn( 'pro-plan' );

		$output = $this->table->column_slug( $item );

		$this->assertStringContainsString( 'pro-plan', $output );
		$this->assertStringContainsString( '<span', $output );
		$this->assertStringContainsString( 'wu-font-mono', $output );
	}

	// =========================================================================
	// column_amount()
	// =========================================================================

	/**
	 * Test column_amount returns 'Free' for zero amount.
	 */
	public function test_column_amount_returns_free_for_zero_amount(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_pricing_type', 'get_amount', 'is_recurring' ] )
			->getMock();
		$item->method( 'get_pricing_type' )->willReturn( 'paid' );
		$item->method( 'get_amount' )->willReturn( 0 );

		$output = $this->table->column_amount( $item );

		$this->assertStringContainsString( 'Free', $output );
	}

	/**
	 * Test column_amount returns 'None' for contact_us pricing type.
	 */
	public function test_column_amount_returns_none_for_contact_us(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_pricing_type', 'get_amount', 'is_recurring' ] )
			->getMock();
		$item->method( 'get_pricing_type' )->willReturn( 'contact_us' );

		$output = $this->table->column_amount( $item );

		$this->assertStringContainsString( 'None', $output );
		$this->assertStringContainsString( 'contact', $output );
	}

	/**
	 * Test column_amount returns 'one time payment' for non-recurring.
	 */
	public function test_column_amount_returns_one_time_for_non_recurring(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_pricing_type', 'get_amount', 'is_recurring', 'get_currency' ] )
			->getMock();
		$item->method( 'get_pricing_type' )->willReturn( 'paid' );
		$item->method( 'get_amount' )->willReturn( 50 );
		$item->method( 'is_recurring' )->willReturn( false );
		$item->method( 'get_currency' )->willReturn( 'USD' );

		$output = $this->table->column_amount( $item );

		$this->assertStringContainsString( 'one time payment', $output );
	}

	// =========================================================================
	// column_setup_fee()
	// =========================================================================

	/**
	 * Test column_setup_fee returns 'No Setup Fee' when no setup fee.
	 */
	public function test_column_setup_fee_returns_no_setup_fee(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_pricing_type', 'has_setup_fee' ] )
			->getMock();
		$item->method( 'get_pricing_type' )->willReturn( 'paid' );
		$item->method( 'has_setup_fee' )->willReturn( false );

		$output = $this->table->column_setup_fee( $item );

		$this->assertStringContainsString( 'No Setup Fee', $output );
	}

	/**
	 * Test column_setup_fee returns 'None' for contact_us pricing type.
	 */
	public function test_column_setup_fee_returns_none_for_contact_us(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_pricing_type', 'has_setup_fee' ] )
			->getMock();
		$item->method( 'get_pricing_type' )->willReturn( 'contact_us' );

		$output = $this->table->column_setup_fee( $item );

		$this->assertStringContainsString( 'None', $output );
	}
}
