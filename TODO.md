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
- [ ] t454 test(cart): improve Cart test coverage (inc/checkout/class-cart.php — 61.1% coverage, 382 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#556
- [ ] t455 test(stripe): write unit tests for Base_Stripe_Gateway (inc/gateways/class-base-stripe-gateway.php — 28.6% coverage, 1093 uncovered stmts) #testing ~8h
- [ ] t456 test(stripe): improve Stripe_Gateway test coverage (inc/gateways/class-stripe-gateway.php — 55% coverage, 200 uncovered stmts) #testing ~4h
- [ ] t457 test(paypal): write unit tests for PayPal_Gateway (inc/gateways/class-paypal-gateway.php — 1.1% coverage, 783 uncovered stmts) #testing ~6h
- [ ] t458 test(paypal): improve PayPal_REST_Gateway test coverage (inc/gateways/class-paypal-rest-gateway.php — 29.8% coverage, 683 uncovered stmts) #testing ~5h
- [x] t459 test(paypal): write tests for PayPal_OAuth_Handler (inc/gateways/class-paypal-oauth-handler.php — 15% coverage, 238 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#549 pr:#554 completed:2026-03-27
- [x] t460 test(gateway): improve Gateway_Manager test coverage (inc/managers/class-gateway-manager.php — 34.7% coverage, 177 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#550 pr:#552 completed:2026-03-27
- [x] t461 test(checkout-pages): improve Checkout_Pages test coverage (inc/checkout/class-checkout-pages.php — 33.7% coverage, 203 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#551 pr:#553 completed:2026-03-27

### Priority 2 — Core Domain Logic (moderate coverage gaps)

- [x] t462 test(site-manager): improve Site_Manager test coverage (inc/managers/class-site-manager.php — 23.5% coverage, 433 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#557 pr:#568 completed:2026-03-27
- [x] t463 test(domain-manager): improve DNS_Record_Manager test coverage (inc/managers/class-dns-record-manager.php — 14.4% coverage, 393 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#558 pr:#361 completed:2026-03-27
- [x] t464 test(event-manager): improve Event_Manager test coverage (inc/managers/class-event-manager.php — 33% coverage, 240 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#559 pr:#566 completed:2026-03-27
- [x] t465 test(form-manager): improve Form_Manager test coverage (inc/managers/class-form-manager.php — 9.7% coverage, 251 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#560 pr:#362 completed:2026-03-27
- [x] t466 test(notes-manager): improve Notes_Manager test coverage (inc/managers/class-notes-manager.php — 9.9% coverage, 283 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#561 pr:#287 completed:2026-03-27
- [ ] t467 test(sso): improve SSO test coverage (inc/sso/class-sso.php — 36.7% coverage, 210 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#570
- [ ] t468 test(domain-mapping): write tests for Domain_Mapping (inc/class-domain-mapping.php — 13.8% coverage, 168 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#571
- [ ] t469 test(membership-functions): improve membership function tests (inc/functions/membership.php — 28.3% coverage, 152 uncovered stmts) #testing #auto-dispatch ~2h ref:GH#572
- [ ] t470 test(checkout-form-model): improve Checkout_Form model tests (inc/models/class-checkout-form.php — 65.7% coverage, 286 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#573
- [ ] t471 test(mcp-abilities): improve MCP abilities trait tests (inc/apis/trait-mcp-abilities.php — 69.3% coverage, 162 uncovered stmts) #testing #auto-dispatch ~2h ref:GH#574
- [ ] t472 test(rest-api): improve REST API trait tests (inc/apis/trait-rest-api.php — 31.6% coverage, 160 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#575

### Priority 3 — Admin Pages (0% coverage, UI-heavy but testable logic)

- [ ] t473 test(admin): write unit tests for Membership_Edit_Admin_Page (inc/admin-pages/class-membership-edit-admin-page.php — 5% coverage, 1042 uncovered stmts) #testing ~6h
- [ ] t474 test(admin): write unit tests for Payment_Edit_Admin_Page (inc/admin-pages/class-payment-edit-admin-page.php — 0% coverage, 913 uncovered stmts) #testing ~5h
- [ ] t475 test(admin): write unit tests for Checkout_Form_Edit_Admin_Page (inc/admin-pages/class-checkout-form-edit-admin-page.php — 0% coverage, 901 uncovered stmts) #testing ~5h
- [ ] t476 test(admin): write unit tests for Product_Edit_Admin_Page (inc/admin-pages/class-product-edit-admin-page.php — 0% coverage, 869 uncovered stmts) #testing ~5h
- [ ] t477 test(admin): write unit tests for Customer_Edit_Admin_Page (inc/admin-pages/class-customer-edit-admin-page.php — 0% coverage, 784 uncovered stmts) #testing ~5h
- [ ] t478 test(admin): write unit tests for Discount_Code_Edit_Admin_Page (inc/admin-pages/class-discount-code-edit-admin-page.php — 1.5% coverage, 526 uncovered stmts) #testing ~4h
- [ ] t479 test(admin): write unit tests for Edit_Admin_Page base class (inc/admin-pages/class-edit-admin-page.php — 2.8% coverage, 375 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#576

### Priority 4 — Infrastructure & Integration Code

- [ ] t480 test(migrator): write unit tests for Migrator (inc/installers/class-migrator.php — 0% coverage, 1057 uncovered stmts) #testing ~6h
- [ ] t481 test(debug): improve Debug test coverage (inc/debug/class-debug.php — 24.3% coverage, 383 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#585
- [ ] t482 test(wp-ultimo): improve WP_Ultimo main class tests (inc/class-wp-ultimo.php — 5.7% coverage, 394 uncovered stmts) #testing #auto-dispatch ~4h ref:GH#586
- [ ] t483 test(ajax): write unit tests for Ajax class (inc/class-ajax.php — 1.8% coverage, 213 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#587
- [ ] t484 test(api): improve API class test coverage (inc/class-api.php — 6.6% coverage, 199 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#588
- [ ] t485 test(host-providers): write tests for Cloudflare host provider (inc/integrations/host-providers/class-cloudflare-host-provider.php — 19.2% coverage, 307 uncovered stmts) #testing ~4h
- [ ] t486 test(host-providers): write tests for cPanel host provider (inc/integrations/host-providers/class-cpanel-host-provider.php — 0% coverage, 279 uncovered stmts) #testing ~3h
- [ ] t487 test(host-providers): write tests for Hestia host provider (inc/integrations/host-providers/class-hestia-host-provider.php — 5.2% coverage, 254 uncovered stmts) #testing ~3h
- [ ] t488 test(host-providers): write tests for Base_Host_Provider (inc/integrations/host-providers/class-base-host-provider.php — 16.9% coverage, 148 uncovered stmts) #testing #auto-dispatch ~2h ref:GH#589
- [ ] t489 test(default-content): write tests for Default_Content_Installer (inc/installers/class-default-content-installer.php — 0.5% coverage, 206 uncovered stmts) #testing #auto-dispatch ~3h ref:GH#590

### Priority 5 — List Tables & Signup Fields

- [ ] t490 test(list-tables): write unit tests for Base_List_Table (inc/list-tables/class-base-list-table.php — 4.4% coverage, 461 uncovered stmts) #testing #auto-dispatch ~4h
- [ ] t491 test(signup-fields): write tests for Base_Signup_Field (inc/checkout/signup-fields/class-base-signup-field.php — 16% coverage, 199 uncovered stmts) #testing #auto-dispatch ~3h
- [ ] t492 test(signup-fields): write tests for Template_Selection field (inc/checkout/signup-fields/class-signup-field-template-selection.php — 1.1% coverage, 174 uncovered stmts) #testing #auto-dispatch ~2h
- [ ] t493 test(signup-fields): write tests for Period_Selection field (inc/checkout/signup-fields/class-signup-field-period-selection.php — 1.3% coverage, 156 uncovered stmts) #testing #auto-dispatch ~2h

### Priority 6 — API Schemas (0% coverage, data-validation code)

- [ ] t494 test(api-schemas): write tests for API schema validation files (inc/apis/schemas/ — 0% coverage across 24 files, 2436 uncovered stmts) #testing #auto-dispatch ~6h

### Fix: Test suite exits early at 56%

- [x] t495 fix(tests): Form_Manager_Test::test_handle_model_delete_form_requires_confirmation calls exit() killing test runner at test 2533/4411 #bug #auto-dispatch ~1h ref:GH#562 pr:#563 completed:2026-03-27