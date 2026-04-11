<?php
/**
 * Adds the Site Maintenance Element UI to the Admin Panel.
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
class Site_Maintenance_Element extends Base_Element {

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
	public $id = 'site-maintenance';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var boolean
	 */
	protected $public = true;

	private \WP_Ultimo\Models\Site $site;

	/**
	 * Initializes the singleton.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init() {

		if (wu_get_setting('maintenance_mode')) {
			parent::init();
		}
	}

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
			return 'eicon-lock-user';
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

		return __('Site Maintenance', 'ultimate-multisite');
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

		return __('Provides a toggle control for customers to enable or disable site maintenance mode.', 'ultimate-multisite');
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
			'type'        => 'text',
			'title'       => __('Label', 'ultimate-multisite'),
			'value'       => __('Toggle Maintenance Mode', 'ultimate-multisite'),
			'placeholder' => __('e.g. Toggle Maintenance Mode', 'ultimate-multisite'),
			'tooltip'     => '',
		];

		$fields['desc'] = [
			'type'    => 'textarea',
			'title'   => __('Description', 'ultimate-multisite'),
			'value'   => __('Put your site on maintenance mode. When activated, the front-end will only be accessible to logged users.', 'ultimate-multisite'),
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
	 *  'Billing_Address',
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
			'Login',
			'Reset Password',
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
			'title' => __('Toggle Maintenance Mode', 'ultimate-multisite'),
			'desc'  => __('Put your site on maintenance mode. When activated, the front-end will only be accessible to logged users.', 'ultimate-multisite'),
		];
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup() {

		$site = WP_Ultimo()->currents->get_site();

		if ( ! $site || ! $site->is_customer_allowed()) {
			$this->set_display(false);
		} else {
			$this->site = $site;
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
	}

	/**
	 * Registers scripts and styles necessary to render this.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts() {

		wp_register_script('wu-site-maintenance', wu_get_asset('site-maintenance.js', 'js'), ['jquery', 'wu-functions'], wu_get_version(), true);
		wp_register_style('wu-site-maintenance', wu_get_asset('site-maintenance.css', 'css'), [], wu_get_version());

		wp_localize_script(
			'wu-site-maintenance',
			'wu_site_maintenance',
			[
				'nonce'   => wp_create_nonce('wu_toggle_maintenance_mode'),
				'ajaxurl' => wu_ajax_url(),
			]
		);

		wp_enqueue_script('wu-site-maintenance');
		wp_enqueue_style('wu-site-maintenance');
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

		$fields = [
			'maintenance_mode' => [
				'type'  => 'toggle',
				'title' => $atts['title'],
				'desc'  => $atts['desc'],
				'value' => wu_string_to_bool($this->site->get_meta('wu_maintenance_mode')),
			],
			'site_hash'        => [
				'type'  => 'hidden',
				'value' => $this->site->get_hash(),
			],
		];

		/**
		 * Instantiate the form for the order details.
		 *
		 * @since 2.0.0
		 */
		$form = new \WP_Ultimo\UI\Form(
			'maintenance-mode',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-widget-list wu-striped wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0 wu-list-none wu-p-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [],
			]
		);

		$atts['form'] = $form;

		wu_get_template('dashboard-widgets/site-maintenance', $atts);
	}
}
