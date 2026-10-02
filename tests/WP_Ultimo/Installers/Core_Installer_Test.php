<?php
/**
 * Tests for setup database installation diagnostics.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @since 2.0.0
 */

namespace WP_Ultimo\Installers;

defined('ABSPATH') || exit;

class Core_Installer_Test extends \WP_UnitTestCase {

	private function run_install($error, $version = false, $throw_exception = false) {
		global $wpdb;

		$loader     = \WP_Ultimo\Loaders\Table_Loader::get_instance();
		$tables     = $loader->get_tables();
		$table      = $this->getMockBuilder(\WP_Ultimo\Database\Engine\Table::class)
			->disableOriginalConstructor()
			->onlyMethods(['install', 'get_version', 'set_schema'])
			->getMock();
		$table_name = new \ReflectionProperty($table, 'table_name');
		$table_name->setValue($table, 'wu_domains');

		$table->method('install')->willReturnCallback(
			function () use ($wpdb, $error, $throw_exception) {
				$this->assertTrue($wpdb->suppress_errors);
				$this->assertSame('', $wpdb->last_error);
				if (is_callable($error)) {
					$error($wpdb);
				} else {
					$wpdb->last_error = $error;
				}

				if ($throw_exception) {
					throw new \RuntimeException('Installation callback failed.');
				}
			}
		);
		$table->method('get_version')->willReturnCallback(
			static function () use ($wpdb, $version) {
				// Simulate the version lookup overwriting the original database error.
				$wpdb->get_var('SELECT 1');
				return $version;
			}
		);
		foreach ($tables as $name => $original) {
			$loader->$name = $table;
		}

		try {
			return Core_Installer::get_instance()->handle(true, 'database_tables', $this);
		} finally {
			foreach ($tables as $name => $original) {
				$loader->$name = $original;
			}
		}
	}

	public function test_creation_error_survives_version_lookup_and_rollback(): void {
		$error  = 'CREATE command denied to user for table wu_domains';
		$result = $this->run_install($error);

		$this->assertWPError($result);
		$this->assertSame('database_tables', $result->get_error_code());
		$this->assertStringContainsString('Installation of the table wu_domains failed', $result->get_error_message());
		$this->assertStringContainsString('Database error: ' . $error, $result->get_error_message());
	}

	public function test_real_database_error_reaches_the_installer_response_without_raw_output(): void {
		$database_error = '';
		ob_start();

		try {
			$result = $this->run_install(
				static function ($wpdb) use (&$database_error) {
					// Deliberately invalid DDL fails without creating or changing a table.
					$wpdb->query('CREATE TABLE wu_install_diagnostic_failure (');
					$database_error = $wpdb->last_error;
				}
			);
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertNotEmpty($database_error);
		$this->assertWPError($result);
		$this->assertStringContainsString(esc_html($database_error), $result->get_error_message());
		$this->assertSame('', $output);
	}

	public function test_database_error_is_escaped_for_the_status_cell(): void {
		$result = $this->run_install('Invalid default value: <img src=x onerror=alert(1)>');

		$this->assertStringNotContainsString('<img', $result->get_error_message());
		$this->assertStringContainsString('&lt;img', $result->get_error_message());
	}

	public function test_missing_database_error_has_hosting_guidance(): void {
		$result = $this->run_install('');

		$this->assertWPError($result);
		$this->assertStringContainsString('An unknown error has occurred.', $result->get_error_message());
		$this->assertStringContainsString('Please check the server error logs.', $result->get_error_message());
	}

	public function test_database_password_is_redacted(): void {
		if (! defined('DB_PASSWORD') || '' === DB_PASSWORD) {
			$this->markTestSkipped('Requires a non-empty database password.');
		}

		$result = $this->run_install('Database diagnostic: ' . DB_PASSWORD);

		$this->assertStringContainsString('[redacted]', $result->get_error_message());
		$this->assertStringNotContainsString(esc_html(DB_PASSWORD), $result->get_error_message());
	}

	public function test_successful_installation_still_succeeds(): void {
		$this->assertTrue($this->run_install('', '1.0.0'));
	}

	public function test_error_suppression_is_restored_after_failure_or_exception(): void {
		global $wpdb;

		$previous = $wpdb->suppress_errors(false);

		try {
			$this->run_install('CREATE command denied');
			$this->assertFalse($wpdb->suppress_errors);

			$result = $this->run_install('', false, true);
			$this->assertWPError($result);
			$this->assertFalse($wpdb->suppress_errors);
		} finally {
			$wpdb->suppress_errors($previous);
		}
	}
}
