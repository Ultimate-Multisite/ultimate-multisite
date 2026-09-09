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
	 * Test column_responsive renders lifetime expiration labels.
	 */
	public function test_column_responsive_renders_lifetime_expiration_labels(): void {

		foreach ( ['', null, '0000-00-00 00:00:00'] as $date_expiration ) {
			$output = $this->get_column_responsive_output( $date_expiration );

			$this->assertStringContainsString( 'Lifetime / It never expires', $output );
			$this->assertStringNotContainsString( 'Expired', $output );
			$this->assertStringNotContainsString( 'Expiring', $output );
		}
	}

	/**
	 * Test column_responsive renders future and past expiration labels.
	 */
	public function test_column_responsive_renders_timed_expiration_labels(): void {

		$future_output = $this->get_column_responsive_output( gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$past_output   = $this->get_column_responsive_output( gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );

		$this->assertStringContainsString( 'Expiring', $future_output );
		$this->assertStringContainsString( 'Expired', $past_output );
	}

	/**
	 * Test column_responsive is defined in get_columns.
	 */
	public function test_column_responsive_is_in_get_columns(): void {

		$columns = $this->table->get_columns();

		$this->assertArrayHasKey( 'responsive', $columns );
	}

	/**
	 * Returns the responsive membership card output for an expiration date.
	 *
	 * @param string|null $date_expiration Membership expiration date.
	 * @return string
	 */
	private function get_column_responsive_output($date_expiration): string {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods(
				[
					'get_plan',
					'get_addon_ids',
					'get_id',
					'get_hash',
					'get_status_label',
					'get_status_class',
					'get_price_description',
					'get_gateway',
					'get_date_expiration',
					'get_date_created',
				]
			)
			->getMock();

		$item->method( 'get_plan' )->willReturn( false );
		$item->method( 'get_addon_ids' )->willReturn( [] );
		$item->method( 'get_id' )->willReturn( 1 );
		$item->method( 'get_hash' )->willReturn( 'membership-hash' );
		$item->method( 'get_status_label' )->willReturn( 'Pending' );
		$item->method( 'get_status_class' )->willReturn( 'wu-bg-yellow-200' );
		$item->method( 'get_price_description' )->willReturn( 'Free' );
		$item->method( 'get_gateway' )->willReturn( 'manual' );
		$item->method( 'get_date_expiration' )->willReturn( $date_expiration );
		$item->method( 'get_date_created' )->willReturn( '2025-01-01 00:00:00' );

		ob_start();

		$this->table->column_responsive( $item );

		return (string) ob_get_clean();
	}
}
