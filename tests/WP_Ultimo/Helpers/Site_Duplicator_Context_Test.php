<?php
/**
 * Regression tests for site duplication blog-context bookkeeping.
 *
 * Covers GH#1163: customers with site templates were not receiving
 * new-site / welcome emails because MUCD_Files::copy_files() left the
 * switch_to_blog() stack with the template site on top, so the post-publish
 * email pipeline ran on the template's blog options and per-site SMTP
 * plugins instead of the network's.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Helpers;

use WP_Ultimo\Helpers\Site_Duplicator;
use WP_UnitTestCase;

/**
 * Asserts that the site-duplication code path leaves the WordPress
 * switch_to_blog() stack balanced and restores the caller's blog context.
 */
class Site_Duplicator_Context_Test extends WP_UnitTestCase {

	/**
	 * Template (source) blog ID.
	 *
	 * @var int
	 */
	private $template_site_id;

	/**
	 * Set up: create a template site only. No wu_create_customer() — the
	 * heavier setUp in Site_Duplicator_Test fails on user-id collisions in
	 * some local test environments and we don't need a customer here.
	 */
	public function setUp(): void {
		parent::setUp();

		if (! is_multisite()) {
			$this->markTestSkipped('Site duplication tests require multisite');
		}

		$this->template_site_id = self::factory()->blog->create(
			[
				'domain' => 'context-template.example.com',
				'path'   => '/',
				'title'  => 'Context Template',
			]
		);
	}

	/**
	 * Clean up the template site.
	 */
	public function tearDown(): void {
		if ($this->template_site_id) {
			wpmu_delete_blog($this->template_site_id, true);
		}

		parent::tearDown();
	}

	/**
	 * MUCD_Files::copy_files() must keep the switch_to_blog() stack balanced —
	 * exactly one restore_current_blog() per switch_to_blog().
	 *
	 * The pre-fix implementation pushed two switch_to_blog() frames (source
	 * then target) but only popped one, leaking the source site's context
	 * back to the caller. This test fails on that buggy implementation.
	 */
	public function test_copy_files_does_not_leak_blog_context() {
		$source_id = self::factory()->blog->create(
			[
				'domain' => 'copy-files-source.example.com',
				'path'   => '/',
				'title'  => 'Copy Files Source',
			]
		);

		$target_id = self::factory()->blog->create(
			[
				'domain' => 'copy-files-target.example.com',
				'path'   => '/',
				'title'  => 'Copy Files Target',
			]
		);

		// Defensive: unwind any switch state factory()->blog->create may leak.
		while (function_exists('ms_is_switched') && ms_is_switched()) {
			restore_current_blog();
		}

		$caller_blog_id = get_current_blog_id();
		$this->assertSame(get_main_site_id(), $caller_blog_id, 'Test prerequisite: caller should be on the main site.');

		// Skip the actual filesystem recursion — we're testing the
		// switch_to_blog() bookkeeping at the start of copy_files(), not
		// the file copy itself.
		add_filter('mucd_copy_dirs', '__return_empty_array', 99);

		\MUCD_Files::copy_files($source_id, $target_id);

		remove_filter('mucd_copy_dirs', '__return_empty_array', 99);

		// Build a diagnostic message in case of failure: shows the residual
		// switch stack and current blog so it's obvious the unbalanced
		// switch_to_blog() / restore_current_blog() pairing has regressed.
		$switched = isset($GLOBALS['_wp_switched_stack']) ? $GLOBALS['_wp_switched_stack'] : [];
		$msg      = sprintf(
			'After copy_files(): current=%d, stack=%s, source=%d, target=%d, caller=%d',
			get_current_blog_id(),
			wp_json_encode($switched),
			$source_id,
			$target_id,
			$caller_blog_id
		);

		$this->assertSame(
			$caller_blog_id,
			get_current_blog_id(),
			'copy_files() must restore the caller blog id. ' . $msg
		);

		$this->assertFalse(
			ms_is_switched(),
			'copy_files() must leave the switch_to_blog() stack empty. ' . $msg
		);

		wpmu_delete_blog($source_id, true);
		wpmu_delete_blog($target_id, true);
	}

	/**
	 * Site_Duplicator::duplicate_site() must restore the caller's blog
	 * context after the full duplication pipeline runs.
	 *
	 * This is the integration-level guard against the email-send regression
	 * reported in GH#1163.
	 */
	public function test_duplicate_site_restores_blog_context() {
		$caller_blog_id = get_current_blog_id();

		$result = Site_Duplicator::duplicate_site(
			$this->template_site_id,
			'Context Restored Site',
			[
				'domain' => 'context-restored.example.com',
				'path'   => '/',
				'title'  => 'Context Restored Site',
			]
		);

		$this->assertIsInt($result, 'duplicate_site() should return the new site id.');
		$this->assertGreaterThan(0, $result);

		$this->assertSame(
			$caller_blog_id,
			get_current_blog_id(),
			'duplicate_site() must leave the caller on the same blog context it started on.'
		);

		$this->assertFalse(
			ms_is_switched(),
			'duplicate_site() must fully unwind the switch_to_blog() stack.'
		);

		wpmu_delete_blog($result, true);
	}
}
