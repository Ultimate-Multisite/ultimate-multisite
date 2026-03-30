<?php
/**
 * Tests for exporter functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for exporter functions.
 */
class Exporter_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_exporter_export async returns WP_Error when wu_enqueue_async_action is missing.
	 */
	public function test_export_async_returns_wp_error_when_no_async_support(): void {

		if (function_exists('wu_enqueue_async_action')) {
			$this->markTestSkipped('wu_enqueue_async_action is available in this environment');
		}

		$result = wu_exporter_export(1, [], true);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('not-enabled', $result->get_error_code());
	}

	/**
	 * Test wu_exporter_get_all_exports returns array.
	 */
	public function test_get_all_exports_returns_array(): void {

		$result = wu_exporter_get_all_exports();

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_exporter_get_all_exports returns empty array when no exports exist.
	 */
	public function test_get_all_exports_returns_empty_when_no_exports(): void {

		$result = wu_exporter_get_all_exports();

		$this->assertEmpty($result);
	}

	/**
	 * Test wu_exporter_get_folder returns a string URL.
	 */
	public function test_get_folder_returns_string(): void {

		$this->setExpectedDeprecated('WP_Ultimo\Helper::get_folder_url');

		$result = wu_exporter_get_folder();

		$this->assertIsString($result);
		$this->assertStringContainsString('wu-site-exports', $result);
	}

	/**
	 * Test wu_exporter_get_site_from_export_name returns false for invalid name.
	 */
	public function test_get_site_from_export_name_returns_false_for_invalid(): void {

		$result = wu_exporter_get_site_from_export_name('no-id-here.zip');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_exporter_get_site_from_export_name extracts site ID from filename.
	 */
	public function test_get_site_from_export_name_extracts_site_id(): void {

		$result = wu_exporter_get_site_from_export_name('wu-site-export-0-2024.zip');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_exporter_save_generation_time returns bool.
	 */
	public function test_save_generation_time_returns_bool(): void {

		$result = wu_exporter_save_generation_time('test-export.zip', 1.5);

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_exporter_get_generation_time returns not-saved string for unknown file.
	 */
	public function test_get_generation_time_returns_not_saved_for_unknown(): void {

		$result = wu_exporter_get_generation_time('nonexistent-file.zip');

		$this->assertStringContainsString('not saved', $result);
	}

	/**
	 * Test wu_exporter_get_generation_time returns human diff after saving.
	 */
	public function test_get_generation_time_returns_human_diff_after_save(): void {

		$file = 'test-gen-time-' . uniqid() . '.zip';

		wu_exporter_save_generation_time($file, 60.0);

		$result = wu_exporter_get_generation_time($file);

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
		$this->assertStringNotContainsString('not saved', $result);
	}

	/**
	 * Test wu_exporter_add_pending returns a 32-char MD5 hash string.
	 */
	public function test_add_pending_returns_hash(): void {

		$hash = wu_exporter_add_pending(1, [], false);

		$this->assertIsString($hash);
		$this->assertSame(32, strlen($hash));
	}

	/**
	 * Test wu_exporter_add_pending returns different hashes for different inputs.
	 */
	public function test_add_pending_different_inputs_produce_different_hashes(): void {

		$hash1 = wu_exporter_add_pending(1, [], false);
		$hash2 = wu_exporter_add_pending(2, [], false);

		$this->assertNotSame($hash1, $hash2);
	}

	/**
	 * Test wu_exporter_get_pending returns array.
	 */
	public function test_get_pending_returns_array(): void {

		$result = wu_exporter_get_pending();

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_exporter_set_transient returns bool.
	 */
	public function test_set_transient_returns_bool(): void {

		$result = wu_exporter_set_transient('wu_test_transient_key', 'test_value', 60);

		$this->assertIsBool($result);
		$this->assertTrue($result);
	}

	/**
	 * Test wu_exporter_delete_transient returns bool.
	 */
	public function test_delete_transient_returns_bool(): void {

		wu_exporter_set_transient('wu_test_delete_transient', 'value', 60);

		$result = wu_exporter_delete_transient('wu_test_delete_transient');

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_exporter_exclude_plugin_from_export returns true and adds filter.
	 */
	public function test_exclude_plugin_from_export_returns_true(): void {

		$result = wu_exporter_exclude_plugin_from_export('my-plugin');

		$this->assertTrue($result);
	}

	/**
	 * Test wu_exporter_exclude_plugin_from_export adds plugin to exclusion list.
	 */
	public function test_exclude_plugin_adds_to_exclusion_list(): void {

		wu_exporter_exclude_plugin_from_export('test-plugin-to-exclude');

		$list = apply_filters('wu_site_exporter_plugin_exclusion_list', []);

		$this->assertContains('test-plugin-to-exclude', $list);
	}

	// --------------------------------------------------------
	// Deprecated function aliases
	// --------------------------------------------------------

	public function test_deprecated_get_all_exports_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_get_all_exports');

		$result = wu_site_exporter_get_all_exports();

		$this->assertIsArray($result);
	}

	public function test_deprecated_get_folder_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_get_folder');
		$this->setExpectedDeprecated('WP_Ultimo\Helper::get_folder_url');

		$result = wu_site_exporter_get_folder();

		$this->assertIsString($result);
	}

	public function test_deprecated_save_generation_time_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_save_generation_time');

		$result = wu_site_exporter_save_generation_time('alias-test.zip', 2.0);

		$this->assertIsBool($result);
	}

	public function test_deprecated_get_generation_time_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_get_generation_time');

		$result = wu_site_exporter_get_generation_time('alias-test.zip');

		$this->assertIsString($result);
	}

	public function test_deprecated_add_pending_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_add_pending');

		$result = wu_site_exporter_add_pending(1, [], false);

		$this->assertIsString($result);
		$this->assertSame(32, strlen($result));
	}

	public function test_deprecated_get_pending_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_get_pending');

		$result = wu_site_exporter_get_pending();

		$this->assertIsArray($result);
	}

	public function test_wp_ultimo_set_transient_alias(): void {

		$result = wp_ultimo_site_exporter_set_transient('wu_alias_transient', 'val', 60);

		$this->assertTrue($result);
	}

	public function test_wp_ultimo_delete_transient_alias(): void {

		wp_ultimo_site_exporter_set_transient('wu_alias_del_transient', 'val', 60);

		$result = wp_ultimo_site_exporter_delete_transient('wu_alias_del_transient');

		$this->assertIsBool($result);
	}

	public function test_deprecated_exclude_plugin_alias(): void {

		$this->setExpectedDeprecated('wu_site_exporter_exclude_plugin_from_export');

		$result = wu_site_exporter_exclude_plugin_from_export('alias-plugin');

		$this->assertTrue($result);
	}
}
