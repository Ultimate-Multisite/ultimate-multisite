<?php
/**
 * Create a test product with a 14-day free trial for e2e testing.
 * Also adds it to the checkout form's pricing table.
 */
$product = new WP_Ultimo\Models\Product();
$product->set_name('Trial Plan');
$product->set_slug('trial-plan');
$product->set_amount(19.99);
$product->set_duration(1);
$product->set_duration_unit('month');
$product->set_trial_duration(14);
$product->set_trial_duration_unit('day');
$product->set_type('plan');
$product->set_active(true);
$product->save();

$product_id = $product->get_id();

// Add the trial product to the checkout form's pricing table.
$form = WP_Ultimo\Models\Checkout_Form::query(['number' => 1]);

if ( $form ) {
	$form     = $form[0];
	$settings = $form->get_settings();

	foreach ( $settings as &$step ) {
		if ( ! isset($step['fields']) ) {
			continue;
		}

		foreach ( $step['fields'] as &$field ) {
			if ( isset($field['id']) && 'pricing_table' === $field['id'] ) {
				$existing                        = $field['pricing_table_products'] ?? '';
				$field['pricing_table_products'] = $existing ? $existing . ',' . $product_id : (string) $product_id;
			}
		}
	}

	$form->set_settings($settings);
	$form->save();
}

echo esc_html($product_id);
