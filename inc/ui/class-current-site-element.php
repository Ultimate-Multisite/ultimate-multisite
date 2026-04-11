<?php
/**
 * Adds the Current_Site_Element UI to the Admin Panel.
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
class Current_Site_Element extends Base_Element {

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
	public $id = 'current-site';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var boolean
	 */
	protected $public = true;

	/**
	 * The site being managed.
	 *
	 * @since 2.0.0
	 * @var null|\WP_Ultimo\Models\Site
	 */
	public $site;

	/**
	 * The membership being managed.
	 *
	 * @since 2.0.0
	 * @var null|\WP_Ultimo\Models\Membership
	 */
	public $membership;

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
			'edit_site',
			[
				'render'     => [$this, 'render_edit_site'],
				'handler'    => [$this, 'handle_edit_site'],
				'capability' => 'exist',
			]
		);
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

		return __('Site', 'ultimate-multisite');
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

		return __('Displays details about the currently selected site including title, URL, and quick actions.', 'ultimate-multisite');
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

		$fields['display_breadcrumbs'] = [
			'type'    => 'toggle',
			'title'   => __('Display Breadcrumbs?', 'ultimate-multisite'),
			'desc'    => __('Toggle to show/hide the breadcrumbs block.', 'ultimate-multisite'),
			'tooltip' => '',
			'value'   => 1,
		];

		$pages = get_pages(
			[
				'exclude' => [get_the_ID()], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			]
		);

		$pages = $pages ?: [];

		$pages_list = [0 => __('Current Page', 'ultimate-multisite')];

		foreach ($pages as $page) {
			$pages_list[ $page->ID ] = $page->post_title;
		}

		$fields['breadcrumbs_my_sites_page'] = [
			'type'    => 'select',
			'title'   => __('My Sites Page', 'ultimate-multisite'),
			'value'   => 0,
			'desc'    => __('The page with the customer sites list.', 'ultimate-multisite'),
			'options' => $pages_list,
		];

		$fields['display_description'] = [
			'type'    => 'toggle',
			'title'   => __('Display Site Description?', 'ultimate-multisite'),
			'desc'    => __('Toggle to show/hide the site description on the element.', 'ultimate-multisite'),
			'tooltip' => '',
			'value'   => 0,
		];

		$fields['display_image'] = [
			'type'    => 'toggle',
			'title'   => __('Display Site Screenshot?', 'ultimate-multisite'),
			'desc'    => __('Toggle to show/hide the site screenshots on the element.', 'ultimate-multisite'),
			'tooltip' => '',
			'value'   => 1,
		];

		$fields['screenshot_size'] = [
			'type'     => 'number',
			'title'    => __('Screenshot Size', 'ultimate-multisite'),
			'desc'     => '',
			'tooltip'  => '',
			'value'    => 200,
			'min'      => 100,
			'max'      => 400,
			'required' => [
				'display_image' => 1,
			],
		];

		$fields['screenshot_position'] = [
			'type'     => 'select',
			'title'    => __('Screenshot Position', 'ultimate-multisite'),
			'options'  => [
				'right' => __('Right', 'ultimate-multisite'),
				'left'  => __('Left', 'ultimate-multisite'),
			],
			'desc'     => '',
			'tooltip'  => '',
			'value'    => 'right',
			'required' => [
				'display_image' => 1,
			],
		];

		$fields['show_admin_link'] = [
			'type'    => 'toggle',
			'title'   => __('Show Admin Link?', 'ultimate-multisite'),
			'desc'    => __('Toggle to show/hide the WP admin link on the element.', 'ultimate-multisite'),
			'tooltip' => '',
			'value'   => 1,
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
	 *  'Site',
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
			'Site',
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
			'display_image'             => 1,
			'display_breadcrumbs'       => 1,
			'display_description'       => 0,
			'screenshot_size'           => 200,
			'screenshot_position'       => 'right',
			'breadcrumbs_my_sites_page' => 0,
			'show_admin_link'           => 1,
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
	 * Loads the required scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts() {

		add_wubox();
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

		$actions = [
			'visit_site' => [
				'label'        => __('Visit Site', 'ultimate-multisite'),
				'icon_classes' => 'dashicons-wu-browser wu-align-text-bottom',
				'classes'      => '',
				'href'         => $this->site ? $this->site->get_active_site_url() : '',
			],
			'edit_site'  => [
				'label'        => __('Edit Site', 'ultimate-multisite'),
				'icon_classes' => 'dashicons-wu-edit wu-align-text-bottom',
				'classes'      => 'wubox',
				'href'         => $this->site ? wu_get_form_url(
					'edit_site',
					[
						'site' => $this->site->get_hash(),
					]
				) : '',
			],
		];

		if ($atts['show_admin_link'] && $this->site) {
			$actions['site_admin'] = [
				'label'        => __('Admin Panel', 'ultimate-multisite'),
				'icon_classes' => 'dashicons-wu-grid wu-align-text-bottom',
				'classes'      => '',
				'href'         => get_admin_url($this->site->get_id()),
			];
		}

		$atts['actions'] = apply_filters('wu_current_site_actions', $actions, $this->site);

		$atts['current_site'] = $this->site;

		$my_sites_id = $atts['breadcrumbs_my_sites_page'];

		$my_sites_url = empty($my_sites_id) ? remove_query_arg('site') : get_page_link($my_sites_id);

		$atts['my_sites_url'] = is_admin() ? admin_url('admin.php?page=sites') : $my_sites_url;

		wu_get_template('dashboard-widgets/current-site', $atts);
	}

	/**
	 * Renders the edit site modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_edit_site() {

		$site = wu_get_site_by_hash(wu_request('site'));

		if ( ! $site) {
			return;
		}

		$fields = [
			'site_title'       => [
				'type'        => 'text',
				'title'       => __('Site Title', 'ultimate-multisite'),
				'placeholder' => __('e.g. My Awesome Site', 'ultimate-multisite'),
				'value'       => $site->get_title(),
				'html_attr'   => [
					'v-model' => 'site_title',
				],
			],
			'site_description' => [
				'type'        => 'textarea',
				'title'       => __('Site Description', 'ultimate-multisite'),
				'placeholder' => __('e.g. My Awesome Site description.', 'ultimate-multisite'),
				'value'       => $site->get_description(),
				'html_attr'   => [
					'rows' => 5,
				],
			],
			'site'             => [
				'type'  => 'hidden',
				'value' => wu_request('site'),
			],
			'submit_button'    => [
				'type'            => 'submit',
				'title'           => __('Save Changes', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end',
				'html_attr'       => [
					'v-bind:disabled' => '!site_title.length',
				],
			],
		];

		$fields = apply_filters('wu_form_edit_site', $fields, $this);

		$form = new \WP_Ultimo\UI\Form(
			'edit_site',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'edit_site',
					'data-state'  => wu_convert_to_state(
						[
							'site_title' => $site->get_title(),
						]
					),
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
	public function handle_edit_site(): void {

		$site = wu_get_site_by_hash(wu_request('site'));

		if ( ! $site) {
			$error = new \WP_Error('site-dont-exist', __('Something went wrong.', 'ultimate-multisite'));

			wp_send_json_error($error);
		}

		$new_title = wu_request('site_title');

		if ( ! $new_title) {
			$error = new \WP_Error('title_empty', __('Site title can not be empty.', 'ultimate-multisite'));

			wp_send_json_error($error);
		}

		$status = update_blog_option($site->get_id(), 'blogname', $new_title);

		$status_desc = update_blog_option($site->get_id(), 'blogdescription', wu_request('site_description'));
		$referer     = isset($_SERVER['HTTP_REFERER']) ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

		wp_send_json_success(
			[
				'redirect_url' => add_query_arg('updated', (int) $status, $referer),
			]
		);
	}
}
