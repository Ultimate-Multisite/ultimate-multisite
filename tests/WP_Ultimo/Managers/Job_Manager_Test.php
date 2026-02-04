<?php
/**
 * Unit tests for Job_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Job_Manager;

class Job_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Job_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}
}
