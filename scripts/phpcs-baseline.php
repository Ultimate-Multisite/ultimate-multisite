<?php
/**
 * Run PHPCS while enforcing the checked-in legacy violation baseline.
 *
 * @package WP_Ultimo
 * @subpackage Development
 * @since 2.15.2
 */

const WU_PHPCS_BASELINE_VERSION = 2;

$root          = dirname(__DIR__);
$baseline_path = $root . '/phpcs-baseline.json';
$generate      = in_array('--generate-baseline', $argv, true);
$command       = [PHP_BINARY, $root . '/vendor/bin/phpcs', '-q', '--report=json'];
$descriptors   = [
	0 => ['pipe', 'r'],
	1 => ['pipe', 'w'],
	2 => ['pipe', 'w'],
];
$pipes         = [];
$process       = proc_open($command, $descriptors, $pipes, $root);

if (! is_resource($process)) {
	fwrite(STDERR, "Unable to start PHP_CodeSniffer.\n");
	exit(1);
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit_code = proc_close($process);

if ($exit_code > 2) {
	fwrite(STDERR, $stderr ?: "PHP_CodeSniffer failed without a report.\n");
	exit($exit_code);
}

try {
	$report = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
	fwrite(STDERR, "Unable to parse the PHP_CodeSniffer report: {$exception->getMessage()}\n");
	if ($stderr) {
		fwrite(STDERR, $stderr);
	}
	exit(1);
}

/**
 * Convert a PHPCS report into stable per-file diagnostic identities.
 *
 * @param array  $report PHPCS JSON report.
 * @param string $root   Repository root.
 * @return array
 */
function wu_phpcs_build_baseline(array $report, string $root): array {
	$baseline = [
		'version' => WU_PHPCS_BASELINE_VERSION,
		'totals'  => [
			'errors'   => (int) ($report['totals']['errors'] ?? 0),
			'warnings' => (int) ($report['totals']['warnings'] ?? 0),
		],
		'files'   => [],
	];

	foreach ($report['files'] ?? [] as $file => $details) {
		$relative_file = str_starts_with($file, $root . '/')
			? substr($file, strlen($root) + 1)
			: $file;

		foreach ($details['messages'] ?? [] as $message) {
			$key = implode(
				'|',
				[
					$message['type'] ?? 'UNKNOWN',
					$message['source'] ?? 'unknown',
					$message['line'] ?? 0,
					$message['column'] ?? 0,
					$message['message'] ?? '',
				]
			);
			$baseline['files'][$relative_file][$key] = ($baseline['files'][$relative_file][$key] ?? 0) + 1;
		}

		if (isset($baseline['files'][$relative_file])) {
			ksort($baseline['files'][$relative_file]);
		}
	}

	ksort($baseline['files']);

	return $baseline;
}

$current = wu_phpcs_build_baseline($report, $root);

if ($generate) {
	$json = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

	if (false === file_put_contents($baseline_path, $json)) {
		fwrite(STDERR, "Unable to write {$baseline_path}.\n");
		exit(1);
	}

	echo "Generated PHPCS baseline: {$current['totals']['errors']} errors, {$current['totals']['warnings']} warnings.\n";
	exit(0);
}

if (! is_file($baseline_path)) {
	fwrite(STDERR, "PHPCS baseline missing. Run: pnpm run lint:php:baseline\n");
	exit(1);
}

try {
	$expected = json_decode(file_get_contents($baseline_path), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
	fwrite(STDERR, "Unable to parse the PHPCS baseline: {$exception->getMessage()}\n");
	exit(1);
}

$differences = [];
$files       = array_unique(array_merge(array_keys($expected['files'] ?? []), array_keys($current['files'])));
sort($files);

foreach ($files as $file) {
	$expected_sniffs = $expected['files'][$file] ?? [];
	$current_sniffs  = $current['files'][$file] ?? [];
	$sniffs          = array_unique(array_merge(array_keys($expected_sniffs), array_keys($current_sniffs)));
	sort($sniffs);

	foreach ($sniffs as $sniff) {
		$expected_count = (int) ($expected_sniffs[$sniff] ?? 0);
		$current_count  = (int) ($current_sniffs[$sniff] ?? 0);

		if ($expected_count !== $current_count) {
			$differences[] = sprintf('%s: %s expected %d, found %d', $file, $sniff, $expected_count, $current_count);
		}
	}
}

if ($differences) {
	fwrite(STDERR, "PHPCS violations differ from the checked-in baseline:\n");
	foreach (array_slice($differences, 0, 50) as $difference) {
		fwrite(STDERR, " - {$difference}\n");
	}
	if (count($differences) > 50) {
		fwrite(STDERR, sprintf(" - ... and %d more differences\n", count($differences) - 50));
	}
	fwrite(STDERR, "Fix new violations or regenerate deliberately with: pnpm run lint:php:baseline\n");
	exit(1);
}

echo "PHPCS baseline matched: {$current['totals']['errors']} errors, {$current['totals']['warnings']} warnings.\n";
