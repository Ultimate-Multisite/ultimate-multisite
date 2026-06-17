<?php
/**
 * Unit tests for Post_Signup_Activity_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Post_Signup_Activity_Manager;

/**
 * Tests for the post-signup activity tracking manager (issue #399).
 */
class Post_Signup_Activity_Manager_Test extends \WP_UnitTestCase {

	/**
	 * The manager instance under test.
	 *
	 * @var Post_Signup_Activity_Manager
	 */
	protected $manager;

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {

		parent::setUp();

		$this->manager = Post_Signup_Activity_Manager::get_instance();
	}

	/**
	 * The manager is a singleton and returns the same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$a = Post_Signup_Activity_Manager::get_instance();
		$b = Post_Signup_Activity_Manager::get_instance();

		$this->assertSame($a, $b);
	}

	/**
	 * The register_event_types method registers all four expected event slugs.
	 */
	public function test_register_event_types_registers_all_slugs(): void {

		// Trigger registration.
		$this->manager->register_event_types();

		$expected_slugs = [
			'subsite_post_created',
			'subsite_cpt_created',
			'subsite_user_registered',
			'subsite_woocommerce_order',
		];

		foreach ($expected_slugs as $slug) {
			$event = wu_get_event_type($slug);
			$this->assertIsArray($event, "Event type '{$slug}' should be registered.");
			$this->assertArrayHasKey('name', $event, "Event type '{$slug}' should have a 'name' key.");
			$this->assertArrayHasKey('desc', $event, "Event type '{$slug}' should have a 'desc' key.");
		}
	}

	/**
	 * The track_post_published method does nothing when the post status is not 'publish'.
	 */
	public function test_track_post_published_ignores_non_publish_status(): void {

		$post = $this->factory->post->create_and_get(
			[
				'post_status' => 'draft',
				'post_type'   => 'post',
			]
		);

		$events_before = wu_get_events(['number' => 9999]);

		// Simulate a draft-to-draft transition (should be ignored).
		$this->manager->track_post_published('draft', 'draft', $post);

		$events_after = wu_get_events(['number' => 9999]);

		$this->assertCount(
			count($events_before),
			$events_after,
			'No new event should be created for a non-publish transition.'
		);
	}

	/**
	 * The track_post_published method does nothing when old and new status are both 'publish'.
	 */
	public function test_track_post_published_ignores_re_save_of_published_post(): void {

		$post = $this->factory->post->create_and_get(
			[
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		$events_before = wu_get_events(['number' => 9999]);

		// Simulate a publish-to-publish transition (re-save, should be ignored).
		$this->manager->track_post_published('publish', 'publish', $post);

		$events_after = wu_get_events(['number' => 9999]);

		$this->assertCount(
			count($events_before),
			$events_after,
			'No new event should be created when re-saving an already-published post.'
		);
	}

	/**
	 * The track_post_published method does nothing when WordPress passes no post object.
	 */
	public function test_track_post_published_ignores_null_post(): void {

		$events_before = wu_get_events(['number' => 9999]);

		$this->manager->track_post_published('publish', 'draft', null);

		$events_after = wu_get_events(['number' => 9999]);

		$this->assertCount(
			count($events_before),
			$events_after,
			'No event should be created when WordPress does not provide a post object.'
		);
	}

	/**
	 * The track_post_published method does nothing for excluded post types.
	 */
	public function test_track_post_published_ignores_excluded_post_types(): void {

		$post = $this->factory->post->create_and_get(
			[
				'post_status' => 'draft',
				'post_type'   => 'revision',
			]
		);

		$events_before = wu_get_events(['number' => 9999]);

		$this->manager->track_post_published('publish', 'draft', $post);

		$events_after = wu_get_events(['number' => 9999]);

		$this->assertCount(
			count($events_before),
			$events_after,
			'No event should be created for excluded post types like "revision".'
		);
	}

	/**
	 * The get_wu_site method returns false when wu_get_site is not available.
	 */
	public function test_get_wu_site_returns_false_for_unknown_blog(): void {

		// Use reflection to call the protected method.
		$reflection = new \ReflectionClass($this->manager);
		$method     = $reflection->getMethod('get_wu_site');
		$method->setAccessible(true);

		// Blog ID 99999 should not exist.
		$result = $method->invoke($this->manager, 99999);

		$this->assertFalse($result, 'get_wu_site should return false for an unknown blog ID.');
	}

	/**
	 * EXCLUDED_POST_TYPES constant contains expected internal types.
	 */
	public function test_excluded_post_types_contains_revision(): void {

		$this->assertContains(
			'revision',
			Post_Signup_Activity_Manager::EXCLUDED_POST_TYPES,
			'"revision" must be in the excluded post types list.'
		);
	}

	/**
	 * EXCLUDED_POST_TYPES constant contains auto-draft.
	 */
	public function test_excluded_post_types_contains_auto_draft(): void {

		$this->assertContains(
			'auto-draft',
			Post_Signup_Activity_Manager::EXCLUDED_POST_TYPES,
			'"auto-draft" must be in the excluded post types list.'
		);
	}
}
