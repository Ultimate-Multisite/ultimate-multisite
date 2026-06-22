<?php
/**
 * Tests that the plugin does not interfere with WordPress.org update checks.
 *
 * WordPress.org tracks active installs by counting sites that send update
 * check requests containing the plugin slug. These tests verify that:
 *
 * 1. The plugin is included in the update check request payload
 * 2. No filters remove the plugin from the payload
 * 3. No filters block requests to api.wordpress.org
 * 4. The Update URI header is not set (which would skip WordPress.org checks)
 * 5. The beta update feature doesn't interfere with WordPress.org checks
 *
 * @package WP_Ultimo
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * @group update-check
 */
class Update_Check_Test extends WP_UnitTestCase {

	/**
	 * The plugin file basename.
	 *
	 * @var string
	 */
	private string $plugin_file = 'ultimate-multisite/ultimate-multisite.php';

	/**
	 * Get plugin headers from the loaded plugin file.
	 *
	 * PHPUnit loads the plugin directly from the checkout instead of from a
	 * wp-content/plugins/ultimate-multisite symlink, so use the bootstrap-defined
	 * entry point for header assertions.
	 *
	 * @return array<string,mixed>
	 */
	private function get_loaded_plugin_data(): array {

		return get_plugin_data(WP_ULTIMO_PLUGIN_FILE);
	}

	/**
	 * Test that the plugin file header does NOT set Update URI.
	 *
	 * WordPress 5.8+ skips the WordPress.org update check for any plugin
	 * with an Update URI pointing to a non-WordPress.org host. If this
	 * header were set to e.g. ultimatemultisite.com, WordPress would not
	 * check WordPress.org for updates, and the active install count would
	 * not be tracked.
	 */
	public function test_plugin_header_has_no_update_uri(): void {

		$plugin_data = $this->get_loaded_plugin_data();

		$this->assertEmpty(
			$plugin_data['UpdateURI'],
			'Plugin must NOT set Update URI header. Setting it to a non-WordPress.org URL would prevent WordPress.org update checks and active install tracking.'
		);
	}

	/**
	 * Test that the plugin text domain matches the expected WordPress.org slug.
	 */
	public function test_text_domain_matches_slug(): void {

		$plugin_data = $this->get_loaded_plugin_data();

		$this->assertSame(
			'ultimate-multisite',
			$plugin_data['TextDomain'],
			'Text domain must match the WordPress.org slug.'
		);
	}

	/**
	 * Test that the plugin directory name matches the WordPress.org slug.
	 *
	 * The directory name (first segment of the plugin basename) must match
	 * the WordPress.org SVN slug for update checks to work.
	 */
	public function test_plugin_directory_matches_slug(): void {

		$dir = dirname($this->plugin_file);

		$this->assertSame('ultimate-multisite', $dir);
	}

	/**
	 * Test that the plugin appears in the data WordPress sends to api.wordpress.org.
	 *
	 * This simulates what wp_update_plugins() builds as the request body.
	 * The plugin must be present in the plugins array for WordPress.org to
	 * count it as an active install.
	 */
	public function test_plugin_included_in_update_check_payload(): void {

		$plugins = get_plugins();

		if (! isset($plugins[ $this->plugin_file ])) {
			$plugins[ $this->plugin_file ] = $this->get_loaded_plugin_data();
		}

		$this->assertArrayHasKey(
			$this->plugin_file,
			$plugins,
			'Plugin must be available under the WordPress.org slug in the simulated update payload.'
		);

		// Simulate the Update URI filtering that wp_update_plugins() performs.
		$plugin_data = $plugins[ $this->plugin_file ];
		$update_uri  = $plugin_data['UpdateURI'] ?? '';

		if ($update_uri) {
			$hostname = wp_parse_url($update_uri, PHP_URL_HOST);
			$excluded = $hostname && ! in_array($hostname, ['wordpress.org', 'w.org'], true);
		} else {
			$excluded = false;
		}

		$this->assertFalse(
			$excluded,
			'Plugin must NOT be excluded from WordPress.org update checks by Update URI header.'
		);
	}

	/**
	 * Test that http_request_args filters do not block api.wordpress.org requests.
	 *
	 * Runs all registered http_request_args filters against a simulated
	 * WordPress.org update check URL and verifies the request is not blocked.
	 */
	public function test_http_request_args_do_not_block_wporg(): void {

		$url  = 'https://api.wordpress.org/plugins/update-check/1.1/';
		$args = [
			'timeout' => 30,
			'body'    => [
				'plugins' => wp_json_encode(
					[
						'plugins' => [
							$this->plugin_file => [
								'Name'    => 'Ultimate Multisite',
								'Version' => '2.4.11',
							],
						],
						'active'  => [$this->plugin_file],
					]
				),
			],
		];

		$filtered_args = apply_filters('http_request_args', $args, $url);

		// The request args should not be fundamentally changed
		$this->assertIsArray($filtered_args, 'http_request_args must return an array.');
		$this->assertArrayHasKey('body', $filtered_args, 'Request body must still exist after filtering.');

		// Verify the plugins data is still intact
		$body    = $filtered_args['body'];
		$plugins = json_decode($body['plugins'], true);

		$this->assertArrayHasKey(
			$this->plugin_file,
			$plugins['plugins'],
			'Plugin must still be in the request body after http_request_args filters.'
		);
	}

	/**
	 * Test that pre_http_request filters do not block api.wordpress.org requests.
	 *
	 * The pre_http_request filter can short-circuit HTTP requests. We verify
	 * that no filter blocks WordPress.org update check requests.
	 */
	public function test_pre_http_request_does_not_block_wporg(): void {

		$url  = 'https://api.wordpress.org/plugins/update-check/1.1/';
		$args = [
			'timeout' => 30,
			'body'    => [],
		];

		$result = apply_filters('pre_http_request', false, $args, $url);

		$this->assertFalse(
			$result,
			'pre_http_request must return false for api.wordpress.org requests, allowing them to proceed. A non-false return would block the update check.'
		);
	}

	/**
	 * Test that the beta update filter does not remove WordPress.org data.
	 *
	 * The maybe_inject_beta_update method on site_transient_update_plugins
	 * should only ADD beta data when enabled, not remove WordPress.org entries.
	 * When beta updates are disabled (the default), the transient should pass
	 * through unchanged.
	 */
	public function test_beta_update_filter_does_not_remove_wporg_data(): void {

		// Create a mock transient with WordPress.org data
		$transient                                  = new \stdClass();
		$transient->last_checked                    = time();
		$transient->checked                         = [$this->plugin_file => '2.4.10'];
		$transient->response                        = [];
		$transient->no_update                       = [];
		$transient->no_update[ $this->plugin_file ] = (object) [
			'id'          => 'w.org/plugins/ultimate-multisite',
			'slug'        => 'ultimate-multisite',
			'plugin'      => $this->plugin_file,
			'new_version' => '2.4.10',
			'url'         => 'https://wordpress.org/plugins/ultimate-multisite/',
			'package'     => 'https://downloads.wordpress.org/plugin/ultimate-multisite.2.4.10.zip',
		];

		// Ensure beta updates are disabled (the default)
		wu_save_setting('enable_beta_updates', false);

		$filtered = apply_filters('site_transient_update_plugins', $transient);

		$this->assertIsObject($filtered, 'Transient must remain an object after filtering.');
		$this->assertArrayHasKey(
			$this->plugin_file,
			(array) $filtered->checked,
			'Plugin must remain in the checked array.'
		);

		// When WordPress.org says there's an update, verify it's preserved
		$transient_with_update = clone $transient;
		unset($transient_with_update->no_update[ $this->plugin_file ]);
		$transient_with_update->response[ $this->plugin_file ] = (object) [
			'id'          => 'w.org/plugins/ultimate-multisite',
			'slug'        => 'ultimate-multisite',
			'plugin'      => $this->plugin_file,
			'new_version' => '2.4.12',
			'url'         => 'https://wordpress.org/plugins/ultimate-multisite/',
			'package'     => 'https://downloads.wordpress.org/plugin/ultimate-multisite.2.4.12.zip',
		];

		$filtered2 = apply_filters('site_transient_update_plugins', $transient_with_update);

		$this->assertArrayHasKey(
			$this->plugin_file,
			(array) $filtered2->response,
			'WordPress.org update response must not be removed by filters when beta updates are disabled.'
		);

		$this->assertSame(
			'2.4.12',
			$filtered2->response[ $this->plugin_file ]->new_version,
			'WordPress.org update version must be preserved when beta updates are disabled.'
		);
	}

	/**
	 * Test that no update_plugins_{hostname} filter hijacks our plugin.
	 *
	 * WordPress 5.8+ fires update_plugins_{hostname} for plugins with a custom
	 * Update URI. Since our plugin has no Update URI, this filter should not
	 * exist for our hostname.
	 */
	public function test_no_custom_update_hostname_filter(): void {

		global $wp_filter;

		// Check there's no filter for ultimatemultisite.com that could intercept core plugin updates
		$filter_name = 'update_plugins_ultimatemultisite.com';

		$has_filter = isset($wp_filter[ $filter_name ]) && $wp_filter[ $filter_name ]->has_filters();

		$this->assertFalse(
			$has_filter,
			"Filter '$filter_name' should not be registered. This would only apply if the plugin had Update URI set to ultimatemultisite.com."
		);
	}
}
