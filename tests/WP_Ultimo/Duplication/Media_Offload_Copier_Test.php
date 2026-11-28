<?php
/**
 * Test case for the media-offload copier used during site duplication.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Duplication;

use WP_Ultimo\Duplication\Media_Offload_Adapter;
use WP_Ultimo\Duplication\Media_Offload_Copier;
use WP_UnitTestCase;

/**
 * Test the Media_Offload_Copier orchestrator.
 */
class Media_Offload_Copier_Test extends WP_UnitTestCase {

	/**
	 * Template blog ID.
	 *
	 * @var int
	 */
	private $from_blog_id;

	/**
	 * Cloned blog ID.
	 *
	 * @var int
	 */
	private $to_blog_id;

	/**
	 * Cloned attachment ID.
	 *
	 * @var int
	 */
	private $attachment_id;

	/**
	 * Stub adapter: the "remote" objects are entries in an array. Exposes the
	 * public properties $copied (destinations written) and $fail.
	 *
	 * @var Media_Offload_Adapter
	 */
	private $adapter;

	/**
	 * Create a template and a clone whose attachment still points at the template.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! is_multisite()) {
			$this->markTestSkipped('Media offload tests require multisite');
		}

		$this->from_blog_id = self::factory()->blog->create();
		$this->to_blog_id   = self::factory()->blog->create();
		$this->adapter      = new class() implements Media_Offload_Adapter {

			/**
			 * Remote objects, keyed by object directory.
			 *
			 * @var array
			 */
			public $objects = [];

			/**
			 * Destinations the adapter wrote to.
			 *
			 * @var array
			 */
			public $copied = [];

			/**
			 * Whether copy() fails.
			 *
			 * @var bool
			 */
			public $fail = false;

			/**
			 * Claim attachments whose stub path is not under the current site.
			 *
			 * @param int $attachment_id Attachment ID.
			 * @return bool
			 */
			public function supports(int $attachment_id) {

				$path = (string) get_post_meta($attachment_id, 'stub_path', true);

				return '' !== $path && false === strpos($path, 'sites/' . get_current_blog_id() . '/');
			}

			/**
			 * Copy the object to the destination directory and repoint the meta.
			 *
			 * @param int $from_blog         Template blog ID.
			 * @param int $to_blog           Destination blog ID.
			 * @param int $source_attachment Source attachment ID.
			 * @param int $new_attachment    New attachment ID.
			 * @return true|\WP_Error
			 */
			public function copy(int $from_blog, int $to_blog, int $source_attachment, int $new_attachment) {

				if ($this->fail) {
					return new \WP_Error('stub_failure', 'Stub failure');
				}

				$source      = (string) get_post_meta($new_attachment, 'stub_path', true);
				$destination = str_replace('sites/' . $from_blog . '/', 'sites/' . $to_blog . '/', $source);

				$this->objects[ $destination ] = $this->objects[ $source ] ?? 'bytes';
				$this->copied[]                = $destination;

				update_post_meta($new_attachment, 'stub_path', $destination);

				return true;
			}
		};

		switch_to_blog($this->to_blog_id);

		$this->attachment_id = wp_insert_post(
			[
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_title'  => 'Placeholder',
			]
		);

		$source_dir = 'cdn.example.com/sites/' . $this->from_blog_id . '/2026/11';

		update_post_meta($this->attachment_id, 'stub_path', $source_dir . '/image.jpg');
		update_option('woocommerce_placeholder_image', $this->attachment_id);
		update_option('theme_mods_test', ['logo' => 'https://cdn.example.com/sites/' . $this->from_blog_id . '/2026/11/image.jpg']);

		restore_current_blog();

		$adapter = $this->adapter;

		add_filter(
			'wu_duplicate_media_adapters',
			function ($adapters) use ($adapter) {
				$adapters[] = $adapter;

				return $adapters;
			}
		);

		// Offload plugins serve the URL from their own meta.
		add_filter(
			'wp_get_attachment_url',
			function ($url, $post_id) {
				$path = (string) get_post_meta($post_id, 'stub_path', true);

				return '' === $path ? $url : 'https://' . $path;
			},
			10,
			2
		);
	}

	/**
	 * Remove the test blogs.
	 */
	public function tearDown(): void {
		wpmu_delete_blog($this->from_blog_id, true);
		wpmu_delete_blog($this->to_blog_id, true);

		parent::tearDown();
	}

	/**
	 * The clone's meta, options and content point at its own location.
	 */
	public function test_copy_media_rewrites_attachment_meta_and_urls() {

		$result = Media_Offload_Copier::get_instance()->copy_media($this->from_blog_id, $this->to_blog_id);

		$this->assertTrue($result);

		switch_to_blog($this->to_blog_id);

		$path = get_post_meta($this->attachment_id, 'stub_path', true);

		$this->assertStringContainsString('sites/' . $this->to_blog_id . '/', $path);
		$this->assertStringNotContainsString('sites/' . $this->from_blog_id . '/', $path);
		$this->assertSame($this->attachment_id, (int) get_option('woocommerce_placeholder_image'));
		$this->assertStringNotContainsString('sites/' . $this->from_blog_id . '/', wp_json_encode(get_option('theme_mods_test')));
		$this->assertStringContainsString('sites/' . $this->to_blog_id . '/', wp_json_encode(get_option('theme_mods_test')));

		restore_current_blog();

		// The template's object is only read, never moved or removed.
		$this->assertCount(1, $this->adapter->copied);
	}

	/**
	 * Running the step again changes nothing.
	 */
	public function test_copy_media_is_idempotent() {

		$copier = Media_Offload_Copier::get_instance();

		$copier->copy_media($this->from_blog_id, $this->to_blog_id);
		$copier->copy_media($this->from_blog_id, $this->to_blog_id);

		$this->assertCount(1, $this->adapter->copied);
	}

	/**
	 * A failing adapter returns a summary and leaves the attachment as it was.
	 */
	public function test_failing_adapter_is_non_fatal_and_retryable() {

		$this->adapter->fail = true;

		$result = Media_Offload_Copier::get_instance()->copy_media($this->from_blog_id, $this->to_blog_id);

		$this->assertWPError($result);
		$this->assertSame('media_offload_copy_failed', $result->get_error_code());
		$this->assertArrayHasKey($this->attachment_id, $result->get_error_data()['failures']);

		// Fixed adapter: the next run picks the attachment up again.
		$this->adapter->fail = false;

		$this->assertTrue(Media_Offload_Copier::get_instance()->copy_media($this->from_blog_id, $this->to_blog_id));
		$this->assertCount(1, $this->adapter->copied);
	}

	/**
	 * Without a registered adapter the step does nothing.
	 */
	public function test_no_adapter_is_a_no_op() {

		remove_all_filters('wu_duplicate_media_adapters');

		$this->assertTrue(Media_Offload_Copier::get_instance()->copy_media($this->from_blog_id, $this->to_blog_id));

		switch_to_blog($this->to_blog_id);

		$this->assertStringContainsString('sites/' . $this->from_blog_id . '/', get_post_meta($this->attachment_id, 'stub_path', true));

		restore_current_blog();
	}

	/**
	 * The moved prefix keeps the trailing slash so site 2 never matches site 25.
	 */
	public function test_get_moved_prefix() {

		$this->assertSame(
			[
				'from' => 'cdn.example.com/sites/2/',
				'to'   => 'cdn.example.com/sites/25/',
			],
			Media_Offload_Copier::get_moved_prefix('https://cdn.example.com/sites/2/2026/11/a.jpg', 'https://cdn.example.com/sites/25/2026/11/a.jpg')
		);
		$this->assertNull(Media_Offload_Copier::get_moved_prefix('https://cdn.example.com/a.jpg', 'https://cdn.example.com/a.jpg'));
	}
}
