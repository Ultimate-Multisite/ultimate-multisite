<?php
/**
 * Media-offload adapter for the Advanced Media Offloader plugin.
 *
 * @package WP_Ultimo
 * @subpackage Duplication
 * @since n.e.x.t
 */

namespace WP_Ultimo\Duplication;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Copies the remote objects of an Advanced Media Offloader attachment to the
 * destination site's location and points the clone's `advmo_path` at it.
 *
 * Opt-in: nothing registers this adapter. A host adds it through the
 * `wu_duplicate_media_adapters` filter, together with the storage operation
 * that only the host's provider can perform (server-side copy):
 *
 *     add_filter('wu_duplicate_media_adapters', function ($adapters) {
 *         $adapters[] = new \WP_Ultimo\Duplication\Advanced_Media_Offloader_Adapter();
 *         return $adapters;
 *     });
 *     add_filter('wu_duplicate_media_copy_object', function ($result, $source_key, $destination_key, $context) {
 *         return my_provider_copy($context['bucket'], $source_key, $destination_key); // true|WP_Error.
 *     }, 10, 4);
 *
 * Without a handler on `wu_duplicate_media_copy_object` the adapter returns an
 * error and leaves the attachment untouched. It only reads from the source
 * keys: it never deletes or modifies them.
 *
 * Meta used (written by Advanced Media Offloader): `advmo_offloaded`,
 * `advmo_path` (the object directory of the attachment) and `advmo_bucket`.
 *
 * @since n.e.x.t
 */
class Advanced_Media_Offloader_Adapter implements Media_Offload_Adapter {

	/**
	 * Claims attachments Advanced Media Offloader offloaded, whose path is set.
	 *
	 * @since n.e.x.t
	 * @param int $attachment_id Attachment ID in the destination site.
	 * @return bool
	 */
	public function supports(int $attachment_id) {

		$path = trim((string) get_post_meta($attachment_id, 'advmo_path', true), '/');

		return (bool) get_post_meta($attachment_id, 'advmo_offloaded', true)
			&& '' !== $path
			&& ! $this->is_in_site_location($path, get_current_blog_id());
	}

	/**
	 * Copy every object of the attachment, then repoint `advmo_path`.
	 *
	 * The meta is rewritten only once every object was copied, so a failure
	 * leaves the clone exactly as it was and a later run retries it.
	 *
	 * @since n.e.x.t
	 * @param int $from_blog         Template blog ID.
	 * @param int $to_blog           Destination blog ID (the current blog).
	 * @param int $source_attachment Attachment ID in the template site.
	 * @param int $new_attachment    Attachment ID in the destination site.
	 * @return true|\WP_Error
	 */
	public function copy(int $from_blog, int $to_blog, int $source_attachment, int $new_attachment) {

		$source_path = trim((string) get_post_meta($new_attachment, 'advmo_path', true), '/');

		// Already under the destination site's location: nothing to copy.
		if ($this->is_in_site_location($source_path, $to_blog)) {
			return true;
		}

		/**
		 * Filters the object directory the duplicated attachment is copied to.
		 *
		 * The default swaps the `sites/<id>/` path segment for `sites/<new site id>/`.
		 * Return the unchanged source path to refuse the copy.
		 *
		 * @since n.e.x.t
		 * @param string $destination_path Destination object directory, no surrounding slashes.
		 * @param string $source_path      Source object directory, no surrounding slashes.
		 * @param int    $from_blog        Template blog ID.
		 * @param int    $to_blog          Destination blog ID.
		 * @param int    $new_attachment   Attachment ID in the destination site.
		 */
		$destination_path = trim((string) apply_filters('wu_duplicate_media_destination_path', $this->get_default_destination_path($source_path, $from_blog, $to_blog), $source_path, $from_blog, $to_blog, $new_attachment), '/');

		if ($destination_path === $source_path) {
			return new \WP_Error('media_offload_no_destination', __('The attachment already has, or cannot be given, its own storage location.', 'ultimate-multisite'));
		}

		$context = [
			'bucket'        => (string) get_post_meta($new_attachment, 'advmo_bucket', true),
			'from_blog'     => $from_blog,
			'to_blog'       => $to_blog,
			'attachment_id' => $new_attachment,
		];

		foreach ($this->get_object_files($new_attachment) as $file) {
			/**
			 * Copies one remote object, server side, from the template's location to the new site's.
			 *
			 * Must only read the source key. Return true on success or a WP_Error;
			 * null means no handler is registered. Never put secrets (keys, tokens) in the error.
			 *
			 * @since n.e.x.t
			 * @param true|\WP_Error|null $result          Copy result.
			 * @param string              $source_key      Source object key.
			 * @param string              $destination_key Destination object key.
			 * @param array               $context         bucket, from_blog, to_blog and attachment_id.
			 */
			$result = apply_filters('wu_duplicate_media_copy_object', null, $source_path . '/' . $file, $destination_path . '/' . $file, $context);

			if (null === $result) {
				return new \WP_Error('media_offload_no_copy_handler', __('No handler is registered to copy offloaded objects.', 'ultimate-multisite'));
			}

			if (is_wp_error($result)) {
				return $result;
			}

			if (true !== $result) {
				return new \WP_Error('media_offload_copy_object_failed', __('An offloaded object could not be copied.', 'ultimate-multisite'));
			}
		}

		update_post_meta($new_attachment, 'advmo_path', $destination_path);

		return true;
	}

	/**
	 * Default destination: swap the `sites/<id>/` path segment for `sites/<to>/`.
	 *
	 * Any site ID is swapped, not only the template's, so a template whose own
	 * media came from another site still gets a location of its own.
	 *
	 * @since n.e.x.t
	 * @param string $source_path Source object directory.
	 * @param int    $from_blog   Template blog ID.
	 * @param int    $to_blog     Destination blog ID.
	 * @return string The source path unchanged when it has no `sites/<id>` segment.
	 */
	protected function get_default_destination_path(string $source_path, int $from_blog, int $to_blog): string {

		return (string) preg_replace('#(^|/)sites/\d+(?=/|$)#', '${1}sites/' . $to_blog, $source_path, 1);
	}

	/**
	 * Whether an object directory sits under a site's `sites/<id>/` segment.
	 *
	 * @since n.e.x.t
	 * @param string $path    Object directory.
	 * @param int    $blog_id Blog ID.
	 * @return bool
	 */
	protected function is_in_site_location(string $path, int $blog_id): bool {

		return 1 === preg_match('#(^|/)sites/' . $blog_id . '(/|$)#', $path);
	}

	/**
	 * List the file names of every object of the attachment: original, scaled and all sizes.
	 *
	 * @since n.e.x.t
	 * @param int $attachment_id Attachment ID.
	 * @return string[] File names relative to the attachment's object directory.
	 */
	protected function get_object_files(int $attachment_id): array {

		$files    = [];
		$main     = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
		$metadata = wp_get_attachment_metadata($attachment_id);

		if ('' !== $main) {
			$files[] = wp_basename($main);
		}

		if (is_array($metadata)) {
			if ( ! empty($metadata['original_image'])) {
				$files[] = wp_basename((string) $metadata['original_image']);
			}

			foreach ((array) ($metadata['sizes'] ?? []) as $size) {
				if ( ! empty($size['file'])) {
					$files[] = wp_basename((string) $size['file']);
				}
			}
		}

		return array_values(array_unique($files));
	}
}
