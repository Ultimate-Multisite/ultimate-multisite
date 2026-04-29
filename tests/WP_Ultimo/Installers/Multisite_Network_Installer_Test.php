<?php
/**
 * Unit tests for Multisite_Network_Installer class.
 *
 * @package WP_Ultimo\Tests\Installers
 */

namespace WP_Ultimo\Tests\Installers;

use WP_Ultimo\Installers\Multisite_Network_Installer;

/**
 * Unit tests for Multisite_Network_Installer.
 *
 * Tests cover:
 * - get_steps() structure and keys
 * - check_network_tables_exist() via reflection
 * - get_config() throws when transient is missing
 * - _install_network_activate() via reflection:
 *   - returns early when plugin already in active_sitewide_plugins
 *   - inserts plugin when no active_sitewide_plugins row exists
 *   - updates plugin when active_sitewide_plugins row exists without the plugin
 *   - does not duplicate when plugin already present in existing row
 */
class Multisite_Network_Installer_Test extends \WP_UnitTestCase {

	/**
	 * Installer instance under test.
	 *
	 * @var Multisite_Network_Installer
	 */
	protected $installer;

	/**
	 * Sitemeta table name.
	 *
	 * @var string
	 */
	protected $sitemeta_table;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {

		parent::setUp();

		global $wpdb;

		$this->installer      = Multisite_Network_Installer::get_instance();
		$this->sitemeta_table = $wpdb->base_prefix . 'sitemeta';
	}

	/**
	 * Tear down: restore sitemeta state for active_sitewide_plugins.
	 */
	public function tearDown(): void {

		global $wpdb;

		// Remove any test-inserted active_sitewide_plugins rows for site_id=1
		// that contain WP_ULTIMO_PLUGIN_BASENAME, to avoid polluting other tests.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_value FROM {$this->sitemeta_table} WHERE meta_key = %s AND site_id = %d",
				'active_sitewide_plugins',
				1
			)
		);

		foreach ( $rows as $row ) {
			$plugins = maybe_unserialize( $row->meta_value );
			if ( is_array( $plugins ) && isset( $plugins[ WP_ULTIMO_PLUGIN_BASENAME ] ) ) {
				unset( $plugins[ WP_ULTIMO_PLUGIN_BASENAME ] );
				$wpdb->update(
					$this->sitemeta_table,
					array( 'meta_value' => serialize( $plugins ) ),
					array( 'meta_id' => $row->meta_id )
				);
			}
		}

		delete_transient( Multisite_Network_Installer::CONFIG_TRANSIENT );

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * get_instance() returns a Multisite_Network_Installer.
	 */
	public function test_get_instance_returns_installer(): void {

		$this->assertInstanceOf( Multisite_Network_Installer::class, $this->installer );
	}

	/**
	 * get_instance() returns the same object on repeated calls.
	 */
	public function test_get_instance_is_singleton(): void {

		$a = Multisite_Network_Installer::get_instance();
		$b = Multisite_Network_Installer::get_instance();

		$this->assertSame( $a, $b );
	}

	// -------------------------------------------------------------------------
	// get_steps()
	// -------------------------------------------------------------------------

	/**
	 * get_steps() returns an array.
	 */
	public function test_get_steps_returns_array(): void {

		$this->assertIsArray( $this->installer->get_steps() );
	}

	/**
	 * get_steps() contains the four expected step keys.
	 */
	public function test_get_steps_contains_expected_keys(): void {

		$steps = $this->installer->get_steps();
		$keys  = array_keys( $steps );

		$this->assertContains( 'enable_multisite', $keys );
		$this->assertContains( 'create_network', $keys );
		$this->assertContains( 'update_wp_config', $keys );
		$this->assertContains( 'network_activate', $keys );
	}

	/**
	 * Each step has done, title, description, pending, installing, success keys.
	 */
	public function test_get_steps_each_step_has_required_keys(): void {

		$required = array( 'done', 'title', 'description', 'pending', 'installing', 'success' );

		foreach ( $this->installer->get_steps() as $key => $step ) {
			foreach ( $required as $field ) {
				$this->assertArrayHasKey( $field, $step, "Step '{$key}' is missing '{$field}'." );
			}
		}
	}

	/**
	 * network_activate step 'done' is a boolean.
	 */
	public function test_network_activate_step_done_is_bool(): void {

		$steps = $this->installer->get_steps();

		$this->assertIsBool( $steps['network_activate']['done'] );
	}

	// -------------------------------------------------------------------------
	// all_done() / check_network_tables_exist()
	// -------------------------------------------------------------------------

	/**
	 * all_done() returns a boolean.
	 */
	public function test_all_done_returns_bool(): void {

		$this->assertIsBool( $this->installer->all_done() );
	}

	/**
	 * check_network_tables_exist() returns true in multisite test environment.
	 */
	public function test_check_network_tables_exist_returns_true_in_multisite(): void {

		$reflection = new \ReflectionClass( $this->installer );
		$method     = $reflection->getMethod( 'check_network_tables_exist' );
		$method->setAccessible( true );

		// WP_TESTS_MULTISITE=1 so the site table exists.
		$this->assertTrue( $method->invoke( $this->installer ) );
	}

	// -------------------------------------------------------------------------
	// get_config() — throws when transient missing
	// -------------------------------------------------------------------------

	/**
	 * get_config() throws Exception when no transient is stored.
	 */
	public function test_get_config_throws_when_transient_missing(): void {

		delete_transient( Multisite_Network_Installer::CONFIG_TRANSIENT );

		$reflection = new \ReflectionClass( $this->installer );
		$method     = $reflection->getMethod( 'get_config' );
		$method->setAccessible( true );

		$this->expectException( \Exception::class );

		$method->invoke( $this->installer );
	}

	/**
	 * get_config() returns the stored transient array when present.
	 */
	public function test_get_config_returns_transient_array(): void {

		$config = array(
			'subdomain_install' => false,
			'sitename'          => 'Test Network',
			'email'             => 'admin@example.com',
			'domain'            => 'example.com',
			'base'              => '/',
		);

		set_transient( Multisite_Network_Installer::CONFIG_TRANSIENT, $config, HOUR_IN_SECONDS );

		$reflection = new \ReflectionClass( $this->installer );
		$method     = $reflection->getMethod( 'get_config' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->installer );

		$this->assertEquals( $config, $result );
	}

	// -------------------------------------------------------------------------
	// _install_network_activate() — core bug fix coverage
	// -------------------------------------------------------------------------

	/**
	 * Helper: call _install_network_activate() via reflection.
	 */
	private function call_install_network_activate(): void {

		$reflection = new \ReflectionClass( $this->installer );
		$method     = $reflection->getMethod( '_install_network_activate' );
		$method->setAccessible( true );
		$method->invoke( $this->installer );
	}

	/**
	 * Helper: fetch active_sitewide_plugins from sitemeta for site_id=1.
	 */
	private function get_active_sitewide_plugins(): array {

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta_value FROM {$this->sitemeta_table} WHERE meta_key = %s AND site_id = %d",
				'active_sitewide_plugins',
				1
			)
		);

		if ( ! $row ) {
			return array();
		}

		$plugins = maybe_unserialize( $row->meta_value );

		return is_array( $plugins ) ? $plugins : array();
	}

	/**
	 * _install_network_activate() adds the plugin to active_sitewide_plugins.
	 *
	 * This is the main regression test for issue #837: the previous
	 * activate_plugin() call depended on is_multisite() being true, which
	 * fails when OPcache serves a stale wp-config.php after the MULTISITE
	 * constant is written. The direct sitemeta write must succeed regardless.
	 */
	public function test_install_network_activate_adds_plugin_to_sitemeta(): void {

		// Ensure plugin is not in active_sitewide_plugins before the call.
		$before = $this->get_active_sitewide_plugins();
		$this->assertArrayNotHasKey( WP_ULTIMO_PLUGIN_BASENAME, $before );

		$this->call_install_network_activate();

		$after = $this->get_active_sitewide_plugins();
		$this->assertArrayHasKey( WP_ULTIMO_PLUGIN_BASENAME, $after );
	}

	/**
	 * _install_network_activate() is idempotent: calling twice does not add
	 * a second entry for the same plugin.
	 */
	public function test_install_network_activate_is_idempotent(): void {

		$this->call_install_network_activate();
		$this->call_install_network_activate();

		// Count how many times the key appears — must be exactly 1.
		$plugins = $this->get_active_sitewide_plugins();
		$count   = array_count_values( array_keys( $plugins ) );

		$this->assertEquals( 1, $count[ WP_ULTIMO_PLUGIN_BASENAME ] );
	}

	/**
	 * _install_network_activate() returns early when plugin is already in
	 * active_sitewide_plugins, preserving all other entries.
	 */
	public function test_install_network_activate_returns_early_when_already_active(): void {

		global $wpdb;

		// Pre-populate sitemeta so the plugin is already network-activated.
		$plugins = array(
			WP_ULTIMO_PLUGIN_BASENAME => time(),
			'other-plugin/other.php'  => time(),
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta_id FROM {$this->sitemeta_table} WHERE meta_key = %s AND site_id = %d",
				'active_sitewide_plugins',
				1
			)
		);

		if ( $row ) {
			$wpdb->update(
				$this->sitemeta_table,
				array( 'meta_value' => serialize( $plugins ) ),
				array( 'meta_id' => $row->meta_id )
			);
		} else {
			$wpdb->insert(
				$this->sitemeta_table,
				array(
					'site_id'    => 1,
					'meta_key'   => 'active_sitewide_plugins',
					'meta_value' => serialize( $plugins ),
				)
			);
		}

		// Call the method — it should not throw and should not remove other entries.
		$this->call_install_network_activate();

		$after = $this->get_active_sitewide_plugins();

		$this->assertArrayHasKey( WP_ULTIMO_PLUGIN_BASENAME, $after );
		$this->assertArrayHasKey( 'other-plugin/other.php', $after );
	}

	/**
	 * _install_network_activate() preserves existing plugins when updating the
	 * active_sitewide_plugins row.
	 */
	public function test_install_network_activate_preserves_existing_plugins(): void {

		global $wpdb;

		$existing_plugin = 'some-other-plugin/plugin.php';

		// Pre-populate sitemeta with a different plugin already active.
		$plugins = array( $existing_plugin => time() );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta_id FROM {$this->sitemeta_table} WHERE meta_key = %s AND site_id = %d",
				'active_sitewide_plugins',
				1
			)
		);

		if ( $row ) {
			$wpdb->update(
				$this->sitemeta_table,
				array( 'meta_value' => serialize( $plugins ) ),
				array( 'meta_id' => $row->meta_id )
			);
		} else {
			$wpdb->insert(
				$this->sitemeta_table,
				array(
					'site_id'    => 1,
					'meta_key'   => 'active_sitewide_plugins',
					'meta_value' => serialize( $plugins ),
				)
			);
		}

		$this->call_install_network_activate();

		$after = $this->get_active_sitewide_plugins();

		// The plugin under test must be added.
		$this->assertArrayHasKey( WP_ULTIMO_PLUGIN_BASENAME, $after );
		// The pre-existing plugin must be preserved.
		$this->assertArrayHasKey( $existing_plugin, $after );
	}

	/**
	 * _install_network_activate() stores a timestamp (integer) as the value
	 * for the activated plugin entry.
	 */
	public function test_install_network_activate_stores_timestamp_value(): void {

		$before_time = time();

		$this->call_install_network_activate();

		$plugins      = $this->get_active_sitewide_plugins();
		$stored_value = $plugins[ WP_ULTIMO_PLUGIN_BASENAME ];

		$this->assertIsInt( $stored_value );
		$this->assertGreaterThanOrEqual( $before_time, $stored_value );
	}

	// -------------------------------------------------------------------------
	// handle() integration — network_activate step via Base_Installer
	// -------------------------------------------------------------------------

	/**
	 * handle() with 'network_activate' returns true (no error) and results in
	 * the plugin being present in sitemeta.
	 */
	public function test_handle_network_activate_step_activates_plugin(): void {

		// Ensure plugin is not present before.
		$before = $this->get_active_sitewide_plugins();
		$this->assertArrayNotHasKey( WP_ULTIMO_PLUGIN_BASENAME, $before );

		$result = $this->installer->handle( true, 'network_activate', $this );

		$this->assertNotWPError( $result );

		$after = $this->get_active_sitewide_plugins();
		$this->assertArrayHasKey( WP_ULTIMO_PLUGIN_BASENAME, $after );
	}

	/**
	 * _install_network_activate() invalidates the object-cache entry so that
	 * is_plugin_active_for_network() returns true immediately after the write.
	 *
	 * This is a regression test for the persistent-object-cache bug: direct
	 * sitemeta writes without a corresponding wp_cache_delete() left a stale
	 * active_sitewide_plugins entry in the cache, causing the "Network
	 * Activate" button to appear to do nothing — the AJAX returned success and
	 * the page reloaded, but the plugin still showed "NOT Network Activated"
	 * because get_site_option() returned the cached (pre-write) value.
	 */
	public function test_install_network_activate_invalidates_object_cache(): void {

		// Prime the cache with an empty plugins list (simulates a persistent
		// cache that was populated before our write).
		$network_id = get_current_network_id();
		wp_cache_set( "{$network_id}:active_sitewide_plugins", array(), 'site-options' );

		$this->call_install_network_activate();

		// After the write+cache-invalidation, is_plugin_active_for_network()
		// must return true even if it was previously returning false due to
		// a stale cache entry.
		$this->assertTrue( is_plugin_active_for_network( WP_ULTIMO_PLUGIN_BASENAME ) );
	}
}
