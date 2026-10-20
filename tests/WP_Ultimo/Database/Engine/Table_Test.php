<?php
/**
 * Tests for database table switching.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Database\Engine;

use WP_UnitTestCase;
use WP_Ultimo\Database\Products\Products_Table;

/**
 * Tests switch_blog registration for Ultimate Multisite database tables.
 */
class Table_Test extends WP_UnitTestCase {

	/**
	 * Tests global tables do not register a switch_blog callback.
	 */
	public function test_global_tables_do_not_register_switch_blog_callback(): void {
		$table = new Products_Table();

		$this->assertFalse(has_action('switch_blog', [$table, 'switch_blog']));
	}

	/**
	 * Tests global table state remains unchanged while switching sites.
	 */
	public function test_global_table_state_remains_unchanged_while_switching_sites(): void {
		$table   = new Products_Table();
		$site_id = self::factory()->blog->create();
		$before  = $this->get_table_state($table);

		switch_to_blog($site_id);
		$switched = $this->get_table_state($table);
		restore_current_blog();
		$restored = $this->get_table_state($table);

		$this->assertSame($before, $switched);
		$this->assertSame($before, $restored);
	}

	/**
	 * Tests per-site tables retain their callback and switch table state.
	 */
	public function test_per_site_tables_retain_switch_blog_callback_and_restore_state(): void {
		$table = new class() extends Table {

			protected $name = 'table_test_local';

			protected $version = '1.0.0';

			protected function set_schema(): void {
				$this->schema = 'id bigint(20) NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)';
			}
		};

		$table->switch_blog(get_current_blog_id());

		$this->assertSame(10, has_action('switch_blog', [$table, 'switch_blog']));

		$site_id = self::factory()->blog->create();
		$before  = $this->get_table_state($table);

		switch_to_blog($site_id);
		$switched = $this->get_table_state($table);
		restore_current_blog();
		$restored = $this->get_table_state($table);

		$this->assertSame($site_id, $switched['site_id']);
		$this->assertNotSame($before['table_name'], $switched['table_name']);
		$this->assertNotSame($before['table_prefix'], $switched['table_prefix']);
		$this->assertNotSame($before['database_interface'], $switched['database_interface']);
		$this->assertSame($before, $restored);
	}

	/**
	 * Returns the state affected by BerlinDB's switch_blog callback.
	 *
	 * @param Table $table Table instance.
	 * @return array<string, mixed>
	 */
	private function get_table_state(Table $table): array {
		$prefixed_name = $this->get_table_property($table, 'prefixed_name');

		return [
			'table_name'         => $this->get_table_property($table, 'table_name'),
			'table_prefix'       => $this->get_table_property($table, 'table_prefix'),
			'database_interface' => $GLOBALS['wpdb']->{$prefixed_name},
			'version'            => $this->get_table_property($table, 'version'),
			'site_id'            => $this->get_table_property($table, 'site_id'),
		];
	}

	/**
	 * Gets an inherited protected property.
	 *
	 * @param Table  $table    Table instance.
	 * @param string $property Property name.
	 * @return mixed
	 */
	private function get_table_property(Table $table, string $property) {
		$reflection = new \ReflectionProperty($table, $property);

		return $reflection->getValue($table);
	}

	/**
	 * On a secondary network the WordPress meta API must resolve model meta
	 * types to that network's prefixed table, not an unset property.
	 */
	public function test_secondary_network_meta_table_is_registered_for_the_meta_api(): void {
		global $wpdb;

		$canonical = $wpdb->wu_customermeta ?? null;
		$main      = static fn() => get_current_network_id() + 1000;

		add_filter('get_main_network_id', $main);

		try {
			$table    = new \WP_Ultimo\Database\Customers\Customers_Meta_Table();
			$expected = $wpdb->base_prefix . 'wu_' . get_current_network_id() . '_customermeta';

			$this->assertSame($expected, $table->table_name);
			$this->assertSame($expected, $wpdb->wu_customermeta);
			$this->assertSame($expected, _get_meta_table('wu_customer'));
		} finally {
			remove_filter('get_main_network_id', $main);
			$wpdb->wu_customermeta = $canonical;
		}

		// The main network keeps the unprefixed table.
		$table = new \WP_Ultimo\Database\Customers\Customers_Meta_Table();

		$this->assertSame($wpdb->base_prefix . 'wu_customermeta', $wpdb->wu_customermeta);
		$this->assertSame($wpdb->base_prefix . 'wu_customermeta', $table->table_name);
	}
}
