<?php
/**
 * Settings view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div id="wp-ultimo-wrap" class="<?php wu_wrap_use_container(); ?> wrap wu-wrap <?php echo esc_attr($classes); ?>">

	<h1 class="wp-heading-inline">

	<?php echo esc_html($page->get_title()); ?>

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
	do_action('wu_page_wizard_after_title', $page);
	?>

	</h1>

	<?php if (wu_request('updated')) : ?>

	<div id="message" class="updated notice wu-admin-notice notice-success is-dismissible below-h2">
		<p><?php esc_html_e('Settings successfully saved.', 'ultimate-multisite'); ?></p>
	</div>

	<?php endif; ?>

	<hr class="wp-header-end">

	<form method="post">

	<div id="poststuff" class="sm:wu-grid sm:wu-grid-cols-12 wu-gap-4">

		<div class="sm:wu-col-span-4 lg:wu-col-span-2">

		<div id="wu-settings-search-box" class="wu-py-4 wu-relative">

			<input
			data-model='setting'
			data-value-field="setting_id"
			data-label-field="title"
			data-search-field="setting_id"
			data-max-items="1"
			selected type="text"
			placeholder="Search Setting"
			class="wu-w-full"
			>

		</div>

		<style>
		#wu-settings-search-box .selectize-dropdown {
			width: max-content !important;
		}
		#wu-settings-search-box .selectize-dropdown-content {
			max-height: 70vh;
		}
		</style>

		<div data-wu-app="settings_menu" data-state="{}">

			<!-- Navigator -->
			<ul>

			<li class="md:wu-hidden wu-p-4 wu-font-bold wu-uppercase wu-text-xs wu-text-gray-700">

				<?php esc_html_e('Menu', 'ultimate-multisite'); ?>

			</li>

			<?php

			/**
			 * We need to set a couple of flags in here to control clickable navigation elements.
			 * This flag makes sure only steps the user already went through are clickable.
			 */
			$is_pre_current_section = true;

			/**
			 * Holds add-on menus
			 */
			$addons = [];

			?>

			<?php foreach ($sections as $section_name => $section) : ?>

				<?php

				if (wu_get_isset($section, 'invisible')) {
					continue; // skip add-ons for now.

				}

				if (wu_get_isset($section, 'addon')) {
					$addons[ $section_name ] = $section;

					continue; // skip add-ons for now.

				}

				/**
				 * Updates the flag after the current section is looped.
				 */
				if ($current_section === $section_name) {
					$is_pre_current_section = false;
				}

				?>

				<!-- Menu Item -->
				<li id="tab-selector-<?php echo esc_attr($section_name); ?>" class="wu-sticky">

				<!-- Menu Link -->
				<a
					id="tab-selector-<?php echo esc_attr($section_name); ?>-link"
					href="<?php echo esc_url($page->get_section_link($section_name)); ?>" 
					class="wu-block wu-py-2 wu-no-underline wu-text-sm wu-rounded <?php echo ! $clickable_navigation && ! $is_pre_current_section ? 'wu-pointer-events-none' : ''; ?> <?php echo $current_section === $section_name ? 'wu-bg-gray-300 wu-text-gray-800' : 'wu-text-gray-600 hover:wu-text-gray-700'; ?>"
				>

					<span class="<?php echo esc_attr($section['icon']); ?> wu-align-text-bottom wu-mr-1"></span>

					<?php echo esc_html($section['title']); ?>

				</a>
				<!-- End Menu Link -->

				<?php if ( ! empty($section['sub-sections'])) : ?>

					<!-- Sub-menu -->
					<ul class="classes" v-show="false" v-cloak>

					<?php foreach ($section['sub-sections'] as $sub_section_name => $sub_section) : ?>

						<li class="classes">
						<a href="<?php echo esc_url($page->get_section_link($section_name) . '#' . $sub_section_name); ?>" class="wu-block wu-py-2 wu-px-4 wu-no-underline wu-text-gray-500 hover:wu-text-gray-600 wu-text-sm">
							&rarr; <?php echo esc_html($sub_section['title']); ?>
						</a>
						</li>

					<?php endforeach; ?>

					</ul>
					<!-- End Sub-menu -->

				<?php endif; ?>

				</li>
				<!-- End Menu Item -->

			<?php endforeach; ?>

			</ul>
			<!-- End Navigator -->

			<?php if ( ! empty($addons)) : ?>

			<!-- Addon Navigator -->
			<ul class="wu-pt-4">

				<li class="wu-px-4 wu-font-bold wu-uppercase wu-text-xs wu-text-gray-700">
				<?php esc_html_e('Add-ons', 'ultimate-multisite'); ?>
				</li>

				<?php foreach ($addons as $section_name => $section) : ?>

					<?php

					/**
					 * Updates the flag after the current section is looped.
					 */
					if ($current_section === $section_name) {
						$is_pre_current_section = false;
					}

					?>

				<!-- Menu Item -->
				<li class="wu-sticky">

					<!-- Menu Link -->
					<a href="<?php echo esc_url($page->get_section_link($section_name)); ?>" class="wu-block wu-py-2 wu-no-underline wu-text-sm wu-rounded <?php echo ! $clickable_navigation && ! $is_pre_current_section ? 'wu-pointer-events-none' : ''; ?> <?php echo $current_section === $section_name ? 'wu-bg-gray-300 wu-text-gray-800' : 'wu-text-gray-600 hover:wu-text-gray-700'; ?>">

					<span class="<?php echo esc_attr($section['icon']); ?> wu-align-text-bottom wu-mr-1"></span>

					<?php echo esc_html($section['title']); ?>

					</a>
					<!-- End Menu Link -->

					<?php if ( ! empty($section['sub-sections'])) : ?>

					<!-- Sub-menu -->
					<ul class="classes" v-show="false" v-cloak>

						<?php foreach ($section['sub-sections'] as $sub_section_name => $sub_section) : ?>

						<li class="classes">
							<a href="<?php echo esc_url($page->get_section_link($section_name) . '#' . $sub_section_name); ?>" class="wu-block wu-py-2 wu-px-4 wu-no-underline wu-text-gray-500 hover:wu-text-gray-600 wu-text-sm">
							&rarr; <?php echo esc_html($sub_section['title']); ?>
							</a>
						</li>

						<?php endforeach; ?>

					</ul>
					<!-- End Sub-menu -->

					<?php endif; ?>

				</li>
				<!-- End Menu Item -->

				<?php endforeach; ?>

			</ul>
			<!-- End Addon Navigator -->

			<?php endif; ?>

		</div>

		</div>

		<div class="sm:wu-col-span-8 lg:wu-col-span-6 metabox-holder">

		<div class="wu-relative">

			<?php
			/**
			 * Print Side Metaboxes
			 *
			 * Allow plugin developers to add new metaboxes
			 *
			 * @since 1.8.2
			 * @param object Object being edited right now
			 */
			do_meta_boxes($screen->id, 'normal', false);
			?>

		</div>

		</div>

		<div class="sm:wu-col-span-8 sm:wu-col-start-5 lg:wu-col-span-4 lg:wu-col-start-9 metabox-holder wu-min-w-0">

		<?php
		/**
		 * Print Normal Metaboxes
		 *
		 * Allow plugin developers to add new metaboxes
		 *
		 * @since 1.8.2
		 * @param object Object being edited right now
		 */
		do_meta_boxes('wu_settings_admin_page', 'side', false);
		?>

		</div>

	</div>

	<?php
	/**
	 * Allow plugin developers to add scripts to the bottom of the page
	 *
	 * @since 1.8.2
	 * @param WU_Page $page Ultimate Multisite Page instance
	 */
	do_action('wu_page_wizard_footer', $page);
	?>

	<?php wp_nonce_field(sprintf('saving_%s', $current_section), sprintf('saving_%s', $current_section), false); ?>

	<?php wp_nonce_field(sprintf('saving_%s', $current_section), '_wpultimo_nonce'); ?>

	</form>

</div>
