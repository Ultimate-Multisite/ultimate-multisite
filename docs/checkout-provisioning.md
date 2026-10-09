# Reload-free checkout provisioning

Existing checkouts retain their original thank-you JavaScript, Vue bindings,
markup and add-on callback contracts by default. No add-on code changes or new
nonce arguments are required for legitimate existing authenticated callers.

Enable the additive flow through explicit site/integration opt-in:

```php
add_filter('wu_checkout_provisioning_enabled', '__return_true');
```

The enhanced thank-you screen checks provisioning without refreshing the document.
It updates ready-site links in place and stays on the thank-you page by default.
The normal checkout POST, gateway verification, customer email-verification policy
and background publisher remain unchanged.

## Protected status contract

`wp_ajax_wu_checkout_provisioning_status` requires an authenticated membership owner
or network administrator and `_ajax_nonce` generated with:

```php
wp_create_nonce('wu_check_pending_site_created:' . $membership->get_hash());
```

Send `membership_hash` and, for checkout completion, `payment_hash` in a same-origin
POST. A supplied payment must belong to the same membership/customer. Authorization
is separate from legacy add-on status callbacks. No anonymous handler is added.
Responses are private/no-store and retain the legacy `publish_status` field:

| `state` | Meaning |
| --- | --- |
| `pending` | Waiting for site publication; absence of pending metadata is not readiness |
| `cloning` | Site exists but cloning or the storage-provider readiness gate is incomplete |
| `payment_pending` | Payment or membership entitlement is not confirmed |
| `verification_pending` | The existing customer email-verification gate is unmet |
| `failed` | Membership/payment is terminal or the native clone failed |
| `ready` | Published sites passed native clone/storage readiness |

Denied requests return HTTP 403 with `state: forbidden`. Ready responses include
`sites` with `id`, `title`, `url`, `admin_url`, and `visit_url`, plus an optional
`redirect_url`. Status polling does not verify payments, activate memberships,
enqueue jobs or publish sites. Stale publication recovery runs in the existing
background publisher instead of the polling request.

## Integrations

- `wu_checkout_provisioning_sites($sites, $membership, $payment)` allows providers
  with other site topologies to supply ready-site models. Return actual
  `WP_Ultimo\Models\Site` objects and preserve the membership's ownership boundary.
  Their active URLs must describe the origins actually served by the provider.
- `wu_checkout_ready_redirect_url($url, $membership, $payment, $sites)` is empty by
  default. An integration may mint its own short-lived signed handoff only after
  readiness. Core rejects non-HTTP(S), credential-bearing, or different-origin URLs.
  Scheme, hostname and port must match a supplied ready site's active URL.
- The browser dispatches `wu:checkout-provisioning-status` from `#wu-sites` with
  the status payload. `window.wu_sites.site_ready`, `creating`, `clone_failed` and
  Vue `$watch` remain available for existing consumers. Integration-owned reload
  listeners should migrate to the status event or server-side destination hook.

Legacy `wu_check_pending_site_created` callers continue sending just
`membership_hash`, without a new nonce, and receiving exactly `publish_status`.
The old empty-site/completion semantics are retained for network adapters, and
priority-one sovereign short-circuit callbacks remain supported. An owner/network
administrator check runs at priority zero; unauthorized callers are rejected, but
legitimate callers are not required to adopt the richer contract. The legacy
endpoint does not return new signed handoff/link fields.

Existing Multinetwork pollers keep their separate endpoint and existing host
overlays keep their Vue watcher/reload behaviour. They may progressively adopt the
new filters and opt-in controller, but are not forced to migrate. Customer-specific
products, fields, email bypasses, template
profiles, AI assistant launch hints and SSO token implementations do not belong in
this generic status layer.

## Browser reliability

In opt-in mode requests are serial, abort after ten seconds, back off to five seconds, and stop
after three minutes or five consecutive transport/response failures. “Check again”
checks the saved order without resubmission; expired access offers sign-in. The
enhanced controller does not kick cron; legacy cron kicks/reloads remain unchanged.
A functioning background worker is required. Opt-in completion routes dispatch
thank-you assets rather than checkout-form dependencies;
there is no global dequeue that could remove another widget's scripts.

## Verification

```bash
vendor/bin/phpunit --filter 'Checkout_Provisioning_Test|Membership_Manager_Test|Thank_You_Element_Test|Checkout_Element_Test'
vendor/bin/phpcs inc/managers/class-membership-manager.php inc/ui/class-thank-you-element.php inc/ui/class-checkout-element.php views/dashboard-widgets/thank-you.php tests/WP_Ultimo/Managers/Checkout_Provisioning_Test.php
pnpm exec eslint assets/js/checkout-provisioning.js
```

For local browser QA, use a synthetic database with contained email, no live
gateway/provider credentials, and a running native background publisher. Test
legacy checkouts with unchanged add-ons, then opt-in completion and handoff. Verify
nonce-less legacy calls and original response keys, Vue fields, existing overrides
and browser callbacks. In opt-in mode assert
one purchase POST, one completion document, serial polling, in-place ready links,
no automatic polling reloads, and one payment/membership/site per signup. Exercise
failure, payment/email waits, connection loss, timeout and manual recovery as well
as expired-session/nonce and cross-customer access. Verify the actual loaded PHP,
template and JS paths instead of assuming a development overlay took precedence.

Minified assets remain release-build outputs; feature branches change source JS.
