#!/bin/bash

# check-env.sh — Verify all development prerequisites before running tests, lint, or WP-CLI.
#
# Run this before any lint, test, or WP-CLI command to catch missing tools early
# and avoid bash:other failures. Exit codes: 0=all OK, 1=one or more checks failed.

set -euo pipefail

# Always run from the repo root regardless of where the script was invoked.
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd -- "${SCRIPT_DIR}/.."

any_fail=0

ok() { echo "[OK]  $1"; }
fail() {
	echo "[!!]  $1"
	any_fail=1
}
info() { echo "      $1"; }
note() { echo "[--]  $1"; }

echo "=== Development environment check ==="
echo ""

# 1. PHP binary
if command -v php >/dev/null 2>&1; then
	ok "PHP found: $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "(version unknown)")"
else
	fail "PHP not in PATH — install PHP 7.4+ and ensure it is on PATH"
fi

# 2. Composer vendor directory
if [ -f "vendor/autoload.php" ]; then
	ok "composer install — vendor/autoload.php found"
else
	fail "composer install not run — vendor/autoload.php missing"
	info "Fix: composer install"
fi

# 3. Node modules
if [ -f "node_modules/.bin/eslint" ]; then
	ok "npm install — node_modules/.bin/eslint found"
else
	fail "npm install not run — node_modules/.bin/eslint missing"
	info "Fix: npm install"
fi

# 4. WP-CLI
if command -v wp >/dev/null 2>&1; then
	ok "wp-cli found: $(wp --version --allow-root 2>/dev/null || echo "(version unknown)")"
else
	fail "wp-cli not in PATH"
	info "Install: https://wp-cli.org/#installing (download wp-cli.phar and add to PATH)"
fi

# 5. WordPress dev install
if [ -f "../wordpress/wp-config.php" ]; then
	ok "WordPress dev install found at ../wordpress"
else
	fail "WordPress dev install missing at ../wordpress"
	info "Fix: cd ../wordpress && ./reset.sh  (or clone the dev WP install there)"
fi

# 6. WP test suite
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
if [ -f "${WP_TESTS_DIR}/includes/bootstrap.php" ]; then
	ok "WP test suite found at ${WP_TESTS_DIR}"
else
	fail "WP test suite not installed at ${WP_TESTS_DIR}"
	info "Fix: bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host]"
	info "     Example: bash bin/install-wp-tests.sh wordpress_test root root localhost"
fi

# 7. xdebug (optional — only needed for coverage reports)
if php -m 2>/dev/null | grep -qi xdebug; then
	note "xdebug loaded — coverage reports supported"
else
	note "xdebug NOT loaded (optional) — coverage flags will fail; unit tests run fine"
	info "Omit coverage flags: use vendor/bin/phpunit (not php -d zend_extension=xdebug ...)"
fi

echo ""
if [ "$any_fail" -eq 0 ]; then
	echo "All checks passed. Environment ready."
	exit 0
else
	echo "One or more checks failed. Fix the items marked [!!] above before running tests or lint."
	exit 1
fi
