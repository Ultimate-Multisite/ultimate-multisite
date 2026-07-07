<?php
/**
 * Tests for array helper functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for array helper functions.
 */
class Array_Helpers_Test extends WP_UnitTestCase {

	/**
	 * Test wu_array_flatten with simple nested array.
	 */
	public function test_array_flatten_simple(): void {
		$array = [
			'a' => [
				'b' => 'value1',
				'c' => 'value2',
			],
		];

		$result = wu_array_flatten($array);

		$this->assertContains('value1', $result);
		$this->assertContains('value2', $result);
	}

	/**
	 * Test wu_array_flatten with deeply nested array.
	 */
	public function test_array_flatten_deeply_nested(): void {
		$array = [
			'level1' => [
				'level2' => [
					'level3' => 'deep_value',
				],
			],
		];

		$result = wu_array_flatten($array);

		$this->assertContains('deep_value', $result);
	}

	/**
	 * Test wu_array_flatten with indexes.
	 */
	public function test_array_flatten_with_indexes(): void {
		$array = [
			'key1' => 'value1',
			'key2' => 'value2',
		];

		$result = wu_array_flatten($array, true);

		$this->assertContains('key1', $result);
		$this->assertContains('value1', $result);
		$this->assertContains('key2', $result);
		$this->assertContains('value2', $result);
	}

	/**
	 * Test wu_array_flatten with empty array.
	 */
	public function test_array_flatten_empty(): void {
		$result = wu_array_flatten([]);

		$this->assertEmpty($result);
		$this->assertIsArray($result);
	}

	/**
	 * Test wu_array_merge_recursive_distinct basic merge.
	 */
	public function test_array_merge_recursive_distinct_basic(): void {
		$array1 = ['key' => 'value1'];
		$array2 = ['key' => 'value2'];

		$result = wu_array_merge_recursive_distinct($array1, $array2);

		$this->assertEquals('value2', $result['key']); // Second array wins
	}

	/**
	 * Test wu_array_merge_recursive_distinct adds new keys.
	 */
	public function test_array_merge_recursive_distinct_adds_keys(): void {
		$array1 = ['key1' => 'value1'];
		$array2 = ['key2' => 'value2'];

		$result = wu_array_merge_recursive_distinct($array1, $array2);

		$this->assertEquals('value1', $result['key1']);
		$this->assertEquals('value2', $result['key2']);
	}

	/**
	 * Test wu_array_merge_recursive_distinct with nested arrays.
	 */
	public function test_array_merge_recursive_distinct_nested(): void {
		$array1 = [
			'nested' => [
				'a' => 1,
				'b' => 2,
			],
		];
		$array2 = [
			'nested' => [
				'b' => 3,
				'c' => 4,
			],
		];

		$result = wu_array_merge_recursive_distinct($array1, $array2);

		$this->assertEquals(1, $result['nested']['a']);
		$this->assertEquals(5, $result['nested']['b']); // 2 + 3 = 5 (summed)
		$this->assertEquals(4, $result['nested']['c']);
	}

	/**
	 * Test wu_array_merge_recursive_distinct sums numeric values.
	 */
	public function test_array_merge_recursive_distinct_sums_numeric(): void {
		$array1 = ['count' => 10];
		$array2 = ['count' => 5];

		$result = wu_array_merge_recursive_distinct($array1, $array2, true);

		$this->assertEquals(15, $result['count']); // 10 + 5 = 15
	}

	/**
	 * Test wu_array_merge_recursive_distinct without summing.
	 */
	public function test_array_merge_recursive_distinct_no_sum(): void {
		$array1 = ['count' => 10];
		$array2 = ['count' => 5];

		$result = wu_array_merge_recursive_distinct($array1, $array2, false);

		$this->assertEquals(5, $result['count']); // Replaced, not summed
	}

	/**
	 * Test wu_array_recursive_diff basic diff.
	 */
	public function test_array_recursive_diff_basic(): void {
		$array1 = [
			'a' => 1,
			'b' => 2,
			'c' => 3,
		];
		$array2 = [
			'a' => 1,
			'b' => 2,
			'c' => 3,
		];

		$result = wu_array_recursive_diff($array1, $array2);

		$this->assertEmpty($result); // Arrays are identical
	}

	/**
	 * Test wu_array_recursive_diff finds differences.
	 */
	public function test_array_recursive_diff_finds_differences(): void {
		$array1 = [
			'a' => 1,
			'b' => 2,
		];
		$array2 = [
			'a' => 1,
			'b' => 3,
		];

		$result = wu_array_recursive_diff($array1, $array2);

		$this->assertArrayHasKey('b', $result);
		$this->assertEquals(2, $result['b']);
	}

	/**
	 * Test wu_array_recursive_diff with missing keys.
	 */
	public function test_array_recursive_diff_missing_keys(): void {
		$array1 = [
			'a' => 1,
			'b' => 2,
			'c' => 3,
		];
		$array2 = ['a' => 1];

		$result = wu_array_recursive_diff($array1, $array2);

		$this->assertArrayHasKey('b', $result);
		$this->assertArrayHasKey('c', $result);
	}

	/**
	 * Test wu_array_recursive_diff with nested arrays.
	 */
	public function test_array_recursive_diff_nested(): void {
		$array1 = [
			'nested' => [
				'a' => 1,
				'b' => 2,
			],
		];
		$array2 = [
			'nested' => [
				'a' => 1,
				'b' => 3,
			],
		];

		$result = wu_array_recursive_diff($array1, $array2);

		$this->assertArrayHasKey('nested', $result);
		$this->assertArrayHasKey('b', $result['nested']);
	}

	/**
	 * Test wu_array_map_keys function.
	 */
	public function test_array_map_keys(): void {
		$array = [
			'first_key'  => 'value1',
			'second_key' => 'value2',
		];

		$result = wu_array_map_keys('strtoupper', $array);

		$this->assertArrayHasKey('FIRST_KEY', $result);
		$this->assertArrayHasKey('SECOND_KEY', $result);
		$this->assertEquals('value1', $result['FIRST_KEY']);
		$this->assertEquals('value2', $result['SECOND_KEY']);
	}

	/**
	 * Test wu_key_map_to_array basic conversion.
	 */
	public function test_key_map_to_array_basic(): void {
		$assoc_array = [
			'key1' => 'value1',
			'key2' => 'value2',
		];

		$result = wu_key_map_to_array($assoc_array);

		$this->assertCount(2, $result);
		$this->assertEquals('key1', $result[0]['id']);
		$this->assertEquals('value1', $result[0]['value']);
		$this->assertEquals('key2', $result[1]['id']);
		$this->assertEquals('value2', $result[1]['value']);
	}

	/**
	 * Test wu_key_map_to_array with custom key names.
	 */
	public function test_key_map_to_array_custom_names(): void {
		$assoc_array = ['foo' => 'bar'];

		$result = wu_key_map_to_array($assoc_array, 'name', 'data');

		$this->assertEquals('foo', $result[0]['name']);
		$this->assertEquals('bar', $result[0]['data']);
	}

	/**
	 * Test wu_key_map_to_array with empty array.
	 */
	public function test_key_map_to_array_empty(): void {
		$result = wu_key_map_to_array([]);

		$this->assertEmpty($result);
		$this->assertIsArray($result);
	}

	/**
	 * Test wu_array_find_first_by finds first match.
	 */
	public function test_array_find_first_by(): void {
		$array = [
			[
				'id'   => 1,
				'name' => 'Alice',
			],
			[
				'id'   => 2,
				'name' => 'Bob',
			],
			[
				'id'   => 3,
				'name' => 'Alice',
			],
		];

		$result = wu_array_find_first_by($array, 'name', 'Alice');

		$this->assertIsArray($result);
		$this->assertEquals(1, $result['id']);
	}

	/**
	 * Test wu_array_find_last_by finds last match.
	 */
	public function test_array_find_last_by(): void {
		$array = [
			[
				'id'   => 1,
				'name' => 'Alice',
			],
			[
				'id'   => 2,
				'name' => 'Bob',
			],
			[
				'id'   => 3,
				'name' => 'Alice',
			],
		];

		$result = wu_array_find_last_by($array, 'name', 'Alice');

		$this->assertIsArray($result);
		$this->assertEquals(3, $result['id']);
	}

	/**
	 * Test wu_array_find_all_by finds all matches.
	 */
	public function test_array_find_all_by(): void {
		$array = [
			[
				'id'   => 1,
				'name' => 'Alice',
			],
			[
				'id'   => 2,
				'name' => 'Bob',
			],
			[
				'id'   => 3,
				'name' => 'Alice',
			],
		];

		$result = wu_array_find_all_by($array, 'name', 'Alice');

		$this->assertCount(2, $result);
	}

	/**
	 * Test wu_array_find_first_by with no match.
	 */
	public function test_array_find_first_by_no_match(): void {
		$array = [
			[
				'id'   => 1,
				'name' => 'Alice',
			],
			[
				'id'   => 2,
				'name' => 'Bob',
			],
		];

		$result = wu_array_find_first_by($array, 'name', 'Charlie');

		$this->assertFalse($result);
	}
}
