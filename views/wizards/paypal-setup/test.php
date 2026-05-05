<?php
/**
 * PayPal setup wizard — test connection step.
 *
 * Mirrors the host-integration test view shape so the same Vue/JS
 * conventions apply. The companion script lives at
 * assets/js/paypal-setup-wizard.js and is enqueued by the page class.
 *
 * @since 2.6.0
 *
 * @var \WP_Ultimo\Admin_Pages\Wizard_Admin_Page $page
 * @var bool $sandbox_mode
 */
defined('ABSPATH') || exit;
?>
<h1><?php esc_html_e('Verifying your credentials with PayPal', 'ultimate-multisite'); ?></h1>

<p class="wu-text-lg wu-text-gray-600 wu-my-4 wu-mb-6">
	<?php esc_html_e('We are sending a test request to PayPal using the credentials you saved. This confirms PayPal accepts them and gives us a chance to install the webhook automatically.', 'ultimate-multisite'); ?>
</p>

<div id="wu-paypal-setup-wizard-test">

	<div v-if="loading" class="wu-flex wu-rounded wu-content-center wu-py-3 wu-px-4 wu-bg-blue-50 wu-border wu-border-solid wu-border-blue-200 wu-m-0 wu-mb-4">
		<span class="dashicons dashicons-update wu-text-blue-500 wu-self-center wu-mr-2 wu-animate-spin"></span>
		<span><?php esc_html_e('Verifying your credentials with PayPal…', 'ultimate-multisite'); ?></span>
	</div>

	<div v-cloak v-if="!loading && success" class="wu-flex wu-rounded wu-content-center wu-py-3 wu-px-4 wu-bg-green-50 wu-border wu-border-solid wu-border-green-200 wu-m-0 wu-mb-4">
		<span class="dashicons dashicons-yes-alt wu-text-green-500 wu-self-center wu-mr-2"></span>
		<span>
			<?php esc_html_e('Your credentials were accepted by PayPal.', 'ultimate-multisite'); ?>
			<span v-if="webhookStatus === 'installed'" class="wu-text-green-700 wu-ml-1">
				<?php esc_html_e('Webhook installed automatically.', 'ultimate-multisite'); ?>
			</span>
			<span v-if="webhookStatus === 'failed'" class="wu-text-yellow-700 wu-ml-1">
				<?php esc_html_e('Webhook auto-install did not complete — you can retry it from the Payments settings.', 'ultimate-multisite'); ?>
			</span>
		</span>
	</div>

	<div v-cloak v-if="!loading && !success" class="wu-flex wu-rounded wu-content-center wu-py-3 wu-px-4 wu-bg-red-50 wu-border wu-border-solid wu-border-red-200 wu-m-0 wu-mb-4">
		<span class="dashicons dashicons-dismiss wu-text-red-500 wu-self-center wu-mr-2"></span>
		<span>{{ errorMessage }}</span>
	</div>

	<details v-cloak v-if="!loading" class="wu-mb-4">
		<summary class="wu-cursor-pointer wu-text-sm wu-text-gray-600">
			<?php esc_html_e('Show response details', 'ultimate-multisite'); ?>
		</summary>
		<pre class="wu-overflow-auto wu-p-3 wu-mt-2 wu-rounded wu-bg-gray-800 wu-text-white wu-text-xs wu-font-mono">{{ rawResponse }}</pre>
	</details>

	<div v-cloak v-if="!loading && !success">
		<h2 class="wu-text-base wu-mt-6 wu-mb-2"><?php esc_html_e('Troubleshooting', 'ultimate-multisite'); ?></h2>
		<ol class="wu-text-sm wu-list-decimal wu-pl-6 wu-space-y-1">
			<li><?php esc_html_e('Go back to the previous step and re-paste the Client ID and Secret. PayPal sometimes adds whitespace when copying — make sure both fields have no leading or trailing spaces.', 'ultimate-multisite'); ?></li>
			<li>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: "Sandbox" or "Live" */
						__('Confirm you copied <strong>%s</strong> credentials, not the other mode. Sandbox keys do not work against the live API and vice versa.', 'ultimate-multisite'),
						$sandbox_mode ? __('Sandbox', 'ultimate-multisite') : __('Live', 'ultimate-multisite')
					),
					[
						'strong' => [],
					]
				);
				?>
			</li>
			<li><?php esc_html_e('Confirm “Accept payments” is enabled under the Features section of your PayPal app.', 'ultimate-multisite'); ?></li>
			<li><?php esc_html_e('If your server cannot make outbound HTTPS requests, the test will time out. Ask your host to allow connections to api-m.paypal.com and api-m.sandbox.paypal.com.', 'ultimate-multisite'); ?></li>
		</ol>
	</div>

	<!-- Submit Box -->
	<div class="wu-flex wu-justify-between wu-bg-gray-100 wu--m-in wu-mt-4 wu-p-4 wu-overflow-hidden wu-border-t wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300">

		<a href="<?php echo esc_url($page->get_prev_section_link()); ?>" class="wu-self-center button button-large wu-float-left">
			<?php esc_html_e('&larr; Go Back', 'ultimate-multisite'); ?>
		</a>

		<span class="wu-self-center">
			<button v-cloak v-if="!loading && !success" type="button" class="button button-large" @click="runTest" data-testid="paypal-test-retry">
				<?php esc_html_e('Retry', 'ultimate-multisite'); ?>
			</button>
			<a
				v-cloak v-if="!loading && success"
				href="<?php echo esc_url($page->get_next_section_link()); ?>"
				class="button button-primary button-large"
				data-testid="paypal-test-continue"
			>
				<?php esc_html_e('Continue &rarr;', 'ultimate-multisite'); ?>
			</a>
		</span>

	</div>
	<!-- End Submit Box -->

</div>
