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

## Frontend customer accounts

Keep account-wide membership, billing and invoices separate from site-specific
summary and limits. Account Summary must offer a sign-in link when logged out,
an owner-scoped site chooser before site selection, and a neutral no-sites state
instead of silently rendering nothing. Do not silently select the first site:
customers may own several. Once a site is selected, omit self-referential
"See More" links and show remaining trial days from the actual membership date.
Use the existing stacked widget styles on desktop and mobile, with native links
and wrapping site names; do not add fixed-width panels or a second design system.
Frontend account pages belong on the main site; customer-site admin Account
screens remain an alternative interface to the same membership.

On combined pages, Manage must visibly land on the selected site's controls,
using a keyboard-focusable native fragment target (Account Summary is the
fallback). Your Sites clears selected-site context and returns to the page
permalink. Successful frontend deletion returns to the account landing page,
unless an explicit destination is configured; never retain a deleted-site URL.
Sites without a template initialize the selector to numeric zero so their first
selection exposes the same destructive-action confirmation as template changes.

Payment-method changes must return from Stripe Billing Portal to the originating
account page, using a validated local destination. Show the subscription's card
override when present; otherwise show the customer's default payment method,
including after the portal clears an override while replacing the default card.

Native account blocks share shortcode renderers, settings and ownership checks.
Load widget preview styles inside the block editor canvas, including iframe
editors. Numeric controls save integers and toggle defaults use booleans. Group
and Columns layouts must preserve nested element attributes during early setup,
especially the Checkout form slug. Editor previews use sample data; verify real
customer actions on the published page rather than treating a preview as proof.
