<?php
/**
 * Unit tests for Block_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Block_Manager;

class Block_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Block_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	/**
	 * Test that add_wp_ultimo_block_category adds the category.
	 */
	public function test_add_wp_ultimo_block_category(): void {

		$manager    = $this->get_manager_instance();
		$categories = $manager->add_wp_ultimo_block_category([], null);

		$this->assertIsArray($categories);
		$this->assertNotEmpty($categories);

		$slugs = array_column($categories, 'slug');
		$this->assertContains('wp-ultimo', $slugs);
	}
}
