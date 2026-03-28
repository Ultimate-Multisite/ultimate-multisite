<?php
/**
 * Tests for Discount_Code_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Discount_Code_List_Table.
 *
 * @group list-tables
 */
class Discount_Code_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Discount_Code_List_Table
	 */
	private Discount_Code_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Discount_Code_List_Table();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Discount Code', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Discount Codes', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'name', $columns );
		$this->assertArrayHasKey( 'coupon_code', $columns );
		$this->assertArrayHasKey( 'uses', $columns );
		$this->assertArrayHasKey( 'value', $columns );
		$this->assertArrayHasKey( 'setup_fee_value', $columns );
		$this->assertArrayHasKey( 'date_expiration', $columns );
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
	// column_default()
	// =========================================================================

	/**
	 * Test column_default returns value from getter.
	 */
	public function test_column_default_returns_value_from_getter(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_name' ] )
			->getMock();
		$item->method( 'get_name' )->willReturn( 'Summer Sale' );

		$output = $this->table->column_default( $item, 'name' );

		$this->assertEquals( 'Summer Sale', $output );
	}

	// =========================================================================
	// column_value()
	// =========================================================================

	/**
	 * Test column_value returns 'No Discount' when value is 0.
	 */
	public function test_column_value_returns_no_discount_when_zero(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_value', 'get_type' ] )
			->getMock();
		$item->method( 'get_value' )->willReturn( 0 );

		$output = $this->table->column_value( $item );

		$this->assertStringContainsString( 'No Discount', $output );
	}

	/**
	 * Test column_value returns percentage format for percentage type.
	 */
	public function test_column_value_returns_percentage_for_percentage_type(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_value', 'get_type' ] )
			->getMock();
		$item->method( 'get_value' )->willReturn( 20 );
		$item->method( 'get_type' )->willReturn( 'percentage' );

		$output = $this->table->column_value( $item );

		$this->assertStringContainsString( '%', $output );
		$this->assertStringContainsString( 'OFF', $output );
	}

	/**
	 * Test column_value returns currency format for flat type.
	 */
	public function test_column_value_returns_currency_for_flat_type(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_value', 'get_type' ] )
			->getMock();
		$item->method( 'get_value' )->willReturn( 10 );
		$item->method( 'get_type' )->willReturn( 'flat' );

		$output = $this->table->column_value( $item );

		$this->assertStringContainsString( 'OFF', $output );
	}

	// =========================================================================
	// column_setup_fee_value()
	// =========================================================================

	/**
	 * Test column_setup_fee_value returns 'No Discount' when value is 0.
	 */
	public function test_column_setup_fee_value_returns_no_discount_when_zero(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_setup_fee_value', 'get_setup_fee_type' ] )
			->getMock();
		$item->method( 'get_setup_fee_value' )->willReturn( 0 );

		$output = $this->table->column_setup_fee_value( $item );

		$this->assertStringContainsString( 'No Discount', $output );
	}

	/**
	 * Test column_setup_fee_value returns percentage for percentage type.
	 */
	public function test_column_setup_fee_value_returns_percentage_for_percentage_type(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_setup_fee_value', 'get_setup_fee_type' ] )
			->getMock();
		$item->method( 'get_setup_fee_value' )->willReturn( 15 );
		$item->method( 'get_setup_fee_type' )->willReturn( 'percentage' );

		$output = $this->table->column_setup_fee_value( $item );

		$this->assertStringContainsString( '%', $output );
	}

	// =========================================================================
	// column_uses()
	// =========================================================================

	/**
	 * Test column_uses returns usage count.
	 */
	public function test_column_uses_returns_usage_count(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_uses', 'get_max_uses' ] )
			->getMock();
		$item->method( 'get_uses' )->willReturn( 5 );
		$item->method( 'get_max_uses' )->willReturn( 0 );

		$output = $this->table->column_uses( $item );

		$this->assertStringContainsString( '5', $output );
		$this->assertStringContainsString( 'No Limits', $output );
	}

	/**
	 * Test column_uses shows max uses when set.
	 */
	public function test_column_uses_shows_max_uses_when_set(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_uses', 'get_max_uses' ] )
			->getMock();
		$item->method( 'get_uses' )->willReturn( 3 );
		$item->method( 'get_max_uses' )->willReturn( 10 );

		$output = $this->table->column_uses( $item );

		$this->assertStringContainsString( '10', $output );
	}

	// =========================================================================
	// column_coupon_code()
	// =========================================================================

	/**
	 * Test column_coupon_code returns code in uppercase span.
	 */
	public function test_column_coupon_code_returns_uppercase_code(): void {

		$valid_mock = true; // not a WP_Error

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_code', 'is_valid' ] )
			->getMock();
		$item->method( 'get_code' )->willReturn( 'summer20' );
		$item->method( 'is_valid' )->willReturn( true );

		$output = $this->table->column_coupon_code( $item );

		$this->assertStringContainsString( 'SUMMER20', $output );
		$this->assertStringContainsString( 'wu-font-mono', $output );
	}
}
