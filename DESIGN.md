# Ultimate Multisite design notes

## Checkout plan selection

The default checkout pricing-table list presents each selectable plan with a
visible native radio control. The focused control keeps the existing card focus
ring, while the selected control retains the blue card border. Contact-us cards
use a non-submitting button so they remain keyboard operable without becoming a
checkout selection. After a plan refresh, focus returns to the selected radio
only when the visitor has not moved it elsewhere during the request.

## Setup installation errors

The setup wizard displays database installation failures in the existing status
cell, including the affected table and the database server's error text. Details
are HTML-escaped and restricted to the existing network-administrator installer
endpoint; the configured database password is redacted and SQL queries are not
added to the response. If the database supplies no error, explain that explicitly
and suggest asking the hosting provider to check permissions and server logs.
Keep the existing retry button and help link available. No debug configuration
is required to view a database error.
