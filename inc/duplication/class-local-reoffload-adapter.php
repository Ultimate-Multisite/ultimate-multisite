<?php
/**
 * Generic media-offload adapter that re-offloads from the local copy.
 *
 * @package WP_Ultimo
 * @subpackage Duplication
 * @since n.e.x.t
 */

namespace WP_Ultimo\Duplication;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Resets an attachment's offload meta when its file was copied to the new
 * site's uploads folder, so an offload plugin that offloads from local files
 * uploads the attachment again under the new site's own location.
 *
 * Opt-in: nothing registers this adapter. A host or plugin adds it through the
 * `wu_duplicate_media_adapters` filter, naming the plugin's offload meta keys:
 *
 *     add_filter('wu_duplicate_media_adapters', function ($adapters) {
 *         $adapters[] = new \WP_Ultimo\Duplication\Local_Reoffload_Adapter(['my_offloaded', 'my_remote_path']);
 *         return $adapters;
 *     });
 *
 * It only edits the clone's postmeta: it never deletes or modifies remote
 * objects, and fires `wu_duplicate_media_reoffload` for plugins that need to
 * queue the upload.
 *
 * @since n.e.x.t
 */
class Local_Reoffload_Adapter implements Media_Offload_Adapter {

	/**
	 * Postmeta keys the offload plugin writes on an offloaded attachment.
	 *
	 * @var string[]
	 */
	protected $meta_keys;

	/**
	 * Constructor.
	 *
	 * @since n.e.x.t
	 * @param string[] $meta_keys Offload postmeta keys. The first key marks an attachment as offloaded.
	 */
	public function __construct(array $meta_keys) {

		$this->meta_keys = array_values(array_filter(array_map('strval', $meta_keys)));
	}

	/**
	 * Claims offloaded attachments whose file exists in the current site's uploads folder.
	 *
	 * @since n.e.x.t
	 * @param int $attachment_id Attachment ID in the destination site.
	 * @return bool
	 */
	public function supports(int $attachment_id) {

		if (empty($this->meta_keys) || '' === (string) get_post_meta($attachment_id, $this->meta_keys[0], true)) {
			return false;
		}

		$relative_path = (string) get_post_meta($attachment_id, '_wp_attached_file', true);

		if ('' === $relative_path || str_contains($relative_path, '..')) {
			return false;
		}

		$uploads = wp_get_upload_dir();

		return empty($uploads['error']) && is_file(trailingslashit($uploads['basedir']) . $relative_path);
	}

	/**
	 * Drop the offload meta of the clone and let the plugin offload the local file again.
	 *
	 * @since n.e.x.t
	 * @param int $from_blog         Template blog ID.
	 * @param int $to_blog           Destination blog ID (the current blog).
	 * @param int $source_attachment Attachment ID in the template site.
	 * @param int $new_attachment    Attachment ID in the destination site.
	 * @return true
	 */
	public function copy(int $from_blog, int $to_blog, int $source_attachment, int $new_attachment) {

		foreach ($this->meta_keys as $meta_key) {
			delete_post_meta($new_attachment, $meta_key);
		}

		/**
		 * Fires after the offload meta of a cloned attachment was reset.
		 *
		 * @since n.e.x.t
		 * @param int $new_attachment Attachment ID in the destination site (the current blog).
		 * @param int $to_blog        Destination blog ID.
		 * @param int $from_blog      Template blog ID.
		 */
		do_action('wu_duplicate_media_reoffload', $new_attachment, $to_blog, $from_blog);

		return true;
	}
}
