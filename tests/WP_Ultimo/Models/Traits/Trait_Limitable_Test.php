<?php
/**
 * Tests for the Limitable trait.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Models\Traits
 * @since 2.0.0
 */

namespace WP_Ultimo\Models\Traits;

use WP_UnitTestCase;
use WP_Ultimo\Models\Product;
use WP_Ultimo\Objects\Limitations;

/**
 * Test class for the Limitable trait (trait-limitable.php).
 *
 * Uses Product as the concrete class that uses this trait.
 */
class Trait_Limitable_Test extends WP_UnitTestCase {

	/**
	 * Product instance.
	 *
	 * @var Product
	 */
	protected $product;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->product = new Product();
		$this->product->set_name('Test Plan');
		$this->product->set_type('plan');
		$this->product->set_slug('test-plan-' . wp_rand());
	}

	/**
	 * Test Product uses the Limitable trait.
	 */
	public function test_product_uses_limitable_trait(): void {

		$traits = class_uses_recursive(Product::class);

		$this->assertContains(Limitable::class, $traits);
	}

	/**
	 * Test get_limitations returns a Limitations instance.
	 */
	public function test_get_limitations_returns_limitations_instance(): void {

		$result = $this->product->get_limitations();

		$this->assertInstanceOf(Limitations::class, $result);
	}

	/**
	 * Test get_limitations with waterfall=false returns Limitations instance.
	 */
	public function test_get_limitations_without_waterfall_returns_limitations(): void {

		$result = $this->product->get_limitations(false);

		$this->assertInstanceOf(Limitations::class, $result);
	}

	/**
	 * Test get_limitations with skip_self=true returns Limitations instance.
	 */
	public function test_get_limitations_with_skip_self_returns_limitations(): void {

		$result = $this->product->get_limitations(true, true);

		$this->assertInstanceOf(Limitations::class, $result);
	}

	/**
	 * Test has_limitations returns bool.
	 */
	public function test_has_limitations_returns_bool(): void {

		$result = $this->product->has_limitations();

		$this->assertIsBool($result);
	}

	/**
	 * Test has_module_limitation returns bool.
	 */
	public function test_has_module_limitation_returns_bool(): void {

		$result = $this->product->has_module_limitation('plugins');

		$this->assertIsBool($result);
	}

	/**
	 * Test get_user_role_quotas returns array.
	 */
	public function test_get_user_role_quotas_returns_array(): void {

		$result = $this->product->get_user_role_quotas();

		$this->assertIsArray($result);
	}

	/**
	 * Test get_allowed_user_roles returns array.
	 */
	public function test_get_allowed_user_roles_returns_array(): void {

		$result = $this->product->get_allowed_user_roles();

		$this->assertIsArray($result);
	}

	/**
	 * Test get_applicable_product_slugs returns array containing product slug.
	 */
	public function test_get_applicable_product_slugs_returns_slug_for_product(): void {

		$slug = 'my-plan-' . wp_rand();
		$this->product->set_slug($slug);

		$slugs = $this->product->get_applicable_product_slugs();

		$this->assertIsArray($slugs);
		$this->assertContains($slug, $slugs);
	}

	/**
	 * Test limitations are cached after first call.
	 */
	public function test_limitations_are_cached_after_first_call(): void {

		$first  = $this->product->get_limitations();
		$second = $this->product->get_limitations();

		$this->assertSame($first, $second);
	}

	/**
	 * Test limitations_to_merge returns array.
	 */
	public function test_limitations_to_merge_returns_array(): void {

		$result = $this->product->limitations_to_merge();

		$this->assertIsArray($result);
	}
}
