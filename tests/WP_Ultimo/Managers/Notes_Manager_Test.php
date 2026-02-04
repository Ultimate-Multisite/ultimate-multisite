<?php
/**
 * Unit tests for Notes_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Notes_Manager;

class Notes_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Notes_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'notes';
	}

	protected function get_expected_model_class(): ?string {
		return '\\WP_Ultimo\\Models\\Notes';
	}
}
