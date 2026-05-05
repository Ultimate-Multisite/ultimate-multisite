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
	 * Permission state: site exists, customer is allowed, full switching UI.
	 */
	const STATE_OK = 'ok';

	/**
	 * Permission state: site exists, customer is allowed, but no membership
	 * is linked. Switching is still permitted; the available templates fall
	 * back to whatever the site's limitations expose.
	 */
	const STATE_NO_MEMBERSHIP = 'no_membership';

	/**
	 * Permission state: no site, or the site exists but the current user is
	 * not its customer (and not a network admin). UI shows a denial notice
	 * instead of the switching grid so the user is not left staring at an
	 * empty page wondering what went wrong.
	 */
	const STATE_NOT_ALLOWED = 'not_allowed';

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
	 * Permission state computed during setup().
	 *
	 * Used by output() to decide whether to render the full grid, the grid
	 * with a "no membership" notice, or a denial notice. Always set to one
	 * of the STATE_* constants by the time output() runs.
	 *
	 * @since 2.5.2
	 * @var string
	 */
	protected $permission_state = self::STATE_OK;

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
	public function register_scripts() {

		wp_register_script('wu-template-switching', wu_get_asset('template-switching.js', 'js'), ['jquery', 'wu-vue-apps', 'wu-selectizer', 'wp-hooks', 'wu-cookie-helpers'], \WP_Ultimo::VERSION, true);

		wp_localize_script(
			'wu-template-switching',
			'wu_template_switching_params',
			[
				'ajaxurl' => wu_ajax_url(),
				'i18n'    => [
					'reset_confirm' => __('Re-apply your current template? This will overwrite your site content with a fresh copy of the template. This cannot be undone.', 'ultimate-multisite'),
				],
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
	public function setup() {

		$this->site = wu_get_current_site();

		/*
		 * Decide which UI state to render.
		 *
		 * Previously this method called $this->set_display(false) whenever the
		 * customer was not allowed or no site was found, which left the page
		 * with three empty meta-box columns and no explanation — a confusing
		 * dead-end for end users. We now always render something: either the
		 * full grid, the grid with a "no membership" notice, or a denial
		 * notice. The actual server-side authorization for AJAX switches
		 * stays in switch_template().
		 */
		if ( ! $this->site || ! $this->site->is_customer_allowed()) {
			$this->permission_state = self::STATE_NOT_ALLOWED;
			$this->membership       = null;
			$this->products         = [];

			return;
		}

		$this->membership = $this->site->get_membership();
		$this->products   = [];

		if ($this->membership) {
			$all_membership_products = $this->membership->get_all_products();

			if (is_array($all_membership_products) && $all_membership_products) {
				foreach ($all_membership_products as $product) {
					$this->products[] = $product['product']->get_id();
				}
			}

			$this->permission_state = self::STATE_OK;
		} else {
			/*
			 * The customer owns this site but no membership is linked. This
			 * happens for sites created outside the normal checkout flow
			 * (manual admin creation, fixtures, legacy migrations, or after
			 * a membership is deleted but the site is preserved). The
			 * customer should still be able to switch templates — we simply
			 * skip the per-product template restriction and let the site's
			 * own limitations drive the available list.
			 */
			$this->permission_state = self::STATE_NO_MEMBERSHIP;
		}
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.4
	 *
	 * @return void
	 */
	public function setup_preview() {

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

		// Defensive guard — wu_get_current_site() can return false when the request
		// runs outside a customer-site context. Without this, dereferencing
		// $this->site below would emit no JSON body and the AJAX caller would
		// hang on its loading spinner.
		if ( ! $this->site || ! $this->site->get_id()) {
			wp_send_json_error(new \WP_Error('site_context_missing', __('Could not determine which site to switch. Please reload the page and try again.', 'ultimate-multisite')));
			return;
		}

		/*
		 * Authorization: confirm the requesting user owns this site.
		 *
		 * The wu-ajax-nonce check in class-light-ajax.php protects against
		 * CSRF, but the nonce is shared across all logged-in users on the
		 * install. Without this check, customer A could replay a valid nonce
		 * to switch the template (and overwrite content) on customer B's
		 * site by passing a forged site context. Network admins bypass this
		 * via the manage_network short-circuit inside is_customer_allowed().
		 */
		if ( ! $this->site->is_customer_allowed()) {
			wp_send_json_error(new \WP_Error('not_authorized', __('You do not have permission to switch templates on this site.', 'ultimate-multisite')));
			return;
		}

		$template_id = (int) wu_request('template_id', '');

		// false means MODE_DEFAULT (no restriction) — all templates are allowed.
		$available_templates = $this->site->get_limitations()->site_templates->get_available_site_templates();

		if (false !== $available_templates && ! in_array($template_id, array_map('intval', $available_templates), true)) {
			wp_send_json_error(new \WP_Error('not_authorized', __('You are not allowed to use this template.', 'ultimate-multisite')));
			return;
		}

		if ( ! $template_id) {
			wp_send_json_error(new \WP_Error('template_id_required', __('You need to provide a valid template to duplicate.', 'ultimate-multisite')));
			return;
		}

		$switch = \WP_Ultimo\Helpers\Site_Duplicator::override_site($template_id, $this->site->get_id());

		if ( ! $switch) {
			/*
			 * Site_Duplicator::override_site() returns false on any failure
			 * (user cap missing, copy_data error, Elementor Kit copy failure,
			 * etc.) without surfacing a reason. Without an explicit error
			 * response here, the AJAX call would close with an empty body,
			 * the JS success handler would throw on results.data.redirect_url,
			 * and the customer would see an indefinite loading spinner.
			 */
			wp_send_json_error(new \WP_Error('switch_failed', __('Could not switch the template. Please contact your network administrator.', 'ultimate-multisite')));
			return;
		}

		/**
		 * Allow plugin developers to hook functions after a user or super admin switches the site template.
		 *
		 * Only fires on a successful switch — previously this fired even on failure,
		 * which caused hooked code (cache clears, notifications, audit logs) to run
		 * for switches that did not actually happen.
		 *
		 * @since 1.9.8
		 * @param int $id Site ID
		 * @return void
		 */
		do_action('wu_after_switch_template', $this->site->get_id());

		$referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

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

		/*
		 * Render an explicit denial notice when the customer is not allowed
		 * to switch templates on this site (or there is no site context at
		 * all). Previously this branch produced an empty page with no
		 * explanation; users would see a "Switch Template" header and a
		 * blank body. Showing a notice keeps the UX informative.
		 */
		if (self::STATE_NOT_ALLOWED === $this->permission_state) {
			?>
			<div class="wu-bg-yellow-100 wu-border wu-border-solid wu-border-yellow-300 wu-text-yellow-800 wu-p-4 wu-rounded">
				<p class="wu-m-0 wu-font-semibold">
					<?php esc_html_e('Template switching is not available right now.', 'ultimate-multisite'); ?>
				</p>
				<p class="wu-m-0 wu-mt-2 wu-text-sm">
					<?php esc_html_e('You do not have permission to switch templates on this site, or the site is not associated with your account. If you believe this is a mistake, please contact your network administrator.', 'ultimate-multisite'); ?>
				</p>
			</div>
			<?php
			return;
		}

		if ($this->site) {
			$filter_template_limits = \WP_Ultimo\Limits\Site_Template_Limits::get_instance();

			$atts['products'] = $this->products;

			$template_selection_field = $filter_template_limits->maybe_filter_template_selection_options($atts);

			/*
			 * When the customer's site has no linked membership we have an
			 * empty $atts['products']. The shared limits filter only
			 * populates $attributes['sites'] when products are non-empty
			 * (see Site_Template_Limits::maybe_filter_template_selection_options),
			 * so without intervention we'd render the friendly "no
			 * membership" banner above an empty grid — defeating the whole
			 * point of letting the customer switch templates anyway.
			 *
			 * Fall back to every registered site template (the same list
			 * defaults() builds via wu_get_site_templates()). The customer
			 * still cannot bypass per-site rules: server-side authorization
			 * lives in switch_template() which calls is_customer_allowed()
			 * before applying the chosen template.
			 */
			if (self::STATE_NO_MEMBERSHIP === $this->permission_state && ! isset($template_selection_field['sites'])) {
				$default_sites = wu_get_site_templates(['fields' => 'ids']);

				$template_selection_field['sites'] = is_array($default_sites) ? $default_sites : [];
			}

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

			/*
			 * "Reset current template" — re-applies the customer's currently
			 * assigned template, refreshing the site from the source template.
			 * Useful when the source template has been updated, or when the
			 * customer wants to discard their customisations and start over
			 * without picking a different design.
			 *
			 * Only shows when the site is actually on a template (original_template_id > 0).
			 * Sites created without a template (original_template_id == 0) have
			 * nothing to reset to.
			 */
			// Confirmation text is provided to JS via wp_localize_script (see register_scripts())
			// so it can be translated; the click handler in template-switching.js shows it via window.confirm().
			$reset_link = sprintf(
				'<div class="wu-text-right wu-mt-2"><a href="#" class="wu-no-underline wu-text-2xs wu-uppercase wu-font-semibold wu-text-red-600 hover:wu-text-red-800" v-on:click.prevent="reset_template()">%s</a></div>',
				esc_html__('Reset current template', 'ultimate-multisite')
			);

			$checkout_fields['reset_current_template'] = [
				'type'              => 'note',
				'wrapper_classes'   => 'wu-w-full',
				'classes'           => 'wu-w-full',
				'desc'              => $reset_link,
				'wrapper_html_attr' => [
					'v-show'  => 'template_id == original_template_id && original_template_id > 0',
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

			/*
			 * Inform the customer when their site has no membership link.
			 * They can still switch templates, but pricing/product-tier
			 * restrictions don't apply, so the available list may differ
			 * from what they would normally see. Without this notice the UI
			 * looks identical to the normal flow but quietly behaves
			 * differently — better to be explicit.
			 */
			if (self::STATE_NO_MEMBERSHIP === $this->permission_state) {
				?>
				<div class="wu-bg-blue-100 wu-border wu-border-solid wu-border-blue-300 wu-text-blue-800 wu-p-4 wu-rounded wu-mb-4">
					<p class="wu-m-0 wu-text-sm">
						<?php esc_html_e('This site is not currently linked to a membership. You can still switch templates, but plan-specific template restrictions do not apply.', 'ultimate-multisite'); ?>
					</p>
				</div>
				<?php
			}

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
