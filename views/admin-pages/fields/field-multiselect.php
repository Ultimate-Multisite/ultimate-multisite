<?php
/**
 * Multi-select field view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<li 
	class="<?php echo esc_attr(trim($field->wrapper_classes)); ?>" 
	<?php $field->print_wrapper_html_attributes(); ?>
>

	<div class="wu-w-full">

	<?php

	/**
	 * Adds the partial title template.
	 *
	 * @since 2.0.0
	 */
	wu_get_template(
		'admin-pages/fields/partials/field-title',
		[
			'field' => $field,
		]
	);

	/**
	 * Adds the partial title template.
	 *
	 * @since 2.0.0
	 */
	wu_get_template(
		'admin-pages/fields/partials/field-description',
		[
			'field' => $field,
		]
	);

	?>

	<ul data-columns="<?php echo esc_attr($field->columns); ?>" class='items wu--mx-1 wu-overflow-hidden wu-multiselect-content wu-static wu-my-2'>

		<?php foreach ($field->options as $value => $option) : ?>

		<?php
		// Support both string options ('key' => 'Label') and array options ('key' => ['title' => 'Label', 'desc' => '...'])
		$option_title = is_array($option) ? ($option['title'] ?? '') : (string) $option;
		$option_desc  = is_array($option) ? ($option['desc'] ?? '') : '';
		?>

		<li class="item wu-box-border wu-m-0 wu-my-2">

			<div class="wu-bg-gray-100 wu-p-3 wu-m-0 wu-border-gray-300 wu-border-solid wu-border wu-rounded wu-items-center wu-flex wu-justify-between">

			<span class="wu-block">

				<span class="wu-my-1 wu-text-xs wu-font-bold wu-block">

				<?php echo esc_html($option_title); ?>

				</span>

				<?php if ( ! empty($option_desc)) : ?>

				<span class="wu-my-1 wu-inline-block wu-text-xs">

					<?php echo esc_html($option_desc); ?>

				</span>

				<?php endif; ?>

			</span>

			<span class="wu-block wu-ml-2">

				<div class="wu-toggle">

				<input <?php checked(in_array($value, (array) $field->value, true)); ?> value="<?php echo esc_attr($value); ?>" id="<?php echo esc_attr("{$field->id}_{$value}"); ?>" type="checkbox" name="<?php echo esc_attr("{$field->id}[]"); ?>" class="wu-tgl wu-tgl-ios" <?php $field->print_html_attributes(); ?>>

				<label for="<?php echo esc_attr("{$field->id}_{$value}"); ?>" class="wu-tgl-btn wp-ui-highlight"></label>

				</div>

			</span>

			</div>

		</li>

		<?php endforeach; ?>

	</ul>

	</div>

</li>
