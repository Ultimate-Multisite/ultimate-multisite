<?php
/**
 * Unit tests for Orphaned_Tables_Manager.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

/**
 * Unit tests for Orphaned_Tables_Manager.
 */
class Orphaned_Tables_Manager_Test extends \WP_UnitTestCase {

	/**
	 * Get the singleton instance.
	 *
	 * @return Orphaned_Tables_Manager
	 */
	protected function get_instance(): Orphaned_Tables_Manager {

		return Orphaned_Tables_Manager::get_instance();
	}

	/**
	 * Create a wp_blogs row and options table for cleanup safety tests.
	 *
	 * @param int $network_id Network ID to store in the wp_blogs row.
	 * @return array{0:int,1:string}
	 */
	protected function create_blog_row_with_options_table(int $network_id = 999): array {

		global $wpdb;

		$blog_id = ((int) $wpdb->get_var("SELECT MAX(blog_id) FROM {$wpdb->blogs}")) + 1000; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table   = $wpdb->base_prefix . $blog_id . '_options';

		$inserted = $wpdb->insert(
			$wpdb->blogs,
			[
				'blog_id'      => $blog_id,
				'site_id'      => $network_id,
				'domain'       => "orphaned-tables-{$blog_id}.example.org",
				'path'         => '/',
				'registered'   => current_time('mysql', true),
				'last_updated' => current_time('mysql', true),
				'public'       => 1,
				'archived'     => 0,
				'mature'       => 0,
				'spam'         => 0,
				'deleted'      => 0,
				'lang_id'      => 0,
			],
			[
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
			]
		);

		$this->assertNotFalse($inserted, 'Failed to insert the test blog row.');

		$created = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"CREATE TABLE {$table} (
				option_id bigint(20) unsigned NOT NULL auto_increment,
				option_name varchar(191) NOT NULL default '',
				option_value longtext NOT NULL,
				autoload varchar(20) NOT NULL default 'yes',
				PRIMARY KEY  (option_id),
				UNIQUE KEY option_name (option_name)
			) {$wpdb->get_charset_collate()}"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if (false === $created) {
			$wpdb->delete($wpdb->blogs, ['blog_id' => $blog_id], ['%d']);
			clean_blog_cache($blog_id);

			$this->fail('Failed to create the test options table.');
		}

		clean_blog_cache($blog_id);

		return [$blog_id, $table];
	}

	/**
	 * Remove test wp_blogs row and options table.
	 *
	 * @param int    $blog_id Blog ID.
	 * @param string $table Options table name.
	 */
	protected function remove_blog_row_with_options_table(int $blog_id, string $table): void {

		global $wpdb;

		$wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete($wpdb->blogs, ['blog_id' => $blog_id], ['%d']);
		clean_blog_cache($blog_id);
	}

	/**
	 * Test helper removes the wp_blogs row when creating the options table fails.
	 */
	public function test_create_blog_row_with_options_table_cleans_blog_row_on_table_creation_failure(): void {

		global $wpdb;

		$blog_id = ((int) $wpdb->get_var("SELECT MAX(blog_id) FROM {$wpdb->blogs}")) + 1000; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table   = $wpdb->base_prefix . $blog_id . '_options';

		$wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$created = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"CREATE TABLE {$table} (
				option_id bigint(20) unsigned NOT NULL auto_increment,
				option_name varchar(191) NOT NULL default '',
				option_value longtext NOT NULL,
				autoload varchar(20) NOT NULL default 'yes',
				PRIMARY KEY  (option_id),
				UNIQUE KEY option_name (option_name)
			) {$wpdb->get_charset_collate()}"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertNotFalse($created, 'Failed to create the conflicting test options table.');

		$caught_failure = false;

		try {
			$this->create_blog_row_with_options_table();
		} catch (\PHPUnit\Framework\AssertionFailedError $exception) {
			$caught_failure = true;

			$this->assertStringContainsString('Failed to create the test options table.', $exception->getMessage());
			$this->assertNull($wpdb->get_var($wpdb->prepare("SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id = %d", $blog_id))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} finally {
			$wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->delete($wpdb->blogs, ['blog_id' => $blog_id], ['%d']);
			clean_blog_cache($blog_id);
		}

		$this->assertTrue($caught_failure, 'Expected the helper to fail when the options table already exists.');
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf(Orphaned_Tables_Manager::class, $this->get_instance());
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(
			Orphaned_Tables_Manager::get_instance(),
			Orphaned_Tables_Manager::get_instance()
		);
	}

	/**
	 * Test init registers hooks when WP version is 6.2+.
	 */
	public function test_init_registers_hooks(): void {

		global $wp_version;

		// Current WP should be >= 6.2
		if (version_compare($wp_version, '6.2', '<')) {
			$this->markTestSkipped('Requires WordPress 6.2+');
		}

		$instance = $this->get_instance();

		$instance->init();

		$this->assertIsInt(has_action('plugins_loaded', [$instance, 'register_forms']));
		$this->assertIsInt(has_action('wu_settings_other', [$instance, 'register_settings_field']));
	}

	/**
	 * Test find_orphaned_tables returns array.
	 */
	public function test_find_orphaned_tables_returns_array(): void {

		$result = $this->get_instance()->find_orphaned_tables();

		$this->assertIsArray($result);
	}

	/**
	 * Test find_orphaned_tables does not include active site tables.
	 */
	public function test_find_orphaned_tables_excludes_active_sites(): void {

		$orphaned = $this->get_instance()->find_orphaned_tables();

		global $wpdb;

		// Get all blog IDs directly from wp_blogs, across every network.
		$site_ids = array_map('intval', $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}") ?: []); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$checked = 0;

		foreach ( $orphaned as $table ) {
			$pattern = '/^' . preg_quote($wpdb->base_prefix, '/') . '([0-9]+)_/';

			if ( preg_match($pattern, $table, $matches) ) {
				$site_id = (int) $matches[1];

				// Orphaned tables should NOT belong to active sites
				$this->assertNotContains($site_id, $site_ids, "Table {$table} belongs to active site {$site_id}");
				++$checked;
			}
		}

		// If no orphaned tables exist, the test passes trivially — mark it as such
		if ( 0 === $checked ) {
			$this->assertTrue(true, 'No orphaned tables found — nothing to check.');
		}
	}

	/**
	 * Test find_orphaned_tables ignores filtered site query results.
	 */
	public function test_find_orphaned_tables_ignores_filtered_site_queries(): void {

		[$blog_id, $table] = $this->create_blog_row_with_options_table();

		$filter = static function ($site_data, $query) {
			if ('ids' !== $query->query_vars['fields']) {
				return $site_data;
			}

			return [1];
		};

		add_filter('sites_pre_query', $filter, 10, 2);

		try {
			$orphaned = $this->get_instance()->find_orphaned_tables();

			$this->assertNotContains($table, $orphaned, 'Tables with a wp_blogs row must not be treated as orphaned when get_sites() is filtered.');
		} finally {
			remove_filter('sites_pre_query', $filter, 10);
			$this->remove_blog_row_with_options_table($blog_id, $table);
		}
	}

	/**
	 * Test delete_orphaned_tables returns int.
	 */
	public function test_delete_orphaned_tables_returns_int(): void {

		$result = $this->get_instance()->delete_orphaned_tables([]);

		$this->assertSame(0, $result);
	}

	/**
	 * Test delete_orphaned_tables rejects non-matching table names.
	 */
	public function test_delete_orphaned_tables_rejects_invalid_names(): void {

		$result = $this->get_instance()->delete_orphaned_tables([
			'some_random_table',
			'another_table',
		]);

		// Should not delete tables that don't match the multisite pattern
		$this->assertSame(0, $result);
	}

	/**
	 * Test delete_orphaned_tables protects tables with a wp_blogs row.
	 */
	public function test_delete_orphaned_tables_skips_tables_with_blog_rows(): void {

		global $wpdb;

		[$blog_id, $table] = $this->create_blog_row_with_options_table();

		try {
			$result = $this->get_instance()->delete_orphaned_tables([$table]);

			$this->assertSame(0, $result);
			$this->assertSame($table, $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)));
		} finally {
			$this->remove_blog_row_with_options_table($blog_id, $table);
		}
	}

	/**
	 * Test cleanup fails closed when wp_blogs cannot be read.
	 */
	public function test_cleanup_fails_closed_when_blog_lookup_fails(): void {

		global $wpdb;

		[$blog_id, $table] = $this->create_blog_row_with_options_table();
		$orphaned_table    = $wpdb->base_prefix . ( $blog_id + 1 ) . '_options';
		$created           = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"CREATE TABLE {$orphaned_table} (
				option_id bigint(20) unsigned NOT NULL auto_increment,
				option_name varchar(191) NOT NULL default '',
				option_value longtext NOT NULL,
				autoload varchar(20) NOT NULL default 'yes',
				PRIMARY KEY  (option_id),
				UNIQUE KEY option_name (option_name)
			) {$wpdb->get_charset_collate()}"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertNotFalse($created, 'Failed to create the orphaned-looking test table.');

		$blogs_table       = $wpdb->blogs;
		$wpdb->blogs       = $wpdb->base_prefix . 'missing_blogs_for_cleanup_test';

		try {
			$this->assertSame([], $this->get_instance()->find_orphaned_tables());
			$this->assertSame(0, $this->get_instance()->delete_orphaned_tables([$table, $orphaned_table]));
			$this->assertSame($table, $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)));
			$this->assertSame($orphaned_table, $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $orphaned_table)));
		} finally {
			$wpdb->blogs = $blogs_table;
			$wpdb->query("DROP TABLE IF EXISTS {$orphaned_table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->remove_blog_row_with_options_table($blog_id, $table);
		}
	}

	/**
	 * Test register_forms registers the orphaned_tables_delete form.
	 */
	public function test_register_forms(): void {

		$instance = $this->get_instance();

		$instance->register_forms();

		$form_manager = \WP_Ultimo\Managers\Form_Manager::get_instance();

		$this->assertTrue($form_manager->is_form_registered('orphaned_tables_delete'));
	}
}
