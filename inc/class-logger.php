<?php
/**
 * Ultimate Multisite Logger
 *
 * Log string messages to a file with a timestamp. Useful for debugging.
 *
 * @package WP_Ultimo
 * @subpackage Logger
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Ultimate Multisite Logger
 *
 * @since 2.0.0
 */
class Logger extends AbstractLogger {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Holds the log file path.
	 *
	 * @since 2.1
	 * @var string
	 */
	protected $log_file = '';

	/**
	 * Returns the logs folder
	 *
	 * @return string
	 */
	public static function get_logs_folder() {

		return wu_maybe_create_folder('wu-logs');
	}

	/**
	 * Add a log entry to chosen file.
	 *
	 * @param string                       $handle Name of the log file to write to.
	 * @param string|\WP_Error|\Stringable $message Log message to write.
	 * @param string                       $log_level Log level to write.
	 */
	public static function add($handle, $message, $log_level = LogLevel::INFO): void {

		$allowed_log_level = wu_get_setting('error_logging_level', 'default');

		if ('disabled' === $allowed_log_level) {
			return;
		}

		if ('default' === $allowed_log_level) {
			/**
			 * Get from default php reporting level
			 *
			 * Here we are converting the PHP error reporting level to the PSR-3 log level.
			 */
			$reporting_level = error_reporting(); // phpcs:ignore WordPress.PHP

			$psr_log_levels = [
				E_ERROR             => LogLevel::ERROR,
				E_WARNING           => LogLevel::WARNING,
				E_PARSE             => LogLevel::ERROR,
				E_NOTICE            => LogLevel::NOTICE,
				E_CORE_ERROR        => LogLevel::CRITICAL,
				E_CORE_WARNING      => LogLevel::WARNING,
				E_COMPILE_ERROR     => LogLevel::ALERT,
				E_COMPILE_WARNING   => LogLevel::WARNING,
				E_USER_ERROR        => LogLevel::ERROR,
				E_USER_WARNING      => LogLevel::WARNING,
				E_USER_NOTICE       => LogLevel::NOTICE,
				E_RECOVERABLE_ERROR => LogLevel::ERROR,
				E_DEPRECATED        => LogLevel::NOTICE,
				E_USER_DEPRECATED   => LogLevel::NOTICE,
			];

			$current_log_levels = [];

			foreach ($psr_log_levels as $php_level => $psr_level) {
				if ($reporting_level & $php_level) {
					$current_log_levels[] = $psr_level;
				}
			}

			if ( ! in_array($log_level, $current_log_levels, true) && ($reporting_level & ~E_ALL)) {
				return;
			}
		} elseif ('errors' === $allowed_log_level && LogLevel::ERROR !== $log_level && LogLevel::CRITICAL !== $log_level) {
			return;
		}

		$instance = self::get_instance();

		$instance->set_log_file(self::get_logs_folder() . "/$handle.log");

		if (is_wp_error($message)) {
			$message = $message->get_error_message();
		}

		$instance->log($log_level, $message);

		do_action('wu_log_add', $handle, $message, $log_level);
	}

	/**
	 * Get the log contents
	 *
	 * @since  1.6.0
	 *
	 * @param  string  $handle File name to read.
	 * @param  integer $lines Number of lines to retrieve, defaults to 10.
	 * @return array
	 */
	public static function read_lines($handle, $lines = 10) {

		$file = self::get_logs_folder() . "/$handle.log";

		if ( ! file_exists($file)) {
			return [];
		}

		// read file
		$content = file_get_contents($file); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// split into lines
		$arr_content = explode(PHP_EOL, $content);

		// remove last line if empty
		if (empty(end($arr_content))) {
			array_pop($arr_content);
		}

		// return last lines
		return array_slice($arr_content, -$lines);
	}

	/**
	 * Clear entries from chosen file.
	 *
	 * @param mixed $handle Name of the log file to clear.
	 */
	public static function clear($handle): void {

		$file = self::get_logs_folder() . "/$handle.log";

		// Delete the file if it exists.
		if (file_exists($file)) {
			@unlink($file); // phpcs:ignore
		}

		do_action('wu_log_clear', $handle);
	}

	/**
	 * Takes a callable as a parameter and logs how much time it took to execute it.
	 *
	 * @since 2.0.0
	 *
	 * @param string   $handle Name of the log file to write to.
	 * @param string   $message  Log message to write.
	 * @param callable $callback Function to track the execution time.
	 * @return array
	 */
	public static function track_time($handle, $message, $callback) {

		$start = microtime(true);

		$return = call_user_func($callback);

		$time_elapsed = microtime(true) - $start;

		// translators: the placeholder %s will be replaced by the time in seconds (float).
		$message .= ' - ' . sprintf(__('This action took %s seconds.', 'ultimate-multisite'), $time_elapsed);

		self::add($handle, $message);

		return $return;
	}

	/**
	 * Set the log file path.
	 *
	 * @since 2.1
	 *
	 * @param string $log_file The log file path.
	 */
	public function set_log_file($log_file): void {

		$this->log_file = $log_file;
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @since 2.1
	 *
	 * @param mixed              $level   The log level.
	 * @param string|\Stringable $message The message to log.
	 * @param mixed[]            $context The context.
	 *
	 * @return void
	 */
	public function log($level, string|\Stringable $message, array $context = []): void {

		if ( ! $this->is_valid_log_level($level) ) {
			return;
		}

		$formatted_message = $this->format_message($level, $message, $context);

		$this->write_to_file($formatted_message);
	}

	/**
	 * Check if the log level is valid.
	 *
	 * @since 2.1
	 *
	 * @param string $level The log level to check.
	 */
	protected function is_valid_log_level($level): bool {

		$valid_log_levels = [
			LogLevel::EMERGENCY,
			LogLevel::ALERT,
			LogLevel::CRITICAL,
			LogLevel::ERROR,
			LogLevel::WARNING,
			LogLevel::NOTICE,
			LogLevel::INFO,
			LogLevel::DEBUG,
		];

		return in_array($level, $valid_log_levels, true);
	}

	/**
	 * Format the message to be logged.
	 *
	 * @since 2.1
	 *
	 * @param string             $level   The log level.
	 * @param string|\Stringable $message The message to log.
	 * @param array              $context The context of the message.
	 * @return string
	 */
	protected function format_message($level, $message, $context = []) {

		$date = new \DateTime();

		$formatted_message = sprintf(
			'[%s] [%s] %s' . PHP_EOL,
			$date->format('Y-m-d H:i:s'),
			strtoupper($level),
			$message
		);

		return $formatted_message;
	}

	/**
	 * Write the message to the log file.
	 *
	 * @since 2.1
	 *
	 * @param string $message The message to log.
	 * @return void
	 */
	protected function write_to_file($message) {

		if ( ! file_exists($this->log_file)) {
			touch($this->log_file); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		if ( ! is_writable($this->log_file)) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return;
		}

		file_put_contents($this->log_file, $message, FILE_APPEND | LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
