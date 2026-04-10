<?php
/**
 * Adds the Thank_You_Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Thank You Element UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Thank_You_Element extends Base_Element {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The id of the element.
	 *
	 * Something simple, without prefixes, like 'checkout', or 'pricing-tables'.
	 *
	 * This is used to construct shortcodes by prefixing the id with 'wu_'
	 * e.g. an id checkout becomes the shortcode 'wu_checkout' and
	 * to generate the Gutenberg block by prefixing it with 'wp-ultimo/'
	 * e.g. checkout would become the block 'wp-ultimo/checkout'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $id = 'thank-you';

	/**
	 * The payment object.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Payment
	 */
	protected $payment;

	/**
	 * The membership object.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Membership
	 */
	protected $membership;

	/**
	 * The customer object.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Customer
	 */
	protected $customer;

	/**
	 * The icon of the UI element.
	 * e.g. return fa fa-search
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 */
	public function get_icon($context = 'block'): string {

		if ('elementor' === $context) {
			return 'eicon-info-circle-o';
		}

		return 'fa fa-search';
	}

	/**
	 * Replace the register page title with the Thank you title.
	 *
	 * @since 2.0.0
	 *
	 * @param array $title_parts The title parts.
	 * @return array
	 */
	public function replace_page_title($title_parts) {

		$title_parts['title'] = $this->get_title();

		return $title_parts;
	}

	/**
	 * Maybe clear the title at the content level.
	 *
	 * @since 2.0.0
	 *
	 * @param string $title The page title.
	 * @param int    $id The post/page id.
	 * @return string
	 */
	public function maybe_replace_page_title($title, $id) {

		global $post;

		if ($post && $post->ID === $id) {
			return '';
		}

		return $title;
	}

	/**
	 * Register additional scripts for the thank you page.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		$has_pending_site = $this->membership ? (bool) $this->membership->get_pending_site() : false;
		$is_publishing    = $has_pending_site ? $this->membership->get_pending_site()->is_publishing() : false;

		wp_register_script('wu-thank-you', wu_get_asset('thank-you.js', 'js'), [], wu_get_version(), true);

		wp_localize_script(
			'wu-thank-you',
			'wu_thank_you',
			[
				'creating'                        => $is_publishing,
				'has_pending_site'                => $has_pending_site,
				'next_queue'                      => wu_get_next_queue_run(),
				'ajaxurl'                         => admin_url('admin-ajax.php'),
				'resend_verification_email_nonce' => wp_create_nonce('wu_resend_verification_email_nonce'),
				'membership_hash'                 => $this->membership ? $this->membership->get_hash() : false,
				'i18n'                            => [
					'resending_verification_email' => __('Resending verification email...', 'ultimate-multisite'),
					'email_sent'                   => __('Verification email sent!', 'ultimate-multisite'),
				],
			]
		);

		wp_enqueue_script('wu-thank-you');
	}

	/**
	 * The title of the UI element.
	 *
	 * This is used on the Blocks list of Gutenberg.
	 * You should return a string with the localized title.
	 * e.g. return __('My Element', 'ultimate-multisite').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('Thank You', 'ultimate-multisite');
	}

	/**
	 * The description of the UI element.
	 *
	 * This is also used on the Gutenberg block list
	 * to explain what this block is about.
	 * You should return a string with the localized title.
	 * e.g. return __('Adds a checkout form to the page', 'ultimate-multisite').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('Displays a confirmation message after successful checkout or registration.', 'ultimate-multisite');
	}

	/**
	 * The list of fields to be added to Gutenberg.
	 *
	 * If you plan to add Gutenberg controls to this block,
	 * you'll need to return an array of fields, following
	 * our fields interface (@see inc/ui/class-field.php).
	 *
	 * You can create new Gutenberg panels by adding fields
	 * with the type 'header'. See the Checkout Elements for reference.
	 *
	 * @see inc/ui/class-checkout-element.php
	 *
	 * Return an empty array if you don't have controls to add.
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
			'value'   => __('Thank You', 'ultimate-multisite'),
			'desc'    => __('Leave blank to hide the title completely.', 'ultimate-multisite'),
			'tooltip' => '',
		];

		$fields['thank_you_message'] = [
			'type'      => 'textarea',
			'title'     => __('Thank You Message', 'ultimate-multisite'),
			'desc'      => __('Shortcodes are supported.', 'ultimate-multisite'),
			'value'     => __('Thank you for your payment! Your transaction has been completed and a receipt for your purchase has been emailed to you.', 'ultimate-multisite'),
			'tooltip'   => '',
			'html_attr' => [
				'rows' => 4,
			],
		];

		$fields['title_pending'] = [
			'type'    => 'text',
			'title'   => __('Title (Pending)', 'ultimate-multisite'),
			'value'   => __('Thank You', 'ultimate-multisite'),
			'desc'    => __('Leave blank to hide the title completely. This title is used when the payment was not yet confirmed.', 'ultimate-multisite'),
			'tooltip' => '',
		];

		$fields['thank_you_message_pending'] = [
			'type'      => 'textarea',
			'title'     => __('Thank You Message (Pending)', 'ultimate-multisite'),
			'desc'      => __('This content is used when the payment was not yet confirmed. Shortcodes are supported.', 'ultimate-multisite'),
			'value'     => __('Thank you for your order! We are waiting on the payment processor to confirm your payment, which can take up to 5 minutes. We will notify you via email when your site is ready.', 'ultimate-multisite'),
			'tooltip'   => '',
			'html_attr' => [
				'rows' => 4,
			],
		];

		$fields['no_sites_message'] = [
			'type'      => 'textarea',
			'title'     => __('No Sites Message', 'ultimate-multisite'),
			'desc'      => __('A message to show if membership has no sites. Shortcodes are supported.', 'ultimate-multisite'),
			'value'     => __('No sites found', 'ultimate-multisite'),
			'tooltip'   => '',
			'html_attr' => [
				'rows' => 4,
			],
		];

		return $fields;
	}

	/**
	 * The list of keywords for this element.
	 *
	 * Return an array of strings with keywords describing this
	 * element. Gutenberg uses this to help customers find blocks.
	 *
	 * e.g.:
	 * return array(
	 *  'Ultimate Multisite',
	 *  'Billing Information',
	 *  'Form',
	 *  'Cart',
	 * );
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function keywords() {

		return [
			'WP Ultimo',
			'Ultimate Multisite',
			'Thank You',
			'Form',
			'Cart',
		];
	}

	/**
	 * List of default parameters for the element.
	 *
	 * If you are planning to add controls using the fields,
	 * it might be a good idea to use this method to set defaults
	 * for the parameters you are expecting.
	 *
	 * These defaults will be used inside a 'wp_parse_args' call
	 * before passing the parameters down to the block render
	 * function and the shortcode render function.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function defaults() {

		return [
			'title'                     => __('Thank You', 'ultimate-multisite'),
			'thank_you_message'         => __('Thank you for your payment! Your transaction has been completed and a receipt for your purchase has been emailed to you.', 'ultimate-multisite'),
			'title_pending'             => __('Thank You', 'ultimate-multisite'),
			'thank_you_message_pending' => __('Thank you for your order! We are waiting on the payment processor to confirm your payment, which can take up to 5 minutes. We will notify you via email when your site is ready.', 'ultimate-multisite'),
			'no_sites_message'          => __('No sites found', 'ultimate-multisite'),
		];
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup(): void {

		$this->payment = wu_get_payment_by_hash(wu_request('payment'));

		if ( ! $this->payment) {
			$this->set_display(false);

			return;
		}

		$this->membership = $this->payment->get_membership();

		if ( ! $this->membership || ! $this->membership->is_customer_allowed()) {
			$this->set_display(false);

			return;
		}

		$this->customer = $this->membership->get_customer();

		add_filter('document_title_parts', [$this, 'replace_page_title']);

		add_filter('the_title', [$this, 'maybe_replace_page_title'], 10, 2);
	}

	/**
	 * Allows the setup in the context of previews.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_preview(): void {

		$this->payment = wu_mock_payment();

		$this->membership = wu_mock_membership();

		$this->customer = wu_mock_customer();
	}

	/**
	 * The content to be output on the screen.
	 *
	 * Should return HTML markup to be used to display the block.
	 * This method is shared between the block render method and
	 * the shortcode implementation.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output($atts, $content = null) {

		$atts['payment'] = $this->payment;

		$atts['membership'] = $this->membership;

		$atts['customer'] = $this->customer;

		$atts = wp_parse_args($atts, $this->defaults());

		/*
		 * Deal with conversion tracking
		 */
		$conversion_snippets = $atts['checkout_form'] ? $atts['checkout_form']->get_conversion_snippets() : false;

		if ( ! empty($conversion_snippets)) {
			$product_ids = [];

			foreach ($this->payment->get_line_items() as $line_item) {
				if ($line_item->get_product_id()) {
					$product_ids[] = (string) $line_item->get_product_id();
				}
			}

			$conversion_placeholders = apply_filters(
				'wu_conversion_placeholders',
				[
					'CUSTOMER_ID'         => $this->customer->get_id(),
					'CUSTOMER_EMAIL'      => $this->customer->get_email_address(),
					'MEMBERSHIP_DURATION' => $this->membership->get_recurring_description(),
					'MEMBERSHIP_PLAN'     => $this->membership->get_plan_id(),
					'MEMBERSHIP_AMOUNT'   => $this->membership->get_amount(),
					'ORDER_ID'            => $this->payment->get_hash(),
					'ORDER_CURRENCY'      => $this->payment->get_currency(),
					'ORDER_PRODUCTS'      => array_values($product_ids),
					'ORDER_AMOUNT'        => $this->payment->get_total(),
				]
			);

			foreach ($conversion_placeholders as $placeholder => $value) {
				$conversion_snippets = preg_replace('/\%\%\s?' . $placeholder . '\s?\%\%/', wp_json_encode($value), (string) $conversion_snippets);
			}

			add_action(
				'wp_print_footer_scripts',
				function () use ($conversion_snippets) {

					echo $conversion_snippets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			);
		}

		/*
		 * Account for the 'className' Gutenberg attribute.
		 */
		$atts['className'] = trim('wu-' . $this->id . ' ' . wu_get_isset($atts, 'className', ''));

		wu_get_template('dashboard-widgets/thank-you', $atts);
	}
}
