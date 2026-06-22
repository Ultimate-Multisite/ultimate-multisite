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
	 * Reset the admin header action marker for tests that queue tours.
	 */
	protected function reset_admin_header_action(): void {

		remove_all_actions('in_admin_header');
		unset($GLOBALS['wp_actions']['in_admin_header']);
	}

	/**
	 * Reset WordPress's in-memory user-settings cache for tour tests.
	 */
	protected function reset_user_settings_cache($user_id): void {

		delete_user_option($user_id, 'user-settings');
		unset($_COOKIE[ 'wp-settings-' . $user_id ], $GLOBALS['_updated_user_settings'][ $user_id ], $GLOBALS['user_settings']);
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
	 * Test is_tour_finished ignores the current user's legacy cookie for other users.
	 */
	public function test_is_tour_finished_uses_legacy_settings_for_passed_user(): void {

		$instance = $this->get_instance();

		$current_user_id = self::factory()->user->create(['role' => 'administrator']);
		$target_user_id  = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($current_user_id);

		$reflection  = new \ReflectionClass($instance);
		$is_finished = $reflection->getMethod('is_tour_finished');
		$is_finished->setAccessible(true);
		$get_setting = $reflection->getMethod('get_setting_key');
		$get_setting->setAccessible(true);

		$current_cookie_name               = 'wp-settings-' . $current_user_id;
		$setting_key                       = $get_setting->invoke($instance, 'legacy-tour');
		$prior_cookie                      = $_COOKIE[ $current_cookie_name ] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- test stash, no user input.
		$prior_updated_settings            = $GLOBALS['_updated_user_settings'] ?? null;
		$_COOKIE[ $current_cookie_name ]   = $setting_key . '=1';
		$GLOBALS['_updated_user_settings'] = null;

		try {
			$this->assertFalse($is_finished->invoke($instance, 'legacy-tour', $target_user_id));

			update_user_option($target_user_id, 'user-settings', $setting_key . '=1', false);

			$this->assertTrue($is_finished->invoke($instance, 'legacy-tour', $target_user_id));
		} finally {
			if (null === $prior_cookie) {
				unset($_COOKIE[ $current_cookie_name ]);
			} else {
				$_COOKIE[ $current_cookie_name ] = $prior_cookie;
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
		unset($_COOKIE[ 'wp-settings-' . $user_id ], $GLOBALS['_updated_user_settings'][ $user_id ]);

		$this->assertSame('wu_tour_checkoutformlist=1', get_user_option('user-settings', $user_id));
		$this->assertContains('wu_tour_checkoutformlist', $get_legacy_keys->invoke($instance, 'checkout-form-list'));

		$this->assertTrue($is_finished->invoke($instance, 'checkout-form-list', $user_id));
		$this->assertSame('1', get_user_meta($user_id, $meta_key, true));
	}

	/**
	 * Test enqueue_scripts prints the tour bootstrap data directly.
	 *
	 * Regression test for GH#707: module scripts cannot be localized via
	 * wp_localize_script(), and in_admin_footer is too late for attaching inline
	 * data to head scripts. The current contract prints the bootstrap data in the
	 * footer before the wu-tours module is enqueued.
	 */
	public function test_enqueue_scripts_prints_inline_bootstrap_data(): void {

		$instance = $this->get_instance();

		// Register 'underscore' if not already registered (test environment may not have it).
		if ( ! wp_script_is('underscore', 'registered')) {
			wp_register_script('underscore', false, [], '1.0.0', false);
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

		ob_start();
		$instance->enqueue_scripts();
		$output = ob_get_clean();

		$this->assertStringContainsString('id="wu-tours-data"', $output, 'Inline script data should be printed in the footer.');
		$this->assertStringContainsString('wu_tours', $output, 'wu_tours should be defined in inline script');
		$this->assertStringContainsString('wu_tours_vars', $output, 'wu_tours_vars should be defined in inline script');
		$this->assertTrue(wp_style_is('shepherd', 'enqueued'), 'shepherd style should be enqueued');

		// wu-admin must NOT have wu_tours localized onto it.
		global $wp_scripts;
		$wu_admin_data = $wp_scripts->get_data('wu-admin', 'data');
		$this->assertStringNotContainsString('wu_tours', (string) $wu_admin_data, 'wu_tours must not be localized onto wu-admin');

		// Reset.
		$prop->setValue($instance, []);
	}

	/**
	 * Test create_tour persists the finished flag as soon as the tour is queued.
	 *
	 * Regression test for the "tour repeats on every page load" symptom: prior
	 * to this fix the finished flag was only written via the AJAX dismissal
	 * triggered by Shepherd's complete / cancel events. Users who navigated
	 * away or refreshed the page before clicking through to the last step
	 * never persisted the dismissal and kept seeing the same tour. Marking
	 * the tour finished at queue time guarantees one-shot semantics.
	 */
	public function test_create_tour_marks_finished_on_render(): void {

		$instance = $this->get_instance();

		$reflection = new \ReflectionClass($instance);
		$tours_prop = $reflection->getProperty('tours');
		$tours_prop->setAccessible(true);
		$tours_prop->setValue($instance, []);

		$get_meta_key = $reflection->getMethod('get_meta_key');
		$get_meta_key->setAccessible(true);
		$is_finished = $reflection->getMethod('is_tour_finished');
		$is_finished->setAccessible(true);

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$meta_key = $get_meta_key->invoke($instance, 'rendered-tour');

		delete_user_meta($user_id, $meta_key);
		$this->reset_user_settings_cache($user_id);
		$this->assertFalse($is_finished->invoke($instance, 'rendered-tour', $user_id));

		$this->reset_admin_header_action();

		$instance->create_tour(
			'rendered-tour',
			[
				[
					'id'   => 'step1',
					'text' => 'Welcome',
				],
			]
		);

		wp_set_current_user($user_id);
		do_action('in_admin_header');

		$registered = $tours_prop->getValue($instance);
		$this->assertArrayHasKey('rendered-tour', $registered, 'tour should be queued for display');

		$this->assertSame(
			'1',
			get_user_meta($user_id, $meta_key, true),
			'finished flag should be persisted as soon as the tour is rendered'
		);

		$tours_prop->setValue($instance, []);
	}

	/**
	 * Test create_tour does NOT persist the finished flag when $once is false.
	 *
	 * Some tours are intentionally configured to be shown on every page load
	 * (e.g. troubleshooting walkthroughs). Those callers pass $once = false to
	 * opt out of one-shot semantics, and the on-render persistence must
	 * respect that contract.
	 */
	public function test_create_tour_does_not_mark_finished_when_once_is_false(): void {

		$instance = $this->get_instance();

		$reflection = new \ReflectionClass($instance);
		$tours_prop = $reflection->getProperty('tours');
		$tours_prop->setAccessible(true);
		$tours_prop->setValue($instance, []);

		$get_meta_key = $reflection->getMethod('get_meta_key');
		$get_meta_key->setAccessible(true);

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$meta_key = $get_meta_key->invoke($instance, 'always-show-tour');

		delete_user_meta($user_id, $meta_key);

		$this->reset_admin_header_action();

		$instance->create_tour(
			'always-show-tour',
			[
				[
					'id'   => 'step1',
					'text' => 'Always',
				],
			],
			false
		);

		do_action('in_admin_header');

		$registered = $tours_prop->getValue($instance);
		$this->assertArrayHasKey('always-show-tour', $registered, 'non-once tour should be queued for display');

		$this->assertSame(
			'',
			(string) get_user_meta($user_id, $meta_key, true),
			'finished flag must NOT be written when $once is false'
		);

		$tours_prop->setValue($instance, []);
	}

	/**
	 * Test create_tour does NOT persist the finished flag when a filter forces visibility.
	 *
	 * Integrations can force a completed tour to render again via the
	 * wu_tour_finished filter. That override should not rewrite the finished
	 * flag; the on-render persistence only applies to a genuine first render.
	 */
	public function test_create_tour_does_not_mark_finished_when_filter_forces_visibility(): void {

		$instance = $this->get_instance();

		$reflection = new \ReflectionClass($instance);
		$tours_prop = $reflection->getProperty('tours');
		$tours_prop->setAccessible(true);
		$tours_prop->setValue($instance, []);

		$get_meta_key = $reflection->getMethod('get_meta_key');
		$get_meta_key->setAccessible(true);

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$meta_key = $get_meta_key->invoke($instance, 'forced-tour');

		update_user_meta($user_id, $meta_key, 'already-finished');

		$this->reset_admin_header_action();

		$force_visibility = static function ($finished, $tour_id) {

			if ('forced-tour' === $tour_id) {
				return false;
			}

			return $finished;
		};

		add_filter('wu_tour_finished', $force_visibility, 10, 2);

		try {
			$instance->create_tour(
				'forced-tour',
				[
					[
						'id'   => 'step1',
						'text' => 'Forced',
					],
				]
			);

			do_action('in_admin_header');
		} finally {
			remove_filter('wu_tour_finished', $force_visibility, 10);
		}

		$registered = $tours_prop->getValue($instance);
		$this->assertArrayHasKey('forced-tour', $registered, 'filter-forced tour should be queued for display');

		$this->assertSame(
			'already-finished',
			get_user_meta($user_id, $meta_key, true),
			'finished flag should remain unchanged when a filter forces visibility'
		);

		$tours_prop->setValue($instance, []);
	}
}
