<?php

namespace WP_Ultimo\Tax;

use WP_UnitTestCase;

class Dashboard_Taxes_Tab_Test extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
	}

	protected function tearDown(): void {
		remove_all_filters('pre_site_transient_wu_tax_monthly_stats');
		parent::tearDown();
	}

	/**
	 * Test that register_scripts method registers the correct scripts.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_register_scripts_registers_scripts(): void {
		// Mock the wu_calculate_taxes_by_month function to avoid database queries
		add_filter(
			'pre_site_transient_wu_tax_monthly_stats',
			function () {
				$mock_tax_data = [];
				for ($i = 1; $i <= 12; $i++) {
					$mock_tax_data[$i] = [
						'order_count' => 0,
						'total_tax'   => 0,
					];
				}
				return $mock_tax_data;
			}
		);

		// Create a mock instance of Dashboard_Admin_Page and call the register_scripts method.
		$dashboard_admin_page = $this->getMockBuilder(Dashboard_Taxes_Tab::class)
									->disableOriginalConstructor()
									->setMethods(['output'])
									->getMock();

		// Execute register_scripts method.
		$dashboard_admin_page->register_scripts();

		// Assert scripts are registered.
		$this->assertTrue(wp_script_is('wu-tax-stats', 'registered'));

		// Assert scripts are enqueued.
		$this->assertTrue(wp_script_is('wu-tax-stats', 'enqueued'));

		// Verify localized script data is correct.
		$localized_vars = wp_scripts()->get_data('wu-tax-stats', 'data');
		$this->assertStringContainsString('"month_list":["Jan ', $localized_vars);
		$this->assertStringContainsString('"today":"', $localized_vars); // Check that today is included.
		$this->assertStringContainsString('"net_profit_label":"Net Profit"', $localized_vars);
	}
}
