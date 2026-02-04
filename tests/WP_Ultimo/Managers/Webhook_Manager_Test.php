<?php
/**
 * Unit tests for Webhook_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Webhook_Manager;

class Webhook_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Webhook_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'webhook';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Webhook::class;
	}
}
