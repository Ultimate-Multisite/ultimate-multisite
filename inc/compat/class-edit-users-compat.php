<?php
/**
 * Adds support to for site owners to edit user accounts for users on their site.
 *
 * WordPress, even in multisite mode, has only one User database table.
 * This can cause problems in a WaaS environment.
 *
 * A site owner with administrator role wants to edit the display name of a subscriber.
 * In the default Multisite only super admins can edit user accounts.
 * This makes it possible for Admins to edit users in their own sites.
 *
 * @package WP_Ultimo
 * @subpackage Compat/Edit_Users_Compat
 * @since 2.4.4
 */

namespace WP_Ultimo\Compat;

/**
 * Edit Users compatibility class.
 *
 * Allows site administrators to edit user accounts for users on their site
 * in multisite environments where normally only super admins can edit users.
 */
class Edit_Users_Compat {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Initialize the Edit Users compatibility functionality.
	 *
	 * Sets up hooks and actions to enable site administrators to edit users
	 * and adds settings to control this feature.
	 *
	 * @since 2.4.4
	 */
	public function init(): void {

		if ( defined( 'WU_MT_SOVEREIGN_TENANT' ) && WU_MT_SOVEREIGN_TENANT ) {
			return;
		}

		// Add the settings to enable or disable this feature.
		add_action('wu_settings_login', [$this, 'add_settings'], 10);

		if ($this->should_load()) {
			// Apply the update_users_caps function to the 'map_meta_cap' filter.
			add_filter('map_meta_cap', [$this, 'update_users_caps'], 1, 4);

			// Add a filter to enable editing any user configuration.
			add_filter('enable_edit_any_user_configuration', '__return_true', 15);

			// Add hooks to allow site admins to add users without confirmation emails.
			add_action('user_new_form', [$this, 'add_noconfirmation_field'], 10);
			add_action('admin_action_adduser', [$this, 'handle_adduser_action'], 5);
			add_action('admin_action_createuser', [$this, 'handle_adduser_action'], 5);
		}
	}

	/**
	 * Allows subsite administrators to edit users in a WordPress Multisite network.
	 *
	 * In a WordPress 3.x Network, the Super Admin role is the only role allowed to edit users.
	 *
	 * @param array  $caps    The user's capabilities.
	 * @param string $cap     The capability being checked.
	 * @param int    $user_id The user ID.
	 * @param mixed  $args    Additional arguments.
	 *
	 * @return array Modified user capabilities.
	 */
	public function update_users_caps($caps, $cap, $user_id, $args) {
		foreach ($caps as $key => $capability) {
			if ('do_not_allow' !== $capability) {
				continue;
			}

			switch ( $cap ) {
				case 'edit_user':
					// Editing a user profile.
					if (empty($args[0]) || is_super_admin($args[0])) {
						// Trying to edit a super admin while not being a super admin.
						$caps[] = 'do_not_allow';
					} elseif ( ! is_user_member_of_blog($args[0], get_current_blog_id()) || ! is_user_member_of_blog($user_id, get_current_blog_id())) {
						// Editing user and edited user aren't members of the same blog.
						$caps[] = 'do_not_allow';
					} else {
						$caps[ $key ] = 'edit_users';
					}

					break;
				case 'edit_users':
					$caps[ $key ] = 'edit_users';
					break;
				case 'delete_user':
				case 'delete_users':
					$caps[ $key ] = 'delete_users';
					break;
				case 'create_users':
					$caps[ $key ] = $cap;
					break;
			}
		}

		return $caps;
	}

	/**
	 * Allow plugin developers to disable this functionality to prevent compatibility issues.
	 *
	 * @since 2.0.0
	 *
	 * @return boolean
	 */
	public function should_load() {

		return apply_filters('wu_should_load_edit_user_support', wu_get_setting('enable_edit_users', false));
	}

	/**
	 * Add edit user setting to enable or disable this feature.
	 *
	 * @since 2.0.0
	 *
	 * @return void.
	 */
	public function add_settings(): void {

		wu_register_settings_field(
			'login-and-registration',
			'enable_edit_users',
			[
				'title'   => __('Enable Edit User Capability', 'ultimate-multisite'),
				'desc'    => __('Allow site owners to edit the user accounts of users on their own site. Also lets site owners create user accounts without requiring email confirmation.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);
	}

	/**
	 * Add the "Skip Confirmation Email" checkbox for site administrators on the Add Existing User form.
	 *
	 * @since 2.4.4
	 *
	 * @param string $context The form context ('add-existing-user' or 'add-new-user').
	 */
	public function add_noconfirmation_field($context) {
		// Only show for existing users form and only for site admins (not network admins)
		if (current_user_can('manage_network_users')) {
			return;
		}

		// Only show if user can create users (site admin capability)
		if (! current_user_can('create_users')) {
			return;
		}

		?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Skip Confirmation Email', 'ultimate-multisite'); ?></th>
					<td>
						<input type="checkbox" name="noconfirmation" id="adduser-noconfirmation-<?php echo esc_attr($context); ?>" value="1" />
						<label for="adduser-noconfirmation-<?php echo esc_attr($context); ?>"><?php esc_html_e('Add the user without sending an email that requires their confirmation', 'ultimate-multisite'); ?></label>
					</td>
				</tr>
			</table>
		<?php
	}

	/**
	 * Handle the adduser action to allow site admins to add users without confirmation.
	 *
	 * @since 2.4.4
	 */
	public function handle_adduser_action() {
		global $pagenow;

		// Only proceed if this is not a network admin and noconfirmation is checked
		if (current_user_can('manage_network_users') || 'user-new.php' !== $pagenow) {
			return;
		}

		// Temporarily grant network user management capability for this request
		add_filter('user_has_cap', [$this, 'grant_temp_network_capability'], 10, 3);
	}

	/**
	 * Temporarily grant manage_network_users capability to site admins for adding users without confirmation.
	 *
	 * @since 2.4.4
	 *
	 * @param array $allcaps All capabilities of the user.
	 * @param array $caps    Required capabilities.
	 * @param array $args    Arguments that accompany the requested capability check.
	 * @return array Modified capabilities.
	 */
	public function grant_temp_network_capability($allcaps, $caps, $args) {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Only grant during adduser action and if user can create users
		if (! empty($allcaps['create_users']) && ! isset($allcaps['manage_network_users'])) {
			$allcaps['manage_network_users'] = true;
		}

		return $allcaps;
	}
}
