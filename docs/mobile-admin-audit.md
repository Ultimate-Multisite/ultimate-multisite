# Mobile admin audit

## Scope and method

Audited the available Ultimate Multisite network-admin interface using an isolated
Chromium session, real development data, viewport screenshots, DOM dimensions,
keyboard interaction, and live AJAX requests. Phone viewports were 320 × 740 and
390 × 844; desktop comparison was 1280 × 900. Dialog scrolling was also checked
at 740 × 390. This is viewport emulation, not physical iOS/Android testing.

Verification used a separate, temporary local preview loading the worktree's PHP
and committed minified assets. The shared WordPress install's active plugin
symlink and configuration were not changed. Preview-only origin and asset-routing
errors were diagnosed separately from product defects.

### Pages inspected

- All main plugin menu pages: Dashboard, Checkout Forms, Products, Memberships,
  Payments, Customers, Sites, Domains, Discount Codes, Broadcasts, Settings,
  Events, Webhooks, and Add-ons.
- Every available settings section: Tools, Whitelabel, Taxes, General, Login &
  Registration, Memberships, Sites, API & Webhooks, Payments, Emails, Domain
  Mapping, Single Sign-On, Integrations, Import/Export, and Other Options.
- Checkout form editor; new and existing products; customer, membership, payment,
  site, and domain detail pages; new discount code; missing-customer fallback;
  System Emails and View Logs.
- Utility pages: System Info, Jobs, Available Shortcodes, Tax Rates, and Template
  Placeholders. System Info was measured without retaining a screenshot of its
  potentially sensitive diagnostic values.
- Customer, product, and site list/grid switching; customer row expansion;
  live-search empty results and reload recovery; settings autocomplete; customer
  creation/invitation dialogs without submitting; keyboard focus and dismissal.

## Confirmed defects and corrections

| Finding | Correction |
| --- | --- |
| Customer list collapsed to avatars instead of names; site/product tables had the same decorative-first-column problem. | The default primary column skips icon-only/empty headings. WordPress's primary-column filter remains available. Names and URLs stay visible on mobile. |
| Status filters, search, and view switches competed for one row. At 390px, search shrank to roughly 34px on Sites, 55px on Payments, 66px on Memberships, and 109px on Customers. | Mobile filters wrap, search occupies its own full-width row, and view switches have at least 44px touch targets. |
| The search container was 32px high around a 40px mobile input. | Remove the fixed container height; mobile search is at least 44px high. |
| Duplicate input IDs broke the search label; its described-by target did not exist. | Use table-specific IDs and matching labels/descriptions. |
| Empty filtered pages lost their AJAX table shell after reload, preventing search/filter recovery. | Keep controls enabled and render the table/grid shell for an active search or view filter. Preserve the original creation empty state for an unfiltered empty dataset. |
| Row actions required hover, and two overlapping expansion buttons appeared in primary cells. | Show mobile actions in normal flow and hide the extra toggle emitted alongside explicit row actions, leaving core's primary-column toggle. |
| Column-heading uppercase/small-font styling also affected semantic row headers. | Apply heading typography only to table head/footer cells and keep mobile row content readable. |
| Embedded detail tables widened phone pages: customer about 538px, membership 860px, payment 718px, domain 430px, and site 393px at a 320px viewport. | Constrain responsive cells, allow flex content to shrink, wrap title/status content, and retain accessible navigation links. Rechecked detail pages fit the viewport. |
| Modal content was as tall as the whole viewport in addition to its title bar: its bottom reached 781px in a 740px viewport. | Use a full-height flex dialog and a shrinking, scrollable content region. Content now ends at 740px, or 390px in the landscape check. |
| Settings search's max-content dropdown reached about 359px at a 320px viewport. | Constrain it to the input width and wrap result labels; measured 283px after the fix. |
| Add-ons' connection button reached about 372px at a 320px viewport. | Allow the action text to wrap within its container. |
| System Info values and Jobs hook names widened pages to about 543px and 446px. | Wrap diagnostic values in a fixed-layout table and allow long hook names to break. Both pages now fit 320px and 390px viewports. |
| The four-column shortcode reference widened the page to about 547px. | Wrap headings and keep parameter columns in labelled, keyboard-scrollable regions. At 320px, the region is 281px wide with 512px of table content; the page itself fits. Desktop tables remain full-width without local scrolling. |

## Reproduction and verification

1. At 320px and 390px, visit each list page. Confirm that the whole search
   placeholder is visible, filters wrap, and controls do not overlap the table.
2. Switch Customers, Products, and Sites to list mode. Confirm that names/URLs
   identify rows before expansion. Expand a customer row and check that its
   metadata remains accessible. Only one expansion button should be visible.
3. Search Customers for a non-matching string, reload the resulting URL, then
   clear search. The table should repopulate without another page reload. Also
   check an empty status view and return to All.
4. Inspect populated customer/membership/payment/site/domain detail widgets,
   including long names, addresses, and event titles. Content must not widen the
   page. Desktop widgets should retain their existing arrangement.
5. Search Settings for `site`. Check the dropdown width and keyboard selection.
   Open the Add Customer dialog and its Invite New tab, then dismiss without
   saving. In landscape, scroll to the final form control and close the dialog.
6. Inspect Add-ons, System Info, Jobs, and Available Shortcodes at 320px. For the
   shortcode reference, focus a parameter region and scroll it horizontally;
   the surrounding page must remain stationary.

Scoped PHPCS, PHPStan, PHP syntax checks, Stylelint, and the CSS minification
command passed. PHPCS retains the pre-existing warning about the dormant
`if (false)` advanced-filter block. A pre-existing Yoda-condition error in the
touched base table was corrected without changing its behavior so the existing
commit hook could pass.

The existing table PHPUnit filter could not start because the WordPress test
library is not installed locally. No new test infrastructure was introduced.

## Coverage limits

External Cron and Template Library returned WordPress access/unavailable pages in
this development setup; their internal screens were not audited and their access
guards were not changed. External provider setup, purchases, destructive actions,
and form submissions were not executed. No claim is made about every third-party
admin theme, installed add-on, physical device, or browser engine.
