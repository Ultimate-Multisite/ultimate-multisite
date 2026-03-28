<?php
/**
 * Tests for Customers_Site_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Customers_Site_List_Table.
 *
 * @group list-tables
 */
class Customers_Site_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Customers_Site_List_Table
	 */
	private Customers_Site_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Customers_Site_List_Table();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		unset( $_REQUEST['page'], $_REQUEST['id'] );

		parent::tear_down();
	}

	// =========================================================================
	// Constructor & Inheritance
	// =========================================================================

	/**
	 * Test table extends Site_List_Table.
	 */
	public function test_table_extends_site_list_table(): void {

		$this->assertInstanceOf( Site_List_Table::class, $this->table );
	}

	/**
	 * Test constructor forces list mode.
	 */
	public function test_constructor_forces_list_mode(): void {

		$this->assertEquals( 'list', $this->table->current_mode );
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
	// get_items() — overrides parent to add pending sites
	// =========================================================================

	/**
	 * Test get_items with count=true returns numeric.
	 */
	public function test_get_items_count_returns_numeric(): void {

		$result = $this->table->get_items( 5, 1, true );

		$this->assertIsNumeric( $result );
	}

	/**
	 * Test get_items with count=false returns array.
	 */
	public function test_get_items_returns_array(): void {

		$result = $this->table->get_items( 5, 1, false );

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_items without id returns sites without pending.
	 */
	public function test_get_items_without_id_returns_sites(): void {

		unset( $_REQUEST['id'] );

		$result = $this->table->get_items( 5, 1, false );

		$this->assertIsArray( $result );
	}
}
