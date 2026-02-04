<?php
/**
 * Adds the Login Form Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use WP_Ultimo\Checkout\Checkout_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Checkout Element UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Login_Form_Element extends Base_Element {

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
	public $id = 'login-form';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var bool
	 */
	protected $public = true;

	/**
	 * If the current user is logged in.
	 *
	 * @since 2.2.0
	 * @var bool
	 */
	protected $logged;

	/**
	 * Initializes the singleton.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public function init(): void {

		// Handle login redirection
		add_filter('login_redirect', [$this, 'handle_redirect'], -1, 3);

		parent::init();
	}

	/**
	 * The icon of the UI element.
	 * e.g. return fa fa-search
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 */
	public function get_icon($context = 'block'): string {

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

		return __('Login Form', 'ultimate-multisite');
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

		return __('Adds a login form to the page.', 'ultimate-multisite');
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

		$fields['display_title'] = [
			'type'    => 'toggle',
			'title'   => __('Display Title?', 'ultimate-multisite'),
			'desc'    => __('Toggle to show/hide the title element.', 'ultimate-multisite'),
			'tooltip' => '',
			'value'   => 1,
		];

		$fields['title'] = [
			'type'     => 'text',
			'title'    => __('Title', 'ultimate-multisite'),
			'value'    => __('Login', 'ultimate-multisite'),
			'desc'     => '',
			'tooltip'  => '',
			'required' => [
				'display_title' => 1,
			],
		];

		$fields['redirect_type'] = [
			'type'    => 'select',
			'title'   => __('Redirect Type', 'ultimate-multisite'),
			'desc'    => __('The behavior after login', 'ultimate-multisite'),
			'tooltip' => '',
			'default' => 'default',
			'options' => [
				'default'       => __('Wordpress Default', 'ultimate-multisite'),
				'customer_site' => __('Send To Customer Site', 'ultimate-multisite'),
				'main_site'     => __('Send To Main Site', 'ultimate-multisite'),
			],
		];

		$fields['customer_redirect_path'] = [
			'type'     => 'text',
			'title'    => __('Customer Redirect Path', 'ultimate-multisite'),
			'value'    => __('/wp-admin', 'ultimate-multisite'),
			'desc'     => __('e.g. /wp-admin', 'ultimate-multisite'),
			'tooltip'  => '',
			'required' => [
				'redirect_type' => 'customer_site',
			],
		];

		$fields['main_redirect_path'] = [
			'type'     => 'text',
			'title'    => __('Main Site Redirect Path', 'ultimate-multisite'),
			'value'    => __('/wp-admin', 'ultimate-multisite'),
			'desc'     => __('e.g. /wp-admin', 'ultimate-multisite'),
			'tooltip'  => '',
			'required' => [
				'redirect_type' => 'main_site',
			],
		];

		$fields['header_username'] = [
			'title' => __('Username Field', 'ultimate-multisite'),
			'desc'  => __('Username Field', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['label_username'] = [
			'type'    => 'text',
			'title'   => __('Username Field Label', 'ultimate-multisite'),
			'value'   => __('Username or Email Address', 'ultimate-multisite'),
			'desc'    => __('Leave blank to hide.', 'ultimate-multisite'),
			'tooltip' => '',
		];

		$fields['placeholder_username'] = [
			'type'    => 'text',
			'title'   => __('Username Field Placeholder', 'ultimate-multisite'),
			'desc'    => __('e.g. Username Here', 'ultimate-multisite'),
			'value'   => '',
			'tooltip' => '',
		];

		$fields['header_password'] = [
			'title' => __('Password Field', 'ultimate-multisite'),
			'desc'  => __('Password Field', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['label_password'] = [
			'type'    => 'text',
			'title'   => __('Password Field Label', 'ultimate-multisite'),
			'value'   => __('Password', 'ultimate-multisite'),
			'desc'    => __('Leave blank to hide.', 'ultimate-multisite'),
			'tooltip' => '',
		];

		$fields['placeholder_password'] = [
			'type'    => 'text',
			'title'   => __('Password Field Placeholder', 'ultimate-multisite'),
			'desc'    => __('e.g. Your Password', 'ultimate-multisite'),
			'value'   => '',
			'tooltip' => '',
		];

		$fields['header_remember'] = [
			'title' => __('Remember Me', 'ultimate-multisite'),
			'desc'  => __('Remember Me', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['remember'] = [
			'type'    => 'toggle',
			'title'   => __('Display Remember Toggle?', 'ultimate-multisite'),
			'desc'    => __('Toggle to show/hide the remember me checkbox.', 'ultimate-multisite'),
			'tooltip' => '',
			'value'   => 1,
		];

		$fields['label_remember'] = [
			'type'     => 'text',
			'title'    => __('Remember Me Label', 'ultimate-multisite'),
			'value'    => __('Remember Me', 'ultimate-multisite'),
			'desc'     => '',
			'tooltip'  => '',
			'required' => [
				'remember' => 1,
			],
		];

		$fields['desc_remember'] = [
			'type'     => 'text',
			'title'    => __('Remember Me Description', 'ultimate-multisite'),
			'value'    => __('Keep me logged in for two weeks.', 'ultimate-multisite'),
			'desc'     => '',
			'tooltip'  => '',
			'required' => [
				'remember' => 1,
			],
		];

		$fields['header_submit'] = [
			'title' => __('Submit Button', 'ultimate-multisite'),
			'desc'  => __('Submit Button', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['label_log_in'] = [
			'type'    => 'text',
			'title'   => __('Submit Button Label', 'ultimate-multisite'),
			'value'   => __('Log In', 'ultimate-multisite'),
			'tooltip' => '',
		];

		return $fields;
	}

	/**
	 * Registers scripts and styles necessary to render this.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		wp_enqueue_style('wu-admin');

		// Enqueue password styles (includes dashicons as dependency).
		wp_enqueue_style('wu-password');

		// Enqueue password toggle script.
		wp_enqueue_script('wu-password-toggle');

		wp_set_script_translations('wu-password-toggle', 'ultimate-multisite');

		// Enqueue password strength scripts for reset password page.
		if ($this->is_reset_password_page()) {
			// wu-password-strength is globally registered with password-strength-meter as dependency.
			wp_enqueue_script('wu-password-strength');

			// Enqueue password reset script.
			wp_enqueue_script(
				'wu-password-reset',
				wu_get_asset('wu-password-reset.js', 'js'),
				['wu-password-strength'],
				wu_get_version(),
				true
			);
		}
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

		// Default 'redirect' value takes the user back to the request URI.
		$redirect_to = wu_get_current_url();

		return [
			'display_title'          => 1,
			'title'                  => __('Login', 'ultimate-multisite'),

			'redirect_type'          => 'default',
			'customer_redirect_path' => '/wp-admin',
			'main_redirect_path'     => '/wp-admin',

			'redirect'               => $redirect_to,
			'form_id'                => 'loginform',

			'label_username'         => __('Username or Email Address'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'placeholder_username'   => '',

			'label_password'         => __('Password'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'placeholder_password'   => '',

			'label_remember'         => __('Remember Me'),  // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'desc_remember'          => __('Keep me logged in for two weeks.', 'ultimate-multisite'),

			'label_log_in'           => __('Log In'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain

			'id_username'            => 'user_login',
			'id_password'            => 'user_pass',
			'id_remember'            => 'rememberme',
			'id_submit'              => 'wp-submit',
			'remember'               => true,
			'value_username'         => '',
			'value_remember'         => false, // Set 'value_remember' to true to default the "Remember me" checkbox to checked.
		];
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup(): void {

		$this->logged = is_user_logged_in();

		if ($this->is_reset_password_page()) {
			$rp_path = '/';

			$rp_cookie = 'wp-resetpass-' . COOKIEHASH;

			if (isset($_GET['key']) && isset($_GET['login'])) { // phpcs:ignore WordPress.Security.NonceVerification
				$value = sprintf('%s:%s', sanitize_text_field(wp_unslash($_GET['login'])), sanitize_text_field(wp_unslash($_GET['key']))); // phpcs:ignore WordPress.Security.NonceVerification

				setcookie(
					$rp_cookie,
					$value,
					[
						'expires'  => 0,
						'path'     => $rp_path,
						'domain'   => (string) COOKIE_DOMAIN,
						'secure'   => is_ssl(),
						'httponly' => true,
					]
				);

				wp_safe_redirect(remove_query_arg(['key', 'login']));

				exit;
			}
		}

		global $post;

		/*
		 * Handles maintenance mode on Elementor.
		 */
		if ($post && absint(wu_get_setting('default_login_page', 0)) === $post->ID) {
			add_filter('elementor/maintenance_mode/is_login_page', '__return_true');
		}
	}

	/**
	 * Checks if we are in a lost password form page.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_lost_password_page() {

		return wu_request('action') === 'lostpassword';
	}

	/**
	 * Checks if we are in the email confirm instruction page of a reset password.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_check_email_confirm() {

		return wu_request('checkemail') === 'confirm';
	}

	/**
	 * Checks if we are in a reset password page.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_reset_password_page() {

		return wu_request('action') === 'rp' || wu_request('action') === 'resetpass';
	}

	/**
	 * Checks if we are in the the password rest confirmation page.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_reset_confirmation_page() {

		return wu_request('password-reset') === 'success';
	}

	/**
	 * Handle custom login redirection
	 *
	 * @since 2.0.11
	 *
	 * @param string             $redirect_to            The redirect destination URL.
	 * @param string             $requested_redirect_to  The requested redirect destination URL.
	 * @param /WP_User|/WP_Error $user                   The URL to redirect user.
	 * @return string
	 */
	public function handle_redirect($redirect_to, $requested_redirect_to, $user) {

		if (is_wp_error($user)) {
			if (wu_request('wu_login_page_url')) {
				$redirect_to = wu_request('wu_login_page_url');
				$redirect_to = add_query_arg('error', $user->get_error_code(), $redirect_to);

				if ($user->get_error_code() === 'invalid_username') {
					$redirect_to = add_query_arg('username', wu_request('log'), $redirect_to);
				}

				// In this case, WP will not redirect, so we need to do it here
				wp_safe_redirect($redirect_to);
				exit;
			}

			return $redirect_to;
		}

		$redirect_type = wu_request('wu_login_form_redirect_type', 'default');

		// If some condition match, force user redirection to the URL
		if ('query_redirect' === $redirect_type) {

			// query_redirect is the default wp behaviour
			return $redirect_to;
		} elseif ('customer_site' === $redirect_type) {
			$user_site = get_active_blog_for_user($user->ID);

			wp_safe_redirect($user_site->siteurl . $requested_redirect_to);
			exit;
		} elseif ('main_site' === $redirect_type) {
			exit;
		}

		return $redirect_to;
	}

	/**
	 * Allows the setup in the context of previews.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_preview(): void {

		$this->logged = false;
	}

	/**
	 * Returns the logout URL for the "not you bar".
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_logout_url() {

		$redirect_to = wu_get_current_url();

		return wp_logout_url($redirect_to);
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
	public function output($atts, $content = null): void {

		$view = 'dashboard-widgets/login-additional-forms';

		/*
		 * Checks if we are in the confirmation page.
		 *
		 * If that's the case, we show a successful message and the
		 * login URL so the user can re-login with the new password.
		 */
		if ($this->is_reset_confirmation_page()) {
			$fields = [
				'email-activation-instructions' => [
					'type' => 'note',
					'desc' => __('Your password has been reset.') . ' <a href="' . esc_url(wp_login_url()) . '">' . __('Log in') . '</a>', // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				],
			];

			/*
			* Check if are in the email confirmation instructions page.
			*
			* If that's the case, we show the instructions.
			*/
		} elseif ($this->is_check_email_confirm()) {
			$fields = [
				'email-activation-instructions' => [
					'type' => 'note',
					'desc' => sprintf(
						/* translators: %s: Link to the login page. */
						__('Check your email for the confirmation link, then visit the <a href="%s">login page</a>.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
						wp_login_url()
					),
				],
			];

			/*
			* Check if we are in the set new password page.
			*
			* If that's the case, we show the new password fields
			* so the user can set a new password.
			*/
		} elseif ($this->is_reset_password_page()) {
			$rp_cookie = 'wp-resetpass-' . COOKIEHASH;

			if (isset($_COOKIE[ $rp_cookie ]) && str_contains(sanitize_text_field(wp_unslash($_COOKIE[ $rp_cookie ])), ':')) {
				[$rp_login, $rp_key] = explode(':', sanitize_text_field(wp_unslash($_COOKIE[ $rp_cookie ])), 2);

				$user = check_password_reset_key($rp_key, $rp_login);

				// If the reset key is invalid or expired, redirect with appropriate error.
				if (is_wp_error($user)) {
					$error_code  = $user->get_error_code();
					$redirect_to = add_query_arg(
						[
							'action' => 'lostpassword',
							'error'  => $error_code,
						],
						remove_query_arg(['action', 'key', 'login'])
					);

					// Clear the invalid cookie.
					setcookie(
						$rp_cookie,
						' ',
						[
							'expires'  => time() - YEAR_IN_SECONDS,
							'path'     => '/',
							'domain'   => (string) COOKIE_DOMAIN,
							'secure'   => is_ssl(),
							'httponly' => true,
						]
					);

					wp_safe_redirect($redirect_to);

					exit;
				}

				if (isset($_POST['pass1']) && isset($_POST['rp_key']) && ! hash_equals($rp_key, wp_unslash($_POST['rp_key']))) { // phpcs:ignore WordPress.Security.NonceVerification
					$user = false;
				}
			} else {
				$user = false;
			}

			$redirect_to = add_query_arg('password-reset', 'success', remove_query_arg(['action', 'error']));

			$fields = [
				'pass1'       => [
					'type'        => 'password',
					'title'       => __('New password'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'placeholder' => '',
					'value'       => '',
					'meter'       => true,
					'html_attr'   => [
						'size'           => 24,
						'autocapitalize' => 'off',
						'autocomplete'   => 'new-password',
					],
				],
				'pass2'       => [
					'type'        => 'password',
					'title'       => __('Confirm new password'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'placeholder' => '',
					'value'       => '',
					'html_attr'   => [
						'size'           => 24,
						'autocapitalize' => 'off',
						'autocomplete'   => 'new-password',
					],
				],
				'action'      => [
					'type'  => 'hidden',
					'value' => 'resetpass',
				],
				'rp_key'      => [
					'type'  => 'hidden',
					'value' => $rp_key ?? '',
				],
				'user_login'  => [
					'type'  => 'hidden',
					'value' => $rp_login ?? '',
				],
				'redirect_to' => [
					'type'  => 'hidden',
					'value' => $redirect_to,
				],
				'wp-submit'   => [
					'type'            => 'submit',
					'title'           => __('Save Password'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'value'           => __('Save Password'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'classes'         => 'button button-primary wu-w-full',
					'wrapper_classes' => 'wu-items-end wu-bg-none',
				],
			];

			/*
			* Checks if we are in the first reset password page, where the customer requests a reset.
			*
			* If that's the case, we show the username/email field, so the user can
			* get an email with the reset link.
			*/
		} elseif ($this->is_lost_password_page()) {
			$user_login = wu_request('user_login', '');

			if ($user_login) {
				$user_login = wp_unslash($user_login);
			}

			$redirect_to = add_query_arg('checkemail', 'confirm', remove_query_arg(['action', 'error']));

			$fields = [
				'lost-password-instructions' => [
					'type'    => 'note',
					'desc'    => __('Please enter your username or email address. You will receive an email message with instructions on how to reset your password.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'tooltip' => '',
				],
				'user_login'                 => [
					'type'        => 'text',
					'title'       => __('Username or Email Address'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'placeholder' => '',
					'value'       => $user_login,
					'html_attr'   => [
						'size'           => 20,
						'autocapitalize' => 'off',
					],
				],
				'action'                     => [
					'type'  => 'hidden',
					'value' => 'lostpassword',
				],
				'redirect_to'                => [
					'type'  => 'hidden',
					'value' => $redirect_to,
				],
				'wp-submit'                  => [
					'type'            => 'submit',
					'title'           => __('Get New Password'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'value'           => __('Get New Password'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'classes'         => 'button button-primary wu-w-full',
					'wrapper_classes' => 'wu-items-end wu-bg-none',
				],
			];
		} else {
			$view = 'dashboard-widgets/login-form';

			$fields = [
				'log' => [
					'type'        => 'text',
					'title'       => $atts['label_username'],
					'placeholder' => $atts['placeholder_username'],
					'tooltip'     => '',
					'html_attr'   => [
						'autocomplete' => 'username',
					],
				],
				'pwd' => [
					'type'        => 'password',
					'title'       => $atts['label_password'],
					'placeholder' => $atts['placeholder_password'],
					'tooltip'     => '',
					'html_attr'   => [
						'autocomplete' => 'current-password',
					],
				],
			];

			if ($atts['remember']) {
				$fields['rememberme'] = [
					'type'  => 'toggle',
					'title' => $atts['label_remember'],
					'desc'  => $atts['desc_remember'],
				];
			}

			$fields['redirect_to'] = [
				'type'  => 'hidden',
				'value' => isset($_GET['redirect_to']) ? sanitize_url(wp_unslash($_GET['redirect_to'])) : $atts['redirect'], // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			];

			if (isset($_GET['redirect_to'])) { // phpcs:ignore WordPress.Security.NonceVerification
				$atts['redirect_type'] = 'query_redirect';
			} elseif ('customer_site' === $atts['redirect_type']) {
				$fields['redirect_to']['value'] = $atts['customer_redirect_path'];
			} elseif ('main_site' === $atts['redirect_type']) {
				$fields['redirect_to']['value'] = $atts['main_redirect_path'];
			}

			$fields['wu_login_form_redirect_type'] = [
				'type'  => 'hidden',
				'value' => $atts['redirect_type'],
			];

			$fields['wp-submit'] = [
				'type'            => 'submit',
				'title'           => $atts['label_log_in'],
				'value'           => $atts['label_log_in'],
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end wu-bg-none',
			];

			$fields['lost-password'] = [
				'type'            => 'html',
				'content'         => sprintf('<a class="wu-text-xs wu-block wu-text-center wu--mt-4" href="%s">%s</a>', esc_url(add_query_arg('action', 'lostpassword')), __('Lost your password?')), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'classes'         => '',
				'wrapper_classes' => 'wu-items-end wu-bg-none',
			];
		}

		/*
		 * Check for error messages
		 *
		 * If we have some, we add a field
		 * at the top of the field array to display the errors.
		 */
		if (wu_request('error')) {
			$username = wu_request('username', '');

			$error_message_field = [
				'error_message' => [
					'type' => 'note',
					'desc' => Checkout_Pages::get_instance()->get_error_message(wu_request('error'), $username),
				],
			];

			$fields = array_merge($error_message_field, $fields);
		}

		$fields['wu_login_page_url'] = [
			'type'  => 'hidden',
			'value' => wu_get_current_url(),
		];

		/**
		 * Instantiate the form for the order details.
		 *
		 * @since 2.0.0
		 */
		$form = new \WP_Ultimo\UI\Form(
			$this->get_id(),
			$fields,
			[
				'action'                => esc_url(site_url('wp-login.php', 'login_post')),
				'wrap_in_form_tag'      => true,
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-p-0 wu-m-0',
				'field_wrapper_classes' => 'wu-box-border wu-items-center wu-flex wu-justify-between wu-py-4 wu-m-0',
				'html_attr'             => [
					'class' => 'wu-w-full',
				],
			]
		);

		$atts['logged']    = $this->logged;
		$atts['login_url'] = $this->get_logout_url();
		$atts['form']      = $form;

		wu_get_template($view, $atts);
	}
}
