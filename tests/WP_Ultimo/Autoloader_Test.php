<?php
/**
 * Tests for the Autoloader class.
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * @group autoloader
 */
class Autoloader_Test extends WP_UnitTestCase {

	// ------------------------------------------------------------------
	// init (deprecated, no-op)
	// ------------------------------------------------------------------

	public function test_init_does_not_throw() {
		// init() is deprecated and should be a no-op
		Autoloader::init();
		$this->assertTrue(true); // No exception thrown
	}

	// ------------------------------------------------------------------
	// is_debug
	// ------------------------------------------------------------------

	public function test_is_debug_returns_false() {
		$this->assertFalse(Autoloader::is_debug());
	}

	public function test_is_debug_returns_boolean() {
		$this->assertIsBool(Autoloader::is_debug());
	}

	// ------------------------------------------------------------------
	// Static instance property
	// ------------------------------------------------------------------

	public function test_instance_property_exists() {
		$this->assertTrue(property_exists(Autoloader::class, 'instance'));
	}

	// ------------------------------------------------------------------
	// Private constructor
	// ------------------------------------------------------------------

	public function test_constructor_is_private() {
		$ref         = new \ReflectionClass(Autoloader::class);
		$constructor = $ref->getConstructor();

		$this->assertTrue($constructor->isPrivate());
	}
}
