<?php
/**
 * Database Replace Class
 *
 * Runs search & replace on a database.
 * Adapted from interconnect/it Search-Replace-DB.
 *
 * @see https://github.com/interconnectit/Search-Replace-DB/blob/master/srdb.class.php
 * @package WP_Ultimo\Site_Exporter\Database
 * @since 2.5.0
 */

// phpcs:disable WordPress.DB.PreparedSQL, WordPress.NamingConventions, WordPress.PHP.YodaConditions, Squiz.Commenting

namespace WP_Ultimo\Site_Exporter\Database;

use Exception;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Class Replace
 *
 * @package WP_Ultimo\Site_Exporter\Database
 */
class Replace {

	/**
	 * The Database Interface Object
	 *
	 * @var Manager
	 */
	private Manager $dbm;

	/**
	 * Count of rows to be replaced at a time
	 *
	 * @var int
	 */
	private int $page_size = 100;

	/**
	 * Dry run flag.
	 *
	 * @var bool
	 */
	private bool $dry_run = true;

	/**
	 * Max execution time handler.
	 *
	 * @var Max_Execution_Time
	 */
	private Max_Execution_Time $max_execution;

	/**
	 * Store csv data
	 *
	 * @var array
	 */
	private array $csv_data = [];

	/**
	 * Replace constructor.
	 *
	 * @param Manager            $dbm           Database manager.
	 * @param Max_Execution_Time $max_execution Max execution time handler.
	 */
	public function __construct(Manager $dbm, Max_Execution_Time $max_execution) {

		$this->dbm           = $dbm;
		$this->max_execution = $max_execution;
	}

	/**
	 * The main loop for search and replace.
	 *
	 * This walks every table in the db that was selected and then
	 * walks every row and column replacing all occurrences of a string with another.
	 * We split large tables into blocks when dealing with them to save on memory consumption.
	 *
	 * @param string      $search  What we want to replace.
	 * @param string      $replace What we want to replace it with.
	 * @param array       $tables  The name of the table we want to look at.
	 * @param string|null $csv     CSV data.
	 *
	 * @return array|\WP_Error Collection of information gathered during the run.
	 * @throws \Throwable Whatever exception is thrown if WP_DEBUG is true.
	 */
	public function run_search_replace(string $search, string $replace, array $tables, ?string $csv = null) {

		if ($search === $replace && '' !== $search) {
			return new \WP_Error('error', esc_html__('Search and replace pattern can\'t be the same!', 'ultimate-multisite'));
		}

		$this->max_execution->set();

		$report = [
			'errors'        => null,
			'changes'       => [],
			'tables'        => '0',
			'changes_count' => '0',
		];

		foreach ((array) $tables as $table) {
			// Count tables.
			++$report['tables'];
			$table_report = $this->replace_values($search, $replace, $table, $csv);
			// Log changes if any.
			if (0 !== $table_report['change']) {
				$report['changes'][ $table ] = $table_report;
				$report['changes_count']    += $table_report['change'];
			}
		}

		$this->max_execution->restore();

		return $report;
	}

	/**
	 * Replace data values inside the table.
	 *
	 * @param string      $search  Search string.
	 * @param string      $replace Replace string.
	 * @param string      $table   Table name.
	 * @param string|null $csv     CSV data.
	 *
	 * @return array
	 * @throws \Throwable Whatever exception is thrown if WP_DEBUG is true.
	 */
	public function replace_values(string $search = '', string $replace = '', string $table = '', ?string $csv = null): array {

		$table_report = [
			'table_name' => $table,
			'rows'       => 0,
			'change'     => 0,
			'changes'    => [],
			'updates'    => 0,
			'start'      => microtime(),
			'end'        => microtime(),
			'errors'     => [],
		];

		// Check we have a search string, bail if not.
		if (empty($search) && empty($csv)) {
			$table_report['errors'][] = 'Search string is empty';

			return $table_report;
		}

		// Grab table structure in order to determine which columns are used to store serialized values in it.
		$table_structure = $this->dbm->get_table_structure($table);

		if (! $table_structure) {
			return $table_report;
		}

		$maybe_serialized = [];
		foreach ($table_structure as $struct) {
			// Longtext is used for meta_values as best practice in all of the automatic products.
			if (0 === stripos($struct->Type, 'longtext')) {
				$maybe_serialized[] = strtolower($struct->Field);
			}
		}

		// Split columns array in primary key string and columns array.
		$columns = $this->dbm->get_columns($table);

		list($primary_key, $columns) = $columns;

		if (null === $primary_key) {
			$table_report['errors'][] = "The table \"{$table}\" has no primary key. Changes will have to be made manually.";

			return $table_report;
		}

		$table_report['start'] = microtime();

		// Count the number of rows we have in the table if large we'll split into blocks
		$row_count = $this->dbm->get_rows($table);

		$page_size = $this->page_size;
		$pages     = ceil($row_count / $page_size);

		// Prepare CSV data
		if (null !== $csv) {
			$csv_lines = explode("\n", $csv);
			$csv_head  = str_getcsv('search,replace');
			foreach ($csv_lines as $line) {
				$this->csv_data[] = array_combine($csv_head, str_getcsv($line));
			}
		}

		for ($page = 0; $page < $pages; $page++) {
			$start = $page * $page_size;

			// Grab the content of the table
			$data = $this->dbm->get_table_content($table, $start, $page_size);

			if (! $data) {
				$table_report['errors'][] = 'no data in table ' . $table;
			}

			foreach ($data as $row) {
				++$table_report['rows'];

				$update_sql = [];
				$where_sql  = [];
				$update     = true;

				foreach ($columns as $column) {
					// Skip the GUID column per WordPress Codex.
					if ($column === 'guid') {
						continue;
					}

					$data_to_fix = $row[ $column ];

					if ($column === $primary_key) {
						$where_sql[] = $column . ' = "' . $this->mysql_escape_mimic($data_to_fix) . '"';
						continue;
					}

					// Run a search replace on the data that'll respect the serialisation.
					if (is_serialized($data_to_fix, false)
						&& in_array(strtolower($column), $maybe_serialized, true)
					) {
						// Run a search replace on the data that'll respect the serialisation.
						$edited_data = $this->recursive_unserialize_replace($search, $replace, $data_to_fix);
					} else {
						$edited_data = str_replace($search, $replace, $data_to_fix);
					}

					// Run a search replace by CSV parameters if CSV input present
					if (null !== $csv) {
						foreach ($this->csv_data as $entry) {
							$edited_data = is_serialized($edited_data, false) ?
								$this->recursive_unserialize_replace(
									$entry['search'],
									$entry['replace'],
									$edited_data
								) : str_replace($entry['search'], $entry['replace'], $data_to_fix);
						}
					}

					// Something was changed.
					if ($edited_data !== $data_to_fix) {
						++$table_report['change'];

						// log changes
						$table_report['changes'][] = [
							'row'    => $table_report['rows'],
							'column' => $column,
							'from'   => $data_to_fix,
							'to'     => $edited_data,
						];

						$update_sql[] = $column . ' = "' . $this->mysql_escape_mimic($edited_data) . '"';
						$update       = true;
					}
				}

				// Determine what to do with updates.
				if (true === $this->dry_run) {
					// Don't do anything if a dry run.
					continue;
				}

				if ($update && ! empty($where_sql) && ! empty($update_sql)) {
					// If there are changes to make, run the query.
					$result = $this->dbm->update($table, $update_sql, $where_sql);

					if (! $result) {
						$table_report['errors'][] = sprintf(
							/* translators: $1 is the number of rows found in database */
							esc_html__('Error updating row: %d.', 'ultimate-multisite'),
							$table_report['rows']
						);
					} else {
						++$table_report['updates'];
					}
				}
			}
		}

		$table_report['end'] = microtime(true);

		$this->dbm->flush();

		return $table_report;
	}

	/**
	 * Mimics the mysql_real_escape_string function.
	 *
	 * Adapted from a post by 'feedr' on php.net.
	 *
	 * @link   http://php.net/manual/en/function.mysql-real-escape-string.php#101248
	 *
	 * @param  array|string $input The string to escape.
	 * @return array|string
	 */
	public function mysql_escape_mimic($input) {

		if (is_array($input)) {
			return array_map([$this, 'mysql_escape_mimic'], $input);
		}

		if (! empty($input) && is_string($input)) {
			return str_replace(
				['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
				['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
				$input
			);
		}

		return $input;
	}

	/**
	 * Recursive unserialize replace
	 *
	 * Take a serialised array and unserialize it replacing elements as needed and
	 * unserializing any subordinate arrays and performing the replace on those too.
	 *
	 * @param string              $from       String we're looking to replace.
	 * @param string              $to         What we want it to be replaced with.
	 * @param array|string|object $data       Used to pass any subordinate arrays back to in.
	 * @param bool                $serialised Does the array passed via $data need serialising.
	 *
	 * @throws \Throwable Whatever exception is thrown if WP_DEBUG is true.
	 * @return mixed The original array with all elements replaced as needed.
	 */
	public function recursive_unserialize_replace(string $from = '', string $to = '', $data = '', bool $serialised = true) {

		// Some unserialized data cannot be re-serialised eg. SimpleXMLElements.
		try {
			$unserialized = is_serialized($data, false) ? maybe_unserialize($data) : false;

			if ($unserialized !== false && ! is_serialized_string($data)) {
				$data = $this->recursive_unserialize_replace($from, $to, $unserialized, false);
			} elseif (is_array($data)) {
				$_tmp = [];
				foreach ((array) $data as $key => $value) {
					$_tmp[ $key ] = $this->recursive_unserialize_replace($from, $to, $value, false);
				}

				$data = $_tmp;

				unset($_tmp);
			} elseif ($data instanceof \__PHP_Incomplete_Class) {
				return $data;
			} elseif (is_object($data)) {
				$_tmp  = $data;
				$props = get_object_vars($data);
				foreach ($props as $key => $value) {
					$_tmp->$key = $this->recursive_unserialize_replace($from, $to, $value, false);
				}

				$data = $_tmp;

				unset($_tmp);
			} else {
				// Don't process data that isn't a string.
				if (! is_string($data)) {
					return $data;
				}

				$marker = false;

				if (is_serialized_string($data)) {
					$data   = maybe_unserialize($data);
					$marker = true;
				}

				$tmp_data = $data;
				$data     = str_replace($from, $to, $data);

				// Do not allow to return valid serialized data,
				// If after replacement data is_serialized then add one | to the replacement.
				if (is_serialized($data, false)) {
					$data = str_replace($from, '|' . $to, $tmp_data);
				}

				if ($marker) {
					$data = maybe_serialize($data);
				}
			}

			if ($serialised) {
				$data = serialize($data); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			}
		} catch (Exception $throwable) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				throw $throwable;
			}

			/**
			 * Error action for search and replace.
			 *
			 * @param \Throwable $throwable The exception.
			 */
			do_action('wu_site_exporter_replace_error', $throwable);
		}

		return $data;
	}

	/**
	 * Returns true, if dry run, false if not
	 *
	 * @return bool
	 */
	public function get_dry_run(): bool {

		return $this->dry_run;
	}

	/**
	 * Sets the dry run option.
	 *
	 * @param bool $state TRUE for dry run, FALSE for writing changes to DB.
	 * @return bool
	 */
	public function set_dry_run(bool $state): bool {

		$this->dry_run = $state;

		return $state;
	}
}
