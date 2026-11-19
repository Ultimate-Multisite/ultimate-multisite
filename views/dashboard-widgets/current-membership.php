<?php
/**
 * The Current Membership
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div class="wu-styling <?php echo esc_attr($className); ?>">

	<div class="<?php echo esc_attr(wu_env_picker('', 'wu-widget-inset')); ?>">

	<!-- Title Element -->
	<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-b wu-border-gray-200')); ?>">

		<?php if ($title) : ?>

		<h3 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

			<?php echo esc_html($title); ?>

		</h3>

		<?php endif; ?>

		<?php if (wu_request('page') !== 'wu-checkout') : ?>

		<div class="wu-ml-auto">

			<a 
			title="<?php esc_attr_e('Update your membership', 'ultimate-multisite'); ?>"
			class="wu-text-sm wu-no-underline button" 
			href="<?php echo esc_attr(wu_get_membership_update_url($membership)); ?>"
			>

			<?php esc_html_e('Change', 'ultimate-multisite'); ?>

			</a>

		</div>

		<?php endif; ?>

	</div>
	<!-- Title Element - End -->

	<!-- Product Block -->

	<?php if ($plan) : ?>

		<div class="wu-p-4 wu-flex wu-justify-between wu-items-center wu-flex-wrap sm:wu-flex-nowrap">

		<div class="">

			<div class="wu-flex wu-items-center">

			<?php if ($display_images && $plan->get_featured_image()) : ?>

				<div class="wu-flex-shrink-0 wu-mr-4">

				<img 
					class="wu-h-8 wu-w-8 wu-rounded" 
					src="<?php echo esc_url($plan->get_featured_image()); ?>" 
					alt="<?php echo esc_attr($plan->get_name()); ?>"
				>

				</div>

			<?php endif; ?>

			<div class="">

				<span class="wu-text-lg wu-font-medium wu-text-gray-900 wu-block">

				<?php echo esc_html($plan->get_name()); ?>

				<span class="wu-font-mono wu-mx-2 wu-text-xs"><?php echo esc_html($membership->get_hash()); ?></span>

				</span>

				<span class="wu-text-sm wu-text-gray-600">

				<?php echo esc_html($plan->get_price_description()); ?>

				</span>

			</div>

			</div>

			<?php if ($pending_change) : ?>

			<div class="wu-mt-4"> 

				<div class="wu-bg-yellow-200 wu-text-yellow-700 wu-rounded wu-p-2">
				<?php // translators: %1$s: date, %2$s: amount. ?>
				<?php printf(esc_html__('There\'s a pending change for this membership, scheduled to take place on %1$s. Changing to %2$s.', 'ultimate-multisite'), '<strong>' . esc_html($pending_change_date) . '</strong>', '<strong>' . esc_html($pending_change) . '</strong>'); ?>

				</div>

			</div>

			<?php endif; ?>

		</div>

		</div>

	<?php endif; ?>

	<!-- Product Block - End -->

	<?php if ($membership) : ?>

		<div class="wu-py-4 wu-pb-2 wu-px-4 wu-grid wu-grid-cols-1 wu-gap-x-4 wu-gap-y-8 sm:wu-grid-cols-<?php echo esc_attr((int) $columns); ?> wu-border-solid wu-border-0 wu-border-b wu-border-t wu-border-gray-200">

		<div class="sm:wu-col-span-1">

			<div class="wu-text-sm wu-font-medium wu-text-gray-600">
			<?php esc_html_e('Status', 'ultimate-multisite'); ?>
			</div>

			<div class="wu-mt-1 wu-text-sm wu-text-gray-900 wu-mb-4">

			<span class="<?php echo esc_attr($membership->get_status_class()); ?> wu-font-medium wu-inline-block wu-py-1 wu-px-2 wu-rounded">

				<?php echo esc_html($membership->get_status_label()); ?>

			</span>

			</div>

		</div>

		<div class="sm:wu-col-span-1">

			<div class="wu-text-sm wu-font-medium wu-text-gray-600">
			<?php esc_html_e('Initial Amount', 'ultimate-multisite'); ?>
			</div>

			<div class="wu-mt-1 wu-text-sm wu-text-gray-900 wu-mb-4">
			<?php echo esc_html(wu_format_currency($membership->get_initial_amount(), $membership->get_currency())); ?>
			</div>

		</div>

		<?php if ($membership->is_recurring()) : ?>

			<div class="sm:wu-col-span-1">

			<div class="wu-text-sm wu-font-medium wu-text-gray-600">
				<?php esc_html_e('Times Billed', 'ultimate-multisite'); ?>
			</div>

			<div class="wu-mt-1 wu-text-sm wu-text-gray-900 wu-mb-4">
				<?php echo esc_html($membership->get_times_billed_description()); ?>
			</div>

			</div>

		<?php endif; ?>

		<?php if ( ! $membership->is_lifetime()) : ?>

			<div class="sm:wu-col-span-1">

			<div class="wu-text-sm wu-font-medium wu-text-gray-600">
				<?php esc_html_e('Expires', 'ultimate-multisite'); ?>
			</div>

			<div class="wu-mt-1 wu-text-sm wu-text-gray-900 wu-mb-4">
				<?php echo esc_html($membership->get_formatted_date('date_expiration')); ?>
			</div>

			</div>

		<?php endif; ?>

		</div>

		<!-- Additional Packages -->

		<div class="wu-hidden">

		<ul class="wu-list-none wu-p-0 wu-m-0 wu-border-solid wu-border-0 wu-border-gray-200">

			<!-- Coupon -->
			<li class="wu-text-sm wu-text-gray-700 wu-border-solid wu-border-0 wu-border-b wu-border-gray-200 wu-m-0 wu-py-3 wu-px-4 wu-flex wu-items-center wu-justify-between">

			<span>

				<span class="wu-font-medium wu-text-gray-700 wu-block">

				<?php esc_html_e('Coupon Code', 'ultimate-multisite'); ?>

				</span>

				<span class="wu-text-sm wu-text-gray-600 wu-block">

				<?php esc_html_e('None applied.', 'ultimate-multisite'); ?>

				</span>

			</span>

			<div class="wu-ml-4 wu-flex-shrink-0 wu-flex">

				<a href="#" class="wu-no-underline">

				<?php esc_html_e('Add Coupon', 'ultimate-multisite'); ?>

				</a>

			</div>

			</li>
			<!-- Coupon End -->

		</ul>

		</div>

		<div>

		<!-- Title Element -->
		<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-b wu-border-gray-200')); ?>">

			<h3 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

			<?php esc_html_e('Additional Packages & Services', 'ultimate-multisite'); ?>

			</h3>

		</div>
		<!-- Title Element - End -->

		<?php if ($membership->has_addons()) : ?>

			<ul class="wu-list-none wu-p-0 wu-m-0 wu-border-solid wu-border-0 wu-border-t wu-border-gray-200">

			<?php foreach ($membership->get_addon_products() as $addon) : ?>

			<!-- Packages and Services -->

			<li class="wu-text-sm wu-text-gray-700 wu-border-solid wu-border-0 wu-border-b wu-border-gray-200 wu-m-0 wu-py-3 wu-px-4 wu-flex wu-items-center wu-justify-between">

				<span>

				<span class="wu-font-medium wu-text-gray-700 wu-block">

					<?php echo esc_html($addon['product']->get_name()); ?>
					<code class="wu-ml-2 wu-text-xs wu-font-normal">

						x <?php echo esc_html($addon['quantity']); ?>

					</code>

				</span>

				<span class="wu-text-sm wu-text-gray-600 wu-block">

					<!-- <span class="wu-text-gray-500 wu-line-through">$29 per month</span>  -->
					<?php echo esc_html($addon['product']->get_price_description()); ?>

				</span>

				</span>

				<div class="wu-ml-4 wu-flex-shrink-0 wu-flex">

				<a 
					title="<?php esc_attr_e('Product Details', 'ultimate-multisite'); ?>"
					href="
					<?php
					echo esc_attr(
						wu_get_form_url(
							'see_product_details',
							[
								'product' => $addon['product']->get_slug(),
								'width'   => 500,
							]
						)
					);
					?>
					" 
					class="wubox wu-ml-4 wu-no-underline"
				>

					<?php esc_html_e('Details', 'ultimate-multisite'); ?>

				</a>

				<?php if ($addon['product']->is_recurring() && (! $pending_products || wu_get_isset($pending_products, $addon['product']->get_id()))) : ?>

					<a     <?php // Translators: %s is the name of the product being canceled. ?>
					title="<?php esc_attr(sprintf(__('Cancel %s', 'ultimate-multisite'), $addon['product']->get_name())); ?>"
					href="
					<?php
					echo esc_attr(
						wu_get_form_url(
							'edit_membership_product_modal',
							[
								'membership' => $membership->get_hash(),
								'product'    => $addon['product']->get_slug(),
								'width'      => 500,
							]
						)
					);
					?>
					"
					class="wubox wu-ml-4 wu-no-underline delete wu-text-red-500 hover:wu-text-red-600"
					>

					<?php esc_html_e('Cancel', 'ultimate-multisite'); ?>

					</a>

				<?php endif; ?>

				</div>

			</li>

			<!-- Packages and Services - End -->

			<?php endforeach; ?>

			</ul>

		<?php else : ?>      

			<div class="wu-px-4 wu-py-6 wu-text-center wu-text-gray-600">
			<?php esc_html_e('No packages or services found.', 'ultimate-multisite'); ?>
			</div>

		<?php endif; ?>  

		</div>

		<?php if ($membership->is_recurring()) : ?>

		<!-- Summary Line - Total Applied -->
		<div class="<?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-t wu-border-gray-200')); ?> wu-m-0 wu-p-4 wu-rounded lg:wu-flex wu-items-center wu-justify-between">

			<div class="wu-text-lg">

			<small class="wu-block wu-text-xs wu-uppercase wu-font-bold wu-text-gray-600">
				<?php esc_html_e('Total', 'ultimate-multisite'); ?>
			</small>

			<!-- <span class="wu-text-gray-500 wu-line-through">$29</span> -->

			<span>
				<?php echo esc_html(wu_format_currency($membership->get_amount(), $membership->get_currency())); ?>
			</span>

			<span class="wu-text-gray-500 wu-text-sm">
				<?php echo esc_html($membership->get_recurring_description()); ?>
			</span>

			</div>

		</div>
		<!-- Summary Line - Total Applied End -->

		<?php endif; ?>

	<?php endif; ?>

	</div>

</div>
