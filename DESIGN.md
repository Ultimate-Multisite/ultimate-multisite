# Ultimate Multisite design notes

## Checkout plan selection

The default checkout pricing-table list presents each selectable plan with a
visible native radio control. The focused control keeps the existing card focus
ring, while the selected control retains the blue card border. Contact-us cards
use a non-submitting button so they remain keyboard operable without becoming a
checkout selection. After a plan refresh, focus returns to the selected radio
only when the visitor has not moved it elsewhere during the request.

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
