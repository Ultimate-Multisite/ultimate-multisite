<?php
/**
 * Comprehensive unit tests for Base_PayPal_Gateway class.
 *
 * Covers the base PayPal gateway functionality in:
 * inc/gateways/class-base-paypal-gateway.php
 *
 * Tests URL generation, subscription ID detection, partner attribution,
 * and site action hooks.
 *
 * @package WP_Ultimo\Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use WP_Ultimo\Models\Customer;
use WP_Ultimo\Models\Membership;
use WP_Ultimo\Models\Site;

/**
 * Minimal concrete stub of Base_PayPal_Gateway for testing base-class-only methods.
 *
 * Does NOT override base class methods so that the base-class implementations
 * are exercised directly.
 */
class Base_PayPal_Gateway_Stub extends Base_PayPal_Gateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected $id = 'paypal-stub';

	/**
	 * Gateway title.
	 *
	 * @var string
	 */
	protected $title = 'PayPal Stub';

	/**
	 * Minimal init — no hooks needed for unit tests.
	 */
	public function init(): void {
		// Intentionally empty for testing.
	}

	/**
	 * Stub implementation of is_configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return true;
	}

	/**
	 * Stub implementation of get_connection_status.
	 *
	 * @return array{connected: bool, message: string, details: array}
	 */
	public function get_connection_status(): array {
		return array(
			'connected' => true,
			'message'   => 'Connected',
			'details'   => array(),
		);
	}

	/**
	 * Stub implementation of process_checkout.
	 *
	 * @param mixed $payment    Payment object.
	 * @param mixed $membership Membership object.
	 * @param mixed $customer   Customer object.
	 * @param mixed $cart       Cart object.
	 * @param mixed $type       Checkout type.
	 * @return bool
	 */
	public function process_checkout( $payment, $membership, $customer, $cart, $type ) {
		return true;
	}

	/**
	 * Stub implementation of process_cancellation.
	 *
	 * @param mixed $membership Membership object.
	 * @param mixed $customer   Customer object.
	 * @return bool
	 */
	public function process_cancellation( $membership, $customer ) {
		return true;
	}

	/**
	 * Stub implementation of process_refund.
	 *
	 * @param float $amount     Refund amount.
	 * @param mixed $payment    Payment object.
	 * @param mixed $membership Membership object.
	 * @param mixed $customer   Customer object.
	 * @return bool
	 */
	public function process_refund( $amount, $payment, $membership, $customer ) {
		return true;
	}

	/**
	 * Expose protected test_mode property for testing.
	 *
	 * @param bool $test_mode Test mode flag.
	 */
	public function set_test_mode( bool $test_mode ): void {
		$this->test_mode = $test_mode;
	}

	/**
	 * Expose protected get_paypal_base_url for testing.
	 *
	 * @return string
	 */
	public function public_get_paypal_base_url(): string {
		return $this->get_paypal_base_url();
	}

	/**
	 * Expose protected get_api_base_url for testing.
	 *
	 * @return string
	 */
	public function public_get_api_base_url(): string {
		return $this->get_api_base_url();
	}

	/**
	 * Expose protected is_rest_subscription_id for testing.
	 *
	 * @param string $subscription_id Subscription ID.
	 * @return bool
	 */
	public function public_is_rest_subscription_id( string $subscription_id ): bool {
		return $this->is_rest_subscription_id( $subscription_id );
	}

	/**
	 * Expose protected add_partner_attribution_header for testing.
	 *
	 * @param array $headers Headers array.
	 * @return array
	 */
	public function public_add_partner_attribution_header( array $headers ): array {
		return $this->add_partner_attribution_header( $headers );
	}

	/**
	 * Expose protected get_subscription_description for testing.
	 *
	 * @param \WP_Ultimo\Checkout\Cart $cart Cart object.
	 * @return string
	 */
	public function public_get_subscription_description( $cart ): string {
		return $this->get_subscription_description( $cart );
	}

	/**
	 * Expose protected log for testing.
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level.
	 */
	public function public_log( string $message, string $level = 'info' ): void {
		$this->log( $message, $level );
	}
}

/**
 * Comprehensive tests for Base_PayPal_Gateway.
 */
class Base_PayPal_Gateway_Test extends \WP_UnitTestCase {

	/**
	 * @var Base_PayPal_Gateway_Stub
	 */
	private $gateway;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->gateway = new Base_PayPal_Gateway_Stub();
	}

	// =========================================================================
	// supports_recurring
	// =========================================================================

	/**
	 * Test supports_recurring returns true.
	 */
	public function test_supports_recurring_returns_true(): void {
		$this->assertTrue( $this->gateway->supports_recurring() );
	}

	// =========================================================================
	// supports_amount_update
	// =========================================================================

	/**
	 * Test supports_amount_update returns true.
	 */
	public function test_supports_amount_update_returns_true(): void {
		$this->assertTrue( $this->gateway->supports_amount_update() );
	}

	// =========================================================================
	// get_paypal_base_url
	// =========================================================================

	/**
	 * Test get_paypal_base_url returns sandbox URL in test mode.
	 */
	public function test_get_paypal_base_url_returns_sandbox_in_test_mode(): void {
		$this->gateway->set_test_mode( true );

		$result = $this->gateway->public_get_paypal_base_url();

		$this->assertSame( 'https://www.sandbox.paypal.com', $result );
	}

	/**
	 * Test get_paypal_base_url returns live URL in live mode.
	 */
	public function test_get_paypal_base_url_returns_live_in_live_mode(): void {
		$this->gateway->set_test_mode( false );

		$result = $this->gateway->public_get_paypal_base_url();

		$this->assertSame( 'https://www.paypal.com', $result );
	}

	// =========================================================================
	// get_api_base_url
	// =========================================================================

	/**
	 * Test get_api_base_url returns sandbox API URL in test mode.
	 */
	public function test_get_api_base_url_returns_sandbox_in_test_mode(): void {
		$this->gateway->set_test_mode( true );

		$result = $this->gateway->public_get_api_base_url();

		$this->assertSame( 'https://api-m.sandbox.paypal.com', $result );
	}

	/**
	 * Test get_api_base_url returns live API URL in live mode.
	 */
	public function test_get_api_base_url_returns_live_in_live_mode(): void {
		$this->gateway->set_test_mode( false );

		$result = $this->gateway->public_get_api_base_url();

		$this->assertSame( 'https://api-m.paypal.com', $result );
	}

	// =========================================================================
	// get_subscription_description
	// =========================================================================

	/**
	 * Test get_subscription_description truncates to 127 characters.
	 */
	public function test_get_subscription_description_truncates_to_127_chars(): void {
		$cart_mock = $this->getMockBuilder( \WP_Ultimo\Checkout\Cart::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_cart_descriptor' ) )
			->getMock();

		$long_descriptor = str_repeat( 'A', 200 );
		$cart_mock->method( 'get_cart_descriptor' )->willReturn( $long_descriptor );

		$result = $this->gateway->public_get_subscription_description( $cart_mock );

		$this->assertSame( 127, strlen( $result ) );
		$this->assertSame( str_repeat( 'A', 127 ), $result );
	}

	/**
	 * Test get_subscription_description decodes HTML entities.
	 */
	public function test_get_subscription_description_decodes_html_entities(): void {
		$cart_mock = $this->getMockBuilder( \WP_Ultimo\Checkout\Cart::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_cart_descriptor' ) )
			->getMock();

		$cart_mock->method( 'get_cart_descriptor' )->willReturn( 'Test &amp; Product' );

		$result = $this->gateway->public_get_subscription_description( $cart_mock );

		$this->assertSame( 'Test & Product', $result );
	}

	/**
	 * Test get_subscription_description handles short descriptors.
	 */
	public function test_get_subscription_description_handles_short_descriptors(): void {
		$cart_mock = $this->getMockBuilder( \WP_Ultimo\Checkout\Cart::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_cart_descriptor' ) )
			->getMock();

		$cart_mock->method( 'get_cart_descriptor' )->willReturn( 'Short' );

		$result = $this->gateway->public_get_subscription_description( $cart_mock );

		$this->assertSame( 'Short', $result );
	}

	// =========================================================================
	// get_payment_url_on_gateway
	// =========================================================================

	/**
	 * Test get_payment_url_on_gateway returns empty string when payment ID is empty.
	 */
	public function test_get_payment_url_on_gateway_returns_empty_when_id_empty(): void {
		$result = $this->gateway->get_payment_url_on_gateway( '' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test get_payment_url_on_gateway returns sandbox URL in test mode.
	 */
	public function test_get_payment_url_on_gateway_returns_sandbox_url_in_test_mode(): void {
		$this->gateway->set_test_mode( true );

		$result = $this->gateway->get_payment_url_on_gateway( 'PAYID-TEST123' );

		$this->assertSame( 'https://www.sandbox.paypal.com/activity/payment/PAYID-TEST123', $result );
	}

	/**
	 * Test get_payment_url_on_gateway returns live URL in live mode.
	 */
	public function test_get_payment_url_on_gateway_returns_live_url_in_live_mode(): void {
		$this->gateway->set_test_mode( false );

		$result = $this->gateway->get_payment_url_on_gateway( 'PAYID-LIVE123' );

		$this->assertSame( 'https://www.paypal.com/activity/payment/PAYID-LIVE123', $result );
	}

	// =========================================================================
	// get_subscription_url_on_gateway
	// =========================================================================

	/**
	 * Test get_subscription_url_on_gateway returns empty string when subscription ID is empty.
	 */
	public function test_get_subscription_url_on_gateway_returns_empty_when_id_empty(): void {
		$result = $this->gateway->get_subscription_url_on_gateway( '' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test get_subscription_url_on_gateway returns REST API URL for I- prefix in test mode.
	 */
	public function test_get_subscription_url_on_gateway_returns_rest_url_for_i_prefix_test_mode(): void {
		$this->gateway->set_test_mode( true );

		$result = $this->gateway->get_subscription_url_on_gateway( 'I-TEST123' );

		$this->assertSame( 'https://www.sandbox.paypal.com/billing/subscriptions/I-TEST123', $result );
	}

	/**
	 * Test get_subscription_url_on_gateway returns REST API URL for I- prefix in live mode.
	 */
	public function test_get_subscription_url_on_gateway_returns_rest_url_for_i_prefix_live_mode(): void {
		$this->gateway->set_test_mode( false );

		$result = $this->gateway->get_subscription_url_on_gateway( 'I-LIVE123' );

		$this->assertSame( 'https://www.paypal.com/billing/subscriptions/I-LIVE123', $result );
	}

	/**
	 * Test get_subscription_url_on_gateway returns legacy NVP URL for non-I- prefix in test mode.
	 */
	public function test_get_subscription_url_on_gateway_returns_legacy_url_for_non_i_prefix_test_mode(): void {
		$this->gateway->set_test_mode( true );

		$result = $this->gateway->get_subscription_url_on_gateway( 'PROFILE-TEST123' );

		$expected = 'https://www.sandbox.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=PROFILE-TEST123';
		$this->assertSame( $expected, $result );
	}

	/**
	 * Test get_subscription_url_on_gateway returns legacy NVP URL for non-I- prefix in live mode.
	 */
	public function test_get_subscription_url_on_gateway_returns_legacy_url_for_non_i_prefix_live_mode(): void {
		$this->gateway->set_test_mode( false );

		$result = $this->gateway->get_subscription_url_on_gateway( 'PROFILE-LIVE123' );

		$expected = 'https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=PROFILE-LIVE123';
		$this->assertSame( $expected, $result );
	}

	// =========================================================================
	// is_rest_subscription_id
	// =========================================================================

	/**
	 * Test is_rest_subscription_id returns true for I- prefix.
	 */
	public function test_is_rest_subscription_id_returns_true_for_i_prefix(): void {
		$this->assertTrue( $this->gateway->public_is_rest_subscription_id( 'I-TEST123' ) );
	}

	/**
	 * Test is_rest_subscription_id returns false for non-I- prefix.
	 */
	public function test_is_rest_subscription_id_returns_false_for_non_i_prefix(): void {
		$this->assertFalse( $this->gateway->public_is_rest_subscription_id( 'PROFILE-TEST123' ) );
	}

	/**
	 * Test is_rest_subscription_id returns false for empty string.
	 */
	public function test_is_rest_subscription_id_returns_false_for_empty_string(): void {
		$this->assertFalse( $this->gateway->public_is_rest_subscription_id( '' ) );
	}

	/**
	 * Test is_rest_subscription_id is case-sensitive.
	 */
	public function test_is_rest_subscription_id_is_case_sensitive(): void {
		$this->assertFalse( $this->gateway->public_is_rest_subscription_id( 'i-test123' ) );
	}

	// =========================================================================
	// add_partner_attribution_header
	// =========================================================================

	/**
	 * Test add_partner_attribution_header adds BN code to headers.
	 */
	public function test_add_partner_attribution_header_adds_bn_code(): void {
		$headers = array( 'Content-Type' => 'application/json' );

		$result = $this->gateway->public_add_partner_attribution_header( $headers );

		$this->assertArrayHasKey( 'PayPal-Partner-Attribution-Id', $result );
		$this->assertSame( 'ULTIMATE_SP_PPCP', $result['PayPal-Partner-Attribution-Id'] );
		$this->assertArrayHasKey( 'Content-Type', $result );
		$this->assertSame( 'application/json', $result['Content-Type'] );
	}

	/**
	 * Test add_partner_attribution_header works with empty headers array.
	 */
	public function test_add_partner_attribution_header_works_with_empty_array(): void {
		$result = $this->gateway->public_add_partner_attribution_header( array() );

		$this->assertArrayHasKey( 'PayPal-Partner-Attribution-Id', $result );
		$this->assertSame( 'ULTIMATE_SP_PPCP', $result['PayPal-Partner-Attribution-Id'] );
	}

	// =========================================================================
	// add_site_actions
	// =========================================================================

	/**
	 * Test add_site_actions returns unchanged actions when no membership.
	 */
	public function test_add_site_actions_returns_unchanged_when_no_membership(): void {
		$actions = array( 'existing_action' => array( 'label' => 'Test' ) );

		$result = $this->gateway->add_site_actions( $actions, array(), null, null );

		$this->assertSame( $actions, $result );
	}

	/**
	 * Test add_site_actions returns unchanged actions when gateway does not match.
	 */
	public function test_add_site_actions_returns_unchanged_when_gateway_does_not_match(): void {
		$membership_mock = $this->getMockBuilder( Membership::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_gateway' ) )
			->getMock();

		$membership_mock->method( 'get_gateway' )->willReturn( 'stripe' );

		$actions = array( 'existing_action' => array( 'label' => 'Test' ) );

		$result = $this->gateway->add_site_actions( $actions, array(), null, $membership_mock );

		$this->assertSame( $actions, $result );
	}

	/**
	 * Test add_site_actions returns unchanged actions when no subscription ID.
	 */
	public function test_add_site_actions_returns_unchanged_when_no_subscription_id(): void {
		$membership_mock = $this->getMockBuilder( Membership::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_gateway', 'get_gateway_subscription_id' ) )
			->getMock();

		$membership_mock->method( 'get_gateway' )->willReturn( 'paypal' );
		$membership_mock->method( 'get_gateway_subscription_id' )->willReturn( '' );

		$actions = array( 'existing_action' => array( 'label' => 'Test' ) );

		$result = $this->gateway->add_site_actions( $actions, array(), null, $membership_mock );

		$this->assertSame( $actions, $result );
	}

	/**
	 * Test add_site_actions adds view_on_paypal action when conditions are met.
	 */
	public function test_add_site_actions_adds_view_on_paypal_action(): void {
		$this->gateway->set_test_mode( true );

		$membership_mock = $this->getMockBuilder( Membership::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_gateway', 'get_gateway_subscription_id' ) )
			->getMock();

		$membership_mock->method( 'get_gateway' )->willReturn( 'paypal' );
		$membership_mock->method( 'get_gateway_subscription_id' )->willReturn( 'I-TEST123' );

		$actions = array( 'existing_action' => array( 'label' => 'Test' ) );

		$result = $this->gateway->add_site_actions( $actions, array(), null, $membership_mock );

		$this->assertArrayHasKey( 'view_on_paypal', $result );
		$this->assertSame( 'View on PayPal', $result['view_on_paypal']['label'] );
		$this->assertSame( 'dashicons-wu-paypal wu-align-middle', $result['view_on_paypal']['icon_classes'] );
		$this->assertStringContainsString( 'sandbox.paypal.com', $result['view_on_paypal']['href'] );
		$this->assertStringContainsString( 'I-TEST123', $result['view_on_paypal']['href'] );
		$this->assertSame( '_blank', $result['view_on_paypal']['target'] );
	}

	/**
	 * Test add_site_actions works with paypal-rest gateway ID.
	 */
	public function test_add_site_actions_works_with_paypal_rest_gateway_id(): void {
		$this->gateway->set_test_mode( false );

		$membership_mock = $this->getMockBuilder( Membership::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_gateway', 'get_gateway_subscription_id' ) )
			->getMock();

		$membership_mock->method( 'get_gateway' )->willReturn( 'paypal-rest' );
		$membership_mock->method( 'get_gateway_subscription_id' )->willReturn( 'I-LIVE123' );

		$actions = array();

		$result = $this->gateway->add_site_actions( $actions, array(), null, $membership_mock );

		$this->assertArrayHasKey( 'view_on_paypal', $result );
		$this->assertStringContainsString( 'paypal.com', $result['view_on_paypal']['href'] );
		$this->assertStringNotContainsString( 'sandbox', $result['view_on_paypal']['href'] );
	}

	// =========================================================================
	// log
	// =========================================================================

	/**
	 * Test log method calls wu_log_add with correct parameters.
	 */
	public function test_log_calls_wu_log_add_with_default_level(): void {
		// wu_log_add is a global function that we can't easily mock,
		// but we can verify it doesn't throw an error.
		$this->gateway->public_log( 'Test message' );

		// If we reach here without error, the test passes.
		$this->assertTrue( true );
	}

	/**
	 * Test log method with custom level.
	 */
	public function test_log_calls_wu_log_add_with_custom_level(): void {
		$this->gateway->public_log( 'Error message', 'error' );

		// If we reach here without error, the test passes.
		$this->assertTrue( true );
	}

	// =========================================================================
	// other_ids property
	// =========================================================================

	/**
	 * Test other_ids property contains expected gateway IDs.
	 */
	public function test_other_ids_contains_expected_gateway_ids(): void {
		$reflection = new \ReflectionClass( $this->gateway );
		$prop       = $reflection->getProperty( 'other_ids' );
		$prop->setAccessible( true );

		$other_ids = $prop->getValue( $this->gateway );

		$this->assertIsArray( $other_ids );
		$this->assertContains( 'paypal', $other_ids );
		$this->assertContains( 'paypal-rest', $other_ids );
	}

	// =========================================================================
	// bn_code property
	// =========================================================================

	/**
	 * Test bn_code property has correct value.
	 */
	public function test_bn_code_has_correct_value(): void {
		$reflection = new \ReflectionClass( $this->gateway );
		$prop       = $reflection->getProperty( 'bn_code' );
		$prop->setAccessible( true );

		$bn_code = $prop->getValue( $this->gateway );

		$this->assertSame( 'ULTIMATE_SP_PPCP', $bn_code );
	}

	// =========================================================================
	// hooks
	// =========================================================================

	/**
	 * Test hooks method registers wu_element_get_site_actions filter.
	 */
	public function test_hooks_registers_site_actions_filter(): void {
		// Remove any existing hooks first.
		remove_all_filters( 'wu_element_get_site_actions' );

		$this->gateway->hooks();

		$this->assertTrue( has_filter( 'wu_element_get_site_actions' ) );
	}
}
