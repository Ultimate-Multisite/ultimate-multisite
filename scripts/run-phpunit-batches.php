<?php
/**
 * Run PHPUnit test files in deterministic, memory-bounded suites.
 *
 * PHPUnit accepts one file or directory as its positional test target. Passing
 * many files through xargs silently runs only the first file in each batch.
 * This runner creates a temporary PHPUnit configuration for every batch so all
 * selected files execute in the same bounded process.
 *
 * @package WP_Ultimo
 */

// WordPress is not loaded in this CLI runner, so native file and process APIs are required.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open

$project_root = dirname(__DIR__);
$phpunit      = $project_root . '/vendor/phpunit/phpunit/phpunit';
$bootstrap    = $project_root . '/tests/bootstrap.php';
$batch_size   = (int) (getenv('PHPUNIT_BATCH_SIZE') ?: 50);
$input_paths  = array_slice($argv, 1) ?: [$project_root . '/tests'];

if ($batch_size < 1) {
	fwrite(STDERR, "PHPUNIT_BATCH_SIZE must be at least 1.\n");
	exit(2);
}

if ( ! is_file($phpunit) || ! is_file($bootstrap)) {
	fwrite(STDERR, "Install Composer dependencies before running PHPUnit batches.\n");
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
		if (str_ends_with($path, '_Test.php')) {
			$test_files[] = $path;
		}

		continue;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->isFile() && str_ends_with($file->getPathname(), '_Test.php')) {
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

$batches     = array_chunk($test_files, $batch_size);
$exit_status = 0;

foreach ($batches as $index => $batch) {
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
		$index + 1,
		implode("\n", $file_nodes)
	);

	if (strlen($config) !== file_put_contents($config_path, $config)) {
		unlink($config_path);
		fwrite(STDERR, "Unable to write a temporary PHPUnit configuration.\n");
		exit(2);
	}

	fwrite(STDOUT, sprintf("Running PHPUnit batch %d/%d (%d files)\n", $index + 1, count($batches), count($batch)));

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
		$exit_status = $batch_status;
	}
}

if (0 !== $exit_status) {
	exit(1);
}

exit(0);
