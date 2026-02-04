<?php

namespace WP_Ultimo\Helpers;

use WP_UnitTestCase;

class Arr_Test extends WP_UnitTestCase {

	/**
	 * Test get with simple key.
	 */
	public function test_get_simple_key(): void {

		$array = ['name' => 'John', 'age' => 30];

		$this->assertEquals('John', Arr::get($array, 'name'));
		$this->assertEquals(30, Arr::get($array, 'age'));
	}

	/**
	 * Test get with dot notation.
	 */
	public function test_get_dot_notation(): void {

		$array = [
			'user' => [
				'name'    => 'John',
				'address' => [
					'city'    => 'New York',
					'country' => 'US',
				],
			],
		];

		$this->assertEquals('John', Arr::get($array, 'user.name'));
		$this->assertEquals('New York', Arr::get($array, 'user.address.city'));
		$this->assertEquals('US', Arr::get($array, 'user.address.country'));
	}

	/**
	 * Test get with missing key returns default.
	 */
	public function test_get_missing_key_returns_default(): void {

		$array = ['name' => 'John'];

		$this->assertNull(Arr::get($array, 'missing'));
		$this->assertEquals('default', Arr::get($array, 'missing', 'default'));
		$this->assertEquals(0, Arr::get($array, 'missing', 0));
	}

	/**
	 * Test get with null key returns entire array.
	 */
	public function test_get_null_key_returns_array(): void {

		$array = ['name' => 'John', 'age' => 30];

		$this->assertEquals($array, Arr::get($array, null));
	}

	/**
	 * Test get with missing nested key returns default.
	 */
	public function test_get_missing_nested_key_returns_default(): void {

		$array = ['user' => ['name' => 'John']];

		$this->assertNull(Arr::get($array, 'user.email'));
		$this->assertNull(Arr::get($array, 'user.address.city'));
	}

	/**
	 * Test set with simple key.
	 */
	public function test_set_simple_key(): void {

		$array = [];

		Arr::set($array, 'name', 'John');

		$this->assertEquals('John', $array['name']);
	}

	/**
	 * Test set with dot notation.
	 */
	public function test_set_dot_notation(): void {

		$array = [];

		Arr::set($array, 'user.name', 'John');
		Arr::set($array, 'user.address.city', 'New York');

		$this->assertEquals('John', $array['user']['name']);
		$this->assertEquals('New York', $array['user']['address']['city']);
	}

	/**
	 * Test set with null key replaces entire array.
	 */
	public function test_set_null_key_replaces_array(): void {

		$array = ['old' => 'data'];

		$result = Arr::set($array, null, 'new_value');

		$this->assertEquals('new_value', $result);
	}

	/**
	 * Test set overwrites existing values.
	 */
	public function test_set_overwrites_existing(): void {

		$array = ['user' => ['name' => 'John']];

		Arr::set($array, 'user.name', 'Jane');

		$this->assertEquals('Jane', $array['user']['name']);
	}

	/**
	 * Test set creates intermediate arrays.
	 */
	public function test_set_creates_intermediate_arrays(): void {

		$array = [];

		Arr::set($array, 'a.b.c.d', 'deep_value');

		$this->assertEquals('deep_value', $array['a']['b']['c']['d']);
	}

	/**
	 * Test filter with closure.
	 */
	public function test_filter_with_closure(): void {

		$array = [1, 2, 3, 4, 5, 6];

		$result = Arr::filter($array, function ($value) {
			return $value > 3;
		});

		$this->assertCount(3, $result);
		$this->assertEquals([4, 5, 6], $result);
	}

	/**
	 * Test filter preserves matching items.
	 */
	public function test_filter_preserves_matching_items(): void {

		$array = [
			['name' => 'John', 'active' => true],
			['name' => 'Jane', 'active' => false],
			['name' => 'Bob', 'active' => true],
		];

		$result = Arr::filter($array, function ($item) {
			return $item['active'] === true;
		});

		$this->assertCount(2, $result);
		$this->assertEquals('John', $result[0]['name']);
		$this->assertEquals('Bob', $result[1]['name']);
	}

	/**
	 * Test filter_by_property with simple match.
	 */
	public function test_filter_by_property_simple(): void {

		$array = [
			['name' => 'John', 'role' => 'admin'],
			['name' => 'Jane', 'role' => 'editor'],
			['name' => 'Bob', 'role' => 'admin'],
		];

		$result = Arr::filter_by_property($array, 'role', 'admin');

		$this->assertCount(2, $result);
	}

	/**
	 * Test filter_by_property with dot notation.
	 */
	public function test_filter_by_property_dot_notation(): void {

		$array = [
			['name' => 'John', 'meta' => ['status' => 'active']],
			['name' => 'Jane', 'meta' => ['status' => 'inactive']],
			['name' => 'Bob', 'meta' => ['status' => 'active']],
		];

		$result = Arr::filter_by_property($array, 'meta.status', 'active');

		$this->assertCount(2, $result);
	}

	/**
	 * Test filter_by_property returns first result.
	 */
	public function test_filter_by_property_returns_first(): void {

		$array = [
			['name' => 'John', 'role' => 'admin'],
			['name' => 'Bob', 'role' => 'admin'],
		];

		$result = Arr::filter_by_property($array, 'role', 'admin', Arr::RESULTS_FIRST);

		$this->assertIsArray($result);
		$this->assertEquals('John', $result['name']);
	}

	/**
	 * Test filter_by_property returns last result.
	 */
	public function test_filter_by_property_returns_last(): void {

		$array = [
			['name' => 'John', 'role' => 'admin'],
			['name' => 'Bob', 'role' => 'admin'],
		];

		$result = Arr::filter_by_property($array, 'role', 'admin', Arr::RESULTS_LAST);

		$this->assertIsArray($result);
		$this->assertEquals('Bob', $result['name']);
	}

	/**
	 * Test filter_by_property with no matches.
	 */
	public function test_filter_by_property_no_matches(): void {

		$array = [
			['name' => 'John', 'role' => 'admin'],
		];

		$result = Arr::filter_by_property($array, 'role', 'nonexistent');

		$this->assertEmpty($result);
	}

	/**
	 * Test constants exist.
	 */
	public function test_constants(): void {

		$this->assertEquals(0, Arr::RESULTS_ALL);
		$this->assertEquals(1, Arr::RESULTS_FIRST);
		$this->assertEquals(2, Arr::RESULTS_LAST);
	}
}
