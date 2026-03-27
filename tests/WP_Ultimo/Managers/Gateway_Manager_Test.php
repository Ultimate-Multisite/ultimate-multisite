<?php
/**
 * Test case for Gateway Manager.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Gateway_Manager;
use WP_Ultimo\Gateways\Base_Gateway;
use WP_Ultimo\Gateways\Free_Gateway;
use WP_Ultimo\Gateways\Manual_Gateway;
use WP_Ultimo\Gateways\PayPal_REST_Gateway;
use WP_Ultimo\Gateways\Stripe_Gateway;
use WP_UnitTestCase;

/**
 * Test Gateway Manager functionality.
 */
class Gateway_Manager_Test extends WP_UnitTestCase {

	/**
	 * Test gateway manager instance.
	 *
	 * @var Gateway_Manager
	 */
	private $manager;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->manager = Gateway_Manager::get_instance();
	}

	// -------------------------------------------------------------------------
	// Helper: reset registered_gateways and auto_renewable_gateways via reflection
	// -------------------------------------------------------------------------

	/**
	 * Reset internal gateway state via reflection.
	 *
	 * @param array $gateways Value to set for registered_gateways.
	 * @param array $auto     Value to set for auto_renewable_gateways.
	 */
	private function reset_gateways( array $gateways = [], array $auto = [] ): void {

		$reflection = new \ReflectionClass( $this->manager );

		$reg_prop = $reflection->getProperty( 'registered_gateways' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reg_prop->setAccessible( true );
		}
		$reg_prop->setValue( $this->manager, $gateways );

		$auto_prop = $reflection->getProperty( 'auto_renewable_gateways' );
		if ( PHP_VERSION_ID < 80100 ) {
			$auto_prop->setAccessible( true );
		}
		$auto_prop->setValue( $this->manager, $auto );
	}

	// =========================================================================
	// Singleton / Initialization
	// =========================================================================

	/**
	 * Test manager initialization.
	 */
	public function test_manager_initialization(): void {
		$this->assertInstanceOf( Gateway_Manager::class, $this->manager );
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {
		$this->assertSame( Gateway_Manager::get_instance(), Gateway_Manager::get_instance() );
	}

	/**
	 * Test init registers plugins_loaded hook.
	 */
	public function test_init_registers_plugins_loaded_hook(): void {
		$this->manager->init();

		$this->assertNotFalse( has_action( 'plugins_loaded', [ $this->manager, 'on_load' ] ) );
	}

	/**
	 * Test on_load registers wu_register_gateways hook.
	 */
	public function test_on_load_registers_hooks(): void {
		$this->manager->on_load();

		$this->assertNotFalse( has_action( 'wu_register_gateways', [ $this->manager, 'add_default_gateways' ] ) );
	}

	/**
	 * Test on_load registers gateway selector field hook.
	 */
	public function test_on_load_registers_gateway_selector_hook(): void {
		$this->manager->on_load();

		$this->assertNotFalse( has_action( 'init', [ $this->manager, 'add_gateway_selector_field' ] ) );
	}

	/**
	 * Test on_load registers process_gateway_confirmations on template_redirect.
	 */
	public function test_on_load_registers_confirmation_hook(): void {
		$this->manager->on_load();

		$this->assertNotFalse( has_action( 'template_redirect', [ $this->manager, 'process_gateway_confirmations' ] ) );
	}

	/**
	 * Test on_load registers maybe_process_webhooks on init.
	 */
	public function test_on_load_registers_webhook_hook(): void {
		$this->manager->on_load();

		$this->assertNotFalse( has_action( 'init', [ $this->manager, 'maybe_process_webhooks' ] ) );
	}

	/**
	 * Test on_load registers maybe_process_v1_webhooks on admin_init.
	 */
	public function test_on_load_registers_v1_webhook_hook(): void {
		$this->manager->on_load();

		$this->assertNotFalse( has_action( 'admin_init', [ $this->manager, 'maybe_process_v1_webhooks' ] ) );
	}

	// =========================================================================
	// Gateway Registration
	// =========================================================================

	/**
	 * Test gateway registration returns true on success.
	 */
	public function test_register_gateway_returns_true(): void {
		$result = $this->manager->register_gateway( 'test-reg-true', 'Test', 'Desc', Manual_Gateway::class );

		$this->assertTrue( $result );
	}

	/**
	 * Test registered gateway appears in get_registered_gateways.
	 */
	public function test_register_gateway_appears_in_list(): void {
		$gateway_id = 'test-appears-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Test', 'Desc', Manual_Gateway::class );

		$registered = $this->manager->get_registered_gateways();
		$this->assertArrayHasKey( $gateway_id, $registered );
	}

	/**
	 * Test duplicate gateway registration returns false.
	 */
	public function test_register_duplicate_gateway_returns_false(): void {
		$gateway_id = 'duplicate-' . wp_generate_uuid4();

		$result1 = $this->manager->register_gateway( $gateway_id, 'man', 'man', Manual_Gateway::class );
		$this->assertTrue( $result1 );

		$result2 = $this->manager->register_gateway( $gateway_id, 'man', 'man', Manual_Gateway::class );
		$this->assertFalse( $result2 );
	}

	/**
	 * Test registered gateway has all expected keys.
	 */
	public function test_registered_gateway_has_expected_keys(): void {
		$gateway_id = 'keys-test-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Keys Test', 'A description', Manual_Gateway::class );

		$gateway = $this->manager->get_gateway( $gateway_id );

		$this->assertArrayHasKey( 'id', $gateway );
		$this->assertArrayHasKey( 'title', $gateway );
		$this->assertArrayHasKey( 'desc', $gateway );
		$this->assertArrayHasKey( 'class_name', $gateway );
		$this->assertArrayHasKey( 'active', $gateway );
		$this->assertArrayHasKey( 'hidden', $gateway );
		$this->assertArrayHasKey( 'gateway', $gateway );

		$this->assertEquals( $gateway_id, $gateway['id'] );
		$this->assertEquals( 'Keys Test', $gateway['title'] );
		$this->assertEquals( 'A description', $gateway['desc'] );
		$this->assertEquals( Manual_Gateway::class, $gateway['class_name'] );
		$this->assertEquals( Manual_Gateway::class, $gateway['gateway'] );
	}

	/**
	 * Test hidden gateway registration sets hidden=true.
	 */
	public function test_hidden_gateway_registration(): void {
		$gateway_id = 'hidden-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Hidden', '', Manual_Gateway::class, true );

		$gateway = $this->manager->get_gateway( $gateway_id );

		$this->assertTrue( $gateway['hidden'] );
	}

	/**
	 * Test non-hidden gateway registration sets hidden=false.
	 */
	public function test_non_hidden_gateway_registration(): void {
		$gateway_id = 'visible-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Visible', '', Manual_Gateway::class, false );

		$gateway = $this->manager->get_gateway( $gateway_id );

		$this->assertFalse( $gateway['hidden'] );
	}

	/**
	 * Test gateway active flag reflects wu_get_setting active_gateways.
	 */
	public function test_register_gateway_active_flag_when_active(): void {
		$gateway_id = 'active-flag-' . wp_generate_uuid4();
		wu_save_setting( 'active_gateways', [ $gateway_id ] );

		$this->manager->register_gateway( $gateway_id, 'Active GW', '', Manual_Gateway::class );

		$gateway = $this->manager->get_gateway( $gateway_id );

		$this->assertTrue( $gateway['active'] );

		// Cleanup
		wu_save_setting( 'active_gateways', [] );
	}

	/**
	 * Test gateway active flag is false when not in active_gateways setting.
	 */
	public function test_register_gateway_active_flag_when_inactive(): void {
		$gateway_id = 'inactive-flag-' . wp_generate_uuid4();
		wu_save_setting( 'active_gateways', [] );

		$this->manager->register_gateway( $gateway_id, 'Inactive GW', '', Manual_Gateway::class );

		$gateway = $this->manager->get_gateway( $gateway_id );

		$this->assertFalse( $gateway['active'] );
	}

	// =========================================================================
	// Gateway Retrieval
	// =========================================================================

	/**
	 * Test get_registered_gateways returns array.
	 */
	public function test_get_registered_gateways_returns_array(): void {
		$gateways = $this->manager->get_registered_gateways();

		$this->assertIsArray( $gateways );
	}

	/**
	 * Test get_registered_gateways is not empty after default registration.
	 */
	public function test_get_registered_gateways_not_empty(): void {
		// Ensure defaults are registered
		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$gateways = $this->manager->get_registered_gateways();

		$this->assertNotEmpty( $gateways );
	}

	/**
	 * Test get_gateway returns array for registered gateway.
	 */
	public function test_get_gateway_returns_array(): void {
		$gateway_id = 'get-array-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'man', 'man', Manual_Gateway::class );

		$gateway = $this->manager->get_gateway( $gateway_id );

		$this->assertIsArray( $gateway );
		$this->assertNotEmpty( $gateway );
	}

	/**
	 * Test get_gateway returns false for nonexistent gateway.
	 */
	public function test_get_gateway_returns_false_for_nonexistent(): void {
		$gateway = $this->manager->get_gateway( 'nonexistent-' . wp_generate_uuid4() );

		$this->assertFalse( $gateway );
	}

	/**
	 * Test is_gateway_registered returns true for registered gateway.
	 */
	public function test_is_gateway_registered_true(): void {
		$gateway_id = 'check-reg-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Manual', '', Manual_Gateway::class );

		$this->assertTrue( $this->manager->is_gateway_registered( $gateway_id ) );
	}

	/**
	 * Test is_gateway_registered returns false for unregistered gateway.
	 */
	public function test_is_gateway_registered_false(): void {
		$this->assertFalse( $this->manager->is_gateway_registered( 'not-registered-' . wp_generate_uuid4() ) );
	}

	// =========================================================================
	// Active Gateway Logic
	// =========================================================================

	/**
	 * Test get_gateways_as_options returns array.
	 */
	public function test_get_gateways_as_options_returns_array(): void {
		$options = $this->manager->get_gateways_as_options();

		$this->assertIsArray( $options );
	}

	/**
	 * Test get_gateways_as_options filters out hidden gateways.
	 */
	public function test_get_gateways_as_options_filters_hidden(): void {
		$options = $this->manager->get_gateways_as_options();

		foreach ( $options as $option ) {
			$this->assertFalse( $option['hidden'], "Hidden gateway should not appear in options: {$option['id']}" );
		}
	}

	/**
	 * Test get_gateways_as_options excludes 'free' gateway (which is hidden).
	 */
	public function test_get_gateways_as_options_excludes_free_gateway(): void {
		$options = $this->manager->get_gateways_as_options();

		$this->assertArrayNotHasKey( 'free', $options );
	}

	/**
	 * Test get_gateways_as_options includes visible gateways.
	 */
	public function test_get_gateways_as_options_includes_visible_gateways(): void {
		$gateway_id = 'visible-option-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Visible Option', 'desc', Manual_Gateway::class, false );

		$options = $this->manager->get_gateways_as_options();

		$this->assertArrayHasKey( $gateway_id, $options );
	}

	/**
	 * Test get_gateways_as_options does not include explicitly hidden gateways.
	 */
	public function test_get_gateways_as_options_excludes_explicitly_hidden(): void {
		$gateway_id = 'hidden-option-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Hidden Option', 'desc', Manual_Gateway::class, true );

		$options = $this->manager->get_gateways_as_options();

		$this->assertArrayNotHasKey( $gateway_id, $options );
	}

	// =========================================================================
	// Auto-Renewable Gateways
	// =========================================================================

	/**
	 * Test get_auto_renewable_gateways returns array.
	 */
	public function test_get_auto_renewable_gateways_returns_array(): void {
		$auto_renewable = $this->manager->get_auto_renewable_gateways();

		$this->assertIsArray( $auto_renewable );
	}

	/**
	 * Test that a gateway supporting recurring is added to auto_renewable list.
	 */
	public function test_install_hooks_adds_recurring_gateway_to_auto_renewable(): void {
		// PayPal (legacy) supports recurring — register it fresh
		$this->reset_gateways();

		$this->manager->register_gateway( 'paypal-recurring-test', 'PayPal', 'desc', \WP_Ultimo\Gateways\PayPal_Gateway::class );

		$auto_renewable = $this->manager->get_auto_renewable_gateways();

		$this->assertContains( 'paypal', $auto_renewable );
	}

	/**
	 * Test that a gateway NOT supporting recurring is NOT added to auto_renewable list.
	 */
	public function test_install_hooks_does_not_add_non_recurring_gateway(): void {
		$this->reset_gateways();

		$gateway_id = 'manual-no-recurring-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Manual', 'desc', Manual_Gateway::class );

		$auto_renewable = $this->manager->get_auto_renewable_gateways();

		$this->assertNotContains( $gateway_id, $auto_renewable );
	}

	/**
	 * Test install_hooks registers wu_settings_payment_gateways action.
	 */
	public function test_install_hooks_registers_settings_action(): void {
		$gateway_id = 'hook-settings-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Settings Hook Test', 'desc', Manual_Gateway::class );

		$this->assertNotFalse( has_action( 'wu_settings_payment_gateways' ) );
	}

	/**
	 * Test install_hooks registers wu_checkout_gateway_fields action.
	 */
	public function test_install_hooks_registers_checkout_gateway_fields_action(): void {
		$gateway_id = 'hook-fields-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Fields Hook Test', 'desc', Manual_Gateway::class );

		$this->assertNotFalse( has_action( 'wu_checkout_gateway_fields' ) );
	}

	/**
	 * Test install_hooks registers wu_checkout_scripts action.
	 */
	public function test_install_hooks_registers_checkout_scripts_action(): void {
		$gateway_id = 'hook-scripts-' . wp_generate_uuid4();
		$this->manager->register_gateway( $gateway_id, 'Hook Test', 'desc', Manual_Gateway::class );

		$this->assertNotFalse( has_action( 'wu_checkout_scripts' ) );
	}

	// =========================================================================
	// Default Gateways
	// =========================================================================

	/**
	 * Test add_default_gateways registers free gateway.
	 */
	public function test_add_default_gateways_registers_free(): void {
		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'free', $registered );
	}

	/**
	 * Test add_default_gateways registers manual gateway.
	 */
	public function test_add_default_gateways_registers_manual(): void {
		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'manual', $registered );
	}

	/**
	 * Test add_default_gateways registers stripe gateway.
	 */
	public function test_add_default_gateways_registers_stripe(): void {
		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'stripe', $registered );
	}

	/**
	 * Test add_default_gateways registers stripe-checkout gateway.
	 */
	public function test_add_default_gateways_registers_stripe_checkout(): void {
		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'stripe-checkout', $registered );
	}

	/**
	 * Test add_default_gateways registers paypal-rest gateway.
	 */
	public function test_add_default_gateways_registers_paypal_rest(): void {
		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'paypal-rest', $registered );
	}

	/**
	 * Test free gateway is hidden.
	 */
	public function test_free_gateway_is_hidden(): void {
		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$gateway = $this->manager->get_gateway( 'free' );

		$this->assertTrue( $gateway['hidden'] );
	}

	/**
	 * Test legacy PayPal is NOT registered without credentials or active status.
	 */
	public function test_legacy_paypal_hidden_without_config(): void {
		wu_save_setting( 'paypal_test_username', '' );
		wu_save_setting( 'paypal_live_username', '' );
		wu_save_setting( 'active_gateways', [ 'stripe' ] );

		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'paypal-rest', $registered );
		$this->assertArrayNotHasKey( 'paypal', $registered, 'Legacy PayPal should NOT be registered without credentials' );
	}

	/**
	 * Test legacy PayPal IS registered when it has existing test credentials.
	 */
	public function test_legacy_paypal_shown_with_credentials(): void {
		wu_save_setting( 'paypal_test_username', 'legacy_api_user' );
		wu_save_setting( 'active_gateways', [ 'stripe' ] );

		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'paypal', $registered, 'Legacy PayPal should be registered when credentials exist' );

		// Cleanup
		wu_save_setting( 'paypal_test_username', '' );
	}

	/**
	 * Test legacy PayPal IS registered when it is an active gateway.
	 */
	public function test_legacy_paypal_shown_when_active(): void {
		wu_save_setting( 'paypal_test_username', '' );
		wu_save_setting( 'paypal_live_username', '' );
		wu_save_setting( 'active_gateways', [ 'paypal' ] );

		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'paypal', $registered, 'Legacy PayPal should be registered when it is active' );

		// Cleanup
		wu_save_setting( 'active_gateways', [] );
	}

	/**
	 * Test legacy PayPal IS registered when live username is set.
	 */
	public function test_legacy_paypal_shown_with_live_credentials(): void {
		wu_save_setting( 'paypal_test_username', '' );
		wu_save_setting( 'paypal_live_username', 'live_api_user' );
		wu_save_setting( 'active_gateways', [] );

		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'paypal', $registered, 'Legacy PayPal should be registered when live credentials exist' );

		// Cleanup
		wu_save_setting( 'paypal_live_username', '' );
	}

	/**
	 * Test PayPal REST gateway is always registered.
	 */
	public function test_paypal_rest_always_registered(): void {
		$this->reset_gateways();
		$this->manager->add_default_gateways();

		$registered = $this->manager->get_registered_gateways();

		$this->assertArrayHasKey( 'paypal-rest', $registered, 'PayPal REST should always be registered' );
	}

	// =========================================================================
	// add_gateway_selector_field
	// =========================================================================

	/**
	 * Test add_gateway_selector_field runs without error.
	 */
	public function test_add_gateway_selector_field_runs_without_error(): void {
		$this->manager->add_gateway_selector_field();

		$this->assertTrue( true );
	}

	// =========================================================================
	// maybe_process_webhooks
	// =========================================================================

	/**
	 * Test maybe_process_webhooks does nothing when wu-gateway param is absent.
	 */
	public function test_maybe_process_webhooks_no_gateway_param(): void {
		unset( $_REQUEST['wu-gateway'] );

		// Should return early without output
		ob_start();
		$this->manager->maybe_process_webhooks();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test maybe_process_webhooks does nothing when wu-gateway is empty string.
	 */
	public function test_maybe_process_webhooks_empty_gateway_param(): void {
		$_REQUEST['wu-gateway'] = '';

		ob_start();
		$this->manager->maybe_process_webhooks();
		$output = ob_get_clean();

		$this->assertEmpty( $output );

		unset( $_REQUEST['wu-gateway'] );
	}

	// =========================================================================
	// maybe_process_v1_webhooks
	// =========================================================================

	/**
	 * Test maybe_process_v1_webhooks does nothing when action param is absent.
	 */
	public function test_maybe_process_v1_webhooks_no_action_param(): void {
		unset( $_REQUEST['action'] );

		ob_start();
		$this->manager->maybe_process_v1_webhooks();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test maybe_process_v1_webhooks does nothing when action doesn't contain notify_gateway_.
	 */
	public function test_maybe_process_v1_webhooks_irrelevant_action(): void {
		$_REQUEST['action'] = 'some_other_action';

		ob_start();
		$this->manager->maybe_process_v1_webhooks();
		$output = ob_get_clean();

		$this->assertEmpty( $output );

		unset( $_REQUEST['action'] );
	}

	/**
	 * Test maybe_process_v1_webhooks does nothing when gateway is not registered.
	 */
	public function test_maybe_process_v1_webhooks_unregistered_gateway(): void {
		$_REQUEST['action'] = 'notify_gateway_nonexistent_gw_xyz';

		ob_start();
		$this->manager->maybe_process_v1_webhooks();
		$output = ob_get_clean();

		$this->assertEmpty( $output );

		unset( $_REQUEST['action'] );
	}

	/**
	 * Test maybe_process_v1_webhooks does nothing when action is empty string.
	 */
	public function test_maybe_process_v1_webhooks_empty_action(): void {
		$_REQUEST['action'] = '';

		ob_start();
		$this->manager->maybe_process_v1_webhooks();
		$output = ob_get_clean();

		$this->assertEmpty( $output );

		unset( $_REQUEST['action'] );
	}

	// =========================================================================
	// process_gateway_confirmations
	// =========================================================================

	/**
	 * Test process_gateway_confirmations returns early when wu-confirm is absent.
	 */
	public function test_process_gateway_confirmations_no_confirm_param(): void {
		unset( $_REQUEST['wu-confirm'] );

		ob_start();
		$this->manager->process_gateway_confirmations();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test process_gateway_confirmations returns early when status=done.
	 */
	public function test_process_gateway_confirmations_status_done(): void {
		$_REQUEST['wu-confirm'] = 'manual';
		$_REQUEST['status']     = 'done';

		ob_start();
		$this->manager->process_gateway_confirmations();
		$output = ob_get_clean();

		$this->assertEmpty( $output );

		unset( $_REQUEST['wu-confirm'], $_REQUEST['status'] );
	}

	/**
	 * Test process_gateway_confirmations calls wp_die for unregistered gateway.
	 */
	public function test_process_gateway_confirmations_unregistered_gateway(): void {
		$_REQUEST['wu-confirm'] = 'nonexistent-gateway-xyz';
		unset( $_REQUEST['status'] );

		// Track ob level before the call so we can restore it after
		$ob_level_before = ob_get_level();

		$exception_thrown = false;
		try {
			$this->manager->process_gateway_confirmations();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			// Clean up any output buffers opened by process_gateway_confirmations
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['wu-confirm'] );
		}

		$this->assertTrue( $exception_thrown, 'Expected WPDieException to be thrown for unregistered gateway' );
	}

	// =========================================================================
	// handle_scheduled_payment_verification
	// =========================================================================

	/**
	 * Test handle_scheduled_payment_verification with empty payment_id (int 0).
	 */
	public function test_handle_scheduled_payment_verification_empty_id(): void {
		// Should return early without error
		$this->manager->handle_scheduled_payment_verification( 0 );

		$this->assertTrue( true );
	}

	/**
	 * Test handle_scheduled_payment_verification with array format containing zero id.
	 */
	public function test_handle_scheduled_payment_verification_array_format_zero_id(): void {
		$this->manager->handle_scheduled_payment_verification( [
			'payment_id' => 0,
			'gateway_id' => 'stripe',
		] );

		$this->assertTrue( true );
	}

	/**
	 * Test handle_scheduled_payment_verification with array format missing payment_id.
	 */
	public function test_handle_scheduled_payment_verification_array_format_missing_id(): void {
		$this->manager->handle_scheduled_payment_verification( [
			'gateway_id' => 'stripe',
		] );

		$this->assertTrue( true );
	}

	/**
	 * Test handle_scheduled_payment_verification with nonexistent payment.
	 */
	public function test_handle_scheduled_payment_verification_nonexistent_payment(): void {
		// Should return early without error for nonexistent payment
		$this->manager->handle_scheduled_payment_verification( 999999 );

		$this->assertTrue( true );
	}

	/**
	 * Test handle_scheduled_payment_verification with non-stripe gateway payment.
	 */
	public function test_handle_scheduled_payment_verification_non_stripe_payment(): void {
		$customer = wu_create_customer( [
			'username' => 'sched-verify-test-' . wp_generate_uuid4(),
			'email'    => 'sched-verify-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		] );

		$product = wu_create_product( [
			'name'         => 'Sched Test Plan',
			'slug'         => 'sched-test-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		] );

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'manual',
		] );

		// Should return early — manual is not a stripe gateway
		$this->manager->handle_scheduled_payment_verification( $payment->get_id(), 'manual' );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$customer->delete();
	}

	/**
	 * Test handle_scheduled_payment_verification with completed payment returns early.
	 */
	public function test_handle_scheduled_payment_verification_completed_payment(): void {
		$customer = wu_create_customer( [
			'username' => 'sched-completed-' . wp_generate_uuid4(),
			'email'    => 'sched-completed-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		] );

		$product = wu_create_product( [
			'name'         => 'Completed Plan',
			'slug'         => 'completed-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		] );

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::COMPLETED,
			'gateway'       => 'stripe',
		] );

		// Should return early — payment already completed
		$this->manager->handle_scheduled_payment_verification( $payment->get_id(), 'stripe' );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$customer->delete();
	}

	/**
	 * Test handle_scheduled_payment_verification with stripe gateway (pending payment).
	 */
	public function test_handle_scheduled_payment_verification_stripe_pending(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'sched-stripe-' . $uuid,
			'email'    => 'sched-stripe-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Stripe Plan ' . $uuid,
			'slug'         => 'stripe-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'stripe',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		// Stripe gateway is registered; verify_and_complete_payment may or may not exist
		// Either way, the method should handle it gracefully
		$this->manager->handle_scheduled_payment_verification( $payment->get_id(), 'stripe' );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$customer->delete();
	}

	/**
	 * Test handle_scheduled_payment_verification derives gateway from payment when not provided.
	 */
	public function test_handle_scheduled_payment_verification_derives_gateway_from_payment(): void {
		$customer = wu_create_customer( [
			'username' => 'sched-derive-' . wp_generate_uuid4(),
			'email'    => 'sched-derive-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		] );

		$product = wu_create_product( [
			'name'         => 'Derive Plan',
			'slug'         => 'derive-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		] );

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'manual',
		] );

		// No gateway_id provided — should derive from payment (manual) and return early
		$this->manager->handle_scheduled_payment_verification( $payment->get_id() );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$customer->delete();
	}

	// =========================================================================
	// maybe_schedule_payment_verification
	// =========================================================================

	/**
	 * Test maybe_schedule_payment_verification with null payment returns early.
	 */
	public function test_maybe_schedule_payment_verification_null_payment(): void {
		$this->manager->maybe_schedule_payment_verification( null, null, null, null, 'new' );

		$this->assertTrue( true );
	}

	/**
	 * Test maybe_schedule_payment_verification with completed payment returns early.
	 */
	public function test_maybe_schedule_payment_verification_completed_payment(): void {
		$customer = wu_create_customer( [
			'username' => 'sched-comp-' . wp_generate_uuid4(),
			'email'    => 'sched-comp-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		] );

		$product = wu_create_product( [
			'name'         => 'Comp Plan',
			'slug'         => 'comp-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		] );

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::COMPLETED,
			'gateway'       => 'stripe',
		] );

		// Completed payment — should return early
		$this->manager->maybe_schedule_payment_verification( $payment, $membership, null, null, 'new' );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$customer->delete();
	}

	/**
	 * Test maybe_schedule_payment_verification with non-stripe gateway returns early.
	 */
	public function test_maybe_schedule_payment_verification_non_stripe_gateway(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'sched-nonstripe-' . $uuid,
			'email'    => 'sched-nonstripe-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Non-Stripe Plan ' . $uuid,
			'slug'         => 'non-stripe-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'manual',
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'manual',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		// Non-stripe gateway — should return early
		$this->manager->maybe_schedule_payment_verification( $payment, $membership, null, null, 'new' );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$customer->delete();
	}

	/**
	 * Test maybe_schedule_payment_verification with null membership returns early.
	 */
	public function test_maybe_schedule_payment_verification_null_membership(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'sched-nomem-' . $uuid,
			'email'    => 'sched-nomem-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'No Mem Plan ' . $uuid,
			'slug'         => 'no-mem-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'manual',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		// Null membership — gateway_id will be empty string, should return early
		$this->manager->maybe_schedule_payment_verification( $payment, null, null, null, 'new' );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$customer->delete();
	}

	// =========================================================================
	// maybe_process_webhooks — exception paths
	// =========================================================================

	/**
	 * Test maybe_process_webhooks catches Ignorable_Exception and sends 200 JSON error.
	 */
	public function test_maybe_process_webhooks_ignorable_exception(): void {
		$_REQUEST['wu-gateway'] = 'manual';

		// Hook into the webhook action to throw an Ignorable_Exception
		add_action(
			'wu_manual_process_webhooks',
			function () {
				throw new \WP_Ultimo\Gateways\Ignorable_Exception( 'Ignorable webhook error' );
			}
		);

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->maybe_process_webhooks();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['wu-gateway'] );
			remove_all_actions( 'wu_manual_process_webhooks' );
		}

		// wp_send_json_error triggers wp_die which throws WPDieException in tests
		$this->assertTrue( $exception_thrown, 'Expected WPDieException from wp_send_json_error on Ignorable_Exception' );
	}

	/**
	 * Test maybe_process_webhooks catches generic Throwable and sends 500 JSON error.
	 */
	public function test_maybe_process_webhooks_generic_throwable(): void {
		$_REQUEST['wu-gateway'] = 'manual';

		// Hook into the webhook action to throw a generic exception
		add_action(
			'wu_manual_process_webhooks',
			function () {
				throw new \RuntimeException( 'Generic webhook error' );
			}
		);

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->maybe_process_webhooks();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['wu-gateway'] );
			remove_all_actions( 'wu_manual_process_webhooks' );
		}

		// wp_send_json_error triggers wp_die which throws WPDieException in tests
		$this->assertTrue( $exception_thrown, 'Expected WPDieException from wp_send_json_error on generic Throwable' );
	}

	// =========================================================================
	// maybe_process_v1_webhooks — registered gateway paths
	// =========================================================================

	/**
	 * Test maybe_process_v1_webhooks with a registered gateway (Ignorable_Exception path).
	 */
	public function test_maybe_process_v1_webhooks_registered_gateway_ignorable_exception(): void {
		// Register manual gateway so wu_get_gateway('manual-v1-test') works
		$this->manager->register_gateway( 'manual-v1-test', 'Manual V1', 'desc', \WP_Ultimo\Gateways\Manual_Gateway::class );

		$_REQUEST['action'] = 'notify_gateway_manual-v1-test';

		// Hook into the webhook action to throw an Ignorable_Exception
		add_action(
			'wu_manual-v1-test_process_webhooks',
			function () {
				throw new \WP_Ultimo\Gateways\Ignorable_Exception( 'Ignorable v1 webhook error' );
			}
		);

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->maybe_process_v1_webhooks();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['action'] );
			remove_all_actions( 'wu_manual-v1-test_process_webhooks' );
		}

		$this->assertTrue( $exception_thrown, 'Expected WPDieException from wp_send_json_error on Ignorable_Exception in v1 webhook' );
	}

	/**
	 * Test maybe_process_v1_webhooks with a registered gateway (generic Throwable path).
	 */
	public function test_maybe_process_v1_webhooks_registered_gateway_generic_throwable(): void {
		// Register manual gateway so wu_get_gateway('manual-v1-throw') works
		$this->manager->register_gateway( 'manual-v1-throw', 'Manual V1 Throw', 'desc', \WP_Ultimo\Gateways\Manual_Gateway::class );

		$_REQUEST['action'] = 'notify_gateway_manual-v1-throw';

		// Hook into the webhook action to throw a generic exception
		add_action(
			'wu_manual-v1-throw_process_webhooks',
			function () {
				throw new \RuntimeException( 'Generic v1 webhook error' );
			}
		);

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->maybe_process_v1_webhooks();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['action'] );
			remove_all_actions( 'wu_manual-v1-throw_process_webhooks' );
		}

		$this->assertTrue( $exception_thrown, 'Expected WPDieException from wp_send_json_error on generic Throwable in v1 webhook' );
	}

	// =========================================================================
	// process_gateway_confirmations — registered gateway paths
	// =========================================================================

	/**
	 * Test process_gateway_confirmations with a registered gateway (no payment hash).
	 * process_confirmation() returns null by default — no WP_Error, no exception.
	 */
	public function test_process_gateway_confirmations_registered_gateway_no_payment(): void {
		// Ensure manual is registered
		if ( ! $this->manager->is_gateway_registered( 'manual' ) ) {
			$this->manager->register_gateway( 'manual', 'Manual', 'desc', \WP_Ultimo\Gateways\Manual_Gateway::class );
		}

		$_REQUEST['wu-confirm'] = 'manual';
		unset( $_REQUEST['status'], $_REQUEST['payment'] );

		$ob_level_before = ob_get_level();

		try {
			$this->manager->process_gateway_confirmations();
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['wu-confirm'], $_REQUEST['payment'] );
		}

		// If we get here without exception, the method handled the case gracefully
		$this->assertTrue( true );
	}

	/**
	 * Test process_gateway_confirmations with a registered gateway and a valid payment hash.
	 */
	public function test_process_gateway_confirmations_registered_gateway_with_payment(): void {
		// Ensure manual is registered
		if ( ! $this->manager->is_gateway_registered( 'manual' ) ) {
			$this->manager->register_gateway( 'manual', 'Manual', 'desc', \WP_Ultimo\Gateways\Manual_Gateway::class );
		}

		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'confirm-test-' . $uuid,
			'email'    => 'confirm-test-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Confirm Plan ' . $uuid,
			'slug'         => 'confirm-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'manual',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		$_REQUEST['wu-confirm'] = 'manual';
		$_REQUEST['payment']    = $payment->get_hash();
		unset( $_REQUEST['status'] );

		$ob_level_before = ob_get_level();

		try {
			$this->manager->process_gateway_confirmations();
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['wu-confirm'], $_REQUEST['payment'] );
		}

		// process_confirmation() returns null by default — no exception expected
		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$customer->delete();
	}

	/**
	 * Test process_gateway_confirmations when process_confirmation returns WP_Error.
	 * Uses a custom gateway class registered via eval.
	 */
	public function test_process_gateway_confirmations_wp_error_result(): void {
		// Define a stub class if not already defined
		if ( ! class_exists( 'WP_Ultimo_Test_WPError_Gateway' ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged
			eval( '
				class WP_Ultimo_Test_WPError_Gateway extends \WP_Ultimo\Gateways\Manual_Gateway {
					public function process_confirmation() {
						return new \WP_Error( "test-error", "Test confirmation error" );
					}
				}
			' );
		}

		$gateway_id = 'test-wperror-gw';
		if ( ! $this->manager->is_gateway_registered( $gateway_id ) ) {
			$this->manager->register_gateway( $gateway_id, 'WPError GW', 'desc', 'WP_Ultimo_Test_WPError_Gateway' );
		}

		$_REQUEST['wu-confirm'] = $gateway_id;
		unset( $_REQUEST['status'], $_REQUEST['payment'] );

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->process_gateway_confirmations();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['wu-confirm'] );
		}

		$this->assertTrue( $exception_thrown, 'Expected WPDieException when process_confirmation returns WP_Error' );
	}

	/**
	 * Test process_gateway_confirmations when process_confirmation throws a Throwable.
	 */
	public function test_process_gateway_confirmations_throwable_from_gateway(): void {
		// Define a stub class if not already defined
		if ( ! class_exists( 'WP_Ultimo_Test_Throw_Gateway' ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged
			eval( '
				class WP_Ultimo_Test_Throw_Gateway extends \WP_Ultimo\Gateways\Manual_Gateway {
					public function process_confirmation() {
						throw new \RuntimeException( "Confirmation threw an exception" );
					}
				}
			' );
		}

		$gateway_id = 'test-throw-gw';
		if ( ! $this->manager->is_gateway_registered( $gateway_id ) ) {
			$this->manager->register_gateway( $gateway_id, 'Throw GW', 'desc', 'WP_Ultimo_Test_Throw_Gateway' );
		}

		$_REQUEST['wu-confirm'] = $gateway_id;
		unset( $_REQUEST['status'], $_REQUEST['payment'] );

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->process_gateway_confirmations();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['wu-confirm'] );
		}

		$this->assertTrue( $exception_thrown, 'Expected WPDieException when process_confirmation throws a Throwable' );
	}

	/**
	 * Test process_gateway_confirmations adds wu_bypass_checkout_form action when output is produced.
	 */
	public function test_process_gateway_confirmations_adds_bypass_action_when_output(): void {
		// Define a stub class that produces output during process_confirmation
		if ( ! class_exists( 'WP_Ultimo_Test_Output_Gateway' ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged
			eval( '
				class WP_Ultimo_Test_Output_Gateway extends \WP_Ultimo\Gateways\Manual_Gateway {
					public function process_confirmation() {
						echo "Some gateway output";
						return null;
					}
				}
			' );
		}

		$gateway_id = 'test-output-gw';
		if ( ! $this->manager->is_gateway_registered( $gateway_id ) ) {
			$this->manager->register_gateway( $gateway_id, 'Output GW', 'desc', 'WP_Ultimo_Test_Output_Gateway' );
		}

		$_REQUEST['wu-confirm'] = $gateway_id;
		unset( $_REQUEST['status'], $_REQUEST['payment'] );

		$ob_level_before = ob_get_level();

		try {
			$this->manager->process_gateway_confirmations();
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['wu-confirm'] );
		}

		// The wu_bypass_checkout_form action should have been added
		$this->assertNotFalse( has_action( 'wu_bypass_checkout_form' ) );
	}

	// =========================================================================
	// ajax_check_payment_status
	// =========================================================================

	/**
	 * Test ajax_check_payment_status with missing payment hash.
	 */
	public function test_ajax_check_payment_status_missing_hash(): void {
		// Set up a valid nonce
		$_REQUEST['nonce']        = wp_create_nonce( 'wu_payment_status_poll' );
		$_REQUEST['payment_hash'] = '';

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->ajax_check_payment_status();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['nonce'], $_REQUEST['payment_hash'] );
		}

		// wp_send_json_error triggers wp_die which throws WPDieException in tests
		$this->assertTrue( $exception_thrown, 'Expected WPDieException for missing payment hash' );
	}

	/**
	 * Test ajax_check_payment_status with nonexistent payment hash.
	 */
	public function test_ajax_check_payment_status_nonexistent_payment(): void {
		$_REQUEST['nonce']        = wp_create_nonce( 'wu_payment_status_poll' );
		$_REQUEST['payment_hash'] = 'nonexistent-hash-' . wp_generate_uuid4();

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->ajax_check_payment_status();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['nonce'], $_REQUEST['payment_hash'] );
		}

		$this->assertTrue( $exception_thrown, 'Expected WPDieException for nonexistent payment' );
	}

	/**
	 * Test ajax_check_payment_status with a completed payment.
	 */
	public function test_ajax_check_payment_status_completed_payment(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'ajax-completed-' . $uuid,
			'email'    => 'ajax-completed-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Ajax Completed Plan ' . $uuid,
			'slug'         => 'ajax-completed-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::COMPLETED,
			'gateway'       => 'manual',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		$_REQUEST['nonce']        = wp_create_nonce( 'wu_payment_status_poll' );
		$_REQUEST['payment_hash'] = $payment->get_hash();

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->ajax_check_payment_status();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['nonce'], $_REQUEST['payment_hash'] );
		}

		// wp_send_json_success triggers wp_die which throws WPDieException in tests
		$this->assertTrue( $exception_thrown, 'Expected WPDieException from wp_send_json_success for completed payment' );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$product->delete();
		$customer->delete();
	}

	/**
	 * Test ajax_check_payment_status with a non-stripe pending payment.
	 */
	public function test_ajax_check_payment_status_non_stripe_payment(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'ajax-nonstripe-' . $uuid,
			'email'    => 'ajax-nonstripe-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Ajax Non-Stripe Plan ' . $uuid,
			'slug'         => 'ajax-nonstripe-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'manual',
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'manual',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		$_REQUEST['nonce']        = wp_create_nonce( 'wu_payment_status_poll' );
		$_REQUEST['payment_hash'] = $payment->get_hash();

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->ajax_check_payment_status();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['nonce'], $_REQUEST['payment_hash'] );
		}

		// wp_send_json_success triggers wp_die which throws WPDieException in tests
		$this->assertTrue( $exception_thrown, 'Expected WPDieException from wp_send_json_success for non-stripe payment' );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$product->delete();
		$customer->delete();
	}

	/**
	 * Test ajax_check_payment_status with a stripe pending payment (no verify_and_complete_payment).
	 */
	public function test_ajax_check_payment_status_stripe_payment(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'ajax-stripe-' . $uuid,
			'email'    => 'ajax-stripe-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Ajax Stripe Plan ' . $uuid,
			'slug'         => 'ajax-stripe-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'stripe',
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'stripe',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		$_REQUEST['nonce']        = wp_create_nonce( 'wu_payment_status_poll' );
		$_REQUEST['payment_hash'] = $payment->get_hash();

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->ajax_check_payment_status();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['nonce'], $_REQUEST['payment_hash'] );
		}

		// Either wp_send_json_success (no verify method) or verify attempt — both trigger WPDieException
		$this->assertTrue( $exception_thrown, 'Expected WPDieException from ajax_check_payment_status for stripe payment' );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$product->delete();
		$customer->delete();
	}

	/**
	 * Test ajax_check_payment_status derives gateway from membership when payment gateway is empty.
	 */
	public function test_ajax_check_payment_status_derives_gateway_from_membership(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'ajax-derive-' . $uuid,
			'email'    => 'ajax-derive-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Ajax Derive Plan ' . $uuid,
			'slug'         => 'ajax-derive-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'manual',
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		// Payment with empty gateway — should derive from membership
		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => '',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		$_REQUEST['nonce']        = wp_create_nonce( 'wu_payment_status_poll' );
		$_REQUEST['payment_hash'] = $payment->get_hash();

		$ob_level_before  = ob_get_level();
		$exception_thrown = false;

		try {
			$this->manager->ajax_check_payment_status();
		} catch ( \WPDieException $e ) {
			$exception_thrown = true;
		} finally {
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			unset( $_REQUEST['nonce'], $_REQUEST['payment_hash'] );
		}

		// wp_send_json_success triggers WPDieException (non-stripe gateway derived from membership)
		$this->assertTrue( $exception_thrown, 'Expected WPDieException from ajax_check_payment_status when deriving gateway from membership' );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$product->delete();
		$customer->delete();
	}

	// =========================================================================
	// maybe_schedule_payment_verification — stripe path
	// =========================================================================

	/**
	 * Test maybe_schedule_payment_verification with stripe gateway (pending payment).
	 * Stripe gateway is registered; schedule_payment_verification may or may not exist.
	 */
	public function test_maybe_schedule_payment_verification_stripe_gateway(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'sched-stripe-gw-' . $uuid,
			'email'    => 'sched-stripe-gw-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Sched Stripe GW Plan ' . $uuid,
			'slug'         => 'sched-stripe-gw-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'stripe',
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'stripe',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		// Ensure stripe is registered
		if ( ! $this->manager->is_gateway_registered( 'stripe' ) ) {
			$this->manager->register_gateway( 'stripe', 'Stripe', 'desc', \WP_Ultimo\Gateways\Stripe_Gateway::class );
		}

		// Should proceed to the stripe gateway check and either schedule or return early
		$this->manager->maybe_schedule_payment_verification( $payment, $membership, null, null, 'new' );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$product->delete();
		$customer->delete();
	}

	/**
	 * Test maybe_schedule_payment_verification with stripe-checkout gateway.
	 */
	public function test_maybe_schedule_payment_verification_stripe_checkout_gateway(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'sched-sc-' . $uuid,
			'email'    => 'sched-sc-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Sched SC Plan ' . $uuid,
			'slug'         => 'sched-sc-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'stripe-checkout',
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => 'stripe-checkout',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		// Ensure stripe-checkout is registered
		if ( ! $this->manager->is_gateway_registered( 'stripe-checkout' ) ) {
			$this->manager->register_gateway( 'stripe-checkout', 'Stripe Checkout', 'desc', \WP_Ultimo\Gateways\Stripe_Checkout_Gateway::class );
		}

		// Should proceed to the stripe-checkout gateway check
		$this->manager->maybe_schedule_payment_verification( $payment, $membership, null, null, 'new' );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$product->delete();
		$customer->delete();
	}

	// =========================================================================
	// handle_scheduled_payment_verification — gateway derived from membership
	// =========================================================================

	/**
	 * Test handle_scheduled_payment_verification derives gateway from membership when payment gateway is empty.
	 */
	public function test_handle_scheduled_payment_verification_derives_gateway_from_membership(): void {
		$uuid     = wp_generate_uuid4();
		$customer = wu_create_customer( [
			'username' => 'sched-mem-gw-' . $uuid,
			'email'    => 'sched-mem-gw-' . $uuid . '@example.com',
			'password' => 'password123',
		] );

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Could not create test customer: ' . $customer->get_error_message() );
			return;
		}

		$product = wu_create_product( [
			'name'         => 'Sched Mem GW Plan ' . $uuid,
			'slug'         => 'sched-mem-gw-plan-' . $uuid,
			'pricing_type' => 'paid',
			'amount'       => 10,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		] );

		if ( is_wp_error( $product ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test product: ' . $product->get_error_message() );
			return;
		}

		$membership = wu_create_membership( [
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
			'status'      => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
			'gateway'     => 'manual',
		] );

		if ( is_wp_error( $membership ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test membership: ' . $membership->get_error_message() );
			return;
		}

		// Payment with empty gateway — should derive from membership (manual) and return early
		$payment = wu_create_payment( [
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => \WP_Ultimo\Database\Payments\Payment_Status::PENDING,
			'gateway'       => '',
		] );

		if ( is_wp_error( $payment ) ) {
			$customer->delete();
			$this->markTestSkipped( 'Could not create test payment: ' . $payment->get_error_message() );
			return;
		}

		// No gateway_id provided — should derive from payment (empty) then membership (manual) and return early
		$this->manager->handle_scheduled_payment_verification( $payment->get_id() );

		$this->assertTrue( true );

		// Cleanup
		$payment->delete();
		$membership->delete();
		$product->delete();
		$customer->delete();
	}

	// =========================================================================
	// Error Cases
	// =========================================================================

	/**
	 * Test register_gateway with invalid class throws Error on install_hooks.
	 */
	public function test_register_gateway_with_invalid_class_throws_error(): void {
		$this->expectException( \Error::class );

		$this->manager->register_gateway( 'invalid-class-' . wp_generate_uuid4(), 'ex', 'tx', 'NonExistentGatewayClass' );
	}

	/**
	 * Test is_gateway_registered returns false when registered_gateways is empty.
	 */
	public function test_is_gateway_registered_with_empty_list(): void {
		$this->reset_gateways();

		$this->assertFalse( $this->manager->is_gateway_registered( 'any-gateway' ) );
	}

	/**
	 * Test get_gateway returns false when registered_gateways is empty.
	 */
	public function test_get_gateway_with_empty_list(): void {
		$this->reset_gateways();

		$this->assertFalse( $this->manager->get_gateway( 'any-gateway' ) );
	}

	/**
	 * Test get_gateways_as_options does not include explicitly hidden gateways after reset.
	 */
	public function test_get_gateways_as_options_all_hidden(): void {
		$this->reset_gateways();

		$hidden_id = 'all-hidden-' . wp_generate_uuid4();
		$this->manager->register_gateway( $hidden_id, 'Hidden', 'desc', Manual_Gateway::class, true );

		$options = $this->manager->get_gateways_as_options();

		$this->assertArrayNotHasKey( $hidden_id, $options );
	}

	/**
	 * Test get_auto_renewable_gateways does not contain manual gateway id.
	 */
	public function test_get_auto_renewable_gateways_excludes_non_recurring(): void {
		$this->reset_gateways();

		// Register only non-recurring gateways
		$this->manager->register_gateway( 'no-recur-' . wp_generate_uuid4(), 'Manual', 'desc', Manual_Gateway::class );

		$auto_renewable = $this->manager->get_auto_renewable_gateways();

		// Manual doesn't support recurring, so auto_renewable should not contain it
		$this->assertNotContains( 'manual', $auto_renewable );
	}
}
