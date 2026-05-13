<?php
/**
 * Hostinger (hPanel) integration instructions view.
 *
 * @package WP_Ultimo
 * @subpackage Views/Wizards/Host_Integrations
 * @since 2.5.1
 */
defined('ABSPATH') || exit;
?>
<h1>
	<?php esc_html_e('Instructions', 'ultimate-multisite'); ?>
</h1>

<p class="wu-text-lg wu-text-gray-600 wu-my-4 wu-mb-6">
	<?php esc_html_e('You will need a Hostinger', 'ultimate-multisite'); ?> <strong><?php esc_html_e('API Token', 'ultimate-multisite'); ?></strong> <?php esc_html_e('from your hPanel account.', 'ultimate-multisite'); ?>
</p>

<p class="wu-text-sm wu-bg-blue-100 wu-p-4 wu-text-blue-600 wu-rounded">
	<strong><?php esc_html_e('Before you start...', 'ultimate-multisite'); ?></strong><br>
	<?php esc_html_e('The Hostinger API is currently in beta. Ultimate Multisite uses only the public endpoints documented at developers.hostinger.com. You can revoke the token at any time from hPanel and the integration will fail gracefully.', 'ultimate-multisite'); ?>
</p>

<h3 class="wu-m-0 wu-py-4 wu-text-lg" id="step-1-generate-token">
	<?php esc_html_e('Step 1 — Generate an API token in hPanel', 'ultimate-multisite'); ?>
</h3>

<ol class="wu-text-sm wu-list-decimal wu-pl-6 wu-space-y-2">
	<li><?php esc_html_e('Log in to hPanel and open Account → API (or visit hpanel.hostinger.com/profile/api directly).', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Click Generate Token, give it a recognisable name (e.g. "Ultimate Multisite") and choose an expiration date.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Copy the token immediately — Hostinger does not show it again after the page is refreshed.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Paste the token into the Hostinger API Token field on the next step.', 'ultimate-multisite'); ?></li>
</ol>

<h3 class="wu-m-0 wu-py-4 wu-text-lg" id="step-2-what-this-integration-does">
	<?php esc_html_e('Step 2 — What this integration manages', 'ultimate-multisite'); ?>
</h3>

<p class="wu-text-sm">
	<?php esc_html_e('Hostinger has a single API token per account, so this integration covers every Hostinger-managed zone in that account. When subsites are created or domains are mapped, Ultimate Multisite will add the appropriate DNS record to the matching Hostinger zone. Zones not in your Hostinger portfolio are skipped — the integration never modifies DNS it does not own.', 'ultimate-multisite'); ?>
</p>

<ul class="wu-text-sm wu-list-disc wu-pl-6 wu-space-y-2">
	<li><?php esc_html_e('Subdomain network installs: a CNAME for each new subsite is added to the network domain\'s Hostinger zone.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Custom domain mapping: when a customer maps a domain that is also hosted on your Hostinger account, the matching record is created automatically.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('SSL: Hostinger issues SSL certificates automatically for hostnames that resolve to its infrastructure — no extra configuration needed.', 'ultimate-multisite'); ?></li>
</ul>

<p class="wu-text-sm wu-bg-yellow-100 wu-p-4 wu-text-yellow-700 wu-rounded wu-mt-4">
	<strong><?php esc_html_e('Out of scope:', 'ultimate-multisite'); ?></strong><br>
	<?php esc_html_e('Hostinger\'s WordPress update controls (auto-update policy for core, themes and plugins), CDN toggles, and Kodee AI actions are not currently exposed by their public API. You will continue to manage those features in hPanel directly. Email account provisioning is also not part of this integration because Hostinger does not expose an email accounts API at this time.', 'ultimate-multisite'); ?>
</p>

<h3 class="wu-m-0 wu-py-4 wu-text-lg" id="step-3-companion-addons">
	<?php esc_html_e('Step 3 — Optional companion addons', 'ultimate-multisite'); ?>
</h3>

<p class="wu-text-sm">
	<?php esc_html_e('Two optional Ultimate Multisite addons extend the same Hostinger API token with additional capabilities:', 'ultimate-multisite'); ?>
</p>

<ul class="wu-text-sm wu-list-disc wu-pl-6 wu-space-y-2">
	<li><strong><?php esc_html_e('Domain Seller addon:', 'ultimate-multisite'); ?></strong> <?php esc_html_e('check domain availability, register domains, manage WHOIS contacts and toggle privacy protection from Ultimate Multisite checkout.', 'ultimate-multisite'); ?></li>
	<li><strong><?php esc_html_e('Multi-Tenancy addon:', 'ultimate-multisite'); ?></strong> <?php esc_html_e('provision new Hostinger Websites for tenants and keep their primary domains in sync.', 'ultimate-multisite'); ?></li>
</ul>

<p class="wu-text-sm wu-mt-4">
	<?php esc_html_e('Each addon reuses the API token you provide on the next step — no second credential is required.', 'ultimate-multisite'); ?>
</p>
