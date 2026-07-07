<?php
/**
 * Tests for environment functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for environment functions.
 */
class Env_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_env_picker returns frontend content when not admin.
	 */
	public function test_env_picker_frontend(): void {
		$result = wu_env_picker('frontend_class', 'backend_class', false);

		$this->assertEquals('frontend_class', $result);
	}

	/**
	 * Test wu_env_picker returns backend content when admin.
	 */
	public function test_env_picker_backend(): void {
		$result = wu_env_picker('frontend_class', 'backend_class', true);

		$this->assertEquals('backend_class', $result);
	}

	/**
	 * Test wu_env_picker works with arrays.
	 */
	public function test_env_picker_with_arrays(): void {
		$frontend = ['class1', 'class2'];
		$backend  = ['class3', 'class4'];

		$result = wu_env_picker($frontend, $backend, false);
		$this->assertEquals($frontend, $result);

		$result = wu_env_picker($frontend, $backend, true);
		$this->assertEquals($backend, $result);
	}

	/**
	 * Test wu_env_picker works with booleans.
	 */
	public function test_env_picker_with_booleans(): void {
		$result = wu_env_picker(true, false, false);
		$this->assertTrue($result);

		$result = wu_env_picker(true, false, true);
		$this->assertFalse($result);
	}

	/**
	 * Test wu_env_picker works with numbers.
	 */
	public function test_env_picker_with_numbers(): void {
		$result = wu_env_picker(100, 200, false);
		$this->assertEquals(100, $result);

		$result = wu_env_picker(100, 200, true);
		$this->assertEquals(200, $result);
	}
}
