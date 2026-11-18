<?php
/**
 * Fatal versus non-fatal database errors during a template clone.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Helpers;

use WP_Ultimo\Helpers\Site_Duplicator;

/** Only failures that break the site fail the clone; the rest complete with warnings. */
class Site_Clone_Error_Tiers_Test extends \WP_UnitTestCase {

	/**
	 * Template with a plugin table, exposed to the copy through the table registry.
	 *
	 * @return int Template blog ID.
	 */
	private function create_template_with_plugin_table() {
		global $wpdb;

		$source = self::factory()->blog->create();
		$table  = $wpdb->get_blog_prefix($source) . 'acme_widgets';
		$wpdb->query("CREATE TABLE `$table` (id INT UNSIGNED NOT NULL PRIMARY KEY, label VARCHAR(20) NOT NULL)"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test fixture; table names cannot be bound.
		$wpdb->query("INSERT INTO `$table` (id, label) VALUES (1, 'one')"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test fixture; table names cannot be bound.

		add_filter(
			'wu_mucd_blog_tables',
			static function ($tables, $site_id) use ($source, $table) {
				if ((int) $site_id === (int) $source) {
					$tables[] = $table;
				}
				return $tables;
			},
			10,
			2
		);

		return $source;
	}

	/**
	 * Clone with every query matching $pattern replaced by one that fails.
	 *
	 * @param int    $source  Template blog ID.
	 * @param string $domain  Destination domain.
	 * @param string $pattern Regex of the query to break.
	 * @return array{0:int|\WP_Error,1:int} Clone result and destination blog ID.
	 */
	private function clone_with_failing_query($source, $domain, $pattern) {
		$break = static fn($query) => preg_match($pattern, $query) ? 'SELECT * FROM `wu_definitely_missing_table`' : $query;

		add_filter('query', $break);
		try {
			$result = Site_Duplicator::duplicate_site($source, 'Tiered clone', [
				'user_id'    => 1,
				'domain'     => $domain,
				'copy_files' => false,
				'keep_users' => false,
			]);
		} finally {
			remove_filter('query', $break);
		}

		return [$result, (int) get_blog_id_from_url($domain, '/')];
	}

	public function test_plugin_table_failure_completes_clone_with_warning() {
		$source = $this->create_template_with_plugin_table();

		[$result, $target] = $this->clone_with_failing_query($source, 'plugin-warning.example.org', '/^INSERT IGNORE `\w+_acme_widgets`/');

		$this->assertNotWPError($result);
		$this->assertSame($target, (int) $result);
		$this->assertTrue(Site_Duplicator::is_site_ready($target));
		$this->assertSame('0', (string) get_blog_status($target, 'archived'));

		$warnings = Site_Duplicator::get_clone_warnings($target);
		$this->assertArrayHasKey($GLOBALS['wpdb']->get_blog_prefix($target) . 'acme_widgets', $warnings);
		$this->assertStringContainsString('wu_definitely_missing_table', reset($warnings));
	}

	public function test_core_table_failure_fails_clone_and_names_table() {
		$source = self::factory()->blog->create();

		[$result, $target] = $this->clone_with_failing_query($source, 'core-fatal.example.org', '/^INSERT IGNORE `\w+_posts` /');

		$this->assertWPError($result);
		$this->assertSame('site_clone_database_failed', $result->get_error_code());
		$this->assertStringContainsString($GLOBALS['wpdb']->get_blog_prefix($target) . 'posts', $result->get_error_message());
		$this->assertStringNotContainsString('wu_definitely_missing_table', $result->get_error_message(), 'MySQL details stay in the log.');
		$this->assertTrue(Site_Duplicator::is_clone_failed($target));
		$this->assertSame('1', (string) get_blog_status($target, 'archived'));
	}

	public function test_filter_can_make_plugin_table_required() {
		$source   = $this->create_template_with_plugin_table();
		$required = static fn($fatal, $table, $base) => 'acme_widgets' === $base ? true : $fatal;

		add_filter('wu_mucd_copy_error_is_fatal', $required, 10, 3);
		try {
			[$result, $target] = $this->clone_with_failing_query($source, 'plugin-required.example.org', '/^INSERT IGNORE `\w+_acme_widgets`/');
		} finally {
			remove_filter('wu_mucd_copy_error_is_fatal', $required, 10);
		}

		$this->assertWPError($result);
		$this->assertTrue(Site_Duplicator::is_clone_failed($target));
	}

	public function test_url_replacement_failure_is_a_warning() {
		$source = self::factory()->blog->create();
		switch_to_blog($source);
		self::factory()->post->create(['post_content' => 'Visit ' . home_url('/about/')]);
		restore_current_blog();

		[$result, $target] = $this->clone_with_failing_query($source, 'update-warning.example.org', '/^SELECT `ID`, `post_content` FROM `\w+_posts` WHERE/');

		$this->assertNotWPError($result);
		$this->assertTrue(Site_Duplicator::is_site_ready($target));
		$this->assertArrayHasKey($GLOBALS['wpdb']->get_blog_prefix($target) . 'posts', Site_Duplicator::get_clone_warnings($target));
	}

	public function test_post_copy_url_rewrite_failure_is_a_warning_not_a_silent_fatal() {
		$target = self::factory()->blog->create();
		$table  = $GLOBALS['wpdb']->get_blog_prefix($target) . 'posts';
		switch_to_blog($target);
		self::factory()->post->create(['post_content' => 'https://old.example.org/page']);
		restore_current_blog();

		$fatal_before = \MUCD_Data::get_copy_error();
		$break        = static fn($query) => preg_match('/^SELECT `ID`, `post_content` FROM `' . preg_quote($table, '/') . '` WHERE/', $query) ? 'SELECT * FROM `wu_definitely_missing_table`' : $query;
		$bases        = [];
		$spy          = static function ($fatal, $table, $base) use (&$bases) {
			$bases[] = $base;
			return $fatal;
		};

		add_filter('query', $break);
		add_filter('wu_mucd_copy_error_is_fatal', $spy, 10, 3);
		try {
			\MUCD_Data::update_as_warning($table, ['post_content'], 'https://old.example.org', 'https://new.example.org', 'posts');
		} finally {
			remove_filter('query', $break);
			remove_filter('wu_mucd_copy_error_is_fatal', $spy, 10);
		}

		$this->assertSame($fatal_before, \MUCD_Data::get_copy_error(), 'A post-copy rewrite failure must not become a fatal error.');
		$this->assertArrayHasKey($table, \MUCD_Data::get_copy_warnings());
		$this->assertSame(['posts'], array_unique($bases), 'The fatal-error filter must receive the table base name.');
	}

	public function test_filter_can_make_post_copy_rewrite_failure_fail_the_clone() {
		$source = self::factory()->blog->create();
		$prefix = $GLOBALS['wpdb']->get_blog_prefix($source);
		$armed  = false;

		// Only the post-copy rewrite DESCRIBEs destination tables; fail its postmeta queries from then on.
		$break    = static function ($query) use (&$armed, $prefix) {
			if (preg_match('/^DESCRIBE `(\w+)_postmeta`$/', $query, $match) && $match[1] . '_' !== $prefix) {
				$armed = true;
			}
			return $armed && preg_match('/^SELECT `meta_id`, `meta_value` FROM `\w+_postmeta` WHERE/', $query) ? 'SELECT * FROM `wu_definitely_missing_table`' : $query;
		};
		$required = static fn($fatal, $table, $base, $phase) => ('update' === $phase && 'postmeta' === $base) ? true : $fatal;

		add_filter('query', $break);
		add_filter('wu_mucd_copy_error_is_fatal', $required, 10, 4);
		try {
			$result = Site_Duplicator::duplicate_site($source, 'Rewrite required', [
				'user_id'    => 1,
				'domain'     => 'rewrite-required.example.org',
				'copy_files' => false,
				'keep_users' => false,
			]);
		} finally {
			remove_filter('query', $break);
			remove_filter('wu_mucd_copy_error_is_fatal', $required, 10);
		}

		$target = (int) get_blog_id_from_url('rewrite-required.example.org', '/');
		$this->assertTrue($armed, 'The post-copy rewrite must have run.');
		$this->assertWPError($result);
		$this->assertStringContainsString($GLOBALS['wpdb']->get_blog_prefix($target) . 'postmeta', $result->get_error_message());
		$this->assertTrue(Site_Duplicator::is_clone_failed($target));
	}

	public function test_clean_clone_records_no_warnings() {
		$source = self::factory()->blog->create();

		[$result, $target] = $this->clone_with_failing_query($source, 'clean-clone.example.org', '/^NEVER MATCHES$/');

		$this->assertNotWPError($result);
		$this->assertSame([], Site_Duplicator::get_clone_warnings($target));
	}
}
