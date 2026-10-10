# Per-worktree WordPress test databases

Local linked worktrees use separate test databases so one PHPUnit run cannot
drop another worktree's test tables. This isolates databases, not PHP processes,
WordPress source files, uploads, or the development website.

## One-time prerequisites

- PHP 8.2+ with `mysqli`, Git, and the existing WordPress test library/core.
- A trusted local WordPress test config with credentials allowed to create
  databases. Cleanup additionally requires permission to drop the owned database.
- Enable the repository's existing hooks with `bash bin/setup-hooks.sh` if they
  are not already enabled. This sets `core.hooksPath` to `.githooks` for the repo;
  it is a shared repository setting, so coordinate if another hook system is used.

Setup normally reads `wp-tests-config.php` from `WP_TESTS_DIR`, or from
`/tmp/wordpress-tests-lib` when that variable is unset. An explicit trusted
source can be selected with `WU_TESTS_BASE_CONFIG=/absolute/path/to/config.php`.
Never pass passwords on the command line or commit a credential-bearing config.

## Automatic creation

For a new linked worktree, Git's initial `post-checkout` hook calls:

```sh
php bin/setup-worktree-tests.php
```

This works with `git worktree add` and helpers that use it, including aidevops.
Ordinary branch checkouts do not create databases. Worktrees based on older
commits without this hook need manual setup or the new test-bootstrap fallback.

Each worktree receives:

- A unique `um_wt_<random-id>` database. Existing databases are never adopted just
  because their names match a prefix.
- `.worktree-tests/wp-tests-config.php`, containing copied test settings and the
  new database name. The directory is owner-only (`0700`), and private files are
  owner-only (`0600`). The entire directory is gitignored.
- A manifest and an independent ownership marker inside the new database.
  The marker is outside WordPress's test-table prefix and survives test installs.

Repeated setup verifies ownership and reuses the same database. It does not
reinstall WordPress or reset the shared test database. Missing config, changed
metadata, unavailable MySQL, or insufficient privileges fail explicitly.

## Running tests

After installing Composer dependencies, existing commands need no extra flags:

```sh
XDEBUG_MODE=off vendor/bin/phpunit --no-coverage --filter Product_Edit_Admin_Page_Test
```

The bootstrap provisions/verifies the environment before loading WordPress.
It never silently falls back to the shared database for a local linked worktree.
A process-lifetime lock rejects a second independent run in the same worktree.
PHPUnit's isolated child processes inherit their parent's lock token and skip
reinstalling tables; unrelated processes cannot borrow that token by default.
Use another worktree for independent simultaneous test runs.

CI (`CI` set) keeps its existing job-specific database. An explicitly defined
`WP_TESTS_CONFIG_FILE_PATH` is also honored without automatic setup/locking;
callers supplying custom configurations are responsible for their isolation.

If provisioning fails, the hook preserves successful Git worktree creation and
prints **tests are NOT READY**. The test bootstrap then refuses unsafe execution.
Fix the reported prerequisites and rerun setup; do not reset the shared database.
A partial receipt is retained. A retry can create a database that was never
created, but an existing database must already have the matching ownership marker.
Do not reuse an unknown database or discard ownership evidence blindly.

## Safe cleanup

Before removing a worktree, run this explicit command from it:

```sh
php bin/setup-worktree-tests.php cleanup --confirm
```

Cleanup refuses an active test run. It checks the manifest's Git identity,
config fingerprint, database name, and database ownership marker before dropping
exactly that database and removing its private config/manifest. It never drops
databases by wildcard or touches the source/shared database.

Git has no project-owned post-worktree-removal hook, so removal does **not**
silently drop a database. Keep ownership evidence if a worktree is removed before
cleanup; orphan recovery requires explicit inspection rather than guessing.
After successful cleanup, subsequent setup/tests create a fresh owned database.
