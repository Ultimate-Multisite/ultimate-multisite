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
- **Type hints**: Use where present in existing code. Return type `void` on init methods.
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
