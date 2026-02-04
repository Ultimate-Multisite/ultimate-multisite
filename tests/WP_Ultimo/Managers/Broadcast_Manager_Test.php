<?php
/**
 * Unit tests for Broadcast_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Broadcast_Manager;

class Broadcast_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Broadcast_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'broadcast';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Broadcast::class;
	}

}
