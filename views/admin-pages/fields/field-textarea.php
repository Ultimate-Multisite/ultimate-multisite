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

$wrapper_classes = wu_resolve_template_string($field->wrapper_classes, $field);
$field_classes   = wu_resolve_template_string($field->classes, $field);
$placeholder     = wu_resolve_template_string($field->placeholder, $field);
$value           = wu_resolve_template_string($field->value, $field);

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

	<textarea class="form-control wu-w-full wu-my-1 <?php echo esc_attr(trim($field_classes)); ?>" name="<?php echo esc_attr($field->id); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" <?php $field->print_html_attributes(); ?>><?php echo esc_textarea($value); ?></textarea>

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
