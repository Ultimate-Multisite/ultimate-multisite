<?php
/**
 * Test case for Logger.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests;

use WP_Ultimo\Logger;
use Psr\Log\LogLevel;

/**
 * Test Logger functionality.
 */
class Logger_Test extends \WP_UnitTestCase {

	/**
	 * Test singleton instance.
	 */
	public function test_singleton_instance(): void {

		$logger1 = Logger::get_instance();
		$logger2 = Logger::get_instance();

		$this->assertSame($logger1, $logger2);
		$this->assertInstanceOf(Logger::class, $logger1);
	}

	/**
	 * Test get_logs_folder returns a string path.
	 */
	public function test_get_logs_folder(): void {

		$folder = Logger::get_logs_folder();

		$this->assertIsString($folder);
		$this->assertNotEmpty($folder);
	}

	/**
	 * Test set_log_file sets the file path.
	 */
	public function test_set_log_file(): void {

		$logger = Logger::get_instance();

		$tmp_file = tempnam(sys_get_temp_dir(), 'wu_test_log_');

		$logger->set_log_file($tmp_file);

		// Use reflection to verify the file was set
		$reflection = new \ReflectionClass($logger);
		$prop       = $reflection->getProperty('log_file');

		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

		$this->assertEquals($tmp_file, $prop->getValue($logger));

		@unlink($tmp_file);
	}

	/**
	 * Test log writes to file.
	 */
	public function test_log_writes_to_file(): void {

		$logger   = Logger::get_instance();
		$tmp_file = tempnam(sys_get_temp_dir(), 'wu_test_log_');

		$logger->set_log_file($tmp_file);
		$logger->log(LogLevel::INFO, 'Test message');

		$contents = file_get_contents($tmp_file);

		$this->assertStringContainsString('Test message', $contents);
		$this->assertStringContainsString('[INFO]', $contents);

		@unlink($tmp_file);
	}

	/**
	 * Test log with different levels.
	 */
	public function test_log_with_different_levels(): void {

		$logger   = Logger::get_instance();
		$tmp_file = tempnam(sys_get_temp_dir(), 'wu_test_log_');

		$logger->set_log_file($tmp_file);

		$logger->log(LogLevel::ERROR, 'Error message');
		$logger->log(LogLevel::WARNING, 'Warning message');
		$logger->log(LogLevel::DEBUG, 'Debug message');

		$contents = file_get_contents($tmp_file);

		$this->assertStringContainsString('[ERROR]', $contents);
		$this->assertStringContainsString('[WARNING]', $contents);
		$this->assertStringContainsString('[DEBUG]', $contents);

		@unlink($tmp_file);
	}

	/**
	 * Test log with invalid level does not write.
	 */
	public function test_log_with_invalid_level(): void {

		$logger   = Logger::get_instance();
		$tmp_file = tempnam(sys_get_temp_dir(), 'wu_test_log_');

		// Clear the file
		file_put_contents($tmp_file, '');

		$logger->set_log_file($tmp_file);
		$logger->log('invalid_level', 'Should not appear');

		$contents = file_get_contents($tmp_file);

		$this->assertEmpty($contents);

		@unlink($tmp_file);
	}

	/**
	 * Test is_valid_log_level with valid levels.
	 */
	public function test_is_valid_log_level(): void {

		$logger     = Logger::get_instance();
		$reflection = new \ReflectionClass($logger);
		$method     = $reflection->getMethod('is_valid_log_level');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$this->assertTrue($method->invoke($logger, LogLevel::EMERGENCY));
		$this->assertTrue($method->invoke($logger, LogLevel::ALERT));
		$this->assertTrue($method->invoke($logger, LogLevel::CRITICAL));
		$this->assertTrue($method->invoke($logger, LogLevel::ERROR));
		$this->assertTrue($method->invoke($logger, LogLevel::WARNING));
		$this->assertTrue($method->invoke($logger, LogLevel::NOTICE));
		$this->assertTrue($method->invoke($logger, LogLevel::INFO));
		$this->assertTrue($method->invoke($logger, LogLevel::DEBUG));
		$this->assertFalse($method->invoke($logger, 'invalid'));
		$this->assertFalse($method->invoke($logger, ''));
	}

	/**
	 * Test format_message includes timestamp and level.
	 */
	public function test_format_message(): void {

		$logger     = Logger::get_instance();
		$reflection = new \ReflectionClass($logger);
		$method     = $reflection->getMethod('format_message');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($logger, LogLevel::INFO, 'Test format');

		$this->assertStringContainsString('[INFO]', $result);
		$this->assertStringContainsString('Test format', $result);
		// Should contain a date-like pattern
		$this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2}/', $result);
	}

	/**
	 * Test read_lines returns empty for nonexistent file.
	 */
	public function test_read_lines_nonexistent_file(): void {

		$lines = Logger::read_lines('nonexistent_handle_' . uniqid());

		$this->assertIsArray($lines);
		$this->assertEmpty($lines);
	}

	/**
	 * Test read_lines returns correct number of lines.
	 */
	public function test_read_lines_returns_lines(): void {

		$handle = 'test_read_' . uniqid();

		// Write some log entries
		$logger   = Logger::get_instance();
		$log_file = Logger::get_logs_folder() . "/{$handle}.log";

		$logger->set_log_file($log_file);

		for ($i = 1; $i <= 5; $i++) {
			$logger->log(LogLevel::INFO, "Line {$i}");
		}

		$lines = Logger::read_lines($handle, 3);

		$this->assertIsArray($lines);
		$this->assertCount(3, $lines);
		$this->assertStringContainsString('Line 3', $lines[0]);
		$this->assertStringContainsString('Line 4', $lines[1]);
		$this->assertStringContainsString('Line 5', $lines[2]);

		// Clean up
		Logger::clear($handle);
	}

	/**
	 * Test clear removes log file.
	 */
	public function test_clear_removes_file(): void {

		$handle   = 'test_clear_' . uniqid();
		$log_file = Logger::get_logs_folder() . "/{$handle}.log";

		// Create the file
		$logger = Logger::get_instance();
		$logger->set_log_file($log_file);
		$logger->log(LogLevel::INFO, 'To be cleared');

		$this->assertFileExists($log_file);

		Logger::clear($handle);

		$this->assertFileDoesNotExist($log_file);
	}

	/**
	 * Test clear fires action hook.
	 */
	public function test_clear_fires_action(): void {

		$fired = false;

		add_action('wu_log_clear', function ($handle) use (&$fired) {
			$fired = $handle;
		});

		Logger::clear('test_action_' . uniqid());

		$this->assertIsString($fired);
	}

	/**
	 * Test track_time returns callback result.
	 */
	public function test_track_time_returns_result(): void {

		wu_save_setting('error_logging_level', 'all');

		$result = Logger::track_time('test_track', 'Tracking test', function () {
			return 'callback_result';
		});

		$this->assertEquals('callback_result', $result);

		// Clean up
		Logger::clear('test_track');
	}

	/**
	 * Test add fires wu_log_add action.
	 */
	public function test_add_fires_action(): void {

		wu_save_setting('error_logging_level', 'all');

		$fired_data = null;

		add_action('wu_log_add', function ($handle, $message, $level) use (&$fired_data) {
			$fired_data = compact('handle', 'message', 'level');
		}, 10, 3);

		Logger::add('test_action_handle', 'Test action message', LogLevel::INFO);

		$this->assertNotNull($fired_data);
		$this->assertEquals('test_action_handle', $fired_data['handle']);
		$this->assertEquals('Test action message', $fired_data['message']);

		// Clean up
		Logger::clear('test_action_handle');
	}

	/**
	 * Test add with disabled logging does nothing.
	 *
	 * Note: wu_save_setting('error_logging_level', 'disabled') triggers a bug
	 * where is_callable('disabled') returns true (WordPress disabled() function).
	 * We bypass the Settings class and set the option directly.
	 */
	public function test_add_disabled_logging(): void {

		// Directly manipulate the settings array to avoid the is_callable bug
		$settings                        = \WP_Ultimo()->settings->get_all();
		$settings['error_logging_level'] = 'disabled';
		wu_save_option('wp-ultimo_settings', $settings);

		// Force settings reload
		$reflection = new \ReflectionClass(\WP_Ultimo()->settings);
		$prop       = $reflection->getProperty('settings');

		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

		$prop->setValue(\WP_Ultimo()->settings, $settings);

		$handle   = 'test_disabled_' . uniqid();
		$log_file = Logger::get_logs_folder() . "/{$handle}.log";

		Logger::add($handle, 'Should not be logged');

		$this->assertFileDoesNotExist($log_file);
	}

	/**
	 * Test add with WP_Error message.
	 */
	public function test_add_with_wp_error(): void {

		wu_save_setting('error_logging_level', 'all');

		$handle = 'test_wp_error_' . uniqid();
		$error  = new \WP_Error('test_code', 'WP Error message');

		Logger::add($handle, $error, LogLevel::ERROR);

		$lines = Logger::read_lines($handle, 1);

		$this->assertNotEmpty($lines);
		$this->assertStringContainsString('WP Error message', $lines[0]);

		// Clean up
		Logger::clear($handle);
	}

	/**
	 * Test PSR-3 convenience methods.
	 */
	public function test_psr3_convenience_methods(): void {

		$logger   = Logger::get_instance();
		$tmp_file = tempnam(sys_get_temp_dir(), 'wu_test_psr3_');

		$logger->set_log_file($tmp_file);

		$logger->info('Info message');
		$logger->error('Error message');
		$logger->warning('Warning message');
		$logger->debug('Debug message');
		$logger->notice('Notice message');
		$logger->critical('Critical message');
		$logger->alert('Alert message');
		$logger->emergency('Emergency message');

		$contents = file_get_contents($tmp_file);

		$this->assertStringContainsString('Info message', $contents);
		$this->assertStringContainsString('Error message', $contents);
		$this->assertStringContainsString('Warning message', $contents);
		$this->assertStringContainsString('Debug message', $contents);
		$this->assertStringContainsString('Notice message', $contents);
		$this->assertStringContainsString('Critical message', $contents);
		$this->assertStringContainsString('Alert message', $contents);
		$this->assertStringContainsString('Emergency message', $contents);

		@unlink($tmp_file);
	}
}
