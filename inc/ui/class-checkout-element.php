<?php
/**
 * Adds the Checkout_Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use Psr\Log\LogLevel;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Exception\SassException;
use WP_Ultimo\Database\Memberships\Membership_Status;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Checkout Element UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Checkout_Element extends Base_Element {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The current signup.
	 *
	 * @since 2.2.0
	 * @var Mocked_Signup
	 */
	protected $signup;

	/**
	 * The current checkout form steps.
	 *
	 * @since 2.2.0
	 * @var array|false
	 */
	public $steps;

	/**
	 * The current checkout form step.
	 *
	 * @since 2.2.0
	 * @var array
	 */
	public $step;

	/**
	 * The current checkout form step name.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $step_name;

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
	public $id = 'checkout';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var boolean
	 */
	protected $public = true;

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
	 * This is used on the Blocks list of Gutenberg.
	 * You should return a string with the localized title.
	 * e.g. return __('My Element', 'ultimate-multisite').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {

		return __('Checkout', 'ultimate-multisite');
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

		return __('Adds a checkout form block to the page.', 'ultimate-multisite');
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

		$fields['slug'] = [
			'title' => __('Slug', 'ultimate-multisite'),
			'desc'  => __('The checkout form slug.', 'ultimate-multisite'),
			'type'  => 'text',
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
			'Ultimate Multisite',
			'Checkout',
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
			'slug'                   => 'main-form',
			'step'                   => false,
			'display_title'          => false,
			'membership_limitations' => [],
		];
	}

	/**
	 * Checks if we are on a thank you page.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_thank_you_page() {

		return is_user_logged_in() && wu_request('payment') && wu_request('status') === 'done';
	}

	/**
	 * Triggers the setup event to allow the checkout class to hook in.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup() {

		if ($this->is_thank_you_page()) {
			\WP_Ultimo\UI\Thank_You_Element::get_instance()->setup();

			return;
		}

		do_action('wu_setup_checkout', $this);
	}

	/**
	 * @return void
	 */
	public function register_scripts() {
		$slug          = $this->get_pre_loaded_attribute('slug');
		$checkout_form = wu_get_checkout_form_by_slug($slug);

		if (! $checkout_form) {
			return;
		}
		$custom_css = $checkout_form->get_custom_css();

		if (! $custom_css) {
			return;
		}

		try {
			$scss       = new Compiler();
			$custom_css = $scss->compileString(
				".wu_checkout_form_{$slug} {
					{$custom_css}
				}"
			)->getCss();

			wp_add_inline_style('wu-checkout', $custom_css);
		} catch (SassException $e) {
			// translators: %s the error message.
			wu_log_add('checkout', sprintf(__('An error occurred while compiling scss: %s', 'ultimate-multisite'), $e->getMessage()), LogLevel::ERROR);
		}
	}

	/**
	 * Outputs thank you page.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output_thank_you($atts, $content = null): void {

		$slug = $atts['slug'];

		$checkout_form = wu_get_checkout_form_by_slug($slug);

		$atts = $checkout_form->get_meta('wu_thank_you_settings', []);

		$atts['checkout_form'] = $checkout_form;

		\WP_Ultimo\UI\Thank_You_Element::get_instance()->register_scripts();

		\WP_Ultimo\UI\Thank_You_Element::get_instance()->output($atts, $content);
	}

	/**
	 * Outputs the registration form.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output_form($atts, $content = null) {

		$atts['step'] = wu_request('step', $atts['step']);

		$slug = $atts['slug'];

		$customer = wu_get_current_customer();

		$membership = WP_Ultimo()->currents->get_membership();

		/**
		 * Allow developers bypass the output and set a new one
		 *
		 * @param string|bool $bypass If we should bypass the checkout form or a string to return instead of the form.
		 * @param array       $atts   Parameters of the checkout block/shortcode.
		 */
		$bypass = apply_filters('wu_bypass_checkout_form', false, $atts);

		if ($bypass) {
			if (is_string($bypass)) {
				echo wp_kses_post($bypass);
			}
			return;
		}

		if ($customer && $membership && 'wu-finish-checkout' !== $slug) {
			$published_sites = $membership->get_published_sites();

			$pending_payment = $membership->get_last_pending_payment();

			/*
			 * Pending payment notice logic:
			 * - active/trialing with pending payment -> show notice AND block form
			 * - pending (abandoned checkout) with pending payment -> show notice
			 *   but do NOT block the form so user can pick a different plan.
			 *
			 * @since 2.4.13
			 */
			$membership_is_active_or_trialing = $membership->is_active()
				|| $membership->get_status() === Membership_Status::TRIALING;

			$membership_is_pending = $membership->get_status() === Membership_Status::PENDING;

			if ($pending_payment && ($membership_is_active_or_trialing || $membership_is_pending)) {
				/**
				 *  We are talking about membership with a pending payment
				 */
				echo '<p>';
				// Translators: Placeholder receives the customer display name
				printf(esc_html__('Hi %s. You have a pending payment for your membership!', 'ultimate-multisite'), esc_html($customer->get_display_name()));

				$payment_url = add_query_arg(
					[
						'payment' => $pending_payment->get_hash(),
					],
					wu_get_registration_url()
				);

				// Translators: The link to registration url with payment hash
				echo '<br>' . wp_kses_post(sprintf(__('Click <a href="%s">here</a> to pay.', 'ultimate-multisite'), esc_attr($payment_url)));

				echo '</p>';

				// Only block the form for active/trialing memberships.
				// For pending (abandoned) memberships, show the notice but let them continue.
				if ($membership_is_active_or_trialing) {
					return;
				}
			}

			$membership_blocked_forms = [
				'wu-add-new-site',
			];

			if ( ! $membership->is_active() && $membership->get_status() !== Membership_Status::TRIALING && in_array($atts['slug'], $membership_blocked_forms, true)) {

				// Translators: Placeholder receives the customer display name
				printf(esc_html__('Hi %s. You cannot take action on your membership while it is not active!', 'ultimate-multisite'), esc_html($customer->get_display_name()));

				if ($membership->get_status() === Membership_Status::PENDING && $customer->get_email_verification() === 'pending') {
					/**
					 * Enqueue thank you page scripts to handle resend email verification link
					 */
					wp_register_script('wu-thank-you', wu_get_asset('thank-you.js', 'js'), [], wu_get_version(), true);

					wp_localize_script(
						'wu-thank-you',
						'wu_thank_you',
						[
							'ajaxurl'         => admin_url('admin-ajax.php'),
							'resend_verification_email_nonce' => wp_create_nonce('wu_resend_verification_email_nonce'),
							'membership_hash' => $membership->get_hash(),
							'i18n'            => [
								'resending_verification_email' => __('Resending verification email...', 'ultimate-multisite'),
								'email_sent' => __('Verification email sent!', 'ultimate-multisite'),
							],
						]
					);

					wp_enqueue_script('wu-thank-you');

					echo '<p>' . esc_html__('Check your inbox and verify your email address.', 'ultimate-multisite') . '</p>';
					echo '<span class="wu-styling">';
					printf('<a href="#" class="wu-mr-2 wu-resend-verification-email wu-no-underline button button-primary">%s</a>', esc_html__('Resend verification email', 'ultimate-multisite'));
					echo '</span>';
				}

				return;
			}

			if ( ! wu_multiple_memberships_enabled() && $membership->is_active()) {
				/**
				 * Allow developers to add new form slugs to bypass this behaviour.
				 *
				 * @param array $slugs a list of form slugs to bypass.
				 */
				$allowed_forms = apply_filters(
					'wu_get_membership_allowed_forms',
					[
						'wu-checkout',
						'wu-add-new-site',
						'wu-register-domain',
					]
				);

				if ( ! in_array($slug, $allowed_forms, true) && ! wu_request('payment')) {
					printf('<p>%s</p>', esc_html__('You already have a membership!', 'ultimate-multisite'));

					if (isset($published_sites[0])) {
						printf(
							'<p><a class="wu-no-underline button button-primary" href="%s">%s</a><p>',
							esc_attr(get_admin_url($published_sites[0]->get_id(), 'admin.php?page=account')),
							esc_html__('Go to my account', 'ultimate-multisite')
						);
					}

					return;
				}
			}

			if ($membership->get_customer_id() !== $customer->get_id()) {
				printf('<p>%s</p>', esc_html__('You are not allowed to change this membership!', 'ultimate-multisite'));

				return;
			}

			/**
			 *  Now we filter the current membership for each membership_limitations
			 *  field in element atts to check if we can show the form, if not we show
			 *  a error message informing the user about and with buttons to allow
			 *  account upgrade and/or to buy a new membership.
			 */
			if (! empty($atts['membership_limitations'])) {
				$limits = $membership->get_limitations();

				foreach ($atts['membership_limitations'] as $limitation) {
					if ( ! method_exists($membership, "get_$limitation")) {
						continue;
					}

					$current_limit = $limits->{$limitation};

					$limit_max = $current_limit->is_enabled() ? $current_limit->get_limit() : PHP_INT_MAX;

					$limit_max = ! empty($limit_max) ? (int) $limit_max : 1;

					$used_limit = $membership->{"get_$limitation"}();

					$used_limit = is_array($used_limit) ? count($used_limit) : (int) $used_limit;

					if ($used_limit >= $limit_max) {

						// Translators: Placeholder receives the limit name
						echo '<p>' . sprintf(esc_html__('You reached your membership %s limit!', 'ultimate-multisite'), esc_html($limitation)) . '</p>';

						echo '<span class="wu-styling">';

						if (wu_multiple_memberships_enabled()) {
							printf(
								'<a class="wu-no-underline button button-primary wu-mr-2" href="%s">%s</a>',
								esc_url(wu_get_registration_url()),
								esc_html(__('Buy a new membership', 'ultimate-multisite'))
							);
						}

						if ('sites' !== $limitation || wu_get_setting('enable_multiple_sites')) {
							$update_link = '';

							$checkout_pages = \WP_Ultimo\Checkout\Checkout_Pages::get_instance();

							$update_url = $checkout_pages->get_page_url('update');

							if ($update_url) {
								$update_link = add_query_arg(
									[
										'membership' => $membership->get_hash(),
									],
									$update_url
								);
							} elseif (is_admin()) {
								$update_link = admin_url('admin.php?page=wu-checkout&membership=' . $membership->get_hash());
							} elseif (isset($published_sites[0])) {
								$update_link = get_admin_url($published_sites[0]->get_id(), 'admin.php?page=wu-checkout&membership=' . $membership->get_hash());
							}

							if ( ! empty($update_link)) {
								$button_text = __('Upgrade your account', 'ultimate-multisite');

								printf('<a class="wu-no-underline button button-primary wu-mr-2" href="%s">%s</a>', esc_attr($update_link), esc_html($button_text));
							}
						}

						echo '</span>';

						return;
					}
				}
			}
		} elseif ( ! $customer && 'wu-finish-checkout' === $slug) {
			echo '<p>';
			if (is_user_logged_in()) {
				esc_html_e('You need to be the account owner to complete this payment.', 'ultimate-multisite');
			} else {
				esc_html_e('You need to be logged in to complete a payment', 'ultimate-multisite');

				echo '<br>' . sprintf(
					// Translators: %s is replaced with <a href="{login_url}">here</a>
					esc_html__('Click %s to sign in.', 'ultimate-multisite'),
					'<a href="' . esc_attr(wp_login_url(wu_get_current_url())) . '">' .
					esc_html__('here', 'ultimate-multisite') .
					'</a>'
				);
			}

			echo '</p>';

			return;
		}

		$checkout_form = wu_get_checkout_form_by_slug($slug);

		if ( ! $checkout_form) {

			// translators: %s is the id of the form. e.g. main-form
			printf(esc_html__('Checkout form %s not found.', 'ultimate-multisite'), esc_html($slug));
			return;
		}

		if ($checkout_form->get_field_count() === 0) {

			// translators: %s is the id of the form. e.g. main-form
			printf(esc_html__('Checkout form %s contains no fields.', 'ultimate-multisite'), esc_html($slug));
			return;
		}

		if ( ! $checkout_form->is_active() || ! wu_get_setting('enable_registration', true)) {
			printf('<p>%s</p>', esc_html__('Registration is not available at this time.', 'ultimate-multisite'));
			return;
		}

		if ($checkout_form->has_country_lock()) {
			$geolocation = \WP_Ultimo\Geolocation::geolocate_ip('', true);

			if ( ! in_array($geolocation['country'], $checkout_form->get_allowed_countries(), true)) {
				printf('<p>%s</p>', esc_html__('Registration is closed for your location.', 'ultimate-multisite'));
				return;
			}
		}

		$checkout = \WP_Ultimo\Checkout\Checkout::get_instance();

		$checkout_form->get_steps_to_show();

		$this->steps = $checkout_form->get_steps_to_show();

		$step = $checkout_form->get_step($atts['step'], true);

		$this->step = $step ?: current($this->steps);

		$this->step = wp_parse_args(
			$this->step,
			[
				'classes' => '',
				'fields'  => [],
			]
		);

		$this->step_name = $this->step['id'] ?? '';

		/*
		 * Hack-y way to make signup available on the template.
		 */
		global $signup;

		$signup = new Mocked_Signup($this->step_name, $this->steps); // phpcs:ignore

		$this->signup = $signup;

		/*
		 * Load the checkout class with the parameters
		 * so we can access them inside the layouts.
		 */
		$checkout->checkout_form = $checkout_form;
		$checkout->steps         = $this->steps;
		$checkout->step          = $this->step;
		$checkout->step_name     = $this->step_name;
		$auto_submittable_field  = $checkout->contains_auto_submittable_field($this->step['fields']);

		$final_fields = wu_create_checkout_fields($this->step['fields']);

		/*
		 * Adds the product fields to keep them.
		 */
		$final_fields['products[]'] = [
			'type'      => 'hidden',
			'html_attr' => [
				'v-for'     => '(product, index) in unique_products',
				'v-model'   => 'products[index]',
				'v-bind:id' => '"products-" + index',
			],
		];

		$this->inject_inline_auto_submittable_field($auto_submittable_field);

		$final_fields = apply_filters('wu_checkout_form_final_fields', $final_fields, $this);

		wu_get_template(
			'checkout/form',
			[
				'step'               => $this->step,
				'step_name'          => $this->step_name,
				'checkout_form_name' => $atts['slug'],
				'errors'             => $checkout->errors,
				'display_title'      => $atts['display_title'],
				'final_fields'       => $final_fields,
			]
		);
	}

	/**
	 * Injects the auto-submittable field inline snippet.
	 *
	 * @since 2.0.11
	 *
	 * @param string $auto_submittable_field The auto-submittable field.
	 * @return void
	 */
	public function inject_inline_auto_submittable_field($auto_submittable_field): void {

		$callback = function () use ($auto_submittable_field) {

			wp_add_inline_script(
				'wu-checkout',
				sprintf(
					'

				/**
				 * Set the auto-submittable field, if one exists.
				 */
				window.wu_auto_submittable_field = %s;

			',
					wp_json_encode($auto_submittable_field)
				),
				'after'
			);
		};

		if (wu_is_block_theme() && ! is_admin()) {
			add_action('wu_checkout_scripts', $callback, 100);
		} else {
			call_user_func($callback);
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

		if (wu_is_update_page()) {
			$atts = [
				'slug'          => apply_filters('wu_membership_update_form', 'wu-checkout'),
				'step'          => false,
				'display_title' => false,
			];
		}

		if (wu_is_new_site_page()) {
			$atts = [
				'slug'                   => apply_filters('wu_membership_new_site_form', 'wu-add-new-site'),
				'step'                   => false,
				'display_title'          => false,
				'membership_limitations' => ['sites'],
			];
		}

		if ($this->is_thank_you_page()) {
			$this->output_thank_you($atts, $content);
			return;
		}

		/**
		 * Allow developers to add new update form slugs.
		 *
		 * @param array $slugs a list of form slugs to bypass.
		 */
		$update_forms = apply_filters(
			'wu_membership_update_forms',
			[
				'wu-checkout',
			]
		);

		if ( ! in_array($atts['slug'], $update_forms, true) && (wu_request('payment') || wu_request('payment_id'))) {
			$atts = [
				'slug'          => 'wu-finish-checkout',
				'step'          => false,
				'display_title' => false,
			];
		}

		if (wu_request('wu_form') && in_array(wu_request('wu_form'), $update_forms, true)) {
			$atts = [
				'slug'          => wu_request('wu_form'),
				'step'          => false,
				'display_title' => false,
			];
		}

		$this->output_form($atts, $content);
	}
}
