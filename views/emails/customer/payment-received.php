<?php
/**
 * Payment Received Email Template
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<?php // translators: %s: Customer Name ?>
<p><?php printf(esc_html__('Hey %s,', 'ultimate-multisite'), '{{customer_name}}'); ?></p>
<p>{{payment_email_customer_intro}}</p>

{{#payment_is_paid}}
<p><a href="{{payment_invoice_url}}" style="text-decoration: none;" rel="nofollow"><?php esc_html_e('Download Receipt', 'ultimate-multisite'); ?></a></p>

<h2><b><?php esc_html_e('Payment Details', 'ultimate-multisite'); ?></b></h2>

<table cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse;">
	<tbody>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Products', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{payment_product_names}}</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Total Paid', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{payment_total}}</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Paid On', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{payment_date_created}}</td>
	</tr>
	</tbody>
</table>
{{/payment_is_paid}}

{{#payment_is_free_signup}}
<h2><b><?php esc_html_e('Membership Details', 'ultimate-multisite'); ?></b></h2>

<table cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse;">
	<tbody>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Products', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{payment_product_names}}</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Cost Today', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;"><?php esc_html_e('No payment required', 'ultimate-multisite'); ?></td>
	</tr>
	</tbody>
</table>
{{/payment_is_free_signup}}

{{#payment_is_trial}}
<h2><b><?php esc_html_e('Trial Details', 'ultimate-multisite'); ?></b></h2>

<table cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse;">
	<tbody>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Products', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{payment_product_names}}</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Trial Ends', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{membership_date_expiration}}</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('First Payment', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{membership_description}}</td>
	</tr>
	</tbody>
</table>
{{/payment_is_trial}}
