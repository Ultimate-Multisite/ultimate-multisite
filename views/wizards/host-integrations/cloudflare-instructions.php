<?php
/**
 * Runcloud instructions view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<h1>
<?php esc_html_e('Instructions', 'ultimate-multisite'); ?></h1>

<p class="wu-text-lg wu-text-gray-600 wu-my-4 wu-mb-6">

	<?php esc_html_e('You’ll need to get your', 'ultimate-multisite'); ?> <strong><?php esc_html_e('API Key', 'ultimate-multisite'); ?></strong> <?php esc_html_e('and', 'ultimate-multisite'); ?> <strong><?php esc_html_e('Zone ID', 'ultimate-multisite'); ?></strong> <?php esc_html_e('for your Cloudflare DNS zone.', 'ultimate-multisite'); ?>

</p>

<p class="wu-text-sm wu-bg-blue-100 wu-p-4 wu-text-blue-600 wu-rounded">
	<strong><?php esc_html_e('Before we start...', 'ultimate-multisite'); ?></strong><br>
	<?php // translators: %s the url ?>
	<?php echo wp_kses_post(sprintf(__('This integration is really aimed at people that do not have access to an Enterprise Cloudflare account, since that particular tier supports proxying on wildcard DNS entries, which makes adding each subdomain unecessary. If you own an enterprise tier account, you can simply follow <a class="wu-no-underline" href="%s" target="_blank">this tutorial</a> to create the wildcard entry and deactivate this integration entirely.', 'ultimate-multisite'), 'https://support.cloudflare.com/hc/en-us/articles/200169356-How-do-I-use-WordPress-Multi-Site-WPMU-With-Cloudflare')); ?>
</p>

<h3 class="wu-m-0 wu-py-4 wu-text-lg" id="step-1-getting-the-api-key-and-secret">
	<?php esc_html_e('Getting the Zone ID and API Key', 'ultimate-multisite'); ?>
</h3>

<p class="wu-text-sm">
	<?php esc_html_e('On the Cloudflare overview page of your Zone (the domain managed), you\'ll see a block on the sidebar containing the Zone ID. Copy that value.', 'ultimate-multisite'); ?>
</p>

<p class="wu-text-center"><i><?php esc_html_e('DNS Zone ID on the Sidebar', 'ultimate-multisite'); ?></i></p>

<p class="wu-text-sm"><?php esc_html_e('On that same sidebar block, you will see the Get your API token link. Click on it to go to the token generation screen.', 'ultimate-multisite'); ?></p>

<p class="wu-text-center"><i><?php esc_html_e('Go to the API Tokens tab, then click on Create Token', 'ultimate-multisite'); ?></i></p>

<p class="wu-text-sm"><?php esc_html_e('We want an API token that will allow us to edit DNS records, so select the Edit zone DNS template.', 'ultimate-multisite'); ?></p>

<p class="wu-text-center"><i><?php esc_html_e('Use the Edit Zone DNS template', 'ultimate-multisite'); ?></i></p>

<p class="wu-text-sm"><?php esc_html_e('On the next screen, set the permissions to Edit, and select the zone that corresponds to your target domain. Then, move to the next step.', 'ultimate-multisite'); ?></p>

<p class="wu-text-center"><i><?php esc_html_e('Permission and Zone Settings', 'ultimate-multisite'); ?></i></p>

<p class="wu-text-sm"><?php esc_html_e('Finally, click Create Token.', 'ultimate-multisite'); ?></p>

<p class="wu-text-center"><i><?php esc_html_e('Finishing up.', 'ultimate-multisite'); ?></i></p>

<p class="wu-text-sm"><?php esc_html_e('Copy the API Token (it won\'t be shown again, so you need to copy it now!). We will use it on the next step alongside with the Zone ID', 'ultimate-multisite'); ?></p>

<p class="wu-text-center"><i><?php esc_html_e('Done!', 'ultimate-multisite'); ?></i></p>

<hr class="wu-my-6">

<h3 class="wu-m-0 wu-py-4 wu-text-lg" id="cloudflare-saas-custom-hostnames">
	<?php esc_html_e('Cloudflare Custom Hostnames — Automatic SSL for Mapped Domains (Optional)', 'ultimate-multisite'); ?>
</h3>

<p class="wu-text-sm wu-bg-green-100 wu-p-4 wu-text-green-700 wu-rounded">
	<strong><?php esc_html_e('What is this?', 'ultimate-multisite'); ?></strong><br>
	<?php esc_html_e('Cloudflare Custom Hostnames let you issue SSL certificates for custom domains mapped to your multisite — without requiring each customer to add your IP to their DNS. When a new domain is added to a subsite, Ultimate Multisite will automatically register it as a Custom Hostname in your Cloudflare zone so Cloudflare can provision the SSL certificate.', 'ultimate-multisite'); ?>
</p>

<p class="wu-text-sm">
	<?php esc_html_e('To enable this feature, you need a Cloudflare zone configured as a SaaS provider zone. This is separate from your main DNS zone. Follow these steps:', 'ultimate-multisite'); ?>
</p>

<ol class="wu-text-sm wu-list-decimal wu-pl-6 wu-space-y-2">
	<li><?php esc_html_e('In your Cloudflare dashboard, open the zone you want to use for Custom Hostnames (this is typically your main domain zone).', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Go to SSL/TLS → Custom Hostnames and enable Cloudflare for SaaS for that zone.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Copy the Zone ID of that zone from the Overview sidebar — this is your Custom Hostnames Zone ID.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Ensure your API token has the "SSL and Certificates: Edit" permission in addition to "DNS: Edit" for the Custom Hostnames zone.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Paste the Zone ID into the Custom Hostnames Zone ID field on the next step.', 'ultimate-multisite'); ?></li>
</ol>

<p class="wu-text-sm wu-bg-yellow-100 wu-p-4 wu-text-yellow-700 wu-rounded wu-mt-4">
	<strong><?php esc_html_e('Customer DNS requirement:', 'ultimate-multisite'); ?></strong><br>
	<?php esc_html_e('Your customers must add a CNAME record pointing their custom domain to your fallback origin (e.g. your main domain or a dedicated CNAME target). Cloudflare will verify domain ownership via HTTP before issuing the certificate.', 'ultimate-multisite'); ?>
</p>
