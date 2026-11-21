<?php
/**
 * Account summary sign-in and site-selection guidance.
 *
 * @package WP_Ultimo
 * @subpackage Views
 * @since 2.18.0
 */

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- className is the shared block/template attribute.
?>
<div class="wu-styling <?php echo esc_attr($className); ?>">
	<div class="wu-p-4 wu-bg-gray-100 wu-rounded">
		<?php if ( ! is_user_logged_in()) : ?>
			<p><?php esc_html_e('Sign in to view your account and manage your sites.', 'ultimate-multisite'); ?></p>
			<a class="button" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>"><?php esc_html_e('Sign In', 'ultimate-multisite'); ?></a>
		<?php elseif ($sites) : ?>
			<p><?php esc_html_e('Select a site to view its account summary and limits.', 'ultimate-multisite'); ?></p>
			<ul class="wu-list-none wu-m-0 wu-p-0">
				<?php foreach ($sites as $customer_site) : ?>
					<li class="wu-my-2">
						<a href="<?php echo esc_url($element->get_manage_url($customer_site->get_id())); ?>"><?php echo esc_html($customer_site->get_title()); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e('No site is available in this account view.', 'ultimate-multisite'); ?></p>
			<a href="<?php echo esc_url(get_permalink()); ?>"><?php esc_html_e('Return to your account', 'ultimate-multisite'); ?></a>
		<?php endif; ?>
	</div>
</div>
