<?php
/**
 * Checkout Completed Email Template - Admin
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<p><?php esc_html_e('Hey there', 'ultimate-multisite'); ?></p>
<p>{{payment_email_admin_intro}}</p>

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
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Amount Received', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{payment_total}}</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Paid With', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{payment_gateway}}</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Payment', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;"><a href="{{payment_manage_url}}" style="text-decoration: none;" rel="nofollow">{{payment_reference_code}}</a></td>
	</tr>
	</tbody>
</table>
{{/payment_is_paid}}

{{#payment_is_free_signup}}
<h2><b><?php esc_html_e('Free Membership', 'ultimate-multisite'); ?></b></h2>

<table cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse;">
	<tbody>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Products', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{payment_product_names}}</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Cost Today', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;"><?php esc_html_e('No payment collected', 'ultimate-multisite'); ?></td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Membership', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;"><a href="{{membership_manage_url}}" style="text-decoration: none;" rel="nofollow">{{membership_reference_code}}</a></td>
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
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Membership', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;"><a href="{{membership_manage_url}}" style="text-decoration: none;" rel="nofollow">{{membership_reference_code}}</a></td>
	</tr>
	</tbody>
</table>
{{/payment_is_trial}}

<h2><b><?php esc_html_e('Customer', 'ultimate-multisite'); ?></b></h2>

<table cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse;">
	<tbody>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Customer', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{customer_name}}</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Email Address', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;"><a href="mailto:{{customer_user_email}}" style="text-decoration: none;" rel="nofollow">{{customer_user_email}}</a></td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Admin Panel', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;"><a href="{{customer_manage_url}}" style="text-decoration: none;" rel="nofollow"><?php esc_html_e('Go to Customer &rarr;', 'ultimate-multisite'); ?></a></td>
	</tr>
	</tbody>
</table>
