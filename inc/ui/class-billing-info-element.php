<?php
/**
 * Adds the Billing_Info_Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Checkout Element UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Billing_Info_Element extends Base_Element {

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
	public $id = 'billing-info';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var boolean
	 */
	protected $public = true;

	/**
	 * The membership object.
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Models\Membership
	 */
	protected $membership;

	/**
	 * The site object.
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Models\Site
	 */
	protected $site;

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
	 * Overload the init to add site-related forms.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init() {

		parent::init();

		wu_register_form(
			'update_billing_address',
			[
				'render'     => [$this, 'render_update_billing_address'],
				'handler'    => [$this, 'handle_update_billing_address'],
				'capability' => 'exist',
			]
		);
	}

	/**
	 * Loads the required scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts() {

		add_wubox();
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

		return __('Billing Information', 'ultimate-multisite');
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

		return __('Displays the customer\'s billing address and contact information.', 'ultimate-multisite');
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
			'value'   => __('Billing Address', 'ultimate-multisite'),
			'desc'    => __('Leave blank to hide the title completely.', 'ultimate-multisite'),
			'tooltip' => '',
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
			'Billing Information',
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
			'title' => __('Billing Address', 'ultimate-multisite'),
		];
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup() {

		$this->membership = WP_Ultimo()->currents->get_membership();

		if ( ! $this->membership) {
			$this->set_display(false);

			return;
		}
	}

	/**
	 * Allows the setup in the context of previews.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_preview() {

		$this->site = wu_mock_site();

		$this->membership = wu_mock_membership();
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

		$this->ensure_setup();

		// Return empty if no membership available (e.g., during SEO processing)
		if ( ! $this->membership) {
			return;
		}

		$atts['membership'] = $this->membership;

		$atts['billing_address'] = $this->membership->get_billing_address();

		$atts['update_billing_address_link'] = wu_get_form_url(
			'update_billing_address',
			[
				'membership' => $this->membership->get_hash(),
				'width'      => 500,
			]
		);

		wu_get_template('dashboard-widgets/billing-info', $atts);
	}

	/**
	 * Apply the placeholders to the fields.
	 *
	 * @since 2.0.0
	 *
	 * @param array $fields The billing fields.
	 * @return array
	 */
	protected function apply_placeholders($fields) {

		foreach ($fields as &$field) {
			$field['placeholder'] = $field['default_placeholder'];
		}

		return $fields;
	}

	/**
	 * Renders the update billing address form.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_update_billing_address() {

		$membership = wu_get_membership_by_hash(wu_request('membership'));

		if ( ! $membership) {
			return;
		}

		$billing_address = $membership->get_billing_address();

		$fields = [];

		$fields['billing-title'] = [
			'type'            => 'header',
			'order'           => 1,
			'title'           => __('Your Address', 'ultimate-multisite'),
			'desc'            => __('Enter your billing address here. This info will be used on your invoices.', 'ultimate-multisite'),
			'wrapper_classes' => 'wu-col-span-2',
		];

		$billing_fields = $this->apply_placeholders($billing_address->get_fields());

		$fields = array_merge($fields, $billing_fields);

		$fields['submit'] = [
			'type'            => 'submit',
			'title'           => __('Save Changes', 'ultimate-multisite'),
			'value'           => 'save',
			'classes'         => 'button button-primary wu-w-full',
			'wrapper_classes' => 'wu-col-span-2',
		];

		$fields['membership'] = [
			'type'  => 'hidden',
			'value' => wu_request('membership'),
		];

		$form = new \WP_Ultimo\UI\Form(
			'edit_site',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0 wu-grid-cols-2 wu-grid',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid wu-grid-col-span-2',
				'html_attr'             => [
					'data-wu-app' => 'edit_site',
					'data-state'  => wu_convert_to_state(),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the password reset form.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_update_billing_address(): void {

		$membership = wu_get_membership_by_hash(wu_request('membership'));

		if ( ! $membership) {
			$error = new \WP_Error('membership-dont-exist', __('Something went wrong.', 'ultimate-multisite'));

			wp_send_json_error($error);
		}

		$billing_address = $membership->get_billing_address();

		$billing_address->load_attributes_from_post();

		$valid_address = $billing_address->validate();

		if (is_wp_error($valid_address)) {
			wp_send_json_error($valid_address);
		}

		$membership->set_billing_address($billing_address);

		$saved = $membership->save();

		if (is_wp_error($saved)) {
			wp_send_json_error($saved);
		}
		$referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

		wp_send_json_success(
			[
				'redirect_url' => add_query_arg('updated', (int) $saved, $referer),
			]
		);
	}
}
