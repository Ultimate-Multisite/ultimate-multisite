# TODO

## Tasks

- [x] t374 Add support for creating new domains in Cloudflare @superdav42 #374 ~4h started:2026-03-24T00:00:00Z pr:#377 completed:2026-03-25
- [x] t450 Positioning and growth plan to attract more customers ref:GH#450 ~8h pr:#452 completed:2026-03-25
- [x] t451 Fix WP Performance Metrics CI ECONNREFUSED failure (WordPress server not starting on port 9400) ref:GH#475 ~2h #bug #auto-dispatch pr:#476 completed:2026-03-25
- [x] t452 feat(tax): implement universal tax fallback as "Apply to all countries" dropdown option on Tax Rates page ref:GH#505 ~3h #enhancement #auto-dispatch pr:#506 completed:2026-03-26

## Unit Test Coverage Tasks

Overall coverage: **35%** (20,720 / 59,212 statements). 90 files at 0% coverage.

### Priority 1 — Business-Critical Code (low coverage, high risk)

- [x] t453 test(checkout): write unit tests for Checkout class (inc/checkout/class-checkout.php — 7.9% coverage, 960 uncovered stmts) #testing #auto-dispatch ~6h ref:GH#555 pr:#569 completed:2026-03-27
- [x] t454 test(cart): improve Cart test coverage (inc/checkout/class-cart.php — 61.1% coverage, 382 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#556 pr:#583 completed:2026-03-28
- [x] t455 test(stripe): write unit tests for Base_Stripe_Gateway (inc/gateways/class-base-stripe-gateway.php — 28.6% coverage, 1093 uncovered stmts) #testing #auto-dispatch ~8h ref:GH#624 pr:#648 completed:2026-03-28
- [x] t456 test(stripe): improve Stripe_Gateway test coverage (inc/gateways/class-stripe-gateway.php — 55% coverage, 200 uncovered stmts) #testing ~4h pr:#623 completed:2026-03-28
- [x] t457 test(paypal): write unit tests for PayPal_Gateway (inc/gateways/class-paypal-gateway.php — 1.1% coverage, 783 uncovered stmts) #testing #auto-dispatch ~6h ref:GH#627 pr:#649 completed:2026-03-28
- [x] t458 test(paypal): improve PayPal_REST_Gateway test coverage (inc/gateways/class-paypal-rest-gateway.php — 29.8% coverage, 683 uncovered stmts) #testing #auto-dispatch ~5h ref:GH#629 pr:#636 completed:2026-03-28
- [x] t459 test(paypal): write tests for PayPal_OAuth_Handler (inc/gateways/class-paypal-oauth-handler.php — 15% coverage, 238 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#549 pr:#554 completed:2026-03-27
- [x] t460 test(gateway): improve Gateway_Manager test coverage (inc/managers/class-gateway-manager.php — 34.7% coverage, 177 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#550 pr:#552 completed:2026-03-27
- [x] t461 test(checkout-pages): improve Checkout_Pages test coverage (inc/checkout/class-checkout-pages.php — 33.7% coverage, 203 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#551 pr:#553 completed:2026-03-27

### Priority 2 — Core Domain Logic (moderate coverage gaps)

- [x] t462 test(site-manager): improve Site_Manager test coverage (inc/managers/class-site-manager.php — 23.5% coverage, 433 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#557 pr:#568 completed:2026-03-27
- [x] t463 test(domain-manager): improve DNS_Record_Manager test coverage (inc/managers/class-dns-record-manager.php — 14.4% coverage, 393 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#558 pr:#361 completed:2026-03-27
- [x] t464 test(event-manager): improve Event_Manager test coverage (inc/managers/class-event-manager.php — 33% coverage, 240 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#559 pr:#566 completed:2026-03-27
- [x] t465 test(form-manager): improve Form_Manager test coverage (inc/managers/class-form-manager.php — 9.7% coverage, 251 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#560 pr:#362 completed:2026-03-27
- [x] t466 test(notes-manager): improve Notes_Manager test coverage (inc/managers/class-notes-manager.php — 9.9% coverage, 283 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#561 pr:#287 completed:2026-03-27
- [x] t467 test(sso): improve SSO test coverage (inc/sso/class-sso.php — 36.7% coverage, 210 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#570 pr:#584 completed:2026-03-28
- [x] t468 test(domain-mapping): write tests for Domain_Mapping (inc/class-domain-mapping.php — 13.8% coverage, 168 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#571 pr:#579 completed:2026-03-28
- [x] t469 test(membership-functions): improve membership function tests (inc/functions/membership.php — 28.3% coverage, 152 uncovered stmts) #testing #auto-dispatch ~2h ref:GH#572 pr:#577 completed:2026-03-28
- [x] t470 test(checkout-form-model): improve Checkout_Form model tests (inc/models/class-checkout-form.php — 65.7% coverage, 286 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#573 pr:#582 completed:2026-03-28
- [x] t471 test(mcp-abilities): improve MCP abilities trait tests (inc/apis/trait-mcp-abilities.php — 69.3% coverage, 162 uncovered stmts) #testing #auto-dispatch ~2h ref:GH#574 pr:#581 completed:2026-03-28
- [x] t472 test(rest-api): improve REST API trait tests (inc/apis/trait-rest-api.php — 31.6% coverage, 160 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#575 pr:#578 completed:2026-03-28

### Priority 3 — Admin Pages (0% coverage, UI-heavy but testable logic)

- [x] t473 test(admin): write unit tests for Membership_Edit_Admin_Page (inc/admin-pages/class-membership-edit-admin-page.php — 5% coverage, 1042 uncovered stmts) #testing #auto-dispatch ~6h ref:GH#626 pr:#634 completed:2026-03-28
- [x] t474 test(admin): write unit tests for Payment_Edit_Admin_Page (inc/admin-pages/class-payment-edit-admin-page.php — 0% coverage, 913 uncovered stmts) #testing ~5h pr:#632 completed:2026-03-28
- [x] t475 test(admin): write unit tests for Checkout_Form_Edit_Admin_Page (inc/admin-pages/class-checkout-form-edit-admin-page.php — 0% coverage, 901 uncovered stmts) #testing ~5h ref:GH#630 pr:#637 completed:2026-03-28
- [x] t476 test(admin): write unit tests for Product_Edit_Admin_Page (inc/admin-pages/class-product-edit-admin-page.php — 0% coverage, 869 uncovered stmts) #testing ~5h pr:#633 completed:2026-03-28
- [x] t477 test(admin): write unit tests for Customer_Edit_Admin_Page (inc/admin-pages/class-customer-edit-admin-page.php — 0% coverage, 784 uncovered stmts) #testing ~5h pr:#622 completed:2026-03-28
- [x] t478 test(admin): write unit tests for Discount_Code_Edit_Admin_Page (inc/admin-pages/class-discount-code-edit-admin-page.php — 1.5% coverage, 526 uncovered stmts) #testing ~4h pr:#618 completed:2026-03-28
- [x] t479 test(admin): write unit tests for Edit_Admin_Page base class (inc/admin-pages/class-edit-admin-page.php — 2.8% coverage, 375 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#576 pr:#580 completed:2026-03-28

### Priority 4 — Infrastructure & Integration Code

- [x] t480 test(migrator): write unit tests for Migrator (inc/installers/class-migrator.php — 0% coverage, 1057 uncovered stmts) #testing ~6h pr:#620 completed:2026-03-28
- [x] t481 test(debug): improve Debug test coverage (inc/debug/class-debug.php — 24.3% coverage, 383 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#585 pr:#593 completed:2026-03-28
- [x] t482 test(wp-ultimo): improve WP_Ultimo main class tests (inc/class-wp-ultimo.php — 5.7% coverage, 394 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#586 pr:#592 completed:2026-03-28
- [x] t483 test(ajax): write unit tests for Ajax class (inc/class-ajax.php — 1.8% coverage, 213 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#587 pr:#596 completed:2026-03-28
- [x] t484 test(api): improve API class test coverage (inc/class-api.php — 6.6% coverage, 199 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#588 pr:#591 completed:2026-03-28
- [x] t485 test(host-providers): write tests for Cloudflare host provider (inc/integrations/host-providers/class-cloudflare-host-provider.php — 19.2% coverage, 307 uncovered stmts) #testing ~4h pr:#619 completed:2026-03-28
- [x] t486 test(host-providers): write tests for cPanel host provider (inc/integrations/host-providers/class-cpanel-host-provider.php — 0% coverage, 279 uncovered stmts) #testing ~3h pr:#621 completed:2026-03-28
- [x] t487 test(host-providers): write tests for Hestia host provider (inc/integrations/host-providers/class-hestia-host-provider.php — 5.2% coverage, 254 uncovered stmts) #testing ~3h pr:#617 completed:2026-03-28
- [x] t488 test(host-providers): write tests for Base_Host_Provider (inc/integrations/host-providers/class-base-host-provider.php — 16.9% coverage, 148 uncovered stmts) #testing #auto-dispatch ~2h ref:GH#589 pr:#594 completed:2026-03-28
- [x] t489 test(default-content): write tests for Default_Content_Installer (inc/installers/class-default-content-installer.php — 0.5% coverage, 206 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#590 pr:#595 completed:2026-03-28

### Priority 5 — List Tables & Signup Fields

- [x] t490 test(list-tables): write unit tests for Base_List_Table (inc/list-tables/class-base-list-table.php — 4.4% coverage, 461 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#600 pr:#609 completed:2026-03-28
- [x] t491 test(signup-fields): write tests for Base_Signup_Field (inc/checkout/signup-fields/class-base-signup-field.php — 16% coverage, 199 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#601 pr:#608 completed:2026-03-28
- [x] t492 test(signup-fields): write tests for Template_Selection field (inc/checkout/signup-fields/class-signup-field-template-selection.php — 1.1% coverage, 174 uncovered stmts) #testing #auto-dispatch ~2h ref:GH#602 pr:#607 completed:2026-03-28
- [x] t493 test(signup-fields): write tests for Period_Selection field (inc/checkout/signup-fields/class-signup-field-period-selection.php — 1.3% coverage, 156 uncovered stmts) #testing #auto-dispatch ~2h ref:GH#603 pr:#606 completed:2026-03-28

### Priority 6 — API Schemas (0% coverage, data-validation code)

- [x] t494 test(api-schemas): write tests for API schema validation files (inc/apis/schemas/ — 0% coverage across 24 files, 2436 uncovered stmts) #testing #auto-dispatch ~6h ref:GH#604 pr:#605 completed:2026-03-28

### Priority 7 — More Admin Pages (0% coverage)

- [x] t496 test(admin): write unit tests for Domain_Edit_Admin_Page (inc/admin-pages/class-domain-edit-admin-page.php — 0% coverage, ~932 lines) #testing #auto-dispatch ~6h ref:GH#638 pr:#653 completed:2026-03-28
- [x] t497 test(admin): write unit tests for Email_Edit_Admin_Page (inc/admin-pages/class-email-edit-admin-page.php — 0% coverage, ~576 lines) #testing #auto-dispatch ~4h ref:GH#639 pr:#650 completed:2026-03-28
- [x] t498 test(admin): write unit tests for Broadcast_Edit_Admin_Page (inc/admin-pages/class-broadcast-edit-admin-page.php — 0% coverage, ~513 lines) #testing #auto-dispatch ~4h ref:GH#640 pr:#655 completed:2026-03-28
- [x] t499 test(admin): write unit tests for Base_Admin_Page (inc/admin-pages/class-base-admin-page.php — 0% coverage, ~800 lines) #testing #auto-dispatch ~5h ref:GH#641 pr:#651 completed:2026-03-28
- [x] t500 test(admin): write unit tests for Addons_Admin_Page (inc/admin-pages/class-addons-admin-page.php — 0% coverage, ~513 lines) #testing #auto-dispatch ~4h ref:GH#642 pr:#667 completed:2026-03-28
- [x] t501 test(admin): write unit tests for Dashboard_Admin_Page (inc/admin-pages/class-dashboard-admin-page.php — 0% coverage) #testing #auto-dispatch ~4h ref:GH#643 pr:#657 completed:2026-03-28
- [x] t502 test(admin): write unit tests for Customer_List_Admin_Page (inc/admin-pages/class-customer-list-admin-page.php — 0% coverage) #testing #auto-dispatch ~3h ref:GH#644 pr:#663 completed:2026-03-28
- [x] t503 test(admin): write unit tests for Discount_Code_List_Admin_Page (inc/admin-pages/class-discount-code-list-admin-page.php — 0% coverage) #testing #auto-dispatch ~3h ref:GH#645 pr:#454 verified:2026-03-28
- [x] t504 test(admin): write unit tests for Domain_List_Admin_Page (inc/admin-pages/class-domain-list-admin-page.php — 0% coverage) #testing #auto-dispatch ~3h ref:GH#646 pr:#654 completed:2026-03-28
- [x] t505 test(admin): write unit tests for Email_List_Admin_Page (inc/admin-pages/class-email-list-admin-page.php — 0% coverage) #testing #auto-dispatch ~3h ref:GH#647 pr:#656 completed:2026-03-28

### Priority 8 — Remaining Admin Pages (0% coverage)

- [x] t506 test(admin): write unit tests for Settings_Admin_Page (inc/admin-pages/class-settings-admin-page.php — 0% coverage, ~980 lines) #testing #auto-dispatch ~6h ref:GH#658 pr:#664 completed:2026-03-28
- [x] t507 test(admin): write unit tests for Setup_Wizard_Admin_Page (inc/admin-pages/class-setup-wizard-admin-page.php — 0% coverage, ~947 lines) #testing #auto-dispatch ~6h ref:GH#659 pr:#665 completed:2026-03-28
- [x] t508 test(admin): write unit tests for System_Info_Admin_Page (inc/admin-pages/class-system-info-admin-page.php — 0% coverage, ~778 lines) #testing #auto-dispatch ~5h ref:GH#660 pr:#669 completed:2026-03-28
- [x] t509 test(admin): write unit tests for Email_Template_Customize_Admin_Page (inc/admin-pages/class-email-template-customize-admin-page.php — 0% coverage, ~692 lines) #testing #auto-dispatch ~4h ref:GH#661 pr:#668 completed:2026-03-28
- [x] t510 test(admin): write unit tests for Template_Library_Admin_Page (inc/admin-pages/class-template-library-admin-page.php — 0% coverage, ~673 lines) #testing #auto-dispatch ~4h ref:GH#662 pr:#666 completed:2026-03-28

### Priority 9 — Remaining Admin Pages & List Tables (0% coverage)

- [x] t511 test(admin): write unit tests for Multisite_Setup_Admin_Page (inc/admin-pages/class-multisite-setup-admin-page.php — 0% coverage, ~520 lines) #testing #auto-dispatch ~4h ref:GH#672 pr:#682 completed:2026-03-28
- [x] t512 test(admin): write unit tests for Wizard_Admin_Page (inc/admin-pages/class-wizard-admin-page.php — 0% coverage, ~445 lines) #testing #auto-dispatch ~3h ref:GH#673 pr:#686 completed:2026-03-28
- [x] t513 test(admin): write unit tests for Invoice_Template_Customize_Admin_Page (inc/admin-pages/class-invoice-template-customize-admin-page.php — 0% coverage, ~435 lines) #testing #auto-dispatch ~3h ref:GH#674 pr:#680 completed:2026-03-28
- [x] t514 test(admin): write unit tests for Hosting_Integration_Wizard_Admin_Page (inc/admin-pages/class-hosting-integration-wizard-admin-page.php — 0% coverage, ~420 lines) #testing #auto-dispatch ~3h ref:GH#675 pr:#687 completed:2026-03-28
- [x] t515 test(admin): write unit tests for External_Cron_Admin_Page (inc/admin-pages/class-external-cron-admin-page.php — 0% coverage, ~417 lines) #testing #auto-dispatch ~3h ref:GH#676 pr:#683 completed:2026-03-28
- [x] t516 test(list-tables): write unit tests for remaining list table classes (15 files, 0% coverage) #testing #auto-dispatch ~6h ref:GH#677 pr:#684 completed:2026-03-28
- [x] t517 test(admin): write unit tests for View_Logs and Tax_Rates admin pages (0% coverage) #testing #auto-dispatch ~3h ref:GH#678 pr:#681 completed:2026-03-28
- [x] t518 test(admin): write unit tests for Template_Previewer, Checkout_Form_List, and Event_View admin pages (0% coverage) #testing #auto-dispatch ~3h ref:GH#679 pr:#685 completed:2026-03-28

### Fix: Test suite exits early at 56%

- [x] t495 fix(tests): Form_Manager_Test::test_handle_model_delete_form_requires_confirmation calls exit() killing test runner at test 2533/4411 #bug #auto-dispatch ~1h ref:GH#562 pr:#563 completed:2026-03-27