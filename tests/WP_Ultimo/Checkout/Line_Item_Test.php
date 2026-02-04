<?php

namespace WP_Ultimo\Checkout;

use WP_UnitTestCase;

class Line_Item_Test extends WP_UnitTestCase {

	/**
	 * Create a basic line item for testing.
	 *
	 * @param array $overrides Optional attribute overrides.
	 * @return Line_Item
	 */
	protected function create_line_item(array $overrides = []): Line_Item {

		$defaults = [
			'hash'       => 'test_hash_123',
			'type'       => 'product',
			'title'      => 'Test Product',
			'unit_price' => 100,
			'quantity'   => 1,
		];

		return new Line_Item(array_merge($defaults, $overrides));
	}

	/**
	 * Test constructor sets basic attributes.
	 */
	public function test_constructor_sets_attributes(): void {

		$line_item = $this->create_line_item();

		$this->assertEquals('Test Product', $line_item->get_title());
		$this->assertEquals(100, $line_item->get_unit_price());
		$this->assertEquals(1, $line_item->get_quantity());
		$this->assertEquals('product', $line_item->get_type());
	}

	/**
	 * Test constructor generates ID from type and hash.
	 */
	public function test_constructor_generates_id(): void {

		$line_item = $this->create_line_item(['hash' => 'abc123', 'type' => 'fee']);

		$id = $line_item->get_id();

		$this->assertStringStartsWith('LN_FEE_', $id);
		$this->assertStringContainsString('abc123', $id);
	}

	/**
	 * Test recalculate_totals with simple product.
	 */
	public function test_recalculate_totals_simple(): void {

		$line_item = $this->create_line_item([
			'unit_price' => 50,
			'quantity'   => 2,
		]);

		$this->assertEquals(100, $line_item->get_subtotal());
		$this->assertEquals(100, $line_item->get_total());
		$this->assertEquals(0, $line_item->get_discount_total());
		$this->assertEquals(0, $line_item->get_tax_total());
	}

	/**
	 * Test recalculate_totals with percentage discount.
	 */
	public function test_recalculate_totals_with_percentage_discount(): void {

		$line_item = $this->create_line_item([
			'unit_price'    => 100,
			'quantity'      => 1,
			'discount_rate' => 10,
			'discount_type' => 'percentage',
		]);

		$this->assertEquals(100, $line_item->get_subtotal());
		$this->assertEquals(10, $line_item->get_discount_total());
		$this->assertEquals(90, $line_item->get_total());
	}

	/**
	 * Test recalculate_totals with absolute discount.
	 */
	public function test_recalculate_totals_with_absolute_discount(): void {

		$line_item = $this->create_line_item([
			'unit_price'    => 100,
			'quantity'      => 1,
			'discount_rate' => 25,
			'discount_type' => 'absolute',
		]);

		$this->assertEquals(100, $line_item->get_subtotal());
		$this->assertEquals(25, $line_item->get_discount_total());
		$this->assertEquals(75, $line_item->get_total());
	}

	/**
	 * Test recalculate_totals with tax exclusive.
	 */
	public function test_recalculate_totals_with_tax_exclusive(): void {

		$line_item = $this->create_line_item([
			'unit_price'    => 100,
			'quantity'      => 1,
			'tax_rate'      => 10,
			'tax_type'      => 'percentage',
			'tax_inclusive' => false,
		]);

		$this->assertEquals(100, $line_item->get_subtotal());
		$this->assertEquals(10, $line_item->get_tax_total());
		$this->assertEquals(110, $line_item->get_total());
	}

	/**
	 * Test recalculate_totals with tax inclusive.
	 */
	public function test_recalculate_totals_with_tax_inclusive(): void {

		$line_item = $this->create_line_item([
			'unit_price'    => 100,
			'quantity'      => 1,
			'tax_rate'      => 10,
			'tax_type'      => 'percentage',
			'tax_inclusive' => true,
		]);

		$this->assertEquals(100, $line_item->get_subtotal());
		// Tax inclusive means total stays the same
		$this->assertEquals(100, $line_item->get_total());
	}

	/**
	 * Test recalculate_totals with tax exempt.
	 */
	public function test_recalculate_totals_tax_exempt(): void {

		$line_item = $this->create_line_item([
			'unit_price'    => 100,
			'quantity'      => 1,
			'tax_rate'      => 10,
			'tax_type'      => 'percentage',
			'tax_inclusive' => false,
			'tax_exempt'    => true,
		]);

		$this->assertEquals(100, $line_item->get_subtotal());
		$this->assertEquals(0, $line_item->get_tax_total());
		$this->assertEquals(100, $line_item->get_total());
	}

	/**
	 * Test discount cannot make total negative.
	 */
	public function test_discount_cannot_make_total_negative(): void {

		$line_item = $this->create_line_item([
			'unit_price'    => 50,
			'quantity'      => 1,
			'discount_rate' => 100,
			'discount_type' => 'absolute',
		]);

		$this->assertGreaterThanOrEqual(0, $line_item->get_total());
		$this->assertEquals(50, $line_item->get_discount_total());
	}

	/**
	 * Test recalculate_totals with discount and tax combined.
	 */
	public function test_recalculate_totals_discount_and_tax(): void {

		$line_item = $this->create_line_item([
			'unit_price'    => 100,
			'quantity'      => 1,
			'discount_rate' => 20,
			'discount_type' => 'percentage',
			'tax_rate'      => 10,
			'tax_type'      => 'percentage',
			'tax_inclusive' => false,
		]);

		// Subtotal: 100
		// Discount: 20% of 100 = 20
		// After discount: 80
		// Tax: 10% of 80 = 8
		// Total: 80 + 8 = 88
		$this->assertEquals(100, $line_item->get_subtotal());
		$this->assertEquals(20, $line_item->get_discount_total());
		$this->assertEquals(8, $line_item->get_tax_total());
		$this->assertEquals(88, $line_item->get_total());
	}

	/**
	 * Test getters and setters for type.
	 */
	public function test_type_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_type('fee');
		$this->assertEquals('fee', $line_item->get_type());

		$line_item->set_type('credit');
		$this->assertEquals('credit', $line_item->get_type());
	}

	/**
	 * Test getters and setters for quantity.
	 */
	public function test_quantity_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_quantity(5);
		$this->assertEquals(5, $line_item->get_quantity());
	}

	/**
	 * Test getters and setters for unit_price.
	 */
	public function test_unit_price_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_unit_price(250);
		$this->assertEquals(250, $line_item->get_unit_price());
	}

	/**
	 * Test getters and setters for tax_rate.
	 */
	public function test_tax_rate_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_tax_rate(15.5);
		$this->assertEquals(15.5, $line_item->get_tax_rate());
	}

	/**
	 * Test getters and setters for tax_type.
	 */
	public function test_tax_type_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_tax_type('absolute');
		$this->assertEquals('absolute', $line_item->get_tax_type());
	}

	/**
	 * Test getters and setters for tax_inclusive.
	 */
	public function test_tax_inclusive_getter_setter(): void {

		$line_item = $this->create_line_item();

		$this->assertFalse($line_item->get_tax_inclusive());

		$line_item->set_tax_inclusive(true);
		$this->assertTrue($line_item->get_tax_inclusive());
	}

	/**
	 * Test getters and setters for tax_exempt.
	 */
	public function test_tax_exempt_getter_setter(): void {

		$line_item = $this->create_line_item();

		$this->assertFalse($line_item->is_tax_exempt());

		$line_item->set_tax_exempt(true);
		$this->assertTrue($line_item->is_tax_exempt());
	}

	/**
	 * Test getters and setters for recurring.
	 */
	public function test_recurring_getter_setter(): void {

		$line_item = $this->create_line_item();

		$this->assertFalse($line_item->is_recurring());

		$line_item->set_recurring(true);
		$this->assertTrue($line_item->is_recurring());
	}

	/**
	 * Test getters and setters for duration.
	 */
	public function test_duration_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_duration(3);
		$this->assertEquals(3, $line_item->get_duration());
	}

	/**
	 * Test getters and setters for duration_unit.
	 */
	public function test_duration_unit_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_duration_unit('year');
		$this->assertEquals('year', $line_item->get_duration_unit());
	}

	/**
	 * Test getters and setters for billing_cycles.
	 */
	public function test_billing_cycles_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_billing_cycles(12);
		$this->assertEquals(12, $line_item->get_billing_cycles());
	}

	/**
	 * Test getters and setters for discount_rate.
	 */
	public function test_discount_rate_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_discount_rate(15.5);
		$this->assertEquals(15.5, $line_item->get_discount_rate());
	}

	/**
	 * Test getters and setters for discount_type.
	 */
	public function test_discount_type_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_discount_type('absolute');
		$this->assertEquals('absolute', $line_item->get_discount_type());
	}

	/**
	 * Test getters and setters for discount_label.
	 */
	public function test_discount_label_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_discount_label('SUMMER20');
		$this->assertEquals('SUMMER20', $line_item->get_discount_label());
	}

	/**
	 * Test getters and setters for apply_discount_to_renewals.
	 */
	public function test_apply_discount_to_renewals_getter_setter(): void {

		$line_item = $this->create_line_item();

		$this->assertTrue($line_item->should_apply_discount_to_renewals());

		$line_item->set_apply_discount_to_renewals(false);
		$this->assertFalse($line_item->should_apply_discount_to_renewals());
	}

	/**
	 * Test getters and setters for product_id.
	 */
	public function test_product_id_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_product_id(42);
		$this->assertEquals(42, $line_item->get_product_id());
	}

	/**
	 * Test getters and setters for title.
	 */
	public function test_title_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_title('New Title');
		$this->assertEquals('New Title', $line_item->get_title());
	}

	/**
	 * Test getters and setters for description.
	 */
	public function test_description_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_description('A detailed description');
		$this->assertEquals('A detailed description', $line_item->get_description());
	}

	/**
	 * Test getters and setters for tax_label.
	 */
	public function test_tax_label_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_tax_label('VAT');
		$this->assertEquals('VAT', $line_item->get_tax_label());
	}

	/**
	 * Test getters and setters for tax_category.
	 */
	public function test_tax_category_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_tax_category('digital_goods');
		$this->assertEquals('digital_goods', $line_item->get_tax_category());
	}

	/**
	 * Test getters and setters for discountable.
	 */
	public function test_discountable_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_discountable(true);
		$this->assertTrue($line_item->is_discountable());

		$line_item->set_discountable(false);
		$this->assertFalse($line_item->is_discountable());
	}

	/**
	 * Test getters and setters for taxable.
	 */
	public function test_taxable_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_taxable(true);
		$this->assertTrue($line_item->is_taxable());

		$line_item->set_taxable(false);
		$this->assertFalse($line_item->is_taxable());
	}

	/**
	 * Test getters and setters for product_slug.
	 */
	public function test_product_slug_getter_setter(): void {

		$line_item = $this->create_line_item();

		$line_item->set_product_slug('premium-plan');
		$this->assertEquals('premium-plan', $line_item->get_product_slug());
	}

	/**
	 * Test get_recurring_description returns empty for non-recurring.
	 */
	public function test_get_recurring_description_non_recurring(): void {

		$line_item = $this->create_line_item(['recurring' => false]);

		$this->assertEquals('', $line_item->get_recurring_description());
	}

	/**
	 * Test get_recurring_description for recurring item.
	 */
	public function test_get_recurring_description_recurring(): void {

		$line_item = $this->create_line_item([
			'recurring'     => true,
			'duration'      => 1,
			'duration_unit' => 'month',
		]);

		$description = $line_item->get_recurring_description();

		$this->assertNotEmpty($description);
	}

	/**
	 * Test to_array returns all properties.
	 */
	public function test_to_array(): void {

		$line_item = $this->create_line_item([
			'title'      => 'Test',
			'unit_price' => 100,
			'quantity'   => 2,
		]);

		$array = $line_item->to_array();

		$this->assertIsArray($array);
		$this->assertArrayHasKey('title', $array);
		$this->assertArrayHasKey('unit_price', $array);
		$this->assertArrayHasKey('quantity', $array);
		$this->assertArrayHasKey('subtotal', $array);
		$this->assertArrayHasKey('total', $array);
		$this->assertArrayHasKey('type', $array);
		$this->assertArrayHasKey('recurring_description', $array);
	}

	/**
	 * Test jsonSerialize returns same as to_array.
	 */
	public function test_json_serialize(): void {

		$line_item = $this->create_line_item();

		$this->assertEquals($line_item->to_array(), $line_item->jsonSerialize());
	}

	/**
	 * Test json_encode works correctly.
	 */
	public function test_json_encode(): void {

		$line_item = $this->create_line_item(['title' => 'Premium Plan']);

		$json = json_encode($line_item);

		$this->assertIsString($json);
		$this->assertStringContainsString('Premium Plan', $json);
	}

	/**
	 * Test get_product returns false when no product is set.
	 */
	public function test_get_product_returns_false_when_no_product(): void {

		$line_item = $this->create_line_item();

		$this->assertFalse($line_item->get_product());
	}

	/**
	 * Test recalculate_totals returns the line item for chaining.
	 */
	public function test_recalculate_totals_returns_self(): void {

		$line_item = $this->create_line_item();

		$result = $line_item->recalculate_totals();

		$this->assertInstanceOf(Line_Item::class, $result);
	}

	/**
	 * Test zero unit price.
	 */
	public function test_zero_unit_price(): void {

		$line_item = $this->create_line_item(['unit_price' => 0]);

		$this->assertEquals(0, $line_item->get_subtotal());
		$this->assertEquals(0, $line_item->get_total());
	}

	/**
	 * Test different line item types.
	 *
	 * @dataProvider lineItemTypesProvider
	 */
	public function test_line_item_types(string $type): void {

		$line_item = $this->create_line_item(['type' => $type]);

		$this->assertEquals($type, $line_item->get_type());
		$this->assertStringContainsString(strtoupper($type), $line_item->get_id());
	}

	/**
	 * Data provider for line item types.
	 */
	public function lineItemTypesProvider(): array {

		return [
			'product'  => ['product'],
			'fee'      => ['fee'],
			'credit'   => ['credit'],
			'discount' => ['discount'],
			'prorate'  => ['prorate'],
		];
	}
}
