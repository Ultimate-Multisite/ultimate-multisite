<?php
/**
 * Handles limitations to post types, uploads and more.
 *
 * @package WP_Ultimo
 * @subpackage Limits
 * @since 2.0.0
 */

namespace WP_Ultimo\Limits;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Handles limitations to post types, uploads and more.
 *
 * @since 2.0.0
 */
class Post_Type_Limits {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Runs on the first and only instantiation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		/**
		 * Emulated post types.
		 *
		 * @since 2.0.6
		 */
		if ( is_main_site() && is_network_admin() ) {
			add_action( 'init', array( $this, 'register_emulated_post_types' ), 999 );
		}

		/**
		 * Allow plugin developers to short-circuit the limitations.
		 *
		 * You can use this filter to run arbitrary code before any of the limits get initiated.
		 * If you filter returns any truthy value, the process will move on, if it returns any falsy value,
		 * the code will return and none of the hooks below will run.
		 *
		 * @since 1.7.0
		 * @return bool
		 */
		if ( ! apply_filters( 'wu_apply_plan_limits', wu_get_current_site()->has_limitations() ) ) {
			return;
		}

		if ( ! wu_get_current_site()->has_module_limitation( 'post_types' ) ) {
			return;
		}

		add_action( 'load-post-new.php', array( $this, 'limit_posts' ) );

		add_filter( 'wp_handle_upload', array( $this, 'limit_media' ) );

		add_filter( 'media_upload_tabs', array( $this, 'limit_tabs' ) );

		add_action( 'current_screen', array( $this, 'limit_restoring' ), 10 );

		add_filter( 'wp_insert_post_data', array( $this, 'limit_draft_publishing' ), 10, 2 );

		add_action( 'wu_async_after_membership_update_products', array( $this, 'handle_downgrade' ) );
	}

	/**
	 * Emulates post types to avoid having to have plugins active on the main site.
	 *
	 * @since 2.0.6
	 * @return void
	 */
	public function register_emulated_post_types(): void {

		$emulated_post_types = wu_get_setting( 'emulated_post_types', array() );

		if ( ! is_array( $emulated_post_types ) || empty( $emulated_post_types ) ) {
			return;
		}

		// Clean up corrupted data automatically
		$cleaned_post_types = array();
		$needs_update       = false;

		foreach ( $emulated_post_types as $index => $pt ) {
			// Verify that $pt is an array
			if ( ! is_array( $pt ) ) {
				$needs_update = true;
				continue;
			}

			// Verify that required keys exist (allow empty values for new entries)
			if ( ! isset( $pt['post_type'] ) || ! isset( $pt['label'] ) ) {
				$needs_update = true;
				continue;
			}

			// Add data (even if empty, to allow users to fill in new post types)
			$cleaned_post_types[] = array(
				'post_type' => isset( $pt['post_type'] ) ? sanitize_key( $pt['post_type'] ) : '',
				'label'     => isset( $pt['label'] ) ? sanitize_text_field( $pt['label'] ) : '',
			);
		}

		// Save cleaned data if there were any changes
		if ( $needs_update ) {
			wu_save_setting( 'emulated_post_types', $cleaned_post_types );
			$emulated_post_types = $cleaned_post_types;
		}

		// Register only valid post types (skip empty ones)
		foreach ( $emulated_post_types as $pt ) {
			// Skip if post_type or label is empty
			if ( empty( $pt['post_type'] ) || empty( $pt['label'] ) ) {
				continue;
			}

			$post_type = sanitize_key( $pt['post_type'] );
			$label     = sanitize_text_field( $pt['label'] );

			// Skip if sanitization resulted in empty values
			if ( empty( $post_type ) || empty( $label ) ) {
				continue;
			}

			// Check if post type is already registered
			$existing_pt = get_post_type_object( $post_type );

			if ( $existing_pt ) {
				continue;
			}

			// Register the post type
			register_post_type(
				$post_type,
				array(
					'label'               => $label,
					'exclude_from_search' => true,
					'public'              => true,
					'show_in_menu'        => false,
					'has_archive'         => false,
					'can_export'          => false,
					'delete_with_user'    => false,
				)
			);
		}
	}

	/**
	 * Prevents users from trashing posts and restoring them later to bypass the limitation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function limit_restoring(): void {

		// nonce is checked in a different action.
		if ( isset( $_REQUEST['action'] ) && 'untrash' === $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->limit_posts();
		}
	}

	/**
	 * Limit the posts after the user reach his plan limits
	 *
	 * @since 1.0.0
	 * @since 1.5.4 Checks for blocked post types
	 */
	public function limit_posts(): void {

		if ( is_main_site() ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! wu_get_current_site()->get_limitations()->post_types->{$screen->post_type}->enabled ) {
			$upgrade_message = __( 'Your plan does not support this post type.', 'ultimate-multisite' );

			wp_die( esc_html( $upgrade_message ), esc_html( __( 'Limit Reached', 'ultimate-multisite' ) ), array( 'back_link' => true ) );
		}

		// Check if that is more than our limit
		if ( wu_get_current_site()->get_limitations()->post_types->is_post_above_limit( $screen->post_type ) ) {
			$upgrade_message = __( 'You reached your plan\'s post limit.', 'ultimate-multisite' );

			wp_die( esc_html( $upgrade_message ), esc_html__( 'Limit Reached', 'ultimate-multisite' ), array( 'back_link' => true ) );
		}
	}

	/**
	 * Checks if the user is trying to publish a draft post.
	 *
	 * If that's the case, only allow him to do it if the post count is not above the quota.
	 *
	 * @since 1.7.0
	 * @param array $data Info being saved on posts.
	 * @param array $modified_data Data that is changing. We are interested in publish.
	 * @return array
	 */
	public function limit_draft_publishing( $data, $modified_data ) {

		global $current_screen;

		if ( empty( $current_screen ) ) {
			return $data;
		}

		if ( get_post_status( $modified_data['ID'] ) === 'publish' ) {
			return $data; // If the post is already published, no need to make changes

		}

		if ( isset( $data['post_status'] ) && 'publish' !== $data['post_status'] ) {
			return $data;
		}

		$post_type = $data['post_type'] ?? 'post';

		$post_type_limits = wu_get_current_site()->get_limitations()->post_types;

		if ( ! $post_type_limits->{$current_screen->post_type}->enabled || $post_type_limits->is_post_above_limit( $post_type ) ) {
			$data['post_status'] = 'draft';
		}

		return $data;
	}

	/**
	 * Limits uploads of items to the media library.
	 *
	 * @since 2.0.0
	 *
	 * @param array $file $_FILE array being passed.
	 * @return mixed
	 */
	public function limit_media( $file ) {

		if ( ! wu_get_current_site()->get_limitations()->post_types->attachment->enabled ) {
			$file['error'] = __( 'Your plan does not support media upload.', 'ultimate-multisite' );

			return $file;
		}

		$post_count = wp_count_posts( 'attachment' );

		$post_count = $post_count->inherit;

		$quota = wu_get_current_site()->get_limitations()->post_types->attachment->number;

		// This bit is for the flash uploader
		if ( 'application/octet-stream' === $file['type'] && isset( $file['tmp_name'] ) ) {
			$file_size = getimagesize( $file['tmp_name'] );

			if ( isset( $file_size['error'] ) && 0 !== $file_size['error'] ) {
				$file['error'] = "Unexpected Error: {$file_size['error']}";

				return $file;
			} else {
				$file['type'] = $file_size['mime'];
			}
		}

		if ( $quota > 0 && $post_count >= $quota ) {

			// translators: %d is the number of images allowed.
			$file['error'] = sprintf( __( 'You reached your media upload limit of %d images. Upgrade your account to unlock more media uploads.', 'ultimate-multisite' ), $quota, '#' );
		}

		return $file;
	}

	/**
	 * Handles post type enforcement after a membership product change (upgrade/downgrade).
	 *
	 * When a membership is downgraded to a plan with lower post-type quotas, any published
	 * posts that now exceed the new limit are moved to draft status. This preserves the
	 * content while preventing the site from exceeding its plan limits.
	 *
	 * Posts are demoted in ascending order of post ID (oldest first) so the most recent
	 * content remains published up to the quota.
	 *
	 * @since 2.2.0
	 *
	 * @param int $membership_id The membership that was updated.
	 * @return void
	 */
	public function handle_downgrade( $membership_id ): void {

		$membership = wu_get_membership( $membership_id );

		if ( ! $membership ) {
			return;
		}

		$sites = $membership->get_sites( false );

		foreach ( $sites as $site ) {

			$blog_id = $site->get_id();

			switch_to_blog( $blog_id );

			$post_type_limits = $membership->get_limitations()->post_types;

			if ( ! is_object( $post_type_limits ) || empty( $post_type_limits->get_limit() ) ) {
				restore_current_blog();
				continue;
			}

			$over_limit = $post_type_limits->check_all_post_types();

			foreach ( $over_limit as $post_type => $counts ) {

				$excess = $counts['current'] - $counts['limit'];

				if ( $excess <= 0 ) {
					continue;
				}

				// Fetch the oldest published posts to demote (ascending ID = oldest first).
				$posts_to_demote = get_posts(
					array(
						'post_type'      => $post_type,
						'post_status'    => array( 'publish', 'private' ),
						'posts_per_page' => $excess,
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'fields'         => 'ids',
					)
				);

				foreach ( $posts_to_demote as $post_id ) {

					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'draft',
						)
					);

					/**
					 * Fires after a post is demoted to draft due to a plan downgrade.
					 *
					 * @since 2.2.0
					 *
					 * @param int    $post_id       The post ID that was demoted.
					 * @param string $post_type     The post type.
					 * @param int    $blog_id       The site ID.
					 * @param int    $membership_id The membership ID.
					 */
					do_action( 'wu_post_type_downgrade_demoted', $post_id, $post_type, $blog_id, $membership_id );
				}
			}

			restore_current_blog();
		}
	}

	/**
	 * Remove the upload tabs if the quota is over.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tabs Tabs of the media gallery upload modal.
	 * @return array
	 */
	public function limit_tabs( $tabs ) {

		$post_count = wp_count_posts( 'attachment' );

		$post_count = $post_count->inherit;

		$quota = wu_get_current_site()->get_limitations()->post_types->attachment->number;

		if ( $quota > 0 && $post_count > $quota ) {
			unset( $tabs['type'] );

			unset( $tabs['type_url'] );
		}

		return $tabs;
	}
}
