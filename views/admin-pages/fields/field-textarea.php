<?php
/**
 * Textarea field view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

$field = $field ?? null;

if ( ! $field instanceof \WP_Ultimo\UI\Field) {
	return;
}

$wrapper_classes = $field->wrapper_classes;

if (is_callable($wrapper_classes)) {
	$wrapper_classes = call_user_func($wrapper_classes, $field);
}

$wrapper_classes = is_scalar($wrapper_classes) || (is_object($wrapper_classes) && method_exists($wrapper_classes, '__toString'))
	? (string) $wrapper_classes
	: '';

$field_classes = $field->classes;

if (is_callable($field_classes)) {
	$field_classes = call_user_func($field_classes, $field);
}

$field_classes = is_scalar($field_classes) || (is_object($field_classes) && method_exists($field_classes, '__toString'))
	? (string) $field_classes
	: '';

$placeholder = $field->placeholder;

if (is_callable($placeholder)) {
	$placeholder = call_user_func($placeholder, $field);
}

$placeholder = is_scalar($placeholder) || (is_object($placeholder) && method_exists($placeholder, '__toString'))
	? (string) $placeholder
	: '';

$value = $field->value;

if (is_callable($value)) {
	$value = call_user_func($value, $field);
}

$value = is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))
	? (string) $value
	: '';

?>
<li class="<?php echo esc_attr(trim($wrapper_classes)); ?>" <?php $field->print_wrapper_html_attributes(); ?>>

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

	?>

	<textarea class="form-control wu-w-full wu-my-1 <?php echo esc_attr(trim($field_classes)); ?>" name="<?php echo esc_attr($field->id); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" <?php $field->print_html_attributes(); ?>><?php echo esc_attr($value); ?></textarea>

	<?php

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

	</div>

</li>
