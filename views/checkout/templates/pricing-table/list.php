<?php
/**
 * List Template field view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;


/**
 * Deal with different pricing options
 */
foreach ($products as $index => &$_product) {
	$_product = wu_get_product($_product['id']);

	$product_variation = $_product->get_as_variation($duration, $duration_unit);

	if (false === $product_variation && ! $force_different_durations) {
		unset($products[ $index ]);

		$_product = $product_variation;
	}
}

?>
<div class="">

	<div class="wu-grid wu-grid-flow-row wu-gap-4 <?php echo esc_attr($classes); ?>">

	<?php foreach ($products as $product) : ?>
		<?php /** @var \WP_Ultimo\Models\Product $product */ ?>

		<label
		id="wu-product-<?php echo esc_attr($product->get_id()); ?>"
		class="wu-relative wu-block wu-rounded-lg wu-border wu-border-gray-300 wu-bg-white wu-border-solid wu-shadow-sm wu-px-6 wu-py-4 wu-cursor-pointer hover:wu-border-gray-400 sm:wu-flex sm:wu-justify-between focus-within:wu-ring-1 focus-within:wu-ring-offset-2 focus-within:wu-ring-indigo-500">

		<input v-if="<?php echo wp_json_encode($product->get_pricing_type() !== 'contact_us'); ?>" v-on:click="$parent.add_plan(<?php echo esc_attr($product->get_id()); ?>)" type="checkbox" name="products[]" value="<?php echo esc_attr($product->get_id()); ?>" class="screen-reader-text wu-hidden">

		<input v-else v-on:click="$parent.open_url('<?php echo esc_url($product->get_contact_us_link()); ?>', '_blank');" type="checkbox" name="products[]" value="<?php echo esc_attr($product->get_id()); ?>" class="screen-reader-text wu-hidden">

		<div class="wu-flex wu-items-center">
			<div class="wu-text-sm">
			<span id="server-size-0-label" class="wu-font-semibold wu-block wu-text-gray-900">
				<?php echo esc_html($product->get_name()); ?>
			</span>
			<div id="server-size-0-description-0" class="wu-text-gray-600">
				<p class="sm:wu-inline">
				<?php echo wp_kses($product->get_description(), wu_kses_allowed_html()); ?>
				</p>
			</div>
			</div>
		</div>
		<div id="server-size-0-description-1" class="wu-mt-2 wu-flex wu-text-md sm:wu-mt-0 sm:wu-block sm:wu-ml-4 sm:wu-text-right">
			<div class="wu-font-semibold wu-text-gray-900"><?php echo esc_html($product->get_formatted_amount()); ?></div>
			<div class="wu-ml-1 wu-text-sm wu-text-gray-500 sm:wu-ml-0"><?php echo esc_html($product->get_recurring_description()); ?></div>
		</div>

		<?php if ($product->is_pay_what_you_want()) : ?>
			<div class="wu-mt-3 wu-w-full wu-space-y-2" v-show="$parent.has_product(<?php echo esc_attr($product->get_id()); ?>)">
				<!-- Custom Amount Input -->
				<div class="wu-flex wu-items-center wu-gap-2">
					<span class="wu-text-gray-600 wu-font-medium"><?php echo esc_html(wu_get_currency_symbol()); ?></span>
					<input
						type="number"
						step="0.01"
						min="<?php echo esc_attr($product->get_pwyw_minimum_amount()); ?>"
						:value="$parent.get_custom_amount(<?php echo esc_attr($product->get_id()); ?>)"
						@input="$parent.set_custom_amount(<?php echo esc_attr($product->get_id()); ?>, $event.target.value)"
						@click.stop
						class="wu-w-28 wu-px-3 wu-py-1 wu-border wu-border-gray-300 wu-border-solid wu-rounded wu-text-sm"
						placeholder="<?php echo esc_attr($product->get_pwyw_suggested_amount() ?: '0.00'); ?>"
					>
				</div>

				<?php if ($product->allows_customer_recurring_choice()) : ?>
					<!-- Recurring Toggle (only when customer can choose) -->
					<label class="wu-flex wu-items-center wu-gap-2 wu-text-sm wu-text-gray-600" @click.stop>
						<input
							type="checkbox"
							:checked="$parent.get_pwyw_recurring(<?php echo esc_attr($product->get_id()); ?>)"
							@change="$parent.set_pwyw_recurring(<?php echo esc_attr($product->get_id()); ?>, $event.target.checked)"
							class="wu-rounded wu-border-gray-300"
						>
						<?php esc_html_e('Make this a recurring payment', 'ultimate-multisite'); ?>
					</label>
				<?php elseif ('force_recurring' === $product->get_pwyw_recurring_mode()) : ?>
					<span class="wu-text-sm wu-text-gray-500"><?php esc_html_e('(Recurring subscription)', 'ultimate-multisite'); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div
			class="wu-absolute wu--inset-px wu-rounded-lg wu-border-solid wu-border-2 wu-pointer-events-none wu-top-0 wu-bottom-0 wu-right-0 wu-left-0"
			:class="$parent.has_product(<?php echo esc_attr($product->get_id()); ?>) || $parent.has_product('<?php echo esc_attr($product->get_slug()); ?>') ? 'wu-border-blue-500' : 'wu-border-transparent'"
			aria-hidden="true"
		>
		</div>
		</label>

	<?php endforeach; ?>

	</div>

</div>
