<?php
/**
 * Select field view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
/** @var $field \WP_Ultimo\UI\Field */

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

	<?php
	// Check if Vue is handling the name dynamically to avoid duplicate attributes
	$has_vue_name = isset($field->html_attr['v-bind:name']);
	?>
	<select
	class="form-control wu-w-full wu-my-1 <?php echo esc_attr(trim($field->classes)); ?>"
	id="field-<?php echo esc_attr($field->id); ?>"
	<?php if ( ! $has_vue_name) : ?>
	name="<?php echo esc_attr($field->id); ?>"
	<?php endif; ?>
	value="<?php echo esc_attr($field->value); ?>"
	<?php $field->print_html_attributes(); ?>
	>

	<?php if ($field->placeholder) : ?>

	<option <?php checked(! $field->value); ?> class="wu-opacity-75"><?php echo esc_html($field->placeholder); ?></option>

	<?php endif; ?>

	<?php foreach ($field->options as $key => $label) : ?>

		<option
		value="<?php echo esc_attr($key); ?>"
		<?php checked($key, $field->value); ?>
		>
		<?php echo esc_html($label); ?>
	</option>

	<?php endforeach; ?>

	<?php if ($field->options_template) : ?>

		<?php echo wp_kses($field->options_template, wu_kses_allowed_html()); ?>

	<?php endif; ?>

	</select>

	<?php

	/**
	 * Adds the partial title template.
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
