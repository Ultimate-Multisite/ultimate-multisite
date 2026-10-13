#!/usr/bin/env php
<?php
/**
 * Create or explicitly clean up a worktree-owned WordPress test database.
 *
 * @package WP_Ultimo
 * @subpackage Testing
 * @since 2.16.2
 */

// CLI-only: no WordPress bootstrap or Composer dependencies needed.
// phpcs:disable WordPress.WP.AlternativeFunctions -- CLI streams are available before WordPress.
if ('cli' !== PHP_SAPI) {
	exit(1);
}

require_once __DIR__ . '/class-worktree-test-environment.php';

umask(0077);
$arguments = array_slice($argv, 1);
if ([] !== $arguments && ['cleanup', '--confirm'] !== $arguments) {
	fwrite(STDERR, "Usage: php bin/setup-worktree-tests.php [cleanup --confirm]\n");
	exit(1);
}

try {
	$environment = new \WP_Ultimo\Tests\Worktree_Test_Environment(dirname(__DIR__));
	if ([] === $arguments) {
		$database = $environment->setup();
		fwrite(STDOUT, "Worktree test database ready: {$database}\n");
	} else {
		$environment->cleanup();
		fwrite(STDOUT, "Removed the verified worktree test database and private config.\n");
	}
} catch (\Throwable $error) {
	// Driver/config exceptions may contain credentials: never print their details.
	fwrite(STDERR, "Worktree test setup/cleanup failed. Check the trusted source config, local state, mysqli extension and database permissions. No shared database fallback is permitted.\n");
	exit(1);
}
