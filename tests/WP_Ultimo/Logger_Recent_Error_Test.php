<?php
/**
 * Test recent error tracking for Logger.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests;

use Psr\Log\LogLevel;
use WP_Ultimo\Logger;

/**
 * Test recent error tracking for Logger.
 */
class Logger_Recent_Error_Test extends \WP_UnitTestCase {

	/**
	 * Clean the recent error marker before each test.
	 */
	public function set_up(): void {

		parent::set_up();

		delete_site_option('wu_recent_error_log_entry');
		wu_save_setting('error_logging_level', 'all');
	}

	/**
	 * Clean the recent error marker after each test.
	 */
	public function tear_down(): void {

		delete_site_option('wu_recent_error_log_entry');

		parent::tear_down();
	}

	/**
	 * Error-level log entries update the recent error marker.
	 */
	public function test_add_error_updates_recent_error_marker(): void {

		$handle = 'test_recent_error_' . uniqid();

		Logger::add($handle, 'Most recent error message', LogLevel::ERROR);

		$entry = Logger::get_recent_error();

		$this->assertIsArray($entry);
		$this->assertSame(sanitize_key($handle), $entry['handle']);
		$this->assertSame('Most recent error message', $entry['message']);
		$this->assertSame(LogLevel::ERROR, $entry['level']);

		Logger::clear($handle);
	}

	/**
	 * Non-error log entries do not update the recent error marker.
	 */
	public function test_add_info_does_not_update_recent_error_marker(): void {

		$handle = 'test_recent_info_' . uniqid();

		Logger::add($handle, 'Informational message', LogLevel::INFO);

		$this->assertFalse(Logger::get_recent_error());

		Logger::clear($handle);
	}
}
