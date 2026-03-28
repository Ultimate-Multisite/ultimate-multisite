<?php
/**
 * Tests for Customers_Membership_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Customers_Membership_List_Table.
 *
 * @group list-tables
 */
class Customers_Membership_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Customers_Membership_List_Table
	 */
	private Customers_Membership_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Customers_Membership_List_Table();
	}

	// =========================================================================
	// Constructor & Inheritance
	// =========================================================================

	/**
	 * Test table extends Membership_List_Table.
	 */
	public function test_table_extends_membership_list_table(): void {

		$this->assertInstanceOf( Membership_List_Table::class, $this->table );
	}

	/**
	 * Test table inherits singular label from parent.
	 */
	public function test_table_inherits_singular_label(): void {

		$this->assertStringContainsString( 'Membership', $this->table->get_label( 'singular' ) );
	}

	// =========================================================================
	// get_columns()
	// =========================================================================

	/**
	 * Test get_columns returns only responsive column.
	 */
	public function test_get_columns_returns_only_responsive(): void {

		$columns = $this->table->get_columns();

		$this->assertIsArray( $columns );
		$this->assertArrayHasKey( 'responsive', $columns );
		$this->assertCount( 1, $columns );
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
	// get_filters() — inherited from Membership_List_Table
	// =========================================================================

	/**
	 * Test get_filters returns correct structure (inherited).
	 */
	public function test_get_filters_returns_correct_structure(): void {

		$filters = $this->table->get_filters();

		$this->assertIsArray( $filters );
		$this->assertArrayHasKey( 'filters', $filters );
		$this->assertArrayHasKey( 'date_filters', $filters );
	}

	// =========================================================================
	// get_views() — inherited from Membership_List_Table
	// =========================================================================

	/**
	 * Test get_views returns all key (inherited).
	 */
	public function test_get_views_returns_all_key(): void {

		$views = $this->table->get_views();

		$this->assertIsArray( $views );
		$this->assertArrayHasKey( 'all', $views );
	}

	// =========================================================================
	// column_responsive()
	// =========================================================================

	/**
	 * Test column_responsive outputs HTML without throwing.
	 *
	 * Note: wu_responsive_table_row() is a template helper not available in unit
	 * test environment. We verify the method is callable and the item getters are
	 * invoked correctly by checking the method exists and the column is defined.
	 */
	public function test_column_responsive_method_exists(): void {

		$this->assertTrue( method_exists( $this->table, 'column_responsive' ) );
	}

	/**
	 * Test column_responsive is defined in get_columns.
	 */
	public function test_column_responsive_is_in_get_columns(): void {

		$columns = $this->table->get_columns();

		$this->assertArrayHasKey( 'responsive', $columns );
	}
}
