<?php
/**
 * Base PayPal Gateway.
 *
 * Base class for PayPal payment gateways. Should be extended by specific PayPal implementations.
 * Follows the same pattern as Base_Stripe_Gateway for consistency.
 *
 * @package WP_Ultimo
 * @subpackage Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Base PayPal Gateway class. Should be extended by PayPal gateway implementations.
 *
 * @since 2.0.0
 */
abstract class Base_PayPal_Gateway extends Base_Gateway {

	/**
	 * Allow gateways to declare multiple additional ids.
	 *
	 * These ids can be retrieved alongside the main id,
	 * via the method get_all_ids().
	 *
	 * This allows hooks to work for both legacy and modern PayPal implementations.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $other_ids = ['paypal', 'paypal-rest'];

	/**
	 * Partner Attribution ID (BN Code) for PayPal Partner Program tracking.
	 *
	 * This code identifies Ultimate Multisite as the integration partner
	 * and enables partner revenue sharing and analytics.
	 *
	 * Apply for an official BN code at: https://www.paypal.com/partnerprogram
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $bn_code = 'ULTIMATE_SP_PPCP';

	/**
	 * Holds if we are in test mode.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $test_mode = true;

	/**
	 * Declares support to recurring payments.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function supports_recurring(): bool {

		return true;
	}

	/**
	 * Declares support to subscription amount updates.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function supports_amount_update(): bool {

		return true;
	}

	/**
	 * Returns the PayPal base URL based on test mode.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_paypal_base_url(): string {

		return $this->test_mode ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
	}

	/**
	 * Returns the PayPal API base URL based on test mode.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_api_base_url(): string {

		return $this->test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	/**
	 * Get the subscription description.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Checkout\Cart $cart The cart object.
	 * @return string
	 */
	protected function get_subscription_description($cart): string {

		$descriptor = $cart->get_cart_descriptor();

		$desc = html_entity_decode(substr($descriptor, 0, 127), ENT_COMPAT, 'UTF-8');

		return $desc;
	}

	/**
	 * Returns the external link to view the payment on the payment gateway.
	 *
	 * Return an empty string to hide the link element.
	 *
	 * @since 2.0.0
	 *
	 * @param string $gateway_payment_id The gateway payment id.
	 * @return string
	 */
	public function get_payment_url_on_gateway($gateway_payment_id) {

		if (empty($gateway_payment_id)) {
			return '';
		}

		$sandbox_prefix = $this->test_mode ? 'sandbox.' : '';

		return sprintf(
			'https://www.%spaypal.com/activity/payment/%s',
			$sandbox_prefix,
			$gateway_payment_id
		);
	}

	/**
	 * Returns the external link to view the subscription on PayPal.
	 *
	 * Return an empty string to hide the link element.
	 *
	 * @since 2.0.0
	 *
	 * @param string $gateway_subscription_id The gateway subscription id.
	 * @return string
	 */
	public function get_subscription_url_on_gateway($gateway_subscription_id) {

		if (empty($gateway_subscription_id)) {
			return '';
		}

		$sandbox_prefix = $this->test_mode ? 'sandbox.' : '';

		// Check if this is a REST API subscription ID (starts with I-) or legacy NVP profile ID
		if (str_starts_with($gateway_subscription_id, 'I-')) {
			// REST API subscription
			return sprintf(
				'https://www.%spaypal.com/billing/subscriptions/%s',
				$sandbox_prefix,
				$gateway_subscription_id
			);
		}

		// Legacy NVP recurring payment profile
		$base_url = 'https://www.%spaypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=%s';

		return sprintf($base_url, $sandbox_prefix, $gateway_subscription_id);
	}

	/**
	 * Returns whether a gateway subscription ID is from the REST API.
	 *
	 * REST API subscription IDs start with "I-" prefix.
	 *
	 * @since 2.0.0
	 *
	 * @param string $subscription_id The subscription ID to check.
	 * @return bool
	 */
	protected function is_rest_subscription_id(string $subscription_id): bool {

		return str_starts_with($subscription_id, 'I-');
	}

	/**
	 * Adds partner attribution to API request headers.
	 *
	 * This should be called when making REST API requests to PayPal
	 * to ensure partner tracking and revenue sharing.
	 *
	 * @since 2.0.0
	 *
	 * @param array $headers Existing headers array.
	 * @return array Headers with partner attribution added.
	 */
	protected function add_partner_attribution_header(array $headers): array {

		$headers['PayPal-Partner-Attribution-Id'] = $this->bn_code;

		return $headers;
	}

	/**
	 * Log a PayPal-related message.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message The message to log.
	 * @param string $level   Log level (default: 'info').
	 * @return void
	 */
	protected function log(string $message, string $level = 'info'): void {

		wu_log_add('paypal', $message, $level);
	}

	/**
	 * Adds the necessary hooks for PayPal gateways.
	 *
	 * Child classes should call parent::hooks() and add their own hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function hooks(): void {

		// Add admin links to PayPal for membership management
		add_filter('wu_element_get_site_actions', [$this, 'add_site_actions'], 10, 4);
	}

	/**
	 * Adds PayPal-related actions to the site actions.
	 *
	 * Allows viewing subscription on PayPal for connected memberships.
	 *
	 * @since 2.0.0
	 *
	 * @param array                        $actions    The site actions.
	 * @param array                        $atts       The widget attributes.
	 * @param \WP_Ultimo\Models\Site       $site       The current site object.
	 * @param \WP_Ultimo\Models\Membership $membership The current membership object.
	 * @return array
	 */
	public function add_site_actions($actions, $atts, $site, $membership) {

		if (! $membership) {
			return $actions;
		}

		$payment_gateway = $membership->get_gateway();

		if (! in_array($payment_gateway, $this->other_ids, true)) {
			return $actions;
		}

		$subscription_id = $membership->get_gateway_subscription_id();

		if (empty($subscription_id)) {
			return $actions;
		}

		$subscription_url = $this->get_subscription_url_on_gateway($subscription_id);

		if (! empty($subscription_url)) {
			$actions['view_on_paypal'] = [
				'label'        => __('View on PayPal', 'ultimate-multisite'),
				'icon_classes' => 'dashicons-wu-paypal wu-align-middle',
				'href'         => $subscription_url,
				'target'       => '_blank',
			];
		}

		return $actions;
	}

	/**
	 * Checks if PayPal is properly configured.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	abstract public function is_configured(): bool;

	/**
	 * Returns the connection status for display in settings.
	 *
	 * @since 2.0.0
	 * @return array{connected: bool, message: string, details: array}
	 */
	abstract public function get_connection_status(): array;
}
