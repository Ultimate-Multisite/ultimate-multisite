<?php
/**
 * Thank You Element
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<div id="wu-thank-you-element" class="wu-styling <?php echo esc_attr($className); ?>">

	<div class="<?php echo esc_attr(wu_env_picker('', 'wu-widget-inset')); ?>">

	<?php if (in_array($payment->get_status(), ['completed'], true)) : ?>

		<!-- Thank You -->
		<div id="wu-thank-you-message-block">

		<!-- Title Element -->
		<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100')); ?>">

			<?php if ($title) : ?>

			<h3 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

				<?php echo esc_html($title); ?>

			</h3>

			<?php endif; ?>

		</div>
		<!-- Title Element - End -->

		<!-- Body Content -->
		<div class="wu-thank-you-message wu-px-4">

			<?php echo do_shortcode($thank_you_message); ?>

		</div>
		<!-- Body Content - End -->

		<ul class="wu-thank-you-info wu-grid wu-grid-cols-1 sm:wu-grid-cols-3 wu-gap-4 wu-m-0 wu-px-4 wu-py-6 wu-list-none">

			<!-- Info Item -->
			<li>

			<span class="wu-uppercase wu-text-sm wu-block">

				<?php esc_html_e('Order ID', 'ultimate-multisite'); ?>

			</span>

			<span class="wu-text-md wu-font-bold wu-block">

				<?php echo esc_html($payment->get_hash()); ?>

				<small class="wu-text-gray-600 wu-font-normal wu-m-0 wu-block">

				<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($payment->get_date_created()))); ?>

				</small>

			</span>

			</li>
			<!-- Info Item - End -->

			<!-- Info Item -->
			<li>

			<span class="wu-uppercase wu-text-sm wu-block">

				<?php esc_html_e('Email', 'ultimate-multisite'); ?>

			</span>

			<span class="wu-text-md wu-font-bold wu-block wu-break-all">

				<?php echo esc_html($customer->get_email_address()); ?>

			</span>

			</li>
			<!-- Info Item - End -->

			<!-- Info Item -->
			<li>

			<span class="wu-uppercase wu-text-sm wu-block">

				<?php esc_html_e('Total', 'ultimate-multisite'); ?>

			</span>

			<span class="wu-text-md wu-font-bold wu-block">

				<?php echo esc_html(wu_format_currency($payment->get_total(), $payment->get_currency())); ?>

			</span>

			</li>
			<!-- Info Item - End -->

		</ul>

		</div>
		<!-- Thank You - End -->

	<?php else : ?>

		<!-- Thank You -->
		<div id="wu-thank-you-message-block">

		<!-- Title Element -->
		<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100')); ?>">

			<?php if ($title_pending) : ?>

			<h3 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

				<?php echo esc_html($title_pending); ?>

			</h3>

			<?php endif; ?>

		</div>
		<!-- Title Element - End -->

		<!-- Body Content -->
		<div class="wu-thank-you-message wu-px-4">

			<?php echo do_shortcode($thank_you_message_pending); ?>

		</div>
		<!-- Body Content - End -->

		<ul class="wu-thank-you-info wu-grid wu-grid-cols-1 sm:wu-grid-cols-2 wu-gap-4 wu-m-0 wu-px-4 wu-py-6 wu-list-none">

			<!-- Info Item -->
			<li>

			<span class="wu-uppercase wu-text-sm wu-block">

				<?php esc_html_e('Order ID', 'ultimate-multisite'); ?>

			</span>

			<span class="wu-text-md wu-font-bold wu-block">

				<?php echo esc_html($payment->get_hash()); ?>

			</span>

			</li>
			<!-- Info Item - End -->

			<!-- Info Item -->
			<li>

			<span class="wu-uppercase wu-text-sm wu-block">

				<?php esc_html_e('Date', 'ultimate-multisite'); ?>

			</span>

			<span class="wu-text-md wu-font-bold wu-block">

				<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($payment->get_date_created()))); ?>

			</span>

			</li>
			<!-- Info Item - End -->

			<!-- Info Item -->
			<li>

			<span class="wu-uppercase wu-text-sm wu-block">

				<?php esc_html_e('Email', 'ultimate-multisite'); ?>

			</span>

			<span class="wu-text-md wu-font-bold wu-block wu-break-all">

				<?php echo esc_html($customer->get_email_address()); ?>

			</span>

			</li>
			<!-- Info Item - End -->

			<!-- Info Item -->
			<li>

			<span class="wu-uppercase wu-text-sm wu-block">

				<?php esc_html_e('Total', 'ultimate-multisite'); ?>

			</span>

			<span class="wu-text-md wu-font-bold wu-block">

				<?php echo esc_html(wu_format_currency($payment->get_total(), $payment->get_currency())); ?>

			</span>

			</li>
			<!-- Info Item - End -->

		</ul>

		</div>
		<!-- Thank You - End -->

	<?php endif; ?>

	<?php do_action('wu_thank_you_before_info_blocks', $payment, $membership, $customer); ?>

	<!-- Sites -->
	<div id="wu-thank-you-sites">

		<!-- Title Element -->
		<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100')); ?>">

		<?php if ('Site') : ?>

			<h4 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

			<?php esc_html_e('Site', 'ultimate-multisite'); ?>

			</h4>

		<?php endif; ?>

		</div>
		<!-- Title Element - End -->

		<!-- Body Content -->
		<div class="wu-thank-you-pending-site wu-px-4 wu-mb-4">

		<?php do_action('wu_thank_you_site_block', $payment, $membership, $customer); ?>

		<div id="wu-sites">

			<?php if ($membership->get_sites()) : ?>

			<?php foreach ($membership->get_sites() as $site) : ?>

			<div class="wu-bg-gray-100 wu-p-4 wu-rounded wu-mb-2 sm:wu-flex wu-items-center wu-w-full">

					<div class="wu-flex-shrink-0 sm:wu-mr-4">

					<img
					class="wu-mb-4 sm:wu-mb-0 wu-rounded"
					style="max-width: 120px;"
					src="<?php echo esc_attr($site->get_featured_image('thumbnail')); ?>"
					alt="Thumbnail of Site" />

				</div>

			<div class="wu-flex-grow wu-min-w-0">

				<h5 class="wu-mb-1">

				<?php echo esc_html(ucfirst($site->get_title())); ?>

					<?php if ($site->get_type() === 'pending') : ?>

						<span class="wu-align-middle wu-inline-block wu-rounded wu-px-2 wu-py-1 wu-uppercase wu-text-xs wu-font-bold <?php echo esc_attr($site->get_type_class()); ?>">
						<?php echo esc_html($site->get_type_label()); ?>
						</span>

						<span v-cloak v-if="creating && false" class="wu-align-middle wu-inline-block wu-rounded wu-px-2 wu-py-1 wu-uppercase wu-text-xs wu-font-bold wu-text-gray-700 wu-bg-gray-300">
						{{ progress }}%
						</span>

					<?php else : ?>

						<span class="wu-align-middle wu-inline-block wu-rounded wu-px-2 wu-py-1 wu-uppercase wu-text-xs wu-font-bold wu-bg-green-300 wu-text-green-700">
						<?php esc_html_e('Ready!', 'ultimate-multisite'); ?>
						</span>

					<?php endif; ?>

					</h5>

					<div class="wu-truncate">

					<span class="wu-text-sm">

						<?php echo esc_html($site->get_active_site_url()); ?>

					</span>

					</div>

				</div>

				<div class="wu-flex-shrink-0 sm:wu-ml-4">

					<?php if ($site->get_type() === 'pending') : ?>

					<a v-if="!creating" href="<?php echo esc_attr(wu_get_current_url()); ?>" class="wu-block sm:wu-inline-block wu-no-underline">
						<span class="dashicons-wu-cycle wu-align-middle wu-mr-1"></span>
						<?php esc_html_e('Check Status', 'ultimate-multisite'); ?>
					</a>
					<div v-else class="wu-block sm:wu-inline-block wu-no-underline">
						<span class="dashicons-wu-loader wu-align-middle wu-mr-1 wu-spin" style="display: inline-block;"></span>
						<?php esc_html_e('Creating', 'ultimate-multisite'); ?>
					</div>

					<?php else : ?>

					<a href="<?php echo esc_attr(get_admin_url($site->get_id())); ?>" class="wu-block sm:wu-inline-block wu-no-underline sm:wu-mr-4">
						<span class="dashicons-wu-gauge wu-align-middle wu-mr-1"></span>
						<?php esc_html_e('Admin Panel', 'ultimate-multisite'); ?>
					</a>

					<a href="<?php echo esc_attr(wu_with_sso(get_site_url($site->get_id()))); ?>" class="wu-block sm:wu-inline-block wu-no-underline" target="_blank">
						<span class="dashicons-wu-browser wu-align-middle wu-mr-1"></span>
						<?php esc_html_e('Visit', 'ultimate-multisite'); ?>
					</a>

					<?php endif; ?>

				</div>

				</div>

			<?php endforeach; ?>

			<?php else : ?>

			<div class="wu-bg-gray-100 wu-p-4 wu-rounded">

				<?php echo do_shortcode($no_sites_message); ?>

			</div>

			<?php endif; ?>

		</div>

		</div>
		<!-- Body Content - End -->

	</div>
	<!-- Sites - End -->

	<!-- Order Details -->
	<div id="wu-thank-you-order-details">

		<!-- Title Element -->
		<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100')); ?>">

		<?php if ('Order Details') : ?>

			<h4 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

			<?php esc_html_e('Order Details', 'ultimate-multisite'); ?>

			</h4>

		<?php endif; ?>

		</div>
		<!-- Title Element - End -->

		<!-- Body Content -->
		<div class="wu-thank-you-message wu-px-4 wu-mb-4 wu-overflow-x-auto">

		<table class="wu-w-full">

			<thead class="wu-bg-gray-100">

			<tr>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php esc_html_e('Product', 'ultimate-multisite'); ?></th>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php esc_html_e('Total', 'ultimate-multisite'); ?></th>
			</tr>

			</thead>

			<tbody>

			<?php foreach ($payment->get_line_items() as $line_item) : ?>

				<tr>

				<td class="wu-py-2 wu-px-4">
					<?php echo esc_html($line_item->get_title()); ?>
					<code class="wu-ml-1">x<?php echo esc_html($line_item->get_quantity()); ?></code>
				</td>

				<td class="wu-py-2 wu-px-4">
					<?php echo esc_html(wu_format_currency($line_item->get_subtotal(), $payment->get_currency())); ?>
				</td>

				</tr>

			<?php endforeach; ?>

			</tbody>

			<tfoot class="wu-bg-gray-100">

			<tr>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php esc_html_e('Subtotal', 'ultimate-multisite'); ?></th>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php echo esc_html(wu_format_currency($payment->get_subtotal(), $payment->get_currency())); ?></th>
			</tr>

			<?php foreach ($payment->get_tax_breakthrough() as $rate => $total) : ?>

				<tr>
				<?php // translators: %s: Tax rate. ?>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php printf(esc_html__('Tax (%s%%)', 'ultimate-multisite'), esc_html($rate)); ?></th>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php echo esc_html(wu_format_currency($total, $payment->get_currency())); ?></th>
				</tr>

			<?php endforeach; ?>

			<?php if ($payment->get_discount_total()) : ?>
				<tr>
					<?php // translators: %s: Discount code. ?>
					<th class="wu-text-left wu-py-2 wu-px-4"><?php printf(esc_html__('Discount (%s)', 'ultimate-multisite'), esc_html($payment->get_discount_code())); ?></th>
					<th class="wu-text-left wu-py-2 wu-px-4"><?php echo esc_html(wu_format_currency($payment->get_discount_total(), $payment->get_currency())); ?></th>
				</tr>
			<?php endif; ?>

			<tr>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php esc_html_e('Total', 'ultimate-multisite'); ?></th>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php echo esc_html(wu_format_currency($payment->get_total(), $payment->get_currency())); ?></th>
			</tr>

			</tfoot>

		</table>

		</div>
		<!-- Body Content - End -->

	</div>
	<!-- Order Details - End -->

	<!-- Billing Address -->
	<div id="wu-thank-you-billing-address">

		<!-- Title Element -->
		<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100')); ?>">

		<?php if ('Billing Address') : ?>

			<h4 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

			<?php esc_html_e('Billing Address', 'ultimate-multisite'); ?>

			</h4>

		<?php endif; ?>

		</div>
		<!-- Title Element - End -->

		<!-- Body Content -->
		<div class="wu-thank-you-billing-address wu-p-4 wu-mx-4 wu-bg-gray-100 wu-rounded">
		<?php /** @var $membership \WP_Ultimo\Models\Membership */ ?>
		<?php echo nl2br(esc_html($membership->get_billing_address()->to_string("\n"))); ?>

		</div>
		<!-- Body Content - End -->

	</div>
	<!-- Billing Address - End -->

	</div>

</div>
