<?php
/**
 * Select icon field view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<li class="<?php echo esc_attr(trim($field->wrapper_classes)); ?>" <?php $field->print_wrapper_html_attributes(); ?>>

	<div class="wu-block wu-w-full">

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

	<div class="wu-flex wu-flex-wrap wu--mx-2 wu-mt-2">

		<?php
		$field_attributes = $field->get_attributes();

		$options = wu_get_isset($field_attributes, 'options', []);

		if (is_callable($options)) {
			$options = call_user_func($options, $field);
		}

		foreach ($options as $option_value => $option) :
			?>

			<?php
			/*
			* Set the default keys.
			*/
			$option = wp_parse_args(
				$option,
				[
					'tooltip' => '',
				]
			);

			?>

		<div class="wu-p-2 wu-box-border wu-flex <?php echo esc_attr($field->classes); ?>" style="height: 110px;">

			<label class="wu-w-full wu-relative wu-rounded wu-p-1 wu-border-solid wu-border wu-flex wu-items-center wu-justify-center wu-bg-gray-100 wu-text-gray-600 wu-border-gray-300" v-bind:class="require('<?php echo esc_attr($field->id); ?>', '<?php echo esc_attr($option_value); ?>') ? 'wu-bg-gray-200 wu-text-gray-700 wu-border-gray-400 selected' : '' " for="<?php echo esc_attr($field->id . '-' . $option_value); ?>">

			<div class="wu-text-center" <?php wu_tooltip_text($option['tooltip']); ?>>

				<span class="wu-block wu-text-2xl wu-mb-2 <?php echo esc_attr($option['icon']); ?>"></span>

				<input class="wu-w-0 wu-h-0 wu-hidden" id="<?php echo esc_attr($field->id . '-' . $option_value); ?>" type="radio" <?php checked($option_value, $field->value); ?> value="<?php echo esc_attr($option_value); ?>" name="<?php echo esc_attr($field->id); ?>" <?php $field->print_html_attributes(); ?>>

				<span class="wu-uppercase wu-text-2xs wu-font-semibold">

				<?php echo esc_html($option['title']); ?>

				</span>

			</div>

			</label>

		</div>

		<?php endforeach; ?>

	</div>

	</div>

</li>
