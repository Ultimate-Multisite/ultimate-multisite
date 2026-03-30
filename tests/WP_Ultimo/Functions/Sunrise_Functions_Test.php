<?php
/**
 * Tests for sunrise functions.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Functions
 * @since 2.0.0
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for sunrise functions in inc/functions/sunrise.php.
 */
class Sunrise_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_should_load_sunrise returns a boolean.
	 */
	public function test_should_load_sunrise_returns_bool(): void {

		$result = wu_should_load_sunrise();

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_get_setting_early returns default value for unknown setting.
	 */
	public function test_get_setting_early_returns_default_for_unknown_setting(): void {

		$this->setExpectedIncorrectUsage('wu_get_setting_early');

		$result = wu_get_setting_early('nonexistent_setting_xyz', 'my_default');

		$this->assertSame('my_default', $result);
	}

	/**
	 * Test wu_get_setting_early returns false as default when no default provided.
	 */
	public function test_get_setting_early_returns_false_as_default(): void {

		$this->setExpectedIncorrectUsage('wu_get_setting_early');

		$result = wu_get_setting_early('nonexistent_setting_xyz');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_setting_early returns stored value.
	 */
	public function test_get_setting_early_returns_stored_value(): void {

		$this->setExpectedIncorrectUsage('wu_get_setting_early');

		$settings_key = \WP_Ultimo\Settings::KEY;
		$option_name  = 'wp-ultimo_' . $settings_key;

		$existing = get_network_option(null, $option_name, []);

		$existing['test_early_setting'] = 'test_value_123';

		update_network_option(null, $option_name, $existing);

		$result = wu_get_setting_early('test_early_setting', false);

		$this->assertSame('test_value_123', $result);

		// Restore.
		unset($existing['test_early_setting']);
		update_network_option(null, $option_name, $existing);
	}

	/**
	 * Test wu_save_setting_early stores a value retrievable by wu_get_setting_early.
	 */
	public function test_save_setting_early_stores_value(): void {

		$this->setExpectedIncorrectUsage('wu_save_setting_early');
		$this->setExpectedIncorrectUsage('wu_get_setting_early');

		$key   = 'test_save_early_' . wp_rand();
		$value = 'saved_value_' . wp_rand();

		wu_save_setting_early($key, $value);

		$retrieved = wu_get_setting_early($key, false);

		$this->assertSame($value, $retrieved);

		// Cleanup.
		$settings_key = \WP_Ultimo\Settings::KEY;
		$option_name  = 'wp-ultimo_' . $settings_key;
		$settings     = get_network_option(null, $option_name, []);
		unset($settings[ $key ]);
		update_network_option(null, $option_name, $settings);
	}

	/**
	 * Test wu_get_security_mode_key returns a 6-character string.
	 */
	public function test_get_security_mode_key_returns_six_char_string(): void {

		$key = wu_get_security_mode_key();

		$this->assertIsString($key);
		$this->assertSame(6, strlen($key));
	}

	/**
	 * Test wu_get_security_mode_key returns only hex characters.
	 */
	public function test_get_security_mode_key_returns_hex_characters(): void {

		$key = wu_get_security_mode_key();

		$this->assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $key);
	}

	/**
	 * Test wu_get_security_mode_key is deterministic for same admin email.
	 */
	public function test_get_security_mode_key_is_deterministic(): void {

		$key1 = wu_get_security_mode_key();
		$key2 = wu_get_security_mode_key();

		$this->assertSame($key1, $key2);
	}

	/**
	 * Test wu_kses_data returns string.
	 */
	public function test_kses_data_returns_string(): void {

		$result = wu_kses_data('<p>Hello <script>alert(1)</script></p>');

		$this->assertIsString($result);
	}

	/**
	 * Test wu_kses_data strips disallowed tags when wp_kses_data is available.
	 */
	public function test_kses_data_strips_script_tags(): void {

		if (! function_exists('wp_kses_data')) {
			$this->markTestSkipped('wp_kses_data not available.');
		}

		$result = wu_kses_data('<p>Hello</p><script>alert(1)</script>');

		$this->assertStringNotContainsString('<script>', $result);
	}

	/**
	 * Test wu_kses_data passes through safe content unchanged.
	 */
	public function test_kses_data_passes_safe_content(): void {

		if (! function_exists('wp_kses_data')) {
			$this->markTestSkipped('wp_kses_data not available.');
		}

		$safe = '<p>Hello <strong>world</strong></p>';

		$result = wu_kses_data($safe);

		$this->assertStringContainsString('Hello', $result);
		$this->assertStringContainsString('<strong>', $result);
	}

	/**
	 * Test wu_kses_data returns data unchanged when wp_kses_data does not exist.
	 */
	public function test_kses_data_returns_data_unchanged_when_function_missing(): void {

		if (function_exists('wp_kses_data')) {
			$this->markTestSkipped('wp_kses_data exists; fallback path not reachable.');
		}

		$data   = '<script>alert(1)</script>';
		$result = wu_kses_data($data);

		$this->assertSame($data, $result);
	}
}
