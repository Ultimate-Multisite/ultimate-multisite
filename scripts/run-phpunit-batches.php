<?php
/**
 * Run PHPUnit test files in deterministic, memory-bounded suites.
 *
 * PHPUnit accepts one file or directory as its positional test target. Passing
 * many files through xargs silently runs only the first file in each batch.
 * This runner creates a temporary configuration for every batch and removes
 * plugin-owned test tables between processes so reset WordPress IDs cannot
 * collide with stale Ultimate Multisite records.
 *
 * @package WP_Ultimo
 */

// WordPress is not loaded in this CLI runner, so native file, database, and process APIs are required.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open, WordPress.DB.RestrictedFunctions

$project_root = dirname(__DIR__);
$phpunit      = $project_root . '/vendor/phpunit/phpunit/phpunit';
$bootstrap    = $project_root . '/tests/bootstrap.php';
$batch_size   = (int) (getenv('PHPUNIT_BATCH_SIZE') ?: 50);
$batch_number = (int) (getenv('PHPUNIT_BATCH_NUMBER') ?: 0);
$input_paths  = array_slice($argv, 1) ?: [$project_root . '/tests'];

if ($batch_size < 1) {
	fwrite(STDERR, "PHPUNIT_BATCH_SIZE must be at least 1.\n");
	exit(2);
}

if ($batch_number < 0) {
	fwrite(STDERR, "PHPUNIT_BATCH_NUMBER cannot be negative.\n");
	exit(2);
}

if ( ! is_file($phpunit) || ! is_file($bootstrap)) {
	fwrite(STDERR, "Install Composer dependencies before running PHPUnit batches.\n");
	exit(2);
}

$tests_directory = getenv('WP_TESTS_DIR') ?: rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
$config_path     = getenv('WP_TESTS_CONFIG_FILE_PATH') ?: $tests_directory . '/wp-tests-config.php';

if (is_dir($config_path)) {
	$config_path = rtrim($config_path, '/\\') . '/wp-tests-config.php';
}

if ( ! is_file($config_path)) {
	fwrite(STDERR, "The WordPress test configuration could not be found.\n");
	exit(2);
}

require $config_path;

if ( ! class_exists('mysqli') || ! defined('DB_NAME') || ! defined('DB_USER') || ! defined('DB_PASSWORD') || ! defined('DB_HOST') || ! isset($table_prefix)) {
	fwrite(STDERR, "The WordPress test database configuration is incomplete.\n");
	exit(2);
}

$test_files = [];

foreach ($input_paths as $input_path) {
	$path = realpath($input_path);

	if (false === $path) {
		fwrite(STDERR, sprintf("Test path does not exist: %s\n", $input_path));
		exit(2);
	}

	if (is_file($path)) {
		if (str_ends_with($path, 'Test.php')) {
			$test_files[] = $path;
		}

		continue;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->isFile() && str_ends_with($file->getPathname(), 'Test.php')) {
			$test_files[] = $file->getPathname();
		}
	}
}

$test_files = array_values(array_unique($test_files));
sort($test_files, SORT_STRING);

if (empty($test_files)) {
	fwrite(STDERR, "No PHPUnit test files were found.\n");
	exit(2);
}

$clean_plugin_tables = static function () use ($table_prefix) {
	$db_host = DB_HOST;
	$db_port = 0;

	if (preg_match('/^([^:]+):(\d+)$/', DB_HOST, $host_parts)) {
		$db_host = $host_parts[1];
		$db_port = (int) $host_parts[2];
	}

	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	$database = mysqli_init();
	$database->real_connect($db_host, DB_USER, DB_PASSWORD, DB_NAME, $db_port);
	$tables   = $database->query('SHOW TABLES');
	$prefixes = [
		$table_prefix . 'wu_',
		$table_prefix . 'actionscheduler_',
	];

	$database->query('SET FOREIGN_KEY_CHECKS = 0');

	while (true) {
		$table = $tables->fetch_row();

		if (null === $table) {
			break;
		}

		foreach ($prefixes as $prefix) {
			if (str_starts_with($table[0], $prefix)) {
				$table_name = str_replace('`', '``', $table[0]);
				$database->query("DROP TABLE IF EXISTS `{$table_name}`");
				break;
			}
		}
	}

	$database->query('SET FOREIGN_KEY_CHECKS = 1');
	$database->close();
};

$batches       = array_chunk($test_files, $batch_size);
$total_batches = count($batches);
$batch_offset  = 0;
$exit_status   = 0;

if ($batch_number) {
	if ( ! isset($batches[ $batch_number - 1 ])) {
		fwrite(STDERR, sprintf("PHPUnit batch %d does not exist; expected 1-%d.\n", $batch_number, $total_batches));
		exit(2);
	}

	$batch_offset = $batch_number - 1;
	$batches      = [$batches[ $batch_offset ]];
}

foreach ($batches as $index => $batch) {
	$current_batch = $batch_offset + $index + 1;

	try {
		$clean_plugin_tables();
	} catch (Throwable $exception) {
		fwrite(STDERR, sprintf("Unable to reset plugin test tables: %s\n", $exception->getMessage()));
		exit(2);
	}

	$config_path = tempnam(sys_get_temp_dir(), 'wu-phpunit-batch-');

	if (false === $config_path) {
		fwrite(STDERR, "Unable to create a temporary PHPUnit configuration.\n");
		exit(2);
	}

	$file_nodes = array_map(
		static fn($file) => '      <file>' . htmlspecialchars($file, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</file>',
		$batch
	);
	$config     = sprintf(
		"<?xml version=\"1.0\"?>\n<phpunit bootstrap=\"%s\" backupGlobals=\"false\" colors=\"true\">\n  <php>\n    <const name=\"WP_TESTS_MULTISITE\" value=\"1\"/>\n  </php>\n  <testsuites>\n    <testsuite name=\"batch-%d\">\n%s\n    </testsuite>\n  </testsuites>\n</phpunit>\n",
		htmlspecialchars($bootstrap, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
		$current_batch,
		implode("\n", $file_nodes)
	);

	if (strlen($config) !== file_put_contents($config_path, $config)) {
		unlink($config_path);
		fwrite(STDERR, "Unable to write a temporary PHPUnit configuration.\n");
		exit(2);
	}

	fwrite(STDOUT, sprintf("Running PHPUnit batch %d/%d (%d files)\n", $current_batch, $total_batches, count($batch)));

	$process = proc_open(
		[PHP_BINARY, $phpunit, '--configuration', $config_path, '--no-coverage'],
		[STDIN, STDOUT, STDERR],
		$pipes,
		$project_root
	);

	if ( ! is_resource($process)) {
		unlink($config_path);
		fwrite(STDERR, "Unable to start PHPUnit.\n");
		exit(2);
	}

	$batch_status = proc_close($process);
	unlink($config_path);

	if (0 !== $batch_status) {
		$exit_status = 1;
	}
}

try {
	$clean_plugin_tables();
} catch (Throwable $exception) {
	fwrite(STDERR, sprintf("Unable to reset plugin test tables: %s\n", $exception->getMessage()));
	exit(2);
}

if (0 !== $exit_status) {
	exit(1);
}

exit(0);
