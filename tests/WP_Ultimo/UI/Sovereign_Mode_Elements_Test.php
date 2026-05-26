<?php
/**
 * Tests for UI element skip-output filters.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Test class for UI element skip-output filters.
 */
class Sovereign_Mode_Elements_Test extends WP_UnitTestCase {

	/**
	 * Filter map to test.
	 *
	 * @var array<string, object>
	 */
	private array $elements = [];

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->elements = [
			'wu_account_skip_output'            => Account_Summary_Element::get_instance(),
			'wu_billing_info_skip_output'       => Billing_Info_Element::get_instance(),
			'wu_invoices_skip_output'           => Invoices_Element::get_instance(),
			'wu_my_sites_skip_output'           => My_Sites_Element::get_instance(),
			'wu_current_membership_skip_output' => Current_Membership_Element::get_instance(),
			'wu_current_site_skip_output'       => Current_Site_Element::get_instance(),
			'wu_template_switching_skip_output' => Template_Switching_Element::get_instance(),
			'wu_domain_mapping_skip_output'     => Domain_Mapping_Element::get_instance(),
		];

		foreach (array_keys($this->elements) as $hook) {
			add_filter($hook, [$this, 'skip_output'], 10, 4);
		}
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		foreach (array_keys($this->elements) as $hook) {
			remove_filter($hook, [$this, 'skip_output']);
		}

		parent::tearDown();
	}

	/**
	 * Output filter callback.
	 *
	 * @param bool        $skip    Whether to skip default output.
	 * @param array       $atts    Element attributes.
	 * @param string|null $content Element content.
	 * @param object      $element Element instance.
	 * @return bool
	 */
	public function skip_output($skip, $atts, $content, $element): bool {
		unset($skip, $atts, $content, $element);

		echo '<div class="wu-filtered-output">Filtered output</div>';

		return true;
	}

	/**
	 * Test element output can be skipped by filters.
	 */
	public function test_elements_can_skip_output_via_filters(): void {

		foreach ($this->elements as $element) {
			ob_start();
			$element->output([]);
			$output = ob_get_clean();

			$this->assertStringContainsString('wu-filtered-output', $output);
			$this->assertStringContainsString('Filtered output', $output);
		}
	}
}
