#!/usr/bin/env bash
# ============================================================================
#  Ultimate Multisite — REGRESSION GUARD (portable, repo-only)
#  ----------------------------------------------------------------------------
#  Run this in CI on every push / PR. It does NOT touch any live site, SSH,
#  or external service. It only greps the source files of THIS repo to confirm
#  that the fixes for 5 real production bugs are still present after a change.
#
#  If a new feature accidentally removes one of these code paths, the guard
#  fails and the PR is blocked — so a regression cannot ship silently.
#
#  USAGE:
#    bash regression-guard.sh            # auto-detect which plugin this repo is
#    bash regression-guard.sh core       # force: core (ultimate-multisite)
#    bash regression-guard.sh woo        # force: ultimate-multisite-woocommerce
#    bash regression-guard.sh captcha    # force: ultimate-multisite-captcha
#
#  EXIT CODES:  0 = all guarded paths present   1 = a regression was detected
#
#  Each check maps to a documented bug (see PARA-DAVID-regression-guard.md).
#  The grep anchors below were verified against the upstream `main` branch.
# ============================================================================

set -u
ROOT="$(cd "$(dirname "$0")" && pwd)"
RED='\033[0;31m'; GRN='\033[0;32m'; YEL='\033[1;33m'; NC='\033[0m'
FAIL=0
PASS=0

ok()   { printf "  ${GRN}PASS${NC}  %s\n" "$1"; PASS=$((PASS+1)); }
bad()  { printf "  ${RED}FAIL${NC}  %s\n" "$1"; FAIL=$((FAIL+1)); }
skip() { printf "  ${YEL}SKIP${NC}  %s\n" "$1"; }

# grep helper: returns 0 if pattern found in file (fixed-string), else 1
has() { # has <file> <fixed-string>
  [ -f "$ROOT/$1" ] && grep -qF -- "$2" "$ROOT/$1"
}
hasE() { # hasE <file> <regex>
  [ -f "$ROOT/$1" ] && grep -qE -- "$2" "$ROOT/$1"
}

# ---- auto-detect which plugin this repo is -------------------------------
detect() {
  [ -n "${1:-}" ] && { echo "$1"; return; }
  if   [ -f "$ROOT/inc/managers/class-domain-manager.php" ]; then echo core
  elif [ -f "$ROOT/inc/gateways/class-woocommerce-gateway.php" ]; then echo woo
  elif [ -f "$ROOT/assets/js/providers/cap-token-resolver.js" ]; then echo captcha
  else echo unknown
  fi
}
PLUGIN="$(detect "${1:-}")"

echo "======================================================================"
echo " Ultimate Multisite — regression guard   (plugin: $PLUGIN)"
echo "======================================================================"

case "$PLUGIN" in

# =====================================================================  CORE
core)
  echo "[BUG 4] pending site stuck / infinite overlay — is_publishing_stale()"
  if has "inc/models/class-site.php" "function is_publishing_stale" \
     && hasE "inc/managers/class-membership-manager.php" "is_publishing_stale\(\)"; then
    ok "is_publishing_stale() defined and wired in the pending-site checker"
  else
    bad "is_publishing_stale() missing or not wired -> overlay can hang again (PR #1267)"
  fi

  echo "[BUG 5] subdomain callback under wildcard DNS — half-built site"
  # FIX: handle_site_created() must guard the enqueue with has_action('wu_add_subdomain')
  # so that with no provider hooked the async job is NOT enqueued (no failed job).
  if hasE "inc/managers/class-domain-manager.php" "has_action\([^)]*wu_add_subdomain"; then
    ok "has_action('wu_add_subdomain') guard present before enqueue"
  else
    bad "NO has_action('wu_add_subdomain') guard -> wildcard DNS aborts site creation (Eva, blog 347)"
  fi
  ;;

# ======================================================================  WOO
woo)
  GW="inc/gateways/class-woocommerce-gateway.php"

  echo "[BUG 2] creates 2nd subscription & cancels the active one"
  # FIX: if existing sub is active/trialing, cancel the DUPLICATE, not the active one.
  if hasE "$GW" "has_status\(\s*\[\s*'active'\s*,\s*'trialing'\s*\]\s*\)"; then
    ok "active/trialing guard present in subscription handling (cancels the duplicate)"
  else
    bad "active/trialing duplicate guard missing -> cancels real active sub (Karoly) (PR #94/#96)"
  fi

  echo "[BUG 3] paid renewal still suspends the site"
  # FIX: recalculate next_payment on renewal before renew() so it is in the future.
  if hasE "$GW" "calculate_date\(\s*'next_payment'\s*\)"; then
    ok "next_payment recalculation present on renewal path"
  else
    bad "next_payment recalculation missing -> paid renewal suspends site (Elizabeth #428, Ikena #466) (PR #99/#1306)"
  fi
  ;;

# ==================================================================  CAPTCHA
captcha)
  JS="assets/js/providers/cap-token-resolver.js"
  MIN="assets/js/providers/cap-token-resolver.min.js"

  echo "[BUG 1] form submits before token is ready — registration blocked on Step 1"
  # FIX #1: guard widget null before reading .token   FIX #2: resolve() with no widget.
  if hasE "$JS" "widget && widget\.token" && hasE "$JS" "if \(! ?widget\)"; then
    ok "null-widget guard + immediate-resolve present in cap-token-resolver.js"
  else
    bad "captcha resolver guard missing in .js -> Step 1 blocks again (Lis/Ender/Eva) (PR #130/#134)"
  fi

  # The minified file must carry the same logic (it is what ships/loads).
  if [ -f "$ROOT/$MIN" ]; then
    if has "$MIN" "e&&e.token" || has "$MIN" "Captcha verification is taking longer than expected"; then
      ok "minified resolver carries the guard / resolver string"
    else
      bad "cap-token-resolver.min.js does NOT carry the fix -> rebuild the minified asset"
    fi
  else
    skip "no cap-token-resolver.min.js found (nothing to verify)"
  fi
  ;;

unknown)
  echo "  Could not detect which Ultimate Multisite plugin this is."
  echo "  Run with an explicit target:  bash regression-guard.sh [core|woo|captcha]"
  exit 2
  ;;
esac

echo "----------------------------------------------------------------------"
if [ "$FAIL" -eq 0 ]; then
  printf " ${GRN}RESULT: all %d guarded path(s) present — safe.${NC}\n" "$PASS"
  exit 0
else
  printf " ${RED}RESULT: %d regression(s) detected, %d ok — block this change.${NC}\n" "$FAIL" "$PASS"
  echo " See PARA-DAVID-regression-guard.md for the bug behind each failing check."
  exit 1
fi
