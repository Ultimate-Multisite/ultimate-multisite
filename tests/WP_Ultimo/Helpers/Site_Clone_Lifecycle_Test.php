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

	/**
	 * Create a destination whose clone failed, as left by a failed initializer.
	 *
	 * @param string $domain Destination domain.
	 * @return array{0:int,1:int} Source and destination blog IDs.
	 */
	private function create_failed_clone($domain) {
		$source      = self::factory()->blog->create();
		$initializer = static fn() => new \WP_Error('initialization_failed', 'Controlled test failure');
		add_filter('wu_initialize_cloned_site', $initializer);
		try {
			$result = Site_Duplicator::duplicate_site($source, 'Failed customer', [
				'user_id'    => 1,
				'domain'     => $domain,
				'copy_files' => false,
				'keep_users' => false,
			]);
		} finally {
			remove_filter('wu_initialize_cloned_site', $initializer);
		}
		$this->assertWPError($result);
		$target = get_blog_id_from_url($domain, '/');
		$this->assertGreaterThan(0, $target);
		$this->assertTrue(Site_Duplicator::is_clone_failed($target));

		return [$source, $target];
	}

	public function test_saving_site_with_failed_clone_does_not_recopy_template() {
		[, $target] = $this->create_failed_clone('save-failed-clone.example.org');

		switch_to_blog($target);
		$post_id = wp_insert_post(
			[
				'post_title'  => 'Built after the failure',
				'post_status' => 'publish',
			]
		);
		restore_current_blog();
		$this->assertGreaterThan(0, $post_id);

		$clones = 0;
		$count  = static function () use (&$clones) {
			++$clones;
		};
		add_action('wu_before_site_clone', $count);
		try {
			$site = wu_get_site($target);
			$this->assertNotEmpty($site->get_template_id());
			$saved = $site->save();
		} finally {
			remove_action('wu_before_site_clone', $count);
		}

		$this->assertNotWPError($saved);
		$this->assertSame(0, $clones, 'A model save must never re-run the template clone.');
		switch_to_blog($target);
		$still_there = get_post($post_id);
		restore_current_blog();
		$this->assertNotNull($still_there);
		$this->assertSame('Built after the failure', $still_there->post_title);
		$this->assertTrue(Site_Duplicator::is_clone_failed($target));
		$this->assertSame('1', (string) get_blog_status($target, 'archived'));
	}

	public function test_retry_failed_clone_recopies_template_and_clears_archived() {
		[, $target] = $this->create_failed_clone('retry-failed-clone.example.org');

		$site = wu_get_site($target);
		$this->assertTrue($site->retry_failed_clone());

		$this->assertTrue(Site_Duplicator::is_site_ready($target));
		$this->assertSame('0', (string) get_blog_status($target, 'archived'));
	}

	public function test_retry_failed_clone_keeps_site_type_and_ownership() {
		[$source, $target] = $this->create_failed_clone('retry-ownership.example.org');
		update_site_meta($source, 'wu_type', 'site_template');
		update_site_meta($target, 'wu_type', 'customer_owned');
		update_site_meta($target, 'wu_customer_id', 77);
		update_site_meta($target, 'wu_membership_id', 88);

		// A retry that fails again must not leave the template's ownership behind either.
		$initializer = static fn() => new \WP_Error('initialization_failed', 'Controlled test failure');
		add_filter('wu_initialize_cloned_site', $initializer);
		try {
			$failed = wu_get_site($target)->retry_failed_clone();
		} finally {
			remove_filter('wu_initialize_cloned_site', $initializer);
		}
		$this->assertWPError($failed);
		$this->assertSame('customer_owned', get_site_meta($target, 'wu_type', true));
		$this->assertSame(77, (int) get_site_meta($target, 'wu_customer_id', true));
		$this->assertSame(88, (int) get_site_meta($target, 'wu_membership_id', true));

		$this->assertTrue(wu_get_site($target)->retry_failed_clone());
		$site = wu_get_site($target);
		$this->assertSame('customer_owned', $site->get_type());
		$this->assertSame(77, (int) $site->get_customer_id());
		$this->assertSame(88, (int) $site->get_membership_id());
		$this->assertSame($source, (int) $site->get_template_id());
	}

	public function test_retry_failed_clone_does_not_inherit_template_associations() {
		[$source, $target] = $this->create_failed_clone('retry-no-owner.example.org');
		update_site_meta($source, 'wu_customer_id', 55);
		update_site_meta($source, 'wu_membership_id', 66);
		delete_site_meta($target, 'wu_customer_id');
		delete_site_meta($target, 'wu_membership_id');

		$initializer = static fn() => new \WP_Error('initialization_failed', 'Controlled test failure');
		add_filter('wu_initialize_cloned_site', $initializer);
		try {
			$this->assertWPError(wu_get_site($target)->retry_failed_clone());
		} finally {
			remove_filter('wu_initialize_cloned_site', $initializer);
		}
		$this->assertSame('', get_site_meta($target, 'wu_customer_id', true));
		$this->assertSame('', get_site_meta($target, 'wu_membership_id', true));

		$this->assertTrue(wu_get_site($target)->retry_failed_clone());
		$this->assertSame('', get_site_meta($target, 'wu_customer_id', true));
		$this->assertSame('', get_site_meta($target, 'wu_membership_id', true));
	}

	public function test_retry_failed_clone_refuses_sites_that_did_not_fail() {
		$target = self::factory()->blog->create();
		update_site_meta($target, Site_Duplicator::CLONE_STATUS_META, 'complete');

		$result = wu_get_site($target)->retry_failed_clone();

		$this->assertWPError($result);
		$this->assertSame('site_clone_not_failed', $result->get_error_code());
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
