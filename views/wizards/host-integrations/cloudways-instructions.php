<?php
/**
 * Cloudways instructions view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<article id="fullArticle">
	<h1 id="step-1-getting-a-cloudways-api-key" class="intercom-align-left" data-post-processed="true">Step 1: Getting a Cloudways API Key</h1>
	<p class="intercom-align-left">Follow the steps 1 and 2 of the official Cloudways tutorial to obtain an API key for your account (<a href="http://support.cloudways.com/how-to-use-the-cloudways-api/" rel="nofollow noopener noreferrer" target="_blank">Read the tutorial</a>). <b>Copy the API Key as we will need it in the following steps.</b></p>
	<h1 id="step-2-get-the-server-id" class="intercom-align-left" data-post-processed="true">Step 2: Get the Server ID</h1>
	<p class="intercom-align-left">The next piece of information we will need is the <b>server ID</b> of the server hosting your WordPress install on Cloudways. To discover the server ID, visit the <b>Server Management </b>screen of the server. The server ID will be present on the URL of that page after the “/server/” portion of it.</p>
	
	<p class="intercom-align-center"><i>The URL takes the form of </i><i>https://platform.cloudways.com/server/</i><i></i><b><i>SERVER_ID_HERE</i></b><i>/access_detail</i></p>
	<h1 id="step-3-get-the-app-id" class="intercom-align-left" data-post-processed="true">Step 3: Get the App ID</h1>
	<p class="intercom-align-left">We’ll need to do a similar thing to obtain the App ID for your WordPress installation. Go to Application Management screen of your WordPress app and the App ID will be present on the URL, after the “/apps/” portion of it.</p>
	
	<p class="intercom-align-center"><i>The same thing happens here: the URL takes the form of </i><i>https://platform.cloudways.com/apps/</i><i></i><b><i>YOUR_APP_ID_HERE</i></b><i>/access_detail</i></p>

	<h1 id="since-161--additional-step--extra-domains" class="intercom-align-left" data-post-processed="true">Additional Step – Extra Domains</h1>
	<p class="intercom-align-left">The Cloudways API is a bit strange in that it doesn’t offer a way to add or remove just one domain, only a way to update the whole domain list. That means that Ultimate Multisite <b>will replace all domains</b> you might have there with the list of mapped domains of the network every time a new domain is added.</p>
	<p class="intercom-align-left">If there are <b>external</b> domains you want kept on the Cloudways aliases list (domains outside your multisite network — for example, a separate marketing site or a parked domain), use the <b>WU_CLOUDWAYS_EXTRA_DOMAINS</b> constant with a comma-separated list:</p>
	<pre class="wu-overflow-auto wu-p-4 wu-m-0 wu-mt-2 wu-rounded wu-content-center wu-bg-gray-800 wu-text-white wu-font-mono wu-border wu-border-solid wu-border-gray-300 wu-max-h-screen wu-overflow-y-auto">define('WU_CLOUDWAYS_EXTRA_DOMAINS', 'extradomain1.com,extradomain2.com');</pre>
	<p class="intercom-align-left">Here’s how it should look on your wp-config.php (fake values used below):</p>

	<h1 id="important-wildcard-ssl-pitfall" class="intercom-align-left" data-post-processed="true"><?php esc_html_e('Important – Wildcard SSL Pitfall', 'ultimate-multisite'); ?></h1>
	<p class="intercom-align-left"><b><?php esc_html_e('Do not include your own network\'s subdomain wildcard (for example *.your-network.com) in WU_CLOUDWAYS_EXTRA_DOMAINS, and do not install a wildcard SSL certificate for it on the Cloudways application.', 'ultimate-multisite'); ?></b></p>
	<p class="intercom-align-left"><?php esc_html_e('Cloudways replaces the active SSL certificate on the application each time the integration installs a Let\'s Encrypt certificate. If a wildcard certificate is already active on the application, Cloudways will not issue per-domain Let\'s Encrypt certificates for the custom domains Ultimate Multisite maps, and tenants will end up with custom domains that have no SSL.', 'ultimate-multisite'); ?></p>
	<p class="intercom-align-left"><b><?php esc_html_e('Recommended Cloudways SSL setup for an Ultimate Multisite network:', 'ultimate-multisite'); ?></b></p>
	<ol>
		<li><?php esc_html_e('In the Cloudways application\'s SSL Certificate tab, install a standard Let\'s Encrypt certificate that covers only your-network.com and www.your-network.com — not a wildcard.', 'ultimate-multisite'); ?></li>
		<li><?php esc_html_e('Do not put *.your-network.com (or any subdomain pattern of your own network) in WU_CLOUDWAYS_EXTRA_DOMAINS. Reserve that constant for external domains only.', 'ultimate-multisite'); ?></li>
		<li><?php esc_html_e('Create the per-tenant subdomain wildcard at the DNS level only (an A record for *.your-network.com pointing at your Cloudways server IP) so subsites resolve. SSL for individual mapped custom domains is then issued automatically by the integration.', 'ultimate-multisite'); ?></li>
	</ol>
	<p class="intercom-align-left"><?php esc_html_e('If your tenants\' custom domains are stuck without SSL, check the Cloudways SSL tab. If a wildcard certificate is active, replace it with a standard Let\'s Encrypt certificate for the main network domain only, then re-trigger a domain mapping (or wait for the next one) — the integration will start issuing per-domain certificates again.', 'ultimate-multisite'); ?></p>
	<p class="intercom-align-left"><i><?php esc_html_e('Note: this integration always requests standard (non-wildcard) Let\'s Encrypt certificates from Cloudways. If a wildcard pattern is supplied in WU_CLOUDWAYS_EXTRA_DOMAINS the leading "*." is stripped before the SSL request, so the wildcard itself is never installed by this integration.', 'ultimate-multisite'); ?></i></p>

	<p class="intercom-align-center"><i>You’re all set!</i></p>
	<p class="intercom-align-left">Now, every time a new domain is mapped in the network (via the Aliases tab by the network admin or via the custom domain meta-box on the user’s Account page) will be added to the Cloudways platform automatically.</p>
	<p class="intercom-align-left">The same is true for domain removals. Every time a domain is deleted from the network, that change will be communicated to your Cloudways account instantly!</p>
</article>
