<?php
/**
 * Tests for the main-site promotion service.
 *
 * @package WP_Ultimo\Site_Exporter
 * @subpackage Tests
 */

namespace WP_Ultimo\Site_Exporter;

use WP_UnitTestCase;

/**
 * Test class for Main_Site_Promoter.
 *
 * @package WP_Ultimo\Site_Exporter
 * @subpackage Tests
 * @since 2.5.1
 */
class Main_Site_Promoter_Test extends WP_UnitTestCase {

	/**
	 * Source blog ID.
	 *
	 * @var int
	 */
	private int $source_blog_id = 0;

	/**
	 * Set up a source subsite.
	 */
	public function set_up(): void {

		parent::set_up();

		if (! is_multisite()) {
			$this->markTestSkipped('Main-site promotion requires multisite.');
		}

		$this->source_blog_id = self::factory()->blog->create(
			[
				'domain' => 'promote-source.example.com',
				'path'   => '/',
				'title'  => 'Promote Source',
			]
		);
	}

	/**
	 * Clean up the source subsite.
	 */
	public function tear_down(): void {

		if ($this->source_blog_id) {
			wpmu_delete_blog($this->source_blog_id, true);
		}

		parent::tear_down();
	}

	/**
	 * Promoting the current main site is rejected.
	 */
	public function test_promote_rejects_current_main_site(): void {

		$result = Main_Site_Promoter::get_instance()->promote(wu_get_main_site_id());

		$this->assertWPError($result);
		$this->assertSame('main_site_promotion_source_is_main', $result->get_error_code());
	}

	/**
	 * Missing source IDs return a clear validation error.
	 */
	public function test_promote_rejects_missing_source_site(): void {

		$result = Main_Site_Promoter::get_instance()->promote(999999);

		$this->assertWPError($result);
		$this->assertSame('main_site_promotion_source_not_found', $result->get_error_code());
	}

	/**
	 * Backup exports are attempted before the destructive copy step.
	 */
	public function test_promote_exports_main_and_source_before_copy(): void {

		$main_site_id = wu_get_main_site_id();
		$events       = [];

		$export_filter = function ($export, $site_id) use (&$events) {
			$events[] = 'export:' . $site_id;

			return 'backup-' . $site_id . '.zip';
		};

		$override_filter = function ($result, $source_blog_id, $target_blog_id) use (&$events) {
			$events[] = 'copy:' . $source_blog_id . ':' . $target_blog_id;

			return true;
		};

		add_filter('wu_main_site_promoter_export_site', $export_filter, 10, 2);
		add_filter('wu_main_site_promoter_override_site', $override_filter, 10, 3);

		$result = Main_Site_Promoter::get_instance()->promote($this->source_blog_id);

		remove_filter('wu_main_site_promoter_export_site', $export_filter, 10);
		remove_filter('wu_main_site_promoter_override_site', $override_filter, 10);

		$this->assertIsArray($result);
		$this->assertSame(
			[
				'export:' . $main_site_id,
				'export:' . $this->source_blog_id,
				'copy:' . $this->source_blog_id . ':' . $main_site_id,
			],
			$events
		);
		$this->assertSame('backup-' . $main_site_id . '.zip', $result['backup_exports']['main_site']);
		$this->assertSame('backup-' . $this->source_blog_id . '.zip', $result['backup_exports']['source']);
		$this->assertNotFalse(get_site($this->source_blog_id), 'Source subsite must remain intact.');
	}

	/**
	 * Backup failures stop before the copy step starts.
	 */
	public function test_promote_stops_when_backup_fails(): void {

		$copy_called = false;

		$export_filter = function () {
			return new \WP_Error('backup_failed', 'Backup failed.');
		};

		$override_filter = function () use (&$copy_called) {
			$copy_called = true;

			return true;
		};

		add_filter('wu_main_site_promoter_export_site', $export_filter);
		add_filter('wu_main_site_promoter_override_site', $override_filter);

		$result = Main_Site_Promoter::get_instance()->promote($this->source_blog_id);

		remove_filter('wu_main_site_promoter_export_site', $export_filter);
		remove_filter('wu_main_site_promoter_override_site', $override_filter);

		$this->assertWPError($result);
		$this->assertSame('backup_failed', $result->get_error_code());
		$this->assertFalse($copy_called, 'Copy must not run after a backup failure.');
	}

	/**
	 * Main URL identity is restored after the copy step rewrites options.
	 */
	public function test_promote_restores_main_url_identity(): void {

		$main_site_id = wu_get_main_site_id();
		$main_home    = get_blog_option($main_site_id, 'home');
		$main_siteurl = get_blog_option($main_site_id, 'siteurl');
		$source_url   = get_site_url($this->source_blog_id);

		$export_filter = function ($export, $site_id) {
			return 'backup-' . $site_id . '.zip';
		};

		$override_filter = function () use ($main_site_id, $source_url) {
			update_blog_option($main_site_id, 'home', $source_url);
			update_blog_option($main_site_id, 'siteurl', $source_url);

			return true;
		};

		add_filter('wu_main_site_promoter_export_site', $export_filter, 10, 2);
		add_filter('wu_main_site_promoter_override_site', $override_filter);

		$result = Main_Site_Promoter::get_instance()->promote($this->source_blog_id);

		remove_filter('wu_main_site_promoter_export_site', $export_filter, 10);
		remove_filter('wu_main_site_promoter_override_site', $override_filter);

		$this->assertIsArray($result);
		$this->assertSame($main_home, get_blog_option($main_site_id, 'home'));
		$this->assertSame($main_siteurl, get_blog_option($main_site_id, 'siteurl'));
	}

	/**
	 * Main title identity is restored when requested after the copy step rewrites it.
	 */
	public function test_promote_restores_original_main_title_when_preserved(): void {

		$main_site_id        = wu_get_main_site_id();
		$original_main_title = get_blog_option($main_site_id, 'blogname');
		$main_title          = 'Original Main Title';
		$source_title        = 'Copied Source Title';

		update_blog_option($main_site_id, 'blogname', $main_title);
		update_blog_option($this->source_blog_id, 'blogname', $source_title);

		$override_filter = function () use ($main_site_id, $source_title) {
			update_blog_option($main_site_id, 'blogname', $source_title);

			return true;
		};

		add_filter('wu_main_site_promoter_override_site', $override_filter);

		try {
			$result = Main_Site_Promoter::get_instance()->promote(
				$this->source_blog_id,
				[
					'backup'              => false,
					'preserve_main_title' => true,
				]
			);

			$this->assertIsArray($result);
			$this->assertSame($main_title, get_blog_option($main_site_id, 'blogname'));
		} finally {
			remove_filter('wu_main_site_promoter_override_site', $override_filter);
			update_blog_option($main_site_id, 'blogname', $original_main_title);
		}
	}

	/**
	 * Source title is applied when the main title should not be preserved.
	 */
	public function test_promote_uses_source_title_when_main_title_not_preserved(): void {

		$main_site_id        = wu_get_main_site_id();
		$original_main_title = get_blog_option($main_site_id, 'blogname');
		$source_title        = 'Copied Source Title';

		update_blog_option($main_site_id, 'blogname', 'Original Main Title');
		update_blog_option($this->source_blog_id, 'blogname', $source_title);

		$override_filter = function () use ($main_site_id) {
			update_blog_option($main_site_id, 'blogname', 'Temporary Copied Title');

			return true;
		};

		add_filter('wu_main_site_promoter_override_site', $override_filter);

		try {
			$result = Main_Site_Promoter::get_instance()->promote(
				$this->source_blog_id,
				[
					'backup'              => false,
					'preserve_main_title' => false,
				]
			);

			$this->assertIsArray($result);
			$this->assertSame($source_title, get_blog_option($main_site_id, 'blogname'));
		} finally {
			remove_filter('wu_main_site_promoter_override_site', $override_filter);
			update_blog_option($main_site_id, 'blogname', $original_main_title);
		}
	}

	/**
	 * The caller's blog context is restored after promotion.
	 */
	public function test_promote_restores_caller_blog_context(): void {

		$export_filter = function ($export, $site_id) {
			return 'backup-' . $site_id . '.zip';
		};

		$override_filter = function () {
			switch_to_blog(wu_get_main_site_id());

			return true;
		};

		add_filter('wu_main_site_promoter_export_site', $export_filter, 10, 2);
		add_filter('wu_main_site_promoter_override_site', $override_filter);

		switch_to_blog($this->source_blog_id);

		$result = Main_Site_Promoter::get_instance()->promote($this->source_blog_id);

		$this->assertIsArray($result);
		$this->assertSame($this->source_blog_id, get_current_blog_id());
		$this->assertTrue(ms_is_switched());

		restore_current_blog();

		remove_filter('wu_main_site_promoter_export_site', $export_filter, 10);
		remove_filter('wu_main_site_promoter_override_site', $override_filter);
	}
}
