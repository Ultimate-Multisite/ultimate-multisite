<?php
/**
 * Unit tests for Cache_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Cache_Manager;

class Cache_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Cache_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	/**
	 * Test flush_known_caches runs without error.
	 */
	public function test_flush_known_caches_does_not_throw(): void {

		$manager = $this->get_manager_instance();

		// Should not throw even when no caching plugins are active.
		$manager->flush_known_caches();

		$this->assertTrue(true);
	}
}
