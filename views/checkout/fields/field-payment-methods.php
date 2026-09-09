<?php
/**
 * Payment methods field view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

$active_gateways = wu_get_active_gateway_as_options();

?>
<div class="<?php echo esc_attr(trim($field->wrapper_classes)); ?>" v-cloak v-show="order && order.should_collect_payment" <?php $field->print_wrapper_html_attributes(); ?>>

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

	<?php if (empty($active_gateways)) : ?>

		<div class="wu-p-3 wu-bg-yellow-50 wu-border wu-border-solid wu-border-yellow-200 wu-rounded-sm wu-text-yellow-800" role="alert">
			<?php esc_html_e('No payment methods are currently available. Please contact the site administrator.', 'ultimate-multisite'); ?>
		</div>

	<?php endif; ?>

	<?php foreach ($active_gateways as $option_value => $option_name) : ?>

		<?php if (count($active_gateways) === 1) : ?>

			<input
					id="field-gateway"
					type="hidden"
					name="gateway"
					value="<?php echo esc_attr($option_value); ?>"
					v-model="gateway"
				<?php $field->print_html_attributes(); ?>
			>

			<?php if (! empty($option_name)) : ?>
				<div class="wu-payment-method-badge wu-mb-2">
					<?php echo wp_kses_post($option_name); ?>
				</div>
			<?php endif; ?>

		<?php else : ?>

			<label class="wu-radio-option" for="field-<?php echo esc_attr($field->id); ?>-<?php echo esc_attr($option_value); ?>">

				<input
						id="field-<?php echo esc_attr($field->id); ?>-<?php echo esc_attr($option_value); ?>"
						type="radio"
						name="gateway"
						value="<?php echo esc_attr($option_value); ?>"
						v-model="gateway"
						class="wu-radio-input <?php echo esc_attr(trim($field->classes)); ?>"
					<?php $field->print_html_attributes(); ?>
					<?php checked((string) $field->value === (string) $option_value, true); ?>
				>

				<?php echo wp_kses_post($option_name); ?>

			</label>

		<?php endif; ?>

	<?php endforeach; ?>

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

	/**
	 * Load Gateway fields
	 *
	 * @since 2.0.0
	 */
	do_action('wu_checkout_gateway_fields');

	?>

</div>
