<?php
/**
 * Tests shared model meta columns on secondary networks.
 *
 * @package WP_Ultimo
 * @subpackage Tests\Database
 * @since 2.18.0
 */

namespace WP_Ultimo\Database;

use WP_Ultimo\Database\Engine\Table;
use WP_Ultimo\Database\Memberships\Memberships_Meta_Table;
use WP_Ultimo\Database\Memberships\Memberships_Table;
use WP_Ultimo\Models\Membership;

defined('ABSPATH') || exit;

// These fixtures deliberately inspect and alter trusted test table identifiers.
// phpcs:disable WordPress.DB

class Network_Meta_Columns_Test extends \WP_UnitTestCase {

	private $original_network;
	private $original_meta_tables = [];
	private $original_global_tables;
	private $tables = [];

	public function set_up(): void {
		parent::set_up();

		global $current_site, $wpdb;

		// SHOW TABLES cannot see the temporary tables normally used by WP tests.
		// Use real, isolated secondary-network tables and explicitly drop them.
		remove_filter('query', [$this, '_create_temporary_tables']);
		remove_filter('query', [$this, '_drop_temporary_tables']);

		$this->original_network       = $current_site;
		$this->original_global_tables = $wpdb->ms_global_tables;

		foreach (self::meta_tables() as [, $entity]) {
			$property                                = 'wu_' . $entity . 'meta';
			$this->original_meta_tables[ $property ] = $wpdb->{$property} ?? null;
			wp_cache_delete(4242, 'wu_' . $entity . '_meta');
		}

		$current_site = self::factory()->network->create_and_get();
	}

	public function tear_down(): void {
		global $current_site, $wpdb;

		foreach ($this->tables as $table) {
			$table->uninstall();
			delete_network_option(get_current_network_id(), $table->prefixed_name . '_meta_column_version');
			unset($wpdb->{$table->prefixed_name});
		}

		foreach ($this->original_meta_tables as $property => $value) {
			$wpdb->{$property} = $value;
		}

		$wpdb->ms_global_tables = $this->original_global_tables;
		$current_site           = $this->original_network;

		parent::tear_down();
	}

	public static function meta_tables(): array {
		return [
			'checkout forms' => [\WP_Ultimo\Database\Checkout_Forms\Checkout_Forms_Meta_Table::class, 'form'],
			'customers'      => [\WP_Ultimo\Database\Customers\Customers_Meta_Table::class, 'customer'],
			'discount codes' => [\WP_Ultimo\Database\Discount_Codes\Discount_Codes_Meta_Table::class, 'discount_code'],
			'domains'        => [\WP_Ultimo\Database\Domains\Domains_Meta_Table::class, 'domain'],
			'memberships'    => [Memberships_Meta_Table::class, 'membership'],
			'payments'       => [\WP_Ultimo\Database\Payments\Payments_Meta_Table::class, 'payment'],
			'posts'          => [\WP_Ultimo\Database\Posts\Posts_Meta_Table::class, 'post'],
			'products'       => [\WP_Ultimo\Database\Products\Products_Meta_Table::class, 'product'],
		];
	}

	/** @dataProvider meta_tables */
	public function test_fresh_tables_use_shared_columns(string $table_class, string $entity): void {
		global $wpdb;

		$table   = $this->create_table($table_class);
		$columns = wp_list_pluck($table->columns(), 'Field');

		$this->assertContains('wu_' . $entity . '_id', $columns);
		$this->assertNotContains('wu_' . get_current_network_id() . '_' . $entity . '_id', $columns);
		$this->assertSame($table->table_name, _get_meta_table('wu_' . $entity));

		$value = [
			'nested' => [
				'limit'   => 12,
				'enabled' => true,
			],
		];

		$this->assertNotFalse(update_metadata('wu_' . $entity, 4242, 'wu_limitations', $value));
		wp_cache_delete(4242, 'wu_' . $entity . '_meta');
		$this->assertSame($value, get_metadata('wu_' . $entity, 4242, 'wu_limitations', true));
		$this->assertSame('', $wpdb->last_error);

		// Already-correct tables (including CREATE TABLE LIKE copies) need no ALTER.
		$queries = $this->capture_table_queries($table_class, $table->table_name);
		$this->assertCount(0, preg_grep('/^ALTER TABLE/i', $queries));
		$this->assertSame('2.18.0', get_network_option(get_current_network_id(), $table->prefixed_name . '_meta_column_version'));
	}

	/** @dataProvider meta_tables */
	public function test_legacy_columns_are_migrated_without_losing_rows_or_indexes(string $table_class, string $entity): void {
		global $wpdb;

		$table  = $this->create_table($table_class);
		$column = 'wu_' . $entity . '_id';
		$legacy = 'wu_' . get_current_network_id() . '_' . $entity . '_id';
		$value  = [
			'plans' => [
				'storage' => 512,
				'title'   => "Network's plan",
			],
		];

		$this->assertNotFalse($wpdb->query("ALTER TABLE {$table->table_name} CHANGE COLUMN {$column} {$legacy} bigint(20) unsigned NOT NULL DEFAULT '0'"));
		$this->assertSame(1, $wpdb->insert($table->table_name, [
			$legacy      => 4242,
			'meta_key'   => 'wu_limitations',
			'meta_value' => maybe_serialize($value),
		]));
		$this->assertSame(1, $wpdb->insert($table->table_name, [
			$legacy      => 4243,
			'meta_key'   => 'pending_site',
			'meta_value' => '9876',
		]));

		$before  = $wpdb->get_results("SELECT meta_id, meta_key, meta_value FROM {$table->table_name} ORDER BY meta_id", ARRAY_A);
		$queries = $this->capture_table_queries($table_class, $table->table_name);

		$this->assertCount(1, preg_grep('/^ALTER TABLE/i', $queries));
		$this->assertSame($before, $wpdb->get_results("SELECT meta_id, meta_key, meta_value FROM {$table->table_name} ORDER BY meta_id", ARRAY_A));
		$this->assertSame(['4242', '4243'], $wpdb->get_col("SELECT {$column} FROM {$table->table_name} ORDER BY meta_id"));
		$this->assertNotContains($legacy, wp_list_pluck($table->columns(), 'Field'));
		$this->assertSame($column, $wpdb->get_var("SHOW INDEX FROM {$table->table_name} WHERE Key_name = '{$column}'", 4));

		wp_cache_delete(4242, 'wu_' . $entity . '_meta');
		$this->assertSame($value, get_metadata('wu_' . $entity, 4242, 'wu_limitations', true));
		$this->assertTrue(update_metadata('wu_' . $entity, 4242, 'wu_limitations', ['updated' => true]));
		wp_cache_delete(4242, 'wu_' . $entity . '_meta');
		$this->assertSame(['updated' => true], get_metadata('wu_' . $entity, 4242, 'wu_limitations', true));
		$this->assertTrue(delete_metadata('wu_' . $entity, 4242, 'wu_limitations'));
		$this->assertSame('', $wpdb->last_error);

		$this->assertSame([], $this->capture_table_queries($table_class, $table->table_name));
		$this->assertFalse(get_network_option(get_main_network_id(), $table->prefixed_name . '_meta_column_version', false));
	}

	public function test_membership_model_meta_round_trips_on_a_secondary_network(): void {
		global $wpdb;

		$table      = $this->create_table(Memberships_Table::class);
		$meta_table = $this->create_table(Memberships_Meta_Table::class);

		// Seed the row without firing unrelated notification/webhook handlers.
		$this->assertSame(1, $wpdb->insert($table->table_name, [
			'status'   => 'pending',
			'currency' => 'USD',
		]));
		$membership = wu_get_membership($wpdb->insert_id);

		$this->assertInstanceOf(Membership::class, $membership);
		$this->assertGreaterThan(0, $membership->get_id());
		$this->assertNotFalse($membership->update_meta('pending_site', ['site_id' => 123]));
		wp_cache_delete($membership->get_id(), 'wu_membership_meta');
		$this->assertSame(['site_id' => 123], $membership->get_meta('pending_site'));
		$this->assertSame('', $wpdb->last_error);

		$legacy = 'wu_' . get_current_network_id() . '_membership_id';
		$this->assertNotFalse($wpdb->query("ALTER TABLE {$meta_table->table_name} CHANGE COLUMN wu_membership_id {$legacy} bigint(20) unsigned NOT NULL DEFAULT '0'"));
		new Memberships_Meta_Table();

		wp_cache_delete($membership->get_id(), 'wu_membership_meta');
		$this->assertSame(['site_id' => 123], $membership->get_meta('pending_site'));
		$this->assertTrue($membership->update_meta('pending_site', ['site_id' => 456]));
		wp_cache_delete($membership->get_id(), 'wu_membership_meta');
		$this->assertSame(['site_id' => 456], $membership->get_meta('pending_site'));
		$this->assertSame('', $wpdb->last_error);
	}

	public function test_failed_migration_is_not_marked_complete_and_can_retry(): void {
		global $wpdb;

		$table  = $this->create_table(Memberships_Meta_Table::class);
		$legacy = 'wu_' . get_current_network_id() . '_membership_id';

		$wpdb->query("ALTER TABLE {$table->table_name} CHANGE COLUMN wu_membership_id {$legacy} bigint(20) unsigned NOT NULL DEFAULT '0'");
		$wpdb->insert($table->table_name, [
			$legacy      => 4242,
			'meta_key'   => 'pending_site',
			'meta_value' => '42',
		]);

		$block_alter = static function ($query) use ($table) {
			return str_starts_with($query, 'ALTER TABLE') && str_contains($query, $table->table_name) ? '' : $query;
		};
		add_filter('query', $block_alter);

		try {
			new Memberships_Meta_Table();
			$this->assertFalse(get_network_option(get_current_network_id(), $table->prefixed_name . '_meta_column_version', false));
			$this->assertContains($legacy, wp_list_pluck($table->columns(), 'Field'));
		} finally {
			remove_filter('query', $block_alter);
		}

		new Memberships_Meta_Table();

		$this->assertSame('2.18.0', get_network_option(get_current_network_id(), $table->prefixed_name . '_meta_column_version'));
		$this->assertSame('4242', $wpdb->get_var("SELECT wu_membership_id FROM {$table->table_name}"));
		$this->assertSame('42', get_metadata('wu_membership', 4242, 'pending_site', true));
	}

	public function test_ambiguous_columns_are_left_intact_and_unmarked(): void {
		global $wpdb;

		$table  = $this->create_table(Memberships_Meta_Table::class);
		$legacy = 'wu_' . get_current_network_id() . '_membership_id';

		$wpdb->query("ALTER TABLE {$table->table_name} ADD COLUMN {$legacy} bigint(20) unsigned NOT NULL DEFAULT '0'");
		$wpdb->insert($table->table_name, [
			'wu_membership_id' => 4242,
			$legacy            => 4243,
			'meta_key'         => 'pending_site',
			'meta_value'       => '42',
		]);
		$before = $wpdb->get_results("SELECT * FROM {$table->table_name}", ARRAY_A);

		$this->assertCount(0, preg_grep('/^ALTER TABLE/i', $this->capture_table_queries(Memberships_Meta_Table::class, $table->table_name)));
		$this->assertSame($before, $wpdb->get_results("SELECT * FROM {$table->table_name}", ARRAY_A));
		$this->assertFalse(get_network_option(get_current_network_id(), $table->prefixed_name . '_meta_column_version', false));
	}

	public function test_main_network_and_wordpress_core_meta_tables_are_not_migrated(): void {
		global $current_site, $wpdb;

		$secondary    = $current_site;
		$current_site = $this->original_network;

		try {
			$this->assertSame([], $this->capture_table_queries(Memberships_Meta_Table::class, $wpdb->base_prefix . 'wu_membershipmeta'));
			$this->assertFalse(get_network_option(get_main_network_id(), 'wu_membershipmeta_meta_column_version', false));
		} finally {
			$current_site = $secondary;
		}

		$this->assertSame([], $this->capture_table_queries(\WP_Ultimo\Database\Sites\Sites_Meta_Table::class, $wpdb->blogmeta));
	}

	private function create_table(string $table_class): Table {
		$table          = new $table_class();
		$this->tables[] = $table;

		$this->assertFalse(get_network_option(get_current_network_id(), $table->prefixed_name . '_meta_column_version', false));
		$table->install();
		$this->assertIsArray($table->columns());

		return $table;
	}

	private function capture_table_queries(string $table_class, string $table_name): array {
		$queries = [];
		$record  = static function ($query) use (&$queries, $table_name) {
			if (str_contains($query, $table_name) && preg_match('/^(SHOW|ALTER) /i', $query)) {
				$queries[] = $query;
			}

			return $query;
		};
		add_filter('query', $record);

		try {
			new $table_class();
		} finally {
			remove_filter('query', $record);
		}

		return $queries;
	}
}
