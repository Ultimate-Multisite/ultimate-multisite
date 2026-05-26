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
	 * Permission state when the current customer can switch templates.
	 *
	 * @since 2.9.3
	 * @var string
	 */
	const STATE_OK = 'ok';

	/**
	 * Permission state when the current site is not linked to a membership.
	 *
	 * @since 2.9.3
	 * @var string
	 */
	const STATE_NO_MEMBERSHIP = 'no_membership';

	/**
	 * Permission state when the current customer is not allowed to manage the site.
	 *
	 * @since 2.9.3
	 * @var string
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
	 * Current template-switching permission state.
	 *
	 * @since 2.9.3
	 * @var string
	 */
	protected $permission_state = self::STATE_NOT_ALLOWED;

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

		if ( ! $this->site || ! $this->site->is_customer_allowed()) {
			$this->permission_state = self::STATE_NOT_ALLOWED;

			return;
		}

		$this->membership       = $this->site->get_membership();
		$this->permission_state = $this->membership ? self::STATE_OK : self::STATE_NO_MEMBERSHIP;

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

		if ( ! $this->site->is_customer_allowed()) {
			wp_send_json_error(new \WP_Error('not_authorized', __('You are not allowed to switch templates for this site.', 'ultimate-multisite')));
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

		/*
		 * Capture the customer's current template id BEFORE override_site()
		 * runs. override_site() rewrites the stored template id as part of
		 * the duplication, so reading $this->site->get_template_id() afterwards
		 * would always return the newly applied template and we'd lose the
		 * ability to distinguish "Reset" (template_id matches previous) from
		 * "Switch" (template_id differs).
		 */
		$previous_template_id = (int) $this->site->get_template_id();

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

		/*
		 * Distinguish reset from switch on the redirect so the page can
		 * tell the customer which action just succeeded. The duplication
		 * itself is identical for both paths (override_site() runs the
		 * same way regardless), so this flag is purely cosmetic — but
		 * "Template reset successfully" reads correctly when the customer
		 * just re-applied their existing template, where "Template
		 * switched successfully" would be misleading.
		 *
		 * Comparing to the *previous* template id (pre-override) is
		 * deliberate: by the time this code runs, override_site() has
		 * already changed the stored template id, so we have to compare
		 * against the value we captured before calling it.
		 */
		$action_label = ((int) $template_id === (int) $previous_template_id) ? 'reset' : 'switch';

		/*
		 * Use a namespaced query var (`wu_template_action`) rather than the
		 * generic `action` key. WordPress's wp-admin/admin.php treats
		 * `?action=...` as a request to dispatch an admin-action handler
		 * (admin_action_{name} hook) and rewrites/strips the URL during
		 * routing, which would drop our `updated=1` flag from the final
		 * address bar and silently break the success notice. The
		 * namespaced key bypasses that handling entirely.
		 */
		wp_send_json_success(
			[
				'redirect_url' => add_query_arg(
					[
						'updated'            => 1,
						'wu_template_action' => $action_label,
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

		if (wu_is_sovereign_tenant()) {
			echo wu_get_template_contents( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'elements/sovereign-redirect',
				[
					'main_site_account_url' => wu_mt_main_site_account_url(),
					'element_label'         => __('Template switching', 'ultimate-multisite'),
				]
			);
			return;
		}

		if ( ! $this->site || self::STATE_NOT_ALLOWED === $this->permission_state) {
			$this->render_not_allowed_notice();

			return;
		}

		if ($this->site) {
			if (self::STATE_NO_MEMBERSHIP === $this->permission_state) {
				$this->render_no_membership_notice();

				$atts['template_selection_sites'] = $this->defaults()['template_selection_sites'];
			}

			$filter_template_limits = \WP_Ultimo\Limits\Site_Template_Limits::get_instance();

			$atts['products'] = $this->products;

			if (self::STATE_NO_MEMBERSHIP === $this->permission_state) {
				$template_selection_field = [
					'sites' => array_filter(array_map('absint', explode(',', $atts['template_selection_sites']))),
				];
			} else {
				$template_selection_field = $filter_template_limits->maybe_filter_template_selection_options($atts);
			}

			if ( ! isset($template_selection_field['sites'])) {
				$template_selection_field['sites'] = [];
			}

			$atts['template_selection_sites'] = implode(',', $template_selection_field['sites']);

			/*
			 * Current-template summary card — rendered before the grid so the
			 * customer can see "what they're on" plus the Reset button up front
			 * without scrolling. The card is always visible (no v-show) because
			 * "what is the site's current template?" remains true regardless of
			 * what template_id the customer has clicked in the grid below.
			 *
			 * Reset is co-located here (instead of as a separate row at the
			 * bottom) because the operation acts on the current template, not
			 * on the grid selection. Pairing them avoids a stray red link
			 * floating below the grid.
			 */
			$current_template_id = (int) $this->site->get_template_id();
			$current_template    = $current_template_id > 0 ? wu_get_site($current_template_id) : false;

			$site_list = explode(',', $atts['template_selection_sites']);

			$sites = array_map('wu_get_site', $site_list);

			$sites = array_filter($sites);

			/*
			 * Hide templates the network admin has marked as unavailable.
			 *
			 * `wu_get_site_templates()` (used upstream to seed the candidate
			 * list) only filters by `wu_type = site_template` — it does not
			 * exclude templates the network admin has deactivated, archived,
			 * marked as deleted, or flagged as spam. Without this filter the
			 * customer-panel switching grid will list templates that the
			 * admin explicitly took out of circulation, which is what the
			 * customer reported as "templates that should not be available
			 * are still listed".
			 *
			 * Mirrors the inactive-only filter in
			 * inc/checkout/signup-fields/class-signup-field-template-selection.php
			 * (line 349) and extends it to cover archived/deleted/spam.
			 * Applied here (in the switching element) rather than in
			 * `wu_get_site_templates()` so we don't change behaviour for
			 * network-admin pages or any other callers that legitimately
			 * want every template.
			 */
			$sites = array_filter(
				$sites,
				static fn($site_template) => $site_template->is_active()
					&& ! $site_template->is_archived()
					&& ! $site_template->is_deleted()
					&& ! $site_template->is_spam()
			);

			/*
			 * Hide the current template from the "Available Templates" grid.
			 * The current template is already shown in the summary card above,
			 * so listing it again as a "Select" option is redundant and a
			 * little confusing — the customer can re-apply it via the
			 * "Reset Current Template" button in the card. Doing this filter
			 * here (in the customer-panel switching element) instead of in
			 * views/checkout/templates/template-selection/clean.php keeps the
			 * filter scoped to switching only — the same view is reused for
			 * new-customer signup, where there is no "current template" to
			 * exclude.
			 */
			if ($current_template_id > 0) {
				$sites = array_filter(
					$sites,
					static fn($site_template) => (int) $site_template->get_id() !== $current_template_id
				);
			}

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

			$current_card_renderer = function () use ($current_template, $current_template_id) {
				wu_get_template(
					'ui/template-switching-current',
					[
						'current_template'     => $current_template,
						'original_template_id' => $current_template_id,
					]
				);
			};

			$checkout_fields['current_template_card'] = [
				'type'              => 'note',
				'wrapper_classes'   => 'wu-w-full wu-bg-transparent wu-p-0',
				'classes'           => 'wu-w-full wu-p-0',
				'desc'              => $current_card_renderer,
				'wrapper_html_attr' => [
					'v-cloak' => '1',
				],
			];

			/*
			 * The grid of available templates. Previously this was hidden
			 * (v-show="template_id == original_template_id") whenever the
			 * customer clicked a different template — which made the grid
			 * disappear, hiding the user's other options behind a confirm
			 * panel. The grid now stays visible during selection so the
			 * customer can see context, change their mind, or pick a third
			 * template without scrolling back from a hidden state.
			 *
			 * Selection state ("Selected" vs "Select" on the buttons inside
			 * the grid) still updates from $parent.template_id, so the visual
			 * cue for "this is the one you have queued for switching" is
			 * preserved.
			 */
			$checkout_fields['template_element'] = [
				'type'              => 'note',
				// `wu-template-switching-grid-field` is a marker class
				// targeted by the scoped <style> at the bottom of output()
				// to tighten the gap between the category filter row and
				// the cards grid — see the style block for rationale.
				'wrapper_classes'   => 'wu-w-full wu-template-switching-grid-field',
				'classes'           => 'wu-w-full',
				'desc'              => $desc,
				'wrapper_html_attr' => [
					'v-cloak' => '1',
				],
			];

			/*
			 * Confirmation panel — replaces the previous (toggle + button)
			 * confirm_group with a single card whose visual layout matches
			 * the current-template card at the top of the page. The same
			 * panel handles two actions:
			 *
			 *   - "Switch to {target}?" — when template_id is a grid template
			 *     the customer just picked.
			 *   - "Reset to {current}?" — when template_id has been set back
			 *     to original_template_id by the Reset button.
			 *
			 * The panel cards are emitted by views/ui/template-switching-
			 * confirm.php which builds one v-if-guarded card per candidate
			 * site (current + all available). Vue's v-if shows only the card
			 * matching the active template_id, so the customer always sees a
			 * single panel with the correct thumbnail, name, and disclaimer.
			 *
			 * Visibility is keyed off `confirm_active` (set true by the grid
			 * Select buttons and the Reset button, false on cancel) rather
			 * than the previous `template_id != original_template_id` check
			 * — the latter could not distinguish "I clicked Reset" from
			 * "I'm already on this template, do nothing".
			 */
			$confirm_sites    = $sites;
			$current_in_sites = false;

			foreach ($confirm_sites as $candidate_site) {
				if ((int) $candidate_site->get_id() === $current_template_id) {
					$current_in_sites = true;
					break;
				}
			}

			if ( ! $current_in_sites && $current_template instanceof \WP_Ultimo\Models\Site) {
				array_unshift($confirm_sites, $current_template);
			}

			$confirm_renderer = function () use ($confirm_sites, $current_template_id) {
				wu_get_template(
					'ui/template-switching-confirm',
					[
						'confirm_sites'        => $confirm_sites,
						'original_template_id' => $current_template_id,
					]
				);
			};

			$checkout_fields['confirm_panel'] = [
				'type'              => 'note',
				'wrapper_classes'   => 'wu-w-full wu-bg-transparent wu-p-0 wu-template-switching-confirm-field',
				'classes'           => 'wu-w-full wu-p-0',
				'desc'              => $confirm_renderer,
				'wrapper_html_attr' => [
					'v-show'  => 'confirm_active',
					'v-cloak' => '1',
				],
			];

			/*
			 * Both v-init directives MUST be present on the same element
			 * so Vue processes them in the same bind phase. If only
			 * `template_id` is initialised, the JS watcher then sees
			 * `template_id` move from 0 → current and `original_template_id`
			 * still at its data() default of -1, so the
			 * `new_value == original_template_id` short-circuit cannot
			 * fire and the watcher flips `confirm_active = true` —
			 * which renders the confirm panel on first paint without the
			 * customer doing anything.
			 *
			 * Listing `original_template_id` *first* in the array means
			 * the rendered HTML emits its attribute before `template_id`,
			 * giving Vue a chance to bind it before the `template_id`
			 * watcher runs. The watcher itself also defends against the
			 * inverse ordering (see template-switching.js: it bails out
			 * early when original_template_id <= 0).
			 */
			$checkout_fields['template_id'] = [
				'type'      => 'hidden',
				'html_attr' => [
					'v-init:original_template_id' => $this->site->get_template_id(),
					'v-init:template_id'          => $this->site->get_template_id(),
					'v-model'                     => 'template_id',
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

			/*
			 * Constrain the page to a reasonable reading width and centre it
			 * inside the customer panel. The previous full-bleed layout made
			 * the current-template card stretch the entire admin width, which
			 * left a lot of empty horizontal space on wide monitors and made
			 * the grid cards much wider than necessary. wu-max-w-screen-lg
			 * (1024px) lines up with the rest of the customer panel content
			 * areas; wu-mx-auto centres it.
			 */

			/*
			 * Scoped CSS adjustments for the switching page only. Each rule
			 * is scoped to .wu-template-switching-wrap so signup/checkout
			 * (which reuses views/checkout/templates/template-selection/
			 * clean.php) is not affected.
			 *
			 * 1. Hide the category filter list when only the "All" item is
			 *    present. The shared clean.php view always renders the
			 *    filter row even when there are zero real categories, which
			 *    leaves a lonely "All" link with no other options — pure
			 *    visual noise on the switching page where almost all
			 *    customers have a flat (uncategorised) template list.
			 *    `:has(li:nth-child(2))` is supported in all evergreen
			 *    browsers (Chrome 105+, Safari 15.4+, Firefox 121+); on
			 *    older browsers the list simply remains visible — graceful
			 *    degradation, not a regression.
			 *
			 * 2. Tighten the vertical gap between the filter row and the
			 *    grid. The form widget wraps each field in `wu-py-5` (20px
			 *    top + 20px bottom) which, combined with the filter row's
			 *    own `wu-mb-4` (16px) and the grid container's natural
			 *    margin, produced ~80px of empty space between the two —
			 *    far too much given they are visually one unit (filter +
			 *    its filtered grid). Pulling top padding off the grid
			 *    field-wrapper closes the gap to a comfortable ~24px.
			 */
			?>
			<style>
				.wu-template-switching-wrap #wu-site-template-filter:not(:has(li:nth-child(2))) {
					display: none;
				}
				.wu-template-switching-wrap li.wu-template-switching-grid-field {
					padding-top: 0;
				}
				.wu-template-switching-wrap #wu-site-template-filter {
					margin-bottom: 0;
				}
			</style>
			<?php
			/*
			 * Inline `max-width` + `margin: 0 auto` instead of the
			 * `wu-max-w-screen-lg wu-mx-auto` utility classes. The compiled
			 * `assets/css/framework.css` scopes every max-width utility under
			 * `.wu-styling :is(...)` (a Tailwind purge artefact), so the
			 * classes only take effect inside an element whose ancestor has
			 * `class="wu-styling"`. The customer-panel admin shell does not
			 * apply that wrapper class, which left the page rendering full
			 * width despite the utility classes being present in the markup.
			 * Using an inline style guarantees the cap is honoured regardless
			 * of CSS scope, without rebuilding framework.css.
			 *
			 * 960px (vs the 1024px of `wu-max-w-screen-lg`) gives a slightly
			 * tighter reading width that makes the 3-column grid feel less
			 * sparse on wide monitors and prevents card thumbnails from
			 * stretching too tall when only 1-2 templates exist.
			 */
			echo '<div class="wu-template-switching-wrap" style="max-width:960px;margin:0 auto;">';
			$form->render();
			echo '</div>';
		}
	}

	/**
	 * Render the not-authorized notice.
	 *
	 * @since 2.9.3
	 * @return void
	 */
	protected function render_not_allowed_notice() {

		printf(
			'<div class="notice notice-warning wu-m-0 wu-p-4"><p>%s</p></div>',
			esc_html__('Template switching is not available right now. Please contact your network administrator for help with this site.', 'ultimate-multisite')
		);
	}

	/**
	 * Render the no-membership informational notice.
	 *
	 * @since 2.9.3
	 * @return void
	 */
	protected function render_no_membership_notice() {

		printf(
			'<div class="notice notice-info wu-m-0 wu-mb-4 wu-p-4"><p>%s</p></div>',
			esc_html__('This site is not currently linked to a membership, so plan-specific template restrictions do not apply.', 'ultimate-multisite')
		);
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
