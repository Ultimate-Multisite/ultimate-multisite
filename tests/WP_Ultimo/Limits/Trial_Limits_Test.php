<?php

namespace WP_Ultimo\Limits;

/**
 * Tests for the Trial_Limits class.
 */
class Trial_Limits_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh Trial_Limits instance via reflection.
	 * Avoids calling init() which may have dependencies.
	 *
	 * @return Trial_Limits
	 */
	private function get_instance() {

		// Create instance directly to bypass Singleton init()
		$ref = new \ReflectionClass(Trial_Limits::class);
		$instance = $ref->newInstanceWithoutConstructor();

		return $instance;
	}

	/**
	 * Test class exists and has correct name.
	 */
	public function test_class_exists() {

		$this->assertTrue(class_exists(Trial_Limits::class));
	}

	/**
	 * Test class uses Singleton trait.
	 */
	public function test_uses_singleton_trait() {

		$instance = $this->get_instance();

		$traits = class_uses($instance);

		$this->assertContains(\WP_Ultimo\Traits\Singleton::class, $traits);
	}

	/**
	 * Test get_instance returns correct class.
	 */
	public function test_get_instance_returns_correct_class() {

		$instance = Trial_Limits::get_instance();

		$this->assertInstanceOf(Trial_Limits::class, $instance);
	}

	/**
	 * Test get_instance returns same instance (singleton).
	 */
	public function test_get_instance_returns_same_instance() {

		$first = Trial_Limits::get_instance();
		$second = Trial_Limits::get_instance();

		$this->assertSame($first, $second);
	}

	/**
	 * Test init method exists and has void return type.
	 */
	public function test_init_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'init'));

		$ref = new \ReflectionMethod($instance, 'init');

		$this->assertTrue($ref->hasReturnType());
		$this->assertEquals('void', $ref->getReturnType()->getName());
	}

	/**
	 * Test init method is public.
	 */
	public function test_init_is_public() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'init');

		$this->assertTrue($ref->isPublic());
	}

	/**
	 * Test load_limitations method exists.
	 */
	public function test_load_limitations_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'load_limitations'));
	}

	/**
	 * Test load_limitations method is public.
	 */
	public function test_load_limitations_is_public() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'load_limitations');

		$this->assertTrue($ref->isPublic());
	}

	/**
	 * Test load_limitations can be called without errors.
	 */
	public function test_load_limitations_can_be_called() {

		$instance = $this->get_instance();

		// Should not throw any errors
		$instance->load_limitations();

		$this->assertTrue(true);
	}

	/**
	 * Test init can be called without errors.
	 */
	public function test_init_can_be_called() {

		$instance = $this->get_instance();

		// Should not throw any errors
		$instance->init();

		$this->assertTrue(true);
	}
}
