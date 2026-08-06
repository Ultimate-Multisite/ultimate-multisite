# E2E Testing Project

This project provides end-to-end (E2E) testing for a WordPress environment using **Cypress** and **wp-env**.

## Prerequisites

- Node.js
- Docker
- pnpm (enabled with Corepack)
- wp-env (automatically installed via @wordpress/env)

### Available Scripts

| Script                    | Description                                                                       |
| ------------------------- | --------------------------------------------------------------------------------- |
| `pnpm run env:start`       | Starts the WordPress development and testing environment using `wp-env`.          |
| `pnpm run env:clean`       | Cleans the WordPress test environment. Useful for ensuring a clean slate.         |
| `pnpm run env:stop`        | Stops the running WordPress environment.                                          |
| `pnpm run env:destroy`     | Deletes all docker containers, images and volumes.                                |
| `pnpm run cy:open:dev`     | Starts the dev environment, cleans it, and opens Cypress Test Runner UI.          |
| `pnpm run cy:run:dev`      | Starts the dev environment, cleans it, and runs Cypress tests in headless mode.   |
| `pnpm run cy:open:test`    | Starts the test environment, cleans it, and opens Cypress Test Runner UI.         |
| `pnpm run cy:run:test`     | Starts the test environment, cleans it, and runs Cypress tests in headless mode.  |
| `pnpm run env:start:all`   | Starts the test and dev environment, and start phpMyAdmin and Mailpit.            |
| `pnpm run env:stop:all`    | Stops the test and dev environment, and stops and deletes phpMyAdmin and Mailpit. |

## Start development and test environment

Run `pnpm run env:start` to start both environments.

- [Development environment on port 8888](http://localhost:8888)
- [Testing environment on port 8889](http://localhost:8889)

## Running Tests

### Open Cypress UI

Development environment
```
pnpm run cy:open:dev
```

Testing environment
```
pnpm run cy:open:test
```

This will launch the Cypress Test Runner where you can run tests interactively.

### Run Cypress Tests Headlessly

Development environment
```
pnpm run cy:run:dev
```

Testing environment
```
pnpm run cy:run:test
```

Runs all Cypress tests in the CLI, useful for testing locally and CI/CD environments.

## Destroy all environments

To destroy all environments manually:
```
pnpm run env:destroy
```

## Configuration

Modify `.wp-env.json` in the root of the project to point to custom themes or plugins for testing.
