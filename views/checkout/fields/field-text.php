<?php
/**
 * Text field view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div class="<?php echo esc_attr(trim($field->wrapper_classes)); ?>" <?php $field->print_wrapper_html_attributes(); ?>>

	<?php

	/**
	 * Adds the partial title template.
	 *
	 * @since 2.0.0
	 */
	wu_get_template(
		'checkout/fields/partials/field-title',
		[
			'field' => $field,
		]
	);

	?>

	<?php if ($field->prefix) : ?>

	<div class="sm:wu-flex wu-items-stretch wu-content-center">

		<div <?php wu_print_html_attributes($field->prefix_html_attr ?? []); ?>>
			<?php echo wp_kses($field->prefix, wu_kses_allowed_html()); ?>
		</div>

		<?php endif; ?>

		<?php
		// Check if Vue is handling the name dynamically to avoid duplicate attributes
		$has_vue_name = isset($field->html_attr['v-bind:name']);
		?>
		<input class="form-control wu-w-full wu-my-1 <?php echo esc_attr(trim($field->classes)); ?>" id="field-<?php echo esc_attr($field->id); ?>" 
		<?php
		if ( ! $has_vue_name) :
			?>
			name="<?php echo esc_attr($field->id); ?>" <?php endif; ?>type="<?php echo esc_attr($field->type); ?>" placeholder="<?php echo esc_attr($field->placeholder); ?>" value="<?php echo esc_attr($field->value); ?>" <?php $field->print_html_attributes(); ?>>

		<?php if ($field->suffix) : ?>

			<div <?php wu_print_html_attributes($field->suffix_html_attr ?? []); ?>>
				<?php echo wp_kses($field->suffix, wu_kses_allowed_html()); ?>
			</div>

		<?php endif; ?>

		<?php if ($field->prefix || $field->suffix) : ?>

	</div>

<?php endif; ?>

	<?php

	/**
	 * Adds the partial error template.
	 *
	 * @since 2.0.0
	 */
	wu_get_template(
		'checkout/fields/partials/field-errors',
		[
			'field' => $field,
		]
	);

	?>

</div>
