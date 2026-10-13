<?php
/**
 * Local, worktree-owned WordPress test database lifecycle.
 *
 * @package WP_Ultimo
 * @subpackage Testing
 * @since 2.16.2
 */

namespace WP_Ultimo\Tests;

// This CLI/test bootstrap utility runs before WordPress is loaded.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
// phpcs:disable WordPress.DB.RestrictedFunctions, WordPress.DB.RestrictedClasses
// Standalone setup needs processes, inherited environment, and PHP config serialization.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.DevelopmentFunctions

/** Owns a linked worktree's private test configuration, database, and locks. */
final class Worktree_Test_Environment {

	private string $root;
	private string $directory;
	private string $git_directory;
	private static array $run_locks = [];

	/**
	 * Resolve an existing linked worktree.
	 *
	 * @param string $root Worktree root.
	 * @throws \RuntimeException When the root is not a linked worktree.
	 */
	public function __construct(string $root) {
		$this->root        = realpath($root) ?: $root;
		$this->directory   = $this->root . '/.worktree-tests';
		[$status, $output] = $this->command(['git', '-C', $this->root, 'rev-parse', '--absolute-git-dir', '--git-common-dir']);
		$paths             = explode("\n", trim($output));
		if (0 !== $status || count($paths) !== 2) {
			throw new \RuntimeException('A Git worktree is required.');
		}
		$this->git_directory = $paths[0];
		$common              = realpath($paths[1]) ?: realpath($this->root . '/' . $paths[1]);
		if (realpath($this->git_directory) === $common) {
			throw new \RuntimeException('Use a linked worktree, not the canonical checkout.');
		}
	}

	/**
	 * Detect a linked worktree without changing Git state.
	 *
	 * @param string $root Worktree root.
	 * @return bool
	 */
	public static function is_worktree(string $root): bool {
		return is_file($root . '/.git');
	}

	/**
	 * Secure the private local-state directory.
	 *
	 * @throws \RuntimeException When the directory is unsafe or inaccessible.
	 */
	private function prepare_directory(): void {
		if (is_link($this->directory) || (! is_dir($this->directory) && ! mkdir($this->directory, 0700))) {
			throw new \RuntimeException('Cannot create the private test environment directory.');
		}
		if (! chmod($this->directory, 0700)) {
			throw new \RuntimeException('Cannot secure the private test environment directory.');
		}
	}

	/**
	 * Open a private lock file.
	 *
	 * @param string $name Lock basename.
	 * @return resource
	 * @throws \RuntimeException When the lock cannot be safely opened.
	 */
	private function lock(string $name) {
		$this->prepare_directory();
		$path = $this->directory . '/' . $name;
		if (is_link($path)) {
			throw new \RuntimeException('Refusing a symlinked test environment lock.');
		}
		$handle = fopen($path, 'c+');
		if (false === $handle || ! chmod($path, 0600)) {
			throw new \RuntimeException('Cannot open the private test environment lock.');
		}
		return $handle;
	}

	/**
	 * Hold a lock for the entire test process; allow its isolated PHPUnit children.
	 *
	 * @param bool $allow_children Whether inherited child runs may share the lock.
	 * @throws \RuntimeException When another run holds the lock.
	 */
	public function lock_run(bool $allow_children = true): void {
		if (isset(self::$run_locks[ $this->directory ])) {
			if (! $allow_children) {
				throw new \RuntimeException('Cleanup cannot run inside an active test process.');
			}
			return;
		}
		$handle = $this->lock('run.lock');
		if (! flock($handle, LOCK_EX | LOCK_NB)) {
			rewind($handle);
			$owner = json_decode(stream_get_contents($handle), true);
			$token = getenv('WU_TESTS_RUN_TOKEN');
			if ($allow_children && is_array($owner) && is_string($token) && '' !== $token && hash_equals($owner['token'] ?? '', $token)) {
				fclose($handle);
				// A child must not reinstall tables while its parent is testing.
				putenv('WP_TESTS_SKIP_INSTALL=1');
				return;
			}
			fclose($handle);
			throw new \RuntimeException('Another test run owns this worktree database. Use another worktree or wait.');
		}
		$token = bin2hex(random_bytes(24));
		ftruncate($handle, 0);
		rewind($handle);
		fwrite(
			$handle,
			json_encode(
			[
				'token' => $token,
				'pid'   => getmypid(),
			]
			)
			);
		fflush($handle);
		putenv('WU_TESTS_RUN_TOKEN=' . $token);
		self::$run_locks[ $this->directory ] = $handle;
	}

	/**
	 * Provision or verify the database in a separate CLI process, before WP loads.
	 *
	 * @throws \RuntimeException When setup fails.
	 */
	public function bootstrap(): void {
		$this->lock_run();
		[$status, $output] = $this->command([PHP_BINARY, $this->root . '/bin/setup-worktree-tests.php']);
		if (0 !== $status) {
			throw new \RuntimeException('Worktree test setup failed. Run php bin/setup-worktree-tests.php; shared database fallback is disabled.');
		}
		define('WP_TESTS_CONFIG_FILE_PATH', $this->directory . '/wp-tests-config.php');
	}

	/**
	 * Run a fixed-argv local command.
	 *
	 * @param array $arguments Command argv.
	 * @return array Exit code and stdout.
	 * @throws \RuntimeException When the process cannot start.
	 */
	private function command(array $arguments): array {
		$process = proc_open(
			$arguments,
			[
				1 => ['pipe', 'w'],
				2 => ['file', 'php://stderr', 'w'],
			],
			$pipes,
			$this->root
			);
		if (! is_resource($process)) {
			throw new \RuntimeException('Cannot start the test environment command.');
		}
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		return [proc_close($process), $output];
	}

	/**
	 * Validate the private ownership receipt.
	 *
	 * @return array|null
	 * @throws \RuntimeException When the receipt is foreign or invalid.
	 */
	private function read_manifest(): ?array {
		$path = $this->directory . '/manifest.json';
		if (! file_exists($path)) {
			return null;
		}
		if (is_link($path)) {
			throw new \RuntimeException('Refusing a symlinked test manifest.');
		}
		$data = json_decode(file_get_contents($path), true);
		if (! is_array($data) || ($data['git_directory'] ?? '') !== $this->git_directory || ! preg_match('/^um_wt_[a-f0-9]{24}$/', $data['database'] ?? '') || ! preg_match('/^[a-f0-9]{48}$/', $data['owner'] ?? '')) {
			throw new \RuntimeException('Invalid or foreign worktree database manifest.');
		}
		return $data;
	}

	/**
	 * Atomically save an owner-only file.
	 *
	 * @param string $name File basename.
	 * @param string $contents File contents.
	 * @throws \RuntimeException When the private write fails.
	 */
	private function write_private(string $name, string $contents): void {
		$path = $this->directory . '/' . $name;
		if (is_link($path)) {
			throw new \RuntimeException('Refusing a symlinked private test file.');
		}
		$temp = tempnam($this->directory, '.write-');
		if (false === $temp || ! chmod($temp, 0600) || false === file_put_contents($temp, $contents) || ! rename($temp, $path)) {
			throw new \RuntimeException('Cannot save private worktree test state.');
		}
	}

	/**
	 * Load a trusted config only inside the standalone setup process.
	 *
	 * @param string $path Config file.
	 * @return array Constants, table prefix, and optional test options.
	 * @throws \RuntimeException When the config is unusable.
	 */
	private function settings(string $path): array {
		if (! is_readable($path) || is_link($path)) {
			throw new \RuntimeException('Test source config is missing or symlinked. Set WU_TESTS_BASE_CONFIG to a trusted config file.');
		}
		$before = get_defined_constants(true)['user'] ?? [];
		ob_start();
		try {
			require $path;
		} finally {
			ob_end_clean();
		}
		$constants = array_diff_key(get_defined_constants(true)['user'] ?? [], $before);
		foreach (['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'ABSPATH'] as $key) {
			if (! isset($constants[ $key ]) || ! is_string($constants[ $key ])) {
				throw new \RuntimeException('Required WordPress test configuration is missing.');
			}
		}
		if (! is_dir($constants['ABSPATH']) || ! isset($table_prefix) || ! preg_match('/^[a-zA-Z0-9_]+$/', $table_prefix)) {
			throw new \RuntimeException('Invalid WordPress test path or table prefix.');
		}
		return [$constants, $table_prefix, $wp_tests_options ?? null];
	}

	/**
	 * Connect without selecting or changing the source database.
	 *
	 * @param array $settings Private database credentials.
	 * @return \mysqli
	 */
	private function connection(array $settings): \mysqli {
		$host   = $settings['DB_HOST'];
		$port   = 3306;
		$socket = null;
		if (preg_match('/^\[([^]]+)\](?::(\d+))?$/', $host, $parts)) {
			$host = $parts[1];
			$port = isset($parts[2]) ? (int) $parts[2] : 3306;
		} elseif (str_contains($host, ':')) {
			[$host, $suffix] = explode(':', $host, 2);
			if (ctype_digit($suffix)) {
				$port = (int) $suffix;
			} else {
				$socket = $suffix;
			}
		}
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		$db = new \mysqli();
		$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
		$db->real_connect($host, $settings['DB_USER'], $settings['DB_PASSWORD'], '', $port, $socket);
		return $db;
	}

	/**
	 * Verify the database marker before reuse or removal.
	 *
	 * @param \mysqli $db Database connection.
	 * @param array   $manifest Ownership receipt.
	 * @throws \RuntimeException When ownership does not match.
	 */
	private function verify_owner(\mysqli $db, array $manifest): void {
		$db->select_db($manifest['database']);
		$owner = $db->query('SELECT owner_token FROM um_worktree_test_owner WHERE id = 1')->fetch_row();
		if (! $owner || ! hash_equals($manifest['owner'], $owner[0])) {
			throw new \RuntimeException('Database ownership verification failed.');
		}
	}

	/**
	 * Idempotently create the private configuration and its owned database.
	 *
	 * @return string Database name.
	 * @throws \RuntimeException When ownership or configuration is invalid.
	 */
	public function setup(): string {
		$lock = $this->lock('setup.lock');
		if (! flock($lock, LOCK_EX)) {
			fclose($lock);
			throw new \RuntimeException('Cannot acquire the provisioning lock.');
		}
		try {
			$manifest = $this->read_manifest();
			$config   = $this->directory . '/wp-tests-config.php';
			$ready    = null !== $manifest && isset($manifest['config_hash']);
			if ($ready && (! is_file($config) || ! hash_equals($manifest['config_hash'], hash_file('sha256', $config)))) {
				throw new \RuntimeException('Private configuration changed or is missing; refusing shared fallback.');
			}
			$source                        = $ready ? $config : (getenv('WU_TESTS_BASE_CONFIG') ?: rtrim(getenv('WP_TESTS_DIR') ?: sys_get_temp_dir() . '/wordpress-tests-lib', '/\\') . '/wp-tests-config.php');
			[$settings, $prefix, $options] = $this->settings($source);
			$db                            = $this->connection($settings);
			if (null === $manifest) {
				$manifest = [
					'database'      => 'um_wt_' . bin2hex(random_bytes(12)),
					'owner'         => bin2hex(random_bytes(24)),
					'git_directory' => $this->git_directory,
				];
				$this->write_private('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT) . "\n");
			}
			if (! $ready) {
				// A failed CREATE may leave only a pending receipt. Retry only if absent.
				$lookup = $db->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
				$lookup->bind_param('s', $manifest['database']);
				$lookup->execute();
				if (0 === $lookup->get_result()->num_rows) {
					$db->query('CREATE DATABASE `' . $manifest['database'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
					$db->select_db($manifest['database']);
					$db->query('CREATE TABLE um_worktree_test_owner (id TINYINT PRIMARY KEY, owner_token VARCHAR(48) NOT NULL)');
					$query = $db->prepare('INSERT INTO um_worktree_test_owner VALUES (1, ?)');
					$query->bind_param('s', $manifest['owner']);
					$query->execute();
				}
			}
			$this->verify_owner($db, $manifest);
			if ($ready && $settings['DB_NAME'] !== $manifest['database']) {
				throw new \RuntimeException('Private config does not name its owned database.');
			}
			if (! $ready) {
				$settings['DB_NAME'] = $manifest['database'];
				$contents            = "<?php\n// Private worktree test configuration. Do not commit.\n";
				foreach ($settings as $key => $value) {
					$contents .= 'define(' . var_export($key, true) . ', ' . var_export($value, true) . ");\n";
				}
				$contents .= '$table_prefix = ' . var_export($prefix, true) . ";\n";
				if (null !== $options) {
					$contents .= '$wp_tests_options = ' . var_export($options, true) . ";\n";
				}
				$this->write_private('wp-tests-config.php', $contents);
				$manifest['config_hash'] = hash('sha256', $contents);
				$this->write_private('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT) . "\n");
			}
			chmod($config, 0600);
			$db->close();
			return $manifest['database'];
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/**
	 * Explicitly delete only the database whose manifest and marker both match.
	 *
	 * @throws \RuntimeException When a run is active or ownership is unverified.
	 */
	public function cleanup(): void {
		$this->lock_run(false);
		$lock = $this->lock('setup.lock');
		if (! flock($lock, LOCK_EX)) {
			fclose($lock);
			throw new \RuntimeException('Cannot acquire the cleanup lock.');
		}
		try {
			$manifest = $this->read_manifest();
			$config   = $this->directory . '/wp-tests-config.php';
			if (null === $manifest || ! isset($manifest['config_hash']) || ! is_file($config) || ! hash_equals($manifest['config_hash'], hash_file('sha256', $config))) {
				throw new \RuntimeException('No verified owned environment to clean up.');
			}
			[$settings] = $this->settings($config);
			if ($settings['DB_NAME'] !== $manifest['database']) {
				throw new \RuntimeException('Database identity mismatch; cleanup refused.');
			}
			$db = $this->connection($settings);
			$this->verify_owner($db, $manifest);
			$db->query('DROP DATABASE `' . $manifest['database'] . '`');
			$db->close();
			unlink($config);
			unlink($this->directory . '/manifest.json');
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}
}
