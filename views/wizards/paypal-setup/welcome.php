<?php
/**
 * PayPal setup wizard — welcome step.
 *
 * @since 2.6.0
 *
 * @var \WP_Ultimo\Admin_Pages\Wizard_Admin_Page $page
 * @var bool $sandbox_mode
 */
defined('ABSPATH') || exit;
?>
<h1><?php esc_html_e('Set up PayPal', 'ultimate-multisite'); ?></h1>

<p class="wu-text-lg wu-text-gray-600 wu-my-4 wu-mb-6">
	<?php esc_html_e('This wizard walks you through connecting Ultimate Multisite to your PayPal Business account in three short steps. We will give you the exact links to copy your API credentials, then verify the connection automatically.', 'ultimate-multisite'); ?>
</p>

<div class="wu-bg-white wu-p-4 wu--mx-6">

	<div class="wu-flex wu-justify-center">

		<div class="wu-flex wu-content-center">
			<img style="width: 150px;" class="wu-self-center" src="<?php echo esc_url(wu_get_network_logo()); ?>" alt="">
		</div>

		<div class="wu-text-2xl wu-self-center wu-m-8">&rarr;</div>

		<div class="wu-flex wu-content-center">
			<img style="width: 150px;" class="wu-self-center" src="<?php echo esc_url('https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-200px.png'); ?>" alt="PayPal">
		</div>

	</div>

	<div>
		<span class="wu-text-sm wu-text-gray-800 wu-inline-block wu-py-4">
			<?php esc_html_e('This integration will:', 'ultimate-multisite'); ?>
		</span>

		<ul class="wu--mx-5 wu-my-0 wu-border-t wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300">
			<li class="wu-flex wu-content-center wu-py-2 wu-px-4 wu-bg-gray-100 wu-border-t-0 wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b wu-border-gray-300 wu-m-0">
				<span class="dashicons dashicons-yes-alt wu-text-green-400 wu-self-center wu-mr-2"></span>
				<span><?php esc_html_e('Accept one-time and recurring payments via PayPal Checkout.', 'ultimate-multisite'); ?></span>
			</li>
			<li class="wu-flex wu-content-center wu-py-2 wu-px-4 wu-bg-gray-100 wu-border-t-0 wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b wu-border-gray-300 wu-m-0">
				<span class="dashicons dashicons-yes-alt wu-text-green-400 wu-self-center wu-mr-2"></span>
				<span><?php esc_html_e('Auto-install the webhook so payment status updates flow back into Ultimate Multisite.', 'ultimate-multisite'); ?></span>
			</li>
			<li class="wu-flex wu-content-center wu-py-2 wu-px-4 wu-bg-gray-100 wu-border-t-0 wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b wu-border-gray-300 wu-m-0">
				<span class="dashicons dashicons-yes-alt wu-text-green-400 wu-self-center wu-mr-2"></span>
				<span><?php esc_html_e('Verify the credentials with PayPal before completing setup.', 'ultimate-multisite'); ?></span>
			</li>
		</ul>
	</div>

	<div class="wu-mt-6">
		<label class="wu-flex wu-items-center wu-cursor-pointer">
			<input
				type="checkbox"
				name="paypal_sandbox_mode"
				value="1"
				<?php checked($sandbox_mode, true); ?>
				class="wu-mr-2"
			>
			<span class="wu-text-sm">
				<strong><?php esc_html_e('Use sandbox (test) mode', 'ultimate-multisite'); ?></strong>
				—
				<?php esc_html_e('Recommended for the first run. Sandbox uses fake money so you can confirm the flow before going live.', 'ultimate-multisite'); ?>
			</span>
		</label>
	</div>

	<p class="wu-text-xs wu-text-gray-600 wu-mt-3 wu-mb-0">
		<?php esc_html_e('You can switch between sandbox and live later from the Payments settings. Each mode has its own pair of API credentials.', 'ultimate-multisite'); ?>
	</p>

</div>

<!-- Submit Box -->
<div class="wu-flex wu-justify-between wu-bg-gray-100 wu--m-in wu-mt-4 wu-p-4 wu-overflow-hidden wu-border-t wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300">

	<a href="<?php echo esc_url(wu_network_admin_url('wp-ultimo-settings', ['tab' => 'payment-gateways'])); ?>" class="wu-self-center button button-large wu-float-left">
		<?php esc_html_e('&larr; Cancel', 'ultimate-multisite'); ?>
	</a>

	<span class="wu-self-center">
		<button name="saving_welcome" value="1" class="button button-primary button-large" data-testid="button-primary">
			<?php esc_html_e('Continue &rarr;', 'ultimate-multisite'); ?>
		</button>
	</span>

</div>
<!-- End Submit Box -->
