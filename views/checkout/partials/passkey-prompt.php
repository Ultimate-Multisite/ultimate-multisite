<?php
/**
 * Template File: Checkout passkey prompt
 *
 * Shown on the thank-you page to the signed-in customer who has no passkey
 * yet. Driven by assets/js/passwordless-auth.js ("stay" mode: no redirect).
 *
 * @since 2.19.0
 * @param string $email     The customer's email address.
 * @param string $login_url The login URL for this site.
 */
defined('ABSPATH') || exit;

?>
<div
	id="wu-checkout-passkey-prompt"
	class="wu-passwordless-auth wu-passwordless-checkout-prompt wu-mx-4 wu-mb-6 wu-p-4 wu-rounded wu-border wu-border-solid wu-border-blue-200 wu-bg-blue-50"
	data-success="stay"
>
	<div class="wu-passwordless-status wu-hidden wu-p-3 wu-rounded wu-mb-3" role="alert" aria-live="polite"></div>

	<div class="wu-passwordless-step" data-wu-step="enroll">
		<p class="wu-m-0 wu-font-semibold wu-text-blue-900">
			<?php esc_html_e('Secure your account with a passkey', 'ultimate-multisite'); ?>
		</p>
		<p class="wu-m-0 wu-mt-1 wu-mb-3 wu-text-sm wu-text-blue-900">
			<?php esc_html_e('Next time, sign in with your fingerprint, face, or device PIN. No password to remember.', 'ultimate-multisite'); ?>
		</p>
		<div class="wu-flex wu-flex-wrap wu-items-center wu-gap-3">
			<button type="button" class="button button-primary wu-passwordless-create-passkey">
				<?php esc_html_e('Create passkey', 'ultimate-multisite'); ?>
			</button>
			<button type="button" class="button-link wu-passwordless-skip-passkey">
				<?php esc_html_e('Not now', 'ultimate-multisite'); ?>
			</button>
		</div>
	</div>

	<p class="wu-m-0 wu-mt-3 wu-text-xs wu-text-gray-700">
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: 1: login URL, 2: email address. */
				esc_html__('You can always sign in at %1$s with a one-time code sent to %2$s.', 'ultimate-multisite'),
				'<a href="' . esc_url($login_url) . '">' . esc_html(wp_parse_url($login_url, PHP_URL_HOST)) . '</a>',
				'<strong>' . esc_html($email) . '</strong>'
			)
		);
		?>
	</p>
</div>
