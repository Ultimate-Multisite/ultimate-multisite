<?php
/**
 * Tests for sort helper functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for sort helper functions.
 */
class Sort_Helpers_Test extends WP_UnitTestCase {

	/**
	 * Test wu_sort_by_column with default order column.
	 */
	public function test_sort_by_column_default(): void {
		$a = ['order' => 10];
		$b = ['order' => 20];

		$this->assertLessThan(0, wu_sort_by_column($a, $b));
		$this->assertGreaterThan(0, wu_sort_by_column($b, $a));
	}

	/**
	 * Test wu_sort_by_column with equal values.
	 */
	public function test_sort_by_column_equal(): void {
		$a = ['order' => 10];
		$b = ['order' => 10];

		$this->assertEquals(0, wu_sort_by_column($a, $b));
	}

	/**
	 * Test wu_sort_by_column with custom column.
	 */
	public function test_sort_by_column_custom(): void {
		$a = ['priority' => 5];
		$b = ['priority' => 15];

		$this->assertLessThan(0, wu_sort_by_column($a, $b, 'priority'));
		$this->assertGreaterThan(0, wu_sort_by_column($b, $a, 'priority'));
	}

	/**
	 * Test wu_sort_by_column with missing key defaults to 50.
	 */
	public function test_sort_by_column_missing_key(): void {
		$a = ['order' => 10];
		$b = []; // No order key, defaults to 50

		$this->assertLessThan(0, wu_sort_by_column($a, $b));

		$c = ['order' => 100];
		$this->assertGreaterThan(0, wu_sort_by_column($c, $b)); // 100 > 50
	}

	/**
	 * Test wu_sort_by_column with both missing keys.
	 */
	public function test_sort_by_column_both_missing(): void {
		$a = [];
		$b = [];

		$this->assertEquals(0, wu_sort_by_column($a, $b)); // Both default to 50
	}

	/**
	 * Test wu_sort_by_order function.
	 */
	public function test_sort_by_order(): void {
		$a = ['order' => 5];
		$b = ['order' => 25];

		$this->assertLessThan(0, wu_sort_by_order($a, $b));
		$this->assertGreaterThan(0, wu_sort_by_order($b, $a));
	}

	/**
	 * Test using wu_sort_by_column with usort.
	 */
	public function test_sort_by_column_with_usort(): void {
		$items = [
			[
				'name'  => 'C',
				'order' => 30,
			],
			[
				'name'  => 'A',
				'order' => 10,
			],
			[
				'name'  => 'B',
				'order' => 20,
			],
		];

		usort($items, fn($a, $b) => wu_sort_by_column($a, $b));

		$this->assertEquals('A', $items[0]['name']);
		$this->assertEquals('B', $items[1]['name']);
		$this->assertEquals('C', $items[2]['name']);
	}

	/**
	 * Test wu_set_order_from_index adds order to items.
	 */
	public function test_set_order_from_index_basic(): void {
		$items = [
			['name' => 'First'],
			['name' => 'Second'],
			['name' => 'Third'],
		];

		$result = wu_set_order_from_index($items);

		$this->assertEquals(10, $result[0]['order']);
		$this->assertEquals(20, $result[1]['order']);
		$this->assertEquals(30, $result[2]['order']);
	}

	/**
	 * Test wu_set_order_from_index preserves existing order.
	 */
	public function test_set_order_from_index_preserves_existing(): void {
		$items = [
			[
				'name'  => 'First',
				'order' => 5,
			],
			['name' => 'Second'],
			[
				'name'  => 'Third',
				'order' => 100,
			],
		];

		$result = wu_set_order_from_index($items);

		$this->assertEquals(5, $result[0]['order']); // Preserved
		// Index counter continues regardless of existing order values
		$this->assertEquals(10, $result[1]['order']); // Added (first item without order gets index 1 * 10)
		$this->assertEquals(100, $result[2]['order']); // Preserved
	}

	/**
	 * Test wu_set_order_from_index with custom key.
	 */
	public function test_set_order_from_index_custom_key(): void {
		$items = [
			['name' => 'First'],
			['name' => 'Second'],
		];

		$result = wu_set_order_from_index($items, 'priority');

		$this->assertEquals(10, $result[0]['priority']);
		$this->assertEquals(20, $result[1]['priority']);
	}

	/**
	 * Test wu_set_order_from_index with empty array.
	 */
	public function test_set_order_from_index_empty(): void {
		$result = wu_set_order_from_index([]);

		$this->assertEmpty($result);
		$this->assertIsArray($result);
	}

	/**
	 * Test wu_set_order_from_index with single item.
	 */
	public function test_set_order_from_index_single_item(): void {
		$items = [['name' => 'Only']];

		$result = wu_set_order_from_index($items);

		$this->assertEquals(10, $result[0]['order']);
	}
}
