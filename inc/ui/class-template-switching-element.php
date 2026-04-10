<?php
/**
 * Adds the Template Selection UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use WP_Ultimo\Managers\Field_Templates_Manager;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Template Selection Element UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Template_Switching_Element extends Base_Element {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The id of the element.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $id = 'template-switching';

	/**
	 * The current site element.
	 *
	 * @since 2.0.18
	 *
	 * @var string
	 */
	protected $site;

	/**
	 * The membership object.
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Models\Membership
	 */
	protected $membership;

	/**
	 * The list of products associated with the current membership.
	 *
	 * @since 2.2.0
	 * @var array
	 */
	protected $products;

	/**
	 * The icon of the UI element.
	 * e.g. return fa fa-search
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 */
	public function get_icon($context = 'block'): string {

		if ('elementor' === $context) {
			return 'eicon-cart-medium';
		}

		return 'fa fa-search';
	}

	/**
	 * The title of the UI element.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('Template Switching', 'ultimate-multisite');
	}

	/**
	 * The description of the UI element.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		return __('Allows customers to switch their site to a different template design.', 'ultimate-multisite');
	}

	/**
	 * Initializes the singleton.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init() {

		add_action('wu_ajax_wu_switch_template', [$this, 'switch_template']);

		parent::init();
	}

	/**
	 * Register element scripts.
	 *
	 * @since 2.0.4
	 *
	 * @return void
	 */
	public function register_scripts(): void {

		wp_register_script('wu-template-switching', wu_get_asset('template-switching.js', 'js'), ['jquery', 'wu-vue-apps', 'wu-selectizer', 'wp-hooks', 'wu-cookie-helpers'], \WP_Ultimo::VERSION, true);

		wp_localize_script(
			'wu-template-switching',
			'wu_template_switching_params',
			[
				'ajaxurl' => wu_ajax_url(),
			]
		);

		wp_enqueue_script('wu-template-switching');
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
			'title' => __('Layout', 'ultimate-multisite'),
			'desc'  => __('Layout', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['template_selection_template'] = [
			'type'   => 'group',
			'desc'   => Field_Templates_Manager::get_instance()->render_preview_block('template_selection'),
			'fields' => [
				'template_selection_template' => [
					'type'            => 'select',
					'title'           => __('Template Selector Layout', 'ultimate-multisite'),
					'placeholder'     => __('Select your Layout', 'ultimate-multisite'),
					'default'         => 'clean',
					'options'         => [$this, 'get_template_selection_templates'],
					'wrapper_classes' => 'wu-flex-grow',
					'html_attr'       => [
						'v-model' => 'template_selection_template',
					],
				],
			],
		];

		$fields['_dev_note_develop_your_own_template_1'] = [
			'type'            => 'note',
			'order'           => 99,
			'wrapper_classes' => 'sm:wu-p-0 sm:wu-block',
			'classes'         => '',
			// translators: %s the doc url
			'desc'            => sprintf('<div class="wu-p-4 wu-bg-blue-100 wu-text-grey-600">%s</div>', sprintf(__('Want to add customized template selection templates?<br><a target="_blank" class="wu-no-underline" href="%s">See how you can do that here</a>.', 'ultimate-multisite'), esc_url(wu_get_documentation_url('wp-ultimo-checkout-forms')))),
		];

		return $fields;
	}

	/**
	 * The list of keywords for this element.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function keywords() {

		return [
			'WP Ultimo',
			'Ultimate Multisite',
			'Template',
			'Template Switching',
		];
	}

	/**
	 * List of default parameters for the element.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function defaults() {

		$site_template_ids = wu_get_site_templates(
			[
				'fields' => 'ids',
			]
		);

		return [
			'slug'                        => 'template-switching',
			'template_selection_template' => 'clean',
			'template_selection_sites'    => implode(',', $site_template_ids),
		];
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup(): void {

		$this->site = wu_get_current_site();

		if ( ! $this->site || ! $this->site->is_customer_allowed()) {
			$this->set_display(false);

			return;
		}

		$this->membership = $this->site->get_membership();

		$this->products = [];

		$all_membership_products = [];

		if ($this->membership) {
			$all_membership_products = $this->membership->get_all_products();

			if (is_array($all_membership_products) && $all_membership_products) {
				foreach ($all_membership_products as $product) {
					$this->products[] = $product['product']->get_id();
				}
			}
		}
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.4
	 *
	 * @return void
	 */
	public function setup_preview(): void {

		$this->site = wu_mock_site();
	}

	/**
	 * Ajax action to change the template for a given site.
	 *
	 * @since 2.0.4
	 *
	 * @return void
	 */
	public function switch_template() {

		if ( ! $this->site) {
			$this->site = wu_get_current_site();
		}

		$template_id = (int) wu_request('template_id', '');

		$available_templates = array_map('intval', $this->site->get_limitations()->site_templates->get_available_site_templates());

		if (! in_array($template_id, $available_templates, true)) {
			wp_send_json_error(new \WP_Error('not_authorized', __('You are not allowed to use this template.', 'ultimate-multisite')));
		}

		if ( ! $template_id) {
			wp_send_json_error(new \WP_Error('template_id_required', __('You need to provide a valid template to duplicate.', 'ultimate-multisite')));
			return;
		}

		$switch = \WP_Ultimo\Helpers\Site_Duplicator::override_site($template_id, $this->site->get_id());

		/**
		 * Allow plugin developers to hook functions after a user or super admin switches the site template
		 *
		 * @since 1.9.8
		 * @param int $id Site ID
		 * @return void
		 */
		do_action('wu_after_switch_template', $this->site->get_id());
		$referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

		if ($switch) {
			wp_send_json_success(
				[
					'redirect_url' => add_query_arg(
						[
							'updated' => 1,
						],
						$referer
					),
				]
			);
		}
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

		if ($this->site) {
			$filter_template_limits = \WP_Ultimo\Limits\Site_Template_Limits::get_instance();

			$atts['products'] = $this->products;

			$template_selection_field = $filter_template_limits->maybe_filter_template_selection_options($atts);

			if ( ! isset($template_selection_field['sites'])) {
				$template_selection_field['sites'] = [];
			}

			$atts['template_selection_sites'] = implode(',', $template_selection_field['sites']);

			$site_list = explode(',', $atts['template_selection_sites']);

			$sites = array_map('wu_get_site', $site_list);

			$sites = array_filter($sites);

			$categories = \WP_Ultimo\Models\Site::get_all_categories($sites);

			$template_attributes = [
				'sites'      => $sites,
				'name'       => '',
				'categories' => $categories,
			];

			$reducer_class = new \WP_Ultimo\Checkout\Signup_Fields\Signup_Field_Template_Selection();

			$template_class = Field_Templates_Manager::get_instance()->get_template_class('template_selection', $atts['template_selection_template']);

			$desc = function () use ($template_attributes, $template_class, $reducer_class) {
				if ($template_class) {
					$template_class->render_container($template_attributes, $reducer_class);
				} else {
					esc_html_e('Template does not exist.', 'ultimate-multisite');
				}
			};

			$checkout_fields['template_element'] = [
				'type'              => 'note',
				'wrapper_classes'   => 'wu-w-full',
				'classes'           => 'wu-w-full',
				'desc'              => $desc,
				'wrapper_html_attr' => [
					'v-show'  => 'template_id == original_template_id',
					'v-cloak' => '1',
				],
			];

			$checkout_fields['confirm_group'] = [
				'type'            => 'group',
				'classes'         => 'wu-justify-center wu-w-1/2 wu-grid',
				'wrapper_classes' => 'wu-bg-gray-100 wu-mt-4 wu-max-w-screen-md wu-mx-auto',
				'fields'          => [
					'back_to_template_selection' => [
						'type'              => 'note',
						'order'             => 0,
						'desc'              => function () {
							printf('<a href="#" class="wu-no-underline wu-mt-1 wu-uppercase wu-text-2xs wu-font-semibold wu-text-gray-600" v-on:click.prevent="template_id = original_template_id; confirm_switch = false">%s</a>', esc_html__('&larr; Back to Template Selection', 'ultimate-multisite'));
						},
						'wrapper_html_attr' => [
							'v-init:original_template_id' => $this->site->get_template_id(),
							'v-show'                      => 'template_id != original_template_id',
							'v-cloak'                     => '1',
						],
					],
					'confirm_switch'             => [
						'type'              => 'toggle',
						'title'             => __('Confirm template switch?', 'ultimate-multisite'),
						'desc'              => __('Switching your current template completely overwrites the content of your site with the contents of the newly chosen template. All customizations will be lost. This action cannot be undone.', 'ultimate-multisite'),
						'tooltip'           => '',
						'wrapper_classes'   => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-py-5 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
						'value'             => 0,
						'html_attr'         => [
							'v-model' => 'confirm_switch',
						],
						'wrapper_html_attr' => [
							'v-show'  => 'template_id != 0 && template_id != original_template_id',
							'v-cloak' => 1,
						],
					],
					'submit_switch'              => [
						'type'              => 'link',
						'display_value'     => __('Process Switch', 'ultimate-multisite'),
						'wrapper_classes'   => 'wu-text-right wu-bg-gray-100 wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-py-5 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
						'classes'           => 'button button-primary',
						'wrapper_html_attr' => [
							'v-cloak'            => 1,
							'v-show'             => 'confirm_switch',
							'v-on:click.prevent' => 'ready = true',
						],
					],
				],
			];

			$checkout_fields['template_id'] = [
				'type'      => 'hidden',
				'html_attr' => [
					'v-model'            => 'template_id',
					'v-init:template_id' => $this->site->get_template_id(),
				],
			];

			$section_slug = 'wu-template-switching-form';

			$form = new Form(
				$section_slug,
				$checkout_fields,
				[
					'views'                 => 'admin-pages/fields',
					'classes'               => 'wu-striped wu-widget-inset',
					'field_wrapper_classes' => 'wu-p-4 wu-py-5',
				]
			);

			$form->render();
		}
	}

	/**
	 * Returns the list of available pricing table templates.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_template_selection_templates() {

		$available_templates = Field_Templates_Manager::get_instance()->get_templates_as_options('template_selection');

		return $available_templates;
	}
}
