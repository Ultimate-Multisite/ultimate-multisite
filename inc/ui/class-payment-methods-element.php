<?php
/**
 * Adds the Payment_Methods_Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Displays the customer's current payment method and allows changes.
 *
 * @since 2.0.0
 */
class Payment_Methods_Element extends Base_Element {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The id of the element.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $id = 'payment-methods';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.5.0
	 * @var boolean
	 */
	protected $public = true;

	/**
	 * The current membership.
	 *
	 * @since 2.5.0
	 * @var \WP_Ultimo\Models\Membership|null
	 */
	protected $membership;

	/**
	 * The current customer.
	 *
	 * @since 2.5.0
	 * @var \WP_Ultimo\Models\Customer|null
	 */
	protected $customer;

	/**
	 * The icon of the UI element.
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 * @return string
	 */
	public function get_icon($context = 'block') {

		if ('elementor' === $context) {
			return 'eicon-credit-card';
		}

		return 'fa fa-credit-card';
	}

	/**
	 * The title of the UI element.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('Payment Methods', 'ultimate-multisite');
	}

	/**
	 * The description of the UI element.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('Displays and manages the customer\'s saved payment methods.', 'ultimate-multisite');
	}

	/**
	 * The list of fields to be added to Gutenberg.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function fields() {

		$fields = [];

		$fields['header'] = [
			'title' => __('General', 'ultimate-multisite'),
			'desc'  => __('General', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['title'] = [
			'type'    => 'text',
			'title'   => __('Title', 'ultimate-multisite'),
			'value'   => __('Payment Method', 'ultimate-multisite'),
			'desc'    => __('Leave blank to hide the title completely.', 'ultimate-multisite'),
			'tooltip' => '',
		];

		return $fields;
	}

	/**
	 * The list of keywords for this element.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function keywords() {

		return [
			'WP Ultimo',
			'Ultimate Multisite',
			'Payment Methods',
			'Credit Card',
			'Billing',
		];
	}

	/**
	 * List of default parameters for the element.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function defaults() {

		return [
			'title' => __('Payment Method', 'ultimate-multisite'),
		];
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function setup() {

		$this->membership = WP_Ultimo()->currents->get_membership();
		$this->customer   = WP_Ultimo()->currents->get_customer();

		if ( ! $this->membership) {
			$this->set_display(false);
		}
	}

	/**
	 * Allows the setup in the context of previews.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function setup_preview() {

		$this->membership = wu_mock_membership();
		$this->customer   = wu_mock_customer();
	}

	/**
	 * The content to be output on the screen.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output($atts, $content = null) {

		$gateway_id      = $this->membership ? $this->membership->get_gateway() : '';
		$gateway         = $gateway_id ? wu_get_gateway($gateway_id) : null;
		$payment_info    = null;
		$change_url      = null;
		$gateway_display = '';

		if ($gateway) {
			$gateway_display = $gateway->get_title();
			$payment_info    = $gateway->get_payment_method_display($this->membership);
			$change_url      = $gateway->get_change_payment_method_url($this->membership);
		}

		$atts['membership']      = $this->membership;
		$atts['customer']        = $this->customer;
		$atts['gateway_display'] = $gateway_display;
		$atts['payment_info']    = $payment_info;
		$atts['change_url']      = $change_url;

		wu_get_template('dashboard-widgets/payment-methods', $atts);
	}
}
