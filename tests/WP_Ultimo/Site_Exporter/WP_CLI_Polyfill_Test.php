<?php
/**
 * Regression test for the MU-Migration WP-CLI polyfill on web/AJAX requests.
 */

namespace WP_Ultimo\Tests\Site_Exporter;

use WP_UnitTestCase;

/**
 * @group site-exporter
 * @group wp-cli-polyfill
 */
class WP_CLI_Polyfill_Test extends WP_UnitTestCase {

	/**
	 * The WP-CLI facade defined for web/AJAX MU-Migration runs must not fatal
	 * third-party activation probes.
	 *
	 * WooCommerce Subscriptions (and other plugins) do:
	 *
	 *     if ( class_exists( 'WP_CLI' ) ) {
	 *         $args = WP_CLI::get_runner()->arguments;
	 *         ...
	 *     }
	 *
	 * Once this facade is loaded on a normal web request, class_exists() becomes
	 * true, so get_runner() must exist and return a runner-like object with a
	 * countable `arguments` list. Without it, every front-end and admin request
	 * fatals with "Call to undefined method WP_CLI::get_runner()" and the whole
	 * site returns HTTP 500.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_polyfill_facade_is_web_safe_for_activation_probes(): void {

		if (class_exists('WP_CLI', false) || (defined('WP_CLI') && WP_CLI)) {
			$this->markTestSkipped('Real WP-CLI is loaded; the polyfill facade is not used.');
		}

		require dirname(__DIR__, 3) . '/inc/site-exporter/mu-migration/includes/wp-cli-polyfill.php';

		$this->assertTrue(class_exists('WP_CLI', false), 'Polyfill must define the WP_CLI facade on web requests.');
		$this->assertTrue(method_exists('WP_CLI', 'get_runner'), 'Facade must expose get_runner() so third-party probes do not fatal.');

		$runner = \WP_CLI::get_runner();

		$this->assertIsObject($runner, 'get_runner() must return a runner-like object.');
		$this->assertTrue(is_countable($runner->arguments), 'get_runner()->arguments must be countable so activation probes short-circuit safely.');
		$this->assertCount(0, $runner->arguments, 'No CLI command runs on a web request, so arguments is empty.');

		// Any other unknown static method must be a no-op, never a fatal.
		$this->assertNull(\WP_CLI::method_a_third_party_plugin_may_call());
	}
}
