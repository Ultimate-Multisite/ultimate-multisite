<?php
/**
 * Generates the berlindb-dynamic-hooks.php reference file.
 *
 * This script introspects all BerlinDB query classes and their associated
 * schema classes to produce a PHP file containing properly documented
 * do_action() / apply_filters() calls that @10up/wp-hooks-documentor can
 * discover.
 *
 * Usage:
 *   php generate-berlindb-hooks.php
 *
 * The output file is written to the same directory as this script.
 *
 * @package WP_Ultimo
 * @subpackage Documentation
 * @since 2.3.0
 */

// ── Bootstrap just enough of WordPress to load the schema classes ───────────

$plugin_root = dirname(__DIR__, 2); // ultimate-multisite/

// We need the autoloader to resolve class references.
$autoloader = $plugin_root . '/vendor/autoload.php';

if (! file_exists($autoloader)) {
	fwrite(STDERR, "Composer autoloader not found at {$autoloader}\n");
	fwrite(STDERR, "Run `composer install` inside {$plugin_root} first.\n");
	exit(1);
}

require_once $autoloader;

// Minimal stubs so schema classes can load outside of WordPress.
if (! defined('ABSPATH')) {
	define('ABSPATH', '/');
}

// ── Source file location for @see tags ──────────────────────────────────────

$query_php_relative = 'vendor/berlindb/core/src/Database/Query.php';
$query_php_absolute = $plugin_root . '/' . $query_php_relative;

/**
 * Find the line number for a specific hook pattern in Query.php.
 *
 * @param string $file    Absolute path to Query.php.
 * @param string $pattern Regex pattern to search for.
 * @return int Line number (1-based) or 0 if not found.
 */
function find_line_number(string $file, string $pattern): int {

    static $cache = [];

    if (! is_readable($file)) {
        return 0;
    }

    if (! isset($cache[$file])) {
        $cache[$file] = file($file, FILE_IGNORE_NEW_LINES);
    }

    foreach ($cache[$file] as $i => $line) {
        if (preg_match($pattern, $line)) {
            return $i + 1;
        }
    }

    return 0;
}

// ── Discover query classes ──────────────────────────────────────────────────

$database_dir = $plugin_root . '/inc/database';
$query_files  = glob($database_dir . '/*/class-*-query.php');

if (empty($query_files)) {
	fwrite(STDERR, "No query class files found in {$database_dir}\n");
	exit(1);
}

// Skip the engine base class.
$query_files = array_filter(
	$query_files,
	function ($file) {
		return strpos($file, '/engine/') === false;
	}
);

// ── Extract metadata from each query class ──────────────────────────────────

/**
 * Parse a single PHP class file and extract protected property values.
 *
 * @param string $file       Absolute path to the PHP file.
 * @param array  $properties List of property names to extract.
 * @return array Associative array of property => value.
 */
function extract_class_properties(string $file, array $properties): array {

	$source = file_get_contents($file);
	$result = [];

	foreach ($properties as $prop) {
		// Match:  protected $prop = 'value';
		// protected $prop = SomeClass::class;
		if (preg_match('/protected\s+\$' . preg_quote($prop, '/') . '\s*=\s*(.+?);/s', $source, $m)) {
			$raw = trim($m[1]);

			// String literal
			if (preg_match("/^['\"](.+?)['\"]$/", $raw, $sm)) {
				$result[ $prop ] = $sm[1];
			}
			// Class reference (Foo::class or \Foo\Bar::class)
			elseif (preg_match('/^(.+?)::class$/', $raw, $cm)) {
				$result[ $prop ] = trim($cm[1], '\\');
			} else {
				$result[ $prop ] = $raw;
			}
		}
	}

	return $result;
}

/**
 * Parse a schema class and return column definitions that have 'transition' => true.
 *
 * @param string $file Absolute path to the schema class file.
 * @return array List of ['name' => string, 'type' => string] for transition columns.
 */
function extract_schema_columns(string $file): array {

	$source = file_get_contents($file);

	// Extract the $columns array block.
	if (! preg_match('/\$columns\s*=\s*\[(.+)\];/s', $source, $m)) {
		return [];
	}

	$columns_block      = $m[1];
	$all_columns        = [];
	$transition_columns = [];
	$seen_names         = [];

	// Split into individual column arrays.
	// Each column is delimited by [ ... ],
	preg_match_all('/\[\s*(.*?)\s*\]/s', $columns_block, $entries);

	foreach ($entries[1] as $entry) {
		$col = [];

		// Extract name
		if (preg_match("/'name'\s*=>\s*'([^']+)'/", $entry, $nm)) {
			$col['name'] = $nm[1];
		} else {
			continue;
		}

		// Extract type
		if (preg_match("/'type'\s*=>\s*'([^']+)'/", $entry, $tm)) {
			$col['type'] = $tm[1];
		} else {
			$col['type'] = 'mixed';
		}

		// Deduplicate columns (some schemas define the same column twice).
		if (isset($seen_names[ $col['name'] ])) {
			continue;
		}

		$seen_names[ $col['name'] ] = true;
		$all_columns[]              = $col;

		// Check for transition => true
		if (preg_match("/'transition'\s*=>\s*true/", $entry)) {
			$transition_columns[] = $col;
		}
	}

	return [
		'all'        => $all_columns,
		'transition' => $transition_columns,
	];
}

/**
 * Resolve a schema class reference to a file path.
 *
 * @param string $class_fqn  Fully qualified class name without leading \.
 * @param string $plugin_root Plugin root directory.
 * @return string|null File path or null.
 */
function resolve_schema_file(string $class_fqn, string $plugin_root): ?string {

	// WP_Ultimo\Database\Memberships\Memberships_Schema
	// → inc/database/memberships/class-memberships-schema.php
	$parts      = explode('\\', $class_fqn);
	$class_name = array_pop($parts); // Memberships_Schema

	// Convert class name to filename: Memberships_Schema → class-memberships-schema.php
	$filename = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';

	// Build the directory path from namespace parts after WP_Ultimo
	// WP_Ultimo\Database\Memberships → inc/database/memberships
	$ns_path    = '';
	$found_root = false;

	foreach ($parts as $part) {
		if ($part === 'WP_Ultimo') {
			$found_root = true;
			$ns_path    = 'inc';
			continue;
		}

		if ($found_root) {
			$ns_path .= '/' . strtolower(str_replace('_', '-', $part));
		}
	}

	$full_path = $plugin_root . '/' . $ns_path . '/' . $filename;

	return file_exists($full_path) ? $full_path : null;
}

/**
 * Map a MySQL type string to a PHPDoc type.
 *
 * @param string $mysql_type The MySQL column type.
 * @return string PHPDoc type.
 */
function mysql_type_to_phpdoc(string $mysql_type): string {

	$type = strtolower($mysql_type);

	if (preg_match('/^(big)?int|smallint|tinyint|mediumint/', $type)) {
		return 'int';
	}

	if (preg_match('/^decimal|float|double/', $type)) {
		return 'string'; // Stored as string in PHP for precision.
	}

	if (preg_match('/^datetime|date|timestamp/', $type)) {
		return 'string';
	}

	if (preg_match('/^enum/', $type)) {
		return 'string';
	}

	return 'string';
}

/**
 * Convert an item_name to a human-readable label.
 *
 * @param string $item_name The item name (e.g. 'discount_code').
 * @return string Human-readable label (e.g. 'discount code').
 */
function humanize(string $item_name): string {

	return str_replace('_', ' ', $item_name);
}

// ── Collect all model metadata ──────────────────────────────────────────────

$models = [];

foreach ($query_files as $query_file) {
	$props = extract_class_properties(
		$query_file,
		[
			'item_name',
			'item_name_plural',
			'table_name',
			'table_schema',
		]
	);

	if (empty($props['item_name']) || empty($props['item_name_plural'])) {
		fwrite(STDERR, "Skipping {$query_file}: missing item_name or item_name_plural\n");
		continue;
	}

	$schema_file = null;
	$columns     = [
		'all'        => [],
		'transition' => [],
	];

	if (! empty($props['table_schema'])) {
		$schema_file = resolve_schema_file($props['table_schema'], $plugin_root);

		if ($schema_file) {
			$columns = extract_schema_columns($schema_file);
		} else {
			fwrite(STDERR, "Schema file not found for {$props['table_schema']}\n");
		}
	}

	$key = $props['item_name'] . '|' . $props['item_name_plural'];

	// Deduplicate: Broadcast_Query, Email_Query, and Post_Query all share
	// item_name = 'post'. We only need one set of hooks per unique
	// item_name/item_name_plural combination.
	if (isset($models[ $key ])) {
		continue;
	}

	$models[ $key ] = [
		'item_name'        => $props['item_name'],
		'item_name_plural' => $props['item_name_plural'],
		'table_name'       => $props['table_name'] ?? $props['item_name_plural'],
		'transition_cols'  => $columns['transition'],
		'all_cols'         => $columns['all'],
		'source_file'      => basename($query_file),
	];
}

// Sort by item name for consistent output.
ksort($models);

fwrite(
	STDERR,
	sprintf(
		"Found %d unique models with %d total transition columns\n",
		count($models),
		array_sum(
			array_map(
				function ($m) {
					return count($m['transition_cols']);
				},
				$models
			)
		)
	)
);

// ── Generate the output file ────────────────────────────────────────────────

$output_file = __DIR__ . '/berlindb-dynamic-hooks.php';
$prefix      = 'wu';
$src         = $query_php_relative;

ob_start();

echo "<?php\n";

// Build the @see line for transition hooks.
$transition_see = $hook_lines['transition'] ? "see {$src}:{$hook_lines['transition']}" : "see {$src}";

echo <<<'HEADER'
/**
 * BerlinDB Dynamic Hooks Reference
 *
 * AUTO-GENERATED — do not edit manually.
 * Regenerate with: php inc/documentation/generate-berlindb-hooks.php
 *
 * This file exists solely so that @10up/wp-hooks-documentor can discover
 * the dynamic hooks fired by BerlinDB's Query class for every registered
 * Ultimate Multisite model. It is never loaded at runtime.
 *
 * @package WP_Ultimo
 * @subpackage Documentation
 * @since 2.3.0
 * @generated
 */

defined('ABSPATH') || exit;

// phpcs:disable -- This file is never executed; it only carries docblocks.

// Variable declarations to keep static analysers and editors happy.
$old_value      = null;
$new_value      = null;
$item_id        = 0;
$query          = null;
$clauses        = [];
$search_columns = [];
$search         = '';
$items          = [];
$item           = [];
$sql            = '';

HEADER;

foreach ($models as $model) {
	$item    = $model['item_name'];
	$items   = $model['item_name_plural'];
	$label   = humanize($item);
	$labels  = humanize($items);
	$section = ucwords($label);

	echo "\n// ─── {$section} " . str_repeat('─', max(1, 72 - strlen($section))) . "\n";

	// ── Transition hooks ────────────────────────────────────────────────
	foreach ($model['transition_cols'] as $col) {
		$col_name  = $col['name'];
		$phpdoc    = mysql_type_to_phpdoc($col['type']);
		$hook_name = "{$prefix}_transition_{$item}_{$col_name}";
		$col_human = str_replace('_', ' ', $col_name);
		$see_line  = $transition_see;

		echo <<<HOOK

/**
 * Fires when the {$col_human} of a {$label} transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `{$col_name}`
 * column for a {$label} row.
 *
 * @since 2.0.0
 * @{$see_line}
 *
 * @param {$phpdoc} \$old_value The previous {$col_human} value.
 * @param {$phpdoc} \$new_value The new {$col_human} value.
 * @param int    \$item_id   The {$label} ID.
 */
do_action('{$hook_name}', \$old_value, \$new_value, \$item_id);

HOOK;
	}

	// ── Query hooks ─────────────────────────────────────────────────────

	$see_pre_get       = $hook_lines['pre_get'] ? "see {$src}:{$hook_lines['pre_get']}" : "see {$src}";
	$see_parse_query   = $hook_lines['parse_query'] ? "see {$src}:{$hook_lines['parse_query']}" : "see {$src}";
	$see_query_clauses = $hook_lines['query_clauses'] ? "see {$src}:{$hook_lines['query_clauses']}" : "see {$src}";
	$see_search_cols   = $hook_lines['search_columns'] ? "see {$src}:{$hook_lines['search_columns']}" : "see {$src}";
	$see_the_items     = $hook_lines['the_items'] ? "see {$src}:{$hook_lines['the_items']}" : "see {$src}";
	$see_filter_item   = $hook_lines['filter_item'] ? "see {$src}:{$hook_lines['filter_item']}" : "see {$src}";
	$see_found_query   = $hook_lines['found_query'] ? "see {$src}:{$hook_lines['found_query']}" : "see {$src}";

	echo <<<HOOK

/**
 * Fires before {$labels} are fetched from the database.
 *
 * @since 2.0.0
 * @{$see_pre_get}
 *
 * @param \\BerlinDB\\Database\\Query \$query The query instance (passed by reference).
 */
do_action_ref_array('{$prefix}_pre_get_{$items}', [&\$query]);

/**
 * Fires after {$labels} query vars have been parsed.
 *
 * @since 2.0.0
 * @{$see_parse_query}
 *
 * @param \\BerlinDB\\Database\\Query \$query The query instance (passed by reference).
 */
do_action_ref_array('{$prefix}_parse_{$items}_query', [&\$query]);

/**
 * Filters the SQL clauses for a {$labels} query.
 *
 * @since 2.0.0
 * @{$see_query_clauses}
 *
 * @param array                    \$clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string \$fields  The SELECT fields.
 *     @type string \$join    The JOIN clause.
 *     @type string \$where   The WHERE clause.
 *     @type string \$orderby The ORDER BY clause.
 *     @type string \$limits  The LIMIT clause.
 *     @type string \$groupby The GROUP BY clause.
 * }
 * @param \\BerlinDB\\Database\\Query \$query The query instance (passed by reference).
 * @return array
 */
\$clauses = apply_filters_ref_array('{$prefix}_{$items}_query_clauses', [\$clauses, &\$query]);

/**
 * Filters the columns to search when performing a {$labels} search.
 *
 * @since 2.0.0
 * @{$see_search_cols}
 *
 * @param string[]                 \$search_columns Array of column names to search.
 * @param string                   \$search         The search term.
 * @param \\BerlinDB\\Database\\Query \$query          The query instance.
 * @return string[]
 */
\$search_columns = apply_filters('{$prefix}_{$items}_search_columns', \$search_columns, \$search, \$query);

/**
 * Filters the found {$labels} after a query.
 *
 * @since 2.0.0
 * @{$see_the_items}
 *
 * @param object[]                 \$items The array of found {$label} objects.
 * @param \\BerlinDB\\Database\\Query \$query The query instance (passed by reference).
 * @return object[]
 */
\$items = apply_filters_ref_array('{$prefix}_the_{$items}', [\$items, &\$query]);

/**
 * Filters a single {$label} item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @{$see_filter_item}
 *
 * @param array                    \$item  The item data as an associative array.
 * @param \\BerlinDB\\Database\\Query \$query The query instance (passed by reference).
 * @return array
 */
\$item = apply_filters_ref_array('{$prefix}_filter_{$item}_item', [\$item, &\$query]);

/**
 * Filters the FOUND_ROWS() query for {$labels}.
 *
 * @since 2.0.0
 * @{$see_found_query}
 *
 * @param string                   \$sql   The SQL query to count found rows.
 * @param \\BerlinDB\\Database\\Query \$query The query instance (passed by reference).
 * @return string
 */
\$sql = apply_filters_ref_array('{$prefix}_found_{$items}_query', [\$sql, &\$query]);

HOOK;
}

echo "\n// phpcs:enable\n";

$content = ob_get_clean();

file_put_contents($output_file, $content);

fwrite(STDERR, "Written to {$output_file}\n");
