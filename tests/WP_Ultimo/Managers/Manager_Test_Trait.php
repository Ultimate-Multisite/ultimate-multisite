<?php
/**
 * Shared test trait for all Manager classes.
 *
 * Provides common assertions that apply to every manager:
 * singleton behavior, slug/model_class properties, and hook registration.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

/**
 * Common manager test methods.
 *
 * Classes using this trait MUST define:
 *   - get_manager_class(): string — fully-qualified class name
 *   - get_expected_slug(): string|null — expected $slug value (null if not set)
 *   - get_expected_model_class(): string|null — expected $model_class value (null if not set)
 */
trait Manager_Test_Trait {

	/**
	 * Return the fully-qualified manager class name.
	 *
	 * @return string
	 */
	abstract protected function get_manager_class(): string;

	/**
	 * Return the expected $slug value, or null if the manager doesn't define one.
	 *
	 * @return string|null
	 */
	abstract protected function get_expected_slug(): ?string;

	/**
	 * Return the expected $model_class value, or null if not defined.
	 *
	 * @return string|null
	 */
	abstract protected function get_expected_model_class(): ?string;

	/**
	 * Get the singleton instance of the manager under test.
	 *
	 * @return object
	 */
	protected function get_manager_instance(): object {

		$class = $this->get_manager_class();

		return $class::get_instance();
	}

	/**
	 * Read a protected/private property via reflection.
	 *
	 * @param object $object The object to read from.
	 * @param string $property The property name.
	 * @return mixed
	 */
	protected function get_protected_property(object $object, string $property) {

		$reflection = new \ReflectionClass($object);
		$prop       = $reflection->getProperty($property);

		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

		return $prop->getValue($object);
	}

	/**
	 * Test that get_instance() returns the correct class.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$class   = $this->get_manager_class();
		$manager = $class::get_instance();

		$this->assertInstanceOf($class, $manager);
	}

	/**
	 * Test that get_instance() always returns the same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$class = $this->get_manager_class();

		$this->assertSame($class::get_instance(), $class::get_instance());
	}

	/**
	 * Test the $slug protected property when expected.
	 */
	public function test_slug_property(): void {

		$expected = $this->get_expected_slug();

		if (null === $expected) {
			$this->assertTrue(true, 'Manager does not define a slug.');
			return;
		}

		$manager = $this->get_manager_instance();
		$slug    = $this->get_protected_property($manager, 'slug');

		$this->assertEquals($expected, $slug);
	}

	/**
	 * Test the $model_class protected property when expected.
	 */
	public function test_model_class_property(): void {

		$expected = $this->get_expected_model_class();

		if (null === $expected) {
			$this->assertTrue(true, 'Manager does not define a model_class.');
			return;
		}

		$manager     = $this->get_manager_instance();
		$model_class = $this->get_protected_property($manager, 'model_class');

		$this->assertEquals($expected, $model_class);
	}
}
