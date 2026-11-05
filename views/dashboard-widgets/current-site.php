<?php
/**
 * The Current Site view
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div id="wu-site-<?php echo esc_attr($current_site->get_hash()); ?>" tabindex="-1" style="scroll-margin-top: 3rem;" class="wu-styling <?php echo esc_attr($className); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Shared block attribute. ?>">

	<div class="<?php echo esc_attr(wu_env_picker('', 'wu-mt-4')); ?>">

	<?php if ($display_breadcrumbs) : ?>

		<div class="wu-current-site-breadcrumbs">

		<div class="wu-bg-gray-100">

			<nav 
			class="wu-border wu-rounded wu-border-solid wu-flex wu-px-4 <?php echo esc_attr(wu_env_picker('wu-border-gray-300', 'wu-border-gray-400')); ?>" 
			aria-label="<?php esc_attr_e('Breadcrumb', 'ultimate-multisite'); ?>"
			>

			<ol class="wu-p-0 wu-m-0 wu-w-full wu-mx-auto wu-flex">

				<li class="wu-flex wu-m-0 wu-p-0">

				<div class="wu-flex wu-items-center">

					<svg class="wu-flex-shrink-0 wu-h-5 wu-w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
					<path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
					</svg>

					<span class="screen-reader-text"><?php esc_html_e('Home', 'ultimate-multisite'); ?></span>

				</div>

				</li>

				<li class="wu-flex wu-m-0 wu-p-0">
				<div class="wu-flex wu-items-center">
					<svg class="wu-flex-shrink-0 wu-w-6 wu-h-full wu-text-gray-300" viewBox="0 0 24 44" preserveAspectRatio="none" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<path d="M.293 0l22 22-22 22h1.414l22-22-22-22H.293z" />
					</svg>
					<a href="<?php echo esc_url($my_sites_url); ?>" class="wu-mx-4 wu-text-sm wu-font-medium wu-text-gray-500 hover:wu-text-gray-700 wu-no-underline">
						<?php esc_html_e('Your Sites', 'ultimate-multisite'); ?>
					</a>
				</div>
				</li>
				<li class="wu-flex wu-m-0 wu-p-0">
				<div class="wu-flex wu-items-center">
					<svg class="wu-flex-shrink-0 wu-w-6 wu-h-full wu-text-gray-300" viewBox="0 0 24 44" preserveAspectRatio="none" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<path d="M.293 0l22 22-22 22h1.414l22-22-22-22H.293z" />
					</svg>
					<span class="wu-mx-4 wu-text-sm wu-font-medium wu-text-gray-700 hover:wu-text-gray-700">
						<?php echo esc_html($current_site->get_title()); ?>
					</span>
				</div>
				</li>
			</ol>

			</nav>

		</div>

		</div>

	<?php endif; ?>

	<div class="wu-py-4 <?php echo esc_attr(wu_env_picker('', '')); ?>">

		<div class="wu-relative md:wu-flex">

		<?php if ($display_image) : ?>

			<div class="wu-mb-4 md:wu-mb-0 <?php echo 'right' === $screenshot_position ? 'wu-order-12 md:wu-ml-6' : 'md:wu-mr-6'; ?>">

			<img 
				style="max-width: <?php echo esc_attr($screenshot_size); ?>px;"
				class="wu-w-full wu-rounded wu-border wu-border-solid <?php echo esc_attr(wu_env_picker('wu-border-gray-300', 'wu-border-gray-400')); ?>" 
				src="<?php echo esc_attr($current_site->get_featured_image()); ?>"
				<?php // translators: %s: Site Title ?>
				alt="<?php printf(esc_attr__('Site Image: %s', 'ultimate-multisite'), esc_attr($current_site->get_title())); ?>"
			>

			</div>

		<?php endif; ?>

		<div class="wu-relative wu-flex wu-flex-grow wu-my-4 wu-px-2">

			<div class="wu-self-center wu-flex-grow">

			<span class="wu-text-3xl wu-font-bold wu-text-gray-900 sm:wu-text-4xl wu-block wu-leading-none">

				<?php echo esc_html($current_site->get_title()); ?>

			</span>

			<span class="wu-text-sm wu-text-gray-600 wu-block wu-my-3 wu-leading-none">

				<?php echo esc_html($current_site->get_active_site_url()); ?>

			</span>

			<?php if ($display_description) : ?>

				<span class="wu-text-sm wu-text-gray-700 wu-my-5 wu-block wu-leading-none">

				<?php echo esc_html($current_site->get_description()); ?>

				</span>

			<?php endif; ?>

			<!-- Site Actions -->
			<ul class="wu-list-none wu-p-0 wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-mt-4')); ?>">

				<?php foreach ($actions as $action) : ?>

				<li class="wu-my-4 sm:wu-m-0 sm:wu-inline sm:wu-mr-6">

					<a 
					class="wu-text-sm wu-no-underline <?php echo esc_attr($action['classes']); ?>" 
					href="<?php echo esc_attr($action['href']); ?>"
					title="<?php echo esc_attr($action['label']); ?>"
					>

					<span class="<?php echo esc_attr($action['icon_classes']); ?>"></span>

					<?php echo esc_html($action['label']); ?>

					</a>

				</li>

				<?php endforeach; ?>

			</ul>
			<!-- Site Actions End -->

			</div>

		</div>

		</div>

	</div>

	</div>

</div>
