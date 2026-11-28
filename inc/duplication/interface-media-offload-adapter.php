<?php
/**
 * Contract for media-offload adapters used during site duplication.
 *
 * @package WP_Ultimo
 * @subpackage Duplication
 * @since n.e.x.t
 */

namespace WP_Ultimo\Duplication;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Lets a media-offload plugin (or host) give a duplicated site its own copy of
 * the remote objects behind an offloaded attachment.
 *
 * Register adapters with the `wu_duplicate_media_adapters` filter. Adapters are
 * called while the destination site is the current blog (switch_to_blog()).
 * Because the site tables are copied row for row, the cloned attachment keeps
 * the template attachment's ID.
 *
 * Adapters must never delete or modify objects at the source location, and must
 * never put credentials in the errors they return: messages are logged.
 *
 * @since n.e.x.t
 */
interface Media_Offload_Adapter {

	/**
	 * Whether the attachment of the destination site carries this adapter's offload data.
	 *
	 * Return false for attachments that already point at the destination, so
	 * the adapter is not asked to copy them again.
	 *
	 * @since n.e.x.t
	 * @param int $attachment_id Attachment ID in the destination site.
	 * @return bool
	 */
	public function supports(int $attachment_id);

	/**
	 * Copy the remote objects (all sizes) to the destination site's location and
	 * rewrite the cloned attachment's offload data to point at it.
	 *
	 * Must be safe to repeat: copying twice yields the same final state.
	 *
	 * @since n.e.x.t
	 * @param int $from_blog         Template blog ID.
	 * @param int $to_blog           Destination blog ID (the current blog).
	 * @param int $source_attachment Attachment ID in the template site.
	 * @param int $new_attachment    Attachment ID in the destination site.
	 * @return true|\WP_Error True once the clone points at its own objects.
	 */
	public function copy(int $from_blog, int $to_blog, int $source_attachment, int $new_attachment);
}
