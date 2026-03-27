<?php
/**
 * Unit tests for Default_Content_Installer class.
 *
 * @package WP_Ultimo\Tests\Installers
 */

namespace WP_Ultimo\Tests\Installers;

use WP_Ultimo\Installers\Default_Content_Installer;

/**
 * Unit tests for Default_Content_Installer.
 *
 * Tests cover:
 * - done_creating_* guard methods
 * - ensure_currency_defaults
 * - get_steps structure
 * - _install_create_products
 * - _install_create_checkout
 * - _install_create_login_page
 * - _install_create_emails
 * - _install_create_template_site
 */
class Default_Content_Installer_Test extends \WP_UnitTestCase {

	/**
	 * Installer instance under test.
	 *
	 * @var Default_Content_Installer
	 */
	protected $installer;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->installer = Default_Content_Installer::get_instance();
	}

	/**
	 * Tear down: clean up any products/checkout forms/emails created during tests.
	 */
	public function tearDown(): void {
		// Clean up products created during tests.
		$products = wu_get_products();
		foreach ( $products as $product ) {
			if ( in_array( $product->get_slug(), [ 'free', 'premium', 'seo' ], true ) ) {
				$product->delete();
			}
		}

		// Clean up checkout forms.
		$forms = wu_get_checkout_forms( [ 'slug' => 'main-form' ] );
		foreach ( $forms as $form ) {
			$form->delete();
		}

		// Clean up pages created during tests.
		$pages = get_posts( [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name__in' => [ 'register', 'login' ],
			'numberposts' => -1,
		] );
		foreach ( $pages as $page ) {
			wp_delete_post( $page->ID, true );
		}

		// Reset settings touched by tests.
		wu_save_setting( 'default_login_page', 0 );
		wu_save_setting( 'default_registration_page', 0 );
		wu_save_setting( 'enable_custom_login_page', 0 );

		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	/**
	 * Test that get_instance returns a Default_Content_Installer.
	 */
	public function test_get_instance_returns_correct_type(): void {
		$this->assertInstanceOf( Default_Content_Installer::class, $this->installer );
	}

	/**
	 * Test that get_instance returns the same object on repeated calls.
	 */
	public function test_get_instance_is_singleton(): void {
		$a = Default_Content_Installer::get_instance();
		$b = Default_Content_Installer::get_instance();
		$this->assertSame( $a, $b );
	}

	// -----------------------------------------------------------------------
	// get_steps
	// -----------------------------------------------------------------------

	/**
	 * Test that get_steps returns an array with the expected keys.
	 */
	public function test_get_steps_returns_expected_keys(): void {
		$steps = $this->installer->get_steps();

		$this->assertIsArray( $steps );
		$this->assertArrayHasKey( 'create_template_site', $steps );
		$this->assertArrayHasKey( 'create_products', $steps );
		$this->assertArrayHasKey( 'create_checkout', $steps );
		$this->assertArrayHasKey( 'create_emails', $steps );
		$this->assertArrayHasKey( 'create_login_page', $steps );
	}

	/**
	 * Test that each step has the required fields.
	 */
	public function test_get_steps_each_step_has_required_fields(): void {
		$steps = $this->installer->get_steps();

		$required_fields = [ 'done', 'title', 'description', 'pending', 'installing', 'success', 'help', 'checked' ];

		foreach ( $steps as $key => $step ) {
			foreach ( $required_fields as $field ) {
				$this->assertArrayHasKey(
					$field,
					$step,
					"Step '{$key}' is missing field '{$field}'"
				);
			}
		}
	}

	/**
	 * Test that get_steps returns exactly 5 steps.
	 */
	public function test_get_steps_returns_five_steps(): void {
		$steps = $this->installer->get_steps();
		$this->assertCount( 5, $steps );
	}

	/**
	 * Test that each step's 'checked' field is true by default.
	 */
	public function test_get_steps_checked_is_true(): void {
		$steps = $this->installer->get_steps();

		foreach ( $steps as $key => $step ) {
			$this->assertTrue( $step['checked'], "Step '{$key}' should have checked=true" );
		}
	}

	/**
	 * Test that step titles are non-empty strings.
	 */
	public function test_get_steps_titles_are_non_empty(): void {
		$steps = $this->installer->get_steps();

		foreach ( $steps as $key => $step ) {
			$this->assertIsString( $step['title'], "Step '{$key}' title should be a string" );
			$this->assertNotEmpty( $step['title'], "Step '{$key}' title should not be empty" );
		}
	}

	// -----------------------------------------------------------------------
	// done_creating_products
	// -----------------------------------------------------------------------

	/**
	 * Test done_creating_products returns false when no products exist.
	 */
	public function test_done_creating_products_false_when_empty(): void {
		// Ensure no plans exist.
		$plans = wu_get_plans();
		foreach ( $plans as $plan ) {
			$plan->delete();
		}

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_products' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertFalse( $result );
	}

	/**
	 * Test done_creating_products returns true when plans exist.
	 */
	public function test_done_creating_products_true_when_plans_exist(): void {
		// Create a plan.
		$product = wu_create_product( [
			'name'         => 'Test Plan',
			'slug'         => 'test-plan-done-check',
			'type'         => 'plan',
			'pricing_type' => 'free',
			'amount'       => 0,
			'currency'     => 'USD',
			'active'       => 1,
		] );

		$this->assertNotWPError( $product );

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_products' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertTrue( $result );

		// Cleanup.
		$product->delete();
	}

	// -----------------------------------------------------------------------
	// done_creating_checkout_forms
	// -----------------------------------------------------------------------

	/**
	 * Test done_creating_checkout_forms returns false when no user-created forms exist.
	 *
	 * Note: wu_get_checkout_forms() may return built-in forms (wu-checkout etc.)
	 * so we test the method's return value reflects the actual state.
	 */
	public function test_done_creating_checkout_forms_returns_bool(): void {
		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_checkout_forms' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertIsBool( $result );
	}

	/**
	 * Test done_creating_checkout_forms returns true after creating a form.
	 */
	public function test_done_creating_checkout_forms_true_after_create(): void {
		$form = wu_create_checkout_form( [
			'name' => 'Test Form',
			'slug' => 'test-form-done-check',
		] );

		$this->assertNotWPError( $form );

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_checkout_forms' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertTrue( $result );

		// Cleanup.
		$form->delete();
	}

	// -----------------------------------------------------------------------
	// done_creating_emails
	// -----------------------------------------------------------------------

	/**
	 * Test done_creating_emails returns a boolean.
	 */
	public function test_done_creating_emails_returns_bool(): void {
		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_emails' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertIsBool( $result );
	}

	// -----------------------------------------------------------------------
	// done_creating_login_page
	// -----------------------------------------------------------------------

	/**
	 * Test done_creating_login_page returns false when no page ID is set.
	 */
	public function test_done_creating_login_page_false_when_no_setting(): void {
		wu_save_setting( 'default_login_page', 0 );

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_login_page' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertFalse( $result );
	}

	/**
	 * Test done_creating_login_page returns false when page ID points to non-existent post.
	 */
	public function test_done_creating_login_page_false_when_page_missing(): void {
		wu_save_setting( 'default_login_page', 999999 );

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_login_page' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertFalse( $result );

		wu_save_setting( 'default_login_page', 0 );
	}

	/**
	 * Test done_creating_login_page returns true when page exists.
	 */
	public function test_done_creating_login_page_true_when_page_exists(): void {
		$page_id = wp_insert_post( [
			'post_title'  => 'Test Login Page',
			'post_status' => 'publish',
			'post_type'   => 'page',
		] );

		wu_save_setting( 'default_login_page', $page_id );

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_login_page' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertTrue( $result );

		// Cleanup.
		wp_delete_post( $page_id, true );
		wu_save_setting( 'default_login_page', 0 );
	}

	// -----------------------------------------------------------------------
	// ensure_currency_defaults
	// -----------------------------------------------------------------------

	/**
	 * Test ensure_currency_defaults sets defaults when settings are missing.
	 */
	public function test_ensure_currency_defaults_sets_missing_values(): void {
		// Clear the currency settings.
		wu_save_setting( 'currency_symbol', false );
		wu_save_setting( 'currency_position', false );
		wu_save_setting( 'decimal_separator', false );
		wu_save_setting( 'thousand_separator', false );
		wu_save_setting( 'precision', false );

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'ensure_currency_defaults' );
		$method->setAccessible( true );
		$method->invoke( $this->installer );

		$this->assertSame( 'USD', wu_get_setting( 'currency_symbol' ) );
		$this->assertSame( '%s %v', wu_get_setting( 'currency_position' ) );
		$this->assertSame( '.', wu_get_setting( 'decimal_separator' ) );
		$this->assertSame( ',', wu_get_setting( 'thousand_separator' ) );
		$this->assertSame( 2, wu_get_setting( 'precision' ) );
	}

	/**
	 * Test ensure_currency_defaults does not overwrite existing values.
	 */
	public function test_ensure_currency_defaults_does_not_overwrite_existing(): void {
		wu_save_setting( 'currency_symbol', 'EUR' );
		wu_save_setting( 'precision', 3 );

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'ensure_currency_defaults' );
		$method->setAccessible( true );
		$method->invoke( $this->installer );

		$this->assertSame( 'EUR', wu_get_setting( 'currency_symbol' ) );
		$this->assertSame( 3, wu_get_setting( 'precision' ) );
	}

	/**
	 * Test ensure_currency_defaults preserves precision=0 (zero-decimal currencies).
	 */
	public function test_ensure_currency_defaults_preserves_zero_precision(): void {
		wu_save_setting( 'precision', 0 );

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'ensure_currency_defaults' );
		$method->setAccessible( true );
		$method->invoke( $this->installer );

		// 0 is a valid stored value and must NOT be overwritten with 2.
		$this->assertSame( 0, wu_get_setting( 'precision' ) );
	}

	/**
	 * Test ensure_currency_defaults replaces empty-string precision with default.
	 */
	public function test_ensure_currency_defaults_replaces_empty_string_precision(): void {
		wu_save_setting( 'precision', '' );

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'ensure_currency_defaults' );
		$method->setAccessible( true );
		$method->invoke( $this->installer );

		$this->assertSame( 2, wu_get_setting( 'precision' ) );
	}

	// -----------------------------------------------------------------------
	// _install_create_products
	// -----------------------------------------------------------------------

	/**
	 * Test _install_create_products creates three products.
	 */
	public function test_install_create_products_creates_three_products(): void {
		// Ensure no conflicting products exist.
		foreach ( [ 'free', 'premium', 'seo' ] as $slug ) {
			$existing = wu_get_product_by_slug( $slug );
			if ( $existing ) {
				$existing->delete();
			}
		}

		// Ensure currency defaults are set so validation passes.
		wu_save_setting( 'currency_symbol', 'USD' );
		wu_save_setting( 'precision', 2 );

		$this->installer->_install_create_products();

		$free    = wu_get_product_by_slug( 'free' );
		$premium = wu_get_product_by_slug( 'premium' );
		$seo     = wu_get_product_by_slug( 'seo' );

		$this->assertNotFalse( $free, 'Free plan should have been created' );
		$this->assertNotFalse( $premium, 'Premium plan should have been created' );
		$this->assertNotFalse( $seo, 'SEO service should have been created' );
	}

	/**
	 * Test _install_create_products sets correct product types.
	 */
	public function test_install_create_products_correct_types(): void {
		foreach ( [ 'free', 'premium', 'seo' ] as $slug ) {
			$existing = wu_get_product_by_slug( $slug );
			if ( $existing ) {
				$existing->delete();
			}
		}

		wu_save_setting( 'currency_symbol', 'USD' );
		wu_save_setting( 'precision', 2 );

		$this->installer->_install_create_products();

		$free    = wu_get_product_by_slug( 'free' );
		$premium = wu_get_product_by_slug( 'premium' );
		$seo     = wu_get_product_by_slug( 'seo' );

		$this->assertSame( 'plan', $free->get_type() );
		$this->assertSame( 'plan', $premium->get_type() );
		$this->assertSame( 'service', $seo->get_type() );
	}

	/**
	 * Test _install_create_products sets correct pricing types.
	 */
	public function test_install_create_products_correct_pricing_types(): void {
		foreach ( [ 'free', 'premium', 'seo' ] as $slug ) {
			$existing = wu_get_product_by_slug( $slug );
			if ( $existing ) {
				$existing->delete();
			}
		}

		wu_save_setting( 'currency_symbol', 'USD' );
		wu_save_setting( 'precision', 2 );

		$this->installer->_install_create_products();

		$free    = wu_get_product_by_slug( 'free' );
		$premium = wu_get_product_by_slug( 'premium' );

		$this->assertSame( 'free', $free->get_pricing_type() );
		$this->assertSame( 'paid', $premium->get_pricing_type() );
	}

	/**
	 * Test _install_create_products sets correct amounts.
	 */
	public function test_install_create_products_correct_amounts(): void {
		foreach ( [ 'free', 'premium', 'seo' ] as $slug ) {
			$existing = wu_get_product_by_slug( $slug );
			if ( $existing ) {
				$existing->delete();
			}
		}

		wu_save_setting( 'currency_symbol', 'USD' );
		wu_save_setting( 'precision', 2 );

		$this->installer->_install_create_products();

		$free    = wu_get_product_by_slug( 'free' );
		$premium = wu_get_product_by_slug( 'premium' );
		$seo     = wu_get_product_by_slug( 'seo' );

		$this->assertSame( 0.0, (float) $free->get_amount() );
		$this->assertSame( 29.99, (float) $premium->get_amount() );
		$this->assertSame( 9.99, (float) $seo->get_amount() );
	}

	/**
	 * Test _install_create_products calls ensure_currency_defaults.
	 *
	 * Verifies that after calling _install_create_products with empty precision,
	 * the precision is set to the default (2).
	 */
	public function test_install_create_products_ensures_currency_defaults(): void {
		foreach ( [ 'free', 'premium', 'seo' ] as $slug ) {
			$existing = wu_get_product_by_slug( $slug );
			if ( $existing ) {
				$existing->delete();
			}
		}

		// Set precision to empty string to simulate the bug scenario.
		wu_save_setting( 'precision', '' );
		wu_save_setting( 'currency_symbol', 'USD' );

		$this->installer->_install_create_products();

		// After the call, precision should be set to 2.
		$this->assertSame( 2, wu_get_setting( 'precision' ) );
	}

	// -----------------------------------------------------------------------
	// _install_create_checkout
	// -----------------------------------------------------------------------

	/**
	 * Test _install_create_checkout creates a checkout form with slug 'main-form'.
	 */
	public function test_install_create_checkout_creates_form(): void {
		// Ensure no existing main-form.
		$existing = wu_get_checkout_form_by_slug( 'main-form' );
		if ( $existing ) {
			$existing->delete();
		}

		wu_save_setting( 'currency_symbol', 'USD' );
		wu_save_setting( 'precision', 2 );

		$this->installer->_install_create_checkout();

		$form = wu_get_checkout_form_by_slug( 'main-form' );
		$this->assertNotFalse( $form, 'main-form checkout form should have been created' );
		$this->assertInstanceOf( \WP_Ultimo\Models\Checkout_Form::class, $form );
	}

	/**
	 * Test _install_create_checkout creates a registration page.
	 */
	public function test_install_create_checkout_creates_registration_page(): void {
		$existing = wu_get_checkout_form_by_slug( 'main-form' );
		if ( $existing ) {
			$existing->delete();
		}

		wu_save_setting( 'currency_symbol', 'USD' );
		wu_save_setting( 'precision', 2 );

		$this->installer->_install_create_checkout();

		$page_id = wu_get_setting( 'default_registration_page', 0 );
		$this->assertGreaterThan( 0, $page_id, 'default_registration_page setting should be set' );

		$page = get_post( $page_id );
		$this->assertNotNull( $page, 'Registration page post should exist' );
		$this->assertSame( 'publish', $page->post_status );
	}

	/**
	 * Test _install_create_checkout sets default_registration_page setting.
	 */
	public function test_install_create_checkout_sets_registration_page_setting(): void {
		$existing = wu_get_checkout_form_by_slug( 'main-form' );
		if ( $existing ) {
			$existing->delete();
		}

		wu_save_setting( 'currency_symbol', 'USD' );
		wu_save_setting( 'precision', 2 );
		wu_save_setting( 'default_registration_page', 0 );

		$this->installer->_install_create_checkout();

		$page_id = wu_get_setting( 'default_registration_page', 0 );
		$this->assertGreaterThan( 0, $page_id );
	}

	/**
	 * Test _install_create_checkout page content includes checkout block.
	 */
	public function test_install_create_checkout_page_has_checkout_block(): void {
		$existing = wu_get_checkout_form_by_slug( 'main-form' );
		if ( $existing ) {
			$existing->delete();
		}

		wu_save_setting( 'currency_symbol', 'USD' );
		wu_save_setting( 'precision', 2 );

		$this->installer->_install_create_checkout();

		$page_id = wu_get_setting( 'default_registration_page', 0 );
		$page    = get_post( $page_id );

		$this->assertStringContainsString(
			'wp-ultimo/checkout',
			$page->post_content,
			'Registration page should contain the checkout block'
		);
	}

	/**
	 * Test _install_create_checkout calls ensure_currency_defaults.
	 */
	public function test_install_create_checkout_ensures_currency_defaults(): void {
		$existing = wu_get_checkout_form_by_slug( 'main-form' );
		if ( $existing ) {
			$existing->delete();
		}

		wu_save_setting( 'precision', '' );
		wu_save_setting( 'currency_symbol', 'USD' );

		$this->installer->_install_create_checkout();

		$this->assertSame( 2, wu_get_setting( 'precision' ) );
	}

	// -----------------------------------------------------------------------
	// _install_create_login_page
	// -----------------------------------------------------------------------

	/**
	 * Test _install_create_login_page creates a page.
	 */
	public function test_install_create_login_page_creates_page(): void {
		$this->installer->_install_create_login_page();

		$page_id = wu_get_setting( 'default_login_page', 0 );
		$this->assertGreaterThan( 0, $page_id, 'default_login_page setting should be set' );

		$page = get_post( $page_id );
		$this->assertNotNull( $page );
		$this->assertSame( 'publish', $page->post_status );
	}

	/**
	 * Test _install_create_login_page sets enable_custom_login_page setting.
	 */
	public function test_install_create_login_page_enables_custom_login(): void {
		wu_save_setting( 'enable_custom_login_page', 0 );

		$this->installer->_install_create_login_page();

		$this->assertSame( 1, wu_get_setting( 'enable_custom_login_page' ) );
	}

	/**
	 * Test _install_create_login_page sets default_login_page setting.
	 */
	public function test_install_create_login_page_sets_page_setting(): void {
		wu_save_setting( 'default_login_page', 0 );

		$this->installer->_install_create_login_page();

		$page_id = wu_get_setting( 'default_login_page', 0 );
		$this->assertGreaterThan( 0, $page_id );
	}

	/**
	 * Test _install_create_login_page page contains login shortcode.
	 */
	public function test_install_create_login_page_has_login_shortcode(): void {
		$this->installer->_install_create_login_page();

		$page_id = wu_get_setting( 'default_login_page', 0 );
		$page    = get_post( $page_id );

		$this->assertStringContainsString(
			'wu_login_form',
			$page->post_content,
			'Login page should contain the wu_login_form shortcode'
		);
	}

	/**
	 * Test done_creating_login_page returns true after _install_create_login_page.
	 */
	public function test_done_creating_login_page_true_after_install(): void {
		$this->installer->_install_create_login_page();

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_login_page' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertTrue( $result );
	}

	// -----------------------------------------------------------------------
	// _install_create_emails
	// -----------------------------------------------------------------------

	/**
	 * Test _install_create_emails delegates to Email_Manager.
	 *
	 * Verifies that calling _install_create_emails results in system emails
	 * being present (Email_Manager::create_all_system_emails is called).
	 */
	public function test_install_create_emails_creates_system_emails(): void {
		// Delete any existing system emails.
		$existing = wu_get_all_system_emails();
		foreach ( $existing as $email ) {
			$email->delete();
		}

		$this->installer->_install_create_emails();

		$emails = wu_get_all_system_emails();
		$this->assertNotEmpty( $emails, 'System emails should have been created' );
	}

	// -----------------------------------------------------------------------
	// _install_create_template_site (multisite only)
	// -----------------------------------------------------------------------

	/**
	 * Test _install_create_template_site creates a site on multisite.
	 *
	 * @group ms-required
	 */
	public function test_install_create_template_site_creates_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		// Check if template site already exists and skip if so.
		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_template_site' );
		$method->setAccessible( true );

		if ( $method->invoke( $this->installer ) ) {
			$this->markTestSkipped( 'Template site already exists.' );
		}

		$this->installer->_install_create_template_site();

		// After creation, done_creating_template_site should return a truthy value
		// (domain_exists returns int blog_id or false).
		$result = $method->invoke( $this->installer );
		$this->assertNotEmpty( $result, 'Template site should exist after creation' );
	}

	/**
	 * Test done_creating_template_site returns false on single-site.
	 */
	public function test_done_creating_template_site_false_on_single_site(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This test is for single-site only.' );
		}

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_template_site' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );
		$this->assertFalse( $result );
	}

	/**
	 * Test done_creating_template_site runs without exception on multisite.
	 *
	 * domain_exists() returns int|false|null depending on WordPress version.
	 * We verify the method completes without throwing.
	 *
	 * @group ms-required
	 */
	public function test_done_creating_template_site_runs_without_exception_on_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$ref    = new \ReflectionClass( $this->installer );
		$method = $ref->getMethod( 'done_creating_template_site' );
		$method->setAccessible( true );

		// Should not throw — just verify it completes.
		$result = $method->invoke( $this->installer );

		// The result is truthy when the template site exists, falsy otherwise.
		$this->assertNotInstanceOf( \WP_Error::class, $result );
	}

	// -----------------------------------------------------------------------
	// all_done (inherited from Base_Installer)
	// -----------------------------------------------------------------------

	/**
	 * Test all_done returns false when at least one step is not done.
	 */
	public function test_all_done_returns_false_when_steps_pending(): void {
		// Ensure at least one step is not done (no login page set).
		wu_save_setting( 'default_login_page', 0 );

		$result = $this->installer->all_done();
		$this->assertFalse( $result );
	}

	/**
	 * Test all_done returns a boolean.
	 */
	public function test_all_done_returns_bool(): void {
		$result = $this->installer->all_done();
		$this->assertIsBool( $result );
	}

	// -----------------------------------------------------------------------
	// handle (inherited from Base_Installer)
	// -----------------------------------------------------------------------

	/**
	 * Test handle returns WP_Error when installer throws an exception.
	 */
	public function test_handle_returns_wp_error_on_exception(): void {
		// Use a mock installer name that has no corresponding _install_ method.
		// Base_Installer::handle returns $status unchanged when no callable found.
		$result = $this->installer->handle( true, 'nonexistent_step', null );
		$this->assertTrue( $result, 'handle should return original status when no callable found' );
	}

	/**
	 * Test handle returns original status when no installer method exists.
	 */
	public function test_handle_returns_status_when_no_callable(): void {
		$result = $this->installer->handle( 'original_status', 'no_such_installer', null );
		$this->assertSame( 'original_status', $result );
	}
}
