<?php
/**
 * Template File: Inline Login Prompt
 *
 * Displays an inline login prompt when a user enters an existing email/username.
 *
 * @since 2.0.20
 * @param string $field_type The field type ('email' or 'username').
 */
defined('ABSPATH') || exit;

$passwordless_auth = \WP_Ultimo\Auth\Passwordless_Auth_Manager::get_instance();

?>

<div id="wu-inline-login-prompt-<?php echo esc_attr($field_type); ?>" class="wu-bg-blue-50 wu-border wu-border-blue-200 wu-rounded wu-p-4 wu-mt-2 wu-mb-4">
	<div class="wu-mb-3">
		<p class="wu-m-0 wu-font-semibold wu-text-blue-900 wu-text-sm">
			<?php esc_html_e('Already have an account?', 'ultimate-multisite'); ?>
		</p>
		<?php if ($passwordless_auth->is_enabled()) : ?>
			<p class="wu-m-0 wu-mt-1 wu-text-blue-900 wu-text-sm">
				<?php esc_html_e('Continue with a passkey or a one-time email code. No password is required.', 'ultimate-multisite'); ?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ($passwordless_auth->is_enabled()) : ?>
		<?php echo $passwordless_auth->get_inline_login_markup($field_type); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<div class="wu-mb-3">
			<label for="wu-inline-login-password-<?php echo esc_attr($field_type); ?>" class="wu-block wu-text-sm wu-font-medium wu-text-gray-700 wu-mb-1">
				<?php esc_html_e('Password', 'ultimate-multisite'); ?>
			</label>
			<input
				type="password"
				id="wu-inline-login-password-<?php echo esc_attr($field_type); ?>"
				class="form-control wu-w-full"
				autocomplete="current-password"
				placeholder="<?php esc_attr_e('Enter your password', 'ultimate-multisite'); ?>"
			/>
		</div>

		<div id="wu-login-error-<?php echo esc_attr($field_type); ?>" class="wu-bg-red-100 wu-text-red-800 wu-p-3 wu-rounded wu-text-sm wu-mb-3 wu-hidden">
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Fires inside the inline login prompt.
	 *
	 * Useful for adding captcha widgets or additional fields.
	 *
	 * @since 2.5.0
	 *
	 * @param string $field_type The field type ('email' or 'username').
	 */
	do_action('wu_inline_login_prompt_before_submit', $field_type);
	?>

	<?php if ( ! $passwordless_auth->is_enabled()) : ?>
		<div class="wu-flex wu-flex-wrap wu-items-center wu-justify-between wu-gap-2">
			<a
				href="<?php echo esc_url(wp_lostpassword_url(wu_get_current_url())); ?>"
				class="wu-text-sm wu-text-blue-600 hover:wu-text-blue-800 wu-no-underline"
				target="_blank"
			>
				<?php esc_html_e('Forgot password?', 'ultimate-multisite'); ?>
			</a>

			<button
				type="button"
				id="wu-inline-login-submit-<?php echo esc_attr($field_type); ?>"
				class="wu-bg-blue-600 wu-text-white wu-px-4 wu-py-2 wu-rounded hover:wu-bg-blue-700 disabled:wu-opacity-50 disabled:wu-cursor-not-allowed wu-border-0 wu-text-sm wu-font-medium wu-cursor-pointer"
			>
				<?php esc_html_e('Sign in', 'ultimate-multisite'); ?>
			</button>
		</div>
	<?php endif; ?>
</div>
