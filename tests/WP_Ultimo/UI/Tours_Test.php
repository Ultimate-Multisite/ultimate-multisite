<?php
/**
 * Unit tests for Tours.
 *
 * @package WP_Ultimo\Tests
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Unit tests for Tours.
 */
class Tours_Test extends WP_UnitTestCase {

	/**
	 * Get the singleton instance.
	 *
	 * @return Tours
	 */
	protected function get_instance(): Tours {

		return Tours::get_instance();
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$instance = $this->get_instance();

		$this->assertInstanceOf(Tours::class, $instance);
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(
			Tours::get_instance(),
			Tours::get_instance()
		);
	}

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks(): void {

		$instance = $this->get_instance();

		$instance->init();

		$this->assertIsInt(has_action('wp_ajax_wu_mark_tour_as_finished', [$instance, 'mark_as_finished']));
		$this->assertIsInt(has_action('admin_enqueue_scripts', [$instance, 'register_scripts']));
		$this->assertIsInt(has_action('in_admin_footer', [$instance, 'enqueue_scripts']));
	}

	/**
	 * Test has_tours returns false when no tours registered.
	 */
	public function test_has_tours_returns_false_when_empty(): void {

		$instance = $this->get_instance();

		// Access protected property via reflection to reset tours.
		$reflection = new \ReflectionClass($instance);
		$prop       = $reflection->getProperty('tours');
		$prop->setAccessible(true);
		$prop->setValue($instance, []);

		$this->assertFalse($instance->has_tours());
	}

	/**
	 * Test has_tours returns true when tours are registered.
	 */
	public function test_has_tours_returns_true_when_tours_exist(): void {

		$instance = $this->get_instance();

		$reflection = new \ReflectionClass($instance);
		$prop       = $reflection->getProperty('tours');
		$prop->setAccessible(true);
		$prop->setValue($instance, [
			'test-tour' => [
				[
					'id'   => 'step1',
					'text' => 'Hello',
				],
			],
		]);

		$this->assertTrue($instance->has_tours());

		// Reset.
		$prop->setValue($instance, []);
	}

	/**
	 * Test enqueue_scripts does nothing when no tours registered.
	 */
	public function test_enqueue_scripts_skips_when_no_tours(): void {

		global $wp_scripts;

		$instance = $this->get_instance();

		// Ensure no tours.
		$reflection = new \ReflectionClass($instance);
		$prop       = $reflection->getProperty('tours');
		$prop->setAccessible(true);
		$prop->setValue($instance, []);

		$queue_before = isset($wp_scripts) ? $wp_scripts->queue : [];

		$instance->enqueue_scripts();

		$queue_after = isset($wp_scripts) ? $wp_scripts->queue : [];

		// Queue should not have grown.
		$this->assertSame($queue_before, $queue_after);
	}

	/**
	 * Test get_setting_key normalises hyphens to underscores.
	 *
	 * Regression test: WordPress's user-settings cookie is sanitised with
	 * preg_replace('/[^A-Za-z0-9=&_]/', '') before storage, and PHP's
	 * parse_str() converts hyphens in key names to underscores. Either path
	 * mangles "wu_tour_wp-ultimo-dashboard" so that get_user_setting() never
	 * finds the stored value, causing tours to re-show every session.
	 * get_setting_key() must replace hyphens with underscores so writes and
	 * reads use the same key regardless of which storage path WordPress takes.
	 */
	public function test_get_setting_key_replaces_hyphens_with_underscores(): void {

		$instance = $this->get_instance();

		$reflection = new \ReflectionClass($instance);
		$method     = $reflection->getMethod('get_setting_key');
		$method->setAccessible(true);

		// Hyphenated IDs (the real-world broken cases).
		$this->assertSame('wu_tour_wp_ultimo_dashboard', $method->invoke($instance, 'wp-ultimo-dashboard'));
		$this->assertSame('wu_tour_checkout_form_editor', $method->invoke($instance, 'checkout-form-editor'));
		$this->assertSame('wu_tour_checkout_form_list', $method->invoke($instance, 'checkout-form-list'));

		// Underscore-only IDs must pass through unchanged.
		$this->assertSame('wu_tour_dashboard', $method->invoke($instance, 'dashboard'));
		$this->assertSame('wu_tour_new_product_warning', $method->invoke($instance, 'new_product_warning'));

		// Mixed: hyphens become underscores, existing underscores untouched.
		$this->assertSame('wu_tour_my_mixed_id', $method->invoke($instance, 'my-mixed_id'));
	}

	/**
	 * Test that the normalised key survives WordPress's user-settings round-trip.
	 *
	 * Regression test: WordPress's cookie sanitizer in wp_user_settings() uses
	 * preg_replace('/[^A-Za-z0-9=&_]/', ...) which strips hyphens, and
	 * wp_set_all_user_settings() builds the stored string via a per-character
	 * allow-list before calling parse_str(). A hyphenated raw tour ID like
	 * 'wp-ultimo-dashboard' would produce a key that is not reliably found by
	 * get_user_setting() across different WordPress versions and storage paths.
	 *
	 * This test verifies that the underscore-normalised key produced by
	 * get_setting_key() round-trips correctly through the parse_str() step that
	 * WordPress uses internally when reading settings back.
	 *
	 * Note: set_user_setting() cannot be called directly in PHPUnit because it
	 * guards on headers_sent() which is true after the test bootstrap output.
	 * The parse_str round-trip (the actual failure mechanism) is tested directly.
	 */
	public function test_normalised_key_survives_user_settings_round_trip(): void {

		$instance = $this->get_instance();

		$reflection = new \ReflectionClass($instance);
		$method     = $reflection->getMethod('get_setting_key');
		$method->setAccessible(true);

		$tour_id     = 'wp-ultimo-dashboard';
		$setting_key = $method->invoke($instance, $tour_id);

		// The normalised key must contain only alphanumeric + underscore characters
		// so it is safe for every WordPress user-settings code path.
		$this->assertMatchesRegularExpression(
			'/^[A-Za-z0-9_]+$/',
			$setting_key,
			'Normalised setting key must contain only alphanumeric and underscore characters.'
		);

		// Simulate what WordPress does internally: build the query string and
		// parse it back. With the normalised key the stored key equals the
		// lookup key, so get_user_setting() finds the value. A hyphenated key
		// would be mangled here (stripped or converted), causing the tour to
		// re-show every session.
		$stored_string = $setting_key . '=1';
		$parsed        = [];
		parse_str($stored_string, $parsed);

		$this->assertArrayHasKey(
			$setting_key,
			$parsed,
			'Normalised key must survive parse_str() unchanged. ' .
			'A hyphenated key is mangled by parse_str(), causing the tour to re-show every session.'
		);

		$this->assertSame('1', $parsed[ $setting_key ]);
	}

	/**
	 * Test get_meta_key normalises hyphens to underscores and uses the wu_tour_finished_ prefix.
	 *
	 * Regression test: the tour-finished flag is stored in user meta so that
	 * the AJAX dismissal handler can persist it without depending on the
	 * wp-settings-* cookie sync (which is skipped during AJAX requests by
	 * wp_user_settings(), leaving the browser cookie stale and causing the
	 * tour to re-show on the next page load — observed on the checkout form
	 * editor page in particular).
	 */
	public function test_get_meta_key_uses_wu_tour_finished_prefix(): void {

		$instance = $this->get_instance();

		$reflection = new \ReflectionClass($instance);
		$method     = $reflection->getMethod('get_meta_key');
		$method->setAccessible(true);

		$this->assertSame('wu_tour_finished_checkout_form_editor', $method->invoke($instance, 'checkout-form-editor'));
		$this->assertSame('wu_tour_finished_wp_ultimo_dashboard', $method->invoke($instance, 'wp-ultimo-dashboard'));
		$this->assertSame('wu_tour_finished_dashboard', $method->invoke($instance, 'dashboard'));
	}

	/**
	 * Test is_tour_finished returns true once the user meta flag is set.
	 *
	 * Regression test for the checkout-form-editor tour re-showing every
	 * visit: the AJAX `wu_mark_tour_as_finished` handler writes to user meta,
	 * so subsequent requests must see the flag without depending on the
	 * wp-settings-* cookie (which is not updated during AJAX requests).
	 */
	public function test_is_tour_finished_reads_user_meta(): void {

		$instance = $this->get_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$reflection  = new \ReflectionClass($instance);
		$is_finished = $reflection->getMethod('is_tour_finished');
		$is_finished->setAccessible(true);
		$get_meta_key = $reflection->getMethod('get_meta_key');
		$get_meta_key->setAccessible(true);

		// Not finished initially.
		$this->assertFalse($is_finished->invoke($instance, 'checkout-form-editor'));

		// Mark as finished via the same meta key the AJAX handler writes.
		update_user_meta($user_id, $get_meta_key->invoke($instance, 'checkout-form-editor'), 1);

		$this->assertTrue($is_finished->invoke($instance, 'checkout-form-editor'));

		// Different tour ID still false.
		$this->assertFalse($is_finished->invoke($instance, 'dashboard'));
	}

	/**
	 * Test is_tour_finished falls back to legacy get_user_setting().
	 *
	 * Users who dismissed a tour before this release have their flag stored
	 * only in the WordPress user-settings cookie (`wp-settings-{uid}`), not in
	 * the new wu_tour_finished_* meta key. The legacy fallback prevents those
	 * users from seeing tours they already dismissed.
	 *
	 * get_user_setting() reads from $_COOKIE (or its in-memory cache), so the
	 * test populates $_COOKIE directly to emulate a returning browser whose
	 * cookie still carries the pre-upgrade dismissal flag.
	 */
	public function test_is_tour_finished_falls_back_to_legacy_user_setting(): void {

		$instance = $this->get_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$reflection  = new \ReflectionClass($instance);
		$is_finished = $reflection->getMethod('is_tour_finished');
		$is_finished->setAccessible(true);
		$get_setting = $reflection->getMethod('get_setting_key');
		$get_setting->setAccessible(true);

		$cookie_name                       = 'wp-settings-' . $user_id;
		$setting_key                       = $get_setting->invoke($instance, 'legacy-tour');
		$prior_cookie                      = $_COOKIE[ $cookie_name ] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- test stash, no user input.
		$prior_updated_settings            = $GLOBALS['_updated_user_settings'] ?? null;
		$GLOBALS['_updated_user_settings'] = null;
		unset($_COOKIE[ $cookie_name ]);

		try {
			$this->assertFalse($is_finished->invoke($instance, 'legacy-tour'));

			// Simulate the legacy user-settings cookie value that get_user_setting() reads.
			$_COOKIE[ $cookie_name ]           = $setting_key . '=1';
			$GLOBALS['_updated_user_settings'] = null;

			$this->assertTrue($is_finished->invoke($instance, 'legacy-tour'));
		} finally {
			if (null === $prior_cookie) {
				unset($_COOKIE[ $cookie_name ]);
			} else {
				$_COOKIE[ $cookie_name ] = $prior_cookie;
			}
			$GLOBALS['_updated_user_settings'] = $prior_updated_settings;
		}
	}

	/**
	 * Test is_tour_finished reads legacy stripped keys from user settings meta.
	 *
	 * Regression test for users who dismissed hyphenated tours before the tour ID
	 * normalisation fix. WordPress could persist keys with hyphens stripped (for
	 * example, wu_tour_checkoutformlist), while newer code looks for the
	 * underscore-normalised key (wu_tour_checkout_form_list). The meta fallback
	 * must recognise the stripped legacy shape and backfill the new user meta flag.
	 */
	public function test_is_tour_finished_reads_stripped_legacy_user_settings_meta(): void {

		$instance = $this->get_instance();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$reflection  = new \ReflectionClass($instance);
		$is_finished = $reflection->getMethod('is_tour_finished');
		$is_finished->setAccessible(true);
		$get_meta_key = $reflection->getMethod('get_meta_key');
		$get_meta_key->setAccessible(true);
		$get_legacy_keys = $reflection->getMethod('get_legacy_setting_keys');
		$get_legacy_keys->setAccessible(true);

		$meta_key = $get_meta_key->invoke($instance, 'checkout-form-list');

		update_user_option($user_id, 'user-settings', 'wu_tour_checkoutformlist=1', false);

		$this->assertSame('wu_tour_checkoutformlist=1', get_user_option('user-settings', $user_id));
		$this->assertContains('wu_tour_checkoutformlist', $get_legacy_keys->invoke($instance, 'checkout-form-list'));

		$this->assertTrue($is_finished->invoke($instance, 'checkout-form-list', $user_id));
		$this->assertSame('1', get_user_meta($user_id, $meta_key, true));
	}

	/**
	 * Test enqueue_scripts uses wp_add_inline_script on 'underscore', not wu-admin.
	 *
	 * Regression test for GH#707: wu_tours was localized onto wu-admin which is
	 * not enqueued on the network dashboard, causing a ReferenceError. The fix
	 * uses wp_add_inline_script on 'underscore' (always present in WP admin).
	 */
	public function test_enqueue_scripts_inlines_data_on_underscore_not_wu_admin(): void {

		global $wp_scripts;

		$instance = $this->get_instance();

		// Register 'underscore' if not already registered (test environment may not have it).
		if ( ! wp_script_is('underscore', 'registered')) {
			wp_register_script('underscore', false, [], false, false);
		}

		// Inject a tour so enqueue_scripts() proceeds.
		$reflection = new \ReflectionClass($instance);
		$prop       = $reflection->getProperty('tours');
		$prop->setAccessible(true);
		$prop->setValue($instance, [
			'test-tour' => [
				[
					'id'   => 'step1',
					'text' => 'Hello',
				],
			],
		]);

		$instance->enqueue_scripts();

		// 'underscore' must be enqueued.
		$this->assertTrue(wp_script_is('underscore', 'enqueued'), 'underscore should be enqueued');

		// Inline data must be attached to 'underscore', not 'wu-admin'.
		$inline_data = $wp_scripts->get_data('underscore', 'after');
		$this->assertNotEmpty($inline_data, 'Inline script data should be attached to underscore');

		$inline_str = is_array($inline_data) ? implode('', $inline_data) : (string) $inline_data;
		$this->assertStringContainsString('wu_tours', $inline_str, 'wu_tours should be defined in inline script');
		$this->assertStringContainsString('wu_tours_vars', $inline_str, 'wu_tours_vars should be defined in inline script');

		// wu-admin must NOT have wu_tours localized onto it.
		$wu_admin_data = $wp_scripts->get_data('wu-admin', 'data');
		$this->assertStringNotContainsString('wu_tours', (string) $wu_admin_data, 'wu_tours must not be localized onto wu-admin');

		// Reset.
		$prop->setValue($instance, []);
	}
}
