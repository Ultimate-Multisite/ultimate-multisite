<?php
/**
 * PayPal setup wizard — get credentials instructions.
 *
 * @since 2.6.0
 *
 * @var \WP_Ultimo\Admin_Pages\Wizard_Admin_Page $page
 * @var bool $sandbox_mode
 * @var string $developer_url
 */
defined('ABSPATH') || exit;

$mode_label = $sandbox_mode
	? __('Sandbox', 'ultimate-multisite')
	: __('Live', 'ultimate-multisite');
?>
<h1><?php esc_html_e('Get your PayPal API credentials', 'ultimate-multisite'); ?></h1>

<p class="wu-text-lg wu-text-gray-600 wu-my-4 wu-mb-6">
	<?php
	/* translators: %s: "Sandbox" or "Live" */
	printf(
		esc_html__('Follow these four steps to copy a Client ID and Secret out of the PayPal Developer Dashboard. You are setting up the %s environment.', 'ultimate-multisite'),
		'<strong>' . esc_html($mode_label) . '</strong>'
	);
	?>
</p>

<div class="wu-bg-blue-50 wu-border wu-border-blue-200 wu-rounded wu-p-4 wu-mb-6">
	<p class="wu-text-sm wu-text-blue-800 wu-m-0">
		<strong><?php esc_html_e('You will need a PayPal Business account.', 'ultimate-multisite'); ?></strong>
		<?php esc_html_e('Personal accounts cannot create REST apps. If you do not have a Business account yet, you can upgrade your existing PayPal account or create a new one for free.', 'ultimate-multisite'); ?>
	</p>
</div>

<ol class="wu-text-sm wu-list-decimal wu-pl-6 wu-space-y-4">

	<li>
		<strong><?php esc_html_e('Open the PayPal Developer Dashboard.', 'ultimate-multisite'); ?></strong>
		<p class="wu-mt-1 wu-mb-2">
			<?php
			printf(
				/* translators: %s: "Sandbox" or "Live" */
				esc_html__('Click the button below to open the %s applications page in a new tab. Sign in with your PayPal Business credentials when prompted.', 'ultimate-multisite'),
				esc_html($mode_label)
			);
			?>
		</p>
		<a href="<?php echo esc_url($developer_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
			<span class="dashicons dashicons-external" style="margin-top: 4px;"></span>
			<?php
			printf(
				/* translators: %s: "Sandbox" or "Live" */
				esc_html__('Open PayPal Developer (%s)', 'ultimate-multisite'),
				esc_html($mode_label)
			);
			?>
		</a>
	</li>

	<li>
		<strong><?php esc_html_e('Create a new REST API app.', 'ultimate-multisite'); ?></strong>
		<p class="wu-mt-1 wu-mb-0">
			<?php
			echo wp_kses(
				__('Click <strong>Create App</strong>, give it a name like <code>Ultimate Multisite</code>, and select <strong>Merchant</strong> as the app type. PayPal will generate a Client ID and Secret immediately.', 'ultimate-multisite'),
				[
					'strong' => [],
					'code'   => [],
				]
			);
			?>
		</p>
	</li>

	<li>
		<strong><?php esc_html_e('Copy the Client ID and Secret.', 'ultimate-multisite'); ?></strong>
		<p class="wu-mt-1 wu-mb-0">
			<?php
			echo wp_kses(
				__('On the app detail page, you will see <strong>Client ID</strong> at the top. Click <strong>Show</strong> next to <strong>Secret</strong> to reveal it. Copy both values — you will paste them into Ultimate Multisite on the next step.', 'ultimate-multisite'),
				[
					'strong' => [],
				]
			);
			?>
		</p>
	</li>

	<li>
		<strong><?php esc_html_e('Confirm the required features are enabled.', 'ultimate-multisite'); ?></strong>
		<p class="wu-mt-1 wu-mb-0">
			<?php
			echo wp_kses(
				__('Scroll down to <strong>Features</strong> on the same page. Make sure <strong>Accept payments</strong> is checked. <strong>Subscriptions</strong> should also be checked if you plan to sell recurring memberships. Save the change if you toggled anything.', 'ultimate-multisite'),
				[
					'strong' => [],
				]
			);
			?>
		</p>
	</li>

</ol>

<?php if ($sandbox_mode) : ?>
	<div class="wu-bg-yellow-50 wu-border wu-border-yellow-200 wu-rounded wu-p-4 wu-mt-6">
		<p class="wu-text-sm wu-text-yellow-800 wu-m-0">
			<strong><?php esc_html_e('Need a sandbox buyer account to test payments?', 'ultimate-multisite'); ?></strong>
			<?php
			echo wp_kses(
				__('PayPal automatically creates one when you generate sandbox credentials. Find it under <strong>Sandbox &rarr; Accounts</strong> on the same dashboard.', 'ultimate-multisite'),
				[
					'strong' => [],
				]
			);
			?>
		</p>
	</div>
<?php endif; ?>
