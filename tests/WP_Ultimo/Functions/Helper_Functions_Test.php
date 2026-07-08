<?php
/**
 * Tests for core helper functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for core helper functions.
 */
class Helper_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_version returns a string.
	 */
	public function test_get_version(): void {

		$result = wu_get_version();

		$this->assertIsString($result);
	}

	/**
	 * Test wu_is_debug returns bool.
	 */
	public function test_is_debug(): void {

		$result = wu_is_debug();

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_is_must_use returns bool.
	 */
	public function test_is_must_use(): void {

		$result = wu_is_must_use();

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_get_isset with array and existing key.
	 */
	public function test_get_isset_existing_key(): void {

		$array = [
			'name' => 'John',
			'age'  => 30,
		];

		$this->assertEquals('John', wu_get_isset($array, 'name'));
		$this->assertEquals(30, wu_get_isset($array, 'age'));
	}

	/**
	 * Test wu_get_isset with missing key returns default.
	 */
	public function test_get_isset_missing_key(): void {

		$array = ['name' => 'John'];

		$this->assertFalse(wu_get_isset($array, 'missing'));
		$this->assertEquals('default', wu_get_isset($array, 'missing', 'default'));
	}

	/**
	 * Test wu_get_isset with object.
	 */
	public function test_get_isset_with_object(): void {

		$obj = (object) [
			'name' => 'Jane',
			'role' => 'admin',
		];

		$this->assertEquals('Jane', wu_get_isset($obj, 'name'));
		$this->assertEquals('admin', wu_get_isset($obj, 'role'));
	}

	/**
	 * Test wu_slugify returns prefixed string.
	 */
	public function test_slugify(): void {

		$result = wu_slugify('test_term');

		$this->assertEquals('wp-ultimo_test_term', $result);
	}

	/**
	 * Test wu_slugify with empty string.
	 */
	public function test_slugify_empty(): void {

		$result = wu_slugify('');

		$this->assertEquals('wp-ultimo_', $result);
	}

	/**
	 * Test wu_path returns path string.
	 */
	public function test_path(): void {

		$result = wu_path('test/file.php');

		$this->assertStringContainsString('test/file.php', $result);
	}

	/**
	 * Test wu_url returns URL string.
	 */
	public function test_url(): void {

		$result = wu_url('assets/test.js');

		$this->assertStringContainsString('assets/test.js', $result);
	}

	/**
	 * Test wu_are_code_comments_available returns bool.
	 */
	public function test_are_code_comments_available(): void {

		$result = wu_are_code_comments_available();

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_path_join with multiple parts.
	 */
	public function test_path_join(): void {

		$result = wu_path_join('/home', 'user', 'file.txt');

		$this->assertEquals('/home/user/file.txt', $result);
	}

	/**
	 * Test wu_path_join with trailing slashes.
	 */
	public function test_path_join_trailing_slashes(): void {

		$result = wu_path_join('/home/', 'user/', 'file.txt');

		$this->assertEquals('/home/user/file.txt', $result);
	}

	/**
	 * Test wu_path_join with empty parts.
	 */
	public function test_path_join_empty(): void {

		$result = wu_path_join();

		$this->assertEquals('', $result);
	}

	/**
	 * Test wu_get_function_caller returns string or null.
	 */
	public function test_get_function_caller(): void {

		$result = wu_get_function_caller(1);

		$this->assertTrue(is_string($result) || is_null($result));
	}

	/**
	 * Test wu_ignore_errors does not throw.
	 */
	public function test_ignore_errors(): void {

		// Should not throw even though the callback throws
		wu_ignore_errors(function () {
			throw new \RuntimeException('Test error');
		});

		// If we get here, the test passed
		$this->assertTrue(true);
	}

	/**
	 * Test wu_ignore_errors runs callback.
	 */
	public function test_ignore_errors_runs_callback(): void {

		$called = false;

		wu_ignore_errors(function () use (&$called) {
			$called = true;
		});

		$this->assertTrue($called);
	}

	/**
	 * Test wu_clean with string.
	 */
	public function test_clean_string(): void {

		$result = wu_clean('Hello World');

		$this->assertEquals('Hello World', $result);
	}

	/**
	 * Test wu_clean with array.
	 */
	public function test_clean_array(): void {

		$result = wu_clean(['Hello', 'World']);

		$this->assertIsArray($result);
		$this->assertEquals('Hello', $result[0]);
		$this->assertEquals('World', $result[1]);
	}

	/**
	 * Test wu_clean strips tags.
	 */
	public function test_clean_strips_tags(): void {

		$result = wu_clean('<script>alert("xss")</script>Hello');

		$this->assertStringNotContainsString('<script>', $result);
	}

	/**
	 * Test wu_kses_allowed_html returns array.
	 */
	public function test_kses_allowed_html(): void {

		$result = wu_kses_allowed_html();

		$this->assertIsArray($result);
		$this->assertNotEmpty($result);
		// Should include SVG tags
		$this->assertArrayHasKey('svg', $result);
		$this->assertArrayHasKey('path', $result);
		// Should include form elements
		$this->assertArrayHasKey('input', $result);
		$this->assertArrayHasKey('select', $result);
	}

	/**
	 * Test wu_request returns default when key not set.
	 */
	public function test_request_default(): void {

		$result = wu_request('nonexistent_key_xyz', 'default_value');

		$this->assertEquals('default_value', $result);
	}
}
