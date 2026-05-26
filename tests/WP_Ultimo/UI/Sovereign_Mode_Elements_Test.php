<?php
/**
 * Tests for sovereign-mode UI elements.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Test class for sovereign-mode element short-circuits.
 */
class Sovereign_Mode_Elements_Test extends WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Load the sovereign helper function.
		require_once dirname(__DIR__, 3) . '/inc/functions/sovereign.php';

		add_filter('wu_is_sovereign_tenant', [$this, 'return_true']);
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		remove_filter('wu_is_sovereign_tenant', [$this, 'return_true']);

		parent::tearDown();
	}

	/**
	 * Return true for filter callbacks.
	 */
	public function return_true(): bool {
		return true;
	}

	/**
	 * Test Account_Summary_Element outputs redirect in sovereign mode.
	 */
	public function test_account_summary_element_sovereign_mode(): void {
		$element = Account_Summary_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-sovereign-redirect', $output);
		$this->assertStringContainsString('manage on main site', $output);
		$this->assertStringContainsString('Your account', $output);
	}

	/**
	 * Test Billing_Info_Element outputs redirect in sovereign mode.
	 */
	public function test_billing_info_element_sovereign_mode(): void {
		$element = Billing_Info_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-sovereign-redirect', $output);
		$this->assertStringContainsString('manage on main site', $output);
		$this->assertStringContainsString('Billing information', $output);
	}

	/**
	 * Test Invoices_Element outputs redirect in sovereign mode.
	 */
	public function test_invoices_element_sovereign_mode(): void {
		$element = Invoices_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-sovereign-redirect', $output);
		$this->assertStringContainsString('manage on main site', $output);
		$this->assertStringContainsString('Invoices', $output);
	}

	/**
	 * Test My_Sites_Element outputs redirect in sovereign mode.
	 */
	public function test_my_sites_element_sovereign_mode(): void {
		$element = My_Sites_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-sovereign-redirect', $output);
		$this->assertStringContainsString('manage on main site', $output);
		$this->assertStringContainsString('My sites', $output);
	}

	/**
	 * Test Current_Membership_Element outputs redirect in sovereign mode.
	 */
	public function test_current_membership_element_sovereign_mode(): void {
		$element = Current_Membership_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-sovereign-redirect', $output);
		$this->assertStringContainsString('manage on main site', $output);
		$this->assertStringContainsString('Subscription', $output);
	}

	/**
	 * Test Current_Site_Element outputs redirect in sovereign mode.
	 */
	public function test_current_site_element_sovereign_mode(): void {
		$element = Current_Site_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-sovereign-redirect', $output);
		$this->assertStringContainsString('manage on main site', $output);
		$this->assertStringContainsString('Site actions', $output);
	}

	/**
	 * Test Template_Switching_Element outputs redirect in sovereign mode.
	 */
	public function test_template_switching_element_sovereign_mode(): void {
		$element = Template_Switching_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-sovereign-redirect', $output);
		$this->assertStringContainsString('manage on main site', $output);
		$this->assertStringContainsString('Template switching', $output);
	}

	/**
	 * Test Domain_Mapping_Element outputs redirect in sovereign mode.
	 */
	public function test_domain_mapping_element_sovereign_mode(): void {
		$element = Domain_Mapping_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-sovereign-redirect', $output);
		$this->assertStringContainsString('manage on main site', $output);
		$this->assertStringContainsString('Domain mapping', $output);
	}

	/**
	 * Test redirect output contains button element.
	 */
	public function test_redirect_output_contains_button(): void {
		$element = Account_Summary_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('<a class="button button-primary"', $output);
		$this->assertStringContainsString('href=', $output);
	}

	/**
	 * Test redirect output contains main site URL.
	 */
	public function test_redirect_output_contains_main_site_url(): void {
		$element = Account_Summary_Element::get_instance();

		ob_start();
		$element->output([]);
		$output = ob_get_clean();

		$this->assertStringContainsString('admin.php?page=account', $output);
		$this->assertStringContainsString('return_to=', $output);
	}
}
