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
	 * Test wu_get_setting_early recovers from a stale notoptions marker.
	 */
	public function test_get_setting_early_recovers_from_stale_notoptions_marker(): void {

		$this->setExpectedIncorrectUsage('wu_get_setting_early');

		$network_id     = get_current_network_id();
		$option_name    = 'wp-ultimo_' . \WP_Ultimo\Settings::KEY;
		$cache_key      = "{$network_id}:{$option_name}";
		$notoptions_key = "{$network_id}:notoptions";
		$settings       = get_network_option($network_id, $option_name, []);

		$settings['stale_cache_recovery'] = 'recovered';
		update_network_option($network_id, $option_name, $settings);
		wp_cache_delete($cache_key, 'site-options');
		wp_cache_set($notoptions_key, [$option_name => true], 'site-options');

		$result     = wu_get_setting_early('stale_cache_recovery', false);
		$notoptions = wp_cache_get($notoptions_key, 'site-options');

		$this->assertSame('recovered', $result);
		$this->assertArrayNotHasKey($option_name, $notoptions);

		unset($settings['stale_cache_recovery']);
		update_network_option($network_id, $option_name, $settings);
	}

	/**
	 * Test a failed settings query does not leave a persistent negative marker.
	 */
	public function test_get_setting_early_does_not_cache_database_errors_as_missing(): void {
		global $wpdb;

		$this->setExpectedIncorrectUsage('wu_get_setting_early');
		$this->setExpectedIncorrectUsage('wu_save_setting_early');

		$network_id     = get_current_network_id();
		$option_name    = 'wp-ultimo_' . \WP_Ultimo\Settings::KEY;
		$notoptions_key = "{$network_id}:notoptions";
		$filter         = static function ($query) use ($option_name) {
			return str_contains($query, 'SELECT meta_value') && str_contains($query, $option_name) ? 'SELECT broken syntax' : $query;
		};

		wp_cache_delete("{$network_id}:{$option_name}", 'site-options');
		wp_cache_delete($notoptions_key, 'site-options');
		$suppress = $wpdb->suppress_errors();
		add_filter('query', $filter);

		try {
			$settings = wu_get_settings_early();
			$result   = wu_get_setting_early('enable_domain_mapping', 'query_failed');
			$saved    = wu_save_setting_early('enable_domain_mapping', true);
		} finally {
			remove_filter('query', $filter);
			$wpdb->suppress_errors($suppress);
		}

		$notoptions = wp_cache_get($notoptions_key, 'site-options');

		$this->assertWPError($settings);
		$this->assertSame('wu_early_settings_query_failed', $settings->get_error_code());
		$this->assertSame('query_failed', $result);
		$this->assertWPError($saved);
		$this->assertFalse(is_array($notoptions) && isset($notoptions[ $option_name ]));
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
	 * Test wu_get_security_mode_key returns a high-entropy string.
	 */
	public function test_get_security_mode_key_returns_high_entropy_string(): void {

		delete_network_option(null, 'wu_security_mode_key');

		$key = wu_get_security_mode_key();

		$this->assertIsString($key);
		$this->assertSame(32, strlen($key));
	}

	/**
	 * Test wu_get_security_mode_key returns only hex characters.
	 */
	public function test_get_security_mode_key_returns_hex_characters(): void {

		delete_network_option(null, 'wu_security_mode_key');

		$key = wu_get_security_mode_key();

		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key);
	}

	/**
	 * Test wu_get_security_mode_key is stable after generation.
	 */
	public function test_get_security_mode_key_is_stable_after_generation(): void {

		delete_network_option(null, 'wu_security_mode_key');

		$key1 = wu_get_security_mode_key();
		$key2 = wu_get_security_mode_key();

		$this->assertSame($key1, $key2);
	}

	/**
	 * Test wu_get_security_mode_key without generation returns a stored key.
	 */
	public function test_get_security_mode_key_without_generation_returns_persisted_key_when_available(): void {

		delete_network_option(null, 'wu_security_mode_key');

		$generated = wu_get_security_mode_key();
		$stored    = get_network_option(null, 'wu_security_mode_key', '');

		$this->assertSame($generated, $stored);
		$this->assertSame($stored, wu_get_security_mode_key(false));
		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $stored);
	}

	/**
	 * Test wu_get_security_mode_key can preserve the legacy key without rotating.
	 */
	public function test_get_security_mode_key_without_generation_returns_legacy_key(): void {

		delete_network_option(null, 'wu_security_mode_key');

		$expected = substr(md5((string) get_network_option(null, 'admin_email')), 0, 6);
		$key      = wu_get_security_mode_key(false);

		$this->assertSame($expected, $key);
		$this->assertSame('', get_network_option(null, 'wu_security_mode_key', ''));
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
