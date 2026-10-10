<?php
/**
 * Stripe settings field visibility tests.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @since 2.5.0
 */

namespace WP_Ultimo\Gateways;

defined('ABSPATH') || exit;

class Stripe_Settings_Test extends \WP_UnitTestCase {

	public function test_key_fields_accept_saved_and_submitted_toggle_values(): void {
		$sections = \WP_Ultimo\Settings::get_instance()->get_sections();
		$fields   = $sections['payment-gateways']['fields'];
		$modes    = [
			'test' => 1,
			'live' => [0, '0', false, ''],
		];

		foreach (['stripe', 'stripe_checkout'] as $gateway) {
			foreach ($modes as $mode => $values) {
				foreach (['pk', 'sk'] as $key_type) {
					$field = $fields["{$gateway}_{$mode}_{$key_type}_key"];

					$this->assertSame($values, $field['require']["{$gateway}_sandbox_mode"]);
					$this->assertSame('manage_api_keys', $field['capability']);
					$this->assertStringContainsString(
						"require('{$gateway}_sandbox_mode', " . wp_json_encode($values) . ')',
						$field['wrapper_html_attr']['v-show']
					);

					if ('stripe' === $gateway) {
						$this->assertSame(1, $field['require']['stripe_show_direct_keys']);
					}
				}
			}
		}
	}

	public function test_live_key_fields_accept_unchecked_sandbox_value_after_save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- Preserve the test request exactly for restoration.
		$original_tab    = $_REQUEST['tab'] ?? null;
		$_REQUEST['tab'] = 'payment-gateways';

		try {
			$settings = \WP_Ultimo\Settings::get_instance()->save_settings(['stripe_show_direct_keys' => '1']);

			$sections = \WP_Ultimo\Settings::get_instance()->get_sections();
			$fields   = $sections['payment-gateways']['fields'];

			foreach (['stripe', 'stripe_checkout'] as $gateway) {
				$value = $settings["{$gateway}_sandbox_mode"];

				$this->assertEmpty($value);

				foreach (['pk', 'sk'] as $key_type) {
					$this->assertContains($value, $fields["{$gateway}_live_{$key_type}_key"]['require']["{$gateway}_sandbox_mode"]);
					$this->assertSame(1, $fields["{$gateway}_test_{$key_type}_key"]['require']["{$gateway}_sandbox_mode"]);
				}
			}
		} finally {
			if (null === $original_tab) {
				unset($_REQUEST['tab']);
			} else {
				$_REQUEST['tab'] = $original_tab;
			}
		}
	}
}
