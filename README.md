# Ultimate Multisite

<p align="center">
  <img src="https://github.com/Multisite-Ultimate/ultimate-multisite/blob/main/assets/img/logo.png" alt="Ultimate Multisite Logo" width="300">
</p>

<p align="center">
  <strong>The Complete Network Solution for transforming your WordPress Multisite into a Website as a Service (WaaS) platform.</strong>
</p>

<p align="center">
  <a href="http://www.gnu.org/licenses/gpl-2.0.html"><img src="https://img.shields.io/badge/License-GPL%20v2-blue.svg" alt="License: GPL v2"></a>
  <a href="https://wordpress.org/"><img src="https://img.shields.io/badge/WordPress-6.8%20Tested-green.svg" alt="WordPress: 6.8 Tested"></a>
  <a href="https://php.net/"><img src="https://img.shields.io/badge/PHP-7.4.0%2B-purple.svg" alt="PHP: 7.4.0+"></a>
  <a href="https://php.net/"><img src="https://img.shields.io/badge/Up%20To%20PHP-8.4.6-purple.svg" alt="Up To PHP: 8.4.6"></a>
  <a href="https://github.com/Multisite-Ultimate/ultimate-multisite/releases"><img src="https://img.shields.io/github/v/release/Multisite-Ultimate/ultimate-multisite" alt="Latest Release"></a>
</p>
<p align="center">
  <a href="https://github.com/Multisite-Ultimate/ultimate-multisite/actions/workflows/tests.yml"><img src="https://github.com/Multisite-Ultimate/ultimate-multisite/actions/workflows/tests.yml/badge.svg" alt="Unit & Integration Tests"></a>
  <a href="https://github.com/Multisite-Ultimate/ultimate-multisite/actions/workflows/e2e.yml"><img src="https://github.com/Multisite-Ultimate/ultimate-multisite/actions/workflows/e2e.yml/badge.svg" alt="E2E Tests"></a>
  <a href="https://codecov.io/gh/Multisite-Ultimate/ultimate-multisite"><img src="https://codecov.io/gh/Multisite-Ultimate/ultimate-multisite/branch/main/graph/badge.svg" alt="Code Coverage"></a>
  <a href="https://github.com/Multisite-Ultimate/ultimate-multisite/actions/workflows/code-quality.yml"><img src="https://github.com/Multisite-Ultimate/ultimate-multisite/actions/workflows/code-quality.yml/badge.svg" alt="Code Quality"></a>
</p>

## 🌟 Overview

**Ultimate Multisite** helps you transform your WordPress Multisite installation into a powerful Website as a Service (WaaS) platform. This plugin enables you to offer website creation, hosting, and management services to your customers through a streamlined interface.

This plugin was formerly known as WP Ultimo and is now community maintained.

## ✨ Key Features

- **Site Creation** - Allow customers to create their own sites in your network
- **Domain Mapping** - Support for custom domains with automated DNS verification
- **Payment Processing** - Integrations with popular payment gateways like Stripe and PayPal
- **Plan Management** - Create and manage subscription plans with different features and limitations
- **Template Sites** - Easily clone and use template sites for new customer websites
- **Customer Dashboard** - Provide a professional management interface for your customers
- **White Labeling** - Brand the platform as your own
- **Hosting Integrations** - Connect with popular hosting control panels like cPanel, RunCloud, and more

## 📋 Requirements

- WordPress Multisite 5.3 or higher
- PHP 7.4.30 or higher
- MySQL 5.6 or higher

## 🔧 Installation

> **Important:** Do not use GitHub's green **"Code → Download ZIP"** button on this page. That archive is the raw source code and is missing the compiled dependencies — installing it will produce errors such as `Failed opening required .../vendor/autoload_packages.php`. Always download from the [**Releases page**](https://github.com/Multisite-Ultimate/ultimate-multisite/releases) instead.

There are two recommended ways to install Ultimate Multisite:

### Method 1: Using the pre-packaged release (Recommended)

1. Download the latest release ZIP from the [Releases page](https://github.com/Multisite-Ultimate/ultimate-multisite/releases)
2. Log in to your WordPress Network Admin dashboard
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the downloaded ZIP file and click "Install Now"
5. Network Activate the plugin through the 'Plugins' menu in WordPress
6. Follow the step-by-step Wizard to set the plugin up

### Method 2: Using Git and Composer (For developers)

This method requires command-line access to your server and familiarity with Git and Composer.

1. Clone the repository to your plugins directory:

   ```bash
   cd wp-content/plugins/
   git clone https://github.com/Multisite-Ultimate/ultimate-multisite.git
   cd ultimate-multisite
   ```

2. Install the required dependencies using Composer:

   ```bash
   composer install
   ```

3. Network Activate the plugin in your WordPress Network Admin dashboard
4. Follow the setup wizard to complete the installation

## 🔍 Common Installation Issues

<details>
<summary><strong>"Failed opening required [...]/vendor/autoload_packages.php"</strong></summary>
<p>This error occurs when the required vendor files are missing. This typically happens when:</p>
<ul>
  <li>You've downloaded the repository directly from GitHub without using a release package</li>
  <li>The composer dependencies haven't been installed</li>
</ul>
<p><strong>Solution:</strong> Use the pre-packaged release from the <a href="https://github.com/Multisite-Ultimate/ultimate-multisite/releases">Releases page</a> or run <code>composer install</code> in the plugin directory.</p>
</details>

<details>
<summary><strong>"Cannot declare class ComposerAutoloaderInitWPUltimoDependencies, because the name is already in use"</strong></summary>
<p>This error usually occurs when updating from an older version of WP Ultimo or when multiple versions of the plugin are installed.</p>
<p><strong>Solution:</strong> Deactivate and remove any older versions of WP Ultimo or Ultimate Multisite before activating the new version.</p>
</details>

<details>
<summary><strong>"Class 'WP_Ultimo\Database\Sites\Site_Query' not found"</strong></summary>
<p>This error can occur if the plugin's autoloader isn't properly loading all the necessary classes.</p>
<p><strong>Solution:</strong> Use the pre-packaged release from the <a href="https://github.com/Multisite-Ultimate/ultimate-multisite/releases">Releases page</a> which includes all required files.</p>
</details>

<details>
<summary><strong>Site screenshots show a Cloudflare challenge page instead of the actual site</strong></summary>
<p>Ultimate Multisite uses <a href="https://www.thum.io/">thum.io</a> to generate site screenshots. If your network is behind Cloudflare with Bot Fight Mode or similar protections enabled, thum.io's screenshot bot may be blocked and return a Cloudflare challenge page instead of your site screenshot.</p>

<p><strong>Solution:</strong> Create a Cloudflare WAF exception rule to allow thum.io's bot:</p>

<ol>
<li>Log in to your <a href="https://dash.cloudflare.com/">Cloudflare dashboard</a></li>
<li>Select your domain</li>
<li>Go to <strong>Security → WAF → Custom rules</strong></li>
<li>Click <strong>Create rule</strong></li>
<li>Configure the rule:
<ul>
<li><strong>Rule name:</strong> <code>Allow thum.io screenshot bot</code></li>
<li><strong>Field:</strong> <code>User Agent</code></li>
<li><strong>Operator:</strong> <code>contains</code></li>
<li><strong>Value:</strong> <code>Thum.io</code></li>
<li><strong>Action:</strong> <code>Skip</code> → Select all skip options (WAF, Rate Limiting, etc.)</li>
</ul>
</li>
<li>Click <strong>Deploy</strong></li>
</ol>

<p>Alternatively, if you use Cloudflare's Super Bot Fight Mode, you can add an exception in <strong>Security → Bots → Configure Super Bot Fight Mode</strong> to allow verified bots or specific user agents.</p>

<p><strong>Note:</strong> Screenshots require sites to be publicly accessible. Local development environments cannot generate screenshots regardless of Cloudflare settings.</p>
</details>

## 🚀 Contributing

We welcome contributions to Ultimate Multisite! Here's how you can contribute effectively:

### Development Workflow

1. **Quick Setup:**
   ```bash
   git clone https://github.com/Multisite-Ultimate/ultimate-multisite.git
   cd wp-multisite-waas
   npm run dev:setup  # Installs dependencies and sets up Git hooks
   ```

2. **Development Commands:**
   ```bash
   npm test              # Run tests
   npm run test:coverage # Run tests with coverage
   npm run lint         # Check code style (PHPCS)
   npm run lint:fix     # Fix code style automatically
   npm run stan         # Run static analysis (PHPStan)
   npm run quality      # Run lint + stan
   npm run check        # Run all quality checks
   npm run build        # Production build
   npm run build:dev    # Development build
   npm run clean        # Clean build artifacts
   ```

3. **Making Changes:**
   - Create your feature branch (`git checkout -b feat/amazing-feature`)
   - Make your changes with tests
   - Git hooks will automatically run PHPCS and PHPStan on changed files
   - Commit using conventional format: `feat(scope): description`
   - Run `npm run check` before pushing
   - Push and create a Pull Request

4. **Code Quality:**
   - Pre-commit hooks run automatically
   - Follow WordPress coding standards
   - Include tests for new features
   - Maintain >80% code coverage
   - Use conventional commit messages

5. **Important:** Update both README.md and readme.txt files when making changes that affect versions, features, or documentation.

### Pull Request Guidelines

When submitting pull requests, please:

1. Include a clear description of the changes and their purpose
2. Reference any related issues using GitHub's issue linking syntax (#123)
3. Ensure your code follows the existing style and conventions
4. Include screenshots or GIFs for UI changes if applicable
5. Make sure all tests pass (if available)
6. Update documentation as needed
7. Verify that both README.md and readme.txt are updated and synchronized

### Release Process

Releases are automated using GitHub Actions workflows that trigger when a version tag is pushed.

**Tagging Convention:** To trigger a new release build, push a tag following the semantic versioning format:

```bash
git tag v2.3.5  # For example, for version 2.3.5
git push origin v2.3.5
```

The tag must begin with "v" followed by the version number (v*.*.\*)

This will automatically:

1. Build the plugin (run `npm run build`)
2. Create a properly packaged ZIP file
3. Create a GitHub release with the ZIP attached

When preparing for a release:

1. Update the version number in the main plugin file and readme.txt
2. Update the changelog in readme.txt
3. Ensure README.md and readme.txt are synchronized with the latest information
4. Create and push the appropriate version tag

## ✅ Testing

See [readme](tests/e2e/README.md) for e2e testing.

## 🆘 Support

For support, please open an issue on the [GitHub repository](https://github.com/Multisite-Ultimate/ultimate-multisite/issues).

## ⚠️ Upgrade Notice

We recommend running this in a staging environment before updating your production environment.

## 📝 Recent Changes

### Version [2.4.12] - Released on 2026-02-27
- New: Send Invoice and Resend Invoice workflows for payments.
- New: Standalone "Pay Invoice" checkout form for invoice payments without a membership.
- New: Payment Methods element displaying current card info and change payment method flow via Stripe Billing Portal.
- New: System events for invoice sent, recurring payment failure, and membership expired with email notifications.
- New: Checkout form debug autofill button when WP_ULTIMO_DEBUG is enabled.
- New: Domain meta table for storing metadata on domain records.
- New: Extensibility hooks on domain mapping widget and domain list table.
- New: Node Management capability interface for hosting integrations.
- Fix: Password strength validation no longer blocks checkout when the meter element is absent.
- Fix: %2F being stripped from SSO redirect URLs breaking some WooCommerce URLs.
- Fix: Stripe Checkout gateway updated to current API — uses price_data format, proper subscription/payment mode, and skips zero-amount items.
- Fix: Removed deprecated Stripe API version pin and product type parameter.
- Fix: Membership cancellation now properly cancels the gateway subscription before the local membership.
- Fix: Payments no longer require a membership, enabling standalone invoices.
- Fix: Cart no longer overrides duration for products with independent billing cycles.
- Fix: Network installer correctly sets core multisite table names.
- Fix: Admin page save handlers now return proper bool values.
- Improved: "Change Payment Method" replaces the destructive "Cancel Payment Method" flow.
- Improved: Integration wizard API key fields use password input type to prevent browser autofill.
- Improved: Integration wizard shows error state on test failure and improved navigation.
- Improved: Addon settings grouped under dedicated admin bar submenu.
- Improved: Select2 multi-select preserves saved option ordering.
- Improved: PayPal fires payment_failed event on IPN failures.
- New: Addon compatibility headers (`UM requires at least`) with network admin version mismatch notices.
- New: `wu_get_checkout_form_by_slug` filter for addon-registered virtual checkout forms.
- New: Cart filters `wu_cart_show_no_changes_error` and `wu_cart_addon_include_existing_plan` for addon checkout flows.
- New: `wu-register-domain` added to checkout element slug list for addon checkout pages.
- Fix: AJAX search_models not passing query parameters to model functions.
- Fix: Template validation failing when an addon product is selected at checkout.
- Fix: New subdomain sites created with http:// instead of https:// causing infinite redirects.
- Fix: mPDF PSR-log aware trait patch applied to wrong file on some Composer versions.
- Improved: Default minimum password strength lowered from "strong" to "medium" for better usability.
- Improved: Dashboard first-steps widget shows contextual action labels for completed steps.

### Version [2.4.11] - Released on 2026-02-16
- New: Settings API for remote settings management.
- New: Pay-What-You-Want (PWYW) pricing with per-product custom amounts and recurring options.
- New: Billing-period controls for discount codes and membership creation.
- New: Better error page for customers and admins.
- New: Stripe Connect via secure proxy server — platform credentials no longer distributed in plugin code.
- New: Stripe Checkout Element with automatic billing address handling and removal of application fees.
- New: Multisite Setup Wizard — guides single-site installs through enabling and configuring WordPress Multisite.
- New: Modular hosting integration system with encrypted credential storage.
- New: Form field normalization CSS for consistent checkout and login styling across all themes and page builders.
- Fix: Password strength setting not being applied during checkout.
- Fix: Encoded characters stripped from URLs during SSO and domain mapping redirects.
- Fix: Inline login prompt stability and missing validation for existing emails at checkout.
- Fix: Site title field error caused by third-party plugin conflicts.
- Fix: URL replacement failing for Elementor content on subdirectory multisite installs.
- Fix: Country and state selection issues in checkout.
- Fix: Duplicate Country/ZIP fields appearing on Stripe checkout.
- Fix: Invoice PDF download failing with expired nonce.
- Fix: Settings page crash on PHP 8.4.
- Fix: Single-site compatibility issues and dashboard widget setup status detection.
- Fix: Rewrite rules now flushed when signup pages are created or modified.
- Improved: Admin pages no longer loaded on frontend and cron requests for better performance.
- Improved: Security hardening for input validation, credential storage, and cart processing.
- Improved: Expanded automated test coverage across checkout, payments, and admin functionality.

### Version [2.4.10] - Released on 2026-01-23
- New: Configurable minimum password strength setting with Medium, Strong, and Super Strong options.
- New: Super Strong password requirements include 12+ characters, uppercase, lowercase, numbers, and special characters - compatible with WPMU DEV Defender Pro rules.
- New: Real-time password requirement hints during checkout with translatable strings.
- New: Themed password field styling with visibility toggle and color fallbacks for page builders (Elementor, Kadence, Beaver Builder).
- New: Opt-in anonymous usage tracking to help improve the plugin.
- New: Rating reminder notice after 30 days of installation.
- New: WooCommerce Subscriptions compatibility layer for site duplication.
- Improved: JSON response handling for pending site creation in non-FastCGI environments.

### Version [2.4.9] - Released on 2025-12-23
- New: Inline login prompt at checkout for existing users - returning customers can sign in directly without leaving the checkout flow.
- New: GitHub Actions workflow for PR builds with WordPress Playground testing - enables one-click browser-based testing of pull requests.
- Fixed: Template switching now preserves images - URLs in post content are correctly updated when switching templates.
- Fixed: Email manager initialization during setup wizard - system emails are now correctly created.
- Fixed: Template switching permission and capability checks improved with better error messaging.
- Fixed: Multiple primary domains being set.
- Improved: Template selection logic with better null safety and smart fallbacks for pre-selected templates.
- Improved: Compatibility for legacy filter `wu_create_site_meta` from WP Ultimo v1.
- Improved: Added support for Runcloud V3 API

### Version [2.4.8] - Released on 2025-11-21
- New: Added MCP (Model Context Protocol) Server integration.
- New: Added support for multi-network installations with network-specific customers, memberships, and products.
- New: Added magic login links for SSO when third-party cookies are disabled.
- New: Added admin notice when invalid COOKIE_DOMAIN constant is detected.
- Fixed: WooCommerce subscriptions incorrectly set to staging mode when site is duplicated.
- Fixed: Single-Sign-On (SSO) authentication issues with custom domains.
- Fixed: Template switching functionality and improved singleton pattern usage across codebase.
- Improved: Enhanced domain mapping element and login form handling.
- Improved: Better redirect handling for sites within the network.
- Improved: Faster site creation after checkout.

### Version [2.4.7] - Released on 2025-10-31
- Fixed: Conflict with YesCookie plugin.
- Improved: Thumbnail image quality on template selection in the checkout.
- Fixed: Redirect from secondary domains to primary domain.
- Fixed: Choosing templates for checkout form builder.
- Fixed: Extra domain creation with subdirectory installation.
- Improved: Allow html in custom domain instructions.

### Version [2.4.6] - Released on 2025-10-15
- Fixed: Toggle switches in RTL languages.
- Fixed: Rendering admin pages for legacy addons.
- Fixed: Some Stripe API errors.
- Improved: Better site URL autogeneration and added preview option.
- Fixed: Escaping too much HTML.
- Fixed: Saving HTML in credits field.
- Improved: Type safety in code.
- Fixed: Downgrading during a trial extending the trial period.
### Version [2.4.5] - Released on 2025-09-30
- Fixed: Custom domain check when downgrading.
- Fixed: Bug in Action Scheduler.
- Fixed: Hosting integration wizard freezing during setup.
- Improved: More robust handling for login URL obfuscation when 404 template unavailable.
- Improved: Better error messaging for installer with sanitized HTML display.
- Added: Recommended plugins installer functionality.
- Added: New end-to-end testing framework.
- Added: Option to include a "Powered by..." message in the footer of customer sites.
- Added: Install recommended "user-switching" plugin during setup wizard.
- Improved: Autogeneration of site urls and usernames to be more human friendly.
- Improved: Code style and return type consistency across codebase.

### Version [2.4.4] - Released on 2025-09-17
- Fixed: Saving email templates without stripping html
- New: Option to allow site owners to edit users on their site
- Fixed: Invoices not loading when logo is not set
- Fixed: Verify DNS settings when using a reverse proxy
- Improved: Lazy load limitations for better performance and compatibility
- New: Add Admin Notice if sunrise.php is not setup
- New: Option to not always create www. subdomains with hosting integrations
- Improved: Plugin renamed to Ultimate Multisite

### Version [2.4.3] - Released on 2025-08-15
- Fixed: Bug in Slim SEO plugin
- New: Addon Marketplace
- Fixed: Custom logo not showing on emails and invoices
- Fixed: Limitations failing to load

### Version [2.4.2] - Released on 2025-08-07
- Fixed: Authentication of the API
- Fixed: Saving checkout fields
- Fixed: Creating Products and Sites
- Fixed: Duplicating sites
- Improved: Performance of switch_blog
- Improved: Remove extra queries related update_meta_data hook and 1.X compat
- New: Addon Marketplace
- Improved: Update currencies to support all supported by Stripe

### Version [2.4.1] - Released on 2025-07-17

- Improved: Update Stripe PHP Library to latest version.
- Improved: Update JS libs.
- Fixed: Added a few more security checks.
- Fixed: Fatal error that may occur when upgrading from old name.
- Improved: Added check for custom domain count when downgrading.

### Version [2.4.0] - Released on 2025-07-07

- Improved: Prep Plugin for release on WordPress.org
- Improved: Update translation text domain
- Fixed: Escape everything that should be escaped.
- Fixed: Add nonce checks where needed.
- Fixed: Sanitize all inputs.
- Improved: Apply Code style changes across the codebase.
- Fixed: Many deprecation notices.
- Improved: Load order of many filters.
- Improved: Add Proper Build script
- Improved: Use emojii flags
- Fixed: i18n deprecation notice for translating too early
- Improved: Put all scripts in footer and load async
- Improved: Add discounts to thank you page
- Improved: Prevent downgrading a plan if it the post type could would be over the limit
- Fixed: Styles on thank you page of legacy checkout

### Version [2.3.4] - Released on 2024-01-31

- Fixed: Unable to checkout with any payment gateway
- Fixed: Warning Undefined global variable $pagenow

### Version [2.3.3] - Released on 2024-01-29

- Improved: Plugin renamed to Ultimate Multisite
- Removed: Enforcement of paid license
- Fixed: Incompatibilities with WordPress 6.7 and i18n timing
- Improved: Reduced plugin size by removing many unnecessary files and shrinking images

For the complete changelog, please see [readme.txt](readme.txt).

## 👥 Contributors

Ultimate Multisite is an open-source project with contributions from:

- [aanduque](https://github.com/aanduque)
- [superdav42](https://github.com/superdav42)
- [And the community](https://github.com/Multisite-Ultimate/ultimate-multisite/graphs/contributors)

## 📄 License

Ultimate Multisite is licensed under the GPL v2 or later.

Copyright © 2024 [Ultimate Multisite Contributors](https://github.com/Multisite-Ultimate/ultimate-multisite/graphs/contributors)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
