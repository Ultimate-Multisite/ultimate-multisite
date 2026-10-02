<?php
/**
 * Fail closed when PHPUnit's report is missing, partial or contains defects.
 *
 * @package WP_Ultimo
 * @subpackage Testing
 * @since 2.16.2
 */

// CLI verification runs outside WordPress.
// phpcs:disable WordPress.WP.AlternativeFunctions

$inventory_file = $argv[1] ?? 'phpunit-inventory.xml';
$results_file   = $argv[2] ?? 'phpunit-results.xml';

if ( ! is_file($inventory_file) || ! is_file($results_file)) {
	fwrite(STDERR, "Missing PHPUnit inventory or results report.\n");
	exit(1);
}

$inventory = simplexml_load_file($inventory_file, SimpleXMLElement::class, LIBXML_NONET);
$results   = simplexml_load_file($results_file, SimpleXMLElement::class, LIBXML_NONET);
if (false === $inventory || false === $results) {
	fwrite(STDERR, "Invalid PHPUnit XML report.\n");
	exit(1);
}

$expected = count($inventory->xpath('//testCaseMethod'));
$actual   = 0;
$defects  = 0;
foreach ($results->testsuite as $suite) {
	$actual  += (int) $suite['tests'];
	$defects += (int) $suite['errors'] + (int) $suite['failures'] + (int) $suite['warnings'];
}

if (0 === $expected || $expected !== $actual || $defects > 0) {
	fwrite(STDERR, sprintf("Incomplete or failing PHPUnit run: expected %d tests, reported %d, defects %d.\n", $expected, $actual, $defects));
	exit(1);
}

fwrite(STDOUT, sprintf("Verified complete PHPUnit execution: %d tests, no errors, failures or PHPUnit warnings.\n", $actual));
exit(0);
