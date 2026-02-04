<?php
/**
 * Unit tests for Current class.
 */

namespace WP_Ultimo;

/**
 * Unit tests for Current class.
 */
class Current_Test extends \WP_UnitTestCase {

	/**
	 * Current instance.
	 *
	 * @var Current
	 */
	protected $current;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->current = Current::get_instance();
	}

	/**
	 * Test that load_currents skips re-run on admin when site is already set.
	 */
	public function test_load_currents_skips_rerun_in_admin(): void {

		set_current_screen('dashboard');

		// Create a site object and pre-set it to simulate the init run.
		$site = wu_get_current_site();
		$this->current->set_site($site);

		// Confirm init has already fired (it has in test bootstrap).
		$this->assertGreaterThan(0, did_action('init'), 'init should have fired.');

		// Record the site before calling load_currents again.
		$site_before = $this->current->get_site();

		// Call load_currents again — this simulates the wp hook re-run.
		$this->current->load_currents();

		// Site should be the same object — the early return skipped re-processing.
		$this->assertSame(
			$site_before,
			$this->current->get_site(),
			'load_currents should skip re-run on admin when site is already set.'
		);
	}

	/**
	 * Test that load_currents runs fully on frontend even when site is set.
	 */
	public function test_load_currents_runs_on_frontend(): void {

		set_current_screen('front');

		// Pre-set a site to simulate the init run.
		$site = wu_get_current_site();
		$this->current->set_site($site);

		// On frontend, load_currents should NOT early-return —
		// it needs to check for query var overrides.
		// We verify by checking it doesn't throw and completes.
		$this->current->load_currents();

		// The site should still be set (may be same or updated from URL params).
		$this->assertNotNull(
			$this->current->get_site(),
			'load_currents should complete on frontend and site should remain set.'
		);
	}

	/**
	 * Test that load_currents runs on the first call (init hook).
	 */
	public function test_load_currents_runs_on_first_init_call(): void {

		set_current_screen('dashboard');

		// Clear the site to simulate a fresh state.
		$this->current->set_site(null);

		// With site = null, the guard should NOT trigger even in admin,
		// because the condition requires $this->site to be truthy.
		$this->current->load_currents();

		// After the first run the site should be populated (admin branch calls wu_get_current_site).
		$this->assertNotNull(
			$this->current->get_site(),
			'First load_currents call should populate the site even in admin.'
		);
	}

	/**
	 * Test that set_site and get_site work correctly.
	 */
	public function test_set_and_get_site(): void {

		$site = wu_get_current_site();
		$this->current->set_site($site);

		$this->assertSame($site, $this->current->get_site());
	}

	/**
	 * Test that set_customer and get_customer work correctly.
	 */
	public function test_set_and_get_customer(): void {

		$this->current->set_customer(null);
		$this->assertNull($this->current->get_customer());

		$user_id  = $this->factory()->user->create(['role' => 'subscriber']);
		$customer = wu_create_customer(
			[
				'user_id'       => $user_id,
				'email_address' => 'current-test@example.com',
			]
		);

		$this->current->set_customer($customer);
		$this->assertSame($customer, $this->current->get_customer());
	}

	/**
	 * Test that set_membership and get_membership work correctly.
	 */
	public function test_set_and_get_membership(): void {

		$this->current->set_membership(false);
		$this->assertFalse($this->current->get_membership());
	}

	/**
	 * Test param_key returns expected defaults.
	 */
	public function test_param_key_returns_defaults(): void {

		$this->assertEquals('site', Current::param_key('site'));
		$this->assertEquals('customer', Current::param_key('customer'));
		$this->assertEquals('membership', Current::param_key('membership'));
	}

	/**
	 * Test param_key falls back to the type string for unknown types.
	 */
	public function test_param_key_falls_back_for_unknown_type(): void {

		$this->assertEquals('unknown', Current::param_key('unknown'));
	}

	/**
	 * Test load_currents skips re-run during AJAX in admin.
	 */
	public function test_load_currents_skips_rerun_during_ajax(): void {

		set_current_screen('front');

		// Simulate AJAX context.
		add_filter('wp_doing_ajax', '__return_true');

		$site = wu_get_current_site();
		$this->current->set_site($site);

		$site_before = $this->current->get_site();

		$this->current->load_currents();

		$this->assertSame(
			$site_before,
			$this->current->get_site(),
			'load_currents should skip re-run during AJAX when site is already set.'
		);

		remove_filter('wp_doing_ajax', '__return_true');
	}
}
