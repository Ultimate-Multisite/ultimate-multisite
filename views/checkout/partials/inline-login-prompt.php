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

?>

<div id="wu-inline-login-prompt-<?php echo esc_attr($field_type); ?>" class="wu-bg-blue-50 wu-border wu-border-blue-200 wu-rounded wu-p-4 wu-mt-2 wu-mb-4">
	<div class="wu-mb-3">
		<p class="wu-m-0 wu-font-semibold wu-text-blue-900 wu-text-sm">
			<?php esc_html_e('Already have an account?', 'ultimate-multisite'); ?>
		</p>
		<p class="wu-m-0 wu-mt-1 wu-text-blue-900 wu-text-sm">
			<?php esc_html_e('Continue with a passkey or a one-time email code. No password is required.', 'ultimate-multisite'); ?>
		</p>
	</div>

	<?php echo \WP_Ultimo\Auth\Passwordless_Auth_Manager::get_instance()->get_inline_login_markup($field_type); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php
	/**
	 * Fires inside the inline passwordless login prompt.
	 *
	 * Useful for adding captcha widgets or additional fields.
	 *
	 * @since 2.5.0
	 *
	 * @param string $field_type The field type ('email' or 'username').
	 */
	do_action('wu_inline_login_prompt_before_submit', $field_type);
	?>
</div>
