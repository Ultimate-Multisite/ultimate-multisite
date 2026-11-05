<?php
/**
 * Account summary view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- className is the shared block/template attribute.
?>
<div id="wu-summary-<?php echo esc_attr($site->get_hash()); ?>" tabindex="-1" style="scroll-margin-top: 3rem;" class="wu-styling <?php echo esc_attr($className); ?>">

	<div class="<?php echo esc_attr(wu_env_picker('', 'wu-widget-inset')); ?>">

	<!-- Title Element -->
	<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-b wu-border-gray-200')); ?>">

		<?php if ($title) : ?>

		<h3 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

			<?php echo esc_html($title); ?>

		</h3>

		<?php endif; ?>

		<?php if (wu_request('page') !== 'account' && empty($hide_manage_link)) : ?>

		<div class="wu-ml-auto">

			<a 
			title="<?php esc_attr_e('See More', 'ultimate-multisite'); ?>"
			class="wu-text-sm wu-no-underline button" 
			href="<?php echo esc_attr($element->get_manage_url($site->get_id())); ?>"
			>

			<?php esc_html_e('See More', 'ultimate-multisite'); ?>

			</a>

		</div>

		<?php endif; ?>

	</div>
	<!-- Title Element - End -->

	<ul class="md:wu-flex wu-m-0 wu-list-none wu-p-4">

	<?php if ($product) : ?>

		<li class="wu-flex-1 wu-relative wu-m-0">

		<div>

			<strong class="wu-text-gray-800 wu-text-base">

				<?php echo esc_html($product->get_name()); ?>

			</strong>

		</div>

		<div class="wu-text-sm wu-text-gray-600">
			<span class="wu-block"><?php esc_html_e('Your current plan', 'ultimate-multisite'); ?></span>
			<!-- <a href="#" class="wu-no-underline"><?php esc_html_e('Manage →', 'ultimate-multisite'); ?></a> -->
		</div>

		</li>

	<?php endif; ?>

	<?php if ($site_trial) : ?>

	<li class="wu-flex-1 wu-relative wu-m-0">

		<div>

		<strong class="wu-text-gray-800 wu-text-base">
			<?php // translators: %s: Number of days. ?>
			<?php printf(esc_html(_n('%s day', '%s days', $site_trial, 'ultimate-multisite')), esc_html($site_trial)); ?>
		</strong>

		</div>

		<div class="wu-text-sm wu-text-gray-600">
		<span class="wu-block"><?php esc_html_e('Remaining time in trial', 'ultimate-multisite'); ?></span>
		<!-- <a href="#" class="wu-no-underline"><?php esc_html_e('Upgrade →', 'ultimate-multisite'); ?></a> -->
		</div>

	</li>

	<?php endif; ?>

	<li class="wu-flex-1 wu-relative wu-m-0">

		<div>

		<strong class="wu-text-gray-800 wu-text-base">
			<?php
			/**
			 * Display space used
			 */
			printf(esc_html($message), esc_html(size_format($space_used)), esc_html(size_format($space_allowed)));
			?>
		</strong>

		<?php if ( ! $unlimited_space) : ?>

			<span class="wu-p-1 wu-bg-gray-200 wu-inline wu-align-text-bottom wu-rounded wu-text-center wu-text-xs wu-text-gray-600">
			<?php echo esc_html($percentage); ?>%
			</span>

		<?php endif; ?>

		</div>

		<div class="wu-text-sm wu-text-gray-600">
		<span class="wu-block"><?php esc_html_e('Disk space used', 'ultimate-multisite'); ?></span>
		<!-- <a href="#" class="wu-no-underline"><?php esc_html_e('Upgrade →', 'ultimate-multisite'); ?></a> -->
		</div>

	</li>

	</ul>

</div>

</div>
