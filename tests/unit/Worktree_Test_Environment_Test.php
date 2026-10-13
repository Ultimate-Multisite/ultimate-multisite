<?php
/**
 * Worktree test-state and concurrency safety regressions (no database required).
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests;

// These fixtures exercise native file locks and process environment without WordPress.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
require_once dirname(__DIR__, 2) . '/bin/class-worktree-test-environment.php';

class Worktree_Test_Environment_Test extends \PHPUnit\Framework\TestCase {

	private $directory;
	private $environment;
	private $previous_token;
	private $previous_skip_install;
	private $handles = [];

	protected function setUp(): void {
		$this->directory             = sys_get_temp_dir() . '/um-worktree-unit-' . bin2hex(random_bytes(8));
		$this->previous_token        = getenv('WU_TESTS_RUN_TOKEN');
		$this->previous_skip_install = getenv('WP_TESTS_SKIP_INSTALL');
		mkdir($this->directory, 0700);
		$this->environment = (new \ReflectionClass(Worktree_Test_Environment::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Worktree_Test_Environment::class, 'directory'))->setValue($this->environment, $this->directory);
		(new \ReflectionProperty(Worktree_Test_Environment::class, 'git_directory'))->setValue($this->environment, 'fixture-git-directory');
	}

	protected function tearDown(): void {
		$property = new \ReflectionProperty(Worktree_Test_Environment::class, 'run_locks');
		$locks    = $property->getValue();
		if (isset($locks[$this->directory])) {
			fclose($locks[$this->directory]);
			unset($locks[$this->directory]);
			$property->setValue(null, $locks);
		}
		foreach ($this->handles as $handle) {
			fclose($handle);
		}
		putenv(false === $this->previous_token ? 'WU_TESTS_RUN_TOKEN' : 'WU_TESTS_RUN_TOKEN=' . $this->previous_token);
		putenv(false === $this->previous_skip_install ? 'WP_TESTS_SKIP_INSTALL' : 'WP_TESTS_SKIP_INSTALL=' . $this->previous_skip_install);
		foreach (glob($this->directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($this->directory);
	}

	private function held_lock(): void {
		$handle          = fopen($this->directory . '/run.lock', 'c+');
		$this->handles[] = $handle;
		flock($handle, LOCK_EX);
		fwrite($handle, json_encode([
			'token' => 'fixture-token',
			'pid'   => getmypid(),
		]));
		fflush($handle);
	}

	public function test_private_run_lock_remains_held(): void {
		$this->environment->lock_run();
		$probe           = fopen($this->directory . '/run.lock', 'c+');
		$this->handles[] = $probe;
		$this->assertFalse(flock($probe, LOCK_EX | LOCK_NB));
		$this->assertSame(0700, fileperms($this->directory) & 0777);
		$this->assertSame(0600, fileperms($this->directory . '/run.lock') & 0777);
	}

	public function test_independent_run_cannot_borrow_lock(): void {
		$this->held_lock();
		putenv('WU_TESTS_RUN_TOKEN');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Another test run owns');
		$this->environment->lock_run();
	}

	public function test_matching_child_skips_reinstallation(): void {
		$this->held_lock();
		putenv('WU_TESTS_RUN_TOKEN=fixture-token');
		$this->environment->lock_run();
		$this->assertSame('1', getenv('WP_TESTS_SKIP_INSTALL'));
	}

	public function test_child_token_cannot_authorize_cleanup(): void {
		$this->held_lock();
		putenv('WU_TESTS_RUN_TOKEN=fixture-token');
		$this->expectException(\RuntimeException::class);
		$this->environment->cleanup();
	}

	public function test_active_owner_cannot_cleanup_inside_test_run(): void {
		$this->environment->lock_run();
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Cleanup cannot run inside an active test process');
		$this->environment->cleanup();
	}

	public function test_shared_database_name_is_rejected(): void {
		file_put_contents($this->directory . '/manifest.json', json_encode([
			'database'      => 'wordpress_test',
			'owner'         => str_repeat('a', 48),
			'git_directory' => 'fixture-git-directory',
		]));
		$this->expectException(\RuntimeException::class);
		(new \ReflectionMethod($this->environment, 'read_manifest'))->invoke($this->environment);
	}

	public function test_other_worktree_manifest_is_rejected(): void {
		file_put_contents($this->directory . '/manifest.json', json_encode([
			'database'      => 'um_wt_' . str_repeat('a', 24),
			'owner'         => str_repeat('a', 48),
			'git_directory' => 'other-worktree-git-directory',
		]));
		$this->expectException(\RuntimeException::class);
		(new \ReflectionMethod($this->environment, 'read_manifest'))->invoke($this->environment);
	}

	public function test_symlinked_manifest_is_rejected(): void {
		file_put_contents($this->directory . '/target', '{}');
		symlink($this->directory . '/target', $this->directory . '/manifest.json');
		$this->expectException(\RuntimeException::class);
		(new \ReflectionMethod($this->environment, 'read_manifest'))->invoke($this->environment);
	}
}
