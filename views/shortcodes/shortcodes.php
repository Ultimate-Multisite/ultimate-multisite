<?php
/**
 * Shortcodes view.
 *
 * @since 2.0.24
 */
defined('ABSPATH') || exit;
?>

<div id="wp-ultimo-wrap" class="<?php wu_wrap_use_container(); ?> wrap">

	<h1 class="wp-heading-inline"><?php esc_html_e('Available Shortcodes', 'ultimate-multisite'); ?></h1>

	<div id="poststuff">
	<div id="post-body" class="">
		<div id="post-body-content">

		<div class="postbox">
			<div class="inside">
				<h2><?php esc_html_e('Build a Frontend Account Area', 'ultimate-multisite'); ?></h2>
				<p><?php esc_html_e('Create a page on the main site and combine the account components you need. There is no single all-in-one account shortcode.', 'ultimate-multisite'); ?></p>
				<pre><code>[wu_account_summary]
[wu_current_membership]
[wu_billing_info]
[wu_payment_methods]
[wu_invoices]
[wu_current_site]
[wu_limits]
[wu_site_actions]</code></pre>
				<p><?php esc_html_e('Account Summary offers sign-in guidance and a site selector. Once a customer selects a site, it shows the plan, remaining trial time and disk usage; Current Site and Limits also need that site selection.', 'ultimate-multisite'); ?></p>
				<p><?php esc_html_e('To keep the membership Change link on the frontend, publish another main-site page with the shortcode below and select it under Settings > Memberships > Default Membership Update Page.', 'ultimate-multisite'); ?></p>
				<p><code>[wu_checkout slug="wu-checkout"]</code></p>
				<p><?php esc_html_e('These frontend shortcodes are not registered on customer-owned subsites. The subsite admin Account screen is an alternative way to manage the same membership; you do not need to duplicate the frontend account page there.', 'ultimate-multisite'); ?></p>
				<p><?php esc_html_e('Exclude account and checkout pages from full-page caching so customer-specific content is never served to another visitor.', 'ultimate-multisite'); ?></p>
			</div>
		</div>

		<?php foreach ($data as $shortcode) { ?>

			<div class="metabox-holder">
			<div class="postbox">
				<div class="wu-w-full wu-box-border wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid">

				<div class="wu-bg-gray-100 wu-py-4 wu-w-full wu-box-border wu-p-4 wu-py-5 wu-m-0 wu-border-b wu-border-l-0 wu-border-r-0 wu-border-t-0 wu-border-gray-300 wu-border-solid">
					<a  
					href="<?php echo esc_url($shortcode['generator_form_url']); ?>" 
					class="wu-float-right wubox wu-no-underline wu-text-gray-600"
					title="<?php esc_html_e('Generator', 'ultimate-multisite'); ?>"
					>
					<span class="dashicons-wu-rocket"></span>
					<?php esc_html_e('Generator', 'ultimate-multisite'); ?>
					</a>  
					<div class="wu-block">
					<h3 class="wu-my-1 wu-text-base wu-text-gray-800">
						<?php echo esc_html($shortcode['title']); ?> <code>[<?php echo esc_html($shortcode['shortcode']); ?>]</code>
					</h3>
					<p class="wu-mt-1 wu-mb-0 wu-text-gray-700">
						<?php echo esc_html($shortcode['description']); ?>
					</p>
					</div>
				</div>

				<div class="wu-w-full">
					<table class="wu-table-auto striped wu-w-full">
					<tr>
						<th class="wu-px-4 wu-py-2 wu-w-3/12 wu-text-left">
						<?php esc_html_e('Parameter', 'ultimate-multisite'); ?>
						</th>
						<th class="wu-px-4 wu-py-2 wu-w-4/12 wu-text-left">
						<?php esc_html_e('Description', 'ultimate-multisite'); ?>
						</th>
						<th class="wu-px-4 wu-py-2 wu-w-3/12 wu-text-left">
						<?php esc_html_e('Accepted Values', 'ultimate-multisite'); ?>
						</th>
						<th class="wu-px-4 wu-py-2 wu-w-2/12 wu-text-left">
						<?php esc_html_e('Default Value', 'ultimate-multisite'); ?>
						</th>
					</tr>
					<?php foreach ($shortcode['params'] as $param => $value) { ?>
						<tr>
						<td class="wu-px-4 wu-py-2 wu-text-left">
							<?php echo esc_html($param); ?>
						</td>
						<td class="wu-px-4 wu-py-2 wu-text-left">
							<?php echo esc_html($value['desc']); ?>
						</td>
						<td class="wu-px-4 wu-py-2 wu-text-left">
							<?php echo esc_html($value['options']); ?>
						</td>
						<td class="wu-px-4 wu-py-2 wu-text-left">
							<?php echo esc_html($value['default']); ?>
						</td>
						</tr>
					<?php } ?>
					</table>
				</div>

				</div>
			</div>
			</div>

		<?php } ?>

		</div>
	</div>
	</div>
</div>
