# Enhance Control Panel Integration

## Overview
Enhance is a modern control panel that provides powerful hosting automation and management capabilities. This integration enables automatic domain syncing and SSL certificate management between Ultimate Multisite and Enhance Control Panel.

**Related Discussion:** See [GitHub Discussion #265](https://github.com/Ultimate-Multisite/ultimate-multisite/discussions/265) for community tips and additional information.

## Features
- Automatic domain syncing when domains are mapped in Ultimate Multisite
- Automatic SSL certificate provisioning via LetsEncrypt when DNS resolves
- Subdomain support for networks running in subdomain mode
- Domain removal when mappings are deleted
- Connection testing to verify API credentials

## Requirements

### System Requirements
- Enhance Control Panel installed and accessible
- WordPress Multisite installation hosted on or connected to an Enhance server
- Apache web server (Enhance currently supports Apache configurations; LiteSpeed Enterprise is available at reduced cost)

### API Access
You must have administrator access to the Enhance Control Panel to create API tokens.

## Getting Your API Credentials

### 1. Create an API Token

1. Log in to your Enhance Control Panel as an administrator
2. Click on **Settings** in the navigation menu
3. Navigate to **Access Tokens**
4. Click **Create Token**
5. Give the token a descriptive name (e.g., "Ultimate Multisite Integration")
6. Assign the **System Administrator** role
7. For the expiry date:
   - Leave empty if you want the token to never expire
   - Or set a specific expiration date for security purposes
8. Click **Create**

After creation, your **Access Token** and **Organization ID** will be displayed. **Save these immediately** as the token will only be shown once.

### 2. Get Your Organization ID

The Organization ID is displayed on the Access Tokens page in a blue information box labeled "Org ID: {your_id}".

The Organization ID is a UUID formatted like: `d8554b6d-5d0d-6719-009b-fec1189aa8f3`

You can also find a customer's Organization ID by:
1. Go to **Customers** page
2. Click **Manage customer** for the relevant customer
3. Look at the URL - the Organization ID is the alphanumeric characters after `/customers/`

### 3. Get Your Server ID

To find your Server ID (required for domain operations):

1. In the Enhance Control Panel, navigate to **Servers**
2. Click on the server where your WordPress installation is running
3. The Server ID (UUID format) will be visible in the URL or server details
4. Alternatively, you can use the API to list servers:

```bash
curl -s -X GET https://your-enhance-panel.com/api/servers \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" | jq
```

The server ID follows the UUID format: `00000000-0000-0000-0000-000000000000`

### 4. Get Your API URL

Your API URL is your Enhance Control Panel URL with `/api/` appended:

```
https://your-enhance-panel.com/api/
```

**Important:** The `/api/` path is required. Common mistakes include:
- Using just the domain without `/api/`
- Using HTTP instead of HTTPS (HTTPS is required for security)

## Configuration

### Required Constants

Add the following constants to your `wp-config.php` file:

```php
// Enhance Control Panel Integration
define('WU_ENHANCE_API_TOKEN', 'your-bearer-token-here');
define('WU_ENHANCE_API_URL', 'https://your-enhance-panel.com/api/');
define('WU_ENHANCE_SERVER_ID', 'your-server-uuid-here');
```

### Setup via Integration Wizard

1. In your WordPress admin, go to **Ultimate Multisite** > **Settings**
2. Navigate to the **Integrations** tab
3. Find **Enhance Control Panel Integration** and click **Configuration**
4. The wizard will guide you through the setup:
   - **Step 1:** Introduction and feature overview
   - **Step 2:** Enter your API credentials (Token, API URL, Server ID)
   - **Step 3:** Test the connection
   - **Step 4:** Review and activate

You can choose to:
- Let the wizard inject the constants into your `wp-config.php` file automatically
- Copy the constant definitions and add them manually

## Additional WordPress Configuration

Based on community feedback ([Discussion #265](https://github.com/Ultimate-Multisite/ultimate-multisite/discussions/265)), you may need to configure these additional settings:

### .htaccess Configuration

If you experience issues with domain mapping:
1. Delete the original Enhance `.htaccess` file
2. Replace it with the standard WordPress Multisite `.htaccess` file

### Cookie Constants

Add these constants to `wp-config.php` to ensure proper cookie handling across mapped domains:

```php
define('COOKIE_DOMAIN', $_SERVER['HTTP_HOST']);
define('COOKIEPATH', '/');
define('ADMIN_COOKIE_PATH', '/');
```

## How It Works

### When a Domain is Mapped

1. A user maps a custom domain in Ultimate Multisite (or a new site is created in subdomain mode)
2. The integration sends a POST request to Enhance's API: `/servers/{server_id}/domains`
3. Enhance adds the domain to your server configuration
4. When DNS resolves to your server, Enhance automatically provisions an SSL certificate via LetsEncrypt
5. The domain becomes active with HTTPS

### When a Domain is Removed

1. A domain mapping is deleted in Ultimate Multisite
2. The integration queries Enhance to find the domain's ID
3. A DELETE request is sent to: `/servers/{server_id}/domains/{domain_id}`
4. Enhance removes the domain from your server configuration

### DNS and SSL Checking

Ultimate Multisite includes built-in DNS and SSL checking:
- You can configure the check interval in **Domain Mapping Settings** (default: 300 seconds/5 minutes)
- The system will verify DNS propagation before marking a domain as active
- SSL certificate validity is checked automatically
- Enhance handles SSL provisioning automatically, so manual SSL configuration is not required

## Verifying Setup

### Test the Connection

1. In the Integration Wizard, use the **Test Connection** step
2. The plugin will attempt to list domains on your server
3. A success message confirms:
   - API credentials are correct
   - API URL is accessible
   - Server ID is valid
   - Permissions are properly set

### After Mapping a Domain

1. Map a test domain in Ultimate Multisite
2. Check the Ultimate Multisite logs (**Ultimate Multisite** > **Logs** > **integration-enhance**)
3. Verify in Enhance Control Panel that the domain was added:
   - Go to **Servers** > **Your Server** > **Domains**
   - The new domain should appear in the list
4. Once DNS propagates, verify SSL is provisioned automatically

## Troubleshooting

### API Connection Issues

**Error: "Failed to connect to Enhance API"**
- Verify `WU_ENHANCE_API_URL` includes `/api/` at the end
- Ensure you're using HTTPS, not HTTP
- Check that the Enhance panel is accessible from your WordPress server
- Verify there are no firewall rules blocking the connection

**Error: "Enhance API Token not found"**
- Ensure `WU_ENHANCE_API_TOKEN` is defined in `wp-config.php`
- Verify the token hasn't been deleted or expired in Enhance
- Check for typos in the token value

**Error: "Server ID is not configured"**
- Verify `WU_ENHANCE_SERVER_ID` is defined in `wp-config.php`
- Ensure the Server ID is a valid UUID format
- Confirm the server exists in your Enhance panel

### Domain Not Added

**Check the logs:**
1. Go to **Ultimate Multisite** > **Logs**
2. Filter by **integration-enhance**
3. Look for error messages indicating the issue

**Common causes:**
- Invalid domain name format
- Domain already exists in Enhance
- Insufficient API permissions (ensure token has System Administrator role)
- Server ID doesn't match the actual server in Enhance

### SSL Certificate Issues

**SSL not provisioning:**
- Verify DNS is pointing to your server's IP address
- Check that the domain resolves correctly: `nslookup yourdomain.com`
- Enhance requires DNS to resolve before it can provision SSL
- SSL provisioning typically takes 5-10 minutes after DNS propagation
- Check Enhance Control Panel logs for SSL-specific errors

**Manual SSL troubleshooting in Enhance:**
1. Go to **Servers** > **Your Server** > **Domains**
2. Find your domain and check its SSL status
3. You can manually trigger SSL provisioning if needed

### DNS Check Interval

If domains or SSL certificates are taking too long to activate:
1. Go to **Ultimate Multisite** > **Settings** > **Domain Mapping**
2. Find **DNS Check Interval** setting
3. Adjust from the default 300 seconds to a lower value (minimum: 10 seconds)
4. **Note:** Lower intervals mean more frequent checks but higher server load

### Authentication Errors

**HTTP 401/403 errors:**
- Regenerate your API token in Enhance
- Verify the token has **System Administrator** role
- Check that the token hasn't expired
- Ensure you're using the correct Organization ID (though it's typically not required in the URL)

### Log Analysis

Enable detailed logging:
```php
// Add to wp-config.php for enhanced debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check logs at:
- Ultimate Multisite logs: **Ultimate Multisite** > **Logs**
- WordPress debug log: `wp-content/debug.log`
- Enhance panel logs: Available in Enhance's admin interface

## API Reference

### Authentication
All API requests use Bearer token authentication:
```
Authorization: Bearer YOUR_TOKEN_HERE
```

### Common Endpoints Used

**List Servers:**
```
GET /servers
```

**List Domains on a Server:**
```
GET /servers/{server_id}/domains
```

**Add a Domain:**
```
POST /servers/{server_id}/domains
Body: {"domain": "example.com"}
```

**Delete a Domain:**
```
DELETE /servers/{server_id}/domains/{domain_id}
```

### Full API Documentation
Complete API documentation: [https://apidocs.enhance.com](https://apidocs.enhance.com)

## Best Practices

### Security
- **Never commit API tokens to version control**
- Store tokens in `wp-config.php` which should be excluded from Git
- Use tokens with appropriate permissions (System Administrator for full integration)
- Set token expiry dates for production environments
- Rotate tokens periodically

### Performance
- Use the default DNS check interval (300 seconds) to avoid excessive API calls
- Monitor Enhance server resources when running large-scale domain operations
- Consider staggering domain additions if mapping many domains at once

### Monitoring
- Regularly check Ultimate Multisite logs for integration errors
- Set up monitoring for failed domain additions
- Verify SSL certificates are provisioning correctly
- Keep an eye on Enhance server capacity and domain limits

## Additional Resources

- **Enhance Official Documentation:** [https://enhance.com/docs](https://enhance.com/docs)
- **Enhance API Documentation:** [https://apidocs.enhance.com](https://apidocs.enhance.com)
- **Enhance Community Forum:** [https://community.enhance.com](https://community.enhance.com)
- **GitHub Discussion:** [Issue #265 - Enhance Integration Tips](https://github.com/Ultimate-Multisite/ultimate-multisite/discussions/265)
- **Ultimate Multisite Domain Mapping Guide:** See wiki page "How to Configure Domain Mapping v2"

## Support

If you encounter issues:
1. Check the Troubleshooting section above
2. Review the Ultimate Multisite logs
3. Consult the [GitHub Discussions](https://github.com/Ultimate-Multisite/ultimate-multisite/discussions)
4. Contact Enhance support for panel-specific issues
5. Create a new discussion with detailed error logs for community assistance

## Notes

- This integration handles domain aliases only; Enhance manages SSL automatically
- The integration supports both custom domain mappings and subdomain-based sites
- Automatic www subdomain creation can be configured in Domain Mapping settings
- Enhance currently supports Apache configurations (LiteSpeed Enterprise available)
- Domain removal from Ultimate Multisite will remove the domain from Enhance but may not delete associated SSL certificates immediately
