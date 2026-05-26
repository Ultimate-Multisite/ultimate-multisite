
<!-- SPDX-License-Identifier: MIT -->
<!-- SPDX-FileCopyrightText: 2025-2026 Marcus Quinn -->
# Execution Plans

Complex, multi-session work requiring research, design decisions, and detailed tracking.

Based on [OpenAI's PLANS.md](https://cookbook.openai.com/articles/codex_exec_plans) with TOON-enhanced parsing and [Beads](https://github.com/steveyegge/beads) integration for dependency visualization.

<!--TOON:meta{version,format,updated}:
1.0,plans-md+toon,2026-05-20
-->

## Format

Each plan includes:
- **Plan ID**: `p001`, `p002`, etc. (for cross-referencing)
- **Status**: Planning / In Progress (Phase X/Y) / Blocked / Completed
- **Time Estimate**: `~2w (ai:1w test:0.5w read:0.5w)`
- **Timestamps**: `logged:`, `started:`, `completed:`
- **Dependencies**: `blocked-by:p001` or `blocks:p003`
- **Linkage (The Pin)**: File:line references for search hit-rate (see below)
- **Progress**: Timestamped checkboxes with estimates and actuals
- **Decision Log**: Key decisions with rationale
- **Surprises & Discoveries**: Unexpected findings
- **Outcomes & Retrospective**: Results and lessons (when complete)

### Linkage (The Pin)

Based on [Loom's spec-as-lookup-table pattern](https://ghuntley.com/ralph/), each plan should include a Linkage section that functions as a lookup table for AI search:

| Concept | Files | Lines | Synonyms |
|---------|-------|-------|----------|
| {concept} | {file path} | {line range} | {related terms} |

**Why this matters:**
- Reduces hallucination by providing explicit anchors
- Improves search hit-rate with synonyms
- Points to exact file hunks for context
- Prevents AI from inventing when it should reference

## Active Plans

<!-- Add active plans here - see Plan Template below -->

<!--TOON:active_plans[0]{id,title,status,phase,total_phases,owner,tags,est,est_ai,est_test,est_read,logged,started}:
-->

### Context from Discussion

PayPal reviewed our integration and provided specific feedback requiring changes before
approval. The feedback covers 6 areas: UI requirements, seller onboarding, account
validation, order/capture process, BN code, and debug ID submission.

**Architecture context:**
- Two PayPal gateways exist: Legacy NVP (`PayPal_Gateway`) and REST (`PayPal_REST_Gateway`)
- Only the REST gateway matters for the review — legacy is hidden from new installs
- OAuth onboarding is delegated to a proxy at `ultimatemultisite.com/wp-json/paypal-connect/v1`
- The proxy holds partner credentials and handles `/v2/customer/partner-referrals` calls
- Merchant status (`payments_receivable`, `email_confirmed`) is stored but not enforced

**Key decisions:**
- `NO_SHIPPING` is correct — we sell digital WaaS subscriptions, not physical goods
- BN code `ULTIMATE_SP_PPCP` is already implemented on all REST API calls
- The partner account architecture is correct (proxy = partner, merchants connect own accounts)
- Platform fees are handled via `PayPal-Auth-Assertion` header + `payment_instruction`

**What passed review:**
- BN code implementation (ULTIMATE_SP_PPCP on all headers)
- NO_SHIPPING preference on orders and subscriptions
- Order creation via /v2/checkout/orders with CAPTURE intent
- Order capture via /v2/checkout/orders/{id}/capture
- Line items include name, description, unit_amount, quantity, category
- Webhook handling for subscription and payment events

**What needs fixing (code — this repo):**
1. Disconnect dialog uses generic text, needs PayPal's required disclaimer
2. No UI feedback when payments_receivable=false or email_confirmed=false after onboarding
3. Gateway not blocked at checkout when merchant account status is invalid
4. Missing payee.merchant_id in purchase_units for OAuth mode orders
5. PayPal-Debug-Id response header not captured in logs

**What needs verification (proxy — ultimatemultisite.com):**
- Partner-referrals call must include ACCESS_MERCHANT_INFORMATION feature
- Products field must use PPCP (not EXPRESS_CHECKOUT)
- BN code must be sent on partner-referrals header
- /oauth/verify must call /v1/customer/partners/{partner_id}/merchant-integrations/{merchant_id}

**Open questions:**
- Is the proxy partner account configured as platform-only (no direct payments)?
- Does the proxy currently use EXPRESS_CHECKOUT or PPCP as the product?

### Execution Plan

#### Phase 1: Code Changes (this repo) ~6h

**t523a: Disconnect disclaimer** (~30min)
- File: `inc/gateways/class-paypal-rest-gateway.php` line 1892
- Change confirm() text to: "Disconnecting your PayPal account will prevent you from
  offering PayPal services and products on your website. Do you wish to continue?"

**t523b: Onboarding failure UI** (~2h)
- File: `inc/gateways/class-paypal-rest-gateway.php` (render_oauth_connection method)
- After successful OAuth, check stored `payments_receivable` and `email_confirmed`
- Display PayPal's required error messages when either is false:
  - payments_receivable=false: "Attention: You currently cannot receive payments due to
    restriction on your PayPal account. Please reach out to PayPal Customer Support or
    connect to https://www.paypal.com for more information."
  - email_confirmed=false: "Attention: Please confirm your email address on
    https://www.paypal.com/businessprofile/settings in order to receive payments! You
    currently cannot receive payments."

**t523c: Block checkout for invalid merchant status** (~1h)
- File: `inc/gateways/class-paypal-rest-gateway.php`
- Hook into `wu_get_active_gateways` (existing pattern for currency check)
- Remove paypal-rest from active gateways when payments_receivable or email_confirmed is false

**t523d: Add payee.merchant_id to orders** (~1h)
- File: `inc/gateways/class-paypal-rest-gateway.php` (create_order method)
- Add `payee.merchant_id` to `purchase_units[0]` when `$this->merchant_id` is set

**t523e: Log PayPal-Debug-Id headers** (~1h)
- File: `inc/gateways/class-paypal-rest-gateway.php` (api_request method)
- After each API response, extract and log the `PayPal-Debug-Id` response header

#### Phase 2: Proxy Verification (manual — ultimatemultisite.com)

- Verify partner-referrals includes ACCESS_MERCHANT_INFORMATION
- Verify products field uses PPCP
- Verify BN code on partner-referrals header
- Verify /oauth/verify calls merchant-integrations API correctly
- Confirm partner account is platform-only

#### Phase 3: Review Deliverables (manual)

- Capture debug IDs from sandbox test calls (all 4 endpoints)
- Record buyer checkout flow video/screenshots
- Screenshot onboarding failure scenarios (both error messages)
- Screenshot disconnect confirmation dialog
- Submit all to PayPal

### Files Affected

- `inc/gateways/class-paypal-rest-gateway.php` (main changes)
- `inc/gateways/class-paypal-oauth-handler.php` (may need status check helper)
- Proxy server code (separate repo/server)

## Completed Plans

<!-- Move completed plans here with Outcomes & Retrospective -->

<!--TOON:completed_plans[0]{id,title,owner,tags,est,actual,logged,started,completed,lead_time_days}:
-->

## Archived Plans

<!-- Plans that were abandoned or superseded -->

<!--TOON:archived_plans[0]{id,title,reason,logged,archived}:
-->

---

## Plan Template

```markdown
### p00X: Plan Title

**Status:** Planning
**Owner:** @username
**Tags:** #tag1 #tag2
**Estimate:** ~Xd (ai:Xd test:Xd read:Xd)
**Dependencies:** blocked-by:p001 (if any)
**PRD:** [todo/tasks/prd-{slug}.md](tasks/prd-{slug}.md)
**Tasks:** [todo/tasks/tasks-{slug}.md](tasks/tasks-{slug}.md)
**Logged:** YYYY-MM-DD

#### Purpose

Brief description of why this work matters.

#### Development Environment

<!-- Required for Python, Node.js, and any project with non-trivial setup.
     Workers read this section to avoid broken installs in worktrees. -->

| Item | Value |
|------|-------|
| Language/runtime | e.g. Python 3.12, Node 20 |
| Venv/install | e.g. `python3 -m venv .venv && pip install -e ".[dev]"` |
| Tests | e.g. `source .venv/bin/activate && pytest` |
| Do NOT | e.g. install globally; run `pip install -e` from worktree using canonical venv |

#### Linkage (The Pin)

| Concept | Files | Lines | Synonyms |
|---------|-------|-------|----------|
| {main concept} | src/path/file.ts | 45-120 | {term1}, {term2} |
| {related concept} | src/path/other.ts | 12-89 | {term3}, {term4} |

#### Progress

- [ ] (YYYY-MM-DD HH:MMZ) Phase 1: Description ~Xh
- [ ] (YYYY-MM-DD HH:MMZ) Phase 2: Description ~Xh

#### Decision Log

(Decisions recorded during implementation)

#### Surprises & Discoveries

(Unexpected findings during implementation)
```

---

## Analytics

<!--TOON:dependencies-->
<!-- Format: child_id|relation|parent_id -->
<!--/TOON:dependencies-->

<!--TOON:analytics{total_plans,active,completed,archived,avg_lead_time_days,avg_variance_pct}:
0,0,0,0,,
-->
