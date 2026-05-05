<?php
/**
 * PayPal setup wizard — configure (credential paste) step.
 *
 * @since 2.6.0
 *
 * @var \WP_Ultimo\Admin_Pages\Wizard_Admin_Page $page
 * @var bool $sandbox_mode
 * @var string $mode_label
 * @var string $client_id
 * @var string $client_secret
 */
defined('ABSPATH') || exit;

$notice = get_site_transient('wu_paypal_wizard_notice');

if ($notice) {
	delete_site_transient('wu_paypal_wizard_notice');
}
?>
<h1><?php esc_html_e('Paste your credentials', 'ultimate-multisite'); ?></h1>

<p class="wu-text-lg wu-text-gray-600 wu-my-4 wu-mb-6">
	<?php
	printf(
		/* translators: %s: "Sandbox" or "Live" */
		esc_html__('Paste the %s Client ID and Secret you copied from the PayPal Developer Dashboard. We will keep them safe and verify them with PayPal on the next step.', 'ultimate-multisite'),
		'<strong>' . esc_html($mode_label) . '</strong>'
	);
	?>
</p>

<?php if ($notice && 'error' === ($notice['type'] ?? '')) : ?>
	<div class="wu-bg-red-50 wu-border wu-border-red-200 wu-rounded wu-p-4 wu-mb-4">
		<p class="wu-text-sm wu-text-red-800 wu-m-0"><?php echo esc_html($notice['message']); ?></p>
	</div>
<?php endif; ?>

<div class="wu-bg-white wu-p-4 wu--mx-6">

	<div class="wu-mb-4">
		<label for="paypal_client_id" class="wu-block wu-text-sm wu-font-semibold wu-mb-1">
			<?php
			printf(
				/* translators: %s: "Sandbox" or "Live" */
				esc_html__('%s Client ID', 'ultimate-multisite'),
				esc_html($mode_label)
			);
			?>
		</label>
		<input
			type="text"
			id="paypal_client_id"
			name="paypal_client_id"
			value="<?php echo esc_attr($client_id); ?>"
			placeholder="<?php echo esc_attr__('e.g. AX7MV-tjPVrGHM3...', 'ultimate-multisite'); ?>"
			class="regular-text wu-w-full"
			autocomplete="off"
			spellcheck="false"
			required
			data-testid="paypal-client-id"
		>
		<p class="wu-text-xs wu-text-gray-500 wu-mt-1 wu-mb-0">
			<?php esc_html_e('Starts with “A”. Visible at the top of your PayPal app detail page.', 'ultimate-multisite'); ?>
		</p>
	</div>

	<div class="wu-mb-4">
		<label for="paypal_client_secret" class="wu-block wu-text-sm wu-font-semibold wu-mb-1">
			<?php
			printf(
				/* translators: %s: "Sandbox" or "Live" */
				esc_html__('%s Client Secret', 'ultimate-multisite'),
				esc_html($mode_label)
			);
			?>
		</label>
		<input
			type="password"
			id="paypal_client_secret"
			name="paypal_client_secret"
			value="<?php echo esc_attr($client_secret); ?>"
			placeholder="<?php echo esc_attr__('e.g. EK4jT-_NA12...', 'ultimate-multisite'); ?>"
			class="regular-text wu-w-full"
			autocomplete="off"
			spellcheck="false"
			required
			data-testid="paypal-client-secret"
		>
		<p class="wu-text-xs wu-text-gray-500 wu-mt-1 wu-mb-0">
			<?php esc_html_e('Click “Show” next to Secret on the app detail page to reveal it before copying.', 'ultimate-multisite'); ?>
		</p>
	</div>

	<p class="wu-text-xs wu-text-gray-600 wu-mt-4 wu-mb-0">
		<?php
		printf(
			/* translators: %s: "Sandbox" or "Live" */
			esc_html__('These values are stored as %s credentials. Switching to the other mode later will not overwrite them.', 'ultimate-multisite'),
			esc_html($mode_label)
		);
		?>
	</p>

</div>

<!-- Submit Box -->
<div class="wu-flex wu-justify-between wu-bg-gray-100 wu--m-in wu-mt-4 wu-p-4 wu-overflow-hidden wu-border-t wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300">

	<a href="<?php echo esc_url($page->get_prev_section_link()); ?>" class="wu-self-center button button-large wu-float-left">
		<?php esc_html_e('&larr; Go Back', 'ultimate-multisite'); ?>
	</a>

	<span class="wu-self-center">
		<button name="saving_configure" value="1" class="button button-primary button-large" data-testid="button-primary">
			<?php esc_html_e('Save and test &rarr;', 'ultimate-multisite'); ?>
		</button>
	</span>

</div>
<!-- End Submit Box -->
