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
