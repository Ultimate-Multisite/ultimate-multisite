<?php
/**
 * Passwordless authentication manager.
 *
 * @package WP_Ultimo
 * @subpackage Auth
 * @since 2.13.2
 */

namespace WP_Ultimo\Auth;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Coordinates email-first login, passkeys, and OTP fallback.
 *
 * @since 2.13.2
 */
class Passwordless_Auth_Manager {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Passkey service.
	 *
	 * @since 2.13.2
	 * @var Passkey_Service
	 */
	protected $passkeys;

	/**
	 * OTP service.
	 *
	 * @since 2.13.2
	 * @var Email_OTP_Service
	 */
	protected $otp;

	/**
	 * Initializes hooks.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function init() {

		$this->passkeys = new Passkey_Service();
		$this->otp      = new Email_OTP_Service();

		if ( ! $this->is_enabled()) {
			return;
		}

		add_action('init', [$this, 'register_assets']);

		add_action('login_enqueue_scripts', [$this, 'enqueue_login_assets']);
		add_action('login_form', [$this, 'render_wp_login_form']);

		$this->register_ajax_hooks();
	}

	/**
	 * Returns the passkey service.
	 *
	 * @since 2.13.2
	 * @return Passkey_Service
	 */
	public function passkeys() {

		return $this->passkeys;
	}

	/**
	 * Returns the OTP service.
	 *
	 * @since 2.13.2
	 * @return Email_OTP_Service
	 */
	public function otp() {

		return $this->otp;
	}

	/**
	 * Checks if passwordless login is enabled.
	 *
	 * @since 2.13.2
	 * @return bool
	 */
	public function is_enabled() {

		return (bool) wu_get_setting('use_passwordless_login', 0);
	}

	/**
	 * Registers AJAX hooks on both WordPress AJAX and Ultimate Multisite light AJAX.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	protected function register_ajax_hooks() {

		$public_actions = [
			'wu_passwordless_start'          => 'ajax_start',
			'wu_passwordless_verify_otp'     => 'ajax_verify_otp',
			'wu_passwordless_verify_passkey' => 'ajax_verify_passkey',
		];

		$private_actions = [
			'wu_passwordless_register_options' => 'ajax_register_options',
			'wu_passwordless_register_verify'  => 'ajax_register_verify',
		];

		foreach ($public_actions as $action => $method) {
			add_action('wp_ajax_' . $action, [$this, $method]);
			add_action('wp_ajax_nopriv_' . $action, [$this, $method]);
			add_action('wu_ajax_' . $action, [$this, $method]);
			add_action('wu_ajax_nopriv_' . $action, [$this, $method]);
		}

		foreach ($private_actions as $action => $method) {
			add_action('wp_ajax_' . $action, [$this, $method]);
			add_action('wu_ajax_' . $action, [$this, $method]);
		}
	}

	/**
	 * Registers passwordless assets.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function register_assets() {

		$script_url = wu_url('assets/js/passwordless-auth.js');

		if (file_exists(wu_path('assets/js/passwordless-auth.min.js'))) {
			$script_url = wu_get_asset('passwordless-auth.js', 'js');
		}

		wp_register_script(
			'wu-passwordless-auth',
			$script_url,
			['jquery-core'],
			wu_get_version(),
			true
		);
	}

	/**
	 * Enqueues passwordless assets.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function enqueue_assets() {

		if ( ! $this->is_enabled()) {
			return;
		}

		if ( ! wp_script_is('wu-passwordless-auth', 'registered')) {
			$this->register_assets();
		}

		wp_localize_script(
			'wu-passwordless-auth',
			'wu_passwordless_auth',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('wu_passwordless_auth'),
				'i18n'     => [
					'email_required'      => __('Enter your email address to continue.', 'ultimate-multisite'),
					'code_required'       => __('Enter the 6-digit code from your email.', 'ultimate-multisite'),
					/* translators: %s: the email address or username the visitor entered. */
					'otp_intro'           => __('Check %s for your 6-digit login code.', 'ultimate-multisite'),
					'otp_intro_generic'   => __('Check your email for your 6-digit login code.', 'ultimate-multisite'),
					'code_resent'         => __("If an account matches, we've sent a new code. Check your inbox (and spam folder) for the newest email.", 'ultimate-multisite'),
					'passkey_starting'    => __('Follow your browser prompt to continue with your passkey.', 'ultimate-multisite'),
					'passkey_unavailable' => __('Passkeys are not available in this browser. We can email you a login code instead.', 'ultimate-multisite'),
					'sending_code'        => __('Sending login code...', 'ultimate-multisite'),
					'verifying_code'      => __('Verifying code...', 'ultimate-multisite'),
					'creating_passkey'    => __('Creating passkey...', 'ultimate-multisite'),
					'login_failed'        => __('Login failed. Please try again.', 'ultimate-multisite'),
					'passkey_created'     => __('Passkey created. Redirecting...', 'ultimate-multisite'),
					'redirecting'         => __('Login successful. Redirecting...', 'ultimate-multisite'),
				],
			]
		);

		if ( ! wp_style_is('wu-passwordless-auth', 'registered')) {
			wp_register_style('wu-passwordless-auth', false, [], wu_get_version());
			wp_add_inline_style('wu-passwordless-auth', $this->get_inline_css());
		}

		wp_enqueue_style('wu-passwordless-auth');
		wp_enqueue_script('wu-passwordless-auth');
	}

	/**
	 * Enqueues assets on wp-login.php and hides the legacy password form.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function enqueue_login_assets() {

		if ( ! $this->is_enabled()) {
			return;
		}

		$this->enqueue_assets();

		if ($this->is_password_fallback()) {
			return;
		}

		wp_add_inline_style('login', $this->get_login_inline_css());
	}

	/**
	 * Returns the styles shared by every passwordless surface.
	 *
	 * The Ultimate Multisite utility classes (wu-*) are not guaranteed to
	 * exist on the page (they are absent on wp-login.php and purged from some
	 * themes), so the status notices, link-style buttons and hidden states are
	 * styled here instead of relying on them.
	 *
	 * @since 2.18.1
	 * @return string
	 */
	protected function get_inline_css() {

		$rules = [
			'.wu-passwordless-auth [hidden] { display: none !important; }',
			'.wu-passwordless-auth .wu-passwordless-code { letter-spacing: 0.3em; text-align: center; font-variant-numeric: tabular-nums; }',
			'.wu-passwordless-auth .wu-passwordless-help { color: #50575e; font-size: 13px; }',
			'.wu-passwordless-auth .wu-passwordless-otp-actions { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 16px; margin-top: 16px; }',
			'.wu-passwordless-auth .button-link { display: inline; margin: 0; padding: 0; border: 0; background: none; box-shadow: none; color: #2271b1; font: inherit; text-decoration: underline; cursor: pointer; }',
			'.wu-passwordless-auth .button-link:hover, .wu-passwordless-auth .button-link:focus { color: #135e96; }',
			'.wu-passwordless-auth .wu-passwordless-status { margin: 0 0 16px; padding: 12px 14px; border-left: 4px solid #72aee6; border-radius: 2px; background: #f0f6fc; color: #1d2327; font-size: 14px; line-height: 1.5; word-wrap: break-word; }',
			'.wu-passwordless-auth .wu-passwordless-status[data-state="success"] { border-left-color: #00a32a; background: #edfaef; color: #1d2327; }',
			'.wu-passwordless-auth .wu-passwordless-status[data-state="error"] { border-left-color: #d63638; background: #fcf0f1; color: #8a2424; }',
		];

		return implode(' ', $rules);
	}

	/**
	 * Returns the wp-login.php specific styles for the passwordless UI.
	 *
	 * Hides the legacy password form and sizes the fields and buttons to match
	 * the WordPress login screen.
	 *
	 * @since 2.18.1
	 * @return string
	 */
	protected function get_login_inline_css() {

		$rules = [
			'body.login #loginform > p, body.login #loginform .user-pass-wrap, body.login #loginform .forgetmenot, body.login #loginform .submit { display: none; }',
			'body.login #loginform .wu-passwordless-auth { margin: 0 0 16px; }',
			'body.login #loginform .wu-passwordless-auth p { margin: 0 0 12px; }',
			'body.login .wu-passwordless-auth label { display: block; margin-bottom: 3px; font-size: 14px; }',
			'body.login .wu-passwordless-auth input.regular-text { width: 100%; box-sizing: border-box; margin: 0 0 6px; font-size: 24px; line-height: 1.33333333; min-height: 40px; }',
			'body.login .wu-passwordless-auth .button.button-primary { width: 100%; min-height: 40px; margin-top: 8px; float: none; }',
			'body.login .wu-passwordless-auth .wu-passwordless-otp-intro { font-size: 14px; font-weight: 600; }',
			'body.login .wu-passwordless-auth .wu-passwordless-otp-actions { margin-top: 16px; }',
			'body.login .wu-passwordless-auth .wu-passwordless-status { background: #fff; box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04); }',
			'body.login .wu-passwordless-auth .wu-passwordless-fallback { text-align: center; margin-top: 16px; }',
		];

		return implode(' ', $rules);
	}

	/**
	 * Renders the passwordless UI on wp-login.php.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function render_wp_login_form() {

		if ( ! $this->is_enabled()) {
			return;
		}

		$redirect_to = isset($_REQUEST['redirect_to']) ? sanitize_url(wp_unslash($_REQUEST['redirect_to'])) : admin_url(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ($this->is_password_fallback()) {
			// Give password users a way back to the email code login.
			printf(
				'<p class="wu-passwordless-switch" style="margin: 0 0 16px;"><a href="%s">%s</a></p>',
				esc_url(wp_login_url($redirect_to)),
				esc_html__('Email me a login code instead', 'ultimate-multisite')
			);

			return;
		}

		$fallback_url = add_query_arg('wu_password_fallback', '1', wp_login_url($redirect_to));

		$markup = $this->get_login_form_markup(
			[
				'context'       => 'wp-login',
				'redirect_to'   => $redirect_to,
				'redirect_type' => 'query_redirect',
				'fallback_url'  => $fallback_url,
			]
		);

		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Returns login form markup for custom login surfaces.
	 *
	 * @since 2.13.2
	 * @param array $args Markup arguments.
	 * @return string
	 */
	public function get_login_form_markup($args = []) {

		if ( ! $this->is_enabled()) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			[
				'context'          => 'login-form',
				'redirect_to'      => admin_url(),
				'redirect_type'    => 'default',
				'success'          => 'redirect',
				'show_email'       => true,
				'identifier_field' => '',
				'fallback_url'     => '',
			]
		);

		ob_start();

		?>
		<div
			class="wu-passwordless-auth wu-text-sm"
			data-context="<?php echo esc_attr($args['context']); ?>"
			data-redirect-to="<?php echo esc_attr($args['redirect_to']); ?>"
			data-redirect-type="<?php echo esc_attr($args['redirect_type']); ?>"
			data-success="<?php echo esc_attr($args['success']); ?>"
			data-identifier-field="<?php echo esc_attr($args['identifier_field']); ?>"
		>
			<div class="wu-passwordless-status wu-hidden wu-p-3 wu-rounded wu-mb-3" role="status" aria-live="polite" hidden></div>

			<div class="wu-passwordless-step" data-wu-step="email">
				<?php if ($args['show_email']) : ?>
					<label class="wu-block wu-font-medium wu-mb-1" for="wu-passwordless-email-<?php echo esc_attr($args['context']); ?>">
						<?php esc_html_e('Email address', 'ultimate-multisite'); ?>
					</label>
					<input
						type="email"
						class="wu-passwordless-email regular-text wu-w-full"
						id="wu-passwordless-email-<?php echo esc_attr($args['context']); ?>"
						autocomplete="username webauthn"
						placeholder="<?php esc_attr_e('you@example.com', 'ultimate-multisite'); ?>"
						aria-describedby="wu-passwordless-email-help-<?php echo esc_attr($args['context']); ?>"
					/>
					<p class="wu-passwordless-help wu-text-gray-600 wu-mt-2" id="wu-passwordless-email-help-<?php echo esc_attr($args['context']); ?>">
						<?php esc_html_e("We'll email you a 6-digit code to log in. No password needed.", 'ultimate-multisite'); ?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e('Continue securely with your passkey or an email login code.', 'ultimate-multisite'); ?></p>
				<?php endif; ?>

				<button type="button" class="button button-primary wu-passwordless-start wu-w-full wu-mt-3">
					<?php echo $args['show_email'] ? esc_html__('Email me a login code', 'ultimate-multisite') : esc_html__('Continue', 'ultimate-multisite'); ?>
				</button>
			</div>

			<div class="wu-passwordless-step wu-hidden" data-wu-step="passkey" hidden>
				<p><?php esc_html_e('Use your passkey to sign in to Ultimate Multisite.', 'ultimate-multisite'); ?></p>
				<button type="button" class="button button-primary wu-passwordless-passkey wu-w-full">
					<?php esc_html_e('Use passkey', 'ultimate-multisite'); ?>
				</button>
				<button type="button" class="button wu-passwordless-email-code wu-w-full wu-mt-2">
					<?php esc_html_e('Email me a code instead', 'ultimate-multisite'); ?>
				</button>
			</div>

			<div class="wu-passwordless-step wu-hidden" data-wu-step="otp" hidden>
				<p class="wu-passwordless-otp-intro wu-font-medium" id="wu-passwordless-otp-intro-<?php echo esc_attr($args['context']); ?>">
					<?php esc_html_e('Check your email for your 6-digit login code.', 'ultimate-multisite'); ?>
				</p>
				<label class="wu-block wu-font-medium wu-mb-1" for="wu-passwordless-code-<?php echo esc_attr($args['context']); ?>">
					<?php esc_html_e('Login code', 'ultimate-multisite'); ?>
				</label>
				<input
					type="text"
					inputmode="numeric"
					pattern="[0-9]*"
					maxlength="8"
					class="wu-passwordless-code regular-text wu-w-full"
					id="wu-passwordless-code-<?php echo esc_attr($args['context']); ?>"
					autocomplete="one-time-code"
					aria-describedby="wu-passwordless-otp-intro-<?php echo esc_attr($args['context']); ?> wu-passwordless-otp-help-<?php echo esc_attr($args['context']); ?>"
				/>
				<p class="wu-passwordless-help wu-text-gray-600 wu-mt-2" id="wu-passwordless-otp-help-<?php echo esc_attr($args['context']); ?>">
					<?php esc_html_e("Can't find it? Check your spam or junk folder, or send a new code.", 'ultimate-multisite'); ?>
				</p>
				<button type="button" class="button button-primary wu-passwordless-verify-code wu-w-full wu-mt-3">
					<?php esc_html_e('Verify code', 'ultimate-multisite'); ?>
				</button>
				<p class="wu-passwordless-otp-actions wu-mt-2">
					<button type="button" class="button-link wu-passwordless-resend">
						<?php esc_html_e('Send a new code', 'ultimate-multisite'); ?>
					</button>
					<?php if ($args['show_email']) : ?>
						<button type="button" class="button-link wu-passwordless-change-email">
							<?php esc_html_e('Use a different email', 'ultimate-multisite'); ?>
						</button>
					<?php endif; ?>
				</p>
			</div>

			<div class="wu-passwordless-step wu-hidden" data-wu-step="enroll" hidden>
				<p><?php esc_html_e('Make your next login faster by creating a passkey on this device.', 'ultimate-multisite'); ?></p>
				<button type="button" class="button button-primary wu-passwordless-create-passkey wu-w-full">
					<?php esc_html_e('Create passkey', 'ultimate-multisite'); ?>
				</button>
				<button type="button" class="button-link wu-passwordless-skip-passkey wu-mt-2">
					<?php esc_html_e('Skip for now', 'ultimate-multisite'); ?>
				</button>
			</div>

			<?php if ( ! empty($args['fallback_url'])) : ?>
				<p class="wu-passwordless-fallback wu-text-center wu-mt-3">
					<a href="<?php echo esc_url($args['fallback_url']); ?>"><?php esc_html_e('Use password instead', 'ultimate-multisite'); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Returns inline checkout login markup.
	 *
	 * @since 2.13.2
	 * @param string $field_type Field type.
	 * @return string
	 */
	public function get_inline_login_markup($field_type) {

		return $this->get_login_form_markup(
			[
				'context'          => 'checkout-' . sanitize_key($field_type),
				'redirect_to'      => wu_get_current_url(),
				'redirect_type'    => 'query_redirect',
				'success'          => 'reload',
				'show_email'       => false,
				'identifier_field' => 'email' === $field_type ? 'email_address' : 'username',
			]
		);
	}

	/**
	 * Starts passwordless login.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function ajax_start() {

		$this->verify_ajax_request();
		$this->ensure_enabled_for_ajax();

		$identifier = sanitize_text_field(wu_request('identifier'));
		$user       = $this->find_user($identifier);

		$email = $user ? $user->user_email : $identifier;
		$otp   = $this->otp->create_and_send($user, $email);

		if (is_wp_error($otp)) {
			$this->send_error($otp);
		}

		wp_send_json_success(
			[
				'mode'    => 'otp',
				'token'   => $otp['token'],
				'message' => $this->get_code_sent_message(),
			]
		);
	}

	/**
	 * Verifies OTP login.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function ajax_verify_otp() {

		$this->verify_ajax_request();
		$this->ensure_enabled_for_ajax();

		$user = $this->otp->verify(wu_request('token'), wu_request('code'));

		if (is_wp_error($user)) {
			$this->send_error($user);
		}

		$redirect_url = $this->login_user($user);
		$registration = null;

		update_user_meta($user->ID, '_wu_passwordless_last_otp_login', time());

		if ( ! $this->passkeys->credentials()->user_has_credentials($user->ID)) {
			$registration = $this->passkeys->get_registration_options($user);
		}

		wp_send_json_success(
			[
				'message'              => __('Login successful.', 'ultimate-multisite'),
				'nonce'                => wp_create_nonce('wu_passwordless_auth'),
				'redirect_url'         => $redirect_url,
				'registration_options' => $registration,
			]
		);
	}

	/**
	 * Verifies passkey login.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function ajax_verify_passkey() {

		$this->verify_ajax_request();
		$this->ensure_enabled_for_ajax();

		$credential = json_decode($this->get_json_request_value('credential'), true);

		if ( ! is_array($credential)) {
			$this->send_error(new \WP_Error('invalid_credential', __('Invalid passkey response.', 'ultimate-multisite')));
		}

		$user = $this->passkeys->verify_authentication($credential);

		if (is_wp_error($user)) {
			$this->send_error($user);
		}

		wp_send_json_success(
			[
				'message'      => __('Login successful.', 'ultimate-multisite'),
				'redirect_url' => $this->login_user($user),
			]
		);
	}

	/**
	 * Returns passkey registration options for the logged-in user.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function ajax_register_options() {

		$this->verify_ajax_request();
		$this->ensure_enabled_for_ajax();

		if ( ! is_user_logged_in()) {
			$this->send_error(new \WP_Error('not_logged_in', __('You need to be logged in to create a passkey.', 'ultimate-multisite')));
		}

		wp_send_json_success(
			[
				'options' => $this->passkeys->get_registration_options(wp_get_current_user()),
			]
		);
	}

	/**
	 * Verifies and stores a passkey registration.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	public function ajax_register_verify() {

		$this->verify_ajax_request();
		$this->ensure_enabled_for_ajax();

		if ( ! is_user_logged_in()) {
			$this->send_error(new \WP_Error('not_logged_in', __('You need to be logged in to create a passkey.', 'ultimate-multisite')));
		}

		$credential = json_decode($this->get_json_request_value('credential'), true);

		if ( ! is_array($credential)) {
			$this->send_error(new \WP_Error('invalid_credential', __('Invalid passkey response.', 'ultimate-multisite')));
		}

		$result = $this->passkeys->verify_registration(get_current_user_id(), $credential);

		if (is_wp_error($result)) {
			$this->send_error($result);
		}

		wp_send_json_success(
			[
				'message' => __('Passkey created.', 'ultimate-multisite'),
			]
		);
	}

	/**
	 * Returns the message shown after a login code request.
	 *
	 * The wording is identical whether or not an account matched, so it does
	 * not reveal which addresses are registered. It still tells the visitor
	 * where the code went, what to do next, and when it expires.
	 *
	 * @since 2.18.1
	 * @return string
	 */
	protected function get_code_sent_message() {

		$minutes = (int) (Email_OTP_Service::TTL / MINUTE_IN_SECONDS);

		return sprintf(
			/* translators: %d: number of minutes before the login code expires. */
			_n(
				"If an account matches, we've emailed a 6-digit login code to its email address. Check your inbox (and spam folder), then enter the code below. It expires in %d minute.",
				"If an account matches, we've emailed a 6-digit login code to its email address. Check your inbox (and spam folder), then enter the code below. It expires in %d minutes.",
				$minutes,
				'ultimate-multisite'
			),
			$minutes
		);
	}

	/**
	 * Verifies the passwordless AJAX nonce.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	protected function verify_ajax_request() {

		check_ajax_referer('wu_passwordless_auth', 'nonce');
	}

	/**
	 * Sends an error response when passwordless login is disabled.
	 *
	 * @since 2.13.2
	 * @return void
	 */
	protected function ensure_enabled_for_ajax() {

		if ($this->is_enabled()) {
			return;
		}

		$this->send_error(new \WP_Error('passwordless_login_disabled', __('Passwordless login is disabled.', 'ultimate-multisite')));
	}

	/**
	 * Finds a user by email or username.
	 *
	 * @since 2.13.2
	 * @param string $identifier Email address or username.
	 * @return \WP_User|false
	 */
	protected function find_user($identifier) {

		$identifier = trim((string) $identifier);

		if (empty($identifier)) {
			return false;
		}

		if (is_email($identifier)) {
			return get_user_by('email', sanitize_email($identifier));
		}

		return get_user_by('login', sanitize_user($identifier));
	}

	/**
	 * Returns an unslashed JSON request value.
	 *
	 * Passkey browser responses are JSON payloads that contain base64url-encoded
	 * binary values. Sanitizing the raw JSON string before decoding can corrupt
	 * the credential response, so individual decoded fields are validated by the
	 * passkey service instead.
	 *
	 * @since 2.13.2
	 * @param string $key Request key.
	 * @return string
	 */
	protected function get_json_request_value($key) {

		if ( ! isset($_POST[ $key ])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}

		return wp_unslash($_POST[ $key ]); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Logs in a user and returns the redirect URL.
	 *
	 * @since 2.13.2
	 * @param \WP_User $user User object.
	 * @return string
	 */
	protected function login_user(\WP_User $user) {

		$remember      = (bool) wu_request('remember');
		$requested     = sanitize_url(wu_request('redirect_to', admin_url()));
		$redirect_type = sanitize_key(wu_request('redirect_type', 'default'));
		$redirect_to   = $this->resolve_redirect($user, $requested, $redirect_type);

		wp_clear_auth_cookie();
		wp_set_current_user($user->ID);
		wp_set_auth_cookie($user->ID, $remember, is_ssl());
		do_action('wp_login', $user->user_login, $user);

		$previous_redirect_type                  = isset($_REQUEST['wu_login_form_redirect_type']) ? sanitize_key(wp_unslash($_REQUEST['wu_login_form_redirect_type'])) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_REQUEST['wu_login_form_redirect_type'] = 'query_redirect';

		$redirect_to = apply_filters('login_redirect', $redirect_to, $redirect_to, $user);

		if (null === $previous_redirect_type) {
			unset($_REQUEST['wu_login_form_redirect_type']);
		} else {
			$_REQUEST['wu_login_form_redirect_type'] = $previous_redirect_type;
		}

		return $redirect_to;
	}

	/**
	 * Resolves custom login form redirect targets.
	 *
	 * @since 2.13.2
	 * @param \WP_User $user          User object.
	 * @param string   $requested     Requested redirect.
	 * @param string   $redirect_type Redirect type.
	 * @return string
	 */
	protected function resolve_redirect(\WP_User $user, $requested, $redirect_type) {

		if ('customer_site' === $redirect_type) {
			$site = get_active_blog_for_user($user->ID);

			if ($site && ! empty($site->siteurl)) {
				return trailingslashit($site->siteurl) . ltrim($requested ?: 'wp-admin', '/');
			}
		}

		if ('main_site' === $redirect_type) {
			return home_url($requested ?: '/wp-admin');
		}

		return wp_validate_redirect($requested, admin_url());
	}

	/**
	 * Sends a normalized JSON error response.
	 *
	 * @since 2.13.2
	 * @param \WP_Error $error Error object.
	 * @return void
	 */
	protected function send_error(\WP_Error $error) {

		wp_send_json_error(
			[
				'code'    => $error->get_error_code(),
				'message' => wp_strip_all_tags($error->get_error_message()),
			]
		);
	}

	/**
	 * Checks if default password fallback is explicitly requested.
	 *
	 * @since 2.13.2
	 * @return bool
	 */
	public function is_password_fallback() {

		return isset($_GET['wu_password_fallback']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
