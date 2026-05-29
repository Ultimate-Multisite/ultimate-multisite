# Regression guard for Ultimate Multisite — bugs we found, fixes, and a CI guard

Hi David. This is a summary of the production bugs we hit on kursopro.com with the
three Ultimate Multisite plugins, the root cause of each, the PR that fixes it, and a
small **CI guard** so a future release does not silently bring any of them back. Each
bug below comes from a **real customer incident** with logs, not a guess.

**Good news first:** we re-checked your `main` branches and **all five fixes are now
present upstream.** The guard below passes green on all three repos today. The point of
the guard is to keep it that way as you add new features.

Everything here is about **your** plugins (core / woocommerce / captcha). We keep a
one-line local patch alive only until a fix ships in a **tagged release** (a merge to
`main` is not enough — we install the release zip, which overwrites the file and
removes our patch). So the moment you cut a release with these, we drop our patches.

---

## The 5 root-cause bugs (all with real cases) — current status in `main`

### 1. Captcha — form submits before the token is ready  → registration blocked
- **Plugin:** `ultimate-multisite-captcha`
- **File:** `assets/js/providers/cap-token-resolver.js` → `waitForToken()`
- **Symptom:** Step 1 of the Vue registration showed *"Captcha verification is taking
  longer than expected"* and blocked the user. Login also failed with empty token.
- **Two chained bugs in 1.5.1:** (1) reads `widget.token` with no guard when `widget`
  is `null` → hard crash on Step 1; (2) invisible mode waits 30s for a `<cap-widget>`
  that the multi-step Vue form only mounts on the **payment step**, not Step 1.
- **Fix:** guard `(widget && widget.token)`; with no widget → `resolve()` immediately.
- **Real cases:** Lis, Ender (login), Eva (checkout).  **PR:** #130 / #134.
- **Status in `main`:** ✅ present (v1.6.0; prod runs 1.5.3 which already carries it).

### 2. WooCommerce addon — creates a 2nd subscription and cancels the active one
- **Plugin:** `ultimate-multisite-woocommerce`
- **File:** `inc/gateways/class-woocommerce-gateway.php` → subscription handling.
- **Symptom:** on a $0-trial double-order / mid-trial renewal, a 2nd WCS subscription
  is created and the **real active one is cancelled** → customer gets a false
  "subscription cancelled" email + DB garbage. The customer never cancelled anything.
- **Fix:** if the existing subscription is already `active`/`trialing`, cancel the
  **duplicate**, not the active one.
- **Real case:** Karoly.  **PR:** #94 / #96.
- **Status in `main`:** ✅ present (`has_status(['active','trialing'])` guard).

### 3. WooCommerce addon — paid renewal still suspends the site (★ most painful)
- **Plugin:** `ultimate-multisite-woocommerce` (root) + core (defense)
- **Symptom:** `on_order_completed` reads `subscription->next_payment` **before** WCS
  advances it on a renewal → `renew()` gets the OLD date → membership ends up
  on-hold/trialing with an already-past expiration → the site is suspended **even
  though the renewal was paid**.
- **Evidence:** `membership-428.log` → `New Expiration: 2026-05-26` (did not advance).
- **Fix:** recalculate `next_payment` on renewal before `renew()` (addon), plus a
  core-side defense so `renew()` rejects an expiration that does not advance.
- **Real cases:** Elizabeth (MEM #428), Ikena (MEM #466).  **PR:** #99 (addon) + #1306 (core).
- **Status in `main`:** ✅ both present (addon `calculate_date('next_payment')` on
  renewal + core "non-advancing expiration" guard from #1306).

### 4. Core — pending site stuck in step 2 / infinite overlay
- **Plugin:** `ultimate-multisite`
- **Symptom:** a blind guard `if ($is_publishing) return` left the publishing flag
  stuck when the $0-trial double-order tripped it → site stuck / overlay never ends.
- **Fix:** `is_publishing_stale()` so a stale flag does not block publishing
  (defined in `inc/models/class-site.php`, wired in `class-membership-manager.php`).
- **PR:** #1267.  **Status in `main`:** ✅ present.

### 5. Core — subdomain callback not registered under wildcard DNS  → half-built site
- **Plugin:** `ultimate-multisite`
- **Symptom:** the `wu_add_subdomain` job FAILED ("no calls registered"). Under
  **wildcard DNS**, with no host-provider hooked, Action Scheduler ran the job with
  zero callbacks → noise / failed job → site creation could abort half-way (123 of 181
  tables, missing `wp_<id>_options`/`postmeta`) → site is 200 but broken / unstyled.
- **Fix:** `handle_site_created()` now guards the enqueue with
  `if (has_action('wu_add_subdomain'))` — with no provider hooked it simply does not
  enqueue. (`@since 2.12.1` in `inc/managers/class-domain-manager.php`.)
- **Real case:** Eva (blog 347).  **Status in `main`:** ✅ present — thanks, this is
  exactly the guard we needed. We drop our `kp-wu-subdomain-noop-callback` mu-plugin
  once we install a release that carries it (prod is on 2.12.0).

---

## The CI guard — keep these 5 fixes from regressing

This PR adds two files at the repo root:

- **`regression-guard.sh`** — a portable, repo-only script. No SSH, no live site, no
  secrets. It greps the source for the fixed code paths above and exits non-zero if any
  is gone. Auto-detects core/woo/captcha, or pass it explicitly:
  `bash regression-guard.sh [core|woo|captcha]`.
- **`.github/workflows/regression-guard.yml`** — runs the script on every push/PR.

Verified today against your `main`: **core PASS (2/2), woo PASS (2/2), captcha PASS
(2/2).** If a future feature removes one of these paths, the PR turns red with the bug
name + the customer case behind it, so the regression is caught before it ships.

The grep anchors are intentionally on **stable** signatures (function names,
`has_action('wu_add_subdomain')`, `has_status(['active','trialing'])`, the null-widget
guard), not on comments or version strings, so normal refactors won't trip them.

---

## TL;DR for David
- 5 real production bugs, all with customer cases and logs.
- **All 5 are already in your `main`** (#94/#96, #99/#1306, #130/#134, #1267, and the
  `has_action('wu_add_subdomain')` guard you added in 2.12.1). Thank you.
- Please cut **tagged releases** carrying them — we can only drop our local patches
  once the fix is in a release zip (we don't install `main`).
- This PR adds a tiny CI guard (`regression-guard.sh` + a workflow) so no future
  release silently reintroduces any of them. It passes green on `main` right now.
