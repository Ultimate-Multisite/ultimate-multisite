<?php
/**
 * Order bump view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

//phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
$duration      = $duration ?: 1;
$duration_unit = $duration_unit ?: 'month';

$product = wu_get_product($product['id']);

$product_variation = $product->get_as_variation($duration, $duration_unit);

if (false !== $product_variation) {
	$product = $product_variation;
}
?>
<div class="wu-relative wu-flex wu-rounded-lg wu-border wu-border-gray-300 wu-bg-white wu-border-solid wu-shadow-sm wu-px-6 wu-py-4 wu-items-center wu-justify-between">
	<div class="wu-flex wu-items-center">
		<?php if ($display_product_image) : ?>
			<?php $image = $product->get_featured_image('thumbnail'); ?>
			<?php if ($image) : ?>
				<div class="wu-w-thumb wu-h-thumb wu-rounded wu-overflow-hidden wu-text-center wu-inline-block wu-mr-4">
					<img src="<?php echo esc_attr($image); ?>" class="wu-h-full">
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<div class="wu-text-sm">
			<span class="wu-font-semibold wu-block wu-text-gray-900"><?php echo empty($name) ? esc_html($product->get_name()) : esc_html($name); ?></span>
			<?php if ($display_product_description && $product->get_description()) : ?>
				<div class="wu-text-gray-600">
					<p class="sm:wu-inline-block wu-my-1"><?php echo wp_kses($product->get_description(), wu_kses_allowed_html()); ?></p>
				</div>
			<?php endif; ?>
			<div class="wu-text-gray-600">
				<p class="sm:wu-inline"><?php echo esc_html($product->get_price_description()); ?></p>
			</div>
		</div>
	</div>
	<div v-if="!($parent.has_product('<?php echo esc_js($product->get_id()); ?>') || $parent.has_product('<?php echo esc_js($product->get_slug()); ?>'))" class="wu-ml-2">
		<a href="#" @click.prevent="$parent.add_product('<?php echo esc_js($product->get_id()); ?>')" class="button btn"><?php esc_html_e('Add to Cart', 'ultimate-multisite'); ?></a>
	</div>
	<div v-else class="wu-ml-2">
		<a href="#" @click.prevent="$parent.remove_product('<?php echo esc_js($product->get_id()); ?>', '<?php echo esc_js($product->get_slug()); ?>')" class="button btn"><?php esc_html_e('Remove', 'ultimate-multisite'); ?></a>
		<input type="hidden" name="products[]" value="<?php echo esc_attr($product->get_id()); ?>">
	</div>
	<div class="wu-absolute wu--inset-px wu-rounded-lg wu-border-solid wu-pointer-events-none wu-top-0 wu-bottom-0 wu-right-0 wu-left-0" :class="($parent.has_product('<?php echo esc_js($product->get_id()); ?>') || $parent.has_product('<?php echo esc_js($product->get_slug()); ?>')) ? 'wu-border-blue-500' : 'wu-border-transparent'" aria-hidden="true"></div>
</div>
