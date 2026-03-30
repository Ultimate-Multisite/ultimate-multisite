<?php
/**
 * Form view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div class="wu-styling">
	<form
		id="wu_form"
		method="post"
		class="wu_checkout_form_<?php echo esc_attr($checkout_form_name); ?> wu-relative"
		<?php echo isset($checkout_form_action) ? 'action="' . esc_attr($checkout_form_action) . '"' : ''; ?>
	>

		<?php

		/**
		 * Display possible errors with the checkout.
		 */
		do_action('wu_checkout_errors', $checkout_form_name);

		/*
		 * When the user is already logged in, include user_id as a hidden
		 * field so both client-side and server-side validation know that
		 * email/username/password fields are not required.
		 */
		if (is_user_logged_in()) :
		?>
			<input type="hidden" name="user_id" value="<?php echo esc_attr(get_current_user_id()); ?>">
		<?php endif;

		/**
		 * Instantiate the form for the order details.
		 *
		 * @since 2.0.0
		 */
		$form = new \WP_Ultimo\UI\Form(
			"checkout-{$step_name}",
			$final_fields,
			[
				'title'                 => $display_title ? $step['name'] : '',
				'views'                 => 'checkout/fields',
				'classes'               => wu_get_isset($step, 'classes', '') . ' wu-grid wu-grid-cols-2 wu-gap-4',
				'field_wrapper_classes' => 'wu-col-span-2',
				'html_attr'             => [
					'id' => wu_get_isset($step, 'element_id') ?: "wu-step-{$step_name}",
				],
				'variables'             => [
					'step' => (object) $step,
				],
			]
		);

		/**
		 * Render form fields.
		 *
		 * @see /view/checkout/fields/ for the template files for each field type.
		 * @since 2.0.0
		 */
		$form->render();

		/**
		 * Add a security nonce field.
		 */
		wp_nonce_field('wu_checkout');

		?>

		<input type="hidden" name="checkout_action" value="wu_checkout">

		<input type="hidden" name="checkout_step" value="<?php echo esc_attr($step_name); ?>">

		<input type="hidden" name="checkout_form" value="<?php echo esc_attr($checkout_form_name); ?>">

	</form>

	<?php

	/**
	 * Renders additional things after the form ios over.
	 */
	do_action("wu_checkout_{$checkout_form_name}_after_form");

	/**
	 * Allow to add after our checkout form.
	 */
	do_action('wu_checkout_after_form');

	?>

</div>
