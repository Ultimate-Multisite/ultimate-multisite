<?php

namespace WP_Ultimo\Domain_Mapping\SSO;

/**
 * Tests for the deprecated SSO class.
 *
 * This class is a compatibility shim to prevent fatal errors when updating
 * from versions before 2.0.11. It has no functionality beyond existing.
 */
class SSO_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh SSO instance via reflection.
	 * Avoids calling init() which may have dependencies.
	 *
	 * @return SSO
	 */
	private function get_instance() {

		// Create instance directly to bypass Singleton init()
		$ref = new \ReflectionClass(SSO::class);
		$instance = $ref->newInstanceWithoutConstructor();

		return $instance;
	}

	/**
	 * Test class exists and has correct name.
	 */
	public function test_class_exists() {

		$this->assertTrue(class_exists(SSO::class));
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

		$instance = SSO::get_instance();

		$this->assertInstanceOf(SSO::class, $instance);
	}

	/**
	 * Test get_instance returns same instance (singleton).
	 */
	public function test_get_instance_returns_same_instance() {

		$first = SSO::get_instance();
		$second = SSO::get_instance();

		$this->assertSame($first, $second);
	}

	/**
	 * Test class is in correct namespace.
	 */
	public function test_class_namespace() {

		$ref = new \ReflectionClass(SSO::class);

		$this->assertEquals('WP_Ultimo\Domain_Mapping\SSO', $ref->getNamespaceName());
	}

	/**
	 * Test class is deprecated (has @deprecated tag in docblock).
	 */
	public function test_class_is_deprecated() {

		$ref = new \ReflectionClass(SSO::class);
		$docblock = $ref->getDocComment();

		$this->assertNotFalse($docblock);
		$this->assertStringContainsString('@deprecated', $docblock);
	}

	/**
	 * Test file header references the new SSO location.
	 */
	public function test_file_references_new_location() {

		$file_path = dirname(__DIR__, 3) . '/inc/domain-mapping/class-sso.php';
		$file_contents = file_get_contents($file_path);

		$this->assertNotFalse($file_contents);
		$this->assertStringContainsString('@see \WP_Ultimo\SSO', $file_contents);
	}

	/**
	 * Test class can be instantiated without errors.
	 */
	public function test_can_instantiate_without_errors() {

		$instance = SSO::get_instance();

		$this->assertNotNull($instance);
	}

	/**
	 * Test class has only Singleton trait public methods.
	 */
	public function test_has_only_singleton_public_methods() {

		$ref = new \ReflectionClass(SSO::class);
		$methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);

		$method_names = array_map(function ($method) {
			return $method->getName();
		}, $methods);

		// Should have get_instance, init, and has_parents from Singleton trait
		$expected_methods = ['get_instance', 'init', 'has_parents'];

		foreach ($expected_methods as $expected) {
			$this->assertContains($expected, $method_names);
		}

		// Filter out Singleton methods
		$non_singleton_methods = array_filter($method_names, function ($name) {
			return !in_array($name, ['get_instance', 'init', 'has_parents']);
		});

		$this->assertEmpty($non_singleton_methods, 'SSO should have no public methods beyond Singleton trait');
	}
}
