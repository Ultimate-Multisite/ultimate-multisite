<?php
/**
 * Tests for tax breakthrough rate grouping.
 *
 * @package WP_Ultimo
 * @subpackage Tests\Tax
 */

namespace WP_Ultimo\Tax;

use WP_Ultimo\Checkout\Cart;
use WP_Ultimo\Checkout\Line_Item;
use WP_Ultimo\Models\Payment;
use WP_UnitTestCase;

/**
 * Tests tax breakthrough precision for cart and payment summaries.
 */
class Tax_Breakthrough_Test extends WP_UnitTestCase {

	/**
	 * Test cart tax breakthrough preserves decimal tax rates.
	 */
	public function test_cart_tax_breakthrough_preserves_decimal_tax_rates(): void {
		$cart = new Cart([]);

		$cart->add_line_item($this->create_taxed_line_item('rate_725', 7.25));
		$cart->add_line_item($this->create_taxed_line_item('rate_775', 7.75));

		$breakthrough = $cart->get_tax_breakthrough();

		$this->assertArrayHasKey('7.25', $breakthrough);
		$this->assertArrayHasKey('7.75', $breakthrough);
		$this->assertArrayNotHasKey(7, $breakthrough);
		$this->assertEquals(7.25, $breakthrough['7.25']);
		$this->assertEquals(7.75, $breakthrough['7.75']);
	}

	/**
	 * Test payment tax breakthrough preserves decimal tax rates.
	 */
	public function test_payment_tax_breakthrough_preserves_decimal_tax_rates(): void {
		$payment = new Payment();

		$payment->set_line_items(
			[
				$this->create_taxed_line_item('rate_725', 7.25),
				$this->create_taxed_line_item('rate_775', 7.75),
			]
		);

		$breakthrough = $payment->get_tax_breakthrough();

		$this->assertArrayHasKey('7.25', $breakthrough);
		$this->assertArrayHasKey('7.75', $breakthrough);
		$this->assertArrayNotHasKey(7, $breakthrough);
		$this->assertEquals(7.25, $breakthrough['7.25']);
		$this->assertEquals(7.75, $breakthrough['7.75']);
	}

	/**
	 * Create a line item with a decimal tax rate.
	 *
	 * @param string $hash     Line item hash.
	 * @param float  $tax_rate Tax rate percentage.
	 * @return Line_Item
	 */
	private function create_taxed_line_item(string $hash, float $tax_rate): Line_Item {
		return new Line_Item(
			[
				'type'       => 'product',
				'hash'       => $hash,
				'title'      => sprintf('Rate %s', $tax_rate),
				'unit_price' => 100.00,
				'quantity'   => 1,
				'taxable'    => true,
				'tax_rate'   => $tax_rate,
			]
		);
	}
}
