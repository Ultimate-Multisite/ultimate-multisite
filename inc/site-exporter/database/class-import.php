<?php
/**
 * Database Import Class
 *
 * PDO class to import sql from a .sql file
 * Adapted from thamaraiselvam's import-database-file-using-php class.
 *
 * @see https://github.com/thamaraiselvam/import-database-file-using-php
 * @package WP_Ultimo\Site_Exporter\Database
 * @since 2.5.0
 */

// phpcs:disable WordPress.NamingConventions, WordPress.PHP.YodaConditions, WordPress.WP.AlternativeFunctions, Generic.CodeAnalysis, Squiz.Commenting

namespace WP_Ultimo\Site_Exporter\Database;

use PDO;
use PDOException;
use Exception;
use Error;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * PDO class to import sql from a .sql file
 */
class Import {

	/**
	 * PDO database connection.
	 *
	 * @var PDO
	 */
	private PDO $db;

	/**
	 * The SQL file to import.
	 *
	 * @var string
	 */
	private string $filename;

	/**
	 * Database username.
	 *
	 * @var string
	 */
	private string $username;

	/**
	 * Database password.
	 *
	 * @var string
	 */
	private string $password;

	/**
	 * Database name.
	 *
	 * @var string
	 */
	private string $database;

	/**
	 * Database host.
	 *
	 * @var string
	 */
	private string $host;

	/**
	 * Force drop tables flag.
	 *
	 * @var bool
	 */
	private bool $forceDropTables;

	/**
	 * Constructor.
	 *
	 * @param string   $filename        Name of the file to import.
	 * @param string   $username        Database username.
	 * @param string   $password        Database password.
	 * @param string   $database        Database name.
	 * @param string   $host            Address host localhost or ip address.
	 * @param bool     $dropTables      When set to true delete the database tables.
	 * @param bool     $forceDropTables When set to true foreign key checks will be disabled during deletion.
	 * @param int|bool $site_id         The site ID for multisite.
	 */
	public function __construct(
		string $filename,
		string $username,
		string $password,
		string $database,
		string $host,
		bool $dropTables,
		bool $forceDropTables = false,
		$site_id = false
	) {
		// Set the variables to properties
		$this->filename        = $filename;
		$this->username        = $username;
		$this->password        = $password;
		$this->database        = $database;
		$this->host            = $host;
		$this->forceDropTables = $forceDropTables;

		// Connect to the database
		$this->connect();

		// If dropTables is true then delete the tables
		if ($dropTables === true) {
			$this->dropTables($site_id);
		}

		// Open file and import the sql
		$this->openfile();
	}

	/**
	 * Connect to the database
	 *
	 * @return void
	 */
	private function connect(): void {

		try {
			$this->db = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->database, $this->username, $this->password);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'Cannot connect: ' . $e->getMessage() . "\n";
		}
	}

	/**
	 * Run queries
	 *
	 * @param string $query The query to perform.
	 * @return \PDOStatement|false
	 */
	private function query(string $query) {

		try {
			return $this->db->query($query);
		} catch (PDOException $e) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'Error with query: ' . $e->getMessage() . "\n";
			return false;
		} catch (Error $e) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'Error with query: ' . $e->getMessage() . "\n";
			return false;
		}
	}

	/**
	 * Select all tables, loop through and delete/drop them.
	 *
	 * @param int|bool $site_id The site ID.
	 * @return void
	 */
	private function dropTables($site_id = false): void {

		global $wpdb;

		if (! $site_id) {
			return;
		}

		$table = is_multisite() ? $wpdb->base_prefix . $site_id : $wpdb->base_prefix;

		// Get list of tables — use PDO::quote() to safely escape the LIKE pattern.
		$like_pattern = $this->db->quote($table . '%');
		$tables = $this->query('SHOW TABLES LIKE ' . $like_pattern);
		if ($tables !== null && $tables !== false) {
			// Loop through tables
			$results = $tables->fetchAll(PDO::FETCH_COLUMN);
			foreach ($results as $table) {
				if (strpos($table, 'user') !== false) {
					continue;
				}

				if ($this->forceDropTables === true) {
					// Delete table with foreign key checks disabled — split into
					// separate statements because PDO does not support multi-statement
					// queries by default (PDO::ATTR_EMULATE_PREPARES off).
					$this->query('SET FOREIGN_KEY_CHECKS=0');
					$this->query('DROP TABLE `' . $table . '`');
					$this->query('SET FOREIGN_KEY_CHECKS=1');
				} else {
					// Delete table
					$this->query('DROP TABLE `' . $table . '`');
				}
			}
		}
	}

	/**
	 * Open $filename, loop through and import the commands
	 *
	 * @return void
	 */
	private function openfile(): void {

		try {
			// If file cannot be found throw error
			if (! file_exists($this->filename)) {
				throw new Exception("Error: File not found.\n");
			}

			// Read in entire file
			$fp = fopen($this->filename, 'r');

			// Temporary variable, used to store current query
			$templine = '';

			// Loop through each line
			while (($line = fgets($fp)) !== false) {
				// Skip it if it's a comment
				if (substr($line, 0, 2) === '--' || $line === '') {
					continue;
				}

				// Add this line to the current segment
				$templine .= $line;

				// If it has a semicolon at the end, it's the end of the query
				if (substr(trim($line), -1, 1) === ';') {
					$this->query($templine);
					// Reset temp variable to empty
					$templine = '';
				}
			}

			// Close the file
			fclose($fp);
		} catch (Exception $e) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'Error importing: ' . $e->getMessage() . "\n";
		}
	}
}
