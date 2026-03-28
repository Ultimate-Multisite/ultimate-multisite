# Contributing to Multisite Ultimate

Thank you for your interest in contributing to Multisite Ultimate! This document provides guidelines and information for developers.

## Development Setup

### Prerequisites

- PHP 7.4+ (8.1+ recommended for development)
- Node.js 16+ and npm
- Composer
- Git
- WordPress Multisite environment

### Quick Start

1. **Clone and setup the repository:**
   ```bash
   git clone https://github.com/superdav42/wp-multisite-waas.git
   cd wp-multisite-waas
   npm run dev:setup
   ```

2. **Or setup manually:**
   ```bash
   npm run install:deps  # Installs both composer and npm dependencies
   npm run setup:hooks   # Sets up Git hooks
   ```

## Development Commands

### Primary Commands (npm)
```bash
npm test                 # Run PHPUnit tests
npm run test:coverage    # Run tests with coverage
npm run lint             # Check code style (PHPCS)
npm run lint:fix         # Fix code style automatically (PHPCBF)
npm run stan             # Run static analysis (PHPStan)
npm run quality          # Run lint + stan
npm run quality:fix      # Run lint:fix + stan
npm run check            # Run all checks before committing
npm run build            # Production build
npm run build:dev        # Development build
npm run clean            # Clean build artifacts
npm run dev:setup        # Complete development setup
```

### Alternative Commands (composer)
```bash
composer test            # Run PHPUnit tests
composer test:coverage   # Run tests with coverage
composer lint            # Run PHPCS
composer lint:fix        # Run PHPCBF to fix issues
composer stan            # Run PHPStan
composer quality         # Run lint + stan
composer setup-hooks     # Setup Git hooks
```

### Direct Commands
```bash
vendor/bin/phpunit                    # Run tests
vendor/bin/phpcs                     # Check code style
vendor/bin/phpcbf                    # Fix code style
vendor/bin/phpstan analyse           # Static analysis
```

## Code Quality Standards

We maintain high code quality through automated tools and Git hooks:

### Pre-commit Hooks

The project includes Git hooks that run automatically:

- **pre-commit**: Runs PHPCS and PHPStan on changed files
- **commit-msg**: Enforces conventional commit format

To install hooks: `npm run setup:hooks`

### Code Style

We follow WordPress coding standards:
- **PHPCS**: WordPress coding standards for PHP
- **PHPStan**: Static analysis for type safety
- **Conventional Commits**: Standardized commit messages

### Testing

- **PHPUnit**: Unit and integration tests
- **Code Coverage**: Aim for >80% coverage
- **WordPress Test Suite**: Tests run against WordPress multisite

## Commit Message Format

We use [Conventional Commits](https://www.conventionalcommits.org/) format:

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

**Types:**
- `feat`: New features
- `fix`: Bug fixes
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Test-related changes
- `chore`: Build, dependencies, or maintenance
- `perf`: Performance improvements
- `ci`: CI/CD changes
- `build`: Build system changes
- `revert`: Revert previous changes

**Examples:**
```bash
feat(checkout): add support for discount codes
fix(gateway): resolve Stripe webhook validation
docs(readme): update installation instructions
test(models): add Customer model tests
```

## Pull Request Process

1. **Fork the repository** and create a feature branch
2. **Make your changes** following our coding standards
3. **Write/update tests** for your changes
4. **Run quality checks**: `make check`
5. **Update documentation** if needed
6. **Submit a pull request** with:
   - Clear description of changes
   - Reference to related issues
   - Screenshots for UI changes

## Testing

### Running Tests

```bash
# Run all tests
npm test

# Run with coverage
npm run test:coverage

# Run specific test
vendor/bin/phpunit tests/WP_Ultimo/Models/Customer_Test.php
```

### Writing Tests

- Place tests in `tests/` directory
- Follow existing test structure
- Include unit and integration tests
- Test both success and failure scenarios

### Test Environment

Tests run against WordPress test suite with multisite enabled:
- Uses WP_TESTS_MULTISITE=1
- Separate test database
- Isolated from production data

## Code Coverage

We aim for high code coverage:

- **Target**: >80% line coverage
- **Reports**: Generated in `coverage-html/`
- **CI Integration**: Automatic upload to Codecov

View coverage locally:
```bash
npm run test:coverage
open coverage-html/index.html
```

## Development Workflow

### Working on Features

1. **Create feature branch**: `git checkout -b feat/feature-name`
2. **Make changes** with tests
3. **Run checks**: `npm run check`
4. **Commit**: Use conventional format
5. **Push and create PR**

### Working on Fixes

1. **Create fix branch**: `git checkout -b fix/issue-description`
2. **Write test** that reproduces the bug
3. **Fix the issue**
4. **Verify test passes**
5. **Run checks**: `npm run check`
6. **Commit and create PR**

## Addon Development

### Overview

Ultimate Multisite has 28+ addon plugins in the `Ultimate-Multisite` GitHub org. Each addon is a separate repo with its own release cycle, but all depend on the core plugin and share a common test infrastructure.

### Directory Layout

```
~/Git/
├── ultimate-multisite/              # Core plugin (canonical repo)
│   ├── addons.json                  # Addon manifest (all slugs + active status)
│   ├── bin/clone-addons.sh          # Clone all addons
│   ├── bin/generate-wp-env-override.sh  # Generate wp-env config for addons
│   └── .github/workflows/
│       └── addon-integration-test.yml   # Reusable CI workflow for addons
├── ultimate-multisite-addons/       # All addon clones (created by clone-addons.sh)
│   ├── multisite-ultimate-domain-seller/
│   ├── ultimate-multisite-woocommerce/
│   └── ...
```

### Working on an Addon

1. **Clone addons** (first time only):
   ```bash
   cd ~/Git/ultimate-multisite
   bin/clone-addons.sh --active-only
   ```

2. **Create a worktree** in the addon repo:
   ```bash
   cd ~/Git/ultimate-multisite-addons/<addon-slug>
   git worktree add -b feature/my-change ../../<addon-slug>-feature-my-change main
   cd ~/Git/<addon-slug>-feature-my-change
   ```

3. **Generate wp-env override** to include your addon:
   ```bash
   cd ~/Git/ultimate-multisite
   bin/generate-wp-env-override.sh --addon <addon-slug>
   ```

4. **Start the test environment**:
   ```bash
   cd ~/Git/ultimate-multisite
   npm run env:start
   # WordPress Multisite is now running at http://localhost:8888
   # with core + your addon loaded
   ```

5. **Make changes** in your addon worktree, then verify:
   ```bash
   # Run addon's PHPUnit tests
   cd ~/Git/<addon-slug>-feature-my-change
   composer install && vendor/bin/phpunit

   # Run static analysis
   vendor/bin/phpstan analyse

   # Browse the running site to verify visually
   # http://localhost:8888/wp-admin/network/
   ```

6. **Run E2E tests** (if the addon has them):
   ```bash
   cd ~/Git/ultimate-multisite
   npx cypress run --config-file cypress.config.test.js \
     --spec "../<addon-slug>-feature-my-change/tests/e2e/**/*.spec.js"
   ```

7. **Commit, push, and create PR** on the addon repo.

8. **Stop the environment**:
   ```bash
   cd ~/Git/ultimate-multisite
   npm run env:stop
   ```

### Addon CI

Every addon has a `.github/workflows/ci.yml` that calls the core's reusable workflow:

```yaml
jobs:
  integration-test:
    uses: Ultimate-Multisite/ultimate-multisite/.github/workflows/addon-integration-test.yml@main
    with:
      addon-slug: <addon-slug>
    secrets: inherit
```

This automatically:
- Checks out the core plugin and the addon
- Sets up a WordPress Multisite with MariaDB
- Symlinks both plugins into the WP test environment
- Runs the addon's PHPUnit tests

To enable E2E tests, add `run-e2e: true` to the workflow inputs.

### Creating a New Addon

1. Use the template: `gh repo create Ultimate-Multisite/<slug> --template Ultimate-Multisite/ultimate-multisite-addon-template --private`
2. Replace all `{{PLACEHOLDER}}` values in the template files
3. Add the addon to `addons.json` in the core repo
4. Register it in `~/.config/aidevops/repos.json`

## Directory Structure

```
ultimate-multisite/
├── .github/workflows/     # GitHub Actions (including addon-integration-test.yml)
├── .githooks/            # Custom Git hooks
├── addons.json           # Addon manifest
├── bin/                  # Development scripts (including clone-addons.sh)
├── inc/                  # Core PHP classes
├── tests/               # PHPUnit + E2E tests
├── assets/              # CSS/JS/images
├── views/               # Template files
├── vendor/              # Composer dependencies
├── node_modules/        # NPM dependencies
└── composer.json        # PHP dependencies
```

## Release Process

Releases are automated via GitHub Actions:

1. Update version numbers in plugin files
2. Update CHANGELOG
3. Create and push version tag: `git tag v2.x.x && git push origin v2.x.x`
4. GitHub Action builds and creates release

## Getting Help

- **Documentation**: Check existing docs and code comments
- **Issues**: Search existing issues before creating new ones
- **Discussions**: Use GitHub Discussions for questions
- **Code Review**: PRs get reviewed by maintainers

## Performance Considerations

- **Database**: Use proper indexing and efficient queries
- **Caching**: Implement appropriate caching strategies
- **Assets**: Minimize and optimize CSS/JS
- **Hooks**: Use appropriate priority and avoid heavy operations

## Security Guidelines

- **Input Sanitization**: Always sanitize user input
- **Output Escaping**: Escape output based on context
- **Nonces**: Use WordPress nonces for forms
- **Capabilities**: Check user permissions
- **SQL**: Use prepared statements

## Debugging

### Development Mode

Enable WordPress debugging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Logging

Use the built-in logger:
```php
wu_log_add('debug', 'Debug message', $context);
```

### Profiling

The project includes hook profiling capabilities for performance analysis.

## Code Architecture

### Models
- Extend `Base_Model`
- Implement CRUD operations
- Use BerlinDB for database layer

### Admin Pages
- Extend base admin page classes
- Follow WordPress admin UI patterns
- Include proper capability checks

### Checkout System
- Modular signup fields
- Payment gateway integration
- Customizable checkout flows

### Limitations
- Flexible limitation system
- Plugin/theme restrictions
- Resource limits (disk, users, etc.)

Thank you for contributing to Multisite Ultimate! 🚀