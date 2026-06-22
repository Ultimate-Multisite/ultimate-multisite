<?php
/**
 * Promotes a subsite into the existing WordPress main site.
 *
 * @package WP_Ultimo\Site_Exporter
 * @subpackage Site_Exporter
 * @since 2.5.1
 */

namespace WP_Ultimo\Site_Exporter;

use WP_Ultimo\Helpers\Site_Duplicator;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Main site promoter service.
 *
 * Copies a selected subsite into the current WordPress main site without changing
 * WordPress bootstrap constants or the main-site blog ID.
 *
 * @since 2.5.1
 */
class Main_Site_Promoter {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Initialize the singleton.
	 *
	 * @since 2.5.1
	 * @return void
	 */
	public function init(): void {

		// No hooks are registered by the service itself.
	}

	/**
	 * Promote a source subsite into the current main site.
	 *
	 * @since 2.5.1
	 *
	 * @param int   $source_blog_id Source subsite blog ID.
	 * @param array $args           Promotion arguments.
	 * @return array|\WP_Error Promotion result or error.
	 */
	public function promote($source_blog_id, $args = []) {

		$source_blog_id = absint($source_blog_id);
		$caller_blog_id = get_current_blog_id();
		$stack_snapshot = isset($GLOBALS['_wp_switched_stack']) ? $GLOBALS['_wp_switched_stack'] : [];
		$switched       = isset($GLOBALS['switched']) ? (bool) $GLOBALS['switched'] : false;

		$args = wp_parse_args(
			$args,
			[
				'force'               => false,
				'backup'              => true,
				'copy_files'          => true,
				'keep_users'          => true,
				'preserve_main_title' => true,
			]
		);

		$validation = $this->validate($source_blog_id, $args);

		if (is_wp_error($validation)) {
			return $validation;
		}

		$main_site_id = wu_get_main_site_id();
		$main_url     = get_site_url($main_site_id);
		$main_title   = get_blog_option($main_site_id, 'blogname');
		$source_url   = get_site_url($source_blog_id);
		$source_title = get_blog_option($source_blog_id, 'blogname');
		$backups      = [];

		try {
			if ($args['backup']) {
				$backups = $this->create_backups($main_site_id, $source_blog_id, $args);

				if (is_wp_error($backups)) {
					return $backups;
				}
			}

			/**
			 * Allow tests and integrations to override the copy/import step.
			 *
			 * Return null to use the default Site_Duplicator pipeline.
			 *
			 * @since 2.5.1
			 *
			 * @param null|bool|\WP_Error $result         Override result.
			 * @param int                 $source_blog_id Source subsite blog ID.
			 * @param int                 $main_site_id   Main site blog ID.
			 * @param array               $args           Promotion arguments.
			 */
			$override = apply_filters('wu_main_site_promoter_override_site', null, $source_blog_id, $main_site_id, $args);

			if (null === $override) {
				$override = Site_Duplicator::override_site(
					$source_blog_id,
					$main_site_id,
					[
						'copy_files' => (bool) $args['copy_files'],
						'keep_users' => (bool) $args['keep_users'],
					]
				);
			}

			if (is_wp_error($override)) {
				return $override;
			}

			if (false === $override) {
				return new \WP_Error('main_site_promotion_failed', __('The source site could not be copied into the main site.', 'ultimate-multisite'));
			}

			$this->restore_main_identity($main_site_id, $main_url, $main_title, $source_title, (bool) $args['preserve_main_title']);

			wp_cache_flush();
			clean_blog_cache($main_site_id);

			return [
				'source_blog_id'        => $source_blog_id,
				'main_site_id'          => $main_site_id,
				'source_url'            => $source_url,
				'main_url'              => $main_url,
				'backup_exports'        => $backups,
				'source_site_preserved' => true,
			];
		} catch (\Throwable $exception) {
			return new \WP_Error('main_site_promotion_exception', $exception->getMessage());
		} finally {
			$this->restore_blog_context($caller_blog_id, $stack_snapshot, $switched);
		}
	}

	/**
	 * Validate a promotion request.
	 *
	 * @since 2.5.1
	 *
	 * @param int   $source_blog_id Source subsite blog ID.
	 * @param array $args           Promotion arguments.
	 * @return true|\WP_Error True when valid, otherwise WP_Error.
	 */
	private function validate($source_blog_id, array $args) {

		if (! is_multisite()) {
			return new \WP_Error('main_site_promotion_requires_multisite', __('Promoting a subsite to the main site requires WordPress multisite.', 'ultimate-multisite'));
		}

		if (! $source_blog_id) {
			return new \WP_Error('main_site_promotion_invalid_source', __('A valid source site is required.', 'ultimate-multisite'));
		}

		$source_site = get_site($source_blog_id);

		if (! $source_site) {
			return new \WP_Error('main_site_promotion_source_not_found', __('The source site could not be found.', 'ultimate-multisite'));
		}

		if ((int) wu_get_main_site_id() === $source_blog_id) {
			return new \WP_Error('main_site_promotion_source_is_main', __('The current main site cannot be promoted into itself.', 'ultimate-multisite'));
		}

		if (empty($args['force']) && ((int) $source_site->archived || (int) $source_site->spam || (int) $source_site->deleted)) {
			return new \WP_Error('main_site_promotion_source_inactive', __('Archived, spam, or deleted sites cannot be promoted unless force is enabled.', 'ultimate-multisite'));
		}

		$main_site = get_site(wu_get_main_site_id());

		if (! $main_site) {
			return new \WP_Error('main_site_promotion_main_not_found', __('The WordPress main site could not be found.', 'ultimate-multisite'));
		}

		if (empty($args['force']) && ((int) $main_site->archived || (int) $main_site->spam || (int) $main_site->deleted)) {
			return new \WP_Error('main_site_promotion_main_inactive', __('The current main site is archived, spam, or deleted. Use force only after reviewing the site status.', 'ultimate-multisite'));
		}

		return true;
	}

	/**
	 * Export the current main site and source subsite before mutation.
	 *
	 * @since 2.5.1
	 *
	 * @param int   $main_site_id   Main site blog ID.
	 * @param int   $source_blog_id Source subsite blog ID.
	 * @param array $args           Promotion arguments.
	 * @return array|\WP_Error Backup filenames or error.
	 */
	private function create_backups($main_site_id, $source_blog_id, array $args) {

		$export_options = [
			'uploads' => true,
			'themes'  => false,
			'plugins' => false,
		];

		/**
		 * Filters backup export options used before main-site promotion.
		 *
		 * @since 2.5.1
		 *
		 * @param array $export_options Export options.
		 * @param int   $main_site_id   Main site blog ID.
		 * @param int   $source_blog_id Source subsite blog ID.
		 * @param array $args           Promotion arguments.
		 */
		$export_options = apply_filters('wu_main_site_promoter_backup_export_options', $export_options, $main_site_id, $source_blog_id, $args);

		$main_backup = $this->export_site($main_site_id, $export_options);

		if (is_wp_error($main_backup)) {
			return $main_backup;
		}

		$source_backup = $this->export_site($source_blog_id, $export_options);

		if (is_wp_error($source_backup)) {
			return $source_backup;
		}

		return [
			'main_site' => $main_backup,
			'source'    => $source_backup,
		];
	}

	/**
	 * Export a site synchronously for backup.
	 *
	 * @since 2.5.1
	 *
	 * @param int   $site_id Site blog ID.
	 * @param array $options Export options.
	 * @return string|true|\WP_Error Export filename/result or error.
	 */
	private function export_site($site_id, array $options) {

		/**
		 * Allow tests and integrations to short-circuit promotion backup exports.
		 *
		 * Return null to use wu_exporter_export().
		 *
		 * @since 2.5.1
		 *
		 * @param null|string|true|\WP_Error $export  Export result.
		 * @param int                        $site_id Site blog ID.
		 * @param array                      $options Export options.
		 */
		$export = apply_filters('wu_main_site_promoter_export_site', null, $site_id, $options);

		if (null !== $export) {
			return $export;
		}

		return wu_exporter_export($site_id, $options, false);
	}

	/**
	 * Restore main-site identity after the content copy/import.
	 *
	 * @since 2.5.1
	 *
	 * @param int    $main_site_id        Main site blog ID.
	 * @param string $main_url            Main site URL.
	 * @param string $main_title          Original main site title.
	 * @param string $source_title        Source site title.
	 * @param bool   $preserve_main_title Whether to preserve the original main title.
	 * @return void
	 */
	private function restore_main_identity($main_site_id, $main_url, $main_title, $source_title, $preserve_main_title) {

		update_blog_option($main_site_id, 'home', $main_url);
		update_blog_option($main_site_id, 'siteurl', $main_url);

		$title_to_use = $preserve_main_title ? $main_title : $source_title;

		update_blog_option($main_site_id, 'blogname', $title_to_use);
	}

	/**
	 * Restore the caller's blog context and switch stack.
	 *
	 * @since 2.5.1
	 *
	 * @param int   $caller_blog_id Caller blog ID.
	 * @param array $stack_snapshot Original switch stack.
	 * @param bool  $switched       Original switched flag.
	 * @return void
	 */
	private function restore_blog_context($caller_blog_id, array $stack_snapshot, $switched) {

		while (function_exists('ms_is_switched') && ms_is_switched()) {
			restore_current_blog();
		}

		if (get_current_blog_id() !== (int) $caller_blog_id) {
			switch_to_blog((int) $caller_blog_id);
		}

		$GLOBALS['_wp_switched_stack'] = $stack_snapshot;
		$GLOBALS['switched']           = (bool) $switched;
	}
}
