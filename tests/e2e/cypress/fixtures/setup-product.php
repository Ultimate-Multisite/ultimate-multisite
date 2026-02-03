<?php
/**
 * Create a test product/plan for e2e testing.
 */
$product = new WP_Ultimo\Models\Product();
$product->set_name('Test Plan');
$product->set_slug('test-plan');
$product->set_amount(29.99);
$product->set_duration(1);
$product->set_duration_unit('month');
$product->set_type('plan');
$product->set_active(true);
$product->save();

echo esc_html($product->get_id());
