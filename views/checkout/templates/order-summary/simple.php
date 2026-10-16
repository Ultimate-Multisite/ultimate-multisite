<?php
/**
 * Order summary view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div id="wu-order-summary-content" class="wu-relative">

	<div v-show="!order" class="wu-bg-gray-100 wu-p-4 wu-text-center wu-border wu-border-solid wu-border-gray-300">

	<?php esc_html_e('Generating Order Summary...', 'ultimate-multisite'); ?>

	</div>

	<div v-if="order" v-cloak>

	<table id="wu-order-summary-table" class="wu-w-full wu-mb-0">

		<thead>

		<tr class="">

			<th class="col-description">
			<?php esc_html_e('Description', 'ultimate-multisite'); ?>
			</th>

			<?php if ('simple' === $table_columns) : ?>

			<th class="col-total-gross">
				<?php esc_html_e('Subtotal', 'ultimate-multisite'); ?>
			</th>

			<?php else : ?>

			<th class="col-total-net">
				<?php esc_html_e('Net Total', 'ultimate-multisite'); ?>
			</th>

			<th class="col-total-vat-percentage">
				<?php esc_html_e('Discounts', 'ultimate-multisite'); ?>
			</th>

			<th class="col-total-tax">
				<?php esc_html_e('Tax', 'ultimate-multisite'); ?>
			</th>

			<th class="col-total-gross">
				<?php esc_html_e('Gross Total', 'ultimate-multisite'); ?>
			</th>

			<?php endif; ?>

		</tr>

		</thead>

		<tbody>

		<tr v-if="order.line_items.length === 0">

			<td class="" colspan="<?php echo esc_attr('simple' === $table_columns) ? 2 : 5; ?>" class="col-description">

			<?php esc_html_e('No products in shopping cart.', 'ultimate-multisite'); ?>

			</td>

		</tr>

		<tr v-for="line_item in order.line_items">

			<td class="wu-py-2 col-description" v-show="line_item.recurring">
			<?php // translators: %s: name of the subscription ?>
			<?php printf(esc_html__('Subscription - %s', 'ultimate-multisite'), '{{ line_item.title }}'); ?>

			<small v-if="line_item.type == 'product' && order.type !== 'retry'" class="wu-ml-3 wu-text-xs">

				<a href="#" class="wu-no-underline" v-on:click.prevent="remove_product(line_item.product_id, line_item.product_slug)">

				<?php esc_html_e('Remove', 'ultimate-multisite'); ?>

				</a>

			</small>

			</td>

			<td class="wu-py-2 col-description" v-show="!line_item.recurring">

			{{ line_item.title }}

			<small v-if="line_item.type == 'product' && order.type !== 'retry'" class="">

				<a href="#" class="wu-no-underline" v-on:click.prevent="remove_product(line_item.product_id, line_item.product_slug)">

				<?php esc_html_e('Remove', 'ultimate-multisite'); ?>

				</a>

			</small>

			</td>

			<?php if ('simple' === $table_columns) : ?>

			<td v-show="line_item.recurring" class="wu-py-2 col-total-net">

				{{ wu_format_money(line_item.subtotal) }} / {{ line_item.recurring_description }}

			</td>

			<td v-show="!line_item.recurring" class="wu-py-2 col-total-net">

				{{ wu_format_money(line_item.subtotal) }}

			</td>

			<?php else : ?>

			<td v-show="line_item.recurring" class="wu-py-2 col-total-net">

				{{ wu_format_money(line_item.subtotal) }} / {{ line_item.recurring_description }}

			</td>

			<td v-show="!line_item.recurring" class="wu-py-2 col-total-net">

				{{ wu_format_money(line_item.subtotal) }}

			</td>

			<td class="wu-py-2 col-total-net">

				{{ wu_format_money(line_item.discount_total) }}

			</td>

			<td class="wu-py-2 col-total-tax">

				{{ wu_format_money(line_item.tax_total) }}

				<small v-if="line_item.tax_rate" class="wu-block">

				{{ line_item.tax_label }} {{ line_item.tax_rate }}%

				</small>

			</td>

			<td class="wu-py-2 col-total-gross">

				{{ wu_format_money(line_item.total) }}

			</td>

			<?php endif; ?>

		</tr>

		</tbody>

		<tfoot class="">

		<?php if ('simple' === $table_columns) : ?>

			<tr>

			<td>

				<?php esc_html_e('Discounts', 'ultimate-multisite'); ?>

			</td>

			<td>

				{{ wu_format_money(order.totals.total_discounts) }}

			</td>

			</tr>

			<tr>

			<td>

				<?php esc_html_e('Taxes', 'ultimate-multisite'); ?>

			</td>

			<td>

				{{ wu_format_money(order.totals.total_taxes) }}

			</td>

			</tr>

		<?php endif; ?>

		<tr>

			<td class="" colspan="<?php echo esc_attr('simple' === $table_columns) ? 1 : 4; ?>">

			<strong><?php esc_html_e("Today's Grand Total", 'ultimate-multisite'); ?></strong>

			</td>

			<td class="" v-show="order.has_trial">

			{{ wu_format_money(0) }}

			</td>

			<td class="" v-show="!order.has_trial">

			{{ wu_format_money(order.totals.total) }}

			</td>

		</tr>

		<tr v-if="order.has_trial">

			<td class="" colspan="<?php echo esc_attr('simple' === $table_columns) ? 1 : 4; ?>">

			<small>
				<?php // translators: %1$s relative date string ?>
				<?php printf(wp_kses_post(__('Total in %1$s - end of trial period.', 'ultimate-multisite')), '{{ $moment.unix(order.dates.date_trial_end).format(`LL`) }}'); ?>
			</small>

			</td>

			<td class="">

			{{ wu_format_money(order.totals.total) }}

			</td>

		</tr>

		</tfoot>

	</table>

	<ul class="wu-p-0 wu-m-0 wu-mt-2 wu-list-none wu-order-summary-additional-info wu-text-sm">

		<li v-if="!order.has_trial && order.has_recurring">
		<?php // translators: %1$s order total, %2$s relative date string. ?>
		<?php printf(esc_html__('Next fee of %1$s will be billed in %2$s.', 'ultimate-multisite'), '{{ wu_format_money(order.totals.recurring.total) }}', '{{ $moment.unix(order.dates.date_next_charge).format(`LL`) }}'); ?>

		</li>

		<li class="order-description" v-if="order.totals.total_discounts < 0">

		<?php
		// translators: 1 is the discount name (e.g. Launch Promo). 2 is the coupon code (e.g PROMO10), 3 is the coupon amount and 4 is the discount total.
		printf(esc_html__('Discount applied: %1$s - %2$s (%3$s) %4$s', 'ultimate-multisite'), '{{ order.discount_code.name }}', '{{ order.discount_code.code }}', '{{ order.discount_code.discount_description }}', '{{ wu_format_money(-order.totals.total_discounts) }}');
		?>

		<a v-if="order.type !== 'retry'" class="wu-no-underline wu-ml-2" href="#" v-on:click.prevent="discount_code = ''; toggle_discount_code = false">

			<?php esc_html_e('Remove', 'ultimate-multisite'); ?>

		</a>

		</li>

	</ul>

	</div>

</div>
