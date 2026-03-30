<?php
/**
 * Tests for the Limitable interface.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Models\Interfaces
 * @since 2.0.0
 */

namespace WP_Ultimo\Models\Interfaces;

use WP_UnitTestCase;
use WP_Ultimo\Models\Product;

/**
 * Test class for the Limitable interface (interface-limitable.php).
 *
 * The interface is verified through Product, which implements it via Trait_Limitable.
 */
class Interface_Limitable_Test extends WP_UnitTestCase {

	/**
	 * Test that Product implements the Limitable interface.
	 */
	public function test_product_implements_limitable_interface(): void {

		$product = new Product();

		$this->assertInstanceOf(Limitable::class, $product);
	}

	/**
	 * Test that Limitable interface declares limitations_to_merge.
	 */
	public function test_interface_declares_limitations_to_merge(): void {

		$this->assertTrue(method_exists(Limitable::class, 'limitations_to_merge'));
	}

	/**
	 * Test that Limitable interface declares get_limitations.
	 */
	public function test_interface_declares_get_limitations(): void {

		$this->assertTrue(method_exists(Limitable::class, 'get_limitations'));
	}

	/**
	 * Test that Limitable interface declares has_limitations.
	 */
	public function test_interface_declares_has_limitations(): void {

		$this->assertTrue(method_exists(Limitable::class, 'has_limitations'));
	}

	/**
	 * Test that Limitable interface declares has_module_limitation.
	 */
	public function test_interface_declares_has_module_limitation(): void {

		$this->assertTrue(method_exists(Limitable::class, 'has_module_limitation'));
	}

	/**
	 * Test that Limitable interface declares get_user_role_quotas.
	 */
	public function test_interface_declares_get_user_role_quotas(): void {

		$this->assertTrue(method_exists(Limitable::class, 'get_user_role_quotas'));
	}

	/**
	 * Test that Limitable interface declares get_allowed_user_roles.
	 */
	public function test_interface_declares_get_allowed_user_roles(): void {

		$this->assertTrue(method_exists(Limitable::class, 'get_allowed_user_roles'));
	}

	/**
	 * Test that Limitable interface declares sync_plugins.
	 */
	public function test_interface_declares_sync_plugins(): void {

		$this->assertTrue(method_exists(Limitable::class, 'sync_plugins'));
	}

	/**
	 * Test that Limitable interface declares handle_limitations.
	 */
	public function test_interface_declares_handle_limitations(): void {

		$this->assertTrue(method_exists(Limitable::class, 'handle_limitations'));
	}

	/**
	 * Test that Limitable interface declares get_applicable_product_slugs.
	 */
	public function test_interface_declares_get_applicable_product_slugs(): void {

		$this->assertTrue(method_exists(Limitable::class, 'get_applicable_product_slugs'));
	}

	/**
	 * Test get_limitations returns a Limitations object.
	 */
	public function test_get_limitations_returns_limitations_object(): void {

		$product = new Product();
		$product->set_name('Test Plan');
		$product->set_type('plan');
		$product->set_slug('test-plan-' . wp_rand());

		$limitations = $product->get_limitations();

		$this->assertInstanceOf(\WP_Ultimo\Objects\Limitations::class, $limitations);
	}

	/**
	 * Test has_limitations returns bool.
	 */
	public function test_has_limitations_returns_bool(): void {

		$product = new Product();
		$product->set_name('Test Plan');
		$product->set_type('plan');
		$product->set_slug('test-plan-' . wp_rand());

		$result = $product->has_limitations();

		$this->assertIsBool($result);
	}

	/**
	 * Test get_user_role_quotas returns array.
	 */
	public function test_get_user_role_quotas_returns_array(): void {

		$product = new Product();
		$product->set_name('Test Plan');
		$product->set_type('plan');
		$product->set_slug('test-plan-' . wp_rand());

		$result = $product->get_user_role_quotas();

		$this->assertIsArray($result);
	}

	/**
	 * Test get_allowed_user_roles returns array.
	 */
	public function test_get_allowed_user_roles_returns_array(): void {

		$product = new Product();
		$product->set_name('Test Plan');
		$product->set_type('plan');
		$product->set_slug('test-plan-' . wp_rand());

		$result = $product->get_allowed_user_roles();

		$this->assertIsArray($result);
	}

	/**
	 * Test get_applicable_product_slugs returns array for product model.
	 */
	public function test_get_applicable_product_slugs_returns_array_for_product(): void {

		$slug    = 'test-plan-' . wp_rand();
		$product = new Product();
		$product->set_name('Test Plan');
		$product->set_type('plan');
		$product->set_slug($slug);

		$slugs = $product->get_applicable_product_slugs();

		$this->assertIsArray($slugs);
		$this->assertContains($slug, $slugs);
	}
}
