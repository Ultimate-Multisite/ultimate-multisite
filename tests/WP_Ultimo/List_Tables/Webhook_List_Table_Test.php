<?php
/**
 * Tests for Webhook_List_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\List_Tables;

use WP_UnitTestCase;

/**
 * Test class for Webhook_List_Table.
 *
 * @group list-tables
 */
class Webhook_List_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Webhook_List_Table
	 */
	private Webhook_List_Table $table;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->table = new Webhook_List_Table();
	}

	// =========================================================================
	// Constructor & Labels
	// =========================================================================

	/**
	 * Test constructor sets singular label.
	 */
	public function test_constructor_sets_singular_label(): void {

		$this->assertStringContainsString( 'Webhook', $this->table->get_label( 'singular' ) );
	}

	/**
	 * Test constructor sets plural label.
	 */
	public function test_constructor_sets_plural_label(): void {

		$this->assertStringContainsString( 'Webhooks', $this->table->get_label( 'plural' ) );
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
		$this->assertArrayHasKey( 'webhook_url', $columns );
		$this->assertArrayHasKey( 'event', $columns );
		$this->assertArrayHasKey( 'event_count', $columns );
		$this->assertArrayHasKey( 'integration', $columns );
		$this->assertArrayHasKey( 'active', $columns );
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
	// column_default()
	// =========================================================================

	/**
	 * Test column_default returns value from getter.
	 */
	public function test_column_default_returns_value_from_getter(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_name' ] )
			->getMock();
		$item->method( 'get_name' )->willReturn( 'My Webhook' );

		$output = $this->table->column_default( $item, 'name' );

		$this->assertEquals( 'My Webhook', $output );
	}

	// =========================================================================
	// column_webhook_url()
	// =========================================================================

	/**
	 * Test column_webhook_url returns truncated URL in span.
	 */
	public function test_column_webhook_url_returns_truncated_url_in_span(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_webhook_url' ] )
			->getMock();
		$item->method( 'get_webhook_url' )->willReturn( 'https://example.com/webhook/endpoint' );

		$output = $this->table->column_webhook_url( $item );

		$this->assertStringContainsString( 'example.com', $output );
		$this->assertStringContainsString( '<span', $output );
		$this->assertStringContainsString( 'wu-font-mono', $output );
	}

	/**
	 * Test column_webhook_url truncates long URLs.
	 */
	public function test_column_webhook_url_truncates_long_urls(): void {

		$long_url = 'https://example.com/webhook/endpoint/with/a/very/long/path/that/exceeds/fifty/characters/limit';

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_webhook_url' ] )
			->getMock();
		$item->method( 'get_webhook_url' )->willReturn( $long_url );

		$output = $this->table->column_webhook_url( $item );

		$this->assertStringContainsString( '...', $output );
	}

	// =========================================================================
	// column_event()
	// =========================================================================

	/**
	 * Test column_event returns event slug in span.
	 */
	public function test_column_event_returns_event_in_span(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_event' ] )
			->getMock();
		$item->method( 'get_event' )->willReturn( 'membership.created' );

		$output = $this->table->column_event( $item );

		$this->assertStringContainsString( 'membership.created', $output );
		$this->assertStringContainsString( '<span', $output );
		$this->assertStringContainsString( 'wu-font-mono', $output );
	}

	// =========================================================================
	// column_active()
	// =========================================================================

	/**
	 * Test column_active returns 'Yes' for active webhook.
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
	 * Test column_active returns 'No' for inactive webhook.
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
	// column_integration()
	// =========================================================================

	/**
	 * Test column_integration returns ucwords formatted integration name.
	 */
	public function test_column_integration_returns_formatted_name(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_integration' ] )
			->getMock();
		$item->method( 'get_integration' )->willReturn( 'zapier_integration' );

		$output = $this->table->column_integration( $item );

		$this->assertStringContainsString( 'Zapier', $output );
		$this->assertStringContainsString( 'Integration', $output );
	}

	// =========================================================================
	// column_count()
	// =========================================================================

	/**
	 * Test column_count returns count value.
	 */
	public function test_column_count_returns_count_value(): void {

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'get_count' ] )
			->getMock();
		$item->method( 'get_count' )->willReturn( 42 );

		$output = $this->table->column_count( $item );

		$this->assertStringContainsString( '42', $output );
	}
}
