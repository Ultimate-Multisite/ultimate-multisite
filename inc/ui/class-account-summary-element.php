<?php
/**
 * Adds the Account Summary Element UI to the Admin Panel.
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
class Account_Summary_Element extends Base_Element {

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
	public $id = 'account-summary';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var boolean
	 */
	protected $public = true;

	/**
	 * The current site.
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Models\Site
	 */
	protected $site;

	/**
	 * The current membership.
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Models\Membership
	 */
	protected $membership;

	/**
	 * The current product.
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Models\Product
	 */
	protected $product;

	/**
	 * The attributes to be passed to the template.
	 *
	 * @since 2.2.0
	 * @var array
	 */
	protected $atts;

	/**
	 * The icon of the UI element.
	 * e.g. return fa fa-search
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 * @return string
	 */
	public function get_icon($context = 'block') {

		if ('elementor' === $context) {
			return 'eicon-call-to-action';
		}

		return 'fa fa-search';
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

		return __('Account Summary', 'ultimate-multisite');
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

		return __('Displays a summary of the customer\'s account including membership and site overview.', 'ultimate-multisite');
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
			'value'   => __('About this Site', 'ultimate-multisite'),
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
	 *  'Checkout',
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
			'Account',
			'Summary',
			'Ultimate Multisite',
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
			'title' => __('About this Site', 'ultimate-multisite'),
		];
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup() {

		$this->site = WP_Ultimo()->currents->get_site();

		if ( ! $this->site || ! $this->site->is_customer_allowed()) {
			$this->set_display(false);

			return;
		}

		$this->membership = $this->site->get_membership();

		$this->product = $this->membership ? $this->membership->get_plan() : false;

		is_multisite() && switch_to_blog($this->site->get_id());

		$space_used      = get_space_used() * 1024 * 1024;
		$space_allowed   = get_space_allowed() ? get_space_allowed() * 1024 * 1024 : 1;
		$percentage      = ceil($space_used / $space_allowed * 100);
		$unlimited_space = get_site_option('upload_space_check_disabled');
		$message         = $unlimited_space ? '%s' : '%s / %s';

		is_multisite() && restore_current_blog();

		$this->atts = [
			'site_trial'      => 0, // @todo: fix this
			'space_used'      => $space_used,
			'space_allowed'   => $space_allowed,
			'percentage'      => $percentage,
			'unlimited_space' => $unlimited_space,
			'message'         => $message,
		];
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

		$this->product = wu_mock_product();

		$unlimited_space = get_site_option('upload_space_check_disabled');
		$message         = $unlimited_space ? '%s' : '%s / %s';

		$this->atts = [
			'site_trial'      => 30, // @todo: fix this
			'space_used'      => 120 * MB_IN_BYTES,
			'space_allowed'   => 1 * GB_IN_BYTES,
			'percentage'      => 120 * MB_IN_BYTES / 1 * GB_IN_BYTES,
			'unlimited_space' => $unlimited_space,
			'message'         => $message,
		];
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

		// Return empty if no site available (e.g., during SEO processing)
		if ( ! $this->site) {
			return;
		}

		$atts = array_merge($atts, $this->atts);

		$atts['site'] = $this->site;

		$atts['membership'] = $this->membership;

		$atts['product'] = $this->product;

		wu_get_template('dashboard-widgets/account-summary', $atts);
	}

	/**
	 * Returns the manage URL for sites, depending on the environment.
	 *
	 * @since 2.0.0
	 *
	 * @param int $site_id A Site ID.
	 * @return string
	 */
	public function get_manage_url($site_id) {

		$base_url = \WP_Ultimo\Current::get_manage_url($site_id, 'site');

		return is_admin() ? add_query_arg('page', 'account', $base_url . '/admin.php') : $base_url;
	}
}
