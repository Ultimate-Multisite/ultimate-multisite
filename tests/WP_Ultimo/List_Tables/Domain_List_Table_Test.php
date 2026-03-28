<?php
/**
 * Tests for Domain_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Domain_List_Table.
 *
 * @group list-tables
 */
class Domain_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Domain_List_Table
	 */
	private Domain_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Domain_List_Table();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {

		unset( $_REQUEST['blog_id'] );

		parent::tear_down();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Domain', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Domains', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'domain', $columns );
		$this->assertArrayHasKey( 'stage', $columns );
		$this->assertArrayHasKey( 'blog_id', $columns );
		$this->assertArrayHasKey( 'active', $columns );
		$this->assertArrayHasKey( 'primary_domain', $columns );
		$this->assertArrayHasKey( 'secure', $columns );
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
		$this->assertArrayHasKey( 'date_filters', $filters );
	}

	/**
	 * Test get_filters has active, primary_domain, secure, stage filters.
	 */
	public function test_get_filters_has_expected_filter_keys(): void {

		$filters = $this->table->get_filters();

		$this->assertArrayHasKey( 'active', $filters['filters'] );
		$this->assertArrayHasKey( 'primary_domain', $filters['filters'] );
		$this->assertArrayHasKey( 'secure', $filters['filters'] );
		$this->assertArrayHasKey( 'stage', $filters['filters'] );
	}

	// =========================================================================
	// get_extra_query_fields()
	// =========================================================================

	/**
	 * Test get_extra_query_fields adds blog_id when present in request.
	 */
	public function test_get_extra_query_fields_adds_blog_id(): void {

		$_REQUEST['blog_id'] = '5';

		$fields = $this->table->get_extra_query_fields();

		$this->assertArrayHasKey( 'blog_id', $fields );
		$this->assertEquals( '5', $fields['blog_id'] );

		unset( $_REQUEST['blog_id'] );
	}

	/**
	 * Test get_extra_query_fields returns empty when no blog_id.
	 */
	public function test_get_extra_query_fields_empty_without_blog_id(): void {

		unset( $_REQUEST['blog_id'] );

		$fields = $this->table->get_extra_query_fields();

		$this->assertArrayNotHasKey( 'blog_id', $fields );
	}

	// =========================================================================
	// column_active()
	// =========================================================================

	/**
	 * Test column_active returns 'Yes' for active domain.
	 */
	public function test_column_active_returns_yes_for_active(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_active' ] )
			->getMock();
		$item->method( 'is_active' )->willReturn( true );

		$output = $this->table->column_active( $item );

		$this->assertStringContainsString( 'Yes', $output );
	}

	/**
	 * Test column_active returns 'No' for inactive domain.
	 */
	public function test_column_active_returns_no_for_inactive(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_active' ] )
			->getMock();
		$item->method( 'is_active' )->willReturn( false );

		$output = $this->table->column_active( $item );

		$this->assertStringContainsString( 'No', $output );
	}

	// =========================================================================
	// column_primary_domain()
	// =========================================================================

	/**
	 * Test column_primary_domain returns 'Yes' for primary domain.
	 */
	public function test_column_primary_domain_returns_yes_for_primary(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_primary_domain' ] )
			->getMock();
		$item->method( 'is_primary_domain' )->willReturn( true );

		$output = $this->table->column_primary_domain( $item );

		$this->assertStringContainsString( 'Yes', $output );
	}

	/**
	 * Test column_primary_domain returns 'No' for non-primary domain.
	 */
	public function test_column_primary_domain_returns_no_for_non_primary(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_primary_domain' ] )
			->getMock();
		$item->method( 'is_primary_domain' )->willReturn( false );

		$output = $this->table->column_primary_domain( $item );

		$this->assertStringContainsString( 'No', $output );
	}

	// =========================================================================
	// column_secure()
	// =========================================================================

	/**
	 * Test column_secure returns 'Yes' for secure domain.
	 */
	public function test_column_secure_returns_yes_for_secure(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_secure' ] )
			->getMock();
		$item->method( 'is_secure' )->willReturn( true );

		$output = $this->table->column_secure( $item );

		$this->assertStringContainsString( 'Yes', $output );
	}

	/**
	 * Test column_secure returns 'No' for non-secure domain.
	 */
	public function test_column_secure_returns_no_for_non_secure(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'is_secure' ] )
			->getMock();
		$item->method( 'is_secure' )->willReturn( false );

		$output = $this->table->column_secure( $item );

		$this->assertStringContainsString( 'No', $output );
	}

	// =========================================================================
	// column_stage()
	// =========================================================================

	/**
	 * Test column_stage returns span with label and class.
	 */
	public function test_column_stage_returns_span_with_label(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_stage_label', 'get_stage_class' ] )
			->getMock();
		$item->method( 'get_stage_label' )->willReturn( 'Verified' );
		$item->method( 'get_stage_class' )->willReturn( 'wu-bg-green-200' );

		$output = $this->table->column_stage( $item );

		$this->assertStringContainsString( 'Verified', $output );
		$this->assertStringContainsString( 'wu-bg-green-200', $output );
		$this->assertStringContainsString( '<span', $output );
	}
}
