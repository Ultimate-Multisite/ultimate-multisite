<?php
/**
 * Handles registration pages and such.
 *
 * @package WP_Ultimo
 * @subpackage Checkout
 * @since 2.0.0
 */

namespace WP_Ultimo\Checkout;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles registration pages and such.
 *
 * @since 2.0.0
 */
class Checkout_Pages {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Initializes the Checkout_Pages singleton and adds hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_filter('display_post_states', [$this, 'add_wp_ultimo_status_annotation'], 10, 2);

		add_action('wu_thank_you_site_block', [$this, 'add_verify_email_notice'], 10, 3);

		add_shortcode('wu_confirmation', [$this, 'render_confirmation_page']);

		add_filter('lostpassword_redirect', [$this, 'filter_lost_password_redirect']);

		$use_custom_login = wu_get_setting('enable_custom_login_page', false);

		/*
		 * Login URL filters need to run on ALL sites (including subsites)
		 * so that password reset and login links redirect to the main site's
		 * custom login page instead of wp-login.php (which may be obfuscated).
		 *
		 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/291
		 */
		if ($use_custom_login) {
			add_filter('login_url', [$this, 'filter_login_url'], 10, 3);

			add_filter('lostpassword_url', [$this, 'filter_login_url'], 10, 3);
		}

		if (is_main_site()) {
			add_action('before_signup_header', [$this, 'redirect_to_registration_page']);

			if ( ! $use_custom_login) {
				return;
			}

			add_filter('retrieve_password_message', [$this, 'replace_reset_password_link'], 10, 4);

			add_filter('network_site_url', [$this, 'maybe_change_wp_login_on_urls']);

			add_action('login_init', [$this, 'maybe_obfuscate_login_url'], 9);

			add_action('template_redirect', [$this, 'maybe_redirect_to_admin_panel']);

			add_action('after_password_reset', [$this, 'maybe_redirect_to_confirm_screen']);

			add_action('lost_password', [$this, 'maybe_handle_password_reset_errors']);

			add_action('validate_password_reset', [$this, 'maybe_handle_password_reset_errors']);

			/**
			 * Adds the force elements controls.
			 */
			add_action('post_submitbox_misc_actions', [$this, 'render_compat_mode_setting']);

			add_action('save_post', [$this, 'handle_compat_mode_setting']);
		}
	}

	/**
	 * Filters the lost password redirect URL.
	 *
	 * @param string $redirect_to The redirect URL.
	 */
	public function filter_lost_password_redirect(string $redirect_to): string {

		if ( ! empty($redirect_to)) {
			return $redirect_to;
		}

		return add_query_arg('checkemail', 'confirm', wp_login_url());
	}

	/**
	 * Renders the compat mode option for pages and posts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_compat_mode_setting(): void {

		$post_id = get_the_ID();

		$value = get_post_meta($post_id, '_wu_force_elements_loading', true);

		wp_nonce_field('_wu_force_compat_' . $post_id, '_wu_force_compat');

		?>

	<div class="misc-pub-section misc-pub-section-last" style="margin-top: 12px; margin-bottom: 6px; display: flex; align-items: center;">
		<label for="wu-compat-mode">
				<span style="display: block; font-weight: 600; margin-bottom: 3px;"><?php esc_html_e('Ultimate Multisite Compatibility Mode', 'ultimate-multisite'); ?></span>
				<small style="display: block; line-height: 1.8em;"><?php esc_html_e('Toggle this option on if Ultimate Multisite elements are not loading correctly or at all.', 'ultimate-multisite'); ?></small>
		</label>
		<div style="margin-left: 6px;">
			<input id="wu-compat-mode" type="checkbox" value="1" <?php checked($value, true, true); ?> name="_wu_force_elements_loading" />
		</div>
	</div>

		<?php
	}

	/**
	 * Handles saving the compat mode switch on posts.
	 *
	 * @since 2.0.0
	 *
	 * @param int $post_id The id of the post being saved.
	 * @return void
	 */
	public function handle_compat_mode_setting($post_id): void {

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		// Nonce checked in calling method.
		if ( ! isset($_POST['_wu_force_compat']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wu_force_compat'])), '_wu_force_compat_' . $post_id)) {
			return;
		}

		if ( ! current_user_can('edit_post', $post_id)) {
			return;
		}

		if (isset($_POST['_wu_force_elements_loading'])) {
			update_post_meta($post_id, '_wu_force_elements_loading', sanitize_text_field(wp_unslash($_POST['_wu_force_elements_loading'])));
		} else {
			delete_post_meta($post_id, '_wu_force_elements_loading');
		}
	}

	/**
	 * Replace wp-login.php in email URLs.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url The URL to filter.
	 * @return string
	 */
	public function maybe_change_wp_login_on_urls($url) {
		/*
		 * Only perform computational-heavy tasks if the URL has
		 * wp-login.php in it to begin with.
		 */
		if (! str_contains($url, 'wp-login.php')) {
			return $url;
		}

		$post_id = wu_get_setting('default_login_page', 0);

		$post = get_post($post_id);

		if ($post) {
			$url = str_replace('wp-login.php', $post->post_name, $url);
		}

		return $url;
	}

	/**
	 * Get an error message.
	 *
	 * @since 2.0.0
	 *
	 * @param string $error_code The error code.
	 * @param string $username The username.
	 * @return string
	 */
	public function get_error_message($error_code, $username = '') {

		$messages = [
			'incorrect_password'         => sprintf(__('<strong>Error:</strong> The password you entered is incorrect.', 'ultimate-multisite')),
			// From here we are using the same messages as WordPress core.
			'expired'                    => __('Your session has expired. Please log in to continue where you left off.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			/* translators: %s: Link to the login page. */
			'confirm'                    => sprintf(__('Check your email for the confirmation link, then visit the <a href="%s">login page</a>.'), wp_login_url()), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			/* translators: %s: Link to the login page. */
			'registered'                 => sprintf(__('Registration complete. Please check your email, then visit the <a href="%s">login page</a>.'), wp_login_url()), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'loggedout'                  => __('You are now logged out.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'registerdisabled'           => __('<strong>Error:</strong> User registration is currently not allowed.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'empty_username'             => __('<strong>Error:</strong> The username field is empty.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'empty_password'             => __('<strong>Error:</strong> The password field is empty.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'invalid_email'              => __('Unknown email address. Check again or try your username.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			/* translators: %s: User name. */
			'invalid_username'           => sprintf(__('<strong>Error:</strong> The username <strong>%s</strong> is not registered on this site. If you are unsure of your username, try your email address instead.'), $username), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'invalidcombo'               => __('<strong>Error:</strong> There is no account with that username or email address.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'password_reset_empty_space' => __('The password cannot be a space or all spaces.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'password_reset_mismatch'    => __('<strong>Error:</strong> The passwords do not match.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'invalidkey'                 => __('<strong>Error:</strong> Your password reset link appears to be invalid. Please request a new link below.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'expiredkey'                 => __('<strong>Error:</strong> Your password reset link has expired. Please request a new link below.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'invalid_key'                => __('<strong>Error:</strong> Your password reset link appears to be invalid. Please request a new link below.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'expired_key'                => __('<strong>Error:</strong> Your password reset link has expired. Please request a new link below.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		];

		/**
		 * Filter the error messages.
		 *
		 * @since 2.1.1
		 * @param array $messages The error messages.
		 * @return array
		 */
		$messages = apply_filters('wu_checkout_pages_error_messages', $messages);

		return wu_get_isset($messages, $error_code, __('Something went wrong', 'ultimate-multisite'));
	}

	/**
	 * Handle password reset errors.
	 *
	 * We redirect users to our custom login URL,
	 * so we can add an error message.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Error $errors The error object.
	 * @return void
	 */
	public function maybe_handle_password_reset_errors($errors): void {

		if ($errors->has_errors()) {
			$url = add_query_arg(
				[
					'action'     => wu_request('action', ''),
					'user_login' => wu_request('user_login', ''),
					'error'      => $errors->get_error_code(),
				],
				wp_login_url()
			);

			wp_safe_redirect($url);

			exit;
		}
	}

	/**
	 * Maybe redirects users to the confirm screen.
	 *
	 * If we are successful in resetting a password,
	 * we need to prevent the user from reaching the empty
	 * wp-login.php message, so we redirect them to the passed
	 * redirect_to query argument.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function maybe_redirect_to_confirm_screen(): void {

		if (wu_request('redirect_to')) {
			wp_safe_redirect(wu_request('redirect_to'));

			exit;
		}
	}

	/**
	 * Replace the reset password link, if necessary.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message The email message.
	 * @param string $key The reset key.
	 * @param string $user_login The user login.
	 * @param array  $user_data The user data array.
	 * @return string
	 */
	public function replace_reset_password_link($message, $key, $user_login, $user_data) {

		if ( ! is_main_site()) {
			return $message;
		}

		$results = [];

		preg_match_all('/.*\/wp-login\.php.*/', $message, $results);

		$switched_locale = false;

		if (isset($results[0][0])) {

			// Localize password reset message content for user.
			$locale = get_user_locale($user_data);

			$switched_locale = switch_to_locale($locale);

			$new_url = add_query_arg(
				[
					'action'  => 'rp',
					'key'     => $key,
					'login'   => rawurlencode($user_login),
					'wp_lang' => $locale,
				],
				wp_login_url()
			);

			$new_url = set_url_scheme($new_url, null);

			$message = str_replace($results[0], $new_url, $message);
		}

		if ($switched_locale) {
			restore_previous_locale();
		}

		return $message;
	}

	/**
	 * Redirect logged users when they reach the login page.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function maybe_redirect_to_admin_panel(): void {

		global $post;

		if ( ! is_user_logged_in()) {
			return;
		}

		$custom_login_page = $this->get_signup_page('login');

		if (empty($custom_login_page) || empty($post)) {
			return;
		}

		if ($custom_login_page->ID !== $post->ID) {
			return;
		}

		/**
		 * Create an exclusion list of parameters that prevent the auto-redirect.
		 *
		 * This is needed because otherwise page builder won't be able to
		 * edit the login page once it is defined.
		 *
		 * @since 2.0.4
		 * @return array
		 */
		$exclusion_list = apply_filters(
			'wu_maybe_redirect_to_admin_panel_exclusion_list',
			[
				'preview',           // WordPress Preview
				'ct_builder',        // Oxygen Builder
				'fl_builder',        // Beaver Builder
				'elementor-preview', // Elementor
				'brizy-edit',        // Brizy
				'brizy-edit-iframe', // Brizy
			],
			$custom_login_page,
			$post,
			$this
		);

		foreach ($exclusion_list as $exclusion_param) {
			if (wu_request($exclusion_param, null) !== null) {
				return;
			}
		}

		$user = wp_get_current_user();

		$active_blog = get_active_blog_for_user($user->ID);

		$redirect_to = $active_blog ? get_admin_url($active_blog->blog_id) : false;

		if (isset($_GET['redirect_to'])) { // phpcs:ignore WordPress.Security.NonceVerification
			$redirect_to = sanitize_url(wp_unslash($_GET['redirect_to'])); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif (is_multisite() && ! get_active_blog_for_user($user->ID) && ! is_super_admin($user->ID)) {
			$redirect_to = user_admin_url();
		} elseif (is_multisite() && ! $user->has_cap('read')) {
			$redirect_to = get_dashboard_url($user->ID);
		} elseif ( ! $user->has_cap('edit_posts')) {
			$redirect_to = $user->has_cap('read') ? admin_url('profile.php') : home_url();
		}

		if ( ! $redirect_to) {
			return;
		}

		wp_safe_redirect($redirect_to);

		exit;
	}

	/**
	 * Adds the unverified email account error message.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment The current payment.
	 * @param \WP_Ultimo\Models\Membership $membership the current membership.
	 * @param \WP_Ultimo\Models\Customer   $customer the current customer.
	 * @return void
	 */
	public function add_verify_email_notice($payment, $membership, $customer): void {

		if ($payment->get_total() === 0.0 && $customer->get_email_verification() === 'pending') {
			printf(
				'<div class="wu-p-4 wu-bg-yellow-200 wu-mb-2 wu-text-yellow-700 wu-rounded">
                    %s
                    <br>
                    <a href="#" class="wu-resend-verification-email wu-text-gray-700">%s</a>
                </div>',
				sprintf(
					// translators: %1$s and %2$s are <strong></strong> HTML tags
					esc_html__('Your email address is not yet verified. Your site %1$s will only be activated %2$s after your email address is verified. Check your inbox and verify your email address.', 'ultimate-multisite'),
					'<strong>',
					'</strong>'
				),
				esc_html__('Resend verification email →', 'ultimate-multisite')
			);
		}
	}

	/**
	 * Check if we should obfuscate the login URL.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function maybe_obfuscate_login_url(): void {

		$use_custom_login = wu_get_setting('enable_custom_login_page', false);

		if ( ! $use_custom_login) {
			return;
		}

		if (isset($_SERVER['REQUEST_METHOD']) && 'POST' === $_SERVER['REQUEST_METHOD']) {
			return;
		}

		if (wu_request('interim-login')) {
			return;
		}

		if (wu_request('action') === 'logout') {
			return;
		}

		$new_login_url = $this->get_page_url('login');

		if ( ! $new_login_url) {
			return;
		}

		$should_obfuscate = wu_get_setting('obfuscate_original_login_url', 1);

		$bypass_obfuscation = wu_request('wu_bypass_obfuscation');

		if ($should_obfuscate && ! $bypass_obfuscation) {
			status_header(404);

			nocache_headers();

			global $wp_query;

			$wp_query->set_404();

			$four_oh_four = get_404_template();

			// Apparently not all themes have a 404 template.
			if ($four_oh_four) {
				include $four_oh_four;
			}

			die;
		} else {
			wp_safe_redirect($new_login_url);

			exit;
		}
	}

	/**
	 * Redirects the customers to the registration page, when one is used.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function redirect_to_registration_page(): void {

		$registration_url = $this->get_page_url('register');

		if ($registration_url) {
			wp_safe_redirect($registration_url);

			exit;
		}
	}

	/**
	 * Filters the login URL if necessary.
	 *
	 * @since 2.0.0
	 *
	 * @param string $login_url Original login URL.
	 * @param string $redirect URL to redirect to after login.
	 * @param bool   $force_reauth If we need to force reauth.
	 * @return string
	 */
	public function filter_login_url($login_url, $redirect, $force_reauth = false) {

		/**
		 * Fix incompatibility with UIPress, making sure we only filter after wp_loaded ran.
		 */
		if ( ! did_action('wp_loaded')) {
			return $login_url;
		}

		$function_caller = wu_get_function_caller(5);

		if ('wp_auth_check_html' === $function_caller) {
			return $login_url;
		}

		$params = [];

		$old_url_params = wp_parse_url($login_url, PHP_URL_QUERY);

		wp_parse_str($old_url_params, $params);

		$new_login_url = $this->get_page_url('login');

		if ( ! $new_login_url) {
			return $login_url;
		}

		if ($params) {
			$new_login_url = add_query_arg($params, $new_login_url);
		}

		if ($redirect) {
			$new_login_url = add_query_arg('redirect_to', rawurlencode($redirect), $new_login_url);
		}

		if ($force_reauth) {
			$new_login_url = add_query_arg('reauth', 1, $new_login_url);
		}

		return $new_login_url;
	}

	/**
	 * Returns the ID of the pages being used for each Ultimate Multisite purpose.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_signup_pages() {

		return [
			'register'       => wu_guess_registration_page(),
			'update'         => wu_get_setting('default_update_page', false),
			'login'          => wu_get_setting('default_login_page', false),
			'block_frontend' => wu_get_setting('default_block_frontend_page', false),
			'new_site'       => wu_get_setting('default_new_site_page', false),
		];
	}
	/**
	 * Returns the WP_Post object for one of the pages.
	 *
	 * @since 2.0.0
	 *
	 * @param string $page The slug of the page to retrieve.
	 * @return \WP_Post|false
	 */
	public function get_signup_page($page) {

		$pages = $this->get_signup_pages();

		$page_id = wu_get_isset($pages, $page);

		if ( ! $page_id) {
			return false;
		}

		return get_blog_post(wu_get_main_site_id(), $page_id);
	}
	/**
	 * Returns the URL for a particular page type.
	 *
	 * @since 2.0.0
	 *
	 * @param string $page_slug The signup page to get.
	 * @return string|false
	 */
	public function get_page_url($page_slug = 'login') {

		$page = $this->get_signup_page($page_slug);

		if ( ! $page) {
			return false;
		}

		return wu_switch_blog_and_run(fn() => get_the_permalink($page));
	}

	/**
	 * Tags the Ultimate Multisite pages on the main site.
	 *
	 * @since 2.0.0
	 *
	 * @param array    $states The previous states of that page.
	 * @param \WP_Post $post The current post.
	 * @return array
	 */
	public function add_wp_ultimo_status_annotation($states, $post) {

		if ( ! is_main_site()) {
			return $states;
		}

		$labels = [
			'register'       => __('Ultimate Multisite - Register Page', 'ultimate-multisite'),
			'login'          => __('Ultimate Multisite - Login Page', 'ultimate-multisite'),
			'block_frontend' => __('Ultimate Multisite - Site Blocked Page', 'ultimate-multisite'),
			'update'         => __('Ultimate Multisite - Membership Update Page', 'ultimate-multisite'),
			'new_site'       => __('Ultimate Multisite - New Site Page', 'ultimate-multisite'),
		];

		$pages = array_map('absint', $this->get_signup_pages());

		if (in_array($post->ID, $pages, true)) {
			$key = array_search($post->ID, $pages, true);

			$states['wp_ultimo_page'] = wu_get_isset($labels, $key);
		}

		return $states;
	}

	/**
	 * Renders the confirmation page.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Shortcode attributes.
	 * @param null|string $content The post content.
	 * @return string
	 */
	public function render_confirmation_page($atts, $content = null) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		return wu_get_template_contents(
			'checkout/confirmation',
			[
				'errors'     => Checkout::get_instance()->errors,
				'membership' => wu_get_membership_by_hash(wu_request('membership')),
			]
		);
	}
}
