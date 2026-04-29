<?php
/**
 * Tests for the Tracker class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Test class for Tracker.
 */
class Tracker_Test extends WP_UnitTestCase {

	/**
	 * Get a Tracker instance.
	 *
	 * @return \WP_Ultimo\Tracker
	 */
	protected function get_tracker(): \WP_Ultimo\Tracker {

		return \WP_Ultimo\Tracker::get_instance();
	}

	/**
	 * Call a protected method on the Tracker.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	protected function call_protected($method, $args = []) {

		$tracker    = $this->get_tracker();
		$reflection = new \ReflectionMethod($tracker, $method);

		if (PHP_VERSION_ID < 80100) {
			$reflection->setAccessible(true);
		}

		return $reflection->invokeArgs($tracker, $args);
	}

	/**
	 * Test get_instance returns Tracker.
	 */
	public function test_get_instance(): void {

		$tracker = $this->get_tracker();

		$this->assertInstanceOf(\WP_Ultimo\Tracker::class, $tracker);
	}

	/**
	 * Test is_tracking_enabled always returns false (background telemetry removed in 2.5.1).
	 */
	public function test_is_tracking_enabled(): void {

		$result = $this->get_tracker()->is_tracking_enabled();

		$this->assertFalse($result);
	}

	/**
	 * Test get_site_hash returns a SHA-256 hash string.
	 */
	public function test_get_site_hash(): void {

		$hash = $this->call_protected('get_site_hash');

		$this->assertIsString($hash);
		$this->assertSame(64, strlen($hash)); // SHA-256 produces 64 hex chars.
	}

	/**
	 * Test get_environment_data returns expected keys.
	 */
	public function test_get_environment_data(): void {

		$data = $this->call_protected('get_environment_data');

		$this->assertIsArray($data);
		$this->assertArrayHasKey('php_version', $data);
		$this->assertArrayHasKey('wp_version', $data);
		$this->assertArrayHasKey('mysql_version', $data);
		$this->assertArrayHasKey('server_software', $data);
		$this->assertArrayHasKey('is_ssl', $data);
		$this->assertArrayHasKey('is_multisite', $data);
		$this->assertArrayHasKey('locale', $data);
	}

	/**
	 * Test get_server_software returns known server types.
	 */
	public function test_get_server_software_apache(): void {

		$_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.41';

		$result = $this->call_protected('get_server_software');

		$this->assertSame('Apache', $result);
	}

	/**
	 * Test get_server_software with nginx.
	 */
	public function test_get_server_software_nginx(): void {

		$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18.0';

		$result = $this->call_protected('get_server_software');

		$this->assertSame('Nginx', $result);
	}

	/**
	 * Test get_server_software with litespeed.
	 */
	public function test_get_server_software_litespeed(): void {

		$_SERVER['SERVER_SOFTWARE'] = 'LiteSpeed';

		$result = $this->call_protected('get_server_software');

		$this->assertSame('LiteSpeed', $result);
	}

	/**
	 * Test get_server_software with IIS.
	 */
	public function test_get_server_software_iis(): void {

		$_SERVER['SERVER_SOFTWARE'] = 'Microsoft-IIS/10.0';

		$result = $this->call_protected('get_server_software');

		$this->assertSame('IIS', $result);
	}

	/**
	 * Test get_server_software with unknown.
	 */
	public function test_get_server_software_other(): void {

		$_SERVER['SERVER_SOFTWARE'] = 'SomeCustomServer/1.0';

		$result = $this->call_protected('get_server_software');

		$this->assertSame('Other', $result);
	}

	/**
	 * Test get_server_software when not set.
	 */
	public function test_get_server_software_not_set(): void {

		unset($_SERVER['SERVER_SOFTWARE']);

		$result = $this->call_protected('get_server_software');

		$this->assertSame('Other', $result);
	}

	/**
	 * Test anonymize_count ranges.
	 */
	public function test_anonymize_count_zero(): void {

		$this->assertSame('0', $this->call_protected('anonymize_count', [0]));
	}

	/**
	 * Test anonymize_count 1-10 range.
	 */
	public function test_anonymize_count_small(): void {

		$this->assertSame('1-10', $this->call_protected('anonymize_count', [5]));
		$this->assertSame('1-10', $this->call_protected('anonymize_count', [10]));
	}

	/**
	 * Test anonymize_count 11-50 range.
	 */
	public function test_anonymize_count_medium(): void {

		$this->assertSame('11-50', $this->call_protected('anonymize_count', [11]));
		$this->assertSame('11-50', $this->call_protected('anonymize_count', [50]));
	}

	/**
	 * Test anonymize_count 51-100 range.
	 */
	public function test_anonymize_count_large(): void {

		$this->assertSame('51-100', $this->call_protected('anonymize_count', [51]));
		$this->assertSame('51-100', $this->call_protected('anonymize_count', [100]));
	}

	/**
	 * Test anonymize_count 101-500 range.
	 */
	public function test_anonymize_count_very_large(): void {

		$this->assertSame('101-500', $this->call_protected('anonymize_count', [101]));
		$this->assertSame('101-500', $this->call_protected('anonymize_count', [500]));
	}

	/**
	 * Test anonymize_count 501-1000 range.
	 */
	public function test_anonymize_count_huge(): void {

		$this->assertSame('501-1000', $this->call_protected('anonymize_count', [501]));
		$this->assertSame('501-1000', $this->call_protected('anonymize_count', [1000]));
	}

	/**
	 * Test anonymize_count 1001-5000 range.
	 */
	public function test_anonymize_count_massive(): void {

		$this->assertSame('1001-5000', $this->call_protected('anonymize_count', [1001]));
		$this->assertSame('1001-5000', $this->call_protected('anonymize_count', [5000]));
	}

	/**
	 * Test anonymize_count 5000+ range.
	 */
	public function test_anonymize_count_over_5000(): void {

		$this->assertSame('5000+', $this->call_protected('anonymize_count', [5001]));
		$this->assertSame('5000+', $this->call_protected('anonymize_count', [100000]));
	}

	/**
	 * Test get_plugin_data returns expected keys.
	 */
	public function test_get_plugin_data(): void {

		$data = $this->call_protected('get_plugin_data');

		$this->assertIsArray($data);
		$this->assertArrayHasKey('version', $data);
		$this->assertArrayHasKey('active_addons', $data);
	}

	/**
	 * Test get_network_data returns expected keys.
	 */
	public function test_get_network_data(): void {

		$data = $this->call_protected('get_network_data');

		$this->assertIsArray($data);
		$this->assertArrayHasKey('is_subdomain', $data);
		$this->assertArrayHasKey('is_subdirectory', $data);
		$this->assertArrayHasKey('sunrise_installed', $data);
		$this->assertArrayHasKey('domain_mapping_enabled', $data);
	}

	/**
	 * Test get_gateway_data returns expected keys.
	 */
	public function test_get_gateway_data(): void {

		$data = $this->call_protected('get_gateway_data');

		$this->assertIsArray($data);
		$this->assertArrayHasKey('active_gateways', $data);
		$this->assertArrayHasKey('gateway_count', $data);
	}

	/**
	 * Test get_tracking_data returns complete structure.
	 */
	public function test_get_tracking_data(): void {

		$data = $this->get_tracker()->get_tracking_data();

		$this->assertIsArray($data);
		$this->assertArrayHasKey('tracker_version', $data);
		$this->assertArrayHasKey('timestamp', $data);
		$this->assertArrayHasKey('site_hash', $data);
		$this->assertArrayHasKey('environment', $data);
		$this->assertArrayHasKey('plugin', $data);
		$this->assertArrayHasKey('network', $data);
		$this->assertArrayHasKey('usage', $data);
		$this->assertArrayHasKey('gateways', $data);
	}

	/**
	 * Test sanitize_error_message removes ABSPATH.
	 */
	public function test_sanitize_error_message_removes_abspath(): void {

		$message = 'Error in ' . ABSPATH . 'wp-content/plugins/test.php';

		$result = $this->call_protected('sanitize_error_message', [$message]);

		$this->assertStringNotContainsString(ABSPATH, $result);
		$this->assertStringContainsString('ABSPATH', $result);
	}

	/**
	 * Test sanitize_error_message removes URLs.
	 */
	public function test_sanitize_error_message_removes_urls(): void {

		$message = 'Error connecting to https://example.com/api/endpoint';

		$result = $this->call_protected('sanitize_error_message', [$message]);

		$this->assertStringContainsString('[url]', $result);
		$this->assertStringNotContainsString('https://example.com', $result);
	}

	/**
	 * Test sanitize_error_message removes email addresses.
	 */
	public function test_sanitize_error_message_removes_emails(): void {

		$message = 'Error for user admin@example.com';

		$result = $this->call_protected('sanitize_error_message', [$message]);

		// Domain regex runs first, replacing example.com with [domain],
		// so the email becomes admin@[domain] rather than [email].
		$this->assertStringNotContainsString('admin@example.com', $result);
		$this->assertStringNotContainsString('example.com', $result);
	}

	/**
	 * Test sanitize_error_message removes IP addresses.
	 */
	public function test_sanitize_error_message_removes_ips(): void {

		$message = 'Connection from 192.168.1.100 failed';

		$result = $this->call_protected('sanitize_error_message', [$message]);

		$this->assertStringContainsString('[ip]', $result);
		$this->assertStringNotContainsString('192.168.1.100', $result);
	}

	/**
	 * Test sanitize_error_message truncates long messages.
	 */
	public function test_sanitize_error_message_truncates(): void {

		$message = str_repeat('A', 2000);

		$result = $this->call_protected('sanitize_error_message', [$message]);

		$this->assertSame(1000, strlen($result));
	}

	/**
	 * Test sanitize_log_handle sanitizes.
	 */
	public function test_sanitize_log_handle(): void {

		$result = $this->call_protected('sanitize_log_handle', ['Test Handle!@#']);

		$this->assertSame('testhandle', $result);
	}

	/**
	 * Test detect_plugin_from_path with plugin path.
	 */
	public function test_detect_plugin_from_path_plugin(): void {

		$result = $this->call_protected('detect_plugin_from_path', ['/var/www/wp-content/plugins/my-plugin/includes/class.php']);

		$this->assertSame('My Plugin', $result);
	}

	/**
	 * Test detect_plugin_from_path with theme path.
	 */
	public function test_detect_plugin_from_path_theme(): void {

		$result = $this->call_protected('detect_plugin_from_path', ['/var/www/wp-content/themes/my-theme/functions.php']);

		$this->assertSame('My Theme (theme)', $result);
	}

	/**
	 * Test detect_plugin_from_path with mu-plugin path.
	 */
	public function test_detect_plugin_from_path_mu_plugin(): void {

		$result = $this->call_protected('detect_plugin_from_path', ['/var/www/wp-content/mu-plugins/my-mu-plugin/init.php']);

		$this->assertSame('My Mu Plugin (mu-plugin)', $result);
	}

	/**
	 * Test detect_plugin_from_path with WordPress core.
	 */
	public function test_detect_plugin_from_path_core(): void {

		$result = $this->call_protected('detect_plugin_from_path', ['/var/www/wp-includes/class-wp.php']);

		$this->assertSame('WordPress Core', $result);
	}

	/**
	 * Test detect_plugin_from_path with empty path.
	 */
	public function test_detect_plugin_from_path_empty(): void {

		$result = $this->call_protected('detect_plugin_from_path', ['']);

		$this->assertStringContainsString('Unknown', $result);
	}

	/**
	 * Test format_plugin_name formats slug to name.
	 */
	public function test_format_plugin_name(): void {

		$result = $this->call_protected('format_plugin_name', ['my-awesome-plugin']);

		$this->assertSame('My Awesome Plugin', $result);
	}

	/**
	 * Test format_plugin_name with underscores.
	 */
	public function test_format_plugin_name_underscores(): void {

		$result = $this->call_protected('format_plugin_name', ['my_awesome_plugin']);

		$this->assertSame('My Awesome Plugin', $result);
	}

	/**
	 * Test get_error_type_name returns correct names.
	 */
	public function test_get_error_type_name(): void {

		$this->assertSame('Fatal Error', $this->call_protected('get_error_type_name', [E_ERROR]));
		$this->assertSame('Parse Error', $this->call_protected('get_error_type_name', [E_PARSE]));
		$this->assertSame('User Error', $this->call_protected('get_error_type_name', [E_USER_ERROR]));
		$this->assertSame('Error', $this->call_protected('get_error_type_name', [999]));
	}

	/**
	 * Test format_backtrace formats correctly.
	 */
	public function test_format_backtrace(): void {

		$trace = [
			[
				'file'     => '/var/www/test.php',
				'line'     => 42,
				'function' => 'doSomething',
				'class'    => 'MyClass',
				'type'     => '->',
			],
			[
				'file'     => '/var/www/other.php',
				'line'     => 10,
				'function' => 'globalFunc',
			],
		];

		$result = $this->call_protected('format_backtrace', [$trace]);

		$this->assertStringContainsString('#0', $result);
		$this->assertStringContainsString('MyClass->doSomething()', $result);
		$this->assertStringContainsString('#1', $result);
		$this->assertStringContainsString('globalFunc()', $result);
	}

	/**
	 * Test sanitize_error_for_url replaces dangerous patterns.
	 */
	public function test_sanitize_error_for_url(): void {

		$text = 'Error in /var/www/test.php line 42';

		$result = $this->call_protected('sanitize_error_for_url', [$text]);

		// Should not contain literal forward slashes.
		$this->assertStringNotContainsString('/var', $result);
	}

	/**
	 * Test maybe_send_error does nothing when tracking disabled.
	 */
	public function test_maybe_send_error_tracking_disabled(): void {

		// Tracking is disabled by default in tests.
		$this->get_tracker()->maybe_send_error('test', 'error message', \Psr\Log\LogLevel::ERROR);

		// Should not throw — just exercises the early return.
		$this->assertTrue(true);
	}

	/**
	 * Test maybe_send_error does nothing with empty handle.
	 */
	public function test_maybe_send_error_empty_handle(): void {

		$this->get_tracker()->maybe_send_error('', 'error message', \Psr\Log\LogLevel::ERROR);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_send_error does nothing with non-error log level.
	 */
	public function test_maybe_send_error_non_error_level(): void {

		$this->get_tracker()->maybe_send_error('test', 'info message', \Psr\Log\LogLevel::INFO);

		$this->assertTrue(true);
	}

	/**
	 * Test customize_fatal_error_message returns original for non-plugin errors.
	 */
	public function test_customize_fatal_error_message_non_plugin(): void {

		$original = '<p>There has been a critical error.</p>';

		$result = $this->get_tracker()->customize_fatal_error_message($original, [
			'file'    => '/var/www/wp-content/plugins/other-plugin/file.php',
			'type'    => E_ERROR,
			'message' => 'Test error',
			'line'    => 42,
		]);

		$this->assertSame($original, $result);
	}

	/**
	 * Test customize_fatal_error_message customizes for plugin errors.
	 */
	public function test_customize_fatal_error_message_plugin_error(): void {

		$original = '<p>There has been a critical error.</p>';

		$result = $this->get_tracker()->customize_fatal_error_message($original, [
			'file'    => '/var/www/wp-content/plugins/ultimate-multisite/inc/class-test.php',
			'type'    => E_ERROR,
			'message' => 'Test error',
			'line'    => 42,
		]);

		$this->assertNotSame($original, $result);
		$this->assertStringContainsString('critical error', $result);
	}

	/**
	 * Test build_user_error_message returns HTML.
	 */
	public function test_build_user_error_message(): void {

		$result = $this->call_protected('build_user_error_message', ['Error occurred', 'https://example.com/']);

		$this->assertStringContainsString('Error occurred', $result);
		$this->assertStringContainsString('https://example.com/', $result);
	}

	/**
	 * Test prepare_error_data returns expected structure.
	 */
	public function test_prepare_error_data(): void {

		$data = $this->call_protected('prepare_error_data', ['test-handle', 'Test error message', \Psr\Log\LogLevel::ERROR]);

		$this->assertIsArray($data);
		$this->assertArrayHasKey('tracker_version', $data);
		$this->assertArrayHasKey('timestamp', $data);
		$this->assertArrayHasKey('site_hash', $data);
		$this->assertArrayHasKey('type', $data);
		$this->assertArrayHasKey('log_level', $data);
		$this->assertArrayHasKey('handle', $data);
		$this->assertArrayHasKey('message', $data);
		$this->assertArrayHasKey('environment', $data);
		$this->assertSame('error', $data['type']);
		$this->assertSame('test-handle', $data['handle']);
	}

	/**
	 * Test maybe_send_initial_data is a no-op (background telemetry removed in 2.5.1).
	 */
	public function test_maybe_send_initial_data_is_noop(): void {

		// Both calls must complete without errors — the method is now a no-op.
		$this->get_tracker()->maybe_send_initial_data('any_setting', true);
		$this->get_tracker()->maybe_send_initial_data('any_setting', false);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_send_tracking_data does nothing when tracking disabled.
	 */
	public function test_maybe_send_tracking_data_disabled(): void {

		$this->get_tracker()->maybe_send_tracking_data();

		$this->assertTrue(true);
	}

	/**
	 * Test create_weekly_schedule runs without error.
	 */
	public function test_create_weekly_schedule(): void {

		$this->get_tracker()->create_weekly_schedule();

		$this->assertTrue(true);
	}
}
