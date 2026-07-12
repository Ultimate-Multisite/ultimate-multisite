<?php
/**
 * Unit tests for Visits_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Visits_Manager;

class Visits_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Visits_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	/**
	 * Test visit limits use the same strict exceeded decision as enforcement.
	 */
	public function test_get_visit_lock_status_reports_exceeded_limit(): void {
		wu_save_setting('enable_visits_limiting', true);

		$site = $this->createMock(\WP_Ultimo\Models\Site::class);
		$site->method('get_limitations')->willReturn(new \WP_Ultimo\Objects\Limitations(['visits' => ['limit' => 2]]));
		$site->method('get_visits_count')->willReturn(3);
		$site->method('has_limitations')->willReturn(true);

		$status = Visits_Manager::get_instance()->get_visit_lock_status($site);

		$this->assertTrue($status['locked']);
		$this->assertSame(2, $status['limit']);
		$this->assertSame(3, $status['count']);
	}

	/**
	 * Test disabled visit limiting never locks a site.
	 */
	public function test_get_visit_lock_status_ignores_disabled_visits(): void {
		wu_save_setting('enable_visits_limiting', false);

		$site = $this->createMock(\WP_Ultimo\Models\Site::class);

		$status = Visits_Manager::get_instance()->get_visit_lock_status($site);

		$this->assertFalse($status['locked']);
		$this->assertSame(0, $status['limit']);
		$this->assertSame(0, $status['count']);
	}
}
