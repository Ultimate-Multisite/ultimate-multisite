<?php
/**
 * Copies offloaded media into a duplicated site's own storage location.
 *
 * @package WP_Ultimo
 * @subpackage Duplication
 * @since n.e.x.t
 */

namespace WP_Ultimo\Duplication;

use Psr\Log\LogLevel;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Orchestrates the media-offload adapters registered through the
 * `wu_duplicate_media_adapters` filter.
 *
 * MUCD_Files::copy_files() copies only the template's local uploads folder, so
 * the cloned attachments of an offloaded template keep pointing at the
 * template's remote objects. This step asks each adapter to copy those objects
 * to the new site's location and then rewrites media URLs that the copy moved.
 *
 * The step never aborts a duplication: failures are logged and summarized in
 * the returned WP_Error. With no adapter registered it does nothing.
 *
 * @since n.e.x.t
 */
class Media_Offload_Copier {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Attachment meta holding the blog ID an attachment was already copied for.
	 *
	 * Makes a second run on the same site a no-op. A clone of a clone inherits
	 * the value of its template, which differs from its own blog ID.
	 */
	public const COPIED_META = '_wu_media_offload_copied_to';

	/**
	 * Attachments loaded per query.
	 */
	private const BATCH_SIZE = 200;

	/**
	 * Copy the offloaded media of a template into the duplicated site.
	 *
	 * Idempotent and resumable: attachments already copied for the destination
	 * are skipped, and a failed attachment is retried by the next run.
	 *
	 * @since n.e.x.t
	 * @param int $from_blog_id Template blog ID.
	 * @param int $to_blog_id   Destination blog ID.
	 * @return true|\WP_Error True when nothing failed, a WP_Error summarizing the failed attachments otherwise.
	 */
	public function copy_media($from_blog_id, $to_blog_id) {

		$from_blog_id = (int) $from_blog_id;
		$to_blog_id   = (int) $to_blog_id;

		if ($from_blog_id < 1 || $to_blog_id < 1 || $from_blog_id === $to_blog_id) {
			return true;
		}

		$adapters = $this->get_adapters($from_blog_id, $to_blog_id);

		if (empty($adapters)) {
			return true;
		}

		switch_to_blog($to_blog_id);

		try {
			$summary = $this->process_attachments($adapters, $from_blog_id, $to_blog_id);

			if ($summary['url_map']) {
				$this->rewrite_urls($summary['url_map'], $to_blog_id);
			}
		} finally {
			restore_current_blog();
		}

		if (empty($summary['failures'])) {
			return true;
		}

		foreach ($summary['failures'] as $attachment_id => $reason) {
			wu_log_add(
				'site-duplication',
				sprintf('Site %d: copying offloaded media of template %d failed for attachment %d: %s', $to_blog_id, $from_blog_id, $attachment_id, $reason),
				LogLevel::WARNING
			);
		}

		return new \WP_Error(
			'media_offload_copy_failed',
			sprintf(
				// translators: %d is the number of attachments that could not be copied.
				__('%d offloaded attachments could not be copied to the new site.', 'ultimate-multisite'),
				count($summary['failures'])
			),
			['failures' => $summary['failures']]
		);
	}

	/**
	 * Get the usable adapters.
	 *
	 * @since n.e.x.t
	 * @param int $from_blog_id Template blog ID.
	 * @param int $to_blog_id   Destination blog ID.
	 * @return object[] Objects exposing supports() and copy().
	 */
	public function get_adapters(int $from_blog_id, int $to_blog_id): array {

		/**
		 * Register media-offload adapters used when a site is duplicated.
		 *
		 * Each adapter implements \WP_Ultimo\Duplication\Media_Offload_Adapter
		 * (or at least its supports() and copy() methods). Adapters run only
		 * when the "Copy Media on Template Duplication" setting is enabled.
		 *
		 * @since n.e.x.t
		 * @param object[] $adapters     Registered adapters.
		 * @param int      $from_blog_id Template blog ID.
		 * @param int      $to_blog_id   Destination blog ID.
		 */
		$adapters = apply_filters('wu_duplicate_media_adapters', [], $from_blog_id, $to_blog_id);

		if ( ! is_array($adapters)) {
			return [];
		}

		return array_values(
			array_filter(
				$adapters,
				fn($adapter) => is_object($adapter) && method_exists($adapter, 'supports') && method_exists($adapter, 'copy')
			)
		);
	}

	/**
	 * Run the adapters over every attachment of the current (destination) blog.
	 *
	 * @since n.e.x.t
	 * @param object[] $adapters     Usable adapters.
	 * @param int      $from_blog_id Template blog ID.
	 * @param int      $to_blog_id   Destination blog ID.
	 * @return array{failures: array<int,string>, url_map: array<string,string>}
	 */
	protected function process_attachments(array $adapters, int $from_blog_id, int $to_blog_id): array {

		global $wpdb;

		$failures = [];
		$url_map  = [];
		$last_id  = 0;

		do {
			// Keyset pagination keeps memory flat and is unaffected by the meta writes below.
			$attachment_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID > %d ORDER BY ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$last_id,
					self::BATCH_SIZE
				)
			);

			foreach ($attachment_ids as $attachment_id) {
				$attachment_id = (int) $attachment_id;
				$last_id       = $attachment_id;

				if ((int) get_post_meta($attachment_id, self::COPIED_META, true) === $to_blog_id) {
					continue;
				}

				$result = $this->copy_attachment($adapters, $from_blog_id, $to_blog_id, $attachment_id);

				if (null === $result) {
					continue;
				}

				if (is_wp_error($result)) {
					$failures[ $attachment_id ] = $result->get_error_code() . ': ' . $result->get_error_message();

					continue;
				}

				$url_map = array_merge($url_map, $result);
			}

			$fetched = count($attachment_ids);
		} while ($fetched >= self::BATCH_SIZE);

		return [
			'failures' => $failures,
			'url_map'  => $url_map,
		];
	}

	/**
	 * Let the first adapter that claims the attachment copy it.
	 *
	 * @since n.e.x.t
	 * @param object[] $adapters      Usable adapters.
	 * @param int      $from_blog_id  Template blog ID.
	 * @param int      $to_blog_id    Destination blog ID.
	 * @param int      $attachment_id Attachment ID (identical in the template and the clone).
	 * @return array<string,string>|\WP_Error|null Moved URL prefixes, an error, or null when no adapter claims it.
	 */
	protected function copy_attachment(array $adapters, int $from_blog_id, int $to_blog_id, int $attachment_id) {

		try {
			$adapter = $this->find_adapter($adapters, $attachment_id);

			if (null === $adapter) {
				return null;
			}

			$url_before = wp_get_attachment_url($attachment_id);

			$result = $adapter->copy($from_blog_id, $to_blog_id, $attachment_id, $attachment_id);
		} catch (\Throwable $exception) {
			return new \WP_Error('media_offload_adapter_exception', $exception->getMessage());
		}

		if (is_wp_error($result)) {
			return $result;
		}

		if (true !== $result) {
			return new \WP_Error('media_offload_unexpected_result', __('The adapter did not report success.', 'ultimate-multisite'));
		}

		update_post_meta($attachment_id, self::COPIED_META, $to_blog_id);

		$prefix = self::get_moved_prefix((string) $url_before, (string) wp_get_attachment_url($attachment_id));

		return $prefix ? [$prefix['from'] => $prefix['to']] : [];
	}

	/**
	 * Find the first adapter claiming an attachment.
	 *
	 * @since n.e.x.t
	 * @param object[] $adapters      Usable adapters.
	 * @param int      $attachment_id Attachment ID.
	 * @return object|null
	 */
	protected function find_adapter(array $adapters, int $attachment_id) {

		foreach ($adapters as $adapter) {
			if ($adapter->supports($attachment_id)) {
				return $adapter;
			}
		}

		return null;
	}

	/**
	 * Get the URL prefix an attachment moved from and to.
	 *
	 * Drops the trailing directories both URLs share (such as the upload
	 * year/month), keeping a trailing slash so `sites/2/` never matches
	 * `sites/25/`.
	 *
	 * @since n.e.x.t
	 * @param string $url_before Attachment URL before the copy.
	 * @param string $url_after  Attachment URL after the copy.
	 * @return array{from: string, to: string}|null Null when the URL did not move.
	 */
	public static function get_moved_prefix(string $url_before, string $url_after): ?array {

		if ('' === $url_before || '' === $url_after || $url_before === $url_after) {
			return null;
		}

		$before = explode('/', preg_replace('#^https?://#', '', dirname($url_before)));
		$after  = explode('/', preg_replace('#^https?://#', '', dirname($url_after)));

		$shared = min(count($before), count($after)) - 1;

		// Keep at least one differing leading segment.
		while ($shared > 0 && end($before) === end($after)) {
			array_pop($before);
			array_pop($after);

			--$shared;
		}

		$from = implode('/', $before) . '/';
		$to   = implode('/', $after) . '/';

		return $from === $to ? null : [
			'from' => $from,
			'to'   => $to,
		];
	}

	/**
	 * Replace the moved URL prefixes in the destination site's content.
	 *
	 * Uses the same replacement primitive and table/field list as
	 * MUCD_Data::db_update_data() (which applies `mucd_string_to_replace`), so
	 * options such as the WooCommerce placeholder URL, site icon, custom logo and
	 * theme mods follow the attachment to its new location. Attachment IDs stored
	 * in options need no rewrite: the clone keeps the template's attachment IDs.
	 *
	 * @since n.e.x.t
	 * @param array<string,string> $url_map     Old prefix => new prefix, without scheme.
	 * @param int                  $to_blog_id  Destination blog ID.
	 * @return void
	 */
	protected function rewrite_urls(array $url_map, int $to_blog_id): void {

		global $wpdb;

		if ( ! class_exists('MUCD_Data') || ! class_exists('MUCD_Option')) {
			return;
		}

		$prefix = $wpdb->get_blog_prefix($to_blog_id);

		foreach ($url_map as $from => $to) {
			$replacements = [
				$from                          => $to,
				str_replace('/', '\\/', $from) => str_replace('/', '\\/', $to),
			];

			foreach (\MUCD_Option::get_fields_to_update() as $table => $fields) {
				foreach ($replacements as $from_string => $to_string) {
					\MUCD_Data::update_as_warning($prefix . $table, $fields, $from_string, $to_string, (string) $table);
				}
			}
		}
	}
}
