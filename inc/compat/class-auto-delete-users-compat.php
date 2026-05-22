<?php
/**
 * Adds support for automatically deleting users from the network when removed from a site.
 *
 * WordPress, even in multisite mode, has only one User database table.
 * This can cause problems in a WaaS environment.
 *
 * When a site owner removes a user from their site, the user remains in the network.
 * This makes it possible for site owners to completely delete users when they are
 * removed from a site, but only if they are not a member of any other site.
 *
 * @package WP_Ultimo
 * @subpackage Compat/Auto_Delete_Users_Compat
 * @since 2.4.5
 */

namespace WP_Ultimo\Compat;

/**
 * Auto Delete Users compatibility class.
 *
 * Automatically deletes users from the network when they are removed from a site
 * and are not members of any other sites in the network.
 */
class Auto_Delete_Users_Compat {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Initialize the Auto Delete Users compatibility functionality.
	 *
	 * Sets up hooks and actions to enable automatic user deletion
	 * and adds settings to control this feature.
	 *
	 * @since 2.4.5
	 */
	public function init(): void {

		if ( defined( 'WU_MT_SOVEREIGN_TENANT' ) && WU_MT_SOVEREIGN_TENANT ) {
			return;
		}

		// Add the settings to enable or disable this feature.
		add_action('wu_settings_login', [$this, 'add_settings'], 10);

		if ($this->should_load()) {
			// Hook into user removal from site.
			add_action('remove_user_from_blog', [$this, 'handle_user_removal'], 10, 2);
		}
	}

	/**
	 * Handle user removal from a site.
	 *
	 * When a user is removed from a site, check if they are a member of any other sites.
	 * If not, delete them from the network completely.
	 *
	 * @since 2.4.5
	 *
	 * @param int $user_id The user ID being removed.
	 * @param int $blog_id The blog ID from which the user is being removed.
	 */
	public function handle_user_removal(int $user_id, int $blog_id): void {
		// Don't delete super admins.
		if (is_super_admin($user_id)) {
			return;
		}

		// Don't delete during user activation process.
		// WordPress core removes users from the main blog before adding to target blog.
		if ($this->is_user_activation_in_progress($user_id, $blog_id)) {
			return;
		}

		// Allow developers to bypass auto-deletion in specific scenarios.
		if (apply_filters('wu_bypass_auto_delete_user', false, $user_id, $blog_id)) {
			return;
		}

		// Get all blogs the user is a member of.
		$blogs = get_blogs_of_user($user_id);

		// Remove the current blog from the list since the user is being removed from it.
		unset($blogs[ $blog_id ]);

		// If user is not a member of any other sites, delete them from the network.
		if (empty($blogs)) {
			// Remove the hook to prevent infinite loop when wpmu_delete_user calls remove_user_from_blog.
			remove_action('remove_user_from_blog', [$this, 'handle_user_removal'], 10);

			require_once ABSPATH . 'wp-admin/includes/user.php';
			wpmu_delete_user($user_id);
		}
	}

	/**
	 * Check if we're in the middle of a user activation process.
	 *
	 * WordPress core's add_new_user_to_blog() removes users from the main blog
	 * before adding them to their target blog during activation. We detect this
	 * by checking if the user exists in the wp_signups table.
	 *
	 * @since 2.4.5
	 *
	 * @param int $user_id The user ID being removed.
	 * @param int $blog_id The blog ID from which the user is being removed.
	 * @return bool True if this is likely a user activation, false otherwise.
	 */
	protected function is_user_activation_in_progress(int $user_id, int $blog_id): bool {
		global $wpdb;

		// Only check if being removed from the main network site.
		$main_site_id = get_network()->site_id;

		if ($blog_id !== $main_site_id) {
			return false;
		}

		// Check if user exists in signups table.
		$user = get_userdata($user_id);

		if (! $user) {
			return false;
		}

		$signup_login = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_login FROM {$wpdb->signups} WHERE user_login = %s",
				$user->user_login
			)
		);

		return ! empty($signup_login);
	}

	/**
	 * Allow plugin developers to disable this functionality to prevent compatibility issues.
	 *
	 * @since 2.4.5
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		return apply_filters('wu_should_load_auto_delete_users', wu_get_setting('enable_auto_delete_users', false));
	}

	/**
	 * Add auto delete users setting to enable or disable this feature.
	 *
	 * @since 2.4.5
	 *
	 * @return void
	 */
	public function add_settings(): void {
		wu_register_settings_field(
			'login-and-registration',
			'enable_auto_delete_users',
			[
				'title'   => __('Enable Auto Delete Users', 'ultimate-multisite'),
				'desc'    => __('Automatically delete users from the network when they are removed from a site and are not members of any other sites.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);
	}
}
