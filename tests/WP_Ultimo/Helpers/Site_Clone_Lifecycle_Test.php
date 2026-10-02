<?php
/**
 * Native clone initialization and readiness contract.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Helpers;

use WP_Ultimo\Helpers\Site_Duplicator;

/** Verify handoff waits for the authoritative copy and error-aware callbacks. */
class Site_Clone_Lifecycle_Test extends \WP_UnitTestCase {

	public function test_initializer_runs_once_in_destination_and_preserves_template_configuration() {
		$source = self::factory()->blog->create();
		update_blog_option($source, 'wu_template_clone_configuration', ['public' => false]);
		update_blog_option($source, 'template_agent_prompt', 'Administrator-owned template prompt');
		$calls       = 0;
		$initializer = function ($result, $payload) use (&$calls, $source) {
			++$calls;
			$this->assertSame($source, $payload['from_site_id']);
			$this->assertSame($payload['site_id'], get_current_blog_id());
			$this->assertSame('Administrator-owned template prompt', get_option('template_agent_prompt'));
			$this->assertFalse(Site_Duplicator::is_site_ready($payload['site_id']));
			return $result;
		};
		add_filter('wu_initialize_cloned_site', $initializer, 10, 2);
		try {
			$target = Site_Duplicator::duplicate_site($source, 'New customer', [
				'user_id'    => 1,
				'domain'     => 'clone-lifecycle.example.org',
				'copy_files' => false,
				'keep_users' => false,
			]);
		} finally {
			remove_filter('wu_initialize_cloned_site', $initializer, 10);
		}
		$this->assertNotWPError($target);
		$this->assertSame(1, $calls);
		$this->assertTrue(Site_Duplicator::is_site_ready($target));
		$this->assertSame('0', (string) get_blog_option($target, 'blog_public'));
	}

	public function test_failed_initializer_keeps_destination_quarantined() {
		$source      = self::factory()->blog->create();
		$initializer = static fn() => new \WP_Error('initialization_failed', 'Controlled test failure');
		add_filter('wu_initialize_cloned_site', $initializer);
		try {
			$result = Site_Duplicator::duplicate_site($source, 'Blocked customer', [
				'user_id'    => 1,
				'domain'     => 'failed-clone.example.org',
				'copy_files' => false,
				'keep_users' => false,
			]);
		} finally {
			remove_filter('wu_initialize_cloned_site', $initializer);
		}
		$this->assertWPError($result);
		$target = get_blog_id_from_url('failed-clone.example.org', '/');
		$this->assertGreaterThan(0, $target);
		$this->assertSame('failed', get_site_meta($target, Site_Duplicator::CLONE_STATUS_META, true));
		$this->assertFalse(Site_Duplicator::is_site_ready($target));
		$this->assertSame('1', (string) get_blog_status($target, 'archived'));
		$retried = Site_Duplicator::duplicate_site($source, 'Retry customer', [
			'to_site_id' => $target,
			'user_id'    => 1,
			'copy_files' => false,
			'keep_users' => false,
		]);
		$this->assertNotWPError($retried);
		$this->assertTrue(Site_Duplicator::is_site_ready($target));
		$this->assertSame('0', (string) get_blog_status($target, 'archived'));
	}

	public function test_source_completion_and_tenant_identity_are_not_inherited() {
		$source = self::factory()->blog->create();
		$target = self::factory()->blog->create();
		update_site_meta($source, 'wu_clone_status', 'complete');
		update_site_meta($source, 'wu_mt_sovereign_shadow_clone_synced', 123);
		update_site_meta($source, 'template_configuration', ['nested' => ['enabled' => false]]);
		update_site_meta($target, 'wu_clone_status', 'copying');
		update_site_meta($target, 'wu_mt_tenant_identity', 'destination');
		\MUCD_Data::db_copy_blog_meta($source, $target);
		$this->assertSame('copying', get_site_meta($target, 'wu_clone_status', true));
		$this->assertSame('destination', get_site_meta($target, 'wu_mt_tenant_identity', true));
		$this->assertSame('', get_site_meta($target, 'wu_mt_sovereign_shadow_clone_synced', true));
		$this->assertSame(['nested' => ['enabled' => false]], get_site_meta($target, 'template_configuration', true));
	}

	public function test_storage_provider_can_defer_completed_clone_readiness() {
		$target = self::factory()->blog->create();
		update_site_meta($target, Site_Duplicator::CLONE_STATUS_META, 'complete');
		add_filter('wu_site_clone_ready', '__return_false');
		$this->assertFalse(Site_Duplicator::is_site_ready($target));
		remove_filter('wu_site_clone_ready', '__return_false');
		$this->assertTrue(Site_Duplicator::is_site_ready($target));
	}
}
