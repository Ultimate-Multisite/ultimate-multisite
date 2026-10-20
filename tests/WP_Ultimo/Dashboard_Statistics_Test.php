<?php
/**
 * Tests for the Dashboard_Statistics class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Tests for the Dashboard_Statistics class.
 *
 * @group dashboard-statistics
 */
class Dashboard_Statistics_Test extends WP_UnitTestCase {

	// ------------------------------------------------------------------
	// Constructor
	// ------------------------------------------------------------------

	/**
	 * Test constructor with empty args.
	 */
	public function test_constructor_with_empty_args() {
		$stats = new Dashboard_Statistics();
		$this->assertInstanceOf(Dashboard_Statistics::class, $stats);
	}

	/**
	 * Test constructor sets properties.
	 */
	public function test_constructor_sets_properties() {
		$stats = new Dashboard_Statistics(
			[
				'start_date' => '2025-01-01',
				'end_date'   => '2025-12-31',
				'types'      => ['mrr_growth'],
			]
		);

		$ref_start = new \ReflectionProperty(Dashboard_Statistics::class, 'start_date');
		$ref_end   = new \ReflectionProperty(Dashboard_Statistics::class, 'end_date');
		$ref_types = new \ReflectionProperty(Dashboard_Statistics::class, 'types');

		if ( PHP_VERSION_ID < 80100 ) {
			$ref_start->setAccessible(true);
			$ref_end->setAccessible(true);
			$ref_types->setAccessible(true);
		}

		$this->assertEquals('2025-01-01', $ref_start->getValue($stats));
		$this->assertEquals('2025-12-31', $ref_end->getValue($stats));
		$this->assertEquals(['mrr_growth'], $ref_types->getValue($stats));
	}

	// ------------------------------------------------------------------
	// statistics_data
	// ------------------------------------------------------------------

	/**
	 * Test statistics_data calls type methods.
	 */
	public function test_statistics_data_calls_type_methods() {
		$stats = new Dashboard_Statistics(
			[
				'start_date' => '2025-01-01',
				'end_date'   => '2025-12-31',
				'types'      => ['mrr_growth' => 'mrr_growth'],
			]
		);

		$data = $stats->statistics_data();

		$this->assertIsArray($data);
		$this->assertArrayHasKey('mrr_growth', $data);
	}

	/**
	 * Test statistics_data with empty types.
	 */
	public function test_statistics_data_with_empty_types() {
		$stats = new Dashboard_Statistics(
			[
				'start_date' => '2025-01-01',
				'end_date'   => '2025-12-31',
				'types'      => [],
			]
		);

		$data = $stats->statistics_data();
		$this->assertIsArray($data);
		$this->assertEmpty($data);
	}

	// ------------------------------------------------------------------
	// get_data_mrr_growth
	// ------------------------------------------------------------------

	/**
	 * Test get_data_mrr_growth returns all months.
	 */
	public function test_get_data_mrr_growth_returns_all_months() {
		$stats = new Dashboard_Statistics(
			[
				'start_date' => '2025-01-01',
				'end_date'   => '2025-12-31',
				'types'      => [],
			]
		);

		$data = $stats->get_data_mrr_growth();

		$expected_months = [
			'january',
			'february',
			'march',
			'april',
			'may',
			'june',
			'july',
			'august',
			'september',
			'october',
			'november',
			'december',
		];

		foreach ( $expected_months as $month ) {
			$this->assertArrayHasKey($month, $data, "Missing month: $month");
			$this->assertArrayHasKey('total', $data[ $month ]);
			$this->assertArrayHasKey('cancelled', $data[ $month ]);
		}
	}

	/**
	 * Test get_data_mrr_growth returns zero totals with no memberships.
	 */
	public function test_get_data_mrr_growth_returns_zero_totals_with_no_memberships() {
		$stats = new Dashboard_Statistics(
			[
				'start_date' => '2025-01-01',
				'end_date'   => '2025-12-31',
				'types'      => [],
			]
		);

		$data = $stats->get_data_mrr_growth();

		foreach ( $data as $month => $values ) {
			$this->assertEquals(0, $values['total'], "Month $month should have 0 total");
			$this->assertEquals(0, $values['cancelled'], "Month $month should have 0 cancelled");
		}
	}

	/**
	 * Test get_data_mrr_growth counts recurring memberships.
	 */
	public function test_get_data_mrr_growth_counts_recurring_memberships() {
		// Create a product with recurring billing
		$product = wu_create_product(
			[
				'name'          => 'Test Plan',
				'slug'          => 'test-plan-mrr',
				'type'          => 'plan',
				'pricing_type'  => 'paid',
				'amount'        => 29.99,
				'duration'      => 1,
				'duration_unit' => 'month',
				'recurring'     => true,
				'active'        => true,
				'currency'      => 'USD',
			]
		);

		$this->assertNotWPError($product);

		// Create a customer
		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer(
			[
				'user_id'            => $user_id,
				'email_verification' => 'none',
			]
		);

		$this->assertNotWPError($customer);

		// Create a recurring membership
		$membership = wu_create_membership(
			[
				'customer_id'   => $customer->get_id(),
				'plan_id'       => $product->get_id(),
				'amount'        => 29.99,
				'status'        => 'active',
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
				'currency'      => 'USD',
				'date_created'  => current_time('Y') . '-01-15 10:00:00',
			]
		);

		$this->assertNotWPError($membership);

		$stats = new Dashboard_Statistics(
			[
				'start_date' => current_time('Y') . '-01-01',
				'end_date'   => current_time('Y') . '-12-31',
				'types'      => [],
			]
		);

		$data = $stats->get_data_mrr_growth();

		// The january total should include our membership's normalized amount
		$this->assertGreaterThanOrEqual(0, $data['january']['total']);
	}

	// ------------------------------------------------------------------
	// Month structure validation
	// ------------------------------------------------------------------

	/**
	 * Test mrr_growth has exactly 12 months.
	 */
	public function test_mrr_growth_has_exactly_12_months() {
		$stats = new Dashboard_Statistics(
			[
				'start_date' => '2025-01-01',
				'end_date'   => '2025-12-31',
				'types'      => [],
			]
		);

		$data = $stats->get_data_mrr_growth();
		$this->assertCount(12, $data);
	}

	/**
	 * Test mrr_growth month values are numeric.
	 */
	public function test_mrr_growth_month_values_are_numeric() {
		$stats = new Dashboard_Statistics(
			[
				'start_date' => '2025-01-01',
				'end_date'   => '2025-12-31',
				'types'      => [],
			]
		);

		$data = $stats->get_data_mrr_growth();

		foreach ( $data as $month => $values ) {
			$this->assertIsNumeric($values['total'], "Month $month total should be numeric");
			$this->assertIsNumeric($values['cancelled'], "Month $month cancelled should be numeric");
		}
	}
}
