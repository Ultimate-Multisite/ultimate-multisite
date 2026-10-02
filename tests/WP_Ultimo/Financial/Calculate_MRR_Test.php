<?php
namespace WP_Ultimo\Financial;

use WP_Ultimo\Faker;
use WP_Ultimo\Database\Memberships\Membership_Status;

class WU_Calculate_MRR_Test extends \WP_UnitTestCase {

	/**
	 * @var Faker
	 */
	private $faker;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		// Aggregate assertions require an empty fixture, including the first test.
		$wpdb->query("DELETE FROM {$wpdb->base_prefix}wu_memberships");
		$wpdb->query("DELETE FROM {$wpdb->base_prefix}wu_products");
		$wpdb->query("DELETE FROM {$wpdb->base_prefix}wu_customers");

		$this->faker = new Faker();
	}

	public function test_calculate_mrr_with_monthly_membership() {
		// Arrange
		$this->faker->generate_fake_customers(1);

		// Create a monthly membership product
		$product_data = [
			'name'          => 'Monthly Plan',
			'amount'        => 100.00,
			'duration'      => 1,
			'duration_unit' => 'month',
			'recurring'     => true,
			'active'        => true,
			'pricing_type'  => 'paid',
			'type'          => 'plan',
		];

		$product = wu_create_product($product_data);

		// Create active membership
		$membership_data = [
			'customer_id'   => $this->faker->get_fake_data_generated('customers')[0]->get_id(),
			'plan_id'       => $product->get_id(),
			'amount'        => $product->get_amount(),
			'status'        => Membership_Status::ACTIVE,
			'recurring'     => true,
			'duration'      => 1,
			'duration_unit' => 'month',
		];

		wu_create_membership($membership_data);

		// Act
		$result = wu_calculate_mrr();

		// Assert
		$this->assertEquals(100.00, $result);
	}

	public function test_calculate_mrr_with_yearly_membership() {
		// Arrange
		$this->faker->generate_fake_customers(1);

		// Create a yearly membership product
		$product_data = [
			'name'          => 'Yearly Plan',
			'amount'        => 1200.00,
			'duration'      => 1,
			'duration_unit' => 'year',
			'recurring'     => true,
			'active'        => true,
			'pricing_type'  => 'paid',
			'type'          => 'plan',
		];

		$product = wu_create_product($product_data);

		// Create active membership
		$membership_data = [
			'customer_id'   => $this->faker->get_fake_data_generated('customers')[0]->get_id(),
			'plan_id'       => $product->get_id(),
			'amount'        => $product->get_amount(),
			'status'        => Membership_Status::ACTIVE,
			'recurring'     => true,
			'duration'      => 1,
			'duration_unit' => 'year',
		];

		wu_create_membership($membership_data);

		// Act
		$result = wu_calculate_mrr();

		// Assert
		$this->assertEquals(100.00, $result); // 1200/12 = 100
	}

	public function test_calculate_mrr_with_non_recurring_membership() {
		// Arrange
		$this->faker->generate_fake_customers(1);

		// Create a non-recurring membership product
		$product_data = [
			'name'          => 'One-time Plan',
			'amount'        => 100.00,
			'duration'      => 1,
			'duration_unit' => 'month',
			'recurring'     => false,
			'active'        => true,
			'pricing_type'  => 'paid',
			'type'          => 'plan',
		];

		$product = wu_create_product($product_data);

		// Create active membership
		$membership_data = [
			'customer_id'   => $this->faker->get_fake_data_generated('customers')[0]->get_id(),
			'plan_id'       => $product->get_id(),
			'amount'        => $product->get_amount(),
			'status'        => Membership_Status::ACTIVE,
			'recurring'     => false,
			'duration'      => 1,
			'duration_unit' => 'month',
		];

		wu_create_membership($membership_data);

		// Act
		$result = wu_calculate_mrr();

		// Assert
		$this->assertEquals(0.00, $result);
	}

	public function test_calculate_mrr_with_multiple_memberships() {
		// Arrange
		$this->faker->generate_fake_customers(2);
		$customers = $this->faker->get_fake_data_generated('customers');

		// Create monthly product
		$monthly_product = wu_create_product(
			[
				'name'          => 'Monthly Plan',
				'amount'        => 100.00,
				'duration'      => 1,
				'duration_unit' => 'month',
				'recurring'     => true,
				'active'        => true,
				'pricing_type'  => 'paid',
				'type'          => 'plan',
			]
		);

		// Create yearly product
		$yearly_product = wu_create_product(
			[
				'name'          => 'Yearly Plan',
				'amount'        => 1200.00,
				'duration'      => 1,
				'duration_unit' => 'year',
				'recurring'     => true,
				'active'        => true,
				'pricing_type'  => 'paid',
				'type'          => 'plan',
			]
		);

		// Create memberships
		wu_create_membership(
			[
				'customer_id'   => $customers[0]->get_id(),
				'plan_id'       => $monthly_product->get_id(),
				'amount'        => $monthly_product->get_amount(),
				'status'        => Membership_Status::ACTIVE,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		wu_create_membership(
			[
				'customer_id'   => $customers[1]->get_id(),
				'plan_id'       => $yearly_product->get_id(),
				'amount'        => $yearly_product->get_amount(),
				'status'        => Membership_Status::ACTIVE,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'year',
			]
		);

		// Act
		$result = wu_calculate_mrr();

		// Assert
		$this->assertEquals(200.00, $result); // 100 + (1200/12)
	}

	public function test_calculate_mrr_with_no_memberships() {
		// Act
		$result = wu_calculate_mrr();

		// Assert
		$this->assertEquals(0.00, $result);
	}

	protected function tearDown(): void {
		global $wpdb;

		// Clean up the test data
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_memberships");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_products");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_customers");

		parent::tearDown();
	}
}
