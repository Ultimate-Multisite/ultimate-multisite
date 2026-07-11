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

	/**
	 * The bundled MU-Migration WP-CLI polyfill must only load explicitly.
	 *
	 * Third-party plugins commonly use class_exists( 'WP_CLI' ) to detect a
	 * real CLI process. If either Composer or Jetpack Autoloader maps this
	 * polyfill, that probe loads a fake global WP_CLI facade during web requests.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_mu_migration_wp_cli_polyfill_is_not_autoloadable() {

		if (class_exists('WP_CLI', false)) {
			$this->markTestSkipped('Real WP-CLI is loaded.');
		}

		$this->assertFalse(class_exists('WP_CLI', false), 'The polyfill must not already be loaded on a web request.');
		$this->assertFalse(class_exists('WP_CLI'), 'The autoloaders must not resolve the MU-Migration WP-CLI polyfill.');
	}
}
