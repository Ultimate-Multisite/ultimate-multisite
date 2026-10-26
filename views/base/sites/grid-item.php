<?php
/**
 * Grid item view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div class="wu-border-transparent" tabindex="0">

	<div class="wu-grid-item wu-border wu-border-solid wu-border-gray-300 wu-pb-8 wu-bg-white">

	<div class="wu-relative wu-bg-gray-100" style="max-height: 220px; overflow: hidden;">

		<img
		style="opacity: 0.6;"
		class="wu-w-full wu-h-auto wu-image-preview"
		data-image="<?php echo esc_attr($item->get_featured_image('large')); ?>"
		src="<?php echo esc_attr($item->get_featured_image('wu-thumb-medium')); ?>"
		/>

		<?php if (current_user_can('wu_read_sites')) : ?>

		<div class="wu-my-4 wu-mx-3 wu-inline-block wu-absolute wu-bottom-0 wu-right-0 wu-rounded wu-px-2 wu-py-1 wu-uppercase wu-text-xs wu-font-bold <?php echo esc_attr($item->get_type_class()); ?>">
			<?php echo esc_html($item->get_type_label()); ?>
		</div>

		<?php endif; ?>

	</div>

	<div class="wu-text-base wu-px-3 wu-my-3">

		<div>
		<span class="wu-font-semibold"><?php echo esc_html($item->get_title()); ?></span>
		<small><?php echo esc_html($item->get_id() ? '#' . $item->get_id() : ''); ?></small>
		</div>

		<div class="wu-text-xs wu-my-1">
		<a class="wu-no-underline" href="<?php echo esc_url($item->get_active_site_url()); ?>"><?php echo esc_url($item->get_active_site_url()); ?></a>
		</div>

	</div>

	<div class="wu-flex wu-justify-between wu-items-center wu--mb-8 wu-p-4 wu-bg-gray-100 wu-border wu-border-solid wu-border-gray-300 wu-border-l-0 wu-border-r-0 wu-border-b-0">

		<?php if ($item->get_type() !== 'main') : ?>

			<?php if ($item->get_type() === 'pending') : ?>

				<?php
				/*
				* Pending checkboxes carry membership IDs. Only the dedicated
				* Pending view submits them to `delete-pending`; in any other view
				* the bulk action is the regular site `delete`, which treats every
				* value as a blog ID and would delete an unrelated live site whose
				* blog ID equals the membership ID. Mirrors Site_List_Table::column_cb().
				*/
				?>
				<?php if ('pending' === wu_request('type')) : ?>

			<label>
				<input class="wu-rounded-none" type="checkbox" name="bulk-delete[]" value="<?php echo esc_attr($item->get_membership_id()); ?>" />
					<?php esc_html_e('Select Site', 'ultimate-multisite'); ?>
			</label>

			<?php else : ?>

			<span>&nbsp;</span>

			<?php endif; ?>

			<a title="<?php echo esc_attr(__('Publish pending site', 'ultimate-multisite')); ?>" href="<?php echo esc_attr(wu_get_form_url('publish_pending_site', ['membership_id' => $item->get_membership_id()])); ?>" class="wubox button button-primary">
				<?php esc_html_e('Publish Site', 'ultimate-multisite'); ?>
			</a>

			<?php else : ?>

			<label>
				<input class="wu-rounded-none" type="checkbox" name="bulk-delete[]" value="<?php echo esc_attr($item->get_id()); ?>" />
				<?php esc_html_e('Select Site', 'ultimate-multisite'); ?>
			</label>

			<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-edit-site', ['id' => $item->get_id()])); ?>" class="button button-primary">
				<?php esc_html_e('Manage', 'ultimate-multisite'); ?>
			</a>

			<?php endif; ?>

		<?php else : ?>

			<span>&nbsp;</span>

			<a href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-edit-site', ['id' => $item->get_id()])); ?>" class="button button-primary">
			<?php esc_html_e('See Main Site', 'ultimate-multisite'); ?>
			</a>

		<?php endif; ?>

	</div>
	</div>
</div>
