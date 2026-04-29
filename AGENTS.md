# AGENTS.md — Ultimate Multisite

**Ultimate Multisite** is the user-facing product name. Use "Ultimate Multisite" in all
UI text, docs, and user-facing strings. The code namespace `WP_Ultimo` and the `wu_`
function/hook prefix are preserved for backwards compatibility — do not rename them.

WordPress Multisite WaaS plugin (formerly WP Ultimo). PHP 7.4+, WP 5.3+, GPL v2.
Root namespace: `WP_Ultimo`. Text domain: `ultimate-multisite`.

## Build / Test / Lint Commands

```bash
# Install dependencies
composer install && npm install

# Run full test suite (requires WP test environment — see bin/install-wp-tests.sh)
vendor/bin/phpunit

# Run a single test class
vendor/bin/phpunit --filter Cart_Test

# Run a single test method
vendor/bin/phpunit --filter test_constructor_initializes_defaults

# Run tests with coverage
php -d zend_extension=xdebug -d xdebug.mode=coverage vendor/bin/phpunit \
  --coverage-html=coverage-html --coverage-clover=coverage.xml

# Lint PHP (PHPCS — WordPress coding standards)
vendor/bin/phpcs
vendor/bin/phpcbf                    # auto-fix

# Lint a single PHP file
vendor/bin/phpcs inc/path/to/file.php
vendor/bin/phpcbf inc/path/to/file.php

# Static analysis (PHPStan level 0)
vendor/bin/phpstan analyse

# Lint JS / CSS
npm run lint:js
npm run lint:css

# All quality checks
npm run check                        # lint + stan + test
```

## Project Structure

```text
ultimate-multisite.php   # Plugin entry point, defines WP_ULTIMO_PLUGIN_FILE
constants.php            # Plugin constants and feature flags
sunrise.php              # MU-plugin for domain mapping (loaded before WP)
inc/
  models/                # Data models (Base_Model subclasses)
  managers/              # Singleton managers (business logic, hooks)
  database/              # BerlinDB tables, schemas, queries, enums
  gateways/              # Payment gateways (Stripe, PayPal, Manual, Free)
  checkout/              # Cart, Checkout, Line_Item, signup fields
  admin-pages/           # WP admin page controllers
  list-tables/           # WP_List_Table subclasses
  integrations/          # Host provider integrations (cPanel, Cloudflare, etc.)
  functions/             # Procedural helper functions (wu_get_*, wu_create_*)
  sso/                   # Single sign-on across subsites
  helpers/               # Utility classes (Arr, Hash, Validator, etc.)
  apis/                  # REST API, WP-CLI, MCP traits
  ui/                    # Frontend elements and shortcodes
  traits/                # Shared traits (Singleton, deprecated compat)
  exception/             # Runtime_Exception
tests/
  WP_Ultimo/             # Unit tests mirroring inc/ structure
  Admin_Pages/           # Admin page tests
  e2e/                   # Cypress E2E tests
  bootstrap.php          # WP test bootstrap (loads plugin via muplugins_loaded)
views/                   # PHP template files
assets/                  # JS, CSS, images, fonts
```

## Code Style

### PHP (WordPress Coding Standards via PHPCS)

- **Indentation**: Tabs (not spaces). Tab width = 4.
- **Braces**: Opening brace on same line as declaration (`class Foo {`, `if (...) {`).
- **Arrays**: Short syntax `[]` allowed. No spaces inside brackets: `['key' => 'val']`.
- **Ternary**: Short ternary `?:` allowed.
- **Yoda conditions**: Required in production code (`'value' === $var`). Not required in tests.
- **Strings**: Single quotes preferred. Double quotes only when interpolating.
- **Type hints**: Use where present in existing code. **NEVER add PHP return type
  declarations (`: void`, `: string`, `: bool`, etc.) to public methods on base/abstract
  classes or interfaces** — external addons extend these classes and PHP will fatal if
  the child class doesn't declare the same return type. Use `@return` PHPDoc tags instead.
  This applies to all classes in `inc/gateways/`, `inc/ui/class-base-element.php`,
  `inc/models/class-base-model.php`, `inc/integrations/`, and `inc/checkout/`.
  Private and final methods may use PHP return types freely.
- **PHPDoc**: Required on classes and public methods in `inc/`. Not required in `tests/`.
  Every file header: `@package WP_Ultimo`, `@subpackage`, `@since`.
- **Security guard**: Every PHP file starts with `defined('ABSPATH') || exit;`.
- **i18n**: All user-facing strings via `__()`, `esc_html__()`, etc. with domain `ultimate-multisite`.
- **Sanitization**: Use `wu_clean()` or WordPress sanitization functions. Custom sanitizers
  registered in `.phpcs.xml.dist`.
- **PHP compat**: Must work on PHP 7.4+. Platform set to 7.4.1 in composer.json.

### Naming Conventions

| Entity | Convention | Example |
|---|---|---|
| Classes | `PascalCase` | `Membership_Manager`, `Base_Model` |
| Files (classes) | `class-kebab-case.php` | `class-membership-manager.php` |
| Files (traits) | `trait-kebab-case.php` | `trait-singleton.php` |
| Files (tests) | `PascalCase_Test.php` | `Cart_Test.php` |
| Methods/functions | `snake_case` | `get_membership()`, `wu_get_site()` |
| Global functions | `wu_` prefix | `wu_get_membership()`, `wu_create_customer()` |
| Constants | `UPPER_SNAKE` | `WP_ULTIMO_PLUGIN_DIR`, `WU_EXTERNAL_CRON_ENABLED` |
| Hooks (actions/filters) | `wu_` prefix | `wu_transition_membership_status` |
| Capabilities | `wu_` prefix | `wu_edit_settings`, `wu_read_dashboard` |
| DB tables | `{$wpdb->prefix}wu_` | `wp_wu_customers`, `wp_wu_memberships` |
| Namespaces | `WP_Ultimo\SubPackage` | `WP_Ultimo\Models`, `WP_Ultimo\Checkout` |

### JavaScript

- WordPress ESLint config. Tabs for indentation. jQuery and Vue.js globals available.
- `camelCase` off — snake_case allowed for WP compatibility.
- `*.min.js` files are generated during the release build. Edit the non-minified
  source only and do not commit `.min.js` files on feature branches.

### CSS

- WordPress Stylelint config. `!important` allowed for admin overrides.
- `*.min.css` files are generated during the release build. Edit the non-minified
  source only and do not commit `.min.css` files on feature branches.

## Key Patterns

### Singleton Managers
Managers use `\WP_Ultimo\Traits\Singleton`. Access via `::get_instance()`.
They define `init()` to register hooks. Never instantiate directly.

### Models
Extend `Base_Model`. Use BerlinDB for persistence. Access via `wu_get_*()` functions
or `Model::get_by_id()`. Models return `false` (not null) when not found.
Save with `$model->save()` which returns `true` or `WP_Error`.

### Error Handling
Use `WP_Error` for validation/operation failures — not exceptions.
`Runtime_Exception` exists but is rarely used. Check `is_wp_error()` on return values.

### Tests
- Extend `WP_UnitTestCase`. Tests run in a WordPress Multisite environment
  (`WP_TESTS_MULTISITE=1`).
- Test namespace mirrors source: `WP_Ultimo\Checkout\Cart_Test` tests `WP_Ultimo\Checkout\Cart`.
- Use `wu_create_*()` helper functions to set up test data.
- Manager tests use `Manager_Test_Trait` for shared singleton/slug/model assertions.
- Tests may use non-Yoda conditions, inline arrays, and skip PHPDoc.
- Direct DB queries (`$wpdb->query()`) allowed in tests for setup/teardown.

## Pre-commit Hook

The `.githooks/pre-commit` hook runs PHPCS + PHPStan on staged PHP files and
ESLint + Stylelint on staged JS/CSS. PHPCBF auto-fixes are attempted before failing.
Set up with: `npm run setup:hooks` or `bash bin/setup-hooks.sh`.

## JSON / YAML

Indent with 2 spaces (not tabs). See `.editorconfig`.

## Local Development Environment

The shared WordPress dev install for testing this plugin is at `../wordpress` (relative to this repo root).

- **URL**: http://wordpress.local:8080
- **Admin**: http://wordpress.local:8080/wp-admin — `admin` / `admin`
- **WordPress version**: 7.0-RC2 (pre-release dev environment)
- **This plugin**: symlinked into `../wordpress/wp-content/plugins/$(basename $PWD)`
- **Reset to clean state**: `cd ../wordpress && ./reset.sh`

WP-CLI is configured via `wp-cli.yml` in this repo root — run `wp` commands directly from here without specifying `--path`.

```bash
wp plugin activate $(basename $PWD)   # activate this plugin
wp plugin deactivate $(basename $PWD) # deactivate
wp db reset --yes && cd ../wordpress && ./reset.sh  # full DB + WordPress reset (requires reset.sh in ../wordpress)
```

## Agent Guidance

Recurring error patterns observed in this codebase. Review before starting any session.

### Quick Session Checklist (top 4 recurring failures)

**Run this single command before anything else:**

```bash
bash bin/check-env.sh   # checks ALL prerequisites (vendor, npm, wp-cli, WP test suite)
```

**Before writing a single line of code, verify these four things:**

1. **No webfetch** — never construct or guess a URL. WordPress docs, Stripe/PayPal API docs,
   GitHub URLs, PHP manual, npm package docs: all fail. Read the codebase instead (see
   [No External URL Fetching](#no-external-url-fetching)).
2. **Verify paths exist before reading** — run `git ls-files '<path>'` first. An empty result
   means the file does not exist; do not attempt to read it (see table below).
3. **Read immediately before every edit** — the Edit tool requires a Read call earlier in the
   same conversation turn on that exact file. `Read(A) → Edit(A)` is correct. `Read(A) →
   Read(B) → Edit(A)` fails if A's content changed, and will always fail on session restart.
   Never read multiple files upfront and edit them later.
4. **Check tool prerequisites** — `bash bin/check-env.sh` (see above) checks all prerequisites
   and prints exactly what's missing with fix instructions. Or check individually:
   - `ls vendor/autoload.php 2>/dev/null || echo "run: composer install"`
   - `ls node_modules/.bin/eslint 2>/dev/null || echo "run: npm install"`
   - `ls /tmp/wordpress-tests-lib/includes/bootstrap.php 2>/dev/null || echo "run: bin/install-wp-tests.sh"`

### File Discovery — `.dist` Config Convention

Config files are committed as `.dist` versions; un-suffixed copies are gitignored and may not exist locally:

| Tracked (exists) | Local override (gitignored, may be absent) |
|---|---|
| `.phpcs.xml.dist` | `.phpcs.xml` |
| `phpunit.xml.dist` | `phpunit.xml` |
| `phpstan.neon.dist` | `phpstan.neon` |

Always verify a file is tracked before reading it:

```bash
git ls-files 'phpunit.xml*'   # shows phpunit.xml.dist, not phpunit.xml
```

The `vendor/` and `node_modules/` directories are gitignored. If they are absent, run:

```bash
composer install && npm install
```

The `docs/` directory exists but is not described in the Project Structure section above — it contains developer documentation markdown files. The `todo/` directory contains task briefs.

The following paths are commonly attempted but **do not exist** in this repo — attempting to
read them will produce `read:file_not_found`:

| Path attempted | Reality |
|---|---|
| `wp-config.php` | Lives at `../wordpress/wp-config.php` (outside this repo) |
| `wp-load.php`, `wp-settings.php` | WordPress core lives in `../wordpress/` |
| `wp-admin/**`, `wp-includes/**` | WordPress core — not in this repo |
| `CHANGELOG.md` | Does not exist; see git log for change history |
| `.phpcs.xml` | Gitignored override; use `.phpcs.xml.dist` |
| `phpunit.xml` | Gitignored override; use `phpunit.xml.dist` |
| `phpstan.neon` | Gitignored override; use `phpstan.neon.dist` |
| `vendor/**` | Gitignored; run `composer install` first |
| `node_modules/**` | Gitignored; run `npm install` first |
| `includes/` | Does not exist; this repo uses `inc/` (not `includes/`) |
| `src/` | Does not exist; PHP classes live in `inc/` |
| `lib/` | Does not exist; use `inc/` for PHP code, `assets/` for frontend |
| `languages/` | Does not exist; i18n files are in `lang/` |
| `.env`, `.env.example` | Does not exist; no local env config stored in this repo |
| `inc/functions.php` | Does not exist as a single file; functions live in `inc/functions/*.php` |
| `inc/admin.php` | Does not exist; admin pages live in `inc/admin-pages/` and `inc/admin/` |
| `SECURITY.md` | Does not exist in this repo |
| `coverage-html/**`, `coverage.xml` | Generated by test:coverage run, gitignored, may be absent |
| `inc/class-checkout.php` | Does not exist at root of `inc/`; checkout class is at `inc/checkout/class-checkout.php` |
| `inc/class-cart.php` | Does not exist at root of `inc/`; cart class is at `inc/checkout/class-cart.php` |
| `inc/class-membership.php` | Does not exist at root of `inc/`; model classes are in `inc/models/` |
| `inc/class-customer.php` | Does not exist at root of `inc/`; model classes are in `inc/models/` |
| `inc/class-product.php` | Does not exist at root of `inc/`; model classes are in `inc/models/` |
| `inc/class-payment.php` | Does not exist at root of `inc/`; model classes are in `inc/models/` |
| `inc/class-site.php` | Does not exist at root of `inc/`; model classes are in `inc/models/` |
| `inc/class-domain.php` | Does not exist at root of `inc/`; model classes are in `inc/models/` |
| `lang/*.po`, `lang/*.mo` | Compiled translation files are not tracked; only `lang/ultimate-multisite.pot` is in the repo |

Always verify a file is tracked before reading it with `git ls-files '<path>'`. An empty result means the file does not exist in the repo.

### No External URL Fetching

**NEVER construct or guess a URL for webfetch.** Only fetch URLs explicitly present in user
messages or tool output. Constructed URLs (e.g. building a GitHub raw URL from a file path,
guessing a docs URL from a package name) account for the majority of `webfetch:other` failures
in this codebase.

Do **not** use webfetch for WordPress documentation, PHP manual pages, Stripe/PayPal API docs,
BerlinDB documentation, WooCommerce docs, npm package docs, WP Plugin Handbook, or any other
developer documentation URLs — these requests consistently fail.

Do **not** use webfetch for GitHub URLs (issues, PRs, raw content, commits). Use the `gh` CLI
instead:

```bash
gh issue view 123 --repo Ultimate-Multisite/ultimate-multisite
gh pr view 456 --repo Ultimate-Multisite/ultimate-multisite
gh api repos/Ultimate-Multisite/ultimate-multisite/contents/path/to/file
```

Use the codebase itself for API/hook research:

- WordPress functions and hooks → `rg 'function_name'` across `inc/`
- Existing gateway patterns → read `inc/gateways/` directly
- Stripe integration details → read `inc/gateways/class-base-stripe-gateway.php`
- PayPal integration details → read `inc/gateways/class-base-paypal-gateway.php`
- REST API shape → read `inc/apis/` trait files
- Hook reference → `rg 'do_action\|apply_filters'` across `inc/`
- BerlinDB schema → read `inc/database/` directly

### Read Before Edit (Mandatory)

The Edit tool **requires** a prior Read call on the same file in the **current conversation session**. A prior read from a previous conversation does not count — if the session restarts or the context is cleared, re-read every file before editing it. If you attempt to edit without reading first, the tool will fail. Always read the complete target file before editing — even for small changes.

**Required per-file workflow — follow this every time:**

1. Call `Read` on the target file.
2. Immediately call `Edit` on that same file.
3. If you need to edit another file, call `Read` on that next file first.

Do **not** read all files at session start and edit them later. The pattern `Read(A) → Read(B) → Edit(A)` is correct only if A's content has not changed; in practice, read immediately before each edit to stay safe.

When working on multiple files in the same session: read each file immediately before editing it, not at the start of the session. Reading file A, then file B, then editing file A will fail if A's content changed between your read and your edit.

**Anti-pattern (causes `edit:not_read_first` failure):**

```
Read(inc/models/class-membership.php)
Read(inc/managers/class-membership-manager.php)   ← reading a second file resets the "last read" state
Edit(inc/models/class-membership.php)             ← FAILS: file not read in this turn
```

**Correct pattern:**

```
Read(inc/models/class-membership.php)
Edit(inc/models/class-membership.php)             ← immediate edit after read
Read(inc/managers/class-membership-manager.php)
Edit(inc/managers/class-membership-manager.php)   ← immediate edit after read
```

**New files use `Write`, not `Edit`** — `Edit` is only for modifying existing files (and requires a
prior `Read`). To create a new file, use the `Write` tool instead — `Write` does NOT require a
prior `Read` call. Before writing a new file, verify its parent directory exists:

```bash
git ls-files 'inc/new-subdir/' | head -1   # non-empty means the directory is tracked
```

If you accidentally call `Edit` on a path that doesn't exist yet, the edit will fail. Use `Write`
for new files and `Edit` only for files you have just `Read`.

**Auto-modifying tools (phpcbf) invalidate the Read state** — if you run `vendor/bin/phpcbf`
(or any command that modifies a file in-place) between your `Read` and `Edit` calls, the Edit
will fail with `edit:not_read_first` because the file changed since your last read. Always
`Read` the file again immediately before `Edit` whenever a tool may have modified it:

```
Read(inc/checkout/class-cart.php)                     ← read the file
vendor/bin/phpcbf inc/checkout/class-cart.php         ← MODIFIES the file (auto-style-fix)
Edit(inc/checkout/class-cart.php)                     ← FAILS: file changed since last Read
```

Fix — re-read after phpcbf:

```
Read(inc/checkout/class-cart.php)
vendor/bin/phpcbf inc/checkout/class-cart.php
Read(inc/checkout/class-cart.php)                     ← re-read after phpcbf
Edit(inc/checkout/class-cart.php)                     ← now succeeds
```

### WP-CLI and Bash Prerequisites

`wp` commands require the dev WordPress install at `../wordpress`. Check it exists before running:

```bash
ls ../wordpress/wp-config.php 2>/dev/null || echo "WordPress dev env not found — run ./reset.sh in ../wordpress first"
```

`vendor/bin/phpunit`, `vendor/bin/phpcs`, and `vendor/bin/phpstan` require `composer install`. Check before running test or lint commands:

```bash
ls vendor/autoload.php 2>/dev/null || composer install
```

`npm run lint:js`, `npm run lint:css`, and `npm run check` require `npm install`. Check before running JS/CSS lint or the combined quality check:

```bash
ls node_modules/.bin/eslint 2>/dev/null || npm install
```

Coverage reports (`--coverage-*` flags) require the xdebug PHP extension (`php -d zend_extension=xdebug`). If xdebug is not installed, omit the coverage flags — tests still run without them.

For file discovery, use `git ls-files '<pattern>'` rather than `find` or glob patterns — only tracked files exist reliably, and gitignored paths (vendor, node_modules, *.xml without .dist) will cause `No such file` errors if accessed directly.

### Common bash:other Failures

Most `bash:other` errors in this codebase fall into one of these categories:

**Tool not installed** — run the prerequisite checks above before any lint, test, or analysis command.

**`wp` command not found or fails** — `wp` requires WP-CLI in PATH and the dev WordPress install
at `../wordpress`. If `../wordpress/wp-config.php` is absent, all WP-CLI commands fail. Check:

```bash
command -v wp || echo "wp-cli not in PATH"
ls ../wordpress/wp-config.php 2>/dev/null || echo "WordPress dev env missing"
```

**Wrong working directory** — all build/lint/test commands must be run from the repo root
(`ultimate-multisite/`). Commands like `vendor/bin/phpunit` resolve paths relative to CWD.

**PHP binary not found** — commands like `php -d zend_extension=xdebug` require PHP in PATH.
If `php` is not available, omit the `php -d ...` prefix and call `vendor/bin/phpunit` directly.

**Syntax error in one-liner** — when constructing a PHP or bash one-liner, test with `bash -n`
(syntax check) or `php -l` before running. A typo in a bash heredoc or PHP string will cause
`bash:other`.

**WordPress test suite not installed** — `vendor/bin/phpunit` requires a WordPress test
environment. Without it, it fails with database connection errors or missing `bootstrap.php`
messages. Install it once with:

```bash
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host]
# Example: bash bin/install-wp-tests.sh wordpress_test root root localhost
```

The test environment is installed to `/tmp/wordpress-tests-lib` and `/tmp/wordpress` by default.
Check it exists before running the test suite:

```bash
ls /tmp/wordpress-tests-lib/includes/bootstrap.php 2>/dev/null || echo "WP test suite not installed — run bin/install-wp-tests.sh first"
```

**`npm run build` is a release command, not a dev command** — the `build` script runs
minification, pot-file generation, and an encryption step (`encrypt-secrets.php`). Running it
outside a release context will fail or produce unexpected output. For routine development
quality checks, use:

```bash
npm run check    # lint (phpcs + eslint + stylelint) + phpstan + phpunit
npm run lint     # lint only
npm run stan     # phpstan only
npm test         # phpunit only (equivalent to vendor/bin/phpunit)
```

**PHP functions unavailable in one-liners** — WordPress functions (`add_filter`, `apply_filters`,
`get_option`, etc.) are not available in bare `php -r` one-liners. They require WordPress to be
bootstrapped. Use WP-CLI for WordPress-context commands instead:

```bash
wp eval 'echo get_option("blogname");'   # correct — WP context available
php -r 'echo get_option("blogname");'    # wrong — fatal: Call to undefined function
```

**Do not pass `--standard=` to PHPCS** — the coding standard is already declared in
`.phpcs.xml.dist` (and `.phpcs.xml` if present). Passing `--standard=WordPress` manually
overrides the project config and ignores custom rules. Run PHPCS without a `--standard=` flag:

```bash
vendor/bin/phpcs inc/path/to/file.php   # correct — uses .phpcs.xml.dist
vendor/bin/phpcs --standard=WordPress inc/path/to/file.php   # wrong — bypasses project config
```

**PHPUnit filter not matching any tests** — `vendor/bin/phpunit --filter ClassName` exits 1 with
`No tests executed!` if the class or method name doesn't match exactly. Test class names use
`PascalCase_Test` convention (e.g., `Cart_Test`, `Membership_Manager_Test`). Test method names
start with `test_` (e.g., `test_constructor_initializes_defaults`). Verify the exact name before
filtering:

```bash
git ls-files 'tests/**/*Test.php'              # list all test files to find the right class name
grep -rn 'function test_' tests/WP_Ultimo/Checkout/   # list test methods in a specific directory
```

**Unquoted glob patterns in shell commands** — shell expands unquoted globs before the command
sees them, which can silently return wrong results or empty output. Always quote glob patterns:

```bash
git ls-files 'inc/**/*.php'   # correct — git interprets the glob
git ls-files inc/**/*.php     # wrong — shell may expand or silently fail
rg --files -g '*.php' inc/   # correct — rg handles the glob internally
```

**Running the full test suite without `--filter`** — `vendor/bin/phpunit` with no filter runs the
entire test suite, which can take several minutes and may time out. During development, always
filter to the specific class or method you are working on:

```bash
vendor/bin/phpunit --filter Cart_Test                    # run one test class
vendor/bin/phpunit --filter test_constructor_initializes_defaults   # run one method
vendor/bin/phpunit --filter 'WP_Ultimo\\Checkout\\Cart_Test'        # fully-qualified
```

Only run the full suite (`vendor/bin/phpunit`) for final verification, not during iterative development.
