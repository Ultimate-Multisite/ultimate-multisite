<?php
/**
 * Tests for Checkout_Form_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Checkout_Form_List_Table.
 *
 * @group list-tables
 */
class Checkout_Form_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Checkout_Form_List_Table
	 */
	private Checkout_Form_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Checkout_Form_List_Table();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Checkout Form', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Checkout Forms', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'slug', $columns );
		$this->assertArrayHasKey( 'steps', $columns );
		$this->assertArrayHasKey( 'id', $columns );
	}

	/**
	 * Test get_columns returns 5 columns.
	 */
	public function test_get_columns_returns_five_columns(): void {

		$columns = $this->table->get_columns();

		$this->assertCount( 5, $columns );
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
	// column_slug()
	// =========================================================================

	/**
	 * Test column_slug returns slug wrapped in span.
	 */
	public function test_column_slug_returns_slug_in_span(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_slug' ] )
			->getMock();
		$item->method( 'get_slug' )->willReturn( 'my-checkout-form' );

		$output = $this->table->column_slug( $item );

		$this->assertStringContainsString( 'my-checkout-form', $output );
		$this->assertStringContainsString( '<span', $output );
		$this->assertStringContainsString( 'wu-font-mono', $output );
	}

	// =========================================================================
	// column_steps()
	// =========================================================================

	/**
	 * Test column_steps returns step and field count.
	 */
	public function test_column_steps_returns_step_and_field_count(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_step_count', 'get_field_count' ] )
			->getMock();
		$item->method( 'get_step_count' )->willReturn( 3 );
		$item->method( 'get_field_count' )->willReturn( 12 );

		$output = $this->table->column_steps( $item );

		$this->assertStringContainsString( '3', $output );
		$this->assertStringContainsString( '12', $output );
	}

	// =========================================================================
	// column_shortcode()
	// =========================================================================

	/**
	 * Test column_shortcode returns input with shortcode value.
	 */
	public function test_column_shortcode_returns_input_with_shortcode(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_shortcode' ] )
			->getMock();
		$item->method( 'get_shortcode' )->willReturn( '[wu_checkout slug="my-form"]' );

		$output = $this->table->column_shortcode( $item );

		$this->assertStringContainsString( '<input', $output );
		$this->assertStringContainsString( 'wu_checkout', $output );
	}

	// =========================================================================
	// column_name()
	// =========================================================================

	/**
	 * Test column_name returns string with form name.
	 */
	public function test_column_name_returns_string_with_form_name(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_id', 'get_slug', 'get_name' ] )
			->getMock();
		$item->method( 'get_id' )->willReturn( 1 );
		$item->method( 'get_slug' )->willReturn( 'my-form' );
		$item->method( 'get_name' )->willReturn( 'My Checkout Form' );

		$output = $this->table->column_name( $item );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'My Checkout Form', $output );
	}
}
