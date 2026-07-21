<?php
/**
 * DirectAdmin integration instructions view.
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
	<?php esc_html_e('You will need DirectAdmin API access, preferably using a Login Key, and the base DirectAdmin domain that serves this WordPress multisite.', 'ultimate-multisite'); ?>
</p>

<p class="wu-text-sm wu-bg-blue-100 wu-p-4 wu-text-blue-600 wu-rounded">
	<strong><?php esc_html_e('What this integration uses:', 'ultimate-multisite'); ?></strong><br>
	<?php esc_html_e('Ultimate Multisite talks to DirectAdmin over its documented CMD_API endpoints and creates domain pointer aliases. Domain pointers keep mapped domains on the same document root as your main multisite install.', 'ultimate-multisite'); ?>
</p>

<h3 class="wu-m-0 wu-py-4 wu-text-lg" id="step-1-create-login-key">
	<?php esc_html_e('Step 1 — Create a DirectAdmin Login Key', 'ultimate-multisite'); ?>
</h3>

<ol class="wu-text-sm wu-list-decimal wu-pl-6 wu-space-y-2">
	<li><?php esc_html_e('Log in to DirectAdmin as the user that owns the WordPress multisite domain, or as an admin/reseller that can impersonate that user.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Open the Login Keys screen and create a key named something recognisable, such as “Ultimate Multisite”.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Allow the key to use the domain pointer API for the account. If your DirectAdmin version does not expose granular permissions, use the standard account key permissions and restrict by expiry/IP where possible.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Copy the generated key immediately. Use it in the Login Key field on the next step instead of your DirectAdmin password.', 'ultimate-multisite'); ?></li>
</ol>

<h3 class="wu-m-0 wu-py-4 wu-text-lg" id="step-2-confirm-base-domain">
	<?php esc_html_e('Step 2 — Confirm the base domain', 'ultimate-multisite'); ?>
</h3>

<p class="wu-text-sm">
	<?php esc_html_e('The base domain is the DirectAdmin domain that currently serves your WordPress multisite. New customer domains will be added as alias pointers to this domain, not as separate sites or separate document roots.', 'ultimate-multisite'); ?>
</p>

<ul class="wu-text-sm wu-list-disc wu-pl-6 wu-space-y-2">
	<li><?php esc_html_e('For a normal installation, this is your network domain, such as network.example.com.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('If you authenticate as an admin or reseller for another account, use DirectAdmin’s username impersonation format in the username field, such as admin|customer.', 'ultimate-multisite'); ?></li>
	<li><?php esc_html_e('Keep wildcard DNS or customer DNS pointed at the same server; this integration does not change external DNS records.', 'ultimate-multisite'); ?></li>
</ul>

<h3 class="wu-m-0 wu-py-4 wu-text-lg" id="step-3-ssl">
	<?php esc_html_e('Step 3 — SSL expectations', 'ultimate-multisite'); ?>
</h3>

<p class="wu-text-sm">
	<?php esc_html_e('DirectAdmin can issue certificates for aliases when Let’s Encrypt or Auto SSL is enabled for the account. After the pointer is created, make sure DirectAdmin’s SSL automation includes domain pointers for the base domain.', 'ultimate-multisite'); ?>
</p>

<p class="wu-text-sm wu-bg-yellow-100 wu-p-4 wu-text-yellow-700 wu-rounded wu-mt-4">
	<strong><?php esc_html_e('Important:', 'ultimate-multisite'); ?></strong><br>
	<?php esc_html_e('Customer domains still need DNS records pointing to this server before DirectAdmin can validate SSL and before WordPress can serve the mapped domain.', 'ultimate-multisite'); ?>
</p>
