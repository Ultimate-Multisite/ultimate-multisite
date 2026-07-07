<?php
/**
 * Tests for options functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for options functions.
 */
class Options_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_option returns default for nonexistent.
	 */
	public function test_get_option_default(): void {

		$result = wu_get_option('nonexistent_option_xyz', 'default_val');

		$this->assertEquals('default_val', $result);
	}

	/**
	 * Test wu_save_option and wu_get_option roundtrip.
	 */
	public function test_save_and_get_option(): void {

		$saved = wu_save_option('test_option_roundtrip', 'test_value');

		$this->assertTrue($saved);

		$result = wu_get_option('test_option_roundtrip');

		$this->assertEquals('test_value', $result);

		// Cleanup
		wu_delete_option('test_option_roundtrip');
	}

	/**
	 * Test wu_save_option with array value.
	 */
	public function test_save_option_array(): void {

		$data = [
			'key1' => 'val1',
			'key2' => 'val2',
		];

		wu_save_option('test_option_array', $data);

		$result = wu_get_option('test_option_array');

		$this->assertIsArray($result);
		$this->assertEquals('val1', $result['key1']);
		$this->assertEquals('val2', $result['key2']);

		// Cleanup
		wu_delete_option('test_option_array');
	}

	/**
	 * Test wu_delete_option removes option.
	 */
	public function test_delete_option(): void {

		wu_save_option('test_option_delete', 'to_delete');

		$this->assertEquals('to_delete', wu_get_option('test_option_delete'));

		wu_delete_option('test_option_delete');

		$result = wu_get_option('test_option_delete', 'gone');

		$this->assertEquals('gone', $result);
	}

	/**
	 * Test wu_get_option with empty default.
	 */
	public function test_get_option_empty_default(): void {

		$result = wu_get_option('nonexistent_option_empty');

		$this->assertIsArray($result);
	}
}
