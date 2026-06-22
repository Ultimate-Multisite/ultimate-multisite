<?php
/**
 * Dash view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

$page_title = $page_title ?? (isset($page) && method_exists($page, 'get_title') ? $page->get_title() : '');

?>
<div id="wp-ultimo-wrap" class="<?php wu_wrap_use_container(); ?> wrap wu-styling">
	<h1 class="wp-heading-inline">

	<?php echo esc_html($page_title); ?>

	<?php
	/**
	 * You can filter the get_title_link using wu_page_list_get_title_link, see class-wu-page-list.php
	 *
	 * @since 1.8.2
	 */
	foreach ($page->get_title_links() as $action_link) :
		$action_classes = $action_link['classes'] ?? '';

		?>

		<a title="<?php echo esc_attr($action_link['label']); ?>" href="<?php echo esc_url($action_link['url']); ?>" class="page-title-action <?php echo esc_attr($action_classes); ?>">

		<?php if ($action_link['icon']) : ?>

			<span class="dashicons dashicons-<?php echo esc_attr($action_link['icon']); ?> wu-text-sm wu-align-middle wu-h-4 wu-w-4">
			&nbsp;
			</span>

		<?php endif; ?>

		<?php echo esc_html($action_link['label']); ?>

		</a>

	<?php endforeach; ?>

	<?php
	/**
	 * Allow plugin developers to add additional buttons to list pages
	 *
	 * @since 1.8.2
	 * @param WU_Page $page Ultimate Multisite Page instance
	 */
	do_action('wu_page_dash_after_title', $page);
	?>

	</h1>
	<hr class="wp-header-end">

	<?php
	/*
	 * Render the updated/notice message exposed to this view via $labels.
	 *
	 * Mirrors the pattern in views/base/edit.php and views/base/centered.php:
	 * customer-panel pages that use dash.php (e.g. Switch Template) set
	 * $hide_admin_notices = true on the page class, which suppresses the
	 * standard WordPress success notice. Without rendering the label here
	 * the customer never sees confirmation that their action succeeded —
	 * the page redirects to ?updated=1 and looks identical to the pre-action
	 * state, which reads as "nothing happened".
	 *
	 * Guarded behind isset() so the dozen other pages that use dash.php
	 * without supplying a $labels array (Dashboard, Account, My Sites,
	 * Add New Site, Debug) keep working unchanged.
	 */
	?>
	<?php if (! empty($labels['updated_message']) && isset($_GET['updated'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div id="message" class="updated notice wu-admin-notice notice-success is-dismissible below-h2">
			<p><?php echo esc_html($labels['updated_message']); ?></p>
		</div>
	<?php endif; ?>

	<?php do_action('wu_dash_before_metaboxes', $page); ?>
	<?php if (apply_filters('wu_dashboard_display_widgets', true)) : ?>

	<div id="dashboard-widgets-wrap">

		<div id="dashboard-widgets" class="metabox-holder">

			<?php if ($has_full_position) : ?>

				<div id="postbox-container" class="postbox-container wu-w-full wu--mb-5" style="width: 100% !important;">
					<?php
					/**
					 * Print Advanced Metaboxes
					 *
					 * Allow plugin developers to add new metaboxes
					 *
					 * @since 1.8.2
					 * @param object Object being edited right now
					 */
					do_meta_boxes($screen->id, 'full', null);
					?>
				</div>

				<div class="wu-mx-2">

					<?php do_action('wu_dash_after_full_metaboxes', $page); ?>

				</div>

			<?php endif; ?>

			<div class="sm:wu-grid md:wu-grid-cols-2 xl:wu-grid-cols-3">

				<div id="postbox-container" class="wu-postbox-container">
					<?php
					/**
					 * Print Advanced Metaboxes
					 *
					 * Allow plugin developers to add new metaboxes
					 *
					 * @since 1.8.2
					 * @param object Object being edited right now
					 */
					do_meta_boxes($screen->id, 'normal', null);
					?>
				</div>

				<div id="postbox-container" class="wu-postbox-container">
					<?php
					/**
					 * Print Advanced Metaboxes
					 *
					 * Allow plugin developers to add new metaboxes
					 *
					 * @since 1.8.2
					 * @param object Object being edited right now
					 */
					do_meta_boxes($screen->id, 'side', null);
					?>
				</div>

				<div id="postbox-container" class="wu-postbox-container">
					<?php
					/**
					 * Print Advanced Metaboxes
					 *
					 * Allow plugin developers to add new metaboxes
					 *
					 * @since 1.8.2
					 * @param object Object being edited right now
					 */
					do_meta_boxes($screen->id, 'column3', null);
					?>
				</div>

			</div>

		</div>

		<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false); ?>

		<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false); ?>

	</div>

	<!-- dashboard-widgets-wrap -->

	<?php endif; ?>

</div>
