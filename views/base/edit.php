<?php
/**
 * Edit view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div id="wp-ultimo-wrap" class="<?php wu_wrap_use_container(); ?> wrap">

	<h1 class="wp-heading-inline">
		<?php echo esc_html($page->edit ? $labels['edit_label'] : $labels['add_new_label']); ?>

		<?php foreach ($page->get_title_links() as $action_link) : ?>
			<a title="<?php echo esc_attr($action_link['label']); ?>" href="<?php echo esc_url($action_link['url']); ?>" class="page-title-action <?php echo esc_attr($action_link['classes'] ?? ''); ?>"
			<?php
			if (! empty($action_link['attrs'])) {
				if (is_array($action_link['attrs'])) {
					foreach ($action_link['attrs'] as $attr_name => $attr_value) {
						printf(' %s="%s"', esc_attr($attr_name), esc_attr($attr_value));
					}
				} else {
					echo ' ' . $action_link['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped attributes
				}
			}
			?>
		>
				<?php if ($action_link['icon']) : ?>
					<span class="dashicons dashicons-<?php echo esc_attr($action_link['icon']); ?> wu-text-sm wu-align-middle wu-h-4 wu-w-4">&nbsp;</span>
				<?php endif; ?>
				<?php echo esc_html($action_link['label']); ?>
			</a>
		<?php endforeach; ?>

		<?php do_action('wu_page_edit_after_title', $object, $page); ?>
	</h1>

	<?php if (isset($_GET['updated'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div id="message" class="updated notice wu-admin-notice notice-success is-dismissible below-h2">
			<p><?php echo esc_html($labels['updated_message']); ?></p>
		</div>
	<?php endif; ?>

	<?php if (isset($_GET['notice'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div id="message" class="updated notice wu-admin-notice notice-success is-dismissible below-h2">
			<p><?php echo esc_html($labels['updated_message']); ?></p>
		</div>
	<?php endif; ?>

	<?php do_action('wu_page_edit_redirect_handlers', $page); ?>

	<hr class="wp-header-end">

	<form id="form-<?php echo esc_attr($page->get_id()); ?>" name="post" method="post" autocomplete="off">
		<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false); ?>
		<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false); ?>
		<?php wp_nonce_field(sprintf('saving_%s', $page->object_id), sprintf('saving_%s', $page->object_id), false); ?>
		<?php wp_nonce_field(sprintf('saving_%s', $page->object_id), '_wpultimo_nonce'); ?>
		<?php if ($page->edit) : ?>
			<?php wp_nonce_field(sprintf('deleting_%s', $page->object_id), sprintf('deleting_%s', $page->object_id), false); ?>
			<?php wp_nonce_field(sprintf('deleting_%s', $page->object_id), 'delete_wpultimo_nonce'); ?>
			<input type="hidden" name="id" value="<?php echo esc_attr($object->get_id()); ?>">
		<?php endif; ?>

	<div id="poststuff">

		<div id="post-body" class="metabox-holder columns-2">

		<?php if ($page->has_title()) : ?>

			<div id="post-body-content">

			<div id="titlediv">

				<div id="titlewrap">

				<input placeholder="<?php echo esc_attr($labels['title_placeholder']); ?>" type="text" name="name" size="30" value="<?php echo method_exists($object, 'get_name') ? esc_attr($object->get_name()) : ''; ?>" id="title" spellcheck="true" autocomplete="off">

				<?php if ( ! empty($labels['title_description'])) : ?>

					<span class="wu-block wu-bg-gray-100 wu-rounded wu-border-solid wu-border-gray-400 wu-border-t-0 wu-border-l wu-border-b wu-border-r wu-text-xs wu-py-2 wu-p-2 wu-pt-3 wu--mt-2">
					<?php echo esc_html($labels['title_description']); ?>
					</span>

				<?php endif; ?>

				<?php
				/**
				 * Allow plugin developers to add additional information below the text input
				 *
				 * @since 1.8.2
				 * @param object  $object Object holding the information
				 * @param WU_Page $page Ultimate Multisite Page instance
				 */
				do_action('wu_edit_page_after_title_input', $object, $page);
				?>

				</div>

			</div>
			<!-- /titlediv -->

			<?php if ($page->has_editor()) : ?>

			<div class="wu-mt-5">

				<?php remove_editor_styles(); ?>

				<?php $content = method_exists($object, 'get_content') ? esc_attr($object->get_content()) : ''; ?>
				<?php
				wp_editor(
					html_entity_decode($content),
					'content',
					[
						'height' => 500,
					]
				);
				?>

			</div>

			<?php endif; ?>

			</div>
			<!-- /post-body-content -->

		<?php endif; ?>

		<div id="postbox-container-1" class="postbox-container">

			<?php
			/**
			 * Print Side Metaboxes
			 *
			 * Allow plugin developers to add new metaboxes
			 *
			 * @since 1.8.2
			 * @param object $object Object being edited right now
			 */
			do_meta_boxes($screen->id, 'side', $object);
			?>

			<?php
			/**
			 * Print Side Metaboxes
			 *
			 * Allow plugin developers to add new metaboxes
			 *
			 * @since 1.8.2
			 * @param object $object Object being edited right now
			 */
			do_meta_boxes($screen->id, 'side-bottom', $object);
			?>

		</div>

		<div id="postbox-container-2" class="postbox-container">

			<?php

			/**
			 * Print Normal Metaboxes
			 *
			 * Allow plugin developers to add new metaboxes
			 *
			 * @since 1.8.2
			 * @param object $object Object being edited right now
			 */
			do_meta_boxes($screen->id, 'normal', $object);

			/**
			 * Allow developers to add additional elements after the modals are printed.
			 *
			 * @since 2.0.0
			 * @param object $object Object being edited right now
			 */
			do_action("wu_edit_{$screen->id}_after_normal", $object);

			/**
			 * Print Advanced Metaboxes
			 *
			 * Allow plugin developers to add new metaboxes
			 *
			 * @since 1.8.2
			 * @param object $object Object being edited right now
			 */
			do_meta_boxes($screen->id, 'advanced', $object);

			?>

		</div>
		<!-- /normal-sortables -->

		</div>
		<!-- /post-body -->

		<br class="clear">

		<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false); ?>

		<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false); ?>

		<?php wp_nonce_field(sprintf('saving_%s', $page->object_id), sprintf('saving_%s', $page->object_id), false); ?>

		<?php wp_nonce_field(sprintf('saving_%s', $page->object_id), '_wpultimo_nonce'); ?>

		<?php if ($page->edit) : ?>
			<?php wp_nonce_field(sprintf('deleting_%s', $page->object_id), sprintf('deleting_%s', $page->object_id), false); ?>

			<?php wp_nonce_field(sprintf('deleting_%s', $page->object_id), 'delete_wpultimo_nonce'); ?>

			<input type="hidden" name="id" value="<?php echo esc_attr($object->get_id()); ?>">

		<?php endif; ?>

	</div>
	<!-- /poststuff -->

	</form>

	<?php
	/**
	 * Allow plugin developers to add scripts to the bottom of the page
	 *
	 * @since 1.8.2
	 * @param object  $object Object holding the information
	 * @param WU_Page $page Ultimate Multisite Page instance
	 */
	do_action('wu_page_edit_footer', $object, $page);
	?>

</div>
