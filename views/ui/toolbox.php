<?php
/**
 * Displays the Toolbox UI.
 *
 * @package WP_Ultimo/Views
 * @subpackage Toolbox
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>

<div id="wu-toolbox" class="wu-styling">

	<div
	class="wu-fixed wu-bottom-0 wu-right-0 wu-mr-4 wu-mb-4 wu-bg-white wu-px-4 wu-py-2 wu-pl-2 wu-shadow-md wu-rounded-full wu-uppercase wu-text-xs wu-text-gray-700">

	<ul class="wu-inline-block wu-m-0 wu-p-0 wu-align-middle wu-mx-1">
		<li class="wu-inline-block wu-m-0 wu-p-0">
		<span id="wu-toolbox-toggle" class="dashicons-before dashicons-wu-wp-ultimo" <?php wu_tooltip_text(__('Toggle Toolbox', 'ultimate-multisite')); ?>></span>
		</li>
	</ul>

	<ul id="wu-toolbox-links" class="wu-inline-block wu-m-0 wu-p-0 wu-align-middle wu-mx-1">

		<li class="wu-inline-block wu-m-0 wu-p-0 wu-px-2">

		<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-edit-site', ['id' => $current_site->get_id()])); ?>"
			class="wu-inline-block wu-uppercase wu-text-gray-600 wu-no-underline">
			<span title="<?php esc_attr_e('Current Site', 'ultimate-multisite'); ?>"
			class="dashicons-wu-browser wu-text-sm wu-w-auto wu-h-auto wu-align-text-bottom wu-relative"></span>
			<span class="">
			<?php echo esc_html($current_site->get_title()); ?>
			</span>
		</a>

		</li>

		<?php if ($customer) : ?>

		<li class="wu-inline-block wu-m-0 wu-p-0 wu-px-2">

			<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-edit-customer', ['id' => $customer->get_id()])); ?>"
			class="wu-inline-block wu-uppercase wu-text-gray-600 wu-no-underline">
			<span title="<?php esc_attr_e('Current Site', 'ultimate-multisite'); ?>"
				class="dashicons-wu-user wu-text-sm wu-w-auto wu-h-auto wu-align-text-bottom wu-relative"></span>
			<span class="">
				<?php echo esc_html($customer->get_display_name()); ?>
			</span>
			</a>

		</li>

		<?php endif; ?>

		<?php if ($membership) : ?>

		<li class="wu-inline-block wu-m-0 wu-p-0 wu-px-2">

			<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-edit-membership', ['id' => $membership->get_id()])); ?>"
			class="wu-inline-block wu-uppercase wu-text-gray-600 wu-no-underline">
			<span title="<?php esc_attr_e('Current Site', 'ultimate-multisite'); ?>"
				class="dashicons-wu-circular-graph wu-text-sm wu-w-auto wu-h-auto wu-align-text-bottom wu-relative"></span>
			<span class="">
				<?php // translators: %s hash of membership. ?>
				<?php printf(wp_kses_post(__('Membership <strong>%s</strong>', 'ultimate-multisite')), esc_html($membership->get_hash())); ?>
			</span>
			<span id="wu-toolbox-membership-status" class="wu-inline-block wu-w-3 wu-h-3 wu-rounded-full wu-align-text-top <?php echo esc_attr($membership->get_status_class()); ?>" <?php wu_tooltip_text($membership->get_status_label()); ?>>
				&nbsp;
			</span>
			</a>

		</li>

		<?php endif; ?>

	</ul>

	<ul class="wu-inline-block wu-m-0 wu-p-0 wu-align-middle wu-mx-1">
		<li class="wu-inline-block wu-m-0 wu-p-0">

		<a id="wu-jumper-button-trigger" href="#"
			class="wu-inline-block wu-uppercase wu-text-gray-600 wu-no-underline">
			<span title="<?php esc_attr_e('Jumper', 'ultimate-multisite'); ?>"
			class="dashicons dashicons-wu-flash wu-text-sm wu-w-auto wu-h-auto wu-align-text-top wu-relative wu--mr-1"></span>
			<span class="wu-font-bold">
			<?php esc_attr_e('Jumper', 'ultimate-multisite'); ?>
			</span>
		</a>

		</li>
	</ul>

	</div>

</div>

<?php
wp_enqueue_style('wu-toolbox', wu_get_asset('toolbox.css', 'css'), [], wu_get_version());
wp_enqueue_script('wu-toolbox', wu_get_asset('toolbox.js', 'js'), ['jquery'], wu_get_version(), true);
?>
