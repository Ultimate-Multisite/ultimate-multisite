<?php
/**
 * Handles limitations to the customer user role.
 *
 * @package WP_Ultimo
 * @subpackage Limits
 * @since 2.0.10
 */

namespace WP_Ultimo\Limits;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles limitations to the customer user role.
 *
 * @since 2.0.0
 */
class Customer_User_Role_Limits {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Runs on the first and only instantiation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('in_admin_header', [$this, 'block_new_user_page']);

		add_action('wu_async_after_membership_update_products', [$this, 'update_site_user_roles']);

		add_filter('editable_roles', [$this, 'filter_editable_roles']);

		if ( ! wu_get_current_site()->has_module_limitation('customer_user_role')) {
			return;
		}
	}

	/**
	 * Block new user page if limit has reached.
	 *
	 * @since 2.0.20
	 */
	public function block_new_user_page(): void {

		if (is_super_admin()) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'user' !== $screen->id) {
			return;
		}

		if ( ! empty(get_editable_roles())) {
			return;
		}

		$message = __('You reached your membership users limit.', 'ultimate-multisite');

		/**
		 * Allow developers to change the message about the membership users limit
		 *
		 * @param string                      $message    The message to print in screen.
		 */
		$message = apply_filters('wu_users_membership_limit_message', $message);

		wp_die(esc_html($message), esc_html__('Limit Reached', 'ultimate-multisite'), ['back_link' => true]);
	}

	/**
	 * Filters editable roles offered as options on limitations.
	 *
	 * @since 2.0.10
	 *
	 * @param array $roles The list of available roles.
	 * @return array
	 */
	public function filter_editable_roles($roles) {
		if ( ! is_admin() || ! is_user_logged_in() ) {
			return $roles;
		}
		if ( ! wu_get_current_site()->has_module_limitation('users') || is_super_admin()) {
			return $roles;
		}

		$users_limitation = wu_get_current_site()->get_limitations()->users;

		foreach ($roles as $role => $details) {
			$limit = $users_limitation->{$role};

			if (property_exists($limit, 'enabled') && $limit->enabled) {
				$number = (int) $limit->number;

				if (0 === $number) {
					continue; // 0 is unlimited.
				}

				if ( ! isset($user_count)) {
					$user_count = count_users();
				}

				if (isset($user_count['avail_roles'][ $role ]) && $user_count['avail_roles'][ $role ] >= $number) {
					unset($roles[ $role ]);
				}
			} else {
				unset($roles[ $role ]);
			}
		}

		return $roles;
	}

	/**
	 * Updates the site user roles after a up/downgrade.
	 *
	 * @since 2.0.10
	 *
	 * @param int $membership_id The membership upgraded or downgraded.
	 * @return void
	 */
	public function update_site_user_roles($membership_id): void {

		$membership = wu_get_membership($membership_id);

		if ($membership) {
			$customer = $membership->get_customer();

			if ( ! $customer) {
				return;
			}

			$sites = $membership->get_sites(false);

			$role = $membership->get_limitations()->customer_user_role->get_limit();

			foreach ($sites as $site) {
				// only add user to blog if they are not already a member, or we are downgrading their role.
				// Without this check the user could lose additional roles added manually or with hooks.
				if ('administrator' !== $role || ! is_user_member_of_blog($customer->get_user_id(), $site->get_id())) {
					add_user_to_blog($site->get_id(), $customer->get_user_id(), $role);
				}
			}
		}
	}
}
