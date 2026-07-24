<?php
/**
 * Branding footer view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>

<div id="wp-ultimo-footer" class="wu-pt-6 wu-pb-1 wu--mx-5 wu-mb-0 wu-text-gray-500 wu-text-center wu-bg-gray-100 wu-border-0 wu-border-gray-300 wu-border-t wu-border-solid">

	<ul id="wu-footer-nav" class="wu-text-xs wu-pb-0">
	<li class="wu-inline-block wu-mx-1 wu-font-medium">
		<?php // translators: %s current version. ?>
		<?php printf(esc_html__('Version %s', 'ultimate-multisite'), esc_html(\WP_Ultimo::VERSION)); ?>
	</li>

	<?php if (WP_Ultimo()->is_loaded()) : ?>

		<li class="wu-inline-block wu-mx-1">
		<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-system-info')); ?>" class="wu-text-gray-500 hover:wu-text-gray-600">
			<?php esc_html_e('System Info', 'ultimate-multisite'); ?>
		</a>
		</li>
		<li class="wu-inline-block wu-mx-1">
		<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-shortcodes')); ?>" class="wu-text-gray-500 hover:wu-text-gray-600">
			<?php esc_html_e('Available Shortcodes', 'ultimate-multisite'); ?>
		</a>
		</li>

	<?php endif; ?>

	<?php if (WP_Ultimo()->is_loaded()) : ?>

		<li class="wu-inline-block wu-mx-1">
		<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-settings')); ?>" class="wu-text-gray-500 hover:wu-text-gray-600">
			<?php esc_html_e('Settings', 'ultimate-multisite'); ?>
		</a>
		</li>

	<?php endif; ?>

	<?php if (WP_Ultimo()->is_loaded()) : ?>

		<li class="wu-inline-block wu-mx-1">
		<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-jobs')); ?>" class="wu-text-gray-500 hover:wu-text-gray-600">
			<?php esc_html_e('Job Queue', 'ultimate-multisite'); ?>
		</a>
		</li>
		<li class="wu-inline-block wu-mx-1">
		<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-view-logs')); ?>" class="wu-text-gray-500 hover:wu-text-gray-600">
			<?php esc_html_e('View Logs', 'ultimate-multisite'); ?>
		</a>
		</li>

	<?php endif; ?>

	<?php do_action('wu_footer_left'); ?>
	<li class="wu-inline-block wu-mx-2">
		• <a href="https://ultimatemultisite.com/support" class="wu-text-gray-500 hover:wu-text-gray-600">Support</a>
	</li>
	</ul>

</div>
