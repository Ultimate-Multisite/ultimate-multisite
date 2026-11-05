# Ultimate Multisite design notes

## Checkout completion

Preserve legacy thank-you markup, Vue bindings, scripts and add-on callbacks by
default. Sites may explicitly opt into the additive provisioning enhancement;
existing checkouts do not require add-on upgrades or new nonce arguments.
In opt-in mode, keep one stable, owner-scoped screen while background provisioning
continues. Update site readiness and links in place without document reloads or
fabricated progress percentages. The enhanced screen stays on thank-you by default;
integrations may supply a validated ready-site handoff. A polite live status,
bounded waiting, keyboard-accessible recovery and sign-in guidance must remain
usable on narrow viewports. Recovery checks the saved order, never repurchases it.
Do not globally dequeue other widgets' assets: completion uses thank-you scripts
instead of dispatching checkout-form dependencies.

## Checkout plan selection

The default checkout pricing-table list presents each selectable plan with a
visible native radio control. The focused control keeps the existing card focus
ring, while the selected control retains the blue card border. Contact-us cards
use a non-submitting button so they remain keyboard operable without becoming a
checkout selection. After a plan refresh, focus returns to the selected radio
only when the visitor has not moved it elsewhere during the request.

## Page selection in element settings

Page choices use asynchronous search, never a dropdown preloaded with every page.
The block inspector uses WordPress's keyboard-accessible combobox; shortcode and
customization forms reuse the existing Selectize model selector. Keep results
bounded to at most 20 published pages on the current site, debounce typing, and
hydrate only the saved selection. Preserve the zero-valued Current Page/Default
choice and existing saved IDs, including when a lookup fails. Show search errors
without silently changing the selection. Controls follow their container width
in both the narrow editor sidebar and mobile forms; do not add fixed widths.
Editor-only settings are resolved on the editor-assets hook, not on unrelated
admin requests. See the block-editor widget manager and Base_Element page-search
field helper for the shared implementation.
## Domain DNS lookup

DNS lookup without a configured integration is a normal read-only state, not a
warning. Use a neutral gray information strip with a secondary configuration
link; reserve warning/error styling for actual failures.

Show returned NS records in a separate nameserver summary, using escaped text
and hiding the entire summary when none are returned. Nameservers may help identify
the DNS host, but do not infer a provider name or imply an integration is enabled.

### Responsive behaviour

Keep the existing stacked widget layout on desktop and mobile. List nameservers
on separate lines and allow long hostnames to wrap without truncation. Hide the
summary during loading or lookup errors; preserve the existing record table and
provider-managed editing controls.

## Mobile admin lists and detail widgets

Use WordPress's 782px mobile admin breakpoint. Keep status filters wrapping in
normal flow, give live search its own full-width row, and keep view-switch controls
at least 44px square. Search remains usable when the current query has no results;
each input has a unique label target and a description of live filtering.

List tables retain WordPress's expandable mobile rows, but their primary column
must identify the record (customer/product name or site URL), never just an avatar
or thumbnail. Hide the preceding decorative column on mobile and show row actions
without requiring hover. Do not apply uppercase column-heading typography to row
headers. Long identifiers and addresses wrap rather than widen the page.

Embedded detail rows are not desktop data grids: constrain their single responsive
cell to the widget width, allow its flex content to shrink, and wrap the title and
status onto separate lines when needed. Preserve the desktop layout and existing
navigation. See `views/base/filter.php`, `views/base/responsive-table-row.php`,
`inc/list-tables/class-base-list-table.php`, and the mobile block in
`assets/css/admin.css`.

Constrain the settings search dropdown to its input width on mobile; unlike the
desktop max-content menu, long result titles must wrap. Diagnostic tables and job
hooks wrap long values. The four-column shortcode reference instead keeps its
column relationships in a labelled, keyboard-scrollable region, never a
page-wide horizontal scrollbar (`views/shortcodes/shortcodes.php`). Full-screen
mobile dialogs reserve space for their title bar and scroll only the remaining
content area so the final form controls stay reachable.
