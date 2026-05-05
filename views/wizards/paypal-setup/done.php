<?php
/**
 * PayPal setup wizard — done step.
 *
 * @since 2.6.0
 *
 * @var \WP_Ultimo\Admin_Pages\Wizard_Admin_Page $page
 * @var bool $sandbox_mode
 */
defined('ABSPATH') || exit;
?>
<div class="wu-bg-white wu-p-4 wu--mx-6 wu-flex wu-content-center" style="height: 250px;">
	<div class="wu-self-center wu-text-center wu-w-full">
		<span class="dashicons dashicons-yes-alt wu-text-green-400 wu-w-auto wu-h-auto wu-text-5xl wu-mb-2"></span>
		<h1>
			<?php esc_html_e('PayPal is ready to take payments!', 'ultimate-multisite'); ?>
		</h1>
		<p class="wu-text-lg wu-text-gray-600 wu-my-4">
			<?php
			if ($sandbox_mode) {
				esc_html_e('Sandbox mode is active. Run a test checkout end-to-end, then come back here to switch to live credentials when you are ready.', 'ultimate-multisite');
			} else {
				esc_html_e('Live mode is active. PayPal will accept real payments from your customers from this moment.', 'ultimate-multisite');
			}
			?>
		</p>
	</div>
</div>

<!-- Submit Box -->
<div class="wu-bg-gray-100 wu--m-in wu-mt-4 wu-p-4 wu-overflow-hidden wu-border-t wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300">

	<span class="wu-float-left">
		<a href="<?php echo esc_url(wu_network_admin_url('wp-ultimo-settings', ['tab' => 'payment-gateways'])); ?>" class="button button-large">
			<?php esc_html_e('Open Payments settings', 'ultimate-multisite'); ?>
		</a>
	</span>

	<span class="wu-float-right">
		<a href="<?php echo esc_url(wu_network_admin_url('wp-ultimo-settings', ['tab' => 'payment-gateways'])); ?>" class="button button-primary button-large" data-testid="button-primary">
			<?php esc_html_e('Finish!', 'ultimate-multisite'); ?>
		</a>
	</span>

</div>
<!-- End Submit Box -->
